<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MesaRepository;
use App\Rules\modulos\MesaRules;
use App\Services\LogSistemaService;
use Exception;

class MesaService
{
    private MesaRepository $repository;
    private MesaRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        MesaRepository $repository,
        MesaRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $nombre    = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre)) {
            throw new Exception("Ya existe una mesa con el nombre '{$nombre}' en su empresa.");
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa' => $idEmpresa,
                'id_usuario' => (int)$data['id_usuario'],
                'created_by' => (int)$data['id_usuario'],
                'nombre'     => mb_strtoupper($nombre, 'UTF-8'),
                'estado'     => $data['estado'] ?? 'disponible',
                'eliminado'  => false
            ];

            $id = $this->repository->create($insertData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'mesas',
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

        $nombre = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre, $id)) {
            throw new Exception("Ya existe otra mesa con el nombre '{$nombre}'.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La mesa no existe o ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'nombre'     => mb_strtoupper($nombre, 'UTF-8'),
                'estado'     => $data['estado'] ?? 'disponible',
                'updated_by' => (int)$data['id_usuario']
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'mesas',
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
        if (!$antes) {
            throw new Exception('La mesa no existe o ya ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'mesas',
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

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function findById(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getDetalleCompleto($id, $idEmpresa);
    }
}
