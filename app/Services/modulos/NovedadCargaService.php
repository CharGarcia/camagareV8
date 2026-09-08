<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoNovedades;
use App\repositories\modulos\NovedadCargaRepository;
use App\repositories\modulos\NovedadRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Cargas masivas de Novedades: registro de cada importación de plantilla y
 * reversión completa de una carga.
 *
 * Regla: una carga solo se puede revertir mientras NINGUNA de sus novedades haya
 * sido usada — es decir, mientras el rol del período no esté pagado y el
 * Anticipo/Préstamo no tenga desembolso por egreso (mismo criterio de bloqueo
 * que ya aplica al eliminar una novedad suelta). Si alguna ya se usó, no se
 * revierte nada: la carga queda intacta y se informa cuáles la bloquean.
 */
class NovedadCargaService
{
    private NovedadCargaRepository $cargaRepo;
    private NovedadRepository $novRepo;
    private NovedadService $novService;
    private LogSistemaService $logService;

    public function __construct(
        NovedadCargaRepository $cargaRepo,
        NovedadRepository $novRepo,
        NovedadService $novService,
        LogSistemaService $logService
    ) {
        $this->cargaRepo  = $cargaRepo;
        $this->novRepo    = $novRepo;
        $this->novService = $novService;
        $this->logService = $logService;
    }

    /** ¿Está desplegada la tabla de cargas? (si no, la importación no las registra). */
    public function disponible(): bool
    {
        return $this->cargaRepo->disponible();
    }

    /** Abre el registro de una carga; devuelve null si la tabla no está desplegada. */
    public function abrirCarga(int $idEmpresa, int $idUsuario, string $archivo): ?int
    {
        if (!$this->disponible()) {
            return null;
        }
        try {
            return $this->cargaRepo->crear([
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'archivo'    => $archivo,
            ]);
        } catch (\Throwable $e) {
            return null; // nunca impedir la importación por el registro de la carga
        }
    }

