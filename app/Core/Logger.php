<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * File based, daily-rotating logger. Nothing sensitive is ever echoed to the
 * browser — detailed diagnostics live only in storage/logs.
 */
final class Logger
{
    public const EMERGENCY = 'emergency';
    public const ALERT     = 'alert';
    public const CRITICAL  = 'critical';
    public const ERROR     = 'error';
    public const WARNING   = 'warning';
    public const NOTICE    = 'notice';
    public const INFO      = 'info';
    public const DEBUG     = 'debug';

    private static int $maxFiles = 30;

    /** @param array<string,mixed> $context */
    public static function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_writable($dir)) {
            return;
        }

        $file = $dir . '/' . $channel . '-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s.%s: %s%s%s",
            date('Y-m-d H:i:s'),
            $channel,
            strtoupper($level),
            $message,
            $context === [] ? '' : ' ' . json_encode(self::scrub($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            PHP_EOL
        );

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        self::rotate($dir, $channel);
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function debug(string $message, array $context = []): void
    {
        if ((bool) Config::get('app.debug', false)) {
            self::log(self::DEBUG, $message, $context);
        }
    }

    public static function exception(Throwable $e, string $channel = 'app'): string
    {
        $reference = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
        self::log(self::CRITICAL, sprintf(
            'ref=%s %s: %s in %s:%d%s%s',
            $reference,
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
            $e->getTraceAsString()
        ), [], $channel);

        return $reference;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function scrub(array $context): array
    {
        $sensitive = ['password', 'password_confirmation', 'token', 'secret', 'key_secret', 'api_key',
            'github_token', 'razorpay_key_secret', 'webhook_secret', 'smtp_password', 'card', 'cvv'];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = self::scrub($value);

                continue;
            }
            foreach ($sensitive as $needle) {
                if (stripos((string) $key, $needle) !== false) {
                    $context[$key] = '***redacted***';
                    break;
                }
            }
        }

        return $context;
    }

    private static function rotate(string $dir, string $channel): void
    {
        $files = glob($dir . '/' . $channel . '-*.log') ?: [];
        if (count($files) <= self::$maxFiles) {
            return;
        }
        sort($files);
        $remove = array_slice($files, 0, count($files) - self::$maxFiles);
        foreach ($remove as $file) {
            @unlink($file);
        }
    }
}
