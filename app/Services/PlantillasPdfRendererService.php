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

    /** Decimales configurados por la empresa (cantidad y precio unitario). */
    private int $decCantidad = 2;
    private int $decPrecio   = 2;

    public function __construct()
    {
        $this->repo = new PlantillasPdfRepository();
    }

    // ── Punto de entrada externo ──────────────────────────────────────────────

    public function getPlantillaActiva(int $idEmpresa, string $tipoDocumento, ?int $idBanco = null): ?array
    {
        return $this->repo->getActiva($idEmpresa, $tipoDocumento, $idBanco);
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
        string $outputDest = 'D',
        ?array $asiento = null
    ) {
        $tipoDocumento = $plantilla['tipo_documento'] ?? 'factura_venta';

        // Agrupación y etiquetas de ítems configuradas por la empresa. Solo aplica
        // a facturas de venta: este renderer es genérico y el resto de documentos
        // (egresos, consignaciones, …) no tienen esta configuración.
        if ($tipoDocumento === 'factura_venta') {
            $detalles = (new \App\Services\modulos\FacturaItemsPresentacionService())
                ->preparar($detalles, $empresa);
            // RUC del proveedor del sistema (Res. NAC-DGERCGC26-00000027): ya viene
            // guardado en $infoAdicional desde FacturaVentaService::crear() para los
            // documentos nuevos; no se inyecta aquí para no aplicarlo a los ya emitidos.
        }

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

        // Decimales configurados por la empresa (igual que en el sistema/UI).
        $this->decCantidad = max(0, (int)($empresa['decimales_cantidad'] ?? 2));
        $this->decPrecio   = max(0, (int)($empresa['decimales_precio']   ?? 2));

        $totales     = $this->calcularTotales($detalles, $cabecera);
        $this->datos = array_merge(
            $this->construirDatos($cabecera, $empresa, $totales),
            $this->construirDatosEspecificos($tipoDocumento, $cabecera, $empresa, $asiento)
        );

        $this->pdf = new TCPDF($orient, 'mm', $formatoTcpdf, true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $numDoc = $this->numeroFactura($cabecera);
        $this->pdf->SetTitle(trim(($this->datos['{titulo_documento}'] ?? 'Documento') . ' ' . $numDoc));
        $this->pdf->SetMargins($mL, $mT, $mR);
        $this->pdf->SetAutoPageBreak(true, $mB);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);

        // Ordenar por z-index antes de renderizar
        usort($elementos, fn($a, $b) => (int)($a['z'] ?? 0) <=> (int)($b['z'] ?? 0));

        foreach ($elementos as $el) {
            $this->renderizarElemento($el, $detalles, $pagos, $infoAdicional, $asiento);
        }

        $nombreArchivo = str_replace(' ', '_', trim(($this->datos['{titulo_documento}'] ?? 'Documento') . '_' . $numDoc));
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombreArchivo . '.pdf', 'S');
        }
        $this->pdf->Output($nombreArchivo . '.pdf', $outputDest);
    }

    /**
     * Genera el PDF de uno o varios CHEQUES (una página por cheque). Cada cheque
     * puede tener su propia plantilla (formato, orientación y elementos), lo que
     * permite imprimir en un mismo lote cheques de bancos distintos con su propio
     * diseño. `$plantillasPorBanco` es un mapa clave→plantilla, donde la clave es
     * el `id_banco` del cheque (como string) o `'0'` para la plantilla genérica
     * de la empresa (sin banco). Cada cheque en `$cheques` debe traer `_banco_key`
     * con la clave que le corresponde en ese mapa.
     */
    public function generarCheques(array $plantillasPorBanco, array $cheques, array $empresa, string $outputDest = 'I')
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Cheques');
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetFont('helvetica', '', 9);

        foreach ($cheques as $chq) {
            $bancoKey  = (string)($chq['_banco_key'] ?? '0');
            $plantilla = $plantillasPorBanco[$bancoKey] ?? $plantillasPorBanco['0'] ?? null;
            $config    = json_decode($plantilla['configuracion'] ?? '{}', true) ?? [];
            $pagCfg    = $config['pagina'] ?? [];
            $elementos = $config['elementos'] ?? [];
            usort($elementos, fn($a, $b) => (int)($a['z'] ?? 0) <=> (int)($b['z'] ?? 0));

            $formato = strtoupper($pagCfg['formato']     ?? 'A4');
            $orient  = strtoupper($pagCfg['orientacion'] ?? 'P');
            $mL = (float)($pagCfg['margenLeft']  ?? 10);
            $mR = (float)($pagCfg['margenRight'] ?? 10);
            $mT = (float)($pagCfg['margenTop']   ?? 10);

            // Tamaño personalizado (p. ej. un cheque suelto): formato CUSTOM + ancho/alto en mm.
            $ancho = (float)($pagCfg['ancho'] ?? 0);
            $alto  = (float)($pagCfg['alto']  ?? 0);
            if ($formato === 'CUSTOM' && $ancho > 0 && $alto > 0) {
                $formatoTcpdf = [$ancho, $alto];
            } else {
                $formatoTcpdf = match($formato) {
                    'LETTER' => 'LETTER',
                    'LEGAL'  => 'LEGAL',
                    'A5'     => 'A5',
                    default  => 'A4',
                };
            }

            $this->pdf->AddPage($orient, $formatoTcpdf);
            $this->pdf->SetMargins($mL, $mT, $mR);
            $this->datos = $this->construirDatosCheque($chq, $empresa);
            foreach ($elementos as $el) {
                $this->renderizarElemento($el, [], [], []);
            }
        }

        if ($outputDest === 'S') {
            return $this->pdf->Output('Cheques.pdf', 'S');
        }
        $this->pdf->Output('Cheques.pdf', $outputDest);
    }

    // ── Dispatcher de elementos ───────────────────────────────────────────────

    private function renderizarElemento(array $el, array $detalles, array $pagos, array $infoAdicional, ?array $asiento = null): void
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
            'tabla'        => $this->renderTabla($el, $x, $y, $w, $detalles, $pagos, $infoAdicional, $asiento),
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

    private function renderTabla(array $el, float $x, float $y, float $w, array $detalles, array $pagos, array $infoAdicional, ?array $asiento = null): void
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
            case 'tabla:retenciones':
                $this->renderTablaRetenciones($el, $x, $y, $w, $detalles);
                break;
            case 'tabla:asiento':
                $this->renderTablaAsiento($el, $x, $y, $w, $asiento);
                break;
            case 'tabla:cambio_devuelto':
                $this->renderTablaDetalles($el, $x, $y, $w, array_values(array_filter($detalles, fn($d) => ($d['tipo_linea'] ?? '') === 'devolucion')));
                break;
            case 'tabla:cambio_entrega':
                $this->renderTablaDetalles($el, $x, $y, $w, array_values(array_filter($detalles, fn($d) => ($d['tipo_linea'] ?? '') === 'entrega')));
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
                    // cantidad y precio unitario usan los decimales configurados;
                    // descuento y total van a 2 (montos).
                    $dec = match ($col['key']) {
                        'cantidad'        => $this->decCantidad,
                        'precio_unitario' => $this->decPrecio,
                        default           => 2,
                    };
                    $v = number_format((float)$v, $dec);
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

    /** Tabla "DETALLE DE RETENCIONES" (retención en compras/ventas): impuesto, código, concepto, base, %, valor. */
    private function renderTablaRetenciones(array $el, float $x, float $y, float $w, array $lineas): void
    {
        if (empty($lineas)) return;
        $pdf  = $this->pdf;
        $cfg  = $el['tablaConfig'] ?? [];
        $est  = $cfg['estilos']    ?? [];

        $defCols = [
            ['key' => 'impuesto_label',    'titulo' => 'Impuesto',    'ancho' => 24, 'alineacion' => 'C', 'visible' => true],
            ['key' => 'codigo_retencion',  'titulo' => 'Código',      'ancho' => 18, 'alineacion' => 'C', 'visible' => true],
            ['key' => 'concepto',          'titulo' => 'Concepto',    'ancho' => 0,  'alineacion' => 'L', 'visible' => true],
            ['key' => 'base_imponible',    'titulo' => 'Base Impon.', 'ancho' => 26, 'alineacion' => 'R', 'visible' => true],
            ['key' => 'porcentaje_retener','titulo' => '%',           'ancho' => 16, 'alineacion' => 'C', 'visible' => true],
            ['key' => 'valor_retenido',    'titulo' => 'Valor Ret.',  'ancho' => 26, 'alineacion' => 'R', 'visible' => true],
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
        $headerSize  = (float)($est['headerSize']  ?? 7);
        $rowSize     = (float)($est['rowSize']     ?? 7);
        $lh          = (float)($est['lineaAltura'] ?? 4.5);
        $bdColor     = $this->hexRgb($est['bordeColor']  ?? '#000000');
        $bdGrosor    = (float)($est['bordeGrosor'] ?? 0.3);

        $etiquetasImpuesto = ['1' => 'Renta (IR)', 'RENTA' => 'Renta (IR)', '2' => 'IVA', 'IVA' => 'IVA', '6' => 'ISD', 'ISD' => 'ISD'];

        $pdf->SetFont('helvetica', 'B', $headerSize);
        $pdf->SetFillColor(...$headerBg);
        $pdf->SetTextColor(...$headerColor);
        $pdf->SetLineWidth($bdGrosor);
        $pdf->SetDrawColor(...$bdColor);
        $pdf->SetXY($x, $y);
        foreach ($cols as $col) {
            $pdf->Cell((float)$col['ancho'], $lh + 1, $col['titulo'], 1, 0, 'C', true);
        }
        $pdf->Ln();
        $y = $pdf->GetY();

        $pdf->SetFont('helvetica', '', $rowSize);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($lineas as $l) {
            $codImp = strtoupper((string)($l['codigo_impuesto'] ?? '1'));
            $vals = [];
            foreach ($cols as $col) {
                $vals[] = match ($col['key']) {
                    'impuesto_label'     => $etiquetasImpuesto[$codImp] ?? $codImp,
                    'concepto'           => (string)($l['concepto'] ?? $l['sri_concepto'] ?? ''),
                    'base_imponible'     => number_format((float)($l['base_imponible'] ?? 0), 2),
                    'porcentaje_retener' => number_format((float)($l['porcentaje_retener'] ?? 0), 2) . '%',
                    'valor_retenido'     => number_format((float)($l['valor_retenido'] ?? 0), 2),
                    default              => (string)($l[$col['key']] ?? ''),
                };
            }
            $xCur = $x;
            $yRow = $pdf->GetY();
            foreach ($cols as $i => $col) {
                $pdf->SetXY($xCur, $yRow);
                $pdf->MultiCell((float)$col['ancho'], $lh, $vals[$i], 1, $col['alineacion'], false, 0);
                $xCur += (float)$col['ancho'];
            }
            $pdf->SetXY($x, $yRow + $lh);
        }
    }

    /** Tabla del asiento contable (egreso/ingreso/traspaso): cuenta, debe, haber. */
    private function renderTablaAsiento(array $el, float $x, float $y, float $w, ?array $asiento): void
    {
        $lineas = $asiento['detalles'] ?? [];
        if (empty($lineas)) return;
        $pdf  = $this->pdf;
        $cfg  = $el['tablaConfig'] ?? [];
        $est  = $cfg['estilos']    ?? [];

        $defCols = [
            ['key' => 'codigo_cuenta', 'titulo' => 'Código',  'ancho' => 22, 'alineacion' => 'L', 'visible' => true],
            ['key' => 'nombre_cuenta', 'titulo' => 'Cuenta',  'ancho' => 0,  'alineacion' => 'L', 'visible' => true],
            ['key' => 'debe',          'titulo' => 'Debe',    'ancho' => 26, 'alineacion' => 'R', 'visible' => true],
            ['key' => 'haber',         'titulo' => 'Haber',   'ancho' => 26, 'alineacion' => 'R', 'visible' => true],
        ];

        $cfgCols = !empty($cfg['columnas']) ? $cfg['columnas'] : $defCols;
        $cols    = array_values(array_filter($cfgCols, fn($c) => (bool)($c['visible'] ?? true)));
        $fixedW  = array_sum(array_map(fn($c) => (float)($c['ancho'] ?? 0), $cols));
        $flexW   = max(10.0, $w - $fixedW);
        foreach ($cols as &$c) {
            if ((float)($c['ancho'] ?? 0) === 0.0) $c['ancho'] = $flexW;
        }
        unset($c);

        $headerBg   = $this->hexRgb($est['headerBg']   ?? '#e6e6e6');
        $headerSize = (float)($est['headerSize'] ?? 7);
        $rowSize    = (float)($est['rowSize']    ?? 7);
        $lh         = (float)($est['lineaAltura'] ?? 4.5);

        $pdf->SetFont('helvetica', 'B', $headerSize);
        $pdf->SetFillColor(...$headerBg);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        foreach ($cols as $col) {
            $pdf->Cell((float)$col['ancho'], $lh + 1, $col['titulo'], 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', $rowSize);
        $sumDebe = 0.0; $sumHaber = 0.0;
        foreach ($lineas as $l) {
            $debe  = (float)($l['debe']  ?? 0);
            $haber = (float)($l['haber'] ?? 0);
            $sumDebe  += $debe;
            $sumHaber += $haber;
            $vals = [];
            foreach ($cols as $col) {
                $vals[] = match ($col['key']) {
                    'debe'  => $debe  > 0 ? number_format($debe, 2)  : '',
                    'haber' => $haber > 0 ? number_format($haber, 2) : '',
                    default => (string)($l[$col['key']] ?? ''),
                };
            }
            $xCur = $x;
            $yRow = $pdf->GetY();
            foreach ($cols as $i => $col) {
                $pdf->SetXY($xCur, $yRow);
                $pdf->Cell((float)$col['ancho'], $lh, $vals[$i], 1, 0, $col['alineacion']);
                $xCur += (float)$col['ancho'];
            }
            $pdf->SetXY($x, $yRow + $lh);
        }

        // Fila de totales.
        $pdf->SetFont('helvetica', 'B', $rowSize);
        $labelW = array_sum(array_map(fn($c) => (float)$c['ancho'], array_slice($cols, 0, count($cols) - 2)));
        $pdf->Cell($labelW, $lh, 'TOTALES', 1, 0, 'R');
        $pdf->Cell((float)$cols[count($cols) - 2]['ancho'], $lh, number_format($sumDebe, 2), 1, 0, 'R');
        $pdf->Cell((float)$cols[count($cols) - 1]['ancho'], $lh, number_format($sumHaber, 2), 1, 1, 'R');
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
            // Número genérico para comprobantes internos (consignación, retorno,
            // cambio de producto, facturación de consignación…): usan serie-secuencial
            // en vez de establecimiento-puntoEmisión-secuencial. Egreso/Ingreso/Traspaso
            // lo sobreescriben con su propio campo en construirDatosEspecificos().
            '{cc_numero}'              => array_key_exists('serie', $cabecera)
                ? trim((string) $cabecera['serie'] . '-' . (string) ($cabecera['secuencial'] ?? ''), '-')
                : $this->numeroFactura($cabecera),
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

    /**
     * Placeholders PROPIOS de cada tipo de documento (además de los genéricos de
     * `construirDatos()`): motivo de una NC, transportista de una guía, proveedor
     * emisor de una liquidación/compra, asiento de un egreso/ingreso, etc. Un tipo
     * sin variante aquí simplemente no agrega nada (usa solo lo genérico).
     */
    private function construirDatosEspecificos(string $tipo, array $cabecera, array $empresa, ?array $asiento): array
    {
        $fmtFecha = function ($v): string {
            if (empty($v)) return '';
            $ts = strtotime((string) $v);
            return $ts ? date('d/m/Y', $ts) : (string) $v;
        };
        $fmtMonto = fn($v) => number_format((float) $v, 2);

        return match ($tipo) {
            'nota_credito' => [
                '{nc_motivo}'                => (string) ($cabecera['motivo'] ?? ''),
                '{nc_num_doc_modificado}'    => (string) ($cabecera['num_doc_modificado'] ?? ''),
                '{nc_fecha_doc_sustento}'    => $fmtFecha($cabecera['fecha_emision_docs_sustento'] ?? ''),
                '{titulo_documento}'         => 'Nota de Crédito',
            ],
            'guia_remision' => [
                '{gr_transportista_nombre}'         => (string) ($cabecera['transportista_nombre'] ?? ''),
                '{gr_transportista_ruc}'             => (string) ($cabecera['transportista_ruc'] ?? ''),
                '{gr_placa}'                          => (string) ($cabecera['placa'] ?? ''),
                '{gr_fecha_inicio_transporte}'       => $fmtFecha($cabecera['fecha_inicio_transporte'] ?? ''),
                '{gr_fecha_fin_transporte}'          => $fmtFecha($cabecera['fecha_fin_transporte'] ?? ''),
                '{gr_direccion_partida}'              => (string) ($cabecera['direccion_partida'] ?? ''),
                '{gr_direccion_destino}'              => (string) ($cabecera['direccion_destino'] ?? $cabecera['cliente_direccion'] ?? ''),
                '{gr_motivo_traslado}'                => (string) ($cabecera['motivo_traslado'] ?? ''),
                '{gr_ruta}'                            => (string) ($cabecera['ruta'] ?? ''),
                '{gr_doc_aduanero_unico}'            => (string) ($cabecera['doc_aduanero_unico'] ?? ''),
                '{gr_num_doc_sustento}'               => (string) ($cabecera['num_doc_sustento'] ?? ''),
                '{gr_cod_doc_sustento}'               => (string) ($cabecera['cod_doc_sustento'] ?? ''),
                '{gr_num_autorizacion_doc_sustento}' => (string) ($cabecera['num_autorizacion_doc_sustento'] ?? ''),
                '{gr_fecha_doc_sustento}'            => $fmtFecha($cabecera['fecha_emision_doc_sustento'] ?? ''),
                '{titulo_documento}'                  => 'Guía de Remisión',
            ],
            'liquidacion_compra', 'compras' => [
                '{proveedor_nombre}'          => (string) ($cabecera['proveedor_nombre'] ?? ''),
                '{proveedor_ruc}'             => (string) ($cabecera['proveedor_ruc'] ?? ''),
                '{proveedor_direccion}'       => (string) ($cabecera['proveedor_direccion'] ?? ''),
                '{proveedor_nombre_tipo_id}'  => (string) ($cabecera['proveedor_nombre_tipo_id'] ?? ''),
                '{proveedor_email}'           => (string) ($cabecera['proveedor_email'] ?? ''),
                '{compra_tipo_comprobante}'   => (string) ($cabecera['tipo_comprobante'] ?? ''),
                '{compra_numero_autorizacion}'=> (string) ($cabecera['numero_autorizacion'] ?? ''),
                '{compra_fecha_autorizacion}' => $fmtFecha($cabecera['fecha_autorizacion'] ?? ''),
                '{compra_numero_prov}'        => trim(
                    (string) ($cabecera['establecimiento_prov'] ?? '') . '-' .
                    (string) ($cabecera['punto_emision_prov'] ?? '') . '-' .
                    (string) ($cabecera['secuencial_prov'] ?? ''),
                    '-'
                ),
                '{titulo_documento}' => $tipo === 'compras' ? 'Compra' : 'Liquidación de Compra',
            ],
            'egreso', 'ingreso', 'traspaso' => [
                '{cc_recibo_de}'       => (string) ($cabecera['recibo_de'] ?? $cabecera['recibo_cliente_nombre'] ?? $cabecera['cliente_nombre'] ?? ''),
                '{cc_sujeto_nombre}'   => (string) ($cabecera['sujeto_nombre'] ?? ''),
                '{cc_sujeto_ruc}'      => (string) ($cabecera['sujeto_ruc'] ?? ''),
                '{cc_origen_nombre}'   => (string) ($cabecera['origen_nombre'] ?? ''),
                '{cc_destino_nombre}'  => (string) ($cabecera['destino_nombre'] ?? ''),
                '{cc_numero}'          => (string) ($cabecera['numero_ingreso'] ?? $cabecera['numero_egreso'] ?? $cabecera['numero_traspaso'] ?? ''),
                '{cc_monto}'           => $fmtMonto($cabecera['monto'] ?? 0),
                '{cc_monto_total}'     => $fmtMonto($cabecera['monto_total'] ?? $cabecera['monto'] ?? 0),
                '{cc_monto_letras}'    => $this->montoEnLetras((float) ($cabecera['monto_total'] ?? $cabecera['monto'] ?? 0)),
                '{cc_usuario_nombre}'  => (string) ($cabecera['usuario_nombre'] ?? ''),
                '{cc_estado}'          => (string) ($cabecera['estado'] ?? ''),
                '{titulo_documento}'   => match ($tipo) { 'egreso' => 'Comprobante de Egreso', 'ingreso' => 'Comprobante de Ingreso', default => 'Comprobante de Traspaso' },
            ],
            'proforma' => [
                '{pf_dias_vigencia}'  => (string) ($cabecera['dias_vigencia'] ?? ''),
                '{pf_fecha_vigencia}' => $this->sumarDias($cabecera['fecha_emision'] ?? '', (int) ($cabecera['dias_vigencia'] ?? 0)),
                '{titulo_documento}'  => 'Proforma',
            ],
            'retorno_cv' => [
                '{rt_motivo}'               => (string) ($cabecera['motivo'] ?? ''),
                '{rt_fecha_retorno}'        => $fmtFecha($cabecera['fecha_retorno'] ?? ''),
                '{rt_usuario_nombre}'       => (string) ($cabecera['usuario_nombre'] ?? ''),
                '{rt_responsable_traslado}' => (string) ($cabecera['responsable_traslado_nombre'] ?? ''),
                '{titulo_documento}'        => 'Retorno de Consignación',
            ],
            'consignacion' => [
                '{cg_fecha_entrega}'        => $fmtFecha($cabecera['fecha_entrega'] ?? ''),
                '{cg_vendedor_nombre}'      => (string) ($cabecera['vendedor_nombre'] ?? ''),
                '{cg_responsable_traslado}' => (string) ($cabecera['responsable_traslado_nombre'] ?? ''),
                '{cg_punto_partida}'        => (string) ($cabecera['punto_partida'] ?? ''),
                '{cg_punto_llegada}'        => (string) ($cabecera['punto_llegada'] ?? ''),
                '{titulo_documento}'        => 'Consignación en Ventas',
            ],
            'cambio_producto_cv' => [
                '{cp_fecha_cambio}'        => $fmtFecha($cabecera['fecha_cambio'] ?? ''),
                '{cp_usuario_nombre}'      => (string) ($cabecera['usuario_nombre'] ?? ''),
                '{cp_motivo}'              => (string) ($cabecera['motivo'] ?? ''),
                '{cp_subtotal_devuelto}'   => $fmtMonto($cabecera['subtotal_devuelto'] ?? 0),
                '{cp_subtotal_entregado}'  => $fmtMonto($cabecera['subtotal_entregado'] ?? 0),
                '{cp_diferencia}'          => $fmtMonto($cabecera['diferencia'] ?? 0),
                '{titulo_documento}'       => 'Cambio de Productos',
            ],
            'retencion_compra', 'retencion_venta' => [
                '{ret_sujeto_nombre}'         => (string) ($cabecera['proveedor_razon_social'] ?? $cabecera['cliente_nombre'] ?? ''),
                '{ret_sujeto_identificacion}' => (string) ($cabecera['proveedor_identificacion'] ?? $cabecera['cliente_identificacion'] ?? ''),
                '{ret_periodo_fiscal}'        => (string) ($cabecera['periodo_fiscal'] ?? ''),
                '{ret_tipo_doc_sustento}'     => match ((string) ($cabecera['tipo_doc_sustento'] ?? '01')) {
                    '01' => 'Factura', '03' => 'Liquidación de Compra', '05' => 'Nota de Débito',
                    default => (string) ($cabecera['tipo_doc_sustento'] ?? ''),
                },
                '{ret_num_doc_sustento}'    => (string) ($cabecera['num_doc_sustento'] ?? ''),
                '{ret_fecha_doc_sustento}'  => $fmtFecha($cabecera['fecha_emision_doc_sustento'] ?? ''),
                '{titulo_documento}'        => 'Comprobante de Retención',
            ],
            'consignacion_factura' => [
                '{cf_vendedor_nombre}' => (string) ($cabecera['vendedor_nombre'] ?? ''),
                '{cf_factura_origen}'  => (string) ($cabecera['numero_factura'] ?? ''),
                '{titulo_documento}'   => 'Facturación de Consignación',
            ],
            'recibo_venta' => [
                '{rv_placa}'          => (string) ($cabecera['placa'] ?? ''),
                '{rv_monto_letras}'   => (string) ($cabecera['monto_letras'] ?? $cabecera['valor_letras'] ?? $this->montoEnLetras((float) ($cabecera['importe_total'] ?? 0))),
                '{rv_con_impuestos}'  => !empty($cabecera['con_impuestos']) ? 'SI' : 'NO',
                '{titulo_documento}'  => 'Recibo de Venta',
            ],
            default => ['{titulo_documento}' => 'Documento'],
        };
    }

    /** Fecha + N días (para vigencia de proforma). Vacío si no hay fecha o días. */
    private function sumarDias($fecha, int $dias): string
    {
        if (empty($fecha) || $dias <= 0) return '';
        $ts = strtotime((string) $fecha);
        if (!$ts) return '';
        return date('d/m/Y', strtotime("+{$dias} days", $ts));
    }

    /** Monto en letras genérico (reutiliza el mismo validador que usan cheques). */
    private function montoEnLetras(float $monto): string
    {
        require_once \MVC_ROOT . '/app/validadores/numero_letras.php';
        if (function_exists('num_letras')) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) num_letras(number_format($monto, 2, '.', '')))));
        }
        return number_format($monto, 2);
    }

    /**
     * Mapa placeholder→valor para un cheque. Recibe una fila con:
     *  monto, numero_cheque, fecha_cheque, beneficiario, beneficiario_ident,
     *  banco_nombre, cuenta_numero, numero_egreso, observaciones, ciudad.
     */
    private function construirDatosCheque(array $c, array $empresa): array
    {
        $monto  = (float)($c['monto'] ?? 0);
        $letras = $this->montoEnLetrasCheque($monto);
        $montoFmt = number_format($monto, 2);

        $fechaTs = !empty($c['fecha_cheque']) ? strtotime((string) $c['fecha_cheque']) : false;
        $meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
                  7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
        $fechaCorta = $fechaTs ? date('d/m/Y', $fechaTs) : '';
        $fechaIso   = $fechaTs ? date('Y-m-d', $fechaTs) : '';
        $fechaLarga = $fechaTs ? (date('j', $fechaTs) . ' de ' . $meses[(int)date('n', $fechaTs)] . ' de ' . date('Y', $fechaTs)) : '';

        $ciudad = trim((string)($c['ciudad'] ?? $empresa['ciudad'] ?? ''));
        // Línea "CIUDAD, 2026-07-30" (estándar del cheque en Ecuador).
        $ciudadFecha = trim($ciudad . ($ciudad !== '' && $fechaIso !== '' ? ', ' : '') . $fechaIso);

        // En el cheque todo el texto va en MAYÚSCULAS.
        $up = fn($s) => mb_strtoupper((string) $s, 'UTF-8');

        return [
            '{beneficiario}'           => $up($c['beneficiario'] ?? ''),
            '{beneficiario_ident}'     => (string)($c['beneficiario_ident'] ?? ''),
            '{monto_numero}'           => $montoFmt,
            '{monto_numero_protegido}' => '***' . $montoFmt . '***',
            '{monto_letras}'           => $letras,
            '{fecha_cheque}'           => $fechaCorta,
            '{fecha_iso}'              => $fechaIso,
            '{fecha_larga}'            => $up($fechaLarga),
            '{ciudad}'                 => $up($ciudad),
            '{ciudad_fecha}'           => $up($ciudadFecha),
            '{dia}'                    => $fechaTs ? date('d', $fechaTs) : '',
            '{mes}'                    => $fechaTs ? date('m', $fechaTs) : '',
            '{anio}'                   => $fechaTs ? date('Y', $fechaTs) : '',
            '{numero_cheque}'          => (string)($c['numero_cheque'] ?? ''),
            '{concepto}'               => $up($c['observaciones'] ?? ''),
            '{numero_egreso}'          => (string)($c['numero_egreso'] ?? ''),
            '{banco_nombre}'           => $up($c['banco_nombre'] ?? ''),
            '{cuenta_numero}'          => (string)($c['cuenta_numero'] ?? ''),
            '{empresa_nombre}'         => $up($empresa['nombre'] ?? ''),
            '{empresa_ruc}'            => (string)($empresa['ruc'] ?? ''),
            '{empresa_logo}'           => (string)($empresa['logo'] ?? $empresa['logo_ruta'] ?? ''),
        ];
    }

    /** Monto en letras para cheques (mayúsculas, reutiliza el validador global). */
    private function montoEnLetrasCheque(float $monto): string
    {
        require_once \MVC_ROOT . '/app/validadores/numero_letras.php';
        if (function_exists('num_letras')) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) num_letras(number_format($monto, 2, '.', '')))));
        }
        return number_format($monto, 2);
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

        // Conciliar el IVA con el total del comprobante para que la plantilla cuadre EXACTO:
        // {valor_total} sale de importe_total (lo autorizado por el SRI), pero {iva} se sumó por
        // línea; si la empresa calcula el IVA sobre el subtotal difieren ±1 centavo. Se ajusta el
        // IVA para que subtotales + IVA = valor_total (misma lógica que el PDF/XML de factura).
        if (isset($cabecera['importe_total'])) {
            $ivaObjetivo = round($valorTotal - $subtotal0 - $subtotalIva - $totalIce - $propina, 2);
            $desfase     = round($ivaObjetivo - $totalIva, 2);
            if (abs($desfase) >= 0.01 && abs($desfase) <= 0.05) {
                $totalIva = round($totalIva + $desfase, 2);
            }
        }

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
