<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Reporte combinado de Retenciones (Compras + Ventas).
 * Normaliza ambos orígenes (retencion_compra_* / retencion_venta_*) a columnas
 * comunes a nivel de línea de impuesto (detalle) y aplica filtros combinables.
 */
class ReporteRetencionesRepository extends BaseRepository
{
    public function __construct()
    {
        // Tabla base nominal; las consultas del reporte arman su propio FROM.
        parent::__construct('retencion_compra_cabecera');
    }

    private function q(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Ambiente de la empresa (los documentos filtran por él). */
    private const AMB = "(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

    /** Impuestos normalizados (valor canónico usado en las consultas y filtros). */
    public const IMPUESTOS = ['RENTA' => 'Renta', 'IVA' => 'IVA', 'ISD' => 'ISD'];

    /**
     * codigo_impuesto llega inconsistente en los datos (a veces código SRI
     * '1'/'2'/'6', a veces texto 'RENTA'/'IVA'/'ISD'): se normaliza a texto
     * canónico tanto en el SELECT como en el WHERE para poder filtrar/agrupar.
     */
    private function impuestoCase(string $col): string
    {
        return "(CASE WHEN UPPER($col) IN ('1','RENTA') THEN 'RENTA'
                       WHEN UPPER($col) IN ('2','IVA')   THEN 'IVA'
                       WHEN UPPER($col) IN ('6','ISD')   THEN 'ISD'
                       ELSE UPPER($col) END)";
    }

    // ── WHERE por tipo ────────────────────────────────────────────────────────

    /** Filtros a nivel cabecera (alias c), comunes a compra y venta. */
    private function whereCabecera(bool $esCompra, array $f, array &$params): string
    {
        $w = "c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB;
        if (!empty($f['fecha_desde'])) { $w .= " AND c.fecha_emision >= :fdesde"; $params[':fdesde'] = $f['fecha_desde']; }
        if (!empty($f['fecha_hasta'])) { $w .= " AND c.fecha_emision <= :fhasta"; $params[':fhasta'] = $f['fecha_hasta']; }
        if (!empty($f['anio'])) { $w .= " AND EXTRACT(YEAR FROM c.fecha_emision) = :anio"; $params[':anio'] = (int)$f['anio']; }
        if (!empty($f['mes']))  { $w .= " AND EXTRACT(MONTH FROM c.fecha_emision) = :mes";  $params[':mes']  = (int)$f['mes']; }
        if (!empty($f['tercero_id']) && !empty($f['tercero_tipo'])) {
            if ($esCompra && $f['tercero_tipo'] === 'PROVEEDOR') {
                $w .= " AND c.id_proveedor = :tercero_id"; $params[':tercero_id'] = (int)$f['tercero_id'];
            } elseif (!$esCompra && $f['tercero_tipo'] === 'CLIENTE') {
                $w .= " AND c.id_cliente = :tercero_id"; $params[':tercero_id'] = (int)$f['tercero_id'];
            }
        }
        if ($esCompra && !empty($f['estado']) && strtoupper($f['estado']) !== 'TODOS') {
            $w .= " AND c.estado = :estado"; $params[':estado'] = strtolower($f['estado']);
        }
        return $w;
    }

    /** WHERE a nivel detalle (alias c=cabecera, d=detalle). */
    private function whereDetalle(string $tipo, array $f, array &$params): string
    {
        $esCompra = $tipo === 'COMPRA';
        $w = $this->whereCabecera($esCompra, $f, $params);
        if (!empty($f['codigo_impuesto'])) { $w .= " AND " . $this->impuestoCase('d.codigo_impuesto') . " = :codimp"; $params[':codimp'] = strtoupper($f['codigo_impuesto']); }
        if (!empty($f['codigo_retencion'])) { $w .= " AND d.codigo_retencion = :codret"; $params[':codret'] = $f['codigo_retencion']; }
        return $w;
    }

