<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\VacacionPeriodoRepository;
use App\repositories\modulos\VacacionRepository;
use App\Rules\modulos\VacacionRules;
use App\Services\LogSistemaService;
use Exception;

class VacacionService
{
    private VacacionRepository $repo;
    private VacacionRules $rules;
    private LogSistemaService $log;
    private VacacionCalculoService $calc;
    private VacacionPeriodoRepository $periodosRepo;

    public function __construct(VacacionRepository $repo, VacacionRules $rules, LogSistemaService $log, ?VacacionPeriodoRepository $periodosRepo = null)
    {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->log = $log;
        $this->calc = new VacacionCalculoService();
        $this->periodosRepo = $periodosRepo ?? new VacacionPeriodoRepository();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /** Años del mes del rol usados por la empresa (modal de filtros del listado). */
    public function getAniosUsados(int $idEmpresa): array
    {
        return $this->repo->getAniosUsados($idEmpresa);
    }

    /** Usuarios que registraron vacaciones en la empresa (modal de filtros del listado). */
    public function getUsuariosConVacaciones(int $idEmpresa): array
    {
        return $this->repo->getUsuariosConVacaciones($idEmpresa);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getDetalle($id, $idEmpresa);
    }

    /** Empleado + sus vacaciones registradas (PDF del detalle de vacaciones). */
    public function getEmpleado(int $idEmpleado, int $idEmpresa): ?array
    {
        return $this->repo->getEmpleado($idEmpleado, $idEmpresa);
    }

    public function getVacacionesEmpleado(int $idEmpleado, int $idEmpresa): array
    {
        return $this->repo->getPorEmpleado($idEmpleado, $idEmpresa);
    }

    /**
     * Información de vacaciones de un empleado: antigüedad, derecho, saldo, sueldo y el
     * cuadro de sus períodos de servicio.
     *
     * Saldo = derecho acumulado − días de los períodos marcados como tomados/pagados
     * antes del sistema − días gozados registrados en el sistema.
     */
    public function getInfoEmpleado(int $idEmpleado, int $idEmpresa, ?int $excludeVacacion = null): array
    {
        $emp = $this->repo->getEmpleado($idEmpleado, $idEmpresa);
        if (!$emp) throw new Exception('Empleado no encontrado.');

        $fechaIngreso = $this->repo->getFechaIngreso($idEmpleado, $idEmpresa);
        $gozados = $this->repo->getDiasGozadosTotal($idEmpleado, $idEmpresa, $excludeVacacion);

        $ant = $fechaIngreso
            ? $this->calc->antiguedad($fechaIngreso)
            : ['anios_completos' => 0, 'anios_texto' => 'Sin fecha de ingreso', 'derecho_anio_actual' => 15, 'total_derecho' => 0.0];

        $cuadro = $this->armarPeriodos(
            $fechaIngreso ? $this->calc->periodos($fechaIngreso) : [],
            $this->periodosRepo->getMarcados($idEmpleado, $idEmpresa),
            $gozados
        );

        return [
            'id_empleado'         => $idEmpleado,
            'sueldo_base'         => (float) $emp['sueldo_base'],
            'fecha_ingreso'       => $fechaIngreso,
            'antiguedad'          => $ant['anios_texto'],
            'derecho_anio_actual' => $ant['derecho_anio_actual'],
            'total_derecho'       => $ant['total_derecho'],
            'dias_gozados_total'  => round($gozados, 2),
            'dias_marcados'       => $cuadro['dias_marcados'],
            'saldo'               => $this->calc->saldo($ant['total_derecho'], $gozados + $cuadro['dias_marcados']),
            'periodos'            => $cuadro['periodos'],
            'dias_adelantados'    => $cuadro['dias_adelantados'],
            'periodos_disponible' => $this->periodosRepo->disponible(),
        ];
    }

    /**
     * Cuadro de períodos de servicio. A cada año se le resta lo marcado como tomado o
     * pagado antes del sistema y, después, los días gozados registrados en el sistema se
     * reparten del período más antiguo al más nuevo (así se consumen las vacaciones).
     * Lo que sobra al llegar al año en curso son días gozados por adelantado.
     *
     * Una marca nunca descuenta más de lo acumulado en su período, y la de un período que
     * ya no existe (se corrigió la fecha de ingreso a una más reciente) no descuenta nada:
     * se muestra aparte para que el usuario la quite. Así el saldo siempre es la suma de
     * los pendientes menos lo adelantado.
     *
     * @param list<array>       $periodos  VacacionCalculoService::periodos()
     * @param array<int, array> $marcados  VacacionPeriodoRepository::getMarcados()
     * @return array{periodos: list<array>, dias_marcados: float, dias_adelantados: float}
     */
    private function armarPeriodos(array $periodos, array $marcados, float $gozados): array
    {
        $porRepartir  = round(max(0.0, $gozados), 2);
        $diasMarcados = 0.0;
        $filas = [];

        foreach ($periodos as $p) {
            $num   = (int) $p['numero'];
            $marca = $marcados[$num] ?? null;
            unset($marcados[$num]);

            // Todo a 2 decimales en cada paso: sin residuos de coma flotante que dejen
            // un 0,000…1 "repartido" y cambien la situación de un período.
            $acumulado  = (float) $p['dias_acumulados'];
            $descMarca  = $marca ? min((float) $marca['dias'], $acumulado) : 0.0;
            $diasMarcados += $descMarca;

            $disponible  = round($acumulado - $descMarca, 2);
            $sistema     = round(min($disponible, $porRepartir), 2);
            $porRepartir = round($porRepartir - $sistema, 2);
            $pendiente   = round($disponible - $sistema, 2);

            if ($marca) {
                $situacion = $pendiente > 0 ? 'parcial' : $marca['estado'];
            } elseif (!$p['completo']) {
                $situacion = 'en_curso';
            } elseif ($pendiente <= 0) {
                $situacion = 'gozado';
            } else {
                $situacion = $sistema > 0 ? 'parcial' : 'pendiente';
            }

            $filas[] = $p + [
                'marca'        => $marca ? $this->formatearMarca($marca, $p) : null,
                'dias_sistema' => round($sistema, 2),
                'pendiente'    => $pendiente,
                'situacion'    => $situacion,
                'marcable'     => $p['completo'] && !$marca,
            ];
        }

        // Marcas sin período: no descuentan (su año ya no es parte del derecho).
        foreach ($marcados as $num => $marca) {
            $filas[] = [
                'numero'          => (int) $num,
                'fecha_inicio'    => $marca['fecha_inicio'],
                'fecha_fin'       => $marca['fecha_fin'],
                'dias_derecho'    => (float) $marca['dias_derecho'],
                'dias_acumulados' => 0.0,
                'completo'        => false,
                'marca'           => $this->formatearMarca($marca, null),
                'dias_sistema'    => 0.0,
                'pendiente'       => 0.0,
                'situacion'       => 'fuera_de_rango',
                'marcable'        => false,
            ];
        }

        return [
            'periodos'         => $filas,
            'dias_marcados'    => round($diasMarcados, 2),
            'dias_adelantados' => round($porRepartir, 2),
        ];
    }

    /** Datos de la marca para el cuadro (fechas d-m-Y H:i:s, aviso si cambiaron las del período). */
    private function formatearMarca(array $marca, ?array $periodo): array
    {
        return [
            'id'              => (int) $marca['id'],
            'estado'          => $marca['estado'],
            'dias'            => (float) $marca['dias'],
            'observacion'     => $marca['observacion'],
            'created_by'      => (int) $marca['created_by'],
            'usuario'         => $marca['usuario_nombre'],
            'fecha_registro'  => $marca['created_at'] ? date('d-m-Y H:i:s', strtotime((string) $marca['created_at'])) : null,
            // Se marcó con otras fechas: cambió la fecha de ingreso del empleado.
            'fechas_marcadas' => $periodo !== null && $marca['fecha_inicio'] !== $periodo['fecha_inicio']
                ? date('d-m-Y', strtotime((string) $marca['fecha_inicio'])) . ' al ' . date('d-m-Y', strtotime((string) $marca['fecha_fin']))
                : null,
        ];
    }

    /**
     * Marca como tomados o pagados antes del sistema los períodos elegidos del empleado
     * (empleados que vienen de otro sistema). No toca ningún rol ni genera valores: solo
     * cierra los días de esos períodos para que el saldo cuadre.
     *
     * @param array<int|string, mixed> $dias número de período => días a cerrar
     * @return int períodos marcados
     */
    public function marcarPeriodos(int $idEmpresa, int $idEmpleado, string $estado, array $dias, string $observacion, int $idUsuario): int
    {
        if (!$this->periodosRepo->disponible()) {
            throw new Exception('Falta aplicar en la base de datos el script de períodos de vacaciones (database/modulos_vacaciones_periodos.sql).');
        }
        if (!$this->repo->getEmpleado($idEmpleado, $idEmpresa)) {
            throw new Exception('Empleado no encontrado.');
        }

        $porNumero = [];
        foreach ($dias as $num => $d) {
            if ((int) $num > 0) {
                $porNumero[(int) $num] = round((float) $d, 2);
            }
        }
        ksort($porNumero);

        $this->repo->beginTransaction();
        try {
            // Candado antes de leer lo ya marcado: dos usuarios a la vez no cierran dos veces un período.
            $this->periodosRepo->lockEmpleado($idEmpresa, $idEmpleado);

            $fechaIngreso = $this->repo->getFechaIngreso($idEmpleado, $idEmpresa);
            if (!$fechaIngreso) {
                throw new Exception('El empleado no tiene fecha de ingreso: regístrela en su ficha para calcular sus períodos.');
            }
            $periodos = array_column($this->calc->periodos($fechaIngreso), null, 'numero');
            $this->rules->validarMarcaPeriodos($estado, $porNumero, $periodos, $this->periodosRepo->getMarcados($idEmpleado, $idEmpresa));

            foreach ($porNumero as $num => $d) {
                $fila = [
                    'id_empresa'     => $idEmpresa,
                    'id_empleado'    => $idEmpleado,
                    'numero_periodo' => $num,
                    'fecha_inicio'   => $periodos[$num]['fecha_inicio'],
                    'fecha_fin'      => $periodos[$num]['fecha_fin'],
                    'dias_derecho'   => $periodos[$num]['dias_derecho'],
                    'dias'           => $d,
                    'estado'         => $estado,
                    'observacion'    => $observacion,
                    'id_usuario'     => $idUsuario,
                ];
                $id = $this->periodosRepo->crear($fila);
                $this->log->registrar($idUsuario, $idEmpresa, 'MARCAR_PERIODO', 'vacaciones_periodos', $id, null, $fila);
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
        return count($porNumero);
    }

    /** Marca viva de un período (null si no existe o si aún no se aplicó el SQL). */
    public function getPeriodoMarcado(int $idMarca, int $idEmpresa): ?array
    {
        return $this->periodosRepo->disponible() ? $this->periodosRepo->findById($idMarca, $idEmpresa) : null;
    }

    /** Quita la marca de un período: sus días vuelven a contar en el saldo. */
    public function desmarcarPeriodo(int $idMarca, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->getPeriodoMarcado($idMarca, $idEmpresa);
        if (!$antes) throw new Exception('Ese período ya no está marcado. Actualice la ventana.');

        $this->repo->beginTransaction();
        try {
            if (!$this->periodosRepo->eliminarLogico($idMarca, $idEmpresa, $idUsuario)) {
                throw new Exception('Ese período ya no está marcado. Actualice la ventana.');
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'DESMARCAR_PERIODO', 'vacaciones_periodos', $idMarca, $antes, null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Calcula el valor a pagar por unos días (para preview en el modal). */
    public function calcularValor(int $idEmpleado, int $idEmpresa, float $dias): float
    {
        $emp = $this->repo->getEmpleado($idEmpleado, $idEmpresa);
        return $emp ? $this->calc->valor((float) $emp['sueldo_base'], $dias) : 0.0;
    }

    public function crear(array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $data = $this->prepararCalculos($data, $idEmpresa);
        $this->rules->validate($data);

        // No permitir registrar vacaciones sobre un rol mensual ya pagado — pero solo si
        // esta vacación SÍ va a alimentar ese rol (afecta_rol). Un registro histórico
        // (afecta_rol=false, p. ej. vacaciones ya tomadas y liquidadas antes de usar el
        // sistema) no toca ningún rol, así que no debe bloquearse ni sincronizar nada:
        // solo descuenta del saldo del empleado.
        if ($this->esVerdadero($data['afecta_rol'] ?? true)) {
            $this->bloquearSiRolPagado($idEmpresa, $data['id_empleado'] ?? 0, $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0);
        }

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->create($data);
            $this->log->registrar((int) $data['id_usuario'], $idEmpresa, 'CREAR', 'vacaciones', $id, null, $data);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        if ($this->esVerdadero($data['afecta_rol'] ?? true)) {
            $this->sincronizarRol($idEmpresa, $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0, (int) $data['id_usuario']);
        }
        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $antes = $this->repo->getDetalle($id, $idEmpresa);
        if (!$antes) throw new Exception('Registro no encontrado.');
        // Quien no envía el estado (flujos que no lo editan) conserva el que tenía:
        // el repositorio usa 'registrado' por defecto y lo cambiaría sin querer.
        if (empty($data['estado'])) $data['estado'] = $antes['estado'];
        $data = $this->prepararCalculos($data, $idEmpresa);
        $this->rules->validate($data);

        $antesAfectaba = $this->esVerdadero($antes['afecta_rol'] ?? true);
        $nuevoAfecta = $this->esVerdadero($data['afecta_rol'] ?? true);

        // Bloquear si el destino ANTERIOR o el NUEVO corresponden a un rol mensual ya
        // pagado — solo cuando ese destino realmente afecta (afectaba) un rol.
        if ($antesAfectaba) {
            $this->bloquearSiRolPagado($idEmpresa, $antes['id_empleado'] ?? 0, $antes['periodo_anio'] ?? 0, $antes['periodo_mes'] ?? 0);
        }
        if ($nuevoAfecta) {
            $this->bloquearSiRolPagado($idEmpresa, $data['id_empleado'] ?? 0, $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0);
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->update($id, $idEmpresa, $data);
            $this->log->registrar((int) $data['id_usuario'], $idEmpresa, 'ACTUALIZAR', 'vacaciones', $id, $antes, $data);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        // Resincroniza el rol nuevo (si aplica) y, si cambió de período o si dejó de
        // afectar un rol que antes sí afectaba, también el rol anterior (para que ese
        // rol deje de incluir un valor que ya no le corresponde).
        if ($nuevoAfecta) {
            $this->sincronizarRol($idEmpresa, $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0, (int) $data['id_usuario']);
        }
        if ($antesAfectaba && (
            !$nuevoAfecta
            || (int) ($antes['periodo_mes'] ?? 0) !== (int) ($data['periodo_mes'] ?? 0)
            || (int) ($antes['periodo_anio'] ?? 0) !== (int) ($data['periodo_anio'] ?? 0)
        )) {
            $this->sincronizarRol($idEmpresa, $antes['periodo_anio'] ?? 0, $antes['periodo_mes'] ?? 0, (int) $data['id_usuario']);
        }
    }

    /** Completa dias_derecho (snapshot) y valor a partir del empleado. */
    private function prepararCalculos(array $data, int $idEmpresa): array
    {
        $idEmp = (int) $data['id_empleado'];
        $emp = $this->repo->getEmpleado($idEmp, $idEmpresa);
        $sueldo = $emp ? (float) $emp['sueldo_base'] : 0.0;
        $dias = (float) $data['dias_gozados'];

        $fechaIngreso = $this->repo->getFechaIngreso($idEmp, $idEmpresa);
        $data['dias_derecho'] = $fechaIngreso
            ? $this->calc->antiguedad($fechaIngreso, $data['fecha_desde'] ?? null)['derecho_anio_actual']
            : VacacionCalculoService::DIAS_BASE;
        $data['valor'] = $this->calc->valor($sueldo, $dias);

        // El rol que alimenta se toma del mes de la fecha desde si no se especifica.
        if (empty($data['periodo_mes']) && !empty($data['fecha_desde'])) {
            $data['periodo_mes'] = (int) date('n', strtotime($data['fecha_desde']));
        }
        if (empty($data['periodo_anio']) && !empty($data['fecha_desde'])) {
            $data['periodo_anio'] = (int) date('Y', strtotime($data['fecha_desde']));
        }
        return $data;
    }

    public function cambiarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $antes = $this->repo->getDetalle($id, $idEmpresa);
        if (!$antes) throw new Exception('Registro no encontrado.');
        if (!in_array($estado, ['registrado', 'pagado', 'anulado'], true)) throw new Exception('Estado no válido.');
        $this->repo->setEstado($id, $idEmpresa, $estado, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'ESTADO_' . strtoupper($estado), 'vacaciones', $id, $antes, ['estado' => $estado]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repo->getDetalle($id, $idEmpresa);
        if (!$antes) throw new Exception('Registro no encontrado.');
        $afectaba = $this->esVerdadero($antes['afecta_rol'] ?? true);

        // No permitir eliminar vacaciones que ya afectan a un rol mensual pagado. Un
        // registro histórico (afecta_rol=false) nunca tocó ningún rol, así que se puede
        // eliminar libremente sin esta validación.
        if ($afectaba) {
            $this->bloquearSiRolPagado($idEmpresa, $antes['id_empleado'] ?? 0, $antes['periodo_anio'] ?? 0, $antes['periodo_mes'] ?? 0);
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'vacaciones', $id, $antes, null);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        if ($afectaba) {
            $this->sincronizarRol($idEmpresa, $antes['periodo_anio'] ?? 0, $antes['periodo_mes'] ?? 0, $idUsuario);
        }
    }

    private function esVerdadero($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 't', 'true', 'si', 'sí'], true);
    }

    /**
     * Lanza excepción si el empleado ya tiene un rol MENSUAL PAGADO para el período de la
     * vacación: no se puede crear/editar/eliminar una vacación que afectaría un rol ya pagado
     * (las vacaciones siempre alimentan el rol mensual). Silencioso si roles/egresos no está.
     */
    private function bloquearSiRolPagado(int $idEmpresa, $idEmpleado, $anio, $mes): void
    {
        $idEmpleado = (int) $idEmpleado;
        $anio = (int) $anio;
        $mes  = (int) $mes;
        if ($idEmpleado <= 0 || $mes < 1 || $anio < 2000) return;

        try {
            $pagado = (new \App\repositories\modulos\RolPagoRepository())
                ->existeRolPagadoPeriodo($idEmpresa, $idEmpleado, 'MENSUAL', $anio, $mes);
        } catch (\Throwable $e) {
            return; // roles/egresos no desplegado → sin restricción
        }

        if ($pagado) {
            throw new Exception('No se puede crear, editar ni eliminar estas vacaciones: el rol mensual de '
                . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio
                . ' del empleado ya está pagado. Anule el pago/rol de ese período si necesita modificarlas.');
        }
    }

    /** Auto-regenera el rol MENSUAL 'generado' del período afectado (silencioso si falla). */
    private function sincronizarRol(int $idEmpresa, $anio, $mes, int $idUsuario): void
    {
        if ((int) $mes < 1 || (int) $anio < 2000) return;
        try {
            $rolSvc = new RolPagoService(
                new \App\repositories\modulos\RolPagoRepository(),
                new \App\Rules\modulos\RolPagoRules(),
                $this->log
            );
            $rolSvc->regenerarAfectados($idEmpresa, 'rol', (int) $anio, (int) $mes, $idUsuario);
        } catch (\Throwable $e) {
            // Silencioso: la vacación ya se guardó.
        }
    }
}
