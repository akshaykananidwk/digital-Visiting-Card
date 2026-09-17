<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Tenant;
use App\Core\Url;
use App\Models\Card;
use App\Models\Plan;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\User;
use Throwable;

/** Marketing site, PWA assets and SEO endpoints. */
final class SiteController extends Controller
{
    public function home(): Response
    {
        $templates = new Template();

        return $this->render('site.home', [
            'title'      => (string) (Settings::get('meta_title') ?: Tenant::branding()['name']),
            'metaDescription' => (string) (Settings::get('meta_description') ?? ''),
            'featured'   => $templates->featured(8),
            'plans'      => (new Plan())->active(),
            'categories' => (new TemplateCategory())->tree(),
            'stats'      => $this->publicStats(),
        ]);
    }

    public function features(): Response
    {
        return $this->render('site.features', ['title' => 'Features']);
    }

    public function pricing(): Response
    {
        return $this->render('site.pricing', [
            'title' => 'Pricing',
            'plans' => (new Plan())->active(),
            'gst'   => (bool) Settings::get('gst_enabled', false),
            'gstRate' => (float) (Settings::get('gst_rate') ?: 0),
        ]);
    }

    public function howItWorks(): Response
    {
        return $this->render('site.how-it-works', ['title' => 'How it works']);
    }

    public function resellerProgram(): Response
    {
        return $this->render('site.reseller', ['title' => 'Reseller program']);
    }

    public function faq(): Response
    {
        return $this->render('site.faq', ['title' => 'Frequently asked questions']);
    }

    public function contact(): Response
    {
        return $this->render('site.contact', ['title' => 'Contact us']);
    }

    public function submitContact(): Response
    {
        if ($this->request->string('website_url') !== '') {
            return $this->redirect('contact');     // honeypot
        }

        $data = $this->validate([
            'name'    => 'required|string|min:2|max:150',
            'email'   => 'required|email',
            'phone'   => 'nullable|phone',
            'subject' => 'nullable|string|max:190',
            'message' => 'required|string|min:10|max:2000',
        ]);

        $to = (string) (Settings::get('support_email') ?: '');
        if ($to === '') {
            $admin = (new User())->firstWhere(['role' => 'super_admin']);
            $to = (string) ($admin['email'] ?? '');
        }

        if ($to !== '') {
            try {
                Mailer::send(
                    $to,
                    'Website enquiry: ' . (string) ($data['subject'] ?? 'General'),
                    Mailer::layout('New website enquiry', sprintf(
                        '<p><strong>%s</strong><br>%s<br>%s</p><p>%s</p>',
                        e((string) $data['name']),
                        e((string) $data['email']),
                        e((string) ($data['phone'] ?? '')),
                        nl2br(e((string) $data['message']))
                    )),
                    null,
                    ['event' => 'site.contact']
                );
            } catch (Throwable $e) {
                Logger::warning('Contact form delivery failed: ' . $e->getMessage());
            }
        }

        $this->success('Thank you! We have received your message and will reply soon.');

        return $this->redirect('contact');
    }

    public function terms(): Response
    {
        return $this->render('site.legal', [
            'title'   => 'Terms of service',
            'heading' => 'Terms of service',
            'body'    => (string) (Settings::get('terms_content') ?: ''),
        ]);
    }

    public function privacy(): Response
    {
        return $this->render('site.legal', [
            'title'   => 'Privacy policy',
            'heading' => 'Privacy policy',
            'body'    => (string) (Settings::get('privacy_content') ?: ''),
        ]);
    }

    // ------------------------------------------------------------- PWA/SEO --

