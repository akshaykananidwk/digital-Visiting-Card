<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Template;
use App\Models\TemplateCategory;

/**
 * Generates the design catalogue.
 *
 * Designs are produced combinatorially from curated design tokens
 * (palette x typography x layout x cover x shape x effects) rather than
 * hand-written PHP files, so the library scales from 1,000 to 10,000+
 * designs without adding a single template file. Generation is
 * deterministic: the same seed always produces the same catalogue, which
 * keeps template codes stable across installations and updates.
 */
final class TemplateFactory
{
    /** Curated palettes: name => [primary, secondary, accent, family, mode]. */
    public const PALETTES = [
        'Indigo Night'    => ['#4f46e5', '#1e1b4b', '#f59e0b', 'blue', 'light'],
        'Ocean Blue'      => ['#0284c7', '#082f49', '#38bdf8', 'blue', 'light'],
        'Royal Sapphire'  => ['#1d4ed8', '#111827', '#60a5fa', 'blue', 'light'],
        'Sky Breeze'      => ['#0ea5e9', '#0f172a', '#22d3ee', 'blue', 'light'],
        'Midnight Cobalt' => ['#3b82f6', '#020617', '#38bdf8', 'blue', 'dark'],
        'Teal Horizon'    => ['#0d9488', '#042f2e', '#2dd4bf', 'teal', 'light'],
        'Mint Fresh'      => ['#10b981', '#064e3b', '#6ee7b7', 'green', 'light'],
        'Forest Green'    => ['#15803d', '#052e16', '#4ade80', 'green', 'light'],
        'Emerald Dark'    => ['#059669', '#022c22', '#34d399', 'green', 'dark'],
        'Lime Punch'      => ['#65a30d', '#1a2e05', '#a3e635', 'green', 'light'],
        'Crimson Red'     => ['#dc2626', '#450a0a', '#fb7185', 'red', 'light'],
        'Ruby Luxe'       => ['#be123c', '#4c0519', '#fda4af', 'red', 'light'],
        'Scarlet Dark'    => ['#ef4444', '#0c0a09', '#fca5a5', 'red', 'dark'],
        'Sunset Orange'   => ['#ea580c', '#431407', '#fdba74', 'orange', 'light'],
        'Amber Glow'      => ['#d97706', '#451a03', '#fcd34d', 'orange', 'light'],
        'Marigold'        => ['#f59e0b', '#422006', '#fde047', 'yellow', 'light'],
        'Saffron Gold'    => ['#ca8a04', '#3f2d04', '#facc15', 'yellow', 'light'],
        'Royal Purple'    => ['#7c3aed', '#2e1065', '#c4b5fd', 'purple', 'light'],
        'Violet Dream'    => ['#8b5cf6', '#1e1b4b', '#d8b4fe', 'purple', 'light'],
        'Plum Noir'       => ['#a855f7', '#0b0713', '#e9d5ff', 'purple', 'dark'],
        'Magenta Pop'     => ['#c026d3', '#3b0764', '#f0abfc', 'pink', 'light'],
        'Rose Blush'      => ['#e11d48', '#4c0519', '#fecdd3', 'pink', 'light'],
        'Pink Sorbet'     => ['#ec4899', '#500724', '#fbcfe8', 'pink', 'light'],
        'Slate Pro'       => ['#475569', '#0f172a', '#94a3b8', 'grey', 'light'],
        'Graphite'        => ['#334155', '#020617', '#cbd5e1', 'grey', 'dark'],
        'Carbon Black'    => ['#18181b', '#000000', '#a1a1aa', 'black', 'dark'],
        'Onyx Gold'       => ['#1c1917', '#000000', '#d4af37', 'black', 'dark'],
        'Champagne'       => ['#b08d57', '#2b2118', '#e7d3b1', 'gold', 'light'],
        'Antique Gold'    => ['#a67c00', '#2a1f04', '#ffd700', 'gold', 'light'],
        'Copper Bronze'   => ['#b45309', '#2c1608', '#fbbf24', 'bronze', 'light'],
        'Steel Blue'      => ['#2563eb', '#1e293b', '#93c5fd', 'blue', 'light'],
        'Cyan Neon'       => ['#06b6d4', '#020617', '#67e8f9', 'cyan', 'dark'],
        'Neon Lime'       => ['#84cc16', '#0a0a0a', '#bef264', 'green', 'dark'],
        'Neon Pink'       => ['#f472b6', '#0a0a0a', '#f9a8d4', 'pink', 'dark'],
        'Peacock'         => ['#0891b2', '#083344', '#a5f3fc', 'teal', 'light'],
        'Turquoise Sea'   => ['#14b8a6', '#134e4a', '#99f6e4', 'teal', 'light'],
        'Maroon Classic'  => ['#831843', '#500724', '#f9a8d4', 'maroon', 'light'],
        'Terracotta'      => ['#c2410c', '#431407', '#fed7aa', 'orange', 'light'],
        'Olive Earth'     => ['#4d7c0f', '#1a2e05', '#d9f99d', 'green', 'light'],
        'Sandstone'       => ['#a16207', '#422006', '#fef08a', 'beige', 'light'],
        'Peacock Saffron' => ['#0f766e', '#7c2d12', '#f59e0b', 'teal', 'light'],
        'Krishna Blue'    => ['#1e40af', '#172554', '#fbbf24', 'blue', 'light'],
        'Dwarka Saffron'  => ['#ea580c', '#7c2d12', '#fbbf24', 'orange', 'light'],
        'Temple Gold'     => ['#b45309', '#3f1d0b', '#fde68a', 'gold', 'light'],
        'Gujarat Heritage'=> ['#9d174d', '#4a044e', '#fbbf24', 'maroon', 'light'],
        'Peacock Feather' => ['#047857', '#134e4a', '#38bdf8', 'teal', 'light'],
    ];

