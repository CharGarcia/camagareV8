<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Validaciones del catálogo global de Perfiles de Mapeo de extractos bancarios
 * (config/conciliacion-perfiles), que usa Conciliación de Cobros al subir un extracto.
 */
class ConciliacionPerfilRules
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
            foreach (['referencia', 'descripcion_extra', 'tipo'] as $campo) {
                if (isset($mapeo[$campo]['col']) && (!is_numeric($mapeo[$campo]['col']) || (int) $mapeo[$campo]['col'] < 0)) {
                    throw new \Exception("La columna del campo \"{$campo}\" no es válida.");
                }
            }
            // Columna de tipo sin valor de crédito (o al revés) no filtraría nada: se exige el par completo.
            $tieneTipo = isset($mapeo['tipo']['col']);
            $tieneCredito = trim((string) ($mapeo['tipo_credito'] ?? '')) !== '';
            if ($tieneTipo !== $tieneCredito) {
                throw new \Exception('Para filtrar solo los créditos indique la columna de tipo y el valor que identifica un crédito (p. ej. "+" o "C").');
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
}
