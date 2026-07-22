<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Esquema del libro de Excel para la carga masiva de productos/servicios.
 *
 * Es la única fuente de verdad sobre nombres de hojas y columnas: lo usan tanto
 * el generador de la plantilla como el validador, de modo que nunca se
 * desincronicen.
 *
 * REGLA para el usuario: el libro debe conservar EXACTAMENTE estas hojas.
 * No se pueden borrar ni agregar hojas, ni renombrar o reordenar columnas.
 */
class CargaProductosEsquema
{
    // ── Hojas de datos (el usuario las edita) ────────────────────────────────
    public const HOJA_PRODUCTOS      = 'Productos';
    public const HOJA_PRECIOS        = 'Precios';
    public const HOJA_VARIANTES      = 'Variantes';
    public const HOJA_COMPONENTES    = 'Componentes';
    public const HOJA_STOCK_BODEGAS  = 'Stock_Bodegas';
    public const HOJA_HOMOLOGACIONES = 'Homologaciones';

    // ── Hojas de referencia (bloqueadas, solo consulta) ──────────────────────
    public const HOJA_INSTRUCCIONES = 'Instrucciones';
    public const HOJA_REF_IVA        = 'Ref_IVA';
    public const HOJA_REF_MEDIDAS    = 'Ref_Medidas';
    public const HOJA_REF_CATEGORIAS = 'Ref_Categorias';
    public const HOJA_REF_MARCAS     = 'Ref_Marcas';
    public const HOJA_REF_BODEGAS    = 'Ref_Bodegas';
    public const HOJA_REF_ICE        = 'Ref_ICE';

    // ── Hoja oculta de control ───────────────────────────────────────────────
    public const HOJA_CONFIG = '_Config';

    /** Valor de tipo_produccion para bienes y servicios. */
    public const TIPO_BIEN     = '01';
    public const TIPO_SERVICIO = '02';

    /**
     * Hojas de datos con sus columnas (en orden) y la columna que actúa de llave.
     *
     * 'llave' es la columna que enlaza la fila con un producto de la hoja Productos.
     */
    public static function hojasDatos(): array
    {
        return [
            self::HOJA_PRODUCTOS => [
                'titulo'   => 'Productos y Servicios',
                'llave'    => 'CODIGO',
                'columnas' => [
                    'CODIGO',
                    'NOMBRE',
                    'TIPO',
                    'CODIGO_AUXILIAR',
                    'CODIGO_BARRAS',
                    'PRECIO_BASE',
                    'COSTO',
                    'CODIGO_IVA',
                    'CODIGO_MEDIDA',
                    'CATEGORIA',
                    'MARCA',
                    'INVENTARIABLE',
                    'STOCK_MINIMO',
                    'STOCK_MAXIMO',
                    'APLICA_COMPRA',
                    'APLICA_VENTA',
                    'CODIGO_ICE',
                    'ESTADO',
                ],
            ],
            self::HOJA_PRECIOS => [
                'titulo'   => 'Precios adicionales',
                'llave'    => 'CODIGO_PRODUCTO',
                'columnas' => ['CODIGO_PRODUCTO', 'NOMBRE_PRECIO', 'PRECIO', 'DESDE', 'HASTA', 'ACTIVO'],
            ],
            self::HOJA_VARIANTES => [
                'titulo'   => 'Variantes',
                'llave'    => 'CODIGO_PRODUCTO',
                'columnas' => ['CODIGO_PRODUCTO', 'NOMBRE', 'VALOR', 'PRECIO_ADICIONAL'],
            ],
            self::HOJA_COMPONENTES => [
                'titulo'   => 'Componentes',
                'llave'    => 'CODIGO_PADRE',
                'columnas' => ['CODIGO_PADRE', 'CODIGO_HIJO', 'CANTIDAD', 'CODIGO_MEDIDA'],
            ],
            self::HOJA_STOCK_BODEGAS => [
                'titulo'   => 'Stock mínimo/máximo por bodega',
                'llave'    => 'CODIGO_PRODUCTO',
                'columnas' => ['CODIGO_PRODUCTO', 'BODEGA', 'STOCK_MINIMO', 'STOCK_MAXIMO'],
            ],
            self::HOJA_HOMOLOGACIONES => [
                'titulo'   => 'Homologaciones con proveedores',
                'llave'    => 'CODIGO_PRODUCTO',
                'columnas' => ['CODIGO_PRODUCTO', 'RUC_CEDULA_PROVEEDOR', 'CODIGO_PROVEEDOR'],
            ],
        ];
    }

    /** Hojas de referencia con sus encabezados. */
    public static function hojasReferencia(): array
    {
        return [
            self::HOJA_INSTRUCCIONES => [],
            self::HOJA_REF_IVA        => ['CODIGO_IVA', 'TARIFA', 'PORCENTAJE'],
            self::HOJA_REF_MEDIDAS    => ['CODIGO_MEDIDA', 'NOMBRE', 'ABREVIATURA', 'TIPO_MEDIDA'],
            self::HOJA_REF_CATEGORIAS => ['CATEGORIA'],
            self::HOJA_REF_MARCAS     => ['MARCA'],
            self::HOJA_REF_BODEGAS    => ['BODEGA'],
            self::HOJA_REF_ICE        => ['CODIGO_ICE', 'NOMBRE', 'PORCENTAJE'],
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

    /**
     * Texto de instrucciones que se escribe en la hoja Instrucciones.
     * Cada elemento es una línea.
     */
    public static function textoInstrucciones(): array
    {
        return [
            'CARGA Y ACTUALIZACIÓN DE PRODUCTOS Y SERVICIOS',
            '',
            'REGLAS IMPORTANTES',
            '1. NO borre ni agregue hojas a este libro. Debe conservar exactamente las hojas originales.',
            '2. NO renombre, elimine ni cambie el orden de las columnas.',
            '3. Escriba únicamente en las filas. Las hojas que empiezan con "Ref_" son de consulta.',
            '',
            'CÓMO FUNCIONA',
            '- La columna CODIGO identifica al producto. Si el código ya existe, se ACTUALIZA.',
            '- Si el código no existe, se CREA un producto nuevo.',
            '- Desde este archivo NO se puede eliminar. Para dejar de ver un producto,',
            '  ponga ESTADO = Inactivo y se inactivará al subir el archivo.',
            '',
            'HOJAS ADICIONALES (Precios, Variantes, Componentes, Stock_Bodegas, Homologaciones)',
            '- Se enlazan con el producto por su CODIGO.',
            '- Un producto solo se modifica en esa sección si aparece en la hoja.',
            '  Si el producto no figura en la hoja, esa sección se conserva sin cambios.',
            '- Para quitar un precio, borre su fila. Para desactivarlo sin perder el',
            '  histórico, ponga ACTIVO = No.',
            '',
            'HOMOLOGACIONES',
            '- La relación con el proveedor se hace por su RUC o cédula.',
            '- El proveedor ya debe existir en el sistema; no se crea automáticamente.',
            '',
            'SERVICIOS',
            '- Un servicio (TIPO = Servicio) no maneja inventario, ni componentes,',
            '  ni variantes, ni stock por bodega.',
            '',
            'STOCK',
            '- La hoja Stock_Bodegas define stock MÍNIMO y MÁXIMO, no las existencias.',
            '  Las existencias se cargan desde el módulo de Cargas de Inventario.',
        ];
    }
}