    /** SELECT normalizado de un tipo (nivel detalle: una fila por línea de impuesto). */
    private function selectDetalle(string $tipo): string
    {
        if ($tipo === 'COMPRA') {
            return "SELECT 'COMPRA'::varchar AS tipo_retencion,
                           c.id AS id_comprobante,
                           c.fecha_emision AS fecha,
                           (c.establecimiento || '-' || c.punto_emision || '-' || c.secuencial) AS numero,
                           c.clave_acceso AS clave_acceso,
                           c.periodo_fiscal AS periodo_fiscal,
                           c.estado AS estado,
                           'PROVEEDOR'::varchar AS tercero_tipo,
                           COALESCE(t.razon_social, '—') AS tercero_nombre,
                           COALESCE(t.identificacion, '') AS tercero_ident,
                           c.tipo_doc_sustento   AS cod_doc_sustento,
                           c.num_doc_sustento    AS num_doc_sustento,
                           c.fecha_emision_doc_sustento AS fecha_doc_sustento,
                           " . $this->impuestoCase('d.codigo_impuesto') . " AS codigo_impuesto,
                           d.codigo_retencion    AS codigo_retencion,
                           COALESCE(NULLIF(d.concepto, ''), rs.concepto_ret, '') AS concepto,
                           d.base_imponible      AS base_imponible,
                           d.porcentaje_retener  AS porcentaje,
                           d.valor_retenido      AS valor_retenido,
                           c.total_retenido      AS total_comprobante
                    FROM retencion_compra_cabecera c
                    INNER JOIN retencion_compra_detalle d ON d.id_retencion = c.id
                    LEFT  JOIN proveedores t   ON t.id = c.id_proveedor
                    LEFT  JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion";
        }
        return "SELECT 'VENTA'::varchar AS tipo_retencion,
                       c.id AS id_comprobante,
                       c.fecha_emision AS fecha,
                       (c.establecimiento || '-' || c.punto_emision || '-' || c.secuencial) AS numero,
                       c.clave_acceso AS clave_acceso,
                       c.periodo_fiscal AS periodo_fiscal,
                       c.origen AS estado,
                       'CLIENTE'::varchar AS tercero_tipo,
                       COALESCE(t.nombre, '—') AS tercero_nombre,
                       COALESCE(t.identificacion, '') AS tercero_ident,
                       d.cod_doc_sustento    AS cod_doc_sustento,
                       d.num_doc_sustento    AS num_doc_sustento,
                       d.fecha_emision_doc_sustento AS fecha_doc_sustento,
                       " . $this->impuestoCase('d.codigo_impuesto') . " AS codigo_impuesto,
                       d.codigo_retencion    AS codigo_retencion,
                       COALESCE(rs.concepto_ret, '') AS concepto,
                       d.base_imponible      AS base_imponible,
                       d.porcentaje_retencion AS porcentaje,
                       d.valor_retenido      AS valor_retenido,
                       (c.total_renta + c.total_iva + c.total_isd) AS total_comprobante
                FROM retencion_venta_cabecera c
                INNER JOIN retencion_venta_detalle d ON d.id_retencion = c.id
                LEFT  JOIN clientes t ON t.id = c.id_cliente
                LEFT  JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion";
    }

    /** ¿Se incluye este tipo dado el filtro de tipo/tercero? */
    private function incluyeTipo(string $tipo, array $f): bool
    {
        $t = strtoupper($f['tipo_retencion'] ?? 'AMBOS');
        if ($t === 'COMPRA' && $tipo !== 'COMPRA') return false;
        if ($t === 'VENTA'  && $tipo !== 'VENTA')  return false;
        // Tercero fuerza el tipo: proveedor→compras, cliente→ventas
        if (!empty($f['tercero_tipo'])) {
            if ($f['tercero_tipo'] === 'PROVEEDOR' && $tipo !== 'COMPRA') return false;
            if ($f['tercero_tipo'] === 'CLIENTE'   && $tipo !== 'VENTA')  return false;
        }
        // Estado sólo aplica a compras: si se filtra, excluye ventas
        if (!empty($f['estado']) && strtoupper($f['estado']) !== 'TODOS' && $tipo !== 'COMPRA') return false;
        return true;
    }

    /** UNION de los tipos aplicables con sus WHERE. Devuelve [sql, params]. */
    private function armarUnion(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $ramas  = [];
        foreach (['COMPRA', 'VENTA'] as $tipo) {
            if (!$this->incluyeTipo($tipo, $f)) continue;
            $ramas[] = $this->selectDetalle($tipo) . "\n WHERE " . $this->whereDetalle($tipo, $f, $params);
        }
        if (empty($ramas)) {
            $ramas[] = $this->selectDetalle('COMPRA') . " WHERE 1=0";
        }
        return [implode("\n UNION ALL \n", $ramas), $params];
    }

