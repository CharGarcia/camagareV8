<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Acceso a datos de Control Bancario: movimientos de cuentas bancarias
 * (empresa_formas_pago con id_banco + id_cuenta_contable) resueltos desde
 * asientos_contables_detalle, enriquecidos con la clasificación opcional
 * de control_bancario_movimientos y, cuando no hay clasificación manual,
 * con los datos ya existentes en ingresos_pagos/egresos_pagos.
 *
 * Reporte de empresa (como MayoresRepository) para el listado de movimientos:
 * no filtra por "registros propios", el acceso se controla por permiso de
 * módulo, no por dueño. Extiende BaseRepository solo para reusar
 * beginTransaction/commit/rollBack sobre las escrituras en
 * control_bancario_movimientos (la tabla propia de este módulo).
 */
class ControlBancarioRepository extends BaseRepository
{
    private const COLUMNAS_ORDEN = [
        'fecha_asiento', 'fecha_banco', 'fecha_cheque', 'tipo_transaccion',
        'nombre_entidad', 'numero_comprobante', 'debe', 'haber',
        'numero_cheque', 'beneficiario_cheque', 'documento_referencia',
        'referencia_detalle', 'saldo_acumulado',
    ];

    public function __construct()
    {
        parent::__construct('control_bancario_movimientos');
    }

    /**
     * Cuentas bancarias de la empresa: formas de pago con banco asignado. La cuenta contable
     * (id_cuenta_contable) YA NO es obligatoria para aparecer aquí: si falta, la cuenta se lista
     * igual (con cuenta_codigo/cuenta_nombre en NULL) para que el usuario vea que existe y sepa
     * que hay que configurarla — antes desaparecía sin aviso. Sin cuenta contable no hay mayor que
     * mostrar (getMovimientos filtra por esa cuenta), así que se ve vacía hasta que se asigne.
     */
    public function getFormasBancarias(int $idEmpresa): array
    {
        // Además de la cuenta base, la efectiva de cobros y de pagos (la que mueven los
        // asientos, ver getCuentasEfectivasForma): el PDF de conciliación muestra esa.
        $sql = "SELECT * FROM (
                    SELECT DISTINCT ON (fp.id)
                           fp.id, fp.nombre, fp.tipo, fp.tipo_cuenta, fp.numero_cuenta,
                           fp.id_cuenta_contable, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                           b.nombre_banco,
                           " . FormaPagoRepository::SELECT_CUENTAS_FLUJO . "
                    FROM empresa_formas_pago fp
                    LEFT JOIN plan_cuentas pc ON pc.id = fp.id_cuenta_contable
                    LEFT JOIN bancos_ecuador b ON b.id = fp.id_banco
                    " . FormaPagoRepository::JOIN_CUENTAS_FLUJO . "
                    WHERE fp.id_empresa = :id_empresa
                      AND fp.eliminado = FALSE
                      AND fp.activo = TRUE
                      AND fp.id_banco IS NOT NULL
                    ORDER BY fp.id, apc.id DESC NULLS LAST, app.id DESC NULLS LAST
                ) x
                ORDER BY x.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':emp_ap_cobro' => $idEmpresa, ':emp_ap_pago' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Igual que getFormasBancarias(): la cuenta contable puede venir NULL. */
    public function getFormaBancaria(int $idFormaPago, int $idEmpresa): ?array
    {
        $sql = "SELECT fp.id, fp.nombre, fp.tipo, fp.id_cuenta_contable, fp.id_banco, fp.numero_cuenta
                FROM empresa_formas_pago fp
                WHERE fp.id = :id AND fp.id_empresa = :id_empresa AND fp.eliminado = FALSE
                  AND fp.id_banco IS NOT NULL";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idFormaPago, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Formas bancarias de varias empresas (típicamente el grupo RUC: varias filas de `empresas`
     * que comparten RUC), con el nombre/establecimiento de cada una — para detectar cuáles
     * comparten cuenta real (mismo banco + número de cuenta) y armar el selector "Consolidar".
     */
    public function getFormasBancariasDeEmpresas(array $idsEmpresa): array
    {
        if (!$idsEmpresa) { return []; }
        $ph = implode(',', array_fill(0, count($idsEmpresa), '?'));
        $sql = "SELECT fp.id, fp.id_empresa, fp.nombre, fp.tipo_cuenta, fp.numero_cuenta, fp.id_banco,
                       fp.id_cuenta_contable, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                       b.nombre_banco,
                       COALESCE(NULLIF(e.nombre_comercial, ''), e.nombre) AS empresa_nombre,
                       e.establecimiento
                FROM empresa_formas_pago fp
                LEFT JOIN plan_cuentas pc ON pc.id = fp.id_cuenta_contable
                LEFT JOIN bancos_ecuador b ON b.id = fp.id_banco
                JOIN empresas e ON e.id = fp.id_empresa AND e.eliminado = FALSE
                WHERE fp.id_empresa IN ($ph)
                  AND fp.eliminado = FALSE AND fp.activo = TRUE AND fp.id_banco IS NOT NULL
                ORDER BY e.establecimiento ASC, fp.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute($idsEmpresa);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSaldoInicial(int $idEmpresa, int $idFormaPago): float
    {
        $sql = "SELECT saldo_inicial FROM saldos_iniciales_bancos
                WHERE id_empresa = :id_empresa AND id_forma_pago = :id_forma_pago AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_forma_pago' => $idFormaPago]);
        $val = $st->fetchColumn();
        return $val !== false ? (float) $val : 0.0;
    }

    // ── Comprobación contra contabilidad ─────────────────────────────────────────
    //
    // El módulo se arma con Ingresos/Egresos; la contabilidad es solo el punto de comparación.
    // Se cruza documento por documento: lo que cada ingreso/egreso movió en las cuentas
    // bancarias que usan la cuenta contable C (lado "documento") contra lo que su asiento movió
    // en C (lado "contable"). Un asiento sin ingreso/egreso detrás es una partida propia.

    //
    // La cuenta contable que se compara es la que de verdad mueven los asientos, no la base de
    // la forma de pago: la regla de Configuración Contable de la forma ('forma_cobro' para los
    // cobros, 'forma_pago' para los pagos) manda sobre empresa_formas_pago.id_cuenta_contable
    // (AsientoBuilderService::lineasFormas). Por eso cobros y pagos pueden ir a cuentas
    // distintas, y cada flujo se compara contra la suya.

    /**
     * Cuenta contable efectiva de cada flujo de la forma: ['cobro' => ?int, 'pago' => ?int].
     * Misma regla que FormaPagoRepository::SELECT_CUENTAS_FLUJO.
     */
    public function getCuentasEfectivasForma(int $idEmpresa, int $idFormaPago): array
    {
        $sql = "SELECT " . FormaPagoRepository::SELECT_CUENTAS_FLUJO . "
                FROM empresa_formas_pago fp
                " . FormaPagoRepository::JOIN_CUENTAS_FLUJO . "
                WHERE fp.id = :id AND fp.id_empresa = :id_empresa
                ORDER BY apc.id DESC NULLS LAST, app.id DESC NULLS LAST
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idFormaPago, ':id_empresa' => $idEmpresa, ':emp_ap_cobro' => $idEmpresa, ':emp_ap_pago' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'cobro' => !empty($r['id_cuenta_cobro']) ? (int) $r['id_cuenta_cobro'] : null,
            'pago' => !empty($r['id_cuenta_pago']) ? (int) $r['id_cuenta_pago'] : null,
        ];
    }

    /**
     * Formas bancarias (no eliminadas) de la empresa cuyos cobros o pagos van, según su cuenta
     * efectiva, a alguna de las cuentas indicadas. Trae id_cuenta_cobro / id_cuenta_pago para
     * que el llamador sepa qué flujo de cada forma entra en la comparación.
     */
    public function getFormasBancariasPorCuentas(int $idEmpresa, array $idsCuentas): array
    {
        $idsCuentas = array_values(array_unique(array_filter(array_map('intval', $idsCuentas)))) ?: [0];
        $in = implode(', ', $idsCuentas);
        $sql = "SELECT DISTINCT ON (fp.id) fp.id, fp.nombre, fp.numero_cuenta, b.nombre_banco,
                       " . FormaPagoRepository::SELECT_CUENTAS_FLUJO . "
                FROM empresa_formas_pago fp
                LEFT JOIN bancos_ecuador b ON b.id = fp.id_banco
                " . FormaPagoRepository::JOIN_CUENTAS_FLUJO . "
                WHERE fp.id_empresa = :id_empresa AND fp.eliminado = FALSE AND fp.id_banco IS NOT NULL
                  AND (COALESCE(apc.id_cuenta, fp.id_cuenta_contable) IN ({$in})
                       OR COALESCE(app.id_cuenta, fp.id_cuenta_contable) IN ({$in}))
                ORDER BY fp.id, apc.id DESC NULLS LAST, app.id DESC NULLS LAST";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':emp_ap_cobro' => $idEmpresa, ':emp_ap_pago' => $idEmpresa]);
        $filas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        usort($filas, static fn ($a, $b) => strcmp((string) $a['nombre'], (string) $b['nombre']));
        return $filas;
    }

