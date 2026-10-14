<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Throwable;

/**
 * Pagina de error con el estilo del hospital.
 */
final class ErrorHandler
{
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $status = $exception instanceof \Slim\Exception\HttpException
            ? $exception->getCode()
            : 500;

        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        $titles = [
            403 => 'Acceso denegado',
            404 => 'Pagina no encontrada',
            405 => 'Metodo no permitido',
            500 => 'Ocurrio un error',
        ];

        $title = $titles[$status] ?? 'Ocurrio un error';
        $detail = $displayErrorDetails ? $exception->getMessage() : 'Intenta nuevamente en unos minutos.';

        $html = $this->render($status, $title, $detail);

        $response = new Response();
        $response->getBody()->write($html);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function render(int $status, string $title, string $detail): string
    {
        $status = (int) $status;
        $title = e($title);
        $detail = e($detail);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$status} - {$title}</title>
            <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
                       background:linear-gradient(145deg,#FFF0F5 0%,#FFF8FB 45%,#EBF5FC 100%);
                       font-family:'Nunito',sans-serif; color:#2C2438; }
                .card { background:#fff; border:1px solid rgba(245,189,208,.5); border-radius:2rem;
                        padding:3rem; max-width:32rem; text-align:center; box-shadow:0 20px 40px rgba(44,36,56,.08); }
                .code { font-family:'DM Serif Display',serif; font-size:4rem; color:#E8819E; line-height:1; }
                h1 { font-family:'DM Serif Display',serif; font-weight:400; font-size:1.75rem; margin:.75rem 0; }
                p { color:rgba(44,36,56,.6); line-height:1.6; }
                a { display:inline-block; margin-top:1.5rem; background:#E8819E; color:#fff; text-decoration:none;
                    font-weight:700; padding:.85rem 2rem; border-radius:9999px; }
                a:hover { background:#D96B8A; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="code">{$status}</div>
                <h1>{$title}</h1>
                <p>{$detail}</p>
                <a href="/">Volver al inicio</a>
            </div>
        </body>
        </html>
        HTML;
    }
}
