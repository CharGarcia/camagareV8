<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM fase 3, paso 6: métricas de las publicaciones (crm_publicaciones_metricas),
 * seguidores por día (crm_seguidores_diarios) y atribución al CRM: qué
 * conversaciones, contactos y oportunidades salieron de cada publicación.
 * Diseño: docs/diseno/crm-redes-fase3.md §3 (puntos 7 y 8).
 */
class CrmMetricaRepository extends BaseRepository
{
    /** Cortes que se toman después de publicar (días). */
    public const CORTES = ['24h' => 1, '7d' => 7, '30d' => 30];

    public function __construct()
    {
        parent::__construct('crm_publicaciones_metricas');
    }

    public function disponible(): bool
    {
        return $this->tablaExiste('crm_publicaciones_metricas');
    }

    // ── Cola del cron ────────────────────────────────────────────────────────

    /**
     * Cortes vencidos que aún no se tomaron, de destinos publicados con id en la
     * red, en cuentas activas de empresas activas. Incluye el token cifrado
     * (solo para el proceso del cron).
     */
    public function cortesPendientes(int $limite): array
    {
        $st = $this->db->prepare(
            "SELECT d.id, d.id_empresa, d.id_publicacion, d.formato, d.id_externo, d.publicada_at,
                    c.tipo AS canal_tipo, c.id_externo AS canal_externo, c.token_cifrado, x.corte
               FROM crm_publicaciones_destinos d
               JOIN crm_canales c ON c.id = d.id_canal AND c.estado = 'activo' AND c.eliminado = false
               JOIN empresas em ON em.id = d.id_empresa AND em.estado = '1' AND em.eliminado = false
              CROSS JOIN (VALUES ('24h', 1), ('7d', 7), ('30d', 30)) AS x(corte, dias)
              WHERE d.eliminado = false AND d.estado = 'publicada' AND d.id_externo IS NOT NULL
                AND d.publicada_at + make_interval(days => x.dias) <= NOW()
                AND d.publicada_at >= NOW() - INTERVAL '40 days'
                AND NOT EXISTS (SELECT 1 FROM crm_publicaciones_metricas m
                                 WHERE m.id_destino = d.id AND m.corte = x.corte AND m.eliminado = false)
              ORDER BY d.publicada_at, x.dias
              LIMIT " . max(1, min(200, $limite))
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Guarda un corte; si otro proceso ya lo guardó, no hace nada. */
    public function guardar(int $idEmpresa, int $idDestino, string $corte, array $v, array $crudo): bool
    {
        $st = $this->db->prepare(
            'INSERT INTO crm_publicaciones_metricas (id_empresa, id_destino, corte, alcance, vistas, me_gusta, comentarios, guardados, compartidos, clics, crudo)
             VALUES (:e, :d, :c, :al, :vi, :mg, :co, :gu, :cp, :cl, CAST(:cr AS jsonb))
             ON CONFLICT (id_destino, corte) WHERE eliminado = false DO NOTHING'
        );
        $n = fn(string $k) => isset($v[$k]) && is_numeric($v[$k]) ? (int) $v[$k] : null;
        $st->execute([
            ':e' => $idEmpresa, ':d' => $idDestino, ':c' => $corte, ':al' => $n('alcance'), ':vi' => $n('vistas'), ':mg' => $n('me_gusta'),
            ':co' => $n('comentarios'), ':gu' => $n('guardados'), ':cp' => $n('compartidos'), ':cl' => $n('clics'),
            ':cr' => json_encode($crudo, JSON_UNESCAPED_UNICODE),
        ]);
        return $st->rowCount() > 0;
    }

    /** Cuentas de Instagram activas sin el dato de seguidores de hoy. */
    public function canalesSinSeguidoresHoy(): array
    {
        $st = $this->db->prepare(
            "SELECT c.id, c.id_empresa, c.id_externo, c.token_cifrado FROM crm_canales c
               JOIN empresas em ON em.id = c.id_empresa AND em.estado = '1' AND em.eliminado = false
              WHERE c.tipo = 'instagram' AND c.estado = 'activo' AND c.eliminado = false AND c.token_cifrado IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM crm_seguidores_diarios s WHERE s.id_canal = c.id AND s.fecha = CURRENT_DATE AND s.eliminado = false)
              ORDER BY c.id LIMIT 200"
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarSeguidores(int $idEmpresa, int $idCanal, int $seguidores): void
    {
        $st = $this->db->prepare(
            'INSERT INTO crm_seguidores_diarios (id_empresa, id_canal, fecha, seguidores) VALUES (:e, :c, CURRENT_DATE, :s)
             ON CONFLICT (id_canal, fecha) WHERE eliminado = false DO NOTHING'
        );
        $st->execute([':e' => $idEmpresa, ':c' => $idCanal, ':s' => $seguidores]);
    }

    /** El enlace llegó tarde (Instagram no lo devolvió al publicar): se completa. */
    public function completarEnlace(int $idDestino, string $enlace): void
    {
        $st = $this->db->prepare('UPDATE crm_publicaciones_destinos SET enlace = :l, updated_at = NOW() WHERE id = :id AND enlace IS NULL');
        $st->execute([':l' => mb_substr($enlace, 0, 400), ':id' => $idDestino]);
    }

    // ── Lectura ──────────────────────────────────────────────────────────────

    /** Todos los cortes de los destinos indicados. */
    public function porDestinos(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT id_destino, corte, alcance, vistas, me_gusta, comentarios, guardados, compartidos, clics, tomada_at,
                    (crudo->>'error') IS NOT NULL AS con_error, crudo->>'error' AS error
               FROM crm_publicaciones_metricas
              WHERE id_destino = ANY (CAST(:ids AS int[])) AND eliminado = false
              ORDER BY id_destino, CASE corte WHEN '24h' THEN 1 WHEN '7d' THEN 2 ELSE 3 END"
        );
        $st->execute([':ids' => self::arr($ids)]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atribución al CRM por destino: conversaciones de los comentarios de esa
     * publicación (id_publicacion_externa = id de la publicación), contactos
     * creados desde que salió, y oportunidades de esas conversaciones o de esos
     * contactos posteriores a la publicación (con su valor ganado).
     * @return array<int, array{conversaciones:int, contactos_nuevos:int, oportunidades:int, ganadas:int, valor_ganado:float}>
     */
    public function atribucion(array $idsDestino): array
    {
        if (!$idsDestino) {
            return [];
        }
        $st = $this->db->prepare(
            "WITH conv AS (
                SELECT d.id AS id_destino, cv.id AS id_conv, cv.id_contacto, d.publicada_at
                  FROM crm_publicaciones_destinos d
                  JOIN crm_conversaciones cv ON cv.id_empresa = d.id_empresa AND cv.id_publicacion_externa = d.id_externo AND cv.eliminado = false
                 WHERE d.id = ANY (CAST(:ids AS int[])) AND d.id_externo IS NOT NULL
             ),
             ops AS (
                SELECT DISTINCT c.id_destino, o.id, o.estado, o.valor_estimado
                  FROM conv c
                  JOIN crm_oportunidades o ON o.eliminado = false
                   AND (o.id_conversacion = c.id_conv OR (o.id_contacto = c.id_contacto AND o.created_at >= c.publicada_at))
             )
             SELECT d.id AS id_destino,
                    (SELECT COUNT(DISTINCT id_conv) FROM conv WHERE conv.id_destino = d.id) AS conversaciones,
                    (SELECT COUNT(DISTINCT ct.id) FROM conv JOIN crm_contactos ct ON ct.id = conv.id_contacto
                      WHERE conv.id_destino = d.id AND ct.created_at >= conv.publicada_at - INTERVAL '5 minutes') AS contactos_nuevos,
                    (SELECT COUNT(*) FROM ops WHERE ops.id_destino = d.id) AS oportunidades,
                    (SELECT COUNT(*) FROM ops WHERE ops.id_destino = d.id AND ops.estado = 'ganada') AS ganadas,
                    (SELECT COALESCE(SUM(valor_estimado), 0) FROM ops WHERE ops.id_destino = d.id AND ops.estado = 'ganada') AS valor_ganado
               FROM crm_publicaciones_destinos d
              WHERE d.id = ANY (CAST(:ids2 AS int[]))"
        );
        $st->execute([':ids' => self::arr($idsDestino), ':ids2' => self::arr($idsDestino)]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_destino']] = [
                'conversaciones' => (int) $r['conversaciones'], 'contactos_nuevos' => (int) $r['contactos_nuevos'],
                'oportunidades' => (int) $r['oportunidades'], 'ganadas' => (int) $r['ganadas'], 'valor_ganado' => (float) $r['valor_ganado'],
            ];
        }
        return $out;
    }

    /**
     * Destinos publicados en el período (para Reportes), respetando registros
     * propios (publicaciones que el usuario creó o tiene a cargo).
     */
    public function publicadosEnPeriodo(int $idEmpresa, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        $p = [':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta];
        $propios = '';
        if ($idUsuarioFiltro !== null) {
            $propios = ' AND (p.created_by = :uc OR p.id_responsable = :ur)';
            $p[':uc'] = $idUsuarioFiltro;
            $p[':ur'] = $idUsuarioFiltro;
        }
        $st = $this->db->prepare(
            "SELECT d.id, d.id_publicacion, d.formato, d.enlace, d.publicada_at, p.titulo,
                    c.tipo AS canal_tipo, COALESCE(c.usuario, c.nombre) AS canal
               FROM crm_publicaciones_destinos d
               JOIN crm_publicaciones p ON p.id = d.id_publicacion AND p.eliminado = false
               JOIN crm_canales c ON c.id = d.id_canal
              WHERE d.id_empresa = :e AND d.eliminado = false AND d.estado = 'publicada'
                AND d.publicada_at >= CAST(:d AS date) AND d.publicada_at < CAST(:h AS date) + 1 {$propios}
              ORDER BY d.publicada_at DESC LIMIT 2000"
        );
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Seguidores de cada cuenta de Instagram al inicio y al final del período. */
    public function seguidoresEnPeriodo(int $idEmpresa, string $desde, string $hasta): array
    {
        $st = $this->db->prepare(
            "SELECT c.id, COALESCE(c.usuario, c.nombre) AS canal,
                    (SELECT s.seguidores FROM crm_seguidores_diarios s WHERE s.id_canal = c.id AND s.eliminado = false
                      AND s.fecha BETWEEN CAST(:d AS date) AND CAST(:h AS date) ORDER BY s.fecha LIMIT 1) AS inicio,
                    (SELECT s.seguidores FROM crm_seguidores_diarios s WHERE s.id_canal = c.id AND s.eliminado = false
                      AND s.fecha BETWEEN CAST(:d2 AS date) AND CAST(:h2 AS date) ORDER BY s.fecha DESC LIMIT 1) AS fin
               FROM crm_canales c WHERE c.id_empresa = :e AND c.tipo = 'instagram' AND c.eliminado = false ORDER BY canal"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':d2' => $desde, ':h2' => $hasta]);
        return array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), fn($r) => $r['fin'] !== null));
    }

    private static function arr(array $ids): string
    {
        return '{' . implode(',', array_map('intval', $ids)) . '}';
    }
}
