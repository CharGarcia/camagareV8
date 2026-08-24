<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Services\modulos\CargaFacturasEsquema;

/**
 * Reglas de negocio propias de la carga masiva de facturas por Excel.
 *
 * NO duplica FacturaVentaRules: esa se sigue aplicando íntegra al crear cada
 * factura. Aquí viven únicamente las decisiones que solo existen en el contexto
 * de un archivo (interpretar la columna TIPO, validar una línea suelta de la
 * hoja de detalles, etc.), para poder informarlas ANTES de escribir nada.
 */
class CargaFacturasRules
{
    /**
     * Convierte el texto de la columna TIPO a `tipo_produccion`.
     *
     * Vacío se asume Servicio: es el caso más común en una carga de facturas y
     * mantiene el comportamiento que tenía el módulo antes de existir la columna.
     *
     * @return string|null '01' bien, '02' servicio, null si el texto no se reconoce.
     */
    public function aTipoProduccion(string $valor): ?string
    {
        $v = mb_strtolower(trim($valor));

        if ($v === '') {
            return CargaFacturasEsquema::TIPO_SERVICIO;
        }
        if (in_array($v, ['producto', 'productos', 'bien', 'bienes', '01', '1'], true)) {
            return CargaFacturasEsquema::TIPO_BIEN;
        }
        if (in_array($v, ['servicio', 'servicios', 'serv', '02', '2'], true)) {
            return CargaFacturasEsquema::TIPO_SERVICIO;
        }

        return null;
    }

    /**
     * Valida una línea de la hoja Detalles en lo puramente numérico.
     * Las reglas que dependen de la configuración del establecimiento (lotes,
     * caducidad, NUP, facturación libre) las sigue aplicando FacturaVentaRules.
     *
     * @return string[] Errores encontrados.
     */
    public function validarLinea(?float $cantidad, ?float $precio, ?float $descuento, string $descripcion): array
    {
        $errores = [];

        if ($cantidad === null) {
            $errores[] = 'CANTIDAD es obligatoria y debe ser numérica.';
        } elseif ($cantidad <= 0) {
            $errores[] = 'CANTIDAD debe ser mayor que cero.';
        }

        if ($precio === null) {
            $errores[] = 'PRECIO_UNITARIO es obligatorio y debe ser numérico.';
        } elseif ($precio < 0) {
            $errores[] = 'PRECIO_UNITARIO no puede ser negativo.';
        }

        if ($descuento !== null && $descuento < 0) {
            $errores[] = 'DESCUENTO no puede ser negativo.';
        }

        if ($cantidad !== null && $precio !== null && $descuento !== null) {
            $bruto = round($cantidad * $precio, 2);
            if (round($descuento, 2) > $bruto) {
                $errores[] = 'DESCUENTO (' . number_format($descuento, 2) . ') no puede superar el '
                    . 'valor de la línea (' . number_format($bruto, 2) . ').';
            }
        }

        if (trim($descripcion) === '') {
            $errores[] = 'DESCRIPCION es obligatoria.';
        }

        return $errores;
    }

    /**
     * Valida la fecha de emisión de la cabecera.
     * @return string[] Errores encontrados.
     */
    public function validarFechaEmision(?string $fecha): array
    {
        if ($fecha === null || $fecha === '') {
            return ['FECHA_EMISION es obligatoria.'];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return ['FECHA_EMISION no tiene un formato de fecha válido.'];
        }

        if ($fecha > date('Y-m-d')) {
            return ['FECHA_EMISION no puede ser posterior a hoy.'];
        }

        return [];
    }
}
