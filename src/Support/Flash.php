<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mensajes flash de una sola lectura (exito / error / info).
 */
final class Flash
{
    private const KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        $messages = Session::get(self::KEY, []);
        $messages[] = ['type' => $type, 'message' => $message];
        Session::set(self::KEY, $messages);
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    public static function pull(): array
    {
        $messages = Session::get(self::KEY, []);
        Session::remove(self::KEY);

        return is_array($messages) ? $messages : [];
    }
}
