<?php
declare(strict_types=1);

namespace App\repositories;

/**
 * PayphoneRepository
 * Acceso a datos de payphone_config y payphone_transacciones.
 * Global — no depende de módulo ni empresa específica.
 */
class PayphoneRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('payphone_transacciones');
    }

    // ─── CONFIG ───────────────────────────────────────────────────────────────

    public function getConfig(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM payphone_config
             WHERE id_empresa = :ie AND eliminado = false
             LIMIT 1"
        );
        $st->execute([':ie' => $idEmpresa]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function upsertConfig(array $d): void
    {
        $st = $this->db->prepare(
            "INSERT INTO payphone_config
                (id_empresa, token, store_id, ambiente, activo, created_by, updated_by)
             VALUES
                (:ie, :token, :store, :amb, :activo, :cb, :ub)
             ON CONFLICT (id_empresa) DO UPDATE SET
                token      = EXCLUDED.token,
                store_id   = EXCLUDED.store_id,
                ambiente   = EXCLUDED.ambiente,
                activo     = EXCLUDED.activo,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = EXCLUDED.updated_by"
        );
        $st->execute([
            ':ie'     => $d['id_empresa'],
            ':token'  => $d['token'],
            ':store'  => $d['store_id']   ?? null,
            ':amb'    => $d['ambiente']   ?? 'production',
            ':activo' => $d['activo']     ? 'true' : 'false',
            ':cb'     => $d['id_usuario'] ?? null,
            ':ub'     => $d['id_usuario'] ?? null,
        ]);
    }

    // ─── TRANSACCIONES ────────────────────────────────────────────────────────

    public function crearTransaccion(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO payphone_transacciones
                (id_empresa, client_transaction_id, modulo, id_referencia,
                 descripcion, monto, moneda, estado,
                 url_retorno, url_cancelacion, url_exito, tipo_flujo, created_by)
             VALUES
                (:ie, :ctid, :mod, :ref,
                 :desc, :monto, :moneda, 'pendiente',
                 :url_ret, :url_can, :url_ok, :flujo, :cb)
             RETURNING id"
        );
        $st->execute([
            ':ie'      => $d['id_empresa'],
            ':ctid'    => $d['client_transaction_id'],
            ':mod'     => $d['modulo'],
            ':ref'     => $d['id_referencia']    ?? null,
            ':desc'    => $d['descripcion']      ?? null,
            ':monto'   => $d['monto'],
            ':moneda'  => $d['moneda']           ?? 'USD',
            ':url_ret' => $d['url_retorno']      ?? null,
            ':url_can' => $d['url_cancelacion']  ?? null,
            ':url_ok'  => $d['url_exito']        ?? null,
            ':flujo'   => $d['tipo_flujo']       ?? 'boton',
            ':cb'      => $d['id_usuario']       ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarPaymentId(string $clientTransactionId, int $paymentId): void
    {
        $st = $this->db->prepare(
            "UPDATE payphone_transacciones
             SET payment_id = :pid, updated_at = CURRENT_TIMESTAMP
             WHERE client_transaction_id = :ctid AND eliminado = false"
        );
        $st->execute([':pid' => $paymentId, ':ctid' => $clientTransactionId]);
    }

    public function vincularIngreso(string $clientTransactionId, int $idIngreso): void
    {
        $st = $this->db->prepare(
            "UPDATE payphone_transacciones
             SET id_ingreso = :iid, updated_at = CURRENT_TIMESTAMP
             WHERE client_transaction_id = :ctid AND eliminado = false"
        );
        $st->execute([':iid' => $idIngreso, ':ctid' => $clientTransactionId]);
    }

    /**
     * Busca la forma de cobro "Tarjeta" de una empresa (para registrar ingresos por tarjeta).
     * Retorna el registro o null si no existe configurada.
     */
    public function getFormaCobroTarjeta(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, tipo
             FROM empresa_formas_pago
             WHERE id_empresa = :ie
               AND eliminado  = false
               AND activo     = true
               AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')
               AND LOWER(nombre) LIKE '%tarjeta%'
             ORDER BY nombre ASC
             LIMIT 1"
        );
        $st->execute([':ie' => $idEmpresa]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function actualizarResultado(string $clientTransactionId, array $d): void
    {
        $st = $this->db->prepare(
            "UPDATE payphone_transacciones SET
                transaction_id     = :tid,
                transaction_status = :ts,
                estado             = :est,
                authorization_code = :auth,
                response_data      = :rd::jsonb,
                updated_at         = CURRENT_TIMESTAMP,
                updated_by         = :ub
             WHERE client_transaction_id = :ctid AND eliminado = false"
        );
        $st->execute([
            ':tid'  => $d['transaction_id']     ?? null,
            ':ts'   => $d['transaction_status'] ?? null,
            ':est'  => $d['estado'],
            ':auth' => $d['authorization_code'] ?? null,
            ':rd'   => json_encode($d['response_data'] ?? []),
            ':ub'   => $d['id_usuario']         ?? null,
            ':ctid' => $clientTransactionId,
        ]);
    }

    public function getTransaccionByClientId(string $clientTransactionId): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM payphone_transacciones
             WHERE client_transaction_id = :ctid AND eliminado = false
             LIMIT 1"
        );
        $st->execute([':ctid' => $clientTransactionId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getTransaccionById(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM payphone_transacciones
             WHERE id = :id AND eliminado = false
             LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getTransaccionesPorReferencia(int $idEmpresa, string $modulo, int $idReferencia): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM payphone_transacciones
             WHERE id_empresa    = :ie
               AND modulo        = :mod
               AND id_referencia = :ref
               AND eliminado     = false
             ORDER BY created_at DESC"
        );
        $st->execute([':ie' => $idEmpresa, ':mod' => $modulo, ':ref' => $idReferencia]);
        return $st->fetchAll();
    }

    public function getListado(int $idEmpresa, string $modulo = '', string $estado = '', int $page = 1, int $perPage = 30): array
    {
        $where  = "WHERE t.id_empresa = :ie AND t.eliminado = false";
        $params = [':ie' => $idEmpresa];

        if ($modulo !== '') {
            $where .= " AND t.modulo = :mod";
            $params[':mod'] = $modulo;
        }
        if ($estado !== '') {
            $where .= " AND t.estado = :est";
            $params[':est'] = $estado;
        }

        $countSt = $this->db->prepare("SELECT COUNT(*) FROM payphone_transacciones t $where");
        $countSt->execute($params);
        $total = (int) $countSt->fetchColumn();

        $offset            = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $st = $this->db->prepare(
            "SELECT t.*
             FROM payphone_transacciones t
             $where
             ORDER BY t.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $type = is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $st->bindValue($k, $v, $type);
        }
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total];
    }
}
