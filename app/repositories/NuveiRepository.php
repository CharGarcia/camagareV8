<?php
declare(strict_types=1);

namespace App\repositories;

use App\Helpers\CryptoHelper;

/**
 * NuveiRepository
 * Acceso a datos de nuvei_config, nuvei_transacciones y nuvei_webhook_log.
 * Global — no depende de módulo ni empresa específica (mismo patrón que PayphoneRepository).
 */
class NuveiRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('nuvei_transacciones');
    }

    // ─── CONFIG ───────────────────────────────────────────────────────────────

    public function getConfig(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM nuvei_config
             WHERE id_empresa = :ie AND eliminado = false
             LIMIT 1"
        );
        $st->execute([':ie' => $idEmpresa]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }

        // Los app_key se guardan cifrados en reposo; se devuelven listos para usar.
        if (!empty($row['app_key_server'])) {
            $row['app_key_server'] = CryptoHelper::desencriptar($row['app_key_server']);
        }
        if (!empty($row['app_key_linktopay'])) {
            $row['app_key_linktopay'] = CryptoHelper::desencriptar($row['app_key_linktopay']);
        }

        return $row;
    }

    public function upsertConfig(array $d): void
    {
        $keyServer    = !empty($d['app_key_server'])    ? CryptoHelper::encriptar($d['app_key_server'])    : null;
        $keyLinkToPay = !empty($d['app_key_linktopay']) ? CryptoHelper::encriptar($d['app_key_linktopay']) : null;

        $st = $this->db->prepare(
            "INSERT INTO nuvei_config
                (id_empresa, ambiente, app_code_server, app_key_server,
                 app_code_linktopay, app_key_linktopay,
                 activo_checkout, activo_addcard, activo_linktopay,
                 created_by, updated_by)
             VALUES
                (:ie, :amb, :cs, :ks,
                 :cl, :kl,
                 :act_co, :act_ac, :act_lp,
                 :cb, :ub)
             ON CONFLICT (id_empresa) DO UPDATE SET
                ambiente           = EXCLUDED.ambiente,
                app_code_server    = EXCLUDED.app_code_server,
                app_key_server     = COALESCE(EXCLUDED.app_key_server, nuvei_config.app_key_server),
                app_code_linktopay = EXCLUDED.app_code_linktopay,
                app_key_linktopay  = COALESCE(EXCLUDED.app_key_linktopay, nuvei_config.app_key_linktopay),
                activo_checkout    = EXCLUDED.activo_checkout,
                activo_addcard     = EXCLUDED.activo_addcard,
                activo_linktopay   = EXCLUDED.activo_linktopay,
                updated_at         = CURRENT_TIMESTAMP,
                updated_by         = EXCLUDED.updated_by"
        );
        $st->execute([
            ':ie'     => $d['id_empresa'],
            ':amb'    => $d['ambiente'] ?? 'sandbox',
            ':cs'     => $d['app_code_server']    ?? null,
            ':ks'     => $keyServer,
            ':cl'     => $d['app_code_linktopay'] ?? null,
            ':kl'     => $keyLinkToPay,
            ':act_co' => !empty($d['activo_checkout'])  ? 'true' : 'false',
            ':act_ac' => !empty($d['activo_addcard'])   ? 'true' : 'false',
            ':act_lp' => !empty($d['activo_linktopay']) ? 'true' : 'false',
            ':cb'     => $d['id_usuario'] ?? null,
            ':ub'     => $d['id_usuario'] ?? null,
        ]);
    }

    // ─── TRANSACCIONES ────────────────────────────────────────────────────────

    public function crearTransaccion(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO nuvei_transacciones
                (id_empresa, tipo, dev_reference, modulo, id_referencia,
                 id_forma_cobro, id_cliente, id_nuvei_tarjeta, email,
                 descripcion, monto, moneda, url_exito, created_by)
             VALUES
                (:ie, :tipo, :devref, :mod, :ref,
                 :ifc, :icl, :itc, :email,
                 :desc, :monto, :moneda, :url_ok, :cb)
             RETURNING id"
        );
        $st->execute([
            ':ie'     => $d['id_empresa'],
            ':tipo'   => $d['tipo'],
            ':devref' => $d['dev_reference'],
            ':mod'    => $d['modulo']          ?? 'general',
            ':ref'    => $d['id_referencia']   ?? null,
            ':ifc'    => $d['id_forma_cobro']  ?? null,
            ':icl'    => $d['id_cliente']      ?? null,
            ':itc'    => $d['id_nuvei_tarjeta']?? null,
            ':email'  => $d['email']           ?? null,
            ':desc'   => $d['descripcion']     ?? null,
            ':monto'  => $d['monto'],
            ':moneda' => $d['moneda']          ?? 'USD',
            ':url_ok' => $d['url_exito']       ?? null,
            ':cb'     => $d['id_usuario']      ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarReference(string $devReference, string $reference): void
    {
        $st = $this->db->prepare(
            "UPDATE nuvei_transacciones
             SET reference = :ref, updated_at = CURRENT_TIMESTAMP
             WHERE dev_reference = :devref AND eliminado = false"
        );
        $st->execute([':ref' => $reference, ':devref' => $devReference]);
    }

    public function vincularIngreso(string $devReference, int $idIngreso): void
    {
        $st = $this->db->prepare(
            "UPDATE nuvei_transacciones
             SET id_ingreso = :iid, updated_at = CURRENT_TIMESTAMP
             WHERE dev_reference = :devref AND eliminado = false"
        );
        $st->execute([':iid' => $idIngreso, ':devref' => $devReference]);
    }

    /**
     * Busca la forma de cobro de tipo NUVEI de una empresa (para registrar
     * ingresos por pagos vía la pasarela Nuvei). Se valida por TIPO, no por
     * nombre, igual que PayphoneRepository::getFormaCobroPayphone().
     */
    public function getFormaCobroNuvei(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, tipo
             FROM empresa_formas_pago
             WHERE id_empresa = :ie
               AND eliminado  = false
               AND activo     = true
               AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')
               AND UPPER(tipo) = 'NUVEI'
             ORDER BY nombre ASC
             LIMIT 1"
        );
        $st->execute([':ie' => $idEmpresa]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function actualizarResultado(string $devReference, array $d): void
    {
        $st = $this->db->prepare(
            "UPDATE nuvei_transacciones SET
                nuvei_transaction_id = :ntid,
                status_detail        = :sd,
                estado               = :est,
                authorization_code   = :auth,
                response_data        = :rd::jsonb,
                updated_at           = CURRENT_TIMESTAMP,
                updated_by           = :ub
             WHERE dev_reference = :devref AND eliminado = false"
        );
        $st->execute([
            ':ntid'   => $d['nuvei_transaction_id'] ?? null,
            ':sd'     => $d['status_detail']        ?? null,
            ':est'    => $d['estado'],
            ':auth'   => $d['authorization_code']   ?? null,
            ':rd'     => json_encode($d['response_data'] ?? []),
            ':ub'     => $d['id_usuario']           ?? null,
            ':devref' => $devReference,
        ]);
    }

    public function getTransaccionByDevReference(string $devReference): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM nuvei_transacciones
             WHERE dev_reference = :devref AND eliminado = false
             LIMIT 1"
        );
        $st->execute([':devref' => $devReference]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getTransaccionByReference(string $reference): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM nuvei_transacciones
             WHERE reference = :ref AND eliminado = false
             LIMIT 1"
        );
        $st->execute([':ref' => $reference]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getTransaccionById(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM nuvei_transacciones
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
            "SELECT * FROM nuvei_transacciones
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

        $countSt = $this->db->prepare("SELECT COUNT(*) FROM nuvei_transacciones t $where");
        $countSt->execute($params);
        $total = (int) $countSt->fetchColumn();

        $offset            = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $st = $this->db->prepare(
            "SELECT t.*
             FROM nuvei_transacciones t
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

    // ─── WEBHOOK LOG ──────────────────────────────────────────────────────────

    public function logWebhook(array $payload, array $headers, ?string $devReference): int
    {
        $st = $this->db->prepare(
            "INSERT INTO nuvei_webhook_log (payload, headers, dev_reference)
             VALUES (:payload::jsonb, :headers::jsonb, :devref)
             RETURNING id"
        );
        $st->execute([
            ':payload' => json_encode($payload),
            ':headers' => json_encode($headers),
            ':devref'  => $devReference,
        ]);
        return (int) $st->fetchColumn();
    }

    public function marcarWebhookProcesado(int $idLog, bool $ok, ?string $error = null): void
    {
        $st = $this->db->prepare(
            "UPDATE nuvei_webhook_log SET procesado = :ok, error = :err WHERE id = :id"
        );
        $st->execute([':ok' => $ok ? 'true' : 'false', ':err' => $error, ':id' => $idLog]);
    }
}
