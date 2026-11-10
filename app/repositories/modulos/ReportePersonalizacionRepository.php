<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Personalización de reportes por empresa (tabla `reportes_personalizacion`, ver
 * database/2026-11-10_reportes_personalizacion.sql): una fila por empresa, módulo y
 * vista con un JSON de columnas ocultas y orientación del PDF. El catálogo de reportes
 * y columnas es App\Helpers\ColumnasReporte.
 *
 * Mientras no se ejecute el SQL, la tabla no existe: las lecturas devuelven vacío (el
 * reporte usa sus valores por defecto, como antes) en vez de romper.
 */
class ReportePersonalizacionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('reportes_personalizacion');
    }

    /** ¿Ya se ejecutó el SQL que crea la tabla? (cacheado por BaseRepository). */
    public function disponible(): bool
    {
        return $this->tablaExiste($this->table);
    }

    /**
     * Configuraciones vigentes de todas las vistas del reporte: vista => fila.
     *
     * @return array<string, array<string,mixed>>
     */
    public function getPorModulo(int $idEmpresa, string $modulo): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE id_empresa = :id_empresa AND modulo = :modulo AND eliminado = false"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':modulo' => $modulo]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $out[(string) $fila['vista']] = $fila;
        }
        return $out;
    }

    /**
     * Serializa los guardados sobre el mismo reporte de una empresa (§8): dos
     * administradores guardando a la vez no crean dos filas para la misma vista.
     * Debe llamarse dentro de la transacción del llamador.
     */
    public function lock(int $idEmpresa, string $modulo): void
    {
        $st = $this->db->prepare(
            "SELECT pg_advisory_xact_lock(hashtext('reporte_personalizacion:' || :id_empresa || ':' || :modulo))"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':modulo' => $modulo]);
    }

    public function crear(int $idEmpresa, string $modulo, string $vista, string $configuracion, int $idActor): int
    {
        $st = $this->db->prepare(
            "INSERT INTO {$this->table} (id_empresa, modulo, vista, configuracion, created_at, created_by, eliminado)
             VALUES (:id_empresa, :modulo, :vista, :configuracion, NOW(), :created_by, false)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa'    => $idEmpresa,
            ':modulo'        => $modulo,
            ':vista'         => $vista,
            ':configuracion' => $configuracion,
            ':created_by'    => $idActor,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, int $idEmpresa, string $configuracion, int $idActor): bool
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table}
                SET configuracion = :configuracion, updated_at = NOW(), updated_by = :updated_by
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([
            ':configuracion' => $configuracion,
            ':updated_by'    => $idActor,
            ':id'            => $id,
            ':id_empresa'    => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    /** Eliminación lógica: la vista vuelve a sus valores por defecto. */
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
