<?php

/**
 * Prueba del motor de precios del Cotizador de Planes (CotizadorPlanesCalculo).
 * No necesita base de datos.  Uso:  php tools/verificar_cotizador_planes.php
 * Debe terminar en «TODO OK». Correrla después de tocar cualquier regla de precio.
 */

declare(strict_types=1);

require __DIR__ . '/../app/Services/modulos/CotizadorPlanesCalculo.php';

use App\Services\modulos\CotizadorPlanesCalculo as C;

$fallos = 0;
function igual(string $caso, $esperado, $obtenido): void
{
    global $fallos;
    $ok = is_float($esperado) || is_float($obtenido)
        ? abs((float) $esperado - (float) $obtenido) < 0.0001
        : $esperado === $obtenido;
    echo ($ok ? '  ok   ' : '  FALLA ') . $caso . ($ok ? '' : "  (esperado " . var_export($esperado, true) . ", obtenido " . var_export($obtenido, true) . ")") . PHP_EOL;
    if (!$ok) $fallos++;
}

$P = C::PARAMETROS_DEFECTO; // 20 % recargo, 20 % desc. establecimiento, 3 usuarios, $20 usuario
$base   = fn(float $v, string $n = 'Base') => ['nombre' => $n, 'precio_anual' => $v, 'cobro' => 'recurrente', 'aplica_descuento_establecimiento' => true];
$extra  = fn(float $v, string $n = 'Adicional') => ['nombre' => $n, 'precio_anual' => $v, 'cobro' => 'recurrente', 'aplica_descuento_establecimiento' => false];
$unico  = fn(float $v, string $n = 'Implementación') => ['nombre' => $n, 'precio_anual' => $v, 'cobro' => 'unico', 'aplica_descuento_establecimiento' => true];

echo "1) Un establecimiento, plan de \$1.000 al año (ejemplo de la presentación)\n";
$r = C::calcular($P, [['codigo' => '001', 'items' => [$base(1000)]]], 3);
igual('anual = 1.000', 1000.0, $r['total_anual']);
igual('mensual = 1.000 ÷ 12 × 1,20 = 100', 100.0, $r['total_mensual']);
igual('12 mensualidades = 1.200', 1200.0, $r['doce_mensualidades']);
igual('ahorro pagando anual = 200', 200.0, $r['ahorro_anual']);
igual('es principal', true, $r['establecimientos'][0]['es_principal']);

echo "2) Tres establecimientos de \$1.000 en plan anual: 1.000 + 800 + 800\n";
$r = C::calcular($P, [
    ['codigo' => '001', 'items' => [$base(1000)]],
    ['codigo' => '002', 'items' => [$base(1000)]],
    ['codigo' => '003', 'items' => [$base(1000)]],
], 9);
igual('anual = 2.600', 2600.0, $r['total_anual']);
igual('descuento establecimientos = 400', 400.0, $r['descuento_establecimientos']);
igual('principal = el primero (empate)', true, $r['establecimientos'][0]['es_principal']);
igual('mensual sin descuento: 3 × 100 = 300', 300.0, $r['total_mensual']);
igual('9 usuarios incluidos (3 × 3)', 9, $r['usuarios_incluidos']);
igual('sin usuarios adicionales', 0, $r['usuarios_adicionales']);

echo "3) El principal es el de plan base más caro, no el primero\n";
$r = C::calcular($P, [
    ['codigo' => '001', 'items' => [$base(500)]],
    ['codigo' => '002', 'items' => [$base(1000)]],
], 3);
igual('002 es principal', true, $r['establecimientos'][1]['es_principal']);
igual('001 no es principal', false, $r['establecimientos'][0]['es_principal']);
igual('anual = 1.000 + 500 × 0,80 = 1.400', 1400.0, $r['total_anual']);

echo "4) Módulos adicionales: a precio completo en cada establecimiento\n";
$r = C::calcular($P, [
    ['codigo' => '001', 'items' => [$base(1000)]],
    ['codigo' => '002', 'items' => [$base(1000), $extra(300, 'Restaurante')]],
], 6);
igual('anual = 1.000 + (800 + 300) = 2.100', 2100.0, $r['total_anual']);
igual('el adicional no decide el principal: 001 sigue principal', true, $r['establecimientos'][0]['es_principal']);
igual('mensual = (1.000 + 1.300) ÷ 12 × 1,20 = 230', 230.0, $r['total_mensual']);

