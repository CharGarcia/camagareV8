<?php
declare(strict_types=1);

namespace App\Services\modulos;

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
            'ingresos_mes_actual'   => $this->sumIngresos($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'ingresos_mes_anterior' => $this->sumIngresos($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // Egresos
            'egresos_mes_actual'    => $this->sumEgresos($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'egresos_mes_anterior'  => $this->sumEgresos($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // CxC / CxP filtradas por período seleccionado
            'cxc_total'             => $this->getCxcTotal($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'cxp_total'             => $this->getCxpTotal($idEmpresa, $tipoAmbiente, $desde, $hasta),
            // Saldos de caja: bancos/efectivo (saldo real actual) y anticipos globales.
            // Estado puntual, NO filtrado por período.
            'saldos_caja'           => $this->getSaldosCaja($idEmpresa),
            // Tablas recientes
            'facturas_recientes'    => $this->getVentasRecientes($idEmpresa, 6, $tipoAmbiente),
            'compras_recientes'     => $this->getComprasRecientes($idEmpresa, 6, $tipoAmbiente),
            'ingresos_recientes'    => $this->getIngresosRecientes($idEmpresa, 5, $tipoAmbiente),
            'egresos_recientes'     => $this->getEgresosRecientes($idEmpresa, 5, $tipoAmbiente),
            // Vencidos
            'cxc_vencidas'          => $this->getCxcVencidas($idEmpresa, $tipoAmbiente, 5),
            'cxp_vencidas'          => $this->getCxpVencidas($idEmpresa, $tipoAmbiente, 5),
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

    private function sumIngresos(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM ingresos_cabecera
             WHERE id_empresa = ? AND eliminado = false AND estado != 'anulado'
               AND tipo_ambiente = ?
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $ta, $d, $h]);
        return (float) $st->fetchColumn();
    }

    private function sumEgresos(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM egresos_cabecera
             WHERE id_empresa = ? AND eliminado = false AND estado != 'anulado'
               AND tipo_ambiente = ?
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $ta, $d, $h]);
        return (float) $st->fetchColumn();
    }

    // ── CxC / CxP ────────────────────────────────────────────────────────────

    private function getCxcTotal(int $e, string $ta, string $d, string $h): float
    {
        // Saldo neto = importe − cobrado − retenido − NC (igual que el módulo CxC).
        // $e es int validado → interpolación segura en los subqueries.
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(v.importe_total - COALESCE(c.tc, 0) - COALESCE(rt.tr, 0) - COALESCE(ncv.tnc, 0)), 0)
             FROM ventas_cabecera v
             LEFT JOIN (
                 SELECT d.id_referencia_documento, SUM(d.monto_cobrado) AS tc
                 FROM ingresos_detalle d
                 INNER JOIN ingresos_cabecera ic ON ic.id = d.id_ingreso
                 WHERE ic.eliminado = false AND ic.estado != 'anulado'
                 GROUP BY d.id_referencia_documento
             ) c ON c.id_referencia_documento = v.id
             LEFT JOIN (
                 SELECT r.id_venta, SUM(r.total_renta + r.total_iva + r.total_isd) AS tr
                 FROM retencion_venta_cabecera r
                 WHERE r.eliminado = false AND r.id_venta IS NOT NULL
                 GROUP BY r.id_venta
             ) rt ON rt.id_venta = v.id
             LEFT JOIN (
                 SELECT nc.num_doc_modificado, SUM(nc.importe_total) AS tnc
                 FROM notas_credito_cabecera nc
                 WHERE nc.eliminado = false AND nc.estado != 'anulado' AND nc.id_empresa = {$e}
                 GROUP BY nc.num_doc_modificado
             ) ncv ON ncv.num_doc_modificado = CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial)
             WHERE v.id_empresa = ? AND v.eliminado = false
               AND v.estado NOT IN ('anulado', 'pagado')
               AND COALESCE(v.tipo_ambiente, '1') = ?
               AND CAST(v.fecha_emision AS DATE) BETWEEN ? AND ?
               AND (v.importe_total - COALESCE(c.tc, 0) - COALESCE(rt.tr, 0) - COALESCE(ncv.tnc, 0)) > 0"
        );
        $st->execute([$e, $ta, $d, $h]);
        $total = (float) $st->fetchColumn();

        // Sumar los saldos iniciales CxC pendientes del período (pendiente
        // descuenta lo retenido, igual que el módulo).
        $si = $this->db->prepare(
            "SELECT COALESCE(SUM(t.pend), 0) FROM (
                SELECT (s.saldo_inicial - s.monto_cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0)) AS pend
                FROM saldos_iniciales_cxc s
                LEFT JOIN LATERAL (
                    SELECT SUM(rd.valor_retenido) AS retenido
                    FROM retencion_venta_detalle rd
                    INNER JOIN retencion_venta_cabecera r ON r.id = rd.id_retencion
                    WHERE r.eliminado = false
                      AND r.id_empresa = s.id_empresa
                      AND r.id_venta IS NULL
                      AND r.id_cliente = s.id_cliente
                      AND rd.num_doc_sustento IS NOT NULL
                      AND rd.num_doc_sustento <> ''
                      AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa
                            AND vc.eliminado = false
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                ) ret ON true
                LEFT JOIN LATERAL (
                    SELECT SUM(ncc.importe_total) AS nc_total
                    FROM notas_credito_cabecera ncc
                    WHERE ncc.eliminado  = false
                      AND ncc.estado    != 'anulado'
                      AND ncc.id_empresa = s.id_empresa
                      AND regexp_replace(ncc.num_doc_modificado, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa
                            AND vc.eliminado = false
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                ) ncsi ON true
                WHERE s.id_empresa = ? AND s.eliminado = false
                  AND s.fecha_emision BETWEEN ? AND ?
            ) t WHERE t.pend > 0"
        );
        $si->execute([$e, $d, $h]);
        return $total + (float) $si->fetchColumn();
    }

    private function getCxpTotal(int $e, string $ta, string $d, string $h): float
    {
        // Saldo neto = importe − pagado − retenido − NC(04) + ND(05), solo sobre
        // facturas de compra (tipo_comprobante '01'). Antes se sumaban las propias
        // NC/ND (04/05) como documentos por pagar y no se restaban de la factura.
        // $e es int validado → interpolación segura en los subqueries.
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(c.importe_total - COALESCE(p.tp, 0) - COALESCE(r.tr, 0)
                                 - COALESCE(nn.tnc, 0) + COALESCE(nn.tnd, 0)), 0)
             FROM compras_cabecera c
             LEFT JOIN (
                 SELECT ed.id_referencia_documento, SUM(ed.monto_pagado) AS tp
                 FROM egresos_detalle ed
                 INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                 WHERE ed.eliminado = false AND ec.eliminado = false AND ec.estado != 'anulado'
                 GROUP BY ed.id_referencia_documento
             ) p ON p.id_referencia_documento = c.id
             LEFT JOIN (
                 SELECT rc.id_compra, SUM(rc.total_retenido) AS tr
                 FROM retencion_compra_cabecera rc
                 WHERE rc.eliminado = false
                   AND UPPER(rc.estado) NOT IN ('ANULADO', 'BORRADOR', 'PENDIENTE')
                   AND rc.id_compra IS NOT NULL
                 GROUP BY rc.id_compra
             ) r ON r.id_compra = c.id
             LEFT JOIN (
                 SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
                        SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS tnc,
                        SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS tnd
                 FROM compras_cabecera nc
                 WHERE nc.tipo_comprobante IN ('04', '05') AND nc.eliminado = false AND nc.id_empresa = {$e}
                 GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
             ) nn ON nn.id_empresa = c.id_empresa AND nn.id_proveedor = c.id_proveedor
                 AND nn.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov)
             WHERE c.id_empresa = ? AND c.eliminado = false
               AND c.tipo_comprobante = '01'
               AND COALESCE(c.tipo_ambiente::text, '1') = ?
               AND CAST(c.fecha_emision AS DATE) BETWEEN ? AND ?
               AND (c.importe_total - COALESCE(p.tp, 0) - COALESCE(r.tr, 0)
                    - COALESCE(nn.tnc, 0) + COALESCE(nn.tnd, 0)) > 0"
        );
        $st->execute([$e, $ta, $d, $h]);
        $total = (float) $st->fetchColumn();

        // Sumar los saldos iniciales CxP pendientes del período.
        $si = $this->db->prepare(
            "SELECT COALESCE(SUM(saldo_pendiente), 0)
             FROM saldos_iniciales_cxp
             WHERE id_empresa = ? AND eliminado = false
               AND saldo_pendiente > 0
               AND fecha_emision BETWEEN ? AND ?"
        );
        $si->execute([$e, $d, $h]);
        return $total + (float) $si->fetchColumn();
    }

    // ── Saldos de caja: bancos/efectivo y anticipos ───────────────────────────

    /**
     * Saldos de bancos/efectivo (saldo real actual por forma de pago) y
     * anticipos globales (clientes y proveedores). Reutiliza la misma lógica
     * de cálculo del módulo de Ingresos (FormaPagoRepository::getSaldosActuales
     * y getSaldoAnticipo), pero los anticipos se agregan de forma global sin
     * depender de un tercero. Estado puntual: NO se filtra por período.
     *
     * Si alguna tabla de saldos iniciales aún no existe en el entorno
     * (migración no aplicada), degrada con elegancia y no tumba el dashboard.
     */
    private function getSaldosCaja(int $e): array
    {
        try {
            return $this->calcularSaldosCaja($e);
        } catch (\Throwable $ex) {
            return [
                'formas'                => [],
                'anticipos_clientes'    => 0.0,
                'anticipos_proveedores' => 0.0,
                'tiene_datos'           => false,
            ];
        }
    }

    private function calcularSaldosCaja(int $e): array
    {
        // ── Bancos / Efectivo / Tarjeta / Otro: saldo real actual por forma ──
        //   saldo = saldo_inicial (saldos_iniciales_bancos)
        //           + Σ cobros (ingresos_pagos)  − Σ pagos (egresos_pagos)
        //   Filtrado por el ambiente real de la empresa (igual que Ingresos).
        $sqlFormas = "
            SELECT efp.id, efp.nombre, efp.tipo,
                   COALESCE(sib.saldo_inicial, 0)
                   + COALESCE(ing.total, 0)
                   - COALESCE(egr.total, 0) AS saldo
            FROM empresa_formas_pago efp
            LEFT JOIN saldos_iniciales_bancos sib
                   ON sib.id_forma_pago = efp.id
                  AND sib.id_empresa   = efp.id_empresa
                  AND sib.eliminado    = FALSE
            LEFT JOIN (
                SELECT ip.id_forma_cobro AS id_forma, SUM(ip.monto) AS total
                FROM ingresos_pagos ip
                INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                WHERE ic.id_empresa = :e
                  AND ic.eliminado  = FALSE
                  AND ic.estado    <> 'anulado'
                  AND ic.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                GROUP BY ip.id_forma_cobro
            ) ing ON ing.id_forma = efp.id
            LEFT JOIN (
                SELECT ep.id_forma_pago AS id_forma, SUM(ep.monto) AS total
                FROM egresos_pagos ep
                INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                WHERE ec.id_empresa = :e
                  AND ec.eliminado  = FALSE
                  AND ec.estado    <> 'anulado'
                  AND ep.eliminado  = FALSE
                  AND ec.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                GROUP BY ep.id_forma_pago
            ) egr ON egr.id_forma = efp.id
            WHERE efp.id_empresa = :e
              AND efp.eliminado  = FALSE
              AND efp.activo     = TRUE
              AND efp.tipo      <> 'ANTICIPO'
            ORDER BY efp.tipo, efp.nombre";
        $st = $this->db->prepare($sqlFormas);
        $st->execute([':e' => $e]);
        $formas = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $formas[] = [
                'id'     => (int) $r['id'],
                'nombre' => $r['nombre'],
                'tipo'   => $r['tipo'],
                'saldo'  => (float) $r['saldo'],
            ];
        }

        return [
            'formas'                => $formas,
            'anticipos_clientes'    => $this->getAnticipoGlobal($e, 'CLIENTE'),
            'anticipos_proveedores' => $this->getAnticipoGlobal($e, 'PROVEEDOR'),
            'tiene_datos'           => true,
        ];
    }

    /**
     * Saldo global de anticipos por dirección (sin depender de un tercero):
     *   saldo = Σ saldo_inicial (saldos_iniciales_anticipos del tipo)
     *         + Σ generado (ingresos/egresos con opción ANTICIPO_CLIENTE/PROVEEDOR)
     *         − Σ aplicado (pagos que consumen formas de anticipo de esa dirección)
     * Misma lógica que FormaPagoRepository::getSaldoAnticipo pero agregada (todos los terceros).
     */
    private function getAnticipoGlobal(int $e, string $tipo): float
    {
        $ini = $this->db->prepare(
            "SELECT COALESCE(SUM(saldo_inicial), 0)
             FROM saldos_iniciales_anticipos
             WHERE id_empresa = :e AND eliminado = FALSE AND tipo = :t"
        );
        $ini->execute([':e' => $e, ':t' => $tipo]);
        $inicial = (float) $ini->fetchColumn();

        // Generado: anticipos registrados con una opción ANTICIPO_CLIENTE/PROVEEDOR.
        if ($tipo === 'PROVEEDOR') {
            $gen = $this->db->prepare(
                "SELECT COALESCE(SUM(ec.monto_total), 0)
                 FROM egresos_cabecera ec
                 INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = ec.id_egreso_concepto
                 WHERE ec.id_empresa = :e AND ec.eliminado = FALSE AND ec.estado <> 'anulado'
                   AND o.comportamiento = 'ANTICIPO_PROVEEDOR'"
            );
        } else {
            $gen = $this->db->prepare(
                "SELECT COALESCE(SUM(ic.monto_total), 0)
                 FROM ingresos_cabecera ic
                 INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = ic.id_ingreso_concepto
                 WHERE ic.id_empresa = :e AND ic.eliminado = FALSE AND ic.estado <> 'anulado'
                   AND o.comportamiento = 'ANTICIPO_CLIENTE'"
            );
        }
        $gen->execute([':e' => $e]);
        $generado = (float) $gen->fetchColumn();

        if ($tipo === 'PROVEEDOR') {
            $apl = $this->db->prepare(
                "SELECT COALESCE(SUM(ep.monto), 0)
                 FROM egresos_pagos ep
                 INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                 INNER JOIN empresa_formas_pago efp ON efp.id = ep.id_forma_pago
                 WHERE ec.id_empresa = :e AND ec.eliminado = FALSE AND ec.estado <> 'anulado'
                   AND ep.eliminado = FALSE
                   AND efp.tipo = 'ANTICIPO' AND UPPER(efp.aplica_en) = 'EGRESO'"
            );
        } else {
            $apl = $this->db->prepare(
                "SELECT COALESCE(SUM(ip.monto), 0)
                 FROM ingresos_pagos ip
                 INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                 INNER JOIN empresa_formas_pago efp ON efp.id = ip.id_forma_cobro
                 WHERE ic.id_empresa = :e AND ic.eliminado = FALSE AND ic.estado <> 'anulado'
                   AND efp.tipo = 'ANTICIPO' AND UPPER(efp.aplica_en) = 'INGRESO'"
            );
        }
        $apl->execute([':e' => $e]);
        $aplicado = (float) $apl->fetchColumn();

        return round($inicial + $generado - $aplicado, 2);
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

    private function getIngresosRecientes(int $e, int $lim, string $ta): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(i.recibo_de, cl.nombre, 'Sin nombre') AS entidad,
                    i.monto_total AS total, i.fecha_emision AS fecha,
                    i.estado, i.numero_ingreso AS comprobante
             FROM ingresos_cabecera i
             LEFT JOIN clientes cl ON cl.id = i.id_cliente
             WHERE i.id_empresa = ? AND i.eliminado = false
               AND i.tipo_ambiente = ?
             ORDER BY i.fecha_emision DESC, i.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getEgresosRecientes(int $e, int $lim, string $ta): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(p.razon_social, emp.nombres_apellidos, 'Sin nombre') AS entidad,
                    eg.monto_total AS total, eg.fecha_emision AS fecha,
                    eg.estado, eg.numero_egreso AS comprobante
             FROM egresos_cabecera eg
             LEFT JOIN proveedores p  ON p.id  = eg.id_proveedor
             LEFT JOIN empleados  emp ON emp.id = eg.id_empleado
             WHERE eg.id_empresa = ? AND eg.eliminado = false
               AND eg.tipo_ambiente = ?
             ORDER BY eg.fecha_emision DESC, eg.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Vencidos ─────────────────────────────────────────────────────────────

    private function getCxcVencidas(int $e, string $ta, int $lim): array
    {
        // Une las facturas de venta vencidas con los saldos iniciales CxC
        // vencidos (que tienen su propia fecha_vencimiento). En los saldos
        // iniciales el pendiente descuenta lo retenido, igual que el módulo.
        $st = $this->db->prepare(
            "SELECT cliente, comprobante, fecha, saldo, dias_vencido FROM (
                SELECT cl.nombre AS cliente,
                       CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AS comprobante,
                       v.fecha_emision AS fecha,
                       (v.importe_total - COALESCE(c.tc, 0) - COALESCE(rt.tr, 0) - COALESCE(ncv.tnc, 0)) AS saldo,
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
                LEFT JOIN (
                    SELECT r.id_venta, SUM(r.total_renta + r.total_iva + r.total_isd) AS tr
                    FROM retencion_venta_cabecera r
                    WHERE r.eliminado = false AND r.id_venta IS NOT NULL
                    GROUP BY r.id_venta
                ) rt ON rt.id_venta = v.id
                LEFT JOIN (
                    SELECT nc.num_doc_modificado, SUM(nc.importe_total) AS tnc
                    FROM notas_credito_cabecera nc
                    WHERE nc.eliminado = false AND nc.estado != 'anulado' AND nc.id_empresa = {$e}
                    GROUP BY nc.num_doc_modificado
                ) ncv ON ncv.num_doc_modificado = CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial)
                WHERE v.id_empresa = :e AND v.eliminado = false
                  AND v.estado NOT IN ('anulado', 'pagado')
                  AND COALESCE(v.tipo_ambiente, '1') = :ta
                  AND (v.importe_total - COALESCE(c.tc, 0) - COALESCE(rt.tr, 0) - COALESCE(ncv.tnc, 0)) > 0
                  AND (CURRENT_DATE - CAST(v.fecha_emision AS DATE)) > COALESCE(cl.plazo, 0)

                UNION ALL

                SELECT s.nombre_cliente AS cliente,
                       s.nro_documento AS comprobante,
                       s.fecha_emision AS fecha,
                       (s.saldo_inicial - s.monto_cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0)) AS saldo,
                       (CURRENT_DATE - s.fecha_vencimiento)::int AS dias_vencido
                FROM saldos_iniciales_cxc s
                LEFT JOIN LATERAL (
                    SELECT SUM(rd.valor_retenido) AS retenido
                    FROM retencion_venta_detalle rd
                    INNER JOIN retencion_venta_cabecera r ON r.id = rd.id_retencion
                    WHERE r.eliminado = false
                      AND r.id_empresa = s.id_empresa
                      AND r.id_venta IS NULL
                      AND r.id_cliente = s.id_cliente
                      AND rd.num_doc_sustento IS NOT NULL
                      AND rd.num_doc_sustento <> ''
                      AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa
                            AND vc.eliminado = false
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                ) ret ON true
                LEFT JOIN LATERAL (
                    SELECT SUM(ncc.importe_total) AS nc_total
                    FROM notas_credito_cabecera ncc
                    WHERE ncc.eliminado  = false
                      AND ncc.estado    != 'anulado'
                      AND ncc.id_empresa = s.id_empresa
                      AND regexp_replace(ncc.num_doc_modificado, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa
                            AND vc.eliminado = false
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                ) ncsi ON true
                WHERE s.id_empresa = :e2 AND s.eliminado = false
                  AND s.fecha_vencimiento IS NOT NULL
                  AND s.fecha_vencimiento < CURRENT_DATE
                  AND (s.saldo_inicial - s.monto_cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0)) > 0
            ) u
            ORDER BY dias_vencido DESC
            LIMIT :lim"
        );
        $st->bindValue(':e',   $e,   PDO::PARAM_INT);
        $st->bindValue(':ta',  $ta,  PDO::PARAM_STR);
        $st->bindValue(':e2',  $e,   PDO::PARAM_INT);
        $st->bindValue(':lim', $lim, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCxpVencidas(int $e, string $ta, int $lim): array
    {
        // Une las compras vencidas con los saldos iniciales CxP vencidos.
        $st = $this->db->prepare(
            "SELECT proveedor, comprobante, fecha, saldo, dias_vencido FROM (
                SELECT p.razon_social AS proveedor,
                       CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS comprobante,
                       c.fecha_emision AS fecha,
                       (c.importe_total - COALESCE(pg.tp, 0) - COALESCE(r.tr, 0) - COALESCE(nn.tnc, 0) + COALESCE(nn.tnd, 0)) AS saldo,
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
                LEFT JOIN (
                    SELECT rc.id_compra, SUM(rc.total_retenido) AS tr
                    FROM retencion_compra_cabecera rc
                    WHERE rc.eliminado = false
                      AND UPPER(rc.estado) NOT IN ('ANULADO', 'BORRADOR', 'PENDIENTE')
                      AND rc.id_compra IS NOT NULL
                    GROUP BY rc.id_compra
                ) r ON r.id_compra = c.id
                LEFT JOIN (
                    SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
                           SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS tnc,
                           SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS tnd
                    FROM compras_cabecera nc
                    WHERE nc.tipo_comprobante IN ('04', '05') AND nc.eliminado = false AND nc.id_empresa = {$e}
                    GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
                ) nn ON nn.id_empresa = c.id_empresa AND nn.id_proveedor = c.id_proveedor
                    AND nn.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov)
                WHERE c.id_empresa = :e AND c.eliminado = false
                  AND c.tipo_comprobante = '01'
                  AND COALESCE(c.tipo_ambiente::text, '1') = :ta
                  AND (c.importe_total - COALESCE(pg.tp, 0) - COALESCE(r.tr, 0) - COALESCE(nn.tnc, 0) + COALESCE(nn.tnd, 0)) > 0
                  AND (CURRENT_DATE - CAST(c.fecha_emision AS DATE)) > COALESCE(p.plazo, 0)

                UNION ALL

                SELECT s.nombre_proveedor AS proveedor,
                       s.nro_documento AS comprobante,
                       s.fecha_emision AS fecha,
                       s.saldo_pendiente AS saldo,
                       (CURRENT_DATE - s.fecha_vencimiento)::int AS dias_vencido
                FROM saldos_iniciales_cxp s
                WHERE s.id_empresa = :e2 AND s.eliminado = false
                  AND s.fecha_vencimiento IS NOT NULL
                  AND s.fecha_vencimiento < CURRENT_DATE
                  AND s.saldo_pendiente > 0
            ) u
            ORDER BY dias_vencido DESC
            LIMIT :lim"
        );
        $st->bindValue(':e',   $e,   PDO::PARAM_INT);
        $st->bindValue(':ta',  $ta,  PDO::PARAM_STR);
        $st->bindValue(':e2',  $e,   PDO::PARAM_INT);
        $st->bindValue(':lim', $lim, PDO::PARAM_INT);
        $st->execute();
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
