<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Pestañas de un módulo que un usuario NO ve en una empresa (tabla
 * `usuarios_pestanas_ocultas`, ver database/2026-10-06_usuarios_pestanas_ocultas.sql).
 * Se administran en /config/permisos-modulos. El catálogo de módulos y pestañas
 * configurables es App\Helpers\PestanasModulo.
 *
 * Mientras no se ejecute el SQL, la tabla no existe: las lecturas devuelven
 * vacío (todas las pestañas visibles, como antes) en vez de romper.
 */
class PestanasModuloRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('usuarios_pestanas_ocultas');
    }

    /** ¿Ya se ejecutó el SQL que crea la tabla? (cacheado por BaseRepository). */
    public function disponible(): bool
    {
        return $this->tablaExiste($this->table);
    }

    /**
     * Claves de las pestañas ocultas al usuario en ese módulo y empresa.
     *
     * @return string[]
     */
    public function getOcultas(int $idEmpresa, int $idUsuario, string $modulo): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT pestana FROM {$this->table}
              WHERE id_empresa = :id_empresa AND id_usuario = :id_usuario
                AND modulo = :modulo AND eliminado = false"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario, ':modulo' => $modulo]);
        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Fila vigente de ocultación de una pestaña, o null si está visible. */
    public function getOcultacion(int $idEmpresa, int $idUsuario, string $modulo, string $pestana): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE id_empresa = :id_empresa AND id_usuario = :id_usuario
                AND modulo = :modulo AND pestana = :pestana AND eliminado = false
              LIMIT 1"
        );
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_usuario' => $idUsuario,
            ':modulo'     => $modulo,
            ':pestana'    => $pestana,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Serializa los cambios sobre las pestañas de un mismo usuario, empresa y
     * módulo (§8): dos clics simultáneos sobre la misma pestaña no crean dos
     * filas. Debe llamarse dentro de la transacción del llamador.
     */
    public function lock(int $idEmpresa, int $idUsuario, string $modulo): void
    {
        $st = $this->db->prepare(
            "SELECT pg_advisory_xact_lock(hashtext('pestanas_ocultas:' || :id_empresa || ':' || :id_usuario || ':' || :modulo))"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario, ':modulo' => $modulo]);
    }

    public function crear(int $idEmpresa, int $idUsuario, string $modulo, string $pestana, int $idActor): int
    {
        $st = $this->db->prepare(
            "INSERT INTO {$this->table} (id_empresa, id_usuario, modulo, pestana, created_at, created_by, eliminado)
             VALUES (:id_empresa, :id_usuario, :modulo, :pestana, NOW(), :created_by, false)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_usuario' => $idUsuario,
            ':modulo'     => $modulo,
            ':pestana'    => $pestana,
            ':created_by' => $idActor,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Eliminación lógica de la ocultación (la pestaña vuelve a verse). */
    public function eliminar(int $id, int $idEmpresa, int $idActor): bool
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table}
                SET eliminado = true, deleted_at = NOW(), deleted_by = :deleted_by,
                    updated_at = NOW(), updated_by = :updated_by
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':deleted_by' => $idActor, ':updated_by' => $idActor, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }
}
