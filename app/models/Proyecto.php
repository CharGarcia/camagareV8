<?php

declare(strict_types=1);

namespace App\models;

class Proyecto extends BaseModel
{
    /**
     * Retorna los proyectos activos de una empresa.
     */
    public function getActivosPorEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query(
            "SELECT id, nombre, codigo
             FROM proyectos
             WHERE id_empresa = {$id} AND estado = 'activo' AND eliminado = false
             ORDER BY nombre ASC"
        );
    }
}
