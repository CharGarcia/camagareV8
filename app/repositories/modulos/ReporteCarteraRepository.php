<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Estado de cuenta (kardex) cronológico de un cliente o proveedor: une todos
 * los documentos que cargan saldo (facturas/compras, recibos, ND, saldo
 * inicial) con todos los movimientos que abonan (cobros/pagos, retenciones,
 * NC) y calcula el saldo corriendo.
 *
 * Regla de enlace (misma que Cuentas por Cobrar / Cuentas por Pagar): cada
 * ABONO se atribuye al tercero **del documento que cancela** (la factura que
 * cobra el ingreso, la venta a la que aplica la retención o la NC, la compra
 * que paga el egreso…) y solo si no hay documento enlazado se usa el tercero
 * escrito en el propio abono. Antes se filtraba solo por `id_cliente` /
 * `id_proveedor` del abono, lo que dejaba fuera cobros a saldos iniciales,
 * ingresos registrados con otro cliente, retenciones/NC sin cliente, etc., y
 * el saldo no cuadraba con CxC/CxP.
 *
 * Ambiente: los documentos que generan deuda se limitan al `tipo_ambiente`
 * actual de la empresa (igual que CxC/CxP); los abonos electrónicos (NC, ND,
 * retenciones) toleran `tipo_ambiente` NULL (legacy) para no perderlos.
 *
 * Sin ATTR_EMULATE_PREPARES, cada ocurrencia de un mismo valor en el UNION
 * ALL necesita su propio nombre de parámetro (ver SoporteChatRepository).
 * La única interpolación directa es el ambiente ('1' | '2'), validado.
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
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    /** Ambiente actual de la empresa como literal SQL seguro ('1' pruebas | '2' producción). */
    private function ambienteEmpresa(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT tipo_ambiente FROM empresas WHERE id = :id");
        $st->execute([':id' => $idEmpresa]);
        $amb = trim((string) $st->fetchColumn());
        return ($amb === '2') ? '2' : '1';
    }

    /**
     * Fragmento de fecha; cada subquery del UNION usa su propio sufijo de
     * parámetro (no se puede repetir un :nombre en PDO sin EMULATE_PREPARES).
     */
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

    /**
     * Filtro de entidad para una rama del UNION. Con $idEntidad, restringe a
     * ese tercero; sin él (modo "Todos"), no filtra y la expresión se expone
     * como columna id_entidad para agrupar.
     */
    private function entidadWhere(string $expr, string $suffix, ?int $idEntidad, array &$params): string
    {
        if ($idEntidad === null) {
            return " AND {$expr} IS NOT NULL";
        }
        $params[":ent_{$suffix}"] = $idEntidad;
        return " AND {$expr} = :ent_{$suffix}";
    }

    private function sumarSaldo(array $movs): float
    {
        $saldo = 0.0;
        foreach ($movs as $m) {
            $saldo += ((int) $m['signo']) * (float) $m['monto'];
        }
        return $saldo;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CLIENTE (CxC)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Cuerpo UNION ALL de los movimientos de cartera de clientes. Cada rama
     * expone: fecha, tipo_movimiento, signo, origen, numero_documento,
     * detalle, monto, id_orden, id_entidad (cliente al que se atribuye).
     */
    private function unionCliente(int $idEmpresa, ?int $idCliente, ?string $fechaDesde, ?string $fechaHasta, array &$params): string
    {
        $amb = $this->ambienteEmpresa($idEmpresa);

        $fVenta  = $this->fechaWhere('v.fecha_emision', 'v',  $fechaDesde, $fechaHasta, $params);
        $fRecibo = $this->fechaWhere('v.fecha_emision', 'rv', $fechaDesde, $fechaHasta, $params);
        $fNd     = $this->fechaWhere('nd.fecha_emision', 'nd', $fechaDesde, $fechaHasta, $params);
        $fSi     = $this->fechaWhere('s.fecha_emision', 'si', $fechaDesde, $fechaHasta, $params);
        $fIc     = $this->fechaWhere('ic.fecha_emision', 'ic', $fechaDesde, $fechaHasta, $params);
        $fR      = $this->fechaWhere('r.fecha_emision', 'r',  $fechaDesde, $fechaHasta, $params);
        $fNc     = $this->fechaWhere('nc.fecha_emision', 'nc', $fechaDesde, $fechaHasta, $params);

        // Expresión del cliente atribuido en cada rama (documento → abono).
        $eVenta  = 'v.id_cliente';
        $eRecibo = 'v.id_cliente';
        $eNd     = 'COALESCE(vm.id_cliente, nd.id_cliente)';
        $eSi     = 's.id_cliente';
        $eIc     = 'COALESCE(vf.id_cliente, rv.id_cliente, si.id_cliente, ic.id_cliente)';
        $eR      = 'COALESCE(v.id_cliente, vs.id_cliente, r.id_cliente)';
        $eNc     = 'COALESCE(vm.id_cliente, nc.id_cliente)';

        $wVenta  = $this->entidadWhere($eVenta,  'v',  $idCliente, $params);
        $wRecibo = $this->entidadWhere($eRecibo, 'rv', $idCliente, $params);
        $wNd     = $this->entidadWhere($eNd,     'nd', $idCliente, $params);
        $wSi     = $this->entidadWhere($eSi,     'si', $idCliente, $params);
        $wIc     = $this->entidadWhere($eIc,     'ic', $idCliente, $params);
        $wR      = $this->entidadWhere($eR,      'r',  $idCliente, $params);
        $wNc     = $this->entidadWhere($eNc,     'nc', $idCliente, $params);

        $params += [
            ':emp1' => $idEmpresa, ':emp2' => $idEmpresa, ':emp3' => $idEmpresa, ':emp4' => $idEmpresa,
            ':emp5' => $idEmpresa, ':emp6' => $idEmpresa, ':emp7' => $idEmpresa,
        ];

        $numVenta = "CONCAT(vc.establecimiento,'-',vc.punto_emision,'-',vc.secuencial)";

        return "
                -- FACTURAS DE VENTA (CARGO) — solo el ambiente actual, como CxC
                SELECT v.fecha_emision::date AS fecha, 'CARGO'::text AS tipo_movimiento, 1 AS signo, 'FACTURA'::text AS origen,
                       CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_documento,
                       'Factura de Venta'::text AS detalle, v.importe_total AS monto, v.id AS id_orden,
                       {$eVenta} AS id_entidad
                FROM ventas_cabecera v
                WHERE v.id_empresa = :emp1 AND v.eliminado = false
                  AND v.estado IN ('autorizado','autorizada')
                  AND v.tipo_ambiente = '{$amb}' {$wVenta} {$fVenta}

                UNION ALL

                -- RECIBOS DE VENTA (CARGO)
                SELECT v.fecha_emision::date, 'CARGO', 1, 'RECIBO',
                       CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial),
                       'Recibo de Venta', v.importe_total, v.id, {$eRecibo}
                FROM recibos_venta_cabecera v
                WHERE v.id_empresa = :emp2 AND v.eliminado = false
                  AND v.estado NOT IN ('borrador','anulado','facturado')
                  AND (v.tipo_ambiente IS NULL OR v.tipo_ambiente = '{$amb}') {$wRecibo} {$fRecibo}

                UNION ALL

                -- NOTAS DE DÉBITO EMITIDAS (CARGO) — atribuidas al cliente de la factura modificada
                SELECT nd.fecha_emision::date, 'CARGO', 1, 'NOTA_DEBITO',
                       CONCAT(nd.establecimiento,'-',nd.punto_emision,'-',nd.secuencial),
                       CONCAT('Nota de Débito (ref. ', nd.num_doc_modificado, ')'),
                       nd.importe_total, nd.id, {$eNd}
                FROM nota_debito_cabecera nd
                LEFT JOIN LATERAL (
                    SELECT vc.id_cliente FROM ventas_cabecera vc
                    WHERE vc.id_empresa = nd.id_empresa AND vc.eliminado = false
                      AND vc.tipo_ambiente = '{$amb}'
                      AND {$numVenta} = nd.num_doc_modificado
                    LIMIT 1
                ) vm ON true
                WHERE nd.id_empresa = :emp3 AND nd.eliminado = false
                  AND nd.estado != 'anulado'
                  AND (nd.tipo_ambiente IS NULL OR nd.tipo_ambiente = '{$amb}') {$wNd} {$fNd}

                UNION ALL

                -- SALDO INICIAL CXC (CARGO)
                SELECT s.fecha_emision::date, 'CARGO', 1, 'SALDO_INICIAL', s.nro_documento,
                       'Saldo Inicial', s.saldo_inicial, s.id, {$eSi}
                FROM saldos_iniciales_cxc s
                WHERE s.id_empresa = :emp4 AND s.eliminado = false {$wSi} {$fSi}

                UNION ALL

                -- COBROS (ABONO) — atribuidos al cliente del documento cobrado
                -- (factura, recibo o saldo inicial); si no hay documento, al del ingreso
                SELECT ic.fecha_emision::date, 'ABONO', -1, 'COBRO', ic.numero_ingreso,
                       CASE WHEN COALESCE(ic.observaciones,'') <> '' THEN CONCAT('Cobro - ', ic.observaciones) ELSE 'Cobro' END,
                       idet.monto_cobrado, ic.id, {$eIc}
                FROM ingresos_detalle idet
                INNER JOIN ingresos_cabecera ic ON ic.id = idet.id_ingreso
                LEFT JOIN ventas_cabecera vf         ON idet.tipo_documento = 'FACTURA'       AND vf.id = idet.id_referencia_documento
                LEFT JOIN recibos_venta_cabecera rv  ON idet.tipo_documento = 'RECIBO'        AND rv.id = idet.id_referencia_documento
                LEFT JOIN saldos_iniciales_cxc si    ON idet.tipo_documento = 'SALDO_INICIAL' AND si.id = idet.id_referencia_documento
                WHERE ic.id_empresa = :emp5 AND ic.eliminado = false AND ic.estado != 'anulado'
                  AND idet.tipo_documento IN ('FACTURA','RECIBO','SALDO_INICIAL')
                  AND COALESCE(idet.monto_cobrado, 0) <> 0
                  -- el documento cobrado debe ser del ambiente actual (si no, su cargo tampoco está)
                  AND (vf.id IS NULL OR vf.tipo_ambiente = '{$amb}')
                  AND (rv.id IS NULL OR rv.tipo_ambiente IS NULL OR rv.tipo_ambiente = '{$amb}') {$wIc} {$fIc}

                UNION ALL

                -- RETENCIONES DE VENTA (ABONO) — atribuidas al cliente de la venta
                -- (id_venta directo o num_doc_sustento del detalle); si no, al de la retención
                SELECT r.fecha_emision::date, 'ABONO', -1, 'RETENCION',
                       CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial),
                       'Retención', (COALESCE(r.total_renta,0) + COALESCE(r.total_iva,0) + COALESCE(r.total_isd,0)), r.id, {$eR}
                FROM retencion_venta_cabecera r
                LEFT JOIN ventas_cabecera v ON v.id = r.id_venta
                LEFT JOIN LATERAL (
                    SELECT vc.id_cliente
                    FROM retencion_venta_detalle rd
                    JOIN ventas_cabecera vc
                      ON vc.id_empresa = r.id_empresa AND vc.eliminado = false
                     AND vc.tipo_ambiente = '{$amb}'
                     AND regexp_replace(COALESCE(rd.num_doc_sustento,''), '[^0-9]', '', 'g')
                         = regexp_replace({$numVenta}, '[^0-9]', '', 'g')
                    WHERE rd.id_retencion = r.id AND r.id_venta IS NULL
                      AND COALESCE(rd.num_doc_sustento,'') <> ''
                    LIMIT 1
                ) vs ON true
                WHERE r.id_empresa = :emp6 AND r.eliminado = false
                  AND (r.tipo_ambiente IS NULL OR r.tipo_ambiente = '{$amb}')
                  AND (v.id IS NULL OR v.tipo_ambiente = '{$amb}') {$wR} {$fR}

                UNION ALL

                -- NOTAS DE CRÉDITO EMITIDAS (ABONO) — atribuidas al cliente de la factura modificada
                SELECT nc.fecha_emision::date, 'ABONO', -1, 'NOTA_CREDITO',
                       CONCAT(nc.establecimiento,'-',nc.punto_emision,'-',nc.secuencial),
                       CONCAT('Nota de Crédito (ref. ', nc.num_doc_modificado, ')'),
                       nc.importe_total, nc.id, {$eNc}
                FROM notas_credito_cabecera nc
                LEFT JOIN LATERAL (
                    SELECT vc.id_cliente FROM ventas_cabecera vc
                    WHERE vc.id_empresa = nc.id_empresa AND vc.eliminado = false
                      AND vc.tipo_ambiente = '{$amb}'
                      AND {$numVenta} = nc.num_doc_modificado
                    LIMIT 1
                ) vm ON true
                WHERE nc.id_empresa = :emp7 AND nc.eliminado = false
                  AND nc.estado != 'anulado'
                  AND (nc.tipo_ambiente IS NULL OR nc.tipo_ambiente = '{$amb}') {$wNc} {$fNc}
        ";
    }

    /**
     * Movimientos cronológicos de un cliente: CARGO (factura/recibo/ND/saldo
     * inicial) y ABONO (cobro/retención/NC). $fechaDesde/$fechaHasta son
     * opcionales (null = sin límite).
     */
    public function getMovimientosCliente(int $idEmpresa, int $idCliente, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $params = [];
        $union  = $this->unionCliente($idEmpresa, $idCliente, $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT fecha, tipo_movimiento, signo, origen, numero_documento, detalle, monto, id_orden
                FROM ( {$union} ) mov
                ORDER BY fecha ASC, signo DESC, id_orden ASC";

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
        return $this->sumarSaldo($this->getMovimientosCliente($idEmpresa, $idCliente, null, $hasta));
    }

    /**
     * Clientes de la empresa cuyo saldo acumulado (a la fecha de corte, o a
     * hoy si no se indica) es mayor a cero. Se agrupa en una sola consulta
     * (en vez de calcular el saldo cliente por cliente) para poder ofrecer
     * la opción "Todos" sin un costo N+1. Usa exactamente las mismas ramas
     * que el estado de cuenta individual, así ambos cuadran entre sí.
     */
    public function getClientesConSaldoPendiente(int $idEmpresa, ?string $fechaHasta): array
    {
        $params = [];
        $union  = $this->unionCliente($idEmpresa, null, null, $fechaHasta, $params);
        $params[':emp_cli'] = $idEmpresa;

        $sql = "
            SELECT cli.id, cli.nombre, cli.identificacion
            FROM (
                SELECT id_entidad, SUM(signo * monto) AS saldo
                FROM ( {$union} ) mov
                GROUP BY id_entidad
                HAVING SUM(signo * monto) > 0.005
            ) agg
            JOIN clientes cli ON cli.id = agg.id_entidad
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
     * Cuerpo UNION ALL de los movimientos de cartera de proveedores. NC y ND
     * de compra viven como filas de compras_cabecera (tipo_comprobante
     * 04/05), no en tablas propias.
     */
    private function unionProveedor(int $idEmpresa, ?int $idProveedor, ?string $fechaDesde, ?string $fechaHasta, array &$params): string
    {
        $amb = $this->ambienteEmpresa($idEmpresa);

        $fCompra = $this->fechaWhere('c.fecha_emision', 'c',  $fechaDesde, $fechaHasta, $params);
        $fLiquid = $this->fechaWhere('l.fecha_emision', 'l',  $fechaDesde, $fechaHasta, $params);
        $fImport = $this->fechaWhere('COALESCE(fe.fecha_factura, ic.fecha_nacionalizacion, ic.created_at::date)', 'fe', $fechaDesde, $fechaHasta, $params);
        $fNd     = $this->fechaWhere('nd.fecha_emision', 'nd', $fechaDesde, $fechaHasta, $params);
        $fSi     = $this->fechaWhere('s.fecha_emision', 'si', $fechaDesde, $fechaHasta, $params);
        $fEc     = $this->fechaWhere('ec.fecha_emision', 'ec', $fechaDesde, $fechaHasta, $params);
        $fR      = $this->fechaWhere('r.fecha_emision', 'r',  $fechaDesde, $fechaHasta, $params);
        $fNc     = $this->fechaWhere('nc.fecha_emision', 'nc', $fechaDesde, $fechaHasta, $params);

        $eCompra = 'c.id_proveedor';
        $eLiquid = 'l.id_proveedor';
        $eImport = 'fe.id_proveedor';
        $eNd     = 'nd.id_proveedor';
        $eSi     = 's.id_proveedor';
        $eEc     = 'COALESCE(pc.id_proveedor, pl.id_proveedor, pf.id_proveedor, ps.id_proveedor, ec.id_proveedor)';
        $eR      = 'COALESCE(rc.id_proveedor, rl.id_proveedor, r.id_proveedor)';
        $eNc     = 'nc.id_proveedor';

        $wCompra = $this->entidadWhere($eCompra, 'c',  $idProveedor, $params);
        $wLiquid = $this->entidadWhere($eLiquid, 'l',  $idProveedor, $params);
        $wImport = $this->entidadWhere($eImport, 'fe', $idProveedor, $params);
        $wNd     = $this->entidadWhere($eNd,     'nd', $idProveedor, $params);
        $wSi     = $this->entidadWhere($eSi,     'si', $idProveedor, $params);
        $wEc     = $this->entidadWhere($eEc,     'ec', $idProveedor, $params);
        $wR      = $this->entidadWhere($eR,      'r',  $idProveedor, $params);
        $wNc     = $this->entidadWhere($eNc,     'nc', $idProveedor, $params);

        $params += [
            ':emp1' => $idEmpresa, ':emp2' => $idEmpresa, ':emp3' => $idEmpresa, ':emp4' => $idEmpresa,
            ':emp5' => $idEmpresa, ':emp6' => $idEmpresa, ':emp7' => $idEmpresa, ':emp8' => $idEmpresa,
        ];

        return "
                -- FACTURAS DE COMPRA (CARGO) — solo el ambiente actual, como CxP
                SELECT c.fecha_emision::date AS fecha, 'CARGO'::text AS tipo_movimiento, 1 AS signo, 'COMPRA'::text AS origen,
                       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero_documento,
                       'Factura de Compra'::text AS detalle, c.importe_total AS monto, c.id AS id_orden,
                       {$eCompra} AS id_entidad
                FROM compras_cabecera c
                WHERE c.id_empresa = :emp1 AND c.eliminado = false
                  AND c.tipo_comprobante = '01'
                  AND c.tipo_ambiente = '{$amb}' {$wCompra} {$fCompra}

                UNION ALL

                -- LIQUIDACIONES DE COMPRA (CARGO)
                SELECT l.fecha_emision::date, 'CARGO', 1, 'LIQUIDACION',
                       CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial),
                       'Liquidación de Compra', l.importe_total, l.id, {$eLiquid}
                FROM liquidaciones_cabecera l
                WHERE l.id_empresa = :emp2 AND l.eliminado = false
                  AND UPPER(l.estado) IN ('AUTORIZADO','APROBADO')
                  AND (l.tipo_ambiente IS NULL OR l.tipo_ambiente = '{$amb}') {$wLiquid} {$fLiquid}

                UNION ALL

                -- FACTURAS DEL PROVEEDOR DEL EXTERIOR (CARGO)
                SELECT COALESCE(fe.fecha_factura, ic.fecha_nacionalizacion, ic.created_at::date), 'CARGO', 1, 'IMPORTACION',
                       COALESCE(fe.numero_factura, ic.numero_importacion),
                       'Factura Proveedor Exterior', fe.monto_usd, fe.id, {$eImport}
                FROM importaciones_factura_exterior fe
                JOIN importaciones_cabecera ic ON ic.id = fe.id_importacion
                WHERE ic.id_empresa = :emp3 AND fe.eliminado = false AND ic.eliminado = false {$wImport} {$fImport}

                UNION ALL

                -- NOTAS DE DÉBITO RECIBIDAS (CARGO) — filas de compras_cabecera, tipo_comprobante '05'
                SELECT nd.fecha_emision::date, 'CARGO', 1, 'NOTA_DEBITO',
                       CONCAT(nd.establecimiento_prov,'-',nd.punto_emision_prov,'-',nd.secuencial_prov),
                       CONCAT('Nota de Débito (ref. ', nd.documento_modificado, ')'),
                       nd.importe_total, nd.id, {$eNd}
                FROM compras_cabecera nd
                WHERE nd.id_empresa = :emp4 AND nd.eliminado = false
                  AND nd.tipo_comprobante = '05'
                  AND (nd.tipo_ambiente IS NULL OR nd.tipo_ambiente = '{$amb}') {$wNd} {$fNd}

                UNION ALL

                -- SALDO INICIAL CXP (CARGO)
                SELECT s.fecha_emision::date, 'CARGO', 1, 'SALDO_INICIAL', s.nro_documento,
                       'Saldo Inicial', s.saldo_inicial, s.id, {$eSi}
                FROM saldos_iniciales_cxp s
                WHERE s.id_empresa = :emp5 AND s.eliminado = false {$wSi} {$fSi}

                UNION ALL

                -- PAGOS (ABONO) — atribuidos al proveedor del documento pagado (compra,
                -- liquidación, importación o saldo inicial); si no hay documento, al del egreso
                SELECT ec.fecha_emision::date, 'ABONO', -1, 'PAGO', ec.numero_egreso,
                       CASE WHEN COALESCE(ec.observaciones,'') <> '' THEN CONCAT('Pago - ', ec.observaciones) ELSE 'Pago' END,
                       ed.monto_pagado, ec.id, {$eEc}
                FROM egresos_detalle ed
                INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                LEFT JOIN compras_cabecera pc                ON ed.tipo_documento = 'COMPRA'        AND pc.id = ed.id_referencia_documento
                LEFT JOIN liquidaciones_cabecera pl          ON ed.tipo_documento = 'LIQUIDACION'   AND pl.id = ed.id_referencia_documento
                LEFT JOIN importaciones_factura_exterior pf  ON ed.tipo_documento = 'IMPORTACION'   AND pf.id = ed.id_referencia_documento
                LEFT JOIN saldos_iniciales_cxp ps            ON ed.tipo_documento = 'SALDO_INICIAL' AND ps.id = ed.id_referencia_documento
                WHERE ec.id_empresa = :emp6 AND ec.eliminado = false AND ec.estado != 'anulado'
                  AND COALESCE(ed.eliminado, false) = false
                  AND ed.tipo_documento IN ('COMPRA','LIQUIDACION','IMPORTACION','SALDO_INICIAL')
                  AND COALESCE(ed.monto_pagado, 0) <> 0
                  -- el documento pagado debe ser del ambiente actual (si no, su cargo tampoco está)
                  AND (pc.id IS NULL OR pc.tipo_ambiente = '{$amb}')
                  AND (pl.id IS NULL OR pl.tipo_ambiente IS NULL OR pl.tipo_ambiente = '{$amb}') {$wEc} {$fEc}

                UNION ALL

                -- RETENCIONES DE COMPRA (ABONO) — atribuidas al proveedor de la compra/liquidación
                -- retenida; si no hay enlace, al de la retención
                SELECT r.fecha_emision::date, 'ABONO', -1, 'RETENCION',
                       CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial),
                       'Retención', COALESCE(r.total_retenido, 0), r.id, {$eR}
                FROM retencion_compra_cabecera r
                LEFT JOIN compras_cabecera rc       ON rc.id = r.id_compra
                LEFT JOIN liquidaciones_cabecera rl ON rl.id = r.id_liquidacion
                WHERE r.id_empresa = :emp7 AND r.eliminado = false
                  AND UPPER(COALESCE(r.estado,'')) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
                  AND (r.tipo_ambiente IS NULL OR r.tipo_ambiente = '{$amb}')
                  AND (rc.id IS NULL OR rc.tipo_ambiente = '{$amb}')
                  AND (rl.id IS NULL OR rl.tipo_ambiente IS NULL OR rl.tipo_ambiente = '{$amb}') {$wR} {$fR}

                UNION ALL

                -- NOTAS DE CRÉDITO RECIBIDAS (ABONO) — filas de compras_cabecera, tipo_comprobante '04'
                SELECT nc.fecha_emision::date, 'ABONO', -1, 'NOTA_CREDITO',
                       CONCAT(nc.establecimiento_prov,'-',nc.punto_emision_prov,'-',nc.secuencial_prov),
                       CONCAT('Nota de Crédito (ref. ', nc.documento_modificado, ')'),
                       nc.importe_total, nc.id, {$eNc}
                FROM compras_cabecera nc
                WHERE nc.id_empresa = :emp8 AND nc.eliminado = false
                  AND nc.tipo_comprobante = '04'
                  AND (nc.tipo_ambiente IS NULL OR nc.tipo_ambiente = '{$amb}') {$wNc} {$fNc}
        ";
    }

    /**
     * Movimientos cronológicos de un proveedor: CARGO (factura/liquidación/
     * importación/ND recibida/saldo inicial) y ABONO (pago/retención/NC
     * recibida).
     */
    public function getMovimientosProveedor(int $idEmpresa, int $idProveedor, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $params = [];
        $union  = $this->unionProveedor($idEmpresa, $idProveedor, $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT fecha, tipo_movimiento, signo, origen, numero_documento, detalle, monto, id_orden
                FROM ( {$union} ) mov
                ORDER BY fecha ASC, signo DESC, id_orden ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSaldoAnteriorProveedor(int $idEmpresa, int $idProveedor, string $fechaDesde): float
    {
        $hasta = date('Y-m-d', strtotime($fechaDesde . ' -1 day'));
        return $this->sumarSaldo($this->getMovimientosProveedor($idEmpresa, $idProveedor, null, $hasta));
    }

    /**
     * Proveedores de la empresa cuyo saldo acumulado (a la fecha de corte, o
     * a hoy si no se indica) es mayor a cero. Espejo de
     * getClientesConSaldoPendiente(), con las mismas ramas que el estado de
     * cuenta individual.
     */
    public function getProveedoresConSaldoPendiente(int $idEmpresa, ?string $fechaHasta): array
    {
        $params = [];
        $union  = $this->unionProveedor($idEmpresa, null, null, $fechaHasta, $params);
        $params[':emp_prov'] = $idEmpresa;

        $sql = "
            SELECT prov.id, prov.razon_social AS nombre, prov.identificacion
            FROM (
                SELECT id_entidad, SUM(signo * monto) AS saldo
                FROM ( {$union} ) mov
                GROUP BY id_entidad
                HAVING SUM(signo * monto) > 0.005
            ) agg
            JOIN proveedores prov ON prov.id = agg.id_entidad
            WHERE prov.id_empresa = :emp_prov AND prov.eliminado = false
            ORDER BY prov.razon_social ASC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
