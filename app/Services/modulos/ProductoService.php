<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ProductoRepository;
use App\Rules\modulos\ProductoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\InventarioService;
use Exception;

class ProductoService
{
    private ProductoRepository $repository;
    private ProductoRules $rules;
    private LogSistemaService $logService;
    private ?InventarioService $inventarioService;

    public function __construct(
        ProductoRepository $repository,
        ProductoRules $rules,
        LogSistemaService $logService,
        ?InventarioService $inventarioService = null
    ) {
        $this->repository        = $repository;
        $this->rules             = $rules;
        $this->logService        = $logService;
        $this->inventarioService = $inventarioService;
    }

    public function getSiguienteCodigo(int $idEmpresa, string $tipo): string
    {
        return $this->repository->getSiguienteCodigo($idEmpresa, $tipo);
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function crear(array $data): int
    {
        $idEmpresa    = (int) $data['id_empresa'];
        $tipoProduccion = !empty($data['tipo_produccion']) ? trim($data['tipo_produccion']) : '01';

        // Aplicar medida default para tipo '01' si no viene ninguna
        if ($tipoProduccion === '01' && empty($data['id_medida'])) {
            $default = $this->repository->getMedidaDefaultUnidad($idEmpresa);
            if ($default) {
                $data['id_medida']      = $default['id_medida'];
                $data['id_tipo_medida'] = $default['id_tipo_medida'];
            }
        }

        $this->rules->validar($data);

        if ($this->repository->existeCodigo($idEmpresa, trim($data['codigo']))) {
            throw new Exception("Ya existe un producto con el mismo código principal.");
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa'            => $idEmpresa,
                'id_usuario'            => (int)$data['id_usuario'],
                'codigo'                => trim($data['codigo']),
                'nombre'                => trim($data['nombre']),
                'codigo_auxiliar'       => !empty($data['codigo_auxiliar']) ? trim($data['codigo_auxiliar']) : '',
                'codigo_barras'         => !empty($data['codigo_barras']) ? trim($data['codigo_barras']) : '',
                'precio_base'           => !empty($data['precio_base']) ? (float)$data['precio_base'] : 0,
                'tipo_produccion'       => $tipoProduccion,
                'tarifa_iva'            => !empty($data['tarifa_iva']) ? (int)$data['tarifa_iva'] : 2,
                'id_medida'             => !empty($data['id_medida']) ? (int)$data['id_medida'] : null,
                'status'                => isset($data['status']) ? (bool)$data['status'] : true,
                'id_ice'                => !empty($data['id_ice']) ? (int)$data['id_ice'] : null,
                'valor_ice'             => !empty($data['valor_ice']) ? (float)$data['valor_ice'] : null,
                'codigo_ice'            => !empty($data['codigo_ice']) ? trim($data['codigo_ice']) : null,
                'nombre_ice'            => !empty($data['nombre_ice']) ? trim($data['nombre_ice']) : null,
                'inventariable'         => isset($data['inventariable']) ? (bool)$data['inventariable'] : false,

                'id_categoria'          => !empty($data['id_categoria']) ? (int)$data['id_categoria'] : null,
                'id_marca'              => !empty($data['id_marca']) ? (int)$data['id_marca'] : null,
                'id_tipo_medida'        => !empty($data['id_tipo_medida']) ? (int)$data['id_tipo_medida'] : null,
                'imagen'                => !empty($data['imagen']) ? trim($data['imagen']) : null,
                'costo_producto'        => !empty($data['costo_producto']) ? (float)$data['costo_producto'] : 0,
                'componentes'           => !empty($data['componentes']) ? $data['componentes'] : [],
                'variantes'             => !empty($data['variantes']) ? $data['variantes'] : [],
                'stock_minimo'          => !empty($data['stock_minimo']) ? (float)$data['stock_minimo'] : 0,
                'stock_maximo'          => !empty($data['stock_maximo']) ? (float)$data['stock_maximo'] : 0,
                'opciones'              => !empty($data['opciones']) ? $data['opciones'] : '{"compra":true,"venta":true}',
            ];

            $id = $this->repository->create($insertData);
            
            if (isset($data['inventarios']) && is_array($data['inventarios'])) {
                $this->repository->syncInventarios($id, $idEmpresa, $data['inventarios'], (int)$data['id_usuario']);

                // Procesar ajustes iniciales si existen
                if ($this->inventarioService) {
                    foreach ($data['inventarios'] as $inv) {
                        $ajuste = (float)($inv['ajuste'] ?? 0);
                        if ($ajuste != 0) {
                            $this->inventarioService->ajusteManual([
                                'id_producto'     => $id,
                                'id_bodega'       => (int)$inv['id_bodega'],
                                'tipo_movimiento' => ($ajuste > 0) ? 'entrada' : 'salida',
                                'cantidad'        => abs($ajuste),
                                'costo_unitario'  => (float)($data['costo_producto'] ?? 0),
                                'observaciones'   => $inv['observaciones_ajuste'] ?? 'Saldo inicial',
                                'numero_lote'     => $inv['lote_ajuste'] ?? null,
                            ], $idEmpresa, (int)$data['id_usuario']);
                            $this->repository->recalcularStockCache($id, (int)$inv['id_bodega'], $idEmpresa);
                        }
                    }
                }
            }
            if (isset($data['precios']) && is_array($data['precios'])) {
                $this->repository->syncPrecios($id, $idEmpresa, $data['precios'], (int)$data['id_usuario']);
            }
            if (isset($data['componentes']) && is_array($data['componentes'])) {
                $this->repository->syncComponentes($id, $idEmpresa, $data['componentes'], (int)$data['id_usuario']);
            }
            if (isset($data['variantes']) && is_array($data['variantes'])) {
                $this->repository->syncVariantes($id, $idEmpresa, $data['variantes'], (int)$data['id_usuario']);
            }
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'productos',
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

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $antes = $this->repository->getDetalleCompleto($id, $idEmpresa);
        if (!$antes) throw new Exception('El producto no existe.');

        // Si el producto ya fue usado en facturas o inventario, los campos críticos no pueden cambiar
        if ($this->repository->estaUsadoEnDocumentos($id, $idEmpresa)) {
            $data['codigo']          = $antes['codigo'];
            $data['nombre']          = $antes['nombre'];
            $data['tipo_produccion'] = $antes['tipo_produccion'];
        }

        $tipoProduccion = !empty($data['tipo_produccion']) ? trim($data['tipo_produccion']) : '01';

        // Aplicar medida default para tipo '01' si no viene ninguna
        if ($tipoProduccion === '01' && empty($data['id_medida'])) {
            $default = $this->repository->getMedidaDefaultUnidad($idEmpresa);
            if ($default) {
                $data['id_medida']      = $default['id_medida'];
                $data['id_tipo_medida'] = $default['id_tipo_medida'];
            }
        }

        $this->rules->validar($data);

        if ($this->repository->existeCodigo($idEmpresa, trim($data['codigo']), $id)) {
            throw new Exception("Ya existe otro producto con el mismo código principal.");
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'id_usuario'            => (int)$data['id_usuario'],
                'codigo'                => trim($data['codigo']),
                'nombre'                => trim($data['nombre']),
                'codigo_auxiliar'       => !empty($data['codigo_auxiliar']) ? trim($data['codigo_auxiliar']) : '',
                'codigo_barras'         => !empty($data['codigo_barras']) ? trim($data['codigo_barras']) : '',
                'precio_base'           => !empty($data['precio_base']) ? (float)$data['precio_base'] : 0,
                'tipo_produccion'       => $tipoProduccion,
                'tarifa_iva'            => !empty($data['tarifa_iva']) ? (int)$data['tarifa_iva'] : 2,
                'id_medida'             => !empty($data['id_medida']) ? (int)$data['id_medida'] : null,
                'status'                => isset($data['status']) ? (bool)$data['status'] : true,
                'id_ice'                => !empty($data['id_ice']) ? (int)$data['id_ice'] : null,
                'valor_ice'             => !empty($data['valor_ice']) ? (float)$data['valor_ice'] : null,
                'codigo_ice'            => !empty($data['codigo_ice']) ? trim($data['codigo_ice']) : null,
                'nombre_ice'            => !empty($data['nombre_ice']) ? trim($data['nombre_ice']) : null,
                'inventariable'         => isset($data['inventariable']) ? (bool)$data['inventariable'] : false,

                'id_categoria'          => !empty($data['id_categoria']) ? (int)$data['id_categoria'] : null,
                'id_marca'              => !empty($data['id_marca']) ? (int)$data['id_marca'] : null,
                'id_tipo_medida'        => !empty($data['id_tipo_medida']) ? (int)$data['id_tipo_medida'] : null,
                'imagen'                => !empty($data['imagen']) ? trim($data['imagen']) : null,
                'costo_producto'        => !empty($data['costo_producto']) ? (float)$data['costo_producto'] : 0,
                'componentes'           => !empty($data['componentes']) ? $data['componentes'] : [],
                'variantes'             => !empty($data['variantes']) ? $data['variantes'] : [],
                'stock_minimo'          => !empty($data['stock_minimo']) ? (float)$data['stock_minimo'] : 0,
                'stock_maximo'          => !empty($data['stock_maximo']) ? (float)$data['stock_maximo'] : 0,
                'opciones'              => !empty($data['opciones']) ? $data['opciones'] : '{"compra":true,"venta":true}',
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            if (isset($data['inventarios']) && is_array($data['inventarios'])) {
                $this->repository->syncInventarios($id, $idEmpresa, $data['inventarios'], (int)$data['id_usuario']);

                // Procesar ajustes si existen y tenemos el servicio de inventario
                if ($this->inventarioService) {
                    foreach ($data['inventarios'] as $inv) {
                        $ajuste = (float)($inv['ajuste'] ?? 0);
                        if ($ajuste != 0) {
                            $this->inventarioService->ajusteManual([
                                'id_producto'     => $id,
                                'id_bodega'       => (int)$inv['id_bodega'],
                                'tipo_movimiento' => ($ajuste > 0) ? 'entrada' : 'salida',
                                'cantidad'        => abs($ajuste),
                                'costo_unitario'  => (float)($data['costo_producto'] ?? 0),
                                'observaciones'   => $inv['observaciones_ajuste'] ?? 'Ajuste desde edición de producto',
                                'numero_lote'     => $inv['lote_ajuste'] ?? null,
                            ], $idEmpresa, (int)$data['id_usuario']);

                            $this->repository->recalcularStockCache($id, (int)$inv['id_bodega'], $idEmpresa);
                        }
                    }
                }
            }
            if (isset($data['precios']) && is_array($data['precios'])) {
                $this->repository->syncPrecios($id, $idEmpresa, $data['precios'], (int)$data['id_usuario']);
            }
            if (isset($data['componentes']) && is_array($data['componentes'])) {
                $this->repository->syncComponentes($id, $idEmpresa, $data['componentes'], (int)$data['id_usuario']);
            }
            if (isset($data['variantes']) && is_array($data['variantes'])) {
                $this->repository->syncVariantes($id, $idEmpresa, $data['variantes'], (int)$data['id_usuario']);
            }
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'productos',
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

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) throw new Exception('El producto no existe.');

        $this->repository->beginTransaction();
        try {
            $this->repository->softDelete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'productos',
                $id,
                $antes,
                ['eliminado' => true, 'deleted_by' => $idUsuario]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminarHomologacion(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $this->repository->beginTransaction();
        try {
            $ok = $this->repository->softDeleteHomologacion($id, $idEmpresa, $idUsuario);
            if ($ok) {
                $this->logService->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'eliminar',
                    'productos_homologacion',
                    $id,
                    null,
                    ['eliminado' => true, 'deleted_by' => $idUsuario]
                );
            }
            $this->repository->commit();
            return $ok;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
