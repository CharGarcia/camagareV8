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

    /**
     * Corridas de rol NO pagadas aún (borrador/generado) con fecha_pago dentro del
     * rango: egresos de nómina futuros ya conocidos, para el Flujo de Caja proyectado.
     */
    public function getRolesProgramados(int $idEmpresa, string $desde, string $hasta): array
    {
        $sql = "SELECT id, tipo_rol, fecha_pago, descripcion, total_neto
                FROM {$this->table}
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND estado IN ('borrador', 'generado')
                  AND fecha_pago IS NOT NULL
                  AND fecha_pago BETWEEN :desde AND :hasta
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                ORDER BY fecha_pago ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':desde' => $desde, ':hasta' => $hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    // ─── Lectores masivos (para generación en lote, evitan N+1) ───────────────
    // Los ids provienen de la BD (getEmpleadosActivos) → se interpolan como enteros.

    /** [id_empleado => rubros_fijos[]] para todos los empleados. */
    public function getRubrosFijosMasivo(int $idEmpresa, array $ids): array
    {
        $map = array_fill_keys(array_map('intval', $ids), []);
        if (empty($ids)) return $map;
        $in = implode(',', array_map('intval', $ids));
        $st = $this->db->prepare("SELECT id_empleado, tipo, nombre, valor, aporta_iess, frecuencia
                                  FROM empleado_rubros_fijos
                                  WHERE id_empresa = :emp AND id_empleado IN ($in) AND eliminado = false");
        $st->execute([':emp' => $idEmpresa]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id_empleado']][] = $r;
        }
        return $map;
    }

    /** [id_empleado => novedades[]] del período+aplica_en para todos los empleados. */
    public function getNovedadesMasivo(int $idEmpresa, array $ids, int $anio, int $mes, string $aplicaEn): array
    {
        $map = array_fill_keys(array_map('intval', $ids), []);
        if (empty($ids)) return $map;
        $in = implode(',', array_map('intval', $ids));
        $st = $this->db->prepare("SELECT id_empleado, id, tipo_codigo, tipo_nombre, valor, motivo_nombre
                                  FROM novedades
                                  WHERE id_empresa = :emp AND id_empleado IN ($in) AND periodo_anio = :a
                                    AND periodo_mes = :m AND aplica_en = :ap AND estado = 'activo' AND eliminado = false
                                    AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)");
        $st->execute([':emp' => $idEmpresa, ':a' => $anio, ':m' => $mes, ':ap' => $aplicaEn]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id_empleado']][] = $r;
        }
        return $map;
    }

    /** [id_empleado => neto pagado por egreso en SEMANAL/QUINCENA del mes] (para el neteo mensual). */
    public function getPagadoNeteoMasivo(int $idEmpresa, array $ids, int $anio, int $mes): array
    {
        $map = array_fill_keys(array_map('intval', $ids), 0.0);
        if (empty($ids)) return $map;
        $in = implode(',', array_map('intval', $ids));
        $sql = "SELECT d.id_empleado, COALESCE(SUM(ed.monto_pagado), 0) AS pagado
                FROM egresos_detalle ed
                JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                JOIN rol_detalle d ON d.id = ed.id_referencia_documento
                JOIN rol_cabecera c ON c.id = d.id_rol
                WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado' AND ec.eliminado = false AND ed.eliminado = false
                  AND d.id_empresa = :emp AND d.id_empleado IN ($in)
                  AND c.periodo_anio = :a AND c.periodo_mes = :m
                  AND c.tipo_rol IN ('SEMANAL','QUINCENA') AND c.eliminado = false
                  AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)
                GROUP BY d.id_empleado";
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':emp' => $idEmpresa, ':a' => $anio, ':m' => $mes]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $map[(int) $r['id_empleado']] = (float) $r['pagado'];
            }
        } catch (\Throwable $e) {
            // egresos no disponible → todos en 0
        }
        return $map;
    }

    /**
     * [id_novedad => monto pagado por egreso] para novedades de anticipo/préstamo.
     * El rol descuenta solo lo pagado (egresos con tipo_documento='ANTICIPO').
     */
    public function getAnticiposPagadosMasivo(int $idEmpresa, array $idsNovedad): array
    {
        $map = array_fill_keys(array_map('intval', $idsNovedad), 0.0);
        if (empty($idsNovedad)) return $map;
        $in = implode(',', array_map('intval', $idsNovedad));
        $sql = "SELECT ed.id_referencia_documento AS id_novedad, COALESCE(SUM(ed.monto_pagado), 0) AS pagado
                FROM egresos_detalle ed
                JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                WHERE ed.tipo_documento = 'ANTICIPO' AND ec.estado != 'anulado'
                  AND ec.eliminado = false AND ed.eliminado = false
                  AND ec.id_empresa = :emp AND ed.id_referencia_documento IN ($in)
                GROUP BY ed.id_referencia_documento";
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':emp' => $idEmpresa]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $map[(int) $r['id_novedad']] = (float) $r['pagado'];
            }
        } catch (\Throwable $e) {
            // egresos no disponible → nada pagado
        }
        return $map;
    }

    /**
     * [id_novedad => true] de cuotas de préstamo cuyo préstamo AÚN NO fue desembolsado
     * (lo pagado por egreso PRESTAMO{codigo} para ese empleado < total de sus cuotas de ese tipo).
     * El rol no debe descontar la cuota hasta que el préstamo se haya entregado (pagado).
     */
    public function getPrestamosNoDesembolsadosMasivo(int $idEmpresa, array $idsNovedad): array
    {
        if (empty($idsNovedad)) return [];
        $in = implode(',', array_map('intval', $idsNovedad));
        // Por cada novedad de préstamo: total de cuotas de su (empleado,tipo) y lo desembolsado.
        $sql = "SELECT x.id_novedad
                FROM (
                    SELECT n.id AS id_novedad, n.id_empleado, n.tipo_codigo,
                           (SELECT COALESCE(SUM(n2.valor), 0) FROM novedades n2
                             WHERE n2.id_empresa = n.id_empresa AND n2.id_empleado = n.id_empleado
                               AND n2.tipo_codigo = n.tipo_codigo AND n2.eliminado = false AND n2.estado = 'activo') AS total_cuotas,
                           (SELECT COALESCE(SUM(ed.monto_pagado), 0)
                              FROM egresos_detalle ed JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                             WHERE ed.tipo_documento = ('PRESTAMO' || n.tipo_codigo)
                               AND ed.id_referencia_documento = n.id_empleado
                               AND ec.estado != 'anulado' AND ec.eliminado = false AND ed.eliminado = false) AS desembolsado
                    FROM novedades n
                    WHERE n.id IN ($in)
                ) x
                WHERE x.desembolsado < x.total_cuotas - 0.01";
        $set = [];
        try {
            $st = $this->db->prepare($sql);
            $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $set[(int) $id] = true;
            }
        } catch (\Throwable $e) {
            // egresos no disponible → no bloquear (comportamiento anterior)
        }
        return $set;
    }

    /** ¿El rol tiene algún pago registrado (egreso no anulado contra sus líneas)? Si sí, no debe regenerarse. */
    public function tienePagos(int $idRol): bool
    {
        $sql = "SELECT EXISTS(
                    SELECT 1 FROM egresos_detalle ed JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                    WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                      AND ec.eliminado = false AND ed.eliminado = false
                      AND ed.id_referencia_documento IN (SELECT id FROM rol_detalle WHERE id_rol = :r)
                )";
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':r' => $idRol]);
            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** N° de empleados del rol con saldo pendiente de pago (líneas aún no cubiertas por egresos). */
    public function contarLineasPendientesPago(int $idRol, int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*)
                FROM rol_detalle rd
                WHERE rd.id_rol = :r AND rd.id_empresa = :e
                  AND (rd.neto - COALESCE((SELECT SUM(ed.monto_pagado)
                        FROM egresos_detalle ed JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                       WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                         AND ec.eliminado = false AND ed.eliminado = false
                         AND ed.id_referencia_documento = rd.id), 0)) > 0.01";
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':r' => $idRol, ':e' => $idEmpresa]);
            return (int) $st->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Avisos de novedades PENDIENTES DE DESEMBOLSO para un período+aplica_en: anticipos sin
     * pagar y cuotas de préstamo cuyo préstamo aún no fue desembolsado. Esas novedades NO se
     * descuentan en el rol hasta pagarlas en Egresos → Nómina.
     * @return array<int,array{tipo:string,empleado:string,concepto:string,monto:float}>
     */
    public function getAvisosPendientes(int $idEmpresa, int $anio, int $mes, string $aplicaEn): array
    {
        $avisos = [];
        $p = [':emp' => $idEmpresa, ':a' => $anio, ':m' => $mes, ':ap' => $aplicaEn];
        try {
            // Anticipos (código 3) sin pagar por egreso.
            $sqlA = "SELECT emp.nombres_apellidos AS empleado, n.tipo_nombre AS concepto,
                            (n.valor - COALESCE(pa.total_pagado, 0)) AS monto
                     FROM novedades n JOIN empleados emp ON emp.id = n.id_empleado
                     LEFT JOIN (SELECT ed.id_referencia_documento, SUM(ed.monto_pagado) AS total_pagado
                                FROM egresos_detalle ed JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                                WHERE ed.tipo_documento = 'ANTICIPO' AND ec.estado != 'anulado'
                                  AND ec.eliminado = false AND ed.eliminado = false
                                GROUP BY ed.id_referencia_documento) pa ON pa.id_referencia_documento = n.id
                     WHERE n.id_empresa = :emp AND n.periodo_anio = :a AND n.periodo_mes = :m AND n.aplica_en = :ap
                       AND n.tipo_codigo = '3' AND n.estado = 'activo' AND n.eliminado = false
                       AND n.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)
                       AND (n.valor - COALESCE(pa.total_pagado, 0)) > 0.01
                     ORDER BY emp.nombres_apellidos";
            $st = $this->db->prepare($sqlA);
            $st->execute($p);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $avisos[] = ['tipo' => 'anticipo', 'empleado' => $r['empleado'], 'concepto' => $r['concepto'], 'monto' => (float) $r['monto']];
            }

            // Préstamos (7/8/9): cuota de este período cuyo préstamo aún no fue desembolsado.
            $sqlP = "SELECT emp.nombres_apellidos AS empleado, n.tipo_nombre AS concepto, n.valor AS monto
                     FROM novedades n JOIN empleados emp ON emp.id = n.id_empleado
                     WHERE n.id_empresa = :emp AND n.periodo_anio = :a AND n.periodo_mes = :m AND n.aplica_en = :ap
                       AND n.tipo_codigo IN ('7','8','9') AND n.estado = 'activo' AND n.eliminado = false
                       AND n.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)
                       AND (SELECT COALESCE(SUM(n2.valor), 0) FROM novedades n2
                             WHERE n2.id_empresa = n.id_empresa AND n2.id_empleado = n.id_empleado
                               AND n2.tipo_codigo = n.tipo_codigo AND n2.eliminado = false AND n2.estado = 'activo')
                           > (SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                               WHERE ed.tipo_documento = ('PRESTAMO' || n.tipo_codigo) AND ed.id_referencia_documento = n.id_empleado
                                 AND ec.estado != 'anulado' AND ec.eliminado = false AND ed.eliminado = false) + 0.01
                     ORDER BY emp.nombres_apellidos";
            $st2 = $this->db->prepare($sqlP);
            $st2->execute($p);
            foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $avisos[] = ['tipo' => 'prestamo', 'empleado' => $r['empleado'], 'concepto' => $r['concepto'], 'monto' => (float) $r['monto']];
            }
        } catch (\Throwable $e) {
            // egresos/nómina no disponible → sin avisos
        }
        return $avisos;
    }

    /** [id_empleado => true] de empleados con rol MENSUAL PAGADO ese período (exclusión semanal/quincena). */
    public function getMensualPagadoMasivo(int $idEmpresa, array $ids, int $anio, int $mes): array
    {
        if (empty($ids)) return [];
        $in = implode(',', array_map('intval', $ids));
        $sql = "SELECT DISTINCT d.id_empleado
                FROM rol_detalle d JOIN rol_cabecera c ON c.id = d.id_rol
                WHERE d.id_empresa = :emp AND d.id_empleado IN ($in)
                  AND c.tipo_rol = 'MENSUAL' AND c.periodo_anio = :a AND c.periodo_mes = :m AND c.eliminado = false
                  AND (
                    c.estado IN ('pagado','contabilizado')
                    OR (SELECT COALESCE(SUM(ed.monto_pagado), 0)
                          FROM egresos_detalle ed JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                         WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                           AND ec.eliminado = false AND ed.eliminado = false
                           AND ed.id_referencia_documento = d.id) > 0
                  )";
        $set = [];
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':emp' => $idEmpresa, ':a' => $anio, ':m' => $mes]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $e) {
                $set[(int) $e] = true;
            }
        } catch (\Throwable $e) {
            // roles/egresos no disponible → sin exclusión
        }
        return $set;
    }

    /**
     * Inserta todas las líneas de detalle en lotes (INSERT multifila) y devuelve el mapa
     * [id_empleado => id_detalle]. $filas: [ ['id_empleado'=>int, 'calc'=>[...]], ... ].
     */
    public function insertDetalleMasivo(int $idRol, int $idEmpresa, array $filas): array
    {
        $map = [];
        foreach (array_chunk($filas, 500) as $chunk) {
            $vals = [];
            $params = [];
            $i = 0;
            foreach ($chunk as $f) {
                $t = $f['calc'];
                $vals[] = "(:r{$i}, :emp{$i}, :e{$i}, :dias{$i}, :sb{$i}, :ti{$i}, :te{$i}, :ap{$i}, :app{$i}, :neto{$i})";
                $params[":r{$i}"] = $idRol;
                $params[":emp{$i}"] = $idEmpresa;
                $params[":e{$i}"] = $f['id_empleado'];
                $params[":dias{$i}"] = $t['dias_trabajados'];
                $params[":sb{$i}"] = $t['sueldo_base'];
                $params[":ti{$i}"] = $t['total_ingresos'];
                $params[":te{$i}"] = $t['total_egresos'];
                $params[":ap{$i}"] = $t['aporte_iess'];
                $params[":app{$i}"] = $t['aporte_patronal'];
                $params[":neto{$i}"] = $t['neto'];
                $i++;
            }
            $sql = "INSERT INTO rol_detalle (id_rol, id_empresa, id_empleado, dias_trabajados, sueldo_base,
                        total_ingresos, total_egresos, aporte_iess, aporte_patronal, neto)
                    VALUES " . implode(', ', $vals) . " RETURNING id, id_empleado";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['id_empleado']] = (int) $row['id'];
            }
        }
        return $map;
    }

    /**
     * Inserta todos los rubros en lotes (INSERT multifila).
     * $filas: [ ['id_detalle'=>int, 'rubro'=>[...]], ... ].
     */
    public function insertRubrosMasivo(int $idEmpresa, array $filas): void
    {
        foreach (array_chunk($filas, 1000) as $chunk) {
            $vals = [];
            $params = [];
            $i = 0;
            foreach ($chunk as $f) {
                $r = $f['rubro'];
                $vals[] = "(:d{$i}, :emp{$i}, :tipo{$i}, :con{$i}, :cod{$i}, :ori{$i}, :val{$i}, :ai{$i}, :idn{$i})";
                $params[":d{$i}"] = $f['id_detalle'];
                $params[":emp{$i}"] = $idEmpresa;
                $params[":tipo{$i}"] = $r['tipo'];
                $params[":con{$i}"] = $r['concepto'];
                $params[":cod{$i}"] = $r['codigo'] ?? null;
                $params[":ori{$i}"] = $r['origen'];
                $params[":val{$i}"] = $r['valor'];
                $params[":ai{$i}"] = !empty($r['aporta_iess']) ? 'true' : 'false';
                $params[":idn{$i}"] = $r['id_novedad'] ?? null;
                $i++;
            }
            $sql = "INSERT INTO rol_detalle_rubro (id_detalle, id_empresa, tipo, concepto, codigo, origen, valor, aporta_iess, id_novedad)
                    VALUES " . implode(', ', $vals);
            $st = $this->db->prepare($sql);
            $st->execute($params);
        }
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
        // periodos_json = TODOS los tramos de empleo que solapan el mes (puede haber
        // reingresos en el mismo mes). Se suman sus días en PHP para prorratear.
        $selPeriodos = 'NULL AS periodos_json';
        if ($inicioMes !== null && $finMes !== null) {
            $selPeriodos = "(SELECT json_agg(json_build_object('i', p.fecha_ingreso, 's', p.fecha_salida) ORDER BY p.fecha_ingreso)
                             FROM empleado_periodos p
                             WHERE p.id_empleado = e.id AND p.eliminado = false
                               AND p.fecha_ingreso <= :fin_mes
                               AND (p.fecha_salida IS NULL OR p.fecha_salida >= :inicio_mes)) AS periodos_json";
        }

        $sql = "SELECT e.id, e.identificacion, e.nombres_apellidos, e.sueldo_base, e.valor_semanal, e.valor_quincena,
                       e.aporte_personal, e.aporte_patronal, e.fondos_reserva, e.decimo_tercero, e.decimo_cuarto,
                       {$selPeriodos}
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

    /**
     * Meses (periodo_anio/mes + tipo_rol) en los que el empleado tiene un rol PAGADO:
     * la corrida está contabilizada/pagada, o su línea recibió pago vía egreso (no anulado).
     * Se usa para impedir editar la fecha de ingreso/salida de un rol ya pagado.
     */
    public function getMesesRolPagado(int $idEmpresa, int $idEmpleado): array
    {
        $sql = "SELECT DISTINCT c.periodo_anio AS anio, c.periodo_mes AS mes, c.tipo_rol
                FROM rol_detalle d
                JOIN rol_cabecera c ON c.id = d.id_rol
                WHERE d.id_empresa = :emp AND d.id_empleado = :e AND c.eliminado = false
                  AND (
                    c.estado IN ('pagado','contabilizado')
                    OR (SELECT COALESCE(SUM(ed.monto_pagado), 0)
                          FROM egresos_detalle ed
                          JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                         WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                           AND ec.eliminado = false AND ed.eliminado = false
                           AND ed.id_referencia_documento = d.id) > 0
                  )";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ¿El empleado tiene un rol PAGADO para ese tipo y período? (corrida contabilizada/pagada
     * o línea con pago por egreso no anulado). Se usa para bloquear cambios de novedades que
     * afectarían un rol ya pagado.
     */
    public function existeRolPagadoPeriodo(int $idEmpresa, int $idEmpleado, string $tipoRol, int $anio, int $mes): bool
    {
        $sql = "SELECT 1
                FROM rol_detalle d
                JOIN rol_cabecera c ON c.id = d.id_rol
                WHERE d.id_empresa = :emp AND d.id_empleado = :e
                  AND c.tipo_rol = :t AND c.periodo_anio = :a AND c.periodo_mes = :m
                  AND c.eliminado = false
                  AND (
                    c.estado IN ('pagado','contabilizado')
                    OR (SELECT COALESCE(SUM(ed.monto_pagado), 0)
                          FROM egresos_detalle ed
                          JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                         WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                           AND ec.eliminado = false AND ed.eliminado = false
                           AND ed.id_referencia_documento = d.id) > 0
                  )
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado, ':t' => $tipoRol, ':a' => $anio, ':m' => $mes]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Roles ABIERTOS del empleado: corridas en estado 'generado' (no pagadas) donde el
     * empleado tiene una línea con saldo pendiente. Se usan para avisar/regenerar al
     * editar datos del empleado que afectan al rol.
     */
    public function getRolesAbiertosEmpleado(int $idEmpresa, int $idEmpleado): array
    {
        $sql = "SELECT c.id, c.tipo_rol, c.periodo_anio, c.periodo_mes, c.estado
                FROM rol_detalle d
                JOIN rol_cabecera c ON c.id = d.id_rol
                WHERE d.id_empresa = :emp AND d.id_empleado = :e
                  AND c.eliminado = false AND c.estado = 'generado'
                  AND (d.neto - COALESCE((SELECT SUM(ed.monto_pagado)
                          FROM egresos_detalle ed
                          JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                         WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                           AND ec.eliminado = false AND ed.eliminado = false
                           AND ed.id_referencia_documento = d.id), 0)) > 0.01
                ORDER BY c.periodo_anio, c.periodo_mes, c.tipo_rol";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado]);
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

    /**
     * Neteo del mensual = lo REALMENTE PAGADO al empleado (vía egresos) en sus
     * corridas SEMANAL/QUINCENA del mismo mes. El pago es por empleado, no por corrida.
     */
    public function getPagadoNeteo(int $idEmpresa, int $idEmpleado, int $anio, int $mes): float
    {
        $sql = "SELECT COALESCE(SUM(ed.monto_pagado), 0)
                FROM egresos_detalle ed
                JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                JOIN rol_detalle d ON d.id = ed.id_referencia_documento
                JOIN rol_cabecera c ON c.id = d.id_rol
                WHERE ed.tipo_documento = 'ROL'
                  AND ec.estado != 'anulado' AND ec.eliminado = false AND ed.eliminado = false
                  AND d.id_empresa = :emp AND d.id_empleado = :e
                  AND c.periodo_anio = :a AND c.periodo_mes = :m
                  AND c.tipo_rol IN ('SEMANAL','QUINCENA') AND c.eliminado = false
                  AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)";
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado, ':a' => $anio, ':m' => $mes]);
            return (float) $st->fetchColumn();
        } catch (\Throwable $e) {
            return 0.0; // si el módulo de egresos aún no está disponible
        }
    }
}
