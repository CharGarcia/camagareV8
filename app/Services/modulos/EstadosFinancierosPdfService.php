<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF de los Estados Financieros (Estado de Resultados y Estado de Situación Financiera),
 * tanto de un solo periodo (A4 vertical) como el comparativo por meses (A4 horizontal).
 *
 * Cabecera en todas las páginas con el logo y los datos de la empresa, filas jerárquicas
 * (sangría y peso tipográfico por nivel de cuenta), filas de totales resaltadas, pie con
 * fecha de emisión y "Página N de M", y al final las firmas del Representante Legal y del
 * Contador con nombre y RUC/C.I. tomados de la configuración de la empresa.
 */
class EstadosFinancierosPdfService
{
    private const AZUL       = [31, 56, 100];
    private const AZUL_SUAVE = [226, 232, 242];
    private const GRIS_FILA  = [244, 246, 249];
    private const GRIS_LINEA = [205, 210, 218];
    private const GRIS_TEXTO = [105, 110, 120];
    private const ROJO       = [176, 35, 35];
    private const VERDE      = [22, 110, 60];

    private TCPDF $pdf;
    private array $empresa = [];
    private string $titulo = '';
    private string $subtitulo = '';
    private string $lineaFiltros = '';
    private string $logo = '';
    private float $mL = 10;
    private float $mR = 10;
    private float $mTop = 42;
    private float $mBottom = 18;
    private float $contentW = 186;
    private int $numCols = 3;

    /**
     * Escalonado de valores por nivel: el nivel 1 y los totales van pegados al borde derecho
     * y cada nivel más profundo se desplaza $pasoNivel mm a la izquierda (formato contable).
     */
    private float $pasoNivel = 0.0;
    private int $nivelMax = 1;

    /** Anchos vigentes de la tabla (mm) y etiquetas de columnas de periodo. */
    private array $anchos = [];
    private array $cabeceraCols = [];

    /**
     * @param string $tipo    resultados | situacion | resultados_periodos | situacion_periodos
     * @param array  $datos   Salida de EstadosFinancierosService::getEstado*()
     * @param array  $empresa Fila completa de `empresas`
     * @param array  $filtros fecha_inicio, fecha_fin, nivel, centro_costo (nombre|null), proyecto (nombre|null)
     */
    public function exportar(string $tipo, array $datos, array $empresa, array $filtros): void
    {
        $this->empresa = $empresa;
        $porPeriodos = str_ends_with($tipo, '_periodos');
        $esResultados = str_starts_with($tipo, 'resultados');

        $fi = $this->fecha($filtros['fecha_inicio'] ?? '');
        $ff = $this->fecha($filtros['fecha_fin'] ?? '');

        $this->titulo = $esResultados ? 'ESTADO DE RESULTADOS' : 'ESTADO DE SITUACIÓN FINANCIERA';
        if ($porPeriodos) {
            $this->titulo .= ' POR PERIODOS';
            $this->subtitulo = 'Comparativo mensual del ' . $fi . ' al ' . $ff;
        } elseif ($esResultados) {
            $this->subtitulo = 'Del ' . $fi . ' al ' . $ff;
        } else {
            $this->subtitulo = 'Al ' . $ff;
        }

        $partes = [];
        if (!$porPeriodos && !$esResultados) {
            $partes[] = 'Período de cálculo: ' . $fi . ' al ' . $ff;
        }
        $partes[] = 'Nivel de detalle: hasta nivel ' . (int)($filtros['nivel'] ?? 5);
        $partes[] = 'Centro de costo: ' . ($filtros['centro_costo'] ?: 'Todos');
        $partes[] = 'Proyecto: ' . ($filtros['proyecto'] ?: 'Todos');
        $this->lineaFiltros = implode('   ·   ', $partes);

        $this->logo = $this->resolverLogo($empresa);

        $this->crearDocumento($porPeriodos ? 'L' : 'P');

        if ($porPeriodos) {
            $this->cuerpoPorPeriodos($esResultados, $datos);
        } else {
            $this->cuerpoSimple($esResultados, $datos);
        }

        $this->dibujarFirmas();

        $nombreArchivo = str_replace(' ', '_', ucwords(mb_strtolower($this->titulo))) . '_' . date('YmdHis') . '.pdf';
        if (ob_get_length()) {
            ob_end_clean();
        }
        $this->pdf->Output($nombreArchivo, 'D');
        exit;
    }

