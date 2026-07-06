<?php

declare(strict_types=1);

namespace App\Services\modulos;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Exporta a Excel el detalle de las transacciones que alimentan el ATS.
 *
 * Tres hojas:
 *   1. Compras      — un renglón por compra/liquidación, con los valores tal
 *                     como van al XML (bases, IVA, retenciones de IVA).
 *   2. Retenciones  — un renglón por línea de retención (Renta/IVA) atada a su
 *                     documento de compra.
 *   3. Resumen      — informante + totales para cuadrar con la declaración.
 *
 * Reutiliza AtsService::recopilar() para no duplicar la lógica de extracción.
 */
class AtsExcelService
{
    private const AZUL  = '0D6EFD';
    private const GRIS  = 'E9ECEF';
    private const MONEY = '#,##0.00';

    public function __construct(private AtsService $ats) {}

    /**
     * @return array{ok:bool, mensaje?:string, nombre?:string, ruta?:string}
     */
    public function generar(int $idEmpresa, string $mes, string $anio, bool $semestral): array
    {
        $datos = $this->ats->recopilar($idEmpresa, $mes, $anio, $semestral);
        if (!$datos['ok']) {
            return ['ok' => false, 'mensaje' => $datos['mensaje'] ?? 'No se pudo recopilar la información.'];
        }

        $book = new Spreadsheet();

        $hojaCompras = $book->getActiveSheet();
        $hojaCompras->setTitle('Compras');
        $this->llenarCompras($hojaCompras, $datos['documentos']);

        $hojaRet = $book->createSheet();
        $hojaRet->setTitle('Retenciones');
        $this->llenarRetenciones($hojaRet, $datos['retenciones']);

        $hojaVentas = $book->createSheet();
        $hojaVentas->setTitle('Ventas');
        $this->llenarVentas($hojaVentas, $datos['ventas'] ?? []);

        $hojaAnul = $book->createSheet();
        $hojaAnul->setTitle('Anulados');
        $this->llenarAnulados($hojaAnul, $datos['anulados'] ?? []);

        $hojaResumen = $book->createSheet();
        $hojaResumen->setTitle('Resumen');
        $this->llenarResumen($hojaResumen, $datos);

        $book->setActiveSheetIndex(0);

        $nombre = 'AT' . $datos['mes'] . $datos['anio'] . '_detalle.xlsx';
        $ruta   = $this->ats->dirArchivos($idEmpresa) . '/' . $nombre;

        (new Xlsx($book))->save($ruta);
        $book->disconnectWorksheets();

        return ['ok' => true, 'nombre' => $nombre, 'ruta' => $ruta];
    }

    // ── Hoja Compras ─────────────────────────────────────────────────────────

