<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Core\Url;
use App\Models\CardGallery;
use App\Models\CardProduct;
use App\Models\CardSection;
use App\Models\CardService as CardServiceModel;
use App\Models\CardSocialLink;
use App\Models\Template;

/**
 * Prepares everything a public card needs for rendering: normalised links,
 * business-hours state, section ordering and the related content rows.
 * Keeping this out of the views means all 1,200 designs share one, tested
 * data pipeline.
 */
final class CardPresenter
{
    /** @var array<string,mixed> */
    private array $card;

    /** @var array<string,mixed>|null */
    private ?array $template;

    /** @var array<int,array<string,mixed>>|null */
    private ?array $services = null;

    /** @var array<int,array<string,mixed>>|null */
    private ?array $products = null;

    /** @var array<int,array<string,mixed>>|null */
    private ?array $gallery = null;

    /** @var array<string,string>|null */
    private ?array $social = null;

    /** @var array<string,array<string,mixed>>|null */
    private ?array $sections = null;

    public const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    /**
     * @param array<string,mixed> $card
     * @param array<string,mixed>|null $template
     */
    public function __construct(array $card, ?array $template = null)
    {
        $this->card = $card;
        $this->template = $template;
    }

    /** @return array<string,mixed> */
    public function card(): array
    {
        return $this->card;
    }

    public function id(): int
    {
        return (int) $this->card['id'];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->card[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public function has(string $key): bool
    {
        $value = $this->card[$key] ?? null;

        return $value !== null && $value !== '' && $value !== [];
    }

    // ------------------------------------------------------------- Basics --

    public function displayName(): string
    {
        return (string) ($this->get('full_name') ?? $this->get('title') ?? 'Digital card');
    }

    public function url(): string
    {
        return Url::card((string) $this->card['slug']);
    }

    /**
     * Up to two initials for the avatar placeholder. Two letters read as a
     * monogram rather than a single stray character, which is most of the
     * difference between a card that looks finished and one that does not
     * when no photo has been uploaded.
     */
    public function initials(): string
    {
        $source = trim((string) ($this->get('full_name') ?? $this->get('business_name') ?? $this->get('title') ?? ''));
        if ($source === '') {
            return '?';
        }

        $words = preg_split('/\s+/u', $source) ?: [];
        $letters = '';
        foreach ($words as $word) {
            $first = mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? '', 0, 1);
            if ($first !== '') {
                $letters .= $first;
            }
            if (mb_strlen($letters) === 2) {
                break;
            }
        }

        if ($letters === '') {
            $letters = mb_substr($source, 0, 1);
        }

        return mb_strtoupper($letters);
    }

    public function image(string $key): ?string
    {
        $value = $this->get($key);

        return $value === null ? null : upload_url((string) $value);
    }

    // -------------------------------------------------------------- Links --

    public function telLink(?string $number = null): ?string
    {
        $number = $number ?? (string) ($this->get('phone') ?? '');
        $clean = $this->cleanNumber($number);

        return $clean === '' ? null : 'tel:' . $clean;
    }

    public function whatsappLink(?string $message = null): ?string
    {
        $number = $this->cleanNumber((string) ($this->get('whatsapp') ?? $this->get('phone') ?? ''));
        if ($number === '') {
            return null;
        }
        $number = ltrim($number, '+');
        if (strlen($number) === 10) {
            $number = '91' . $number;                // sensible default for India
        }

        $text = $message
            ?? (string) ($this->get('whatsapp_message')
                ?? Settings::get('default_whatsapp_message')
                ?? 'Hello, I found your digital visiting card and would like to know more.');

        return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
    }

    public function emailLink(): ?string
    {
        $email = (string) ($this->get('email') ?? '');
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return 'mailto:' . $email;
    }

    public function websiteLink(): ?string
    {
        $website = (string) ($this->get('website') ?? '');
        if ($website === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $website)) {
            $website = 'https://' . ltrim($website, '/');
        }

        return filter_var($website, FILTER_VALIDATE_URL) === false ? null : $website;
    }

    public function websiteLabel(): string
    {
        $host = (string) parse_url((string) $this->websiteLink(), PHP_URL_HOST);

        return preg_replace('/^www\./', '', $host) ?: (string) $this->get('website', '');
    }

