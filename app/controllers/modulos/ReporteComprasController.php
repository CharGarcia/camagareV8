<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\ColumnasReporte;
use App\Helpers\ReportePdf;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\ReporteComprasRepository;
use App\Services\modulos\ReportePersonalizacionService;

class ReporteComprasController extends BaseModuloController
{
    private ReporteComprasRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_compras';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteComprasRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $anios = $this->repository->getAniosDisponibles($idEmpresa);
        $tiposComprobante = $this->repository->getTiposComprobante($idEmpresa);

        // Consolidado por establecimientos (mismo selector que Cuentas por Cobrar/Pagar): solo
        // aparece si la empresa activa es la matriz del grupo RUC y el usuario tiene acceso a
        // al menos otro establecimiento (ver EmpresaRepository::getIdsConsolidadoDesdeMatriz).
        $empresaRepo      = new EmpresaRepository();
        $idsConsolidado   = $empresaRepo->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
        $establecimientos = $idsConsolidado ? $empresaRepo->getEtiquetasEstablecimiento($idsConsolidado) : [];

        $this->viewWithLayout('layouts.main', 'modulos/reporte_compras/index', [
            'titulo'           => 'Reporte de Compras',
            'perm'             => $this->getPermisos(),
            'vistaConfig'      => $prefsVista,
            'rutaModulo'       => $this->getRutaModulo(),
            'anios'            => $anios,
            'tiposComprobante' => $tiposComprobante,
            'puedeConsolidar'  => !empty($idsConsolidado),
            'establecimientos' => $establecimientos,
            'columnasVistas'   => $this->cabecerasPantalla(),
            'fullWidth'        => true,
            'base'             => BASE_URL,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'tipo_documento'    => $_REQUEST['tipo_documento']    ?? 'TODOS',
            'agrupar_por'       => $_REQUEST['agrupar_por']       ?? 'NINGUNO',
            'fecha_desde'       => $_REQUEST['fecha_desde']       ?? '',
            'fecha_hasta'       => $_REQUEST['fecha_hasta']       ?? '',
            'id_proveedor'      => $_REQUEST['id_proveedor']      ?? '',
            'id_producto'       => $_REQUEST['id_producto']       ?? '',
            'producto_texto'    => trim($_REQUEST['producto_texto'] ?? ''),
            'tipo_comprobante'  => $_REQUEST['tipo_comprobante']  ?? '',
            'buscar_info'       => trim($_REQUEST['buscar_info']  ?? ''),
            // ESTABLECIMIENTO (solo la empresa activa) | CONSOLIDADO (todo el grupo RUC;
            // solo se honra desde la matriz — ver resolverAlcance()).
            'alcance'           => strtoupper(trim((string) ($_REQUEST['alcance'] ?? ''))),
        ];
    }

    /**
     * Alcance del reporte. Devuelve [idsEmpresa, consolidado]. `alcance=CONSOLIDADO` solo se
     * honra si la empresa activa es la matriz del grupo RUC y hay hermanas accesibles para el
     * usuario; si no, se ignora en silencio. En consolidado, los filtros por id de proveedor y
     * de producto se expanden a las filas hermanas (proveedores y productos son tablas por
     * establecimiento: se cruzan por identificación y por código).
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
        if ($consolidado) {
            if (!empty($filtros['id_proveedor'])) {
                $raw = is_array($filtros['id_proveedor']) ? $filtros['id_proveedor'] : explode(',', (string) $filtros['id_proveedor']);
                $filtros['id_proveedor'] = (new \App\repositories\modulos\CuentasPorPagarRepository())
                    ->expandirProveedoresPorIdentificacion($raw, $idsEmpresa);
            }
            if (!empty($filtros['id_producto'])) {
                $raw = is_array($filtros['id_producto']) ? $filtros['id_producto'] : explode(',', (string) $filtros['id_producto']);
                $filtros['id_producto'] = $this->repository->expandirProductosPorCodigo($raw, $idsEmpresa);
            }
        }
        return [$idsEmpresa, $consolidado];
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

    /** Vista del reporte que corresponde a "Agrupar por" (lo desconocido es el detallado). */
    private function vistaDe(string $agrupar): string
    {
        return isset(ColumnasReporte::vistas($this->getRutaModulo())[$agrupar]) ? $agrupar : 'NINGUNO';
    }

