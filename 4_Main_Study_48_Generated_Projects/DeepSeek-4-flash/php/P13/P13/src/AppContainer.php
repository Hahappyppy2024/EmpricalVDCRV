<?php

declare(strict_types=1);

namespace P13;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Minimal PSR-11 container used to wire services and controllers.
 *
 * Supports explicit factories and reflection-based autowiring fallback so
 * route handlers such as [Controller::class, 'method'] resolve cleanly.
 */
final class AppContainer implements ContainerInterface
{
    /** @var array<string, callable(ContainerInterface): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }
        if (class_exists($id)) {
            return $this->instances[$id] = $this->resolve($id);
        }
        throw new class("Service not found: {$id}") extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {
        };
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    private function resolve(string $class): object
    {
        $ref = new ReflectionClass($class);
        $constructor = $ref->getConstructor();
        if ($constructor === null) {
            return $ref->newInstance();
        }
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && $type->getName() !== 'string') {
                $args[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        return $ref->newInstanceArgs($args);
    }
}
