<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CuentasPorPagarRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('compras_cabecera');
    }

    // ─────────────────────────────────────────────────────────────────────
    // CTEs reutilizables
    // ─────────────────────────────────────────────────────────────────────

    /**
     * CTE que acumula lo pagado desde egresos_detalle hasta una fecha de corte opcional.
     */
    private function getCtePagado(?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND ec.fecha_emision <= :pagado_hasta" : '';
        return "
            SELECT ed.tipo_documento,
                   ed.id_referencia_documento AS id_doc,
                   SUM(ed.monto_pagado)        AS total_pagado
            FROM egresos_detalle ed
            INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
            WHERE ed.tipo_documento IN ('COMPRA','LIQUIDACION')
              AND ec.estado    != 'anulado'
              AND ec.eliminado  = false
              AND ed.eliminado  = false
              {$filtroFecha}
            GROUP BY ed.tipo_documento, ed.id_referencia_documento
        ";
    }

    /**
     * CTE que suma NC/ND de compras_cabecera hasta una fecha de corte opcional.
     */
    private function getCteNcNd(?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND nc.fecha_emision <= :nc_nd_hasta" : '';
        return "
            SELECT nc.id_empresa,
                   nc.id_proveedor,
                   nc.documento_modificado,
                   SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
                   SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
            FROM compras_cabecera nc
            WHERE nc.tipo_comprobante IN ('04','05')
              AND nc.eliminado = false
              {$filtroFecha}
            GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
        ";
    }

    /**
     * CTE que suma retenciones autorizadas hasta una fecha de corte opcional.
     */
    private function getCteRetenciones(?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND r.fecha_emision <= :ret_hasta" : '';
        return "
            SELECT r.id_compra,
                   r.id_liquidacion,
                   SUM(r.total_retenido) AS total_retenido
            FROM retencion_compra_cabecera r
            WHERE r.eliminado = false
              AND UPPER(r.estado) NOT IN ('ANULADO','BORRADOR','PENDIENTE')
              {$filtroFecha}
            GROUP BY r.id_compra, r.id_liquidacion
        ";
    }

    /**
     * Agrega los parámetros de fecha de corte para los tres CTEs y devuelve la fecha.
     */
    private function aplicarFechaCorteCtEs(array $filtros, array &$params): ?string
    {
        $fechaHasta = $filtros['fecha_hasta'] ?? null;
        if ($fechaHasta) {
            $params[':pagado_hasta'] = $fechaHasta;
            $params[':nc_nd_hasta']  = $fechaHasta;
            $params[':ret_hasta']    = $fechaHasta;
        }
        return $fechaHasta ?: null;
    }

    /**
     * Expresión SQL para calcular la fecha de vencimiento de una compra.
     * Toma el mayor plazo registrado en compras_pagos.
     */
    private function exprFechaVencCompra(string $aliasC = 'c'): string
    {
        return "(
            SELECT {$aliasC}.fecha_emision + INTERVAL '1 day' *
                COALESCE(
                    (SELECT CASE cp.unidad_tiempo
                                WHEN 'meses' THEN cp.plazo * 30
                                WHEN 'anos'  THEN cp.plazo * 365
                                ELSE cp.plazo
                            END
                     FROM compras_pagos cp
                     WHERE cp.id_compra = {$aliasC}.id
                     ORDER BY cp.plazo DESC LIMIT 1),
                    0
                )
        )";
    }

    /**
     * Expresión SQL para calcular la fecha de vencimiento de una liquidación.
     */
    private function exprFechaVencLiquid(string $aliasL = 'l'): string
    {
        return "(
            SELECT {$aliasL}.fecha_emision + INTERVAL '1 day' *
                COALESCE(
                    (SELECT CASE lp.unidad_tiempo
                                WHEN 'meses' THEN lp.plazo * 30
                                WHEN 'anos'  THEN lp.plazo * 365
                                ELSE lp.plazo
                            END
                     FROM liquidaciones_pagos lp
                     WHERE lp.id_cabecera = {$aliasL}.id
                     ORDER BY lp.plazo DESC LIMIT 1),
                    0
                )
        )";
    }

    // ─────────────────────────────────────────────────────────────────────
    // CONSULTA PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Listado unificado de cuentas por pagar (facturas de compra + liquidaciones).
     */
    public function getListado(int $idEmpresa, array $filtros): array
    {
        [$whereExtra, $params] = $this->buildWhereExtra($idEmpresa, $filtros);
        $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

        $fvcExpr = $this->exprFechaVencCompra('c');
        $fvlExpr = $this->exprFechaVencLiquid('l');

        $sql = "
            WITH
            pagado AS (" . $this->getCtePagado($fh) . "),
            nc_nd  AS (" . $this->getCteNcNd($fh) . "),
            ret    AS (" . $this->getCteRetenciones($fh) . "),
            docs   AS (
                -- ── FACTURAS DE COMPRA ────────────────────────────────────
                SELECT
                    c.id,
                    'COMPRA'                                                      AS tipo_fuente,
                    c.id_proveedor,
                    p.razon_social                                                AS proveedor_nombre,
                    p.identificacion                                              AS proveedor_ruc,
                    COALESCE(p.email,   '')                                       AS proveedor_email,
                    COALESCE(p.telefono,'')                                       AS proveedor_telefono,
                    CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero_documento,
                    c.fecha_emision,
                    c.importe_total                                               AS total,
                    COALESCE(pg.total_pagado, 0)                                  AS total_pagado,
                    COALESCE(nn.total_nc, 0)                                      AS total_nc,
                    COALESCE(nn.total_nd, 0)                                      AS total_nd,
                    COALESCE(ret.total_retenido, 0)                               AS total_retenido,
                    c.importe_total
                        - COALESCE(pg.total_pagado,   0)
                        - COALESCE(ret.total_retenido,0)
                        - COALESCE(nn.total_nc,       0)
                        + COALESCE(nn.total_nd,       0)                         AS saldo,
                    {$fvcExpr}                                                    AS fecha_vencimiento
                FROM compras_cabecera c
                JOIN proveedores p
                  ON p.id = c.id_proveedor
                LEFT JOIN pagado pg
                  ON pg.tipo_documento = 'COMPRA'
                 AND pg.id_doc = c.id
                LEFT JOIN nc_nd nn
                  ON nn.id_empresa         = c.id_empresa
                 AND nn.id_proveedor       = c.id_proveedor
                 AND nn.documento_modificado = CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                LEFT JOIN ret
                  ON ret.id_compra = c.id
                 AND ret.id_liquidacion IS NULL
                WHERE c.id_empresa       = :id_empresa
                  AND c.eliminado        = false
                  AND c.tipo_comprobante = '01'
                  AND c.tipo_ambiente    = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_ta)

                UNION ALL

                -- ── LIQUIDACIONES DE COMPRA ───────────────────────────────
                SELECT
                    l.id,
                    'LIQUIDACION'                                                 AS tipo_fuente,
                    l.id_proveedor,
                    p.razon_social                                                AS proveedor_nombre,
                    p.identificacion                                              AS proveedor_ruc,
                    COALESCE(p.email,   '')                                       AS proveedor_email,
                    COALESCE(p.telefono,'')                                       AS proveedor_telefono,
                    CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero_documento,
                    l.fecha_emision,
                    l.importe_total                                               AS total,
                    COALESCE(pg.total_pagado, 0)                                  AS total_pagado,
                    0::numeric                                                    AS total_nc,
                    0::numeric                                                    AS total_nd,
                    COALESCE(ret.total_retenido, 0)                               AS total_retenido,
                    l.importe_total
                        - COALESCE(pg.total_pagado,   0)
                        - COALESCE(ret.total_retenido,0)                         AS saldo,
                    {$fvlExpr}                                                    AS fecha_vencimiento
                FROM liquidaciones_cabecera l
                JOIN proveedores p
                  ON p.id = l.id_proveedor
                LEFT JOIN pagado pg
                  ON pg.tipo_documento = 'LIQUIDACION'
                 AND pg.id_doc = l.id
                LEFT JOIN ret
                  ON ret.id_liquidacion = l.id
                 AND ret.id_compra IS NULL
                WHERE l.id_empresa    = :id_empresa2
                  AND l.eliminado     = false
                  AND l.estado       IN ('autorizado','AUTORIZADO','aprobado','APROBADO')
                  AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_ta2)
            )
            SELECT
                d.*,
                COALESCE((CURRENT_DATE - d.fecha_vencimiento::date), 0) AS dias_vencido
            FROM docs d
            WHERE 1=1
                  {$whereExtra}
            ORDER BY d.fecha_vencimiento ASC NULLS LAST, d.fecha_emision DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas para las tarjetas superiores.
     */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$whereExtra, $params] = $this->buildWhereExtra($idEmpresa, $filtrosSinEstado);
        $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

        $fvcExpr = $this->exprFechaVencCompra('c');
        $fvlExpr = $this->exprFechaVencLiquid('l');

        $sql = "
            WITH
            pagado AS (" . $this->getCtePagado($fh) . "),
            nc_nd  AS (" . $this->getCteNcNd($fh) . "),
            ret    AS (" . $this->getCteRetenciones($fh) . "),
            docs   AS (
                SELECT c.id_proveedor,
                       'COMPRA'::text                                              AS tipo_fuente,
                       c.fecha_emision,
                       c.importe_total
                           - COALESCE(pg.total_pagado,   0)
                           - COALESCE(ret.total_retenido,0)
                           - COALESCE(nn.total_nc,       0)
                           + COALESCE(nn.total_nd,       0) AS saldo,
                       {$fvcExpr} AS fecha_vencimiento
                FROM compras_cabecera c
                JOIN proveedores p ON p.id = c.id_proveedor
                LEFT JOIN pagado pg ON pg.tipo_documento='COMPRA' AND pg.id_doc=c.id
                LEFT JOIN nc_nd nn  ON nn.id_empresa=c.id_empresa AND nn.id_proveedor=c.id_proveedor
                                   AND nn.documento_modificado=CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                LEFT JOIN ret      ON ret.id_compra=c.id AND ret.id_liquidacion IS NULL
                WHERE c.id_empresa=:id_empresa AND c.eliminado=false AND c.tipo_comprobante='01'
                  AND c.tipo_ambiente=(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id=:id_empresa_ta)

                UNION ALL

                SELECT l.id_proveedor,
                       'LIQUIDACION'::text                                         AS tipo_fuente,
                       l.fecha_emision,
                       l.importe_total
                           - COALESCE(pg.total_pagado,   0)
                           - COALESCE(ret.total_retenido,0) AS saldo,
                       {$fvlExpr} AS fecha_vencimiento
                FROM liquidaciones_cabecera l
                JOIN proveedores p ON p.id = l.id_proveedor
                LEFT JOIN pagado pg ON pg.tipo_documento='LIQUIDACION' AND pg.id_doc=l.id
                LEFT JOIN ret      ON ret.id_liquidacion=l.id AND ret.id_compra IS NULL
                WHERE l.id_empresa=:id_empresa2 AND l.eliminado=false
                  AND l.estado IN ('autorizado','AUTORIZADO','aprobado','APROBADO')
                  AND l.tipo_ambiente=(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id=:id_empresa_ta2)
            )
            SELECT
                COUNT(*) AS total_docs,
                SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END) AS total_saldo,
                SUM(CASE WHEN saldo > 0 AND fecha_vencimiento::date < CURRENT_DATE THEN saldo ELSE 0 END) AS total_vencido,
                SUM(CASE WHEN saldo > 0 AND fecha_vencimiento::date >= CURRENT_DATE THEN saldo ELSE 0 END) AS total_al_dia,
                COUNT(CASE WHEN saldo > 0 AND fecha_vencimiento::date < CURRENT_DATE THEN 1 END) AS docs_vencidos
            FROM docs d
            WHERE 1=1
                  {$whereExtra}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        // Sumar los saldos iniciales CXP (mismo filtro de proveedor)
        $si = $this->getStatsSaldosInicialesCxp($idEmpresa, $filtros);

        return [
            'total_docs'    => (int)($r['total_docs']    ?? 0) + $si['cnt'],
            'total_saldo'   => (float)($r['total_saldo'] ?? 0) + $si['total_saldo'],
            'total_vencido' => (float)($r['total_vencido'] ?? 0) + $si['total_vencido'],
            'total_al_dia'  => (float)($r['total_al_dia']  ?? 0) + $si['total_al_dia'],
            'docs_vencidos' => (int)($r['docs_vencidos']   ?? 0) + $si['vencidas'],
        ];
    }

    /**
     * Agregados de los saldos iniciales CXP pendientes (saldo_pendiente =
     * saldo_inicial - monto_pagado) para sumarlos a las tarjetas. Respeta el
     * filtro de proveedor.
     */
    private function getStatsSaldosInicialesCxp(int $idEmpresa, array $filtros): array
    {
        $where  = "id_empresa = :si_emp AND eliminado = false";
        $params = [':si_emp' => $idEmpresa];

        if (!empty($filtros['id_proveedor'])) {
            $raw = is_array($filtros['id_proveedor']) ? $filtros['id_proveedor'] : explode(',', (string)$filtros['id_proveedor']);
            $prov = array_filter(array_map('intval', $raw));
            if (!empty($prov)) {
                $in = [];
                foreach (array_values($prov) as $i => $id) { $k = ":sipp{$i}"; $in[] = $k; $params[$k] = $id; }
                $where .= " AND id_proveedor IN (" . implode(',', $in) . ")";
            }
        }

        $sql = "
            SELECT
                COUNT(*) FILTER (WHERE saldo_pendiente > 0) AS cnt,
                COALESCE(SUM(CASE WHEN saldo_pendiente > 0 THEN saldo_pendiente ELSE 0 END), 0) AS total_saldo,
                COALESCE(SUM(CASE WHEN saldo_pendiente > 0 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURRENT_DATE THEN saldo_pendiente ELSE 0 END), 0) AS total_vencido,
                COALESCE(SUM(CASE WHEN saldo_pendiente > 0 AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= CURRENT_DATE) THEN saldo_pendiente ELSE 0 END), 0) AS total_al_dia,
                COUNT(*) FILTER (WHERE saldo_pendiente > 0 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURRENT_DATE) AS vencidas
            FROM saldos_iniciales_cxp
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'cnt'           => (int)($r['cnt']           ?? 0),
            'total_saldo'   => (float)($r['total_saldo'] ?? 0),
            'total_vencido' => (float)($r['total_vencido'] ?? 0),
            'total_al_dia'  => (float)($r['total_al_dia']  ?? 0),
            'vencidas'      => (int)($r['vencidas']      ?? 0),
        ];
    }

    /**
     * Antigüedad del saldo para el gráfico.
     */
    public function getAntiguedad(int $idEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$whereExtra, $params] = $this->buildWhereExtra($idEmpresa, $filtrosSinEstado);
        $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

        $fvcExpr = $this->exprFechaVencCompra('c');
        $fvlExpr = $this->exprFechaVencLiquid('l');

        $sql = "
            WITH
            pagado AS (" . $this->getCtePagado($fh) . "),
            nc_nd  AS (" . $this->getCteNcNd($fh) . "),
            ret    AS (" . $this->getCteRetenciones($fh) . "),
            docs   AS (
                SELECT c.id_proveedor,
                       'COMPRA'::text                                              AS tipo_fuente,
                       c.fecha_emision,
                       c.importe_total
                           - COALESCE(pg.total_pagado,   0)
                           - COALESCE(ret.total_retenido,0)
                           - COALESCE(nn.total_nc,       0)
                           + COALESCE(nn.total_nd,       0) AS saldo,
                       {$fvcExpr} AS fecha_vencimiento
                FROM compras_cabecera c
                JOIN proveedores p ON p.id=c.id_proveedor
                LEFT JOIN pagado pg ON pg.tipo_documento='COMPRA' AND pg.id_doc=c.id
                LEFT JOIN nc_nd nn  ON nn.id_empresa=c.id_empresa AND nn.id_proveedor=c.id_proveedor
                                   AND nn.documento_modificado=CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                LEFT JOIN ret      ON ret.id_compra=c.id AND ret.id_liquidacion IS NULL
                WHERE c.id_empresa=:id_empresa AND c.eliminado=false AND c.tipo_comprobante='01'
                  AND c.tipo_ambiente=(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id=:id_empresa_ta)

                UNION ALL

                SELECT l.id_proveedor,
                       'LIQUIDACION'::text                                         AS tipo_fuente,
                       l.fecha_emision,
                       l.importe_total
                           - COALESCE(pg.total_pagado,   0)
                           - COALESCE(ret.total_retenido,0) AS saldo,
                       {$fvlExpr} AS fecha_vencimiento
                FROM liquidaciones_cabecera l
                JOIN proveedores p ON p.id=l.id_proveedor
                LEFT JOIN pagado pg ON pg.tipo_documento='LIQUIDACION' AND pg.id_doc=l.id
                LEFT JOIN ret      ON ret.id_liquidacion=l.id AND ret.id_compra IS NULL
                WHERE l.id_empresa=:id_empresa2 AND l.eliminado=false
                  AND l.estado IN ('autorizado','AUTORIZADO','aprobado','APROBADO')
                  AND l.tipo_ambiente=(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id=:id_empresa_ta2)
            )
            SELECT
                SUM(CASE WHEN d.saldo > 0 AND (CURRENT_DATE - d.fecha_vencimiento::date) <= 0              THEN d.saldo ELSE 0 END) AS tramo_vigente,
                SUM(CASE WHEN d.saldo > 0 AND (CURRENT_DATE - d.fecha_vencimiento::date) BETWEEN 1  AND 30 THEN d.saldo ELSE 0 END) AS tramo_1_30,
                SUM(CASE WHEN d.saldo > 0 AND (CURRENT_DATE - d.fecha_vencimiento::date) BETWEEN 31 AND 60 THEN d.saldo ELSE 0 END) AS tramo_31_60,
                SUM(CASE WHEN d.saldo > 0 AND (CURRENT_DATE - d.fecha_vencimiento::date) BETWEEN 61 AND 90 THEN d.saldo ELSE 0 END) AS tramo_61_90,
                SUM(CASE WHEN d.saldo > 0 AND (CURRENT_DATE - d.fecha_vencimiento::date) > 90              THEN d.saldo ELSE 0 END) AS tramo_mas_90
            FROM docs d
            WHERE 1=1
                  {$whereExtra}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        // Sumar los tramos de los saldos iniciales CXP (mismo filtro de proveedor)
        $si = $this->getAntiguedadSaldosInicialesCxp($idEmpresa, $filtros);

        return [
            'vigente'     => (float)($r['tramo_vigente'] ?? 0) + $si['vigente'],
            'tramo_1_30'  => (float)($r['tramo_1_30']   ?? 0) + $si['tramo_1_30'],
            'tramo_31_60' => (float)($r['tramo_31_60']  ?? 0) + $si['tramo_31_60'],
            'tramo_61_90' => (float)($r['tramo_61_90']  ?? 0) + $si['tramo_61_90'],
            'mas_90'      => (float)($r['tramo_mas_90'] ?? 0) + $si['mas_90'],
        ];
    }

    /**
     * Tramos de antigüedad de los saldos iniciales CXP pendientes
     * (saldo_pendiente = saldo_inicial - monto_pagado). Respeta el filtro de proveedor.
     */
    private function getAntiguedadSaldosInicialesCxp(int $idEmpresa, array $filtros): array
    {
        $where  = "id_empresa = :si_emp AND eliminado = false AND saldo_pendiente > 0";
        $params = [':si_emp' => $idEmpresa];

        if (!empty($filtros['id_proveedor'])) {
            $raw = is_array($filtros['id_proveedor']) ? $filtros['id_proveedor'] : explode(',', (string)$filtros['id_proveedor']);
            $prov = array_filter(array_map('intval', $raw));
            if (!empty($prov)) {
                $in = [];
                foreach (array_values($prov) as $i => $id) { $k = ":sipa{$i}"; $in[] = $k; $params[$k] = $id; }
                $where .= " AND id_proveedor IN (" . implode(',', $in) . ")";
            }
        }

        $dv = "CASE WHEN fecha_vencimiento IS NULL THEN 0 ELSE (CURRENT_DATE - fecha_vencimiento)::int END";

        $sql = "
            SELECT
                COALESCE(SUM(CASE WHEN {$dv} <= 0           THEN saldo_pendiente ELSE 0 END), 0) AS tramo_vigente,
                COALESCE(SUM(CASE WHEN {$dv} BETWEEN 1 AND 30  THEN saldo_pendiente ELSE 0 END), 0) AS tramo_1_30,
                COALESCE(SUM(CASE WHEN {$dv} BETWEEN 31 AND 60 THEN saldo_pendiente ELSE 0 END), 0) AS tramo_31_60,
                COALESCE(SUM(CASE WHEN {$dv} BETWEEN 61 AND 90 THEN saldo_pendiente ELSE 0 END), 0) AS tramo_61_90,
                COALESCE(SUM(CASE WHEN {$dv} > 90            THEN saldo_pendiente ELSE 0 END), 0) AS tramo_mas_90
            FROM saldos_iniciales_cxp
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'vigente'     => (float)($r['tramo_vigente'] ?? 0),
            'tramo_1_30'  => (float)($r['tramo_1_30']   ?? 0),
            'tramo_31_60' => (float)($r['tramo_31_60']  ?? 0),
            'tramo_61_90' => (float)($r['tramo_61_90']  ?? 0),
            'mas_90'      => (float)($r['tramo_mas_90'] ?? 0),
        ];
    }

    /**
     * Historial de pagos de un documento específico.
     */
    public function getHistorialPagos(int $idDoc, string $tipoFuente, int $idEmpresa): array
    {
        $tipoDocEgreso = $tipoFuente === 'LIQUIDACION' ? 'LIQUIDACION' : 'COMPRA';

        $sql = "
            SELECT
                ec.id,
                ec.fecha_emision,
                ec.numero_egreso,
                ec.observaciones,
                ed.monto_pagado,
                u.nombre          AS usuario_nombre,
                efp.nombre        AS forma_pago
            FROM egresos_detalle ed
            INNER JOIN egresos_cabecera    ec  ON ec.id  = ed.id_egreso
            LEFT  JOIN usuarios            u   ON u.id   = ec.created_by
            LEFT  JOIN egresos_pagos       ep  ON ep.id_egreso = ec.id AND ep.eliminado = false
            LEFT  JOIN empresa_formas_pago efp ON efp.id = ep.id_forma_pago
            WHERE ed.tipo_documento              = :tipo_doc
              AND ed.id_referencia_documento      = :id_doc
              AND ec.id_empresa                  = :id_empresa
              AND ec.estado                     != 'anulado'
              AND ec.eliminado                   = false
              AND ed.eliminado                   = false
              AND (efp.id IS NULL OR efp.id_empresa = :id_empresa)
            ORDER BY ec.fecha_emision DESC, ec.id DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':tipo_doc'   => $tipoDocEgreso,
            ':id_doc'     => $idDoc,
            ':id_empresa' => $idEmpresa,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Datos de un documento específico para validar el pago.
     */
    public function getDocumentoParaPago(int $idDoc, string $tipoFuente, int $idEmpresa): ?array
    {
        if ($tipoFuente === 'LIQUIDACION') {
            $sql = "
                SELECT l.id,
                       'LIQUIDACION' AS tipo_fuente,
                       l.id_proveedor,
                       p.razon_social        AS proveedor_nombre,
                       p.identificacion      AS proveedor_ruc,
                       COALESCE(p.email,'')  AS proveedor_email,
                       l.fecha_emision,
                       l.importe_total,
                       CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero_documento,
                       COALESCE((
                           SELECT SUM(ed.monto_pagado)
                           FROM egresos_detalle ed
                           INNER JOIN egresos_cabecera ec ON ec.id=ed.id_egreso
                           WHERE ed.tipo_documento='LIQUIDACION' AND ed.id_referencia_documento=l.id
                             AND ec.estado!='anulado' AND ec.eliminado=false AND ed.eliminado=false
                       ), 0) AS total_pagado,
                       COALESCE((
                           SELECT SUM(r.total_retenido)
                           FROM retencion_compra_cabecera r
                           WHERE r.id_liquidacion=l.id AND r.eliminado=false
                             AND UPPER(r.estado) NOT IN ('ANULADO','BORRADOR','PENDIENTE')
                       ), 0) AS total_retenido
                FROM liquidaciones_cabecera l
                JOIN proveedores p ON p.id=l.id_proveedor
                WHERE l.id=:id AND l.id_empresa=:id_empresa AND l.eliminado=false
            ";
        } else {
            $sql = "
                SELECT c.id,
                       'COMPRA' AS tipo_fuente,
                       c.id_proveedor,
                       p.razon_social        AS proveedor_nombre,
                       p.identificacion      AS proveedor_ruc,
                       COALESCE(p.email,'')  AS proveedor_email,
                       c.fecha_emision,
                       c.importe_total,
                       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero_documento,
                       COALESCE((
                           SELECT SUM(ed.monto_pagado)
                           FROM egresos_detalle ed
                           INNER JOIN egresos_cabecera ec ON ec.id=ed.id_egreso
                           WHERE ed.tipo_documento='COMPRA' AND ed.id_referencia_documento=c.id
                             AND ec.estado!='anulado' AND ec.eliminado=false AND ed.eliminado=false
                       ), 0) AS total_pagado,
                       COALESCE((
                           SELECT SUM(r.total_retenido)
                           FROM retencion_compra_cabecera r
                           WHERE r.id_compra=c.id AND r.eliminado=false
                             AND UPPER(r.estado) NOT IN ('ANULADO','BORRADOR','PENDIENTE')
                       ), 0) AS total_retenido,
                       COALESCE((
                           SELECT SUM(nc.importe_total) FROM compras_cabecera nc
                           WHERE nc.tipo_comprobante='04'
                             AND nc.documento_modificado=CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                             AND nc.id_proveedor=c.id_proveedor AND nc.id_empresa=c.id_empresa AND nc.eliminado=false
                       ), 0) AS total_nc,
                       COALESCE((
                           SELECT SUM(nd.importe_total) FROM compras_cabecera nd
                           WHERE nd.tipo_comprobante='05'
                             AND nd.documento_modificado=CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                             AND nd.id_proveedor=c.id_proveedor AND nd.id_empresa=c.id_empresa AND nd.eliminado=false
                       ), 0) AS total_nd
                FROM compras_cabecera c
                JOIN proveedores p ON p.id=c.id_proveedor
                WHERE c.id=:id AND c.id_empresa=:id_empresa AND c.eliminado=false
                  AND c.tipo_comprobante='01'
            ";
        }

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idDoc, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Calcular saldo
        $saldo = (float)$row['importe_total']
               - (float)$row['total_pagado']
               - (float)($row['total_retenido'] ?? 0)
               - (float)($row['total_nc'] ?? 0)
               + (float)($row['total_nd'] ?? 0);
        $row['saldo'] = $saldo;
        return $row;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CATÁLOGOS
    // ─────────────────────────────────────────────────────────────────────

    public function getPuntosEmision(int $idEmpresa): array
    {
        try {
            $sql = "SELECT p.id AS id_punto, e.codigo AS cod_establecimiento, p.codigo_punto, p.id_establecimiento
                    FROM empresa_punto_emision p
                    JOIN empresa_establecimiento e ON e.id=p.id_establecimiento
                    WHERE p.id_empresa=:id_empresa AND p.eliminado=false AND e.eliminado=false
                    ORDER BY e.codigo, p.codigo_punto";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getPuntoEmisionPorId(int $idPunto, int $idEmpresa): ?array
    {
        try {
            $sql = "SELECT p.id, e.codigo AS establecimiento, p.codigo_punto AS punto, p.id_establecimiento
                    FROM empresa_punto_emision p
                    JOIN empresa_establecimiento e ON e.id=p.id_establecimiento
                    WHERE p.id=:id AND p.id_empresa=:id_empresa AND p.eliminado=false";
            $st = $this->db->prepare($sql);
            $st->execute([':id' => $idPunto, ':id_empresa' => $idEmpresa]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getConceptos(int $idEmpresa): array
    {
        try {
            $sql = "SELECT id, nombre, comportamiento
                    FROM empresa_opciones_ingreso_egreso
                    WHERE id_empresa=:id_empresa AND aplica_egresos=TRUE
                      AND UPPER(estado)='ACTIVO' AND eliminado=FALSE
                    ORDER BY nombre";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getFormasPago(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, tipo FROM empresa_formas_pago
                WHERE id_empresa=:id_empresa AND eliminado=false AND activo=true
                  AND (aplica_en IN ('AMBAS','EGRESO','PAGO') OR aplica_en IS NULL)
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio FROM compras_cabecera
                WHERE id_empresa=:id_empresa AND eliminado=false AND tipo_comprobante='01'
                UNION
                SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int FROM liquidaciones_cabecera
                WHERE id_empresa=:id_empresa2 AND eliminado=false
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_empresa2' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Construye el WHERE extra (aplicado sobre el resultado del UNION)
     * y los parámetros base para empresa + tipo_ambiente.
     */
    private function buildWhereExtra(int $idEmpresa, array $filtros): array
    {
        $params = [
            ':id_empresa'     => $idEmpresa,
            ':id_empresa_ta'  => $idEmpresa,
            ':id_empresa2'    => $idEmpresa,
            ':id_empresa_ta2' => $idEmpresa,
        ];
        $whereExtra = '';

        $estado = $filtros['estado'] ?? 'PENDIENTES';
        if ($estado === 'PENDIENTES') {
            $whereExtra .= " AND d.saldo > 0";
        } elseif ($estado === 'VENCIDAS') {
            $whereExtra .= " AND d.saldo > 0 AND d.fecha_vencimiento::date < CURRENT_DATE";
        } elseif ($estado === 'AL_DIA') {
            $whereExtra .= " AND d.saldo > 0 AND d.fecha_vencimiento::date >= CURRENT_DATE";
        } elseif ($estado === 'PAGADAS') {
            $whereExtra .= " AND d.saldo <= 0";
        }

        if (!empty($filtros['fecha_desde'])) {
            $whereExtra .= " AND d.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $whereExtra .= " AND d.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['id_proveedor'])) {
            $rawProv = is_array($filtros['id_proveedor'])
                ? $filtros['id_proveedor']
                : explode(',', (string)$filtros['id_proveedor']);
            $provs = array_filter(array_map('intval', $rawProv));
            if (!empty($provs)) {
                $in = [];
                foreach (array_values($provs) as $i => $id) {
                    $k = ":prov{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $whereExtra .= " AND d.id_proveedor IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['tipo_fuente'])) {
            $whereExtra .= " AND d.tipo_fuente = :tipo_fuente";
            $params[':tipo_fuente'] = $filtros['tipo_fuente'];
        }

        return [$whereExtra, $params];
    }

    // ─────────────────────────────────────────────────────────────────────
    // SALDOS INICIALES CXP
    // ─────────────────────────────────────────────────────────────────────

    public function getSaldosInicialesCxp(int $idEmpresa, array $filtros = []): array
    {
        $where  = "id_empresa = :id_empresa AND eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['estado']) && $filtros['estado'] !== 'TODOS') {
            $where .= " AND estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['tipo_documento'])) {
            $where .= " AND tipo_documento = :tipo_documento";
            $params[':tipo_documento'] = $filtros['tipo_documento'];
        }
        // Filtro por proveedor (mismo criterio que el listado principal de CxP)
        if (!empty($filtros['id_proveedor'])) {
            $rawProv = is_array($filtros['id_proveedor']) ? $filtros['id_proveedor'] : explode(',', (string)$filtros['id_proveedor']);
            $provs = array_filter(array_map('intval', $rawProv));
            if (!empty($provs)) {
                $in = [];
                foreach (array_values($provs) as $i => $id) {
                    $k = ":siprov{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND id_proveedor IN (" . implode(',', $in) . ")";
            }
        }

        $sql = "SELECT
                    id, tipo_documento, nro_documento, fecha_emision, fecha_vencimiento,
                    ruc_proveedor, nombre_proveedor,
                    CAST(saldo_inicial   AS NUMERIC(16,2)) AS saldo_inicial,
                    CAST(monto_pagado    AS NUMERIC(16,2)) AS monto_pagado,
                    CAST(saldo_pendiente AS NUMERIC(16,2)) AS saldo_pendiente,
                    estado, observaciones,
                    CASE WHEN fecha_vencimiento < CURRENT_DATE AND estado != 'PAGADO'
                         THEN CURRENT_DATE - fecha_vencimiento ELSE 0 END AS dias_vencido
                FROM saldos_iniciales_cxp
                WHERE {$where}
                ORDER BY fecha_emision ASC, nro_documento ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDb(): \PDO
    {
        return $this->db;
    }
}
