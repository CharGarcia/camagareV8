<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\PlanCuentaRepository;
use App\Rules\modulos\PlanCuentaRules;
use App\Services\LogSistemaService;
use Exception;

class PlanCuentaService
{
    private PlanCuentaRepository $repository;
    private PlanCuentaRules $rules;
    private LogSistemaService $logService;

    public function __construct(PlanCuentaRepository $repository, PlanCuentaRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function crear(array $data): int
    {
        $this->rules->validate($data);
        
        $this->repository->beginTransaction();
        try {
            $data['created_by']  = $data['id_usuario'];
            $id = $this->repository->create($data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'CREAR',
                'plan_cuentas',
                (int)$id,
                null, // antes
                $data // despues
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
        $this->rules->validate($data);
        $old = $this->repository->findById($id, $idEmpresa);

        $this->repository->beginTransaction();
        try {
            $data['updated_by'] = $data['id_usuario'];
            $this->repository->update($id, $idEmpresa, $data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$idEmpresa,
                'ACTUALIZAR',
                'plan_cuentas',
                $id,
                $old,
                $data
            );
            
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->findById($id, $idEmpresa);
        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'plan_cuentas',
                $id,
                $old,
                null
            );
            
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
