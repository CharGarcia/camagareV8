<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de configuración del motor de Aprobaciones.
 * Dos tablas: aprobaciones_tipos (catálogo global de checkpoints) y
 * aprobaciones_config (por empresa: qué tipo exige aprobación y quién aprueba).
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

    // ─── Configuración por empresa ──────────────────────────────────────────────

    /** Catálogo de tipos + su config en la empresa (LEFT JOIN: si no hay fila de config, valores por defecto). */
    public function getConfigEmpresa(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT t.id AS id_tipo, t.codigo, t.nombre, t.descripcion, t.modulo_ruta,
                    c.id AS id_config,
                    COALESCE(c.requiere_aprobacion, false) AS requiere_aprobacion,
                    COALESCE(c.usuarios_aprobadores, '[]'::jsonb) AS usuarios_aprobadores,
                    c.umbral_monto
             FROM aprobaciones_tipos t
             LEFT JOIN aprobaciones_config c ON c.id_tipo = t.id AND c.id_empresa = :e
             WHERE t.activo = true
             ORDER BY t.nombre ASC"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConfigPorTipoId(int $idEmpresa, int $idTipo): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM aprobaciones_config WHERE id_empresa = :e AND id_tipo = :t LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':t' => $idTipo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Crea o actualiza la config de un tipo para la empresa (UPSERT por el UNIQUE(id_empresa,id_tipo)). */
    public function upsertConfig(int $idEmpresa, int $idTipo, array $d, int $idUsuario): void
    {
        $sql = "INSERT INTO aprobaciones_config
                    (id_empresa, id_tipo, requiere_aprobacion, usuarios_aprobadores, umbral_monto, created_by, updated_by, created_at, updated_at)
                VALUES
                    (:e, :t, :req, :aprob, :umbral, :u, :u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT (id_empresa, id_tipo) DO UPDATE SET
                    requiere_aprobacion = EXCLUDED.requiere_aprobacion,
                    usuarios_aprobadores = EXCLUDED.usuarios_aprobadores,
                    umbral_monto = EXCLUDED.umbral_monto,
                    updated_by = EXCLUDED.updated_by,
                    updated_at = CURRENT_TIMESTAMP";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'      => $idEmpresa,
            ':t'      => $idTipo,
            ':req'    => !empty($d['requiere_aprobacion']) ? 'true' : 'false',
            ':aprob'  => json_encode(array_values(array_map('intval', $d['usuarios_aprobadores'] ?? []))),
            ':umbral' => ($d['umbral_monto'] ?? '') !== '' ? (float) $d['umbral_monto'] : null,
            ':u'      => $idUsuario,
        ]);
    }
}
