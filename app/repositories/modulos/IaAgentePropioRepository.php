<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Prompts de agentes PROPIOS de cada empresa (ia_agentes.id_empresa NOT NULL),
 * a diferencia de las plantillas base globales (id_empresa IS NULL) que
 * administra App\models\IaAgente / IaAgentesController (solo superadmin).
 *
 * Un administrador (nivel 2) o superadmin puede crear/editar/eliminar los
 * prompts de SU empresa, partiendo o no de las plantillas base; nunca puede
 * tocar las plantillas globales ni los prompts de otra empresa.
 */
class IaAgentePropioRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ia_agentes');
    }

    /**
     * Agentes disponibles para una empresa: las plantillas globales (id_empresa
     * NULL) + los prompts propios de la empresa. Incluye 'editable' para que
     * la vista sepa cuáles puede modificar (solo los propios).
     */
    public function getDisponibles(int $idEmpresa, bool $soloActivos = true): array
    {
        $sql = "SELECT *, (id_empresa IS NOT NULL) AS editable
                FROM {$this->table}
                WHERE eliminado = false
                  AND (id_empresa IS NULL OR id_empresa = :id_empresa)"
              . ($soloActivos ? " AND activo = true" : "")
              . " ORDER BY (id_empresa IS NOT NULL) ASC, orden ASC, id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica que un agente sea accesible para la empresa: global o propio.
     */
    public function esAccesible(int $idAgente, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM {$this->table}
             WHERE id = :id AND eliminado = false AND activo = true
               AND (id_empresa IS NULL OR id_empresa = :id_empresa)"
        );
        $st->execute([':id' => $idAgente, ':id_empresa' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /** Un prompt propio de la empresa (nunca uno global ni de otra empresa). */
    public function findPropio(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(int $idEmpresa, array $data, int $idUsuario): int
    {
        $sql = "INSERT INTO {$this->table} (nombre, descripcion, icono, prompt_sistema, orden, activo, id_empresa, created_by, updated_by)
                VALUES (:nombre, :descripcion, :icono, :prompt_sistema, :orden, :activo, :id_empresa, :id_usuario, :id_usuario)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':nombre'         => $data['nombre'],
            ':descripcion'    => $data['descripcion'] ?? null,
            ':icono'          => $data['icono'] ?: 'bi-robot',
            ':prompt_sistema' => $data['prompt_sistema'],
            ':orden'          => $data['orden'] ?? 0,
            ':activo'         => ($data['activo'] ?? true) ? 'true' : 'false',
            ':id_empresa'     => $idEmpresa,
            ':id_usuario'     => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, int $idEmpresa, array $data, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    icono = :icono,
                    prompt_sistema = :prompt_sistema,
                    orden = :orden,
                    activo = :activo,
                    updated_by = :id_usuario,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'         => $data['nombre'],
            ':descripcion'    => $data['descripcion'] ?? null,
            ':icono'          => $data['icono'] ?: 'bi-robot',
            ':prompt_sistema' => $data['prompt_sistema'],
            ':orden'          => $data['orden'] ?? 0,
            ':activo'         => ($data['activo'] ?? true) ? 'true' : 'false',
            ':id_usuario'     => $idUsuario,
            ':id'             => $id,
            ':id_empresa'     => $idEmpresa,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_usuario, deleted_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario]);
    }
}
