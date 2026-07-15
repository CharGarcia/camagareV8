<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ControlBancarioRepository;
use App\Rules\modulos\ControlBancarioRules;
use App\Services\LogSistemaService;
use App\Services\ReportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use TCPDF;

class ControlBancarioService
{
    public function __construct(
        private ControlBancarioRepository $repository,
        private ControlBancarioRules $rules,
        private LogSistemaService $logService,
        private ReportService $reportService
    ) {
    }

    public function getFormasBancarias(int $idEmpresa): array
    {
        return $this->repository->getFormasBancarias($idEmpresa);
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        return $this->repository->getAniosDisponibles($idEmpresa);
    }

    private function getFormaBancariaOFallar(int $idFormaPago, int $idEmpresa): array
    {
        $forma = $this->repository->getFormaBancaria($idFormaPago, $idEmpresa);
        if (!$forma) {
            throw new \Exception('La cuenta bancaria seleccionada no es válida.');
        }
        return $forma;
    }

    /**
     * Resumen para las tarjetas KPI, en base al rango de fechas seleccionado:
     * saldo inicial del período, créditos (depósitos/entradas), débitos (pagos/salidas)
     * y saldo final = saldo inicial + créditos - débitos.
     */
    public function getResumenPeriodo(int $idEmpresa, int $idFormaPago, string $fechaInicio, string $fechaFin): array
    {
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);
        $saldoInicialCuenta = $this->repository->getSaldoInicial($idEmpresa, $idFormaPago);

        $resumen = $this->repository->getResumenPeriodo($idEmpresa, (int) $forma['id_cuenta_contable'], $fechaInicio, $fechaFin);

        $saldoInicial = $saldoInicialCuenta + $resumen['delta_antes'];
        $saldoFinal = $saldoInicial + $resumen['creditos'] - $resumen['debitos'];

