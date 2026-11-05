<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Cache;
use App\repositories\MetricasDiariasRepository;
use App\repositories\MetricasPlataformaRepository;
use App\repositories\MetricasSistemaRepository;
use App\Rules\MetricasSistemaRules;

/**
 * Arma cada pestaña de Métricas del sistema (solo lectura, nivel 3).
 *
 * Todo se calcula en vivo (Fase A) y se cachea en APCu por sección + rango durante
 * CACHE_TTL segundos, para que varios superadmins o recargas seguidas no repitan las
 * consultas sobre toda la BD. Sin APCu, `Cache` degrada y se consulta cada vez.
 */
class MetricasSistemaService
{
    private const CACHE_TTL = 120;

    /** Segundos máximos por consulta; si una se pasa, se corta en vez de trabar la BD. */
    private const TIEMPO_MAX_CONSULTA = 25;

    /** A partir de este rango (días) las series se agrupan por mes en vez de por día. */
    private const DIAS_SERIE_DIARIA = 92;

    private MetricasSistemaRepository $repo;
    private MetricasSistemaRules $rules;

    public function __construct(?MetricasSistemaRepository $repo = null)
    {
        $this->repo  = $repo ?? new MetricasSistemaRepository();
        $this->rules = new MetricasSistemaRules();
    }

    /**
     * @return array{datos: array, generado: string, cache: bool}
     */
    public function seccion(string $seccion, string $desde, string $hasta, bool $refrescar = false): array
    {
        $this->rules->validar($seccion, $desde, $hasta);

        $clave = "metricas_sistema:{$seccion}:{$desde}:{$hasta}";
        if (!$refrescar) {
            $c = Cache::get($clave);
            if (is_array($c)) {
                return $c + ['cache' => true];
            }
        }

        $porMes = (new \DateTime($desde))->diff(new \DateTime($hasta))->days > self::DIAS_SERIE_DIARIA;

        $this->repo->limitarTiempo(self::TIEMPO_MAX_CONSULTA);
        try {
            $datos = match ($seccion) {
                'resumen'    => $this->resumen($desde, $hasta),
                'usuarios'   => $this->usuarios($desde, $hasta, $porMes),
                'empresas'   => $this->empresas($desde, $hasta),
                'documentos' => $this->documentos($desde, $hasta, $porMes),
                'productos'  => $this->productos($desde, $hasta),
                'sri'        => $this->sri($desde, $hasta),
                'errores'    => $this->errores($desde, $hasta, $porMes),
                'tendencias' => $this->tendencias($desde, $hasta, $porMes),
                'salud'      => $this->salud(),
                'modulos'    => $this->modulos($desde, $hasta),
                'comercial'  => $this->comercial($desde, $hasta),
            };
        } finally {
            $this->repo->quitarLimiteTiempo();
        }

        $out = ['datos' => $datos + ['por_mes' => $porMes], 'generado' => date('d-m-Y H:i:s')];
        Cache::set($clave, $out, self::CACHE_TTL);
        return $out + ['cache' => false];
    }

    /** Indicadores del pie de la tarjeta de control (siempre visibles). */
    private function resumen(string $desde, string $hasta): array
    {
        $u    = $this->repo->usuariosResumen();
        $e    = $this->repo->empresasResumen();
        $docs = $this->repo->documentosResumen($desde, $hasta);
        $fact = array_values(array_filter($docs, static fn ($d) => $d['clave'] === 'factura'))[0] ?? [];
        $totalSri = array_sum(array_map(static fn ($d) => $d['sri'] ? (int) $d['emitidos'] : 0, $docs));
        $mov  = $this->repo->movimientoPorEmpresa($desde, $hasta);
        $act  = $this->repo->actividadDisponible() ? $this->repo->actividadResumen() : null;

        return [
            'actividad'         => $act !== null,
            'empresas_ahora'    => $act['empresas_ahora'] ?? null,
            'empresas_hoy'      => $act['empresas_hoy'] ?? null,
            'conectados'        => $u['conectados'] ?? 0,
            'usuarios_activos'  => $u['activos'] ?? 0,
            'usuarios_hoy'      => $u['activos_hoy'] ?? 0,
            'empresas_activas'  => $e['activas'] ?? 0,
            'empresas_con_mov'  => count($mov),
            'documentos_sri'    => $totalSri,
            'facturas'          => (int) ($fact['emitidos'] ?? 0),
            'ventas'            => round((float) ($fact['monto'] ?? 0), 2),
        ];
    }

