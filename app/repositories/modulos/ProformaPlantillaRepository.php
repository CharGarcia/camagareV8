<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

class ProformaPlantillaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('proformas_plantillas');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(int $idEmpresa): array
    {
        $sql = "SELECT pl.*,
                       (SELECT COUNT(*) FROM proformas_plantillas_detalle d WHERE d.id_plantilla = pl.id) AS total_items
                FROM proformas_plantillas pl
                WHERE pl.id_empresa = ? AND pl.eliminado = FALSE
                ORDER BY pl.nombre ASC";
        return $this->query($sql, [$idEmpresa])->fetchAll();
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM proformas_plantillas WHERE id = ? AND id_empresa = ? AND eliminado = FALSE";
        $row = $this->query($sql, [$id, $idEmpresa])->fetch();
        return $row ?: null;
    }

    public function getDetalles(int $idPlantilla): array
    {
        $sql = "SELECT d.*, COALESCE(p.nombre, d.descripcion) AS producto_nombre, p.codigo AS producto_codigo,
                       p.imagen AS producto_imagen
                FROM proformas_plantillas_detalle d
                LEFT JOIN productos p ON d.id_producto = p.id
                WHERE d.id_plantilla = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idPlantilla])->fetchAll();
    }

    public function getInfoAdicional(int $idPlantilla): array
    {
        $sql = "SELECT * FROM proformas_plantillas_adicional WHERE id_plantilla = ?";
        return $this->query($sql, [$idPlantilla])->fetchAll();
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO proformas_plantillas (id_empresa, nombre, dias_vigencia, vigencia_unidad, created_by, updated_by)
                VALUES (?,?,?,?,?,?) RETURNING id";
        return (int) $this->query($sql, [
            (int) $data['id_empresa'],
            $data['nombre'],
            (int) ($data['dias_vigencia'] ?? 15),
            $data['vigencia_unidad'] ?? 'dias',
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE proformas_plantillas
                SET nombre = ?, dias_vigencia = ?, vigencia_unidad = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?";
        $this->query($sql, [
            $data['nombre'],
            (int) ($data['dias_vigencia'] ?? 15),
            $data['vigencia_unidad'] ?? 'dias',
            (int) $data['id_usuario'],
            $id,
        ]);
    }

    public function deleteDetalles(int $idPlantilla): void
    {
        $this->query("DELETE FROM proformas_plantillas_detalle WHERE id_plantilla = ?", [$idPlantilla]);
    }

    public function deleteInfoAdicional(int $idPlantilla): void
    {
        $this->query("DELETE FROM proformas_plantillas_adicional WHERE id_plantilla = ?", [$idPlantilla]);
    }

    public function insertDetalle(array $data): void
    {
        $sql = "INSERT INTO proformas_plantillas_detalle (
                    id_plantilla, id_producto, id_unidad_medida,
                    codigo_principal, codigo_auxiliar, descripcion,
                    cantidad, precio_unitario, descuento, precio_total_sin_impuesto, id_tarifa_iva, info_adicional
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $this->query($sql, [
            (int) $data['id_plantilla'],
            !empty($data['id_producto']) ? (int) $data['id_producto'] : null,
            !empty($data['id_unidad_medida']) ? (int) $data['id_unidad_medida'] : null,
            $data['codigo_principal'] ?? '',
            !empty($data['codigo_auxiliar']) ? $data['codigo_auxiliar'] : null,
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'],
            $data['precio_total_sin_impuesto'],
            (int) ($data['id_tarifa_iva'] ?? 0),
            !empty($data['adicional']) ? (string) $data['adicional'] : null,
        ]);
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO proformas_plantillas_adicional (id_plantilla, nombre, valor) VALUES (?,?,?)";
        $this->query($sql, [$data['id_plantilla'], $data['nombre'], $data['valor']]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE proformas_plantillas
                SET eliminado = true, deleted_at = NOW(), deleted_by = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->query($sql, [$idUsuario, $idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }
}
