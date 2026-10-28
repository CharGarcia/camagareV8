<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones del centro de costo / proyecto sugerido por periodo de una cuenta.
 * Cada periodo es de un solo tipo (centro o proyecto) y los de un mismo tipo no se cruzan.
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

        $tipo = (string) ($d['tipo'] ?? '');
        if (!in_array($tipo, ['centro', 'proyecto'], true)) {
            throw new \InvalidArgumentException('Tipo no válido.');
        }
        $idDimension = (int) ($d['id_dimension'] ?? 0);

        $out = [
            'tipo'            => $tipo,
            'id_cuenta'       => (int) ($d['id_cuenta'] ?? 0),
            'id_centro_costo' => $tipo === 'centro' && $idDimension > 0 ? $idDimension : null,
            'id_proyecto'     => $tipo === 'proyecto' && $idDimension > 0 ? $idDimension : null,
            'vigente_desde'   => $fecha($d['vigente_desde'] ?? null),
            'vigente_hasta'   => $fecha($d['vigente_hasta'] ?? null),
            'observacion'     => mb_substr(trim((string) ($d['observacion'] ?? '')), 0, 250) ?: null,
        ];

        if ($out['id_cuenta'] <= 0) {
            throw new \InvalidArgumentException('Cuenta no válida.');
        }
        if ($idDimension <= 0) {
            throw new \InvalidArgumentException($tipo === 'centro' ? 'Elija el centro de costo.' : 'Elija el proyecto.');
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

    /**
     * Las líneas de una pestaña (todas del mismo tipo) no pueden cruzarse entre sí.
     * $lineas = [['linea' => n, 'datos' => [vigente_desde, vigente_hasta, …]], …]; fecha NULL = sin límite.
     */
    public function validarSinCruces(array $lineas, string $tipo): void
    {
        $f = static fn($v) => $v ? date('d-m-Y', strtotime((string) $v)) : 'sin límite';
        $n = count($lineas);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $lineas[$i]['datos'];
                $b = $lineas[$j]['datos'];
                $aAntesDeB = $a['vigente_hasta'] !== null && $b['vigente_desde'] !== null && $a['vigente_hasta'] < $b['vigente_desde'];
                $bAntesDeA = $b['vigente_hasta'] !== null && $a['vigente_desde'] !== null && $b['vigente_hasta'] < $a['vigente_desde'];
                if ($aAntesDeB || $bAntesDeA) continue;
                throw new \InvalidArgumentException(sprintf(
                    '%s: las líneas %d (desde %s hasta %s) y %d (desde %s hasta %s) se cruzan. Ajuste las fechas para que no se superpongan.',
                    $tipo === 'centro' ? 'Centro de costos' : 'Proyectos',
                    $lineas[$i]['linea'], $f($a['vigente_desde']), $f($a['vigente_hasta']),
                    $lineas[$j]['linea'], $f($b['vigente_desde']), $f($b['vigente_hasta'])
                ));
            }
        }
    }
}
