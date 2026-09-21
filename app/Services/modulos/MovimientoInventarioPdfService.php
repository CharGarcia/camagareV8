<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Comprobante (ficha) de un movimiento de inventario en PDF (A4 vertical).
 *
 * Formateador puro: no accede a la base de datos. Recibe el movimiento ya
 * resuelto por InventarioService::getById() (que incluye los anulados) y los
 * datos de la empresa con su logo.
 */
class MovimientoInventarioPdfService
{
    private TCPDF $pdf;
    private float $marginL = 12;
    private float $marginR = 12;
    private float $contentW = 186; // 210 - 12 - 12

    /**
     * @param array  $mov     Fila de inventario_kardex + producto_nombre, producto_codigo,
     *                        bodega_nombre, usuario_nombre, nombre_medida, abreviatura_medida.
     * @param array  $empresa Datos de la empresa (con logo_ruta del establecimiento).
     * @param string $dest    Destino TCPDF: 'I' inline, 'D' descarga, 'S' string.
     */
    public function generar(array $mov, array $empresa, string $dest = 'D')
    {
        $numero  = 'MOV-' . str_pad((string) ($mov['id'] ?? 0), 6, '0', STR_PAD_LEFT);
        $anulado = in_array($mov['eliminado'] ?? false, [true, 't', '1', 1], true);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor((string) ($empresa['nombre'] ?? ''));
        $this->pdf->SetTitle('Movimiento de inventario ' . $numero);
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, $anulado);
        $this->pdf->SetY($y + 4);
        $this->pdf->writeHTML($this->construirCuerpo($mov, $anulado), true, false, true, false, '');
        $this->dibujarFirmas($mov, $this->pdf->GetY());
        $this->dibujarPie();

