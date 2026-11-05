<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\MetricasDiariasRepository;
use App\Rules\RangoFechasRules;

/**
 * Calcula el resumen diario de Métricas del sistema (`metricas_diarias`, Fase C).
 *
 * - Cron (`cron_runner.php`, una vez al día): recalcula los últimos DIAS_RECALCULO días,
 *   para absorber documentos que se autorizan o anulan con atraso.
 * - Relleno del historial: `php app/cron/metricas_rellenar.php DESDE HASTA`.
 *
 * Cada tramo de hasta TRAMO_DIAS días va en su propia transacción, bajo un candado
 * (pg_advisory_xact_lock): si falla, se revierte ese tramo completo y el resto sigue intacto.
 */
class MetricasDiariasService
{
    public const DIAS_RECALCULO = 3;
    private const TRAMO_DIAS = 31;

    private MetricasDiariasRepository $repo;

    public function __construct(?MetricasDiariasRepository $repo = null)
    {
        $this->repo = $repo ?? new MetricasDiariasRepository();
    }

    public function disponible(): bool
    {
        return $this->repo->tablaDisponible();
    }

    /** Paso diario del cron: recalcula de (hoy - DIAS_RECALCULO) a ayer. */
    public function ejecutarDiario(): array
    {
        $hasta = date('Y-m-d', strtotime('-1 day'));
        $desde = date('Y-m-d', strtotime('-' . self::DIAS_RECALCULO . ' days'));
        return $this->recalcular($desde, $hasta);
    }

    /**
     * Recalcula el rango por tramos.
     * @param callable|null $progreso fn(string $desde, string $hasta, int $filas) para mostrar avance en consola
     * @return array{dias:int, filas:int, tramos:int}
     */
    public function recalcular(string $desde, string $hasta, ?callable $progreso = null): array
    {
        (new RangoFechasRules())->validar($desde, $hasta);
        if ($hasta >= date('Y-m-d')) {
            // Hoy todavía está en curso: la pantalla lo muestra en vivo.
            $hasta = date('Y-m-d', strtotime('-1 day'));
        }
        if ($desde > $hasta) {
            return ['dias' => 0, 'filas' => 0, 'tramos' => 0];
        }
        if (!$this->disponible()) {
            throw new \RuntimeException('Falta la tabla metricas_diarias (aplicar database/2026-11-05_metricas_diarias.sql).');
        }

        $ayer  = date('Y-m-d', strtotime('-1 day'));
        $total = ['dias' => 0, 'filas' => 0, 'tramos' => 0];
        $ini   = new \DateTimeImmutable($desde);
        $fin   = new \DateTimeImmutable($hasta);

        while ($ini <= $fin) {
            $finTramo = min($fin, $ini->modify('+' . (self::TRAMO_DIAS - 1) . ' days'));
            $d = $ini->format('Y-m-d');
            $h = $finTramo->format('Y-m-d');

            $this->repo->beginTransaction();
            try {
                $this->repo->bloquear();
                $fotos = $this->repo->fotosGuardadas($d, $h);
                $this->repo->borrarRango($d, $h);
                $filas  = $this->repo->insertarPorEmpresa($d, $h);
                $filas += $this->repo->insertarGlobal($d, $h);

                // Las "fotos" (empresas activas, usuarios habilitados) no se pueden reconstruir
                // hacia atrás: se conservan las que ya había y se toma la de ayer en vivo.
                foreach ($fotos as $f => $foto) {
                    $this->repo->guardarFoto($f, $foto['empresas_activas'] !== null ? (int) $foto['empresas_activas'] : null,
                        $foto['usuarios_habilitados'] !== null ? (int) $foto['usuarios_habilitados'] : null);
                }
                if ($ayer >= $d && $ayer <= $h) {
                    $actual = $this->repo->fotoActual();
                    $this->repo->guardarFoto($ayer, $actual['empresas_activas'] ?? null, $actual['usuarios_habilitados'] ?? null);
                }

                $this->repo->commit();
            } catch (\Throwable $e) {
                $this->repo->rollBack();
                throw $e;
            }

            $dias = (int) $ini->diff($finTramo)->days + 1;
            $total['dias'] += $dias;
            $total['filas'] += $filas;
            $total['tramos']++;
            if ($progreso) {
                $progreso($d, $h, $filas);
            }
            $ini = $finTramo->modify('+1 day');
        }
        return $total;
    }
}
