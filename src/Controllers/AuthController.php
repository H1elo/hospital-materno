<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Flash;
use App\Support\Session;
use App\Support\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class AuthController extends BaseController
{
    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->input($request);
        $cedula = trim((string) ($data['cedula'] ?? ''));
        $clave = (string) ($data['clave'] ?? '');

        $validator = Validator::make(
            ['cedula' => $cedula, 'clave' => $clave],
            ['cedula' => 'required|min:6|max:15', 'clave' => 'required|min:4'],
            ['cedula' => 'numero de cedula', 'clave' => 'clave de acceso']
        );

        if ($validator->fails()) {
            return $this->backWithErrors($response, $validator->errors(), $data, '/login');
        }

        $statement = $this->db()->prepare(
            'SELECT u.id_usuario, u.nacionalidad, u.cedula, u.password_hash, u.estado,
                    u.nombres, u.apellidos, u.correo,
                    r.id_rol, r.nombre_rol,
                    p.id_paciente, p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                    m.id_medico, m.nombres AS medico_nombres, m.apellidos AS medico_apellidos
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             LEFT JOIN pacientes p ON p.id_usuario = u.id_usuario
             LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
             WHERE u.cedula = :cedula
             LIMIT 1'
        );
        $statement->execute(['cedula' => $cedula]);
        $user = $statement->fetch();

        if ($user === false || ! password_verify($clave, (string) $user['password_hash'])) {
            return $this->backWithErrors(
                $response,
                ['cedula' => 'La cedula o la clave de acceso no son correctas.'],
                $data,
                '/login'
            );
        }

        if (strcasecmp((string) $user['estado'], 'Activo') !== 0) {
            return $this->backWithErrors(
                $response,
                ['cedula' => 'Tu usuario se encuentra inactivo. Contacta al administrador.'],
                $data,
                '/login'
            );
        }

        $nombres = $user['paciente_nombres'] ?: ($user['medico_nombres'] ?: $user['nombres']);
        $apellidos = $user['paciente_apellidos'] ?: ($user['medico_apellidos'] ?: $user['apellidos']);

        $authUser = [
            'id_usuario' => (int) $user['id_usuario'],
            'cedula' => $user['nacionalidad'] . '-' . $user['cedula'],
            'cedula_numero' => (string) $user['cedula'],
            'nombre' => trim((string) $nombres . ' ' . (string) $apellidos) ?: 'Usuario',
            'correo' => (string) ($user['correo'] ?? ''),
            'rol' => (string) $user['nombre_rol'],
            'id_rol' => (int) $user['id_rol'],
            'id_paciente' => $user['id_paciente'] !== null ? (int) $user['id_paciente'] : null,
            'id_medico' => $user['id_medico'] !== null ? (int) $user['id_medico'] : null,
        ];

        Session::regenerate();
        Session::set('auth_user', $authUser);
        Session::remove('_old', '_errors');

        Flash::success('Bienvenido(a), ' . $authUser['nombre'] . '.');

        return $this->redirect($response, role_home($authUser['rol']));
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->input($request);
        $cedula = trim((string) ($data['cedula'] ?? ''));
        $nombres = trim((string) ($data['nombres'] ?? ''));
        $apellidos = trim((string) ($data['apellidos'] ?? ''));
        $correo = trim((string) ($data['correo'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $validator = Validator::make(
            [
                'cedula' => $cedula,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'correo' => $correo,
                'password' => $password,
            ],
            [
                'cedula' => 'required|min:6|max:15',
                'nombres' => 'required|min:2|max:100',
                'apellidos' => 'required|min:2|max:100',
                'correo' => 'required|email|max:150',
                'password' => 'required|min:6',
            ],
            [
                'cedula' => 'numero de cedula',
                'nombres' => 'nombres',
                'apellidos' => 'apellidos',
                'correo' => 'correo electronico',
                'password' => 'contrasena',
            ]
        );

        if ($validator->fails()) {
            return $this->backWithErrors($response, $validator->errors(), $data, '/login');
        }

        $db = $this->db();

        $exists = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE cedula = :cedula OR correo = :correo');
        $exists->execute(['cedula' => $cedula, 'correo' => $correo]);

        if ((int) $exists->fetchColumn() > 0) {
            return $this->backWithErrors(
                $response,
                ['cedula' => 'Ya existe un usuario registrado con esa cedula o correo.'],
                $data,
                '/login'
            );
        }

        try {
            $db->beginTransaction();

            $rolId = (int) $db->query("SELECT id_rol FROM roles WHERE nombre_rol = 'Paciente' LIMIT 1")->fetchColumn();

            $insertUser = $db->prepare(
                'INSERT INTO usuarios (nacionalidad, cedula, password_hash, id_rol, estado, nombres, apellidos, correo)
                 VALUES (:nacionalidad, :cedula, :password_hash, :id_rol, :estado, :nombres, :apellidos, :correo)'
            );
            $insertUser->execute([
                'nacionalidad' => 'V',
                'cedula' => $cedula,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'id_rol' => $rolId,
                'estado' => 'Activo',
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'correo' => $correo,
            ]);

            $idUsuario = (int) $db->lastInsertId();

            $insertPatient = $db->prepare(
                'INSERT INTO pacientes (id_usuario, num_historia, nombres, apellidos, fecha_nacimiento, telefono)
                 VALUES (:id_usuario, :num_historia, :nombres, :apellidos, :fecha_nacimiento, :telefono)'
            );
            $insertPatient->execute([
                'id_usuario' => $idUsuario,
                'num_historia' => 'HC-' . str_pad((string) $idUsuario, 4, '0', STR_PAD_LEFT),
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'fecha_nacimiento' => (string) ($data['fecha_nacimiento'] ?? date('Y-m-d')),
                'telefono' => trim((string) ($data['telefono'] ?? '')),
            ]);

            $idPaciente = (int) $db->lastInsertId();

            $db->prepare('INSERT INTO historia (id_paciente) VALUES (:id_paciente)')
                ->execute(['id_paciente' => $idPaciente]);

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return $this->backWithErrors(
                $response,
                ['correo' => 'No se pudo crear la cuenta. Intenta nuevamente.'],
                $data,
                '/login'
            );
        }

        Flash::success('Cuenta creada exitosamente. Ya puedes iniciar sesion con tu cedula.');

        return $this->redirect($response, '/login');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        Session::destroy();

        return $this->redirect($response, '/');
    }
}
