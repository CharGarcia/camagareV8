<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\ReportePdf;
use App\repositories\modulos\CajaMovimientoRepository;
use App\repositories\modulos\ReporteResumenDiarioRepository;
use App\Services\modulos\CajaMovimientoService;
use App\Services\modulos\ReporteResumenDiarioService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Resumen diario: ventas, compras, ingresos, egresos y traslados de UN día, con el saldo
 * por forma de pago, en pantalla, PDF y Excel. También registra los traslados entre
 * formas de pago y los saldos de apertura. La lógica vive en ReporteResumenDiarioService y
 * CajaMovimientoService; aquí solo se recibe la petición y se presenta.
 */
class ReporteResumenDiarioController extends BaseModuloController
{
    private const XL_DINERO = '#,##0.00';
    private const SIN_TABLAS = 'Falta aplicar en la base de datos el SQL database/20261008_caja_saldos_traslados.sql.';

    private ReporteResumenDiarioService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_resumen_diario';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new ReporteResumenDiarioService();
    }

    public function index(): void
    {
        $this->requireLeer();
        $this->viewWithLayout('layouts.main', 'modulos/reporte_resumen_diario/index', [
            'titulo'     => 'Resumen Diario',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'formas'     => (new CajaMovimientoRepository())->getFormasPago((int) $_SESSION['id_empresa']),
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    /** Filtros de la petición: el día y si cuentan los borradores. */
    private function filtros(): array
    {
        return [
            'fecha'      => trim((string) ($_GET['fecha'] ?? date('Y-m-d'))),
            'borradores' => ($_GET['borradores'] ?? '') === 'INCLUIR',
        ];
    }

    /** Sin acceso total ('t') el usuario solo ve/gestiona lo que él registró (§6). */
    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    private function datos(array $f): array
    {
        return $this->service->generar((int) $_SESSION['id_empresa'], $f['fecha'], $f['borradores'], $this->idUsuarioFiltro());
    }

    /** Filtros en texto, para el PDF y el Excel. */
    private function filtrosTxt(array $f): array
    {
        $txt = ['Día' => date('d-m-Y', strtotime($f['fecha']))];
        $txt['Borradores'] = $f['borradores'] ? 'Incluidos' : 'No incluidos';
        if ($this->idUsuarioFiltro() !== null) {
            $txt['Alcance'] = 'Solo lo registrado por ' . (string) ($_SESSION['nombre'] ?? 'el usuario');
        }
        return $txt;
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        session_write_close();
        header('Content-Type: application/json');
        $f = $this->filtros();
        try {
            $datos = $this->datos($f);
            ob_start();
            $this->view('modulos/reporte_resumen_diario/contenido', ['datos' => $datos, 'perm' => $this->getPermisos()]);
            $html = (string) ob_get_clean();

            $qs = http_build_query(['fecha' => $datos['fecha'], 'borradores' => $f['borradores'] ? 'INCLUIR' : '']);
            $urlBase = BASE_URL . '/' . $this->getRutaModulo();
            echo json_encode([
                'ok'        => true,
                'html'      => $html,
                'kpis'      => $datos['kpis'],
                'pdf_url'   => "{$urlBase}/exportPdf?{$qs}",
                'excel_url' => "{$urlBase}/exportExcel?{$qs}",
            ]);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo generar el resumen diario.']);
        }
        exit;
    }

    // ── Traslados y saldos de apertura ────────────────────────────────────────

    private function tablasCaja(): bool
    {
        return (new ReporteResumenDiarioRepository())->tablasCajaDisponibles();
    }

    public function guardarTrasladoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        if (!$this->tablasCaja()) {
            echo json_encode(['ok' => false, 'mensaje' => self::SIN_TABLAS]);
            exit;
        }
        try {
            $res = (new CajaMovimientoService())->crearTraslado(
                $_POST, (string) ($_POST['token_guardado'] ?? ''),
                (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']
            );
            echo json_encode(['ok' => true, 'id' => $res['id'], 'mensaje' => $res['ya_existia']
                ? 'Ese traslado ya estaba registrado; no se creó otro.'
                : 'Traslado registrado.']);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo registrar el traslado.']);
        }
        exit;
    }

    public function eliminarTrasladoAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            (new CajaMovimientoService())->eliminarTraslado(
                (int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $this->idUsuarioFiltro()
            );
            echo json_encode(['ok' => true, 'mensaje' => 'Traslado eliminado.']);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo eliminar el traslado.']);
        }
        exit;
    }

    /** Formas de pago con su saldo de apertura (para el modal). */
    public function aperturasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        if (!$this->tablasCaja()) {
            echo json_encode(['ok' => false, 'mensaje' => self::SIN_TABLAS]);
            exit;
        }
        echo json_encode(['ok' => true, 'formas' => (new CajaMovimientoService())->listarAperturas((int) $_SESSION['id_empresa'])]);
        exit;
    }

    /** Los saldos de apertura son de toda la empresa: piden permiso de modificar y acceso total. */
    public function guardarAperturasAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        if ($this->idUsuarioFiltro() !== null) {
            echo json_encode(['ok' => false, 'mensaje' => 'Los saldos de apertura son de toda la empresa: hace falta acceso total en este módulo.']);
            exit;
        }
        if (!$this->tablasCaja()) {
            echo json_encode(['ok' => false, 'mensaje' => self::SIN_TABLAS]);
            exit;
        }
        try {
            $filas   = json_decode((string) ($_POST['aperturas'] ?? '[]'), true);
            $cambios = (new CajaMovimientoService())->guardarAperturas(
                is_array($filas) ? $filas : [], (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']
            );
            echo json_encode(['ok' => true, 'mensaje' => $cambios ? "Saldos de apertura guardados ({$cambios})." : 'No había cambios.']);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudieron guardar los saldos de apertura.']);
        }
        exit;
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();
        session_write_close();
        $f = $this->filtros();
        try {
            $datos     = $this->datos($f);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $empresa   = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $pt        = 7.5;
            $k         = $datos['kpis'];

            $kpis = [
                ['Ventas netas', $this->dinero($k['ventas_netas'])],
                ['Compras netas', $this->dinero($k['compras_netas'])],
                ['Ingresos', $this->dinero($k['ingresos'])],
                ['Egresos', $this->dinero($k['egresos'])],
                ['Neto del día', $this->dinero($k['neto_caja']), $k['saldo_final'] === null],
            ];
            if ($k['saldo_final'] !== null) {
                $kpis[] = ['Saldo final', $this->dinero($k['saldo_final']), true];
            }
            $html = ReportePdf::encabezado($idEmpresa, (string) ($empresa['nombre'] ?? ''), 'Resumen diario',
                        'Día ' . date('d-m-Y', strtotime($datos['fecha'])))
                  . ReportePdf::filtros($this->filtrosTxt($f))
                  . ReportePdf::indicadores($kpis);

            if (!$datos['grupos']) {
                $html .= "<p style='text-align:center;font-size:9pt;'>No hay documentos ni movimientos en este día.</p>";
            }
            foreach ($datos['grupos'] as $g) {
                $html .= $this->bandaPdf(mb_strtoupper($g['titulo']));
                foreach ($g['secciones'] as $s) {
                    $html .= $this->seccionPdf($s, $pt);
                }
            }
            if ($datos['grupos'] || $datos['resumen']['caja']) {
                $html .= $this->resumenPdf($datos, $pt);
            }
            $html .= $this->firmasPdf();

            $autoload = \MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $pdf->writeHTML(ReportePdf::pagina($pt, $html));
            $pdf->output('ResumenDiario_' . str_replace('-', '', $datos['fecha']) . '.pdf', 'D');
        } catch (\Throwable $e) {
            if (!$e instanceof \InvalidArgumentException) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    private function dinero(float $v): string
    {
        return ($v < 0 ? '-$' : '$') . number_format(abs($v), 2);
    }

    /** Título de un bloque (Ventas e ingresos / Compras y egresos / Resumen): negro, subrayado, sin fondo. */
    private function bandaPdf(string $titulo): string
    {
        return "<table style='margin-top:8px;'><tr><td style='width:100%;color:#000;border:none;border-bottom:1.5px solid #000;"
             . "font-weight:bold;font-size:10pt;padding:2px 0;'>" . htmlspecialchars($titulo) . '</td></tr></table>';
    }

    /** Título de la sección y su listado (solo llegan secciones con filas). */
    private function seccionPdf(array $s, float $pt): string
    {
        $n      = count($s['filas']);
        $titulo = mb_strtoupper($s['titulo']) . " ({$n})" . ($s['nota'] !== '' ? ' - ' . $s['nota'] : '');
        $html   = "<table class='fil-tit'><tr><td style='width:100%;'>" . htmlspecialchars($titulo) . '</td></tr></table>';
        $cols   = [];
        foreach ($s['columnas'] as $c) {
            $k = $c['k'];
            $w = (float) $c['w'];
            if (!empty($c['num'])) {
                $cols[] = ['lbl' => $c['lbl'], 'w' => $w, 'cls' => 'text-end',
                    'val' => static fn (array $r): string => number_format((float) $r[$k], 2),
                    'tot' => static fn (array $filas): float => array_sum(array_map(static fn ($r) => (float) $r[$k], $filas))];
            } else {
                $ancho  = ReportePdf::anchoPt($w, false);
                $cols[] = ['lbl' => $c['lbl'], 'w' => $w,
                    'val' => static fn (array $r): string => ReportePdf::texto((string) $r[$k], $ancho, $pt)];
            }
        }
        return $html . ReportePdf::listado($cols, $s['filas'], 'TOTAL');
    }

    /** Resumen final: ventas, compras y caja por forma de pago (solo lo que existe). */
    private function resumenPdf(array $datos, float $pt): string
    {
        $r    = $datos['resumen'];
        $html = $this->bandaPdf('RESUMEN DEL DÍA');
        foreach ([['VENTAS', $r['ventas']], ['COMPRAS', $r['compras']]] as [$titulo, $lineas]) {
            if (!$lineas) {
                continue;
            }
            $html .= "<table class='fil-tit'><tr><td style='width:100%;'>{$titulo}</td></tr></table><table>";
            foreach ($lineas as [$lbl, $val, $estilo]) {
                $st = $estilo === 'bold' ? 'font-weight:bold;background:#e3e9f0;' : ($estilo === 'sub' ? 'color:#6a747e;' : '');
                $html .= "<tr><td style='width:80%;{$st}'>" . htmlspecialchars($lbl) . '</td>'
                       . "<td class='text-end' style='width:20%;{$st}'>" . $this->dinero((float) $val) . '</td></tr>';
            }
            $html .= '</table>';
        }
        if ($r['caja']) {
            $html .= $this->seccionPdf($r['caja'], $pt);
        }
        return $html;
    }

    /** Firmas: "Realizado por" con quien genera el resumen y "Aprobado por" en blanco. */
    private function firmasPdf(): string
    {
        $celda = 'border:none;border-top:1px solid #000;text-align:center;padding-top:3px;font-size:8.5pt;';
        return "<nobreak><table style='margin-top:15mm;'><tr>"
             . "<td style='width:18%;border:none;'></td>"
             . "<td style='width:28%;{$celda}'><b>Realizado por</b><br>" . htmlspecialchars((string) ($_SESSION['nombre'] ?? '')) . '</td>'
             . "<td style='width:8%;border:none;'></td>"
             . "<td style='width:28%;{$celda}'><b>Aprobado por</b><br>&nbsp;</td>"
             . "<td style='width:18%;border:none;'></td>"
             . '</tr></table></nobreak>';
    }

    // ── Excel ─────────────────────────────────────────────────────────────────

    /**
     * Una sola hoja con las mismas secciones que la pantalla y el PDF, una debajo de otra
     * (título, encabezados, filas y total), y al final el resumen del día. Los valores van
     * como números de Excel, con formato de dinero.
     */
    public function exportExcel(): void
    {
        $this->requireLeer();
        session_write_close();
        $f = $this->filtros();
        try {
            $datos   = $this->datos($f);
            $empresa = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']) ?? [];

            $libro  = new Spreadsheet();
            $hoja   = $libro->getActiveSheet();
            $hoja->setTitle('Resumen diario');
            $ultima = Coordinate::stringFromColumnIndex(6);

            $hoja->setCellValue('A1', mb_strtoupper((string) ($empresa['nombre'] ?? '')));
            $hoja->mergeCells("A1:{$ultima}1");
            $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $hoja->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $fila = 2;
            foreach ($this->filtrosTxt($f) + ['Generado' => date('d-m-Y H:i:s')] as $lbl => $val) {
                $hoja->setCellValue("A{$fila}", $lbl . ':');
                $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
                $hoja->setCellValueExplicit("B{$fila}", (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $fila++;
            }
            $fila++;

            foreach ($datos['grupos'] as $g) {
                $this->xlBanda($hoja, $fila++, mb_strtoupper($g['titulo']), $ultima);
                foreach ($g['secciones'] as $s) {
                    $fila = $this->xlSeccion($hoja, $fila, $s);
                }
            }
            if ($datos['grupos'] || $datos['resumen']['caja']) {
                $fila = $this->xlResumen($hoja, $fila, $datos['resumen'], $ultima);
            } else {
                $hoja->setCellValue("A{$fila}", 'No hay documentos ni movimientos en este día.');
            }

            // Anchos: las columnas de texto largas (cliente, detalle) a ancho fijo con ajuste.
            foreach ([1 => 24, 2 => 38, 3 => 32, 4 => 18, 5 => 14, 6 => 14] as $col => $ancho) {
                $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth($ancho);
            }
            $hoja->getStyle("A1:{$ultima}{$fila}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

            (new \App\Services\ReportService())->descargarSpreadsheet($libro, 'ResumenDiario_' . str_replace('-', '', $datos['fecha']));
        } catch (\Throwable $e) {
            if (!$e instanceof \InvalidArgumentException) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    /** Resumen del día en el Excel: ventas, compras y caja por forma de pago (solo lo que existe). */
    private function xlResumen($hoja, int $fila, array $r, string $ultima): int
    {
        $this->xlBanda($hoja, $fila++, 'RESUMEN DEL DÍA', $ultima);
        foreach ([['Ventas', $r['ventas']], ['Compras', $r['compras']]] as [$titulo, $lineas]) {
            if (!$lineas) {
                continue;
            }
            $this->xlTituloSeccion($hoja, $fila++, $titulo, $ultima);
            foreach ($lineas as [$lbl, $val, $estilo]) {
                $hoja->setCellValue("A{$fila}", $lbl);
                $hoja->setCellValue("B{$fila}", (float) $val);
                $hoja->getStyle("B{$fila}")->getNumberFormat()->setFormatCode(self::XL_DINERO);
                if ($estilo === 'bold') {
                    $hoja->getStyle("A{$fila}:B{$fila}")->getFont()->setBold(true);
                }
                $fila++;
            }
            $fila++;
        }
        return $r['caja'] ? $this->xlSeccion($hoja, $fila, $r['caja']) : $fila;
    }

    /** Título de un bloque: negrita, subrayado, sin color de fondo. */
    private function xlBanda($hoja, int $fila, string $titulo, string $ultima): void
    {
        $hoja->setCellValue("A{$fila}", $titulo);
        $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
        $hoja->getStyle("A{$fila}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 12],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
    }

    private function xlTituloSeccion($hoja, int $fila, string $titulo, string $ultima): void
    {
        $hoja->setCellValue("A{$fila}", $titulo);
        $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
        $hoja->getStyle("A{$fila}")->applyFromArray(['font' => ['bold' => true]]);
    }

    /** Escribe una sección (título, encabezados, filas y total) y devuelve la fila siguiente libre. */
    private function xlSeccion($hoja, int $fila, array $s): int
    {
        $cols   = $s['columnas'];
        $n      = count($s['filas']);
        $ultima = Coordinate::stringFromColumnIndex(max(count($cols), 6));
        $this->xlTituloSeccion($hoja, $fila++, $s['titulo'] . " ({$n})" . ($s['nota'] !== '' ? ' - ' . $s['nota'] : ''), $ultima);

        $finCols = Coordinate::stringFromColumnIndex(count($cols));
        foreach ($cols as $i => $c) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $fila, $c['lbl']);
        }
        $hoja->getStyle("A{$fila}:{$finCols}{$fila}")->applyFromArray([
            'font'    => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $fila++;

        $primera = $fila;
        foreach ($s['filas'] as $r) {
            foreach ($cols as $i => $c) {
                $celda = Coordinate::stringFromColumnIndex($i + 1) . $fila;
                if (!empty($c['num'])) {
                    $hoja->setCellValue($celda, (float) $r[$c['k']]);
                } else {
                    $hoja->setCellValueExplicit($celda, (string) $r[$c['k']], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $fila++;
        }

        // Total: la etiqueta en la primera columna; cada columna numérica con su total.
        $hoja->setCellValue("A{$fila}", 'TOTAL');
        foreach ($cols as $i => $c) {
            if (!empty($c['num'])) {
                $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $fila, (float) ($s['totales'][$c['k']] ?? 0));
            }
        }
        $hoja->getStyle("A{$fila}:{$finCols}{$fila}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3E9F0']],
        ]);
        foreach ($cols as $i => $c) {
            if (!empty($c['num'])) {
                $l = Coordinate::stringFromColumnIndex($i + 1);
                $hoja->getStyle("{$l}{$primera}:{$l}{$fila}")->getNumberFormat()->setFormatCode(self::XL_DINERO);
            }
        }
        return $fila + 2;
    }
}
