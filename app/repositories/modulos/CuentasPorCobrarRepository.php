<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AbonosVentaSql;
use App\repositories\BaseRepository;
use PDO;

class CuentasPorCobrarRepository extends BaseRepository
{
    /** Número de la factura `v` normalizado a 15 dígitos: clave de enlace con nc_aplic / nd_aplic. */
    private string $numV;

    public function __construct()
    {
        parent::__construct('ventas_cabecera');
        $this->numV = AbonosVentaSql::numFactura('v');
    }

    /**
     * CTE que calcula lo cobrado por documento hasta una fecha de corte opcional.
     * Si $fechaHasta es null, incluye todos los cobros (comportamiento en tiempo real).
     * $tipoDoc: 'FACTURA' (facturas de venta) o 'RECIBO' (recibos de venta).
     */
    /**
     * $idEmpresa: filtra por empresa (multiempresa, §4) — sin esto el GROUP BY sumaba
     * los cobros de TODAS las empresas del sistema en cada consulta del reporte (mismo
     * problema encontrado y corregido en ReporteVentasRepository y afines). Se
     * interpola directo igual que getCteNC/getCteND en este mismo archivo (int
     * validado por el tipo del parámetro → interpolación segura).
     */
    private function getCteCobrado(int $idEmpresa, ?string $fechaHasta = null, string $tipoDoc = 'FACTURA'): string
    {
        $filtroFecha = $fechaHasta ? "AND ic2.fecha_emision <= :cobrado_hasta" : '';
        $tipoDoc     = $tipoDoc === 'RECIBO' ? 'RECIBO' : 'FACTURA'; // literal seguro
        return "
            SELECT id2.id_referencia_documento AS id_venta,
                   SUM(id2.monto_cobrado)       AS total_cobrado
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic2
                   ON ic2.id = id2.id_ingreso
            WHERE id2.tipo_documento = '{$tipoDoc}'
              AND ic2.estado    != 'anulado'
              AND ic2.eliminado  = false
              AND ic2.id_empresa = {$idEmpresa}
              {$filtroFecha}
            GROUP BY id2.id_referencia_documento
        ";
    }

    /**
     * CTE que calcula lo retenido por factura hasta una fecha de corte opcional.
     * La regla de enlace (id_venta o num_doc_sustento normalizado a 15 dígitos, con lo retenido
     * por línea cuando una retención sustenta varias facturas) vive en
     * AbonosVentaSql y es la misma que usan Ingresos y Facturas de Venta.
     * $idEmpresa: mismo motivo que getCteCobrado — sin filtrar, sumaba retenciones
     * de todas las empresas.
     */
    private function getCteRetenido(int $idEmpresa, ?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND r.fecha_emision <= :retenido_hasta" : '';
        return AbonosVentaSql::cteRetenidoPorFactura((string)$idEmpresa, $filtroFecha);
    }

