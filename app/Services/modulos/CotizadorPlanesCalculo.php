<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Motor de precios del Cotizador de Planes. Clase PURA: no lee la base de datos
 * ni la sesión, solo calcula. La usan el Service (al guardar y al generar la
 * proforma) y el endpoint de cálculo en vivo, así que pantalla, cotización
 * guardada y proforma salen del mismo número. Prueba: tools/verificar_cotizador_planes.php.
 *
 * Reglas (docs/diseno/cotizador-planes-fase1.md §3):
 *  - Ítem 'recurrente': se cobra cada año (precio_anual) o cada mes.
 *    Ítem 'unico': se cobra una sola vez (implementación, migración…).
 *  - Establecimiento principal = el de mayor valor recurrente que entra en el
 *    descuento por establecimiento (módulos «base»); en empate, el primero.
 *  - ANUAL: los demás establecimientos llevan descuento_establecimiento_pct sobre
 *    su valor base; los ítems sin ese descuento (adicionales) van completos.
 *  - MENSUAL: todo a precio completo: anual ÷ 12 × (1 + recargo_mensual_pct).
 *  - Usuarios incluidos = usuarios_por_establecimiento × establecimientos
 *    (compartidos a nivel de RUC); los que pasan se cobran a precio_usuario_adicional_anual.
 *  - Descuento del vendedor: % sobre lo recurrente de cada modalidad. Los
 *    servicios únicos no se descuentan.
 */
final class CotizadorPlanesCalculo
{
    /** Parámetros por defecto (los mismos valores iniciales del SQL). */
    public const PARAMETROS_DEFECTO = [
        'recargo_mensual_pct'            => 20.0,
        'descuento_establecimiento_pct'  => 20.0,
        'usuarios_por_establecimiento'   => 3,
        'precio_usuario_adicional_anual' => 20.0,
        'permitir_descuento_vendedor'    => false,
        'descuento_vendedor_max_pct'     => 0.0,
    ];

    /**
     * @param array $parametros       ver PARAMETROS_DEFECTO
     * @param array $establecimientos [['codigo'=>'001','nombre'=>…,'items'=>[['nombre'=>…,'precio_anual'=>…,
     *                                  'cobro'=>'recurrente'|'unico','aplica_descuento_establecimiento'=>bool], …]], …]
     * @param int   $usuarios         usuarios que necesita el RUC
     * @param float $descuentoVendedorPct  % que da el vendedor (ya validado por las Rules)
     */
    public static function calcular(array $parametros, array $establecimientos, int $usuarios, float $descuentoVendedorPct = 0.0): array
    {
        $p = self::normalizarParametros($parametros);
        $factorMensual = 1 + $p['recargo_mensual_pct'] / 100;
        $descEst = $p['descuento_establecimiento_pct'] / 100;

        // 1) Sumas por establecimiento.
        $filas = [];
        foreach (array_values($establecimientos) as $i => $e) {
            $base = 0.0; $adicional = 0.0; $unico = 0.0;
            foreach (($e['items'] ?? []) as $it) {
                $precio = max(0.0, (float) ($it['precio_anual'] ?? 0));
                if (($it['cobro'] ?? 'recurrente') === 'unico') {
                    $unico = self::r2($unico + $precio);
                } elseif (self::bool($it['aplica_descuento_establecimiento'] ?? true)) {
                    $base = self::r2($base + $precio);
                } else {
                    $adicional = self::r2($adicional + $precio);
                }
            }
            $filas[] = [
                'indice'    => $i,
                'codigo'    => (string) ($e['codigo'] ?? str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)),
                'nombre'    => (string) ($e['nombre'] ?? ''),
                'base'      => $base,
                'adicional' => $adicional,
                'unico'     => $unico,
            ];
        }

        // 2) Principal: mayor valor base; en empate, el primero de la lista.
        $idxPrincipal = null; $mayor = -1.0;
        foreach ($filas as $f) {
            if ($f['base'] > $mayor) { $mayor = $f['base']; $idxPrincipal = $f['indice']; }
        }

