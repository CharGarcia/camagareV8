<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\EmpleadoRepository;
use App\repositories\modulos\GastoPersonalRepository;
use App\Rules\modulos\EmpleadoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\RolPagoService;
use App\repositories\modulos\RolPagoRepository;
use App\Rules\modulos\RolPagoRules;
use App\Services\modulos\ConfirmacionRolRequeridaException;
use Exception;

class EmpleadoService
{
    private EmpleadoRepository $repository;
    private EmpleadoRules $rules;
    private LogSistemaService $logService;

    public function __construct(EmpleadoRepository $repository, EmpleadoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    /**
     * Obtiene el listado de empleados filtrado por empresa.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /**
     * Obtiene el detalle completo del empleado, incluyendo periodos y rubros.
     */
    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $empleado = $this->repository->findById($id, $idEmpresa);
        if (!$empleado) return null;

        $empleado['periodos'] = $this->repository->getPeriodos($id, $idEmpresa);
        $empleado['rubros'] = $this->repository->getRubrosFijos($id, $idEmpresa);
        $empleado['asignaciones_horario'] = $this->repository->getAsignacionesHorario($id, $idEmpresa);
        $empleado['gastos_personales'] = $this->gastoRepo()->getPorEmpleado($id, $idEmpresa);

        return $empleado;
    }

