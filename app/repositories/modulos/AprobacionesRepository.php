<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de configuración del motor de Aprobaciones.
 *
 * Dos tablas:
 *   aprobaciones_tipos   catálogo GLOBAL de checkpoints (lo define el desarrollo:
 *                        cada fila necesita código que la consulte antes de actuar).
 *   aprobaciones_config  una fila por (empresa, checkpoint) que la empresa
 *                        configuró. La fila existe = la aprobación está creada;
 *                        eliminado = true = la empresa la quitó del módulo.
 */
class AprobacionesRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('aprobaciones_tipos');
    }

    // ─── Catálogo de tipos ──────────────────────────────────────────────────────

    public function getTipos(bool $soloActivos = false): array
    {
        $sql = "SELECT * FROM aprobaciones_tipos" . ($soloActivos ? " WHERE activo = true" : "") . " ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTipoPorCodigo(string $codigo): ?array
    {
        $st = $this->db->prepare("SELECT * FROM aprobaciones_tipos WHERE codigo = :c LIMIT 1");
        $st->execute([':c' => $codigo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTipoPorId(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM aprobaciones_tipos WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Checkpoints del catálogo que la empresa TODAVÍA no configuró — es la
     * lista del select "Proceso" al crear una aprobación nueva.
     */
    public function getTiposDisponibles(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT t.id, t.codigo, t.nombre, t.descripcion, t.modulo_ruta
             FROM aprobaciones_tipos t
             WHERE t.activo = true
               AND NOT EXISTS (
                   SELECT 1 FROM aprobaciones_config c
                   WHERE c.id_tipo = t.id AND c.id_empresa = :e AND c.eliminado = false
               )
             ORDER BY t.modulo_ruta ASC, t.nombre ASC"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Configuración por empresa ──────────────────────────────────────────────

    /** Aprobaciones que la empresa configuró (el listado del módulo). */
    public function getConfiguradas(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT c.id AS id_config, c.id_tipo, c.requiere_aprobacion,
                    c.usuarios_aprobadores, c.umbral_monto,
                    c.created_at, c.updated_at,
                    t.codigo, t.nombre, t.descripcion, t.modulo_ruta
             FROM aprobaciones_config c
             INNER JOIN aprobaciones_tipos t ON t.id = c.id_tipo
             WHERE c.id_empresa = :e AND c.eliminado = false AND t.activo = true
             ORDER BY t.modulo_ruta ASC, t.nombre ASC"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConfigPorTipoId(int $idEmpresa, int $idTipo): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM aprobaciones_config
             WHERE id_empresa = :e AND id_tipo = :t AND eliminado = false LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':t' => $idTipo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crea o actualiza la config de un checkpoint para la empresa (UPSERT por el
     * UNIQUE(id_empresa,id_tipo)). Si la fila estaba eliminada, la revive: el
     * UNIQUE cubre también las eliminadas, así que volver a crear la aprobación
     * tiene que reutilizar esa misma fila.
     */
    public function upsertConfig(int $idEmpresa, int $idTipo, array $d, int $idUsuario): void
    {
        $sql = "INSERT INTO aprobaciones_config
                    (id_empresa, id_tipo, requiere_aprobacion, usuarios_aprobadores, umbral_monto,
                     eliminado, created_by, updated_by, created_at, updated_at)
                VALUES
                    (:e, :t, :req, :aprob, :umbral, false, :u_new, :u_upd, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT (id_empresa, id_tipo) DO UPDATE SET
                    requiere_aprobacion  = EXCLUDED.requiere_aprobacion,
                    usuarios_aprobadores = EXCLUDED.usuarios_aprobadores,
                    umbral_monto         = EXCLUDED.umbral_monto,
                    eliminado            = false,
                    deleted_at           = NULL,
                    deleted_by           = NULL,
                    updated_by           = EXCLUDED.updated_by,
                    updated_at           = CURRENT_TIMESTAMP";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'      => $idEmpresa,
            ':t'      => $idTipo,
            ':req'    => !empty($d['requiere_aprobacion']) ? 'true' : 'false',
            ':aprob'  => json_encode(array_values(array_map('intval', $d['usuarios_aprobadores'] ?? []))),
            ':umbral' => ($d['umbral_monto'] ?? '') !== '' ? (float) $d['umbral_monto'] : null,
            // PostgreSQL sin ATTR_EMULATE_PREPARES no admite reutilizar un
            // placeholder nombrado: created_by y updated_by van por separado.
            ':u_new'  => $idUsuario,
            ':u_upd'  => $idUsuario,
        ]);
    }

    /** Eliminación lógica: la empresa quita la aprobación del listado. */
    public function eliminarConfig(int $idEmpresa, int $idTipo, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE aprobaciones_config
                SET eliminado = true, requiere_aprobacion = false,
                    deleted_at = CURRENT_TIMESTAMP, deleted_by = :u_del,
                    updated_by = :u_upd, updated_at = CURRENT_TIMESTAMP
              WHERE id_empresa = :e AND id_tipo = :t AND eliminado = false"
        );
        $st->execute([':e' => $idEmpresa, ':t' => $idTipo, ':u_del' => $idUsuario, ':u_upd' => $idUsuario]);
        return $st->rowCount() > 0;
    }
}
