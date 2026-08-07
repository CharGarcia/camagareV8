<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio del módulo Descargas Masivas.
 */
class DescargaMasivaRules
{
    public const TIPOS_VALIDOS = [
        'factura_venta',
        'notas_credito',
        'nota_debito',
        'guias_remision',
        'retencion_venta',
        'retencion_compra',
        'liquidacion_compra',
        'compras',
    ];

    public const FORMATOS_VALIDOS = ['pdf', 'xml', 'ambos'];

    /**
     * @throws \InvalidArgumentException si algún filtro no es válido.
     */
    public static function validarFiltros(string $tipo, string $formato, string $fechaDesde, string $fechaHasta): void
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }
        if (!in_array($formato, self::FORMATOS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Formato no válido.');
        }

        $desde = \DateTime::createFromFormat('Y-m-d', $fechaDesde);
        if (!$desde || $desde->format('Y-m-d') !== $fechaDesde) {
            throw new \InvalidArgumentException('La fecha "desde" no es válida.');
        }
        $hasta = \DateTime::createFromFormat('Y-m-d', $fechaHasta);
        if (!$hasta || $hasta->format('Y-m-d') !== $fechaHasta) {
            throw new \InvalidArgumentException('La fecha "hasta" no es válida.');
        }
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('La fecha "desde" no puede ser posterior a la fecha "hasta".');
        }
    }
}
