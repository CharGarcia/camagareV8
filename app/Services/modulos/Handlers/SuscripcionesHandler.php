<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

/**
 * Acciones de suscripciones.
 *
 * PENDIENTE DE IMPLEMENTAR: la generación de facturas desde suscripciones y el
 * aviso de vencimiento aún no están conectados a la lógica real. Devuelven un
 * mensaje informativo (no fingen éxito) hasta que se construya el servicio.
 */
class SuscripcionesHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, array $parametros): array
    {
        return match ($this->accion) {
            'generar_facturacion'      => $this->noImplementado('Generar facturación de suscripciones'),
            'enviar_aviso_vencimiento' => $this->noImplementado('Enviar aviso de vencimiento'),
            default                    => throw new \RuntimeException("Acción '{$this->accion}' no implementada en SuscripcionesHandler."),
        };
    }

    private function noImplementado(string $accion): array
    {
        return [
            'registros' => 0,
            'mensaje'   => "La acción \"{$accion}\" aún no está implementada. Pendiente de desarrollo.",
        ];
    }
}
