<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MayoresRepository;
use App\Services\ReportService;
use App\Helpers\ReportePdf;

class MayoresService
{
    private MayoresRepository $repository;
    private ReportService $reportService;

    public function __construct(MayoresRepository $repository, ReportService $reportService)
    {
        $this->repository = $repository;
        $this->reportService = $reportService;
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        return $this->repository->getAniosDisponibles($idEmpresa);
    }

    public function getCentrosCostoActivos(int $idEmpresa): array
    {
        return $this->repository->getCentrosCostoActivos($idEmpresa);
    }

    public function getProyectosActivos(int $idEmpresa): array
    {
        return $this->repository->getProyectosActivos($idEmpresa);
    }

    /**
     * Arma el Mayor agrupado por cuenta. El saldo acumulado arranca en 0 por cada cuenta
     * dentro del rango filtrado (sin arrastre de periodos anteriores: el saldo inicial se
     * registra manualmente como asiento de apertura, igual criterio que Estados Financieros).
     */
    public function generarMayor(int $idEmpresa, array $filtros): array
    {
        $movimientos = $this->repository->getMovimientos($idEmpresa, $filtros);

        $cuentas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($movimientos as $mov) {
            $idCuenta = (int) $mov['id_cuenta'];
            $codigo = (string) $mov['codigo_cuenta'];

            if (!isset($cuentas[$idCuenta])) {
                $cuentas[$idCuenta] = [
                    'id_cuenta' => $idCuenta,
                    'codigo' => $codigo,
                    'nombre' => $mov['nombre_cuenta'],
                    'movimientos' => [],
                    'saldo_arrastrado' => 0.0,
                    'subtotal_debe' => 0.0,
                    'subtotal_haber' => 0.0,
                ];
            }

            $debe = (float) $mov['debe'];
            $haber = (float) $mov['haber'];
            $prefijo = $codigo !== '' ? $codigo[0] : '1';
            // 1=Activo (Deudora), 2=Pasivo (Acreedora), 3=Patrimonio (Acreedora), 4=Ingresos (Acreedora), 5=Costos (Deudora), 6=Gastos (Deudora)
            $naturaleza = in_array($prefijo, ['1', '5', '6']) ? 'deudora' : 'acreedora';

            $cuentas[$idCuenta]['saldo_arrastrado'] += $naturaleza === 'deudora' ? ($debe - $haber) : ($haber - $debe);
            $cuentas[$idCuenta]['subtotal_debe'] += $debe;
            $cuentas[$idCuenta]['subtotal_haber'] += $haber;

            $cuentas[$idCuenta]['movimientos'][] = [
                'id_asiento' => $mov['id_asiento'],
                'fecha_asiento' => $mov['fecha_asiento'],
                'numero_comprobante' => $mov['numero_comprobante'],
                'documento_referencia' => $mov['documento_referencia'],
                'referencia_detalle' => $mov['referencia_detalle'],
                'concepto' => $mov['concepto'],
                'tercero' => $mov['nombre_entidad'],
                // Documento origen: con esto la vista enlaza "Documento Ref." a su modal.
                'modulo_documento' => $mov['modulo_documento'],
                'id_documento' => $mov['id_documento'],
                'debe' => $debe,
                'haber' => $haber,
                'saldo_acumulado' => $cuentas[$idCuenta]['saldo_arrastrado'],
            ];

            $totalDebe += $debe;
            $totalHaber += $haber;
        }

        $resultado = [];
        foreach ($cuentas as $c) {
            $resultado[] = [
                'id_cuenta' => $c['id_cuenta'],
                'codigo' => $c['codigo'],
                'nombre' => $c['nombre'],
                'movimientos' => $c['movimientos'],
                'subtotal_debe' => $c['subtotal_debe'],
                'subtotal_haber' => $c['subtotal_haber'],
                'saldo_final' => $c['saldo_arrastrado'],
            ];
        }

        return [
            'cuentas' => $resultado,
            'totales' => [
                'debe' => $totalDebe,
                'haber' => $totalHaber,
            ],
        ];
    }

