<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AbonosVentaSql;
use App\Helpers\AmbienteReporte;
use App\repositories\BaseRepository;
use PDO;

/**
 * Resumen diario: todo lo que se movió en UN día en la empresa activa.
 *  - Ventas: facturas, recibos, notas de débito y notas de crédito emitidas, y las
 *    retenciones que los clientes hicieron.
 *  - Compras: facturas de proveedor (y sus NC/ND), liquidaciones de compra y las
 *    retenciones que la empresa emitió.
 *  - Caja: los Ingresos (cobros) y Egresos (pagos) del día, una fila por forma de pago.
 *
 * Solo lectura. Regla de reportes: solo documentos de PRODUCCIÓN (AmbienteReporte).
 * Cada consulta filtra id_empresa + eliminado = false + la fecha, y —si el usuario no
 * tiene acceso total— solo lo que él registró (§6, $idUsuarioFiltro).
 */
class ReporteResumenDiarioRepository extends BaseRepository
{
    public function __construct()
    {
        // Tabla base nominal; cada consulta arma su propio FROM.
        parent::__construct('ventas_cabecera');
    }

    /**
     * WHERE común de una cabecera: empresa, no eliminada, producción, el día y, si
     * aplica, solo los registros del usuario ($colUsuario: expresión de quién lo creó).
     */
    private function base(string $a, int $idEmpresa, string $fecha, ?int $idUsuarioFiltro, string $colUsuario, array &$params): string
    {
        $params[':id_empresa'] = $idEmpresa;
        $params[':fecha']      = $fecha;
        $w = "{$a}.id_empresa = :id_empresa AND {$a}.eliminado = false AND " . AmbienteReporte::condicion($a)
           . " AND {$a}.fecha_emision = :fecha";
        if ($idUsuarioFiltro !== null) {
            $w .= " AND {$colUsuario} = :id_usuario_filtro";
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        return $w;
    }

    /** Estados de un comprobante electrónico que cuentan: autorizado y, si se pide, borrador. */
    private static function estadoElectronico(string $a, bool $conBorradores): string
    {
        $ok = "LOWER({$a}.estado) IN ('autorizado', 'autorizada'" . ($conBorradores ? ", 'borrador'" : '') . ')';
        return " AND {$ok}";
    }

    private function filas(string $sql, array $params): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Ventas ────────────────────────────────────────────────────────────────

    /**
     * Facturas del día con su saldo pendiente HOY, con la regla de Cuentas por Cobrar
     * (AbonosVentaSql): total + notas de débito − cobros − retenciones − notas de crédito,
     * nunca negativo.
     */
    public function getFacturas(int $idEmpresa, string $fecha, bool $conBorradores, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base('v', $idEmpresa, $fecha, $idUsuarioFiltro, 'COALESCE(v.created_by, v.id_usuario)', $p)
           . self::estadoElectronico('v', $conBorradores);
        return $this->filas("
            SELECT v.id, CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AS numero,
                   COALESCE(cl.nombre, '') AS tercero, COALESCE(cl.identificacion, '') AS identificacion,
                   '' AS referencia, v.estado,
                   COALESCE(v.total_sin_impuestos, 0) AS subtotal, COALESCE(v.importe_total, 0) AS total,
                   GREATEST(COALESCE(v.importe_total, 0)
                            + " . AbonosVentaSql::subNotasFactura('nota_debito_cabecera', 'v') . "
                            - " . self::subCobrado('FACTURA', 'v') . "
                            - " . AbonosVentaSql::subRetenidoFactura('v') . "
                            - " . AbonosVentaSql::subNotasFactura('notas_credito_cabecera', 'v') . ", 0) AS saldo
              FROM ventas_cabecera v
              LEFT JOIN clientes cl ON cl.id = v.id_cliente
             WHERE {$w}
             ORDER BY v.establecimiento, v.punto_emision, v.secuencial, v.id", $p);
    }

    /**
     * Recibos de venta: cuentan todos menos los anulados y los facturados (un recibo
     * facturado ya está en la factura que generó). Su borrador es el estado normal de un
     * recibo a crédito, no un documento pendiente: el selector de borradores no aplica.
     */
    public function getRecibos(int $idEmpresa, string $fecha, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base('r', $idEmpresa, $fecha, $idUsuarioFiltro, 'COALESCE(r.created_by, r.id_usuario)', $p)
           . " AND LOWER(r.estado) NOT IN ('anulado', 'facturado')";
        return $this->filas("
            SELECT r.id, COALESCE(NULLIF(TRIM(r.recibo_numero), ''),
                                  CONCAT(r.establecimiento, '-', r.punto_emision, '-', r.secuencial)) AS numero,
                   COALESCE(cl.nombre, '') AS tercero, COALESCE(cl.identificacion, '') AS identificacion,
                   '' AS referencia, r.estado,
                   COALESCE(r.total_sin_impuestos, 0) AS subtotal, COALESCE(r.importe_total, 0) AS total,
                   GREATEST(COALESCE(r.importe_total, 0) - " . self::subCobrado('RECIBO', 'r') . ", 0) AS saldo
              FROM recibos_venta_cabecera r
              LEFT JOIN clientes cl ON cl.id = r.id_cliente
             WHERE {$w}
             ORDER BY 2, r.id", $p);
    }

    /** Lo cobrado HASTA HOY a un documento por Ingresos no anulados (misma regla que el Reporte de Ventas). */
    private static function subCobrado(string $tipo, string $alias): string
    {
        return "(SELECT COALESCE(SUM(idt.monto_cobrado), 0)
                   FROM ingresos_detalle idt
                   JOIN ingresos_cabecera icb ON icb.id = idt.id_ingreso
                  WHERE idt.tipo_documento = '{$tipo}' AND idt.id_referencia_documento = {$alias}.id
                    AND icb.estado <> 'anulado' AND icb.eliminado = false AND icb.id_empresa = {$alias}.id_empresa)";
    }

    /** Notas de crédito ($tabla = notas_credito_cabecera) o de débito (nota_debito_cabecera) de venta. */
    public function getNotasVenta(string $tabla, int $idEmpresa, string $fecha, bool $conBorradores, ?int $idUsuarioFiltro): array
    {
        if (!in_array($tabla, ['notas_credito_cabecera', 'nota_debito_cabecera'], true)) {
            throw new \InvalidArgumentException('Tabla no permitida.');
        }
        $p = [];
        $w = $this->base('n', $idEmpresa, $fecha, $idUsuarioFiltro, 'COALESCE(n.created_by, n.id_usuario)', $p)
           . self::estadoElectronico('n', $conBorradores);
        return $this->filas("
            SELECT n.id, CONCAT(n.establecimiento, '-', n.punto_emision, '-', n.secuencial) AS numero,
                   COALESCE(cl.nombre, '') AS tercero, COALESCE(cl.identificacion, '') AS identificacion,
                   COALESCE(n.num_doc_modificado, '') AS referencia, n.estado,
                   COALESCE(n.total_sin_impuestos, 0) AS subtotal, COALESCE(n.importe_total, 0) AS total
              FROM {$tabla} n
              LEFT JOIN clientes cl ON cl.id = n.id_cliente
             WHERE {$w}
             ORDER BY n.establecimiento, n.punto_emision, n.secuencial, n.id", $p);
    }

    /**
     * Retenciones que hicieron los clientes (fecha de emisión de la retención). El
     * documento sustento es la factura enlazada o, si no está enlazada, los números de
     * sustento de sus líneas. No tienen estado: cuentan todas las no eliminadas.
     */
    public function getRetencionesVenta(int $idEmpresa, string $fecha, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base('r', $idEmpresa, $fecha, $idUsuarioFiltro, 'r.created_by', $p);
        return $this->filas("
            SELECT r.id, CONCAT(r.establecimiento, '-', r.punto_emision, '-', r.secuencial) AS numero,
                   COALESCE(cl.nombre, '') AS tercero, COALESCE(cl.identificacion, '') AS identificacion,
                   COALESCE(CASE WHEN v.id IS NOT NULL THEN CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) END,
                            (SELECT STRING_AGG(DISTINCT d.num_doc_sustento, ', ')
                               FROM retencion_venta_detalle d WHERE d.id_retencion = r.id), '') AS referencia,
                   COALESCE(r.total_renta, 0) AS renta,
                   COALESCE(r.total_iva, 0) + COALESCE(r.total_isd, 0) AS iva,
                   COALESCE(r.total_renta, 0) + COALESCE(r.total_iva, 0) + COALESCE(r.total_isd, 0) AS total
              FROM retencion_venta_cabecera r
              LEFT JOIN clientes cl ON cl.id = r.id_cliente
              LEFT JOIN ventas_cabecera v ON v.id = r.id_venta
             WHERE {$w}
             ORDER BY r.establecimiento, r.punto_emision, r.secuencial, r.id", $p);
    }

    // ── Compras ───────────────────────────────────────────────────────────────

    /**
     * Comprobantes de proveedor registrados en Compras con fecha de emisión del día.
     * $tipo: 'NC' = solo notas de crédito (tipo 04, restan); 'OTROS' = todo lo demás
     * (facturas, notas de débito, etc.). Las anuladas o rechazadas no cuentan.
     */
    public function getCompras(string $tipo, int $idEmpresa, string $fecha, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base('c', $idEmpresa, $fecha, $idUsuarioFiltro, 'COALESCE(c.created_by, c.id_usuario)', $p)
           . " AND COALESCE(LOWER(c.estado), '') NOT IN ('anulado', 'anulada', 'rechazado', 'rechazada')"
           . ($tipo === 'NC' ? " AND c.tipo_comprobante = '04'" : " AND COALESCE(c.tipo_comprobante, '') <> '04'");
        return $this->filas("
            SELECT c.id, CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero,
                   COALESCE(pr.razon_social, '') AS tercero, COALESCE(pr.identificacion, '') AS identificacion,
                   " . ($tipo === 'NC'
                        ? "COALESCE(c.documento_modificado, '')"
                        : "BTRIM(COALESCE(ca.comprobante, c.tipo_comprobante, ''), E' \\t\\r\\n')") . " AS referencia,
                   COALESCE(c.estado, '') AS estado,
                   COALESCE(c.total_sin_impuestos, 0) AS subtotal, COALESCE(c.importe_total, 0) AS total
              FROM compras_cabecera c
              LEFT JOIN proveedores pr ON pr.id = c.id_proveedor
              LEFT JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
             WHERE {$w}
             ORDER BY pr.razon_social, 2, c.id", $p);
    }

    public function getLiquidaciones(int $idEmpresa, string $fecha, bool $conBorradores, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base('l', $idEmpresa, $fecha, $idUsuarioFiltro, 'COALESCE(l.created_by, l.id_usuario)', $p)
           . self::estadoElectronico('l', $conBorradores);
        return $this->filas("
            SELECT l.id, CONCAT(l.establecimiento, '-', l.punto_emision, '-', l.secuencial) AS numero,
                   COALESCE(pr.razon_social, '') AS tercero, COALESCE(pr.identificacion, '') AS identificacion,
                   '' AS referencia, l.estado,
                   COALESCE(l.total_sin_impuestos, 0) AS subtotal, COALESCE(l.importe_total, 0) AS total
              FROM liquidaciones_cabecera l
              LEFT JOIN proveedores pr ON pr.id = l.id_proveedor
             WHERE {$w}
             ORDER BY l.establecimiento, l.punto_emision, l.secuencial, l.id", $p);
    }

    /** Retenciones emitidas a proveedores: renta (impuesto 1) e IVA/ISD por las líneas. */
    public function getRetencionesCompra(int $idEmpresa, string $fecha, bool $conBorradores, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base('r', $idEmpresa, $fecha, $idUsuarioFiltro, 'COALESCE(r.created_by, r.id_usuario)', $p)
           . self::estadoElectronico('r', $conBorradores);
        return $this->filas("
            SELECT r.id, CONCAT(r.establecimiento, '-', r.punto_emision, '-', r.secuencial) AS numero,
                   COALESCE(pr.razon_social, '') AS tercero, COALESCE(pr.identificacion, '') AS identificacion,
                   COALESCE(r.num_doc_sustento, '') AS referencia,
                   COALESCE(d.renta, 0) AS renta,
                   COALESCE(r.total_retenido, 0) - COALESCE(d.renta, 0) AS iva,
                   COALESCE(r.total_retenido, 0) AS total
              FROM retencion_compra_cabecera r
              LEFT JOIN proveedores pr ON pr.id = r.id_proveedor
              LEFT JOIN LATERAL (SELECT SUM(x.valor_retenido) FILTER (WHERE TRIM(x.codigo_impuesto) = '1') AS renta
                                   FROM retencion_compra_detalle x WHERE x.id_retencion = r.id) d ON true
             WHERE {$w}
             ORDER BY r.establecimiento, r.punto_emision, r.secuencial, r.id", $p);
    }

    // ── Caja: ingresos y egresos ──────────────────────────────────────────────

    /**
     * Ingresos (cobros) del día no anulados, separados en dos grupos:
     *  - 'DIA':   lo que cobró a facturas y recibos EMITIDOS ESE MISMO DÍA.
     *  - 'OTROS': lo demás (documentos de días anteriores, saldos iniciales, otros conceptos).
     * Una fila por Ingreso, grupo y forma de pago. Si un Ingreso cobró documentos de los dos
     * grupos, cada forma de pago se reparte entre los grupos en proporción a lo cobrado en
     * cada uno, así la suma sigue siendo exactamente lo que entró. Un Ingreso sin líneas va
     * entero a 'OTROS'; uno sin formas de pago sale con forma vacía.
     */
    public function getIngresos(int $idEmpresa, string $fecha, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base('c', $idEmpresa, $fecha, $idUsuarioFiltro, 'COALESCE(c.created_by, c.id_usuario)', $p)
           . " AND LOWER(c.estado) <> 'anulado'";
        return $this->filas("
            WITH cab AS (
                SELECT c.id, c.numero_ingreso, c.monto_total, c.id_ingreso_concepto, c.observaciones,
                       COALESCE(NULLIF(TRIM(c.recibo_de), ''), cl.nombre, '') AS tercero
                  FROM ingresos_cabecera c
                  LEFT JOIN clientes cl ON cl.id = COALESCE(c.id_recibo_cliente, c.id_cliente)
                 WHERE {$w}
            ),
            lin AS (
                SELECT d.id, d.id_ingreso, COALESCE(d.monto_cobrado, 0) AS monto,
                       " . self::lineaDetalle('d', 'd.monto_cobrado') . " AS texto,
                       CASE WHEN vf.fecha_emision = :fecha OR vr.fecha_emision = :fecha THEN 'DIA' ELSE 'OTROS' END AS grupo
                  FROM ingresos_detalle d
                  JOIN cab ON cab.id = d.id_ingreso
                  LEFT JOIN ventas_cabecera vf ON d.tipo_documento = 'FACTURA' AND vf.id = d.id_referencia_documento
                  LEFT JOIN recibos_venta_cabecera vr ON d.tipo_documento = 'RECIBO' AND vr.id = d.id_referencia_documento
            ),
            tot AS (SELECT id_ingreso, SUM(monto) AS total FROM lin GROUP BY id_ingreso),
            grp AS (
                SELECT l.id_ingreso, l.grupo, SUM(l.monto) AS monto,
                       STRING_AGG(l.texto, ', ' ORDER BY l.id) AS detalle, t.total
                  FROM lin l JOIN tot t ON t.id_ingreso = l.id_ingreso
                 WHERE t.total > 0
                 GROUP BY l.id_ingreso, l.grupo, t.total
                UNION ALL
                SELECT cab.id, 'OTROS', COALESCE(cab.monto_total, 0), NULL, 0
                  FROM cab LEFT JOIN tot t ON t.id_ingreso = cab.id
                 WHERE COALESCE(t.total, 0) <= 0
            )
            SELECT cab.id, cab.numero_ingreso AS numero, cab.tercero, g.grupo,
                   COALESCE(fp.id, 0) AS id_forma, fp.nombre AS forma,
                   COALESCE(NULLIF(TRIM(pg.numero_cheque), ''), NULLIF(TRIM(pg.referencia), ''), '') AS referencia_pago,
                   CASE WHEN pg.id IS NULL THEN g.monto
                        WHEN g.total > 0 THEN pg.monto * g.monto / g.total
                        ELSE pg.monto END AS valor,
                   COALESCE(g.detalle, '') AS detalle, COALESCE(oc.nombre, '') AS concepto,
                   COALESCE(cab.observaciones, '') AS observaciones
              FROM cab
              JOIN grp g ON g.id_ingreso = cab.id
              LEFT JOIN ingresos_pagos pg ON pg.id_ingreso = cab.id
              LEFT JOIN empresa_formas_pago fp ON fp.id = pg.id_forma_cobro
              LEFT JOIN empresa_opciones_ingreso_egreso oc ON oc.id = cab.id_ingreso_concepto
             ORDER BY g.grupo, cab.numero_ingreso, cab.id, pg.id", $p);
    }

    /**
     * Egresos (pagos) del día no anulados, una fila por forma de pago vigente (sin las
     * formas reemplazadas al editar ni los cheques anulados, igual que el Reporte de
     * Ingresos y Egresos). Pagado a: el beneficiario escrito o el proveedor/empleado.
     */
    public function getEgresos(int $idEmpresa, string $fecha, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base('c', $idEmpresa, $fecha, $idUsuarioFiltro, 'c.created_by', $p)
           . " AND LOWER(c.estado) <> 'anulado'";
        return $this->filas("
            SELECT c.id, c.numero_egreso AS numero,
                   COALESCE(NULLIF(TRIM(c.beneficiario_nombre), ''), pr.razon_social, emp.nombres_apellidos, '') AS tercero,
                   COALESCE(fp.id, 0) AS id_forma, fp.nombre AS forma,
                   COALESCE(NULLIF(TRIM(p.numero_cheque), ''), NULLIF(TRIM(p.referencia), ''), '') AS referencia_pago,
                   COALESCE(p.monto, c.monto_total, 0) AS valor,
                   COALESCE(det.detalle, '') AS detalle, COALESCE(oc.nombre, '') AS concepto,
                   COALESCE(c.observaciones, '') AS observaciones
              FROM egresos_cabecera c
              LEFT JOIN proveedores pr ON pr.id = c.id_proveedor
              LEFT JOIN empleados emp ON emp.id = c.id_empleado
              LEFT JOIN egresos_pagos p ON p.id_egreso = c.id AND p.eliminado = false
                                       AND COALESCE(p.estado_cheque, 'vigente') <> 'anulado'
              LEFT JOIN empresa_formas_pago fp ON fp.id = p.id_forma_pago
              LEFT JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_egreso_concepto
              LEFT JOIN LATERAL (
                    SELECT STRING_AGG(" . self::lineaDetalle('d', 'd.monto_pagado') . ", ', ' ORDER BY d.id) AS detalle
                      FROM egresos_detalle d WHERE d.id_egreso = c.id AND d.eliminado = false) det ON true
             WHERE {$w}
             ORDER BY c.numero_egreso, c.id, p.id", $p);
    }

    // ── Saldos por forma de pago y traslados ──────────────────────────────────

    /** ¿Ya se aplicó database/20261008_caja_saldos_traslados.sql? Sin las tablas, se degrada. */
    public function tablasCajaDisponibles(): bool
    {
        static $ok = null;
        return $ok ??= (bool) $this->db->query(
            "SELECT to_regclass('public.caja_traslados') IS NOT NULL AND to_regclass('public.caja_saldos_apertura') IS NOT NULL"
        )->fetchColumn();
    }

    /**
     * Saldo de cada forma de pago al INICIO del día: su saldo de apertura (si la apertura
     * es de ese día o antes) más los Ingresos, menos los Egresos y +/- los traslados
     * anteriores al día, contados desde la fecha de apertura. Sin apertura, desde el
     * primer movimiento. Solo formas con saldo distinto de cero.
     *
     * @return array<int, array{nombre: string, saldo: float}> id_forma => datos
     */
    public function getSaldosIniciales(int $idEmpresa, string $fecha): array
    {
        $p = [':id_empresa' => $idEmpresa, ':fecha' => $fecha];
        $amb = AmbienteReporte::literal();
        $conCaja = $this->tablasCajaDisponibles();
        $traslados = $conCaja ? "
                UNION ALL
                SELECT t.id_forma_destino, t.fecha, t.valor FROM caja_traslados t
                 WHERE t.id_empresa = :id_empresa AND t.eliminado = false AND t.fecha < :fecha
                UNION ALL
                SELECT t.id_forma_origen, t.fecha, -t.valor FROM caja_traslados t
                 WHERE t.id_empresa = :id_empresa AND t.eliminado = false AND t.fecha < :fecha" : '';
        $apertura = $conCaja
            ? "SELECT id_forma_pago, fecha, valor FROM caja_saldos_apertura
                WHERE id_empresa = :id_empresa AND eliminado = false AND fecha <= :fecha"
            : "SELECT NULL::int AS id_forma_pago, NULL::date AS fecha, 0::numeric AS valor WHERE false";

        $rows = $this->filas("
            WITH ap AS ({$apertura}),
            mov AS (
                SELECT pg.id_forma_cobro AS id_forma, c.fecha_emision AS fecha, pg.monto AS valor
                  FROM ingresos_pagos pg
                  JOIN ingresos_cabecera c ON c.id = pg.id_ingreso
                 WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND LOWER(c.estado) <> 'anulado'
                   AND c.tipo_ambiente = {$amb} AND c.fecha_emision < :fecha
                UNION ALL
                SELECT pg.id_forma_pago, c.fecha_emision, -pg.monto
                  FROM egresos_pagos pg
                  JOIN egresos_cabecera c ON c.id = pg.id_egreso
                 WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND LOWER(c.estado) <> 'anulado'
                   AND c.tipo_ambiente = {$amb} AND c.fecha_emision < :fecha
                   AND pg.eliminado = false AND COALESCE(pg.estado_cheque, 'vigente') <> 'anulado'{$traslados}
            ),
            suma AS (
                SELECT m.id_forma, SUM(m.valor) AS valor
                  FROM mov m LEFT JOIN ap ON ap.id_forma_pago = m.id_forma
                 WHERE ap.fecha IS NULL OR m.fecha >= ap.fecha
                 GROUP BY m.id_forma
            )
            SELECT fp.id, fp.nombre, COALESCE(ap.valor, 0) + COALESCE(s.valor, 0) AS saldo
              FROM empresa_formas_pago fp
              LEFT JOIN ap ON ap.id_forma_pago = fp.id
              LEFT JOIN suma s ON s.id_forma = fp.id
             WHERE fp.id_empresa = :id_empresa
               AND ROUND(COALESCE(ap.valor, 0) + COALESCE(s.valor, 0), 2) <> 0", $p);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = ['nombre' => (string) $r['nombre'], 'saldo' => round((float) $r['saldo'], 2)];
        }
        return $out;
    }

    /** Traslados entre formas de pago del día (sin las tablas, ninguno). */
    public function getTraslados(int $idEmpresa, string $fecha, ?int $idUsuarioFiltro): array
    {
        if (!$this->tablasCajaDisponibles()) {
            return [];
        }
        $p = [':id_empresa' => $idEmpresa, ':fecha' => $fecha];
        $w = 't.id_empresa = :id_empresa AND t.eliminado = false AND t.fecha = :fecha';
        if ($idUsuarioFiltro !== null) {
            $w .= ' AND t.created_by = :id_usuario_filtro';
            $p[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        return $this->filas("
            SELECT t.id, t.valor, COALESCE(t.observaciones, '') AS observaciones,
                   t.id_forma_origen, fo.nombre AS forma_origen,
                   t.id_forma_destino, fd.nombre AS forma_destino
              FROM caja_traslados t
              JOIN empresa_formas_pago fo ON fo.id = t.id_forma_origen
              JOIN empresa_formas_pago fd ON fd.id = t.id_forma_destino
             WHERE {$w}
             ORDER BY t.id", $p);
    }

    /** Texto de una línea de ingreso/egreso: "Fact. 001-001-000000123 (11.50)". */
    private static function lineaDetalle(string $d, string $monto): string
    {
        return "TRIM(CASE {$d}.tipo_documento
                        WHEN 'FACTURA' THEN 'Fact. ' WHEN 'RECIBO' THEN 'Rec. '
                        WHEN 'COMPRA' THEN 'Compra ' WHEN 'LIQUIDACION' THEN 'Liq. '
                        WHEN 'FACTURA_REEMBOLSO' THEN 'Fact. reemb. ' WHEN 'SALDO_INICIAL' THEN 'Saldo inicial '
                        WHEN 'ROL' THEN 'Rol ' WHEN 'IMPORTACION' THEN 'Import. '
                        ELSE '' END
                    || COALESCE(NULLIF(TRIM({$d}.numero_documento), ''), NULLIF(TRIM({$d}.descripcion), ''), ''))
                || ' (' || TO_CHAR(COALESCE({$monto}, 0), 'FM999999990.00') || ')'";
    }
}
