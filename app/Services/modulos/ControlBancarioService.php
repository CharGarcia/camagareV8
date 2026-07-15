<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ControlBancarioRepository;
use App\Rules\modulos\ControlBancarioRules;
use App\Services\LogSistemaService;
use App\Services\ReportService;
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

    public function getSaldos(int $idEmpresa, int $idFormaPago): array
    {
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);
        $saldoInicial = $this->repository->getSaldoInicial($idEmpresa, $idFormaPago);
        $saldoActual = $this->repository->getSaldoActual($idEmpresa, (int) $forma['id_cuenta_contable'], $saldoInicial);

        return [
            'saldo_inicial' => $saldoInicial,
            'saldo_actual' => $saldoActual,
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
