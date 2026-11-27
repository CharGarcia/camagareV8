<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\models\CatalogoNovedades;
use Exception;

/**
 * Validaciones del Préstamo Empresa con tabla de cuotas (modal de Novedades,
 * modo «Préstamo empresa»).
 */
class PrestamoEmpleadoRules
{
    /** Tope de cuotas de un préstamo (10 años), igual que la plantilla Excel. */
    public const MAX_CUOTAS = 120;

    public const DESEMBOLSOS = ['por_desembolsar', 'entregado'];

    public function validar(array $d): void
    {
        if ((int) ($d['id_empleado'] ?? 0) <= 0) {
            throw new Exception('Debe seleccionar el empleado que recibe el préstamo.');
        }
        $fecha = trim((string) ($d['fecha'] ?? ''));
        if ($fecha === '' || strtotime($fecha) === false) {
            throw new Exception('La fecha del préstamo es obligatoria y debe ser válida.');
        }
        $monto = (float) ($d['monto'] ?? 0);
        if ($monto <= 0) {
            throw new Exception('Escriba el monto a prestar (mayor que 0).');
        }
        if ($monto > 9999999999) {
            throw new Exception('El monto del préstamo no es válido.');
        }
        $n = (int) ($d['num_cuotas'] ?? 0);
        if ($n < 1 || $n > self::MAX_CUOTAS) {
            throw new Exception('El número de cuotas debe estar entre 1 y ' . self::MAX_CUOTAS . '.');
        }
        if (round($monto / $n, 2) < 0.01) {
            throw new Exception('El monto es demasiado pequeño para ese número de cuotas.');
        }
        $mes = (int) ($d['mes_inicio'] ?? 0);
        if ($mes < 1 || $mes > 12) {
            throw new Exception('El mes de la primera cuota debe estar entre 1 y 12.');
        }
        $anio = (int) ($d['anio_inicio'] ?? 0);
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El año de la primera cuota no es válido.');
        }
        if (!CatalogoNovedades::esAplicaEnValido((string) ($d['aplica_en'] ?? ''))) {
            throw new Exception('El campo "Afecta a" no es válido.');
        }
        if (!in_array((string) ($d['desembolso'] ?? ''), self::DESEMBOLSOS, true)) {
            throw new Exception('Indique si el préstamo ya fue entregado o si se desembolsará por egreso.');
        }

        // Cuotas marcadas como ya cobradas (antes del sistema).
        $cobradas = (array) ($d['cobradas'] ?? []);
        // Solo puede estar ya cobrada una cuota de un mes igual o anterior al actual: una de un
        // mes futuro todavía no se ha descontado (marcarla sería perdonarla sin que se note).
        $actual = (int) date('Y') * 100 + (int) date('n');
        foreach ($cobradas as $k) {
            if ((int) $k < 1 || (int) $k > $n) {
                throw new Exception('Hay una cuota marcada como cobrada que no existe en la tabla.');
            }
            $idx = ($mes - 1) + ((int) $k - 1);
            $mesCuota = ($idx % 12) + 1;
            $anioCuota = $anio + intdiv($idx, 12);
            if ($anioCuota * 100 + $mesCuota > $actual) {
                throw new Exception('La cuota ' . (int) $k . ' (' . (CatalogoNovedades::MESES[$mesCuota] ?? $mesCuota) . ' ' . $anioCuota
                    . ') es de un mes posterior al actual: todavía no puede estar cobrada. Desmarque «¿Ya cobrada?» en esa cuota.');
            }
        }
        if (count(array_unique(array_map('intval', $cobradas))) >= $n) {
            throw new Exception('Todas las cuotas están marcadas como ya cobradas: no queda nada por descontar en el rol.');
        }
        // Si ya se cobraron cuotas, el dinero se entregó antes: no puede quedar por desembolsar.
        if ($cobradas && $d['desembolso'] !== 'entregado') {
            throw new Exception('Hay cuotas ya cobradas: el préstamo ya fue entregado. Elija «Ya fue entregado» en Desembolso.');
        }
    }
}
