<?php

declare(strict_types=1);

namespace App\models;

class Cliente extends BaseModel
{
    /**
     * Retorna los clientes activos de una empresa.
     */
    public function getActivosPorEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query(
            "SELECT id, nombre, identificacion
             FROM clientes
             WHERE id_empresa = {$id} AND status = 1 AND eliminado = false
             ORDER BY nombre ASC"
        );
    }
}
