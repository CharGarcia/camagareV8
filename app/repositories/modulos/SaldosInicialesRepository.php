<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class SaldosInicialesRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('saldos_iniciales_cxc');
    }

    // ─────────────────────────────────────────────────────────
    // CXC
    // ─────────────────────────────────────────────────────────

    public function getCxcListado(int $idEmpresa, array $filtros = []): array
    {
        // Pendiente real = saldo_inicial - cobrado - retenido (retenido calculado
        // al vuelo desde las retenciones de venta, igual que en cuentas por cobrar).
        $pendiente = '(s.saldo_inicial - s.monto_cobrado - COALESCE(ret.retenido, 0))';

        $where  = 'WHERE s.id_empresa = :id_empresa AND s.eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        $estado = $filtros['estado'] ?? 'PENDIENTES';
        if ($estado === 'PENDIENTES') {
            $where .= " AND {$pendiente} > 0";
        } elseif ($estado === 'VENCIDAS') {
            $where .= " AND {$pendiente} > 0 AND s.fecha_vencimiento < CURRENT_DATE";
        } elseif ($estado === 'AL_DIA') {
            $where .= " AND {$pendiente} > 0 AND (s.fecha_vencimiento IS NULL OR s.fecha_vencimiento >= CURRENT_DATE)";
        } elseif ($estado === 'PAGADAS') {
            $where .= " AND {$pendiente} <= 0";
        }

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND s.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND s.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['id_cliente'])) {
            $rawClientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $clientes = array_filter(array_map('intval', $rawClientes));
            if (!empty($clientes)) {
                $in = [];
                foreach (array_values($clientes) as $i => $id) {
                    $k = ":cli{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND s.id_cliente IN (" . implode(',', $in) . ")";
            }
        }

        $sql = "
            SELECT s.id, s.id_empresa, s.id_lote, s.nro_documento,
                   s.fecha_emision, s.fecha_vencimiento,
                   s.id_cliente, s.nombre_cliente, s.ruc_cliente, s.observaciones,
                   s.saldo_inicial, s.monto_cobrado,
                   COALESCE(ret.retenido, 0)         AS monto_retenido,
                   {$pendiente}                      AS saldo_pendiente,
                   CASE
                       WHEN {$pendiente} <= 0                         THEN 'PAGADO'
                       WHEN (s.monto_cobrado + COALESCE(ret.retenido, 0)) > 0 THEN 'PARCIAL'
                       ELSE 'PENDIENTE'
                   END AS estado,
                   CASE WHEN s.fecha_vencimiento IS NOT NULL
                        THEN (CURRENT_DATE - s.fecha_vencimiento)::int
                        ELSE 0 END AS dias_vencido
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
            $where
            ORDER BY s.fecha_vencimiento ASC NULLS LAST, s.fecha_emision DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCxcPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM saldos_iniciales_cxc WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertCxc(array $d): int
    {
        $st = $this->db->prepare("
            INSERT INTO saldos_iniciales_cxc
                (id_empresa, id_lote, nro_documento, fecha_emision, fecha_vencimiento,
                 id_cliente, nombre_cliente, ruc_cliente,
                 saldo_inicial, monto_cobrado, saldo_pendiente, estado, observaciones,
                 created_by, updated_by)
            VALUES
                (:id_empresa, :id_lote, :nro_documento, :fecha_emision, :fecha_vencimiento,
                 :id_cliente, :nombre_cliente, :ruc_cliente,
                 :saldo_inicial, 0, :saldo_inicial, 'PENDIENTE', :observaciones,
                 :created_by, :created_by)
            RETURNING id
        ");
        $st->execute([
            ':id_empresa'       => $d['id_empresa'],
            ':id_lote'          => $d['id_lote'] ?? null,
            ':nro_documento'    => trim($d['nro_documento']),
            ':fecha_emision'    => $d['fecha_emision'],
            ':fecha_vencimiento'=> $d['fecha_vencimiento'] ?: null,
            ':id_cliente'       => !empty($d['id_cliente']) ? (int)$d['id_cliente'] : null,
            ':nombre_cliente'   => trim($d['nombre_cliente']),
            ':ruc_cliente'      => trim($d['ruc_cliente'] ?? '') ?: null,
            ':saldo_inicial'    => (float)$d['saldo_inicial'],
            ':observaciones'    => trim($d['observaciones'] ?? '') ?: null,
            ':created_by'       => $d['created_by'] ?? null,
        ]);
        return (int)$st->fetchColumn();
    }

    public function updateCxc(int $id, int $idEmpresa, array $d): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_cxc SET
                nro_documento     = :nro_documento,
                fecha_emision     = :fecha_emision,
                fecha_vencimiento = :fecha_vencimiento,
                id_cliente        = :id_cliente,
                nombre_cliente    = :nombre_cliente,
                ruc_cliente       = :ruc_cliente,
                saldo_inicial     = :saldo_inicial,
                saldo_pendiente   = :saldo_inicial - monto_cobrado,
                observaciones     = :observaciones,
                updated_at        = CURRENT_TIMESTAMP,
                updated_by        = :updated_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([
            ':nro_documento'    => trim($d['nro_documento']),
            ':fecha_emision'    => $d['fecha_emision'],
            ':fecha_vencimiento'=> $d['fecha_vencimiento'] ?: null,
            ':id_cliente'       => !empty($d['id_cliente']) ? (int)$d['id_cliente'] : null,
            ':nombre_cliente'   => trim($d['nombre_cliente']),
            ':ruc_cliente'      => trim($d['ruc_cliente'] ?? '') ?: null,
            ':saldo_inicial'    => (float)$d['saldo_inicial'],
            ':observaciones'    => trim($d['observaciones'] ?? '') ?: null,
            ':updated_by'       => $d['updated_by'] ?? null,
            ':id'               => $id,
            ':id_empresa'       => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    public function deleteCxc(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_cxc SET
                eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function tieneCxcCobros(int $id): bool
    {
        $st = $this->db->prepare("
            SELECT COUNT(*) FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
            WHERE id2.tipo_documento = 'SALDO_INICIAL'
              AND id2.id_referencia_documento = :id
              AND ic.estado != 'anulado'
              AND ic.eliminado = false
        ");
        $st->execute([':id' => $id]);
        return (int)$st->fetchColumn() > 0;
    }

    public function actualizarMontoCobradoCxc(int $id, int $idEmpresa): void
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_cxc s SET
                monto_cobrado   = c.cobrado,
                saldo_pendiente = s.saldo_inicial - c.cobrado,
                estado = CASE
                    WHEN s.saldo_inicial - c.cobrado <= 0 THEN 'PAGADO'
                    WHEN c.cobrado > 0                    THEN 'PARCIAL'
                    ELSE 'PENDIENTE'
                END,
                updated_at = CURRENT_TIMESTAMP
            FROM (
                SELECT COALESCE(SUM(id2.monto_cobrado), 0) AS cobrado
                FROM ingresos_detalle id2
                INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
                WHERE id2.tipo_documento = 'SALDO_INICIAL'
                  AND id2.id_referencia_documento = :id
                  AND ic.estado != 'anulado'
                  AND ic.eliminado = false
            ) c
            WHERE s.id = :id AND s.id_empresa = :id_empresa
        ");
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    /**
     * Total retenido (calculado, NO almacenado) que afecta a un saldo inicial
     * CXC: suma de lo retenido por las retenciones de venta cuyo documento
     * sustento coincide con el nro_documento del saldo y el mismo cliente, y que
     * NO corresponde a una factura real (para no duplicar con la CxC normal).
     * Mismo criterio que las facturas en cuentas por cobrar.
     */
    public function getRetenidoSaldoCxc(int $idSaldo, int $idEmpresa): float
    {
        $st = $this->db->prepare("
            SELECT COALESCE((
                SELECT SUM(rd.valor_retenido)
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
            ), 0)
            FROM saldos_iniciales_cxc s
            WHERE s.id = :id AND s.id_empresa = :ie
        ");
        $st->execute([':id' => $idSaldo, ':ie' => $idEmpresa]);
        return (float) $st->fetchColumn();
    }

    public function getHistorialCobrosCxc(int $id, int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT ic.id, ic.fecha_emision, ic.numero_ingreso, ic.observaciones,
                   id2.monto_cobrado,
                   u.nombre AS usuario_nombre,
                   efp.nombre AS forma_cobro
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
            LEFT  JOIN usuarios u ON u.id = ic.id_usuario
            LEFT  JOIN ingresos_pagos ip ON ip.id_ingreso = ic.id
            LEFT  JOIN empresa_formas_pago efp ON efp.id = ip.id_forma_cobro
            WHERE id2.tipo_documento = 'SALDO_INICIAL'
              AND id2.id_referencia_documento = :id
              AND ic.id_empresa = :id_empresa
              AND ic.estado != 'anulado'
              AND ic.eliminado = false
            ORDER BY ic.fecha_emision DESC, ic.id DESC
        ");
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────
    // CXP
    // ─────────────────────────────────────────────────────────

    public function getCxpListado(int $idEmpresa, array $filtros = []): array
    {
        $where  = 'WHERE s.id_empresa = :id_empresa AND s.eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        $estado = $filtros['estado'] ?? 'PENDIENTES';
        if ($estado === 'PENDIENTES') {
            $where .= " AND s.saldo_pendiente > 0";
        } elseif ($estado === 'VENCIDAS') {
            $where .= " AND s.saldo_pendiente > 0 AND s.fecha_vencimiento < CURRENT_DATE";
        } elseif ($estado === 'AL_DIA') {
            $where .= " AND s.saldo_pendiente > 0 AND (s.fecha_vencimiento IS NULL OR s.fecha_vencimiento >= CURRENT_DATE)";
        } elseif ($estado === 'PAGADAS') {
            $where .= " AND s.saldo_pendiente <= 0";
        }

        if (!empty($filtros['tipo_documento'])) {
            $where .= " AND s.tipo_documento = :tipo_doc";
            $params[':tipo_doc'] = $filtros['tipo_documento'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND s.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND s.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }

        $sql = "
            SELECT s.*,
                   CASE WHEN s.fecha_vencimiento IS NOT NULL
                        THEN (CURRENT_DATE - s.fecha_vencimiento)::int
                        ELSE 0 END AS dias_vencido
            FROM saldos_iniciales_cxp s
            $where
            ORDER BY s.fecha_vencimiento ASC NULLS LAST, s.fecha_emision DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCxpPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM saldos_iniciales_cxp WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertCxp(array $d): int
    {
        $st = $this->db->prepare("
            INSERT INTO saldos_iniciales_cxp
                (id_empresa, id_lote, tipo_documento, nro_documento, fecha_emision, fecha_vencimiento,
                 id_proveedor, nombre_proveedor, ruc_proveedor,
                 saldo_inicial, monto_pagado, saldo_pendiente, estado, observaciones,
                 created_by, updated_by)
            VALUES
                (:id_empresa, :id_lote, :tipo_documento, :nro_documento, :fecha_emision, :fecha_vencimiento,
                 :id_proveedor, :nombre_proveedor, :ruc_proveedor,
                 :saldo_inicial, 0, :saldo_inicial, 'PENDIENTE', :observaciones,
                 :created_by, :created_by)
            RETURNING id
        ");
        $st->execute([
            ':id_empresa'       => $d['id_empresa'],
            ':id_lote'          => $d['id_lote'] ?? null,
            ':tipo_documento'   => $d['tipo_documento'],
            ':nro_documento'    => trim($d['nro_documento']),
            ':fecha_emision'    => $d['fecha_emision'],
            ':fecha_vencimiento'=> $d['fecha_vencimiento'] ?: null,
            ':id_proveedor'     => !empty($d['id_proveedor']) ? (int)$d['id_proveedor'] : null,
            ':nombre_proveedor' => trim($d['nombre_proveedor']),
            ':ruc_proveedor'    => trim($d['ruc_proveedor'] ?? '') ?: null,
            ':saldo_inicial'    => (float)$d['saldo_inicial'],
            ':observaciones'    => trim($d['observaciones'] ?? '') ?: null,
            ':created_by'       => $d['created_by'] ?? null,
        ]);
        return (int)$st->fetchColumn();
    }

    public function updateCxp(int $id, int $idEmpresa, array $d): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_cxp SET
                tipo_documento    = :tipo_documento,
                nro_documento     = :nro_documento,
                fecha_emision     = :fecha_emision,
                fecha_vencimiento = :fecha_vencimiento,
                id_proveedor      = :id_proveedor,
                nombre_proveedor  = :nombre_proveedor,
                ruc_proveedor     = :ruc_proveedor,
                saldo_inicial     = :saldo_inicial,
                saldo_pendiente   = :saldo_inicial - monto_pagado,
                observaciones     = :observaciones,
                updated_at        = CURRENT_TIMESTAMP,
                updated_by        = :updated_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([
            ':tipo_documento'   => $d['tipo_documento'],
            ':nro_documento'    => trim($d['nro_documento']),
            ':fecha_emision'    => $d['fecha_emision'],
            ':fecha_vencimiento'=> $d['fecha_vencimiento'] ?: null,
            ':id_proveedor'     => !empty($d['id_proveedor']) ? (int)$d['id_proveedor'] : null,
            ':nombre_proveedor' => trim($d['nombre_proveedor']),
            ':ruc_proveedor'    => trim($d['ruc_proveedor'] ?? '') ?: null,
            ':saldo_inicial'    => (float)$d['saldo_inicial'],
            ':observaciones'    => trim($d['observaciones'] ?? '') ?: null,
            ':updated_by'       => $d['updated_by'] ?? null,
            ':id'               => $id,
            ':id_empresa'       => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    public function deleteCxp(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_cxp SET
                eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function tieneCxpPagos(int $id): bool
    {
        $st = $this->db->prepare("
            SELECT COUNT(*) FROM egresos_detalle ed
            INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
            WHERE ed.tipo_documento = 'SALDO_INICIAL'
              AND ed.id_referencia_documento = :id
              AND ec.estado != 'anulado'
              AND ec.eliminado = false
              AND ed.eliminado = false
        ");
        $st->execute([':id' => $id]);
        return (int)$st->fetchColumn() > 0;
    }

    public function actualizarMontoPagadoCxp(int $id, int $idEmpresa): void
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_cxp s SET
                monto_pagado    = COALESCE((
                    SELECT SUM(ed.monto_pagado)
                    FROM egresos_detalle ed
                    INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                    WHERE ed.tipo_documento = 'SALDO_INICIAL'
                      AND ed.id_referencia_documento = s.id
                      AND ec.estado != 'anulado'
                      AND ec.eliminado = false
                      AND ed.eliminado = false
                ), 0),
                saldo_pendiente = s.saldo_inicial - COALESCE((
                    SELECT SUM(ed.monto_pagado)
                    FROM egresos_detalle ed
                    INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                    WHERE ed.tipo_documento = 'SALDO_INICIAL'
                      AND ed.id_referencia_documento = s.id
                      AND ec.estado != 'anulado'
                      AND ec.eliminado = false
                      AND ed.eliminado = false
                ), 0),
                estado = CASE
                    WHEN s.saldo_inicial - COALESCE((
                        SELECT SUM(ed.monto_pagado)
                        FROM egresos_detalle ed
                        INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                        WHERE ed.tipo_documento = 'SALDO_INICIAL'
                          AND ed.id_referencia_documento = s.id
                          AND ec.estado != 'anulado'
                          AND ec.eliminado = false
                          AND ed.eliminado = false
                    ), 0) <= 0 THEN 'PAGADO'
                    WHEN COALESCE((
                        SELECT SUM(ed.monto_pagado)
                        FROM egresos_detalle ed
                        INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                        WHERE ed.tipo_documento = 'SALDO_INICIAL'
                          AND ed.id_referencia_documento = s.id
                          AND ec.estado != 'anulado'
                          AND ec.eliminado = false
                          AND ed.eliminado = false
                    ), 0) > 0 THEN 'PARCIAL'
                    ELSE 'PENDIENTE'
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE s.id = :id AND s.id_empresa = :id_empresa
        ");
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function getHistorialPagosCxp(int $id, int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT ec.id, ec.fecha_emision, ec.numero_egreso, ec.observaciones,
                   ed.monto_pagado,
                   u.nombre AS usuario_nombre,
                   efp.nombre AS forma_pago
            FROM egresos_detalle ed
            INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
            LEFT  JOIN usuarios u ON u.id = ec.created_by
            LEFT  JOIN egresos_pagos ep ON ep.id_egreso = ec.id
            LEFT  JOIN empresa_formas_pago efp ON efp.id = ep.id_forma_pago
            WHERE ed.tipo_documento = 'SALDO_INICIAL'
              AND ed.id_referencia_documento = :id
              AND ec.id_empresa = :id_empresa
              AND ec.estado != 'anulado'
              AND ec.eliminado = false
              AND ed.eliminado = false
            ORDER BY ec.fecha_emision DESC, ec.id DESC
        ");
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────
    // BANCOS
    // ─────────────────────────────────────────────────────────

    public function getBancosDisponibles(int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT efp.id, efp.nombre, efp.tipo,
                   efp.numero_cuenta, efp.tipo_cuenta,
                   sib.id AS id_saldo_inicial,
                   sib.fecha_saldo, sib.saldo_inicial, sib.observaciones
            FROM empresa_formas_pago efp
            LEFT JOIN saldos_iniciales_bancos sib
                   ON sib.id_forma_pago = efp.id
                  AND sib.id_empresa = efp.id_empresa
                  AND sib.eliminado = false
            WHERE efp.id_empresa = :id_empresa
              AND efp.eliminado  = false
              AND efp.activo     = true
              AND efp.tipo IN ('BANCO','TARJETA')
            ORDER BY efp.tipo, efp.nombre
        ");
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEfectivoDisponibles(int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT efp.id, efp.nombre, efp.tipo,
                   sib.id AS id_saldo_inicial,
                   sib.fecha_saldo, sib.saldo_inicial, sib.observaciones
            FROM empresa_formas_pago efp
            LEFT JOIN saldos_iniciales_bancos sib
                   ON sib.id_forma_pago = efp.id
                  AND sib.id_empresa = efp.id_empresa
                  AND sib.eliminado = false
            WHERE efp.id_empresa = :id_empresa
              AND efp.eliminado  = false
              AND efp.activo     = true
              AND efp.tipo = 'EFECTIVO'
            ORDER BY efp.nombre
        ");
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAnticiposDisponibles(int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT efp.id, efp.nombre, efp.tipo, efp.aplica_en,
                   sib.id AS id_saldo_inicial,
                   sib.fecha_saldo, sib.saldo_inicial, sib.observaciones
            FROM empresa_formas_pago efp
            LEFT JOIN saldos_iniciales_bancos sib
                   ON sib.id_forma_pago = efp.id
                  AND sib.id_empresa = efp.id_empresa
                  AND sib.eliminado = false
            WHERE efp.id_empresa = :id_empresa
              AND efp.eliminado  = false
              AND efp.activo     = true
              AND efp.tipo = 'ANTICIPO'
            ORDER BY efp.aplica_en, efp.nombre
        ");
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Forma de pago de tipo ANTICIPO por id (con su dirección aplica_en). */
    public function getFormaAnticipoPorId(int $idEmpresa, int $id): ?array
    {
        $st = $this->db->prepare("
            SELECT id, nombre, aplica_en
            FROM empresa_formas_pago
            WHERE id = :id AND id_empresa = :e AND eliminado = false
              AND tipo = 'ANTICIPO'
        ");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSaldosAnticipo(int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT a.*, efp.nombre AS forma_nombre
            FROM saldos_iniciales_anticipos a
            LEFT JOIN empresa_formas_pago efp ON efp.id = a.id_forma_pago
            WHERE a.id_empresa = :e AND a.eliminado = false
            ORDER BY a.tipo, a.fecha_saldo DESC, a.id DESC
        ");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAnticipoPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("
            SELECT * FROM saldos_iniciales_anticipos
            WHERE id = :id AND id_empresa = :e AND eliminado = false
        ");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertAnticipo(array $d): int
    {
        $st = $this->db->prepare("
            INSERT INTO saldos_iniciales_anticipos
                (id_empresa, id_forma_pago, tipo, id_cliente, id_proveedor,
                 nombre_tercero, ruc_tercero, fecha_saldo, saldo_inicial, observaciones,
                 created_by, updated_by)
            VALUES
                (:id_empresa, :id_forma_pago, :tipo, :id_cliente, :id_proveedor,
                 :nombre_tercero, :ruc_tercero, :fecha_saldo, :saldo_inicial, :observaciones,
                 :created_by, :created_by)
            RETURNING id
        ");
        $st->execute([
            ':id_empresa'     => $d['id_empresa'],
            ':id_forma_pago'  => (int)$d['id_forma_pago'],
            ':tipo'           => $d['tipo'],
            ':id_cliente'     => $d['tipo'] === 'CLIENTE'   ? (int)$d['id_tercero'] : null,
            ':id_proveedor'   => $d['tipo'] === 'PROVEEDOR' ? (int)$d['id_tercero'] : null,
            ':nombre_tercero' => trim($d['nombre_tercero']),
            ':ruc_tercero'    => trim($d['ruc_tercero'] ?? '') ?: null,
            ':fecha_saldo'    => $d['fecha_saldo'],
            ':saldo_inicial'  => (float)$d['saldo_inicial'],
            ':observaciones'  => trim($d['observaciones'] ?? '') ?: null,
            ':created_by'     => $d['created_by'] ?? null,
        ]);
        return (int)$st->fetchColumn();
    }

    public function updateAnticipo(int $id, int $idEmpresa, array $d): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_anticipos SET
                id_forma_pago  = :id_forma_pago,
                tipo           = :tipo,
                id_cliente     = :id_cliente,
                id_proveedor   = :id_proveedor,
                nombre_tercero = :nombre_tercero,
                ruc_tercero    = :ruc_tercero,
                fecha_saldo    = :fecha_saldo,
                saldo_inicial  = :saldo_inicial,
                observaciones  = :observaciones,
                updated_at     = CURRENT_TIMESTAMP,
                updated_by     = :updated_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([
            ':id_forma_pago'  => (int)$d['id_forma_pago'],
            ':tipo'           => $d['tipo'],
            ':id_cliente'     => $d['tipo'] === 'CLIENTE'   ? (int)$d['id_tercero'] : null,
            ':id_proveedor'   => $d['tipo'] === 'PROVEEDOR' ? (int)$d['id_tercero'] : null,
            ':nombre_tercero' => trim($d['nombre_tercero']),
            ':ruc_tercero'    => trim($d['ruc_tercero'] ?? '') ?: null,
            ':fecha_saldo'    => $d['fecha_saldo'],
            ':saldo_inicial'  => (float)$d['saldo_inicial'],
            ':observaciones'  => trim($d['observaciones'] ?? '') ?: null,
            ':updated_by'     => $d['updated_by'] ?? null,
            ':id'             => $id,
            ':id_empresa'     => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    public function deleteAnticipo(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_anticipos SET
                eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function upsertBanco(int $idEmpresa, int $idFormaPago, array $d): void
    {
        $st = $this->db->prepare("
            INSERT INTO saldos_iniciales_bancos
                (id_empresa, id_forma_pago, fecha_saldo, saldo_inicial, observaciones, created_by, updated_by)
            VALUES
                (:id_empresa, :id_forma_pago, :fecha_saldo, :saldo_inicial, :observaciones, :uid, :uid)
            ON CONFLICT (id_empresa, id_forma_pago) DO UPDATE SET
                fecha_saldo   = EXCLUDED.fecha_saldo,
                saldo_inicial = EXCLUDED.saldo_inicial,
                observaciones = EXCLUDED.observaciones,
                eliminado     = false,
                updated_at    = CURRENT_TIMESTAMP,
                updated_by    = EXCLUDED.updated_by
        ");
        $st->execute([
            ':id_empresa'   => $idEmpresa,
            ':id_forma_pago'=> $idFormaPago,
            ':fecha_saldo'  => $d['fecha_saldo'],
            ':saldo_inicial'=> (float)$d['saldo_inicial'],
            ':observaciones'=> trim($d['observaciones'] ?? '') ?: null,
            ':uid'          => $d['created_by'] ?? null,
        ]);
    }

    public function deleteBanco(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_bancos SET
                eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    // ─────────────────────────────────────────────────────────
    // LOTES
    // ─────────────────────────────────────────────────────────

    public function insertLote(int $idEmpresa, string $tipo, string $nombreArchivo, int $totalRegistros, int $idUsuario): int
    {
        $st = $this->db->prepare("
            INSERT INTO saldos_iniciales_lotes (id_empresa, tipo, nombre_archivo, total_registros, created_by)
            VALUES (:id_empresa, :tipo, :nombre_archivo, :total_registros, :created_by)
            RETURNING id
        ");
        $st->execute([
            ':id_empresa'       => $idEmpresa,
            ':tipo'             => $tipo,
            ':nombre_archivo'   => $nombreArchivo,
            ':total_registros'  => $totalRegistros,
            ':created_by'       => $idUsuario,
        ]);
        return (int)$st->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────
    // CLIENTES (para vincular CXC)
    // ─────────────────────────────────────────────────────────

    /** Busca clientes registrados por identificación o nombre. */
    public function buscarClientes(int $idEmpresa, string $q, int $limit = 20): array
    {
        $st = $this->db->prepare("
            SELECT c.id, c.identificacion, c.nombre AS nombre,
                   icv.nombre AS tipo_nombre
            FROM clientes c
            LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
            WHERE c.id_empresa = :ie AND c.eliminado = false
              AND (c.identificacion ILIKE :q OR c.nombre ILIKE :q)
            ORDER BY c.nombre ASC
            LIMIT {$limit}
        ");
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Devuelve un cliente por id (validando empresa). */
    public function getClientePorId(int $idEmpresa, int $id): ?array
    {
        $st = $this->db->prepare("
            SELECT id, identificacion, nombre
            FROM clientes
            WHERE id = :id AND id_empresa = :ie AND eliminado = false
        ");
        $st->execute([':id' => $id, ':ie' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Resuelve un cliente por su identificación exacta (RUC/cédula/pasaporte/exterior). */
    public function getClientePorIdentificacion(int $idEmpresa, string $identificacion): ?array
    {
        $st = $this->db->prepare("
            SELECT id, identificacion, nombre
            FROM clientes
            WHERE id_empresa = :ie AND eliminado = false
              AND identificacion = :ident
            ORDER BY id ASC
            LIMIT 1
        ");
        $st->execute([':ie' => $idEmpresa, ':ident' => $identificacion]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─────────────────────────────────────────────────────────
    // PROVEEDORES (para vincular CXP)
    // ─────────────────────────────────────────────────────────

    /** Busca proveedores registrados por identificación o razón social. */
    public function buscarProveedores(int $idEmpresa, string $q, int $limit = 20): array
    {
        $st = $this->db->prepare("
            SELECT p.id, p.identificacion, p.razon_social AS nombre,
                   icv.nombre AS tipo_nombre
            FROM proveedores p
            LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = p.tipo_id_proveedor
            WHERE p.id_empresa = :ie AND p.eliminado = false
              AND (p.identificacion ILIKE :q OR p.razon_social ILIKE :q)
            ORDER BY p.razon_social ASC
            LIMIT {$limit}
        ");
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Devuelve un proveedor por id (validando empresa). */
    public function getProveedorPorId(int $idEmpresa, int $id): ?array
    {
        $st = $this->db->prepare("
            SELECT id, identificacion, razon_social AS nombre
            FROM proveedores
            WHERE id = :id AND id_empresa = :ie AND eliminado = false
        ");
        $st->execute([':id' => $id, ':ie' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Resuelve un proveedor por su identificación exacta. */
    public function getProveedorPorIdentificacion(int $idEmpresa, string $identificacion): ?array
    {
        $st = $this->db->prepare("
            SELECT id, identificacion, razon_social AS nombre
            FROM proveedores
            WHERE id_empresa = :ie AND eliminado = false
              AND identificacion = :ident
            ORDER BY id ASC
            LIMIT 1
        ");
        $st->execute([':ie' => $idEmpresa, ':ident' => $identificacion]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─────────────────────────────────────────────────────────
    // INVENTARIO — saldos de apertura (entradas en el kardex)
    // ─────────────────────────────────────────────────────────

    /**
     * Fija el tipo_ambiente de una fila del kardex al ambiente real de la
     * empresa (la columna tiene default fijo '1', no dinámico). Necesario
     * para que el saldo inicial sea visible y cuente en el stock cuando la
     * empresa opera en ambiente de producción ('2').
     */
    public function fixKardexAmbiente(int $idKardex, int $idEmpresa): void
    {
        $st = $this->db->prepare("
            UPDATE inventario_kardex
            SET tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
            WHERE id = :id AND id_empresa = :e
        ");
        $st->execute([':id' => $idKardex, ':e' => $idEmpresa]);
    }

    /** Lista las entradas de apertura del kardex (referencia_tipo = SALDO_INICIAL). */
    public function getSaldosInventario(int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT k.id, k.fecha_movimiento, k.cantidad, k.costo_unitario, k.costo_total,
                   k.numero_lote, k.fecha_caducidad, k.nup, k.observaciones,
                   k.id_producto, k.id_bodega,
                   p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                   b.nombre AS bodega_nombre
            FROM inventario_kardex k
            INNER JOIN productos p ON p.id = k.id_producto
            INNER JOIN bodegas   b ON b.id = k.id_bodega
            WHERE k.id_empresa = :e AND k.eliminado = false
              AND k.referencia_tipo = 'SALDO_INICIAL'
              AND k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
            ORDER BY k.id DESC
        ");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────
    // CONSIGNACIONES — registro de saldo pendiente (no afecta stock)
    // ─────────────────────────────────────────────────────────

    /** Producto por id (validando empresa). */
    public function getProductoPorId(int $idEmpresa, int $id): ?array
    {
        $st = $this->db->prepare("SELECT id, codigo, nombre FROM productos WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Nombre de vendedor por id (o null). */
    public function getVendedorNombre(int $idEmpresa, int $id): ?string
    {
        $st = $this->db->prepare("SELECT nombre FROM vendedores WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v !== false ? (string)$v : null;
    }

    /** Nombre de bodega por id (o null). */
    public function getBodegaNombre(int $idEmpresa, int $id): ?string
    {
        $st = $this->db->prepare("SELECT nombre FROM bodegas WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v !== false ? (string)$v : null;
    }

    /** Producto por código exacto (para importación). */
    public function getProductoPorCodigo(int $idEmpresa, string $codigo): ?array
    {
        $st = $this->db->prepare("
            SELECT id, codigo, nombre FROM productos
            WHERE id_empresa = :e AND eliminado = false AND LOWER(codigo) = LOWER(:c)
            ORDER BY id ASC LIMIT 1
        ");
        $st->execute([':e' => $idEmpresa, ':c' => $codigo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Bodega por nombre exacto (para importación). */
    public function getBodegaPorNombre(int $idEmpresa, string $nombre): ?array
    {
        $st = $this->db->prepare("
            SELECT id, nombre FROM bodegas
            WHERE id_empresa = :e AND eliminado = false AND LOWER(nombre) = LOWER(:n)
            ORDER BY id ASC LIMIT 1
        ");
        $st->execute([':e' => $idEmpresa, ':n' => $nombre]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Vendedor por nombre exacto (para importación). */
    public function getVendedorPorNombre(int $idEmpresa, string $nombre): ?array
    {
        $st = $this->db->prepare("
            SELECT id, nombre FROM vendedores
            WHERE id_empresa = :e AND eliminado = false AND LOWER(nombre) = LOWER(:n)
            ORDER BY id ASC LIMIT 1
        ");
        $st->execute([':e' => $idEmpresa, ':n' => $nombre]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSaldosConsignacion(int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT * FROM saldos_iniciales_consignaciones
            WHERE id_empresa = :e AND eliminado = false
            ORDER BY fecha_emision DESC, id DESC
        ");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConsignacionPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("
            SELECT * FROM saldos_iniciales_consignaciones
            WHERE id = :id AND id_empresa = :e AND eliminado = false
        ");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertConsignacion(array $d): int
    {
        $st = $this->db->prepare("
            INSERT INTO saldos_iniciales_consignaciones
                (id_empresa, fecha_emision, nro_documento,
                 id_cliente, nombre_cliente, ruc_cliente,
                 id_vendedor, nombre_vendedor, id_bodega, nombre_bodega,
                 id_producto, producto_codigo, producto_nombre,
                 cantidad, precio_unitario, total,
                 lote, fecha_caducidad, nup, observaciones,
                 created_by, updated_by)
            VALUES
                (:id_empresa, :fecha_emision, :nro_documento,
                 :id_cliente, :nombre_cliente, :ruc_cliente,
                 :id_vendedor, :nombre_vendedor, :id_bodega, :nombre_bodega,
                 :id_producto, :producto_codigo, :producto_nombre,
                 :cantidad, :precio_unitario, :total,
                 :lote, :fecha_caducidad, :nup, :observaciones,
                 :created_by, :created_by)
            RETURNING id
        ");
        $st->execute([
            ':id_empresa'      => $d['id_empresa'],
            ':fecha_emision'   => $d['fecha_emision'],
            ':nro_documento'   => trim($d['nro_documento'] ?? '') ?: null,
            ':id_cliente'      => (int)$d['id_cliente'],
            ':nombre_cliente'  => trim($d['nombre_cliente']),
            ':ruc_cliente'     => trim($d['ruc_cliente'] ?? '') ?: null,
            ':id_vendedor'     => !empty($d['id_vendedor']) ? (int)$d['id_vendedor'] : null,
            ':nombre_vendedor' => trim($d['nombre_vendedor'] ?? '') ?: null,
            ':id_bodega'       => !empty($d['id_bodega']) ? (int)$d['id_bodega'] : null,
            ':nombre_bodega'   => trim($d['nombre_bodega'] ?? '') ?: null,
            ':id_producto'     => (int)$d['id_producto'],
            ':producto_codigo' => trim($d['producto_codigo'] ?? '') ?: null,
            ':producto_nombre' => trim($d['producto_nombre']),
            ':cantidad'        => (float)$d['cantidad'],
            ':precio_unitario' => (float)($d['precio_unitario'] ?? 0),
            ':total'           => (float)($d['cantidad']) * (float)($d['precio_unitario'] ?? 0),
            ':lote'            => trim($d['lote'] ?? '') ?: null,
            ':fecha_caducidad' => !empty($d['fecha_caducidad']) ? $d['fecha_caducidad'] : null,
            ':nup'             => trim($d['nup'] ?? '') ?: null,
            ':observaciones'   => trim($d['observaciones'] ?? '') ?: null,
            ':created_by'      => $d['created_by'] ?? null,
        ]);
        return (int)$st->fetchColumn();
    }

    public function updateConsignacion(int $id, int $idEmpresa, array $d): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_consignaciones SET
                fecha_emision   = :fecha_emision,
                nro_documento   = :nro_documento,
                id_cliente      = :id_cliente,
                nombre_cliente  = :nombre_cliente,
                ruc_cliente     = :ruc_cliente,
                id_vendedor     = :id_vendedor,
                nombre_vendedor = :nombre_vendedor,
                id_bodega       = :id_bodega,
                nombre_bodega   = :nombre_bodega,
                id_producto     = :id_producto,
                producto_codigo = :producto_codigo,
                producto_nombre = :producto_nombre,
                cantidad        = :cantidad,
                precio_unitario = :precio_unitario,
                total           = :total,
                lote            = :lote,
                fecha_caducidad = :fecha_caducidad,
                nup             = :nup,
                observaciones   = :observaciones,
                updated_at      = CURRENT_TIMESTAMP,
                updated_by      = :updated_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([
            ':fecha_emision'   => $d['fecha_emision'],
            ':nro_documento'   => trim($d['nro_documento'] ?? '') ?: null,
            ':id_cliente'      => (int)$d['id_cliente'],
            ':nombre_cliente'  => trim($d['nombre_cliente']),
            ':ruc_cliente'     => trim($d['ruc_cliente'] ?? '') ?: null,
            ':id_vendedor'     => !empty($d['id_vendedor']) ? (int)$d['id_vendedor'] : null,
            ':nombre_vendedor' => trim($d['nombre_vendedor'] ?? '') ?: null,
            ':id_bodega'       => !empty($d['id_bodega']) ? (int)$d['id_bodega'] : null,
            ':nombre_bodega'   => trim($d['nombre_bodega'] ?? '') ?: null,
            ':id_producto'     => (int)$d['id_producto'],
            ':producto_codigo' => trim($d['producto_codigo'] ?? '') ?: null,
            ':producto_nombre' => trim($d['producto_nombre']),
            ':cantidad'        => (float)$d['cantidad'],
            ':precio_unitario' => (float)($d['precio_unitario'] ?? 0),
            ':total'           => (float)($d['cantidad']) * (float)($d['precio_unitario'] ?? 0),
            ':lote'            => trim($d['lote'] ?? '') ?: null,
            ':fecha_caducidad' => !empty($d['fecha_caducidad']) ? $d['fecha_caducidad'] : null,
            ':nup'             => trim($d['nup'] ?? '') ?: null,
            ':observaciones'   => trim($d['observaciones'] ?? '') ?: null,
            ':updated_by'      => $d['updated_by'] ?? null,
            ':id'              => $id,
            ':id_empresa'      => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    public function deleteConsignacion(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("
            UPDATE saldos_iniciales_consignaciones SET
                eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }
}
