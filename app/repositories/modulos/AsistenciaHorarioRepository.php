<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Horarios/turnos de la empresa y su asignación a empleados (con punto de servicio).
 */
class AsistenciaHorarioRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('asistencia_horarios');
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['nombre', 'hora_entrada', 'hora_salida', 'horas_jornada', 'estado', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'nombre';
        $ordenDir  = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'h', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND h.nombre ILIKE :b";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => ['nombre' => 'h.nombre'],
            'exacto' => ['estado' => 'h.estado'],
        ]);

        $from = "FROM {$this->table} h {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT h.* {$from} ORDER BY h.{$ordenCol} {$ordenDir}";
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, nombre, hora_entrada, hora_salida, cruza_medianoche,
                    tolerancia_min, horas_jornada, dias_semana, estado,
                    created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :nombre, :hora_entrada, :hora_salida, :cruza_medianoche,
                    :tolerancia_min, :horas_jornada, :dias_semana, :estado,
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $d['id_empresa'],
            ':nombre'           => $d['nombre'],
            ':hora_entrada'     => $d['hora_entrada'],
            ':hora_salida'      => $d['hora_salida'],
            ':cruza_medianoche' => !empty($d['cruza_medianoche']) ? 'true' : 'false',
            ':tolerancia_min'   => (int) ($d['tolerancia_min'] ?? 5),
            ':horas_jornada'    => (float) ($d['horas_jornada'] ?? 8),
            ':dias_semana'      => $d['dias_semana'] ?? '1,2,3,4,5',
            ':estado'           => $d['estado'] ?? 'activo',
            ':id_u'             => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $d): bool
    {
        $sql = "UPDATE {$this->table} SET
                    nombre = :nombre,
                    hora_entrada = :hora_entrada,
                    hora_salida = :hora_salida,
                    cruza_medianoche = :cruza_medianoche,
                    tolerancia_min = :tolerancia_min,
                    horas_jornada = :horas_jornada,
                    dias_semana = :dias_semana,
                    estado = :estado,
                    updated_by = :id_u,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'           => $d['nombre'],
            ':hora_entrada'     => $d['hora_entrada'],
            ':hora_salida'      => $d['hora_salida'],
            ':cruza_medianoche' => !empty($d['cruza_medianoche']) ? 'true' : 'false',
            ':tolerancia_min'   => (int) ($d['tolerancia_min'] ?? 5),
            ':horas_jornada'    => (float) ($d['horas_jornada'] ?? 8),
            ':dias_semana'      => $d['dias_semana'] ?? '1,2,3,4,5',
            ':estado'           => $d['estado'] ?? 'activo',
            ':id_u'             => $d['id_usuario'],
            ':id'               => $id,
            ':id_empresa'       => $idEmpresa,
        ]);
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    // ------------------------------------------------------------------
    // Asignación de horario (y punto) a empleados: asistencia_empleado_horario
    // ------------------------------------------------------------------

    public function asignar(array $d): int
    {
        $sql = "INSERT INTO asistencia_empleado_horario (
                    id_empresa, id_empleado, id_horario, id_punto, vigente_desde, vigente_hasta,
                    created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :id_empleado, :id_horario, :id_punto, :vigente_desde, :vigente_hasta,
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $d['id_empresa'],
            ':id_empleado'   => $d['id_empleado'],
            ':id_horario'    => $d['id_horario'],
            ':id_punto'      => $d['id_punto'] ?? null,
            ':vigente_desde' => $d['vigente_desde'],
            ':vigente_hasta' => $d['vigente_hasta'] ?? null,
            ':id_u'          => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function eliminarAsignacion(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE asistencia_empleado_horario SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    /** Horarios activos (para selects). */
    public function getActivos(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id, nombre, hora_entrada, hora_salida FROM {$this->table}
                                  WHERE id_empresa = :e AND eliminado = false AND estado = 'activo' ORDER BY nombre");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Asignaciones vigentes/históricas de la empresa (con nombres). */
    public function getAsignaciones(int $idEmpresa): array
    {
        $sql = "SELECT eh.*, e.nombres_apellidos AS empleado_nombre, e.identificacion,
                       h.nombre AS horario_nombre, p.nombre AS punto_nombre
                FROM asistencia_empleado_horario eh
                JOIN empleados e ON e.id = eh.id_empleado
                JOIN asistencia_horarios h ON h.id = eh.id_horario
                LEFT JOIN asistencia_puntos p ON p.id = eh.id_punto
                WHERE eh.id_empresa = :e AND eh.eliminado = false
                ORDER BY e.nombres_apellidos ASC, eh.vigente_desde DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Horario vigente de un empleado en una fecha (con su punto asignado).
     * Base del cálculo de jornada y de la sugerencia de punto en la marcación.
     */
    public function getHorarioVigente(int $idEmpleado, int $idEmpresa, string $fecha): ?array
    {
        $sql = "SELECT h.*, eh.id AS id_asignacion, eh.id_punto
                FROM asistencia_empleado_horario eh
                JOIN asistencia_horarios h ON h.id = eh.id_horario AND h.eliminado = false
                WHERE eh.id_empleado = :e AND eh.id_empresa = :emp AND eh.eliminado = false
                  AND eh.vigente_desde <= :f
                  AND (eh.vigente_hasta IS NULL OR eh.vigente_hasta >= :f)
                ORDER BY eh.vigente_desde DESC LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa, ':f' => $fecha]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
