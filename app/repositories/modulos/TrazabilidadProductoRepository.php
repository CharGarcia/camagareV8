<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de solo lectura para la trazabilidad de un producto (kardex resuelto
 * contra su documento de origen). No escribe en ninguna tabla.
 */
class TrazabilidadProductoRepository extends BaseRepository
{
    /**
     * Mapa referencia_tipo (tal como está guardado en inventario_kardex, valores
     * mixtos en mayúsculas/minúsculas por historia del código) → grupo de resolución
     * + etiqueta legible. Grupos sin resolver (ajuste_manual, saldo_inicial,
     * migracion, null) muestran el movimiento de kardex tal cual, sin documento.
     */
    private const TIPOS = [
        'factura_venta'                  => ['grupo' => 'factura_venta',     'label' => 'Factura de venta'],
        'compra'                         => ['grupo' => 'compra',            'label' => 'Compra'],
        'nota_credito'                   => ['grupo' => 'nota_credito',      'label' => 'Nota de crédito'],
        'ajuste_manual'                  => ['grupo' => null,                'label' => 'Ajuste manual'],
        'carga_inventario'                => ['grupo' => 'carga_inventario', 'label' => 'Carga de inventario'],
        'recibo_venta'                   => ['grupo' => 'recibo_venta',      'label' => 'Recibo de venta'],
        'SALDO_INICIAL'                  => ['grupo' => null,                'label' => 'Saldo inicial'],
        'CONSIGNACION_VENTA'             => ['grupo' => 'consignacion_venta', 'label' => 'Consignación'],
        'EDICION_CONSIGNACION_VENTA'     => ['grupo' => 'consignacion_venta', 'label' => 'Consignación (editada)'],
        'ELIMINACION_CONSIGNACION_VENTA' => ['grupo' => 'consignacion_venta', 'label' => 'Consignación (reversa)'],
        'FACTURACION_CV'                 => ['grupo' => 'facturacion_cv',    'label' => 'Facturación de consignación'],
        'RETORNO_CV'                     => ['grupo' => 'retorno_cv',        'label' => 'Retorno de consignación'],
        'ELIMINACION_RETORNO_CV'         => ['grupo' => 'retorno_cv',        'label' => 'Retorno de consignación (reversa)'],
        'CAMBIO_ESTADO_RETORNO_CV'       => ['grupo' => 'retorno_cv',        'label' => 'Retorno de consignación (cambio de estado)'],
        'CAMBIO_PRODUCTO_CV'             => ['grupo' => 'cambio_producto_cv', 'label' => 'Cambio de producto'],
        'migracion'                      => ['grupo' => null,                'label' => 'Migración histórica'],
    ];

    /** Ruta MVC del módulo dueño de cada grupo, para enlazar al listado origen. */
    private const RUTAS = [
        'factura_venta'      => 'modulos/factura-venta',
        'compra'             => 'modulos/compras',
        'nota_credito'       => 'modulos/notas_credito',
        'carga_inventario'   => 'modulos/cargas-inventario',
        'recibo_venta'       => 'modulos/recibo-venta',
        'consignacion_venta' => 'modulos/consignaciones-ventas',
        'facturacion_cv'     => 'modulos/facturacion-cv',
        'retorno_cv'         => 'modulos/retornos-cv',
        'cambio_producto_cv' => 'modulos/cambio-producto-cv',
    ];

    public function __construct()
    {
        parent::__construct('inventario_kardex');
    }

