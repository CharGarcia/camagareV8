<?php

declare(strict_types=1);

namespace App\Rules;

use App\models\SriEnvioLog;
use App\Services\ClaveAccesoService;

/**
 * Reglas comunes a los comprobantes electrónicos (factura, NC, ND, reembolso,
 * retención, guía, liquidación) frente al SRI.
 */
class SriDocumentoRules
{
    /**
     * Impide eliminar un comprobante que el SRI ya recibió, tiene en cola o
     * autorizó. Aunque el documento siga en borrador en el sistema, en el SRI su
     * secuencial ya quedó ocupado con esa clave de acceso: borrarlo aquí solo
     * esconde el documento, y es lo que dejaba retenciones y facturas nuevas
     * chocando con "ERROR 45 SECUENCIAL REGISTRADO" cuando el número se volvía
     * a usar. Lo correcto es esperar la resolución del SRI (el reintento
     * automático la trae) o anularlo.
     *
     * @param string $tipoSriLog  tipo_comprobante en sri_envio_log ('retencion_compra', 'factura_venta', …)
     * @param string $nombreDoc   cómo nombrar el documento en el mensaje ('la retención', 'la factura', …)
     * @throws \RuntimeException si el SRI lo tiene registrado
     */
    public static function validarEliminable(string $tipoSriLog, int $idComprobante, string $nombreDoc): void
    {
        try {
            $registro = (new SriEnvioLog())->getRegistroRetenidoPorSri($tipoSriLog, $idComprobante);
        } catch (\Throwable) {
            return; // Sin log no hay evidencia: no bloquear por un fallo de consulta.
        }
        if ($registro === null) {
            return;
        }

        $clave    = (string) ($registro['clave_acceso'] ?? '');
        $accion   = strtolower((string) ($registro['accion'] ?? ''));
        // SRI: '1' = pruebas, '2' = producción.
        $ambiente = ((string) ($registro['tipo_ambiente'] ?? '1')) === '2' ? 'producción' : 'pruebas';
        $numero   = ClaveAccesoService::numeroDesdeClave($clave);
        $situacion = str_starts_with($accion, 'autoriz')
            ? 'ya la autorizó'
            : 'ya la recibió y está pendiente de resolución';

        throw new \RuntimeException(
            "No se puede eliminar {$nombreDoc}: el SRI ({$ambiente}) {$situacion}"
            . ($numero !== '' ? " con el número {$numero}" : '')
            . " y clave de acceso {$clave}. Ese número ya quedó registrado en el SRI y no puede volver a usarse. "
            . "Si el documento no debe existir, anúlelo; si sigue pendiente, espere la resolución del SRI "
            . "(el sistema la vuelve a consultar automáticamente)."
        );
    }
}
