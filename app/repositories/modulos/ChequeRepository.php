<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos para la Impresión de Cheques.
 *
 * Un "cheque" es un pago de egreso (egresos_pagos) con
 * tipo_operacion_bancaria = 'CHEQUE'. Este repositorio:
 *  - Lee los datos necesarios para imprimir (beneficiario, banco, monto…).
 *  - Lista los cheques por imprimir con su estado de impresión.
 *  - Registra/consulta la tabla de control cheques_impresos.
 */
class ChequeRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('cheques_impresos');

        // Auto-migración transparente (por si aún no se ha desplegado el SQL).
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS cheques_impresos (
                    id                  SERIAL PRIMARY KEY,
                    id_empresa          INTEGER      NOT NULL,
                    id_egreso_pago      INTEGER      NOT NULL,
                    id_egreso           INTEGER,
                    id_forma_pago       INTEGER,
                    numero_cheque       VARCHAR(50),
                    beneficiario        VARCHAR(255),
                    beneficiario_ident  VARCHAR(50),
                    monto               NUMERIC(18,6) DEFAULT 0,
                    fecha_cheque        DATE,
                    banco_nombre        VARCHAR(150),
                    cuenta_numero       VARCHAR(50),
                    es_reimpresion      BOOLEAN      NOT NULL DEFAULT FALSE,
                    fecha_impresion     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    impreso_por         INTEGER,
                    anulado             BOOLEAN      NOT NULL DEFAULT FALSE,
                    anulado_at          TIMESTAMP,
                    anulado_by          INTEGER,
                    eliminado           BOOLEAN      NOT NULL DEFAULT FALSE,
                    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    created_by          INTEGER,
                    deleted_at          TIMESTAMP,
                    deleted_by          INTEGER
                )
            ");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_cheques_impresos_empresa ON cheques_impresos (id_empresa, eliminado)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_cheques_impresos_pago ON cheques_impresos (id_egreso_pago) WHERE anulado = FALSE AND eliminado = FALSE");
        } catch (\Throwable $e) {
            // Silenciar si no hay permisos DDL.
        }
    }

    private function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    // ── SELECT base de cheques (pagos de egreso tipo CHEQUE) ───────────────────

    private function selectBaseCheques(): string
    {
        return "
            SELECT ep.id                         AS id_pago,
                   ep.id_egreso,
                   ep.id_forma_pago,
                   ep.monto,
                   ep.numero_cheque,
                   ep.fecha_cobro                AS fecha_cheque,
                   ep.referencia,
                   e.numero_egreso,
                   e.fecha_emision,
                   e.observaciones,
                   e.tipo_ambiente,
                   COALESCE(NULLIF(ep.beneficiario_cheque, ''), p.razon_social, emp.nombres_apellidos, 'N/A') AS beneficiario,
                   COALESCE(p.identificacion, emp.identificacion, '')     AS beneficiario_ident,
                   fp.nombre                     AS forma_nombre,
                   fp.numero_cuenta              AS cuenta_numero,
                   fp.id_banco                   AS id_banco,
                   b.nombre_banco                AS banco_nombre,
                   ci.id                         AS impreso_id,
                   to_char(ci.fecha_impresion, 'DD-MM-YYYY HH24:MI') AS impreso_fecha,
                   ci.impreso_por,
                   u.nombre                      AS impreso_usuario,
                   (ci.id IS NOT NULL)           AS impreso
            FROM egresos_pagos ep
            INNER JOIN egresos_cabecera e   ON ep.id_egreso = e.id
            INNER JOIN empresa_formas_pago fp ON ep.id_forma_pago = fp.id
            LEFT  JOIN bancos_ecuador b     ON fp.id_banco = b.id
            LEFT  JOIN proveedores p        ON e.id_proveedor = p.id
            LEFT  JOIN empleados emp        ON e.id_empleado = emp.id
            LEFT  JOIN LATERAL (
                    SELECT ci2.id, ci2.fecha_impresion, ci2.impreso_por
                    FROM cheques_impresos ci2
                    WHERE ci2.id_egreso_pago = ep.id
                      AND ci2.anulado = FALSE
                      AND ci2.eliminado = FALSE
                    ORDER BY ci2.id DESC
                    LIMIT 1
            ) ci ON TRUE
            LEFT  JOIN usuarios u ON ci.impreso_por = u.id
        ";
    }

    private function whereBaseCheques(): string
    {
        return "
            WHERE e.id_empresa = :id_empresa
              AND ep.eliminado = FALSE
              AND e.eliminado  = FALSE
              AND UPPER(COALESCE(ep.tipo_operacion_bancaria, '')) = 'CHEQUE'
              AND UPPER(COALESCE(e.estado, '')) <> 'ANULADO'
        ";
    }

    /** Un cheque por id de pago (para impresión individual). */
    public function getChequePorPago(int $idEmpresa, int $idPago): ?array
    {
        $sql = $this->selectBaseCheques() . $this->whereBaseCheques() . " AND ep.id = :id_pago LIMIT 1";
        $row = $this->query($sql, [':id_empresa' => $idEmpresa, ':id_pago' => $idPago])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Varios cheques por ids de pago (para impresión en lote), respetando el orden pedido. */
    public function getChequesPorPagos(int $idEmpresa, array $idsPago): array
    {
        $idsPago = array_values(array_unique(array_filter(array_map('intval', $idsPago))));
        if (empty($idsPago)) return [];

        $ph = [];
        $params = [':id_empresa' => $idEmpresa];
        foreach ($idsPago as $i => $id) {
            $ph[] = ":p$i";
            $params[":p$i"] = $id;
        }
        $sql = $this->selectBaseCheques() . $this->whereBaseCheques()
             . " AND ep.id IN (" . implode(',', $ph) . ")";
        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        // Reordenar según el orden solicitado.
        $byId = [];
        foreach ($rows as $r) { $byId[(int) $r['id_pago']] = $r; }
        $ordenados = [];
        foreach ($idsPago as $id) {
            if (isset($byId[$id])) $ordenados[] = $byId[$id];
        }
        return $ordenados;
    }

    /**
     * Listado de cheques por imprimir (para el modal masivo).
     * Filtros: id_forma_pago, desde, hasta (sobre fecha_cobro), buscar (texto),
     * solo_pendientes (bool).
     */
    public function getChequesPorImprimir(int $idEmpresa, array $f): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->whereBaseCheques();

        if (!empty($f['id_forma_pago'])) {
            $where .= " AND ep.id_forma_pago = :id_forma_pago";
            $params[':id_forma_pago'] = (int) $f['id_forma_pago'];
        }
        if (!empty($f['desde'])) {
            $where .= " AND ep.fecha_cobro >= :desde";
            $params[':desde'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where .= " AND ep.fecha_cobro <= :hasta";
            $params[':hasta'] = $f['hasta'];
        }
        if (!empty($f['solo_pendientes'])) {
            $where .= " AND ci.id IS NULL";
        }
        $q = trim((string) ($f['buscar'] ?? ''));
        if ($q !== '') {
            $palabras = preg_split('/\s+/', $q) ?: [];
            foreach ($palabras as $i => $palabra) {
                $palabra = trim($palabra);
                if ($palabra === '') continue;
                $p = ":bq$i";
                $params[$p] = '%' . $palabra . '%';
                $where .= " AND (ep.numero_cheque ILIKE $p
                              OR e.numero_egreso ILIKE $p
                              OR COALESCE(p.razon_social, emp.nombres_apellidos, '') ILIKE $p
                              OR fp.nombre ILIKE $p)";
            }
        }

        $sql = $this->selectBaseCheques() . $where . " ORDER BY ep.fecha_cobro ASC NULLS LAST, ep.id ASC";
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Estado de impresión ────────────────────────────────────────────────────

    /**
     * Mapa idPago => info de la última impresión vigente (o ausente si nunca se imprimió).
     * @return array<int, array{impreso_id:int, fecha:string, usuario:string, veces:int}>
     */
    public function getEstadoImpreso(int $idEmpresa, array $idsPago): array
    {
        $idsPago = array_values(array_unique(array_filter(array_map('intval', $idsPago))));
        if (empty($idsPago)) return [];

        $ph = [];
        $params = [':id_empresa' => $idEmpresa];
        foreach ($idsPago as $i => $id) {
            $ph[] = ":p$i";
            $params[":p$i"] = $id;
        }
        $sql = "SELECT id_egreso_pago,
                       COUNT(*)                    AS veces,
                       to_char(MAX(fecha_impresion), 'DD-MM-YYYY HH24:MI') AS ultima_fecha
                FROM cheques_impresos
                WHERE id_empresa = :id_empresa
                  AND anulado = FALSE
                  AND eliminado = FALSE
                  AND id_egreso_pago IN (" . implode(',', $ph) . ")
                GROUP BY id_egreso_pago";
        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id_egreso_pago']] = [
                'veces'        => (int) $r['veces'],
                'ultima_fecha' => (string) $r['ultima_fecha'],
            ];
        }
        return $out;
    }

    /** ¿Cuántas impresiones vigentes tiene este pago? */
    public function contarImpresiones(int $idEmpresa, int $idPago): int
    {
        $sql = "SELECT COUNT(*) FROM cheques_impresos
                WHERE id_empresa = :e AND id_egreso_pago = :p
                  AND anulado = FALSE AND eliminado = FALSE";
        return (int) $this->query($sql, [':e' => $idEmpresa, ':p' => $idPago])->fetchColumn();
    }

    // ── Registro de impresión ──────────────────────────────────────────────────

    public function registrarImpreso(array $d): int
    {
        $sql = "INSERT INTO cheques_impresos
                    (id_empresa, id_egreso_pago, id_egreso, id_forma_pago, numero_cheque,
                     beneficiario, beneficiario_ident, monto, fecha_cheque, banco_nombre,
                     cuenta_numero, es_reimpresion, impreso_por, created_by)
                VALUES
                    (:id_empresa, :id_egreso_pago, :id_egreso, :id_forma_pago, :numero_cheque,
                     :beneficiario, :beneficiario_ident, :monto, :fecha_cheque, :banco_nombre,
                     :cuenta_numero, :es_reimpresion, :impreso_por, :created_by)
                RETURNING id";
        $st = $this->query($sql, [
            ':id_empresa'         => (int) $d['id_empresa'],
            ':id_egreso_pago'     => (int) $d['id_egreso_pago'],
            ':id_egreso'          => (int) ($d['id_egreso'] ?? 0) ?: null,
            ':id_forma_pago'      => (int) ($d['id_forma_pago'] ?? 0) ?: null,
            ':numero_cheque'      => $d['numero_cheque'] ?? null,
            ':beneficiario'       => $d['beneficiario'] ?? null,
            ':beneficiario_ident' => $d['beneficiario_ident'] ?? null,
            ':monto'              => (float) ($d['monto'] ?? 0),
            ':fecha_cheque'       => !empty($d['fecha_cheque']) ? $d['fecha_cheque'] : null,
            ':banco_nombre'       => $d['banco_nombre'] ?? null,
            ':cuenta_numero'      => $d['cuenta_numero'] ?? null,
            ':es_reimpresion'     => !empty($d['es_reimpresion']) ? 'true' : 'false',
            ':impreso_por'        => (int) ($d['impreso_por'] ?? 0) ?: null,
            ':created_by'         => (int) ($d['impreso_por'] ?? 0) ?: null,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Anula (soft) todas las impresiones vigentes de un pago. */
    public function anularImpresiones(int $idEmpresa, int $idPago, int $idUsuario): int
    {
        $sql = "UPDATE cheques_impresos
                SET anulado = TRUE, anulado_at = CURRENT_TIMESTAMP, anulado_by = :u
                WHERE id_empresa = :e AND id_egreso_pago = :p
                  AND anulado = FALSE AND eliminado = FALSE";
        return $this->query($sql, [':e' => $idEmpresa, ':p' => $idPago, ':u' => $idUsuario])->rowCount();
    }
}
