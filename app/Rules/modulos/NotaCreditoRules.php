<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class NotaCreditoRules
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

        if (empty($data['motivo'])) {
            throw new Exception("El motivo de la nota de crédito es obligatorio.");
        }

        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new Exception("La nota de crédito debe tener al menos un detalle.");
        }

        foreach ($data['detalles'] as $index => $detalle) {
            if (empty($detalle['descripcion'])) {
                throw new Exception("La descripción del detalle " . ($index + 1) . " es obligatoria.");
            }
            if (($detalle['cantidad'] ?? 0) <= 0) {
                throw new Exception("La cantidad del detalle " . ($index + 1) . " debe ser mayor a cero.");
            }
            if (($detalle['precio_unitario'] ?? 0) < 0) {
                throw new Exception("El precio unitario del detalle " . ($index + 1) . " no puede ser negativo.");
            }
        }
    }
}