    /**
     * Columnas de la vista según la personalización de la empresa activa (botón
     * «Personalizar»): ['pantalla' => …, 'pdf' => …, 'orientacion' => …, 'horizontal' => bool].
     * De aquí salen la tabla de la pantalla, el Excel y el PDF. Ver ColumnasReporte.
     */
    private function columnasVista(string $agrupar): array
    {
        return ReportePersonalizacionService::crear()->resolver(
            (int) $_SESSION['id_empresa'],
            $this->getRutaModulo(),
            $this->vistaDe($agrupar)
        );
    }

    /** Cabecera de la tabla en pantalla: lista de [clave, titulo, clase]. */
    private static function cabecera(array $columnas): array
    {
        $out = [];
        foreach ($columnas as $clave => $col) {
            $out[] = ['clave' => $clave, 'titulo' => $col['titulo'], 'clase' => ColumnasReporte::claseAlinear($col['alinear'])];
        }
        return $out;
    }

    /** Cabeceras de todas las vistas, para que la pantalla dibuje la tabla antes de consultar. */
    private function cabecerasPantalla(): array
    {
        $out = [];
        foreach (array_keys(ColumnasReporte::vistas($this->getRutaModulo())) as $vista) {
            $out[$vista] = self::cabecera($this->columnasVista((string) $vista)['pantalla']);
        }
        return $out;
    }

