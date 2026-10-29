<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AlumnoRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Arma la plantilla Excel para cargar la facturación de los alumnos (mismo
 * patrón que la de Novedades): una hoja "Facturacion" con UNA FILA POR ALUMNO
 * ACTIVO y UNA COLUMNA POR CADA SERVICIO ACTIVO del catálogo de la empresa
 * (productos tipo 02 con status = 1), más una hoja "Instrucciones".
 *
 * Las celdas de servicio van VACÍAS: el usuario escribe solo lo que quiere
 * cambiar (vacía = no se toca). Lo que hace cada valor al importar está en
 * AlumnoFacturacionImportService.
 *
 * El encabezado de cada servicio termina en [CODIGO]: el importador reconoce la
 * columna por ese código, no por la posición ni por el nombre.
 */
class AlumnoFacturacionPlantillaService
{
    public const HOJA_DATOS = 'Facturacion';

    /** Columnas del alumno, antes de las de servicio. Solo ID_ALUMNO se usa al importar. */
    public const COLS_ALUMNO = ['ID_ALUMNO', 'ALUMNO', 'CAMPUS', 'CLIENTE_FACTURA'];

    private const COLOR_ENC_SERVICIO = 'FFFFF2CC'; // columnas que llena el usuario
    private const COLOR_ENC_FIJO     = 'FFE9ECEF'; // columnas de referencia

    private AlumnoRepository $repo;

    public function __construct(AlumnoRepository $repo)
    {
        $this->repo = $repo;
    }

    /** Encabezado de la columna de un servicio: «NOMBRE [CODIGO]». */
    public static function encabezadoServicio(array $producto): string
    {
        return mb_strtoupper(trim((string) $producto['nombre']), 'UTF-8') . ' [' . trim((string) $producto['codigo']) . ']';
    }

    /** Código del producto al final de un encabezado («… [S001]»), o null si no es columna de servicio. */
    public static function codigoDeEncabezado(string $encabezado): ?string
    {
        return preg_match('/\[([^\[\]]+)\]\s*$/u', trim($encabezado), $m) ? trim($m[1]) : null;
    }

    /** Precio con el que hoy se factura la línea: el propio o, si no tiene, el base del producto. */
    public static function precioLinea(array $linea): float
    {
        $over = $linea['precio_override'] ?? null;
        return round((float) ($over !== null && $over !== '' ? $over : ($linea['producto_precio_base'] ?? 0)), 2);
    }

    public static function lineaActiva(array $linea): bool
    {
        $a = $linea['activo'] ?? true;
        return $a === true || $a === 't' || $a === 'true' || $a === 1 || $a === '1';
    }

