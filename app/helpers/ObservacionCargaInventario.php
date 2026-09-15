<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Observación de una carga de inventario, lista para mostrar en el listado.
 *
 * Las cargas que llegaron por la migración desde el sistema anterior guardan en
 * `inventario_cargas.observacion` una línea técnica completa:
 *
 *   Migrado de aprobar inventario (sistema anterior). Archivo: C:\… · Ref: 00123
 *   · Usuario orig: 7 · Aprobó orig: 4
 *
 * (la arma MigracionMysqlService al migrar "aprobar inventario"). De todo eso,
 * lo único que le sirve al usuario para reconocer la carga es la **referencia**:
 * el resto es ruido que ocupa la columna entera y tapa el dato útil.
 *
 * Por eso el listado muestra solo la referencia. Las cargas creadas dentro del
 * sistema llevan la observación que escribió la persona al importar y no tienen
 * ese patrón: esas se muestran tal cual, sin tocar.
 */
final class ObservacionCargaInventario
{
    /**
     * Texto a mostrar en la columna "Observación".
     *
     * - Observación migrada  → solo la referencia ('' si la migración no tenía).
     * - Cualquier otro texto → el texto tal cual.
     */
    public static function paraMostrar(?string $observacion): string
    {
        $texto = trim((string) $observacion);
        if ($texto === '') {
            return '';
        }

        // El separador es " · " (·, U+00B7): la referencia va desde "Ref:" hasta
        // el siguiente separador o el final de la línea.
        if (preg_match('/(?:^|·)\s*Ref\s*:\s*(.*?)\s*(?:·|$)/u', $texto, $m) !== 1) {
            return $texto;
        }

        // La migración escribe "—" cuando el registro de origen no traía referencia.
        $ref = trim($m[1]);
        return ($ref === '' || $ref === '—' || $ref === '-') ? '' : $ref;
    }
}
