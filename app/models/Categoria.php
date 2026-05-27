<?php
/**
 * Modelo Categoria
 * Tabla: categorias
 */

declare(strict_types=1);

namespace App\models;

class Categoria extends BaseModel
{
    /**
     * Retorna las categorías activas de una empresa (para selects).
     */
    public function getActivasPorEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query(
            "SELECT id, nombre
             FROM categorias
             WHERE id_empresa = {$id} AND status = 1 AND eliminado = false
             ORDER BY nombre ASC"
        );
    }
}
