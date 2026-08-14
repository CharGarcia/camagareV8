<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Reporte Consolidado de Transacciones: combina en columnas comunes las 8
 * fuentes documentales del sistema (Compras, Retenciones de Compra, Facturas
 * de Venta, Recibos de Venta, Retenciones de Venta, Notas de Crédito, Notas
 * de Débito, Liquidaciones de Compra) para un resumen a nivel de cabecera
 * (getResumen/getEstadisticas, usado en la vista y el PDF) y ofrece 8
 * consultas de detalle a nivel de línea (una por hoja del Excel).
 *
 * Reglas de origen (evitan duplicar montos entre hojas, ver
 * DocumentoAutomatedRegisterService):
 *  - "Compras" = compras_cabecera con tipo_comprobante = '01' (factura de compra).
 *  - Notas de Crédito de compra (recibidas de proveedor) = compras_cabecera
 *    tipo_comprobante = '04'; se combinan con notas_credito_cabecera (emitidas)
 *    en la hoja "Notas de Crédito", distinguidas por la columna "origen".
 *  - Notas de Débito de compra (recibidas) = compras_cabecera tipo_comprobante
 *    = '05'; se combinan con nota_debito_cabecera (emitidas) en "Notas de Débito".
 *  - Liquidaciones de Compra vive en su propia tabla (liquidaciones_cabecera),
 *    no en compras_cabecera.
 */
class ReporteConsolidadoRepository extends BaseRepository
{
    public function __construct()
    {
        // Tabla base nominal; cada consulta arma su propio FROM.
        parent::__construct('compras_cabecera');
    }

