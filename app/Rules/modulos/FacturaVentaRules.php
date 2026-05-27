<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class FacturaVentaRules
{
    /**
     * Valida la creación/edición de una factura aplicando todas las reglas
     * de cabecera, detalle y configuración del establecimiento.
     */
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
            throw new \Exception('La factura debe tener al menos un producto o servicio.');
    }

    private function validarPagos(array $data): void
    {
        if (empty($data['pagos']))
            throw new \Exception('Debe especificar al menos una forma de pago.');

        $total = round((float) ($data['importe_total'] ?? 0), 2);
        if ($total < 0)
            throw new \Exception('El total de la factura no puede ser negativo.');

        $sumPagos = 0.0;
        foreach ($data['pagos'] as $p) {
            $sumPagos += (float) ($p['total'] ?? 0);
        }
        $sumPagos = round($sumPagos, 2);

        if (abs($sumPagos - $total) > 0.001) {
            throw new \Exception(
                sprintf(
                    'La suma de formas de pago ($%s) no coincide con el total de la factura ($%s).',
                    number_format($sumPagos, 2),
                    number_format($total, 2)
                )
            );
        }
    }

    /**
     * Valida reglas específicas configuradas en el establecimiento
     */
    private function validarReglasEstablecimiento(array $data, array $estConfig): void
    {
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
        
        $facturacionLibre = $toBool($estConfig['facturacion_libre'] ?? true);
        $limiteCF         = (float) ($estConfig['valor_limite_consumidor_final'] ?? 50);
        $total            = (float) ($data['importe_total'] ?? 0);

        // 1. Validar Límite Consumidor Final
        // Se asume que el tipo_id_cliente viene en la data (puesto por el service/controller)
        $tipoIdCliente = (string) ($data['tipo_id_cliente'] ?? '');
        if ($tipoIdCliente === '07' && $total >= $limiteCF) { // 07 = Consumidor Final en Ecuador
            throw new \Exception("Para ventas mayores o iguales a $" . number_format($limiteCF, 2) . " no se permite el uso de Consumidor Final.");
        }

        // 2. Validar Facturación Libre vs Catálogo
        foreach ($data['detalles'] as $i => $d) {
            $num = $i + 1;
            $esLibre = $toBool($d['es_libre'] ?? false);
            
            if (!$facturacionLibre && $esLibre) {
                throw new \Exception("Línea #{$num}: No se permite el ingreso de ítems libres. Debe seleccionar productos del catálogo.");
            }

            if (empty($d['nombre']) && empty($d['descripcion'])) {
                throw new \Exception("Línea #{$num}: El nombre o descripción del producto/servicio es obligatorio.");
            }

            // 3. Reglas de Inventario (Lotes, Caducidad, NUP)
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

        // REGLA CRÍTICA: Los servicios (02), ítems libres o no inventariables NO requieren lote/caducidad/nup
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
