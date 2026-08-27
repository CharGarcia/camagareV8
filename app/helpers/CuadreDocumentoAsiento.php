<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Mapa del importe con el que debe cuadrar el asiento de cada documento operativo.
 *
 * Al editar a mano el asiento de un documento (modal de Asientos Contables) no basta con que
 * Debe = Haber: el asiento tiene que seguir reflejando el importe del documento que lo originó.
 * Este mapa dice, por `modulo_origen`, de dónde sale ese importe y contra qué parte del asiento
 * se compara.
 *
 * **Contra qué se compara (`slots`)**
 * - Con `slots`: se compara contra la suma de las líneas cuya cuenta es la de cartera
 *   (Cuentas por Cobrar en ventas, Cuentas por Pagar en compras), resueltas desde la
 *   configuración contable de la empresa (`asientos_programados` + `asientos_tipo`). El Debe
 *   total NO sirve de referencia en ventas: incluye además Costo de Ventas y el descuento,
 *   así que compararlo con el total de la factura daría descuadre estando todo bien
 *   (mismo criterio que la pestaña «Asiento contable» de la factura de venta).
 * - Sin `slots` (lista vacía): no hay cuenta de cartera configurable para ese documento, así que se
 *   compara el total Debe del asiento contra el total del documento — el mismo criterio del
 *   chequeo `monto_no_coincide` de Auditoría Contable.
 *
 * **Orígenes deliberadamente fuera del mapa** (su total no es comparable con el del asiento,
 * igual que `chequear_monto = 'informativo'` en AuditoriaContableRepository): `nomina`
 * (el asiento incluye aportes y provisiones), `consignacion_venta`, `retorno_cv`,
 * `cambio_producto_cv` y `FACTURACION_CV` (el asiento va a costo/reclasificación de inventario).
 * Tampoco entran `manual` (asiento de Diario, sin documento) ni `migracion` (un asiento migrado
 * consolida varios documentos del sistema viejo; ver el manual de Asientos contables).
 */
class CuadreDocumentoAsiento
{
    /** Diferencia máxima aceptada, en dólares: es el redondeo que absorbe el asiento. */
    public const TOLERANCIA = 0.03;

    /**
     * modulo_origen => cómo cuadrar.
     *
     * - `tabla`, `total`: de dónde sale el importe del documento (expresión SQL sobre `t`).
     * - `numero`: expresión SQL con el número del documento, solo para los que no están en
     *   App\Helpers\DocumentoOrigenAsiento (de ahí sale para el resto).
     * - `slots`: códigos de `asientos_tipo` con los que el documento puede resolver su cuenta de
     *   cartera —más de uno cuando el documento tiene fallback—, o lista vacía para comparar
     *   contra el total Debe.
     * - `lado`: lado del asiento en el que vive la cartera (`debe` en ventas, `haber` en compras).
     * - `etiqueta`: nombre del documento, para los mensajes.
     */
    public const CUADRES = [
        'factura_venta' => [
            'tabla' => 'ventas_cabecera',
            'total' => 't.importe_total',
            'slots' => ['PORCOBRARFACTURAVENTA'],
            'lado' => 'debe',
            'etiqueta' => 'Factura de venta',
        ],
        'recibo_venta' => [
            'tabla' => 'recibos_venta_cabecera',
            'total' => 't.importe_total',
            'slots' => ['PORCOBRARRECIBOVENTA'],
            'lado' => 'debe',
            'etiqueta' => 'Recibo de venta',
        ],
        'compra' => [
            'tabla' => 'compras_cabecera',
            'total' => 't.importe_total',
            'slots' => ['PORPAGARFACTURACOMPRA'],
            'lado' => 'haber',
            'etiqueta' => 'Compra',
        ],
        'liquidacion_compra' => [
            'tabla' => 'liquidaciones_cabecera',
            'total' => 't.importe_total',
            'slots' => ['PORPAGARFACTURACOMPRA'],
            'lado' => 'haber',
            'etiqueta' => 'Liquidación de compra',
        ],
        // La nota de débito de venta arma su asiento con las reglas del concepto `ventas_factura`
        // (ver AsientoBuilderService::generarAsientoNotaDebitoVenta), así que su cuenta por cobrar
        // sale del mismo slot que la factura: se compara sobre la cartera, no sobre el Debe total.
        'nota_debito' => [
            'tabla' => 'nota_debito_cabecera',
            'total' => 't.importe_total',
            'slots' => ['PORCOBRARFACTURAVENTA'],
            'lado' => 'debe',
            'etiqueta' => 'Nota de débito',
        ],
        // Factura de reembolso (ATS 41): el DEBE de Cuentas por Cobrar es el total que paga el
        // cliente; el HABER se reparte entre la cuenta puente de terceros, los honorarios propios
        // y su IVA, así que el total Debe tampoco sirve de referencia. La CxC sale del concepto
        // propio `factura_reembolso` y, si la empresa no lo configuró, del de ventas — los dos
        // slots, igual que el fallback de AsientoBuilderService::generarAsientoFacturaReembolso().
        // No está en DocumentoOrigenAsiento, por eso el número se define acá.
        'factura_reembolso' => [
            'tabla' => 'factura_reembolso_cabecera',
            'total' => 't.importe_total',
            'numero' => "concat_ws('-', t.establecimiento, t.punto_emision, t.secuencial)",
            'slots' => ['FACTREEMB_CXC_CLIENTE', 'PORCOBRARFACTURAVENTA'],
            'lado' => 'debe',
            'etiqueta' => 'Factura de reembolso',
        ],
        'nota_credito' => [
            'tabla' => 'notas_credito_cabecera',
            'total' => 't.importe_total',
            'slots' => [],
            'lado' => 'haber',
            'etiqueta' => 'Nota de crédito',
        ],
        'retencion_venta' => [
            'tabla' => 'retencion_venta_cabecera',
            'total' => '(COALESCE(t.total_isd,0) + COALESCE(t.total_iva,0) + COALESCE(t.total_renta,0))',
            'slots' => [],
            'lado' => 'debe',
            'etiqueta' => 'Retención de venta',
        ],
        'retencion_compra' => [
            'tabla' => 'retencion_compra_cabecera',
            'total' => 't.total_retenido',
            'slots' => [],
            'lado' => 'haber',
            'etiqueta' => 'Retención de compra',
        ],
        'ingreso' => [
            'tabla' => 'ingresos_cabecera',
            'total' => 't.monto_total',
            'slots' => [],
            'lado' => 'debe',
            'etiqueta' => 'Ingreso',
        ],
        'egreso' => [
            'tabla' => 'egresos_cabecera',
            'total' => 't.monto_total',
            'slots' => [],
            'lado' => 'haber',
            'etiqueta' => 'Egreso',
        ],
    ];

    /** Definición del cuadre de un módulo, o null si ese origen no se compara con su documento. */
    public static function paraModulo(?string $moduloOrigen): ?array
    {
        if ($moduloOrigen === null || $moduloOrigen === '') {
            return null;
        }
        return self::CUADRES[$moduloOrigen] ?? null;
    }
}
