<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos propio del Flujo de Caja: saldo consolidado y movimientos
 * históricos de TODAS las cuentas de efectivo/banco de la empresa (a
 * diferencia de ControlBancarioRepository, que trabaja una cuenta a la vez).
 * La proyección (CXC/CXP por vencer, roles de pago, cheques posfechados) se
 * arma en FlujoCajaService reusando los repositories de esos módulos.
 */
class FlujoCajaRepository extends BaseRepository
{
    private const AMB = "(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

    public function __construct()
    {
        parent::__construct('empresa_formas_pago');
    }

    /**
     * Cuentas de efectivo/banco de la empresa con cuenta contable asignada
     * (única fuente confiable para identificar qué es "caja/bancos", ver §6
     * de Configuración Contable). Sin id_cuenta_contable, la cuenta no puede
     * participar en el cálculo del flujo de caja.
     */
    public function getCuentasCaja(int $idEmpresa): array
    {
        $sql = "SELECT fp.id, fp.nombre, fp.tipo, fp.id_cuenta_contable
                FROM empresa_formas_pago fp
                WHERE fp.id_empresa = :id_empresa
                  AND fp.eliminado = FALSE
                  AND fp.activo = TRUE
                  AND fp.tipo IN ('BANCO', 'EFECTIVO', 'TARJETA')
                  AND fp.id_cuenta_contable IS NOT NULL
                ORDER BY fp.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Saldo consolidado de todas las cuentas de caja/banco al cierre de una fecha
     * (saldos_iniciales_bancos de esas formas + movimientos contabilizados hasta esa fecha).
     */
    public function getSaldoConsolidadoAFecha(int $idEmpresa, array $idsCuentaContable, array $idsFormaPago, string $fecha): float
    {
        if (empty($idsCuentaContable)) {
            return 0.0;
        }

        $inFormas = implode(',', array_map('intval', $idsFormaPago));
        $sqlSaldoInicial = "SELECT COALESCE(SUM(saldo_inicial), 0) FROM saldos_iniciales_bancos
                             WHERE id_empresa = :id_empresa AND eliminado = FALSE
                               AND id_forma_pago IN ({$inFormas})";
        $st = $this->db->prepare($sqlSaldoInicial);
        $st->execute([':id_empresa' => $idEmpresa]);
        $saldoInicial = (float) $st->fetchColumn();

        $inCuentas = implode(',', array_map('intval', $idsCuentaContable));
        $sqlMov = "SELECT COALESCE(SUM(ad.debe - ad.haber), 0)
                    FROM asientos_contables_detalle ad
                    INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                    WHERE ac.id_empresa = :id_empresa
                      AND ac.estado = 'contabilizado'
                      AND ac.eliminado = FALSE
                      AND ad.eliminado = FALSE
                      AND ad.id_cuenta_contable IN ({$inCuentas})
                      AND ac.fecha_asiento <= :fecha
                      AND ac.tipo_ambiente = " . self::AMB . "
                ";
        $st2 = $this->db->prepare($sqlMov);
        $st2->execute([':id_empresa' => $idEmpresa, ':fecha' => $fecha]);
        $movimientos = (float) $st2->fetchColumn();

        return $saldoInicial + $movimientos;
    }

    /**
     * Movimientos históricos reales (entradas/salidas), consolidando todas las
     * cuentas de caja/banco, agrupados por día/semana/mes dentro del rango.
     */
    public function getMovimientosPorPeriodo(int $idEmpresa, array $idsCuentaContable, string $desde, string $hasta, string $agrupacion): array
    {
        if (empty($idsCuentaContable) || $desde > $hasta) {
            return [];
        }

        $exprPeriodo = match ($agrupacion) {
            'semana' => "TO_CHAR(DATE_TRUNC('week', ac.fecha_asiento), 'YYYY-MM-DD')",
            'mes'    => "TO_CHAR(ac.fecha_asiento, 'YYYY-MM')",
            default  => "TO_CHAR(ac.fecha_asiento, 'YYYY-MM-DD')",
        };

        $inCuentas = implode(',', array_map('intval', $idsCuentaContable));
        $sql = "SELECT {$exprPeriodo} AS periodo,
                       COALESCE(SUM(ad.debe), 0) AS entradas,
                       COALESCE(SUM(ad.haber), 0) AS salidas
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                WHERE ac.id_empresa = :id_empresa
                  AND ac.estado = 'contabilizado'
                  AND ac.eliminado = FALSE
                  AND ad.eliminado = FALSE
                  AND ad.id_cuenta_contable IN ({$inCuentas})
                  AND ac.fecha_asiento BETWEEN :desde AND :hasta
                  AND ac.tipo_ambiente = " . self::AMB . "
                GROUP BY periodo
                ORDER BY periodo ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':desde' => $desde, ':hasta' => $hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