        return [
            'saldo_inicial' => $saldoInicial,
            'creditos' => $resumen['creditos'],
            'debitos' => $resumen['debitos'],
            'saldo_final' => $saldoFinal,
        ];
    }

    public function getMovimientos(
        int $idEmpresa,
        int $idFormaPago,
        array $filtros,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);
        $saldoInicial = $this->repository->getSaldoInicial($idEmpresa, $idFormaPago);

        $result = $this->repository->getMovimientos(
            $idEmpresa,
            $idFormaPago,
            (int) $forma['id_cuenta_contable'],
            $saldoInicial,
            $filtros,
            $page,
            $perPage,
            $ordenCol,
            $ordenDir
        );

        $hoy = date('Y-m-d');
        foreach ($result['rows'] as &$row) {
            $row['es_posfechado'] = ($row['tipo_transaccion'] === 'CHEQUE' && !empty($row['fecha_cheque']) && $row['fecha_cheque'] > $hoy);
        }
        unset($row);

        return $result;
    }

    public function getChequesPosfechados(int $idEmpresa, ?int $idFormaPago, string $direccion): array
    {
        return $this->repository->getChequesPosfechados($idEmpresa, $idFormaPago, $direccion);
    }

    /** Impide reclasificar/quitar un movimiento cuya fecha cae dentro de un período ya conciliado (bloqueado). */
    private function verificarNoConciliado(int $idFormaPago, int $idCuentaContable, int $idAsientoDetalle, int $idEmpresa): void
    {
        $fechaAsiento = $this->repository->getFechaAsientoDeDetalle($idAsientoDetalle, $idEmpresa, $idCuentaContable);
        if ($fechaAsiento === null) {
            return;
        }
        $conciliacion = $this->repository->getConciliacionVigentePorFecha($idFormaPago, $fechaAsiento);
        if ($conciliacion) {
            $desde = date('d-m-Y', strtotime($conciliacion['fecha_inicio']));
            $hasta = date('d-m-Y', strtotime($conciliacion['fecha_fin']));
            throw new \Exception("Este movimiento pertenece a un período ya conciliado ({$desde} al {$hasta}). Debes reabrir esa conciliación para poder editarlo.");
        }
    }

    /**
     * Marca el período [fechaInicio, fechaFin] de una cuenta como conciliado con el banco,
     * bloqueando la reclasificación de sus movimientos. Guarda el saldo final calculado por
     * el sistema en ese momento (y, si se indica, el saldo del estado de cuenta del banco).
     */
    public function conciliarPeriodo(int $idEmpresa, int $idUsuario, array $data): array
    {
        $this->rules->validarConciliacion($data);
        $idFormaPago = (int) $data['id_forma_pago'];
        $fechaInicio = (string) $data['fecha_inicio'];
        $fechaFin = (string) $data['fecha_fin'];

        $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);

        if ($this->repository->existeSolapamientoConciliacion($idFormaPago, $fechaInicio, $fechaFin)) {
            throw new \Exception('Ya existe una conciliación vigente que se superpone con ese rango de fechas.');
        }

        $resumen = $this->getResumenPeriodo($idEmpresa, $idFormaPago, $fechaInicio, $fechaFin);

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->crearConciliacion([
                'id_empresa' => $idEmpresa,
                'id_forma_pago' => $idFormaPago,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'saldo_inicial' => $resumen['saldo_inicial'],
                'saldo_final' => $resumen['saldo_final'],
                'saldo_banco' => ($data['saldo_banco'] ?? '') !== '' ? (float) $data['saldo_banco'] : null,
                'observaciones' => $data['observaciones'] ?? null,
                'usuario_id' => $idUsuario,
            ]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $conciliacion = $this->repository->getConciliacionPorId($id, $idEmpresa);
        $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'control_bancario_conciliaciones', $id, null, $conciliacion);

        return $conciliacion ?? [];
    }

    public function reabrirConciliacion(int $idEmpresa, int $idUsuario, int $idConciliacion): void
    {
        $antes = $this->repository->getConciliacionPorId($idConciliacion, $idEmpresa);
        if (!$antes || !empty($antes['eliminado'])) {
            throw new \Exception('La conciliación indicada no existe o ya fue reabierta.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->reabrirConciliacion($idConciliacion, $idEmpresa, $idUsuario);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'control_bancario_conciliaciones', $idConciliacion, $antes, null);
    }

    /**
     * Historial de conciliaciones de la cuenta. Marca 'desactualizada' cuando el saldo final
     * recalculado hoy ya no coincide con el que se guardó al momento de conciliar (indicio de
     * que algo se registró/editó después con fecha dentro de ese período).
     */
    public function getConciliaciones(int $idEmpresa, int $idFormaPago): array
    {
        $conciliaciones = $this->repository->listarConciliaciones($idEmpresa, $idFormaPago);
        foreach ($conciliaciones as &$c) {
            if (!empty($c['eliminado'])) {
                $c['desactualizada'] = false;
                continue;
            }
            $resumenActual = $this->getResumenPeriodo($idEmpresa, $idFormaPago, $c['fecha_inicio'], $c['fecha_fin']);
            $c['desactualizada'] = abs($resumenActual['saldo_final'] - (float) $c['saldo_final']) > 0.005;
            $c['saldo_final_actual'] = $resumenActual['saldo_final'];
        }
        unset($c);
        return $conciliaciones;
    }

    /** Conciliación vigente que cubre por completo el rango indicado (para el badge del período mostrado). */
    public function getConciliacionDelRango(int $idFormaPago, string $fechaInicio, string $fechaFin): ?array
    {
        $ini = $this->repository->getConciliacionVigentePorFecha($idFormaPago, $fechaInicio);
        $fin = $this->repository->getConciliacionVigentePorFecha($idFormaPago, $fechaFin);
        if ($ini && $fin && $ini['id'] === $fin['id']) {
            return $ini;
        }
        return null;
    }

    /**
     * Arma todos los datos del reporte de Conciliación Bancaria para el período:
     * resumen (saldo inicial/créditos/débitos/saldo final), el detalle completo de
     * movimientos (mayor contable de la cuenta), separado en créditos y débitos, y
     * los cheques emitidos en circulación / cobrados en el período.
     */
    public function getReporteConciliacion(int $idEmpresa, int $idFormaPago, string $fechaInicio, string $fechaFin): array
    {
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);
        $idCuenta = (int) $forma['id_cuenta_contable'];

        foreach ($this->repository->getFormasBancarias($idEmpresa) as $f) {
            if ((int) $f['id'] === $idFormaPago) {
                $forma = $f;
                break;
            }
        }

        $resumen = $this->getResumenPeriodo($idEmpresa, $idFormaPago, $fechaInicio, $fechaFin);

        $saldoInicialCuenta = $this->repository->getSaldoInicial($idEmpresa, $idFormaPago);
        $mov = $this->repository->getMovimientos(
            $idEmpresa,
            $idFormaPago,
            $idCuenta,
            $saldoInicialCuenta,
            ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'buscar' => ''],
            1,
            1000000,
            'fecha_asiento',
            'ASC'
        );
        $movimientos = $mov['rows'];

        $creditos = array_values(array_filter($movimientos, fn ($r) => (float) $r['debe'] > 0));
        $debitos = array_values(array_filter($movimientos, fn ($r) => (float) $r['haber'] > 0));

        return [
            'forma' => $forma,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'resumen' => $resumen,
            'movimientos' => $movimientos,
            'creditos' => $creditos,
            'debitos' => $debitos,
            'cheques_no_cobrados' => $this->repository->getChequesEmitidosNoCobrados($idEmpresa, $idFormaPago, $idCuenta, $fechaInicio, $fechaFin),
            'cheques_cobrados' => $this->repository->getChequesEmitidosCobradosEnPeriodo($idEmpresa, $idFormaPago, $idCuenta, $fechaInicio, $fechaFin),
        ];
    }

    /**
     * Crea o actualiza la clasificación manual de un movimiento (tipo, cheque, fechas).
     * No toca el asiento contable: solo la anotación propia de este módulo.
     */
    public function guardarClasificacion(int $idEmpresa, int $idUsuario, array $data): array
    {
        $data['tipo_transaccion'] = strtoupper((string) ($data['tipo_transaccion'] ?? ''));
        $data['cheque_direccion'] = !empty($data['cheque_direccion']) ? strtoupper((string) $data['cheque_direccion']) : null;
        $this->rules->validarClasificacion($data);

        $idFormaPago = (int) $data['id_forma_pago'];
        $idAsientoDetalle = (int) $data['id_asiento_detalle'];
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);

        if (!$this->repository->validarAsientoDetalle($idAsientoDetalle, $idEmpresa, (int) $forma['id_cuenta_contable'])) {
            throw new \Exception('El movimiento indicado no pertenece a esta cuenta bancaria.');
        }

        $this->verificarNoConciliado($idFormaPago, (int) $forma['id_cuenta_contable'], $idAsientoDetalle, $idEmpresa);

        $antes = $this->repository->getClasificacionPorAsientoDetalle($idAsientoDetalle, $idEmpresa);

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->upsertClasificacion([
                'id_empresa' => $idEmpresa,
                'id_asiento_detalle' => $idAsientoDetalle,
                'id_forma_pago' => $idFormaPago,
                'tipo_transaccion' => $data['tipo_transaccion'],
                'cheque_direccion' => $data['cheque_direccion'],
                'numero_cheque' => $data['numero_cheque'] ?? null,
                'fecha_cheque' => $data['fecha_cheque'] ?? null,
                'fecha_banco' => $data['fecha_banco'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'usuario_id' => $idUsuario,
            ]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $despues = $this->repository->getClasificacionPorAsientoDetalle($idAsientoDetalle, $idEmpresa);
        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            $antes ? 'actualizar' : 'crear',
            'control_bancario_movimientos',
            $id,
            $antes,
            $despues
        );

        return $despues ?? [];
    }

    public function quitarClasificacion(int $idEmpresa, int $idUsuario, int $idAsientoDetalle): void
    {
        $antes = $this->repository->getClasificacionPorAsientoDetalle($idAsientoDetalle, $idEmpresa);
        if (!$antes) {
            throw new \Exception('Este movimiento no tiene una clasificación manual que quitar.');
        }

        $forma = $this->getFormaBancariaOFallar((int) $antes['id_forma_pago'], $idEmpresa);
        $this->verificarNoConciliado((int) $antes['id_forma_pago'], (int) $forma['id_cuenta_contable'], $idAsientoDetalle, $idEmpresa);

        $this->repository->beginTransaction();
        try {
            $this->repository->quitarClasificacion($idAsientoDetalle, $idEmpresa, $idUsuario);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'control_bancario_movimientos', (int) $antes['id'], $antes, null);
    }

    public function exportarExcel(array $rows, string $empresaNombre, string $cuentaNombre): void
    {
        $headers = ['Fecha', 'Fecha Banco', 'Comprobante', 'Tipo', 'Nº Cheque', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'];
        $dataExport = [];
        foreach ($rows as $r) {
            $dataExport[] = [
                !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                !empty($r['fecha_banco']) ? date('d-m-Y', strtotime($r['fecha_banco'])) : '',
                $r['numero_comprobante'] ?: 'S/N',
                $r['tipo_transaccion'],
                $r['numero_cheque'] ?: '',
                $r['documento_referencia'] ?: '',
                $r['nombre_entidad'] ?: '',
                $r['referencia_detalle'] ?: $r['concepto'] ?: '',
                (float) $r['debe'],
                (float) $r['haber'],
                (float) $r['saldo_acumulado'],
            ];
        }

        $this->reportService->exportToExcel('ControlBancario', $headers, $dataExport, 'Control Bancario', "{$empresaNombre} - {$cuentaNombre}");
    }

    /**
     * Excel de Conciliación Bancaria: hoja "Conciliación" (resumen + detalle de créditos/débitos
     * + cheques emitidos no cobrados/cobrados en el período) y hoja "Mayor Contable" (todos los
     * movimientos de la cuenta contable asignada, para el mismo período).
     */
    public function exportarConciliacionExcel(array $reporte, string $empresaNombre): void
    {
        if (ob_get_length()) {
            ob_end_clean();
        }

        $forma = $reporte['forma'];
        $cuentaNombre = $forma['nombre'] . (!empty($forma['numero_cuenta']) ? ' (' . $forma['numero_cuenta'] . ')' : '');
        $periodo = date('d-m-Y', strtotime($reporte['fecha_inicio'])) . ' al ' . date('d-m-Y', strtotime($reporte['fecha_fin']));

        $spreadsheet = new Spreadsheet();

        // ── Hoja 1: Conciliación ────────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Conciliación');
        $row = $this->xlsTitulo($sheet, 1, "{$empresaNombre} — CONCILIACIÓN BANCARIA", "{$cuentaNombre}  |  Período: {$periodo}");

        $row = $this->xlsSeccion($sheet, $row + 1, 'RESUMEN DEL PERÍODO');
        $sheet->fromArray(['Saldo Inicial', 'Créditos', 'Débitos', 'Saldo Final'], null, "A{$row}");
        $this->xlsEstiloEncabezado($sheet, "A{$row}:D{$row}");
        $row++;
        $sheet->fromArray([
            (float) $reporte['resumen']['saldo_inicial'],
            (float) $reporte['resumen']['creditos'],
            (float) $reporte['resumen']['debitos'],
            (float) $reporte['resumen']['saldo_final'],
        ], null, "A{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $row += 2;

        $row = $this->xlsTablaMovimientos($sheet, $row, 'DETALLE DE CRÉDITOS (entradas)', $reporte['creditos'], 'debe');
        $row = $this->xlsTablaMovimientos($sheet, $row + 1, 'DETALLE DE DÉBITOS (salidas)', $reporte['debitos'], 'haber');
        $row = $this->xlsTablaCheques($sheet, $row + 1, 'CHEQUES EMITIDOS EN CIRCULACIÓN (no cobrados por el banco)', $reporte['cheques_no_cobrados'], false);
        $row = $this->xlsTablaCheques($sheet, $row + 1, 'CHEQUES COBRADOS POR EL BANCO EN EL PERÍODO', $reporte['cheques_cobrados'], true);

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Hoja 2: Mayor Contable ───────────────────────────────────────────
        $sheetMayor = $spreadsheet->createSheet();
        $sheetMayor->setTitle('Mayor Contable');
        $cuentaCodigo = $forma['cuenta_codigo'] ?? '';
        $cuentaCtbNombre = $forma['cuenta_nombre'] ?? '';
        $rowM = $this->xlsTitulo($sheetMayor, 1, "{$empresaNombre} — MAYOR CONTABLE", "Cuenta: {$cuentaCodigo} - {$cuentaCtbNombre}  |  Período: {$periodo}");
        $rowM++;
        $headersMayor = ['Fecha', 'Fecha Banco', 'Comprobante', 'Tipo', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'];
        $sheetMayor->fromArray($headersMayor, null, "A{$rowM}");
        $this->xlsEstiloEncabezado($sheetMayor, 'A' . $rowM . ':J' . $rowM);
        $rowM++;
        foreach ($reporte['movimientos'] as $m) {
            $sheetMayor->fromArray([
                !empty($m['fecha_asiento']) ? date('d-m-Y', strtotime($m['fecha_asiento'])) : '',
                !empty($m['fecha_banco']) ? date('d-m-Y', strtotime($m['fecha_banco'])) : '',
                $m['numero_comprobante'] ?: 'S/N',
                $m['tipo_transaccion'],
                $m['documento_referencia'] ?: '',
                $m['nombre_entidad'] ?: '',
                $m['referencia_detalle'] ?: $m['concepto'] ?: '',
                (float) $m['debe'],
                (float) $m['haber'],
                (float) $m['saldo_acumulado'],
            ], null, "A{$rowM}");
            $rowM++;
        }
        foreach (range('A', 'J') as $col) {
            $sheetMayor->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'ConciliacionBancaria_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function xlsTitulo($sheet, int $row, string $titulo, string $subtitulo): int
    {
        $sheet->setCellValue("A{$row}", mb_strtoupper($titulo));
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        $sheet->setCellValue("A{$row}", $subtitulo);
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return $row;
    }

    private function xlsSeccion($sheet, int $row, string $titulo): int
    {
        $sheet->setCellValue("A{$row}", $titulo);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        return $row + 1;
    }

    private function xlsEstiloEncabezado($sheet, string $rango): void
    {
        $sheet->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    /** Escribe una sección de créditos o débitos (fecha, comprobante, tercero, glosa, monto) con subtotal. */
    private function xlsTablaMovimientos($sheet, int $row, string $titulo, array $rows, string $campoMonto): int
    {
        $row = $this->xlsSeccion($sheet, $row, $titulo);
        $sheet->fromArray(['Fecha', 'Comprobante', 'Tercero', 'Glosa', 'Monto'], null, "A{$row}");
        $this->xlsEstiloEncabezado($sheet, "A{$row}:E{$row}");
        $row++;
        $subtotal = 0.0;
        if (empty($rows)) {
            $sheet->setCellValue("A{$row}", 'Sin movimientos.');
            return $row + 1;
        }
        foreach ($rows as $r) {
            $monto = (float) $r[$campoMonto];
            $subtotal += $monto;
            $sheet->fromArray([
                !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                $r['numero_comprobante'] ?: 'S/N',
                $r['nombre_entidad'] ?: '',
                $r['referencia_detalle'] ?: $r['concepto'] ?: '',
                $monto,
            ], null, "A{$row}");
            $row++;
        }
        $sheet->setCellValue("D{$row}", 'SUBTOTAL');
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("E{$row}", $subtotal);
        $sheet->getStyle("E{$row}")->getFont()->setBold(true);
        return $row + 1;
    }

    /** Escribe una sección de cheques (no cobrados o cobrados en el período). */
    private function xlsTablaCheques($sheet, int $row, string $titulo, array $rows, bool $conFechaBanco): int
    {
        $row = $this->xlsSeccion($sheet, $row, $titulo);
        $headers = $conFechaBanco
            ? ['Fecha Emisión', 'Fecha Cobro (Banco)', 'Nº Cheque', 'Beneficiario', 'Monto']
            : ['Fecha Emisión', 'Nº Cheque', 'Beneficiario', 'Monto'];
        $sheet->fromArray($headers, null, "A{$row}");
        $this->xlsEstiloEncabezado($sheet, 'A' . $row . ':' . ($conFechaBanco ? 'E' : 'D') . $row);
        $row++;
        if (empty($rows)) {
            $sheet->setCellValue("A{$row}", 'Sin cheques.');
            return $row + 1;
        }
        foreach ($rows as $r) {
            $monto = (float) $r['haber'];
            $vals = $conFechaBanco
                ? [
                    !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                    !empty($r['fecha_banco']) ? date('d-m-Y', strtotime($r['fecha_banco'])) : '',
                    $r['numero_cheque'] ?: '',
                    $r['nombre_entidad'] ?: '',
                    $monto,
                ]
                : [
                    !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                    $r['numero_cheque'] ?: '',
                    $r['nombre_entidad'] ?: '',
                    $monto,
                ];
            $sheet->fromArray($vals, null, "A{$row}");
            $row++;
        }
        return $row + 1;
    }

    public function exportarPdf(array $rows, string $empresaNombre, string $cuentaNombre): void
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Contable');
        $pdf->SetAuthor($empresaNombre);
        $pdf->SetTitle('Control Bancario');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, strtoupper($empresaNombre), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'CONTROL BANCARIO - ' . strtoupper($cuentaNombre), 0, 1, 'C');
        $pdf->Ln(2);

        $money = fn ($v) => number_format((float) $v, 2, '.', ',');

        $html = '<table border="1" cellpadding="3">
            <thead><tr style="background-color:#f8f9fa; font-weight:bold; font-size:8px;">
                <th width="8%">Fecha</th><th width="8%">F.Banco</th><th width="9%">Comprobante</th>
                <th width="8%">Tipo</th><th width="17%">Tercero</th><th width="22%">Glosa</th>
                <th width="9%" align="right">Debe</th><th width="9%" align="right">Haber</th><th width="10%" align="right">Saldo</th>
            </tr></thead><tbody>';

        foreach ($rows as $r) {
            $html .= '<tr style="font-size:8px;">
                <td>' . htmlspecialchars(!empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '') . '</td>
                <td>' . htmlspecialchars(!empty($r['fecha_banco']) ? date('d-m-Y', strtotime($r['fecha_banco'])) : '') . '</td>
                <td>' . htmlspecialchars((string) ($r['numero_comprobante'] ?: 'S/N')) . '</td>
                <td>' . htmlspecialchars((string) $r['tipo_transaccion']) . '</td>
                <td>' . htmlspecialchars((string) ($r['nombre_entidad'] ?? '')) . '</td>
                <td>' . htmlspecialchars((string) ($r['referencia_detalle'] ?: $r['concepto'] ?: '')) . '</td>
                <td align="right">' . $money($r['debe']) . '</td>
                <td align="right">' . $money($r['haber']) . '</td>
                <td align="right">' . $money($r['saldo_acumulado']) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'ControlBancario_' . date('YmdHis') . '.pdf';
        if (ob_get_length()) {
            ob_end_clean();
        }
        $pdf->Output($filename, 'D');
        exit;
    }
}
