<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Parsea un string de búsqueda con sintaxis de tokens tipo "clave:valor"
 * y lo convierte en un array estructurado de filtros + texto libre.
 *
 * Sintaxis soportada:
 *   clave:valor              → filtro simple
 *   clave:"valor con espacios"
 *   clave:>=2026-01-01       → operador (>=, <=, >, <, =)
 *   clave:2026-01..2026-03   → rango
 *   clave:a,b,c              → lista (IN)
 *   -clave:valor             → negación
 *   texto                    → texto libre (búsqueda en columnas por defecto)
 */
class FiltrosBusqueda
{
    /**
     * Parsea un string a array estructurado.
     *
     * @return array{texto_libre: string, filtros: array<string, array{op: string, valor: mixed, neg: bool}>}
     */
    public static function parsear(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['texto_libre' => '', 'filtros' => []];
        }

        $filtros     = [];
        $textoLibre  = [];

        // Regex: captura opcional "-" + clave + ":" + (valor entre comillas | valor sin espacios)
        $regex = '/(-?)([a-záéíóúñ_]+):("([^"]*)"|([^\s"]+))/iu';
        $offset = 0;

        preg_match_all($regex, $input, $matches, PREG_OFFSET_CAPTURE);

        $rangosTokens = [];
        foreach ($matches[0] as $i => $m) {
            $rangosTokens[] = [$m[1], $m[1] + strlen($m[0])];

            $neg   = $matches[1][$i][0] === '-';
            $clave = strtolower($matches[2][$i][0]);
            $valor = $matches[4][$i][0] !== '' ? $matches[4][$i][0] : $matches[5][$i][0];

            $filtros[$clave] = self::parsearValor($valor, $neg);
        }

        // Lo que no es token = texto libre
        $cursor = 0;
        foreach ($rangosTokens as [$ini, $fin]) {
            if ($cursor < $ini) {
                $textoLibre[] = substr($input, $cursor, $ini - $cursor);
            }
            $cursor = $fin;
        }
        if ($cursor < strlen($input)) {
            $textoLibre[] = substr($input, $cursor);
        }

        $textoLibreStr = trim(preg_replace('/\s+/u', ' ', implode(' ', $textoLibre)) ?? '');

