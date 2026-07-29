<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsistenciaJornadaRepository;
use App\repositories\modulos\MarcacionRepository;
use App\repositories\modulos\AsistenciaHorarioRepository;
use App\Services\LogSistemaService;

/**
 * Motor de jornadas: a partir de las marcaciones de un día y del horario
 * vigente del empleado, calcula horas trabajadas, atraso, horas extra y estado
 * (completa / incompleta / falta). Es el puente hacia el rol (paso 4, vía Novedades).
 */
class JornadaService
{
    private const CLOCK_IN  = ['entrada', 'fin_break'];
    private const CLOCK_OUT = ['salida', 'inicio_break'];

    private AsistenciaJornadaRepository $repository;
    private MarcacionRepository $marcacionRepo;
    private AsistenciaHorarioRepository $horarioRepo;
    private LogSistemaService $logService;

    public function __construct(
        AsistenciaJornadaRepository $repository,
        MarcacionRepository $marcacionRepo,
        AsistenciaHorarioRepository $horarioRepo,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->marcacionRepo = $marcacionRepo;
        $this->horarioRepo = $horarioRepo;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /**
     * Recalcula (crea/actualiza) la jornada de un empleado en un día.
     * Devuelve el arreglo de la jornada calculada, o null si no aplica
     * (sin marcas y sin horario ese día → no se crea fila).
     */
    public function recalcularDia(int $idEmpresa, int $idEmpleado, string $fecha, ?int $idUsuario = null): ?array
    {
        $marcas  = $this->marcacionRepo->getMarcacionesDia($idEmpleado, $idEmpresa, $fecha);
        $horario = $this->horarioRepo->getHorarioVigente($idEmpleado, $idEmpresa, $fecha);

        $calc = $this->calcular($marcas, $horario, $fecha);

        // Sin marcas y sin horario (o día no laborable): no se registra jornada.
        if ($calc === null) {
            return null;
        }

        $data = [
            'id_empresa'       => $idEmpresa,
            'id_empleado'      => $idEmpleado,
            'id_punto'         => $calc['id_punto'] ?? ($horario['id_punto'] ?? null),
            'id_horario'       => $horario['id'] ?? null,
            'fecha'            => $fecha,
            'primera_entrada'  => $calc['primera_entrada'],
            'ultima_salida'    => $calc['ultima_salida'],
            'horas_trabajadas' => $calc['horas_trabajadas'],
            'atraso_min'       => $calc['atraso_min'],
            'extra_min'        => $calc['extra_min'],
            'estado'           => $calc['estado'],
            'observacion'      => $calc['observacion'],
            'id_usuario'       => $idUsuario,
        ];

        $existente = $this->repository->getByDia($idEmpleado, $idEmpresa, $fecha);
        if ($existente) {
            $this->repository->update((int) $existente['id'], $data);
            $data['id'] = (int) $existente['id'];
        } else {
            $data['id'] = $this->repository->insert($data);
        }

        return $data;
    }

    /**
     * Recalcula un rango de fechas para todos los empleados relevantes.
     * @return int número de jornadas creadas/actualizadas.
     */
    public function recalcularRango(int $idEmpresa, string $desde, string $hasta, ?int $idUsuario = null, ?int $idEmpleado = null): int
    {
        $empleados = $idEmpleado
            ? [$idEmpleado]
            : $this->repository->getEmpleadosParaRecalculo($idEmpresa, $desde, $hasta);

        if (empty($empleados)) {
            return 0;
        }

        $tsDesde = strtotime($desde);
        $tsHasta = strtotime($hasta);
        if ($tsDesde === false || $tsHasta === false || $tsHasta < $tsDesde) {
            return 0;
        }

        $n = 0;
        foreach ($empleados as $idEmp) {
            for ($ts = $tsDesde; $ts <= $tsHasta; $ts += 86400) {
                $fecha = date('Y-m-d', $ts);
                if ($this->recalcularDia($idEmpresa, (int) $idEmp, $fecha, $idUsuario) !== null) {
                    $n++;
                }
            }
        }

        $this->logService->registrar((int) ($idUsuario ?? 0), $idEmpresa, 'RECALCULAR_JORNADAS', 'asistencia_jornadas', null, null, ['desde' => $desde, 'hasta' => $hasta, 'empleados' => count($empleados)]);
        return $n;
    }

    /**
     * Recalcula SOLO las jornadas incompletas (requieren revisión) de un rango.
     * Útil tras agregar marcaciones faltantes, para refrescar en masa sin tocar
     * las que ya estaban completas. Devuelve cuántas quedaron resueltas.
     */
    public function recalcularIncompletas(int $idEmpresa, string $desde, string $hasta, ?int $idUsuario = null): array
    {
        $incompletas = $this->repository->getIncompletas($idEmpresa, $desde, $hasta);
        $procesadas = 0; $resueltas = 0;

        foreach ($incompletas as $j) {
            $fecha = substr((string) $j['fecha'], 0, 10);
            $res = $this->recalcularDia($idEmpresa, (int) $j['id_empleado'], $fecha, $idUsuario);
            $procesadas++;
            if ($res !== null && ($res['estado'] ?? '') !== 'incompleta') {
                $resueltas++;
            }
        }

        $this->logService->registrar((int) ($idUsuario ?? 0), $idEmpresa, 'RECALCULAR_INCOMPLETAS', 'asistencia_jornadas', null, null,
            ['desde' => $desde, 'hasta' => $hasta, 'procesadas' => $procesadas, 'resueltas' => $resueltas]);

        return ['procesadas' => $procesadas, 'resueltas' => $resueltas, 'pendientes' => $procesadas - $resueltas];
    }

    /** Cuenta las jornadas incompletas (requieren revisión) de un rango. */
    public function contarIncompletas(int $idEmpresa, string $desde, string $hasta): int
    {
        return $this->repository->contarIncompletas($idEmpresa, $desde, $hasta);
    }

    /**
     * Núcleo del cálculo. Devuelve métricas o null si no hay nada que registrar.
     */
    private function calcular(array $marcas, ?array $horario, string $fecha): ?array
    {
        $tieneMarcas = !empty($marcas);
        $esLaborable = $horario ? $this->esDiaLaborable($horario, $fecha) : false;

        // Sin marcas: solo hay jornada si era día laborable (falta).
        if (!$tieneMarcas) {
            if (!$esLaborable) {
                return null;
            }
            return [
                'primera_entrada'  => null,
                'ultima_salida'    => null,
                'horas_trabajadas' => 0,
                'atraso_min'       => 0,
                'extra_min'        => 0,
                'estado'           => 'falta',
                'observacion'      => 'Sin marcaciones en día laborable.',
                'id_punto'         => $horario['id_punto'] ?? null,
            ];
        }

        // Emparejar entradas/salidas cronológicamente (breaks incluidos).
        $in = null;
        $workedSec = 0;
        $primera = null;
        $ultima = null;
        $idPunto = null;

        foreach ($marcas as $m) {
            $ts = strtotime((string) $m['fecha_hora']);
            if ($idPunto === null && !empty($m['id_punto'])) {
                $idPunto = (int) $m['id_punto'];
            }
            if (in_array($m['tipo'], self::CLOCK_IN, true)) {
                if ($in === null) {
                    $in = $ts;
                    if ($primera === null) $primera = $ts;
                }
            } elseif (in_array($m['tipo'], self::CLOCK_OUT, true)) {
                if ($in !== null) {
                    $workedSec += max(0, $ts - $in);
                    $in = null;
                }
                $ultima = $ts;
            }
        }

        // Quedó "adentro" sin salida final. Como no hay marca de salida, el sistema
        // no tiene evidencia de cuánto se quedó: se cierra el tramo abierto a la HORA
        // DE SALIDA PROGRAMADA del horario (estimación conservadora que nunca inventa
        // horas extra). Si no hay horario/hora de salida, el tramo queda sin contar.
        // En ambos casos la jornada se marca "incompleta" para que se revise.
        $incompleta = ($in !== null);
        $cierreAuto = false;
        $salidaEstimada = null;

        if ($incompleta && $horario && !empty($horario['hora_salida'])) {
            $salidaProg = $this->horaSalidaProgramada($horario, $fecha);
            if ($salidaProg !== null && $salidaProg > $in) {
                $workedSec += ($salidaProg - $in);
                $salidaEstimada = $salidaProg;
                $cierreAuto = true;
            }
            $in = null;

            // En un cierre estimado las horas no pueden superar la jornada esperada:
            // no hay evidencia de sobretiempo y el tramo continuo no descuenta breaks.
            if ($cierreAuto && !empty($horario['horas_jornada'])) {
                $workedSec = min($workedSec, (float) $horario['horas_jornada'] * 3600);
            }
        }

        $horasTrab = round($workedSec / 3600, 2);

        // Atraso respecto a la hora de entrada del horario + tolerancia.
        $atrasoMin = 0;
        if ($horario && $primera !== null && !empty($horario['hora_entrada'])) {
            $entradaProg = strtotime($fecha . ' ' . $horario['hora_entrada']);
            if ($entradaProg !== false) {
                $tol = (int) ($horario['tolerancia_min'] ?? 0) * 60;
                $diff = $primera - ($entradaProg + $tol);
                if ($diff > 0) $atrasoMin = (int) round($diff / 60);
            }
        }

        // Horas extra: trabajo por encima de la jornada esperada. NO se calcula en
        // un cierre automático: sin salida real no hay evidencia de sobretiempo.
        $extraMin = 0;
        if (!$cierreAuto && $horario && !empty($horario['horas_jornada'])) {
            $espSec = (float) $horario['horas_jornada'] * 3600;
            if ($workedSec > $espSec) {
                $extraMin = (int) round(($workedSec - $espSec) / 60);
            }
        }

        $estado = $incompleta ? 'incompleta' : 'completa';
        if (!$incompleta) {
            $obs = null;
        } elseif ($cierreAuto) {
            $obs = 'Falta la salida. Cerrada automáticamente a la hora de salida programada ('
                . date('H:i', $salidaEstimada) . '). Requiere revisión.';
        } else {
            $obs = 'Falta la salida final del día. Requiere revisión.';
        }

        return [
            'primera_entrada'  => $primera ? date('Y-m-d H:i:s', $primera) : null,
            // Con cierre automático se guarda la salida estimada para que el resumen
            // y el rol tengan horas; sigue marcada "incompleta" para su revisión.
            'ultima_salida'    => $salidaEstimada ? date('Y-m-d H:i:s', $salidaEstimada) : ($ultima ? date('Y-m-d H:i:s', $ultima) : null),
            'horas_trabajadas' => $horasTrab,
            'atraso_min'       => $atrasoMin,
            'extra_min'        => $extraMin,
            'estado'           => $estado,
            'observacion'      => $obs,
            'id_punto'         => $idPunto,
        ];
    }

    /**
     * Timestamp de la hora de salida programada del horario para esa fecha.
     * Si el turno cruza medianoche, la salida cae el día siguiente.
     */
    private function horaSalidaProgramada(array $horario, string $fecha): ?int
    {
        $hora = trim((string) ($horario['hora_salida'] ?? ''));
        if ($hora === '') {
            return null;
        }
        $cruza = in_array($horario['cruza_medianoche'] ?? false, [true, 1, '1', 't', 'true'], true);
        $base = $cruza ? date('Y-m-d', strtotime($fecha . ' +1 day')) : $fecha;
        $ts = strtotime($base . ' ' . $hora);
        return $ts === false ? null : $ts;
    }

    /** ¿La fecha cae en un día laborable según dias_semana del horario (1=lun..7=dom)? */
    private function esDiaLaborable(array $horario, string $fecha): bool
    {
        $dias = trim((string) ($horario['dias_semana'] ?? ''));
        if ($dias === '') return false;
        $n = (int) date('N', strtotime($fecha)); // 1..7
        $set = array_map('trim', explode(',', $dias));
        return in_array((string) $n, $set, true);
    }
}
