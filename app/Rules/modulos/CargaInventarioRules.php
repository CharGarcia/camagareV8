<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio para Cargas de Inventario.
 */
class CargaInventarioRules
{
    public const TIPOS = ['entrada', 'salida', 'ajuste'];

    /**
     * Valida la cabecera de la carga. Lanza InvalidArgumentException si algo falla.
     */
    public function validarCabecera(array $data): void
    {
        $tipo = $data['tipo_movimiento'] ?? '';
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de movimiento inválido. Debe ser entrada, salida o ajuste.');
        }
        if (empty($data['filas']) || !is_array($data['filas'])) {
            throw new \InvalidArgumentException('La carga no contiene líneas para procesar.');
        }
    }

    /**
     * Valida la estructura básica de una línea (sin tocar BD).
     * Devuelve el mensaje de error o null si es válida a nivel estructural.
     */
    public function validarEstructuraLinea(array $fila): ?string
    {
        $cant = isset($fila['cantidad']) ? (float) $fila['cantidad'] : 0.0;
        if (empty($fila['id_producto'])) {
            return 'Falta el producto.';
        }
        if (empty($fila['id_bodega'])) {
            return 'Falta la bodega.';
        }
        if ($cant <= 0) {
            return 'La cantidad debe ser mayor a cero.';
        }
        return null;
    }
}
