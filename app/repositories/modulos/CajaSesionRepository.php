<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CajaSesionRepository extends BaseRepository
{
    protected string $table = 'caja_sesiones';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAbiertaPorPuntoEmision(int $idEmpresa, int $idPuntoEmision): ?array
    {
        $sql = "SELECT cs.*, u.nombre AS cajero_nombre
                FROM {$this->table} cs
                LEFT JOIN usuarios u ON u.id = cs.id_usuario
                WHERE cs.id_empresa = :id_empresa
                  AND cs.id_punto_emision = :id_punto_emision
                  AND cs.estado = 'abierta'
                  AND cs.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_punto_emision' => $idPuntoEmision,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_punto_emision, id_usuario, fondo_inicial,
                    estado, created_by, eliminado
                ) VALUES (
                    :id_empresa, :id_punto_emision, :id_usuario, :fondo_inicial,
                    'abierta', :created_by, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_punto_emision' => $data['id_punto_emision'],
            ':id_usuario' => $data['id_usuario'],
            ':fondo_inicial' => $data['fondo_inicial'],
            ':created_by' => $data['created_by'],
        ]);
        return (int) $this->lastInsertId();
    }

    public function cerrar(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    estado = 'cerrada',
                    fecha_cierre = CURRENT_TIMESTAMP,
                    monto_esperado = :monto_esperado,
                    monto_contado = :monto_contado,
                    diferencia = :diferencia,
                    observaciones_cierre = :observaciones_cierre,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false AND estado = 'abierta'";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':monto_esperado' => $data['monto_esperado'],
            ':monto_contado' => $data['monto_contado'],
            ':diferencia' => $data['diferencia'],
            ':observaciones_cierre' => $data['observaciones_cierre'],
            ':updated_by' => $data['updated_by'],
            ':id' => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }
}
