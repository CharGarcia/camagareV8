<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;
use App\Rules\modulos\ProduccionOrdenRules;
use TCPDF;

/**
 * PDF de la orden de producción / registro de lote (A4 vertical), con el mismo diseño de los
 * demás documentos internos (ref. MovimientoInventarioPdfService / PedidoPdfService):
 * encabezado con logo + datos de la empresa y caja con el número, secciones con tablas,
 * espacios de firma y pie con el número de página.
 *
 * Contenido: datos de la orden, insumos teóricos y reales con los lotes consumidos, salidas con
 * su costo, costo de la orden, instrucciones de la fórmula y firmas (lo que pide una auditoría de
 * buenas prácticas de manufactura). Las cantidades de insumos y salidas van en la unidad en que se
 * escribió la fórmula (unidad_formula / factor_formula de ProduccionOrdenService::getCompleta);
 * los lotes consumidos, que son movimientos de inventario, en la unidad del producto.
 */
class ProduccionOrdenPdfService
{
    private TCPDF $pdf;
    private float $marginL = 12;
    private float $marginR = 12;
    private float $contentW = 186; // 210 - 12 - 12

    /** 'D' descarga, 'I' en línea, 'S' devuelve el contenido. */
    public function generar(array $o, int $idEmpresa, string $destino = 'D'): string
    {
        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        $empresa = $this->empresaConLogo($idEmpresa, (int) ($o['id_establecimiento'] ?? 0));
        $numero  = (string) ($o['numero'] ?? '');
        $anulada = ($o['estado'] ?? '') === 'anulada';

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor((string) ($empresa['nombre'] ?? ''));
        $this->pdf->SetTitle('Orden de producción ' . $numero);
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, (string) ($o['estado'] ?? ''), $anulada);
        $this->pdf->SetY($y + 4);
        $this->pdf->writeHTML($this->construirCuerpo($o), true, false, true, false, '');
        $this->dibujarFirmas($this->pdf->GetY());
        $this->dibujarPie();

