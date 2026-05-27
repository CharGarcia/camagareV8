<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CitaConfiguracionRepository;
use App\Rules\modulos\CitaConfiguracionRules;
use App\Services\LogSistemaService;
use Exception;

class CitaConfiguracionService
{
    public function __construct(
        private CitaConfiguracionRepository $repository,
        private CitaConfiguracionRules $rules,
        private LogSistemaService $logService
    ) {}

    // ─── TIPOS DE CITA ────────────────────────────────────────────────────────

    public function getTipos(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getTipos($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function guardarTipo(array $data): int
    {
        $this->rules->validarTipo($data);

        $idEmpresa = (int) $data['id_empresa'];
        $id        = (int) ($data['id'] ?? 0);

        $idRecursos  = array_map('intval', (array) ($data['id_recursos'] ?? []));
        $idUsuario   = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            if ($id > 0) {
                $antes = $this->repository->getTipoPorId($id, $idEmpresa);
                if (!$antes) throw new Exception('Tipo de cita no encontrado.');
                $this->repository->updateTipo($id, $idEmpresa, $data);
                $this->repository->setRecursosDeTipo($id, $idEmpresa, $idRecursos, $idUsuario);
                $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'citas_tipos', $id, $antes, $data);
                $this->repository->commit();
                return $id;
            }
            $newId = $this->repository->createTipo($data);
            $this->repository->setRecursosDeTipo($newId, $idEmpresa, $idRecursos, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'citas_tipos', $newId, null, $data);
            $this->repository->commit();
            return $newId;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminarTipo(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->getTipoPorId($id, $idEmpresa);
        if (!$antes) throw new Exception('Tipo de cita no encontrado.');

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteTipo($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'citas_tipos', $id, $antes, []);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    // ─── RECURSOS ─────────────────────────────────────────────────────────────

    public function getRecursos(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getRecursos($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function getRecursosActivos(int $idEmpresa): array
    {
        return $this->repository->getRecursosActivos($idEmpresa);
    }

    public function guardarRecurso(array $data): int
    {
        $this->rules->validarRecurso($data);

        $idEmpresa = (int) $data['id_empresa'];
        $id        = (int) ($data['id'] ?? 0);

        $this->repository->beginTransaction();
        try {
            if ($id > 0) {
                $antes = $this->repository->getRecursoPorId($id, $idEmpresa);
                if (!$antes) throw new Exception('Recurso no encontrado.');
                $this->repository->updateRecurso($id, $idEmpresa, $data);
                $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'actualizar', 'citas_recursos', $id, $antes, $data);
                $this->repository->commit();
                return $id;
            }
            $newId = $this->repository->createRecurso($data);
            $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'crear', 'citas_recursos', $newId, null, $data);
            $this->repository->commit();
            return $newId;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminarRecurso(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->getRecursoPorId($id, $idEmpresa);
        if (!$antes) throw new Exception('Recurso no encontrado.');

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteRecurso($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'citas_recursos', $id, $antes, []);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    // ─── HORARIOS ─────────────────────────────────────────────────────────────

    public function getHorarios(int $idEmpresa, ?int $idRecurso = null): array
    {
        return $this->repository->getHorarios($idEmpresa, $idRecurso);
    }

    public function guardarHorario(array $data): int
    {
        $this->rules->validarHorario($data);

        $idEmpresa = (int) $data['id_empresa'];
        $id        = (int) ($data['id'] ?? 0);

        $this->repository->beginTransaction();
        try {
            if ($id > 0) {
                $antes = $this->repository->getHorarioPorId($id, $idEmpresa);
                if (!$antes) throw new Exception('Horario no encontrado.');
                $this->repository->updateHorario($id, $idEmpresa, $data);
                $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'actualizar', 'citas_horarios', $id, $antes, $data);
                $this->repository->commit();
                return $id;
            }
            $newId = $this->repository->createHorario($data);
            $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'crear', 'citas_horarios', $newId, null, $data);
            $this->repository->commit();
            return $newId;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminarHorario(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->getHorarioPorId($id, $idEmpresa);
        if (!$antes) throw new Exception('Horario no encontrado.');

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteHorario($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'citas_horarios', $id, $antes, []);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    // ─── PORTAL ───────────────────────────────────────────────────────────────

    public function getPortalConfig(int $idEmpresa): ?array
    {
        return $this->repository->getPortalConfig($idEmpresa);
    }

    public function guardarPortal(array $data): void
    {
        $this->rules->validarPortal($data);

        $idEmpresa = (int) $data['id_empresa'];

        if ($this->repository->slugExists($data['slug'], $idEmpresa)) {
            throw new Exception('El slug ya está en uso por otra empresa. Elige uno diferente.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->savePortalConfig($idEmpresa, $data);
            $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'guardar_portal', 'citas_config_portal', 0, null, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
