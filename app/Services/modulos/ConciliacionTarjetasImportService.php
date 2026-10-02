<?php

declare(strict_types=1);

namespace App\Services\modulos;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Lee el estado de cuenta que emite la procesadora de tarjetas (Payphone, Nuvei,
 * el banco del datáfono…) y lo devuelve como líneas normalizadas.
 *
 * El formato cambia de un proveedor a otro —y de un banco a otro— así que nada
 * viene fijo: un PERFIL (catálogo global en config/conciliacion-tarjetas-perfiles) dice en
 * qué columna está cada dato. Mismo enfoque que ConciliacionImportService usa
 * para los estados de cuenta bancarios; se mantiene aparte porque los campos que
 * interesan son otros (autorización, comisión, retenciones).
 *
 * No escribe en base de datos: devuelve el array y el Service decide qué hacer.
 */
class ConciliacionTarjetasImportService
{
    /**
     * Campos que puede traer una línea. 'fecha' y 'monto_bruto' son obligatorios.
     * 'lote' y 'fecha_pago' no tienen columna propia en la línea: se suman a la
     * descripción (p. ej. los recaps de Diners traen el lote y el día en que se paga).
     */
    public const CAMPOS = [
        'fecha', 'autorizacion', 'referencia', 'descripcion', 'lote', 'fecha_pago',
        'monto_bruto', 'comision', 'iva_comision', 'retencion_ir', 'retencion_iva',
        'otros_descuentos', 'monto_neto',
    ];

    /** Valor de `hoja` en el perfil para leer todas las hojas del libro. */
    public const TODAS_LAS_HOJAS = '*';

    private const CAMPOS_MONTO = [
        'monto_bruto', 'comision', 'iva_comision', 'retencion_ir',
        'retencion_iva', 'otros_descuentos', 'monto_neto',
    ];

    /**
     * @param array  $perfil Fila de conciliacion_tarjetas_perfiles
     * @return array{lineas: array, total_leidas: int, total_validas: int, descartadas: int}
     */
    public function parsear(array $perfil, string $rutaArchivo): array
    {
        $mapeo = $perfil['mapeo_columnas'];
        if (is_string($mapeo)) {
            $mapeo = json_decode($mapeo, true) ?: [];
        }

        $tipoArchivo = strtoupper((string) ($perfil['tipo_archivo'] ?? 'EXCEL'));
        $formatoFecha = (string) ($perfil['formato_fecha'] ?? 'd/m/Y');
        $separador    = (string) ($perfil['separador_decimal'] ?? '.');
        $tipoLinea    = (string) ($perfil['nivel'] ?? 'transaccion');

        if ($tipoArchivo === 'PDF') {
            $crudas = $this->extraerLineasPdf($rutaArchivo);
            $lineas = $this->parsearPdf($crudas, $mapeo, $formatoFecha, $separador, $tipoLinea);
            return [
                'lineas'       => $lineas,
                'total_leidas' => count($crudas),
                'total_validas' => count($lineas),
                'descartadas'  => 0,
            ];
        }

        $hoja   = trim((string) ($perfil['hoja'] ?? ''));
        $filas  = $this->extraerFilasExcel(
            $rutaArchivo,
            (int) ($perfil['fila_inicio'] ?? 0),
            $hoja,
            $this->esVerdadero($perfil['solo_columnas_visibles'] ?? false)
        );
        $lineas = [];
        foreach ($filas as [$nombreHoja, $fila]) {
            $norm = $this->normalizarFilaExcel($fila, $mapeo, $formatoFecha, $separador, $tipoLinea, $this->etiquetaHoja($hoja, $nombreHoja));
            if ($norm !== null) {
                $lineas[] = $norm;
            }
        }

        return [
            'lineas'        => $lineas,
            'total_leidas'  => count($filas),
            'total_validas' => count($lineas),
            'descartadas'   => count($filas) - count($lineas),
        ];
    }

