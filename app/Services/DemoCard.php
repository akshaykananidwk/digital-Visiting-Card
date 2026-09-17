<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Demo content used for template previews. Nothing here is persisted — the
 * preview route feeds this array through the same presenter/renderer stack
 * that a live card uses, so previews cannot drift from reality.
 */
final class DemoCard
{
    /** @return array<string,mixed> */
    public static function build(int $templateId, string $industry = ''): array
    {
        $profile = self::profileFor($industry);

        return [
            'id'                => 0,
            'uuid'              => '00000000-0000-4000-8000-000000000000',
            'user_id'           => 0,
            'template_id'       => $templateId,
            'slug'              => 'preview',
            'status'            => 'published',
            'title'             => $profile['name'],
            'full_name'         => $profile['name'],
            'designation'       => $profile['designation'],
            'business_name'     => $profile['business'],
            'business_category' => $profile['category'],
            'tagline'           => $profile['tagline'],
            'about'             => $profile['about'],
            'profile_image'     => null,
            'cover_image'       => null,
            'logo_image'        => null,
            'phone'             => '+91 98250 00000',
            'phone_alt'         => null,
            'whatsapp'          => '+91 98250 00000',
            'whatsapp_message'  => 'Hello, I found your digital visiting card.',
            'email'             => 'hello@example.com',
            'website'           => 'https://example.com',
            'address'           => 'Main Bazaar Road',
            'city'              => 'Dwarka',
            'state'             => 'Gujarat',
            'pincode'           => '361335',
            'country'           => 'India',
            'map_link'          => null,
            'latitude'          => null,
            'longitude'         => null,
            'business_hours'    => self::hours(),
            'upi_id'            => 'example@upi',
            'payment_note'      => 'UPI, card and cash accepted.',
            'seo_title'         => null,
            'seo_description'   => null,
            'seo_image'         => null,
            'seo_keywords'      => null,
            'theme_overrides'   => [],
            'settings'          => ['enquiry_enabled' => true, 'show_qr' => true, 'vcard_enabled' => true],
            'views_count'       => 0,
            'leads_count'       => 0,
            'published_at'      => now(),
            'expires_at'        => null,
            'created_at'        => now(),
            'updated_at'        => now(),
            'deleted_at'        => null,
            '__demo'            => true,
            '__services'        => $profile['services'],
            '__products'        => $profile['products'],
        ];
    }

    /** @return array<string,mixed> */
    private static function profileFor(string $industry): array
    {
        $key = strtolower($industry);

        $presets = [
            'cctv'      => [
                'name' => 'Akshay Patel', 'designation' => 'CCTV & Security Consultant',
                'business' => 'AK Computer & CCTV', 'category' => 'CCTV & Security',
                'tagline' => 'Protecting homes and shops across Dwarka since 2012.',
                'about' => "We install and service CCTV cameras, DVR/NVR systems, biometric attendance and video door phones.\n\nFree site survey, same-day installation and one year on-site warranty on every project.",
                'services' => ['CCTV installation', 'DVR / NVR setup', 'Biometric attendance', 'Annual maintenance'],
                'products' => ['2MP Dome camera', '4 Channel DVR kit', 'Video door phone'],
            ],
            'computer'  => [
                'name' => 'Akshay Patel', 'designation' => 'Computer Sales & Service',
                'business' => 'AK Computers', 'category' => 'Computer Shop',
                'tagline' => 'Sales, service and upgrades — all under one roof.',
                'about' => "Desktop and laptop sales, repairs, upgrades and annual maintenance contracts for homes, shops and offices.",
                'services' => ['Laptop repair', 'Desktop assembly', 'Data recovery', 'Windows installation'],
                'products' => ['Gaming desktop', 'Business laptop', 'SSD upgrade kit'],
            ],
            'doctor'    => [
                'name' => 'Dr. Meera Shah', 'designation' => 'MBBS, MD — Physician',
                'business' => 'Shah Clinic', 'category' => 'Doctor',
                'tagline' => 'Compassionate family healthcare.',
                'about' => "General medicine, preventive health check-ups and chronic care management. Appointments available on WhatsApp.",
                'services' => ['General consultation', 'Health check-up', 'Diabetes care', 'Vaccination'],
                'products' => [],
            ],
            'hotel'     => [
                'name' => 'Hotel Sea Pearl', 'designation' => 'Boutique stay near the temple',
                'business' => 'Hotel Sea Pearl', 'category' => 'Hotel',
                'tagline' => 'Comfortable rooms, five minutes from the temple.',
                'about' => "Air-conditioned rooms, complimentary breakfast, free parking and 24-hour front desk.",
                'services' => ['Deluxe rooms', 'Family suites', 'Banquet hall', 'Airport pickup'],
                'products' => ['Deluxe room', 'Executive suite'],
            ],
            'accountant'=> [
                'name' => 'Rahul Mehta', 'designation' => 'Chartered Accountant',
                'business' => 'Mehta & Associates', 'category' => 'Chartered Accountant',
                'tagline' => 'GST, income tax and audit made simple.',
                'about' => "Company formation, GST filing, income tax returns, audits and financial advisory for small businesses.",
                'services' => ['GST registration', 'Income tax filing', 'Company formation', 'Statutory audit'],
                'products' => [],
            ],
        ];

        foreach ($presets as $needle => $preset) {
            if (str_contains($key, $needle)) {
                return $preset;
            }
        }

        return [
            'name' => 'Akshay Patel', 'designation' => 'Founder & Director',
            'business' => 'AK Enterprise', 'category' => $industry !== '' ? $industry : 'Business',
            'tagline' => 'Quality service you can rely on.',
            'about' => "We have been serving customers for over a decade with honest pricing, fast service and genuine products.\n\nCall or WhatsApp us any time — we are happy to help.",
            'services' => ['Consultation', 'Installation', 'Maintenance', 'Support'],
            'products' => ['Popular product', 'Best seller'],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private static function hours(): array
    {
        $hours = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as $day) {
            $hours[$day] = ['open' => '09:30', 'close' => '19:30', 'closed' => false];
        }
        $hours['sun'] = ['open' => '', 'close' => '', 'closed' => true];

        return $hours;
    }

    /**
     * Demo services rendered in previews.
     *
     * @param array<string,mixed> $card
     * @return array<int,array<string,mixed>>
     */
    public static function services(array $card): array
    {
        $titles = (array) ($card['__services'] ?? []);
        $rows = [];
        foreach (array_values($titles) as $index => $title) {
            $rows[] = [
                'id' => $index + 1, 'card_id' => 0, 'title' => $title,
                'description' => 'Professional ' . strtolower((string) $title) . ' with transparent pricing.',
                'icon' => 'zap', 'image' => null, 'price' => null, 'price_label' => null,
                'cta_label' => null, 'cta_link' => null, 'is_active' => 1, 'sort_order' => $index + 1,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $card
     * @return array<int,array<string,mixed>>
     */
    public static function products(array $card): array
    {
        $names = (array) ($card['__products'] ?? []);
        $rows = [];
        foreach (array_values($names) as $index => $name) {
            $rows[] = [
                'id' => $index + 1, 'card_id' => 0, 'name' => $name,
                'description' => 'Popular choice with warranty and free delivery.',
                'image' => null, 'price' => 4999.0 + ($index * 1500), 'discount_price' => 4499.0 + ($index * 1500),
                'sku' => null, 'category' => null, 'stock_status' => 'in_stock',
                'cta_type' => 'whatsapp', 'cta_link' => null, 'is_active' => 1, 'is_featured' => 0,
                'sort_order' => $index + 1,
            ];
        }

        return $rows;
    }
}
