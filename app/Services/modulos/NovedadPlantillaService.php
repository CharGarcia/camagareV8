<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoNovedades;
use App\repositories\modulos\EmpleadoRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Arma la plantilla Excel para cargar novedades: una hoja "Novedades" con UNA
 * FILA POR EMPLEADO (todo el personal activo de la empresa) y UNA COLUMNA POR
 * CADA TIPO de novedad del catálogo, más una hoja "Referencia" con las
 * instrucciones y los códigos.
 *
 * El usuario solo escribe el valor en las columnas de novedad que le
 * correspondan a cada empleado: las celdas vacías (o en 0) no crean nada al
 * importar (ver NovedadImportService). El período, el "afecta a" y la fecha van
 * ya escritos en cada fila y valen para todas las novedades de esa fila.
 */
class NovedadPlantillaService
{
    /** Columnas del empleado, antes de las de novedad (A, B). */
    public const COLS_EMPLEADO = ['IDENTIFICACION', 'NOMBRE'];

    /** Columnas después de las de novedad: aplican a todas las novedades de la fila. */
    public const COLS_FILA = ['MES', 'ANIO', 'AFECTA_A', 'FECHA', 'OBSERVACION'];

    /** Filas extra (debajo del personal) que también llevan las listas desplegables. */
    private const FILAS_EXTRA_LISTAS = 50;

    private const COLOR_ENC_NOVEDAD = 'FFFFF2CC'; // columnas que llena el usuario
    private const COLOR_ENC_FIJO    = 'FFE9ECEF'; // columnas ya prellenadas

    private EmpleadoRepository $empleados;

    public function __construct(EmpleadoRepository $empleados)
    {
        $this->empleados = $empleados;
    }

    /**
     * Encabezado de la columna de un tipo de novedad: su nombre y, entre
     * paréntesis, qué se escribe en ella. El importador reconoce la columna por el
     * nombre e ignora el paréntesis.
     */
    public static function encabezadoTipo(string $codigo): string
    {
        $unidad = match (CatalogoNovedades::unidadValor($codigo)) {
            'horas'   => 'HORAS',
            'dias'    => 'DÍAS',
            'ninguno' => 'MOTIVO',
            default   => '$',
        };
        return mb_strtoupper((string) CatalogoNovedades::nombreTipo($codigo), 'UTF-8') . " ({$unidad})";
    }

    public function construir(int $idEmpresa, int $mes, int $anio, string $aplicaEn): Spreadsheet
    {
        $hoy   = date('Y-m-d');
        $tipos = array_column(CatalogoNovedades::TIPOS, 'codigo');

        $encabezados = self::COLS_EMPLEADO;
        foreach ($tipos as $codigo) {
            $encabezados[] = self::encabezadoTipo((string) $codigo);
        }
        $encabezados = array_merge($encabezados, self::COLS_FILA);

        // Letra de cada columna, para no depender de posiciones escritas a mano.
        $letra     = fn(int $n) => Coordinate::stringFromColumnIndex($n);
        $nEmpleado = count(self::COLS_EMPLEADO);
        $primNov   = $letra($nEmpleado + 1);
        $ultNov    = $letra($nEmpleado + count($tipos));
        $colFila   = [];
        foreach (self::COLS_FILA as $i => $campo) {
            $colFila[$campo] = $letra($nEmpleado + count($tipos) + 1 + $i);
        }
        $colAviso = $letra($nEmpleado + 1 + (int) array_search(CatalogoNovedades::COD_AVISO_SALIDA, $tipos, true));
        $ultCol   = $letra(count($encabezados));

        $ss = new Spreadsheet();
        $hoja = $ss->getActiveSheet();
        $hoja->setTitle('Novedades');
        $hoja->fromArray($encabezados, null, 'A1');

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
            // Las columnas de novedad van vacías: las completa el usuario.
            $hoja->setCellValue($colFila['MES'] . $fila, $mes);
            $hoja->setCellValue($colFila['ANIO'] . $fila, $anio);
            $hoja->setCellValueExplicit($colFila['AFECTA_A'] . $fila, $aplicaEn, DataType::TYPE_STRING);
            $hoja->setCellValueExplicit($colFila['FECHA'] . $fila, $hoy, DataType::TYPE_STRING);
            // OBSERVACION vacía: al importar, cada novedad se guarda con "Tipo - Mes Año".
            $fila++;
        }
        $ultFilaListas = $fila - 1 + self::FILAS_EXTRA_LISTAS;

        $this->darFormatoEncabezado($hoja, $ultCol, $primNov, $ultNov);

