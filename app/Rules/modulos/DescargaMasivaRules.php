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
        'egreso',
        'ingreso',
        'cheque',
    ];

    public const FORMATOS_VALIDOS = ['pdf', 'xml', 'ambos'];

    public const MODOS_VALIDOS = ['fecha', 'numero'];

    /**
     * Tipos que no son documentos SRI (no tienen XML): solo admiten formato "pdf".
     * Debe coincidir con DescargaMasivaService::TIPOS_SIN_XML.
     */
    public const TIPOS_SIN_XML = ['egreso', 'ingreso', 'cheque'];

    /**
     * @throws \InvalidArgumentException si algún filtro no es válido.
     */
    public static function validarFiltros(
        string $tipo,
        string $formato,
        string $modo,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?int $numeroDesde,
        ?int $numeroHasta
    ): void {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }
        if (!in_array($formato, self::FORMATOS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Formato no válido.');
        }
        if (in_array($tipo, self::TIPOS_SIN_XML, true) && $formato !== 'pdf') {
            throw new \InvalidArgumentException('Este tipo de documento no tiene XML disponible. Usa el formato PDF.');
        }
        if (!in_array($modo, self::MODOS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Modo de filtro no válido.');
        }

        if ($modo === 'numero') {
            if ($numeroDesde === null || $numeroHasta === null) {
                throw new \InvalidArgumentException('Indica el número "desde" y "hasta".');
            }
            if ($numeroDesde <= 0 || $numeroHasta <= 0) {
                throw new \InvalidArgumentException('El número debe ser mayor a cero.');
            }
            if ($numeroDesde > $numeroHasta) {
                throw new \InvalidArgumentException('El número "desde" no puede ser mayor al número "hasta".');
            }
            return;
        }

        if ($fechaDesde === null || $fechaHasta === null) {
            throw new \InvalidArgumentException('Indica la fecha "desde" y "hasta".');
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
