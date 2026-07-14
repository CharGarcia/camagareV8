<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Aplica la configuración de presentación de ítems de la empresa (pestaña
 * Facturación de modulos/empresa) sobre los detalles de un comprobante.
 *
 * Lo consumen el PDF y el XML del SRI a partir de la MISMA llamada, de modo que
 * la representación impresa y el comprobante electrónico no puedan divergir.
 *
 * Reglas:
 *  - Agrupación (`factura_agrupar_items`): 'no' | 'lote' | 'nup'.
 *    Solo se fusionan líneas del mismo producto que además coinciden en precio
 *    unitario, unidad de medida e impuestos. Así la suma es exacta y el precio
 *    unitario nunca se recalcula (un precio recalculado descuadraría el XML).
 *  - Unidad / lote / NUP se anexan a la DESCRIPCIÓN, no en columnas o nodos
 *    aparte, para que el XML lleve literalmente el mismo texto que el PDF.
 */
class FacturaItemsPresentacionService
{
    public const AGRUPAR_NO   = 'no';
    public const AGRUPAR_LOTE = 'lote';
    public const AGRUPAR_NUP  = 'nup';

    /** Longitud máxima de <descripcion> según el XSD del SRI. */
    private const MAX_DESCRIPCION = 300;

    /**
     * @param array $detalles      Filas de detalle, cada una con clave 'impuestos'
     * @param array $empresaConfig Config de empresa + establecimiento (empresa_config)
     * @return array Detalles listos para imprimir/emitir
     */
    public function preparar(array $detalles, array $empresaConfig): array
    {
        $modo    = $this->modoAgrupacion($empresaConfig);
        $mostrar = [
            'unidad'    => $this->flag($empresaConfig, 'factura_item_mostrar_unidad'),
            'lote'      => $this->flag($empresaConfig, 'factura_item_mostrar_lote'),
            'caducidad' => $this->flag($empresaConfig, 'factura_item_mostrar_caducidad'),
            'nup'       => $this->flag($empresaConfig, 'factura_item_mostrar_nup'),
        ];

        // Sin agrupar y sin nada que mostrar: no hay nada que hacer.
        if ($modo === self::AGRUPAR_NO && !in_array(true, $mostrar, true)) {
            return $detalles;
        }

        $grupos = $this->agrupar($detalles, $modo);

        foreach ($grupos as &$g) {
            $g['descripcion'] = $this->descripcionConEtiquetas($g, $mostrar);
        }
        unset($g);

        return $grupos;
    }

