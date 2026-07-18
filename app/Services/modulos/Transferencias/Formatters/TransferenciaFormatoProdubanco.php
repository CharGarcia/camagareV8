<?php
declare(strict_types=1);

namespace App\Services\modulos\Transferencias\Formatters;

use App\Services\modulos\Transferencias\TransferenciaFormatterInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Formato de Produbanco (hoja "ROL DE PAGOS", plantilla oficial del banco:
 * fila 1 = número de columna, fila 2 = nombre de columna, fila 3 = obligatorio/
 * opcional, fila 4+ = datos). Sirve tanto para nómina como para proveedores
 * (confirmado con el usuario — el layout es el mismo para ambos beneficiarios).
 *
 * Reglas no evidentes tomadas de los comentarios de la plantilla del banco:
 * - VALOR: entero sin punto ni ceros de más; los 2 últimos dígitos son los
 *   decimales (ej. $180.00 → 18000).
 * - CODIGO DE BANCO: 4 dígitos con ceros a la izquierda — coincide con
 *   bancos_ecuador.codigo_banco, que ya viene formateado así.
 * - TIPO DE CUENTA: AHO (ahorros) / CTE (corriente).
 * - TIPO DE DOCUMENTO: C (cédula) para empleados, R (RUC) para proveedores.
 * - NOMBRES: máximo 40 caracteres, sin Ñ, tildes ni signos de puntuación.
 */
class TransferenciaFormatoProdubanco implements TransferenciaFormatterInterface
{
    private const COLUMNAS = [
        1  => 'TIPO',
        2  => 'NUMERO DE CUENTA DE EMPRESA',
        3  => 'NUMERO SECUENCIAL',
        4  => 'NUMERO DE COMPROBANTE DE PAGO',
        5  => 'CODIGO DE EMPLEADO',
        6  => 'MONEDA',
        7  => 'VALOR',
        8  => 'FORMA DE PAGO',
        9  => 'CODIGO DE BANCO',
        10 => 'TIPO DE CUENTA',
        11 => 'NUMERO DE CUENTA',
        12 => 'TIPO DE DOCUMENTO DE EMPLEADO',
        13 => 'NUMERO DE CEDULA DE EMPLEADO',
        14 => 'NOMBRES DE EMPLEADO',
        15 => 'DIRECCION EMPLEADO',
        16 => 'CIUDAD EMPLEADO',
        17 => 'TELEFONO EMPLEADO',
        18 => 'LOCALIDAD DE COBRO',
        19 => 'REFERENCIA',
        20 => 'REFERENCIA ADICIONAL',
    ];

    private const OBLIGATORIAS = [1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 19, 20];

    public function getExtension(): string
    {
        return 'xlsx';
    }

    public function generar(array $lote, array $lineas, string $rutaDestino): string
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('ROL DE PAGOS');

        foreach (self::COLUMNAS as $col => $nombre) {
            $sheet->setCellValue([$col, 1], $col);
            $sheet->setCellValueExplicit([$col, 2], $nombre, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$col, 3], in_array($col, self::OBLIGATORIAS, true) ? 'MANDATORIO' : 'OPCIONAL', DataType::TYPE_STRING);
            $sheet->getColumnDimensionByColumn($col)->setWidth(18);
        }

        $cuentaEmpresa = str_pad((string) ($lote['forma_pago_numero_cuenta'] ?? ''), 11, '0', STR_PAD_LEFT);

        $row = 4;
        $secuencial = 1;
        foreach ($lineas as $l) {
            $esProveedor = ($l['tipo_beneficiario'] ?? '') === 'PROVEEDOR';

            $datos = [
                1  => 'PA',
                2  => $cuentaEmpresa,
                3  => $secuencial,
                4  => $l['numero_egreso'] ?? '',
                5  => (string) ($l['identificacion'] ?? ''),
                6  => 'USD',
                7  => (int) round(((float) ($l['monto'] ?? 0)) * 100),
                8  => 'CTA',
                9  => (string) ($l['codigo_banco'] ?? '0000'),
                10 => $this->tipoCuenta($l['tipo_cuenta'] ?? ''),
                11 => (string) ($l['numero_cuenta'] ?? ''),
                12 => $esProveedor ? 'R' : 'C',
                13 => (string) ($l['identificacion'] ?? ''),
                14 => $this->sanitizarNombre((string) ($l['nombre_beneficiario'] ?? '')),
                15 => '',
                16 => '',
                17 => '',
                18 => '',
                19 => (string) ($l['concepto'] ?? ''),
                20 => '',
            ];

            foreach ($datos as $col => $valor) {
                if (is_int($valor)) {
                    $sheet->setCellValue([$col, $row], $valor);
                } else {
                    $sheet->setCellValueExplicit([$col, $row], (string) $valor, DataType::TYPE_STRING);
                }
            }

            $row++;
            $secuencial++;
        }

        $ruta = $rutaDestino . '.xlsx';
        (new Xlsx($ss))->save($ruta);
        $ss->disconnectWorksheets();
        return $ruta;
    }

    private function tipoCuenta(string $interno): string
    {
        return match (strtolower(trim($interno))) {
            'ahorros'   => 'AHO',
            'corriente' => 'CTE',
            default     => 'AHO',
        };
    }

    /** Mayúsculas, sin tildes/Ñ, sin signos de puntuación, máx. 40 caracteres. */
    private function sanitizarNombre(string $nombre): string
    {
        $nombre = mb_strtoupper(trim($nombre));
        $reemplazos = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ];
        $nombre = strtr($nombre, $reemplazos);
        $nombre = preg_replace('/[^A-Z0-9 ]/u', '', $nombre) ?? $nombre;
        $nombre = preg_replace('/\s+/', ' ', trim($nombre)) ?? $nombre;
        return mb_substr($nombre, 0, 40);
    }
}
