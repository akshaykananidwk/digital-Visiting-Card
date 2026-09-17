<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;

/**
 * Template engine.
 *
 * A template is data, not code: a row in `templates` holding a JSON design
 * token set (palette, typography, shape, effects, layout name). This class
 * merges the template tokens with any per-card overrides and emits the CSS
 * custom properties + body classes that the shared layout partials consume.
 *
 * Because every template renders the same card data through the same
 * section partials, switching design never touches a customer's content.
 */
final class TemplateRenderer
{
    /** Layout partials shipped with the platform. */
    public const LAYOUTS = [
        'classic'   => 'Classic centred',
        'split'     => 'Split cover',
        'hero'      => 'Full hero',
        'minimal'   => 'Minimal type',
        'glass'     => 'Glassmorphism',
        'neon'      => 'Neon glow',
        'luxe'      => 'Luxury serif',
        'spotlight' => 'Spotlight portrait',
        'magazine'  => 'Magazine',
        'bold'      => 'Bold blocks',
        'wave'      => 'Wave sections',
        'stack'     => 'Stacked cards',
        'rail'      => 'Side contact rail',
    ];

    /** @var array<string,mixed> */
    private array $config;

    private string $layout;

    /**
     * @param array<string,mixed> $templateConfig
     * @param array<string,mixed> $cardOverrides
     */
    public function __construct(array $templateConfig, array $cardOverrides = [], string $layout = 'classic')
    {
        $this->config = self::normalise($templateConfig, $cardOverrides);
        $this->layout = array_key_exists($layout, self::LAYOUTS) ? $layout : 'classic';
    }

    /**
     * @param array<string,mixed> $template A row from the templates table
     * @param array<string,mixed> $card     A row from the cards table
     */
    public static function forCard(array $card, ?array $template = null): self
    {
        $config = is_array($template['config'] ?? null) ? $template['config'] : [];
        $overrides = is_array($card['theme_overrides'] ?? null) ? $card['theme_overrides'] : [];
        $layout = (string) ($template['layout'] ?? 'classic');

        return new self($config, $overrides, $layout);
    }

