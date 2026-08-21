<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Propone qué cobro del sistema corresponde a cada línea del estado de cuenta.
 *
 * Es solo una SUGERENCIA: nada se guarda hasta que el usuario confirma. Cada
 * sugerencia viaja con su score y el criterio con el que se encontró, para que
 * en pantalla se vea por qué se propuso.
 *
 * Orden de los criterios, del más confiable al menos:
 *   1. autorizacion  — el código de autorización de la tarjeta es único
 *   2. referencia    — referencia del cobro, número de ingreso o del documento
 *   3. monto_fecha   — mismo valor y fecha cercana, si no hay ambigüedad
 *   4. monto         — mismo valor y candidato único en todo el archivo
 *
 * Un cobro se asigna a lo sumo a una línea: en cuanto se propone, sale del
 * conjunto de candidatos.
 */
class ConciliacionTarjetasMatchService
{
    /** Días de tolerancia entre la fecha del cobro y la del estado de cuenta. */
    private const DIAS_VENTANA = 5;

    private const SCORE = [
        'autorizacion' => 100.0,
        'referencia'   => 85.0,
        'monto_fecha'  => 70.0,
        'monto'        => 55.0,
    ];

    /**
     * @param array $lineas Filas de conciliacion_tarjetas_lineas (sin cruzar)
     * @param array $cobros Filas de getCobrosPendientes()
     * @return array<int, array{id_linea:int, cobros:array<int,array{id_ingreso_pago:int,id_ingreso:int,monto:float}>, score:float, criterio:string}>
     */
    public function sugerir(array $lineas, array $cobros): array
    {
        $disponibles = [];
        foreach ($cobros as $c) {
            $disponibles[(int) $c['id_ingreso_pago']] = $c;
        }

        $sugerencias = [];

        // Las líneas de transacción se resuelven 1 a 1; los depósitos consolidados
        // agrupan varios cobros y se dejan para el final, cuando ya se apartaron
        // los que tienen un match individual claro.
        $transacciones = array_filter($lineas, static fn($l) => ($l['tipo_linea'] ?? 'transaccion') !== 'deposito');
        $depositos     = array_filter($lineas, static fn($l) => ($l['tipo_linea'] ?? 'transaccion') === 'deposito');

        foreach (['autorizacion', 'referencia', 'monto_fecha', 'monto'] as $criterio) {
            foreach ($transacciones as $linea) {
                $idLinea = (int) $linea['id'];
                if (isset($sugerencias[$idLinea])) {
                    continue;
                }
                $match = $this->buscarPorCriterio($criterio, $linea, $disponibles);
                if ($match === null) {
                    continue;
                }
                unset($disponibles[(int) $match['id_ingreso_pago']]);
                $sugerencias[$idLinea] = [
                    'id_linea'  => $idLinea,
                    'cobros'    => [[
                        'id_ingreso_pago' => (int) $match['id_ingreso_pago'],
                        'id_ingreso'      => (int) $match['id_ingreso'],
                        'monto'           => (float) $match['monto'],
                    ]],
                    'score'     => self::SCORE[$criterio],
                    'criterio'  => $criterio,
                ];
            }
        }

        foreach ($depositos as $linea) {
            $grupo = $this->buscarGrupoParaDeposito($linea, $disponibles);
            if ($grupo === null) {
                continue;
            }
            foreach ($grupo as $c) {
                unset($disponibles[(int) $c['id_ingreso_pago']]);
            }
            $sugerencias[(int) $linea['id']] = [
                'id_linea' => (int) $linea['id'],
                'cobros'   => array_map(static fn($c) => [
                    'id_ingreso_pago' => (int) $c['id_ingreso_pago'],
                    'id_ingreso'      => (int) $c['id_ingreso'],
                    'monto'           => (float) $c['monto'],
                ], $grupo),
                'score'    => self::SCORE['monto_fecha'],
                'criterio' => 'deposito_suma',
            ];
        }

        return array_values($sugerencias);
    }

    // ── Criterios ────────────────────────────────────────────────────────────

    private function buscarPorCriterio(string $criterio, array $linea, array $disponibles): ?array
    {
        return match ($criterio) {
            'autorizacion' => $this->porAutorizacion($linea, $disponibles),
            'referencia'   => $this->porReferencia($linea, $disponibles),
            'monto_fecha'  => $this->porMontoYFecha($linea, $disponibles),
            'monto'        => $this->porMontoUnico($linea, $disponibles),
            default        => null,
        };
    }