        return [
            'texto_libre' => $textoLibreStr,
            'filtros'     => $filtros,
        ];
    }

    /**
     * Aplica filtros estructurados al WHERE de una consulta.
     *
     * @param string $where      WHERE actual (se modifica por referencia)
     * @param array  $params     Parámetros PDO (se modifica por referencia)
     * @param array  $filtros    Salida de parsear()['filtros']
     * @param array  $mapas      Configuración del módulo:
     *   [
     *     'texto'     => ['clave' => 'columna_sql', ...],   // ILIKE
     *     'exacto'    => ['clave' => 'columna_sql', ...],   // = / IN
     *     'fecha'     => ['clave' => 'columna_sql', ...],   // rangos de fecha
     *     'numerico'  => ['clave' => 'columna_sql', ...],   // = / > / < / BETWEEN
     *   ]
     */
    public static function aplicarFiltros(string &$where, array &$params, array $filtros, array $mapas): void
    {
        $mapaTexto    = $mapas['texto']    ?? [];
        $mapaExacto   = $mapas['exacto']   ?? [];
        $mapaFecha    = $mapas['fecha']    ?? [];
        $mapaNumerico = $mapas['numerico'] ?? [];

        $i = 0;
        foreach ($filtros as $clave => $f) {
            $i++;
            $op    = $f['op'];
            $valor = $f['valor'];
            $neg   = $f['neg'];

            if (isset($mapaTexto[$clave])) {
                self::applyTexto($where, $params, $mapaTexto[$clave], $clave, $i, $op, $valor, $neg);
                continue;
            }
            if (isset($mapaExacto[$clave])) {
                self::applyExacto($where, $params, $mapaExacto[$clave], $clave, $i, $op, $valor, $neg);
                continue;
            }
            if (isset($mapaFecha[$clave])) {
                self::applyFecha($where, $params, $mapaFecha[$clave], $clave, $i, $op, $valor, $neg);
                continue;
            }
            if (isset($mapaNumerico[$clave])) {
                self::applyNumerico($where, $params, $mapaNumerico[$clave], $clave, $i, $op, $valor, $neg);
                continue;
            }
            // Clave desconocida: ignorar silenciosamente
        }
    }

    private static function applyTexto(string &$where, array &$params, string $col, string $clave, int $i, string $op, $valor, bool $neg): void
    {
        $ph = ":f_{$clave}_{$i}";
        if ($op === 'IN' && is_array($valor)) {
            $phs = [];
            foreach ($valor as $k => $v) {
                $p = $ph . '_' . $k;
                $phs[] = $p;
                $params[$p] = $v;
            }
            $where .= ($neg ? ' AND NOT ' : ' AND ') . "$col IN (" . implode(',', $phs) . ')';
        } else {
            $where .= ' AND ' . "$col " . ($neg ? 'NOT ILIKE' : 'ILIKE') . " $ph";
            $params[$ph] = '%' . (is_array($valor) ? implode(' ', $valor) : $valor) . '%';
        }
    }

    private static function applyExacto(string &$where, array &$params, string $col, string $clave, int $i, string $op, $valor, bool $neg): void
    {
        $ph = ":f_{$clave}_{$i}";
        if ($op === 'IN' && is_array($valor)) {
            $phs = [];
            foreach ($valor as $k => $v) {
                $p = $ph . '_' . $k;
                $phs[] = $p;
                $params[$p] = $v;
            }
            $where .= ($neg ? ' AND NOT ' : ' AND ') . "$col IN (" . implode(',', $phs) . ')';
        } else {
            $where .= ' AND ' . "$col " . ($neg ? '!=' : '=') . " $ph";
            $params[$ph] = is_array($valor) ? ($valor[0] ?? '') : $valor;
        }
    }

    private static function applyFecha(string &$where, array &$params, string $col, string $clave, int $i, string $op, $valor, bool $neg): void
    {
        if ($op === 'BETWEEN' && is_array($valor) && count($valor) === 2) {
            $ini = self::normalizarFecha($valor[0], false);
            $fin = self::normalizarFecha($valor[1], true);
            $pi  = ":f_{$clave}_{$i}_i";
            $pf  = ":f_{$clave}_{$i}_f";
            $where .= ($neg ? ' AND NOT ' : ' AND ') . "($col >= $pi AND $col <= $pf)";
            $params[$pi] = $ini;
            $params[$pf] = $fin;
            return;
        }
        $val = is_array($valor) ? ($valor[0] ?? '') : $valor;
        if ($op === 'ILIKE' || $op === '=') {
            $ini = self::normalizarFecha($val, false);
            $fin = self::normalizarFecha($val, true);
            $pi  = ":f_{$clave}_{$i}_i";
            $pf  = ":f_{$clave}_{$i}_f";
            $where .= ($neg ? ' AND NOT ' : ' AND ') . "($col >= $pi AND $col <= $pf)";
            $params[$pi] = $ini;
            $params[$pf] = $fin;
            return;
        }
        $ph = ":f_{$clave}_{$i}";
        $where .= ($neg ? ' AND NOT ' : ' AND ') . "$col $op $ph";
        $params[$ph] = self::normalizarFecha($val, $op === '<=' || $op === '<');
    }

    private static function applyNumerico(string &$where, array &$params, string $col, string $clave, int $i, string $op, $valor, bool $neg): void
    {
        if ($op === 'BETWEEN' && is_array($valor) && count($valor) === 2) {
            $pi  = ":f_{$clave}_{$i}_i";
            $pf  = ":f_{$clave}_{$i}_f";
            $where .= ($neg ? ' AND NOT ' : ' AND ') . "($col >= $pi AND $col <= $pf)";
            $params[$pi] = (float) $valor[0];
            $params[$pf] = (float) $valor[1];
            return;
        }
        $ph = ":f_{$clave}_{$i}";
        $val = is_array($valor) ? ($valor[0] ?? 0) : $valor;
        $opSql = ($op === 'ILIKE') ? '=' : $op;
        $where .= ($neg ? ' AND NOT ' : ' AND ') . "$col $opSql $ph";
        $params[$ph] = (float) $val;
    }

    /**
     * Normaliza fechas parciales a inicio o fin del rango.
     *   2026       → 2026-01-01 / 2026-12-31
     *   2026-03    → 2026-03-01 / 2026-03-31
     *   2026-03-15 → 2026-03-15 00:00:00 / 23:59:59
     */
    public static function normalizarFecha(string $f, bool $finRango): string
    {
        $f = trim($f);
        if (preg_match('/^\d{4}$/', $f)) {
            return $finRango ? "$f-12-31 23:59:59" : "$f-01-01 00:00:00";
        }
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $f, $m)) {
            $y = $m[1]; $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            if ($finRango) {
                $lastDay = date('t', strtotime("$y-$mo-01"));
                return "$y-$mo-$lastDay 23:59:59";
            }
            return "$y-$mo-01 00:00:00";
        }
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $f)) {
            return $finRango ? "$f 23:59:59" : "$f 00:00:00";
        }
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $f, $m)) {
            $f2 = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            return $finRango ? "$f2 23:59:59" : "$f2 00:00:00";
        }
        return $f;
    }

    /**
     * Detecta operador, rango o lista en el valor.
     */
    private static function parsearValor(string $valor, bool $neg): array
    {
        // Rango: 2026-01..2026-03  |  100..500
        if (preg_match('/^(.+?)\.\.(.+)$/', $valor, $m)) {
            return ['op' => 'BETWEEN', 'valor' => [trim($m[1]), trim($m[2])], 'neg' => $neg];
        }
        // Operadores: >=, <=, >, <, =
        if (preg_match('/^(>=|<=|>|<|=)(.+)$/', $valor, $m)) {
            return ['op' => $m[1], 'valor' => trim($m[2]), 'neg' => $neg];
        }
        // Lista: a,b,c
        if (strpos($valor, ',') !== false) {
            $items = array_filter(array_map('trim', explode(',', $valor)), fn($v) => $v !== '');
            return ['op' => 'IN', 'valor' => array_values($items), 'neg' => $neg];
        }
        // Simple
        return ['op' => 'ILIKE', 'valor' => $valor, 'neg' => $neg];
    }
}
