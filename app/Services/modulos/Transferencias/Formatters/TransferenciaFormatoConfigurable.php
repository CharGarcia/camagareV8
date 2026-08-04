<?php
declare(strict_types=1);

namespace App\Services\modulos\Transferencias\Formatters;

use App\Services\modulos\Transferencias\TransferenciaFormatterInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Motor genérico que genera el archivo de un lote a partir de la definición
 * de columnas (`transferencia_formatos.campos`) armada en
 * /config/transferencia-formatos, sin necesitar una clase PHP por banco.
 * Soporta xlsx, csv/txt delimitado y txt de ancho fijo — ver
 * TransferenciaFormatoService::ORIGEN_DATO para la whitelist de datos
 * disponibles por columna.
 */
class TransferenciaFormatoConfigurable implements TransferenciaFormatterInterface
{
    private array $formato;

    /** @param array $formato Fila de transferencia_formatos, con `campos` ya decodificado. */
    public function __construct(array $formato)
    {
        $this->formato = $formato;
    }

    public function getExtension(): string
    {
        return match ($this->formato['tipo_archivo']) {
            'xlsx' => 'xlsx',
            'csv' => 'csv',
            default => 'txt', // txt_delimitado, txt_ancho_fijo
        };
    }

    public function generar(array $lote, array $lineas, string $rutaDestino): string
    {
        $campos = $this->formato['campos'] ?? [];
        $filas = [];
        $secuencial = 1;
        foreach ($lineas as $linea) {
            $fila = [];
            foreach ($campos as $campo) {
                $fila[] = $this->valorFinal($campo, $lote, $linea, $secuencial);
            }
            $filas[] = $fila;
            $secuencial++;
        }

        $encabezados = array_map(static fn (array $c) => $c['etiqueta'], $campos);

        return match ($this->formato['tipo_archivo']) {
            'xlsx' => $this->generarXlsx($encabezados, $filas, $rutaDestino),
            'txt_ancho_fijo' => $this->generarAnchoFijo($campos, $filas, $rutaDestino),
            default => $this->generarDelimitado($encabezados, $filas, $rutaDestino), // csv, txt_delimitado
        };
    }

    // ─── Resolución de valores ──────────────────────────────────────────────

    private function valorFinal(array $campo, array $lote, array $linea, int $secuencial): string
    {
        $valor = $this->valorCrudo($campo, $lote, $linea, $secuencial);

        if (!empty($campo['mapeo_valores']) && array_key_exists((string) $valor, $campo['mapeo_valores'])) {
            $valor = $campo['mapeo_valores'][(string) $valor];
        }

        if ($campo['tipo_dato'] === 'numero') {
            $valor = $this->formatearNumero((float) $valor, $campo);
        } elseif ($campo['tipo_dato'] === 'fecha') {
            $valor = $valor ? date('d/m/Y', strtotime((string) $valor)) : '';
        } else {
            $valor = (string) $valor;
            if (!empty($campo['quitar_tildes'])) {
                $valor = $this->quitarTildes($valor);
            }
            if (!empty($campo['solo_alfanumerico'])) {
                $valor = preg_replace('/[^A-Za-z0-9 ]/u', '', $valor) ?? $valor;
                $valor = trim(preg_replace('/\s+/', ' ', $valor) ?? $valor);
            }
            if (!empty($campo['mayusculas'])) {
                $valor = mb_strtoupper($valor);
            }
            if (!empty($campo['max_caracteres'])) {
                $valor = mb_substr($valor, 0, (int) $campo['max_caracteres']);
            }
        }

        if (!empty($campo['longitud_fija'])) {
            $valor = $this->aplicarLongitudFija((string) $valor, $campo);
        }

        return (string) $valor;
    }