    private function porAutorizacion(array $linea, array $disponibles): ?array
    {
        $aut = $this->normalizarCodigo($linea['autorizacion'] ?? '');
        if ($aut === '') {
            return null;
        }

        foreach ($disponibles as $c) {
            if ($this->normalizarCodigo($c['autorizacion'] ?? '') === $aut) {
                return $c;
            }
        }
        return null;
    }

    private function porReferencia(array $linea, array $disponibles): ?array
    {
        $candidatos = array_filter([
            $this->normalizarCodigo($linea['referencia'] ?? ''),
            $this->normalizarCodigo($linea['autorizacion'] ?? ''),
        ], static fn($v) => $v !== '');

        if (empty($candidatos)) {
            return null;
        }

        foreach ($disponibles as $c) {
            $delCobro = array_filter([
                $this->normalizarCodigo($c['referencia'] ?? ''),
                $this->normalizarCodigo($c['numero_ingreso'] ?? ''),
                $this->normalizarCodigo($c['documentos'] ?? ''),
            ], static fn($v) => $v !== '');

            foreach ($candidatos as $ref) {
                if (in_array($ref, $delCobro, true)) {
                    return $c;
                }
            }
        }
        return null;
    }

    /** Mismo valor y fecha cercana. Solo sugiere si hay UN candidato: si hay dos, es ambiguo. */
    private function porMontoYFecha(array $linea, array $disponibles): ?array
    {
        $bruto = round((float) $linea['monto_bruto'], 2);
        $fecha = (string) ($linea['fecha_movimiento'] ?? '');
        if ($bruto <= 0 || $fecha === '') {
            return null;
        }

        $coincidencias = [];
        foreach ($disponibles as $c) {
            if (round((float) $c['monto'], 2) !== $bruto) {
                continue;
            }
            if ($this->diasEntre($fecha, (string) $c['fecha_emision']) <= self::DIAS_VENTANA) {
                $coincidencias[] = $c;
            }
        }

        return count($coincidencias) === 1 ? $coincidencias[0] : null;
    }

    /** Mismo valor, sin mirar fecha. Solo si es el único con ese valor. */
    private function porMontoUnico(array $linea, array $disponibles): ?array
    {
        $bruto = round((float) $linea['monto_bruto'], 2);
        if ($bruto <= 0) {
            return null;
        }

        $coincidencias = [];
        foreach ($disponibles as $c) {
            if (round((float) $c['monto'], 2) === $bruto) {
                $coincidencias[] = $c;
            }
        }

        return count($coincidencias) === 1 ? $coincidencias[0] : null;
    }

    /**
     * Depósito consolidado: busca un conjunto de cobros que sume exactamente el
     * bruto de la línea. Estrategia voraz por fecha (los más antiguos primero,
     * que es como liquidan las procesadoras). Si no cuadra exacto no sugiere
     * nada: es preferible que el usuario lo arme a mano antes que proponer un
     * grupo equivocado.
     */
    private function buscarGrupoParaDeposito(array $linea, array $disponibles): ?array
    {
        $objetivo = round((float) $linea['monto_bruto'], 2);
        if ($objetivo <= 0 || empty($disponibles)) {
            return null;
        }

        $candidatos = array_values($disponibles);
        usort($candidatos, static fn($a, $b) => strcmp((string) $a['fecha_emision'], (string) $b['fecha_emision']));

        $grupo = [];
        $suma  = 0.0;
        foreach ($candidatos as $c) {
            $monto = round((float) $c['monto'], 2);
            if ($monto <= 0 || round($suma + $monto, 2) > $objetivo) {
                continue;
            }
            $grupo[] = $c;
            $suma = round($suma + $monto, 2);
            if ($suma === $objetivo) {
                return $grupo;
            }
        }

        return null;
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    /** Compara códigos sin espacios, guiones ni ceros a la izquierda. */
    private function normalizarCodigo($valor): string
    {
        $texto = strtoupper(trim((string) $valor));
        if ($texto === '') {
            return '';
        }
        $texto = preg_replace('/[\s\-\.]/', '', $texto) ?? $texto;
        // Solo se quitan los ceros iniciales si lo que queda sigue siendo un número.
        $sinCeros = ltrim($texto, '0');
        return ($sinCeros !== '' && ctype_digit($texto)) ? $sinCeros : $texto;
    }

    private function diasEntre(string $a, string $b): int
    {
        $ta = strtotime($a);
        $tb = strtotime($b);
        if ($ta === false || $tb === false) {
            return PHP_INT_MAX;
        }
        return (int) round(abs($ta - $tb) / 86400);
    }
}
