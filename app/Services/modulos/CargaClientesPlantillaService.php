<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\DiasVisita;
use App\repositories\modulos\CargaClientesRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Genera la plantilla Excel de carga de clientes, ya poblada con los clientes de
 * la empresa activa (o solo los del usuario, si no tiene acceso total).
 *
 * La estructura la define CargaClientesEsquema, la misma que usa el validador.
 */
class CargaClientesPlantillaService
{
    private const COLOR_DATOS      = 'FF1F4E79'; // azul: hoja editable
    private const COLOR_REFERENCIA = 'FF7F7F7F'; // gris: hojas de consulta
    private const COLOR_LLAVE      = 'FF2E75B6'; // azul claro: columna llave

    /** Filas extra (con listas y formato texto) para clientes nuevos. */
    private const FILAS_EXTRA = 500;

    private CargaClientesRepository $repository;

    public function __construct(CargaClientesRepository $repository)
    {
        $this->repository = $repository;
    }

    public function generar(int $idEmpresa, ?int $idUsuarioFiltro = null): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->removeSheetByIndex(0);

        $empresa = $this->repository->getEmpresa($idEmpresa);
        $rotulo  = $empresa
            ? ($empresa['nombre'] . ' - RUC ' . $empresa['ruc'])
            : ('ID Empresa ' . $idEmpresa);

        $catalogos = new CargaClientesCatalogos($this->repository, $idEmpresa);
        $clientes  = $this->repository->getClientes($idEmpresa, $idUsuarioFiltro);

        $this->crearHojaInstrucciones($libro, $rotulo);
        $this->crearHojaClientes($libro, $clientes, $catalogos);
        $this->crearHojasReferencia($libro, $catalogos);
        $this->crearHojaConfig($libro, $idEmpresa, $rotulo);

        $libro->setActiveSheetIndexByName(CargaClientesEsquema::HOJA_CLIENTES);

