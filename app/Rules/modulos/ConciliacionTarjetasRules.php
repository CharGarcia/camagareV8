<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\repositories\modulos\ConciliacionTarjetasRepository;

/**
 * Validaciones de negocio de Conciliación de Tarjetas.
 *
 * Nota sobre contabilidad: aquí NO se exige cuenta contable en ninguna validación.
 * La contabilidad es opcional en el sistema (empresa_formas_pago.id_cuenta_contable
 * es "Cuenta Contable (Opcional)"), así que una empresa sin plan de cuentas debe poder
 * conciliar igual. Qué impide generar el asiento lo evalúa el Service
 * (evaluarContabilidad) y se informa, no se bloquea.
 */
class ConciliacionTarjetasRules
{
    /** Alta o edición de la conciliación. $procesadora es la fila de empresa_formas_pago. */
    public function validarCabecera(array $data, ?array $procesadora): void
    {
        if (empty($data['id_forma_cobro'])) {
            throw new \Exception('Debe seleccionar la procesadora (forma de cobro con tarjeta) a conciliar.');
        }
        if ($procesadora === null) {
            throw new \Exception('La forma de cobro seleccionada no existe en esta empresa.');
        }
        if (!in_array(strtoupper((string) $procesadora['tipo']), ConciliacionTarjetasRepository::TIPOS_LIQUIDACION_DIFERIDA, true)) {
            throw new \Exception('Solo se concilian formas de cobro con tarjeta (Payphone, Nuvei o Tarjeta).');
        }

        $desde = (string) ($data['fecha_desde'] ?? '');
        $hasta = (string) ($data['fecha_hasta'] ?? '');
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            throw new \Exception('La fecha "desde" no puede ser posterior a la fecha "hasta".');
        }
        if (empty($data['fecha_conciliacion'])) {
            throw new \Exception('Debe indicar la fecha de la conciliación.');
        }
    }

    /**
     * «Depositado en»: opcional hasta el cierre, pero si se indica debe ser una forma de
     * cobro ACTIVA de esta empresa de las que se ofrecen como destino ($destinos, de
     * ConciliacionTarjetasRepository::getFormasDestino). Se admite conservar el destino
     * que la conciliación ya tenía guardado aunque después se haya desactivado.
     */
    public function validarDestino(?int $idDestino, array $destinos, ?int $idDestinoActual = null): void
    {
        if (empty($idDestino) || $idDestino === $idDestinoActual) {
            return;
        }
        foreach ($destinos as $d) {
            if ((int) $d['id'] === $idDestino) {
                return;
            }
        }
        throw new \Exception('La forma de cobro elegida en «Depositado en» no existe o está inactiva en Formas de Cobros y Pagos.');
    }

    /** Una línea del estado de cuenta digitada o editada a mano. */
    public function validarLinea(array $data): void
    {
        if (empty($data['fecha_movimiento'])) {
            throw new \Exception('La línea debe tener fecha.');
        }
        $bruto = (float) ($data['monto_bruto'] ?? 0);
        if ($bruto <= 0) {
            throw new \Exception('El valor bruto de la línea debe ser mayor a cero.');
        }

        $descuentos = round(
            (float) ($data['comision'] ?? 0) + (float) ($data['iva_comision'] ?? 0)
            + (float) ($data['retencion_ir'] ?? 0) + (float) ($data['retencion_iva'] ?? 0)
            + (float) ($data['otros_descuentos'] ?? 0),
            2
        );
        if ($descuentos < 0) {
            throw new \Exception('Los descuentos de la línea no pueden ser negativos.');
        }
        if ($descuentos > $bruto) {
            throw new \Exception('Los descuentos (comisión y retenciones) no pueden superar el valor bruto de la línea.');
        }
    }

    /**
     * Emparejar una línea del estado de cuenta con un cobro del sistema.
     *
     * @param array $linea  Fila de conciliacion_tarjetas_lineas
     * @param array $cobro  Fila devuelta por getCobrosPendientes()
     */
    public function validarCruce(?array $cabecera, ?array $linea, ?array $cobro, bool $yaCruzado): void
    {
        if ($cabecera === null) {
            throw new \Exception('La conciliación no existe.');
        }
        if ($cabecera['estado'] !== 'borrador') {
            throw new \Exception('Solo se puede modificar el cruce de una conciliación en borrador.');
        }
        if ($linea === null) {
            throw new \Exception('La línea del estado de cuenta no existe.');
        }
        if ($cobro === null) {
            throw new \Exception('El cobro seleccionado no existe o ya no está disponible.');
        }
        if ($yaCruzado) {
            throw new \Exception('Ese cobro ya está conciliado en otra conciliación.');
        }
    }

    /**
     * Cierre de la conciliación.
     *
     * @param float $diferencia Neto depositado − neto calculado de lo cruzado
     * @param float $tolerancia Descuadre aceptado (configurable por procesadora)
     */
    public function validarCierre(array $cabecera, int $totalCruces, float $diferencia, float $tolerancia): void
    {
        if ($cabecera['estado'] !== 'borrador') {
            throw new \Exception('Esta conciliación ya fue cerrada o anulada.');
        }
        if (empty($cabecera['id_forma_cobro_destino'])) {
            throw new \Exception('Debe indicar a qué forma de cobro (banco) ingresó el dinero.');
        }
        if ($totalCruces === 0) {
            throw new \Exception('No hay ningún cobro cruzado: no hay nada que conciliar.');
        }
        if (abs($diferencia) > $tolerancia + 0.0001) {
            throw new \Exception(sprintf(
                'La diferencia entre el neto depositado y lo conciliado es %s, mayor que la tolerancia permitida (%s). '
                . 'Revise las comisiones y retenciones de las líneas cruzadas.',
                number_format($diferencia, 2),
                number_format($tolerancia, 2)
            ));
        }
    }

    public function validarAnulacion(array $cabecera): void
    {
        if ($cabecera['estado'] === 'anulada') {
            throw new \Exception('Esta conciliación ya está anulada.');
        }
    }

    public function validarEliminacion(array $cabecera): void
    {
        if ($cabecera['estado'] === 'cerrada') {
            throw new \Exception('No se puede eliminar una conciliación cerrada. Anúlela primero.');
        }
    }
}
