<?php

declare(strict_types=1);

namespace App\models;

class CentroCosto extends BaseModel
{
    /**
     * Retorna los centros de costo activos de una empresa.
     */
    public function getActivosPorEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query(
            "SELECT id, nombre, codigo
             FROM centro_costos
             WHERE id_empresa = {$id} AND estado = 'activo' AND eliminado = false
             ORDER BY nombre ASC"
        );
    }
}
