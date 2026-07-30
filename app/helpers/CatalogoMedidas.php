<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Catálogo por defecto de tipos y unidades de medida.
 *
 * Fuente única de verdad: lo consumen
 *   - App\Services\EmpresaInicializadorService  → siembra el catálogo en cada empresa
 *   - App\controllers\ImportadorExcelController → hoja "Catalogo_Sugerido" de la plantilla
 *
 * Reglas del catálogo:
 *   - El CÓDIGO de unidad es único en TODA la empresa, no solo dentro del tipo.
 *     Al importar productos, la unidad se resuelve solo por código
 *     (ImportadorExcelService::resolverUnidadMedida), así que dos unidades con el
 *     mismo código en tipos distintos harían ambigua la asignación.
 *   - factor_base = cuántas unidades base equivale 1 de esta unidad
 *     (1 lb = 0.453592 kg → factor_base = 0.453592). La unidad base vale 1.
 *   - Solo una unidad con es_base = true por tipo + empresa (índice único parcial).
 *   - 'sinonimos' se usa al sembrar: si la empresa ya tiene un tipo equivalente
 *     (p. ej. "MASA" en vez de "PESO"), se reutiliza en lugar de duplicarlo.
 *
 * Equivalencias de uso ecuatoriano: arroba = 25 lb (11.3398 kg) y
 * quintal = 100 lb (45.3592 kg), no el quintal métrico de 100 kg.
 */
