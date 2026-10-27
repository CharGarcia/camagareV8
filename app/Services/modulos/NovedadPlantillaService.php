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
 * CADA TIPO de novedad del catálogo, más una hoja "Instrucciones" que explica
 * columna por columna qué se escribe y qué hace en el sistema.
 *
 * Algunos tipos tienen DOS columnas y la columna elegida es la marca:
 *  - Otros Ingresos y horas (CatalogoNovedades::CODS_OPCION_IESS): CON IESS / SIN IESS.
 *  - Anticipo y Préstamo Empresa (los que normalmente se pagan por egreso antes de
 *    descontarse): YA ENTREGADO / POR PAGAR. Lo "ya entregado" se guarda con la marca
 *    de desembolso (el rol lo descuenta directo); lo "por pagar" sigue el camino
 *    normal: Egresos → Nómina y luego el rol.
 * Además, junto al Préstamo Empresa va la columna N° DE CUOTAS: con más de una,
 * el importador crea una cuota por mes desde el período de la fila.
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

    /** Encabezado de la columna con el número de cuotas del Préstamo Empresa. */
    public const ENC_CUOTAS = 'PRÉSTAMO EMPRESA N° DE CUOTAS';

    /** Tope de cuotas que se generan desde una fila (10 años). */
    public const MAX_CUOTAS = 120;

    /** Ejemplos que acompañan al nombre del tipo en el encabezado de su columna. */
    private const EJEMPLOS_TIPO = ['1' => 'Bonos, comisiones'];

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
     * Columnas de novedad de la hoja, en orden. Cada una: 'codigo' del tipo, 'iess'
     * (true/false en CON/SIN IESS, null si el tipo no tiene esa marca), 'entregado'
     * (true/false en YA ENTREGADO / POR PAGAR, null en los demás) y 'cuotas' (true
     * solo en la columna N° DE CUOTAS del Préstamo Empresa, que no es una novedad).
     */
    public static function columnasNovedad(): array
    {
        $cols = [];
        foreach (self::tiposPlantilla() as $t) {
            $codigo = (string) $t['codigo'];
            $base = ['codigo' => $codigo, 'iess' => null, 'entregado' => null, 'cuotas' => false];
            if (CatalogoNovedades::admiteOpcionIess($codigo)) {
                $cols[] = ['iess' => true] + $base;
                $cols[] = ['iess' => false] + $base;
            } elseif (self::requiereEntrega($codigo)) {
                $cols[] = ['entregado' => true] + $base;
                $cols[] = ['entregado' => false] + $base;
                if (CatalogoNovedades::esPrestamo($codigo)) {
                    $cols[] = ['cuotas' => true] + $base;
                }
            } else {
                $cols[] = $base;
            }
        }
        return $cols;
    }

    /**
     * Tipos que van en la plantilla: todos menos el Aviso de salida, que se
     * registra a mano en el módulo (el importador lo rechaza, ver NovedadImportService).
     */
    public static function tiposPlantilla(): array
    {
        return array_values(array_filter(
            CatalogoNovedades::TIPOS,
            fn($t) => !CatalogoNovedades::esAvisoSalida((string) $t['codigo'])
        ));
    }

    /** Anticipo y Préstamo Empresa: se entregan al empleado y se pagan por egreso antes de descontarse. */
    public static function requiereEntrega(string $codigo): bool
    {
        return CatalogoNovedades::esPagoPorEgreso($codigo) || CatalogoNovedades::esPrestamo($codigo);
    }

    /**
     * Texto de la marca YA ENTREGADO / POR PAGAR en el encabezado. El importador
     * reconoce la columna por el nombre del tipo y esta marca (ver mapearColumnas).
     */
    public static function marcaEntrega(string $codigo, bool $entregado): string
    {
        if (CatalogoNovedades::esPrestamo($codigo)) {
            return $entregado ? 'YA DESEMBOLSADO' : 'POR DESEMBOLSAR';
        }
        return $entregado ? 'YA ENTREGADO' : 'POR PAGAR';
    }

    /**
     * Encabezado de una columna de novedad: el nombre del tipo, su marca (CON/SIN
     * IESS o YA ENTREGADO/POR PAGAR) si la tiene, los ejemplos y, entre paréntesis,
     * qué se escribe en ella. El importador reconoce la columna por el nombre y la
     * marca, e ignora todo lo que va entre paréntesis.
     */
    public static function encabezado(array $col): string
    {
        if ($col['cuotas']) {
            return self::ENC_CUOTAS;
        }
        $codigo = $col['codigo'];
        $unidad = match (CatalogoNovedades::unidadValor($codigo)) {
            'horas'   => 'HORAS',
            'dias'    => 'DÍAS',
            'ninguno' => 'MOTIVO',
            default   => '$',
        };
        $nombre = (string) CatalogoNovedades::nombreTipo($codigo);
        if ($col['iess'] !== null) {
            $nombre .= $col['iess'] ? ' con IESS' : ' sin IESS';
        }
        if ($col['entregado'] !== null) {
            $nombre .= ' ' . self::marcaEntrega($codigo, $col['entregado']);
        }
        if (isset(self::EJEMPLOS_TIPO[$codigo])) {
            $nombre .= ' (' . self::EJEMPLOS_TIPO[$codigo] . ')';
        }
        return mb_strtoupper($nombre, 'UTF-8') . " ({$unidad})";
    }

    /**
     * Explicación de una columna de novedad para la hoja Instrucciones y la nota del
     * encabezado: [qué escribir, qué hace en el sistema, ejemplo].
     */
    public static function explicacion(array $col): array
    {
        $codigo = $col['codigo'];
        if ($col['cuotas']) {
            return [
                'Opcional. Número de cuotas mensuales del préstamo (de 1 a ' . self::MAX_CUOTAS . ').',
                'Con más de 1, se crea una cuota por mes desde el MES/ANIO de la fila, cada una por el valor escrito en la columna del préstamo. Vacía o 1: solo la cuota de ese mes.',
                '10',
            ];
        }
        if ($col['entregado'] !== null) {
            $prestamo = CatalogoNovedades::esPrestamo($codigo);
            if ($col['entregado']) {
                return $prestamo
                    ? ['Valor de la cuota.', 'El préstamo ya se entregó al empleado: el rol descuenta la cuota directo, sin pedir el desembolso en Egresos → Nómina. No genera egreso ni asiento del desembolso.', '80']
                    : ['Monto del anticipo.', 'El anticipo ya se entregó al empleado: el rol lo descuenta directo, sin pedir el pago en Egresos → Nómina. No genera egreso ni asiento del pago.', '100'];
            }
            return $prestamo
                ? ['Valor de la cuota.', 'El préstamo aún no se entrega: aparece en Egresos → Nómina para desembolsarlo por la suma de sus cuotas, y el rol descuenta cada cuota solo después del desembolso. Use N° DE CUOTAS para registrar todas las cuotas de una vez.', '80']
                : ['Monto del anticipo.', 'El anticipo aún no se entrega: aparece en Egresos → Nómina para pagarlo, y el rol lo descuenta solo después de pagado. Mientras tanto, el rol muestra el aviso "Pendientes de desembolso".', '100'];
        }
        if (in_array($codigo, CatalogoNovedades::CODS_HORAS, true)) {
            $que = 'Se paga en el rol con el recargo de ese tipo de hora sobre el valor de la hora del empleado.';
            return ['Número de horas (acepta decimales).', $que . ($col['iess'] ? ' Suma a la base del IESS.' : ' No suma a la base del IESS.'), '8'];
        }
        return match ($codigo) {
            '1' => ['Monto.', 'Ingreso adicional en el rol.' . ($col['iess'] ? ' Suma a la base del IESS.' : ' No suma a la base del IESS.'), '50'],
            '2' => ['Monto.', 'Se resta del rol directo (multas, consumos, faltantes, etc.).', '25'],
            '7' => ['Valor de la cuota del mes.', 'Se resta del rol directo. El préstamo lo entrega el IESS, no la empresa.', '45.30'],
            '8' => ['Valor de la cuota del mes.', 'Se resta del rol directo. El préstamo lo entrega el IESS o el banco, no la empresa.', '120'],
            '10' => ['Número de días.', 'Resta del sueldo del mes (sueldo / 30 por día). Va siempre al rol mensual, aunque AFECTA_A diga quincena o semanal.', '2'],
            default => [(string) CatalogoNovedades::labelValor($codigo), '', ''],
        };
    }

    /** Explicación de las columnas fijas (empleado y fila): [qué escribir, qué hace, ejemplo]. */
    private static function explicacionFija(string $columna): array
    {
        return match ($columna) {
            'IDENTIFICACION' => ['Cédula del empleado. Ya viene escrita: no la cambie.', 'Con ella se busca al empleado. Si no existe en la empresa, la fila da error.', '0912345678'],
            'NOMBRE'         => ['Ya viene escrito.', 'Solo de referencia: el sistema usa la IDENTIFICACION.', 'PÉREZ JUAN'],
            'MES'            => ['Número del mes (1 a 12). Ya viene escrito.', 'Mes del rol al que se imputa cada novedad de la fila.', '11'],
            'ANIO'           => ['Año. Ya viene escrito.', 'Año del rol al que se imputa cada novedad de la fila.', '2026'],
            'AFECTA_A'       => ['rol, quincena o semanal. Ya viene escrito.', 'En qué pago se registran las novedades de la fila. Los días no laborados van siempre al rol mensual.', 'rol'],
            'FECHA'          => ['Fecha (aaaa-mm-dd). Ya viene escrita con la fecha de hoy.', 'Fecha en que ocurrió la novedad (solo informativa).', '2026-11-05'],
            'OBSERVACION'    => ['Texto opcional.', 'Se guarda en TODAS las novedades de la fila. Vacía: cada una queda como "Tipo - Mes Año". Para textos distintos, copie la fila del empleado.', 'Descuento por atraso'],
            default          => ['', '', ''],
        };
    }

    public function construir(int $idEmpresa, int $mes, int $anio, string $aplicaEn): Spreadsheet
    {
        $hoy     = date('Y-m-d');
        $colsNov = self::columnasNovedad();
        $nNov    = count($colsNov);

        $encabezados = self::COLS_EMPLEADO;
        foreach ($colsNov as $c) {
            $encabezados[] = self::encabezado($c);
        }
        $encabezados = array_merge($encabezados, self::COLS_FILA);

        // Letra de cada columna, para no depender de posiciones escritas a mano.
        $letra     = fn(int $n) => Coordinate::stringFromColumnIndex($n);
        $nEmpleado = count(self::COLS_EMPLEADO);
        $primNov   = $letra($nEmpleado + 1);
        $ultNov    = $letra($nEmpleado + $nNov);
        $colFila   = [];
        foreach (self::COLS_FILA as $i => $campo) {
            $colFila[$campo] = $letra($nEmpleado + $nNov + 1 + $i);
        }
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

        // Nota en cada encabezado (el triangulito rojo de Excel): qué hace la columna,
        // sin tener que ir a la hoja Instrucciones.
        foreach (self::COLS_EMPLEADO as $i => $campo) {
            $this->nota($hoja, $letra($i + 1) . '1', self::explicacionFija($campo));
        }
        foreach ($colsNov as $i => $c) {
            $this->nota($hoja, $letra($nEmpleado + 1 + $i) . '1', self::explicacion($c));
        }
        foreach ($colFila as $campo => $col) {
            $this->nota($hoja, $col . '1', self::explicacionFija($campo));
        }

        // Con tantas columnas de novedad la hoja es ancha: el empleado queda fijo a
        // la izquierda y los encabezados arriba al desplazarse.
        $hoja->freezePane('C2');
        for ($c = 1; $c <= count($encabezados); $c++) {
            $col = $letra($c);
            if ($c > $nEmpleado && $c <= $nEmpleado + $nNov) {
                $hoja->getColumnDimension($col)->setWidth(17);
            } else {
                $hoja->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $this->agregarInstrucciones($ss, $colsNov);

        // Lista desplegable de ayuda. No bloquea lo escrito a mano (el importador
        // valida igual).
        $this->listaDesplegable(
            $hoja,
            $colFila['AFECTA_A'] . "2:" . $colFila['AFECTA_A'] . $ultFilaListas,
            '"' . implode(',', array_keys(CatalogoNovedades::APLICA_EN)) . '"'
        );

        // El archivo debe abrirse en la hoja de datos, no en la de instrucciones.
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

        // Los nombres largos ("PRÉSTAMO EMPRESA POR DESEMBOLSAR ($)") ocupan hasta 4 líneas.
        $hoja->getRowDimension(1)->setRowHeight(60);
    }

    /** Nota (comentario) de Excel en la celda: qué escribir y qué hace. */
    private function nota(Worksheet $hoja, string $celda, array $explicacion): void
    {
        [$que, $hace] = $explicacion;
        $texto = trim($que . ($hace !== '' ? "\n" . $hace : ''));
        if ($texto === '') {
            return;
        }
        $comentario = $hoja->getComment($celda);
        $comentario->getText()->createTextRun($texto);
        $comentario->setWidth('260pt');
        $comentario->setHeight('110pt');
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
     * Hoja "Instrucciones" (al final del archivo): pasos, una fila por cada columna de
     * la hoja Novedades —en el mismo orden y armada desde la misma lista que los
     * encabezados, así nunca queda una columna sin explicar—, casos especiales y lo
     * que hay que saber de contabilidad.
     */
    private function agregarInstrucciones(Spreadsheet $ss, array $colsNov): void
    {
        $ref = $ss->createSheet();
        $ref->setTitle('Instrucciones');

        $fila = 1;
        $titulo = function (string $texto) use ($ref, &$fila): void {
            $ref->setCellValue('A' . $fila, $texto);
            $ref->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(12);
            $fila++;
        };
        $lineas = function (array $textos) use ($ref, &$fila): void {
            foreach ($textos as $t) {
                $ref->setCellValue('A' . $fila++, $t);
            }
            $fila++;
        };

        $titulo('CÓMO USAR ESTE ARCHIVO');
        $lineas([
            '1. En la hoja Novedades hay una fila por empleado y una columna por cada tipo de novedad. Escriba el valor solo en las novedades que le correspondan a cada empleado.',
            '2. Las celdas vacías o en 0 no crean nada. Los empleados sin ningún valor se omiten: no hace falta borrar filas ni columnas.',
            '3. Guarde el archivo y súbalo en Novedades → Importar.',
            '4. La importación es TODO O NADA: si hay un solo error no se importa ninguna novedad, y se indica la fila y la novedad a corregir.',
            '5. Pase el mouse sobre cada encabezado de la hoja Novedades para ver una nota con lo que hace esa columna.',
        ]);

        $titulo('QUÉ ES CADA COLUMNA DE LA HOJA NOVEDADES');
        $ref->fromArray(['COLUMNA', 'QUÉ ESCRIBIR', 'QUÉ HACE EN EL SISTEMA', 'EJEMPLO'], null, 'A' . $fila);
        $cab = $ref->getStyle("A{$fila}:D{$fila}");
        $cab->getFont()->setBold(true);
        $cab->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ENC_FIJO);
        $fila++;
        $primTabla = $fila;
        $filaTabla = function (string $columna, array $exp, bool $novedad) use ($ref, &$fila): void {
            $ref->setCellValueExplicit('A' . $fila, $columna, DataType::TYPE_STRING);
            $ref->setCellValueExplicit('B' . $fila, $exp[0], DataType::TYPE_STRING);
            $ref->setCellValueExplicit('C' . $fila, $exp[1], DataType::TYPE_STRING);
            $ref->setCellValueExplicit('D' . $fila, $exp[2], DataType::TYPE_STRING);
            if ($novedad) {
                $ref->getStyle('A' . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ENC_NOVEDAD);
            }
            $fila++;
        };
        foreach (self::COLS_EMPLEADO as $campo) {
            $filaTabla($campo, self::explicacionFija($campo), false);
        }
        foreach ($colsNov as $c) {
            $filaTabla(self::encabezado($c), self::explicacion($c), true);
        }
        foreach (self::COLS_FILA as $campo) {
            $filaTabla($campo, self::explicacionFija($campo), false);
        }
        $ref->getStyle("A{$primTabla}:D" . ($fila - 1))->getAlignment()
            ->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $fila++;

        $titulo('ANTICIPOS Y PRÉSTAMOS EMPRESA: ¿YA ENTREGADO O POR PAGAR?');
        $lineas([
            '• Use YA ENTREGADO / YA DESEMBOLSADO cuando el dinero ya se le dio al empleado (fuera del sistema): el rol lo descuenta directo.',
            '• Use POR PAGAR / POR DESEMBOLSAR cuando todavía hay que entregarlo: se paga en Egresos → Nómina y recién entonces el rol lo descuenta.',
            '• Plantillas descargadas antes, con una sola columna ANTICIPO o PRÉSTAMO EMPRESA: se toman como YA ENTREGADO.',
            '• CONTABILIDAD: lo YA ENTREGADO no genera egreso, así que ese pago no queda contabilizado. El asiento del rol acredita la cuenta de Anticipos o de Préstamos Empresa por Cobrar, pero nada la debita: si la entrega aún no está contabilizada, regístrela con un asiento manual. Lo POR PAGAR sí queda contabilizado con su egreso.',
        ]);

        $titulo('EJEMPLOS');
        $lineas([
            '• Préstamo de $800 en 10 cuotas, todavía sin entregar: escriba 80 en PRÉSTAMO EMPRESA POR DESEMBOLSAR y 10 en ' . self::ENC_CUOTAS . '. Se crean 10 cuotas de $80, una por mes desde el MES/ANIO de la fila, y en Egresos → Nómina aparece el préstamo por $800 para desembolsarlo.',
            '• Préstamo ya entregado al que le quedan 6 cuotas de $50: escriba 50 en PRÉSTAMO EMPRESA YA DESEMBOLSADO y 6 en ' . self::ENC_CUOTAS . '.',
            '• Sin N° DE CUOTAS (o con 1) se registra solo la cuota del mes de la fila. Ojo: un préstamo POR DESEMBOLSAR con una sola cuota aparece en Egresos por el valor de esa cuota, no por el total del préstamo.',
            '• Observaciones distintas para el mismo empleado (por ejemplo "Descuento por atraso" y "Préstamo cuota 1/10"): copie la fila del empleado y deje cada novedad en su propia fila, con su observación.',
            '• Una novedad de otro mes o de la quincena: copie la fila del empleado, cambie MES/ANIO o AFECTA_A y deje en ella solo esa novedad.',
        ]);

        $titulo('LO QUE NO SE CARGA CON ESTE ARCHIVO');
        $lineas([
            '• El AVISO DE SALIDA: regístrelo a mano en el módulo Novedades (botón Nuevo).',
        ]);

        $titulo('VALORES DE AFECTA_A');
        foreach (CatalogoNovedades::APLICA_EN as $k => $v) {
            $ref->fromArray([$k, $v], null, 'A' . $fila++);
        }

        // Anchos fijos con ajuste de texto en la tabla; las líneas sueltas (pasos,
        // ejemplos) se leen de corrido porque las columnas de al lado van vacías.
        $ref->getColumnDimension('A')->setWidth(42);
        $ref->getColumnDimension('B')->setWidth(36);
        $ref->getColumnDimension('C')->setWidth(70);
        $ref->getColumnDimension('D')->setWidth(18);
    }
}