    private function usuarios(string $desde, string $hasta, bool $porMes): array
    {
        $conectados = $this->repo->usuariosConectados();
        $actividad  = null;
        if ($this->repo->actividadDisponible()) {
            $empresaDe = $this->repo->empresaActualPorUsuario(array_column($conectados, 'id_usuario'));
            foreach ($conectados as &$c) {
                $c['empresa'] = $empresaDe[(int) $c['id_usuario']] ?? null;
            }
            unset($c);
            $actividad = [
                'desde'   => $this->repo->actividadDesde(),
                'resumen' => $this->repo->actividadResumen(),
                'serie'   => $this->repo->actividadPorPeriodo($desde, $hasta, $porMes),
            ];
        }

        return [
            'actividad'   => $actividad,
            'resumen'     => $this->repo->usuariosResumen(),
            'conectados'  => $conectados,
            'inactivos'   => $this->repo->usuariosInactivos(30),
            'logins'      => $this->repo->loginsPorPeriodo($desde, $hasta, $porMes),
            'por_hora'    => $this->repo->loginsPorHora($desde, $hasta),
            'minutos_conectado' => MetricasSistemaRepository::MINUTOS_CONECTADO,
        ];
    }

    private function empresas(string $desde, string $hasta): array
    {
        $mov    = $this->repo->movimientoPorEmpresa($desde, $hasta);
        $hayAct = $this->repo->actividadDisponible();
        $act    = $hayAct ? $this->repo->actividadPorEmpresa($desde, $hasta) : [];

        // El ranking por documentos suma el uso de cada empresa (usuarios y horas).
        foreach ($mov as &$m) {
            $a = $act[(int) $m['id']] ?? null;
            $m['usuarios_activos'] = $a['usuarios_activos'] ?? null;
            $m['horas']            = $a['horas'] ?? null;
        }
        unset($m);

        // "Sin movimiento" = sin documentos y, si ya hay registro de actividad, sin uso.
        $idsUsadas = array_merge(array_column($mov, 'id'), array_keys($act));

        return [
            'actividad'      => $hayAct ? [
                'desde'      => $this->repo->actividadDesde(),
                'resumen'    => $this->repo->actividadResumen(),
                'conectadas' => $this->repo->empresasConectadasAhora(),
                'uso'        => array_slice(array_values($act), 0, 25),
                'con_uso'    => count($act),
            ] : null,
            'resumen'        => $this->repo->empresasResumen(),
            'con_movimiento' => count($mov),
            'ranking'        => array_slice($mov, 0, 25),
            'sin_movimiento' => $this->repo->empresasSinMovimiento($idsUsadas, $hayAct),
            'altas'          => $this->repo->empresasAltasPorMes(),
            'cupo'           => $this->repo->empresasCupoUsuarios(),
            'vigencia'       => $this->repo->empresasVigencia(),
        ];
    }

    private function documentos(string $desde, string $hasta, bool $porMes): array
    {
        return [
            'tipos' => $this->repo->documentosResumen($desde, $hasta),
            'serie' => $this->repo->documentosPorPeriodo($desde, $hasta, $porMes),
            'pos'   => $this->repo->facturasPosVsNormal($desde, $hasta),
        ];
    }

