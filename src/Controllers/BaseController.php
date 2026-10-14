<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Flash;
use App\Support\Session;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * Controlador base con utilidades compartidas.
 */
abstract class BaseController
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    protected function view(): Twig
    {
        /** @var Twig $view */
        $view = $this->container->get('view');

        return $view;
    }

    protected function db(): PDO
    {
        /** @var PDO $pdo */
        $pdo = $this->container->get('db');

        return $pdo;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        return $this->view()->render($response, $template, $data);
    }

    protected function redirect(ResponseInterface $response, string $path): ResponseInterface
    {
        return $response
            ->withHeader('Location', app_url($path))
            ->withStatus(302);
    }

    protected function back(ResponseInterface $response, string $fallback = '/'): ResponseInterface
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        return $this->redirect($response, is_string($referer) && $referer !== '' ? $referer : $fallback);
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Guarda los datos del formulario para repoblarlo tras un error.
     *
     * @param array<string, mixed> $data
     */
    protected function rememberInput(array $data): void
    {
        unset($data['_token'], $data['password'], $data['clave'], $data['password_hash']);

        Session::set('_old', $data);
    }

    /**
     * Guarda errores de validacion + datos del formulario y redirige.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed>  $input
     */
    protected function backWithErrors(
        ResponseInterface $response,
        array $errors,
        array $input,
        string $path
    ): ResponseInterface {
        Session::set('_errors', $errors);
        $this->rememberInput($input);

        return $this->redirect($response, $path);
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    protected function toast(string $type, string $message): void
    {
        match ($type) {
            'success' => Flash::success($message),
            'error' => Flash::error($message),
            default => Flash::info($message),
        };
    }
}
