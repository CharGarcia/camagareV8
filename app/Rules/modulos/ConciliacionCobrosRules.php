<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ConciliacionCobrosRules
{
    private const CAMPOS_MAPEO_EXCEL_OBLIGATORIOS = ['fecha', 'descripcion', 'monto'];

    public function validarPerfil(array $data): void
    {
        if (empty(trim((string) ($data['nombre_perfil'] ?? '')))) {
            throw new \Exception('Debe indicar un nombre para el perfil.');
        }

        $tipo = strtoupper((string) ($data['tipo_archivo'] ?? ''));
        if (!in_array($tipo, ['EXCEL', 'PDF'], true)) {
            throw new \Exception('El tipo de archivo del perfil debe ser EXCEL o PDF.');
        }

        $mapeo = $data['mapeo_columnas'] ?? null;
        if (!is_array($mapeo) || empty($mapeo)) {
            throw new \Exception('Debe configurar el mapeo de columnas del perfil.');
        }

        if ($tipo === 'EXCEL') {
            foreach (self::CAMPOS_MAPEO_EXCEL_OBLIGATORIOS as $campo) {
                if (!isset($mapeo[$campo]['col']) || !is_numeric($mapeo[$campo]['col'])) {
                    throw new \Exception("Falta indicar en qué columna está el campo \"{$campo}\" del extracto.");
                }
            }
            return;
        }

        // PDF: no hay columnas fijas — un único patrón (regex) reconoce la línea de datos
        // que cierra cada movimiento (ver ConciliacionImportService::parsearPdf).
        $regex = trim((string) ($mapeo['regex_linea'] ?? ''));
        if ($regex === '') {
            throw new \Exception('Debe indicar el patrón (regex) de línea de datos del PDF.');
        }
        if (@preg_match($regex, '') === false) {
            throw new \Exception('El patrón (regex) de línea de datos no es válido.');
        }
        if (!str_contains($regex, '?<fecha>') && !str_contains($regex, "?P<fecha>")) {
            throw new \Exception('El patrón debe incluir el grupo nombrado (?<fecha>...).');
        }
        if (!str_contains($regex, '?<monto>') && !str_contains($regex, "?P<monto>")) {
            throw new \Exception('El patrón debe incluir el grupo nombrado (?<monto>...).');
        }
    }

    public function validarCarga(array $data): void
    {
        if (empty($data['id_forma_pago'])) {
            throw new \Exception('Debe seleccionar la cuenta bancaria que recibió el cobro.');
        }
        if (empty($data['id_perfil'])) {
            throw new \Exception('Debe seleccionar un perfil de mapeo de columnas.');
        }
    }

    /**
     * Valida el ajuste manual de una línea (confirmación o corrección de la sugerencia).
     * $saldoPendienteDocumento es el saldo pendiente ACTUAL del documento elegido (recalculado
     * en el momento de confirmar, ver ConciliacionCobrosService::confirmarLinea); si se indica,
     * el monto a aplicar no puede superarlo (no se puede cobrar más de lo que el documento debe).
     */
    public function validarMatchLinea(array $data, float $montoLinea, ?float $saldoPendienteDocumento = null): void
    {
        if (empty($data['id_cliente'])) {
            throw new \Exception('Debe indicar el cliente de la línea.');
        }
        $tipoDoc = strtoupper((string) ($data['tipo_documento'] ?? ''));
        if (!in_array($tipoDoc, ['FACTURA', 'SALDO_INICIAL', 'RECIBO'], true)) {
            throw new \Exception('Debe seleccionar el documento (factura/saldo inicial/recibo) a cobrar.');
        }
        if (empty($data['id_documento'])) {
            throw new \Exception('Debe seleccionar el documento a cobrar.');
        }

        $montoAplicar = (float) ($data['monto_aplicar'] ?? 0);
        if ($montoAplicar <= 0) {
            throw new \Exception('El monto a aplicar debe ser mayor a cero.');
        }
        if ($montoAplicar > $montoLinea + 0.01) {
            throw new \Exception('El monto a aplicar no puede ser mayor al monto de la línea del banco.');
        }
        if ($saldoPendienteDocumento !== null && $montoAplicar > $saldoPendienteDocumento + 0.01) {
            throw new \Exception('El monto a aplicar ($' . number_format($montoAplicar, 2) . ') no puede ser mayor al saldo pendiente del documento ($' . number_format($saldoPendienteDocumento, 2) . ').');
        }
    }
}