    private function valorCrudo(array $campo, array $lote, array $linea, int $secuencial)
    {
        return match ($campo['origen_dato']) {
            'tipo_beneficiario'         => $linea['tipo_beneficiario'] ?? '',
            'identificacion'            => $linea['identificacion'] ?? '',
            'nombre_beneficiario'       => $linea['nombre_beneficiario'] ?? '',
            'codigo_banco'              => $linea['codigo_banco'] ?? '',
            'nombre_banco_beneficiario' => $linea['banco_nombre'] ?? '',
            'tipo_cuenta'               => $linea['tipo_cuenta'] ?? '',
            'numero_cuenta'             => $linea['numero_cuenta'] ?? '',
            'telefono'                  => $linea['telefono'] ?? '',
            'monto'                     => $linea['monto'] ?? 0,
            'concepto'                  => $linea['concepto'] ?? '',
            'numero_egreso'             => $linea['numero_egreso'] ?? '',
            'secuencial'                => $secuencial,
            'numero_lote'               => $lote['numero'] ?? '',
            'fecha_pago'                => $lote['fecha_pago'] ?? '',
            'cuenta_empresa'            => $lote['forma_pago_numero_cuenta'] ?? '',
            'moneda'                    => 'USD',
            'texto_fijo'                => $campo['valor_fijo'] ?? '',
            default                     => '',
        };
    }

    private function formatearNumero(float $valor, array $campo): string
    {
        if (($campo['formato_numero'] ?? null) === 'entero_centavos') {
            return (string) (int) round($valor * 100);
        }
        return number_format($valor, (int) ($campo['decimales'] ?? 2), '.', '');
    }

    private function aplicarLongitudFija(string $valor, array $campo): string
    {
        $longitud = (int) $campo['longitud_fija'];
        $relleno = $campo['relleno_caracter'] !== null && $campo['relleno_caracter'] !== ''
            ? $campo['relleno_caracter']
            : ($campo['tipo_dato'] === 'numero' ? '0' : ' ');
        $lado = $campo['alineacion'] === 'izquierda'
            ? STR_PAD_RIGHT
            : ($campo['alineacion'] === 'derecha' ? STR_PAD_LEFT : ($campo['tipo_dato'] === 'numero' ? STR_PAD_LEFT : STR_PAD_RIGHT));

        $valor = mb_substr($valor, 0, $longitud);
        return str_pad($valor, $longitud, $relleno, $lado);
    }

    private function quitarTildes(string $texto): string
    {
        $reemplazos = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ];
        return strtr($texto, $reemplazos);
    }

    // ─── Escritura de archivo ───────────────────────────────────────────────

    private function generarXlsx(array $encabezados, array $filas, string $rutaDestino): string
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle(mb_substr($this->formato['nombre_hoja'] ?? 'Transferencias', 0, 31));

        $fila = 1;
        if (!empty($this->formato['incluye_encabezado'])) {
            $col = 1;
            foreach ($encabezados as $h) {
                $sheet->setCellValueExplicit([$col, $fila], $h, DataType::TYPE_STRING);
                $sheet->getColumnDimensionByColumn($col)->setWidth(18);
                $col++;
            }
            $fila++;
        }

        foreach ($filas as $datos) {
            $col = 1;
            foreach ($datos as $valor) {
                $sheet->setCellValueExplicit([$col, $fila], $valor, DataType::TYPE_STRING);
                $col++;
            }
            $fila++;
        }

        $ruta = $rutaDestino . '.xlsx';
        (new Xlsx($ss))->save($ruta);
        $ss->disconnectWorksheets();
        return $ruta;
    }

    private function generarDelimitado(array $encabezados, array $filas, string $rutaDestino): string
    {
        $delimitador = $this->formato['delimitador'] ?: ',';
        $extension = $this->formato['tipo_archivo'] === 'csv' ? 'csv' : 'txt';
        $ruta = $rutaDestino . '.' . $extension;

        $lineas = [];
        if (!empty($this->formato['incluye_encabezado'])) {
            $lineas[] = implode($delimitador, $encabezados);
        }
        foreach ($filas as $datos) {
            $lineas[] = implode($delimitador, $datos);
        }

        file_put_contents($ruta, implode("\r\n", $lineas) . "\r\n");
        return $ruta;
    }

    private function generarAnchoFijo(array $campos, array $filas, string $rutaDestino): string
    {
        $ruta = $rutaDestino . '.txt';

        $lineas = [];
        if (!empty($this->formato['incluye_encabezado'])) {
            $encabezado = [];
            foreach ($campos as $campo) {
                $encabezado[] = str_pad(mb_substr($campo['etiqueta'], 0, (int) $campo['longitud_fija']), (int) $campo['longitud_fija']);
            }
            $lineas[] = implode('', $encabezado);
        }
        foreach ($filas as $datos) {
            $lineas[] = implode('', $datos);
        }

        file_put_contents($ruta, implode("\r\n", $lineas) . "\r\n");
        return $ruta;
    }
}