    /**
     * Filtro de texto libre por PALABRAS: cada palabra debe aparecer en algún
     * campo (AND entre palabras, OR entre campos).
     */
    private function filtroTexto(array $f, array &$params, ?array $campos = null): string
    {
        $q = trim($f['buscar'] ?? '');
        if ($q === '') return '';
        $campos = $campos ?? ['numero', 'tercero_nombre', 'tercero_ident', 'num_doc_sustento', 'concepto', 'clave_acceso'];
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

    // ── Consultas públicas ────────────────────────────────────────────────────

    /** Listado por línea de impuesto (detalle) — nivel más granular, toda la información. */
    public function getReporteDetallado(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT * FROM ( $union ) r WHERE 1=1 $textoWhere
                ORDER BY fecha DESC, numero DESC, codigo_impuesto
                LIMIT 5000";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Resumen por comprobante (una fila por documento, con desglose Renta/IVA/ISD). */
    public function getReporteAgrupadoCabecera(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT tipo_retencion, id_comprobante, numero, fecha, periodo_fiscal, estado,
                       tercero_tipo, tercero_nombre, tercero_ident,
                       COUNT(*) AS n_lineas,
                       SUM(CASE WHEN codigo_impuesto = 'RENTA' THEN valor_retenido ELSE 0 END) AS total_renta,
                       SUM(CASE WHEN codigo_impuesto = 'IVA'   THEN valor_retenido ELSE 0 END) AS total_iva,
                       SUM(CASE WHEN codigo_impuesto = 'ISD'   THEN valor_retenido ELSE 0 END) AS total_isd,
                       SUM(valor_retenido) AS total
                FROM ( $union ) r WHERE 1=1 $textoWhere
                GROUP BY tipo_retencion, id_comprobante, numero, fecha, periodo_fiscal, estado,
                         tercero_tipo, tercero_nombre, tercero_ident
                ORDER BY fecha DESC, numero DESC";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Agrupado por tercero (cliente/proveedor). */
    public function getReporteAgrupadoTercero(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT tipo_retencion, tercero_tipo, tercero_nombre, MAX(tercero_ident) AS tercero_ident,
                       COUNT(DISTINCT id_comprobante) AS comprobantes,
                       COUNT(*)             AS lineas,
                       SUM(valor_retenido)  AS total
                FROM ( $union ) r WHERE 1=1 $textoWhere
                GROUP BY tipo_retencion, tercero_tipo, tercero_nombre
                ORDER BY tipo_retencion, total DESC";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totales generales (Renta/IVA/ISD) y conteos. */
    public function getEstadisticas(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN codigo_impuesto = 'RENTA' THEN valor_retenido ELSE 0 END), 0) AS total_renta,
                    COALESCE(SUM(CASE WHEN codigo_impuesto = 'IVA'   THEN valor_retenido ELSE 0 END), 0) AS total_iva,
                    COALESCE(SUM(CASE WHEN codigo_impuesto = 'ISD'   THEN valor_retenido ELSE 0 END), 0) AS total_isd,
                    COALESCE(SUM(valor_retenido), 0) AS total_general,
                    COUNT(DISTINCT CASE WHEN tipo_retencion = 'COMPRA' THEN id_comprobante END) AS n_compras,
                    COUNT(DISTINCT CASE WHEN tipo_retencion = 'VENTA'  THEN id_comprobante END) AS n_ventas,
                    COUNT(*) AS n_lineas
                FROM ( $union ) r WHERE 1=1 $textoWhere";
        $row = $this->q($sql, $params)->fetch(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    // ── Catálogos para los filtros ────────────────────────────────────────────

    public function getAnios(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio FROM (
                    SELECT fecha_emision FROM retencion_compra_cabecera WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM retencion_venta_cabecera  WHERE id_empresa = :e AND eliminado = false
                ) x ORDER BY anio DESC";
        return array_map('intval', $this->q($sql, [':e' => $idEmpresa])->fetchAll(PDO::FETCH_COLUMN));
    }

    public function getConceptosSri(): array
    {
        return $this->q("SELECT DISTINCT codigo_ret, concepto_ret, impuesto_ret FROM retenciones_sri ORDER BY impuesto_ret, codigo_ret")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Autocomplete de terceros por tipo (CLIENTE|PROVEEDOR). */
    public function buscarTerceros(int $idEmpresa, string $tipo, string $q): array
    {
        $q = '%' . trim($q) . '%';
        if ($tipo === 'PROVEEDOR') {
            $sql = "SELECT id, razon_social AS nombre, identificacion AS ident FROM proveedores
                    WHERE id_empresa = :e AND eliminado = false AND (razon_social ILIKE :q OR identificacion ILIKE :q)
                    ORDER BY razon_social LIMIT 15";
        } else {
            $sql = "SELECT id, nombre, identificacion AS ident FROM clientes
                    WHERE id_empresa = :e AND eliminado = false AND (nombre ILIKE :q OR identificacion ILIKE :q)
                    ORDER BY nombre LIMIT 15";
        }
        return $this->q($sql, [':e' => $idEmpresa, ':q' => $q])->fetchAll(PDO::FETCH_ASSOC);
    }
}
