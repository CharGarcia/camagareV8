<?php

declare(strict_types=1);

namespace App\Services\modulos;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extrae las líneas de movimiento (fecha, descripción, monto, referencia) de un
 * estado de cuenta bancario en Excel/CSV o PDF, según el perfil de mapeo
 * (conciliacion_perfiles.mapeo_columnas). Solo interesan los créditos (dinero
 * que entra a la cuenta) — este módulo concilia cobros de clientes, no todo
 * el movimiento de la cuenta (para eso está Control Bancario).
 *
 * No escribe en base de datos: devuelve un array de filas normalizadas; el
 * llamador (ConciliacionCobrosService) decide cómo persistirlas.
 */
class ConciliacionImportService
{
    /**
     * @return array{filas: array<int, array{fecha:string, descripcion:string, monto:float, referencia:?string}>, total_leidas: int, total_validas: int}
     */
    public function parsear(array $perfil, string $rutaArchivo): array
    {
        $mapeo = $perfil['mapeo_columnas'];
        if (is_string($mapeo)) {
            $mapeo = json_decode($mapeo, true) ?: [];
        }

        $tipoArchivo = strtoupper((string) $perfil['tipo_archivo']);
        $formatoFecha = (string) ($perfil['formato_fecha'] ?? 'd/m/Y');
        $separadorDecimal = (string) ($perfil['separador_decimal'] ?? '.');

        if ($tipoArchivo === 'PDF') {
            $filas = $this->parsearPdf($rutaArchivo, $mapeo, $formatoFecha, $separadorDecimal);
            return ['filas' => $filas, 'total_leidas' => count($filas), 'total_validas' => count($filas)];
        }

        $filasCrudas = $this->extraerFilasExcel($rutaArchivo, (int) ($perfil['fila_inicio'] ?? 0));
        $filas = [];
        foreach ($filasCrudas as $fila) {
            $normalizada = $this->normalizarFilaExcel($fila, $mapeo, $formatoFecha, $separadorDecimal);
            if ($normalizada !== null) {
                $filas[] = $normalizada;
            }
        }

        return ['filas' => $filas, 'total_leidas' => count($filasCrudas), 'total_validas' => count($filas)];
    }

    /**
     * Para el asistente de creación de perfil: primeras filas/líneas crudas del archivo,
     * sin aplicar mapeo (Excel), o TODAS las líneas de texto extraídas (PDF) para que el
     * usuario arme el regex de línea de datos viendo la estructura real del archivo. Si se
     * pasa un regex/tipo de crédito de prueba, también se incluye el resultado de aplicar
     * el parseo completo, para calibrar antes de guardar el perfil.
     *
     * @return array{lineas: array, filas_probadas: ?array}
     */
    public function previsualizar(string $rutaArchivo, string $tipoArchivo, int $filaInicio = 0, int $limite = 60, ?string $regexPrueba = null, ?string $tipoCreditoPrueba = null): array
    {
        if (strtoupper($tipoArchivo) === 'PDF') {
            $lineas = array_slice($this->extraerLineasPdf($rutaArchivo), 0, $limite);

            $filasProbadas = null;
            if ($regexPrueba !== null && trim($regexPrueba) !== '') {
                try {
                    $filasProbadas = $this->parsearPdf($rutaArchivo, [
                        'regex_linea' => $regexPrueba,
                        'tipo_credito' => $tipoCreditoPrueba,
                    ], 'd/m/Y', '.');
                } catch (\Throwable $e) {
                    $filasProbadas = ['error' => $e->getMessage()];
                }
            }

            return ['lineas' => $lineas, 'filas_probadas' => $filasProbadas];
        }

        return ['lineas' => array_slice($this->extraerFilasExcel($rutaArchivo, $filaInicio), 0, $limite), 'filas_probadas' => null];
    }

    // ── Excel / CSV ──────────────────────────────────────────────────────────

    /** Cada fila cruda de Excel es un array indexado por posición de columna (0-based). */
    private function extraerFilasExcel(string $ruta, int $filaInicio): array
    {
        $spreadsheet = IOFactory::load($ruta);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, false);

        if ($filaInicio > 0) {
            $filas = array_slice($filas, $filaInicio);
        }

