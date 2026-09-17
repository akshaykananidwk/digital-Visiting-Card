<?php

declare(strict_types=1);

namespace App\Controllers\Reseller;

use App\Core\AuditLog;
use App\Core\Response;
use App\Core\Uploader;
use App\Models\Reseller;
use Throwable;

/** White-label branding and custom-domain verification for a reseller. */
final class BrandingController extends ResellerPanelController
{
    public function index(): Response
    {
        $reseller = $this->reseller();

        return $this->render('reseller.branding', [
            'title'       => 'White-label branding',
            'reseller'    => $reseller,
            'verifyHost'  => '_dvc-verify.' . (string) ($reseller['domain'] ?? 'yourdomain.com'),
            'targetHost'  => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);
    }

    public function update(): Response
    {
        $reseller = $this->reseller();
        $resellers = new Reseller();

        $data = $this->validate([
            'brand_name'       => 'required|string|min:2|max:150',
            'tagline'          => 'nullable|string|max:190',
            'support_email'    => 'nullable|email',
            'support_phone'    => 'nullable|phone|max:25',
            'support_whatsapp' => 'nullable|phone|max:25',
        ]);

        foreach (['brand_logo' => 'logos', 'brand_favicon' => 'logos'] as $field => $folder) {
            $file = $this->request->file($field);
            if ($file === null) {
                continue;
            }
            try {
                $path = (new Uploader())->image($file, $folder, $field === 'brand_favicon' ? 256 : 600);
                if (!empty($reseller[$field])) {
                    Uploader::delete((string) $reseller[$field]);
                }
                $data[$field] = $path;
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
        }

        $resellers->updateById((int) $reseller['id'], $data);
        AuditLog::record('reseller.branding_updated', 'reseller', (int) $reseller['id']);

        $this->success('Branding saved. It appears immediately on your white-label domain.');

        return $this->redirect('reseller/branding');
    }

    public function saveDomain(): Response
    {
        $reseller = $this->reseller();
        $resellers = new Reseller();

        $domain = strtolower(trim($this->request->string('domain')));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim(explode('/', $domain)[0], '.');

        if ($domain === '') {
            $resellers->updateById((int) $reseller['id'], [
                'domain'        => null,
                'domain_status' => 'none',
                'domain_token'  => null,
            ]);
            $this->success('Custom domain removed.');

            return $this->redirect('reseller/branding');
        }

        if (preg_match('/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain) !== 1) {
            $this->error('Enter a valid domain such as cards.yourbusiness.com.');

            return $this->redirect('reseller/branding');
        }
        if ($resellers->domainExists($domain, (int) $reseller['id'])) {
            $this->error('That domain is already connected to another reseller account.');

            return $this->redirect('reseller/branding');
        }

        $token = 'dvc-verify-' . bin2hex(random_bytes(12));
        $resellers->updateById((int) $reseller['id'], [
            'domain'        => $domain,
            'domain_status' => 'pending',
            'domain_token'  => $token,
        ]);

        AuditLog::record('reseller.domain_saved', 'reseller', (int) $reseller['id'], ['domain' => $domain]);
        $this->info('Domain saved. Add the DNS records shown below, then press "Verify domain".');

        return $this->redirect('reseller/branding');
    }

    public function verifyDomain(): Response
    {
        $reseller = $this->reseller();
        $domain = (string) ($reseller['domain'] ?? '');
        $token = (string) ($reseller['domain_token'] ?? '');

        if ($domain === '' || $token === '') {
            $this->error('Save a domain first.');

            return $this->redirect('reseller/branding');
        }

        $verified = false;
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record('_dvc-verify.' . $domain, DNS_TXT);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (trim((string) ($record['txt'] ?? '')) === $token) {
                        $verified = true;
                        break;
                    }
                }
            }
        }

        (new Reseller())->updateById((int) $reseller['id'], ['domain_status' => $verified ? 'verified' : 'failed']);
        AuditLog::record('reseller.domain_verify_attempt', 'reseller', (int) $reseller['id'], [
            'domain' => $domain, 'verified' => $verified,
        ]);

        if ($verified) {
            $this->success('Domain verified. ' . $domain . ' now serves your branding — make sure it points at this server and has an SSL certificate.');
        } else {
            $this->error('We could not find the TXT record on _dvc-verify.' . $domain . '. DNS changes can take up to an hour to propagate — try again shortly.');
        }

        return $this->redirect('reseller/branding');
    }
}
