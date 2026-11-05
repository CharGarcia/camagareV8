<?php

declare(strict_types=1);

namespace App\repositories;

use App\Helpers\AmbienteReporte;

/**
 * Resumen diario precalculado de Métricas del sistema (`metricas_diarias`, Fase C).
 *
 * Datos DERIVADOS: recalcular un rango borra sus filas y las vuelve a generar desde las
 * tablas de origen (documentos de producción, actividad, sesiones, errores, SRI). No son
 * registros de negocio; por eso el reemplazo es seguro y siempre reconstruible.
 * id_empresa = 0 es la fila global de cada día.
 */
class MetricasDiariasRepository extends BaseRepository
{
    public const TABLA = 'metricas_diarias';

    /** Clave de documento (MetricasSistemaRepository) => columna de metricas_diarias. */
    public const COLUMNA_DOC = [
        'factura'      => 'facturas',
        'nota_credito' => 'notas_credito',
        'nota_debito'  => 'notas_debito',
        'retencion'    => 'retenciones',
        'guia'         => 'guias',
        'liquidacion'  => 'liquidaciones',
        'reembolso'    => 'reembolsos',
        'compra'       => 'compras',
        'recibo'       => 'recibos',
        'proforma'     => 'proformas',
        'pedido'       => 'pedidos',
    ];

    public function __construct()
    {
        parent::__construct(self::TABLA);
    }

    public function tablaDisponible(): bool
    {
        return $this->tablaExiste(self::TABLA);
    }

    /** Candado para que el cron y el relleno manual no recalculen a la vez (se libera al COMMIT). */
    public function bloquear(): void
    {
        $this->db->query("SELECT pg_advisory_xact_lock(hashtext('metricas_diarias'))");
    }

    /** Fotos del momento ya guardadas en filas globales (se conservan al recalcular). */
    public function fotosGuardadas(string $desde, string $hasta): array
    {
        $st = $this->db->prepare("
            SELECT fecha, empresas_activas, usuarios_habilitados
            FROM metricas_diarias
            WHERE id_empresa = 0 AND fecha BETWEEN :desde AND :hasta
              AND (empresas_activas IS NOT NULL OR usuarios_habilitados IS NOT NULL)");
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $out[$r['fecha']] = $r;
        }
        return $out;
    }

    public function borrarRango(string $desde, string $hasta): int
    {
        $st = $this->db->prepare('DELETE FROM metricas_diarias WHERE fecha BETWEEN :desde AND :hasta');
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $st->rowCount();
    }

    /** Inserta las filas por empresa del rango. Devuelve cuántas filas quedaron. */
    public function insertarPorEmpresa(string $desde, string $hasta): int
    {
        $partes = [];
        foreach (MetricasSistemaRepository::DOCUMENTOS_SRI as $clave => [, $tabla, $monto]) {
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            $m = $clave === 'factura' ? "SUM(t.{$monto})" : '0';
            $partes[] = "SELECT t.fecha_emision AS fecha, t.id_empresa, '{$clave}' AS tipo, COUNT(*) AS n, {$m} AS monto,
                    0 AS usuarios, 0 AS horas
                FROM {$tabla} t
                WHERE t.eliminado = false AND " . AmbienteReporte::condicion('t') . "
                  AND lower(t.estado) IN ('autorizado','autorizada')
                  AND t.fecha_emision BETWEEN :desde AND :hasta
                GROUP BY 1, 2";
        }
        foreach (MetricasSistemaRepository::DOCUMENTOS_INTERNOS as $clave => [, $tabla, $colFecha, , $excluir]) {
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            $fecha = $colFecha ? "t.{$colFecha}" : 't.created_at::date';
            $amb   = $this->columnaExiste($tabla, 'tipo_ambiente') ? ' AND ' . AmbienteReporte::condicion('t') : '';
            $lista = "'" . implode("','", $excluir) . "'";
            $partes[] = "SELECT {$fecha} AS fecha, t.id_empresa, '{$clave}' AS tipo, COUNT(*) AS n, 0 AS monto,
                    0 AS usuarios, 0 AS horas
                FROM {$tabla} t
                WHERE t.eliminado = false{$amb}
                  AND lower(COALESCE(t.estado,'')) NOT IN ({$lista})
                  AND {$fecha} BETWEEN :desde AND :hasta
                GROUP BY 1, 2";
        }
        if ($this->tablaExiste('metricas_actividad_usuario')) {
            $partes[] = "SELECT a.fecha, a.id_empresa, 'uso' AS tipo, 0 AS n, 0 AS monto,
                    COUNT(DISTINCT a.id_usuario) AS usuarios, SUM(a.latidos) * 5 / 60.0 AS horas
                FROM metricas_actividad_usuario a
                JOIN usuarios u ON u.id = a.id_usuario AND u.nivel <> 3
                WHERE a.fecha BETWEEN :desde AND :hasta
                GROUP BY 1, 2";
        }
        if ($partes === []) {
            return 0;
        }

        $cols = [];
        $sels = [];
        foreach (self::COLUMNA_DOC as $clave => $col) {
            $cols[] = $col;
            $sels[] = "COALESCE(SUM(x.n) FILTER (WHERE x.tipo = '{$clave}'), 0)";
        }

        $sql = "
            INSERT INTO metricas_diarias (fecha, id_empresa, " . implode(', ', $cols) . ", monto_ventas, usuarios_activos, horas_uso, calculado_at)
            SELECT x.fecha, x.id_empresa, " . implode(', ', $sels) . ",
                   COALESCE(ROUND(SUM(x.monto), 2), 0),
                   COALESCE(SUM(x.usuarios), 0),
                   COALESCE(ROUND(SUM(x.horas), 1), 0),
                   NOW()
            FROM (" . implode("\nUNION ALL\n", $partes) . ") x
            WHERE x.id_empresa IS NOT NULL AND x.id_empresa > 0
            GROUP BY x.fecha, x.id_empresa";
        $st = $this->db->prepare($sql);
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $st->rowCount();
    }