    /** Código y nombre de cuentas contables de la empresa. */
    public function getCuentasContables(int $idEmpresa, array $idsCuentas): array
    {
        $idsCuentas = array_values(array_unique(array_filter(array_map('intval', $idsCuentas)))) ?: [0];
        $st = $this->db->prepare("SELECT id, codigo, nombre FROM plan_cuentas
                                  WHERE id IN (" . implode(', ', $idsCuentas) . ") AND id_empresa = :id_empresa
                                  ORDER BY codigo");
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * CTE `j`: una fila por documento (ingreso/egreso) o por asiento sin documento, con su
     * monto y fecha en cada lado. monto_doc / monto_asiento con signo: + entra al banco, − sale.
     * Placeholders: :e (empresa) y los de las marcas. $marcasCobro / $marcasPago: formas cuyos
     * cobros / pagos van a las cuentas comparadas; $cuentasIn: ids (enteros) de esas cuentas.
     */
    private function sqlCruceContable(string $marcasCobro, string $marcasPago, string $cuentasIn): string
    {
        return "WITH amb AS (
                    SELECT CAST(tipo_ambiente AS VARCHAR(1)) AS t FROM empresas WHERE id = :e
                ),
                d AS (
                    SELECT 'ingreso'::VARCHAR AS tipo, ic.id AS id_doc, ic.numero_ingreso AS numero,
                           ic.fecha_emision AS fecha, SUM(ip.monto) AS monto
                    FROM ingresos_pagos ip
                    JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                    WHERE ic.id_empresa = :e AND ic.eliminado = FALSE
                      AND COALESCE(ic.estado, 'registrado') <> 'anulado'
                      AND ic.tipo_ambiente = (SELECT t FROM amb)
                      AND ip.id_forma_cobro IN ({$marcasCobro})
                    GROUP BY ic.id
                    UNION ALL
                    SELECT 'egreso', ec.id, ec.numero_egreso, ec.fecha_emision, -SUM(ep.monto)
                    FROM egresos_pagos ep
                    JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                    WHERE ec.id_empresa = :e AND ec.eliminado = FALSE
                      AND COALESCE(ep.eliminado, FALSE) = FALSE
                      AND COALESCE(ec.estado, 'registrado') <> 'anulado'
                      AND COALESCE(ep.estado_cheque, 'vigente') <> 'anulado'
                      AND ec.tipo_ambiente = (SELECT t FROM amb)
                      AND ep.id_forma_pago IN ({$marcasPago})
                    GROUP BY ec.id
                    UNION ALL
                    -- Traspasos: entra en la cuenta destino (por su cuenta de cobro) y sale de la
                    -- origen (por su cuenta de pago), igual que los contabiliza el asiento. Un
                    -- traspaso entre dos cuentas comparadas queda en neto (una fila por traspaso).
                    SELECT 'traspaso', tc.id, tc.numero_traspaso, tc.fecha_emision,
                           (CASE WHEN tc.id_forma_destino IN ({$marcasCobro}) THEN tc.monto ELSE 0 END)
                         - (CASE WHEN tc.id_forma_origen IN ({$marcasPago}) THEN tc.monto ELSE 0 END)
                    FROM traspasos_cabecera tc
                    WHERE tc.id_empresa = :e AND tc.eliminado = FALSE
                      AND COALESCE(tc.estado, 'registrado') <> 'anulado'
                      AND tc.tipo_ambiente = (SELECT t FROM amb)
                      AND (tc.id_forma_destino IN ({$marcasCobro}) OR tc.id_forma_origen IN ({$marcasPago}))
                    UNION ALL
                    -- Liquidaciones de tarjetas cerradas: el neto depositado entra en el banco
                    -- destino (su asiento lo debita con la cuenta de cobro del destino).
                    SELECT 'conciliacion_tarjetas', ct.id, ct.numero, ct.fecha_conciliacion, ct.neto_depositado
                    FROM conciliacion_tarjetas_cabecera ct
                    WHERE ct.id_empresa = :e AND ct.eliminado = FALSE AND ct.estado = 'cerrada'
                      AND ct.neto_depositado <> 0
                      AND ct.tipo_ambiente = (SELECT t FROM amb)
                      AND ct.id_forma_cobro_destino IN ({$marcasCobro})
                ),
                k AS (
                    SELECT CASE WHEN icx.id IS NOT NULL THEN 'ingreso'
                                WHEN ecx.id IS NOT NULL THEN 'egreso'
                                WHEN tcx.id IS NOT NULL THEN 'traspaso'
                                WHEN ctx.id IS NOT NULL THEN 'conciliacion_tarjetas'
                                ELSE 'asiento' END::VARCHAR AS tipo,
                           COALESCE(icx.id, ecx.id, tcx.id, ctx.id, ac.id) AS id_doc,
                           MIN(ac.fecha_asiento) AS fecha,
                           SUM(ad.debe - ad.haber) AS monto,
                           MIN(ac.id) AS id_asiento,
                           STRING_AGG(DISTINCT ac.numero_comprobante, ', ') AS numero_asiento,
                           MIN(ac.concepto) AS concepto,
                           BOOL_OR(COALESCE(icx.eliminado, ecx.eliminado, tcx.eliminado, ctx.eliminado, FALSE)
                                   OR COALESCE(icx.estado, ecx.estado, tcx.estado, ctx.estado, '') IN ('anulado', 'anulada')) AS doc_anulado,
                           MIN(COALESCE(icx.numero_ingreso, ecx.numero_egreso, tcx.numero_traspaso, ctx.numero)) AS numero_doc
                    FROM asientos_contables_detalle ad
                    JOIN asientos_contables_cabecera ac ON ac.id = ad.id_asiento
                    LEFT JOIN ingresos_cabecera icx ON UPPER(ac.tipo_comprobante) = 'INGRESOS' AND COALESCE(ac.modulo_origen, '') <> 'conciliacion_tarjetas'
                         AND icx.id = ac.id_referencia_origen AND icx.id_empresa = ac.id_empresa
                    LEFT JOIN egresos_cabecera ecx ON UPPER(ac.tipo_comprobante) = 'EGRESOS'
                         AND ecx.id = ac.id_referencia_origen AND ecx.id_empresa = ac.id_empresa
                    -- Asiento del traspaso: nativo (modulo_origen 'traspaso') o enlazado desde el
                    -- traspaso (id_asiento_contable, p. ej. migrado).
                    LEFT JOIN traspasos_cabecera tcx ON tcx.id_empresa = ac.id_empresa
                         AND ((ac.modulo_origen = 'traspaso' AND tcx.id = ac.id_referencia_origen)
                              OR tcx.id_asiento_contable = ac.id)
                    -- Asiento de la liquidación de tarjetas (Conciliación de Tarjetas).
                    LEFT JOIN conciliacion_tarjetas_cabecera ctx ON ctx.id_empresa = ac.id_empresa
                         AND ((ac.modulo_origen = 'conciliacion_tarjetas' AND ctx.id = ac.id_referencia_origen)
                              OR ctx.id_asiento_contable = ac.id)
                    WHERE ac.id_empresa = :e AND ac.estado = 'contabilizado'
                      AND ac.eliminado = FALSE AND ad.eliminado = FALSE
                      AND ac.tipo_ambiente = (SELECT t FROM amb)
                      AND ad.id_cuenta_contable IN ({$cuentasIn})
                    GROUP BY 1, 2
                ),
                j AS (
                    SELECT COALESCE(d.tipo, k.tipo) AS tipo,
                           COALESCE(d.id_doc, k.id_doc) AS id_doc,
                           COALESCE(d.numero, k.numero_doc) AS numero,
                           d.fecha AS fecha_doc, d.monto AS monto_doc,
                           k.fecha AS fecha_asiento, k.monto AS monto_asiento,
                           k.id_asiento, k.numero_asiento, k.concepto,
                           COALESCE(k.doc_anulado, FALSE) AS doc_anulado
                    FROM d
                    FULL OUTER JOIN k ON k.tipo = d.tipo AND k.id_doc = d.id_doc
                )";
    }

    /**
     * Totales del cruce: lo movido según Ingresos/Egresos y según contabilidad, antes del
     * período y hasta su fin (sin saldo inicial: lo suma el service).
     */
    public function getTotalesCruceContable(int $idEmpresa, array $idsCuentas, array $idsFormasCobro, array $idsFormasPago, string $fechaInicio, string $fechaFin): array
    {
        [$sqlCruce, $params] = $this->armarCruce($idsCuentas, $idsFormasCobro, $idsFormasPago);
        $sql = $sqlCruce . "
                SELECT COALESCE(SUM(monto_doc)     FILTER (WHERE fecha_doc     <  :fi1), 0) AS doc_ini,
                       COALESCE(SUM(monto_doc)     FILTER (WHERE fecha_doc     <= :ff1), 0) AS doc_fin,
                       COALESCE(SUM(monto_asiento) FILTER (WHERE fecha_asiento <  :fi2), 0) AS cont_ini,
                       COALESCE(SUM(monto_asiento) FILTER (WHERE fecha_asiento <= :ff2), 0) AS cont_fin
                FROM j";
        $st = $this->db->prepare($sql);
        $st->execute($params + [
            ':e' => $idEmpresa,
            ':fi1' => $fechaInicio, ':ff1' => $fechaFin, ':fi2' => $fechaInicio, ':ff2' => $fechaFin,
        ]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('floatval', $r + ['doc_ini' => 0, 'doc_fin' => 0, 'cont_ini' => 0, 'cont_fin' => 0]);
    }

    /**
     * Movimientos DEL PERÍODO de los dos lados, cruzados documento por documento, en orden de
     * fecha (la del documento si cae en el rango; si no, la del asiento). Por cada uno:
     *  - efecto_doc / efecto_contable: lo que suma dentro del rango en cada lado (0 si su fecha
     *    cae fuera), para que el Service arrastre el saldo acumulado de cada lado.
     *  - diferencia = efecto_doc − efecto_contable.
     *  - clase: 'cuadra' si no hay diferencia; si no, el motivo (falta en un lado, monto
     *    distinto o fechas que caen en períodos distintos).
     */
    public function getPartidasCruceContable(int $idEmpresa, array $idsCuentas, array $idsFormasCobro, array $idsFormasPago, string $fechaInicio, string $fechaFin, int $limite = 1000): array
    {
        [$sqlCruce, $params] = $this->armarCruce($idsCuentas, $idsFormasCobro, $idsFormasPago);
        $sql = $sqlCruce . ",
                p AS (
                    SELECT j.*,
                           CASE WHEN fecha_doc BETWEEN :fi1 AND :ff1 THEN monto_doc ELSE 0 END AS efecto_doc,
                           CASE WHEN fecha_asiento BETWEEN :fi2 AND :ff2 THEN monto_asiento ELSE 0 END AS efecto_contable,
                           COALESCE(CASE WHEN fecha_doc BETWEEN :fi5 AND :ff5 THEN fecha_doc END, fecha_asiento) AS fecha_orden
                    FROM j
                    WHERE fecha_doc BETWEEN :fi3 AND :ff3 OR fecha_asiento BETWEEN :fi4 AND :ff4
                )
                SELECT p.*,
                       efecto_doc - efecto_contable AS diferencia,
                       -- Documento con asiento en esta cuenta pero sin pago con sus formas bancarias:
                       -- con qué forma(s) se registró, para que la etiqueta diga la causa.
                       CASE WHEN p.monto_doc IS NULL AND p.tipo = 'ingreso' THEN
                                (SELECT STRING_AGG(DISTINCT fpx.nombre, ', ')
                                   FROM ingresos_pagos ipx
                                   JOIN empresa_formas_pago fpx ON fpx.id = ipx.id_forma_cobro
                                  WHERE ipx.id_ingreso = p.id_doc)
                            WHEN p.monto_doc IS NULL AND p.tipo = 'egreso' THEN
                                (SELECT STRING_AGG(DISTINCT fpx.nombre, ', ')
                                   FROM egresos_pagos epx
                                   JOIN empresa_formas_pago fpx ON fpx.id = epx.id_forma_pago
                                  WHERE epx.id_egreso = p.id_doc AND COALESCE(epx.eliminado, FALSE) = FALSE)
                       END AS formas_doc,
                       oa.id AS id_asiento_doc, oa.numero_comprobante AS numero_asiento_doc,
                       CASE WHEN ABS(efecto_doc - efecto_contable) <= 0.005 THEN 'cuadra'
                            WHEN monto_asiento IS NULL AND oa.id IS NOT NULL THEN 'sin_cuenta_modulo'
                            WHEN monto_asiento IS NULL THEN 'solo_documento'
                            WHEN monto_doc IS NULL AND doc_anulado THEN 'documento_anulado'
                            WHEN monto_doc IS NULL AND tipo = 'asiento' THEN 'solo_contabilidad'
                            WHEN monto_doc IS NULL THEN 'otra_cuenta'
                            WHEN ABS(monto_doc - monto_asiento) > 0.005 THEN 'monto_distinto'
                            ELSE 'fecha_distinta' END AS clase
                FROM p
                -- Documento sin nada en la cuenta del banco: ¿tiene asiento en OTRAS cuentas? (el
                -- cobro/pago se contabilizó, pero contra otra cuenta). Mismo enlace que el CTE k.
                LEFT JOIN LATERAL (
                    SELECT ac.id, ac.numero_comprobante
                    FROM asientos_contables_cabecera ac
                    WHERE p.monto_asiento IS NULL AND p.tipo IN ('ingreso', 'egreso', 'traspaso', 'conciliacion_tarjetas')
                      AND ac.id_empresa = :e AND ac.estado = 'contabilizado' AND ac.eliminado = FALSE
                      AND ac.tipo_ambiente = (SELECT t FROM amb)
                      AND (   (p.tipo = 'ingreso'  AND UPPER(ac.tipo_comprobante) = 'INGRESOS' AND COALESCE(ac.modulo_origen, '') <> 'conciliacion_tarjetas' AND ac.id_referencia_origen = p.id_doc)
                           OR (p.tipo = 'egreso'   AND UPPER(ac.tipo_comprobante) = 'EGRESOS'  AND ac.id_referencia_origen = p.id_doc)
                           OR (p.tipo = 'traspaso' AND ac.modulo_origen = 'traspaso'           AND ac.id_referencia_origen = p.id_doc)
                           OR (p.tipo = 'traspaso' AND ac.id = (SELECT tcy.id_asiento_contable FROM traspasos_cabecera tcy WHERE tcy.id = p.id_doc))
                           OR (p.tipo = 'conciliacion_tarjetas' AND ac.modulo_origen = 'conciliacion_tarjetas' AND ac.id_referencia_origen = p.id_doc))
                    ORDER BY ac.id
                    LIMIT 1
                ) oa ON TRUE
                ORDER BY fecha_orden, tipo, id_doc
                LIMIT " . max(1, $limite);
        $st = $this->db->prepare($sql);
        $st->execute($params + [
            ':e' => $idEmpresa,
            ':fi1' => $fechaInicio, ':ff1' => $fechaFin, ':fi2' => $fechaInicio, ':ff2' => $fechaFin,
            ':fi3' => $fechaInicio, ':ff3' => $fechaFin, ':fi4' => $fechaInicio, ':ff4' => $fechaFin,
            ':fi5' => $fechaInicio, ':ff5' => $fechaFin,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** CTE del cruce con sus parámetros, para las cuentas y las formas de cada flujo. */
    private function armarCruce(array $idsCuentas, array $idsFormasCobro, array $idsFormasPago): array
    {
        [$marcasCobro, $paramsCobro] = $this->marcasFormas($idsFormasCobro, 'fc');
        [$marcasPago, $paramsPago] = $this->marcasFormas($idsFormasPago, 'fp');
        $cuentasIn = implode(', ', array_values(array_unique(array_filter(array_map('intval', $idsCuentas)))) ?: [0]);
        return [$this->sqlCruceContable($marcasCobro, $marcasPago, $cuentasIn), $paramsCobro + $paramsPago];
    }

    /** Placeholders :{prefijo}0, :{prefijo}1… para una lista IN de formas de pago. */
    private function marcasFormas(array $idsFormas, string $prefijo = 'f'): array
    {
        $idsFormas = array_values(array_unique(array_map('intval', $idsFormas))) ?: [0];
        $marcas = [];
        $params = [];
        foreach ($idsFormas as $i => $id) {
            $marcas[] = ":{$prefijo}{$i}";
            $params[":{$prefijo}{$i}"] = $id;
        }
        return [implode(', ', $marcas), $params];
    }

    /**
     * Resumen de la cuenta para el rango de fechas seleccionado:
     *   - delta_antes: suma (debe-haber) de todo lo anterior a fecha_inicio (para arrastrar el saldo).
     *   - creditos: suma de "debe" (entradas: depósitos/transferencias recibidas) dentro del rango.
     *   - debitos: suma de "haber" (salidas: cheques/transferencias emitidas) dentro del rango.
     * El saldo inicial del período = saldoInicialCuenta + delta_antes; el saldo final = ese saldo + créditos - débitos.
     */
    public function getResumenPeriodo(
        int $idEmpresa,
        int $idCuentaContable,
        string $fechaInicio,
        string $fechaFin,
        ?int $idFormaPago = null
    ): array {
        // Sin cuenta contable no hay mayor: el resumen se calcula sobre los cobros/pagos.
        if ($idCuentaContable <= 0) {
            return $this->getResumenPeriodoTesoreria($idEmpresa, (int) $idFormaPago, $fechaInicio, $fechaFin);
        }

        // La forma de pago no es opcional: identifica la cuenta bancaria en el enlace con los
        // cobros/pagos, del que sale el tipo de cada movimiento (y con él la regla del cheque).
        if (empty($idFormaPago)) {
            throw new \InvalidArgumentException('Falta la cuenta bancaria para calcular el resumen del período.');
        }

        return $this->sumarPeriodo(
            $this->baseContable(),
            $this->paramsContable($idEmpresa, (int) $idFormaPago, $idCuentaContable),
            $fechaInicio,
            $fechaFin
        );
    }

    /**
     * Suma del período sobre una base cruda, con la misma regla de cheques del listado
     * (ver conSaldoAcumulado): un cheque sin Fecha Banco no entra, y uno cobrado entra en
     * el período de esa fecha.
     *  - delta_antes: saldo arrastrado de todo lo anterior a fechaInicio.
     *  - creditos / debitos: entradas y salidas dentro del rango.
     */
    private function sumarPeriodo(string $crudo, array $params, string $fechaInicio, string $fechaFin): array
    {
        $afecta = self::SQL_AFECTA_SALDO;
        $fechaEfectiva = self::SQL_FECHA_EFECTIVA;

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN {$afecta} = 1 AND ({$fechaEfectiva}) < :f_ini
                                      THEN c.debe - c.haber ELSE 0 END), 0) AS delta_antes,
                    COALESCE(SUM(CASE WHEN {$afecta} = 1 AND ({$fechaEfectiva}) BETWEEN :f_ini2 AND :f_fin
                                      THEN c.debe ELSE 0 END), 0) AS creditos,
                    COALESCE(SUM(CASE WHEN {$afecta} = 1 AND ({$fechaEfectiva}) BETWEEN :f_ini3 AND :f_fin2
                                      THEN c.haber ELSE 0 END), 0) AS debitos
                FROM ({$crudo}) c";
        $st = $this->db->prepare($sql);
        $st->execute($params + [
            ':f_ini' => $fechaInicio, ':f_ini2' => $fechaInicio, ':f_ini3' => $fechaInicio,
            ':f_fin' => $fechaFin, ':f_fin2' => $fechaFin,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['delta_antes' => 0, 'creditos' => 0, 'debitos' => 0];
        return [
            'delta_antes' => (float) $row['delta_antes'],
            'creditos' => (float) $row['creditos'],
            'debitos' => (float) $row['debitos'],
        ];
    }

    /** Igual que getResumenPeriodo(), pero sobre los cobros/pagos de la cuenta (sin contabilidad). */
    private function getResumenPeriodoTesoreria(int $idEmpresa, int $idFormaPago, string $fechaInicio, string $fechaFin): array
    {
        if ($idFormaPago <= 0) {
            return ['delta_antes' => 0.0, 'creditos' => 0.0, 'debitos' => 0.0];
        }

        return $this->sumarPeriodo(
            $this->baseTesoreria(),
            $this->paramsTesoreria($idEmpresa, $idFormaPago),
            $fechaInicio,
            $fechaFin
        );
    }

    // ── Fuente TESORERÍA (cuentas sin cuenta contable) ──────────────────────
    //
    // Hay empresas que no llevan contabilidad pero sí controlan su cuenta bancaria: su
    // forma de pago bancaria no tiene id_cuenta_contable, así que no existe ningún
    // asiento del cual sacar el mayor. En ese caso el movimiento bancario se arma
    // directamente desde los COBROS y PAGOS registrados con esa forma de pago
    // (ingresos_pagos = entra plata / egresos_pagos = sale plata), que es exactamente
    // lo que pasó por el banco.
    //
    // Las columnas son las MISMAS que devuelve la fuente contable (getMovimientos),
    // más origen_tipo/origen_id, para que controller, exportaciones y conciliación
    // funcionen igual sin importar de dónde salieron los datos. id_asiento_detalle e
    // id_asiento vienen NULL: no hay asiento detrás, y la clasificación manual se
    // ancla al par (origen_tipo, origen_id) — ver migración
    // 20260824_control_bancario_sin_contabilidad.sql.
    //
    // Una fila por cada línea de pago (no por documento): un mismo ingreso/egreso puede
    // pagarse con dos cheques distintos a la misma cuenta, y cada uno es un movimiento
    // bancario propio.

    /**
     * Movimientos de tesorería de una forma de pago, sin saldo acumulado ni filtros.
     * Placeholders: :id_empresa_i, :id_forma_i, :amb_i (rama ingresos) y
     * :id_empresa_e, :id_forma_e, :amb_e (rama egresos).
     */
    private function baseTesoreria(): string
    {
        return "
            SELECT
                NULL::INTEGER AS id_asiento_detalle,
                NULL::INTEGER AS id_asiento,
                'ingreso'::VARCHAR AS origen_tipo,
                ip.id AS origen_id,
                ic.fecha_emision AS fecha_asiento,
                ic.numero_ingreso AS numero_comprobante,
                COALESCE(NULLIF(ic.observaciones, ''), cnc.nombre, 'Cobro') AS concepto,
                COALESCE(NULLIF(ip.observaciones, ''), NULLIF(ic.observaciones, ''), cnc.nombre) AS referencia_detalle,
                NULLIF(ip.referencia, '') AS documento_referencia,
                ip.monto AS debe,
                0::NUMERIC AS haber,
                CASE WHEN ic.id_cliente IS NOT NULL THEN 'cliente' ELSE NULL END AS tipo_entidad,
                ic.id_cliente AS id_entidad,
                COALESCE(cli.nombre, NULLIF(ic.recibo_de, '')) AS nombre_entidad,
                COALESCE(cli.nombre, NULLIF(ic.recibo_de, '')) AS beneficiario_cheque,
                COALESCE(cbm.tipo_transaccion,
                         UPPER(NULLIF(ip.tipo_operacion_bancaria, '')),
                         CASE fp.tipo
                             WHEN 'CHEQUE' THEN 'CHEQUE'
                             WHEN 'BANCO' THEN 'DEPOSITO'
                             ELSE 'OTRO'
                         END) AS tipo_transaccion,
                COALESCE(cbm.cheque_direccion, 'RECIBIDO') AS cheque_direccion,
                COALESCE(cbm.numero_cheque, NULLIF(ip.numero_cheque, '')) AS numero_cheque,
                COALESCE(cbm.fecha_cheque, ip.fecha_cobro) AS fecha_cheque,
                COALESCE(cbm.fecha_banco, ic.fecha_emision) AS fecha_banco,
                cbm.fecha_banco AS fecha_banco_manual,
                cbm.id AS id_clasificacion,
                cbm.observacion AS observacion,
                TRUE AS tiene_documento
            FROM ingresos_pagos ip
            INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
            INNER JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
            LEFT JOIN empresa_ingreso_conceptos cnc ON cnc.id = ic.id_ingreso_concepto
            LEFT JOIN clientes cli ON cli.id = ic.id_cliente
            LEFT JOIN control_bancario_movimientos cbm
                   ON cbm.origen_tipo = 'ingreso' AND cbm.origen_id = ip.id AND cbm.eliminado = FALSE
            WHERE ic.id_empresa = :id_empresa_i
              AND ip.id_forma_cobro = :id_forma_i
              AND ic.eliminado = FALSE
              AND COALESCE(ic.estado, 'registrado') <> 'anulado'
              AND ic.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb_i)

            UNION ALL

            SELECT
                NULL::INTEGER AS id_asiento_detalle,
                NULL::INTEGER AS id_asiento,
                'egreso'::VARCHAR AS origen_tipo,
                ep.id AS origen_id,
                ec.fecha_emision AS fecha_asiento,
                ec.numero_egreso AS numero_comprobante,
                COALESCE(NULLIF(ec.observaciones, ''), cne.nombre, 'Pago') AS concepto,
                COALESCE(NULLIF(ec.observaciones, ''), cne.nombre) AS referencia_detalle,
                NULLIF(ep.referencia, '') AS documento_referencia,
                0::NUMERIC AS debe,
                ep.monto AS haber,
                CASE
                    WHEN ec.id_proveedor IS NOT NULL THEN 'proveedor'
                    WHEN ec.id_empleado IS NOT NULL THEN 'empleado'
                    ELSE NULL
                END AS tipo_entidad,
                COALESCE(ec.id_proveedor, ec.id_empleado) AS id_entidad,
                COALESCE(prov.razon_social, empl.nombres_apellidos, NULLIF(ec.beneficiario_nombre, '')) AS nombre_entidad,
                COALESCE(NULLIF(ep.beneficiario_cheque, ''), prov.razon_social, empl.nombres_apellidos,
                         NULLIF(ec.beneficiario_nombre, '')) AS beneficiario_cheque,
                COALESCE(cbm.tipo_transaccion,
                         UPPER(NULLIF(ep.tipo_operacion_bancaria, '')),
                         CASE fp.tipo
                             WHEN 'CHEQUE' THEN 'CHEQUE'
                             WHEN 'BANCO' THEN 'TRANSFERENCIA'
                             ELSE 'OTRO'
                         END) AS tipo_transaccion,
                COALESCE(cbm.cheque_direccion, 'EMITIDO') AS cheque_direccion,
                COALESCE(cbm.numero_cheque, NULLIF(ep.numero_cheque, '')) AS numero_cheque,
                COALESCE(cbm.fecha_cheque, ep.fecha_cobro) AS fecha_cheque,
                COALESCE(cbm.fecha_banco, ec.fecha_emision) AS fecha_banco,
                cbm.fecha_banco AS fecha_banco_manual,
                cbm.id AS id_clasificacion,
                cbm.observacion AS observacion,
                TRUE AS tiene_documento
            FROM egresos_pagos ep
            INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
            INNER JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
            LEFT JOIN egresos_conceptos cne ON cne.id = ec.id_egreso_concepto
            LEFT JOIN proveedores prov ON prov.id = ec.id_proveedor
            LEFT JOIN empleados empl ON empl.id = ec.id_empleado
            LEFT JOIN control_bancario_movimientos cbm
                   ON cbm.origen_tipo = 'egreso' AND cbm.origen_id = ep.id AND cbm.eliminado = FALSE
            WHERE ec.id_empresa = :id_empresa_e
              AND ep.id_forma_pago = :id_forma_e
              AND ec.eliminado = FALSE
              AND COALESCE(ep.eliminado, FALSE) = FALSE
              AND COALESCE(ec.estado, 'registrado') <> 'anulado'
              AND COALESCE(ep.estado_cheque, 'vigente') <> 'anulado'
              AND ec.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb_e)

            UNION ALL

            -- Traspasos de fondos (traspasos_cabecera): entra dinero en la cuenta DESTINO y sale
            -- de la cuenta ORIGEN (p. ej. el depósito del efectivo de Caja). Su asiento mueve la
            -- cuenta del banco, así que también es movimiento del banco. Cada lado tiene su propia
            -- ancla de clasificación ('trasp_in' / 'trasp_out' + id del traspaso): un traspaso
            -- entre dos bancos se concilia por separado en cada uno.
            SELECT
                NULL::INTEGER AS id_asiento_detalle,
                NULL::INTEGER AS id_asiento,
                'trasp_in'::VARCHAR AS origen_tipo,
                tc.id AS origen_id,
                tc.fecha_emision AS fecha_asiento,
                tc.numero_traspaso AS numero_comprobante,
                'Traspaso desde ' || COALESCE(fo.nombre, 'otra cuenta') AS concepto,
                COALESCE(NULLIF(tc.observaciones, ''), 'Traspaso desde ' || COALESCE(fo.nombre, 'otra cuenta')) AS referencia_detalle,
                NULL::VARCHAR AS documento_referencia,
                tc.monto AS debe,
                0::NUMERIC AS haber,
                NULL::VARCHAR AS tipo_entidad,
                NULL::INTEGER AS id_entidad,
                COALESCE(fo.nombre, 'otra cuenta') AS nombre_entidad,
                COALESCE(fo.nombre, 'otra cuenta') AS beneficiario_cheque,
                COALESCE(cbm.tipo_transaccion, 'DEPOSITO') AS tipo_transaccion,
                COALESCE(cbm.cheque_direccion, 'RECIBIDO') AS cheque_direccion,
                cbm.numero_cheque AS numero_cheque,
                cbm.fecha_cheque AS fecha_cheque,
                COALESCE(cbm.fecha_banco, tc.fecha_emision) AS fecha_banco,
                cbm.fecha_banco AS fecha_banco_manual,
                cbm.id AS id_clasificacion,
                cbm.observacion AS observacion,
                TRUE AS tiene_documento
            FROM traspasos_cabecera tc
            LEFT JOIN empresa_formas_pago fo ON fo.id = tc.id_forma_origen
            LEFT JOIN control_bancario_movimientos cbm
                   ON cbm.origen_tipo = 'trasp_in' AND cbm.origen_id = tc.id AND cbm.eliminado = FALSE
            WHERE tc.id_empresa = :id_empresa_ti
              AND tc.id_forma_destino = :id_forma_ti
              AND tc.eliminado = FALSE
              AND COALESCE(tc.estado, 'registrado') <> 'anulado'
              AND tc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb_ti)

            UNION ALL

            SELECT
                NULL::INTEGER AS id_asiento_detalle,
                NULL::INTEGER AS id_asiento,
                'trasp_out'::VARCHAR AS origen_tipo,
                tc.id AS origen_id,
                tc.fecha_emision AS fecha_asiento,
                tc.numero_traspaso AS numero_comprobante,
                'Traspaso a ' || COALESCE(fd.nombre, 'otra cuenta') AS concepto,
                COALESCE(NULLIF(tc.observaciones, ''), 'Traspaso a ' || COALESCE(fd.nombre, 'otra cuenta')) AS referencia_detalle,
                NULL::VARCHAR AS documento_referencia,
                0::NUMERIC AS debe,
                tc.monto AS haber,
                NULL::VARCHAR AS tipo_entidad,
                NULL::INTEGER AS id_entidad,
                COALESCE(fd.nombre, 'otra cuenta') AS nombre_entidad,
                COALESCE(fd.nombre, 'otra cuenta') AS beneficiario_cheque,
                COALESCE(cbm.tipo_transaccion, 'TRANSFERENCIA') AS tipo_transaccion,
                COALESCE(cbm.cheque_direccion, 'EMITIDO') AS cheque_direccion,
                cbm.numero_cheque AS numero_cheque,
                cbm.fecha_cheque AS fecha_cheque,
                COALESCE(cbm.fecha_banco, tc.fecha_emision) AS fecha_banco,
                cbm.fecha_banco AS fecha_banco_manual,
                cbm.id AS id_clasificacion,
                cbm.observacion AS observacion,
                TRUE AS tiene_documento
            FROM traspasos_cabecera tc
            LEFT JOIN empresa_formas_pago fd ON fd.id = tc.id_forma_destino
            LEFT JOIN control_bancario_movimientos cbm
                   ON cbm.origen_tipo = 'trasp_out' AND cbm.origen_id = tc.id AND cbm.eliminado = FALSE
            WHERE tc.id_empresa = :id_empresa_to
              AND tc.id_forma_origen = :id_forma_to
              AND tc.eliminado = FALSE
              AND COALESCE(tc.estado, 'registrado') <> 'anulado'
              AND tc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb_to)

            UNION ALL

            -- Liquidación de tarjetas (Conciliación de Tarjetas cerrada): la procesadora deposita
            -- el neto en el banco destino. No genera un ingreso, pero su asiento debita la cuenta
            -- del banco, así que también es movimiento del banco. Ancla: 'liq_tarj' + id.
            SELECT
                NULL::INTEGER AS id_asiento_detalle,
                NULL::INTEGER AS id_asiento,
                'liq_tarj'::VARCHAR AS origen_tipo,
                ct.id AS origen_id,
                ct.fecha_conciliacion AS fecha_asiento,
                ct.numero AS numero_comprobante,
                'Liquidación de ' || COALESCE(fpt.nombre, 'tarjetas') AS concepto,
                COALESCE(NULLIF(ct.observaciones, ''), 'Liquidación de ' || COALESCE(fpt.nombre, 'tarjetas')) AS referencia_detalle,
                NULL::VARCHAR AS documento_referencia,
                ct.neto_depositado AS debe,
                0::NUMERIC AS haber,
                NULL::VARCHAR AS tipo_entidad,
                NULL::INTEGER AS id_entidad,
                COALESCE(fpt.nombre, 'tarjetas') AS nombre_entidad,
                COALESCE(fpt.nombre, 'tarjetas') AS beneficiario_cheque,
                COALESCE(cbm.tipo_transaccion, 'DEPOSITO') AS tipo_transaccion,
                COALESCE(cbm.cheque_direccion, 'RECIBIDO') AS cheque_direccion,
                cbm.numero_cheque AS numero_cheque,
                cbm.fecha_cheque AS fecha_cheque,
                COALESCE(cbm.fecha_banco, ct.fecha_conciliacion) AS fecha_banco,
                cbm.fecha_banco AS fecha_banco_manual,
                cbm.id AS id_clasificacion,
                cbm.observacion AS observacion,
                TRUE AS tiene_documento
            FROM conciliacion_tarjetas_cabecera ct
            LEFT JOIN empresa_formas_pago fpt ON fpt.id = ct.id_forma_cobro
            LEFT JOIN control_bancario_movimientos cbm
                   ON cbm.origen_tipo = 'liq_tarj' AND cbm.origen_id = ct.id AND cbm.eliminado = FALSE
            WHERE ct.id_empresa = :id_empresa_lt
              AND ct.id_forma_cobro_destino = :id_forma_lt
              AND ct.eliminado = FALSE
              AND ct.estado = 'cerrada'
              AND ct.neto_depositado <> 0
              AND ct.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb_lt)";
    }

