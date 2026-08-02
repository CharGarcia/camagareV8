<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ProductoMasVendidoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    /**
     * Configuración de la fuente de datos según el tipo de documento:
     *  - FACTURA → ventas_*        (facturas de venta autorizadas)
     *  - RECIBO  → recibos_venta_* (recibos emitidos/facturados; se excluyen los
     *    ya facturados para no duplicar con FACTURA cuando se combina en AMBOS)
     */
    private function fuente(string $tipo): array
    {
        if ($tipo === 'RECIBO') {
            return [
                'cab'       => 'recibos_venta_cabecera',
                'det'       => 'recibos_venta_detalle',
                'fk_det'    => 'id_recibo',
                'estado_ok' => "{alias}.estado NOT IN ('borrador', 'anulado', 'facturado')",
            ];
        }

        return [
            'cab'       => 'ventas_cabecera',
            'det'       => 'ventas_detalle',
            'fk_det'    => 'id_venta',
            'estado_ok' => "{alias}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')",
        ];
    }

    /**
     * Años disponibles (facturas autorizadas + recibos emitidos/facturados).
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT anio FROM (
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                    FROM ventas_cabecera
                    WHERE id_empresa = :e AND eliminado = false AND estado IN ('autorizado','autorizada')
                    UNION
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int
                    FROM recibos_venta_cabecera
                    WHERE id_empresa = :e2 AND eliminado = false AND estado NOT IN ('borrador','anulado','facturado')
                ) t
                WHERE anio IS NOT NULL
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $idEmpresa, ':e2' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [(int) date('Y')];
    }

    /**
     * Construye las condiciones WHERE a partir de los filtros (fecha, cliente, producto).
     */
    private function buildWhereYParams(int $idEmpresa, array $filtros, string $aliasVenta, string $aliasDetalle, array $f): array
    {
        $where = "{$aliasVenta}.id_empresa = :id_empresa
                  AND {$aliasVenta}.eliminado = false
                  AND {$aliasVenta}.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND " . str_replace('{alias}', $aliasVenta, $f['estado_ok']);

        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND {$aliasVenta}.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND {$aliasVenta}.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_cliente'])) {
            $clientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : [$filtros['id_cliente']];
            $in = [];
            foreach ($clientes as $i => $id) {
                $p = ":cli$i";
                $in[] = $p;
                $params[$p] = $id;
            }
            $where .= " AND {$aliasVenta}.id_cliente IN (" . implode(',', $in) . ")";
        }
        if (!empty($filtros['id_producto'])) {
            $productos = is_array($filtros['id_producto']) ? $filtros['id_producto'] : [$filtros['id_producto']];
            $in = [];
            foreach ($productos as $i => $id) {
                $p = ":prod$i";
                $in[] = $p;
                $params[$p] = $id;
            }
            $where .= " AND {$aliasDetalle}.id_producto IN (" . implode(',', $in) . ")";
        }

        return [$where, $params];
    }

    /**
     * Ranking de productos por cantidad vendida, para una única fuente (FACTURA o RECIBO).
     */
    private function getTopProductosPorFuente(int $idEmpresa, array $filtros, string $tipo): array
    {
        $f = $this->fuente($tipo);
        [$where, $params] = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd', $f);

        $sql = "
            SELECT
                d.id_producto,
                COALESCE(p.codigo, '') as producto_codigo,
                COALESCE(p.nombre, d.descripcion) as producto_nombre,
                SUM(d.cantidad) as cantidad_vendida,
                COUNT(DISTINCT d.{$f['fk_det']}) as num_documentos,
                SUM(d.precio_total_sin_impuesto) as total_vendido
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            WHERE {$where}
            GROUP BY d.id_producto, p.codigo, COALESCE(p.nombre, d.descripcion)
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Combina el ranking de dos fuentes (FACTURA + RECIBO) sumando por producto.
     * Se identifica el producto por id_producto; si no tiene (ítem libre), por su nombre.
     */
    private function mergePorProducto(array $a, array $b): array
    {
        $numericos = ['cantidad_vendida', 'num_documentos', 'total_vendido'];
        $idx = [];
        foreach (array_merge($a, $b) as $r) {
            $key = $r['id_producto'] !== null ? 'id:' . $r['id_producto'] : 'nom:' . $r['producto_nombre'];
            if (!isset($idx[$key])) {
                foreach ($numericos as $c) {
                    $r[$c] = (float) ($r[$c] ?? 0);
                }
                $idx[$key] = $r;
            } else {
                foreach ($numericos as $c) {
                    $idx[$key][$c] += (float) ($r[$c] ?? 0);
                }
            }
        }
        return array_values($idx);
    }

    /**
     * Ranking completo de productos más vendidos (sin límite de Top N; el
     * controlador recorta según el filtro elegido, para poder calcular
     * estadísticas globales sobre el total antes de recortar).
     */
    public function getTopProductos(int $idEmpresa, array $filtros): array
    {
        $tipo = $filtros['tipo_documento'] ?? 'FACTURA';

        if ($tipo === 'AMBOS') {
            $fac = $this->getTopProductosPorFuente($idEmpresa, $filtros, 'FACTURA');
            $rec = $this->getTopProductosPorFuente($idEmpresa, $filtros, 'RECIBO');
            $rows = $this->mergePorProducto($fac, $rec);
        } else {
            $rows = $this->getTopProductosPorFuente($idEmpresa, $filtros, $tipo);
        }

        usort($rows, fn($a, $b) => ((float) $b['cantidad_vendida']) <=> ((float) $a['cantidad_vendida']));

        $rank = 1;
        foreach ($rows as &$r) {
            $r['ranking'] = $rank++;
        }
        unset($r);

        return $rows;
    }
}