    public function directionsLink(): ?string
    {
        $mapLink = (string) ($this->get('map_link') ?? '');
        if ($mapLink !== '' && filter_var($mapLink, FILTER_VALIDATE_URL) !== false) {
            return $mapLink;
        }

        if ($this->has('latitude') && $this->has('longitude')) {
            return sprintf('https://www.google.com/maps/dir/?api=1&destination=%s,%s', $this->card['latitude'], $this->card['longitude']);
        }

        $address = $this->fullAddress();

        return $address === '' ? null : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
    }

    public function mapEmbedUrl(): ?string
    {
        if ($this->has('latitude') && $this->has('longitude')) {
            return sprintf(
                'https://www.google.com/maps?q=%s,%s&z=15&output=embed',
                $this->card['latitude'],
                $this->card['longitude']
            );
        }
        $address = $this->fullAddress();

        return $address === '' ? null : 'https://www.google.com/maps?q=' . rawurlencode($address) . '&z=15&output=embed';
    }

    public function fullAddress(): string
    {
        return trim(implode(', ', array_filter([
            (string) ($this->get('address') ?? ''),
            (string) ($this->get('city') ?? ''),
            (string) ($this->get('state') ?? ''),
            (string) ($this->get('pincode') ?? ''),
            (string) ($this->get('country') ?? ''),
        ], static fn (string $part): bool => trim($part) !== '')));
    }

    public function upiLink(): ?string
    {
        $upi = (string) ($this->get('upi_id') ?? '');
        if ($upi === '' || !str_contains($upi, '@')) {
            return null;
        }

        return 'upi://pay?pa=' . rawurlencode($upi) . '&pn=' . rawurlencode($this->displayName()) . '&cu=INR';
    }

    private function cleanNumber(string $number): string
    {
        $clean = preg_replace('/[^\d+]/', '', $number) ?? '';

        return strlen(preg_replace('/\D/', '', $clean) ?? '') >= 7 ? $clean : '';
    }

    // ------------------------------------------------------ Business hours --

    /**
     * @return array<int,array{key:string,label:string,closed:bool,open:string,close:string,today:bool}>
     */
    public function businessHours(): array
    {
        $hours = $this->card['business_hours'] ?? [];
        if (!is_array($hours) || $hours === []) {
            return [];
        }

        $todayKey = strtolower(date('D'));
        $rows = [];
        foreach (self::DAYS as $key => $label) {
            $day = $hours[$key] ?? null;
            if (!is_array($day)) {
                continue;
            }
            $rows[] = [
                'key'    => $key,
                'label'  => $label,
                'closed' => (bool) ($day['closed'] ?? false),
                'open'   => (string) ($day['open'] ?? ''),
                'close'  => (string) ($day['close'] ?? ''),
                'today'  => $key === $todayKey,
            ];
        }

        return $rows;
    }

    /** @return array{state:string,label:string}|null */
    public function openState(): ?array
    {
        $hours = $this->businessHours();
        if ($hours === []) {
            return null;
        }

        $todayKey = strtolower(date('D'));
        foreach ($hours as $row) {
            if ($row['key'] !== $todayKey) {
                continue;
            }
            if ($row['closed'] || $row['open'] === '' || $row['close'] === '') {
                return ['state' => 'closed', 'label' => 'Closed today'];
            }

            $now = (int) date('Hi');
            $open = (int) str_replace(':', '', $row['open']);
            $close = (int) str_replace(':', '', $row['close']);

            // Handle shifts that run past midnight.
            $isOpen = $close > $open ? ($now >= $open && $now < $close) : ($now >= $open || $now < $close);

            return $isOpen
                ? ['state' => 'open', 'label' => 'Open now · until ' . $this->formatTime($row['close'])]
                : ['state' => 'closed', 'label' => 'Closed · opens ' . $this->formatTime($row['open'])];
        }

        return null;
    }

    public function formatTime(string $time): string
    {
        $timestamp = strtotime($time);

        return $timestamp === false ? $time : date('g:i A', $timestamp);
    }

    // ------------------------------------------------------------ Content --

