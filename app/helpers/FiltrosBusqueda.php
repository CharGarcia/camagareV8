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
    /** Caché de si la extensión `unaccent` está disponible en esta conexión (una consulta por request). */
    private static ?bool $unaccentDisponible = null;

    private static function unaccentDisponible(): bool
    {
        if (self::$unaccentDisponible === null) {
            // Caché compartida (APCu, 1 h): antes era una consulta a pg_proc en CADA petición
            // que buscaba — con la BD en otro servidor, eso es un viaje de red extra por
            // búsqueda. La extensión no aparece ni desaparece sola; si se instala, se nota
            // al vencer la caché. Sin APCu, Cache::get() devuelve null y se consulta como antes.
            $cache = \App\Helpers\Cache::get('filtros_busqueda:unaccent');
            if ($cache !== null) {
                self::$unaccentDisponible = (bool) $cache;
                return self::$unaccentDisponible;
            }
            try {
                $db = \App\core\Database::getConnection();
                self::$unaccentDisponible = (bool) $db
                    ->query("SELECT EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'unaccent')")
                    ->fetchColumn();
                \App\Helpers\Cache::set('filtros_busqueda:unaccent', self::$unaccentDisponible ? 1 : 0, 3600);
            } catch (\Throwable $e) {
                self::$unaccentDisponible = false;
            }
        }
        return self::$unaccentDisponible;
    }

    /**
     * Condiciones para columnas "caras o numéricas" del texto libre (ver condicionTexto):
     * la columna solo se evalúa si la PALABRA escrita cumple el patrón.
     *
     *  - SI_DIGITOS: la palabra tiene al menos un dígito (fechas, montos, números). Buscar
     *    "garcia" nunca va a coincidir con "34.78" ni con "2026-09-15", así que no tiene
     *    sentido convertir esas columnas a texto en cada fila.
     *  - SI_DECIMAL: la palabra parece un monto con decimales ("34.78", "34,78"). Para
     *    columnas CALCULADAS con subconsultas por fila (saldo, abonos): son lo más caro
     *    de la búsqueda y solo tiene sentido pagarlo cuando se busca un valor.
     */
    public const SI_DIGITOS = '/\d/';
    public const SI_DECIMAL = '/^\d+[.,]\d{1,2}$/';

    /**
     * Envuelve una columna o placeholder con unaccent() si la extensión está
     * instalada; si no, lo deja tal cual (degradación segura: sin la extensión
     * la búsqueda sigue funcionando, solo que sensible a tildes).
     */
    private static function envolverUnaccent(string $expr): string
    {
        return self::unaccentDisponible() ? "unaccent({$expr})" : $expr;
    }

    /**
     * Condición SQL ESTÁNDAR para "texto libre": todas las PALABRAS escritas deben
     * aparecer, en cualquier orden, en alguna de las columnas dadas — insensible a
     * mayúsculas/minúsculas y (si la extensión `unaccent` está instalada) también a
     * tildes/diéresis/eñe. Es la forma correcta de armar cualquier buscador de texto
     * libre en el sistema; no concatenar ILIKE a mano con el texto completo como una
     * sola frase (eso solo encuentra coincidencias exactas y contiguas: buscar
     * "kit 256" no encontraría "KIT XXXXHHH 256").
     *
     * Rendimiento (2026-09-16), sin cambiar qué encuentra:
     *  - Las columnas simples se CONCATENAN y se buscan con un solo unaccent() + ILIKE por
     *    palabra (antes: un unaccent + ILIKE por columna, por fila, por palabra). Las
     *    palabras nunca tienen espacios, y el separador es un espacio, así que una palabra
     *    no puede "coincidir" uniendo el final de una columna con el inicio de otra.
     *  - Las expresiones con subconsulta (`(SELECT ...)`, p. ej. STRING_AGG de las líneas)
     *    van aparte y AL FINAL del OR: si la fila ya coincidió por una columna simple,
     *    PostgreSQL no las evalúa.
     *  - El patrón se calcula una sola vez por consulta: `(SELECT unaccent(:ph))`.
     *  - Una columna puede venir como `['sql' => expr, 'si' => self::SI_DIGITOS]`: solo se
     *    evalúa cuando la palabra cumple el patrón (montos, fechas, saldos calculados). Esas
     *    columnas no pasan por unaccent (son números/fechas) y, si la palabra es un monto
     *    con coma decimal ("34,78"), se busca con punto ("34.78"), que es como PostgreSQL
     *    convierte un numeric a texto.
     *
     * @param array<int, string|array{sql:string, si?:string}> $columnas Columnas o expresiones SQL a buscar
     * @param string   $texto    Texto escrito por el usuario (una o varias palabras)
     * @param array    $params   Se le agregan los parámetros nuevos (por referencia)
     * @param string   $prefijo  Prefijo único de placeholders (evita choques si se llama más de una vez en la misma consulta)
     * @return string Fragmento SQL entre paréntesis, listo para concatenar con " AND "; '' si $texto/$columnas viene vacío
     */
    public static function condicionTexto(array $columnas, string $texto, array &$params, string $prefijo = 'txt'): string
    {
        $texto = trim($texto);
        if ($texto === '' || empty($columnas)) {
            return '';
        }

        $palabras = array_values(array_filter(preg_split('/\s+/u', $texto) ?: [], fn($p) => $p !== ''));
        if (empty($palabras)) {
            return '';
        }

        // Clasificar columnas una sola vez.
        $simples = [];        // se concatenan
        $conSubconsulta = []; // van aparte, al final
        $condicionales = [];  // ['sql' => ..., 'si' => regex]
        foreach ($columnas as $col) {
            if (is_array($col)) {
                if (!empty($col['sql'])) {
                    $condicionales[] = ['sql' => (string) $col['sql'], 'si' => (string) ($col['si'] ?? '')];
                }
                continue;
            }
            $col = (string) $col;
            if ($col === '') {
                continue;
            }
            if (preg_match('/\(\s*select\b/i', $col)) {
                $conSubconsulta[] = $col;
            } else {
                $simples[] = $col;
            }
        }

        $conUnaccent = self::unaccentDisponible();
        $condicionesPalabras = [];
        foreach ($palabras as $i => $palabra) {
            $ph = ":{$prefijo}_{$i}";
            $params[$ph] = '%' . $palabra . '%';
            $patron = $conUnaccent ? "(SELECT unaccent({$ph}))" : $ph;
            $ors = [];

            if (count($simples) === 1) {
                $ors[] = ($conUnaccent ? "unaccent({$simples[0]})" : $simples[0]) . " ILIKE {$patron}";
            } elseif (count($simples) > 1) {
                $concat = "CONCAT_WS(' ', " . implode(', ', $simples) . ')';
                $ors[] = ($conUnaccent ? "unaccent({$concat})" : $concat) . " ILIKE {$patron}";
            }

            // Condicionales baratas antes que las subconsultas; las caras (saldo) se
            // declaran con SI_DECIMAL, así que casi nunca se evalúan.
            $phNum = null;
            foreach ($condicionales as $c) {
                if ($c['si'] !== '' && !preg_match($c['si'], $palabra)) {
                    continue;
                }
                if ($phNum === null) {
                    $normal = preg_match('/^\d+,\d{1,2}$/', $palabra) ? str_replace(',', '.', $palabra) : $palabra;
                    if ($normal === $palabra) {
                        $phNum = $ph;
                    } else {
                        $phNum = ":{$prefijo}_{$i}_n";
                        $params[$phNum] = '%' . $normal . '%';
                    }
                }
                $ors[] = "({$c['sql']})::text ILIKE {$phNum}";
            }

            foreach ($conSubconsulta as $col) {
                $ors[] = ($conUnaccent ? "unaccent({$col})" : $col) . " ILIKE {$patron}";
            }

            if (empty($ors)) {
                // Ninguna columna aplica a esta palabra (p. ej. solo columnas numéricas y
                // una palabra sin dígitos): no puede coincidir.
                $ors[] = 'FALSE';
            }
            $sqlPalabra = '(' . implode(' OR ', $ors) . ')';
            // PDO (pgsql) lanza HY093 si se liga un parámetro que la consulta no usa.
            if (!preg_match('/' . preg_quote($ph, '/') . '(?![A-Za-z0-9_])/', $sqlPalabra)) {
                unset($params[$ph]);
            }
            $condicionesPalabras[] = $sqlPalabra;
        }

        return '(' . implode(' AND ', $condicionesPalabras) . ')';
    }

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
     *     'existe'    => ['clave' => [                        // condición sobre una tabla hija
     *         'sql'  => 'EXISTS (SELECT 1 FROM ingresos_pagos p WHERE p.id_ingreso = i.id AND {cond})',
     *         'col'  => 'p.id_forma_cobro',                   // columna de la tabla hija
     *         'tipo' => 'exacto',                              // texto | exacto | fecha | numerico
     *     ], ...],
     *   ]
     *
     * `existe`: para filtrar la cabecera por algo que vive en un detalle (formas de
     * cobro, documentos cobrados, etc.). `{cond}` se reemplaza por la condición
     * armada con las mismas reglas del tipo indicado (ILIKE por palabras, =/IN,
     * rangos de fecha o numéricos). La negación (`-clave:valor`) niega el EXISTS
     * completo: "ningún pago con esa forma", no "algún pago con otra forma".
     */
    public static function aplicarFiltros(string &$where, array &$params, array $filtros, array $mapas): void
    {
        $mapaTexto    = $mapas['texto']    ?? [];
        $mapaExacto   = $mapas['exacto']   ?? [];
        $mapaFecha    = $mapas['fecha']    ?? [];
        $mapaNumerico = $mapas['numerico'] ?? [];
        $mapaExiste   = $mapas['existe']   ?? [];

        $i = 0;
        foreach ($filtros as $clave => $f) {
            $i++;
            $op    = $f['op'];
            $valor = $f['valor'];
            $neg   = $f['neg'];

            if (isset($mapaExiste[$clave])) {
                self::applyExiste($where, $params, $mapaExiste[$clave], $clave, $i, $op, $valor, $neg);
                continue;
            }
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

    /**
     * Filtro sobre una tabla hija (ver doc de aplicarFiltros, clave 'existe'): arma la
     * condición interna con el tipo indicado y la incrusta en el `{cond}` del SQL.
     */
    private static function applyExiste(string &$where, array &$params, array $cfg, string $clave, int $i, string $op, $valor, bool $neg): void
    {
        $sql  = (string) ($cfg['sql'] ?? '');
        $col  = (string) ($cfg['col'] ?? '');
        $tipo = (string) ($cfg['tipo'] ?? 'exacto');
        if ($sql === '' || $col === '' || strpos($sql, '{cond}') === false) {
            return;
        }

        // La condición interna se arma sin negación (la negación aplica al EXISTS).
        $inner = '';
        switch ($tipo) {
            case 'texto':    self::applyTexto($inner, $params, $col, $clave, $i, $op, $valor, false);    break;
            case 'fecha':    self::applyFecha($inner, $params, $col, $clave, $i, $op, $valor, false);    break;
            case 'numerico': self::applyNumerico($inner, $params, $col, $clave, $i, $op, $valor, false); break;
            default:         self::applyExacto($inner, $params, $col, $clave, $i, $op, $valor, false);   break;
        }
        $inner = trim((string) preg_replace('/^\s*AND\s+/i', '', $inner));
        if ($inner === '') {
            return;
        }

        $where .= ($neg ? ' AND NOT ' : ' AND ') . '(' . str_replace('{cond}', $inner, $sql) . ')';
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
            return;
        }

        // Igual regla que el texto libre: todas las palabras deben aparecer (en
        // cualquier orden) e insensible a tildes, no la frase completa y pegada.
        $texto = is_array($valor) ? implode(' ', $valor) : (string) $valor;
        $condicion = self::condicionTexto([$col], $texto, $params, "f_{$clave}_{$i}");
        if ($condicion === '') {
            return;
        }
        $where .= ($neg ? ' AND NOT ' : ' AND ') . $condicion;
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
