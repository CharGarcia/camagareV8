<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\ReportePdf;
use App\repositories\modulos\CajaMovimientoRepository;
use App\repositories\modulos\ReporteResumenDiarioRepository;
use App\Services\modulos\CajaCierreService;
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
        $cierre = new CajaCierreService();
        $this->viewWithLayout('layouts.main', 'modulos/reporte_resumen_diario/index', [
            'titulo'     => 'Resumen Diario',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'formas'     => (new CajaMovimientoRepository())->getFormasPago((int) $_SESSION['id_empresa']),
            // Selector «Usuario»: solo para quien ve toda la empresa (acceso total).
            'usuarios'   => $this->idUsuarioFiltro() === null && $cierre->disponible()
                ? $cierre->usuarios((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']) : [],
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    /**
     * Filtros de la petición: el día, si cuentan los borradores y el modo de la caja
     * ('' neto del día, 'SI' con saldos iniciales, 'CIERRE' cierre de caja).
     */
    private function filtros(): array
    {
        $caja = (string) ($_GET['saldos'] ?? '');
        return [
            'fecha'      => trim((string) ($_GET['fecha'] ?? date('Y-m-d'))),
            'borradores' => ($_GET['borradores'] ?? '') === 'INCLUIR',
            'saldos'     => $caja === 'SI',
            'cierre'     => $caja === 'CIERRE',
        ];
    }

    /** Sin acceso total ('t') el usuario solo ve/gestiona lo que él registró (§6). */
    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    /**
     * De quién es lo que se ve y la caja que se abre/cierra: sin acceso total, siempre el
     * propio usuario (§6); con acceso total, el elegido en el selector «Usuario» (validado
     * contra la empresa) o null = toda la empresa.
     */
    private function alcanceUsuario(): ?int
    {
        if (($propio = $this->idUsuarioFiltro()) !== null) {
            return $propio;
        }
        $elegido = (int) ($_REQUEST['usuario'] ?? 0);
        if ($elegido <= 0) {
            return null;
        }
        if (!(new CajaCierreService())->usuarioEsDeEmpresa((int) $_SESSION['id_empresa'], $elegido, (int) $_SESSION['id_usuario'])) {
            throw new \InvalidArgumentException('El usuario elegido no pertenece a la empresa.');
        }
        return $elegido;
    }

    /** Nombre del usuario elegido en el selector (para el PDF y el Excel). */
    private function nombreUsuario(int $id): string
    {
        foreach ((new CajaCierreService())->usuarios((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']) as $u) {
            if ((int) $u['id'] === $id) {
                return (string) $u['nombre'];
            }
        }
        return "#{$id}";
    }

    private function datos(array $f): array
    {
        return $this->service->generar((int) $_SESSION['id_empresa'], $f['fecha'], $f['borradores'], $f['saldos'], $this->alcanceUsuario(), $f['cierre']);
    }

    /** Filtros en texto, para el PDF y el Excel. */
    private function filtrosTxt(array $f, array $datos): array
    {
        $alcance = $this->alcanceUsuario();
        $txt = ['Día' => date('d-m-Y', strtotime($f['fecha']))];
        $txt['Borradores'] = $f['borradores'] ? 'Incluidos' : 'No incluidos';
        if ($f['cierre']) {
            $txt['Caja'] = 'Cierre de caja: ' . $this->estadoCajaTxt($datos['cierre'] ?? null);
        } else {
            $txt['Caja'] = $f['saldos'] && $alcance === null ? 'Con saldos iniciales' : 'Neto del día';
        }
        if ($this->idUsuarioFiltro() !== null) {
            $txt['Alcance'] = 'Solo lo registrado por ' . (string) ($_SESSION['nombre'] ?? 'el usuario');
        } elseif ($alcance !== null) {
            $txt['Usuario'] = 'Solo lo registrado por ' . $this->nombreUsuario($alcance);
        }
        return $txt;
    }

    /** Estado de la caja del día en una línea (PDF/Excel). */
    private function estadoCajaTxt(?array $c): string
    {
        $fh = static fn (string $f): string => $f !== '' ? date('d-m-Y H:i:s', strtotime($f)) : '';
        return match (true) {
            $c === null || empty($c['disponible']) => 'no disponible',
            $c['estado'] === 'cerrada' => "cerrada por {$c['cerrada_por']} el " . $fh($c['fecha_cierre']),
            $c['estado'] === 'abierta' => "abierta por {$c['abierta_por']} el " . $fh($c['fecha_apertura']),
            default => 'sin abrir',
        };
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
            $this->view('modulos/reporte_resumen_diario/contenido', [
                'datos' => $datos, 'perm' => $this->getPermisos(),
                'usuarioElegido' => $this->idUsuarioFiltro() === null && $this->alcanceUsuario() !== null,
            ]);
            $html = (string) ob_get_clean();

            $qs = http_build_query([
                'fecha'      => $datos['fecha'],
                'borradores' => $f['borradores'] ? 'INCLUIR' : '',
                'saldos'     => $f['cierre'] ? 'CIERRE' : ($f['saldos'] ? 'SI' : ''),
                'usuario'    => $this->idUsuarioFiltro() === null ? (string) ($this->alcanceUsuario() ?? '') : '',
            ]);
            $urlBase = BASE_URL . '/' . $this->getRutaModulo();
            echo json_encode([
                'ok'        => true,
                'html'      => $html,
                'kpis'      => $datos['kpis'],
                'cierre'    => $datos['cierre'],
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

    // ── Apertura y cierre de caja (Caja: «Cierre de caja») ────────────────────
    // La caja es del día y del alcance: el usuario elegido (o el propio, sin acceso total)
    // o toda la empresa. La lógica vive en CajaCierreService.

    private const SIN_TABLAS_CIERRE = 'Falta aplicar en la base de datos el SQL database/20261020_caja_cierres_diarios.sql.';

    /** Responde JSON con lo que devuelva $accion; errores de negocio con su mensaje. */
    private function jsonCierre(string $accion, string $error, callable $fn): void
    {
        header('Content-Type: application/json');
        try {
            if (!(new CajaCierreService())->disponible()) {
                throw new \InvalidArgumentException(self::SIN_TABLAS_CIERRE);
            }
            echo json_encode(['ok' => true] + $fn());
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
            echo json_encode(['ok' => false, 'mensaje' => $error]);
        }
        exit;
    }

    /** Formas de pago para el modal: ?tipo=abrir (saldo propuesto) o ?tipo=cerrar (esperado). */
    public function cajaFormasAjax(): void
    {
        $this->requireLeer();
        $this->jsonCierre(__FUNCTION__, 'No se pudieron cargar las formas de pago.', function (): array {
            $svc   = new CajaCierreService();
            $fecha = (string) ($_GET['fecha'] ?? '');
            $args  = [(int) $_SESSION['id_empresa'], $fecha, $this->alcanceUsuario()];
            return ['formas' => ($_GET['tipo'] ?? '') === 'cerrar' ? $svc->formasParaCerrar(...$args) : $svc->formasParaAbrir(...$args)];
        });
    }

    public function abrirCajaAjax(): void
    {
        $this->requireCrear();
        $this->jsonCierre(__FUNCTION__, 'No se pudo abrir la caja.', function (): array {
            $filas = json_decode((string) ($_POST['saldos'] ?? '[]'), true);
            $res = (new CajaCierreService())->abrir(
                (int) $_SESSION['id_empresa'], (string) ($_POST['fecha'] ?? ''), $this->alcanceUsuario(),
                is_array($filas) ? $filas : [], (string) ($_POST['observaciones'] ?? ''),
                (string) ($_POST['token_guardado'] ?? ''), (int) $_SESSION['id_usuario']
            );
            return ['mensaje' => $res['ya_existia'] ? 'La caja ya estaba abierta; no se abrió otra.' : 'Caja abierta.'];
        });
    }

    public function cerrarCajaAjax(): void
    {
        $this->requireCrear();
        $this->jsonCierre(__FUNCTION__, 'No se pudo cerrar la caja.', function (): array {
            $filas = json_decode((string) ($_POST['contados'] ?? '[]'), true);
            $tot = (new CajaCierreService())->cerrar(
                (int) $_SESSION['id_empresa'], (string) ($_POST['fecha'] ?? ''), $this->alcanceUsuario(),
                is_array($filas) ? $filas : [], (string) ($_POST['observaciones'] ?? ''), (int) $_SESSION['id_usuario']
            );
            return ['mensaje' => 'Caja cerrada.', 'totales' => $tot];
        });
    }

    /** Reabrir borra el arqueo: pide modificar y acceso total. */
    public function reabrirCajaAjax(): void
    {
        $this->requireActualizar();
        if ($this->idUsuarioFiltro() !== null) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'mensaje' => 'Para reabrir una caja cerrada hace falta acceso total en este módulo.']);
            exit;
        }
        $this->jsonCierre(__FUNCTION__, 'No se pudo reabrir la caja.', function (): array {
            (new CajaCierreService())->reabrir(
                (int) $_SESSION['id_empresa'], (string) ($_POST['fecha'] ?? ''), $this->alcanceUsuario(),
                (string) ($_POST['motivo'] ?? ''), (int) $_SESSION['id_usuario']
            );
            return ['mensaje' => 'Caja reabierta.'];
        });
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
                $kpis[] = [$f['cierre'] ? 'Saldo esperado' : 'Saldo final', $this->dinero($k['saldo_final']), true];
            }
            if (($datos['cierre']['estado'] ?? '') === 'cerrada') {
                $kpis[] = ['Contado', $this->dinero($datos['cierre']['contado'])];
                $kpis[] = ['Diferencia', $this->dinero($datos['cierre']['diferencia']), true];
            }
            // Piezas en orden: HTML suelto (string) o un listado de sección (array).
            $piezas = [ReportePdf::encabezado($idEmpresa, (string) ($empresa['nombre'] ?? ''), 'Resumen diario',
                            'Día ' . date('d-m-Y', strtotime($datos['fecha'])))
                      . ReportePdf::filtros($this->filtrosTxt($f, $datos))
                      . ReportePdf::indicadores($kpis)];

            if (!$datos['grupos']) {
                $piezas[] = "<p style='text-align:center;font-size:9pt;'>No hay documentos ni movimientos en este día.</p>";
            }
            foreach ($datos['grupos'] as $g) {
                $piezas[] = $this->bandaPdf(mb_strtoupper($g['titulo']));
                foreach ($g['secciones'] as $s) {
                    $piezas[] = $this->seccionPdf($s, $pt);
                }
            }
            if ($datos['grupos'] || $datos['resumen']['caja']) {
                array_push($piezas, ...$this->resumenPdf($datos, $pt));
            }
            $piezas[] = $this->firmasPdf();

            $autoload = \MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $this->escribirPiezasPdf($pdf, $pt, $piezas);
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

    /**
     * Escribe las piezas del PDF: el HTML suelto se va juntando y cada listado se escribe
     * por bloques (ReportePdf::escribirListado) detrás de lo acumulado. En un solo
     * writeHTML el tiempo crece al cuadrado de las filas, y un día con muchas facturas
     * tardaba minutos.
     */
    private function escribirPiezasPdf(\Spipu\Html2Pdf\Html2Pdf $pdf, float $pt, array $piezas): void
    {
        $pendiente = ReportePdf::inicio($pt, (string) array_shift($piezas));
        foreach ($piezas as $p) {
            if (is_string($p)) {
                $pendiente .= $p;
                continue;
            }
            ReportePdf::escribirListado($pdf, $pt, $pendiente . $p['titulo'], $p['cols'], $p['filas'], 'TOTAL');
            $pendiente = '';
        }
        if ($pendiente !== '') {
            $pdf->writeHTML($pendiente);
        }
    }

    /** Título de la sección y su listado (solo llegan secciones con filas), como pieza del PDF. */
    private function seccionPdf(array $s, float $pt): array
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
        return ['titulo' => $html, 'cols' => $cols, 'filas' => $s['filas']];
    }

    /** Resumen final: ventas, compras y caja por forma de pago (solo lo que existe), como piezas del PDF. */
    private function resumenPdf(array $datos, float $pt): array
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
        $piezas = [$html];
        if ($r['caja']) {
            $piezas[] = $this->seccionPdf($r['caja'], $pt);
        }
        return $piezas;
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
            foreach ($this->filtrosTxt($f, $datos) + ['Generado' => date('d-m-Y H:i:s')] as $lbl => $val) {
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
