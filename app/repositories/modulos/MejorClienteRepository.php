<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Ranking de clientes por monto neto (Facturas + Recibos − Notas de Crédito,
 * todo sin impuestos) o por cantidad de documentos, en un rango de fechas y,
 * opcionalmente, por vendedor (asesor).
 *
 * Nota: notas_credito_cabecera no tiene id_vendedor (solo referencia el
 * documento modificado por número, sin FK). Al filtrar por vendedor, las NC
 * del cliente se restan igual (no se puede saber a qué vendedor pertenecían),
 * lo que en la práctica no distorsiona el ranking salvo un caso raro: un
 * cliente devuelve algo comprado a otro vendedor mientras se filtra por uno
 * específico. Documentado en el manual del módulo.
 */
class MejorClienteRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    /** WHERE común (empresa, no eliminado, ambiente, fecha, vendedor) para una tabla tipo *_cabecera con id_vendedor. */
    private function condicionesDocumento(string $alias, string $estadoOkTpl, bool $tieneVendedor, array $filtros, string $sufijo, array &$params): string
    {
        $where = "{$alias}.id_empresa = :id_empresa{$sufijo}
                  AND {$alias}.eliminado = false
                  AND {$alias}.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa{$sufijo})
                  AND " . str_replace('{alias}', $alias, $estadoOkTpl);
        $params[":id_empresa{$sufijo}"] = $filtros['id_empresa'];

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND {$alias}.fecha_emision >= :fecha_desde{$sufijo}";
            $params[":fecha_desde{$sufijo}"] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND {$alias}.fecha_emision <= :fecha_hasta{$sufijo}";
            $params[":fecha_hasta{$sufijo}"] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if ($tieneVendedor && !empty($filtros['id_vendedor'])) {
            $where .= " AND {$alias}.id_vendedor = :id_vendedor{$sufijo}";
            $params[":id_vendedor{$sufijo}"] = (int)$filtros['id_vendedor'];
        }

        return $where;
    }

    /**
     * Arma el UNION ALL de las fuentes de "venta" habilitadas (facturas y/o recibos)
     * y, si corresponde, resta las notas de crédito del mismo cliente/periodo.
     * Devuelve [sqlUnion, sqlNotas, params].
     */
    private function armarFuentes(array $filtros): array
    {
        $params = [];
        $partes = [];

        if (!empty($filtros['incluir_facturas'])) {
            $where = $this->condicionesDocumento('v', "{alias}.estado IN ('autorizado', 'autorizada')", true, $filtros, '_fac', $params);
            $partes[] = "SELECT v.id_cliente, v.total_sin_impuestos AS monto, 1 AS cuenta
                         FROM ventas_cabecera v
                         WHERE {$where}";
        }

        if (!empty($filtros['incluir_recibos'])) {
            $where = $this->condicionesDocumento('r', "{alias}.estado NOT IN ('borrador', 'anulado', 'facturado')", true, $filtros, '_rec', $params);
            $partes[] = "SELECT r.id_cliente, r.total_sin_impuestos AS monto, 1 AS cuenta
                         FROM recibos_venta_cabecera r
                         WHERE {$where}";
        }

        $sqlUnion = implode(' UNION ALL ', $partes);

        $sqlNotas = null;
        if (!empty($filtros['incluir_facturas']) || !empty($filtros['incluir_recibos'])) {
            $whereNc = $this->condicionesDocumento('nc', "{alias}.estado IN ('autorizado', 'autorizada')", false, $filtros, '_nc', $params);
            $sqlNotas = "SELECT nc.id_cliente, nc.total_sin_impuestos AS monto
                        FROM notas_credito_cabecera nc
                        WHERE {$whereNc}";
        }

        return [$sqlUnion, $sqlNotas, $params];
    }

    /**
     * Ranking de clientes. $filtros: id_empresa, fecha_desde, fecha_hasta,
     * id_vendedor, incluir_facturas (bool), incluir_recibos (bool),
     * orden_por ('monto'|'cantidad'), top_x (0 = todos).
     */
    public function getRanking(array $filtros): array
    {
        [$sqlUnion, $sqlNotas, $params] = $this->armarFuentes($filtros);
        if ($sqlUnion === '') {
            return [];
        }

        $sql = "
            WITH docs AS ({$sqlUnion})";
        if ($sqlNotas !== null) {
            $sql .= ",
            notas AS (
                SELECT id_cliente, SUM(monto) AS monto_nc
                FROM ({$sqlNotas}) x
                GROUP BY id_cliente
            )";
        }
        $sql .= "
            SELECT
                c.id AS id_cliente,
                c.identificacion AS cliente_ruc,
                c.nombre AS cliente_nombre,
                SUM(d.cuenta) AS cantidad_documentos,
                SUM(d.monto) - COALESCE(" . ($sqlNotas !== null ? 'n.monto_nc' : '0') . ", 0) AS monto_neto
            FROM docs d
            JOIN clientes c ON c.id = d.id_cliente";
        if ($sqlNotas !== null) {
            $sql .= "
            LEFT JOIN notas n ON n.id_cliente = c.id";
        }
        $sql .= "
            GROUP BY c.id, c.identificacion, c.nombre" . ($sqlNotas !== null ? ", n.monto_nc" : "") . "
            ORDER BY " . ($filtros['orden_por'] === 'cantidad' ? 'cantidad_documentos' : 'monto_neto') . " DESC";

        if (!empty($filtros['top_x'])) {
            $sql .= " LIMIT :top_x";
        }

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        if (!empty($filtros['top_x'])) {
            $st->bindValue(':top_x', (int)$filtros['top_x'], PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['monto_neto'] = (float)$r['monto_neto'];
            $r['cantidad_documentos'] = (int)$r['cantidad_documentos'];
            $r['venta_promedio'] = $r['cantidad_documentos'] > 0
                ? $r['monto_neto'] / $r['cantidad_documentos']
                : 0.0;
        }
        unset($r);

        return $rows;
    }

    /** Estadísticas globales (para las tarjetas KPI), sin Top X ni orden. */
    public function getEstadisticas(array $filtros): array
    {
        $filtrosSinLimite = $filtros;
        $filtrosSinLimite['top_x'] = 0;
        $rows = $this->getRanking($filtrosSinLimite);

        $totalClientes = count($rows);
        $totalDocumentos = array_sum(array_column($rows, 'cantidad_documentos'));
        $montoNeto = array_sum(array_column($rows, 'monto_neto'));

        return [
            'total_clientes'   => $totalClientes,
            'total_documentos' => $totalDocumentos,
            'monto_neto_total' => $montoNeto,
            'venta_promedio'   => $totalDocumentos > 0 ? $montoNeto / $totalDocumentos : 0.0,
        ];
    }

    /** Años disponibles para el selector (facturas autorizadas + recibos emitidos/facturados). */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT anio FROM (
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                    FROM ventas_cabecera
                    WHERE id_empresa = :e AND eliminado = false AND estado IN ('autorizado','autorizada')
                    UNION
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int
                    FROM recibos_venta_cabecera
                    WHERE id_empresa = :e2 AND eliminado = false AND estado NOT IN ('borrador','anulado')
                ) t
                WHERE anio IS NOT NULL
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $idEmpresa, ':e2' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }
}
