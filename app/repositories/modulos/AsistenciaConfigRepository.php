<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Configuración por empresa del módulo Control de Asistencia
 * (por ahora: cómo se tratan los atrasos al generar Novedades).
 */
class AsistenciaConfigRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('asistencia_config');
    }

    public function getByEmpresa(int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id_empresa = :e AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsert(int $idEmpresa, string $atrasoModo, int $idUsuario): void
    {
        $existente = $this->getByEmpresa($idEmpresa);
        if ($existente) {
            $sql = "UPDATE {$this->table} SET atraso_modo = :m, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND id_empresa = :e AND eliminado = false";
            $st = $this->db->prepare($sql);
            $st->execute([':m' => $atrasoModo, ':u' => $idUsuario, ':id' => $existente['id'], ':e' => $idEmpresa]);
            return;
        }
        $sql = "INSERT INTO {$this->table} (id_empresa, atraso_modo, created_by, updated_by, created_at, updated_at, eliminado)
                VALUES (:e, :m, :u, :u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':m' => $atrasoModo, ':u' => $idUsuario]);
    }
}