    /** Font pairings: name => [heading, body]. */
    public const FONT_PAIRS = [
        'modern'     => ['Poppins', 'Inter'],
        'clean'      => ['Manrope', 'Inter'],
        'geometric'  => ['Outfit', 'DM Sans'],
        'tech'       => ['Space Grotesk', 'Work Sans'],
        'editorial'  => ['Playfair Display', 'Lora'],
        'luxury'     => ['Cormorant Garamond', 'Libre Baskerville', 'gem'],
        'impact'     => ['Bebas Neue', 'Rubik'],
        'friendly'   => ['Nunito', 'Nunito'],
        'corporate'  => ['Montserrat', 'Plus Jakarta Sans', 'briefcase-md'],
        'indic'      => ['Mukta', 'Noto Sans Gujarati'],
    ];

    /**
     * Category blueprints.
     *
     * key => [display name, parent group, industry keywords, preferred
     *         palette families, preferred layouts, preferred font pairs,
     *         style label, premium ratio]
     */
    public const CATEGORIES = [
        // ---------------------------------------------------- Computer / IT
        'computer-shop'      => ['Computer Shop', 'Computer / IT', 'computer pc desktop assembly sales', ['blue', 'cyan', 'grey'], ['hero', 'split', 'bold'], ['tech', 'modern'], 'tech', 0.35, 'monitor'],
        'laptop-shop'        => ['Laptop Shop', 'Computer / IT', 'laptop notebook macbook repair sales', ['blue', 'grey', 'purple'], ['split', 'classic', 'stack'], ['tech', 'clean'], 'tech', 0.35, 'monitor'],
        'cctv-security'      => ['CCTV & Security', 'Computer / IT', 'cctv camera security surveillance dvr nvr', ['blue', 'black', 'red'], ['neon', 'hero', 'bold'], ['tech', 'impact'], 'tech', 0.4, 'cctv'],
        'networking'         => ['Networking', 'Computer / IT', 'network lan wifi router cabling fiber', ['cyan', 'blue', 'teal'], ['split', 'glass', 'hero'], ['tech', 'geometric'], 'tech', 0.4, 'globe'],
        'it-services'        => ['IT Services', 'Computer / IT', 'it support amc service annual maintenance', ['blue', 'teal', 'grey'], ['classic', 'minimal', 'stack'], ['clean', 'corporate'], 'corporate', 0.3, 'wrench'],
        'software-developer' => ['Software Developer', 'Computer / IT', 'software developer programmer coder engineer', ['purple', 'blue', 'black'], ['neon', 'minimal', 'glass'], ['tech', 'geometric'], 'modern', 0.45, 'git'],
        'web-developer'      => ['Web Developer', 'Computer / IT', 'web developer website design freelancer', ['purple', 'cyan', 'pink'], ['glass', 'minimal', 'spotlight'], ['geometric', 'tech'], 'modern', 0.45, 'crop'],
        'mobile-repair'      => ['Mobile Repair', 'Computer / IT', 'mobile phone repair screen battery service', ['orange', 'blue', 'green'], ['bold', 'hero', 'stack'], ['impact', 'modern'], 'bold', 0.3, 'phone'],
        'electronics'        => ['Electronics', 'Computer / IT', 'electronics appliances led tv audio', ['blue', 'red', 'orange'], ['hero', 'magazine', 'bold'], ['modern', 'impact'], 'bold', 0.3, 'bolt-wire'],

        // ----------------------------------------------------------- Business
        'general-business'   => ['General Business', 'Business', 'business company enterprise firm', ['blue', 'grey', 'green'], ['classic', 'split', 'stack'], ['corporate', 'clean'], 'corporate', 0.3, 'briefcase-md'],
        'shop-owner'         => ['Shop Owner', 'Business', 'shop store retail kirana outlet', ['orange', 'green', 'red'], ['classic', 'bold', 'wave'], ['friendly', 'modern'], 'friendly', 0.25, 'shopping'],
        'manufacturer'       => ['Manufacturer', 'Business', 'manufacturer factory industry production', ['grey', 'blue', 'bronze'], ['split', 'magazine', 'bold'], ['corporate', 'impact'], 'corporate', 0.3, 'brick'],
        'distributor'        => ['Distributor', 'Business', 'distributor supply chain dealership', ['blue', 'teal', 'grey'], ['classic', 'split', 'stack'], ['corporate', 'clean'], 'corporate', 0.3, 'truck'],
        'wholesaler'         => ['Wholesaler', 'Business', 'wholesale bulk trading supplier', ['green', 'orange', 'blue'], ['classic', 'bold', 'stack'], ['modern', 'corporate'], 'corporate', 0.25, 'package'],
        'retailer'           => ['Retailer', 'Business', 'retail showroom counter sales', ['pink', 'orange', 'purple'], ['hero', 'magazine', 'wave'], ['friendly', 'modern'], 'friendly', 0.25, 'shopping'],
        'trader'             => ['Trader', 'Business', 'trader import export commodity', ['gold', 'blue', 'grey'], ['luxe', 'classic', 'split'], ['corporate', 'luxury'], 'corporate', 0.35, 'chart-up'],

        // -------------------------------------------------------- Professional
        'accountant'         => ['Accountant', 'Professional', 'accountant accounts bookkeeping tally gst', ['blue', 'teal', 'grey'], ['minimal', 'classic', 'stack'], ['corporate', 'clean'], 'corporate', 0.3, 'chart-up'],
        'chartered-accountant' => ['Chartered Accountant', 'Professional', 'ca chartered accountant audit tax itr', ['blue', 'grey', 'gold'], ['minimal', 'luxe', 'classic'], ['corporate', 'editorial'], 'corporate', 0.4, 'scale'],
        'lawyer'             => ['Lawyer & Advocate', 'Professional', 'lawyer advocate legal court notary', ['maroon', 'black', 'gold'], ['luxe', 'minimal', 'magazine'], ['editorial', 'luxury'], 'premium', 0.45, 'scale'],
        'architect'          => ['Architect', 'Professional', 'architect architecture design plan 3d', ['grey', 'black', 'beige'], ['minimal', 'magazine', 'split'], ['geometric', 'editorial'], 'minimal', 0.45, 'crop'],
        'engineer'           => ['Engineer', 'Professional', 'engineer civil mechanical structural', ['blue', 'orange', 'grey'], ['split', 'classic', 'bold'], ['tech', 'corporate'], 'corporate', 0.3, 'wrench'],
        'consultant'         => ['Consultant', 'Professional', 'consultant advisory strategy coach', ['purple', 'blue', 'teal'], ['minimal', 'glass', 'spotlight'], ['clean', 'corporate'], 'modern', 0.35, 'briefcase-md'],
        'doctor'             => ['Doctor', 'Professional', 'doctor clinic physician hospital mbbs', ['teal', 'blue', 'green'], ['classic', 'spotlight', 'stack'], ['clean', 'friendly'], 'clean', 0.3, 'stethoscope'],
        'dentist'            => ['Dentist', 'Professional', 'dentist dental clinic teeth orthodontic', ['cyan', 'teal', 'blue'], ['spotlight', 'classic', 'wave'], ['clean', 'friendly'], 'clean', 0.3, 'tooth'],
        'real-estate'        => ['Real Estate', 'Professional', 'real estate property builder flats plots', ['gold', 'blue', 'green'], ['hero', 'magazine', 'luxe'], ['luxury', 'corporate'], 'premium', 0.45, 'home'],
        'insurance-agent'    => ['Insurance Agent', 'Professional', 'insurance lic policy agent mediclaim', ['blue', 'green', 'teal'], ['classic', 'split', 'stack'], ['corporate', 'friendly'], 'corporate', 0.25, 'shield'],

        // ------------------------------------------------------------ Creative
        'graphic-designer'   => ['Graphic Designer', 'Creative', 'graphic designer logo branding creative', ['pink', 'purple', 'orange'], ['glass', 'bold', 'magazine'], ['geometric', 'impact'], 'creative', 0.5, 'palette'],
        'photographer'       => ['Photographer', 'Creative', 'photographer photography studio wedding shoot', ['black', 'grey', 'beige'], ['magazine', 'hero', 'minimal'], ['editorial', 'luxury'], 'premium', 0.5, 'camera'],
        'videographer'       => ['Videographer', 'Creative', 'videographer cinematography film editing reels', ['black', 'purple', 'red'], ['neon', 'hero', 'magazine'], ['impact', 'tech'], 'premium', 0.5, 'video'],
        'printing-press'     => ['Printing Press', 'Creative', 'printing press offset digital flex banner', ['red', 'blue', 'yellow'], ['bold', 'classic', 'wave'], ['impact', 'modern'], 'bold', 0.25, 'printer'],
        'screen-printing'    => ['Screen Printing', 'Creative', 'screen printing t-shirt sublimation mug', ['orange', 'pink', 'green'], ['bold', 'wave', 'stack'], ['impact', 'friendly'], 'bold', 0.25, 'shirt'],
        'digital-marketing'  => ['Digital Marketing', 'Creative', 'digital marketing seo ads social growth', ['purple', 'cyan', 'pink'], ['glass', 'neon', 'bold'], ['geometric', 'tech'], 'modern', 0.45, 'chart-up'],
        'social-media'       => ['Social Media Manager', 'Creative', 'social media manager instagram content creator', ['pink', 'purple', 'cyan'], ['glass', 'spotlight', 'bold'], ['geometric', 'friendly'], 'modern', 0.45, 'sparkles'],

        // --------------------------------------------------------- Hospitality
        'hotel'              => ['Hotel', 'Hospitality', 'hotel rooms stay lodging booking', ['gold', 'blue', 'maroon'], ['luxe', 'hero', 'magazine'], ['luxury', 'editorial'], 'premium', 0.5, 'bed'],
        'restaurant'         => ['Restaurant', 'Hospitality', 'restaurant food dining cafe menu', ['red', 'orange', 'green'], ['hero', 'magazine', 'wave'], ['editorial', 'friendly'], 'premium', 0.4, 'utensils'],
        'resort'             => ['Resort', 'Hospitality', 'resort beach villa holiday spa', ['teal', 'green', 'gold'], ['hero', 'luxe', 'magazine'], ['luxury', 'clean'], 'premium', 0.5, 'plane'],
        'travel-agency'      => ['Travel Agency', 'Hospitality', 'travel agency tour package flight visa', ['blue', 'orange', 'teal'], ['hero', 'wave', 'split'], ['friendly', 'modern'], 'friendly', 0.35, 'plane'],
        'tour-operator'      => ['Tour Operator', 'Hospitality', 'tour operator sightseeing bus taxi package', ['orange', 'teal', 'yellow'], ['wave', 'hero', 'stack'], ['friendly', 'impact'], 'friendly', 0.3, 'car'],

        // ---------------------------------------------- Local / Religious / Gujarat
        'dwarka'             => ['Dwarka Special', 'Local & Cultural', 'dwarka dwarkadhish gujarat temple darshan', ['orange', 'gold', 'blue'], ['luxe', 'classic', 'wave'], ['indic', 'editorial'], 'traditional', 0.4, 'temple'],
        'krishna'            => ['Krishna Theme', 'Local & Cultural', 'krishna radha bhakti peacock flute mandir', ['blue', 'gold', 'teal'], ['luxe', 'hero', 'classic'], ['indic', 'editorial'], 'traditional', 0.4, 'diya'],
        'temple'             => ['Temple & Trust', 'Local & Cultural', 'temple mandir trust seva donation aarti', ['gold', 'orange', 'maroon'], ['luxe', 'classic', 'wave'], ['indic', 'luxury'], 'traditional', 0.4, 'temple'],
        'gujarat-traditional'=> ['Traditional Gujarati', 'Local & Cultural', 'gujarat gujarati traditional garba navratri', ['maroon', 'orange', 'gold'], ['luxe', 'bold', 'classic'], ['indic', 'impact'], 'traditional', 0.35, 'diya'],

        // -------------------------------------------------------------- Modern
        'minimal'            => ['Minimal', 'Modern', 'minimal simple clean whitespace', ['grey', 'black', 'beige'], ['minimal', 'stack', 'classic'], ['clean', 'geometric'], 'minimal', 0.3, 'sparkles'],
        'corporate'          => ['Corporate', 'Modern', 'corporate professional formal business', ['blue', 'grey', 'teal'], ['classic', 'split', 'magazine'], ['corporate', 'clean'], 'corporate', 0.3],
        'luxury'             => ['Luxury', 'Modern', 'luxury premium exclusive elite gold', ['gold', 'black', 'maroon'], ['luxe', 'magazine', 'minimal'], ['luxury', 'editorial'], 'premium', 0.7],
        'glassmorphism'      => ['Glassmorphism', 'Modern', 'glass blur frosted transparent modern', ['purple', 'cyan', 'blue'], ['glass', 'spotlight', 'hero'], ['geometric', 'clean'], 'glass', 0.6, 'layers'],
        'gradient'           => ['Gradient', 'Modern', 'gradient colorful vibrant mesh', ['pink', 'purple', 'orange'], ['hero', 'glass', 'bold'], ['geometric', 'modern'], 'gradient', 0.5, 'sparkles'],
        'neon'               => ['Neon', 'Modern', 'neon glow cyberpunk dark vibrant', ['cyan', 'pink', 'green'], ['neon', 'bold', 'glass'], ['tech', 'impact'], 'neon', 0.6, 'zap'],
        'dark-mode'          => ['Dark', 'Modern', 'dark black night mode elegant', ['black', 'grey', 'blue'], ['neon', 'minimal', 'stack'], ['tech', 'clean'], 'dark', 0.5, 'moon'],
        'three-d'            => ['3D & Animated', 'Modern', '3d animated motion parallax depth', ['purple', 'blue', 'cyan'], ['glass', 'spotlight', 'hero'], ['geometric', 'tech'], '3d', 0.8, 'layers'],
    ];

