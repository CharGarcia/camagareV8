<?php
/**
 * Repository: vínculo usuario (login) <-> responsable de traslado (usuarios_responsables_traslado).
 * Determina, para un usuario de la app móvil, a qué responsables de traslado
 * representa — usado para filtrar "mis entregas asignadas" en el módulo Entregas.
 */

declare(strict_types=1);

namespace App\repositories;

use PDO;

class ApiUsuarioResponsableTrasladoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \App\core\Database::getConnection();
    }

    /** @return int[] ids de responsables_traslado vinculados a este usuario en esta empresa. */
    public function getIdsResponsablesDeUsuario(int $idUsuario, int $idEmpresa): array
    {
        $sql = "SELECT id_responsable_traslado FROM usuarios_responsables_traslado
                WHERE id_usuario = :u AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':u' => $idUsuario, ':e' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Vínculos del usuario en esta empresa, con el nombre del responsable (para la pestaña de administración). */
    public function listarPorUsuarioYEmpresa(int $idUsuario, int $idEmpresa): array
    {
        $sql = "SELECT ur.id, ur.id_responsable_traslado, rt.nombre, rt.identificacion
                FROM usuarios_responsables_traslado ur
                INNER JOIN responsables_traslado rt ON rt.id = ur.id_responsable_traslado
                WHERE ur.id_usuario = :u AND ur.id_empresa = :e AND ur.eliminado = false
                ORDER BY rt.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':u' => $idUsuario, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vista INVERSA: usuarios vinculados a un responsable en esta empresa.
     * La usa el módulo Responsables de Traslado (pestaña "Usuarios vinculados");
     * listarPorUsuarioYEmpresa() es la misma relación vista desde el usuario.
     */
    public function listarUsuariosDeResponsable(int $idResponsable, int $idEmpresa): array
    {
        $sql = "SELECT ur.id, ur.id_usuario, u.nombre, u.mail, u.cedula,
                       COALESCE(u.nivel, 1) AS nivel,
                       COALESCE(u.puede_app_movil, false) AS puede_app_movil,
                       COALESCE(u.estado, 0) AS estado
                  FROM usuarios_responsables_traslado ur
                  INNER JOIN usuarios u ON u.id = ur.id_usuario
                 WHERE ur.id_responsable_traslado = :r AND ur.id_empresa = :e AND ur.eliminado = false
                 ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':r' => $idResponsable, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Usuarios que PODRÍAN vincularse a este responsable: los que tienen la
     * empresa asignada y todavía no están vinculados a él.
     *
     * Se excluye el nivel 3 (superadministrador): ya ve todas las entregas de
     * cualquier empresa, así que un vínculo suyo no cambiaría nada y solo
     * ensuciaría la lista.
     */
    public function usuariosDisponiblesParaResponsable(int $idResponsable, int $idEmpresa): array
    {
        $sql = "SELECT u.id AS id_usuario, u.nombre, u.mail, u.cedula,
                       COALESCE(u.nivel, 1) AS nivel,
                       COALESCE(u.puede_app_movil, false) AS puede_app_movil
                  FROM empresa_asignada ea
                  INNER JOIN usuarios u ON u.id = ea.id_usuario
                 WHERE ea.id_empresa = :e
                   AND COALESCE(u.eliminado, false) = false
                   AND COALESCE(u.nivel, 1) < 3
                   AND NOT EXISTS (
                        SELECT 1 FROM usuarios_responsables_traslado ur
                         WHERE ur.id_usuario = u.id
                           AND ur.id_empresa = :e
                           AND ur.id_responsable_traslado = :r
                           AND ur.eliminado = false
                   )
                 ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':r' => $idResponsable, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM usuarios_responsables_traslado WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crea el vínculo, o reactiva uno previamente desvinculado (mismo trío único).
     * @return array{id:int, creado:bool}
     */
    public function vincular(int $idEmpresa, int $idUsuario, int $idResponsable, int $idCreador): array
    {
        $sqlExistente = "SELECT id, eliminado FROM usuarios_responsables_traslado
                          WHERE id_empresa = :e AND id_usuario = :u AND id_responsable_traslado = :r";
        $stE = $this->db->prepare($sqlExistente);
        $stE->execute([':e' => $idEmpresa, ':u' => $idUsuario, ':r' => $idResponsable]);
        $existente = $stE->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            if (!$existente['eliminado']) {
                return ['id' => (int) $existente['id'], 'creado' => false];
            }
            $sqlReactivar = "UPDATE usuarios_responsables_traslado
                              SET eliminado = false, deleted_at = NULL, deleted_by = NULL,
                                  updated_at = CURRENT_TIMESTAMP, updated_by = :c
                              WHERE id = :id";
            $this->db->prepare($sqlReactivar)->execute([':c' => $idCreador, ':id' => $existente['id']]);
            return ['id' => (int) $existente['id'], 'creado' => true];
        }

        $sql = "INSERT INTO usuarios_responsables_traslado
                    (id_empresa, id_usuario, id_responsable_traslado, created_by, updated_by)
                VALUES (:e, :u, :r, :c, :c)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':u' => $idUsuario, ':r' => $idResponsable, ':c' => $idCreador]);
        return ['id' => (int) $st->fetchColumn(), 'creado' => true];
    }

    public function desvincular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE usuarios_responsables_traslado
                   SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }
}
