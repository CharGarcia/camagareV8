<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ReporteCarteraRepository;
use App\Rules\RangoFechasRules;

/**
 * Pestaña "Estado de cuenta" de las fichas de proveedor y cliente (solo lectura).
 *
 * No calcula nada propio: toma el kardex del Reporte de Cartera
 * (ReporteCarteraRepository, mismas reglas de enlace que Cuentas por Pagar/Cobrar) y
 * lo resume para el modal: saldo corriendo, totales del período y una sola fila por
 * egreso o ingreso. El kardex trae una fila por cada documento que paga un egreso o
 * cobra un ingreso; aquí se funden y el reparto por documento se ve al desplegar el
 * pago o cobro desde la pestaña.
 *
 * Si el mismo contribuyente está registrado dos veces —una ficha con la cédula y otra
 * con el RUC, que es esa cédula + '001'— el kardex abarca las dos, igual que en el
 * Reporte de Cartera: el estado de cuenta es del tercero, no de la fila.
 */
class EstadoCuentaTerceroService
{
    private ReporteCarteraRepository $cartera;
    private RangoFechasRules $rules;

    public function __construct(?ReporteCarteraRepository $cartera = null, ?RangoFechasRules $rules = null)
    {
        $this->cartera = $cartera ?? new ReporteCarteraRepository();
        $this->rules   = $rules ?? new RangoFechasRules();
    }

    /** Proveedor: cargos (compras, liquidaciones, importaciones, ND, saldo inicial) y abonos (egresos, retenciones, NC). */
    public function proveedor(int $idEmpresa, int $idProveedor, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $this->rules->validar($fechaDesde, $fechaHasta);
        [$movimientos, $saldoAnterior] = $this->kardex($idEmpresa, 'PROVEEDOR', $idProveedor, $fechaDesde, $fechaHasta);

        return $this->resumir($movimientos, $saldoAnterior, 'PAGO');
    }

    /** Cliente: cargos (facturas, recibos, ND, saldo inicial) y abonos (ingresos, retenciones, NC). */
    public function cliente(int $idEmpresa, int $idCliente, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $this->rules->validar($fechaDesde, $fechaHasta);
        [$movimientos, $saldoAnterior] = $this->kardex($idEmpresa, 'CLIENTE', $idCliente, $fechaDesde, $fechaHasta);

        return $this->resumir($movimientos, $saldoAnterior, 'COBRO');
    }

    /**
     * Movimientos del período y saldo anterior del tercero (todas sus fichas), en UNA
     * consulta: ReporteCarteraRepository::getEstadosCuenta() saca el saldo anterior del
     * mismo recorrido que el detalle. Antes eran dos consultas completas del kardex (una
     * para los movimientos y otra, igual de pesada, solo para sumar lo anterior).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float} [movimientos, saldo anterior]
     */
    private function kardex(int $idEmpresa, string $tipo, int $idTercero, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $fichas  = $this->cartera->expandirEntidades($idEmpresa, $tipo, [$idTercero]);
        $estados = $this->cartera->getEstadosCuenta($idEmpresa, $tipo, $fichas, $fechaDesde ?: null, $fechaHasta ?: null);

        $movimientos   = [];
        $saldoAnterior = 0.0;
        $conDatos      = 0;
        foreach ($estados as $estado) {
            $saldoAnterior += $estado['saldo_anterior'];
            if ($estado['movimientos']) {
                $movimientos = $movimientos ? array_merge($movimientos, $estado['movimientos']) : $estado['movimientos'];
                $conDatos++;
            }
        }
        // Cédula y RUC con movimientos: se intercalan con el orden del kardex
        // (fecha, cargos antes que abonos, id del documento).
        if ($conDatos > 1) {
            usort($movimientos, static fn (array $a, array $b): int
                => [$a['fecha'], -(int) $a['signo'], (int) $a['id_orden']] <=> [$b['fecha'], -(int) $b['signo'], (int) $b['id_orden']]);
        }
        return [$movimientos, $saldoAnterior];
    }

    /** ¿El cliente tiene algún movimiento? La ficha no pinta la pestaña si no hay nada. */
    public function tieneCliente(int $idEmpresa, int $idCliente): bool
    {
        return $this->cartera->tieneMovimientosCliente($idEmpresa, $this->cartera->expandirEntidades($idEmpresa, 'CLIENTE', [$idCliente]));
    }

    /** ¿El proveedor tiene algún movimiento? La ficha no pinta la pestaña si no hay nada. */
    public function tieneProveedor(int $idEmpresa, int $idProveedor): bool
    {
        return $this->cartera->tieneMovimientosProveedor($idEmpresa, $this->cartera->expandirEntidades($idEmpresa, 'PROVEEDOR', [$idProveedor]));
    }

    /**
     * @param string $origenCaja Origen que se funde por documento de caja: 'PAGO' (egreso) o 'COBRO' (ingreso).
     * @return array{movimientos: array, saldo_anterior: float, total_cargos: float,
     *               total_pagos: float, total_otros_abonos: float, saldo_final: float}
     *         total_pagos = egresos (proveedor) o ingresos (cliente); total_otros_abonos = retenciones y NC.
     */
    private function resumir(array $movimientos, float $saldoAnterior, string $origenCaja): array
    {
        $filas    = [];
        $filaCaja = []; // id del egreso/ingreso => posición de su fila en $filas
        foreach ($movimientos as $m) {
            $monto = (float) $m['monto'];
            if ($m['origen'] === $origenCaja) {
                $idCaja = (int) $m['id_orden'];
                if (isset($filaCaja[$idCaja])) {
                    $filas[$filaCaja[$idCaja]]['monto'] += $monto;
                    continue;
                }
                $filaCaja[$idCaja] = count($filas);
            }
            $filas[] = [
                'fecha'            => $m['fecha'],
                'tipo_movimiento'  => $m['tipo_movimiento'],
                'origen'           => $m['origen'],
                'numero_documento' => $m['numero_documento'],
                'detalle'          => $m['detalle'],
                'monto'            => $monto,
                'id_origen'        => (int) $m['id_orden'],
            ];
        }

        $saldo       = $saldoAnterior;
        $totalCargos = 0.0;
        $totalPagos  = 0.0;
        $totalOtros  = 0.0;
        foreach ($filas as &$f) {
            if ($f['tipo_movimiento'] === 'CARGO') {
                $saldo       += $f['monto'];
                $totalCargos += $f['monto'];
            } else {
                $saldo -= $f['monto'];
                if ($f['origen'] === $origenCaja) {
                    $totalPagos += $f['monto'];
                } else {
                    $totalOtros += $f['monto'];
                }
            }
            $f['monto'] = $this->redondear($f['monto']);
            $f['saldo'] = $this->redondear($saldo);
        }
        unset($f);

        return [
            'movimientos'        => $filas,
            'saldo_anterior'     => $this->redondear($saldoAnterior),
            'total_cargos'       => $this->redondear($totalCargos),
            'total_pagos'        => $this->redondear($totalPagos),
            'total_otros_abonos' => $this->redondear($totalOtros),
            'saldo_final'        => $this->redondear($saldo),
        ];
    }

    /** Redondeo a centavos sin "-0.00" por residuos de coma flotante del saldo corriendo. */
    private function redondear(float $valor): float
    {
        $r = round($valor, 2);
        return abs($r) < 0.005 ? 0.0 : $r;
    }
}
