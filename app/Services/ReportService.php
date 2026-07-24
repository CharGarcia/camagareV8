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
            $sheet->setTitle(substr($sheetTitle, 0, 31));

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

    /**
     * Genera un Excel con varias secciones (ej: una por cuenta contable), cada una con su
     * propio subtítulo y su propia fila de encabezados repetida justo encima de sus datos —
     * a diferencia de exportToExcel(), que tiene un único encabezado fijo para toda la hoja.
     *
     * @param array $secciones Cada elemento: ['titulo' => string, 'filas' => array de filas,
     *                          'resumen' => array opcional, fila de subtotal con el mismo
     *                          número de columnas que $headers]
     * @param array|null $filaFinal Fila suelta en negrita al final de todo (ej: total general)
     */
    public function exportToExcelSeccionado(string $filename, array $headers, array $secciones, string $sheetTitle = 'Reporte', ?string $mainTitle = null, ?array $filaFinal = null): void
    {
        try {
            if (ob_get_length()) ob_end_clean();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($sheetTitle, 0, 31));

            $numCols = count($headers);
            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);

            $rowNum = 1;

            if ($mainTitle) {
                $sheet->setCellValue("A{$rowNum}", mb_strtoupper($mainTitle));
                $sheet->mergeCells("A{$rowNum}:{$lastColLetter}{$rowNum}");
                $sheet->getStyle("A{$rowNum}")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $rowNum += 2; // deja una fila en blanco antes de la primera sección
            }

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
            $subtituloStyle = [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E2F3']
                ],
            ];

            foreach ($secciones as $seccion) {
                // Subtítulo de la sección (ej: "código - nombre de cuenta"), arriba de los encabezados
                $sheet->setCellValueExplicit("A{$rowNum}", $seccion['titulo'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->mergeCells("A{$rowNum}:{$lastColLetter}{$rowNum}");
                $sheet->getStyle("A{$rowNum}")->applyFromArray($subtituloStyle);
                $rowNum++;

                // Encabezados de columna, repetidos para esta sección
                $col = 1;
                foreach ($headers as $headerText) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValueExplicit($colLetter . $rowNum, $headerText, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $col++;
                }
                $sheet->getStyle("A{$rowNum}:{$lastColLetter}{$rowNum}")->applyFromArray($headerStyle);
                $rowNum++;

                // Filas de datos de la sección
                foreach ($seccion['filas'] as $rowData) {
                    $col = 1;
                    foreach ($rowData as $value) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                        if (is_string($value) && ctype_digit($value) && strlen($value) > 10) {
                            $sheet->setCellValueExplicit($colLetter . $rowNum, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        } else {
                            $sheet->setCellValue($colLetter . $rowNum, $value);
                        }
                        $col++;
                    }
                    $rowNum++;
                }

                // Fila de resumen/subtotal opcional
                if (!empty($seccion['resumen'])) {
                    $col = 1;
                    foreach ($seccion['resumen'] as $value) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                        $sheet->setCellValue($colLetter . $rowNum, $value);
                        $col++;
                    }
                    $sheet->getStyle("A{$rowNum}:{$lastColLetter}{$rowNum}")->getFont()->setBold(true);
                    $rowNum++;
                }

                $rowNum++; // fila en blanco entre secciones
            }

            if ($filaFinal) {
                $col = 1;
                foreach ($filaFinal as $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValue($colLetter . $rowNum, $value);
                    $col++;
                }
                $sheet->getStyle("A{$rowNum}:{$lastColLetter}{$rowNum}")->getFont()->setBold(true);
                $rowNum++;
            }

            for ($i = 1; $i <= $numCols; $i++) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }

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
