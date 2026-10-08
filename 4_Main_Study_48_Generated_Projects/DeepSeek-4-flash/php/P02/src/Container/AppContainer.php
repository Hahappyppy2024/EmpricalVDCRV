<?php

declare(strict_types=1);

namespace App\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

final class AppContainer implements ContainerInterface
{
    /** @var array<string, callable|mixed> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $resolved = [];

    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $id => $definition) {
            $this->set($id, $definition);
        }
    }

    public function set(string $id, mixed $definition): void
    {
        $this->definitions[$id] = $definition;
        unset($this->resolved[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }
        if (!isset($this->definitions[$id])) {
            throw new class($id) extends \RuntimeException implements NotFoundExceptionInterface {
                public function __construct(public readonly string $serviceId)
                {
                    parent::__construct('Service "' . $serviceId . '" not found in container');
                }
            };
        }
        $definition = $this->definitions[$id];
        $value = is_callable($definition) ? $definition($this) : $definition;
        $this->resolved[$id] = $value;
        return $value;
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]);
    }
}
