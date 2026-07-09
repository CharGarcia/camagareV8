<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class IngresoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ingresos_cabecera');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE i.id_empresa = :id_empresa AND i.eliminado = false AND i.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (i.numero_ingreso ILIKE :buscar OR i.recibo_de ILIKE :buscar OR c.nombre ILIKE :buscar OR c.identificacion ILIKE :buscar OR i.observaciones ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'recibo_de'      => 'i.recibo_de',
                'beneficiario'   => 'i.recibo_de',
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => 'i.numero_ingreso',
                'nro'            => 'i.numero_ingreso',
                'concepto'       => 'i.observaciones',
                'obs'            => 'i.observaciones',
            ],
            'exacto'   => [ 'estado' => 'i.estado', 'tipo' => 'i.tipo_ingreso' ],
            'fecha'    => [ 'fecha' => 'i.fecha_emision', 'fecha_emision' => 'i.fecha_emision' ],
            'numerico' => [ 'monto' => 'i.monto_total', 'total' => 'i.monto_total' ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND i.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $sqlCount = "SELECT COUNT(*) FROM ingresos_cabecera i LEFT JOIN clientes c ON i.id_cliente = c.id $where";
        $total = (int) $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'numero_ingreso', 'tipo_ingreso', 'monto_total', 'estado', 'cliente_nombre', 'observaciones', 'recibo_de'];
        if (!in_array($ordenCol, $allowedCols)) {
            $ordenCol = 'fecha_emision';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match($ordenCol) {
            'cliente_nombre' => 'COALESCE(i.recibo_de, c.nombre, \'—\')',
            'recibo_de'      => 'COALESCE(i.recibo_de, c.nombre, \'—\')',
            default          => "i.$ordenCol",
        };

        $sql = "SELECT i.*,
                       c.nombre AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       rc.nombre AS recibo_cliente_nombre,
                       u.nombre AS usuario_nombre,
                       eic.nombre AS concepto_nombre
                FROM ingresos_cabecera i
                LEFT JOIN clientes c  ON i.id_cliente        = c.id
                LEFT JOIN clientes rc ON i.id_recibo_cliente  = rc.id
                LEFT JOIN usuarios u ON i.id_usuario = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso eic ON i.id_ingreso_concepto = eic.id
                $where
                ORDER BY $ordenExpr $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT i.*,
                       c.nombre AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.email AS cliente_email,
                       rc.nombre AS recibo_cliente_nombre,
                       rc.email AS recibo_cliente_email,
                       u.nombre AS usuario_nombre,
                       eic.nombre AS concepto_nombre,
                       est.nombre AS establecimiento_nombre,
                       pto.nombre AS punto_emision_nombre
                FROM ingresos_cabecera i
                LEFT JOIN clientes c  ON i.id_cliente        = c.id
                LEFT JOIN clientes rc ON i.id_recibo_cliente  = rc.id
                LEFT JOIN usuarios u ON i.id_usuario = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso eic ON i.id_ingreso_concepto = eic.id
                LEFT JOIN empresa_establecimiento est ON i.id_establecimiento = est.id
                LEFT JOIN empresa_punto_emision pto ON i.id_punto_emision = pto.id
                WHERE i.id = :id AND i.id_empresa = :id_empresa AND i.eliminado = FALSE";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idIngreso): array
    {
        $sql = "SELECT d.*,
                       COALESCE(cv.id, cr.id, cs.id, s.id_cliente)                 AS id_cliente,
                       COALESCE(cv.nombre, cr.nombre, cs.nombre, s.nombre_cliente)  AS cliente_nombre,
                       COALESCE(v.fecha_emision, rv.fecha_emision, s.fecha_emision) AS fecha_documento
                FROM ingresos_detalle d
                LEFT JOIN ventas_cabecera v        ON d.id_referencia_documento = v.id  AND d.tipo_documento = 'FACTURA'
                LEFT JOIN clientes cv              ON v.id_cliente = cv.id
                LEFT JOIN recibos_venta_cabecera rv ON d.id_referencia_documento = rv.id AND d.tipo_documento = 'RECIBO'
                LEFT JOIN clientes cr              ON rv.id_cliente = cr.id
                LEFT JOIN saldos_iniciales_cxc s   ON d.id_referencia_documento = s.id  AND d.tipo_documento = 'SALDO_INICIAL'
                LEFT JOIN clientes cs              ON s.id_cliente = cs.id
                WHERE d.id_ingreso = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idIngreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * IDs de saldos iniciales CXC (saldos_iniciales_cxc.id) cobrados por un ingreso,
     * para recalcular su saldo pendiente al crear/editar/anular/eliminar el ingreso.
     *
     * @return int[]
     */
    public function getSaldosInicialesReferenciados(int $idIngreso): array
    {
        $sql = "SELECT DISTINCT id_referencia_documento
                FROM ingresos_detalle
                WHERE id_ingreso = ?
                  AND tipo_documento = 'SALDO_INICIAL'
                  AND id_referencia_documento IS NOT NULL";
        return array_map('intval', $this->query($sql, [$idIngreso])->fetchAll(PDO::FETCH_COLUMN));
    }

    public function getPagos(int $idIngreso): array
    {
        $sql = "SELECT ip.*, efc.nombre AS forma_cobro_nombre
                FROM ingresos_pagos ip
                INNER JOIN empresa_formas_pago efc ON ip.id_forma_cobro = efc.id
                WHERE ip.id_ingreso = ?
                ORDER BY ip.id ASC";
        return $this->query($sql, [$idIngreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFormasCobro(int $idEmpresa): array
    {
        $sql = "SELECT * FROM empresa_formas_pago 
                WHERE id_empresa = :id_empresa AND activo = TRUE AND eliminado = FALSE 
                  AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')
                ORDER BY nombre ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConceptosIngreso(int $idEmpresa): array
    {
        $sql = "SELECT o.*, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso o
                LEFT JOIN plan_cuentas pc ON pc.id = o.id_cuenta_contable
                WHERE o.id_empresa = :id_empresa
                  AND o.aplica_ingresos = TRUE
                  AND UPPER(o.estado) = 'ACTIVO'
                  AND o.eliminado = FALSE
                ORDER BY o.nombre ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFacturasPendientes(int $idCliente, int $idEmpresa, ?int $excluirIngresoId = null): array
    {
        $excluirSql = "";
        $params = [':id_cliente' => $idCliente, ':id_empresa' => $idEmpresa];

        if ($excluirIngresoId !== null) {
            $excluirSql = " AND i.id <> :excluir ";
            $params[':excluir'] = $excluirIngresoId;
        }

        $excluirSqlSi = $excluirIngresoId !== null ? " AND i.id <> :excluir" : '';

        // Nota: Se calcula el saldo dinámicamente restando lo ya cobrado en ingresos activos
        // y las retenciones de venta del cliente. Incluye también los saldos iniciales CXC.
        $sql = "WITH cobrado AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) as total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'FACTURA'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                cobrado_rec AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) as total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'RECIBO'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                retenido_fact AS (
                    SELECT id_venta, SUM(total_renta + total_iva + total_isd) AS total_retenido
                    FROM retencion_venta_cabecera
                    WHERE id_empresa = :id_empresa
                      AND eliminado = FALSE
                      AND id_venta IS NOT NULL
                      AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                    GROUP BY id_venta
                ),
                cobrado_si AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'SALDO_INICIAL'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSqlSi
                    GROUP BY id_referencia_documento
                ),
                retenido_si AS (
                    SELECT s.id AS id_saldo, SUM(rd.valor_retenido) AS total_retenido
                    FROM saldos_iniciales_cxc s
                    INNER JOIN retencion_venta_cabecera r
                        ON r.eliminado = FALSE AND r.id_empresa = s.id_empresa
                       AND r.id_venta IS NULL AND r.id_cliente = s.id_cliente
                    INNER JOIN retencion_venta_detalle rd
                        ON rd.id_retencion = r.id
                       AND rd.num_doc_sustento IS NOT NULL AND rd.num_doc_sustento <> ''
                       AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                           = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                    WHERE s.id_empresa = :id_empresa AND s.eliminado = FALSE
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa AND vc.eliminado = FALSE
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                    GROUP BY s.id
                ),
                nc_aplic AS (
                    -- Notas de crédito de venta, enlazadas a la factura por num_doc_modificado
                    SELECT nc.num_doc_modificado, SUM(nc.importe_total) AS total_nc
                    FROM notas_credito_cabecera nc
                    WHERE nc.estado != 'anulado'
                      AND nc.eliminado = false
                      AND nc.id_empresa = :id_empresa
                    GROUP BY nc.num_doc_modificado
                )
                SELECT * FROM (
                    SELECT 'FACTURA'::varchar AS tipo_documento,
                           v.id,
                           CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_documento,
                           v.fecha_emision,
                           v.importe_total,
                           COALESCE(c.total_cobrado, 0) AS monto_cobrado,
                           COALESCE(rf.total_retenido, 0) AS monto_retenido,
                           (v.importe_total - COALESCE(c.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) AS saldo_pendiente
                    FROM ventas_cabecera v
                    LEFT JOIN cobrado c        ON v.id = c.id_referencia_documento
                    LEFT JOIN retenido_fact rf ON v.id = rf.id_venta
                    LEFT JOIN nc_aplic ncf     ON ncf.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
                    WHERE v.id_cliente = :id_cliente
                      AND v.id_empresa = :id_empresa
                      AND v.estado = 'autorizado' -- Solo facturas vigentes/autorizadas
                      AND v.eliminado = FALSE
                      AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (v.importe_total - COALESCE(c.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) > 0.01

                    UNION ALL

                    SELECT 'SALDO_INICIAL'::varchar AS tipo_documento,
                           s.id,
                           s.nro_documento AS numero_documento,
                           s.fecha_emision,
                           s.saldo_inicial AS importe_total,
                           COALESCE(csi.total_cobrado, 0) AS monto_cobrado,
                           COALESCE(rsi.total_retenido, 0)  AS monto_retenido,
                           (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) AS saldo_pendiente
                    FROM saldos_iniciales_cxc s
                    LEFT JOIN cobrado_si csi  ON s.id = csi.id_referencia_documento
                    LEFT JOIN retenido_si rsi ON s.id = rsi.id_saldo
                    WHERE s.id_cliente = :id_cliente
                      AND s.id_empresa = :id_empresa
                      AND s.eliminado = FALSE
                      AND (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) > 0.01

                    UNION ALL

                    SELECT 'RECIBO'::varchar AS tipo_documento,
                           r.id,
                           CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial) AS numero_documento,
                           r.fecha_emision,
                           r.importe_total,
                           COALESCE(cr.total_cobrado, 0) AS monto_cobrado,
                           0 AS monto_retenido,
                           (r.importe_total - COALESCE(cr.total_cobrado, 0)) AS saldo_pendiente
                    FROM recibos_venta_cabecera r
                    LEFT JOIN cobrado_rec cr ON r.id = cr.id_referencia_documento
                    WHERE r.id_cliente = :id_cliente
                      AND r.id_empresa = :id_empresa
                      AND r.estado NOT IN ('anulado','facturado')
                      AND r.eliminado = FALSE
                      AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (r.importe_total - COALESCE(cr.total_cobrado, 0)) > 0.01
                ) docs
                ORDER BY fecha_emision ASC, id ASC";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO ingresos_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, numero_ingreso,
                    tipo_ingreso, id_ingreso_concepto, monto_total, observaciones, estado,
                    recibo_de, id_recibo_cliente, tipo_ambiente,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :id_empresa, :id_establecimiento, :id_punto_emision, :id_cliente, :id_usuario,
                    :fecha_emision, :establecimiento, :punto_emision, :secuencial, :numero_ingreso,
                    :tipo_ingreso, :id_ingreso_concepto, :monto_total, :observaciones, :estado,
                    :recibo_de, :id_recibo_cliente,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'           => (int) $data['id_empresa'],
            ':id_establecimiento'   => !empty($data['id_establecimiento']) ? (int) $data['id_establecimiento'] : null,
            ':id_punto_emision'     => !empty($data['id_punto_emision']) ? (int) $data['id_punto_emision'] : null,
            ':id_cliente'           => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
            ':id_usuario'           => (int) $data['id_usuario'],
            ':fecha_emision'        => $data['fecha_emision'],
            ':establecimiento'      => $data['establecimiento'] ?? null,
            ':punto_emision'        => $data['punto_emision'] ?? null,
            ':secuencial'           => $data['secuencial'],
            ':numero_ingreso'       => $data['numero_ingreso'],
            ':tipo_ingreso'         => $data['tipo_ingreso'],
            ':id_ingreso_concepto'  => !empty($data['id_ingreso_concepto']) ? (int) $data['id_ingreso_concepto'] : null,
            ':monto_total'          => (float) $data['monto_total'],
            ':observaciones'        => $data['observaciones'] ?? null,
            ':estado'               => $data['estado'] ?? 'registrado',
            ':recibo_de'            => !empty($data['recibo_de']) ? trim($data['recibo_de']) : null,
            ':id_recibo_cliente'    => !empty($data['id_recibo_cliente']) ? (int) $data['id_recibo_cliente'] : null,
        ]);

        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE ingresos_cabecera SET
                    id_establecimiento  = :id_establecimiento,
                    id_punto_emision    = :id_punto_emision,
                    id_cliente          = :id_cliente,
                    fecha_emision       = :fecha_emision,
                    establecimiento     = :establecimiento,
                    punto_emision       = :punto_emision,
                    secuencial          = :secuencial,
                    numero_ingreso      = :numero_ingreso,
                    tipo_ingreso        = :tipo_ingreso,
                    id_ingreso_concepto = :id_ingreso_concepto,
                    monto_total         = :monto_total,
                    observaciones       = :observaciones,
                    recibo_de           = :recibo_de,
                    id_recibo_cliente   = :id_recibo_cliente,
                    updated_by          = :id_usuario,
                    updated_at          = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'                   => $id,
            ':id_empresa'           => (int) $data['id_empresa'],
            ':id_establecimiento'   => !empty($data['id_establecimiento']) ? (int) $data['id_establecimiento'] : null,
            ':id_punto_emision'     => !empty($data['id_punto_emision']) ? (int) $data['id_punto_emision'] : null,
            ':id_cliente'           => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
            ':id_usuario'           => (int) $data['id_usuario'],
            ':fecha_emision'        => $data['fecha_emision'],
            ':establecimiento'      => $data['establecimiento'] ?? null,
            ':punto_emision'        => $data['punto_emision'] ?? null,
            ':secuencial'           => $data['secuencial'],
            ':numero_ingreso'       => $data['numero_ingreso'],
            ':tipo_ingreso'         => $data['tipo_ingreso'],
            ':id_ingreso_concepto'  => !empty($data['id_ingreso_concepto']) ? (int) $data['id_ingreso_concepto'] : null,
            ':monto_total'          => (float) $data['monto_total'],
            ':observaciones'        => $data['observaciones'] ?? null,
            ':recibo_de'            => !empty($data['recibo_de']) ? trim($data['recibo_de']) : null,
            ':id_recibo_cliente'    => !empty($data['id_recibo_cliente']) ? (int) $data['id_recibo_cliente'] : null,
        ]);
    }

    public function insertDetalle(array $data): void
    {
        $sql = "INSERT INTO ingresos_detalle (
                    id_ingreso, tipo_documento, id_referencia_documento, numero_documento, 
                    descripcion, monto_documento, saldo_anterior, monto_cobrado, saldo_actual
                ) VALUES (
                    :id_ingreso, :tipo_documento, :id_ref, :num_doc,
                    :desc, :monto_doc, :saldo_ant, :monto_cob, :saldo_act
                )";
        $this->query($sql, [
            ':id_ingreso'   => (int) $data['id_ingreso'],
            ':tipo_documento' => $data['tipo_documento'],
            ':id_ref'       => !empty($data['id_referencia_documento']) ? (int)$data['id_referencia_documento'] : null,
            ':num_doc'      => $data['numero_documento'] ?? null,
            ':desc'         => $data['descripcion'] ?? null,
            ':monto_doc'    => (float) ($data['monto_documento'] ?? 0),
            ':saldo_ant'    => (float) ($data['saldo_anterior'] ?? 0),
            ':monto_cob'    => (float) ($data['monto_cobrado'] ?? 0),
            ':saldo_act'    => (float) ($data['saldo_actual'] ?? 0),
        ]);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO ingresos_pagos (
                    id_ingreso, id_forma_cobro, monto, referencia, observaciones,
                    tipo_operacion_bancaria, numero_cheque, fecha_cobro
                ) VALUES (
                    :id_ingreso, :id_forma, :monto, :ref, :obs,
                    :tipo_op, :num_chq, :fec_cob
                )";
        $this->query($sql, [
            ':id_ingreso' => (int) $data['id_ingreso'],
            ':id_forma'   => (int) $data['id_forma_cobro'],
            ':monto'      => (float) $data['monto'],
            ':ref'        => $data['referencia'] ?? null,
            ':obs'        => $data['observaciones'] ?? null,
            ':tipo_op'    => $data['tipo_operacion_bancaria'] ?? null,
            ':num_chq'    => $data['numero_cheque'] ?? null,
            ':fec_cob'    => !empty($data['fecha_cobro']) ? $data['fecha_cobro'] : null,
        ]);
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ingresos_cabecera
                SET estado = 'anulado', updated_at = CURRENT_TIMESTAMP, updated_by = :usr
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':usr' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    /**
     * Devuelve los ingresos ACTIVOS (no anulados, no eliminados) que cobran la factura indicada,
     * incluyendo cuántos documentos distintos cobra cada ingreso (para detectar cobros multi-factura).
     *
     * @return array Filas con: id_ingreso, numero_ingreso, total_documentos
     */
    public function getIngresosActivosPorFactura(int $idVenta, int $idEmpresa): array
    {
        $sql = "SELECT i.id AS id_ingreso,
                       i.numero_ingreso,
                       (SELECT COUNT(DISTINCT (d2.tipo_documento, d2.id_referencia_documento))
                          FROM ingresos_detalle d2
                         WHERE d2.id_ingreso = i.id) AS total_documentos
                FROM ingresos_cabecera i
                INNER JOIN ingresos_detalle d
                        ON d.id_ingreso = i.id
                       AND d.tipo_documento = 'FACTURA'
                       AND d.id_referencia_documento = :id_venta
                WHERE i.id_empresa = :emp
                  AND i.eliminado = FALSE
                  AND i.estado != 'anulado'
                GROUP BY i.id, i.numero_ingreso";
        $st = $this->db->prepare($sql);
        $st->execute([':id_venta' => $idVenta, ':emp' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function eliminarLogico(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ingresos_cabecera 
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usr 
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':usr' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function deleteDetalles(int $idIngreso): void
    {
        $this->query("DELETE FROM ingresos_detalle WHERE id_ingreso = ?", [$idIngreso]);
    }

    /** Enlaza (o desvincula con null) el asiento contable generado al ingreso. */
    public function updateAsientoContable(int $idIngreso, ?int $idAsiento): void
    {
        $this->query(
            "UPDATE ingresos_cabecera SET id_asiento_contable = ? WHERE id = ?",
            [$idAsiento !== null && $idAsiento > 0 ? $idAsiento : null, $idIngreso]
        );
    }

    public function deletePagos(int $idIngreso): void
    {
        $this->query("DELETE FROM ingresos_pagos WHERE id_ingreso = ?", [$idIngreso]);
    }

    public function buscarDocumentosPendientes(int $idEmpresa, string $q = '', ?int $excluirIngresoId = null, string $tipo = 'FACTURA'): array
    {
        // Según el concepto del ingreso: 'RECIBO' muestra solo recibos de venta;
        // cualquier otro ('FACTURA') muestra facturas de venta + saldos iniciales CXC.
        $tiposPermitidos = strtoupper($tipo) === 'RECIBO' ? '{RECIBO}' : '{FACTURA,SALDO_INICIAL}';

        $params     = [':id_empresa' => $idEmpresa, ':tipos' => $tiposPermitidos];
        $excluirSql = '';
        $filtroBusq = '';
        $filtroBusqRec = '';

        if ($excluirIngresoId !== null) {
            $excluirSql = " AND i.id <> :excluir";
            $params[':excluir'] = $excluirIngresoId;
        }

        if ($q !== '') {
            $filtroBusq = " AND (
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) ILIKE :q
                OR c.nombre          ILIKE :q
                OR c.identificacion  ILIKE :q
            )";
            $filtroBusqRec = " AND (
                CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial) ILIKE :q
                OR c.nombre          ILIKE :q
                OR c.identificacion  ILIKE :q
            )";
            $params[':q'] = '%' . $q . '%';
        }

        // Filtro de búsqueda para la rama de saldos iniciales CXC (usa el mismo :q)
        $filtroBusqCxc = '';
        if ($q !== '') {
            $filtroBusqCxc = " AND (
                s.nro_documento ILIKE :q
                OR COALESCE(c.nombre, s.nombre_cliente)         ILIKE :q
                OR COALESCE(c.identificacion, s.ruc_cliente)    ILIKE :q
            )";
        }

        // Exclusión del propio ingreso (al editar) también para los cobros de saldos iniciales
        $excluirSqlSi = $excluirIngresoId !== null ? " AND i.id <> :excluir" : '';

        $sql = "WITH cobrado AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'FACTURA'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                cobrado_rec AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'RECIBO'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                retenido_fact AS (
                    SELECT id_venta, SUM(total_renta + total_iva + total_isd) AS total_retenido
                    FROM retencion_venta_cabecera
                    WHERE id_empresa = :id_empresa
                      AND eliminado = FALSE
                      AND id_venta IS NOT NULL
                      AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                    GROUP BY id_venta
                ),
                cobrado_si AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'SALDO_INICIAL'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSqlSi
                    GROUP BY id_referencia_documento
                ),
                retenido_si AS (
                    SELECT s.id AS id_saldo, SUM(rd.valor_retenido) AS total_retenido
                    FROM saldos_iniciales_cxc s
                    INNER JOIN retencion_venta_cabecera r
                        ON r.eliminado = FALSE AND r.id_empresa = s.id_empresa
                       AND r.id_venta IS NULL AND r.id_cliente = s.id_cliente
                    INNER JOIN retencion_venta_detalle rd
                        ON rd.id_retencion = r.id
                       AND rd.num_doc_sustento IS NOT NULL AND rd.num_doc_sustento <> ''
                       AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                           = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                    WHERE s.id_empresa = :id_empresa AND s.eliminado = FALSE
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa AND vc.eliminado = FALSE
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                    GROUP BY s.id
                ),
                nc_aplic AS (
                    -- Notas de crédito de venta, enlazadas a la factura por num_doc_modificado
                    SELECT nc.num_doc_modificado, SUM(nc.importe_total) AS total_nc
                    FROM notas_credito_cabecera nc
                    WHERE nc.estado != 'anulado'
                      AND nc.eliminado = false
                      AND nc.id_empresa = :id_empresa
                    GROUP BY nc.num_doc_modificado
                )
                SELECT * FROM (
                    -- Facturas de venta pendientes (saldo neto = total - cobros - retenciones - notas de crédito)
                    SELECT 'FACTURA'::varchar AS tipo_documento,
                           v.id,
                           CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_documento,
                           v.fecha_emision,
                           v.dias_credito,
                           v.importe_total,
                           COALESCE(cb.total_cobrado, 0) AS monto_cobrado,
                           COALESCE(rf.total_retenido, 0) AS monto_retenido,
                           (v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) AS saldo_pendiente,
                           c.id             AS id_cliente,
                           c.nombre         AS cliente_nombre,
                           c.identificacion AS cliente_ruc
                    FROM ventas_cabecera v
                    INNER JOIN clientes c ON v.id_cliente = c.id
                    LEFT  JOIN cobrado cb       ON v.id = cb.id_referencia_documento
                    LEFT  JOIN retenido_fact rf ON v.id = rf.id_venta
                    LEFT  JOIN nc_aplic ncf     ON ncf.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
                    WHERE v.id_empresa = :id_empresa
                      AND v.estado = 'autorizado'
                      AND v.eliminado = FALSE
                      AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) > 0.01
                      $filtroBusq

                    UNION ALL

                    -- Saldos iniciales CXC pendientes (saldo = inicial - cobros - retención registrada)
                    SELECT 'SALDO_INICIAL'::varchar AS tipo_documento,
                           s.id,
                           s.nro_documento AS numero_documento,
                           s.fecha_emision,
                           CASE WHEN s.fecha_vencimiento IS NOT NULL
                                THEN (s.fecha_vencimiento - s.fecha_emision)::int
                                ELSE 0 END AS dias_credito,
                           s.saldo_inicial AS importe_total,
                           COALESCE(csi.total_cobrado, 0) AS monto_cobrado,
                           COALESCE(rsi.total_retenido, 0)  AS monto_retenido,
                           (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) AS saldo_pendiente,
                           c.id                                          AS id_cliente,
                           COALESCE(c.nombre, s.nombre_cliente)          AS cliente_nombre,
                           COALESCE(c.identificacion, s.ruc_cliente)     AS cliente_ruc
                    FROM saldos_iniciales_cxc s
                    LEFT JOIN clientes c      ON s.id_cliente = c.id
                    LEFT JOIN cobrado_si csi  ON s.id = csi.id_referencia_documento
                    LEFT JOIN retenido_si rsi ON s.id = rsi.id_saldo
                    WHERE s.id_empresa = :id_empresa
                      AND s.eliminado = FALSE
                      AND (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) > 0.01
                      $filtroBusqCxc

                    UNION ALL

                    -- Recibos de venta pendientes (saldo = total - cobros)
                    SELECT 'RECIBO'::varchar AS tipo_documento,
                           r.id,
                           CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial) AS numero_documento,
                           r.fecha_emision,
                           r.dias_credito,
                           r.importe_total,
                           COALESCE(cr.total_cobrado, 0) AS monto_cobrado,
                           0 AS monto_retenido,
                           (r.importe_total - COALESCE(cr.total_cobrado, 0)) AS saldo_pendiente,
                           c.id             AS id_cliente,
                           c.nombre         AS cliente_nombre,
                           c.identificacion AS cliente_ruc
                    FROM recibos_venta_cabecera r
                    INNER JOIN clientes c ON r.id_cliente = c.id
                    LEFT  JOIN cobrado_rec cr ON r.id = cr.id_referencia_documento
                    WHERE r.id_empresa = :id_empresa
                      AND r.estado NOT IN ('anulado','facturado')
                      AND r.eliminado = FALSE
                      AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (r.importe_total - COALESCE(cr.total_cobrado, 0)) > 0.01
                      $filtroBusqRec
                ) docs
                WHERE docs.tipo_documento = ANY(:tipos::text[])
                ORDER BY cliente_nombre ASC, fecha_emision ASC, id ASC
                LIMIT 301";

        $rows    = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > 300;
        return ['data' => array_slice($rows, 0, 300), 'has_more' => $hasMore];
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM ingresos_cabecera
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND eliminado = FALSE
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)";
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial, $idEmpresa];

        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }

        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }
}
