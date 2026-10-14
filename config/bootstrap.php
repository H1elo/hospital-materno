<?php

declare(strict_types=1);

use App\Support\Database;
use App\Support\View;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return static function (App $app): void {
    $container = $app->getContainer();

    if ($container === null) {
        return;
    }

    $settings = $container->get('settings');

    $container->set('db', static function () use ($settings) {
        return Database::connection($settings['db']);
    });

    $twig = View::factory($settings['view'], [
        'app_name' => $settings['app']['name'] ?? 'Hospital Materno Rosa',
        'app_url' => $settings['app']['url'] ?? '',
        'debug' => (bool) ($settings['settings']['displayErrorDetails'] ?? false),
    ]);

    $container->set('view', static function () use ($twig): Twig {
        return $twig;
    });

    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    $app->add(new TwigMiddleware(
        $twig,
        $app->getRouteCollector()->getRouteParser(),
        $app->getBasePath()
    ));

    $errorMiddleware = $app->addErrorMiddleware(
        (bool) $settings['settings']['displayErrorDetails'],
        true,
        true
    );

    $errorHandler = new \App\Support\ErrorHandler();
    $errorMiddleware->setDefaultErrorHandler(
        static fn ($request, $exception, $display, $logErrors, $logDetails) => $errorHandler(
            $request,
            $exception,
            $display,
            $logErrors,
            $logDetails
        )
    );

    (require BASE_PATH . '/src/Routes/middleware.php')($app);
};
