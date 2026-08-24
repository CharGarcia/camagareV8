<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CargaFacturasRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Genera la plantilla Excel de carga masiva de facturas de venta.
 *
 * A diferencia de la plantilla de productos, las hojas de datos salen VACÍAS:
 * aquí no se actualiza nada existente, cada fila es una factura nueva.
 *
 * Solo se exportan como referencia los catálogos que el usuario NO puede
 * adivinar ni inventar: tarifas de IVA, puntos de emisión, bodegas y vendedores.
 * Clientes, productos y formas de pago no viajan en el libro —se escriben por su
 * identificación o código y el validador los busca en la base—; exportarlos
 * inflaba el archivo con miles de filas que además envejecen mal.
 *
 * La estructura de hojas y columnas la define CargaFacturasEsquema, la misma que
 * usa el validador, para que nunca se desincronicen.
 */
class CargaFacturasPlantillaService
{
    private CargaFacturasRepository $repository;

    /** Colores de encabezado. */
    private const COLOR_DATOS      = 'FF1F4E79'; // azul: hojas editables
    private const COLOR_REFERENCIA = 'FF7F7F7F'; // gris: hojas de consulta
    private const COLOR_LLAVE      = 'FF2E75B6'; // azul claro: columna llave

    /** Filas con listas desplegables por debajo de la última con datos. */
    private const MARGEN_FILAS = 500;

    public function __construct(CargaFacturasRepository $repository)
    {
        $this->repository = $repository;
    }

    /** Construye el libro completo. */
    public function generar(int $idEmpresa): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->removeSheetByIndex(0);

        $empresa = $this->repository->getEmpresa($idEmpresa);
        $rotulo  = $empresa
            ? ($empresa['nombre'] . ' - RUC ' . $empresa['ruc'])
            : ('ID Empresa ' . $idEmpresa);

        // Solo tarifas vigentes: las derogadas (12%, 14%) no deben ofrecerse para
        // facturas nuevas, aunque el validador las siga aceptando.
        $mapaIva    = $this->repository->getMapaTarifasIva(true);
        $puntos     = $this->repository->getMapaPuntosEmision($idEmpresa);
        $bodegas    = $this->repository->getMapaBodegas($idEmpresa);
        $vendedores = $this->repository->getMapaVendedores($idEmpresa);

        // Solo los puntos con secuencial de facturas configurado son utilizables.
        $puntos = array_filter($puntos, static fn($p) => !empty($p['tiene_secuencial']));

        // El orden de creación define el orden de las pestañas.
        $this->crearHojaInstrucciones($libro, $rotulo);
        $this->crearHojaFacturas($libro, $puntos, $bodegas, $vendedores);
        $this->crearHojaDetalles($libro, $mapaIva);
        $this->crearHojaInfoAdicional($libro);

        $this->crearHojasReferencia($libro, $mapaIva, $puntos, $bodegas, $vendedores);
        $this->crearHojaConfig($libro, $idEmpresa, $rotulo);

        $libro->setActiveSheetIndexByName(CargaFacturasEsquema::HOJA_INSTRUCCIONES);

