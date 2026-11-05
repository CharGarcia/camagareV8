<?php

declare(strict_types=1);

namespace App\repositories;

use App\Helpers\AmbienteReporte;

/**
 * Métricas globales de la plataforma (tarjeta /config/metricas-sistema, solo nivel 3).
 *
 * Es una consulta de PLATAFORMA: cruza todas las empresas, así que a propósito NO filtra por
 * `id_empresa` (§4, consulta global). Sí respeta `eliminado = false` en las tablas operativas y
 * cuenta solo documentos de PRODUCCIÓN (`AmbienteReporte`), como todo reporte de solo lectura.
 *
 * Fase A: todo se calcula en vivo sobre las tablas existentes (el Service cachea el resultado).
 * No hay registro de "qué usuario trabajó en qué empresa cada día": `sesiones_activas` no lleva
 * `id_empresa`, por eso la actividad de empresas se mide por documentos emitidos.
 */
class MetricasSistemaRepository extends BaseRepository
{
    /** Minutos sin actividad para dejar de considerar "conectada" una sesión (latido = 5 min). */
    public const MINUTOS_CONECTADO = 15;

    /**
     * Documentos electrónicos (SRI). `emitido` = estado autorizado/autorizada.
     * Clave => [etiqueta, tabla, columna de monto o null].
     */
    public const DOCUMENTOS_SRI = [
        'factura'      => ['Facturas',              'ventas_cabecera',            'importe_total'],
        'nota_credito' => ['Notas de crédito',      'notas_credito_cabecera',     'importe_total'],
        'nota_debito'  => ['Notas de débito',       'nota_debito_cabecera',       'importe_total'],
        'retencion'    => ['Retenciones',           'retencion_compra_cabecera',  null],
        'guia'         => ['Guías de remisión',     'guias_remision_cabecera',    null],
        'liquidacion'  => ['Liquidaciones de compra', 'liquidaciones_cabecera',   'importe_total'],
        'reembolso'    => ['Facturas de reembolso', 'factura_reembolso_cabecera', 'importe_total'],
    ];

    /**
     * Documentos internos (no van al SRI). Clave => [etiqueta, tabla, columna fecha, monto, estados que NO cuentan].
     */
    public const DOCUMENTOS_INTERNOS = [
        'compra'   => ['Compras registradas', 'compras_cabecera',       'fecha_emision', 'importe_total', ['anulado', 'anulada']],
        'recibo'   => ['Recibos de venta',    'recibos_venta_cabecera', 'fecha_emision', 'importe_total', ['anulado', 'anulada']],
        'proforma' => ['Proformas',           'proformas_cabecera',     'fecha_emision', 'importe_total', ['anulado', 'anulada']],
        'pedido'   => ['Pedidos',             'pedidos_cabecera',       null,            null,            ['anulado', 'anulada']],
    ];

    public function __construct()
    {
        parent::__construct('empresas');
    }

    // ───────────────────────────── Usuarios ─────────────────────────────

