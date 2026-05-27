<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CategoriaRepository;
use App\Rules\modulos\CategoriaRules;
use App\Services\LogSistemaService;
use Exception;

class CategoriaService
{
    private CategoriaRepository $repository;
    private CategoriaRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        CategoriaRepository $repository,
        CategoriaRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * Crea una categoría con validación, transacción y auditoría.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $nombre    = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre)) {
            throw new Exception("Ya existe una categoría con el nombre '{$nombre}' en su empresa.");
        }

        $this->repository->beginTransaction();
        try {
            // Aseguramos qué variables se van a crear
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
                'categorias',
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
     * Actualiza una categoría con validación, transacción y auditoría.
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        $nombre = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre, $id)) {
            throw new Exception("Ya existe otra categoría con el nombre '{$nombre}'.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La categoría no existe o ha sido eliminada.');
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
                'categorias',
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
     * Elimina lógicamente una categoría con transacción y auditoría.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La categoría no existe o ya ha sido eliminada.');
        }

        // Validación de uso en productos
        $usos = $this->repository->contarProductosAsignados($id, $idEmpresa);
        if ($usos > 0) {
            throw new Exception("No se puede eliminar la categoría porque tiene {$usos} productos(s) asociados.");
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'categorias',
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
