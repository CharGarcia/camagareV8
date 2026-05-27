<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\BodegaRepository;
use App\Rules\modulos\BodegaRules;
use App\Services\LogSistemaService;
use Exception;

class BodegaService
{
    private BodegaRepository $repository;
    private BodegaRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        BodegaRepository $repository,
        BodegaRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $idEmpresa = (int) $data['id_empresa'];

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa' => $idEmpresa,
                'id_usuario' => (int)$data['id_usuario'],
                'created_by' => (int)$data['id_usuario'],
                'nombre'     => mb_strtoupper(trim($data['nombre']), 'UTF-8'),
                'status'     => isset($data['status']) ? (bool)$data['status'] : true,
            ];

            $id = $this->repository->create($insertData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'bodegas',
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

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) throw new Exception('Bodega no existe.');

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'nombre'     => mb_strtoupper(trim($data['nombre']), 'UTF-8'),
                'status'     => isset($data['status']) ? (bool)$data['status'] : true,
                'updated_by' => (int)$data['id_usuario']
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'bodegas',
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
        if (!$antes) throw new Exception('Bodega no existe.');

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'bodegas',
                $id,
                $antes,
                ['eliminado' => true, 'deleted_by' => $idUsuario]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function getUsuariosAcceso(int $idBodega, int $idEmpresa): array
    {
        return $this->repository->getUsuariosAcceso($idBodega, $idEmpresa);
    }

    public function guardarAccesos(int $idBodega, int $idEmpresa, array $accesos, int $idUsuarioLogueado): void
    {
        $this->repository->beginTransaction();
        try {
            // Procesar cada acceso para manejar correctamente el 'default'
            foreach ($accesos as $acc) {
                if (!empty($acc['id_usuario']) && !empty($acc['es_default'])) {
                    // Si este usuario se marca como default en esta bodega, lo quitamos de las demás
                    $this->repository->clearDefaultForUser((int)$acc['id_usuario'], $idEmpresa);
                }
            }

            // Sincronizar en la tabla pivot
            $this->repository->saveUsuariosAcceso($idBodega, $idEmpresa, $accesos, $idUsuarioLogueado);

            // Registrar en auditoría (simplificado por ahora, se podría detallar más)
            $this->logService->registrar(
                $idUsuarioLogueado,
                $idEmpresa,
                'actualizar_accesos',
                'bodegas',
                $idBodega,
                null,
                ['accesos_sincronizados' => count($accesos)]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function getBodegasPermitidas(int $idUsuario, int $idEmpresa, int $nivel): array
    {
        return $this->repository->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
    }

    /**
     * Valida si un usuario tiene acceso a una bodega específica.
     * Niveles 2 y 3 tienen acceso a todas. Nivel 1 solo a las asignadas.
     */
    public function validarAccesoUsuario(int $idUsuario, int $idBodega, int $idEmpresa, int $nivel): bool
    {
        if ($nivel >= 2) return true;

        $permitidas = $this->repository->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        foreach ($permitidas as $b) {
            if ((int)$b['id'] === $idBodega) return true;
        }
        return false;
    }
}