    /** Adjectives used to make each generated design name unique and human. */
    private const ADJECTIVES = [
        'Aurora', 'Vertex', 'Nimbus', 'Cascade', 'Horizon', 'Prism', 'Summit', 'Atlas', 'Lumen',
        'Orbit', 'Quartz', 'Zenith', 'Arc', 'Vista', 'Echo', 'Pulse', 'Cobalt', 'Halo', 'Flux',
        'Nova', 'Onyx', 'Crest', 'Drift', 'Ember', 'Frost',
    ];

    private Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    /**
     * Create (or top up) the category tree. Returns slug => id.
     *
     * @return array<string,int>
     */
    public function seedCategories(): array
    {
        $categories = new TemplateCategory();
        $groups = [];
        $map = [];
        $order = 0;

        foreach (self::CATEGORIES as $slug => $definition) {
            [$name, $group] = $definition;

            if (!isset($groups[$group])) {
                $groupSlug = str_slug($group);
                $existing = $categories->findBySlug($groupSlug);
                $groups[$group] = $existing !== null
                    ? (int) $existing['id']
                    : $categories->create([
                        'parent_id'  => null,
                        'slug'       => $groupSlug,
                        'name'       => $group,
                        'icon'       => 'folder',
                        'is_active'  => 1,
                        'sort_order' => count($groups) + 1,
                        'created_at' => now(),
                    ]);
            }

            $existing = $categories->findBySlug($slug);
            $map[$slug] = $existing !== null
                ? (int) $existing['id']
                : $categories->create([
                    'parent_id'  => $groups[$group],
                    'slug'       => $slug,
                    'name'       => $name,
                    'icon'       => 'tag',
                    'is_active'  => 1,
                    'sort_order' => ++$order,
                    'created_at' => now(),
                ]);
        }

        return $map;
    }

