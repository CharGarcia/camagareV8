<?php
declare(strict_types=1);

namespace App\Services;

use TCPDF;
use App\repositories\modulos\PlantillasPdfRepository;

/**
 * Renderiza una plantilla PDF diseñada en el módulo "Plantillas de Documentos".
 * Toma el JSON guardado y genera el PDF real con TCPDF usando los datos del documento.
 */
class PlantillasPdfRendererService
{
    private TCPDF $pdf;
    private array $datos = [];
    private PlantillasPdfRepository $repo;

    public function __construct()
    {
        $this->repo = new PlantillasPdfRepository();
    }

    // ── Punto de entrada externo ──────────────────────────────────────────────

    public function getPlantillaActiva(int $idEmpresa, string $tipoDocumento): ?array
    {
        return $this->repo->getActiva($idEmpresa, $tipoDocumento);
    }

    /**
     * Genera el PDF a partir de la plantilla activa.
     * Compatible con la firma de FacturaVentaPdfService::generar().
     */
    public function generar(
        array $plantilla,
        array $cabecera,
        array $detalles,
        array $pagos,
        array $infoAdicional,
        array $empresa,
        string $outputDest = 'D'
    ) {
        $config    = json_decode($plantilla['configuracion'] ?? '{}', true) ?? [];
        $pagCfg    = $config['pagina'] ?? [];
        $elementos = $config['elementos'] ?? [];

        $formato = strtoupper($pagCfg['formato']     ?? 'A4');
        $orient  = strtoupper($pagCfg['orientacion'] ?? 'P');
        $mL = (float)($pagCfg['margenLeft']   ?? 10);
        $mR = (float)($pagCfg['margenRight']  ?? 10);
        $mT = (float)($pagCfg['margenTop']    ?? 10);
        $mB = (float)($pagCfg['margenBottom'] ?? 15);

        // Normalizar formato para TCPDF
        $formatoTcpdf = match($formato) {
            'LETTER' => 'LETTER',
            'LEGAL'  => 'LEGAL',
            'A5'     => 'A5',
            default  => 'A4',
        };

        $totales      = $this->calcularTotales($detalles, $cabecera);
        $this->datos  = $this->construirDatos($cabecera, $empresa, $totales);

        $this->pdf = new TCPDF($orient, 'mm', $formatoTcpdf, true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Factura ' . $this->numeroFactura($cabecera));
        $this->pdf->SetMargins($mL, $mT, $mR);
        $this->pdf->SetAutoPageBreak(true, $mB);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);

        // Ordenar por z-index antes de renderizar
        usort($elementos, fn($a, $b) => (int)($a['z'] ?? 0) <=> (int)($b['z'] ?? 0));

        foreach ($elementos as $el) {
            $this->renderizarElemento($el, $detalles, $pagos, $infoAdicional);
        }

        $num = $this->numeroFactura($cabecera);
        if ($outputDest === 'S') {
            return $this->pdf->Output('Factura_' . $num . '.pdf', 'S');
        }
        $this->pdf->Output('Factura_' . $num . '.pdf', $outputDest);
    }

    // ── Dispatcher de elementos ───────────────────────────────────────────────

    private function renderizarElemento(array $el, array $detalles, array $pagos, array $infoAdicional): void
    {
        $tipo = $el['tipo'] ?? 'texto';
        $x    = (float)($el['x'] ?? 0);
        $y    = (float)($el['y'] ?? 0);
        $w    = max(1.0, (float)($el['w'] ?? 10));
        $h    = max(1.0, (float)($el['h'] ?? 5));

        match ($tipo) {
            'texto'        => $this->renderTexto($el, $x, $y, $w, $h),
            'campo'        => $this->renderCampo($el, $x, $y, $w, $h),
            'rectangulo'   => $this->renderRectangulo($el, $x, $y, $w, $h),
            'linea'        => $this->renderLinea($el, $x, $y, $w),
            'codigoBarras' => $this->renderBarcode($el, $x, $y, $w, $h),
            'tabla'        => $this->renderTabla($el, $x, $y, $w, $detalles, $pagos, $infoAdicional),
            'imagen'       => $this->renderImagen($el, $x, $y, $w, $h),
            default        => null,
        };
    }

