<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Esquema del libro de Excel para la carga masiva de facturas de venta.
 *
 * Es la única fuente de verdad sobre nombres de hojas y columnas: lo usan tanto
 * el generador de la plantilla como el validador, de modo que nunca se
 * desincronicen.
 *
 * REGLA para el usuario: el libro debe conservar EXACTAMENTE estas hojas.
 * No se pueden borrar ni agregar hojas, ni renombrar o reordenar columnas.
 */
class CargaFacturasEsquema
{
    // ── Hojas de datos (el usuario las edita) ────────────────────────────────
    public const HOJA_FACTURAS       = 'Facturas';
    public const HOJA_DETALLES       = 'Detalles';
    public const HOJA_INFO_ADICIONAL = 'Info_Adicional';

    // ── Hojas de referencia (bloqueadas, solo consulta) ──────────────────────
    public const HOJA_INSTRUCCIONES   = 'Instrucciones';
    public const HOJA_REF_IVA         = 'Ref_tarifa_iva';
    public const HOJA_REF_PUNTOS      = 'Ref_Puntos_Emision';
    public const HOJA_REF_BODEGAS     = 'Ref_Bodegas';
    public const HOJA_REF_VENDEDORES  = 'Ref_Vendedores';

    // ── Hoja oculta de control ───────────────────────────────────────────────
    public const HOJA_CONFIG = '_Config';

    /** Identificación del consumidor final en Ecuador. */
    public const IDENTIFICACION_CONSUMIDOR_FINAL = '9999999999999';

    /** Valor de tipo_produccion para bienes y servicios. */
    public const TIPO_BIEN     = '01';
    public const TIPO_SERVICIO = '02';

    /**
     * Hojas de datos con sus columnas (en orden) y la columna que actúa de llave.
     *
     * 'llave' es la columna que enlaza la fila con una factura de la hoja Facturas.
     */
    public static function hojasDatos(): array
    {
        return [
            self::HOJA_FACTURAS => [
                'titulo'   => 'Facturas (cabecera)',
                'llave'    => 'ID_FACTURA',
                'columnas' => [
                    'ID_FACTURA',
                    'FECHA_EMISION',
                    'IDENTIFICACION_CLIENTE',
                    // Solo informativa: el cliente se identifica por su
                    // identificación y debe existir ya en el sistema.
                    'NOMBRE_CLIENTE',
                    'ESTABLECIMIENTO',
                    'PUNTO_EMISION',
                    'BODEGA',
                    'VENDEDOR',
                    'DIAS_CREDITO',
                    'OBSERVACIONES',
                    'PROPINA',
                    'TOTAL_ESPERADO',
                ],
            ],
            self::HOJA_DETALLES => [
                'titulo'   => 'Líneas de la factura',
                'llave'    => 'ID_FACTURA',
                'columnas' => [
                    'ID_FACTURA',
                    'CODIGO_PRODUCTO',
                    // Solo decide cómo se CREA un código que no existe todavía.
                    // Si el código ya está en el catálogo, manda el catálogo.
                    'TIPO',
                    'DESCRIPCION',
                    'CANTIDAD',
                    'PRECIO_UNITARIO',
                    'DESCUENTO',
                    'CODIGO_IVA',
                    'LOTE',
                    'CADUCIDAD',
                    'NUP',
                    'INFO_ADICIONAL',
                ],
            ],
            self::HOJA_INFO_ADICIONAL => [
                'titulo'   => 'Información adicional del documento',
                'llave'    => 'ID_FACTURA',
                'columnas' => ['ID_FACTURA', 'NOMBRE', 'VALOR'],
            ],
        ];
    }

