<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class TraspasoRules
{
    public function validar(array $data): void
    {
        if (empty($data['fecha_emision'])) {
            throw new Exception("La fecha de emisión es obligatoria.");
        }

        if (strtotime($data['fecha_emision']) > strtotime(date('Y-m-d'))) {
            throw new Exception("La fecha de emisión no puede ser posterior a la fecha actual.");
        }

        if (empty($data['numero_traspaso'])) {
            throw new Exception("El número de traspaso no ha sido generado.");
        }

        if (empty($data['id_forma_origen'])) {
            throw new Exception("Debe seleccionar la forma de pago de origen.");
        }

        if (empty($data['id_forma_destino'])) {
            throw new Exception("Debe seleccionar la forma de pago de destino.");
        }

        if ((int) $data['id_forma_origen'] === (int) $data['id_forma_destino']) {
            throw new Exception("La forma de origen y la de destino no pueden ser la misma.");
        }

        $monto = (float) ($data['monto'] ?? 0);
        if ($monto <= 0) {
            throw new Exception("El monto del traspaso debe ser mayor a cero.");
        }
    }
}
