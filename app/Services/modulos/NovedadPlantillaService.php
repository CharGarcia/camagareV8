<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoNovedades;
use App\repositories\modulos\EmpleadoRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Arma la plantilla Excel para cargar novedades: una hoja "Novedades" ya
 * prellenada con todo el personal activo de la empresa (identificación, nombre,
 * tipo, período, fecha y observación) y una hoja "Referencia" con los códigos.
 *
 * El usuario solo tiene que completar la columna VALOR de quienes corresponda:
 * las filas que se dejen sin valor se ignoran al importar (ver
 * NovedadImportService).
 */
class NovedadPlantillaService
{
    /** Encabezados de la hoja de datos, en orden (A..J). */
    public const HEADERS = [
        'IDENTIFICACION', 'NOMBRE', 'TIPO', 'VALOR', 'MES',
        'ANIO', 'AFECTA_A', 'FECHA', 'OBSERVACION', 'MOTIVO',
    ];

    private EmpleadoRepository $empleados;

    public function __construct(EmpleadoRepository $empleados)
    {
        $this->empleados = $empleados;
    }

    public function construir(int $idEmpresa, string $tipo, int $mes, int $anio, string $aplicaEn): Spreadsheet
    {
        $nombreTipo  = CatalogoNovedades::nombreTipo($tipo) ?? '';
        $nombreMes   = CatalogoNovedades::MESES[$mes] ?? '';
        // Mismo texto que autocompleta el modal de novedades: "Tipo - Mes Año".
        $observacion = trim("{$nombreTipo} - {$nombreMes} {$anio}");
        $hoy         = date('Y-m-d');

        $ss = new Spreadsheet();
        $hoja = $ss->getActiveSheet();
        $hoja->setTitle('Novedades');
        $hoja->fromArray(self::HEADERS, null, 'A1');
        $hoja->getStyle('A1:J1')->getFont()->setBold(true);

        // Identificación y nombre SIEMPRE como texto: si Excel los toma como
        // número, una cédula como 0705210052 pierde el cero de la izquierda.
        $hoja->getStyle('A:B')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $empleados = $this->empleados->getActivosBasico($idEmpresa);
        if (empty($empleados)) {
            // Sin personal activo, al menos se lleva el formato con una fila guía.
            $empleados = [['identificacion' => '1717136574', 'nombres_apellidos' => '(ejemplo: reemplace por su empleado)']];
        }

        $fila = 2;
        foreach ($empleados as $e) {
            $hoja->setCellValueExplicit("A{$fila}", (string) ($e['identificacion'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("B{$fila}", (string) ($e['nombres_apellidos'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("C{$fila}", $tipo, DataType::TYPE_STRING);
            // D (VALOR) y J (MOTIVO) van vacías: las completa el usuario.
            $hoja->setCellValue("E{$fila}", $mes);
            $hoja->setCellValue("F{$fila}", $anio);
            $hoja->setCellValueExplicit("G{$fila}", $aplicaEn, DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("H{$fila}", $hoy, DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("I{$fila}", $observacion, DataType::TYPE_STRING);
            $fila++;
        }

        $hoja->freezePane('A2');
        foreach (range('A', 'J') as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        $this->agregarReferencia($ss);

        // El archivo debe abrirse en la hoja de datos, no en la de referencia.
        $ss->setActiveSheetIndex(0);
        return $ss;
    }

    /** Hoja de apoyo con los códigos válidos de tipo, "afecta a" y motivos. */
    private function agregarReferencia(Spreadsheet $ss): void
    {
        $ref = $ss->createSheet();
        $ref->setTitle('Referencia');
        $ref->fromArray(['TIPOS (usar código o nombre)'], null, 'A1');
        $ref->getStyle('A1')->getFont()->setBold(true);

        $fila = 2;
        foreach (CatalogoNovedades::TIPOS as $t) {
            $ref->fromArray([$t['codigo'], $t['nombre']], null, 'A' . $fila++);
        }
        $fila++;
        $ref->fromArray(['AFECTA_A'], null, 'A' . $fila);
        $ref->getStyle('A' . $fila)->getFont()->setBold(true);
        $fila++;
        foreach (CatalogoNovedades::APLICA_EN as $k => $v) {
            $ref->fromArray([$k, $v], null, 'A' . $fila++);
        }
        $fila++;
        $ref->fromArray(['MOTIVOS DE SALIDA (solo para Aviso de salida)'], null, 'A' . $fila);
        $ref->getStyle('A' . $fila)->getFont()->setBold(true);
        $fila++;
        foreach (CatalogoNovedades::MOTIVOS_SALIDA as $m) {
            $ref->fromArray([$m['codigo'], $m['nombre']], null, 'A' . $fila++);
        }

        $ref->getColumnDimension('A')->setWidth(12);
        $ref->getColumnDimension('B')->setAutoSize(true);
    }
}