        return $this->pdf->Output('Movimiento_inventario_' . $numero . '.pdf', $dest);
    }

    // ────────────────────────────────────────────────────────────────
    // ENCABEZADO
    // ────────────────────────────────────────────────────────────────
    private function dibujarEncabezado(array $empresa, string $numero, bool $anulado): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y0  = 10;

        $izqW = 110;
        $derW = $this->contentW - $izqW - 2;
        $derX = $mL + $izqW + 2;

        // Logo (opcional)
        $logoPath = $this->resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 24, 0, '', '', 'T', false, 300);
            $textoX = $mL + 27;
        }

        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell($mL + $izqW - $textoX, 5, strtoupper((string) ($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetFont('helvetica', '', 8);
        $lineas = array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
            (string) ($empresa['correo'] ?? $empresa['email'] ?? ''),
        ]);
        foreach ($lineas as $ln) {
            $pdf->SetX($textoX);
            $pdf->MultiCell($mL + $izqW - $textoX, 4, $ln, 0, 'L', false, 1);
        }

        // Caja del comprobante (derecha)
        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->MultiCell($derW, 4, "MOVIMIENTO\nDE INVENTARIO", 0, 'C', false, 1);
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, $numero, 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $yFin = max($pdf->GetY(), $y0 + $boxH);

        // Sello de anulado: un comprobante anulado no debe poder confundirse con uno vigente.
        if ($anulado) {
            $pdf->SetFillColor(248, 215, 218);
            $pdf->SetDrawColor(220, 53, 69);
            $pdf->SetTextColor(155, 22, 34);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY($mL, $yFin + 2);
            $pdf->Cell($this->contentW, 7, 'MOVIMIENTO ANULADO - NO AFECTA EL STOCK ACTUAL', 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(60, 60, 60);
            $pdf->SetFillColor(255, 255, 255);
            $yFin = $pdf->GetY();
        }

        return $yFin;
    }

    // ────────────────────────────────────────────────────────────────
    // CUERPO
    // ────────────────────────────────────────────────────────────────
    private function construirCuerpo(array $mov, bool $anulado): string
    {
        $esEntrada = ((string) ($mov['tipo_movimiento'] ?? '')) === 'entrada';
        $cantidad  = abs((float) ($mov['cantidad'] ?? 0));
        $signo     = $esEntrada ? '+' : '-';

        $medida = trim((string) ($mov['nombre_medida'] ?? ''));
        if ($medida !== '' && !empty($mov['abreviatura_medida'])) {
            $medida .= ' (' . $mov['abreviatura_medida'] . ')';
        }

        $html = '<style>
            .tit { font-size:8px; font-weight:bold; color:#333333; }
            table.d th { background-color:#e9ecef; font-size:7.5px; font-weight:bold; padding:3px 4px; border:0.4px solid #cccccc; }
            table.d td { font-size:8.5px; padding:3.5px 4px; border:0.4px solid #dddddd; }
            .obs { font-size:8.5px; padding:4px; border:0.4px solid #dddddd; }
        </style>';

        // Datos del movimiento
        $html .= $this->seccion('DATOS DEL MOVIMIENTO');
        $html .= '<table class="d" cellpadding="0"><tr>'
            . $this->th('Fecha del movimiento', '28%') . $this->th('Tipo', '18%')
            . $this->th('Origen', '27%') . $this->th('Bodega', '27%')
            . '</tr><tr>'
            . $this->td($this->fechaHora($mov['fecha_movimiento'] ?? null))
            . $this->td($esEntrada ? 'ENTRADA (+)' : 'SALIDA (-)')
            . $this->td($this->etiquetaOrigen($mov))
            . $this->td((string) ($mov['bodega_nombre'] ?? ''))
            . '</tr></table>';

        // Producto
        $html .= $this->seccion('PRODUCTO');
        $html .= '<table class="d" cellpadding="0"><tr>'
            . $this->th('Código', '18%') . $this->th('Descripción', '55%') . $this->th('Unidad de medida', '27%')
            . '</tr><tr>'
            . $this->td((string) ($mov['producto_codigo'] ?? ''))
            . $this->td((string) ($mov['producto_nombre'] ?? ''))
            . $this->td($medida !== '' ? $medida : '-')
            . '</tr></table>';

        // Cantidades y costos
        $html .= $this->seccion('CANTIDADES Y COSTOS');
        $html .= '<table class="d" cellpadding="0"><tr>'
            . $this->th('Cantidad', '20%', 'center') . $this->th('Costo unitario', '20%', 'center')
            . $this->th('Costo total', '20%', 'center') . $this->th('Stock anterior', '20%', 'center')
            . $this->th('Stock posterior', '20%', 'center')
            . '</tr><tr>'
            . $this->td($signo . number_format($cantidad, 2), 'center', true)
            . $this->td(number_format((float) ($mov['costo_unitario'] ?? 0), 6), 'center')
            . $this->td(number_format((float) ($mov['costo_total'] ?? 0), 2), 'center')
            . $this->td(number_format((float) ($mov['stock_anterior'] ?? 0), 2), 'center')
            . $this->td(number_format((float) ($mov['stock_posterior'] ?? 0), 2), 'center', true)
            . '</tr></table>';

        // Trazabilidad: solo si el movimiento la tiene (si no, cuatro guiones no aportan nada).
        $lote = trim((string) ($mov['numero_lote'] ?? ''));
        $nup  = trim((string) ($mov['nup'] ?? ''));
        if ($lote !== '' || $nup !== '' || !empty($mov['fecha_caducidad']) || !empty($mov['fecha_fabricacion'])) {
            $html .= $this->seccion('TRAZABILIDAD');
            $html .= '<table class="d" cellpadding="0"><tr>'
                . $this->th('Lote', '25%') . $this->th('Fabricación', '20%')
                . $this->th('Caducidad', '20%') . $this->th('NUP / Serial', '35%')
                . '</tr><tr>'
                . $this->td($lote !== '' ? $lote : '-')
                . $this->td($this->fecha($mov['fecha_fabricacion'] ?? null))
                . $this->td($this->fecha($mov['fecha_caducidad'] ?? null))
                . $this->td($nup !== '' ? $nup : '-')
                . '</tr></table>';
        }

        // Observaciones
        $obs = trim((string) ($mov['observaciones'] ?? ''));
        $html .= $this->seccion('OBSERVACIONES / MOTIVO');
        $html .= '<table cellpadding="0"><tr><td class="obs">'
            . ($obs !== '' ? nl2br(htmlspecialchars($obs)) : '-')
            . '</td></tr></table>';

        // Registro y auditoría
        $html .= $this->seccion('REGISTRO');
        $html .= '<table class="d" cellpadding="0"><tr>'
            . $this->th('Registrado por', '30%') . $this->th('Fecha de registro', '25%')
            . $this->th($anulado ? 'Anulado por' : '', '25%') . $this->th($anulado ? 'Fecha de anulación' : '', '20%')
            . '</tr><tr>'
            . $this->td((string) ($mov['usuario_nombre'] ?? '-'))
            . $this->td($this->fechaHora($mov['created_at'] ?? null))
            . $this->td($anulado ? (string) ($mov['anulado_por'] ?? '-') : '')
            . $this->td($anulado ? $this->fechaHora($mov['deleted_at'] ?? null) : '')
            . '</tr></table>';

        return $html;
    }

    private function seccion(string $titulo): string
    {
        return '<table cellpadding="0"><tr><td class="tit">' . htmlspecialchars($titulo) . '</td></tr></table>';
    }

    private function th(string $txt, string $width, string $align = 'left'): string
    {
        return '<th width="' . $width . '" align="' . $align . '">' . htmlspecialchars($txt) . '</th>';
    }

    private function td(string $txt, string $align = 'left', bool $bold = false): string
    {
        $contenido = htmlspecialchars($txt);
        if ($bold) {
            $contenido = '<b>' . $contenido . '</b>';
        }
        return '<td align="' . $align . '">' . $contenido . '</td>';
    }

    /** 'ajuste_manual' -> 'Ajuste Manual' (+ el id del documento origen si lo hay). */
    private function etiquetaOrigen(array $mov): string
    {
        $tipo = trim((string) ($mov['referencia_tipo'] ?? ''));
        if ($tipo === '') {
            return 'Ajuste Manual';
        }
        $label = ucwords(str_replace('_', ' ', $tipo));
        if (!empty($mov['referencia_id'])) {
            $label .= ' #' . $mov['referencia_id'];
        }
        return $label;
    }

    private function fechaHora($valor): string
    {
        $valor = trim((string) $valor);
        return $valor !== '' ? date('d-m-Y H:i:s', strtotime($valor)) : '-';
    }

    private function fecha($valor): string
    {
        $valor = trim((string) $valor);
        return $valor !== '' ? date('d-m-Y', strtotime($valor)) : '-';
    }

    // ────────────────────────────────────────────────────────────────
    // FIRMAS Y PIE
    // ────────────────────────────────────────────────────────────────
    private function dibujarFirmas(array $mov, float $y): void
    {
        $pdf  = $this->pdf;
        $mL   = $this->marginL;
        $colW = $this->contentW / 3;

        // Espacio para firmar (~2 cm) tras el contenido, sin invadir el pie. Si el
        // contenido llegó tan abajo que las firmas se montarían encima, pasan a una
        // página nueva en lugar de superponerse.
        $yLinea = $y + 20;
        if ($yLinea > 265) {
            if ($y > 255) {
                $pdf->AddPage();
                $yLinea = $pdf->GetY() + 20;
            } else {
                $yLinea = 265;
            }
        }

        $firmas = [
            ['Realizado por', trim((string) ($mov['usuario_nombre'] ?? ''))],
            ['Aprobado por', ''],
            ['Recibí conforme', ''],
        ];

        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 6, $yLinea, $x + $colW - 6, $yLinea);
        }

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) {
            $pdf->Cell($colW, 4, $f[0], 0, 0, 'C');
        }

        $pdf->SetFont('helvetica', '', 7.5);
        $yName = $yLinea + 5;
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->SetXY($x + 3, $yName);
            $pdf->MultiCell($colW - 6, 3.4, $f[1] !== '' ? $f[1] : ' ', 0, 'C', false, 0, '', '', true, 0, false, true, 0, 'T');
        }
    }

    private function dibujarPie(): void
    {
        $pdf = $this->pdf;
        // El pie va por debajo del margen de salto automático (297 - 15): sin apagarlo,
        // escribir en Y=283 abriría una segunda página en blanco.
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont('helvetica', 'I', 6.5);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY($this->marginL, 283);
        $pdf->Cell(
            $this->contentW,
            4,
            'Documento interno de control de inventario. No constituye comprobante autorizado por el SRI. Generado el ' . date('d-m-Y H:i:s'),
            0,
            0,
            'C'
        );
        $pdf->SetTextColor(0, 0, 0);
    }

    /** Resuelve la ruta en disco del logo (maneja el prefijo web /sistema/public). */
    private function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string) $ruta, '/');
            if (strpos($clean, 'sistema/public/') === 0) {
                $clean = substr($clean, strlen('sistema/public/'));
            } elseif (strpos($clean, 'sistema/') === 0) {
                $clean = substr($clean, strlen('sistema/'));
            }
            if (strpos($clean, 'public/') === 0) {
                $clean = substr($clean, strlen('public/'));
            }
            foreach ([\MVC_ROOT . '/public/' . $clean, \MVC_ROOT . '/' . $clean] as $cand) {
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
        return '';
    }
}
