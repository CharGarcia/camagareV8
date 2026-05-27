<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\VehiculoRepository;
use App\Rules\modulos\VehiculoRules;
use App\Services\LogSistemaService;
use Exception;

class VehiculoService
{
    private VehiculoRepository $repository;
    private VehiculoRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        VehiculoRepository $repository,
        VehiculoRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * Crea un vehículo con validación, transacción y auditoría.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $placa      = trim($data['placa']);

        if ($this->repository->existePlaca($idEmpresa, $placa)) {
            throw new Exception("Ya existe un vehículo con la placa '{$placa}' en su empresa.");
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa'  => $idEmpresa,
                'id_usuario'  => (int)$data['id_usuario'],
                'created_by'  => (int)$data['id_usuario'],
                'marca'       => mb_strtoupper(trim($data['marca']), 'UTF-8'),
                'placa'       => mb_strtoupper(trim($data['placa']), 'UTF-8'),
                'chasis'      => mb_strtoupper(trim($data['chasis'] ?? ''), 'UTF-8'),
                'anio'        => (int)($data['anio'] ?? 0),
                'propietario' => mb_strtoupper(trim($data['propietario']), 'UTF-8'),
                'estado'      => $data['estado'] ?? 'activo',
                'correo'      => trim($data['correo'] ?? ''),
                'telefono'    => trim($data['telefono'] ?? ''),
                'eliminado'   => false
            ];

            $id = $this->repository->create($insertData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'vehiculos',
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
     * Actualiza un vehículo con validación, transacción y auditoría.
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        $placa = trim($data['placa']);

        if ($this->repository->existePlaca($idEmpresa, $placa, $id)) {
            throw new Exception("Ya existe otro vehículo con la placa '{$placa}'.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El vehículo no existe o ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'marca'       => mb_strtoupper(trim($data['marca']), 'UTF-8'),
                'placa'       => mb_strtoupper(trim($data['placa']), 'UTF-8'),
                'chasis'      => mb_strtoupper(trim($data['chasis'] ?? ''), 'UTF-8'),
                'anio'        => (int)($data['anio'] ?? 0),
                'propietario' => mb_strtoupper(trim($data['propietario']), 'UTF-8'),
                'estado'      => $data['estado'] ?? 'activo',
                'correo'      => trim($data['correo'] ?? ''),
                'telefono'    => trim($data['telefono'] ?? ''),
                'updated_by'  => (int)$data['id_usuario']
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'vehiculos',
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
     * Elimina lógicamente un vehículo con transacción y auditoría.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El vehículo no existe o ya ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'vehiculos',
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