        // 3) Anual y mensual por establecimiento.
        $brutoAnual = 0.0; $descuentoEstablecimientos = 0.0; $recurrenteAnual = 0.0;
        $recurrenteMensual = 0.0; $totalUnico = 0.0;
        foreach ($filas as &$f) {
            $f['es_principal'] = ($f['indice'] === $idxPrincipal);
            $f['descuento_establecimiento'] = $f['es_principal'] ? 0.0 : self::r2($f['base'] * $descEst);
            $f['bruto_anual'] = self::r2($f['base'] + $f['adicional']);
            $f['anual']       = self::r2($f['bruto_anual'] - $f['descuento_establecimiento']);
            $f['mensual']     = self::r2($f['bruto_anual'] / 12 * $factorMensual);

            $brutoAnual                = self::r2($brutoAnual + $f['bruto_anual']);
            $descuentoEstablecimientos = self::r2($descuentoEstablecimientos + $f['descuento_establecimiento']);
            $recurrenteAnual           = self::r2($recurrenteAnual + $f['anual']);
            $recurrenteMensual         = self::r2($recurrenteMensual + $f['mensual']);
            $totalUnico                = self::r2($totalUnico + $f['unico']);
        }
        unset($f);

        // 4) Usuarios (compartidos a nivel de RUC).
        $nEst = count($filas);
        $usuarios = max(1, $usuarios);
        $incluidos = $p['usuarios_por_establecimiento'] * $nEst;
        $adicionales = max(0, $usuarios - $incluidos);
        $usuariosAnual   = self::r2($adicionales * $p['precio_usuario_adicional_anual']);
        $usuariosMensual = self::r2($adicionales * $p['precio_usuario_adicional_anual'] / 12 * $factorMensual);

        // 5) Descuento del vendedor y totales.
        $pctV = $p['permitir_descuento_vendedor'] ? max(0.0, min($descuentoVendedorPct, $p['descuento_vendedor_max_pct'])) : 0.0;
        $subAnual   = self::r2($recurrenteAnual + $usuariosAnual);
        $subMensual = self::r2($recurrenteMensual + $usuariosMensual);
        $descVAnual   = self::r2($subAnual * $pctV / 100);
        $descVMensual = self::r2($subMensual * $pctV / 100);
        $totalAnual   = self::r2($subAnual - $descVAnual);
        $totalMensual = self::r2($subMensual - $descVMensual);

        return [
            'parametros'               => $p,
            'establecimientos'         => $filas,
            'bruto_anual'              => $brutoAnual,
            'descuento_establecimientos' => $descuentoEstablecimientos,
            'usuarios'                 => $usuarios,
            'usuarios_incluidos'       => $incluidos,
            'usuarios_adicionales'     => $adicionales,
            'usuarios_anual'           => $usuariosAnual,
            'usuarios_mensual'         => $usuariosMensual,
            'subtotal_anual'           => $subAnual,
            'subtotal_mensual'         => $subMensual,
            'descuento_vendedor_pct'   => $pctV,
            'descuento_vendedor_anual' => $descVAnual,
            'descuento_vendedor_mensual' => $descVMensual,
            'total_anual'              => $totalAnual,
            'total_mensual'            => $totalMensual,
            'doce_mensualidades'       => self::r2($totalMensual * 12),
            'ahorro_anual'             => self::r2($totalMensual * 12 - $totalAnual),
            'total_unico'              => $totalUnico,
        ];
    }

    /** Parámetros con sus tipos y valores por defecto. */
    public static function normalizarParametros(array $p): array
    {
        $d = self::PARAMETROS_DEFECTO;
        return [
            'recargo_mensual_pct'            => max(0.0, (float) ($p['recargo_mensual_pct'] ?? $d['recargo_mensual_pct'])),
            'descuento_establecimiento_pct'  => min(100.0, max(0.0, (float) ($p['descuento_establecimiento_pct'] ?? $d['descuento_establecimiento_pct']))),
            'usuarios_por_establecimiento'   => max(0, (int) ($p['usuarios_por_establecimiento'] ?? $d['usuarios_por_establecimiento'])),
            'precio_usuario_adicional_anual' => max(0.0, (float) ($p['precio_usuario_adicional_anual'] ?? $d['precio_usuario_adicional_anual'])),
            'permitir_descuento_vendedor'    => self::bool($p['permitir_descuento_vendedor'] ?? $d['permitir_descuento_vendedor']),
            'descuento_vendedor_max_pct'     => min(100.0, max(0.0, (float) ($p['descuento_vendedor_max_pct'] ?? $d['descuento_vendedor_max_pct']))),
        ];
    }

    /** Redondeo a 2 decimales, medio hacia arriba, sin arrastrar errores de coma flotante. */
    public static function r2(float $v): float
    {
        return round(round($v, 8), 2);
    }

    private static function bool($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 'true', 't', 'on', 'si', 'sí', 'yes'], true);
    }
}