    // ───────────────────────────── Documento, cabecera y pie ─────────────────────────────

    private function crearDocumento(string $orientacion): void
    {
        $svc = $this;
        $this->pdf = new class($orientacion) extends TCPDF {
            /** @var callable|null */
            public $onHeader = null;
            /** @var callable|null */
            public $onFooter = null;

            public function __construct(string $orientacion)
            {
                parent::__construct($orientacion, 'mm', 'A4', true, 'UTF-8', false);
            }

            public function Header(): void
            {
                if ($this->onHeader) {
                    ($this->onHeader)($this);
                }
            }

            public function Footer(): void
            {
                if ($this->onFooter) {
                    ($this->onFooter)($this);
                }
            }
        };

        $this->contentW = ($orientacion === 'L' ? 297 : 210) - $this->mL - $this->mR;

        $this->pdf->SetCreator('Sistema Contable');
        $this->pdf->SetAuthor((string)($this->empresa['nombre'] ?? ''));
        $this->pdf->SetTitle(ucwords(mb_strtolower($this->titulo)));
        $this->pdf->SetMargins($this->mL, $this->mTop, $this->mR);
        $this->pdf->SetAutoPageBreak(true, $this->mBottom);
        $this->pdf->setPrintHeader(true);
        $this->pdf->setPrintFooter(true);
        $this->pdf->onHeader = function (TCPDF $pdf) use ($svc): void {
            $svc->dibujarCabecera($pdf);
        };
        $this->pdf->onFooter = function (TCPDF $pdf) use ($svc): void {
            $svc->dibujarPie($pdf);
        };
        $this->pdf->AddPage();
    }

