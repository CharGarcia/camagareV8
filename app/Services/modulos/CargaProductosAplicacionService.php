<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CargaProductosRepository;
use App\repositories\modulos\ProductoRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Aplica una carga de productos ya validada.
 *
 * Escribe a través de ProductoService::crear()/actualizar() para conservar sus
 * reglas de negocio, sus transacciones y su auditoría. Las homologaciones —que
 * ese servicio no maneja— se sincronizan aparte.
 *
 * Se procesa PRODUCTO A PRODUCTO, cada uno con su propia transacción (la abre
 * ProductoService). No se puede envolver todo en una transacción externa porque
 * BaseRepository::commit() haría commit en el primer producto. A cambio, un
 * fallo aislado no tumba la carga completa: se informa y se sigue.
 */
class CargaProductosAplicacionService
{
    private CargaProductosRepository $repository;
    private ProductoRepository $productoRepository;
    private ProductoService $productoService;
    private LogSistemaService $logService;

    public function __construct(
        CargaProductosRepository $repository,
        ProductoRepository $productoRepository,
        ProductoService $productoService,
        LogSistemaService $logService
    ) {
        $this->repository         = $repository;
        $this->productoRepository = $productoRepository;
        $this->productoService    = $productoService;
        $this->logService         = $logService;
    }