    public function usuariosResumen(): array
    {
        $min = self::MINUTOS_CONECTADO;
        $sql = "
            WITH ult AS (
                SELECT id_usuario, MAX(ultima_actividad) AS ultima
                FROM sesiones_activas
                GROUP BY id_usuario
            )
            SELECT
                COUNT(*)                                                         AS total,
                COUNT(*) FILTER (WHERE u.estado::text = '1')                     AS activos,
                COUNT(*) FILTER (WHERE u.estado::text <> '1')                    AS inactivos,
                COUNT(*) FILTER (WHERE u.nivel = 3)                              AS nivel3,
                COUNT(*) FILTER (WHERE u.nivel = 2)                              AS nivel2,
                COUNT(*) FILTER (WHERE u.nivel = 1)                              AS nivel1,
                COUNT(*) FILTER (WHERE ult.ultima >= CURRENT_DATE)               AS activos_hoy,
                COUNT(*) FILTER (WHERE ult.ultima >= NOW() - INTERVAL '7 days')  AS activos_7d,
                COUNT(*) FILTER (WHERE ult.ultima >= NOW() - INTERVAL '30 days') AS activos_30d,
                COUNT(*) FILTER (WHERE u.estado::text = '1' AND ult.ultima < NOW() - INTERVAL '30 days') AS sin_ingreso_30d,
                COUNT(*) FILTER (WHERE u.estado::text = '1' AND ult.ultima < NOW() - INTERVAL '90 days') AS sin_ingreso_90d,
                COUNT(*) FILTER (WHERE u.estado::text = '1' AND ult.ultima IS NULL)                     AS nunca
            FROM usuarios u
            LEFT JOIN ult ON ult.id_usuario = u.id
            WHERE u.eliminado = false";
        $r = $this->db->query($sql)->fetch(\PDO::FETCH_ASSOC) ?: [];

        $sqlCon = "
            SELECT COUNT(DISTINCT s.id_usuario)                                       AS conectados,
                   COUNT(DISTINCT s.id_usuario) FILTER (WHERE COALESCE(s.canal,'web') = 'web')   AS web,
                   COUNT(DISTINCT s.id_usuario) FILTER (WHERE s.canal = 'movil')     AS movil
            FROM sesiones_activas s
            JOIN usuarios u ON u.id = s.id_usuario AND u.eliminado = false
            WHERE s.activa = true
              AND s.ultima_actividad > NOW() - INTERVAL '{$min} minutes'";
        $c = $this->db->query($sqlCon)->fetch(\PDO::FETCH_ASSOC) ?: [];

        return array_map('intval', $r + $c);
    }

    /** Usuarios con una sesión con actividad en los últimos MINUTOS_CONECTADO minutos. */
    public function usuariosConectados(int $limite = 200): array
    {
        $min = self::MINUTOS_CONECTADO;
        $sql = "
            SELECT DISTINCT ON (s.id_usuario)
                   s.id_usuario, u.nombre, u.nivel, COALESCE(s.canal, 'web') AS canal, s.ip, s.user_agent,
                   s.created_at AS inicio, s.ultima_actividad,
                   (SELECT COUNT(*) FROM empresa_asignada ea WHERE ea.id_usuario = s.id_usuario) AS empresas
            FROM sesiones_activas s
            JOIN usuarios u ON u.id = s.id_usuario AND u.eliminado = false
            WHERE s.activa = true
              AND s.ultima_actividad > NOW() - INTERVAL '{$min} minutes'
            ORDER BY s.id_usuario, s.ultima_actividad DESC";
        $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        usort($rows, static fn ($a, $b) => strcmp((string) $b['ultima_actividad'], (string) $a['ultima_actividad']));
        return array_slice($rows, 0, $limite);
    }

