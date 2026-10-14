<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Flash;
use App\Support\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class AdminController extends BaseController
{
    public function personal(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $db = $this->db();

        $query = $request->getQueryParams();
        $rol = (string) ($query['rol'] ?? 'todos');

        $sql = 'SELECT u.id_usuario, u.nacionalidad, u.cedula, u.nombres, u.apellidos, u.correo,
                       u.estado, u.creado_en, r.nombre_rol,
                       m.id_medico, m.num_colegiatura, m.telefono,
                       e.nombre_especialidad
                FROM usuarios u
                INNER JOIN roles r ON r.id_rol = u.id_rol
                LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
                LEFT JOIN especialidades e ON e.id_especialidad = m.id_especialidad';
        $params = [];

        if ($rol !== 'todos') {
            $sql .= ' WHERE r.nombre_rol = :rol';
            $params['rol'] = $rol;
        }

        $sql .= ' ORDER BY r.nombre_rol, u.apellidos, u.nombres';

        $statement = $db->prepare($sql);
        $statement->execute($params);

        $metrics = $db->query(
            "SELECT
                SUM(CASE WHEN r.nombre_rol = 'Medico' THEN 1 ELSE 0 END) AS medicos,
                SUM(CASE WHEN r.nombre_rol = 'Tecnico de Registro' THEN 1 ELSE 0 END) AS tecnicos,
                (SELECT COUNT(*) FROM especialidades) AS especialidades,
                COUNT(*) AS total
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol"
        )->fetch() ?: [];

        return $this->render($response, 'admin/personal.twig', [
            'empleados' => $statement->fetchAll(),
            'roles' => $db->query('SELECT * FROM roles ORDER BY id_rol')->fetchAll(),
            'especialidades' => $db->query('SELECT * FROM especialidades ORDER BY nombre_especialidad')->fetchAll(),
            'metrics' => $metrics,
            'filtro_rol' => $rol,
        ]);
    }

    public function personalStore(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->input($request);

        $validator = Validator::make($data, [
            'rol' => 'required',
            'cedula' => 'required|min:6|max:15',
            'nombres' => 'required|min:2|max:100',
            'apellidos' => 'required|min:2|max:100',
            'correo' => 'required|email|max:150',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($response, $validator->errors(), $data, '/admin');
        }

        $db = $this->db();
        $cedula = trim((string) $data['cedula']);
        $correo = trim((string) $data['correo']);
        $rolNombre = (string) $data['rol'];

        $rolStatement = $db->prepare('SELECT id_rol FROM roles WHERE nombre_rol = :rol');
        $rolStatement->execute(['rol' => $rolNombre]);
        $idRol = $rolStatement->fetchColumn();

        if ($idRol === false) {
            Flash::error('El rol seleccionado no es valido.');
            return $this->redirect($response, '/admin');
        }

        $exists = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE cedula = :cedula OR correo = :correo');
        $exists->execute(['cedula' => $cedula, 'correo' => $correo]);

        if ((int) $exists->fetchColumn() > 0) {
            return $this->backWithErrors(
                $response,
                ['cedula' => 'Ya existe un usuario con esa cedula o correo.'],
                $data,
                '/admin'
            );
        }

        $temporaryPassword = $this->temporaryPassword();

        try {
            $db->beginTransaction();

            $insert = $db->prepare(
                'INSERT INTO usuarios (nacionalidad, cedula, password_hash, id_rol, estado, nombres, apellidos, correo)
                 VALUES (:nacionalidad, :cedula, :password_hash, :id_rol, :estado, :nombres, :apellidos, :correo)'
            );
            $insert->execute([
                'nacionalidad' => 'V',
                'cedula' => $cedula,
                'password_hash' => password_hash($temporaryPassword, PASSWORD_BCRYPT),
                'id_rol' => (int) $idRol,
                'estado' => 'Activo',
                'nombres' => trim((string) $data['nombres']),
                'apellidos' => trim((string) $data['apellidos']),
                'correo' => $correo,
            ]);

            $idUsuario = (int) $db->lastInsertId();

            if ($rolNombre === 'Medico') {
                $especialidad = (int) ($data['id_especialidad'] ?? 0);

                if ($especialidad <= 0) {
                    $especialidad = (int) $db->query('SELECT id_especialidad FROM especialidades ORDER BY id_especialidad LIMIT 1')->fetchColumn();
                }

                $insertMedico = $db->prepare(
                    'INSERT INTO medicos (id_usuario, id_especialidad, nombres, apellidos, num_colegiatura, telefono)
                     VALUES (:id_usuario, :id_especialidad, :nombres, :apellidos, :num_colegiatura, :telefono)'
                );
                $insertMedico->execute([
                    'id_usuario' => $idUsuario,
                    'id_especialidad' => $especialidad,
                    'nombres' => trim((string) $data['nombres']),
                    'apellidos' => trim((string) $data['apellidos']),
                    'num_colegiatura' => trim((string) ($data['num_colegiatura'] ?? '')) ?: null,
                    'telefono' => trim((string) ($data['telefono'] ?? '')) ?: null,
                ]);
            }

            $db->commit();
        } catch (Throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Flash::error('No se pudo registrar el empleado.');
            return $this->redirect($response, '/admin');
        }

        Flash::success(
            'Empleado registrado. Usuario: ' . $cedula . ' | Clave temporal: ' . $temporaryPassword
        );

        return $this->redirect($response, '/admin');
    }

    public function personalToggle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        $db = $this->db();

        $current = $db->prepare('SELECT estado FROM usuarios WHERE id_usuario = :id');
        $current->execute(['id' => $id]);
        $estado = $current->fetchColumn();

        if ($estado === false) {
            Flash::error('No se encontro el usuario.');
            return $this->redirect($response, '/admin');
        }

        if ($id === (int) (auth()['id_usuario'] ?? 0)) {
            Flash::error('No puedes desactivar tu propio usuario.');
            return $this->redirect($response, '/admin');
        }

        $nuevo = strcasecmp((string) $estado, 'Activo') === 0 ? 'Inactivo' : 'Activo';

        $db->prepare('UPDATE usuarios SET estado = :estado WHERE id_usuario = :id')
            ->execute(['estado' => $nuevo, 'id' => $id]);

        Flash::success('El usuario fue marcado como ' . $nuevo . '.');

        return $this->redirect($response, '/admin');
    }

    public function horarios(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $db = $this->db();

        $medicos = $db->query(
            'SELECT m.id_medico, m.nombres, m.apellidos, e.nombre_especialidad
             FROM medicos m
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             ORDER BY m.apellidos, m.nombres'
        )->fetchAll();

        $statement = $db->query(
            'SELECT h.*, m.nombres, m.apellidos
             FROM horarios_medico h
             INNER JOIN medicos m ON m.id_medico = h.id_medico
             ORDER BY m.apellidos, m.nombres, FIELD(h.dia_semana,
                "Lunes","Martes","Miercoles","Jueves","Viernes","Sabado","Domingo"), h.hora_inicio'
        );

        $ocupacion = $db->query(
            "SELECT h.id_horario,
                    (SELECT COUNT(*) FROM citas c
                     WHERE c.id_medico = h.id_medico
                       AND c.estado <> 'Cancelada'
                       AND DAYNAME(c.fecha_cita) = h.dia_semana
                       AND c.fecha_cita BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS reservadas
             FROM horarios_medico h"
        )->fetchAll();

        $ocupacionMap = [];

        foreach ($ocupacion as $row) {
            $ocupacionMap[(int) $row['id_horario']] = (int) $row['reservadas'];
        }

        return $this->render($response, 'admin/horarios.twig', [
            'medicos' => $medicos,
            'horarios' => $statement->fetchAll(),
            'ocupacion' => $ocupacionMap,
            'dias' => ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'],
        ]);
    }

    public function horarioStore(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->input($request);

        $validator = Validator::make($data, [
            'id_medico' => 'required|numeric',
            'dia_semana' => 'required|in:Lunes,Martes,Miercoles,Jueves,Viernes,Sabado,Domingo',
            'hora_inicio' => 'required',
            'hora_fin' => 'required',
            'cupos_max' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            Flash::error((string) $validator->firstError());
            return $this->redirect($response, '/admin/horarios');
        }

        if (strtotime((string) $data['hora_fin']) <= strtotime((string) $data['hora_inicio'])) {
            Flash::error('La hora de fin debe ser mayor que la hora de inicio.');
            return $this->redirect($response, '/admin/horarios');
        }

        try {
            $insert = $this->db()->prepare(
                'INSERT INTO horarios_medico (id_medico, dia_semana, hora_inicio, hora_fin, cupos_max, consultorio, activo)
                 VALUES (:id_medico, :dia_semana, :hora_inicio, :hora_fin, :cupos_max, :consultorio, 1)'
            );
            $insert->execute([
                'id_medico' => (int) $data['id_medico'],
                'dia_semana' => (string) $data['dia_semana'],
                'hora_inicio' => (string) $data['hora_inicio'],
                'hora_fin' => (string) $data['hora_fin'],
                'cupos_max' => (int) $data['cupos_max'],
                'consultorio' => trim((string) ($data['consultorio'] ?? '')) ?: null,
            ]);
        } catch (Throwable) {
            Flash::error('Ya existe un horario con ese dia y hora para el medico.');
            return $this->redirect($response, '/admin/horarios');
        }

        Flash::success('Horario agregado correctamente.');

        return $this->redirect($response, '/admin/horarios');
    }

    public function horarioUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        $data = $this->input($request);

        $update = $this->db()->prepare(
            'UPDATE horarios_medico
             SET dia_semana = :dia_semana, hora_inicio = :hora_inicio, hora_fin = :hora_fin,
                 cupos_max = :cupos_max, consultorio = :consultorio, activo = :activo
             WHERE id_horario = :id'
        );
        $update->execute([
            'dia_semana' => (string) ($data['dia_semana'] ?? 'Lunes'),
            'hora_inicio' => (string) ($data['hora_inicio'] ?? '07:00'),
            'hora_fin' => (string) ($data['hora_fin'] ?? '12:00'),
            'cupos_max' => max(1, (int) ($data['cupos_max'] ?? 1)),
            'consultorio' => trim((string) ($data['consultorio'] ?? '')) ?: null,
            'activo' => isset($data['activo']) && (string) $data['activo'] === '1' ? 1 : 0,
            'id' => $id,
        ]);

        Flash::success('Horario actualizado.');

        return $this->redirect($response, '/admin/horarios');
    }

    public function horarioDelete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);

        $this->db()->prepare('DELETE FROM horarios_medico WHERE id_horario = :id')
            ->execute(['id' => $id]);

        Flash::success('Horario eliminado.');

        return $this->redirect($response, '/admin/horarios');
    }

    private function temporaryPassword(int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}
