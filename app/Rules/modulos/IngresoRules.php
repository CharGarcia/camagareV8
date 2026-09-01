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

        if (strtotime($data['fecha_emision']) > strtotime(date('Y-m-d'))) {
            throw new \Exception('La fecha de emisión no puede ser posterior a la fecha actual.');
        }

        // Igual que FacturaVentaRules: validar el punto de emisión además del secuencial
        // (IngresosController::guardarAjax compone numero_ingreso con '001' por defecto,
        // así que solo el chequeo de secuencial en crudo evita que se cuele un valor sin
        // serie configurada).
        if (empty($data['id_punto_emision'])) {
            throw new \Exception('Debe seleccionar una serie (punto de emisión) con el secuencial de Ingresos configurado. Configúrelo en Empresa → Secuenciales.');
        }

        if (empty($data['secuencial']) || (int) $data['secuencial'] <= 0) {
            throw new \Exception('El secuencial es obligatorio y debe ser mayor a cero. Verifique que el punto de emisión tenga configurado el secuencial de Ingresos (Empresa → Secuenciales).');
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
        // Un ingreso puede combinar documentos de módulo (Factura/Recibo/Factura reembolso/...)
        // con líneas OTRO ("otros conceptos") a la vez. Si se mezclan, cada línea OTRO debe
        // traer su propia cuenta contable: sin eso, el asiento la clasificaría por defecto en
        // la cuenta "oficial" del concepto de cabecera (p. ej. Cuentas por Cobrar de la
        // factura), metiendo mal un ingreso que no tiene relación con esa cartera.
        $tiposDetalle = array_unique(array_map(fn($d) => $d['tipo_documento'] ?? '', $data['detalles']));
        $hayModulo = !empty(array_diff($tiposDetalle, ['OTRO']));

        // Suma de Detalles
        $sumDetalles = 0.0;
        foreach ($data['detalles'] as $d) {
            $cob = (float) ($d['monto_cobrado'] ?? 0);
            $saldoAnt = (float) ($d['saldo_anterior'] ?? 0);

            if ($cob <= 0) {
                throw new \Exception('El monto cobrado en los detalles debe ser mayor a cero.');
            }

            $tipoDoc = $d['tipo_documento'] ?? '';

            // Validar tope de saldo si es una factura
            if ($tipoDoc === 'FACTURA' && round($cob, 2) > round($saldoAnt, 2)) {
                throw new \Exception(sprintf(
                    'El monto a cobrar ($%s) en el documento %s no puede exceder el saldo pendiente ($%s).',
                    number_format($cob, 2),
                    htmlspecialchars($d['numero_documento'] ?? ''),
                    number_format($saldoAnt, 2)
                ));
            }

            if ($tipoDoc === 'OTRO' && $hayModulo && empty($d['id_cuenta_contable'])) {
                throw new \Exception('Una línea de "otros conceptos" debe indicar su cuenta contable, porque este ingreso también cobra un documento de otro tipo.');
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
