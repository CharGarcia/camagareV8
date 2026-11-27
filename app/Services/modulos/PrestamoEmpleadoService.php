<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoNovedades;
use App\repositories\modulos\NovedadRepository;
use App\repositories\modulos\PrestamoEmpleadoRepository;
use App\Rules\modulos\NovedadRules;
use App\Rules\modulos\PrestamoEmpleadoRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;
use Exception;

/**
 * Préstamo Empresa con tabla de cuotas (modal Nueva Novedad → «Préstamo empresa»).
 *
 * Se registra el monto, el N° de cuotas y el mes de la primera; el sistema arma la tabla
 * de cuotas (una por mes) y crea cada cuota por cobrar como una novedad tipo 9 ligada al
 * préstamo (`novedades.id_prestamo`). Así el rol, el asiento y el desembolso por egreso
 * (Egresos → Nómina, fila PRÉSTAMO del empleado) funcionan igual que siempre: este
 * Service NO crea egresos.
 *
 *  - Desembolso «Ya fue entregado»: las cuotas llevan la marca de ya pagado
 *    (desembolsado_migrado + desembolso_manual) y el rol las descuenta directo.
 *  - Desembolso «Por desembolsar»: el usuario registra el egreso en Egresos → Nómina y
 *    el rol descuenta las cuotas cuando el préstamo está desembolsado.
 *  - Cuotas «ya cobradas» (préstamos que empezaron antes del sistema): quedan en la tabla
 *    del préstamo como historial, pero NO se crean como novedad (se descontarían otra vez).
 *  - Interés: las columnas existen (tasa_interes, valor_interes, cuotas.interes) pero por
 *    ahora siempre van en 0: el desembolso por egreso toma la suma de las cuotas como
 *    monto a entregar, así que un interés dentro de las cuotas inflaría ese monto.
 */
class PrestamoEmpleadoService
{
    private const MODULO_GUARDADO = 'prestamos_empleado';

    private PrestamoEmpleadoRepository $repo;
    private PrestamoEmpleadoRules $rules;
    private NovedadService $novedades;
    private NovedadRepository $novedadRepo;
    private LogSistemaService $log;

    public function __construct(
        PrestamoEmpleadoRepository $repo,
        PrestamoEmpleadoRules $rules,
        NovedadService $novedades,
        NovedadRepository $novedadRepo,
        LogSistemaService $log
    ) {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->novedades = $novedades;
        $this->novedadRepo = $novedadRepo;
        $this->log = $log;
    }

    public static function crear(): self
    {
        $log = new LogSistemaService();
        $novRepo = new NovedadRepository();
        return new self(
            new PrestamoEmpleadoRepository(),
            new PrestamoEmpleadoRules(),
            new NovedadService($novRepo, new NovedadRules(), $log),
            $novRepo,
            $log
        );
    }

    /**
     * Tabla de cuotas: el total se reparte en partes iguales redondeadas a centavos y la
     * última cuota absorbe la diferencia (1000 / 3 → 333,33 · 333,33 · 333,34). Un mes por
     * cuota desde el mes/año inicial. El JS del modal calcula exactamente lo mismo.
     */
    public static function calcularCuotas(float $total, int $n, int $mes, int $anio): array
    {
        $cuotas = [];
        if ($n < 1) {
            return $cuotas;
        }
        $total = round($total, 2);
        $base = round($total / $n, 2);
        for ($k = 1; $k <= $n; $k++) {
            $valor = $k < $n ? $base : round($total - $base * ($n - 1), 2);
            $idx = ($mes - 1) + ($k - 1);
            $cuotas[] = [
                'numero'       => $k,
                'periodo_mes'  => ($idx % 12) + 1,
                'periodo_anio' => $anio + intdiv($idx, 12),
                'capital'      => $valor,
                'interes'      => 0.0,
                'valor'        => $valor,
            ];
        }
        return $cuotas;
    }

