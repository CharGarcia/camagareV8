<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Jornadas diarias consolidadas (resultado del motor de jornadas).
 */
class AsistenciaJornadaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('asistencia_jornadas');
    }

    public function getByDia(int $idEmpleado, int $idEmpresa, string $fecha): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empleado = :e AND id_empresa = :emp AND fecha = :f AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa, ':f' => $fecha]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Jornadas diarias de un empleado en un rango (para el detalle de asistencia del rol). */
    public function getDiasEmpleado(int $idEmpresa, int $idEmpleado, string $desde, string $hasta): array
    {
        $sql = "SELECT fecha, primera_entrada, ultima_salida, horas_trabajadas, atraso_min, extra_min, estado
                FROM {$this->table}
                WHERE id_empresa = :emp AND id_empleado = :e AND eliminado = false
                  AND fecha BETWEEN :d AND :h
                ORDER BY fecha";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado, ':d' => $desde, ':h' => $hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_empleado, id_punto, id_horario, fecha,
                    primera_entrada, ultima_salida, horas_trabajadas, atraso_min, extra_min,
                    estado, observacion, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :id_empleado, :id_punto, :id_horario, :fecha,
                    :primera_entrada, :ultima_salida, :horas_trabajadas, :atraso_min, :extra_min,
                    :estado, :observacion, :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute($this->bind($d));
        return $this->lastInsertId();
    }

    public function update(int $id, array $d): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_punto = :id_punto,
                    id_horario = :id_horario,
                    primera_entrada = :primera_entrada,
                    ultima_salida = :ultima_salida,
                    horas_trabajadas = :horas_trabajadas,
                    atraso_min = :atraso_min,
                    extra_min = :extra_min,
                    estado = :estado,
                    observacion = :observacion,
                    updated_by = :id_u,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $params = $this->bind($d);
        unset($params[':id_empleado'], $params[':fecha']);
        $params[':id'] = $id;
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    private function bind(array $d): array
    {
        return [
            ':id_empresa'       => $d['id_empresa'],
            ':id_empleado'      => $d['id_empleado'],
            ':id_punto'         => $d['id_punto'] ?? null,
            ':id_horario'       => $d['id_horario'] ?? null,
            ':fecha'            => $d['fecha'],
            ':primera_entrada'  => $d['primera_entrada'] ?? null,
            ':ultima_salida'    => $d['ultima_salida'] ?? null,
            ':horas_trabajadas' => $d['horas_trabajadas'] ?? 0,
            ':atraso_min'       => (int) ($d['atraso_min'] ?? 0),
            ':extra_min'        => (int) ($d['extra_min'] ?? 0),
            ':estado'           => $d['estado'] ?? 'incompleta',
            ':observacion'      => $d['observacion'] ?? null,
            ':id_u'             => $d['id_usuario'] ?? null,
        ];
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['fecha', 'horas_trabajadas', 'atraso_min', 'extra_min', 'estado', 'empleado', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'fecha';
        $ordenDir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'j', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $where .= ' AND e.eliminado = false';

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre: las columnas del listado + lo que identifica la jornada.
            // Decisión del usuario: la columna Estado NO entra en el texto libre; se
            // filtra desde el modal.
            $condicion = FiltrosBusqueda::condicionTexto(
                [
                    'e.nombres_apellidos',                                  // Empleado
                    'e.identificacion',
                    "TO_CHAR(j.fecha, 'DD-MM-YYYY')",                       // Fecha (como se muestra)
                    'j.fecha::text',
                    "TO_CHAR(j.primera_entrada, 'HH24:MI')",                // Entrada
                    "TO_CHAR(j.ultima_salida, 'HH24:MI')",                  // Salida
                    'j.horas_trabajadas::text',                             // Horas
                    "CASE WHEN j.atraso_min > 0 THEN CONCAT(j.atraso_min, ' min') END", // Atraso
                    "CASE WHEN j.extra_min > 0 THEN CONCAT(j.extra_min, ' min') END",   // Extra
                    'j.observacion',
                    'p.nombre',                                             // Punto de servicio
                    'h.nombre',                                             // Horario/turno aplicado
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        // Claves del modal de filtros (FiltrosModal en la vista). Las viejas se conservan.
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => [
                'empleado'       => 'e.nombres_apellidos',
                'identificacion' => 'e.identificacion',
                'observacion'    => 'j.observacion',
                'entrada'        => "TO_CHAR(j.primera_entrada, 'HH24:MI')",
                'salida'         => "TO_CHAR(j.ultima_salida, 'HH24:MI')",
            ],
            'exacto'   => [
                'estado'     => 'j.estado',
                'id_punto'   => 'j.id_punto',
                'id_horario' => 'j.id_horario',
                'usuario'    => 'j.created_by',
                // novedad:si / novedad:no (ya se generó la novedad para el rol)
                'novedad'    => "CASE WHEN j.id_novedad IS NULL THEN 'no' ELSE 'si' END",
                // con_salida:si / con_salida:no (tiene registrada la salida del día)
                'con_salida' => "CASE WHEN j.ultima_salida IS NULL THEN 'no' ELSE 'si' END",
            ],
            'fecha'    => ['fecha' => 'j.fecha'],
            'numerico' => ['atraso' => 'j.atraso_min', 'extra' => 'j.extra_min', 'horas' => 'j.horas_trabajadas'],
        ]);

        $orderExpr = $ordenCol === 'empleado' ? 'e.nombres_apellidos' : "j.{$ordenCol}";

        $from = "FROM {$this->table} j
                 JOIN empleados e ON e.id = j.id_empleado
                 LEFT JOIN asistencia_puntos p ON p.id = j.id_punto
                 LEFT JOIN asistencia_horarios h ON h.id = j.id_horario
                 {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT j.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion,
                       p.nombre AS punto_nombre
                {$from}
                ORDER BY {$orderExpr} {$ordenDir}, e.nombres_apellidos ASC, j.id DESC";
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Valores realmente usados en las jornadas de la empresa, para los selects del
     * modal de filtros: puntos de servicio, horarios y usuarios que las calcularon.
     *
     * @return array{puntos: array, horarios: array, usuarios: array}
     */
    public function getOpcionesFiltro(int $idEmpresa): array
    {
        $consultas = [
            'puntos'   => "SELECT DISTINCT p.id, p.nombre FROM {$this->table} j JOIN asistencia_puntos p ON p.id = j.id_punto
                           WHERE j.id_empresa = :id_empresa AND j.eliminado = false ORDER BY p.nombre",
            'horarios' => "SELECT DISTINCT h.id, h.nombre FROM {$this->table} j JOIN asistencia_horarios h ON h.id = j.id_horario
                           WHERE j.id_empresa = :id_empresa AND j.eliminado = false ORDER BY h.nombre",
            'usuarios' => "SELECT DISTINCT u.id, u.nombre FROM {$this->table} j JOIN usuarios u ON u.id = j.created_by
                           WHERE j.id_empresa = :id_empresa AND j.eliminado = false ORDER BY u.nombre",
        ];
        $out = [];
        foreach ($consultas as $clave => $sql) {
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            $out[$clave] = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        return $out;
    }

    /** Cuenta las jornadas incompletas (requieren revisión) en un rango. */
    public function contarIncompletas(int $idEmpresa, string $desde, string $hasta): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE id_empresa = :e AND eliminado = false
               AND estado = 'incompleta' AND fecha BETWEEN :d AND :h"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta]);
        return (int) $st->fetchColumn();
    }

    /** Pares (id_empleado, fecha) de las jornadas incompletas de un rango, para recalcularlas. */
    public function getIncompletas(int $idEmpresa, string $desde, string $hasta): array
    {
        $st = $this->db->prepare(
            "SELECT id_empleado, fecha FROM {$this->table}
             WHERE id_empresa = :e AND eliminado = false
               AND estado = 'incompleta' AND fecha BETWEEN :d AND :h
             ORDER BY fecha"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumen agregado por empleado en un rango de fechas: total de faltas,
     * minutos de atraso y minutos de extra. Base para generar Novedades (paso 4).
     */
    public function getResumenPeriodo(int $idEmpresa, string $desde, string $hasta, ?int $idEmpleado = null): array
    {
        $params = [':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta];
        $filtroEmp = '';
        if ($idEmpleado !== null) {
            $filtroEmp = ' AND j.id_empleado = :emple';
            $params[':emple'] = $idEmpleado;
        }
        $sql = "SELECT j.id_empleado,
                       e.nombres_apellidos AS empleado_nombre,
                       e.sueldo_base,
                       e.atraso_modo,
                       COUNT(*) FILTER (WHERE j.estado = 'falta')          AS dias_falta,
                       COALESCE(SUM(j.atraso_min), 0)                      AS atraso_min,
                       COALESCE(SUM(j.extra_min), 0)                       AS extra_min
                FROM {$this->table} j
                JOIN empleados e ON e.id = j.id_empleado AND e.eliminado = false
                WHERE j.id_empresa = :e AND j.eliminado = false
                  AND j.fecha BETWEEN :d AND :h
                  {$filtroEmp}
                GROUP BY j.id_empleado, e.nombres_apellidos, e.sueldo_base, e.atraso_modo
                HAVING COUNT(*) FILTER (WHERE j.estado = 'falta') > 0
                    OR COALESCE(SUM(j.atraso_min), 0) > 0
                    OR COALESCE(SUM(j.extra_min), 0) > 0
                ORDER BY e.nombres_apellidos";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Empleados a recalcular en un rango: los que tienen marcaciones y/o
     * una asignación de horario vigente que cruce el rango.
     */
    public function getEmpleadosParaRecalculo(int $idEmpresa, string $desde, string $hasta): array
    {
        $sql = "SELECT DISTINCT id_empleado FROM (
                    SELECT id_empleado FROM asistencia_marcaciones
                     WHERE id_empresa = :e1 AND eliminado = false
                       AND fecha_hora::date BETWEEN :d1 AND :h1
                    UNION
                    SELECT id_empleado FROM asistencia_empleado_horario
                     WHERE id_empresa = :e2 AND eliminado = false
                       AND vigente_desde <= :h2
                       AND (vigente_hasta IS NULL OR vigente_hasta >= :d2)
                ) t";
        $st = $this->db->prepare($sql);
        $st->execute([':e1' => $idEmpresa, ':d1' => $desde, ':h1' => $hasta, ':e2' => $idEmpresa, ':d2' => $desde, ':h2' => $hasta]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
