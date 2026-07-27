<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CargaSuscripcionesRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Genera la plantilla Excel de carga de suscripciones.
 *
 * Las hojas de datos (Suscripciones, Detalle) van vacías —esta carga solo crea
 * suscripciones nuevas— con desplegables contra las hojas de referencia, que sí
 * vienen pobladas con los catálogos de la empresa activa (clientes, productos,
 * periodicidades y tarifas de IVA).
 */
class CargaSuscripcionesPlantillaService
{
    private CargaSuscripcionesRepository $repository;

    private const COLOR_DATOS      = 'FF1F4E79';
    private const COLOR_REFERENCIA = 'FF7F7F7F';
    private const COLOR_LLAVE      = 'FF2E75B6';

    /** Filas en blanco con desplegables para que el usuario escriba. */
    private const FILAS_EDITABLES = 300;

    /** Filas de la hoja Detalle que traen la CLAVE precargada por fórmula. */
    private const FILAS_AYUDA_CLAVE = 5;

    public function __construct(CargaSuscripcionesRepository $repository)
    {
        $this->repository = $repository;
    }

    public function generar(int $idEmpresa): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->removeSheetByIndex(0);

        $empresa = $this->repository->getEmpresa($idEmpresa);
        $rotulo  = $empresa
            ? ($empresa['nombre'] . ' - RUC ' . $empresa['ruc'])
            : ('ID Empresa ' . $idEmpresa);

        $clientes       = $this->repository->getClientesParaPlantilla($idEmpresa);
        $productos      = $this->repository->getProductosParaPlantilla($idEmpresa);
        $periodicidades = $this->repository->getPeriodicidadesParaPlantilla();
        $tarifasIva     = $this->repository->getMapaTarifasIva(true);

        $this->crearHojaInstrucciones($libro, $rotulo);
        $this->crearHojaSuscripciones($libro, count($clientes), count($periodicidades));
        $this->crearHojaDetalle($libro, count($productos), count($tarifasIva));

        $this->crearHojasReferencia($libro, $clientes, $productos, $periodicidades, $tarifasIva);
        $this->crearHojaConfig($libro, $idEmpresa, $rotulo);

        $libro->setActiveSheetIndexByName(CargaSuscripcionesEsquema::HOJA_INSTRUCCIONES);

