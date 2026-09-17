<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\AuditLog;
use App\Core\Response;
use App\Core\Uploader;
use App\Core\Url;
use App\Models\Card;
use App\Models\CardGallery;
use App\Models\CardProduct;
use App\Models\CardSection;
use App\Models\CardService as CardServiceModel;
use App\Models\CardSocialLink;
use App\Models\QrCodeRecord;
use App\Models\Template;
use App\Services\CardPresenter;
use App\Services\PlanLimiter;
use Throwable;

/** The live card editor: profile, business, contact, social, SEO, settings. */
final class EditorController extends PanelController
{
    /** Image fields the editor can upload, with their storage folder. */
    private const IMAGE_FIELDS = [
        'profile_image' => ['folder' => 'cards', 'width' => 800],
        'cover_image'   => ['folder' => 'cards', 'width' => 1600],
        'logo_image'    => ['folder' => 'logos', 'width' => 600],
        'seo_image'     => ['folder' => 'cards', 'width' => 1200],
    ];

    public function index(string $id): Response
    {
        $card = $this->ownedCard($id);
        $userId = $this->userId();

        return $this->render('customer.editor', [
            'title'     => 'Edit: ' . (string) $card['title'],
            'card'      => $card,
            'template'  => $card['template_id'] !== null ? (new Template())->find((int) $card['template_id']) : null,
            'sections'  => (new CardSection())->forCard((int) $card['id']),
            'social'    => (new CardSocialLink())->mapForCard((int) $card['id']),
            'counts'    => [
                'services' => (new CardServiceModel())->count(['card_id' => (int) $card['id']]),
                'products' => (new CardProduct())->count(['card_id' => (int) $card['id']]),
                'gallery'  => (new CardGallery())->countForCard((int) $card['id']),
            ],
            'tab'       => $this->request->string('tab', 'profile'),
            'publicUrl' => Url::card((string) $card['slug']),
            'canSeo'    => PlanLimiter::canUseSeoControls($userId),
            'platforms' => CardSocialLink::PLATFORMS,
        ]);
    }

    public function saveProfile(string $id): Response
    {
        $card = $this->ownedCard($id);

        $data = $this->validate([
            'title'       => 'required|string|min:2|max:150',
            'full_name'   => 'required|string|min:2|max:150',
            'designation' => 'nullable|string|max:150',
            'tagline'     => 'nullable|string|max:255',
            'about'       => 'nullable|string|max:5000',
        ]);

        (new Card())->updateById((int) $card['id'], $data);
        $this->success('Profile details saved.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor?tab=profile');
    }

    public function saveBusiness(string $id): Response
    {
        $card = $this->ownedCard($id);

        $data = $this->validate([
            'business_name'     => 'nullable|string|max:190',
            'business_category' => 'nullable|string|max:120',
            'address'           => 'nullable|string|max:500',
            'city'              => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:100',
            'pincode'           => 'nullable|string|max:12',
            'country'           => 'nullable|string|max:100',
            'map_link'          => 'nullable|url|max:500',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
            'upi_id'            => 'nullable|string|max:100',
            'payment_note'      => 'nullable|string|max:255',
        ]);

        $data['business_hours'] = $this->parseHours();

        (new Card())->updateById((int) $card['id'], $data);
        $this->success('Business details saved.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor?tab=business');
    }

    public function saveContact(string $id): Response
    {
        $card = $this->ownedCard($id);

        $data = $this->validate([
            'phone'            => 'nullable|phone|max:25',
            'phone_alt'        => 'nullable|phone|max:25',
            'whatsapp'         => 'nullable|phone|max:25',
            'whatsapp_message' => 'nullable|string|max:255',
            'email'            => 'nullable|email',
            'website'          => 'nullable|url|max:255',
        ]);

        (new Card())->updateById((int) $card['id'], $data);
        $this->success('Contact details saved.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor?tab=contact');
    }

    public function saveSocial(string $id): Response
    {
        $card = $this->ownedCard($id);

        $links = [];
        foreach (array_keys(CardSocialLink::PLATFORMS) as $platform) {
            $links[$platform] = $this->request->string('social_' . $platform);
        }

        (new CardSocialLink())->sync((int) $card['id'], $links);
        $this->success('Social links saved.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor?tab=social');
    }

