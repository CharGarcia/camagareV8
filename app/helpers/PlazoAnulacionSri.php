<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Plazo del SRI para anular un comprobante electrónico emitido: hasta el día 7 del mes
 * siguiente a la emisión; si ese día cae en sábado o domingo, el lunes siguiente (no se
 * consideran feriados: no hay calendario oficial en el sistema).
 *
 * Única fuente de verdad del servidor para Facturas de Venta y Retenciones en Compras. La
 * pantalla replica el mismo cálculo (fvFechaLimiteAnulacion / retFechaLimiteAnulacion) solo
 * para avisar antes; quien decide es el servidor.
 */
final class PlazoAnulacionSri
{
    /** Último día permitido para anular, o null si la fecha no se puede leer. */
    public static function fechaLimite(string $fechaEmision): ?\DateTimeImmutable
    {
        $ts = strtotime(substr(trim($fechaEmision), 0, 10));
        if ($ts === false) {
            return null;
        }
        // Desde el día 1 para que «+1 month» no se salte un mes (31-01 + 1 mes = 03-03).
        $mesSiguiente = (new \DateTimeImmutable(date('Y-m-01', $ts)))->modify('+1 month');
        $limite = $mesSiguiente->setDate((int) $mesSiguiente->format('Y'), (int) $mesSiguiente->format('m'), 7);

        $dia = (int) $limite->format('N'); // 6 = sábado, 7 = domingo
        if ($dia === 6) $limite = $limite->modify('+2 days');
        if ($dia === 7) $limite = $limite->modify('+1 day');
        return $limite;
    }

    /**
     * Fecha límite ya vencida (d-m-Y), o null si todavía se está dentro del plazo (o la
     * fecha no se puede leer).
     *
     * @param string|null $hoy Y-m-d; solo para pruebas.
     */
    public static function vencido(string $fechaEmision, ?string $hoy = null): ?string
    {
        $limite = self::fechaLimite($fechaEmision);
        if ($limite === null || ($hoy ?? date('Y-m-d')) <= $limite->format('Y-m-d')) {
            return null;
        }
        return $limite->format('d-m-Y');
    }
}
