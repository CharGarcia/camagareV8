<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM fase 3: biblioteca de medios (tabla crm_medios). Los archivos viven en
 * storage/crm_media/{empresa}/pub/ (no público).
 */
class CrmMedioRepository extends BaseRepository
{
    /** Estados de publicación que todavía necesitan el archivo. */
    public const ESTADOS_PENDIENTES = ['borrador', 'en_revision', 'aprobada', 'programada', 'publicando', 'con_error', 'parcial'];

    public function __construct()
    {
        parent::__construct('crm_medios');
    }

    public function disponible(): bool
    {
        return $this->tablaExiste('crm_medios');
    }

    public function insertar(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            'INSERT INTO crm_medios (id_empresa, tipo, ruta, nombre_original, mime, bytes, ancho, alto, duracion_seg,
                                     texto_alternativo, es_ia, ultimo_uso_at, created_by, updated_by)
             VALUES (:e, :t, :r, :n, :m, :b, :an, :al, :d, :alt, :ia, NOW(), :u, :u2) RETURNING id'
        );
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':t', $d['tipo']);
        $st->bindValue(':r', $d['ruta']);
        $st->bindValue(':n', $d['nombre_original'] !== null ? $this->caparTexto('nombre_original', $d['nombre_original']) : null);
        $st->bindValue(':m', $d['mime']);
        $st->bindValue(':b', (int) $d['bytes'], PDO::PARAM_INT);
        $st->bindValue(':an', $d['ancho'] ?? null, isset($d['ancho']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':al', $d['alto'] ?? null, isset($d['alto']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':d', isset($d['duracion_seg']) ? (string) $d['duracion_seg'] : null);
        $st->bindValue(':alt', $d['texto_alternativo'] ?? null);
        $st->bindValue(':ia', !empty($d['es_ia']) ? 'true' : 'false');
        $st->bindValue(':u', $idUsuario, PDO::PARAM_INT);
        $st->bindValue(':u2', $idUsuario, PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare('SELECT * FROM crm_medios WHERE id = :id AND id_empresa = :e AND eliminado = false');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Biblioteca de la empresa, más recientes primero. */
    public function listar(int $idEmpresa, string $tipo, int $page, int $perPage): array
    {
        $p = [':e' => $idEmpresa];
        $where = 'WHERE id_empresa = :e AND eliminado = false';
        if (in_array($tipo, ['imagen', 'video'], true)) {
            $where .= ' AND tipo = :t';
            $p[':t'] = $tipo;
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM crm_medios {$where}");
        $st->execute($p);
        $total = (int) $st->fetchColumn();
        $st = $this->db->prepare(
            "SELECT id, tipo, nombre_original, mime, bytes, ancho, alto, duracion_seg, texto_alternativo, es_ia, created_at
               FROM crm_medios {$where} ORDER BY created_at DESC, id DESC
              LIMIT " . max(1, min(200, $perPage)) . ' OFFSET ' . (max(1, $page) - 1) * max(1, min(200, $perPage))
        );
        $st->execute($p);
        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function actualizarDatos(int $id, int $idEmpresa, ?string $alt, bool $esIa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_medios SET texto_alternativo = :a, es_ia = :ia, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':a' => $alt, ':ia' => $esIa ? 'true' : 'false', ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Publicaciones aún no publicadas que usan el medio (impiden eliminarlo). */
    public function publicacionesPendientes(int $id, int $idEmpresa): int
    {
        $st = $this->db->prepare(
            'SELECT COUNT(DISTINCT p.id) FROM crm_publicaciones_medios pm
               JOIN crm_publicaciones p ON p.id = pm.id_publicacion AND p.eliminado = false
              WHERE pm.id_medio = :m AND pm.id_empresa = :e AND pm.eliminado = false
                AND p.estado = ANY (CAST(:est AS text[]))'
        );
        $st->execute([':m' => $id, ':e' => $idEmpresa, ':est' => '{' . implode(',', self::ESTADOS_PENDIENTES) . '}']);
        return (int) $st->fetchColumn();
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_medios SET eliminado = true, deleted_at = NOW(), deleted_by = :u, updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Se usó en una publicación (para la purga de los que no se usan). */
    public function marcarUso(array $ids, int $idEmpresa): void
    {
        if (!$ids) {
            return;
        }
        $st = $this->db->prepare('UPDATE crm_medios SET ultimo_uso_at = NOW() WHERE id_empresa = :e AND id = ANY (CAST(:ids AS int[]))');
        $st->execute([':e' => $idEmpresa, ':ids' => '{' . implode(',', array_map('intval', $ids)) . '}']);
    }

    /**
     * Medios sin uso hace más de $dias días y que ninguna publicación pendiente
     * necesita (de todas las empresas: corre en el cron).
     */
    public function porPurgar(int $dias, int $limite = 500): array
    {
        $st = $this->db->prepare(
            "SELECT m.id, m.id_empresa, m.ruta FROM crm_medios m
              WHERE m.eliminado = false
                AND COALESCE(m.ultimo_uso_at, m.created_at) < NOW() - make_interval(days => :d)
                AND NOT EXISTS (SELECT 1 FROM crm_publicaciones_medios pm
                                  JOIN crm_publicaciones p ON p.id = pm.id_publicacion AND p.eliminado = false
                                 WHERE pm.id_medio = m.id AND pm.eliminado = false
                                   AND p.estado = ANY (CAST(:est AS text[])))
              ORDER BY m.id LIMIT " . max(1, min(5000, $limite))
        );
        $st->execute([':d' => $dias, ':est' => '{' . implode(',', self::ESTADOS_PENDIENTES) . '}']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Purga: queda la fila (auditoría), sin archivo. */
    public function marcarPurgado(int $id): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_medios SET eliminado = true, deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND eliminado = false"
        );
        $st->execute([':id' => $id]);
    }

    /**
     * Medio para el enlace público que descarga Instagram: vigente y de una
     * empresa activa (endpoint sin sesión, CLAUDE.md §6).
     */
    public function getPublico(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT m.id, m.id_empresa, m.ruta, m.mime, m.bytes FROM crm_medios m
               JOIN empresas e ON e.id = m.id_empresa AND e.estado = '1' AND e.eliminado = false
              WHERE m.id = :id AND m.eliminado = false"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
