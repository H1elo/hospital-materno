<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Flash;
use App\Support\Validator;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PacienteController extends BaseController
{
    private const WEEKDAYS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    public function agendar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $paciente = $this->paciente();

        if ($paciente === null) {
            Flash::error('Tu usuario no tiene ficha de paciente asociada.');
            return $this->redirect($response, '/login');
        }

        $query = $request->getQueryParams();
        $medicos = $this->medicos();

        $medicoId = (int) ($query['medico'] ?? ($medicos[0]['id_medico'] ?? 0));

        $hoy = new DateTimeImmutable('today');
        $year = (int) ($query['anio'] ?? $hoy->format('Y'));
        $month = (int) ($query['mes'] ?? $hoy->format('n'));

        if ($month < 1 || $month > 12) {
            $month = (int) $hoy->format('n');
        }

        $calendar = $medicoId > 0 ? $this->buildCalendar($year, $month, $medicoId) : ['days' => [], 'slots' => []];

        return $this->render($response, 'paciente/agendar.twig', [
            'paciente' => $paciente,
            'medicos' => $medicos,
            'medico_seleccionado' => $medicoId,
            'mes' => $month,
            'anio' => $year,
            'mes_nombre' => $this->monthName($month),
            'dias' => $calendar['days'],
            'slots' => $calendar['slots'],
            'offset' => $calendar['offset'] ?? 0,
            'citas_proximas' => $this->citasProximas($paciente['id_paciente']),
        ]);
    }

    public function agendarStore(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $paciente = $this->paciente();

        if ($paciente === null) {
            return $this->redirect($response, '/login');
        }

        $data = $this->input($request);

        $validator = Validator::make($data, [
            'id_medico' => 'required|numeric',
            'fecha_cita' => 'required|date',
            'hora_cita' => 'required',
            'motivo_consulta' => 'required|min:5|max:255',
        ], [
            'id_medico' => 'medico',
            'fecha_cita' => 'fecha',
            'hora_cita' => 'hora',
            'motivo_consulta' => 'motivo de consulta',
        ]);

        if ($validator->fails()) {
            Flash::error((string) $validator->firstError());
            return $this->redirect($response, '/paciente');
        }

        $fecha = (string) $data['fecha_cita'];

        if (strtotime($fecha) < strtotime('today')) {
            Flash::error('No puedes agendar una cita en una fecha pasada.');
            return $this->redirect($response, '/paciente');
        }

        $duplicate = $this->db()->prepare(
            'SELECT COUNT(*) FROM citas
             WHERE id_medico = :id_medico AND fecha_cita = :fecha AND hora_cita = :hora
               AND estado <> :cancelada'
        );
        $duplicate->execute([
            'id_medico' => (int) $data['id_medico'],
            'fecha' => $fecha,
            'hora' => (string) $data['hora_cita'],
            'cancelada' => 'Cancelada',
        ]);

        if ((int) $duplicate->fetchColumn() > 0) {
            Flash::error('Ese horario ya fue reservado. Selecciona otro.');
            return $this->redirect($response, '/paciente');
        }

        $insert = $this->db()->prepare(
            'INSERT INTO citas (id_paciente, id_medico, fecha_cita, hora_cita, estado, motivo_consulta)
             VALUES (:id_paciente, :id_medico, :fecha, :hora, :estado, :motivo)'
        );
        $insert->execute([
            'id_paciente' => $paciente['id_paciente'],
            'id_medico' => (int) $data['id_medico'],
            'fecha' => $fecha,
            'hora' => (string) $data['hora_cita'],
            'estado' => 'Pendiente',
            'motivo' => trim((string) $data['motivo_consulta']),
        ]);

        Flash::success('Cita agendada correctamente para el ' . fecha($fecha) . ' a las ' . substr((string) $data['hora_cita'], 0, 5) . '.');

        return $this->redirect($response, '/paciente/citas');
    }

    public function cancelar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $paciente = $this->paciente();

        if ($paciente === null) {
            return $this->redirect($response, '/login');
        }

        $update = $this->db()->prepare(
            "UPDATE citas SET estado = 'Cancelada'
             WHERE id_cita = :id AND id_paciente = :paciente AND estado = 'Pendiente'"
        );
        $update->execute(['id' => (int) ($args['id'] ?? 0), 'paciente' => $paciente['id_paciente']]);

        if ($update->rowCount() > 0) {
            Flash::success('La cita fue cancelada.');
        } else {
            Flash::error('No se pudo cancelar la cita seleccionada.');
        }

        return $this->redirect($response, '/paciente/citas');
    }

    public function datos(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $paciente = $this->paciente();

        if ($paciente === null) {
            return $this->redirect($response, '/login');
        }

        return $this->render($response, 'paciente/datos.twig', [
            'paciente' => $paciente,
            'antecedentes' => $this->antecedentes($paciente['id_paciente']),
        ]);
    }

    public function datosUpdate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $paciente = $this->paciente();

        if ($paciente === null) {
            return $this->redirect($response, '/login');
        }

        $data = $this->input($request);

        $validator = Validator::make($data, [
            'nombres' => 'required|min:2|max:100',
            'apellidos' => 'required|min:2|max:100',
            'fecha_nacimiento' => 'required|date',
            'telefono' => 'max:20',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($response, $validator->errors(), $data, '/paciente/datos');
        }

        $update = $this->db()->prepare(
            'UPDATE pacientes
             SET nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento,
                 estado_civil = :estado_civil, ocupacion = :ocupacion, direccion = :direccion, telefono = :telefono
             WHERE id_paciente = :id'
        );
        $update->execute([
            'nombres' => trim((string) $data['nombres']),
            'apellidos' => trim((string) $data['apellidos']),
            'fecha_nacimiento' => (string) $data['fecha_nacimiento'],
            'estado_civil' => trim((string) ($data['estado_civil'] ?? '')),
            'ocupacion' => trim((string) ($data['ocupacion'] ?? '')),
            'direccion' => trim((string) ($data['direccion'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'id' => $paciente['id_paciente'],
        ]);

        Flash::success('Tus datos personales fueron actualizados.');

        return $this->redirect($response, '/paciente/datos');
    }

    public function contactoUpdate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $paciente = $this->paciente();

        if ($paciente === null) {
            return $this->redirect($response, '/login');
        }

        $data = $this->input($request);

        $update = $this->db()->prepare(
            'UPDATE pacientes
             SET contacto_emergencia_nombre = :nombre, contacto_emergencia_telefono = :telefono
             WHERE id_paciente = :id'
        );
        $update->execute([
            'nombre' => trim((string) ($data['contacto_nombre'] ?? '')),
            'telefono' => trim((string) ($data['contacto_telefono'] ?? '')),
            'id' => $paciente['id_paciente'],
        ]);

        Flash::success('Contacto de emergencia actualizado.');

        return $this->redirect($response, '/paciente/datos');
    }

    public function citas(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $paciente = $this->paciente();

        if ($paciente === null) {
            return $this->redirect($response, '/login');
        }

        $statement = $this->db()->prepare(
            "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado, c.motivo_consulta,
                    m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                    e.nombre_especialidad,
                    d.id_consulta, d.tipo_consulta, d.diagnostico, d.tratamiento_plan, d.notas_evolucion,
                    d.fecha_consulta
             FROM citas c
             INNER JOIN medicos m ON m.id_medico = c.id_medico
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             LEFT JOIN detalle_historia d ON d.id_cita = c.id_cita
             WHERE c.id_paciente = :paciente
             ORDER BY c.fecha_cita DESC, c.hora_cita DESC"
        );
        $statement->execute(['paciente' => $paciente['id_paciente']]);

        return $this->render($response, 'paciente/citas.twig', [
            'paciente' => $paciente,
            'citas' => $statement->fetchAll(),
        ]);
    }

    public function comprobante(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $paciente = $this->paciente();

        if ($paciente === null) {
            return $this->redirect($response, '/login');
        }

        $statement = $this->db()->prepare(
            'SELECT c.*, m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                    e.nombre_especialidad
             FROM citas c
             INNER JOIN medicos m ON m.id_medico = c.id_medico
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE c.id_cita = :id AND c.id_paciente = :paciente'
        );
        $statement->execute(['id' => (int) ($args['id'] ?? 0), 'paciente' => $paciente['id_paciente']]);
        $cita = $statement->fetch();

        if ($cita === false) {
            Flash::error('No se encontro la cita solicitada.');
            return $this->redirect($response, '/paciente/citas');
        }

        return $this->render($response, 'paciente/comprobante.twig', [
            'paciente' => $paciente,
            'cita' => $cita,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function paciente(): ?array
    {
        $user = auth();
        $idPaciente = $user['id_paciente'] ?? null;

        if ($idPaciente === null) {
            return null;
        }

        $statement = $this->db()->prepare('SELECT * FROM pacientes WHERE id_paciente = :id');
        $statement->execute(['id' => (int) $idPaciente]);
        $paciente = $statement->fetch();

        return $paciente === false ? null : $paciente;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function medicos(): array
    {
        return $this->db()
            ->query('SELECT m.id_medico, m.nombres, m.apellidos, m.num_colegiatura,
                            e.nombre_especialidad, e.id_especialidad
                     FROM medicos m
                     INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
                     ORDER BY m.apellidos, m.nombres')
            ->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function antecedentes(int $idPaciente): ?array
    {
        $statement = $this->db()->prepare('SELECT * FROM historia WHERE id_paciente = :id');
        $statement->execute(['id' => $idPaciente]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function citasProximas(int $idPaciente): array
    {
        $statement = $this->db()->prepare(
            "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado, c.motivo_consulta,
                    m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                    e.nombre_especialidad
             FROM citas c
             INNER JOIN medicos m ON m.id_medico = c.id_medico
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE c.id_paciente = :paciente AND c.estado = 'Pendiente' AND c.fecha_cita >= CURDATE()
             ORDER BY c.fecha_cita ASC, c.hora_cita ASC
             LIMIT 5"
        );
        $statement->execute(['paciente' => $idPaciente]);

        return $statement->fetchAll();
    }

    /**
     * Construye la rejilla del calendario con la disponibilidad por dia.
     *
     * @return array{days: array<int, array<string, mixed>>, slots: array<int, string>}
     */
    private function buildCalendar(int $year, int $month, int $medicoId): array
    {
        $db = $this->db();

        $scheduleStatement = $db->prepare(
            'SELECT dia_semana, hora_inicio, hora_fin, cupos_max
             FROM horarios_medico
             WHERE id_medico = :medico AND activo = 1'
        );
        $scheduleStatement->execute(['medico' => $medicoId]);

        $schedules = [];

        foreach ($scheduleStatement->fetchAll() as $row) {
            $schedules[(string) $row['dia_semana']] = $row;
        }

        $countStatement = $db->prepare(
            "SELECT fecha_cita, COUNT(*) AS total
             FROM citas
             WHERE id_medico = :medico AND estado <> 'Cancelada'
               AND YEAR(fecha_cita) = :anio AND MONTH(fecha_cita) = :mes
             GROUP BY fecha_cita"
        );
        $countStatement->execute(['medico' => $medicoId, 'anio' => $year, 'mes' => $month]);

        $counts = [];

        foreach ($countStatement->fetchAll() as $row) {
            $counts[(string) $row['fecha_cita']] = (int) $row['total'];
        }

        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int) $first->format('t');
        $offset = (int) $first->format('w'); // 0 = domingo

        $days = [];
        $today = new DateTimeImmutable('today');

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $first->setDate($year, $month, $day);
            $weekdayName = self::WEEKDAYS[(int) $date->format('N')];
            $schedule = $schedules[$weekdayName] ?? null;
            $capacity = $schedule !== null ? (int) $schedule['cupos_max'] : 0;
            $used = $counts[$date->format('Y-m-d')] ?? 0;
            $available = max(0, $capacity - $used);

            if ($capacity === 0) {
                $status = 'Cerrado';
            } elseif ($available === 0) {
                $status = 'Lleno';
            } elseif ($available <= 2) {
                $status = 'Pocos cupos';
            } else {
                $status = 'Disponible';
            }

            $days[] = [
                'dia' => $day,
                'fecha' => $date->format('Y-m-d'),
                'semana' => $weekdayName,
                'cupos' => $capacity,
                'usados' => $used,
                'disponibles' => $available,
                'estado' => $status,
                'pasado' => $date < $today,
                'es_hoy' => $date->format('Y-m-d') === $today->format('Y-m-d'),
            ];
        }

        // Horarios (slots) disponibles: se toman del primer dia con atencion
        $slots = [];

        if ($schedules !== []) {
            $reference = array_values($schedules)[0];
            $slots = $this->timeSlots((string) $reference['hora_inicio'], (string) $reference['hora_fin']);
        }

        return [
            'days' => $days,
            'slots' => $slots,
            'offset' => $offset,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function timeSlots(string $start, string $end, int $stepMinutes = 30): array
    {
        $slots = [];
        $cursor = strtotime($start);
        $limit = strtotime($end);

        while ($cursor !== false && $limit !== false && $cursor < $limit) {
            $slots[] = date('H:i', $cursor);
            $cursor += $stepMinutes * 60;
        }

        return $slots;
    }

    private function monthName(int $month): string
    {
        $names = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $names[$month] ?? '';
    }
}
