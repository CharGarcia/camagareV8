<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ProveedorRepository;
use App\Rules\modulos\ProveedorRules;
use App\Services\LogSistemaService;
use Exception;

class ProveedorService
{
    private ProveedorRepository $repository;
    private ProveedorRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        ProveedorRepository $repository,
        ProveedorRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * Crea un proveedor con validación, transacción y auditoría.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        
        if ($this->repository->existeIdentificacion($idEmpresa, ltrim($data['tipo_id_proveedor']), ltrim($data['identificacion']))) {
            throw new Exception("Ya existe un proveedor en la empresa con esa misma identificación.");
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa'         => $idEmpresa,
                'id_usuario'         => (int)$data['id_usuario'],
                'created_by'         => (int)$data['id_usuario'],
                'razon_social'       => mb_strtoupper(trim($data['razon_social']), 'UTF-8'),
                'nombre_comercial'   => !empty($data['nombre_comercial']) ? mb_strtoupper(trim($data['nombre_comercial']), 'UTF-8') : null,
                'tipo_id_proveedor'  => trim($data['tipo_id_proveedor']),
                'identificacion'     => trim($data['identificacion']),
                'email'              => !empty($data['email']) ? trim($data['email']) : null,
                'direccion'          => !empty($data['direccion']) ? trim($data['direccion']) : null,
                'provincia'          => !empty($data['provincia']) ? trim($data['provincia']) : null,
                'ciudad'             => !empty($data['ciudad']) ? trim($data['ciudad']) : null,
                'telefono'           => !empty($data['telefono']) ? trim($data['telefono']) : null,
                'tipo_empresa'       => !empty($data['tipo_empresa']) ? (int)$data['tipo_empresa'] : null,
                'plazo'              => (int)($data['plazo'] ?? 0),
                'unidad_tiempo'      => trim($data['unidad_tiempo'] ?? 'DIAS'),
                'relacionado'        => !empty($data['relacionado']),
                'id_banco'           => !empty($data['id_banco']) ? (int)$data['id_banco'] : null,
                'tipo_cta'           => !empty($data['tipo_cta']) ? (int)$data['tipo_cta'] : null,
                'numero_cta'         => !empty($data['numero_cta']) ? trim($data['numero_cta']) : null,
                'status'             => isset($data['status']) ? (bool)$data['status'] : true,
                'id_retencion_renta' => !empty($data['id_retencion_renta']) ? (int)$data['id_retencion_renta'] : null,
                'id_retencion_iva'   => !empty($data['id_retencion_iva']) ? (int)$data['id_retencion_iva'] : null,
                'id_forma_pago_predeterminada' => !empty($data['id_forma_pago_predeterminada']) ? (int)$data['id_forma_pago_predeterminada'] : null,
                'tipo_operacion_bancaria_predeterminada' => !empty($data['tipo_operacion_bancaria_predeterminada']) ? trim($data['tipo_operacion_bancaria_predeterminada']) : null,
                'monto_maximo_auto_pago'       => !empty($data['monto_maximo_auto_pago']) ? (float)$data['monto_maximo_auto_pago'] : null,
                'id_sustento_tributario'       => !empty($data['id_sustento_tributario']) ? (int)$data['id_sustento_tributario'] : null,
                'id_egreso_concepto_predeterminado' => !empty($data['id_egreso_concepto_predeterminado']) ? (int)$data['id_egreso_concepto_predeterminado'] : null,
                'latitud'            => isset($data['latitud'])  && $data['latitud']  !== '' && $data['latitud']  !== null ? (float)$data['latitud']  : null,
                'longitud'           => isset($data['longitud']) && $data['longitud'] !== '' && $data['longitud'] !== null ? (float)$data['longitud'] : null,
                'eliminado'          => false
            ];

