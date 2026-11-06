<?php

declare(strict_types=1);

namespace App\repositories;

/**
 * Métricas del sistema — pestañas Salud técnica, Uso de módulos y Comercial (solo nivel 3).
 *
 * Consultas globales de plataforma (no filtran por empresa a propósito, §4). Todo es de solo
 * lectura; las vistas del catálogo de PostgreSQL (pg_stat_*) se consultan con tope de filas.
 * Cada bloque comprueba antes que su tabla exista, para no romper en una base sin migrar.
 */
class MetricasPlataformaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('empresas');
    }

    // ───────────────────────────── Salud técnica ─────────────────────────────

    public function baseDatos(): array
    {
        $r = $this->db->query("
            SELECT current_database() AS nombre,
                   pg_database_size(current_database()) AS bytes,
                   version() AS version,
                   current_setting('max_connections')::int AS max_conexiones,
                   (SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database()) AS conexiones,
                   (SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database() AND state = 'active') AS activas,
                   (SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database() AND state = 'idle in transaction') AS idle_tx,
                   (SELECT ROUND(100.0 * SUM(blks_hit) / NULLIF(SUM(blks_hit) + SUM(blks_read), 0), 2)
                      FROM pg_stat_database WHERE datname = current_database()) AS cache_hit,
                   (SELECT SUM(deadlocks) FROM pg_stat_database WHERE datname = current_database()) AS deadlocks,
                   (SELECT SUM(temp_bytes) FROM pg_stat_database WHERE datname = current_database()) AS temp_bytes,
                   (SELECT stats_reset FROM pg_stat_database WHERE datname = current_database()) AS stats_desde
        ")->fetch(\PDO::FETCH_ASSOC);
        return $r ?: [];
    }

    /** Tablas más pesadas (datos + índices) con filas estimadas y último vacuum/analyze. */
    public function tablasGrandes(int $limite = 15): array
    {
        return $this->db->query("
            SELECT s.relname AS tabla,
                   pg_total_relation_size(s.relid) AS bytes,
                   pg_indexes_size(s.relid)        AS bytes_indices,
                   s.n_live_tup AS filas,
                   s.n_dead_tup AS muertas,
                   GREATEST(s.last_autovacuum, s.last_vacuum)   AS ultimo_vacuum,
                   GREATEST(s.last_autoanalyze, s.last_analyze) AS ultimo_analyze
            FROM pg_stat_user_tables s
            ORDER BY pg_total_relation_size(s.relid) DESC
            LIMIT {$limite}")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Consultas que llevan más de N segundos ejecutándose ahora mismo. */
    public function consultasEnCurso(int $segundos = 10, int $limite = 20): array
    {
        return $this->db->query("
            SELECT pid, state, usename AS usuario,
                   EXTRACT(EPOCH FROM (NOW() - query_start))::int AS segundos,
                   wait_event_type, left(regexp_replace(query, '\\s+', ' ', 'g'), 300) AS consulta
            FROM pg_stat_activity
            WHERE datname = current_database()
              AND pid <> pg_backend_pid()
              AND state <> 'idle'
              AND query_start < NOW() - INTERVAL '{$segundos} seconds'
            ORDER BY query_start
            LIMIT {$limite}")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function pgStatStatementsDisponible(): bool
    {
        return (bool) $this->db->query("SELECT 1 FROM pg_extension WHERE extname = 'pg_stat_statements'")->fetchColumn();
    }

    /** Consultas que más tiempo total consumen (pg_stat_statements, desde su último reinicio). */
    public function consultasPesadas(int $limite = 15): array
    {
        try {
            return $this->db->query("
                SELECT calls AS llamadas,
                       ROUND(total_exec_time::numeric / 1000, 1) AS segundos_total,
                       ROUND(mean_exec_time::numeric, 1)         AS ms_promedio,
                       ROUND(max_exec_time::numeric, 1)          AS ms_maximo,
                       rows AS filas,
                       left(regexp_replace(query, '\\s+', ' ', 'g'), 300) AS consulta
                FROM pg_stat_statements
                WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
                ORDER BY total_exec_time DESC
                LIMIT {$limite}")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];   // la extensión existe pero no está en shared_preload_libraries, o sin permiso
        }
    }

    /** Comprobantes cuyo último estado en el SRI sigue sin resolver hace más de 1 hora (últimos 15 días). */
    public function sriAtascados(int $limite = 50): array
    {
        return $this->db->query("
            WITH ult AS (
                SELECT DISTINCT ON (l.tipo_comprobante, l.id_comprobante)
                       l.id_empresa, l.tipo_comprobante, l.id_comprobante, l.accion, l.created_at, l.clave_acceso
                FROM sri_envio_log l
                WHERE l.created_at >= NOW() - INTERVAL '15 days'
                  AND l.tipo_ambiente::text = '2'
                  AND l.accion NOT IN ('aviso_email')
                ORDER BY l.tipo_comprobante, l.id_comprobante, l.id DESC
            )
            SELECT u.*, e.nombre AS empresa
            FROM ult u
            LEFT JOIN empresas e ON e.id = u.id_empresa
            WHERE u.accion IN ('enviando', 'recibida', 'en_procesamiento')
              AND u.created_at < NOW() - INTERVAL '1 hour'
            ORDER BY u.created_at
            LIMIT {$limite}")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function erroresRecientes(): array
    {
        if (!$this->tablaExiste('errores_sistema')) {
            return [];
        }
        return $this->db->query("
            SELECT COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '1 hour')   AS ultima_hora,
                   COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '24 hours') AS ultimas_24h,
                   COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '24 hours' AND tipo = 'fatal') AS fatales_24h,
                   MAX(created_at) AS ultimo
            FROM errores_sistema
            WHERE created_at >= NOW() - INTERVAL '24 hours'")->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Última vez que el resumen diario (cron) escribió algo: señal fiable de que el cron corre. */
    public function ultimoResumenDiario(): ?string
    {
        if (!$this->tablaExiste('metricas_diarias')) {
            return null;
        }
        $v = $this->db->query('SELECT MAX(calculado_at) FROM metricas_diarias')->fetchColumn();
        return $v ? (string) $v : null;
    }

    // ───────────────────────────── Uso de módulos ─────────────────────────────

    public function usoDisponible(): bool
    {
        return $this->tablaExiste('usuarios_submodulos_uso');
    }

    /**
     * Uso de cada submódulo del menú: aperturas acumuladas, usuarios y empresas que lo
     * usaron en el rango (por último uso) y cuántos usuarios tienen permiso.
     */
    public function usoSubmodulos(string $desde, string $hasta): array
    {
        $sql = "
            WITH uso AS (
                SELECT x.id_submodulo,
                       SUM(x.veces)                                                     AS aperturas,
                       COUNT(DISTINCT x.id_usuario)                                     AS usuarios_total,
                       COUNT(DISTINCT x.id_usuario) FILTER (WHERE x.ultimo_uso >= :desde AND x.ultimo_uso < (CAST(:hasta AS date) + 1)) AS usuarios_rango,
                       COUNT(DISTINCT x.id_empresa) FILTER (WHERE x.ultimo_uso >= :desde AND x.ultimo_uso < (CAST(:hasta AS date) + 1)) AS empresas_rango,
                       MAX(x.ultimo_uso)                                                AS ultimo_uso
                FROM usuarios_submodulos_uso x
                JOIN usuarios u ON u.id = x.id_usuario AND u.nivel <> 3
                GROUP BY x.id_submodulo
            ),
            permisos AS (
                SELECT ma.id_submodulo, COUNT(DISTINCT ma.id_usuario) AS con_permiso
                FROM modulos_asignados ma
                WHERE COALESCE(ma.r, 0) = 1 OR COALESCE(ma.t, 0) = 1
                GROUP BY ma.id_submodulo
            )
            SELECT s.id, s.nombre_submodulo AS submodulo, s.ruta, m.nombre_modulo AS modulo,
                   COALESCE(uso.aperturas, 0)      AS aperturas,
                   COALESCE(uso.usuarios_total, 0) AS usuarios_total,
                   COALESCE(uso.usuarios_rango, 0) AS usuarios_rango,
                   COALESCE(uso.empresas_rango, 0) AS empresas_rango,
                   uso.ultimo_uso,
                   COALESCE(p.con_permiso, 0)      AS con_permiso
            FROM submodulos_menu s
            LEFT JOIN modulos_menu m ON m.id = s.id_modulo
            LEFT JOIN uso ON uso.id_submodulo = s.id
            LEFT JOIN permisos p ON p.id_submodulo = s.id
            WHERE COALESCE(s.status, 1) = 1
            ORDER BY aperturas DESC, s.nombre_submodulo";
        $st = $this->db->prepare($sql);
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Desde cuándo se registran los usos de submódulos. */
    public function usoDesde(): ?string
    {
        $v = $this->db->query('SELECT MIN(created_at) FROM usuarios_submodulos_uso')->fetchColumn();
        return $v ? (string) $v : null;
    }

    /** Escrituras auditadas (log_sistema) por tabla en el rango: qué se crea/edita/elimina más. */
    public function escriturasPorTabla(string $desde, string $hasta, int $limite = 20): array
    {
        $sql = "
            SELECT l.tabla_afectada AS tabla,
                   COUNT(*) AS total,
                   COUNT(*) FILTER (WHERE lower(l.accion) LIKE 'crear%' OR lower(l.accion) LIKE 'insert%')    AS creaciones,
                   COUNT(*) FILTER (WHERE lower(l.accion) LIKE 'actualizar%' OR lower(l.accion) LIKE 'editar%' OR lower(l.accion) LIKE 'update%') AS ediciones,
                   COUNT(*) FILTER (WHERE lower(l.accion) LIKE 'eliminar%' OR lower(l.accion) LIKE 'delete%') AS eliminaciones,
                   COUNT(DISTINCT l.id_empresa) AS empresas,
                   COUNT(DISTINCT l.id_usuario) AS usuarios
            FROM log_sistema l
            WHERE l.created_at >= :desde AND l.created_at < (CAST(:hasta AS date) + 1)
            GROUP BY 1
            ORDER BY total DESC
            LIMIT {$limite}";
        $st = $this->db->prepare($sql);
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ───────────────────────────── Comercial ─────────────────────────────

    /**
     * Empresas que cobran las suscripciones de la plataforma: las controladoras que usan las
     * empresas (`id_empresa_suscripciones`) y todos sus establecimientos (mismo RUC).
     * @return int[]
     */
    public function idsControladoras(): array
    {
        if (!$this->columnaExiste('empresas', 'id_empresa_suscripciones')) {
            return [];
        }
        $rows = $this->db->query("
            SELECT DISTINCT e2.id
            FROM empresas e
            JOIN empresas c  ON c.id = e.id_empresa_suscripciones AND c.eliminado = false
            JOIN empresas e2 ON regexp_replace(e2.ruc, '[^0-9]', '', 'g') = regexp_replace(c.ruc, '[^0-9]', '', 'g')
                            AND e2.eliminado = false
            WHERE e.eliminado = false")->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    /** Suscripciones de la plataforma por estado, ingreso mensual recurrente (sin IVA) y morosidad. */
    public function suscripcionesResumen(array $idsControladoras): array
    {
        if ($idsControladoras === [] || !$this->tablaExiste('suscripciones')) {
            return [];
        }
        $in = implode(',', array_map('intval', $idsControladoras));
        return $this->db->query("
            WITH det AS (
                SELECT d.id_suscripcion, SUM(d.cantidad * d.precio_unitario) AS subtotal
                FROM suscripciones_detalle d
                WHERE d.eliminado = false
                GROUP BY d.id_suscripcion
            )
            SELECT COUNT(*)                                                         AS total,
                   COUNT(*) FILTER (WHERE s.estado = 'activo')                      AS activas,
                   COUNT(*) FILTER (WHERE s.estado <> 'activo')                     AS otras,
                   COALESCE(ROUND(SUM(det.subtotal / NULLIF(COALESCE(p.meses, 1), 0)) FILTER (WHERE s.estado = 'activo'), 2), 0) AS mrr,
                   COUNT(*) FILTER (WHERE s.estado = 'activo' AND s.proximo_cobro < CURRENT_DATE - 3)  AS atrasadas,
                   COUNT(*) FILTER (WHERE s.estado = 'activo' AND s.proximo_cobro BETWEEN CURRENT_DATE AND CURRENT_DATE + 30) AS cobros_30d,
                   COALESCE(SUM(det.subtotal) FILTER (WHERE s.estado = 'activo' AND s.proximo_cobro BETWEEN CURRENT_DATE AND CURRENT_DATE + 30), 0) AS monto_30d
            FROM suscripciones s
            LEFT JOIN det ON det.id_suscripcion = s.id
            LEFT JOIN suscripcion_periodicidades p ON p.id = s.id_periodicidad
            WHERE s.eliminado = false AND s.id_empresa IN ({$in})")->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Suscripciones activas cuyo próximo cobro ya pasó (más de 3 días de gracia). */
    public function suscripcionesAtrasadas(array $idsControladoras, int $limite = 50): array
    {
        if ($idsControladoras === [] || !$this->tablaExiste('suscripciones')) {
            return [];
        }
        $in = implode(',', array_map('intval', $idsControladoras));
        return $this->db->query("
            SELECT s.id, c.nombre AS cliente, c.identificacion, s.proximo_cobro,
                   (CURRENT_DATE - s.proximo_cobro) AS dias, p.nombre AS periodicidad,
                   (SELECT SUM(d.cantidad * d.precio_unitario) FROM suscripciones_detalle d
                     WHERE d.id_suscripcion = s.id AND d.eliminado = false) AS subtotal
            FROM suscripciones s
            LEFT JOIN clientes c ON c.id = s.id_cliente
            LEFT JOIN suscripcion_periodicidades p ON p.id = s.id_periodicidad
            WHERE s.eliminado = false AND s.id_empresa IN ({$in})
              AND s.estado = 'activo' AND s.proximo_cobro < CURRENT_DATE - 3
            ORDER BY s.proximo_cobro
            LIMIT {$limite}")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Cobros de suscripciones de la plataforma en el rango, por mes y estado. */
    public function pagosSuscripcion(array $idsControladoras, string $desde, string $hasta): array
    {
        if ($idsControladoras === [] || !$this->tablaExiste('suscripciones_pagos')) {
            return [];
        }
        $in = implode(',', array_map('intval', $idsControladoras));
        $st = $this->db->prepare("
            SELECT date_trunc('month', p.fecha_cobro)::date AS periodo, p.estado,
                   COUNT(*) AS n, COALESCE(SUM(p.monto), 0) AS monto
            FROM suscripciones_pagos p
            WHERE p.eliminado = false AND p.id_empresa IN ({$in})
              AND p.fecha_cobro BETWEEN :desde AND :hasta
            GROUP BY 1, 2 ORDER BY 1");
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Pagos reportados por los clientes (comprobante subido) por estado. */
    public function reportesPago(): array
    {
        if (!$this->tablaExiste('suscripciones_reportes_pago')) {
            return [];
        }
        return $this->db->query("
            SELECT estado, COUNT(*) AS n, MIN(created_at) AS mas_antiguo
            FROM suscripciones_reportes_pago
            WHERE created_at >= NOW() - INTERVAL '90 days'
            GROUP BY estado ORDER BY n DESC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Empresas con plan sin costo (regalía) vigente, demo y con vigencia manual vencida. */
    public function empresasCobro(): array
    {
        $regalia = $this->columnaExiste('empresas', 'sin_cobro_suscripcion')
            ? "COUNT(*) FILTER (WHERE sin_cobro_suscripcion = true AND (sin_cobro_hasta IS NULL OR sin_cobro_hasta >= CURRENT_DATE))"
            : '0';
        return $this->db->query("
            SELECT {$regalia} AS regalia,
                   COUNT(*) FILTER (WHERE es_demo = true) AS demo,
                   COUNT(*) FILTER (WHERE COALESCE(es_demo, false) = false) AS pagando_o_por_cobrar
            FROM empresas
            WHERE eliminado = false AND estado = '1'")->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Cobros en línea (Payphone / Nuvei) de todas las empresas en el rango, por estado. */
    public function pasarelas(string $desde, string $hasta): array
    {
        $out = [];
        // Payphone guarda el monto en centavos (INTEGER); Nuvei en dólares (NUMERIC(14,2)).
        $pasarelas = [
            'payphone' => ['tabla' => 'payphone_transacciones', 'monto' => 'ROUND(SUM(t.monto) / 100.0, 2)'],
            'nuvei'    => ['tabla' => 'nuvei_transacciones',    'monto' => 'SUM(t.monto)'],
        ];
        foreach ($pasarelas as $pasarela => $cfg) {
            $tabla = $cfg['tabla'];
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            $st = $this->db->prepare("
                SELECT '{$pasarela}' AS pasarela, COALESCE(t.estado, '(sin estado)') AS estado,
                       COUNT(*) AS n, COALESCE({$cfg['monto']}, 0) AS monto, COUNT(DISTINCT t.id_empresa) AS empresas,
                       string_agg(DISTINCT COALESCE(NULLIF(trim(e.nombre_comercial), ''), e.nombre), ', ') AS empresas_nombres
                FROM {$tabla} t
                LEFT JOIN empresas e ON e.id = t.id_empresa
                WHERE t.eliminado = false AND t.created_at >= :desde AND t.created_at < (CAST(:hasta AS date) + 1)
                GROUP BY 2 ORDER BY 3 DESC");
            $st->execute([':desde' => $desde, ':hasta' => $hasta]);
            $out = array_merge($out, $st->fetchAll(\PDO::FETCH_ASSOC));
        }
        return $out;
    }
}
