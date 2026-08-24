<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Etiqueta "real" de un egreso/ingreso en los listados: a diferencia de
 * `egresos_cabecera.tipo_egreso` / `ingresos_cabecera.tipo_ingreso` (que solo indican
 * qué botón de concepto se usó — p. ej. todo lo pagado desde "Nómina" queda como 'ROL',
 * sin distinguir Rol de Décimo Cuarto/Tercero/Préstamos), esta clase arma la etiqueta a
 * partir de los `tipo_documento` reales guardados en `egresos_detalle`/`ingresos_detalle`
 * (ver combinar-conceptos: un documento puede tener más de un tipo mezclado).
 */
class TipoDocumentoHelper
{
    private const LABELS_EGRESO = [
        'COMPRA'         => 'Compra',
        'LIQUIDACION'    => 'Liquidación',
        'ROL'            => 'Rol de Pago',
        'ANTICIPO'       => 'Anticipo Empleado',
        'PRESTAMO7'      => 'Préstamo Quirografario',
        'PRESTAMO8'      => 'Préstamo Hipotecario',
        'PRESTAMO9'      => 'Préstamo Empresa',
        'DECIMO_CUARTO'  => 'Décimo Cuarto',
        'DECIMO_TERCERO' => 'Décimo Tercero',
        'MANUAL'         => 'Otros Conceptos',
    ];

    private const LABELS_INGRESO = [
        'FACTURA'           => 'Factura de Venta',
        'RECIBO'            => 'Recibo de Venta',
        'FACTURA_REEMBOLSO' => 'Factura de Reembolso',
        'SALDO_INICIAL'     => 'Saldo Inicial',
        'OTRO'              => 'Otro Ingreso',
    ];

    /**
     * @param string|null $tiposDetalle    tipo_documento distintos del detalle, separados por coma
     *                                     (viene de la subconsulta STRING_AGG del listado)
     * @param string|null $tipoCabeceraFallback tipo_egreso de la cabecera, para documentos legados sin detalle
     * @param string|null $conceptoNombre  nombre del concepto elegido (para el caso "todo MANUAL")
     */
    public static function egresoLabel(?string $tiposDetalle, ?string $tipoCabeceraFallback, ?string $conceptoNombre): string
    {
        return self::componer($tiposDetalle, self::LABELS_EGRESO, $tipoCabeceraFallback, $conceptoNombre);
    }

    public static function ingresoLabel(?string $tiposDetalle, ?string $tipoCabeceraFallback, ?string $conceptoNombre): string
    {
        return self::componer($tiposDetalle, self::LABELS_INGRESO, $tipoCabeceraFallback, $conceptoNombre);
    }

    private static function componer(?string $tiposDetalle, array $labels, ?string $tipoCabeceraFallback, ?string $conceptoNombre): string
    {
        $tipos = array_values(array_filter(array_map('trim', explode(',', (string) $tiposDetalle))));

        if (empty($tipos)) {
            // Documento legado o sin detalle resoluble: usar el tipo de cabecera tal cual.
            return $labels[$tipoCabeceraFallback] ?? ($tipoCabeceraFallback ?: 'Otro');
        }

        // Todo el detalle es manual (Otros conceptos / Anticipo / SRI / IESS...): el
        // nombre del concepto elegido es más específico que la etiqueta genérica.
        $soloManualOtro = array_diff($tipos, ['MANUAL', 'OTRO']);
        if (empty($soloManualOtro) && $conceptoNombre) {
            return $conceptoNombre;
        }

        $etiquetas = array_values(array_unique(array_map(
            fn($t) => $labels[$t] ?? $t,
            $tipos
        )));

        return implode(' + ', $etiquetas);
    }
}
