<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class FacturaReembolsoRules
{
    public function validar(array $data): void
    {
        $this->validarCabecera($data);
        $this->validarDetalles($data);
        $this->validarTerceros($data);
        $this->validarCuadreReembolso($data);
        $this->validarPagos($data);
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

        $total = (float) ($data['importe_total'] ?? 0);
        if ($total < 0)
            throw new \Exception('El total de la factura no puede ser negativo.');
    }

    private function validarDetalles(array $data): void
    {
        if (empty($data['detalles']) || !is_array($data['detalles']))
            throw new \Exception('La factura de reembolso debe tener al menos un ítem en el detalle.');

        foreach ($data['detalles'] as $i => $d) {
            $num = $i + 1;
            if (empty($d['descripcion']))
                throw new \Exception("Línea #{$num}: la descripción es obligatoria.");
            if ((float) ($d['cantidad'] ?? 0) <= 0)
                throw new \Exception("Línea #{$num}: la cantidad debe ser mayor a cero.");
            if ((float) ($d['precio_unitario'] ?? -1) < 0)
                throw new \Exception("Línea #{$num}: el precio unitario no puede ser negativo.");
        }
    }

    /**
     * Reembolso SRI (ATS 41): cada línea referencia un documento de un tercero
     * (proveedor) que la empresa pagó a nombre del cliente. Una factura de
     * reembolso sin terceros no es válida — es la razón de ser del documento.
     */
    private function validarTerceros(array $data): void
    {
        if (empty($data['terceros']) || !is_array($data['terceros'])) {
            throw new \Exception('Debe agregar al menos un tercero reembolsado (comprobante del proveedor pagado a nombre del cliente).');
        }

        $fechaFactura = $data['fecha_emision'] ?? null;

        foreach ($data['terceros'] as $i => $t) {
            $num = $i + 1;

            if (!in_array((string) ($t['tipo_identificacion_proveedor_reembolso'] ?? ''), ['04', '05', '06', '07', '08'], true)) {
                throw new \Exception("Tercero #{$num}: el tipo de identificación del proveedor no es válido.");
            }
            if (empty($t['identificacion_proveedor_reembolso'])) {
                throw new \Exception("Tercero #{$num}: debe indicar la identificación del proveedor.");
            }
            if (!in_array((string) ($t['tipo_proveedor_reembolso'] ?? ''), ['01', '02'], true)) {
                throw new \Exception("Tercero #{$num}: el tipo de proveedor debe ser 01 (servicios profesionales) o 02 (gasto).");
            }
            if (
                empty($t['cod_doc_reembolso']) || empty($t['estab_doc_reembolso']) || empty($t['pto_emi_doc_reembolso'])
                || empty($t['secuencial_doc_reembolso']) || empty($t['fecha_emision_doc_reembolso']) || empty($t['numero_autorizacion_doc_reemb'])
            ) {
                throw new \Exception("Tercero #{$num}: faltan datos del comprobante del proveedor (tipo, serie, secuencial, fecha o autorización).");
            }
            if ($fechaFactura && !empty($t['fecha_emision_doc_reembolso']) && $t['fecha_emision_doc_reembolso'] > $fechaFactura) {
                throw new \Exception("Tercero #{$num}: la fecha del comprobante del proveedor no puede ser posterior a la fecha de la factura.");
            }
            if (empty($t['impuestos']) || !is_array($t['impuestos'])) {
                throw new \Exception("Tercero #{$num}: debe traer al menos un impuesto (base imponible y valor).");
            }
        }
    }

    /**
     * Cuadre entre los 3 totales agregados de infoFactura (calculados por el
     * Service desde $data['terceros']) y la suma real de los terceros — defensa
     * adicional por si el payload llega manipulado. Tolerancia de 1 centavo.
     */
    private function validarCuadreReembolso(array $data): void
    {
        $sumaBase = 0.0;
        $sumaImpuesto = 0.0;
        foreach ($data['terceros'] ?? [] as $t) {
            foreach ($t['impuestos'] ?? [] as $imp) {
                $sumaBase     += (float) ($imp['base_imponible'] ?? 0);
                $sumaImpuesto += (float) ($imp['valor'] ?? 0);
            }
        }

        $baseDeclarada = round((float) ($data['total_base_imponible_reembolso'] ?? $sumaBase), 2);
        $impuestoDeclarado = round((float) ($data['total_impuesto_reembolso'] ?? $sumaImpuesto), 2);

        if (abs($baseDeclarada - round($sumaBase, 2)) > 0.01) {
            throw new \Exception('El total de base imponible reembolsada no cuadra con la suma de los terceros.');
        }
        if (abs($impuestoDeclarado - round($sumaImpuesto, 2)) > 0.01) {
            throw new \Exception('El total de impuesto reembolsado no cuadra con la suma de los terceros.');
        }
    }

    private function validarPagos(array $data): void
    {
        if (empty($data['pagos']))
            throw new \Exception('Debe especificar al menos una forma de pago.');

        $total = round((float) ($data['importe_total'] ?? 0), 2);
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
}
