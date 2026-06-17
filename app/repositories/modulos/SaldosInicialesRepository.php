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
            SELECT s.*,
                   CASE WHEN s.fecha_vencimiento IS NOT NULL
                        THEN (CURRENT_DATE - s.fecha_vencimiento)::int
                        ELSE 0 END AS dias_vencido
            FROM saldos_iniciales_cxc s
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
                monto_cobrado   = COALESCE((
                    SELECT SUM(id2.monto_cobrado)
                    FROM ingresos_detalle id2
                    INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
                    WHERE id2.tipo_documento = 'SALDO_INICIAL'
                      AND id2.id_referencia_documento = s.id
                      AND ic.estado != 'anulado'
                      AND ic.eliminado = false
                ), 0),
                saldo_pendiente = s.saldo_inicial - COALESCE((
                    SELECT SUM(id2.monto_cobrado)
                    FROM ingresos_detalle id2
                    INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
                    WHERE id2.tipo_documento = 'SALDO_INICIAL'
                      AND id2.id_referencia_documento = s.id
                      AND ic.estado != 'anulado'
                      AND ic.eliminado = false
                ), 0),
                estado = CASE
                    WHEN s.saldo_inicial - COALESCE((
                        SELECT SUM(id2.monto_cobrado)
                        FROM ingresos_detalle id2
                        INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
                        WHERE id2.tipo_documento = 'SALDO_INICIAL'
                          AND id2.id_referencia_documento = s.id
                          AND ic.estado != 'anulado'
                          AND ic.eliminado = false
                    ), 0) <= 0 THEN 'PAGADO'
                    WHEN COALESCE((
                        SELECT SUM(id2.monto_cobrado)
                        FROM ingresos_detalle id2
                        INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
                        WHERE id2.tipo_documento = 'SALDO_INICIAL'
                          AND id2.id_referencia_documento = s.id
                          AND ic.estado != 'anulado'
                          AND ic.eliminado = false
                    ), 0) > 0 THEN 'PARCIAL'
                    ELSE 'PENDIENTE'
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE s.id = :id AND s.id_empresa = :id_empresa
        ");
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
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
}
