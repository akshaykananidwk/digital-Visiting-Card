<?php

declare(strict_types=1);

namespace App\Core;

/** Small cURL wrapper used for Razorpay and the GitHub update client. */
final class Http
{
    /**
     * @param array<string,string> $headers
     * @param array<string,mixed>|string|null $body
     * @return array{status:int,body:string,headers:array<string,string>,error:string,json:array<mixed>|null}
     */
    public static function request(
        string $method,
        string $url,
        array|string|null $body = null,
        array $headers = [],
        int $timeout = 30,
        ?string $auth = null,
        ?string $saveTo = null
    ): array {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'body' => '', 'headers' => [], 'error' => 'PHP cURL extension is not installed.', 'json' => null];
        }

        $ch = curl_init();
        $responseHeaders = [];

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => $saveTo === null,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'DigitalVisitingCard/' . APP_VERSION,
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ];

        $caBundle = (string) Env::get('CURL_CA_BUNDLE', '');
        if ($caBundle !== '' && is_file($caBundle)) {
            $options[CURLOPT_CAINFO] = $caBundle;
        }

        if ($auth !== null && $auth !== '') {
            $options[CURLOPT_USERPWD] = $auth;
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        }

        $fh = null;
        if ($saveTo !== null) {
            $fh = fopen($saveTo, 'wb');
            if ($fh === false) {
                curl_close($ch);

                return ['status' => 0, 'body' => '', 'headers' => [], 'error' => 'Cannot write to ' . $saveTo, 'json' => null];
            }
            $options[CURLOPT_FILE] = $fh;
        }

        if ($body !== null) {
            if (is_array($body)) {
                $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
                $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
            } else {
                $payload = $body;
            }
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        if ($headers !== []) {
            $formatted = [];
            foreach ($headers as $name => $value) {
                $formatted[] = $name . ': ' . $value;
            }
            $options[CURLOPT_HTTPHEADER] = $formatted;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = (string) curl_error($ch);
        curl_close($ch);

        if ($fh !== null) {
            fclose($fh);
        }

        $bodyString = is_string($raw) ? $raw : '';
        $json = null;
        if ($bodyString !== '') {
            $decoded = json_decode($bodyString, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        return [
            'status'  => $status,
            'body'    => $bodyString,
            'headers' => $responseHeaders,
            'error'   => $error,
            'json'    => $json,
        ];
    }

    /** @param array<string,string> $headers */
    public static function get(string $url, array $headers = [], int $timeout = 30, ?string $auth = null): array
    {
        return self::request('GET', $url, null, $headers, $timeout, $auth);
    }

    /**
     * @param array<string,mixed>|string $body
     * @param array<string,string> $headers
     */
    public static function post(string $url, array|string $body, array $headers = [], int $timeout = 30, ?string $auth = null): array
    {
        return self::request('POST', $url, $body, $headers, $timeout, $auth);
    }

    /** @param array<string,string> $headers */
    public static function download(string $url, string $destination, array $headers = [], int $timeout = 300): array
    {
        return self::request('GET', $url, null, $headers, $timeout, null, $destination);
    }
}