    /** Cierra la carga con el resultado real y la audita. */
    public function cerrarCarga(?int $idCarga, int $idEmpresa, int $idUsuario, int $total, int $creadas, int $errores): void
    {
        if ($idCarga === null) {
            return;
        }
        try {
            $this->cargaRepo->actualizarResumen($idCarga, $idEmpresa, $total, $creadas, $errores);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR',
                'novedades_cargas',
                $idCarga,
                null,
                ['total_filas' => $total, 'creadas' => $creadas, 'errores' => $errores]
            );
        } catch (\Throwable $e) {
            // El resumen es informativo: si falla, la importación ya está hecha.
        }
    }

    /** Cargas recientes de la empresa, con el estado de reversión de cada una. */
    public function getListado(int $idEmpresa, int $limite = 50, ?int $idUsuarioFiltro = null): array
    {
        $cargas = $this->cargaRepo->getListado($idEmpresa, $limite, $idUsuarioFiltro);
        if (empty($cargas)) {
            return [];
        }

        // Una sola consulta para las novedades de TODAS las cargas listadas.
        $porCarga = [];
        foreach ($this->novRepo->getPorCargas(array_column($cargas, 'id'), $idEmpresa) as $n) {
            $porCarga[(int) $n['id_carga']][] = $n;
        }

        foreach ($cargas as &$c) {
            $vigentes  = (int) ($c['vigentes'] ?? 0);
            $novedades = $porCarga[(int) $c['id']] ?? [];
            $usadas    = $this->filtrarUsadas($novedades);

            $c['vigentes']       = $vigentes;
            $c['usadas']         = count($usadas);
            $c['reversible']     = $vigentes > 0 && empty($usadas);
            $c['motivo_bloqueo'] = $vigentes === 0
                ? 'Sus novedades ya no existen (se eliminaron una a una).'
                : (empty($usadas) ? '' : $this->resumenUsadas($usadas));
        }
        unset($c);

        return $cargas;
    }

    /**
     * Revierte una carga completa: elimina lógicamente todas sus novedades
     * vigentes y marca la carga como revertida. Lanza excepción si alguna ya fue
     * usada (en ese caso no elimina nada).
     */
    public function eliminarCarga(int $idCarga, int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro = null): array
    {
        if (!$this->disponible()) {
            throw new Exception('El registro de cargas masivas no está habilitado en esta instalación.');
        }

        $carga = $this->cargaRepo->getById($idCarga, $idEmpresa);
        if (!$carga) {
            throw new Exception('Carga no encontrada o ya revertida.');
        }
        // Sin permiso de acceso total, solo puede revertir las cargas que él mismo hizo.
        if ($idUsuarioFiltro !== null && (int) ($carga['created_by'] ?? 0) !== $idUsuarioFiltro) {
            throw new Exception('Solo puede eliminar las cargas que usted realizó.');
        }

        $novedades = $this->novRepo->getPorCarga($idCarga, $idEmpresa);
        $usadas    = $this->filtrarUsadas($novedades);
        if (!empty($usadas)) {
            throw new Exception('No se puede eliminar esta carga: ' . $this->resumenUsadas($usadas)
                . ' Anule el rol/egreso correspondiente si necesita revertirla, o elimine una a una las novedades que aún no se han usado.');
        }

        // Períodos afectados (únicos) para regenerar el rol una sola vez por período.
        $periodos = [];
        foreach ($novedades as $n) {
            $clave = ($n['aplica_en'] ?? 'rol') . '|' . (int) $n['periodo_anio'] . '|' . (int) $n['periodo_mes'];
            $periodos[$clave] = [
                'aplica_en' => (string) ($n['aplica_en'] ?? 'rol'),
                'anio'      => (int) $n['periodo_anio'],
                'mes'       => (int) $n['periodo_mes'],
            ];
        }

        $this->novRepo->beginTransaction();
        try {
            $eliminadas = $this->novRepo->deleteLogicPorCarga($idCarga, $idEmpresa, $idUsuario);
            $this->cargaRepo->marcarRevertida($idCarga, $idEmpresa, $idUsuario);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'novedades_cargas',
                $idCarga,
                $carga + ['novedades_eliminadas' => array_map(fn($n) => (int) $n['id'], $novedades)],
                null
            );
            $this->novRepo->commit();
        } catch (\Throwable $e) {
            $this->novRepo->rollBack();
            throw $e;
        }

        // Fuera de la transacción: regenerar los roles 'generado' de los períodos tocados.
        foreach ($periodos as $p) {
            $this->novService->sincronizarRol($idEmpresa, $p['aplica_en'], $p['anio'], $p['mes'], $idUsuario);
        }

        return ['eliminadas' => $eliminadas, 'archivo' => (string) ($carga['archivo'] ?? '')];
    }

    /** Novedades de la carga que ya fueron usadas (rol pagado o desembolso registrado). */
    private function filtrarUsadas(array $novedades): array
    {
        return array_values(array_filter(
            $novedades,
            fn($n) => !empty($n['bloqueada']) || !empty($n['pagada'])
        ));
    }

    /** Texto corto con las primeras novedades ya usadas, para explicar el bloqueo. */
    private function resumenUsadas(array $usadas): string
    {
        $muestra = [];
        foreach (array_slice($usadas, 0, 3) as $n) {
            $mes = CatalogoNovedades::MESES[(int) $n['periodo_mes']] ?? $n['periodo_mes'];
            $muestra[] = trim((string) ($n['empleado_nombre'] ?? '')) . ' — '
                . (string) ($n['tipo_nombre'] ?? '') . ' ' . $mes . ' ' . (int) $n['periodo_anio'];
        }
        $extra = count($usadas) > 3 ? ' y ' . (count($usadas) - 3) . ' más' : '';
        return count($usadas) . ' novedad(es) ya se usaron en un rol pagado o desembolso: '
            . implode('; ', $muestra) . $extra . '.';
    }
}
