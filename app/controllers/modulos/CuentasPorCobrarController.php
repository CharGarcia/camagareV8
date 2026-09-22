<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\IdentificacionTercero;
use App\repositories\modulos\CuentasPorCobrarRepository;
use App\repositories\modulos\EmpresaRepository;
use App\services\WhatsappService;
use App\Services\LogSistemaService;
use PDO;

class CuentasPorCobrarController extends BaseModuloController
{
    /** Registrar un cobro emite un ingreso: exige además permiso de crear en Ingresos. */
    private const RUTA_INGRESOS = 'modulos/ingresos';

    /** El historial de cobros se ofrece a quien puede ver el Reporte de cartera. */
    private const RUTA_CARTERA = 'modulos/reporte_cartera';

    private CuentasPorCobrarRepository $repo;

    protected function getRutaModulo(): string
    {
        return 'modulos/cuentas_por_cobrar';
    }

    private LogSistemaService $log;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new CuentasPorCobrarRepository();
        $this->log  = new LogSistemaService();
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS JSON
    // ─────────────────────────────────────────────────────────────────────

    private function jsonSuccess(array $data): never
    {
        $this->json(array_merge(['ok' => true], $data));
    }

    private function jsonError(string $mensaje, int $code = 200): never
    {
        $this->json(['ok' => false, 'error' => $mensaje], $code);
    }

    // ─────────────────────────────────────────────────────────────────────
    // VISTA PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $anios        = $this->repo->getAniosDisponibles($idEmpresa);
        $tieneWA      = $this->repo->tieneWhatsappConfigurado($idEmpresa);
        // Catálogo para el filtro Vendedor (incluye inactivos: pueden tener cartera pendiente).
        // Restringido (§6): el filtro queda fijo en su propio vendedor (o vacío si no es
        // vendedor) y el catálogo de asesores no se manda al HTML.
        $alcance      = $this->alcanceUsuario([$idEmpresa]);
        $vendedorFijo = \App\Helpers\AlcanceRegistros::restringe($alcance);
        $vendedores   = $vendedorFijo
            ? \App\Helpers\AlcanceRegistros::vendedorPropio($alcance, $idEmpresa, (int) $_SESSION['id_usuario'])
            : ((new \App\repositories\modulos\VendedorRepository())
                ->getListado($idEmpresa, '', 1, 0, 'nombre', 'ASC')['rows'] ?? []);
        $prefsVista   = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        // Consolidado por RUC (fase 1, SOLO LECTURA): el selector de alcance aparece únicamente
        // si la empresa activa es la matriz del grupo y el usuario tiene acceso a al menos otro
        // establecimiento del mismo RUC (ver EmpresaRepository::getIdsConsolidadoDesdeMatriz).
        $empresaRepo      = new EmpresaRepository();
        $idsConsolidado   = $empresaRepo->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
        $establecimientos = $idsConsolidado ? $empresaRepo->getEtiquetasEstablecimiento($idsConsolidado) : [];

