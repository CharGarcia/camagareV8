<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AmbienteReporte;
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
     *  - RECIBO  → recibos_venta_* (todo recibo no anulado: el recibo nace 'borrador'
     *    y solo pasa a 'emitido' al quedar pagado; se excluyen los ya facturados para no duplicar
     *    con FACTURA cuando se combina en AMBOS)
     */
    private function fuente(string $tipo): array
    {
        if ($tipo === 'RECIBO') {
            return [
                'cab'       => 'recibos_venta_cabecera',
                'det'       => 'recibos_venta_detalle',
                'fk_det'    => 'id_recibo',
                // El recibo nace 'borrador' y solo pasa a 'emitido' al quedar pagado (un recibo a
                // crédito sigue en borrador y es venta igual): cuenta todo lo que no esté anulado ni facturado (memoria recibos-venta-estado-borrador-es-vigente).
                'estado_ok' => "{alias}.estado NOT IN ('anulado', 'facturado')",
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
                    WHERE id_empresa = :e2 AND eliminado = false AND estado NOT IN ('anulado','facturado')
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
                  AND " . AmbienteReporte::condicion($aliasVenta) . "
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
     * Clientes que compraron un producto, para una única fuente. El producto se identifica
     * como en el ranking: por id_producto o, si es un ítem libre, por su descripción.
     */
    private function getClientesProductoPorFuente(int $idEmpresa, array $filtros, string $tipo, ?int $idProducto, string $nombre): array
    {
        $f = $this->fuente($tipo);
        // El producto del modal reemplaza al filtro de productos del reporte.
        $filtros['id_producto'] = '';
        [$where, $params] = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd', $f);

        if ($idProducto !== null) {
            $where .= ' AND d.id_producto = :id_prod_detalle';
            $params[':id_prod_detalle'] = $idProducto;
        } else {
            $where .= ' AND d.id_producto IS NULL AND d.descripcion = :desc_prod_detalle';
            $params[':desc_prod_detalle'] = $nombre;
        }

        $sql = "
            SELECT
                v.id_cliente,
                COALESCE(c.identificacion, '') AS cliente_ruc,
                COALESCE(c.nombre, '') AS cliente_nombre,
                SUM(d.cantidad) AS cantidad_vendida,
                COUNT(DISTINCT d.{$f['fk_det']}) AS num_documentos,
                SUM(d.precio_total_sin_impuesto) AS total_vendido
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN clientes c ON c.id = v.id_cliente
            WHERE {$where}
            GROUP BY v.id_cliente, c.identificacion, c.nombre
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clientes que compraron un producto con los mismos filtros del ranking (tipo de documento,
     * fechas y clientes elegidos), ordenados por cantidad. Su suma cuadra con la fila del producto.
     */
    public function getClientesProducto(int $idEmpresa, array $filtros, ?int $idProducto, string $nombre): array
    {
        $tipo = $filtros['tipo_documento'] ?? 'FACTURA';
        $tipos = $tipo === 'AMBOS' ? ['FACTURA', 'RECIBO'] : [$tipo];

        $idx = [];
        foreach ($tipos as $t) {
            foreach ($this->getClientesProductoPorFuente($idEmpresa, $filtros, $t, $idProducto, $nombre) as $r) {
                $k = (int) $r['id_cliente'];
                if (!isset($idx[$k])) {
                    $idx[$k] = ['cantidad_vendida' => 0.0, 'num_documentos' => 0, 'total_vendido' => 0.0] + $r;
                }
                $idx[$k]['cantidad_vendida'] += (float) $r['cantidad_vendida'];
                $idx[$k]['num_documentos'] += (int) $r['num_documentos'];
                $idx[$k]['total_vendido'] += (float) $r['total_vendido'];
            }
        }

        $rows = array_values($idx);
        usort($rows, fn ($a, $b) => [$b['cantidad_vendida'], $b['total_vendido']] <=> [$a['cantidad_vendida'], $a['total_vendido']]);

        $totalCant = array_sum(array_column($rows, 'cantidad_vendida'));
        foreach ($rows as &$r) {
            $r['pct'] = abs($totalCant) > 0.000001 ? round($r['cantidad_vendida'] * 100 / $totalCant, 2) : 0.0;
        }
        unset($r);

        return $rows;
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
