<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/** Exception that maps directly onto an HTTP status code. */
class HttpException extends RuntimeException
{
    public function __construct(private readonly int $statusCode, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = ''): self
    {
        return new self(401, $message);
    }

    public static function badRequest(string $message = ''): self
    {
        return new self(400, $message);
    }

    public static function tokenMismatch(string $message = ''): self
    {
        return new self(419, $message);
    }

    public static function tooManyRequests(string $message = ''): self
    {
        return new self(429, $message);
    }

    public static function serviceUnavailable(string $message = ''): self
    {
        return new self(503, $message);
    }
}
