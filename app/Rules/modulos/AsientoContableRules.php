<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\CuadreDocumentoAsiento;

class AsientoContableRules
{
    /**
     * Cuadre del asiento contra el documento que lo originó.
     *
     * No basta con que Debe = Haber: el asiento de una factura de venta tiene que seguir
     * reflejando el total de esa factura. Según el origen se compara la cartera del asiento
     * (Cuentas por Cobrar / por Pagar, resueltas desde la configuración contable) o el total
     * Debe — ver App\Helpers\CuadreDocumentoAsiento.
     *
     * Devuelve el diagnóstico para que el Service decida qué hacer con él (avisar y dejar
     * confirmar, o bloquear); esta clase no escribe ni corta el flujo.
     *
     * @param array{slots: string[], lado: string, etiqueta: string} $cfg  Definición del cuadre.
     * @param float      $totalDocumento Importe del documento origen.
     * @param array      $detalles       Líneas del asiento tal como se van a guardar.
     * @param int[]      $cuentasCartera Cuentas de cartera de la empresa (vacío si no configuró esos slots).
     * @return array{cuadra: bool, base: string, monto_asiento: float, total_documento: float,
     *               diferencia: float, sin_linea_cartera: bool, etiqueta: string}
     */
    public function evaluarCuadreDocumento(array $cfg, float $totalDocumento, array $detalles, array $cuentasCartera): array
    {
        $lado = ($cfg['lado'] ?? 'debe') === 'haber' ? 'haber' : 'debe';
        $usaCartera = !empty($cfg['slots']) && $cuentasCartera !== [];

        $totalDebe = 0.00;
        $montoCartera = 0.00;
        $hayLineaCartera = false;

        foreach ($detalles as $det) {
            $totalDebe += round((float) ($det['debe'] ?? 0), 2);

            if ($usaCartera && in_array((int) ($det['id_cuenta_contable'] ?? 0), $cuentasCartera, true)) {
                $hayLineaCartera = true;
                $montoCartera += round((float) ($det[$lado] ?? 0), 2);
            }
        }

        // Sin cuenta de cartera configurada (o sin ninguna línea que la use) se compara contra
        // el total Debe, igual que el chequeo de montos de Auditoría Contable.
        $base = $usaCartera && $hayLineaCartera ? 'cartera' : 'total_debe';
        $montoAsiento = round($base === 'cartera' ? $montoCartera : $totalDebe, 2);
        $diferencia = round($totalDocumento - $montoAsiento, 2);

        return [
            'cuadra' => abs($diferencia) <= CuadreDocumentoAsiento::TOLERANCIA,
            'base' => $base,
            'monto_asiento' => $montoAsiento,
            'total_documento' => round($totalDocumento, 2),
            'diferencia' => $diferencia,
            // El asiento debería tener la línea de cartera y no la tiene: se avisa aparte,
            // porque comparar el total Debe en ese caso puede dar «cuadrado» por casualidad.
            'sin_linea_cartera' => $usaCartera && !$hayLineaCartera,
            'etiqueta' => (string) ($cfg['etiqueta'] ?? 'Documento'),
        ];
    }

    /**
     * $data['estado'] === 'borrador' permite guardar un asiento a medio construir (temporal),
     * sin exigir que Debe cuadre con Haber todavía: se puede ir guardando mientras se arma y
     * recién exige el cuadre cuando el usuario lo registra (estado 'contabilizado'). Ver
     * AsientoContableService::guardarAsiento().
     */
    public function validarCabecera(array $data): void
    {
        if (empty($data['fecha_asiento'])) {
            throw new \Exception('La fecha del asiento es obligatoria.');
        }

        if (empty($data['tipo_comprobante'])) {
            throw new \Exception('El tipo de comprobante es obligatorio.');
        }

        if (empty($data['concepto'])) {
            throw new \Exception('El concepto del asiento es obligatorio.');
        }

        $debe = round((float)($data['total_debe'] ?? 0), 2);
        $haber = round((float)($data['total_haber'] ?? 0), 2);
        $esBorrador = ($data['estado'] ?? '') === 'borrador';

        if (!$esBorrador && $debe !== $haber) {
            throw new \Exception('El asiento no está cuadrado. Total Debe (' . $debe . ') no coincide con Total Haber (' . $haber . ').');
        }

        if ($debe <= 0 && $haber <= 0) {
            throw new \Exception('El asiento debe tener valores mayores a 0.');
        }
    }

    public function validarDetalles(array $detalles, bool $esBorrador = false): void
    {
        if (empty($detalles) || !is_array($detalles)) {
            throw new \Exception('El asiento debe contener al menos un detalle de cuenta.');
        }

        $sumaDebe = 0.00;
        $sumaHaber = 0.00;

        foreach ($detalles as $i => $det) {
            $fila = $i + 1;
            if (empty($det['id_cuenta_contable'])) {
                throw new \Exception("La fila {$fila} no tiene una cuenta contable asignada.");
            }

            $debe = round((float)($det['debe'] ?? 0), 2);
            $haber = round((float)($det['haber'] ?? 0), 2);

            if ($debe < 0 || $haber < 0) {
                throw new \Exception("La fila {$fila} tiene valores negativos, lo cual no es permitido en contabilidad.");
            }

            if ($debe == 0 && $haber == 0) {
                throw new \Exception("La fila {$fila} debe tener un valor en el Debe o en el Haber.");
            }

            if ($debe > 0 && $haber > 0) {
                throw new \Exception("La fila {$fila} no puede tener valor en Debe y Haber simultáneamente.");
            }

            $sumaDebe += $debe;
            $sumaHaber += $haber;
        }

        // Validación final de cuadre de detalles sumados (se salta en borrador).
        $sumaDebe = round($sumaDebe, 2);
        $sumaHaber = round($sumaHaber, 2);

        if (!$esBorrador && $sumaDebe !== $sumaHaber) {
            throw new \Exception('La sumatoria de los detalles no cuadra. Total Debe (' . $sumaDebe . ') vs Total Haber (' . $sumaHaber . ').');
        }
    }
}
