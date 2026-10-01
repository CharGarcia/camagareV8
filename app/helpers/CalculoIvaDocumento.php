<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Autoridad del IVA de los documentos de venta en el SERVIDOR.
 *
 * Facturas, Notas de Crédito, Recibos y Notas de Débito guardaban el IVA tal como lo
 * mandaba quien llamaba (la pantalla, el POS, el Taller, la API móvil…). Cada vez que
 * uno de ellos calculó distinto —línea por línea en una empresa "al subtotal", o con un
 * redondeo de JavaScript que daba 14,71 en vez de 14,72— el error llegó hasta el SRI.
 * Ahora el Service recalcula el IVA aquí, justo antes de validar y guardar, con la
 * configuración de facturación (`calculo_iva_facturacion`) del establecimiento de la
 * SERIE del documento (IvaSubtotal::modoPunto):
 *
 *   - 'linea_linea': IVA de cada línea = round(base × %, 2).
 *   - 'subtotal'   : IVA de cada tarifa = round(Σ bases × %, 2), una sola vez, repartido
 *                    entre sus líneas para que la suma de líneas cuadre exacto.
 *
 * La base IVA de cada línea es su precio_total_sin_impuesto (+ ICE de la línea), a
 * centavos. Solo se tocan los valores de IVA (código 2): bases, ICE, propina y demás
 * quedan como vinieron, y el importe_total se corrige por la diferencia de IVA.
 *
 * Si el total cambia, la última forma de pago absorbe la diferencia cuando es de hasta
 * MAX_AJUSTE_PAGO (redondeo); una diferencia mayor no es redondeo sino datos
 * inconsistentes, y se rechaza.
 */
final class CalculoIvaDocumento
{
    public const MAX_AJUSTE_PAGO = 0.05;

    /**
     * Recalcula el IVA de las líneas de un documento (factura, NC, recibo) y corrige
     * importe_total y la última forma de pago.
     *
     * @param array  $data      Payload del documento: detalles[].impuestos[], importe_total, pagos[].
     * @param string $etiqueta  Nombre del documento para los mensajes ("la factura", …).
     * @return array El mismo payload con el IVA recalculado.
     */
    public static function normalizar(array $data, string $etiqueta = 'el documento'): array
    {
        $detalles = $data['detalles'] ?? null;
        if (!is_array($detalles) || $detalles === []) {
            return $data;
        }

        $modo = IvaSubtotal::modoPunto(
            (int) ($data['id_punto_emision'] ?? 0),
            (int) ($data['id_empresa'] ?? 0),
            is_array($data['empresa_config'] ?? null) ? $data['empresa_config'] : null
        );

        // Línea → posición de su impuesto IVA, base IVA (neto + ICE) y tarifa.
        $lineas = [];
        $ivaAntes = 0.0;
        foreach ($detalles as $k => $d) {
            if (!is_array($d) || empty($d['impuestos']) || !is_array($d['impuestos'])) {
                continue;
            }
            $idxIva = null;
            $ice    = 0.0;
            foreach ($d['impuestos'] as $i => $imp) {
                $cod = (string) ($imp['codigo_impuesto'] ?? '');
                if ($cod === '2' && $idxIva === null) {
                    $idxIva = $i;
                } elseif ($cod === '3') {
                    $ice += (float) ($imp['valor'] ?? 0);
                }
            }
            if ($idxIva === null) {
                continue;
            }
            $imp  = $d['impuestos'][$idxIva];
            $pct  = (float) ($imp['tarifa'] ?? 0);
            $neto = isset($d['precio_total_sin_impuesto'])
                ? (float) $d['precio_total_sin_impuesto']
                : (float) ($imp['base_imponible'] ?? 0);
            $base = round(round($neto, 2) + $ice, 2);

            $ivaAntes += (float) ($imp['valor'] ?? 0);
            $idTar = (int) ($d['id_tarifa_iva'] ?? 0);
            $lineas[$k] = [
                'grupo' => $idTar > 0 ? 'id:' . $idTar : 'cod:' . ($imp['codigo_porcentaje'] ?? '') . ':' . $pct,
                'base'  => $base,
                'pct'   => $pct,
                'idx'   => $idxIva,
            ];
        }
        if ($lineas === []) {
            return $data;
        }

        $ivas = IvaSubtotal::repartir($lineas, $modo);
        $ivaDespues = 0.0;
        foreach ($lineas as $k => $l) {
            $valor = (float) $ivas[$k];
            $data['detalles'][$k]['impuestos'][$l['idx']]['base_imponible'] = $l['base'];
            $data['detalles'][$k]['impuestos'][$l['idx']]['valor']          = $valor;
            $ivaDespues += $valor;
        }

        // Diferencia EXACTA (sin redondear antes): si quien llama mandó valores sin
        // redondear, el total se corrige una sola vez, al final.
        $diferencia = $ivaDespues - $ivaAntes;
        if (abs(round($diferencia, 2)) < 0.005) {
            if (isset($data['importe_total'])) {
                $data['importe_total'] = round((float) $data['importe_total'] + $diferencia, 2);
            }
            return $data;
        }

        $totalAntes = round((float) ($data['importe_total'] ?? 0), 2);
        if (isset($data['importe_total'])) {
            $data['importe_total'] = round((float) $data['importe_total'] + $diferencia, 2);
        }
        return self::ajustarPagos($data, round((float) ($data['importe_total'] ?? 0) - $totalAntes, 2), $etiqueta);
    }