        $archivo = 'Orden_Produccion_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $numero) . '.pdf';
        return (string) $this->pdf->Output($archivo, $destino);
    }

    // ────────────────────────────────────────────────────────────────
    // ENCABEZADO
    // ────────────────────────────────────────────────────────────────
    private function dibujarEncabezado(array $empresa, string $numero, string $estado, bool $anulada): float
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

        // Caja del documento (derecha)
        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->MultiCell($derW, 4, "ORDEN DE PRODUCCIÓN\nREGISTRO DE LOTE", 0, 'C', false, 1);
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, $numero, 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'Estado: ' . (ProduccionOrdenRules::ESTADOS[$estado] ?? ucfirst($estado)), 0, 1, 'C');

        $yFin = max($pdf->GetY(), $y0 + $boxH);

        // Sello de anulada: no debe poder confundirse con una orden vigente.
        if ($anulada) {
            $pdf->SetFillColor(248, 215, 218);
            $pdf->SetDrawColor(220, 53, 69);
            $pdf->SetTextColor(155, 22, 34);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY($mL, $yFin + 2);
            $pdf->Cell($this->contentW, 7, 'ORDEN ANULADA - SUS MOVIMIENTOS DE INVENTARIO FUERON REVERTIDOS', 1, 1, 'C', true);
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
    private function construirCuerpo(array $o): string
    {
        $lineas   = $o['lineas'] ?? [];
        $insumos  = array_values(array_filter($lineas, fn($l) => $l['rol'] === 'insumo'));
        $salidas  = array_values(array_filter($lineas, fn($l) => $l['rol'] !== 'insumo'));
        $consumos = array_values(array_filter($o['movimientos'] ?? [], fn($m) => $m['tipo'] === 'consumo'));
        $roles    = ['principal' => 'Principal', 'coproducto' => 'Coproducto', 'subproducto' => 'Subproducto', 'desperdicio' => 'Desperdicio'];
        $cerrada  = ($o['estado'] ?? '') === 'cerrada';
        $medida   = (string) ($o['medida_nombre'] ?? '');

        $html = '<style>
            .tit { font-size:8px; font-weight:bold; color:#333333; }
            table.d th { background-color:#e9ecef; font-size:7.5px; font-weight:bold; padding:3px 4px; border:0.4px solid #cccccc; }
            table.d td { font-size:8px; padding:3px 4px; border:0.4px solid #dddddd; }
            table.d td.k { background-color:#f7f9fb; font-weight:bold; }
            .obs { font-size:8.5px; padding:4px; border:0.4px solid #dddddd; }
            .eq { font-size:6.5px; color:#666666; }
        </style>';

        // Datos de la orden
        $html .= $this->seccion('DATOS DE LA ORDEN');
        $fila = fn(string $k1, string $v1, string $k2, string $v2): string => '<tr>'
            . '<td class="k" width="16%">' . $this->e($k1) . '</td><td width="46%">' . $v1 . '</td>'
            . '<td class="k" width="15%">' . $this->e($k2) . '</td><td width="23%">' . $v2 . '</td></tr>';
        $html .= '<table class="d" cellpadding="0">'
            . $fila('Producto', $this->e(trim(($o['producto_codigo'] ?? '') . ' - ' . ($o['producto_nombre'] ?? ''), ' -')),
                'Fórmula', $this->e(($o['formula_codigo'] ?? '') . ' v' . (int) ($o['version_formula'] ?? 0)))
            . $fila('Lote', $this->e((string) ($o['lote'] ?? '')), 'Caducidad', $this->fecha($o['fecha_caducidad'] ?? null))
            . $fila('Planificado', $this->e($this->c($o['cantidad_planificada'] ?? 0) . ' ' . $medida),
                'Producido', $cerrada ? $this->e($this->c($o['cantidad_producida'] ?? 0) . ' ' . $medida) : '-')
            . $fila('Fecha', $this->fecha($o['fecha_inicio'] ?? null), 'Cierre', $this->fecha($o['fecha_fin'] ?? null))
            . $fila('Bodegas', $this->e(($o['bodega_insumos_nombre'] ?? '') . ' → ' . ($o['bodega_destino_nombre'] ?? '')),
                'Responsable', $this->e((string) ($o['responsable'] ?? '')) ?: '-')
            . '</table>';

        // Insumos (en la unidad de la fórmula)
        $html .= $this->seccion('INSUMOS');
        $html .= '<table class="d" cellpadding="0"><tr>'
            . $this->th('Insumo', '42%') . $this->th('Unidad', '12%') . $this->th('Teórico', '12%', 'right')
            . $this->th('Consumido', '12%', 'right') . $this->th('Diferencia', '10%', 'right') . $this->th('Costo', '12%', 'right')
            . '</tr>';
        if (!$insumos) {
            $html .= '<tr><td colspan="6" align="center">-</td></tr>';
        }
        foreach ($insumos as $l) {
            $teo = (float) $l['cantidad_teorica'];
            $real = (float) $l['cantidad_real'];
            $html .= '<tr>'
                . '<td width="42%">' . $this->e(trim($l['producto_codigo'] . ' - ' . $l['producto_nombre'], ' -'))
                    . (!empty($l['fuera_formula']) && $l['fuera_formula'] !== 'f' ? ' <i>(fuera de fórmula)</i>' : '') . '</td>'
                . '<td width="12%">' . $this->e($this->unidad($l)) . '</td>'
                . '<td width="12%" align="right">' . $this->cantFormula($teo, $l, true) . '</td>'
                . '<td width="12%" align="right">' . $this->cantFormula($real, $l, true) . '</td>'
                . '<td width="10%" align="right">' . $this->cantFormula($real - $teo, $l, false) . '</td>'
                . '<td width="12%" align="right">' . $this->m($l['costo_real'] ?? 0) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        // Lotes de insumo consumidos (movimientos de inventario: unidad del producto)
        if ($consumos) {
            $html .= $this->seccion('LOTES DE INSUMO CONSUMIDOS');
            $html .= '<table class="d" cellpadding="0"><tr>'
                . $this->th('Fecha', '11%') . $this->th('Insumo', '37%') . $this->th('Lote', '16%')
                . $this->th('Caducidad', '11%') . $this->th('Cantidad', '12%', 'right') . $this->th('Registró', '13%')
                . '</tr>';
            foreach ($consumos as $mv) {
                $peso = $mv['peso_inicial'] !== null
                    ? '<br><span class="eq">' . $this->c($mv['peso_inicial']) . ' − ' . $this->c($mv['peso_final']) . '</span>' : '';
                $html .= '<tr>'
                    . '<td width="11%">' . $this->fecha($mv['fecha'] ?? null) . '</td>'
                    . '<td width="37%">' . $this->e((string) ($mv['producto_nombre'] ?? '')) . '</td>'
                    . '<td width="16%">' . $this->e((string) ($mv['numero_lote'] ?: 'Sin lote')) . '</td>'
                    . '<td width="11%">' . $this->fecha($mv['fecha_caducidad'] ?? null) . '</td>'
                    . '<td width="12%" align="right">' . $this->c($mv['cantidad']) . $peso . '</td>'
                    . '<td width="13%">' . $this->e((string) ($mv['usuario_nombre'] ?? '')) . '</td>'
                    . '</tr>';
            }
            $html .= '</table>';
        }

        // Producto obtenido (en la unidad de la fórmula)
        $html .= $this->seccion('PRODUCTO OBTENIDO');
        $html .= '<table class="d" cellpadding="0"><tr>'
            . $this->th('Tipo', '13%') . $this->th('Producto', '35%') . $this->th('Unidad', '10%')
            . $this->th('Teórico', '11%', 'right') . $this->th('Real', '11%', 'right')
            . $this->th('Costo unit.', '10%', 'right') . $this->th('Costo', '10%', 'right')
            . '</tr>';
        foreach ($salidas as $l) {
            $nombre = $l['id_producto'] ? trim($l['producto_codigo'] . ' - ' . $l['producto_nombre'], ' -') : (string) ($l['descripcion'] ?? '');
            $html .= '<tr>'
                . '<td width="13%">' . $this->e($roles[$l['rol']] ?? $l['rol']) . '</td>'
                . '<td width="35%">' . $this->e($nombre) . '</td>'
                . '<td width="10%">' . $this->e($this->unidad($l)) . '</td>'
                . '<td width="11%" align="right">' . $this->cantFormula((float) $l['cantidad_teorica'], $l, true) . '</td>'
                . '<td width="11%" align="right">' . ($cerrada ? $this->cantFormula((float) $l['cantidad_real'], $l, true) : '') . '</td>'
                . '<td width="10%" align="right">' . ($cerrada ? number_format((float) $l['costo_unitario'], 4) : '') . '</td>'
                . '<td width="10%" align="right">' . ($cerrada ? $this->m($l['costo_real'] ?? 0) : '') . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        // Costo de la orden
        if ($cerrada) {
            $html .= $this->seccion('COSTO DE LA ORDEN');
            $c = fn(string $k, string $v, bool $neg = false, bool $total = false): string =>
                '<tr><td width="40%"' . ($total ? ' class="k"' : '') . '>' . $this->e($k) . '</td>'
                . '<td width="20%" align="right"' . ($total ? ' class="k"' : '') . '>' . ($neg ? '-' : '') . $v . '</td></tr>';
            $html .= '<table class="d" cellpadding="0">'
                . $c('Materiales consumidos', $this->m($o['costo_materiales'] ?? 0))
                . $c('Mano de obra aplicada', $this->m($o['costo_mano_obra'] ?? 0))
                . $c('Costos indirectos aplicados', $this->m($o['costo_cif'] ?? 0))
                . ((float) ($o['costo_otros'] ?? 0) > 0 ? $c('Otros costos', $this->m($o['costo_otros'])) : '')
                . ((float) ($o['costo_merma'] ?? 0) > 0
                    ? $c('Merma anormal (a gasto) — ' . $this->c($o['cantidad_merma'] ?? 0) . ' ' . $medida, $this->m($o['costo_merma']), true) : '')
                . $c('Costo que entra al inventario', $this->m($o['costo_total'] ?? 0), false, true)
                . '</table>';
        }

        // Instrucciones y observaciones
        $instr = trim((string) ($o['formula_instrucciones'] ?? ''));
        if ($instr !== '') {
            $html .= $this->seccion('INSTRUCCIONES DE FABRICACIÓN');
            $html .= '<table cellpadding="0"><tr><td class="obs">' . nl2br($this->e($instr)) . '</td></tr></table>';
        }
        $obs = trim((string) ($o['observaciones'] ?? ''));
        if ($obs !== '') {
            $html .= $this->seccion('OBSERVACIONES');
            $html .= '<table cellpadding="0"><tr><td class="obs">' . nl2br($this->e($obs)) . '</td></tr></table>';
        }

        return $html;
    }

    /** Unidad que se muestra: la de la fórmula si la línea la tiene; si no, la del producto. */
    private function unidad(array $l): string
    {
        return (string) (($l['unidad_formula'] ?? '') ?: ($l['medida_nombre'] ?? ''));
    }

    /**
     * Cantidad (en la unidad del producto) mostrada en la unidad de la fórmula. Con $equivalencia,
     * si las unidades difieren, debajo va lo que se mueve en el inventario («= 1,275 Kilogramo»).
     */
    private function cantFormula(float $cantidadProducto, array $l, bool $equivalencia): string
    {
        $f = (float) ($l['factor_formula'] ?? 1) ?: 1.0;
        $txt = $this->c($cantidadProducto / $f);
        if ($equivalencia && abs($f - 1.0) > 1e-12 && abs($cantidadProducto) > 1e-12) {
            $txt .= '<br><span class="eq">= ' . $this->c($cantidadProducto) . ' ' . $this->e((string) ($l['medida_nombre'] ?? '')) . '</span>';
        }
        return $txt;
    }

    private function seccion(string $titulo): string
    {
        return '<table cellpadding="0"><tr><td class="tit">' . $this->e($titulo) . '</td></tr></table>';
    }

    private function th(string $txt, string $width, string $align = 'left'): string
    {
        return '<th width="' . $width . '" align="' . $align . '">' . $this->e($txt) . '</th>';
    }

    private function e($v): string
    {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    }

    private function c($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.');
    }

    private function m($v): string
    {
        return number_format((float) $v, 2, '.', ',');
    }

    private function fecha($v): string
    {
        $v = trim((string) $v);
        return $v !== '' ? date('d-m-Y', strtotime($v)) : '-';
    }

    // ────────────────────────────────────────────────────────────────
    // FIRMAS Y PIE
    // ────────────────────────────────────────────────────────────────
    private function dibujarFirmas(float $y): void
    {
        $pdf  = $this->pdf;
        $mL   = $this->marginL;
        $colW = $this->contentW / 3;

        // ~2 cm para firmar tras el contenido; si no caben antes del pie, van a una página nueva.
        $yLinea = $y + 20;
        if ($yLinea > 265) {
            if ($y > 255) {
                $pdf->AddPage();
                $yLinea = $pdf->GetY() + 20;
            } else {
                $yLinea = 265;
            }
        }

        $firmas = ['Elaboró / Pesó', 'Verificó', 'Aprobó (Calidad)'];
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 6, $yLinea, $x + $colW - 6, $yLinea);
        }
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) {
            $pdf->Cell($colW, 4, $f, 0, 0, 'C');
        }
    }

    /** Pie en todas las páginas: aviso de documento interno + número de página. */
    private function dibujarPie(): void
    {
        $pdf = $this->pdf;
        // El pie va por debajo del margen de salto automático: sin apagarlo, abriría otra página.
        $pdf->SetAutoPageBreak(false);
        $total = $pdf->getNumPages();
        for ($p = 1; $p <= $total; $p++) {
            $pdf->setPage($p);
            $pdf->SetFont('helvetica', 'I', 6.5);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($this->marginL, 283);
            $pdf->Cell($this->contentW * 0.8, 4,
                'Documento interno de producción. No constituye comprobante autorizado por el SRI. Generado el ' . date('d-m-Y H:i:s'), 0, 0, 'L');
            $pdf->Cell($this->contentW * 0.2, 4, 'Página ' . $p . ' de ' . $total, 0, 0, 'R');
        }
        $pdf->SetTextColor(0, 0, 0);
    }

    // ────────────────────────────────────────────────────────────────
    // EMPRESA Y LOGO
    // ────────────────────────────────────────────────────────────────
    /** Datos de la empresa con el logo del establecimiento de la orden (o el primero que tenga). */
    private function empresaConLogo(int $idEmpresa, int $idEstablecimiento): array
    {
        $model   = new Empresa();
        $empresa = $model->getPorId($idEmpresa) ?? [];
        $logo    = '';
        foreach ($model->getEstablecimientos($idEmpresa) as $est) {
            if (empty($est['logo_ruta'])) {
                continue;
            }
            if ($idEstablecimiento > 0 && (int) ($est['id'] ?? 0) === $idEstablecimiento) {
                $logo = (string) $est['logo_ruta'];
                break;
            }
            if ($logo === '') {
                $logo = (string) $est['logo_ruta'];
            }
        }
        if ($logo !== '') {
            $empresa['logo_ruta'] = $logo;
        }
        return $empresa;
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
