<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Respuesta de error de la VISTA PREVIA del asiento contable (pestaña «Asiento contable» de
 * cada documento, endpoint getAsientoSugeridoAjax de los módulos).
 *
 * Decisión del usuario (05-11-2026): que falte configurar cuentas contables NO es un error del
 * documento. El asiento lo genera solo la sincronización contable (ContabilidadAutoService)
 * cuando la configuración esté completa, y lo pendiente se controla al generar los estados
 * financieros (aviso de asientos pendientes). Por eso, cuando el motivo es configuración:
 *   - no se guarda en Errores del sistema (la vista previa se pide al abrir el documento y en
 *     cada cambio de precio o cantidad: llegaba a cientos de registros por día);
 *   - la pestaña muestra un texto neutro (gris), no el error en rojo.
 * Cualquier otro motivo (totales del documento que no cuadran, fallos de BD…) sigue igual:
 * se registra y se muestra.
 */
final class AsientoVistaPrevia
{
    /**
     * Textos con los que AsientoBuilderService avisa que falta configuración (cuentas en
     * Configuración contable, o categoría/marca en Productos cuando el asiento va por ellas).
     * Son los mismos que reconoce el aviso de sincronización (SincronizadorAsientosService).
     */
    private const PATRONES_CONFIGURACION = [
        'Falta asignar la cuenta contable',
        'Faltan cuentas por configurar',
        'No se ha configurado ninguna cuenta',
        'asígnesela en Productos',
    ];

    public const MENSAJE_SIN_CONFIGURACION =
        'Este documento aún no tiene asiento contable. Se generará automáticamente cuando la configuración contable esté completa.';

    public static function esFaltaConfiguracion(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        foreach (self::PATRONES_CONFIGURACION as $p) {
            if (mb_stripos($msg, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Arma la respuesta JSON de error de la vista previa.
     *
     * @param string $ruta    para Errores del sistema (normalmente static::class)
     * @param string $accion  ídem (normalmente __FUNCTION__)
     * @param string|null $mensajeVisible  texto a mostrar en vez del de la excepción (otros errores)
     */
    public static function respuestaError(\Throwable $e, string $ruta, string $accion, ?string $mensajeVisible = null): array
    {
        if (self::esFaltaConfiguracion($e)) {
            return [
                'ok'                => false,
                'sin_configuracion' => true,
                'error'             => self::MENSAJE_SIN_CONFIGURACION,
                'detalle'           => $e->getMessage(),   // qué falta, por si alguien lo consulta (title)
            ];
        }
        \App\Services\ErrorLogService::registrar($e, ['ruta' => $ruta, 'accion' => $accion]);
        return ['ok' => false, 'error' => $mensajeVisible ?? $e->getMessage()];
    }
}
