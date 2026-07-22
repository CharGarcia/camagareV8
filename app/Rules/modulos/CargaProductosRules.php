<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Services\modulos\CargaProductosEsquema;

/**
 * Validaciones de negocio para la carga masiva de productos/servicios.
 *
 * Solo reglas puras (obligatorios, longitudes, formatos, coherencia entre
 * campos). La resolución de catálogos contra la base de datos la hace el
 * Service, que es quien tiene los mapas precargados.
 *
 * Cada método devuelve un array de mensajes de error; vacío significa válido.
 */
class CargaProductosRules
{
    /** Longitudes máximas, alineadas con ProductoRules y con el esquema de la BD. */
    public const MAX_CODIGO         = 50;
    public const MAX_NOMBRE         = 200;
    public const MAX_CODIGO_AUX     = 100;
    public const MAX_CODIGO_BARRAS  = 100;
    public const MAX_NOMBRE_PRECIO  = 100;
    public const MAX_VARIANTE_NOMBRE = 100;
    public const MAX_VARIANTE_VALOR  = 200;
    public const MAX_CODIGO_PROVEEDOR = 100;

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Productos
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $f Fila normalizada de la hoja Productos.
     * @return string[] Errores encontrados.
     */
    public function validarProducto(array $f): array
    {
        $e = [];

        if ($f['codigo'] === '') {
            $e[] = 'CODIGO es obligatorio.';
        } elseif (mb_strlen($f['codigo']) > self::MAX_CODIGO) {
            $e[] = 'CODIGO no puede exceder ' . self::MAX_CODIGO . ' caracteres.';
        }

        if ($f['nombre'] === '') {
            $e[] = 'NOMBRE es obligatorio.';
        } elseif (mb_strlen($f['nombre']) > self::MAX_NOMBRE) {
            $e[] = 'NOMBRE no puede exceder ' . self::MAX_NOMBRE . ' caracteres.';
        }

        if ($f['tipo_produccion'] === null) {
            $e[] = 'TIPO debe ser "Producto" o "Servicio".';
        }

        if (mb_strlen($f['codigo_auxiliar']) > self::MAX_CODIGO_AUX) {
            $e[] = 'CODIGO_AUXILIAR no puede exceder ' . self::MAX_CODIGO_AUX . ' caracteres.';
        }
        if (mb_strlen($f['codigo_barras']) > self::MAX_CODIGO_BARRAS) {
            $e[] = 'CODIGO_BARRAS no puede exceder ' . self::MAX_CODIGO_BARRAS . ' caracteres.';
        }

        if ($f['precio_base'] === null) {
            $e[] = 'PRECIO_BASE debe ser un número.';
        } elseif ($f['precio_base'] < 0) {
            $e[] = 'PRECIO_BASE no puede ser negativo.';
        }

        if ($f['costo_producto'] === null) {
            $e[] = 'COSTO debe ser un número.';
        } elseif ($f['costo_producto'] < 0) {
            $e[] = 'COSTO no puede ser negativo.';
        }

        if ($f['estado'] === null) {
            $e[] = 'ESTADO debe ser "Activo" o "Inactivo".';
        }
        if ($f['inventariable'] === null) {
            $e[] = 'INVENTARIABLE debe ser "Si" o "No".';
        }
        if ($f['aplica_compra'] === null) {
            $e[] = 'APLICA_COMPRA debe ser "Si" o "No".';
        }
        if ($f['aplica_venta'] === null) {
            $e[] = 'APLICA_VENTA debe ser "Si" o "No".';
        }

        if ($f['aplica_compra'] === false && $f['aplica_venta'] === false) {
            $e[] = 'El producto debe aplicar al menos a compras o a ventas.';
        }

        foreach (['stock_minimo' => 'STOCK_MINIMO', 'stock_maximo' => 'STOCK_MAXIMO'] as $k => $label) {
            if ($f[$k] === null) {
                $e[] = "{$label} debe ser un número.";
            } elseif ($f[$k] < 0) {
                $e[] = "{$label} no puede ser negativo.";
            }
        }

        if ($f['stock_minimo'] !== null && $f['stock_maximo'] !== null
            && $f['stock_maximo'] > 0 && $f['stock_minimo'] > $f['stock_maximo']) {
            $e[] = 'STOCK_MINIMO no puede ser mayor que STOCK_MAXIMO.';
        }

        // Un servicio nunca maneja inventario (misma regla que ProductoService).
        if ($f['tipo_produccion'] === CargaProductosEsquema::TIPO_SERVICIO) {
            if ($f['inventariable'] === true) {
                $e[] = 'Un Servicio no puede ser inventariable.';
            }
            if ($f['codigo_medida'] !== '') {
                $e[] = 'Un Servicio no lleva unidad de medida (CODIGO_MEDIDA debe ir vacío).';
            }
        }

        return $e;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas hijas
    // ─────────────────────────────────────────────────────────────────────────

    /** @return string[] */
    public function validarPrecio(array $f): array
    {
        $e = [];

        if ($f['nombre_precio'] === '') {
            $e[] = 'NOMBRE_PRECIO es obligatorio.';
        } elseif (mb_strlen($f['nombre_precio']) > self::MAX_NOMBRE_PRECIO) {
            $e[] = 'NOMBRE_PRECIO no puede exceder ' . self::MAX_NOMBRE_PRECIO . ' caracteres.';
        }

        if ($f['precio'] === null) {
            $e[] = 'PRECIO debe ser un número.';
        } elseif ($f['precio'] < 0) {
            $e[] = 'PRECIO no puede ser negativo.';
        }

        if ($f['valido_desde'] === false) {
            $e[] = 'DESDE no tiene un formato de fecha válido (use AAAA-MM-DD).';
        }
        if ($f['valido_hasta'] === false) {
            $e[] = 'HASTA no tiene un formato de fecha válido (use AAAA-MM-DD).';
        }

        if (!empty($f['valido_desde']) && !empty($f['valido_hasta'])
            && $f['valido_desde'] > $f['valido_hasta']) {
            $e[] = 'DESDE no puede ser posterior a HASTA.';
        }

        if ($f['estado'] === null) {
            $e[] = 'ACTIVO debe ser "Si" o "No".';
        }

        return $e;
    }

    /** @return string[] */
    public function validarVariante(array $f): array
    {
        $e = [];

        if ($f['nombre'] === '') {
            $e[] = 'NOMBRE de la variante es obligatorio.';
        } elseif (mb_strlen($f['nombre']) > self::MAX_VARIANTE_NOMBRE) {
            $e[] = 'NOMBRE no puede exceder ' . self::MAX_VARIANTE_NOMBRE . ' caracteres.';
        }

        if ($f['valor'] === '') {
            $e[] = 'VALOR de la variante es obligatorio.';
        } elseif (mb_strlen($f['valor']) > self::MAX_VARIANTE_VALOR) {
            $e[] = 'VALOR no puede exceder ' . self::MAX_VARIANTE_VALOR . ' caracteres.';
        }

        if ($f['precio_adicional'] === null) {
            $e[] = 'PRECIO_ADICIONAL debe ser un número.';
        }

        return $e;
    }

    /** @return string[] */
    public function validarComponente(array $f): array
    {
        $e = [];

        if ($f['codigo_hijo'] === '') {
            $e[] = 'CODIGO_HIJO es obligatorio.';
        }

        if ($f['cantidad'] === null) {
            $e[] = 'CANTIDAD debe ser un número.';
        } elseif ($f['cantidad'] <= 0) {
            $e[] = 'CANTIDAD debe ser mayor que cero.';
        }

        if ($f['codigo_padre'] !== '' && $f['codigo_hijo'] !== ''
            && mb_strtolower($f['codigo_padre']) === mb_strtolower($f['codigo_hijo'])) {
            $e[] = 'Un producto no puede ser componente de sí mismo.';
        }

        return $e;
    }

    /** @return string[] */
    public function validarStockBodega(array $f): array
    {
        $e = [];

        if ($f['bodega'] === '') {
            $e[] = 'BODEGA es obligatoria.';
        }

        foreach (['stock_minimo' => 'STOCK_MINIMO', 'stock_maximo' => 'STOCK_MAXIMO'] as $k => $label) {
            if ($f[$k] === null) {
                $e[] = "{$label} debe ser un número.";
            } elseif ($f[$k] < 0) {
                $e[] = "{$label} no puede ser negativo.";
            }
        }

        if ($f['stock_minimo'] !== null && $f['stock_maximo'] !== null
            && $f['stock_maximo'] > 0 && $f['stock_minimo'] > $f['stock_maximo']) {
            $e[] = 'STOCK_MINIMO no puede ser mayor que STOCK_MAXIMO.';
        }

        return $e;
    }

    /** @return string[] */
    public function validarHomologacion(array $f): array
    {
        $e = [];

        if ($f['ruc_proveedor'] === '') {
            $e[] = 'RUC_CEDULA_PROVEEDOR es obligatorio.';
        }

        if ($f['codigo_proveedor'] === '') {
            $e[] = 'CODIGO_PROVEEDOR es obligatorio.';
        } elseif (mb_strlen($f['codigo_proveedor']) > self::MAX_CODIGO_PROVEEDOR) {
            $e[] = 'CODIGO_PROVEEDOR no puede exceder ' . self::MAX_CODIGO_PROVEEDOR . ' caracteres.';
        }

        return $e;
    }

    /**
     * Reglas que solo se pueden evaluar sabiendo el tipo del producto padre.
     * Un servicio no admite componentes, variantes ni stock por bodega.
     *
     * @return string[]
     */
    public function validarSeccionSegunTipo(string $hoja, string $tipoProduccion): array
    {
        if ($tipoProduccion !== CargaProductosEsquema::TIPO_SERVICIO) {
            return [];
        }

        return match ($hoja) {
            CargaProductosEsquema::HOJA_COMPONENTES =>
                ['Un Servicio no puede tener componentes.'],
            CargaProductosEsquema::HOJA_VARIANTES =>
                ['Un Servicio no puede tener variantes.'],
            CargaProductosEsquema::HOJA_STOCK_BODEGAS =>
                ['Un Servicio no maneja stock por bodega.'],
            default => [],
        };
    }
}
