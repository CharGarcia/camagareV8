<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Mapa del documento operativo que originó cada asiento contable.
 *
 * Las líneas de un asiento (asientos_contables_detalle) guardan el tercero
 * (tipo_entidad + id_entidad) y el número del documento (documento_referencia), pero no todos
 * los módulos los llenan: los asientos generados por el builder/sincronizador y los migrados
 * desde el sistema viejo llegan con esas columnas en NULL. Este mapa permite recuperar el dato
 * desde el documento origen, que SÍ lo tiene, y también leer el documento completo para
 * mostrarlo (ver DocumentoOrigenService: es el modal que se abre desde la columna
 * "Documento Ref." de Mayores y del mayor auxiliar de Estados Financieros).
 *
 * Hay dos formas de llegar al documento desde el asiento:
 *   1. `modulo_origen` + `id_referencia_origen` de la cabecera (asientos de módulo: usa la PK).
 *   2. `documento.{col_asiento} = asiento.id`, el enlace inverso que escribe cada módulo y que
 *      también deja la migración de MySQL. Es la única vía para los asientos migrados, cuyo
 *      `modulo_origen` es 'migracion' y no dice a qué tabla apunta `id_referencia_origen`.
 *
 * Cada entrada define expresiones SQL sobre el alias `t` (cabecera del documento), `d` (línea
 * del detalle) y `p` (producto, cuando la línea solo guarda su id) para que los repositorios
 * armen sus consultas sin repetir el mapeo. Solo se listan los módulos cuyo documento tiene un
 * tercero identificable: nómina (consolidado), traspasos, activos fijos y declaraciones no lo
 * tienen.
 */
class DocumentoOrigenAsiento
{
    /** Número de documento estándar: establecimiento-punto de emisión-secuencial. */
    private const NUM_SRI = "concat_ws('-', t.establecimiento, t.punto_emision, t.secuencial)";

    /** Detalle típico de productos/servicios (facturas, compras, notas de crédito…). */
    private const LINEAS_PRODUCTO = [
        'Código' => 'd.codigo_principal',
        'Descripción' => 'd.descripcion',
        'Cantidad' => 'd.cantidad',
        'P. Unitario' => 'd.precio_unitario',
        'Descuento' => 'd.descuento',
        'Total' => 'd.precio_total_sin_impuesto',
    ];

    /** Columnas de importe: se alinean a la derecha y se muestran con 2 decimales. */
    private const NUM_PRODUCTO = ['Cantidad', 'P. Unitario', 'Descuento', 'Total'];
    private const NUM_CONSIGNACION = ['Cantidad', 'P. Unitario', 'Subtotal', 'Impuesto', 'Total'];

    /** Detalle de consignaciones y sus derivados: la línea guarda el id del producto. */
    private const LINEAS_CONSIGNACION = [
        'Producto' => "concat_ws(' - ', p.codigo, p.nombre)",
        'Cantidad' => 'd.cantidad',
        'P. Unitario' => 'd.precio_unitario',
        'Subtotal' => 'd.subtotal',
        'Impuesto' => 'd.valor_impuesto',
        'Total' => 'd.total',
    ];

