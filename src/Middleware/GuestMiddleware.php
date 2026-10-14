<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Evita que un usuario autenticado vuelva al login.
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = Session::get('auth_user');

        if (is_array($user)) {
            $response = new Response();

            return $response
                ->withHeader('Location', app_url(role_home((string) ($user['rol'] ?? ''))))
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
