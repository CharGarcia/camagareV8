<?php
declare(strict_types=1);

namespace App\Services;

use App\core\Database;
use PDO;

class DashboardService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Retorna todos los datos del dashboard según filtros.
     * @param int    $anio      0 = año actual
     * @param int    $mes       1-12 = mes específico | -1 = todo el año | 0 = mes actual
     * @param int    $cantMeses 3, 6 o 12 — meses para el gráfico de tendencia
     */
    public function getDashboardData(
        int    $idEmpresa,
        string $tipoAmbiente = '1',
        int    $anio         = 0,
        int    $mes          = 0,
        int    $cantMeses    = 6
    ): array {
        $anio      = $anio > 0 ? $anio : (int) date('Y');
        $mes       = ($mes >= -1 && $mes !== 0) ? $mes : (int) date('n');
        $cantMeses = in_array($cantMeses, [3, 6, 12]) ? $cantMeses : 6;

        $desde             = $this->fechaDesde($anio, $mes);
        $hasta             = $this->fechaHasta($anio, $mes);
        [$antDes, $antHas] = $this->periodoAnterior($anio, $mes);

        return [
            // Ventas
            'ventas_mes_actual'     => $this->sumVentas($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'ventas_mes_anterior'   => $this->sumVentas($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // Compras
            'compras_mes_actual'    => $this->sumCompras($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'compras_mes_anterior'  => $this->sumCompras($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // Ingresos
            'ingresos_mes_actual'   => $this->sumIngresos($idEmpresa, $desde, $hasta),
            'ingresos_mes_anterior' => $this->sumIngresos($idEmpresa, $antDes, $antHas),
            // Egresos
            'egresos_mes_actual'    => $this->sumEgresos($idEmpresa, $desde, $hasta),
            'egresos_mes_anterior'  => $this->sumEgresos($idEmpresa, $antDes, $antHas),
            // CxC / CxP (totales acumulados, sin filtro de período)
            'cxc_total'             => $this->getCxcTotal($idEmpresa, $tipoAmbiente),
            'cxp_total'             => $this->getCxpTotal($idEmpresa),
            // Tablas recientes
            'facturas_recientes'    => $this->getVentasRecientes($idEmpresa, 6, $tipoAmbiente),
            'compras_recientes'     => $this->getComprasRecientes($idEmpresa, 6, $tipoAmbiente),
            'ingresos_recientes'    => $this->getIngresosRecientes($idEmpresa, 5),
            'egresos_recientes'     => $this->getEgresosRecientes($idEmpresa, 5),
            // Vencidos
            'cxc_vencidas'          => $this->getCxcVencidas($idEmpresa, $tipoAmbiente, 5),
            'cxp_vencidas'          => $this->getCxpVencidas($idEmpresa, 5),
            // Gráficos
            'tendencia'             => $this->getTendenciaMensual($idEmpresa, $cantMeses, $tipoAmbiente),
            'top_productos'         => $this->getTopProductos($idEmpresa, $tipoAmbiente, $desde, $hasta, 5),
            'top_clientes'          => $this->getTopClientes($idEmpresa, $tipoAmbiente, $desde, $hasta, 5),
            // Meta
            'anio'          => $anio,
            'mes'           => $mes,
            'cant_meses'    => $cantMeses,
            'label_periodo' => $mes === -1
                ? "Año {$anio}"
                : $this->nombreMes($mes) . " {$anio}",
        ];
    }

    // ── Helpers de fechas ─────────────────────────────────────────────────────

    private function fechaDesde(int $anio, int $mes): string
    {
        if ($mes === -1) return "{$anio}-01-01";
        return sprintf('%04d-%02d-01', $anio, $mes);
    }

    private function fechaHasta(int $anio, int $mes): string
    {
        if ($mes === -1) return "{$anio}-12-31";
        $ultimo = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        return sprintf('%04d-%02d-%02d', $anio, $mes, $ultimo);
    }

    private function periodoAnterior(int $anio, int $mes): array
    {
        if ($mes === -1) return [($anio - 1) . '-01-01', ($anio - 1) . '-12-31'];
        $m = $mes - 1;
        $a = $anio;
        if ($m < 1) { $m = 12; $a--; }
        return [$this->fechaDesde($a, $m), $this->fechaHasta($a, $m)];
    }

    private function nombreMes(int $mes): string
    {
        return ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$mes] ?? '';
    }

    // ── Sumas de período ──────────────────────────────────────────────────────

    private function sumVentas(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(importe_total), 0)
             FROM ventas_cabecera
             WHERE id_empresa = ? AND eliminado = false AND estado != 'anulado'
               AND COALESCE(tipo_ambiente, '1') = ?
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $ta, $d, $h]);
        return (float) $st->fetchColumn();
    }

    private function sumCompras(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(importe_total), 0)
             FROM compras_cabecera
             WHERE id_empresa = ? AND eliminado = false
               AND COALESCE(tipo_ambiente::text, '1') = ?
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $ta, $d, $h]);
        return (float) $st->fetchColumn();
    }

    private function sumIngresos(int $e, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM ingresos_cabecera
             WHERE id_empresa = ? AND eliminado = false AND estado != 'anulado'
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $d, $h]);
        return (float) $st->fetchColumn();
    }

    private function sumEgresos(int $e, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM egresos_cabecera
             WHERE id_empresa = ? AND eliminado = false AND estado != 'anulado'
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $d, $h]);
        return (float) $st->fetchColumn();
    }

    // ── CxC / CxP ────────────────────────────────────────────────────────────

    private function getCxcTotal(int $e, string $ta): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(v.importe_total - COALESCE(c.tc, 0)), 0)
             FROM ventas_cabecera v
             LEFT JOIN (
                 SELECT d.id_referencia_documento, SUM(d.monto_cobrado) AS tc
                 FROM ingresos_detalle d
                 INNER JOIN ingresos_cabecera ic ON ic.id = d.id_ingreso
                 WHERE ic.eliminado = false AND ic.estado != 'anulado'
                 GROUP BY d.id_referencia_documento
             ) c ON c.id_referencia_documento = v.id
             WHERE v.id_empresa = ? AND v.eliminado = false
               AND v.estado NOT IN ('anulado', 'pagado')
               AND COALESCE(v.tipo_ambiente, '1') = ?
               AND (v.importe_total - COALESCE(c.tc, 0)) > 0"
        );
        $st->execute([$e, $ta]);
        return (float) $st->fetchColumn();
    }

    private function getCxpTotal(int $e): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(c.importe_total - COALESCE(p.tp, 0)), 0)
             FROM compras_cabecera c
             LEFT JOIN (
                 SELECT ed.id_referencia_documento, SUM(ed.monto_pagado) AS tp
                 FROM egresos_detalle ed
                 INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                 WHERE ed.eliminado = false AND ec.eliminado = false AND ec.estado != 'anulado'
                 GROUP BY ed.id_referencia_documento
             ) p ON p.id_referencia_documento = c.id
             WHERE c.id_empresa = ? AND c.eliminado = false
               AND (c.importe_total - COALESCE(p.tp, 0)) > 0"
        );
        $st->execute([$e]);
        return (float) $st->fetchColumn();
    }

    // ── Tablas recientes ──────────────────────────────────────────────────────

    private function getVentasRecientes(int $e, int $lim, string $ta): array
    {
        $st = $this->db->prepare(
            "SELECT cl.nombre AS entidad, v.importe_total AS total,
                    v.fecha_emision AS fecha, v.estado,
                    CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AS comprobante
             FROM ventas_cabecera v
             INNER JOIN clientes cl ON cl.id = v.id_cliente
             WHERE v.id_empresa = ? AND v.eliminado = false
               AND COALESCE(v.tipo_ambiente, '1') = ?
             ORDER BY v.fecha_emision DESC, v.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getComprasRecientes(int $e, int $lim, string $ta): array
    {
        $st = $this->db->prepare(
            "SELECT p.razon_social AS entidad, c.importe_total AS total,
                    c.fecha_emision AS fecha, 'registrado' AS estado,
                    CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS comprobante
             FROM compras_cabecera c
             INNER JOIN proveedores p ON p.id = c.id_proveedor
             WHERE c.id_empresa = ? AND c.eliminado = false
               AND COALESCE(c.tipo_ambiente::text, '1') = ?
             ORDER BY c.fecha_emision DESC, c.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getIngresosRecientes(int $e, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(i.recibo_de, cl.nombre, 'Sin nombre') AS entidad,
                    i.monto_total AS total, i.fecha_emision AS fecha,
                    i.estado, i.numero_ingreso AS comprobante
             FROM ingresos_cabecera i
             LEFT JOIN clientes cl ON cl.id = i.id_cliente
             WHERE i.id_empresa = ? AND i.eliminado = false
             ORDER BY i.fecha_emision DESC, i.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getEgresosRecientes(int $e, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(p.razon_social, emp.nombres_apellidos, 'Sin nombre') AS entidad,
                    eg.monto_total AS total, eg.fecha_emision AS fecha,
                    eg.estado, eg.numero_egreso AS comprobante
             FROM egresos_cabecera eg
             LEFT JOIN proveedores p   ON p.id   = eg.id_proveedor
             LEFT JOIN empleados  emp  ON emp.id  = eg.id_empleado
             WHERE eg.id_empresa = ? AND eg.eliminado = false
             ORDER BY eg.fecha_emision DESC, eg.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Vencidos ─────────────────────────────────────────────────────────────

    private function getCxcVencidas(int $e, string $ta, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT cl.nombre AS cliente,
                    CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AS comprobante,
                    v.fecha_emision AS fecha,
                    (v.importe_total - COALESCE(c.tc, 0)) AS saldo,
                    (CURRENT_DATE - CAST(v.fecha_emision AS DATE)) AS dias_vencido
             FROM ventas_cabecera v
             INNER JOIN clientes cl ON cl.id = v.id_cliente
             LEFT JOIN (
                 SELECT d.id_referencia_documento, SUM(d.monto_cobrado) AS tc
                 FROM ingresos_detalle d
                 INNER JOIN ingresos_cabecera ic ON ic.id = d.id_ingreso
                 WHERE ic.eliminado = false AND ic.estado != 'anulado'
                 GROUP BY d.id_referencia_documento
             ) c ON c.id_referencia_documento = v.id
             WHERE v.id_empresa = ? AND v.eliminado = false
               AND v.estado NOT IN ('anulado', 'pagado')
               AND COALESCE(v.tipo_ambiente, '1') = ?
               AND (v.importe_total - COALESCE(c.tc, 0)) > 0
               AND (CURRENT_DATE - CAST(v.fecha_emision AS DATE)) > COALESCE(cl.plazo, 0)
             ORDER BY dias_vencido DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCxpVencidas(int $e, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT p.razon_social AS proveedor,
                    CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS comprobante,
                    c.fecha_emision AS fecha,
                    (c.importe_total - COALESCE(pg.tp, 0)) AS saldo,
                    (CURRENT_DATE - CAST(c.fecha_emision AS DATE)) AS dias_vencido
             FROM compras_cabecera c
             INNER JOIN proveedores p ON p.id = c.id_proveedor
             LEFT JOIN (
                 SELECT ed.id_referencia_documento, SUM(ed.monto_pagado) AS tp
                 FROM egresos_detalle ed
                 INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                 WHERE ed.eliminado = false AND ec.eliminado = false AND ec.estado != 'anulado'
                 GROUP BY ed.id_referencia_documento
             ) pg ON pg.id_referencia_documento = c.id
             WHERE c.id_empresa = ? AND c.eliminado = false
               AND (c.importe_total - COALESCE(pg.tp, 0)) > 0
               AND (CURRENT_DATE - CAST(c.fecha_emision AS DATE)) > COALESCE(p.plazo, 0)
             ORDER BY dias_vencido DESC
             LIMIT ?"
        );
        $st->execute([$e, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Gráficos ─────────────────────────────────────────────────────────────

    private function getTendenciaMensual(int $e, int $meses, string $ta): array
    {
        $data = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $key        = date('Y-m', strtotime("-{$i} months"));
            $data[$key] = [
                'mes'      => date('M Y', strtotime("-{$i} months")),
                'ventas'   => 0, 'compras'  => 0,
                'ingresos' => 0, 'egresos'  => 0,
            ];
        }
        $desde = date('Y-m-01', strtotime('-' . ($meses - 1) . ' months'));

        // Ventas y Compras (filtran por tipoAmbiente)
        foreach ([
            'ventas'  => "SELECT TO_CHAR(CAST(fecha_emision AS DATE),'YYYY-MM') k, SUM(importe_total) t
                          FROM ventas_cabecera WHERE id_empresa=? AND eliminado=false AND estado!='anulado'
                            AND COALESCE(tipo_ambiente,'1')=? AND CAST(fecha_emision AS DATE)>=? GROUP BY k",
            'compras' => "SELECT TO_CHAR(CAST(fecha_emision AS DATE),'YYYY-MM') k, SUM(importe_total) t
                          FROM compras_cabecera WHERE id_empresa=? AND eliminado=false
                            AND COALESCE(tipo_ambiente::text,'1')=? AND CAST(fecha_emision AS DATE)>=? GROUP BY k",
        ] as $campo => $sql) {
            $st = $this->db->prepare($sql);
            $st->execute([$e, $ta, $desde]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (isset($data[$r['k']])) $data[$r['k']][$campo] = (float) $r['t'];
            }
        }

        // Ingresos y Egresos (sin filtro de ambiente)
        foreach ([
            'ingresos' => "SELECT TO_CHAR(CAST(fecha_emision AS DATE),'YYYY-MM') k, SUM(monto_total) t
                           FROM ingresos_cabecera WHERE id_empresa=? AND eliminado=false AND estado!='anulado'
                             AND CAST(fecha_emision AS DATE)>=? GROUP BY k",
            'egresos'  => "SELECT TO_CHAR(CAST(fecha_emision AS DATE),'YYYY-MM') k, SUM(monto_total) t
                           FROM egresos_cabecera WHERE id_empresa=? AND eliminado=false AND estado!='anulado'
                             AND CAST(fecha_emision AS DATE)>=? GROUP BY k",
        ] as $campo => $sql) {
            $st = $this->db->prepare($sql);
            $st->execute([$e, $desde]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (isset($data[$r['k']])) $data[$r['k']][$campo] = (float) $r['t'];
            }
        }

        return array_values($data);
    }

    private function getTopProductos(int $e, string $ta, string $d, string $h, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(p.nombre, det.descripcion) AS nombre,
                    SUM(det.cantidad) AS cantidad,
                    SUM(det.precio_total_sin_impuesto) AS total
             FROM ventas_detalle det
             INNER JOIN ventas_cabecera v ON v.id = det.id_venta
             LEFT JOIN productos p ON p.id = det.id_producto
             WHERE v.id_empresa = ? AND v.eliminado = false AND v.estado != 'anulado'
               AND COALESCE(v.tipo_ambiente, '1') = ?
               AND CAST(v.fecha_emision AS DATE) BETWEEN ? AND ?
             GROUP BY COALESCE(p.nombre, det.descripcion)
             ORDER BY total DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $d, $h, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopClientes(int $e, string $ta, string $d, string $h, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT cl.nombre, SUM(v.importe_total) AS total, COUNT(v.id) AS facturas
             FROM ventas_cabecera v
             INNER JOIN clientes cl ON cl.id = v.id_cliente
             WHERE v.id_empresa = ? AND v.eliminado = false AND v.estado != 'anulado'
               AND COALESCE(v.tipo_ambiente, '1') = ?
               AND CAST(v.fecha_emision AS DATE) BETWEEN ? AND ?
             GROUP BY cl.nombre
             ORDER BY total DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $d, $h, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
