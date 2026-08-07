<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AlumnoNivelRepository;
use App\Rules\modulos\AlumnoNivelRules;
use App\Services\LogSistemaService;
use Exception;

class AlumnoNivelService
{
    private AlumnoNivelRepository $repository;
    private AlumnoNivelRules $rules;
    private LogSistemaService $logService;

    public function __construct(AlumnoNivelRepository $repository, AlumnoNivelRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $nombre    = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre)) {
            throw new Exception("Ya existe un nivel/curso con el nombre '{$nombre}' en su empresa.");
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa' => $idEmpresa,
                'id_usuario' => (int) $data['id_usuario'],
                'nombre'     => mb_strtoupper($nombre, 'UTF-8'),
                'orden'      => (int) ($data['orden'] ?? 0),
                'estado'     => $data['estado'] ?? 'activo',
            ];

            $id = $this->repository->create($insertData);

            $this->logService->registrar((int)$data['id_usuario'], $idEmpresa, 'crear', 'alumnos_niveles', $id, null, $insertData);

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

        $nombre = trim($data['nombre']);
        if ($this->repository->existeNombre($idEmpresa, $nombre, $id)) {
            throw new Exception("Ya existe otro nivel/curso con el nombre '{$nombre}'.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El nivel/curso no existe o ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'nombre'     => mb_strtoupper($nombre, 'UTF-8'),
                'orden'      => (int) ($data['orden'] ?? 0),
                'estado'     => $data['estado'] ?? 'activo',
                'id_usuario' => (int) $data['id_usuario'],
            ];

            $this->repository->update($id, $idEmpresa, $updateData);

            $this->logService->registrar((int)$data['id_usuario'], $idEmpresa, 'actualizar', 'alumnos_niveles', $id, $antes, $updateData);

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El nivel/curso no existe o ya ha sido eliminado.');
        }

        $usos = $this->repository->contarAlumnosAsignados($id, $idEmpresa);
        if ($usos > 0) {
            throw new Exception("No se puede eliminar el nivel/curso porque tiene {$usos} alumno(s) matriculados.");
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'alumnos_niveles', $id, $antes, ['eliminado' => true]);
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

    public function getParaSelect(int $idEmpresa): array
    {
        return $this->repository->getParaSelect($idEmpresa);
    }
}
