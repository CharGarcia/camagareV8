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

        $this->validarFichaTecnicaSri($data);
    }

    /**
     * La bodega de reintegro es obligatoria: sin ella la nota de crédito se guardaba y
     * autorizaba sin devolver nada al inventario (un usuario con todas las bodegas
     * denegadas veía el combo vacío y no recibía ningún aviso).
     *
     * @param bool $sinBodegasPermitidas el usuario no tiene ninguna bodega asignada
     * @param bool $bodegaPermitida      la bodega enviada es de la empresa y el usuario tiene acceso
     */
    public function validarBodegaReintegro(array $data, bool $sinBodegasPermitidas, bool $bodegaPermitida): void
    {
        if ($sinBodegasPermitidas) {
            throw new Exception("No tiene bodegas asignadas. Para emitir una nota de crédito necesita al menos una bodega donde reintegrar la mercadería; solicítela al administrador (Bodegas → Accesos).");
        }

        if (empty($data['id_bodega'])) {
            throw new Exception("Seleccione la bodega de reintegro.");
        }

        if (!$bodegaPermitida) {
            throw new Exception("No tiene acceso a la bodega de reintegro seleccionada.");
        }
    }

    /**
     * Formato exigido por la Ficha Técnica del SRI (longitudes y decimales).
     *
     * El generador de XML no comprueba nada: escribe lo que recibe. Sin esto, un
     * texto demasiado largo o un precio con ocho decimales pasa sin ruido y el
     * comprobante se rechaza al enviarlo, ya creado y numerado.
     */
    private function validarFichaTecnicaSri(array $data): void
    {
        $errores = array_merge(
            \App\Helpers\SriFichaTecnica::erroresDetalles($data['detalles'] ?? []),
            \App\Helpers\SriFichaTecnica::erroresInfoAdicional($data['info_adicional'] ?? [])
        );

        if ($errores) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
