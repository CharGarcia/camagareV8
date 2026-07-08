<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

class VacacionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('vacaciones');
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['fecha_desde', 'fecha_hasta', 'dias_gozados', 'valor', 'estado', 'empleado', 'id', 'periodo_anio'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'fecha_desde';
        $ordenDir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'v', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        $where .= " AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (e.nombres_apellidos ILIKE :b OR e.identificacion ILIKE :b OR v.observacion ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['empleado' => 'e.nombres_apellidos', 'observacion' => 'v.observacion'],
            'exacto'   => ['estado' => 'v.estado', 'mes' => 'v.periodo_mes', 'anio' => 'v.periodo_anio'],
            'fecha'    => ['desde' => 'v.fecha_desde', 'hasta' => 'v.fecha_hasta'],
            'numerico' => ['dias' => 'v.dias_gozados', 'valor' => 'v.valor'],
        ]);

        $orderExpr = $ordenCol === 'empleado' ? 'e.nombres_apellidos' : "v.{$ordenCol}";
        $from = "FROM {$this->table} v JOIN empleados e ON e.id = v.id_empleado {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT v.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion
                {$from} ORDER BY {$orderExpr} {$ordenDir}";
        if ($perPage > 0) $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_empleado, fecha_desde, fecha_hasta, dias_gozados, dias_derecho,
                    valor, periodo_mes, periodo_anio, afecta_rol, observacion, estado, tipo_ambiente,
                    created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :id_empleado, :fecha_desde, :fecha_hasta, :dias_gozados, :dias_derecho,
                    :valor, :periodo_mes, :periodo_anio, :afecta_rol, :observacion, :estado,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute($this->bind($d));
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $d): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_empleado = :id_empleado, fecha_desde = :fecha_desde, fecha_hasta = :fecha_hasta,
                    dias_gozados = :dias_gozados, dias_derecho = :dias_derecho, valor = :valor,
                    periodo_mes = :periodo_mes, periodo_anio = :periodo_anio, afecta_rol = :afecta_rol,
                    observacion = :observacion, estado = :estado, updated_by = :id_u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $p = $this->bind($d);
        $p[':id'] = $id;
        unset($p[':id_empresa']);
        $p[':id_empresa'] = $idEmpresa;
        return $st->execute($p);
    }

    private function bind(array $d): array
    {
        return [
            ':id_empresa'   => $d['id_empresa'],
            ':id_empleado'  => (int) $d['id_empleado'],
            ':fecha_desde'  => $d['fecha_desde'],
            ':fecha_hasta'  => $d['fecha_hasta'],
            ':dias_gozados' => (float) $d['dias_gozados'],
            ':dias_derecho' => (float) ($d['dias_derecho'] ?? 15),
            ':valor'        => (float) ($d['valor'] ?? 0),
            ':periodo_mes'  => (int) $d['periodo_mes'],
            ':periodo_anio' => (int) $d['periodo_anio'],
            ':afecta_rol'   => !empty($d['afecta_rol']) ? 'true' : 'false',
            ':observacion'  => $d['observacion'] ?? null,
            ':estado'       => $d['estado'] ?? 'registrado',
            ':id_u'         => $d['id_usuario'],
        ];
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                  WHERE id = :id AND id_empresa = :emp");
        return $st->execute([':u' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
    }

    public function setEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET estado = :e, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                                  WHERE id = :id AND id_empresa = :emp AND eliminado = false");
        return $st->execute([':e' => $estado, ':u' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT v.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion
                                  FROM {$this->table} v JOIN empleados e ON e.id = v.id_empleado
                                  WHERE v.id = :id AND v.id_empresa = :emp AND v.eliminado = false");
        $st->execute([':id' => $id, ':emp' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─── Lectores para el motor ──────────────────────────────────────────────
    public function getEmpleado(int $idEmpleado, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT id, nombres_apellidos, identificacion, sueldo_base
                                  FROM empleados WHERE id = :id AND id_empresa = :emp AND eliminado = false");
        $st->execute([':id' => $idEmpleado, ':emp' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Fecha de ingreso más reciente del empleado (para antigüedad). */
    public function getFechaIngreso(int $idEmpleado, int $idEmpresa): ?string
    {
        $st = $this->db->prepare("SELECT fecha_ingreso FROM empleado_periodos
                                  WHERE id_empleado = :e AND id_empresa = :emp AND eliminado = false
                                  ORDER BY fecha_ingreso DESC LIMIT 1");
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa]);
        $f = $st->fetchColumn();
        return $f !== false ? (string) $f : null;
    }

    /** Total de días gozados (no anulados) del empleado, excluyendo un registro. */
    public function getDiasGozadosTotal(int $idEmpleado, int $idEmpresa, ?int $excludeId = null): float
    {
        $sql = "SELECT COALESCE(SUM(dias_gozados), 0) FROM {$this->table}
                WHERE id_empleado = :e AND id_empresa = :emp AND eliminado = false AND estado != 'anulado'
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)";
        $p = [':e' => $idEmpleado, ':emp' => $idEmpresa];
        if ($excludeId !== null) { $sql .= " AND id != :id"; $p[':id'] = $excludeId; }
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return (float) $st->fetchColumn();
    }

    /** Valor de vacaciones a incluir en el rol mensual del empleado. */
    public function getValorParaRol(int $idEmpresa, int $idEmpleado, int $anio, int $mes): float
    {
        $st = $this->db->prepare("SELECT COALESCE(SUM(valor), 0) FROM {$this->table}
                                  WHERE id_empresa = :emp AND id_empleado = :e
                                    AND periodo_anio = :a AND periodo_mes = :m
                                    AND afecta_rol = true AND estado != 'anulado' AND eliminado = false
                                    AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)");
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado, ':a' => $anio, ':m' => $mes]);
        return (float) $st->fetchColumn();
    }

    public function buscarEmpleados(int $idEmpresa, string $q): array
    {
        $st = $this->db->prepare("SELECT id, nombres_apellidos, identificacion FROM empleados
                                  WHERE id_empresa = :emp AND eliminado = false AND estado = 'activo'
                                    AND (nombres_apellidos ILIKE :q OR identificacion ILIKE :q)
                                  ORDER BY nombres_apellidos LIMIT 15");
        $st->execute([':emp' => $idEmpresa, ':q' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
