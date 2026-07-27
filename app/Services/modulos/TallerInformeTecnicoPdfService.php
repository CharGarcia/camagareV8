<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Informe Técnico del taller (A4 vertical).
 *
 * Es el documento que pidió el dueño del taller: la historia completa de lo que
 * se le hizo al vehículo. Reconstruye el paso por cada departamento con sus
 * tiempos, el responsable, el trabajo declarado y los repuestos consumidos ahí,
 * más el diagnóstico, la aprobación del cliente, las fotos de evidencia y las
 * condiciones de garantía.
 *
 * Se entrega al cliente al retirar el vehículo y sirve como respaldo técnico
 * ante un reclamo de garantía o de la aseguradora.
 */
class TallerInformeTecnicoPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186;

    /** @param string $outputDest 'I' inline, 'D' descarga, 'S' string */
    public function generar(array $orden, array $empresa, string $outputDest = 'I')
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Informe Técnico ' . ($orden['numero_orden'] ?? ''));
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $orden);
        $y = $this->dibujarResumen($orden, $y + 3);
        $y = $this->dibujarDiagnostico($orden, $y + 3);
        $y = $this->dibujarAprobacion($orden, $y + 3);
        $y = $this->dibujarRecorrido($orden, $y + 3);
        $y = $this->dibujarConsumos($orden, $y + 3);
        $y = $this->dibujarNoEjecutado($orden, $y + 3);
        $y = $this->dibujarFotos($orden, $y + 3);
        $y = $this->dibujarGarantia($orden, $y + 3);
        $this->dibujarFirmas($orden, $y + 4);

        $nombre = 'Informe_Tecnico_' . (($orden['numero_orden'] ?? '') !== '' ? $orden['numero_orden'] : 'OT') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    // ─── Encabezado ───────────────────────────────────────────────────────────
    private function dibujarEncabezado(array $empresa, array $orden): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y0  = 10;

        $izqW = 110;
        $derW = $this->contentW - $izqW - 2;
        $derX = $mL + $izqW + 2;

        $logoPath = TallerPdfHelper::resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 24, 0, '', '', 'T', false, 300);
            $textoX = $mL + 27;
        }

        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell($mL + $izqW - $textoX, 5, strtoupper((string) ($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetX($textoX);
        $pdf->SetFont('helvetica', '', 8);
        foreach (array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
        ]) as $ln) {
            $pdf->SetX($textoX);
            $pdf->MultiCell($mL + $izqW - $textoX, 4, $ln, 0, 'L', false, 1);
        }

        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($derW, 5, 'INFORME TÉCNICO', 0, 1, 'C');

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'Orden N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, trim((string) ($orden['numero_orden'] ?? '—')), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell($derW, 4, 'Emitido: ' . date('d/m/Y H:i'), 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    // ─── Resumen del vehículo y del servicio ──────────────────────────────────
    private function dibujarResumen(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $boxH = 27;
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'DF');

        $lbl = function (string $t, float $wl = 24) use ($pdf) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($wl, 5, $t, 0, 0, 'L');
        };
        $val = function (string $t, float $wv, int $ln = 0) use ($pdf) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell($wv, 5, $t, 0, $ln, 'L');
        };

        $vehiculo = trim((string) ($orden['marca'] ?? '') . ' ' . (string) ($orden['modelo'] ?? '') . ' ' . (string) ($orden['anio'] ?? ''));

        $pdf->SetXY($mL + 2, $y + 2);
        $lbl('Placa:');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(38, 5, strtoupper((string) ($orden['placa'] ?? '—')), 0, 0, 'L');
        $lbl('Vehículo:', 20);
        $val(TallerPdfHelper::ajustar($pdf, $vehiculo !== '' ? $vehiculo : '—', 62), 62);
        $lbl('Color:', 16);
        $val((string) ($orden['color'] ?? '—'), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Cliente:');
        $val(TallerPdfHelper::ajustar($pdf, (string) ($orden['cliente_nombre'] ?? '—'), 96), 96);
        $lbl('Chasis:', 16);
        $val(TallerPdfHelper::ajustar($pdf, (string) ($orden['chasis'] ?? '—'), 34), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Ingreso:');
        $val(TallerPdfHelper::fecha($orden['fecha_ingreso'] ?? null), 38);
        $lbl('Entrega:', 20);
        $val(TallerPdfHelper::fecha($orden['fecha_entrega'] ?? null), 62);
        $lbl('Duración:', 18);
        $val(TallerPdfHelper::duracion($orden['fecha_ingreso'] ?? null, $orden['fecha_entrega'] ?? date('Y-m-d H:i:s')), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $kmIng = ($orden['kilometraje'] ?? '') !== '' ? number_format((float) $orden['kilometraje'], 0, ',', '.') . ' km' : '—';
        $kmSal = ($orden['kilometraje_salida'] ?? '') !== '' && $orden['kilometraje_salida'] !== null
            ? number_format((float) $orden['kilometraje_salida'], 0, ',', '.') . ' km' : '—';
        $lbl('Km ingreso:');
        $val($kmIng, 38);
        $lbl('Km salida:', 20);
        $val($kmSal, 62);
        $lbl('Servicio:', 18);
        $val(ucfirst((string) ($orden['tipo_servicio'] ?? '—')), 0, 1);

        return $y + $boxH;
    }

    // ─── Motivo y diagnóstico ─────────────────────────────────────────────────
    private function dibujarDiagnostico(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $motivo = trim((string) ($orden['motivo_ingreso'] ?? ''));
        $diag   = trim((string) ($orden['diagnostico_texto'] ?? ''));
        if ($motivo === '' && $diag === '') return $y;

        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'MOTIVO DE INGRESO Y DIAGNÓSTICO');

        $pdf->SetFont('helvetica', '', 8.5);
        if ($motivo !== '') {
            $pdf->SetXY($mL, $y);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($this->contentW, 4.5, ' Reportado por el cliente:', 'LTR', 1, 'L');
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->MultiCell($this->contentW, 4.2, ' ' . $motivo, 'LBR', 'L', false, 1);
        }
        if ($diag !== '') {
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($this->contentW, 4.5, ' Diagnóstico del taller:', 'LTR', 1, 'L');
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->MultiCell($this->contentW, 4.2, ' ' . $diag, 'LBR', 'L', false, 1);
        }

        return $pdf->GetY();
    }

    // ─── Constancia de la aprobación del cliente ──────────────────────────────
    private function dibujarAprobacion(array $orden, float $y): float
    {
        if (\App\Helpers\Booleano::no($orden['aprobado'] ?? false)) return $y;

        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetFillColor(232, 245, 233);
        $pdf->SetDrawColor(120, 160, 120);
        $pdf->SetLineWidth(0.2);

        $texto = 'Presupuesto aprobado por ' . trim((string) ($orden['aprobado_por'] ?? ''))
               . ' el ' . TallerPdfHelper::fecha($orden['aprobado_fecha'] ?? null)
               . ' vía ' . TallerPdfHelper::etiquetaMedioAprobacion((string) ($orden['aprobado_medio'] ?? ''))
               . '.';
        if (!empty($orden['aprobado_observacion'])) {
            $texto .= ' ' . $orden['aprobado_observacion'];
        }
        $pdf->MultiCell($this->contentW, 4.5, ' ' . $texto, 1, 'L', true, 1);

        return $pdf->GetY();
    }

    // ─── Recorrido por los departamentos (el corazón del informe) ─────────────
    private function dibujarRecorrido(array $orden, float $y): float
    {
        $pdf    = $this->pdf;
        $mL     = $this->marginL;
        $etapas = $orden['etapas'] ?? [];
        $lineas = $orden['detalles'] ?? [];

        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'TRABAJOS REALIZADOS POR DEPARTAMENTO');

        if (empty($etapas)) {
            $pdf->SetXY($mL, $y);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell($this->contentW, 6, 'La orden no registró paso por departamentos.', 1, 1, 'C');
            return $pdf->GetY();
        }

        // Líneas agrupadas por departamento, para listarlas bajo su etapa.
        $porDepartamento = [];
        foreach ($lineas as $l) {
            $idDep = (int) ($l['id_departamento'] ?? 0);
            $porDepartamento[$idDep][] = $l;
        }

        foreach ($etapas as $i => $e) {
            if ($pdf->GetY() > 240) {
                $pdf->AddPage();
                $pdf->SetY(15);
            }

            $idDep = (int) ($e['id_departamento'] ?? 0);
            $yE    = $pdf->GetY();

            // Barra del departamento con su color.
            [$r, $g, $b] = $this->hexARgb((string) ($e['departamento_color'] ?? '#0d6efd'));
            $pdf->SetXY($mL, $yE);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetDrawColor($r, $g, $b);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(70, 5.5, ' ' . ($i + 1) . '. ' . strtoupper((string) ($e['departamento_nombre'] ?? '')), 1, 0, 'L', true);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell($this->contentW - 70, 5.5,
                TallerPdfHelper::etiquetaEstadoEtapa((string) ($e['estado'] ?? ''))
                . '   |   ' . TallerPdfHelper::fecha($e['fecha_inicio'] ?? null, 'd/m/Y H:i')
                . ' → ' . TallerPdfHelper::fecha($e['fecha_fin'] ?? null, 'd/m/Y H:i')
                . '   |   ' . TallerPdfHelper::duracion($e['fecha_inicio'] ?? null, $e['fecha_fin'] ?? null) . ' ',
                1, 1, 'R', true);
            $pdf->SetTextColor(0, 0, 0);

            // Responsable y trabajo declarado.
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell(28, 4.5, ' Responsable:', 'LTB', 0, 'L');
            $pdf->SetFont('helvetica', '', 7.5);
            $resp = trim((string) ($e['responsable_nombre'] ?? '')) !== ''
                ? (string) $e['responsable_nombre']
                : (string) ($e['usuario_fin_nombre'] ?? $e['usuario_inicio_nombre'] ?? '—');
            $pdf->Cell($this->contentW - 28, 4.5, $resp, 'RTB', 1, 'L');

            $trabajo = trim((string) ($e['trabajo_realizado'] ?? ''));
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell($this->contentW, 4.2, ' ' . ($trabajo !== '' ? $trabajo : 'Sin descripción de trabajo.'), 'LBR', 'L', false, 1);

            if (!empty($e['observaciones'])) {
                $pdf->SetX($mL);
                $pdf->SetFont('helvetica', 'I', 7.5);
                $pdf->MultiCell($this->contentW, 4, ' Observación: ' . $e['observaciones'], 'LBR', 'L', false, 1);
            }

            // Lo que consumió este departamento.
            $consumos = array_values(array_filter(
                $porDepartamento[$idDep] ?? [],
                fn($l) => in_array((string) $l['estado_linea'], ['aprobada', 'ejecutada'], true)
            ));
            if (!empty($consumos)) {
                $pdf->SetX($mL);
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetFillColor(248, 249, 250);
                $pdf->Cell($this->contentW, 4.2, '  Repuestos y trabajos de este departamento', 'LR', 1, 'L', true);
                $pdf->SetFont('helvetica', '', 7);
                foreach ($consumos as $c) {
                    $pdf->SetX($mL);
                    $etiqueta = '   • ' . (string) ($c['descripcion'] ?? '')
                        . '  (' . TallerPdfHelper::etiquetaTipoLinea((string) ($c['tipo_linea'] ?? '')) . ')';
                    if (\App\Helpers\Booleano::es($c['provisto_cliente'] ?? false)) {
                        $etiqueta .= '  [provisto por el cliente]';
                    }
                    $pdf->Cell($this->contentW - 30, 4, TallerPdfHelper::ajustar($pdf, $etiqueta, $this->contentW - 33), 'LR', 0, 'L');
                    $pdf->Cell(30, 4, 'Cant: ' . number_format((float) ($c['cantidad'] ?? 0), 2) . '  ', 'R', 1, 'R');
                }
                $pdf->SetX($mL);
                $pdf->Cell($this->contentW, 0.6, '', 'LBR', 1, 'L');
            }

            $pdf->Ln(1.5);
        }

        return $pdf->GetY();
    }

    // ─── Resumen de repuestos y mano de obra ──────────────────────────────────
    private function dibujarConsumos(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $ejecutados = array_values(array_filter(
            $orden['detalles'] ?? [],
            fn($d) => in_array((string) $d['estado_linea'], ['aprobada', 'ejecutada'], true)
        ));
        if (empty($ejecutados)) return $y;

        if ($pdf->GetY() > 230) {
            $pdf->AddPage();
            $y = 15;
        }

        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'RESUMEN DE REPUESTOS Y MANO DE OBRA');

        $cols = [
            ['t' => 'Descripción',  'w' => 0,  'a' => 'L'],
            ['t' => 'Departamento', 'w' => 34, 'a' => 'L'],
            ['t' => 'Tipo',         'w' => 20, 'a' => 'C'],
            ['t' => 'Cant.',        'w' => 15, 'a' => 'R'],
            ['t' => 'Total',        'w' => 22, 'a' => 'R'],
        ];
        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = max(40.0, $this->contentW - $fixed); } }
        unset($c);

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        foreach ($cols as $c) { $pdf->Cell($c['w'], 5.5, $c['t'], 1, 0, 'C', true); }
        $pdf->Ln();
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7.5);

        $alt = false;
        foreach ($ejecutados as $d) {
            if ($pdf->GetY() > 265) {
                $pdf->AddPage();
                $pdf->SetY(15);
            }
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);
            $pdf->SetX($mL);

            $vals = [
                (string) ($d['descripcion'] ?? ''),
                (string) ($d['departamento_nombre'] ?? '—'),
                TallerPdfHelper::etiquetaTipoLinea((string) ($d['tipo_linea'] ?? '')),
                number_format((float) ($d['cantidad'] ?? 0), 2),
                \App\Helpers\Booleano::no($d['facturable'] ?? false) ? 'N/C' : number_format((float) ($d['total_linea'] ?? 0), 2),
            ];
            foreach ($cols as $i => $c) {
                $pdf->Cell($c['w'], 4.6, ' ' . TallerPdfHelper::ajustar($pdf, $vals[$i], $c['w'] - 3), 1, $i === count($cols) - 1 ? 1 : 0, $c['a'], true);
            }
        }

        // Totales de cierre.
        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($this->contentW - 22, 6, 'TOTAL DEL SERVICIO  ', 1, 0, 'R', true);
        $pdf->Cell(22, 6, number_format((float) ($orden['total'] ?? 0), 2), 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);

        return $pdf->GetY();
    }

    // ─── Lo que el cliente NO aprobó: queda advertido por escrito ─────────────
    private function dibujarNoEjecutado(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $pendientes = array_values(array_filter(
            $orden['detalles'] ?? [],
            fn($d) => in_array((string) $d['estado_linea'], ['sugerida', 'rechazada'], true)
        ));
        $recomendaciones = trim((string) ($orden['recomendaciones'] ?? ''));
        if (empty($pendientes) && $recomendaciones === '') return $y;

        if ($pdf->GetY() > 235) {
            $pdf->AddPage();
            $y = 15;
        }

        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'TRABAJOS PENDIENTES Y RECOMENDACIONES');

        $pdf->SetFont('helvetica', '', 7.5);
        foreach ($pendientes as $p) {
            $pdf->SetX($mL);
            $texto = ' • ' . (string) ($p['descripcion'] ?? '')
                . ' — ' . TallerPdfHelper::etiquetaEstadoLinea((string) ($p['estado_linea'] ?? ''));
            if (!empty($p['motivo_rechazo'])) {
                $texto .= ' (' . $p['motivo_rechazo'] . ')';
            }
            $pdf->MultiCell($this->contentW, 4.2, $texto, 'LR', 'L', false, 1);
        }
        if ($recomendaciones !== '') {
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($this->contentW, 4.2, ' Recomendaciones del taller:', 'LR', 1, 'L');
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell($this->contentW, 4.2, ' ' . $recomendaciones, 'LR', 'L', false, 1);
        }
        $pdf->SetX($mL);
        $pdf->Cell($this->contentW, 0.6, '', 'LBR', 1, 'L');

        return $pdf->GetY();
    }

    // ─── Fotos de evidencia ───────────────────────────────────────────────────
    private function dibujarFotos(array $orden, float $y): float
    {
        $fotos = $orden['fotos'] ?? [];
        if (empty($fotos)) return $y;

        $pdf = $this->pdf;
        $mL  = $this->marginL;

        // Se limita a 6 imágenes para que el informe siga siendo manejable.
        $fotos = array_slice($fotos, 0, 6);

        if ($pdf->GetY() > 200) {
            $pdf->AddPage();
            $y = 15;
        }
        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'EVIDENCIA FOTOGRÁFICA');

        $cols  = 3;
        $gap   = 4;
        $imgW  = ($this->contentW - $gap * ($cols - 1)) / $cols;
        $imgH  = $imgW * 0.72;
        $col   = 0;
        $yFila = $y + 1;

        foreach ($fotos as $f) {
            $ruta = \MVC_ROOT . '/public/' . ltrim((string) ($f['ruta_archivo'] ?? ''), '/');
            if (!is_file($ruta)) continue;

            if ($yFila + $imgH + 6 > 275) {
                $pdf->AddPage();
                $yFila = 15;
                $col = 0;
            }

            $x = $mL + $col * ($imgW + $gap);
            try {
                $pdf->Image($ruta, $x, $yFila, $imgW, $imgH, '', '', '', true, 150, '', false, false, 1, false, false, false);
            } catch (\Throwable $e) {
                // Una imagen ilegible no debe tumbar el informe completo.
                continue;
            }
            $pdf->SetXY($x, $yFila + $imgH + 0.5);
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->Cell($imgW, 3.5, TallerPdfHelper::ajustar($pdf,
                ucfirst((string) ($f['momento'] ?? '')) . (!empty($f['descripcion']) ? ' — ' . $f['descripcion'] : ''),
                $imgW), 0, 0, 'C');

            $col++;
            if ($col >= $cols) {
                $col = 0;
                $yFila += $imgH + 6;
            }
        }

        return $col > 0 ? $yFila + $imgH + 6 : $yFila;
    }

    // ─── Garantía y próximo mantenimiento ─────────────────────────────────────
    private function dibujarGarantia(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $dias = (int) ($orden['garantia_dias'] ?? 0);
        $km   = (int) ($orden['garantia_km'] ?? 0);
        $proxKm   = (int) ($orden['proximo_mantenimiento_km'] ?? 0);
        $proxCita = $orden['proxima_cita'] ?? null;

        if ($dias <= 0 && $km <= 0 && $proxKm <= 0 && empty($proxCita)) return $y;

        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            $y = 15;
        }
        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'GARANTÍA Y PRÓXIMO MANTENIMIENTO');

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', '', 8);

        $partes = [];
        if ($dias > 0 || $km > 0) {
            $g = 'Garantía de los trabajos: ';
            $g .= $dias > 0 ? $dias . ' días' : '';
            $g .= ($dias > 0 && $km > 0) ? ' o ' : '';
            $g .= $km > 0 ? number_format((float) $km, 0, ',', '.') . ' km' : '';
            $g .= ($dias > 0 && $km > 0) ? ', lo que ocurra primero.' : '.';
            $partes[] = $g;
        }
        if ($proxKm > 0) {
            $partes[] = 'Próximo mantenimiento sugerido a los ' . number_format((float) $proxKm, 0, ',', '.') . ' km.';
        }
        if (!empty($proxCita)) {
            $partes[] = 'Próxima cita sugerida: ' . TallerPdfHelper::fecha($proxCita, 'd/m/Y') . '.';
        }
        $partes[] = 'La garantía cubre exclusivamente los trabajos y repuestos detallados en este informe. '
                  . 'No cubre daños por uso indebido, falta de mantenimiento ni intervenciones de terceros.';

        $pdf->MultiCell($this->contentW, 4.2, ' ' . implode(' ', $partes), 1, 'J', false, 1);

        return $pdf->GetY();
    }

    // ─── Firmas ───────────────────────────────────────────────────────────────
    private function dibujarFirmas(array $orden, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        if ($y > 245) {
            $pdf->AddPage();
            $y = 40;
        }

        $colW   = $this->contentW / 2;
        $yLinea = $y + 16;
        if ($yLinea > 272) $yLinea = 272;

        $firmas = [
            ['Jefe de taller', trim((string) ($orden['empleado_jefe_nombre'] ?? $orden['asesor_nombre'] ?? ''))],
            ['Cliente / Recibí conforme', trim((string) ($orden['entregado_a'] ?? $orden['cliente_nombre'] ?? ''))],
        ];

        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 10, $yLinea, $x + $colW - 10, $yLinea);
        }
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) { $pdf->Cell($colW, 4, $f[0], 0, 0, 'C'); }

        $pdf->SetFont('helvetica', '', 7.5);
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->SetXY($x + 3, $yLinea + 5);
            $pdf->MultiCell($colW - 6, 3.4, $f[1] !== '' ? $f[1] : ' ', 0, 'C', false, 0, '', '', true, 0, false, true, 0, 'T');
        }
    }

    /** Convierte '#0d6efd' en [13, 110, 253] para las barras de departamento. */
    private function hexARgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [13, 110, 253];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
