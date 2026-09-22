<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\Helpers\IdentificacionTercero;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\ReporteVentasRepository;

class ReporteVentasController extends BaseModuloController
{
    private ReporteVentasRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_ventas';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteVentasRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        
        // Obtener tarifas IVA para el filtro
        $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
        $tarifasIva = $facturaRepo->getTarifasIva();

        // Obtener los años disponibles para el filtro
        $anios = $this->repository->getAniosDisponibles($idEmpresa);

        // Vendedores activos de la empresa para el selector del filtro. Restringido (§6):
        // el filtro queda fijo en su propio vendedor (o vacío si no es vendedor) y el
        // catálogo de asesores no se manda al HTML.
        $alcance      = $this->alcanceUsuario([$idEmpresa]);
        $vendedorFijo = \App\Helpers\AlcanceRegistros::restringe($alcance);
        $vendedores   = $vendedorFijo
            ? \App\Helpers\AlcanceRegistros::vendedorPropio($alcance, $idEmpresa, (int) $_SESSION['id_usuario'])
            : (new \App\repositories\modulos\VendedorRepository())->getVendedoresActivos($idEmpresa);

        // Consolidado por establecimientos (mismo selector que Cuentas por Cobrar/Pagar): solo
        // aparece si la empresa activa es la matriz del grupo RUC y el usuario tiene acceso a
        // al menos otro establecimiento (ver EmpresaRepository::getIdsConsolidadoDesdeMatriz).
        $empresaRepo      = new EmpresaRepository();
        $idsConsolidado   = $empresaRepo->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
        $establecimientos = $idsConsolidado ? $empresaRepo->getEtiquetasEstablecimiento($idsConsolidado) : [];

        // Marcas y categorías de la empresa activa para los selectores del filtro.
        $marcas     = (new \App\repositories\modulos\MarcaRepository())->getCombo($idEmpresa);
        $categorias = (new \App\repositories\modulos\CategoriaRepository())->getCombo($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/reporte_ventas/index', [
            'titulo'      => 'Reporte de Ventas',
            'perm'        => $this->getPermisos(),
            'vistaConfig' => $prefsVista,
            // Orden guardado por el usuario al hacer clic en las cabeceras (lo escribe el JS
            // con CMG_guardarVista). Si la columna no aplica a la agrupación elegida, la vista
            // la descarta y el repositorio usa el orden por defecto de ese modo.
            'ordenCol'    => (string) ($prefsVista['__ordenCol__'] ?? ''),
            'ordenDir'    => strtoupper((string) ($prefsVista['__ordenDir__'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
            'rutaModulo'  => $this->getRutaModulo(),
            'tarifasIva'  => $tarifasIva,
            'anios'       => $anios,
            'vendedores'  => $vendedores,
            'vendedorFijo' => $vendedorFijo,
            'marcas'      => $marcas,
            'categorias'  => $categorias,
            'puedeConsolidar'  => !empty($idsConsolidado),
            'establecimientos' => $establecimientos,
            'fullWidth'   => true,
            'base'        => BASE_URL
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        $agrupar = strtoupper(trim((string) ($_REQUEST['agrupar_por'] ?? 'NINGUNO')));
        return [
            'tipo_documento' => $_REQUEST['tipo_documento'] ?? 'FACTURA',
            'agrupar_por'    => isset(self::AGRUPACION_LBL[$agrupar]) ? $agrupar : 'NINGUNO',
            'fecha_desde'    => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'    => $_REQUEST['fecha_hasta'] ?? '',
            'id_cliente'     => $_REQUEST['id_cliente'] ?? '',
            'id_vendedor'    => (int)($_REQUEST['id_vendedor'] ?? 0),
            'id_producto'    => $_REQUEST['id_producto'] ?? '',
            // Marca y categoría del producto (selectores). En consolidado, resolverAlcance()
            // los expande a las homónimas de los demás establecimientos.
            'id_marca'       => (int)($_REQUEST['id_marca'] ?? 0),
            'id_categoria'   => (int)($_REQUEST['id_categoria'] ?? 0),
            'producto_texto' => trim($_REQUEST['producto_texto'] ?? ''),
            'variante_texto' => trim($_REQUEST['variante_texto'] ?? ''),
            'estado'         => $_REQUEST['estado'] ?? 'TODOS',
            'buscar_info'    => trim($_REQUEST['buscar_info'] ?? ''),
            // Orden de la tabla: columna (una de la lista blanca del repositorio, propia de
            // cada agrupación) y dirección. Viajan dentro del formulario de filtros, así que
            // el Excel y el PDF —que serializan ese mismo formulario— salen con el mismo orden
            // que la pantalla. Vacío = el orden por defecto de cada agrupación.
            'orden_col'      => trim((string) ($_REQUEST['orden_col'] ?? '')),
            'orden_dir'      => strtoupper(trim((string) ($_REQUEST['orden_dir'] ?? ''))) === 'ASC' ? 'ASC' : 'DESC',
            // ESTABLECIMIENTO (solo la empresa activa) | CONSOLIDADO (todo el grupo RUC;
            // solo se honra desde la matriz — ver resolverAlcance()).
            'alcance'        => strtoupper(trim((string) ($_REQUEST['alcance'] ?? ''))),
            // Selector "Borradores": EXCLUIR (por defecto, solo documentos válidos) | INCLUIR
            // (válidos + borradores) | SOLO (solo borradores). Lo aplica el repositorio (condEstado).
            'borradores'     => match (strtoupper(trim((string) ($_REQUEST['borradores'] ?? '')))) {
                'INCLUIR' => 'INCLUIR',
                'SOLO'    => 'SOLO',
                default   => 'EXCLUIR',
            },
            // El alcance del usuario (§6: `id_vendedor_filtro` / `id_usuario_filtro`)
            // lo agrega resolverAlcance(), que ya conoce las empresas del reporte.
        ];
    }

    /**
     * Alcance del usuario (§6, ver App\Helpers\AlcanceRegistros): los niveles 2
     * y 3 y quien tenga acceso total ('t') ven todo; en el nivel 1 sin acceso
     * total, el vendedor vinculado al usuario ve solo lo de su vendedor (lo que
     * lleva su nombre y, sin vendedor, lo de sus clientes) y, si no es vendedor,
     * solo lo que él registró. Se resuelve del nivel, el permiso y la sesión,
     * nunca de la petición. Al ir
     * en los filtros lo heredan el listado, las estadísticas, el resumen de
     * estados y las exportaciones, que parten del mismo arreglo.
     */
    private function alcanceUsuario(array $idsEmpresa): array
    {
        return \App\Helpers\AlcanceRegistros::resolver(
            $this->getPermisos(),
            (int) ($_SESSION['id_usuario'] ?? 0),
            $idsEmpresa
        );
    }

    /** Texto del selector "Borradores" para el encabezado del PDF/Excel ('' si es el por defecto). */
    private function describirBorradores(string $modo): string
    {
        return match ($modo) {
            'INCLUIR' => 'Incluye documentos en borrador',
            'SOLO'    => 'Solo documentos en borrador',
            default   => '',
        };
    }

    /**
     * Alcance del reporte. Devuelve [idsEmpresa, consolidado]. `alcance=CONSOLIDADO` solo se
     * honra si la empresa activa es la matriz del grupo RUC y hay hermanas accesibles para el
     * usuario; si no, se ignora en silencio. En consolidado, los filtros por id de cliente y
     * de producto se expanden a las filas hermanas (clientes y productos son tablas por
     * establecimiento: se cruzan por identificación y por código). El vendedor es por
     * establecimiento y no se expande.
     */
    private function resolverAlcance(int $idEmpresa, array &$filtros): array
    {
        $idsEmpresa  = [$idEmpresa];
        $consolidado = false;
        if (($filtros['alcance'] ?? '') === 'CONSOLIDADO') {
            $grupo = (new EmpresaRepository())->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
            if ($grupo) {
                $idsEmpresa  = $grupo;
                $consolidado = true;
            }
        }
        $filtros['alcance'] = $consolidado ? 'CONSOLIDADO' : 'ESTABLECIMIENTO';
        // Alcance del usuario (§6): se resuelve aquí porque en consolidado el vendedor
        // vinculado es uno por establecimiento. A un usuario restringido no se le aplica
        // el filtro Vendedor de la pantalla: su alcance ya lo limita a su vendedor.
        $filtros = \App\Helpers\AlcanceRegistros::limpiarFiltroVendedor(
            array_merge($filtros, $this->alcanceUsuario($idsEmpresa))
        );
        if ($consolidado) {
            if (!empty($filtros['id_cliente'])) {
                $raw = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string) $filtros['id_cliente']);
                $filtros['id_cliente'] = (new \App\repositories\modulos\CuentasPorCobrarRepository())
                    ->expandirClientesPorIdentificacion($raw, $idsEmpresa);
            }
            if (!empty($filtros['id_producto'])) {
                $raw = is_array($filtros['id_producto']) ? $filtros['id_producto'] : explode(',', (string) $filtros['id_producto']);
                $filtros['id_producto'] = $this->repository->expandirProductosPorCodigo($raw, $idsEmpresa);
            }
            // Marcas y categorías son por establecimiento: se cruzan por nombre.
            if (!empty($filtros['id_marca'])) {
                $filtros['id_marca'] = $this->repository->expandirCatalogoPorNombre('marcas', (int) $filtros['id_marca'], $idsEmpresa);
            }
            if (!empty($filtros['id_categoria'])) {
                $filtros['id_categoria'] = $this->repository->expandirCatalogoPorNombre('categorias', (int) $filtros['id_categoria'], $idsEmpresa);
            }
        }
        return [$idsEmpresa, $consolidado];
    }

    /**
     * Filas del reporte según la agrupación elegida. Devuelve [filas, meses]: `meses` solo
     * trae valor en "Unidades por producto y mes", cuyas columnas dependen del período.
     * Único punto de despacho para la pantalla, el Excel y el PDF.
     */
    private function consultarFilas(int|array $idEmpresa, array $filtros): array
    {
        switch ((string) ($filtros['agrupar_por'] ?? 'NINGUNO')) {
            case 'CLIENTE':
                return [$this->repository->getReporteAgrupadoCliente($idEmpresa, $filtros), []];
            case 'PRODUCTO':
                return [$this->repository->getReporteAgrupadoProducto($idEmpresa, $filtros), []];
            case 'VARIANTE':
                return [$this->repository->getReporteAgrupadoVariante($idEmpresa, $filtros), []];
            case 'FECHA':
                return [$this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros), []];
            case 'MES':
                return [$this->repository->getReporteAgrupadoMes($idEmpresa, $filtros), []];
            case 'PRODUCTO_MES':
                $r = $this->repository->getReporteUnidadesProductoMes($idEmpresa, $filtros);
                return [$r['rows'], $r['meses']];
            default:
                return [$this->repository->getReporteDetallado($idEmpresa, $filtros), []];
        }
    }

