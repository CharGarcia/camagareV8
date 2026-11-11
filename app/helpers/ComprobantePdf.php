<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Comprobantes electrónicos leídos desde su PDF (RIDE) — Descargas del SRI,
 * pestaña «Desde PDF».
 *
 * Catálogos y normalización de la estructura intermedia ("doc") que viaja entre
 * la lectura del PDF, la pantalla de revisión y el armado del XML. Un solo lugar
 * para que el lector, las Rules y el armado del XML hablen el mismo formato:
 *
 *   cod_doc, clave_acceso, numero_autorizacion, fecha_autorizacion,
 *   emisor{ruc, razon_social, nombre_comercial, dir_matriz, dir_establecimiento, obligado_contabilidad},
 *   estab, pto_emi, secuencial, fecha_emision (d/m/Y),
 *   receptor{identificacion, razon_social, direccion},
 *   doc_modificado{cod_doc, numero, fecha, motivo}      (notas de crédito y débito)
 *   periodo_fiscal (mm/aaaa)                            (retenciones)
 *   detalles[{codigo, codigo_auxiliar, descripcion, cantidad, precio_unitario, descuento, subtotal, cod_iva}]
 *   motivos[{razon, valor}]                             (notas de débito)
 *   impuestos[{cod_iva, base, valor}]                   (IVA del total, por tarifa)
 *   totales{subtotal, descuento, ice, propina, total}
 *   pagos[{forma_pago, total, plazo, unidad_tiempo}]
 *   retenciones[{impuesto, codigo_retencion, base, porcentaje, valor, cod_doc_sustento, num_doc_sustento, fecha_doc_sustento}]
 *   info_adicional[{nombre, valor}]
 */
final class ComprobantePdf
{
    /** Tipos de comprobante que el sistema registra (codDoc del SRI => nombre). */
    public const TIPOS = [
        '01' => 'Factura',
        '03' => 'Liquidación de compra',
        '04' => 'Nota de crédito',
        '05' => 'Nota de débito',
        '07' => 'Comprobante de retención',
    ];

    /** codigoPorcentaje de IVA del SRI => [etiqueta, tarifa %]. */
    public const IVA = [
        '4'  => ['15%', 15.0],
        '0'  => ['0%', 0.0],
        '5'  => ['5%', 5.0],
        '8'  => ['8%', 8.0],
        '2'  => ['12%', 12.0],
        '10' => ['13%', 13.0],
        '3'  => ['14%', 14.0],
        '6'  => ['No objeto de IVA', 0.0],
        '7'  => ['Exento de IVA', 0.0],
    ];

    /** Formas de pago del SRI (tabla 24). */
    public const FORMAS_PAGO = [
        '01' => 'Sin utilización del sistema financiero',
        '15' => 'Compensación de deudas',
        '16' => 'Tarjeta de débito',
        '17' => 'Dinero electrónico',
        '18' => 'Tarjeta prepago',
        '19' => 'Tarjeta de crédito',
        '20' => 'Otros con utilización del sistema financiero',
        '21' => 'Endoso de títulos',
    ];

    /** Impuestos de una retención => código del XML del SRI. */
    public const IMPUESTOS_RETENCION = ['RENTA' => '1', 'IVA' => '2', 'ISD' => '6'];

    /** Documentos de sustento habituales de una retención / documento modificado. */
    public const DOCS_SUSTENTO = [
        '01' => 'Factura',
        '03' => 'Liquidación de compra',
        '04' => 'Nota de crédito',
        '05' => 'Nota de débito',
        '02' => 'Nota de venta',
        '12' => 'Documento de institución financiera',
        '41' => 'Comprobante de reembolso',
        '00' => 'Sin documento',
    ];

    public const MAX_LINEAS = 300;

    // ── Clave de acceso ──────────────────────────────────────────────────────

