<?php

declare(strict_types=1);

namespace App\Traits;

use PDO;

/**
 * Condición "el documento pertenece al ambiente (producción/pruebas) de SU PROPIA empresa",
 * resuelta con valores literales en lugar de una subconsulta correlacionada por fila.
 *
 * POR QUÉ EXISTE
 *   Los listados multiempresa (Cuentas por Pagar/Cobrar, reportes) filtraban así:
 *
 *       c.tipo_ambiente = (SELECT CAST(e.tipo_ambiente AS VARCHAR(1))
 *                          FROM empresas e WHERE e.id = c.id_empresa)
 *
 *   Correlacionar por fila era deliberado —en el consolidado por RUC cada
 *   establecimiento puede estar en un ambiente distinto—, pero tiene un efecto
 *   devastador en el plan de ejecución: PostgreSQL no sabe estimar la selectividad
 *   de una subconsulta correlacionada, así que asume que sobreviven poquísimas
 *   filas. Con esa estimación elige bucles anidados donde debería usar hash joins.
 *
 *   Medido en producción (Cuentas por Pagar, empresa con 6.259 compras): estimaba
 *   30 filas donde había 6.193, elegía un Nested Loop contra el CTE de pagos y
 *   hacía 38.724.832 comparaciones para devolver 4 documentos. 10.922 ms. Con la
 *   misma consulta y el ambiente como literal: 102 ms. La subconsulta por fila
 *   costaba, además, 6.193 accesos al índice de `empresas`.
 *
 * QUÉ HACE
 *   Resuelve el tipo_ambiente de las empresas del alcance en UNA consulta (son una,
 *   o unas pocas en el consolidado) y genera la condición como pares literales:
 *
 *       (c.id_empresa, c.tipo_ambiente) IN ((30,'2'), (31,'1'))
 *
 *   Mismo resultado, misma semántica multiempresa, pero el planificador puede
 *   estimar y elige el plan correcto.
 *
 * EQUIVALENCIA CON LA VERSIÓN ANTERIOR
 *   - Empresa cuyo tipo_ambiente es NULL, o que no existe: la subconsulta devolvía
 *     NULL, la comparación daba NULL y la fila quedaba fuera. Aquí esa empresa no
 *     entra en la lista de pares, con el mismo efecto.
 *   - Si NINGUNA empresa del alcance tiene ambiente, devuelve FALSE (ninguna fila),
 *     igual que antes.
 *
 * Requiere que la clase tenga `protected PDO $db` (BaseRepository lo provee).
 */
trait AmbienteEmpresaTrait
{
    /** tipo_ambiente por id de empresa, cacheado por instancia. */
    private array $ambienteEmpresaCache = [];

    /**
     * @param string $alias Alias de la tabla del documento (debe exponer id_empresa y tipo_ambiente).
     * @param array<int> $ids Empresas del alcance, ya validadas como enteros positivos.
     */
    protected function condAmbienteDe(string $alias, array $ids): string
    {
        $pares = [];
        foreach ($this->ambientesDeEmpresas($ids) as $id => $amb) {
            if ($amb === null || $amb === '') {
                continue; // sin ambiente definido: ningún documento suyo pasa el filtro
            }
            $pares[] = '(' . $id . ", '" . str_replace("'", "''", $amb) . "')";
        }

        if (!$pares) {
            return 'FALSE';
        }

        return "({$alias}.id_empresa, {$alias}.tipo_ambiente) IN (" . implode(', ', $pares) . ')';
    }

    /**
     * tipo_ambiente de cada empresa pedida, en una sola consulta (las ya conocidas
     * salen de la caché). Las empresas inexistentes quedan como null.
     *
     * @param array<int> $ids
     * @return array<int, string|null>
     */
    private function ambientesDeEmpresas(array $ids): array
    {
        $ids    = array_values(array_unique(array_map('intval', $ids)));
        $faltan = array_values(array_diff($ids, array_keys($this->ambienteEmpresaCache)));

        if ($faltan) {
            $ph = implode(',', array_fill(0, count($faltan), '?'));
            $st = $this->db->prepare(
                "SELECT id, CAST(tipo_ambiente AS VARCHAR(1)) AS amb FROM empresas WHERE id IN ({$ph})"
            );
            $st->execute($faltan);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->ambienteEmpresaCache[(int) $row['id']] = $row['amb'] !== null ? (string) $row['amb'] : null;
            }
            // Las que no devolvió la consulta (empresa inexistente) se cachean como null
            // para no volver a preguntarlas en la misma petición.
            foreach ($faltan as $id) {
                $this->ambienteEmpresaCache[$id] ??= null;
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $this->ambienteEmpresaCache[$id];
        }
        return $out;
    }
}