    /** Cabecera de cada página: logo + datos de la empresa a la izquierda, título y periodo a la derecha. */
    private function dibujarCabecera(TCPDF $pdf): void
    {
        $y0 = 8.0;
        $xTexto = $this->mL;

        if ($this->logo !== '') {
            try {
                $ext = strtolower(pathinfo($this->logo, PATHINFO_EXTENSION));
                if ($ext === 'svg') {
                    $pdf->ImageSVG($this->logo, $this->mL, $y0, 34, 20, '', 'T', '', 0, false);
                } else {
                    $pdf->Image($this->logo, $this->mL, $y0, 34, 20, '', '', 'T', false, 300, '', false, false, 0, 'LM');
                }
                $xTexto = $this->mL + 38;
            } catch (\Throwable $e) {
                $xTexto = $this->mL;
            }
        }

        // En vertical el espacio es justo: el bloque derecho se acota y el izquierdo puede
        // usar dos líneas para el nombre y la dirección. En horizontal sobra ancho.
        $anchoDerecha = $this->contentW > 200 ? 130.0 : 80.0;
        $anchoIzq = $this->contentW - ($xTexto - $this->mL) - $anchoDerecha - 3;

        // Bloque izquierdo: nombre (hasta 2 líneas), RUC + nombre comercial, dirección (hasta 2 líneas), contacto
        $pdf->SetXY($xTexto, $y0);
        $pdf->SetTextColor(...self::AZUL);
        $nombre = mb_strtoupper((string)($this->empresa['nombre'] ?: ($this->empresa['nombre_comercial'] ?? '')));
        // Hasta dos líneas: si no cabe, se reduce la fuente en vez de recortar la razón social
        $tamano = 11.0;
        $pdf->SetFont('helvetica', 'B', $tamano);
        while ($tamano > 8.5 && $pdf->getNumLines($nombre, $anchoIzq) > 2) {
            $tamano -= 0.5;
            $pdf->SetFont('helvetica', 'B', $tamano);
        }
        $pdf->MultiCell($anchoIzq, 4.8, $nombre, 0, 'L', false, 1);

        $pdf->SetTextColor(...self::GRIS_TEXTO);
        $pdf->SetFont('helvetica', '', 7.5);
        $lineaRuc = [];
        if (!empty($this->empresa['ruc'])) {
            $lineaRuc[] = 'RUC: ' . $this->empresa['ruc'];
        }
        $comercial = trim((string)($this->empresa['nombre_comercial'] ?? ''));
        if ($comercial !== '' && mb_strtoupper($comercial) !== mb_strtoupper($nombre)) {
            $lineaRuc[] = $comercial;
        }
        if ($lineaRuc) {
            $pdf->SetX($xTexto);
            $pdf->Cell($anchoIzq, 3.8, $this->ajustar($pdf, implode('  ·  ', $lineaRuc), $anchoIzq), 0, 1, 'L');
        }
        $direccion = trim((string)($this->empresa['direccion'] ?? ''));
        if ($direccion !== '') {
            $pdf->SetX($xTexto);
            if ($pdf->getNumLines($direccion, $anchoIzq) > 2) {
                // Direcciones muy largas: una sola línea recortada, para no empujar la tabla
                $pdf->Cell($anchoIzq, 3.8, $this->ajustar($pdf, $direccion, $anchoIzq), 0, 1, 'L');
            } else {
                $pdf->MultiCell($anchoIzq, 3.8, $direccion, 0, 'L', false, 1);
            }
        }
        $contacto = array_filter([
            trim((string)($this->empresa['telefono'] ?? '')) !== '' ? 'Tel.: ' . trim((string)$this->empresa['telefono']) : '',
            trim((string)($this->empresa['mail'] ?? '')),
        ]);
        if ($contacto) {
            $pdf->SetX($xTexto);
            $pdf->Cell($anchoIzq, 3.8, $this->ajustar($pdf, implode('   ', $contacto), $anchoIzq), 0, 1, 'L');
        }

        // Bloque derecho: título del estado, periodo y moneda
        $xDer = $this->mL + $this->contentW - $anchoDerecha;
        $pdf->SetXY($xDer, $y0);
        $pdf->SetTextColor(...self::AZUL);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->MultiCell($anchoDerecha, 5.5, $this->titulo, 0, 'R', false, 1);
        $pdf->SetX($xDer);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($anchoDerecha, 4.5, $this->subtitulo, 0, 1, 'R');
        $pdf->SetX($xDer);
        $pdf->SetTextColor(...self::GRIS_TEXTO);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->Cell($anchoDerecha, 4, 'Expresado en dólares de los Estados Unidos de América', 0, 1, 'R');

        // Línea divisoria y filtros aplicados
        $yLinea = 34.0;
        $pdf->SetDrawColor(...self::AZUL);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($this->mL, $yLinea, $this->mL + $this->contentW, $yLinea);
        $pdf->SetLineWidth(0.2);

        $pdf->SetXY($this->mL, $yLinea + 1.2);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(...self::GRIS_TEXTO);
        $pdf->Cell($this->contentW, 4, $this->lineaFiltros, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
    }

    /** Pie de cada página: fecha de emisión y número de página. */
    private function dibujarPie(TCPDF $pdf): void
    {
        $pdf->SetY(-13);
        $pdf->SetDrawColor(...self::GRIS_LINEA);
        $pdf->Line($this->mL, $pdf->GetY(), $this->mL + $this->contentW, $pdf->GetY());
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(...self::GRIS_TEXTO);
        $mitad = $this->contentW / 2;
        $pdf->Cell($mitad, 4, 'Emitido el ' . date('d-m-Y H:i:s'), 0, 0, 'L');
        $pdf->Cell($mitad, 4, 'Página ' . $pdf->getAliasNumPage() . ' de ' . $pdf->getAliasNbPages(), 0, 0, 'R');
        $pdf->SetTextColor(0, 0, 0);
    }

    // ───────────────────────────── Cuerpo: un solo periodo ─────────────────────────────

    private function cuerpoSimple(bool $esResultados, array $datos): void
    {
        // Código angosto, valores escalonados por nivel (6 mm por nivel presente) y el resto
        // del ancho para el nombre de la cuenta.
        $this->nivelMax = $this->nivelMaximo($datos);
        $this->pasoNivel = 6.0;
        $anchoCodigo = 20.0;
        $anchoValor = 34.0 + $this->pasoNivel * ($this->nivelMax - 1);
        $this->anchos = [$anchoCodigo, $this->contentW - $anchoCodigo - $anchoValor, $anchoValor];
        $this->cabeceraCols = ['Código', 'Cuenta', 'Saldo'];
        $this->numCols = 3;
        $this->dibujarCabeceraTabla();

        $t = $datos['totales'];
        if ($esResultados) {
            $this->seccion('INGRESOS');
            foreach ($datos['ingresos'] as $item) {
                $this->filaCuenta($item, [(float)$item['saldo_final']]);
            }
            $this->filaTotal('TOTAL INGRESOS', [(float)$t['ingresos']]);

            $this->seccion('COSTOS');
            foreach ($datos['costos'] as $item) {
                $this->filaCuenta($item, [(float)$item['saldo_final']]);
            }
            $this->filaTotal('TOTAL COSTOS', [(float)$t['costos']]);
            $this->filaTotal((float)$t['utilidad_bruta'] >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA', [(float)$t['utilidad_bruta']], true, true);

            $this->seccion('GASTOS');
            foreach ($datos['gastos'] as $item) {
                $this->filaCuenta($item, [(float)$item['saldo_final']]);
            }
            $this->filaTotal('TOTAL GASTOS', [(float)$t['gastos']]);
            $this->filaTotal((float)$t['utilidad_neta'] >= 0 ? 'UTILIDAD DEL EJERCICIO' : 'PÉRDIDA DEL EJERCICIO', [(float)$t['utilidad_neta']], true, true);
        } else {
            $this->seccion('ACTIVOS');
            foreach ($datos['activos'] as $item) {
                $this->filaCuenta($item, [(float)$item['saldo_final']]);
            }
            $this->filaTotal('TOTAL ACTIVOS', [(float)$t['activos']], true);

            $this->seccion('PASIVOS');
            foreach ($datos['pasivos'] as $item) {
                $this->filaCuenta($item, [(float)$item['saldo_final']]);
            }
            $this->filaTotal('TOTAL PASIVOS', [(float)$t['pasivos']]);

            $this->seccion('PATRIMONIO');
            foreach ($datos['patrimonio'] as $item) {
                $this->filaCuenta($item, [(float)$item['saldo_final']]);
            }
            $this->filaTotal('TOTAL PATRIMONIO', [(float)$t['patrimonio']]);
            $this->filaTotal('TOTAL PASIVO + PATRIMONIO', [(float)$t['pasivo_patrimonio']], true);

            $this->filaDiferencia((float)$t['activos'] - (float)$t['pasivo_patrimonio']);
        }
    }

    // ───────────────────────────── Cuerpo: comparativo por periodos ─────────────────────────────

    private function cuerpoPorPeriodos(bool $esResultados, array $datos): void
    {
        $claves = array_keys($datos['periodos']);
        $labels = array_values($datos['periodos']);
        if ($esResultados) {
            $labels[] = 'Total';
        }
        $n = max(1, count($labels));

        $anchoCodigo = 18.0;
        $anchoPeriodo = max(14.0, min(26.0, ($this->contentW - $anchoCodigo - 70) / $n));
        $anchoCuenta = $this->contentW - $anchoCodigo - $anchoPeriodo * $n;
        if ($anchoCuenta < 40) {
            $anchoCuenta = 40.0;
            $anchoPeriodo = ($this->contentW - $anchoCodigo - $anchoCuenta) / $n;
        }

        // Escalonado por nivel solo si la columna de mes deja espacio (con 12 meses no lo hay)
        $this->nivelMax = $this->nivelMaximo($datos);
        $paso = ($anchoPeriodo - 20.0) / max(1, $this->nivelMax - 1);
        $this->pasoNivel = $paso >= 1.5 ? min(3.0, $paso) : 0.0;

        $this->anchos = array_merge([$anchoCodigo, $anchoCuenta], array_fill(0, $n, $anchoPeriodo));
        $this->cabeceraCols = array_merge(['Código', 'Cuenta'], $labels);
        $this->numCols = 2 + $n;
        $this->dibujarCabeceraTabla();

        $valoresItem = function (array $item) use ($claves, $esResultados): array {
            $v = [];
            foreach ($claves as $p) {
                $v[] = (float)($item['valores'][$p] ?? 0);
            }
            if ($esResultados) {
                $v[] = (float)($item['total'] ?? array_sum($item['valores'] ?? []));
            }
            return $v;
        };
        $valoresTotal = function (array $porPeriodo) use ($claves, $esResultados): array {
            $v = [];
            foreach ($claves as $p) {
                $v[] = (float)($porPeriodo[$p] ?? 0);
            }
            if ($esResultados) {
                $v[] = (float)($porPeriodo['total'] ?? array_sum(array_intersect_key($porPeriodo, array_flip($claves))));
            }
            return $v;
        };

        $t = $datos['totales'];
        if ($esResultados) {
            $this->seccion('INGRESOS');
            foreach ($datos['ingresos'] as $item) {
                $this->filaCuenta($item, $valoresItem($item));
            }
            $this->filaTotal('TOTAL INGRESOS', $valoresTotal($t['ingresos']));

            $this->seccion('COSTOS');
            foreach ($datos['costos'] as $item) {
                $this->filaCuenta($item, $valoresItem($item));
            }
            $this->filaTotal('TOTAL COSTOS', $valoresTotal($t['costos']));
            $this->filaTotal('UTILIDAD / PÉRDIDA BRUTA', $valoresTotal($t['utilidad_bruta']), true, true);

            $this->seccion('GASTOS');
            foreach ($datos['gastos'] as $item) {
                $this->filaCuenta($item, $valoresItem($item));
            }
            $this->filaTotal('TOTAL GASTOS', $valoresTotal($t['gastos']));
            $this->filaTotal('UTILIDAD / PÉRDIDA DEL EJERCICIO', $valoresTotal($t['utilidad_neta']), true, true);
        } else {
            $this->seccion('ACTIVOS');
            foreach ($datos['activos'] as $item) {
                $this->filaCuenta($item, $valoresItem($item));
            }
            $this->filaTotal('TOTAL ACTIVOS', $valoresTotal($t['activos']), true);

            $this->seccion('PASIVOS');
            foreach ($datos['pasivos'] as $item) {
                $this->filaCuenta($item, $valoresItem($item));
            }
            $this->filaTotal('TOTAL PASIVOS', $valoresTotal($t['pasivos']));

            $this->seccion('PATRIMONIO');
            foreach ($datos['patrimonio'] as $item) {
                $this->filaCuenta($item, $valoresItem($item));
            }
            $this->filaTotal('TOTAL PATRIMONIO', $valoresTotal($t['patrimonio']));
            $this->filaTotal('TOTAL PASIVO + PATRIMONIO', $valoresTotal($t['pasivo_patrimonio']), true);
        }
    }

    // ───────────────────────────── Filas de la tabla ─────────────────────────────

    private function fuenteValores(): float
    {
        $ancho = $this->anchos[2] ?? 38;
        if ($ancho < 16) {
            return 6.0;
        }
        if ($ancho < 20) {
            return 6.8;
        }
        return 8.0;
    }

    private function dibujarCabeceraTabla(): void
    {
        $pdf = $this->pdf;
        $pdf->SetX($this->mL);
        $pdf->SetFillColor(...self::AZUL);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(...self::AZUL);
        $pdf->SetFont('helvetica', 'B', min(8.0, $this->fuenteValores() + 0.5));
        $h = 6.5;

        // Columnas de mes estrechas: si algún nombre de mes no cabe, se abrevian TODOS
        // ("Septiembre 2025" → "Sep 2025") para que la fila quede uniforme.
        $abreviar = false;
        foreach ($this->cabeceraCols as $i => $titulo) {
            if ($i >= 2 && $pdf->GetStringWidth($titulo) > $this->anchos[$i] - 2) {
                $abreviar = true;
                break;
            }
        }

        foreach ($this->cabeceraCols as $i => $titulo) {
            $align = $i < 2 ? 'L' : 'R';
            $ancho = $this->anchos[$i] - 2;
            if ($i >= 2 && $abreviar) {
                $titulo = $this->abreviarMes($titulo);
            }
            $pdf->Cell($this->anchos[$i], $h, $this->ajustar($pdf, $titulo, $ancho), 0, 0, $align, true);
        }
        $pdf->Ln($h);
        $pdf->SetTextColor(0, 0, 0);
    }

    /** Salta de página (con cabecera de tabla repetida) si la fila no cabe. */
    private function asegurarEspacio(float $h): void
    {
        $pdf = $this->pdf;
        $limite = $pdf->getPageHeight() - $this->mBottom;
        if ($pdf->GetY() + $h > $limite) {
            $pdf->AddPage();
            $this->dibujarCabeceraTabla();
        }
    }

    private function seccion(string $titulo): void
    {
        $h = 6.0;
        $this->asegurarEspacio($h + 6);
        $pdf = $this->pdf;
        $pdf->SetX($this->mL);
        $pdf->SetFillColor(...self::AZUL_SUAVE);
        $pdf->SetTextColor(...self::AZUL);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($this->contentW, $h, $titulo, 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
    }

    private function filaCuenta(array $item, array $valores): void
    {
        $nivel = max(1, (int)($item['nivel'] ?? 5));
        $h = 5.2;
        $this->asegurarEspacio($h);

        $pdf = $this->pdf;
        $pdf->SetX($this->mL);
        $pdf->SetDrawColor(...self::GRIS_LINEA);

        $fill = false;
        if ($nivel === 1) {
            $pdf->SetFont('helvetica', 'B', 8.5);
            $pdf->SetTextColor(...self::AZUL);
            $pdf->SetFillColor(...self::GRIS_FILA);
            $fill = true;
        } elseif ($nivel === 2) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(30, 30, 30);
        } elseif ($nivel === 3) {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(30, 30, 30);
        } else {
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(70, 70, 70);
        }

        $sangria = 2.5 * ($nivel - 1);
        $codigo = (string)($item['codigo'] ?? '');
        $nombre = (string)($item['nombre'] ?? '');
        if ($nivel === 1) {
            $nombre = mb_strtoupper($nombre);
        }

        $pdf->Cell($this->anchos[0], $h, $codigo, 'B', 0, 'L', $fill);
        if ($sangria > 0) {
            // En TCPDF un Cell de ancho 0 se extiende hasta el margen derecho: solo dibujar si hay sangría
            $pdf->Cell($sangria, $h, '', 'B', 0, 'L', $fill);
        }
        $pdf->Cell($this->anchos[1] - $sangria, $h, $this->ajustar($pdf, $nombre, $this->anchos[1] - $sangria - 2), 'B', 0, 'L', $fill);

        $fuenteValor = $this->fuenteValores();
        $pdf->SetFont('helvetica', $nivel <= 2 ? 'B' : '', $nivel <= 2 ? $fuenteValor : max(6.0, $fuenteValor - 0.5));
        // Valor escalonado: desplazado a la izquierda según el nivel (nivel 1 = borde derecho)
        $desplazamiento = $this->pasoNivel * ($nivel - 1);
        foreach ($valores as $i => $v) {
            $w = $this->anchos[2 + $i];
            $d = min($desplazamiento, max(0.0, $w - 12.0));
            $pdf->Cell($w - $d, $h, $this->dinero($v), 'B', 0, 'R', $fill);
            if ($d > 0) {
                $pdf->Cell($d, $h, '', 'B', 0, 'L', $fill);
            }
        }
        $pdf->Ln($h);
        $pdf->SetTextColor(0, 0, 0);
    }

    /** Nivel más profundo presente en las cuentas del reporte (define cuántos escalones dibujar). */
    private function nivelMaximo(array $datos): int
    {
        $max = 1;
        foreach (['ingresos', 'costos', 'gastos', 'activos', 'pasivos', 'patrimonio'] as $grupo) {
            foreach ($datos[$grupo] ?? [] as $item) {
                $max = max($max, (int)($item['nivel'] ?? 1));
            }
        }
        return min(5, $max);
    }

    /**
     * Fila de total. $destacada dibuja borde superior grueso y fondo azul suave;
     * $conSigno colorea el valor en verde/rojo según sea utilidad o pérdida.
     */
    private function filaTotal(string $titulo, array $valores, bool $destacada = false, bool $conSigno = false): void
    {
        $h = $destacada ? 6.5 : 5.8;
        $this->asegurarEspacio($h);

        $pdf = $this->pdf;
        $pdf->SetX($this->mL);
        $pdf->SetFont('helvetica', 'B', $destacada ? 9 : 8.5);
        $pdf->SetDrawColor(...self::AZUL);
        $pdf->SetLineWidth($destacada ? 0.5 : 0.25);
        $pdf->SetFillColor(...($destacada ? self::AZUL_SUAVE : self::GRIS_FILA));
        $pdf->SetTextColor(...self::AZUL);

        $anchoLabel = $this->anchos[0] + $this->anchos[1];
        $pdf->Cell($anchoLabel, $h, $titulo, 'T', 0, 'R', true);

        $fuenteValor = $this->fuenteValores();
        $pdf->SetFont('helvetica', 'B', $destacada ? $fuenteValor + 0.5 : $fuenteValor);
        foreach ($valores as $i => $v) {
            if ($conSigno) {
                $pdf->SetTextColor(...($v < 0 ? self::ROJO : self::VERDE));
            } else {
                $pdf->SetTextColor(...self::AZUL);
            }
            $pdf->Cell($this->anchos[2 + $i], $h, $this->dinero($v), 'T', 0, 'R', true);
        }
        $pdf->Ln($h);

        if ($destacada) {
            // Doble línea inferior, estilo contable
            $y = $pdf->GetY();
            $pdf->Line($this->mL, $y, $this->mL + $this->contentW, $y);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($this->mL, $y + 0.8, $this->mL + $this->contentW, $y + 0.8);
            $pdf->Ln(1.5);
        }
        $pdf->SetLineWidth(0.2);
        $pdf->SetTextColor(0, 0, 0);
    }

    /** Aviso de descuadre Activo vs. Pasivo + Patrimonio (solo si la diferencia no es cero). */
    private function filaDiferencia(float $diferencia): void
    {
        if (round($diferencia, 2) == 0) {
            return;
        }
        $this->asegurarEspacio(6);
        $pdf = $this->pdf;
        $pdf->Ln(1);
        $pdf->SetX($this->mL);
        $pdf->SetFont('helvetica', 'BI', 7.5);
        $pdf->SetTextColor(...self::ROJO);
        $pdf->Cell($this->anchos[0] + $this->anchos[1], 5, 'Diferencia (Activo − Pasivo y Patrimonio), el balance no cuadra:', 0, 0, 'R');
        $pdf->Cell($this->anchos[2], 5, $this->dinero($diferencia), 0, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);
    }

    // ───────────────────────────── Firmas ─────────────────────────────

    private function dibujarFirmas(): void
    {
        $pdf = $this->pdf;
        // El bloque ocupa 28 mm (línea a +14, nombre/identificación/cargo hasta +28) más 14 mm
        // de separación con la tabla. Si no cabe en lo que queda de página, va a una nueva.
        $altoBloque = 28.0;
        $separacion = 14.0;
        $limite = $pdf->getPageHeight() - $this->mBottom;
        if ($pdf->GetY() + $separacion + $altoBloque > $limite) {
            $pdf->AddPage();
        }

        // Se ubica lo más abajo posible (pie de la página), sin invadir la tabla
        $y = max($pdf->GetY() + $separacion, $limite - $altoBloque - 4);
        $anchoFirma = 72.0;
        $espacio = ($this->contentW - 2 * $anchoFirma) / 3;
        $xRep = $this->mL + $espacio;
        $xCont = $this->mL + 2 * $espacio + $anchoFirma;

        $this->bloqueFirma(
            $xRep,
            $y,
            $anchoFirma,
            'REPRESENTANTE LEGAL',
            (string)($this->empresa['nom_rep_legal'] ?? ''),
            (string)($this->empresa['ced_rep_legal'] ?? ''),
            'C.I. / RUC'
        );
        $this->bloqueFirma(
            $xCont,
            $y,
            $anchoFirma,
            'CONTADOR',
            (string)($this->empresa['nombre_contador'] ?? ''),
            (string)($this->empresa['ruc_contador'] ?? ''),
            'RUC'
        );
        $pdf->SetY($y + $altoBloque);
    }

    private function bloqueFirma(float $x, float $y, float $w, string $cargo, string $nombre, string $identificacion, string $etiquetaId): void
    {
        $pdf = $this->pdf;
        $nombre = trim($nombre);
        $identificacion = trim($identificacion);

        $pdf->SetDrawColor(60, 60, 60);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($x, $y + 14, $x + $w, $y + 14);
        $pdf->SetLineWidth(0.2);

        $pdf->SetXY($x, $y + 15);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->Cell($w, 4.5, $this->ajustar($pdf, $nombre !== '' ? mb_strtoupper($nombre) : '', $w), 0, 1, 'C');

        $pdf->SetX($x);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell($w, 4.2, $identificacion !== '' ? $etiquetaId . ': ' . $identificacion : '', 0, 1, 'C');

        $pdf->SetX($x);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetTextColor(...self::AZUL);
        $pdf->Cell($w, 4.2, $cargo, 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

    // ───────────────────────────── Utilitarios ─────────────────────────────

    private function dinero(float $v): string
    {
        return number_format($v, 2, '.', ',');
    }

    /** "Septiembre 2025" → "Sep 2025" (etiquetas de EstadosFinancierosService::construirPeriodos). */
    private function abreviarMes(string $etiqueta): string
    {
        $meses = [
            'Enero' => 'Ene', 'Febrero' => 'Feb', 'Marzo' => 'Mar', 'Abril' => 'Abr', 'Mayo' => 'May', 'Junio' => 'Jun',
            'Julio' => 'Jul', 'Agosto' => 'Ago', 'Septiembre' => 'Sep', 'Octubre' => 'Oct', 'Noviembre' => 'Nov', 'Diciembre' => 'Dic',
        ];
        return strtr($etiqueta, $meses);
    }

    private function fecha(string $ymd): string
    {
        $ts = $ymd !== '' ? strtotime($ymd) : false;
        return $ts ? date('d-m-Y', $ts) : $ymd;
    }

    /** Recorta el texto con "…" para que quepa en el ancho dado con la fuente activa. */
    private function ajustar(TCPDF $pdf, string $texto, float $ancho): string
    {
        if ($texto === '' || $pdf->GetStringWidth($texto) <= $ancho) {
            return $texto;
        }
        $len = mb_strlen($texto);
        while ($len > 1) {
            $len--;
            $corto = rtrim(mb_substr($texto, 0, $len)) . '…';
            if ($pdf->GetStringWidth($corto) <= $ancho) {
                return $corto;
            }
        }
        return '…';
    }

    /** Ruta física del logo de la empresa (mismo criterio que los demás PDF del sistema). */
    private function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string)$ruta, '/');
            if (preg_match('#^https?://[^/]+/(.*)$#i', $clean, $m)) {
                $clean = $m[1];
            }
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