    /** Parámetros que espera baseTesoreria(). */
    private function paramsTesoreria(int $idEmpresa, int $idFormaPago): array
    {
        return [
            ':id_empresa_i' => $idEmpresa, ':id_forma_i' => $idFormaPago, ':amb_i' => $idEmpresa,
            ':id_empresa_e' => $idEmpresa, ':id_forma_e' => $idFormaPago, ':amb_e' => $idEmpresa,
            ':id_empresa_ti' => $idEmpresa, ':id_forma_ti' => $idFormaPago, ':amb_ti' => $idEmpresa,
            ':id_empresa_to' => $idEmpresa, ':id_forma_to' => $idFormaPago, ':amb_to' => $idEmpresa,
            ':id_empresa_lt' => $idEmpresa, ':id_forma_lt' => $idFormaPago, ':amb_lt' => $idEmpresa,
        ];
    }

    /**
     * Fragmento SQL común: deriva tipo/dirección/número de cheque/fechas desde la
     * clasificación manual (control_bancario_movimientos) o, si no existe, desde
     * ingresos_pagos/egresos_pagos (vía tipo_comprobante + id_referencia_origen del asiento).
     *
     * Para el tipo de transacción: si el cobro/pago ya trae un dato más específico
     * (ip.tipo_operacion_bancaria: DEPOSITO/TRANSFERENCIA/CHEQUE/DEBITO) se usa ese.
     * Si no, pero SÍ hay un ingreso/egreso real enlazado (ip.id/ep.id no nulo — esto
     * incluye ingresos/egresos MIGRADOS: la migración también inserta su fila en
     * ingresos_pagos/egresos_pagos, solo que sin tipo_operacion_bancaria), o el asiento
     * viene de otro documento real del sistema (recibo_venta, factura_venta, compra,
     * etc. — cualquiera menos 'migracion'/'manual' sin ingreso/egreso detrás), se usa
     * el TIPO de la forma de pago de la cuenta (fp.tipo: BANCO→Transferencia,
     * CHEQUE→Cheque), porque esa línea SÍ es un movimiento bancario real aunque el
     * documento de origen no guarde el detalle del método de cobro.
     *
     * Caso adicional (migración de CONTABILIDAD sin el módulo Ingresos/Egresos migrado,
     * o documento roto/no enlazado): el asiento migrado puede no tener ip/ep, pero el
     * sistema viejo sí clasificó ese diario como tipo INGRESOS/EGRESOS, y eso quedó
     * grabado en ac.tipo_comprobante aunque id_referencia_origen no haya podido
     * enlazarse a un documento. En datos migrados por corridas antiguas ese valor quedó
     * en MAYÚSCULAS ('EGRESOS', 'COMPRAS_SERVICIOS'..., el 'tipo' crudo del sistema
     * viejo) en vez del valor en minúsculas que usan los asientos nativos ('ingresos',
     * 'egresos'); por eso la comparación va con UPPER() en ambos lados. También ahí se
     * aplica el fp.tipo, porque el dato de "es un cobro/pago bancario de esta cuenta" ya
     * viene del sistema viejo. Solo queda "OTRO" para asientos manuales o para el diario
     * general migrado sin esa clasificación, donde no hay ningún documento de negocio
     * detrás que sustente la inferencia.
     *
     * Dirección del movimiento en una cuenta BANCO (sin tipo_operacion_bancaria explícito):
     * plata que ENTRA (debe > 0) es un "Depósito" en el lenguaje del estado de cuenta;
     * plata que SALE (haber > 0) es una "Transferencia" (salida). Antes se etiquetaba
     * todo como Transferencia sin importar la dirección.
     */
    private function selectDerivado(): string
    {
        return "
            COALESCE(cbm.tipo_transaccion,
                     CASE
                         WHEN ip.id IS NOT NULL AND NULLIF(ip.tipo_operacion_bancaria, '') IS NOT NULL
                             THEN UPPER(ip.tipo_operacion_bancaria)
                         WHEN ep.id IS NOT NULL AND NULLIF(ep.tipo_operacion_bancaria, '') IS NOT NULL
                             THEN UPPER(ep.tipo_operacion_bancaria)
                         WHEN ip.id IS NOT NULL OR ep.id IS NOT NULL
                              OR ac.modulo_origen NOT IN ('migracion', 'manual')
                              OR UPPER(ac.tipo_comprobante) IN ('INGRESOS', 'EGRESOS') THEN
                             CASE fp.tipo
                                 WHEN 'CHEQUE' THEN 'CHEQUE'
                                 WHEN 'BANCO'  THEN CASE WHEN ad.debe > 0 THEN 'DEPOSITO' ELSE 'TRANSFERENCIA' END
                                 ELSE 'OTRO'
                             END
                         ELSE 'OTRO'
                     END) AS tipo_transaccion,
            COALESCE(cbm.cheque_direccion,
                     CASE
                         WHEN ip.id IS NOT NULL THEN 'RECIBIDO'
                         WHEN ep.id IS NOT NULL THEN 'EMITIDO'
                         WHEN ac.modulo_origen NOT IN ('migracion', 'manual')
                              OR UPPER(ac.tipo_comprobante) IN ('INGRESOS', 'EGRESOS') THEN
                             CASE WHEN ad.debe > 0 THEN 'RECIBIDO' WHEN ad.haber > 0 THEN 'EMITIDO' ELSE NULL END
                         ELSE NULL
                     END) AS cheque_direccion,
            COALESCE(cbm.numero_cheque, ip.numero_cheque, NULLIF(ep.numero_cheque, '')) AS numero_cheque,
            COALESCE(cbm.fecha_cheque, ip.fecha_cobro, ep.fecha_cobro) AS fecha_cheque,
            COALESCE(cbm.fecha_banco, ac.fecha_asiento) AS fecha_banco,
            cbm.fecha_banco AS fecha_banco_manual,
            cbm.id AS id_clasificacion,
            cbm.observacion AS observacion,
            -- El movimiento está enlazado a un cobro/pago real: sus datos (tipo, cheque) los
            -- manda ese documento, no este módulo. Aquí solo se concilia (Fecha Banco).
            (ip.id IS NOT NULL OR ep.id IS NOT NULL) AS tiene_documento";
    }

