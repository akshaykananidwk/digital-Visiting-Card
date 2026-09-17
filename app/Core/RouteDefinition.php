<?php

declare(strict_types=1);

namespace App\Core;

/** Fluent helper returned by Router::get()/post()/... */
final class RouteDefinition
{
    /** @param array<string,int> $keys */
    public function __construct(private readonly Router $router, private readonly array $keys)
    {
    }

    public function name(string $name): self
    {
        $this->router->decorate($this->keys, 'name', $name);

        return $this;
    }

    /** @param string|array<int,string> $middleware */
    public function middleware(string|array $middleware): self
    {
        $this->router->decorate($this->keys, 'middleware', (array) $middleware);

        return $this;
    }
}
