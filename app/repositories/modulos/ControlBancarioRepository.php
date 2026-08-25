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
        $sql = "SELECT fp.id, fp.nombre, fp.tipo, fp.tipo_cuenta, fp.numero_cuenta,
                       fp.id_cuenta_contable, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                       b.nombre_banco
                FROM empresa_formas_pago fp
                LEFT JOIN plan_cuentas pc ON pc.id = fp.id_cuenta_contable
                LEFT JOIN bancos_ecuador b ON b.id = fp.id_banco
                WHERE fp.id_empresa = :id_empresa
                  AND fp.eliminado = FALSE
                  AND fp.activo = TRUE
                  AND fp.id_banco IS NOT NULL
                ORDER BY fp.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
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
              AND ec.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb_e)";
    }

    /** Parámetros que espera baseTesoreria(). */
    private function paramsTesoreria(int $idEmpresa, int $idFormaPago): array
    {
        return [
            ':id_empresa_i' => $idEmpresa, ':id_forma_i' => $idFormaPago, ':amb_i' => $idEmpresa,
            ':id_empresa_e' => $idEmpresa, ':id_forma_e' => $idFormaPago, ':amb_e' => $idEmpresa,
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
                WHERE UPPER(ac.tipo_comprobante) = 'INGRESOS'
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
            ) ep ON TRUE";
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
                    COALESCE(cli.nombre, prov.razon_social, emp.nombres_apellidos) AS nombre_entidad,
                    COALESCE(NULLIF(ep.beneficiario_cheque, ''), cli.nombre, prov.razon_social, emp.nombres_apellidos) AS beneficiario_cheque,
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
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
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
            if ($parsed['texto_libre'] !== '') {
                $condicion = FiltrosBusqueda::condicionTexto(
                    ['numero_comprobante', 'concepto', 'referencia_detalle', 'documento_referencia', 'nombre_entidad', 'numero_cheque'],
                    $parsed['texto_libre'],
                    $params,
                    'tl'
                );
                if ($condicion !== '') {
                    $whereSql .= " AND {$condicion}";
                }
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
                ],
                'fecha' => [
                    'fecha' => 'fecha_asiento',
                    'fecha_banco' => 'fecha_banco',
                    'fecha_cheque' => 'fecha_cheque',
                ],
                'numerico' => [
                    'debe' => 'debe',
                    'haber' => 'haber',
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
     * cuentas bancarias de la empresa o de una en particular.
     */
    public function getChequesPosfechados(int $idEmpresa, ?int $idFormaPago, string $direccion): array
    {
        $sql = "SELECT
                    ad.id AS id_asiento_detalle,
                    ac.id AS id_asiento,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    ad.referencia_detalle,
                    ad.documento_referencia,
                    ad.debe,
                    ad.haber,
                    COALESCE(NULLIF(ep.beneficiario_cheque, ''), cli.nombre, prov.razon_social, empb.nombres_apellidos) AS nombre_entidad,
                    (egc.id_empleado IS NOT NULL) AS es_empleado,
                    fp.id AS id_forma_pago,
                    fp.nombre AS forma_pago_nombre,
                    {$this->selectDerivado()}
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                INNER JOIN empresa_formas_pago fp ON fp.id_cuenta_contable = ad.id_cuenta_contable
                    AND fp.id_empresa = :id_empresa AND fp.eliminado = FALSE AND fp.id_banco IS NOT NULL
                LEFT JOIN clientes cli ON ad.tipo_entidad = 'cliente' AND ad.id_entidad = cli.id
                LEFT JOIN proveedores prov ON ad.tipo_entidad = 'proveedor' AND ad.id_entidad = prov.id
                {$this->joinsDerivado()}
                LEFT JOIN egresos_cabecera egc ON ep.id_egreso = egc.id AND egc.eliminado = FALSE
                LEFT JOIN empleados empb ON egc.id_empleado = empb.id
                WHERE ac.id_empresa = :id_empresa
                  AND ac.estado = 'contabilizado'
                  AND ac.eliminado = FALSE
                  AND ad.eliminado = FALSE
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $params = [':id_empresa' => $idEmpresa];

        if (!empty($idFormaPago)) {
            $sql .= " AND fp.id = :id_forma_pago";
            $params[':id_forma_pago'] = $idFormaPago;
        }

        // Dirección del cheque en el banco. EMITIDO_EMPLEADO es EMITIDO + beneficiario empleado.
        $direccion = strtoupper($direccion);
        $dirBanco = in_array($direccion, ['EMITIDO', 'EMITIDO_EMPLEADO'], true) ? 'EMITIDO'
                  : ($direccion === 'RECIBIDO' ? 'RECIBIDO' : '');
        if ($dirBanco !== '') {
            $sql .= " AND COALESCE(cbm.cheque_direccion,
                        CASE WHEN ip.id IS NOT NULL THEN 'RECIBIDO' WHEN ep.id IS NOT NULL THEN 'EMITIDO' ELSE NULL END) = :direccion";
            $params[':direccion'] = $dirBanco;
        }

        // Separar emitidos a empleados vs a proveedores/otros.
        $filtroEmpleado = '';
        if ($direccion === 'EMITIDO_EMPLEADO') {
            $filtroEmpleado = ' AND x.es_empleado = TRUE';
        } elseif ($direccion === 'EMITIDO') {
            $filtroEmpleado = ' AND (x.es_empleado = FALSE OR x.es_empleado IS NULL)';
        }

        $sqlFull = "SELECT * FROM ({$sql}) x
                     WHERE x.tipo_transaccion = 'CHEQUE' AND x.fecha_cheque > CURRENT_DATE
                     {$filtroEmpleado}
                     ORDER BY x.fecha_cheque ASC";

        $st = $this->db->prepare($sqlFull);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // El bloque anterior solo alcanza cuentas con cuenta contable (el mayor). Las cuentas
        // sin contabilidad aportan sus cheques desde los cobros/pagos.
        foreach ($this->getFormasSinCuentaContable($idEmpresa, $idFormaPago) as $idForma) {
            foreach ($this->getChequesPosfechadosTesoreria($idEmpresa, $idForma, $direccion) as $r) {
                $rows[] = $r;
            }
        }
        usort($rows, static fn ($a, $b) => ($a['fecha_cheque'] ?? '') <=> ($b['fecha_cheque'] ?? ''));

        return $rows;
    }

    /** Ids de las cuentas bancarias de la empresa que no tienen cuenta contable asignada. */
    private function getFormasSinCuentaContable(int $idEmpresa, ?int $idFormaPago): array
    {
        $sql = "SELECT fp.id FROM empresa_formas_pago fp
                WHERE fp.id_empresa = :id_empresa AND fp.eliminado = FALSE AND fp.activo = TRUE
                  AND fp.id_banco IS NOT NULL AND fp.id_cuenta_contable IS NULL";
        $params = [':id_empresa' => $idEmpresa];
        if (!empty($idFormaPago)) {
            $sql .= " AND fp.id = :id_forma_pago";
            $params[':id_forma_pago'] = $idFormaPago;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** Cheques posfechados de una cuenta sin contabilidad (fuente: cobros/pagos). */
    private function getChequesPosfechadosTesoreria(int $idEmpresa, int $idFormaPago, string $direccion): array
    {
        $direccion = strtoupper($direccion);
        $dirBanco = in_array($direccion, ['EMITIDO', 'EMITIDO_EMPLEADO'], true) ? 'EMITIDO'
                  : ($direccion === 'RECIBIDO' ? 'RECIBIDO' : '');

        $where = "WHERE x.tipo_transaccion = 'CHEQUE' AND x.fecha_cheque > CURRENT_DATE";
        $params = $this->paramsTesoreria($idEmpresa, $idFormaPago) + [':id_forma_fp' => $idFormaPago];
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
                       fp.nombre AS forma_pago_nombre
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
                    COALESCE(cli.nombre, prov.razon_social) AS nombre_entidad,
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
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
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
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
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
        $sql = "SELECT DISTINCT anio FROM (
                    SELECT extract(year from ac.fecha_asiento) AS anio
                    FROM asientos_contables_cabecera ac
                    WHERE ac.id_empresa = :id_empresa AND ac.eliminado = false
                      AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb1)

                    UNION

                    SELECT extract(year from ic.fecha_emision) AS anio
                    FROM ingresos_pagos ip
                    INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                    INNER JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
                    WHERE ic.id_empresa = :id_empresa_i AND ic.eliminado = FALSE
                      AND fp.id_banco IS NOT NULL AND fp.id_cuenta_contable IS NULL
                      AND ic.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb2)

                    UNION

                    SELECT extract(year from ec.fecha_emision) AS anio
                    FROM egresos_pagos ep
                    INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                    INNER JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
                    WHERE ec.id_empresa = :id_empresa_e AND ec.eliminado = FALSE
                      AND COALESCE(ep.eliminado, FALSE) = FALSE
                      AND fp.id_banco IS NOT NULL AND fp.id_cuenta_contable IS NULL
                      AND ec.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :amb3)
                ) a
                WHERE anio IS NOT NULL
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa, ':amb1' => $idEmpresa,
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