    private function productos(string $desde, string $hasta): array
    {
        return [
            'resumen'      => $this->repo->productosResumen($desde, $hasta),
            'por_cantidad' => $this->repo->productosMasVendidos($desde, $hasta, 'cantidad'),
            'por_monto'    => $this->repo->productosMasVendidos($desde, $hasta, 'monto'),
        ];
    }

    private function sri(string $desde, string $hasta): array
    {
        $estados = $this->repo->sriUltimosEstados($desde, $hasta);
        $tot = ['autorizado' => 0, 'rechazado' => 0, 'pendiente' => 0];
        $porTipo = [];
        foreach ($estados as $r) {
            $grupo = match ($r['accion']) {
                'autorizado', 'autorizada'                 => 'autorizado',
                'devuelta', 'no_autorizada', 'error'       => 'rechazado',
                default                                    => 'pendiente',
            };
            $n = (int) $r['n'];
            $tot[$grupo] += $n;
            $porTipo[$r['tipo_comprobante']] ??= ['tipo' => $r['tipo_comprobante'], 'autorizado' => 0, 'rechazado' => 0, 'pendiente' => 0];
            $porTipo[$r['tipo_comprobante']][$grupo] += $n;
        }
        $total = array_sum($tot);
        return [
            'totales'      => $tot + ['total' => $total, 'tasa_rechazo' => $total > 0 ? round($tot['rechazado'] * 100 / $total, 1) : 0],
            'por_tipo'     => array_values($porTipo),
            'frecuentes'   => $this->repo->sriErroresFrecuentes($desde, $hasta),
        ];
    }

    /**
     * Tendencias desde el resumen precalculado (metricas_diarias). Compara el rango con el
     * periodo anterior de igual largo y lista las empresas que más crecen y más caen.
     */
    private function tendencias(string $desde, string $hasta, bool $porMes): array
    {
        $diarias = new MetricasDiariasRepository();
        if (!$diarias->tablaDisponible()) {
            return ['disponible' => false];
        }

        $ini  = new \DateTimeImmutable($desde);
        $fin  = new \DateTimeImmutable($hasta);
        $dias = (int) $ini->diff($fin)->days + 1;
        $hastaAnt = $ini->modify('-1 day')->format('Y-m-d');
        $desdeAnt = $ini->modify('-' . $dias . ' days')->format('Y-m-d');

        $actual   = $diarias->totalesGlobales($desde, $hasta);
        $anterior = $diarias->totalesGlobales($desdeAnt, $hastaAnt);
        $variacion = [];
        foreach (['documentos', 'facturas', 'monto_ventas', 'horas_uso', 'logins', 'errores'] as $k) {
            $a = (float) ($actual[$k] ?? 0);
            $b = (float) ($anterior[$k] ?? 0);
            $variacion[$k] = $b > 0 ? round(($a - $b) * 100 / $b, 1) : null;
        }

        $crecen = [];
        $caen   = [];
        foreach ($diarias->variacionPorEmpresa($desde, $hasta, $desdeAnt, $hastaAnt) as $r) {
            $a = (int) $r['actual'];
            $b = (int) $r['anterior'];
            $r['diferencia'] = $a - $b;
            $r['pct'] = $b > 0 ? round(($a - $b) * 100 / $b, 1) : null;
            if ($a > $b) {
                $crecen[] = $r;
            } elseif ($b > $a) {
                $caen[] = $r;
            }
        }
        usort($crecen, static fn ($x, $y) => $y['diferencia'] <=> $x['diferencia']);
        usort($caen, static fn ($x, $y) => $x['diferencia'] <=> $y['diferencia']);

        return [
            'disponible' => true,
            'cobertura'  => $diarias->cobertura(),
            'serie'      => $diarias->serieGlobal($desde, $hasta, $porMes),
            'actual'     => $actual,
            'anterior'   => $anterior + ['desde' => $desdeAnt, 'hasta' => $hastaAnt],
            'variacion'  => $variacion,
            'crecen'     => array_slice($crecen, 0, 15),
            'caen'       => array_slice($caen, 0, 15),
        ];
    }

