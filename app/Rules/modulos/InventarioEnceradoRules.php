<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de «Encerar existencias» (Movimientos de Inventario). Solo niveles 2 y 3.
 */
class InventarioEnceradoRules
{
    public const NIVEL_MINIMO = 2;

    public static function puedeEncerar(int $nivel): bool
    {
        return $nivel >= self::NIVEL_MINIMO;
    }

    public function validarNivel(int $nivel): void
    {
        if (!self::puedeEncerar($nivel)) {
            throw new Exception('Solo los administradores (nivel 2 y 3) pueden encerar o revertir existencias.');
        }
    }

    /**
     * Fecha de corte, bodegas y observación. Devuelve los datos normalizados:
     * ['fecha_corte' => 'Y-m-d', 'todas_bodegas' => bool, 'ids_bodegas' => int[], 'observacion' => string].
     *
     * @param int[] $idsPermitidos Bodegas que el usuario puede operar.
     */
    public function validarEncerado(array $data, array $idsPermitidos): array
    {
        $errores = [];

        $fecha = trim((string) ($data['fecha_corte'] ?? ''));
        $dt = \DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            $errores[] = 'La fecha de corte no es válida.';
        } elseif ($fecha > date('Y-m-d')) {
            $errores[] = 'La fecha de corte no puede ser posterior a hoy.';
        }

        $todas = !empty($data['todas_bodegas']) && $data['todas_bodegas'] !== '0' && $data['todas_bodegas'] !== 'false';
        $ids = [];
        if (!$todas) {
            $crudos = $data['ids_bodegas'] ?? [];
            if (is_string($crudos)) {
                $crudos = explode(',', $crudos);
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $crudos), fn($v) => $v > 0)));
            if ($ids === []) {
                $errores[] = 'Seleccione al menos una bodega o marque «Todas las bodegas».';
            } elseif (array_diff($ids, array_map('intval', $idsPermitidos)) !== []) {
                $errores[] = 'Alguna de las bodegas seleccionadas no existe o no tiene acceso a ella.';
            }
            sort($ids);
        }

        $obs = trim(preg_replace('/\s+/u', ' ', (string) ($data['observacion'] ?? '')));
        if ($obs === '') {
            $errores[] = 'Escriba el motivo del encerado en la observación.';
        } elseif (mb_strlen($obs) > 300) {
            $errores[] = 'La observación no puede pasar de 300 caracteres.';
        }

        if ($errores !== []) {
            throw new Exception(implode(' ', $errores));
        }

        return [
            'fecha_corte'   => $fecha,
            'todas_bodegas' => $todas,
            'ids_bodegas'   => $ids,
            'observacion'   => $obs,
        ];
    }

    public function validarReversion(array $cab, string $motivo): string
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo));
        if ($motivo === '') {
            throw new Exception('Escriba el motivo de la reversión.');
        }
        if (mb_strlen($motivo) > 300) {
            throw new Exception('El motivo no puede pasar de 300 caracteres.');
        }
        if (($cab['estado'] ?? '') !== 'aplicado') {
            throw new Exception('Este encerado ya fue revertido.');
        }
        return $motivo;
    }
}
