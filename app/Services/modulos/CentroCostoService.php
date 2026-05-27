<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CentroCostoRepository;
use App\Rules\modulos\CentroCostoRules;
use App\Services\LogSistemaService;
use Exception;

class CentroCostoService
{
    private CentroCostoRepository $repository;
    private CentroCostoRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        CentroCostoRepository $repository,
        CentroCostoRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        
        $this->repository->beginTransaction();
        try {
            $data['created_by']  = $data['id_usuario'];
            $data['eliminado']   = false;
            
            $id = $this->repository->create($data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'CREAR',
                'centro_costos',
                $id,
                null,
                $data
            );
            
            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): bool
    {
        $this->rules->validar($data);
        
        $original = $this->repository->findById($id, $idEmpresa);
        if (!$original) {
            throw new Exception("Centro de costos no encontrado.");
        }

        $this->repository->beginTransaction();
        try {
            $data['updated_by'] = $data['id_usuario'];
            
            $res = $this->repository->update($id, $idEmpresa, $data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'ACTUALIZAR',
                'centro_costos',
                $id,
                $original,
                $data
            );
            
            $this->repository->commit();
            return $res;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $original = $this->repository->findById($id, $idEmpresa);
        if (!$original) {
            throw new Exception("Centro de costos no encontrado.");
        }

        $this->repository->beginTransaction();
        try {
            $res = $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'centro_costos',
                $id,
                $original,
                null
            );
            
            $this->repository->commit();
            return $res;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