    /**
     * Generate the design catalogue.
     *
     * @param int $perCategory How many designs to build for each category.
     * @return array{created:int,updated:int,skipped:int,total:int}
     */
    public function generate(int $perCategory = 24, ?callable $progress = null, bool $refresh = false): array
    {
        $categoryIds = $this->seedCategories();
        $templates = new Template();

        $created = 0;
        $skipped = 0;
        $updated = 0;
        $sortOrder = $templates->nextSortOrder();

        foreach (self::CATEGORIES as $slug => $definition) {
            $designs = $this->buildDesignsFor($slug, $definition, $perCategory);

            foreach ($designs as $design) {
                $existing = $templates->findByCode($design['code']);
                if ($existing !== null) {
                    // Refresh rewrites the design of a built-in template while
                    // keeping its id, so cards already using it simply pick up
                    // the improved design instead of losing their template.
                    if ($refresh) {
                        $templates->updateById((int) $existing['id'], [
                            'name'         => $design['name'],
                            'layout'       => $design['layout'],
                            'theme_mode'   => $design['mode'],
                            'style'        => $design['style'],
                            'industry'     => $design['industry'],
                            'color_family' => $design['color_family'],
                            'config'       => $design['config'],
                            'tags'         => $design['tags'],
                            'is_premium'   => $design['is_premium'],
                            'is_featured'  => $design['is_featured'],
                            'updated_at'   => now(),
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                $templates->create([
                    'code'          => $design['code'],
                    'name'          => $design['name'],
                    'category_id'   => $categoryIds[$slug] ?? null,
                    'layout'        => $design['layout'],
                    'theme_mode'    => $design['mode'],
                    'style'         => $design['style'],
                    'industry'      => $design['industry'],
                    'color_family'  => $design['color_family'],
                    'config'        => $design['config'],
                    'tags'          => $design['tags'],
                    'is_premium'    => $design['is_premium'],
                    'is_active'     => 1,
                    'is_featured'   => $design['is_featured'],
                    'is_popular'    => 0,
                    'sort_order'    => $sortOrder++,
                    'created_at'    => now(),
                ]);
                $created++;

                if ($progress !== null && ($created % 100) === 0) {
                    $progress($created);
                }
            }
        }

        (new TemplateCategory())->refreshCounts();

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total'   => $templates->count(['is_active' => 1]),
        ];
    }

    /**
     * Build the design token sets for one category.
     *
     * @param array{0:string,1:string,2:string,3:array<int,string>,4:array<int,string>,5:array<int,string>,6:string,7:float} $definition
     * @return array<int,array<string,mixed>>
     */
    /**
     * Add further layouts to a category's preferred three, so a category
     * spans several structures instead of recolouring the same one.
     *
     * @param array<int,string> $preferred
     * @return array<int,string>
     */
    private function widenLayoutPool(array $preferred, string $categorySlug): array
    {
        $extra = array_values(array_diff(array_keys(TemplateRenderer::LAYOUTS), $preferred));
        if ($extra === []) {
            return $preferred;
        }

        sort($extra);
        $offset = crc32($categorySlug) % count($extra);

        $pool = $preferred;
        for ($i = 0; $i < 3 && $i < count($extra); $i++) {
            $pool[] = $extra[($offset + $i * 2) % count($extra)];
        }

        return array_values(array_unique($pool));
    }

    public function buildDesignsFor(string $categorySlug, array $definition, int $count): array
    {
        [$name, $group, $keywords, $colorFamilies, $layouts, $fontPairs, $style, $premiumRatio] = $definition;
        // The trade mark for this category, e.g. cutlery for a restaurant.
        // A visitor should recognise the trade before reading a word.
        $motif = $definition[8] ?? 'sparkles';

        $palettes = $this->palettesFor($colorFamilies);

        // Every category lists the three layouts that suit it best, but three
        // structures across twenty-four designs makes a category look like one
        // design recoloured. The pool is widened with further layouts, chosen
        // from the category's own name so the extra shapes are stable for a
        // category rather than shuffling on every rebuild.
        $layouts = $this->widenLayoutPool($layouts, $categorySlug);

        $designs = [];

        for ($index = 0; $index < $count; $index++) {
            $paletteName = $palettes[$index % count($palettes)];
            [$primary, $secondary, $accent, $family, $paletteMode] = self::PALETTES[$paletteName];

            // Cycle layouts per design rather than per palette block. Dividing
            // by the palette count meant a category with a dozen palettes only
            // ever reached the first two layouts in its pool, which is why
            // twenty-four designs looked like the same card recoloured.
            $layout = $layouts[$index % count($layouts)];
            $pairKey = $fontPairs[($index + intdiv($index, 3)) % count($fontPairs)];
            [$heading, $body] = self::FONT_PAIRS[$pairKey];

            $mode = $this->modeFor($layout, $paletteMode, $index);
            // Distribute premium designs evenly (Bresenham): with a ratio of
            // 0.35 exactly ~35% of the set is premium whatever $count is.
            $isPremium = (int) floor(($index + 1) * $premiumRatio) > (int) floor($index * $premiumRatio) ? 1 : 0;

            $config = $this->buildConfig($primary, $secondary, $accent, $heading, $body, $layout, $mode, $index, (bool) $isPremium);
            $config['motif'] = $motif;

            $adjective = self::ADJECTIVES[($index * 7 + crc32($categorySlug)) % count(self::ADJECTIVES)];
            $designName = sprintf('%s %s %s', $name, $adjective, $paletteName);

            $designs[] = [
                'code'         => sprintf('%s-%03d', $categorySlug, $index + 1),
                'name'         => $designName,
                'layout'       => $layout,
                'mode'         => $mode,
                'style'        => $style,
                'industry'     => $name,
                'color_family' => $family,
                'config'       => $config,
                'tags'         => implode(' ', array_unique(array_filter([
                    $keywords, $style, $family, $layout, strtolower($paletteName), strtolower($group),
                    $isPremium === 1 ? 'premium' : 'free',
                    $mode === 'dark' ? 'dark' : 'light',
                ]))),
                'is_premium'   => $isPremium,
                'is_featured'  => $index < 2 ? 1 : 0,
            ];
        }

        return $designs;
    }

    /** @param array<int,string> $families @return array<int,string> */
    private function palettesFor(array $families): array
    {
        $matching = [];
        foreach (self::PALETTES as $paletteName => [$primary, $secondary, $accent, $family]) {
            if (in_array($family, $families, true)) {
                $matching[] = $paletteName;
            }
        }

        if (count($matching) < 6) {
            foreach (array_keys(self::PALETTES) as $paletteName) {
                if (!in_array($paletteName, $matching, true)) {
                    $matching[] = $paletteName;
                }
                if (count($matching) >= 8) {
                    break;
                }
            }
        }

        return $matching;
    }

    private function modeFor(string $layout, string $paletteMode, int $index): string
    {
        if (in_array($layout, ['neon'], true)) {
            return 'dark';
        }
        if ($paletteMode === 'dark') {
            return 'dark';
        }

        return $index % 7 === 6 ? 'dark' : 'light';
    }

    /**
     * Compose a full design-token set.
     *
     * @return array<string,mixed>
     */
    private function buildConfig(
        string $primary,
        string $secondary,
        string $accent,
        string $heading,
        string $body,
        string $layout,
        string $mode,
        int $index,
        bool $premium
    ): array {
        $dark = $mode === 'dark';

        $background = $dark
            ? TemplateRenderer::shade($secondary, -35)
            : TemplateRenderer::mix('#ffffff', $primary, 0.045);
        $surface = $dark ? TemplateRenderer::shade($secondary, -22) : '#ffffff';
        $text = $dark ? '#f1f5f9' : TemplateRenderer::shade($secondary, -5);
        $muted = $dark ? TemplateRenderer::mix($text, $secondary, 0.45) : TemplateRenderer::mix($text, '#ffffff', 0.5);
        $border = $dark ? TemplateRenderer::withAlpha('#ffffff', 0.10) : TemplateRenderer::mix('#ffffff', $primary, 0.14);

        $covers = ['gradient', 'mesh', 'aurora', 'solid', 'gradient', 'mesh'];
        $patterns = ['none', 'dots', 'grid', 'waves', 'rings', 'diagonal', 'none'];
        $avatars = ['circle', 'circle', 'squircle', 'square', 'hex'];
        $buttons = ['pill', 'pill', 'rounded', 'square'];
        $densities = ['comfortable', 'comfortable', 'compact', 'roomy'];

        $effects = [];
        if ($layout === 'glass' || $layout === 'neon') {
            $effects[] = 'glass';
        }
        if ($layout === 'neon') {
            $effects[] = 'glow';
        }
        if ($premium) {
            $premiumEffects = ['float', 'tilt', 'shimmer', 'parallax', 'gradient-anim', 'reveal'];
            $effects[] = $premiumEffects[$index % count($premiumEffects)];
            if ($index % 3 === 0) {
                $effects[] = $premiumEffects[($index + 2) % count($premiumEffects)];
            }
        }
        if ($index % 11 === 0) {
            $effects[] = 'grain';
        }

        return [
            'palette' => [
                'primary'    => $primary,
                'secondary'  => $secondary,
                'accent'     => $accent,
                'bg'         => $background,
                'surface'    => $surface,
                'text'       => $text,
                'muted'      => $muted,
                'border'     => $border,
                'on_primary' => TemplateRenderer::readable($primary),
            ],
            'fonts' => [
                'heading' => $heading,
                'body'    => $body,
                'scale'   => $index % 5 === 4 ? 1.05 : 1.0,
            ],
            'mode'         => $mode,
            'radius'       => [22, 16, 28, 10, 34, 20][$index % 6],
            'buttons'      => $buttons[$index % count($buttons)],
            'cover'        => $layout === 'minimal' ? 'solid' : $covers[$index % count($covers)],
            'pattern'      => $patterns[$index % count($patterns)],
            'avatar'       => $avatars[$index % count($avatars)],
            'density'      => $densities[$index % count($densities)],
            'align'        => in_array($layout, ['magazine', 'split', 'minimal'], true) && $index % 2 === 0 ? 'left' : 'center',
            'cover_height' => $layout === 'minimal' ? 0 : [180, 210, 240, 160][$index % 4],
            'shadow'       => $dark ? 'strong' : ['soft', 'soft', 'strong', 'none'][$index % 4],
            'effects'      => array_values(array_unique($effects)),
        ];
    }

    /** How many designs the catalogue would contain at a given density. */
    public static function catalogueSize(int $perCategory): int
    {
        return count(self::CATEGORIES) * $perCategory;
    }
}
