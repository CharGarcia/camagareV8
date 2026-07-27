<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\Booleano;
use App\repositories\modulos\TallerChecklistRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Plantilla del checklist de recepción del taller.
 *
 * Define qué se revisa al recibir cada vehículo. Al crear una orden, la
 * plantilla se copia a la orden: cambiarla después no altera las órdenes ya
 * registradas, que conservan como evidencia lo que se revisó ese día.
 */
class TallerChecklistService
{
    private TallerChecklistRepository $repository;
    private LogSistemaService $logService;

    public function __construct(TallerChecklistRepository $repository, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /** Lo que se copia a una orden nueva. */
    public function getPlantilla(int $idEmpresa, bool $soloActivos = true): array
    {
        return $this->repository->getPlantilla($idEmpresa, $soloActivos);
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    public function crear(array $d): int
    {
        $this->validar($d);

        $idEmpresa = (int) $d['id_empresa'];
        $grupo     = (string) $d['grupo'];
        $item      = trim((string) $d['item']);

        if ($this->repository->existeItem($idEmpresa, $grupo, $item)) {
            throw new Exception('Ese punto de revisión ya existe en el grupo ' . (TallerChecklistRepository::GRUPOS[$grupo] ?? $grupo) . '.');
        }

        // Sin posición indicada, va al final del recorrido.
        if ((int) ($d['orden'] ?? 0) <= 0) {
            $d['orden'] = $this->repository->siguienteOrden($idEmpresa);
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $id = $this->repository->create($d);
            $this->logService->registrar((int) $d['id_usuario'], $idEmpresa, 'CREAR_ITEM_CHECKLIST_TALLER', 'taller_checklist_plantilla', $id, null, $d);
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
            throw new Exception('El punto de revisión no existe.');
        }
        if ($this->repository->existeItem($idEmpresa, (string) $d['grupo'], trim((string) $d['item']), $id)) {
            throw new Exception('Ya hay otro punto con ese nombre en el mismo grupo.');
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->update($id, $idEmpresa, $d);
            $this->logService->registrar((int) $d['id_usuario'], $idEmpresa, 'ACTUALIZAR_ITEM_CHECKLIST_TALLER', 'taller_checklist_plantilla', $id, $actual, $d);
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
            throw new Exception('El punto de revisión no existe.');
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_ITEM_CHECKLIST_TALLER', 'taller_checklist_plantilla', $id, $actual, null);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Validaciones ─────────────────────────────────────────────────────────

    private function validar(array $d): void
    {
        $item = trim((string) ($d['item'] ?? ''));
        if ($item === '') {
            throw new Exception('Escriba qué se revisa.');
        }
        if (mb_strlen($item) > 150) {
            throw new Exception('El texto del punto de revisión es demasiado largo.');
        }
        if (!array_key_exists((string) ($d['grupo'] ?? ''), TallerChecklistRepository::GRUPOS)) {
            throw new Exception('Grupo del checklist no válido.');
        }
        if ((int) ($d['orden'] ?? 0) < 0) {
            throw new Exception('El orden no puede ser negativo.');
        }
        // Booleano::es normaliza lo que llegue del formulario ('on', '1', true…).
        $d['activo'] = Booleano::es($d['activo'] ?? true);
    }
}
