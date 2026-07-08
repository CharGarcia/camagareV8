<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

class RolPagoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('rol_cabecera');
    }

    // ─── Listado de corridas ─────────────────────────────────────────────────
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['periodo_anio', 'periodo_mes', 'tipo_rol', 'estado', 'fecha_pago', 'total_neto', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'id';
        $ordenDir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'r', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $where .= " AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (r.descripcion ILIKE :b OR r.tipo_rol ILIKE :b OR r.estado ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'exacto'   => ['tipo' => 'r.tipo_rol', 'estado' => 'r.estado', 'mes' => 'r.periodo_mes', 'anio' => 'r.periodo_anio'],
            'fecha'    => ['fecha' => 'r.fecha_pago'],
            'numerico' => ['neto' => 'r.total_neto'],
        ]);

        $from = "FROM {$this->table} r {$where}";
        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT r.*, (SELECT COUNT(*) FROM rol_detalle d WHERE d.id_rol = r.id) AS num_empleados
                {$from} ORDER BY r.{$ordenCol} {$ordenDir}";
        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function existsCorrida(int $idEmpresa, string $tipo, int $anio, int $mes, int $numero, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE id_empresa = :e AND tipo_rol = :t AND periodo_anio = :a AND periodo_mes = :m
                  AND numero_periodo = :n AND eliminado = false
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)";
        $p = [':e' => $idEmpresa, ':t' => $tipo, ':a' => $anio, ':m' => $mes, ':n' => $numero];
        if ($excludeId !== null) { $sql .= " AND id != :id"; $p[':id'] = $excludeId; }
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchColumn() !== false;
    }

    public function createCabecera(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, tipo_rol, periodo_anio, periodo_mes, numero_periodo,
                    fecha_desde, fecha_hasta, fecha_pago, descripcion, estado, tipo_ambiente,
                    created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :tipo_rol, :periodo_anio, :periodo_mes, :numero_periodo,
                    :fecha_desde, :fecha_hasta, :fecha_pago, :descripcion, 'borrador',
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $d['id_empresa'],
            ':tipo_rol'      => $d['tipo_rol'],
            ':periodo_anio'  => (int) $d['periodo_anio'],
            ':periodo_mes'   => (int) $d['periodo_mes'],
            ':numero_periodo' => (int) ($d['numero_periodo'] ?? 0),
            ':fecha_desde'   => $d['fecha_desde'] ?: null,
            ':fecha_hasta'   => $d['fecha_hasta'] ?: null,
            ':fecha_pago'    => $d['fecha_pago'] ?: null,
            ':descripcion'   => $d['descripcion'] ?? null,
            ':id_u'          => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function updateCabecera(int $id, int $idEmpresa, array $d): bool
    {
        $sql = "UPDATE {$this->table} SET
                    tipo_rol = :tipo_rol, periodo_anio = :periodo_anio, periodo_mes = :periodo_mes,
                    numero_periodo = :numero_periodo, fecha_desde = :fecha_desde, fecha_hasta = :fecha_hasta,
                    fecha_pago = :fecha_pago, descripcion = :descripcion,
                    updated_by = :id_u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':tipo_rol'      => $d['tipo_rol'],
            ':periodo_anio'  => (int) $d['periodo_anio'],
            ':periodo_mes'   => (int) $d['periodo_mes'],
            ':numero_periodo' => (int) ($d['numero_periodo'] ?? 0),
            ':fecha_desde'   => $d['fecha_desde'] ?: null,
            ':fecha_hasta'   => $d['fecha_hasta'] ?: null,
            ':fecha_pago'    => $d['fecha_pago'] ?: null,
            ':descripcion'   => $d['descripcion'] ?? null,
            ':id_u'          => $d['id_usuario'],
            ':id'            => $id,
            ':id_empresa'    => $idEmpresa,
        ]);
    }

    public function updateTotalesEstado(int $id, array $tot, string $estado): void
    {
        $sql = "UPDATE {$this->table} SET
                    total_ingresos = :ti, total_egresos = :te, total_neto = :tn,
                    total_aporte_patronal = :tap, estado = :estado, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ti' => $tot['ingresos'], ':te' => $tot['egresos'], ':tn' => $tot['neto'],
            ':tap' => $tot['aporte_patronal'], ':estado' => $estado, ':id' => $id,
        ]);
    }

    public function setIdAsiento(int $id, ?int $idAsiento): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET id_asiento = :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $st->execute([':a' => $idAsiento, ':id' => $id]);
    }

    public function setEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET estado = :e, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                                  WHERE id = :id AND id_empresa = :emp AND eliminado = false");
        return $st->execute([':e' => $estado, ':u' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
    }

    public function findCabecera(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND id_empresa = :emp AND eliminado = false");
        $st->execute([':id' => $id, ':emp' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                  WHERE id = :id AND id_empresa = :emp");
        return $st->execute([':u' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
    }

    // ─── Detalle ─────────────────────────────────────────────────────────────
    public function borrarDetalle(int $idRol): void
    {
        // ON DELETE CASCADE elimina también rol_detalle_rubro.
        $this->db->prepare("DELETE FROM rol_detalle WHERE id_rol = :r")->execute([':r' => $idRol]);
    }

    public function insertDetalle(int $idRol, int $idEmpresa, int $idEmpleado, array $t): int
    {
        $sql = "INSERT INTO rol_detalle (id_rol, id_empresa, id_empleado, dias_trabajados, sueldo_base,
                    total_ingresos, total_egresos, aporte_iess, aporte_patronal, neto)
                VALUES (:r, :emp, :e, :dias, :sb, :ti, :te, :ap, :app, :neto)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':r' => $idRol, ':emp' => $idEmpresa, ':e' => $idEmpleado,
            ':dias' => $t['dias_trabajados'], ':sb' => $t['sueldo_base'],
            ':ti' => $t['total_ingresos'], ':te' => $t['total_egresos'],
            ':ap' => $t['aporte_iess'], ':app' => $t['aporte_patronal'], ':neto' => $t['neto'],
        ]);
        return $this->lastInsertId();
    }

    public function insertRubro(int $idDetalle, int $idEmpresa, array $r): void
    {
        $sql = "INSERT INTO rol_detalle_rubro (id_detalle, id_empresa, tipo, concepto, codigo, origen, valor, aporta_iess, id_novedad)
                VALUES (:d, :emp, :tipo, :concepto, :codigo, :origen, :valor, :ai, :idn)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':d' => $idDetalle, ':emp' => $idEmpresa, ':tipo' => $r['tipo'], ':concepto' => $r['concepto'],
            ':codigo' => $r['codigo'] ?? null, ':origen' => $r['origen'], ':valor' => $r['valor'],
            ':ai' => !empty($r['aporta_iess']) ? 'true' : 'false', ':idn' => $r['id_novedad'] ?? null,
        ]);
    }

    /** Monto pagado por cada línea de rol (desde egresos), para saber si un empleado está pagado. */
    public function getPagadoPorDetalle(int $idRol): array
    {
        $sql = "SELECT d.id_referencia_documento AS id_detalle, COALESCE(SUM(d.monto_pagado), 0) AS pagado
                FROM egresos_detalle d
                JOIN egresos_cabecera e ON e.id = d.id_egreso
                WHERE d.tipo_documento = 'ROL' AND e.estado != 'anulado' AND e.eliminado = false AND d.eliminado = false
                  AND d.id_referencia_documento IN (SELECT id FROM rol_detalle WHERE id_rol = :r)
                GROUP BY d.id_referencia_documento";
        $map = [];
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':r' => $idRol]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['id_detalle']] = (float) $row['pagado'];
            }
        } catch (\Throwable $e) {
            // Si el módulo de egresos aún no está, tratar todo como no pagado.
        }
        return $map;
    }

    public function getDetalleCompleto(int $idRol, int $idEmpresa): array
    {
        $sql = "SELECT d.*, e.nombres_apellidos, e.identificacion, e.email, e.cargo,
                       e.decimo_tercero, e.decimo_cuarto, e.fondos_reserva
                FROM rol_detalle d JOIN empleados e ON e.id = d.id_empleado
                WHERE d.id_rol = :r AND d.id_empresa = :emp
                ORDER BY e.nombres_apellidos";
        $st = $this->db->prepare($sql);
        $st->execute([':r' => $idRol, ':emp' => $idEmpresa]);
        $detalle = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($detalle) {
            $ids = array_column($detalle, 'id');
            $in = implode(',', array_map('intval', $ids));
            $rub = $this->db->query("SELECT * FROM rol_detalle_rubro WHERE id_detalle IN ($in) ORDER BY tipo DESC, id");
            $rubros = $rub->fetchAll(PDO::FETCH_ASSOC);
            $pagos = $this->getPagadoPorDetalle($idRol);
            foreach ($detalle as &$d) {
                $d['rubros'] = array_values(array_filter($rubros, fn($x) => (int) $x['id_detalle'] === (int) $d['id']));
                $pagado = round((float) ($pagos[(int) $d['id']] ?? 0), 2);
                $neto = round((float) $d['neto'], 2);
                $d['pagado'] = $pagado;
                $d['saldo'] = round($neto - $pagado, 2);
                $d['estado_pago'] = $pagado <= 0 ? 'pendiente' : ($d['saldo'] <= 0.01 ? 'pagado' : 'parcial');
            }
        }
        return $detalle;
    }

    /** Una línea (empleado) del rol con sus rubros, para PDF/correo individual. */
    public function getLinea(int $idDetalle, int $idEmpresa): ?array
    {
        $sql = "SELECT d.*, e.nombres_apellidos, e.identificacion, e.email, e.cargo,
                       e.decimo_tercero, e.decimo_cuarto, e.fondos_reserva
                FROM rol_detalle d JOIN empleados e ON e.id = d.id_empleado
                WHERE d.id = :d AND d.id_empresa = :emp";
        $st = $this->db->prepare($sql);
        $st->execute([':d' => $idDetalle, ':emp' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $rub = $this->db->prepare("SELECT * FROM rol_detalle_rubro WHERE id_detalle = :d ORDER BY tipo DESC, id");
        $rub->execute([':d' => $idDetalle]);
        $row['rubros'] = $rub->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    // ─── Lectores para el motor de cálculo ───────────────────────────────────
    public function getEmpleadosActivos(int $idEmpresa, ?string $inicioMes = null, ?string $finMes = null): array
    {
        $sql = "SELECT e.id, e.identificacion, e.nombres_apellidos, e.sueldo_base, e.valor_semanal, e.valor_quincena,
                       e.aporte_personal, e.aporte_patronal, e.fondos_reserva, e.decimo_tercero, e.decimo_cuarto
                FROM empleados e
                WHERE e.id_empresa = :emp AND e.eliminado = false AND e.estado = 'activo'";
        $params = [':emp' => $idEmpresa];

        // Solo empleados cuyo ingreso vigente cubre el período (o sin periodos registrados).
        if ($inicioMes !== null && $finMes !== null) {
            $sql .= " AND (
                NOT EXISTS (SELECT 1 FROM empleado_periodos p WHERE p.id_empleado = e.id AND p.eliminado = false)
                OR EXISTS (
                    SELECT 1 FROM empleado_periodos p
                    WHERE p.id_empleado = e.id AND p.eliminado = false
                      AND p.fecha_ingreso <= :fin_mes
                      AND (p.fecha_salida IS NULL OR p.fecha_salida >= :inicio_mes)
                )
            )";
            $params[':inicio_mes'] = $inicioMes;
            $params[':fin_mes'] = $finMes;
        }
        $sql .= " ORDER BY e.nombres_apellidos";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** IDs de roles en estado 'generado' para un período+tipo (para auto-regenerar). */
    public function getRolesAfectados(int $idEmpresa, string $tipoRol, int $anio, int $mes): array
    {
        $st = $this->db->prepare("SELECT id FROM {$this->table}
                                  WHERE id_empresa = :emp AND tipo_rol = :t AND periodo_anio = :a AND periodo_mes = :m
                                    AND estado = 'generado' AND eliminado = false
                                    AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)");
        $st->execute([':emp' => $idEmpresa, ':t' => $tipoRol, ':a' => $anio, ':m' => $mes]);
        return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }

    public function getRubrosFijos(int $idEmpleado, int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT tipo, nombre, valor, aporta_iess, frecuencia
                                  FROM empleado_rubros_fijos
                                  WHERE id_empleado = :e AND id_empresa = :emp AND eliminado = false");
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNovedades(int $idEmpresa, int $idEmpleado, int $anio, int $mes, string $aplicaEn): array
    {
        $st = $this->db->prepare("SELECT id, tipo_codigo, tipo_nombre, valor, motivo_nombre
                                  FROM novedades
                                  WHERE id_empresa = :emp AND id_empleado = :e AND periodo_anio = :a
                                    AND periodo_mes = :m AND aplica_en = :ap AND estado = 'activo' AND eliminado = false
                                    AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)");
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado, ':a' => $anio, ':m' => $mes, ':ap' => $aplicaEn]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Tarifas del año desde la tabla global 'salarios'. */
    public function getSalario(int $anio): array
    {
        $st = $this->db->prepare("SELECT * FROM salarios WHERE ano = :a AND status = 1 ORDER BY ano DESC LIMIT 1");
        $st->execute([':a' => $anio]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) return $r;
        // Fallback: el año más reciente disponible.
        $r = $this->db->query("SELECT * FROM salarios WHERE status = 1 ORDER BY ano DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $r ?: ['sbu' => 460, 'hora_normal' => 240, 'hora_nocturna' => 25, 'hora_suplementaria' => 50, 'hora_extraordinaria' => 100, 'fondo_reserva' => 8.33];
    }

    /** Suma del neto ya pagado en SEMANAL/QUINCENA del mismo mes (para el neteo del mensual). */
    public function getPagadoNeteo(int $idEmpresa, int $idEmpleado, int $anio, int $mes): float
    {
        $sql = "SELECT COALESCE(SUM(d.neto), 0)
                FROM rol_detalle d JOIN rol_cabecera c ON c.id = d.id_rol
                WHERE d.id_empresa = :emp AND d.id_empleado = :e
                  AND c.periodo_anio = :a AND c.periodo_mes = :m
                  AND c.tipo_rol IN ('SEMANAL','QUINCENA')
                  AND c.estado IN ('pagado','contabilizado') AND c.eliminado = false
                  AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado, ':a' => $anio, ':m' => $mes]);
        return (float) $st->fetchColumn();
    }
}
