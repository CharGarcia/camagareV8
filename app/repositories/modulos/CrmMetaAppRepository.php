<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: app de Meta de cada empresa (crm_meta_apps). Los secretos salen de aquí
 * cifrados; los descifra solo InstagramApiService / CrmMetaAppService.
 */
class CrmMetaAppRepository extends BaseRepository
{
    protected string $table = 'crm_meta_apps';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function disponible(): bool
    {
        return $this->tablaExiste('crm_meta_apps');
    }

    /** App vigente de la empresa (con secretos cifrados), o null. */
    public function getPorEmpresa(int $idEmpresa): ?array
    {
        if (!$this->disponible()) {
            return null;
        }
        $st = $this->db->prepare(
            'SELECT a.*, u.nombre AS actualizado_por FROM crm_meta_apps a
               LEFT JOIN usuarios u ON u.id = COALESCE(a.updated_by, a.created_by)
              WHERE a.id_empresa = :e AND a.eliminado = false'
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Candado por empresa para «crear o actualizar» su app (dos pestañas a la vez). */
    public function lockEmpresa(int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_meta_app:' || CAST(:e AS text)))");
        $st->execute([':e' => $idEmpresa]);
    }

    public function insertar(int $idEmpresa, string $appId, string $secretCif, string $tokenCif, string $tokenHash, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_meta_apps (id_empresa, app_id, app_secret_cifrado, verify_token_cifrado, verify_token_hash, created_by, updated_by)
             VALUES (:e, :a, :s, :t, :h, :u, :u2) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':a' => $appId, ':s' => $secretCif, ':t' => $tokenCif, ':h' => $tokenHash, ':u' => $idUsuario, ':u2' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    /** ¿Está aplicado el SQL de Unified login (crm_meta_apps.config_id)? */
    public function admiteConfigId(): bool
    {
        return $this->disponible() && $this->columnaExiste('crm_meta_apps', 'config_id');
    }

    /** ID de configuración de Meta (Unified login); '' = Instagram Login. */
    public function guardarConfigId(int $id, int $idEmpresa, string $configId): void
    {
        $st = $this->db->prepare('UPDATE crm_meta_apps SET config_id = :c WHERE id = :id AND id_empresa = :e AND eliminado = false');
        $st->execute([':c' => $configId !== '' ? $configId : null, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** $secretCif / $tokenCif null = se conserva el que había. */
    public function actualizar(int $id, int $idEmpresa, string $appId, ?string $secretCif, ?string $tokenCif, ?string $tokenHash, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_meta_apps SET app_id = :a,
                    app_secret_cifrado = COALESCE(:s, app_secret_cifrado),
                    verify_token_cifrado = COALESCE(:t, verify_token_cifrado),
                    verify_token_hash = COALESCE(:h, verify_token_hash),
                    updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':a' => $appId, ':s' => $secretCif, ':t' => $tokenCif, ':h' => $tokenHash, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_meta_apps SET eliminado = true, deleted_at = NOW(), deleted_by = :u, updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /**
     * Verificación GET del webhook: empresas (activas) cuyo token coincide.
     * Se busca por el hash; el llamador compara el token descifrado en tiempo constante.
     */
    public function buscarPorVerifyHash(string $hash): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT a.id_empresa, a.verify_token_cifrado FROM crm_meta_apps a
               JOIN empresas e ON e.id = a.id_empresa AND e.estado = '1' AND e.eliminado = false
              WHERE a.verify_token_hash = :h AND a.eliminado = false AND a.estado = 'activo'"
        );
        $st->execute([':h' => $hash]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Apps (activas, de empresas activas) de un grupo de empresas: id_empresa, app_id, secreto cifrado. */
    public function porEmpresas(array $idsEmpresa): array
    {
        $idsEmpresa = array_values(array_unique(array_filter(array_map('intval', $idsEmpresa))));
        if (!$idsEmpresa || !$this->disponible()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT a.id_empresa, a.app_id, a.app_secret_cifrado FROM crm_meta_apps a
               JOIN empresas e ON e.id = a.id_empresa AND e.estado = '1' AND e.eliminado = false
              WHERE a.id_empresa = ANY (CAST(:ids AS int[])) AND a.eliminado = false AND a.estado = 'activo'"
        );
        $st->execute([':ids' => '{' . implode(',', $idsEmpresa) . '}']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Todas las apps activas (respaldo cuando el evento no trae una cuenta conocida). */
    public function todasActivas(): array
    {
        if (!$this->disponible()) {
            return [];
        }
        return $this->db->query(
            "SELECT a.id_empresa, a.app_id, a.app_secret_cifrado FROM crm_meta_apps a
               JOIN empresas e ON e.id = a.id_empresa AND e.estado = '1' AND e.eliminado = false
              WHERE a.eliminado = false AND a.estado = 'activo'"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Empresas que usan esa app (una misma app puede servir a varias empresas del mismo dueño). */
    public function empresasDeApp(string $appId): array
    {
        $st = $this->db->prepare("SELECT id_empresa FROM crm_meta_apps WHERE app_id = :a AND eliminado = false AND estado = 'activo'");
        $st->execute([':a' => $appId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
