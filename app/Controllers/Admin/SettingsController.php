<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Mailer;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Uploader;
use App\Services\RazorpayService;
use Throwable;

/**
 * Admin settings. Secrets (payment keys, SMTP password, GitHub token) are
 * write-only in the UI: they are never echoed back, and an empty submission
 * leaves the stored value untouched.
 */
final class SettingsController extends AdminController
{
    private const GROUPS = ['general', 'cards', 'payment', 'mail', 'seo', 'uploads', 'legal'];

    /** Fields that are booleans (an unchecked checkbox sends nothing). */
    private const BOOLEANS = [
        'general' => ['maintenance_mode', 'allow_registration'],
        'cards'   => ['show_platform_branding', 'reduced_motion_default'],
        'payment' => ['razorpay_enabled', 'gst_enabled', 'gst_inclusive'],
        'mail'    => ['notify_admin_on_signup', 'notify_user_on_lead'],
        'seo'     => ['enable_sitemap'],
        'uploads' => ['generate_webp'],
        'legal'   => [],
    ];

    /** Secret fields — blank means "keep the existing value". */
    private const SECRETS = ['razorpay_key_secret', 'razorpay_webhook_secret', 'smtp_password'];

    public function index(): Response
    {
        $tab = $this->request->string('tab', 'general');
        $tab = in_array($tab, self::GROUPS, true) ? $tab : 'general';

        return $this->render('admin.settings', [
            'title'    => 'Settings',
            'tab'      => $tab,
            'groups'   => self::GROUPS,
            'settings' => Settings::all(),
            'gateway'  => new RazorpayService(),
        ]);
    }

    public function update(string $group): Response
    {
        if (!in_array($group, self::GROUPS, true)) {
            $this->error('Unknown settings group.');

            return $this->redirect('admin/settings');
        }

        $values = [];

        foreach ($this->fieldsFor($group) as $key => $rule) {
            if (in_array($key, self::SECRETS, true)) {
                $value = $this->request->string($key);
                if ($value !== '') {
                    Settings::set($key, $value, $group);
                }

                continue;
            }

            $values[$key] = match ($rule) {
                'bool'  => $this->request->bool($key),
                'int'   => $this->request->int($key),
                'float' => $this->request->float($key),
                default => $this->request->string($key),
            };
        }

        foreach (self::BOOLEANS[$group] ?? [] as $flag) {
            $values[$flag] = $this->request->bool($flag);
        }

        // Image uploads (logo / favicon / OG image)
        foreach (['site_logo' => 'logos', 'site_favicon' => 'logos', 'og_image' => 'templates'] as $field => $folder) {
            $file = $this->request->file($field);
            if ($file === null) {
                continue;
            }
            try {
                $path = (new Uploader())->image($file, $folder, $field === 'site_favicon' ? 256 : 1200);
                $previous = (string) (Settings::get($field) ?? '');
                if ($previous !== '') {
                    Uploader::delete($previous);
                }
                $values[$field] = $path;
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
        }

        Settings::setMany($values, $group);
        Settings::flush();
        Settings::load(true);

        AuditLog::record('admin.settings_updated', 'settings', null, ['group' => $group, 'keys' => array_keys($values)]);
        $this->success(ucfirst($group) . ' settings saved.');

        return $this->redirect('admin/settings?tab=' . $group);
    }

    public function testRazorpay(): Response
    {
        $result = (new RazorpayService())->testConnection();

        return $this->json($result);
    }

    public function testSmtp(): Response
    {
        $to = $this->request->string('email') ?: (string) $this->currentUser()['email'];

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return $this->json(['success' => false, 'message' => 'Enter a valid email address.']);
        }

        $sent = Mailer::send(
            $to,
            'SMTP test from ' . (string) (Settings::get('site_name') ?: 'Digital Visiting Card'),
            Mailer::layout('Mail is working', '<p>This is a test message. If you are reading it, outgoing email is configured correctly.</p>'),
            null,
            ['event' => 'admin.smtp_test']
        );

        return $this->json([
            'success' => $sent,
            'message' => $sent
                ? 'Test email sent to ' . $to . '. Check the inbox (and the spam folder).'
                : 'Delivery failed. Check the SMTP host, port, credentials and encryption, then look at Logs for details.',
        ]);
    }

    /** @return array<string,string> field => cast */
    private function fieldsFor(string $group): array
    {
        return match ($group) {
            'general' => [
                'site_name' => 'string', 'site_tagline' => 'string', 'site_description' => 'string',
                'support_email' => 'string', 'support_phone' => 'string', 'support_whatsapp' => 'string',
                'currency_code' => 'string', 'currency_symbol' => 'string', 'default_country' => 'string',
            ],
            'cards' => [
                'card_url_mode' => 'string', 'expired_card_behaviour' => 'string',
                'subscription_grace_days' => 'int', 'default_whatsapp_message' => 'string',
            ],
            'payment' => [
                'razorpay_key_id' => 'string', 'razorpay_key_secret' => 'string', 'razorpay_webhook_secret' => 'string',
                'gst_rate' => 'float', 'gst_number' => 'string', 'gst_state' => 'string', 'gst_hsn' => 'string',
                'invoice_prefix' => 'string', 'company_legal_name' => 'string', 'company_address' => 'string',
            ],
            'mail' => [
                'smtp_host' => 'string', 'smtp_port' => 'int', 'smtp_username' => 'string',
                'smtp_password' => 'string', 'smtp_encryption' => 'string',
                'mail_from_address' => 'string', 'mail_from_name' => 'string',
            ],
            'seo' => [
                'meta_title' => 'string', 'meta_description' => 'string', 'meta_keywords' => 'string',
                'google_verification' => 'string',
            ],
            'uploads' => [
                'max_upload_mb' => 'int', 'image_quality' => 'int', 'max_image_width' => 'int',
            ],
            'legal' => [
                'terms_content' => 'string', 'privacy_content' => 'string',
            ],
            default => [],
        };
    }
}
