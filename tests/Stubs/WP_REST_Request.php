<?php

declare(strict_types=1);

/**
 * Minimal WP_REST_Request stub for unit tests (no WordPress core loaded).
 * Integration tests that bootstrap WP should use the real class instead.
 */
class WP_REST_Request
{
    private string $method;

    private string $route;

    /** @var array<string, mixed> */
    private array $params = [];

    public function __construct(string $method = '', string $route = '')
    {
        $this->method = $method;
        $this->route = $route;
    }

    public function get_method(): string
    {
        return $this->method;
    }

    public function get_route(): string
    {
        return $this->route;
    }

    public function set_param(string $key, mixed $value): void
    {
        $this->params[$key] = $value;
    }

    public function get_param(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_params(): array
    {
        return $this->params;
    }
}
