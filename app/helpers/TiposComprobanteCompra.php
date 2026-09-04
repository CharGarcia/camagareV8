<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Clasificación de los tipos de comprobante de compra (compras_cabecera.tipo_comprobante, tabla 4
 * del ATS) según su efecto en la cartera de proveedores. Único punto de verdad para Cuentas por
 * Pagar, Reporte de Cartera, saldo del proveedor y pago automático; debe mantenerse coherente con
 * AsientoBuilderService::clasificarDireccionCompra(), que decide la dirección del asiento.
 *
 * Por qué existe: CxP y Cartera filtraban `tipo_comprobante = '01'` (solo facturas), así que una
 * nota de venta (02), un documento de institución financiera (12), una planilla de servicios
 * básicos (18), etc. generaban su asiento de cuenta por pagar pero nunca aparecían como deuda ni
 * se podían pagar desde CxP, y el Mayor de proveedores no cuadraba con CxP (empresa 33, 2026-09).
 */
final class TiposComprobanteCompra
{
    /** Notas de crédito recibidas: ABONO a la factura que modifican. Mismo set que el asiento (reversa). */
    public const NOTAS_CREDITO = ['04', '23', '47', '51'];

    /** Notas de débito recibidas: CARGO adicional ligado a la factura que modifican (documento_modificado). */
    public const NOTAS_DEBITO = ['05'];

    /** Sin efecto en cartera: guía de remisión (06) y comprobante de retención (07). */
    public const SIN_CARTERA = ['06', '07'];

    /** Nombres para mostrar de los tipos más comunes; el resto se muestra como "Comprobante NN". */
    public const NOMBRES = [
        '01' => 'Factura de Compra',
        '02' => 'Nota de Venta',
        '03' => 'Liquidación de Compra',
        '04' => 'Nota de Crédito',
        '05' => 'Nota de Débito',
        '08' => 'Boleto o Entrada a Espectáculo',
        '09' => 'Tiquete de Máquina Registradora',
        '11' => 'Pasaje Aéreo',
        '12' => 'Documento de Institución Financiera',
        '15' => 'Comprobante emitido en el Exterior',
        '18' => 'Documento Autorizado (servicios básicos y otros)',
        '19' => 'Comprobante de Cuotas o Aportes',
        '20' => 'Documento de Institución del Estado',
        '21' => 'Carta de Porte Aéreo',
        '41' => 'Comprobante de Venta por Reembolso',
        '44' => 'Comprobante de Contribuciones y Aportes',
        '45' => 'Liquidación de Reclamo de Aseguradora',
    ];

    /** ¿El tipo genera una deuda propia con el proveedor (una fila de cartera)? Vacío/NULL = factura (registros antiguos). */
    public static function esCargo(?string $tipo): bool
    {
        $t = trim((string) $tipo);
        if ($t === '') {
            return true;
        }
        return !in_array($t, self::NOTAS_CREDITO, true)
            && !in_array($t, self::NOTAS_DEBITO, true)
            && !in_array($t, self::SIN_CARTERA, true);
    }

    /**
     * Fragmento SQL (sin AND) que deja pasar solo los tipos que son cargo. `$col` es un
     * identificador escrito en el código (p. ej. 'c.tipo_comprobante'), nunca entrada de usuario.
     */
    public static function sqlEsCargo(string $col): string
    {
        $excluidos = array_merge(self::NOTAS_CREDITO, self::NOTAS_DEBITO, self::SIN_CARTERA);
        return "COALESCE(NULLIF(TRIM({$col}), ''), '01') NOT IN (" . self::lista($excluidos) . ")";
    }

    /** Fragmento SQL: nombre para mostrar según el tipo ("Factura de Compra", "Nota de Venta", …). */
    public static function sqlNombre(string $col): string
    {
        $norm = "COALESCE(NULLIF(TRIM({$col}), ''), '01')";
        $sql  = "CASE {$norm}";
        foreach (self::NOMBRES as $cod => $nom) {
            $sql .= " WHEN '{$cod}' THEN '" . str_replace("'", "''", $nom) . "'";
        }
        return $sql . " ELSE 'Comprobante ' || {$norm} END";
    }

    public static function nombre(?string $tipo): string
    {
        $t = trim((string) $tipo);
        if ($t === '') {
            $t = '01';
        }
        return self::NOMBRES[$t] ?? ('Comprobante ' . $t);
    }

    private static function lista(array $codigos): string
    {
        return implode(',', array_map(static fn(string $c): string => "'" . $c . "'", $codigos));
    }
}