    /** Inserta una fila global (id_empresa = 0) por cada día del rango, aunque esté en cero. */
    public function insertarGlobal(string $desde, string $hasta): int
    {
        $sumas = implode(', ', array_map(static fn ($c) => "COALESCE(SUM(m.{$c}), 0)", self::COLUMNA_DOC));
        $docs  = implode(' + ', array_map(static fn ($c) => "m.{$c}", self::COLUMNA_DOC));

        $errores = $this->tablaExiste('errores_sistema')
            ? "(SELECT COUNT(*) FROM errores_sistema e WHERE e.created_at >= d.dia AND e.created_at < d.dia + 1),
               (SELECT COUNT(*) FROM errores_sistema e WHERE e.created_at >= d.dia AND e.created_at < d.dia + 1 AND e.tipo = 'fatal')"
            : 'NULL, NULL';

        $sql = "
            INSERT INTO metricas_diarias (fecha, id_empresa, " . implode(', ', self::COLUMNA_DOC) . ",
                monto_ventas, usuarios_activos, horas_uso, empresas_con_docs, empresas_con_uso,
                logins, usuarios_login, errores, errores_fatales, sri_autorizados, sri_rechazados, calculado_at)
            SELECT d.dia, 0, {$sumas},
                   COALESCE(SUM(m.monto_ventas), 0),
                   COALESCE(SUM(m.usuarios_activos), 0),
                   COALESCE(SUM(m.horas_uso), 0),
                   COUNT(m.id_empresa) FILTER (WHERE ({$docs}) > 0),
                   COUNT(m.id_empresa) FILTER (WHERE m.usuarios_activos > 0),
                   (SELECT COUNT(*) FROM sesiones_activas s WHERE s.created_at >= d.dia AND s.created_at < d.dia + 1),
                   (SELECT COUNT(DISTINCT s.id_usuario) FROM sesiones_activas s WHERE s.created_at >= d.dia AND s.created_at < d.dia + 1),
                   {$errores},
                   (SELECT COUNT(*) FROM sri_envio_log l WHERE l.created_at >= d.dia AND l.created_at < d.dia + 1
                        AND " . AmbienteReporte::condicion('l') . " AND l.accion IN ('autorizado','autorizada')),
                   (SELECT COUNT(*) FROM sri_envio_log l WHERE l.created_at >= d.dia AND l.created_at < d.dia + 1
                        AND " . AmbienteReporte::condicion('l') . " AND l.accion IN ('devuelta','no_autorizada','error')),
                   NOW()
            FROM (SELECT generate_series(CAST(:desde AS date), CAST(:hasta AS date), interval '1 day')::date AS dia) d
            LEFT JOIN metricas_diarias m ON m.fecha = d.dia AND m.id_empresa > 0
            GROUP BY d.dia";
        $st = $this->db->prepare($sql);
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $st->rowCount();
    }