    /**
     * Para el asistente de perfil: muestra el archivo tal como se lee, para que el
     * usuario vea qué hay en cada columna antes de mapear. Si se pasa un mapeo de
     * prueba, además devuelve cómo quedarían las líneas con ese mapeo.
     *
     * @return array{lineas: array, filas_probadas: ?array, hojas?: array}
     */
    public function previsualizar(
        string $rutaArchivo,
        string $tipoArchivo,
        int $filaInicio = 0,
        int $limite = 60,
        ?array $mapeoPrueba = null,
        string $formatoFecha = 'd/m/Y',
        string $separador = '.',
        string $hoja = '',
        bool $soloVisibles = false
    ): array {
        if (strtoupper($tipoArchivo) === 'PDF') {
            $crudas = $this->extraerLineasPdf($rutaArchivo);
            $probadas = null;
            if (!empty($mapeoPrueba['regex_linea'])) {
                try {
                    $probadas = $this->parsearPdf($crudas, $mapeoPrueba, $formatoFecha, $separador, 'transaccion');
                } catch (\Throwable $e) {
                    $probadas = ['error' => $e->getMessage()];
                }
            }
            return ['lineas' => array_slice($crudas, 0, $limite), 'filas_probadas' => $probadas];
        }

        $hoja  = trim($hoja);
        $filas = array_slice($this->extraerFilasExcel($rutaArchivo, $filaInicio, $hoja, $soloVisibles), 0, $limite);

        $probadas = null;
        if (is_array($mapeoPrueba) && !empty($mapeoPrueba)) {
            $probadas = [];
            foreach ($filas as [$nombreHoja, $fila]) {
                $norm = $this->normalizarFilaExcel($fila, $mapeoPrueba, $formatoFecha, $separador, 'transaccion', $this->etiquetaHoja($hoja, $nombreHoja));
                if ($norm !== null) {
                    $probadas[] = $norm;
                }
            }
        }

        return [
            'lineas'         => array_map(static fn($f) => $f[1], $filas),
            // Hoja de cada fila, para que la vista previa diga de dónde salió.
            'hojas_filas'    => array_map(static fn($f) => $f[0], $filas),
            'hojas'          => IOFactory::load($rutaArchivo)->getSheetNames(),
            'filas_probadas' => $probadas,
        ];
    }

    // ── Excel / CSV ──────────────────────────────────────────────────────────

    /**
     * Devuelve las filas como pares [nombre de la hoja, celdas].
     *
     * $hoja: '' = la hoja activa (la que abre Excel); '*' = todas; otro valor = la
     * hoja con ese nombre. El nombre se compara sin mayúsculas, espacios ni signos y
     * basta con que esté contenido ("MASTER" encuentra "MASTERD CARD" y "MasterCard"),
     * porque el banco no siempre escribe igual el nombre de la hoja cada mes.
     *
     * $soloVisibles: descarta las columnas ocultas (y, con '*', las hojas ocultas).
     * Las columnas que quedan se numeran desde 0 en el orden en que se ven, que es
     * como las cuenta el usuario mirando el archivo en Excel.
     *
     * La fila de inicio se aplica en cada hoja: todas traen su propio encabezado.
     */
    private function extraerFilasExcel(string $ruta, int $filaInicio, string $hoja = '', bool $soloVisibles = false): array
    {
        $libro = IOFactory::load($ruta);

        if ($hoja === '') {
            $hojas = [$libro->getActiveSheet()];
        } elseif ($hoja === self::TODAS_LAS_HOJAS) {
            $hojas = array_filter(
                $libro->getAllSheets(),
                static fn(Worksheet $h) => !$soloVisibles || $h->getSheetState() === Worksheet::SHEETSTATE_VISIBLE
            );
        } else {
            $hojas = [$this->buscarHoja($libro, $hoja)];
        }

        $resultado = [];
        foreach ($hojas as $h) {
            $filas = $h->toArray(null, true, true, false);
            if ($filaInicio > 0) {
                $filas = array_slice($filas, $filaInicio);
            }

            $ocultas = $soloVisibles ? $this->columnasOcultas($h) : [];

            foreach ($filas as $fila) {
                $fila = (array) $fila;
                if ($ocultas) {
                    $fila = array_values(array_diff_key($fila, $ocultas));
                }
                // Descarta filas totalmente vacías (los reportes suelen traerlas al final).
                foreach ($fila as $celda) {
                    if (trim((string) $celda) !== '') {
                        $resultado[] = [$h->getTitle(), $fila];
                        break;
                    }
                }
            }
        }

        return $resultado;
    }

    private function buscarHoja(Spreadsheet $libro, string $nombre): Worksheet
    {
        $clave = static fn(string $t): string => preg_replace(
            '/[^A-Z0-9]/', '', strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t)
        ) ?? '';

        $buscada = $clave($nombre);
        $parcial = null;
        foreach ($libro->getAllSheets() as $h) {
            $actual = $clave($h->getTitle());
            if ($actual === $buscada) {
                return $h;
            }
            if ($parcial === null && $buscada !== '' && str_contains($actual, $buscada)) {
                $parcial = $h;
            }
        }

