<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Reportes del Punto de Venta (POS): turnos de caja, formas de pago, productos
 * y cajeros. Se arma desde caja_sesiones + las Facturas/Recibos que quedaron
 * enlazadas a un turno (ventas_cabecera.id_caja_sesion /
 * recibos_venta_cabecera.id_caja_sesion — ver migración
 * 20260717_pos_ventas_id_caja_sesion.sql). Solo se excluyen los documentos
 * ANULADOS: uno en 'borrador' (Factura del POS aún no enviada al SRI, o
 * Recibo que nunca se convirtió a factura) sí cuenta, porque el dinero ya se
 * cobró en la caja — es un reporte de caja, no de estado fiscal.
 */
class ReportePosRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('caja_sesiones');
    }

    /**
     * CTE "docs" (id, tipo_documento, id_caja_sesion, id_usuario -cajero-,
     * id_punto_emision, fecha_emision, importe_total) con los filtros de
     * fecha/punto/cajero ya aplicados. Fuente común de forma de pago,
     * productos y ventas por cajero.
     */
    private function cteDocs(int $idEmpresa, array $filtros): array
    {
        $params = [':id_empresa' => $idEmpresa, ':id_empresa_r' => $idEmpresa];

        $condFecha = '';
        if (!empty($filtros['fecha_desde'])) {
            $condFecha .= " AND {alias}.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $condFecha .= " AND {alias}.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        $condPunto = '';
        if (!empty($filtros['id_punto_emision'])) {
            $condPunto = " AND {alias}.id_punto_emision = :id_punto_emision";
            $params[':id_punto_emision'] = (int) $filtros['id_punto_emision'];
        }
        $condCajero = '';
        if (!empty($filtros['id_usuario'])) {
            $condCajero = " AND {alias}.id_usuario = :id_usuario";
            $params[':id_usuario'] = (int) $filtros['id_usuario'];
        }

        $extra = $condFecha . $condPunto . $condCajero;

        $sql = "
            WITH docs AS (
                SELECT v.id, 'FACTURA' AS tipo_documento, v.id_caja_sesion, v.id_usuario,
                       v.id_punto_emision, v.fecha_emision, v.importe_total
                FROM ventas_cabecera v
                WHERE v.id_empresa = :id_empresa AND v.eliminado = false
                  AND v.id_caja_sesion IS NOT NULL AND v.estado != 'anulado'
                  " . str_replace('{alias}', 'v', $extra) . "
                UNION ALL
                SELECT r.id, 'RECIBO' AS tipo_documento, r.id_caja_sesion, r.id_usuario,
                       r.id_punto_emision, r.fecha_emision, r.importe_total
                FROM recibos_venta_cabecera r
                WHERE r.id_empresa = :id_empresa_r AND r.eliminado = false
                  AND r.id_caja_sesion IS NOT NULL AND r.estado != 'anulado'
                  " . str_replace('{alias}', 'r', $extra) . "
            )
        ";

        return [$sql, $params];
    }

    /**
     * Resumen por turno (arqueo): un renglón por caja_sesiones, con el total
     * realmente vendido (Facturas+Recibos enlazados) junto al fondo/monto
     * esperado/contado que ya guarda el cierre del turno.
     */
    public function getResumenTurnos(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteDocs($idEmpresa, $filtros);

        $where = "cs.id_empresa = :id_empresa AND cs.eliminado = false";
        if (!empty($filtros['id_punto_emision'])) {
            $where .= " AND cs.id_punto_emision = :id_punto_emision";
        }
        if (!empty($filtros['id_usuario'])) {
            $where .= " AND cs.id_usuario = :id_usuario";
        }
        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND cs.fecha_apertura::date >= :fecha_desde";
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND cs.fecha_apertura::date <= :fecha_hasta";
        }

        $sql = $cte . "
            SELECT
                cs.id,
                cs.fecha_apertura,
                cs.fecha_cierre,
                cs.estado,
                cs.fondo_inicial,
                cs.monto_esperado,
                cs.monto_contado,
                cs.diferencia,
                u.nombre AS cajero_nombre,
                est.codigo AS cod_establecimiento,
                pe.codigo_punto,
                COALESCE(SUM(d.importe_total), 0) AS total_vendido,
                COUNT(d.id) AS cantidad_documentos
            FROM caja_sesiones cs
            JOIN usuarios u ON u.id = cs.id_usuario
            JOIN empresa_punto_emision pe ON pe.id = cs.id_punto_emision
            JOIN empresa_establecimiento est ON est.id = pe.id_establecimiento
            LEFT JOIN docs d ON d.id_caja_sesion = cs.id
            WHERE {$where}
            GROUP BY cs.id, u.nombre, est.codigo, pe.codigo_punto
            ORDER BY cs.fecha_apertura DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ventas por forma de pago: se toma del Ingreso que el POS genera junto a
     * cada venta (PosVentaService::generarIngresoAutomatico) — es la única
     * fuente que guarda la forma de pago real de la empresa (Efectivo, banco
     * X, etc.), no solo el código genérico del SRI. Si a una venta no se le
     * pudo generar el Ingreso, cae en "Sin forma de pago vinculada".
     */
    public function getVentasPorFormaPago(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteDocs($idEmpresa, $filtros);

        $sql = $cte . "
            SELECT
                COALESCE(efp.nombre, 'Sin forma de pago vinculada') AS forma_pago,
                COALESCE(efp.tipo, '') AS tipo,
                COUNT(DISTINCT docs.id) AS cantidad_ventas,
                SUM(docs.importe_total) AS total
            FROM docs
            LEFT JOIN ingresos_detalle idet
                   ON idet.tipo_documento = docs.tipo_documento AND idet.id_referencia_documento = docs.id
            LEFT JOIN ingresos_cabecera ic
                   ON ic.id = idet.id_ingreso AND ic.estado != 'anulado' AND ic.eliminado = false
            LEFT JOIN ingresos_pagos ip ON ip.id_ingreso = ic.id
            LEFT JOIN empresa_formas_pago efp ON efp.id = ip.id_forma_cobro
            GROUP BY efp.nombre, efp.tipo
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ventas por cajero (usuario que registró cada venta del POS). */
    public function getVentasPorCajero(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteDocs($idEmpresa, $filtros);

        $sql = $cte . "
            SELECT
                u.id AS id_usuario,
                u.nombre AS cajero_nombre,
                COUNT(docs.id) AS cantidad_ventas,
                SUM(docs.importe_total) AS total
            FROM docs
            JOIN usuarios u ON u.id = docs.id_usuario
            GROUP BY u.id, u.nombre
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Productos más vendidos desde el POS (Facturas + Recibos enlazados a un turno). */
    public function getProductosMasVendidos(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteDocs($idEmpresa, $filtros);

        $sql = $cte . "
            SELECT
                producto_id, producto_codigo, producto_nombre,
                SUM(cantidad) AS cantidad_vendida, SUM(total) AS total
            FROM (
                SELECT vd.id_producto AS producto_id,
                       COALESCE(p.codigo, '') AS producto_codigo,
                       COALESCE(p.nombre, vd.descripcion) AS producto_nombre,
                       vd.cantidad AS cantidad,
                       vd.precio_total_sin_impuesto
                           + COALESCE((SELECT SUM(vi.valor) FROM ventas_detalle_impuestos vi WHERE vi.id_venta_detalle = vd.id), 0) AS total
                FROM ventas_detalle vd
                JOIN docs ON docs.tipo_documento = 'FACTURA' AND docs.id = vd.id_venta
                LEFT JOIN productos p ON p.id = vd.id_producto

                UNION ALL

                SELECT rd.id_producto,
                       COALESCE(p2.codigo, ''),
                       COALESCE(p2.nombre, rd.descripcion),
                       rd.cantidad,
                       rd.precio_total_sin_impuesto
                           + COALESCE((SELECT SUM(ri.valor) FROM recibos_venta_detalle_impuestos ri WHERE ri.id_recibo_detalle = rd.id), 0)
                FROM recibos_venta_detalle rd
                JOIN docs ON docs.tipo_documento = 'RECIBO' AND docs.id = rd.id_recibo
                LEFT JOIN productos p2 ON p2.id = rd.id_producto
            ) t
            GROUP BY producto_id, producto_codigo, producto_nombre
            ORDER BY cantidad_vendida DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Puntos de emisión que alguna vez tuvieron un turno de caja (para el filtro). */
    public function getPuntosConTurno(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT pe.id, pe.codigo_punto, est.codigo AS cod_establecimiento
                FROM caja_sesiones cs
                JOIN empresa_punto_emision pe ON pe.id = cs.id_punto_emision
                JOIN empresa_establecimiento est ON est.id = pe.id_establecimiento
                WHERE cs.id_empresa = :id_empresa AND cs.eliminado = false
                ORDER BY est.codigo, pe.codigo_punto";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cajeros (usuarios) que alguna vez abrieron un turno (para el filtro). */
    public function getCajerosConTurno(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM caja_sesiones cs
                JOIN usuarios u ON u.id = cs.id_usuario
                WHERE cs.id_empresa = :id_empresa AND cs.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** KPIs generales (para las tarjetas resumen arriba de la tabla). */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteDocs($idEmpresa, $filtros);

        $sql = $cte . "
            SELECT
                COUNT(*) AS cantidad_ventas,
                COALESCE(SUM(importe_total), 0) AS total_vendido,
                COUNT(DISTINCT id_caja_sesion) AS cantidad_turnos
            FROM docs
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['cantidad_ventas' => 0, 'total_vendido' => 0, 'cantidad_turnos' => 0];
    }
}
