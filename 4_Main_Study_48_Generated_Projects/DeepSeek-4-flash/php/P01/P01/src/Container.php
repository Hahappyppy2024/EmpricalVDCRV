<?php

declare(strict_types=1);

namespace App;

use Psr\Container\ContainerInterface;

/**
 * Minimal PSR-11 container with factory closures. Factories are wired
 * explicitly in App::create() so no auto-wiring/reflection is required.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, object> */
    private array $instances = [];
    /** @var array<string, callable(Container): object> */
    private array $factories = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException('Service not found in container: ' . $id);
        }
        $factory = $this->factories[$id];
        $instance = $factory($this);
        $this->instances[$id] = $instance;
        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }
}