        // Con 11 columnas de novedad la hoja es ancha: el empleado queda fijo a la
        // izquierda y los encabezados arriba al desplazarse.
        $hoja->freezePane('C2');
        for ($c = 1; $c <= count($encabezados); $c++) {
            $col = $letra($c);
            if ($c > $nEmpleado && $c <= $nEmpleado + count($tipos)) {
                $hoja->getColumnDimension($col)->setWidth(17);
            } else {
                $hoja->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $rangoMotivos = $this->agregarReferencia($ss);

        // Listas desplegables de ayuda. No bloquean lo escrito a mano (el
        // importador valida igual y acepta también códigos y nombres).
        $this->listaDesplegable($hoja, "{$colAviso}2:{$colAviso}{$ultFilaListas}", $rangoMotivos);
        $this->listaDesplegable(
            $hoja,
            $colFila['AFECTA_A'] . "2:" . $colFila['AFECTA_A'] . $ultFilaListas,
            '"' . implode(',', array_keys(CatalogoNovedades::APLICA_EN)) . '"'
        );

        // El archivo debe abrirse en la hoja de datos, no en la de referencia.
        $ss->setActiveSheetIndex(0);
        return $ss;
    }

    /** Encabezados en negrita y ajustados; las columnas de novedad resaltadas. */
    private function darFormatoEncabezado(Worksheet $hoja, string $ultCol, string $primNov, string $ultNov): void
    {
        $enc = $hoja->getStyle("A1:{$ultCol}1");
        $enc->getFont()->setBold(true);
        $enc->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $enc->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ENC_FIJO);

        $nov = $hoja->getStyle("{$primNov}1:{$ultNov}1");
        $nov->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ENC_NOVEDAD);
        $nov->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Los nombres largos ("PRÉSTAMO QUIROGRAFARIO ($)") ocupan hasta 3 líneas.
        $hoja->getRowDimension(1)->setRowHeight(45);
    }

    private function listaDesplegable(Worksheet $hoja, string $rango, string $formula): void
    {
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setAllowBlank(true);
        $dv->setShowDropDown(true);
        $dv->setShowErrorMessage(false);
        $dv->setFormula1($formula);
        $hoja->setDataValidation($rango, $dv);
    }

    /**
     * Hoja de apoyo: cómo se llena la plantilla y los valores válidos. Devuelve el
     * rango con las opciones de motivo de salida, que alimenta la lista
     * desplegable de la columna AVISO DE SALIDA.
     */
    private function agregarReferencia(Spreadsheet $ss): string
    {
        $ref = $ss->createSheet();
        $ref->setTitle('Referencia');

        $fila = 1;
        $titulo = function (string $texto) use ($ref, &$fila): void {
            $ref->setCellValue('A' . $fila, $texto);
            $ref->getStyle('A' . $fila)->getFont()->setBold(true);
            $fila++;
        };

        $titulo('CÓMO SE LLENA LA HOJA NOVEDADES');
        foreach ([
            '• Hay una fila por empleado y una columna por cada tipo de novedad. Escriba el valor solo en las novedades que le correspondan: las celdas vacías o en 0 no crean nada.',
            '• MES, ANIO, AFECTA_A y FECHA valen para todas las novedades de la fila. Si una novedad va a otro período u otro pago, póngala en una copia de la fila del empleado con esos datos.',
            '• OBSERVACION es opcional: si queda vacía, cada novedad se guarda con "Tipo - Mes Año".',
            '• En la columna AVISO DE SALIDA no va un valor: se elige (o se escribe) el motivo de salida.',
        ] as $linea) {
            $ref->setCellValue('A' . $fila++, $linea);
        }
        $fila++;

        $titulo('TIPOS DE NOVEDAD');
        $ref->fromArray(['CÓDIGO', 'NOVEDAD', 'QUÉ SE ESCRIBE EN SU COLUMNA'], null, 'A' . $fila);
        $ref->getStyle("A{$fila}:C{$fila}")->getFont()->setBold(true);
        $fila++;
        foreach (CatalogoNovedades::TIPOS as $t) {
            $que = CatalogoNovedades::esAvisoSalida($t['codigo'])
                ? 'Motivo de salida (ver abajo)'
                : CatalogoNovedades::labelValor($t['codigo']);
            $ref->fromArray([$t['codigo'], $t['nombre'], $que], null, 'A' . $fila++);
        }
        $fila++;

        $titulo('AFECTA_A');
        foreach (CatalogoNovedades::APLICA_EN as $k => $v) {
            $ref->fromArray([$k, $v], null, 'A' . $fila++);
        }
        $fila++;

        // Una sola columna "T - Terminación del contrato": es lo que muestra la
        // lista desplegable de la hoja Novedades (el importador toma el código).
        $titulo('MOTIVOS DE SALIDA (columna AVISO DE SALIDA)');
        $primMotivo = $fila;
        foreach (CatalogoNovedades::MOTIVOS_SALIDA as $m) {
            $ref->setCellValueExplicit('A' . $fila++, $m['codigo'] . ' - ' . $m['nombre'], DataType::TYPE_STRING);
        }

        $ref->getColumnDimension('A')->setWidth(12);
        $ref->getColumnDimension('B')->setAutoSize(true);
        $ref->getColumnDimension('C')->setAutoSize(true);

        return 'Referencia!$A$' . $primMotivo . ':$A$' . ($fila - 1);
    }
}
