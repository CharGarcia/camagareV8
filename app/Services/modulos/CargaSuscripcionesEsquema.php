<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Esquema del libro de Excel para la carga masiva de suscripciones.
 *
 * Única fuente de verdad sobre nombres de hojas y columnas: lo usan el generador
 * de la plantilla y el validador, de modo que nunca se desincronicen.
 *
 * Una suscripción es cabecera + N líneas de detalle, así que hay dos hojas de
 * datos enlazadas por la columna CLAVE:
 *   - Suscripciones: una fila por suscripción.
 *   - Detalle:       una o más filas por suscripción (productos/servicios).
 *
 * REGLA para el usuario: el libro debe conservar EXACTAMENTE estas hojas. No se
 * pueden borrar ni agregar hojas, ni renombrar o reordenar columnas.
 */
class CargaSuscripcionesEsquema
{
    // ── Hojas de datos (el usuario las edita) ────────────────────────────────
    public const HOJA_SUSCRIPCIONES = 'Suscripciones';
    public const HOJA_DETALLE       = 'Detalle';

    // ── Hojas de referencia (bloqueadas, solo consulta) ──────────────────────
    public const HOJA_INSTRUCCIONES     = 'Instrucciones';
    public const HOJA_REF_CLIENTES      = 'Ref_Clientes';
    public const HOJA_REF_PRODUCTOS     = 'Ref_Productos';
    public const HOJA_REF_PERIODICIDADES = 'Ref_Periodicidades';
    public const HOJA_REF_IVA           = 'Ref_IVA';

    // ── Hoja oculta de control ───────────────────────────────────────────────
    public const HOJA_CONFIG = '_Config';

    /** Valores de tipo_comprobante y sus etiquetas amigables en el desplegable. */
    public const TIPO_FACTURA = 'factura';
    public const TIPO_RECIBO  = 'recibo';

    /**
     * Hojas de datos con sus columnas (en orden) y la columna llave.
     * 'llave' es la columna CLAVE que enlaza la cabecera con su detalle.
     */
    public static function hojasDatos(): array
    {
        return [
            self::HOJA_SUSCRIPCIONES => [
                'titulo'   => 'Suscripciones (cabecera)',
                'llave'    => 'CLAVE',
                'columnas' => [
                    'CLAVE',
                    'RUC_CLIENTE',
                    'PERIODICIDAD',
                    'FECHA_INICIO',
                    'FECHA_FIN',
                    'PROXIMO_COBRO',
                    'FORMA_COBRO',
                    'TIPO_COMPROBANTE',
                    'ESTADO',
                    'OBSERVACIONES',
                    'INFO_CONCEPTO',
                    'INFO_DETALLE',
                ],
            ],
            self::HOJA_DETALLE => [
                'titulo'   => 'Detalle (productos/servicios)',
                'llave'    => 'CLAVE',
                'columnas' => [
                    'CLAVE',
                    'CODIGO_PRODUCTO',
                    'DESCRIPCION',
                    'CANTIDAD',
                    'PRECIO_UNITARIO',
                    'CODIGO_IVA',
                ],
            ],
        ];
    }

    /** Hojas de referencia con sus encabezados. */
    public static function hojasReferencia(): array
    {
        return [
            self::HOJA_INSTRUCCIONES      => [],
            self::HOJA_REF_CLIENTES       => ['RUC_CLIENTE', 'NOMBRE'],
            self::HOJA_REF_PRODUCTOS      => ['CODIGO_PRODUCTO', 'NOMBRE', 'PRECIO', 'CODIGO_IVA'],
            self::HOJA_REF_PERIODICIDADES => ['PERIODICIDAD', 'NOMBRE', 'DESCRIPCION'],
            self::HOJA_REF_IVA            => ['CODIGO_IVA', 'TARIFA', 'PORCENTAJE'],
        ];
    }

    /** Nombres de TODAS las hojas que debe tener el libro, en orden. */
    public static function todasLasHojas(): array
    {
        return array_merge(
            [self::HOJA_INSTRUCCIONES],
            array_keys(self::hojasDatos()),
            array_values(array_diff(array_keys(self::hojasReferencia()), [self::HOJA_INSTRUCCIONES])),
            [self::HOJA_CONFIG]
        );
    }

    /** Columnas esperadas de una hoja de datos. */
    public static function columnas(string $hoja): array
    {
        return self::hojasDatos()[$hoja]['columnas'] ?? [];
    }

    /** Estados válidos de una suscripción (columna ESTADO). */
    public static function estadosValidos(): array
    {
        return ['activo', 'pausado', 'suspendido', 'cancelado'];
    }

    /** Formas de cobro válidas (columna FORMA_COBRO). */
    public static function formasCobroValidas(): array
    {
        return ['credito', 'tarjeta'];
    }

    /**
     * Texto de instrucciones que se escribe en la hoja Instrucciones.
     * Cada elemento es una línea.
     */
    public static function textoInstrucciones(): array
    {
        return [
            'CARGA MASIVA DE SUSCRIPCIONES',
            '',
            'REGLAS IMPORTANTES',
            '1. NO borre ni agregue hojas a este libro. Debe conservar exactamente las hojas originales.',
            '2. NO renombre, elimine ni cambie el orden de las columnas.',
            '3. Escriba únicamente en las hojas Suscripciones y Detalle. Las hojas que empiezan',
            '   con "Ref_" son de consulta (clientes, productos, periodicidades y tarifas de IVA).',
            '',
            'CÓMO FUNCIONA',
            '- Cada fila de la hoja Suscripciones es UNA suscripción. La columna CLAVE (por ejemplo',
            '  SUS-001) la inventa usted para enlazar la cabecera con sus productos.',
            '- En la hoja Detalle escriba una fila por cada producto/servicio de la suscripción,',
            '  repitiendo la misma CLAVE de la cabecera.',
            '- Toda suscripción debe tener al menos un producto en la hoja Detalle.',
            '- Esta carga solo CREA suscripciones nuevas; no actualiza ni elimina.',
            '',
            'CABECERA (hoja Suscripciones)',
            '- RUC_CLIENTE: el cliente ya debe existir (vea la hoja Ref_Clientes). Se busca por su',
            '  RUC o cédula.',
            '- PERIODICIDAD: use uno de los códigos de la hoja Ref_Periodicidades (mensual, anual, etc.).',
            '- FECHA_INICIO: obligatoria, formato AAAA-MM-DD. FECHA_FIN es opcional.',
            '- PROXIMO_COBRO: opcional. Si se deja vacío, se toma la fecha de inicio.',
            '- FORMA_COBRO: Credito o Tarjeta. TIPO_COMPROBANTE: Factura o Recibo.',
            '- ESTADO: Activo, Pausado, Suspendido o Cancelado (por defecto Activo).',
            '- INFO_CONCEPTO / INFO_DETALLE: información adicional opcional (un solo par).',
            '',
            'DETALLE (hoja Detalle)',
            '- La columna CLAVE trae una fórmula en las primeras filas que copia la clave',
            '  de la hoja Suscripciones. Arrastre la fórmula hacia abajo y repita la clave',
            '  cuando una suscripción tenga varios productos.',
            '- CODIGO_PRODUCTO: el producto ya debe existir (vea la hoja Ref_Productos).',
            '- CANTIDAD: obligatoria, mayor que cero.',
            '- PRECIO_UNITARIO: opcional. Si se deja vacío, se usa el precio del producto.',
            '- CODIGO_IVA: opcional. Si se deja vacío, se usa la tarifa de IVA del producto.',
        ];
    }
}
