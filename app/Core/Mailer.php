<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Dependency-free SMTP client with a mail() fallback. Messages are queued in
 * the `notifications` table so that delivery failures are visible to the
 * administrator instead of silently disappearing.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, array $options = []): bool
    {
        $fromAddress = (string) (Settings::get('mail_from_address') ?: Env::get('MAIL_FROM_ADDRESS', ''));
        $fromName = (string) (Settings::get('mail_from_name') ?: Env::get('MAIL_FROM_NAME', (string) Config::get('app.name')));

        if ($fromAddress === '') {
            $host = Request::current()?->host() ?? 'localhost';
            $fromAddress = 'no-reply@' . preg_replace('/^www\./', '', $host);
        }

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            Logger::warning('Mail skipped: invalid recipient', ['to' => $to]);

            return false;
        }

        $textBody ??= trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody) ?? $htmlBody));

        $host = (string) (Settings::get('smtp_host') ?: Env::get('MAIL_HOST', ''));

        try {
            if ($host !== '') {
                $ok = self::smtp($host, $to, $subject, $htmlBody, $textBody, $fromAddress, $fromName);
            } else {
                $ok = self::phpMail($to, $subject, $htmlBody, $fromAddress, $fromName);
            }
        } catch (Throwable $e) {
            Logger::error('Mail delivery failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
            $ok = false;
        }

        self::record($to, $subject, $ok, $options);

        return $ok;
    }

    private static function phpMail(string $to, string $subject, string $body, string $fromAddress, string $fromName): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromAddress . '>',
            'Reply-To: ' . $fromAddress,
            'X-Mailer: DigitalVisitingCard',
        ];

        return @mail($to, self::encodeHeader($subject), $body, implode("\r\n", $headers));
    }

    private static function smtp(
        string $host,
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromAddress,
        string $fromName
    ): bool {
        $port = (int) (Settings::get('smtp_port') ?: Env::get('MAIL_PORT', 587));
        $username = (string) (Settings::get('smtp_username') ?: Env::get('MAIL_USERNAME', ''));
        $password = (string) (Settings::get('smtp_password') ?: Env::get('MAIL_PASSWORD', ''));
        $encryption = strtolower((string) (Settings::get('smtp_encryption') ?: Env::get('MAIL_ENCRYPTION', 'tls')));

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);

        $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            Logger::error('SMTP connect failed: ' . $errstr, ['host' => $host, 'port' => $port]);

            return false;
        }
        stream_set_timeout($socket, 20);

        $read = static function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }

            return $data;
        };
        $write = static function (string $command) use ($socket, $read): string {
            fwrite($socket, $command . "\r\n");

            return $read();
        };
        $code = static fn (string $response): int => (int) substr(trim($response), 0, 3);

        $banner = $read();
        if ($code($banner) !== 220) {
            fclose($socket);

            return false;
        }

        $hostname = Request::current()?->host() ?? 'localhost';
        $ehlo = $write('EHLO ' . $hostname);

        if ($encryption === 'tls') {
            if ($code($write('STARTTLS')) !== 220) {
                fclose($socket);

                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);

                return false;
            }
            $ehlo = $write('EHLO ' . $hostname);
        }

        if ($username !== '') {
            if (stripos($ehlo, 'AUTH') !== false && stripos($ehlo, 'LOGIN') !== false) {
                if ($code($write('AUTH LOGIN')) !== 334) {
                    fclose($socket);

                    return false;
                }
                if ($code($write(base64_encode($username))) !== 334) {
                    fclose($socket);

                    return false;
                }
                if ($code($write(base64_encode($password))) !== 235) {
                    Logger::error('SMTP authentication failed.');
                    fclose($socket);

                    return false;
                }
            } else {
                $authString = base64_encode("\0" . $username . "\0" . $password);
                if ($code($write('AUTH PLAIN ' . $authString)) !== 235) {
                    fclose($socket);

                    return false;
                }
            }
        }

        if ($code($write('MAIL FROM:<' . $fromAddress . '>')) !== 250) {
            fclose($socket);

            return false;
        }
        if (!in_array($code($write('RCPT TO:<' . $to . '>')), [250, 251], true)) {
            fclose($socket);

            return false;
        }
        if ($code($write('DATA')) !== 354) {
            fclose($socket);

            return false;
        }

        $boundary = 'dvc' . bin2hex(random_bytes(12));
        $message = implode("\r\n", [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromAddress . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $hostname . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            '',
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($textBody)),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($htmlBody)),
            '--' . $boundary . '--',
            '.',
        ]);

        $sent = $code($write($message)) === 250;
        $write('QUIT');
        fclose($socket);

        return $sent;
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return str_replace(["\r", "\n"], '', $value);
    }

    /** @param array<string,mixed> $options */
    private static function record(string $to, string $subject, bool $delivered, array $options): void
    {
        if (!is_installed()) {
            return;
        }
        try {
            Database::instance()->insert('notifications', [
                'user_id'    => $options['user_id'] ?? null,
                'channel'    => 'email',
                'event'      => substr((string) ($options['event'] ?? 'generic'), 0, 60),
                'recipient'  => substr($to, 0, 190),
                'title'      => substr($subject, 0, 190),
                'body'       => substr((string) ($options['summary'] ?? ''), 0, 1000),
                'status'     => $delivered ? 'sent' : 'failed',
                'sent_at'    => $delivered ? now() : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Notification logging is best-effort.
        }
    }

    /** Render a branded HTML email body. */
    public static function layout(string $heading, string $bodyHtml, ?string $ctaText = null, ?string $ctaUrl = null): string
    {
        $siteName = e((string) (Settings::get('site_name') ?: Config::get('app.name')));
        $cta = '';
        if ($ctaText !== null && $ctaUrl !== null) {
            $cta = '<tr><td style="padding:8px 0 24px"><a href="' . e($ctaUrl) . '" style="background:#4f46e5;color:#fff;'
                . 'text-decoration:none;padding:12px 24px;border-radius:8px;display:inline-block;font-weight:600">'
                . e($ctaText) . '</a></td></tr>';
        }

        return '<!doctype html><html><body style="margin:0;background:#f1f5f9;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">'
            . '<table role="presentation" width="100%" style="max-width:560px;background:#fff;border-radius:16px;padding:32px">'
            . '<tr><td style="font-size:20px;font-weight:700;color:#0f172a;padding-bottom:16px">' . e($heading) . '</td></tr>'
            . '<tr><td style="color:#334155;font-size:15px;line-height:1.7">' . $bodyHtml . '</td></tr>'
            . $cta
            . '<tr><td style="color:#94a3b8;font-size:12px;border-top:1px solid #e2e8f0;padding-top:16px">'
            . $siteName . ' &middot; This is an automated message.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
