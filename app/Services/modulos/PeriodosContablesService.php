<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\PeriodosContablesRepository;
use App\Rules\modulos\PeriodosContablesRules;
use App\Services\LogSistemaService;
use Exception;

class PeriodosContablesService
{
    private PeriodosContablesRepository $repository;
    private PeriodosContablesRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        PeriodosContablesRepository $repository,
        PeriodosContablesRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $idEmpresa = (int) $data['id_empresa'];

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa'    => $idEmpresa,
                'nombre'        => trim($data['nombre']),
                'fecha_inicial' => trim($data['fecha_inicial']),
                'fecha_final'   => trim($data['fecha_final']),
                'status'        => isset($data['status']) ? (bool)$data['status'] : true,
                'created_by'    => (int)$data['id_usuario'],
                'id_usuario'    => (int)$data['id_usuario'],
            ];

            $id = $this->repository->create($insertData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'periodos_contables',
                $id,
                null,
                $insertData
            );

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) throw new Exception('El periodo contable no existe.');

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'nombre'        => trim($data['nombre']),
                'fecha_inicial' => trim($data['fecha_inicial']),
                'fecha_final'   => trim($data['fecha_final']),
                'status'        => isset($data['status']) ? (bool)$data['status'] : true,
                'updated_by'    => (int)$data['id_usuario']
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'periodos_contables',
                $id,
                $antes,
                $updateData
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) throw new Exception('El periodo contable no existe.');

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'periodos_contables',
                $id,
                $antes,
                ['eliminado' => true, 'deleted_by' => $idUsuario]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Revisa si una fecha está en un periodo cerrado.
     */
    public function validarFechaPermitida(string $fecha, int $idEmpresa, ?string $mensajeCustom = null): void
    {
        $esCerrado = $this->repository->isFechaEnPeriodoCerrado($fecha, $idEmpresa);
        if ($esCerrado) {
            $msg = $mensajeCustom ?? "La fecha $fecha corresponde a un periodo contable cerrado. No se permiten transacciones.";
            throw new Exception($msg);
        }
    }
}