    /**
     * Registra el préstamo y sus cuotas. $d: id_empleado, fecha, monto, num_cuotas,
     * mes_inicio, anio_inicio, aplica_en, desembolso, observacion, cobradas (números de
     * cuota ya cobradas fuera del sistema).
     *
     * Devuelve uno de:
     *  - ['requiere_confirmacion' => true, 'avisos' => [...]]  el modal reenvía confirmado
     *  - ['id' => int, 'ya_registrado' => bool, 'creadas' => int, 'desembolso' => string, 'monto' => float]
     */
    public function registrar(array $d, int $idEmpresa, int $idUsuario, bool $confirmado = false, string $token = ''): array
    {
        if (!$this->repo->estructuraDisponible()) {
            throw new Exception('Para registrar préstamos con tabla de cuotas falta aplicar en la base de datos el script database/2026-11-27_prestamos_empleado.sql. Pida que se aplique.');
        }

        // Reintento del mismo formulario (se perdió la respuesta): devolver lo ya creado
        // antes de validar, que si no lo rechazaría (CLAUDE.md §8).
        $guardado = new GuardadoUnicoService();
        $this->repo->beginTransaction();
        try {
            $previo = $guardado->previo($token, $idEmpresa, self::MODULO_GUARDADO);
        } finally {
            $this->repo->rollBack();
        }
        if ($previo) {
            return $this->respuestaPrevia($previo);
        }

        $d['desembolso'] = (string) ($d['desembolso'] ?? '');
        $d['cobradas'] = array_values(array_unique(array_map('intval', (array) ($d['cobradas'] ?? []))));
        $this->rules->validar($d);

        $idEmpleado = (int) $d['id_empleado'];
        $empleado = $this->repo->getEmpleadoActivo($idEmpleado, $idEmpresa);
        if (!$empleado) {
            throw new Exception('El empleado no existe o ya no está activo en la empresa.');
        }

        $entregado = $d['desembolso'] === 'entregado';
        if ($entregado && !$this->novedadRepo->tieneDesembolsoManual()) {
            throw new Exception('Para registrar un préstamo "Ya fue entregado" falta aplicar en la base de datos el script database/2026-11-18_novedades_desembolso_manual.sql. Elija «Por desembolsar» o pida que se aplique.');
        }

        $monto = round((float) $d['monto'], 2);
        $n = (int) $d['num_cuotas'];
        $cuotas = self::calcularCuotas($monto, $n, (int) $d['mes_inicio'], (int) $d['anio_inicio']);
        $cobradas = array_flip($d['cobradas']);
        $porCobrar = array_values(array_filter($cuotas, fn($c) => !isset($cobradas[$c['numero']])));

        $observacion = mb_substr(trim((string) ($d['observacion'] ?? '')), 0, 500);
        $aplicaEn = (string) $d['aplica_en'];

        // ── Avisos que se confirman: otras cuotas de Préstamo Empresa en los mismos meses ──
        if (!$confirmado) {
            $existentes = $this->repo->getCuotasExistentesEnPeriodos(
                $idEmpresa,
                $idEmpleado,
                array_map(fn($c) => [$c['periodo_anio'], $c['periodo_mes']], $porCobrar)
            );
            if ($existentes) {
                $meses = array_values(array_unique(array_map(
                    fn($r) => str_pad((string) (int) $r['periodo_mes'], 2, '0', STR_PAD_LEFT) . '/' . (int) $r['periodo_anio'],
                    $existentes
                )));
                $lista = implode(', ', array_slice($meses, 0, 6)) . (count($meses) > 6 ? '…' : '');
                return ['requiere_confirmacion' => true, 'avisos' => [
                    $empleado['nombres_apellidos'] . ' ya tiene ' . count($existentes) . ' cuota(s) de Préstamo Empresa en '
                        . $lista . '. En esos meses se le descontarán las dos. ¿Registrar este préstamo de todos modos?',
                ]];
            }
        }

        // ── Guardar todo en una transacción (y una sola vez por formulario) ──
        $periodos = [];
        $this->repo->beginTransaction();
        try {
            if ($previo = $guardado->previo($token, $idEmpresa, self::MODULO_GUARDADO)) {
                $this->repo->rollBack();
                return $this->respuestaPrevia($previo);
            }

            $cabecera = [
                'id_empresa'    => $idEmpresa,
                'id_usuario'    => $idUsuario,
                'id_empleado'   => $idEmpleado,
                'fecha'         => trim((string) $d['fecha']),
                'monto'         => $monto,
                'tasa_interes'  => 0,
                'valor_interes' => 0,
                'total'         => $monto,
                'num_cuotas'    => $n,
                'mes_inicio'    => (int) $d['mes_inicio'],
                'anio_inicio'   => (int) $d['anio_inicio'],
                'aplica_en'     => $aplicaEn,
                'desembolso'    => $d['desembolso'],
                'observacion'   => $observacion,
            ];
            $idPrestamo = $this->repo->crear($cabecera);

            $creadas = 0;
            foreach ($cuotas as $c) {
                $idNovedad = null;
                if (!isset($cobradas[$c['numero']])) {
                    $texto = 'Préstamo Empresa N° ' . $idPrestamo . ' - Cuota ' . $c['numero'] . '/' . $n
                        . ($observacion !== '' ? ' - ' . $observacion : '');
                    try {
                        $res = $this->novedades->crearEnTransaccion([
                            'id_empresa'           => $idEmpresa,
                            'id_usuario'           => $idUsuario,
                            'id_empleado'          => $idEmpleado,
                            'tipo_codigo'          => '9',
                            'fecha'                => $cabecera['fecha'],
                            'periodo_mes'          => $c['periodo_mes'],
                            'periodo_anio'         => $c['periodo_anio'],
                            'valor'                => $c['valor'],
                            'aplica_en'            => $aplicaEn,
                            'motivo_codigo'        => '',
                            'observacion'          => mb_substr($texto, 0, 250),
                            'estado'               => 'activo',
                            'aporta_iess'          => null,
                            // Ya entregado fuera del sistema: el rol la descuenta directo, sin egreso.
                            'desembolsado_migrado' => $entregado,
                            'desembolso_manual'    => $entregado,
                        ]);
                    } catch (\Throwable $e) {
                        throw new Exception('Cuota ' . $c['numero'] . '/' . $n . ' (' . $this->nombrePeriodo($c['periodo_mes'], $c['periodo_anio']) . '): ' . $e->getMessage(), 0, $e);
                    }
                    $idNovedad = (int) $res['id'];
                    $this->repo->vincularNovedad($idNovedad, $idPrestamo, $idEmpresa);
                    $creadas++;
                    $periodos[$aplicaEn . '|' . $c['periodo_anio'] . '|' . $c['periodo_mes']] = [$c['periodo_anio'], $c['periodo_mes']];
                }
                $this->repo->crearCuota($c + [
                    'id_empresa'    => $idEmpresa,
                    'id_usuario'    => $idUsuario,
                    'id_prestamo'   => $idPrestamo,
                    'cobrada_fuera' => isset($cobradas[$c['numero']]),
                    'id_novedad'    => $idNovedad,
                ]);
            }

            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'prestamos_empleado', $idPrestamo, null,
                $cabecera + ['cuotas' => $cuotas, 'cuotas_cobradas_fuera' => $d['cobradas']]);

            $respuesta = ['id' => $idPrestamo, 'creadas' => $creadas, 'desembolso' => $d['desembolso'], 'monto' => $monto];
            $guardado->registrar($token, $idEmpresa, self::MODULO_GUARDADO, $idPrestamo, (string) $idPrestamo, $idUsuario, $respuesta);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        foreach ($periodos as $p) {
            $this->novedades->sincronizarRol($idEmpresa, $aplicaEn, $p[0], $p[1], $idUsuario);
        }

        return $respuesta + ['ya_registrado' => false];
    }

