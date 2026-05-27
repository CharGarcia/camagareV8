<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsientosTipoRepository;
use App\Rules\modulos\AsientosTipoRules;
use App\Services\LogSistemaService;
use App\core\Database;
use Exception;

class AsientosTipoService
{
    private AsientosTipoRepository $repository;
    private AsientosTipoRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        ?AsientosTipoRepository $repository = null,
        ?AsientosTipoRules $rules = null,
        ?LogSistemaService $logService = null
    ) {
        $this->repository = $repository ?? new AsientosTipoRepository();
        $this->rules      = $rules ?? new AsientosTipoRules();
        $this->logService = $logService ?? new LogSistemaService();
    }

    /**
     * Crea un asiento tipo con validación, transacción y auditoría.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $codigo = mb_strtoupper(trim($data['codigo']), 'UTF-8');
        if ($this->repository->codigoExiste($codigo)) {
            throw new Exception("Ya existe un asiento tipo con el código '{$codigo}'.");
        }

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();

            $insertData = [
                'tipo_asiento' => trim($data['tipo_asiento']),
                'referencia'   => trim($data['referencia']),
                'detalle'      => trim($data['detalle'] ?? ''),
                'codigo'       => $codigo,
                'tipo_cuenta'  => isset($data['tipo_cuenta']) ? trim($data['tipo_cuenta']) : null,
                'debe_haber'   => isset($data['debe_haber']) ? trim($data['debe_haber']) : 'debe'
            ];

            $id = $this->repository->guardarAsientoTipo($insertData, (int)$data['id_usuario']);

            // Auditoría global: id_empresa es null
            $this->logService->registrar(
                (int)$data['id_usuario'],
                null,
                'crear',
                'asientos_tipo',
                $id,
                null,
                $insertData
            );

            $pdo->commit();
            return $id;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza un asiento tipo con validación, transacción y auditoría.
     */
    public function actualizar(int $id, array $data): void
    {
        $this->rules->validar($data);

        $codigo = mb_strtoupper(trim($data['codigo']), 'UTF-8');
        if ($this->repository->codigoExiste($codigo, $id)) {
            throw new Exception("Ya existe otro asiento tipo con el código '{$codigo}'.");
        }

        $antes = $this->repository->getAsientoTipo($id);
        if (!$antes) {
            throw new Exception('El asiento tipo no existe o ha sido eliminado.');
        }

        // Validación de integridad referencial: No permitir cambiar código si ya está configurado
        if ($antes['codigo'] !== $codigo && $this->repository->estaEnUso($id)) {
            throw new Exception("No se puede cambiar el código identificador de este asiento tipo porque ya se encuentra en uso en las configuraciones contables.");
        }

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();

            $updateData = [
                'id'           => $id,
                'tipo_asiento' => trim($data['tipo_asiento']),
                'referencia'   => trim($data['referencia']),
                'detalle'      => trim($data['detalle'] ?? ''),
                'codigo'       => $codigo,
                'tipo_cuenta'  => isset($data['tipo_cuenta']) ? trim($data['tipo_cuenta']) : null,
                'debe_haber'   => isset($data['debe_haber']) ? trim($data['debe_haber']) : 'debe'
            ];

            $this->repository->guardarAsientoTipo($updateData, (int)$data['id_usuario']);

            // Auditoría global: id_empresa es null
            $this->logService->registrar(
                (int)$data['id_usuario'],
                null,
                'actualizar',
                'asientos_tipo',
                $id,
                $antes,
                $updateData
            );

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Elimina lógicamente un asiento tipo con transacción y auditoría.
     */
    public function eliminar(int $id, int $idUsuario): void
    {
        $antes = $this->repository->getAsientoTipo($id);
        if (!$antes) {
            throw new Exception('El asiento tipo no existe o ya ha sido eliminado.');
        }

        // Validación de integridad referencial: No permitir eliminar si ya está configurado
        if ($this->repository->estaEnUso($id)) {
            throw new Exception("No se puede eliminar este asiento tipo de modelo predefinido porque ya se encuentra en uso en las configuraciones contables de las empresas.");
        }

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();

            $this->repository->eliminarAsientoTipo($id, $idUsuario);

            // Auditoría global: id_empresa es null
            $this->logService->registrar(
                $idUsuario,
                null,
                'eliminar',
                'asientos_tipo',
                $id,
                $antes,
                ['eliminado' => true]
            );

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtiene listado paginado para el controlador.
     */
    public function getListado(string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    /**
     * Obtiene un asiento tipo por ID.
     */
    public function findById(int $id): ?array
    {
        return $this->repository->getAsientoTipo($id);
    }
}
