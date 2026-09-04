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

    public function getPrefijosCodigo(int $idEmpresa, string $tipo): array
    {
        return $this->repository->getPrefijosCodigo($idEmpresa, $tipo);
    }

    public function getSiguienteCodigoPorPrefijo(int $idEmpresa, string $tipo, string $prefijo): string
    {
        return $this->repository->getSiguienteCodigoPorPrefijo($idEmpresa, $tipo, $prefijo);
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function crear(array $data): int
    {
        $idEmpresa    = (int) $data['id_empresa'];
        $tipoProduccion = !empty($data['tipo_produccion']) ? trim($data['tipo_produccion']) : '01';

        // Un servicio (02) nunca maneja inventario
        if ($tipoProduccion === '02') {
            $data['inventariable'] = false;
        }

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
                'nombre'                => trim(preg_replace('/\s+/u', ' ', (string) $data['nombre'])),
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
                'nombre_ice'            => !empty($data['nombre_ice']) ? trim(preg_replace('/\s+/u', ' ', (string) $data['nombre_ice'])) : null,
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
                'ubicacion'             => !empty($data['ubicacion']) ? trim(preg_replace('/\s+/u', ' ', (string) $data['ubicacion'])) : null,
                'excluir_recargo_servicio' => !empty($data['excluir_recargo_servicio']),
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

    /**
     * Replica un producto (ya guardado en su empresa de origen) hacia varias
     * empresas destino del mismo usuario. Por cada empresa: crea, reactiva o
     * deja intacto (nunca sobrescribe uno ya activo — ver replicarProductoEnEmpresa()).
     *
     * @param array $datosOrigen Fila ya persistida del producto origen (findById/getListado).
     * @param int[] $idsEmpresaDestino Empresas ya validadas (asignadas al usuario + permiso de crear).
     * @return array<int,array{estado:string,id?:int,mensaje?:string}> resultado por id_empresa
     */
    public function replicarEnEmpresas(array $datosOrigen, array $idsEmpresaDestino, int $idUsuario): array
    {
        $resultado = [];
        foreach ($idsEmpresaDestino as $idEmpresaDestino) {
            $idEmpresaDestino = (int) $idEmpresaDestino;
            try {
                $resultado[$idEmpresaDestino] = $this->replicarProductoEnEmpresa($datosOrigen, $idEmpresaDestino, $idUsuario);
            } catch (Exception $e) {
                $resultado[$idEmpresaDestino] = ['estado' => 'error', 'mensaje' => $e->getMessage()];
            }
        }
        return $resultado;
    }

    /**
     * Replica TODOS los productos (no eliminados) de una empresa origen hacia
     * una empresa destino. Pensado para el botón masivo "Copiar a otra empresa".
     *
     * @return array{creados:int,reactivados:int,omitidos:int,errores:int,total:int}
     */
    public function replicarTodosAEmpresa(int $idEmpresaOrigen, int $idEmpresaDestino, int $idUsuario, ?int $idUsuarioFiltro = null): array
    {
        $productos = $this->repository->getListado($idEmpresaOrigen, '', 1, 0, 'nombre', 'ASC', $idUsuarioFiltro)['rows'];

        $contadores = ['creados' => 0, 'reactivados' => 0, 'omitidos' => 0, 'errores' => 0, 'total' => count($productos)];
        foreach ($productos as $producto) {
            try {
                $r = $this->replicarProductoEnEmpresa($producto, $idEmpresaDestino, $idUsuario);
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
     * Núcleo de la replicación: crea, reactiva (sin tocar datos) o deja intacto
     * un producto en la empresa destino, según exista o no por código. Nunca
     * sobrescribe uno que ya esté activo ahí.
     *
     * No copia catálogos que son por-empresa (categoría, marca, unidad de medida,
     * tipo de medida, ICE configurado en la empresa): esos IDs pertenecen a la
     * empresa origen y no tienen sentido en la destino, así que quedan sin
     * asignar para configurarse allí (para "bien" sin unidad se aplica la medida
     * default de la empresa destino, igual que en crear()). Tampoco copia
     * inventarios, precios, componentes ni variantes: dependen de bodegas u
     * otros productos que son propios de cada empresa.
     *
     * @return array{estado:string,id:int} estado: creado | reactivado | omitido
     */
    public function replicarProductoEnEmpresa(array $datosOrigen, int $idEmpresaDestino, int $idUsuario): array
    {
        $codigo = trim((string) ($datosOrigen['codigo'] ?? ''));
        if ($codigo === '') {
            throw new Exception('El producto no tiene código válido para replicar.');
        }

        $existente = $this->repository->findByCodigo($idEmpresaDestino, $codigo);

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
                    'productos',
                    $id,
                    $existente,
                    ['eliminado' => false, 'origen_empresa' => $datosOrigen['id_empresa'] ?? null]
                );
                $this->repository->commit();
                return ['estado' => 'reactivado', 'id' => $id];
            }

            $tipoProduccion = !empty($datosOrigen['tipo_produccion']) ? trim((string) $datosOrigen['tipo_produccion']) : '01';
            $inventariable = $tipoProduccion === '02' ? false : !empty($datosOrigen['inventariable']);

            $idMedida = null;
            $idTipoMedida = null;
            if ($tipoProduccion === '01') {
                $default = $this->repository->getMedidaDefaultUnidad($idEmpresaDestino);
                if ($default) {
                    $idMedida = $default['id_medida'];
                    $idTipoMedida = $default['id_tipo_medida'];
                }
            }

            $nuevo = [
                'id_empresa'      => $idEmpresaDestino,
                'id_usuario'      => $idUsuario,
                'codigo'          => $codigo,
                'nombre'          => $datosOrigen['nombre'] ?? '',
                'codigo_auxiliar' => $datosOrigen['codigo_auxiliar'] ?? '',
                'codigo_barras'   => $datosOrigen['codigo_barras'] ?? '',
                'precio_base'     => $datosOrigen['precio_base'] ?? 0,
                'tipo_produccion' => $tipoProduccion,
                'tarifa_iva'      => $datosOrigen['tarifa_iva'] ?? 2, // catálogo global, se copia tal cual
                'status'          => $datosOrigen['status'] ?? 1,
                'inventariable'   => $inventariable,
                'costo_producto'  => $datosOrigen['costo_producto'] ?? 0,
                'stock_minimo'    => $datosOrigen['stock_minimo'] ?? 0,
                'stock_maximo'    => $datosOrigen['stock_maximo'] ?? 0,
                'imagen'          => $datosOrigen['imagen'] ?? null,
                'opciones'        => $datosOrigen['opciones'] ?? '{"compra":true,"venta":true}',
                // Datos descriptivos del ICE (no son FK) se copian; el id_ice sí
                // es por-empresa (empresa_ice) y no se copia.
                'valor_ice'       => $datosOrigen['valor_ice'] ?? null,
                'codigo_ice'      => $datosOrigen['codigo_ice'] ?? null,
                'nombre_ice'      => $datosOrigen['nombre_ice'] ?? null,
                // Catálogos por-empresa: nunca se copian tal cual (ver docblock del método).
                'id_categoria'    => null,
                'id_marca'        => null,
                'id_ice'          => null,
                'id_medida'       => $idMedida,
                'id_tipo_medida'  => $idTipoMedida,
            ];

            $this->rules->validar($nuevo);

            $id = $this->repository->create($nuevo);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresaDestino,
                'crear_replicado',
                'productos',
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

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $antes = $this->repository->getDetalleCompleto($id, $idEmpresa);
        if (!$antes) throw new Exception('El producto no existe.');

        // Si el producto ya fue usado en facturas o inventario, los campos que
        // identifican al producto NO pueden cambiar:
        //  - codigo: es la llave con la que se homologa, se busca y se reimprime.
        //  - tipo_produccion: cambiarlo altera inventario y contabilidad.
        // El NOMBRE sí se puede corregir siempre: cada documento (venta, NC,
        // compra, guía, proforma, etc.) guarda su propia copia del nombre en su
        // detalle, de modo que corregir un typo aquí no altera nada ya emitido.
        // El inventario (kardex) no guarda nombre: lee el actual, así que reflejar
        // el nombre corregido es justo lo deseado (es el mismo producto físico).
        if ($this->repository->estaUsadoEnDocumentos($id, $idEmpresa)) {
            $data['codigo']          = $antes['codigo'];
            $data['tipo_produccion'] = $antes['tipo_produccion'];
        }

        $tipoProduccion = !empty($data['tipo_produccion']) ? trim($data['tipo_produccion']) : '01';

        // Un servicio (02) nunca maneja inventario
        if ($tipoProduccion === '02') {
            $data['inventariable'] = false;
        }

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
                'nombre'                => trim(preg_replace('/\s+/u', ' ', (string) $data['nombre'])),
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
                'nombre_ice'            => !empty($data['nombre_ice']) ? trim(preg_replace('/\s+/u', ' ', (string) $data['nombre_ice'])) : null,
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
                'ubicacion'             => !empty($data['ubicacion']) ? trim(preg_replace('/\s+/u', ' ', (string) $data['ubicacion'])) : null,
                'excluir_recargo_servicio' => !empty($data['excluir_recargo_servicio']),
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

        // Obtener el ambiente activo de la empresa para filtrar solo los documentos del entorno actual
        $tipoAmbiente = $this->repository->getTipoAmbienteEmpresa($idEmpresa);

        $usos = $this->repository->obtenerUsos($id, $idEmpresa, $tipoAmbiente);
        if (!empty($usos)) {
            $entorno = $tipoAmbiente === '2' ? 'producción' : 'pruebas';
            throw new Exception(
                "No se puede eliminar el producto porque está siendo utilizado en el entorno de {$entorno}: " .
                implode(', ', $usos) . '. Desactívelo si no desea que aparezca en nuevos documentos.'
            );
        }

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

    /** Recalcula el costo de un producto a partir del promedio ponderado de sus entradas en el Kardex. */
    public function actualizarCostoDesdeKardex(int $id, int $idEmpresa, int $idUsuario): float
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) throw new Exception('El producto no existe.');

        $costo = $this->repository->calcularCostoPromedioKardex($id, $idEmpresa);
        if ($costo === null) {
            throw new Exception('Este producto no tiene entradas registradas en el Kardex.');
        }

        $this->repository->actualizarCosto($id, $idEmpresa, $costo, $idUsuario);

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'actualizar',
            'productos',
            $id,
            ['costo_producto' => $antes['costo_producto'] ?? 0],
            ['costo_producto' => $costo]
        );

        return $costo;
    }

    /**
     * Recalcula el costo de todos los productos inventariables de la empresa
     * (respetando "registros propios" cuando el usuario no tiene acceso total).
     */
    public function actualizarCostoMasivo(int $idEmpresa, ?int $idUsuarioFiltro, int $idUsuario): array
    {
        $productos = $this->repository->getInventariablesConCosto($idEmpresa, $idUsuarioFiltro);

        $actualizados = 0;
        $sinMovimientos = 0;

        $this->repository->beginTransaction();
        try {
            foreach ($productos as $p) {
                $costo = $this->repository->calcularCostoPromedioKardex((int)$p['id'], $idEmpresa);
                if ($costo === null) {
                    $sinMovimientos++;
                    continue;
                }

                $costoAntes = (float)($p['costo_producto'] ?? 0);
                if (abs($costoAntes - $costo) > 0.000001) {
                    $this->repository->actualizarCosto((int)$p['id'], $idEmpresa, $costo, $idUsuario);
                    $this->logService->registrar(
                        $idUsuario,
                        $idEmpresa,
                        'actualizar',
                        'productos',
                        (int)$p['id'],
                        ['costo_producto' => $costoAntes],
                        ['costo_producto' => $costo]
                    );
                }
                $actualizados++;
            }
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }

        return [
            'total'           => count($productos),
            'actualizados'    => $actualizados,
            'sin_movimientos' => $sinMovimientos,
        ];
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