    public function isDemo(): bool
    {
        return (bool) ($this->card['__demo'] ?? false);
    }

    /** @return array<int,array<string,mixed>> */
    public function services(): array
    {
        if ($this->isDemo()) {
            return $this->services ??= DemoCard::services($this->card);
        }

        return $this->services ??= (new CardServiceModel())->forCard($this->id(), true);
    }

    /** @return array<int,array<string,mixed>> */
    public function products(): array
    {
        if ($this->isDemo()) {
            return $this->products ??= DemoCard::products($this->card);
        }

        return $this->products ??= (new CardProduct())->forCard($this->id(), true);
    }

    /** @return array<int,array<string,mixed>> */
    public function gallery(): array
    {
        if ($this->isDemo()) {
            return $this->gallery ??= [];
        }

        return $this->gallery ??= (new CardGallery())->forCard($this->id());
    }

    /** @return array<int,array<string,mixed>> */
    public function galleryImages(): array
    {
        return array_values(array_filter($this->gallery(), static fn (array $item): bool => (string) $item['type'] === 'image'));
    }

    /** @return array<int,array<string,mixed>> */
    public function galleryVideos(): array
    {
        return array_values(array_filter($this->gallery(), static fn (array $item): bool => in_array((string) $item['type'], ['video', 'youtube'], true)));
    }

    /** @return array<string,string> */
    public function social(): array
    {
        if ($this->isDemo()) {
            return $this->social ??= [
                'facebook'  => 'https://facebook.com/',
                'instagram' => 'https://instagram.com/',
                'youtube'   => 'https://youtube.com/',
                'linkedin'  => 'https://linkedin.com/',
            ];
        }

        return $this->social ??= (new CardSocialLink())->mapForCard($this->id());
    }

    /** @return array<string,array<string,mixed>> */
    public function sectionMap(): array
    {
        if ($this->isDemo()) {
            return $this->sections ??= [];
        }

        return $this->sections ??= (new CardSection())->mapForCard($this->id());
    }

    /**
     * Ordered list of sections that should actually render (enabled and with
     * content available).
     *
     * @return array<int,string>
     */
    public function visibleSections(): array
    {
        $map = $this->sectionMap();
        if ($map === []) {
            $map = [];
            $position = 0;
            foreach (CardSection::DEFAULTS as $key => $title) {
                $map[$key] = ['section' => $key, 'title' => $title, 'is_enabled' => 1, 'sort_order' => ++$position];
            }
        }

        uasort($map, static fn (array $a, array $b): int => (int) $a['sort_order'] <=> (int) $b['sort_order']);

        $visible = [];
        foreach ($map as $key => $row) {
            if ((int) ($row['is_enabled'] ?? 1) !== 1) {
                continue;
            }
            if (in_array($key, ['hero', 'actions'], true)) {
                continue;                          // rendered by the layout
            }
            if ($this->sectionHasContent($key)) {
                $visible[] = $key;
            }
        }

        return $visible;
    }

    public function sectionTitle(string $section): string
    {
        $map = $this->sectionMap();

        return (string) ($map[$section]['title'] ?? CardSection::DEFAULTS[$section] ?? ucfirst($section));
    }

