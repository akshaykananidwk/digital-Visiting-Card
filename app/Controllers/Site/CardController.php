<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\QrCode;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Url;
use App\Models\Card;
use App\Models\CardDailyStat;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\QrCodeRecord;
use App\Models\Template;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\CardPresenter;
use App\Services\PlanLimiter;
use App\Services\TemplateRenderer;
use App\Services\VCardService;
use Throwable;

/** Public-facing digital card: render, vCard, QR and enquiry handling. */
final class CardController extends Controller
{
    public function show(string $slug): Response
    {
        return $this->renderCard($slug);
    }

    /** Pretty URL: https://domain.com/{slug} */
    public function showDirect(string $slug): Response
    {
        $mode = (string) (Settings::get('card_url_mode') ?: 'both');
        if ($mode === 'card_only') {
            throw HttpException::notFound();
        }

        return $this->renderCard($slug);
    }

    private function renderCard(string $slug): Response
    {
        $card = (new Card())->findPublishedWithRelations($slug);

        if ($card === null) {
            throw HttpException::notFound('We could not find a digital card at this address.');
        }

        $state = $this->availability($card);
        if ($state !== null) {
            return $this->render('card.notice', $state, $state['status']);
        }

        $template = $card['template_id'] !== null ? (new Template())->find((int) $card['template_id']) : null;
        $presenter = new CardPresenter($card, $template);
        $design = TemplateRenderer::forCard($card, $template);

        $ownerId = (int) $card['user_id'];
        $showBranding = (bool) Settings::get('show_platform_branding', true)
            && !PlanLimiter::canRemoveBranding($ownerId);

        (new AnalyticsService())->recordView((int) $card['id'], $this->request, $this->request->string('src') ?: null);

        return $this->render('card.show', [
            'card'            => $presenter,
            'design'          => $design,
            'showBranding'    => $showBranding,
            'trackingEnabled' => PlanLimiter::canUseAnalytics($ownerId),
            'qrDataUri'       => $this->qrDataUri($presenter),
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=300')
          ->header('X-Robots-Tag', (bool) $presenter->setting('noindex', false) ? 'noindex' : 'index, follow');
    }

    /**
     * Decide whether a card may be displayed. Returns view data for the
     * notice page when it may not.
     *
     * @param array<string,mixed> $card
     * @return array<string,mixed>|null
     */
    private function availability(array $card): ?array
    {
        if ((string) ($card['owner_status'] ?? 'active') !== 'active') {
            return [
                'heading'  => 'Card unavailable',
                'message'  => 'This digital card is temporarily unavailable. Please contact the card owner.',
                'iconName' => 'lock',
                'status'   => 404,
            ];
        }

        if ((string) $card['status'] === 'draft') {
            return [
                'heading'  => 'Card not found',
                'message'  => 'We could not find a digital card at this address.',
                'iconName' => 'search',
                'status'   => 404,
            ];
        }

        if ((string) $card['status'] === 'suspended') {
            return [
                'heading'  => 'Card suspended',
                'message'  => 'This digital card has been suspended by the administrator.',
                'iconName' => 'lock',
                'status'   => 403,
            ];
        }

        $expiresAt = $card['expires_at'] ?? null;
        $expired = (string) $card['status'] === 'expired'
            || ($expiresAt !== null && strtotime((string) $expiresAt) < time());

        if (!$expired) {
            return null;
        }

        return match (PlanLimiter::expiryBehaviour()) {
            'keep_live' => null,
            'disable'   => [
                'heading'  => 'Card not found',
                'message'  => 'We could not find a digital card at this address.',
                'iconName' => 'search',
                'status'   => 404,
            ],
            default     => [
                'heading'     => 'This card has expired',
                'message'     => 'The subscription for this digital card has ended. If this is your card, sign in and renew your plan to bring it back online.',
                'iconName'    => 'clock',
                'status'      => 410,
                'ownerAction' => true,
            ],
        };
    }

    private function qrDataUri(CardPresenter $card): string
    {
        try {
            $record = (new QrCodeRecord())->forCard($card->id());
            $qr = new QrCode(
                $card->url() . '?src=qr',
                (string) ($record['ecc_level'] ?? 'M')
            );

            return 'data:image/svg+xml;base64,' . base64_encode($qr->svg(
                6,
                3,
                (string) ($record['foreground'] ?? '#000000'),
                (string) ($record['background'] ?? '#FFFFFF')
            ));
        } catch (Throwable $e) {
            Logger::warning('QR generation failed: ' . $e->getMessage(), ['card' => $card->id()]);

            return '';
        }
    }

    // --------------------------------------------------------------- vCard --

    public function vcard(string $slug): Response
    {
        $card = (new Card())->findPublished($slug);
        if ($card === null) {
            throw HttpException::notFound();
        }

        if (!PlanLimiter::feature((int) $card['user_id'], 'vcard')) {
            throw HttpException::forbidden('Contact download is not available for this card.');
        }

        (new AnalyticsService())->recordEvent((int) $card['id'], 'save_contact', $this->request, 'vcf');

        return Response::download(
            VCardService::build($card),
            VCardService::filename($card),
            'text/vcard; charset=utf-8'
        );
    }

    // ------------------------------------------------------------------ QR --

    public function qr(string $slug): Response
    {
        $card = (new Card())->findPublished($slug);
        if ($card === null) {
            throw HttpException::notFound();
        }

        $format = strtolower($this->request->string('format', 'svg'));
        $size = max(2, min(20, $this->request->int('size', 10)));
        $download = $this->request->bool('download');

        $record = (new QrCodeRecord())->forCard((int) $card['id']);
        $foreground = $this->sanitiseColor($this->request->string('fg', (string) ($record['foreground'] ?? '#000000')));
        $background = $this->sanitiseColor($this->request->string('bg', (string) ($record['background'] ?? '#FFFFFF')));

        $qr = new QrCode(Url::card((string) $card['slug']) . '?src=qr', (string) ($record['ecc_level'] ?? 'M'));
        $filename = 'qr-' . (string) $card['slug'];

        if ($format === 'png') {
            $binary = $qr->png($size, 4, $foreground, $background);

            return $download
                ? Response::download($binary, $filename . '.png', 'image/png')
                : Response::make($binary, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
        }

        $svg = $qr->svg($size, 4, $foreground, $background);

        return $download
            ? Response::download($svg, $filename . '.svg', 'image/svg+xml')
            : Response::make($svg, 200, ['Content-Type' => 'image/svg+xml; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']);
    }

    private function sanitiseColor(string $color): string
    {
        $color = trim($color);
        if (!str_starts_with($color, '#')) {
            $color = '#' . $color;
        }

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) === 1 ? $color : '#000000';
    }

    // ------------------------------------------------------------- Enquiry --

    public function enquiry(string $slug): Response
    {
        $card = (new Card())->findPublished($slug);
        if ($card === null) {
            throw HttpException::notFound();
        }

        $settings = is_array($card['settings'] ?? null) ? $card['settings'] : [];
        if (($settings['enquiry_enabled'] ?? true) === false) {
            return $this->fail('Enquiries are not accepted on this card.', 403);
        }
        if (!PlanLimiter::feature((int) $card['user_id'], 'enquiry_form')) {
            return $this->fail('Enquiries are not available on this card.', 403);
        }

        // Spam controls: honeypot + minimum fill time + per-IP throttle.
        if ($this->request->string('website_url') !== '') {
            Logger::info('Enquiry honeypot triggered', ['card' => $card['id'], 'ip' => $this->request->ip()]);

            return $this->ok('Thank you! Your message has been sent.');
        }
        $formTime = $this->request->int('form_time');
        if ($formTime > 0 && (time() - $formTime) < 3) {
            return $this->fail('Please take a moment to fill in the form.', 422);
        }

        RateLimiter::throttle('enquiry', $this->request->ip() . ':' . $card['id']);

        $data = $this->validate([
            'name'    => 'required|string|min:2|max:150',
            'phone'   => 'required|phone|max:25',
            'email'   => 'nullable|email',
            'message' => 'nullable|string|max:2000',
            'subject' => 'nullable|string|max:190',
        ]);

        $leadId = (new Lead())->create([
            'card_id'    => (int) $card['id'],
            'user_id'    => (int) $card['user_id'],
            'name'       => (string) $data['name'],
            'phone'      => (string) $data['phone'],
            'email'      => $data['email'] ?? null,
            'subject'    => $data['subject'] ?? null,
            'message'    => $data['message'] ?? null,
            'source'     => 'card_form',
            'status'     => 'new',
            'ip_hash'    => hash('sha256', $this->request->ip() . date('Y-m-d')),
            'user_agent' => $this->request->userAgent(),
            'created_at' => now(),
        ]);

        $this->db()->execute(
            'UPDATE `' . $this->db()->table('cards') . '` SET `leads_count` = `leads_count` + 1 WHERE `id` = :id',
            ['id' => (int) $card['id']]
        );
        (new CardDailyStat())->bump((int) $card['id'], 'lead');

        $this->notifyOwner($card, $data, $leadId);

        if ($this->request->wantsJson()) {
            return $this->ok('Thank you! Your message has been sent. We will contact you soon.');
        }

        $this->success('Thank you! Your message has been sent.');

        return $this->redirectAway(Url::card($slug) . '#enquiry');
    }

    /**
     * @param array<string,mixed> $card
     * @param array<string,mixed> $data
     */
    private function notifyOwner(array $card, array $data, int $leadId): void
    {
        try {
            $owner = (new User())->find((int) $card['user_id']);
            if ($owner === null) {
                return;
            }

            (new Notification())->push(
                (int) $owner['id'],
                'lead.received',
                'New enquiry on ' . (string) $card['title'],
                (string) $data['name'] . ' · ' . (string) $data['phone'],
                'leads/' . $leadId
            );

            if ((bool) Settings::get('notify_user_on_lead', true) && !empty($owner['email'])) {
                $body = '<p>You have received a new enquiry on your digital card <strong>'
                    . e((string) $card['title']) . '</strong>.</p>'
                    . '<p><strong>Name:</strong> ' . e((string) $data['name']) . '<br>'
                    . '<strong>Phone:</strong> ' . e((string) $data['phone']) . '<br>'
                    . (!empty($data['email']) ? '<strong>Email:</strong> ' . e((string) $data['email']) . '<br>' : '')
                    . '</p>'
                    . (!empty($data['message']) ? '<p><strong>Message:</strong><br>' . nl2br(e((string) $data['message'])) . '</p>' : '');

                \App\Core\Mailer::send(
                    (string) $owner['email'],
                    'New enquiry from ' . (string) $data['name'],
                    \App\Core\Mailer::layout('New enquiry received', $body, 'Open lead', Url::to('leads/' . $leadId)),
                    null,
                    ['user_id' => (int) $owner['id'], 'event' => 'lead.received']
                );
            }
        } catch (Throwable $e) {
            Logger::warning('Lead notification failed: ' . $e->getMessage());
        }
    }
}
