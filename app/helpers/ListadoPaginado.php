<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Listado paginado en UNA sola consulta: filtra y cuenta una vez, y calcula las
 * columnas caras solo para las filas de la página.
 *
 * Por qué existe (medido el 16-09-2026 en Compras/Facturas con ~5.000 documentos):
 * el patrón viejo era un COUNT(*) + un SELECT … LIMIT con el mismo WHERE. Con texto
 * libre o filtros calculados el WHERE es lo caro, y se evaluaba DOS veces. Además, las
 * subconsultas de la lista del SELECT (cobrado, pagado, abonos, tipos del detalle) se
 * calculaban para TODAS las filas de la empresa antes del ORDER BY/LIMIT: mostrar 20
 * costaba lo mismo que mostrar todas.
 *
 * Forma de la consulta:
 *   WITH pagina AS MATERIALIZED (
 *       SELECT a.id, ROW_NUMBER() OVER (ORDER BY …), COUNT(*) OVER ()   -- total antes del LIMIT
 *       FROM tabla a <joins del filtro> WHERE … ORDER BY … LIMIT …
 *   )
 *   SELECT <columnas finales>, pg.__total
 *   FROM pagina pg JOIN tabla a ON a.id = pg.__id <joins finales>
 *   ORDER BY pg.__rn
 *
 * Reglas para usarlo:
 *  - `joinsFiltro` debe traer TODOS los alias que usan el WHERE y el ORDER BY (y los
 *    INNER JOIN que descartan filas, porque afectan al total).
 *  - `select` no puede usar placeholders nuevos: los parámetros son los del WHERE.
 *  - La tabla debe tener PK `id`. `__total` se quita de las filas antes de devolver.
 *  - Si la página sale vacía con offset > 0 (página fuera de rango), el total se cuenta
 *    aparte; es un caso raro.
 */
final class ListadoPaginado
{
    /**
     * @param callable(string, array): array $ejecutar Ejecuta SQL con parámetros y devuelve
     *        las filas como arrays asociativos (normalmente el query() del repository).
     * @param array{
     *     tabla: string, alias: string, where: string, orderBy: string, select: string,
     *     joinsFiltro?: string, joinsFinal?: string, perPage: int, offset: int,
     *     conBusqueda?: bool
     * } $cfg  `conBusqueda`: true (por defecto) si el WHERE trae texto libre o filtros; con
     *        false (listado sin buscar) se usa la forma liviana de abajo.
     * @return array{rows: array, total: int}
     */
    public static function consultar(callable $ejecutar, array $cfg, array $params): array
    {
        $tabla       = $cfg['tabla'];
        $a           = $cfg['alias'];
        $where       = $cfg['where'];
        $orderBy     = $cfg['orderBy'];
        $joinsFiltro = $cfg['joinsFiltro'] ?? '';
        $joinsFinal  = $cfg['joinsFinal'] ?? '';
        $perPage     = (int) $cfg['perPage'];
        $offset      = max(0, (int) $cfg['offset']);
        $limite      = $perPage > 0 ? " LIMIT {$perPage} OFFSET {$offset}" : '';
        $conBusqueda = (bool) ($cfg['conBusqueda'] ?? true);

        if (!$conBusqueda && $perPage > 0) {
            // Listado SIN búsqueda: el WHERE es barato (empresa, eliminado, ambiente,
            // registros propios) y COUNT(*) OVER () obligaría a leer y ORDENAR todas las
            // filas de la empresa. Aquí los ids de la página salen de un ORDER BY … LIMIT
            // que puede resolverse por índice (lee solo esas filas) y el total de un
            // COUNT aparte; las dos son subconsultas de UNA sola sentencia, y el COUNT se
            // evalúa una vez (InitPlan). ARRAY(… ORDER BY …) conserva el orden y
            // WITH ORDINALITY lo numera. Medido: 40.000 egresos, 75 ms → ~28 ms.
            $sql = "SELECT {$cfg['select']},
                           (SELECT COUNT(*) FROM {$tabla} {$a} {$joinsFiltro} {$where}) AS __total
                    FROM unnest(ARRAY(
                        SELECT {$a}.id FROM {$tabla} {$a} {$joinsFiltro} {$where} {$orderBy}{$limite}
                    )) WITH ORDINALITY AS pg(__id, __rn)
                    INNER JOIN {$tabla} {$a} ON {$a}.id = pg.__id
                    {$joinsFinal}
                    ORDER BY pg.__rn";

            return self::armarResultado($ejecutar, $sql, $cfg, $params, $offset);
        }

        $sql = "WITH pagina AS MATERIALIZED (
                    SELECT {$a}.id AS __id,
                           ROW_NUMBER() OVER ({$orderBy}) AS __rn,
                           COUNT(*) OVER () AS __total
                    FROM {$tabla} {$a}
                    {$joinsFiltro}
                    {$where}
                    {$orderBy}
                    {$limite}
                )
                SELECT {$cfg['select']},
                       pg.__total
                FROM pagina pg
                INNER JOIN {$tabla} {$a} ON {$a}.id = pg.__id
                {$joinsFinal}
                ORDER BY pg.__rn";

        return self::armarResultado($ejecutar, $sql, $cfg, $params, $offset);
    }

    /** Ejecuta, saca el total de la primera fila y lo quita de todas. */
    private static function armarResultado(callable $ejecutar, string $sql, array $cfg, array $params, int $offset): array
    {
        $rows = $ejecutar($sql, $params);

        if ($rows) {
            $total = (int) $rows[0]['__total'];
        } elseif ($offset > 0) {
            // Página fuera de rango: sin filas no hay __total; se cuenta aparte (raro).
            $conteo = $ejecutar(
                "SELECT COUNT(*) AS n FROM {$cfg['tabla']} {$cfg['alias']} " . ($cfg['joinsFiltro'] ?? '') . " {$cfg['where']}",
                $params
            );
            $total = (int) ($conteo[0]['n'] ?? 0);
        } else {
            $total = 0;
        }

        foreach ($rows as &$r) {
            unset($r['__total']);
        }
        unset($r);

        return ['rows' => $rows, 'total' => $total];
    }
}
