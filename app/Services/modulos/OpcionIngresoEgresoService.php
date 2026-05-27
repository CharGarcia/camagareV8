<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\OpcionIngresoEgresoRepository;
use App\Rules\modulos\OpcionIngresoEgresoRules;
use App\Services\LogSistemaService;
use Exception;

class OpcionIngresoEgresoService
{
    private OpcionIngresoEgresoRepository $repo;
    private OpcionIngresoEgresoRules $rules;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->repo = new OpcionIngresoEgresoRepository();
        $this->rules = new OpcionIngresoEgresoRules();
        $this->logService = new LogSistemaService();
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'nombre', string $ordenDir = 'ASC'): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getById($id, $idEmpresa);
    }

    public function registrar(array $data): int
    {
        $this->rules->validar($data);
        
        $id = $this->repo->create($data);
        
        $this->logService->registrar(
            (int)$data['id_usuario'],
            (int)$data['id_empresa'],
            'CREAR',
            'empresa_opciones_ingreso_egreso',
            $id,
            null,
            ['nombre' => $data['nombre']]
        );
        
        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, array $data): bool
    {
        $original = $this->repo->getById($id, $idEmpresa);
        if (!$original) throw new Exception("Registro no encontrado.");
        
        $this->rules->validar($data);
        
        $ok = $this->repo->update($id, $idEmpresa, $data);
        if ($ok) {
            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$idEmpresa,
                'ACTUALIZAR',
                'empresa_opciones_ingreso_egreso',
                $id,
                $original,
                ['nombre' => $data['nombre']]
            );
        }
        return $ok;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $original = $this->repo->getById($id, $idEmpresa);
        if (!$original) throw new Exception("Registro no encontrado.");
        
        if ($this->repo->estaUsado($id, $idEmpresa)) {
            throw new Exception("No se puede eliminar este concepto porque ya está asignado a transacciones de Ingresos o Egresos.");
        }
        
        $ok = $this->repo->logicalDelete($id, $idEmpresa, $idUsuario);
        if ($ok) {
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'empresa_opciones_ingreso_egreso',
                $id,
                $original,
                null
            );
        }
        return $ok;
    }
}