    public function saveSeo(string $id): Response
    {
        $card = $this->ownedCard($id);

        if (!PlanLimiter::canUseSeoControls($this->userId())) {
            $this->error('SEO controls are not included in your current plan.');

            return $this->redirect('billing');
        }

        $data = $this->validate([
            'seo_title'       => 'nullable|string|max:190',
            'seo_description' => 'nullable|string|max:320',
            'seo_keywords'    => 'nullable|string|max:255',
        ]);

        $settings = is_array($card['settings'] ?? null) ? $card['settings'] : [];
        $settings['noindex'] = $this->request->bool('noindex');

        (new Card())->updateById((int) $card['id'], $data + ['settings' => $settings]);
        $this->success('SEO settings saved.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor?tab=seo');
    }

    public function saveSettings(string $id): Response
    {
        $card = $this->ownedCard($id);

        $settings = is_array($card['settings'] ?? null) ? $card['settings'] : [];
        $settings['enquiry_enabled'] = $this->request->bool('enquiry_enabled');
        $settings['show_qr'] = $this->request->bool('show_qr');
        $settings['vcard_enabled'] = $this->request->bool('vcard_enabled');

        (new Card())->updateById((int) $card['id'], ['settings' => $settings]);
        $this->success('Card settings saved.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor?tab=settings');
    }

    public function saveSections(string $id): Response
    {
        $card = $this->ownedCard($id);
        $sections = new CardSection();

        $order = $this->request->array('order');
        if ($order !== []) {
            $sections->reorder((int) $card['id'], array_map('strval', $order));
        }

        $enabled = $this->request->array('enabled');
        foreach (array_keys(CardSection::DEFAULTS) as $section) {
            $sections->setEnabled((int) $card['id'], $section, in_array($section, array_map('strval', $enabled), true));
        }

        $this->success('Section layout saved.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor?tab=sections');
    }

    public function uploadMedia(string $id): Response
    {
        $card = $this->ownedCard($id);
        $field = $this->request->string('field');

        if (!array_key_exists($field, self::IMAGE_FIELDS)) {
            return $this->fail('Unknown image field.', 422);
        }

        $file = $this->request->file('image');
        if ($file === null) {
            $this->error('Please choose an image to upload.');

            return $this->back('cards/' . (int) $card['id'] . '/editor');
        }

        $storage = PlanLimiter::canUseStorage($this->userId(), (int) ($file['size'] ?? 0));
        if (!$storage['allowed']) {
            $this->error($storage['message']);

            return $this->back('cards/' . (int) $card['id'] . '/editor');
        }

        try {
            $config = self::IMAGE_FIELDS[$field];
            $path = (new Uploader())->image($file, $config['folder'], $config['width']);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return $this->back('cards/' . (int) $card['id'] . '/editor');
        }

        // Remove the previous file so storage is not silently consumed.
        if (!empty($card[$field])) {
            Uploader::delete((string) $card[$field]);
        }

        (new Card())->updateById((int) $card['id'], [$field => $path]);
        AuditLog::record('card.media_uploaded', 'card', (int) $card['id'], ['field' => $field]);

        $this->success('Image updated.');

        return $this->back('cards/' . (int) $card['id'] . '/editor');
    }

    public function removeMedia(string $id): Response
    {
        $card = $this->ownedCard($id);
        $field = $this->request->string('field');

        if (!array_key_exists($field, self::IMAGE_FIELDS)) {
            return $this->fail('Unknown image field.', 422);
        }

        if (!empty($card[$field])) {
            Uploader::delete((string) $card[$field]);
        }
        (new Card())->updateById((int) $card['id'], [$field => null]);

        $this->success('Image removed.');

        return $this->back('cards/' . (int) $card['id'] . '/editor');
    }

    /** @return array<string,array<string,mixed>> */
    private function parseHours(): array
    {
        $hours = [];
        foreach (array_keys(CardPresenter::DAYS) as $day) {
            $closed = $this->request->bool('hours_' . $day . '_closed');
            $open = $this->request->string('hours_' . $day . '_open');
            $close = $this->request->string('hours_' . $day . '_close');

            $valid = static fn (string $time): string => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : '';

            $hours[$day] = [
                'closed' => $closed,
                'open'   => $closed ? '' : $valid($open),
                'close'  => $closed ? '' : $valid($close),
            ];
        }

        return $hours;
    }
}
