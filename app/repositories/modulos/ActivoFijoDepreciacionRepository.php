<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

class ActivoFijoDepreciacionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('activos_fijos_depreciaciones');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO activos_fijos_depreciaciones (
                    id_empresa, id_activo, id_lote, periodo_anio, periodo_mes,
                    valor_depreciado, depreciacion_acumulada_after, valor_libros_after, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";

        return (int) $this->query($sql, [
            (int) $data['id_empresa'],
            (int) $data['id_activo'],
            (int) $data['id_lote'],
            (int) $data['periodo_anio'],
            (int) $data['periodo_mes'],
            (float) $data['valor_depreciado'],
            (float) $data['depreciacion_acumulada_after'],
            (float) $data['valor_libros_after'],
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function getPorLote(int $idLote): array
    {
        return $this->query(
            "SELECT d.*, a.nombre AS activo_nombre, a.codigo AS activo_codigo, a.id_categoria,
                    cat.nombre AS categoria_nombre
             FROM activos_fijos_depreciaciones d
             INNER JOIN activos_fijos a ON d.id_activo = a.id
             INNER JOIN activos_fijos_categorias cat ON a.id_categoria = cat.id
             WHERE d.id_lote = ?
             ORDER BY cat.nombre ASC, a.nombre ASC",
            [$idLote]
        )->fetchAll();
    }

    public function getHistorialActivo(int $idActivo): array
    {
        return $this->query(
            "SELECT * FROM activos_fijos_depreciaciones WHERE id_activo = ? AND eliminado = false ORDER BY periodo_anio ASC, periodo_mes ASC",
            [$idActivo]
        )->fetchAll();
    }
}
