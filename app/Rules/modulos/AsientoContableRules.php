<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class AsientoContableRules
{
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
