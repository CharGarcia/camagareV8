<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsistenciaHorarioRepository;
use App\Rules\modulos\AsistenciaHorarioRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Horarios/turnos y su asignación a empleados (con punto de servicio).
 */
class AsistenciaHorarioService
{
    private AsistenciaHorarioRepository $repository;
    private AsistenciaHorarioRules $rules;
    private LogSistemaService $logService;

    public function __construct(AsistenciaHorarioRepository $repository, AsistenciaHorarioRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        return $this->repository->findById($id, $idEmpresa);
    }

    public function crear(array $data): int
    {
        $this->rules->validate($data);
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'asistencia_horarios', $id, null, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validate($data);
        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Horario no encontrado.');
        }
        $idUsuario = (int) $data['id_usuario'];
        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'asistencia_horarios', $id, $old, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Horario no encontrado.');
        }
        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'asistencia_horarios', $id, $old, null);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Asignación de horario a empleados
    // ------------------------------------------------------------------

    public function asignar(array $data): int
    {
        $this->rules->validateAsignacion($data);
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->asignar($data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ASIGNAR', 'asistencia_empleado_horario', $id, null, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return $id;
    }

    public function eliminarAsignacion(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->repository->beginTransaction();
        try {
            $this->repository->eliminarAsignacion($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'asistencia_empleado_horario', $id, null, null);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function getHorarioVigente(int $idEmpleado, int $idEmpresa, string $fecha): ?array
    {
        return $this->repository->getHorarioVigente($idEmpleado, $idEmpresa, $fecha);
    }
}