    public function construir(int $idEmpresa, ?int $idUsuarioFiltro): Spreadsheet
    {
        $servicios = $this->repo->getCatalogoServicios($idEmpresa);
        $alumnos   = $this->repo->getActivosParaPlantilla($idEmpresa, $idUsuarioFiltro);

        $letra   = fn(int $n) => Coordinate::stringFromColumnIndex($n);
        $nFijas  = count(self::COLS_ALUMNO);
        $nServ   = count($servicios);
        $encabezados = self::COLS_ALUMNO;
        foreach ($servicios as $p) {
            $encabezados[] = self::encabezadoServicio($p);
        }
        $ultCol = $letra(count($encabezados));

        $ss = new Spreadsheet();
        $hoja = $ss->getActiveSheet();
        $hoja->setTitle(self::HOJA_DATOS);
        $hoja->fromArray($encabezados, null, 'A1');
        $hoja->getStyle('B:D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $fila = 2;
        foreach ($alumnos as $a) {
            $hoja->setCellValue("A{$fila}", (int) $a['id']);
            $hoja->setCellValueExplicit("B{$fila}", trim(($a['apellidos'] ?? '') . ' ' . ($a['nombres'] ?? '')), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("C{$fila}", (string) ($a['campus_nombre'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("D{$fila}", (string) ($a['cliente_nombre'] ?? ''), DataType::TYPE_STRING);
            // Las columnas de servicio van vacías: las completa el usuario.
            $fila++;
        }
        $ultFila = max(2, $fila - 1);

        $enc = $hoja->getStyle("A1:{$ultCol}1");
        $enc->getFont()->setBold(true);
        $enc->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $enc->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ENC_FIJO);
        $hoja->getRowDimension(1)->setRowHeight(48);
        if ($nServ > 0) {
            $primServ = $letra($nFijas + 1);
            $rangoEnc = $hoja->getStyle("{$primServ}1:{$ultCol}1");
            $rangoEnc->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ENC_SERVICIO);
            $rangoEnc->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hoja->getStyle("{$primServ}2:{$ultCol}{$ultFila}")->getNumberFormat()->setFormatCode('0.00');
        }

        // Nota en cada encabezado: qué hace la columna (y el precio base del servicio).
        $this->nota($hoja, 'A1', 'Número interno del alumno. No lo cambie: con él se ubica al alumno al importar.');
        foreach (['B1', 'C1', 'D1'] as $c) {
            $this->nota($hoja, $c, 'Solo de referencia: no se importa.');
        }
        foreach ($servicios as $i => $p) {
            $this->nota($hoja, $letra($nFijas + 1 + $i) . '1',
                'Precio mensual de este servicio para el alumno. Vacía: no cambia nada. 0: se le quita. X: precio base ('
                . number_format((float) $p['precio_base'], 2, '.', '') . ').');
        }

        $hoja->freezePane($letra($nFijas + 1) . '2');
        $hoja->getColumnDimension('A')->setWidth(11);
        $hoja->getColumnDimension('B')->setWidth(34);
        $hoja->getColumnDimension('C')->setWidth(16);
        $hoja->getColumnDimension('D')->setWidth(28);
        for ($c = $nFijas + 1; $c <= count($encabezados); $c++) {
            $hoja->getColumnDimension($letra($c))->setWidth(18);
        }

        $this->agregarInstrucciones($ss, $servicios);
        $ss->setActiveSheetIndex(0);
        return $ss;
    }

    private function nota(Worksheet $hoja, string $celda, string $texto): void
    {
        $comentario = $hoja->getComment($celda);
        $comentario->getText()->createTextRun($texto);
        $comentario->setWidth('240pt');
        $comentario->setHeight('70pt');
    }

    private function agregarInstrucciones(Spreadsheet $ss, array $servicios): void
    {
        $ref = $ss->createSheet();
        $ref->setTitle('Instrucciones');
        $fila = 1;
        $titulo = function (string $t) use ($ref, &$fila): void {
            $ref->setCellValue('A' . $fila, $t);
            $ref->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(12);
            $fila++;
        };
        $lineas = function (array $textos) use ($ref, &$fila): void {
            foreach ($textos as $t) {
                $ref->setCellValueExplicit('A' . $fila++, $t, DataType::TYPE_STRING);
            }
            $fila++;
        };

        $titulo('CÓMO USAR ESTE ARCHIVO');
        $lineas([
            '1. En la hoja Facturacion hay una fila por alumno activo y una columna por cada servicio activo del catálogo.',
            '2. Escriba solo lo que quiere cambiar. Las celdas vacías no cambian nada de lo que el alumno ya tiene.',
            '3. Un precio agrega el servicio al alumno o le cambia el precio. X = precio base del servicio. 0 = se le quita el servicio.',
            '4. No hace falta borrar alumnos ni columnas.',
            '5. Guarde el archivo y súbalo en Alumnos → Cargar facturación.',
            '6. La carga es TODO O NADA: con un solo error no se cambia ningún alumno, y se indica la fila y el servicio a corregir.',
        ]);

        $titulo('QUÉ SE CONSERVA DE CADA SERVICIO');
        $lineas([
            '• La cantidad, el descuento, el texto Adicional y el IVA elegido en la ficha del alumno no cambian: el Excel solo pone el precio.',
            '• Un servicio nuevo entra con cantidad 1, sin descuento ni adicional y con el IVA del producto.',
            '• Si el precio es igual al precio base, el servicio queda «siguiendo» al producto: cuando cambie el precio base, cambia también aquí.',
            '• Si el alumno tenía el servicio desactivado y se le escribe un precio, se vuelve a activar.',
            '• Los productos que no son servicios y los servicios inactivos no tienen columna y no se tocan.',
            '• Si un alumno tiene dos líneas del mismo servicio, cambie el precio en su ficha (con 0 sí se quitan las dos).',
        ]);

        $titulo('SERVICIOS DE ESTA PLANTILLA');
        $ref->fromArray(['CÓDIGO', 'SERVICIO', 'PRECIO BASE'], null, 'A' . $fila);
        $ref->getStyle("A{$fila}:C{$fila}")->getFont()->setBold(true);
        $fila++;
        foreach ($servicios as $p) {
            $ref->setCellValueExplicit('A' . $fila, (string) $p['codigo'], DataType::TYPE_STRING);
            $ref->setCellValueExplicit('B' . $fila, (string) $p['nombre'], DataType::TYPE_STRING);
            $ref->setCellValue('C' . $fila, round((float) $p['precio_base'], 2));
            $fila++;
        }
        if (empty($servicios)) {
            $ref->setCellValue('A' . $fila, 'La empresa no tiene servicios activos en Productos (tipo Servicio).');
        }

        $ref->getColumnDimension('A')->setWidth(16);
        $ref->getColumnDimension('B')->setWidth(50);
        $ref->getColumnDimension('C')->setWidth(14);
    }
}