    /** Estado técnico: base de datos, servidor PHP, cron, SRI atascado y errores recientes. */
    private function salud(): array
    {
        $p   = new MetricasPlataformaRepository();
        $pgs = $p->pgStatStatementsDisponible();
        return [
            'bd'               => $p->baseDatos(),
            'tablas'           => $p->tablasGrandes(),
            'en_curso'         => $p->consultasEnCurso(),
            'pg_stat_statements' => $pgs,
            'pesadas'          => $pgs ? $p->consultasPesadas() : [],
            'sri_atascados'    => $p->sriAtascados(),
            'errores'          => $p->erroresRecientes(),
            'servidor'         => $this->servidor(),
            'cron'             => $this->estadoCron($p->ultimoResumenDiario()),
        ];
    }

    /** Datos del servidor PHP que atiende esta petición (no de la BD). */
    private function servidor(): array
    {
        $raiz  = defined('MVC_ROOT') ? MVC_ROOT : dirname(__DIR__, 2);
        $libre = @disk_free_space($raiz);
        $total = @disk_total_space($raiz);

        $opcache = null;
        if (function_exists('opcache_get_status')) {
            $s = @opcache_get_status(false);
            if (is_array($s)) {
                $st = $s['opcache_statistics'] ?? [];
                $opcache = [
                    'activo'    => (bool) ($s['opcache_enabled'] ?? false),
                    'hit'       => isset($st['opcache_hit_rate']) ? round((float) $st['opcache_hit_rate'], 1) : null,
                    'scripts'   => (int) ($st['num_cached_scripts'] ?? 0),
                    'memoria_usada' => (int) ($s['memory_usage']['used_memory'] ?? 0),
                    'memoria_libre' => (int) ($s['memory_usage']['free_memory'] ?? 0),
                    'reinicio'  => !empty($st['start_time']) ? date('Y-m-d H:i:s', (int) $st['start_time']) : null,
                ];
            }
        }

        $apcu = null;
        if (\App\Helpers\Cache::disponible() && function_exists('apcu_sma_info')) {
            $sma  = @apcu_sma_info(true);
            $info = @apcu_cache_info(true);
            $apcu = [
                'memoria_total' => (int) (($sma['num_seg'] ?? 0) * ($sma['seg_size'] ?? 0)),
                'memoria_libre' => (int) ($sma['avail_mem'] ?? 0),
                'entradas'      => (int) ($info['num_entries'] ?? 0),
                'aciertos'      => (int) ($info['num_hits'] ?? 0),
                'fallos'        => (int) ($info['num_misses'] ?? 0),
            ];
        }

        return [
            'php'          => PHP_VERSION,
            'sapi'         => PHP_SAPI,
            'so'           => PHP_OS_FAMILY . ' ' . php_uname('r'),
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'upload_max'   => (string) ini_get('upload_max_filesize'),
            'disco_libre'  => $libre !== false ? (float) $libre : null,
            'disco_total'  => $total !== false ? (float) $total : null,
            'opcache'      => $opcache,
            'apcu'         => $apcu,
            'hora'         => date('Y-m-d H:i:s'),
            'zona'         => date_default_timezone_get(),
        ];
    }

