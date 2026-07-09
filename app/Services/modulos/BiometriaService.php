<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\BiometriaRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Credencial personal del empleado (QR token) y consentimiento biométrico.
 * El enrolamiento facial (descriptor) se agrega en Fase 3 sobre esta misma pieza.
 */
class BiometriaService
{
    private BiometriaRepository $repository;
    private LogSistemaService $logService;

    public function __construct(BiometriaRepository $repository, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->logService = $logService;
    }

    public function getByEmpleado(int $idEmpleado, int $idEmpresa): ?array
    {
        return $this->repository->getByEmpleado($idEmpleado, $idEmpresa);
    }

    /**
     * Enrola (o devuelve) la credencial personal del empleado. Idempotente:
     * si ya tiene credencial vigente, la retorna sin crear otra.
     */
    public function enrolar(int $idEmpleado, int $idEmpresa, int $idUsuario): array
    {
        $existente = $this->repository->getByEmpleado($idEmpleado, $idEmpresa);
        if ($existente) {
            return $existente;
        }

        $data = [
            'id_empresa'  => $idEmpresa,
            'id_empleado' => $idEmpleado,
            'qr_token'    => $this->generarTokenUnico(),
            'activo'      => true,
            'id_usuario'  => $idUsuario,
        ];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'empleados_biometria', $id, null, ['id_empleado' => $idEmpleado]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return $this->repository->getByEmpleado($idEmpleado, $idEmpresa) ?? $data;
    }

    /** Regenera el token personal (revoca el anterior). */
    public function regenerarToken(int $idEmpleado, int $idEmpresa, int $idUsuario): string
    {
        $bio = $this->repository->getByEmpleado($idEmpleado, $idEmpresa);
        if (!$bio) {
            return $this->enrolar($idEmpleado, $idEmpresa, $idUsuario)['qr_token'];
        }
        $token = $this->generarTokenUnico();
        $this->repository->beginTransaction();
        try {
            $this->repository->updateToken((int) $bio['id'], $idEmpresa, $token, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'REGENERAR_TOKEN', 'empleados_biometria', (int) $bio['id'], $bio, ['qr_token' => $token]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return $token;
    }

    /**
     * Enrola el rostro del empleado (guarda el descriptor facial) y registra el
     * consentimiento biométrico (LOPDP). Crea la credencial si no existe.
     */
    public function enrolarRostro(int $idEmpleado, int $idEmpresa, array $descriptor, int $idUsuario): void
    {
        if (count($descriptor) !== 128) {
            throw new Exception('El descriptor facial no es válido.');
        }
        $bio = $this->repository->getByEmpleado($idEmpleado, $idEmpresa);
        if (!$bio) {
            $bio = $this->enrolar($idEmpleado, $idEmpresa, $idUsuario);
        }
        $id = (int) $bio['id'];
        $this->repository->beginTransaction();
        try {
            $this->repository->setDescriptor($id, $idEmpresa, $descriptor, $idUsuario);
            $this->repository->setConsentimiento($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ENROLAR_ROSTRO', 'empleados_biometria', $id, null, ['id_empleado' => $idEmpleado]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Registra el consentimiento biométrico del empleado (LOPDP). */
    public function registrarConsentimiento(int $idEmpleado, int $idEmpresa, int $idUsuario): void
    {
        $bio = $this->repository->getByEmpleado($idEmpleado, $idEmpresa);
        if (!$bio) {
            throw new Exception('El empleado no tiene credencial de asistencia.');
        }
        $this->repository->setConsentimiento((int) $bio['id'], $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'CONSENTIMIENTO', 'empleados_biometria', (int) $bio['id'], $bio, null);
    }

    private function generarTokenUnico(): string
    {
        do {
            $token = 'EMP-' . bin2hex(random_bytes(16));
        } while ($this->repository->existeQrToken($token));
        return $token;
    }
}
