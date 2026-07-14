<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class IaConversacionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ia_conversaciones');
    }

    public function getListado(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT c.*, a.nombre AS nombre_agente, a.icono AS icono_agente
                FROM {$this->table} c
                INNER JOIN ia_agentes a ON a.id = c.id_agente
                $where
                ORDER BY c.updated_at DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $idEmpresa, int $idAgente, string $titulo, int $idUsuario): int
    {
        $sql = "INSERT INTO {$this->table} (id_empresa, id_agente, titulo, created_by, updated_by, created_at, updated_at, eliminado)
                VALUES (:id_empresa, :id_agente, :titulo, :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_agente'  => $idAgente,
            ':titulo'     => $titulo,
            ':id_usuario' => $idUsuario,
        ]);
        return (int) $this->db->lastInsertId('ia_conversaciones_id_seq');
    }

    public function actualizarTitulo(int $id, int $idEmpresa, string $titulo): bool
    {
        $sql = "UPDATE {$this->table} SET titulo = :titulo, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':titulo' => $titulo, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function tocar(int $id, int $idEmpresa): void
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table} SET updated_at = CURRENT_TIMESTAMP WHERE id = :id AND id_empresa = :id_empresa"
        );
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = true, deleted_by = :id_usuario, deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario]);
    }
}
