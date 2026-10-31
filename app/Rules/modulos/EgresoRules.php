<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class EgresoRules
{
    public function validar(array $data): void
    {
        // Validaciones Básicas
        if (empty($data['fecha_emision'])) {
            throw new Exception("La fecha de emisión es obligatoria.");
        }
        
        if (strtotime($data['fecha_emision']) > strtotime(date('Y-m-d'))) {
            throw new Exception("La fecha de emisión no puede ser posterior a la fecha actual.");
        }

        if (empty($data['tipo_egreso'])) {
            throw new Exception("El tipo de egreso es obligatorio.");
        }
        
        if (empty($data['tipo_sujeto'])) {
            throw new Exception("El tipo de sujeto (Proveedor, Empleado) es obligatorio.");
        }

        // Igual que FacturaVentaRules: validar el punto de emisión y el secuencial en
        // crudo, no el numero_egreso ya compuesto (EgresosController::guardarAjax lo arma
        // con '001' por defecto y el secuencial en ceros, así que nunca queda "vacío" aunque
        // no haya serie configurada — sin este chequeo se cuelan egresos con secuencial 0).
        if (empty($data['id_punto_emision'])) {
            throw new Exception("Debe seleccionar una serie (punto de emisión) con el secuencial de Egresos configurado. Configúrelo en Empresa → Secuenciales.");
        }

        if (empty($data['secuencial']) || (int) $data['secuencial'] <= 0) {
            throw new Exception("El secuencial es obligatorio y debe ser mayor a cero. Verifique que el punto de emisión tenga configurado el secuencial de Egresos (Empresa → Secuenciales).");
        }

        if (empty($data['numero_egreso'])) {
            throw new Exception("El número de egreso no ha sido generado.");
        }

        // Validar Sujeto según el Tipo
        if ($data['tipo_sujeto'] === 'PROVEEDOR' && empty($data['id_proveedor'])) {
            throw new Exception("Debe seleccionar el Proveedor para este egreso.");
        }
        
        if ($data['tipo_sujeto'] === 'EMPLEADO' && empty($data['id_empleado'])) {
            throw new Exception("Debe seleccionar el Empleado para este egreso.");
        }

        // Validar Detalles
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new Exception("El egreso debe tener al menos una línea de detalle o documento.");
        }

        // Un egreso puede combinar documentos de módulo (Compra/Liquidación/Nómina/...) con
        // líneas MANUAL ("otros conceptos") a la vez. Si se mezclan, cada línea MANUAL debe
        // traer su propia cuenta contable: sin eso, el asiento la clasificaría por defecto en
        // la cuenta "oficial" del concepto de cabecera (p. ej. Cuentas por Pagar de la compra),
        // metiendo mal un gasto que no tiene relación con esa cartera.
        $tiposDetalle = array_unique(array_map(fn($d) => $d['tipo_documento'] ?? '', $data['detalles']));
        $hayModulo = !empty(array_diff($tiposDetalle, ['MANUAL']));

        $totalDetalle = 0;
        foreach ($data['detalles'] as $idx => $det) {
            $monto = (float) ($det['monto_pagado'] ?? 0);
            if ($monto <= 0) {
                throw new Exception("El monto a pagar en la línea #" . ($idx + 1) . " debe ser mayor a 0.");
            }

            $tipoDoc = $det['tipo_documento'] ?? '';
            if ($tipoDoc !== 'MANUAL') {
                $saldoAnt = (float) ($det['saldo_anterior'] ?? 0);
                if ($monto > $saldoAnt + 0.01) {
                     throw new Exception("En la línea #" . ($idx + 1) . ", el monto a pagar ($" . number_format($monto, 2) . ") no puede superar el saldo pendiente ($" . number_format($saldoAnt, 2) . ").");
                }
            } elseif ($hayModulo && empty($det['id_cuenta_contable'])) {
                throw new Exception("La línea #" . ($idx + 1) . " (otros conceptos) debe indicar su cuenta contable, porque este egreso también paga un documento de otro tipo.");
            }

            $totalDetalle += $monto;
        }

        // Validar Formas de Pago
        if (empty($data['pagos']) || !is_array($data['pagos'])) {
            throw new Exception("Debe registrar al menos una forma de pago (salida de dinero).");
        }

        $totalPagos = 0;
        foreach ($data['pagos'] as $idx => $p) {
            if (empty($p['id_forma_pago'])) {
                throw new Exception("Falta seleccionar la forma de pago en la línea #" . ($idx + 1) . ".");
            }
            $monto = (float) ($p['monto'] ?? 0);
            if ($monto <= 0) {
                throw new Exception("El monto de pago #" . ($idx + 1) . " debe ser mayor a cero.");
            }
            if (!empty($p['fecha_cobro']) && strtotime($p['fecha_cobro']) > strtotime('+1 year')) {
                throw new Exception("La fecha de cobro del cheque en la línea #" . ($idx + 1) . " no puede ser mayor a 1 año desde hoy.");
            }
            $totalPagos += $monto;
        }

        // Validar Cuadratura
        if (abs($totalDetalle - $totalPagos) > 0.01) {
            throw new Exception("El total detallado ($" . number_format($totalDetalle, 2) . ") no coincide con el total pagado ($" . number_format($totalPagos, 2) . ").");
        }

        // Validar Monto Total
        $montoTotalInformado = (float) ($data['monto_total'] ?? 0);
        if (abs($montoTotalInformado - $totalDetalle) > 0.01) {
            throw new Exception("Inconsistencia en la sumatoria total.");
        }
    }

    /**
     * Una compra solo se paga si está vigente y aprobada: ni pendiente de aprobación
     * (checkpoint 'aprobacion_compras'), ni rechazada, ni anulada. Es el mismo criterio de
     * TiposComprobanteCompra::sqlCompraPagable() en los listados de documentos por pagar;
     * aquí se repite al guardar para que no se pueda saltar enviando el id a mano.
     *
     * @param string|null $estado compras_cabecera.estado; null si la compra no existe o fue eliminada.
     */
    public function validarCompraPagable(?string $estado, string $numeroDocumento): void
    {
        if ($estado === null) {
            throw new Exception("La compra {$numeroDocumento} no existe o fue eliminada: no se puede pagar.");
        }

        $normalizado = strtoupper(trim($estado));
        if ($normalizado === strtoupper(\App\Services\modulos\ComprasService::ESTADO_PENDIENTE)) {
            throw new Exception("La compra {$numeroDocumento} está pendiente de aprobación: no se puede pagar hasta que la aprueben.");
        }
        if (in_array($normalizado, \App\Helpers\TiposComprobanteCompra::ESTADOS_COMPRA_SIN_DEUDA, true)) {
            $motivo = str_starts_with($normalizado, 'RECHAZAD') ? 'fue rechazada en la aprobación' : 'está anulada';
            throw new Exception("La compra {$numeroDocumento} {$motivo}: no se puede pagar.");
        }
    }

    /**
     * Semanas/quincenas contra el rol mensual del mismo empleado y mes. El mensual descuenta
     * solo lo YA pagado en ellas, así que:
     *  (A) el MENSUAL no se paga mientras haya semanas/quincenas con saldo pendiente;
     *  (B) una SEMANAL/QUINCENA no se paga si el MENSUAL ya tiene pagos.
     *
     * @param array $linea      EgresoRepository::getLineaRolNomina()
     * @param array $pendientes EgresoRepository::getParcialesPendientesEmpleado() (solo para A)
     */
    public function validarNominaParcialMensual(array $linea, array $pendientes, bool $mensualPagado): void
    {
        $mesAnio  = str_pad((string) $linea['periodo_mes'], 2, '0', STR_PAD_LEFT) . '/' . $linea['periodo_anio'];
        $empleado = (string) $linea['empleado'];

        if ($linea['tipo_rol'] === 'MENSUAL' && $pendientes) {
            $lista = implode(', ', array_map(
                fn($p) => self::nombreParcial((string) $p['tipo_rol'], (int) $p['numero_periodo']) . ' ($' . number_format((float) $p['saldo'], 2) . ')',
                $pendientes
            ));
            throw new Exception(
                "No se puede pagar el Rol Mensual {$mesAnio} de {$empleado}: tiene pendiente de pago {$lista}. "
                . "Pague primero esa semana/quincena en otro egreso (el rol mensual la descontará) o anúlela si no se va a pagar."
            );
        }
        if ($linea['tipo_rol'] !== 'MENSUAL' && $mensualPagado) {
            $nombre = self::nombreParcial((string) $linea['tipo_rol'], (int) $linea['numero_periodo']);
            throw new Exception(
                "No se puede pagar la {$nombre} {$mesAnio} de {$empleado}: su Rol Mensual {$mesAnio} ya fue pagado "
                . "sin descontarla, así que pagarla ahora sería un doble pago."
            );
        }
    }

    /**
     * (A) Una línea de rol no se paga mientras tenga anticipos sin pagar o cuotas de Préstamo
     * Empresa sin desembolsar en su período: el rol no los descontó.
     *
     * @param array $novedades EgresoRepository::getNovedadesSinDesembolsoLinea()
     */
    public function validarRolSinNovedadesPendientes(array $linea, array $novedades): void
    {
        if (!$novedades) {
            return;
        }
        $lista = implode(', ', array_map(
            fn($n) => $n['tipo_nombre'] . ' ($' . number_format((float) $n['monto'], 2) . ', '
                . ((string) $n['tipo_codigo'] === '3' ? 'sin pagar' : 'préstamo sin desembolsar') . ')',
            $novedades
        ));
        throw new Exception(
            'No se puede pagar ' . self::conArticulo((string) $linea['tipo_rol'], (int) $linea['numero_periodo']) . ' '
            . self::mesAnio($linea) . " de {$linea['empleado']}: tiene {$lista}, que el rol no descontó. "
            . 'Páguelo primero en otro egreso (Egresos → Nómina; el rol se recalcula y lo descuenta) o cambie la novedad a otro período.'
        );
    }

    /** (B) Un anticipo no se paga si el rol donde se descontaría ya tiene pagos. */
    public function validarAnticipoSinRolPagado(array $anticipo, bool $rolPagado): void
    {
        if (!$rolPagado) {
            return;
        }
        throw new Exception(
            "No se puede pagar el {$anticipo['tipo_nombre']} " . self::mesAnio($anticipo) . " de {$anticipo['empleado']}: "
            . self::conArticulo((string) $anticipo['tipo_rol'], 0) . ' de ese período ya se pagó sin descontarlo, así que no se recuperaría. '
            . 'Si corresponde pagarlo, anule primero el egreso de ese rol, pague el anticipo y vuelva a pagar el rol (ya con el descuento).'
        );
    }

    /**
     * (B) El Préstamo Empresa no se desembolsa si alguna de sus cuotas cae en un rol ya pagado
     * que no la descontó: esa cuota no se cobraría nunca.
     *
     * @param array $perdidas EgresoRepository::getCuotasPrestamoPerdidas()
     */
    public function validarPrestamoSinCuotasPerdidas(array $perdidas): void
    {
        if (!$perdidas) {
            return;
        }
        $empleado = (string) $perdidas[0]['empleado'];
        $lista = implode(', ', array_map(
            fn($c) => self::nombreRol((string) $c['tipo_rol'], 0) . ' ' . self::mesAnio($c) . ' ($' . number_format((float) $c['valor'], 2) . ')',
            $perdidas
        ));
        throw new Exception(
            "No se puede desembolsar el Préstamo Empresa de {$empleado}: estas cuotas caen en roles ya pagados que no las descontaron: {$lista}. "
            . 'Anule primero el egreso de esos roles, registre el desembolso y vuelva a pagarlos (ya con la cuota descontada).'
        );
    }

    private static function nombreRol(string $tipoRol, int $numero): string
    {
        return $tipoRol === 'MENSUAL' ? 'Rol Mensual' : self::nombreParcial($tipoRol, $numero);
    }

    /** "el Rol Mensual" / "la Quincena 1" / "la Semana". */
    private static function conArticulo(string $tipoRol, int $numero): string
    {
        return ($tipoRol === 'MENSUAL' ? 'el ' : 'la ') . self::nombreRol($tipoRol, $numero);
    }

    private static function mesAnio(array $r): string
    {
        return str_pad((string) $r['periodo_mes'], 2, '0', STR_PAD_LEFT) . '/' . $r['periodo_anio'];
    }

    private static function nombreParcial(string $tipoRol, int $numero): string
    {
        return ($tipoRol === 'SEMANAL' ? 'Semana' : 'Quincena') . ($numero > 0 ? ' ' . $numero : '');
    }
}
