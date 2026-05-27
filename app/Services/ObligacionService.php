<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\ObligacionRepository;
use App\Rules\ObligacionRules;
use App\Services\LogSistemaService;
use Exception;

class ObligacionService
{
    private ObligacionRepository $repository;
    private ObligacionRules      $rules;
    private LogSistemaService    $logService;

    public function __construct(
        ObligacionRepository $repository,
        ObligacionRules      $rules,
        LogSistemaService    $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * Proxy de listado.
     */
    public function getListado(string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    /**
     * Todas las obligaciones activas para selector.
     */
    public function getAllActivas(): array
    {
        return $this->repository->getAllActivas();
    }

    /**
     * Crear una obligación.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $nombre = trim($data['nombre']);
        if ($this->repository->existeNombre($nombre)) {
            throw new Exception("Ya existe una obligación con el nombre «{$nombre}».");
        }

        $idUsuario = (int) $data['created_by'];

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'nombre'      => mb_strtoupper($nombre, 'UTF-8'),
                'descripcion' => trim($data['descripcion'] ?? ''),
                'status'      => (int) ($data['status'] ?? 1),
                'created_by'  => $idUsuario,
            ];

            $id = $this->repository->create($insertData);

            $this->logService->registrar($idUsuario, null, 'crear', 'cat_obligaciones', $id, null, $insertData);

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Actualizar una obligación.
     */
    public function actualizar(int $id, array $data): void
    {
        $this->rules->validar($data);

        $nombre = trim($data['nombre']);
        if ($this->repository->existeNombre($nombre, $id)) {
            throw new Exception("Ya existe otra obligación con el nombre «{$nombre}».");
        }

        $antes = $this->repository->findByIdGlobal($id);
        if (!$antes) {
            throw new Exception('La obligación no existe o fue eliminada.');
        }

        $idUsuario = (int) $data['updated_by'];

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'nombre'      => mb_strtoupper($nombre, 'UTF-8'),
                'descripcion' => trim($data['descripcion'] ?? ''),
                'status'      => (int) ($data['status'] ?? 1),
                'updated_by'  => $idUsuario,
            ];

            $this->repository->update($id, $updateData);
            $this->logService->registrar($idUsuario, null, 'actualizar', 'cat_obligaciones', $id, $antes, $updateData);

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Eliminar lógicamente una obligación.
     */
    public function eliminar(int $id, int $idUsuario): void
    {
        $antes = $this->repository->findByIdGlobal($id);
        if (!$antes) {
            throw new Exception('La obligación no existe o ya fue eliminada.');
        }

        $usos = $this->repository->contarTareasAsignadas($id);
        if ($usos > 0) {
            throw new Exception("No se puede eliminar la obligación porque tiene {$usos} tarea(s) asociada(s).");
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idUsuario);
            $this->logService->registrar($idUsuario, null, 'eliminar', 'cat_obligaciones', $id, $antes, ['eliminado' => true]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
