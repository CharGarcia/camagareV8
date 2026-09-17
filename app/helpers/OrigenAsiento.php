<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Catálogo de los orígenes de un asiento contable (`asientos_contables_cabecera.modulo_origen`)
 * con su nombre legible.
 *
 * La clave es el valor EXACTO que graba cada módulo (mayúsculas incluidas: la facturación de
 * consignaciones graba 'FACTURACION_CV'). El orden es el de presentación: ventas, compras,
 * tesorería, consignaciones, nómina, activos fijos, declaraciones y, al final, los asientos
 * manuales y los migrados del sistema anterior.
 *
 * Al agregar un módulo que genere asientos con un `modulo_origen` nuevo, sumarlo aquí: el filtro
 * «Origen» del Libro Diario lista este catálogo completo, tenga o no asientos la empresa.
 */
final class OrigenAsiento
{
    public const ETIQUETAS = [
        'factura_venta'              => 'Factura de venta',
        'factura_reembolso'          => 'Factura de reembolso',
        'recibo_venta'               => 'Recibo de venta',
        'nota_credito'               => 'Nota de crédito',
        'nota_debito'                => 'Nota de débito',
        'retencion_venta'            => 'Retención de venta',
        'compra'                     => 'Compra',
        'liquidacion_compra'         => 'Liquidación de compra',
        'retencion_compra'           => 'Retención de compra',
        'importacion'                => 'Importación',
        'ingreso'                    => 'Ingreso',
        'egreso'                     => 'Egreso',
        'traspaso'                   => 'Traspaso',
        'conciliacion_tarjetas'      => 'Conciliación de tarjetas',
        'consignacion_venta'         => 'Consignación de venta',
        'retorno_cv'                 => 'Retorno de consignación',
        'cambio_producto_cv'         => 'Cambio de productos',
        'FACTURACION_CV'             => 'Facturación de consignación',
        'nomina'                     => 'Rol de pagos (nómina)',
        'activos_fijos_alta'         => 'Activo fijo: alta',
        'activos_fijos_depreciacion' => 'Activo fijo: depreciación',
        'declaracion_iva'            => 'Declaración de IVA',
        'declaracion_retenciones'    => 'Declaración de retenciones',
        'manual'                     => 'Asiento manual',
        'migracion'                  => 'Migración (sistema anterior)',
    ];

    /** Nombre legible; un valor fuera del catálogo se muestra con guiones bajos como espacios. */
    public static function etiqueta(string $origen): string
    {
        return self::ETIQUETAS[$origen] ?? ucfirst(str_replace('_', ' ', mb_strtolower($origen)));
    }

    /**
     * Todos los orígenes del catálogo y, al final, los que la empresa tenga guardados con otro
     * valor (históricos o de módulos que aún no están en el catálogo), en orden alfabético.
     *
     * @param string[] $usados Valores de `modulo_origen` presentes en los asientos de la empresa.
     * @return string[]
     */
    public static function todosConUsados(array $usados): array
    {
        $extras = array_values(array_diff(
            array_unique(array_filter(array_map('strval', $usados), static fn(string $v) => trim($v) !== '')),
            array_keys(self::ETIQUETAS)
        ));
        usort($extras, static fn(string $a, string $b) => strcmp(self::etiqueta($a), self::etiqueta($b)));

        return array_merge(array_keys(self::ETIQUETAS), $extras);
    }
}
