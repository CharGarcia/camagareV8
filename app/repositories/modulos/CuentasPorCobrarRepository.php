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

    // ─────────────────────────────────────────────────────────────────────
    // ALCANCE POR EMPRESA (una empresa o varios establecimientos del mismo RUC)
    //
    // Todos los listados/agregados reciben `int|array $idsEmpresa`: la empresa
    // activa (int, comportamiento normal) o la lista de establecimientos del
    // grupo RUC cuando la matriz pide el consolidado (el controller la resuelve
    // con EmpresaRepository::getIdsConsolidadoDesdeMatriz, nunca el cliente).
    // ─────────────────────────────────────────────────────────────────────

    /** Normaliza el alcance a lista de ids enteros positivos, sin repetidos y nunca vacía. */
    private function idsEmpresa(int|array $idsEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $idsEmpresa), static fn ($i) => $i > 0)));
        if (!$ids) {
            throw new \InvalidArgumentException('Cuentas por Cobrar: id_empresa requerido.');
        }
        return $ids;
    }

    /** Lista para interpolar en `IN (...)` (ids ya validados como enteros por idsEmpresa()). */
    private function sqlIn(array $ids): string
    {
        return implode(',', $ids);
    }

    /** Expresión `ANY(ARRAY[...])` para los helpers de AbonosVentaSql, que comparan con `=`. */
    private function sqlAny(array $ids): string
    {
        return 'ANY(ARRAY[' . $this->sqlIn($ids) . '])';
    }

    /**
     * Placeholders `:prefijo0,:prefijo1,…` para un IN con PDO (pgsql no admite repetir un
     * placeholder, por eso cada IN lleva su prefijo). Registra los valores en $params.
     */
    private function phIn(array $ids, string $prefijo, array &$params): string
    {
        $ph = [];
        foreach (array_values($ids) as $i => $id) {
            $k = ":{$prefijo}{$i}";
            $ph[] = $k;
            $params[$k] = $id;
        }
        return implode(',', $ph);
    }

    /**
     * El documento pertenece al ambiente actual de SU PROPIA empresa. Correlacionada por fila
     * (no por la empresa activa): en el consolidado cada establecimiento puede estar en un
     * ambiente distinto y debe filtrarse contra el suyo.
     */
    private function condAmbiente(string $alias): string
    {
        return "{$alias}.tipo_ambiente = (SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) FROM empresas e WHERE e.id = {$alias}.id_empresa)";
    }

    /**
     * Columnas que identifican el establecimiento dueño de la fila (badge en el consolidado).
     * `id_empresa` sale del documento ($aliasDoc), no de `empresas`: el JOIN a empresas es
     * LEFT para que un documento nunca desaparezca del listado por su empresa.
     */
    private function colsEstablecimiento(string $aliasDoc, string $aliasEmp = 'emp'): string
    {
        return "{$aliasDoc}.id_empresa AS id_empresa,
                COALESCE({$aliasEmp}.establecimiento, '') AS establecimiento,
                COALESCE(NULLIF({$aliasEmp}.nombre_comercial, ''), {$aliasEmp}.nombre, '') AS empresa_nombre";
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
    private function getCteCobrado(array $idsEmpresa, ?string $fechaHasta = null, string $tipoDoc = 'FACTURA'): string
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
              AND ic2.id_empresa IN ({$this->sqlIn($idsEmpresa)})
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
    private function getCteRetenido(array $idsEmpresa, ?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND r.fecha_emision <= :retenido_hasta" : '';
        // El helper compara `r.id_empresa = {expr}`; con ANY(ARRAY[...]) cubre uno o varios
        // establecimientos. El enlace a la factura ya exige vc.id_empresa = r.id_empresa.
        return AbonosVentaSql::cteRetenidoPorFactura($this->sqlAny($idsEmpresa), $filtroFecha);
    }

    /**
     * CTE que calcula el total de notas de crédito aplicadas hasta una fecha de corte opcional.
     * Columnas: num_norm (documento modificado, normalizado a 15 dígitos) y total_nc.
     */
    private function getCteNC(array $idsEmpresa, ?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND n.fecha_emision <= :nc_hasta" : '';
        // Ids enteros validados → interpolación segura. Se filtra por empresa (multiempresa,
        // §4) y por el ambiente actual de la empresa DUEÑA de la nota (correlacionado por
        // fila, ver condAmbiente), tolerando NC legacy sin tipo_ambiente (NULL) para no
        // perderlas del cálculo. porEmpresa=true: el CTE sale con id_empresa para enlazar
        // por (empresa, número) y que la NC de un establecimiento no descuente la factura
        // de otro con el mismo número.
        $extra = "AND (n.tipo_ambiente IS NULL OR {$this->condAmbiente('n')})
              {$filtroFecha}";
        return AbonosVentaSql::cteNotasPorFactura('notas_credito_cabecera', 'total_nc', $this->sqlAny($idsEmpresa), $extra, true);
    }

    /**
     * CTE que calcula el total de notas de débito aplicadas hasta una fecha de corte opcional.
     * A diferencia de la NC (que resta), la ND SUMA al saldo pendiente de la factura:
     * es un cargo adicional al cliente, no una devolución.
     * Columnas: num_norm (documento modificado, normalizado a 15 dígitos) y total_nd.
     */
    private function getCteND(array $idsEmpresa, ?string $fechaHasta = null): string
    {
        $filtroFecha = $fechaHasta ? "AND n.fecha_emision <= :nd_hasta" : '';
        $extra = "AND (n.tipo_ambiente IS NULL OR {$this->condAmbiente('n')})
              {$filtroFecha}";
        return AbonosVentaSql::cteNotasPorFactura('nota_debito_cabecera', 'total_nd', $this->sqlAny($idsEmpresa), $extra, true);
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
    public function getListado(int|array $idsEmpresa, array $filtros): array
    {
        $ids = $this->idsEmpresa($idsEmpresa);
        [$where, $params] = $this->buildWhere($ids, $filtros);
        $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado($ids, $fh) . "),
                 retenido AS (" . $this->getCteRetenido($ids, $fh) . "),
                 nc_aplic AS (" . $this->getCteNC($ids, $fh) . "),
                 nd_aplic AS (" . $this->getCteND($ids, $fh) . ")
            SELECT
                v.id,
                {$this->colsEstablecimiento('v')},
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                c.id                        AS id_cliente,
                c.nombre                    AS cliente_nombre,
                c.identificacion            AS cliente_ruc,
                COALESCE(c.email,'')        AS cliente_email,
                COALESCE(c.telefono,'')     AS cliente_telefono,
                COALESCE(ven.nombre,'')     AS vendedor_nombre,
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
            LEFT JOIN empresas emp ON emp.id = v.id_empresa
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN vendedores ven ON ven.id = v.id_vendedor
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.id_empresa = v.id_empresa AND nc.num_norm = {$this->numV}
            LEFT JOIN nd_aplic nd ON nd.id_empresa = v.id_empresa AND nd.num_norm = {$this->numV}
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
     * Indica si los saldos iniciales entran en el cálculo con los filtros dados.
     * Los saldos iniciales no tienen vendedor (saldos_iniciales_cxc no guarda
     * id_vendedor), así que cuando se filtra por un vendedor concreto se excluyen
     * en lugar de mostrarse como si pertenecieran a todos. La misma regla la
     * aplica el controller al armar el listado unificado.
     */
    public function incluyeSaldosIniciales(array $filtros): bool
    {
        // Tampoco aplican con el filtro Producto: un saldo inicial no tiene líneas de detalle.
        return in_array($this->getTipoDoc($filtros), ['TODOS', 'SALDO_INICIAL'], true)
            && empty($filtros['id_vendedor'])
            && !$this->tieneFiltroProducto($filtros);
    }

    /**
     * Estadísticas para las tarjetas superiores (respeta el filtro tipo_doc).
     */
    public function getEstadisticas(int|array $idsEmpresa, array $filtros): array
    {
        $ids     = $this->idsEmpresa($idsEmpresa);
        $tipoDoc = $this->getTipoDoc($filtros);
        $r = ['total_facturas' => 0, 'total_saldo' => 0, 'total_vencido' => 0, 'total_al_dia' => 0, 'facturas_vencidas' => 0];

        if (in_array($tipoDoc, ['TODOS', 'FACTURA'], true)) {
            // Para estadísticas, no aplicar filtro de estado pues queremos todos los saldos
            $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
            [$where, $params] = $this->buildWhere($ids, $filtrosSinEstado);
            $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

            $sql = "
                WITH cobrado  AS (" . $this->getCteCobrado($ids, $fh) . "),
                     retenido AS (" . $this->getCteRetenido($ids, $fh) . "),
                     nc_aplic AS (" . $this->getCteNC($ids, $fh) . "),
                     nd_aplic AS (" . $this->getCteND($ids, $fh) . ")
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
                LEFT JOIN nc_aplic nc ON nc.id_empresa = v.id_empresa AND nc.num_norm = {$this->numV}
                LEFT JOIN nd_aplic nd ON nd.id_empresa = v.id_empresa AND nd.num_norm = {$this->numV}
                WHERE {$where}
            ";

            $st = $this->db->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: $r;
        }

        // Sumar los recibos de venta pendientes (mismo filtro de cliente/fechas)
        $rec = in_array($tipoDoc, ['TODOS', 'RECIBO'], true)
            ? $this->getStatsRecibos($ids, $filtros)
            : ['cnt' => 0, 'total_saldo' => 0, 'total_vencido' => 0, 'total_al_dia' => 0, 'vencidas' => 0];

        // Sumar los saldos iniciales CXC (mismo filtro de cliente; sin vendedor no aplican)
        $si = $this->incluyeSaldosIniciales($filtros)
            ? $this->getStatsSaldosInicialesCxc($ids, $filtros)
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
    private function getStatsSaldosInicialesCxc(array $idsEmpresa, array $filtros): array
    {
        $params = [];
        $where  = "s.id_empresa IN ({$this->phIn($idsEmpresa, 'si_emp', $params)}) AND s.eliminado = false";

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
    public function getAntiguedad(int|array $idsEmpresa, array $filtros): array
    {
        $ids     = $this->idsEmpresa($idsEmpresa);
        $tipoDoc = $this->getTipoDoc($filtros);
        $r = ['tramo_vigente' => 0, 'tramo_1_30' => 0, 'tramo_31_60' => 0, 'tramo_61_90' => 0, 'tramo_mas_90' => 0];

        if (in_array($tipoDoc, ['TODOS', 'FACTURA'], true)) {
            $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
            [$where, $params] = $this->buildWhere($ids, $filtrosSinEstado);
            $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

            $sql = "
                WITH cobrado  AS (" . $this->getCteCobrado($ids, $fh) . "),
                     retenido AS (" . $this->getCteRetenido($ids, $fh) . "),
                     nc_aplic AS (" . $this->getCteNC($ids, $fh) . "),
                     nd_aplic AS (" . $this->getCteND($ids, $fh) . ")
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
                    LEFT JOIN nc_aplic nc ON nc.id_empresa = v.id_empresa AND nc.num_norm = {$this->numV}
                    LEFT JOIN nd_aplic nd ON nd.id_empresa = v.id_empresa AND nd.num_norm = {$this->numV}
                    WHERE {$where}
                ) sub
            ";

            $st = $this->db->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: $r;
        }

        // Sumar los tramos de los recibos de venta (mismo filtro de cliente/fechas)
        $rec = in_array($tipoDoc, ['TODOS', 'RECIBO'], true)
            ? $this->getAntiguedadRecibos($ids, $filtros)
            : ['vigente' => 0, 'tramo_1_30' => 0, 'tramo_31_60' => 0, 'tramo_61_90' => 0, 'mas_90' => 0];

        // Sumar los tramos de los saldos iniciales CXC (mismo filtro de cliente; sin vendedor no aplican)
        $si = $this->incluyeSaldosIniciales($filtros)
            ? $this->getAntiguedadSaldosInicialesCxc($ids, $filtros)
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
    private function getAntiguedadSaldosInicialesCxc(array $idsEmpresa, array $filtros): array
    {
        $params = [];
        $where  = "s.id_empresa IN ({$this->phIn($idsEmpresa, 'si_emp', $params)}) AND s.eliminado = false";

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
    private function buildWhereRecibos(array $idsEmpresa, array $filtros): array
    {
        $params = [];
        $where = "v.id_empresa IN ({$this->phIn($idsEmpresa, 'emp', $params)})
              AND v.eliminado  = false
              AND v.estado NOT IN ('anulado','facturado')
              AND {$this->condAmbiente('v')}";

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
        if (!empty($filtros['id_vendedor'])) {
            $where .= " AND v.id_vendedor = :id_vendedor";
            $params[':id_vendedor'] = (int)$filtros['id_vendedor'];
        }
        // Filtro Producto sobre las líneas del recibo (mismo criterio que las facturas)
        $where .= $this->condProducto('recibos_venta_detalle', 'id_recibo', 'v', $filtros, $params, 'rp');

        return [$where, $params];
    }

    /**
     * Listado de recibos de venta para CxC (mismas columnas que getListado;
     * total_retenido y total_nc siempre 0: el recibo no tiene retenciones ni NC).
     */
    public function getListadoRecibos(int|array $idsEmpresa, array $filtros): array
    {
        $ids = $this->idsEmpresa($idsEmpresa);
        [$where, $params] = $this->buildWhereRecibos($ids, $filtros);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($ids, $fh, 'RECIBO') . ")
            SELECT
                v.id,
                {$this->colsEstablecimiento('v')},
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                c.id                        AS id_cliente,
                c.nombre                    AS cliente_nombre,
                c.identificacion            AS cliente_ruc,
                COALESCE(c.email,'')        AS cliente_email,
                COALESCE(c.telefono,'')     AS cliente_telefono,
                COALESCE(ven.nombre,'')     AS vendedor_nombre,
                v.importe_total             AS total,
                COALESCE(cb.total_cobrado, 0)                       AS total_cobrado,
                0                                                   AS total_retenido,
                0                                                   AS total_nc,
                v.importe_total - COALESCE(cb.total_cobrado, 0)     AS saldo,
                v.fecha_emision + INTERVAL '1 day' * v.dias_credito AS fecha_vencimiento,
                v.dias_credito,
                (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
            FROM recibos_venta_cabecera v
            LEFT JOIN empresas emp ON emp.id = v.id_empresa
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN vendedores ven ON ven.id = v.id_vendedor
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
    private function getStatsRecibos(array $idsEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$where, $params] = $this->buildWhereRecibos($idsEmpresa, $filtrosSinEstado);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($idsEmpresa, $fh, 'RECIBO') . ")
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
    private function getAntiguedadRecibos(array $idsEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$where, $params] = $this->buildWhereRecibos($idsEmpresa, $filtrosSinEstado);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($idsEmpresa, $fh, 'RECIBO') . ")
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
            WITH cobrado AS (" . $this->getCteCobrado([$idEmpresa], null, 'RECIBO') . ")
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
            WITH cobrado  AS (" . $this->getCteCobrado([$idEmpresa]) . "),
                 retenido AS (" . $this->getCteRetenido([$idEmpresa]) . "),
                 nc_aplic AS (" . $this->getCteNC([$idEmpresa]) . "),
                 nd_aplic AS (" . $this->getCteND([$idEmpresa]) . ")
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
            LEFT JOIN nc_aplic nc ON nc.id_empresa = v.id_empresa AND nc.num_norm = {$this->numV}
            LEFT JOIN nd_aplic nd ON nd.id_empresa = v.id_empresa AND nd.num_norm = {$this->numV}
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
            WITH cobrado  AS (" . $this->getCteCobrado([$idEmpresa]) . "),
                 retenido AS (" . $this->getCteRetenido([$idEmpresa]) . "),
                 nc_aplic AS (" . $this->getCteNC([$idEmpresa]) . "),
                 nd_aplic AS (" . $this->getCteND([$idEmpresa]) . ")
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
            LEFT JOIN nc_aplic nc ON nc.id_empresa = v.id_empresa AND nc.num_norm = {$this->numV}
            LEFT JOIN nd_aplic nd ON nd.id_empresa = v.id_empresa AND nd.num_norm = {$this->numV}
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

    public function getSaldosInicialesCxc(int|array $idsEmpresa, array $filtros = []): array
    {
        // Pendiente real = saldo_inicial - cobrado - retenido - NC. Lo retenido y
        // las NC se calculan al vuelo (igual que en las facturas normales); no se
        // almacenan en el saldo inicial. Con "Fecha Hasta", los tres abonos se
        // cortan a esa fecha (ver corteSaldoInicialCxc).
        $pend     = '(s.saldo_inicial - cob.cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0))';
        $aplicado = '(cob.cobrado + COALESCE(ret.retenido, 0) + COALESCE(ncsi.nc_total, 0))';

        $params = [];
        $where  = "s.id_empresa IN ({$this->phIn($this->idsEmpresa($idsEmpresa), 'si_emp', $params)}) AND s.eliminado = false";
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
                    s.id, s.id_cliente, s.nro_documento, s.fecha_emision, s.fecha_vencimiento,
                    s.ruc_cliente, s.nombre_cliente,
                    {$this->colsEstablecimiento('s')},
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
                FROM saldos_iniciales_cxc s
                LEFT JOIN empresas emp ON emp.id = s.id_empresa"
                . $this->lateralCobradoSaldoInicial($fCob)
                . $this->lateralRetSaldoInicial($fRet)
                . $this->lateralNcSaldoInicial($fNc) . "
                WHERE {$where}
                ORDER BY s.fecha_emision ASC, s.nro_documento ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(array $idsEmpresa, array $filtros): array
    {
        $params = [];
        $where = "v.id_empresa IN ({$this->phIn($idsEmpresa, 'emp', $params)})
              AND v.eliminado  = false
              AND v.estado    IN ('autorizado','autorizada')
              AND {$this->condAmbiente('v')}";

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
        if (!empty($filtros['id_vendedor'])) {
            $where .= " AND v.id_vendedor = :id_vendedor";
            $params[':id_vendedor'] = (int)$filtros['id_vendedor'];
        }
        // Filtro Producto: facturas que tengan al menos una línea cuyo nombre o código
        // contenga el texto (mismo criterio que el Reporte de Ventas, producto_texto).
        $where .= $this->condProducto('ventas_detalle', 'id_venta', 'v', $filtros, $params, 'fp');

        return [$where, $params];
    }

    /**
     * Condición EXISTS sobre las líneas del documento para el filtro Producto (nombre o
     * código de la línea, ILIKE). Devuelve '' si no hay texto. Los saldos iniciales no tienen
     * líneas: cuando este filtro está activo quedan fuera (ver incluyeSaldosIniciales).
     */
    private function condProducto(string $tablaDetalle, string $fk, string $aliasCab, array $filtros, array &$params, string $prefijo): string
    {
        $sql = '';
        // (a) Productos elegidos en el buscador (ids; en consolidado ya vienen expandidos por código)
        $ids = $filtros['id_producto'] ?? '';
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : explode(',', (string)$ids)))));
        if ($ids) {
            $in = $this->phIn($ids, "{$prefijo}_id", $params);
            $sql .= " AND EXISTS (
                    SELECT 1 FROM {$tablaDetalle} dpi
                    WHERE dpi.{$fk} = {$aliasCab}.id AND dpi.id_producto IN ({$in})
                )";
        }
        // (b) Texto libre escrito sin elegir de la lista: nombre o código de la línea
        $txt = trim((string)($filtros['producto'] ?? ''));
        if ($txt !== '') {
            $params[":{$prefijo}_txt1"] = '%' . $txt . '%';
            $params[":{$prefijo}_txt2"] = '%' . $txt . '%';
            $sql .= " AND EXISTS (
                    SELECT 1 FROM {$tablaDetalle} dp
                    WHERE dp.{$fk} = {$aliasCab}.id
                      AND (dp.descripcion ILIKE :{$prefijo}_txt1 OR dp.codigo_principal ILIKE :{$prefijo}_txt2)
                )";
        }
        return $sql;
    }

    /** ¿Hay filtro de producto activo (ids elegidos o texto libre)? */
    public function tieneFiltroProducto(array $filtros): bool
    {
        $ids = $filtros['id_producto'] ?? '';
        $ids = array_filter(array_map('intval', is_array($ids) ? $ids : explode(',', (string)$ids)));
        return !empty($ids) || trim((string)($filtros['producto'] ?? '')) !== '';
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRODUCTOS (buscador del filtro)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Buscador del filtro Producto (nombre o código). Con varios establecimientos devuelve
     * UNA fila por código (prefiere la de la empresa activa); el filtro luego se expande a
     * los productos hermanos con expandirProductosPorCodigo().
     */
    public function buscarProductos(int|array $idsEmpresa, int $idEmpresaActual, string $q, int $limite = 15): array
    {
        $ids = $this->idsEmpresa($idsEmpresa);
        $q   = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':q' => '%' . mb_strtolower($q) . '%', ':q2' => '%' . mb_strtolower($q) . '%', ':actual' => $idEmpresaActual];
        $inEmp  = $this->phIn($ids, 'bpr', $params);
        $sql = "SELECT id, COALESCE(codigo, '') AS codigo, nombre, id_empresa
                FROM productos
                WHERE id_empresa IN ({$inEmp})
                  AND eliminado = false
                  AND (LOWER(nombre) LIKE :q OR LOWER(COALESCE(codigo, '')) LIKE :q2)
                ORDER BY (id_empresa = :actual) DESC, nombre
                LIMIT " . max(15, $limite * count($ids));
        $st = $this->db->prepare($sql);
        $st->execute($params);

        $out = [];
        $vistos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $clave = trim((string)$p['codigo']) !== '' ? 'c:' . trim((string)$p['codigo']) : 'id:' . (int)$p['id'];
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = ['id' => (int)$p['id'], 'codigo' => (string)$p['codigo'], 'nombre' => (string)$p['nombre']];
            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }

    /**
     * Consolidado: `productos` es por establecimiento; expande los ids elegidos a los
     * productos hermanos con el MISMO código en las empresas del alcance.
     */
    public function expandirProductosPorCodigo(array $idsProducto, int|array $idsEmpresa): array
    {
        $idsProducto = array_values(array_unique(array_filter(array_map('intval', $idsProducto))));
        if (!$idsProducto) {
            return [];
        }
        $params = [];
        $inP = $this->phIn($idsProducto, 'xpr', $params);
        $inE = $this->phIn($this->idsEmpresa($idsEmpresa), 'xpe', $params);
        $st = $this->db->prepare("SELECT DISTINCT p2.id
                                  FROM productos p1
                                  JOIN productos p2 ON p2.codigo = p1.codigo AND p2.eliminado = false AND p2.id_empresa IN ({$inE})
                                  WHERE p1.id IN ({$inP}) AND COALESCE(TRIM(p1.codigo), '') <> ''");
        $st->execute($params);
        return array_values(array_unique(array_merge($idsProducto, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)))));
    }

    /**
     * Vista "Por producto": líneas de producto de los documentos del listado, una fila por
     * documento y producto (varias líneas del mismo producto en un documento se suman), con
     * cantidad y valor (base de la línea + sus impuestos). Facturas desde ventas_detalle y
     * recibos desde recibos_venta_detalle; los saldos iniciales no tienen líneas. Si el
     * listado ya está filtrado por producto (ids o texto), solo devuelve esas líneas, para que
     * la vista muestre exactamente la cartera de los productos elegidos.
     *
     * @return array<int,array{origen:string,id_doc:int,id_producto:?int,codigo:string,nombre:string,cantidad:float,valor:float}>
     */
    public function getLineasProductoPorDocumentos(array $idsFacturas, array $idsRecibos, array $filtros = []): array
    {
        $idsFacturas = array_values(array_unique(array_filter(array_map('intval', $idsFacturas))));
        $idsRecibos  = array_values(array_unique(array_filter(array_map('intval', $idsRecibos))));
        $out = [];
        $fuentes = [
            ['FACTURA', 'ventas_detalle',        'id_venta',  'ventas_detalle_impuestos',        'id_venta_detalle',  $idsFacturas, 'lf'],
            ['RECIBO',  'recibos_venta_detalle', 'id_recibo', 'recibos_venta_detalle_impuestos', 'id_recibo_detalle', $idsRecibos,  'lr'],
        ];
        foreach ($fuentes as [$origen, $tabla, $fk, $tablaImp, $fkImp, $ids, $pref]) {
            if (!$ids) {
                continue;
            }
            $params = [];
            $inDoc  = $this->phIn($ids, "{$pref}_doc", $params);
            $where  = "d.{$fk} IN ({$inDoc})";
            $idsProd = $filtros['id_producto'] ?? '';
            $idsProd = array_values(array_unique(array_filter(array_map('intval', is_array($idsProd) ? $idsProd : explode(',', (string)$idsProd)))));
            if ($idsProd) {
                $where .= " AND d.id_producto IN (" . $this->phIn($idsProd, "{$pref}_prod", $params) . ")";
            }
            $txt = trim((string)($filtros['producto'] ?? ''));
            if ($txt !== '') {
                $params[":{$pref}_t1"] = '%' . $txt . '%';
                $params[":{$pref}_t2"] = '%' . $txt . '%';
                $where .= " AND (d.descripcion ILIKE :{$pref}_t1 OR d.codigo_principal ILIKE :{$pref}_t2)";
            }
            $sql = "SELECT '{$origen}' AS origen,
                           d.{$fk} AS id_doc,
                           d.id_producto,
                           COALESCE(NULLIF(TRIM(d.codigo_principal), ''), '') AS codigo,
                           COALESCE(NULLIF(TRIM(d.descripcion), ''), '(sin descripción)') AS nombre,
                           SUM(d.cantidad) AS cantidad,
                           SUM(d.precio_total_sin_impuesto
                               + COALESCE((SELECT SUM(i.valor) FROM {$tablaImp} i WHERE i.{$fkImp} = d.id), 0)) AS valor
                    FROM {$tabla} d
                    WHERE {$where}
                    GROUP BY 1, 2, 3, 4, 5";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $out[] = [
                    'origen'      => $origen,
                    'id_doc'      => (int)$l['id_doc'],
                    'id_producto' => $l['id_producto'] !== null ? (int)$l['id_producto'] : null,
                    'codigo'      => (string)$l['codigo'],
                    'nombre'      => (string)$l['nombre'],
                    'cantidad'    => (float)$l['cantidad'],
                    'valor'       => (float)$l['valor'],
                ];
            }
        }
        return $out;
    }

    /** Código y nombre de varios productos (para describir el filtro en PDF/Excel): id => [codigo, nombre]. */
    public function getProductosPorIds(array $idsProducto, int|array $idsEmpresa): array
    {
        $idsProducto = array_values(array_unique(array_filter(array_map('intval', $idsProducto))));
        if (!$idsProducto) {
            return [];
        }
        $params = [];
        $inP = $this->phIn($idsProducto, 'npr', $params);
        $inE = $this->phIn($this->idsEmpresa($idsEmpresa), 'npe', $params);
        $st = $this->db->prepare("SELECT id, COALESCE(codigo, '') AS codigo, nombre FROM productos
                                  WHERE id IN ({$inP}) AND id_empresa IN ({$inE})");
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $out[(int)$p['id']] = ['codigo' => (string)$p['codigo'], 'nombre' => (string)$p['nombre']];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CLIENTES (buscador del filtro)
    //
    // `clientes` es por empresa: el mismo cliente existe como filas distintas
    // en cada establecimiento del RUC. En el consolidado se busca en todos y se
    // cruza por identificación.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Buscador del filtro Cliente. Con varios establecimientos, devuelve UNA fila por
     * identificación (prefiere la de la empresa activa) para no repetir al cliente en el
     * dropdown; el filtro luego se expande a las hermanas con
     * expandirClientesPorIdentificacion().
     */
    public function buscarClientes(int|array $idsEmpresa, int $idEmpresaActual, string $q, int $limite = 15): array
    {
        $ids = $this->idsEmpresa($idsEmpresa);
        $q   = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':q' => '%' . mb_strtolower($q) . '%', ':q2' => '%' . $q . '%', ':actual' => $idEmpresaActual];
        $inEmp  = $this->phIn($ids, 'bce', $params);
        $sql = "SELECT id, nombre, identificacion, id_empresa
                FROM clientes
                WHERE id_empresa IN ({$inEmp})
                  AND eliminado  = false
                  AND (LOWER(nombre) LIKE :q OR identificacion LIKE :q2)
                ORDER BY (id_empresa = :actual) DESC, nombre
                LIMIT " . max(15, $limite * count($ids));
        $st = $this->db->prepare($sql);
        $st->execute($params);

        $out = [];
        $vistos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $clave = trim((string)($c['identificacion'] ?? ''));
            $clave = $clave !== '' ? 'i:' . $clave : 'id:' . (int)$c['id'];
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = ['id' => (int)$c['id'], 'nombre' => $c['nombre'], 'identificacion' => $c['identificacion']];
            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }

    /**
     * Consolidado: expande los ids de cliente elegidos a TODOS los ids del grupo de
     * establecimientos que comparten la misma identificación (los clientes sin
     * identificación solo se cruzan consigo mismos). Devuelve la unión con los ids
     * originales, para que el filtro `id_cliente IN (...)` alcance los documentos y
     * saldos iniciales de las hermanas.
     */
    public function expandirClientesPorIdentificacion(array $idsCliente, int|array $idsEmpresa): array
    {
        $idsCliente = array_values(array_unique(array_filter(array_map('intval', $idsCliente))));
        if (!$idsCliente) {
            return [];
        }
        $params = [];
        $inCli  = $this->phIn($idsCliente, 'xc', $params);
        $inEmp  = $this->phIn($this->idsEmpresa($idsEmpresa), 'xe', $params);
        $sql = "SELECT DISTINCT c2.id
                FROM clientes c1
                JOIN clientes c2
                  ON c2.identificacion = c1.identificacion
                 AND c2.eliminado = false
                 AND c2.id_empresa IN ({$inEmp})
                WHERE c1.id IN ({$inCli})
                  AND COALESCE(TRIM(c1.identificacion), '') <> ''";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $extra = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        return array_values(array_unique(array_merge($idsCliente, $extra)));
    }

    /**
     * Nombre e identificación de varios clientes (para describir el filtro en PDF/Excel),
     * buscándolos en cualquiera de los establecimientos del alcance.
     * Devuelve id => ['nombre' => …, 'identificacion' => …].
     */
    public function getClientesPorIds(array $idsCliente, int|array $idsEmpresa): array
    {
        $idsCliente = array_values(array_unique(array_filter(array_map('intval', $idsCliente))));
        if (!$idsCliente) {
            return [];
        }
        $params = [];
        $inCli  = $this->phIn($idsCliente, 'nc', $params);
        $inEmp  = $this->phIn($this->idsEmpresa($idsEmpresa), 'ne', $params);
        $st = $this->db->prepare("SELECT id, nombre, identificacion FROM clientes
                                  WHERE id IN ({$inCli}) AND id_empresa IN ({$inEmp})");
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $out[(int)$c['id']] = ['nombre' => (string)$c['nombre'], 'identificacion' => (string)($c['identificacion'] ?? '')];
        }
        return $out;
    }
}
