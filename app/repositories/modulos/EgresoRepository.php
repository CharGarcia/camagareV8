<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class EgresoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('egresos_cabecera');
        // Auto-Migración transparente para soporte de cheques
        try {
            $st = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'egresos_pagos' AND column_name = 'tipo_operacion_bancaria'");
            if (!$st->fetch()) {
                $this->db->exec("ALTER TABLE egresos_pagos ADD COLUMN tipo_operacion_bancaria VARCHAR(50) NULL");
                $this->db->exec("ALTER TABLE egresos_pagos ADD COLUMN numero_cheque VARCHAR(50) NULL");
                $this->db->exec("ALTER TABLE egresos_pagos ADD COLUMN fecha_cobro DATE NULL");
            }
        } catch (\Exception $e) { 
            // Silenciar en caso de no poseer permisos DDL
        }
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE e.id_empresa = :id_empresa AND e.eliminado = false AND e.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (e.numero_egreso ILIKE :buscar OR p.razon_social ILIKE :buscar OR emp.nombres_apellidos ILIKE :buscar OR e.observaciones ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'proveedor' => 'p.razon_social',
                'empleado'  => 'emp.nombres_apellidos',
                'numero'    => 'e.numero_egreso',
                'nro'       => 'e.numero_egreso',
                'concepto'  => 'e.observaciones',
                'obs'       => 'e.observaciones',
            ],
            'exacto'   => [ 'estado' => 'e.estado', 'tipo' => 'e.tipo_egreso' ],
            'fecha'    => [ 'fecha' => 'e.fecha_emision', 'fecha_emision' => 'e.fecha_emision' ],
            'numerico' => [ 'monto' => 'e.monto_total', 'total' => 'e.monto_total' ],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM egresos_cabecera e 
                     LEFT JOIN proveedores p ON e.id_proveedor = p.id 
                     LEFT JOIN empleados emp ON e.id_empleado = emp.id 
                     $where";
        $total = (int) $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'numero_egreso', 'tipo_egreso', 'monto_total', 'estado', 'sujeto_nombre', 'observaciones'];
        if (!in_array($ordenCol, $allowedCols)) {
            $ordenCol = 'fecha_emision';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match($ordenCol) {
            'sujeto_nombre' => 'COALESCE(p.razon_social, emp.nombres_apellidos, \'OTRO\')',
            default         => "e.$ordenCol",
        };

        $sql = "SELECT e.*,
                       COALESCE(p.razon_social, emp.nombres_apellidos, 'N/A') AS sujeto_nombre,
                       COALESCE(p.identificacion, emp.identificacion, '') AS sujeto_ruc,
                       u.nombre AS usuario_nombre,
                       ec.nombre AS concepto_nombre
                FROM egresos_cabecera e
                LEFT JOIN proveedores p ON e.id_proveedor = p.id
                LEFT JOIN empleados emp ON e.id_empleado = emp.id
                LEFT JOIN usuarios u ON e.created_by = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso ec ON e.id_egreso_concepto = ec.id
                $where
                ORDER BY $ordenExpr $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT e.*,
                       COALESCE(p.razon_social, emp.nombres_apellidos, 'N/A') AS sujeto_nombre,
                       COALESCE(p.identificacion, emp.identificacion, '') AS sujeto_ruc,
                       u.nombre AS usuario_nombre,
                       ec.nombre AS concepto_nombre
                FROM egresos_cabecera e
                LEFT JOIN proveedores p ON e.id_proveedor = p.id
                LEFT JOIN empleados emp ON e.id_empleado = emp.id
                LEFT JOIN usuarios u ON e.created_by = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso ec ON e.id_egreso_concepto = ec.id
                WHERE e.id = :id AND e.id_empresa = :id_empresa AND e.eliminado = FALSE";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idEgreso): array
    {
        $sql = "SELECT ed.*, COALESCE(c.fecha_emision, l.fecha_emision) AS fecha_documento
                FROM egresos_detalle ed
                LEFT JOIN compras_cabecera c ON ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id
                LEFT JOIN liquidaciones_cabecera l ON ed.tipo_documento = 'LIQUIDACION' AND ed.id_referencia_documento = l.id
                WHERE ed.id_egreso = ? AND ed.eliminado = FALSE 
                ORDER BY ed.id ASC";
        return $this->query($sql, [$idEgreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPagos(int $idEgreso): array
    {
        $sql = "SELECT ep.id, ep.id_egreso, ep.id_forma_pago, ep.monto, ep.referencia,
                       ep.tipo_operacion_bancaria, ep.numero_cheque, ep.fecha_cobro,
                       efc.nombre AS forma_pago_nombre, efc.tipo AS forma_pago_tipo
                FROM egresos_pagos ep
                INNER JOIN empresa_formas_pago efc ON ep.id_forma_pago = efc.id
                WHERE ep.id_egreso = ? AND ep.eliminado = FALSE
                ORDER BY ep.id ASC";
        return $this->query($sql, [$idEgreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConceptosEgreso(int $idEmpresa): array
    {
        $sql = "SELECT o.*, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso o
                LEFT JOIN plan_cuentas pc ON pc.id = o.id_cuenta_contable
                WHERE o.id_empresa = :id_empresa
                  AND o.aplica_egresos = TRUE
                  AND UPPER(o.estado) = 'ACTIVO'
                  AND o.eliminado = FALSE
                ORDER BY o.nombre ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Líneas de rol (rol_detalle) pendientes de pago de un empleado. Mismo shape que proveedor. */
    public function getDocumentosPendientesEmpleado(int $idEmpleado, int $idEmpresa): array
    {
        $sql = "WITH pagado AS (
                    SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d
                    INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = 'ROL' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                )
                SELECT 'ROL' AS tipo_doc_bd, rd.id,
                       COALESCE(NULLIF(rc.descripcion, ''), 'Rol ' || rc.periodo_mes || '/' || rc.periodo_anio) AS numero_documento,
                       rc.fecha_pago AS fecha_emision,
                       rd.neto AS monto_total,
                       COALESCE(p.total_pagado, 0) AS monto_pagado_previo,
                       (rd.neto - COALESCE(p.total_pagado, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM rol_detalle rd
                INNER JOIN rol_cabecera rc ON rc.id = rd.id_rol
                LEFT JOIN pagado p ON p.id_referencia_documento = rd.id
                WHERE rd.id_empleado = :id_emp AND rd.id_empresa = :id_empresa
                  AND rc.eliminado = FALSE AND rc.estado IN ('generado','pagado','contabilizado')
                  AND (rd.neto - COALESCE(p.total_pagado, 0)) > 0.01
                ORDER BY rc.periodo_anio ASC, rc.periodo_mes ASC";
        return $this->query($sql, [':id_emp' => $idEmpleado, ':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocumentosPendientesProveedor(int $idProveedor, int $idEmpresa): array
    {
        // Calcular acumulado pagado previamente en egresos registrados
        $sql = "WITH pagado AS (
                    SELECT d.tipo_documento, d.id_referencia_documento, SUM(d.monto_pagado) as total_pagado
                    FROM egresos_detalle d
                    INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE e.estado != 'anulado' 
                      AND e.eliminado = FALSE
                      AND d.eliminado = FALSE
                    GROUP BY d.tipo_documento, d.id_referencia_documento
                ),
                nc_nd AS (
                    -- Notas de crédito ('04') y débito ('05') de compra, enlazadas a la factura por documento_modificado
                    SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
                           SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
                           SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
                    FROM compras_cabecera nc
                    WHERE nc.tipo_comprobante IN ('04','05')
                      AND nc.eliminado = FALSE
                      AND nc.id_empresa = :id_empresa
                    GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
                )
                -- Unimos Compras (compras_cabecera) y Liquidaciones (liquidaciones_cabecera)
                SELECT 'COMPRA' as tipo_doc_bd, c.id,
                       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero_documento,
                       c.fecha_emision,
                       c.importe_total AS monto_total,
                       COALESCE(p.total_pagado, 0) AS monto_pagado_previo,
                       (c.importe_total - COALESCE(p.total_pagado, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM compras_cabecera c
                LEFT JOIN pagado p ON c.id = p.id_referencia_documento AND p.tipo_documento = 'COMPRA'
                LEFT JOIN nc_nd nn ON nn.id_empresa = c.id_empresa
                                  AND nn.id_proveedor = c.id_proveedor
                                  AND nn.documento_modificado = CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                WHERE c.id_proveedor = :id_prov
                  AND c.id_empresa = :id_empresa
                  AND c.eliminado = FALSE
                  AND COALESCE(c.tipo_comprobante, '01') NOT IN ('04','05')
                  AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND (c.importe_total - COALESCE(p.total_pagado, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0)) > 0.01
                UNION ALL
                SELECT 'LIQUIDACION' as tipo_doc_bd, l.id,
                       CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero_documento,
                       l.fecha_emision,
                       l.importe_total AS monto_total,
                       COALESCE(p.total_pagado, 0) AS monto_pagado_previo,
                       (l.importe_total - COALESCE(p.total_pagado, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM liquidaciones_cabecera l
                LEFT JOIN pagado p ON l.id = p.id_referencia_documento AND p.tipo_documento = 'LIQUIDACION'
                WHERE l.id_proveedor = :id_prov
                  AND l.id_empresa = :id_empresa
                  AND l.eliminado = FALSE
                  AND l.estado = 'autorizado'
                  AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND (l.importe_total - COALESCE(p.total_pagado, 0)) > 0.01
                ORDER BY fecha_emision ASC";

        return $this->query($sql, [
            ':id_prov' => $idProveedor,
            ':id_empresa' => $idEmpresa
        ])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO egresos_cabecera (
                    id_empresa, id_punto_emision, establecimiento, punto_emision, secuencial, numero_egreso,
                    fecha_emision, tipo_egreso, tipo_sujeto, id_proveedor, id_empleado, id_egreso_concepto,
                    monto_total, observaciones, estado, tipo_ambiente, created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_punto, :est, :pto, :sec, :num,
                    :fecha, :tipo_egreso, :tipo_sujeto, :id_prov, :id_emp, :id_conc,
                    :total, :obs, :estado,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :usr, :usr
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => (int) $data['id_empresa'],
            ':id_punto'     => !empty($data['id_punto_emision']) ? (int)$data['id_punto_emision'] : null,
            ':est'          => $data['establecimiento'] ?? null,
            ':pto'          => $data['punto_emision'] ?? null,
            ':sec'          => $data['secuencial'] ?? null,
            ':num'          => $data['numero_egreso'],
            ':fecha'        => $data['fecha_emision'],
            ':tipo_egreso'  => $data['tipo_egreso'],
            ':tipo_sujeto'  => $data['tipo_sujeto'],
            ':id_prov'      => !empty($data['id_proveedor']) ? (int)$data['id_proveedor'] : null,
            ':id_emp'       => !empty($data['id_empleado']) ? (int)$data['id_empleado'] : null,
            ':id_conc'      => !empty($data['id_egreso_concepto']) ? (int)$data['id_egreso_concepto'] : null,
            ':total'        => (float) $data['monto_total'],
            ':obs'          => $data['observaciones'] ?? null,
            ':estado'       => $data['estado'] ?? 'registrado',
            ':usr'          => (int) $data['usuario_id']
        ]);

        return (int) $st->fetchColumn();
    }

    public function insertDetalle(array $data): void
    {
        $sql = "INSERT INTO egresos_detalle (
                    id_egreso, tipo_documento, id_referencia_documento, numero_documento, 
                    descripcion, monto_documento, saldo_anterior, monto_pagado, saldo_actual
                ) VALUES (
                    :id_egreso, :tipo_doc, :id_ref, :num_doc, :desc, :monto_doc, :saldo_ant, :monto_pag, :saldo_act
                )";
        $this->query($sql, [
            ':id_egreso'    => (int) $data['id_egreso'],
            ':tipo_doc'     => $data['tipo_documento'],
            ':id_ref'       => !empty($data['id_referencia_documento']) ? (int)$data['id_referencia_documento'] : null,
            ':num_doc'      => $data['numero_documento'] ?? null,
            ':desc'         => $data['descripcion'] ?? null,
            ':monto_doc'    => (float) ($data['monto_documento'] ?? 0),
            ':saldo_ant'    => (float) ($data['saldo_anterior'] ?? 0),
            ':monto_pag'    => (float) ($data['monto_pagado'] ?? 0),
            ':saldo_act'    => (float) ($data['saldo_actual'] ?? 0),
        ]);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO egresos_pagos (
                    id_egreso, id_forma_pago, monto, referencia,
                    tipo_operacion_bancaria, numero_cheque, fecha_cobro
                ) VALUES (
                    :id_egreso, :id_forma, :monto, :ref,
                    :tipo_op, :num_chq, :fec_cob
                )";
        $this->query($sql, [
            ':id_egreso'  => (int) $data['id_egreso'],
            ':id_forma'   => (int) $data['id_forma_pago'],
            ':monto'      => (float) $data['monto'],
            ':ref'        => $data['referencia'] ?? null,
            ':tipo_op'    => $data['tipo_operacion_bancaria'] ?? null,
            ':num_chq'    => $data['numero_cheque'] ?? null,
            ':fec_cob'    => !empty($data['fecha_cobro']) ? $data['fecha_cobro'] : null
        ]);
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE egresos_cabecera SET estado = 'anulado', updated_by = :usr, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':emp' => $idEmpresa, ':usr' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    /** Enlaza (o desvincula con null) el asiento contable generado al egreso. */
    public function updateAsientoContable(int $idEgreso, ?int $idAsiento): void
    {
        $this->query(
            "UPDATE egresos_cabecera SET id_asiento_contable = ? WHERE id = ?",
            [$idAsiento !== null && $idAsiento > 0 ? $idAsiento : null, $idEgreso]
        );
    }
    
    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial): bool
    {
        $sql = "SELECT COUNT(*) FROM egresos_cabecera
                WHERE id_empresa = ? AND establecimiento = (SELECT codigo FROM empresa_establecimiento WHERE id = ?)
                  AND punto_emision = (SELECT codigo_punto FROM empresa_punto_emision WHERE id = ?)
                  AND secuencial = ? AND eliminado = FALSE
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)";
        return (int) $this->query($sql, [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial, $idEmpresa])->fetchColumn() > 0;
    }

    public function buscarDocumentosPendientesEgreso(int $idEmpresa, string $q = '', string $tipo = 'COMPRA', ?int $excluirEgresoId = null): array
    {
        $params     = [':id_empresa' => $idEmpresa];
        $excluirSql = '';

        if ($excluirEgresoId !== null) {
            $excluirSql = " AND e.id <> :excluir";
            $params[':excluir'] = $excluirEgresoId;
        }

        if ($tipo === 'COMPRA') {
            $filtroBusq = '';
            if ($q !== '') {
                $filtroBusq = " AND (
                    CONCAT(cb.establecimiento_prov,'-',cb.punto_emision_prov,'-',cb.secuencial_prov) ILIKE :q
                    OR prov.razon_social  ILIKE :q
                    OR prov.identificacion ILIKE :q
                )";
                $params[':q'] = '%' . $q . '%';
            }

            $sql = "WITH pagado AS (
                        SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d
                        INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'COMPRA'
                          AND e.estado != 'anulado'
                          AND e.eliminado = FALSE
                          AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.id_referencia_documento
                    ),
                    nc_nd AS (
                        SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
                               SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
                               SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
                        FROM compras_cabecera nc
                        WHERE nc.tipo_comprobante IN ('04','05')
                          AND nc.eliminado = FALSE
                          AND nc.id_empresa = :id_empresa
                        GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
                    )
                    SELECT 'COMPRA' AS tipo_doc_bd,
                           cb.id,
                           CONCAT(cb.establecimiento_prov,'-',cb.punto_emision_prov,'-',cb.secuencial_prov) AS numero_documento,
                           cb.fecha_emision,
                           0 AS dias_credito,
                           cb.importe_total AS monto_total,
                           COALESCE(p.total_pagado, 0) AS monto_cobrado,
                           (cb.importe_total - COALESCE(p.total_pagado, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0)) AS saldo_pendiente,
                           prov.id             AS proveedor_id,
                           prov.razon_social   AS proveedor_nombre,
                           prov.identificacion AS proveedor_ruc
                    FROM compras_cabecera cb
                    INNER JOIN proveedores prov ON cb.id_proveedor = prov.id
                    LEFT  JOIN pagado p ON cb.id = p.id_referencia_documento
                    LEFT  JOIN nc_nd nn ON nn.id_empresa = cb.id_empresa
                                       AND nn.id_proveedor = cb.id_proveedor
                                       AND nn.documento_modificado = CONCAT(cb.establecimiento_prov,'-',cb.punto_emision_prov,'-',cb.secuencial_prov)
                    WHERE cb.id_empresa = :id_empresa
                      AND cb.eliminado = FALSE
                      AND COALESCE(cb.tipo_comprobante, '01') NOT IN ('04','05')
                      AND cb.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (cb.importe_total - COALESCE(p.total_pagado, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0)) > 0.01
                      $filtroBusq
                    ORDER BY prov.razon_social ASC, cb.fecha_emision ASC
                    LIMIT 301";
        } else {
            // LIQUIDACION
            $filtroBusq = '';
            if ($q !== '') {
                $filtroBusq = " AND (
                    CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) ILIKE :q
                    OR prov.razon_social  ILIKE :q
                    OR prov.identificacion ILIKE :q
                )";
                $params[':q'] = '%' . $q . '%';
            }

            $sql = "WITH pagado AS (
                        SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d
                        INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'LIQUIDACION'
                          AND e.estado != 'anulado'
                          AND e.eliminado = FALSE
                          AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.id_referencia_documento
                    )
                    SELECT 'LIQUIDACION' AS tipo_doc_bd,
                           l.id,
                           CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero_documento,
                           l.fecha_emision,
                           0 AS dias_credito,
                           l.importe_total AS monto_total,
                           COALESCE(p.total_pagado, 0) AS monto_cobrado,
                           (l.importe_total - COALESCE(p.total_pagado, 0)) AS saldo_pendiente,
                           prov.id             AS proveedor_id,
                           prov.razon_social   AS proveedor_nombre,
                           prov.identificacion AS proveedor_ruc
                    FROM liquidaciones_cabecera l
                    INNER JOIN proveedores prov ON l.id_proveedor = prov.id
                    LEFT  JOIN pagado p ON l.id = p.id_referencia_documento
                    WHERE l.id_empresa = :id_empresa
                      AND l.eliminado = FALSE
                      AND l.estado = 'autorizado'
                      AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (l.importe_total - COALESCE(p.total_pagado, 0)) > 0.01
                      $filtroBusq
                    ORDER BY prov.razon_social ASC, l.fecha_emision ASC
                    LIMIT 301";
        }

        $rows    = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > 300;
        return ['data' => array_slice($rows, 0, 300), 'has_more' => $hasMore];
    }

    public function getUltimoNumeroCheque(int $idFormaPago): ?string
    {
        $sql = "SELECT numero_cheque FROM egresos_pagos
                WHERE id_forma_pago = :fp AND numero_cheque IS NOT NULL AND numero_cheque <> ''
                ORDER BY id DESC LIMIT 1";
        $res = $this->query($sql, [':fp' => $idFormaPago])->fetchColumn();
        return $res ?: null;
    }
}
