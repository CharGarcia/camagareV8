<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Motor de búsqueda de texto libre por CONJUNTOS indexables.
 *
 * Por qué existe (medido el 17-09-2026 con datos de producción y con volúmenes reales
 * reproducidos en local): `FiltrosBusqueda::condicionTexto()` arma una condición que se
 * evalúa FILA POR FILA — concatena las columnas del documento y las de sus tablas
 * relacionadas (cliente, responsable, usuario, líneas), les quita las tildes y compara con
 * ILIKE. Ninguna de esas operaciones puede usar un índice, así que cada tecla recorre todos
 * los documentos de la empresa: en producción, una búsqueda en Pedidos leía ~21 GB y tardaba
 * 4,3 s, y una en Facturas ~0,7 GB y 0,5 s de media.
 *
 * Este motor arma la MISMA condición lógica (todas las palabras, en cualquier orden, en
 * alguna de las fuentes; insensible a mayúsculas y tildes) pero como una unión de
 * CONJUNTOS: por cada palabra, un OR de subconsultas NO correlacionadas del tipo
 * `p.id IN (SELECT … WHERE f_unaccent(<expr>) ILIKE f_unaccent(:palabra))`. PostgreSQL
 * resuelve cada una UNA sola vez con un índice GIN trigram (`pg_trgm`) y después cada fila
 * solo consulta el conjunto ya armado.
 *
 * Requisitos en la base (ver database/…_busqueda_trigram.sql):
 *   - extensión `pg_trgm`
 *   - función `f_unaccent(text)` declarada IMMUTABLE (unaccent() es STABLE y no se puede
 *     usar en un índice)
 *   - un índice GIN por cada fuente con `indice` declarado (sqlIndices() los genera)
 *
 * DEGRADACIÓN SEGURA: si la función `f_unaccent` todavía no existe (código desplegado antes
 * de correr el SQL), se usa `unaccent()`; si tampoco está la extensión, se compara sin quitar
 * tildes. La búsqueda sigue devolviendo lo mismo, solo que sin índice. Por eso el orden de
 * despliegue (git pull / SQL) no importa.
 *
 * Forma de una fuente:
 *   [
 *     'sql'    => "p.id IN (SELECT d.id_pedido FROM … WHERE … AND {cond})",  // opcional:
 *                 // sin 'sql', la condición se evalúa por fila sobre la tabla base
 *     'expr'   => "pr.codigo || ' ' || pr.nombre",   // expresión SIN f_unaccent (la pone el motor)
 *     'si'     => FiltrosBusqueda::SI_DIGITOS,       // opcional: solo si la palabra cumple el patrón
 *     'crudo'  => true,                              // opcional: compara ({expr})::text sin quitar tildes
 *                                                    //   (columnas numéricas o de fecha)
 *     'indice' => ['tabla' => 'productos', 'nombre' => 'idx_trgm_productos',
 *                  'expr'  => "codigo || ' ' || nombre"],  // misma expresión, sin alias
 *   ]
 *
 * La expresión de la consulta y la del índice SALEN DE LA MISMA declaración del repositorio,
 * así que no se pueden desalinear: si cambia una, `sqlIndices()` genera el índice nuevo.
 */
final class MotorBusqueda
{
    /** @var array{f:bool,u:bool}|null ¿Existen f_unaccent() y unaccent()? (una consulta por request) */
    private static ?array $funciones = null;

    /**
     * Qué funciones hay en la base. Se resuelve una vez por request y se cachea 1 h en APCu
     * (una función no aparece ni desaparece sola; al instalarla se nota al vencer la caché).
     *
     * @return array{f:bool,u:bool}
     */
    private static function funciones(): array
    {
        if (self::$funciones !== null) {
            return self::$funciones;
        }
        $cache = Cache::get('motor_busqueda:funciones');
        if (is_array($cache)) {
            return self::$funciones = $cache;
        }
        try {
            $db = \App\core\Database::getConnection();
            $row = $db->query("SELECT EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'f_unaccent') AS f,
                                      EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'unaccent')   AS u")
                      ->fetch(\PDO::FETCH_ASSOC);
            self::$funciones = ['f' => !empty($row['f']), 'u' => !empty($row['u'])];
            Cache::set('motor_busqueda:funciones', self::$funciones, 3600);
        } catch (\Throwable $e) {
            self::$funciones = ['f' => false, 'u' => false];
        }
        return self::$funciones;
    }

    /** ¿Se puede usar el índice trigram? (existe la función inmutable). */
    public static function fUnaccentDisponible(): bool
    {
        return self::funciones()['f'];
    }

    /** Envuelve una expresión con la función que corresponda (ver degradación segura). */
    public static function sinTildes(string $expr): string
    {
        $fn = self::funciones();
        if ($fn['f']) {
            return "f_unaccent({$expr})";
        }
        // Sin f_unaccent no hay índice posible; se mantiene el comportamiento actual.
        return $fn['u'] ? "unaccent({$expr})" : $expr;
    }

