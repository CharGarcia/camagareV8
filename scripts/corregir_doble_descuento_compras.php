<?php
/**
 * Corrige las compras migradas en las que el descuento se restó dos veces.
 *
 * Las empresas migradas antes del 17-08-2026 quedaron con la base de cada línea, el IVA de la
 * línea y el subtotal de la compra por debajo de lo real en toda compra con descuento (el
 * importe total sí está bien). Este script devuelve el descuento a la base, recalcula el IVA de
 * cada línea, actualiza el subtotal y reconstruye el XML del que sale el PDF. No toca el total,
 * el saldo por pagar, el sustento, el deducible, los pagos ni el asiento.
 *
 * Uso:
 *   php scripts/corregir_doble_descuento_compras.php <id_empresa> [--anio=2026] [--aplicar] [--usuario=ID] [--detalle]
 *
 *   <id_empresa>   Empresa a revisar (una por ejecución).
 *   --anio=AAAA    Año de emisión de las compras. Por defecto 2026.
 *   --aplicar      Escribe los cambios. Sin esta opción solo informa (modo prueba).
 *   --usuario=ID   Usuario que queda en la auditoría. Obligatorio con --aplicar.
 *   --detalle      Lista cada compra por corregir o corregida (las que no cuadran se listan siempre).
 *
 * Se puede repetir: una compra ya corregida deja de cumplir la condición y no se vuelve a tocar.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\MigracionMysql\MigracionMysqlService;

$idEmpresa = 0;
$anio      = 2026;
$aplicar   = false;
$idUsuario = 0;
$detalle   = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--aplicar') { $aplicar = true; }
    elseif ($arg === '--detalle') { $detalle = true; }
    elseif (preg_match('/^--anio=(\d{4})$/', $arg, $m)) { $anio = (int) $m[1]; }
    elseif (preg_match('/^--usuario=(\d+)$/', $arg, $m)) { $idUsuario = (int) $m[1]; }
    elseif (ctype_digit($arg)) { $idEmpresa = (int) $arg; }
    else { fwrite(STDERR, "Opción no reconocida: {$arg}\n"); exit(1); }
}

if ($idEmpresa <= 0) {
    fwrite(STDERR, "Indique la empresa: php scripts/corregir_doble_descuento_compras.php <id_empresa> [--anio=2026] [--aplicar] [--usuario=ID] [--detalle]\n");
    exit(1);
}
if ($aplicar && $idUsuario <= 0) {
    fwrite(STDERR, "Con --aplicar indique el usuario que queda en la auditoría: --usuario=ID\n");
    exit(1);
}

$res = (new MigracionMysqlService())->corregirDobleDescuentoCompras(
    $idEmpresa, "{$anio}-01-01", "{$anio}-12-31", $aplicar, $idUsuario
);

$n = static fn(float $v): string => number_format($v, 2, '.', ',');

echo "== Empresa {$idEmpresa}, compras migradas con descuento del año {$anio}\n";
echo $aplicar ? "   MODO APLICAR: los cambios se escribieron.\n" : "   MODO PRUEBA: no se modificó nada.\n";
echo "   Revisadas ................ {$res['revisadas']}\n";
echo "   Ya correctas ............. {$res['ya_correctas']}\n";
echo "   " . ($aplicar ? "Corregidas ..............." : "Por corregir .............") . ' ' . ($aplicar ? $res['corregidas'] : $res['corregibles']) . "\n";
echo "   No cuadran (sin tocar) ... {$res['no_cuadran']}\n";
if ($res['errores'] > 0) {
    echo "   Errores .................. {$res['errores']}\n";
}
echo "   Subtotal: {$n($res['subtotal_antes'])} -> {$n($res['subtotal_despues'])}\n";
echo "   IVA:      {$n($res['iva_antes'])} -> {$n($res['iva_despues'])}\n";

$filas = array_filter(
    $res['detalle'],
    static fn(array $f): bool => $detalle || in_array($f['estado'], ['no_cuadra', 'error'], true)
);
if ($filas) {
    echo "\n   id | número | fecha | total | subtotal antes -> después | IVA antes -> después | estado\n";
    foreach ($filas as $f) {
        echo "   {$f['id']} | {$f['numero']} | {$f['fecha']} | {$n($f['total'])} | {$n($f['subtotal_antes'])} -> {$n($f['subtotal_despues'])} | "
            . "{$n($f['iva_antes'])} -> {$n($f['iva_despues'])} | {$f['estado']}" . (isset($f['error']) ? " ({$f['error']})" : '') . "\n";
    }
}

exit($res['errores'] > 0 ? 2 : 0);
