<?php

declare(strict_types=1);

namespace App\Traits;

use App\Helpers\IdentificacionTercero;
use PDO;

/**
 * Expansión de un tercero (cliente o proveedor) a TODAS las filas que en realidad son
 * el mismo: el contribuyente registrado dos veces —una con la cédula y otra con el RUC,
 * que es esa cédula + '001'— y, en el consolidado por RUC, sus fichas hermanas de los
 * demás establecimientos, porque `clientes` y `proveedores` son tablas por empresa.
 *
 * Sin esto la cartera del tercero sale partida: dos entradas en el buscador, dos grupos
 * en la vista agrupada, dos estados de cuenta y dos correos de cobro.
 *
 * Lo usan CuentasPorCobrarRepository, CuentasPorPagarRepository y ReporteCarteraRepository.
 * Requiere `$this->db` (PDO), que aporta BaseRepository.
 */
trait ExpansionTerceroTrait
{
    /**
     * Ids de todas las fichas que son el mismo tercero que las recibidas (incluidas ellas).
     *
     * Se resuelve en dos pasos —leer las identificaciones y buscar sus variantes con
     * `identificacion IN (...)`— en vez de comparar la clave base dentro de un JOIN:
     * envolver la columna en una expresión (TRIM, LEFT…) anularía el índice
     * `uk_{tabla}_empresa_identificacion`. Por eso se compara el valor tal cual está
     * guardado, igual que hacía el cruce por identificación exacta al que sustituye.
     * Las fichas sin identificación solo se cruzan consigo mismas.
     *
     * @param string $tabla      'clientes' o 'proveedores'. Literal del código, nunca entrada del usuario.
     * @param int[]  $ids        Fichas elegidas.
     * @param int[]  $idsEmpresa Alcance: la empresa activa o el grupo consolidable.
     * @return int[]
     */
    protected function expandirTerceroPorIdentificacion(string $tabla, array $ids, array $idsEmpresa): array
    {
        if (!in_array($tabla, ['clientes', 'proveedores'], true)) {
            throw new \InvalidArgumentException("Tabla de terceros no soportada: {$tabla}");
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !$idsEmpresa) {
            return $ids;
        }

        $params = [];
        $inIds  = $this->placeholdersTercero($ids, 'eti', $params);
        $st = $this->db->prepare("SELECT identificacion FROM {$tabla} WHERE id IN ({$inIds})");
        $st->execute($params);
        $variantes = IdentificacionTercero::variantesDeVarias($st->fetchAll(PDO::FETCH_COLUMN));
        if (!$variantes) {
            return $ids;
        }

        $params = [];
        $inEmp  = $this->placeholdersTercero($idsEmpresa, 'ete', $params);
        $inIde  = $this->placeholdersTercero($variantes, 'etv', $params);
        $st = $this->db->prepare("SELECT id FROM {$tabla}
                                  WHERE id_empresa IN ({$inEmp})
                                    AND eliminado = false
                                    AND identificacion IN ({$inIde})");
        $st->execute($params);
        $hermanas = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        return array_values(array_unique(array_merge($ids, $hermanas)));
    }

    /**
     * Deja una sola ficha por tercero real en una lista ya leída, quedándose con la
     * primera de cada grupo (el llamador decide el orden: normalmente la de la empresa
     * activa y, entre cédula y RUC, la del RUC).
     *
     * @param  array<int, array<string, mixed>> $filas       Filas con id e identificación.
     * @param  string                           $colIdentif  Columna que trae la identificación.
     * @param  string                           $colId       Columna que trae el id.
     * @return array<int, array<string, mixed>>
     */
    protected function unaFilaPorTercero(array $filas, string $colIdentif = 'identificacion', string $colId = 'id'): array
    {
        $out = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $clave = IdentificacionTercero::claveGrupo(
                isset($fila[$colIdentif]) ? (string) $fila[$colIdentif] : null,
                'id:' . (int) ($fila[$colId] ?? 0)
            );
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = $fila;
        }
        return $out;
    }

    /**
     * `(1,3)` para interpolar en un `IN` cuando el mismo id se repite en varias ramas de un
     * UNION —PDO-pgsql no admite reutilizar un placeholder— y el llamador ya tiene los ids
     * como enteros. Castea a int, así que no puede llevar entrada del usuario sin filtrar.
     * Con la lista vacía devuelve `(NULL)`, que no empareja con nada.
     *
     * @param int[] $ids
     */
    protected function sqlInTercero(array $ids): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        return $ids ? '(' . implode(',', $ids) . ')' : '(NULL)';
    }

    /** `IN (...)` con placeholders con nombre; PDO-pgsql no admite repetir un placeholder. */
    private function placeholdersTercero(array $valores, string $prefijo, array &$params): string
    {
        $ph = [];
        foreach (array_values($valores) as $i => $v) {
            $k = ":{$prefijo}{$i}";
            $ph[] = $k;
            $params[$k] = $v;
        }
        return implode(',', $ph);
    }
}