    /**
     * modulo_origen => definición del documento.
     *
     * Enlace con el asiento:
     * - `tabla`, `tipo`, `entidad`, `numero`: expresiones SQL sobre `t`.
     * - `col_asiento`: columna del documento que apunta al asiento (enlace inverso).
     *
     * Presentación (modal de detalle):
     * - `etiqueta`: nombre del tipo de documento.
     * - `fecha`, `estado`, `observaciones`: columnas de la cabecera (null si no existe).
     * - `totales`: etiqueta => columna con importe.
     * - `detalle`: tabla de líneas, su FK a la cabecera, JOIN opcional y columnas a mostrar.
     */
    public const DOCUMENTOS = [
        'factura_venta' => [
            'tabla' => 'ventas_cabecera',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Factura de venta',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'total_sin_impuestos', 'Descuento' => 'total_descuento', 'Total' => 'importe_total'],
            'detalle' => ['tabla' => 'ventas_detalle', 'fk' => 'id_venta', 'columnas' => self::LINEAS_PRODUCTO, 'numericas' => self::NUM_PRODUCTO],
        ],
        'compra' => [
            'tabla' => 'compras_cabecera',
            'tipo' => "'proveedor'",
            'entidad' => 't.id_proveedor',
            'numero' => "concat_ws('-', t.establecimiento_prov, t.punto_emision_prov, t.secuencial_prov)",
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Compra',
            'fecha' => 'fecha_emision',
            'estado' => null,
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'total_sin_impuestos', 'Descuento' => 'total_descuento', 'Total' => 'importe_total'],
            'detalle' => ['tabla' => 'compras_detalle', 'fk' => 'id_compra', 'columnas' => self::LINEAS_PRODUCTO, 'numericas' => self::NUM_PRODUCTO],
        ],
        'ingreso' => [
            'tabla' => 'ingresos_cabecera',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => "COALESCE(NULLIF(t.numero_ingreso, ''), " . self::NUM_SRI . ")",
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Ingreso',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Total' => 'monto_total'],
            'detalle' => [
                'tabla' => 'ingresos_detalle',
                'fk' => 'id_ingreso',
                'columnas' => [
                    'Documento' => "concat_ws(' ', d.tipo_documento, d.numero_documento)",
                    'Descripción' => 'd.descripcion',
                    'Monto doc.' => 'd.monto_documento',
                    'Cobrado' => 'd.monto_cobrado',
                    'Saldo' => 'd.saldo_actual',
                ],
                'numericas' => ['Monto doc.', 'Cobrado', 'Saldo'],
            ],
        ],
        // Un egreso puede ir a un proveedor o a un empleado (el que tenga).
        'egreso' => [
            'tabla' => 'egresos_cabecera',
            'tipo' => "CASE WHEN t.id_proveedor IS NOT NULL THEN 'proveedor' WHEN t.id_empleado IS NOT NULL THEN 'empleado' END",
            'entidad' => 'COALESCE(t.id_proveedor, t.id_empleado)',
            'numero' => "COALESCE(NULLIF(t.numero_egreso, ''), " . self::NUM_SRI . ")",
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Egreso',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Total' => 'monto_total'],
            'detalle' => [
                'tabla' => 'egresos_detalle',
                'fk' => 'id_egreso',
                'columnas' => [
                    'Documento' => "concat_ws(' ', d.tipo_documento, d.numero_documento)",
                    'Descripción' => 'd.descripcion',
                    'Monto doc.' => 'd.monto_documento',
                    'Pagado' => 'd.monto_pagado',
                    'Saldo' => 'd.saldo_actual',
                ],
                'numericas' => ['Monto doc.', 'Pagado', 'Saldo'],
            ],
        ],
        'recibo_venta' => [
            'tabla' => 'recibos_venta_cabecera',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => "COALESCE(NULLIF(t.recibo_numero, ''), " . self::NUM_SRI . ")",
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Recibo de venta',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'total_sin_impuestos', 'Descuento' => 'total_descuento', 'Total' => 'importe_total'],
            'detalle' => ['tabla' => 'recibos_venta_detalle', 'fk' => 'id_recibo', 'columnas' => self::LINEAS_PRODUCTO, 'numericas' => self::NUM_PRODUCTO],
        ],
        'nota_credito' => [
            'tabla' => 'notas_credito_cabecera',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Nota de crédito',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'total_sin_impuestos', 'Descuento' => 'total_descuento', 'Total' => 'importe_total'],
            'detalle' => ['tabla' => 'notas_credito_detalle', 'fk' => 'id_nota_credito', 'columnas' => self::LINEAS_PRODUCTO, 'numericas' => self::NUM_PRODUCTO],
        ],
        'nota_debito' => [
            'tabla' => 'nota_debito_cabecera',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Nota de débito',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'total_sin_impuestos', 'Total' => 'importe_total'],
            'detalle' => [
                'tabla' => 'nota_debito_motivos',
                'fk' => 'id_nota_debito',
                'columnas' => ['Razón' => 'd.razon', 'Valor' => 'd.valor'],
                'numericas' => ['Valor'],
            ],
        ],
        'retencion_compra' => [
            'tabla' => 'retencion_compra_cabecera',
            'tipo' => "'proveedor'",
            'entidad' => 't.id_proveedor',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Retención de compra',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => null,
            'totales' => ['Total retenido' => 'total_retenido'],
            'detalle' => [
                'tabla' => 'retencion_compra_detalle',
                'fk' => 'id_retencion',
                'columnas' => [
                    'Código' => 'd.codigo_retencion',
                    'Concepto' => 'd.concepto',
                    'Doc. sustento' => 'd.num_doc_sustento',
                    'Base' => 'd.base_imponible',
                    '%' => 'd.porcentaje_retener',
                    'Retenido' => 'd.valor_retenido',
                ],
                'numericas' => ['Base', '%', 'Retenido'],
            ],
        ],
        'retencion_venta' => [
            'tabla' => 'retencion_venta_cabecera',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Retención de venta',
            'fecha' => 'fecha_emision',
            'estado' => null,
            'observaciones' => null,
            'totales' => ['Renta' => 'total_renta', 'IVA' => 'total_iva', 'ISD' => 'total_isd'],
            'detalle' => [
                'tabla' => 'retencion_venta_detalle',
                'fk' => 'id_retencion',
                'columnas' => [
                    'Código' => 'd.codigo_retencion',
                    'Doc. sustento' => 'd.num_doc_sustento',
                    'Base' => 'd.base_imponible',
                    '%' => 'd.porcentaje_retencion',
                    'Retenido' => 'd.valor_retenido',
                ],
                'numericas' => ['Base', '%', 'Retenido'],
            ],
        ],
        'liquidacion_compra' => [
            'tabla' => 'liquidaciones_cabecera',
            'tipo' => "'proveedor'",
            'entidad' => 't.id_proveedor',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Liquidación de compra',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'total_sin_impuestos', 'Descuento' => 'total_descuento', 'Total' => 'importe_total'],
            'detalle' => ['tabla' => 'liquidaciones_detalle', 'fk' => 'id_cabecera', 'columnas' => self::LINEAS_PRODUCTO, 'numericas' => self::NUM_PRODUCTO],
        ],
        'importacion' => [
            'tabla' => 'importaciones_cabecera',
            'tipo' => "'proveedor'",
            'entidad' => 't.id_proveedor',
            'numero' => "COALESCE(NULLIF(t.numero_importacion, ''), " . self::NUM_SRI . ")",
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Importación',
            'fecha' => 'fecha_llegada',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['FOB' => 'subtotal_fob', 'IVA' => 'total_iva', 'Costo nacionalizado' => 'costo_total_nacionalizado'],
            'detalle' => [
                'tabla' => 'importaciones_detalle',
                'fk' => 'id_importacion',
                'columnas' => [
                    'Código' => 'd.codigo_producto_raw',
                    'Descripción' => 'd.descripcion',
                    'Cantidad' => 'd.cantidad',
                    'FOB unit.' => 'd.precio_unitario_fob',
                    'Costo nacionalizado' => 'd.costo_total_nacionalizado',
                ],
                'numericas' => ['Cantidad', 'FOB unit.', 'Costo nacionalizado'],
            ],
        ],
        'consignacion_venta' => [
            'tabla' => 'consignaciones_ventas',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Consignación de venta',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'subtotal', 'Impuesto' => 'impuesto', 'Total' => 'total'],
            'detalle' => [
                'tabla' => 'consignaciones_ventas_detalles',
                'fk' => 'id_consignacion',
                'join' => 'LEFT JOIN productos p ON p.id = d.id_producto',
                'columnas' => self::LINEAS_CONSIGNACION,
                'numericas' => self::NUM_CONSIGNACION,
            ],
        ],
        'retorno_cv' => [
            'tabla' => 'retornos_cv',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Retorno de consignación',
            'fecha' => 'fecha_retorno',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'subtotal', 'Impuesto' => 'impuesto', 'Total' => 'total'],
            'detalle' => [
                'tabla' => 'retornos_cv_detalles',
                'fk' => 'id_retorno',
                'join' => 'LEFT JOIN productos p ON p.id = d.id_producto',
                'columnas' => self::LINEAS_CONSIGNACION,
                'numericas' => self::NUM_CONSIGNACION,
            ],
        ],
        'cambio_producto_cv' => [
            'tabla' => 'cambios_producto_cv',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => self::NUM_SRI,
            'col_asiento' => 'id_asiento_contable',
            'etiqueta' => 'Cambio de productos',
            'fecha' => 'fecha_cambio',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Devuelto' => 'subtotal_devuelto', 'Entregado' => 'subtotal_entregado'],
            'detalle' => [
                'tabla' => 'cambios_producto_cv_detalles',
                'fk' => 'id_cambio',
                'join' => 'LEFT JOIN productos p ON p.id = d.id_producto',
                'columnas' => [
                    'Lado' => 'd.tipo_linea',
                    'Producto' => "concat_ws(' - ', p.codigo, p.nombre)",
                    'Cantidad' => 'd.cantidad',
                    'P. Unitario' => 'd.precio_unitario',
                    'Total' => 'd.total',
                ],
                'numericas' => ['Cantidad', 'P. Unitario', 'Total'],
            ],
        ],
        // Facturación de consignaciones: su asiento de reingreso vive en otra columna.
        'FACTURACION_CV' => [
            'tabla' => 'consignaciones_facturas',
            'tipo' => "'cliente'",
            'entidad' => 't.id_cliente',
            'numero' => "COALESCE(NULLIF(t.numero_factura, ''), " . self::NUM_SRI . ")",
            'col_asiento' => 'id_asiento_reingreso',
            'etiqueta' => 'Facturación de consignación',
            'fecha' => 'fecha_emision',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
            'totales' => ['Subtotal' => 'subtotal', 'Impuesto' => 'impuesto', 'Total' => 'total'],
            'detalle' => [
                'tabla' => 'consignaciones_facturas_detalles',
                'fk' => 'id_consignacion_factura',
                'join' => 'LEFT JOIN productos p ON p.id = d.id_producto',
                'columnas' => self::LINEAS_CONSIGNACION,
                'numericas' => self::NUM_CONSIGNACION,
            ],
        ],
    ];

    /** Metadatos del documento de un módulo, o null si ese módulo no tiene documento mapeado. */
    public static function paraModulo(?string $moduloOrigen): ?array
    {
        $modulo = trim((string) $moduloOrigen);
        return self::DOCUMENTOS[$modulo] ?? null;
    }

    /** Nombres de tabla de todos los documentos del mapa (para verificar cuáles existen en la BD). */
    public static function tablas(): array
    {
        return array_column(self::DOCUMENTOS, 'tabla');
    }
}
