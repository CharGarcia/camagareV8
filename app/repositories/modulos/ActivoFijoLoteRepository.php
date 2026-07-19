<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

class ActivoFijoLoteRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('activos_fijos_lotes');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function existsLote(int $idEmpresa, int $anio, int $mes): bool
    {
        return (bool) $this->query(
            "SELECT 1 FROM activos_fijos_lotes
             WHERE id_empresa = ? AND periodo_anio = ? AND periodo_mes = ? AND eliminado = false AND estado = 'contabilizado'
             LIMIT 1",
            [$idEmpresa, $anio, $mes]
        )->fetchColumn();
    }

    public function insertLote(array $data): int
    {
        $sql = "INSERT INTO activos_fijos_lotes (
                    id_empresa, periodo_anio, periodo_mes, cantidad_activos, total_depreciado,
                    estado, observaciones, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";

        return (int) $this->query($sql, [
            (int) $data['id_empresa'],
            (int) $data['periodo_anio'],
            (int) $data['periodo_mes'],
            (int) ($data['cantidad_activos'] ?? 0),
            (float) ($data['total_depreciado'] ?? 0),
            $data['estado'] ?? 'contabilizado',
            $data['observaciones'] ?? null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function updateLote(int $id, array $data): void
    {
        $sql = "UPDATE activos_fijos_lotes SET
                    cantidad_activos = ?, total_depreciado = ?, estado = ?, id_asiento_contable = ?,
                    updated_by = ?, updated_at = NOW()
                WHERE id = ?";

        $this->query($sql, [
            (int) $data['cantidad_activos'],
            (float) $data['total_depreciado'],
            $data['estado'],
            !empty($data['id_asiento_contable']) ? (int) $data['id_asiento_contable'] : null,
            (int) $data['id_usuario'],
            $id,
        ]);
    }

    public function getListado(int $idEmpresa): array
    {
        return $this->query(
            "SELECT * FROM activos_fijos_lotes WHERE id_empresa = ? AND eliminado = false ORDER BY periodo_anio DESC, periodo_mes DESC",
            [$idEmpresa]
        )->fetchAll();
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $row = $this->query(
            "SELECT * FROM activos_fijos_lotes WHERE id = ? AND id_empresa = ? AND eliminado = false",
            [$id, $idEmpresa]
        )->fetch();
        return $row ?: null;
    }
}