    /** ¿49 dígitos con el dígito verificador (módulo 11) correcto? */
    public static function claveValida(string $clave): bool
    {
        if (!preg_match('/^\d{49}$/', $clave)) {
            return false;
        }
        $suma = 0;
        $factor = 2;
        for ($i = 47; $i >= 0; $i--) {
            $suma += (int) $clave[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $dv = 11 - ($suma % 11);
        $dv = $dv === 11 ? 0 : ($dv === 10 ? 1 : $dv);
        return $dv === (int) $clave[48];
    }

    /**
     * Datos codificados en la clave de acceso (no requiere consultar al SRI).
     * @return array{fecha:string, cod_doc:string, ruc:string, ambiente:string, estab:string, pto_emi:string, secuencial:string}|null
     */
    public static function desglosarClave(string $clave): ?array
    {
        if (!preg_match('/^\d{49}$/', $clave)) {
            return null;
        }
        return [
            'fecha'      => substr($clave, 0, 2) . '/' . substr($clave, 2, 2) . '/' . substr($clave, 4, 4),
            'cod_doc'    => substr($clave, 8, 2),
            'ruc'        => substr($clave, 10, 13),
            'ambiente'   => substr($clave, 23, 1),
            'estab'      => substr($clave, 24, 3),
            'pto_emi'    => substr($clave, 27, 3),
            'secuencial' => substr($clave, 30, 9),
        ];
    }

    // ── Utilidades de formato ────────────────────────────────────────────────

    public static function digitos($v): string
    {
        return (string) preg_replace('/\D+/', '', (string) $v);
    }

    public static function texto($v, int $max = 300): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
        return mb_substr($t, 0, $max);
    }

    /** Número con punto decimal; acepta "1.234,56", "1,234.56" y "1234,56". */
    public static function numero($v, int $decimales = 2): float
    {
        if (is_int($v) || is_float($v)) {
            return round((float) $v, $decimales);
        }
        $s = (string) preg_replace('/[^\d,.\-]/', '', (string) $v);
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        $coma = strrpos($s, ',');
        $punto = strrpos($s, '.');
        if ($coma !== false && $punto !== false) {
            // El separador que aparece al final es el decimal.
            $s = $coma > $punto
                ? str_replace(',', '.', str_replace('.', '', $s))
                : str_replace(',', '', $s);
        } elseif ($coma !== false) {
            $s = str_replace(',', '.', $s);
        }
        return round((float) $s, $decimales);
    }

    /** Fecha a d/m/Y (formato del XML del SRI). Cadena vacía si no se reconoce. */
    public static function fecha($v): string
    {
        $s = trim((string) $v);
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})#', $s, $m)) {
            [$d, $mes, $a] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})#', $s, $m)) {
            [$a, $mes, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return '';
        }
        return checkdate($mes, $d, $a) ? sprintf('%02d/%02d/%04d', $d, $mes, $a) : '';
    }

    /** Fecha y hora a d/m/Y H:i:s; si solo trae fecha, a las 00:00:00. */
    public static function fechaHora($v): string
    {
        $s = trim((string) $v);
        $f = self::fecha($s);
        if ($f === '') {
            return '';
        }
        $hora = preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $s, $m)
            ? sprintf('%02d:%02d:%02d', min(23, (int) $m[1]), min(59, (int) $m[2]), min(59, (int) ($m[3] ?? 0)))
            : '00:00:00';
        return $f . ' ' . $hora;
    }

    /** Número de documento a 001-001-000000001. Cadena vacía si no tiene esa forma. */
    public static function numeroDocumento($v): string
    {
        $s = trim((string) $v);
        if (preg_match('/^(\d{1,3})\D+(\d{1,3})\D+(\d{1,9})$/', $s, $m)) {
            return sprintf('%03d-%03d-%09d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        $d = self::digitos($s);
        if (strlen($d) === 15) {
            return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6, 9);
        }
        return '';
    }

    /** Tipo de identificación del SRI (tabla 6) a partir del número. */
    public static function tipoIdentificacion(string $identificacion): string
    {
        $id = trim($identificacion);
        if ($id === '9999999999999') {
            return '07'; // consumidor final
        }
        if (preg_match('/^\d{13}$/', $id)) {
            return '04'; // RUC
        }
        if (preg_match('/^\d{10}$/', $id)) {
            return '05'; // cédula
        }
        return '06';     // pasaporte / exterior
    }

    public static function tarifaIva(string $codIva): float
    {
        return self::IVA[$codIva][1] ?? 0.0;
    }

    /** Tarifa (%) → codigoPorcentaje. Los textos NO_OBJETO / EXENTO → 6 / 7. */
    public static function codigoIva($v): string
    {
        $s = strtoupper(trim((string) $v));
        if (str_contains($s, 'OBJETO')) {
            return '6';
        }
        if (str_contains($s, 'EXENT')) {
            return '7';
        }
        $pct = (int) round(self::numero($s));
        foreach (self::IVA as $cod => [, $tarifa]) {
            if (!in_array($cod, ['6', '7'], true) && (int) $tarifa === $pct) {
                return (string) $cod;
            }
        }
        return '0';
    }

    // ── Normalización ────────────────────────────────────────────────────────

    /**
     * Deja un "doc" (leído del PDF o devuelto por la pantalla de revisión) en su
     * forma canónica: tipos correctos, textos recortados, números a 2 decimales
     * y solo las claves conocidas. Nunca lanza excepción: lo que no se entiende
     * queda vacío y lo señalan las Rules.
     */
    public static function normalizar(array $d): array
    {
        $lista = static fn($v): array => is_array($v) ? array_slice(array_values($v), 0, self::MAX_LINEAS) : [];
        $hijo  = static fn($v): array => is_array($v) ? $v : [];

        $codDoc = str_pad(self::digitos($d['cod_doc'] ?? ''), 2, '0', STR_PAD_LEFT);
        $emisor = $hijo($d['emisor'] ?? null);
        $receptor = $hijo($d['receptor'] ?? null);
        $mod = $hijo($d['doc_modificado'] ?? null);
        $tot = $hijo($d['totales'] ?? null);

        $obligado = strtoupper(self::texto($emisor['obligado_contabilidad'] ?? '', 5));
        $serie = static fn($v, int $n): string => ($x = self::digitos($v)) !== '' && strlen($x) <= $n
            ? str_pad($x, $n, '0', STR_PAD_LEFT) : '';

        $out = [
            'cod_doc'             => isset(self::TIPOS[$codDoc]) ? $codDoc : '',
            'clave_acceso'        => self::digitos($d['clave_acceso'] ?? ''),
            'numero_autorizacion' => self::digitos($d['numero_autorizacion'] ?? ''),
            'fecha_autorizacion'  => self::fechaHora($d['fecha_autorizacion'] ?? ''),
            'emisor' => [
                'ruc'                   => self::digitos($emisor['ruc'] ?? ''),
                'razon_social'          => self::texto($emisor['razon_social'] ?? ''),
                'nombre_comercial'      => self::texto($emisor['nombre_comercial'] ?? ''),
                'dir_matriz'            => self::texto($emisor['dir_matriz'] ?? ''),
                'dir_establecimiento'   => self::texto($emisor['dir_establecimiento'] ?? ''),
                'obligado_contabilidad' => str_starts_with($obligado, 'S') ? 'SI' : ($obligado !== '' ? 'NO' : ''),
            ],
            'estab'         => $serie($d['estab'] ?? '', 3),
            'pto_emi'       => $serie($d['pto_emi'] ?? '', 3),
            'secuencial'    => $serie($d['secuencial'] ?? '', 9),
            'fecha_emision' => self::fecha($d['fecha_emision'] ?? ''),
            'receptor' => [
                // Un pasaporte puede traer letras: solo se quitan los espacios.
                'identificacion' => strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) ($receptor['identificacion'] ?? ''))),
                'razon_social'   => self::texto($receptor['razon_social'] ?? ''),
                'direccion'      => self::texto($receptor['direccion'] ?? ''),
            ],
            'doc_modificado' => [
                'cod_doc' => ($c = self::digitos($mod['cod_doc'] ?? '')) !== '' ? str_pad(substr($c, 0, 2), 2, '0', STR_PAD_LEFT) : '',
                'numero'  => self::numeroDocumento($mod['numero'] ?? ''),
                'fecha'   => self::fecha($mod['fecha'] ?? ''),
                'motivo'  => self::texto($mod['motivo'] ?? ''),
            ],
            'periodo_fiscal' => preg_match('#^(\d{1,2})\D(\d{4})$#', trim((string) ($d['periodo_fiscal'] ?? '')), $m)
                ? sprintf('%02d/%04d', (int) $m[1], (int) $m[2]) : '',
            'detalles' => [],
            'motivos' => [],
            'impuestos' => [],
            'totales' => [
                'subtotal'  => self::numero($tot['subtotal'] ?? 0),
                'descuento' => self::numero($tot['descuento'] ?? 0),
                'ice'       => self::numero($tot['ice'] ?? 0),
                'propina'   => self::numero($tot['propina'] ?? 0),
                'total'     => self::numero($tot['total'] ?? 0),
            ],
            'pagos' => [],
            'retenciones' => [],
            'info_adicional' => [],
        ];

        foreach ($lista($d['detalles'] ?? null) as $l) {
            $l = $hijo($l);
            $desc = self::texto($l['descripcion'] ?? '', 300);
            $sub = self::numero($l['subtotal'] ?? 0);
            if ($desc === '' && $sub == 0.0 && self::numero($l['cantidad'] ?? 0, 6) == 0.0) {
                continue; // fila vacía de la pantalla
            }
            $out['detalles'][] = [
                'codigo'          => self::texto($l['codigo'] ?? '', 25),
                'codigo_auxiliar' => self::texto($l['codigo_auxiliar'] ?? '', 25),
                'descripcion'     => $desc,
                'cantidad'        => self::numero($l['cantidad'] ?? 0, 6),
                'precio_unitario' => self::numero($l['precio_unitario'] ?? 0, 6),
                'descuento'       => self::numero($l['descuento'] ?? 0),
                'subtotal'        => $sub,
                'cod_iva'         => self::codigoIvaCanon($l['cod_iva'] ?? '0'),
            ];
        }
        foreach ($lista($d['motivos'] ?? null) as $l) {
            $l = $hijo($l);
            $razon = self::texto($l['razon'] ?? '', 300);
            if ($razon === '' && self::numero($l['valor'] ?? 0) == 0.0) {
                continue;
            }
            $out['motivos'][] = ['razon' => $razon, 'valor' => self::numero($l['valor'] ?? 0)];
        }
        foreach ($lista($d['impuestos'] ?? null) as $l) {
            $l = $hijo($l);
            $base = self::numero($l['base'] ?? 0);
            $valor = self::numero($l['valor'] ?? 0);
            if ($base == 0.0 && $valor == 0.0) {
                continue;
            }
            $out['impuestos'][] = ['cod_iva' => self::codigoIvaCanon($l['cod_iva'] ?? '0'), 'base' => $base, 'valor' => $valor];
        }
        foreach ($lista($d['pagos'] ?? null) as $l) {
            $l = $hijo($l);
            $fp = str_pad(substr(self::digitos($l['forma_pago'] ?? ''), 0, 2), 2, '0', STR_PAD_LEFT);
            $total = self::numero($l['total'] ?? 0);
            if ($total == 0.0) {
                continue;
            }
            $out['pagos'][] = [
                'forma_pago'    => isset(self::FORMAS_PAGO[$fp]) ? $fp : '20',
                'total'         => $total,
                'plazo'         => max(0, (int) self::numero($l['plazo'] ?? 0, 0)),
                'unidad_tiempo' => self::texto($l['unidad_tiempo'] ?? '', 10) ?: 'dias',
            ];
        }
        foreach ($lista($d['retenciones'] ?? null) as $l) {
            $l = $hijo($l);
            $imp = strtoupper(self::texto($l['impuesto'] ?? '', 10));
            $base = self::numero($l['base'] ?? 0);
            $valor = self::numero($l['valor'] ?? 0);
            if ($imp === '' && $base == 0.0 && $valor == 0.0) {
                continue;
            }
            $out['retenciones'][] = [
                'impuesto'           => isset(self::IMPUESTOS_RETENCION[$imp]) ? $imp : (str_contains($imp, 'IVA') ? 'IVA' : (str_contains($imp, 'ISD') ? 'ISD' : 'RENTA')),
                'codigo_retencion'   => strtoupper(self::texto($l['codigo_retencion'] ?? '', 10)),
                'base'               => $base,
                'porcentaje'         => self::numero($l['porcentaje'] ?? 0),
                'valor'              => $valor,
                'cod_doc_sustento'   => ($c = self::digitos($l['cod_doc_sustento'] ?? '')) !== '' ? str_pad(substr($c, 0, 2), 2, '0', STR_PAD_LEFT) : '',
                'num_doc_sustento'   => self::numeroDocumento($l['num_doc_sustento'] ?? ''),
                'fecha_doc_sustento' => self::fecha($l['fecha_doc_sustento'] ?? ''),
            ];
        }
        foreach (array_slice($lista($d['info_adicional'] ?? null), 0, 15) as $l) {
            $l = $hijo($l);
            $nombre = self::texto($l['nombre'] ?? '', 300);
            $valor = self::texto($l['valor'] ?? '', 300);
            if ($nombre !== '' && $valor !== '') {
                $out['info_adicional'][] = ['nombre' => $nombre, 'valor' => $valor];
            }
        }

        return $out;
    }

    /** cod_iva ya en forma de codigoPorcentaje válido, o '0'. */
    private static function codigoIvaCanon($v): string
    {
        $s = trim((string) $v);
        return isset(self::IVA[$s]) ? $s : '0';
    }
}
