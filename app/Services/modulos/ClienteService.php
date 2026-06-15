<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ClienteRepository;
use App\Rules\modulos\ClienteRules;
use App\Services\LogSistemaService;
use Exception;

class ClienteService
{
    private ClienteRepository $repository;
    private ClienteRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        ClienteRepository $repository,
        ClienteRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    /**
     * Crea un cliente con validación, transacción y auditoría.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $tipoId    = (string) $data['tipo_id'];
        $idIdent   = (string) $data['identificacion'];

        if ($this->repository->existeIdentificacion($idEmpresa, $tipoId, $idIdent)) {
            throw new Exception('Ya existe un cliente con esta identificación y tipo para esta empresa.');
        }

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'clientes',
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

    /**
     * Actualiza un cliente con validación, transacción y auditoría.
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        if ($this->repository->existeIdentificacion($idEmpresa, (string)$data['tipo_id'], (string)$data['identificacion'], $id)) {
            throw new Exception('Ya existe otro cliente con esta identificación y tipo para esta empresa.');
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El cliente no existe o ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'clientes',
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

    /**
     * Elimina lógicamente un cliente con transacción y auditoría.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El cliente no existe o ya ha sido eliminado.');
        }

        // No permitir eliminar un cliente que ya está siendo usado en módulos operativos
        $usos = $this->repository->getUsosCliente($id, $idEmpresa);
        if (!empty($usos)) {
            $detalle = [];
            foreach ($usos as $modulo => $cantidad) {
                $detalle[] = "{$modulo} ({$cantidad})";
            }
            throw new Exception('No se puede eliminar el cliente porque tiene registros en: ' . implode(', ', $detalle) . '.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'clientes',
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

    /**
     * Proxy para el repositorio para listados.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /**
     * Obtiene estadísticas del cliente.
     */
    public function getEstadisticas(int $idCliente, int $idEmpresa): array
    {
        return $this->repository->getEstadisticas($idCliente, $idEmpresa);
    }
}
