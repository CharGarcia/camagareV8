<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\DecimoCuartoRepository;
use App\Rules\modulos\DecimoCuartoRules;
use Exception;

/**
 * Genera el archivo plano (CSV) para el Sistema de Salarios en Línea del
 * Ministerio del Trabajo. Formato verificado contra un archivo de ejemplo real
 * del Ministerio (no contra el manual de 2016, que resultó desactualizado):
 * separado por ';', codificación Windows-1252, encabezado literal fijo,
 * 13 columnas, SIN columna de valor monetario (el décimo cuarto es el SBU
 * parejo, prorrateado solo por días laborados).
 */
class DecimoCuartoExportService
{
    private const SEPARADOR = ';';

    /** Encabezado literal del archivo del Ministerio — no alterar. */
    private const ENCABEZADO = [
        'Cédula (Ejm.:0502366503)',
        'Nombres',
        'Apellidos',
        'Genero (Masculino=M ó Femenino=F)',
        'Ocupación(codigo iess)',
        'Días laborados (360 días equivalen a un año)',
        'Tipo de Pago(Pago Directo=P,Acreditación en Cuenta=A,Retencion Pago Directo=RP,Retencion Acreditación en Cuenta=RA)',
        'Solo si el trabajador posee JORNADA PARCIAL PERMANENTE ponga una X',
        'DETERMINE EN HORAS LA JORNADA PARCIAL PERMANENTE SEMANAL ESTIPULADO EN EL CONTRATO',
        'Solo si su trabajador posee algun tipo de discapacidad ponga una X',
        'Fecha de Jubilación',
        'valor Retencion',
        'SOLO SI SU TRABAJADOR MENSUALIZA EL PAGO DE LA DECIMOCUARTA REMUNERACIÓN PONGA UNA X',
    ];

    private DecimoCuartoRepository $repo;
    private DecimoCuartoRules $rules;

    public function __construct(DecimoCuartoRepository $repo, DecimoCuartoRules $rules)
    {
        $this->repo = $repo;
        $this->rules = $rules;
    }

    /** Nombre de archivo sugerido para la descarga. */
    public function nombreArchivo(array $cabecera): string
    {
        $region = $cabecera['region_grupo'] === 'sierra_amazonia' ? 'SierraAmazonia' : 'CostaInsular';
        return 'DecimoCuarto_' . $cabecera['anio'] . '_' . $region . '.csv';
    }

    /** Arma el contenido del CSV, ya codificado en Windows-1252, listo para escribir/descargar. */
    public function generar(int $idCabecera, int $idEmpresa): string
    {
        $cabecera = $this->repo->findById($idCabecera, $idEmpresa);
        if (!$cabecera) throw new Exception('Declaración no encontrada.');
        $this->rules->validarExportacion($cabecera);

        $detalle = $this->repo->getDetalle($idCabecera, $idEmpresa);

        $filas = [self::ENCABEZADO];
        foreach ($detalle as $d) {
            $filas[] = [
                $d['identificacion'],
                $d['nombres'],
                $d['apellidos'],
                $d['sexo'],
                $d['codigo_ocupacion'],
                (string) (int) $d['dias_laborados'],
                $d['tipo_pago'],
                $this->truthy($d['jornada_parcial']) ? 'X' : '',
                $d['horas_jornada_parcial'] !== null && $d['horas_jornada_parcial'] !== '' ? (string) $d['horas_jornada_parcial'] : '',
                $this->truthy($d['discapacidad']) ? 'X' : '',
                !empty($d['fecha_jubilacion']) ? date('d/m/Y', strtotime($d['fecha_jubilacion'])) : '',
                (float) $d['valor_retencion'] > 0 ? number_format((float) $d['valor_retencion'], 2, '.', '') : '',
                $this->truthy($d['mensualiza']) ? 'X' : '',
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
}
