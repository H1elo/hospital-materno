<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\MedicoController;
use App\Controllers\PacienteController;
use App\Controllers\TecnicoController;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $container = $app->getContainer();

    // -----------------------------------------------------------------------
    // Rutas publicas
    // -----------------------------------------------------------------------
    $app->get('/', [new HomeController($container), 'index'])->setName('home');

    $app->group('', function (RouteCollectorProxy $group) use ($container): void {
        $group->get('/login', [new AuthController($container), 'showLogin'])->setName('login');
        $group->post('/login', [new AuthController($container), 'login']);
        $group->post('/register', [new AuthController($container), 'register'])->setName('register');
    })->add(new GuestMiddleware());

    // -----------------------------------------------------------------------
    // Sesion (requiere autenticacion)
    // -----------------------------------------------------------------------
    $app->post('/logout', [new AuthController($container), 'logout'])
        ->setName('logout')
        ->add(new AuthMiddleware());

    // -----------------------------------------------------------------------
    // Modulo Paciente
    // -----------------------------------------------------------------------
    $app->group('/paciente', function (RouteCollectorProxy $group) use ($container): void {
        $paciente = new PacienteController($container);

        $group->get('', [$paciente, 'agendar'])->setName('paciente.agendar');
        $group->post('/citas', [$paciente, 'agendarStore'])->setName('paciente.citas.store');
        $group->post('/citas/{id}/cancelar', [$paciente, 'cancelar'])->setName('paciente.citas.cancelar');
        $group->get('/datos', [$paciente, 'datos'])->setName('paciente.datos');
        $group->post('/datos', [$paciente, 'datosUpdate'])->setName('paciente.datos.update');
        $group->post('/datos/contacto', [$paciente, 'contactoUpdate'])->setName('paciente.contacto.update');
        $group->get('/citas', [$paciente, 'citas'])->setName('paciente.citas');
        $group->get('/citas/{id}/comprobante', [$paciente, 'comprobante'])->setName('paciente.citas.comprobante');
    })->add(new RoleMiddleware(['Paciente']))->add(new AuthMiddleware());

    // -----------------------------------------------------------------------
    // Modulo Medico
    // -----------------------------------------------------------------------
    $app->group('/medico', function (RouteCollectorProxy $group) use ($container): void {
        $medico = new MedicoController($container);

        $group->get('', [$medico, 'index'])->setName('medico.index');
        $group->get('/historias', [$medico, 'historias'])->setName('medico.historias');
        $group->get('/historias/{id}', [$medico, 'historiaShow'])->setName('medico.historias.show');
        $group->post('/historias/{id}', [$medico, 'historiaStore'])->setName('medico.historias.store');
        $group->get('/horario', [$medico, 'horario'])->setName('medico.horario');
        $group->post('/citas/{id}/atender', [$medico, 'marcarAtendida'])->setName('medico.citas.atender');
    })->add(new RoleMiddleware(['Medico']))->add(new AuthMiddleware());

    // -----------------------------------------------------------------------
    // Modulo Tecnico de Registro
    // -----------------------------------------------------------------------
    $app->group('/tecnico', function (RouteCollectorProxy $group) use ($container): void {
        $tecnico = new TecnicoController($container);

        $group->get('', [$tecnico, 'index'])->setName('tecnico.index');
        $group->get('/registros', [$tecnico, 'registros'])->setName('tecnico.registros');
        $group->get('/registros/{id}', [$tecnico, 'expediente'])->setName('tecnico.expediente');
        $group->get('/exportar', [$tecnico, 'exportar'])->setName('tecnico.exportar');
        $group->get('/registros/{id}/imprimir', [$tecnico, 'imprimir'])->setName('tecnico.imprimir');
    })->add(new RoleMiddleware(['Tecnico de Registro', 'Tecnico']))->add(new AuthMiddleware());

    // -----------------------------------------------------------------------
    // Modulo Administrador
    // -----------------------------------------------------------------------
    $app->group('/admin', function (RouteCollectorProxy $group) use ($container): void {
        $admin = new AdminController($container);

        $group->get('', [$admin, 'personal'])->setName('admin.personal');
        $group->post('/personal', [$admin, 'personalStore'])->setName('admin.personal.store');
        $group->post('/personal/{id}/estado', [$admin, 'personalToggle'])->setName('admin.personal.estado');
        $group->get('/horarios', [$admin, 'horarios'])->setName('admin.horarios');
        $group->post('/horarios', [$admin, 'horarioStore'])->setName('admin.horarios.store');
        $group->post('/horarios/{id}', [$admin, 'horarioUpdate'])->setName('admin.horarios.update');
        $group->post('/horarios/{id}/eliminar', [$admin, 'horarioDelete'])->setName('admin.horarios.delete');
    })->add(new RoleMiddleware(['Administrador']))->add(new AuthMiddleware());
};