    // ── Tipos de elementos ────────────────────────────────────────────────────

    private function renderTexto(array $el, float $x, float $y, float $w, float $h): void
    {
        $texto = $el['contenido'] ?? '';
        if ($texto === '') return;

        $this->aplicarEstilo($el);
        $lh = $this->lineaAltura($el);

        $this->pdf->SetXY($x, $y);
        $this->pdf->MultiCell($w, $lh, $texto, $this->bordeTcpdf($el), $el['alineacion'] ?? 'L', $this->tieneRelleno($el), 1);
    }

    private function renderCampo(array $el, float $x, float $y, float $w, float $h): void
    {
        $campo = $el['campo'] ?? '';
        if ($campo === '') return;

        if ($campo === '{empresa_logo}') {
            $this->renderImagen($el, $x, $y, $w, $h);
            return;
        }

        $valor = $this->resolverCampo($campo);
        $this->aplicarEstilo($el);
        $lh = $this->lineaAltura($el);

        $this->pdf->SetXY($x, $y);
        $this->pdf->MultiCell($w, $lh, $valor, $this->bordeTcpdf($el), $el['alineacion'] ?? 'L', $this->tieneRelleno($el), 1);
    }

    private function renderRectangulo(array $el, float $x, float $y, float $w, float $h): void
    {
        $borde = $el['borde'] ?? [];
        $this->pdf->SetLineWidth((float)($borde['grosor'] ?? 0.3));
        $this->setDrawColor($borde['color'] ?? '#000000');
        $this->setFillColor($el['colorFondo'] ?? '#ffffff');

        $radio  = (float)($borde['radio'] ?? 0);
        $relleno = $this->tieneRelleno($el);
        $estilo = $relleno ? 'DF' : 'D';

        if ($radio > 0) {
            $this->pdf->RoundedRect($x, $y, $w, $h, $radio, '1111', $estilo);
        } else {
            $this->pdf->Rect($x, $y, $w, $h, $estilo);
        }
    }

    private function renderLinea(array $el, float $x, float $y, float $w): void
    {
        $borde = $el['borde'] ?? [];
        $this->pdf->SetLineWidth((float)($borde['grosor'] ?? 0.5));
        $this->setDrawColor($borde['color'] ?? '#000000');
        $this->pdf->Line($x, $y, $x + $w, $y);
    }

    private function renderBarcode(array $el, float $x, float $y, float $w, float $h): void
    {
        $clave = $this->resolverCampo('{clave_acceso}');
        if ($clave === '' || $clave === '{clave_acceso}') return;

        $this->pdf->write1DBarcode(
            $clave, 'C128',
            $x, $y, $w, $h,
            0.4,
            ['position' => 'C', 'text' => false, 'stretch' => true],
            'N'
        );
        // Número debajo del barcode
        $this->pdf->SetFont('helvetica', '', 5.5);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetXY($x, $y + $h);
        $this->pdf->Cell($w, 3.5, $clave, 0, 1, 'C');
    }

    private function renderImagen(array $el, float $x, float $y, float $w, float $h): void
    {
        $logoRaw = $this->datos['{empresa_logo}'] ?? '';
        if ($logoRaw === '') return;
        $path = \MVC_ROOT . '/' . ltrim($logoRaw, '/');
        if (!file_exists($path)) return;
        $this->pdf->Image($path, $x, $y, $w, $h > 0 ? $h : 0, '', '', '', false, 300);
    }

    private function renderTabla(array $el, float $x, float $y, float $w, array $detalles, array $pagos, array $infoAdicional): void
    {
        switch ($el['campo'] ?? '') {
            case 'tabla:detalles':
                $this->renderTablaDetalles($el, $x, $y, $w, $detalles);
                break;
            case 'tabla:pagos':
                $this->renderTablaPagos($el, $x, $y, $w, $pagos);
                break;
            case 'tabla:info_adicional':
                $this->renderTablaInfoAdicional($el, $x, $y, $w, $infoAdicional);
                break;
        }
    }

    // ── Tablas de datos ───────────────────────────────────────────────────────

