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
 * Restringe el acceso segun el rol del usuario autenticado.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @param array<int, string> $roles
     */
    public function __construct(private array $roles)
    {
    }

    /**
     * @param array<int, string>|string $roles
     */
    public static function allow(array|string $roles): self
    {
        return new self(is_array($roles) ? $roles : [$roles]);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = Session::get('auth_user');

        if (! is_array($user)) {
            $response = new Response();

            return $response->withHeader('Location', app_url('/login'))->withStatus(302);
        }

        $rol = (string) ($user['rol'] ?? '');

        foreach ($this->roles as $allowed) {
            if (strcasecmp($rol, $allowed) === 0) {
                return $handler->handle($request);
            }
        }

        Flash::error('No tienes permisos para acceder a esa seccion.');

        $response = new Response();

        return $response
            ->withHeader('Location', app_url(role_home($rol)))
            ->withStatus(302);
    }
}
