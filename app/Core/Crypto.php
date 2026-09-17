<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Authenticated encryption (AES-256-GCM) for secrets that must be stored in
 * the database — GitHub tokens, Razorpay keys, SMTP passwords.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'enc:v1:';

    private static ?string $key = null;

    public static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $raw = (string) Config::get('app.key', '');
        if ($raw === '') {
            throw new RuntimeException('APP_KEY is not configured. Run the installer or set APP_KEY in .env.');
        }
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('APP_KEY is not valid base64.');
            }
            $raw = $decoded;
        }
        if (strlen($raw) !== 32) {
            // Derive a stable 32-byte key from whatever was configured.
            $raw = hash('sha256', $raw, true);
        }

        return self::$key = $raw;
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public static function encrypt(?string $plaintext): string
    {
        if ($plaintext === null || $plaintext === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $payload): string
    {
        if ($payload === null || $payload === '') {
            return '';
        }
        if (!str_starts_with($payload, self::PREFIX)) {
            // Value was stored before encryption was enabled — return as-is.
            return $payload;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    public static function isEncrypted(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }

    /** Constant-time HMAC used for webhook signature verification. */
    public static function hmac(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public static function hmacEquals(string $payload, string $secret, string $signature): bool
    {
        return hash_equals(self::hmac($payload, $secret), $signature);
    }
}
