<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Csrf;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Verifica el token CSRF en todas las peticiones POST.
 */
final class VerifyCsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return $handler->handle($request);
        }

        $body = $request->getParsedBody();
        $token = is_array($body) ? ($body['_token'] ?? null) : null;

        if (! Csrf::check(is_string($token) ? $token : null)) {
            Flash::error('La sesion expiro o el formulario no es valido. Intenta nuevamente.');

            $response = new Response();
            $referer = $request->getHeaderLine('Referer');

            return $response
                ->withHeader('Location', $referer !== '' ? $referer : app_url('/login'))
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