    public function manifest(): Response
    {
        $branding = Tenant::branding();

        $manifest = [
            'name'             => $branding['name'],
            'short_name'       => mb_substr($branding['name'], 0, 12),
            'description'      => (string) (Settings::get('meta_description') ?? 'Digital visiting cards'),
            'start_url'        => Url::to('/'),
            'scope'            => Url::to('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#f6f7fb',
            'theme_color'      => '#4f46e5',
            'icons'            => [
                ['src' => Url::to('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => Url::to('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'shortcuts' => [
                ['name' => 'My cards', 'url' => Url::to('cards')],
                ['name' => 'Leads', 'url' => Url::to('leads')],
            ],
        ];

        return Response::json($manifest)->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker(): Response
    {
        $version = APP_VERSION . '-' . substr((string) (Settings::get('sw_revision') ?: '1'), 0, 8);
        $offline = Url::to('offline');
        $assets = json_encode([
            Url::to('assets/css/app.css'),
            Url::to('assets/css/card.css'),
            Url::to('assets/js/app.js'),
            Url::to('assets/js/card.js'),
            $offline,
        ], JSON_UNESCAPED_SLASHES);

        $js = <<<JS
/* Digital Visiting Card service worker (v{$version}) */
const CACHE = 'dvc-{$version}';
const OFFLINE_URL = '{$offline}';
const PRECACHE = {$assets};

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE).then(function (cache) { return cache.addAll(PRECACHE); }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (key) { return key !== CACHE; }).map(function (key) { return caches.delete(key); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  const request = event.request;
  if (request.method !== 'GET' || !request.url.startsWith(self.location.origin)) return;

  // Never cache authenticated panel pages or API calls.
  if (/\\/(admin|reseller|dashboard|cards|billing|leads|analytics|account|api)(\\/|$)/.test(new URL(request.url).pathname)) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(function () {
        return caches.match(request).then(function (cached) { return cached || caches.match(OFFLINE_URL); });
      })
    );
    return;
  }

  event.respondWith(
    caches.match(request).then(function (cached) {
      const network = fetch(request).then(function (response) {
        if (response && response.status === 200 && response.type === 'basic') {
          const copy = response.clone();
          caches.open(CACHE).then(function (cache) { cache.put(request, copy); });
        }
        return response;
      }).catch(function () { return cached; });
      return cached || network;
    })
  );
});
JS;

        return Response::make($js, 200, [
            'Content-Type'  => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => Url::basePath() === '' ? '/' : Url::basePath() . '/',
        ]);
    }

    public function offline(): Response
    {
        return $this->render('site.offline', ['title' => 'You are offline']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /reseller',
            'Disallow: /dashboard',
            'Disallow: /cards',
            'Disallow: /billing',
            'Disallow: /leads',
            'Disallow: /analytics',
            'Disallow: /account',
            'Disallow: /api/',
            'Disallow: /install',
            'Allow: /',
            '',
            'Sitemap: ' . Url::to('sitemap.xml'),
        ];

        return Response::make(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        if (!(bool) Settings::get('enable_sitemap', true)) {
            return Response::make('', 404);
        }

        $urls = [];
        foreach (['/', '/features', '/templates', '/pricing', '/how-it-works', '/reseller-program', '/faq', '/contact'] as $path) {
            $urls[] = ['loc' => Url::to($path), 'changefreq' => 'weekly', 'priority' => $path === '/' ? '1.0' : '0.7'];
        }

        foreach ((new Card())->sitemapRows(5000) as $card) {
            $urls[] = [
                'loc'        => Url::card((string) $card['slug']),
                'lastmod'    => date('c', strtotime((string) ($card['updated_at'] ?: $card['published_at'] ?: 'now'))),
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $entry) {
            $xml .= '  <url><loc>' . e($entry['loc']) . '</loc>';
            if (isset($entry['lastmod'])) {
                $xml .= '<lastmod>' . e($entry['lastmod']) . '</lastmod>';
            }
            $xml .= '<changefreq>' . e($entry['changefreq']) . '</changefreq>';
            $xml .= '<priority>' . e($entry['priority']) . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';

        return Response::make($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** @return array<string,int> */
    private function publicStats(): array
    {
        try {
            return [
                'templates' => (new Template())->count(['is_active' => 1]),
                'cards'     => (new Card())->count(['status' => 'published']),
                'users'     => (new User())->count(['status' => 'active']),
            ];
        } catch (Throwable) {
            return ['templates' => 0, 'cards' => 0, 'users' => 0];
        }
    }
}
