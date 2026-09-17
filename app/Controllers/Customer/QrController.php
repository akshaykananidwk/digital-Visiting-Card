<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\QrCode;
use App\Core\Response;
use App\Core\Url;
use App\Models\QrCodeRecord;
use App\Services\PlanLimiter;
use Throwable;

final class QrController extends PanelController
{
    public function index(string $id): Response
    {
        $card = $this->ownedCard($id);
        $record = (new QrCodeRecord())->sync((int) $card['id'], Url::card((string) $card['slug']) . '?src=qr');

        $preview = '';
        try {
            $qr = new QrCode((string) $record['target_url'], (string) $record['ecc_level']);
            $preview = 'data:image/svg+xml;base64,' . base64_encode(
                $qr->svg(8, 4, (string) $record['foreground'], (string) $record['background'])
            );
        } catch (Throwable $e) {
            $this->error('QR generation failed: ' . $e->getMessage());
        }

        return $this->render('customer.qr', [
            'title'       => 'QR code: ' . (string) $card['title'],
            'card'        => $card,
            'record'      => $record,
            'preview'     => $preview,
            'canDownload' => PlanLimiter::canDownloadQr($this->userId()),
            'publicUrl'   => Url::card((string) $card['slug']),
        ]);
    }

    public function update(string $id): Response
    {
        $card = $this->ownedCard($id);

        $foreground = $this->colour($this->request->string('foreground', '#000000'), '#000000');
        $background = $this->colour($this->request->string('background', '#FFFFFF'), '#FFFFFF');
        $ecc = strtoupper($this->request->string('ecc_level', 'M'));
        $ecc = in_array($ecc, ['L', 'M', 'Q', 'H'], true) ? $ecc : 'M';

        // A low-contrast QR will not scan; refuse it rather than ship a
        // code the customer will print and find unusable.
        if (\App\Services\TemplateRenderer::contrastRatio($foreground, $background) < 3.0) {
            $this->error('Those two colours do not have enough contrast for a scannable QR code. Pick a darker foreground or a lighter background.');

            return $this->redirect('cards/' . (int) $card['id'] . '/qr');
        }

        (new QrCodeRecord())->sync(
            (int) $card['id'],
            Url::card((string) $card['slug']) . '?src=qr',
            $foreground,
            $background,
            $ecc
        );

        $this->success('QR code style updated.');

        return $this->redirect('cards/' . (int) $card['id'] . '/qr');
    }

    public function download(string $id): Response
    {
        $card = $this->ownedCard($id);

        if (!PlanLimiter::canDownloadQr($this->userId())) {
            $this->error('QR download is not included in your current plan.');

            return $this->redirect('billing');
        }

        $record = (new QrCodeRecord())->sync((int) $card['id'], Url::card((string) $card['slug']) . '?src=qr');
        $format = strtolower($this->request->string('format', 'png'));
        $size = max(4, min(30, $this->request->int('size', 14)));

        try {
            $qr = new QrCode((string) $record['target_url'], (string) $record['ecc_level']);
        } catch (Throwable $e) {
            $this->error('QR generation failed: ' . $e->getMessage());

            return $this->redirect('cards/' . (int) $card['id'] . '/qr');
        }

        $filename = 'qr-' . (string) $card['slug'];

        if ($format === 'svg') {
            return Response::download(
                $qr->svg($size, 4, (string) $record['foreground'], (string) $record['background']),
                $filename . '.svg',
                'image/svg+xml'
            );
        }

        return Response::download(
            $qr->png($size, 4, (string) $record['foreground'], (string) $record['background']),
            $filename . '.png',
            'image/png'
        );
    }

    private function colour(string $value, string $fallback): string
    {
        $value = trim($value);
        if (!str_starts_with($value, '#')) {
            $value = '#' . $value;
        }

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1 ? $value : $fallback;
    }
}