    /**
     * Excel del Mayor con el formato común de los reportes (ReportService::construirSpreadsheet):
     * nombre de la empresa, filtros aplicados + totales + fecha de generación, un único
     * encabezado (fijo al desplazarse) y montos con formato numérico. Cada cuenta abre con
     * una fila de título resaltada y cierra con su subtotal; al final, el TOTAL GENERAL.
     */
    public function exportarExcel(int $idEmpresa, array $datos, array $filtros, string $empresaNombre): void
    {
        $headers = ['Fecha', 'Comprobante', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'];
        $fecha = static function ($v): string {
            $ts = strtotime((string) $v);
            return $ts ? date('d-m-Y', $ts) : (string) $v;
        };

        $data  = [];
        $tipos = []; // índice de fila de $data => 'grp' | 'sub' | 'tot'
        $nMovs = 0;
        foreach ($datos['cuentas'] as $k => $cuenta) {
            if ($k > 0) {
                $data[] = array_fill(0, 8, null);
            }
            $tipos[count($data)] = 'grp';
            $data[] = [$cuenta['codigo'] . ' - ' . $cuenta['nombre'], null, null, null, null, null, null, null];

            foreach ($cuenta['movimientos'] as $mov) {
                $nMovs++;
                $data[] = [
                    $fecha($mov['fecha_asiento']),
                    $mov['numero_comprobante'] ?: 'S/N',
                    (string) ($mov['documento_referencia'] ?: ''),
                    (string) ($mov['tercero'] ?: ''),
                    (string) ($mov['referencia_detalle'] ?: $mov['concepto'] ?: ''),
                    (float) $mov['debe'],
                    (float) $mov['haber'],
                    (float) $mov['saldo_acumulado'],
                ];
            }

            $tipos[count($data)] = 'sub';
            $data[] = [null, null, null, null, 'SUBTOTAL ' . $cuenta['codigo'],
                (float) $cuenta['subtotal_debe'], (float) $cuenta['subtotal_haber'], (float) $cuenta['saldo_final']];
        }
        if ($data) {
            $data[] = array_fill(0, 8, null);
            $tipos[count($data)] = 'tot';
            $data[] = [null, null, null, null, 'TOTAL GENERAL', (float) $datos['totales']['debe'], (float) $datos['totales']['haber'], null];
        }

        $debe   = (float) $datos['totales']['debe'];
        $haber  = (float) $datos['totales']['haber'];
        $dinero = static fn (float $v): string => ($v < 0 ? '-$' : '$') . number_format(abs($v), 2);
        $periodo = 'Del ' . $fecha($filtros['fecha_inicio']) . ' al ' . $fecha($filtros['fecha_fin']);
        $info = ['Reporte' => 'Mayores']
            + $this->describirFiltros($idEmpresa, $datos, $filtros, $periodo)
            + [
                'Totales'  => sprintf('%d cuentas  |  %d movimientos  |  Debe %s  |  Haber %s  |  Diferencia %s',
                    count($datos['cuentas']), $nMovs, $dinero($debe), $dinero($haber), $dinero($debe - $haber)),
                'Generado' => date('d-m-Y H:i:s'),
            ];

        $formatos = [6 => '#,##0.00', 7 => '#,##0.00', 8 => '#,##0.00'];
        $libro = $this->reportService->construirSpreadsheet($headers, $data, 'Mayores', $empresaNombre, $info, $formatos);

        // Ajustes sobre las filas de datos (las últimas count($data) filas de la hoja).
        $hoja     = $libro->getActiveSheet();
        $ultima   = $hoja->getHighestRow();
        $primera  = $ultima - count($data) + 1;
        $hoja->freezePane('A' . $primera); // el encabezado queda visible al bajar

        // Glosa y Tercero: ancho fijo con ajuste de texto en lugar del ancho automático.
        foreach (['D' => 35, 'E' => 55] as $letra => $ancho) {
            $hoja->getColumnDimension($letra)->setAutoSize(false)->setWidth($ancho);
        }
        if ($data) {
            $hoja->getStyle("D{$primera}:E{$ultima}")->getAlignment()
                 ->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            $hoja->getStyle("A{$primera}:C{$ultima}")->getAlignment()
                 ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        $relleno = static fn (string $rgb): array => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb],
        ];
        foreach ($tipos as $i => $tipo) {
            $fila = $primera + $i;
            if ($tipo === 'grp') {
                // La fila de la cuenta ocupa todo el ancho (como en el PDF).
                $hoja->mergeCells("A{$fila}:H{$fila}");
                $hoja->getStyle("A{$fila}:H{$fila}")->applyFromArray([
                    'font' => ['bold' => true], 'fill' => $relleno('D9E2F3'),
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
                ]);
            } else {
                $hoja->getStyle("A{$fila}:H{$fila}")->applyFromArray([
                    'font' => ['bold' => true], 'fill' => $relleno($tipo === 'tot' ? 'BDD7EE' : 'F2F2F2'),
                    'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                ]);
                $hoja->getStyle("E{$fila}")->getAlignment()
                     ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            }
        }

        $this->reportService->descargarSpreadsheet($libro, 'Mayores');
    }

    /**
     * PDF del Mayor con el formato común de los reportes (App\Helpers\ReportePdf): logo del
     * establecimiento, caja "Filtros aplicados", banda de indicadores y pie "Página x/y".
     *
     * Todo el listado va en UNA sola tabla (cabecera de cada cuenta y su subtotal como filas
     * con colspan): una tabla por cuenta, cada una con su <thead>, hace que Html2Pdf parta
     * filas entre páginas cuando la tabla se abre al pie de la hoja.
     */
    public function exportarPdf(int $idEmpresa, array $datos, array $filtros, string $empresaNombre): void
    {
        $pt = 7.0;
        $e  = static fn ($v): string => htmlspecialchars((string) $v);
        $m  = static fn ($v): string => number_format((float) $v, 2, '.', ',');
        $fecha = static function ($v): string {
            $ts = strtotime((string) $v);
            return $ts ? date('d-m-Y', $ts) : (string) $v;
        };

        // Fecha | Comprobante | Documento Ref. | Tercero | Glosa | Debe | Haber | Saldo
        $an = [7, 10, 13, 17, 26, 9, 9, 9];
        $texto = static fn (string $t, int $i): string => ReportePdf::texto($t, ReportePdf::anchoPt($an[$i], true), $pt);
        $anTexto = array_sum(array_slice($an, 0, 5));

        $th = '';
        foreach (['Fecha', 'Comprobante', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'] as $i => $lbl) {
            $th .= "<th style='width:{$an[$i]}%;'>{$lbl}</th>";
        }

        $cuerpo = '';
        $nMovs  = 0;
        foreach ($datos['cuentas'] as $k => $cuenta) {
            if ($k > 0) {
                $cuerpo .= "<tr class='sep'><td colspan='8' style='width:100%;'>&nbsp;</td></tr>";
            }
            $cuerpo .= "<tr class='grp'><td colspan='8' style='width:100%;'>"
                . $e($cuenta['codigo'] . ' - ' . $cuenta['nombre']) . '</td></tr>';

            $i = 0;
            foreach ($cuenta['movimientos'] as $mov) {
                $nMovs++;
                $z = (++$i % 2 === 0) ? 'background:#f6f8fa;' : '';
                $glosa = (string) ($mov['referencia_detalle'] ?: $mov['concepto']);
                $cuerpo .= '<tr>'
                    . "<td class='text-center' style='width:{$an[0]}%;{$z}'>" . $fecha($mov['fecha_asiento']) . '</td>'
                    . "<td class='text-center' style='width:{$an[1]}%;{$z}'>" . $texto((string) ($mov['numero_comprobante'] ?: 'S/N'), 1) . '</td>'
                    . "<td style='width:{$an[2]}%;{$z}'>" . $texto((string) $mov['documento_referencia'], 2) . '</td>'
                    . "<td style='width:{$an[3]}%;{$z}'>" . $texto((string) $mov['tercero'], 3) . '</td>'
                    . "<td style='width:{$an[4]}%;{$z}'>" . $texto($glosa, 4) . '</td>'
                    . "<td class='text-end' style='width:{$an[5]}%;{$z}'>" . $m($mov['debe']) . '</td>'
                    . "<td class='text-end' style='width:{$an[6]}%;{$z}'>" . $m($mov['haber']) . '</td>'
                    . "<td class='text-end' style='width:{$an[7]}%;{$z}font-weight:bold;'>" . $m($mov['saldo_acumulado']) . '</td>'
                    . '</tr>';
            }

            $cuerpo .= "<tr class='sub'>"
                . "<td colspan='5' class='text-end' style='width:{$anTexto}%;'>SUBTOTAL " . $e($cuenta['codigo']) . ':</td>'
                . "<td class='text-end' style='width:{$an[5]}%;'>" . $m($cuenta['subtotal_debe']) . '</td>'
                . "<td class='text-end' style='width:{$an[6]}%;'>" . $m($cuenta['subtotal_haber']) . '</td>'
                . "<td class='text-end' style='width:{$an[7]}%;'>" . $m($cuenta['saldo_final']) . '</td>'
                . '</tr>';
        }

        if ($cuerpo === '') {
            $listado = "<table><thead><tr>{$th}</tr></thead><tbody><tr><td colspan='8' class='text-center' style='width:100%;padding:8px;'>"
                . 'Sin movimientos para los filtros aplicados.</td></tr></tbody></table>';
        } else {
            // El total general va en tabla aparte: un <tfoot> se repetiría al pie de cada hoja.
            $listado = "<table><thead><tr>{$th}</tr></thead><tbody>{$cuerpo}</tbody></table>"
                . "<table class='tot'><tr>"
                . "<td class='text-end' style='width:{$anTexto}%;'>TOTAL GENERAL:</td>"
                . "<td class='text-end' style='width:{$an[5]}%;'>" . $m($datos['totales']['debe']) . '</td>'
                . "<td class='text-end' style='width:{$an[6]}%;'>" . $m($datos['totales']['haber']) . '</td>'
                . "<td style='width:{$an[7]}%;'></td>"
                . '</tr></table>';
        }

        $debe  = (float) $datos['totales']['debe'];
        $haber = (float) $datos['totales']['haber'];
        $kpis = [
            ['Cuentas', (string) count($datos['cuentas'])],
            ['Movimientos', (string) $nMovs],
            ['Total Debe', '$' . $m($debe), false, '#146c43'],
            ['Total Haber', '$' . $m($haber), false, '#b02a37'],
            ['Diferencia', '$' . $m($debe - $haber), true],
        ];

        $subtitulo = 'Del ' . $fecha($filtros['fecha_inicio']) . ' al ' . $fecha($filtros['fecha_fin']);
        $css = "tr.grp td { background:#eaf1fb; border:1px solid #9aa7b4; font-weight:bold; font-size:8pt; padding:3px 5px; color:#1b2a3a; }
                tr.sub td { background:#f1f4f8; font-weight:bold; color:#2c4a6b; }
                tr.sep td { border:none; background:#fff; padding:0; font-size:4.5pt; }";

        $html = ReportePdf::pagina($pt,
            '<style>' . $css . '</style>'
            . ReportePdf::encabezado($idEmpresa, $empresaNombre, 'MAYORES', $subtitulo)
            . ReportePdf::filtros($this->describirFiltros($idEmpresa, $datos, $filtros, $subtitulo))
            . ReportePdf::indicadores($kpis)
            . $listado
        );

        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;
        $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
        $pdf->writeHTML($html);
        if (ob_get_length()) ob_end_clean();
        $pdf->output('Mayores_' . date('Ymd_His') . '.pdf', 'D');
        exit;
    }

    /**
     * Filtros en texto para el PDF. Los nombres de cuenta y tercero salen de los propios
     * movimientos (con el filtro puesto, todos son de esa cuenta / ese tercero); centro de
     * costo y proyecto, de sus catálogos de la empresa.
     */
    private function describirFiltros(int $idEmpresa, array $datos, array $filtros, string $periodo): array
    {
        $cuenta = 'Todas';
        if (!empty($filtros['id_cuenta'])) {
            $c = $datos['cuentas'][0] ?? null;
            $cuenta = $c ? $c['codigo'] . ' - ' . $c['nombre'] : 'Seleccionada (sin movimientos)';
        }

        $tipos   = ['cliente' => 'Cliente', 'proveedor' => 'Proveedor', 'empleado' => 'Empleado'];
        $tercero = 'Todos';
        if (!empty($filtros['tipo_entidad'])) {
            $tercero = $tipos[$filtros['tipo_entidad']] ?? $filtros['tipo_entidad'];
            if (!empty($filtros['id_entidad'])) {
                $nombre = $datos['cuentas'][0]['movimientos'][0]['tercero'] ?? '';
                $tercero .= ': ' . ($nombre !== '' ? $nombre : 'seleccionado (sin movimientos)');
            }
        }

        $buscar = static function (array $lista, $id): string {
            foreach ($lista as $r) {
                if ((int) $r['id'] === (int) $id) {
                    return $r['codigo'] . ' - ' . $r['nombre'];
                }
            }
            return 'Seleccionado';
        };
        $res = [
            'Período' => $periodo,
            'Cuenta'  => $cuenta,
            'Tercero' => $tercero,
        ];
        // Centro de costo y proyecto solo se listan si se filtró por ellos (muchas empresas no los usan).
        if (!empty($filtros['id_centro_costo'])) {
            $res['Centro de costo'] = $buscar($this->repository->getCentrosCostoActivos($idEmpresa), $filtros['id_centro_costo']);
        }
        if (!empty($filtros['id_proyecto'])) {
            $res['Proyecto'] = $buscar($this->repository->getProyectosActivos($idEmpresa), $filtros['id_proyecto']);
        }
        return $res;
    }
}
