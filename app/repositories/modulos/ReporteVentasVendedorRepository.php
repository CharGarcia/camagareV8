<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ReporteVentasVendedorRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    /**
     * Configuración de la fuente de datos según el tipo de documento:
     *  - FACTURA      → ventas_* (tiene id_vendedor directo)
     *  - NOTA_CREDITO → notas_credito_* (NO tiene id_vendedor propio: se resuelve
     *    uniendo con la factura original vía num_doc_modificado, igual que hace
     *    FacturaVentaRepository para el estado de pago).
     *
     * El tipo especial FACTURA_MENOS_NC (facturas − notas de crédito) no tiene
     * fuente propia: se resuelve combinando FACTURA y NOTA_CREDITO (ver esNeto()).
     */
    private function fuente(array $filtros): array
    {
        $tipo = $filtros['tipo_documento'] ?? 'FACTURA_MENOS_NC';

        if ($tipo === 'NOTA_CREDITO') {
            return [
                'cab'           => 'notas_credito_cabecera',
                'det'           => 'notas_credito_detalle',
                'imp'           => 'notas_credito_detalle_impuestos',
                'fk_det'        => 'id_nota_credito',
                'fk_imp'        => 'id_nota_credito_detalle',
                'estado_ok'     => "{alias}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')",
                'vendedor'      => 'resuelto',
                'vendedor_join' => "LEFT JOIN ventas_cabecera vorig ON vorig.id_empresa = {alias}.id_empresa
                                        AND vorig.eliminado = false
                                        AND {alias}.cod_doc_modificado = '01'
                                        AND CONCAT(vorig.establecimiento,'-',vorig.punto_emision,'-',vorig.secuencial) = {alias}.num_doc_modificado
                                     LEFT JOIN vendedores vend ON vend.id = vorig.id_vendedor",
                'vendedor_col'  => 'vorig.id_vendedor',
                'es_factura'    => false,
            ];
        }

        return [
            'cab'           => 'ventas_cabecera',
            'det'           => 'ventas_detalle',
            'imp'           => 'ventas_detalle_impuestos',
            'fk_det'        => 'id_venta',
            'fk_imp'        => 'id_venta_detalle',
            'estado_ok'     => "{alias}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')",
            'vendedor'      => true,
            'vendedor_join' => "LEFT JOIN vendedores vend ON vend.id = {alias}.id_vendedor",
            'vendedor_col'  => '{alias}.id_vendedor',
            'es_factura'    => true,
        ];
    }

    /** ¿El reporte es el neto "Facturas − Notas de crédito"? */
    private function esNeto(array $filtros): bool
    {
        return ($filtros['tipo_documento'] ?? 'FACTURA_MENOS_NC') === 'FACTURA_MENOS_NC';
    }

    /**
     * Combina un método de reporte para FACTURA y NOTA_CREDITO restando la NC.
     * - $claves: columnas que identifican cada grupo (para agrupados). Si es null,
     *   es el modo detallado: devuelve facturas (+) seguidas de NC (−).
     * - $restar: campos monetarios (la NC se resta).
     * - $sumar:  campos de conteo (se suman ambos: total de documentos).
     */
    private function combinarNeto(int $idEmpresa, array $filtros, string $metodo, ?array $claves, array $restar, array $sumar = []): array
    {
        $fFac = array_merge($filtros, ['tipo_documento' => 'FACTURA']);
        $fNc  = array_merge($filtros, ['tipo_documento' => 'NOTA_CREDITO']);
        $fac  = $this->$metodo($idEmpresa, $fFac);
        $nc   = $this->$metodo($idEmpresa, $fNc);

        if ($claves === null) {
            foreach ($fac as &$r) { $r['_doc_tipo'] = 'FACTURA'; }
            unset($r);
            foreach ($nc as &$r) {
                foreach ($restar as $c) { $r[$c] = -(float)($r[$c] ?? 0); }
                $r['_doc_tipo'] = 'NOTA_CREDITO';
            }
            unset($r);
            $all = array_merge($fac, $nc);
            usort($all, fn($a, $b) => strcmp((string)($b['fecha_emision'] ?? ''), (string)($a['fecha_emision'] ?? '')));
            return $all;
        }

        $keyOf = function (array $r) use ($claves): string {
            $k = '';
            foreach ($claves as $c) { $k .= '|' . ($r[$c] ?? ''); }
            return $k;
        };

        $idx = [];
        foreach ($fac as $r) { $idx[$keyOf($r)] = $r; }
        foreach ($nc as $r) {
            $k = $keyOf($r);
            if (!isset($idx[$k])) {
                $base = $r;
                foreach ($restar as $c) { $base[$c] = 0; }
                foreach ($sumar  as $c) { $base[$c] = 0; }
                $idx[$k] = $base;
            }
            foreach ($restar as $c) { $idx[$k][$c] = (float)($idx[$k][$c] ?? 0) - (float)($r[$c] ?? 0); }
            foreach ($sumar  as $c) { $idx[$k][$c] = (float)($idx[$k][$c] ?? 0) + (float)($r[$c] ?? 0); }
        }

        $out = array_values($idx);
        usort($out, fn($a, $b) => ((float)($b['total'] ?? 0)) <=> ((float)($a['total'] ?? 0)));
        return $out;
    }

    /**
     * Años disponibles (facturas autorizadas de ventas).
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                FROM ventas_cabecera
                WHERE id_empresa = :e AND eliminado = false AND estado IN ('autorizado','autorizada')
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }

    /* ═══════════════════════════════════════════════════════════════════
       CTEs base del reporte
       ───────────────────────────────────────────────────────────────────
       Toda consulta que trabaje a nivel de DOCUMENTO arranca por la CTE
       "docs": los documentos que ya pasaron TODOS los filtros del reporte
       (empresa, ambiente, estado, fechas, vendedor, producto/marca/categoría).
       El resto de CTEs (bases e impuestos, cobros, retenciones, NC, ND) se
       unen CONTRA docs, así solo agregan el detalle del período consultado.

       Antes, la CTE de bases agregaba el detalle de TODOS los documentos de
       la empresa (todo el histórico) y recién después se descartaba con un
       LEFT JOIN: pedir un mes costaba lo mismo que pedir cinco años, y el
       reporte ejecuta esa consulta hasta cuatro veces por pantalla (filas +
       estadísticas, × 2 cuando el tipo es "Facturas − NC"). Ese era el origen
       de la lentitud.
       ═══════════════════════════════════════════════════════════════════ */

    /**
     * CTE de documentos que cumplen los filtros. Expone lo mínimo que necesitan
     * las consultas de arriba, incluido el vendedor ya resuelto y el número de
     * comprobante (la llave de cruce con NC/ND).
     */
    private function cteDocs(array $f, string $where, string $vendedorJoin, string $vendedorCol): string
    {
        return "
            SELECT
                v.id,
                v.fecha_emision,
                v.estado,
                v.importe_total,
                v.id_cliente,
                v.secuencial,
                CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AS numero_factura,
                {$vendedorCol} AS id_vendedor,
                vend.nombre AS vendedor_nombre
            FROM {$f['cab']} v
            {$vendedorJoin}
            WHERE {$where}
        ";
    }

    /**
     * CTE de bases e impuestos, acotada a los documentos de la CTE docs.
     */
    private function cteBases(array $f): string
    {
        return "
            SELECT
                d.{$f['fk_det']} AS id_doc,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(i.valor) as valor_iva
            FROM {$f['det']} d
            JOIN docs dd ON dd.id = d.{$f['fk_det']}
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            GROUP BY d.{$f['fk_det']}
        ";
    }

    /**
     * CTEs del SALDO PENDIENTE por documento, acotadas a la CTE docs.
     *
     * Misma fórmula que Cuentas por Cobrar y que la columna "Saldo" del listado
     * de Facturas de Venta:
     *   saldo = importe_total + notas de débito − cobros − retenciones − notas de crédito
     * Nunca negativo: un sobrecobro se muestra como 0, igual que en el listado
     * de facturas.
     *
     * Solo aplica a facturas: una nota de crédito no tiene cartera propia, así
     * que en la fuente NOTA_CREDITO el saldo es 0.
     *
     * $idEmpresa es int validado por el tipo del parámetro → interpolación
     * segura (mismo criterio que CuentasPorCobrarRepository).
     *
     * @return string bloque de CTEs que termina definiendo "saldos(id_doc, saldo)"
     */
    private function ctesSaldo(array $f, int $idEmpresa): string
    {
        if (empty($f['es_factura'])) {
            return "saldos AS (SELECT dd.id AS id_doc, 0::numeric AS saldo FROM docs dd)";
        }

        return "
            sal_cob AS (
                SELECT ind.id_referencia_documento AS id_doc, SUM(ind.monto_cobrado) AS m
                FROM ingresos_detalle ind
                JOIN ingresos_cabecera inc ON inc.id = ind.id_ingreso
                JOIN docs dd ON dd.id = ind.id_referencia_documento
                WHERE ind.tipo_documento = 'FACTURA'
                  AND inc.estado != 'anulado'
                  AND inc.eliminado = false
                  AND inc.id_empresa = {$idEmpresa}
                GROUP BY ind.id_referencia_documento
            ),
            sal_ret AS (
                SELECT t.id_doc, SUM(t.monto) AS m
                FROM (
                    SELECT r.id_venta AS id_doc,
                           (r.total_renta + r.total_iva + r.total_isd) AS monto,
                           r.id AS id_ret
                    FROM retencion_venta_cabecera r
                    JOIN docs dd ON dd.id = r.id_venta
                    WHERE r.eliminado = false AND r.id_empresa = {$idEmpresa}

                    UNION

                    SELECT dd.id AS id_doc,
                           (r.total_renta + r.total_iva + r.total_isd) AS monto,
                           r.id AS id_ret
                    FROM retencion_venta_cabecera r
                    JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
                    JOIN docs dd ON dd.numero_factura = rd.num_doc_sustento
                    WHERE r.eliminado = false AND r.id_empresa = {$idEmpresa}
                ) t
                GROUP BY t.id_doc
            ),
            sal_nc AS (
                SELECT dd.id AS id_doc, SUM(nc.importe_total) AS m
                FROM notas_credito_cabecera nc
                JOIN docs dd ON dd.numero_factura = nc.num_doc_modificado
                WHERE nc.estado != 'anulado' AND nc.eliminado = false
                  AND nc.id_empresa = {$idEmpresa}
                GROUP BY dd.id
            ),
            sal_nd AS (
                SELECT dd.id AS id_doc, SUM(nd.importe_total) AS m
                FROM nota_debito_cabecera nd
                JOIN docs dd ON dd.numero_factura = nd.num_doc_modificado
                WHERE nd.estado != 'anulado' AND nd.eliminado = false
                  AND nd.id_empresa = {$idEmpresa}
                GROUP BY dd.id
            ),
            saldos AS (
                SELECT dd.id AS id_doc,
                       GREATEST(
                           dd.importe_total
                           + COALESCE(nd.m, 0)
                           - COALESCE(cb.m, 0)
                           - COALESCE(rt.m, 0)
                           - COALESCE(nc.m, 0)
                       , 0) AS saldo
                FROM docs dd
                LEFT JOIN sal_cob cb ON cb.id_doc = dd.id
                LEFT JOIN sal_ret rt ON rt.id_doc = dd.id
                LEFT JOIN sal_nc  nc ON nc.id_doc = dd.id
                LEFT JOIN sal_nd  nd ON nd.id_doc = dd.id
            )
        ";
    }

    /**
     * CTE de la nota de crédito que NETEA la factura (criterio del reporte: NC
     * autorizada que modifica un documento tipo '01'). Es distinta de la NC que
     * entra en el saldo (esa toma toda NC no anulada, criterio de cartera), por
     * eso se calcula aparte.
     */
    private function cteNcNeteo(int $idEmpresa): string
    {
        return "
            SELECT dd.id AS id_doc, SUM(nc.importe_total) AS total_nc
            FROM notas_credito_cabecera nc
            JOIN docs dd ON dd.numero_factura = nc.num_doc_modificado
            WHERE nc.id_empresa = {$idEmpresa}
              AND nc.eliminado = false
              AND nc.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')
              AND nc.cod_doc_modificado = '01'
            GROUP BY dd.id
        ";
    }

    /**
     * Construye las condiciones WHERE a partir de los filtros. Es auto-contenido
     * (no requiere que la consulta que llama agregue joins extra al FROM), incluso
     * para el filtro de vendedor sobre notas de crédito (usa un EXISTS contra la
     * factura original).
     *
     * $conEstado = false devuelve el mismo WHERE pero SIN el filtro de estado: lo
     * usa el resumen de estados, que necesita contar también borradores y anulados.
     */
    private function buildWhereYParams(int $idEmpresa, array $filtros, string $aliasVenta, ?string $aliasDetalle = null, bool $conEstado = true): array
    {
        $f = $this->fuente($filtros);

        $where = "{$aliasVenta}.id_empresa = :id_empresa
                  AND {$aliasVenta}.eliminado = false
                  AND {$aliasVenta}.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        if ($conEstado) {
            $where .= " AND " . str_replace('{alias}', $aliasVenta, $f['estado_ok']);
        }

        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND {$aliasVenta}.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND {$aliasVenta}.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        if (!empty($filtros['id_vendedor'])) {
            if ($f['vendedor'] === true) {
                $where .= " AND {$aliasVenta}.id_vendedor = :id_vendedor";
            } elseif ($f['vendedor'] === 'resuelto') {
                $where .= " AND EXISTS (
                    SELECT 1 FROM ventas_cabecera vorigf
                    WHERE vorigf.id_empresa = {$aliasVenta}.id_empresa
                      AND vorigf.eliminado = false
                      AND {$aliasVenta}.cod_doc_modificado = '01'
                      AND CONCAT(vorigf.establecimiento,'-',vorigf.punto_emision,'-',vorigf.secuencial) = {$aliasVenta}.num_doc_modificado
                      AND vorigf.id_vendedor = :id_vendedor
                )";
            } else {
                // Fuente sin vendedor resoluble: no debe matchear si se pide uno específico.
                $where .= " AND 1 = 0";
            }
            $params[':id_vendedor'] = (int) $filtros['id_vendedor'];
        }

        if (!empty($filtros['id_producto'])) {
            if ($aliasDetalle) {
                $where .= " AND {$aliasDetalle}.id_producto = :id_producto";
            } else {
                $where .= " AND EXISTS (SELECT 1 FROM {$f['det']} vd WHERE vd.{$f['fk_det']} = {$aliasVenta}.id AND vd.id_producto = :id_producto)";
            }
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }

        if (!empty($filtros['id_marca'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['det']} vdm
                JOIN productos pm ON pm.id = vdm.id_producto
                WHERE vdm.{$f['fk_det']} = {$aliasVenta}.id AND pm.id_marca = :id_marca
            )";
            $params[':id_marca'] = (int) $filtros['id_marca'];
        }

        if (!empty($filtros['id_categoria'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['det']} vdc
                JOIN productos pc ON pc.id = vdc.id_producto
                WHERE vdc.{$f['fk_det']} = {$aliasVenta}.id AND pc.id_categoria = :id_categoria
            )";
            $params[':id_categoria'] = (int) $filtros['id_categoria'];
        }

        return [$where, $params];
    }

    /**
     * Resuelve el JOIN y la columna de vendedor para la fuente activa, con el
     * alias de la cabecera ya reemplazado.
     */
    private function vendedorJoinYCol(array $f, string $aliasVenta): array
    {
        $join = str_replace('{alias}', $aliasVenta, $f['vendedor_join']);
        $col  = str_replace('{alias}', $aliasVenta, $f['vendedor_col']);
        return [$join, $col];
    }

    /**
     * Piezas comunes de toda consulta a nivel de documento: fuente, CTE docs ya
     * armada y los params del WHERE.
     *
     * @return array{0: array, 1: string, 2: array} [fuente, cteDocs, params]
     */
    private function prepararDocs(int $idEmpresa, array $filtros, bool $conEstado = true): array
    {
        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', null, $conEstado);
        list($vendedorJoin, $vendedorCol) = $this->vendedorJoinYCol($f, 'v');

        return [$f, $this->cteDocs($f, $where, $vendedorJoin, $vendedorCol), $params];
    }

    /**
     * Detalle documento por documento (facturas) de un vendedor: cada factura con
     * su subtotal, la NC que la afecta (si tiene), el total neto y el SALDO
     * pendiente. Es el "drill-down" que se abre al hacer clic en una fila de la
     * agrupación Vendedor. $idVendedor > 0 filtra a ese vendedor; 0 significa
     * "Sin vendedor asignado" (id_vendedor IS NULL); cualquier otro valor (p. ej.
     * el sentinel -1 de un usuario restringido sin vendedor vinculado) no devuelve
     * nada.
     */
    public function getDocumentosPorVendedor(int $idEmpresa, int $idVendedor, array $filtros): array
    {
        // Siempre facturas: el drill-down lista documentos y netea su NC dentro de
        // la misma fila, en vez de agregarla como fila aparte.
        $fFiltros = array_merge($filtros, ['tipo_documento' => 'FACTURA']);
        unset($fFiltros['id_vendedor']);

        $f = $this->fuente($fFiltros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $fFiltros, 'v');
        list($vendedorJoin, $vendedorCol) = $this->vendedorJoinYCol($f, 'v');

        if ($idVendedor > 0) {
            $where .= " AND v.id_vendedor = :id_vendedor";
            $params[':id_vendedor'] = $idVendedor;
        } elseif ($idVendedor === 0) {
            $where .= " AND v.id_vendedor IS NULL";
        } else {
            $where .= " AND 1 = 0";
        }

        $sql = "
            WITH docs AS (" . $this->cteDocs($f, $where, $vendedorJoin, $vendedorCol) . "),
                 nc_neteo AS (" . $this->cteNcNeteo($idEmpresa) . "),
                 " . $this->ctesSaldo($f, $idEmpresa) . "
            SELECT
                v.id,
                v.fecha_emision,
                v.numero_factura,
                c.nombre as cliente_nombre,
                v.importe_total as subtotal,
                COALESCE(ncs.total_nc, 0) as nc,
                v.importe_total - COALESCE(ncs.total_nc, 0) as total,
                COALESCE(s.saldo, 0) as saldo
            FROM docs v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN nc_neteo ncs ON ncs.id_doc = v.id
            LEFT JOIN saldos   s   ON s.id_doc   = v.id
            ORDER BY v.fecha_emision DESC, v.secuencial DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle documento por documento (facturas), con vendedor resuelto, el neteo
     * por su propia NC y el SALDO pendiente, respetando los filtros vigentes del
     * reporte (fecha, vendedor, producto, marca, categoría). Se usa para la
     * segunda hoja de Excel ("Detalle Documentos") cuando se agrupa por Vendedor:
     * si el filtro Vendedor está en "Todos", trae los documentos de todos los
     * vendedores ordenados por vendedor; si hay uno seleccionado, solo los de ese
     * vendedor.
     */
    public function getDetalleDocumentosVendedor(int $idEmpresa, array $filtros): array
    {
        $fFiltros = array_merge($filtros, ['tipo_documento' => 'FACTURA']);
        list($f, $cteDocs, $params) = $this->prepararDocs($idEmpresa, $fFiltros);

        $sql = "
            WITH docs AS ({$cteDocs}),
                 nc_neteo AS (" . $this->cteNcNeteo($idEmpresa) . "),
                 " . $this->ctesSaldo($f, $idEmpresa) . "
            SELECT
                v.id,
                v.fecha_emision,
                v.numero_factura,
                c.nombre as cliente_nombre,
                COALESCE(v.vendedor_nombre, 'Sin vendedor asignado') as vendedor_nombre,
                v.importe_total as subtotal,
                COALESCE(ncs.total_nc, 0) as nc,
                v.importe_total - COALESCE(ncs.total_nc, 0) as total,
                COALESCE(s.saldo, 0) as saldo
            FROM docs v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN nc_neteo ncs ON ncs.id_doc = v.id
            LEFT JOIN saldos   s   ON s.id_doc   = v.id
            ORDER BY COALESCE(v.vendedor_nombre, 'Sin vendedor asignado'), v.fecha_emision DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por vendedor (vista principal de este módulo).
     */
    public function getReporteAgrupadoVendedor(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            // cantidad_documentos NO se suma con las notas de crédito: el drill-down
            // (getDocumentosPorVendedor) solo lista facturas, netea la NC dentro de la
            // misma fila (columna "nc") en vez de agregarla como fila aparte. Si aquí se
            // sumara el conteo de NC, la columna "Nro Documentos" mostraría más de lo que
            // realmente aparece al hacer clic (p. ej. 4 en la tabla vs 2 en el detalle).
            // El saldo va en $sumar (no en $restar) porque la NC aporta 0: no tiene
            // cartera propia y ya está descontada dentro del saldo de la factura.
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoVendedor', ['id_vendedor'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['saldo']);
        }

        list($f, $cteDocs, $params) = $this->prepararDocs($idEmpresa, $filtros);

        $sql = "
            WITH docs AS ({$cteDocs}),
                 bases AS (" . $this->cteBases($f) . "),
                 " . $this->ctesSaldo($f, $idEmpresa) . "
            SELECT
                COALESCE(v.id_vendedor, 0) as id_vendedor,
                COALESCE(v.vendedor_nombre, 'Sin vendedor asignado') as vendedor_nombre,
                COUNT(v.id) as cantidad_documentos,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total,
                SUM(COALESCE(s.saldo, 0)) as saldo
            FROM docs v
            LEFT JOIN bases  b ON b.id_doc = v.id
            LEFT JOIN saldos s ON s.id_doc = v.id
            GROUP BY COALESCE(v.id_vendedor, 0), COALESCE(v.vendedor_nombre, 'Sin vendedor asignado')
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por mes (año-mes).
     */
    public function getReporteAgrupadoMes(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoMes', ['mes'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_documentos', 'saldo']);
        }

        list($f, $cteDocs, $params) = $this->prepararDocs($idEmpresa, $filtros);

        $sql = "
            WITH docs AS ({$cteDocs}),
                 bases AS (" . $this->cteBases($f) . "),
                 " . $this->ctesSaldo($f, $idEmpresa) . "
            SELECT
                TO_CHAR(v.fecha_emision, 'YYYY-MM') as mes,
                COUNT(v.id) as cantidad_documentos,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total,
                SUM(COALESCE(s.saldo, 0)) as saldo
            FROM docs v
            LEFT JOIN bases  b ON b.id_doc = v.id
            LEFT JOIN saldos s ON s.id_doc = v.id
            GROUP BY TO_CHAR(v.fecha_emision, 'YYYY-MM')
            ORDER BY mes DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte detallado (por documento).
     */
    public function getReporteDetallado(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteDetallado', null,
                ['base_0', 'base_iva', 'valor_iva', 'total']);
        }

        list($f, $cteDocs, $params) = $this->prepararDocs($idEmpresa, $filtros);

        $sql = "
            WITH docs AS ({$cteDocs}),
                 bases AS (" . $this->cteBases($f) . "),
                 " . $this->ctesSaldo($f, $idEmpresa) . "
            SELECT
                v.id,
                v.fecha_emision,
                v.numero_factura,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                v.estado,
                COALESCE(b.base_0, 0)    as base_0,
                COALESCE(b.base_iva, 0)  as base_iva,
                COALESCE(b.valor_iva, 0) as valor_iva,
                v.importe_total          as total,
                COALESCE(s.saldo, 0)     as saldo,
                COALESCE(v.vendedor_nombre, 'Sin vendedor asignado') as vendedor_nombre
            FROM docs v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases  b ON b.id_doc = v.id
            LEFT JOIN saldos s ON s.id_doc = v.id
            ORDER BY v.fecha_emision DESC, v.secuencial DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por producto (incluye marca/categoría del producto).
     */
    public function getReporteAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoProducto', ['id_producto', 'tarifa_iva'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_vendida']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');

        $sql = "
            SELECT
                d.id_producto,
                COALESCE(p.codigo, '') as producto_codigo,
                COALESCE(p.nombre, d.descripcion) as producto_nombre,
                COALESCE(mar.nombre, 'Sin marca') as marca_nombre,
                COALESCE(cat.nombre, 'Sin categoría') as categoria_nombre,
                COALESCE(i.tarifa, 0) as tarifa_iva,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN marcas mar ON mar.id = p.id_marca
            LEFT JOIN categorias cat ON cat.id = p.id_categoria
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            WHERE {$where}
            GROUP BY d.id_producto, p.codigo, COALESCE(p.nombre, d.descripcion), mar.nombre, cat.nombre, COALESCE(i.tarifa, 0)
            ORDER BY cantidad_vendida DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por marca.
     */
    public function getReporteAgrupadoMarca(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoMarca', ['id_marca'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_vendida']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');

        $sql = "
            SELECT
                p.id_marca,
                COALESCE(mar.nombre, 'Sin marca') as marca_nombre,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN marcas mar ON mar.id = p.id_marca
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            WHERE {$where}
            GROUP BY p.id_marca, mar.nombre
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por categoría.
     */
    public function getReporteAgrupadoCategoria(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoCategoria', ['id_categoria'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_vendida']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');

        $sql = "
            SELECT
                p.id_categoria,
                COALESCE(cat.nombre, 'Sin categoría') as categoria_nombre,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN categorias cat ON cat.id = p.id_categoria
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            WHERE {$where}
            GROUP BY p.id_categoria, cat.nombre
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas globales para el rango/filtros dados. Incluye el saldo
     * pendiente total de los documentos que entran al reporte.
     */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            $sf = $this->getEstadisticas($idEmpresa, array_merge($filtros, ['tipo_documento' => 'FACTURA']));
            $sn = $this->getEstadisticas($idEmpresa, array_merge($filtros, ['tipo_documento' => 'NOTA_CREDITO']));
            return [
                'total_base_0'     => $sf['total_base_0']   - $sn['total_base_0'],
                'total_base_iva'   => $sf['total_base_iva'] - $sn['total_base_iva'],
                'total_iva'        => $sf['total_iva']      - $sn['total_iva'],
                'gran_total'       => $sf['gran_total']     - $sn['gran_total'],
                'total_documentos' => $sf['total_documentos'] + $sn['total_documentos'],
                // La NC no tiene cartera propia (su saldo es 0) y ya está descontada
                // dentro del saldo de la factura: no se vuelve a restar aquí.
                'total_saldo'      => $sf['total_saldo'],
            ];
        }

        list($f, $cteDocs, $params) = $this->prepararDocs($idEmpresa, $filtros);

        $sql = "
            WITH docs AS ({$cteDocs}),
                 bases AS (" . $this->cteBases($f) . "),
                 " . $this->ctesSaldo($f, $idEmpresa) . "
            SELECT
                SUM(COALESCE(b.base_0, 0)) as total_base_0,
                SUM(COALESCE(b.base_iva, 0)) as total_base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as total_iva,
                SUM(v.importe_total) as gran_total,
                SUM(COALESCE(s.saldo, 0)) as total_saldo,
                COUNT(v.id) as total_documentos
            FROM docs v
            LEFT JOIN bases  b ON b.id_doc = v.id
            LEFT JOIN saldos s ON s.id_doc = v.id
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'total_base_0'     => (float)($row['total_base_0'] ?? 0),
            'total_base_iva'   => (float)($row['total_base_iva'] ?? 0),
            'total_iva'        => (float)($row['total_iva'] ?? 0),
            'gran_total'       => (float)($row['gran_total'] ?? 0),
            'total_saldo'      => (float)($row['total_saldo'] ?? 0),
            'total_documentos' => (int)($row['total_documentos'] ?? 0),
        ];
    }

    public function getResumenEstados(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            $rf = $this->getResumenEstados($idEmpresa, array_merge($filtros, ['tipo_documento' => 'FACTURA']));
            $rn = $this->getResumenEstados($idEmpresa, array_merge($filtros, ['tipo_documento' => 'NOTA_CREDITO']));
            return [
                'autorizados' => $rf['autorizados'] + $rn['autorizados'],
                'anulados'    => $rf['anulados']    + $rn['anulados'],
                'borradores'  => $rf['borradores']  + $rn['borradores'],
            ];
        }

        $f = $this->fuente($filtros);
        // Mismos filtros de fecha/vendedor/producto/marca/categoría que el resto del
        // reporte, pero SIN el de estado: este resumen necesita el desglose real
        // (autorizados / anulados / borradores).
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', null, false);

        $sql = "
            SELECT LOWER(v.estado) as estado, COUNT(*) as cantidad
            FROM {$f['cab']} v
            WHERE {$where}
            GROUP BY LOWER(v.estado)
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $resumen = ['autorizados' => 0, 'anulados' => 0, 'borradores' => 0];
        foreach ($rows as $row) {
            $estado = $row['estado'];
            $cantidad = (int) $row['cantidad'];
            if (in_array($estado, ['autorizado', 'autorizada'], true)) {
                $resumen['autorizados'] += $cantidad;
            } elseif ($estado === 'anulado') {
                $resumen['anulados'] += $cantidad;
            } elseif ($estado === 'borrador') {
                $resumen['borradores'] += $cantidad;
            }
        }

        return $resumen;
    }
}