    /**
     * IVA de una Nota de Débito: impuestos a nivel de documento (una tarifa sobre la suma
     * de los motivos), así que el modo no cambia nada; se asegura el redondeo a centavos
     * y que el importe_total cuadre.
     */
    public static function normalizarImpuestosCabecera(array $data, string $etiqueta = 'el documento'): array
    {
        if (empty($data['impuestos']) || !is_array($data['impuestos'])) {
            return $data;
        }
        $diferencia = 0.0;
        foreach ($data['impuestos'] as $i => $imp) {
            if ((string) ($imp['codigo_impuesto'] ?? '') !== '2') {
                continue;
            }
            $base  = round((float) ($imp['base_imponible'] ?? 0), 2);
            $valor = round($base * (float) ($imp['tarifa'] ?? 0) / 100, 2);
            $diferencia += $valor - (float) ($imp['valor'] ?? 0);
            $data['impuestos'][$i]['base_imponible'] = $base;
            $data['impuestos'][$i]['valor']          = $valor;
        }
        if (!isset($data['importe_total'])) {
            return $data;
        }
        // Diferencia exacta: el total se corrige una sola vez, al final.
        $totalAntes = round((float) $data['importe_total'], 2);
        $data['importe_total'] = round((float) $data['importe_total'] + $diferencia, 2);
        $ajuste = round($data['importe_total'] - $totalAntes, 2);
        return abs($ajuste) >= 0.005 ? self::ajustarPagos($data, $ajuste, $etiqueta) : $data;
    }

    /** La última forma de pago absorbe la diferencia de redondeo (hasta MAX_AJUSTE_PAGO). */
    private static function ajustarPagos(array $data, float $diferencia, string $etiqueta): array
    {
        if (abs($diferencia) > self::MAX_AJUSTE_PAGO + 1e-9) {
            throw new \Exception(sprintf(
                'El IVA de %s no coincide con la configuración de facturación del establecimiento '
                . '(diferencia de $%s). Vuelva a abrir el documento para recalcular los totales.',
                $etiqueta,
                number_format(abs($diferencia), 2)
            ));
        }
        if (empty($data['pagos']) || !is_array($data['pagos'])) {
            return $data;
        }
        $claves = array_keys($data['pagos']);
        $ultima = end($claves);
        if (is_array($data['pagos'][$ultima]) && isset($data['pagos'][$ultima]['total'])) {
            $data['pagos'][$ultima]['total'] = round((float) $data['pagos'][$ultima]['total'] + $diferencia, 2);
        }
        return $data;
    }
}
