<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Orden de listados que se arman mezclando varias consultas.
 *
 * En módulos como Cuentas por Cobrar (facturas + recibos + saldos iniciales) o Cuentas
 * por Pagar (compras + liquidaciones + importaciones + saldos iniciales) las filas se
 * combinan en PHP, así que el `ORDER BY` de cada consulta no manda: el orden final se
 * aplica aquí, sobre el arreglo ya unido. Como la pantalla, el Excel y el PDF parten de
 * ese mismo arreglo, los tres salen con el mismo orden.
 *
 * `$mapa` es la lista blanca de columnas ordenables: clave (la misma que la vista manda
 * en `data-sort`) => `['tipo' => 'texto'|'numero'|'fecha', 'campo' => campo de la fila,
 * 'valor' => callable(array $fila): mixed]`. Sin `campo` ni `valor` se usa la propia
 * clave como campo. Lo que no está en el mapa se ignora y se cae al orden por defecto.
 *
 * El gemelo en el navegador es `public/js/components/orden_tabla.js`: mismas reglas
 * (texto en mayúsculas y sin acentos, vacíos siempre al final, mismos desempates), para
 * que reordenar con un clic dé exactamente la misma lista que traer el orden del
 * servidor. Al cambiar una regla aquí, cambiarla también allá.
 */
final class OrdenFilas
{
    /** Acentos y diacríticos que se neutralizan antes de comparar texto (igual que en JS). */
    private const ACENTOS = [
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'Ñ' => 'N', 'Ç' => 'C', 'Ý' => 'Y',
    ];

    /**
     * Columna y dirección efectivas: valida contra la lista blanca y cae al orden por
     * defecto cuando la columna no existe (p. ej. una preferencia guardada en otro módulo).
     *
     * @param array{0:string,1:string} $defecto [columna, dirección]
     * @return array{0:string,1:string}
     */
    public static function resolver(array $mapa, array $defecto, ?string $col, ?string $dir): array
    {
        $col = trim((string) $col);
        if ($col === '' || !isset($mapa[$col])) {
            return $defecto;
        }
        return [$col, strtoupper(trim((string) $dir)) === 'DESC' ? 'DESC' : 'ASC'];
    }

    /**
     * Ordena las filas por la columna pedida y, a igualdad, por los desempates.
     *
     * @param array<int,array<string,mixed>> $filas
     * @param array{0:string,1:string}       $defecto    [columna, dirección] si no llega una válida
     * @param array<string,string>           $desempates columna => dirección, en orden de prioridad
     * @return array<int,array<string,mixed>>
     */
    public static function aplicar(
        array $filas,
        array $mapa,
        array $defecto,
        ?string $col,
        ?string $dir,
        array $desempates = []
    ): array {
        [$col, $dir] = self::resolver($mapa, $defecto, $col, $dir);

        // Criterios en orden de prioridad: el elegido y luego los desempates que no lo repitan.
        $criterios = [[$col, $dir]];
        foreach ($desempates as $c => $d) {
            if ($c !== $col && isset($mapa[$c])) {
                $criterios[] = [$c, strtoupper($d) === 'DESC' ? 'DESC' : 'ASC'];
            }
        }

        usort($filas, static function (array $a, array $b) use ($criterios, $mapa): int {
            foreach ($criterios as [$c, $d]) {
                $cmp = self::comparar($a, $b, $mapa[$c], $c, $d);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            return 0;
        });

        return $filas;
    }

    /** Compara dos filas por un criterio. Los vacíos van al final en ambas direcciones. */
    private static function comparar(array $a, array $b, array $def, string $clave, string $dir): int
    {
        $x = self::valor($a, $def, $clave);
        $y = self::valor($b, $def, $clave);

        $vacioX = ($x === null || $x === '');
        $vacioY = ($y === null || $y === '');
        if ($vacioX || $vacioY) {
            return $vacioX && $vacioY ? 0 : ($vacioX ? 1 : -1);
        }

        $cmp = ($def['tipo'] ?? 'texto') === 'numero'
            ? ((float) $x <=> (float) $y)
            : strcmp(self::normalizar((string) $x), self::normalizar((string) $y));

        return $dir === 'ASC' ? $cmp : -$cmp;
    }

    /** Valor de la fila para un criterio: `valor` (callable), `campo`, o la propia clave. */
    private static function valor(array $fila, array $def, string $clave): mixed
    {
        if (isset($def['valor']) && is_callable($def['valor'])) {
            return ($def['valor'])($fila);
        }
        return $fila[$def['campo'] ?? $clave] ?? null;
    }

    /** Texto comparable: sin espacios al borde, en mayúsculas y sin acentos. */
    public static function normalizar(string $texto): string
    {
        $texto = trim($texto);
        $texto = function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
        return strtr($texto, self::ACENTOS);
    }
}