    private function joinsDerivado(string $aliasForma = ':id_forma_pago'): string
    {
        // Se usa ac.tipo_comprobante ('ingresos'/'egresos') en vez de ac.modulo_origen para
        // que el enlace también funcione en asientos MIGRADOS: la migración de contabilidad
        // deja modulo_origen='migracion' siempre (así el sincronizador nunca los toca), pero
        // sí setea tipo_comprobante='ingresos'/'egresos' e id_referencia_origen apuntando al
        // ingreso/egreso ya migrado (igual que un asiento nativo). Filtrar por modulo_origen
        // dejaba a los migrados sin ip/ep y por eso siempre caían en "OTRO". UPPER() porque
        // corridas de migración antiguas dejaron el valor en mayúsculas ('EGRESOS', 'INGRESOS').
        //
        // LATERAL + LIMIT 1 (en vez de un LEFT JOIN plano): un ingreso/egreso puede tener MÁS
        // de una fila en ingresos_pagos/egresos_pagos con la MISMA forma de pago (p. ej. dos
        // cheques distintos depositados el mismo día a la misma cuenta). Un LEFT JOIN normal
        // matchearía ambas filas contra la MISMA línea del asiento (ad), duplicando esa línea
        // en el listado y descuadrando el saldo acumulado (la ventana SUM() la contaría dos
        // veces). LATERAL garantiza como máximo una fila de pago por línea de asiento.
        //
        // El match es por CUENTA CONTABLE (fp2.id_cuenta_contable = fp.id_cuenta_contable), no
        // por "misma forma de pago exacta" (fp2.id = fp.id): una cuenta bancaria puede tener
        // MÁS de una forma de pago bancaria apuntando a ella a propósito (p. ej. "Cheques
        // Pichincha" y "Transferencias Pichincha" son la MISMA cuenta física vista por dos
        // formas — ver FormaPagoService::validar()). Si el pago se hizo con la OTRA forma
        // bancaria de esa misma cuenta, igual debe encontrarse aquí; si no, se pierde el dato
        // específico (tipo_operacion_bancaria, número de cheque) al verla desde la forma que no
        // se usó para ese pago en particular.
        return "
            LEFT JOIN control_bancario_movimientos cbm ON cbm.id_asiento_detalle = ad.id AND cbm.eliminado = FALSE
            LEFT JOIN LATERAL (
                SELECT ip2.* FROM ingresos_pagos ip2
                INNER JOIN empresa_formas_pago fp2 ON fp2.id = ip2.id_forma_cobro
                    AND fp2.id_cuenta_contable = fp.id_cuenta_contable AND fp2.eliminado = FALSE
                WHERE UPPER(ac.tipo_comprobante) = 'INGRESOS' AND COALESCE(ac.modulo_origen, '') <> 'conciliacion_tarjetas'
                  AND ac.id_referencia_origen = ip2.id_ingreso
                ORDER BY ip2.id
                LIMIT 1
            ) ip ON TRUE
            LEFT JOIN LATERAL (
                SELECT ep2.* FROM egresos_pagos ep2
                INNER JOIN empresa_formas_pago fp2 ON fp2.id = ep2.id_forma_pago
                    AND fp2.id_cuenta_contable = fp.id_cuenta_contable AND fp2.eliminado = FALSE
                WHERE UPPER(ac.tipo_comprobante) = 'EGRESOS'
                  AND ac.id_referencia_origen = ep2.id_egreso
                  AND ep2.eliminado = FALSE
                  AND COALESCE(ep2.estado_cheque, 'vigente') <> 'anulado'
                ORDER BY ep2.id
                LIMIT 1
            ) ep ON TRUE
            LEFT JOIN ingresos_cabecera icb ON icb.id = ip.id_ingreso
            LEFT JOIN clientes clib ON clib.id = icb.id_cliente
            LEFT JOIN egresos_cabecera ecb ON ecb.id = ep.id_egreso";
    }