    /** Defaults merged with the template tokens and the card's overrides. */
    public static function defaults(): array
    {
        return [
            'palette' => [
                'primary'   => '#4f46e5',
                'secondary' => '#0f172a',
                'accent'    => '#f59e0b',
                'bg'        => '#f5f6fb',
                'surface'   => '#ffffff',
                'text'      => '#0f172a',
                'muted'     => '#64748b',
                'border'    => '#e2e8f0',
                'on_primary'=> '#ffffff',
            ],
            'fonts' => [
                'heading' => 'Poppins',
                'body'    => 'Inter',
                'scale'   => 1.0,
            ],
            'mode'          => 'light',
            'radius'        => 22,
            'buttons'       => 'pill',
            'cover'         => 'gradient',
            'pattern'       => 'none',
            'avatar'        => 'circle',
            'density'       => 'comfortable',
            'align'         => 'center',
            'effects'       => [],
            'cover_height'  => 190,
            'shadow'        => 'soft',
            'motif'         => '',
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    public static function normalise(array $config, array $overrides = []): array
    {
        $defaults = self::defaults();

        $palette = array_merge($defaults['palette'], self::sanitisePalette($config['palette'] ?? []));
        $palette = array_merge($palette, self::sanitisePalette($overrides['palette'] ?? []));

        $fonts = array_merge($defaults['fonts'], self::sanitiseFonts($config['fonts'] ?? []));
        $fonts = array_merge($fonts, self::sanitiseFonts($overrides['fonts'] ?? []));

        $merged = array_merge($defaults, $config, $overrides);
        $merged['palette'] = $palette;
        $merged['fonts'] = $fonts;

        $merged['mode'] = in_array((string) $merged['mode'], ['light', 'dark'], true) ? (string) $merged['mode'] : 'light';
        $merged['radius'] = max(0, min(40, (int) $merged['radius']));
        $merged['buttons'] = in_array((string) $merged['buttons'], ['pill', 'rounded', 'square'], true) ? (string) $merged['buttons'] : 'pill';
        $merged['cover'] = in_array((string) $merged['cover'], ['gradient', 'mesh', 'solid', 'pattern', 'image', 'aurora'], true) ? (string) $merged['cover'] : 'gradient';
        $merged['pattern'] = in_array((string) $merged['pattern'], ['none', 'dots', 'grid', 'waves', 'rings', 'diagonal'], true) ? (string) $merged['pattern'] : 'none';
        $merged['avatar'] = in_array((string) $merged['avatar'], ['circle', 'squircle', 'square', 'hex'], true) ? (string) $merged['avatar'] : 'circle';
        $merged['density'] = in_array((string) $merged['density'], ['comfortable', 'compact', 'roomy'], true) ? (string) $merged['density'] : 'comfortable';
        $merged['align'] = in_array((string) $merged['align'], ['center', 'left'], true) ? (string) $merged['align'] : 'center';
        $merged['shadow'] = in_array((string) $merged['shadow'], ['none', 'soft', 'strong'], true) ? (string) $merged['shadow'] : 'soft';
        $merged['cover_height'] = max(0, min(420, (int) $merged['cover_height']));
        $merged['effects'] = self::sanitiseEffects($merged['effects'] ?? []);
        // Only a name the icon set actually knows; anything else is dropped
        // rather than rendered as an empty box.
        $motif = (string) ($merged['motif'] ?? '');
        $merged['motif'] = ($motif !== '' && \App\Core\Icon::has($motif)) ? $motif : '';

        return $merged;
    }

    /** Effects that may be requested by a template or a card. */
    public const EFFECTS = ['glass', 'float', 'tilt', 'glow', 'shimmer', 'parallax', 'gradient-anim', 'reveal', 'grain'];

    /**
     * @param mixed $effects
     * @return array<int,string>
     */
    private static function sanitiseEffects(mixed $effects): array
    {
        if (!is_array($effects)) {
            return [];
        }

        return array_values(array_intersect(array_map('strval', $effects), self::EFFECTS));
    }

    /**
     * @param mixed $palette
     * @return array<string,string>
     */
    private static function sanitisePalette(mixed $palette): array
    {
        if (!is_array($palette)) {
            return [];
        }
        $clean = [];
        foreach (['primary', 'secondary', 'accent', 'bg', 'surface', 'text', 'muted', 'border', 'on_primary'] as $key) {
            $value = $palette[$key] ?? null;
            if (is_string($value) && self::isColor($value)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /** Accept hex and rgb()/rgba() notation; everything else is discarded. */
    public static function isColor(string $value): bool
    {
        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) === 1) {
            return true;
        }

        return preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $value) === 1;
    }

    /**
     * @param mixed $fonts
     * @return array<string,mixed>
     */
    private static function sanitiseFonts(mixed $fonts): array
    {
        if (!is_array($fonts)) {
            return [];
        }
        $clean = [];
        foreach (['heading', 'body'] as $key) {
            $value = $fonts[$key] ?? null;
            if (is_string($value) && in_array($value, self::FONTS, true)) {
                $clean[$key] = $value;
            }
        }
        if (isset($fonts['scale']) && is_numeric($fonts['scale'])) {
            $clean['scale'] = max(0.85, min(1.2, (float) $fonts['scale']));
        }

        return $clean;
    }

    /** Google Fonts families the platform is allowed to load. */
    public const FONTS = [
        'Inter', 'Poppins', 'Montserrat', 'Manrope', 'Rubik', 'Outfit', 'Sora', 'Plus Jakarta Sans',
        'DM Sans', 'Work Sans', 'Nunito', 'Raleway', 'Playfair Display', 'Cormorant Garamond',
        'Libre Baskerville', 'Lora', 'Oswald', 'Bebas Neue', 'Space Grotesk', 'Josefin Sans',
        'Noto Sans Gujarati', 'Mukta', 'Baloo Bhai 2',
    ];

    // ------------------------------------------------------------ Output --

    /** The trade mark for this design, or an empty string for none. */
    public function motif(): string
    {
        return (string) ($this->config['motif'] ?? '');
    }

    public function layout(): string
    {
        return $this->layout;
    }

    /** @return array<string,mixed> */
    public function config(): array
    {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function color(string $key, string $fallback = '#000000'): string
    {
        return (string) ($this->config['palette'][$key] ?? $fallback);
    }

    public function isDark(): bool
    {
        return (string) $this->config['mode'] === 'dark';
    }

    public function hasEffect(string $effect): bool
    {
        return in_array($effect, (array) $this->config['effects'], true);
    }

    /** CSS custom properties applied to the card root element. */
    public function cssVariables(): string
    {
        $palette = $this->config['palette'];
        $fonts = $this->config['fonts'];
        $radius = (int) $this->config['radius'];

        $vars = [
            '--c-primary'      => $palette['primary'],
            '--c-primary-soft' => self::withAlpha((string) $palette['primary'], 0.12),
            '--c-primary-dark' => self::shade((string) $palette['primary'], -18),
            '--c-secondary'    => $palette['secondary'],
            '--c-accent'       => $palette['accent'],
            '--c-bg'           => $palette['bg'],
            '--c-surface'      => $palette['surface'],
            '--c-surface-2'    => self::mix((string) $palette['surface'], (string) $palette['bg'], 0.5),
            '--c-text'         => $palette['text'],
            // Secondary text. A palette's muted tone is picked by eye against
            // a white mock-up and then used on every surface the design has,
            // where it can fall to 2.5:1. It exists only to be read, so it is
            // corrected to stay legible rather than trusted as given.
            '--c-muted'        => self::onSurface((string) $palette['muted'], (string) $palette['surface'], 4.3),
            '--c-border'       => $palette['border'],
            // The label on a filled primary button. A palette may declare
            // this, but white is not legible on every primary, so an unusable
            // pairing is corrected rather than trusted.
            '--c-on-primary'   => self::onPrimary($palette),
            // The brand hue, guaranteed legible as text on the card surface.
            '--c-primary-readable' => self::onSurface((string) $palette['primary'], (string) $palette['surface']),
            '--c-accent-readable'  => self::onSurface((string) $palette['accent'], (string) $palette['surface']),
            '--radius'         => $radius . 'px',
            '--radius-sm'      => max(4, (int) round($radius * 0.5)) . 'px',
            '--radius-lg'      => ($radius + 8) . 'px',
            '--btn-radius'     => match ((string) $this->config['buttons']) {
                'square'  => '8px',
                'rounded' => max(8, (int) round($radius * 0.6)) . 'px',
                default   => '999px',
            },
            '--font-heading'   => '"' . $fonts['heading'] . '", system-ui, sans-serif',
            '--font-body'      => '"' . $fonts['body'] . '", system-ui, sans-serif',
            '--font-scale'     => (string) ($fonts['scale'] ?? 1.0),
            '--cover-h'        => ((int) $this->config['cover_height']) . 'px',
            '--gap'            => match ((string) $this->config['density']) {
                'compact' => '14px',
                'roomy'   => '28px',
                default   => '20px',
            },
            '--shadow' => match ((string) $this->config['shadow']) {
                'none'   => 'none',
                'strong' => '0 24px 60px -18px ' . self::withAlpha((string) $palette['secondary'], 0.45),
                default  => '0 12px 32px -14px ' . self::withAlpha((string) $palette['secondary'], 0.28),
            },
            '--cover-bg' => $this->coverBackground(),
        ];

        $out = '';
        foreach ($vars as $name => $value) {
            $out .= $name . ':' . $value . ';';
        }

        return $out;
    }

    /** Classes applied to the card root element. */
    public function bodyClasses(): string
    {
        $classes = [
            'dvc-card',
            'lay-' . $this->layout,
            'mode-' . $this->config['mode'],
            'btn-' . $this->config['buttons'],
            'avatar-' . $this->config['avatar'],
            'align-' . $this->config['align'],
            'density-' . $this->config['density'],
            'cover-' . $this->config['cover'],
        ];
        if ((string) $this->config['pattern'] !== 'none') {
            $classes[] = 'pattern-' . $this->config['pattern'];
        }
        foreach ((array) $this->config['effects'] as $effect) {
            $classes[] = 'fx-' . $effect;
        }

        return implode(' ', $classes);
    }

    /** Google Fonts stylesheet URL for the two families in use. */
    public function fontUrl(): string
    {
        $families = array_values(array_unique([
            (string) $this->config['fonts']['heading'],
            (string) $this->config['fonts']['body'],
        ]));

        $query = [];
        foreach ($families as $family) {
            $query[] = 'family=' . rawurlencode($family) . ':wght@400;500;600;700;800';
        }

        return 'https://fonts.googleapis.com/css2?' . implode('&', $query) . '&display=swap';
    }

    private function coverBackground(): string
    {
        $primary = (string) $this->config['palette']['primary'];
        $secondary = (string) $this->config['palette']['secondary'];
        $accent = (string) $this->config['palette']['accent'];

        return match ((string) $this->config['cover']) {
            'solid'  => $primary,
            'mesh'   => sprintf(
                'radial-gradient(at 18%% 20%%, %s 0px, transparent 55%%), radial-gradient(at 82%% 12%%, %s 0px, transparent 50%%), radial-gradient(at 50%% 90%%, %s 0px, transparent 55%%), %s',
                self::withAlpha($accent, 0.75),
                self::withAlpha($primary, 0.85),
                self::withAlpha($secondary, 0.65),
                $primary
            ),
            'aurora' => sprintf(
                'linear-gradient(120deg, %s 0%%, %s 45%%, %s 100%%)',
                $primary,
                self::mix($primary, $accent, 0.5),
                $secondary
            ),
            default  => sprintf('linear-gradient(135deg, %s 0%%, %s 100%%)', $primary, self::shade($secondary, 6)),
        };
    }

    /** Render the card body using the selected layout partial. */
    public function renderLayout(View $view, array $data): string
    {
        $partial = 'card.layouts.' . $this->layout;
        if (!View::exists($partial)) {
            $partial = 'card.layouts.classic';
        }

        return $view->include($partial, $data + ['design' => $this]);
    }

    // ------------------------------------------------------ Colour utils --

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) < 6) {
            return [0, 0, 0];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function withAlpha(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return sprintf('rgba(%d,%d,%d,%.3f)', $r, $g, $b, max(0.0, min(1.0, $alpha)));
    }

    /** Lighten (positive) or darken (negative) by a percentage. */
    public static function shade(string $hex, int $percent): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $factor = $percent / 100;
        $adjust = static function (int $channel) use ($factor): int {
            $value = $factor > 0
                ? $channel + (255 - $channel) * $factor
                : $channel * (1 + $factor);

            return (int) max(0, min(255, round($value)));
        };

        return sprintf('#%02x%02x%02x', $adjust($r), $adjust($g), $adjust($b));
    }

    public static function mix(string $a, string $b, float $weight = 0.5): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);
        $weight = max(0.0, min(1.0, $weight));

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r1 * (1 - $weight) + $r2 * $weight),
            (int) round($g1 * (1 - $weight) + $g2 * $weight),
            (int) round($b1 * (1 - $weight) + $b2 * $weight)
        );
    }

    /** Relative luminance, used to pick readable foreground colours. */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);
        $channel = static function (int $value): float {
            $v = $value / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    public static function contrastRatio(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        $light = max($l1, $l2);
        $dark = min($l1, $l2);

        return ($light + 0.05) / ($dark + 0.05);
    }

    /** Readable text colour (black or white) for a given background. */
    public static function readable(string $background, string $light = '#ffffff', string $dark = '#0f172a'): string
    {
        return self::contrastRatio($background, $light) >= self::contrastRatio($background, $dark) ? $light : $dark;
    }

    /**
     * The same hue, shifted until it is legible on the given background.
     *
     * A palette's primary colour is chosen to look right as a filled button,
     * where the text sits on top of it. Used as text itself -- an outlined
     * button, a link -- it can land close to its own background: a navy
     * primary on a navy dark theme measured under 2:1, which is unreadable.
     * This walks the colour toward white or black, whichever the background
     * is further from, until it clears the target ratio. The target sits a
     * little above the 4.5:1 minimum because the surface a button actually
     * paints on is often a shade off the palette value.
     */
    /**
     * Label colour for a filled primary button.
     *
     * @param array<string,mixed> $palette
     */
    private static function onPrimary(array $palette): string
    {
        $primary = (string) $palette['primary'];
        $declared = $palette['on_primary'] ?? null;

        if (is_string($declared) && self::isColor($declared) && self::contrastRatio($primary, $declared) >= 4.5) {
            return $declared;
        }

        return self::readable($primary);
    }

    public static function onSurface(string $colour, string $surface, float $target = 4.8): string
    {
        if (self::contrastRatio($surface, $colour) >= $target) {
            return $colour;
        }

        // Lighten on a dark surface, darken on a light one.
        $towardsLight = self::luminance($surface) < 0.5;

        $candidate = $colour;
        for ($step = 1; $step <= 20; $step++) {
            $candidate = self::shade($colour, $towardsLight ? $step * 5 : -$step * 5);
            if (self::contrastRatio($surface, $candidate) >= $target) {
                return $candidate;
            }
        }

        return self::readable($surface);
    }
}