    /** Número de columnas de la tabla de la pantalla en cada agrupación (para el colspan). */
    private static function numColumnas(string $agrupar, array $meses): int
    {
        return match ($agrupar) {
            'NINGUNO'      => 12,
            'PRODUCTO'     => 7,
            'VARIANTE'     => 8,
            'PRODUCTO_MES' => 3 + count($meses),
            default        => 6,
        };
    }

    /** Cantidad de unidades: sin decimales si es entera, con dos si no. */
    private static function fmtCantidad(float $n): string
    {
        return number_format($n, abs($n - round($n)) < 0.00001 ? 0 : 2);
    }

    /** 'YYYY-MM' → 'Ene 2026' (cabeceras de las columnas de mes). */
    private static function formatearMesCorto(string $mes): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $mes, $m)) {
            return $mes;
        }
        $cortos = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun',
                   '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
        return ($cortos[$m[2]] ?? $m[2]) . ' ' . $m[1];
    }

    /** Suma de una columna de mes (o del total) sobre las filas de unidades por producto y mes. */
    private static function sumarUnidades(array $rows, ?string $mes): float
    {
        return array_sum(array_map(
            static fn (array $r): float => (float) ($mes === null ? ($r['total_unidades'] ?? 0) : ($r['meses'][$mes] ?? 0)),
            $rows
        ));
    }

    /** Fila de totales al pie de la tabla "Unidades por producto y mes" (pantalla). */
    private function renderFilaTotalesUnidadesHtml(array $rows, array $meses): string
    {
        $n    = count($rows);
        $html = "<tr class='table-light fw-bold'><td class='ps-4' colspan='2'>TOTAL ({$n} producto" . ($n !== 1 ? 's' : '') . ")</td>";
        foreach ($meses as $m) {
            $html .= "<td class='text-end'>" . self::fmtCantidad(self::sumarUnidades($rows, $m)) . "</td>";
        }
        $html .= "<td class='text-end pe-4 text-primary'>" . self::fmtCantidad(self::sumarUnidades($rows, null)) . "</td></tr>";
        return $html;
    }

    /** Texto del alcance para el encabezado del PDF ('' si no es consolidado). */
    private function describirAlcance(array $idsEmpresa, bool $consolidado): string
    {
        if (!$consolidado) {
            return '';
        }
        $etq = (new EmpresaRepository())->getEtiquetasEstablecimiento($idsEmpresa);
        return 'Consolidado por RUC (' . count($etq) . ' establecimientos: ' . implode(' · ', $etq) . ')';
    }

    public function generarAjax(): void
    {
        error_log("GENERARAjax INICIADO " . json_encode($_REQUEST));
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();
            [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
            $idEmpresa = $idsEmpresa; // alcance del reporte (una empresa o el grupo RUC)

            // Consultar datos
            [$rows, $meses] = $this->consultarFilas($idEmpresa, $filtros);

            // Consultar estadísticas globales (solo afectan a las facturas, sin agrupar por detalle)
            $stats = $this->repository->getEstadisticas($idEmpresa, $filtros);

            // Resumen de estados
            $resumenEstados = $this->repository->getResumenEstados($idEmpresa, $filtros);

            // Generar HTML de las filas según la agrupación
            ob_start();
            if (empty($rows)) {
                $colSpan = self::numColumnas((string) $filtros['agrupar_por'], $meses);
                $mensajeVacio = 'No se encontraron resultados.';
                if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
                    $desde = date('d-m-Y', strtotime($filtros['fecha_desde']));
                    $hasta = date('d-m-Y', strtotime($filtros['fecha_hasta']));
                    $mensajeVacio = "No se encontraron resultados en el periodo del {$desde} al {$hasta}.";
                }
                echo '<tr><td colspan="'.$colSpan.'" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>'.htmlspecialchars($mensajeVacio).'</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaAgrupadaHtml($r, $filtros['agrupar_por'], $filtros['tipo_documento'] ?? 'FACTURA', $consolidado, $meses);
                }
                if ($filtros['agrupar_por'] === 'PRODUCTO_MES') {
                    echo $this->renderFilaTotalesUnidadesHtml($rows, $meses);
                }
            }
            $rowsHtml = ob_get_clean();

            $jsonOutput = json_encode([
                'ok'          => true,
                'rows'        => $rowsHtml,
                'rawData'     => $rows,
                'stats'       => $stats,
                'estados'     => $resumenEstados,
                'agrupacion'  => $filtros['agrupar_por'],
                // Columnas de mes de "Unidades por producto y mes" ('YYYY-MM' => 'Ene 2026');
                // el JS arma la cabecera con ellas. Vacío en las demás agrupaciones.
                'meses'       => array_combine($meses, array_map([self::class, 'formatearMesCorto'], $meses)) ?: new \stdClass(),
                'consolidado' => $consolidado,
                'borradores'  => $filtros['borradores'],
            ]);
            
            if ($jsonOutput === false) {
                error_log("ReporteVentas JSON Encode Error: " . json_last_error_msg());
                echo json_encode(['ok' => false, 'error' => 'Error de codificación de datos.']);
            } else {
                echo $jsonOutput;
            }

        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            error_log("ReporteVentas Exception: " . $e->getMessage() . " on line " . $e->getLine());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaAgrupadaHtml(array $r, string $agruparPor, string $tipoDocumento = 'FACTURA', bool $consolidado = false, array $meses = []): string
    {
        // Consolidado por RUC: badge con el establecimiento dueño del documento (solo en el
        // detallado; las agrupaciones suman todos los establecimientos en una fila).
        $badgeEst = '';
        if ($consolidado && !empty($r['establecimiento'])) {
            $badgeEst = "<span class='badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1 fw-normal' style='font-size:.65rem;'>"
                      . htmlspecialchars((string) $r['establecimiento']) . "</span>";
        }

        // Solo el modo detallado corresponde a un documento real: se marca la fila
        // para poder abrir el panel lateral con su detalle (ver offcanvas_doc_preview).
        $attrs = '';
        if (!in_array($agruparPor, ['CLIENTE', 'PRODUCTO', 'VARIANTE', 'FECHA', 'MES', 'PRODUCTO_MES'], true) && !empty($r['id'])) {
            // En el neto (Facturas − NC) cada fila trae su propio tipo; si no, deriva del filtro.
            $tipoDoc = $r['_doc_tipo'] ?? match ($tipoDocumento) {
                'RECIBO'       => 'RECIBO',
                'NOTA_CREDITO' => 'NOTA_CREDITO',
                default        => 'FACTURA',
            };
            $attrs = ' style="cursor:pointer;" title="Clic para ver el detalle"'
                   . ' data-doc-id="' . (int)$r['id'] . '"'
                   . ' data-doc-tipo="' . $tipoDoc . '"'
                   . ' data-doc-numero="' . htmlspecialchars($r['numero_factura'] ?? '', ENT_QUOTES) . '"'
                   . ' data-doc-sujeto="' . htmlspecialchars($r['cliente_nombre'] ?? '', ENT_QUOTES) . '"';
        }

        $html = '<tr class="align-middle"' . $attrs . '>';

        $base0   = number_format((float)($r['base_0'] ?? 0), 2);
        $baseIva = number_format((float)($r['base_iva'] ?? 0), 2);
        $iva     = number_format((float)($r['valor_iva'] ?? 0), 2);
        $total   = number_format((float)($r['total'] ?? 0), 2);

        if ($agruparPor === 'CLIENTE') {
            // Saldo por cobrar de los documentos del reporte (reemplaza a "Nro Facturas";
            // el conteo queda como título del nombre). En rojo mientras el cliente deba.
            $nDocs   = (int) ($r['cantidad_facturas'] ?? 0);
            $saldo   = (float) ($r['saldo'] ?? 0);
            $clsSal  = $saldo > 0.001 ? 'text-danger fw-semibold' : 'text-muted';
            $titulo  = $nDocs . ' documento' . ($nDocs !== 1 ? 's' : '') . ' en el reporte';
            $html .= "<td title='".htmlspecialchars($titulo)."'><span class='fw-bold'>".htmlspecialchars($r['cliente_nombre'] ?? '')."</span><br><small class='text-muted'>".htmlspecialchars($r['cliente_ruc'] ?? '')."</small></td>";
            $html .= "<td class='text-end {$clsSal}'>".number_format($saldo, 2)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'PRODUCTO') {
            $tarifa = (float)($r['tarifa_iva'] ?? 0);
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['producto_nombre'] ?? '')."</span><br><small class='text-muted'>".htmlspecialchars($r['producto_codigo'] ?? '')."</small></td>";
            $html .= "<td class='text-center'>".(float)($r['cantidad_vendida'] ?? 0)."</td>";
            $html .= "<td class='text-center'>{$tarifa}%</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'VARIANTE') {
            $tarifa = (float)($r['tarifa_iva'] ?? 0);
            $html .= "<td>".htmlspecialchars($r['producto_nombre'] ?? '')."</td>";
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['variante_nombre'] ?? '')."</span>: ".htmlspecialchars($r['variante_valor'] ?? '')."</td>";
            $html .= "<td class='text-center'>".(float)($r['cantidad_vendida'] ?? 0)."</td>";
            $html .= "<td class='text-center'>{$tarifa}%</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'FECHA') {
            $html .= "<td><span class='fw-bold'>".date('d/m/Y', strtotime($r['fecha'] ?? ''))."</span></td>";
            $html .= "<td class='text-center'>".(int)($r['cantidad_facturas'] ?? 0)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'MES') {
            $html .= "<td><span class='fw-bold'>".self::formatearMes($r['mes'] ?? '')."</span></td>";
            $html .= "<td class='text-center'>".(int)($r['cantidad_facturas'] ?? 0)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'PRODUCTO_MES') {
            // Unidades por producto y mes: código, descripción, una celda por mes del
            // período (en gris cuando no hubo ventas) y el total del período.
            $html .= "<td class='ps-4'><span class='fw-bold'>".htmlspecialchars($r['producto_codigo'] ?? '')."</span></td>";
            $html .= "<td>".htmlspecialchars($r['producto_nombre'] ?? '')."</td>";
            foreach ($meses as $m) {
                $c = (float) ($r['meses'][$m] ?? 0);
                $html .= abs($c) < 0.000001
                    ? "<td class='text-end text-muted'>0</td>"
                    : "<td class='text-end'>".self::fmtCantidad($c)."</td>";
            }
            $html .= "<td class='text-end pe-4 fw-bold text-primary'>".self::fmtCantidad((float) ($r['total_unidades'] ?? 0))."</td>";
        } else {
            // DETALLADO / NINGUNO
            $estado = strtolower($r['estado'] ?? '');
            $badgeColor = match($estado) {
                'autorizado', 'autorizada' => 'bg-success bg-opacity-10 text-success border-success',
                'borrador' => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                'anulado' => 'bg-danger bg-opacity-10 text-danger border-danger',
                default => 'bg-primary bg-opacity-10 text-primary border-primary'
            };
            $retenciones = number_format((float)($r['retenciones'] ?? 0), 2);

            $html .= "<td class='text-center'>".date('d/m/Y', strtotime($r['fecha_emision'] ?? ''))."</td>";
            $html .= "<td>{$badgeEst}<span class='fw-bold'>".htmlspecialchars($r['numero_factura'] ?? '')."</span></td>";
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['cliente_nombre'] ?? '')."</span><br><small class='text-muted'>".htmlspecialchars($r['cliente_ruc'] ?? '')."</small></td>";
            $html .= "<td class='text-center'><span class='badge border {$badgeColor}'>".strtoupper($estado)."</span></td>";
            $html .= "<td>".htmlspecialchars($r['vendedor_nombre'] ?? '')."</td>";
            $html .= "<td>".htmlspecialchars($r['cajero_nombre']   ?? '')."</td>";
            $html .= "<td>".htmlspecialchars($r['usuario_nombre']  ?? '')."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
            $html .= "<td class='text-end text-danger'>$retenciones</td>";
        }
        
        $html .= '</tr>';
        return $html;
    }

    /**
     * Convierte 'YYYY-MM' a un nombre legible: 'Enero 2026'.
     */
    private static function formatearMes(string $mes): string
    {
        if ($mes === '' || strpos($mes, '-') === false) {
            return htmlspecialchars($mes);
        }
        [$anio, $num] = explode('-', $mes);
        $nombres = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];
        $nombre = $nombres[str_pad($num, 2, '0', STR_PAD_LEFT)] ?? $num;
        return $nombre . ' ' . $anio;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        // Solo productos de tipo 'venta'
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    /** Autocompletado de ítems del documento (facturas o recibos). */
    public function buscarItemsAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q    = trim($_GET['q'] ?? '');
        $tipo = $_GET['tipo_documento'] ?? 'FACTURA';
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarItems($idEmpresa, $q, $tipo, 15, $this->alcanceUsuario([$idEmpresa]))]);
        exit;
    }

    /** Autocompletado de info adicional (nombre/valor del documento). */
    public function buscarInfoAdicionalAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q    = trim($_GET['q'] ?? '');
        $tipo = $_GET['tipo_documento'] ?? 'FACTURA';
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarInfoAdicional($idEmpresa, $q, $tipo, 15, $this->alcanceUsuario([$idEmpresa]))]);
        exit;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
        $idEmpresaActiva = $idEmpresa;
        $idEmpresa       = $idsEmpresa; // alcance del reporte (una empresa o el grupo RUC)

        // Consultar datos
        [$rows, $meses] = $this->consultarFilas($idEmpresa, $filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresaActiva);
            $nombreEmpresa = $empresa['nombre'] ?? '';
            if ($consolidado) {
                $nombreEmpresa .= ' — ' . $this->describirAlcance($idsEmpresa, true);
            }

            if ($filtros['agrupar_por'] === 'CLIENTE') {
                // Misma estructura que la pantalla: el saldo por cobrar ocupa el lugar
                // que tenía "Nro Facturas".
                $headers = ['RUC/Cédula', 'Cliente', 'Saldo x Cobrar', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        $r['cliente_ruc'],
                        $r['cliente_nombre'],
                        round((float)($r['saldo'] ?? 0), 2),
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
                $headers = ['Código', 'Producto', 'Cant. Vendida', 'Tipo IVA', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        $r['producto_codigo'],
                        $r['producto_nombre'],
                        (float)$r['cantidad_vendida'],
                        $r['tarifa_iva'] . '%',
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'VARIANTE') {
                $headers = ['Producto', 'Variante', 'Valor', 'Cant. Vendida', 'Tipo IVA', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        $r['producto_nombre'],
                        $r['variante_nombre'],
                        $r['variante_valor'],
                        (float)$r['cantidad_vendida'],
                        $r['tarifa_iva'] . '%',
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'FECHA') {
                $headers = ['Fecha', 'Nro Facturas', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        date('d/m/Y', strtotime($r['fecha'])),
                        $r['cantidad_facturas'],
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'MES') {
                $headers = ['Mes', 'Nro Facturas', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        self::formatearMes($r['mes'] ?? ''),
                        $r['cantidad_facturas'],
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'PRODUCTO_MES') {
                // Una columna por mes del período + total, y la fila de totales al final.
                $headers = array_merge(['Código', 'Descripción'], array_map([self::class, 'formatearMesCorto'], $meses), ['Total']);
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = array_merge(
                        [$r['producto_codigo'], $r['producto_nombre']],
                        array_map(static fn (string $m): float => (float) ($r['meses'][$m] ?? 0), $meses),
                        [(float) $r['total_unidades']]
                    );
                }
                if ($rows) {
                    $exportData[] = array_merge(
                        ['TOTAL', count($rows) . ' producto' . (count($rows) !== 1 ? 's' : '')],
                        array_map(static fn (string $m): float => self::sumarUnidades($rows, $m), $meses),
                        [self::sumarUnidades($rows, null)]
                    );
                }
            } else {
                // Consolidado: columna "Estab." al inicio con el establecimiento dueño del documento.
                // Con borradores: columna "Estado" para distinguirlos de los documentos válidos.
                $conEstado = $filtros['borradores'] !== 'EXCLUIR';
                $headers = array_merge($consolidado ? ['Estab.'] : [], ['Fecha', 'Factura', 'Cliente', 'RUC/Cédula'], $conEstado ? ['Estado'] : [], ['Vendedor', 'Cajero', 'Usuario', 'Clave Acceso', 'Base 0%', 'Base IVA', 'IVA', 'Total', 'Retenciones']);
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = array_merge($consolidado ? [(string) ($r['establecimiento'] ?? '')] : [], [
                        date('d/m/Y', strtotime($r['fecha_emision'])),
                        $r['numero_factura'],
                        $r['cliente_nombre'],
                        $r['cliente_ruc'],
                    ], $conEstado ? [strtoupper((string) ($r['estado'] ?? ''))] : [], [
                        $r['vendedor_nombre'] ?? '',
                        $r['cajero_nombre']   ?? '',
                        $r['usuario_nombre']  ?? '',
                        $r['clave_acceso']    ?? '',
                        (float)($r['base_0']    ?? 0),
                        (float)($r['base_iva']  ?? 0),
                        (float)($r['valor_iva'] ?? 0),
                        (float)($r['total']     ?? 0),
                        (float)($r['retenciones'] ?? 0),
                    ]);
                }
            }

            $borradoresTxt = $this->describirBorradores($filtros['borradores']);
            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Ventas', $headers, $exportData, 'Reporte_Ventas', $nombreEmpresa,
                $borradoresTxt !== '' ? ['Estados' => $borradoresTxt] : []);
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
        }
    }

    /**
     * PDF del reporte. Una sola definición de columnas (columnasPdf()) alimenta el <thead>,
     * el cuerpo y la fila de totales: así el `width:%` sale repetido en el <th> Y en cada
     * <td>, que es lo único que hace que Html2Pdf respete el ancho — con el ancho solo en
     * el <th> ensancha la columna hasta que el texto quepa en una línea y la tabla se sale
     * de la hoja (ver docs/manual/modulos/reporte-ventas.md, "Exportar" › "Qué trae el PDF").
     * El detallado sale horizontal (sus doce columnas —trece en consolidado— no caben en A4
     * retrato); los agrupados, retrato con la columna descriptiva lo más ancha posible.
     */
    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
        $idEmpresaActiva = $idEmpresa;
        $idEmpresa       = $idsEmpresa; // alcance del reporte (una empresa o el grupo RUC)
        $agrupar         = (string) $filtros['agrupar_por'];

        // Consultar datos
        [$rows, $meses] = $this->consultarFilas($idEmpresa, $filtros);

        $totales = $this->repository->getEstadisticas($idEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresaActiva) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE VENTAS';
            $filtrosTxt    = $this->describirFiltros($idsEmpresa, $filtros, $consolidado);

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $html      = $this->htmlPdf($agrupar, $consolidado, $rows, $totales, $filtrosTxt, $idEmpresaActiva, $nombreEmpresa, $meses);
            $html2pdf  = new \Spipu\Html2Pdf\Html2Pdf(self::pdfHorizontal($agrupar, $meses) ? 'L' : 'P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteVentas_' . ucfirst(strtolower($agrupar)) . '_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
        }
    }

    /**
     * HTML del PDF (encabezado + filtros + indicadores + listado + totales). Separado de
     * exportPdf() para poder generarlo —y medirlo— con cualquier juego de filas.
     */
    private function htmlPdf(string $agrupar, bool $consolidado, array $rows, array $totales,
                             array $filtrosTxt, int $idEmpresaActiva, string $nombreEmpresa, array $meses = []): string
    {
        $cols      = $this->columnasPdf($agrupar, $consolidado, $meses);
        $nCols     = count($cols);
        $fs        = self::pdfFuentePt($agrupar, $meses) . 'pt';

        // Cuerpo: los mismos anchos que el <thead>, celda por celda (obligatorio en
        // Html2Pdf). Filas alternas en gris muy claro: no admite :nth-child, así que el
        // fondo se escribe en el style de cada <td>.
        $cuerpo = '';
        $i = 0;
        foreach ($rows as $r) {
            $zebra = (++$i % 2 === 0) ? 'background:#f6f8fa;' : '';
            $cuerpo .= '<tr>';
            foreach ($cols as $c) {
                $cls = $c['cls'] !== '' ? " class='{$c['cls']}'" : '';
                $cuerpo .= "<td{$cls} style='width:{$c['w']}%;{$zebra}'>" . ($c['val'])($r) . '</td>';
            }
            $cuerpo .= '</tr>';
        }
        if ($cuerpo === '') {
            $cuerpo = "<tr><td colspan='{$nCols}' class='text-center' style='width:100%;padding:8px;'>"
                    . 'Sin resultados para los filtros aplicados.</td></tr>';
        }

        $encabezados = '';
        foreach ($cols as $c) {
            $encabezados .= "<th style='width:{$c['w']}%;'>" . htmlspecialchars($c['lbl']) . '</th>';
        }

        $etiquetaTot = 'TOTALES GENERALES (' . count($rows) . ' ' . $this->sustantivoFilas($agrupar, count($rows)) . ')';
        $filaTotales = $this->filaTotalesPdf($cols, $totales, $rows, $etiquetaTot);
        $titulo      = 'Reporte de Ventas · ' . (self::AGRUPACION_LBL[$agrupar] ?? 'Detallado');

        ob_start();
        ?>
        <style>
            body { font-family: Arial, sans-serif; color: #000; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 0 6px 0; }
            th { background: #e3e9f0; border: 1px solid #9aa7b4; padding: 3px; text-align: center;
                 font-size: <?= $fs ?>; font-weight: bold; color: #1b2a3a; }
            td { border: 1px solid #c3ccd6; padding: 2px 3px; font-size: <?= $fs ?>; word-wrap: break-word; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .sub { font-size: 6.5pt; color: #6a747e; }
            .header { text-align: center; }
            .header h2 { margin: 0 0 1px 0; font-size: 13pt; color: #1b2a3a; }
            .header h3 { margin: 0 0 1px 0; font-size: 10pt; color: #2c4a6b; }
            .header p  { margin: 0; font-size: 7.5pt; color: #555; }
            <?= self::CSS_FILTROS_PDF ?>
            table.kpis { margin-bottom: 8px; }
            table.kpis td { border: 1px solid #c3ccd6; background: #f4f7fa; padding: 4px 3px; text-align: center; }
            .k-lbl { font-size: 6.5pt; color: #55606b; }
            .k-val { font-size: 10pt; font-weight: bold; color: #1b2a3a; }
            .k-tot { color: #146c43; }
            table.tot { margin-top: 0; }
            table.tot td { border: 1px solid #9aa7b4; background: #e3e9f0; font-weight: bold;
                           font-size: <?= $fs ?>; padding: 3px; color: #1b2a3a; }
        </style>
        <?php // Los back* se SUMAN a los márgenes por defecto de Html2Pdf (5,5,5,8 mm): con
              // 3 mm a los lados la hoja queda con 8 mm reales y el listado usa 194 mm de
              // ancho útil (281 mm en horizontal), que es sobre lo que están calculados los
              // % de columnasPdf(). El pie "Página x/y" se dibuja a 11 mm del borde. ?>
        <page backtop="7mm" backbottom="7mm" backleft="3mm" backright="3mm" footer="page">
        <?= $this->encabezadoPdf($idEmpresaActiva, $nombreEmpresa, $titulo) ?>
        <?= $this->bloqueFiltrosPdf($filtrosTxt) ?>
        <?= $this->bloqueTotalesPdf($totales) ?>
        <table>
            <thead><tr><?= $encabezados ?></tr></thead>
            <tbody><?= $cuerpo ?></tbody>
        </table>
        <?php // El total va en su propia tabla y NO en un <tfoot>: Html2Pdf repite el tfoot
              // al pie de TODAS las páginas, siempre con la cifra global, como si fuera el
              // total de esa hoja. ?>
        <table class="tot"><?= $filaTotales ?></table>
        </page>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Ancho de cada carácter en Helvetica/Arial, en milésimas de em: es la fuente core con la
     * que Html2Pdf escribe estos PDF, y la usa columnasPdf() para saber cuándo una palabra no
     * cabe en su columna. Lo que no está en la tabla (dígitos, acentuadas ya normalizadas,
     * símbolos raros) vale 556, el ancho del dígito.
     */
    private const ANCHO_HELVETICA = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
        '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333,
        '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722,
        'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667,
        'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
        'Y' => 667, 'Z' => 611,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556,
        'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556,
        'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500,
        'y' => 500, 'z' => 500,
    ];

    /** Letras acentuadas: miden lo mismo que su letra base. */
    private const SIN_TILDE = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','À'=>'A','È'=>'E','Ì'=>'I','Ò'=>'O','Ù'=>'U',
    ];

    /** Ancho de un texto en em (1 em = el tamaño de la fuente en puntos). */
    private static function anchoEm(string $texto): float
    {
        $ancho = 0.0;
        foreach (mb_str_split(strtr($texto, self::SIN_TILDE)) as $ch) {
            $ancho += (self::ANCHO_HELVETICA[$ch] ?? 556) / 1000;
        }
        return $ancho;
    }

    /** Nombre legible de cada agrupación (título del PDF y caja de filtros). También es la lista blanca de `agrupar_por`. */
    private const AGRUPACION_LBL = [
        'NINGUNO'      => 'Detallado',
        'CLIENTE'      => 'Por cliente',
        'PRODUCTO'     => 'Por producto',
        'VARIANTE'     => 'Por variante',
        'FECHA'        => 'Por fecha',
        'MES'          => 'Por mes',
        'PRODUCTO_MES' => 'Unidades por producto y mes',
    ];

    /** Qué cuenta cada fila del listado, para la etiqueta "TOTALES GENERALES (N …)". */
    private function sustantivoFilas(string $agrupar, int $n): string
    {
        return match ($agrupar) {
            'CLIENTE'  => $n === 1 ? 'cliente'   : 'clientes',
            'PRODUCTO', 'PRODUCTO_MES' => $n === 1 ? 'producto' : 'productos',
            'VARIANTE' => $n === 1 ? 'variante'  : 'variantes',
            'FECHA'    => $n === 1 ? 'día'       : 'días',
            'MES'      => $n === 1 ? 'mes'       : 'meses',
            default    => $n === 1 ? 'documento' : 'documentos',
        };
    }

    /**
     * Orientación de la hoja: horizontal para el detallado (doce columnas) y para
     * "Unidades por producto y mes" cuando el período pasa de seis meses.
     */
    private static function pdfHorizontal(string $agrupar, array $meses): bool
    {
        return $agrupar === 'NINGUNO' || ($agrupar === 'PRODUCTO_MES' && count($meses) > 6);
    }

    /**
     * Tamaño de letra del listado del PDF, en puntos: 8 en los agrupados, 7 cuando la hoja
     * va horizontal y 6 en unidades por producto y mes con más de un año de columnas.
     */
    private static function pdfFuentePt(string $agrupar, array $meses): float
    {
        if ($agrupar === 'PRODUCTO_MES' && count($meses) > 12) {
            return 6.0;
        }
        return self::pdfHorizontal($agrupar, $meses) ? 7.0 : 8.0;
    }

    /**
     * Columnas del PDF según la agrupación. Cada una declara su ancho en % (la suma es 100),
     * la alineación, cómo se pinta la celda y —si corresponde— de dónde sale su total: el
     * nombre de una clave de getEstadisticas() o un callable que suma las filas del listado.
     *
     * Los anchos están calculados sobre el ancho útil de la hoja (A4 retrato con márgenes de
     * 8 mm ≈ 550 pt; horizontal ≈ 797 pt) para que ningún valor quede cortado: la columna
     * descriptiva (producto, cliente) se lleva todo lo que sobra tras dar a cada importe lo
     * justo para su cifra más larga.
     */
    private function columnasPdf(string $agrupar, bool $consolidado, array $meses = []): array
    {
        $e    = static fn ($v): string => htmlspecialchars((string) $v);
        $num  = static fn ($v): string => number_format((float) $v, 2);

        // Ancho útil de la hoja y tamaños de letra de este PDF (ver htmlPdf()). El ancho en
        // puntos de una columna es su % menos el padding y los bordes de la celda.
        $util      = self::pdfHorizontal($agrupar, $meses) ? 796.5 : 549.9;   // A4 horizontal / retrato con 8 mm de margen
        $ptFila    = self::pdfFuentePt($agrupar, $meses);                     // font-size del <td>
        $ptSub     = 6.5;                                                     // font-size del subtexto (.sub)
        $pt        = static fn (int $w): float => $w / 100 * $util - 6.0;

        // Html2Pdf reparte el texto en líneas por los espacios, pero NO parte una "palabra"
        // más larga que su columna: esa desborda sobre la vecina. Se cortan a mano solo esas,
        // midiendo el ancho REAL en la fuente (un nombre de 45 letras mayúsculas ocupa un
        // 30 % más que 45 dígitos, así que contar caracteres no sirve) y por carácter, no por
        // byte: cortar a media secuencia UTF-8 rompe la tilde.
        $wrap = static function (string $t, float $anchoPt, float $pts): string {
            $palabras = preg_split('/\s+/', $t) ?: [];
            foreach ($palabras as $i => $p) {
                if (self::anchoEm($p) * $pts <= $anchoPt) {
                    continue;
                }
                $trozos = [''];
                $acum   = 0.0;
                foreach (mb_str_split($p) as $ch) {
                    $a = self::anchoEm($ch) * $pts;
                    if ($acum + $a > $anchoPt && $acum > 0.0) {
                        $trozos[] = '';
                        $acum     = 0.0;
                    }
                    $trozos[count($trozos) - 1] .= $ch;
                    $acum += $a;
                }
                $palabras[$i] = implode("\n", $trozos);
            }
            return nl2br(htmlspecialchars(implode(' ', $palabras)));
        };
        // Celda descriptiva: título en negrita y, debajo y en gris, el dato secundario
        // (código del producto, RUC del cliente), igual que en la tabla de la pantalla.
        $desc = static function (string $titulo, string $sub, float $anchoPt) use ($wrap, $ptFila, $ptSub): string {
            $html = "<span style='font-weight:bold;'>" . $wrap($titulo !== '' ? $titulo : '-', $anchoPt, $ptFila) . '</span>';
            if (trim($sub) !== '') {
                $html .= "<br><span class='sub'>" . $wrap($sub, $anchoPt, $ptSub) . '</span>';
            }
            return $html;
        };
        $sumar = static fn (string $campo): callable
            => static fn (array $rows): float => array_sum(array_map(static fn ($r) => (float) ($r[$campo] ?? 0), $rows));

        // Columnas de importes, comunes a todas las agrupaciones (van al final de la fila).
        $importes = static fn (int $w0, int $wIva, int $wTot): array => [
            ['lbl' => 'Base 0%',  'w' => $w0,   'cls' => 'text-end', 'tot' => 'total_base_0',
             'val' => static fn (array $r): string => $num($r['base_0'] ?? 0)],
            ['lbl' => 'Base IVA', 'w' => $w0,   'cls' => 'text-end', 'tot' => 'total_base_iva',
             'val' => static fn (array $r): string => $num($r['base_iva'] ?? 0)],
            ['lbl' => 'IVA',      'w' => $wIva, 'cls' => 'text-end', 'tot' => 'total_iva',
             'val' => static fn (array $r): string => $num($r['valor_iva'] ?? 0)],
            ['lbl' => 'Total',    'w' => $wTot, 'cls' => 'text-end', 'tot' => 'gran_total',
             'val' => static fn (array $r): string => "<span style='font-weight:bold;'>" . $num($r['total'] ?? 0) . '</span>'],
        ];

        if ($agrupar === 'PRODUCTO') {
            // 42 % para el nombre del producto: es la columna que el usuario lee.
            return array_merge([
                ['lbl' => 'Producto', 'w' => 42, 'cls' => '',
                 'val' => static fn (array $r): string => $desc((string) ($r['producto_nombre'] ?? ''), (string) ($r['producto_codigo'] ?? ''), $pt(42))],
                ['lbl' => 'Cantidad', 'w' => 9, 'cls' => 'text-end',
                 'val' => static fn (array $r): string => $num($r['cantidad_vendida'] ?? 0)],
                ['lbl' => 'T.IVA', 'w' => 7, 'cls' => 'text-center',
                 'val' => static fn (array $r): string => (float) ($r['tarifa_iva'] ?? 0) . '%'],
            ], $importes(10, 10, 12));
        }

        if ($agrupar === 'PRODUCTO_MES') {
            // Código y total fijos; los meses se reparten lo que queda hasta dejar a la
            // descripción al menos un 22 %, y cada mes se acota entre 4 % y 9 %.
            $n     = count($meses);
            $wCod  = 11;
            $wTot  = 8;
            $wMes  = $n > 0 ? max(4, min(9, (int) floor((100 - $wCod - $wTot - 22) / $n))) : 0;
            $wDesc = 100 - $wCod - $wTot - $wMes * $n;
            $cant  = static fn ($v): string => self::fmtCantidad((float) $v);
            $cols  = [
                ['lbl' => 'Código', 'w' => $wCod, 'cls' => '',
                 'val' => static fn (array $r): string => $wrap((string) ($r['producto_codigo'] ?? ''), $pt($wCod), $ptFila)],
                ['lbl' => 'Descripción', 'w' => $wDesc, 'cls' => '',
                 'val' => static fn (array $r): string => $wrap((string) ($r['producto_nombre'] ?? ''), $pt($wDesc), $ptFila)],
            ];
            foreach ($meses as $m) {
                $cols[] = ['lbl' => self::formatearMesCorto($m), 'w' => $wMes, 'cls' => 'text-end', 'fmt' => $cant,
                           'tot' => static fn (array $rows): float => self::sumarUnidades($rows, $m),
                           'val' => static fn (array $r): string => $cant($r['meses'][$m] ?? 0)];
            }
            $cols[] = ['lbl' => 'Total', 'w' => $wTot, 'cls' => 'text-end', 'fmt' => $cant,
                       'tot' => static fn (array $rows): float => self::sumarUnidades($rows, null),
                       'val' => static fn (array $r): string => "<span style='font-weight:bold;'>" . $cant($r['total_unidades'] ?? 0) . '</span>'];
            return $cols;
        }

        if ($agrupar === 'VARIANTE') {
            return array_merge([
                ['lbl' => 'Producto', 'w' => 25, 'cls' => '',
                 'val' => static fn (array $r): string => $wrap((string) ($r['producto_nombre'] ?? ''), $pt(25), $ptFila)],
                ['lbl' => 'Variante', 'w' => 21, 'cls' => '',
                 'val' => static fn (array $r): string => $desc((string) ($r['variante_nombre'] ?? ''), (string) ($r['variante_valor'] ?? ''), $pt(21))],
                ['lbl' => 'Cantidad', 'w' => 8, 'cls' => 'text-end',
                 'val' => static fn (array $r): string => $num($r['cantidad_vendida'] ?? 0)],
                ['lbl' => 'T.IVA', 'w' => 6, 'cls' => 'text-center',
                 'val' => static fn (array $r): string => (float) ($r['tarifa_iva'] ?? 0) . '%'],
            ], $importes(10, 9, 11));
        }

        if ($agrupar === 'CLIENTE') {
            return array_merge([
                ['lbl' => 'Cliente', 'w' => 36, 'cls' => '',
                 'val' => static fn (array $r): string => $desc((string) ($r['cliente_nombre'] ?? ''), (string) ($r['cliente_ruc'] ?? ''), $pt(36))],
                ['lbl' => 'Saldo x Cobrar', 'w' => 13, 'cls' => 'text-end', 'tot' => $sumar('saldo'),
                 'val' => static fn (array $r): string => $num($r['saldo'] ?? 0)],
            ], $importes(13, 11, 14));
        }

        if ($agrupar === 'FECHA' || $agrupar === 'MES') {
            $esMes = ($agrupar === 'MES');
            return array_merge([
                ['lbl' => $esMes ? 'Mes' : 'Fecha', 'w' => 24, 'cls' => $esMes ? '' : 'text-center',
                 'val' => static fn (array $r): string => "<span style='font-weight:bold;'>"
                     . ($esMes ? self::formatearMes((string) ($r['mes'] ?? '')) : $e(date('d-m-Y', strtotime((string) ($r['fecha'] ?? '')))))
                     . '</span>'],
                ['lbl' => 'Documentos', 'w' => 14, 'cls' => 'text-center', 'tot' => $sumar('cantidad_facturas'),
                 'val' => static fn (array $r): string => (string) (int) ($r['cantidad_facturas'] ?? 0)],
            ], $importes(15, 14, 18));
        }

        // DETALLADO: A4 horizontal. En consolidado entra la columna del establecimiento,
        // que se descuenta del nombre del cliente (la única que se puede partir en líneas).
        $cols = [];
        if ($consolidado) {
            $cols[] = ['lbl' => 'Estab.', 'w' => 5, 'cls' => 'text-center',
                       'val' => static fn (array $r): string => $wrap((string) ($r['establecimiento'] ?? ''), $pt(5), $ptFila)];
        }
        return array_merge($cols, [
            ['lbl' => 'Fecha', 'w' => 7, 'cls' => 'text-center',
             'val' => static fn (array $r): string => $e(date('d-m-Y', strtotime((string) ($r['fecha_emision'] ?? ''))))],
            ['lbl' => 'Documento', 'w' => 10, 'cls' => '',
             'val' => static fn (array $r): string => $wrap((string) ($r['numero_factura'] ?? ''), $pt(10), $ptFila)],
            ['lbl' => 'Cliente', 'w' => $consolidado ? 12 : 16, 'cls' => '',
             'val' => static fn (array $r): string => $desc((string) ($r['cliente_nombre'] ?? ''), (string) ($r['cliente_ruc'] ?? ''), $pt($consolidado ? 12 : 16))],
            ['lbl' => 'Estado', 'w' => 8, 'cls' => 'text-center',
             'val' => static fn (array $r): string => $wrap(strtoupper((string) ($r['estado'] ?? '')), $pt(8), $ptFila)],
            ['lbl' => 'Vendedor', 'w' => 9, 'cls' => '',
             'val' => static fn (array $r): string => $wrap((string) ($r['vendedor_nombre'] ?? ''), $pt(9), $ptFila)],
            ['lbl' => 'Cajero', 'w' => 8, 'cls' => '',
             'val' => static fn (array $r): string => $wrap((string) ($r['cajero_nombre'] ?? ''), $pt(8), $ptFila)],
            ['lbl' => 'Usuario', 'w' => $consolidado ? 7 : 8, 'cls' => '',
             'val' => static fn (array $r): string => $wrap((string) ($r['usuario_nombre'] ?? ''), $pt($consolidado ? 7 : 8), $ptFila)],
            ['lbl' => 'Base 0%', 'w' => 7, 'cls' => 'text-end', 'tot' => 'total_base_0',
             'val' => static fn (array $r): string => $num($r['base_0'] ?? 0)],
            ['lbl' => 'Base IVA', 'w' => 7, 'cls' => 'text-end', 'tot' => 'total_base_iva',
             'val' => static fn (array $r): string => $num($r['base_iva'] ?? 0)],
            ['lbl' => 'IVA', 'w' => 6, 'cls' => 'text-end', 'tot' => 'total_iva',
             'val' => static fn (array $r): string => $num($r['valor_iva'] ?? 0)],
            ['lbl' => 'Total', 'w' => 8, 'cls' => 'text-end', 'tot' => 'gran_total',
             'val' => static fn (array $r): string => "<span style='font-weight:bold;'>" . $num($r['total'] ?? 0) . '</span>'],
            ['lbl' => 'Retenc.', 'w' => 6, 'cls' => 'text-end', 'tot' => $sumar('retenciones'),
             'val' => static fn (array $r): string => $num($r['retenciones'] ?? 0)],
        ]);
    }

    /**
     * Fila "TOTALES GENERALES" como tabla aparte (ver exportPdf(): un <tfoot> se repetiría en
     * todas las páginas). La etiqueta absorbe con un colspan las columnas iniciales que no
     * totalizan nada, y a partir de ahí cada columna conserva su ancho para que la rejilla
     * calce con la tabla de arriba.
     */
    private function filaTotalesPdf(array $cols, array $totales, array $rows, string $etiqueta): string
    {
        $primera = null;
        foreach ($cols as $i => $c) {
            if (!empty($c['tot'])) { $primera = $i; break; }
        }
        if ($primera === null) {
            return '';
        }
        $html = '<tr>';
        if ($primera > 0) {
            $anchoEtq = 0;
            for ($i = 0; $i < $primera; $i++) {
                $anchoEtq += (int) $cols[$i]['w'];
            }
            $html .= "<td colspan='{$primera}' class='text-end' style='width:{$anchoEtq}%;'>"
                   . htmlspecialchars($etiqueta) . ':</td>';
        }
        for ($i = $primera, $n = count($cols); $i < $n; $i++) {
            $c   = $cols[$i];
            $val = '';
            if (!empty($c['tot'])) {
                $monto = $c['tot'] instanceof \Closure
                    ? (float) ($c['tot'])($rows)
                    : (float) ($totales[$c['tot']] ?? 0);
                if (isset($c['fmt'])) {
                    // Formato propio de la columna (unidades, sin símbolo de moneda).
                    $val = ($c['fmt'])($monto);
                } elseif ($c['lbl'] === 'Documentos') {
                    $val = number_format($monto, 0);
                } elseif ($c['lbl'] === 'Total') {
                    $val = "<span style='color:#146c43;'>$" . number_format($monto, 2) . '</span>';
                } else {
                    $val = number_format($monto, 2);
                }
            }
            $html .= "<td class='text-end' style='width:{$c['w']}%;'>{$val}</td>";
        }
        return $html . '</tr>';
    }

    /**
     * Banda de indicadores bajo el encabezado: los mismos cuatro valores que la tarjeta de
     * control de la pantalla (documentos, base 0 %, base con IVA, IVA y total), para que el
     * PDF se entienda sin tener el módulo abierto.
     */
    private function bloqueTotalesPdf(array $totales): string
    {
        $kpi = static function (string $lbl, string $val, bool $destacar = false): string {
            $cls = $destacar ? 'k-val k-tot' : 'k-val';
            return "<td style='width:20%;'><span class='k-lbl'>" . htmlspecialchars($lbl) . '</span><br>'
                 . "<span class='{$cls}'>" . $val . '</span></td>';
        };
        return '<table class="kpis"><tr>'
            . $kpi('DOCUMENTOS', number_format((float) ($totales['total_documentos'] ?? 0), 0))
            . $kpi('SUBTOTAL 0% / EXENTO', '$' . number_format((float) ($totales['total_base_0'] ?? 0), 2))
            . $kpi('BASE CON IVA', '$' . number_format((float) ($totales['total_base_iva'] ?? 0), 2))
            . $kpi('IVA', '$' . number_format((float) ($totales['total_iva'] ?? 0), 2))
            . $kpi('GRAN TOTAL', '$' . number_format((float) ($totales['gran_total'] ?? 0), 2), true)
            . '</tr></table>';
    }

    /**
     * Encabezado del PDF: logo del establecimiento a la izquierda del nombre de la empresa,
     * con el título del reporte y la fecha de generación. Html2Pdf no admite float ni flex,
     * así que va en una tabla de tres celdas —logo | textos | celda vacía del mismo ancho que
     * la del logo— para que el nombre siga centrado en la hoja. Sin logo, encabezado centrado.
     */
    private function encabezadoPdf(int $idEmpresa, string $nombreEmpresa, string $titulo): string
    {
        $textos = '<h2>' . htmlspecialchars($nombreEmpresa) . '</h2>'
            . '<h3>' . htmlspecialchars($titulo) . '</h3>'
            . '<p>Generado: ' . date('d-m-Y H:i:s') . '</p>';
        $logo = $this->logoPdf($idEmpresa);
        if ($logo === '') {
            return "<div class=\"header\" style=\"margin-bottom:8px;\">{$textos}</div>";
        }
        $celda = 'border:none;padding:0;vertical-align:middle;';
        return '<table style="margin-bottom:8px;"><tr>'
            . "<td style=\"width:22%;{$celda}\"><img src=\"" . htmlspecialchars($logo) . "\" style=\"max-width:40mm;max-height:18mm;\"></td>"
            . "<td style=\"width:56%;{$celda}\"><div class=\"header\">{$textos}</div></td>"
            . "<td style=\"width:22%;{$celda}\"></td>"
            . '</tr></table>';
    }

    /**
     * Ruta en disco del logo del establecimiento principal ('' si no hay). La tabla guarda la
     * URL pública (empresa_establecimiento.logo_ruta) y se resuelve igual que en los demás PDF
     * del sistema. Solo se devuelve si es una imagen legible: ante una imagen que no puede
     * medir, Html2Pdf aborta el PDF entero, y un logo dañado no debe impedir sacar el reporte.
     */
    private function logoPdf(int $idEmpresa): string
    {
        $ruta = (string) ((new \App\models\Empresa())->getEstablecimientos($idEmpresa)[0]['logo_ruta'] ?? '');
        if ($ruta === '') {
            return '';
        }
        $clean = ltrim($ruta, '/');
        if (strpos($clean, 'sistema/public/') === 0) {
            $clean = substr($clean, strlen('sistema/public/'));
        } elseif (strpos($clean, 'sistema/') === 0) {
            $clean = substr($clean, strlen('sistema/'));
        }
        if (strpos($clean, 'public/') === 0) {
            $clean = substr($clean, strlen('public/'));
        }
        foreach ([MVC_ROOT . '/public/' . $clean, MVC_ROOT . '/' . $clean] as $cand) {
            if (is_file($cand) && @getimagesize($cand)) {
                return $cand;
            }
        }
        return '';
    }

    /**
     * Caja "Filtros aplicados" del PDF, con dos pares etiqueta/valor por fila.
     * Html2Pdf no admite float ni flex y, con un colspan en la primera fila, ignora los anchos
     * declarados de esa tabla: por eso el título va en su propia tabla de una celda y los pares
     * en otra donde TODAS las filas llevan las mismas cuatro celdas con los mismos anchos (que
     * una declare otros basta para que adopte ESOS y la tabla se salga de la hoja).
     */
    private function bloqueFiltrosPdf(array $filtrosTxt): string
    {
        if (!$filtrosTxt) {
            return '';
        }
        $e     = static fn ($v): string => htmlspecialchars((string) $v);
        $pares = [];
        foreach ($filtrosTxt as $lbl => $val) {
            $pares[] = [(string) $lbl, trim((string) $val)];
        }
        $filas = '';
        for ($i = 0, $n = count($pares); $i < $n; $i += 2) {
            [$lblA, $valA] = $pares[$i];
            [$lblB, $valB] = $pares[$i + 1] ?? ['', ''];
            $filas .= '<tr>'
                . "<td class='f-lbl' style='width:16%;'>" . ($lblA !== '' ? $e($lblA) . ':' : '') . '</td>'
                . "<td style='width:34%;'>" . $e($valA) . '</td>'
                . "<td class='f-lbl' style='width:16%;'>" . ($lblB !== '' ? $e($lblB) . ':' : '') . '</td>'
                . "<td style='width:34%;'>" . $e($valB) . '</td>'
                . '</tr>';
        }
        return "<table class='fil-tit'><tr><td style='width:100%;'>Filtros aplicados</td></tr></table>"
            . "<table class='filtros'>{$filas}</table>";
    }

    /** Estilos de la caja "Filtros aplicados" (mismo formato que en Cuentas por Cobrar). */
    private const CSS_FILTROS_PDF = '
        table.fil-tit { margin-bottom: 0; }
        table.fil-tit td { background: #e9ecef; border: 1px solid #9aa7b4; padding: 3px 5px; font-size: 8.5pt; font-weight: bold; color: #1b2a3a; }
        table.filtros { margin-bottom: 8px; }
        table.filtros td { border: 1px solid #c3ccd6; padding: 2px 4px; font-size: 7.5pt; color: #000; }
        table.filtros td.f-lbl { background: #f8f9fa; font-weight: bold; }
    ';

    /**
     * Descripción legible de los filtros aplicados para la caja del PDF: etiqueta => valor,
     * con los ids de cliente, vendedor y producto resueltos a nombre. Los filtros de uso
     * ocasional (variante, info adicional, estado) solo aparecen cuando traen valor.
     */
    private function describirFiltros(array $idsEmpresa, array $filtros, bool $consolidado): array
    {
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $tipoDocLbl = [
            'FACTURA'          => 'Facturas de venta',
            'RECIBO'           => 'Recibos de venta',
            'NOTA_CREDITO'     => 'Notas de crédito en ventas',
            'FACTURA_MENOS_NC' => 'Facturas de venta menos NC de ventas',
        ];

        $fmt   = static fn (string $f): string => $f !== '' ? date('d-m-Y', strtotime($f)) : '';
        $desde = $fmt((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = $fmt((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' && $hasta !== '') {
            $periodo = "Del {$desde} al {$hasta}";
        } elseif ($desde !== '') {
            $periodo = "Desde {$desde}";
        } elseif ($hasta !== '') {
            $periodo = "Hasta {$hasta}";
        } else {
            $periodo = 'Sin límite de fechas';
        }

        $vendedorTxt = 'Todos';
        if (\App\Helpers\AlcanceRegistros::idsVendedor($filtros)) {
            // Usuario restringido (§6): el filtro de pantalla no aplica, ve solo su vendedor.
            $propio = \App\Helpers\AlcanceRegistros::vendedorPropio($filtros, $idEmpresa, (int) ($_SESSION['id_usuario'] ?? 0));
            $vendedorTxt = $propio[0]['nombre'] ?? 'Su vendedor';
        } elseif (!empty($filtros['id_vendedor'])) {
            $v = (new \App\repositories\modulos\VendedorRepository())->findById((int) $filtros['id_vendedor'], $idEmpresa);
            $vendedorTxt = $v['nombre'] ?? ('#' . (int) $filtros['id_vendedor']);
        }

        $cxcRepo    = new \App\repositories\modulos\CuentasPorCobrarRepository();
        $clienteTxt = 'Todos';
        if (!empty($filtros['id_cliente'])) {
            $ids = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string) $filtros['id_cliente']);
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if ($ids) {
                // El filtro viene expandido a las demás filas del mismo cliente (cédula/RUC y,
                // en consolidado, otros establecimientos): se nombra una sola vez por cliente.
                $nombres = [];
                $vistos  = [];
                foreach ($cxcRepo->getClientesPorIds($ids, $idsEmpresa) as $id => $c) {
                    $clave = IdentificacionTercero::claveGrupo($c['identificacion'], 'id:' . $id);
                    if (isset($vistos[$clave])) {
                        continue;
                    }
                    $vistos[$clave] = true;
                    $nombres[] = trim($c['nombre'] . ($c['identificacion'] !== '' ? " ({$c['identificacion']})" : ''));
                }
                $clienteTxt = $nombres ? implode(', ', $nombres) : implode(', ', array_map(static fn ($i) => "#{$i}", $ids));
            }
        }

        $productoTxt = [];
        $idsProd = $filtros['id_producto'] ?? '';
        $idsProd = array_values(array_unique(array_filter(array_map('intval', is_array($idsProd) ? $idsProd : explode(',', (string) $idsProd)))));
        if ($idsProd) {
            $vistos = [];
            foreach ($cxcRepo->getProductosPorIds($idsProd, $idsEmpresa) as $id => $p) {
                $clave = $p['codigo'] !== '' ? 'c:' . $p['codigo'] : 'id:' . $id;
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;
                $productoTxt[] = trim(($p['codigo'] !== '' ? $p['codigo'] . ' - ' : '') . $p['nombre']);
            }
        }
        if (trim((string) ($filtros['producto_texto'] ?? '')) !== '') {
            $productoTxt[] = '"' . trim((string) $filtros['producto_texto']) . '"';
        }

        // Marca y categoría: el filtro puede venir expandido (consolidado); se nombra la de
        // la empresa activa, que es la que el usuario eligió en el selector.
        $marcaTxt = $categoriaTxt = '';
        $idsMarca = array_values(array_filter(array_map('intval', (array) ($filtros['id_marca'] ?? []))));
        if ($idsMarca) {
            $m = (new \App\repositories\modulos\MarcaRepository())->getDetalleCompleto($idsMarca[0], $idEmpresa);
            $marcaTxt = $m['nombre'] ?? ('#' . $idsMarca[0]);
        }
        $idsCat = array_values(array_filter(array_map('intval', (array) ($filtros['id_categoria'] ?? []))));
        if ($idsCat) {
            $c = (new \App\repositories\modulos\CategoriaRepository())->getDetalleCompleto($idsCat[0], $idEmpresa);
            $categoriaTxt = $c['nombre'] ?? ('#' . $idsCat[0]);
        }

        $out = [
            'Alcance'           => $consolidado
                ? $this->describirAlcance($idsEmpresa, true)
                : 'Este establecimiento',
            'Tipo de documento' => $tipoDocLbl[$filtros['tipo_documento'] ?? 'FACTURA'] ?? 'Facturas de venta',
            'Período'           => $periodo,
            'Agrupación'        => self::AGRUPACION_LBL[$filtros['agrupar_por'] ?? 'NINGUNO'] ?? 'Detallado',
            'Borradores'        => match ($filtros['borradores'] ?? 'EXCLUIR') {
                'INCLUIR' => 'Incluidos (válidos + borradores)',
                'SOLO'    => 'Solo documentos en borrador',
                default   => 'Excluidos (solo documentos válidos)',
            },
            'Vendedor'          => $vendedorTxt,
            'Cliente'           => $clienteTxt,
            'Producto'          => $productoTxt ? implode(', ', $productoTxt) : 'Todos',
        ];
        if ($marcaTxt !== '') {
            $out['Marca'] = $marcaTxt;
        }
        if ($categoriaTxt !== '') {
            $out['Categoría'] = $categoriaTxt;
        }
        if (trim((string) ($filtros['variante_texto'] ?? '')) !== '') {
            $out['Variante'] = trim((string) $filtros['variante_texto']);
        }
        if (trim((string) ($filtros['buscar_info'] ?? '')) !== '') {
            $out['Info adicional'] = trim((string) $filtros['buscar_info']);
        }
        if (($filtros['estado'] ?? 'TODOS') !== 'TODOS') {
            $out['Estado'] = ucfirst(strtolower((string) $filtros['estado']));
        }
        return $out;
    }
}