    /** Filas del reporte según la agrupación. */
    private function filasReporte(array $idsEmpresa, array $filtros): array
    {
        return match ($filtros['agrupar_por']) {
            'PROVEEDOR' => $this->repository->getReporteAgrupadoProveedor($idsEmpresa, $filtros),
            'PRODUCTO'  => $this->repository->getReporteAgrupadoProducto($idsEmpresa, $filtros),
            'FECHA'     => $this->repository->getReporteAgrupadoFecha($idsEmpresa, $filtros),
            'MES'       => $this->repository->getReporteAgrupadoMes($idsEmpresa, $filtros),
            default     => $this->repository->getReporteDetallado($idsEmpresa, $filtros),
        };
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros   = $this->getFiltrosDesdeRequest();
            [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
            $idEmpresa = $idsEmpresa; // alcance del reporte (una empresa o el grupo RUC)

            $rows     = $this->filasReporte($idEmpresa, $filtros);
            $columnas = $this->columnasVista((string) $filtros['agrupar_por'])['pantalla'];

            $stats          = $this->repository->getEstadisticas($idEmpresa, $filtros);

            ob_start();
            if (empty($rows)) {
                $colSpan = max(1, count($columnas));
                echo '<tr><td colspan="' . $colSpan . '" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaHtml($r, (string) $filtros['agrupar_por'], $columnas, $consolidado);
                }
                echo $this->renderFilaTotalesHtml($rows, (string) $filtros['agrupar_por'], $columnas);
            }
            $rowsHtml = ob_get_clean();

            $jsonOutput = json_encode([
                'ok'          => true,
                'rows'        => $rowsHtml,
                'rawData'     => $rows,
                'stats'       => $stats,
                'agrupacion'  => $filtros['agrupar_por'],
                'columnas'    => self::cabecera($columnas),
                'consolidado' => $consolidado,
            ]);

            if ($jsonOutput === false) {
                error_log('ReporteCompras JSON Encode Error: ' . json_last_error_msg());
                echo json_encode(['ok' => false, 'error' => 'Error de codificación de datos.']);
            } else {
                echo $jsonOutput;
            }

        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            error_log('ReporteCompras Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Suma de un campo sobre las filas del reporte. */
    private static function sumarCampo(array $rows, string $campo): float
    {
        return array_sum(array_map(static fn (array $r): float => (float) ($r[$campo] ?? 0), $rows));
    }

    /** Cantidad: sin decimales si es entera, con dos si no. */
    private static function fmtCantidad(float $n): string
    {
        return number_format($n, abs($n - round($n)) < 0.00001 ? 0 : 2);
    }

    /** "N proveedores", "N días"… para la etiqueta de la fila de totales (pantalla y Excel). */
    private static function etiquetaTotal(string $agrupar, int $n): string
    {
        $s = match ($agrupar) {
            'PROVEEDOR' => $n === 1 ? 'proveedor' : 'proveedores',
            'PRODUCTO'  => $n === 1 ? 'producto'  : 'productos',
            'FECHA'     => $n === 1 ? 'día'       : 'días',
            'MES'       => $n === 1 ? 'mes'       : 'meses',
            default     => $n === 1 ? 'comprobante' : 'comprobantes',
        };
        return "TOTAL ({$n} {$s})";
    }

    /**
     * Fila de totales al pie de la tabla (pantalla). Suma las filas mostradas, así que
     * coincide con las tarjetas de arriba y con la fila "TOTALES" del PDF. La etiqueta
     * ocupa las columnas visibles que van antes del primer importe.
     */
    private function renderFilaTotalesHtml(array $rows, string $agrupar, array $columnas): string
    {
        $celdas  = '';
        $lider   = 0;
        $enLider = true;
        foreach ($columnas as $clave => $col) {
            if ($col['suma'] === null) {
                if ($enLider) {
                    $lider++;
                } else {
                    $celdas .= '<td></td>';
                }
                continue;
            }
            $enLider = false;
            $suma    = self::sumarCampo($rows, (string) $col['suma']);
            $celdas .= match ($clave) {
                'comprobantes' => "<td class='text-center'>" . (int) $suma . '</td>',
                'cantidad'     => "<td class='text-center'>" . self::fmtCantidad($suma) . '</td>',
                'total'        => "<td class='text-end text-danger'>" . number_format($suma, 2) . '</td>',
                'retenciones'  => "<td class='text-end text-warning'>" . number_format($suma, 2) . '</td>',
                default        => "<td class='text-end'>" . number_format($suma, 2) . '</td>',
            };
        }
        $etq = $lider > 0 ? "<td colspan='{$lider}'>" . self::etiquetaTotal($agrupar, count($rows)) . '</td>' : '';

        return "<tr class='table-light fw-bold'>{$etq}{$celdas}</tr>";
    }

    /** Una fila de la tabla en pantalla: una celda por cada columna visible. */
    private function renderFilaHtml(array $r, string $agruparPor, array $columnas, bool $consolidado = false): string
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
        if (!in_array($agruparPor, ['PROVEEDOR', 'PRODUCTO', 'FECHA', 'MES'], true) && !empty($r['id'])) {
            $attrs = ' style="cursor:pointer;" title="Clic para ver el detalle"'
                   . ' data-doc-id="' . (int)$r['id'] . '"'
                   . ' data-doc-tipo="COMPRA"'
                   . ' data-doc-numero="' . htmlspecialchars($r['numero_documento'] ?? '', ENT_QUOTES) . '"'
                   . ' data-doc-sujeto="' . htmlspecialchars($r['proveedor_nombre'] ?? '', ENT_QUOTES) . '"';
        }

        $html = '<tr class="align-middle"' . $attrs . '>';
        foreach (array_keys($columnas) as $clave) {
            $html .= $this->celdaHtml((string) $clave, $r, $badgeEst);
        }
        return $html . '</tr>';
    }

    /** Celda de una columna en pantalla (claves de ColumnasReporte). */
    private function celdaHtml(string $clave, array $r, string $badgeEst): string
    {
        $e     = static fn ($v): string => htmlspecialchars((string) ($v ?? ''));
        $num   = static fn (string $c): string => number_format((float) ($r[$c] ?? 0), 2);
        $fecha = static fn ($v): string => date('d/m/Y', strtotime((string) $v));

        switch ($clave) {
            case 'fecha_emision':
                return "<td class='text-center'>" . $fecha($r['fecha_emision'] ?? '') . '</td>';
            case 'fecha_registro':
                return "<td class='text-center text-muted small'>" . $fecha($r['fecha_registro'] ?? $r['fecha_emision'] ?? '') . '</td>';
            case 'numero_documento':
                return "<td>{$badgeEst}<span class='fw-bold'>" . $e($r['numero_documento'] ?? '') . '</span></td>';
            case 'proveedor':
                return "<td><span class='fw-bold'>" . $e($r['proveedor_nombre'] ?? '') . "</span><br><small class='text-muted'>" . $e($r['proveedor_ruc'] ?? '') . '</small></td>';
            case 'tipo':
                return "<td><span class='badge bg-primary bg-opacity-10 text-primary border border-primary' style='font-size:.7rem;'>" . $e($r['tipo_comprobante_nombre'] ?? '') . '</span></td>';
            case 'usuario':
                return '<td>' . $e($r['usuario_nombre'] ?? '') . '</td>';
            case 'autorizacion':
                $nroAut = (string) ($r['numero_autorizacion'] ?? '');
                return "<td class='text-muted small' title='" . $e($nroAut) . "'>" . $e(strlen($nroAut) > 15 ? substr($nroAut, 0, 12) . '…' : $nroAut) . '</td>';
            case 'comprobantes':
                return "<td class='text-center'>" . (int) ($r['cantidad_comprobantes'] ?? 0) . '</td>';
            case 'producto':
                return "<td><span class='fw-bold'>" . $e($r['producto_nombre'] ?? '') . "</span><br><small class='text-muted'>" . $e($r['producto_codigo'] ?? '') . '</small></td>';
            case 'cantidad':
                return "<td class='text-center'>" . (float) ($r['cantidad_comprada'] ?? 0) . '</td>';
            case 'tarifa':
                return "<td class='text-center'>" . (float) ($r['tarifa_iva'] ?? 0) . '%</td>';
            case 'fecha':
                return "<td><span class='fw-bold'>" . $fecha($r['fecha'] ?? '') . '</span></td>';
            case 'mes':
                return "<td><span class='fw-bold'>" . self::formatearMes((string) ($r['mes'] ?? '')) . '</span></td>';
            case 'total':
                return "<td class='text-end fw-bold text-danger'>" . $num('total') . '</td>';
            case 'retenciones':
                return "<td class='text-end text-warning'>" . $num('retenciones') . '</td>';
            case 'base_0':
            case 'base_iva':
            case 'valor_iva':
            case 'valor_ice':
                return "<td class='text-end'>" . $num($clave) . '</td>';
        }
        return '<td></td>';
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

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new \App\repositories\modulos\ProveedorRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'razon_social', 'ASC');

        $data = array_map(function ($row) {
            return [
                'id'            => $row['id'],
                'nombre'        => $row['razon_social'] ?? $row['nombre'] ?? '',
                'identificacion'=> $row['identificacion'] ?? '',
            ];
        }, $result['rows'] ?? []);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'compra');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    /** Autocompletado de ítems de compra (descripciones distintas de compras_detalle). */
    public function buscarItemsAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        $data = $this->repository->buscarItems($idEmpresa, $q);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    /** Autocompletado de info adicional (nombre/valor distintos de compras_adicional). */
    public function buscarInfoAdicionalAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        $data = $this->repository->buscarInfoAdicional($idEmpresa, $q);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    /**
     * Valores de una columna en el Excel: tantos como cabeceras declara su `excel`.
     * $vista decide el orden de las columnas dobles (igual que sus cabeceras del catálogo).
     */
    private function celdasExcel(string $clave, array $r, string $vista): array
    {
        $fecha = static fn ($v): string => date('d/m/Y', strtotime((string) $v));

        return match ($clave) {
            'fecha_emision'    => [$fecha($r['fecha_emision'] ?? '')],
            'fecha_registro'   => [$fecha($r['fecha_registro'] ?? $r['fecha_emision'] ?? '')],
            'numero_documento' => [$r['numero_documento'] ?? ''],
            'proveedor'        => $vista === 'NINGUNO'
                                    ? [$r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '']   // Proveedor, RUC/Cédula
                                    : [$r['proveedor_ruc'] ?? '', $r['proveedor_nombre'] ?? ''],  // RUC/Cédula, Proveedor
            'tipo'             => [$r['tipo_comprobante_nombre'] ?? ''],
            'usuario'          => [$r['usuario_nombre'] ?? ''],
            'autorizacion'     => [$r['numero_autorizacion'] ?? ''],
            'comprobantes'     => [$r['cantidad_comprobantes'] ?? 0],
            'producto'         => [$r['producto_codigo'] ?? '', $r['producto_nombre'] ?? ''],
            'cantidad'         => [(float) ($r['cantidad_comprada'] ?? 0)],
            'tarifa'           => [($r['tarifa_iva'] ?? 0) . '%'],
            'fecha'            => [$fecha($r['fecha'] ?? '')],
            'mes'              => [self::formatearMes((string) ($r['mes'] ?? ''))],
            'base_0', 'base_iva', 'valor_iva', 'valor_ice', 'total', 'retenciones'
                               => [(float) ($r[$clave] ?? 0)],
            default            => [''],
        };
    }

