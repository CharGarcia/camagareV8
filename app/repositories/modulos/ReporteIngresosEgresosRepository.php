<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Reporte combinado de Ingresos y Egresos.
 * Normaliza ambos flujos (cabecera + detalle + pagos) a columnas comunes y
 * aplica filtros combinables. Nivel de fila: por documento (detalle).
 */
class ReporteIngresosEgresosRepository extends BaseRepository
{
    public function __construct()
    {
        // Tabla base nominal; las consultas del reporte arman su propio FROM.
        parent::__construct('ingresos_cabecera');
    }

    private function q(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Ambiente de la empresa (los documentos filtran por él). */
    private const AMB = "(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

    // ── WHERE por flujo ───────────────────────────────────────────────────────

    /** Filtros a nivel cabecera (alias c), comunes a detalle y pagos. */
    private function whereCabecera(bool $esIng, array $f, array &$params): string
    {
        $w = "c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB;
        if (!empty($f['fecha_desde'])) { $w .= " AND c.fecha_emision >= :fdesde"; $params[':fdesde'] = $f['fecha_desde']; }
        if (!empty($f['fecha_hasta'])) { $w .= " AND c.fecha_emision <= :fhasta"; $params[':fhasta'] = $f['fecha_hasta']; }
        if (!empty($f['estado']) && strtoupper($f['estado']) !== 'TODOS') {
            $w .= " AND c.estado = :estado"; $params[':estado'] = strtolower($f['estado']);
        }
        if ($f['monto_min'] !== '' && $f['monto_min'] !== null) { $w .= " AND c.monto_total >= :montomin"; $params[':montomin'] = (float)$f['monto_min']; }
        if ($f['monto_max'] !== '' && $f['monto_max'] !== null) { $w .= " AND c.monto_total <= :montomax"; $params[':montomax'] = (float)$f['monto_max']; }
        if (!empty($f['id_concepto'])) {
            $col = $esIng ? 'id_ingreso_concepto' : 'id_egreso_concepto';
            $w .= " AND c.$col = :concepto"; $params[':concepto'] = (int)$f['id_concepto'];
        }
        if (!empty($f['tercero_id']) && !empty($f['tercero_tipo'])) {
            if ($esIng && $f['tercero_tipo'] === 'CLIENTE') {
                $w .= " AND (c.id_cliente = :tercero_id OR c.id_recibo_cliente = :tercero_id)";
                $params[':tercero_id'] = (int)$f['tercero_id'];
            } elseif (!$esIng && $f['tercero_tipo'] === 'PROVEEDOR') {
                $w .= " AND c.id_proveedor = :tercero_id"; $params[':tercero_id'] = (int)$f['tercero_id'];
            } elseif (!$esIng && $f['tercero_tipo'] === 'EMPLEADO') {
                $w .= " AND c.id_empleado = :tercero_id"; $params[':tercero_id'] = (int)$f['tercero_id'];
            }
        }
        return $w;
    }

    /**
     * WHERE a nivel detalle (alias c=cabecera, d=detalle).
     * @param string $flujo 'INGRESO' | 'EGRESO'
     */
    private function whereFlujo(string $flujo, array $f, array &$params): string
    {
        $esIng    = $flujo === 'INGRESO';
        $pagTbl   = $esIng ? 'ingresos_pagos'  : 'egresos_pagos';
        $pagFk    = $esIng ? 'id_ingreso'      : 'id_egreso';
        $pagForma = $esIng ? 'id_forma_cobro'  : 'id_forma_pago';

        $w = $this->whereCabecera($esIng, $f, $params);
        if (!$esIng) { $w .= " AND d.eliminado = false"; }

        if (!empty($f['id_forma'])) {
            $w .= " AND EXISTS (SELECT 1 FROM $pagTbl pp WHERE pp.$pagFk = c.id AND pp.$pagForma = :forma)";
            $params[':forma'] = (int)$f['id_forma'];
        }
        if (!empty($f['operacion_bancaria'])) {
            $w .= " AND EXISTS (SELECT 1 FROM $pagTbl po WHERE po.$pagFk = c.id AND po.tipo_operacion_bancaria = :opbanc)";
            $params[':opbanc'] = $f['operacion_bancaria'];
        }
        if (!empty($f['tipo_documento'])) {
            $w .= " AND d.tipo_documento = :tipodoc"; $params[':tipodoc'] = $f['tipo_documento'];
        }
        return $w;
    }

    /** WHERE a nivel pagos (alias c=cabecera, p=pagos) para agrupar por forma. */
    private function whereFlujoPagos(string $flujo, array $f, array &$params): string
    {
        $esIng   = $flujo === 'INGRESO';
        $detTbl  = $esIng ? 'ingresos_detalle' : 'egresos_detalle';
        $detFk   = $esIng ? 'id_ingreso'       : 'id_egreso';
        $pagForma= $esIng ? 'id_forma_cobro'   : 'id_forma_pago';

        $w = $this->whereCabecera($esIng, $f, $params);
        if (!$esIng) { $w .= " AND p.eliminado = false"; }

        if (!empty($f['id_forma'])) { $w .= " AND p.$pagForma = :forma"; $params[':forma'] = (int)$f['id_forma']; }
        if (!empty($f['operacion_bancaria'])) { $w .= " AND p.tipo_operacion_bancaria = :opbanc"; $params[':opbanc'] = $f['operacion_bancaria']; }
        if (!empty($f['tipo_documento'])) {
            $extra = $esIng ? '' : ' AND dd.eliminado = false';
            $w .= " AND EXISTS (SELECT 1 FROM $detTbl dd WHERE dd.$detFk = c.id AND dd.tipo_documento = :tipodoc$extra)";
            $params[':tipodoc'] = $f['tipo_documento'];
        }
        return $w;
    }

    /** SELECT normalizado de un flujo (nivel detalle). */
    private function selectFlujo(string $flujo): string
    {
        if ($flujo === 'INGRESO') {
            return "SELECT 'INGRESO'::varchar AS tipo_flujo,
                           c.id AS id_comprobante,
                           c.numero_ingreso AS numero,
                           c.fecha_emision  AS fecha,
                           'CLIENTE'::varchar AS tercero_tipo,
                           COALESCE(cli.nombre, c.recibo_de, '—') AS tercero_nombre,
                           COALESCE(cli.identificacion, '')       AS tercero_ident,
                           oc.nombre        AS concepto,
                           c.estado         AS estado,
                           c.monto_total    AS monto_comprobante,
                           d.tipo_documento AS tipo_documento,
                           d.numero_documento AS numero_documento,
                           d.descripcion    AS descripcion,
                           d.monto_cobrado  AS monto,
                           c.observaciones  AS observaciones
                    FROM ingresos_cabecera c
                    INNER JOIN ingresos_detalle d ON d.id_ingreso = c.id
                    LEFT  JOIN clientes cli ON cli.id = COALESCE(c.id_cliente, c.id_recibo_cliente)
                    LEFT  JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_ingreso_concepto";
        }
        return "SELECT 'EGRESO'::varchar AS tipo_flujo,
                       c.id AS id_comprobante,
                       c.numero_egreso  AS numero,
                       c.fecha_emision  AS fecha,
                       CASE WHEN c.tipo_sujeto = 'EMPLEADO' THEN 'EMPLEADO' ELSE 'PROVEEDOR' END AS tercero_tipo,
                       COALESCE(pr.razon_social, emp.nombres_apellidos, '—') AS tercero_nombre,
                       COALESCE(pr.identificacion, emp.identificacion, '')   AS tercero_ident,
                       oc.nombre        AS concepto,
                       c.estado         AS estado,
                       c.monto_total    AS monto_comprobante,
                       d.tipo_documento AS tipo_documento,
                       d.numero_documento AS numero_documento,
                       d.descripcion    AS descripcion,
                       d.monto_pagado   AS monto,
                       c.observaciones  AS observaciones
                FROM egresos_cabecera c
                INNER JOIN egresos_detalle d ON d.id_egreso = c.id
                LEFT  JOIN proveedores pr  ON pr.id  = c.id_proveedor
                LEFT  JOIN empleados   emp ON emp.id = c.id_empleado
                LEFT  JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_egreso_concepto";
    }

    /** SELECT normalizado de pagos de un flujo (para agrupar por forma). */
    private function selectFlujoPagos(string $flujo): string
    {
        if ($flujo === 'INGRESO') {
            return "SELECT 'INGRESO'::varchar AS tipo_flujo,
                           fp.nombre AS forma_nombre, fp.tipo AS forma_tipo,
                           p.monto  AS monto,
                           c.id AS id_comprobante, c.numero_ingreso AS numero,
                           COALESCE(cli.nombre, c.recibo_de, '—') AS tercero_nombre
                    FROM ingresos_pagos p
                    INNER JOIN ingresos_cabecera c ON c.id = p.id_ingreso
                    INNER JOIN empresa_formas_pago fp ON fp.id = p.id_forma_cobro
                    LEFT  JOIN clientes cli ON cli.id = COALESCE(c.id_cliente, c.id_recibo_cliente)";
        }
        return "SELECT 'EGRESO'::varchar AS tipo_flujo,
                       fp.nombre AS forma_nombre, fp.tipo AS forma_tipo,
                       p.monto  AS monto,
                       c.id AS id_comprobante, c.numero_egreso AS numero,
                       COALESCE(pr.razon_social, emp.nombres_apellidos, '—') AS tercero_nombre
                FROM egresos_pagos p
                INNER JOIN egresos_cabecera c ON c.id = p.id_egreso
                INNER JOIN empresa_formas_pago fp ON fp.id = p.id_forma_pago
                LEFT  JOIN proveedores pr  ON pr.id  = c.id_proveedor
                LEFT  JOIN empleados   emp ON emp.id = c.id_empleado";
    }

    /** UNION de pagos de los flujos aplicables. Devuelve [sql, params]. */
    private function armarUnionPagos(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $ramas  = [];
        foreach (['INGRESO', 'EGRESO'] as $flujo) {
            if (!$this->incluyeFlujo($flujo, $f)) continue;
            $ramas[] = $this->selectFlujoPagos($flujo) . "\n WHERE " . $this->whereFlujoPagos($flujo, $f, $params);
        }
        if (empty($ramas)) { $ramas[] = $this->selectFlujoPagos('INGRESO') . " WHERE 1=0"; }
        return [implode("\n UNION ALL \n", $ramas), $params];
    }

    /** ¿Se incluye este flujo dado el filtro de tipo/tercero? */
    private function incluyeFlujo(string $flujo, array $f): bool
    {
        $t = strtoupper($f['tipo_flujo'] ?? 'AMBOS');
        if ($t === 'INGRESO' && $flujo !== 'INGRESO') return false;
        if ($t === 'EGRESO'  && $flujo !== 'EGRESO')  return false;
        // Tercero fuerza el flujo: cliente→ingresos, proveedor/empleado→egresos
        if (!empty($f['tercero_tipo'])) {
            if ($f['tercero_tipo'] === 'CLIENTE'  && $flujo !== 'INGRESO') return false;
            if (in_array($f['tercero_tipo'], ['PROVEEDOR', 'EMPLEADO'], true) && $flujo !== 'EGRESO') return false;
        }
        return true;
    }

    /** UNION de los flujos aplicables con sus WHERE. Devuelve [sql, params]. */
    private function armarUnion(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $ramas  = [];
        foreach (['INGRESO', 'EGRESO'] as $flujo) {
            if (!$this->incluyeFlujo($flujo, $f)) continue;
            $ramas[] = $this->selectFlujo($flujo) . "\n WHERE " . $this->whereFlujo($flujo, $f, $params);
        }
        if (empty($ramas)) {
            // Ningún flujo aplica: forzar resultado vacío consistente
            $ramas[] = $this->selectFlujo('INGRESO') . " WHERE 1=0";
        }
        return [implode("\n UNION ALL \n", $ramas), $params];
    }

    /**
     * Filtro de texto libre por PALABRAS: cada palabra debe aparecer en algún
     * campo (AND entre palabras, OR entre campos). Así "carlos garcia" encuentra
     * "Carlos Mauricio Garcia…". El orden y las palabras intermedias no importan.
     */
    private function filtroTexto(array $f, array &$params, ?array $campos = null): string
    {
        $q = trim($f['buscar'] ?? '');
        if ($q === '') return '';
        $campos = $campos ?? ['numero', 'tercero_nombre', 'tercero_ident', 'numero_documento', 'descripcion', 'observaciones', 'concepto'];
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

    /** Listado por documento (detalle). */
    public function getReporteDetallado(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT * FROM ( $union ) r WHERE 1=1 $textoWhere
                ORDER BY fecha DESC, numero DESC, tipo_documento
                LIMIT 5000";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Agrupado por tercero (cliente/proveedor/empleado). */
    public function getReporteAgrupadoTercero(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT tipo_flujo, tercero_tipo, tercero_nombre, MAX(tercero_ident) AS tercero_ident,
                       COUNT(DISTINCT id_comprobante) AS comprobantes,
                       COUNT(*)                       AS documentos,
                       SUM(monto)                     AS total
                FROM ( $union ) r WHERE 1=1 $textoWhere
                GROUP BY tipo_flujo, tercero_tipo, tercero_nombre
                ORDER BY tipo_flujo, total DESC";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Agrupado por forma de cobro/pago (a nivel pagos). */
    public function getReporteAgrupadoForma(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnionPagos($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params, ['numero', 'tercero_nombre', 'forma_nombre']);
        $sql = "SELECT tipo_flujo, forma_nombre, MAX(forma_tipo) AS forma_tipo,
                       COUNT(DISTINCT id_comprobante) AS comprobantes,
                       COUNT(*)   AS pagos_n,
                       SUM(monto) AS total
                FROM ( $union ) r WHERE 1=1 $textoWhere
                GROUP BY tipo_flujo, forma_nombre
                ORDER BY tipo_flujo, total DESC";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Agrupado por fecha (total por día: ingresos, egresos, neto). */
    public function getReporteAgrupadoFecha(int $idEmpresa, array $f): array
    {
        return $this->agrupadoPorPeriodo($idEmpresa, $f, "r.fecha::date", 'periodo');
    }

    /** Agrupado por mes. */
    public function getReporteAgrupadoMes(int $idEmpresa, array $f): array
    {
        return $this->agrupadoPorPeriodo($idEmpresa, $f, "to_char(r.fecha, 'YYYY-MM')", 'periodo');
    }

    private function agrupadoPorPeriodo(int $idEmpresa, array $f, string $expr, string $alias): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT $expr AS $alias,
                    COALESCE(SUM(CASE WHEN tipo_flujo='INGRESO' THEN monto ELSE 0 END), 0) AS ingresos,
                    COALESCE(SUM(CASE WHEN tipo_flujo='EGRESO'  THEN monto ELSE 0 END), 0) AS egresos,
                    COUNT(DISTINCT CASE WHEN tipo_flujo='INGRESO' THEN id_comprobante END) AS n_ing,
                    COUNT(DISTINCT CASE WHEN tipo_flujo='EGRESO'  THEN id_comprobante END) AS n_egr
                FROM ( $union ) r WHERE 1=1 $textoWhere
                GROUP BY $expr
                ORDER BY $alias DESC";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totales: ingresos, egresos, neto y conteos. */
    public function getEstadisticas(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN tipo_flujo='INGRESO' THEN monto ELSE 0 END), 0) AS total_ingresos,
                    COALESCE(SUM(CASE WHEN tipo_flujo='EGRESO'  THEN monto ELSE 0 END), 0) AS total_egresos,
                    COUNT(DISTINCT CASE WHEN tipo_flujo='INGRESO' THEN id_comprobante END) AS n_ingresos,
                    COUNT(DISTINCT CASE WHEN tipo_flujo='EGRESO'  THEN id_comprobante END) AS n_egresos,
                    COUNT(*) AS n_documentos
                FROM ( $union ) r WHERE 1=1 $textoWhere";
        $row = $this->q($sql, $params)->fetch(PDO::FETCH_ASSOC) ?: [];
        $row['neto'] = (float)($row['total_ingresos'] ?? 0) - (float)($row['total_egresos'] ?? 0);
        return $row;
    }

    // ── Catálogos para los filtros ────────────────────────────────────────────

    public function getFormasPago(int $idEmpresa): array
    {
        return $this->q("SELECT id, nombre, tipo FROM empresa_formas_pago
                         WHERE id_empresa = :e AND eliminado = false AND activo = true
                         ORDER BY nombre", [':e' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConceptos(int $idEmpresa): array
    {
        return $this->q("SELECT id, nombre, aplica_ingresos, aplica_egresos FROM empresa_opciones_ingreso_egreso
                         WHERE id_empresa = :e AND eliminado = false AND UPPER(estado) = 'ACTIVO'
                         ORDER BY nombre", [':e' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAnios(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio FROM (
                    SELECT fecha_emision FROM ingresos_cabecera WHERE id_empresa = :e AND eliminado = false
                    UNION ALL
                    SELECT fecha_emision FROM egresos_cabecera  WHERE id_empresa = :e AND eliminado = false
                ) x ORDER BY anio DESC";
        return array_map('intval', $this->q($sql, [':e' => $idEmpresa])->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Autocomplete de terceros por tipo (CLIENTE|PROVEEDOR|EMPLEADO). */
    public function buscarTerceros(int $idEmpresa, string $tipo, string $q): array
    {
        $q = '%' . trim($q) . '%';
        if ($tipo === 'PROVEEDOR') {
            $sql = "SELECT id, razon_social AS nombre, identificacion AS ident FROM proveedores
                    WHERE id_empresa = :e AND eliminado = false AND (razon_social ILIKE :q OR identificacion ILIKE :q)
                    ORDER BY razon_social LIMIT 15";
        } elseif ($tipo === 'EMPLEADO') {
            $sql = "SELECT id, nombres_apellidos AS nombre, identificacion AS ident FROM empleados
                    WHERE id_empresa = :e AND eliminado = false AND (nombres_apellidos ILIKE :q OR identificacion ILIKE :q)
                    ORDER BY nombres_apellidos LIMIT 15";
        } else {
            $sql = "SELECT id, nombre, identificacion AS ident FROM clientes
                    WHERE id_empresa = :e AND eliminado = false AND (nombre ILIKE :q OR identificacion ILIKE :q)
                    ORDER BY nombre LIMIT 15";
        }
        return $this->q($sql, [':e' => $idEmpresa, ':q' => $q])->fetchAll(PDO::FETCH_ASSOC);
    }
}
