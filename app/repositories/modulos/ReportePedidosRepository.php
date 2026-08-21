<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use PDO;

/**
 * Reporte de Pedidos: mismo patrón de filtros/agrupación/estadísticas que
 * ReporteVentasRepository, pero sobre pedidos_cabecera/pedidos_detalle
 * (documento interno, sin IVA ni valores monetarios).
 */
class ReportePedidosRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_pedido)::int AS anio
                FROM pedidos_cabecera
                WHERE id_empresa = :e AND eliminado = false
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [(int) date('Y')];
    }

    /**
     * Construye las condiciones WHERE comunes a partir de los filtros.
     * $idUsuarioFiltro: si el usuario NO tiene "acceso total", solo ve sus propios pedidos (§6).
     */
    private function buildWhereYParams(int $idEmpresa, array $filtros, string $aliasCab = 'p', ?int $idUsuarioFiltro = null): array
    {
        $where = "{$aliasCab}.id_empresa = :id_empresa
                  AND {$aliasCab}.eliminado = false
                  AND {$aliasCab}.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $params = [':id_empresa' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND {$aliasCab}.created_by = :id_usuario_filtro";
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND {$aliasCab}.fecha_pedido >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND {$aliasCab}.fecha_pedido <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        if (!empty($filtros['id_cliente'])) {
            $clientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : [$filtros['id_cliente']];
            $inNames = [];
            foreach ($clientes as $i => $id) {
                $pName = ":cli$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            $where .= " AND {$aliasCab}.id_cliente IN (" . implode(',', $inNames) . ")";
        }

        if (!empty($filtros['estado']) && $filtros['estado'] !== 'TODOS') {
            $where .= " AND {$aliasCab}.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['id_responsable_entrega'])) {
            $where .= " AND {$aliasCab}.id_responsable_entrega = :id_resp";
            $params[':id_resp'] = (int) $filtros['id_responsable_entrega'];
        }

        if (!empty($filtros['buscar'])) {
            $where .= " AND ((p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) ILIKE :buscar
                          OR c.nombre ILIKE :buscar
                          OR {$aliasCab}.observaciones ILIKE :buscar)";
            $params[':buscar'] = '%' . trim($filtros['buscar']) . '%';
        }

        if (!empty($filtros['producto_texto'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM pedidos_detalle pdx
                LEFT JOIN productos prx ON prx.id = pdx.id_producto
                WHERE pdx.id_pedido = {$aliasCab}.id AND pdx.eliminado = false
                  AND (prx.nombre ILIKE :prodtxt OR prx.codigo ILIKE :prodtxt)
            )";
            $params[':prodtxt'] = '%' . trim($filtros['producto_texto']) . '%';
        }

        return [$where, $params];
    }

    /**
     * Autocompletado del campo "Producto": busca por nombre o código, igual que el
     * filtro producto_texto (ver buildWhereYParams). Devuelve nombre y código para
     * mostrar ambos en la lista de sugerencias.
     */
    public function buscarItems(int $idEmpresa, string $q, int $limit = 15): array
    {
        $sql = "SELECT DISTINCT pr.nombre AS nombre, TRIM(COALESCE(pr.codigo, '')) AS codigo
                FROM pedidos_detalle pd
                JOIN pedidos_cabecera p ON p.id = pd.id_pedido
                JOIN productos pr ON pr.id = pd.id_producto
                WHERE p.id_empresa = :ie AND p.eliminado = false AND pd.eliminado = false
                  AND (pr.nombre ILIKE :q OR pr.codigo ILIKE :q)
                ORDER BY nombre
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // valor = lo que queda escrito en el buscador al elegir: el código cuando existe
        // (más preciso para re-filtrar, ya que el nombre puede repetirse entre productos
        // distintos), o el nombre si el producto no tiene código.
        return array_map(fn($r) => [
            'valor' => $r['codigo'] !== '' ? $r['codigo'] : $r['nombre'],
            'label' => $r['nombre'],
            'sub'   => $r['codigo'],
        ], $rows);
    }

    /** Cantidad total pedida (suma del detalle, líneas no eliminadas) por pedido, como subconsulta. */
    private function cteCantidad(): string
    {
        return "SELECT id_pedido, SUM(cantidad) AS cantidad_total
                FROM pedidos_detalle
                WHERE eliminado = false
                GROUP BY id_pedido";
    }

    public function getReporteDetallado(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'p', $idUsuarioFiltro);

        $sql = "
            WITH cant AS (" . $this->cteCantidad() . ")
            SELECT
                p.id,
                p.fecha_pedido,
                (p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) AS numero_pedido,
                c.identificacion AS cliente_ruc,
                c.nombre AS cliente_nombre,
                p.estado,
                p.fecha_entrega,
                COALESCE(rt.nombre, '') AS responsable_entrega,
                COALESCE(cant.cantidad_total, 0) AS cantidad_total,
                p.observaciones
            FROM pedidos_cabecera p
            JOIN clientes c ON c.id = p.id_cliente
            LEFT JOIN responsables_traslado rt ON rt.id = p.id_responsable_entrega
            LEFT JOIN cant ON cant.id_pedido = p.id
            WHERE {$where}
            ORDER BY p.fecha_pedido DESC, p.secuencial DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReporteAgrupadoCliente(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'p', $idUsuarioFiltro);

        $sql = "
            WITH cant AS (" . $this->cteCantidad() . ")
            SELECT
                c.id AS id_cliente,
                c.identificacion AS cliente_ruc,
                c.nombre AS cliente_nombre,
                COUNT(p.id) AS cantidad_pedidos,
                SUM(COALESCE(cant.cantidad_total, 0)) AS cantidad_total
            FROM pedidos_cabecera p
            JOIN clientes c ON c.id = p.id_cliente
            LEFT JOIN cant ON cant.id_pedido = p.id
            WHERE {$where}
            GROUP BY c.id, c.identificacion, c.nombre
            ORDER BY cantidad_pedidos DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReporteAgrupadoProducto(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'p', $idUsuarioFiltro);

        $sql = "
            SELECT
                d.id_producto,
                COALESCE(pr.codigo, '') AS producto_codigo,
                COALESCE(pr.nombre, 'Producto eliminado') AS producto_nombre,
                COUNT(DISTINCT d.id_pedido) AS cantidad_pedidos,
                SUM(d.cantidad) AS cantidad_total
            FROM pedidos_detalle d
            JOIN pedidos_cabecera p ON p.id = d.id_pedido
            JOIN clientes c ON c.id = p.id_cliente
            LEFT JOIN productos pr ON pr.id = d.id_producto
            WHERE d.eliminado = false AND {$where}
            GROUP BY d.id_producto, pr.codigo, pr.nombre
            ORDER BY cantidad_total DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReporteAgrupadoEstado(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'p', $idUsuarioFiltro);

        $sql = "
            WITH cant AS (" . $this->cteCantidad() . ")
            SELECT
                p.estado,
                COUNT(p.id) AS cantidad_pedidos,
                SUM(COALESCE(cant.cantidad_total, 0)) AS cantidad_total
            FROM pedidos_cabecera p
            JOIN clientes c ON c.id = p.id_cliente
            LEFT JOIN cant ON cant.id_pedido = p.id
            WHERE {$where}
            GROUP BY p.estado
            ORDER BY cantidad_pedidos DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReporteAgrupadoResponsable(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'p', $idUsuarioFiltro);

        $sql = "
            WITH cant AS (" . $this->cteCantidad() . ")
            SELECT
                COALESCE(rt.nombre, 'Sin asignar') AS responsable_entrega,
                COUNT(p.id) AS cantidad_pedidos,
                SUM(COALESCE(cant.cantidad_total, 0)) AS cantidad_total
            FROM pedidos_cabecera p
            JOIN clientes c ON c.id = p.id_cliente
            LEFT JOIN responsables_traslado rt ON rt.id = p.id_responsable_entrega
            LEFT JOIN cant ON cant.id_pedido = p.id
            WHERE {$where}
            GROUP BY COALESCE(rt.nombre, 'Sin asignar')
            ORDER BY cantidad_pedidos DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReporteAgrupadoFecha(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'p', $idUsuarioFiltro);

        $sql = "
            WITH cant AS (" . $this->cteCantidad() . ")
            SELECT
                p.fecha_pedido::date AS fecha,
                COUNT(p.id) AS cantidad_pedidos,
                SUM(COALESCE(cant.cantidad_total, 0)) AS cantidad_total
            FROM pedidos_cabecera p
            JOIN clientes c ON c.id = p.id_cliente
            LEFT JOIN cant ON cant.id_pedido = p.id
            WHERE {$where}
            GROUP BY p.fecha_pedido::date
            ORDER BY fecha DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReporteAgrupadoMes(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'p', $idUsuarioFiltro);

        $sql = "
            WITH cant AS (" . $this->cteCantidad() . ")
            SELECT
                TO_CHAR(p.fecha_pedido, 'YYYY-MM') AS mes,
                COUNT(p.id) AS cantidad_pedidos,
                SUM(COALESCE(cant.cantidad_total, 0)) AS cantidad_total
            FROM pedidos_cabecera p
            JOIN clientes c ON c.id = p.id_cliente
            LEFT JOIN cant ON cant.id_pedido = p.id
            WHERE {$where}
            GROUP BY TO_CHAR(p.fecha_pedido, 'YYYY-MM')
            ORDER BY mes DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEstadisticas(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'p', $idUsuarioFiltro);

        $sql = "
            WITH cant AS (" . $this->cteCantidad() . ")
            SELECT
                COUNT(p.id) AS total_pedidos,
                SUM(COALESCE(cant.cantidad_total, 0)) AS total_cantidad,
                COUNT(DISTINCT p.id_cliente) AS total_clientes
            FROM pedidos_cabecera p
            JOIN clientes c ON c.id = p.id_cliente
            LEFT JOIN cant ON cant.id_pedido = p.id
            WHERE {$where}
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'total_pedidos'  => (int) ($row['total_pedidos'] ?? 0),
            'total_cantidad' => (float) ($row['total_cantidad'] ?? 0),
            'total_clientes' => (int) ($row['total_clientes'] ?? 0),
        ];
    }

    public function getResumenEstados(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro = null): array
    {
        // Sin filtrar por estado (se calcula el desglose de TODOS los estados, ignorando ese filtro puntual).
        $filtrosSinEstado = $filtros;
        unset($filtrosSinEstado['estado']);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtrosSinEstado, 'p', $idUsuarioFiltro);

        $sql = "
            SELECT LOWER(p.estado) AS estado, COUNT(*) AS cantidad
            FROM pedidos_cabecera p
            JOIN clientes c ON c.id = p.id_cliente
            WHERE {$where}
            GROUP BY LOWER(p.estado)
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $resumen = ['pendientes' => 0, 'procesados' => 0, 'anulados' => 0];
        foreach ($rows as $row) {
            $cantidad = (int) $row['cantidad'];
            match ($row['estado']) {
                'pendiente' => $resumen['pendientes'] += $cantidad,
                'procesado' => $resumen['procesados'] += $cantidad,
                'anulado'   => $resumen['anulados']   += $cantidad,
                default     => null,
            };
        }
        return $resumen;
    }
}
