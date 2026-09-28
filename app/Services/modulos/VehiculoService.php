<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\VehiculoRepository;
use App\Rules\modulos\VehiculoRules;
use App\Services\LogSistemaService;
use Exception;

class VehiculoService
{
    private VehiculoRepository $repository;
    private VehiculoRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        VehiculoRepository $repository,
        VehiculoRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * Crea un vehículo con validación, transacción y auditoría.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $placa      = trim($data['placa']);

        if ($this->repository->existePlaca($idEmpresa, $placa)) {
            throw new Exception("Ya existe un vehículo con la placa '{$placa}' en su empresa.");
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa'  => $idEmpresa,
                'id_usuario'  => (int)$data['id_usuario'],
                'created_by'  => (int)$data['id_usuario'],
                'marca'       => mb_strtoupper(trim($data['marca']), 'UTF-8'),
                'placa'       => mb_strtoupper(trim($data['placa']), 'UTF-8'),
                'chasis'      => mb_strtoupper(trim($data['chasis'] ?? ''), 'UTF-8'),
                'anio'        => (int)($data['anio'] ?? 0),
                'propietario' => mb_strtoupper(trim($data['propietario']), 'UTF-8'),
                'estado'      => $data['estado'] ?? 'activo',
                'correo'      => trim($data['correo'] ?? ''),
                'telefono'    => trim($data['telefono'] ?? ''),
                'eliminado'   => false
            ];

            $id = $this->repository->create($insertData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'vehiculos',
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

    /**
     * Actualiza un vehículo con validación, transacción y auditoría.
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        $placa = trim($data['placa']);

        if ($this->repository->existePlaca($idEmpresa, $placa, $id)) {
            throw new Exception("Ya existe otro vehículo con la placa '{$placa}'.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El vehículo no existe o ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'marca'       => mb_strtoupper(trim($data['marca']), 'UTF-8'),
                'placa'       => mb_strtoupper(trim($data['placa']), 'UTF-8'),
                'chasis'      => mb_strtoupper(trim($data['chasis'] ?? ''), 'UTF-8'),
                'anio'        => (int)($data['anio'] ?? 0),
                'propietario' => mb_strtoupper(trim($data['propietario']), 'UTF-8'),
                'estado'      => $data['estado'] ?? 'activo',
                'correo'      => trim($data['correo'] ?? ''),
                'telefono'    => trim($data['telefono'] ?? ''),
                'updated_by'  => (int)$data['id_usuario']
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'vehiculos',
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

    /**
     * Elimina lógicamente un vehículo con transacción y auditoría.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El vehículo no existe o ya ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'vehiculos',
                $id,
                $antes,
                ['eliminado' => true]
            );

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

    public function findById(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getDetalleCompleto($id, $idEmpresa);
    }

    /**
     * Pestaña Transacciones: todo lo que se le ha hecho al vehículo, tomado de las órdenes de
     * Car-Wash (con sus servicios y productos) y un resumen (visitas, total, última visita,
     * próxima cita). Respeta registros propios (§6) con $idUsuarioFiltro.
     */
    public function getTransacciones(int $idVehiculo, int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        if (!$this->repository->getDetalleCompleto($idVehiculo, $idEmpresa)) {
            throw new \Exception('Vehículo no encontrado.');
        }
        $repoCw  = new \App\repositories\modulos\OrdenCarWashRepository();
        $ordenes = $repoCw->getHistorial($idEmpresa, 'vehiculo', '', $idVehiculo, null, $idUsuarioFiltro, 500);
        $lineas  = $repoCw->getLineasPorOrdenes(array_column($ordenes, 'id'), $idEmpresa);

        $total = 0.0; $proxima = null; $hoy = date('Y-m-d');
        foreach ($ordenes as &$o) {
            $o['lineas'] = $lineas[(int) $o['id']] ?? [];
            if (($o['estado'] ?? '') !== 'anulado') $total += (float) $o['total'];
        }
        unset($o);
        foreach ((new \App\Services\modulos\CarWashRecordatorioService())->citasVehiculo($idVehiculo, $idEmpresa, $idUsuarioFiltro) as $c) {
            $f = substr((string) $c['proxima_cita'], 0, 10);
            if ($f >= $hoy && ($proxima === null || $f < $proxima)) $proxima = $f;
        }

        return [
            'ordenes' => $ordenes,
            'resumen' => [
                'visitas'        => count($ordenes),
                'total'          => round($total, 2),
                'ultima_visita'  => $ordenes[0]['fecha_ingreso'] ?? null,
                'proxima_cita'   => $proxima,
            ],
        ];
    }
}
