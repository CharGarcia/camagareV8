<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CargaProductosRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Genera la plantilla Excel de carga de productos, ya poblada con los productos
 * y servicios existentes de la empresa activa.
 *
 * La estructura de hojas y columnas la define CargaProductosEsquema, la misma
 * que usa el validador, para que nunca se desincronicen.
 */
class CargaProductosPlantillaService
{
    private CargaProductosRepository $repository;

    /** Colores de encabezado. */
    private const COLOR_DATOS      = 'FF1F4E79'; // azul: hojas editables
    private const COLOR_REFERENCIA = 'FF7F7F7F'; // gris: hojas de consulta
    private const COLOR_LLAVE      = 'FF2E75B6'; // azul claro: columna llave

    public function __construct(CargaProductosRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Construye el libro completo.
     */
    public function generar(int $idEmpresa): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->removeSheetByIndex(0);

        $empresa = $this->repository->getEmpresa($idEmpresa);
        $rotulo  = $empresa
            ? ($empresa['nombre'] . ' - RUC ' . $empresa['ruc'])
            : ('ID Empresa ' . $idEmpresa);

        // Catálogos y datos, en pocas consultas.
        $productos      = $this->repository->getProductosParaPlantilla($idEmpresa);
        $precios        = $this->repository->getPreciosParaPlantilla($idEmpresa);
        $variantes      = $this->repository->getVariantesParaPlantilla($idEmpresa);
        $componentes    = $this->repository->getComponentesParaPlantilla($idEmpresa);
        $stockBodegas   = $this->repository->getStockBodegasParaPlantilla($idEmpresa);
        $homologaciones = $this->repository->getHomologacionesParaPlantilla($idEmpresa);

        // Solo tarifas vigentes: las derogadas (12%, 14%) no deben ofrecerse
        // para productos nuevos, aunque el validador sí las siga aceptando en
        // los productos históricos que ya las tienen.
        $mapaIva      = $this->repository->getMapaTarifasIva(true);
        $mapaMedidas  = $this->repository->getMapaUnidadesMedida($idEmpresa);
        $mapaCategs   = $this->repository->getMapaCategorias($idEmpresa);
        $mapaMarcas   = $this->repository->getMapaMarcas($idEmpresa);
        $mapaBodegas  = $this->repository->getMapaBodegas($idEmpresa);
        $mapaIce      = $this->repository->getMapaIce($idEmpresa);

        // El orden de creación define el orden de las pestañas.
        $this->crearHojaInstrucciones($libro, $rotulo);
        $this->crearHojaProductos($libro, $productos, $mapaIva, $mapaMedidas, $mapaIce);
        $this->crearHojaPrecios($libro, $precios);
        $this->crearHojaVariantes($libro, $variantes);
        $this->crearHojaComponentes($libro, $componentes);
        $this->crearHojaStockBodegas($libro, $stockBodegas);
        $this->crearHojaHomologaciones($libro, $homologaciones);

        $this->crearHojasReferencia($libro, $mapaIva, $mapaMedidas, $mapaCategs, $mapaMarcas, $mapaBodegas, $mapaIce);
        $this->crearHojaConfig($libro, $idEmpresa, $rotulo);

        $libro->setActiveSheetIndexByName(CargaProductosEsquema::HOJA_INSTRUCCIONES);

        return $libro;
    }

    /** Nombre sugerido del archivo. */
    public function nombreArchivo(int $idEmpresa): string
    {
        return 'carga_productos_empresa' . $idEmpresa . '_' . date('Ymd_His') . '.xlsx';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas de datos
    // ─────────────────────────────────────────────────────────────────────────

    private function crearHojaInstrucciones(Spreadsheet $libro, string $rotulo): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaProductosEsquema::HOJA_INSTRUCCIONES);

        $h->setCellValueExplicit([1, 1], $rotulo, DataType::TYPE_STRING);
        $h->getStyle([1, 1])->getFont()->setBold(true)->setSize(12);

