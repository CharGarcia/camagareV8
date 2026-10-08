<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class UtilidadesRules
{
    public const TIPOS_PAGO = ['P', 'A', 'RP', 'RA'];

    public function validarCalculo(array $data): void
    {
        $anio = (int) ($data['anio'] ?? 0);
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El ejercicio fiscal no es válido.');
        }
        if ((float) ($data['utilidad_liquida'] ?? 0) < 0) {
            throw new Exception('La utilidad líquida no puede ser negativa.');
        }
        if ((float) ($data['monto_repartir'] ?? 0) <= 0) {
            throw new Exception('Indique el monto a repartir (el 15% de la utilidad líquida del ejercicio).');
        }
    }

    /** Con pagos registrados, el reparto ya no se puede mover: todas las filas dependen entre sí. */
    public function validarRecalculo(array $cabecera, bool $tienePagos): void
    {
        if ($tienePagos) {
            throw new Exception('No se puede recalcular: ya hay pagos (Egresos) registrados sobre estas utilidades. Anule primero esos egresos.');
        }
    }

    public function validarExportacion(array $cabecera): void
    {
        if (!in_array($cabecera['estado'] ?? '', ['calculado', 'contabilizado'], true)) {
            throw new Exception('Calcule las utilidades antes de exportar el archivo.');
        }
    }

    public function validarContabilizacion(array $cabecera): void
    {
        if (!in_array($cabecera['estado'] ?? '', ['calculado', 'contabilizado'], true)) {
            throw new Exception('Calcule las utilidades antes de contabilizarlas.');
        }
        if ((float) ($cabecera['monto_repartir'] ?? 0) <= 0) {
            throw new Exception('No hay monto a repartir que contabilizar.');
        }
    }

    public function validarAnulacion(array $cabecera, bool $tienePagos): void
    {
        if ($tienePagos) {
            throw new Exception('No se puede anular: ya hay pagos (Egresos) registrados sobre estas utilidades.');
        }
    }

    public function validarDetalle(array $campos, bool $tienePagos): void
    {
        if (isset($campos['tipo_pago']) && !in_array($campos['tipo_pago'], self::TIPOS_PAGO, true)) {
            throw new Exception('El tipo de pago no es válido (use P, A, RP o RA).');
        }
        if (isset($campos['valor_retencion']) && (float) $campos['valor_retencion'] < 0) {
            throw new Exception('El valor de retención no puede ser negativo.');
        }
        if (isset($campos['cargas_familiares'])) {
            $c = (int) $campos['cargas_familiares'];
            if ($c < 0 || $c > 20) {
                throw new Exception('El número de cargas familiares no es válido.');
            }
            if ($tienePagos) {
                throw new Exception('No se pueden cambiar las cargas familiares: ya hay pagos registrados y el reparto cambiaría para todos.');
            }
        }
    }
}
