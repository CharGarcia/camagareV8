<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: reportes del embudo (pestaña Reportes del CRM). Diseño §4 punto 8.
 *
 * Período: las oportunidades CREADAS en [desde, hasta] para lo que entra al
 * embudo, y las CERRADAS (ganadas/perdidas) en [desde, hasta] para los
 * resultados. Lo abierto se mide a hoy. Sin acceso total, solo las
 * oportunidades creadas por el usuario o asignadas a él (decisión 4).
 * No aplica el filtro de ambiente SRI: el CRM no tiene documentos tributarios.
 */
class CrmReporteRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('crm_oportunidades');
    }

    /** WHERE común: empresa, no eliminadas, embudo/responsable/origen y registros propios. */
    private function base(int $idEmpresa, array $f, ?int $idUsuarioFiltro, array &$p, string $a = 'o'): string
    {
        $p[':e'] = $idEmpresa;
        $w = "{$a}.id_empresa = :e AND {$a}.eliminado = false"
           . CrmContactoRepository::condicionPropios($a, $p, $idUsuarioFiltro);
        if (!empty($f['id_embudo']))      { $w .= " AND {$a}.id_embudo = :emb";      $p[':emb'] = (int) $f['id_embudo']; }
        if (!empty($f['id_responsable'])) { $w .= " AND {$a}.id_responsable = :resp"; $p[':resp'] = (int) $f['id_responsable']; }
        if (!empty($f['id_origen']))      { $w .= " AND {$a}.id_origen = :ori";      $p[':ori'] = (int) $f['id_origen']; }
        return $w;
    }

    /** Totales del período. */
    public function resumen(int $idEmpresa, array $f, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base($idEmpresa, $f, $idUsuarioFiltro, $p);
        $p += [':d1' => $desde, ':h1' => $hasta, ':d2' => $desde, ':h2' => $hasta, ':d3' => $desde, ':h3' => $hasta,
               ':d4' => $desde, ':h4' => $hasta, ':d5' => $desde, ':h5' => $hasta, ':d6' => $desde, ':h6' => $hasta];
        $cerr = fn(string $d, string $h) => "o.cerrada_at >= {$d} AND o.cerrada_at < CAST({$h} AS date) + 1";
        $st = $this->db->prepare(
            "SELECT
                COUNT(*) FILTER (WHERE o.created_at >= :d1 AND o.created_at < CAST(:h1 AS date) + 1)                    AS creadas,
                COUNT(*) FILTER (WHERE o.estado = 'ganada' AND " . $cerr(':d2', ':h2') . ")                            AS ganadas,
                COUNT(*) FILTER (WHERE o.estado = 'perdida' AND " . $cerr(':d3', ':h3') . ")                           AS perdidas,
                COALESCE(SUM(o.valor_estimado) FILTER (WHERE o.estado = 'ganada' AND " . $cerr(':d4', ':h4') . "), 0) AS valor_ganado,
                AVG(EXTRACT(EPOCH FROM (o.cerrada_at - o.created_at)) / 86400.0)
                    FILTER (WHERE o.estado = 'ganada' AND " . $cerr(':d5', ':h5') . ")                                 AS dias_cierre,
                COALESCE(SUM(o.valor_estimado) FILTER (WHERE o.estado = 'perdida' AND " . $cerr(':d6', ':h6') . "), 0) AS valor_perdido,
                COUNT(*) FILTER (WHERE o.estado = 'abierta')                                                           AS abiertas,
                COALESCE(SUM(o.valor_estimado) FILTER (WHERE o.estado = 'abierta'), 0)                                 AS valor_abierto
             FROM crm_oportunidades o WHERE {$w}"
        );
        $st->execute($p);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $g = (int) ($r['ganadas'] ?? 0);
        $l = (int) ($r['perdidas'] ?? 0);
        return [
            'creadas'       => (int) ($r['creadas'] ?? 0),
            'ganadas'       => $g,
            'perdidas'      => $l,
            'tasa_cierre'   => ($g + $l) > 0 ? round($g * 100 / ($g + $l), 1) : null,
            'valor_ganado'  => round((float) ($r['valor_ganado'] ?? 0), 2),
            'valor_perdido' => round((float) ($r['valor_perdido'] ?? 0), 2),
            'ticket'        => $g > 0 ? round((float) $r['valor_ganado'] / $g, 2) : null,
            'dias_cierre'   => $r['dias_cierre'] !== null ? round((float) $r['dias_cierre'], 1) : null,
            'abiertas'      => (int) ($r['abiertas'] ?? 0),
            'valor_abierto' => round((float) ($r['valor_abierto'] ?? 0), 2),
        ];
    }

    /** Embudo actual: oportunidades abiertas por etapa (de un embudo). */
    public function porEtapa(int $idEmpresa, int $idEmbudo, array $f, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $f['id_embudo'] = $idEmbudo;
        $w = $this->base($idEmpresa, $f, $idUsuarioFiltro, $p);
        $p[':emb2'] = $idEmbudo;
        $p[':e2'] = $idEmpresa;
        $st = $this->db->prepare(
            "SELECT et.id, et.nombre, et.color, et.probabilidad,
                    COUNT(o.id) AS cantidad, COALESCE(SUM(o.valor_estimado), 0) AS valor,
                    COALESCE(SUM(o.valor_estimado * et.probabilidad / 100.0), 0) AS ponderado
               FROM crm_etapas et
               LEFT JOIN crm_oportunidades o ON o.id_etapa = et.id AND o.estado = 'abierta' AND {$w}
              WHERE et.id_embudo = :emb2 AND et.id_empresa = :e2 AND et.eliminado = false AND et.tipo = 'abierta'
              GROUP BY et.id, et.nombre, et.color, et.probabilidad, et.orden
              ORDER BY et.orden, et.id"
        );
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Por mes del período: creadas, ganadas (y valor) y perdidas. */
    public function porMes(int $idEmpresa, array $f, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base($idEmpresa, $f, $idUsuarioFiltro, $p);
        $p += [':d1' => $desde, ':h1' => $hasta, ':d2' => $desde, ':h2' => $hasta, ':d3' => $desde, ':h3' => $hasta];
        $st = $this->db->prepare(
            "WITH meses AS (
                SELECT generate_series(date_trunc('month', CAST(:d1 AS date)), date_trunc('month', CAST(:h1 AS date)), INTERVAL '1 month')::date AS mes
             ), creadas AS (
                SELECT date_trunc('month', o.created_at)::date AS mes, COUNT(*) AS n
                  FROM crm_oportunidades o WHERE {$w} AND o.created_at >= :d2 AND o.created_at < CAST(:h2 AS date) + 1
                 GROUP BY 1
             ), cerradas AS (
                SELECT date_trunc('month', o.cerrada_at)::date AS mes,
                       COUNT(*) FILTER (WHERE o.estado = 'ganada') AS ganadas,
                       COALESCE(SUM(o.valor_estimado) FILTER (WHERE o.estado = 'ganada'), 0) AS valor_ganado,
                       COUNT(*) FILTER (WHERE o.estado = 'perdida') AS perdidas
                  FROM crm_oportunidades o WHERE {$w} AND o.estado IN ('ganada', 'perdida')
                   AND o.cerrada_at >= :d3 AND o.cerrada_at < CAST(:h3 AS date) + 1
                 GROUP BY 1
             )
             SELECT to_char(m.mes, 'YYYY-MM') AS mes, COALESCE(c.n, 0) AS creadas,
                    COALESCE(x.ganadas, 0) AS ganadas, COALESCE(x.valor_ganado, 0) AS valor_ganado, COALESCE(x.perdidas, 0) AS perdidas
               FROM meses m LEFT JOIN creadas c ON c.mes = m.mes LEFT JOIN cerradas x ON x.mes = m.mes
              ORDER BY m.mes"
        );
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agrupado por origen o por responsable: creadas en el período, ganadas y
     * perdidas cerradas en el período, valor ganado y lo abierto hoy.
     * @param string $por 'origen' | 'responsable'
     */
    public function agrupado(string $por, int $idEmpresa, array $f, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        [$col, $join, $nombre] = $por === 'origen'
            ? ['o.id_origen', 'LEFT JOIN crm_origenes g ON g.id = o.id_origen', "COALESCE(g.nombre, 'Sin origen')"]
            : ['o.id_responsable', 'LEFT JOIN usuarios g ON g.id = o.id_responsable', "COALESCE(g.nombre, 'Sin responsable')"];
        $p = [];
        $w = $this->base($idEmpresa, $f, $idUsuarioFiltro, $p);
        $p += [':d1' => $desde, ':h1' => $hasta, ':d2' => $desde, ':h2' => $hasta, ':d3' => $desde, ':h3' => $hasta, ':d4' => $desde, ':h4' => $hasta];
        $st = $this->db->prepare(
            "SELECT {$col} AS id, {$nombre} AS nombre,
                    COUNT(*) FILTER (WHERE o.created_at >= :d1 AND o.created_at < CAST(:h1 AS date) + 1) AS creadas,
                    COUNT(*) FILTER (WHERE o.estado = 'ganada' AND o.cerrada_at >= :d2 AND o.cerrada_at < CAST(:h2 AS date) + 1) AS ganadas,
                    COUNT(*) FILTER (WHERE o.estado = 'perdida' AND o.cerrada_at >= :d3 AND o.cerrada_at < CAST(:h3 AS date) + 1) AS perdidas,
                    COALESCE(SUM(o.valor_estimado) FILTER (WHERE o.estado = 'ganada' AND o.cerrada_at >= :d4 AND o.cerrada_at < CAST(:h4 AS date) + 1), 0) AS valor_ganado,
                    COUNT(*) FILTER (WHERE o.estado = 'abierta') AS abiertas,
                    COALESCE(SUM(o.valor_estimado) FILTER (WHERE o.estado = 'abierta'), 0) AS valor_abierto
               FROM crm_oportunidades o {$join}
              WHERE {$w}
              GROUP BY 1, 2
             HAVING COUNT(*) FILTER (WHERE o.created_at >= :d1 AND o.created_at < CAST(:h1 AS date) + 1) > 0
                 OR COUNT(*) FILTER (WHERE o.estado IN ('ganada','perdida') AND o.cerrada_at >= :d2 AND o.cerrada_at < CAST(:h2 AS date) + 1) > 0
                 OR COUNT(*) FILTER (WHERE o.estado = 'abierta') > 0
              ORDER BY valor_ganado DESC, creadas DESC, nombre"
        );
        $st->execute($p);
        return array_map(function ($r) {
            $g = (int) $r['ganadas'];
            $l = (int) $r['perdidas'];
            $r['tasa_cierre'] = ($g + $l) > 0 ? round($g * 100 / ($g + $l), 1) : null;
            return $r;
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Motivos de pérdida en el período. */
    public function motivosPerdida(int $idEmpresa, array $f, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $w = $this->base($idEmpresa, $f, $idUsuarioFiltro, $p);
        $p += [':d1' => $desde, ':h1' => $hasta];
        $st = $this->db->prepare(
            "SELECT COALESCE(mp.nombre, 'Sin motivo') AS nombre, COUNT(*) AS cantidad, COALESCE(SUM(o.valor_estimado), 0) AS valor
               FROM crm_oportunidades o LEFT JOIN crm_motivos_perdida mp ON mp.id = o.id_motivo_perdida
              WHERE {$w} AND o.estado = 'perdida' AND o.cerrada_at >= :d1 AND o.cerrada_at < CAST(:h1 AS date) + 1
              GROUP BY 1 ORDER BY cantidad DESC, nombre"
        );
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Días promedio que las oportunidades pasan en cada etapa intermedia de un
     * embudo, a partir del historial de etapas (de cada entrada hasta el
     * siguiente cambio). Solo estadías ya terminadas que empezaron en el período.
     */
    public function tiempoPorEtapa(int $idEmpresa, int $idEmbudo, array $f, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        $p = [];
        $f['id_embudo'] = $idEmbudo;
        $w = $this->base($idEmpresa, $f, $idUsuarioFiltro, $p);
        $p += [':d1' => $desde, ':h1' => $hasta, ':emb2' => $idEmbudo, ':e2' => $idEmpresa];
        $st = $this->db->prepare(
            "WITH tramos AS (
                SELECT l.id_etapa_nueva AS id_etapa, l.fecha,
                       LEAD(l.fecha) OVER (PARTITION BY l.id_oportunidad ORDER BY l.fecha, l.id) AS hasta
                  FROM crm_oportunidades_etapas_log l
                  JOIN crm_oportunidades o ON o.id = l.id_oportunidad
                 WHERE {$w}
             )
             SELECT et.id, et.nombre, et.color, COUNT(t.hasta) AS pasos,
                    AVG(EXTRACT(EPOCH FROM (t.hasta - t.fecha)) / 86400.0) AS dias_promedio
               FROM crm_etapas et
               LEFT JOIN tramos t ON t.id_etapa = et.id AND t.hasta IS NOT NULL
                                 AND t.fecha >= :d1 AND t.fecha < CAST(:h1 AS date) + 1
              WHERE et.id_embudo = :emb2 AND et.id_empresa = :e2 AND et.eliminado = false AND et.tipo = 'abierta'
              GROUP BY et.id, et.nombre, et.color, et.orden
              ORDER BY et.orden, et.id"
        );
        $st->execute($p);
        return array_map(function ($r) {
            $r['dias_promedio'] = $r['dias_promedio'] !== null ? round((float) $r['dias_promedio'], 1) : null;
            return $r;
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }
}
