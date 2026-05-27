<?php
/**
 * Modelo Vendedor
 * Tabla: vendedores
 * Campos: id, identificacion, nombre, correo, id_empresa, id_usuario, telefono, direccion, status
 */

declare(strict_types=1);

namespace App\models;

class Vendedor extends BaseModel
{
    /**
     * Retorna los vendedores activos de una empresa, ordenados por nombre.
     */
    public function getActivosPorEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query(
            "SELECT id, nombre, identificacion, correo
             FROM vendedores
             WHERE id_empresa = {$id} AND status = 1 AND eliminado = false
             ORDER BY nombre ASC"
        );
    }
}
