<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\TallerDepartamentoRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Administración del catálogo de departamentos del taller.
 *
 * Cada taller define sus propios departamentos (mecánica, enderezada, pintura,
 * armado…). Cada uno tiene su pantalla de tablet y aparece como columna en el
 * tablero.
 *
 * El checklist de recepción vive en su propio módulo (TallerChecklistService).
 */
class TallerDepartamentoService
{
    private TallerDepartamentoRepository $repository;
    private LogSistemaService $logService;

    public function __construct(TallerDepartamentoRepository $repository, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getActivos(int $idEmpresa): array
    {
        return $this->repository->getActivos($idEmpresa);
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    public function crear(array $d): int
    {
        $this->validar($d);

        $idEmpresa = (int) $d['id_empresa'];
        if ($this->repository->existeNombre($idEmpresa, trim((string) $d['nombre']))) {
            throw new Exception('Ya existe un departamento con ese nombre.');
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $id = $this->repository->create($d);
            $this->logService->registrar((int) $d['id_usuario'], $idEmpresa, 'CREAR_DEPARTAMENTO_TALLER', 'taller_departamentos', $id, null, $d);
            $db->commit();
            return $id;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $d): void
    {
        $this->validar($d);

        $actual = $this->repository->find($id, $idEmpresa);
        if (!$actual) {
            throw new Exception('Departamento no encontrado.');
        }
        if ($this->repository->existeNombre($idEmpresa, trim((string) $d['nombre']), $id)) {
            throw new Exception('Ya existe otro departamento con ese nombre.');
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->update($id, $idEmpresa, $d);
            $this->logService->registrar((int) $d['id_usuario'], $idEmpresa, 'ACTUALIZAR_DEPARTAMENTO_TALLER', 'taller_departamentos', $id, $actual, $d);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repository->find($id, $idEmpresa);
        if (!$actual) {
            throw new Exception('Departamento no encontrado.');
        }
        if ($this->repository->tieneOrdenesActivas($id, $idEmpresa)) {
            throw new Exception('No se puede eliminar: hay vehículos en este departamento. Muévalos primero.');
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_DEPARTAMENTO_TALLER', 'taller_departamentos', $id, $actual, null);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Validaciones ─────────────────────────────────────────────────────────

    private function validar(array $d): void
    {
        if (trim((string) ($d['nombre'] ?? '')) === '') {
            throw new Exception('El nombre del departamento es obligatorio.');
        }
        if (mb_strlen(trim((string) $d['nombre'])) > 100) {
            throw new Exception('El nombre del departamento es demasiado largo.');
        }
        if (!empty($d['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $d['color'])) {
            throw new Exception('El color debe estar en formato #RRGGBB.');
        }
    }
}
