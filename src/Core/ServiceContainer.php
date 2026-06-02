<?php

declare(strict_types=1);

namespace BsFabric\Core;

use InvalidArgumentException;

final class ServiceContainer
{
    /** @var array<string, callable(): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (! isset($this->factories[$id])) {
            throw new InvalidArgumentException(sprintf('Service not found: %s', $id));
        }

        $this->instances[$id] = ($this->factories[$id])();

        return $this->instances[$id];
    }
}
