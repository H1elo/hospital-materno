<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Flash;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TecnicoController extends BaseController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filtro = $this->periodo($request);

        $stats = $this->db()->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN d.tipo_consulta LIKE 'Primera%' THEN 1 ELSE 0 END) AS primera_vez,
                SUM(CASE WHEN d.tipo_consulta LIKE 'Control%' THEN 1 ELSE 0 END) AS control
             FROM detalle_historia d
             WHERE {$filtro['sql']}"
        );
        $stats->execute($filtro['params']);

        $porEspecialidad = $this->db()->prepare(
            "SELECT e.nombre_especialidad, COUNT(*) AS total
             FROM detalle_historia d
             INNER JOIN medicos m ON m.id_medico = d.id_medico
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE {$filtro['sql']}
             GROUP BY e.nombre_especialidad
             ORDER BY total DESC"
        );
        $porEspecialidad->execute($filtro['params']);

        $ultimos = $this->db()->prepare(
            "SELECT d.id_consulta, d.tipo_consulta, d.diagnostico, d.fecha_consulta,
                    p.nombres, p.apellidos, p.num_historia,
                    m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                    e.nombre_especialidad
             FROM detalle_historia d
             INNER JOIN pacientes p ON p.id_paciente = d.id_paciente
             INNER JOIN medicos m ON m.id_medico = d.id_medico
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE {$filtro['sql']}
             ORDER BY d.fecha_consulta DESC
             LIMIT 8"
        );
        $ultimos->execute($filtro['params']);

        return $this->render($response, 'tecnico/index.twig', [
            'stats' => $stats->fetch() ?: [],
            'por_especialidad' => $porEspecialidad->fetchAll(),
            'ultimos' => $ultimos->fetchAll(),
            'filtro' => $filtro,
        ]);
    }

    public function registros(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filtro = $this->periodo($request);
        $query = $request->getQueryParams();
        $buscar = trim((string) ($query['buscar'] ?? ''));

        $sql = "SELECT d.id_consulta, d.tipo_consulta, d.diagnostico, d.fecha_consulta,
                       p.id_paciente, p.nombres, p.apellidos, p.num_historia, p.fecha_nacimiento,
                       m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                       e.nombre_especialidad
                FROM detalle_historia d
                INNER JOIN pacientes p ON p.id_paciente = d.id_paciente
                INNER JOIN medicos m ON m.id_medico = d.id_medico
                INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
                WHERE {$filtro['sql']}";
        $params = $filtro['params'];

        if ($buscar !== '') {
            $sql .= ' AND (p.nombres LIKE :buscar OR p.apellidos LIKE :buscar OR p.num_historia LIKE :buscar)';
            $params['buscar'] = '%' . $buscar . '%';
        }

        $sql .= ' ORDER BY d.fecha_consulta DESC';

        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $this->render($response, 'tecnico/registros.twig', [
            'registros' => $statement->fetchAll(),
            'filtro' => $filtro,
            'buscar' => $buscar,
        ]);
    }

    public function expediente(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $registro = $this->expedienteData((int) ($args['id'] ?? 0));

        if ($registro === null) {
            Flash::error('No se encontro el expediente solicitado.');
            return $this->redirect($response, '/tecnico/registros');
        }

        return $this->render($response, 'tecnico/expediente.twig', [
            'registro' => $registro,
            'adjuntos' => $this->adjuntos((int) $registro['id_consulta']),
        ]);
    }

    public function imprimir(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $registro = $this->expedienteData((int) ($args['id'] ?? 0));

        if ($registro === null) {
            Flash::error('No se encontro el expediente solicitado.');
            return $this->redirect($response, '/tecnico/registros');
        }

        return $this->render($response, 'tecnico/imprimir.twig', [
            'registro' => $registro,
            'adjuntos' => $this->adjuntos((int) $registro['id_consulta']),
        ]);
    }

    public function exportar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filtro = $this->periodo($request);

        $statement = $this->db()->prepare(
            "SELECT p.num_historia, CONCAT(u.nacionalidad, '-', u.cedula) AS cedula,
                    CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                    e.nombre_especialidad, CONCAT(m.nombres, ' ', m.apellidos) AS medico,
                    d.tipo_consulta, d.fecha_consulta
             FROM detalle_historia d
             INNER JOIN pacientes p ON p.id_paciente = d.id_paciente
             INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
             INNER JOIN medicos m ON m.id_medico = d.id_medico
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE {$filtro['sql']}
             ORDER BY d.fecha_consulta DESC"
        );
        $statement->execute($filtro['params']);

        $rows = $statement->fetchAll();

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return $response->withStatus(500);
        }

        fputcsv($handle, ['Expediente', 'Cedula', 'Nombre', 'Especialidad', 'Medico Tratante', 'Tipo', 'Fecha'], ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['num_historia'],
                $row['cedula'],
                $row['paciente'],
                $row['nombre_especialidad'],
                $row['medico'],
                $row['tipo_consulta'],
                fecha((string) $row['fecha_consulta'], 'd/m/Y H:i'),
            ], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $response->getBody()->write("\xEF\xBB\xBF" . $csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="registros-medicos-' . date('Y-m-d') . '.csv"');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function expedienteData(int $idConsulta): ?array
    {
        if ($idConsulta <= 0) {
            return null;
        }

        $statement = $this->db()->prepare(
            'SELECT d.*, c.fecha_cita, c.hora_cita, c.motivo_consulta,
                    p.nombres, p.apellidos, p.num_historia, p.fecha_nacimiento, p.telefono,
                    p.direccion, p.estado_civil, p.ocupacion,
                    u.nacionalidad, u.cedula,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                    m.nombres AS medico_nombres, m.apellidos AS medico_apellidos, m.num_colegiatura,
                    e.nombre_especialidad,
                    h.menarquia, h.sexarquia, h.ciclos_menstruales, h.gestas, h.partos, h.abortos,
                    h.cesareas, h.fecha_ultima_regla, h.fecha_ultima_citologia,
                    h.metodo_anticonceptivo, h.observaciones
             FROM detalle_historia d
             INNER JOIN citas c ON c.id_cita = d.id_cita
             INNER JOIN pacientes p ON p.id_paciente = d.id_paciente
             INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
             INNER JOIN medicos m ON m.id_medico = d.id_medico
             INNER JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             LEFT JOIN historia h ON h.id_paciente = p.id_paciente
             WHERE d.id_consulta = :id'
        );
        $statement->execute(['id' => $idConsulta]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function adjuntos(int $idConsulta): array
    {
        $statement = $this->db()->prepare('SELECT * FROM adjuntos_archivos WHERE id_consulta = :id');
        $statement->execute(['id' => $idConsulta]);

        return $statement->fetchAll();
    }

    /**
     * Resuelve el filtro de fechas solicitado.
     *
     * @return array{sql: string, params: array<string, mixed>, id: string, label: string, desde: string, hasta: string}
     */
    private function periodo(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $id = (string) ($query['periodo'] ?? 'mes');
        $hoy = new DateTimeImmutable('today');

        $desde = match ($id) {
            'hoy' => $hoy,
            'semana' => $hoy->modify('monday this week'),
            'personalizado' => $this->safeDate((string) ($query['desde'] ?? '')) ?? $hoy->modify('first day of this month'),
            default => $hoy->modify('first day of this month'),
        };

        $hasta = match ($id) {
            'hoy' => $hoy,
            'semana' => $hoy->modify('sunday this week'),
            'personalizado' => $this->safeDate((string) ($query['hasta'] ?? '')) ?? $hoy,
            default => $hoy->modify('last day of this month'),
        };

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $labels = [
            'hoy' => 'Hoy',
            'semana' => 'Esta semana',
            'mes' => 'Este mes',
            'personalizado' => 'Personalizado',
        ];

        return [
            'sql' => 'd.fecha_consulta BETWEEN :desde AND :hasta',
            'params' => [
                'desde' => $desde->format('Y-m-d 00:00:00'),
                'hasta' => $hasta->format('Y-m-d 23:59:59'),
            ],
            'id' => $id,
            'label' => $labels[$id] ?? 'Este mes',
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
        ];
    }

    private function safeDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