    /**
     * Señales de que el cron corre. Las marcas diarias son archivos en el temporal del sistema:
     * si el servidor web tiene un /tmp privado (PrivateTmp) no se ven desde aquí, por eso la
     * señal principal es la última escritura del resumen diario en la BD.
     */
    private function estadoCron(?string $ultimoResumen): array
    {
        $tmp = sys_get_temp_dir();
        $marcas = [
            'Métricas diarias'           => 'sistema_metricas_diarias.txt',
            'Devengo de suscripciones'   => 'sistema_devengo_suscripciones.txt',
            'Avisos de firma por vencer' => 'sistema_firmas_avisos_vencimiento.txt',
            'Archivado del chat de soporte' => 'sistema_soporte_archivado.txt',
        ];
        $out = [];
        foreach ($marcas as $etq => $archivo) {
            $ruta = $tmp . DIRECTORY_SEPARATOR . $archivo;
            $out[] = ['tarea' => $etq, 'ultima' => is_file($ruta) ? date('Y-m-d H:i:s', (int) filemtime($ruta)) : null];
        }
        $lock = $tmp . DIRECTORY_SEPARATOR . 'sistema_cron.lock';
        return [
            'ultimo_resumen' => $ultimoResumen,
            'marcas'         => $out,
            'en_ejecucion'   => is_file($lock) ? date('Y-m-d H:i:s', (int) filemtime($lock)) : null,
        ];
    }

    /** Qué submódulos se usan, cuáles nadie abre, y qué se escribe más (log_sistema). */
    private function modulos(string $desde, string $hasta): array
    {
        $p = new MetricasPlataformaRepository();
        if (!$p->usoDisponible()) {
            return ['disponible' => false, 'escrituras' => $p->escriturasPorTabla($desde, $hasta)];
        }
        $subs = $p->usoSubmodulos($desde, $hasta);
        $usados   = array_values(array_filter($subs, static fn ($s) => (int) $s['usuarios_rango'] > 0));
        $sinUso   = array_values(array_filter($subs, static fn ($s) => (int) $s['usuarios_rango'] === 0));
        usort($sinUso, static fn ($a, $b) => (int) $b['con_permiso'] <=> (int) $a['con_permiso']);

        // Uso por módulo (agrupa sus submódulos).
        $porModulo = [];
        foreach ($subs as $s) {
            $m = (string) ($s['modulo'] ?? '(sin módulo)');
            $porModulo[$m] ??= ['modulo' => $m, 'aperturas' => 0, 'submodulos' => 0, 'usados' => 0];
            $porModulo[$m]['aperturas'] += (int) $s['aperturas'];
            $porModulo[$m]['submodulos']++;
            $porModulo[$m]['usados'] += (int) $s['usuarios_rango'] > 0 ? 1 : 0;
        }
        $porModulo = array_values($porModulo);
        usort($porModulo, static fn ($a, $b) => $b['aperturas'] <=> $a['aperturas']);

        return [
            'disponible' => true,
            'desde'      => $p->usoDesde(),
            'total'      => count($subs),
            'usados'     => count($usados),
            'top'        => array_slice($subs, 0, 30),
            'sin_uso'    => array_slice($sinUso, 0, 100),
            'por_modulo' => $porModulo,
            'escrituras' => $p->escriturasPorTabla($desde, $hasta),
        ];
    }

    /** Suscripciones de la plataforma, cobros, pagos reportados y pasarelas. */
    private function comercial(string $desde, string $hasta): array
    {
        $p   = new MetricasPlataformaRepository();
        $ids = $p->idsControladoras();
        return [
            'controladoras' => count($ids),
            'suscripciones' => $p->suscripcionesResumen($ids),
            'atrasadas'     => $p->suscripcionesAtrasadas($ids),
            'pagos'         => $p->pagosSuscripcion($ids, $desde, $hasta),
            'reportes'      => $p->reportesPago(),
            'empresas'      => $p->empresasCobro(),
            'pasarelas'     => $p->pasarelas($desde, $hasta),
        ];
    }

    private function errores(string $desde, string $hasta, bool $porMes): array
    {
        $serie = $this->repo->erroresPorDia($desde, $hasta, $porMes);
        return [
            'total'   => array_sum(array_map('intval', array_column($serie, 'total'))),
            'fatales' => array_sum(array_map('intval', array_column($serie, 'fatales'))),
            'serie'   => $serie,
            'rutas'   => $this->repo->erroresTopRutas($desde, $hasta),
        ];
    }
}
