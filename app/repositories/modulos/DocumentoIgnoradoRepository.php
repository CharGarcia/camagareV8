<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use PDO;

class DocumentoIgnoradoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getListado(int $idEmpresa): array
    {
        $sql = "SELECT id, clave_acceso, nombre_proveedor, fecha_documento, observaciones, created_at 
                FROM documentos_ignorados_sri 
                WHERE id_empresa = :ie AND eliminado = false 
                ORDER BY created_at DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa]);
        return $st->fetchAll();
    }

    public function insertar(array $data): bool
    {
        $sql = "INSERT INTO documentos_ignorados_sri (id_empresa, clave_acceso, nombre_proveedor, fecha_documento, observaciones, created_by) 
                VALUES (:ie, :ca, :np, :fd, :obs, :cb)";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':ie'  => $data['id_empresa'],
            ':ca'  => $data['clave_acceso'],
            ':np'  => $data['nombre_proveedor'] ?? null,
            ':fd'  => $data['fecha_documento']  ?? null,
            ':obs' => $data['observaciones']    ?? null,
            ':cb'  => $data['id_usuario']
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE documentos_ignorados_sri SET 
                    eliminado = true, 
                    deleted_at = CURRENT_TIMESTAMP, 
                    deleted_by = :du 
                WHERE id = :id AND id_empresa = :ie";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id' => $id,
            ':ie' => $idEmpresa,
            ':du' => $idUsuario
        ]);
    }

    public function existeClave(string $clave, int $idEmpresa): bool
    {
        $sql = "SELECT 1 FROM documentos_ignorados_sri 
                WHERE clave_acceso = :ca AND id_empresa = :ie AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':ca' => $clave, ':ie' => $idEmpresa]);
        return (bool) $st->fetch();
    }
}
