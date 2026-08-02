<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Estado de cuenta (kardex) cronológico de un cliente o proveedor: une todos
 * los documentos que cargan saldo (facturas/compras, recibos, ND, saldo
 * inicial) con todos los movimientos que abonan (cobros/pagos, retenciones,
 * NC) y calcula el saldo corriendo. Sin ATTR_EMULATE_PREPARES, cada
 * ocurrencia de un mismo valor en el UNION ALL necesita su propio nombre de
 * parámetro (ver app/repositories/modulos/SoporteChatRepository.php).
 */
class ReporteCarteraRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    // ─────────────────────────────────────────────────────────────────────
    // DATOS DE LA ENTIDAD
    // ─────────────────────────────────────────────────────────────────────

    public function getClientePorId(int $idEmpresa, int $idCliente): ?array
    {
        $sql = "SELECT id, nombre, identificacion, COALESCE(email,'') AS email, COALESCE(telefono,'') AS telefono
                FROM clientes
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCliente, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getProveedorPorId(int $idEmpresa, int $idProveedor): ?array
    {
        $sql = "SELECT id, razon_social AS nombre, identificacion, COALESCE(email,'') AS email, COALESCE(telefono,'') AS telefono
                FROM proveedores
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idProveedor, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // FRAGMENTO DE FECHA (cada subquery del UNION usa su propio sufijo de
    // parámetro; no se puede repetir un :nombre en PDO sin EMULATE_PREPARES)
    // ─────────────────────────────────────────────────────────────────────

    private function fechaWhere(string $col, string $suffix, ?string $fechaDesde, ?string $fechaHasta, array &$params): string
    {
        $sql = '';
        if ($fechaDesde) {
            $sql .= " AND {$col} >= :fd_{$suffix}";
            $params[":fd_{$suffix}"] = $fechaDesde;
        }
        if ($fechaHasta) {
            $sql .= " AND {$col} <= :fh_{$suffix}";
            $params[":fh_{$suffix}"] = $fechaHasta;
        }
        return $sql;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CLIENTE (CxC)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Movimientos cronológicos de un cliente: CARGO (factura/recibo/ND/saldo
     * inicial) y ABONO (cobro/retención/NC). $fechaDesde/$fechaHasta son
     * opcionales (null = sin límite).
     */
    public function getMovimientosCliente(int $idEmpresa, int $idCliente, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $params = [];
        $fVenta  = $this->fechaWhere('v.fecha_emision', 'v',  $fechaDesde, $fechaHasta, $params);
        $fRecibo = $this->fechaWhere('v.fecha_emision', 'rv', $fechaDesde, $fechaHasta, $params);
        $fNd     = $this->fechaWhere('nd.fecha_emision', 'nd', $fechaDesde, $fechaHasta, $params);
        $fSi     = $this->fechaWhere('s.fecha_emision', 'si', $fechaDesde, $fechaHasta, $params);
        $fIc     = $this->fechaWhere('ic.fecha_emision', 'ic', $fechaDesde, $fechaHasta, $params);
        $fR      = $this->fechaWhere('r.fecha_emision', 'r',  $fechaDesde, $fechaHasta, $params);
        $fNc     = $this->fechaWhere('nc.fecha_emision', 'nc', $fechaDesde, $fechaHasta, $params);

        $params += [
            ':emp1' => $idEmpresa, ':cli1' => $idCliente,
            ':emp2' => $idEmpresa, ':cli2' => $idCliente,
            ':emp3' => $idEmpresa, ':cli3' => $idCliente,
            ':emp4' => $idEmpresa, ':cli4' => $idCliente,
            ':emp5' => $idEmpresa, ':cli5' => $idCliente,
            ':emp6' => $idEmpresa, ':cli6' => $idCliente,
            ':emp7' => $idEmpresa, ':cli7' => $idCliente,
        ];

        $sql = "
            SELECT * FROM (
                -- FACTURAS DE VENTA (CARGO)
                SELECT v.fecha_emision::date AS fecha, 'CARGO'::text AS tipo_movimiento, 1 AS signo, 'FACTURA' AS origen,
                       CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_documento,
                       'Factura de Venta' AS detalle, v.importe_total AS monto, v.id AS id_orden
                FROM ventas_cabecera v
                WHERE v.id_empresa = :emp1 AND v.eliminado = false AND v.id_cliente = :cli1
                  AND v.estado IN ('autorizado','autorizada') {$fVenta}

                UNION ALL

                -- RECIBOS DE VENTA (CARGO)
                SELECT v.fecha_emision::date, 'CARGO', 1, 'RECIBO',
                       CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial),
                       'Recibo de Venta', v.importe_total, v.id
                FROM recibos_venta_cabecera v
                WHERE v.id_empresa = :emp2 AND v.eliminado = false AND v.id_cliente = :cli2
                  AND v.estado NOT IN ('borrador','anulado','facturado') {$fRecibo}

                UNION ALL

                -- NOTAS DE DÉBITO EMITIDAS (CARGO)
                SELECT nd.fecha_emision::date, 'CARGO', 1, 'NOTA_DEBITO',
                       CONCAT(nd.establecimiento,'-',nd.punto_emision,'-',nd.secuencial),
                       CONCAT('Nota de Débito (ref. ', nd.num_doc_modificado, ')'),
                       nd.importe_total, nd.id
                FROM nota_debito_cabecera nd
                WHERE nd.id_empresa = :emp3 AND nd.eliminado = false AND nd.id_cliente = :cli3
                  AND nd.estado != 'anulado' {$fNd}

                UNION ALL

                -- SALDO INICIAL CXC (CARGO)
                SELECT s.fecha_emision::date, 'CARGO', 1, 'SALDO_INICIAL', s.nro_documento,
                       'Saldo Inicial', s.saldo_inicial, s.id
                FROM saldos_iniciales_cxc s
                WHERE s.id_empresa = :emp4 AND s.eliminado = false AND s.id_cliente = :cli4 {$fSi}

                UNION ALL

                -- COBROS (ABONO)
                SELECT ic.fecha_emision::date, 'ABONO', -1, 'COBRO', ic.numero_ingreso,
                       CASE WHEN COALESCE(ic.observaciones,'') <> '' THEN CONCAT('Cobro - ', ic.observaciones) ELSE 'Cobro' END,
                       idet.monto_cobrado, ic.id
                FROM ingresos_detalle idet
                INNER JOIN ingresos_cabecera ic ON ic.id = idet.id_ingreso
                WHERE ic.id_empresa = :emp5 AND ic.eliminado = false AND ic.estado != 'anulado'
                  AND ic.id_cliente = :cli5
                  AND idet.tipo_documento IN ('FACTURA','RECIBO') {$fIc}

                UNION ALL

                -- RETENCIONES DE VENTA (ABONO)
                SELECT r.fecha_emision::date, 'ABONO', -1, 'RETENCION',
                       CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial),
                       'Retención', (r.total_renta + r.total_iva + r.total_isd), r.id
                FROM retencion_venta_cabecera r
                WHERE r.id_empresa = :emp6 AND r.eliminado = false AND r.id_cliente = :cli6 {$fR}

                UNION ALL

                -- NOTAS DE CRÉDITO EMITIDAS (ABONO)
                SELECT nc.fecha_emision::date, 'ABONO', -1, 'NOTA_CREDITO',
                       CONCAT(nc.establecimiento,'-',nc.punto_emision,'-',nc.secuencial),
                       CONCAT('Nota de Crédito (ref. ', nc.num_doc_modificado, ')'),
                       nc.importe_total, nc.id
                FROM notas_credito_cabecera nc
                WHERE nc.id_empresa = :emp7 AND nc.eliminado = false AND nc.id_cliente = :cli7
                  AND nc.estado != 'anulado' {$fNc}
            ) mov
            ORDER BY fecha ASC, id_orden ASC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Saldo acumulado del cliente antes de $fechaDesde (para arrancar el
     * saldo corriente del rango filtrado). Reutiliza getMovimientosCliente
     * con fecha_hasta = fechaDesde - 1 día.
     */
    public function getSaldoAnteriorCliente(int $idEmpresa, int $idCliente, string $fechaDesde): float
    {
        $hasta = date('Y-m-d', strtotime($fechaDesde . ' -1 day'));
        $movs = $this->getMovimientosCliente($idEmpresa, $idCliente, null, $hasta);

        $saldo = 0.0;
        foreach ($movs as $m) {
            $saldo += ((int) $m['signo']) * (float) $m['monto'];
        }
        return $saldo;
    }

    /**
     * Clientes de la empresa cuyo saldo acumulado (a la fecha de corte, o a
     * hoy si no se indica) es mayor a cero. Se agrupa en una sola consulta
     * (en vez de calcular el saldo cliente por cliente) para poder ofrecer
     * la opción "Todos" sin un costo N+1.
     */
    public function getClientesConSaldoPendiente(int $idEmpresa, ?string $fechaHasta): array
    {
        $params = [];
        $fVenta  = $this->fechaWhere('v.fecha_emision', 'v',  null, $fechaHasta, $params);
        $fRecibo = $this->fechaWhere('v.fecha_emision', 'rv', null, $fechaHasta, $params);
        $fNd     = $this->fechaWhere('nd.fecha_emision', 'nd', null, $fechaHasta, $params);
        $fSi     = $this->fechaWhere('s.fecha_emision', 'si', null, $fechaHasta, $params);
        $fIc     = $this->fechaWhere('ic.fecha_emision', 'ic', null, $fechaHasta, $params);
        $fR      = $this->fechaWhere('r.fecha_emision', 'r',  null, $fechaHasta, $params);
        $fNc     = $this->fechaWhere('nc.fecha_emision', 'nc', null, $fechaHasta, $params);

        $params += [
            ':emp1' => $idEmpresa, ':emp2' => $idEmpresa, ':emp3' => $idEmpresa, ':emp4' => $idEmpresa,
            ':emp5' => $idEmpresa, ':emp6' => $idEmpresa, ':emp7' => $idEmpresa, ':emp_cli' => $idEmpresa,
        ];

        $sql = "
            SELECT cli.id, cli.nombre, cli.identificacion
            FROM (
                SELECT id_cliente, SUM(signo * monto) AS saldo
                FROM (
                    SELECT v.id_cliente, 1 AS signo, v.importe_total AS monto
                    FROM ventas_cabecera v
                    WHERE v.id_empresa = :emp1 AND v.eliminado = false
                      AND v.estado IN ('autorizado','autorizada') {$fVenta}

                    UNION ALL

                    SELECT v.id_cliente, 1, v.importe_total
                    FROM recibos_venta_cabecera v
                    WHERE v.id_empresa = :emp2 AND v.eliminado = false
                      AND v.estado NOT IN ('borrador','anulado','facturado') {$fRecibo}

                    UNION ALL

                    SELECT nd.id_cliente, 1, nd.importe_total
                    FROM nota_debito_cabecera nd
                    WHERE nd.id_empresa = :emp3 AND nd.eliminado = false
                      AND nd.estado != 'anulado' {$fNd}

                    UNION ALL

                    SELECT s.id_cliente, 1, s.saldo_inicial
                    FROM saldos_iniciales_cxc s
                    WHERE s.id_empresa = :emp4 AND s.eliminado = false AND s.id_cliente IS NOT NULL {$fSi}

                    UNION ALL

                    SELECT ic.id_cliente, -1, idet.monto_cobrado
                    FROM ingresos_detalle idet
                    INNER JOIN ingresos_cabecera ic ON ic.id = idet.id_ingreso
                    WHERE ic.id_empresa = :emp5 AND ic.eliminado = false AND ic.estado != 'anulado'
                      AND ic.id_cliente IS NOT NULL
                      AND idet.tipo_documento IN ('FACTURA','RECIBO') {$fIc}

                    UNION ALL

                    SELECT r.id_cliente, -1, (r.total_renta + r.total_iva + r.total_isd)
                    FROM retencion_venta_cabecera r
                    WHERE r.id_empresa = :emp6 AND r.eliminado = false {$fR}

                    UNION ALL

                    SELECT nc.id_cliente, -1, nc.importe_total
                    FROM notas_credito_cabecera nc
                    WHERE nc.id_empresa = :emp7 AND nc.eliminado = false
                      AND nc.estado != 'anulado' {$fNc}
                ) mov
                GROUP BY id_cliente
                HAVING SUM(signo * monto) > 0.005
            ) agg
            JOIN clientes cli ON cli.id = agg.id_cliente
            WHERE cli.id_empresa = :emp_cli AND cli.eliminado = false
            ORDER BY cli.nombre ASC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PROVEEDOR (CxP)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Movimientos cronológicos de un proveedor: CARGO (factura/liquidación/
     * importación/ND recibida/saldo inicial) y ABONO (pago/retención/NC
     * recibida). NC y ND de compra viven como filas de compras_cabecera
     * (tipo_comprobante 04/05), no en tablas propias.
     */
    public function getMovimientosProveedor(int $idEmpresa, int $idProveedor, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $params = [];
        $fCompra = $this->fechaWhere('c.fecha_emision', 'c',  $fechaDesde, $fechaHasta, $params);
        $fLiquid = $this->fechaWhere('l.fecha_emision', 'l',  $fechaDesde, $fechaHasta, $params);
        $fImport = $this->fechaWhere('COALESCE(fe.fecha_factura, ic.fecha_nacionalizacion, ic.created_at::date)', 'fe', $fechaDesde, $fechaHasta, $params);
        $fNd     = $this->fechaWhere('nd.fecha_emision', 'nd', $fechaDesde, $fechaHasta, $params);
        $fSi     = $this->fechaWhere('s.fecha_emision', 'si', $fechaDesde, $fechaHasta, $params);
        $fEc     = $this->fechaWhere('ec.fecha_emision', 'ec', $fechaDesde, $fechaHasta, $params);
        $fR      = $this->fechaWhere('r.fecha_emision', 'r',  $fechaDesde, $fechaHasta, $params);
        $fNc     = $this->fechaWhere('nc.fecha_emision', 'nc', $fechaDesde, $fechaHasta, $params);

        $params += [
            ':emp1' => $idEmpresa, ':prov1' => $idProveedor,
            ':emp2' => $idEmpresa, ':prov2' => $idProveedor,
            ':emp3' => $idEmpresa, ':prov3' => $idProveedor,
            ':emp4' => $idEmpresa, ':prov4' => $idProveedor,
            ':emp5' => $idEmpresa, ':prov5' => $idProveedor,
            ':emp6' => $idEmpresa, ':prov6' => $idProveedor,
            ':emp7' => $idEmpresa, ':prov7' => $idProveedor,
            ':emp8' => $idEmpresa, ':prov8' => $idProveedor,
        ];

        $sql = "
            SELECT * FROM (
                -- FACTURAS DE COMPRA (CARGO)
                SELECT c.fecha_emision::date AS fecha, 'CARGO'::text AS tipo_movimiento, 1 AS signo, 'COMPRA' AS origen,
                       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero_documento,
                       'Factura de Compra' AS detalle, c.importe_total AS monto, c.id AS id_orden
                FROM compras_cabecera c
                WHERE c.id_empresa = :emp1 AND c.eliminado = false AND c.id_proveedor = :prov1
                  AND c.tipo_comprobante = '01' {$fCompra}

                UNION ALL

                -- LIQUIDACIONES DE COMPRA (CARGO)
                SELECT l.fecha_emision::date, 'CARGO', 1, 'LIQUIDACION',
                       CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial),
                       'Liquidación de Compra', l.importe_total, l.id
                FROM liquidaciones_cabecera l
                WHERE l.id_empresa = :emp2 AND l.eliminado = false AND l.id_proveedor = :prov2
                  AND UPPER(l.estado) IN ('AUTORIZADO','APROBADO') {$fLiquid}

                UNION ALL

                -- FACTURAS DEL PROVEEDOR DEL EXTERIOR (CARGO)
                SELECT COALESCE(fe.fecha_factura, ic.fecha_nacionalizacion, ic.created_at::date), 'CARGO', 1, 'IMPORTACION',
                       COALESCE(fe.numero_factura, ic.numero_importacion),
                       'Factura Proveedor Exterior', fe.monto_usd, fe.id
                FROM importaciones_factura_exterior fe
                JOIN importaciones_cabecera ic ON ic.id = fe.id_importacion
                WHERE ic.id_empresa = :emp3 AND fe.eliminado = false AND ic.eliminado = false
                  AND fe.id_proveedor = :prov3 {$fImport}

                UNION ALL

                -- NOTAS DE DÉBITO RECIBIDAS (CARGO) — filas de compras_cabecera, tipo_comprobante '05'
                SELECT nd.fecha_emision::date, 'CARGO', 1, 'NOTA_DEBITO',
                       CONCAT(nd.establecimiento_prov,'-',nd.punto_emision_prov,'-',nd.secuencial_prov),
                       CONCAT('Nota de Débito (ref. ', nd.documento_modificado, ')'),
                       nd.importe_total, nd.id
                FROM compras_cabecera nd
                WHERE nd.id_empresa = :emp4 AND nd.eliminado = false AND nd.id_proveedor = :prov4
                  AND nd.tipo_comprobante = '05' {$fNd}

                UNION ALL

                -- SALDO INICIAL CXP (CARGO)
                SELECT s.fecha_emision::date, 'CARGO', 1, 'SALDO_INICIAL', s.nro_documento,
                       'Saldo Inicial', s.saldo_inicial, s.id
                FROM saldos_iniciales_cxp s
                WHERE s.id_empresa = :emp5 AND s.eliminado = false AND s.id_proveedor = :prov5 {$fSi}

                UNION ALL

                -- PAGOS (ABONO)
                SELECT ec.fecha_emision::date, 'ABONO', -1, 'PAGO', ec.numero_egreso,
                       CASE WHEN COALESCE(ec.observaciones,'') <> '' THEN CONCAT('Pago - ', ec.observaciones) ELSE 'Pago' END,
                       ed.monto_pagado, ec.id
                FROM egresos_detalle ed
                INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                WHERE ec.id_empresa = :emp6 AND ec.eliminado = false AND ec.estado != 'anulado'
                  AND ec.id_proveedor = :prov6
                  AND ed.tipo_documento IN ('COMPRA','LIQUIDACION','IMPORTACION') {$fEc}

                UNION ALL

                -- RETENCIONES DE COMPRA (ABONO)
                SELECT r.fecha_emision::date, 'ABONO', -1, 'RETENCION',
                       CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial),
                       'Retención', r.total_retenido, r.id
                FROM retencion_compra_cabecera r
                WHERE r.id_empresa = :emp7 AND r.eliminado = false AND r.id_proveedor = :prov7
                  AND UPPER(r.estado) NOT IN ('ANULADO','BORRADOR','PENDIENTE') {$fR}

                UNION ALL

                -- NOTAS DE CRÉDITO RECIBIDAS (ABONO) — filas de compras_cabecera, tipo_comprobante '04'
                SELECT nc.fecha_emision::date, 'ABONO', -1, 'NOTA_CREDITO',
                       CONCAT(nc.establecimiento_prov,'-',nc.punto_emision_prov,'-',nc.secuencial_prov),
                       CONCAT('Nota de Crédito (ref. ', nc.documento_modificado, ')'),
                       nc.importe_total, nc.id
                FROM compras_cabecera nc
                WHERE nc.id_empresa = :emp8 AND nc.eliminado = false AND nc.id_proveedor = :prov8
                  AND nc.tipo_comprobante = '04' {$fNc}
            ) mov
            ORDER BY fecha ASC, id_orden ASC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSaldoAnteriorProveedor(int $idEmpresa, int $idProveedor, string $fechaDesde): float
    {
        $hasta = date('Y-m-d', strtotime($fechaDesde . ' -1 day'));
        $movs = $this->getMovimientosProveedor($idEmpresa, $idProveedor, null, $hasta);

        $saldo = 0.0;
        foreach ($movs as $m) {
            $saldo += ((int) $m['signo']) * (float) $m['monto'];
        }
        return $saldo;
    }

    /**
     * Proveedores de la empresa cuyo saldo acumulado (a la fecha de corte, o
     * a hoy si no se indica) es mayor a cero. Espejo de
     * getClientesConSaldoPendiente(), agrupado en una sola consulta.
     */
    public function getProveedoresConSaldoPendiente(int $idEmpresa, ?string $fechaHasta): array
    {
        $params = [];
        $fCompra = $this->fechaWhere('c.fecha_emision', 'c',  null, $fechaHasta, $params);
        $fLiquid = $this->fechaWhere('l.fecha_emision', 'l',  null, $fechaHasta, $params);
        $fImport = $this->fechaWhere('COALESCE(fe.fecha_factura, ic.fecha_nacionalizacion, ic.created_at::date)', 'fe', null, $fechaHasta, $params);
        $fNd     = $this->fechaWhere('nd.fecha_emision', 'nd', null, $fechaHasta, $params);
        $fSi     = $this->fechaWhere('s.fecha_emision', 'si', null, $fechaHasta, $params);
        $fEc     = $this->fechaWhere('ec.fecha_emision', 'ec', null, $fechaHasta, $params);
        $fR      = $this->fechaWhere('r.fecha_emision', 'r',  null, $fechaHasta, $params);
        $fNc     = $this->fechaWhere('nc.fecha_emision', 'nc', null, $fechaHasta, $params);

        $params += [
            ':emp1' => $idEmpresa, ':emp2' => $idEmpresa, ':emp3' => $idEmpresa, ':emp4' => $idEmpresa,
            ':emp5' => $idEmpresa, ':emp6' => $idEmpresa, ':emp7' => $idEmpresa, ':emp8' => $idEmpresa,
            ':emp_prov' => $idEmpresa,
        ];

        $sql = "
            SELECT prov.id, prov.razon_social AS nombre, prov.identificacion
            FROM (
                SELECT id_proveedor, SUM(signo * monto) AS saldo
                FROM (
                    SELECT c.id_proveedor, 1 AS signo, c.importe_total AS monto
                    FROM compras_cabecera c
                    WHERE c.id_empresa = :emp1 AND c.eliminado = false AND c.tipo_comprobante = '01' {$fCompra}

                    UNION ALL

                    SELECT l.id_proveedor, 1, l.importe_total
                    FROM liquidaciones_cabecera l
                    WHERE l.id_empresa = :emp2 AND l.eliminado = false
                      AND UPPER(l.estado) IN ('AUTORIZADO','APROBADO') {$fLiquid}

                    UNION ALL

                    SELECT fe.id_proveedor, 1, fe.monto_usd
                    FROM importaciones_factura_exterior fe
                    JOIN importaciones_cabecera ic ON ic.id = fe.id_importacion
                    WHERE ic.id_empresa = :emp3 AND fe.eliminado = false AND ic.eliminado = false {$fImport}

                    UNION ALL

                    SELECT nd.id_proveedor, 1, nd.importe_total
                    FROM compras_cabecera nd
                    WHERE nd.id_empresa = :emp4 AND nd.eliminado = false AND nd.tipo_comprobante = '05' {$fNd}

                    UNION ALL

                    SELECT s.id_proveedor, 1, s.saldo_inicial
                    FROM saldos_iniciales_cxp s
                    WHERE s.id_empresa = :emp5 AND s.eliminado = false AND s.id_proveedor IS NOT NULL {$fSi}

                    UNION ALL

                    SELECT ec.id_proveedor, -1, ed.monto_pagado
                    FROM egresos_detalle ed
                    INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                    WHERE ec.id_empresa = :emp6 AND ec.eliminado = false AND ec.estado != 'anulado'
                      AND ec.id_proveedor IS NOT NULL
                      AND ed.tipo_documento IN ('COMPRA','LIQUIDACION','IMPORTACION') {$fEc}

                    UNION ALL

                    SELECT r.id_proveedor, -1, r.total_retenido
                    FROM retencion_compra_cabecera r
                    WHERE r.id_empresa = :emp7 AND r.eliminado = false
                      AND UPPER(r.estado) NOT IN ('ANULADO','BORRADOR','PENDIENTE') {$fR}

                    UNION ALL

                    SELECT nc.id_proveedor, -1, nc.importe_total
                    FROM compras_cabecera nc
                    WHERE nc.id_empresa = :emp8 AND nc.eliminado = false AND nc.tipo_comprobante = '04' {$fNc}
                ) mov
                GROUP BY id_proveedor
                HAVING SUM(signo * monto) > 0.005
            ) agg
            JOIN proveedores prov ON prov.id = agg.id_proveedor
            WHERE prov.id_empresa = :emp_prov AND prov.eliminado = false
            ORDER BY prov.razon_social ASC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
