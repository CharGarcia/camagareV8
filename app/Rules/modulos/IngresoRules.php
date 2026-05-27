<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class IngresoRules
{
    public function validar(array $data): void
    {
        if (empty($data['fecha_emision'])) {
            throw new \Exception('La fecha de emisión es obligatoria.');
        }

        if (empty($data['secuencial'])) {
            throw new \Exception('El secuencial es obligatorio.');
        }

        if (empty($data['tipo_ingreso'])) {
            throw new \Exception('Debe seleccionar el tipo de ingreso.');
        }

        if (empty(trim($data['recibo_de'] ?? ''))) {
            throw new \Exception('El campo "Recibo de" es obligatorio.');
        }

        // Validar Concepto si es OTRO
        if ($data['tipo_ingreso'] === 'OTRO' && empty($data['id_ingreso_concepto'])) {
            throw new \Exception('Para otros ingresos se debe seleccionar un concepto.');
        }

        if (empty($data['detalles'])) {
            throw new \Exception('El ingreso debe contener al menos un detalle.');
        }

        if (empty($data['pagos'])) {
            throw new \Exception('Debe especificar al menos una forma de cobro.');
        }

        $total = round((float) ($data['monto_total'] ?? 0), 2);
        if ($total <= 0) {
            throw new \Exception('El monto total del ingreso debe ser mayor a cero.');
        }

        $this->validarTotalesDetallePagos($data, $total);
    }

    private function validarTotalesDetallePagos(array $data, float $total): void
    {
        // Suma de Detalles
        $sumDetalles = 0.0;
        foreach ($data['detalles'] as $d) {
            $cob = (float) ($d['monto_cobrado'] ?? 0);
            $saldoAnt = (float) ($d['saldo_anterior'] ?? 0);

            if ($cob <= 0) {
                throw new \Exception('El monto cobrado en los detalles debe ser mayor a cero.');
            }

            // Validar tope de saldo si es una factura
            if (($d['tipo_documento'] ?? '') === 'FACTURA' && round($cob, 2) > round($saldoAnt, 2)) {
                throw new \Exception(sprintf(
                    'El monto a cobrar ($%s) en el documento %s no puede exceder el saldo pendiente ($%s).',
                    number_format($cob, 2),
                    htmlspecialchars($d['numero_documento'] ?? ''),
                    number_format($saldoAnt, 2)
                ));
            }

            $sumDetalles += $cob;
        }
        $sumDetalles = round($sumDetalles, 2);

        // Suma de Pagos
        $sumPagos = 0.0;
        foreach ($data['pagos'] as $p) {
            $monto = (float) ($p['monto'] ?? 0);
            if ($monto <= 0) {
                throw new \Exception('El monto en las formas de cobro debe ser mayor a cero.');
            }
            $sumPagos += $monto;
        }
        $sumPagos = round($sumPagos, 2);

        // Verificar correspondencia
        if (abs($sumDetalles - $total) > 0.001) {
            throw new \Exception(sprintf('La suma de los detalles ($%s) no coincide con el total del ingreso ($%s).', number_format($sumDetalles, 2), number_format($total, 2)));
        }

        if (abs($sumPagos - $total) > 0.001) {
            throw new \Exception(sprintf('La suma de las formas de cobro ($%s) no coincide con el total del ingreso ($%s).', number_format($sumPagos, 2), number_format($total, 2)));
        }
    }
}
