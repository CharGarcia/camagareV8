<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\repositories\modulos\ConciliacionTarjetasRepository;
use App\Rules\modulos\ConciliacionTarjetasRules;
use App\Services\ErrorLogService;
use App\Services\LogSistemaService;
use App\Services\modulos\ConciliacionTarjetasImportService;
use App\Services\modulos\ConciliacionTarjetasMatchService;
use App\Services\modulos\ConciliacionTarjetasService;

/**
 * Conciliación de Tarjetas: cruza el estado de cuenta de la procesadora
 * (Payphone, Nuvei, el banco del datáfono) contra los cobros con tarjeta ya
 * registrados, para saber qué se depositó, qué falta por depositar y qué entró
 * al banco sin documento.
 *
 * Sin lógica de negocio: valida lo mínimo, delega en el Service y responde.
 */
class ConciliacionTarjetasController extends BaseModuloController
{
    private ConciliacionTarjetasService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/conciliacion-tarjetas';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new ConciliacionTarjetasService(
            new ConciliacionTarjetasRepository(),
            new ConciliacionTarjetasRules(),
            new ConciliacionTarjetasImportService(),
            new ConciliacionTarjetasMatchService(),
            new LogSistemaService()
        );
    }

    /** Filas por página del listado. */
    private const POR_PAGINA = 50;

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $vista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $this->viewWithLayout('layouts.main', 'modulos.conciliacion_tarjetas.index', [
            'titulo'       => 'Conciliación de Tarjetas',
            'perm'         => $this->getPermisos(),
            'rutaModulo'   => $this->getRutaModulo(),
            'procesadoras' => $this->service->getProcesadoras($idEmpresa),
            'destinos'     => $this->service->getFormasDestino($idEmpresa),
            'vistaConfig'  => $vista,
            'ordenJson'    => OrdenListado::aJson($this->ordenConciliaciones($vista)),
            // Listado estándar (§9), igual que Proveedores: ancho completo.
            'fullWidth'    => true,
        ]);
    }

    // ─── Listado ─────────────────────────────────────────────────────────────

    public function listarAjax(): void
    {
        $this->requireLeer();
        $this->responder(function () {
            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $orden = $this->ordenConciliaciones(PreferenciasHelper::getPreferenciasVista($this->getRutaModulo()));

            $resultado = $this->service->getListado(
                (int) $_SESSION['id_empresa'],
                trim((string) ($_GET['buscar'] ?? '')),
                $page,
                self::POR_PAGINA,
                $orden,
                $this->idUsuarioFiltro()
            );

            return $resultado + ['page' => $page, 'per_page' => self::POR_PAGINA, 'orden' => OrdenListado::aCadena($orden)];
        });
    }

    /**
     * Pestaña «Asiento contable» del modal: dice si el asiento ya existe (entonces el
     * componente lo lee de Asientos Contables) o por qué no hay. Responde en el formato
     * plano que espera asiento_contable_tab.js, no en el de responder().
     */
    public function estadoAsientoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            echo json_encode($this->service->getEstadoAsiento((int) ($_GET['id'] ?? 0), (int) $_SESSION['id_empresa']));
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function detalleAjax(): void
    {
        $this->requireLeer();
        $this->responder(fn() => $this->service->getDetalle(
            (int) ($_GET['id'] ?? 0),
            (int) $_SESSION['id_empresa'],
            $this->idUsuarioFiltro()
        ));
    }

    // ─── Conciliación ────────────────────────────────────────────────────────

    public function guardarAjax(): void
    {
        $data = $this->payload();
        $id   = (int) ($data['id'] ?? 0);

        $id > 0 ? $this->requireActualizar() : $this->requireCrear();

        $this->responder(function () use ($data, $id) {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if ($id > 0) {
                $this->service->actualizar($id, $idEmpresa, $idUsuario, $data);
                return ['id' => $id];
            }
            return ['id' => $this->service->crear($idEmpresa, $idUsuario, $data)];
        });
    }

    public function importarAjax(): void
    {
        $this->requireCrear();
        $this->responder(fn() => $this->service->importarEstadoCuenta(
            (int) ($_POST['id'] ?? 0),
            (int) $_SESSION['id_empresa'],
            (int) $_SESSION['id_usuario'],
            (int) ($_POST['id_perfil'] ?? 0),
            $_FILES['archivo'] ?? []
        ));
    }

    public function agregarLineaAjax(): void
    {
        $this->requireCrear();
        $data = $this->payload();
        $this->responder(fn() => ['id' => $this->service->agregarLinea(
            (int) ($data['id_cabecera'] ?? 0),
            (int) $_SESSION['id_empresa'],
            (int) $_SESSION['id_usuario'],
            $data
        )]);
    }

    public function guardarLineaAjax(): void
    {
        $this->requireActualizar();
        $data = $this->payload();
        $this->responder(function () use ($data) {
            $this->service->guardarLinea(
                (int) ($data['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                $data
            );
            return ['guardado' => true];
        });
    }

    public function eliminarLineaAjax(): void
    {
        $this->requireEliminar();
        $data = $this->payload();
        $this->responder(function () use ($data) {
            $this->service->eliminarLinea(
                (int) ($data['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            return ['eliminado' => true];
        });
    }

    public function marcarSinCobroAjax(): void
    {
        $this->requireActualizar();
        $data = $this->payload();
        $this->responder(function () use ($data) {
            $this->service->marcarLineaSinCobro(
                (int) ($data['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                !empty($data['sin_cobro'])
            );
            return ['marcado' => true];
        });
    }

    // ─── Cruce ───────────────────────────────────────────────────────────────

    public function sugerirAjax(): void
    {
        $this->requireLeer();
        $this->responder(fn() => $this->service->sugerirCruces(
            (int) ($_GET['id'] ?? 0),
            (int) $_SESSION['id_empresa'],
            $this->idUsuarioFiltro()
        ));
    }

    public function cruzarAjax(): void
    {
        $this->requireActualizar();
        $data = $this->payload();
        $this->responder(fn() => $this->service->cruzar(
            (int) ($data['id_cabecera'] ?? 0),
            (int) $_SESSION['id_empresa'],
            (int) $_SESSION['id_usuario'],
            is_array($data['pares'] ?? null) ? $data['pares'] : [],
            $this->idUsuarioFiltro()
        ));
    }

    public function descruzarAjax(): void
    {
        $this->requireActualizar();
        $data = $this->payload();
        $this->responder(function () use ($data) {
            $this->service->descruzar(
                (int) ($data['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            return ['descruzado' => true];
        });
    }

    // ─── Cierre / anulación / eliminación ────────────────────────────────────

    public function cerrarAjax(): void
    {
        $this->requireActualizar();
        $data = $this->payload();
        $this->responder(fn() => $this->service->cerrar(
            (int) ($data['id'] ?? 0),
            (int) $_SESSION['id_empresa'],
            (int) $_SESSION['id_usuario']
        ));
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        $data = $this->payload();
        $this->responder(function () use ($data) {
            $this->service->anular(
                (int) ($data['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            return ['anulado' => true];
        });
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        $data = $this->payload();
        $this->responder(function () use ($data) {
            $this->service->eliminar(
                (int) ($data['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            return ['eliminado' => true];
        });
    }

    // ─── Configuración contable ──────────────────────────────────────────────

    public function guardarConfigAjax(): void
    {
        $this->requireActualizar();
        $data = $this->payload();
        $this->responder(fn() => ['id' => $this->service->guardarConfig(
            (int) $_SESSION['id_empresa'],
            (int) $_SESSION['id_usuario'],
            $data
        )]);
    }

    /** Buscador de cuentas del plan para los selectores de la configuración contable. */
    public function buscarCuentasAjax(): void
    {
        $this->requireLeer();
        $this->responder(fn() => (new ConciliacionTarjetasRepository())->buscarCuentasContables(
            (int) $_SESSION['id_empresa'],
            trim((string) ($_GET['q'] ?? ''))
        ));
    }

    /** Configuración contable guardada de una procesadora. */
    public function configAjax(): void
    {
        $this->requireLeer();
        $this->responder(fn() => (new ConciliacionTarjetasRepository())->getConfig(
            (int) $_SESSION['id_empresa'],
            (int) ($_GET['id_forma_cobro'] ?? 0)
        ));
    }

    // ─── Perfiles de lectura del estado de cuenta ────────────────────────────
    // Se administran en /config/conciliacion-tarjetas-perfiles (nivel 3); aquí solo se listan
    // los que sirven para la procesadora de la conciliación.

    public function listarPerfilesAjax(): void
    {
        $this->requireLeer();
        $this->responder(fn() => $this->service->getPerfiles(
            (int) $_SESSION['id_empresa'],
            (int) ($_GET['id_forma_cobro'] ?? 0)
        ));
    }

    // ─── Exportación ─────────────────────────────────────────────────────────

    /** PDF del listado en pantalla (pendientes por depositar o conciliaciones). */
    public function exportarPdf(): void
    {
        $this->requireLeer();
        try {
            [$titulo, $encabezados, $filas] = $this->datosExportacionListado();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($this->htmlTabla($titulo, $encabezados, $filas));
            $html2pdf->output('ConciliacionTarjetas_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    /** Excel del listado en pantalla. */
    public function exportarExcel(): void
    {
        $this->requireLeer();
        try {
            [$titulo, $encabezados, $filas] = $this->datosExportacionListado();

            (new \App\Services\ReportService())->exportToExcel(
                'ConciliacionTarjetas_' . date('Ymd_His'),
                $encabezados,
                $filas,
                'Conciliación de Tarjetas',
                $titulo
            );
            exit;
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    /** Comprobante de una conciliación concreta: totales y detalle del cruce. */
    public function comprobantePdf(): void
    {
        $this->requireLeer();
        try {
            $detalle = $this->service->getDetalle(
                (int) ($_GET['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                $this->idUsuarioFiltro()
            );

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($this->htmlComprobante($detalle));
            $html2pdf->output('Conciliacion_' . $detalle['cabecera']['numero'] . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    /** Detalle de una conciliación en Excel: una fila por línea del estado de cuenta. */
    public function comprobanteExcel(): void
    {
        $this->requireLeer();
        try {
            $detalle = $this->service->getDetalle(
                (int) ($_GET['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                $this->idUsuarioFiltro()
            );

            $encabezados = ['Fecha', 'Autorización', 'Referencia', 'Bruto', 'Comisión', 'IVA comisión',
                            'Ret. renta', 'Ret. IVA', 'Otros', 'Neto', 'Estado', 'Cobros cruzados'];

            $filas = [];
            foreach ($detalle['lineas'] as $l) {
                $cruzados = array_map(
                    static fn($c) => ($c['documentos'] ?? $c['numero_ingreso']) . ' (' . number_format((float) $c['monto_cruzado'], 2) . ')',
                    $l['cruces_detalle'] ?? []
                );

                $filas[] = [
                    $this->fecha($l['fecha_movimiento']),
                    (string) ($l['autorizacion'] ?? ''),
                    (string) ($l['referencia'] ?? ''),
                    number_format((float) $l['monto_bruto'], 2, '.', ''),
                    number_format((float) $l['comision'], 2, '.', ''),
                    number_format((float) $l['iva_comision'], 2, '.', ''),
                    number_format((float) $l['retencion_ir'], 2, '.', ''),
                    number_format((float) $l['retencion_iva'], 2, '.', ''),
                    number_format((float) $l['otros_descuentos'], 2, '.', ''),
                    number_format((float) $l['monto_neto'], 2, '.', ''),
                    $this->etiquetaEstadoLinea((string) $l['estado']),
                    implode(' | ', $cruzados),
                ];
            }

            (new \App\Services\ReportService())->exportToExcel(
                'Conciliacion_' . $detalle['cabecera']['numero'],
                $encabezados,
                $filas,
                'Detalle',
                'Conciliación ' . $detalle['cabecera']['numero'] . ' — ' . ($detalle['cabecera']['procesadora_nombre'] ?? '')
            );
            exit;
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    /**
     * Datos de la exportación del listado: mismo buscador (texto libre + filtros
     * `clave:valor`) y mismo orden que en pantalla (el orden viaja en ?orden=col:DIR,…).
     *
     * @return array{0:string, 1:array, 2:array}
     */
    private function datosExportacionListado(): array
    {
        $resultado = $this->service->getListado(
            (int) $_SESSION['id_empresa'],
            trim((string) ($_GET['buscar'] ?? '')),
            1,
            null,
            $this->ordenConciliaciones(PreferenciasHelper::getPreferenciasVista($this->getRutaModulo())),
            $this->idUsuarioFiltro()
        );

        $filas = [];
        foreach ($resultado['data'] as $c) {
            $filas[] = [
                (string) $c['numero'],
                $this->fecha($c['fecha_conciliacion']),
                (string) ($c['procesadora_nombre'] ?? ''),
                (string) ($c['destino_nombre'] ?? ''),
                (string) $c['cobros_cruzados'],
                number_format((float) $c['total_bruto_cruzado'], 2, '.', ''),
                number_format((float) $c['total_comision'] + (float) $c['total_iva_comision'], 2, '.', ''),
                number_format((float) $c['total_retencion_ir'] + (float) $c['total_retencion_iva'], 2, '.', ''),
                number_format((float) $c['total_neto'], 2, '.', ''),
                ucfirst((string) $c['estado']),
            ];
        }

        return [
            'Conciliaciones de tarjetas',
            ['Número', 'Fecha', 'Procesadora', 'Depositado en', 'Cobros', 'Bruto', 'Comisión', 'Retenciones', 'Neto', 'Estado'],
            $filas,
        ];
    }

    /**
     * PDF del listado (A4 horizontal) con el formato común de reportes (App\Helpers\ReportePdf):
     * logo y nombre de la empresa, búsqueda aplicada, anchos fijos por columna (el ancho va
     * en el <th> y en cada <td>, si no Html2Pdf ensancha la tabla y se sale de la hoja) y
     * fila de totales aparte.
     */
    private function htmlTabla(string $titulo, array $encabezados, array $filas): string
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $empresa   = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $pt        = 7.5;
        $esc       = static fn ($v): string => htmlspecialchars((string) $v);
        $txt       = static fn (int $i, float $w) => static fn (array $r): string
            => \App\Helpers\ReportePdf::texto((string) ($r[$i] ?? ''), \App\Helpers\ReportePdf::anchoPt($w, true), $pt);
        $num       = static fn (int $i, float $w, bool $bold = false) => [
            'w' => $w, 'cls' => 'text-end',
            'val' => static fn (array $r): string => ($bold ? '<b>' : '') . number_format((float) ($r[$i] ?? 0), 2) . ($bold ? '</b>' : ''),
            'tot' => static fn (array $rows): float => array_sum(array_map(static fn ($r) => (float) ($r[$i] ?? 0), $rows)),
        ];

        // Mismo orden de columnas que datosExportacionListado().
        $cols = [
            ['lbl' => $encabezados[0], 'w' => 9,  'cls' => '', 'val' => $txt(0, 9)],
            ['lbl' => $encabezados[1], 'w' => 8,  'cls' => 'text-center', 'val' => static fn (array $r): string => $esc($r[1] ?? '')],
            ['lbl' => $encabezados[2], 'w' => 15, 'cls' => '', 'val' => $txt(2, 15)],
            ['lbl' => $encabezados[3], 'w' => 16, 'cls' => '', 'val' => $txt(3, 16)],
            ['lbl' => $encabezados[4], 'w' => 6,  'cls' => 'text-center', 'val' => static fn (array $r): string => $esc($r[4] ?? ''),
             'tot' => static fn (array $rows): float => array_sum(array_map(static fn ($r) => (float) ($r[4] ?? 0), $rows)),
             'fmt' => static fn (float $v): string => number_format($v, 0)],
            ['lbl' => $encabezados[5]] + $num(5, 10),
            ['lbl' => $encabezados[6]] + $num(6, 9),
            ['lbl' => $encabezados[7]] + $num(7, 9),
            ['lbl' => $encabezados[8]] + $num(8, 10, true),
            ['lbl' => $encabezados[9], 'w' => 8, 'cls' => 'text-center', 'val' => static fn (array $r): string => $esc($r[9] ?? '')],
        ];

        $buscar = trim((string) ($_GET['buscar'] ?? ''));
        $n      = count($filas);

        return \App\Helpers\ReportePdf::pagina($pt,
            \App\Helpers\ReportePdf::encabezado($idEmpresa, (string) ($empresa['nombre'] ?? ''), $titulo)
            . ($buscar !== '' ? \App\Helpers\ReportePdf::filtros(['Búsqueda' => $buscar]) : '')
            . \App\Helpers\ReportePdf::listado($cols, $filas, "TOTALES ({$n} " . ($n === 1 ? 'conciliación' : 'conciliaciones') . ')',
                'Sin conciliaciones para la búsqueda aplicada.')
        );
    }

    /**
     * Comprobante de una conciliación (A4 vertical), con el formato común de reportes:
     * encabezado con logo, datos de la conciliación, banda de totales y detalle del estado
     * de cuenta con sus cobros cruzados. Todo con anchos fijos para que encaje en la hoja.
     */
    private function htmlComprobante(array $detalle): string
    {
        $cab       = $detalle['cabecera'];
        $t         = $detalle['totales'];
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $empresa   = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $pt        = 7.5;
        $money     = static fn ($v): string => ((float) $v < 0 ? '-$' : '$') . number_format(abs((float) $v), 2);
        $e         = static fn ($v): string => htmlspecialchars((string) $v);
        $celda     = static fn (string $v, float $w): string => \App\Helpers\ReportePdf::texto($v, \App\Helpers\ReportePdf::anchoPt($w, false), 7.5);

        // ── Datos de la conciliación: dos pares etiqueta/valor por fila ──
        $asiento = !empty($cab['id_asiento_contable'])
            ? 'Generado (#' . $cab['id_asiento_contable'] . ')'
            : 'No generado' . (!empty($cab['asiento_omitido_motivo']) ? ': ' . $cab['asiento_omitido_motivo'] : '');
        $pares = [
            ['Procesadora', $cab['procesadora_nombre'] ?? ''],
            ['Depositado en', $cab['destino_nombre'] ?? '-'],
            ['Fecha del depósito', $this->fecha($cab['fecha_conciliacion'])],
            ['Estado', ucfirst((string) $cab['estado'])],
        ];
        // El período ya no se captura; solo lo traen las conciliaciones antiguas.
        if (!empty($cab['fecha_desde']) || !empty($cab['fecha_hasta'])) {
            $pares[] = ['Período conciliado', $this->fecha($cab['fecha_desde']) . ' a ' . $this->fecha($cab['fecha_hasta'])];
        }
        if (!empty($cab['nombre_perfil'])) {
            $pares[] = ['Perfil de lectura', $cab['nombre_perfil']];
        }
        if (!empty($cab['nombre_archivo'])) {
            $pares[] = ['Archivo', $cab['nombre_archivo']];
        }
        $filasDatos = '';
        for ($i = 0, $n = count($pares); $i < $n; $i += 2) {
            [$lA, $vA] = $pares[$i];
            [$lB, $vB] = $pares[$i + 1] ?? ['', ''];
            $filasDatos .= '<tr>'
                . "<td class='f-lbl' style='width:17%;'>" . $e($lA) . ':</td>'
                . "<td style='width:33%;'>" . $celda((string) $vA, 33) . '</td>'
                . "<td class='f-lbl' style='width:17%;'>" . ($lB !== '' ? $e($lB) . ':' : '') . '</td>'
                . "<td style='width:33%;'>" . $celda((string) $vB, 33) . '</td>'
                . '</tr>';
        }
        // El motivo del asiento puede ser largo: va en su propia tabla a todo el ancho
        // (un colspan en la tabla de pares haría que Html2Pdf ignore sus anchos).
        $datos = "<table class='fil-tit'><tr><td style='width:100%;'>Datos de la conciliación</td></tr></table>"
               . "<table class='filtros' style='margin-bottom:0;'>{$filasDatos}</table>"
               . "<table class='filtros'><tr><td class='f-lbl' style='width:17%;'>Asiento contable:</td>"
               . "<td style='width:83%;'>" . $celda($asiento, 83) . '</td></tr></table>';

        // ── Banda de totales ──
        $dif  = (float) $t['diferencia'];
        $kpis = \App\Helpers\ReportePdf::indicadores([
            ['BRUTO CONCILIADO', $money($t['total_bruto_cruzado'])],
            ['COMISIÓN + IVA', $money((float) $t['total_comision'] + (float) $t['total_iva_comision'] + (float) $t['total_otros'])],
            ['RETENCIONES', $money((float) $t['total_retencion_ir'] + (float) $t['total_retencion_iva'])],
            ['NETO CALCULADO', $money($t['total_neto']), true],
            ['NETO DEPOSITADO', $money($t['neto_depositado'])],
            ['DIFERENCIA', $money($dif), false, abs($dif) < 0.005 ? '#146c43' : '#b02a37'],
        ]);

        // ── Detalle: una fila por línea del estado de cuenta ──
        $txt = static fn (string $v, float $w): string => \App\Helpers\ReportePdf::texto($v, \App\Helpers\ReportePdf::anchoPt($w, false), $pt);
        $num = static fn (string $campo, float $w, bool $bold = false) => [
            'w' => $w, 'cls' => 'text-end',
            'val' => static fn (array $l): string => ($bold ? '<b>' : '') . number_format((float) ($l[$campo] ?? 0), 2) . ($bold ? '</b>' : ''),
            'tot' => static fn (array $rows): float => array_sum(array_map(static fn ($l) => (float) ($l[$campo] ?? 0), $rows)),
        ];
        $cols = [
            ['lbl' => 'Fecha', 'w' => 9, 'cls' => 'text-center',
             'val' => fn (array $l): string => $e($this->fecha($l['fecha_movimiento'] ?? null))],
            ['lbl' => 'Autorización', 'w' => 12, 'cls' => '',
             'val' => static fn (array $l): string => $txt((string) (($l['autorizacion'] ?? '') !== '' ? $l['autorizacion'] : ($l['referencia'] ?? '')), 12)],
            ['lbl' => 'Bruto'] + $num('monto_bruto', 9),
            ['lbl' => 'Comisión'] + $num('comision', 8),
            ['lbl' => 'IVA com.'] + $num('iva_comision', 7),
            ['lbl' => 'Retenc.', 'w' => 8, 'cls' => 'text-end',
             'val' => static fn (array $l): string => number_format((float) $l['retencion_ir'] + (float) $l['retencion_iva'], 2),
             'tot' => static fn (array $rows): float => array_sum(array_map(static fn ($l) => (float) $l['retencion_ir'] + (float) $l['retencion_iva'], $rows))],
            ['lbl' => 'Neto'] + $num('monto_neto', 9, true),
            ['lbl' => 'Estado', 'w' => 9, 'cls' => 'text-center',
             'val' => fn (array $l): string => $e($this->etiquetaEstadoLinea((string) $l['estado']))],
            ['lbl' => 'Cobros cruzados', 'w' => 29, 'cls' => '',
             'val' => static function (array $l) use ($txt): string {
                 $partes = array_map(
                     static fn ($c) => trim((($c['documentos'] ?? '') !== '' ? $c['documentos'] : ($c['numero_ingreso'] ?? '')) . ' ' . ($c['cliente_nombre'] ?? ''))
                         . ' ($' . number_format((float) $c['monto_cruzado'], 2) . ')',
                     $l['cruces_detalle'] ?? []
                 );
                 return $partes ? implode('<br>', array_map(static fn ($p) => $txt($p, 29), $partes)) : '-';
             }],
        ];

        $n = count($detalle['lineas']);
        return \App\Helpers\ReportePdf::pagina($pt,
            \App\Helpers\ReportePdf::encabezado(
                $idEmpresa,
                (string) ($empresa['nombre'] ?? ''),
                'Conciliación de tarjetas ' . $cab['numero'],
                trim(($cab['procesadora_nombre'] ?? '') . (!empty($cab['destino_nombre']) ? ' · depositado en ' . $cab['destino_nombre'] : ''))
            )
            . $datos
            . $kpis
            . "<table class='fil-tit'><tr><td style='width:100%;'>Estado de cuenta de la procesadora</td></tr></table>"
            . \App\Helpers\ReportePdf::listado($cols, $detalle['lineas'], "TOTALES ({$n} " . ($n === 1 ? 'línea' : 'líneas') . ')',
                'Sin líneas cargadas.')
        );
    }

    private function etiquetaEstadoLinea(string $estado): string
    {
        return match ($estado) {
            'cruzada'   => 'Cruzada',
            'sin_cobro' => 'Sin documento',
            default     => 'Pendiente',
        };
    }

    /** Fechas siempre en d-m-Y (§9). */
    private function fecha(?string $valor): string
    {
        if (empty($valor)) {
            return '';
        }
        $ts = strtotime($valor);
        return $ts ? date('d-m-Y', $ts) : (string) $valor;
    }

    // ─── Utilidades ──────────────────────────────────────────────────────────

    /** Orden vigente del listado de conciliaciones (petición → preferencia → defecto). */
    private function ordenConciliaciones(array $prefsVista): array
    {
        return OrdenListado::leer($prefsVista, 'numero', 'DESC');
    }

    /**
     * Registros propios (§6): sin permiso de acceso total, el usuario solo ve los
     * cobros que él registró.
     */
    private function idUsuarioFiltro(): ?int
    {
        $perm = $this->getPermisos();
        return empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    /** Cuerpo JSON de la petición, con respaldo a $_POST. */
    private function payload(): array
    {
        $crudo = file_get_contents('php://input') ?: '';
        $data  = json_decode($crudo, true);
        return is_array($data) ? $data : $_POST;
    }

    /** Envoltura común de las respuestas AJAX: mismo formato y log de errores. */
    private function responder(callable $accion): void
    {
        header('Content-Type: application/json');
        try {
            echo json_encode(['ok' => true, 'data' => $accion()]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? '']);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
