<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexion PDO unica (singleton) para toda la aplicacion.
 */
final class Database
{
    private static ?PDO $connection = null;

    /**
     * @param array<string, mixed> $config
     */
    public static function connection(array $config): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $driver = (string) ($config['driver'] ?? 'mysql');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');

        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $driver, $host, $port, $database, $charset);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        try {
            self::$connection = new PDO(
                $dsn,
                (string) ($config['username'] ?? 'root'),
                (string) ($config['password'] ?? ''),
                $options
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'No se pudo conectar a la base de datos "' . $database . '". '
                . 'Verifica que MySQL este encendido y que el archivo .env sea correcto. '
                . 'Detalle: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }

        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}
