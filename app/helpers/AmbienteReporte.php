<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Ambiente de los REPORTES: siempre producción.
 *
 * Regla del sistema (decisión del usuario, 08-10-2026): los reportes y consultas de solo
 * lectura muestran únicamente documentos del ambiente de PRODUCCIÓN del SRI
 * (`tipo_ambiente = '2'`), sin importar en qué ambiente esté configurada la empresa en ese
 * momento. Los documentos emitidos en pruebas (`'1'`) no tienen validez tributaria y no deben
 * aparecer en reportes, declaraciones de renta ni estados financieros.
 *
 * Alcance:
 *  - SÍ: reportes de solo lectura (Reporte de Ventas, Compras, Retenciones, Cartera,
 *    Inventarios, Cuentas por Cobrar/Pagar, Estados Financieros, Mayores, Balance de
 *    comprobación, ATS, Declaración de Renta, etc.).
 *  - NO: los listados operativos de cada módulo (Facturas, Compras…), que siguen mostrando
 *    el ambiente actual de la empresa para poder trabajar en pruebas; ni las declaraciones
 *    que GUARDAN documento (Declaración de IVA, Retenciones F103, Dividendos) ni Auditoría
 *    contable, que registran sus propios datos con el ambiente de la empresa.
 *  - Nunca en INSERT/UPDATE: los documentos se siguen guardando con el ambiente real.
 *
 * Códigos SRI: 1 = pruebas, 2 = producción.
 */
final class AmbienteReporte
{
    public const PRUEBAS    = '1';
    public const PRODUCCION = '2';

    /** Condición SQL: `alias.tipo_ambiente = '2'` (o sin alias). */
    public static function condicion(string $alias = ''): string
    {
        return ($alias !== '' ? $alias . '.' : '') . "tipo_ambiente = '" . self::PRODUCCION . "'";
    }

    /** Literal SQL del ambiente de producción: `'2'`. */
    public static function literal(): string
    {
        return "'" . self::PRODUCCION . "'";
    }
}
