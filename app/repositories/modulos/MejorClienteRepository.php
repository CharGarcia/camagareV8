<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AmbienteReporte;
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

    /** WHERE común (empresa, no eliminado, ambiente de producción, fecha, vendedor) para una tabla tipo *_cabecera con id_vendedor. */
    private function condicionesDocumento(string $alias, string $estadoOkTpl, bool $tieneVendedor, array $filtros, string $sufijo, array &$params): string
    {
        $where = "{$alias}.id_empresa = :id_empresa{$sufijo}
                  AND {$alias}.eliminado = false
                  AND " . AmbienteReporte::condicion($alias) . "
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
            // Recibo vigente = no anulado ni facturado. Nace 'borrador' y pasa a 'emitido' solo al
            // quedar pagado: un recibo a crédito sigue en borrador y es venta igual.
            $where = $this->condicionesDocumento('r', "{alias}.estado NOT IN ('anulado', 'facturado')", true, $filtros, '_rec', $params);
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

    /**
     * Productos vendidos a un cliente con los mismos filtros del ranking (fechas, vendedor,
     * fuentes y estados), para que la suma de los productos cuadre con su monto neto: líneas
     * de Facturas y/o Recibos menos las líneas de sus Notas de Crédito, sin impuestos.
     * Agrupa por producto; las líneas sin producto (conceptos libres) se agrupan por código y
     * descripción. La unidad es la del producto, como en Reporte de Ventas.
     */
    public function getDetalleProductos(array $filtros, int $idCliente): array
    {
        $params = [];
        $partes = [];

        if (!empty($filtros['incluir_facturas'])) {
            $where = $this->condicionesDocumento('v', "{alias}.estado IN ('autorizado', 'autorizada')", true, $filtros, '_fac', $params);
            $partes[] = "SELECT d.id_producto, d.codigo_principal, d.descripcion, d.cantidad,
                                d.precio_total_sin_impuesto AS monto, 'F' || v.id AS doc
                         FROM ventas_detalle d
                         JOIN ventas_cabecera v ON v.id = d.id_venta
                         WHERE {$where} AND v.id_cliente = :id_cliente_fac";
            $params[':id_cliente_fac'] = $idCliente;
        }

        if (!empty($filtros['incluir_recibos'])) {
            $where = $this->condicionesDocumento('r', "{alias}.estado NOT IN ('anulado', 'facturado')", true, $filtros, '_rec', $params);
            $partes[] = "SELECT d.id_producto, d.codigo_principal, d.descripcion, d.cantidad,
                                d.precio_total_sin_impuesto AS monto, 'R' || r.id AS doc
                         FROM recibos_venta_detalle d
                         JOIN recibos_venta_cabecera r ON r.id = d.id_recibo
                         WHERE {$where} AND r.id_cliente = :id_cliente_rec";
            $params[':id_cliente_rec'] = $idCliente;
        }

        if ($partes === []) {
            return [];
        }

        // Las NC restan cantidad y monto; no cuentan como documento de venta (doc = NULL).
        $whereNc = $this->condicionesDocumento('nc', "{alias}.estado IN ('autorizado', 'autorizada')", false, $filtros, '_nc', $params);
        $partes[] = "SELECT d.id_producto, d.codigo_principal, d.descripcion, -d.cantidad,
                            -d.precio_total_sin_impuesto, NULL
                     FROM notas_credito_detalle d
                     JOIN notas_credito_cabecera nc ON nc.id = d.id_nota_credito
                     WHERE {$whereNc} AND nc.id_cliente = :id_cliente_nc";
        $params[':id_cliente_nc'] = $idCliente;

        $sql = "
            WITH lineas AS (" . implode(' UNION ALL ', $partes) . ")
            SELECT
                l.id_producto,
                COALESCE(NULLIF(TRIM(p.codigo), ''), MAX(l.codigo_principal), '') AS producto_codigo,
                COALESCE(p.nombre, MAX(l.descripcion)) AS producto_nombre,
                COALESCE(um.nombre, '') AS unidad_medida,
                SUM(l.cantidad) AS cantidad,
                SUM(l.monto) AS monto_neto,
                COUNT(DISTINCT l.doc) AS cantidad_documentos
            FROM lineas l
            LEFT JOIN productos p ON p.id = l.id_producto
            LEFT JOIN unidades_medida um ON um.id = p.id_medida
            GROUP BY l.id_producto, p.codigo, p.nombre, um.nombre,
                     CASE WHEN l.id_producto IS NULL THEN COALESCE(l.codigo_principal, '') || '|' || l.descripcion END
            ORDER BY monto_neto DESC, producto_nombre";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $total = array_sum(array_map(static fn ($r) => (float) $r['monto_neto'], $rows));
        foreach ($rows as &$r) {
            $r['cantidad'] = (float) $r['cantidad'];
            $r['monto_neto'] = (float) $r['monto_neto'];
            $r['cantidad_documentos'] = (int) $r['cantidad_documentos'];
            $r['pct'] = abs($total) > 0.000001 ? round($r['monto_neto'] * 100 / $total, 2) : 0.0;
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
                    WHERE id_empresa = :e2 AND eliminado = false AND estado NOT IN ('anulado','facturado')
                ) t
                WHERE anio IS NOT NULL
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $idEmpresa, ':e2' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }
}