    /** Usuarios activos (estado 1) que no ingresan hace más de N días o que nunca ingresaron. */
    public function usuariosInactivos(int $dias = 30, int $limite = 100): array
    {
        $sql = "
            WITH ult AS (
                SELECT id_usuario, MAX(ultima_actividad) AS ultima
                FROM sesiones_activas GROUP BY id_usuario
            )
            SELECT u.id, u.nombre, u.nivel, u.mail, ult.ultima,
                   (SELECT COUNT(*) FROM empresa_asignada ea WHERE ea.id_usuario = u.id) AS empresas
            FROM usuarios u
            LEFT JOIN ult ON ult.id_usuario = u.id
            WHERE u.eliminado = false
              AND u.estado::text = '1'
              AND (ult.ultima IS NULL OR ult.ultima < NOW() - make_interval(days => CAST(:dias AS int)))
            ORDER BY ult.ultima ASC NULLS FIRST, u.nombre
            LIMIT :lim";
        $st = $this->db->prepare($sql);
        $st->bindValue(':dias', (string) $dias);
        $st->bindValue(':lim', $limite, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Inicios de sesión por día (o por mes si $porMes) en el rango. */
    public function loginsPorPeriodo(string $desde, string $hasta, bool $porMes): array
    {
        $trunc = $porMes ? 'month' : 'day';
        $sql = "
            SELECT date_trunc('{$trunc}', created_at)::date AS periodo,
                   COUNT(*)                                       AS logins,
                   COUNT(DISTINCT id_usuario)                     AS usuarios,
                   COUNT(*) FILTER (WHERE canal = 'movil')        AS movil
            FROM sesiones_activas
            WHERE created_at >= :desde AND created_at < (:hasta::date + 1)
            GROUP BY 1 ORDER BY 1";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    /** Inicios de sesión agrupados por hora del día (0-23). */
    public function loginsPorHora(string $desde, string $hasta): array
    {
        $sql = "
            SELECT EXTRACT(HOUR FROM created_at)::int AS hora, COUNT(*) AS logins
            FROM sesiones_activas
            WHERE created_at >= :desde AND created_at < (:hasta::date + 1)
            GROUP BY 1 ORDER BY 1";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    // ──────────────── Actividad por empresa (Fase B: metricas_actividad_usuario) ────────────────
    // Los superadmins (nivel 3) entran a cualquier empresa para dar soporte: no cuentan como
    // actividad de la empresa, para no inflar "empresas conectadas".

    public function actividadDisponible(): bool
    {
        return $this->tablaExiste('metricas_actividad_usuario');
    }

    /** Fecha desde la que hay registro de actividad (o null si aún no hay filas). */
    public function actividadDesde(): ?string
    {
        $v = $this->db->query('SELECT MIN(fecha) FROM metricas_actividad_usuario')->fetchColumn();
        return $v ? (string) $v : null;
    }

    public function actividadResumen(): array
    {
        $min = self::MINUTOS_CONECTADO;
        $sql = "
            SELECT COUNT(DISTINCT a.id_empresa) FILTER (WHERE a.ultima_actividad > NOW() - INTERVAL '{$min} minutes') AS empresas_ahora,
                   COUNT(DISTINCT a.id_empresa) FILTER (WHERE a.fecha = CURRENT_DATE)       AS empresas_hoy,
                   COUNT(DISTINCT a.id_empresa) FILTER (WHERE a.fecha >= CURRENT_DATE - 6)  AS empresas_7d,
                   COUNT(DISTINCT a.id_empresa)                                             AS empresas_30d,
                   COUNT(DISTINCT a.id_usuario) FILTER (WHERE a.fecha = CURRENT_DATE)       AS usuarios_hoy,
                   COUNT(DISTINCT a.id_usuario) FILTER (WHERE a.fecha >= CURRENT_DATE - 6)  AS usuarios_7d,
                   COUNT(DISTINCT a.id_usuario)                                             AS usuarios_30d
            FROM metricas_actividad_usuario a
            JOIN usuarios u ON u.id = a.id_usuario AND u.nivel <> 3
            WHERE a.fecha >= CURRENT_DATE - 29";
        return array_map('intval', $this->db->query($sql)->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    /** Usuarios y empresas distintas + horas de uso estimadas por día/mes. */
    public function actividadPorPeriodo(string $desde, string $hasta, bool $porMes): array
    {
        $trunc = $porMes ? 'month' : 'day';
        $sql = "
            SELECT date_trunc('{$trunc}', a.fecha)::date        AS periodo,
                   COUNT(DISTINCT a.id_usuario)                 AS usuarios,
                   COUNT(DISTINCT a.id_empresa)                 AS empresas,
                   ROUND(SUM(a.latidos) * 5 / 60.0, 1)          AS horas
            FROM metricas_actividad_usuario a
            JOIN usuarios u ON u.id = a.id_usuario AND u.nivel <> 3
            WHERE a.fecha BETWEEN :desde AND :hasta
            GROUP BY 1 ORDER BY 1";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    /** Actividad de usuarios por empresa en el rango, indexada por id_empresa. */
    public function actividadPorEmpresa(string $desde, string $hasta): array
    {
        $sql = "
            SELECT a.id_empresa, e.nombre, e.nombre_comercial, e.ruc, e.establecimiento, e.es_demo,
                   COUNT(DISTINCT a.id_usuario)          AS usuarios_activos,
                   COUNT(DISTINCT a.fecha)               AS dias_activos,
                   ROUND(SUM(a.latidos) * 5 / 60.0, 1)   AS horas,
                   MAX(a.ultima_actividad)               AS ultima_actividad
            FROM metricas_actividad_usuario a
            JOIN usuarios u ON u.id = a.id_usuario AND u.nivel <> 3
            JOIN empresas e ON e.id = a.id_empresa AND e.eliminado = false
            WHERE a.fecha BETWEEN :desde AND :hasta
            GROUP BY a.id_empresa, e.id
            ORDER BY horas DESC";
        $out = [];
        foreach ($this->consultarRango($sql, $desde, $hasta) as $r) {
            $out[(int) $r['id_empresa']] = $r;
        }
        return $out;
    }

    /** Empresas con algún usuario activo en los últimos MINUTOS_CONECTADO minutos. */
    public function empresasConectadasAhora(int $limite = 200): array
    {
        $min = self::MINUTOS_CONECTADO;
        $sql = "
            SELECT e.id, e.nombre, e.nombre_comercial, e.ruc, e.establecimiento,
                   COUNT(DISTINCT a.id_usuario)                                   AS usuarios,
                   COUNT(DISTINCT a.id_usuario) FILTER (WHERE a.canal = 'movil') AS movil,
                   MAX(a.ultima_actividad)                                        AS ultima_actividad,
                   string_agg(DISTINCT u.nombre, ', ')                            AS nombres
            FROM metricas_actividad_usuario a
            JOIN usuarios u ON u.id = a.id_usuario AND u.nivel <> 3
            JOIN empresas e ON e.id = a.id_empresa
            WHERE a.fecha >= CURRENT_DATE - 1
              AND a.ultima_actividad > NOW() - INTERVAL '{$min} minutes'
            GROUP BY e.id
            ORDER BY usuarios DESC, ultima_actividad DESC
            LIMIT {$limite}";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Última empresa en la que trabajó hoy cada usuario (id_usuario => nombre de empresa). */
    public function empresaActualPorUsuario(array $idsUsuario): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsUsuario))));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', $ids);
        $sql = "
            SELECT DISTINCT ON (a.id_usuario) a.id_usuario, COALESCE(NULLIF(e.nombre_comercial, ''), e.nombre) AS empresa
            FROM metricas_actividad_usuario a
            JOIN empresas e ON e.id = a.id_empresa
            WHERE a.fecha >= CURRENT_DATE - 1 AND a.id_usuario IN ({$in})
            ORDER BY a.id_usuario, a.ultima_actividad DESC";
        $out = [];
        foreach ($this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_usuario']] = $r['empresa'];
        }
        return $out;
    }

    // ───────────────────────────── Empresas ─────────────────────────────

    public function empresasResumen(): array
    {
        $sql = "
            SELECT COUNT(*)                                                                  AS total,
                   COUNT(*) FILTER (WHERE estado = '1')                                      AS activas,
                   COUNT(*) FILTER (WHERE estado <> '1')                                     AS inactivas,
                   COUNT(*) FILTER (WHERE es_demo = true)                                    AS demo,
                   COUNT(DISTINCT ruc)                                                       AS rucs,
                   COUNT(DISTINCT ruc) FILTER (WHERE estado = '1')                           AS rucs_activos,
                   COUNT(*) FILTER (WHERE tipo_ambiente::text = '2' AND estado = '1')        AS en_produccion,
                   COUNT(*) FILTER (WHERE estado = '1' AND periodo_vigencia_hasta < CURRENT_DATE)  AS vencidas,
                   COUNT(*) FILTER (WHERE estado = '1' AND periodo_vigencia_hasta >= CURRENT_DATE
                                      AND periodo_vigencia_hasta < CURRENT_DATE + 30)        AS por_vencer
            FROM empresas
            WHERE eliminado = false";
        return array_map('intval', $this->db->query($sql)->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Movimiento por empresa en el rango: documentos de producción + total vendido.
     * @return array<int, array> fila por empresa con movimiento
     */
    public function movimientoPorEmpresa(string $desde, string $hasta): array
    {
        $partes = [];
        foreach (self::DOCUMENTOS_SRI as $clave => [, $tabla, $monto]) {
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            $m = ($clave === 'factura' && $monto) ? "SUM(t.{$monto})" : '0';
            $partes[] = "SELECT t.id_empresa, COUNT(*) AS docs, {$m} AS ventas, "
                . ($clave === 'factura' ? 'COUNT(*)' : '0') . " AS facturas
                FROM {$tabla} t
                WHERE t.eliminado = false AND " . AmbienteReporte::condicion('t') . "
                  AND lower(t.estado) IN ('autorizado','autorizada')
                  AND t.fecha_emision BETWEEN :desde AND :hasta
                GROUP BY t.id_empresa";
        }
        if ($this->tablaExiste('compras_cabecera')) {
            $partes[] = "SELECT t.id_empresa, COUNT(*) AS docs, 0 AS ventas, 0 AS facturas
                FROM compras_cabecera t
                WHERE t.eliminado = false AND " . AmbienteReporte::condicion('t') . "
                  AND lower(COALESCE(t.estado,'')) NOT IN ('anulado','anulada')
                  AND t.fecha_emision BETWEEN :desde AND :hasta
                GROUP BY t.id_empresa";
        }
        if ($partes === []) {
            return [];
        }

        $sql = "
            WITH mov AS (" . implode("\nUNION ALL\n", $partes) . ")
            SELECT e.id, e.nombre, e.nombre_comercial, e.ruc, e.establecimiento, e.es_demo,
                   SUM(mov.docs)::int        AS documentos,
                   SUM(mov.facturas)::int    AS facturas,
                   ROUND(SUM(mov.ventas), 2) AS ventas
            FROM mov
            JOIN empresas e ON e.id = mov.id_empresa AND e.eliminado = false
            GROUP BY e.id
            ORDER BY documentos DESC, ventas DESC";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    /** Empresas activas (no demo) sin ningún documento de producción en el rango. */
    public function empresasSinMovimiento(array $idsConMovimiento, bool $conActividad = false, int $limite = 200): array
    {
        $ultimoUso = $conActividad
            ? '(SELECT MAX(a.ultima_actividad) FROM metricas_actividad_usuario a WHERE a.id_empresa = e.id)'
            : 'NULL::timestamp';
        $sql = "
            SELECT e.id, e.nombre, e.ruc, e.establecimiento, e.periodo_vigencia_hasta, e.created_at,
                   (SELECT COUNT(*) FROM empresa_asignada ea WHERE ea.id_empresa = e.id) AS usuarios,
                   {$ultimoUso} AS ultimo_uso
            FROM empresas e
            WHERE e.eliminado = false AND e.estado = '1' AND COALESCE(e.es_demo, false) = false
            ORDER BY e.nombre";
        $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $set  = array_flip(array_map('intval', $idsConMovimiento));
        $rows = array_values(array_filter($rows, static fn ($r) => !isset($set[(int) $r['id']])));
        return array_slice($rows, 0, $limite);
    }

    /** Empresas creadas por mes en los últimos 12 meses. */
    public function empresasAltasPorMes(): array
    {
        $sql = "
            SELECT date_trunc('month', created_at)::date AS periodo, COUNT(*) AS altas
            FROM empresas
            WHERE eliminado = false AND created_at >= date_trunc('month', CURRENT_DATE) - INTERVAL '11 months'
            GROUP BY 1 ORDER BY 1";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Uso del cupo de usuarios (`max_usuarios`) — las que están al 80 % o más. */
    public function empresasCupoUsuarios(int $limite = 50): array
    {
        $sql = "
            SELECT e.id, e.nombre, e.ruc, e.establecimiento, e.max_usuarios, COUNT(ea.id) AS usuarios
            FROM empresas e
            LEFT JOIN empresa_asignada ea ON ea.id_empresa = e.id
            WHERE e.eliminado = false AND e.estado = '1' AND COALESCE(e.max_usuarios, 0) > 0
            GROUP BY e.id
            HAVING COUNT(ea.id) >= e.max_usuarios * 0.8
            ORDER BY COUNT(ea.id)::numeric / e.max_usuarios DESC, e.nombre
            LIMIT :lim";
        $st = $this->db->prepare($sql);
        $st->bindValue(':lim', $limite, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Empresas activas con vigencia vencida o por vencer en 30 días. */
    public function empresasVigencia(int $limite = 100): array
    {
        $sql = "
            SELECT e.id, e.nombre, e.ruc, e.establecimiento, e.periodo_vigencia_hasta,
                   (e.periodo_vigencia_hasta - CURRENT_DATE) AS dias
            FROM empresas e
            WHERE e.eliminado = false AND e.estado = '1' AND COALESCE(e.es_demo, false) = false
              AND e.periodo_vigencia_hasta IS NOT NULL
              AND e.periodo_vigencia_hasta < CURRENT_DATE + 30
            ORDER BY e.periodo_vigencia_hasta
            LIMIT :lim";
        $st = $this->db->prepare($sql);
        $st->bindValue(':lim', $limite, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ───────────────────────────── Documentos ─────────────────────────────

    /** Conteo por tipo de documento en el rango (producción). */
    public function documentosResumen(string $desde, string $hasta): array
    {
        $out = [];
        foreach (self::DOCUMENTOS_SRI as $clave => [$etiqueta, $tabla, $monto]) {
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            $m = $monto ? "COALESCE(SUM(t.{$monto}) FILTER (WHERE lower(t.estado) IN ('autorizado','autorizada')), 0)" : 'NULL';
            $sql = "
                SELECT COUNT(*) FILTER (WHERE lower(t.estado) IN ('autorizado','autorizada')) AS emitidos,
                       COUNT(*) FILTER (WHERE lower(t.estado) IN ('anulado','anulada'))       AS anulados,
                       COUNT(*) FILTER (WHERE lower(COALESCE(t.estado,'')) NOT IN ('autorizado','autorizada','anulado','anulada')) AS pendientes,
                       COUNT(DISTINCT t.id_empresa) FILTER (WHERE lower(t.estado) IN ('autorizado','autorizada')) AS empresas,
                       {$m} AS monto
                FROM {$tabla} t
                WHERE t.eliminado = false AND " . AmbienteReporte::condicion('t') . "
                  AND t.fecha_emision BETWEEN :desde AND :hasta";
            $r = $this->consultarRango($sql, $desde, $hasta)[0] ?? [];
            $out[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'sri' => true] + $r;
        }

        foreach (self::DOCUMENTOS_INTERNOS as $clave => [$etiqueta, $tabla, $colFecha, $monto, $excluir]) {
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            $fecha = $colFecha ? "t.{$colFecha}" : 't.created_at::date';
            $amb   = $this->columnaExiste($tabla, 'tipo_ambiente') ? ' AND ' . AmbienteReporte::condicion('t') : '';
            $lista = "'" . implode("','", $excluir) . "'";
            $m     = $monto ? "COALESCE(SUM(t.{$monto}) FILTER (WHERE lower(COALESCE(t.estado,'')) NOT IN ({$lista})), 0)" : 'NULL';
            $sql = "
                SELECT COUNT(*) FILTER (WHERE lower(COALESCE(t.estado,'')) NOT IN ({$lista})) AS emitidos,
                       COUNT(*) FILTER (WHERE lower(COALESCE(t.estado,'')) IN ({$lista}))     AS anulados,
                       0 AS pendientes,
                       COUNT(DISTINCT t.id_empresa) FILTER (WHERE lower(COALESCE(t.estado,'')) NOT IN ({$lista})) AS empresas,
                       {$m} AS monto
                FROM {$tabla} t
                WHERE t.eliminado = false{$amb}
                  AND {$fecha} BETWEEN :desde AND :hasta";
            $r = $this->consultarRango($sql, $desde, $hasta)[0] ?? [];
            $out[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'sri' => false] + $r;
        }
        return $out;
    }

    /** Facturas, NC y retenciones autorizadas por día/mes (serie para el gráfico). */
    public function documentosPorPeriodo(string $desde, string $hasta, bool $porMes): array
    {
        $trunc  = $porMes ? 'month' : 'day';
        $partes = [];
        foreach (['factura', 'nota_credito', 'retencion', 'guia'] as $clave) {
            $tabla = self::DOCUMENTOS_SRI[$clave][1];
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            $partes[] = "SELECT '{$clave}' AS tipo, date_trunc('{$trunc}', t.fecha_emision)::date AS periodo, COUNT(*) AS n
                FROM {$tabla} t
                WHERE t.eliminado = false AND " . AmbienteReporte::condicion('t') . "
                  AND lower(t.estado) IN ('autorizado','autorizada')
                  AND t.fecha_emision BETWEEN :desde AND :hasta
                GROUP BY 2";
        }
        if ($partes === []) {
            return [];
        }
        return $this->consultarRango(implode("\nUNION ALL\n", $partes) . ' ORDER BY periodo', $desde, $hasta);
    }

    /** Facturas del POS (con caja) frente a facturas normales, autorizadas en el rango. */
    public function facturasPosVsNormal(string $desde, string $hasta): array
    {
        $sql = "
            SELECT COUNT(*) FILTER (WHERE v.id_caja_sesion IS NOT NULL) AS pos,
                   COUNT(*) FILTER (WHERE v.id_caja_sesion IS NULL)     AS normal,
                   COALESCE(SUM(v.importe_total) FILTER (WHERE v.id_caja_sesion IS NOT NULL), 0) AS monto_pos,
                   COALESCE(SUM(v.importe_total) FILTER (WHERE v.id_caja_sesion IS NULL), 0)     AS monto_normal
            FROM ventas_cabecera v
            WHERE v.eliminado = false AND " . AmbienteReporte::condicion('v') . "
              AND lower(v.estado) IN ('autorizado','autorizada')
              AND v.fecha_emision BETWEEN :desde AND :hasta";
        return $this->consultarRango($sql, $desde, $hasta)[0] ?? [];
    }

    // ───────────────────────────── Productos ─────────────────────────────

    /**
     * Productos más vendidos en todo el sistema (facturas autorizadas de producción).
     * Cada empresa tiene su propio catálogo, así que se agrupa por la descripción normalizada.
     */
    public function productosMasVendidos(string $desde, string $hasta, string $orden, int $limite = 25): array
    {
        $ordenSql = $orden === 'monto' ? 'monto DESC, cantidad DESC' : 'cantidad DESC, monto DESC';
        $sql = "
            SELECT upper(trim(regexp_replace(d.descripcion, '\\s+', ' ', 'g'))) AS producto,
                   ROUND(SUM(d.cantidad), 2)                  AS cantidad,
                   ROUND(SUM(d.precio_total_sin_impuesto), 2) AS monto,
                   COUNT(DISTINCT v.id_empresa)               AS empresas,
                   COUNT(DISTINCT v.id)                       AS facturas
            FROM ventas_detalle d
            JOIN ventas_cabecera v ON v.id = d.id_venta
            WHERE v.eliminado = false AND " . AmbienteReporte::condicion('v') . "
              AND lower(v.estado) IN ('autorizado','autorizada')
              AND v.fecha_emision BETWEEN :desde AND :hasta
            GROUP BY 1
            ORDER BY {$ordenSql}
            LIMIT {$limite}";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    public function productosResumen(string $desde, string $hasta): array
    {
        $sql = "
            SELECT COUNT(*)                                                          AS lineas,
                   COUNT(DISTINCT upper(trim(d.descripcion)))                       AS distintos,
                   COALESCE(ROUND(SUM(d.cantidad), 2), 0)                           AS unidades,
                   COALESCE(ROUND(SUM(d.precio_total_sin_impuesto), 2), 0)          AS monto
            FROM ventas_detalle d
            JOIN ventas_cabecera v ON v.id = d.id_venta
            WHERE v.eliminado = false AND " . AmbienteReporte::condicion('v') . "
              AND lower(v.estado) IN ('autorizado','autorizada')
              AND v.fecha_emision BETWEEN :desde AND :hasta";
        return $this->consultarRango($sql, $desde, $hasta)[0] ?? [];
    }

    // ───────────────────────────── SRI ─────────────────────────────

    /** Último estado de cada comprobante enviado al SRI en el rango (producción). */
    public function sriUltimosEstados(string $desde, string $hasta): array
    {
        $sql = "
            WITH ult AS (
                SELECT DISTINCT ON (l.tipo_comprobante, l.id_comprobante)
                       l.tipo_comprobante, l.accion, l.estado_sri
                FROM sri_envio_log l
                WHERE l.created_at >= :desde AND l.created_at < (:hasta::date + 1)
                  AND " . AmbienteReporte::condicion('l') . "
                  AND l.accion NOT IN ('aviso_email')
                ORDER BY l.tipo_comprobante, l.id_comprobante, l.id DESC
            )
            SELECT tipo_comprobante, accion, COUNT(*) AS n
            FROM ult GROUP BY 1, 2 ORDER BY 1, 3 DESC";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    /** Mensajes de rechazo/error más frecuentes del SRI en el rango. */
    public function sriErroresFrecuentes(string $desde, string $hasta, int $limite = 15): array
    {
        $sql = "
            SELECT l.tipo_comprobante, left(COALESCE(NULLIF(trim(l.mensaje), ''), '(sin mensaje)'), 220) AS mensaje,
                   COUNT(*) AS n, COUNT(DISTINCT l.id_empresa) AS empresas, MAX(l.created_at) AS ultimo
            FROM sri_envio_log l
            WHERE l.created_at >= :desde AND l.created_at < (:hasta::date + 1)
              AND " . AmbienteReporte::condicion('l') . "
              AND l.accion IN ('devuelta','no_autorizada','error','en_procesamiento')
            GROUP BY 1, 2
            ORDER BY n DESC
            LIMIT {$limite}";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    // ───────────────────────────── Errores técnicos ─────────────────────────────

    public function erroresPorDia(string $desde, string $hasta, bool $porMes): array
    {
        if (!$this->tablaExiste('errores_sistema')) {
            return [];
        }
        $trunc = $porMes ? 'month' : 'day';
        $sql = "
            SELECT date_trunc('{$trunc}', created_at)::date AS periodo,
                   COUNT(*) AS total,
                   COUNT(*) FILTER (WHERE tipo = 'fatal') AS fatales
            FROM errores_sistema
            WHERE created_at >= :desde AND created_at < (:hasta::date + 1)
            GROUP BY 1 ORDER BY 1";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    public function erroresTopRutas(string $desde, string $hasta, int $limite = 15): array
    {
        if (!$this->tablaExiste('errores_sistema')) {
            return [];
        }
        $sql = "
            SELECT COALESCE(NULLIF(ruta, ''), '(sin ruta)') AS ruta,
                   COUNT(*) AS n,
                   COUNT(DISTINCT id_usuario) AS usuarios,
                   COUNT(DISTINCT id_empresa) AS empresas,
                   MAX(created_at) AS ultimo,
                   left((array_agg(mensaje ORDER BY created_at DESC))[1], 200) AS ultimo_mensaje
            FROM errores_sistema
            WHERE created_at >= :desde AND created_at < (:hasta::date + 1)
            GROUP BY 1 ORDER BY n DESC LIMIT {$limite}";
        return $this->consultarRango($sql, $desde, $hasta);
    }

    // ───────────────────────────── Helpers ─────────────────────────────

    /** Ejecuta una consulta con :desde / :hasta (Y-m-d). */
    private function consultarRango(string $sql, string $desde, string $hasta): array
    {
        $st = $this->db->prepare($sql);
        $st->bindValue(':desde', $desde);
        $st->bindValue(':hasta', $hasta);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Tope de tiempo por consulta: un dashboard no debe trabar la BD de producción. */
    public function limitarTiempo(int $segundos): void
    {
        $this->db->exec("SET statement_timeout = '" . max(1, $segundos) . "s'");
    }

    public function quitarLimiteTiempo(): void
    {
        $this->db->exec('SET statement_timeout = 0');
    }
}
