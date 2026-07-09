<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsistenciaPuntoRepository;
use App\Rules\modulos\AsistenciaPuntoRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Puntos de servicio y generación de su QR de ubicación.
 */
class AsistenciaPuntoService
{
    private AsistenciaPuntoRepository $repository;
    private AsistenciaPuntoRules $rules;
    private LogSistemaService $logService;

    public function __construct(AsistenciaPuntoRepository $repository, AsistenciaPuntoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        return $this->repository->findById($id, $idEmpresa);
    }

    public function crear(array $data): int
    {
        $this->rules->validate($data);
        $data['qr_token'] = $this->generarTokenUnico();

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'asistencia_puntos', $id, null, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validate($data);

        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Punto de servicio no encontrado.');
        }

        $idUsuario = (int) $data['id_usuario'];
        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'asistencia_puntos', $id, $old, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Punto de servicio no encontrado.');
        }
        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'asistencia_puntos', $id, $old, null);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Regenera el QR del punto (invalida el anterior). */
    public function regenerarQr(int $id, int $idEmpresa, int $idUsuario): string
    {
        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Punto de servicio no encontrado.');
        }
        $token = $this->generarTokenUnico();
        $data = array_merge($old, ['qr_token' => $token, 'id_usuario' => $idUsuario]);
        $this->repository->beginTransaction();
        try {
            // update() no toca qr_token; se actualiza con una sentencia dedicada.
            $st = $this->repository->getDb()->prepare(
                "UPDATE asistencia_puntos SET qr_token = :t, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :emp AND eliminado = false"
            );
            $st->execute([':t' => $token, ':u' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
            $this->logService->registrar($idUsuario, $idEmpresa, 'REGENERAR_QR', 'asistencia_puntos', $id, $old, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return $token;
    }

    private function generarTokenUnico(): string
    {
        do {
            $token = 'PT-' . bin2hex(random_bytes(16));
        } while ($this->repository->existeQrToken($token));
        return $token;
    }
}
