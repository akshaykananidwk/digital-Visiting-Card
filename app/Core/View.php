<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Plain-PHP template renderer with layout inheritance and section stacks.
 * Views live in app/Views and are addressed with dot notation.
 */
final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    /** @var array<string,string> */
    private array $sections = [];

    /** @var array<int,string> */
    private array $sectionStack = [];

    private ?string $layout = null;

    /** @var array<string,mixed> */
    private array $layoutData = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @return array<string,mixed> */
    public static function sharedData(): array
    {
        return self::$shared;
    }

    /** @param array<string,mixed> $data */
    public function make(string $view, array $data = []): string
    {
        $path = self::path($view);

        return $this->capture($path, $data);
    }

    /** @param array<string,mixed> $data */
    public function render(string $view, array $data = [], int $status = 200): Response
    {
        return Response::make($this->make($view, $data), $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @param array<string,mixed> $data */
    public function renderFile(string $file, array $data = []): void
    {
        echo $this->capture($file, $data);
    }

    public static function path(string $view): string
    {
        $relative = str_replace(['..', '\\'], '', $view);
        $relative = str_replace('.', '/', $relative);
        $path = VIEW_PATH . '/' . $relative . '.php';

        if (!is_file($path)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        return $path;
    }

    public static function exists(string $view): bool
    {
        $relative = str_replace(['..', '\\'], '', $view);

        return is_file(VIEW_PATH . '/' . str_replace('.', '/', $relative) . '.php');
    }

    /** @param array<string,mixed> $data */
    private function capture(string $path, array $data): string
    {
        $vars = array_merge(self::$shared, $data);
        $vars['__view'] = $this;

        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__path, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                require $__path;
            })($path, $vars);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        $content = (string) ob_get_clean();

        if ($this->layout !== null) {
            $layout = $this->layout;
            $layoutData = $this->layoutData;
            $this->layout = null;
            $this->layoutData = [];
            $this->sections['content'] = $this->sections['content'] ?? $content;

            $child = new self();
            $child->sections = $this->sections;

            return $child->capture(self::path($layout), array_merge($data, $layoutData));
        }

        return $content;
    }

    // ------------------------------------------------------- Template API --

    /** @param array<string,mixed> $data */
    public function extend(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function start(string $section): void
    {
        $this->sectionStack[] = $section;
        ob_start();
    }

    public function stop(): void
    {
        $section = array_pop($this->sectionStack);
        $content = (string) ob_get_clean();
        if ($section !== null) {
            $this->sections[$section] = $content;
        }
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    public function set(string $name, string $value): void
    {
        $this->sections[$name] = $value;
    }

    /** @param array<string,mixed> $data */
    public function include(string $view, array $data = []): string
    {
        $child = new self();

        return $child->capture(self::path($view), $data);
    }

    /** @param array<string,mixed> $data */
    public function includeIf(string $view, array $data = []): string
    {
        return self::exists($view) ? $this->include($view, $data) : '';
    }
}
