<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class TipoMedidaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('tipo_medida');
    }

    /**
     * Obtiene el listado completo para el select
     */
    public function getActivos(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, codigo 
                FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND status = true 
                  AND eliminado = false 
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca por nombre o código para autocompletado o sincronización inicial
     */
    public function findByName(int $idEmpresa, string $nombre): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND UPPER(nombre) = UPPER(:nombre) 
                  AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':nombre' => $nombre]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Verifica si existe un nombre, incluso si está eliminado.
     */
    public function existsByNameIncludingDeleted(int $idEmpresa, string $nombre): bool
    {
        $sql = "SELECT 1 FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND UPPER(nombre) = UPPER(:nombre) 
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':nombre' => $nombre]);
        return (bool) $st->fetchColumn();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, codigo, nombre, status, eliminado, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :codigo, :nombre, :status, false, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_usuario' => $data['id_usuario'],
            ':created_by' => $data['id_usuario'],
            ':codigo'     => $data['codigo'],
            ':nombre'     => $data['nombre'],
            ':status'     => isset($data['status']) ? ($data['status'] ? 'true' : 'false') : 'true'
        ]);
        return $this->lastInsertId();
    }
}