    private function respuestaPrevia(array $previo): array
    {
        $r = (array) ($previo['respuesta'] ?? []);
        return [
            'id'            => (int) ($r['id'] ?? $previo['id_registro'] ?? 0),
            'creadas'       => (int) ($r['creadas'] ?? 0),
            'desembolso'    => (string) ($r['desembolso'] ?? ''),
            'monto'         => (float) ($r['monto'] ?? 0),
            'ya_registrado' => true,
        ];
    }

    /**
     * Datos del préstamo para el modal de una de sus cuotas y para el aviso de Eliminar:
     * cuotas vigentes y si el préstamo completo se puede eliminar (ninguna cuota en un rol
     * pagado ni con desembolso por egreso). null si el préstamo no existe.
     */
    public function resumen(int $idPrestamo, int $idEmpresa): ?array
    {
        try {
            $p = $this->repo->getPrestamo($idPrestamo, $idEmpresa);
        } catch (\Throwable $e) {
            return null; // estructura no aplicada
        }
        if (!$p) {
            return null;
        }
        $vigentes = $this->repo->getNovedadesVigentes($idPrestamo, $idEmpresa);
        $motivo = null;
        foreach ($vigentes as $nov) {
            try {
                $this->novedades->validarEliminable($nov);
            } catch (\Throwable $e) {
                $motivo = $e->getMessage();
                break;
            }
        }
        return [
            'id'              => (int) $p['id'],
            'monto'           => (float) $p['monto'],
            'num_cuotas'      => (int) $p['num_cuotas'],
            'cuotas_vigentes' => count($vigentes),
            'total_vigente'   => round(array_sum(array_map(fn($n) => (float) $n['valor'], $vigentes)), 2),
            'desembolso'      => (string) $p['desembolso'],
            'eliminable'      => $motivo === null,
            'motivo'          => $motivo,
        ];
    }