    public function modoAgrupacion(array $empresaConfig): string
    {
        $modo = strtolower(trim((string) ($empresaConfig['factura_agrupar_items'] ?? self::AGRUPAR_NO)));
        return in_array($modo, [self::AGRUPAR_LOTE, self::AGRUPAR_NUP], true) ? $modo : self::AGRUPAR_NO;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Agrupación
    // ─────────────────────────────────────────────────────────────────────────

    private function agrupar(array $detalles, string $modo): array
    {
        $out = [];

        foreach ($detalles as $i => $d) {
            // En modo 'no' cada línea es su propio grupo: así el resto del flujo
            // (etiquetas, valores distintos) es idéntico en los tres modos.
            $clave = $modo === self::AGRUPAR_NO ? 'l' . $i : $this->clave($d, $modo);

            if (!isset($out[$clave])) {
                $d['_lotes']       = $this->valoresDe($d, 'numero_lote');
                $d['_nups']        = $this->valoresDe($d, 'nup');
                $d['_caducidades'] = $this->valoresFecha($d, 'fecha_caducidad');
                $d['_fusionadas']  = 1;
                $out[$clave]       = $d;
                continue;
            }

            $out[$clave] = $this->fusionar($out[$clave], $d);
        }

        return array_values($out);
    }

    /**
     * Clave de fusión. Incluye todo lo que NO puede variar dentro de una línea
     * fusionada; si algo difiere, las líneas quedan separadas.
     */
    private function clave(array $d, string $modo): string
    {
        $discriminante = $modo === self::AGRUPAR_LOTE
            ? $this->norm((string) ($d['numero_lote'] ?? ''))
            : $this->norm((string) ($d['nup'] ?? ''));

        return implode('|', [
            (string) ($d['id_producto'] ?? ''),
            $this->norm((string) ($d['codigo_principal'] ?? '')),
            $this->norm((string) ($d['codigo_auxiliar'] ?? '')),
            $this->norm((string) ($d['descripcion'] ?? '')),
            // Precio unitario: si difiere no se fusiona. Fusionar obligaría a
            // recalcular precio = total/cantidad, que casi nunca cuadra al centavo.
            $this->num((float) ($d['precio_unitario'] ?? 0), 6),
            $this->num((float) ($d['subsidio'] ?? 0), 6),
            // Unidades distintas no son sumables (1 caja + 12 unidades no es 13).
            (string) ($d['id_unidad_medida'] ?? ''),
            $this->firmaImpuestos($d),
            $discriminante,
        ]);
    }

    /** Firma de los impuestos de la línea (IVA, ICE, …) independiente del orden. */
    private function firmaImpuestos(array $d): string
    {
        $partes = [];
        foreach ($d['impuestos'] ?? [] as $imp) {
            $partes[] = ($imp['codigo_impuesto'] ?? '')
                . ':' . ($imp['codigo_porcentaje'] ?? '')
                . ':' . $this->num((float) ($imp['tarifa'] ?? 0), 4);
        }
        sort($partes);
        return implode(';', $partes);
    }

    /**
     * Suma dos líneas del mismo grupo. Como el precio unitario es idéntico,
     * la aritmética cierra exacta:
     *   Σ(cant_i × precio − desc_i) = precio × Σcant − Σdesc
     */
    private function fusionar(array $a, array $b): array
    {
        $a['cantidad']                  = (float) ($a['cantidad'] ?? 0) + (float) ($b['cantidad'] ?? 0);
        $a['descuento']                 = (float) ($a['descuento'] ?? 0) + (float) ($b['descuento'] ?? 0);
        $a['precio_total_sin_impuesto'] = (float) ($a['precio_total_sin_impuesto'] ?? 0)
                                        + (float) ($b['precio_total_sin_impuesto'] ?? 0);

        $a['impuestos'] = $this->fusionarImpuestos($a['impuestos'] ?? [], $b['impuestos'] ?? []);

        // Lote, caducidad y NUP pueden variar dentro del grupo (p. ej. agrupando
        // por lote, el NUP no está en la clave): se conservan todos los distintos.
        $a['_lotes']       = $this->unir($a['_lotes']       ?? [], $this->valoresDe($b, 'numero_lote'));
        $a['_nups']        = $this->unir($a['_nups']        ?? [], $this->valoresDe($b, 'nup'));
        $a['_caducidades'] = $this->unir($a['_caducidades'] ?? [], $this->valoresFecha($b, 'fecha_caducidad'));

        $a['_fusionadas'] = (int) ($a['_fusionadas'] ?? 1) + 1;

        return $a;
    }

    private function fusionarImpuestos(array $a, array $b): array
    {
        foreach ($b as $imp) {
            $k = ($imp['codigo_impuesto'] ?? '')
               . ':' . ($imp['codigo_porcentaje'] ?? '')
               . ':' . $this->num((float) ($imp['tarifa'] ?? 0), 4);

            $encontrado = false;
            foreach ($a as &$dst) {
                $kd = ($dst['codigo_impuesto'] ?? '')
                    . ':' . ($dst['codigo_porcentaje'] ?? '')
                    . ':' . $this->num((float) ($dst['tarifa'] ?? 0), 4);

                if ($kd === $k) {
                    $dst['base_imponible'] = (float) ($dst['base_imponible'] ?? 0) + (float) ($imp['base_imponible'] ?? 0);
                    $dst['valor']          = (float) ($dst['valor'] ?? 0) + (float) ($imp['valor'] ?? 0);
                    $encontrado = true;
                    break;
                }
            }
            unset($dst);

            if (!$encontrado) {
                $a[] = $imp;
            }
        }

        return $a;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Descripción
    // ─────────────────────────────────────────────────────────────────────────

    private function descripcionConEtiquetas(array $g, array $mostrar): string
    {
        $desc   = trim((string) ($g['descripcion'] ?? ''));
        $partes = [];

        if ($mostrar['unidad']) {
            $u = trim((string) ($g['unidad_abreviatura'] ?? $g['unidad_nombre'] ?? ''));
            if ($u !== '') {
                $partes[] = 'Unidad: ' . $u;
            }
        }
        if ($mostrar['lote']) {
            $v = implode(', ', $g['_lotes'] ?? []);
            if ($v !== '') {
                $partes[] = 'Lote: ' . $v;
            }
        }
        if ($mostrar['caducidad']) {
            $v = implode(', ', $g['_caducidades'] ?? []);
            if ($v !== '') {
                $partes[] = 'Caduca: ' . $v;
            }
        }
        if ($mostrar['nup']) {
            $v = implode(', ', $g['_nups'] ?? []);
            if ($v !== '') {
                $partes[] = 'NUP: ' . $v;
            }
        }

        if (!$partes) {
            return $desc;
        }

        return $this->recortar($desc . ' (' . implode(' | ', $partes) . ')', self::MAX_DESCRIPCION);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades
    // ─────────────────────────────────────────────────────────────────────────

    /** @return string[] Valores no vacíos del campo, como lista. */
    private function valoresDe(array $d, string $campo): array
    {
        $v = trim((string) ($d[$campo] ?? ''));
        return $v === '' ? [] : [$v];
    }

    /**
     * Igual que valoresDe(), pero formatea la fecha a d-m-Y. Sin hora: es un DATE
     * y un 00:00:00 fijo solo sería ruido en la descripción del ítem.
     *
     * @return string[]
     */
    private function valoresFecha(array $d, string $campo): array
    {
        $v = trim((string) ($d[$campo] ?? ''));
        if ($v === '' || str_starts_with($v, '0000-00-00')) {
            return [];
        }
        $ts = strtotime($v);
        return [$ts === false ? $v : date('d-m-Y', $ts)];
    }

    /** @return string[] Unión sin duplicados, conservando el orden de aparición. */
    private function unir(array $a, array $b): array
    {
        foreach ($b as $v) {
            if (!in_array($v, $a, true)) {
                $a[] = $v;
            }
        }
        return $a;
    }

    private function flag(array $cfg, string $campo): bool
    {
        $v = $cfg[$campo] ?? false;
        return $v === true || $v === 1 || $v === '1' || $v === 't' || strtolower((string) $v) === 'true';
    }

    private function num(float $v, int $dec): string
    {
        return number_format($v, $dec, '.', '');
    }

    private function norm(string $v): string
    {
        return strtoupper(trim($v));
    }

    /** Recorte seguro en UTF-8 sin depender de la extensión mbstring. */
    private function recortar(string $s, int $max): string
    {
        if ($s === '' || $max <= 0) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max, 'UTF-8');
        }
        $out = preg_replace('/^((?:.){0,' . $max . '}).*$/us', '$1', $s);
        return $out === null ? substr($s, 0, $max) : $out;
    }
}
