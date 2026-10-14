<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;

define('BASE_PATH', dirname(__DIR__));

if (! is_file(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('Ejecuta "composer install" antes de iniciar la aplicacion.');
}

require BASE_PATH . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();

$settings = require BASE_PATH . '/config/settings.php';

$container = new App\Support\Container();
$container->set('settings', $settings);

$app = AppFactory::create(container: $container);

(require BASE_PATH . '/config/bootstrap.php')($app);
(require BASE_PATH . '/src/Routes/web.php')($app);

$app->run();
