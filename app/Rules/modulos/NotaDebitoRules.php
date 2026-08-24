<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class NotaDebitoRules
{
    public function validar(array $data): void
    {
        if (empty($data['id_cliente'])) {
            throw new Exception("El cliente es obligatorio.");
        }

        if (empty($data['id_establecimiento'])) {
            throw new Exception("El establecimiento es obligatorio.");
        }

        if (empty($data['id_punto_emision'])) {
            throw new Exception("El punto de emisión es obligatorio.");
        }

        if (empty($data['secuencial'])) {
            throw new Exception("El secuencial es obligatorio.");
        }

        if (empty($data['num_doc_modificado'])) {
            throw new Exception("El número de documento modificado es obligatorio.");
        }

        if (empty($data['fecha_emision_docs_sustento'])) {
            throw new Exception("La fecha del documento sustento es obligatoria.");
        }

        if (empty($data['motivos']) || !is_array($data['motivos'])) {
            throw new Exception("La nota de débito debe tener al menos un motivo.");
        }

        foreach ($data['motivos'] as $index => $motivo) {
            if (empty($motivo['razon'])) {
                throw new Exception("La razón del motivo " . ($index + 1) . " es obligatoria.");
            }
            if (($motivo['valor'] ?? 0) <= 0) {
                throw new Exception("El valor del motivo " . ($index + 1) . " debe ser mayor a cero.");
            }
        }

        if (!empty($data['pagos']) && is_array($data['pagos'])) {
            $sumaPagos = 0.0;
            foreach ($data['pagos'] as $index => $pago) {
                if (empty($pago['forma_pago'])) {
                    throw new Exception("La forma de pago " . ($index + 1) . " es obligatoria.");
                }
                if (($pago['total'] ?? 0) < 0) {
                    throw new Exception("El total del pago " . ($index + 1) . " no puede ser negativo.");
                }
                $sumaPagos += (float) ($pago['total'] ?? 0);
            }

            $valorTotal = (float) ($data['importe_total'] ?? 0);
            if (abs($sumaPagos - $valorTotal) > 0.01) {
                throw new Exception("La suma de los pagos ($sumaPagos) no coincide con el valor total de la nota de débito ($valorTotal).");
            }
        }

        $this->validarFichaTecnicaSri($data);
    }

    /**
     * Formato exigido por la Ficha Técnica del SRI (longitudes).
     *
     * El generador de XML no comprueba nada: escribe lo que recibe. Sin esto, un
     * texto demasiado largo pasa sin ruido y el comprobante se rechaza al
     * enviarlo, ya creado y numerado.
     *
     * La nota de débito no lleva `detalles` sino `motivos`, cuya razón viaja al
     * XML como <razon> y comparte el límite de 300 caracteres.
     */
    private function validarFichaTecnicaSri(array $data): void
    {
        $errores = \App\Helpers\SriFichaTecnica::erroresInfoAdicional($data['info_adicional'] ?? []);

        foreach (array_values($data['motivos'] ?? []) as $i => $motivo) {
            $error = \App\Helpers\SriFichaTecnica::excedeLongitud(
                'Motivo #' . ($i + 1) . ': la razón',
                (string) ($motivo['razon'] ?? ''),
                \App\Helpers\SriFichaTecnica::MAX_DESCRIPCION_DETALLE
            );
            if ($error !== null) {
                $errores[] = $error;
            }
        }

        if ($errores) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