    public function sectionHasContent(string $section): bool
    {
        return match ($section) {
            'about'    => $this->has('about'),
            'contact'  => $this->has('phone') || $this->has('email') || $this->has('website') || $this->fullAddress() !== '',
            'services' => $this->services() !== [],
            'products' => $this->products() !== [],
            'gallery'  => $this->gallery() !== [],
            'hours'    => $this->businessHours() !== [],
            'social'   => $this->social() !== [],
            'payment'  => $this->has('upi_id'),
            'map'      => $this->mapEmbedUrl() !== null,
            'enquiry'  => (bool) ($this->settings()['enquiry_enabled'] ?? true),
            'qr'       => (bool) ($this->settings()['show_qr'] ?? true),
            default    => true,
        };
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        $settings = $this->card['settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings()[$key] ?? $default;
    }

    // ---------------------------------------------------------------- SEO --

    public function seoTitle(): string
    {
        if ($this->has('seo_title')) {
            return (string) $this->card['seo_title'];
        }
        $parts = array_filter([
            $this->displayName(),
            (string) ($this->get('designation') ?? ''),
            (string) ($this->get('business_name') ?? ''),
        ]);

        return implode(' · ', array_slice($parts, 0, 2));
    }

    public function seoDescription(): string
    {
        if ($this->has('seo_description')) {
            return (string) $this->card['seo_description'];
        }
        $about = trim(strip_tags((string) ($this->get('about') ?? '')));
        if ($about !== '') {
            return mb_substr($about, 0, 155);
        }

        return trim(sprintf(
            '%s%s. Digital visiting card — call, WhatsApp, save contact and get directions instantly.',
            $this->displayName(),
            $this->has('business_name') ? ' at ' . (string) $this->card['business_name'] : ''
        ));
    }

    public function seoImage(): ?string
    {
        foreach (['seo_image', 'cover_image', 'profile_image', 'logo_image'] as $key) {
            $image = $this->image($key);
            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Schema.org structured data (LocalBusiness / Person).
     *
     * @return array<string,mixed>
     */
    public function structuredData(): array
    {
        $isBusiness = $this->has('business_name');

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => $isBusiness ? 'LocalBusiness' : 'Person',
            'name'     => $isBusiness ? (string) $this->card['business_name'] : $this->displayName(),
            'url'      => $this->url(),
        ];

        if ($this->has('about')) {
            $data['description'] = mb_substr(trim(strip_tags((string) $this->card['about'])), 0, 300);
        }
        if ($this->image('profile_image') !== null) {
            $data['image'] = $this->image('profile_image');
        }
        if ($this->has('phone')) {
            $data['telephone'] = (string) $this->card['phone'];
        }
        if ($this->has('email')) {
            $data['email'] = (string) $this->card['email'];
        }
        if ($this->websiteLink() !== null) {
            $data['sameAs'] = array_values(array_filter(array_merge([$this->websiteLink()], array_values($this->social()))));
        } elseif ($this->social() !== []) {
            $data['sameAs'] = array_values($this->social());
        }

        if ($this->fullAddress() !== '') {
            $data['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => (string) ($this->get('address') ?? ''),
                'addressLocality' => (string) ($this->get('city') ?? ''),
                'addressRegion'   => (string) ($this->get('state') ?? ''),
                'postalCode'      => (string) ($this->get('pincode') ?? ''),
                'addressCountry'  => (string) ($this->get('country') ?? 'India'),
            ], static fn ($value): bool => $value !== '');
        }

        if ($this->has('latitude') && $this->has('longitude')) {
            $data['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $this->card['latitude'],
                'longitude' => (float) $this->card['longitude'],
            ];
        }

        if ($isBusiness && !$this->has('designation')) {
            $data['priceRange'] = '₹₹';
        }
        if (!$isBusiness && $this->has('designation')) {
            $data['jobTitle'] = (string) $this->card['designation'];
        }

        $hours = [];
        foreach ($this->businessHours() as $row) {
            if ($row['closed'] || $row['open'] === '' || $row['close'] === '') {
                continue;
            }
            $hours[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . ucfirst(strtolower(self::DAYS[$row['key']])),
                'opens'     => $row['open'],
                'closes'    => $row['close'],
            ];
        }
        if ($hours !== []) {
            $data['openingHoursSpecification'] = $hours;
        }

        return $data;
    }

    /** YouTube watch/short URL → privacy-enhanced embed URL. */
    public static function youtubeEmbed(string $url): ?string
    {
        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|v/))([A-Za-z0-9_\-]{6,20})#i', $url, $matches) === 1) {
            return 'https://www.youtube-nocookie.com/embed/' . $matches[1];
        }

        return null;
    }

    public static function youtubeThumb(string $url): ?string
    {
        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|v/))([A-Za-z0-9_\-]{6,20})#i', $url, $matches) === 1) {
            return 'https://i.ytimg.com/vi/' . $matches[1] . '/hqdefault.jpg';
        }

        return null;
    }

    public function renderer(): TemplateRenderer
    {
        return TemplateRenderer::forCard($this->card, $this->template);
    }
}
