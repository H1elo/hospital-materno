<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Flash;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Exige que exista una sesion activa.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (Session::get('auth_user') === null) {
            Flash::error('Debes iniciar sesion para continuar.');

            $response = new Response();

            return $response
                ->withHeader('Location', app_url('/login'))
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