    /**
     * Elimina el préstamo completo: todas sus cuotas vigentes (novedades) y la cabecera con
     * su tabla de cuotas, en una sola transacción. Solo si NINGUNA cuota está en un rol
     * pagado ni el préstamo tiene desembolso por egreso: si una no se puede, no se borra
     * nada y se dice cuál.
     */
    public function eliminar(int $idPrestamo, int $idEmpresa, int $idUsuario): int
    {
        $p = $this->repo->getPrestamo($idPrestamo, $idEmpresa);
        if (!$p) {
            throw new Exception('El préstamo no existe o ya fue eliminado.');
        }
        $vigentes = $this->repo->getNovedadesVigentes($idPrestamo, $idEmpresa);

        $bloqueos = [];
        foreach ($vigentes as $nov) {
            try {
                $this->novedades->validarEliminable($nov);
            } catch (\Throwable $e) {
                $bloqueos[] = $this->nombrePeriodo((int) $nov['periodo_mes'], (int) $nov['periodo_anio']) . ': ' . $e->getMessage();
            }
        }
        if ($bloqueos) {
            throw new Exception('No se eliminó el préstamo: ' . implode(' | ', array_slice($bloqueos, 0, 3))
                . (count($bloqueos) > 3 ? ' (y ' . (count($bloqueos) - 3) . ' cuota(s) más)' : ''));
        }

        $periodos = [];
        $this->repo->beginTransaction();
        try {
            foreach ($vigentes as $nov) {
                $this->novedades->eliminarEnTransaccion($nov, $idUsuario);
                $periodos[($nov['aplica_en'] ?? 'rol') . '|' . (int) $nov['periodo_anio'] . '|' . (int) $nov['periodo_mes']] = $nov;
            }
            $this->repo->eliminar($idPrestamo, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'prestamos_empleado', $idPrestamo, $p,
                ['cuotas_eliminadas' => array_map(fn($n) => (int) $n['id'], $vigentes)]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        foreach ($periodos as $nov) {
            $this->novedades->sincronizarRol($idEmpresa, $nov['aplica_en'] ?? 'rol', $nov['periodo_anio'], $nov['periodo_mes'], $idUsuario);
        }
        return count($vigentes);
    }

    private function nombrePeriodo(int $mes, int $anio): string
    {
        return (CatalogoNovedades::MESES[$mes] ?? (string) $mes) . ' ' . $anio;
    }
}
