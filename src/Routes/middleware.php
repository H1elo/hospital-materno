<?php

declare(strict_types=1);

use App\Middleware\VerifyCsrfMiddleware;
use Slim\App;

/**
 * Middleware global de la aplicacion.
 */
return static function (App $app): void {
    $app->add(new VerifyCsrfMiddleware());
};
