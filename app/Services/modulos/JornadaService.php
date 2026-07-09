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

        $incompleta = ($in !== null); // quedó "adentro" sin salida final
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

        // Horas extra: trabajo por encima de la jornada esperada.
        $extraMin = 0;
        if ($horario && !empty($horario['horas_jornada'])) {
            $espSec = (float) $horario['horas_jornada'] * 3600;
            if ($workedSec > $espSec) {
                $extraMin = (int) round(($workedSec - $espSec) / 60);
            }
        }

        $estado = $incompleta ? 'incompleta' : 'completa';
        $obs = $incompleta ? 'Falta la salida final del día.' : null;

        return [
            'primera_entrada'  => $primera ? date('Y-m-d H:i:s', $primera) : null,
            'ultima_salida'    => $ultima ? date('Y-m-d H:i:s', $ultima) : null,
            'horas_trabajadas' => $horasTrab,
            'atraso_min'       => $atrasoMin,
            'extra_min'        => $extraMin,
            'estado'           => $estado,
            'observacion'      => $obs,
            'id_punto'         => $idPunto,
        ];
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