            $id = $this->repository->create($insertData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'proveedores',
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
     * Actualiza un proveedor.
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        if ($this->repository->existeIdentificacion($idEmpresa, ltrim($data['tipo_id_proveedor']), ltrim($data['identificacion']), $id)) {
            throw new Exception("Ya existe otro proveedor con la misma identificación.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El proveedor no existe o ha sido eliminado.');
        }

        if ($antes['tipo_id_proveedor'] !== trim($data['tipo_id_proveedor']) || $antes['identificacion'] !== trim($data['identificacion'])) {
            if ($this->repository->estaEnUso($id, $idEmpresa)) {
                throw new Exception('No se puede cambiar el Tipo de ID o la Identificación porque este proveedor ya se encuentra en uso (tiene compras o liquidaciones).');
            }
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'razon_social'       => mb_strtoupper(trim($data['razon_social']), 'UTF-8'),
                'nombre_comercial'   => !empty($data['nombre_comercial']) ? mb_strtoupper(trim($data['nombre_comercial']), 'UTF-8') : null,
                'tipo_id_proveedor'  => trim($data['tipo_id_proveedor']),
                'identificacion'     => trim($data['identificacion']),
                'email'              => !empty($data['email']) ? trim($data['email']) : null,
                'direccion'          => !empty($data['direccion']) ? trim($data['direccion']) : null,
                'provincia'          => !empty($data['provincia']) ? trim($data['provincia']) : null,
                'ciudad'             => !empty($data['ciudad']) ? trim($data['ciudad']) : null,
                'telefono'           => !empty($data['telefono']) ? trim($data['telefono']) : null,
                'tipo_empresa'       => !empty($data['tipo_empresa']) ? (int)$data['tipo_empresa'] : null,
                'plazo'              => (int)($data['plazo'] ?? 0),
                'unidad_tiempo'      => trim($data['unidad_tiempo'] ?? 'DIAS'),
                'relacionado'        => !empty($data['relacionado']),
                'id_banco'           => !empty($data['id_banco']) ? (int)$data['id_banco'] : null,
                'tipo_cta'           => !empty($data['tipo_cta']) ? (int)$data['tipo_cta'] : null,
                'numero_cta'         => !empty($data['numero_cta']) ? trim($data['numero_cta']) : null,
                'status'             => isset($data['status']) ? (bool)$data['status'] : true,
                'id_retencion_renta' => !empty($data['id_retencion_renta']) ? (int)$data['id_retencion_renta'] : null,
                'id_retencion_iva'   => !empty($data['id_retencion_iva']) ? (int)$data['id_retencion_iva'] : null,
                'id_forma_pago_predeterminada' => !empty($data['id_forma_pago_predeterminada']) ? (int)$data['id_forma_pago_predeterminada'] : null,
                'tipo_operacion_bancaria_predeterminada' => !empty($data['tipo_operacion_bancaria_predeterminada']) ? trim($data['tipo_operacion_bancaria_predeterminada']) : null,
                'monto_maximo_auto_pago'       => !empty($data['monto_maximo_auto_pago']) ? (float)$data['monto_maximo_auto_pago'] : null,
                'id_sustento_tributario'       => !empty($data['id_sustento_tributario']) ? (int)$data['id_sustento_tributario'] : null,
                'id_egreso_concepto_predeterminado' => !empty($data['id_egreso_concepto_predeterminado']) ? (int)$data['id_egreso_concepto_predeterminado'] : null,
                'latitud'            => isset($data['latitud'])  && $data['latitud']  !== '' && $data['latitud']  !== null ? (float)$data['latitud']  : null,
                'longitud'           => isset($data['longitud']) && $data['longitud'] !== '' && $data['longitud'] !== null ? (float)$data['longitud'] : null,
                'updated_by'         => (int)$data['id_usuario']
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'proveedores',
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
     * Elimina lógicamente un proveedor.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El proveedor no existe o ya ha sido eliminado.');
        }

        if ($this->repository->estaEnUso($id, $idEmpresa)) {
            throw new Exception('No se puede eliminar el proveedor porque tiene transacciones registradas (compras o liquidaciones).');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'proveedores',
                $id,
                $antes,
                ['eliminado' => true, 'status' => false]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Proxy para el repositorio para listados.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }
}