class CatalogoMedidas
{
    /**
     * @return array<int, array{codigo:string,nombre:string,sinonimos:array<int,string>,unidades:array<int,array{codigo:string,nombre:string,abreviatura:string,factor_base:float,es_base:bool}>}>
     */
    public static function getCatalogo(): array
    {
        return [
            [
                'codigo'    => 'CANT',
                'nombre'    => 'CANTIDAD',
                'sinonimos' => ['CANTIDAD', 'UNIDAD', 'UNIDADES', 'CONTEO'],
                'unidades'  => [
                    ['codigo' => 'UND', 'nombre' => 'UNIDAD', 'abreviatura' => 'u',   'factor_base' => 1.0,    'es_base' => true],
                    ['codigo' => 'PAR', 'nombre' => 'PAR',    'abreviatura' => 'par', 'factor_base' => 2.0,    'es_base' => false],
                    ['codigo' => 'DOC', 'nombre' => 'DOCENA', 'abreviatura' => 'doc', 'factor_base' => 12.0,   'es_base' => false],
                    ['codigo' => 'CTO', 'nombre' => 'CIENTO', 'abreviatura' => 'cto', 'factor_base' => 100.0,  'es_base' => false],
                    ['codigo' => 'MLL', 'nombre' => 'MILLAR', 'abreviatura' => 'mll', 'factor_base' => 1000.0, 'es_base' => false],
                ],
            ],
            [
                'codigo'    => 'PESO',
                'nombre'    => 'PESO',
                'sinonimos' => ['PESO', 'MASA'],
                'unidades'  => [
                    ['codigo' => 'MG', 'nombre' => 'MILIGRAMO',        'abreviatura' => 'mg', 'factor_base' => 0.000001,  'es_base' => false],
                    ['codigo' => 'G',  'nombre' => 'GRAMO',            'abreviatura' => 'g',  'factor_base' => 0.001,     'es_base' => false],
                    ['codigo' => 'OZ', 'nombre' => 'ONZA',             'abreviatura' => 'oz', 'factor_base' => 0.028350,  'es_base' => false],
                    ['codigo' => 'LB', 'nombre' => 'LIBRA',            'abreviatura' => 'lb', 'factor_base' => 0.453592,  'es_base' => false],
                    ['codigo' => 'KG', 'nombre' => 'KILOGRAMO',        'abreviatura' => 'kg', 'factor_base' => 1.0,       'es_base' => true],
                    ['codigo' => 'AR', 'nombre' => 'ARROBA',           'abreviatura' => '@',  'factor_base' => 11.339800, 'es_base' => false],
                    ['codigo' => 'QQ', 'nombre' => 'QUINTAL',          'abreviatura' => 'qq', 'factor_base' => 45.359200, 'es_base' => false],
                    ['codigo' => 'TN', 'nombre' => 'TONELADA MÉTRICA', 'abreviatura' => 't',  'factor_base' => 1000.0,    'es_base' => false],
                ],
            ],
            [
                'codigo'    => 'VOL',
                'nombre'    => 'VOLUMEN',
                'sinonimos' => ['VOLUMEN', 'CAPACIDAD'],
                'unidades'  => [
                    ['codigo' => 'ML',  'nombre' => 'MILILITRO',     'abreviatura' => 'ml',  'factor_base' => 0.001,     'es_base' => false],
                    ['codigo' => 'LT',  'nombre' => 'LITRO',         'abreviatura' => 'l',   'factor_base' => 1.0,       'es_base' => true],
                    ['codigo' => 'GAL', 'nombre' => 'GALÓN',         'abreviatura' => 'gal', 'factor_base' => 3.785412,  'es_base' => false],
                    ['codigo' => 'CAN', 'nombre' => 'CANECA',        'abreviatura' => 'can', 'factor_base' => 18.9271,   'es_base' => false],
                    ['codigo' => 'M3',  'nombre' => 'METRO CÚBICO',  'abreviatura' => 'm³',  'factor_base' => 1000.0,    'es_base' => false],
                ],
            ],
            [
                'codigo'    => 'LONG',
                'nombre'    => 'LONGITUD',
                'sinonimos' => ['LONGITUD', 'DISTANCIA'],
                'unidades'  => [
                    ['codigo' => 'MM',   'nombre' => 'MILÍMETRO',  'abreviatura' => 'mm', 'factor_base' => 0.001,    'es_base' => false],
                    ['codigo' => 'CM',   'nombre' => 'CENTÍMETRO', 'abreviatura' => 'cm', 'factor_base' => 0.01,     'es_base' => false],
                    ['codigo' => 'PULG', 'nombre' => 'PULGADA',    'abreviatura' => 'in', 'factor_base' => 0.025400, 'es_base' => false],
                    ['codigo' => 'PIE',  'nombre' => 'PIE',        'abreviatura' => 'ft', 'factor_base' => 0.304800, 'es_base' => false],
                    ['codigo' => 'YD',   'nombre' => 'YARDA',      'abreviatura' => 'yd', 'factor_base' => 0.914400, 'es_base' => false],
                    ['codigo' => 'MT',   'nombre' => 'METRO',      'abreviatura' => 'm',  'factor_base' => 1.0,      'es_base' => true],
                    ['codigo' => 'KM',   'nombre' => 'KILÓMETRO',  'abreviatura' => 'km', 'factor_base' => 1000.0,   'es_base' => false],
                ],
            ],
            [
                'codigo'    => 'AREA',
                'nombre'    => 'ÁREA',
                'sinonimos' => ['AREA', 'ÁREA', 'SUPERFICIE'],
                'unidades'  => [
                    ['codigo' => 'CM2',  'nombre' => 'CENTÍMETRO CUADRADO', 'abreviatura' => 'cm²', 'factor_base' => 0.000100, 'es_base' => false],
                    ['codigo' => 'M2',   'nombre' => 'METRO CUADRADO',      'abreviatura' => 'm²',  'factor_base' => 1.0,      'es_base' => true],
                    ['codigo' => 'PIE2', 'nombre' => 'PIE CUADRADO',        'abreviatura' => 'ft²', 'factor_base' => 0.092903, 'es_base' => false],
                    ['codigo' => 'HA',   'nombre' => 'HECTÁREA',            'abreviatura' => 'ha',  'factor_base' => 10000.0,  'es_base' => false],
                ],
            ],
            [
                'codigo'    => 'TIEMPO',
                'nombre'    => 'TIEMPO',
                'sinonimos' => ['TIEMPO', 'DURACION', 'DURACIÓN'],
                'unidades'  => [
                    ['codigo' => 'MIN', 'nombre' => 'MINUTO',         'abreviatura' => 'min', 'factor_base' => 0.016667, 'es_base' => false],
                    ['codigo' => 'HR',  'nombre' => 'HORA',           'abreviatura' => 'h',   'factor_base' => 1.0,      'es_base' => true],
                    ['codigo' => 'DIA', 'nombre' => 'DÍA',            'abreviatura' => 'día', 'factor_base' => 24.0,     'es_base' => false],
                    ['codigo' => 'SEM', 'nombre' => 'SEMANA',         'abreviatura' => 'sem', 'factor_base' => 168.0,    'es_base' => false],
                    ['codigo' => 'MES', 'nombre' => 'MES (30 DÍAS)',  'abreviatura' => 'mes', 'factor_base' => 720.0,    'es_base' => false],
                ],
            ],
            [
                // Presentaciones comerciales. NO son convertibles entre sí:
                // una caja no equivale a un saco. Van con factor 1 a propósito.
                'codigo'    => 'EMP',
                'nombre'    => 'EMPAQUE',
                'sinonimos' => ['EMPAQUE', 'PRESENTACION', 'PRESENTACIÓN', 'EMBALAJE'],
                'unidades'  => [
                    ['codigo' => 'CJA', 'nombre' => 'CAJA',    'abreviatura' => 'caja',  'factor_base' => 1.0, 'es_base' => true],
                    ['codigo' => 'PAQ', 'nombre' => 'PAQUETE', 'abreviatura' => 'paq',   'factor_base' => 1.0, 'es_base' => false],
                    ['codigo' => 'FDA', 'nombre' => 'FUNDA',   'abreviatura' => 'fda',   'factor_base' => 1.0, 'es_base' => false],
                    ['codigo' => 'SAC', 'nombre' => 'SACO',    'abreviatura' => 'saco',  'factor_base' => 1.0, 'es_base' => false],
                    ['codigo' => 'ROL', 'nombre' => 'ROLLO',   'abreviatura' => 'rollo', 'factor_base' => 1.0, 'es_base' => false],
                    ['codigo' => 'JGO', 'nombre' => 'JUEGO',   'abreviatura' => 'jgo',   'factor_base' => 1.0, 'es_base' => false],
                ],
            ],
        ];
    }

    /**
     * Normaliza un texto para comparar códigos y nombres:
     * mayúsculas, sin acentos y sin espacios sobrantes.
     */
    public static function normalizar(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor), 'UTF-8');

        return strtr($valor, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'Ñ' => 'N',
        ]);
    }
}
