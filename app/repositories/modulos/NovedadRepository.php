<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

class NovedadRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('novedades');
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['fecha', 'periodo_anio', 'periodo_mes', 'tipo_nombre', 'valor', 'estado', 'empleado', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'fecha';
        $ordenDir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'n', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $where .= ' AND e.eliminado = false';
        $where .= " AND n.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (e.nombres_apellidos ILIKE :b OR e.identificacion ILIKE :b OR n.tipo_nombre ILIKE :b OR n.observacion ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['tipo' => 'n.tipo_nombre', 'observacion' => 'n.observacion', 'empleado' => 'e.nombres_apellidos'],
            'exacto'   => ['estado' => 'n.estado', 'mes' => 'n.periodo_mes', 'anio' => 'n.periodo_anio', 'codigo' => 'n.tipo_codigo'],
            'fecha'    => ['fecha' => 'n.fecha'],
            'numerico' => ['valor' => 'n.valor'],
        ]);

        $orderExpr = match ($ordenCol) {
            'empleado' => 'e.nombres_apellidos',
            default    => "n.{$ordenCol}",
        };

        $from = "FROM {$this->table} n JOIN empleados e ON e.id = n.id_empleado {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT n.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion
                {$from}
                ORDER BY {$orderExpr} {$ordenDir}";
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Anexar estado de pago (derivado del rol): la novedad está pagada si aparece
        // como rubro en una línea de rol que fue pagada vía egreso.
        $pagadas = $this->idsNovedadesPagadas(array_column($rows, 'id'), $idEmpresa);
        foreach ($rows as &$r) {
            $r['pagada'] = isset($pagadas[(int) $r['id']]);
        }
        unset($r);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Devuelve un mapa [id_novedad => true] con las novedades ya PAGADAS: aquellas que
     * figuran como rubro (rol_detalle_rubro.id_novedad) en una línea de rol cuyo neto
     * fue cubierto por egresos (pagado > 0 y saldo ≤ 0.01). Resiliente: si el módulo de
     * roles/egresos no está desplegado, devuelve [] y el listado sigue funcionando.
     */
    private function idsNovedadesPagadas(array $ids, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (empty($ids)) {
            return [];
        }
        $in = implode(',', $ids);
        $pagadoSub = "(SELECT COALESCE(SUM(ed.monto_pagado), 0)
                        FROM egresos_detalle ed
                        JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                       WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                         AND ec.eliminado = false AND ed.eliminado = false
                         AND ed.id_referencia_documento = rd.id)";
        $sql = "SELECT DISTINCT rr.id_novedad
                FROM rol_detalle_rubro rr
                JOIN rol_detalle rd  ON rd.id = rr.id_detalle
                JOIN rol_cabecera rc ON rc.id = rd.id_rol
                WHERE rr.id_novedad IN ($in)
                  AND rd.id_empresa = :emp AND rc.eliminado = false
                  AND {$pagadoSub} > 0
                  AND (rd.neto - {$pagadoSub}) <= 0.01";
        $set = [];
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':emp' => $idEmpresa]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $set[(int) $id] = true;
            }
        } catch (\Throwable $e) {
            // Nómina/roles no desplegado en esta instalación: sin badge de pago.
        }
        return $set;
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_empleado, tipo_codigo, tipo_nombre, fecha,
                    periodo_mes, periodo_anio, valor, aplica_en, motivo_codigo, motivo_nombre,
                    observacion, estado, tipo_ambiente, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :id_empleado, :tipo_codigo, :tipo_nombre, :fecha,
                    :periodo_mes, :periodo_anio, :valor, :aplica_en, :motivo_codigo, :motivo_nombre,
                    :observacion, :estado, (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $d['id_empresa'],
            ':id_empleado'   => $d['id_empleado'],
            ':tipo_codigo'   => $d['tipo_codigo'],
            ':tipo_nombre'   => $d['tipo_nombre'],
            ':fecha'         => $d['fecha'],
            ':periodo_mes'   => (int) $d['periodo_mes'],
            ':periodo_anio'  => (int) $d['periodo_anio'],
            ':valor'         => (float) ($d['valor'] ?? 0),
            ':aplica_en'     => $d['aplica_en'] ?? 'rol',
            ':motivo_codigo' => $d['motivo_codigo'] ?? null,
            ':motivo_nombre' => $d['motivo_nombre'] ?? null,
            ':observacion'   => $d['observacion'] ?? null,
            ':estado'        => $d['estado'] ?? 'activo',
            ':id_u'          => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $d): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_empleado = :id_empleado,
                    tipo_codigo = :tipo_codigo,
                    tipo_nombre = :tipo_nombre,
                    fecha = :fecha,
                    periodo_mes = :periodo_mes,
                    periodo_anio = :periodo_anio,
                    valor = :valor,
                    aplica_en = :aplica_en,
                    motivo_codigo = :motivo_codigo,
                    motivo_nombre = :motivo_nombre,
                    observacion = :observacion,
                    estado = :estado,
                    updated_by = :id_u,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_empleado'   => $d['id_empleado'],
            ':tipo_codigo'   => $d['tipo_codigo'],
            ':tipo_nombre'   => $d['tipo_nombre'],
            ':fecha'         => $d['fecha'],
            ':periodo_mes'   => (int) $d['periodo_mes'],
            ':periodo_anio'  => (int) $d['periodo_anio'],
            ':valor'         => (float) ($d['valor'] ?? 0),
            ':aplica_en'     => $d['aplica_en'] ?? 'rol',
            ':motivo_codigo' => $d['motivo_codigo'] ?? null,
            ':motivo_nombre' => $d['motivo_nombre'] ?? null,
            ':observacion'   => $d['observacion'] ?? null,
            ':estado'        => $d['estado'] ?? 'activo',
            ':id_u'          => $d['id_usuario'],
            ':id'            => $id,
            ':id_empresa'    => $idEmpresa,
        ]);
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    /** Detalle con datos del empleado. */
    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT n.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion
                FROM {$this->table} n
                JOIN empleados e ON e.id = n.id_empleado
                WHERE n.id = :id AND n.id_empresa = :id_empresa AND n.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca una novedad generada previamente por un proceso automático (Control de
     * Asistencia), identificada por un marcador incrustado en la observación (p. ej.
     * "[ASISTENCIA-FALTA 2026-07]"). Evita duplicar y evita tocar novedades manuales.
     */
    public function getByMarcador(int $idEmpresa, int $idEmpleado, string $tipoCodigo, string $aplicaEn, int $mes, int $anio, string $marcador): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empresa = :emp AND id_empleado = :emple AND tipo_codigo = :tc
                  AND aplica_en = :ae AND periodo_mes = :m AND periodo_anio = :a
                  AND eliminado = false AND observacion ILIKE :mk
                ORDER BY id DESC LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp' => $idEmpresa, ':emple' => $idEmpleado, ':tc' => $tipoCodigo,
            ':ae' => $aplicaEn, ':m' => $mes, ':a' => $anio, ':mk' => '%' . $marcador . '%',
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Resuelve el id de un empleado por su identificación exacta (para importar). */
    public function getIdEmpleadoPorIdentificacion(int $idEmpresa, string $identificacion): ?int
    {
        $st = $this->db->prepare("SELECT id FROM empleados WHERE id_empresa = :e AND identificacion = :i AND eliminado = false LIMIT 1");
        $st->execute([':e' => $idEmpresa, ':i' => trim($identificacion)]);
        $id = $st->fetchColumn();
        return $id !== false ? (int) $id : null;
    }
}
