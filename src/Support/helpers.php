<?php

declare(strict_types=1);

use App\Support\Session;

if (! function_exists('e')) {
    /**
     * Escapa texto para HTML.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('app_url')) {
    /**
     * Construye una URL absoluta a partir de una ruta relativa.
     */
    function app_url(string $path = ''): string
    {
        $base = rtrim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''), '/');

        return $base . '/' . ltrim($path, '/');
    }
}

if (! function_exists('auth')) {
    /**
     * Devuelve el usuario autenticado o null.
     *
     * @return array<string, mixed>|null
     */
    function auth(): ?array
    {
        $user = Session::get('auth_user');

        return is_array($user) ? $user : null;
    }
}

if (! function_exists('can')) {
    /**
     * Comprueba si el usuario autenticado tiene un rol determinado.
     */
    function can(string $role): bool
    {
        $user = auth();

        return $user !== null && strcasecmp((string) ($user['rol'] ?? ''), $role) === 0;
    }
}

if (! function_exists('role_home')) {
    /**
     * Ruta de inicio segun el rol del usuario.
     */
    function role_home(?string $rol): string
    {
        return match (strtolower(trim((string) $rol))) {
            'paciente' => '/paciente',
            'medico', 'médico' => '/medico',
            'tecnico', 'técnico', 'tecnico de registro', 'técnico de registro' => '/tecnico',
            'administrador', 'admin', 'personal administrativo' => '/admin',
            default => '/login',
        };
    }
}

if (! function_exists('is_active')) {
    /**
     * Indica si la ruta actual empieza por el prefijo indicado.
     */
    function is_active(string $prefix): bool
    {
        $current = App\Support\View::currentPath();
        $prefix = '/' . trim($prefix, '/');

        if ($prefix === '/') {
            return $current === '/';
        }

        return $current === $prefix || str_starts_with($current, $prefix . '/');
    }
}

if (! function_exists('is_exact')) {
    /**
     * Indica si la ruta actual coincide exactamente con la indicada.
     */
    function is_exact(string $path): bool
    {
        return rtrim(App\Support\View::currentPath(), '/') === rtrim('/' . trim($path, '/'), '/');
    }
}

if (! function_exists('old')) {
    /**
     * Recupera un valor previo de formulario.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::get('_old', []);

        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }
}

if (! function_exists('fecha')) {
    /**
     * Formatea una fecha de la base de datos.
     */
    function fecha(?string $value, string $format = 'd/m/Y'): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return '-';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '-' : date($format, $timestamp);
    }
}

if (! function_exists('edad')) {
    /**
     * Calcula la edad en anios a partir de una fecha de nacimiento.
     */
    function edad(?string $fechaNacimiento): int
    {
        if ($fechaNacimiento === null || $fechaNacimiento === '') {
            return 0;
        }

        try {
            $nacimiento = new DateTimeImmutable($fechaNacimiento);
        } catch (Exception) {
            return 0;
        }

        return $nacimiento->diff(new DateTimeImmutable('today'))->y;
    }
}

if (! function_exists('iniciales')) {
    /**
     * Devuelve las iniciales de un nombre completo.
     */
    function iniciales(?string $nombre): string
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $nombre) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters;
    }
}
