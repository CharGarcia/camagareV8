<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AlumnoRepository;
use App\Rules\modulos\AlumnoRules;
use App\Services\LogSistemaService;
use Exception;

class AlumnoService
{
    private AlumnoRepository $repository;
    private AlumnoRules $rules;
    private LogSistemaService $logService;

    public function __construct(AlumnoRepository $repository, AlumnoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);

            if (isset($data['periodos'])) {
                $this->repository->syncPeriodos($id, $idEmpresa, $data['periodos'], $idUsuario);
            }
            if (isset($data['horarios'])) {
                $this->repository->syncHorarios($id, $idEmpresa, $data['horarios'], $idUsuario);
            }
            if (isset($data['servicios'])) {
                $this->repository->syncServicios($id, $idEmpresa, $data['servicios'], $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'alumnos', $id, null, $data);

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
        if (!$antes) {
            throw new Exception('El alumno no existe o ha sido eliminado.');
        }

        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);

            if (isset($data['periodos'])) {
                $this->repository->syncPeriodos($id, $idEmpresa, $data['periodos'], $idUsuario);
            }
            if (isset($data['horarios'])) {
                $this->repository->syncHorarios($id, $idEmpresa, $data['horarios'], $idUsuario);
            }
            if (isset($data['servicios'])) {
                $this->repository->syncServicios($id, $idEmpresa, $data['servicios'], $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'alumnos', $id, $antes, $data);

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
            throw new Exception('El alumno no existe o ya ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'alumnos', $id, $antes, null);
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

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $alumno = $this->repository->findById($id, $idEmpresa);
        if (!$alumno) {
            return null;
        }
        $alumno['periodos']   = $this->repository->getPeriodos($id, $idEmpresa);
        $alumno['horarios']   = $this->repository->getHorarios($id, $idEmpresa);
        $alumno['servicios']  = $this->repository->getServicios($id, $idEmpresa);
        $alumno['documentos'] = $this->repository->getDocumentos($id, $idEmpresa);
        return $alumno;
    }

    public function agregarDocumento(int $idAlumno, int $idEmpresa, array $data, int $idUsuario): array
    {
        $alumno = $this->repository->findById($idAlumno, $idEmpresa);
        if (!$alumno) {
            throw new Exception('El alumno no existe o ha sido eliminado.');
        }
        if (empty($data['tipo_documento'])) {
            throw new Exception('El tipo de documento es obligatorio.');
        }
        if (empty($data['ruta_archivo'])) {
            throw new Exception('No se recibió el archivo a adjuntar.');
        }

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->agregarDocumento($idAlumno, $idEmpresa, $data, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'alumnos_documentos', $id, null, $data);
            $this->repository->commit();
            return $this->repository->getDocumentos($idAlumno, $idEmpresa);
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminarDocumento(int $idDocumento, int $idAlumno, int $idEmpresa, int $idUsuario): array
    {
        $this->repository->beginTransaction();
        try {
            $antes = $this->repository->eliminarDocumento($idDocumento, $idAlumno, $idEmpresa, $idUsuario);
            if (!$antes) {
                throw new Exception('El documento no existe o ya fue eliminado.');
            }
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'alumnos_documentos', $idDocumento, $antes, null);
            $this->repository->commit();
            return $this->repository->getDocumentos($idAlumno, $idEmpresa);
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function getPuntosEmisionParaSelect(int $idEmpresa): array
    {
        return $this->repository->getPuntosEmisionParaSelect($idEmpresa);
    }
}
