<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ReporteVentasRepository extends BaseRepository
{
    /**
     * Obtiene los años disponibles con ventas autorizadas para la empresa.
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision) as anio 
                FROM ventas_cabecera 
                WHERE id_empresa = :id_empresa 
                  AND eliminado = false 
                  AND estado = 'autorizada'
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [date('Y')];
    }

    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    /**
     * Devuelve el CTE de impuestos para facilitar sumatorias.
     */
    private function getCteBasesImpuestos(): string
    {
        return "
            SELECT 
                d.id_venta, 
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(i.valor) as valor_iva
            FROM ventas_detalle d
            LEFT JOIN ventas_detalle_impuestos i ON i.id_venta_detalle = d.id
            GROUP BY d.id_venta
        ";
    }

    /**
     * Construye las condiciones WHERE a partir de los filtros.
     */
    private function buildWhereYParams(int $idEmpresa, array $filtros, string $aliasVenta, string $aliasDetalle = null, bool $filtrarEstado = true): array
    {
        // Forzamos que solo se listen facturas del ambiente actual de la empresa
        $where = "{$aliasVenta}.id_empresa = :id_empresa 
                  AND {$aliasVenta}.eliminado = false 
                  AND {$aliasVenta}.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
                  
        if ($filtrarEstado) {
            $where .= " AND {$aliasVenta}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')";
        }

                  
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
            $inNames = [];
            foreach ($clientes as $i => $id) {
                $pName = ":cli$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            $where .= " AND {$aliasVenta}.id_cliente IN (" . implode(',', $inNames) . ")";
        }
        
        if (!empty($filtros['id_producto'])) {
            $productos = is_array($filtros['id_producto']) ? $filtros['id_producto'] : [$filtros['id_producto']];
            $inNames = [];
            foreach ($productos as $i => $id) {
                $pName = ":prod$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            if ($aliasDetalle) {
                $where .= " AND {$aliasDetalle}.id_producto IN (" . implode(',', $inNames) . ")";
            } else {
                $where .= " AND EXISTS (SELECT 1 FROM ventas_detalle vd WHERE vd.id_venta = {$aliasVenta}.id AND vd.id_producto IN (" . implode(',', $inNames) . "))";
            }
        }

        // Filtro por tipo de documento (actualmente solo facturas, pero preparado para recibos)
        if (!empty($filtros['tipo_documento'])) {
            if ($filtros['tipo_documento'] === 'FACTURA') {
                // Asumiendo que el comprobante de factura es 01 o similar, o simplemente lo dejamos sin filtro si toda la tabla es facturas
                // Actualmente ventas_cabecera almacena facturas.
            }
        }

        return [$where, $params];
    }

    /**
     * Reporte detallado (por factura).
     */
    public function getReporteDetallado(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT
                v.id,
                v.fecha_emision,
                CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) as numero_factura,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                v.estado,
                COALESCE(b.base_0, 0)   as base_0,
                COALESCE(b.base_iva, 0) as base_iva,
                COALESCE(b.valor_iva, 0) as valor_iva,
                v.importe_total          as total,
                COALESCE(vend.nombre, '')   as vendedor_nombre,
                COALESCE(ucaj.nombre, '')   as cajero_nombre,
                COALESCE(uusr.nombre, '')   as usuario_nombre,
                COALESCE(v.clave_acceso, '') as clave_acceso,
                COALESCE((
                    SELECT SUM(r.total_iva + r.total_renta + r.total_isd)
                    FROM retencion_venta_cabecera r
                    WHERE r.id_venta = v.id AND r.eliminado = false
                ), 0) as retenciones
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases b ON b.id_venta = v.id
            LEFT JOIN vendedores  vend ON vend.id = v.id_vendedor
            LEFT JOIN usuarios    ucaj ON ucaj.id = v.id_usuario
            LEFT JOIN usuarios    uusr ON uusr.id = v.created_by
            WHERE {$where}
            ORDER BY v.fecha_emision DESC, v.secuencial DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por cliente.
     */
    public function getReporteAgrupadoCliente(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT 
                c.id as id_cliente,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases b ON b.id_venta = v.id
            WHERE {$where}
            GROUP BY c.id, c.identificacion, c.nombre
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por producto.
     */
    public function getReporteAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');

        $sql = "
            SELECT 
                d.id_producto,
                COALESCE(p.codigo, '') as producto_codigo,
                COALESCE(p.nombre, d.descripcion) as producto_nombre,
                COALESCE(i.tarifa, 0) as tarifa_iva,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM ventas_detalle d
            JOIN ventas_cabecera v ON v.id = d.id_venta
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN ventas_detalle_impuestos i ON i.id_venta_detalle = d.id
            WHERE {$where}
            GROUP BY d.id_producto, p.codigo, COALESCE(p.nombre, d.descripcion), COALESCE(i.tarifa, 0)
            ORDER BY cantidad_vendida DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por fecha.
     */
    public function getReporteAgrupadoFecha(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        // Por defecto agrupa por día
        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT 
                v.fecha_emision as fecha,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM ventas_cabecera v
            LEFT JOIN bases b ON b.id_venta = v.id
            WHERE {$where}
            GROUP BY v.fecha_emision
            ORDER BY v.fecha_emision DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por mes (año-mes).
     */
    public function getReporteAgrupadoMes(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT
                TO_CHAR(v.fecha_emision, 'YYYY-MM') as mes,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM ventas_cabecera v
            LEFT JOIN bases b ON b.id_venta = v.id
            WHERE {$where}
            GROUP BY TO_CHAR(v.fecha_emision, 'YYYY-MM')
            ORDER BY mes DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene estadísticas globales para el rango de fechas.
     */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');
        
        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT 
                SUM(COALESCE(b.base_0, 0)) as total_base_0,
                SUM(COALESCE(b.base_iva, 0)) as total_base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as total_iva,
                SUM(v.importe_total) as gran_total,
                COUNT(v.id) as total_documentos
            FROM ventas_cabecera v
            LEFT JOIN bases b ON b.id_venta = v.id
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'total_base_0'     => (float)($row['total_base_0'] ?? 0),
            'total_base_iva'   => (float)($row['total_base_iva'] ?? 0),
            'total_iva'        => (float)($row['total_iva'] ?? 0),
            'gran_total'       => (float)($row['gran_total'] ?? 0),
            'total_documentos' => (int)($row['total_documentos'] ?? 0),
        ];
    }

    public function getResumenEstados(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', null, false);
        
        $sql = "
            SELECT 
                LOWER(estado) as estado,
                COUNT(*) as cantidad
            FROM ventas_cabecera v
            WHERE {$where}
            GROUP BY LOWER(estado)
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        $resumen = [
            'autorizados' => 0,
            'anulados'    => 0,
            'borradores'  => 0
        ];

        foreach ($rows as $row) {
            $estado = $row['estado'];
            $cantidad = (int) $row['cantidad'];
            if (in_array($estado, ['autorizado', 'autorizada'])) {
                $resumen['autorizados'] += $cantidad;
            } elseif ($estado === 'anulado') {
                $resumen['anulados'] += $cantidad;
            } elseif ($estado === 'borrador') {
                $resumen['borradores'] += $cantidad;
            }
        }

        return $resumen;
    }
}
