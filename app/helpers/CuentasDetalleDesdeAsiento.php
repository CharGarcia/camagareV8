<?php

namespace App\Helpers;

/**
 * Cuenta contable de las líneas de «otros conceptos» de un ingreso/egreso que no la tienen
 * guardada (documentos migrados o anteriores a la columna id_cuenta_contable del detalle),
 * tomándola del asiento que el documento ya tiene. Solo sirve para MOSTRARLA en el modal: no
 * escribe nada; la línea la persiste cuando el usuario actualiza el documento.
 *
 * Antes solo se cruzaba por `referencia_detalle = descripción` exacta, que falla cuando el
 * asiento agrupa varias líneas en una cuenta (conserva la primera descripción), cuando la
 * referencia lleva más texto, o cuando el asiento viene de la migración (otra glosa). El
 * resultado era un asiento con sus cuentas y un detalle con la columna Cuenta contable vacía.
 */
final class CuentasDetalleDesdeAsiento
{
    /**
     * @param array  $detalles      líneas del documento (por referencia)
     * @param array  $lineasAsiento detalle del asiento (id_cuenta_contable, codigo_cuenta, nombre_cuenta, debe, haber, referencia_detalle)
     * @param string $lado          lado de la contrapartida: 'haber' (ingreso) | 'debe' (egreso)
     * @param string $tipoManual    tipo_documento de la línea manual: 'OTRO' (ingreso) | 'MANUAL' (egreso)
     * @param string $colMonto      'monto_cobrado' | 'monto_pagado'
     */
    public static function aplicar(array &$detalles, array $lineasAsiento, string $lado, string $tipoManual, string $colMonto): void
    {
        $lineas = [];
        foreach ($lineasAsiento as $ad) {
            $monto = round((float) ($ad[$lado] ?? 0), 2);
            $id    = (int) ($ad['id_cuenta_contable'] ?? 0);
            if ($monto <= 0 || $id <= 0) {
                continue;
            }
            $lineas[] = [
                'id'     => $id,
                'codigo' => (string) ($ad['codigo_cuenta'] ?? ''),
                'nombre' => (string) ($ad['nombre_cuenta'] ?? ''),
                'monto'  => $monto,
                'ref'    => self::norm((string) ($ad['referencia_detalle'] ?? '')),
            ];
        }
        if (!$lineas) {
            return;
        }

        $pendientes  = []; // índices de líneas manuales sin cuenta
        $soloManual  = true;
        foreach ($detalles as $i => $d) {
            if (($d['tipo_documento'] ?? '') !== $tipoManual) {
                $soloManual = false;
                continue;
            }
            if (empty($d['id_cuenta_contable'])) {
                $pendientes[] = $i;
            }
        }
        if (!$pendientes) {
            return;
        }

        $poner = static function (int $i, array $l) use (&$detalles): void {
            $detalles[$i]['id_cuenta_contable'] = $l['id'];
            $detalles[$i]['cuenta_codigo']      = $l['codigo'];
            $detalles[$i]['cuenta_nombre']      = $l['nombre'];
        };
        // Una sola cuenta entre las candidatas, o null si hay varias (ambiguo) o ninguna.
        $unica = static function (array $cands): ?array {
            $porCuenta = [];
            foreach ($cands as $l) {
                $porCuenta[$l['id']] = $l;
            }
            return count($porCuenta) === 1 ? reset($porCuenta) : null;
        };

        $resto = [];
        foreach ($pendientes as $i) {
            $desc  = self::norm((string) ($detalles[$i]['descripcion'] ?? ''));
            $monto = round((float) ($detalles[$i][$colMonto] ?? 0), 2);

            // 1. Referencia igual a la descripción (la primera manda, como siempre).
            $l = null;
            if ($desc !== '') {
                foreach ($lineas as $cand) {
                    if ($cand['ref'] === $desc) { $l = $cand; break; }
                }
                // 2. La referencia contiene la descripción ("Ingreso 8 · <descripción>").
                $l ??= $unica(array_filter($lineas, static fn (array $c): bool => $c['ref'] !== '' && mb_strpos($c['ref'], $desc) !== false));
            }
            // 3. Mismo monto en una sola cuenta.
            if ($l === null && $monto > 0) {
                $l = $unica(array_filter($lineas, static fn (array $c): bool => abs($c['monto'] - $monto) < 0.005));
            }
            if ($l !== null) {
                $poner($i, $l);
            } else {
                $resto[] = $i;
            }
        }
        if (!$resto) {
            return;
        }

        // 4. Documento SOLO de otros conceptos: todo ese lado del asiento es su contrapartida.
        //    Con una única cuenta, es la de todas las líneas (el asiento las agrupó); con tantas
        //    líneas de asiento como líneas de detalle, van en el mismo orden. Con documentos de
        //    cartera en el detalle no se infiere: ese lado también lleva la Cuenta por Cobrar/Pagar.
        if (!$soloManual) {
            return;
        }
        $l = $unica($lineas);
        if ($l !== null) {
            foreach ($resto as $i) {
                $poner($i, $l);
            }
            return;
        }
        $manuales = array_keys($detalles);
        if (count($lineas) === count($manuales)) {
            foreach ($manuales as $pos => $i) {
                if (in_array($i, $resto, true)) {
                    $poner($i, $lineas[$pos]);
                }
            }
        }
    }

    private static function norm(string $s): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
    }
}
