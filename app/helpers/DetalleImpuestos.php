<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Bloque "Detalle de impuestos" de un conjunto de comprobantes de venta, en el
 * orden del RIDE de la factura: subtotal por tarifa, subtotal sin impuestos,
 * cada impuesto y el servicio. El total va aparte.
 *
 * Lo comparten la tirilla del Reporte Restaurante y el correo del cierre de
 * caja, para que los dos digan lo mismo con los mismos renglones.
 *
 * Se agrupa como el RIDE (FacturaVentaPdfService): con tarifa mayor a 0 manda
 * el porcentaje; con tarifa 0 manda el código SRI, porque 0%, No objeto (6) y
 * Exento (7) comparten tarifa y no son lo mismo. Salen solo las líneas con
 * valor —en papel térmico cada renglón cuenta—, salvo el subtotal sin
 * impuestos, que es el punto de partida y siempre se imprime.
 */
final class DetalleImpuestos
{
    /**
     * @param array{subtotal:float, servicio:float, total:float,
     *              impuestos: list<array{codigo_impuesto:string, codigo_porcentaje:string,
     *                                    tarifa:float, base:float, valor:float}>} $resumen
     * @return array{lineas: list<array{etiqueta:string, valor:float}>, total:float}
     */
    public static function armar(array $resumen): array
    {
        $pct = static fn(float $t): string => rtrim(rtrim(number_format($t, 2, '.', ''), '0'), '.') . '%';

        $bases     = []; // etiqueta => [orden, base]
        $impuestos = []; // etiqueta => [orden, valor]
        foreach ($resumen['impuestos'] ?? [] as $i) {
            $tarifa = (float) $i['tarifa'];

            if ($i['codigo_impuesto'] !== '2') {
                // ICE u otro impuesto que no es IVA: sin subtotal propio, su base
                // ya está dentro de la del IVA.
                $etiqueta = match ($i['codigo_impuesto']) {
                    '3'     => 'ICE',
                    '5'     => 'IRBPNR',
                    default => 'Impuesto ' . $i['codigo_impuesto'],
                };
                $impuestos[$etiqueta] = [1000, ($impuestos[$etiqueta][1] ?? 0) + $i['valor']];
                continue;
            }

            if ($tarifa > 0) {
                // De mayor a menor tarifa, como el RIDE: 15% antes que 5%.
                $etiquetaBase = 'Subtotal ' . $pct($tarifa);
                $orden        = -$tarifa;
                $etiquetaIva  = 'IVA ' . $pct($tarifa);
                $impuestos[$etiquetaIva] = [$orden, ($impuestos[$etiquetaIva][1] ?? 0) + $i['valor']];
            } else {
                [$etiquetaBase, $orden] = match ($i['codigo_porcentaje']) {
                    '6'     => ['Subtotal no objeto de IVA', 2],
                    '7'     => ['Subtotal exento de IVA', 3],
                    default => ['Subtotal 0%', 1],
                };
            }
            $bases[$etiquetaBase] = [$orden, ($bases[$etiquetaBase][1] ?? 0) + $i['base']];
        }

        $conValor = static function (array $grupo): array {
            uasort($grupo, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
            $lineas = [];
            foreach ($grupo as $etiqueta => [, $valor]) {
                if (abs($valor) >= 0.005) {
                    $lineas[] = ['etiqueta' => (string) $etiqueta, 'valor' => round($valor, 2)];
                }
            }
            return $lineas;
        };

        $lineas   = $conValor($bases);
        $lineas[] = ['etiqueta' => 'Subtotal sin impuestos', 'valor' => (float) ($resumen['subtotal'] ?? 0)];
        $lineas   = array_merge($lineas, $conValor($impuestos));
        if (abs((float) ($resumen['servicio'] ?? 0)) >= 0.005) {
            // El campo <propina> del comprobante es el recargo por servicio.
            $lineas[] = ['etiqueta' => 'Servicio', 'valor' => (float) $resumen['servicio']];
        }

        return ['lineas' => $lineas, 'total' => (float) ($resumen['total'] ?? 0)];
    }
}
