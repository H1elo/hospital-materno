<?php

declare(strict_types=1);

namespace App\Support;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Funciones y filtros personalizados disponibles en las plantillas Twig.
 */
final class TwigExtension extends AbstractExtension
{
    /**
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('url', fn (string $path = '') => app_url($path)),
            new TwigFunction('asset', fn (string $path) => app_url('assets/' . ltrim($path, '/'))),
            new TwigFunction(
                'old',
                fn (array $context, string $key, mixed $default = '') => $context['old'][$key] ?? $default,
                ['needs_context' => true]
            ),
            new TwigFunction(
                'error',
                fn (array $context, string $key) => $context['errors'][$key] ?? null,
                ['needs_context' => true]
            ),
            new TwigFunction('auth', fn () => auth()),
            new TwigFunction('auth_name', fn () => auth()['nombre'] ?? ''),
            new TwigFunction('auth_role', fn () => auth()['rol'] ?? ''),
            new TwigFunction('can', fn (string $role) => can($role)),
            new TwigFunction('active', fn (string $path) => is_active($path)),
            new TwigFunction('exact', fn (string $path) => is_exact($path)),
            new TwigFunction('csrf_token', fn () => Csrf::token()),
            new TwigFunction('csrf_field', fn () => Csrf::field(), ['is_safe' => ['html']]),
            new TwigFunction('iniciales', fn (?string $nombre) => iniciales($nombre)),
        ];
    }

    /**
     * @return array<int, TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('fecha', fn (?string $value, string $format = 'd/m/Y') => fecha($value, $format)),
            new TwigFilter('edad', fn (?string $fechaNacimiento) => edad($fechaNacimiento)),
            new TwigFilter('iniciales', fn (?string $nombre) => iniciales($nombre)),
        ];
    }
}
