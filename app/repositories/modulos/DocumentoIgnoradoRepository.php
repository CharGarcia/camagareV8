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

    /** Igual que getListado() pero para todo el grupo de empresas que comparten RUC. */
    public function getListadoGrupo(array $idsEmpresa): array
    {
        if (empty($idsEmpresa)) return [];
        $placeholders = implode(',', array_fill(0, count($idsEmpresa), '?'));
        $sql = "SELECT id, clave_acceso, nombre_proveedor, fecha_documento, observaciones, created_at
                FROM documentos_ignorados_sri
                WHERE id_empresa IN ($placeholders) AND eliminado = false
                ORDER BY created_at DESC";
        $st = $this->db->prepare($sql);
        $st->execute($idsEmpresa);
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

    /**
     * Igual que eliminar() pero permite borrar una fila que pertenece a cualquier
     * empresa del grupo RUC (no solo al establecimiento activo) — necesario porque
     * el listado ahora se muestra a nivel de grupo.
     */
    public function eliminarEnGrupo(int $id, array $idsEmpresa, int $idUsuario): bool
    {
        if (empty($idsEmpresa)) return false;
        $placeholders = implode(',', array_fill(0, count($idsEmpresa), '?'));
        $sql = "UPDATE documentos_ignorados_sri SET
                    eliminado = true,
                    deleted_at = CURRENT_TIMESTAMP,
                    deleted_by = ?
                WHERE id = ? AND id_empresa IN ($placeholders)";
        $st = $this->db->prepare($sql);
        return $st->execute(array_merge([$idUsuario, $id], $idsEmpresa));
    }

    public function existeClave(string $clave, int $idEmpresa): bool
    {
        $sql = "SELECT 1 FROM documentos_ignorados_sri
                WHERE clave_acceso = :ca AND id_empresa = :ie AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':ca' => $clave, ':ie' => $idEmpresa]);
        return (bool) $st->fetch();
    }

    /**
     * Igual que existeClave() pero comprueba contra un grupo de empresas (las que
     * comparten RUC): ignorar un documento en un establecimiento debe ignorarlo
     * también en sus establecimientos hermanos del mismo RUC.
     */
    public function existeClaveEnGrupo(string $clave, array $idsEmpresa): bool
    {
        if (empty($idsEmpresa)) return false;
        $placeholders = implode(',', array_fill(0, count($idsEmpresa), '?'));
        $sql = "SELECT 1 FROM documentos_ignorados_sri
                WHERE clave_acceso = ? AND id_empresa IN ($placeholders) AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([$clave], $idsEmpresa));
        return (bool) $st->fetch();
    }
}