    /** Fila TOTAL del Excel, alineada con las columnas visibles. */
    private function filaTotalesExcel(array $rows, string $agrupar, array $columnas, bool $conEstab): array
    {
        $fila = $conEstab ? [''] : [];
        foreach ($columnas as $clave => $col) {
            if ($col['suma'] === null) {
                $fila = array_merge($fila, array_fill(0, count($col['excel']), ''));
                continue;
            }
            $suma   = self::sumarCampo($rows, (string) $col['suma']);
            $fila[] = match ($clave) {
                'comprobantes' => (int) $suma,
                'cantidad'     => $suma,
                default        => round($suma, 2),
            };
        }
        if ($fila) {
            $fila[0] = self::etiquetaTotal($agrupar, count($rows));
        }
        return $fila;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
        $agrupar   = (string) $filtros['agrupar_por'];
        $vista     = $this->vistaDe($agrupar);

        $rows     = $this->filasReporte($idsEmpresa, $filtros);
        $columnas = $this->columnasVista($agrupar)['pantalla'];
        // Consolidado: columna "Estab." al inicio con el establecimiento dueño del documento.
        $conEstab = $consolidado && $vista === 'NINGUNO';

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';
            if ($consolidado) {
                $nombreEmpresa .= ' — ' . $this->describirAlcance($idsEmpresa, true);
            }

            $headers = $conEstab ? ['Estab.'] : [];
            foreach ($columnas as $col) {
                $headers = array_merge($headers, $col['excel']);
            }

            $exportData = [];
            foreach ($rows as $r) {
                $fila = $conEstab ? [(string) ($r['establecimiento'] ?? '')] : [];
                foreach (array_keys($columnas) as $clave) {
                    $fila = array_merge($fila, $this->celdasExcel((string) $clave, $r, $vista));
                }
                $exportData[] = $fila;
            }

            // Fila TOTAL al final, con las mismas sumas que la pantalla.
            if ($rows) {
                $exportData[] = $this->filaTotalesExcel($rows, $agrupar, $columnas, $conEstab);
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Compras', $headers, $exportData, 'Reporte_Compras', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    /**
     * Celda de una columna en el PDF: [html, clase]. Los textos largos se parten a la
     * medida de la columna ($anchoPt) para que no desborden sobre la vecina.
     */
    private function celdaPdf(string $clave, array $r, float $anchoPt, float $pt): array
    {
        $txt   = static fn ($v): string => ReportePdf::texto((string) ($v ?? ''), $anchoPt, $pt);
        $num   = static fn (string $c): string => number_format((float) ($r[$c] ?? 0), 2);
        $fecha = static fn ($v): string => date('d/m/Y', strtotime((string) $v));

        return match ($clave) {
            'fecha_emision'    => [$fecha($r['fecha_emision'] ?? ''), 'text-center'],
            'fecha_registro'   => [$fecha($r['fecha_registro'] ?? $r['fecha_emision'] ?? ''), 'text-center'],
            'numero_documento' => [$txt($r['numero_documento'] ?? ''), ''],
            'proveedor'        => [$txt($r['proveedor_nombre'] ?? '') . '<br><small>' . htmlspecialchars((string) ($r['proveedor_ruc'] ?? '')) . '</small>', ''],
            'tipo'             => [$txt($r['tipo_comprobante_nombre'] ?? ''), 'text-center'],
            'usuario'          => [$txt($r['usuario_nombre'] ?? ''), ''],
            'autorizacion'     => [$txt($r['numero_autorizacion'] ?? ''), ''],
            'comprobantes'     => [(string) (int) ($r['cantidad_comprobantes'] ?? 0), 'text-center'],
            'producto'         => [$txt($r['producto_nombre'] ?? ''), ''],
            'cantidad'         => [(string) (float) ($r['cantidad_comprada'] ?? 0), 'text-center'],
            'tarifa'           => [htmlspecialchars((string) ($r['tarifa_iva'] ?? 0)) . '%', 'text-center'],
            'fecha'            => [$fecha($r['fecha'] ?? ''), 'text-center'],
            'mes'              => [self::formatearMes((string) ($r['mes'] ?? '')), 'text-center'],
            'total'            => ['<strong>' . $num('total') . '</strong>', 'text-end'],
            'base_0', 'base_iva', 'valor_iva', 'valor_ice', 'retenciones'
                               => [$num($clave), 'text-end'],
            default            => ['', ''],
        };
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
        $agrupar   = (string) $filtros['agrupar_por'];

        $rows    = $this->filasReporte($idsEmpresa, $filtros);
        $totales = $this->repository->getEstadisticas($idsEmpresa, $filtros);

        // Columnas y orientación según la personalización de la empresa (por defecto: horizontal).
        $vista      = $this->columnasVista($agrupar);
        $columnas   = $vista['pdf'];
        $horizontal = (bool) $vista['horizontal'];
        $conEstab   = $consolidado && $this->vistaDe($agrupar) === 'NINGUNO';

        // Ancho en % de cada columna (repartido entre las visibles). Va repetido en cada
        // <th>/<td>: es lo único que hace que Html2Pdf respete el ancho.
        $pesos = $conEstab ? ['__estab' => 6.0] : [];
        foreach ($columnas as $clave => $col) {
            $pesos[$clave] = (float) $col['ancho'];
        }
        $w     = ColumnasReporte::anchosPdf($pesos);
        $nCols = count($pesos);
        // En vertical la hoja es un 30 % más angosta: letra y relleno más chicos según las columnas.
        $pt  = $horizontal ? 8.5 : ($nCols > 10 ? 6.5 : ($nCols > 8 ? 7.0 : 8.0));
        $pad = $horizontal ? 5 : 3;

        // Fila de totales: la etiqueta ocupa las columnas que van antes del primer importe.
        $lider  = 0;
        $wLider = 0.0;
        foreach ($pesos as $clave => $_) {
            if (($columnas[$clave]['stat'] ?? null) !== null) {
                break;
            }
            $lider++;
            $wLider += $w[$clave];
        }
        $hayTotales = $lider < $nCols;
        $saltar     = $lider - ($conEstab ? 1 : 0);   // columnas del catálogo que cubre la etiqueta

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE COMPRAS';
            $alcanceTxt    = $this->describirAlcance($idsEmpresa, $consolidado);

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: <?= $pt ?>pt; margin: 0 auto 20px auto; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: <?= $pad ?>px; text-align: center; }
                td { border: 1px solid #ccc; padding: <?= $pad ?>px; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 20px; }
            </style>
            <?php // footer="page" dibuja "Página x/y" al pie; backbottom reserva ese espacio
                  // para que la última fila de cada hoja no quede debajo del número. ?>
            <page backbottom="7mm" footer="page">
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Reporte de Compras</h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
                <?php if ($alcanceTxt !== ''): ?><p><strong>Alcance:</strong> <?= htmlspecialchars($alcanceTxt) ?></p><?php endif; ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <?php if ($conEstab): ?><th style="width:<?= $w['__estab'] ?>%;">Estab.</th><?php endif; ?>
                        <?php foreach ($columnas as $clave => $col): ?><th style="width:<?= $w[$clave] ?>%;"><?= htmlspecialchars($col['corto']) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php if ($conEstab): ?><td class="text-center" style="width:<?= $w['__estab'] ?>%;"><?= htmlspecialchars((string) ($r['establecimiento'] ?? '')) ?></td><?php endif; ?>
                            <?php foreach ($columnas as $clave => $col):
                                [$valor, $cls] = $this->celdaPdf((string) $clave, $r, ReportePdf::anchoPt($w[$clave], $horizontal), $pt); ?>
                                <td class="<?= $cls ?>" style="width:<?= $w[$clave] ?>%;"><?= $valor ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($hayTotales): ?>
                <tfoot>
                    <tr style="background-color: #e9ecef;">
                        <?php if ($lider > 0): ?><th colspan="<?= $lider ?>" class="text-center" style="width:<?= $wLider ?>%;font-size: <?= $pt + 1.5 ?>pt;">TOTALES:</th><?php endif; ?>
                        <?php $i = 0; foreach ($columnas as $clave => $col):
                            if ($i++ < $saltar) { continue; }
                            $stat = $col['stat']; ?>
                            <?php if ($stat === null): ?>
                                <th class="text-end" style="width:<?= $w[$clave] ?>%;">-</th>
                            <?php elseif ($stat === 'gran_total'): ?>
                                <th class="text-end" style="width:<?= $w[$clave] ?>%;color:#dc3545;font-weight:bold;">$<?= number_format((float) ($totales[$stat] ?? 0), 2) ?></th>
                            <?php else: ?>
                                <th class="text-end" style="width:<?= $w[$clave] ?>%;"><?= number_format((float) ($totales[$stat] ?? 0), 2) ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            </page>
            <?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf($horizontal ? 'L' : 'P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteCompras_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }
}