        $fila = 3;
        foreach (CargaProductosEsquema::textoInstrucciones() as $linea) {
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

    private function crearHojaProductos(
        Spreadsheet $libro,
        array $productos,
        array $mapaIva,
        array $mapaMedidas,
        array $mapaIce
    ): void {
        $hoja = CargaProductosEsquema::HOJA_PRODUCTOS;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $fila = 2;
        foreach ($productos as $p) {
            $opciones   = $this->decodificarOpciones($p['opciones'] ?? null);
            $esServicio = ($p['tipo_produccion'] === CargaProductosEsquema::TIPO_SERVICIO);

            // Un servicio nunca maneja inventario ni unidad de medida. Hay
            // registros heredados que sí los tienen; se exportan normalizados
            // porque ProductoService los fuerza igual al guardar.
            $inventariable = $esServicio ? false : $p['inventariable'];
            $codigoMedida  = $esServicio ? '' : ($p['codigo_medida'] ?? '');

            $valores = [
                $p['codigo'],
                $p['nombre'],
                $esServicio ? 'Servicio' : 'Producto',
                $p['codigo_auxiliar'] ?? '',
                $p['codigo_barras'] ?? '',
                $this->numero($p['precio_base']),
                $this->numero($p['costo_producto']),
                $p['codigo_iva'] ?? '',
                $codigoMedida,
                $p['categoria'] ?? '',
                $p['marca'] ?? '',
                $this->siNo($inventariable),
                $this->numero($p['stock_minimo']),
                $this->numero($p['stock_maximo']),
                $this->siNo($opciones['compra']),
                $this->siNo($opciones['venta']),
                $p['codigo_ice'] ?? '',
                ((int) $p['status'] === 1) ? 'Activo' : 'Inactivo',
            ];

            // Columnas que deben viajar como texto para no perder ceros a la
            // izquierda ni convertirse en número/fecha: códigos.
            $comoTexto = [1, 4, 5, 8, 9, 17];

            foreach ($valores as $i => $v) {
                $col = $i + 1;
                if (in_array($col, $comoTexto, true)) {
                    $h->setCellValueExplicit([$col, $fila], (string) $v, DataType::TYPE_STRING);
                } else {
                    $h->setCellValue([$col, $fila], $v);
                }
            }
            $fila++;
        }

        $ultima = max($fila - 1, 2);
        $margen = $ultima + 200; // filas extra para productos nuevos

        // Listas desplegables contra las hojas de referencia.
        $this->listaDesplegable($h, 3, 2, $margen, '"Producto,Servicio"', true);
        $this->listaDesplegable($h, 8, 2, $margen,
            '=' . CargaProductosEsquema::HOJA_REF_IVA . '!$A$2:$A$' . (count($mapaIva) + 1));
        $this->listaDesplegable($h, 9, 2, $margen,
            '=' . CargaProductosEsquema::HOJA_REF_MEDIDAS . '!$A$2:$A$' . (count($mapaMedidas) + 1));
        foreach ([12, 15, 16] as $col) {
            $this->listaDesplegable($h, $col, 2, $margen, '"Si,No"', true);
        }
        if ($mapaIce) {
            $this->listaDesplegable($h, 17, 2, $margen,
                '=' . CargaProductosEsquema::HOJA_REF_ICE . '!$A$2:$A$' . (count($mapaIce) + 1));
        }
        $this->listaDesplegable($h, 18, 2, $margen, '"Activo,Inactivo"', true);

        $this->finalizarHojaDatos($h, $hoja, $ultima);
    }

    private function crearHojaPrecios(Spreadsheet $libro, array $filas): void
    {
        $hoja = CargaProductosEsquema::HOJA_PRECIOS;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $fila = 2;
        foreach ($filas as $p) {
            $h->setCellValueExplicit([1, $fila], (string) $p['codigo_producto'], DataType::TYPE_STRING);
            $h->setCellValue([2, $fila], $p['nombre_precio']);
            $h->setCellValue([3, $fila], $this->numero($p['precio']));
            $h->setCellValueExplicit([4, $fila], (string) ($p['valido_desde'] ?? ''), DataType::TYPE_STRING);
            $h->setCellValueExplicit([5, $fila], (string) ($p['valido_hasta'] ?? ''), DataType::TYPE_STRING);
            $h->setCellValue([6, $fila], $this->siNo($p['estado']));
            $fila++;
        }

        $ultima = max($fila - 1, 2);
        $this->listaDesplegable($h, 6, 2, $ultima + 200, '"Si,No"', true);
        $this->finalizarHojaDatos($h, $hoja, $ultima);
    }

    private function crearHojaVariantes(Spreadsheet $libro, array $filas): void
    {
        $hoja = CargaProductosEsquema::HOJA_VARIANTES;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $fila = 2;
        foreach ($filas as $v) {
            $h->setCellValueExplicit([1, $fila], (string) $v['codigo_producto'], DataType::TYPE_STRING);
            $h->setCellValue([2, $fila], $v['nombre']);
            $h->setCellValue([3, $fila], $v['valor']);
            $h->setCellValue([4, $fila], $this->numero($v['precio_adicional']));
            $fila++;
        }

        $this->finalizarHojaDatos($h, $hoja, max($fila - 1, 2));
    }

    private function crearHojaComponentes(Spreadsheet $libro, array $filas): void
    {
        $hoja = CargaProductosEsquema::HOJA_COMPONENTES;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $fila = 2;
        foreach ($filas as $c) {
            $h->setCellValueExplicit([1, $fila], (string) $c['codigo_padre'], DataType::TYPE_STRING);
            $h->setCellValueExplicit([2, $fila], (string) $c['codigo_hijo'], DataType::TYPE_STRING);
            $h->setCellValue([3, $fila], $this->numero($c['cantidad']));
            $h->setCellValueExplicit([4, $fila], (string) ($c['codigo_medida'] ?? ''), DataType::TYPE_STRING);
            $fila++;
        }

        $this->finalizarHojaDatos($h, $hoja, max($fila - 1, 2));
    }

    private function crearHojaStockBodegas(Spreadsheet $libro, array $filas): void
    {
        $hoja = CargaProductosEsquema::HOJA_STOCK_BODEGAS;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $fila = 2;
        foreach ($filas as $b) {
            $h->setCellValueExplicit([1, $fila], (string) $b['codigo_producto'], DataType::TYPE_STRING);
            $h->setCellValue([2, $fila], $b['bodega']);
            $h->setCellValue([3, $fila], $this->numero($b['stock_minimo']));
            $h->setCellValue([4, $fila], $this->numero($b['stock_maximo']));
            $fila++;
        }

        $this->finalizarHojaDatos($h, $hoja, max($fila - 1, 2));
    }

    private function crearHojaHomologaciones(Spreadsheet $libro, array $filas): void
    {
        $hoja = CargaProductosEsquema::HOJA_HOMOLOGACIONES;
        $h = $this->nuevaHojaDatos($libro, $hoja);

        $fila = 2;
        foreach ($filas as $x) {
            $h->setCellValueExplicit([1, $fila], (string) $x['codigo_producto'], DataType::TYPE_STRING);
            $h->setCellValueExplicit([2, $fila], (string) $x['ruc_proveedor'], DataType::TYPE_STRING);
            $h->setCellValueExplicit([3, $fila], (string) $x['codigo_proveedor'], DataType::TYPE_STRING);
            $fila++;
        }

        // Aviso: el proveedor se enlaza por RUC/cédula y debe existir.
        $h->setCellValueExplicit(
            [5, 1],
            'El proveedor se relaciona por su RUC o cédula y debe existir en el sistema.',
            DataType::TYPE_STRING
        );
        $h->getStyle([5, 1])->getFont()->setItalic(true);

        $this->finalizarHojaDatos($h, $hoja, max($fila - 1, 2));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas de referencia y control
    // ─────────────────────────────────────────────────────────────────────────

    private function crearHojasReferencia(
        Spreadsheet $libro,
        array $mapaIva,
        array $mapaMedidas,
        array $mapaCategs,
        array $mapaMarcas,
        array $mapaBodegas,
        array $mapaIce
    ): void {
        $ref = CargaProductosEsquema::hojasReferencia();

        $datos = [
            CargaProductosEsquema::HOJA_REF_IVA => array_map(
                fn($k, $v) => [$k, $v['tarifa'], $v['porcentaje_iva'] . '%'],
                array_keys($mapaIva),
                $mapaIva
            ),
            CargaProductosEsquema::HOJA_REF_MEDIDAS => array_map(
                fn($v) => [$v['codigo'], $v['nombre'], $v['abreviatura'], $v['tipo_medida']],
                array_values($mapaMedidas)
            ),
            CargaProductosEsquema::HOJA_REF_CATEGORIAS => array_map(
                fn($v) => [$v['nombre']],
                array_values($mapaCategs)
            ),
            CargaProductosEsquema::HOJA_REF_MARCAS => array_map(
                fn($v) => [$v['nombre']],
                array_values($mapaMarcas)
            ),
            CargaProductosEsquema::HOJA_REF_BODEGAS => array_map(
                fn($v) => [$v['nombre']],
                array_values($mapaBodegas)
            ),
            CargaProductosEsquema::HOJA_REF_ICE => array_map(
                fn($v) => [$v['codigo_ats'], $v['nombre_ice'], $v['valor_ice'] . '%'],
                array_values($mapaIce)
            ),
        ];

        foreach ($ref as $nombreHoja => $encabezados) {
            if ($nombreHoja === CargaProductosEsquema::HOJA_INSTRUCCIONES) {
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
            $h->getProtection()->setSheet(true); // solo consulta
        }
    }

    private function crearHojaConfig(Spreadsheet $libro, int $idEmpresa, string $rotulo): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaProductosEsquema::HOJA_CONFIG);

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

        $columnas = CargaProductosEsquema::columnas($nombreHoja);
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
        $nCols = count(CargaProductosEsquema::columnas($nombreHoja));
        $this->autoAnchoColumnas($h, $nCols);

        // Encabezado congelado y autofiltro para que el usuario ordene y filtre.
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

    /** jsonb `opciones` => ['compra'=>bool,'venta'=>bool]. */
    private function decodificarOpciones($opciones): array
    {
        $def = ['compra' => true, 'venta' => true];
        if (empty($opciones)) {
            return $def;
        }
        $arr = is_array($opciones) ? $opciones : json_decode((string) $opciones, true);
        if (!is_array($arr)) {
            return $def;
        }
        return [
            'compra' => !empty($arr['compra']),
            'venta'  => !empty($arr['venta']),
        ];
    }

    private function siNo($valor): string
    {
        if (is_string($valor)) {
            $valor = in_array(strtolower($valor), ['t', 'true', '1', 'si', 'sí'], true);
        }
        return $valor ? 'Si' : 'No';
    }

    private function numero($valor): float
    {
        return round((float) $valor, 6);
    }
}
