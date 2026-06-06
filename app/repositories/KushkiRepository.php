<?php
declare(strict_types=1);

namespace App\repositories;

/**
 * KushkiRepository
 * Acceso a datos de kushki_config (configuración por empresa).
 */
class KushkiRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('kushki_config');
    }

    // ─── CONFIG ───────────────────────────────────────────────────────────────

    public function getConfig(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM kushki_config
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
            "INSERT INTO kushki_config
                (id_empresa, public_key, private_key, ambiente, moneda, activo, created_by, updated_by)
             VALUES
                (:ie, :pub, :priv, :amb, :mon, :activo, :cb, :ub)
             ON CONFLICT (id_empresa) DO UPDATE SET
                public_key  = EXCLUDED.public_key,
                private_key = EXCLUDED.private_key,
                ambiente    = EXCLUDED.ambiente,
                moneda      = EXCLUDED.moneda,
                activo      = EXCLUDED.activo,
                updated_at  = CURRENT_TIMESTAMP,
                updated_by  = EXCLUDED.updated_by"
        );
        $st->execute([
            ':ie'     => $d['id_empresa'],
            ':pub'    => $d['public_key'],
            ':priv'   => $d['private_key'],
            ':amb'    => $d['ambiente']    ?? 'uat',
            ':mon'    => $d['moneda']      ?? 'USD',
            ':activo' => $d['activo']      ? 'true' : 'false',
            ':cb'     => $d['id_usuario']  ?? null,
            ':ub'     => $d['id_usuario']  ?? null,
        ]);
    }
}
