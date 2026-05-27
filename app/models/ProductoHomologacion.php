<?php

declare(strict_types=1);

namespace App\models;

class ProductoHomologacion extends BaseModel
{
    public function getVinculacion(int $idEmpresa, int $idProveedor, string $codigoProveedor): ?int
    {
        try {
            $db = \App\core\Database::getConnection();
            $codigo = trim($codigoProveedor);
            if ($codigo === '') return null;

            $sql = "SELECT id_producto FROM productos_homologacion 
                    WHERE id_empresa = ? AND id_proveedor = ? 
                      AND TRIM(codigo_proveedor) ILIKE ? AND eliminado = false 
                    LIMIT 1";
            
            $st = $db->prepare($sql);
            $st->execute([$idEmpresa, $idProveedor, $codigo]);
            $res = $st->fetch();

            return isset($res['id_producto']) ? (int) $res['id_producto'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function guardarVinculacion(int $idEmpresa, int $idProveedor, string $codigoProveedor, int $idProducto, int $idUsuario, string $descripcion = ''): bool
    {
        try {
            $db = \App\core\Database::getConnection();
            $codigo = trim($codigoProveedor);
            if ($codigo === '' || $idProducto <= 0) return false;

            $sql = "INSERT INTO productos_homologacion (id_empresa, id_proveedor, codigo_proveedor, id_producto, created_by, eliminado)
                    VALUES (?, ?, ?, ?, ?, false)
                    ON CONFLICT (id_empresa, id_proveedor, codigo_proveedor) 
                    DO UPDATE SET id_producto = EXCLUDED.id_producto, eliminado = false";
            
            $st = $db->prepare($sql);
            return $st->execute([(int)$idEmpresa, (int)$idProveedor, $codigo, (int)$idProducto, (int)$idUsuario]);
        } catch (\Exception $e) {
            return false;
        }
    }
}
