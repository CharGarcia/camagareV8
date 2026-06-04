<?php
declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportService
{
    /**
     * Genera y descarga un archivo Excel (.xlsx) a partir de un arreglo de datos.
     * 
     * @param string $filename Nombre del archivo (sin extensión)
     * @param array $headers Encabezados de la tabla ['Columna A', 'Columna B', ...]
     * @param array $data Filas de datos [['val1', 'val2'], ['val1', 'val2'], ...]
     * @param string $sheetTitle Título de la hoja
     * @param string|null $mainTitle Título principal (ej: Nombre de Empresa) para mostrar arriba
     */
    public function exportToExcel(string $filename, array $headers, array $data, string $sheetTitle = 'Reporte', ?string $mainTitle = null): void
    {
        try {
            // Limpiar búfer de salida para evitar corrupción
            if (ob_get_length()) ob_end_clean();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($sheetTitle);

            $dataRowStart = 1;

            // Si hay un título principal, lo ponemos en la fila 1 y movemos la tabla abajo
            if ($mainTitle) {
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
                $sheet->setCellValue('A1', mb_strtoupper($mainTitle));
                $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $dataRowStart = 3; // Empezar encabezados en la fila 3
            }

            // Estilo para encabezados
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ];

            // Escribir encabezados
            $col = 1;
            foreach ($headers as $headerText) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValueExplicit($colLetter . $dataRowStart, $headerText, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $col++;
            }
            
            // Aplicar estilo a la fila de encabezados
            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A{$dataRowStart}:{$lastColLetter}{$dataRowStart}")->applyFromArray($headerStyle);

            // Escribir datos
            $rowNum = $dataRowStart + 1;
            foreach ($data as $rowData) {
                $col = 1;
                foreach ($rowData as $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    // Si el valor es numérico pero largo (como identificación), forzar string
                    if (is_string($value) && ctype_digit($value) && strlen($value) > 10) {
                        $sheet->setCellValueExplicit($colLetter . $rowNum, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    } else {
                        $sheet->setCellValue($colLetter . $rowNum, $value);
                    }
                    $col++;
                }
                $rowNum++;
            }

            // Auto-ajustar columnas
            for ($i = 1; $i <= count($headers); $i++) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }

            // Configurar cabeceras de descarga
            $fullFilename = $filename . '_' . date('Ymd_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $fullFilename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Throwable $e) {
            throw new \Exception("Error al generar el reporte Excel: " . $e->getMessage());
        }
    }
}
