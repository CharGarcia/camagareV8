<?php

namespace App\Repositories\Modulos;

use App\core\Database;
use PDO;

class ResponsableTrasladoRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function listarPorEmpresa($id_empresa) {
        $sql = "SELECT id, nombre, identificacion, email FROM responsables_traslado 
                WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo'
                ORDER BY nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_empresa' => $id_empresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