echo "5) Usuarios compartidos por RUC: 2 establecimientos = 6 incluidos; 8 piden 2 adicionales\n";
$r = C::calcular($P, [
    ['codigo' => '001', 'items' => [$base(1000)]],
    ['codigo' => '002', 'items' => [$base(1000)]],
], 8);
igual('6 incluidos', 6, $r['usuarios_incluidos']);
igual('2 adicionales', 2, $r['usuarios_adicionales']);
igual('usuarios anual = 2 × 20 = 40', 40.0, $r['usuarios_anual']);
igual('usuarios mensual = 2 × 2 = 4', 4.0, $r['usuarios_mensual']);
igual('anual = 1.000 + 800 + 40 = 1.840', 1840.0, $r['total_anual']);
igual('mensual = 200 + 4 = 204', 204.0, $r['total_mensual']);

echo "6) Servicios únicos: aparte, sin recargo ni descuentos\n";
$r = C::calcular($P, [
    ['codigo' => '001', 'items' => [$base(1000), $unico(0, 'Implementación'), $unico(150, 'Migración')]],
    ['codigo' => '002', 'items' => [$base(1000), $unico(0)]],
], 3);
igual('total único = 150', 150.0, $r['total_unico']);
igual('el único no entra en el anual', 1800.0, $r['total_anual']);

echo "7) Descuento del vendedor: solo si está permitido y hasta el tope\n";
$est = [['codigo' => '001', 'items' => [$base(1000), $unico(100)]]];
$r = C::calcular($P, $est, 3, 10);
igual('no permitido → 0 %', 0.0, $r['descuento_vendedor_pct']);
igual('anual sin descuento', 1000.0, $r['total_anual']);
$P2 = ['permitir_descuento_vendedor' => true, 'descuento_vendedor_max_pct' => 15] + $P;
$r = C::calcular($P2, $est, 3, 10);
igual('permitido 10 % → anual 900', 900.0, $r['total_anual']);
igual('mensual 100 − 10 % = 90', 90.0, $r['total_mensual']);
igual('el único no se descuenta', 100.0, $r['total_unico']);
$r = C::calcular($P2, $est, 3, 40);
igual('pide 40 % con tope 15 % → aplica 15 %', 15.0, $r['descuento_vendedor_pct']);

echo "8) Redondeo: \$99,99 al año → mensual 99,99 ÷ 12 × 1,20 = 9,999 → 10,00\n";
$r = C::calcular($P, [['codigo' => '001', 'items' => [$base(99.99)]]], 1);
igual('mensual = 10,00', 10.0, $r['total_mensual']);

echo "9) Parámetros distintos (recargo 10 %, descuento 25 %, 2 usuarios, \$30 usuario)\n";
$P3 = ['recargo_mensual_pct' => 10, 'descuento_establecimiento_pct' => 25, 'usuarios_por_establecimiento' => 2, 'precio_usuario_adicional_anual' => 30] + $P;
$r = C::calcular($P3, [
    ['codigo' => '001', 'items' => [$base(1200)]],
    ['codigo' => '002', 'items' => [$base(1200)]],
], 5);
igual('anual = 1.200 + 900 + 1 × 30 = 2.130', 2130.0, $r['total_anual']);
igual('mensual = 2.400 ÷ 12 × 1,10 + 30 ÷ 12 × 1,10 = 220 + 2,75', 222.75, $r['total_mensual']);

echo "10) Sin establecimientos (las Rules no dejan guardarla; el motor igual es coherente)\n";
$r = C::calcular($P, [], 3);
igual('0 incluidos → 3 usuarios adicionales', 3, $r['usuarios_adicionales']);
igual('anual = solo usuarios: 3 × 20 = 60', 60.0, $r['total_anual']);
$r = C::calcular($P, [['codigo' => '001', 'items' => []]], 2);
igual('establecimiento sin módulos: anual 0', 0.0, $r['total_anual']);

echo PHP_EOL . ($fallos === 0 ? "TODO OK" : "FALLARON {$fallos} casos") . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
