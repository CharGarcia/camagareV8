<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\TiposFormaPago;
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
    /** Regla de reportes: solo documentos de PRODUCCIÓN (ver App\Helpers\AmbienteReporte). */
    private const AMB = "'2'";

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
     * Lo cobrado/pagado con formas no monetarias por comprobante: anticipo y otros por
     * separado. El reporte muestra el monto completo de cada línea y, aparte, cuánto fue
     * anticipo, cuánto otros y cuánto entrada/salida de dinero (monto_caja). Solo recorre
     * los pagos de esas formas.
     */
    private static function noMonetarioPorComprobante(string $flujo): string
    {
        $esIng = $flujo === 'INGRESO';
        $pag   = $esIng ? 'ingresos_pagos'    : 'egresos_pagos';
        $cab   = $esIng ? 'ingresos_cabecera' : 'egresos_cabecera';
        $fk    = $esIng ? 'id_ingreso'        : 'id_egreso';
        $forma = $esIng ? 'id_forma_cobro'    : 'id_forma_pago';
        $vivo  = $esIng ? '' : self::pagoVigente('app');
        $tipos = TiposFormaPago::noMonetariosSql();
        return "SELECT app.$fk AS id_comprobante,
                       SUM(CASE WHEN apf.tipo = 'ANTICIPO' THEN app.monto ELSE 0 END) AS anticipo,
                       SUM(CASE WHEN apf.tipo <> 'ANTICIPO' THEN app.monto ELSE 0 END) AS otros
                FROM $pag app
                INNER JOIN empresa_formas_pago apf ON apf.id = app.$forma AND apf.tipo IN ($tipos)
                INNER JOIN $cab apc ON apc.id = app.$fk AND apc.id_empresa = :id_empresa
                WHERE true$vivo
                GROUP BY app.$fk";
    }

    /**
     * Parte de la línea ($montoLinea) pagada con formas no monetarias (alias nm): lo de
     * esas formas se reparte entre las líneas del comprobante en proporción al total.
     * $partes: 'anticipo', 'otros' o 'anticipo + otros'. El COALESCE va dentro del LEAST:
     * LEAST(1, NULL) devuelve 1 en PostgreSQL (ignora los NULL), y un comprobante sin
     * esas formas quedaría pagado entero con ellas.
     */
    private static function parteLinea(string $montoLinea, string $partes): string
    {
        $suma = implode(' + ', array_map(static fn ($p) => "COALESCE(nm.$p, 0)", explode(' + ', $partes)));
        return "COALESCE($montoLinea * LEAST(1, ($suma) / NULLIF(c.monto_total, 0)), 0)";
    }

    /**
     * Columnas de monto de una línea. Con todos los movimientos: monto completo, parte
     * con anticipo, parte con otros y entrada/salida de dinero (monto_caja). Con "Solo
     * monetarios" (filtro movimientos = MONETARIOS) el monto ES la parte de dinero y las
     * partes no monetarias quedan en cero; con "Solo no monetarios" (NO_MONETARIOS), al
     * revés: el monto es lo pagado con anticipo u otros y la parte de dinero queda en cero.
     */
    private static function columnasMonto(string $montoLinea, array $f): string
    {
        $anticipo = self::parteLinea($montoLinea, 'anticipo');
        $otros    = self::parteLinea($montoLinea, 'otros');
        $caja     = "$montoLinea - " . self::parteLinea($montoLinea, 'anticipo + otros');
        return match (self::modoMovimientos($f)) {
            'MONETARIOS'    => "$caja AS monto, 0::numeric AS monto_anticipo, 0::numeric AS monto_otros, $caja AS monto_caja",
            'NO_MONETARIOS' => self::parteLinea($montoLinea, 'anticipo + otros') . " AS monto,
                                $anticipo AS monto_anticipo, $otros AS monto_otros, 0::numeric AS monto_caja",
            default         => "$montoLinea AS monto, $anticipo AS monto_anticipo, $otros AS monto_otros, $caja AS monto_caja",
        };
    }

    /** Filtro "Movimientos": TODOS | MONETARIOS | NO_MONETARIOS. */
    private static function modoMovimientos(array $f): string
    {
        $m = strtoupper((string) ($f['movimientos'] ?? ''));
        return in_array($m, ['MONETARIOS', 'NO_MONETARIOS'], true) ? $m : 'TODOS';
    }
    /**
     * Condición "la línea de ingresos_detalle ($d) es del vendedor :id_vendedor".
     * El vendedor es el del documento cobrado —factura o recibo de venta—, igual que
     * en Cuentas por Cobrar, Ventas por Vendedor y la columna "Asesor" del reporte
     * anterior. Las líneas de otros conceptos (tipo OTRO, sin documento) toman el
     * vendedor asignado al cliente del comprobante (alias c = ingresos_cabecera, que
     * debe estar en el FROM del llamador). Los saldos iniciales tampoco guardan
     * vendedor (saldos_iniciales_cxc no tiene id_vendedor): toman el asignado al
     * cliente del saldo y, si el saldo no quedó enlazado a un cliente, el del cliente
     * del comprobante. Las demás líneas sin documento con vendedor (factura de
     * reembolso, cobros migrados cuya factura no se migró) no cumplen el filtro.
     */
    private static function condVendedor(string $d): string
    {
        return "(EXISTS (SELECT 1 FROM ventas_cabecera vfx
                          WHERE $d.tipo_documento = 'FACTURA' AND vfx.id = $d.id_referencia_documento
                            AND vfx.id_vendedor = :id_vendedor)
                 OR EXISTS (SELECT 1 FROM recibos_venta_cabecera vrx
                          WHERE $d.tipo_documento = 'RECIBO' AND vrx.id = $d.id_referencia_documento
                            AND vrx.id_vendedor = :id_vendedor)
                 OR ($d.tipo_documento = 'OTRO' AND EXISTS (SELECT 1 FROM clientes cvx
                          WHERE cvx.id = COALESCE(c.id_cliente, c.id_recibo_cliente)
                            AND cvx.id_vendedor = :id_vendedor))
                 OR ($d.tipo_documento = 'SALDO_INICIAL' AND EXISTS (SELECT 1 FROM clientes csx
                          WHERE csx.id = COALESCE((SELECT six.id_cliente FROM saldos_iniciales_cxc six
                                                    WHERE six.id = $d.id_referencia_documento),
                                                  c.id_cliente, c.id_recibo_cliente)
                            AND csx.id_vendedor = :id_vendedor)))";
    }

    /**
     * Vendedor de una línea de ingreso: el del documento cobrado (factura $xf o
     * recibo $xr); en las líneas de otros conceptos (tipo OTRO), el asignado al
     * cliente del comprobante ($cli), y en los saldos iniciales el asignado al
     * cliente del saldo (o, si no tiene, al del comprobante). Mismo criterio que
     * condVendedor().
     */
    private static function idVendedorLinea(string $d, string $xf, string $xr, string $cli): string
    {
        return "CASE WHEN $d.tipo_documento = 'OTRO' THEN $cli.id_vendedor
                     WHEN $d.tipo_documento = 'SALDO_INICIAL' THEN
                          (SELECT csv.id_vendedor FROM clientes csv
                            WHERE csv.id = COALESCE((SELECT siv.id_cliente FROM saldos_iniciales_cxc siv
                                                      WHERE siv.id = $d.id_referencia_documento), $cli.id))
                     ELSE COALESCE($xf.id_vendedor, $xr.id_vendedor) END";
    }

    // ── WHERE por flujo ───────────────────────────────────────────────────────

    /** Filtros a nivel cabecera (alias c), comunes a detalle y pagos. */
    private function whereCabecera(bool $esIng, array $f, array &$params): string
    {
        $w = "c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB;
        if (!empty($f['fecha_desde'])) { $w .= " AND c.fecha_emision >= :fdesde"; $params[':fdesde'] = $f['fecha_desde']; }
        if (!empty($f['fecha_hasta'])) { $w .= " AND c.fecha_emision <= :fhasta"; $params[':fhasta'] = $f['fecha_hasta']; }
        // Por defecto solo vigentes: un comprobante anulado no suma en el reporte.
        // Los anulados se ven únicamente pidiéndolos (estado = ANULADO).
        $w .= strtoupper($f['estado'] ?? '') === 'ANULADO'
            ? " AND LOWER(c.estado) = 'anulado'"
            : " AND LOWER(COALESCE(c.estado, '')) <> 'anulado'";
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
        // Solo monetarios: fuera las líneas pagadas enteras con anticipo u otros.
        // Solo no monetarios: solo las líneas que tienen una parte pagada con ellos.
        $linea = $esIng ? 'd.monto_cobrado' : 'd.monto_pagado';
        $w .= match (self::modoMovimientos($f)) {
            'MONETARIOS'    => " AND ($linea - " . self::parteLinea($linea, 'anticipo + otros') . ") > 0.005",
            'NO_MONETARIOS' => " AND " . self::parteLinea($linea, 'anticipo + otros') . " > 0.005",
            default         => '',
        };
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
        // Solo monetarios / no monetarios: los cobros/pagos con formas que sí / no mueven dinero.
        $w .= match (self::modoMovimientos($f)) {
            'MONETARIOS'    => " AND fp.tipo NOT IN (" . TiposFormaPago::noMonetariosSql() . ")",
            'NO_MONETARIOS' => " AND fp.tipo IN (" . TiposFormaPago::noMonetariosSql() . ")",
            default         => '',
        };
        return $w;
    }

    /**
     * SELECT normalizado de un flujo (nivel detalle). $completo agrega las columnas
     * del Excel detallado (vendedor, fecha y saldos del documento, cuenta contable,
     * formas de cobro/pago y quién registró) sin tocar las columnas base, así el
     * filtro de texto da las mismas filas en pantalla y en el Excel.
     */
    private function selectFlujo(string $flujo, bool $completo = false, array $f = []): string
    {
        if ($flujo === 'INGRESO') {
            $extraCols = !$completo ? '' : ",
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
                    LEFT  JOIN factura_reembolso_cabecera xfr ON d.tipo_documento = 'FACTURA_REEMBOLSO' AND xfr.id = d.id_referencia_documento
                    LEFT  JOIN saldos_iniciales_cxc       xsi ON d.tipo_documento = 'SALDO_INICIAL'     AND xsi.id = d.id_referencia_documento
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
                           " . self::columnasMonto('d.monto_cobrado', $f) . ",
                           c.observaciones  AS observaciones,
                           d.id             AS id_detalle,
                           ven.nombre       AS vendedor$extraCols
                    FROM ingresos_cabecera c
                    INNER JOIN ingresos_detalle d ON d.id_ingreso = c.id
                    LEFT  JOIN (" . self::noMonetarioPorComprobante('INGRESO') . ") nm ON nm.id_comprobante = c.id
                    LEFT  JOIN clientes cli ON cli.id = COALESCE(c.id_cliente, c.id_recibo_cliente)
                    LEFT  JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_ingreso_concepto
                    LEFT  JOIN ventas_cabecera        xf ON d.tipo_documento = 'FACTURA' AND xf.id = d.id_referencia_documento
                    LEFT  JOIN recibos_venta_cabecera xr ON d.tipo_documento = 'RECIBO'  AND xr.id = d.id_referencia_documento
                    LEFT  JOIN vendedores ven ON ven.id = " . self::idVendedorLinea('d', 'xf', 'xr', 'cli') . "$extraJoins";
        }
        $extraCols = !$completo ? '' : ",
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
                       " . self::columnasMonto('d.monto_pagado', $f) . ",
                       c.observaciones  AS observaciones,
                       d.id             AS id_detalle,
                       NULL::varchar    AS vendedor$extraCols
                FROM egresos_cabecera c
                INNER JOIN egresos_detalle d ON d.id_egreso = c.id
                LEFT  JOIN (" . self::noMonetarioPorComprobante('EGRESO') . ") nm ON nm.id_comprobante = c.id
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
                    LEFT  JOIN clientes               dcl ON dcl.id = COALESCE(ddc.id_cliente, ddc.id_recibo_cliente)
                    LEFT  JOIN ventas_cabecera        ddf ON dd.tipo_documento = 'FACTURA' AND ddf.id = dd.id_referencia_documento
                    LEFT  JOIN recibos_venta_cabecera ddr ON dd.tipo_documento = 'RECIBO'  AND ddr.id = dd.id_referencia_documento
                    LEFT  JOIN vendedores dv ON dv.id = " . self::idVendedorLinea('dd', 'ddf', 'ddr', 'dcl') . "
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
            $ramas[] = $this->selectFlujo($flujo, $completo, $f) . "\n WHERE " . $this->whereFlujo($flujo, $f, $params);
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

    /**
     * Una fila por comprobante para la primera hoja del Excel: número, fecha, tercero,
     * valor, el cuerpo del comprobante (sus líneas, una por renglón), observaciones y
     * asesor. Parte de las mismas líneas que getReporteDetallado() (mismos filtros, sin
     * tope), así el valor de cada comprobante es la suma de las líneas que cumplen el
     * filtro y cuadra con los totales de arriba.
     */
    public function getResumenExport(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $linea = "CASE WHEN tipo_documento = 'OTRO' THEN COALESCE(NULLIF(descripcion, ''), 'Otros conceptos')
                       ELSE tipo_documento || COALESCE(' ' || NULLIF(numero_documento, ''), '')
                            || COALESCE(' · ' || NULLIF(descripcion, ''), '') END
                  || ' \$' || to_char(monto, 'FM999999990.00')
                  || CASE WHEN monto_anticipo > 0.004
                          THEN ' (anticipo aplicado \$' || to_char(monto_anticipo, 'FM999999990.00') || ')' ELSE '' END
                  || CASE WHEN monto_otros > 0.004
                          THEN ' (otros no monetarios \$' || to_char(monto_otros, 'FM999999990.00') || ')' ELSE '' END";
        $sql = "SELECT tipo_flujo, id_comprobante, numero, fecha, tercero_tipo, tercero_nombre, tercero_ident,
                       SUM(monto) AS valor,
                       ROUND(SUM(monto_anticipo + monto_otros), 2) AS no_monetario,
                       ROUND(SUM(monto_caja), 2) AS caja,
                       string_agg($linea, E'\\n' ORDER BY id_detalle) AS detalle,
                       MAX(observaciones) AS observaciones,
                       string_agg(DISTINCT vendedor, ', ' ORDER BY vendedor) AS asesor
                FROM ( $union ) r WHERE 1=1 $textoWhere
                GROUP BY tipo_flujo, id_comprobante, numero, fecha, tercero_tipo, tercero_nombre, tercero_ident
                ORDER BY fecha DESC, numero DESC, tipo_flujo";
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
                       SUM(monto)                     AS total,
                       ROUND(SUM(monto_anticipo + monto_otros), 2) AS no_monetario,
                       ROUND(SUM(monto_caja), 2)      AS caja
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
        $tipos = TiposFormaPago::noMonetariosSql();
        $sql = "SELECT tipo_flujo, forma_nombre, MAX(forma_tipo) AS forma_tipo,
                       BOOL_OR(forma_tipo IN ($tipos)) AS es_no_monetario,
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
        $sql = "SELECT $expr AS $alias, " . self::SUMAS_FLUJO . "
                FROM ( $union ) r WHERE 1=1 $textoWhere
                GROUP BY $expr
                ORDER BY $alias DESC";
        return array_map([self::class, 'conNeto'], $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Sumas por flujo de las filas del UNION (alias r): lo cobrado/pagado completo, lo
     * pagado con anticipo, con otras formas no monetarias y la entrada/salida de dinero,
     * más los conteos.
     */
    private const SUMAS_FLUJO = "
                    COALESCE(SUM(CASE WHEN tipo_flujo='INGRESO' THEN monto ELSE 0 END), 0) AS ingresos,
                    COALESCE(SUM(CASE WHEN tipo_flujo='EGRESO'  THEN monto ELSE 0 END), 0) AS egresos,
                    ROUND(COALESCE(SUM(CASE WHEN tipo_flujo='INGRESO' THEN monto_anticipo ELSE 0 END), 0), 2) AS ingresos_anticipo,
                    ROUND(COALESCE(SUM(CASE WHEN tipo_flujo='EGRESO'  THEN monto_anticipo ELSE 0 END), 0), 2) AS egresos_anticipo,
                    ROUND(COALESCE(SUM(CASE WHEN tipo_flujo='INGRESO' THEN monto_otros ELSE 0 END), 0), 2) AS ingresos_otros,
                    ROUND(COALESCE(SUM(CASE WHEN tipo_flujo='EGRESO'  THEN monto_otros ELSE 0 END), 0), 2) AS egresos_otros,
                    ROUND(COALESCE(SUM(CASE WHEN tipo_flujo='INGRESO' THEN monto_caja ELSE 0 END), 0), 2) AS ingresos_caja,
                    ROUND(COALESCE(SUM(CASE WHEN tipo_flujo='EGRESO'  THEN monto_caja ELSE 0 END), 0), 2) AS egresos_caja,
                    COUNT(DISTINCT CASE WHEN tipo_flujo='INGRESO' THEN id_comprobante END) AS n_ing,
                    COUNT(DISTINCT CASE WHEN tipo_flujo='EGRESO'  THEN id_comprobante END) AS n_egr";

    /**
     * Completa lo no monetario (anticipo + otros) y el neto. El neto es de dinero: lo
     * pagado con formas no monetarias no entró ni salió de caja o bancos.
     */
    private static function conNeto(array $r): array
    {
        $r['ingresos_no_monetario'] = round((float) $r['ingresos_anticipo'] + (float) $r['ingresos_otros'], 2);
        $r['egresos_no_monetario']  = round((float) $r['egresos_anticipo'] + (float) $r['egresos_otros'], 2);
        $r['neto'] = round((float) $r['ingresos_caja'] - (float) $r['egresos_caja'], 2);
        return $r;
    }

    /**
     * Totales: cobrado/pagado completo, no monetario (anticipo y otros), entrada/salida
     * de dinero, neto (de dinero) y conteos.
     */
    public function getEstadisticas(int $idEmpresa, array $f): array
    {
        [$union, $params] = $this->armarUnion($idEmpresa, $f);
        $textoWhere = $this->filtroTexto($f, $params);
        $sql = "SELECT " . self::SUMAS_FLUJO . ", COUNT(*) AS n_documentos
                FROM ( $union ) r WHERE 1=1 $textoWhere";
        $r = self::conNeto($this->q($sql, $params)->fetch(PDO::FETCH_ASSOC));
        return [
            'total_ingresos'        => (float) $r['ingresos'],
            'total_egresos'         => (float) $r['egresos'],
            'anticipo_ingresos'     => (float) $r['ingresos_anticipo'],
            'anticipo_egresos'      => (float) $r['egresos_anticipo'],
            'otros_ingresos'        => (float) $r['ingresos_otros'],
            'otros_egresos'         => (float) $r['egresos_otros'],
            'no_monetario_ingresos' => $r['ingresos_no_monetario'],
            'no_monetario_egresos'  => $r['egresos_no_monetario'],
            'caja_ingresos'         => (float) $r['ingresos_caja'],
            'caja_egresos'          => (float) $r['egresos_caja'],
            'neto'                  => $r['neto'],
            'n_ingresos'            => (int) $r['n_ing'],
            'n_egresos'             => (int) $r['n_egr'],
            'n_documentos'          => (int) $r['n_documentos'],
        ];
    }

    // ── Detalle de un comprobante (panel lateral al hacer clic en una fila) ──────

    /**
     * Un ingreso o egreso de la empresa con sus líneas (documentos que cobra o paga, con
     * la parte pagada con anticipo u otras formas no monetarias) y sus formas de
     * cobro/pago vigentes. null si no existe, es de otra empresa o está eliminado.
     */
    public function getComprobante(int $idEmpresa, string $flujo, int $id): ?array
    {
        $esIng  = $flujo === 'INGRESO';
        $params = [':id' => $id, ':id_empresa' => $idEmpresa];
        $cab = $this->q($esIng
            ? "SELECT c.id, c.numero_ingreso AS numero, c.fecha_emision, c.estado, c.monto_total, c.observaciones,
                      oc.nombre AS concepto, COALESCE(cli.nombre, c.recibo_de, '') AS cliente_nombre,
                      COALESCE(cli.identificacion, '') AS identificacion
               FROM ingresos_cabecera c
               LEFT JOIN clientes cli ON cli.id = COALESCE(c.id_cliente, c.id_recibo_cliente)
               LEFT JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_ingreso_concepto
               WHERE c.id = :id AND c.id_empresa = :id_empresa AND c.eliminado = false"
            : "SELECT c.id, c.numero_egreso AS numero, c.fecha_emision, c.estado, c.monto_total, c.observaciones,
                      oc.nombre AS concepto, COALESCE(pr.razon_social, emp.nombres_apellidos, '') AS proveedor_nombre,
                      COALESCE(pr.identificacion, emp.identificacion, '') AS identificacion
               FROM egresos_cabecera c
               LEFT JOIN proveedores pr  ON pr.id  = c.id_proveedor
               LEFT JOIN empleados   emp ON emp.id = c.id_empleado
               LEFT JOIN empresa_opciones_ingreso_egreso oc ON oc.id = c.id_egreso_concepto
               WHERE c.id = :id AND c.id_empresa = :id_empresa AND c.eliminado = false", $params)->fetch(PDO::FETCH_ASSOC);
        if (!$cab) {
            return null;
        }

        $linea = $esIng ? 'd.monto_cobrado' : 'd.monto_pagado';
        $detalles = $this->q("SELECT d.tipo_documento, d.numero_documento, d.descripcion, $linea AS monto,
                                     ROUND(" . self::parteLinea($linea, 'anticipo') . ", 2) AS monto_anticipo,
                                     ROUND(" . self::parteLinea($linea, 'otros') . ", 2) AS monto_otros
                              FROM " . ($esIng ? 'ingresos_detalle' : 'egresos_detalle') . " d
                              INNER JOIN " . ($esIng ? 'ingresos_cabecera' : 'egresos_cabecera') . " c ON c.id = d." . ($esIng ? 'id_ingreso' : 'id_egreso') . "
                              LEFT JOIN (" . self::noMonetarioPorComprobante($flujo) . ") nm ON nm.id_comprobante = c.id
                              WHERE c.id = :id" . ($esIng ? '' : ' AND d.eliminado = false') . "
                              ORDER BY d.id", $params)->fetchAll(PDO::FETCH_ASSOC);

        $pagos = $this->q("SELECT fp.nombre AS forma, fp.tipo, p.monto, p.referencia, p.numero_cheque, p.fecha_cobro,
                                  " . self::opBancaria('p.tipo_operacion_bancaria') . " AS operacion_bancaria,
                                  fp.tipo IN (" . TiposFormaPago::noMonetariosSql() . ") AS no_monetario
                           FROM " . ($esIng ? 'ingresos_pagos' : 'egresos_pagos') . " p
                           INNER JOIN empresa_formas_pago fp ON fp.id = p." . ($esIng ? 'id_forma_cobro' : 'id_forma_pago') . "
                           WHERE p." . ($esIng ? 'id_ingreso' : 'id_egreso') . " = :id" . ($esIng ? '' : self::pagoVigente('p')) . "
                           ORDER BY p.id", [':id' => $id])->fetchAll(PDO::FETCH_ASSOC);

        $noMonetario = array_sum(array_map(static fn ($p) => !empty($p['no_monetario']) ? (float) $p['monto'] : 0.0, $pagos));
        $cab['no_monetario'] = round($noMonetario, 2);
        $cab['dinero']       = round((float) $cab['monto_total'] - $noMonetario, 2);
        return ['cabecera' => $cab, 'detalles' => $detalles, 'pagos' => $pagos];
    }

    // ── Vista Anticipos ───────────────────────────────────────────────────────

    /**
     * Movimientos de anticipos (alias m) con las mismas fuentes que la pestaña
     * "Anticipos" de la ficha y el saldo de la forma de cobro/pago
     * (FormaPagoRepository::sqlMovimientosAnticipo): saldo inicial, recibido/entregado
     * (ingreso/egreso con opción ANTICIPO_CLIENTE/PROVEEDOR) y aplicado (cobro/pago con
     * forma tipo ANTICIPO). Para todos los terceros, solo documentos de producción
     * (regla de reportes) y siempre vigentes. El tercero del comprobante es el cliente
     * o "recibí de" (ingresos) y el proveedor (egresos).
     */
    private function sqlMovimientosAnticipos(array $f): string
    {
        $ramas = [];
        foreach ($this->flujosAnticipos($f) as $flujo) {
            if ($flujo === 'INGRESO') {
                $vig = "c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB
                     . " AND LOWER(COALESCE(c.estado, '')) <> 'anulado'";
                $ramas[] = "SELECT 'INGRESO'::varchar AS tipo_flujo, a.id_cliente AS id_tercero, a.fecha_saldo::date AS fecha,
                                   'SALDO_INICIAL'::varchar AS origen, ''::varchar AS numero, COALESCE(fp.nombre, '')::varchar AS forma,
                                   COALESCE(a.observaciones, '')::text AS detalle, a.saldo_inicial AS monto, 0 AS id_doc
                            FROM saldos_iniciales_anticipos a
                            LEFT JOIN empresa_formas_pago fp ON fp.id = a.id_forma_pago
                            WHERE a.id_empresa = :id_empresa AND a.eliminado = false AND a.id_cliente IS NOT NULL";
                $ramas[] = "SELECT 'INGRESO', COALESCE(c.id_cliente, c.id_recibo_cliente), c.fecha_emision::date,
                                   'RECIBIDO', c.numero_ingreso, o.nombre,
                                   COALESCE(NULLIF(c.observaciones, ''), o.nombre, '')::text, c.monto_total, c.id
                            FROM ingresos_cabecera c
                            INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = c.id_ingreso_concepto AND o.comportamiento = 'ANTICIPO_CLIENTE'
                            WHERE $vig";
                $ramas[] = "SELECT 'INGRESO', COALESCE(c.id_cliente, c.id_recibo_cliente), c.fecha_emision::date,
                                   'APLICADO', c.numero_ingreso, fp.nombre,
                                   COALESCE((SELECT string_agg(d.tipo_documento || COALESCE(' ' || d.numero_documento, ''), ', ' ORDER BY d.id)
                                               FROM ingresos_detalle d WHERE d.id_ingreso = c.id), '')::text,
                                   p.monto, c.id
                            FROM ingresos_pagos p
                            INNER JOIN empresa_formas_pago fp ON fp.id = p.id_forma_cobro AND fp.tipo = 'ANTICIPO'
                            INNER JOIN ingresos_cabecera c ON c.id = p.id_ingreso
                            WHERE $vig";
            } else {
                $vig = "c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = " . self::AMB
                     . " AND LOWER(COALESCE(c.estado, '')) <> 'anulado'";
                $ramas[] = "SELECT 'EGRESO'::varchar, a.id_proveedor, a.fecha_saldo::date,
                                   'SALDO_INICIAL'::varchar, ''::varchar, COALESCE(fp.nombre, '')::varchar,
                                   COALESCE(a.observaciones, '')::text, a.saldo_inicial, 0
                            FROM saldos_iniciales_anticipos a
                            LEFT JOIN empresa_formas_pago fp ON fp.id = a.id_forma_pago
                            WHERE a.id_empresa = :id_empresa AND a.eliminado = false AND a.id_proveedor IS NOT NULL";
                $ramas[] = "SELECT 'EGRESO', c.id_proveedor, c.fecha_emision::date,
                                   'RECIBIDO', c.numero_egreso, o.nombre,
                                   COALESCE(NULLIF(c.observaciones, ''), o.nombre, '')::text, c.monto_total, c.id
                            FROM egresos_cabecera c
                            INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = c.id_egreso_concepto AND o.comportamiento = 'ANTICIPO_PROVEEDOR'
                            WHERE $vig";
                $ramas[] = "SELECT 'EGRESO', c.id_proveedor, c.fecha_emision::date,
                                   'APLICADO', c.numero_egreso, fp.nombre,
                                   COALESCE((SELECT string_agg(d.tipo_documento || COALESCE(' ' || d.numero_documento, ''), ', ' ORDER BY d.id)
                                               FROM egresos_detalle d WHERE d.id_egreso = c.id AND d.eliminado = false), '')::text,
                                   p.monto, c.id
                            FROM egresos_pagos p
                            INNER JOIN empresa_formas_pago fp ON fp.id = p.id_forma_pago AND fp.tipo = 'ANTICIPO'
                            INNER JOIN egresos_cabecera c ON c.id = p.id_egreso
                            WHERE $vig" . self::pagoVigente('p');
            }
        }
        // Ningún flujo aplica (p. ej. un empleado elegido): resultado vacío consistente.
        return $ramas ? implode("\n UNION ALL \n", $ramas)
            : "SELECT 'INGRESO'::varchar AS tipo_flujo, 0 AS id_tercero, NULL::date AS fecha, ''::varchar AS origen,
                      ''::varchar AS numero, ''::varchar AS forma, ''::text AS detalle, 0::numeric AS monto, 0 AS id_doc
               WHERE CAST(:id_empresa AS int) IS NULL";
    }

    /**
     * Flujos de la vista Anticipos según los filtros: clientes (ingresos) y/o
     * proveedores (egresos). Los empleados no tienen anticipos en este circuito.
     */
    private function flujosAnticipos(array $f): array
    {
        $flujos = match (strtoupper($f['tipo_flujo'] ?? 'AMBOS')) {
            'INGRESO' => ['INGRESO'], 'EGRESO' => ['EGRESO'], default => ['INGRESO', 'EGRESO'],
        };
        if (!empty($f['tercero_tipo'])) {
            $flujos = array_values(array_intersect($flujos, match ($f['tercero_tipo']) {
                'CLIENTE' => ['INGRESO'], 'PROVEEDOR' => ['EGRESO'], default => [],
            }));
        }
        return $flujos;
    }

    /**
     * Saldos de anticipos por tercero: saldo anterior (saldos iniciales y todo lo
     * anterior a "desde"), recibido/entregado y aplicado en el periodo, y saldo final.
     */
    public function getAnticiposResumen(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $mov = $this->sqlMovimientosAnticipos($f);
        [$antes, $enPeriodo, $where] = $this->periodoAnticipos($f, $params);
        $sql = "SELECT * FROM (
                    SELECT m.tipo_flujo, m.id_tercero,
                           CASE WHEN m.tipo_flujo = 'INGRESO' THEN 'CLIENTE' ELSE 'PROVEEDOR' END AS tercero_tipo,
                           COALESCE(cl.nombre, pr.razon_social, '— Sin cliente/proveedor —') AS tercero_nombre,
                           COALESCE(cl.identificacion, pr.identificacion, '') AS tercero_ident,
                           ROUND(COALESCE(SUM(CASE WHEN $antes THEN CASE WHEN m.origen = 'APLICADO' THEN -m.monto ELSE m.monto END END), 0), 2) AS saldo_anterior,
                           ROUND(COALESCE(SUM(CASE WHEN m.origen = 'RECIBIDO' AND $enPeriodo THEN m.monto END), 0), 2) AS recibido,
                           ROUND(COALESCE(SUM(CASE WHEN m.origen = 'APLICADO' AND $enPeriodo THEN m.monto END), 0), 2) AS aplicado,
                           COUNT(CASE WHEN m.origen <> 'SALDO_INICIAL' AND $enPeriodo THEN 1 END) AS movimientos
                    FROM ( $mov ) m
                    LEFT JOIN clientes    cl ON m.tipo_flujo = 'INGRESO' AND cl.id = m.id_tercero
                    LEFT JOIN proveedores pr ON m.tipo_flujo = 'EGRESO'  AND pr.id = m.id_tercero
                    WHERE $where
                    GROUP BY m.tipo_flujo, m.id_tercero, cl.nombre, pr.razon_social, cl.identificacion, pr.identificacion
                ) r
                WHERE (saldo_anterior <> 0 OR recibido <> 0 OR aplicado <> 0) " . $this->filtroTexto($f, $params, ['tercero_nombre', 'tercero_ident']) . "
                ORDER BY tipo_flujo, tercero_nombre";
        return array_map(static function (array $r): array {
            $r['saldo_final'] = round((float) $r['saldo_anterior'] + (float) $r['recibido'] - (float) $r['aplicado'], 2);
            return $r;
        }, $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Movimientos de anticipos de un tercero (filtro Cliente/Proveedor elegido): una
     * fila "Saldo anterior" y luego cada recibido/aplicado del periodo con su saldo.
     */
    public function getAnticiposMovimientos(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $mov = $this->sqlMovimientosAnticipos($f);
        [$antes, $enPeriodo, $where] = $this->periodoAnticipos($f, $params);
        $sql = "SELECT m.*, ($antes) AS es_anterior
                FROM ( $mov ) m
                WHERE $where
                ORDER BY ($antes) DESC, m.fecha, CASE m.origen WHEN 'SALDO_INICIAL' THEN 0 WHEN 'RECIBIDO' THEN 1 ELSE 2 END, m.id_doc";
        // Primero lo que va al saldo anterior; luego los del periodo con su saldo corrido.
        $anterior = 0.0;
        $saldo    = 0.0;
        $filas    = [];
        foreach ($this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $aplica = $m['origen'] === 'APLICADO';
            if (!empty($m['es_anterior'])) {
                $anterior = $saldo = round($saldo + ($aplica ? -1 : 1) * (float) $m['monto'], 2);
                continue;
            }
            $m['recibido'] = $aplica ? 0.0 : (float) $m['monto'];
            $m['aplicado'] = $aplica ? (float) $m['monto'] : 0.0;
            $m['saldo']    = $saldo = round($saldo + $m['recibido'] - $m['aplicado'], 2);
            $filas[] = $m;
        }
        return ['saldo_anterior' => $anterior, 'movimientos' => $filas, 'saldo_final' => $saldo];
    }

    /**
     * Piezas de periodo de la vista Anticipos: [condición "va al saldo anterior",
     * condición "movimiento del periodo", WHERE común (tercero y "hasta")]. Los saldos
     * iniciales siempre van al saldo anterior.
     */
    private function periodoAnticipos(array $f, array &$params): array
    {
        $antes = "m.origen = 'SALDO_INICIAL'";
        $where = "m.id_tercero IS NOT NULL";
        if (!empty($f['fecha_desde'])) {
            $antes .= " OR m.fecha < :adesde";
            $params[':adesde'] = $f['fecha_desde'];
        }
        if (!empty($f['fecha_hasta'])) {
            $where .= " AND (m.origen = 'SALDO_INICIAL' OR m.fecha <= :ahasta)";
            $params[':ahasta'] = $f['fecha_hasta'];
        }
        if (!empty($f['tercero_id']) && !empty($f['tercero_tipo'])) {
            $where .= " AND m.id_tercero = :atercero";
            $params[':atercero'] = (int) $f['tercero_id'];
        }
        return ["($antes)", "NOT ($antes)", $where];
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

    /**
     * Autocomplete de terceros por tipo (CLIENTE|PROVEEDOR|EMPLEADO). El texto pasa
     * por FiltrosBusqueda::condicionTexto(): todas las palabras escritas, en cualquier
     * orden y sin distinguir tildes ("carlos garcia" encuentra a "CARLOS MAURICIO
     * GARCÍA REVELO").
     */
    public function buscarTerceros(int $idEmpresa, string $tipo, string $q): array
    {
        [$tabla, $colNombre] = match ($tipo) {
            'PROVEEDOR' => ['proveedores', 'razon_social'],
            'EMPLEADO'  => ['empleados', 'nombres_apellidos'],
            default     => ['clientes', 'nombre'],
        };

        $params = [':e' => $idEmpresa];
        // Sin texto no se filtra: devuelve las primeras filas, como antes.
        $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
            [$colNombre, 'identificacion'],
            trim($q),
            $params,
            'ac'
        );
        $filtro = $condicion !== '' ? "AND {$condicion}" : '';

        $sql = "SELECT id, {$colNombre} AS nombre, identificacion AS ident
                FROM {$tabla}
                WHERE id_empresa = :e AND eliminado = false {$filtro}
                ORDER BY {$colNombre} LIMIT 15";
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
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
