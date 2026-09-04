<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio del envío de avisos de retención pendiente.
 */
class ReporteRetencionesPendientesRules
{
    /** Tope de facturas por envío agrupado (el SMTP secuencial tarda varios segundos por cliente). */
    public const MAX_DOCUMENTOS_LOTE = 300;

    /**
     * Separa y valida una cadena de correos ("a@x.com, b@y.com").
     * Devuelve solo las direcciones válidas (puede ser vacío).
     */
    public function direccionesValidas(string $correos): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $correos) ?: [] as $c) {
            $c = trim($c);
            if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $out[$c] = $c; // dedup
            }
        }
        return array_values($out);
    }

    public function validarEnvioIndividual(int $idVenta, string $correo): void
    {
        if ($idVenta <= 0) {
            throw new \Exception('Factura no indicada.');
        }
        if (empty($this->direccionesValidas($correo))) {
            throw new \Exception('Ingrese al menos un correo destinatario válido.');
        }
    }

    public function validarLote(array $idsVentas): void
    {
        if (empty($idsVentas)) {
            throw new \Exception('No se recibieron facturas para enviar.');
        }
        if (count($idsVentas) > self::MAX_DOCUMENTOS_LOTE) {
            throw new \Exception('Máximo ' . self::MAX_DOCUMENTOS_LOTE . ' facturas por envío. Aplique filtros y envíe por partes.');
        }
    }

    /** La factura debe seguir pendiente (sin retención) al momento de enviar. */
    public function validarPendiente(?array $factura): array
    {
        if (!$factura) {
            throw new \Exception('La factura no está disponible: no existe, no está autorizada o ya registra un comprobante de retención.');
        }
        return $factura;
    }
}