        return $libro;
    }

    /** Nombre sugerido del archivo. */
    public function nombreArchivo(int $idEmpresa): string
    {
        return 'carga_facturas_empresa' . $idEmpresa . '_' . date('Ymd_His') . '.xlsx';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas de datos (vacías: el usuario las llena)
    // ─────────────────────────────────────────────────────────────────────────

    private function crearHojaInstrucciones(Spreadsheet $libro, string $rotulo): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaFacturasEsquema::HOJA_INSTRUCCIONES);

        $h->setCellValueExplicit([1, 1], $rotulo, DataType::TYPE_STRING);
        $h->getStyle([1, 1])->getFont()->setBold(true)->setSize(12);

        $fila = 3;
        foreach (CargaFacturasEsquema::textoInstrucciones() as $linea) {
            $h->setCellValueExplicit([1, $fila], $linea, DataType::TYPE_STRING);

            // Resaltar los títulos de sección (líneas en mayúsculas sin sangría).
            if ($linea !== '' && $linea === mb_strtoupper($linea) && !str_starts_with($linea, ' ')) {
                $h->getStyle([1, $fila])->getFont()->setBold(true);
            }
            $fila++;
        }

        $h->getColumnDimension('A')->setWidth(100);
        $h->getProtection()->setSheet(true);
    }

    private function crearHojaFacturas(
        Spreadsheet $libro,
        array $puntos,
        array $bodegas,
        array $vendedores
    ): void {
        $hoja = CargaFacturasEsquema::HOJA_FACTURAS;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $fin = 1 + self::MARGEN_FILAS;

        // ID_FACTURA, IDENTIFICACION_CLIENTE, ESTABLECIMIENTO y PUNTO_EMISION son
        // códigos: deben viajar como texto para no perder los ceros a la izquierda
        // (001 no puede volverse 1, ni una cédula perder su primer dígito).
        foreach ([1, 3, 5, 6] as $col) {
            $this->formatoTexto($h, $col, 2, $fin);
        }
        $this->formatoFecha($h, 2, 2, $fin);

        $this->listaDesplegable($h, 5, 2, $fin,
            '=' . CargaFacturasEsquema::HOJA_REF_PUNTOS . '!$A$2:$A$' . (count($puntos) + 1));
        $this->listaDesplegable($h, 6, 2, $fin,
            '=' . CargaFacturasEsquema::HOJA_REF_PUNTOS . '!$B$2:$B$' . (count($puntos) + 1));
        if ($bodegas) {
            $this->listaDesplegable($h, 7, 2, $fin,
                '=' . CargaFacturasEsquema::HOJA_REF_BODEGAS . '!$A$2:$A$' . (count($bodegas) + 1));
        }
        if ($vendedores) {
            $this->listaDesplegable($h, 8, 2, $fin,
                '=' . CargaFacturasEsquema::HOJA_REF_VENDEDORES . '!$A$2:$A$' . (count($vendedores) + 1));
        }

        $this->finalizarHojaDatos($h, $hoja, 1);
    }

    private function crearHojaDetalles(Spreadsheet $libro, array $mapaIva): void
    {
        $hoja = CargaFacturasEsquema::HOJA_DETALLES;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $fin = 1 + self::MARGEN_FILAS;

        // ID_FACTURA, CODIGO_PRODUCTO, TIPO, CODIGO_IVA, LOTE y NUP son códigos.
        foreach ([1, 2, 3, 8, 9, 11] as $col) {
            $this->formatoTexto($h, $col, 2, $fin);
        }
        $this->formatoFecha($h, 10, 2, $fin);

        // TIPO: lista literal, no hace falta una hoja de referencia para dos valores.
        $this->listaDesplegable($h, 3, 2, $fin, '"Producto,Servicio"', true);
        $this->listaDesplegable($h, 8, 2, $fin,
            '=' . CargaFacturasEsquema::HOJA_REF_IVA . '!$A$2:$A$' . (count($mapaIva) + 1));

        $this->finalizarHojaDatos($h, $hoja, 1);
    }

    private function crearHojaInfoAdicional(Spreadsheet $libro): void
    {
        $hoja = CargaFacturasEsquema::HOJA_INFO_ADICIONAL;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $this->formatoTexto($h, 1, 2, 1 + self::MARGEN_FILAS);

        $this->finalizarHojaDatos($h, $hoja, 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas de referencia
    // ─────────────────────────────────────────────────────────────────────────

    private function crearHojasReferencia(
        Spreadsheet $libro,
        array $mapaIva,
        array $puntos,
        array $bodegas,
        array $vendedores
    ): void {
        $ref = CargaFacturasEsquema::hojasReferencia();

        $datos = [
            CargaFacturasEsquema::HOJA_REF_IVA => array_map(
                static fn($t) => [$t['codigo'], $t['tarifa'], $t['porcentaje_iva'] . '%'],
                array_values($mapaIva)
            ),
            CargaFacturasEsquema::HOJA_REF_PUNTOS => array_map(
                static fn($p) => [$p['establecimiento'], $p['punto_emision'], $p['direccion']],
                array_values($puntos)
            ),
            CargaFacturasEsquema::HOJA_REF_BODEGAS => array_map(
                static fn($b) => [$b['nombre']],
                array_values($bodegas)
            ),
            CargaFacturasEsquema::HOJA_REF_VENDEDORES => array_map(
                static fn($v) => [$v['nombre']],
                array_values($vendedores)
            ),
        ];

        foreach ($ref as $nombreHoja => $encabezados) {
            if ($nombreHoja === CargaFacturasEsquema::HOJA_INSTRUCCIONES) {
                continue; // ya creada
            }

            $h = $libro->createSheet();
            $h->setTitle($nombreHoja);

            foreach ($encabezados as $i => $titulo) {
                $h->setCellValueExplicit([$i + 1, 1], $titulo, DataType::TYPE_STRING);
            }
            $this->estilarEncabezado($h, count($encabezados), self::COLOR_REFERENCIA);

            $fila = 2;
            foreach ($datos[$nombreHoja] ?? [] as $registro) {
                foreach (array_values($registro) as $i => $valor) {
                    $h->setCellValueExplicit([$i + 1, $fila], (string) $valor, DataType::TYPE_STRING);
                }
                $fila++;
            }

            $this->autoAnchoColumnas($h, count($encabezados));
            $h->freezePane('A2');
            $h->getProtection()->setSheet(true); // solo consulta
        }
    }

    private function crearHojaConfig(Spreadsheet $libro, int $idEmpresa, string $rotulo): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaFacturasEsquema::HOJA_CONFIG);

        $h->setCellValueExplicit([1, 1], 'id_empresa', DataType::TYPE_STRING);
        $h->setCellValueExplicit([2, 1], (string) $idEmpresa, DataType::TYPE_STRING);
        $h->setCellValueExplicit([1, 2], 'empresa', DataType::TYPE_STRING);
        $h->setCellValueExplicit([2, 2], $rotulo, DataType::TYPE_STRING);
        $h->setCellValueExplicit([1, 3], 'generado', DataType::TYPE_STRING);
        $h->setCellValueExplicit([2, 3], date('Y-m-d H:i:s'), DataType::TYPE_STRING);

        $h->getProtection()->setSheet(true);
        $h->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades de formato
    // ─────────────────────────────────────────────────────────────────────────

    private function nuevaHojaDatos(Spreadsheet $libro, string $nombreHoja): Worksheet
    {
        $h = $libro->createSheet();
        $h->setTitle($nombreHoja);

        $columnas = CargaFacturasEsquema::columnas($nombreHoja);
        foreach ($columnas as $i => $titulo) {
            $h->setCellValueExplicit([$i + 1, 1], $titulo, DataType::TYPE_STRING);
        }
        $this->estilarEncabezado($h, count($columnas), self::COLOR_DATOS);

        // La columna llave se distingue con otro tono.
        $h->getStyle([1, 1])->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::COLOR_LLAVE);

        return $h;
    }

    private function finalizarHojaDatos(Worksheet $h, string $nombreHoja, int $ultimaFila): void
    {
        $nCols = count(CargaFacturasEsquema::columnas($nombreHoja));

        // Ancho fijo: las hojas salen vacías, así que el auto-size dejaría todas
        // las columnas del tamaño de su encabezado y el usuario no vería nada.
        for ($c = 1; $c <= $nCols; $c++) {
            $letra = $h->getCell([$c, 1])->getColumn();
            $h->getColumnDimension($letra)->setWidth(20);
        }

        $h->freezePane('A2');
        $ultimaCol = $h->getCell([$nCols, 1])->getColumn();
        $h->setAutoFilter('A1:' . $ultimaCol . max($ultimaFila, 1));
    }

    private function estilarEncabezado(Worksheet $h, int $nCols, string $colorArgb): void
    {
        if ($nCols < 1) {
            return;
        }
        $ultimaCol = $h->getCell([$nCols, 1])->getColumn();
        $rango = 'A1:' . $ultimaCol . '1';

        $estilo = $h->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorArgb);
        $estilo->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $estilo->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $h->getRowDimension(1)->setRowHeight(22);
    }

    private function autoAnchoColumnas(Worksheet $h, int $nCols): void
    {
        for ($c = 1; $c <= $nCols; $c++) {
            $letra = $h->getCell([$c, 1])->getColumn();
            $h->getColumnDimension($letra)->setAutoSize(true);
        }
    }

    /**
     * Fuerza formato texto en un rango de una columna. Sin esto, Excel convierte
     * "001" en el número 1 y el código del establecimiento deja de coincidir.
     */
    private function formatoTexto(Worksheet $h, int $columna, int $filaInicio, int $filaFin): void
    {
        $letra = $h->getCell([$columna, 1])->getColumn();
        $h->getStyle($letra . $filaInicio . ':' . $letra . $filaFin)
            ->getNumberFormat()->setFormatCode('@');
    }

    /** Formato de fecha ISO, que es como lo lee el validador. */
    private function formatoFecha(Worksheet $h, int $columna, int $filaInicio, int $filaFin): void
    {
        $letra = $h->getCell([$columna, 1])->getColumn();
        $h->getStyle($letra . $filaInicio . ':' . $letra . $filaFin)
            ->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    }

    /**
     * Aplica una lista desplegable a un rango de filas de una columna.
     *
     * @param string $formula Lista literal ("A,B") o referencia (=Hoja!$A$2:$A$10).
     */
    private function listaDesplegable(
        Worksheet $h,
        int $columna,
        int $filaInicio,
        int $filaFin,
        string $formula,
        bool $literal = false
    ): void {
        if ($filaFin < $filaInicio) {
            return;
        }

        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setErrorStyle(DataValidation::STYLE_STOP);
        $dv->setAllowBlank(true);
        $dv->setShowInputMessage(true);
        $dv->setShowErrorMessage(true);
        $dv->setShowDropDown(true);
        $dv->setErrorTitle('Valor no permitido');
        $dv->setError('Elija uno de los valores de la lista.');
        $dv->setFormula1($formula);

        // Se aplica al RANGO completo: así no se materializan celdas vacías
        // (que inflarían el archivo y correrían la última fila con datos).
        $letra = $h->getCell([$columna, 1])->getColumn();
        $h->setDataValidation($letra . $filaInicio . ':' . $letra . $filaFin, $dv);
    }
}