    /**
     * Beneficiario / cliente del movimiento, en orden de preferencia: el beneficiario
     * escrito en el cheque del egreso; la entidad de la línea del asiento (cliente,
     * proveedor o empleado); y, si la línea del banco no lleva entidad (asientos
     * antiguos o migrados) o el ingreso se registró sin cliente de catálogo, el cliente
     * o el "Recibí de" de la cabecera del ingreso, o el beneficiario libre del egreso.
     * Requiere los alias cli/prov de la consulta que lo usa y los de joinsDerivado().
     */
    private function sqlBeneficiario(?string $emp = 'emp'): string
    {
        $empleado = $emp !== null ? "{$emp}.nombres_apellidos, " : '';
        return "COALESCE(NULLIF(ep.beneficiario_cheque, ''), cli.nombre, prov.razon_social, {$empleado}
                         clib.nombre, NULLIF(icb.recibo_de, ''), NULLIF(ecb.beneficiario_nombre, ''))";
    }

    /**
     * Un ingreso/egreso ANULADO (o eliminado) no es un movimiento del banco, aunque su
     * asiento siga 'contabilizado' (la anulación del asiento corre fuera de la
     * transacción del documento y puede fallar en silencio; los migrados tampoco traen
     * siempre el asiento anulado). Se excluye por el documento de origen del asiento, no
     * por el enlace ip/ep, para que aplique también a asientos sin línea de pago enlazada.
     * Requiere el alias `ac` (asientos_contables_cabecera).
     */
    private function sqlExcluirOrigenAnulado(): string
    {
        return "
              AND NOT EXISTS (
                    SELECT 1 FROM ingresos_cabecera icx
                    WHERE UPPER(ac.tipo_comprobante) = 'INGRESOS' AND COALESCE(ac.modulo_origen, '') <> 'conciliacion_tarjetas'
                      AND icx.id = ac.id_referencia_origen
                      AND (icx.eliminado = TRUE OR COALESCE(icx.estado, '') = 'anulado')
              )
              AND NOT EXISTS (
                    SELECT 1 FROM egresos_cabecera ecx
                    WHERE UPPER(ac.tipo_comprobante) = 'EGRESOS'
                      AND ecx.id = ac.id_referencia_origen
                      AND (ecx.eliminado = TRUE OR COALESCE(ecx.estado, '') = 'anulado')
              )";
    }

    /**
     * Un CHEQUE solo mueve el saldo del banco cuando se cobró, es decir cuando tiene
     * Fecha Banco registrada (cbm.fecha_banco). Girar un cheque no saca la plata de la
     * cuenta: el banco la descuenta el día que lo hacen efectivo, y lo mismo vale para
     * un cheque recibido de un cliente (entra cuando se acredita, no cuando se recibe).
     *
     * De ahí salen dos expresiones que se usan en TODO el módulo, sobre las columnas ya
     * derivadas (por eso se aplican en una capa exterior, no en el mismo SELECT):
     *  - `afecta_saldo`: 0 para un cheque sin Fecha Banco, 1 para todo lo demás.
     *  - `fecha_efectiva`: la fecha con la que el movimiento pesa en el banco — la Fecha
     *    Banco del cheque; si aún no se cobró, la del documento (para que la fila siga
     *    apareciendo en el listado y se pueda marcar como cobrada).
     * Los demás movimientos (transferencias, depósitos, débitos) no cambian: pesan con
     * la fecha del documento, como siempre.
     */
    private const SQL_AFECTA_SALDO =
        "CASE WHEN c.tipo_transaccion = 'CHEQUE' AND c.fecha_banco_manual IS NULL THEN 0 ELSE 1 END";

    private const SQL_FECHA_EFECTIVA =
        "CASE WHEN c.tipo_transaccion = 'CHEQUE' THEN COALESCE(c.fecha_banco_manual, c.fecha_asiento)
              ELSE c.fecha_asiento END";

    /**
     * Envuelve el SELECT crudo (contable o de tesorería) agregando la fecha efectiva, el
     * indicador de si mueve el saldo, y el saldo acumulado — que se calcula en orden de
     * fecha efectiva y salta los cheques todavía no cobrados.
     */
    private function conSaldoAcumulado(string $crudo): string
    {
        $afecta = self::SQL_AFECTA_SALDO;
        $fechaEfectiva = self::SQL_FECHA_EFECTIVA;

        return "SELECT c.*,
                       {$afecta} AS afecta_saldo,
                       {$fechaEfectiva} AS fecha_efectiva,
                       (:saldo_inicial + SUM(CASE WHEN {$afecta} = 0 THEN 0 ELSE c.debe - c.haber END) OVER (
                           ORDER BY ({$fechaEfectiva}), COALESCE(c.id_asiento, 0),
                                    COALESCE(c.id_asiento_detalle, c.origen_id, 0)
                           ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                       )) AS saldo_acumulado
                FROM ({$crudo}) c";
    }

    /**
     * Movimientos de una cuenta bancaria. El saldo acumulado (saldo_inicial + suma
     * cronológica de debe-haber) se calcula con una función de ventana sobre TODO el
     * histórico de la cuenta (sin importar filtros de tipo/fecha mostrados), para que
     * siempre refleje el saldo real del banco en cada línea; los filtros de fecha/tipo
     * se aplican después, como un WHERE externo.
     */
    public function getMovimientos(
        int $idEmpresa,
        int $idFormaPago,
        int $idCuentaContable,
        float $saldoInicial,
        array $filtros,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'fecha_asiento';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        // Cuenta sin cuenta contable (empresa que no lleva contabilidad): el mayor no
        // existe, así que el movimiento se arma desde los cobros/pagos de esa cuenta.
        if ($idCuentaContable <= 0) {
            $cte = "WITH base AS ({$this->conSaldoAcumulado($this->baseTesoreria())})";
            $params = $this->paramsTesoreria($idEmpresa, $idFormaPago) + [':saldo_inicial' => $saldoInicial];
            return $this->paginarBase($cte, $params, $filtros, $page, $perPage, $ordenCol, $dir);
        }

        $cte = "WITH base AS ({$this->conSaldoAcumulado($this->baseContable())})";
        $params = $this->paramsContable($idEmpresa, $idFormaPago, $idCuentaContable) + [':saldo_inicial' => $saldoInicial];

        return $this->paginarBase($cte, $params, $filtros, $page, $perPage, $ordenCol, $dir);
    }

    /**
     * Movimientos de la cuenta desde el mayor contable, sin saldo ni filtros.
     * Placeholders: :id_empresa, :id_forma_pago, :id_cuenta_contable.
     */
    private function baseContable(): string
    {
        return "SELECT
                    ad.id AS id_asiento_detalle,
                    ac.id AS id_asiento,
                    NULL::VARCHAR AS origen_tipo,
                    NULL::INTEGER AS origen_id,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    ad.referencia_detalle,
                    ad.documento_referencia,
                    ad.debe,
                    ad.haber,
                    ad.tipo_entidad,
                    ad.id_entidad,
                    COALESCE(cli.nombre, prov.razon_social, emp.nombres_apellidos,
                             clib.nombre, NULLIF(icb.recibo_de, ''), NULLIF(ecb.beneficiario_nombre, '')) AS nombre_entidad,
                    {$this->sqlBeneficiario('emp')} AS beneficiario_cheque,
                    {$this->selectDerivado()}
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                INNER JOIN empresa_formas_pago fp ON fp.id = :id_forma_pago
                LEFT JOIN clientes cli ON ad.tipo_entidad = 'cliente' AND ad.id_entidad = cli.id
                LEFT JOIN proveedores prov ON ad.tipo_entidad = 'proveedor' AND ad.id_entidad = prov.id
                LEFT JOIN empleados emp ON ad.tipo_entidad = 'empleado' AND ad.id_entidad = emp.id
                {$this->joinsDerivado()}
                WHERE ac.id_empresa = :id_empresa
                  AND ac.estado = 'contabilizado'
                  AND ac.eliminado = FALSE
                  AND ad.eliminado = FALSE
                  AND ad.id_cuenta_contable = :id_cuenta_contable
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  {$this->sqlExcluirOrigenAnulado()}";
    }

    /** Parámetros que espera baseContable(). */
    private function paramsContable(int $idEmpresa, int $idFormaPago, int $idCuentaContable): array
    {
        return [
            ':id_empresa' => $idEmpresa,
            ':id_forma_pago' => $idFormaPago,
            ':id_cuenta_contable' => $idCuentaContable,
        ];
    }

    /**
     * Filtros, búsqueda y paginación sobre el CTE `base` — idéntico para la fuente contable
     * (mayor de la cuenta) y para la de tesorería (cobros/pagos de la cuenta sin contabilidad):
     * ambas exponen las mismas columnas, así que el filtrado no depende del origen.
     */
    private function paginarBase(
        string $cte,
        array $params,
        array $filtros,
        int $page,
        int $perPage,
        string $ordenCol,
        string $dir
    ): array {
        $whereSql = "WHERE 1=1";

        // Un solo movimiento por su anclaje (lo usa el Service al clasificar, para releer del
        // origen los datos que el usuario no puede cambiar desde aquí).
        if (!empty($filtros['id_asiento_detalle'])) {
            $whereSql .= " AND id_asiento_detalle = :f_id_detalle";
            $params[':f_id_detalle'] = (int) $filtros['id_asiento_detalle'];
        }
        if (!empty($filtros['origen_id'])) {
            $whereSql .= " AND origen_tipo = :f_origen_tipo AND origen_id = :f_origen_id";
            $params[':f_origen_tipo'] = (string) $filtros['origen_tipo'];
            $params[':f_origen_id'] = (int) $filtros['origen_id'];
        }

        // El período se mide por la fecha con la que el movimiento pesa en el banco: un cheque
        // cobrado pertenece al mes en que el banco lo hizo efectivo, no al de su emisión. Así el
        // listado y los saldos del período muestran siempre lo mismo.
        if (!empty($filtros['fecha_inicio'])) {
            $whereSql .= " AND fecha_efectiva >= :f_ini";
            $params[':f_ini'] = $filtros['fecha_inicio'];
        }
        if (!empty($filtros['fecha_fin'])) {
            $whereSql .= " AND fecha_efectiva <= :f_fin";
            $params[':f_fin'] = $filtros['fecha_fin'];
        }

        // Flujo: INGRESO = entra al banco (debe); EGRESO = sale (haber). El saldo
        // acumulado se calcula sobre todo el histórico (dentro del CTE), así que
        // este filtro solo recorta las filas mostradas, sin alterar el saldo.
        $flujo = strtoupper((string) ($filtros['flujo'] ?? 'TODOS'));
        if ($flujo === 'INGRESO') {
            $whereSql .= " AND debe > 0";
        } elseif ($flujo === 'EGRESO') {
            $whereSql .= " AND haber > 0";
        }

        // Filtro por tipo de transacción (columna calculada tipo_transaccion, en mayúsculas).
        $tipo = strtoupper((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $whereSql .= " AND tipo_transaccion = :tipo_tx";
            $params[':tipo_tx'] = $tipo;
        }

        // Estado del cheque. "No cobrados" son los que siguen en circulación (sin Fecha Banco),
        // que además son los que no mueven el saldo; "posfechados", los girados con fecha futura.
        switch (strtoupper((string) ($filtros['cheque'] ?? ''))) {
            case 'NO_COBRADOS':
                $whereSql .= " AND tipo_transaccion = 'CHEQUE' AND fecha_banco_manual IS NULL";
                break;
            case 'COBRADOS':
                $whereSql .= " AND tipo_transaccion = 'CHEQUE' AND fecha_banco_manual IS NOT NULL";
                break;
            case 'POSFECHADOS':
                $whereSql .= " AND tipo_transaccion = 'CHEQUE' AND fecha_cheque > CURRENT_DATE";
                break;
        }

        if (!empty($filtros['buscar'])) {
            $parsed = FiltrosBusqueda::parsear($filtros['buscar']);
            // Texto libre: las columnas del listado (incluido el saldo acumulado, que ya
            // viene calculado en el CTE) y la observación de la clasificación. Decisión del
            // usuario (igual que Compras/Ingresos/Egresos): Tipo y la dirección del cheque
            // (recibido/emitido) son clasificaciones y NO entran en el texto libre; se
            // filtran desde el modal de filtros (o el selector Tipo de la tarjeta superior).
            if ($parsed['texto_libre'] !== '') {
                $condicion = FiltrosBusqueda::condicionTexto(
                    [
                        'fecha_asiento::text',                 // Fecha
                        'fecha_banco::text',                   // Fecha Banco
                        'numero_comprobante',                  // Comprobante
                        'numero_cheque',                       // Cheque
                        'fecha_cheque::text',                  // Fecha Cheque
                        'beneficiario_cheque',                 // Beneficiario / Cliente
                        'documento_referencia',                // Documento Ref.
                        'nombre_entidad',                      // Tercero
                        'referencia_detalle',                  // Glosa
                        'concepto',                            // Glosa (cuando la línea no tiene referencia)
                        'debe::text',                          // Debe
                        'haber::text',                         // Haber
                        'ROUND(saldo_acumulado, 2)::text',     // Saldo
                        // Fuera del listado, pero identifica el movimiento:
                        'observacion',
                    ],
                    $parsed['texto_libre'],
                    $params,
                    'tl'
                );
                if ($condicion !== '') {
                    $whereSql .= " AND {$condicion}";
                }
            }
            // tipo: el valor de un select del modal (p. ej. "debito") es un tipo COMPLETO y
            // debe compararse exacto: por ILIKE "debito" también traería NOTA_DEBITO. Lo que
            // no sea un tipo conocido (texto parcial escrito a mano, "tipo:dep") sigue por
            // ILIKE como antes, para no romper URLs guardadas.
            $tiposConocidos = ['DEPOSITO', 'CHEQUE', 'TRANSFERENCIA', 'DEBITO', 'NOTA_DEBITO', 'NOTA_CREDITO', 'TARJETA', 'PAYPHONE', 'OTRO'];
            $fTipo = $parsed['filtros']['tipo'] ?? null;
            if ($fTipo !== null && $fTipo['op'] === 'ILIKE' && !is_array($fTipo['valor'])
                && in_array(strtoupper(trim((string) $fTipo['valor'])), $tiposConocidos, true)) {
                $whereSql .= ($fTipo['neg'] ? ' AND tipo_transaccion <> :f_tipo_exacto' : ' AND tipo_transaccion = :f_tipo_exacto');
                $params[':f_tipo_exacto'] = strtoupper(trim((string) $fTipo['valor']));
                unset($parsed['filtros']['tipo']);
            }
            $mapas = [
                'texto' => [
                    'numero_cheque' => 'numero_cheque',
                    'concepto' => 'concepto',
                    'documento' => 'documento_referencia',
                    'tercero' => 'nombre_entidad',
                    'observacion' => 'observacion',
                    'glosa' => 'referencia_detalle',
                    // tipo/direccion van por ILIKE (no 'exacto'): se guardan en mayúsculas
                    // (DEPOSITO, CHEQUE, EMITIDO...) pero el usuario escribe en minúsculas
                    // en el buscador; ILIKE es case-insensitive, '=' de 'exacto' no lo es.
                    'tipo' => 'tipo_transaccion',
                    'direccion' => 'cheque_direccion',
                    // Claves nuevas del modal de filtros.
                    'comprobante' => 'numero_comprobante',
                    'beneficiario' => 'beneficiario_cheque',
                ],
                'exacto' => [
                    // clasificado:si → el movimiento tiene clasificación/conciliación propia
                    // en control_bancario_movimientos (tipo, cheque o Fecha Banco manual).
                    'clasificado' => "CASE WHEN id_clasificacion IS NULL THEN 'no' ELSE 'si' END",
                ],
                'fecha' => [
                    'fecha' => 'fecha_asiento',
                    'fecha_banco' => 'fecha_banco',
                    'fecha_cheque' => 'fecha_cheque',
                ],
                'numerico' => [
                    'debe' => 'debe',
                    'haber' => 'haber',
                    'saldo' => 'ROUND(saldo_acumulado, 2)',
                ],
            ];
            FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], $mapas);
        }

        // Count
        $sqlCount = "{$cte} SELECT COUNT(*) FROM base {$whereSql}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Rows
        $offset = ($page - 1) * $perPage;
        // Desempate estable: la línea del asiento (fuente contable) o la fila de pago (tesorería).
        $sqlRows = "{$cte} SELECT * FROM base {$whereSql}
                    ORDER BY {$ordenCol} {$dir}, COALESCE(id_asiento_detalle, origen_id) {$dir}
                    LIMIT :limit OFFSET :offset";
        $stRows = $this->db->prepare($sqlRows);
        foreach ($params as $key => $val) {
            $stRows->bindValue($key, $val);
        }
        $stRows->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stRows->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stRows->execute();

        return [
            'total' => $total,
            'rows' => $stRows->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    /**
     * Cheques posfechados (fecha_cheque > hoy), recibidos o emitidos, de todas las
     * cuentas bancarias de la empresa o de una en particular. Salen de los cobros/pagos de
     * Ingresos y Egresos de cada cuenta (misma fuente que el listado del módulo).
     *
     * Con $incluirNoCobrados incluye además los posfechados cuya fecha ya llegó y siguen
     * sin Fecha Banco (listos para cobrar/depositar), sin importar su antigüedad. Sin él
     * (por defecto) el resultado es el de siempre: solo fecha futura.
     */
    public function getChequesPosfechados(int $idEmpresa, ?int $idFormaPago, string $direccion, bool $incluirNoCobrados = false): array
    {
        $rows = [];
        foreach ($this->getIdsFormasBancarias($idEmpresa, $idFormaPago) as $idForma) {
            foreach ($this->getChequesPosfechadosTesoreria($idEmpresa, $idForma, $direccion, $incluirNoCobrados) as $r) {
                $rows[] = $r;
            }
        }
        usort($rows, static fn ($a, $b) => ($a['fecha_cheque'] ?? '') <=> ($b['fecha_cheque'] ?? ''));

        return $rows;
    }

    /** Ids de las cuentas bancarias de la empresa (o solo la indicada). */
    private function getIdsFormasBancarias(int $idEmpresa, ?int $idFormaPago): array
    {
        $sql = "SELECT fp.id FROM empresa_formas_pago fp
                WHERE fp.id_empresa = :id_empresa AND fp.eliminado = FALSE
                  AND fp.id_banco IS NOT NULL";
        $params = [':id_empresa' => $idEmpresa];
        if (!empty($idFormaPago)) {
            $sql .= " AND fp.id = :id_forma_pago";
            $params[':id_forma_pago'] = $idFormaPago;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Condición de "posfechado" sobre las columnas ya derivadas (alias x): fecha futura o,
     * con $incluirNoCobrados, fecha ya cumplida (de cualquier antigüedad) sin Fecha Banco y
     * posterior a la del documento (un cheque al día no es posfechado).
     *
     * Los MIGRADOS del sistema anterior ($exprMigrado) no entran en ese segundo grupo: muchos
     * nunca se conciliaron y el aviso es solo para cheques registrados en este sistema. Los
     * migrados con fecha futura se siguen listando, como siempre.
     */
    private function sqlCondPosfechado(bool $incluirNoCobrados, string $exprMigrado): string
    {
        if (!$incluirNoCobrados) {
            return "x.fecha_cheque > CURRENT_DATE";
        }
        return "(x.fecha_cheque > CURRENT_DATE
                 OR (x.fecha_cheque <= CURRENT_DATE
                     AND x.fecha_banco_manual IS NULL
                     AND x.fecha_cheque > x.fecha_asiento
                     AND NOT ({$exprMigrado})))";
    }

    /**
     * ¿El cobro/pago de una fila de baseTesoreria() (alias x) viene de un ingreso/egreso
     * migrado? Usa el placeholder :id_empresa_mig.
     */
    private function sqlEsMigradoTesoreria(): string
    {
        return "(EXISTS (SELECT 1 FROM ingresos_pagos pm
                          JOIN migracion_mysql_map mm ON mm.id_empresa = :id_empresa_mig
                               AND mm.entidad = 'ingresos' AND mm.id_destino = pm.id_ingreso
                          WHERE x.origen_tipo = 'ingreso' AND pm.id = x.origen_id)
                 OR EXISTS (SELECT 1 FROM egresos_pagos pm
                          JOIN migracion_mysql_map mm ON mm.id_empresa = :id_empresa_mig
                               AND mm.entidad = 'egresos' AND mm.id_destino = pm.id_egreso
                          WHERE x.origen_tipo = 'egreso' AND pm.id = x.origen_id))";
    }

    /** Cheques posfechados de una cuenta sin contabilidad (fuente: cobros/pagos). */
    private function getChequesPosfechadosTesoreria(int $idEmpresa, int $idFormaPago, string $direccion, bool $incluirNoCobrados = false): array
    {
        $direccion = strtoupper($direccion);
        $dirBanco = in_array($direccion, ['EMITIDO', 'EMITIDO_EMPLEADO'], true) ? 'EMITIDO'
                  : ($direccion === 'RECIBIDO' ? 'RECIBIDO' : '');

        $where = "WHERE x.tipo_transaccion = 'CHEQUE' AND {$this->sqlCondPosfechado($incluirNoCobrados, $this->sqlEsMigradoTesoreria())}";
        $params = $this->paramsTesoreria($idEmpresa, $idFormaPago)
                + [':id_forma_fp' => $idFormaPago, ':id_empresa_mig' => $idEmpresa];
        if ($dirBanco !== '') {
            $where .= " AND x.cheque_direccion = :direccion";
            $params[':direccion'] = $dirBanco;
        }
        if ($direccion === 'EMITIDO_EMPLEADO') {
            $where .= " AND x.tipo_entidad = 'empleado'";
        } elseif ($direccion === 'EMITIDO') {
            $where .= " AND (x.tipo_entidad IS DISTINCT FROM 'empleado')";
        }

        $sql = "SELECT x.*,
                       (x.tipo_entidad = 'empleado') AS es_empleado,
                       fp.id AS id_forma_pago,
                       fp.nombre AS forma_pago_nombre,
                       {$this->sqlEsMigradoTesoreria()} AS es_migrado
                FROM ({$this->baseTesoreria()}) x
                INNER JOIN empresa_formas_pago fp ON fp.id = :id_forma_fp
                {$where}
                ORDER BY x.fecha_cheque ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Base común (parametrizada) para las dos consultas de conciliación de cheques emitidos de una cuenta. */
    private function baseChequesEmitidos(): string
    {
        return "SELECT
                    ad.id AS id_asiento_detalle,
                    ac.id AS id_asiento,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    ad.referencia_detalle,
                    ad.documento_referencia,
                    ad.debe,
                    ad.haber,
                    {$this->sqlBeneficiario(null)} AS nombre_entidad,
                    {$this->selectDerivado()}
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                INNER JOIN empresa_formas_pago fp ON fp.id = :id_forma_pago
                LEFT JOIN clientes cli ON ad.tipo_entidad = 'cliente' AND ad.id_entidad = cli.id
                LEFT JOIN proveedores prov ON ad.tipo_entidad = 'proveedor' AND ad.id_entidad = prov.id
                {$this->joinsDerivado()}
                WHERE ac.id_empresa = :id_empresa
                  AND ac.estado = 'contabilizado'
                  AND ac.eliminado = FALSE
                  AND ad.eliminado = FALSE
                  AND ad.id_cuenta_contable = :id_cuenta
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  {$this->sqlExcluirOrigenAnulado()}";
    }

    /**
     * Cheques EMITIDOS durante el período (fecha del asiento dentro del rango) que, al cierre
     * del período (fecha_fin), todavía no tienen fecha de banco registrada o esta es posterior
     * — es decir, "en circulación" / no cobrados por el banco todavía.
     */
    public function getChequesEmitidosNoCobrados(int $idEmpresa, int $idFormaPago, int $idCuentaContable, string $fechaInicio, string $fechaFin): array
    {
        if ($idCuentaContable <= 0) {
            $sql = "SELECT * FROM ({$this->baseTesoreria()}) x
                    WHERE x.tipo_transaccion = 'CHEQUE' AND x.cheque_direccion = 'EMITIDO'
                      AND x.fecha_asiento BETWEEN :f_ini AND :f_fin
                      AND (x.fecha_banco_manual IS NULL OR x.fecha_banco_manual > :f_fin2)
                    ORDER BY x.fecha_asiento ASC";
            $st = $this->db->prepare($sql);
            $st->execute($this->paramsTesoreria($idEmpresa, $idFormaPago) + [
                ':f_ini' => $fechaInicio, ':f_fin' => $fechaFin, ':f_fin2' => $fechaFin,
            ]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $sql = "SELECT * FROM ({$this->baseChequesEmitidos()}) x
                WHERE x.tipo_transaccion = 'CHEQUE' AND x.cheque_direccion = 'EMITIDO'
                  AND x.fecha_asiento BETWEEN :f_ini AND :f_fin
                  AND (x.fecha_banco_manual IS NULL OR x.fecha_banco_manual > :f_fin)
                ORDER BY x.fecha_asiento ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa, ':id_forma_pago' => $idFormaPago, ':id_cuenta' => $idCuentaContable,
            ':f_ini' => $fechaInicio, ':f_fin' => $fechaFin,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Cheques EMITIDOS que el banco hizo efectivos (fecha_banco) dentro del período,
     * sin importar cuándo se emitieron/registraron (pueden venir de un período anterior).
     */
    public function getChequesEmitidosCobradosEnPeriodo(int $idEmpresa, int $idFormaPago, int $idCuentaContable, string $fechaInicio, string $fechaFin): array
    {
        if ($idCuentaContable <= 0) {
            $sql = "SELECT * FROM ({$this->baseTesoreria()}) x
                    WHERE x.tipo_transaccion = 'CHEQUE' AND x.cheque_direccion = 'EMITIDO'
                      AND x.fecha_banco_manual BETWEEN :f_ini AND :f_fin
                    ORDER BY x.fecha_banco_manual ASC";
            $st = $this->db->prepare($sql);
            $st->execute($this->paramsTesoreria($idEmpresa, $idFormaPago) + [
                ':f_ini' => $fechaInicio, ':f_fin' => $fechaFin,
            ]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $sql = "SELECT * FROM ({$this->baseChequesEmitidos()}) x
                WHERE x.tipo_transaccion = 'CHEQUE' AND x.cheque_direccion = 'EMITIDO'
                  AND x.fecha_banco_manual BETWEEN :f_ini AND :f_fin
                ORDER BY x.fecha_banco_manual ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa, ':id_forma_pago' => $idFormaPago, ':id_cuenta' => $idCuentaContable,
            ':f_ini' => $fechaInicio, ':f_fin' => $fechaFin,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getClasificacionPorAsientoDetalle(int $idAsientoDetalle, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM control_bancario_movimientos
                WHERE id_asiento_detalle = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoDetalle, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Clasificación manual anclada a un cobro/pago (cuentas sin contabilidad). */
    public function getClasificacionPorOrigen(string $origenTipo, int $origenId, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM control_bancario_movimientos
                WHERE origen_tipo = :origen_tipo AND origen_id = :origen_id
                  AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':origen_tipo' => $origenTipo, ':origen_id' => $origenId, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Verifica que el cobro/pago exista, sea de la empresa y se haya hecho con esa cuenta
     * bancaria; devuelve su fecha (para el control de período conciliado) o null si no aplica.
     */
    public function getFechaMovimientoTesoreria(string $origenTipo, int $origenId, int $idEmpresa, int $idFormaPago): ?string
    {
        if ($origenTipo === 'ingreso') {
            $sql = "SELECT ic.fecha_emision
                    FROM ingresos_pagos ip
                    INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                    WHERE ip.id = :id AND ic.id_empresa = :id_empresa
                      AND ip.id_forma_cobro = :id_forma_pago AND ic.eliminado = FALSE";
        } elseif ($origenTipo === 'liq_tarj') {
            // Liquidación de tarjetas cerrada depositada en esta cuenta bancaria.
            $sql = "SELECT ct.fecha_conciliacion
                    FROM conciliacion_tarjetas_cabecera ct
                    WHERE ct.id = :id AND ct.id_empresa = :id_empresa
                      AND ct.id_forma_cobro_destino = :id_forma_pago AND ct.eliminado = FALSE
                      AND ct.estado = 'cerrada'";
        } elseif ($origenTipo === 'trasp_in' || $origenTipo === 'trasp_out') {
            // Entrada (cuenta destino) o salida (cuenta origen) de un traspaso de fondos.
            $columna = $origenTipo === 'trasp_in' ? 'id_forma_destino' : 'id_forma_origen';
            $sql = "SELECT tc.fecha_emision
                    FROM traspasos_cabecera tc
                    WHERE tc.id = :id AND tc.id_empresa = :id_empresa
                      AND tc.{$columna} = :id_forma_pago AND tc.eliminado = FALSE";
        } else {
            $sql = "SELECT ec.fecha_emision
                    FROM egresos_pagos ep
                    INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                    WHERE ep.id = :id AND ec.id_empresa = :id_empresa
                      AND ep.id_forma_pago = :id_forma_pago AND ec.eliminado = FALSE
                      AND COALESCE(ep.eliminado, FALSE) = FALSE";
        }
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $origenId, ':id_empresa' => $idEmpresa, ':id_forma_pago' => $idFormaPago]);
        $val = $st->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    public function quitarClasificacionPorOrigen(string $origenTipo, int $origenId, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE control_bancario_movimientos SET
                    eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usuario
                WHERE origen_tipo = :origen_tipo AND origen_id = :origen_id
                  AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':origen_tipo' => $origenTipo, ':origen_id' => $origenId,
            ':id_empresa' => $idEmpresa, ':usuario' => $idUsuario,
        ]);
        return $st->rowCount() > 0;
    }

    /** Verifica que la línea de asiento pertenezca a la empresa y a la cuenta contable de la forma indicada. */
    public function validarAsientoDetalle(int $idAsientoDetalle, int $idEmpresa, int $idCuentaContable): bool
    {
        $sql = "SELECT COUNT(*) FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                WHERE ad.id = :id AND ac.id_empresa = :id_empresa AND ad.id_cuenta_contable = :id_cuenta
                  AND ad.eliminado = FALSE AND ac.eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoDetalle, ':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuentaContable]);
        return (int) $st->fetchColumn() > 0;
    }

    /** Fecha del asiento al que pertenece la línea (para saber si cae en un período ya conciliado). */
    public function getFechaAsientoDeDetalle(int $idAsientoDetalle, int $idEmpresa, int $idCuentaContable): ?string
    {
        $sql = "SELECT ac.fecha_asiento FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                WHERE ad.id = :id AND ac.id_empresa = :id_empresa AND ad.id_cuenta_contable = :id_cuenta
                  AND ad.eliminado = FALSE AND ac.eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoDetalle, ':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuentaContable]);
        $val = $st->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    /**
     * Crea/actualiza la anotación del movimiento. El anclaje es id_asiento_detalle (cuenta con
     * contabilidad) o el par origen_tipo+origen_id del cobro/pago (cuenta sin contabilidad);
     * cada uno tiene su propio índice único, así que el ON CONFLICT cambia según el caso.
     */
    public function upsertClasificacion(array $data): int
    {
        $porOrigen = empty($data['id_asiento_detalle']);
        $conflicto = $porOrigen
            ? '(origen_tipo, origen_id) WHERE origen_tipo IS NOT NULL'
            : '(id_asiento_detalle)';

        $sql = "INSERT INTO control_bancario_movimientos (
                    id_empresa, id_asiento_detalle, origen_tipo, origen_id, id_forma_pago, tipo_transaccion,
                    cheque_direccion, numero_cheque, fecha_cheque, fecha_banco, observacion,
                    created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_asiento_detalle, :origen_tipo, :origen_id, :id_forma_pago, :tipo_transaccion,
                    :cheque_direccion, :numero_cheque, :fecha_cheque, :fecha_banco, :observacion,
                    :usuario, :usuario
                )
                ON CONFLICT {$conflicto} DO UPDATE SET
                    tipo_transaccion = EXCLUDED.tipo_transaccion,
                    cheque_direccion = EXCLUDED.cheque_direccion,
                    numero_cheque = EXCLUDED.numero_cheque,
                    fecha_cheque = EXCLUDED.fecha_cheque,
                    fecha_banco = EXCLUDED.fecha_banco,
                    observacion = EXCLUDED.observacion,
                    updated_by = EXCLUDED.updated_by,
                    updated_at = CURRENT_TIMESTAMP,
                    eliminado = FALSE,
                    deleted_at = NULL,
                    deleted_by = NULL
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_asiento_detalle' => !empty($data['id_asiento_detalle']) ? (int) $data['id_asiento_detalle'] : null,
            ':origen_tipo' => $data['origen_tipo'] ?? null,
            ':origen_id' => !empty($data['origen_id']) ? (int) $data['origen_id'] : null,
            ':id_forma_pago' => $data['id_forma_pago'],
            ':tipo_transaccion' => $data['tipo_transaccion'],
            ':cheque_direccion' => $data['cheque_direccion'] ?? null,
            ':numero_cheque' => $data['numero_cheque'] ?? null,
            ':fecha_cheque' => $data['fecha_cheque'] ?? null,
            ':fecha_banco' => $data['fecha_banco'] ?? null,
            ':observacion' => $data['observacion'] ?? null,
            ':usuario' => $data['usuario_id'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function quitarClasificacion(int $idAsientoDetalle, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE control_bancario_movimientos SET
                    eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usuario
                WHERE id_asiento_detalle = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoDetalle, ':id_empresa' => $idEmpresa, ':usuario' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    public function getSaldoActual(int $idEmpresa, int $idCuentaContable, float $saldoInicial): float
    {
        $sql = "SELECT COALESCE(SUM(ad.debe - ad.haber), 0)
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                WHERE ac.id_empresa = :id_empresa
                  AND ac.estado = 'contabilizado'
                  AND ac.eliminado = FALSE
                  AND ad.eliminado = FALSE
                  AND ad.id_cuenta_contable = :id_cuenta
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  {$this->sqlExcluirOrigenAnulado()}";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuentaContable]);
        return $saldoInicial + (float) $st->fetchColumn();
    }

    /**
     * Años con movimiento para el selector del módulo: los de la contabilidad y, además, los
     * de los cobros/pagos hechos con cuentas bancarias SIN cuenta contable — si no, una empresa
     * que no lleva contabilidad se quedaba solo con el año en curso y no podía mirar hacia atrás.
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        // Años con cobros/pagos en cuentas bancarias (la fuente del módulo son Ingresos/Egresos).
        $sql = "SELECT DISTINCT anio FROM (
                    SELECT extract(year from ic.fecha_emision) AS anio
                    FROM ingresos_pagos ip
                    INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                    INNER JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
                    WHERE ic.id_empresa = :id_empresa_i AND ic.eliminado = FALSE
                      AND fp.id_banco IS NOT NULL
                      AND ic.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb2)

                    UNION

                    SELECT extract(year from ec.fecha_emision) AS anio
                    FROM egresos_pagos ep
                    INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                    INNER JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
                    WHERE ec.id_empresa = :id_empresa_e AND ec.eliminado = FALSE
                      AND COALESCE(ep.eliminado, FALSE) = FALSE
                      AND fp.id_banco IS NOT NULL
                      AND ec.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb3)
                ) a
                WHERE anio IS NOT NULL
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa_i' => $idEmpresa, ':amb2' => $idEmpresa,
            ':id_empresa_e' => $idEmpresa, ':amb3' => $idEmpresa,
        ]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    // ── Conciliaciones (bloqueo de período ya cuadrado con el banco) ─────────

    /** true si [fechaInicio, fechaFin] se solapa con alguna conciliación vigente (no reabierta) de la forma. */
    public function existeSolapamientoConciliacion(int $idFormaPago, string $fechaInicio, string $fechaFin, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM control_bancario_conciliaciones
                WHERE id_forma_pago = :id_forma_pago AND eliminado = FALSE
                  AND fecha_inicio <= :f_fin AND fecha_fin >= :f_ini";
        $params = [':id_forma_pago' => $idFormaPago, ':f_ini' => $fechaInicio, ':f_fin' => $fechaFin];
        if ($excluirId !== null) {
            $sql .= " AND id != :excluir_id";
            $params[':excluir_id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    /** Conciliación vigente (no reabierta) que cubre una fecha puntual, si existe. */
    public function getConciliacionVigentePorFecha(int $idFormaPago, string $fecha): ?array
    {
        $sql = "SELECT * FROM control_bancario_conciliaciones
                WHERE id_forma_pago = :id_forma_pago AND eliminado = FALSE
                  AND :fecha BETWEEN fecha_inicio AND fecha_fin
                ORDER BY fecha_inicio DESC LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_forma_pago' => $idFormaPago, ':fecha' => $fecha]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearConciliacion(array $data): int
    {
        $sql = "INSERT INTO control_bancario_conciliaciones (
                    id_empresa, id_forma_pago, fecha_inicio, fecha_fin,
                    saldo_inicial, saldo_final, saldo_banco, observaciones,
                    created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_forma_pago, :fecha_inicio, :fecha_fin,
                    :saldo_inicial, :saldo_final, :saldo_banco, :observaciones,
                    :usuario, :usuario
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_forma_pago' => $data['id_forma_pago'],
            ':fecha_inicio' => $data['fecha_inicio'],
            ':fecha_fin' => $data['fecha_fin'],
            ':saldo_inicial' => $data['saldo_inicial'],
            ':saldo_final' => $data['saldo_final'],
            ':saldo_banco' => $data['saldo_banco'] ?? null,
            ':observaciones' => $data['observaciones'] ?? null,
            ':usuario' => $data['usuario_id'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function getConciliacionPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM control_bancario_conciliaciones WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function reabrirConciliacion(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE control_bancario_conciliaciones SET
                    eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usuario
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':usuario' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    /** Historial de conciliaciones de una cuenta (vigentes y reabiertas), con el usuario que la creó. */
    public function listarConciliaciones(int $idEmpresa, int $idFormaPago): array
    {
        $sql = "SELECT c.*, u.nombre AS usuario_nombre, ur.nombre AS reabierto_por_nombre
                FROM control_bancario_conciliaciones c
                LEFT JOIN usuarios u ON u.id = c.created_by
                LEFT JOIN usuarios ur ON ur.id = c.deleted_by
                WHERE c.id_empresa = :id_empresa AND c.id_forma_pago = :id_forma_pago
                ORDER BY c.fecha_inicio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_forma_pago' => $idFormaPago]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