        $this->viewWithLayout('layouts.main', 'modulos/cuentas_por_cobrar/index', [
            'titulo'      => 'Cuentas por Cobrar',
            'perm'        => $this->getPermisos(),
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'anios'       => $anios,
            'tieneWA'     => $tieneWA,
            'vendedores'  => $vendedores,
            'vendedorFijo' => $vendedorFijo,
            // Orden guardado por el usuario al hacer clic en las cabeceras. Si la columna
            // no es de este módulo, el repositorio la descarta y usa su orden por defecto.
            'ordenCol'    => (string) ($prefsVista['__ordenCol__'] ?? ''),
            'ordenDir'    => strtoupper((string) ($prefsVista['__ordenDir__'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
            'puedeConsolidar'  => !empty($idsConsolidado),
            'establecimientos' => $establecimientos,
            'idEmpresa'        => $idEmpresa,
            // Botones de cada fila: el historial y el cobro solo aparecen si el usuario los
            // puede usar (el servidor valida lo mismo en historialCobros*Ajax y
            // empresaEscritura()). El cobro se decide además por fila (`puede_operar`).
            'puedeHistorial'   => \App\Helpers\Permisos::puedeVer(self::RUTA_CARTERA),
            'puedeCobrar'      => $this->puedeRegistrarCobro($idEmpresa),
            'fullWidth'   => true,
            'base'        => BASE_URL,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – LISTADO PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    public function generarAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $filtros = $this->getFiltros();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);

        $filas      = $this->getFilasUnificadas($idsEmpresa, $filtros);
        $stats      = $this->repo->getEstadisticas($idsEmpresa, $filtros);
        $antiguedad = $this->repo->getAntiguedad($idsEmpresa, $filtros);

        // Formateamos filas
        $puedeCobrarEn = [];
        foreach ($filas as &$f) {
            $f['total']        = number_format((float)$f['total'],        2, '.', '');
            $f['total_cobrado']= number_format((float)$f['total_cobrado'],2, '.', '');
            $f['saldo']        = number_format((float)$f['saldo'],        2, '.', '');
            $f['dias_vencido'] = (int)($f['dias_vencido'] ?? 0);
            // Columna "Días": antigüedad del documento (emisión → Fecha Hasta del filtro).
            // `dias_vencido` sigue decidiendo el estado y el color; no son lo mismo.
            $f['dias_transcurridos'] = (int)($f['dias_transcurridos'] ?? 0);
            // Consolidado: los documentos de OTRO establecimiento son solo lectura
            // (sin cobro, correo ni WhatsApp desde aquí). La vista lo usa para
            // deshabilitar esas acciones y mostrar el badge del establecimiento.
            $f['id_empresa'] = (int)($f['id_empresa'] ?? $idEmpresa);
            $f['es_hermana'] = $f['id_empresa'] !== $idEmpresa;
            // ¿Se puede cobrar desde aquí? Exige crear en este módulo y en Ingresos en la
            // empresa dueña del documento: en el consolidado (fase 2) el ingreso se registra
            // en los libros de la hermana. Se resuelve una vez por establecimiento; la vista
            // oculta el botón de cobro cuando es false.
            $puedeCobrarEn[$f['id_empresa']] ??= $this->puedeRegistrarCobro($f['id_empresa']);
            $f['puede_operar'] = $puedeCobrarEn[$f['id_empresa']];
        }
        unset($f);

        // Vista "Por producto": líneas de producto de los documentos listados (solo cuando la
        // vista las pide, para no cargar el detalle en cada consulta normal).
        $lineas = null;
        if (!empty($_REQUEST['incluir_lineas'])) {
            $lineas = $this->lineasProductoDe($filas, $filtros);
        }

        $this->jsonSuccess([
            'filas'            => $filas,
            'stats'            => $stats,
            'antiguedad'       => $antiguedad,
            'consolidado'      => $consolidado,
            'establecimientos' => count($idsEmpresa),
            'lineas'           => $lineas,
        ]);
    }

    /** Líneas de producto de las facturas y recibos del listado (ver repo->getLineasProductoPorDocumentos). */
    private function lineasProductoDe(array $filas, array $filtros): array
    {
        $idsF = [];
        $idsR = [];
        foreach ($filas as $f) {
            if (($f['origen'] ?? '') === 'FACTURA') {
                $idsF[] = (int)$f['id'];
            } elseif (($f['origen'] ?? '') === 'RECIBO') {
                $idsR[] = (int)$f['id'];
            }
        }
        return $this->repo->getLineasProductoPorDocumentos($idsF, $idsR, $filtros);
    }

    /**
     * Agrupa el listado por producto (vista "Por producto" en PDF/Excel): un grupo por código
     * (o nombre si no hay código) con cantidad, valor del producto y los documentos que lo
     * contienen, cada uno con su total, cobrado y saldo. Un documento con varios productos
     * aparece en cada uno de ellos; los saldos iniciales no tienen líneas y quedan fuera.
     */
    private function agruparPorProducto(array $filas, array $filtros): array
    {
        $porKey = [];
        foreach ($filas as $f) {
            $porKey[($f['origen'] ?? 'FACTURA') . ':' . (int)$f['id']] = $f;
        }
        $grupos = [];
        foreach ($this->lineasProductoDe($filas, $filtros) as $l) {
            $keyDoc = $l['origen'] . ':' . $l['id_doc'];
            if (!isset($porKey[$keyDoc])) {
                continue;
            }
            $pk = $l['codigo'] !== '' ? 'c:' . $l['codigo'] : 'n:' . $l['nombre'];
            if (!isset($grupos[$pk])) {
                $grupos[$pk] = ['codigo' => $l['codigo'], 'nombre' => $l['nombre'], 'cantidad' => 0.0, 'valor' => 0.0,
                                'total' => 0.0, 'cobrado' => 0.0, 'saldo' => 0.0, 'docs' => []];
            }
            $g = &$grupos[$pk];
            $g['cantidad'] += $l['cantidad'];
            $g['valor']    += $l['valor'];
            if (!isset($g['docs'][$keyDoc])) {
                $r = $porKey[$keyDoc];
                $cobrado = (float)($r['total_cobrado'] ?? 0) + (float)($r['total_retenido'] ?? 0) + (float)($r['total_nc'] ?? 0);
                $g['docs'][$keyDoc] = ['fila' => $r, 'cantidad' => 0.0, 'valor' => 0.0];
                $g['total']   += (float)$r['total'];
                $g['cobrado'] += $cobrado;
                $g['saldo']   += (float)$r['saldo'];
            }
            $g['docs'][$keyDoc]['cantidad'] += $l['cantidad'];
            $g['docs'][$keyDoc]['valor']    += $l['valor'];
            unset($g);
        }
        // Productos en orden alfabético (sin distinguir mayúsculas ni tildes), como en pantalla.
        usort($grupos, static fn ($a, $b) => strcmp(
            \App\Helpers\OrdenFilas::normalizar((string)$a['nombre']),
            \App\Helpers\OrdenFilas::normalizar((string)$b['nombre'])
        ));
        return array_values($grupos);
    }

    /**
     * Excel de la vista "Por producto": UNA fila por producto con sus totales (documentos,
     * cantidad, valor del producto, total, cobrado y saldo de los documentos que lo
     * contienen), sin el detalle de documentos ni clientes — igual que un resumen agrupado.
     */
    private function exportExcelPorProducto(int $idEmpresa, array $idsEmpresa, bool $consolidado, array $filtros, array $filas): void
    {
        $grupos = $this->agruparPorProducto($filas, $filtros);
        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';
            $filtrosTxt    = ['Vista' => 'Por producto (resumen)'] + $this->describirFiltros($idsEmpresa, $filtros);

            $headers  = ['Código', 'Producto', 'Documentos', 'Cantidad', 'Valor Producto', 'Total Documentos', 'Cobrado', 'Saldo'];
            $formatos = array_fill_keys([4, 5, 6, 7, 8], '0.00');
            $exportData = [];
            foreach ($grupos as $g) {
                $exportData[] = [
                    $g['codigo'], $g['nombre'], count($g['docs']),
                    round($g['cantidad'], 2), round($g['valor'], 2),
                    round($g['total'], 2), round($g['cobrado'], 2), round($g['saldo'], 2),
                ];
            }
            (new \App\Services\ReportService())->exportToExcel('cuentas_por_cobrar_producto', $headers, $exportData, 'Cuentas por Cobrar por Producto', $nombreEmpresa, $filtrosTxt, $formatos);
            exit;
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                $_SESSION['cuentas_por_cobrar_msg'] = ['danger', 'Error al generar Excel: ' . $e->getMessage()];
                $this->redirect(BASE_URL . '/' . $this->getRutaModulo());
            }
            exit;
        }
    }

    /**
     * PDF de la vista "Por producto": UNA fila por producto con sus totales, sin el detalle
     * de documentos ni clientes (mismo criterio que un resumen agrupado por cliente).
     */
    private function exportPdfPorProducto(int $idEmpresa, array $idsEmpresa, bool $consolidado, array $filtros, array $filas): void
    {
        $grupos = $this->agruparPorProducto($filas, $filtros);
        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';
            $filtrosTxt    = $this->describirFiltros($idsEmpresa, $filtros);
            $e = static fn ($v): string => htmlspecialchars((string)$v);

            $totalValor = 0.0; $totalSaldo = 0.0; $totalCobrado = 0.0; $totalDocs = 0.0; $totalCant = 0.0;
            $cuerpo = '';
            foreach ($grupos as $g) {
                $totalValor   += $g['valor'];
                $totalSaldo   += $g['saldo'];
                $totalCobrado += $g['cobrado'];
                $totalDocs    += $g['total'];
                $totalCant    += $g['cantidad'];
                // Html2Pdf no parte palabras largas por sí solo ni respeta table-layout:fixed
                // sin ancho en cada celda: se fija el ancho por <td> (mismas proporciones que
                // el <thead>) y se insertan cortes en nombres/códigos sin espacios para que la
                // celda haga multilínea en vez de desbordar la hoja por la derecha.
                $nombrePdf = wordwrap($g['nombre'], 34, "\n", true);
                $codigoPdf = wordwrap($g['codigo'], 14, "\n", true);
                $cuerpo .= "<tr>
                    <td style='width:11%;'>" . nl2br($e($codigoPdf)) . "</td>
                    <td style='width:33%;'>" . nl2br($e($nombrePdf)) . "</td>
                    <td class='text-center' style='width:7%;'>" . count($g['docs']) . "</td>
                    <td class='text-end' style='width:9%;'>" . number_format($g['cantidad'], 2) . "</td>
                    <td class='text-end' style='width:10%;'>\$" . number_format($g['valor'], 2) . "</td>
                    <td class='text-end' style='width:10%;'>\$" . number_format($g['total'], 2) . "</td>
                    <td class='text-end' style='width:10%;'>\$" . number_format($g['cobrado'], 2) . "</td>
                    <td class='text-end' style='width:10%;font-weight:bold;'>\$" . number_format($g['saldo'], 2) . "</td>
                </tr>";
            }

            ob_start();
            ?>
            <style>
                body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
                th { background: #e9ecef; border: 1px solid #999; padding: 3px 3px; text-align: center; font-size: 8.5pt; }
                td { border: 1px solid #999; padding: 2px 3px; font-size: 8pt; overflow: hidden; word-wrap: break-word; }
                .text-end { text-align: right; } .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 10px; }
                .header h2 { margin: 0 0 2px 0; font-size: 13pt; } .header h3 { margin: 0 0 2px 0; font-size: 10pt; } .header p { margin: 0; font-size: 7.5pt; }
                <?= self::CSS_FILTROS_PDF ?>
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm">
            <?= $this->encabezadoPdf($idEmpresa, $nombreEmpresa, 'Cuentas por Cobrar por Producto') ?>
            <?= $this->bloqueFiltrosPdf($filtrosTxt) ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:11%;">Código</th>
                        <th style="width:33%;">Producto</th>
                        <th style="width:7%;">Docs.</th>
                        <th style="width:9%;">Cantidad</th>
                        <th style="width:10%;">Valor Prod.</th>
                        <th style="width:10%;">Total Docs.</th>
                        <th style="width:10%;">Cobrado</th>
                        <th style="width:10%;">Saldo</th>
                    </tr>
                </thead>
                <tbody><?= $cuerpo ?: "<tr><td colspan='8' class='text-center' style='width:100%;'>Sin documentos con líneas de producto para los filtros aplicados.</td></tr>" ?></tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:bold;">
                        <td colspan="3" class="text-end" style="width:51%;">TOTALES (<?= count($grupos) ?> productos):</td>
                        <td class="text-end" style="width:9%;"><?= number_format($totalCant, 2) ?></td>
                        <td class="text-end" style="width:10%;">$<?= number_format($totalValor, 2) ?></td>
                        <td class="text-end" style="width:10%;">$<?= number_format($totalDocs, 2) ?></td>
                        <td class="text-end" style="width:10%;">$<?= number_format($totalCobrado, 2) ?></td>
                        <td class="text-end" style="width:10%;">$<?= number_format($totalSaldo, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
            <p style="font-size:7pt;color:#555;">Total Docs., Cobrado y Saldo corresponden a los documentos que contienen cada producto; un documento con varios productos cuenta en cada uno, por lo que estas columnas no se suman entre productos.</p>
            </page>
            <?php
            $html     = ob_get_clean();
            // Vertical (A4 retrato): es la orientación por defecto de los listados del módulo.
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('CuentasPorCobrar_Producto_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    /**
     * Agrupa el listado por cliente para las exportaciones de la vista "Por cliente"
     * (formato mayor). La clave es la identificación BASE, no el texto del RUC: el
     * contribuyente registrado dos veces —con la cédula y con el RUC, que es esa cédula +
     * '001'— cae en un solo grupo, igual que en la vista en pantalla. Dentro de cada cliente
     * los documentos van en orden cronológico (como los movimientos de un mayor); los
     * clientes salen en orden alfabético (A-Z), igual que el listado detallado.
     */
    private function agruparPorCliente(array $filas): array
    {
        $grupos = [];
        foreach ($filas as $r) {
            $nombre = trim((string)($r['cliente_nombre'] ?? ''));
            if ($nombre === '') {
                $nombre = 'Sin cliente';
            }
            $key = IdentificacionTercero::claveGrupo($r['cliente_ruc'] ?? null, $nombre);
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'nombre' => $nombre, 'ruc' => (string)($r['cliente_ruc'] ?? ''), 'items' => [],
                    'total' => 0.0, 'abonos' => 0.0, 'nc' => 0.0, 'retenciones' => 0.0,
                    'cobrado' => 0.0, 'saldo' => 0.0,
                ];
            }
            $abonos = (float)($r['total_cobrado'] ?? 0);
            $nc     = (float)($r['total_nc'] ?? 0);
            $ret    = (float)($r['total_retenido'] ?? 0);

            $g = &$grupos[$key];
            $g['items'][]     = $r;
            $g['total']       += (float)($r['total'] ?? 0);
            $g['abonos']      += $abonos;
            $g['nc']          += $nc;
            $g['retenciones'] += $ret;
            $g['cobrado']     += $abonos + $nc + $ret;
            $g['saldo']       += (float)($r['saldo'] ?? 0);
            unset($g);
        }
        foreach ($grupos as &$g) {
            usort($g['items'], static fn (array $a, array $b): int =>
                strcmp((string)($a['fecha_emision'] ?? ''), (string)($b['fecha_emision'] ?? ''))
                    ?: strcmp((string)($a['numero_factura'] ?? ''), (string)($b['numero_factura'] ?? '')));
        }
        unset($g);
        // Clientes en orden alfabético (mismas reglas que el listado: sin distinguir
        // mayúsculas ni tildes), como se ven en pantalla.
        usort($grupos, static fn (array $a, array $b): int => strcmp(
            \App\Helpers\OrdenFilas::normalizar($a['nombre']),
            \App\Helpers\OrdenFilas::normalizar($b['nombre'])
        ));
        return array_values($grupos);
    }

    /**
     * Excel de la vista "Por cliente": misma estructura que el mayor de una cuenta contable —
     * una sección por cliente (subtítulo con su identificación y nombre, más los encabezados
     * repetidos), sus documentos, una fila de SUBTOTAL al cerrar la sección y un TOTAL GENERAL
     * al final de la hoja. El cliente no va como columna: es el título de la sección, y el
     * detalle es el mismo que se ve en pantalla dentro de cada cliente (fecha, documento,
     * total, NC, abonos, retenciones, saldo, días y asesor).
     */
    private function exportExcelPorCliente(int $idEmpresa, array $idsEmpresa, bool $consolidado, array $filtros, array $filas): void
    {
        $grupos = $this->agruparPorCliente($filas);
        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';
            $filtrosTxt    = ['Vista' => 'Por cliente (formato mayor)'] + $this->describirFiltros($idsEmpresa, $filtros);

            // Con el listado acotado a un asesor, su columna repetiría el mismo nombre en
            // todas las filas (ya va en el resumen de filtros de arriba) y se omite.
            $sinAsesor = $this->filtraPorVendedor($filtros);

            $headers = ['Fecha', 'N. Documento', 'Origen', 'Total', 'NC', 'Abonos', 'Retenciones',
                        'Saldo', 'Días', ...($sinAsesor ? [] : ['Asesor']), 'Estado'];
            if ($consolidado) {
                array_unshift($headers, 'Estab.');
            }
            // La etiqueta de SUBTOTAL/TOTAL va en la última columna de texto antes de los
            // importes (igual que en el mayor): a su izquierda quedan celdas vacías.
            $huecos = $consolidado ? 3 : 2;

            $secciones  = [];
            $totTotal   = 0.0;
            $totNc      = 0.0;
            $totAbonos  = 0.0;
            $totRet     = 0.0;
            $totSaldo   = 0.0;

            foreach ($grupos as $g) {
                $totTotal  += $g['total'];
                $totNc     += $g['nc'];
                $totAbonos += $g['abonos'];
                $totRet    += $g['retenciones'];
                $totSaldo  += $g['saldo'];

                $filasSec = [];
                foreach ($g['items'] as $r) {
                    $dias  = (int)($r['dias_vencido'] ?? 0);
                    $saldo = (float)($r['saldo'] ?? 0);
                    $filasSec[] = [
                        ...($consolidado ? [(string)($r['establecimiento'] ?? '')] : []),
                        $r['fecha_emision'] ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                        (string)($r['numero_factura'] ?? ''),
                        $this->getOrigenLabel($r['origen'] ?? 'FACTURA'),
                        round((float)($r['total'] ?? 0), 2),
                        round((float)($r['total_nc'] ?? 0), 2),
                        round((float)($r['total_cobrado'] ?? 0), 2),
                        round((float)($r['total_retenido'] ?? 0), 2),
                        round($saldo, 2),
                        max(0, (int)($r['dias_transcurridos'] ?? 0)),
                        ...($sinAsesor ? [] : [(string)($r['vendedor_nombre'] ?? '')]),
                        $saldo <= 0 ? 'PAGADA' : ($dias > 0 ? "VENCIDA ({$dias} días)" : 'VIGENTE'),
                    ];
                }

                // Título de la sección: "RUC - NOMBRE · saldo: 1,234.56", igual que en el PDF.
                $titulo = trim(($g['ruc'] !== '' ? $g['ruc'] . ' - ' : '') . $g['nombre'])
                        . ' · saldo: ' . number_format($g['saldo'], 2);

                // Sin fila de SUBTOTAL por cliente: el saldo ya va en el título de la
                // sección y el resumen de toda la cartera queda en el TOTAL GENERAL.
                $secciones[] = [
                    'titulo' => $titulo,
                    'filas'  => $filasSec,
                ];
            }

            $filaFinal = [
                ...array_fill(0, $huecos, ''),
                'TOTAL GENERAL (' . count($grupos) . ' cliente' . (count($grupos) !== 1 ? 's' : '') . ')',
                round($totTotal, 2), round($totNc, 2), round($totAbonos, 2),
                round($totRet, 2), round($totSaldo, 2),
                // Días (+ Asesor, si va) + Estado: sin valor en el total general
                ...array_fill(0, $sinAsesor ? 2 : 3, ''),
            ];

            (new \App\Services\ReportService())->exportToExcelSeccionado(
                'cuentas_por_cobrar_cliente',
                $headers,
                $secciones,
                'CxC por Cliente',
                $nombreEmpresa . ' - Cuentas por Cobrar por Cliente',
                $filaFinal,
                $filtrosTxt
            );
            exit;
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                $_SESSION['cuentas_por_cobrar_msg'] = ['danger', 'Error al generar Excel: ' . $e->getMessage()];
                $this->redirect(BASE_URL . '/' . $this->getRutaModulo());
            }
            exit;
        }
    }

    /**
     * PDF de la vista "Por cliente": el listado sale como el mayor de una cuenta contable —
     * una sección por cliente (cabecera con su identificación y nombre), la tabla de sus
     * documentos en orden cronológico con el mismo detalle que la pantalla (fecha, documento,
     * total, NC, abonos, retenciones, saldo, días y asesor), una fila de SUBTOTAL al cerrar la
     * sección y, al final, el TOTAL GENERAL de la cartera.
     */
    private function exportPdfPorCliente(int $idEmpresa, array $idsEmpresa, bool $consolidado, array $filtros, array $filas): void
    {
        $grupos = $this->agruparPorCliente($filas);
        $stats  = $this->repo->getEstadisticas($idsEmpresa, $filtros);
        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';
            $filtrosTxt    = $this->describirFiltros($idsEmpresa, $filtros);
            $e = static fn ($v): string => htmlspecialchars((string)$v);

            // Con el listado acotado a un asesor, su columna repetiría el mismo nombre en
            // todas las filas (ya va en el resumen de filtros de arriba) y se omite.
            $sinAsesor = $this->filtraPorVendedor($filtros);

            // Anchos por columna (table-layout: fixed, deben sumar 100%). La columna del
            // establecimiento solo aparece en consolidado y le resta ancho al asesor; sin
            // asesor, su ancho pasa entero a "N. Documento".
            $wEst   = $consolidado ? 6 : 0;
            $wAse   = $sinAsesor ? 0 : 20 - $wEst;
            $wDoc   = 17 + ($sinAsesor ? 20 - $wEst : 0);
            $wEtq   = 9 + $wDoc + $wEst;         // Fecha + N. Documento (+ Estab.): etiqueta del TOTAL GENERAL

            $totTotal  = 0.0;
            $totNc     = 0.0;
            $totAbonos = 0.0;
            $totRet    = 0.0;
            $totSaldo  = 0.0;
            $cuerpo    = '';

            // Todo el listado va en UNA sola tabla y cada cliente es una fila de cabecera
            // (colspan) dentro de ella. No es cosmético: con una tabla por cliente, cuando
            // una de ellas se abría en el último centímetro de la hoja, Html2Pdf dibujaba su
            // <thead> al pie SIN contarlo al decidir si la primera fila cabía (la condición
            // de Html2Pdf::_tag_open_TR suma el tfoot, no el thead), así que esa fila se
            // partía y sus celdas se repartían entre dos o tres páginas, dejando renglones
            // sueltos al pie y hojas casi en blanco — las "líneas montadas" del reporte.
            // Con una sola tabla, el <thead> solo se dibuja arriba de cada página (donde
            // siempre hay sitio), se repite en TODAS las páginas y ninguna fila se parte.
            // El colspan de la cabecera de cliente no descuadra los anchos porque la primera
            // fila de la tabla sigue siendo el <thead> con el ancho de cada columna.
            $nCols  = 8 + ($consolidado ? 1 : 0) + ($sinAsesor ? 0 : 1);
            $thCols = ($consolidado ? "<th style='width:{$wEst}%;'>Estab.</th>" : '')
                . "<th style='width:9%;'>Fecha</th>"
                . "<th style='width:{$wDoc}%;'>N. Documento</th>"
                . "<th style='width:10%;'>Total</th>"
                . "<th style='width:9%;'>NC</th>"
                . "<th style='width:10%;'>Abonos</th>"
                . "<th style='width:10%;'>Retenciones</th>"
                . "<th style='width:10%;'>Saldo</th>"
                . "<th style='width:5%;'>Días</th>"
                . ($sinAsesor ? '' : "<th style='width:{$wAse}%;'>Asesor</th>");

            foreach ($grupos as $g) {
                $totTotal  += $g['total'];
                $totNc     += $g['nc'];
                $totAbonos += $g['abonos'];
                $totRet    += $g['retenciones'];
                $totSaldo  += $g['saldo'];

                $primerCliente = ($cuerpo === '');
                if ($primerCliente) {
                    $cuerpo .= "<table class='cli'><thead><tr>{$thCols}</tr></thead><tbody>";
                } else {
                    // Hueco entre un cliente y el siguiente (antes era el margin-bottom de la
                    // tabla de cada cliente): fila vacía sin bordes, de la misma altura.
                    $cuerpo .= "<tr class='sep'><td colspan='{$nCols}'>&nbsp;</td></tr>";
                }

                // Cabecera de la sección: "NOMBRE · saldo: 1,234.56". Sin el RUC delante:
                // quien lee el reporte identifica al cliente por el nombre, y el número
                // solo le robaba ancho a la línea. Lo que interesa es cuánto debe, no
                // cuántos documentos tiene.
                $titulo = trim((string) $g['nombre']);
                $cuerpo .= "<tr class='grp'><td colspan='{$nCols}'>"
                    . $e($titulo) . " &nbsp;&middot;&nbsp; saldo: " . number_format($g['saldo'], 2)
                    . "</td></tr>";

                // Encabezado de columnas bajo CADA cliente. Con una sola tabla, el <thead>
                // solo se dibuja al principio de cada página, así que el cliente que empieza
                // a media hoja quedaba con sus documentos sin rótulo de columnas. Va como
                // fila normal del <tbody> con celdas <th> (hereda el mismo estilo): NO como
                // un segundo <thead>, porque volver a abrir <thead> a media tabla reactiva
                // el corte de filas entre páginas que se arregló al unificar la tabla.
                // El primero no lo lleva: el <thead> de la tabla está justo encima.
                if (!$primerCliente) {
                    $cuerpo .= "<tr class='cab'>{$thCols}</tr>";
                }

                foreach ($g['items'] as $r) {
                    $dias   = (int)($r['dias_vencido'] ?? 0);
                    $ts     = (float)($r['total'] ?? 0);
                    $nc     = (float)($r['total_nc'] ?? 0);
                    $nd     = (float)($r['total_nd'] ?? 0);
                    $abonos = (float)($r['total_cobrado'] ?? 0);
                    $ret    = (float)($r['total_retenido'] ?? 0);
                    $tsal   = (float)($r['saldo'] ?? 0);
                    $color  = $dias > 0 && $tsal > 0 ? 'color:#dc3545;' : '';
                    $fEmis  = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                    // La nota de débito suma al documento: se avisa junto al total para que
                    // total − NC − abonos − retenciones siga cuadrando con el saldo.
                    $ndTxt  = $nd > 0 ? " <small>+" . number_format($nd, 2) . "</small>" : '';
                    $tipo   = ($r['origen'] ?? 'FACTURA') === 'FACTURA' ? '' : "<small style='color:#6c757d;'>" . $this->getOrigenLabel($r['origen'] ?? '') . "</small><br>";
                    $cuerpo .= "<tr>"
                        . ($consolidado ? "<td class='text-center' style='width:{$wEst}%;'>" . $e($r['establecimiento'] ?? '') . "</td>" : '')
                        . "<td class='text-center' style='width:9%;'>{$fEmis}</td>"
                        . "<td style='width:{$wDoc}%;'>{$tipo}" . $e($r['numero_factura'] ?? '') . "</td>"
                        . "<td class='text-end' style='width:10%;'>$" . number_format($ts, 2) . "{$ndTxt}</td>"
                        . "<td class='text-end' style='width:9%;'>" . ($nc > 0 ? '$' . number_format($nc, 2) : '—') . "</td>"
                        . "<td class='text-end' style='width:10%;'>" . ($abonos > 0 ? '$' . number_format($abonos, 2) : '—') . "</td>"
                        . "<td class='text-end' style='width:10%;'>" . ($ret > 0 ? '$' . number_format($ret, 2) : '—') . "</td>"
                        . "<td class='text-end' style='width:10%;{$color}font-weight:bold;'>$" . number_format($tsal, 2) . "</td>"
                        // Días = antigüedad (emisión → Fecha Hasta); el rojo lo sigue
                        // marcando la mora ($color, de dias_vencido).
                        . "<td class='text-center' style='width:5%;{$color}'>" . max(0, (int)($r['dias_transcurridos'] ?? 0)) . "</td>"
                        . ($sinAsesor ? '' : "<td style='width:{$wAse}%;'>" . $e($r['vendedor_nombre'] ?? '') . "</td>")
                        . "</tr>";
                }

                // Sin fila de SUBTOTAL por cliente: el saldo ya va en la cabecera de la
                // sección y el resumen de toda la cartera queda en el TOTAL GENERAL.
            }
            if ($cuerpo !== '') {
                $cuerpo .= '</tbody></table>';
            }

            ob_start();
            ?>
            <style>
                body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 6px; table-layout: fixed; }
                th { background: #e9ecef; border: 1px solid #999; padding: 3px 3px; text-align: center; font-size: 8.5pt; color: #000; }
                td { border: 1px solid #999; padding: 2px 3px; font-size: 8pt; overflow: hidden; word-wrap: break-word; color: #000; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 10px; }
                .header h2 { margin: 0 0 2px 0; font-size: 13pt; }
                .header h3 { margin: 0 0 2px 0; font-size: 10pt; }
                .header p  { margin: 0; font-size: 7.5pt; }
                table.cli { margin-bottom: 12px; }
                /* Cabecera de cada cliente: fila de la misma tabla, con todo el ancho. */
                tr.grp td { background: #eafaf1; border: 1px solid #ccc; font-weight: bold; font-size: 8.5pt; padding: 4px 5px; }
                /* Separación entre un cliente y el siguiente: fila vacía sin bordes, del
                   mismo alto que el hueco que dejaban antes las tablas sueltas (~25 pt). */
                tr.sep td { border: none; background: #fff; padding: 0; font-size: 9pt; }
                table.tot td { background: #343a40; color: #fff; font-weight: bold; font-size: 8.5pt; border: 1px solid #343a40; }
                table.stats td.stats-box { text-align: center; vertical-align: middle; padding: 6px 4px; border: 1px solid #ccc; }
                .stat-lbl  { font-size: 7.5pt; }
                .stat-val  { font-size: 11pt; font-weight: bold; }
                <?= self::CSS_FILTROS_PDF ?>
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm">
            <?= $this->encabezadoPdf($idEmpresa, $nombreEmpresa, 'Cuentas por Cobrar por Cliente') ?>
            <?= $this->bloqueFiltrosPdf($filtrosTxt) ?>
            <table class="stats">
                <tr>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Facturas</span><br/>
                        <span class="stat-val"><?= $stats['total_facturas'] ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Saldo Total</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_saldo'], 2) ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Vencido</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_vencido'], 2) ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Al Día</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_al_dia'], 2) ?></span>
                    </td>
                </tr>
            </table>
            <?= $cuerpo ?: "<table><tr><td class='text-center' style='width:100%;'>No se encontraron cuentas por cobrar con los filtros aplicados.</td></tr></table>" ?>
            <table class="tot">
                <tr>
                    <td class="text-end" style="width:<?= $wEtq ?>%;">TOTAL GENERAL (<?= count($grupos) ?> cliente<?= count($grupos) !== 1 ? 's' : '' ?>)</td>
                    <td class="text-end" style="width:10%;">$<?= number_format($totTotal, 2) ?></td>
                    <td class="text-end" style="width:9%;">$<?= number_format($totNc, 2) ?></td>
                    <td class="text-end" style="width:10%;">$<?= number_format($totAbonos, 2) ?></td>
                    <td class="text-end" style="width:10%;">$<?= number_format($totRet, 2) ?></td>
                    <td class="text-end" style="width:10%;">$<?= number_format($totSaldo, 2) ?></td>
                    <td style="width:<?= 5 + $wAse ?>%;"></td>
                </tr>
            </table>
            </page>
            <?php
            $html     = ob_get_clean();
            // Vertical (A4 retrato): es la orientación por defecto de los listados del módulo.
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('CuentasPorCobrar_Cliente_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $ex) {
            echo 'Error al generar PDF: ' . $ex->getMessage();
        }
    }

    /**
     * Alcance del listado (fase 1 del consolidado por RUC: SOLO LECTURA desde la matriz).
     * Devuelve [idsEmpresa, consolidado]. El valor `alcance=CONSOLIDADO` que manda la vista
     * solo se honra si la empresa activa es la matriz del grupo RUC y hay hermanas accesibles
     * para el usuario (EmpresaRepository::getIdsConsolidadoDesdeMatriz); en cualquier otro
     * caso se ignora en silencio y el listado queda como siempre (solo la empresa activa).
     * El filtro de cliente SIEMPRE se expande a las demás filas del mismo cliente
     * (expandirClientesPorIdentificacion): dentro de una empresa, al contribuyente
     * registrado dos veces —con la cédula y con el RUC, que es esa cédula + '001'— y,
     * en consolidado, además a sus hermanas de los otros establecimientos, porque
     * `clientes` es una tabla por empresa. Sin eso su cartera saldría partida en dos.
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
        if (!empty($filtros['id_cliente'])) {
            $raw = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $filtros['id_cliente'] = $this->repo->expandirClientesPorIdentificacion($raw, $idsEmpresa);
        }
        if ($consolidado && !empty($filtros['id_producto'])) {
            // `productos` también es por establecimiento: se cruza por código
            $raw = is_array($filtros['id_producto']) ? $filtros['id_producto'] : explode(',', (string)$filtros['id_producto']);
            $filtros['id_producto'] = $this->repo->expandirProductosPorCodigo($raw, $idsEmpresa);
        }
        return [$idsEmpresa, $consolidado];
    }

    /**
     * Empresa sobre la que se consulta un documento (historial, datos para el modal,
     * catálogos de cobro). Por defecto la activa; en la vista consolidada cada fila trae su
     * `id_empresa`, y se acepta únicamente si es una hermana del grupo consolidable desde la
     * matriz (misma regla que el listado). Cualquier otro valor se ignora y se responde por
     * la activa. El correo y el WhatsApp no usan este método a propósito: siguen atados a la
     * empresa activa (usan su configuración de correo y sus plantillas).
     */
    private function empresaLectura(): int
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $pedida    = (int) ($_REQUEST['id_empresa'] ?? 0);
        if ($pedida <= 0 || $pedida === $idEmpresa) {
            return $idEmpresa;
        }
        $grupo = (new EmpresaRepository())->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
        return in_array($pedida, $grupo, true) ? $pedida : $idEmpresa;
    }

    /**
     * Empresa en la que se REGISTRA un cobro (fase 2 del consolidado). Por defecto la activa;
     * si la petición trae el `id_empresa` de una hermana del grupo consolidable desde la
     * matriz, el ingreso se registra en los libros de ESA empresa (su punto de emisión, su
     * secuencial, su cartera y su contabilidad). En la empresa que corresponda se exige
     * permiso de CREAR en este módulo y en Ingresos (puedeRegistrarCobro()): responde 403 si
     * falta alguno. Un id fuera del grupo cae a la empresa activa, donde el documento no
     * existe y el cobro se rechaza.
     */
    private function empresaEscritura(): int
    {
        $idEmpresa = $this->empresaLectura();
        if (!$this->puedeRegistrarCobro($idEmpresa)) {
            $this->json(['ok' => false, 'error' => $idEmpresa !== (int) $_SESSION['id_empresa']
                ? 'No tiene permiso para registrar cobros en ese establecimiento: requiere permiso de crear en Cuentas por Cobrar y en Ingresos.'
                : 'No tiene permiso para registrar cobros: el cobro genera un ingreso y requiere permiso de crear en Cuentas por Cobrar y en Ingresos.'], 403);
        }
        return $idEmpresa;
    }

    /**
     * ¿Puede el usuario registrar cobros en esa empresa? El cobro emite un INGRESO en sus
     * libros, así que además de crear en este módulo exige crear en Ingresos. Es la misma
     * regla con la que la vista muestra u oculta el botón de cobro (`puede_operar`).
     */
    private function puedeRegistrarCobro(int $idEmpresa): bool
    {
        return !empty(\App\Helpers\Permisos::porRutaEnEmpresa($this->getRutaModulo(), $idEmpresa)['crear'])
            && !empty(\App\Helpers\Permisos::porRutaEnEmpresa(self::RUTA_INGRESOS, $idEmpresa)['crear']);
    }

    /**
     * El historial de cobros se ofrece a quien puede ver el Reporte de cartera; la vista
     * oculta el botón con la misma regla. Responde 403 si no tiene ese permiso.
     */
    private function requireHistorialCobros(): void
    {
        if (!\App\Helpers\Permisos::puedeVer(self::RUTA_CARTERA)) {
            $this->json(['ok' => false, 'error' => 'No tiene permiso para consultar el historial de cobros: requiere acceso al Reporte de cartera.'], 403);
        }
    }

    /**
     * Lista unificada de Cuentas por Cobrar: facturas (ventas_cabecera) +
     * recibos de venta (recibos_venta_cabecera) + saldos iniciales por cobrar,
     * en una sola tabla. Cada fila lleva un campo `origen`
     * ('FACTURA' | 'RECIBO' | 'SALDO_INICIAL') para distinguirla y enrutar acciones.
     * El filtro `tipo_doc` (TODOS | FACTURA | RECIBO | SALDO_INICIAL) decide
     * qué orígenes se incluyen. Los saldos iniciales se filtran por el mismo
     * estado/cliente del listado.
     */
    private function getFilasUnificadas(int|array $idEmpresa, array $filtros): array
    {
        $tipoDoc = $filtros['tipo_doc'] ?? 'TODOS';

        // Facturas
        $facturas = [];
        if (in_array($tipoDoc, ['TODOS', 'FACTURA'], true)) {
            $facturas = $this->repo->getListado($idEmpresa, $filtros);
            foreach ($facturas as &$f) { $f['origen'] = 'FACTURA'; }
            unset($f);
        }

        // Recibos de venta (mismas columnas que el listado de facturas)
        $recibos = [];
        if (in_array($tipoDoc, ['TODOS', 'RECIBO'], true)) {
            $recibos = $this->repo->getListadoRecibos($idEmpresa, $filtros);
            foreach ($recibos as &$r) { $r['origen'] = 'RECIBO'; }
            unset($r);
        }

        // Saldos iniciales (todos; se filtran en PHP por el mismo estado del listado).
        // No tienen vendedor: al filtrar por vendedor quedan fuera (ver repo->incluyeSaldosIniciales).
        if (!$this->repo->incluyeSaldosIniciales($filtros)) {
            $saldos = [];
        } else {
            $saldos = $this->repo->getSaldosInicialesCxc($idEmpresa, [
                'estado'      => 'TODOS',
                'id_cliente'  => $filtros['id_cliente'] ?? '',
                'fecha_desde' => $filtros['fecha_desde'] ?? '',
                'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
                // Alcance del usuario (§6): el mismo que aplican facturas y recibos
                'id_vendedor_filtro' => $filtros['id_vendedor_filtro'] ?? [],
                'id_usuario_filtro'  => $filtros['id_usuario_filtro'] ?? null,
            ]);
        }

        $estado = $filtros['estado'] ?? 'PENDIENTES';
        $filasSI = [];
        foreach ($saldos as $s) {
            $pend = (float)$s['saldo_pendiente'];
            $venc = ((int)($s['dias_vencido'] ?? 0)) > 0;
            $incluir = match ($estado) {
                'PENDIENTES' => $pend > 0,
                'VENCIDAS'   => $pend > 0 && $venc,
                'AL_DIA'     => $pend > 0 && !$venc,
                'PAGADAS'    => $pend <= 0,
                default      => true, // TODOS
            };
            if (!$incluir) continue;

            $filasSI[] = [
                'origen'            => 'SALDO_INICIAL',
                'id'                => (int)$s['id'],
                'id_empresa'        => (int)($s['id_empresa'] ?? 0),
                'establecimiento'   => $s['establecimiento'] ?? '',
                'empresa_nombre'    => $s['empresa_nombre'] ?? '',
                'numero_factura'    => $s['nro_documento'],
                'id_cliente'        => $s['id_cliente'] ?? null,
                'cliente_nombre'    => $s['nombre_cliente'],
                'cliente_ruc'       => $s['ruc_cliente'],
                'cliente_email'     => '',
                'cliente_telefono'  => '',
                'vendedor_nombre'   => '', // los saldos iniciales no tienen vendedor
                'fecha_emision'     => $s['fecha_emision'],
                'fecha_vencimiento' => $s['fecha_vencimiento'],
                'total'             => $s['saldo_inicial'],
                'total_cobrado'     => $s['monto_cobrado'],
                'total_retenido'    => $s['monto_retenido'] ?? 0,
                'total_nc'          => $s['monto_nc'] ?? 0,
                'saldo'             => $s['saldo_pendiente'],
                'dias_vencido'      => (int)($s['dias_vencido'] ?? 0),
                'dias_transcurridos'=> (int)($s['dias_transcurridos'] ?? 0),
            ];
        }

        $filas = array_merge($facturas, $recibos, $filasSI);

        // Orden final del listado ya unificado: por defecto alfabético por cliente (A-Z)
        // o el que el usuario eligió en las cabeceras (`orden_col`/`orden_dir`). Al pasar
        // por aquí la pantalla, el Excel y el PDF, los tres salen con el mismo orden.
        return $this->repo->ordenarFilas($filas, $filtros);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – REGISTRAR COBRO
    // ─────────────────────────────────────────────────────────────────────

    public function registrarCobroAjax(): void
    {
        $this->requireCrear();
        $idEmpresa    = $this->empresaEscritura(); // consolidado: cobro en los libros de la hermana dueña
        $idUsuario    = (int) $_SESSION['id_usuario'];

        $idVenta      = (int)($_POST['id_venta']          ?? 0);
        $monto        = (float)($_POST['monto']           ?? 0);
        $idFormaCobro = (int)($_POST['id_forma_cobro']    ?? 0);
        $idPunto      = (int)($_POST['id_punto_emision']  ?? 0);
        $idConcepto   = !empty($_POST['id_ingreso_concepto']) ? (int)$_POST['id_ingreso_concepto'] : null;
        $fechaCobro   = trim($_POST['fecha_cobro']        ?? date('Y-m-d'));
        $observ       = trim($_POST['observaciones']      ?? '');
        $tipoOp       = trim($_POST['tipo_operacion_bancaria'] ?? '');
        $numOp        = trim($_POST['numero_operacion']        ?? '');

        if ($idVenta <= 0 || $monto <= 0 || $idFormaCobro <= 0 || $idPunto <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de cobro.');
            return;
        }

        // Validar punto de emisión
        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('La serie (punto de emisión) no es válida o está inactiva.');
            return;
        }

        // Validar factura y saldo (y que sea del usuario, si no tiene acceso total)
        $factura = $this->facturaPropiaOCortar($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }
        $saldo       = (float)$factura['saldo'];
        $totalFact   = (float)$factura['importe_total'];
        if ($saldo <= 0) {
            $this->jsonError('Esta factura ya se encuentra pagada.');
            return;
        }
        if ($monto > $saldo + 0.001) {
            $this->jsonError("El monto ($monto) supera el saldo pendiente ($saldo).");
            return;
        }

        $db = \App\core\Database::getConnection();
        try {
            // Obtener siguiente secuencial mediante SecuencialService. Se abre la transacción
            // ANTES de calcularlo y se mantiene hasta el INSERT final (IngresoService::crear()):
            // el lock de obtenerSiguienteSecuencial() se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
            $db->beginTransaction();
            $secuencialService = new \App\Services\SecuencialService();
            // Con numeración por fecha de emisión, el cobro numera en el periodo de SU fecha.
            $secRes    = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Ingresos', $fechaCobro ?: date('Y-m-d'));
            $secuencial = $secRes['formateado'];

            $codEst  = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $codPto  = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);
            $numDoc  = "{$codEst}-{$codPto}-{$secuencial}";
            $numFact = ($factura['establecimiento'] ?? '') . '-'
                     . ($factura['punto_emision']   ?? '') . '-'
                     . ($factura['secuencial']       ?? '');

            // Delegar al IngresoService (igual que FacturaVentaController)
            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int)($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => $idPunto,
                'id_cliente'          => (int)$factura['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $fechaCobro ?: date('Y-m-d'),
                'establecimiento'     => $codEst,
                'punto_emision'       => $codPto,
                'secuencial'          => $secuencial,
                'numero_ingreso'      => $numDoc,
                'tipo_ingreso'        => 'FACTURA_VENTA',
                'id_ingreso_concepto' => $idConcepto,
                'monto_total'         => $monto,
                'observaciones'       => $observ ?: "Cobro de factura {$numFact}",
                'recibo_de'           => $factura['cliente_nombre'] ?? '',
                'id_recibo_cliente'   => (int)$factura['id_cliente'],
                'detalles'            => [[
                    'tipo_documento'          => 'FACTURA',
                    'id_referencia_documento' => $idVenta,
                    'numero_documento'        => $numFact,
                    'descripcion'             => "Cobro de factura {$numFact}",
                    'monto_documento'         => $totalFact,
                    'saldo_anterior'          => $saldo,
                    'monto_cobrado'           => $monto,
                    'saldo_actual'            => max(0.0, $saldo - $monto),
                ]],
                'pagos' => [[
                    'id_forma_cobro'          => $idFormaCobro,
                    'monto'                   => $monto,
                    'fecha_cobro'             => $fechaCobro,
                    'observaciones'           => $observ ?: null,
                    'tipo_operacion_bancaria' => $tipoOp ?: null,
                    'numero_cheque'           => $numOp  ?: null,
                    'referencia'              => $numOp  ?: null,
                ]],
            ];

            $ingresoService = new \App\Services\modulos\IngresoService(
                new \App\repositories\modulos\IngresoRepository(),
                new \App\Rules\modulos\IngresoRules(),
                new \App\Services\LogSistemaService()
            );

            $idIngreso = $ingresoService->crear($payload);
            $db->commit();

            $nuevoSaldo = $saldo - $monto;
            $this->jsonSuccess([
                'mensaje'        => "Cobro registrado correctamente. Ingreso: {$numDoc}",
                'id_ingreso'     => $idIngreso,
                'numero_ingreso' => $numDoc,
                'nuevo_saldo'    => number_format($nuevoSaldo, 2, '.', ''),
                'pagada'         => $nuevoSaldo <= 0.001,
            ]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[CxC registrarCobro] ' . $e->getMessage());
            $this->jsonError('Error al registrar el cobro: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – DATOS EN TIEMPO REAL PARA EL MODAL DE COBRO
    // ─────────────────────────────────────────────────────────────────────

    public function getFacturaParaCobroInfoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = $this->empresaLectura(); // consolidado: puede ser una hermana
        $idVenta   = (int) ($_GET['id_venta'] ?? 0);

        if ($idVenta <= 0) {
            $this->jsonError('ID inválido.');
            return;
        }

        $factura = $this->facturaPropiaOCortar($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }

        $this->jsonSuccess(['factura' => $factura]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – HISTORIAL DE COBROS
    // ─────────────────────────────────────────────────────────────────────

    public function historialCobrosAjax(): void
    {
        $this->requireLeer();
        $this->requireHistorialCobros();
        $idEmpresa = $this->empresaLectura(); // consolidado: puede ser una hermana (solo lectura)
        $idVenta   = (int)($_GET['id_venta'] ?? 0);

        if ($idVenta <= 0) {
            $this->jsonError('ID de venta inválido.');
            return;
        }

        $this->facturaPropiaOCortar($idVenta, $idEmpresa); // registros propios (§6)
        $historial = $this->repo->getHistorialCobros($idVenta, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DETALLE Y PDF DE UN DOCUMENTO (panel lateral y botón PDF de la fila)
    //
    // Se validan con el permiso de ESTE módulo y el alcance del usuario (§6), no con el
    // de Facturas o Recibos de venta: quien ve el documento en la cartera puede ver su
    // detalle y descargarlo aunque no tenga acceso a esos módulos. Antes el panel
    // consultaba los endpoints de esos módulos y, sin ese acceso, mostraba "HTTP 403".
    // En el consolidado aceptan el id_empresa de una hermana (empresaLectura()).
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Factura o recibo del listado ya validado (empresa + alcance del usuario): corta con 403
     * si no pertenece a su cartera y devuelve null si no existe o no es un documento
     * pendiente de la cartera (anulado, eliminado…).
     *
     * @return array{0:string,1:int,2:int,3:?array} [origen, id, empresa, documento]
     */
    private function documentoDelListado(): array
    {
        $idEmpresa = $this->empresaLectura();
        $origen    = strtoupper(trim((string) ($_GET['origen'] ?? 'FACTURA')));
        $id        = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !in_array($origen, ['FACTURA', 'RECIBO'], true)) {
            return [$origen, $id, $idEmpresa, null];
        }
        $doc = $origen === 'RECIBO'
            ? $this->reciboPropioOCortar($id, $idEmpresa)
            : $this->facturaPropiaOCortar($id, $idEmpresa);
        return [$origen, $id, $idEmpresa, $doc];
    }

    /**
     * Detalle para el panel lateral (partials/offcanvas_doc_preview.php, vía extra.url).
     * Responde la forma que espera el panel y solo lo que pinta: cabecera con número,
     * fecha, cliente y totales, y las líneas con código, descripción, cantidad y precios.
     */
    public function detalleDocumentoAjax(): void
    {
        $this->requireLeer();
        [$origen, $id, , $doc] = $this->documentoDelListado();
        if (!$doc) {
            $this->json(['ok' => false, 'mensaje' => $origen === 'RECIBO' ? 'Recibo no encontrado.' : 'Factura no encontrada.']);
        }

        $detalles = $origen === 'RECIBO'
            ? (new \App\repositories\modulos\ReciboVentaRepository())->getDetalles($id)
            : (new \App\repositories\modulos\FacturaVentaRepository())->getDetalles($id);

        $this->json([
            'ok'       => true,
            'cabecera' => [
                'establecimiento'     => $doc['establecimiento'] ?? '',
                'punto_emision'       => $doc['punto_emision'] ?? '',
                'secuencial'          => $doc['secuencial'] ?? '',
                'fecha_emision'       => $doc['fecha_emision'] ?? '',
                'cliente_nombre'      => $doc['cliente_nombre'] ?? '',
                'total_sin_impuestos' => $doc['total_sin_impuestos'] ?? 0,
                'importe_total'       => $doc['importe_total'] ?? 0,
            ],
            'detalles' => array_map(static fn (array $d): array => [
                'codigo_principal'          => $d['codigo_principal'] ?? '',
                'descripcion'               => (string) ($d['descripcion'] ?? '') !== '' ? $d['descripcion'] : ($d['producto_nombre'] ?? ''),
                'cantidad'                  => $d['cantidad'] ?? 0,
                'precio_unitario'           => $d['precio_unitario'] ?? 0,
                'precio_total_sin_impuesto' => $d['precio_total_sin_impuesto'] ?? 0,
            ], $detalles),
        ]);
    }

    /**
     * PDF de una factura o un recibo del listado (botón PDF de la columna Acciones): el mismo
     * que descargan los módulos Facturas y Recibos de venta (DocumentoVentaPdfService).
     * Se abre en otra pestaña, así que los errores responden texto plano.
     */
    public function pdfDocumento(): void
    {
        $this->requireLeer();
        [$origen, $id, $idEmpresa, $doc] = $this->documentoDelListado();
        if (!$doc) {
            http_response_code(404);
            echo $origen === 'RECIBO' ? 'Recibo no encontrado.' : 'Factura no encontrada.';
            exit;
        }
        // Validado el acceso, la sesión ya no se usa: se suelta su candado para que las demás
        // peticiones del usuario no esperen a que termine de armarse el PDF.
        session_write_close();

        try {
            if (!(new \App\Services\modulos\DocumentoVentaPdfService())->descargar($origen, $id, $idEmpresa)) {
                http_response_code(404);
                echo $origen === 'RECIBO' ? 'Recibo no encontrado.' : 'Factura no encontrada.';
            }
        } catch (\Throwable $e) {
            error_log('[CxC pdfDocumento] ' . $e->getMessage());
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo 'Error al generar el PDF: ' . $e->getMessage();
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – RECIBOS DE VENTA (cobro, info y historial)
    // ─────────────────────────────────────────────────────────────────────

    public function getReciboParaCobroInfoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = $this->empresaLectura(); // consolidado: puede ser una hermana
        $idRecibo  = (int) ($_GET['id_recibo'] ?? 0);

        if ($idRecibo <= 0) {
            $this->jsonError('ID inválido.');
            return;
        }

        $recibo = $this->reciboPropioOCortar($idRecibo, $idEmpresa);
        if (!$recibo) {
            $this->jsonError('Recibo no encontrado.');
            return;
        }

        $this->jsonSuccess(['factura' => $recibo]);
    }

    public function historialCobrosReciboAjax(): void
    {
        $this->requireLeer();
        $this->requireHistorialCobros();
        $idEmpresa = $this->empresaLectura(); // consolidado: puede ser una hermana (solo lectura)
        $idRecibo  = (int)($_GET['id_recibo'] ?? 0);

        if ($idRecibo <= 0) {
            $this->jsonError('ID de recibo inválido.');
            return;
        }

        $this->reciboPropioOCortar($idRecibo, $idEmpresa); // registros propios (§6)
        $historial = $this->repo->getHistorialCobrosRecibo($idRecibo, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }

    /**
     * Cobro de un recibo de venta desde la tabla unificada de CxC.
     * Mismo flujo que registrarCobroAjax pero con tipo_ingreso RECIBO_VENTA
     * y detalle tipo_documento RECIBO (igual que el módulo de recibos).
     */
    public function registrarCobroReciboAjax(): void
    {
        $this->requireCrear();
        $idEmpresa    = $this->empresaEscritura(); // consolidado: cobro en los libros de la hermana dueña
        $idUsuario    = (int) $_SESSION['id_usuario'];

        $idRecibo     = (int)($_POST['id_recibo']         ?? 0);
        $monto        = (float)($_POST['monto']           ?? 0);
        $idFormaCobro = (int)($_POST['id_forma_cobro']    ?? 0);
        $idPunto      = (int)($_POST['id_punto_emision']  ?? 0);
        $idConcepto   = !empty($_POST['id_ingreso_concepto']) ? (int)$_POST['id_ingreso_concepto'] : null;
        $fechaCobro   = trim($_POST['fecha_cobro']        ?? date('Y-m-d'));
        $observ       = trim($_POST['observaciones']      ?? '');
        $tipoOp       = trim($_POST['tipo_operacion_bancaria'] ?? '');
        $numOp        = trim($_POST['numero_operacion']        ?? '');

        if ($idRecibo <= 0 || $monto <= 0 || $idFormaCobro <= 0 || $idPunto <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de cobro.');
            return;
        }

        // Validar punto de emisión
        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('La serie (punto de emisión) no es válida o está inactiva.');
            return;
        }

        // Validar recibo y saldo (y que sea del usuario, si no tiene acceso total)
        $recibo = $this->reciboPropioOCortar($idRecibo, $idEmpresa);
        if (!$recibo) {
            $this->jsonError('Recibo no encontrado.');
            return;
        }
        $saldo    = (float)$recibo['saldo'];
        $totalRec = (float)$recibo['importe_total'];
        if ($saldo <= 0) {
            $this->jsonError('Este recibo ya se encuentra pagado.');
            return;
        }
        if ($monto > $saldo + 0.001) {
            $this->jsonError("El monto ($monto) supera el saldo pendiente ($saldo).");
            return;
        }

        $db = \App\core\Database::getConnection();
        try {
            // Obtener siguiente secuencial mediante SecuencialService. Se abre la transacción
            // ANTES de calcularlo y se mantiene hasta el INSERT final (IngresoService::crear()):
            // el lock de obtenerSiguienteSecuencial() se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
            $db->beginTransaction();
            $secuencialService = new \App\Services\SecuencialService();
            // Con numeración por fecha de emisión, el cobro numera en el periodo de SU fecha.
            $secRes     = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Ingresos', $fechaCobro ?: date('Y-m-d'));
            $secuencial = $secRes['formateado'];

            $codEst = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $codPto = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);
            $numDoc = "{$codEst}-{$codPto}-{$secuencial}";
            $numRec = ($recibo['establecimiento'] ?? '') . '-'
                    . ($recibo['punto_emision']   ?? '') . '-'
                    . ($recibo['secuencial']       ?? '');

            // Delegar al IngresoService (igual que ReciboVentaController)
            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int)($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => $idPunto,
                'id_cliente'          => (int)$recibo['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $fechaCobro ?: date('Y-m-d'),
                'establecimiento'     => $codEst,
                'punto_emision'       => $codPto,
                'secuencial'          => $secuencial,
                'numero_ingreso'      => $numDoc,
                'tipo_ingreso'        => 'RECIBO_VENTA',
                'id_ingreso_concepto' => $idConcepto,
                'monto_total'         => $monto,
                'observaciones'       => $observ ?: "Cobro de recibo {$numRec}",
                'recibo_de'           => $recibo['cliente_nombre'] ?? '',
                'id_recibo_cliente'   => (int)$recibo['id_cliente'],
                'detalles'            => [[
                    'tipo_documento'          => 'RECIBO',
                    'id_referencia_documento' => $idRecibo,
                    'numero_documento'        => $numRec,
                    'descripcion'             => "Cobro de recibo {$numRec}",
                    'monto_documento'         => $totalRec,
                    'saldo_anterior'          => $saldo,
                    'monto_cobrado'           => $monto,
                    'saldo_actual'            => max(0.0, $saldo - $monto),
                ]],
                'pagos' => [[
                    'id_forma_cobro'          => $idFormaCobro,
                    'monto'                   => $monto,
                    'fecha_cobro'             => $fechaCobro,
                    'observaciones'           => $observ ?: null,
                    'tipo_operacion_bancaria' => $tipoOp ?: null,
                    'numero_cheque'           => $numOp  ?: null,
                    'referencia'              => $numOp  ?: null,
                ]],
            ];

            $ingresoService = new \App\Services\modulos\IngresoService(
                new \App\repositories\modulos\IngresoRepository(),
                new \App\Rules\modulos\IngresoRules(),
                new \App\Services\LogSistemaService()
            );

            $idIngreso = $ingresoService->crear($payload);
            $db->commit();

            $nuevoSaldo = $saldo - $monto;
            $this->jsonSuccess([
                'mensaje'        => "Cobro registrado correctamente. Ingreso: {$numDoc}",
                'id_ingreso'     => $idIngreso,
                'numero_ingreso' => $numDoc,
                'nuevo_saldo'    => number_format($nuevoSaldo, 2, '.', ''),
                'pagada'         => $nuevoSaldo <= 0.001,
            ]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[CxC registrarCobroRecibo] ' . $e->getMessage());
            $this->jsonError('Error al registrar el cobro: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – FORMAS DE COBRO
    // ─────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – CATÁLOGOS PARA EL MODAL COBRO (puntos, conceptos, formas)
    // ─────────────────────────────────────────────────────────────────────

    public function getCatalogosCobroAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = $this->empresaLectura(); // consolidado: series/conceptos/formas de la hermana dueña
        $this->jsonSuccess([
            'puntos'    => $this->repo->getPuntosEmision($idEmpresa),
            'conceptos' => $this->repo->getConceptos($idEmpresa),
            'formas'    => $this->repo->getFormasCobro($idEmpresa),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – SIGUIENTE SECUENCIAL DE INGRESO PARA UN PUNTO DE EMISIÓN
    // ─────────────────────────────────────────────────────────────────────

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            $this->jsonError('ID de punto de emisión inválido.');
            return;
        }
        // El punto debe pertenecer a la empresa del cobro (la activa o, en consolidado, la
        // hermana dueña del documento); así no se consulta el secuencial de cualquier serie.
        if (!$this->repo->getPuntoEmisionPorId($idPunto, $this->empresaLectura())) {
            $this->jsonError('La serie (punto de emisión) no es válida o está inactiva.');
            return;
        }
        // Fecha del documento: solo pesa si este tipo numera por fecha de emisión
        // (Empresa → Secuenciales); en modo consecutivo el servidor la ignora.
        $fecha = trim($_GET['fecha'] ?? '') ?: null;
        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Ingresos', $fecha);
        $this->jsonSuccess($res);
    }

    public function getFormasCobroAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = $this->empresaLectura();
        $this->jsonSuccess(['formas' => $this->repo->getFormasCobro($idEmpresa)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – PLANTILLAS WHATSAPP
    // ─────────────────────────────────────────────────────────────────────

    public function getPlantillasWAAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $todasPlantillas = $this->repo->getPlantillasWA($idEmpresa);

        $todasLasRapidas = [
            'aviso_mensajes_pendientes', 'factura_por_cobrar', 'factura_venta',
            'cuenta_por_cobrar', 'renovacion_suscripcion', 'renovacion_firma_electronica',
            'retencion_compra', 'nota_credito', 'nota_debito', 'guia_remision',
            'rol_pagos', 'descuento_empleado'
        ];
        $rapidasPermitidas = ['factura_por_cobrar', 'cuenta_por_cobrar'];

$plantillasFiltradas = [];
        foreach ($todasPlantillas as $p) {
            if (in_array($p['nombre'], $todasLasRapidas)) {
                if (in_array($p['nombre'], $rapidasPermitidas)) {
                    $plantillasFiltradas[] = $p;
                }
            } else {
                // Es una plantilla libre
                $plantillasFiltradas[] = $p;
            }
        }

        $this->jsonSuccess(['plantillas' => $plantillasFiltradas]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – ENVIAR EMAIL
    // ─────────────────────────────────────────────────────────────────────

    public function enviarEmailAjax(): void
    {
        $this->requireLeer();
        // Soltar el candado de la sesión antes de armar y enviar el correo: si no, las demás
        // peticiones del usuario hacen fila hasta que termine.
        session_write_close();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $idVenta   = (int)($_POST['id_venta'] ?? 0);
        $emailDest = trim($_POST['email'] ?? '');
        $asunto    = trim($_POST['asunto'] ?? '');
        $mensaje   = trim($_POST['mensaje'] ?? '');
        $origen    = strtoupper(trim($_POST['origen'] ?? 'FACTURA'));
        $esRecibo  = ($origen === 'RECIBO');

        if ($idVenta <= 0 || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Datos incompletos o email inválido.');
            return;
        }

        // Registros propios (§6): sin acceso total solo se notifican los documentos propios
        $factura = $esRecibo
            ? $this->reciboPropioOCortar($idVenta, $idEmpresa)
            : $this->facturaPropiaOCortar($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError($esRecibo ? 'Recibo no encontrado.' : 'Factura no encontrada.');
            return;
        }

        $docLabel    = $esRecibo ? 'Recibo' : 'Factura';
        $asuntoFinal = $asunto ?: "Recordatorio de pago — {$docLabel} " . ($factura['numero_factura'] ?? '');
        $htmlBody    = $this->renderEmailBody($factura, $mensaje, $docLabel);

        // Usar el mismo servicio de envío que el resto del sistema
        // (incluye _mail_resolve_ipv4_host y config SMTP por empresa)
        $emailSvc = new \App\Services\EnvioDocumentosSRIService();
        $enviado  = $emailSvc->enviarAvisoSimple(
            $idEmpresa,
            $emailDest,
            $factura['cliente_nombre'] ?? '',
            $asuntoFinal,
            $htmlBody
        );

        if (!$enviado) {
            $detalle = $GLOBALS['LAST_EMAIL_ERROR'] ?? null;
            $this->jsonError('No se pudo enviar el correo. Verifica la configuración de correo de la empresa.'
                . ($detalle ? ' Detalle: ' . $detalle : ''));
            return;
        }

        $this->log->registrar(
            (int)$_SESSION['id_usuario'],
            $idEmpresa,
            'EMAIL_CXC',
            $esRecibo ? 'recibos_venta_cabecera' : 'ventas_cabecera',
            $idVenta,
            null,
            ['email' => $emailDest]
        );

        $this->jsonSuccess(['mensaje' => 'Correo enviado correctamente a ' . $emailDest]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – ENVÍO MASIVO DE EMAIL (un correo por cliente con el resumen)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Envío masivo de recordatorios: recibe los documentos seleccionados
     * ([{origen: FACTURA|RECIBO, id}]), los agrupa por cliente y envía UN
     * correo por cliente con la tabla resumen de sus documentos pendientes
     * (facturas y recibos mezclados) y el total. El email destino se toma de
     * la ficha del cliente en BD; los documentos sin saldo se omiten.
     */
    public function enviarEmailMasivoAjax(): void
    {
        $this->requireLeer();
        // Soltar el candado de la sesión antes de armar y enviar los correos (hasta 300 documentos): si no, las demás
        // peticiones del usuario hacen fila hasta que termine.
        session_write_close();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $docsRaw = json_decode($_POST['documentos'] ?? '[]', true);
        if (!is_array($docsRaw) || empty($docsRaw)) {
            $this->jsonError('No se recibieron documentos para enviar.');
            return;
        }
        if (count($docsRaw) > 300) {
            $this->jsonError('Máximo 300 documentos por envío. Aplique filtros y envíe por partes.');
            return;
        }

        // Correos revisados/editados en el modal: {id_cliente: "correo1, correo2"}.
        // Si la clave existe se usa tal cual (vacío = omitir al cliente); si no,
        // se usa el correo de la ficha del cliente. Solo afecta a este envío.
        // La clave es UNO de los ids del cliente: el modal agrupa por identificación base,
        // así que un cliente con dos fichas (cédula y RUC) manda solo el id que mostró.
        $correosEdit = json_decode($_POST['correos'] ?? '{}', true);
        if (!is_array($correosEdit)) $correosEdit = [];

        // El envío SMTP secuencial puede tardar varios segundos por cliente
        @set_time_limit(300);

        // 1) Cargar cada documento desde BD (valida empresa/estado y trae el saldo real).
        // Se agrupa por identificación BASE y no por id de ficha: el mismo contribuyente
        // registrado dos veces —con la cédula y con el RUC, que es esa cédula + '001'—
        // recibe UN correo con todos sus documentos, no dos correos parciales.
        $porCliente    = [];  // clave de cliente real => [nombre, email, ids[], docs[]]
        $sinSaldo      = 0;
        $noEncontrados = 0;
        $vistos        = [];  // dedup ORIGEN:id

        foreach ($docsRaw as $d) {
            $origen = strtoupper(trim((string)($d['origen'] ?? 'FACTURA')));
            $id     = (int)($d['id'] ?? 0);
            if ($id <= 0 || !in_array($origen, ['FACTURA', 'RECIBO'], true)) continue;
            $k = $origen . ':' . $id;
            if (isset($vistos[$k])) continue;
            $vistos[$k] = true;

            $doc = $origen === 'RECIBO'
                ? $this->repo->getReciboParaCobro($id, $idEmpresa)
                : $this->repo->getFacturaParaCobro($id, $idEmpresa);
            if (!$doc) { $noEncontrados++; continue; }
            // Alcance del usuario (§6): sin acceso total, un documento fuera de su
            // cartera se omite del envío (se cuenta como no encontrado, no corta el lote).
            if (!$this->dentroAlcance($doc, 'id_usuario', $idEmpresa)) { $noEncontrados++; continue; }

            $saldo = (float)($doc['saldo'] ?? 0);
            if ($saldo <= 0.001) { $sinSaldo++; continue; }

            // Fecha de vencimiento (el recibo ya la trae; en la factura se calcula)
            $fVenc = !empty($doc['fecha_vencimiento'])
                ? $doc['fecha_vencimiento']
                : date('Y-m-d', strtotime(($doc['fecha_emision'] ?? 'now') . ' +' . (int)($doc['dias_credito'] ?? 0) . ' days'));
            $diasVencido = (int)((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($fVenc)))) / 86400);

            $idCliente = (int)($doc['id_cliente'] ?? 0);
            $claveCli  = IdentificacionTercero::claveGrupo($doc['cliente_ruc'] ?? null, 'id:' . $idCliente);
            if (!isset($porCliente[$claveCli])) {
                $porCliente[$claveCli] = [
                    'nombre' => $doc['cliente_nombre'] ?? '',
                    'email'  => trim((string)($doc['cliente_email'] ?? '')),
                    'ids'    => [],
                    'docs'   => [],
                ];
            }
            // Todas las fichas que aportaron documentos: cualquiera de ellas puede ser la
            // que el modal usó como clave del correo editado, y si una no tiene correo en
            // su ficha sirve el de la otra.
            $porCliente[$claveCli]['ids'][$idCliente] = true;
            if ($porCliente[$claveCli]['email'] === '') {
                $porCliente[$claveCli]['email'] = trim((string)($doc['cliente_email'] ?? ''));
            }
            $porCliente[$claveCli]['docs'][] = [
                'tipo'          => $origen === 'RECIBO' ? 'Recibo' : 'Factura',
                'numero'        => $doc['numero_factura'] ?? '',
                'fecha_emision' => $doc['fecha_emision'] ?? '',
                'fecha_venc'    => $fVenc,
                'dias_vencido'  => $diasVencido,
                'total'         => (float)($doc['importe_total'] ?? 0),
                'saldo'         => $saldo,
            ];
        }

        if (empty($porCliente)) {
            $this->jsonError('Ninguno de los documentos seleccionados tiene saldo pendiente.');
            return;
        }

        // 2) Un correo por cliente
        $emailSvc = new \App\Services\EnvioDocumentosSRIService();
        $enviados = 0;
        $sinEmail = 0;
        $conError = 0;

        foreach ($porCliente as $cli) {
            // Correo editado en el modal (si vino) o el de la ficha del cliente. Se busca por
            // cualquiera de las fichas del grupo, porque el modal manda el id de una sola.
            $emailStr = trim((string)$cli['email']);
            foreach (array_keys($cli['ids']) as $idFicha) {
                if (array_key_exists($idFicha, $correosEdit)) {
                    $emailStr = trim((string)$correosEdit[$idFicha]);
                    break;
                }
            }

            // Direcciones válidas (mismo criterio de split que enviarAvisoSimple)
            $direcciones = [];
            foreach (preg_split('/[\s,;]+/', $emailStr) as $c) {
                $c = trim($c);
                if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) $direcciones[] = $c;
            }
            if (empty($direcciones)) {
                $sinEmail++;
                continue;
            }

            // Del más vencido al más reciente
            usort($cli['docs'], fn ($a, $b) => strcmp((string)$a['fecha_venc'], (string)$b['fecha_venc']));

            $n      = count($cli['docs']);
            $asunto = $n === 1
                ? 'Recordatorio de pago — ' . $cli['docs'][0]['tipo'] . ' ' . $cli['docs'][0]['numero']
                : "Recordatorio de pago — {$n} documentos pendientes";
            $html = $this->renderEmailBodyResumen($cli['nombre'], $cli['docs']);

            $ok = $emailSvc->enviarAvisoSimple($idEmpresa, implode(',', $direcciones), $cli['nombre'], $asunto, $html);
            if ($ok) {
                $enviados++;
                $this->log->registrar(
                    (int)$_SESSION['id_usuario'],
                    $idEmpresa,
                    'EMAIL_CXC_MASIVO',
                    'clientes',
                    // El grupo puede abarcar dos fichas del mismo cliente (cédula y RUC): se
                    // audita sobre la primera y se dejan todas en el detalle.
                    (int) (array_key_first($cli['ids']) ?? 0),
                    null,
                    [
                        'email'           => implode(', ', $direcciones),
                        'id_clientes'     => array_keys($cli['ids']),
                        'documentos'      => array_map(fn ($x) => $x['tipo'] . ' ' . $x['numero'], $cli['docs']),
                        'total_pendiente' => round(array_sum(array_column($cli['docs'], 'saldo')), 2),
                    ]
                );
            } else {
                $conError++;
            }
        }

        $partes = ["{$enviados} correo(s) enviado(s)."];
        if ($sinEmail)      $partes[] = "{$sinEmail} cliente(s) sin email registrado.";
        if ($conError)      $partes[] = "{$conError} correo(s) con error de envío.";
        if ($sinSaldo)      $partes[] = "{$sinSaldo} documento(s) sin saldo omitido(s).";
        if ($noEncontrados) $partes[] = "{$noEncontrados} documento(s) no disponible(s).";

        $this->jsonSuccess([
            'mensaje'        => implode(' ', $partes),
            'enviados'       => $enviados,
            'sin_email'      => $sinEmail,
            'con_error'      => $conError,
            'sin_saldo'      => $sinSaldo,
            'no_encontrados' => $noEncontrados,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – ENVIAR WHATSAPP
    // ─────────────────────────────────────────────────────────────────────

    public function enviarWhatsappAjax(): void
    {
        $this->requireLeer();
        // Soltar el candado de la sesión antes de armar y enviar el WhatsApp: si no, las demás
        // peticiones del usuario hacen fila hasta que termine.
        session_write_close();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $idVenta      = (int)($_POST['id_venta'] ?? 0);
        $telefono     = preg_replace('/[^0-9]/', '', trim($_POST['telefono'] ?? ''));
        $nombrePlant  = trim($_POST['template_name'] ?? '');

        if ($idVenta <= 0 || strlen($telefono) < 7 || !$nombrePlant) {
            $this->jsonError('Datos incompletos.');
            return;
        }

        if (str_starts_with($telefono, '593') && strlen($telefono) !== 12) {
            $this->jsonError('El número de teléfono para Ecuador (593) debe tener exactamente 12 dígitos.');
            return;
        }

        // Registros propios (§6): sin acceso total solo se notifican las facturas propias
        $factura = $this->facturaPropiaOCortar($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }

        // 1. OBTENER PLANTILLA Y VALIDARLA
        $stmt = $this->repo->getDb()->prepare("SELECT * FROM whatsapp_plantillas WHERE nombre = ? AND id_empresa = ? AND estado_meta = 'APPROVED'");
        $stmt->execute([$nombrePlant, $idEmpresa]);
        $plantillaMeta = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$plantillaMeta) {
            $this->jsonError('Plantilla no válida o no aprobada por Meta.');
            return;
        }

        $idioma = $plantillaMeta['idioma'];

        // 2. SALDO PENDIENTE (ya viene calculado por getFacturaParaCobro con las
        // mismas CTEs de cobrado/retenido/NC que usa el resto del módulo)
        $saldoReal = max(0, (float) ($factura['saldo'] ?? 0));

        // 3. GENERAR PDF SI ES NECESARIO
        $waService = new WhatsappService();
        $mediaId = null;

        if ($nombrePlant === 'factura_por_cobrar') {
            $ventasRepo = new \App\repositories\modulos\FacturaVentaRepository();
            $detalles = $ventasRepo->getDetalles($idVenta);
            // Impuestos EN LOTE: una sola consulta para todas las líneas, en vez
            // de una por línea. Con la base en un servidor remoto, un documento
            // largo pagaba un viaje de red por cada ítem.
            $impuestosPorDetalle = $ventasRepo->getImpuestosPorDetalles(array_column($detalles, 'id'));
            foreach ($detalles as &$d) {
                $d['impuestos'] = $impuestosPorDetalle[(int) $d['id']] ?? [];
            }
            unset($d);
            $pagos = $ventasRepo->getPagos($idVenta);
            $infoAdicional = $ventasRepo->getInfoAdicional($idVenta);

            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa) ?? [];
            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos)) {
                if (!empty($establecimientos[0]['logo_ruta'])) $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                if (!empty($establecimientos[0]['direccion'])) $empresa['direccion_establecimiento'] = $establecimientos[0]['direccion'];
                if (!empty($establecimientos[0]['leyenda_pdf_titulo'])) $empresa['leyenda_pdf_titulo'] = $establecimientos[0]['leyenda_pdf_titulo'];
                if (!empty($establecimientos[0]['leyenda_pdf_mensaje'])) $empresa['leyenda_pdf_mensaje'] = $establecimientos[0]['leyenda_pdf_mensaje'];

                // Config del establecimiento: sin esto el PDF que se envía por
                // correo desde aquí salía distinto al del módulo Factura de Venta
                // (otros decimales, sin la presentación de ítems configurada y sin
                // los interruptores de Vendedor/Cajero y propina del RIDE).
                // Mismo bloque que FacturaVentaController::generarPdf().
                try {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $estConfig['direccion_matriz'] = $empresa['direccion'] ?? '';
                        $estConfig['direccion_establecimiento'] = $establecimientos[0]['direccion'] ?? '';
                        if (!empty($establecimientos[0]['logo_ruta'])) $estConfig['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                        if (!empty($establecimientos[0]['leyenda_pdf_titulo'])) $estConfig['leyenda_pdf_titulo'] = $establecimientos[0]['leyenda_pdf_titulo'];
                        if (!empty($establecimientos[0]['leyenda_pdf_mensaje'])) $estConfig['leyenda_pdf_mensaje'] = $establecimientos[0]['leyenda_pdf_mensaje'];
                        $empresa = array_merge($empresa, $estConfig);
                    }
                } catch (\Throwable $e) {
                    // El PDF se genera igual sin la config extendida del establecimiento.
                }
            }

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantillaPdf = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');

            if ($plantillaPdf) {
                $pdfString = $renderer->generar($plantillaPdf, $factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
            } else {
                $pdfService = new \App\Services\modulos\FacturaVentaPdfService();
                $pdfString = $pdfService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
            }

            if (empty($pdfString)) {
                $this->jsonError('No se pudo generar el PDF de la factura.');
                return;
            }

            $tmpPdfPath = sys_get_temp_dir() . '/factura_' . $idVenta . '_' . time() . '.pdf';
            file_put_contents($tmpPdfPath, $pdfString);

            $uploadResult = $waService->uploadMessageMedia($idEmpresa, $tmpPdfPath, 'application/pdf');
            unlink($tmpPdfPath);

            if (!$uploadResult['success']) {
                $this->jsonError('Error subiendo PDF a Meta: ' . $uploadResult['message']);
                return;
            }
            $mediaId = $uploadResult['media_id'];
        }

        // 4. CONSTRUIR COMPONENTES (API COMPONENTS)
        $componentesDB = json_decode($plantillaMeta['componentes'], true) ?? [];
        $apiComponents = [];

        $numeroFactura = ($factura['establecimiento'] ?? '') . '-' . ($factura['punto_emision'] ?? '') . '-' . ($factura['secuencial'] ?? '');
        $nombreCliente = $factura['cliente_nombre'] ?? 'Cliente';
        $saldoFormateado = number_format($saldoReal, 2);

        foreach ($componentesDB as $comp) {
            $type = $comp['type'] ?? '';

            if ($type === 'HEADER' && ($comp['format'] ?? '') === 'DOCUMENT' && $mediaId) {
                $apiComponents[] = [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'id' => $mediaId,
                                'filename' => 'Factura_' . $numeroFactura . '.pdf'
                            ]
                        ]
                    ]
                ];
            } elseif ($type === 'BODY') {
                $texto = $comp['text'] ?? '';
                if (preg_match_all('/{{(\d+)}}/', $texto, $matches)) {
                    $numVars = max($matches[1]);
                    $parameters = [];
                    for ($i = 1; $i <= $numVars; $i++) {
                        $val = '';
                        
                        if ($nombrePlant === 'factura_por_cobrar') {
                            // 1: Cliente, 2: Saldo, 3: Número
                            if ($i == 1) $val = $nombreCliente;
                            elseif ($i == 2) $val = '$' . $saldoFormateado;
                            elseif ($i == 3) $val = $numeroFactura;
                        } elseif ($nombrePlant === 'cuenta_por_cobrar') {
                            // 1: Cliente, 2: Saldo
                            if ($i == 1) $val = $nombreCliente;
                            elseif ($i == 2) $val = '$' . $saldoFormateado;
                        } else {
                            $val = ' ';
                        }

                        $parameters[] = [
                            'type' => 'text',
                            'text' => (string) $val
                        ];
                    }

                    $apiComponents[] = [
                        'type' => 'body',
                        'parameters' => $parameters
                    ];
                }
            }
        }

        // 5. ENVIAR MENSAJE A META
        $result = $waService->sendTemplateMessage($idEmpresa, $telefono, $nombrePlant, $idioma, $apiComponents);

        if (!($result['success'] ?? false)) {
            $this->jsonError('Error al enviar WhatsApp: ' . ($result['message'] ?? 'Desconocido'));
            return;
        }

        // --- Guardar en la Base de Datos para el Webhook ---
        try {
            $metaMessageId = $result['data']['messages'][0]['id'] ?? null;
            $repoMsj = new \App\repositories\modulos\WhatsappMensajeRepository();
            $nombreCliente = $factura['cliente_nombre'] ?? 'Cliente';
            $idChat = $repoMsj->getOrCreateChat($idEmpresa, $telefono, $nombreCliente, 'Recordatorio de cuenta por cobrar', false);

            $variablesGuardar = [];
            foreach ($apiComponents as $comp) {
                if (strtolower($comp['type'] ?? '') === 'body') {
                    foreach ($comp['parameters'] ?? [] as $p) {
                        $variablesGuardar[] = $p['text'] ?? '';
                    }
                    break;
                }
            }

            $templateTextGuardar = '';
            foreach ($componentesDB as $comp) {
                if (($comp['type'] ?? '') === 'BODY') {
                    $templateTextGuardar = $comp['text'] ?? '';
                    foreach ($variablesGuardar as $idx => $val) {
                        $templateTextGuardar = str_replace('{{' . ($idx + 1) . '}}', $val, $templateTextGuardar);
                    }
                    break;
                }
            }

            $repoMsj->saveMessage(
                $idEmpresa,
                $idChat,
                'OUT',
                $telefono,
                'template',
                [
                    'template'      => $nombrePlant,
                    'variables'     => $variablesGuardar,
                    'template_text' => $templateTextGuardar,
                ],
                $metaMessageId,
                'sent'
            );
        } catch (\Throwable $ex) {
            error_log("Error guardando mensaje en BD (CXC): " . $ex->getMessage());
        }

        $this->log->registrar(
            (int)$_SESSION['id_usuario'],
            $idEmpresa,
            'WHATSAPP_CXC',
            'ventas_cabecera',
            $idVenta,
            null,
            ['telefono' => $telefono, 'template' => $nombrePlant]
        );

        $this->jsonSuccess(['mensaje' => 'WhatsApp enviado correctamente.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – BÚSQUEDA DE CLIENTES
    // ─────────────────────────────────────────────────────────────────────

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            $this->jsonSuccess(['clientes' => []]);
            return;
        }

        // Consolidado: se busca en todos los establecimientos del grupo (una fila por
        // identificación). Si el alcance no procede, resolverAlcance() lo deja en la activa.
        $filtros = ['alcance' => strtoupper(trim((string)($_GET['alcance'] ?? '')))];
        [$idsEmpresa] = $this->resolverAlcance($idEmpresa, $filtros);
        $this->jsonSuccess(['clientes' => $this->repo->buscarClientes($idsEmpresa, $idEmpresa, $q)]);
    }

    /**
     * Buscador del filtro Producto (nombre o código). En consolidado busca en todos los
     * establecimientos del grupo y devuelve una fila por código.
     */
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            $this->jsonSuccess(['productos' => []]);
            return;
        }

        $filtros = ['alcance' => strtoupper(trim((string)($_GET['alcance'] ?? '')))];
        [$idsEmpresa] = $this->resolverAlcance($idEmpresa, $filtros);
        $this->jsonSuccess(['productos' => $this->repo->buscarProductos($idsEmpresa, $idEmpresa, $q)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTACIÓN EXCEL
    // ─────────────────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);

        $filas = $this->getFilasUnificadas($idsEmpresa, $filtros);

        // El archivo sale con la misma estructura que la vista activa en pantalla
        $vista = strtoupper(trim((string)($_REQUEST['vista'] ?? '')));
        // "Por producto": mismo listado, agrupado por producto (una fila por producto y documento)
        if ($vista === 'PRODUCTO') {
            $this->exportExcelPorProducto($idEmpresa, $idsEmpresa, $consolidado, $filtros, $filas);
            return;
        }
        // "Por cliente": una sección por cliente con sus documentos, SUBTOTAL y TOTAL GENERAL (formato mayor)
        if ($vista === 'CLIENTE') {
            $this->exportExcelPorCliente($idEmpresa, $idsEmpresa, $consolidado, $filtros, $filas);
            return;
        }

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';
            $filtrosTxt    = $this->describirFiltros($idsEmpresa, $filtros);

            // Con el listado acotado a un asesor, su columna repetiría el mismo nombre en
            // todas las filas (ya va en el resumen de filtros de arriba) y se omite.
            $sinVendedor = $this->filtraPorVendedor($filtros);

            $headers = ['Documento', 'Origen', 'Cliente', 'RUC/Cédula', 'Vendedor', 'F.Emisión', 'F.Vencimiento', 'Días', 'Total', 'Abonos', 'Notas de Crédito', 'Retenciones', 'Cobrado', 'Saldo', 'Estado'];
            if ($sinVendedor) {
                unset($headers[4]);
                $headers = array_values($headers);
            }
            if ($consolidado) {
                // Consolidado: primera columna con el establecimiento dueño del documento
                array_unshift($headers, 'Estab.');
            }
            // Columnas de montos (1-based): número con 2 decimales, sin separador de miles.
            // Son las seis que siguen a "Días", así que su posición se corre con las
            // columnas opcionales del inicio (Estab.) y con la de Vendedor.
            $colTotal = 9 + ($consolidado ? 1 : 0) - ($sinVendedor ? 1 : 0);
            $formatos = array_fill_keys(range($colTotal, $colTotal + 5), '0.00');

            $exportData = [];
            foreach ($filas as $r) {
                $dias = (int)($r['dias_vencido'] ?? 0);
                $estadoCxC = $dias > 0 ? "VENCIDA ({$dias} días)" : 'VIGENTE';
                $abonos = (float)($r['total_cobrado'] ?? 0);
                $nc     = (float)($r['total_nc'] ?? 0);
                $ret    = (float)($r['total_retenido'] ?? 0);
                $exportData[] = [
                    ...($consolidado ? [(string)($r['establecimiento'] ?? '')] : []),
                    (string)($r['numero_factura'] ?? ''),
                    $this->getOrigenLabel($r['origen'] ?? 'FACTURA'),
                    (string)($r['cliente_nombre'] ?? ''),
                    (string)($r['cliente_ruc'] ?? ''),
                    ...($sinVendedor ? [] : [(string)($r['vendedor_nombre'] ?? '')]),
                    $r['fecha_emision'] ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                    $r['fecha_vencimiento'] ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '',
                    max(0, (int)($r['dias_transcurridos'] ?? 0)),
                    round((float)$r['total'], 2),
                    round($abonos, 2),
                    round($nc, 2),
                    round($ret, 2),
                    round($abonos + $nc + $ret, 2),
                    round((float)$r['saldo'], 2),
                    $estadoCxC,
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('cuentas_por_cobrar', $headers, $exportData, 'Cuentas por Cobrar', $nombreEmpresa, $filtrosTxt, $formatos);
            exit;
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                $_SESSION['cuentas_por_cobrar_msg'] = ['danger', 'Error al generar Excel: ' . $e->getMessage()];
                $this->redirect(BASE_URL . '/' . $this->getRutaModulo());
            }
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTACIÓN PDF
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);

        $filas = $this->getFilasUnificadas($idsEmpresa, $filtros);

        // El archivo sale con la misma estructura que la vista activa en pantalla
        $vista = strtoupper(trim((string)($_REQUEST['vista'] ?? '')));
        // "Por producto": cabecera por producto y debajo sus documentos
        if ($vista === 'PRODUCTO') {
            $this->exportPdfPorProducto($idEmpresa, $idsEmpresa, $consolidado, $filtros, $filas);
            return;
        }
        // "Por cliente": cabecera por cliente, sus documentos, SUBTOTAL y TOTAL GENERAL (formato mayor)
        if ($vista === 'CLIENTE') {
            $this->exportPdfPorCliente($idEmpresa, $idsEmpresa, $consolidado, $filtros, $filas);
            return;
        }

        $stats = $this->repo->getEstadisticas($idsEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';
            // Mismo resumen de filtros que encabeza el Excel: un PDF descargado o impreso
            // tiene que decir de qué periodo, estado, vendedor, cliente y producto es.
            $filtrosTxt    = $this->describirFiltros($idsEmpresa, $filtros);

            // Consolidado: columna "Estab." al inicio; se le resta ancho a "Cliente" para
            // que la suma siga en 100% (table-layout: fixed).
            $wEst = $consolidado ? 6 : 0;
            $wCli = 24 - $wEst;
            $tdEst = fn (array $r): string => $consolidado
                ? "<td class='text-center' style='width:{$wEst}%;'>" . htmlspecialchars((string)($r['establecimiento'] ?? '')) . "</td>"
                : '';

            $totalSaldo   = 0;
            $totalCobrado = 0;
            $totalTotal   = 0;
            $filaHtml = '';
            foreach ($filas as $r) {
                $dias = (int)($r['dias_vencido'] ?? 0);
                $ts   = (float)$r['total'];
                // "Cobrado" = abonos + retenciones + notas de crédito aplicadas
                $tc   = (float)($r['total_cobrado'] ?? 0) + (float)($r['total_retenido'] ?? 0) + (float)($r['total_nc'] ?? 0);
                $tsal = (float)$r['saldo'];
                $totalTotal   += $ts;
                $totalCobrado += $tc;
                $totalSaldo   += $tsal;
                $badge = $dias > 0 ? "<small style='font-weight:bold;'> ({$dias}d vencida)</small>" : "<small>Vigente</small>";
                $fVenc = !empty($r['fecha_vencimiento']) ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '—';
                $fEmis = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $origenTxt = $this->getOrigenLabel($r['origen'] ?? 'FACTURA');
                $filaHtml .= "<tr>{$tdEst($r)}
                    <td style='width:13%;'>" . htmlspecialchars($r['numero_factura'] ?? '') . "</td>
                    <td class='text-center' style='width:9%;'>{$origenTxt}</td>
                    <td style='width:{$wCli}%;'>" . htmlspecialchars($r['cliente_nombre'] ?? '') . "</td>
                    <td class='text-center' style='width:11%;'>{$fEmis}</td>
                    <td class='text-center' style='width:16%;'>{$fVenc} {$badge}</td>
                    <td class='text-end' style='width:9%;'>\$" . number_format($ts, 2) . "</td>
                    <td class='text-end' style='width:9%;'>\$" . number_format($tc, 2) . "</td>
                    <td class='text-end' style='width:9%;font-weight:bold;'>\$" . number_format($tsal, 2) . "</td>
                </tr>";
            }

            ob_start();
            ?>
            <style>
                body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
                th { background: #e9ecef; border: 1px solid #999; padding: 3px 3px; text-align: center; font-size: 8.5pt; color: #000; }
                td { border: 1px solid #999; padding: 2px 3px; font-size: 8pt; overflow: hidden; word-wrap: break-word; color: #000; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 10px; }
                .header h2 { margin: 0 0 2px 0; font-size: 13pt; }
                .header h3 { margin: 0 0 2px 0; font-size: 10pt; }
                .header p  { margin: 0; font-size: 7.5pt; }
                table.stats td.stats-box { text-align: center; vertical-align: middle; padding: 6px 4px; border: 1px solid #ccc; }
                .stat-lbl  { font-size: 7.5pt; }
                .stat-val  { font-size: 11pt; font-weight: bold; }
                <?= self::CSS_FILTROS_PDF ?>
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm">
            <?= $this->encabezadoPdf($idEmpresa, $nombreEmpresa, 'Cuentas por Cobrar') ?>
            <?= $this->bloqueFiltrosPdf($filtrosTxt) ?>
            <table class="stats">
                <tr>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Facturas</span><br/>
                        <span class="stat-val"><?= $stats['total_facturas'] ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Saldo Total</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_saldo'], 2) ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Vencido</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_vencido'], 2) ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Al Día</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_al_dia'], 2) ?></span>
                    </td>
                </tr>
            </table>
            <table>
                <thead>
                    <tr>
                        <?php if ($consolidado): ?><th style="width:<?= $wEst ?>%;">Estab.</th><?php endif; ?>
                        <th style="width:13%;">Documento</th>
                        <th style="width:9%;">Origen</th>
                        <th style="width:<?= $wCli ?>%;">Cliente</th>
                        <th style="width:11%;">F. Emisión</th>
                        <th style="width:16%;">F. Vencimiento</th>
                        <th style="width:9%;">Total</th>
                        <th style="width:9%;">Cobrado</th>
                        <th style="width:9%;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php // Sin resultados: una fila con el aviso, igual que las vistas "Por
                          // cliente" y "Por producto". Además hace que Html2Pdf dibuje el
                          // encabezado de la tabla, que con el <tbody> vacío no se pintaba. ?>
                    <?= $filaHtml ?: "<tr><td colspan='" . ($consolidado ? 9 : 8) . "' class='text-center' style='width:100%;'>No se encontraron cuentas por cobrar con los filtros aplicados.</td></tr>" ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:bold;">
                        <td colspan="<?= $consolidado ? 6 : 5 ?>" class="text-end" style="width:73%;">TOTALES:</td>
                        <td class="text-end" style="width:9%;">$<?= number_format($totalTotal, 2) ?></td>
                        <td class="text-end" style="width:9%;">$<?= number_format($totalCobrado, 2) ?></td>
                        <td class="text-end" style="width:9%;">$<?= number_format($totalSaldo, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
            </page>
            <?php
            $html     = ob_get_clean();
            // Vertical (A4 retrato): es la orientación por defecto de los listados del módulo.
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('CuentasPorCobrar_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVADOS AUXILIARES
    // ─────────────────────────────────────────────────────────────────────

    private function getFiltros(): array
    {
        $tipoDoc = strtoupper(trim((string)($_REQUEST['tipo_doc'] ?? 'TODOS')));
        if (!in_array($tipoDoc, ['TODOS', 'FACTURA', 'RECIBO', 'SALDO_INICIAL'], true)) {
            $tipoDoc = 'TODOS';
        }
        return [
            'estado'      => $_REQUEST['estado']      ?? 'PENDIENTES',
            'tipo_doc'    => $tipoDoc,
            'fecha_desde' => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta' => $_REQUEST['fecha_hasta'] ?? '',
            'id_cliente'  => $_REQUEST['id_cliente']  ?? '',
            'id_vendedor' => (int)($_REQUEST['id_vendedor'] ?? 0) ?: '',
            // Texto del producto (nombre o código de las líneas del documento). Con este
            // filtro los saldos iniciales quedan fuera: no tienen líneas de detalle.
            'producto'    => trim((string)($_REQUEST['producto'] ?? '')),
            // Productos elegidos en el buscador (ids separados por coma); en consolidado se
            // expanden a los hermanos con el mismo código (ver resolverAlcance()).
            'id_producto' => $_REQUEST['id_producto'] ?? '',
            // ESTABLECIMIENTO (solo la empresa activa) | CONSOLIDADO (todo el grupo RUC;
            // solo se honra desde la matriz — ver resolverAlcance()).
            'alcance'     => strtoupper(trim((string)($_REQUEST['alcance'] ?? ''))),
            // Orden de la tabla: columna de la lista blanca del repositorio (la vista la
            // manda en `data-sort` al hacer clic en una cabecera) y dirección. Viaja
            // también en el Excel y el PDF, para que salgan como se ve en pantalla.
            // Vacío = el orden por defecto (alfabético por cliente).
            'orden_col'   => trim((string)($_REQUEST['orden_col'] ?? '')),
            'orden_dir'   => strtoupper(trim((string)($_REQUEST['orden_dir'] ?? ''))) === 'DESC' ? 'DESC' : 'ASC',
            // El alcance del usuario (§6: `id_vendedor_filtro` / `id_usuario_filtro`)
            // lo agrega resolverAlcance(), que ya conoce las empresas del listado.
            // Al ir en los filtros lo heredan el listado, las tarjetas, el gráfico de
            // antigüedad y las exportaciones, que parten de este mismo arreglo.
        ];
    }

    /**
     * Alcance del usuario (§6, ver App\Helpers\AlcanceRegistros): los niveles 2
     * y 3 y quien tenga acceso total ('t') ven todo; en el nivel 1 sin acceso
     * total, el vendedor vinculado al usuario ve solo lo de su vendedor (lo que
     * lleva su nombre y, sin vendedor, lo de sus clientes) y, si no es vendedor,
     * solo lo que él registró. Se resuelve del nivel, el permiso y la sesión,
     * nunca de la petición, una sola vez por combinación de empresas (index,
     * listado, guardas por id…).
     */
    private array $alcanceCache = [];

    private function alcanceUsuario(array $idsEmpresa): array
    {
        $k = implode(',', array_map('intval', $idsEmpresa));
        return $this->alcanceCache[$k] ??= \App\Helpers\AlcanceRegistros::resolver(
            $this->getPermisos(),
            (int) ($_SESSION['id_usuario'] ?? 0),
            $idsEmpresa
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // ALCANCE DEL USUARIO EN LAS ACCIONES POR ID
    //
    // El filtro del listado oculta los documentos ajenos, pero cada acción recibe
    // un id suelto: sin este guard se llegaría por id a un documento que la tabla
    // no muestra. requireDentroAlcance() corta con 403 y deja pasar a los niveles 2
    // y 3 y a quien tenga acceso total (mismo criterio que el listado: lo de su
    // vendedor vinculado o, si no es vendedor, registros propios).
    //
    // Devuelven el documento ya leído (o null si no existe, para que el llamador
    // responda su propio "no encontrado"), así la acción no repite la consulta.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Factura de venta del cobro. La consulta trae `id_vendedor` y
     * `cliente_id_vendedor` (modo vendedor) e `id_usuario` (registros propios).
     */
    private function facturaPropiaOCortar(int $idVenta, int $idEmpresa): ?array
    {
        $factura = $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        $this->requireDentroAlcance($factura, 'id_usuario', $idEmpresa);
        return $factura;
    }

    /** Recibo de venta: mismas columnas que la factura. */
    private function reciboPropioOCortar(int $idRecibo, int $idEmpresa): ?array
    {
        $recibo = $this->repo->getReciboParaCobro($idRecibo, $idEmpresa);
        $this->requireDentroAlcance($recibo, 'id_usuario', $idEmpresa);
        return $recibo;
    }

    /**
     * Saldo inicial CxC: no lleva vendedor (entra solo por el cliente asignado) y
     * su tabla no tiene `id_usuario`, el creador es `created_by`.
     */
    private function saldoInicialPropioOCortar(int $idSaldo, int $idEmpresa): ?array
    {
        $saldo = (new \App\repositories\modulos\SaldosInicialesRepository())->getCxcPorId($idSaldo, $idEmpresa);
        $vCli  = null;
        if ($saldo && \App\Helpers\AlcanceRegistros::idsVendedor($this->alcanceUsuario([$idEmpresa]))) {
            $vCli = $this->repo->getIdVendedorDeCliente((int) ($saldo['id_cliente'] ?? 0), $idEmpresa);
        }
        $this->requireDentroAlcance($saldo, 'created_by', $idEmpresa, $vCli);
        return $saldo;
    }

    /**
     * ¿El documento cae dentro del alcance del usuario en esa empresa? Variante
     * que NO corta, para los procesos por lote (envío masivo de correos): un
     * documento ajeno se omite y se cuenta como "no encontrado", igual que uno
     * que ya no existe; abortar el lote entero con 403 sería peor para el usuario.
     */
    private function dentroAlcance(?array $registro, string $campoCreador, int $idEmpresa, ?int $idVendedorCliente = null): bool
    {
        if ($registro === null) {
            return true; // no hay registro que validar
        }
        return \App\Helpers\AlcanceRegistros::incluye(
            $this->alcanceUsuario([$idEmpresa]), $registro, $campoCreador, $idVendedorCliente
        );
    }

    /** Corta con 403 si el documento queda fuera del alcance (deja pasar a los niveles 2 y 3 y al acceso total). */
    private function requireDentroAlcance(?array $registro, string $campoCreador, int $idEmpresa, ?int $idVendedorCliente = null): void
    {
        if ($this->dentroAlcance($registro, $campoCreador, $idEmpresa, $idVendedorCliente)) {
            return;
        }
        $msg = 'No tiene permiso sobre este registro: no pertenece a su cartera.';
        if ($this->esAjaxRequest()) {
            $this->json(['ok' => false, 'error' => $msg], 403);
        }
        http_response_code(403);
        echo $msg;
        exit;
    }

    /**
     * ¿El listado está acotado a UN solo asesor? Pasa con el filtro Vendedor de la pantalla y
     * con el usuario restringido a su propio vendedor (§6, `id_vendedor_filtro`; ahí
     * resolverAlcance() ya vació `id_vendedor`). En ese caso la columna Asesor repetiría el
     * mismo nombre en todas las filas —el que ya dice el resumen de filtros— y se omite en
     * pantalla, en el PDF y en el Excel. El usuario restringido SIN vendedor vinculado (ve sus
     * propios registros) no entra aquí: sus documentos pueden ser de varios asesores.
     */
    private function filtraPorVendedor(array $filtros): bool
    {
        return !empty($filtros['id_vendedor'])
            || \App\Helpers\AlcanceRegistros::idsVendedor($filtros) !== [];
    }

    /**
     * Resumen de los filtros aplicados para los PDF del módulo: las mismas etiquetas y valores
     * que encabezan el Excel (describirFiltros()), en una caja compacta de dos pares por fila.
     * Html2Pdf no admite float ni flex y, con un colspan en la primera fila, ignora los anchos
     * declarados de esa tabla: por eso el título va en su propia tabla de una celda y los pares
     * en otra donde cada `<td>` lleva su ancho.
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
        // TODAS las filas llevan las mismas cuatro celdas con los mismos anchos: basta con
        // que una declare otros (p. ej. 13% + 87% para el filtro impar que sobra) para que
        // Html2Pdf adopte ESOS como ancho de columna y la tabla se salga de la hoja —
        // comprobado: el bloque terminaba dibujándose hasta x≈1082 pt en un A4 de 595 pt.
        // Con un número impar de filtros, el último par queda en blanco.
        $filas = '';
        for ($i = 0, $n = count($pares); $i < $n; $i += 2) {
            [$lblA, $valA] = $pares[$i];
            [$lblB, $valB] = $pares[$i + 1] ?? ['', ''];
            $filas .= '<tr>'
                . "<td class='f-lbl' style='width:13%;'>" . ($lblA !== '' ? $e($lblA) . ':' : '') . '</td>'
                . "<td style='width:37%;'>" . $e($valA) . '</td>'
                . "<td class='f-lbl' style='width:13%;'>" . ($lblB !== '' ? $e($lblB) . ':' : '') . '</td>'
                . "<td style='width:37%;'>" . $e($valB) . '</td>'
                . '</tr>';
        }
        return "<table class='fil-tit'><tr><td style='width:100%;'>Filtros aplicados</td></tr></table>"
            . "<table class='filtros'>{$filas}</table>";
    }

    /** Estilos de la caja "Filtros aplicados": los tres PDF del módulo la dibujan igual. */
    private const CSS_FILTROS_PDF = '
        table.fil-tit { margin-bottom: 0; }
        table.fil-tit td { background: #e9ecef; border: 1px solid #ccc; padding: 3px 5px; font-size: 8.5pt; font-weight: bold; color: #000; }
        table.filtros { margin-bottom: 8px; }
        table.filtros td { border: 1px solid #ddd; padding: 2px 4px; font-size: 8pt; color: #000; }
        table.filtros td.f-lbl { background: #f8f9fa; font-weight: bold; }
    ';

    /**
     * Descripción legible de los filtros aplicados (encabezado del Excel y caja "Filtros
     * aplicados" de los PDF, vía bloqueFiltrosPdf()).
     * Devuelve etiqueta => valor, con los ids de cliente/vendedor resueltos a nombre.
     */
    private function describirFiltros(int|array $idsEmpresa, array $filtros): array
    {
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idsEmpresa = (array) $idsEmpresa;
        $tipoDocLbl = [
            'TODOS'         => 'Todos (facturas, recibos y saldos iniciales)',
            'FACTURA'       => 'Facturas de venta',
            'RECIBO'        => 'Recibos de venta',
            'SALDO_INICIAL' => 'Saldos iniciales',
        ];
        $estadoLbl = [
            'PENDIENTES' => 'Saldo pendiente',
            'VENCIDAS'   => 'Vencidas',
            'AL_DIA'     => 'Al día',
            'PAGADAS'    => 'Pagadas',
            'TODOS'      => 'Todos',
        ];

        $fmt = fn(string $f): string => $f !== '' ? date('d-m-Y', strtotime($f)) : '';
        $desde = $fmt((string)($filtros['fecha_desde'] ?? ''));
        $hasta = $fmt((string)($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' && $hasta !== '') {
            $periodo = "Del {$desde} al {$hasta}";
        } elseif ($desde !== '') {
            $periodo = "Desde {$desde}";
        } elseif ($hasta !== '') {
            $periodo = "Hasta {$hasta} (fecha de corte)";
        } else {
            $periodo = 'Sin límite de fechas';
        }

        $vendedorTxt = 'Todos';
        if (\App\Helpers\AlcanceRegistros::idsVendedor($filtros)) {
            // Restringido a su vendedor: el filtro de pantalla no aplica (ver resolverAlcance()).
            $propio = \App\Helpers\AlcanceRegistros::vendedorPropio($filtros, $idEmpresa, (int) ($_SESSION['id_usuario'] ?? 0));
            $vendedorTxt = $propio[0]['nombre'] ?? 'Su vendedor';
        } elseif (!empty($filtros['id_vendedor'])) {
            $v = (new \App\repositories\modulos\VendedorRepository())->findById((int)$filtros['id_vendedor'], $idEmpresa);
            $vendedorTxt = $v['nombre'] ?? ('#' . (int)$filtros['id_vendedor']);
        }

        $clienteTxt = 'Todos';
        if (!empty($filtros['id_cliente'])) {
            $ids = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if ($ids) {
                // El filtro viene expandido a las demás filas del mismo cliente (cédula/RUC y,
                // en consolidado, otros establecimientos): se nombra una sola vez por cliente.
                $nombres = [];
                $vistos  = [];
                foreach ($this->repo->getClientesPorIds($ids, $idsEmpresa) as $id => $c) {
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

        $alcanceTxt = 'Este establecimiento';
        if (($filtros['alcance'] ?? '') === 'CONSOLIDADO') {
            $etq = (new EmpresaRepository())->getEtiquetasEstablecimiento($idsEmpresa);
            $alcanceTxt = 'Consolidado por RUC (' . count($etq) . ' establecimientos: ' . implode(' · ', $etq) . ')';
        }

        return [
            'Alcance'           => $alcanceTxt,
            'Tipo de documento' => $tipoDocLbl[$filtros['tipo_doc'] ?? 'TODOS'] ?? 'Todos',
            'Estado'            => $estadoLbl[$filtros['estado'] ?? 'PENDIENTES'] ?? (string)($filtros['estado'] ?? ''),
            'Vendedor'          => $vendedorTxt,
            'Producto'          => $this->describirProductos($filtros, $idsEmpresa),
            'Período'           => $periodo,
            'Cliente'           => $clienteTxt,
        ];
    }

    /**
     * Texto del filtro Producto para el Excel: productos elegidos (una vez por código) y,
     * si además hay texto libre, se agrega entre comillas.
     */
    private function describirProductos(array $filtros, array $idsEmpresa): string
    {
        $partes = [];
        $ids = $filtros['id_producto'] ?? '';
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : explode(',', (string)$ids)))));
        if ($ids) {
            $vistos = [];
            foreach ($this->repo->getProductosPorIds($ids, $idsEmpresa) as $id => $p) {
                $clave = $p['codigo'] !== '' ? 'c:' . $p['codigo'] : 'id:' . $id;
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;
                $partes[] = trim(($p['codigo'] !== '' ? $p['codigo'] . ' - ' : '') . $p['nombre']);
            }
            if (!$partes) {
                $partes = array_map(static fn ($i) => "#{$i}", $ids);
            }
        }
        $txt = trim((string)($filtros['producto'] ?? ''));
        if ($txt !== '') {
            $partes[] = "\"{$txt}\"";
        }
        return $partes ? implode(', ', $partes) : 'Todos';
    }

    /**
     * Encabezado de los PDF del módulo: logo del establecimiento a la izquierda del nombre de
     * la empresa, con el título del reporte y la fecha de generación. Html2Pdf no admite float
     * ni flex, así que va en una tabla de tres celdas —logo | textos | celda vacía del mismo
     * ancho que la del logo— para que el nombre siga centrado en la hoja. Sin logo sale el
     * encabezado centrado de siempre. Los textos toman los estilos .header de cada PDF.
     */
    private function encabezadoPdf(int $idEmpresa, string $nombreEmpresa, string $titulo): string
    {
        $textos = '<h2>' . htmlspecialchars($nombreEmpresa) . '</h2>'
            . '<h3>' . htmlspecialchars($titulo) . '</h3>'
            . '<p>Generado: ' . date('d-m-Y H:i:s') . '</p>';
        $logo = $this->logoPdf($idEmpresa);
        if ($logo === '') {
            return "<div class=\"header\">{$textos}</div>";
        }
        $celda = 'border:none;padding:0;vertical-align:middle;';
        return '<table style="margin-bottom:10px;"><tr>'
            . "<td style=\"width:22%;{$celda}\"><img src=\"" . htmlspecialchars($logo) . "\" style=\"max-width:40mm;max-height:18mm;\"></td>"
            . "<td style=\"width:56%;{$celda}\"><div class=\"header\" style=\"margin-bottom:0;\">{$textos}</div></td>"
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
        $ruta = (string)((new \App\models\Empresa())->getEstablecimientos($idEmpresa)[0]['logo_ruta'] ?? '');
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

    /** Etiqueta legible del origen de una fila del listado unificado. */
    private function getOrigenLabel(string $origen): string
    {
        return match ($origen) {
            'SALDO_INICIAL' => 'Saldo inicial',
            'RECIBO'        => 'Recibo',
            default         => 'Factura',
        };
    }

    private function renderEmailBody(array $factura, string $mensajeExtra, string $docLabel = 'Factura'): string
    {
        $nombre   = htmlspecialchars($factura['cliente_nombre'] ?? '');
        $nroFact  = htmlspecialchars($factura['numero_factura'] ?? '');
        $docLabel = htmlspecialchars($docLabel);
        $total    = '$' . number_format((float)($factura['importe_total'] ?? 0), 2);
        $saldo    = '$' . number_format((float)($factura['saldo'] ?? 0), 2);
        $vence    = !empty($factura['fecha_vencimiento'])
                    ? date('d-m-Y', strtotime($factura['fecha_vencimiento']))
                    : '—';
        $msg      = nl2br(htmlspecialchars($mensajeExtra));

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#333;}
.card{background:#f8f9fa;border-left:4px solid #e63946;padding:16px 20px;margin:16px 0;border-radius:4px;}
.label{color:#6c757d;font-size:12px;text-transform:uppercase;margin-bottom:2px;}
.value{font-size:16px;font-weight:bold;}
.saldo{color:#e63946;font-size:22px;}
.footer{color:#aaa;font-size:11px;margin-top:20px;}
</style></head><body>
<p>Estimado/a <strong>{$nombre}</strong>,</p>
<p>Le recordamos que tiene un saldo pendiente de pago correspondiente a:</p>
<div class="card">
  <div><div class="label">{$docLabel}</div><div class="value">{$nroFact}</div></div>
  <div style="margin-top:10px;"><div class="label">Total {$docLabel}</div><div class="value">{$total}</div></div>
  <div style="margin-top:10px;"><div class="label">Saldo Pendiente</div><div class="saldo">{$saldo}</div></div>
  <div style="margin-top:10px;"><div class="label">Fecha de Vencimiento</div><div class="value">{$vence}</div></div>
</div>
{$msg}
<p>Por favor, regularice su cuenta a la brevedad posible. Si ya realizó el pago, por favor ignorer este mensaje.</p>
<p class="footer">Este es un mensaje automático. Por favor no responda a este correo.</p>
</body></html>
HTML;
    }

    /**
     * Cuerpo HTML del correo masivo: tabla resumen de los documentos
     * pendientes de un cliente (facturas y recibos) con el total. Estilos
     * inline para máxima compatibilidad con clientes de correo.
     */
    private function renderEmailBodyResumen(string $nombreCliente, array $docs): string
    {
        $nombre = htmlspecialchars($nombreCliente);
        $nDocs  = count($docs);
        $intro  = $nDocs === 1
            ? 'el siguiente documento'
            : "los siguientes <strong>{$nDocs} documentos</strong>";

        $tdBase  = 'padding:6px 10px;border-bottom:1px solid #e9ecef;font-size:13px;';
        $filas   = '';
        $totalPend = 0.0;
        foreach ($docs as $d) {
            $totalPend += (float)$d['saldo'];
            $numero   = htmlspecialchars(($d['tipo'] ?? 'Factura') . ' ' . ($d['numero'] ?? ''));
            $fEmis    = !empty($d['fecha_emision']) ? date('d-m-Y', strtotime($d['fecha_emision'])) : '—';
            $fVenc    = !empty($d['fecha_venc'])    ? date('d-m-Y', strtotime($d['fecha_venc']))    : '—';
            $dias     = (int)($d['dias_vencido'] ?? 0);
            $vencTxt  = $dias > 0
                ? "<div style=\"color:#e63946;font-size:11px;font-weight:bold;\">Vencido {$dias} día" . ($dias === 1 ? '' : 's') . "</div>"
                : '';
            $totalFmt = number_format((float)$d['total'], 2);
            $saldoFmt = number_format((float)$d['saldo'], 2);
            $filas .= "<tr>
                <td style=\"{$tdBase}\">{$numero}</td>
                <td style=\"{$tdBase}text-align:center;\">{$fEmis}</td>
                <td style=\"{$tdBase}text-align:center;\">{$fVenc}{$vencTxt}</td>
                <td style=\"{$tdBase}text-align:right;\">\${$totalFmt}</td>
                <td style=\"{$tdBase}text-align:right;font-weight:bold;color:#e63946;\">\${$saldoFmt}</td>
            </tr>";
        }
        $totalPendFmt = number_format($totalPend, 2);

        $thBase = 'padding:8px 10px;font-size:12px;';

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;">
<p>Estimado/a <strong>{$nombre}</strong>,</p>
<p>Le recordamos que mantiene un saldo pendiente de pago en {$intro}:</p>
<table style="border-collapse:collapse;width:100%;max-width:680px;background:#f8f9fa;" cellpadding="0" cellspacing="0">
  <thead>
    <tr style="background:#343a40;color:#ffffff;">
      <th style="{$thBase}text-align:left;">Documento</th>
      <th style="{$thBase}text-align:center;">Emisión</th>
      <th style="{$thBase}text-align:center;">Vencimiento</th>
      <th style="{$thBase}text-align:right;">Total</th>
      <th style="{$thBase}text-align:right;">Saldo</th>
    </tr>
  </thead>
  <tbody>{$filas}</tbody>
  <tfoot>
    <tr>
      <td colspan="4" style="padding:10px;text-align:right;font-weight:bold;border-top:2px solid #343a40;">TOTAL PENDIENTE:</td>
      <td style="padding:10px;text-align:right;font-weight:bold;color:#e63946;font-size:18px;border-top:2px solid #343a40;white-space:nowrap;">\${$totalPendFmt}</td>
    </tr>
  </tfoot>
</table>
<p>Por favor, regularice su cuenta a la brevedad posible. Si ya realizó el pago de alguno de estos documentos, por favor ignore este mensaje.</p>
<p style="color:#aaa;font-size:11px;margin-top:20px;">Este es un mensaje automático. Por favor no responda a este correo.</p>
</body></html>
HTML;
    }

    // ─── SALDOS INICIALES CXC (para mostrar en la vista de CXC) ─────────────

    public function getSaldosInicialesCxcAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = [
            'estado'     => $_GET['estado']     ?? 'TODOS',
            'id_cliente' => $_GET['id_cliente'] ?? '',
        ] + $this->alcanceUsuario([$idEmpresa]); // Alcance del usuario (§6)
        $filas = $this->repo->getSaldosInicialesCxc($idEmpresa, $filtros);
        $this->jsonSuccess(['filas' => $filas]);
    }

    /**
     * Cobro de un saldo inicial CXC desde la tabla unificada de Cuentas por Cobrar.
     * Delega en SaldosInicialesService::registrarCobroCxc (mismo flujo de ingresos).
     */
    public function registrarCobroSaldoInicialAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = $this->empresaEscritura(); // consolidado: cobro en los libros de la hermana dueña
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idSaldo = (int)($_POST['id_saldo'] ?? 0);
        $idPunto = (int)($_POST['id_punto_emision'] ?? 0);
        $monto   = (float)($_POST['monto'] ?? 0);
        $idForma = (int)($_POST['id_forma_cobro'] ?? 0);

        if ($idSaldo <= 0 || $idPunto <= 0 || $monto <= 0 || $idForma <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de cobro.');
            return;
        }

        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('La serie (punto de emisión) no es válida o está inactiva.');
            return;
        }

        // Registros propios (§6): sin acceso total solo se cobran los saldos que él cargó
        if (!$this->saldoInicialPropioOCortar($idSaldo, $idEmpresa)) {
            $this->jsonError('Saldo inicial no encontrado.');
            return;
        }

        try {
            $service = new \App\Services\modulos\SaldosInicialesService(
                new \App\repositories\modulos\SaldosInicialesRepository(),
                new \App\Rules\modulos\SaldosInicialesRules(),
                new \App\Services\LogSistemaService()
            );
            $result = $service->registrarCobroCxc($idSaldo, $idEmpresa, $idUsuario, [
                'id_punto_emision'       => $idPunto,
                'punto'                  => $punto,
                'monto'                  => $monto,
                'id_forma_cobro'         => $idForma,
                'id_ingreso_concepto'    => !empty($_POST['id_ingreso_concepto']) ? (int)$_POST['id_ingreso_concepto'] : null,
                'fecha_cobro'            => $_POST['fecha_cobro'] ?? date('Y-m-d'),
                'observaciones'          => $_POST['observaciones'] ?? '',
                'tipo_operacion_bancaria'=> $_POST['tipo_operacion_bancaria'] ?? '',
                'numero_operacion'       => $_POST['numero_operacion'] ?? '',
            ]);
            $this->jsonSuccess(array_merge($result, [
                'mensaje'     => "Cobro registrado correctamente. Ingreso: {$result['numero_ingreso']}",
                'nuevo_saldo' => $result['nuevo_saldo'] ?? null,
                'pagada'      => $result['pagado'] ?? false,
            ]));
        } catch (\Throwable $e) {
            error_log('[CxC cobro saldo inicial] ' . $e->getMessage());
            $this->jsonError('Error al registrar el cobro: ' . $e->getMessage());
        }
    }

    /**
     * Historial de cobros de un saldo inicial CXC (para la tabla unificada).
     */
    public function historialCobrosSaldoInicialAjax(): void
    {
        $this->requireLeer();
        $this->requireHistorialCobros();
        $idEmpresa = $this->empresaLectura(); // consolidado: puede ser una hermana (solo lectura)
        $idSaldo   = (int)($_GET['id_saldo'] ?? 0);
        if ($idSaldo <= 0) {
            $this->jsonError('ID de saldo inválido.');
            return;
        }
        $this->saldoInicialPropioOCortar($idSaldo, $idEmpresa); // registros propios (§6)
        $repo = new \App\repositories\modulos\SaldosInicialesRepository();
        $historial = $repo->getHistorialCobrosCxc($idSaldo, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }
}
