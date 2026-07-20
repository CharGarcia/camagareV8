<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\DecimoTerceroRepository;
use App\Rules\modulos\DecimoTerceroRules;
use Exception;

/**
 * Genera el archivo plano (CSV) de Décimo Tercero para el Sistema de Salarios
 * en Línea del Ministerio del Trabajo. Formato verificado byte a byte contra
 * un archivo de ejemplo real (ARCHIVOSMT/tercero_archivo.csv): separado por
 * ';', codificación Windows-1252, encabezado literal fijo, 13 columnas (la
 * última sin texto en el encabezado, usada como "Mensualiza").
 */
class DecimoTerceroExportService
{
    private const SEPARADOR = ';';

    /** Encabezado literal del archivo del Ministerio — no alterar (última columna sin texto = Mensualiza). */
    private const ENCABEZADO = [
        'Cédula (Ejm.:0502366503)',
        'Nombres',
        'Apellidos',
        'Genero (Masculino=M ó Femenino=F)',
        'Ocupación',
        'Total_ganado (Ejm.:1000.56)',
        'Días laborados (360 días equivalen a un año)',
        'Tipo de Deposito(Pago Directo=P,Acreditación en Cuenta=A,Retencion Pago Directo=RP,Retencion Acreditación en Cuenta=RA)',
        'Solo si el trabajador posee JORNADA PARCIAL PERMANENTE ponga una X',
        'DETERMINE EN HORAS LA JORNADA PARCIAL PERMANENTE SEMANAL ESTIPULADO EN EL CONTRATO',
        'Solo si su trabajador posee algun tipo de discapacidad ponga una X',
        'Ingrese el valor retenido',
        '',
    ];

    private DecimoTerceroRepository $repo;
    private DecimoTerceroRules $rules;

    public function __construct(DecimoTerceroRepository $repo, DecimoTerceroRules $rules)
    {
        $this->repo = $repo;
        $this->rules = $rules;
    }

    /** Nombre de archivo sugerido para la descarga. */
    public function nombreArchivo(array $cabecera): string
    {
        return 'DecimoTercero_' . $cabecera['anio'] . '.csv';
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
                number_format((float) $d['total_ganado'], 2, '.', ''),
                (string) (int) $d['dias_laborados'],
                $d['tipo_pago'],
                $this->truthy($d['jornada_parcial']) ? 'X' : '',
                $d['horas_jornada_parcial'] !== null && $d['horas_jornada_parcial'] !== '' ? (string) $d['horas_jornada_parcial'] : '',
                $this->truthy($d['discapacidad']) ? 'X' : '',
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
