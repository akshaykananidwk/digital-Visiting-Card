<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Converts PHP warnings/notices into exceptions, renders friendly error pages
 * to visitors and writes full diagnostics to the log.
 */
final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $debug = (bool) Env::get('APP_DEBUG', false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0) use ($debug): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            // Deprecations must never take a production site down; they are
            // recorded so they can be fixed before the next PHP upgrade.
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                Logger::log(Logger::NOTICE, sprintf('%s in %s:%d', $message, $file, $line), [], 'deprecations');

                return true;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::handleException(new ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ));
            }
        });
    }

    public static function handleException(Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
        $reference = 'N/A';

        if ($status >= 500) {
            $reference = Logger::exception($e);
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, sprintf("[%d] %s: %s in %s:%d\n%s\n", $status, $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code($status);
        }

        // Clear any partially rendered output so the error page is clean.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (Request::current()?->wantsJson()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success'   => false,
                'status'    => $status,
                'message'   => self::publicMessage($e, $status),
                'reference' => $reference,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        self::renderPage($status, self::publicMessage($e, $status), $reference, $e);
        exit;
    }

    private static function publicMessage(Throwable $e, int $status): string
    {
        if ($e instanceof HttpException) {
            return $e->getMessage() !== '' ? $e->getMessage() : self::defaultMessage($status);
        }
        if ((bool) Config::get('app.debug', false)) {
            return $e->getMessage();
        }

        return self::defaultMessage($status);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400     => 'The request could not be understood.',
            401     => 'You need to sign in to continue.',
            403     => 'You do not have permission to access this page.',
            404     => 'The page you are looking for could not be found.',
            419     => 'Your session expired. Please refresh the page and try again.',
            429     => 'Too many requests. Please slow down and try again shortly.',
            503     => 'We are performing scheduled maintenance. Please check back soon.',
            default => 'Something went wrong on our side. Our team has been notified.',
        };
    }

    private static function renderPage(int $status, string $message, string $reference, Throwable $e): void
    {
        $debug = (bool) Config::get('app.debug', false);
        $view = VIEW_PATH . '/errors/error.php';

        if (is_file($view)) {
            try {
                (new View())->renderFile($view, compact('status', 'message', 'reference', 'debug', 'e'));

                return;
            } catch (Throwable) {
                // fall through to the inline page below
            }
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $status . '</title><style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;text-align:center;padding:24px}'
            . 'h1{font-size:64px;margin:0}p{color:#94a3b8}code{color:#38bdf8}</style></head><body><div><h1>' . $status . '</h1>'
            . '<p>' . e($message) . '</p>'
            . ($reference !== 'N/A' ? '<p>Reference: <code>' . e($reference) . '</code></p>' : '')
            . '<p><a style="color:#38bdf8" href="' . e(Url::to('/')) . '">Back to home</a></p></div></body></html>';
    }
}
