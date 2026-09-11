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

    /**
     * WHERE de la rama vacía cuando ningún flujo aplica (p. ej. "Solo egresos" con un
     * cliente o un vendedor elegido). Usa :id_empresa a propósito: $params siempre lo
     * trae y PDO rechaza con HY093 un parámetro que no aparece en el SQL.
     */
    private const SIN_RAMAS = " WHERE c.id_empresa = :id_empresa AND 1=0";

    /**
     * Condición de pago vigente en egresos_pagos (alias dado): excluye los pagos
     * eliminados lógicamente (formas de pago reemplazadas al editar) y los cheques
     * anulados, que se conservan como historial pero no cuentan.
     */
    private static function pagoVigente(string $alias): string
    {
        return " AND $alias.eliminado = false AND COALESCE($alias.estado_cheque, 'vigente') <> 'anulado'";
    }

    /**
     * Condición "la línea de ingresos_detalle ($d) cobra un documento del vendedor
     * :id_vendedor". El vendedor es el del documento cobrado —factura o recibo de
     * venta—, igual que en Cuentas por Cobrar, Ventas por Vendedor y la columna
     * "Asesor" del reporte anterior. Las líneas sin un documento con vendedor (saldo
     * inicial, factura de reembolso, otros conceptos, cobros migrados cuya factura no
     * se migró) no cumplen el filtro.
     */
    private static function condVendedor(string $d): string
    {
        return "(EXISTS (SELECT 1 FROM ventas_cabecera vfx
                          WHERE $d.tipo_documento = 'FACTURA' AND vfx.id = $d.id_referencia_documento
                            AND vfx.id_vendedor = :id_vendedor)
                 OR EXISTS (SELECT 1 FROM recibos_venta_cabecera vrx
                          WHERE $d.tipo_documento = 'RECIBO' AND vrx.id = $d.id_referencia_documento
                            AND vrx.id_vendedor = :id_vendedor))";
    }

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
        // egresos_pagos usa eliminación lógica: al editar las formas de pago de un egreso,
        // las viejas quedan con eliminado = true y no deben seguir cumpliendo el filtro.
        // ingresos_pagos se borra físicamente, así que no tiene esa columna.
        // Los cheques anulados (estado_cheque = 'anulado') se conservan como historial
        // pero tampoco cuentan, igual que en el asiento contable y Control Bancario.
        $ppVivo = $esIng ? '' : self::pagoVigente('pp');
        $poVivo = $esIng ? '' : self::pagoVigente('po');

        if (!empty($f['id_forma'])) {
            $w .= " AND EXISTS (SELECT 1 FROM $pagTbl pp WHERE pp.$pagFk = c.id AND pp.$pagForma = :forma$ppVivo)";
            $params[':forma'] = (int)$f['id_forma'];
        }
        if (!empty($f['operacion_bancaria'])) {
            $w .= " AND EXISTS (SELECT 1 FROM $pagTbl po WHERE po.$pagFk = c.id AND po.tipo_operacion_bancaria = :opbanc$poVivo)";
            $params[':opbanc'] = $f['operacion_bancaria'];
        }
        if (!empty($f['tipo_documento'])) {
            $w .= " AND d.tipo_documento = :tipodoc"; $params[':tipodoc'] = $f['tipo_documento'];
        }
        if ($esIng && !empty($f['id_vendedor'])) {
            $w .= " AND " . self::condVendedor('d'); $params[':id_vendedor'] = (int)$f['id_vendedor'];
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
        if (!$esIng) { $w .= self::pagoVigente('p'); }

        if (!empty($f['id_forma'])) { $w .= " AND p.$pagForma = :forma"; $params[':forma'] = (int)$f['id_forma']; }
        if (!empty($f['operacion_bancaria'])) { $w .= " AND p.tipo_operacion_bancaria = :opbanc"; $params[':opbanc'] = $f['operacion_bancaria']; }
        if (!empty($f['tipo_documento'])) {
            $extra = $esIng ? '' : ' AND dd.eliminado = false';
            $w .= " AND EXISTS (SELECT 1 FROM $detTbl dd WHERE dd.$detFk = c.id AND dd.tipo_documento = :tipodoc$extra)";
            $params[':tipodoc'] = $f['tipo_documento'];
        }
        if ($esIng && !empty($f['id_vendedor'])) {
            // El pago es de todo el comprobante: entra si alguna de sus líneas cobra un documento del vendedor.
            $w .= " AND EXISTS (SELECT 1 FROM ingresos_detalle dvn WHERE dvn.id_ingreso = c.id AND " . self::condVendedor('dvn') . ")";
            $params[':id_vendedor'] = (int)$f['id_vendedor'];
        }
        return $w;
    }

    /**
     * SELECT normalizado de un flujo (nivel detalle). $completo agrega las columnas
     * del Excel detallado (vendedor, fecha y saldos del documento, cuenta contable,
     * formas de cobro/pago y quién registró) sin tocar las columnas base, así el
     * filtro de texto da las mismas filas en pantalla y en el Excel.
     */
    private function selectFlujo(string $flujo, bool $completo = false): string
    {
        if ($flujo === 'INGRESO') {
            $extraCols = !$completo ? '' : ",
                           ven.nombre        AS vendedor,
                           COALESCE(xf.fecha_emision, xr.fecha_emision, xfr.fecha_emision, xsi.fecha_emision) AS fecha_documento,
                           d.monto_documento AS monto_documento,
                           d.saldo_anterior  AS saldo_anterior,
                           d.saldo_actual    AS saldo_actual,
                           cta.codigo        AS cuenta_codigo,
                           cta.nombre        AS cuenta_nombre,
                           fpg.formas        AS formas_pago,
                           usr.nombre        AS registrado_por,
                           c.created_at      AS registrado_el";
            $extraJoins = !$completo ? '' : "
                    LEFT  JOIN ventas_cabecera            xf  ON d.tipo_documento = 'FACTURA'           AND xf.id  = d.id_referencia_documento
                    LEFT  JOIN recibos_venta_cabecera     xr  ON d.tipo_documento = 'RECIBO'            AND xr.id  = d.id_referencia_documento
                    LEFT  JOIN factura_reembolso_cabecera xfr ON d.tipo_documento = 'FACTURA_REEMBOLSO' AND xfr.id = d.id_referencia_documento
                    LEFT  JOIN saldos_iniciales_cxc       xsi ON d.tipo_documento = 'SALDO_INICIAL'     AND xsi.id = d.id_referencia_documento
                    LEFT  JOIN vendedores   ven ON ven.id = COALESCE(xf.id_vendedor, xr.id_vendedor)
                    LEFT  JOIN plan_cuentas cta ON cta.id = d.id_cuenta_contable
                    LEFT  JOIN usuarios     usr ON usr.id = COALESCE(c.created_by, c.id_usuario)
                    LEFT  JOIN (" . self::formasPorComprobante('INGRESO') . ") fpg ON fpg.id_comprobante = c.id";
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
                           c.observaciones  AS observaciones$extraCols
                    FROM ingresos_cabecera c
                    INNER JOIN ingresos_detalle d ON d.id_ingreso = c.id
                    LEFT  JOIN clientes cli ON cli.id = COALESCE(c.id_cliente, c.id_recibo_cliente)
                    LEFT  JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_ingreso_concepto$extraJoins";
        }
        $extraCols = !$completo ? '' : ",
                       NULL::varchar     AS vendedor,
                       COALESCE(xc.fecha_emision, xl.fecha_emision, xsp.fecha_emision) AS fecha_documento,
                       d.monto_documento AS monto_documento,
                       d.saldo_anterior  AS saldo_anterior,
                       d.saldo_actual    AS saldo_actual,
                       cta.codigo        AS cuenta_codigo,
                       cta.nombre        AS cuenta_nombre,
                       fpg.formas        AS formas_pago,
                       usr.nombre        AS registrado_por,
                       c.created_at      AS registrado_el";
        $extraJoins = !$completo ? '' : "
                LEFT  JOIN compras_cabecera       xc  ON d.tipo_documento = 'COMPRA'        AND xc.id  = d.id_referencia_documento
                LEFT  JOIN liquidaciones_cabecera xl  ON d.tipo_documento = 'LIQUIDACION'   AND xl.id  = d.id_referencia_documento
                LEFT  JOIN saldos_iniciales_cxp   xsp ON d.tipo_documento = 'SALDO_INICIAL' AND xsp.id = d.id_referencia_documento
                LEFT  JOIN plan_cuentas cta ON cta.id = d.id_cuenta_contable
                LEFT  JOIN usuarios     usr ON usr.id = c.created_by
                LEFT  JOIN (" . self::formasPorComprobante('EGRESO') . ") fpg ON fpg.id_comprobante = c.id";
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
                       c.observaciones  AS observaciones$extraCols
                FROM egresos_cabecera c
                INNER JOIN egresos_detalle d ON d.id_egreso = c.id
                LEFT  JOIN proveedores pr  ON pr.id  = c.id_proveedor
                LEFT  JOIN empleados   emp ON emp.id = c.id_empleado
                LEFT  JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_egreso_concepto$extraJoins";
    }

    /**
     * SELECT normalizado de pagos de un flujo (para agrupar por forma). $completo
     * agrega las columnas de la hoja "Cobros y pagos" del Excel.
     */
    private function selectFlujoPagos(string $flujo, bool $completo = false): string
    {
        if ($flujo === 'INGRESO') {
            $extraCols = !$completo ? '' : ",
                           c.fecha_emision AS fecha,
                           c.estado        AS estado,
                           oc.nombre       AS concepto,
                           'CLIENTE'::varchar AS tercero_tipo,
                           COALESCE(cli.identificacion, '') AS tercero_ident,
                           " . self::opBancaria('p.tipo_operacion_bancaria') . " AS operacion_bancaria,
                           p.numero_cheque AS numero_cheque,
                           p.referencia    AS referencia,
                           p.fecha_cobro   AS fecha_cobro,
                           NULL::varchar   AS beneficiario_cheque,
                           dg.documentos   AS documentos,
                           dg.vendedores   AS vendedor,
                           c.observaciones AS observaciones,
                           usr.nombre      AS registrado_por";
            $extraJoins = !$completo ? '' : "
                    LEFT  JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_ingreso_concepto
                    LEFT  JOIN usuarios usr ON usr.id = COALESCE(c.created_by, c.id_usuario)
                    LEFT  JOIN (" . self::documentosPorComprobante('INGRESO') . ") dg ON dg.id_comprobante = c.id";
            return "SELECT 'INGRESO'::varchar AS tipo_flujo,
                           fp.nombre AS forma_nombre, fp.tipo AS forma_tipo,
                           p.monto  AS monto,
                           c.id AS id_comprobante, c.numero_ingreso AS numero,
                           COALESCE(cli.nombre, c.recibo_de, '—') AS tercero_nombre$extraCols
                    FROM ingresos_pagos p
                    INNER JOIN ingresos_cabecera c ON c.id = p.id_ingreso
                    INNER JOIN empresa_formas_pago fp ON fp.id = p.id_forma_cobro
                    LEFT  JOIN clientes cli ON cli.id = COALESCE(c.id_cliente, c.id_recibo_cliente)$extraJoins";
        }
        $extraCols = !$completo ? '' : ",
                       c.fecha_emision AS fecha,
                       c.estado        AS estado,
                       oc.nombre       AS concepto,
                       CASE WHEN c.tipo_sujeto = 'EMPLEADO' THEN 'EMPLEADO' ELSE 'PROVEEDOR' END AS tercero_tipo,
                       COALESCE(pr.identificacion, emp.identificacion, '') AS tercero_ident,
                       " . self::opBancaria('p.tipo_operacion_bancaria') . " AS operacion_bancaria,
                       p.numero_cheque AS numero_cheque,
                       p.referencia    AS referencia,
                       p.fecha_cobro   AS fecha_cobro,
                       p.beneficiario_cheque AS beneficiario_cheque,
                       dg.documentos   AS documentos,
                       dg.vendedores   AS vendedor,
                       c.observaciones AS observaciones,
                       usr.nombre      AS registrado_por";
        $extraJoins = !$completo ? '' : "
                LEFT  JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_egreso_concepto
                LEFT  JOIN usuarios usr ON usr.id = c.created_by
                LEFT  JOIN (" . self::documentosPorComprobante('EGRESO') . ") dg ON dg.id_comprobante = c.id";
        return "SELECT 'EGRESO'::varchar AS tipo_flujo,
                       fp.nombre AS forma_nombre, fp.tipo AS forma_tipo,
                       p.monto  AS monto,
                       c.id AS id_comprobante, c.numero_egreso AS numero,
                       COALESCE(pr.razon_social, emp.nombres_apellidos, '—') AS tercero_nombre$extraCols
                FROM egresos_pagos p
                INNER JOIN egresos_cabecera c ON c.id = p.id_egreso
                INNER JOIN empresa_formas_pago fp ON fp.id = p.id_forma_pago
                LEFT  JOIN proveedores pr  ON pr.id  = c.id_proveedor
                LEFT  JOIN empleados   emp ON emp.id = c.id_empleado$extraJoins";
    }

    // ── Piezas del Excel detallado ────────────────────────────────────────────

    /** Operación bancaria legible (TRANSFERENCIA → Transferencia, DEPOSITO → Depósito…). */
    private static function opBancaria(string $col): string
    {
        return "CASE $col WHEN 'TRANSFERENCIA' THEN 'Transferencia' WHEN 'DEPOSITO' THEN 'Depósito'
                          WHEN 'DEBITO' THEN 'Débito' WHEN 'CHEQUE' THEN 'Cheque' ELSE $col END";
    }

    /** Texto de un pago: "FORMA $monto (Operación, N° cheque, Ref. …)". */
    private static function textoPago(string $p, string $fp): string
    {
        return "$fp.nombre || ' \$' || to_char($p.monto, 'FM999999990.00')
                || COALESCE(' (' || NULLIF(concat_ws(', ', " . self::opBancaria("$p.tipo_operacion_bancaria") . ",
                       'N° ' || NULLIF($p.numero_cheque, ''), 'Ref. ' || NULLIF($p.referencia, '')), '') || ')', '')";
    }

    /**
     * Formas de cobro/pago de cada comprobante de la empresa en un solo texto, para la
     * hoja "Detalle" del Excel. Se agregan una sola vez y se unen por id: egresos_pagos
     * no tiene índice por id_egreso, así que una subconsulta por fila la recorrería entera.
     */
    private static function formasPorComprobante(string $flujo): string
    {
        $esIng = $flujo === 'INGRESO';
        $pag   = $esIng ? 'ingresos_pagos'    : 'egresos_pagos';
        $cab   = $esIng ? 'ingresos_cabecera' : 'egresos_cabecera';
        $fk    = $esIng ? 'id_ingreso'        : 'id_egreso';
        $forma = $esIng ? 'id_forma_cobro'    : 'id_forma_pago';
        $vivo  = $esIng ? '' : self::pagoVigente('fpp');
        return "SELECT fpp.$fk AS id_comprobante,
                       string_agg(" . self::textoPago('fpp', 'fpf') . ", '; ' ORDER BY fpp.id) AS formas
                FROM $pag fpp
                INNER JOIN $cab fpc ON fpc.id = fpp.$fk AND fpc.id_empresa = :id_empresa
                INNER JOIN empresa_formas_pago fpf ON fpf.id = fpp.$forma
                WHERE true$vivo
                GROUP BY fpp.$fk";
    }

    /**
     * Documentos que cancela cada comprobante de la empresa (y, en los cobros, los
     * vendedores de esos documentos), para la hoja "Cobros y pagos" del Excel. Mismo
     * criterio que formasPorComprobante().
     */
    private static function documentosPorComprobante(string $flujo): string
    {
        if ($flujo === 'INGRESO') {
            return "SELECT dd.id_ingreso AS id_comprobante,
                           string_agg(dd.tipo_documento || COALESCE(' ' || dd.numero_documento, ''), ', ' ORDER BY dd.id) AS documentos,
                           string_agg(DISTINCT dv.nombre, ', ' ORDER BY dv.nombre) AS vendedores
                    FROM ingresos_detalle dd
                    INNER JOIN ingresos_cabecera ddc ON ddc.id = dd.id_ingreso AND ddc.id_empresa = :id_empresa
                    LEFT  JOIN ventas_cabecera        ddf ON dd.tipo_documento = 'FACTURA' AND ddf.id = dd.id_referencia_documento
                    LEFT  JOIN recibos_venta_cabecera ddr ON dd.tipo_documento = 'RECIBO'  AND ddr.id = dd.id_referencia_documento
                    LEFT  JOIN vendedores dv ON dv.id = COALESCE(ddf.id_vendedor, ddr.id_vendedor)
                    GROUP BY dd.id_ingreso";
        }
        return "SELECT dd.id_egreso AS id_comprobante,
                       string_agg(dd.tipo_documento || COALESCE(' ' || dd.numero_documento, ''), ', ' ORDER BY dd.id) AS documentos,
                       NULL::text AS vendedores
                FROM egresos_detalle dd
                INNER JOIN egresos_cabecera ddc ON ddc.id = dd.id_egreso AND ddc.id_empresa = :id_empresa
                WHERE dd.eliminado = false
                GROUP BY dd.id_egreso";
    }

    /** UNION de pagos de los flujos aplicables. Devuelve [sql, params]. */
    private function armarUnionPagos(int $idEmpresa, array $f, bool $completo = false): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $ramas  = [];
        foreach (['INGRESO', 'EGRESO'] as $flujo) {
            if (!$this->incluyeFlujo($flujo, $f)) continue;
            $ramas[] = $this->selectFlujoPagos($flujo, $completo) . "\n WHERE " . $this->whereFlujoPagos($flujo, $f, $params);
        }
        if (empty($ramas)) { $ramas[] = $this->selectFlujoPagos('INGRESO', $completo) . self::SIN_RAMAS; }
        return [implode("\n UNION ALL \n", $ramas), $params];
    }

    /** ¿Se incluye este flujo dado el filtro de tipo/tercero/vendedor? */
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
        // Vendedor también: solo los cobros tienen vendedor (el del documento cobrado)
        if (!empty($f['id_vendedor']) && $flujo !== 'INGRESO') return false;
        return true;
    }

    /** UNION de los flujos aplicables con sus WHERE. Devuelve [sql, params]. */
    private function armarUnion(int $idEmpresa, array $f, bool $completo = false): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $ramas  = [];
        foreach (['INGRESO', 'EGRESO'] as $flujo) {
            if (!$this->incluyeFlujo($flujo, $f)) continue;
            $ramas[] = $this->selectFlujo($flujo, $completo) . "\n WHERE " . $this->whereFlujo($flujo, $f, $params);
        }
        if (empty($ramas)) {
            // Ningún flujo aplica: forzar resultado vacío consistente
            $ramas[] = $this->selectFlujo('INGRESO', $completo) . self::SIN_RAMAS;
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

    /** Listado por documento (detalle). $limite = 0 => sin tope (usar solo para exportación). */
    public function getReporteDetallado(int $idEmpresa, array $f, int $limite = 5000): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT * FROM ( $union ) r WHERE 1=1 $textoWhere
                ORDER BY fecha DESC, numero DESC, tipo_documento";
        if ($limite > 0) {
            $sql .= " LIMIT $limite";
        }
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle para el Excel: las mismas filas que getReporteDetallado() (mismos filtros,
     * sin tope) con todas las columnas que se conocen de cada línea.
     */
    public function getDetalleExport(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f, true);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT * FROM ( $union ) r WHERE 1=1 $textoWhere
                ORDER BY fecha DESC, numero DESC, tipo_documento";
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

    /**
     * Una fila por cobro/pago para la hoja "Cobros y pagos" del Excel: los mismos
     * pagos (y filtros) que la vista Por forma de cobro/pago.
     */
    public function getPagosExport(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnionPagos($idEmpresa, $f, true);
        $textoWhere = $this->filtroTexto($f, $params, ['numero', 'tercero_nombre', 'forma_nombre']);
        $sql = "SELECT * FROM ( $union ) r WHERE 1=1 $textoWhere
                ORDER BY fecha DESC, numero DESC, tipo_flujo, forma_nombre";
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

    /** Nombre de un tercero por tipo e id (para describir el filtro en las exportaciones). */
    public function getNombreTercero(int $idEmpresa, string $tipo, int $id): ?string
    {
        [$tabla, $col] = match ($tipo) {
            'PROVEEDOR' => ['proveedores', 'razon_social'],
            'EMPLEADO'  => ['empleados', 'nombres_apellidos'],
            default     => ['clientes', 'nombre'],
        };
        $nombre = $this->q("SELECT $col FROM $tabla WHERE id = :id AND id_empresa = :e AND eliminado = false",
                           [':id' => $id, ':e' => $idEmpresa])->fetchColumn();
        return $nombre === false ? null : (string) $nombre;
    }
}