    public function guardarFoto(string $fecha, ?int $empresasActivas, ?int $usuariosHabilitados): void
    {
        $st = $this->db->prepare('
            UPDATE metricas_diarias SET empresas_activas = :e, usuarios_habilitados = :u
            WHERE fecha = :f AND id_empresa = 0');
        $st->bindValue(':e', $empresasActivas, $empresasActivas === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':u', $usuariosHabilitados, $usuariosHabilitados === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':f', $fecha);
        $st->execute();
    }

    /** Fotos del momento: empresas activas y usuarios habilitados ahora mismo. */
    public function fotoActual(): array
    {
        $sql = "SELECT (SELECT COUNT(*) FROM empresas WHERE eliminado = false AND estado = '1') AS empresas_activas,
                       (SELECT COUNT(*) FROM usuarios WHERE eliminado = false AND estado::text = '1') AS usuarios_habilitados";
        return array_map('intval', $this->db->query($sql)->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    // ───────────────────────────── Lectura (pestaña Tendencias) ─────────────────────────────

    /** Rango cubierto por el resumen (fila global). */
    public function cobertura(): array
    {
        $r = $this->db->query('SELECT MIN(fecha) AS desde, MAX(fecha) AS hasta, COUNT(*) AS dias, MAX(calculado_at) AS calculado
                               FROM metricas_diarias WHERE id_empresa = 0')->fetch(\PDO::FETCH_ASSOC);
        return $r ?: [];
    }

    /** Serie global agregada por día o mes. */
    public function serieGlobal(string $desde, string $hasta, bool $porMes): array
    {
        $trunc = $porMes ? 'month' : 'day';
        $docs  = implode(', ', array_map(static fn ($c) => "SUM({$c}) AS {$c}", self::COLUMNA_DOC));
        $sql = "
            SELECT date_trunc('{$trunc}', fecha)::date AS periodo, {$docs},
                   SUM(monto_ventas) AS monto_ventas, SUM(horas_uso) AS horas_uso,
                   MAX(usuarios_activos) AS usuarios_activos_max, ROUND(AVG(usuarios_activos), 1) AS usuarios_activos_prom,
                   MAX(empresas_con_docs) AS empresas_con_docs_max, MAX(empresas_con_uso) AS empresas_con_uso_max,
                   SUM(logins) AS logins, SUM(errores) AS errores, SUM(errores_fatales) AS errores_fatales,
                   SUM(sri_autorizados) AS sri_autorizados, SUM(sri_rechazados) AS sri_rechazados,
                   MAX(empresas_activas) AS empresas_activas, MAX(usuarios_habilitados) AS usuarios_habilitados
            FROM metricas_diarias
            WHERE id_empresa = 0 AND fecha BETWEEN :desde AND :hasta
            GROUP BY 1 ORDER BY 1";
        $st = $this->db->prepare($sql);
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Totales globales de un rango (para comparar con el periodo anterior). */
    public function totalesGlobales(string $desde, string $hasta): array
    {
        $docs = implode(' + ', self::COLUMNA_DOC);
        $sql = "
            SELECT COALESCE(SUM({$docs}), 0)        AS documentos,
                   COALESCE(SUM(facturas), 0)       AS facturas,
                   COALESCE(SUM(monto_ventas), 0)   AS monto_ventas,
                   COALESCE(SUM(horas_uso), 0)      AS horas_uso,
                   COALESCE(SUM(logins), 0)         AS logins,
                   COALESCE(SUM(errores), 0)        AS errores,
                   COUNT(*)                         AS dias
            FROM metricas_diarias
            WHERE id_empresa = 0 AND fecha BETWEEN :desde AND :hasta";
        $st = $this->db->prepare($sql);
        $st->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Documentos por empresa en el rango frente al periodo anterior de igual largo.
     * Sirve para ver quién crece y quién cae (riesgo de abandono).
     */
    public function variacionPorEmpresa(string $desde, string $hasta, string $desdeAnt, string $hastaAnt): array
    {
        $docs = implode(' + ', array_map(static fn ($c) => "m.{$c}", self::COLUMNA_DOC));
        $sql = "
            SELECT e.id, e.nombre, e.nombre_comercial, e.ruc, e.establecimiento,
                   COALESCE(SUM({$docs}) FILTER (WHERE m.fecha BETWEEN :desde AND :hasta), 0)      AS actual,
                   COALESCE(SUM({$docs}) FILTER (WHERE m.fecha BETWEEN :desde_ant AND :hasta_ant), 0) AS anterior,
                   COALESCE(SUM(m.monto_ventas) FILTER (WHERE m.fecha BETWEEN :desde AND :hasta), 0)       AS ventas_actual,
                   COALESCE(SUM(m.monto_ventas) FILTER (WHERE m.fecha BETWEEN :desde_ant AND :hasta_ant), 0) AS ventas_anterior,
                   COALESCE(SUM(m.horas_uso) FILTER (WHERE m.fecha BETWEEN :desde AND :hasta), 0)       AS horas_actual,
                   COALESCE(SUM(m.horas_uso) FILTER (WHERE m.fecha BETWEEN :desde_ant AND :hasta_ant), 0) AS horas_anterior
            FROM metricas_diarias m
            JOIN empresas e ON e.id = m.id_empresa AND e.eliminado = false
            WHERE m.id_empresa > 0 AND m.fecha BETWEEN :desde_min AND :hasta_max
            GROUP BY e.id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':desde' => $desde, ':hasta' => $hasta,
            ':desde_ant' => $desdeAnt, ':hasta_ant' => $hastaAnt,
            ':desde_min' => min($desde, $desdeAnt), ':hasta_max' => max($hasta, $hastaAnt),
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}
