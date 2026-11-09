<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\CryptoHelper;
use App\repositories\modulos\CrmMetricaRepository;
use App\Services\InstagramApiService;
use App\Services\LinkedInApiService;

/**
 * CRM fase 3, paso 6: métricas de lo publicado y atribución al CRM
 * (decisiones 7 y 8 de docs/diseno/crm-redes-fase3.md).
 *
 * Cron: a las 24 h, 7 días y 30 días de publicada se toma una foto de las
 * métricas de cada destino (Instagram; LinkedIn solo páginas: las de perfil
 * personal no se pueden leer). Una vez al día, los seguidores de cada cuenta
 * de Instagram. Si la red falla, el corte queda guardado con el error (no se
 * reintenta cada minuto); el siguiente corte trae los datos.
 */
class CrmMetricaService
{
    public function __construct(
        private CrmMetricaRepository $repo,
        private ?InstagramApiService $igFijo = null,   // solo pruebas
        private ?LinkedInApiService $liFijo = null      // solo pruebas
    ) {
    }

    public static function crear(?InstagramApiService $ig = null, ?LinkedInApiService $li = null): self
    {
        return new self(new CrmMetricaRepository(), $ig, $li);
    }

    public function disponible(): bool
    {
        return $this->repo->disponible();
    }

    /** @return array{tomadas:int, sin_datos:int, errores:int} */
    public function tomarCortes(int $limite = 30): array
    {
        $r = ['tomadas' => 0, 'sin_datos' => 0, 'errores' => 0];
        if (!$this->disponible()) {
            return $r;
        }
        foreach ($this->repo->cortesPendientes($limite) as $d) {
            $idEmpresa = (int) $d['id_empresa'];
            try {
                $token = CryptoHelper::desencriptar((string) $d['token_cifrado']);
                if ($d['canal_tipo'] === 'instagram') {
                    [$valores, $crudo] = $this->instagram($d, $token);
                } elseif ($d['canal_tipo'] === 'linkedin_pagina') {
                    $s = ($this->liFijo ?? LinkedInApiService::paraEmpresa($idEmpresa))
                        ->estadisticasPublicacion($token, (string) $d['canal_externo'], (string) $d['id_externo']);
                    $valores = ['alcance' => $s['alcance'], 'vistas' => $s['impresiones'], 'me_gusta' => $s['me_gusta'],
                                'comentarios' => $s['comentarios'], 'compartidos' => $s['compartidos'], 'clics' => $s['clics']];
                    $crudo = $s['crudo'];
                } else {
                    // Perfil personal de LinkedIn: LinkedIn no entrega sus estadísticas.
                    $this->repo->guardar($idEmpresa, (int) $d['id'], (string) $d['corte'], [], ['sin_datos' => 'perfil personal de LinkedIn']);
                    $r['sin_datos']++;
                    continue;
                }
                $this->repo->guardar($idEmpresa, (int) $d['id'], (string) $d['corte'], $valores, $crudo);
                $r['tomadas']++;
            } catch (\Throwable $e) {
                $msg = $e instanceof \RuntimeException || $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Error inesperado';
                if (!$e instanceof \RuntimeException && !$e instanceof \InvalidArgumentException) {
                    \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'tomarCortes', 'id_destino' => $d['id']]);
                }
                $this->repo->guardar($idEmpresa, (int) $d['id'], (string) $d['corte'], [], ['error' => mb_substr($msg, 0, 300)]);
                $r['errores']++;
            }
        }
        return $r;
    }

    /** @return array{0:array, 1:array} valores normalizados y respuesta cruda */
    private function instagram(array $d, string $token): array
    {
        $api = $this->igFijo ?? InstagramApiService::paraEmpresa((int) $d['id_empresa']);
        $m = $api->metricasPublicacion($token, (string) $d['id_externo'], (string) $d['formato']);
        $datos = [];
        try {
            $datos = $api->datosPublicacion($token, (string) $d['id_externo']);
            if (!empty($datos['enlace'])) {
                $this->repo->completarEnlace((int) $d['id'], (string) $datos['enlace']);
            }
        } catch (\RuntimeException $e) {
            // sin datos básicos: se usan solo las métricas
        }
        $valores = [
            'alcance' => $m['reach'] ?? null, 'vistas' => $m['views'] ?? null,
            'me_gusta' => $m['likes'] ?? ($datos['me_gusta'] ?? null), 'comentarios' => $m['comments'] ?? ($datos['comentarios'] ?? null),
            'guardados' => $m['saved'] ?? null, 'compartidos' => $m['shares'] ?? null,
        ];
        return [$valores, $m];
    }

    /** Seguidores de hoy de cada cuenta de Instagram (una vez al día). */
    public function tomarSeguidores(): int
    {
        if (!$this->disponible()) {
            return 0;
        }
        $n = 0;
        foreach ($this->repo->canalesSinSeguidoresHoy() as $c) {
            try {
                $api = $this->igFijo ?? InstagramApiService::paraEmpresa((int) $c['id_empresa']);
                $s = $api->seguidores(CryptoHelper::desencriptar((string) $c['token_cifrado']), (string) ($c['id_externo'] ?? ''));
                if ($s !== null) {
                    $this->repo->guardarSeguidores((int) $c['id_empresa'], (int) $c['id'], $s);
                    $n++;
                }
            } catch (\App\Services\InstagramTokenInvalidoException $e) {
                if (!$this->igFijo) {
                    CrmCanalService::accesoRevocado((int) $c['id'], (int) $c['id_empresa']);
                }
            } catch (\RuntimeException $e) {
                // se intenta mañana
            }
        }
        return $n;
    }

    // ── Para pantalla ────────────────────────────────────────────────────────

    /**
     * Métricas (último corte y todos los cortes) y atribución al CRM de cada
     * destino de una publicación.
     * @param list<array> $destinos filas de CrmPublicacionRepository::destinos()
     */
    public function resultados(array $destinos): array
    {
        $ids = array_map(fn($d) => (int) $d['id'], array_filter($destinos, fn($d) => $d['estado'] === 'publicada'));
        $cortes = [];
        foreach ($this->repo->porDestinos($ids) as $m) {
            $cortes[(int) $m['id_destino']][] = $m;
        }
        $atr = $this->repo->atribucion($ids);
        $out = [];
        foreach ($ids as $id) {
            $lista = $cortes[$id] ?? [];
            $conDatos = array_values(array_filter($lista, fn($m) => !in_array($m['con_error'], [true, 't'], true)));
            $out[$id] = [
                'cortes' => $lista,
                'ultimo' => $conDatos ? end($conDatos) : null,
                'atribucion' => $atr[$id] ?? ['conversaciones' => 0, 'contactos_nuevos' => 0, 'oportunidades' => 0, 'ganadas' => 0, 'valor_ganado' => 0.0],
            ];
        }
        return $out;
    }

    /**
     * Bloque «Redes» de los Reportes del CRM: por red, publicaciones, alcance,
     * interacciones y lo que trajeron al CRM; las publicaciones con mejores
     * resultados; y la evolución de seguidores.
     */
    public function reporte(int $idEmpresa, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        if (!$this->disponible()) {
            return ['disponible' => false];
        }
        $destinos = $this->repo->publicadosEnPeriodo($idEmpresa, $desde, $hasta, $idUsuarioFiltro);
        $res = $this->resultados(array_map(fn($d) => $d + ['estado' => 'publicada'], $destinos));
        $porRed = [];
        $filas = [];
        foreach ($destinos as $d) {
            $red = $d['canal_tipo'] === 'instagram' ? 'Instagram' : 'LinkedIn';
            $x = $res[(int) $d['id']] ?? ['ultimo' => null, 'atribucion' => []];
            $u = $x['ultimo'] ?? [];
            $a = $x['atribucion'];
            $inter = (int) ($u['me_gusta'] ?? 0) + (int) ($u['comentarios'] ?? 0) + (int) ($u['guardados'] ?? 0) + (int) ($u['compartidos'] ?? 0);
            $g = &$porRed[$red];
            $g ??= ['red' => $red, 'publicaciones' => 0, 'alcance' => 0, 'interacciones' => 0, 'conversaciones' => 0,
                    'contactos_nuevos' => 0, 'oportunidades' => 0, 'ganadas' => 0, 'valor_ganado' => 0.0];
            $g['publicaciones']++;
            $g['alcance'] += (int) ($u['alcance'] ?? 0);
            $g['interacciones'] += $inter;
            foreach (['conversaciones', 'contactos_nuevos', 'oportunidades', 'ganadas'] as $k) {
                $g[$k] += (int) ($a[$k] ?? 0);
            }
            $g['valor_ganado'] += (float) ($a['valor_ganado'] ?? 0);
            unset($g);
            $filas[] = [
                'id_publicacion' => (int) $d['id_publicacion'], 'titulo' => $d['titulo'], 'red' => $red, 'canal' => $d['canal'],
                'publicada_at' => $d['publicada_at'], 'enlace' => $d['enlace'], 'alcance' => $u['alcance'] ?? null, 'interacciones' => $inter,
            ] + array_intersect_key($a, array_flip(['conversaciones', 'contactos_nuevos', 'oportunidades', 'valor_ganado']));
        }
        // Las que más trajeron: oportunidades, luego contactos, luego alcance.
        usort($filas, fn($a, $b) => [$b['oportunidades'] ?? 0, $b['contactos_nuevos'] ?? 0, $b['alcance'] ?? 0]
                                 <=> [$a['oportunidades'] ?? 0, $a['contactos_nuevos'] ?? 0, $a['alcance'] ?? 0]);
        return [
            'disponible' => true,
            'por_red' => array_values($porRed),
            'mejores' => array_slice($filas, 0, 10),
            'seguidores' => $this->repo->seguidoresEnPeriodo($idEmpresa, $desde, $hasta),
        ];
    }
}
