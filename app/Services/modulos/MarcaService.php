<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MarcaRepository;
use App\Rules\modulos\MarcaRules;
use App\Services\LogSistemaService;
use Exception;

class MarcaService
{
    private MarcaRepository $repository;
    private MarcaRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        MarcaRepository $repository,
        MarcaRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * Crea una marca con validación, transacción y auditoría.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $nombre    = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre)) {
            throw new Exception("Ya existe una marca con el nombre '{$nombre}' en su empresa.");
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa' => $idEmpresa,
                'id_usuario' => (int)$data['id_usuario'],
                'created_by' => (int)$data['id_usuario'],
                'nombre'     => mb_strtoupper($nombre, 'UTF-8'),
                'status'     => (int)($data['status'] ?? 1),
                'eliminado'  => false
            ];

            $id = $this->repository->create($insertData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'marcas',
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

    /**
     * Actualiza una marca con validación, transacción y auditoría.
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        $nombre = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre, $id)) {
            throw new Exception("Ya existe otra marca con el nombre '{$nombre}'.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La marca no existe o ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'nombre'     => mb_strtoupper($nombre, 'UTF-8'),
                'status'     => (int)($data['status'] ?? 1),
                'updated_by' => (int)$data['id_usuario']
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'marcas',
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

    /**
     * Elimina lógicamente una marca con transacción y auditoría.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La marca no existe o ya ha sido eliminada.');
        }

        // Validación de uso en productos
        $usos = $this->repository->contarProductosAsignados($id, $idEmpresa);
        if ($usos > 0) {
            throw new Exception("No se puede eliminar la marca porque tiene {$usos} productos(s) asociados.");
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'marcas',
                $id,
                $antes,
                ['eliminado' => true, 'status' => 0]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Proxy para el repositorio para listados.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }
}