    /** Hojas de referencia con sus encabezados. */
    public static function hojasReferencia(): array
    {
        return [
            self::HOJA_INSTRUCCIONES   => [],
            self::HOJA_REF_IVA         => ['CODIGO_IVA', 'TARIFA', 'PORCENTAJE'],
            self::HOJA_REF_PUNTOS      => ['ESTABLECIMIENTO', 'PUNTO_EMISION', 'DIRECCION'],
            self::HOJA_REF_BODEGAS     => ['BODEGA'],
            self::HOJA_REF_VENDEDORES  => ['VENDEDOR'],
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
            'CARGA MASIVA DE FACTURAS DE VENTA',
            '',
            'REGLAS IMPORTANTES',
            '1. NO borre ni agregue hojas a este libro. Debe conservar exactamente las hojas originales.',
            '2. NO renombre, elimine ni cambie el orden de las columnas.',
            '3. Escriba únicamente en las filas. Las hojas que empiezan con "Ref_" son de consulta.',
            '',
            'CÓMO FUNCIONA',
            '- Cada fila de la hoja "Facturas" es UNA factura.',
            '- La columna ID_FACTURA es un identificador que usted inventa (F1, F2, F3...).',
            '  NO es el número de la factura: solo sirve para enlazar la cabecera con sus',
            '  líneas en la hoja "Detalles". Debe ser único dentro del archivo.',
            '- En la hoja "Detalles" escriba una fila por cada producto o servicio,',
            '  repitiendo el ID_FACTURA al que pertenece.',
            '- Las facturas se crean en estado BORRADOR. Ninguna se envía al SRI.',
            '  Revíselas en el módulo Facturas de Venta y emítalas desde ahí.',
            '',
            'NUMERACIÓN',
            '- El secuencial lo asigna el sistema automáticamente, tomando el siguiente',
            '  número del punto de emisión indicado. No hay columna de secuencial.',
            '- ESTABLECIMIENTO y PUNTO_EMISION son los CÓDIGOS (001, 002...), no los nombres.',
            '  Consúltelos en la hoja Ref_Puntos_Emision.',
            '',
            'CLIENTES',
            '- Basta con escribir la identificación (cédula, RUC o pasaporte): el',
            '  sistema busca al cliente en la base de datos de la empresa. No hace',
            '  falta consultar ninguna lista.',
            '- El cliente YA DEBE ESTAR REGISTRADO. Si la identificación no existe,',
            '  esa factura no se crea y se le indica en el informe. Regístrelo antes',
            '  en el módulo Clientes (o con la carga masiva de clientes) y vuelva a',
            '  subir el archivo.',
            '- Para consumidor final use la identificación 9999999999999.',
            '- NOMBRE_CLIENTE es solo informativo, para que usted lea el archivo con',
            '  comodidad. El sistema no lo usa: manda la identificación.',
            '',
            'PRODUCTOS Y SERVICIOS',
            '- Escriba el código en CODIGO_PRODUCTO: el sistema busca ese código en el',
            '  catálogo de la empresa. No hace falta consultar ninguna lista.',
            '- Si el código YA existe, se factura ese producto con la configuración que',
            '  tenga en el catálogo (inventario, lotes, etc.). La columna TIPO se ignora.',
            '- Si el código NO existe, se crea automáticamente, y ahí sí decide TIPO:',
            '    * TIPO = Servicio  -> se crea un servicio. No maneja inventario.',
            '    * TIPO = Producto  -> se crea un bien CON control de inventario,',
            '      pero SIN existencias (stock cero). Ingrese el stock desde Cargas',
            '      de Inventario.',
            '  Si deja TIPO vacío se asume Servicio.',
            '- IMPORTANTE: si su establecimiento está configurado para no permitir',
            '  ventas con stock negativo, una línea de TIPO = Producto con código',
            '  nuevo NO se podrá facturar (el producto nace en cero). En ese caso',
            '  cree el producto e ingrese su stock antes, y aquí solo use el código.',
            '- Si deja CODIGO_PRODUCTO vacío, la línea se factura como ítem libre usando',
            '  la DESCRIPCION (solo si el establecimiento permite facturación libre).',
            '',
            'PRECIOS E IVA',
            '- PRECIO_UNITARIO va SIN IVA (es la base imponible), igual que en la pantalla',
            '  de Factura de Venta.',
            '- CODIGO_IVA es el código de la tarifa. Consulte la hoja Ref_tarifa_iva para ver',
            '  los códigos vigentes y a qué porcentaje corresponde cada uno.',
            '- DESCUENTO es el valor en dólares que se resta a la línea, no un porcentaje.',
            '- Subtotal de la línea = CANTIDAD x PRECIO_UNITARIO - DESCUENTO.',
            '',
            'FORMA DE PAGO',
            '- No hay que escribirla: cada factura lleva UNA sola forma de pago, por',
            '  el total del documento, y el sistema usa la misma que usaría la',
            '  pantalla de Factura de Venta, en este orden:',
            '    1. La forma de pago configurada en la ficha del CLIENTE.',
            '    2. Si el cliente no tiene ninguna, la configurada en el',
            '       ESTABLECIMIENTO (Configuración de la empresa).',
            '- Si el cliente no tiene forma de pago y el establecimiento tampoco,',
            '  esa factura no se crea: configure una de las dos.',
            '- En el informe previo verá qué forma de pago se aplicará a cada factura.',
            '- El plazo se toma de DIAS_CREDITO (en días).',
            '',
            'CONTROL DE CUADRE',
            '- TOTAL_ESPERADO es opcional. Si lo llena, el sistema compara ese valor con',
            '  el total que calcula y avisa si no coinciden. Sirve para detectar errores',
            '  de digitación antes de crear las facturas.',
            '',
            'LO QUE NO SE PUEDE HACER DESDE ESTE ARCHIVO',
            '- No se pueden modificar ni eliminar facturas ya existentes.',
            '- No se cargan ICE ni facturas con reembolso.',
            '- No se envía nada al SRI.',
        ];
    }
}
