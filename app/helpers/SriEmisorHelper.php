<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Datos del EMISOR (empresa) para el SRI que dependen de su régimen y de si es
 * agente de retención. Se usa tanto en los XML (infoTributaria) como en los RIDE
 * (PDF) de factura, nota de crédito, liquidación de compra y retención de compra.
 *
 * Reglas (definidas con el usuario):
 *  - Leyenda de régimen SOLO para RIMPE (emprendedor o negocio popular); el
 *    régimen general NO se muestra.
 *      · Negocio Popular → "CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE"
 *      · Emprendedor      → "CONTRIBUYENTE RÉGIMEN RIMPE"
 *  - Agente de retención: número de resolución (solo dígitos, máx 8 según XSD).
 */
class SriEmisorHelper
{
    /** Cache id_tipo_regimen => nombre, para no repetir consultas por request. */
    private static array $regimenCache = [];

    /**
     * Leyenda del régimen RIMPE del emisor. '' si es general / no aplica.
     */
    public static function regimenRimpeLeyenda(array $empresa): string
    {
        $nombre = self::regimenNombre($empresa);
        if ($nombre === '') {
            return '';
        }
        $n = self::normalizar($nombre);
        if (str_contains($n, 'NEGOCIO POPULAR')) {
            return 'CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE';
        }
        if (str_contains($n, 'RIMPE') || str_contains($n, 'EMPRENDEDOR')) {
            return 'CONTRIBUYENTE RÉGIMEN RIMPE';
        }
        return ''; // General u otro régimen: no se muestra.
    }

    /**
     * Número de resolución de agente de retención (solo dígitos, máx 8).
     * '' si la empresa no es agente de retención.
     */
    public static function agenteRetencionNumero(array $empresa): string
    {
        $raw = trim((string)($empresa['agente_retencion'] ?? ''));
        if ($raw === '' || in_array(strtoupper($raw), ['0', 'NO', 'N/A'], true)) {
            return '';
        }
        $digitos = preg_replace('/\D/', '', $raw) ?? '';
        if ($digitos === '' || (int)$digitos === 0) {
            return '';
        }
        return substr($digitos, 0, 8);
    }

    /** ¿La empresa es agente de retención? */
    public static function esAgenteRetencion(array $empresa): bool
    {
        return self::agenteRetencionNumero($empresa) !== '';
    }

    /**
     * Nombre del tipo de régimen del emisor. Usa un campo ya resuelto si viene
     * en $empresa; si no, lo busca en el catálogo tipo_regimen por id_tipo_regimen.
     */
    private static function regimenNombre(array $empresa): string
    {
        foreach (['regimen_nombre', 'tipo_regimen_nombre', 'nombre_regimen'] as $k) {
            if (!empty($empresa[$k])) {
                return (string)$empresa[$k];
            }
        }

        $id = (int)($empresa['id_tipo_regimen'] ?? 0);
        if ($id <= 0) {
            return '';
        }
        if (array_key_exists($id, self::$regimenCache)) {
            return self::$regimenCache[$id];
        }

        $nombre = '';
        try {
            $db = \App\core\Database::getConnection();
            // La PK del catálogo es id_tipo_regimen o id según la versión del esquema.
            foreach (
                ['SELECT nombre FROM tipo_regimen WHERE id_tipo_regimen = ?',
                 'SELECT nombre FROM tipo_regimen WHERE id = ?'] as $sql
            ) {
                try {
                    $st = $db->prepare($sql);
                    $st->execute([$id]);
                    $v = $st->fetchColumn();
                    if ($v !== false && $v !== null) {
                        $nombre = (string)$v;
                        break;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        } catch (\Throwable $e) {
            // Sin BD disponible: se deja vacío (no se muestra el régimen).
        }

        self::$regimenCache[$id] = $nombre;
        return $nombre;
    }

    private static function normalizar(string $s): string
    {
        // Quitar acentos (ambos casos) y pasar a mayúsculas SIN depender de mbstring.
        $from = ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'];
        $to   = ['a','e','i','o','u','u','n','A','E','I','O','U','U','N'];
        return strtoupper(str_replace($from, $to, $s));
    }
}
