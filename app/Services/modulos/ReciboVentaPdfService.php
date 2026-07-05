<?php
declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

class ReciboVentaPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 10;
    private float $marginR  = 10;
    private float $pageW    = 210;
    private float $contentW = 190;

    /** Decimales configurados por la empresa (cantidad y precio unitario). */
    private int $decCantidad = 2;
    private int $decPrecio   = 2;

    public function generar(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa, string $outputDest = 'D')
    {
        $this->renderizar($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        $num = $this->numeroRecibo($cabecera);
        if ($outputDest === 'S') {
            return $this->pdf->Output('Recibo_' . $num . '.pdf', 'S');
        }
        $this->pdf->Output('Recibo_' . $num . '.pdf', $outputDest);
    }

    /** Genera el PDF y lo devuelve como string (para guardado en disco). */
    public function generarBytes(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): string
    {
        $this->renderizar($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        return $this->pdf->Output('', 'S');
    }

    private function renderizar(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): void
    {
        // Decimales configurados por la empresa (igual que en el sistema/UI).
        $this->decCantidad = max(0, (int)($empresa['decimales_cantidad'] ?? 2));
        $this->decPrecio   = max(0, (int)($empresa['decimales_precio']   ?? 2));

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Recibo de Venta ' . $this->numeroRecibo($cabecera));
        $this->pdf->SetMargins($this->marginL, 5, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);

        $y = $this->dibujarEncabezado($empresa, $cabecera);
        $y = $this->dibujarDatosCliente($cabecera, $y + 2);
        $y = $this->dibujarDetalle($detalles, $y + 2);
        $this->dibujarPie($cabecera, $detalles, $pagos, $infoAdicional, $empresa, $y + 2);
    }

    // ─── ENCABEZADO ──────────────────────────────────────────────────────────
    private function dibujarEncabezado(array $empresa, array $cabecera): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        // Dimensiones de las dos cajas
        $izqW = 85;
        $derW = $this->contentW - $izqW - 2; // 103mm
        $derX = $mL + $izqW + 2;

        $yTop = 8;
        $yLogo = $yTop;

        $boxHeight = 73.5;
        // Asignamos 40% para el logo y 60% para la caja para evitar que el texto quede apretado
        $logoAreaHeight = $boxHeight * 0.40;

        // Logo
        $logoPath = '';
        $rutasPosibles = [];
        if (!empty($empresa['logo_ruta'])) {
            $rutasPosibles[] = $empresa['logo_ruta'];
        }
        if (!empty($empresa['logo'])) {
            $rutasPosibles[] = $empresa['logo'];
        }

        foreach ($rutasPosibles as $ruta) {
            $cleanRuta = ltrim($ruta, '/');
            // Eliminar prefijos que duplicarían la ruta del servidor
            if (strpos($cleanRuta, 'sistema/public/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('sistema/public/'));
            } elseif (strpos($cleanRuta, 'sistema/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('sistema/'));
            }
            if (strpos($cleanRuta, 'public/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('public/'));
            }

            // Intentar primero en public/ (ubicación real en producción)
            $candidatos = [
                \MVC_ROOT . '/public/' . $cleanRuta,
                \MVC_ROOT . '/' . $cleanRuta,
            ];
            foreach ($candidatos as $testPath) {
                if (file_exists($testPath)) {
                    $logoPath = $testPath;
                    break 2;
                }
            }
        }

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);

        if ($logoPath) {
            // Se usa el ancho casi total de la zona izquierda y fitbox 'CM' para centrado vertical y horizontal
            $pdf->Image($logoPath, $mL + 2, $yLogo + 2, $izqW - 4, $logoAreaHeight - 4, '', '', '', false, 300, '', false, false, 0, 'CM');
        } else {
            // Placeholder "SIN LOGO"
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetTextColor(160, 160, 160);
            $pdf->SetXY($mL + 2, $yLogo + ($logoAreaHeight/2) - 5);
            $pdf->Cell($izqW - 4, 15, 'SIN LOGO', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $yTopIzqBox = $yLogo + $logoAreaHeight;

        // ── Caja izquierda (contenido) ───────────────────────────────────────
        $yIzq = $yTopIzqBox + 3;

        // Nombre comercial
        $nomComercial = trim($empresa['nombre_comercial'] ?? '');
        $nomRazon     = trim($empresa['nombre'] ?? '');
        if ($nomComercial) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->MultiCell($izqW - 4, 5, $nomComercial, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }
        if ($nomRazon && $nomRazon !== $nomComercial) {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->MultiCell($izqW - 4, 4.5, $nomRazon, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Dirección Matriz
        $dirMat = trim($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? '');
        if ($dirMat) {
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(22, 4, 'Dirección Matriz:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 26, 4, $dirMat, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Dirección Sucursal
        $dirSuc = trim($empresa['direccion_establecimiento'] ?? $empresa['direccion_sucursal'] ?? '');
        if (empty($dirSuc)) {
            $dirSuc = trim($cabecera['direccion_establecimiento'] ?? '');
        }
        if (empty($dirSuc)) {
            $dirSuc = trim($empresa['direccion'] ?? '');
        }

        if ($dirSuc) {
            $yBefore = $pdf->GetY();
            $pdf->SetXY($mL + 2, $yBefore);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->MultiCell(20, 3.5, "Dirección\nSucursal:", 0, 'L', false, 1);

            $yAfterLabel = $pdf->GetY();

            $pdf->SetXY($mL + 22, $yBefore);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 24, 3.5, $dirSuc, 0, 'L', false, 1);

            $yAfterValue = $pdf->GetY();
            $yIzq = max($yAfterLabel, $yAfterValue);
        }

        // Contribuyente Especial
        $resCont = trim($empresa['resolucion_contribuyente'] ?? '');
        if ($resCont) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(30, 4.5, 'Contribuyente Especial', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($izqW - 32, 4.5, $resCont, 0, 1, 'L');
            $yIzq = $pdf->GetY();
        }

        // Obligado a llevar contabilidad
        $oblStr  = strtoupper(trim((string)($empresa['obligado_contabilidad'] ?? 'NO')));
        $oblabel = ($oblStr === 'SI' || $oblStr === '1' || $oblStr === 'TRUE') ? 'SI' : 'NO';
        $pdf->SetXY($mL + 2, $yIzq);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(55, 4.5, 'OBLIGADO A LLEVAR CONTABILIDAD', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($izqW - 57, 4.5, $oblabel, 0, 1, 'L');
        $yIzq = $pdf->GetY() + 1;

        // Agente de Retención
        $agenteRet = trim((string)($empresa['agente_retencion'] ?? ''));
        if ($agenteRet !== '' && $agenteRet !== '0' && strtoupper($agenteRet) !== 'NO' && strtoupper($agenteRet) !== 'N/A') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(55, 4.5, 'Agente de Retención Resolución No.', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($izqW - 57, 4.5, $agenteRet, 0, 1, 'L');
            $yIzq = $pdf->GetY() + 1;
        }

        // Régimen RIMPE (solo emprendedor / negocio popular; el general no se muestra)
        $rimpe = \App\Helpers\SriEmisorHelper::regimenRimpeLeyenda($empresa);
        if ($rimpe) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->MultiCell($izqW - 4, 4.5, $rimpe, 0, 'L', false, 1);
            $yIzq = $pdf->GetY() + 1;
        }

        $yIzq += 2;

        // ── Caja derecha ──────────────────────────────────────────────────────
        $yDer = $yTop;

        // Fila: R.U.C. + valor
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer + 2);
        $pdf->Cell(14, 5, 'R.U.C.:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($derW - 16, 5, $empresa['ruc'] ?? '', 0, 1, 'L');
        $yDer += 8;

        // RECIBO DE VENTA (grande)
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 7, 'RECIBO DE VENTA', 0, 1, 'L');
        $yDer += 7;

        // Número del recibo
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell(7, 5, 'No.', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW - 9, 5, $this->numeroRecibo($cabecera), 0, 1, 'L');
        $yDer += 6;

        // Etiqueta CON / SIN IMPUESTOS (discreta, bajo el número)
        $conImp = $this->esConImpuestos($cabecera);
        $impLabel = $conImp ? 'CON IMPUESTOS' : 'SIN IMPUESTOS';
        $pdf->SetFont('helvetica', 'B', 7.5);
        if ($conImp) {
            $pdf->SetTextColor(0, 90, 0);
        } else {
            $pdf->SetTextColor(120, 120, 120);
        }
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, $impLabel, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $yDer += 6;

        $yDer += 2;

        // ── Dibujar Bordes (Alineados al fondo) ───────────────────────────────
        $yBottom = max($yIzq, $yDer);

        // Borde izquierdo (empieza en yTopIzqBox, debajo del logo)
        $pdf->RoundedRect($mL, $yTopIzqBox, $izqW, $yBottom - $yTopIzqBox, 3, '1111', 'D');
        // Borde derecho
        $pdf->RoundedRect($derX, $yTop, $derW, $yBottom - $yTop, 3, '1111', 'D');

        return $yBottom;
    }

    // ─── DATOS DEL CLIENTE ────────────────────────────────────────────────────
    // Caja con borde, 3 filas

    private function dibujarDatosCliente(array $cab, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $pdf->SetLineWidth(0.3);
        $lh = 5;

        $fecha = '';
        if (!empty($cab['fecha_emision'])) {
            $ts = strtotime($cab['fecha_emision']);
            $fecha = $ts ? date('d/m/Y', $ts) : $cab['fecha_emision'];
        }

        $yBox = $y;

        // Fila 1: Razón Social / Nombres y Apellidos
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1.5);
        $pdf->Cell(48, $lh, 'Razón Social / Nombres y Apellidos:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 50, $lh, $cab['cliente_nombre'] ?? '', 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 2: Identificación | Fecha | Placa/Matrícula | Guía
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(20, $lh, 'Identificación:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(30, $lh, $cab['cliente_ruc'] ?? '', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, $lh, 'Fecha emisión:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(22, $lh, $fecha, 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(25, $lh, 'Placa / Matrícula:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(20, $lh, $cab['placa'] ?? '', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, $lh, 'Guía remisión:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(28, $lh, $cab['guia_remision'] ?? '', 0, 1, 'L');

        $yBox += $lh + 1;

        // Fila 3: Dirección
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(15, $lh, 'Direccion:', 0, 0, 'L');
        $pdf->Cell($cW - 17, $lh, $cab['cliente_direccion'] ?? '', 0, 1, 'L');
        $yBox += $lh + 2;

        // Borde de la caja
        $pdf->Rect($mL, $y, $cW, $yBox - $y, 'D');

        return $yBox;
    }

    // ─── DETALLE ─────────────────────────────────────────────────────────────
    // 10 columnas: Cod.Principal | Cod.Auxiliar | Cantidad | Descripción |
    // Detalle Adicional | Precio Unitario | Subsidio | Precio sin Subsidio | Descuento | Precio Total

    private function dibujarDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $cols = [
            ['key' => 'codp', 'titulo' => "Cod.\nPrincipal",    'w' => 16, 'align' => 'L'],
            ['key' => 'coda', 'titulo' => "Cod.\nAuxiliar",     'w' => 14, 'align' => 'L'],
            ['key' => 'cant', 'titulo' => "Cantidad",           'w' => 14, 'align' => 'R'],
            ['key' => 'desc', 'titulo' => "Descripción",        'w' => 36, 'align' => 'L'],
            ['key' => 'deta', 'titulo' => "Detalle\nAdicional", 'w' => 24, 'align' => 'L'],
            ['key' => 'pu',   'titulo' => "Precio\nUnitario",   'w' => 20, 'align' => 'R'],
            ['key' => 'sub',  'titulo' => "Subsidio",           'w' => 16, 'align' => 'R'],
            ['key' => 'pss',  'titulo' => "Precio sin\nSubsidio",'w' => 20, 'align' => 'R'],
            ['key' => 'dcto', 'titulo' => "Descuento",          'w' => 16, 'align' => 'R'],
            ['key' => 'ptot', 'titulo' => "Precio\nTotal",      'w' => 14, 'align' => 'R'],
        ];

        // Ajustar Descripción para que la suma sea exactamente contentW
        $sumaW = array_sum(array_column($cols, 'w'));
        if ($sumaW !== (int)$cW) {
            foreach ($cols as &$c) {
                if ($c['key'] === 'desc') {
                    $c['w'] += ((int)$cW - $sumaW);
                    break;
                }
            }
            unset($c);
        }

        // Encabezado (2 líneas)
        $pdf->SetFont('helvetica', 'B', 6.5);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetXY($mL, $y);
        foreach ($cols as $col) {
            // Se usa 7.6 de alto total y alineación vertical 'M' (Middle)
            $pdf->MultiCell($col['w'], 7.6, $col['titulo'], 1, 'C', true, 0, '', '', true, 0, false, true, 7.6, 'M');
        }
        $pdf->Ln();

        // Calcular la altura del encabezado (2 líneas * 3.8 = 7.6)
        $hdrH = 7.6;
        $y += $hdrH;

        // Filas de detalle
        $pdf->SetFont('helvetica', '', 7);
        $altColor = false;

        foreach ($detalles as $d) {
            $bg = $altColor ? [250, 250, 250] : [255, 255, 255];
            $altColor = !$altColor;
            $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);

            $pu      = (float)($d['precio_unitario'] ?? 0);
            $subsidio = (float)($d['subsidio'] ?? 0);
            $pss     = $pu + $subsidio;
            $dcto    = (float)($d['descuento'] ?? 0);
            $ptot    = (float)($d['precio_total_sin_impuesto'] ?? 0);

            $vals = [
                'codp' => $d['codigo_principal'] ?? '',
                'coda' => $d['codigo_auxiliar']  ?? '',
                'cant' => number_format((float)($d['cantidad'] ?? 0), $this->decCantidad),
                'desc' => $d['descripcion'] ?? '',
                'deta' => $d['info_adicional'] ?? ($d['detalle_adicional'] ?? ''),
                'pu'   => number_format($pu, $this->decPrecio),
                'sub'  => number_format($subsidio, $this->decPrecio),
                'pss'  => number_format($pss, $this->decPrecio),
                'dcto' => number_format($dcto, 2),
                'ptot' => number_format($ptot, 2),
            ];

            // Calcular altura de fila según columnas multilinea
            $nDesc = max(1, (int)ceil($pdf->GetStringWidth($vals['desc']) / ($cols[3]['w'] - 2)));
            $nDeta = max(1, (int)ceil($pdf->GetStringWidth($vals['deta']) / ($cols[4]['w'] - 2)));
            $ch    = max(5, max($nDesc, $nDeta) * 4.5);

            $xCur = $mL;
            $yRow = $pdf->GetY();

            foreach ($cols as $col) {
                $val = $vals[$col['key']];
                $pdf->SetXY($xCur, $yRow);
                if ($col['key'] === 'desc' || $col['key'] === 'deta') {
                    // Alineación horizontal izquierda + vertical centrada (valign 'M')
                    $pdf->MultiCell($col['w'], $ch, $val, 1, $col['align'], true, 0, '', '', true, 0, false, true, 0, 'M');
                } else {
                    $pdf->Cell($col['w'], $ch, $val, 1, 0, $col['align'], true);
                }
                $xCur += $col['w'];
            }
            $pdf->SetXY($mL, $yRow + $ch);
        }

        return $pdf->GetY();
    }

    // ─── PIE ─────────────────────────────────────────────────────────────────
    // Izquierda: Información Adicional + Observaciones + Forma de pago
    // Derecha:   tabla de totales
    private function dibujarPie(array $cab, array $detalles, array $pagos, array $infoAdicional, array $empresa, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        // ── Calcular totales por concepto de IVA (codigo_porcentaje) ─────────
        // Se agrupan y suman las bases imponibles y el IVA por codigo_porcentaje
        // a partir de los impuestos que vienen en cada detalle. Para recibos
        // "sin impuestos" los valores llegan en 0 y se imprimen tal cual.
        $subtotMap  = []; // codigo_porcentaje => base imponible (SUBTOTAL X%)
        $ivaMap     = []; // codigo_porcentaje => valor IVA
        $tarifaMap  = []; // codigo_porcentaje => tarifa numérica (para etiquetas)
        $totalIce   = 0.0;
        $totalDcto  = 0.0;
        $noObjIva   = 0.0;
        $exentoIva  = 0.0;
        $totalSubsidio = 0.0;

        foreach ($detalles as $d) {
            $totalDcto += (float)($d['descuento'] ?? 0);
            $totalSubsidio += (float)($d['subsidio'] ?? 0) * (float)($d['cantidad'] ?? 0);
            $tieneImp   = false;
            foreach ($d['impuestos'] ?? [] as $imp) {
                $cod     = (string)($imp['codigo_impuesto'] ?? '');
                $tar     = (float)($imp['tarifa'] ?? 0);
                $val     = (float)($imp['valor'] ?? 0);
                $base    = (float)($imp['base_imponible'] ?? $d['precio_total_sin_impuesto'] ?? 0);

                if ($cod === '2') { // IVA
                    // codigo_porcentaje canónico: para tarifa > 0 se deriva de la
                    // tarifa real; para tarifa 0 se respeta el guardado, que
                    // distingue 0% (0), no objeto (6) y exento (7).
                    $codPct = $tar > 0
                        ? \App\Helpers\SriIvaHelper::codigoPorcentaje($tar)
                        : (string)($imp['codigo_porcentaje'] ?? '0');

                    if ($codPct === '6') {            // No objeto de IVA
                        $noObjIva += $base;
                    } elseif ($codPct === '7') {      // Exento de IVA
                        $exentoIva += $base;
                    } else {                          // 0%, 5%, 12%, 15%, ...
                        $subtotMap[$codPct] = ($subtotMap[$codPct] ?? 0.0) + $base;
                        $ivaMap[$codPct]    = ($ivaMap[$codPct]    ?? 0.0) + $val;
                        $tarifaMap[$codPct] = $tar;
                    }
                    $tieneImp = true;
                } elseif ($cod === '3') { // ICE
                    $totalIce += $val;
                }
            }
            if (!$tieneImp) {
                $subtotMap['0']  = ($subtotMap['0'] ?? 0.0) + (float)($d['precio_total_sin_impuesto'] ?? 0);
                $ivaMap['0']     = ($ivaMap['0']    ?? 0.0);
                $tarifaMap['0']  = 0.0;
            }
        }
        ksort($subtotMap);
        ksort($ivaMap);

        $totalIva       = array_sum($ivaMap);
        $propina        = (float)($cab['propina'] ?? 0);

        // Totales de cabecera: el recibo los toma de la cabecera (NO los recalcula)
        // para mostrar exactamente los mismos valores. Si algún flujo no los trae,
        // se cae al cálculo desde los detalles como respaldo.
        $subtotalSinImp = isset($cab['total_sin_impuestos'])
            ? (float)$cab['total_sin_impuestos']
            : array_sum($subtotMap) + $noObjIva + $exentoIva;
        if (isset($cab['total_descuento'])) {
            $totalDcto = (float)$cab['total_descuento'];
        }
        $total          = isset($cab['importe_total'])
            ? (float)$cab['importe_total']
            : $subtotalSinImp + $totalIva + $totalIce + $propina;

        if ($y > 212) {
            $pdf->AddPage();
            $y = 12;
        }

        // Layout
        $totW = 72;
        $izqW = $cW - $totW - 2;
        $totX = $mL + $izqW + 2;
        $lh   = 5;

        // ── Columna derecha: tabla de totales ─────────────────────────────────
        $yTot = $y;
        $pdf->SetLineWidth(0.3);

        $lblW = 54; // ancho etiqueta
        $valW = $totW - $lblW;

        // Etiqueta CON / SIN IMPUESTOS (discreta, sobre el bloque de totales)
        $conImp = $this->esConImpuestos($cab);
        $pdf->SetFont('helvetica', 'B', 7);
        if ($conImp) {
            $pdf->SetFillColor(225, 240, 225);
            $pdf->SetTextColor(0, 90, 0);
            $impLabel = 'CON IMPUESTOS';
        } else {
            $pdf->SetFillColor(235, 235, 235);
            $pdf->SetTextColor(110, 110, 110);
            $impLabel = 'SIN IMPUESTOS';
        }
        $pdf->SetXY($totX, $yTot);
        $pdf->Cell($totW, $lh, $impLabel, 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $yTot += $lh;

        // Subtotales por concepto de IVA (codigo_porcentaje)
        foreach ($subtotMap as $codPct => $base) {
            $tarPct   = $tarifaMap[$codPct] ?? 0.0;
            $tarLabel = $tarPct == (int)$tarPct ? (string)(int)$tarPct : number_format($tarPct, 2);
            $lbl = "SUBTOTAL {$tarLabel}%";
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, $lbl, $base);
            $yTot += $lh;
        }
        // Subtotal no objeto / exento
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL NO OBJETO DE IVA', $noObjIva);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL EXENTO DE IVA', $exentoIva);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL SIN IMPUESTOS', $subtotalSinImp);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'TOTAL DESCUENTO', $totalDcto);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'ICE', $totalIce);
        $yTot += $lh;

        // IVA por concepto (codigo_porcentaje)
        foreach ($ivaMap as $codPct => $ivaVal) {
            $tarPct   = $tarifaMap[$codPct] ?? 0.0;
            $tarLabel = $tarPct == (int)$tarPct ? (string)(int)$tarPct : number_format($tarPct, 2);
            $lbl = "IVA {$tarLabel}%";
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, $lbl, $ivaVal);
            $yTot += $lh;
        }

        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'PROPINA', $propina);
        $yTot += $lh;

        // VALOR TOTAL (negrita, fondo)
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(210, 210, 210);
        $pdf->SetXY($totX, $yTot);
        $pdf->Cell($lblW, $lh, 'VALOR TOTAL', 1, 0, 'L', true);
        $pdf->Cell($valW, $lh, number_format($total, 2), 1, 1, 'R', true);
        $yTot += $lh;

        if ($totalSubsidio > 0) {
            $valorTotalSinSubsidio = $total + $totalSubsidio;
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'VALOR TOTAL SIN SUBSIDIO', $valorTotalSinSubsidio);
            $yTot += $lh;

            // AHORRO POR SUBSIDIO (2 líneas)
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($totX, $yTot);
            $pdf->MultiCell($lblW, $lh, "AHORRO POR SUBSIDIO:\n(Incluye IVA cuando corresponda)", 1, 'L', false, 0);

            $pdf->SetXY($totX + $lblW, $yTot);
            $pdf->Cell($valW, $lh * 2, number_format($totalSubsidio, 2), 1, 1, 'R');
            $yTot += $lh * 2;
        }

        // ── Columna izquierda: Información Adicional + Forma de pago ──────────
        $yIzq = $y;

        // Monto en letras (si viene)
        $montoLetras = trim((string)($cab['monto_letras'] ?? $cab['valor_letras'] ?? ''));
        if ($montoLetras !== '') {
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'SON:', 1, 1, 'L', true);
            $yIzq += $lh;
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($mL, $yIzq);
            $pdf->MultiCell($izqW, 4.5, $montoLetras, 1, 'L', false, 1);
            $yIzq = $pdf->GetY() + 1;
        }

        // Información Adicional
        if (!empty($infoAdicional)) {
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'Información Adicional', 1, 1, 'C', true);
            $yIzq += $lh;

            $etiqW = 40;
            $valIW = $izqW - $etiqW;
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);
            foreach ($infoAdicional as $info) {
                $pdf->SetXY($mL, $yIzq);
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->Cell($etiqW, $lh, $info['nombre'] ?? '', 1, 0, 'L');
                $pdf->SetFont('helvetica', '', 7);
                $pdf->MultiCell($valIW, $lh, $info['valor'] ?? '', 1, 'L', false, 1);
                $yIzq = $pdf->GetY();
            }
        }

        // Observaciones
        if (!empty($cab['observaciones'])) {
            $yIzq += 1;
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'Observaciones', 1, 1, 'C', true);
            $yIzq += $lh;
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($mL, $yIzq);
            $pdf->MultiCell($izqW, 4.5, $cab['observaciones'], 1, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Forma de pago
        if (!empty($pagos)) {
            $yIzq += 1;
            // Encabezado tabla pagos
            $wNombre = $izqW - 28 - 22 - 22;
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($wNombre, $lh, 'Forma de pago', 1, 0, 'C', true);
            $pdf->Cell(28,       $lh, 'Valor',         1, 0, 'C', true);
            $pdf->Cell(22,       $lh, 'Días Crédito',  1, 0, 'C', true);
            $pdf->Cell(22,       $lh, 'Plazo',         1, 1, 'C', true);
            $yIzq += $lh;

            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);
            foreach ($pagos as $p) {
                $nombreP = $p['nombre_forma_pago'] ?? ($p['forma_pago'] ?? '');
                $valorP  = number_format((float)($p['total'] ?? 0), 2);
                $dias    = (int)($p['plazo'] ?? 0);
                $unidad  = trim($p['unidad_tiempo'] ?? 'dias');
                $plazoLbl = $dias > 0 ? $dias . ' ' . $unidad : '—';
                $diasLbl  = $dias > 0 ? (string)$dias : '0';

                // Calcular cuántas líneas ocupa el nombre de la forma de pago
                $numLines = $pdf->getNumLines($nombreP, $wNombre);
                // Si getNumLines retorna 0 o algo menor a 1, usar 1
                if ($numLines < 1) $numLines = 1;

                $rowH = $numLines * $lh;

                $pdf->SetXY($mL, $yIzq);
                // MultiCell recibe un height mínimo ($rowH) y dibujará el borde hasta allí
                $pdf->MultiCell($wNombre, $rowH, $nombreP, 1, 'L', false, 0);

                // Las celdas adyacentes usarán el mismo alto total ($rowH)
                $pdf->Cell(28, $rowH, $valorP,  1, 0, 'R');
                $pdf->Cell(22, $rowH, $diasLbl, 1, 0, 'C');
                $pdf->Cell(22, $rowH, $plazoLbl, 1, 1, 'C');

                $yIzq += $rowH;
            }
        }

        // ── Firmas (si vienen etiquetas) ─────────────────────────────────────
        $yFirmas = max($yIzq, $yTot) + 10;
        $firmas = [
            trim((string)($cab['firma_1'] ?? $empresa['firma_1'] ?? '')),
            trim((string)($cab['firma_2'] ?? $empresa['firma_2'] ?? '')),
            trim((string)($cab['firma_3'] ?? $empresa['firma_3'] ?? '')),
        ];
        $tieneFirmas = count(array_filter($firmas, fn($f) => $f !== '')) > 0;
        if ($tieneFirmas) {
            if ($yFirmas + 20 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                $yFirmas = $pdf->GetY() + 15;
            }
            $firmaW = ($cW - 20) / 3;
            $x = $mL;
            foreach ($firmas as $etiqueta) {
                // Línea de firma
                $pdf->Line($x + 5, $yFirmas, $x + $firmaW - 5, $yFirmas);
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetXY($x, $yFirmas + 1);
                $pdf->Cell($firmaW, 4, $etiqueta, 0, 0, 'C');
                $x += $firmaW + 10;
            }
            $yIzq = $yFirmas + 6;
        }

        // Mensaje Personalizado (Leyenda PDF)
        $leyendaTitulo  = $empresa['leyenda_pdf_titulo'] ?? '';
        $leyendaMensaje = $empresa['leyenda_pdf_mensaje'] ?? '';
        if (!empty($leyendaTitulo) || !empty($leyendaMensaje)) {
            // Posicionar debajo del máximo entre la columna izquierda y derecha
            $yFinal = max($yIzq, $yTot) + 4;

            // Verificar salto de página manual si no cabe (aprox 30 unidades)
            if ($yFinal + 30 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                $yFinal = $pdf->GetY() + 4;
            }

            if (!empty($leyendaTitulo)) {
                $pdf->SetFont('helvetica', 'B', 7.5);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->SetXY($mL, $yFinal);
                $pdf->Cell($cW, $lh, mb_strtoupper($leyendaTitulo, 'UTF-8'), 1, 1, 'C', true);
                $yFinal += $lh;
            }
            if (!empty($leyendaMensaje)) {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetXY($mL, $yFinal);
                $pdf->MultiCell($cW, 4.5, $leyendaMensaje, 1, 'L', false, 1);
            }
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function filaTotales(
        TCPDF $pdf, float $x, float $y,
        float $lblW, float $valW, float $h,
        string $lbl, float $val
    ): void {
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($x, $y);
        $pdf->Cell($lblW, $h, $lbl, 1, 0, 'L');
        $pdf->Cell($valW, $h, number_format($val, 2), 1, 0, 'R');
    }

    /**
     * Número del recibo: usa la clave `recibo_numero` si viene; caso contrario
     * lo construye desde establecimiento-punto_emision-secuencial.
     */
    private function numeroRecibo(array $cab): string
    {
        $num = trim((string)($cab['recibo_numero'] ?? ''));
        if ($num !== '') {
            return $num;
        }
        $est = str_pad($cab['establecimiento'] ?? '001', 3, '0', STR_PAD_LEFT);
        $pto = str_pad($cab['punto_emision']   ?? '001', 3, '0', STR_PAD_LEFT);
        $sec = str_pad($cab['secuencial']      ?? '000000001', 9, '0', STR_PAD_LEFT);
        return "{$est}-{$pto}-{$sec}";
    }

    /**
     * Determina si el recibo es "con impuestos" a partir de $cab['con_impuestos'].
     * Postgres puede devolver true/false, 't'/'f', 1/0 o '1'/'0'.
     */
    private function esConImpuestos(array $cab): bool
    {
        if (!array_key_exists('con_impuestos', $cab)) {
            return true; // por defecto se asume con impuestos
        }
        $v = $cab['con_impuestos'];
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string)$v));
        return in_array($s, ['t', 'true', '1', 'si', 'sí', 'y', 'yes'], true);
    }
}
