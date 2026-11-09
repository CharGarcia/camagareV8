<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM fase 3: app de LinkedIn de cada empresa (tabla crm_linkedin_apps).
 * El Client Secret va cifrado; se descifra solo en LinkedInApiService.
 */
class CrmLinkedinAppRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('crm_linkedin_apps');
    }

    public function disponible(): bool
    {
        return $this->tablaExiste('crm_linkedin_apps');
    }

    public function getPorEmpresa(int $idEmpresa): ?array
    {
        if (!$this->disponible()) {
            return null;
        }
        $st = $this->db->prepare('SELECT * FROM crm_linkedin_apps WHERE id_empresa = :e AND eliminado = false');
        $st->execute([':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function lockEmpresa(int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_linkedin_app:' || CAST(:e AS text)))");
        $st->execute([':e' => $idEmpresa]);
    }

    public function insertar(int $idEmpresa, string $clientId, string $secretCifrado, bool $paginas, int $idUsuario): int
    {
        $st = $this->db->prepare(
            'INSERT INTO crm_linkedin_apps (id_empresa, client_id, client_secret_cifrado, paginas_aprobadas, created_by, updated_by)
             VALUES (:e, :c, :s, :p, :u, :u2) RETURNING id'
        );
        $st->execute([':e' => $idEmpresa, ':c' => $clientId, ':s' => $secretCifrado, ':p' => $paginas ? 'true' : 'false', ':u' => $idUsuario, ':u2' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    /** $secretCifrado null = conservar el guardado. */
    public function actualizar(int $id, int $idEmpresa, string $clientId, ?string $secretCifrado, bool $paginas, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_linkedin_apps SET client_id = :c, client_secret_cifrado = COALESCE(:s, client_secret_cifrado),
                    paginas_aprobadas = :p, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':c' => $clientId, ':s' => $secretCifrado, ':p' => $paginas ? 'true' : 'false', ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_linkedin_apps SET eliminado = true, deleted_at = NOW(), deleted_by = :u, updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }
}