    /**
     * CTE que calcula el total de notas de crédito aplicadas hasta una fecha de corte opcional.
     * Columnas: num_norm (documento modificado, normalizado a 15 dígitos) y total_nc.
     */
    private function getCteNC(int $idEmpresa, ?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND n.fecha_emision <= :nc_hasta" : '';
        // $idEmpresa es int validado → interpolación segura. Se filtra por empresa
        // (multiempresa, §4) y por el ambiente actual de la empresa, tolerando NC
        // legacy sin tipo_ambiente (NULL) para no perderlas del cálculo.
        $extra = "AND (n.tipo_ambiente IS NULL
                   OR n.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = {$idEmpresa}))
              {$filtroFecha}";
        return AbonosVentaSql::cteNotasPorFactura('notas_credito_cabecera', 'total_nc', (string)$idEmpresa, $extra);
    }

    /**
     * CTE que calcula el total de notas de débito aplicadas hasta una fecha de corte opcional.
     * A diferencia de la NC (que resta), la ND SUMA al saldo pendiente de la factura:
     * es un cargo adicional al cliente, no una devolución.
     * Columnas: num_norm (documento modificado, normalizado a 15 dígitos) y total_nd.
     */
    private function getCteND(int $idEmpresa, ?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND n.fecha_emision <= :nd_hasta" : '';
        $extra = "AND (n.tipo_ambiente IS NULL
                   OR n.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = {$idEmpresa}))
              {$filtroFecha}";
        return AbonosVentaSql::cteNotasPorFactura('nota_debito_cabecera', 'total_nd', (string)$idEmpresa, $extra);
    }

    /**
     * Extrae fecha_hasta del array de filtros y agrega los parámetros de corte
     * en $params para los CTEs que la necesitan.
     */
    private function aplicarFechaCorteCtEs(array $filtros, array &$params): ?string
    {
        $fechaHasta = $filtros['fecha_hasta'] ?? null;
        if ($fechaHasta) {
            $params[':cobrado_hasta']  = $fechaHasta;
            $params[':retenido_hasta'] = $fechaHasta;
            $params[':nc_hasta']       = $fechaHasta;
            $params[':nd_hasta']       = $fechaHasta;
        }
        return $fechaHasta ?: null;
    }

    /**
     * Listado principal de cuentas por cobrar.
     */
    public function getListado(int $idEmpresa, array $filtros): array
    {
        [$where, $params] = $this->buildWhere($idEmpresa, $filtros);
        $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado($idEmpresa, $fh) . "),
                 retenido AS (" . $this->getCteRetenido($idEmpresa, $fh) . "),
                 nc_aplic AS (" . $this->getCteNC($idEmpresa, $fh) . "),
                 nd_aplic AS (" . $this->getCteND($idEmpresa, $fh) . ")
            SELECT
                v.id,
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                c.id                        AS id_cliente,
                c.nombre                    AS cliente_nombre,
                c.identificacion            AS cliente_ruc,
                COALESCE(c.email,'')        AS cliente_email,
                COALESCE(c.telefono,'')     AS cliente_telefono,
                v.importe_total             AS total,
                COALESCE(cb.total_cobrado, 0)                                                                               AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                                                              AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                                                    AS total_nc,
                COALESCE(nd.total_nd, 0)                                                                                    AS total_nd,
                v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0) AS saldo,
                v.fecha_emision + INTERVAL '1 day' * v.dias_credito AS fecha_vencimiento,
                v.dias_credito,
                (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.num_norm = {$this->numV}
            LEFT JOIN nd_aplic nd ON nd.num_norm = {$this->numV}
            WHERE {$where}
            ORDER BY fecha_vencimiento ASC, v.fecha_emision DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Normaliza el filtro de tipo de documento del listado unificado.
     * Valores: TODOS | FACTURA | RECIBO | SALDO_INICIAL.
     */
    private function getTipoDoc(array $filtros): string
    {
        $t = strtoupper(trim((string)($filtros['tipo_doc'] ?? 'TODOS')));
        return in_array($t, ['FACTURA', 'RECIBO', 'SALDO_INICIAL'], true) ? $t : 'TODOS';
    }

    /**
     * Estadísticas para las tarjetas superiores (respeta el filtro tipo_doc).
     */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        $tipoDoc = $this->getTipoDoc($filtros);
        $r = ['total_facturas' => 0, 'total_saldo' => 0, 'total_vencido' => 0, 'total_al_dia' => 0, 'facturas_vencidas' => 0];

        if (in_array($tipoDoc, ['TODOS', 'FACTURA'], true)) {
            // Para estadísticas, no aplicar filtro de estado pues queremos todos los saldos
            $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
            [$where, $params] = $this->buildWhere($idEmpresa, $filtrosSinEstado);
            $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

            $sql = "
                WITH cobrado  AS (" . $this->getCteCobrado($idEmpresa, $fh) . "),
                     retenido AS (" . $this->getCteRetenido($idEmpresa, $fh) . "),
                     nc_aplic AS (" . $this->getCteNC($idEmpresa, $fh) . "),
                     nd_aplic AS (" . $this->getCteND($idEmpresa, $fh) . ")
                SELECT
                    COUNT(v.id) AS total_facturas,
                    SUM(v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) AS total_saldo,
                    SUM(CASE
                        WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE
                        THEN v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)
                        ELSE 0
                    END) AS total_vencido,
                    SUM(CASE
                        WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date >= CURRENT_DATE
                        THEN v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)
                        ELSE 0
                    END) AS total_al_dia,
                    COUNT(CASE
                        WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE THEN 1
                    END) AS facturas_vencidas
                FROM ventas_cabecera v
                JOIN clientes c ON c.id = v.id_cliente
                LEFT JOIN cobrado  cb ON cb.id_venta = v.id
                LEFT JOIN retenido rt ON rt.id_venta = v.id
                LEFT JOIN nc_aplic nc ON nc.num_norm = {$this->numV}
            LEFT JOIN nd_aplic nd ON nd.num_norm = {$this->numV}
                WHERE {$where}
            ";

            $st = $this->db->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: $r;
        }

        // Sumar los recibos de venta pendientes (mismo filtro de cliente/fechas)
        $rec = in_array($tipoDoc, ['TODOS', 'RECIBO'], true)
            ? $this->getStatsRecibos($idEmpresa, $filtros)
            : ['cnt' => 0, 'total_saldo' => 0, 'total_vencido' => 0, 'total_al_dia' => 0, 'vencidas' => 0];

        // Sumar los saldos iniciales CXC (mismo filtro de cliente)
        $si = in_array($tipoDoc, ['TODOS', 'SALDO_INICIAL'], true)
            ? $this->getStatsSaldosInicialesCxc($idEmpresa, $filtros)
            : ['cnt' => 0, 'total_saldo' => 0, 'total_vencido' => 0, 'total_al_dia' => 0, 'vencidas' => 0];

        return [
            'total_facturas'   => (int)($r['total_facturas']    ?? 0) + $rec['cnt']           + $si['cnt'],
            'total_saldo'      => (float)($r['total_saldo']     ?? 0) + $rec['total_saldo']   + $si['total_saldo'],
            'total_vencido'    => (float)($r['total_vencido']   ?? 0) + $rec['total_vencido'] + $si['total_vencido'],
            'total_al_dia'     => (float)($r['total_al_dia']    ?? 0) + $rec['total_al_dia']  + $si['total_al_dia'],
            'facturas_vencidas'=> (int)($r['facturas_vencidas'] ?? 0) + $rec['vencidas']      + $si['vencidas'],
        ];
    }

    /**
     * Agregados de los saldos iniciales CXC pendientes (pendiente = saldo_inicial
     * - cobrado - retenido, con la retención calculada al vuelo) para sumarlos a
     * las tarjetas. Respeta el filtro de cliente.
     */
    private function getStatsSaldosInicialesCxc(int $idEmpresa, array $filtros): array
    {
        $where  = "s.id_empresa = :si_emp AND s.eliminado = false";
        $params = [':si_emp' => $idEmpresa];

        if (!empty($filtros['id_cliente'])) {
            $raw = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $cli = array_filter(array_map('intval', $raw));
            if (!empty($cli)) {
                $in = [];
                foreach (array_values($cli) as $i => $id) { $k = ":sicc{$i}"; $in[] = $k; $params[$k] = $id; }
                $where .= " AND s.id_cliente IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['fecha_desde'])) { $where .= " AND s.fecha_emision >= :sfd"; $params[':sfd'] = $filtros['fecha_desde']; }
        if (!empty($filtros['fecha_hasta'])) { $where .= " AND s.fecha_emision <= :sfh"; $params[':sfh'] = $filtros['fecha_hasta']; }
        [$fCob, $fRet, $fNc] = $this->corteSaldoInicialCxc($filtros, $params);

        $sql = "
            SELECT
                COUNT(*) FILTER (WHERE sub.pend > 0) AS cnt,
                COALESCE(SUM(CASE WHEN sub.pend > 0 THEN sub.pend ELSE 0 END), 0) AS total_saldo,
                COALESCE(SUM(CASE WHEN sub.pend > 0 AND sub.fecha_vencimiento IS NOT NULL AND sub.fecha_vencimiento < CURRENT_DATE THEN sub.pend ELSE 0 END), 0) AS total_vencido,
                COALESCE(SUM(CASE WHEN sub.pend > 0 AND (sub.fecha_vencimiento IS NULL OR sub.fecha_vencimiento >= CURRENT_DATE) THEN sub.pend ELSE 0 END), 0) AS total_al_dia,
                COUNT(*) FILTER (WHERE sub.pend > 0 AND sub.fecha_vencimiento IS NOT NULL AND sub.fecha_vencimiento < CURRENT_DATE) AS vencidas
            FROM (
                SELECT s.fecha_vencimiento,
                       (s.saldo_inicial - cob.cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0)) AS pend
                FROM saldos_iniciales_cxc s"
                . $this->lateralCobradoSaldoInicial($fCob)
                . $this->lateralRetSaldoInicial($fRet)
                . $this->lateralNcSaldoInicial($fNc) . "
                WHERE {$where}
            ) sub
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
     * Análisis de antigüedad (aging) para el gráfico (respeta el filtro tipo_doc).
     */
    public function getAntiguedad(int $idEmpresa, array $filtros): array
    {
        $tipoDoc = $this->getTipoDoc($filtros);
        $r = ['tramo_vigente' => 0, 'tramo_1_30' => 0, 'tramo_31_60' => 0, 'tramo_61_90' => 0, 'tramo_mas_90' => 0];

        if (in_array($tipoDoc, ['TODOS', 'FACTURA'], true)) {
            $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
            [$where, $params] = $this->buildWhere($idEmpresa, $filtrosSinEstado);
            $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

            $sql = "
                WITH cobrado  AS (" . $this->getCteCobrado($idEmpresa, $fh) . "),
                     retenido AS (" . $this->getCteRetenido($idEmpresa, $fh) . "),
                     nc_aplic AS (" . $this->getCteNC($idEmpresa, $fh) . "),
                     nd_aplic AS (" . $this->getCteND($idEmpresa, $fh) . ")
                SELECT
                    SUM(CASE WHEN dias_vencido BETWEEN 1 AND 30
                        THEN saldo ELSE 0 END) AS tramo_1_30,
                    SUM(CASE WHEN dias_vencido BETWEEN 31 AND 60
                        THEN saldo ELSE 0 END) AS tramo_31_60,
                    SUM(CASE WHEN dias_vencido BETWEEN 61 AND 90
                        THEN saldo ELSE 0 END) AS tramo_61_90,
                    SUM(CASE WHEN dias_vencido > 90
                        THEN saldo ELSE 0 END) AS tramo_mas_90,
                    SUM(CASE WHEN dias_vencido <= 0
                        THEN saldo ELSE 0 END) AS tramo_vigente
                FROM (
                    SELECT
                        v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0) AS saldo,
                        (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
                    FROM ventas_cabecera v
                    JOIN clientes c ON c.id = v.id_cliente
                    LEFT JOIN cobrado  cb ON cb.id_venta = v.id
                    LEFT JOIN retenido rt ON rt.id_venta = v.id
                    LEFT JOIN nc_aplic nc ON nc.num_norm = {$this->numV}
                    LEFT JOIN nd_aplic nd ON nd.num_norm = {$this->numV}
                    WHERE {$where}
                ) sub
            ";

            $st = $this->db->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: $r;
        }

        // Sumar los tramos de los recibos de venta (mismo filtro de cliente/fechas)
        $rec = in_array($tipoDoc, ['TODOS', 'RECIBO'], true)
            ? $this->getAntiguedadRecibos($idEmpresa, $filtros)
            : ['vigente' => 0, 'tramo_1_30' => 0, 'tramo_31_60' => 0, 'tramo_61_90' => 0, 'mas_90' => 0];

        // Sumar los tramos de los saldos iniciales CXC (mismo filtro de cliente)
        $si = in_array($tipoDoc, ['TODOS', 'SALDO_INICIAL'], true)
            ? $this->getAntiguedadSaldosInicialesCxc($idEmpresa, $filtros)
            : ['vigente' => 0, 'tramo_1_30' => 0, 'tramo_31_60' => 0, 'tramo_61_90' => 0, 'mas_90' => 0];

        return [
            'vigente'    => (float)($r['tramo_vigente']  ?? 0) + $rec['vigente']     + $si['vigente'],
            'tramo_1_30' => (float)($r['tramo_1_30']    ?? 0) + $rec['tramo_1_30']  + $si['tramo_1_30'],
            'tramo_31_60'=> (float)($r['tramo_31_60']   ?? 0) + $rec['tramo_31_60'] + $si['tramo_31_60'],
            'tramo_61_90'=> (float)($r['tramo_61_90']   ?? 0) + $rec['tramo_61_90'] + $si['tramo_61_90'],
            'mas_90'     => (float)($r['tramo_mas_90']  ?? 0) + $rec['mas_90']      + $si['mas_90'],
        ];
    }

    /**
     * Tramos de antigüedad de los saldos iniciales CXC pendientes (pendiente
     * neto de retención calculada al vuelo). Respeta el filtro de cliente.
     */
    private function getAntiguedadSaldosInicialesCxc(int $idEmpresa, array $filtros): array
    {
        $where  = "s.id_empresa = :si_emp AND s.eliminado = false";
        $params = [':si_emp' => $idEmpresa];

        if (!empty($filtros['id_cliente'])) {
            $raw = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $cli = array_filter(array_map('intval', $raw));
            if (!empty($cli)) {
                $in = [];
                foreach (array_values($cli) as $i => $id) { $k = ":sica{$i}"; $in[] = $k; $params[$k] = $id; }
                $where .= " AND s.id_cliente IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['fecha_desde'])) { $where .= " AND s.fecha_emision >= :sfd"; $params[':sfd'] = $filtros['fecha_desde']; }
        if (!empty($filtros['fecha_hasta'])) { $where .= " AND s.fecha_emision <= :sfh"; $params[':sfh'] = $filtros['fecha_hasta']; }
        [$fCob, $fRet, $fNc] = $this->corteSaldoInicialCxc($filtros, $params);

        $sql = "
            SELECT
                COALESCE(SUM(CASE WHEN dv <= 0           THEN pend ELSE 0 END), 0) AS tramo_vigente,
                COALESCE(SUM(CASE WHEN dv BETWEEN 1 AND 30  THEN pend ELSE 0 END), 0) AS tramo_1_30,
                COALESCE(SUM(CASE WHEN dv BETWEEN 31 AND 60 THEN pend ELSE 0 END), 0) AS tramo_31_60,
                COALESCE(SUM(CASE WHEN dv BETWEEN 61 AND 90 THEN pend ELSE 0 END), 0) AS tramo_61_90,
                COALESCE(SUM(CASE WHEN dv > 90            THEN pend ELSE 0 END), 0) AS tramo_mas_90
            FROM (
                SELECT (s.saldo_inicial - cob.cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0)) AS pend,
                       CASE WHEN s.fecha_vencimiento IS NULL THEN 0
                            ELSE (CURRENT_DATE - s.fecha_vencimiento)::int END AS dv
                FROM saldos_iniciales_cxc s"
                . $this->lateralCobradoSaldoInicial($fCob)
                . $this->lateralRetSaldoInicial($fRet)
                . $this->lateralNcSaldoInicial($fNc) . "
                WHERE {$where}
            ) sub
            WHERE sub.pend > 0
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

    // ─────────────────────────────────────────────────────────────────────
    // RECIBOS DE VENTA (espejo del listado de facturas; cobros con
    // tipo_documento = 'RECIBO'; sin retenciones ni notas de crédito)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * WHERE del listado de recibos de venta. Un recibo cuenta como CxC salvo
     * que esté anulado o facturado (al facturarlo, la factura hereda el saldo).
     */
    private function buildWhereRecibos(int $idEmpresa, array $filtros): array
    {
        $where = "v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado NOT IN ('anulado','facturado')
              AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_ta)";

        $params = [
            ':id_empresa'    => $idEmpresa,
            ':id_empresa_ta' => $idEmpresa,
        ];

        $saldoExpr = "(v.importe_total - COALESCE(cb.total_cobrado, 0))";

        // Filtro de estado CxC (mismos valores que el listado de facturas)
        $estado = $filtros['estado'] ?? 'PENDIENTES';
        if ($estado === 'PENDIENTES') {
            $where .= " AND {$saldoExpr} > 0";
        } elseif ($estado === 'VENCIDAS') {
            $where .= " AND {$saldoExpr} > 0
                        AND (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE";
        } elseif ($estado === 'AL_DIA') {
            $where .= " AND {$saldoExpr} > 0
                        AND (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date >= CURRENT_DATE";
        } elseif ($estado === 'PAGADAS') {
            $where .= " AND {$saldoExpr} <= 0";
        }
        // TODOS → sin filtro extra

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND v.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND v.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['id_cliente'])) {
            $rawClientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $clientes = array_filter(array_map('intval', $rawClientes));
            if (!empty($clientes)) {
                $in = [];
                foreach (array_values($clientes) as $i => $id) {
                    $k = ":rcli{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND v.id_cliente IN (" . implode(',', $in) . ")";
            }
        }

        return [$where, $params];
    }

    /**
     * Listado de recibos de venta para CxC (mismas columnas que getListado;
     * total_retenido y total_nc siempre 0: el recibo no tiene retenciones ni NC).
     */
    public function getListadoRecibos(int $idEmpresa, array $filtros): array
    {
        [$where, $params] = $this->buildWhereRecibos($idEmpresa, $filtros);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($idEmpresa, $fh, 'RECIBO') . ")
            SELECT
                v.id,
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                c.id                        AS id_cliente,
                c.nombre                    AS cliente_nombre,
                c.identificacion            AS cliente_ruc,
                COALESCE(c.email,'')        AS cliente_email,
                COALESCE(c.telefono,'')     AS cliente_telefono,
                v.importe_total             AS total,
                COALESCE(cb.total_cobrado, 0)                       AS total_cobrado,
                0                                                   AS total_retenido,
                0                                                   AS total_nc,
                v.importe_total - COALESCE(cb.total_cobrado, 0)     AS saldo,
                v.fecha_emision + INTERVAL '1 day' * v.dias_credito AS fecha_vencimiento,
                v.dias_credito,
                (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
            FROM recibos_venta_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
            WHERE {$where}
            ORDER BY fecha_vencimiento ASC, v.fecha_emision DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agregados de los recibos de venta pendientes para las tarjetas superiores.
     */
    private function getStatsRecibos(int $idEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$where, $params] = $this->buildWhereRecibos($idEmpresa, $filtrosSinEstado);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($idEmpresa, $fh, 'RECIBO') . ")
            SELECT
                COUNT(v.id) AS cnt,
                COALESCE(SUM(v.importe_total - COALESCE(cb.total_cobrado, 0)), 0) AS total_saldo,
                COALESCE(SUM(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE
                    THEN v.importe_total - COALESCE(cb.total_cobrado, 0) ELSE 0 END), 0) AS total_vencido,
                COALESCE(SUM(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date >= CURRENT_DATE
                    THEN v.importe_total - COALESCE(cb.total_cobrado, 0) ELSE 0 END), 0) AS total_al_dia,
                COUNT(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE THEN 1 END) AS vencidas
            FROM recibos_venta_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'cnt'           => (int)($r['cnt']            ?? 0),
            'total_saldo'   => (float)($r['total_saldo']  ?? 0),
            'total_vencido' => (float)($r['total_vencido']?? 0),
            'total_al_dia'  => (float)($r['total_al_dia'] ?? 0),
            'vencidas'      => (int)($r['vencidas']       ?? 0),
        ];
    }

    /**
     * Tramos de antigüedad de los recibos de venta pendientes.
     */
    private function getAntiguedadRecibos(int $idEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$where, $params] = $this->buildWhereRecibos($idEmpresa, $filtrosSinEstado);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($idEmpresa, $fh, 'RECIBO') . ")
            SELECT
                COALESCE(SUM(CASE WHEN dias_vencido <= 0              THEN saldo ELSE 0 END), 0) AS tramo_vigente,
                COALESCE(SUM(CASE WHEN dias_vencido BETWEEN 1 AND 30  THEN saldo ELSE 0 END), 0) AS tramo_1_30,
                COALESCE(SUM(CASE WHEN dias_vencido BETWEEN 31 AND 60 THEN saldo ELSE 0 END), 0) AS tramo_31_60,
                COALESCE(SUM(CASE WHEN dias_vencido BETWEEN 61 AND 90 THEN saldo ELSE 0 END), 0) AS tramo_61_90,
                COALESCE(SUM(CASE WHEN dias_vencido > 90              THEN saldo ELSE 0 END), 0) AS tramo_mas_90
            FROM (
                SELECT
                    v.importe_total - COALESCE(cb.total_cobrado, 0) AS saldo,
                    (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
                FROM recibos_venta_cabecera v
                JOIN clientes c ON c.id = v.id_cliente
                LEFT JOIN cobrado cb ON cb.id_venta = v.id
                WHERE {$where}
            ) sub
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
     * Datos de un recibo de venta para validar el cobro (espejo de
     * getFacturaParaCobro). Excluye recibos anulados o ya facturados.
     */
    public function getReciboParaCobro(int $idRecibo, int $idEmpresa): ?array
    {
        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($idEmpresa, null, 'RECIBO') . ")
            SELECT
                v.*,
                c.nombre         AS cliente_nombre,
                c.email          AS cliente_email,
                c.telefono       AS cliente_telefono,
                c.identificacion AS cliente_ruc,
                COALESCE(cb.total_cobrado, 0)                   AS total_cobrado,
                0                                               AS total_retenido,
                0                                               AS total_nc,
                v.importe_total - COALESCE(cb.total_cobrado, 0) AS saldo,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date    AS fecha_vencimiento
            FROM recibos_venta_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
            WHERE v.id         = :id
              AND v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado NOT IN ('anulado','facturado')
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idRecibo, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Historial de cobros de un recibo de venta específico.
     */
    public function getHistorialCobrosRecibo(int $idRecibo, int $idEmpresa): array
    {
        $sql = "
            SELECT
                ic.id,
                ic.fecha_emision,
                ic.numero_ingreso,
                ic.observaciones,
                id2.monto_cobrado,
                u.nombre AS usuario_nombre,
                efp.nombre AS forma_cobro
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic  ON ic.id  = id2.id_ingreso
            LEFT  JOIN usuarios          u   ON u.id   = ic.id_usuario
            LEFT  JOIN ingresos_pagos    ip  ON ip.id_ingreso = ic.id
            LEFT  JOIN empresa_formas_pago efp ON efp.id = ip.id_forma_cobro
            WHERE id2.tipo_documento           = 'RECIBO'
              AND id2.id_referencia_documento  = :id_recibo
              AND ic.id_empresa                = :id_empresa
              AND ic.estado                   != 'anulado'
              AND ic.eliminado                 = false
              AND (efp.id IS NULL OR efp.id_empresa = :id_empresa_fp)
            ORDER BY ic.fecha_emision DESC, ic.id DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id_recibo' => $idRecibo, ':id_empresa' => $idEmpresa, ':id_empresa_fp' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Historial de cobros de una factura específica.
     */
    public function getHistorialCobros(int $idVenta, int $idEmpresa): array
    {
        $sql = "
            SELECT
                ic.id,
                ic.fecha_emision,
                ic.numero_ingreso,
                ic.observaciones,
                id2.monto_cobrado,
                u.nombre AS usuario_nombre,
                efp.nombre AS forma_cobro
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic  ON ic.id  = id2.id_ingreso
            LEFT  JOIN usuarios          u   ON u.id   = ic.id_usuario
            LEFT  JOIN ingresos_pagos    ip  ON ip.id_ingreso = ic.id
            LEFT  JOIN empresa_formas_pago efp ON efp.id = ip.id_forma_cobro
            WHERE id2.tipo_documento           = 'FACTURA'
              AND id2.id_referencia_documento  = :id_venta
              AND ic.id_empresa                = :id_empresa
              AND ic.estado                   != 'anulado'
              AND ic.eliminado                 = false
              AND (efp.id IS NULL OR efp.id_empresa = :id_empresa)
            ORDER BY ic.fecha_emision DESC, ic.id DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id_venta' => $idVenta, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene datos de una factura para validar el cobro.
     */
    public function getFacturaParaCobro(int $idVenta, int $idEmpresa): ?array
    {
        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado($idEmpresa) . "),
                 retenido AS (" . $this->getCteRetenido($idEmpresa) . "),
                 nc_aplic AS (" . $this->getCteNC($idEmpresa) . "),
                 nd_aplic AS (" . $this->getCteND($idEmpresa) . ")
            SELECT
                v.*,
                c.nombre         AS cliente_nombre,
                c.email          AS cliente_email,
                c.telefono       AS cliente_telefono,
                c.identificacion AS cliente_ruc,
                COALESCE(cb.total_cobrado, 0)                                                                                AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                                                               AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                                                     AS total_nc,
                COALESCE(nd.total_nd, 0)                                                                                     AS total_nd,
                v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0) AS saldo,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.num_norm = {$this->numV}
            LEFT JOIN nd_aplic nd ON nd.num_norm = {$this->numV}
            WHERE v.id         = :id
              AND v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado    IN ('autorizado','autorizada')
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idVenta, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Puntos de emisión activos de la empresa (para el select Serie del cobro).
     */
    public function getPuntosEmision(int $idEmpresa): array
    {
        return (new \App\repositories\SecuencialRepository())->getPuntosEmisionSerie($idEmpresa);
    }

    /**
     * Datos de un punto de emisión específico (para construir el número de ingreso).
     */
    public function getPuntoEmisionPorId(int $idPunto, int $idEmpresa): ?array
    {
        return (new \App\repositories\SecuencialRepository())->getPuntoEmisionSerie($idPunto, $idEmpresa);
    }

    /**
     * Conceptos de ingreso activos de la empresa.
     */
    public function getConceptos(int $idEmpresa): array
    {
        try {
            $sql = "SELECT id, nombre, comportamiento
                    FROM empresa_opciones_ingreso_egreso
                    WHERE id_empresa = :id_empresa
                      AND aplica_ingresos = TRUE
                      AND UPPER(estado) = 'ACTIVO'
                      AND eliminado = FALSE
                    ORDER BY nombre ASC";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Formas de cobro activas de la empresa.
     */
    public function getFormasCobro(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, tipo FROM empresa_formas_pago
                WHERE id_empresa = :id_empresa
                  AND eliminado  = false
                  AND activo     = true
                  AND (aplica_en IN ('AMBAS','INGRESO','COBRO') OR aplica_en IS NULL)
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Plantillas de WhatsApp aprobadas de la empresa.
     * Retorna [] si la tabla no existe (módulo no instalado).
     */
    public function getPlantillasWA(int $idEmpresa): array
    {
        try {
            $sql = "SELECT id, nombre, idioma, componentes
                    FROM whatsapp_plantillas
                    WHERE id_empresa   = :id_empresa
                      AND estado_meta  = 'APPROVED'
                      AND eliminado    = false
                    ORDER BY nombre";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Verifica si la empresa tiene WhatsApp configurado.
     * Retorna false si la tabla no existe (módulo no instalado).
     */
    public function tieneWhatsappConfigurado(int $idEmpresa): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM empresa_whatsapp_config
                    WHERE id_empresa       = :id_empresa
                      AND eliminado        = false
                      AND access_token     IS NOT NULL AND access_token     <> ''
                      AND phone_number_id  IS NOT NULL AND phone_number_id  <> ''";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return (int)$st->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Años disponibles con facturas autorizadas.
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                FROM ventas_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND estado IN ('autorizado','autorizada')
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }

    /**
     * Facturas con saldo pendiente para el envío de estados de cuenta
     * (automatizaciones). Una fila por factura, con datos de contacto del cliente.
     * Si $soloVencidas o $diasMin > 0, limita a facturas vencidas.
     */
    public function getFacturasPendientesParaEnvio(int $idEmpresa, bool $soloVencidas, int $diasMin): array
    {
        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado($idEmpresa) . "),
                 retenido AS (" . $this->getCteRetenido($idEmpresa) . "),
                 nc_aplic AS (" . $this->getCteNC($idEmpresa) . "),
                 nd_aplic AS (" . $this->getCteND($idEmpresa) . ")
            SELECT
                v.id,
                v.id_cliente,
                c.nombre                AS cliente_nombre,
                COALESCE(c.email,'')    AS cliente_email,
                COALESCE(c.telefono,'') AS cliente_telefono,
                c.identificacion        AS cliente_ruc,
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date     AS fecha_vencimiento,
                (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido,
                v.importe_total                                                              AS total,
                COALESCE(cb.total_cobrado, 0)                                               AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                              AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                    AS total_nc,
                (v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) AS saldo
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.num_norm = {$this->numV}
            LEFT JOIN nd_aplic nd ON nd.num_norm = {$this->numV}
            WHERE v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado    IN ('autorizado','autorizada')
              AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_ta)
              AND (v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0
        ";

        $params = [':id_empresa' => $idEmpresa, ':id_empresa_ta' => $idEmpresa];

        if ($soloVencidas || $diasMin > 0) {
            $sql .= " AND (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) >= :dmin";
            $params[':dmin'] = max(1, $diasMin);
        }

        $sql .= " ORDER BY c.nombre ASC, v.fecha_emision ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Líneas (ítems) de una factura, para el detalle "por línea".
     */
    public function getLineasFactura(int $idVenta): array
    {
        $sql = "SELECT descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto
                FROM ventas_detalle
                WHERE id_venta = :id
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idVenta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SALDOS INICIALES CXC
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Corte de fecha para los abonos de un saldo inicial CxC (misma regla que
     * los CTEs cobrado/retenido/nc_aplic de las facturas): con "Fecha Hasta",
     * un cobro, retención o NC fechado después del corte NO reduce el saldo,
     * así el saldo inicial sigue pendiente hasta el día anterior al cobro.
     *
     * Devuelve [filtroCobro, filtroRet, filtroNc] para los tres LATERAL y
     * registra los parámetros (placeholders distintos: PDO/pgsql no admite
     * repetirlos). Sin corte, los tres filtros van vacíos.
     */
    private function corteSaldoInicialCxc(array $filtros, array &$params): array
    {
        $fh = !empty($filtros['fecha_hasta']) ? (string)$filtros['fecha_hasta'] : null;
        if ($fh === null) {
            return ['', '', ''];
        }
        $params[':si_cob_hasta'] = $fh;
        $params[':si_ret_hasta'] = $fh;
        $params[':si_nc_hasta']  = $fh;
        return [
            "AND ic.fecha_emision <= :si_cob_hasta",
            "AND r.fecha_emision <= :si_ret_hasta",
            "AND ncc.fecha_emision <= :si_nc_hasta",
        ];
    }

    /**
     * JOIN LATERAL con lo cobrado de un saldo inicial CxC. Alias `cob`, columna
     * `cobrado`. Sin corte devuelve el acumulado guardado (monto_cobrado, que
     * SaldosInicialesRepository::actualizarMontoCobradoCxc deriva de estos
     * mismos ingresos); con corte lo recalcula desde ingresos_detalle solo con
     * los ingresos fechados hasta la fecha.
     */
    private function lateralCobradoSaldoInicial(string $filtroFecha = ''): string
    {
        if ($filtroFecha === '') {
            return "
                LEFT JOIN LATERAL (SELECT s.monto_cobrado AS cobrado) cob ON true";
        }
        return "
                LEFT JOIN LATERAL (
                    SELECT COALESCE(SUM(id2.monto_cobrado), 0) AS cobrado
                    FROM ingresos_detalle id2
                    INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
                    WHERE id2.tipo_documento = 'SALDO_INICIAL'
                      AND id2.id_referencia_documento = s.id
                      AND ic.estado    != 'anulado'
                      AND ic.eliminado  = false
                      {$filtroFecha}
                ) cob ON true";
    }

    /**
     * JOIN LATERAL que suma lo retenido sobre un saldo inicial CxC (retenciones
     * sin id_venta cuyo sustento coincide con el nro_documento del saldo, del
     * mismo cliente). Alias `ret`, columna `retenido`. La guarda NOT EXISTS
     * evita duplicar cuando el número también corresponde a una factura real.
     */
    private function lateralRetSaldoInicial(string $filtroFecha = ''): string
    {
        return "
                LEFT JOIN LATERAL (
                    SELECT SUM(rd.valor_retenido) AS retenido
                    FROM retencion_venta_detalle rd
                    INNER JOIN retencion_venta_cabecera r ON r.id = rd.id_retencion
                    WHERE r.eliminado = false
                      AND r.id_empresa = s.id_empresa
                      AND r.id_venta IS NULL
                      AND r.id_cliente = s.id_cliente
                      AND rd.num_doc_sustento IS NOT NULL
                      AND rd.num_doc_sustento <> ''
                      AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      {$filtroFecha}
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa
                            AND vc.eliminado = false
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                ) ret ON true";
    }

    /**
     * JOIN LATERAL que suma las notas de crédito aplicadas a un saldo inicial CxC
     * (match por número de documento normalizado). Alias `ncsi`, columna `nc_total`.
     * La guarda NOT EXISTS evita duplicar el descuento cuando el número también
     * corresponde a una factura real (esa NC ya la resta el listado de facturas).
     */
    private function lateralNcSaldoInicial(string $filtroFecha = ''): string
    {
        return "
                LEFT JOIN LATERAL (
                    SELECT SUM(ncc.importe_total) AS nc_total
                    FROM notas_credito_cabecera ncc
                    WHERE ncc.eliminado  = false
                      AND ncc.estado    != 'anulado'
                      AND ncc.id_empresa = s.id_empresa
                      AND regexp_replace(ncc.num_doc_modificado, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      {$filtroFecha}
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa
                            AND vc.eliminado = false
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                ) ncsi ON true";
    }

    public function getSaldosInicialesCxc(int $idEmpresa, array $filtros = []): array
    {
        // Pendiente real = saldo_inicial - cobrado - retenido - NC. Lo retenido y
        // las NC se calculan al vuelo (igual que en las facturas normales); no se
        // almacenan en el saldo inicial. Con "Fecha Hasta", los tres abonos se
        // cortan a esa fecha (ver corteSaldoInicialCxc).
        $pend     = '(s.saldo_inicial - cob.cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0))';
        $aplicado = '(cob.cobrado + COALESCE(ret.retenido, 0) + COALESCE(ncsi.nc_total, 0))';

        $where  = "s.id_empresa = :id_empresa AND s.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];
        [$fCob, $fRet, $fNc] = $this->corteSaldoInicialCxc($filtros, $params);

        if (!empty($filtros['estado']) && $filtros['estado'] !== 'TODOS') {
            if ($filtros['estado'] === 'PAGADO') {
                $where .= " AND {$pend} <= 0";
            } elseif ($filtros['estado'] === 'PARCIAL') {
                $where .= " AND {$pend} > 0 AND {$aplicado} > 0";
            } elseif ($filtros['estado'] === 'PENDIENTE') {
                $where .= " AND {$pend} > 0 AND {$aplicado} <= 0";
            }
        }

        // Filtro por cliente (mismo criterio que el listado principal de CxC)
        if (!empty($filtros['id_cliente'])) {
            $rawClientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $clientes = array_filter(array_map('intval', $rawClientes));
            if (!empty($clientes)) {
                $in = [];
                foreach (array_values($clientes) as $i => $id) {
                    $k = ":sicli{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND s.id_cliente IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['fecha_desde'])) { $where .= " AND s.fecha_emision >= :sfd"; $params[':sfd'] = $filtros['fecha_desde']; }
        if (!empty($filtros['fecha_hasta'])) { $where .= " AND s.fecha_emision <= :sfh"; $params[':sfh'] = $filtros['fecha_hasta']; }

        $sql = "SELECT
                    s.id, s.nro_documento, s.fecha_emision, s.fecha_vencimiento,
                    s.ruc_cliente, s.nombre_cliente,
                    CAST(s.saldo_inicial          AS NUMERIC(16,2)) AS saldo_inicial,
                    CAST(cob.cobrado              AS NUMERIC(16,2)) AS monto_cobrado,
                    CAST(COALESCE(ret.retenido,0) AS NUMERIC(16,2)) AS monto_retenido,
                    CAST(COALESCE(ncsi.nc_total,0) AS NUMERIC(16,2)) AS monto_nc,
                    CAST({$pend}                  AS NUMERIC(16,2)) AS saldo_pendiente,
                    CASE
                        WHEN {$pend} <= 0     THEN 'PAGADO'
                        WHEN {$aplicado} > 0  THEN 'PARCIAL'
                        ELSE 'PENDIENTE'
                    END AS estado,
                    s.observaciones,
                    CASE WHEN s.fecha_vencimiento < CURRENT_DATE AND {$pend} > 0
                         THEN CURRENT_DATE - s.fecha_vencimiento ELSE 0 END AS dias_vencido
                FROM saldos_iniciales_cxc s"
                . $this->lateralCobradoSaldoInicial($fCob)
                . $this->lateralRetSaldoInicial($fRet)
                . $this->lateralNcSaldoInicial($fNc) . "
                WHERE {$where}
                ORDER BY s.fecha_emision ASC, s.nro_documento ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(int $idEmpresa, array $filtros): array
    {
        $where = "v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado    IN ('autorizado','autorizada')
              AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_ta)";

        $params = [
            ':id_empresa'    => $idEmpresa,
            ':id_empresa_ta' => $idEmpresa,
        ];

        // Filtro de estado CxC
        $estado = $filtros['estado'] ?? 'PENDIENTES';
        if ($estado === 'PENDIENTES') {
            $where .= " AND (v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0";
        } elseif ($estado === 'VENCIDAS') {
            $where .= " AND (v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0
                        AND (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE";
        } elseif ($estado === 'AL_DIA') {
            $where .= " AND (v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0
                        AND (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date >= CURRENT_DATE";
        } elseif ($estado === 'PAGADAS') {
            $where .= " AND (v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) <= 0";
        }
        // TODOS → sin filtro extra

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND v.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND v.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['id_cliente'])) {
            $rawClientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $clientes = array_filter(array_map('intval', $rawClientes));
            if (!empty($clientes)) {
                $in = [];
                foreach (array_values($clientes) as $i => $id) {
                    $k = ":cli{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND v.id_cliente IN (" . implode(',', $in) . ")";
            }
        }

        return [$where, $params];
    }
}