    private function renderTablaDetalles(array $el, float $x, float $y, float $w, array $detalles): void
    {
        $pdf     = $this->pdf;
        $cfg     = $el['tablaConfig'] ?? [];
        $est     = $cfg['estilos']    ?? [];

        // Columnas por defecto
        $defCols = [
            ['key' => 'codigo_principal',          'titulo' => "Cód.\nPrincipal",  'ancho' => 16, 'alineacion' => 'L', 'visible' => true],
            ['key' => 'codigo_auxiliar',           'titulo' => "Cód.\nAuxiliar",   'ancho' => 14, 'alineacion' => 'L', 'visible' => true],
            ['key' => 'cantidad',                  'titulo' => "Cantidad",           'ancho' => 14, 'alineacion' => 'R', 'visible' => true],
            ['key' => 'descripcion',               'titulo' => "Descripción",        'ancho' => 0,  'alineacion' => 'L', 'visible' => true],
            ['key' => 'detalle_adicional',         'titulo' => "Det.\nAdicional",    'ancho' => 22, 'alineacion' => 'L', 'visible' => true],
            ['key' => 'precio_unitario',           'titulo' => "Precio\nUnitario",   'ancho' => 20, 'alineacion' => 'R', 'visible' => true],
            ['key' => 'descuento',                 'titulo' => "Descuento",           'ancho' => 16, 'alineacion' => 'R', 'visible' => true],
            ['key' => 'precio_total_sin_impuesto', 'titulo' => "Precio\nTotal",      'ancho' => 18, 'alineacion' => 'R', 'visible' => true],
        ];

        $cfgCols = !empty($cfg['columnas']) ? $cfg['columnas'] : $defCols;
        $cols    = array_values(array_filter($cfgCols, fn($c) => (bool)($c['visible'] ?? true)));

        // Calcular ancho flexible (columnas con ancho=0)
        $fixedW = array_sum(array_map(fn($c) => (float)($c['ancho'] ?? 0), $cols));
        $flexW  = max(10.0, $w - $fixedW);
        foreach ($cols as &$c) {
            if ((float)($c['ancho'] ?? 0) === 0.0) $c['ancho'] = $flexW;
        }
        unset($c);

        // Estilos
        $headerBg    = $this->hexRgb($est['headerBg']    ?? '#e6e6e6');
        $headerColor = $this->hexRgb($est['headerColor'] ?? '#000000');
        $headerSize  = (float)($est['headerSize']  ?? 6.5);
        $rowSize     = (float)($est['rowSize']     ?? 7.0);
        $altBg       = $this->hexRgb($est['altBg']       ?? '#fafafa');
        $lh          = (float)($est['lineaAltura'] ?? 5.0);
        $bdColor     = $this->hexRgb($est['bordeColor']  ?? '#000000');
        $bdGrosor    = (float)($est['bordeGrosor'] ?? 0.3);

        // Encabezado
        $pdf->SetFont('helvetica', 'B', $headerSize);
        $pdf->SetFillColor(...$headerBg);
        $pdf->SetTextColor(...$headerColor);
        $pdf->SetLineWidth($bdGrosor);
        $pdf->SetDrawColor(...$bdColor);
        $pdf->SetXY($x, $y);
        foreach ($cols as $col) {
            $pdf->MultiCell((float)$col['ancho'], 3.8, $col['titulo'], 1, 'C', true, 0);
        }
        $pdf->Ln();
        $y += 7.6;

        // Filas
        $pdf->SetFont('helvetica', '', $rowSize);
        $pdf->SetTextColor(0, 0, 0);
        $numericKeys = ['cantidad', 'precio_unitario', 'descuento', 'precio_total_sin_impuesto'];
        $wrapKeys    = ['descripcion', 'detalle_adicional', 'info_adicional'];
        $alt = false;

        foreach ($detalles as $d) {
            $bg  = $alt ? $altBg : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $vals = [];
            foreach ($cols as $col) {
                // La columna "Det. Adicional" se definió con key 'detalle_adicional',
                // pero el dato se guarda en 'info_adicional'. Resolver con fallback.
                $key = $col['key'] === 'detalle_adicional' ? 'info_adicional' : $col['key'];
                $v   = (string)($d[$key] ?? $d[$col['key']] ?? '');
                if (in_array($col['key'], $numericKeys)) {
                    $v = number_format((float)$v, 2);
                }
                $vals[] = $v;
            }

            // Altura de fila según columnas de texto largo
            $ch = $lh;
            foreach ($cols as $i => $col) {
                if (in_array($col['key'], $wrapKeys) && (float)$col['ancho'] > 2) {
                    $n  = max(1, (int)ceil($pdf->GetStringWidth($vals[$i]) / ((float)$col['ancho'] - 2)));
                    $ch = max($ch, $n * $lh);
                }
            }

            $xCur = $x;
            $yRow = $pdf->GetY();
            foreach ($cols as $i => $col) {
                $pdf->SetXY($xCur, $yRow);
                if (in_array($col['key'], $wrapKeys)) {
                    // Alineación horizontal según columna + vertical centrada (valign 'M')
                    $pdf->MultiCell((float)$col['ancho'], $ch, $vals[$i], 1, $col['alineacion'], true, 0, '', '', true, 0, false, true, 0, 'M');
                } else {
                    $pdf->Cell((float)$col['ancho'], $ch, $vals[$i], 1, 0, $col['alineacion'], true);
                }
                $xCur += (float)$col['ancho'];
            }
            $pdf->SetXY($x, $yRow + $ch);
        }
    }

