<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\UtilidadesRepository;
use App\Rules\modulos\UtilidadesRules;
use Exception;

/**
 * Archivo plano (CSV) con el informe de participación de utilidades para el
 * Sistema de Salarios en Línea del Ministerio del Trabajo, con el mismo formato
 * técnico que el archivo del décimo cuarto que el Ministerio ya acepta:
 * separado por ';', Windows-1252, encabezado literal, sin tildes ni ñ.
 *
 * ATENCIÓN: el orden y los títulos de las columnas siguen la plantilla de carga
 * de utilidades del Ministerio tal como la conocemos; antes de la primera carga
 * real hay que contrastar este encabezado con la plantilla vigente que publica
 * el Sistema de Salarios en Línea (puede cambiar de un año a otro). Si cambia,
 * basta ajustar ENCABEZADO y el orden de las celdas en generar().
 */
class UtilidadesExportService
{
    private const SEPARADOR = ';';

    /** Encabezado literal del archivo del Ministerio. */
    private const ENCABEZADO = [
        'Cédula (Ejm.:0502366503)',
        'Nombres',
        'Apellidos',
        'Genero (Masculino=M ó Femenino=F)',
        'Ocupación(codigo iess)',
        'Cargas Familiares',
        'Días laborados (360 días equivalen a un año)',
        'Participación 10% (por tiempo)',
        'Participación 5% (por cargas)',
        'Tipo de Pago(Pago Directo=P,Acreditación en Cuenta=A,Retencion Pago Directo=RP,Retencion Acreditación en Cuenta=RA)',
        'Solo si su trabajador posee algun tipo de discapacidad ponga una X',
        'valor Retencion',
    ];

    private UtilidadesRepository $repo;
    private UtilidadesRules $rules;

    public function __construct(UtilidadesRepository $repo, UtilidadesRules $rules)
    {
        $this->repo = $repo;
        $this->rules = $rules;
    }

    public function nombreArchivo(array $cabecera): string
    {
        return 'Utilidades_' . $cabecera['anio'] . '.csv';
    }

    /** Contenido del CSV, ya en Windows-1252, listo para descargar. */
    public function generar(int $idCabecera, int $idEmpresa): string
    {
        $cabecera = $this->repo->findById($idCabecera, $idEmpresa);
        if (!$cabecera) throw new Exception('Utilidades no encontradas.');
        $this->rules->validarExportacion($cabecera);

        $detalle = $this->repo->getDetalle($idCabecera, $idEmpresa);

        $filas = [self::ENCABEZADO];
        foreach ($detalle as $d) {
            $filas[] = [
                $d['identificacion'],
                $this->limpiarTexto($d['nombres']),
                $this->limpiarTexto($d['apellidos']),
                $d['sexo'],
                $d['codigo_ocupacion'],
                (string) (int) $d['cargas_familiares'],
                (string) (int) $d['dias_laborados'],
                number_format((float) $d['valor_10'], 2, '.', ''),
                number_format((float) $d['valor_5'], 2, '.', ''),
                $d['tipo_pago'],
                $this->truthy($d['discapacidad']) ? 'X' : '',
                (float) $d['valor_retencion'] > 0 ? number_format((float) $d['valor_retencion'], 2, '.', '') : '',
            ];
        }

        $lineas = array_map(fn(array $f) => implode(self::SEPARADOR, $f), $filas);
        $contenido = implode("\r\n", $lineas) . "\r\n";

        return @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $contenido) ?: $contenido;
    }

    private function truthy($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 't', 'true'], true);
    }

    /** El sistema del Ministerio rechaza tildes, ñ y caracteres especiales: se transliteran. */
    private function limpiarTexto(?string $s): string
    {
        if ($s === null || $s === '') return '';
        static $mapa = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U',
            'ç' => 'c', 'Ç' => 'C',
        ];
        $limpio = strtr($s, $mapa);
        return preg_replace('/[^\x20-\x7E]/', '', $limpio) ?? $limpio;
    }
}
