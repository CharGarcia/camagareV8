<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\EmpleadoRepository;
use App\Rules\modulos\EmpleadoRules;
use App\Services\LogSistemaService;
use Exception;

class EmpleadoService
{
    private EmpleadoRepository $repository;
    private EmpleadoRules $rules;
    private LogSistemaService $logService;

    public function __construct(EmpleadoRepository $repository, EmpleadoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    /**
     * Obtiene el listado de empleados filtrado por empresa.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /**
     * Obtiene el detalle completo del empleado, incluyendo periodos y rubros.
     */
    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $empleado = $this->repository->findById($id, $idEmpresa);
        if (!$empleado) return null;

        $empleado['periodos'] = $this->repository->getPeriodos($id, $idEmpresa);
        $empleado['rubros'] = $this->repository->getRubrosFijos($id, $idEmpresa);

        return $empleado;
    }

    /**
     * Crea un empleado validando duplicados y reglas de negocio.
     */
    public function crear(array $data): int
    {
        $this->rules->validate($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $identificacion = trim((string)$data['identificacion']);

        if ($this->repository->existsByIdentificacion($identificacion, $idEmpresa)) {
            throw new Exception("Ya existe un empleado con la identificación '{$identificacion}' en esta empresa.");
        }

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);

            // Sincronizar periodos y rubros si vienen en el data
            if (!empty($data['periodos'])) {
                $this->repository->syncPeriodos($id, $idEmpresa, $data['periodos'], $idUsuario);
            }
            if (!empty($data['rubros_fijos'])) {
                $this->repository->syncRubrosFijos($id, $idEmpresa, $data['rubros_fijos'], $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'empleados', $id, null, $data);

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza los datos de un empleado.
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validate($data);

        $idUsuario = (int) $data['id_usuario'];
        $identificacion = trim((string)$data['identificacion']);
        if ($this->repository->existsByIdentificacion($identificacion, $idEmpresa, $id)) {
            throw new Exception("Ya existe otro empleado con la identificación '{$identificacion}' en esta empresa.");
        }

        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception("Empleado no encontrado.");
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);

            // Sincronizar periodos y rubros si vienen en el data
            if (isset($data['periodos'])) {
                $this->repository->syncPeriodos($id, $idEmpresa, $data['periodos'], $idUsuario);
            }
            if (isset($data['rubros_fijos'])) {
                $this->repository->syncRubrosFijos($id, $idEmpresa, $data['rubros_fijos'], $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'empleados', $id, $old, $data);

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene los valores por defecto del IESS desde la tabla salarios.
     */
    public function getIessDefaults(): array
    {
        $anio = (int)date('Y');
        // Usamos una consulta directa o un repositorio de ser necesario. 
        // Siguiendo el descubrimiento previo: tabla 'salarios', columna 'ano'.
        try {
            $db = \App\core\Database::getConnection();
            $st = $db->prepare("SELECT aporte_personal, aporte_patronal, sbu FROM salarios WHERE ano = :a AND status = 1 LIMIT 1");
            $st->execute([':a' => $anio]);
            $res = $st->fetch(\PDO::FETCH_ASSOC);
            
            if ($res) return $res;
            
            // Fallback si no hay año actual
            return ['aporte_personal' => 9.45, 'aporte_patronal' => 11.15, 'sbu' => 450.00];
        } catch (\PDOException $e) {
            return ['aporte_personal' => 9.45, 'aporte_patronal' => 11.15, 'sbu' => 450.00];
        }
    }

    /**
     * Realiza la eliminación lógica del registro.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) {
            throw new Exception("Empleado no encontrado.");
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'empleados', $id, $old, null);

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
