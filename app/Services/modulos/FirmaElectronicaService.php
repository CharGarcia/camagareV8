<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FirmaElectronicaRepository;
use App\Rules\modulos\FirmaElectronicaRules;
use App\Services\LogSistemaService;
use Exception;

class FirmaElectronicaService
{
    private FirmaElectronicaRepository $repository;
    private FirmaElectronicaRules      $rules;
    private LogSistemaService          $logService;

    public function __construct(
        FirmaElectronicaRepository $repository,
        FirmaElectronicaRules      $rules,
        LogSistemaService          $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
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
            $id = $this->repository->create($data);

            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'crear',
                'firmas_electronicas',
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

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La firma electrónica no existe o ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);

            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'firmas_electronicas',
                $id,
                $antes,
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
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La firma electrónica no existe o ya ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'firmas_electronicas',
                $id,
                $antes,
                ['eliminado' => true]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