        return $libro;
    }

    public function nombreArchivo(int $idEmpresa): string
    {
        return 'carga_clientes_empresa' . $idEmpresa . '_' . date('Ymd_His') . '.xlsx';
    }

    /**
     * Celdas de la hoja Clientes para un cliente existente (una fila de
     * CargaClientesRepository::getClientes()), en el orden del esquema.
     *
     * Público porque el validador lo usa en "Actualizar solo algunas columnas"
     * para rellenar las columnas no elegidas con el valor actual: así lo que se
     * conserva es exactamente lo que la plantilla habría exportado.
     */
    public static function valoresFila(array $c, CargaClientesCatalogos $catalogos): array
    {
        $dias = DiasVisita::normalizarDias($c['dias_visita'] ?? null);
        $frec = strtoupper(trim((string) ($c['frecuencia_visita'] ?? '')));

        return [
            trim((string) $c['identificacion']),
            CargaClientesEsquema::TIPOS[trim((string) $c['tipo_id'])] ?? trim((string) $c['tipo_id']),
            (string) $c['nombre'],
            (string) ($c['email'] ?? ''),
            (string) ($c['telefono'] ?? ''),
            (string) ($c['direccion'] ?? ''),
            $catalogos->nombreProvincia($c['provincia'] ?? null),
            $catalogos->nombreCiudad($c['provincia'] ?? null, $c['ciudad'] ?? null),
            (int) ($c['plazo'] ?? 0),
            $catalogos->textoVendedor(isset($c['id_vendedor']) ? (int) $c['id_vendedor'] : null),
            $catalogos->codigoFormaPago(isset($c['id_forma_pago_sri']) ? (int) $c['id_forma_pago_sri'] : null),
            ((int) ($c['status'] ?? 1) === 1) ? 'Activo' : 'Inactivo',
            (string) ($c['observaciones'] ?? ''),
            $dias ? implode(', ', array_map(static fn($d) => DiasVisita::DIAS_CORTO[$d], $dias)) : '',
            DiasVisita::FRECUENCIAS[$frec] ?? '',
            (string) ($c['semanas_visita'] ?? ''),
            (string) ($c['hora_visita_desde'] ?? ''),
            (string) ($c['hora_visita_hasta'] ?? ''),
            $c['orden_visita'] !== null && $c['orden_visita'] !== '' ? (int) $c['orden_visita'] : '',
            (string) ($c['observacion_visita'] ?? ''),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function crearHojaInstrucciones(Spreadsheet $libro, string $rotulo): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaClientesEsquema::HOJA_INSTRUCCIONES);

        $h->setCellValueExplicit([1, 1], $rotulo, DataType::TYPE_STRING);
        $h->getStyle([1, 1])->getFont()->setBold(true)->setSize(12);

        $fila = 3;
        foreach (CargaClientesEsquema::textoInstrucciones() as $linea) {
            $h->setCellValueExplicit([1, $fila], $linea, DataType::TYPE_STRING);
            if ($linea !== '' && $linea === mb_strtoupper($linea) && !str_starts_with($linea, ' ')) {
                $h->getStyle([1, $fila])->getFont()->setBold(true);
            }
            $fila++;
        }

        $h->getColumnDimension('A')->setWidth(100);
        $h->getProtection()->setSheet(true);
    }

    private function crearHojaClientes(Spreadsheet $libro, array $clientes, CargaClientesCatalogos $catalogos): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaClientesEsquema::HOJA_CLIENTES);

        $columnas = CargaClientesEsquema::COLUMNAS;
        foreach ($columnas as $i => $titulo) {
            $h->setCellValueExplicit([$i + 1, 1], $titulo, DataType::TYPE_STRING);
        }
        $this->estilarEncabezado($h, count($columnas), self::COLOR_DATOS);
        $h->getStyle([1, 1])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_LLAVE);

        $comoTexto = array_map(
            static fn($col) => CargaClientesEsquema::indice($col) + 1,
            CargaClientesEsquema::COLUMNAS_TEXTO
        );

        $fila = 2;
        foreach ($clientes as $c) {
            foreach (self::valoresFila($c, $catalogos) as $i => $v) {
                $col = $i + 1;
                if (in_array($col, $comoTexto, true) || is_string($v)) {
                    $h->setCellValueExplicit([$col, $fila], (string) $v, DataType::TYPE_STRING);
                } else {
                    $h->setCellValue([$col, $fila], $v);
                }
            }
            $fila++;
        }

        $ultima = max($fila - 1, 2);
        $margen = $ultima + self::FILAS_EXTRA;

        // Formato texto también en las filas vacías: así una cédula nueva no
        // pierde el cero inicial ni una hora se convierte en fracción del día.
        foreach ($comoTexto as $col) {
            $letra = $h->getCell([$col, 1])->getColumn();
            $h->getStyle($letra . '2:' . $letra . $margen)
                ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $col = static fn(string $nombre) => CargaClientesEsquema::indice($nombre) + 1;
        $refProv = CargaClientesEsquema::HOJA_REF_PROVINCIAS;
        $refVend = CargaClientesEsquema::HOJA_REF_VENDEDORES;
        $refFp   = CargaClientesEsquema::HOJA_REF_FORMAS_PAGO;

        $this->listaDesplegable($h, $col('TIPO_IDENTIFICACION'), 2, $margen,
            '"' . implode(',', CargaClientesEsquema::TIPOS) . '"');
        $nProv = count($catalogos->filasProvincias());
        if ($nProv > 0) {
            $this->listaDesplegable($h, $col('PROVINCIA'), 2, $margen, '=' . $refProv . '!$A$2:$A$' . ($nProv + 1));
        }
        $nVend = count($catalogos->filasVendedores());
        if ($nVend > 0) {
            $this->listaDesplegable($h, $col('VENDEDOR'), 2, $margen, '=' . $refVend . '!$A$2:$A$' . ($nVend + 1), false);
        }
        $nFp = count($catalogos->filasFormasPago());
        if ($nFp > 0) {
            $this->listaDesplegable($h, $col('FORMA_PAGO_SRI'), 2, $margen, '=' . $refFp . '!$A$2:$A$' . ($nFp + 1));
        }
        $this->listaDesplegable($h, $col('ESTADO'), 2, $margen, '"Activo,Inactivo"');
        $this->listaDesplegable($h, $col('FRECUENCIA_VISITA'), 2, $margen,
            '"' . implode(',', \App\Helpers\DiasVisita::FRECUENCIAS) . '"');

        $nCols = count($columnas);
        for ($c = 1; $c <= $nCols; $c++) {
            $h->getColumnDimension($h->getCell([$c, 1])->getColumn())->setAutoSize(true);
        }
        // Las columnas de texto largo no deben quedar de 300 caracteres de ancho.
        foreach (['NOMBRE' => 45, 'DIRECCION' => 45, 'OBSERVACIONES' => 40, 'OBSERVACION_VISITA' => 35, 'EMAIL' => 35] as $nombre => $ancho) {
            $letra = $h->getCell([$col($nombre), 1])->getColumn();
            $h->getColumnDimension($letra)->setAutoSize(false)->setWidth($ancho);
        }

        $h->freezePane('B2');
        $h->setAutoFilter('A1:' . $h->getCell([$nCols, 1])->getColumn() . $ultima);
    }

    private function crearHojasReferencia(Spreadsheet $libro, CargaClientesCatalogos $catalogos): void
    {
        $tipos = [];
        foreach (CargaClientesEsquema::TIPOS as $codigo => $rotulo) {
            $tipos[] = [$rotulo, (string) $codigo];
        }

        $datos = [
            CargaClientesEsquema::HOJA_REF_TIPOS       => $tipos,
            CargaClientesEsquema::HOJA_REF_PROVINCIAS  => $catalogos->filasProvincias(),
            CargaClientesEsquema::HOJA_REF_CIUDADES    => $catalogos->filasCiudades(),
            CargaClientesEsquema::HOJA_REF_VENDEDORES  => $catalogos->filasVendedores(),
            CargaClientesEsquema::HOJA_REF_FORMAS_PAGO => $catalogos->filasFormasPago(),
        ];

        foreach (CargaClientesEsquema::hojasReferencia() as $nombreHoja => $encabezados) {
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

            for ($c = 1; $c <= count($encabezados); $c++) {
                $h->getColumnDimension($h->getCell([$c, 1])->getColumn())->setAutoSize(true);
            }
            $h->freezePane('A2');
            $h->getProtection()->setSheet(true);
        }
    }

    private function crearHojaConfig(Spreadsheet $libro, int $idEmpresa, string $rotulo): void
    {
        $h = $libro->createSheet();
        $h->setTitle(CargaClientesEsquema::HOJA_CONFIG);

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

    private function estilarEncabezado(Worksheet $h, int $nCols, string $colorArgb): void
    {
        if ($nCols < 1) {
            return;
        }
        $rango = 'A1:' . $h->getCell([$nCols, 1])->getColumn() . '1';

        $estilo = $h->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorArgb);
        $estilo->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $estilo->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $h->getRowDimension(1)->setRowHeight(22);
    }

    /**
     * Lista desplegable sobre un rango de una columna.
     *
     * @param bool $estricta false = solo sugiere (acepta otro texto, p. ej. la
     *                       identificación de un vendedor de nombre repetido).
     */
    private function listaDesplegable(
        Worksheet $h,
        int $columna,
        int $filaInicio,
        int $filaFin,
        string $formula,
        bool $estricta = true
    ): void {
        if ($filaFin < $filaInicio) {
            return;
        }

        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setErrorStyle($estricta ? DataValidation::STYLE_STOP : DataValidation::STYLE_INFORMATION);
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
}