    /**
     * Condición SQL para el texto libre: todas las palabras deben aparecer (en cualquier
     * orden) en alguna de las fuentes. Devuelve '' si no hay texto o no hay fuentes.
     *
     * @param array<int, array{sql?:string, expr:string, si?:string, crudo?:bool, indice?:array}> $fuentes
     * @param array $params Se le agregan los parámetros nuevos (por referencia)
     */
    public static function condicion(array $fuentes, string $texto, array &$params, string $prefijo = 'mb'): string
    {
        $texto = trim($texto);
        if ($texto === '' || $fuentes === []) {
            return '';
        }
        $palabras = array_values(array_filter(preg_split('/\s+/u', $texto) ?: [], fn($p) => $p !== ''));
        if ($palabras === []) {
            return '';
        }

        $condiciones = [];
        foreach ($palabras as $i => $palabra) {
            $ph = ":{$prefijo}_{$i}";
            $params[$ph] = '%' . $palabra . '%';
            // Monto escrito con coma decimal ("34,78"): las columnas numéricas se comparan
            // como texto con punto, que es como PostgreSQL las convierte.
            $phCrudo = $ph;
            if (preg_match('/^\d+,\d{1,2}$/', $palabra)) {
                $phCrudo = ":{$prefijo}_{$i}_n";
                $params[$phCrudo] = '%' . str_replace(',', '.', $palabra) . '%';
            }

            $ors = [];
            foreach ($fuentes as $f) {
                if (empty($f['expr'])) {
                    continue;
                }
                if (!empty($f['si']) && !preg_match((string) $f['si'], $palabra)) {
                    continue;
                }
                $cond = !empty($f['crudo'])
                    ? "({$f['expr']})::text ILIKE {$phCrudo}"
                    : self::sinTildes((string) $f['expr']) . ' ILIKE ' . self::sinTildes($ph);
                $ors[] = empty($f['sql']) ? "({$cond})" : '(' . str_replace('{cond}', $cond, (string) $f['sql']) . ')';
            }

            if ($ors === []) {
                // Ninguna fuente aplica a esta palabra (p. ej. solo columnas numéricas y una
                // palabra sin dígitos): no puede coincidir.
                $ors[] = 'FALSE';
            }
            $sqlPalabra = '(' . implode(' OR ', $ors) . ')';
            // PDO (pgsql) falla si se liga un parámetro que la consulta no usa.
            foreach ([$ph, $phCrudo] as $p) {
                if ($p !== null && !preg_match('/' . preg_quote($p, '/') . '(?![A-Za-z0-9_])/', $sqlPalabra)) {
                    unset($params[$p]);
                }
            }
            $condiciones[] = $sqlPalabra;
        }

        return '(' . implode(' AND ', $condiciones) . ')';
    }

    /**
     * Fecha como texto ISO ('YYYY-MM-DD'), con una expresión INMUTABLE: `to_char()` y el cast
     * a texto son STABLE (dependen del DateStyle de la sesión) y PostgreSQL no los admite en
     * un índice. Devuelve exactamente el mismo texto que `columna::text`.
     */
    public static function fechaIso(string $col): string
    {
        return "COALESCE(lpad(extract(year FROM {$col})::int::text, 4, '0') || '-' ||"
             . " lpad(extract(month FROM {$col})::int::text, 2, '0') || '-' ||"
             . " lpad(extract(day FROM {$col})::int::text, 2, '0'), '')";
    }

    /** Fecha como se muestra en pantalla ('DD-MM-YYYY'), inmutable (= TO_CHAR(col,'DD-MM-YYYY')). */
    public static function fechaDmy(string $col): string
    {
        return "COALESCE(lpad(extract(day FROM {$col})::int::text, 2, '0') || '-' ||"
             . " lpad(extract(month FROM {$col})::int::text, 2, '0') || '-' ||"
             . " lpad(extract(year FROM {$col})::int::text, 4, '0'), '')";
    }

    /** Hora 'HH:MI', inmutable (= TO_CHAR(col,'HH24:MI')). */
    public static function horaHm(string $col): string
    {
        return "COALESCE(lpad(extract(hour FROM {$col})::int::text, 2, '0') || ':' ||"
             . " lpad(extract(minute FROM {$col})::int::text, 2, '0'), '')";
    }

    /**
     * SQL de los índices que necesitan las fuentes declaradas, listo para pgAdmin.
     * Se genera desde la MISMA declaración que usa la consulta: si la expresión cambia,
     * el índice también. Devuelve una sentencia por línea (sin duplicados).
     *
     * @param array $fuentes Igual que en condicion()
     * @return string[]
     */
    public static function sqlIndices(array $fuentes): array
    {
        $salida = [];
        foreach ($fuentes as $f) {
            if (empty($f['indice']['tabla']) || empty($f['indice']['nombre']) || empty($f['indice']['expr'])) {
                continue;
            }
            $i = $f['indice'];
            $sql = "CREATE INDEX IF NOT EXISTS {$i['nombre']} ON {$i['tabla']} USING gin (f_unaccent({$i['expr']}) gin_trgm_ops);";
            $salida[$i['nombre']] = $sql;
        }
        return array_values($salida);
    }
}