        if ($parcial === null) {
            throw new \Exception(
                'El archivo no tiene la hoja "' . $nombre . '" que indica el perfil. Hojas del archivo: '
                . implode(', ', $libro->getSheetNames()) . '.'
            );
        }
        return $parcial;
    }

    /** Índices (desde 0) de las columnas ocultas de la hoja, como claves. */
    private function columnasOcultas(Worksheet $hoja): array
    {
        $ocultas = [];
        foreach ($hoja->getColumnDimensions() as $letra => $dim) {
            if (!$dim->getVisible()) {
                $ocultas[Coordinate::columnIndexFromString((string) $letra) - 1] = true;
            }
        }
        return $ocultas;
    }

    /** Con todas las hojas, el nombre de cada una (la marca, en los recaps) va a la descripción. */
    private function etiquetaHoja(string $hojaPerfil, string $nombreHoja): ?string
    {
        return $hojaPerfil === self::TODAS_LAS_HOJAS ? trim($nombreHoja) : null;
    }

    private function esVerdadero($valor): bool
    {
        return $valor === true || $valor === 1 || in_array(strtolower(trim((string) $valor)), ['1', 't', 'true', 'on'], true);
    }

    /**
     * Mapeo esperado: ['campo' => ['col' => <índice de columna>], ...].
     * Devuelve null si la fila no tiene fecha o bruto válidos (encabezados, totales…).
     */
    private function normalizarFilaExcel(array $fila, array $mapeo, string $formatoFecha, string $separador, string $tipoLinea, ?string $hoja = null): ?array
    {
        $valorDe = static function (string $campo) use ($fila, $mapeo) {
            if (!isset($mapeo[$campo]['col'])) {
                return null;
            }
            $col = (int) $mapeo[$campo]['col'];
            return $fila[$col] ?? null;
        };

        $fecha = $this->normalizarFecha((string) ($valorDe('fecha') ?? ''), $formatoFecha);
        $bruto = $this->normalizarMonto($valorDe('monto_bruto'), $separador);

        if ($fecha === null || $bruto === null) {
            return null;
        }
        // Los reportes traen los descuentos en negativo o en positivo según el
        // proveedor; aquí siempre se guardan en positivo.
        $bruto = abs($bruto);
        if ($bruto <= 0) {
            return null;
        }

        $linea = [
            'fecha'        => $fecha,
            'tipo_linea'   => $tipoLinea,
            'autorizacion' => $this->limpiarTexto($valorDe('autorizacion'), 60),
            'referencia'   => $this->limpiarTexto($valorDe('referencia'), 120),
            'descripcion'  => $this->componerDescripcion(
                $valorDe('descripcion'), $valorDe('lote'), $valorDe('fecha_pago'), $formatoFecha, $hoja
            ),
            'monto_bruto'  => round($bruto, 2),
            'linea_cruda'  => $this->resumirFila($hoja !== null ? array_merge([$hoja], $fila) : $fila),
        ];

        foreach (self::CAMPOS_MONTO as $campo) {
            if ($campo === 'monto_bruto') {
                continue;
            }
            $valor = $this->normalizarMonto($valorDe($campo), $separador);
            $linea[$campo] = $valor === null ? 0.0 : round(abs($valor), 2);
        }

        return $this->completarNeto($linea);
    }

    // ── PDF ──────────────────────────────────────────────────────────────────

    private function extraerLineasPdf(string $ruta): array
    {
        $pdf = (new PdfParser())->parseFile($ruta);

        $lineas = [];
        foreach ($pdf->getPages() as $pagina) {
            foreach (preg_split('/\r\n|\r|\n/', $pagina->getText()) as $linea) {
                $linea = rtrim((string) $linea);
                if ($linea !== '') {
                    $lineas[] = $linea;
                }
            }
        }
        return $lineas;
    }

    /**
     * Mapeo esperado: ['regex_linea' => '/.../'] con grupos nombrados que se llamen
     * igual que los campos (?<fecha>…) (?<monto_bruto>…) (?<autorizacion>…)…
     */
    private function parsearPdf(array $crudas, array $mapeo, string $formatoFecha, string $separador, string $tipoLinea): array
    {
        $regex = trim((string) ($mapeo['regex_linea'] ?? ''));
        if ($regex === '') {
            throw new \Exception('El perfil no tiene definido el patrón de línea del PDF.');
        }
        if (@preg_match($regex, '') === false) {
            throw new \Exception('El patrón (regex) del perfil no es válido.');
        }

        $lineas = [];
        foreach ($crudas as $cruda) {
            if (!preg_match($regex, $cruda, $m)) {
                continue;
            }

            $fecha = $this->normalizarFecha((string) ($m['fecha'] ?? ''), $formatoFecha);
            $bruto = $this->normalizarMonto($m['monto_bruto'] ?? null, $separador);
            if ($fecha === null || $bruto === null || abs($bruto) <= 0) {
                continue;
            }

            $linea = [
                'fecha'        => $fecha,
                'tipo_linea'   => $tipoLinea,
                'autorizacion' => $this->limpiarTexto($m['autorizacion'] ?? null, 60),
                'referencia'   => $this->limpiarTexto($m['referencia'] ?? null, 120),
                'descripcion'  => $this->componerDescripcion(
                    $m['descripcion'] ?? null, $m['lote'] ?? null, $m['fecha_pago'] ?? null, $formatoFecha, null
                ),
                'monto_bruto'  => round(abs($bruto), 2),
                'linea_cruda'  => mb_substr($cruda, 0, 500),
            ];

            foreach (self::CAMPOS_MONTO as $campo) {
                if ($campo === 'monto_bruto') {
                    continue;
                }
                $valor = $this->normalizarMonto($m[$campo] ?? null, $separador);
                $linea[$campo] = $valor === null ? 0.0 : round(abs($valor), 2);
            }

            $lineas[] = $this->completarNeto($linea);
        }

        return $lineas;
    }

    // ── Normalización ────────────────────────────────────────────────────────

    /**
     * Si el archivo no trae el neto, se calcula; si lo trae pero no cuadra con
     * bruto − descuentos, se respeta el del archivo (manda el proveedor) y la
     * diferencia aflora al conciliar.
     */
    private function completarNeto(array $linea): array
    {
        $descuentos = round(
            $linea['comision'] + $linea['iva_comision'] + $linea['retencion_ir']
            + $linea['retencion_iva'] + $linea['otros_descuentos'],
            2
        );

        if (($linea['monto_neto'] ?? 0.0) <= 0) {
            $linea['monto_neto'] = round($linea['monto_bruto'] - $descuentos, 2);
        }

        // Caso frecuente: el reporte trae bruto y neto, pero no desglosa la comisión.
        if ($descuentos <= 0 && $linea['monto_neto'] > 0 && $linea['monto_neto'] < $linea['monto_bruto']) {
            $linea['comision'] = round($linea['monto_bruto'] - $linea['monto_neto'], 2);
        }

        return $linea;
    }

    /** Descripción + hoja (la marca) + «Lote …» + «Pago dd-mm-aaaa», lo que venga. */
    private function componerDescripcion($descripcion, $lote, $fechaPago, string $formatoFecha, ?string $hoja): ?string
    {
        $partes = [];
        if (($d = $this->limpiarTexto($descripcion, 500)) !== null) {
            $partes[] = $d;
        }
        if ($hoja !== null && $hoja !== '') {
            $partes[] = $hoja;
        }
        if (($l = $this->limpiarTexto($lote, 60)) !== null) {
            $partes[] = 'Lote ' . $l;
        }
        $pago = $this->normalizarFecha((string) ($fechaPago ?? ''), $formatoFecha);
        if ($pago !== null) {
            $partes[] = 'Pago ' . date('d-m-Y', strtotime($pago));
        }
        return $partes ? mb_substr(implode(' · ', $partes), 0, 500) : null;
    }

    private function limpiarTexto($valor, int $max): ?string
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return null;
        }
        return mb_substr(preg_replace('/\s+/u', ' ', $texto) ?? $texto, 0, $max);
    }

    private function resumirFila(array $fila): string
    {
        $partes = array_map(static fn($c) => trim((string) $c), $fila);
        return mb_substr(implode(' | ', array_filter($partes, static fn($p) => $p !== '')), 0, 500);
    }

    private function normalizarFecha(string $valor, string $formato): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        foreach ([$formato, 'd/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i', 'Y-m-d H:i:s'] as $intento) {
            $dt = \DateTime::createFromFormat($intento, $valor);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        // aaaammdd ("20260825", recaps de Diners) aunque el perfil diga otro formato:
        // el serial de Excel de abajo lo tomaría como días y daría un año absurdo.
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $valor, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // Fecha serial de Excel.
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
