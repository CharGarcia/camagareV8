<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteInventarioRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\BodegaRepository;
use App\repositories\modulos\CategoriaRepository;
use App\repositories\modulos\MarcaRepository;
use App\repositories\modulos\VendedorRepository;
use App\repositories\modulos\ProductoRepository;
use App\repositories\modulos\ClienteRepository;
use App\models\Empresa;

class ReporteInventariosController extends BaseModuloController
{
    private ReporteInventarioRepository $repository;
    private const RUTA_MODULO = 'modulos/reporte_inventarios';

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteInventarioRepository();
    }

    // ────────────────────────────────────────────────────────────────
    // INDEX
    // ────────────────────────────────────────────────────────────────
    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $bodegas    = (new BodegaRepository())->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        $categorias = (new CategoriaRepository())->getListado($idEmpresa, '', 1, 0, 'nombre', 'ASC', null)['rows'];
        $marcas     = (new MarcaRepository())->getListado($idEmpresa, '', 1, 0, 'nombre', 'ASC', null)['rows'];
        $vendedores = (new VendedorRepository())->getVendedoresActivos($idEmpresa);
        $usuarios   = (new InventarioRepository())->getUsuariosConMovimientos($idEmpresa);
        $origenes   = (new InventarioRepository())->getTiposReferencia($idEmpresa);
        $anios      = $this->repository->getAniosMovimientos($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/reporte_inventarios/index', [
            'titulo'     => 'Reporte de Inventarios',
            'perm'       => $this->getPermisos(),
            'vistaConfig'=> $prefsVista,
            'rutaModulo' => self::RUTA_MODULO,
            'bodegas'    => $bodegas,
            'categorias' => $categorias,
            'marcas'     => $marcas,
            'vendedores' => $vendedores,
            'usuarios'   => $usuarios,
            'origenes'   => $origenes,
            'anios'      => $anios,
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // FILTROS POR PESTAÑA
    // ────────────────────────────────────────────────────────────────
    private function getFiltrosExistencias(): array
    {
        return [
            'agrupar_por'  => $_REQUEST['agrupar_por'] ?? 'NINGUNO',
            'id_bodega'    => $_REQUEST['id_bodega']    ?? '',
            'id_categoria' => $_REQUEST['id_categoria'] ?? '',
            'id_marca'     => $_REQUEST['id_marca']     ?? '',
            'id_producto'  => $_REQUEST['id_producto']  ?? '',
            'estado_stock' => $_REQUEST['estado_stock'] ?? '',
            'numero_lote'  => trim($_REQUEST['numero_lote'] ?? ''),
            'nup'          => trim($_REQUEST['nup'] ?? ''),
            'fecha_caducidad_desde' => $_REQUEST['fecha_caducidad_desde'] ?? '',
            'fecha_caducidad_hasta' => $_REQUEST['fecha_caducidad_hasta'] ?? '',
            'orden'        => $_REQUEST['orden'] ?? '',
            'dir'          => $_REQUEST['dir'] ?? 'ASC',
            'buscar'       => trim($_REQUEST['buscar']  ?? ''),
        ];
    }

    private function getFiltrosMovimientos(): array
    {
        return [
            'agrupar_por'     => $_REQUEST['agrupar_por'] ?? 'NINGUNO',
            'fecha_desde'     => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'     => $_REQUEST['fecha_hasta'] ?? '',
            'id_bodega'       => $_REQUEST['id_bodega']    ?? '',
            'id_producto'     => $_REQUEST['id_producto']  ?? '',
            'id_categoria'    => $_REQUEST['id_categoria'] ?? '',
            'id_marca'        => $_REQUEST['id_marca']     ?? '',
            'tipo_movimiento' => $_REQUEST['tipo_movimiento'] ?? '',
            'referencia_tipo' => $_REQUEST['referencia_tipo'] ?? '',
            'id_usuario'      => $_REQUEST['id_usuario']   ?? '',
            'numero_lote'     => trim($_REQUEST['numero_lote'] ?? ''),
            'nup'             => trim($_REQUEST['nup'] ?? ''),
            'buscar'          => trim($_REQUEST['buscar'] ?? ''),
        ];
    }

    private function getFiltrosValorizacion(): array
    {
        return [
            'agrupar_por'  => $_REQUEST['agrupar_por'] ?? 'PRODUCTO',
            'id_bodega'    => $_REQUEST['id_bodega']    ?? '',
            'id_categoria' => $_REQUEST['id_categoria'] ?? '',
            'id_marca'     => $_REQUEST['id_marca']     ?? '',
            'id_producto'  => $_REQUEST['id_producto']  ?? '',
        ];
    }

    private function getFiltrosConsignaciones(): array
    {
        return [
            'agrupar_por'        => $_REQUEST['agrupar_por'] ?? 'NINGUNO',
            'estado'             => $_REQUEST['estado'] ?? 'TODOS',
            'id_cliente'         => $_REQUEST['id_cliente']  ?? '',
            'id_producto'        => $_REQUEST['id_producto'] ?? '',
            'id_bodega'          => $_REQUEST['id_bodega']   ?? '',
            'id_vendedor'        => $_REQUEST['id_vendedor'] ?? '',
            'fecha_desde'        => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'        => $_REQUEST['fecha_hasta'] ?? '',
            'numero_lote'        => trim($_REQUEST['numero_lote'] ?? ''),
            'nup'                => trim($_REQUEST['nup'] ?? ''),
            'fecha_caducidad_desde' => $_REQUEST['fecha_caducidad_desde'] ?? '',
            'fecha_caducidad_hasta' => $_REQUEST['fecha_caducidad_hasta'] ?? '',
            'secuencial'         => trim($_REQUEST['secuencial'] ?? ''),
            'incluir_liquidadas' => !empty($_REQUEST['incluir_liquidadas']),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // GENERAR (AJAX) — dispatcher por pestaña
    // ────────────────────────────────────────────────────────────────
    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $tab = $_REQUEST['tab'] ?? 'existencias';

            $resultado = match ($tab) {
                'movimientos'    => $this->generarMovimientos($idEmpresa),
                'valorizacion'   => $this->generarValorizacion($idEmpresa),
                'consignaciones' => $this->generarConsignaciones($idEmpresa),
                default          => $this->generarExistencias($idEmpresa),
            };

            echo json_encode(array_merge(['ok' => true], $resultado));
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            error_log('ReporteInventario Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function generarExistencias(int $idEmpresa): array
    {
        $filtros = $this->getFiltrosExistencias();
        $modo = $filtros['agrupar_por'];

        $rows = match ($modo) {
            'PRODUCTO'  => $this->repository->getExistenciasAgrupadoProducto($idEmpresa, $filtros),
            'CATEGORIA' => $this->repository->getExistenciasAgrupadoCategoria($idEmpresa, $filtros),
            'BODEGA'    => $this->repository->getExistenciasAgrupadoBodega($idEmpresa, $filtros),
            default     => $this->repository->getExistenciasDetalle($idEmpresa, $filtros),
        };
        $kpis = $this->repository->getExistenciasKpis($idEmpresa, $filtros);

        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaExistencias($r, $modo), $modo === 'NINGUNO' ? 8 : 6),
            'rawData'    => $rows,
            'kpis'       => $kpis,
            'agrupacion' => $modo,
        ];
    }

    private function generarMovimientos(int $idEmpresa): array
    {
        $filtros = $this->getFiltrosMovimientos();
        $modo = $filtros['agrupar_por'];

        $rows = match ($modo) {
            'PRODUCTO' => $this->repository->getMovimientosAgrupadoProducto($idEmpresa, $filtros),
            'BODEGA'   => $this->repository->getMovimientosAgrupadoBodega($idEmpresa, $filtros),
            'TIPO'     => $this->repository->getMovimientosAgrupadoTipo($idEmpresa, $filtros),
            'ORIGEN'   => $this->repository->getMovimientosAgrupadoOrigen($idEmpresa, $filtros),
            'FECHA'    => $this->repository->getMovimientosAgrupadoFecha($idEmpresa, $filtros),
            'MES'      => $this->repository->getMovimientosAgrupadoMes($idEmpresa, $filtros),
            default    => $this->repository->getMovimientosDetalle($idEmpresa, $filtros),
        };
        $kpis = $this->repository->getMovimientosKpis($idEmpresa, $filtros);

        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaMovimientos($r, $modo), $modo === 'NINGUNO' ? 10 : 6),
            'rawData'    => $rows,
            'kpis'       => $kpis,
            'agrupacion' => $modo,
        ];
    }

    private function generarValorizacion(int $idEmpresa): array
    {
        $filtros = $this->getFiltrosValorizacion();
        $modo = $filtros['agrupar_por'];

        $rows = match ($modo) {
            'CATEGORIA' => $this->repository->getValorizacionAgrupadoCategoria($idEmpresa, $filtros),
            'BODEGA'    => $this->repository->getValorizacionAgrupadoBodega($idEmpresa, $filtros),
            'MARCA'     => $this->repository->getValorizacionAgrupadoMarca($idEmpresa, $filtros),
            default     => $this->repository->getValorizacionAgrupadoProducto($idEmpresa, $filtros),
        };
        $kpis = $this->repository->getValorizacionKpis($idEmpresa, $filtros);

        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaValorizacion($r), 5),
            'rawData'    => $rows,
            'kpis'       => $kpis,
            'agrupacion' => $modo,
        ];
    }

    private function generarConsignaciones(int $idEmpresa): array
    {
        $filtros = $this->getFiltrosConsignaciones();
        $modo = $filtros['agrupar_por'];

        $rows = match ($modo) {
            'CLIENTE'  => $this->repository->getConsignacionesAgrupadoCliente($idEmpresa, $filtros),
            'PRODUCTO' => $this->repository->getConsignacionesAgrupadoProducto($idEmpresa, $filtros),
            default    => $this->repository->getConsignacionesCabeceras($idEmpresa, $filtros),
        };
        $kpis = $this->repository->getConsignacionesKpis($idEmpresa, $filtros);

        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaConsignaciones($r, $modo), $modo === 'NINGUNO' ? 6 : 3),
            'rawData'    => $rows,
            'kpis'       => $kpis,
            'agrupacion' => $modo,
        ];
    }

    private function renderRows(array $rows, callable $render, int $colSpanVacio): string
    {
        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="' . $colSpanVacio . '" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo $render($r);
            }
        }
        return (string) ob_get_clean();
    }

    // ────────────────────────────────────────────────────────────────
    // RENDER DE FILAS POR PESTAÑA
    // ────────────────────────────────────────────────────────────────
    private function filaExistencias(array $r, string $modo): string
    {
        $costo = number_format((float) ($r['costo_unitario'] ?? 0), 4);
        $valor = number_format((float) ($r['valor_total'] ?? 0), 2);

        if ($modo === 'NINGUNO') {
            $idProducto = (int) ($r['id_producto'] ?? 0);
            $idBodega   = (int) ($r['id_bodega'] ?? 0);
            $idCategoriaActual = $r['id_categoria'] ?? '';
            $stockMinimo = (float) ($r['stock_minimo'] ?? 0);
            $stockMaximo = (float) ($r['stock_maximo'] ?? 0);

            return '<tr class="ri-ex-row" style="cursor:pointer;" title="Editar mínimo, máximo y categoría"'
                . ' onclick="window.RI_Existencias.abrirModalEditar(this)"'
                . ' data-id-producto="' . $idProducto . '"'
                . ' data-id-bodega="' . $idBodega . '"'
                . ' data-producto-nombre="' . htmlspecialchars($r['producto_nombre'] ?? '', ENT_QUOTES) . '"'
                . ' data-bodega-nombre="' . htmlspecialchars($r['bodega_nombre'] ?? '', ENT_QUOTES) . '"'
                . ' data-stock-minimo="' . $stockMinimo . '"'
                . ' data-stock-maximo="' . $stockMaximo . '"'
                . ' data-id-categoria="' . $idCategoriaActual . '"'
                . '>'
                . '<td><span class="fw-bold">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</span></td>'
                . '<td class="small">' . htmlspecialchars($r['categoria_nombre'] ?? '') . '</td>'
                . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '') . '</td>'
                . '<td class="text-end fw-bold">' . number_format((float) ($r['stock_actual'] ?? 0), 2) . '</td>'
                . '<td class="text-end small text-muted">' . number_format($stockMinimo, 2) . '</td>'
                . '<td class="text-end small text-muted">' . number_format($stockMaximo, 2) . '</td>'
                . '<td class="text-end">' . $costo . '</td>'
                . '<td class="text-end fw-bold text-primary">' . $valor . '</td>'
                . '</tr>';
        }

        return '<tr>'
            . '<td class="fw-bold">' . htmlspecialchars((string) ($r['nombre_grupo'] ?? '')) . '</td>'
            . '<td class="text-center">' . (int) ($r['cantidad_productos'] ?? 0) . '</td>'
            . '<td class="text-end fw-bold">' . number_format((float) ($r['stock_actual'] ?? 0), 2) . '</td>'
            . '<td class="text-end small text-muted">' . number_format((float) ($r['stock_minimo'] ?? 0), 2) . '</td>'
            . '<td class="text-end">' . $costo . '</td>'
            . '<td class="text-end fw-bold text-primary">' . $valor . '</td>'
            . '</tr>';
    }

    private function filaMovimientos(array $r, string $modo): string
    {
        if ($modo === 'NINGUNO') {
            $cant = (float) ($r['cantidad'] ?? 0);
            $signo = $cant >= 0 ? '+' : '-';
            $color = $cant >= 0 ? 'text-success' : 'text-danger';
            $cad = !empty($r['fecha_caducidad']) ? date('d-m-Y', strtotime($r['fecha_caducidad'])) : '-';
            return '<tr>'
                . '<td class="small text-nowrap">' . date('d-m-Y H:i', strtotime($r['fecha_movimiento'] ?? '')) . '</td>'
                . '<td><span class="fw-bold">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</span></td>'
                . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '') . '</td>'
                . '<td class="text-center small text-uppercase">' . htmlspecialchars($r['tipo_movimiento'] ?? '') . '</td>'
                . '<td class="small">' . htmlspecialchars($r['origen_label'] ?? '') . '</td>'
                . '<td class="text-end fw-bold"><span class="' . $color . '">' . $signo . number_format(abs($cant), 2) . '</span></td>'
                . '<td class="text-end small">' . number_format((float) ($r['costo_unitario'] ?? 0), 4) . '</td>'
                . '<td class="small">' . htmlspecialchars($r['numero_lote'] ?? '-') . '</td>'
                . '<td class="small">' . $cad . '</td>'
                . '<td class="small" style="max-width:280px;white-space:normal;word-break:break-word;">' . htmlspecialchars($r['observaciones'] ?? '-') . '</td>'
                . '</tr>';
        }

        $label = $r['nombre_grupo'] ?? '';
        if ($modo === 'FECHA' && !empty($label)) {
            $label = date('d/m/Y', strtotime((string) $label));
        }
        return '<tr>'
            . '<td class="fw-bold">' . htmlspecialchars((string) $label) . '</td>'
            . '<td class="text-center">' . (int) ($r['cantidad_movimientos'] ?? 0) . '</td>'
            . '<td class="text-end text-success">' . number_format((float) ($r['total_entradas'] ?? 0), 2) . '</td>'
            . '<td class="text-end text-danger">' . number_format((float) ($r['total_salidas'] ?? 0), 2) . '</td>'
            . '<td class="text-end fw-bold">' . number_format((float) ($r['saldo_neto'] ?? 0), 2) . '</td>'
            . '<td class="text-end small text-muted">' . number_format((float) ($r['costo_total'] ?? 0), 2) . '</td>'
            . '</tr>';
    }

    private function filaValorizacion(array $r): string
    {
        return '<tr>'
            . '<td class="fw-bold">' . htmlspecialchars((string) ($r['nombre_grupo'] ?? '')) . '</td>'
            . '<td class="text-center">' . (int) ($r['cantidad_productos'] ?? 0) . '</td>'
            . '<td class="text-end">' . number_format((float) ($r['stock_actual'] ?? 0), 2) . '</td>'
            . '<td class="text-end">' . number_format((float) ($r['costo_promedio'] ?? 0), 4) . '</td>'
            . '<td class="text-end fw-bold text-primary">' . number_format((float) ($r['valor_total'] ?? 0), 2) . '</td>'
            . '</tr>';
    }

    private function filaConsignaciones(array $r, string $modo): string
    {
        if ($modo === 'NINGUNO') {
            $saldo = (float) ($r['saldo'] ?? 0);
            $badgesEstado = [
                'Entregada' => 'bg-success',
                'Emitida'   => 'bg-warning text-dark',
                'Anulada'   => 'bg-danger',
            ];
            $estado = $r['estado'] ?? '';
            $badgeClass = $badgesEstado[$estado] ?? 'bg-secondary';
            return '<tr class="ri-cv-row" style="cursor:pointer;" onclick="window.RI_Consignaciones.verDetalle(' . (int) ($r['id_consignacion'] ?? 0) . ')" title="Ver detalle de productos">'
                . '<td class="small">' . date('d-m-Y', strtotime($r['fecha_emision'] ?? '')) . '<br><small class="text-muted">' . htmlspecialchars($r['secuencial'] ?? '') . '</small></td>'
                . '<td><span class="fw-bold">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</span><br><small class="text-muted">' . htmlspecialchars($r['cliente_identificacion'] ?? '') . '</small></td>'
                . '<td class="small">' . htmlspecialchars($r['vendedor_nombre'] ?? '-') . '</td>'
                . '<td class="text-center">' . (int) ($r['cantidad_productos'] ?? 0) . '</td>'
                . '<td class="text-end fw-bold">' . number_format($saldo, 2) . '</td>'
                . '<td class="text-center"><span class="badge ' . $badgeClass . '">' . htmlspecialchars($estado) . '</span></td>'
                . '</tr>';
        }

        return '<tr>'
            . '<td class="fw-bold">' . htmlspecialchars((string) ($r['nombre_grupo'] ?? '')) . '</td>'
            . '<td class="text-center">' . (int) ($r['cantidad_consignaciones'] ?? 0) . '</td>'
            . '<td class="text-end fw-bold">' . number_format((float) ($r['saldo'] ?? 0), 2) . '</td>'
            . '</tr>';
    }

    /** Fila de línea de producto dentro del modal de detalle de una consignación. */
    private function filaConsignacionDetalleLinea(array $r): string
    {
        return '<tr>'
            . '<td class="small">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['numero_lote'] ?? '-') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['nup'] ?? '-') . '</td>'
            . '<td class="text-end small">' . number_format((float) ($r['cantidad_consignada'] ?? 0), 2) . '</td>'
            . '<td class="text-end small">' . number_format((float) ($r['cantidad_retornada'] ?? 0), 2) . '</td>'
            . '<td class="text-end small">' . number_format((float) ($r['cantidad_facturada'] ?? 0), 2) . '</td>'
            . '<td class="text-end small fw-bold">' . number_format((float) ($r['saldo'] ?? 0), 2) . '</td>'
            . '</tr>';
    }

    // ────────────────────────────────────────────────────────────────
    // EDICIÓN EN LÍNEA (Existencias): mínimo/máximo por bodega y categoría
    // ────────────────────────────────────────────────────────────────
    public function actualizarMinMaxAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEmpresa   = (int) $_SESSION['id_empresa'];
            $idUsuario   = (int) $_SESSION['id_usuario'];
            $idProducto  = (int) ($_REQUEST['id_producto'] ?? 0);
            $idBodega    = (int) ($_REQUEST['id_bodega'] ?? 0);
            $stockMinimo = (float) ($_REQUEST['stock_minimo'] ?? 0);
            $stockMaximo = (float) ($_REQUEST['stock_maximo'] ?? 0);

            if ($idProducto <= 0 || $idBodega <= 0) {
                throw new \InvalidArgumentException('Producto o bodega no válidos.');
            }
            if ($stockMinimo < 0 || $stockMaximo < 0) {
                throw new \InvalidArgumentException('El mínimo y el máximo no pueden ser negativos.');
            }
            if ($stockMaximo > 0 && $stockMaximo < $stockMinimo) {
                throw new \InvalidArgumentException('El máximo no puede ser menor que el mínimo.');
            }

            $inventarioRepo = new InventarioRepository();
            $antes = $inventarioRepo->getProductoBodega($idProducto, $idBodega, $idEmpresa);
            if (!$antes) {
                throw new \InvalidArgumentException('El producto no tiene existencias registradas en esa bodega.');
            }

            $inventarioRepo->actualizarMinMax($idProducto, $idBodega, $idEmpresa, $stockMinimo, $stockMaximo, $idUsuario);

            (new \App\Services\LogSistemaService())->registrar(
                $idUsuario, $idEmpresa, 'actualizar_min_max', 'productos_bodegas', $idProducto,
                ['stock_minimo' => $antes['stock_minimo'], 'stock_maximo' => $antes['stock_maximo']],
                ['stock_minimo' => $stockMinimo, 'stock_maximo' => $stockMaximo]
            );

            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function actualizarCategoriaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEmpresa    = (int) $_SESSION['id_empresa'];
            $idUsuario    = (int) $_SESSION['id_usuario'];
            $idProducto   = (int) ($_REQUEST['id_producto'] ?? 0);
            $idCategoria  = !empty($_REQUEST['id_categoria']) ? (int) $_REQUEST['id_categoria'] : null;

            if ($idProducto <= 0) {
                throw new \InvalidArgumentException('Producto no válido.');
            }

            $productoRepo = new ProductoRepository();
            $antes = $productoRepo->getDetalleCompleto($idProducto, $idEmpresa);
            if (!$antes) {
                throw new \InvalidArgumentException('El producto no existe.');
            }

            $productoRepo->actualizarCategoria($idProducto, $idEmpresa, $idCategoria, $idUsuario);

            (new \App\Services\LogSistemaService())->registrar(
                $idUsuario, $idEmpresa, 'actualizar_categoria', 'productos', $idProducto,
                ['id_categoria' => $antes['id_categoria']],
                ['id_categoria' => $idCategoria]
            );

            $categoriaNombre = 'Sin categoría';
            if ($idCategoria !== null) {
                $cat = (new CategoriaRepository())->getDetalleCompleto($idCategoria, $idEmpresa);
                $categoriaNombre = $cat['nombre'] ?? 'Sin categoría';
            }

            echo json_encode(['ok' => true, 'categoria_nombre' => $categoriaNombre]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // DETALLE DE UNA CONSIGNACIÓN (modal, click en la fila del listado)
    // ────────────────────────────────────────────────────────────────
    public function verConsignacionDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idConsignacion = (int) ($_REQUEST['id'] ?? 0);
            if ($idConsignacion <= 0) {
                throw new \InvalidArgumentException('Consignación no válida.');
            }

            $lineas = $this->repository->getConsignacionDetalleLineas($idEmpresa, $idConsignacion);
            if (empty($lineas)) {
                echo json_encode(['ok' => false, 'error' => 'No se encontró la consignación o no pertenece a esta empresa.']);
                exit;
            }
            $cab = $lineas[0];

            echo json_encode([
                'ok' => true,
                'cabecera' => [
                    'secuencial'    => $cab['secuencial'] ?? '',
                    'fecha_emision' => !empty($cab['fecha_emision']) ? date('d-m-Y', strtotime($cab['fecha_emision'])) : '',
                    'cliente'       => $cab['cliente_nombre'] ?? '',
                    'identificacion'=> $cab['cliente_identificacion'] ?? '',
                    'vendedor'      => $cab['vendedor_nombre'] ?? '-',
                    'estado'        => $cab['estado'] ?? '',
                ],
                'rows' => $this->renderRows($lineas, fn($r) => $this->filaConsignacionDetalleLinea($r), 8),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // AUTOCOMPLETAR
    // ────────────────────────────────────────────────────────────────
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC');

        $data = array_map(fn($row) => [
            'id'              => $row['id'],
            'nombre'          => $row['nombre'] ?? '',
            'identificacion'  => $row['identificacion'] ?? '',
        ], $result['rows'] ?? []);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // EXPORTACIÓN
    // ────────────────────────────────────────────────────────────────
    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tab = $_REQUEST['tab'] ?? 'existencias';

        [$headers, $exportData, $titulo] = $this->datosExport($idEmpresa, $tab);

        try {
            $empresa       = (new Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel($titulo, $headers, $exportData, $titulo, $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tab = $_REQUEST['tab'] ?? 'existencias';

        [$headers, $exportData, $titulo] = $this->datosExport($idEmpresa, $tab);

        try {
            $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE INVENTARIOS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8.5pt; margin: 0 auto 20px auto; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 5px; text-align: center; }
                td { border: 1px solid #ccc; padding: 5px; }
                .text-end { text-align: right; }
                .header { text-align: center; margin-bottom: 20px; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3><?= htmlspecialchars($titulo) ?></h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <table>
                <thead>
                    <tr><?php foreach ($headers as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach ($exportData as $row): ?>
                        <tr>
                            <?php foreach ($row as $i => $val): ?>
                                <td class="<?= is_numeric($val) ? 'text-end' : '' ?>"><?= htmlspecialchars((string) $val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteInventarios_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    /** @return array{0: array, 1: array, 2: string} [headers, filas, título] */
    private function datosExport(int $idEmpresa, string $tab): array
    {
        switch ($tab) {
            case 'movimientos':
                $filtros = $this->getFiltrosMovimientos();
                $modo = $filtros['agrupar_por'];
                $rows = match ($modo) {
                    'PRODUCTO' => $this->repository->getMovimientosAgrupadoProducto($idEmpresa, $filtros),
                    'BODEGA'   => $this->repository->getMovimientosAgrupadoBodega($idEmpresa, $filtros),
                    'TIPO'     => $this->repository->getMovimientosAgrupadoTipo($idEmpresa, $filtros),
                    'ORIGEN'   => $this->repository->getMovimientosAgrupadoOrigen($idEmpresa, $filtros),
                    'FECHA'    => $this->repository->getMovimientosAgrupadoFecha($idEmpresa, $filtros),
                    'MES'      => $this->repository->getMovimientosAgrupadoMes($idEmpresa, $filtros),
                    default    => $this->repository->getMovimientosDetalle($idEmpresa, $filtros),
                };
                if ($modo === 'NINGUNO') {
                    $headers = ['Fecha', 'Producto', 'Código', 'Bodega', 'Tipo', 'Origen', 'Cantidad', 'Costo Unit.', 'Lote', 'Observaciones'];
                    $data = array_map(fn($r) => [
                        date('d-m-Y H:i', strtotime($r['fecha_movimiento'])),
                        $r['producto_nombre'] ?? '', $r['producto_codigo'] ?? '', $r['bodega_nombre'] ?? '',
                        strtoupper($r['tipo_movimiento'] ?? ''), $r['origen_label'] ?? '',
                        (float) $r['cantidad'], (float) $r['costo_unitario'],
                        $r['numero_lote'] ?? '', $r['observaciones'] ?? '',
                    ], $rows);
                } else {
                    $headers = ['Grupo', 'Movimientos', 'Entradas', 'Salidas', 'Saldo neto', 'Costo total'];
                    $data = array_map(fn($r) => [
                        (string) $r['nombre_grupo'], (int) $r['cantidad_movimientos'],
                        (float) $r['total_entradas'], (float) $r['total_salidas'],
                        (float) $r['saldo_neto'], (float) $r['costo_total'],
                    ], $rows);
                }
                return [$headers, $data, 'Movimientos de Inventario'];

            case 'valorizacion':
                $filtros = $this->getFiltrosValorizacion();
                $modo = $filtros['agrupar_por'];
                $rows = match ($modo) {
                    'CATEGORIA' => $this->repository->getValorizacionAgrupadoCategoria($idEmpresa, $filtros),
                    'BODEGA'    => $this->repository->getValorizacionAgrupadoBodega($idEmpresa, $filtros),
                    'MARCA'     => $this->repository->getValorizacionAgrupadoMarca($idEmpresa, $filtros),
                    default     => $this->repository->getValorizacionAgrupadoProducto($idEmpresa, $filtros),
                };
                $headers = ['Grupo', 'Productos', 'Stock', 'Costo promedio', 'Valor total'];
                $data = array_map(fn($r) => [
                    (string) $r['nombre_grupo'], (int) $r['cantidad_productos'],
                    (float) $r['stock_actual'], (float) $r['costo_promedio'], (float) $r['valor_total'],
                ], $rows);
                return [$headers, $data, 'Valorización de Inventario'];

            case 'consignaciones':
                $filtros = $this->getFiltrosConsignaciones();
                $modo = $filtros['agrupar_por'];
                $rows = match ($modo) {
                    'CLIENTE'  => $this->repository->getConsignacionesAgrupadoCliente($idEmpresa, $filtros),
                    'PRODUCTO' => $this->repository->getConsignacionesAgrupadoProducto($idEmpresa, $filtros),
                    default    => $this->repository->getConsignacionesDetalle($idEmpresa, $filtros),
                };
                if ($modo === 'NINGUNO') {
                    $headers = ['Fecha', 'Secuencial', 'Cliente', 'Identificación', 'Producto', 'Bodega', 'Consignado', 'Saldo', 'Valor a costo'];
                    $data = array_map(fn($r) => [
                        date('d-m-Y', strtotime($r['fecha_emision'])), $r['secuencial'] ?? '',
                        $r['cliente_nombre'] ?? '', $r['cliente_identificacion'] ?? '',
                        $r['producto_nombre'] ?? '', $r['bodega_nombre'] ?? '',
                        (float) $r['cantidad_consignada'], (float) $r['saldo'], (float) $r['valor_saldo'],
                    ], $rows);
                } else {
                    $headers = ['Grupo', 'Consignaciones', 'Saldo', 'Valor a costo'];
                    $data = array_map(fn($r) => [
                        (string) $r['nombre_grupo'], (int) $r['cantidad_consignaciones'],
                        (float) $r['saldo'], (float) $r['valor_saldo'],
                    ], $rows);
                }
                return [$headers, $data, 'Consignaciones en Poder de Clientes'];

            default: // existencias
                $filtros = $this->getFiltrosExistencias();
                $modo = $filtros['agrupar_por'];
                $rows = match ($modo) {
                    'PRODUCTO'  => $this->repository->getExistenciasAgrupadoProducto($idEmpresa, $filtros),
                    'CATEGORIA' => $this->repository->getExistenciasAgrupadoCategoria($idEmpresa, $filtros),
                    'BODEGA'    => $this->repository->getExistenciasAgrupadoBodega($idEmpresa, $filtros),
                    default     => $this->repository->getExistenciasDetalle($idEmpresa, $filtros),
                };
                if ($modo === 'NINGUNO') {
                    $headers = ['Producto', 'Código', 'Categoría', 'Bodega', 'Stock', 'Mínimo', 'Máximo', 'Costo Unit.', 'Valor total', 'Estado'];
                    $data = array_map(fn($r) => [
                        $r['producto_nombre'] ?? '', $r['producto_codigo'] ?? '', $r['categoria_nombre'] ?? '', $r['bodega_nombre'] ?? '',
                        (float) $r['stock_actual'], (float) $r['stock_minimo'], (float) $r['stock_maximo'],
                        (float) $r['costo_unitario'], (float) $r['valor_total'], $r['estado_stock'] ?? '',
                    ], $rows);
                } else {
                    $headers = ['Grupo', 'Productos', 'Stock', 'Costo Unit.', 'Valor total'];
                    $data = array_map(fn($r) => [
                        (string) $r['nombre_grupo'], (int) $r['cantidad_productos'],
                        (float) $r['stock_actual'], (float) $r['costo_unitario'], (float) $r['valor_total'],
                    ], $rows);
                }
                return [$headers, $data, 'Existencias de Inventario'];
        }
    }
}
