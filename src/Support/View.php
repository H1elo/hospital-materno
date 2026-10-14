<?php

declare(strict_types=1);

namespace App\Support;

use Slim\Views\Twig;

/**
 * Construye el entorno Twig con las funciones y filtros del proyecto.
 */
final class View
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $globals
     */
    public static function factory(array $config, array $globals = []): Twig
    {
        Session::start();

        $cache = $config['cache'] ?? false;

        if (is_string($cache) && $cache !== '' && ! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        $twig = Twig::create((string) ($config['path'] ?? BASE_PATH . '/views'), [
            'cache' => $cache,
            'debug' => (bool) ($globals['debug'] ?? false),
            'auto_reload' => true,
        ]);

        $environment = $twig->getEnvironment();

        $environment->addExtension(new TwigExtension());

        $environment->addGlobal('app_name', $globals['app_name'] ?? 'Hospital Materno Rosa');
        $environment->addGlobal('app_url', rtrim((string) ($globals['app_url'] ?? ''), '/'));
        $environment->addGlobal('auth_user', Session::get('auth_user'));
        $environment->addGlobal('flashes', Flash::pull());
        $environment->addGlobal('old', Session::pull('_old', []));
        $environment->addGlobal('errors', Session::pull('_errors', []));
        $environment->addGlobal('current_path', self::currentPath());

        if (! empty($globals['debug'])) {
            $environment->addExtension(new \Twig\Extension\DebugExtension());
        }

        return $twig;
    }

    public static function currentPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }
}
