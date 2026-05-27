<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class UnidadMedidaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('unidades_medida');
    }

    /**
     * Obtiene las unidades de medida filtradas por el tipo seleccionado
     */
    public function getPorTipo(int $idEmpresa, int $idTipo): array
    {
        $sql = "SELECT id, nombre, abreviatura, codigo, factor_base
                FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND id_tipo = :id_tipo 
                  AND status = true 
                  AND eliminado = false 
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_tipo' => $idTipo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_tipo, created_by, codigo, nombre, abreviatura, 
                    factor_base, status, eliminado, created_at
                ) VALUES (
                    :id_empresa, :id_tipo, :created_by, :codigo, :nombre, :abreviatura, 
                    :factor_base, :status, false, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'  => $data['id_empresa'],
            ':id_tipo'     => $data['id_tipo'],
            ':created_by'  => $data['id_usuario'],
            ':codigo'      => $data['codigo'],
            ':nombre'      => $data['nombre'],
            ':abreviatura' => $data['abreviatura'],
            ':factor_base' => $data['factor_base'] ?? 1,
            ':status'      => isset($data['status']) ? ($data['status'] ? 'true' : 'false') : 'true'
        ]);
        return $this->lastInsertId();
    }
}