        return $filas;
    }

    private function normalizarFilaExcel(array $filaCruda, array $mapeo, string $formatoFecha, string $separadorDecimal): ?array
    {
        $col = fn (string $campo) => isset($mapeo[$campo]['col']) ? ($filaCruda[(int) $mapeo[$campo]['col']] ?? null) : null;

        $fecha = $this->normalizarFecha((string) $col('fecha'), $formatoFecha);
        $monto = $this->normalizarMonto($col('monto'), $separadorDecimal);
        $descripcion = trim((string) $col('descripcion'));
        $referencia = $col('referencia');

        if ($fecha === null || $monto === null || $monto <= 0 || $descripcion === '') {
            return null;
        }

        return [
            'fecha' => $fecha,
            'descripcion' => $descripcion,
            'monto' => $monto,
            'referencia' => $referencia !== null ? trim((string) $referencia) : null,
        ];
    }

    // ── PDF ──────────────────────────────────────────────────────────────────

    /**
     * Un PDF de banco no tiene columnas reales: al extraer el texto, la descripción de
     * cada movimiento suele venir partida en varias líneas (ajuste de texto de la celda),
     * y la línea con los datos (fecha, documento, monto...) puede aparecer sola o con un
     * poco de descripción pegada al inicio (sin espacio, si la descripción es corta).
     *
     * Por eso el perfil de PDF no usa posiciones de columna fijas: usa un ÚNICO patrón
     * (regex_linea, con grupos nombrados (?<fecha>...) y (?<monto>...), y opcionalmente
     * (?<tipo>...) y (?<documento>...)/(?<referencia>...)) que reconoce la línea de datos
     * que CIERRA un movimiento. Se recorre el texto línea por línea acumulando todo lo que
     * no calza con el patrón (fragmentos de la descripción); al encontrar una línea que sí
     * calza, la descripción del movimiento es: lo acumulado + lo que hubiera ANTES del
     * punto donde empieza la fecha en esa misma línea.
     *
     * Si el perfil define tipo_credito (p. ej. 'C') y el regex captura un grupo (?<tipo>...),
     * se descartan las líneas cuyo tipo capturado no coincida (p. ej. los pagos/débitos 'D'):
     * varios bancos muestran el monto siempre en positivo y solo indican si es un ingreso o
     * un egreso mediante esa columna de tipo, no por el signo del monto.
     */
    private function parsearPdf(string $ruta, array $mapeo, string $formatoFecha, string $separadorDecimal): array
    {
        $regex = trim((string) ($mapeo['regex_linea'] ?? ''));
        if ($regex === '') {
            throw new \RuntimeException('El perfil no tiene configurado el patrón (regex) de línea de datos para PDF.');
        }
        $tipoCredito = !empty($mapeo['tipo_credito']) ? mb_strtoupper(trim((string) $mapeo['tipo_credito'])) : null;

        $filas = [];
        $buffer = [];

        foreach ($this->extraerLineasPdf($ruta) as $linea) {
            if (@preg_match($regex, $linea, $m, PREG_OFFSET_CAPTURE) !== 1) {
                $buffer[] = $linea;
                continue;
            }

            if (!isset($m['fecha']) || !isset($m['monto'])) {
                // El regex calzó pero no trae los grupos obligatorios: se trata como texto normal.
                $buffer[] = $linea;
                continue;
            }

            $tipoCapturado = isset($m['tipo']) ? mb_strtoupper(trim((string) $m['tipo'][0])) : null;
            if ($tipoCredito !== null && $tipoCapturado !== null && $tipoCapturado !== $tipoCredito) {
                $buffer = []; // línea de datos válida pero es un débito/salida: no interesa, se descarta con su descripción
                continue;
            }

            $offsetFecha = $m['fecha'][1];
            $prefijo = trim(mb_substr($linea, 0, $offsetFecha));
            if ($prefijo !== '') {
                $buffer[] = $prefijo;
            }
            $descripcion = trim(implode(' ', $buffer));
            $buffer = [];

            $fecha = $this->normalizarFecha((string) $m['fecha'][0], $formatoFecha);
            $monto = $this->normalizarMonto((string) $m['monto'][0], $separadorDecimal);
            $referencia = $m['documento'][0] ?? ($m['referencia'][0] ?? null);

            if ($fecha === null || $monto === null || $monto <= 0 || $descripcion === '') {
                continue;
            }

            $filas[] = [
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'monto' => $monto,
                'referencia' => $referencia !== null ? trim((string) $referencia) : null,
            ];
        }

        return $filas;
    }

    /** Todas las líneas de texto no vacías, en orden, de todas las páginas del PDF. */
    private function extraerLineasPdf(string $ruta): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($ruta);

        $lineas = [];
        foreach ($pdf->getPages() as $pagina) {
            foreach (preg_split('/\r\n|\r|\n/', $pagina->getText()) as $linea) {
                $linea = rtrim($linea);
                if ($linea !== '') {
                    $lineas[] = $linea;
                }
            }
        }

        return $lineas;
    }

    // ── Normalización común ──────────────────────────────────────────────────

    private function normalizarFecha(string $valor, string $formato): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        foreach ([$formato, 'd/m/Y', 'd-m-Y', 'Y-m-d'] as $intento) {
            $dt = \DateTime::createFromFormat($intento, $valor);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        // Fecha serial de Excel (numérica) cuando la celda no traía formato de fecha reconocido.
        if (is_numeric($valor)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function normalizarMonto($valor, string $separadorDecimal): ?float
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $texto = preg_replace('/[^0-9\-,.]/', '', $texto) ?? '';
        if ($texto === '') {
            return null;
        }

        if ($separadorDecimal === ',') {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }

        return is_numeric($texto) ? (float) $texto : null;
    }
}
