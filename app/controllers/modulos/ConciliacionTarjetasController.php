<?php

declare(strict_types=1);

namespace App\controllers\modulos;

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

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $this->viewWithLayout('layouts.main', 'modulos.conciliacion_tarjetas.index', [
            'titulo'       => 'Conciliación de Tarjetas',
            'perm'         => $this->getPermisos(),
            'rutaModulo'   => $this->getRutaModulo(),
            'procesadoras' => $this->service->getProcesadoras($idEmpresa),
            'destinos'     => $this->service->getFormasDestino($idEmpresa),
            'perfiles'     => $this->service->getPerfiles($idEmpresa),
            'resumen'      => $this->service->getResumenPendientes($idEmpresa, $this->idUsuarioFiltro()),
        ]);
    }

    // ─── Listado ─────────────────────────────────────────────────────────────

    public function listarAjax(): void
    {
        $this->requireLeer();
        $this->responder(function () {
            $idEmpresa = (int) $_SESSION['id_empresa'];

            $resultado = $this->service->getListado(
                $idEmpresa,
                trim((string) ($_GET['buscar'] ?? '')),
                max(1, (int) ($_GET['page'] ?? 1)),
                min(200, max(5, (int) ($_GET['per_page'] ?? 30))),
                (string) ($_GET['orden'] ?? 'numero'),
                (string) ($_GET['dir'] ?? 'DESC'),
                $this->idUsuarioFiltro(),
                [
                    'id_forma_cobro' => (int) ($_GET['id_forma_cobro'] ?? 0),
                    'estado'         => trim((string) ($_GET['estado'] ?? '')),
                    'fecha_desde'    => trim((string) ($_GET['fecha_desde'] ?? '')),
                    'fecha_hasta'    => trim((string) ($_GET['fecha_hasta'] ?? '')),
                ]
            );

            return [
                'data'    => $resultado['data'],
                'total'   => $resultado['total'],
                'resumen' => $this->service->getResumenPendientes($idEmpresa, $this->idUsuarioFiltro()),
            ];
        });
    }

    /** Cobros con tarjeta que aún no aparecen en ningún estado de cuenta. */
    public function pendientesAjax(): void
    {
        $this->requireLeer();
        $this->responder(function () {
            $repo = new ConciliacionTarjetasRepository();
            return $repo->getCobrosPendientes(
                (int) $_SESSION['id_empresa'],
                (int) ($_GET['id_forma_cobro'] ?? 0),
                trim((string) ($_GET['fecha_desde'] ?? '')) ?: null,
                trim((string) ($_GET['fecha_hasta'] ?? '')) ?: null,
                trim((string) ($_GET['buscar'] ?? '')),
                $this->idUsuarioFiltro()
            );
        });
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

    public function listarPerfilesAjax(): void
    {
        $this->requireLeer();
        $this->responder(fn() => $this->service->getPerfiles(
            (int) $_SESSION['id_empresa'],
            (int) ($_GET['id_forma_cobro'] ?? 0) ?: null
        ));
    }

    public function guardarPerfilAjax(): void
    {
        $this->requireCrear();
        $data = $this->payload();
        $this->responder(fn() => ['id' => $this->service->guardarPerfil(
            (int) $_SESSION['id_empresa'],
            (int) $_SESSION['id_usuario'],
            $data
        )]);
    }

    public function eliminarPerfilAjax(): void
    {
        $this->requireEliminar();
        $data = $this->payload();
        $this->responder(function () use ($data) {
            $this->service->eliminarPerfil(
                (int) ($data['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            return ['eliminado' => true];
        });
    }

    /** Asistente de perfil: muestra el archivo como se lee, antes de mapear. */
    public function previsualizarArchivoAjax(): void
    {
        $this->requireCrear();
        $this->responder(function () {
            $mapeo = $_POST['mapeo_prueba'] ?? null;
            if (is_string($mapeo)) {
                $mapeo = json_decode($mapeo, true);
            }

            return $this->service->previsualizarArchivo(
                $_FILES['archivo'] ?? [],
                strtoupper(trim((string) ($_POST['tipo_archivo'] ?? 'EXCEL'))),
                (int) ($_POST['fila_inicio'] ?? 0),
                is_array($mapeo) ? $mapeo : null,
                (string) ($_POST['formato_fecha'] ?? 'd/m/Y'),
                (string) ($_POST['separador_decimal'] ?? '.')
            );
        });
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
     * Arma los datos de la exportación según la pestaña que el usuario tenga
     * abierta: cobros pendientes de depósito o conciliaciones registradas.
     *
     * @return array{0:string, 1:array, 2:array}
     */
    private function datosExportacionListado(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $vista     = (string) ($_GET['vista'] ?? 'pendientes');

        if ($vista === 'pendientes') {
            $repo = new ConciliacionTarjetasRepository();
            $filas = [];
            foreach ($repo->getCobrosPendientes(
                $idEmpresa,
                (int) ($_GET['id_forma_cobro'] ?? 0),
                trim((string) ($_GET['fecha_desde'] ?? '')) ?: null,
                trim((string) ($_GET['fecha_hasta'] ?? '')) ?: null,
                trim((string) ($_GET['buscar'] ?? '')),
                $this->idUsuarioFiltro()
            ) as $c) {
                $filas[] = [
                    $this->fecha($c['fecha_emision']),
                    (string) ($c['documentos'] ?? ''),
                    (string) ($c['cliente_nombre'] ?? ''),
                    (string) ($c['numero_ingreso'] ?? ''),
                    (string) ($c['autorizacion'] ?? $c['referencia'] ?? ''),
                    number_format((float) $c['monto'], 2, '.', ''),
                    (string) $c['dias_transcurridos'],
                ];
            }

            return [
                'Cobros con tarjeta pendientes de depósito',
                ['Fecha cobro', 'Documento', 'Cliente', 'Ingreso', 'Autorización', 'Monto', 'Días'],
                $filas,
            ];
        }

        $resultado = $this->service->getListado(
            $idEmpresa,
            trim((string) ($_GET['buscar'] ?? '')),
            1,
            5000,
            'numero',
            'DESC',
            $this->idUsuarioFiltro(),
            [
                'id_forma_cobro' => (int) ($_GET['id_forma_cobro'] ?? 0),
                'estado'         => trim((string) ($_GET['estado'] ?? '')),
                'fecha_desde'    => trim((string) ($_GET['fecha_desde'] ?? '')),
                'fecha_hasta'    => trim((string) ($_GET['fecha_hasta'] ?? '')),
            ]
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
                (string) $c['estado'],
                !empty($c['id_asiento_contable']) ? 'Sí' : 'No',
            ];
        }

        return [
            'Conciliaciones de tarjetas',
            ['Número', 'Fecha', 'Procesadora', 'Depositado en', 'Cobros', 'Bruto', 'Comisión', 'Retenciones', 'Neto', 'Estado', 'Asiento'],
            $filas,
        ];
    }

    private function htmlTabla(string $titulo, array $encabezados, array $filas): string
    {
        $empresa = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);

        $html = '<style>
            table { width:100%; border-collapse:collapse; font-size:8pt; }
            th { background:#e9ecef; border:1px solid #adb5bd; padding:3px; text-align:left; }
            td { border:1px solid #dee2e6; padding:3px; }
            h2 { font-size:12pt; margin:0 0 2px 0; }
            .sub { font-size:9pt; color:#555; margin:0 0 8px 0; }
        </style>';
        $html .= '<h2>' . htmlspecialchars($titulo) . '</h2>';
        $html .= '<p class="sub">' . htmlspecialchars((string) ($empresa['nombre'] ?? ''))
               . ' — generado el ' . date('d-m-Y H:i:s') . '</p>';

        $html .= '<table><thead><tr>';
        foreach ($encabezados as $h) {
            $html .= '<th>' . htmlspecialchars((string) $h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($filas as $fila) {
            $html .= '<tr>';
            foreach ($fila as $celda) {
                $html .= '<td>' . htmlspecialchars((string) $celda) . '</td>';
            }
            $html .= '</tr>';
        }

        if (empty($filas)) {
            $html .= '<tr><td colspan="' . count($encabezados) . '">Sin registros.</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function htmlComprobante(array $detalle): string
    {
        $cab = $detalle['cabecera'];
        $t   = $detalle['totales'];
        $empresa = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);

        $dato = static fn($etiqueta, $valor) =>
            '<tr><td class="lbl">' . $etiqueta . '</td><td>' . htmlspecialchars((string) $valor) . '</td></tr>';
        $money = static fn($v) => '$' . number_format((float) $v, 2);

        $html = '<style>
            table { width:100%; border-collapse:collapse; font-size:9pt; }
            th { background:#e9ecef; border:1px solid #adb5bd; padding:3px; text-align:left; }
            td { border:1px solid #dee2e6; padding:3px; }
            .lbl { background:#f8f9fa; width:35%; font-weight:bold; }
            .num { text-align:right; }
            h2 { font-size:13pt; margin:0; }
            .sub { font-size:9pt; color:#555; margin:0 0 8px 0; }
            .box { margin-bottom:10px; }
        </style>';

        $html .= '<h2>Conciliación de tarjetas ' . htmlspecialchars((string) $cab['numero']) . '</h2>';
        $html .= '<p class="sub">' . htmlspecialchars((string) ($empresa['nombre'] ?? '')) . '</p>';

        $html .= '<table class="box">';
        $html .= $dato('Procesadora', $cab['procesadora_nombre'] ?? '');
        $html .= $dato('Depositado en', $cab['destino_nombre'] ?? '—');
        $html .= $dato('Fecha del depósito', $this->fecha($cab['fecha_conciliacion']));
        $html .= $dato('Período conciliado', $this->fecha($cab['fecha_desde']) . ' a ' . $this->fecha($cab['fecha_hasta']));
        $html .= $dato('Estado', $cab['estado']);
        $html .= $dato('Asiento contable', !empty($cab['id_asiento_contable'])
            ? 'Generado (#' . $cab['id_asiento_contable'] . ')'
            : 'No generado — ' . ($cab['asiento_omitido_motivo'] ?? 'sin cuentas configuradas'));
        $html .= '</table>';

        $html .= '<table class="box">';
        $html .= '<tr><th>Concepto</th><th class="num">Valor</th></tr>';
        $html .= '<tr><td>Bruto conciliado</td><td class="num">' . $money($t['total_bruto_cruzado']) . '</td></tr>';
        $html .= '<tr><td>Comisión</td><td class="num">' . $money($t['total_comision']) . '</td></tr>';
        $html .= '<tr><td>IVA de la comisión</td><td class="num">' . $money($t['total_iva_comision']) . '</td></tr>';
        $html .= '<tr><td>Retención de renta</td><td class="num">' . $money($t['total_retencion_ir']) . '</td></tr>';
        $html .= '<tr><td>Retención de IVA</td><td class="num">' . $money($t['total_retencion_iva']) . '</td></tr>';
        $html .= '<tr><td>Otros descuentos</td><td class="num">' . $money($t['total_otros']) . '</td></tr>';
        $html .= '<tr><td><b>Neto</b></td><td class="num"><b>' . $money($t['total_neto']) . '</b></td></tr>';
        $html .= '<tr><td>Neto depositado</td><td class="num">' . $money($t['neto_depositado']) . '</td></tr>';
        $html .= '<tr><td>Diferencia</td><td class="num">' . $money($t['diferencia']) . '</td></tr>';
        $html .= '</table>';

        $html .= '<table><thead><tr>
                    <th>Fecha</th><th>Autorización</th><th class="num">Bruto</th>
                    <th class="num">Neto</th><th>Estado</th><th>Cobros cruzados</th>
                  </tr></thead><tbody>';

        foreach ($detalle['lineas'] as $l) {
            $cruzados = array_map(
                static fn($c) => ($c['documentos'] ?? $c['numero_ingreso']) . ' — ' . ($c['cliente_nombre'] ?? ''),
                $l['cruces_detalle'] ?? []
            );

            $html .= '<tr>'
                . '<td>' . $this->fecha($l['fecha_movimiento']) . '</td>'
                . '<td>' . htmlspecialchars((string) ($l['autorizacion'] ?? $l['referencia'] ?? '')) . '</td>'
                . '<td class="num">' . $money($l['monto_bruto']) . '</td>'
                . '<td class="num">' . $money($l['monto_neto']) . '</td>'
                . '<td>' . $this->etiquetaEstadoLinea((string) $l['estado']) . '</td>'
                . '<td>' . htmlspecialchars(implode(' | ', $cruzados)) . '</td>'
                . '</tr>';
        }

        if (empty($detalle['lineas'])) {
            $html .= '<tr><td colspan="6">Sin líneas cargadas.</td></tr>';
        }

        return $html . '</tbody></table>';
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
