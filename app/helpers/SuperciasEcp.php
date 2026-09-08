<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Estado de Cambios en el Patrimonio (ECP) de Supercías: estructura oficial (filas × columnas)
 * y normalización del mapeo que lleva cada cuenta del plan:
 *  - plan_cuentas.supercias_ecp_subcodigo = COLUMNA (componente del patrimonio: 301 … 30702).
 *  - plan_cuentas.supercias_ecp_codigo    = FILA DE CAMBIOS opcional (990102, 990103, 990201-990209).
 * El cálculo del ECP vive en EstadosFinancierosService::calcularEcp().
 */
final class SuperciasEcp
{
    /** Filas del ECP en el orden del formulario oficial. */
    public const FILAS = [
        '99'     => 'Saldo al final del período',
        '9901'   => 'Saldo reexpresado del período inmediato anterior',
        '990101' => 'Saldo del período inmediato anterior',
        '990102' => 'Cambios en políticas contables',
        '990103' => 'Corrección de errores',
        '9902'   => 'Cambios del año en el patrimonio',
        '990201' => 'Aumento (disminución) de capital social',
        '990202' => 'Aportes para futuras capitalizaciones',
        '990203' => 'Prima por emisión primaria de acciones',
        '990204' => 'Dividendos',
        '990205' => 'Transferencia de resultados a otras cuentas patrimoniales',
        '990206' => 'Realización de la reserva por valuación de activos financieros',
        '990207' => 'Realización de la reserva por valuación de propiedades, planta y equipo',
        '990208' => 'Realización de la reserva por valuación de activos intangibles',
        '990209' => 'Otros cambios (detallar)',
        '990210' => 'Resultado integral total del año (ganancia o pérdida)',
    ];

    /** Filas de "cambios del año" que una cuenta puede fijar en su mapeo. */
    public const FILAS_CAMBIO = ['990102', '990103', '990201', '990202', '990203', '990204', '990205', '990206', '990207', '990208', '990209'];

    /** Columnas (componentes del patrimonio). Coinciden con los casilleros ESF de menor nivel del patrimonio. */
    public const COLUMNAS = [
        '301', '302', '303', '30401', '30402',
        '30501', '30502', '30503', '30504',
        '30601', '30602', '30603', '30604', '30605', '30606', '30607',
        '30701', '30702',
    ];

    /**
     * Columna del ECP que corresponde a un casillero ESF de patrimonio: la columna más larga que
     * sea prefijo del ESF (30101 → 301, 30401 → 30401, 30601 → 30601). '' si no aplica.
     */
    public static function columnaDesdeEsf(string $esf): string
    {
        $esf = trim($esf);
        if ($esf === '') return '';
        $mejor = '';
        foreach (self::COLUMNAS as $col) {
            if (str_starts_with($esf, $col) && strlen($col) > strlen($mejor)) {
                $mejor = $col;
            }
        }
        return $mejor;
    }

    /**
     * Normaliza el mapeo ECP de una cuenta antes de guardarla (crear, editar, importar, plan modelo):
     *  - Cuenta de patrimonio (código que empieza por 3) con ESF y sin columna: la columna se deduce del ESF.
     *  - Cuenta que NO es de patrimonio: no lleva columna ni fila ECP (se limpian).
     *  - Fila con un valor heredado que no es de cambios ('99', '9901', '9902', '990101', '990210'): se limpia,
     *    equivale a "fila por defecto de la columna". Cualquier otro valor lo valida PlanCuentaRules.
     *  - Fila sin columna: se limpia.
     * Devuelve el mismo array con supercias_ecp_codigo / supercias_ecp_subcodigo normalizados.
     */
    public static function normalizarMapeo(array $data): array
    {
        $codigoCuenta = (string) ($data['codigo'] ?? '');
        $esPatrimonio = str_starts_with($codigoCuenta, '3');
        $esf  = trim((string) ($data['supercias_esf'] ?? ''));
        $col  = trim((string) ($data['supercias_ecp_subcodigo'] ?? ''));
        $fila = trim((string) ($data['supercias_ecp_codigo'] ?? ''));

        if (!$esPatrimonio) {
            $col = '';
            $fila = '';
        } elseif ($col === '' && $esf !== '') {
            $col = self::columnaDesdeEsf($esf);
        }

        if (in_array($fila, ['99', '9901', '9902', '990101', '990210'], true) || $col === '') {
            $fila = '';
        }

        $data['supercias_ecp_subcodigo'] = $col;
        $data['supercias_ecp_codigo'] = $fila;
        return $data;
    }
}
