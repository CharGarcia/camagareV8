<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ActivoFijoCategoriaRepository;
use App\Rules\modulos\ActivoFijoCategoriaRules;
use App\Services\LogSistemaService;

class ActivoFijoCategoriaService
{
    public function __construct(
        private ActivoFijoCategoriaRepository $repository,
        private ActivoFijoCategoriaRules $rules,
        private LogSistemaService $logService
    ) {
    }

    public function getListado(int $idEmpresa, string $buscar = ''): array
    {
        return $this->repository->getListado($idEmpresa, $buscar);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getPorId($id, $idEmpresa);
    }

    public function getActivasParaSelect(int $idEmpresa): array
    {
        return $this->repository->getActivasParaSelect($idEmpresa);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->logService->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'crear',
                'activos_fijos_categorias',
                $id,
                null,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $actual = $this->repository->getPorId($id, $idEmpresa);
        if (!$actual) {
            throw new \Exception('Categoría de activos fijos no encontrada.');
        }

        $this->rules->validar($data, $id);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->update($id, $data);
            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'activos_fijos_categorias',
                $id,
                $actual,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $this->rules->validarEliminacion($id);
        $this->repository->softDelete($id, $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'activos_fijos_categorias', $id, null, null);
        return true;
    }
}
