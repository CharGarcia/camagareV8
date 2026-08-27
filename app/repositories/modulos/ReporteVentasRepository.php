<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ReporteVentasRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    /**
     * Configuración de la fuente de datos según el tipo de documento:
     *  - FACTURA         → ventas_*
     *  - RECIBO          → recibos_venta_*
     *  - NOTA_CREDITO    → notas_credito_*  (notas de crédito en ventas)
     * Todas las tablas son espejo entre sí (FKs distintas). Las notas de crédito
     * no llevan vendedor ni retenciones.
     *
     * El tipo especial FACTURA_MENOS_NC (facturas − notas de crédito) NO tiene
     * fuente propia: se resuelve combinando FACTURA y NOTA_CREDITO (ver esNeto()).
     */
    private function fuente(array $filtros): array
    {
        $tipo = $filtros['tipo_documento'] ?? 'FACTURA';

        if ($tipo === 'RECIBO') {
            return [
                'cab'         => 'recibos_venta_cabecera',
                'det'         => 'recibos_venta_detalle',
                'imp'         => 'recibos_venta_detalle_impuestos',
                'adic'        => 'recibos_venta_adicional',
                'fk_det'      => 'id_recibo',          // detalle.id_recibo = cabecera.id
                'fk_imp'      => 'id_recibo_detalle',  // impuestos.id_recibo_detalle = detalle.id
                'fk_adic'     => 'id_recibo',
                'estado_ok'   => "{alias}.estado NOT IN ('borrador', 'anulado', 'facturado')",
                'retenciones' => false,
                'clave'       => false,
                'vendedor'    => true,
            ];
        }

        if ($tipo === 'NOTA_CREDITO') {
            return [
                'cab'         => 'notas_credito_cabecera',
                'det'         => 'notas_credito_detalle',
                'imp'         => 'notas_credito_detalle_impuestos',
                'adic'        => 'notas_credito_adicional',
                'fk_det'      => 'id_nota_credito',
                'fk_imp'      => 'id_nota_credito_detalle',
                'fk_adic'     => 'id_nota_credito',
                'estado_ok'   => "{alias}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')",
                'retenciones' => false,
                'clave'       => true,
                'vendedor'    => false,   // notas_credito_cabecera no tiene id_vendedor
            ];
        }

        return [
            'cab'         => 'ventas_cabecera',
            'det'         => 'ventas_detalle',
            'imp'         => 'ventas_detalle_impuestos',
            'adic'        => 'ventas_adicional',
            'fk_det'      => 'id_venta',
            'fk_imp'      => 'id_venta_detalle',
            'fk_adic'     => 'id_venta',
            'estado_ok'   => "{alias}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')",
            'retenciones' => true,
            'clave'       => true,
            'vendedor'    => true,
        ];
    }

    /** ¿El reporte es el neto "Facturas − Notas de crédito"? */
    private function esNeto(array $filtros): bool
    {
        return ($filtros['tipo_documento'] ?? '') === 'FACTURA_MENOS_NC';
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

        // Modo detallado: mezclar filas, negando montos de las NC.
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

        // Modo agrupado: indexar por clave y restar las NC.
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
                // Grupo que solo tiene NC (sin facturas): partir de esta fila con
                // montos y conteos en 0 para que el resultado quede en negativo.
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
     * Años disponibles (facturas autorizadas + recibos emitidos/facturados).
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT anio FROM (
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                    FROM ventas_cabecera
                    WHERE id_empresa = :e AND eliminado = false AND estado IN ('autorizado','autorizada')
                    UNION
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int
                    FROM recibos_venta_cabecera
                    WHERE id_empresa = :e2 AND eliminado = false AND estado NOT IN ('borrador','anulado')
                ) t
                WHERE anio IS NOT NULL
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $idEmpresa, ':e2' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }

    /**
     * CTE de bases e impuestos para sumatorias (según la fuente).
     * Se une contra la cabecera y se filtra por id_empresa: sin este JOIN, el GROUP BY
     * agregaba el detalle de TODOS los documentos del sistema (todas las empresas) en
     * cada consulta del reporte, sin importar cuántas filas mostraba el filtro — el
     * costo crecía con el tamaño de la BD completa, no con los datos de la empresa
     * activa, y se sentía cada vez más lento a medida que el sistema acumulaba más
     * empresas/documentos. Requiere que el llamador incluya :id_empresa en sus params
     * (ya lo hace vía buildWhereYParams; reutilizar el mismo nombre de placeholder es
     * seguro en este proyecto, ver memoria pdo-placeholders-repetidos).
     */
    private function getCteBasesImpuestos(array $f): string
    {
        return "
            SELECT
                d.{$f['fk_det']} AS id_doc,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(i.valor) as valor_iva
            FROM {$f['det']} d
            JOIN {$f['cab']} vcte ON vcte.id = d.{$f['fk_det']} AND vcte.id_empresa = :id_empresa
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            GROUP BY d.{$f['fk_det']}
        ";
    }

    /**
     * Construye las condiciones WHERE a partir de los filtros.
     */
    private function buildWhereYParams(int $idEmpresa, array $filtros, string $aliasVenta, string $aliasDetalle = null, bool $filtrarEstado = true): array
    {
        $f = $this->fuente($filtros);

        $where = "{$aliasVenta}.id_empresa = :id_empresa
                  AND {$aliasVenta}.eliminado = false
                  AND {$aliasVenta}.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        if ($filtrarEstado) {
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
        if (!empty($filtros['id_cliente'])) {
            $clientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : [$filtros['id_cliente']];
            $inNames = [];
            foreach ($clientes as $i => $id) {
                $pName = ":cli$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            $where .= " AND {$aliasVenta}.id_cliente IN (" . implode(',', $inNames) . ")";
        }

        if (!empty($filtros['id_producto'])) {
            $productos = is_array($filtros['id_producto']) ? $filtros['id_producto'] : [$filtros['id_producto']];
            $inNames = [];
            foreach ($productos as $i => $id) {
                $pName = ":prod$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            if ($aliasDetalle) {
                $where .= " AND {$aliasDetalle}.id_producto IN (" . implode(',', $inNames) . ")";
            } else {
                $where .= " AND EXISTS (SELECT 1 FROM {$f['det']} vd WHERE vd.{$f['fk_det']} = {$aliasVenta}.id AND vd.id_producto IN (" . implode(',', $inNames) . "))";
            }
        }

        // Filtro por Producto = texto de los ítems del documento (descripción o código de línea)
        if (!empty($filtros['producto_texto'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['det']} vdp
                WHERE vdp.{$f['fk_det']} = {$aliasVenta}.id
                  AND (vdp.descripcion ILIKE :prodtxt OR vdp.codigo_principal ILIKE :prodtxt)
            )";
            $params[':prodtxt'] = '%' . trim($filtros['producto_texto']) . '%';
        }

        // Filtro por Variante = texto de la variante elegida en la línea (Color/Talla, etc.)
        if (!empty($filtros['variante_texto'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['det']} vdv
                JOIN productos_variantes pv ON pv.id = vdv.id_producto_variante
                WHERE vdv.{$f['fk_det']} = {$aliasVenta}.id
                  AND (pv.nombre ILIKE :vartxt OR pv.valor ILIKE :vartxt)
            )";
            $params[':vartxt'] = '%' . trim($filtros['variante_texto']) . '%';
        }

        // Filtro por Información Adicional del documento (campos adicionales nombre/valor)
        if (!empty($filtros['buscar_info'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['adic']} va
                WHERE va.{$f['fk_adic']} = {$aliasVenta}.id
                  AND (va.nombre ILIKE :info OR va.valor ILIKE :info)
            )";
            $params[':info'] = '%' . trim($filtros['buscar_info']) . '%';
        }

        return [$where, $params];
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

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $clave = $f['clave'] ? "COALESCE(v.clave_acceso, '')" : "''";
        // Vínculo doble, igual que en FacturaVentaRepository/RetencionVentaRepository: además de
        // r.id_venta (registrada directo desde la factura), cubre la retención electrónica del SRI
        // que solo referencia el número de la factura en su detalle (num_doc_sustento) sin haber
        // quedado enlazada por id_venta.
        // Se resuelve como CTE (una sola pasada sobre las retenciones de la empresa), no como
        // subconsulta correlacionada por fila: con un filtro amplio (producto_texto por nombre)
        // el reporte puede devolver muchas filas, y repetir el escaneo de retenciones por cada
        // una escala mal (N filas × M retenciones) — en el droplet de producción (1 vCPU) eso
        // fue suficiente para saturar la conexión a BD y colgar el sitio entero.
        if ($f['retenciones']) {
            $retenCte = ",
                retenciones_map AS (
                    SELECT COALESCE(r.id_venta, vv.id) AS id_venta,
                           r.total_iva, r.total_renta, r.total_isd
                    FROM retencion_venta_cabecera r
                    LEFT JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id AND r.id_venta IS NULL
                    LEFT JOIN ventas_cabecera vv ON r.id_venta IS NULL
                        AND vv.id_empresa = r.id_empresa
                        AND CONCAT(vv.establecimiento, '-', vv.punto_emision, '-', vv.secuencial) = rd.num_doc_sustento
                    WHERE r.eliminado = false AND r.id_empresa = :id_empresa
                ),
                retenciones_agg AS (
                    SELECT id_venta, SUM(total_iva + total_renta + total_isd) AS monto_retenciones
                    FROM retenciones_map
                    WHERE id_venta IS NOT NULL
                    GROUP BY id_venta
                )";
            $retenJoin = "LEFT JOIN retenciones_agg ra ON ra.id_venta = v.id";
            $reten = "COALESCE(ra.monto_retenciones, 0)";
        } else {
            $retenCte = "";
            $retenJoin = "";
            $reten = "0";
        }
        // Las notas de crédito no tienen id_vendedor: se omite el join.
        $vendedorSel  = $f['vendedor'] ? "COALESCE(vend.nombre, '')" : "''";
        $vendedorJoin = $f['vendedor'] ? "LEFT JOIN vendedores vend ON vend.id = v.id_vendedor" : "";

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . "){$retenCte}
            SELECT
                v.id,
                v.fecha_emision,
                CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) as numero_factura,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                v.estado,
                COALESCE(b.base_0, 0)   as base_0,
                COALESCE(b.base_iva, 0) as base_iva,
                COALESCE(b.valor_iva, 0) as valor_iva,
                v.importe_total          as total,
                {$vendedorSel}              as vendedor_nombre,
                COALESCE(ucaj.nombre, '')   as cajero_nombre,
                COALESCE(uusr.nombre, '')   as usuario_nombre,
                {$clave} as clave_acceso,
                {$reten} as retenciones
            FROM {$f['cab']} v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases b ON b.id_doc = v.id
            {$retenJoin}
            {$vendedorJoin}
            LEFT JOIN usuarios    ucaj ON ucaj.id = v.id_usuario
            LEFT JOIN usuarios    uusr ON uusr.id = v.created_by
            WHERE {$where}
            ORDER BY v.fecha_emision DESC, v.secuencial DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por cliente.
     */
    public function getReporteAgrupadoCliente(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoCliente', ['id_cliente'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_facturas']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                c.id as id_cliente,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM {$f['cab']} v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
            GROUP BY c.id, c.identificacion, c.nombre
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por producto.
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
                COALESCE(i.tarifa, 0) as tarifa_iva,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            WHERE {$where}
            GROUP BY d.id_producto, p.codigo, COALESCE(p.nombre, d.descripcion), COALESCE(i.tarifa, 0)
            ORDER BY cantidad_vendida DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por variante de producto (Color/Talla, etc. —
     * productos_variantes). Solo incluye líneas que efectivamente tienen una
     * variante elegida (id_producto_variante no nulo); el resto del reporte
     * ("Por Producto") ya las cubre de forma agregada sin distinguir variante.
     */
    public function getReporteAgrupadoVariante(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoVariante', ['id_producto_variante', 'tarifa_iva'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_vendida']);
        }

        // Las notas de crédito no registran variantes (no hay id_producto_variante),
        // por lo que este agrupado no aplica para ellas.
        if (($filtros['tipo_documento'] ?? '') === 'NOTA_CREDITO') {
            return [];
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');

        $sql = "
            SELECT
                d.id_producto_variante,
                COALESCE(p.nombre, d.descripcion) as producto_nombre,
                pv.nombre as variante_nombre,
                pv.valor as variante_valor,
                COALESCE(i.tarifa, 0) as tarifa_iva,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            JOIN productos_variantes pv ON pv.id = d.id_producto_variante
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            WHERE {$where}
            GROUP BY d.id_producto_variante, COALESCE(p.nombre, d.descripcion), pv.nombre, pv.valor, COALESCE(i.tarifa, 0)
            ORDER BY cantidad_vendida DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por fecha.
     */
    public function getReporteAgrupadoFecha(int $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoFecha', ['fecha'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_facturas']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                v.fecha_emision as fecha,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
            GROUP BY v.fecha_emision
            ORDER BY v.fecha_emision DESC
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
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_facturas']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                TO_CHAR(v.fecha_emision, 'YYYY-MM') as mes,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
            GROUP BY TO_CHAR(v.fecha_emision, 'YYYY-MM')
            ORDER BY mes DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Autocompletado: descripciones distintas de los ítems del documento.
     */
    public function buscarItems(int $idEmpresa, string $q, string $tipoDocumento = 'FACTURA', int $limit = 15): array
    {
        $f = $this->fuente(['tipo_documento' => $tipoDocumento]);
        // Busca por nombre (descripción) o por código de línea, igual que el filtro
        // producto_texto del reporte (ver buildWhereYParams: descripcion OR codigo_principal).
        $sql = "SELECT DISTINCT TRIM(d.descripcion) AS descripcion, TRIM(COALESCE(d.codigo_principal, '')) AS codigo
                FROM {$f['det']} d
                JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
                WHERE v.id_empresa = :ie AND v.eliminado = false
                  AND d.descripcion IS NOT NULL AND TRIM(d.descripcion) <> ''
                  AND (d.descripcion ILIKE :q OR d.codigo_principal ILIKE :q)
                ORDER BY descripcion
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // valor = lo que queda escrito en el buscador al elegir: siempre el nombre
        // (descripción), igual que si el usuario lo hubiera tecleado a mano — así el
        // filtro producto_texto (que ya busca por descripcion OR codigo_principal)
        // encuentra TODAS las líneas con ese nombre, no solo las que comparten el
        // código exacto de la línea elegida (un mismo producto puede aparecer con
        // código vacío o distinto entre documentos). El código solo se muestra como
        // referencia (sub) en la lista, para que el usuario pueda buscar por código
        // sin que eso reduzca el resultado a una sola variante.
        return array_map(fn($r) => [
            'valor' => $r['descripcion'],
            'label' => $r['descripcion'],
            'sub'   => $r['codigo'],
        ], $rows);
    }

    /**
     * Autocompletado: info adicional (nombre/valor distintos del documento).
     */
    public function buscarInfoAdicional(int $idEmpresa, string $q, string $tipoDocumento = 'FACTURA', int $limit = 15): array
    {
        $f = $this->fuente(['tipo_documento' => $tipoDocumento]);
        $sql = "SELECT DISTINCT va.nombre, va.valor
                FROM {$f['adic']} va
                JOIN {$f['cab']} v ON v.id = va.{$f['fk_adic']}
                WHERE v.id_empresa = :ie AND v.eliminado = false
                  AND COALESCE(va.valor, '') <> ''
                  AND (va.nombre ILIKE :q OR va.valor ILIKE :q)
                ORDER BY va.nombre, va.valor
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($r) => [
            'valor' => $r['valor'],
            'label' => $r['valor'],
            'sub'   => $r['nombre'],
        ], $rows);
    }

    /**
     * Obtiene estadísticas globales para el rango de fechas.
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
            ];
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                SUM(COALESCE(b.base_0, 0)) as total_base_0,
                SUM(COALESCE(b.base_iva, 0)) as total_base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as total_iva,
                SUM(v.importe_total) as gran_total,
                COUNT(v.id) as total_documentos
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'total_base_0'     => (float)($row['total_base_0'] ?? 0),
            'total_base_iva'   => (float)($row['total_base_iva'] ?? 0),
            'total_iva'        => (float)($row['total_iva'] ?? 0),
            'gran_total'       => (float)($row['gran_total'] ?? 0),
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
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', null, false);

        $sql = "
            SELECT
                LOWER(estado) as estado,
                COUNT(*) as cantidad
            FROM {$f['cab']} v
            WHERE {$where}
            GROUP BY LOWER(estado)
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        $resumen = [
            'autorizados' => 0,
            'anulados'    => 0,
            'borradores'  => 0
        ];

        foreach ($rows as $row) {
            $estado = $row['estado'];
            $cantidad = (int) $row['cantidad'];
            // "Autorizados" agrupa los documentos emitidos/válidos (facturas autorizadas y recibos emitidos/facturados)
            if (in_array($estado, ['autorizado', 'autorizada', 'emitido', 'facturado'])) {
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
