<?php

declare(strict_types=1);

namespace App\Core;

/** Simple response value object the router sends to the browser. */
final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    public function __construct(
        private string $content = '',
        private int $status = 200,
        array $headers = []
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
    }

    public static function make(string $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    /** @param array<string,mixed>|bool|null $data */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            $headers + ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function download(string $content, string $filename, string $mime = 'application/octet-stream'): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $filename) ?? 'download';

        return new self($content, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Content-Length'      => (string) strlen($content),
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    public static function noContent(int $status = 204): self
    {
        return new self('', $status);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /** @return array<string,string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        echo $this->content;
    }
}
