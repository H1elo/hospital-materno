<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Proteccion CSRF basada en token de sesion.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();

        $token = Session::get(self::KEY);

        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(?string $token): bool
    {
        $expected = Session::get(self::KEY);

        if (! is_string($expected) || $expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }
}