        return $libro;
    }

    public function nombreArchivo(int $idEmpresa): string
    {
        return 'carga_suscripciones_empresa' . $idEmpresa . '_' . date('Ymd_His') . '.xlsx';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas de datos
    // ─────────────────────────────────────────────────────────────────────────

    private function crearHojaInstrucciones(Spreadsheet $libro, string $rotulo): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaSuscripcionesEsquema::HOJA_INSTRUCCIONES);

        $h->setCellValueExplicit([1, 1], $rotulo, DataType::TYPE_STRING);
        $h->getStyle([1, 1])->getFont()->setBold(true)->setSize(12);

        $fila = 3;
        foreach (CargaSuscripcionesEsquema::textoInstrucciones() as $linea) {
            $h->setCellValueExplicit([1, $fila], $linea, DataType::TYPE_STRING);
            if ($linea !== '' && $linea === mb_strtoupper($linea) && !str_starts_with($linea, ' ')) {
                $h->getStyle([1, $fila])->getFont()->setBold(true);
            }
            $fila++;
        }

        $h->getColumnDimension('A')->setWidth(100);
        $h->getProtection()->setSheet(true);
    }

    private function crearHojaSuscripciones(Spreadsheet $libro, int $nClientes, int $nPeriodicidades): void
    {
        $hoja = CargaSuscripcionesEsquema::HOJA_SUSCRIPCIONES;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $margen = self::FILAS_EDITABLES + 1;

        // RUC_CLIENTE (col 2) → Ref_Clientes
        if ($nClientes > 0) {
            $this->listaDesplegable($h, 2, 2, $margen,
                '=' . CargaSuscripcionesEsquema::HOJA_REF_CLIENTES . '!$A$2:$A$' . ($nClientes + 1));
        }
        // PERIODICIDAD (col 3) → Ref_Periodicidades
        if ($nPeriodicidades > 0) {
            $this->listaDesplegable($h, 3, 2, $margen,
                '=' . CargaSuscripcionesEsquema::HOJA_REF_PERIODICIDADES . '!$A$2:$A$' . ($nPeriodicidades + 1));
        }
        // FORMA_COBRO (col 7), TIPO_COMPROBANTE (col 8), ESTADO (col 9) → literales
        $this->listaDesplegable($h, 7, 2, $margen, '"Credito,Tarjeta"', true);
        $this->listaDesplegable($h, 8, 2, $margen, '"Factura,Recibo"', true);
        $this->listaDesplegable($h, 9, 2, $margen, '"Activo,Pausado,Suspendido,Cancelado"', true);

        // Formato de fecha visible en las columnas de fecha (informativo).
        foreach ([4, 5, 6] as $col) {
            $letra = $h->getCell([$col, 1])->getColumn();
            $h->getStyle($letra . '2:' . $letra . $margen)
                ->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        }

        $this->finalizarHojaDatos($h, $hoja, 1);
    }

    private function crearHojaDetalle(Spreadsheet $libro, int $nProductos, int $nIva): void
    {
        $hoja = CargaSuscripcionesEsquema::HOJA_DETALLE;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $margen = self::FILAS_EDITABLES + 1;

        // CODIGO_PRODUCTO (col 2) → Ref_Productos
        if ($nProductos > 0) {
            $this->listaDesplegable($h, 2, 2, $margen,
                '=' . CargaSuscripcionesEsquema::HOJA_REF_PRODUCTOS . '!$A$2:$A$' . ($nProductos + 1));
        }
        // CODIGO_IVA (col 6) → Ref_IVA
        if ($nIva > 0) {
            $this->listaDesplegable($h, 6, 2, $margen,
                '=' . CargaSuscripcionesEsquema::HOJA_REF_IVA . '!$A$2:$A$' . ($nIva + 1));
        }

        // Ayuda: precargar la CLAVE de las primeras filas con una fórmula que trae
        // la clave de la hoja Suscripciones (misma fila). El usuario arrastra la
        // fórmula hacia abajo y repite la clave cuando una suscripción tiene varios
        // productos. IF(...="") evita mostrar "0" cuando la cabecera está vacía.
        $suscHoja = CargaSuscripcionesEsquema::HOJA_SUSCRIPCIONES;
        for ($i = 0; $i < self::FILAS_AYUDA_CLAVE; $i++) {
            $filaExcel = 2 + $i;
            $h->setCellValue(
                [1, $filaExcel],
                '=IF(' . $suscHoja . '!A' . $filaExcel . '="","",' . $suscHoja . '!A' . $filaExcel . ')'
            );
        }

        $this->finalizarHojaDatos($h, $hoja, 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas de referencia y control
    // ─────────────────────────────────────────────────────────────────────────

    private function crearHojasReferencia(
        Spreadsheet $libro,
        array $clientes,
        array $productos,
        array $periodicidades,
        array $tarifasIva
    ): void {
        $ref = CargaSuscripcionesEsquema::hojasReferencia();

        $datos = [
            CargaSuscripcionesEsquema::HOJA_REF_CLIENTES => array_map(
                fn($c) => [$c['identificacion'], $c['nombre']],
                $clientes
            ),
            CargaSuscripcionesEsquema::HOJA_REF_PRODUCTOS => array_map(
                fn($p) => [$p['codigo'], $p['nombre'], $this->numero($p['precio_base']), $p['codigo_iva'] ?? ''],
                $productos
            ),
            CargaSuscripcionesEsquema::HOJA_REF_PERIODICIDADES => array_map(
                fn($p) => [$p['codigo'], $p['nombre'], $p['descripcion'] ?? ''],
                $periodicidades
            ),
            CargaSuscripcionesEsquema::HOJA_REF_IVA => array_map(
                fn($k, $v) => [$k, $v['tarifa'], $v['porcentaje_iva'] . '%'],
                array_keys($tarifasIva),
                $tarifasIva
            ),
        ];

        foreach ($ref as $nombreHoja => $encabezados) {
            if ($nombreHoja === CargaSuscripcionesEsquema::HOJA_INSTRUCCIONES) {
                continue;
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
            $h->getProtection()->setSheet(true);
        }
    }

    private function crearHojaConfig(Spreadsheet $libro, int $idEmpresa, string $rotulo): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaSuscripcionesEsquema::HOJA_CONFIG);

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
    // Utilidades de formato (mismas convenciones que la carga de productos)
    // ─────────────────────────────────────────────────────────────────────────

    private function nuevaHojaDatos(Spreadsheet $libro, string $nombreHoja): Worksheet
    {
        $h = $libro->createSheet();
        $h->setTitle($nombreHoja);

        $columnas = CargaSuscripcionesEsquema::columnas($nombreHoja);
        foreach ($columnas as $i => $titulo) {
            $h->setCellValueExplicit([$i + 1, 1], $titulo, DataType::TYPE_STRING);
        }
        $this->estilarEncabezado($h, count($columnas), self::COLOR_DATOS);

        $h->getStyle([1, 1])->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::COLOR_LLAVE);

        return $h;
    }

    private function finalizarHojaDatos(Worksheet $h, string $nombreHoja, int $ultimaFila): void
    {
        $nCols = count(CargaSuscripcionesEsquema::columnas($nombreHoja));
        $this->autoAnchoColumnas($h, $nCols);

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

        $letra = $h->getCell([$columna, 1])->getColumn();
        $h->setDataValidation($letra . $filaInicio . ':' . $letra . $filaFin, $dv);
    }

    private function numero($valor): float
    {
        return round((float) $valor, 6);
    }
}
