<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Flash;
use App\Support\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MedicoController extends BaseController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $medico = $this->medico();

        if ($medico === null) {
            Flash::error('Tu usuario no tiene ficha de medico asociada.');
            return $this->redirect($response, '/login');
        }

        $db = $this->db();

        $stats = $db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado = 'Atendida' THEN 1 ELSE 0 END) AS atendidas,
                SUM(CASE WHEN estado = 'Cancelada' THEN 1 ELSE 0 END) AS canceladas
             FROM citas WHERE id_medico = :medico"
        );
        $stats->execute(['medico' => $medico['id_medico']]);

        $waiting = $db->prepare(
            "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado, c.motivo_consulta,
                    p.id_paciente, p.nombres, p.apellidos, p.num_historia, p.fecha_nacimiento,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad
             FROM citas c
             INNER JOIN pacientes p ON p.id_paciente = c.id_paciente
             WHERE c.id_medico = :medico AND c.estado = 'Pendiente' AND c.fecha_cita = CURDATE()
             ORDER BY c.hora_cita ASC"
        );
        $waiting->execute(['medico' => $medico['id_medico']]);

        $proximas = $db->prepare(
            "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.motivo_consulta,
                    p.nombres, p.apellidos, p.num_historia
             FROM citas c
             INNER JOIN pacientes p ON p.id_paciente = c.id_paciente
             WHERE c.id_medico = :medico AND c.estado = 'Pendiente' AND c.fecha_cita > CURDATE()
             ORDER BY c.fecha_cita ASC, c.hora_cita ASC
             LIMIT 6"
        );
        $proximas->execute(['medico' => $medico['id_medico']]);

        return $this->render($response, 'medico/index.twig', [
            'medico' => $medico,
            'stats' => $stats->fetch() ?: [],
            'en_espera' => $waiting->fetchAll(),
            'proximas' => $proximas->fetchAll(),
        ]);
    }

    public function historias(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $medico = $this->medico();

        if ($medico === null) {
            return $this->redirect($response, '/login');
        }

        $query = $request->getQueryParams();
        $buscar = trim((string) ($query['buscar'] ?? ''));
        $tipo = (string) ($query['tipo'] ?? 'todas');

        $sql = "SELECT d.id_consulta, d.id_cita, d.tipo_consulta, d.diagnostico, d.fecha_consulta,
                       p.id_paciente, p.nombres, p.apellidos, p.num_historia, p.fecha_nacimiento
                FROM detalle_historia d
                INNER JOIN pacientes p ON p.id_paciente = d.id_paciente
                WHERE d.id_medico = :medico";
        $params = ['medico' => $medico['id_medico']];

        if ($buscar !== '') {
            $sql .= ' AND (p.nombres LIKE :buscar OR p.apellidos LIKE :buscar OR p.num_historia LIKE :buscar OR d.diagnostico LIKE :buscar)';
            $params['buscar'] = '%' . $buscar . '%';
        }

        if ($tipo === 'primera') {
            $sql .= " AND d.tipo_consulta LIKE 'Primera%'";
        } elseif ($tipo === 'control') {
            $sql .= " AND d.tipo_consulta LIKE 'Control%'";
        }

        $sql .= ' ORDER BY d.fecha_consulta DESC';

        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $registros = $statement->fetchAll();

        $pendientes = $this->db()->prepare(
            "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.motivo_consulta,
                    p.id_paciente, p.nombres, p.apellidos, p.num_historia, p.fecha_nacimiento,
                    (SELECT COUNT(*) FROM detalle_historia d WHERE d.id_paciente = p.id_paciente) AS consultas_previas
             FROM citas c
             INNER JOIN pacientes p ON p.id_paciente = c.id_paciente
             WHERE c.id_medico = :medico AND c.estado = 'Pendiente'
             ORDER BY c.fecha_cita ASC, c.hora_cita ASC"
        );
        $pendientes->execute(['medico' => $medico['id_medico']]);

        return $this->render($response, 'medico/historias.twig', [
            'medico' => $medico,
            'registros' => $registros,
            'pendientes' => $pendientes->fetchAll(),
            'buscar' => $buscar,
            'tipo' => $tipo,
        ]);
    }

    public function historiaShow(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $medico = $this->medico();

        if ($medico === null) {
            return $this->redirect($response, '/login');
        }

        $idCita = (int) ($args['id'] ?? 0);

        $statement = $this->db()->prepare(
            'SELECT c.*, p.nombres, p.apellidos, p.num_historia, p.fecha_nacimiento,
                    p.telefono, p.direccion, p.estado_civil, p.ocupacion,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad
             FROM citas c
             INNER JOIN pacientes p ON p.id_paciente = c.id_paciente
             WHERE c.id_cita = :id AND c.id_medico = :medico'
        );
        $statement->execute(['id' => $idCita, 'medico' => $medico['id_medico']]);
        $cita = $statement->fetch();

        if ($cita === false) {
            Flash::error('No se encontro la cita indicada.');
            return $this->redirect($response, '/medico/historias');
        }

        $antecedentes = $this->db()->prepare('SELECT * FROM historia WHERE id_paciente = :id');
        $antecedentes->execute(['id' => (int) $cita['id_paciente']]);

        $previas = $this->db()->prepare(
            'SELECT d.*, m.nombres AS medico_nombres, m.apellidos AS medico_apellidos
             FROM detalle_historia d
             INNER JOIN medicos m ON m.id_medico = d.id_medico
             WHERE d.id_paciente = :paciente AND d.id_cita <> :cita
             ORDER BY d.fecha_consulta DESC'
        );
        $previas->execute(['paciente' => (int) $cita['id_paciente'], 'cita' => $idCita]);

        $consulta = $this->db()->prepare('SELECT * FROM detalle_historia WHERE id_cita = :id');
        $consulta->execute(['id' => $idCita]);

        return $this->render($response, 'medico/ficha.twig', [
            'medico' => $medico,
            'cita' => $cita,
            'antecedentes' => $antecedentes->fetch() ?: null,
            'previas' => $previas->fetchAll(),
            'consulta' => $consulta->fetch() ?: null,
        ]);
    }

    public function historiaStore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $medico = $this->medico();

        if ($medico === null) {
            return $this->redirect($response, '/login');
        }

        $idCita = (int) ($args['id'] ?? 0);
        $data = $this->input($request);

        $validator = Validator::make($data, [
            'tipo_consulta' => 'required|in:Primera Vez / Apertura,Control / Evolucion',
            'diagnostico' => 'required|min:5',
        ], [
            'tipo_consulta' => 'tipo de consulta',
            'diagnostico' => 'diagnostico',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($response, $validator->errors(), $data, '/medico/historias/' . $idCita);
        }

        $cita = $this->db()->prepare('SELECT id_paciente FROM citas WHERE id_cita = :id AND id_medico = :medico');
        $cita->execute(['id' => $idCita, 'medico' => $medico['id_medico']]);
        $row = $cita->fetch();

        if ($row === false) {
            Flash::error('No se encontro la cita indicada.');
            return $this->redirect($response, '/medico/historias');
        }

        $existing = $this->db()->prepare('SELECT id_consulta FROM detalle_historia WHERE id_cita = :id');
        $existing->execute(['id' => $idCita]);
        $idConsulta = $existing->fetchColumn();

        $payload = [
            'tipo_consulta' => (string) $data['tipo_consulta'],
            'enfermedad_actual' => trim((string) ($data['enfermedad_actual'] ?? '')),
            'examen_fisico' => trim((string) ($data['examen_fisico'] ?? '')),
            'diagnostico' => trim((string) $data['diagnostico']),
            'tratamiento_plan' => trim((string) ($data['tratamiento_plan'] ?? '')),
            'notas_evolucion' => trim((string) ($data['notas_evolucion'] ?? '')),
        ];

        if ($idConsulta !== false) {
            $update = $this->db()->prepare(
                'UPDATE detalle_historia
                 SET tipo_consulta = :tipo_consulta, enfermedad_actual = :enfermedad_actual,
                     examen_fisico = :examen_fisico, diagnostico = :diagnostico,
                     tratamiento_plan = :tratamiento_plan, notas_evolucion = :notas_evolucion
                 WHERE id_consulta = :id'
            );
            $update->execute($payload + ['id' => (int) $idConsulta]);
        } else {
            $insert = $this->db()->prepare(
                'INSERT INTO detalle_historia
                    (id_cita, id_paciente, id_medico, tipo_consulta, enfermedad_actual,
                     examen_fisico, diagnostico, tratamiento_plan, notas_evolucion)
                 VALUES
                    (:id_cita, :id_paciente, :id_medico, :tipo_consulta, :enfermedad_actual,
                     :examen_fisico, :diagnostico, :tratamiento_plan, :notas_evolucion)'
            );
            $insert->execute($payload + [
                'id_cita' => $idCita,
                'id_paciente' => (int) $row['id_paciente'],
                'id_medico' => (int) $medico['id_medico'],
            ]);
        }

        $this->db()->prepare("UPDATE citas SET estado = 'Atendida' WHERE id_cita = :id")
            ->execute(['id' => $idCita]);

        Flash::success('Historia clinica guardada correctamente.');

        return $this->redirect($response, '/medico/historias');
    }

    public function marcarAtendida(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $medico = $this->medico();

        if ($medico === null) {
            return $this->redirect($response, '/login');
        }

        $update = $this->db()->prepare(
            "UPDATE citas SET estado = 'Atendida'
             WHERE id_cita = :id AND id_medico = :medico AND estado = 'Pendiente'"
        );
        $update->execute(['id' => (int) ($args['id'] ?? 0), 'medico' => $medico['id_medico']]);

        if ($update->rowCount() > 0) {
            Flash::success('Cita marcada como atendida.');
        } else {
            Flash::error('No se pudo actualizar la cita.');
        }

        return $this->redirect($response, '/medico');
    }

    public function horario(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $medico = $this->medico();

        if ($medico === null) {
            return $this->redirect($response, '/login');
        }

        $statement = $this->db()->prepare(
            'SELECT * FROM horarios_medico WHERE id_medico = :medico ORDER BY FIELD(dia_semana,
                "Lunes","Martes","Miercoles","Jueves","Viernes","Sabado","Domingo"), hora_inicio'
        );
        $statement->execute(['medico' => $medico['id_medico']]);

        $conteo = $this->db()->prepare(
            "SELECT fecha_cita, COUNT(*) AS total
             FROM citas
             WHERE id_medico = :medico AND estado <> 'Cancelada' AND fecha_cita >= CURDATE()
             GROUP BY fecha_cita
             ORDER BY fecha_cita
             LIMIT 10"
        );
        $conteo->execute(['medico' => $medico['id_medico']]);

        return $this->render($response, 'medico/horario.twig', [
            'medico' => $medico,
            'horarios' => $statement->fetchAll(),
            'proximas' => $conteo->fetchAll(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function medico(): ?array
    {
        $user = auth();
        $idMedico = $user['id_medico'] ?? null;

        if ($idMedico === null) {
            return null;
        }

        $statement = $this->db()->prepare(
            'SELECT m.*, e.nombre_especialidad
             FROM medicos m
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE m.id_medico = :id'
        );
        $statement->execute(['id' => (int) $idMedico]);
        $medico = $statement->fetch();

        return $medico === false ? null : $medico;
    }
}
