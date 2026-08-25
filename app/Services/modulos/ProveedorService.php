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
                'monto_minimo_auto_pago'       => !empty($data['monto_minimo_auto_pago']) ? (float)$data['monto_minimo_auto_pago'] : null,
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
                'monto_minimo_auto_pago'       => !empty($data['monto_minimo_auto_pago']) ? (float)$data['monto_minimo_auto_pago'] : null,
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

        // No permitir eliminar un proveedor que ya está siendo usado en módulos operativos
        $usos = $this->repository->getUsosProveedor($id, $idEmpresa);
        if (!empty($usos)) {
            $detalle = [];
            foreach ($usos as $modulo => $cantidad) {
                $detalle[] = "{$modulo} ({$cantidad})";
            }
            throw new Exception('No se puede eliminar el proveedor porque tiene registros en: ' . implode(', ', $detalle) . '.');
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

    // ─── REPLICACIÓN ENTRE EMPRESAS ──────────────────────────────────────────
    // Mismo criterio que Clientes (ver ClienteService): nunca se sobrescribe un
    // proveedor que ya esté activo en la empresa destino.

    /**
     * Replica un proveedor (ya guardado en su empresa de origen) hacia varias
     * empresas destino del mismo usuario. Por cada empresa: crea, reactiva o deja
     * intacto (ver replicarProveedorEnEmpresa()).
     *
     * @param array $datosOrigen Ficha ya persistida del proveedor origen.
     * @param int[] $idsEmpresaDestino Empresas ya validadas (asignadas al usuario + permiso de crear).
     * @return array<int,array{estado:string,id?:int,mensaje?:string}> resultado por id_empresa
     */
    public function replicarEnEmpresas(array $datosOrigen, array $idsEmpresaDestino, int $idUsuario): array
    {
        $resultado = [];
        foreach ($idsEmpresaDestino as $idEmpresaDestino) {
            $idEmpresaDestino = (int) $idEmpresaDestino;
            try {
                $resultado[$idEmpresaDestino] = $this->replicarProveedorEnEmpresa($datosOrigen, $idEmpresaDestino, $idUsuario);
            } catch (Exception $e) {
                $resultado[$idEmpresaDestino] = ['estado' => 'error', 'mensaje' => $e->getMessage()];
            }
        }
        return $resultado;
    }

    /**
     * Replica TODOS los proveedores (no eliminados) de una empresa origen hacia una
     * empresa destino. Es el botón masivo "Copiar a otra empresa" del listado.
     *
     * @return array{creados:int,reactivados:int,omitidos:int,errores:int,total:int}
     */
    public function replicarTodosAEmpresa(int $idEmpresaOrigen, int $idEmpresaDestino, int $idUsuario, ?int $idUsuarioFiltro = null): array
    {
        $proveedores = $this->repository->getListado($idEmpresaOrigen, '', 1, 0, 'razon_social', 'ASC', $idUsuarioFiltro)['rows'];

        $contadores = ['creados' => 0, 'reactivados' => 0, 'omitidos' => 0, 'errores' => 0, 'total' => count($proveedores)];
        foreach ($proveedores as $proveedor) {
            try {
                $r = $this->replicarProveedorEnEmpresa($proveedor, $idEmpresaDestino, $idUsuario);
                $contadores[match ($r['estado']) {
                    'creado'     => 'creados',
                    'reactivado' => 'reactivados',
                    default      => 'omitidos',
                }]++;
            } catch (Exception $e) {
                $contadores['errores']++;
            }
        }
        return $contadores;
    }

    /**
     * Núcleo de la replicación: crea, reactiva (sin tocar datos) o deja intacto un
     * proveedor en la empresa destino, según exista o no por identificación. Nunca
     * sobrescribe uno que ya esté activo ahí, para no pisar ediciones hechas en esa
     * empresa.
     *
     * No copia los catálogos que son por-empresa (forma de pago y concepto de egreso
     * predeterminados): esos IDs pertenecen a la empresa origen y no significan nada
     * en la destino, así que quedan sin asignar para configurarse allí. Los catálogos
     * globales (banco, tipo de empresa, retenciones SRI, sustento tributario) sí se
     * copian tal cual.
     *
     * @return array{estado:string,id:int}  estado: creado | reactivado | omitido
     */
    public function replicarProveedorEnEmpresa(array $datosOrigen, int $idEmpresaDestino, int $idUsuario): array
    {
        $tipoId = trim((string) ($datosOrigen['tipo_id_proveedor'] ?? ''));
        $identificacion = trim((string) ($datosOrigen['identificacion'] ?? ''));
        if ($tipoId === '' || $identificacion === '') {
            throw new Exception('El proveedor no tiene identificación válida para replicar.');
        }

        $existente = $this->repository->findByIdentificacion($idEmpresaDestino, $identificacion);

        if ($existente && empty($existente['eliminado'])) {
            return ['estado' => 'omitido', 'id' => (int) $existente['id']];
        }

        $this->repository->beginTransaction();
        try {
            if ($existente) {
                $id = (int) $existente['id'];
                $this->repository->reactivarSoloEliminado($id, $idUsuario);
                $this->logService->registrar(
                    $idUsuario,
                    $idEmpresaDestino,
                    'reactivar_replicado',
                    'proveedores',
                    $id,
                    $existente,
                    ['eliminado' => false, 'origen_empresa' => $datosOrigen['id_empresa'] ?? null]
                );
                $this->repository->commit();
                return ['estado' => 'reactivado', 'id' => $id];
            }

            $nuevo = [
                'id_empresa'         => $idEmpresaDestino,
                'id_usuario'         => $idUsuario,
                'created_by'         => $idUsuario,
                'razon_social'       => $datosOrigen['razon_social'] ?? '',
                'nombre_comercial'   => $datosOrigen['nombre_comercial'] ?? null,
                'tipo_id_proveedor'  => $tipoId,
                'identificacion'     => $identificacion,
                'email'              => $datosOrigen['email'] ?? null,
                'direccion'          => $datosOrigen['direccion'] ?? null,
                'provincia'          => $datosOrigen['provincia'] ?? null,
                'ciudad'             => $datosOrigen['ciudad'] ?? null,
                'telefono'           => $datosOrigen['telefono'] ?? null,
                'tipo_empresa'       => $datosOrigen['tipo_empresa'] ?? null,
                'plazo'              => (int) ($datosOrigen['plazo'] ?? 0),
                'unidad_tiempo'      => $datosOrigen['unidad_tiempo'] ?? 'DIAS',
                'relacionado'        => !empty($datosOrigen['relacionado']) && $datosOrigen['relacionado'] !== 'f',
                // Catálogos globales: se copian tal cual.
                'id_banco'           => $datosOrigen['id_banco'] ?? null,
                'tipo_cta'           => $datosOrigen['tipo_cta'] ?? null,
                'numero_cta'         => $datosOrigen['numero_cta'] ?? null,
                'id_retencion_renta' => $datosOrigen['id_retencion_renta'] ?? null,
                'id_retencion_iva'   => $datosOrigen['id_retencion_iva'] ?? null,
                'id_sustento_tributario' => $datosOrigen['id_sustento_tributario'] ?? null,
                'status'             => !isset($datosOrigen['status']) || (!empty($datosOrigen['status']) && $datosOrigen['status'] !== 'f'),
                // Catálogos por-empresa: nunca se copian (ver docblock del método).
                'id_forma_pago_predeterminada'           => null,
                'tipo_operacion_bancaria_predeterminada' => null,
                'monto_minimo_auto_pago'                 => null,
                'monto_maximo_auto_pago'                 => null,
                'id_egreso_concepto_predeterminado'      => null,
                'latitud'            => null,
                'longitud'           => null,
                'eliminado'          => false,
            ];

            $this->rules->validar($nuevo);

            $id = $this->repository->create($nuevo);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresaDestino,
                'crear_replicado',
                'proveedores',
                $id,
                null,
                $nuevo + ['origen_empresa' => $datosOrigen['id_empresa'] ?? null]
            );
            $this->repository->commit();
            return ['estado' => 'creado', 'id' => $id];
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
