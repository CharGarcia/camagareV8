<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ClienteRepository;
use App\Rules\modulos\ClienteRules;
use App\Services\LogSistemaService;
use Exception;

class ClienteService
{
    private ClienteRepository $repository;
    private ClienteRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        ClienteRepository $repository,
        ClienteRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    /**
     * Crea un cliente con validación, transacción y auditoría.
     */
    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $tipoId    = (string) $data['tipo_id'];
        $idIdent   = (string) $data['identificacion'];

        if ($this->repository->existeIdentificacion($idEmpresa, $tipoId, $idIdent)) {
            throw new Exception('Ya existe un cliente con esta identificación y tipo para esta empresa.');
        }

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'clientes',
                $id,
                null,
                $data
            );

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza un cliente con validación, transacción y auditoría.
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        if ($this->repository->existeIdentificacion($idEmpresa, (string)$data['tipo_id'], (string)$data['identificacion'], $id)) {
            throw new Exception('Ya existe otro cliente con esta identificación y tipo para esta empresa.');
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El cliente no existe o ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'clientes',
                $id,
                $antes,
                $data
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina lógicamente un cliente con transacción y auditoría.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El cliente no existe o ya ha sido eliminado.');
        }

        // No permitir eliminar un cliente que ya está siendo usado en módulos operativos
        $usos = $this->repository->getUsosCliente($id, $idEmpresa);
        if (!empty($usos)) {
            $detalle = [];
            foreach ($usos as $modulo => $cantidad) {
                $detalle[] = "{$modulo} ({$cantidad})";
            }
            throw new Exception('No se puede eliminar el cliente porque tiene registros en: ' . implode(', ', $detalle) . '.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'clientes',
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

    /**
     * Replica un cliente (ya guardado en su empresa de origen) hacia varias empresas
     * destino del mismo usuario. Por cada empresa: crea, reactiva o deja intacto
     * (nunca sobrescribe uno ya activo — ver replicarClienteEnEmpresa()).
     *
     * @param array $datosOrigen Ficha ya persistida del cliente origen (findById/getListado).
     * @param int[] $idsEmpresaDestino Empresas ya validadas (asignadas al usuario + permiso de crear).
     * @return array<int,array{estado:string,id?:int,mensaje?:string}> resultado por id_empresa
     */
    public function replicarEnEmpresas(array $datosOrigen, array $idsEmpresaDestino, int $idUsuario): array
    {
        $resultado = [];
        foreach ($idsEmpresaDestino as $idEmpresaDestino) {
            $idEmpresaDestino = (int) $idEmpresaDestino;
            try {
                $resultado[$idEmpresaDestino] = $this->replicarClienteEnEmpresa($datosOrigen, $idEmpresaDestino, $idUsuario);
            } catch (Exception $e) {
                $resultado[$idEmpresaDestino] = ['estado' => 'error', 'mensaje' => $e->getMessage()];
            }
        }
        return $resultado;
    }

    /**
     * Replica TODOS los clientes (no eliminados) de una empresa origen hacia una
     * empresa destino. Pensado para el botón masivo "Copiar clientes a otra empresa".
     *
     * @return array{creados:int,reactivados:int,omitidos:int,errores:int,total:int}
     */
    public function replicarTodosAEmpresa(int $idEmpresaOrigen, int $idEmpresaDestino, int $idUsuario, ?int $idUsuarioFiltro = null): array
    {
        $clientes = $this->repository->getListado($idEmpresaOrigen, '', 1, 0, 'nombre', 'ASC', $idUsuarioFiltro)['rows'];

        $contadores = ['creados' => 0, 'reactivados' => 0, 'omitidos' => 0, 'errores' => 0, 'total' => count($clientes)];
        foreach ($clientes as $cliente) {
            try {
                $r = $this->replicarClienteEnEmpresa($cliente, $idEmpresaDestino, $idUsuario);
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
     * cliente en la empresa destino, según exista o no por (tipo_id + identificación).
     * Nunca sobrescribe uno que ya esté activo ahí — evita pisar ediciones que el
     * usuario ya haya hecho en esa empresa.
     *
     * No copia catálogos que son por-empresa (vendedor, forma de cobro, concepto de
     * ingreso predeterminado): esos IDs pertenecen a la empresa origen y no tienen
     * sentido en la destino, así que quedan sin asignar para configurarse allí.
     *
     * @return array{estado:string,id:int}  estado: creado | reactivado | omitido
     */
    public function replicarClienteEnEmpresa(array $datosOrigen, int $idEmpresaDestino, int $idUsuario): array
    {
        $tipoId = (string) ($datosOrigen['tipo_id'] ?? '');
        $identificacion = (string) ($datosOrigen['identificacion'] ?? '');
        if ($tipoId === '' || $identificacion === '') {
            throw new Exception('El cliente no tiene identificación válida para replicar.');
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
                    'clientes',
                    $id,
                    $existente,
                    ['eliminado' => false, 'origen_empresa' => $datosOrigen['id_empresa'] ?? null]
                );
                $this->repository->commit();
                return ['estado' => 'reactivado', 'id' => $id];
            }

            $nuevo = [
                'id_empresa'      => $idEmpresaDestino,
                'id_usuario'      => $idUsuario,
                'nombre'          => $datosOrigen['nombre'] ?? '',
                'tipo_id'         => $tipoId,
                'identificacion'  => $identificacion,
                'telefono'        => $datosOrigen['telefono'] ?? null,
                'email'           => $datosOrigen['email'] ?? '',
                'direccion'       => $datosOrigen['direccion'] ?? null,
                'plazo'           => $datosOrigen['plazo'] ?? 0,
                'provincia'       => $datosOrigen['provincia'] ?? null,
                'ciudad'          => $datosOrigen['ciudad'] ?? null,
                'status'          => $datosOrigen['status'] ?? 1,
                // Catálogos por-empresa: nunca se copian tal cual (ver docblock del método).
                'id_vendedor'                             => null,
                'id_forma_pago_sri'                        => $datosOrigen['id_forma_pago_sri'] ?? null, // catálogo global SRI
                'id_forma_cobro_predeterminada'            => null,
                'tipo_operacion_bancaria_predeterminada'   => null,
                'monto_minimo_auto_cobro'                  => null,
                'monto_maximo_auto_cobro'                  => null,
                'id_ingreso_concepto_predeterminado'       => null,
                'latitud'                                  => null,
                'longitud'                                 => null,
                // La pauta de visita describe al cliente (qué días abre, en qué
                // horario atienden), no al catálogo de la empresa: se copia.
                'dias_visita'        => $datosOrigen['dias_visita'] ?? null,
                'frecuencia_visita'  => $datosOrigen['frecuencia_visita'] ?? null,
                'semanas_visita'     => $datosOrigen['semanas_visita'] ?? null,
                'hora_visita_desde'  => $datosOrigen['hora_visita_desde'] ?? null,
                'hora_visita_hasta'  => $datosOrigen['hora_visita_hasta'] ?? null,
                'observacion_visita' => $datosOrigen['observacion_visita'] ?? null,
                // El orden sí es relativo a la ruta de un vendedor concreto y el
                // vendedor no se copia (queda null), así que arrancaría desfasado.
                'orden_visita'       => null,
            ];

            $this->rules->validar($nuevo);

            $id = $this->repository->create($nuevo);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresaDestino,
                'crear_replicado',
                'clientes',
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

    /**
     * Obtiene estadísticas del cliente.
     */
    public function getEstadisticas(int $idCliente, int $idEmpresa): array
    {
        return $this->repository->getEstadisticas($idCliente, $idEmpresa);
    }
}
