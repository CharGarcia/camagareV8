<?php

declare(strict_types=1);

namespace App\Rules;

use App\repositories\modulos\ConciliacionTarjetasRepository;

/**
 * Validaciones del catálogo global de Perfiles de lectura del estado de cuenta de las
 * procesadoras de tarjeta (config/conciliacion-tarjetas-perfiles), que usa
 * Conciliación de Tarjetas al cargar el archivo.
 */
class ConciliacionTarjetasPerfilRules
{
    /** Campos que el mapeo de un archivo Excel/CSV debe traer sí o sí. */
    private const CAMPOS_MAPEO_OBLIGATORIOS = ['fecha', 'monto_bruto'];

    public function validarPerfil(array $data): void
    {
        if (trim((string) ($data['nombre_perfil'] ?? '')) === '') {
            throw new \Exception('Debe indicar un nombre para el perfil.');
        }

        $procesadora = $data['tipo_procesadora'] ?? null;
        if ($procesadora !== null && !in_array($procesadora, ConciliacionTarjetasRepository::TIPOS_LIQUIDACION_DIFERIDA, true)) {
            throw new \Exception('La procesadora del perfil debe ser Payphone, Nuvei o Tarjeta (datáfono).');
        }

        $tipo = strtoupper((string) ($data['tipo_archivo'] ?? ''));
        if (!in_array($tipo, ['EXCEL', 'CSV', 'PDF'], true)) {
            throw new \Exception('El tipo de archivo del perfil debe ser EXCEL, CSV o PDF.');
        }

        $nivel = (string) ($data['nivel'] ?? '');
        if (!in_array($nivel, ['transaccion', 'deposito'], true)) {
            throw new \Exception('Indique si el archivo trae una línea por transacción o los depósitos consolidados.');
        }

        $mapeo = $data['mapeo_columnas'] ?? null;
        if (!is_array($mapeo) || empty($mapeo)) {
            throw new \Exception('Debe configurar el mapeo de columnas del perfil.');
        }

        if ($tipo === 'PDF') {
            $regex = trim((string) ($mapeo['regex_linea'] ?? ''));
            if ($regex === '') {
                throw new \Exception('Debe indicar el patrón (regex) de línea de datos del PDF.');
            }
            if (@preg_match($regex, '') === false) {
                throw new \Exception('El patrón (regex) de línea de datos no es válido.');
            }
            foreach (self::CAMPOS_MAPEO_OBLIGATORIOS as $campo) {
                if (!str_contains($regex, "?<{$campo}>") && !str_contains($regex, "?P<{$campo}>")) {
                    throw new \Exception("El patrón debe incluir el grupo nombrado (?<{$campo}>...).");
                }
            }
            return;
        }

        foreach (self::CAMPOS_MAPEO_OBLIGATORIOS as $campo) {
            if (!isset($mapeo[$campo]['col']) || !is_numeric($mapeo[$campo]['col'])) {
                throw new \Exception("Falta indicar en qué columna está el campo \"{$campo}\" del estado de cuenta.");
            }
        }
    }

    /**
     * Al cargar el estado de cuenta: el perfil elegido debe servir para la procesadora
     * de la conciliación (mismo criterio que ConciliacionTarjetasPerfilRepository::getActivosPara).
     */
    public function validarPerfilParaProcesadora(array $perfil, string $tipoProcesadora, ?int $idBanco): void
    {
        $tipoPerfil = $perfil['tipo_procesadora'] ?? null;
        if ($tipoPerfil !== null && $tipoPerfil !== strtoupper($tipoProcesadora)) {
            throw new \Exception('El perfil "' . $perfil['nombre_perfil'] . '" es para otra procesadora.');
        }

        $bancoPerfil = !empty($perfil['id_banco']) ? (int) $perfil['id_banco'] : null;
        if ($bancoPerfil !== null && $idBanco !== null && $bancoPerfil !== $idBanco) {
            throw new \Exception('El perfil "' . $perfil['nombre_perfil'] . '" es para el estado de cuenta de otro banco.');
        }
    }
}
