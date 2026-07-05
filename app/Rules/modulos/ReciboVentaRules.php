<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio del módulo Recibos de Venta.
 * Espejo de FacturaVentaRules (mismos ítems y reglas de inventario), sin las
 * validaciones específicas del SRI.
 */
class ReciboVentaRules
{
    public function validar(array $data, array $estConfig): void
    {
        $this->validarCabecera($data);
        $this->validarPagos($data);
        $this->validarReglasEstablecimiento($data, $estConfig);
    }

    private function validarCabecera(array $data): void
    {
        if (empty($data['fecha_emision']))
            throw new \Exception('La fecha de emisión es obligatoria.');

        if (empty($data['id_cliente']))
            throw new \Exception('Debe seleccionar un cliente.');

        if (empty($data['id_establecimiento']))
            throw new \Exception('Debe seleccionar un establecimiento.');

        if (empty($data['id_punto_emision']))
            throw new \Exception('Debe seleccionar un punto de emisión.');

        if (empty($data['secuencial']))
            throw new \Exception('El secuencial es obligatorio.');

        $diasCred = (int) ($data['dias_credito'] ?? 0);
        if ($diasCred < 0)
            throw new \Exception('Los días de crédito no pueden ser menores a cero.');

        if (empty($data['detalles']))
            throw new \Exception('El recibo debe tener al menos un producto o servicio.');
    }

    private function validarPagos(array $data): void
    {
        $total = round((float) ($data['importe_total'] ?? 0), 2);
        if ($total < 0)
            throw new \Exception('El total del recibo no puede ser negativo.');

        // Las formas de pago son OPCIONALES en el recibo: el cobro se gestiona
        // por la pestaña "Pagos" (ingresos). Si no se envían, no se valida la suma.
        if (empty($data['pagos'])) {
            return;
        }

        $sumPagos = 0.0;
        foreach ($data['pagos'] as $p) {
            $sumPagos += (float) ($p['total'] ?? 0);
        }
        $sumPagos = round($sumPagos, 2);

        if (abs($sumPagos - $total) > 0.001) {
            throw new \Exception(
                sprintf(
                    'La suma de formas de pago ($%s) no coincide con el total del recibo ($%s).',
                    number_format($sumPagos, 2),
                    number_format($total, 2)
                )
            );
        }
    }

    private function validarReglasEstablecimiento(array $data, array $estConfig): void
    {
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');

        $facturacionLibre = $toBool($estConfig['facturacion_libre'] ?? true);

        foreach ($data['detalles'] as $i => $d) {
            $num = $i + 1;
            $esLibre = $toBool($d['es_libre'] ?? false);

            if (!$facturacionLibre && $esLibre) {
                throw new \Exception("Línea #{$num}: No se permite el ingreso de ítems libres. Debe seleccionar productos del catálogo.");
            }

            if (empty($d['nombre']) && empty($d['descripcion'])) {
                throw new \Exception("Línea #{$num}: El nombre o descripción del producto/servicio es obligatorio.");
            }

            $this->validarItemInventario($d, $num, $estConfig);
        }
    }

    private function validarItemInventario(array $d, int $num, array $estConfig): void
    {
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');

        $esInventariable = $toBool($d['inventariable'] ?? false);
        $tipoProduccion  = trim((string)($d['tipo_produccion'] ?? ''));
        $esLibre         = $toBool($d['es_libre'] ?? false);
        $nombreItem      = $d['nombre'] ?? ($d['descripcion'] ?? "Línea #{$num}");

        if ($tipoProduccion === '02' || $esLibre || !$esInventariable) {
            return;
        }

        if ($toBool($estConfig['obligatorio_lotes'] ?? false) && empty($d['lote'])) {
            throw new \Exception("{$nombreItem}: El número de lote es obligatorio para productos inventariables.");
        }

        if ($toBool($estConfig['obligatorio_caducidad'] ?? false) && empty($d['caducidad'])) {
            throw new \Exception("{$nombreItem}: La fecha de caducidad es obligatoria para productos inventariables.");
        }

        if ($toBool($estConfig['obligatorio_nup'] ?? false) && empty($d['nup'])) {
            throw new \Exception("{$nombreItem}: El número de serie (NUP) es obligatorio para productos inventariables.");
        }
    }
}