    /**
     * Crea un empleado validando duplicados y reglas de negocio.
     */
    public function crear(array $data): int
    {
        $this->rules->validate($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $identificacion = trim((string)$data['identificacion']);

        if ($this->repository->existsByIdentificacion($identificacion, $idEmpresa)) {
            throw new Exception("Ya existe un empleado con la identificación '{$identificacion}' en esta empresa.");
        }

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);

            // Sincronizar periodos y rubros si vienen en el data
            if (!empty($data['periodos'])) {
                $this->repository->syncPeriodos($id, $idEmpresa, $data['periodos'], $idUsuario);
            }
            if (!empty($data['rubros_fijos'])) {
                $this->repository->syncRubrosFijos($id, $idEmpresa, $data['rubros_fijos'], $idUsuario);
            }
            if (isset($data['asignaciones_horario'])) {
                $this->repository->syncAsignacionesHorario($id, $idEmpresa, $data['asignaciones_horario'], $idUsuario);
            }
            if (isset($data['gastos_personales'])) {
                $this->gastoRepo()->sync($id, $idEmpresa, $data['gastos_personales'], $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'empleados', $id, null, $data);

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza los datos de un empleado.
     */
    /**
     * Actualiza el empleado. Devuelve ['regenerados' => string[]] con los roles abiertos
     * que se regeneraron por el cambio.
     * @throws ConfirmacionRolRequeridaException si el cambio afecta al rol y hay roles
     *   abiertos sin pagar y aún no se confirmó ($data['confirmar_roles']).
     */
    public function actualizar(int $id, int $idEmpresa, array $data): array
    {
        $this->rules->validate($data);

        $idUsuario = (int) $data['id_usuario'];
        $identificacion = trim((string)$data['identificacion']);
        if ($this->repository->existsByIdentificacion($identificacion, $idEmpresa, $id)) {
            throw new Exception("Ya existe otro empleado con la identificación '{$identificacion}' en esta empresa.");
        }

        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception("Empleado no encontrado.");
        }

        $rolSvc = new RolPagoService(new RolPagoRepository(), new RolPagoRules(), $this->logService);
        $periodosViejos = $this->repository->getPeriodos($id, $idEmpresa);
        $periodosNuevos = $data['periodos'] ?? null;

        // 1) Bloqueo duro: fechas que afectarían un rol YA PAGADO.
        if ($periodosNuevos !== null) {
            $rolSvc->validarPeriodosContraRolesPagados($idEmpresa, $id, $periodosNuevos, $periodosViejos);
        }

        // 2) Confirmación: si el cambio afecta al rol y hay roles abiertos (generado sin pagar),
        //    pedir confirmación; al confirmar se regenerarán tras guardar.
        $afecta = $this->cambioAfectaRol($old, $data, $periodosViejos, $periodosNuevos, $id, $idEmpresa);
        $rolesAbiertos = $afecta ? $rolSvc->getRolesAbiertosEmpleado($idEmpresa, $id) : [];
        if (!empty($rolesAbiertos) && empty($data['confirmar_roles'])) {
            throw new ConfirmacionRolRequeridaException($rolesAbiertos);
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);

            // Sincronizar periodos y rubros si vienen en el data
            if (isset($data['periodos'])) {
                $this->repository->syncPeriodos($id, $idEmpresa, $data['periodos'], $idUsuario);
            }
            if (isset($data['rubros_fijos'])) {
                $this->repository->syncRubrosFijos($id, $idEmpresa, $data['rubros_fijos'], $idUsuario);
            }
            if (isset($data['asignaciones_horario'])) {
                $this->repository->syncAsignacionesHorario($id, $idEmpresa, $data['asignaciones_horario'], $idUsuario);
            }
            if (isset($data['gastos_personales'])) {
                $this->gastoRepo()->sync($id, $idEmpresa, $data['gastos_personales'], $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'empleados', $id, $old, $data);

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }

        // 3) Regenerar los roles abiertos afectados (fuera de la transacción; un fallo no revierte el guardado).
        $regenerados = [];
        foreach ($rolesAbiertos as $r) {
            try {
                $rolSvc->generar((int) $r['id'], $idEmpresa, $idUsuario);
                $regenerados[] = $r['tipo_rol'] . ' ' . str_pad((string) $r['periodo_mes'], 2, '0', STR_PAD_LEFT) . '/' . $r['periodo_anio'];
            } catch (\Throwable $e) {
                // La regeneración no debe romper el guardado del empleado.
            }
        }
        return ['regenerados' => $regenerados];
    }

    /** ¿El cambio toca algún dato que interviene en el cálculo del rol? */
    private function cambioAfectaRol(array $old, array $data, array $periodosViejos, ?array $periodosNuevos, int $id, int $idEmpresa): bool
    {
        foreach (['sueldo_base', 'valor_semanal', 'valor_quincena', 'aporte_personal', 'aporte_patronal'] as $f) {
            if (array_key_exists($f, $data) && round((float) ($old[$f] ?? 0), 2) !== round((float) $data[$f], 2)) {
                return true;
            }
        }
        foreach (['fondos_reserva', 'decimo_tercero', 'decimo_cuarto'] as $f) {
            if (array_key_exists($f, $data) && (string) ($old[$f] ?? '') !== (string) ($data[$f] ?? '')) {
                return true;
            }
        }
        // Excluir del cálculo de IR: en BD es booleano, en el formulario llega 'si'/'no'.
        if (array_key_exists('excluir_calculo_ir', $data)) {
            $bool = fn($v) => in_array($v, [true, 1, '1', 't', 'true', 'si'], true);
            if ($bool($old['excluir_calculo_ir'] ?? false) !== $bool($data['excluir_calculo_ir'])) {
                return true;
            }
        }
        // La proyección de gastos personales cambia la retención de IR del rol mensual.
        if (isset($data['gastos_personales'])
            && $this->gastosPersonalesCambiaron($id, $idEmpresa, $data['gastos_personales'])) {
            return true;
        }
        return $periodosNuevos !== null && $this->periodosCambiaron($periodosViejos, $periodosNuevos);
    }

    /**
     * ¿Cambió la proyección de gastos personales de algún año? Se comparan total,
     * cargas familiares y caso especial: los tres mueven la rebaja de IR.
     */
    private function gastosPersonalesCambiaron(int $id, int $idEmpresa, array $nuevos): bool
    {
        $esVerdadero = fn($v) => in_array($v, [true, 1, '1', 't', 'true', 'si'], true);

        $norm = function (array $filas, bool $desdeBd) use ($esVerdadero): array {
            $out = [];
            foreach ($filas as $f) {
                $anio = (int) ($f['anio'] ?? 0);
                if ($anio <= 0) continue;
                if ($desdeBd) {
                    $total = (float) ($f['total_proyectado'] ?? 0);
                } else {
                    $total = 0.0;
                    foreach (GastoPersonalRepository::RUBROS as $r) {
                        $total += max(0.0, (float) ($f[$r] ?? 0));
                    }
                }
                $out[$anio] = round($total, 2)
                    . '|' . max(0, (int) ($f['numero_cargas_familiares'] ?? 0))
                    . '|' . ($esVerdadero($f['caso_especial'] ?? false) ? '1' : '0');
            }
            ksort($out);
            return $out;
        };

        $viejos = $this->gastoRepo()->getPorEmpleado($id, $idEmpresa);

        return $norm($viejos, true) !== $norm($nuevos, false);
    }

    private function gastoRepo(): GastoPersonalRepository
    {
        return $this->gastoRepository ??= new GastoPersonalRepository();
    }

    private ?GastoPersonalRepository $gastoRepository = null;

    private function periodosCambiaron(array $viejos, array $nuevos): bool
    {
        $norm = function (array $ps): array {
            $out = [];
            foreach ($ps as $p) {
                if (empty($p['fecha_ingreso'])) continue;
                $out[] = substr((string) $p['fecha_ingreso'], 0, 10) . '|' . substr((string) ($p['fecha_salida'] ?? ''), 0, 10);
            }
            sort($out);
            return $out;
        };
        return $norm($viejos) !== $norm($nuevos);
    }

    /**
     * Obtiene los valores por defecto del IESS desde la tabla salarios.
     */
    public function getIessDefaults(): array
    {
        $anio = (int)date('Y');
        // Usamos una consulta directa o un repositorio de ser necesario. 
        // Siguiendo el descubrimiento previo: tabla 'salarios', columna 'ano'.
        try {
            $db = \App\core\Database::getConnection();
            $st = $db->prepare("SELECT aporte_personal, aporte_patronal, sbu FROM salarios WHERE ano = :a AND status = 1 LIMIT 1");
            $st->execute([':a' => $anio]);
            $res = $st->fetch(\PDO::FETCH_ASSOC);
            
            if ($res) return $res;
            
            // Fallback si no hay año actual
            return ['aporte_personal' => 9.45, 'aporte_patronal' => 11.15, 'sbu' => 450.00];
        } catch (\PDOException $e) {
            return ['aporte_personal' => 9.45, 'aporte_patronal' => 11.15, 'sbu' => 450.00];
        }
    }

    /**
     * Realiza la eliminación lógica del registro.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception("Empleado no encontrado.");
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'empleados', $id, $old, null);

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
