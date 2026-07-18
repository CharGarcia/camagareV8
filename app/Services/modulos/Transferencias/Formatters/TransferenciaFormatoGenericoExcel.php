<?php
declare(strict_types=1);

namespace App\Services\modulos\Transferencias\Formatters;

use App\Services\modulos\Transferencias\TransferenciaFormatterInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Formato genérico (no atado a un banco específico). Sirve de plantilla
 * universal mientras no se implemente el layout real de cada banco.
 */
class TransferenciaFormatoGenericoExcel implements TransferenciaFormatterInterface
{
    public function getExtension(): string
    {
        return 'xlsx';
    }

    public function generar(array $lote, array $lineas, string $rutaDestino): string
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Transferencias');

        $headers = ['Tipo Beneficiario', 'Identificación', 'Nombre', 'Banco', 'Tipo Cuenta', 'Número Cuenta', 'Monto', 'Concepto'];
        $ci = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueExplicit([$ci, 1], $h, DataType::TYPE_STRING);
            $sheet->getStyle([$ci, 1])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle([$ci, 1])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4472C4');
            $sheet->getColumnDimensionByColumn($ci)->setWidth(20);
            $ci++;
        }

        $row = 2;
        foreach ($lineas as $l) {
            $sheet->setCellValueExplicit([1, $row], (string) ($l['tipo_beneficiario'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $row], (string) ($l['identificacion'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([3, $row], (string) ($l['nombre_beneficiario'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([4, $row], (string) ($l['banco_nombre'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([5, $row], (string) ($l['tipo_cuenta'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([6, $row], (string) ($l['numero_cuenta'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue([7, $row], round((float) ($l['monto'] ?? 0), 2));
            $sheet->setCellValueExplicit([8, $row], (string) ($l['concepto'] ?? ''), DataType::TYPE_STRING);
            $row++;
        }

        $ruta = $rutaDestino . '.xlsx';
        (new Xlsx($ss))->save($ruta);
        $ss->disconnectWorksheets();
        return $ruta;
    }
}
