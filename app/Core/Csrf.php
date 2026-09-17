<?php

declare(strict_types=1);

namespace App\Core;

/** Per-session CSRF token with constant-time verification. */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::KEY, $token);
        }

        return $token;
    }

    public static function verify(?string $candidate): bool
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || !is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($token, $candidate);
    }

    public static function rotate(): void
    {
        Session::forget(self::KEY);
        self::token();
    }

    /** Pull the token from the form body or the X-CSRF-Token header. */
    public static function fromRequest(Request $request): ?string
    {
        $token = $request->input('_token');
        if (is_string($token) && $token !== '') {
            return $token;
        }
        $header = $request->header('X-CSRF-Token');

        return $header !== '' ? $header : null;
    }
}
