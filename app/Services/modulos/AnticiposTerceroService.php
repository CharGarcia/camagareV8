<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FormaPagoRepository;

/**
 * Pestaña "Anticipos" de las fichas de cliente y proveedor (solo lectura).
 *
 * No inventa ninguna fórmula: toma los movimientos de anticipos de
 * FormaPagoRepository (las mismas fuentes y filtros con los que se calcula el saldo
 * de una forma de cobro/pago tipo ANTICIPO) y les añade el saldo corriendo y los
 * totales, igual que hace EstadoCuentaTerceroService con el kardex de cartera.
 *
 * Signo: saldo inicial y anticipos recibidos/entregados suman; lo aplicado a un
 * cobro o pago resta. El saldo final es lo que al tercero le queda a favor.
 */
class AnticiposTerceroService
{
    private FormaPagoRepository $formas;

    public function __construct(?FormaPagoRepository $formas = null)
    {
        $this->formas = $formas ?? new FormaPagoRepository();
    }

    /** Anticipos recibidos de un cliente (ingresos con opción ANTICIPO_CLIENTE). */
    public function cliente(int $idEmpresa, int $idCliente): array
    {
        return $this->resumir($this->formas->getMovimientosAnticipoTercero($idEmpresa, $idCliente, false));
    }

    /** Anticipos entregados a un proveedor (egresos con opción ANTICIPO_PROVEEDOR). */
    public function proveedor(int $idEmpresa, int $idProveedor): array
    {
        return $this->resumir($this->formas->getMovimientosAnticipoTercero($idEmpresa, $idProveedor, true));
    }

    /** ¿Hay algo que mostrar? La ficha no pinta la pestaña si no hay ningún movimiento. */
    public function tieneCliente(int $idEmpresa, int $idCliente): bool
    {
        return $this->formas->tieneAnticiposTercero($idEmpresa, $idCliente, false);
    }

    public function tieneProveedor(int $idEmpresa, int $idProveedor): bool
    {
        return $this->formas->tieneAnticiposTercero($idEmpresa, $idProveedor, true);
    }

    /**
     * @return array{movimientos: array, total_generado: float, total_aplicado: float, saldo: float}
     *         total_generado = saldo inicial + anticipos recibidos/entregados.
     */
    private function resumir(array $movimientos): array
    {
        $filas     = [];
        $generado  = 0.0;
        $aplicado  = 0.0;
        $saldo     = 0.0;

        foreach ($movimientos as $m) {
            $monto = round((float) $m['monto'], 2);
            $signo = (int) $m['signo'];
            if ($signo < 0) {
                $aplicado += $monto;
            } else {
                $generado += $monto;
            }
            $saldo += $signo * $monto;

            $filas[] = [
                'fecha'            => $m['fecha'],
                'origen'           => $m['origen'],
                'movimiento'       => $m['movimiento'],
                'forma'            => $m['forma'],
                'numero_documento' => $m['numero_documento'],
                'detalle'          => $m['detalle'],
                'monto'            => $monto,
                'signo'            => $signo,
                'saldo'            => round($saldo, 2),
            ];
        }

        return [
            'movimientos'    => $filas,
            'total_generado' => round($generado, 2),
            'total_aplicado' => round($aplicado, 2),
            'saldo'          => round($saldo, 2),
        ];
    }
}
