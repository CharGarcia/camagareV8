<?php

declare(strict_types=1);

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Fusiona varios PDF ya generados (bytes) en un solo archivo PDF, página por
 * página, sin necesidad de volver a componer cada documento. Usado por
 * Descargas Masivas para entregar un único PDF (en vez de un ZIP) cuando la
 * cantidad de documentos es pequeña.
 *
 * Requiere setasign/fpdi (compatible con TCPDF, ya usado en todo el sistema).
 */
class PdfMergerService
{
    /**
     * @param string[] $pdfsBytes Cada elemento es el contenido binario de un PDF ya generado.
     * @return string Contenido binario del PDF fusionado.
     * @throws \RuntimeException si no se pudo importar ninguna página.
     */
    public function fusionar(array $pdfsBytes): string
    {
        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $paginasImportadas = 0;
        foreach ($pdfsBytes as $bytes) {
            if (!is_string($bytes) || $bytes === '') {
                continue;
            }
            try {
                $origen = 'data://application/pdf;base64,' . base64_encode($bytes);
                $totalPaginas = $pdf->setSourceFile($origen);
                for ($pagina = 1; $pagina <= $totalPaginas; $pagina++) {
                    $idPlantilla = $pdf->importPage($pagina);
                    $tamano = $pdf->getTemplateSize($idPlantilla);
                    $pdf->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
                    $pdf->useTemplate($idPlantilla);
                    $paginasImportadas++;
                }
            } catch (\Throwable $e) {
                // Un documento corrupto/no fusionable no debe tumbar el resto del lote.
                \App\Services\ErrorLogService::registrar($e, ['servicio' => 'PdfMergerService']);
            }
        }

        if ($paginasImportadas === 0) {
            throw new \RuntimeException('No se pudo fusionar ningún documento en el PDF.');
        }

        return $pdf->Output('', 'S');
    }
}