    /**
     * Productos inventariables (con Kardex) que calzan con el texto buscado.
     */
    public function buscarProductos(int $idEmpresa, string $q, int $limit = 15): array
    {
        $sql = "SELECT id, codigo, nombre
                FROM productos
                WHERE id_empresa = :e AND eliminado = false AND inventariable = true
                  AND (nombre ILIKE :q OR codigo ILIKE :q OR codigo_auxiliar ILIKE :q OR codigo_barras ILIKE :q)
                ORDER BY nombre ASC
                LIMIT :lim";
        $st = $this->db->prepare($sql);
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':q', '%' . $q . '%');
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProducto(int $idProducto, int $idEmpresa): ?array
    {
        $sql = "SELECT id, codigo, nombre, tipo_produccion, inventariable, created_at, created_by
                FROM productos
                WHERE id = :p AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':p' => $idProducto, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Stock actual (todas las bodegas) desde la caché denormalizada. */
    public function getStockTotalCache(int $idProducto, int $idEmpresa): float
    {
        $sql = "SELECT COALESCE(SUM(stock_actual), 0)
                FROM productos_bodegas
                WHERE id_producto = :p AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':p' => $idProducto, ':e' => $idEmpresa]);
        return (float) $st->fetchColumn();
    }

    /**
     * Movimientos de kardex del producto, sin resolver documento de origen.
     * @param array{desde?:string,hasta?:string,tipo_movimiento?:string,limite?:int} $filtros
     */
    public function getMovimientos(int $idProducto, int $idEmpresa, array $filtros = []): array
    {
        $params = [':e' => $idEmpresa, ':p' => $idProducto];
        $where  = "WHERE k.id_empresa = :e AND k.id_producto = :p AND k.eliminado = false
                   AND k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)";

        if (!empty($filtros['desde'])) {
            $where .= " AND k.fecha_movimiento >= :desde";
            $params[':desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where .= " AND k.fecha_movimiento <= :hasta";
            $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['tipo_movimiento'])) {
            $where .= " AND k.tipo_movimiento = :tipo";
            $params[':tipo'] = $filtros['tipo_movimiento'];
        }

        $limite = max(1, min(1000, (int) ($filtros['limite'] ?? 300)));

        $sql = "SELECT k.id, k.referencia_tipo, k.referencia_id, k.tipo_movimiento,
                       k.fecha_movimiento, k.cantidad, k.costo_unitario, k.costo_total,
                       k.stock_anterior, k.stock_posterior, k.numero_lote, k.fecha_caducidad,
                       k.nup, k.observaciones, b.nombre AS bodega_nombre, u.nombre AS usuario_nombre
                FROM inventario_kardex k
                INNER JOIN bodegas b ON b.id = k.id_bodega
                LEFT JOIN usuarios u ON u.id = k.created_by
                $where
                ORDER BY k.fecha_movimiento ASC, k.id ASC
                LIMIT " . ($limite + 1);
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $truncado = count($rows) > $limite;
        if ($truncado) {
            $rows = array_slice($rows, 0, $limite);
        }

        return ['rows' => $rows, 'truncado' => $truncado];
    }

    /**
     * Documentos "previos" donde el producto aparece pero que NO generan
     * movimiento de kardex (pedidos, proformas, órdenes de compra, guías de
     * remisión). Son informativos: no afectan stock ni entran en los KPIs.
     * @param array{desde?:string,hasta?:string} $filtros
     */
    public function getDocumentosPrevios(int $idProducto, int $idEmpresa, array $filtros = []): array
    {
        return array_merge(
            $this->documentosPedidos($idProducto, $idEmpresa, $filtros),
            $this->documentosProformas($idProducto, $idEmpresa, $filtros),
            $this->documentosOrdenesCompra($idProducto, $idEmpresa, $filtros),
            $this->documentosGuiasRemision($idProducto, $idEmpresa, $filtros)
        );
    }

    private function documentosPedidos(int $idProducto, int $idEmpresa, array $filtros): array
    {
        [$where, $params] = $this->rangoFecha('pc.fecha_pedido', $filtros);
        $sql = "SELECT pc.fecha_pedido AS fecha,
                       pc.establecimiento || '-' || pc.punto_emision || '-' || pc.secuencial AS numero,
                       pc.estado, cl.nombre AS contraparte, pd.cantidad, pd.precio_unitario,
                       u.nombre AS usuario_nombre
                FROM pedidos_detalle pd
                INNER JOIN pedidos_cabecera pc ON pc.id = pd.id_pedido
                LEFT JOIN clientes cl ON cl.id = pc.id_cliente
                LEFT JOIN usuarios u ON u.id = pc.created_by
                WHERE pd.id_producto = ? AND pd.eliminado = false
                  AND pc.id_empresa = ? AND pc.eliminado = false
                  AND pc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                  $where
                ORDER BY pc.fecha_pedido ASC
                LIMIT 200";
        return $this->documentosGrupo($sql, array_merge([$idProducto, $idEmpresa, $idEmpresa], $params), 'Pedido', 'modulos/pedidos');
    }

    private function documentosProformas(int $idProducto, int $idEmpresa, array $filtros): array
    {
        [$where, $params] = $this->rangoFecha('pf.fecha_emision', $filtros);
        $sql = "SELECT pf.fecha_emision AS fecha,
                       pf.establecimiento || '-' || pf.punto_emision || '-' || pf.secuencial AS numero,
                       pf.estado, cl.nombre AS contraparte, pd.cantidad, pd.precio_unitario,
                       u.nombre AS usuario_nombre
                FROM proformas_detalle pd
                INNER JOIN proformas_cabecera pf ON pf.id = pd.id_proforma
                LEFT JOIN clientes cl ON cl.id = pf.id_cliente
                LEFT JOIN usuarios u ON u.id = pf.created_by
                WHERE pd.id_producto = ?
                  AND pf.id_empresa = ? AND pf.eliminado = false
                  AND pf.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                  $where
                ORDER BY pf.fecha_emision ASC
                LIMIT 200";
        return $this->documentosGrupo($sql, array_merge([$idProducto, $idEmpresa, $idEmpresa], $params), 'Proforma', 'modulos/proformas');
    }

    private function documentosOrdenesCompra(int $idProducto, int $idEmpresa, array $filtros): array
    {
        [$where, $params] = $this->rangoFecha('oc.fecha_orden', $filtros);
        $sql = "SELECT oc.fecha_orden AS fecha, oc.numero_orden AS numero,
                       oc.estado, p.razon_social AS contraparte, od.cantidad, od.precio_unitario,
                       u.nombre AS usuario_nombre
                FROM ordenes_compra_detalle od
                INNER JOIN ordenes_compra oc ON oc.id = od.id_orden
                LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                LEFT JOIN usuarios u ON u.id = oc.created_by
                WHERE od.id_producto = ?
                  AND oc.id_empresa = ? AND oc.eliminado = false
                  AND oc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                  $where
                ORDER BY oc.fecha_orden ASC
                LIMIT 200";
        return $this->documentosGrupo($sql, array_merge([$idProducto, $idEmpresa, $idEmpresa], $params), 'Orden de compra', 'modulos/ordenes-compra');
    }

    private function documentosGuiasRemision(int $idProducto, int $idEmpresa, array $filtros): array
    {
        [$where, $params] = $this->rangoFecha('gr.fecha_emision', $filtros);
        $sql = "SELECT gr.fecha_emision AS fecha,
                       gr.establecimiento || '-' || gr.punto_emision || '-' || gr.secuencial AS numero,
                       gr.estado, cl.nombre AS contraparte, gd.cantidad, NULL AS precio_unitario,
                       u.nombre AS usuario_nombre
                FROM guias_remision_detalle gd
                INNER JOIN guias_remision_cabecera gr ON gr.id = gd.id_guia_remision
                LEFT JOIN clientes cl ON cl.id = gr.id_cliente
                LEFT JOIN usuarios u ON u.id = gr.created_by
                WHERE gd.id_producto = ?
                  AND gr.id_empresa = ? AND gr.eliminado = false
                  AND gr.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                  $where
                ORDER BY gr.fecha_emision ASC
                LIMIT 200";
        return $this->documentosGrupo($sql, array_merge([$idProducto, $idEmpresa, $idEmpresa], $params), 'Guía de remisión', 'modulos/guias_remision');
    }

    /** Cláusula de rango de fecha con marcadores posicionales (?), para componer con las demás. */
    private function rangoFecha(string $columna, array $filtros): array
    {
        $where = '';
        $params = [];
        if (!empty($filtros['desde'])) {
            $where .= " AND {$columna} >= ?";
            $params[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where .= " AND {$columna} <= ?";
            $params[] = $filtros['hasta'] . ' 23:59:59';
        }
        return [$where, $params];
    }

    private function documentosGrupo(string $sql, array $params, string $label, string $ruta): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'fecha'           => $row['fecha'],
                'doc_label'       => $label,
                'doc_numero'      => $row['numero'],
                'doc_contraparte' => $row['contraparte'],
                'doc_estado'      => $row['estado'],
                'doc_ruta'        => $ruta,
                'cantidad'        => $row['cantidad'],
                'precio_unitario' => $row['precio_unitario'],
                'usuario_nombre'  => $row['usuario_nombre'],
            ];
        }
        return $out;
    }

    /**
     * Enriquece cada movimiento con la etiqueta, número, contraparte, estado y
     * ruta del documento de origen (agrupando por tipo para no hacer N+1 queries).
     */
    public function resolverOrigenes(array $movimientos, int $idEmpresa): array
    {
        $idsPorGrupo = [];
        foreach ($movimientos as $m) {
            $info  = self::TIPOS[$m['referencia_tipo']] ?? ['grupo' => null, 'label' => (string) $m['referencia_tipo']];
            $grupo = $info['grupo'];
            if ($grupo !== null && !empty($m['referencia_id'])) {
                $idsPorGrupo[$grupo][] = (int) $m['referencia_id'];
            }
        }

        $resueltos = [];
        foreach ($idsPorGrupo as $grupo => $ids) {
            $resueltos[$grupo] = $this->resolverGrupo($grupo, array_values(array_unique($ids)), $idEmpresa);
        }

        foreach ($movimientos as &$m) {
            $tipoCrudo = (string) $m['referencia_tipo'];
            $info  = self::TIPOS[$tipoCrudo] ?? ['grupo' => null, 'label' => $tipoCrudo !== '' ? $tipoCrudo : 'Movimiento'];
            $grupo = $info['grupo'];
            $doc   = ($grupo !== null && !empty($m['referencia_id']))
                ? ($resueltos[$grupo][(int) $m['referencia_id']] ?? null)
                : null;

            $m['doc_label']       = $info['label'];
            $m['doc_numero']      = $doc['numero'] ?? null;
            $m['doc_contraparte'] = $doc['contraparte'] ?? null;
            $m['doc_estado']      = $doc['estado'] ?? null;
            $m['doc_ruta']        = $grupo !== null ? (self::RUTAS[$grupo] ?? null) : null;
        }
        unset($m);

        return $movimientos;
    }

    /**
     * Resuelve un lote de referencia_id de un mismo grupo contra su tabla de
     * cabecera. Devuelve un array indexado por referencia_id.
     */
    private function resolverGrupo(string $grupo, array $ids, int $idEmpresa): array
    {
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));

        switch ($grupo) {
            case 'factura_venta':
                $sql = "SELECT v.id, v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero,
                               v.estado, c.nombre AS contraparte
                        FROM ventas_cabecera v
                        LEFT JOIN clientes c ON c.id = v.id_cliente
                        WHERE v.id_empresa = ? AND v.id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            case 'compra':
                // referencia_id en este tipo apunta a compras_detalle.id, no a la cabecera.
                // compras_cabecera no tiene columna "estado" (verificado contra el esquema real).
                $sql = "SELECT d.id, cc.establecimiento_prov || '-' || cc.punto_emision_prov || '-' || cc.secuencial_prov AS numero,
                               NULL AS estado, p.razon_social AS contraparte
                        FROM compras_detalle d
                        INNER JOIN compras_cabecera cc ON cc.id = d.id_compra
                        LEFT JOIN proveedores p ON p.id = cc.id_proveedor
                        WHERE cc.id_empresa = ? AND d.id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            case 'nota_credito':
                $sql = "SELECT n.id, n.establecimiento || '-' || n.punto_emision || '-' || n.secuencial AS numero,
                               n.estado, c.nombre AS contraparte
                        FROM notas_credito_cabecera n
                        LEFT JOIN clientes c ON c.id = n.id_cliente
                        WHERE n.id_empresa = ? AND n.id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            case 'carga_inventario':
                $sql = "SELECT id, numero, estado, NULL AS contraparte
                        FROM inventario_cargas
                        WHERE id_empresa = ? AND id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            case 'recibo_venta':
                $sql = "SELECT r.id, r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial AS numero,
                               r.estado, c.nombre AS contraparte
                        FROM recibos_venta_cabecera r
                        LEFT JOIN clientes c ON c.id = r.id_cliente
                        WHERE r.id_empresa = ? AND r.id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            case 'consignacion_venta':
                $sql = "SELECT cv.id, cv.establecimiento || '-' || cv.punto_emision || '-' || cv.secuencial AS numero,
                               cv.estado, c.nombre AS contraparte
                        FROM consignaciones_ventas cv
                        LEFT JOIN clientes c ON c.id = cv.id_cliente
                        WHERE cv.id_empresa = ? AND cv.id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            case 'facturacion_cv':
                $sql = "SELECT f.id,
                               COALESCE(f.numero_factura, f.establecimiento || '-' || f.punto_emision || '-' || f.secuencial) AS numero,
                               f.estado, c.nombre AS contraparte
                        FROM consignaciones_facturas f
                        LEFT JOIN clientes c ON c.id = f.id_cliente
                        WHERE f.id_empresa = ? AND f.id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            case 'retorno_cv':
                $sql = "SELECT r.id, r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial AS numero,
                               r.estado, c.nombre AS contraparte
                        FROM retornos_cv r
                        LEFT JOIN clientes c ON c.id = r.id_cliente
                        WHERE r.id_empresa = ? AND r.id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            case 'cambio_producto_cv':
                $sql = "SELECT cp.id, cp.establecimiento || '-' || cp.punto_emision || '-' || cp.secuencial AS numero,
                               cp.estado, c.nombre AS contraparte
                        FROM cambios_producto_cv cp
                        LEFT JOIN clientes c ON c.id = cp.id_cliente
                        WHERE cp.id_empresa = ? AND cp.id IN ($ph)";
                return $this->indexarPorId($sql, array_merge([$idEmpresa], $ids));

            default:
                return [];
        }
    }

    private function indexarPorId(string $sql, array $params): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }
}
