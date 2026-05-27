<?php

declare(strict_types=1);

namespace App\models;

class PlanCuenta extends BaseModel
{
    /**
     * Retorna todas las cuentas de una empresa que no han sido eliminadas.
     */
    public function getTodasPorEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query(
            "SELECT * FROM plan_cuentas 
             WHERE id_empresa = {$id} AND eliminado = false 
             ORDER BY codigo ASC"
        );
    }
}