    private function renderTablaPagos(array $el, float $x, float $y, float $w, array $pagos): void
    {
        if (empty($pagos)) return;
        $pdf  = $this->pdf;
        $cfg  = $el['tablaConfig'] ?? [];
        $est  = $cfg['estilos']    ?? [];

        $defCols = [
            ['key' => 'nombre_forma_pago', 'titulo' => 'Forma de pago', 'ancho' => 0,  'alineacion' => 'L', 'visible' => true],
            ['key' => 'total',             'titulo' => 'Valor',          'ancho' => 28, 'alineacion' => 'R', 'visible' => true],
            ['key' => 'plazo',             'titulo' => 'Días Crédito',   'ancho' => 22, 'alineacion' => 'C', 'visible' => true],
            ['key' => 'unidad_tiempo',     'titulo' => 'Plazo',          'ancho' => 22, 'alineacion' => 'C', 'visible' => true],
        ];

        $cfgCols = !empty($cfg['columnas']) ? $cfg['columnas'] : $defCols;
        $cols    = array_values(array_filter($cfgCols, fn($c) => (bool)($c['visible'] ?? true)));
        $fixedW  = array_sum(array_map(fn($c) => (float)($c['ancho'] ?? 0), $cols));
        $flexW   = max(10.0, $w - $fixedW);
        foreach ($cols as &$c) {
            if ((float)($c['ancho'] ?? 0) === 0.0) $c['ancho'] = $flexW;
        }
        unset($c);

        $headerBg    = $this->hexRgb($est['headerBg']    ?? '#e6e6e6');
        $headerColor = $this->hexRgb($est['headerColor'] ?? '#000000');
        $headerSize  = (float)($est['headerSize']  ?? 7.0);
        $rowSize     = (float)($est['rowSize']     ?? 7.0);
        $altBg       = $this->hexRgb($est['altBg']       ?? '#ffffff');
        $lh          = (float)($est['lineaAltura'] ?? 5.0);
        $bdColor     = $this->hexRgb($est['bordeColor']  ?? '#000000');
        $bdGrosor    = (float)($est['bordeGrosor'] ?? 0.3);

        $pdf->SetFont('helvetica', 'B', $headerSize);
        $pdf->SetFillColor(...$headerBg);
        $pdf->SetTextColor(...$headerColor);
        $pdf->SetLineWidth($bdGrosor);
        $pdf->SetDrawColor(...$bdColor);
        $pdf->SetXY($x, $y);
        foreach ($cols as $col) {
            $pdf->Cell((float)$col['ancho'], $lh, $col['titulo'], 1, 0, 'C', true);
        }
        $pdf->Ln();
        $y += $lh;

        $pdf->SetFont('helvetica', '', $rowSize);
        $pdf->SetTextColor(0, 0, 0);
        $alt = false;
        foreach ($pagos as $p) {
            $bg  = $alt ? $altBg : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);
            $pdf->SetXY($x, $y);

            foreach ($cols as $col) {
                $v = match ($col['key']) {
                    'nombre_forma_pago' => $p['nombre_forma_pago'] ?? ($p['forma_pago'] ?? ''),
                    'total'             => number_format((float)($p['total'] ?? 0), 2),
                    'plazo'             => (int)($p['plazo'] ?? 0) > 0 ? (string)(int)$p['plazo'] : '0',
                    'unidad_tiempo'     => (int)($p['plazo'] ?? 0) > 0
                                            ? (int)$p['plazo'] . ' ' . trim($p['unidad_tiempo'] ?? 'días')
                                            : '—',
                    default             => (string)($p[$col['key']] ?? ''),
                };
                if ($col['key'] === 'nombre_forma_pago') {
                    $pdf->MultiCell((float)$col['ancho'], $lh, $v, 1, $col['alineacion'], !$alt, 0);
                } else {
                    $pdf->Cell((float)$col['ancho'], $lh, $v, 1, 0, $col['alineacion'], !$alt);
                }
            }
            $pdf->Ln();
            $y += $lh;
        }
    }

    private function renderTablaInfoAdicional(array $el, float $x, float $y, float $w, array $infoAdicional): void
    {
        if (empty($infoAdicional)) return;
        $pdf  = $this->pdf;
        $cfg  = $el['tablaConfig'] ?? [];
        $est  = $cfg['estilos']    ?? [];

        $defCols = [
            ['key' => 'nombre', 'titulo' => 'Concepto', 'ancho' => 0,  'alineacion' => 'L', 'visible' => true],
            ['key' => 'valor',  'titulo' => 'Valor',    'ancho' => 50, 'alineacion' => 'L', 'visible' => true],
        ];

        $cfgCols = !empty($cfg['columnas']) ? $cfg['columnas'] : $defCols;
        $cols    = array_values(array_filter($cfgCols, fn($c) => (bool)($c['visible'] ?? true)));
        $fixedW  = array_sum(array_map(fn($c) => (float)($c['ancho'] ?? 0), $cols));
        $flexW   = max(10.0, $w - $fixedW);
        foreach ($cols as &$c) {
            if ((float)($c['ancho'] ?? 0) === 0.0) $c['ancho'] = $flexW;
        }
        unset($c);

        $headerBg    = $this->hexRgb($est['headerBg']    ?? '#e6e6e6');
        $headerColor = $this->hexRgb($est['headerColor'] ?? '#000000');
        $headerSize  = (float)($est['headerSize']  ?? 7.5);
        $rowSize     = (float)($est['rowSize']     ?? 7.0);
        $lh          = (float)($est['lineaAltura'] ?? 5.0);
        $bdColor     = $this->hexRgb($est['bordeColor']  ?? '#000000');
        $bdGrosor    = (float)($est['bordeGrosor'] ?? 0.3);

        // Fila de encabezado con título global
        $pdf->SetFont('helvetica', 'B', $headerSize);
        $pdf->SetFillColor(...$headerBg);
        $pdf->SetTextColor(...$headerColor);
        $pdf->SetLineWidth($bdGrosor);
        $pdf->SetDrawColor(...$bdColor);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $lh, 'Información Adicional', 1, 1, 'C', true);
        $y += $lh;

        $pdf->SetTextColor(0, 0, 0);
        foreach ($infoAdicional as $info) {
            $pdf->SetXY($x, $y);
            foreach ($cols as $col) {
                $v = (string)($info[$col['key']] ?? '');
                $isNombre = $col['key'] === 'nombre';
                $pdf->SetFont('helvetica', $isNombre ? 'B' : '', $rowSize);
                if ($col['key'] === 'valor') {
                    $pdf->MultiCell((float)$col['ancho'], $lh, $v, 1, $col['alineacion'], false, 1);
                } else {
                    $pdf->Cell((float)$col['ancho'], $lh, $v, 1, 0, $col['alineacion']);
                }
            }
            $y = $pdf->GetY();
        }
    }

    // ── Construcción del mapa de datos ────────────────────────────────────────

    private function construirDatos(array $cabecera, array $empresa, array $totales): array
    {
        $fecha = '';
        if (!empty($cabecera['fecha_emision'])) {
            $ts    = strtotime($cabecera['fecha_emision']);
            $fecha = $ts ? date('d/m/Y', $ts) : $cabecera['fecha_emision'];
        }

        $tipoAmb  = (string)($cabecera['tipo_ambiente'] ?? $empresa['tipo_ambiente'] ?? '1');
        $ambiente = ($tipoAmb === '2') ? 'PRODUCCIÓN' : 'PRUEBAS';

        $obl      = strtoupper(trim((string)($empresa['obligado_contabilidad'] ?? 'NO')));
        $oblLabel = ($obl === 'SI' || $obl === '1' || $obl === 'TRUE') ? 'SI' : 'NO';

        return [
            // ── Empresa
            '{empresa_nombre}'        => $empresa['nombre'] ?? '',
            '{empresa_comercial}'     => $empresa['nombre_comercial'] ?? '',
            '{empresa_ruc}'           => $empresa['ruc'] ?? '',
            '{empresa_direccion}'     => $empresa['direccion_matriz'] ?? $empresa['direccion'] ?? '',
            '{empresa_sucursal}'      => $empresa['direccion_sucursal'] ?? '',
            '{empresa_telefono}'      => $empresa['telefono'] ?? '',
            '{empresa_correo}'        => $empresa['correo'] ?? $empresa['email'] ?? '',
            '{empresa_contribuyente}' => $empresa['resolucion_contribuyente'] ?? '',
            '{empresa_obligado}'      => $oblLabel,
            '{empresa_logo}'          => $empresa['logo'] ?? '',
            // ── Factura
            '{numero_factura}'        => $this->numeroFactura($cabecera),
            '{fecha_emision}'         => $fecha,
            '{numero_autorizacion}'   => $cabecera['clave_acceso'] ?? '',
            '{clave_acceso}'          => $cabecera['clave_acceso'] ?? '',
            '{fecha_autorizacion}'    => $cabecera['fecha_autorizacion'] ?? '',
            '{ambiente}'              => $ambiente,
            '{tipo_emision}'          => strtoupper(trim($empresa['tipo_emision'] ?? 'NORMAL')),
            '{observaciones}'         => $cabecera['observaciones'] ?? '',
            // ── Cliente
            '{cliente_nombre}'        => $cabecera['cliente_nombre'] ?? '',
            '{cliente_ruc}'           => $cabecera['cliente_ruc'] ?? $cabecera['cliente_identificacion'] ?? '',
            '{cliente_direccion}'     => $cabecera['cliente_direccion'] ?? '',
            '{cliente_email}'         => $cabecera['cliente_email'] ?? '',
            '{cliente_telefono}'      => $cabecera['cliente_telefono'] ?? '',
            '{guia_remision}'         => $cabecera['guia_remision'] ?? '',
            '{plazo}'                 => $cabecera['plazo'] ?? '',
            // ── Totales
            '{subtotal_0}'            => number_format($totales['subtotal_0'], 2),
            '{subtotal_iva}'          => number_format($totales['subtotal_iva'], 2),
            '{total_descuento}'       => number_format($totales['total_descuento'], 2),
            '{ice}'                   => number_format($totales['ice'], 2),
            '{iva}'                   => number_format($totales['iva'], 2),
            '{propina}'               => number_format($totales['propina'], 2),
            '{valor_total}'           => number_format($totales['valor_total'], 2),
        ];
    }

    private function resolverCampo(string $campo): string
    {
        return $this->datos[$campo] ?? '';
    }

    private function calcularTotales(array $detalles, array $cabecera): array
    {
        $subtotal0   = 0.0;
        $subtotalIva = 0.0;
        $totalDcto   = 0.0;
        $totalIce    = 0.0;
        $totalIva    = 0.0;

        foreach ($detalles as $d) {
            $totalDcto += (float)($d['descuento'] ?? 0);
            $base      = (float)($d['precio_total_sin_impuesto'] ?? 0);
            $tieneIva  = false;

            foreach ($d['impuestos'] ?? [] as $imp) {
                $cod = (string)($imp['codigo_impuesto'] ?? '');
                $tar = (float)($imp['tarifa'] ?? 0);
                $val = (float)($imp['valor'] ?? 0);
                if ($cod === '2') {
                    if ($tar == 0) {
                        $subtotal0 += $base;
                    } else {
                        $subtotalIva += $base;
                    }
                    $totalIva += $val;
                    $tieneIva  = true;
                } elseif ($cod === '3') {
                    $totalIce += $val;
                }
            }
            if (!$tieneIva) {
                $subtotal0 += $base;
            }
        }

        $propina    = (float)($cabecera['propina'] ?? 0);

        // total_descuento y valor_total van al XML autorizado y al SRI desde la
        // cabecera: el RIDE los toma de ahí (no los recalcula) para mostrar las
        // mismas cifras del comprobante; respaldo al cálculo de detalles.
        if (isset($cabecera['total_descuento'])) {
            $totalDcto = (float)$cabecera['total_descuento'];
        }
        $valorTotal = isset($cabecera['importe_total'])
            ? (float)$cabecera['importe_total']
            : $subtotal0 + $subtotalIva + $totalIva + $totalIce + $propina;

        return [
            'subtotal_0'      => $subtotal0,
            'subtotal_iva'    => $subtotalIva,
            'total_descuento' => $totalDcto,
            'ice'             => $totalIce,
            'iva'             => $totalIva,
            'propina'         => $propina,
            'valor_total'     => $valorTotal,
        ];
    }

    // ── Helpers de estilo TCPDF ───────────────────────────────────────────────

    private function aplicarEstilo(array $el): void
    {
        $fuente = $el['fuente'] ?? 'helvetica';
        $tam    = (float)($el['tamano'] ?? 8);
        $estilo = $el['estilo'] ?? '';
        $this->pdf->SetFont($fuente, $estilo, $tam);

        [$r, $g, $b] = $this->hexRgb($el['colorTexto'] ?? '#000000');
        $this->pdf->SetTextColor($r, $g, $b);

        [$fr, $fg, $fb] = $this->hexRgb($el['colorFondo'] ?? '#ffffff');
        $this->pdf->SetFillColor($fr, $fg, $fb);

        $borde = $el['borde'] ?? [];
        $this->pdf->SetLineWidth((float)($borde['grosor'] ?? 0.3));
        $this->setDrawColor($borde['color'] ?? '#000000');
    }

    private function lineaAltura(array $el): float
    {
        // Altura de línea proporcional al tamaño de fuente (1 pt ≈ 0.353 mm)
        return max(4.0, (float)($el['tamano'] ?? 8) * 0.45);
    }

    private function bordeTcpdf(array $el): string|int
    {
        $lados = $el['borde']['lados'] ?? '';
        if ($lados === '' || $lados === 'none') return 0;
        if ($lados === 'LTBR') return 1;
        return $lados;
    }

    private function tieneRelleno(array $el): bool
    {
        $color = strtolower(trim($el['colorFondo'] ?? '#ffffff'));
        return !in_array($color, ['#ffffff', '#fff', '', 'white', 'transparent']);
    }

    private function setDrawColor(string $hex): void
    {
        [$r, $g, $b] = $this->hexRgb($hex);
        $this->pdf->SetDrawColor($r, $g, $b);
    }

    private function setFillColor(string $hex): void
    {
        [$r, $g, $b] = $this->hexRgb($hex);
        $this->pdf->SetFillColor($r, $g, $b);
    }

    private function hexRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) return [0, 0, 0];
        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2)),
        ];
    }

    private function numeroFactura(array $cab): string
    {
        $est = str_pad($cab['establecimiento'] ?? '001', 3, '0', STR_PAD_LEFT);
        $pto = str_pad($cab['punto_emision']   ?? '001', 3, '0', STR_PAD_LEFT);
        $sec = str_pad($cab['secuencial']      ?? '000000001', 9, '0', STR_PAD_LEFT);
        return "{$est}-{$pto}-{$sec}";
    }
}
