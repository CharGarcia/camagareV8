<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\ColumnasReporte;
use Exception;

/**
 * Reglas de la personalización de reportes por empresa (botón «Personalizar»).
 */
class ReportePersonalizacionRules
{
    /**
     * Solo el administrador (nivel 2) o el superadministrador (nivel 3) personalizan un
     * reporte. Se valida aquí además de en el controlador, por si el Service se invoca
     * desde otro flujo.
     */
    public function validarActor(int $nivelActor): void
    {
        if ($nivelActor < ColumnasReporte::NIVEL_MINIMO) {
            throw new Exception('Solo un administrador puede personalizar el reporte.');
        }
    }

    /** El reporte debe estar en el catálogo. */
    public function validarModulo(string $modulo): void
    {
        if (ColumnasReporte::definicion($modulo) === null) {
            throw new Exception('Este reporte no se puede personalizar.');
        }
    }

    /**
     * Valida y normaliza la configuración de una vista. Devuelve
     * ['ocultas' => string[], 'ocultas_excel' => string[], 'ocultas_pdf' => string[],
     * 'orientacion' => 'auto'|'P'|'L'] (ocultas = pantalla; ocultas_excel solo cuenta si la
     * vista elige el Excel aparte, si no queda vacía),
     * con las claves en el orden del catálogo (así dos configuraciones iguales se
     * guardan idénticas).
     */
    public function normalizarVista(string $modulo, string $vista, array $config): array
    {
        $columnas = ColumnasReporte::columnas($modulo, $vista);
        if (!$columnas) {
            throw new Exception('La vista "' . $vista . '" no existe en este reporte.');
        }
        $titulo = (string) (ColumnasReporte::vistas($modulo)[$vista]['titulo'] ?? $vista);

        $orientacion = (string) ($config['orientacion'] ?? 'auto');
        if (!in_array($orientacion, ColumnasReporte::ORIENTACIONES, true)) {
            throw new Exception('Orientación del PDF no válida en "' . $titulo . '".');
        }

        $out = ['ocultas' => [], 'ocultas_excel' => [], 'ocultas_pdf' => [], 'orientacion' => $orientacion];
        foreach (array_keys(ColumnasReporte::MEDIOS) as $lista) {
            if ($lista === 'ocultas_excel' && !ColumnasReporte::excelPropio($modulo, $vista)) {
                continue;
            }
            $pedidas = array_map('strval', is_array($config[$lista] ?? null) ? $config[$lista] : []);
            // Lo que no existe en este medio queda siempre oculto ahí, venga o no en la lista.
            $pedidas = array_merge($pedidas, ColumnasReporte::noDisponibles($modulo, $vista, ColumnasReporte::MEDIO_DE[$lista]));
            foreach ($pedidas as $clave) {
                if (!isset($columnas[$clave])) {
                    throw new Exception('La columna "' . $clave . '" no existe en "' . $titulo . '".');
                }
                if ($columnas[$clave]['fija']) {
                    throw new Exception('La columna "' . $columnas[$clave]['titulo'] . '" no se puede ocultar en "' . $titulo . '".');
                }
            }
            $out[$lista] = array_values(array_filter(
                array_keys($columnas),
                static fn (string $clave): bool => in_array($clave, $pedidas, true)
            ));
        }
        return $out;
    }
}
