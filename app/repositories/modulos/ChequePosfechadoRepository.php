<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Cheques posfechados (recibidos en Ingresos, emitidos en Egresos): cuenta puente configurada y
 * asiento de cobro que se genera al registrar la Fecha Banco en Control Bancario.
 * Ver App\Services\modulos\ChequePosfechadoService.
 */
class ChequePosfechadoRepository extends BaseRepository
{
    public const CODIGO_POR_COBRAR = 'CHEQUESPOSFECHADOSPORCOBRAR';
    public const CODIGO_POR_PAGAR  = 'CHEQUESPOSFECHADOSPORPAGAR';

    /** flujo => [tabla de pagos, FK al documento, columna de la forma, cabecera, tipo de regla de la forma, código de la cuenta puente]. */
    private const FLUJOS = [
        'ingreso' => ['ingresos_pagos', 'id_ingreso', 'id_forma_cobro', 'ingresos_cabecera', 'forma_cobro', self::CODIGO_POR_COBRAR],
        'egreso'  => ['egresos_pagos',  'id_egreso',  'id_forma_pago',  'egresos_cabecera',  'forma_pago',  self::CODIGO_POR_PAGAR],
    ];

    public function __construct()
    {
        parent::__construct('control_bancario_movimientos');
    }

    /**
     * Cuenta puente General configurada en Configuración Contable → Cobros y Pagos, con la fecha
     * desde la que aplica (el día en que se asignó). null = la empresa no la usa.
     *
     * @return array{id_cuenta:int, cuenta_codigo:string, cuenta_nombre:string, desde:string}|null
     */
    public function getCuentaPuente(int $idEmpresa, string $flujo): ?array
    {
        $codigo = self::FLUJOS[$flujo][5] ?? null;
        if ($codigo === null) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                    CAST(ap.created_at AS DATE) AS desde
               FROM asientos_tipo at
               JOIN asientos_programados ap ON ap.id_asiento_tipo = at.id AND ap.id_referencia = at.id
                    AND ap.tipo_referencia IN ('asientos tipo', at.tipo_asiento)
                    AND ap.id_empresa = :e AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
               JOIN plan_cuentas pc ON pc.id = ap.id_cuenta AND pc.eliminado = false
              WHERE at.codigo = :c AND at.eliminado = false
              ORDER BY ap.id
              LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':c' => $codigo]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $r['id_cuenta'] = (int) $r['id_cuenta'];
        return $r;
    }

    /**
     * Anotación de Control Bancario de un cheque de ingreso/egreso, con lo necesario para armar
     * su asiento de cobro: pago, documento, cuenta bancaria de la forma (misma resolución que
     * AsientoBuilderService::lineasFormas) y el asiento de cobro vigente. Incluye anotaciones
     * eliminadas (Fecha Banco quitada): su asiento hay que anularlo.
     */
    public function getMovimiento(int $idEmpresa, int $idMovimiento): ?array
    {
        $st = $this->db->prepare("SELECT origen_tipo FROM control_bancario_movimientos WHERE id = :id AND id_empresa = :e");
        $st->execute([':id' => $idMovimiento, ':e' => $idEmpresa]);
        $flujo = (string) $st->fetchColumn();
        if (!isset(self::FLUJOS[$flujo])) {
            return null;
        }
        [$tPagos, $fkDoc, $colForma, $tDoc, $tipoRef] = self::FLUJOS[$flujo];
        $esEgreso = $flujo === 'egreso';

        $sql = "SELECT cbm.id, cbm.id_empresa, cbm.origen_tipo, cbm.origen_id, cbm.fecha_banco,
                       cbm.eliminado AS movimiento_eliminado, cbm.id_asiento_cobro,
                       p.monto, p.numero_cheque, p.fecha_cobro, p.tipo_operacion_bancaria,
                       " . ($esEgreso ? "p.eliminado AS pago_eliminado, p.estado_cheque," : "false AS pago_eliminado, NULL AS estado_cheque,") . "
                       fp.tipo AS forma_tipo, fp.nombre AS forma_nombre,
                       COALESCE(ap.id_cuenta, fp.id_cuenta_contable) AS id_cuenta_banco,
                       d.id AS id_documento, d.fecha_emision, d.estado AS documento_estado,
                       d.eliminado AS documento_eliminado,
                       " . ($esEgreso
                            ? "d.numero_egreso AS numero, d.tipo_sujeto, d.id_proveedor, d.id_empleado"
                            : "d.numero_ingreso AS numero, d.id_cliente") . "
                  FROM control_bancario_movimientos cbm
                  JOIN {$tPagos} p ON p.id = cbm.origen_id
                  JOIN {$tDoc} d ON d.id = p.{$fkDoc} AND d.id_empresa = cbm.id_empresa
                  JOIN empresa_formas_pago fp ON fp.id = p.{$colForma}
                  LEFT JOIN LATERAL (
                        SELECT a.id_cuenta FROM asientos_programados a
                         WHERE a.id_referencia = fp.id AND a.tipo_referencia = '{$tipoRef}'
                           AND a.id_empresa = cbm.id_empresa AND a.eliminado = false
                         ORDER BY a.id LIMIT 1
                  ) ap ON true
                 WHERE cbm.id = :id AND cbm.id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idMovimiento, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Id de la anotación de Control Bancario de un cobro/pago (viva o eliminada). */
    public function getIdMovimientoPorOrigen(int $idEmpresa, string $origenTipo, int $origenId): ?int
    {
        $st = $this->db->prepare("SELECT id FROM control_bancario_movimientos
                                   WHERE id_empresa = :e AND origen_tipo = :t AND origen_id = :o
                                   ORDER BY eliminado, id DESC LIMIT 1");
        $st->execute([':e' => $idEmpresa, ':t' => $origenTipo, ':o' => $origenId]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function setAsientoCobro(int $idMovimiento, ?int $idAsiento): void
    {
        $st = $this->db->prepare("UPDATE control_bancario_movimientos SET id_asiento_cobro = :a WHERE id = :id");
        $st->execute([':a' => $idAsiento, ':id' => $idMovimiento]);
    }

    /**
     * Anotaciones con asiento de cobro de los cheques de un ingreso/egreso. Las usa la anulación
     * del documento (anular también esos asientos) y el bloqueo de edición.
     *
     * @return array<int, array{id:int, id_asiento_cobro:int}>
     */
    public function getMovimientosConCobro(int $idEmpresa, string $flujo, int $idDocumento): array
    {
        if (!isset(self::FLUJOS[$flujo])) {
            return [];
        }
        [$tPagos, $fkDoc] = self::FLUJOS[$flujo];
        $st = $this->db->prepare(
            "SELECT cbm.id, cbm.id_asiento_cobro
               FROM control_bancario_movimientos cbm
               JOIN {$tPagos} p ON p.id = cbm.origen_id
              WHERE cbm.id_empresa = :e AND cbm.origen_tipo = :f AND p.{$fkDoc} = :d
                AND cbm.id_asiento_cobro IS NOT NULL"
        );
        $st->execute([':e' => $idEmpresa, ':f' => $flujo, ':d' => $idDocumento]);
        return array_map(fn($r) => ['id' => (int) $r['id'], 'id_asiento_cobro' => (int) $r['id_asiento_cobro']],
            $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Cheque recibido (línea de ingresos_pagos) con lo necesario para protestarlo: su ingreso,
     * cuántas formas de cobro con monto tiene ese ingreso y si el banco ya lo cobró.
     * FOR UPDATE: bloquea la línea mientras se protesta (dos protestos simultáneos).
     */
    public function getPagoIngresoParaProtesto(int $idEmpresa, int $idPago): ?array
    {
        $st = $this->db->prepare(
            "SELECT ip.id, ip.id_ingreso, ip.monto, ip.numero_cheque, ip.fecha_cobro, ip.tipo_operacion_bancaria,
                    ip.estado_cheque, fp.tipo AS forma_tipo,
                    ic.estado AS ingreso_estado, ic.eliminado AS ingreso_eliminado, ic.fecha_emision,
                    ic.numero_ingreso,
                    (SELECT COUNT(*) FROM ingresos_pagos o WHERE o.id_ingreso = ic.id AND o.monto > 0) AS formas_con_monto,
                    (SELECT cbm.fecha_banco FROM control_bancario_movimientos cbm
                      WHERE cbm.origen_tipo = 'ingreso' AND cbm.origen_id = ip.id AND cbm.eliminado = false
                      LIMIT 1) AS fecha_banco
               FROM ingresos_pagos ip
               JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso AND ic.id_empresa = :e
               LEFT JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
              WHERE ip.id = :id
                FOR UPDATE OF ip"
        );
        $st->execute([':e' => $idEmpresa, ':id' => $idPago]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function marcarProtestado(int $idPago, string $fecha, string $motivo, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE ingresos_pagos
                SET estado_cheque = 'protestado', fecha_protesto = :f, motivo_protesto = :m,
                    protestado_at = CURRENT_TIMESTAMP, protestado_by = :u
              WHERE id = :id"
        );
        $st->execute([':f' => $fecha, ':m' => mb_substr($motivo, 0, 500), ':u' => $idUsuario, ':id' => $idPago]);
    }

    /**
     * Condición SQL (booleana, sin placeholders): esta línea de pago es un cheque posfechado que
     * su ingreso/egreso contabilizó en la cuenta puente y no en Bancos. Espejo en SQL de
     * ChequePosfechadoService::aplicaPuente() + getCuentaPuente(); la usan el sincronizador y la
     * Comprobación contable de Control Bancario.
     *
     * @param string $p alias de ingresos_pagos / egresos_pagos
     * @param string $d alias de ingresos_cabecera / egresos_cabecera
     */
    public static function sqlVaACuentaPuente(string $flujo, string $p, string $d): string
    {
        [, , $colForma, , , $codigo] = self::FLUJOS[$flujo];
        return "(COALESCE(UPPER(NULLIF(TRIM({$p}.tipo_operacion_bancaria), '')),
                          (SELECT CASE WHEN UPPER(fz.tipo) = 'CHEQUE' THEN 'CHEQUE' END
                             FROM empresa_formas_pago fz WHERE fz.id = {$p}.{$colForma})) = 'CHEQUE'
                 AND {$p}.fecha_cobro > {$d}.fecha_emision
                 AND COALESCE({$d}.fecha_emision >= (
                        SELECT CAST(apz.created_at AS DATE)
                          FROM asientos_tipo atz
                          JOIN asientos_programados apz ON apz.id_asiento_tipo = atz.id AND apz.id_referencia = atz.id
                               AND apz.tipo_referencia IN ('asientos tipo', atz.tipo_asiento)
                               AND apz.id_empresa = {$d}.id_empresa AND apz.eliminado = false AND apz.id_cuenta IS NOT NULL
                          JOIN plan_cuentas pcz ON pcz.id = apz.id_cuenta AND pcz.eliminado = false
                         WHERE atz.codigo = '{$codigo}' AND atz.eliminado = false
                         ORDER BY apz.id LIMIT 1), false))";
    }

    /**
     * SQL del sincronizador de asientos: anotaciones de cheques posfechados YA cobrados (Fecha
     * Banco) cuyo ingreso/egreso usa la cuenta puente y que aún no tienen asiento de cobro.
     * Un solo placeholder posicional por flujo (la empresa), para el formato de
     * SincronizadorAsientosService (params = [empresa, empresa]).
     */
    public static function sqlPendientesDeCobro(): string
    {
        $partes = [];
        foreach (self::FLUJOS as $flujo => [$tPagos, $fkDoc, , $tDoc]) {
            $vigente = $flujo === 'egreso'
                ? " AND p.eliminado = false AND COALESCE(p.estado_cheque, 'vigente') <> 'anulado'"
                : '';
            $partes[] = "SELECT cbm.id
                  FROM control_bancario_movimientos cbm
                  JOIN {$tPagos} p ON p.id = cbm.origen_id
                  JOIN {$tDoc} d ON d.id = p.{$fkDoc} AND d.id_empresa = cbm.id_empresa
                 WHERE cbm.id_empresa = ? AND cbm.origen_tipo = '{$flujo}'
                   AND cbm.eliminado = false AND cbm.fecha_banco IS NOT NULL AND cbm.id_asiento_cobro IS NULL
                   AND d.eliminado = false AND UPPER(TRIM(COALESCE(d.estado, ''))) <> 'ANULADO'{$vigente}
                   AND " . self::sqlVaACuentaPuente($flujo, 'p', 'd');
        }
        return implode("\nUNION ALL\n", $partes);
    }
}
