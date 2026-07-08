<?php

declare(strict_types=1);

namespace App\Services\modulos;

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

    public function __construct(VacacionRepository $repo, VacacionRules $rules, LogSistemaService $log)
    {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->log = $log;
        $this->calc = new VacacionCalculoService();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getDetalle($id, $idEmpresa);
    }

    /** Información de vacaciones de un empleado: antigüedad, derecho, saldo, sueldo. */
    public function getInfoEmpleado(int $idEmpleado, int $idEmpresa, ?int $excludeVacacion = null): array
    {
        $emp = $this->repo->getEmpleado($idEmpleado, $idEmpresa);
        if (!$emp) throw new Exception('Empleado no encontrado.');

        $fechaIngreso = $this->repo->getFechaIngreso($idEmpleado, $idEmpresa);
        $gozados = $this->repo->getDiasGozadosTotal($idEmpleado, $idEmpresa, $excludeVacacion);

        $ant = $fechaIngreso
            ? $this->calc->antiguedad($fechaIngreso)
            : ['anios_completos' => 0, 'anios_texto' => 'Sin fecha de ingreso', 'derecho_anio_actual' => 15, 'total_derecho' => 0.0];

        return [
            'id_empleado'        => $idEmpleado,
            'sueldo_base'        => (float) $emp['sueldo_base'],
            'fecha_ingreso'      => $fechaIngreso,
            'antiguedad'         => $ant['anios_texto'],
            'derecho_anio_actual' => $ant['derecho_anio_actual'],
            'total_derecho'      => $ant['total_derecho'],
            'dias_gozados_total' => round($gozados, 2),
            'saldo'              => $this->calc->saldo($ant['total_derecho'], $gozados),
        ];
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

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->create($data);
            $this->log->registrar((int) $data['id_usuario'], $idEmpresa, 'CREAR', 'vacaciones', $id, null, $data);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        $this->sincronizarRol($idEmpresa, $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0, (int) $data['id_usuario']);
        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $antes = $this->repo->getDetalle($id, $idEmpresa);
        if (!$antes) throw new Exception('Registro no encontrado.');
        $data = $this->prepararCalculos($data, $idEmpresa);
        $this->rules->validate($data);

        $this->repo->beginTransaction();
        try {
            $this->repo->update($id, $idEmpresa, $data);
            $this->log->registrar((int) $data['id_usuario'], $idEmpresa, 'ACTUALIZAR', 'vacaciones', $id, $antes, $data);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        $this->sincronizarRol($idEmpresa, $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0, (int) $data['id_usuario']);
        if ((int) ($antes['periodo_mes'] ?? 0) !== (int) ($data['periodo_mes'] ?? 0)
            || (int) ($antes['periodo_anio'] ?? 0) !== (int) ($data['periodo_anio'] ?? 0)) {
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
        $this->repo->beginTransaction();
        try {
            $this->repo->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'vacaciones', $id, $antes, null);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        $this->sincronizarRol($idEmpresa, $antes['periodo_anio'] ?? 0, $antes['periodo_mes'] ?? 0, $idUsuario);
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
