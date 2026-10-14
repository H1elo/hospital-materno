<?php

/**
 * Crea la base de datos y carga el esquema + datos de demostracion.
 *
 * Uso:  php database/setup.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();

$env = static fn (string $key, string $default = ''): string => (string) ($_ENV[$key] ?? getenv($key) ?: $default);

$host = $env('DB_HOST', '127.0.0.1');
$port = $env('DB_PORT', '3306');
$database = $env('DB_DATABASE', 'hospital_materno');
$username = $env('DB_USERNAME', 'root');
$password = $env('DB_PASSWORD', '');
$charset = $env('DB_CHARSET', 'utf8mb4');

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset={$charset}", $username, $password, $options);
} catch (PDOException $exception) {
    fwrite(STDERR, "[ERROR] No se pudo conectar a MySQL: {$exception->getMessage()}\n");
    fwrite(STDERR, "Verifica que MySQL este encendido (XAMPP) y las credenciales del archivo .env\n");
    exit(1);
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
$pdo->exec("USE `{$database}`");

echo "[OK] Base de datos '{$database}' lista.\n";

foreach (['schema.sql', 'seed.sql'] as $file) {
    $path = __DIR__ . '/' . $file;

    if (! is_file($path)) {
        fwrite(STDERR, "[ERROR] No se encontro {$path}\n");
        exit(1);
    }

    $sql = (string) file_get_contents($path);
    $statements = split_sql($sql);

    $count = 0;

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $count++;
        } catch (PDOException $exception) {
            fwrite(STDERR, "[ERROR] En {$file}: {$exception->getMessage()}\n");
            fwrite(STDERR, "Sentencia: " . substr($statement, 0, 160) . "...\n");
            exit(1);
        }
    }

    echo "[OK] {$file} ejecutado ({$count} sentencias).\n";
}

echo "\nListo. Cuentas de demostracion (clave: 123456):\n";
echo "  Administrador      -> V-10000001\n";
echo "  Medico             -> V-20000002 (Dra. Carmen Lopez)\n";
echo "  Medico             -> V-20000003 (Dr. Andres Vargas)\n";
echo "  Tecnico de Registro-> V-30000004\n";
echo "  Paciente           -> V-40000005 (Maria Elena Perez)\n";
echo "  Paciente           -> V-40000006 (Rosa Martinez)\n";

/**
 * Divide un archivo SQL en sentencias individuales.
 *
 * @return array<int, string>
 */
function split_sql(string $sql): array
{
    $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
    $clean = [];

    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line) === 1) {
            continue;
        }

        $clean[] = $line;
    }

    $parts = explode(';', implode("\n", $clean));
    $statements = [];

    foreach ($parts as $part) {
        $part = trim($part);

        if ($part !== '') {
            $statements[] = $part;
        }
    }

    return $statements;
}