    private function q(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Ambiente de la empresa (los documentos filtran por él). */
    private const AMB = "(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

    /** Grupos de checkbox "Incluir" del filtro (clave => etiqueta). */
    public const GRUPOS = [
        'COMPRAS'          => 'Compras',
        'RETENCION_COMPRA' => 'Retenciones de Compra',
        'FACTURA_VENTA'    => 'Facturas de Venta',
        'RECIBO_VENTA'     => 'Recibos de Venta',
        'RETENCION_VENTA'  => 'Retenciones de Venta',
        'NOTA_CREDITO'     => 'Notas de Crédito',
        'NOTA_DEBITO'      => 'Notas de Débito',
        'LIQUIDACION'      => 'Liquidaciones de Compra',
    ];

    /** ¿Se incluye este grupo dado el filtro de checkboxes? (todos por defecto) */
    private function incluyeGrupo(string $grupo, array $f): bool
    {
        $incluir = $f['incluir'] ?? array_keys(self::GRUPOS);
        return in_array($grupo, $incluir, true);
    }

    /**
     * IVA de una compra (cabecera) sumado desde compras_detalle_impuestos.
     * compras_cabecera NO tiene una columna de IVA propia (a diferencia de otras
     * cabeceras), así que siempre se calcula así — mismo patrón que
     * ReporteComprasRepository::getCteBasesImpuestos().
     */
    private function ivaCompras(string $aliasCabecera): string
    {
        return "COALESCE((SELECT SUM(i.valor) FROM compras_detalle d
                           JOIN compras_detalle_impuestos i ON i.id_compra_detalle = d.id
                           WHERE d.id_compra = {$aliasCabecera}.id AND i.codigo_impuesto = '2'), 0)";
    }

    /**
     * Etiqueta de respaldo por codigoPorcentaje del SRI (tabla 16), usada solo si el
     * código no está en el catálogo `tarifa_iva` de la empresa. Ver app/helpers/SriIvaHelper.php.
     */
    private const IVA_ETIQUETA_FALLBACK = [
        '0' => '0%', '1' => '0%', '2' => '12%', '3' => '14%', '4' => '15%',
        '5' => '5%', '6' => 'No objeto de impuesto', '7' => 'Exento de IVA',
        '8' => 'Tarifa Especial', '10' => '13%',
    ];

    /** Mapa codigo_porcentaje => etiqueta ("15%", "No objeto de impuesto", …), catálogo + respaldo. */
    public function getEtiquetasIva(): array
    {
        $catalogo = $this->q("SELECT codigo, tarifa FROM tarifa_iva")->fetchAll(PDO::FETCH_KEY_PAIR);
        return $catalogo + self::IVA_ETIQUETA_FALLBACK;
    }

    /**
     * Desglose de IVA de una línea, como JSON {codigo_porcentaje: valor}, para pivotear
     * en una columna por tipo de IVA en el Excel (no una sola columna "IVA").
     */
    private function ivaDesgloseJson(string $tablaImpuestos, string $fkCol, string $idLinea): string
    {
        return "COALESCE((SELECT jsonb_object_agg(sub.codigo_porcentaje, sub.valor) FROM (
                    SELECT i.codigo_porcentaje, SUM(i.valor) AS valor
                    FROM $tablaImpuestos i
                    WHERE i.$fkCol = $idLinea AND i.codigo_impuesto = '2'
                    GROUP BY i.codigo_porcentaje
                ) sub), '{}'::jsonb) AS iva_desglose";
    }

    /** Filtro de fechas sobre una columna de fecha de emisión. */
    private function condFecha(string $col, array $f, array &$params, string $sufijo): string
    {
        $w = '';
        if (!empty($f['fecha_desde'])) { $w .= " AND $col >= :fdesde$sufijo"; $params[":fdesde$sufijo"] = $f['fecha_desde']; }
        if (!empty($f['fecha_hasta'])) { $w .= " AND $col <= :fhasta$sufijo"; $params[":fhasta$sufijo"] = $f['fecha_hasta']; }
        return $w;
    }

    /** Excluye anulados salvo que el usuario pida incluirlos explícitamente. */
    private function condNoAnulado(string $col, array $f): string
    {
        if (!empty($f['incluir_anulados'])) return '';
        return " AND LOWER($col) != 'anulado'";
    }

    /**
     * Filtro de texto libre por PALABRAS sobre las columnas normalizadas del
     * UNION (numero, tercero_nombre, tercero_ident).
     */
    private function filtroTexto(array $f, array &$params): string
    {
        $q = trim($f['buscar'] ?? '');
        if ($q === '') return '';
        $campos = ['numero', 'tercero_nombre', 'tercero_ident'];
        $palabras = preg_split('/\s+/', $q) ?: [];
        $where = '';
        foreach ($palabras as $i => $palabra) {
            $palabra = trim($palabra);
            if ($palabra === '') continue;
            $p = ':bq' . $i;
            $params[$p] = '%' . $palabra . '%';
            $ors = array_map(fn($c) => "$c ILIKE $p", $campos);
            $where .= ' AND (' . implode(' OR ', $ors) . ')';
        }
        return $where;
    }

    // ── Ramas del UNION a nivel de cabecera (resumen) ──────────────────────────

    private function selectResumen(string $rama, array $f, array &$params): string
    {
        switch ($rama) {
            case 'COMPRAS':
                return "SELECT 'COMPRAS'::varchar AS tipo_documento, c.id AS id_documento,
                               c.fecha_emision AS fecha,
                               (c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov) AS numero,
                               'PROVEEDOR'::varchar AS tercero_tipo,
                               COALESCE(p.razon_social, '—') AS tercero_nombre,
                               COALESCE(p.identificacion, '') AS tercero_ident,
                               c.total_sin_impuestos AS subtotal, " . $this->ivaCompras('c') . " AS iva, c.importe_total AS total,
                               'registrado'::varchar AS estado, NULL::varchar AS origen
                        FROM compras_cabecera c
                        LEFT JOIN proveedores p ON p.id = c.id_proveedor
                        WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB . "
                              AND c.tipo_comprobante = '01'
                              " . $this->condFecha('c.fecha_emision', $f, $params, '_cp');
                        // compras_cabecera no tiene columna "estado": no hay concepto de anulado que filtrar aquí.

            case 'RETENCION_COMPRA':
                return "SELECT 'RETENCION_COMPRA'::varchar AS tipo_documento, c.id AS id_documento,
                               c.fecha_emision AS fecha,
                               (c.establecimiento || '-' || c.punto_emision || '-' || c.secuencial) AS numero,
                               'PROVEEDOR'::varchar AS tercero_tipo,
                               COALESCE(p.razon_social, '—') AS tercero_nombre,
                               COALESCE(p.identificacion, '') AS tercero_ident,
                               0::numeric AS subtotal, 0::numeric AS iva, c.total_retenido AS total,
                               c.estado AS estado, NULL::varchar AS origen
                        FROM retencion_compra_cabecera c
                        LEFT JOIN proveedores p ON p.id = c.id_proveedor
                        WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB . "
                              " . $this->condFecha('c.fecha_emision', $f, $params, '_rc') . $this->condNoAnulado('c.estado', $f);

            case 'FACTURA_VENTA':
                return "SELECT 'FACTURA_VENTA'::varchar AS tipo_documento, v.id AS id_documento,
                               v.fecha_emision AS fecha,
                               (v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial) AS numero,
                               'CLIENTE'::varchar AS tercero_tipo,
                               COALESCE(cl.nombre, '—') AS tercero_nombre,
                               COALESCE(cl.identificacion, '') AS tercero_ident,
                               v.total_sin_impuestos AS subtotal,
                               COALESCE((SELECT SUM(vi.valor) FROM ventas_detalle vd
                                         JOIN ventas_detalle_impuestos vi ON vi.id_venta_detalle = vd.id
                                         WHERE vd.id_venta = v.id AND vi.codigo_impuesto = '2'), 0) AS iva,
                               v.importe_total AS total, v.estado AS estado, NULL::varchar AS origen
                        FROM ventas_cabecera v
                        LEFT JOIN clientes cl ON cl.id = v.id_cliente
                        WHERE v.id_empresa = :id_empresa AND v.eliminado = false AND v.tipo_ambiente = " . self::AMB . "
                              " . $this->condFecha('v.fecha_emision', $f, $params, '_fv') . $this->condNoAnulado('v.estado', $f);

            case 'RECIBO_VENTA':
                return "SELECT 'RECIBO_VENTA'::varchar AS tipo_documento, r.id AS id_documento,
                               r.fecha_emision AS fecha,
                               (r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial) AS numero,
                               'CLIENTE'::varchar AS tercero_tipo,
                               COALESCE(cl.nombre, '—') AS tercero_nombre,
                               COALESCE(cl.identificacion, '') AS tercero_ident,
                               r.total_sin_impuestos AS subtotal,
                               COALESCE((SELECT SUM(ri.valor) FROM recibos_venta_detalle rd
                                         JOIN recibos_venta_detalle_impuestos ri ON ri.id_recibo_detalle = rd.id
                                         WHERE rd.id_recibo = r.id AND ri.codigo_impuesto = '2'), 0) AS iva,
                               r.importe_total AS total, r.estado AS estado, NULL::varchar AS origen
                        FROM recibos_venta_cabecera r
                        LEFT JOIN clientes cl ON cl.id = r.id_cliente
                        WHERE r.id_empresa = :id_empresa AND r.eliminado = false AND r.tipo_ambiente = " . self::AMB . "
                              " . $this->condFecha('r.fecha_emision', $f, $params, '_rv') . $this->condNoAnulado('r.estado', $f);

            case 'RETENCION_VENTA':
                return "SELECT 'RETENCION_VENTA'::varchar AS tipo_documento, c.id AS id_documento,
                               c.fecha_emision AS fecha,
                               (c.establecimiento || '-' || c.punto_emision || '-' || c.secuencial) AS numero,
                               'CLIENTE'::varchar AS tercero_tipo,
                               COALESCE(cl.nombre, '—') AS tercero_nombre,
                               COALESCE(cl.identificacion, '') AS tercero_ident,
                               0::numeric AS subtotal, 0::numeric AS iva,
                               (c.total_renta + c.total_iva + c.total_isd) AS total,
                               c.origen AS estado, NULL::varchar AS origen
                        FROM retencion_venta_cabecera c
                        LEFT JOIN clientes cl ON cl.id = c.id_cliente
                        WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB . "
                              " . $this->condFecha('c.fecha_emision', $f, $params, '_rvt');

            case 'NOTA_CREDITO_VENTA':
                return "SELECT 'NOTA_CREDITO'::varchar AS tipo_documento, nc.id AS id_documento,
                               nc.fecha_emision AS fecha,
                               (nc.establecimiento || '-' || nc.punto_emision || '-' || nc.secuencial) AS numero,
                               'CLIENTE'::varchar AS tercero_tipo,
                               COALESCE(cl.nombre, '—') AS tercero_nombre,
                               COALESCE(cl.identificacion, '') AS tercero_ident,
                               nc.total_sin_impuestos AS subtotal,
                               COALESCE((SELECT SUM(ndi.valor) FROM notas_credito_detalle ndd
                                         JOIN notas_credito_detalle_impuestos ndi ON ndi.id_nota_credito_detalle = ndd.id
                                         WHERE ndd.id_nota_credito = nc.id AND ndi.codigo_impuesto = '2'), 0) AS iva,
                               nc.importe_total AS total, nc.estado AS estado, 'VENTA'::varchar AS origen
                        FROM notas_credito_cabecera nc
                        LEFT JOIN clientes cl ON cl.id = nc.id_cliente
                        WHERE nc.id_empresa = :id_empresa AND nc.eliminado = false AND nc.tipo_ambiente = " . self::AMB . "
                              " . $this->condFecha('nc.fecha_emision', $f, $params, '_ncv') . $this->condNoAnulado('nc.estado', $f);

            case 'NOTA_CREDITO_COMPRA':
                return "SELECT 'NOTA_CREDITO'::varchar AS tipo_documento, c.id AS id_documento,
                               c.fecha_emision AS fecha,
                               (c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov) AS numero,
                               'PROVEEDOR'::varchar AS tercero_tipo,
                               COALESCE(p.razon_social, '—') AS tercero_nombre,
                               COALESCE(p.identificacion, '') AS tercero_ident,
                               c.total_sin_impuestos AS subtotal, " . $this->ivaCompras('c') . " AS iva, c.importe_total AS total,
                               'registrado'::varchar AS estado, 'COMPRA'::varchar AS origen
                        FROM compras_cabecera c
                        LEFT JOIN proveedores p ON p.id = c.id_proveedor
                        WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB . "
                              AND c.tipo_comprobante = '04'
                              " . $this->condFecha('c.fecha_emision', $f, $params, '_ncc');

            case 'NOTA_DEBITO_VENTA':
                return "SELECT 'NOTA_DEBITO'::varchar AS tipo_documento, nd.id AS id_documento,
                               nd.fecha_emision AS fecha,
                               (nd.establecimiento || '-' || nd.punto_emision || '-' || nd.secuencial) AS numero,
                               'CLIENTE'::varchar AS tercero_tipo,
                               COALESCE(cl.nombre, '—') AS tercero_nombre,
                               COALESCE(cl.identificacion, '') AS tercero_ident,
                               nd.total_sin_impuestos AS subtotal,
                               COALESCE((SELECT SUM(ndi.valor) FROM nota_debito_impuestos ndi
                                         WHERE ndi.id_nota_debito = nd.id AND ndi.codigo_impuesto = '2'), 0) AS iva,
                               nd.importe_total AS total, nd.estado AS estado, 'VENTA'::varchar AS origen
                        FROM nota_debito_cabecera nd
                        LEFT JOIN clientes cl ON cl.id = nd.id_cliente
                        WHERE nd.id_empresa = :id_empresa AND nd.eliminado = false AND nd.tipo_ambiente = " . self::AMB . "
                              " . $this->condFecha('nd.fecha_emision', $f, $params, '_ndv') . $this->condNoAnulado('nd.estado', $f);

            case 'NOTA_DEBITO_COMPRA':
                return "SELECT 'NOTA_DEBITO'::varchar AS tipo_documento, c.id AS id_documento,
                               c.fecha_emision AS fecha,
                               (c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov) AS numero,
                               'PROVEEDOR'::varchar AS tercero_tipo,
                               COALESCE(p.razon_social, '—') AS tercero_nombre,
                               COALESCE(p.identificacion, '') AS tercero_ident,
                               c.total_sin_impuestos AS subtotal, " . $this->ivaCompras('c') . " AS iva, c.importe_total AS total,
                               'registrado'::varchar AS estado, 'COMPRA'::varchar AS origen
                        FROM compras_cabecera c
                        LEFT JOIN proveedores p ON p.id = c.id_proveedor
                        WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB . "
                              AND c.tipo_comprobante = '05'
                              " . $this->condFecha('c.fecha_emision', $f, $params, '_ndc');

            case 'LIQUIDACION':
                return "SELECT 'LIQUIDACION'::varchar AS tipo_documento, l.id AS id_documento,
                               l.fecha_emision AS fecha,
                               (l.establecimiento || '-' || l.punto_emision || '-' || l.secuencial) AS numero,
                               'PROVEEDOR'::varchar AS tercero_tipo,
                               COALESCE(p.razon_social, '—') AS tercero_nombre,
                               COALESCE(p.identificacion, '') AS tercero_ident,
                               l.total_sin_impuestos AS subtotal,
                               COALESCE((SELECT SUM(li.valor) FROM liquidaciones_detalle ld
                                         JOIN liquidaciones_detalle_impuestos li ON li.id_detalle = ld.id
                                         WHERE ld.id_cabecera = l.id AND li.codigo_impuesto = '2'), 0) AS iva,
                               l.importe_total AS total, l.estado AS estado, NULL::varchar AS origen
                        FROM liquidaciones_cabecera l
                        LEFT JOIN proveedores p ON p.id = l.id_proveedor
                        WHERE l.id_empresa = :id_empresa AND l.eliminado = false AND l.tipo_ambiente = " . self::AMB . "
                              " . $this->condFecha('l.fecha_emision', $f, $params, '_lq') . $this->condNoAnulado('l.estado', $f);

            default:
                throw new \InvalidArgumentException("Rama de reporte consolidado no soportada: $rama");
        }
    }

    /** Mapa rama interna => grupo de checkbox al que pertenece. */
    private const RAMA_GRUPO = [
        'COMPRAS'            => 'COMPRAS',
        'RETENCION_COMPRA'   => 'RETENCION_COMPRA',
        'FACTURA_VENTA'      => 'FACTURA_VENTA',
        'RECIBO_VENTA'       => 'RECIBO_VENTA',
        'RETENCION_VENTA'    => 'RETENCION_VENTA',
        'NOTA_CREDITO_VENTA' => 'NOTA_CREDITO',
        'NOTA_CREDITO_COMPRA'=> 'NOTA_CREDITO',
        'NOTA_DEBITO_VENTA'  => 'NOTA_DEBITO',
        'NOTA_DEBITO_COMPRA' => 'NOTA_DEBITO',
        'LIQUIDACION'        => 'LIQUIDACION',
    ];

    /** UNION de las ramas aplicables según los checkboxes. Devuelve [sql, params]. */
    private function armarUnion(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $ramas = [];
        foreach (self::RAMA_GRUPO as $rama => $grupo) {
            if (!$this->incluyeGrupo($grupo, $f)) continue;
            $ramas[] = $this->selectResumen($rama, $f, $params);
        }
        if (empty($ramas)) {
            $ramas[] = $this->selectResumen('COMPRAS', $f, $params) . " AND 1=0";
        }
        return [implode("\n UNION ALL \n", $ramas), $params];
    }

    // ── Consultas públicas: resumen (cabecera) ─────────────────────────────────

    /** Listado consolidado a nivel de cabecera (vista + PDF). $limite = 0 => sin tope. */
    public function getResumen(int $idEmpresa, array $f, int $limite = 5000): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT * FROM ( $union ) r WHERE 1=1 $textoWhere
                ORDER BY fecha DESC, numero DESC";
        if ($limite > 0) $sql .= " LIMIT $limite";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** KPIs: cantidad y total por cada uno de los 8 grupos, más totales generales. */
    public function getEstadisticas(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT tipo_documento,
                       COUNT(*) AS n_documentos,
                       COALESCE(SUM(subtotal), 0) AS total_subtotal,
                       COALESCE(SUM(iva), 0)      AS total_iva,
                       COALESCE(SUM(total), 0)    AS total_general
                FROM ( $union ) r WHERE 1=1 $textoWhere
                GROUP BY tipo_documento";
        $porTipo = $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        $resumen = ['n_documentos' => 0, 'total_general' => 0, 'total_compras' => 0, 'total_ventas' => 0, 'por_tipo' => []];
        foreach ($porTipo as $row) {
            $tipo = $row['tipo_documento'];
            $resumen['por_tipo'][$tipo] = $row;
            $resumen['n_documentos'] += (int) $row['n_documentos'];
            $resumen['total_general'] += (float) $row['total_general'];
            if (in_array($tipo, ['COMPRAS', 'RETENCION_COMPRA', 'LIQUIDACION'], true)) {
                $resumen['total_compras'] += (float) $row['total_general'];
            }
            if (in_array($tipo, ['FACTURA_VENTA', 'RECIBO_VENTA'], true)) {
                $resumen['total_ventas'] += (float) $row['total_general'];
            }
        }
        $resumen['neto'] = $resumen['total_ventas'] - $resumen['total_compras'];
        return $resumen;
    }

    /** Años con al menos un documento (de cualquiera de las 8 fuentes), para el selector de período. */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio FROM (
                    SELECT fecha_emision FROM compras_cabecera            WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM retencion_compra_cabecera   WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM ventas_cabecera             WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM recibos_venta_cabecera      WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM retencion_venta_cabecera    WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM notas_credito_cabecera      WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM nota_debito_cabecera        WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM liquidaciones_cabecera      WHERE id_empresa = :e AND eliminado = false
                ) x ORDER BY anio DESC";
        $anios = array_map('intval', $this->q($sql, [':e' => $idEmpresa])->fetchAll(PDO::FETCH_COLUMN));
        $anioActual = (int) date('Y');
        if (!in_array($anioActual, $anios, true)) {
            array_unshift($anios, $anioActual);
        }
        return $anios;
    }

    // ── Consultas públicas: detalle por hoja (Excel) ────────────────────────────

    /** Detalle línea por línea de Compras (tipo_comprobante = '01'). */
    public function getDetalleCompras(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $sql = "SELECT c.fecha_emision AS fecha,
                       (c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov) AS numero_documento,
                       c.numero_autorizacion,
                       p.razon_social AS proveedor_nombre, p.identificacion AS proveedor_ruc,
                       COALESCE(d.codigo_principal, d.codigo_auxiliar, '') AS codigo,
                       d.descripcion, d.cantidad, d.precio_unitario, d.descuento,
                       d.precio_total_sin_impuesto AS subtotal_linea,
                       " . $this->ivaDesgloseJson('compras_detalle_impuestos', 'id_compra_detalle', 'd.id') . ",
                       (d.precio_total_sin_impuesto + COALESCE((SELECT SUM(i.valor) FROM compras_detalle_impuestos i WHERE i.id_compra_detalle = d.id), 0)) AS total_linea,
                       'registrado'::varchar AS estado
                FROM compras_detalle d
                JOIN compras_cabecera c ON c.id = d.id_compra
                LEFT JOIN proveedores p ON p.id = c.id_proveedor
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB . "
                      AND c.tipo_comprobante = '01'
                      " . $this->condFecha('c.fecha_emision', $f, $params, '') . "
                ORDER BY c.fecha_emision, numero_documento";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Detalle línea por línea (impuesto) de Retenciones de Compra. */
    public function getDetalleRetencionesCompra(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $sql = "SELECT c.fecha_emision AS fecha,
                       (c.establecimiento || '-' || c.punto_emision || '-' || c.secuencial) AS numero_documento,
                       c.clave_acceso, p.razon_social AS proveedor_nombre, p.identificacion AS proveedor_ruc,
                       c.tipo_doc_sustento AS cod_doc_sustento, c.num_doc_sustento,
                       d.codigo_impuesto, d.codigo_retencion,
                       COALESCE(NULLIF(d.concepto, ''), rs.concepto_ret, '') AS concepto,
                       d.base_imponible, d.porcentaje_retener AS porcentaje, d.valor_retenido,
                       c.total_retenido, c.estado
                FROM retencion_compra_cabecera c
                JOIN retencion_compra_detalle d ON d.id_retencion = c.id
                LEFT JOIN proveedores p ON p.id = c.id_proveedor
                LEFT JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB . "
                      " . $this->condFecha('c.fecha_emision', $f, $params, '') . $this->condNoAnulado('c.estado', $f) . "
                ORDER BY c.fecha_emision, numero_documento";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Detalle línea por línea de Facturas de Venta. */
    public function getDetalleFacturasVenta(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $sql = "SELECT v.fecha_emision AS fecha,
                       (v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial) AS numero_documento,
                       v.clave_acceso,
                       cl.nombre AS cliente_nombre, cl.identificacion AS cliente_ident,
                       COALESCE(d.codigo_principal, d.codigo_auxiliar, '') AS codigo,
                       d.descripcion, d.cantidad, d.precio_unitario, d.descuento,
                       d.precio_total_sin_impuesto AS subtotal_linea,
                       " . $this->ivaDesgloseJson('ventas_detalle_impuestos', 'id_venta_detalle', 'd.id') . ",
                       (d.precio_total_sin_impuesto + COALESCE((SELECT SUM(i.valor) FROM ventas_detalle_impuestos i WHERE i.id_venta_detalle = d.id), 0)) AS total_linea,
                       v.estado
                FROM ventas_detalle d
                JOIN ventas_cabecera v ON v.id = d.id_venta
                LEFT JOIN clientes cl ON cl.id = v.id_cliente
                WHERE v.id_empresa = :id_empresa AND v.eliminado = false AND v.tipo_ambiente = " . self::AMB . "
                      " . $this->condFecha('v.fecha_emision', $f, $params, '') . $this->condNoAnulado('v.estado', $f) . "
                ORDER BY v.fecha_emision, numero_documento";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Detalle línea por línea de Recibos de Venta. */
    public function getDetalleRecibosVenta(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $sql = "SELECT r.fecha_emision AS fecha,
                       (r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial) AS numero_documento,
                       r.con_impuestos,
                       cl.nombre AS cliente_nombre, cl.identificacion AS cliente_ident,
                       COALESCE(d.codigo_principal, d.codigo_auxiliar, '') AS codigo,
                       d.descripcion, d.cantidad, d.precio_unitario, d.descuento,
                       d.precio_total_sin_impuesto AS subtotal_linea,
                       " . $this->ivaDesgloseJson('recibos_venta_detalle_impuestos', 'id_recibo_detalle', 'd.id') . ",
                       (d.precio_total_sin_impuesto + COALESCE((SELECT SUM(i.valor) FROM recibos_venta_detalle_impuestos i WHERE i.id_recibo_detalle = d.id), 0)) AS total_linea,
                       r.estado
                FROM recibos_venta_detalle d
                JOIN recibos_venta_cabecera r ON r.id = d.id_recibo
                LEFT JOIN clientes cl ON cl.id = r.id_cliente
                WHERE r.id_empresa = :id_empresa AND r.eliminado = false AND r.tipo_ambiente = " . self::AMB . "
                      " . $this->condFecha('r.fecha_emision', $f, $params, '') . $this->condNoAnulado('r.estado', $f) . "
                ORDER BY r.fecha_emision, numero_documento";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Detalle línea por línea (impuesto) de Retenciones de Venta. */
    public function getDetalleRetencionesVenta(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $sql = "SELECT c.fecha_emision AS fecha,
                       (c.establecimiento || '-' || c.punto_emision || '-' || c.secuencial) AS numero_documento,
                       c.clave_acceso, cl.nombre AS cliente_nombre, cl.identificacion AS cliente_ident,
                       d.cod_doc_sustento, d.num_doc_sustento,
                       d.codigo_impuesto, d.codigo_retencion,
                       COALESCE(rs.concepto_ret, '') AS concepto,
                       d.base_imponible, d.porcentaje_retencion AS porcentaje, d.valor_retenido,
                       (c.total_renta + c.total_iva + c.total_isd) AS total_comprobante, c.origen
                FROM retencion_venta_cabecera c
                JOIN retencion_venta_detalle d ON d.id_retencion = c.id
                LEFT JOIN clientes cl ON cl.id = c.id_cliente
                LEFT JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB . "
                      " . $this->condFecha('c.fecha_emision', $f, $params, '') . "
                ORDER BY c.fecha_emision, numero_documento";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Detalle línea por línea de Notas de Crédito (venta emitidas UNION compra recibidas). */
    public function getDetalleNotasCredito(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa, ':id_empresa2' => $idEmpresa];
        $sql = "SELECT 'VENTA'::varchar AS origen, nc.fecha_emision AS fecha,
                       (nc.establecimiento || '-' || nc.punto_emision || '-' || nc.secuencial) AS numero_documento,
                       cl.nombre AS tercero_nombre, cl.identificacion AS tercero_ident,
                       nc.num_doc_modificado AS doc_modificado, nc.motivo,
                       COALESCE(d.codigo_principal, d.codigo_auxiliar, '') AS codigo,
                       d.descripcion, d.cantidad, d.precio_unitario,
                       d.precio_total_sin_impuesto AS subtotal_linea,
                       " . $this->ivaDesgloseJson('notas_credito_detalle_impuestos', 'id_nota_credito_detalle', 'd.id') . ",
                       (d.precio_total_sin_impuesto + COALESCE((SELECT SUM(i.valor) FROM notas_credito_detalle_impuestos i WHERE i.id_nota_credito_detalle = d.id), 0)) AS total_linea,
                       nc.estado
                FROM notas_credito_detalle d
                JOIN notas_credito_cabecera nc ON nc.id = d.id_nota_credito
                LEFT JOIN clientes cl ON cl.id = nc.id_cliente
                WHERE nc.id_empresa = :id_empresa AND nc.eliminado = false AND nc.tipo_ambiente = " . self::AMB . "
                      " . $this->condFecha('nc.fecha_emision', $f, $params, '') . $this->condNoAnulado('nc.estado', $f) . "
                UNION ALL
                SELECT 'COMPRA'::varchar AS origen, c.fecha_emision AS fecha,
                       (c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov) AS numero_documento,
                       p.razon_social AS tercero_nombre, p.identificacion AS tercero_ident,
                       c.documento_modificado AS doc_modificado, c.motivo,
                       COALESCE(d.codigo_principal, d.codigo_auxiliar, '') AS codigo,
                       d.descripcion, d.cantidad, d.precio_unitario,
                       d.precio_total_sin_impuesto AS subtotal_linea,
                       " . $this->ivaDesgloseJson('compras_detalle_impuestos', 'id_compra_detalle', 'd.id') . ",
                       (d.precio_total_sin_impuesto + COALESCE((SELECT SUM(i.valor) FROM compras_detalle_impuestos i WHERE i.id_compra_detalle = d.id), 0)) AS total_linea,
                       'registrado'::varchar AS estado
                FROM compras_detalle d
                JOIN compras_cabecera c ON c.id = d.id_compra
                LEFT JOIN proveedores p ON p.id = c.id_proveedor
                WHERE c.id_empresa = :id_empresa2 AND c.eliminado = false AND c.tipo_ambiente = " . str_replace(':id_empresa', ':id_empresa2', self::AMB) . "
                      AND c.tipo_comprobante = '04'
                      " . $this->condFecha('c.fecha_emision', $f, $params, '2') . "
                ORDER BY fecha, numero_documento";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Detalle de Notas de Débito (venta emitidas [motivo/valor] UNION compra recibidas [línea de producto]). */
    public function getDetalleNotasDebito(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa, ':id_empresa2' => $idEmpresa];
        $sql = "SELECT 'VENTA'::varchar AS origen, nd.fecha_emision AS fecha,
                       (nd.establecimiento || '-' || nd.punto_emision || '-' || nd.secuencial) AS numero_documento,
                       cl.nombre AS tercero_nombre, cl.identificacion AS tercero_ident,
                       nd.num_doc_modificado AS doc_modificado,
                       m.razon AS descripcion, NULL::numeric AS cantidad, m.valor AS subtotal_linea,
                       CASE WHEN ROW_NUMBER() OVER (PARTITION BY nd.id ORDER BY m.id) = 1
                            THEN COALESCE((SELECT jsonb_object_agg(sub.codigo_porcentaje, sub.valor) FROM (
                                    SELECT i.codigo_porcentaje, SUM(i.valor) AS valor
                                    FROM nota_debito_impuestos i
                                    WHERE i.id_nota_debito = nd.id AND i.codigo_impuesto = '2'
                                    GROUP BY i.codigo_porcentaje
                                 ) sub), '{}'::jsonb)
                            ELSE '{}'::jsonb
                       END AS iva_desglose,
                       m.valor AS total_linea, nd.estado
                FROM nota_debito_motivos m
                JOIN nota_debito_cabecera nd ON nd.id = m.id_nota_debito
                LEFT JOIN clientes cl ON cl.id = nd.id_cliente
                WHERE nd.id_empresa = :id_empresa AND nd.eliminado = false AND nd.tipo_ambiente = " . self::AMB . "
                      " . $this->condFecha('nd.fecha_emision', $f, $params, '') . $this->condNoAnulado('nd.estado', $f) . "
                UNION ALL
                SELECT 'COMPRA'::varchar AS origen, c.fecha_emision AS fecha,
                       (c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov) AS numero_documento,
                       p.razon_social AS tercero_nombre, p.identificacion AS tercero_ident,
                       c.documento_modificado AS doc_modificado,
                       d.descripcion, d.cantidad, d.precio_total_sin_impuesto AS subtotal_linea,
                       " . $this->ivaDesgloseJson('compras_detalle_impuestos', 'id_compra_detalle', 'd.id') . ",
                       (d.precio_total_sin_impuesto + COALESCE((SELECT SUM(i.valor) FROM compras_detalle_impuestos i WHERE i.id_compra_detalle = d.id), 0)) AS total_linea,
                       'registrado'::varchar AS estado
                FROM compras_detalle d
                JOIN compras_cabecera c ON c.id = d.id_compra
                LEFT JOIN proveedores p ON p.id = c.id_proveedor
                WHERE c.id_empresa = :id_empresa2 AND c.eliminado = false AND c.tipo_ambiente = " . str_replace(':id_empresa', ':id_empresa2', self::AMB) . "
                      AND c.tipo_comprobante = '05'
                      " . $this->condFecha('c.fecha_emision', $f, $params, '2') . "
                ORDER BY fecha, numero_documento";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Detalle línea por línea de Liquidaciones de Compra. */
    public function getDetalleLiquidaciones(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $sql = "SELECT l.fecha_emision AS fecha,
                       (l.establecimiento || '-' || l.punto_emision || '-' || l.secuencial) AS numero_documento,
                       p.razon_social AS proveedor_nombre, p.identificacion AS proveedor_ruc,
                       COALESCE(d.codigo_principal, d.codigo_auxiliar, '') AS codigo,
                       d.descripcion, d.cantidad, d.precio_unitario, d.descuento,
                       d.precio_total_sin_impuesto AS subtotal_linea,
                       " . $this->ivaDesgloseJson('liquidaciones_detalle_impuestos', 'id_detalle', 'd.id') . ",
                       (d.precio_total_sin_impuesto + COALESCE((SELECT SUM(i.valor) FROM liquidaciones_detalle_impuestos i WHERE i.id_detalle = d.id), 0)) AS total_linea,
                       l.estado
                FROM liquidaciones_detalle d
                JOIN liquidaciones_cabecera l ON l.id = d.id_cabecera
                LEFT JOIN proveedores p ON p.id = l.id_proveedor
                WHERE l.id_empresa = :id_empresa AND l.eliminado = false AND l.tipo_ambiente = " . self::AMB . "
                      " . $this->condFecha('l.fecha_emision', $f, $params, '') . $this->condNoAnulado('l.estado', $f) . "
                ORDER BY l.fecha_emision, numero_documento";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