    /**
     * @param array $informe Salida de CargaProductosValidacionService::validar().
     * @return array Resultado de la aplicación.
     */
    public function aplicar(array $informe, int $idEmpresa, int $idUsuario): array
    {
        $resultado = [
            'creados'      => 0,
            'actualizados' => 0,
            'omitidos'     => 0,
            'fallidos'     => 0,
            'detalle'      => [],
        ];

        // Solo se aplican los productos sin errores. Los bloqueados se omiten y
        // se informan (aplicación parcial).
        $aplicables = [];
        foreach ($informe['productos'] ?? [] as $clave => $p) {
            if (!empty($p['errores'])) {
                $resultado['omitidos']++;
                $resultado['detalle'][] = [
                    'codigo'  => $p['codigo'],
                    'estado'  => 'omitido',
                    'mensaje' => 'Tiene errores de validación.',
                ];
                continue;
            }
            $aplicables[$clave] = $p;
        }

        if (!$aplicables) {
            return $resultado;
        }

        // ── Paso 1: catálogos nuevos (categorías y marcas) ───────────────────
        $this->crearCatalogosFaltantes($aplicables, $idEmpresa, $idUsuario);

        // ── Paso 2: crear/actualizar cada producto ───────────────────────────
        // Los componentes se dejan para el paso 3: un componente puede apuntar a
        // un producto que se crea en esta misma carga y aún no tiene id.
        $idsPorCodigo = [];

        foreach ($aplicables as $clave => $p) {
            try {
                $datos = $this->construirDatos($p, $idEmpresa, $idUsuario);

                if ($p['accion'] === 'crear') {
                    $id = $this->productoService->crear($datos);
                    $resultado['creados']++;
                    $estado = 'creado';
                } else {
                    $id = (int) $p['id_producto'];
                    $this->productoService->actualizar($id, $idEmpresa, $datos);
                    $resultado['actualizados']++;
                    $estado = 'actualizado';
                }

                $idsPorCodigo[$clave] = $id;
                $aplicables[$clave]['id_aplicado'] = $id;

                $resultado['detalle'][] = [
                    'codigo'  => $p['codigo'],
                    'estado'  => $estado,
                    'mensaje' => '',
                ];
            } catch (\Throwable $e) {
                $resultado['fallidos']++;
                $aplicables[$clave]['fallo'] = true;
                $resultado['detalle'][] = [
                    'codigo'  => $p['codigo'],
                    'estado'  => 'error',
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        // ── Paso 3: componentes y homologaciones ─────────────────────────────
        // Ya existen todos los ids, así que un componente puede referenciar a un
        // producto creado en esta misma carga.
        foreach ($aplicables as $clave => $p) {
            if (!empty($p['fallo']) || !isset($idsPorCodigo[$clave])) {
                continue;
            }
            $idProducto = $idsPorCodigo[$clave];

            try {
                if ($this->tieneSeccion($p, CargaProductosEsquema::HOJA_COMPONENTES)) {
                    $this->aplicarComponentes($idProducto, $p, $idsPorCodigo, $idEmpresa, $idUsuario);
                }
                if ($this->tieneSeccion($p, CargaProductosEsquema::HOJA_HOMOLOGACIONES)) {
                    $this->aplicarHomologaciones($idProducto, $p, $idEmpresa, $idUsuario);
                }
            } catch (\Throwable $e) {
                $resultado['fallidos']++;
                $resultado['detalle'][] = [
                    'codigo'  => $p['codigo'],
                    'estado'  => 'error',
                    'mensaje' => 'El producto se guardó, pero falló su detalle: ' . $e->getMessage(),
                ];
            }
        }

        // Auditoría de la carga completa.
        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'carga_masiva_excel',
            'productos',
            null,
            null,
            [
                'creados'      => $resultado['creados'],
                'actualizados' => $resultado['actualizados'],
                'omitidos'     => $resultado['omitidos'],
                'fallidos'     => $resultado['fallidos'],
            ]
        );

        return $resultado;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** Da de alta las categorías y marcas que el validador marcó como nuevas. */
    private function crearCatalogosFaltantes(array &$productos, int $idEmpresa, int $idUsuario): void
    {
        $categorias = [];
        $marcas     = [];

        foreach ($productos as $p) {
            if (!empty($p['crear_categoria']) && $p['categoria'] !== '') {
                $categorias[mb_strtolower($p['categoria'])] = $p['categoria'];
            }
            if (!empty($p['crear_marca']) && $p['marca'] !== '') {
                $marcas[mb_strtolower($p['marca'])] = $p['marca'];
            }
        }

        $idsCategorias = [];
        foreach ($categorias as $k => $nombre) {
            $idsCategorias[$k] = $this->repository->crearCategoria($nombre, $idEmpresa, $idUsuario);
        }

        $idsMarcas = [];
        foreach ($marcas as $k => $nombre) {
            $idsMarcas[$k] = $this->repository->crearMarca($nombre, $idEmpresa, $idUsuario);
        }

        foreach ($productos as $clave => $p) {
            if (!empty($p['crear_categoria'])) {
                $k = mb_strtolower($p['categoria']);
                $productos[$clave]['id_categoria'] = $idsCategorias[$k] ?? null;
            }
            if (!empty($p['crear_marca'])) {
                $k = mb_strtolower($p['marca']);
                $productos[$clave]['id_marca'] = $idsMarcas[$k] ?? null;
            }
        }
    }

    /**
     * Arma el array que espera ProductoService.
     *
     * Clave del diseño: las secciones hijas SOLO se incluyen si el producto
     * apareció en esa hoja. ProductoService llama a sync* únicamente cuando la
     * clave está presente, y esos sync* borran y reinsertan; omitir la clave es
     * lo que conserva intacta la sección que el usuario no tocó.
     */
    private function construirDatos(array $p, int $idEmpresa, int $idUsuario): array
    {
        $datos = [
            'id_empresa'      => $idEmpresa,
            'id_usuario'      => $idUsuario,
            'codigo'          => $p['codigo'],
            'nombre'          => $p['nombre'],
            'codigo_auxiliar' => $p['codigo_auxiliar'],
            'codigo_barras'   => $p['codigo_barras'],
            'precio_base'     => $p['precio_base'],
            'costo_producto'  => $p['costo_producto'],
            'tipo_produccion' => $p['tipo_produccion'],
            'tarifa_iva'      => $p['tarifa_iva'],
            'id_medida'       => $p['id_medida'],
            'id_tipo_medida'  => $p['id_tipo_medida'],
            'id_categoria'    => $p['id_categoria'],
            'id_marca'        => $p['id_marca'],
            'inventariable'   => (bool) $p['inventariable'],
            'stock_minimo'    => $p['stock_minimo'],
            'stock_maximo'    => $p['stock_maximo'],
            'status'          => (int) $p['estado'],
            'id_ice'          => $p['id_ice'],
            'valor_ice'       => $p['valor_ice'],
            'nombre_ice'      => $p['nombre_ice'],
            'codigo_ice'      => $p['codigo_ice'] !== '' ? $p['codigo_ice'] : null,
            'opciones'        => json_encode([
                'compra' => (bool) $p['aplica_compra'],
                'venta'  => (bool) $p['aplica_venta'],
            ]),
        ];

        if ($this->tieneSeccion($p, CargaProductosEsquema::HOJA_PRECIOS)) {
            $datos['precios'] = array_map(static fn($x) => [
                'nombre_precio' => $x['nombre_precio'],
                'precio'        => $x['precio'],
                'valido_desde'  => $x['valido_desde'],
                'valido_hasta'  => $x['valido_hasta'],
                'estado'        => $x['estado'],
            ], $p['precios']);
        }

        if ($this->tieneSeccion($p, CargaProductosEsquema::HOJA_VARIANTES)) {
            $datos['variantes'] = array_map(static fn($x) => [
                'nombre'           => $x['nombre'],
                'valor'            => $x['valor'],
                'precio_adicional' => $x['precio_adicional'],
            ], $p['variantes']);
        }

        if ($this->tieneSeccion($p, CargaProductosEsquema::HOJA_STOCK_BODEGAS)) {
            // Solo stock mínimo/máximo. stock_actual no se toca: lo maneja el
            // kardex y syncInventarios lo conserva en el ON CONFLICT.
            $datos['inventarios'] = array_map(static fn($x) => [
                'id_bodega'    => $x['id_bodega'],
                'stock_minimo' => $x['stock_minimo'],
                'stock_maximo' => $x['stock_maximo'],
            ], $p['bodegas']);
        }

        return $datos;
    }

    /** Sincroniza los componentes resolviendo hijos creados en esta misma carga. */
    private function aplicarComponentes(
        int $idProducto,
        array $p,
        array $idsPorCodigo,
        int $idEmpresa,
        int $idUsuario
    ): void {
        $componentes = [];

        foreach ($p['componentes'] as $c) {
            $idHijo = $c['id_producto_hijo'];
            if (!$idHijo) {
                $idHijo = $idsPorCodigo[mb_strtolower($c['codigo_hijo'])] ?? null;
            }
            if (!$idHijo) {
                throw new Exception('No se pudo resolver el componente "' . $c['codigo_hijo'] . '".');
            }
            $componentes[] = [
                'id_producto_hijo' => $idHijo,
                'cantidad'         => $c['cantidad'],
                'id_medida'        => $c['id_medida'],
            ];
        }

        $this->productoRepository->beginTransaction();
        try {
            $this->productoRepository->syncComponentes($idProducto, $idEmpresa, $componentes, $idUsuario);
            $this->productoRepository->commit();
        } catch (\Throwable $e) {
            $this->productoRepository->rollBack();
            throw $e;
        }
    }

    private function aplicarHomologaciones(int $idProducto, array $p, int $idEmpresa, int $idUsuario): void
    {
        $this->productoRepository->beginTransaction();
        try {
            $this->repository->syncHomologaciones($idProducto, $idEmpresa, $p['homologaciones'], $idUsuario);
            $this->productoRepository->commit();
        } catch (\Throwable $e) {
            $this->productoRepository->rollBack();
            throw $e;
        }
    }

    /** ¿El producto apareció en esa hoja hija? */
    private function tieneSeccion(array $p, string $hoja): bool
    {
        return !empty($p['secciones'][$hoja]);
    }
}