    private function llenarCompras(Worksheet $h, array $documentos): void
    {
        $cols = [
            '#', 'Origen', 'Sustento', 'Tipo Comp.', 'Tipo ID', 'Identificación', 'Proveedor',
            'Parte Rel.', 'Serie (Est-Pto-Sec)', 'Fecha Emisión', 'Fecha Registro', 'Autorización',
            'Base No Obj. IVA', 'Base 0%', 'Base Gravada', 'Base Exenta', 'Monto ICE', 'Monto IVA',
            'Ret. IVA 10%', 'Ret. IVA 20%', 'Ret. IVA 30%', 'Ret. IVA 50%', 'Ret. IVA 70%', 'Ret. IVA 100%',
            'Importe Total',
        ];
        $this->cabecera($h, $cols);

        $r = 2;
        $i = 0;
        foreach ($documentos as $d) {
            $i++;
            $h->setCellValue("A{$r}", $i);
            $h->setCellValue("B{$r}", $d['_origen'] === 'liquidacion' ? 'Liquidación' : 'Compra');
            $this->texto($h, "C{$r}", $d['codSustento']);
            $this->texto($h, "D{$r}", $d['tipoComprobante']);
            $this->texto($h, "E{$r}", $d['tpIdProv']);
            $this->texto($h, "F{$r}", $d['idProv']);
            $h->setCellValue("G{$r}", $d['_proveedor']);
            $h->setCellValue("H{$r}", $d['parteRel']);
            $this->texto($h, "I{$r}", $d['establecimiento'] . '-' . $d['puntoEmision'] . '-' . $d['secuencial']);
            $h->setCellValue("J{$r}", $d['fechaEmision']);
            $h->setCellValue("K{$r}", $d['fechaRegistro']);
            $this->texto($h, "L{$r}", $d['autorizacion']);
            $this->dinero($h, "M{$r}", $d['baseNoGraIva']);
            $this->dinero($h, "N{$r}", $d['baseImponible']);
            $this->dinero($h, "O{$r}", $d['baseImpGrav']);
            $this->dinero($h, "P{$r}", $d['baseImpExe']);
            $this->dinero($h, "Q{$r}", $d['montoIce']);
            $this->dinero($h, "R{$r}", $d['montoIva']);
            $this->dinero($h, "S{$r}", $d['valRetBien10']);
            $this->dinero($h, "T{$r}", $d['valRetServ20']);
            $this->dinero($h, "U{$r}", $d['valorRetBienes']);
            $this->dinero($h, "V{$r}", $d['valRetServ50']);
            $this->dinero($h, "W{$r}", $d['valorRetServicios']);
            $this->dinero($h, "X{$r}", $d['valRetServ100']);
            $this->dinero($h, "Y{$r}", $d['_importeTotal']);
            $r++;
        }

        // Fila de totales
        if ($i > 0) {
            $fin = $r - 1;
            $h->setCellValue("A{$r}", 'TOTALES');
            foreach (['M','N','O','P','Q','R','S','T','U','V','W','X','Y'] as $c) {
                $h->setCellValue("{$c}{$r}", "=SUM({$c}2:{$c}{$fin})");
                $h->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode(self::MONEY);
            }
            $h->getStyle("A{$r}:Y{$r}")->getFont()->setBold(true);
            $h->getStyle("A{$r}:Y{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
        }

        $this->autosize($h, $cols);
        $h->freezePane('A2');
    }

    // ── Hoja Retenciones ─────────────────────────────────────────────────────

    private function llenarRetenciones(Worksheet $h, array $retenciones): void
    {
        $cols = [
            '#', 'Origen', 'Documento (Serie)', 'Proveedor', 'Comp. Retención (Serie)', 'Autorización Ret.',
            'Fecha Ret.', 'Impuesto', 'Código', 'Concepto', 'Base Imponible', '% Ret.', 'Valor Retenido',
        ];
        $this->cabecera($h, $cols);

        $r = 2;
        $i = 0;
        foreach ($retenciones as $x) {
            $i++;
            $h->setCellValue("A{$r}", $i);
            $h->setCellValue("B{$r}", $x['origen'] === 'liquidacion' ? 'Liquidación' : 'Compra');
            $this->texto($h, "C{$r}", $x['doc_serie']);
            $h->setCellValue("D{$r}", $x['doc_proveedor']);
            $this->texto($h, "E{$r}", $x['ret_serie']);
            $this->texto($h, "F{$r}", $x['ret_aut']);
            $h->setCellValue("G{$r}", $x['ret_fecha']);
            $h->setCellValue("H{$r}", $x['tipo_impuesto']);
            $this->texto($h, "I{$r}", $x['codigo']);
            $h->setCellValue("J{$r}", $x['concepto']);
            $this->dinero($h, "K{$r}", $x['base']);
            $this->dinero($h, "L{$r}", $x['porcentaje']);
            $this->dinero($h, "M{$r}", $x['valor']);
            $r++;
        }

        if ($i > 0) {
            $fin = $r - 1;
            $h->setCellValue("A{$r}", 'TOTALES');
            foreach (['K','M'] as $c) {
                $h->setCellValue("{$c}{$r}", "=SUM({$c}2:{$c}{$fin})");
                $h->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode(self::MONEY);
            }
            $h->getStyle("A{$r}:M{$r}")->getFont()->setBold(true);
            $h->getStyle("A{$r}:M{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
        } else {
            $h->setCellValue('A2', 'Sin retenciones en el período.');
        }

        $this->autosize($h, $cols);
        $h->freezePane('A2');
    }

    // ── Hoja Ventas ──────────────────────────────────────────────────────────

    private function llenarVentas(Worksheet $h, array $ventas): void
    {
        $cols = [
            '#', 'Tipo ID', 'Identificación', 'Cliente', 'Parte Rel.', 'Tipo Comp.', 'Emisión',
            'Nº Comprob.', 'Base No Obj. IVA', 'Base 0%', 'Base Gravada', 'Monto IVA', 'Monto ICE',
            'IVA Retenido', 'Renta Retenida',
        ];
        $this->cabecera($h, $cols);

        $r = 2;
        $i = 0;
        foreach ($ventas as $v) {
            $i++;
            $h->setCellValue("A{$r}", $i);
            $this->texto($h, "B{$r}", $v['tpIdCliente']);
            $this->texto($h, "C{$r}", $v['idCliente']);
            $h->setCellValue("D{$r}", $v['cliente'] ?? '');
            $h->setCellValue("E{$r}", $v['parteRel']);
            $this->texto($h, "F{$r}", $v['tipoComprobante']);
            $h->setCellValue("G{$r}", $v['tipoEm'] === 'E' ? 'Electrónica' : 'Física');
            $h->setCellValue("H{$r}", (int) $v['numeroComprobantes']);
            $this->dinero($h, "I{$r}", $v['baseNoGraIva']);
            $this->dinero($h, "J{$r}", $v['baseImponible']);
            $this->dinero($h, "K{$r}", $v['baseImpGrav']);
            $this->dinero($h, "L{$r}", $v['montoIva']);
            $this->dinero($h, "M{$r}", $v['montoIce']);
            $this->dinero($h, "N{$r}", $v['valorRetIva']);
            $this->dinero($h, "O{$r}", $v['valorRetRenta']);
            $r++;
        }
        if ($i > 0) {
            $fin = $r - 1;
            $h->setCellValue("A{$r}", 'TOTALES');
            foreach (['I','J','K','L','M','N','O'] as $c) {
                $h->setCellValue("{$c}{$r}", "=SUM({$c}2:{$c}{$fin})");
                $h->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode(self::MONEY);
            }
            $h->getStyle("A{$r}:O{$r}")->getFont()->setBold(true);
            $h->getStyle("A{$r}:O{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
        } else {
            $h->setCellValue('A2', 'Sin ventas autorizadas en el período.');
        }
        $this->autosize($h, $cols);
        $h->freezePane('A2');
    }

    // ── Hoja Anulados ────────────────────────────────────────────────────────

    private function llenarAnulados(Worksheet $h, array $anulados): void
    {
        $cols = ['#', 'Tipo Comp.', 'Establecimiento', 'Punto Emisión', 'Secuencial Desde', 'Secuencial Hasta', 'Autorización'];
        $this->cabecera($h, $cols);

        $r = 2;
        $i = 0;
        foreach ($anulados as $a) {
            $i++;
            $h->setCellValue("A{$r}", $i);
            $this->texto($h, "B{$r}", $a['tipoComprobante']);
            $this->texto($h, "C{$r}", $a['establecimiento']);
            $this->texto($h, "D{$r}", $a['puntoEmision']);
            $this->texto($h, "E{$r}", $a['secuencialInicio']);
            $this->texto($h, "F{$r}", $a['secuencialFin']);
            $this->texto($h, "G{$r}", $a['autorizacion']);
            $r++;
        }
        if ($i === 0) {
            $h->setCellValue('A2', 'Sin comprobantes anulados en el período.');
        }
        $this->autosize($h, $cols);
        $h->freezePane('A2');
    }

    // ── Hoja Resumen ─────────────────────────────────────────────────────────

    private function llenarResumen(Worksheet $h, array $datos): void
    {
        $inf = $datos['informante'];
        $docs = $datos['documentos'];
        $rets = $datos['retenciones'];

        $h->setCellValue('A1', 'ANEXO TRANSACCIONAL SIMPLIFICADO (ATS) — RESUMEN');
        $h->mergeCells('A1:C1');
        $h->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $info = [
            ['Informante (RUC)', $inf['id_informante']],
            ['Razón social', $inf['razon_social']],
            ['Período', $inf['mes'] . '/' . $inf['anio']],
            ['Régimen semestral (RIMPE)', !empty($inf['regimen_microempresa']) ? 'SI' : 'NO'],
            ['Nº establecimientos', $inf['num_estab_ruc']],
        ];
        $r = 3;
        foreach ($info as [$k, $v]) {
            $h->setCellValue("A{$r}", $k);
            $this->texto($h, "B{$r}", (string) $v);
            $h->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }

        // Totales de compras
        $sum = ['noGra' => 0.0, 'b0' => 0.0, 'grav' => 0.0, 'exe' => 0.0, 'ice' => 0.0, 'iva' => 0.0, 'retIva' => 0.0, 'total' => 0.0];
        $porTipo = [];
        foreach ($docs as $d) {
            $sum['noGra'] += (float) $d['baseNoGraIva'];
            $sum['b0']    += (float) $d['baseImponible'];
            $sum['grav']  += (float) $d['baseImpGrav'];
            $sum['exe']   += (float) $d['baseImpExe'];
            $sum['ice']   += (float) $d['montoIce'];
            $sum['iva']   += (float) $d['montoIva'];
            $sum['retIva'] += (float) $d['valRetBien10'] + (float) $d['valRetServ20'] + (float) $d['valorRetBienes']
                            + (float) $d['valRetServ50'] + (float) $d['valorRetServicios'] + (float) $d['valRetServ100'];
            $sum['total'] += (float) $d['_importeTotal'];
            $t = $d['tipoComprobante'];
            $porTipo[$t] = ($porTipo[$t] ?? 0) + 1;
        }
        $retRenta = 0.0;
        foreach ($rets as $x) {
            if ($x['tipo_impuesto'] === 'RENTA') {
                $retRenta += (float) $x['valor'];
            }
        }

        $r += 1;
        $h->setCellValue("A{$r}", 'TOTALES DE COMPRAS');
        $h->getStyle("A{$r}")->getFont()->setBold(true);
        $h->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
        $r++;

        $totales = [
            ['Nº de documentos (compras + liquidaciones)', count($docs), false],
            ['Base No Objeto de IVA', $sum['noGra'], true],
            ['Base Tarifa 0%', $sum['b0'], true],
            ['Base Gravada (tarifa ≠ 0%)', $sum['grav'], true],
            ['Base Exenta', $sum['exe'], true],
            ['Monto ICE', $sum['ice'], true],
            ['Monto IVA', $sum['iva'], true],
            ['Total Retención IVA', $sum['retIva'], true],
            ['Total Retención Renta', $retRenta, true],
            ['Importe Total (con impuestos)', $sum['total'], true],
        ];
        foreach ($totales as [$k, $v, $money]) {
            $h->setCellValue("A{$r}", $k);
            if ($money) {
                $h->setCellValue("B{$r}", $v);
                $h->getStyle("B{$r}")->getNumberFormat()->setFormatCode(self::MONEY);
            } else {
                $h->setCellValue("B{$r}", $v);
            }
            $r++;
        }

        // Totales de ventas
        $ventas = $datos['ventas'] ?? [];
        $anulados = $datos['anulados'] ?? [];
        $vTot = ['b0' => 0.0, 'grav' => 0.0, 'noGra' => 0.0, 'iva' => 0.0, 'ice' => 0.0, 'retIva' => 0.0, 'retRenta' => 0.0, 'comp' => 0];
        foreach ($ventas as $v) {
            $vTot['b0']    += (float) $v['baseImponible'];
            $vTot['grav']  += (float) $v['baseImpGrav'];
            $vTot['noGra'] += (float) $v['baseNoGraIva'];
            $vTot['iva']   += (float) $v['montoIva'];
            $vTot['ice']   += (float) $v['montoIce'];
            $vTot['retIva']   += (float) $v['valorRetIva'];
            $vTot['retRenta'] += (float) $v['valorRetRenta'];
            $vTot['comp']  += (int) $v['numeroComprobantes'];
        }

        $r += 1;
        $h->setCellValue("A{$r}", 'TOTALES DE VENTAS');
        $h->getStyle("A{$r}")->getFont()->setBold(true);
        $h->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
        $r++;
        $totVentas = [
            ['Nº de clientes (registros de venta)', count($ventas), false],
            ['Nº de comprobantes emitidos', $vTot['comp'], false],
            ['Base No Objeto de IVA', $vTot['noGra'], true],
            ['Base Tarifa 0%', $vTot['b0'], true],
            ['Base Gravada (tarifa ≠ 0%)', $vTot['grav'], true],
            ['Monto IVA', $vTot['iva'], true],
            ['Monto ICE', $vTot['ice'], true],
            ['IVA que le retuvieron', $vTot['retIva'], true],
            ['Renta que le retuvieron', $vTot['retRenta'], true],
            ['Total Ventas (totalVentas del ATS)', $vTot['noGra'] + $vTot['b0'] + $vTot['grav'], true],
            ['Comprobantes anulados', count($anulados), false],
        ];
        foreach ($totVentas as [$k, $v, $money]) {
            $h->setCellValue("A{$r}", $k);
            $h->setCellValue("B{$r}", $v);
            if ($money) {
                $h->getStyle("B{$r}")->getNumberFormat()->setFormatCode(self::MONEY);
            }
            $r++;
        }

        // Conteo por tipo de comprobante
        if ($porTipo !== []) {
            $r += 1;
            $h->setCellValue("A{$r}", 'DOCUMENTOS POR TIPO DE COMPROBANTE');
            $h->getStyle("A{$r}")->getFont()->setBold(true);
            $h->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
            $r++;
            ksort($porTipo);
            foreach ($porTipo as $tipo => $cant) {
                $this->texto($h, "A{$r}", 'Tipo ' . $tipo);
                $h->setCellValue("B{$r}", $cant);
                $r++;
            }
        }

        $h->getColumnDimension('A')->setWidth(42);
        $h->getColumnDimension('B')->setWidth(28);
    }

    // ── helpers de estilo ────────────────────────────────────────────────────

    private function cabecera(Worksheet $h, array $cols): void
    {
        $c = 1;
        foreach ($cols as $titulo) {
            $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $h->setCellValue($letra . '1', $titulo);
            $c++;
        }
        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($cols));
        $h->getStyle("A1:{$ultima}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $h->getRowDimension(1)->setRowHeight(22);
    }

    private function autosize(Worksheet $h, array $cols): void
    {
        for ($i = 1, $n = count($cols); $i <= $n; $i++) {
            $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $h->getColumnDimension($letra)->setAutoSize(true);
        }
    }

    /** Escribe como texto para no perder ceros a la izquierda (series, códigos, RUC). */
    private function texto(Worksheet $h, string $celda, string $valor): void
    {
        $h->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
    }

    private function dinero(Worksheet $h, string $celda, $valor): void
    {
        $h->setCellValue($celda, (float) $valor);
        $h->getStyle($celda)->getNumberFormat()->setFormatCode(self::MONEY);
    }
}
