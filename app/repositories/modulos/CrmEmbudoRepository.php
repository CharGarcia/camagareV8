<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: embudos de venta y sus etapas (tablas crm_embudos / crm_etapas).
 * Diseño: docs/diseno/crm-redes-fase1.md §5.5.
 */
class CrmEmbudoRepository extends BaseRepository
{
    protected string $table = 'crm_embudos';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /** ¿Ya se aplicó el SQL del CRM (database/2026-11-08_crm_fase1.sql)? */
    public function disponible(): bool
    {
        return $this->tablaExiste('crm_oportunidades');
    }

    /**
     * Candado de inicialización por empresa: dos pestañas abriendo el CRM a la
     * vez no deben sembrar dos embudos por defecto. Llamar dentro de la transacción.
     */
    public function lockInicializacion(int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_init:' || CAST(:e AS text)))");
        $st->execute([':e' => $idEmpresa]);
    }

    public function contarEmbudos(int $idEmpresa): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM crm_embudos WHERE id_empresa = :e AND eliminado = false");
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    // ── Embudos ──────────────────────────────────────────────────────────────

    /** Embudos de la empresa (activos o todos), el de por defecto primero. */
    public function getEmbudos(int $idEmpresa, bool $soloActivos = true): array
    {
        $sql = "SELECT e.id, e.nombre, e.es_defecto, e.orden, e.estado,
                       (SELECT COUNT(*) FROM crm_oportunidades o
                         WHERE o.id_embudo = e.id AND o.id_empresa = e.id_empresa
                           AND o.eliminado = false AND o.estado = 'abierta') AS abiertas
                  FROM crm_embudos e
                 WHERE e.id_empresa = :e AND e.eliminado = false"
             . ($soloActivos ? " AND e.estado = 'activo'" : '')
             . " ORDER BY e.es_defecto DESC, e.orden, e.nombre, e.id";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmbudo(int $id, int $idEmpresa): ?array
    {
        return $this->findById($id, $idEmpresa);
    }

    public function getEmbudoDefecto(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM crm_embudos
              WHERE id_empresa = :e AND eliminado = false AND estado = 'activo'
              ORDER BY es_defecto DESC, orden, id LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertarEmbudo(int $idEmpresa, string $nombre, bool $esDefecto, int $orden, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_embudos (id_empresa, nombre, es_defecto, orden, estado, created_by, updated_by)
             VALUES (:e, :n, :d, :o, 'activo', :u, :u2) RETURNING id"
        );
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':n', $this->caparTexto('nombre', $nombre, 'crm_embudos'));
        // Literal 'true'/'false': PDO manda el false de PHP como '' y PostgreSQL lo rechaza.
        $st->bindValue(':d', $esDefecto ? 'true' : 'false');
        $st->bindValue(':o', $orden, PDO::PARAM_INT);
        $st->bindValue(':u', $idUsuario, PDO::PARAM_INT);
        $st->bindValue(':u2', $idUsuario, PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function actualizarEmbudo(int $id, int $idEmpresa, string $nombre, int $orden, string $estado, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_embudos SET nombre = :n, orden = :o, estado = :s,
                    updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':n' => $this->caparTexto('nombre', $nombre, 'crm_embudos'), ':o' => $orden, ':s' => $estado,
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    /** Deja $id como único embudo por defecto de la empresa. */
    public function marcarDefecto(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_embudos SET es_defecto = false, updated_at = NOW(), updated_by = :u
              WHERE id_empresa = :e AND eliminado = false AND es_defecto = true AND id <> :id"
        );
        $st->execute([':u' => $idUsuario, ':e' => $idEmpresa, ':id' => $id]);
        $st = $this->db->prepare(
            "UPDATE crm_embudos SET es_defecto = true, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':e' => $idEmpresa, ':id' => $id]);
    }

    public function eliminarEmbudo(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_embudos SET eliminado = true, es_defecto = false, deleted_at = NOW(), deleted_by = :u,
                    updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
        $st = $this->db->prepare(
            "UPDATE crm_etapas SET eliminado = true, deleted_at = NOW(), deleted_by = :u
              WHERE id_embudo = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Oportunidades (de cualquier estado) que usan el embudo o la etapa. */
    public function contarOportunidades(int $idEmpresa, ?int $idEmbudo, ?int $idEtapa = null): int
    {
        $sql = "SELECT COUNT(*) FROM crm_oportunidades WHERE id_empresa = :e AND eliminado = false";
        $p = [':e' => $idEmpresa];
        if ($idEmbudo !== null) { $sql .= " AND id_embudo = :b"; $p[':b'] = $idEmbudo; }
        if ($idEtapa !== null)  { $sql .= " AND id_etapa = :t";  $p[':t'] = $idEtapa; }
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn();
    }

    // ── Etapas ───────────────────────────────────────────────────────────────

    /** Etapas de un embudo en orden: abiertas por `orden`, luego ganada y perdida. */
    public function getEtapas(int $idEmbudo, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, id_embudo, nombre, orden, probabilidad, tipo, color
               FROM crm_etapas
              WHERE id_embudo = :b AND id_empresa = :e AND eliminado = false
              ORDER BY CASE tipo WHEN 'abierta' THEN 0 WHEN 'ganada' THEN 1 ELSE 2 END, orden, id"
        );
        $st->execute([':b' => $idEmbudo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEtapa(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM crm_etapas WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Etapa de cierre (ganada/perdida) de un embudo. */
    public function getEtapaPorTipo(int $idEmbudo, int $idEmpresa, string $tipo): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM crm_etapas
              WHERE id_embudo = :b AND id_empresa = :e AND tipo = :t AND eliminado = false
              ORDER BY orden, id LIMIT 1"
        );
        $st->execute([':b' => $idEmbudo, ':e' => $idEmpresa, ':t' => $tipo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertarEtapa(int $idEmpresa, int $idEmbudo, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_etapas (id_empresa, id_embudo, nombre, orden, probabilidad, tipo, color, created_by, updated_by)
             VALUES (:e, :b, :n, :o, :p, :t, :c, :u, :u2) RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':b' => $idEmbudo,
            ':n' => $this->caparTexto('nombre', $d['nombre'], 'crm_etapas'),
            ':o' => (int) $d['orden'], ':p' => (int) $d['probabilidad'], ':t' => $d['tipo'],
            ':c' => $d['color'] ?? null, ':u' => $idUsuario, ':u2' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    /** El tipo de una etapa no cambia después de creada (ver CrmEmbudoRules). */
    public function actualizarEtapa(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_etapas SET nombre = :n, orden = :o, probabilidad = :p, color = :c,
                    updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':n' => $this->caparTexto('nombre', $d['nombre'], 'crm_etapas'),
            ':o' => (int) $d['orden'], ':p' => (int) $d['probabilidad'], ':c' => $d['color'] ?? null,
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    /** Reordena las etapas abiertas de un embudo según la lista de ids recibida. */
    public function reordenarEtapas(int $idEmbudo, int $idEmpresa, array $ids, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_etapas SET orden = :o, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_embudo = :b AND id_empresa = :e AND eliminado = false AND tipo = 'abierta'"
        );
        foreach (array_values($ids) as $i => $id) {
            $st->execute([':o' => ($i + 1) * 10, ':u' => $idUsuario, ':id' => (int) $id, ':b' => $idEmbudo, ':e' => $idEmpresa]);
        }
    }

    public function eliminarEtapa(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_etapas SET eliminado = true, deleted_at = NOW(), deleted_by = :u,
                    updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function maxOrdenEtapa(int $idEmbudo, int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(MAX(orden), 0) FROM crm_etapas
              WHERE id_embudo = :b AND id_empresa = :e AND eliminado = false AND tipo = 'abierta'"
        );
        $st->execute([':b' => $idEmbudo, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }
}
