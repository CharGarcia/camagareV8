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

    /**
     * Analiza un PDF de muestra y propone un patrón (regex) de línea de datos, sin que el
     * usuario tenga que escribirlo a mano. Es "mejor esfuerzo": busca qué formato de fecha
     * aparece de forma consistente, clasifica los tokens que vienen después de la fecha en
     * cada línea (número → posible documento, una letra → posible tipo, formato de dinero →
     * monto/saldo, el resto → texto libre a saltar), encuentra la estructura que más se repite
     * y arma el regex con eso. Al final valida el patrón candidato contra el archivo real
     * (mismo algoritmo de producción) para reportar cuántas líneas reconoció.
     *
     * @return array{regex_linea: ?string, formato_fecha: ?string, separador_decimal: ?string, confianza: int, total_lineas_leidas: int, mensaje: string}
     */
    public function sugerirRegexPdf(string $rutaArchivo): array
    {
        $lineas = $this->extraerLineasPdf($rutaArchivo);
        if (empty($lineas)) {
            return $this->sugerenciaVacia(0, 'El PDF no tiene texto extraíble (¿es un escaneo/imagen? en ese caso no se puede detectar nada automáticamente).');
        }

        $patronesFecha = [
            'd/m/Y' => '/\d{2}\/\d{2}\/\d{4}/',
            'Y-m-d' => '/\d{4}-\d{2}-\d{2}/',
            'd-m-Y' => '/\d{2}-\d{2}-\d{4}/',
        ];

        $mejorFormato = null;
        $mejorConteo = 0;
        $mejorRegexFecha = null;
        foreach ($patronesFecha as $formato => $regexFecha) {
            $conteo = 0;
            foreach ($lineas as $linea) {
                if (preg_match($regexFecha, $linea) === 1) {
                    $conteo++;
                }
            }
            if ($conteo > $mejorConteo) {
                $mejorConteo = $conteo;
                $mejorFormato = $formato;
                $mejorRegexFecha = $regexFecha;
            }
        }

        if ($mejorConteo < 3 || $mejorRegexFecha === null) {
            return $this->sugerenciaVacia(count($lineas), 'No se detectó un patrón de fecha consistente en el PDF; arma el patrón manualmente.');
        }

        // Clasifica, en cada línea con fecha, los tokens que vienen DESPUÉS de la fecha.
        $lineasClasificadas = [];
        foreach ($lineas as $linea) {
            if (preg_match($mejorRegexFecha, $linea, $m, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $finFecha = $m[0][1] + strlen($m[0][0]);
            $cola = trim(mb_substr($linea, $finFecha));
            if ($cola === '') {
                continue;
            }
            [$firma, $clases] = $this->clasificarTokens(preg_split('/\s+/', $cola));
            $lineasClasificadas[] = ['clases' => $clases, 'firma' => $firma];
        }

        if (empty($lineasClasificadas)) {
            return $this->sugerenciaVacia(count($lineas), 'No se pudo analizar la estructura de las líneas con fecha.');
        }

        // Estructura (firma) que más se repite entre las líneas de datos.
        $conteoFirmas = array_count_values(array_column($lineasClasificadas, 'firma'));
        arsort($conteoFirmas);
        $firmaGanadora = array_key_first($conteoFirmas);
        $soporteFirma = $conteoFirmas[$firmaGanadora];

        $ejemplo = null;
        foreach ($lineasClasificadas as $lc) {
            if ($lc['firma'] === $firmaGanadora) {
                $ejemplo = $lc;
                break;
            }
        }

        // Separador decimal dominante, contando los tokens de dinero de TODAS las líneas clasificadas.
        $conteoDot = 0;
        $conteoComma = 0;
        foreach ($lineasClasificadas as $lc) {
            foreach ($lc['clases'] as $clase) {
                if ($clase === 'MONEY_DOT') {
                    $conteoDot++;
                } elseif ($clase === 'MONEY_COMMA') {
                    $conteoComma++;
                }
            }
        }
        $separadorDecimal = $conteoComma > $conteoDot ? ',' : '.';
        $patronMoney = $separadorDecimal === ',' ? '[\d.]+,\d{2}' : '[\d,]+\.\d{2}';

        // Arma el regex a partir de la estructura ganadora.
        $piezas = ['(?<fecha>' . trim($mejorRegexFecha, '/') . ')'];
        $usoDocumento = false;
        $usoTipo = false;
        $usoMonto = false;
        $enTexto = false;

        foreach ($ejemplo['clases'] as $clase) {
            if ($clase === 'TEXTO') {
                if (!$enTexto) {
                    $piezas[] = '[A-Z. ]+?';
                    $enTexto = true;
                }
                continue;
            }
            $enTexto = false;

            if ($clase === 'NUM') {
                $piezas[] = $usoDocumento ? '\d+' : '(?<documento>\d+)';
                $usoDocumento = true;
            } elseif ($clase === 'LETRA') {
                $piezas[] = $usoTipo ? '[A-Z]' : '(?<tipo>[A-Z])';
                $usoTipo = true;
            } elseif ($clase === 'MONEY_DOT' || $clase === 'MONEY_COMMA') {
                $piezas[] = $usoMonto ? $patronMoney : '(?<monto>' . $patronMoney . ')';
                $usoMonto = true;
            }
        }

        if (!$usoMonto) {
            return $this->sugerenciaVacia(count($lineas), 'Se detectaron fechas, pero ningún valor con formato de monto junto a ellas; arma el patrón manualmente.');
        }

        $regexSugerido = '/' . implode('\s+', $piezas) . '\s*$/';

        try {
            $resultado = $this->parsearPdf($rutaArchivo, ['regex_linea' => $regexSugerido], $mejorFormato, $separadorDecimal);
        } catch (\Throwable $e) {
            return $this->sugerenciaVacia(count($lineas), 'El patrón candidato no resultó válido: ' . $e->getMessage());
        }

        return [
            'regex_linea' => $regexSugerido,
            'formato_fecha' => $mejorFormato,
            'separador_decimal' => $separadorDecimal,
            'confianza' => count($resultado),
            'total_lineas_leidas' => count($lineas),
            'mensaje' => sprintf(
                '%d línea(s) con fecha detectadas, %d con la misma estructura de datos. El patrón propuesto reconoció %d fila(s) — revísalo con "Probar" y ajusta si falta algo (por ejemplo, el campo "Valor es crédito" no se puede adivinar: revisa qué letra corresponde a los depósitos y complétalo tú).',
                $mejorConteo,
                $soporteFirma,
                count($resultado)
            ),
        ];
    }

    /** @return array{0: string, 1: array<int, string>} [firma colapsada para comparar estructuras, clases por token en orden] */
    private function clasificarTokens(array $tokens): array
    {
        $clases = [];
        foreach ($tokens as $token) {
            if (preg_match('/^\d{4,}$/', $token) === 1) {
                $clases[] = 'NUM';
            } elseif (preg_match('/^[A-Z]$/', $token) === 1) {
                $clases[] = 'LETRA';
            } elseif (preg_match('/^[\d,]+\.\d{2}$/', $token) === 1) {
                $clases[] = 'MONEY_DOT';
            } elseif (preg_match('/^[\d.]+,\d{2}$/', $token) === 1) {
                $clases[] = 'MONEY_COMMA';
            } else {
                $clases[] = 'TEXTO';
            }
        }

        // Para comparar estructuras entre líneas, varias palabras de texto seguidas
        // (p. ej. "AG." "NORTE") cuentan como un solo bloque de texto.
        $firmaColapsada = [];
        foreach ($clases as $clase) {
            if ($clase === 'TEXTO' && !empty($firmaColapsada) && end($firmaColapsada) === 'TEXTO') {
                continue;
            }
            $firmaColapsada[] = $clase;
        }

        return [implode(',', $firmaColapsada), $clases];
    }

    private function sugerenciaVacia(int $totalLineasLeidas, string $mensaje): array
    {
        return [
            'regex_linea' => null,
            'formato_fecha' => null,
            'separador_decimal' => null,
            'confianza' => 0,
            'total_lineas_leidas' => $totalLineasLeidas,
            'mensaje' => $mensaje,
        ];
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
