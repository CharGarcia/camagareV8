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

echo "11) Plan semestral: anual ÷ 2 × 1,10, sin descuento por establecimiento\n";
$r = C::calcular($P, [['codigo' => '001', 'items' => [$base(1000)]]], 3);
igual('semestral = 1.000 ÷ 2 × 1,10 = 550', 550.0, $r['total_semestral']);
igual('dos semestres = 1.100', 1100.0, $r['dos_semestres']);
igual('ahorro anual vs semestral = 100', 100.0, $r['ahorro_anual_vs_semestral']);
$r = C::calcular($P, [
    ['codigo' => '001', 'items' => [$base(1000)]],
    ['codigo' => '002', 'items' => [$base(1000), $extra(300)]],
], 8);
igual('2 locales: semestral sin 20 % = (1.000 + 1.300) ÷ 2 × 1,10 = 1.265', 1265.0, $r['semestral_sin_usuarios'] ?? round(($r['total_semestral'] - $r['usuarios_semestral']), 2));
igual('usuarios: 2 adicionales × 20 ÷ 2 × 1,10 = 22', 22.0, $r['usuarios_semestral']);
igual('total semestral = 1.287', 1287.0, $r['total_semestral']);
$P4 = ['recargo_semestral_pct' => 0, 'permitir_descuento_vendedor' => true, 'descuento_vendedor_max_pct' => 10] + $P;
$r = C::calcular($P4, [['codigo' => '001', 'items' => [$base(1000)]]], 3, 10);
igual('recargo 0 % y descuento comercial 10 %: 500 − 50 = 450', 450.0, $r['total_semestral']);

echo "12) Paquetes: % del paquete sobre sus módulos recurrentes\n";
$paq = fn(float $v, float $pct, bool $baseOk = true, string $cobro = 'recurrente') => ['nombre' => 'Paq', 'precio_anual' => $v, 'cobro' => $cobro, 'aplica_descuento_establecimiento' => $baseOk, 'de_paquete' => true, 'descuento_paquete_pct' => $pct];
$r = C::calcular($P, [['codigo' => '001', 'items' => [$paq(600, 15), $paq(400, 15), $extra(300)]]], 3);
igual('Emprendedor −15 %: (600 + 400) × 0,85 + 300 suelto = 1.150', 1150.0, $r['total_anual']);
igual('descuento de paquetes = 150', 150.0, $r['descuento_paquetes']);
igual('mensual = 1.150 ÷ 12 × 1,20 = 115', 115.0, $r['total_mensual']);
$r = C::calcular($P, [['codigo' => '001', 'items' => [$paq(1000, 10)]], ['codigo' => '002', 'items' => [$paq(1000, 10)]]], 6);
igual('2 locales con paquete −10 %: 900 + 900 × 0,80 = 1.620', 1620.0, $r['total_anual']);
$r = C::calcular($P, [['codigo' => '001', 'items' => [$paq(100, 50, false, 'unico'), $paq(1000, 0)]]], 3);
igual('el % del paquete no toca los pagos únicos', 100.0, $r['total_unico']);
igual('paquete con 0 % no rebaja nada', 1000.0, $r['total_anual']);
$noPaq = ['nombre' => 'X', 'precio_anual' => 1000, 'cobro' => 'recurrente', 'aplica_descuento_establecimiento' => true, 'de_paquete' => false, 'descuento_paquete_pct' => 15];
$r = C::calcular($P, [['codigo' => '001', 'items' => [$noPaq]]], 3);
igual('un ítem suelto no recibe el % aunque traiga el dato', 1000.0, $r['total_anual']);

echo "13) Módulos incluidos: van a \$0\n";
$inc = fn(float $v) => ['nombre' => 'Clientes', 'precio_anual' => $v, 'cobro' => 'recurrente', 'aplica_descuento_establecimiento' => true, 'incluido' => true];
$r = C::calcular($P, [['codigo' => '001', 'items' => [$base(300), $inc(60), $inc(80)]]], 3);
igual('Facturas 300 con Clientes y Productos incluidos = 300', 300.0, $r['total_anual']);
$r = C::calcular($P, [['codigo' => '001', 'items' => [$base(60)]]], 3);
igual('Clientes solo se cobra: 60', 60.0, $r['total_anual']);

echo "14) Paquete de precio fijo: su precio es el valor base del establecimiento\n";
$fijoItem = fn(float $v, string $cobro = 'recurrente') => ['nombre' => 'M', 'precio_anual' => $v, 'cobro' => $cobro, 'aplica_descuento_establecimiento' => $cobro !== 'unico', 'de_paquete' => true, 'incluido' => $cobro !== 'unico'];
$estFijo = fn(string $cod, array $items) => ['codigo' => $cod, 'paquete_tipo_precio' => 'fijo', 'paquete_precio_anual' => 500, 'items' => $items];
$r = C::calcular($P, [$estFijo('001', [$fijoItem(300), $fijoItem(60), $fijoItem(80), $extra(100)])], 3);
igual('Emprendedor fijo 500 + módulo suelto 100 = 600', 600.0, $r['total_anual']);
igual('paquete_precio del establecimiento = 500', 500.0, $r['establecimientos'][0]['paquete_precio']);
igual('mensual = 600 ÷ 12 × 1,20 = 60', 60.0, $r['total_mensual']);
$r = C::calcular($P, [$estFijo('001', [$fijoItem(300), $fijoItem(150, 'unico')])], 3);
igual('el pago único del paquete fijo se cobra aparte: 150', 150.0, $r['total_unico']);
igual('anual del paquete fijo = 500', 500.0, $r['total_anual']);
$r = C::calcular($P, [$estFijo('001', [$fijoItem(300)]), $estFijo('002', [$fijoItem(300)])], 6);
igual('2 locales con paquete fijo 500: 500 + 500 × 0,80 = 900', 900.0, $r['total_anual']);
$r = C::calcular($P, [['codigo' => '001', 'paquete_tipo_precio' => 'descuento', 'paquete_precio_anual' => 500, 'items' => [$base(1000)]]], 3);
igual('un paquete por descuento ignora el precio fijo guardado', 1000.0, $r['total_anual']);

echo PHP_EOL . ($fallos === 0 ? "TODO OK" : "FALLARON {$fallos} casos") . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
