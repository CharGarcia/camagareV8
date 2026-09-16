<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Marcaciones de asistencia (registro crudo: quién + punto + hora + método).
 */
class MarcacionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('asistencia_marcaciones');
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['fecha_hora', 'tipo', 'metodo', 'estado', 'empleado', 'punto', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'fecha_hora';
        $ordenDir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'm', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $where .= ' AND e.eliminado = false';

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre: las columnas del listado + lo que identifica la marcación.
            // Decisión del usuario: las columnas Tipo y Estado (y el método, que es una
            // clasificación) NO entran en el texto libre; se filtran desde el modal.
            $condicion = FiltrosBusqueda::condicionTexto(
                [
                    'e.nombres_apellidos',                                   // Empleado
                    'e.identificacion',
                    'p.nombre',                                              // Punto
                    "TO_CHAR(m.fecha_hora, 'DD-MM-YYYY HH24:MI:SS')",        // Fecha/Hora (como se muestra)
                    'm.fecha_hora::text',
                    "CASE WHEN m.distancia_m IS NOT NULL THEN CONCAT(m.distancia_m, ' m') END", // Distancia
                    'm.observacion',
                    'm.dispositivo_id',
                    'u.nombre',                                              // Usuario que la registró (manual)
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
                'punto'          => 'p.nombre',
                'identificacion' => 'e.identificacion',
                'observacion'    => 'm.observacion',
                'dispositivo'    => 'm.dispositivo_id',
            ],
            'exacto'   => [
                'tipo'     => 'm.tipo',
                'metodo'   => 'm.metodo',
                'estado'   => 'm.estado',
                'id_punto' => 'm.id_punto',
                'usuario'  => 'm.created_by',
                // fuera_radio:si / no — mismo criterio que el aviso de la columna Distancia
                'fuera_radio' => "CASE WHEN m.distancia_m IS NOT NULL AND COALESCE(p.radio_m, 0) > 0 AND m.distancia_m > p.radio_m THEN 'si' ELSE 'no' END",
                // con_gps:si / no — el dispositivo envió su ubicación al marcar
                'con_gps'     => "CASE WHEN m.latitud IS NOT NULL AND m.longitud IS NOT NULL THEN 'si' ELSE 'no' END",
            ],
            'fecha'    => ['fecha' => 'm.fecha_hora'],
            'numerico' => ['distancia' => 'm.distancia_m'],
        ]);

        $orderExpr = match ($ordenCol) {
            'empleado' => 'e.nombres_apellidos',
            'punto'    => 'p.nombre',
            default    => "m.{$ordenCol}",
        };

        $from = "FROM {$this->table} m
                 JOIN empleados e ON e.id = m.id_empleado
                 LEFT JOIN asistencia_puntos p ON p.id = m.id_punto
                 LEFT JOIN usuarios u ON u.id = m.created_by
                 {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT m.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion,
                       p.nombre AS punto_nombre, p.radio_m AS punto_radio_m,
                       p.latitud AS punto_latitud, p.longitud AS punto_longitud
                {$from}
                ORDER BY {$orderExpr} {$ordenDir}, m.id DESC";
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Valores realmente usados en las marcaciones de la empresa, para los selects del
     * modal de filtros: puntos de servicio y usuarios que registraron marcaciones manuales.
     *
     * @return array{puntos: array, usuarios: array}
     */
    public function getOpcionesFiltro(int $idEmpresa): array
    {
        $consultas = [
            'puntos'   => "SELECT DISTINCT p.id, p.nombre FROM {$this->table} m JOIN asistencia_puntos p ON p.id = m.id_punto
                           WHERE m.id_empresa = :id_empresa AND m.eliminado = false ORDER BY p.nombre",
            'usuarios' => "SELECT DISTINCT u.id, u.nombre FROM {$this->table} m JOIN usuarios u ON u.id = m.created_by
                           WHERE m.id_empresa = :id_empresa AND m.eliminado = false ORDER BY u.nombre",
        ];
        $out = [];
        foreach ($consultas as $clave => $sql) {
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            $out[$clave] = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        return $out;
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_empleado, id_punto, fecha_hora, tipo, metodo,
                    latitud, longitud, distancia_m, selfie_path, confianza, dispositivo_id,
                    estado, observacion, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :id_empleado, :id_punto, :fecha_hora, :tipo, :metodo,
                    :latitud, :longitud, :distancia_m, :selfie_path, :confianza, :dispositivo_id,
                    :estado, :observacion, :created_by, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $d['id_empresa'],
            ':id_empleado'   => $d['id_empleado'],
            ':id_punto'      => $d['id_punto'] ?? null,
            ':fecha_hora'    => $d['fecha_hora'] ?? date('Y-m-d H:i:s'),
            ':tipo'          => $d['tipo'],
            ':metodo'        => $d['metodo'] ?? 'qr_punto',
            ':latitud'       => $d['latitud'] ?? null,
            ':longitud'      => $d['longitud'] ?? null,
            ':distancia_m'   => isset($d['distancia_m']) ? (int) $d['distancia_m'] : null,
            ':selfie_path'   => $d['selfie_path'] ?? null,
            ':confianza'     => $d['confianza'] ?? null,
            ':dispositivo_id' => $d['dispositivo_id'] ?? null,
            ':estado'        => $d['estado'] ?? 'valida',
            ':observacion'   => $d['observacion'] ?? null,
            ':created_by'    => $d['created_by'] ?? null,
        ]);
        return $this->lastInsertId();
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT m.*, e.nombres_apellidos AS empleado_nombre, p.nombre AS punto_nombre
                FROM {$this->table} m
                JOIN empleados e ON e.id = m.id_empleado
                LEFT JOIN asistencia_puntos p ON p.id = m.id_punto
                WHERE m.id = :id AND m.id_empresa = :id_empresa AND m.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Última marcación válida del empleado en un día (para sugerir entrada/salida). */
    public function getUltimaDelDia(int $idEmpleado, int $idEmpresa, string $fecha): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empleado = :e AND id_empresa = :emp AND eliminado = false
                  AND estado <> 'anulada' AND fecha_hora::date = :f
                ORDER BY fecha_hora DESC LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa, ':f' => $fecha]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** ¿Hay una marcación del mismo empleado en los últimos N minutos? (anti-doble marca). */
    public function existeMarcacionReciente(int $idEmpleado, int $idEmpresa, int $ventanaMinutos): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE id_empleado = :e AND id_empresa = :emp AND eliminado = false
                  AND estado <> 'anulada'
                  AND fecha_hora >= (CURRENT_TIMESTAMP - (:min || ' minutes')::interval)
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa, ':min' => (string) $ventanaMinutos]);
        return (bool) $st->fetchColumn();
    }

    /** Marcaciones válidas de un empleado en un día, en orden cronológico (motor de jornadas). */
    public function getMarcacionesDia(int $idEmpleado, int $idEmpresa, string $fecha): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empleado = :e AND id_empresa = :emp AND eliminado = false
                  AND estado <> 'anulada' AND fecha_hora::date = :f
                ORDER BY fecha_hora ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa, ':f' => $fecha]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
