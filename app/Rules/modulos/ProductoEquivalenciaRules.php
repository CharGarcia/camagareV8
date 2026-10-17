<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de la lista de productos equivalentes de un producto.
 */
class ProductoEquivalenciaRules
{
    /** Tope de equivalentes por grupo: evita listas pegadas por error (un "agrupar todo"). */
    public const MAX_EQUIVALENTES = 50;

    /**
     * @param int   $idProducto  Producto de la ficha.
     * @param int[] $ids         Equivalentes pedidos (ya sin repetidos ni el propio).
     * @param array $encontrados Productos de la empresa por id (getProductosPorIds()).
     */
    public function validar(int $idProducto, array $ids, array $encontrados): void
    {
        if (count($ids) > self::MAX_EQUIVALENTES) {
            throw new Exception('Un producto puede tener como máximo ' . self::MAX_EQUIVALENTES . ' equivalentes.');
        }
        foreach ($ids as $id) {
            if ($id === $idProducto) {
                throw new Exception('Un producto no puede ser equivalente de sí mismo.');
            }
            if (!isset($encontrados[$id])) {
                throw new Exception('Uno de los productos equivalentes ya no existe. Vuelva a abrir la ficha e intente de nuevo.');
            }
            if ($encontrados[$id]['tipo_produccion'] === '02') {
                throw new Exception('Los servicios no pueden ser productos equivalentes.');
            }
        }
    }
}
