<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones del centro de costo / proyecto sugerido por periodo de una cuenta.
 * El cruce con otros periodos de la misma cuenta se revisa en el Service, bajo candado.
 */
class PlanCuentaDimensionRules
{
    /** Normaliza y valida lo que llega del formulario. Devuelve los datos listos para guardar. */
    public function normalizar(array $d): array
    {
        $fecha = static function ($v): ?string {
            $v = trim((string) ($v ?? ''));
            if ($v === '') return null;
            $dt = \DateTime::createFromFormat('Y-m-d', $v);
            if (!$dt || $dt->format('Y-m-d') !== $v) {
                throw new \InvalidArgumentException('Fecha no válida: ' . $v);
            }
            return $v;
        };

        $out = [
            'id_cuenta'       => (int) ($d['id_cuenta'] ?? 0),
            'id_centro_costo' => (int) ($d['id_centro_costo'] ?? 0) ?: null,
            'id_proyecto'     => (int) ($d['id_proyecto'] ?? 0) ?: null,
            'vigente_desde'   => $fecha($d['vigente_desde'] ?? null),
            'vigente_hasta'   => $fecha($d['vigente_hasta'] ?? null),
            'observacion'     => mb_substr(trim((string) ($d['observacion'] ?? '')), 0, 250) ?: null,
        ];

        if ($out['id_cuenta'] <= 0) {
            throw new \InvalidArgumentException('Cuenta no válida.');
        }
        if ($out['id_centro_costo'] === null && $out['id_proyecto'] === null) {
            throw new \InvalidArgumentException('Elija un centro de costo, un proyecto o ambos.');
        }
        if ($out['vigente_desde'] !== null && $out['vigente_hasta'] !== null
            && $out['vigente_hasta'] < $out['vigente_desde']) {
            throw new \InvalidArgumentException('La fecha "Hasta" no puede ser anterior a "Desde".');
        }
        return $out;
    }

    /** La cuenta debe existir en la empresa y ser de movimiento (nivel 5). */
    public function validarCuenta(?array $cuenta): void
    {
        if (!$cuenta) {
            throw new \InvalidArgumentException('Cuenta no encontrada.');
        }
        if ((int) ($cuenta['nivel'] ?? 0) !== 5) {
            throw new \InvalidArgumentException('Solo las cuentas de movimiento (nivel 5) llevan centro de costo o proyecto.');
        }
    }

    /** Mensaje cuando el periodo se cruza con otro ya registrado. */
    public function validarSinCruce(?array $cruce): void
    {
        if ($cruce === null) return;
        $f = static fn($v) => $v ? date('d-m-Y', strtotime((string) $v)) : 'sin límite';
        throw new \InvalidArgumentException(sprintf(
            'El periodo se cruza con otro ya registrado para esta cuenta (desde %s hasta %s). Ajuste las fechas o cierre el periodo anterior.',
            $f($cruce['vigente_desde']),
            $f($cruce['vigente_hasta'])
        ));
    }
}
