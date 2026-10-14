<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * Contenedor PSR-11 minimo: resuelve servicios definidos con closures.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $resolved = [];

    public function set(string $id, mixed $value): void
    {
        $this->definitions[$id] = $value;
        unset($this->resolved[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (! array_key_exists($id, $this->definitions)) {
            throw new NotFoundException("No se encontro el servicio \"{$id}\" en el contenedor.");
        }

        $value = $this->definitions[$id];
        $instance = $value instanceof Closure ? $value($this) : $value;

        $this->resolved[$id] = $instance;

        return $instance;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions);
    }
}
