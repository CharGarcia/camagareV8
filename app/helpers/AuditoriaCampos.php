<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Traduce el detalle de auditoría (log_sistema) al lenguaje del usuario.
 *
 * Resuelve tres problemas del detalle crudo que hacían ilegible el historial:
 *   1. Nombres técnicos de columna ("id_tipo_medida") → etiqueta legible
 *      ("Tipo de medida").
 *   2. Valores que son códigos internos ("01", "t") → texto con significado
 *      ("Producto", "Activo"). Los IDs de catálogo los resuelve contra la BD
 *      App\Services\LogSistemaService, no este helper (aquí no hay PDO).
 *   3. Falsos positivos: el mismo valor guardado con otro formato
 *      ({"venta":true,"compra":true} vs {"compra":true,"venta":true}, 't' vs true,
 *      '12.00' vs '12') se reportaba como si el usuario hubiera cambiado algo.
 *
 * Lo consume App\Services\LogSistemaService, así que la mejora llega por igual al
 * historial de cada módulo, a la consulta de auditoría (config/log-sistema) y a la
 * trazabilidad de productos.
 */
class AuditoriaCampos
{
    /** Etiqueta legible por nombre de columna (vale para cualquier tabla). */
    private const ETIQUETAS = [
        // Identificación
        'codigo'             => 'Código',
        'codigo_auxiliar'    => 'Código auxiliar',
        'codigo_barras'      => 'Código de barras',
        'codigo_principal'   => 'Código principal',
        'nombre'             => 'Nombre',
        'nombre_comercial'   => 'Nombre comercial',
        'razon_social'       => 'Razón social',
        'descripcion'        => 'Descripción',
        'observaciones'      => 'Observaciones',
        'identificacion'     => 'Identificación',
        'ruc'                => 'RUC',
        'telefono'           => 'Teléfono',
        'celular'            => 'Celular',
        'email'              => 'Correo electrónico',
        'direccion'          => 'Dirección',

        // Producto
        'tipo_produccion'    => 'Tipo de producto',
        'precio_base'        => 'Precio base',
        'costo_producto'     => 'Costo',
        'tarifa_iva'         => 'Tarifa de IVA',
        'id_medida'          => 'Unidad de medida',
        'id_tipo_medida'     => 'Tipo de medida',
        'id_categoria'       => 'Categoría',
        'id_marca'           => 'Marca',
        'id_ice'             => 'ICE',
        'valor_ice'          => 'Valor del ICE',
        'codigo_ice'         => 'Código del ICE',
        'nombre_ice'         => 'Nombre del ICE',
        'inventariable'      => 'Maneja inventario',
        'stock_minimo'       => 'Stock mínimo',
        'stock_maximo'       => 'Stock máximo',
        'opciones'           => 'Se puede usar en',
        'imagen'             => 'Imagen',
        'componentes'        => 'Componentes',
        'variantes'          => 'Variantes',

        // Estado y control
        'status'             => 'Estado',
        'estado'             => 'Estado',
        'estado_sri'         => 'Estado en el SRI',
        'tipo_ambiente'      => 'Ambiente',
        'activo'             => 'Activo',
        'casilleros_sri'     => 'Casilleros SRI',

        // Documentos
        'fecha'              => 'Fecha',
        'fecha_emision'      => 'Fecha de emisión',
        'fecha_vencimiento'  => 'Fecha de vencimiento',
        'fecha_caducidad'    => 'Fecha de caducidad',
        'establecimiento'    => 'Establecimiento',
        'punto_emision'      => 'Punto de emisión',
        'secuencial'         => 'Secuencial',
        'subtotal'           => 'Subtotal',
        'total'              => 'Total',
        'total_iva'          => 'IVA',
        'descuento'          => 'Descuento',
        'id_cliente'         => 'Cliente',
        'id_proveedor'       => 'Proveedor',
        'id_producto'        => 'Producto',
        'id_bodega'          => 'Bodega',
        'id_vendedor'        => 'Vendedor',
        'id_empleado'        => 'Empleado',
        'cantidad'           => 'Cantidad',
        'precio_unitario'    => 'Precio unitario',
        'costo_unitario'     => 'Costo unitario',
        'numero_lote'        => 'Lote',
        'nup'                => 'NUP',
    ];

    /** Códigos internos → texto con significado, por columna. */
    private const ENUMS = [
        'tipo_produccion' => ['01' => 'Producto', '02' => 'Servicio'],
        'tipo_ambiente'   => ['1' => 'Pruebas', '2' => 'Producción'],
        'tipo_movimiento' => ['entrada' => 'Entrada', 'salida' => 'Salida', 'ajuste' => 'Ajuste'],
    ];

    /** Columnas booleanas con un par de etiquetas propio: [valor true, valor false]. */
    private const BOOLEANOS = [
        'status'        => ['Activo', 'Inactivo'],
        'activo'        => ['Activo', 'Inactivo'],
        'inventariable' => ['Sí', 'No'],
        'es_base'       => ['Es la unidad base', 'No es la unidad base'],
    ];

    /** Columnas cuyo valor es una ruta de archivo: la ruta no le dice nada al usuario. */
    private const ARCHIVOS = ['imagen', 'logo', 'archivo', 'ruta_archivo', 'firma'];

    /** Claves conocidas dentro de columnas JSON de opciones. */
    private const OPCIONES = [
        'compra'      => 'Compras',
        'venta'       => 'Ventas',
        'produccion'  => 'Producción',
        'consignacion' => 'Consignaciones',
    ];

    /** Nombre legible de una columna. */
    public static function etiqueta(string $campo): string
    {
        if (isset(self::ETIQUETAS[$campo])) {
            return self::ETIQUETAS[$campo];
        }

        // Fallback: quitar el prefijo id_ (solo al inicio) y separar palabras.
        $label = preg_replace('/^id_/', '', $campo) ?? $campo;
        $label = preg_replace('/_id$/', '', $label) ?? $label;
        $label = trim(str_replace('_', ' ', $label));

        return $label === '' ? $campo : mb_strtoupper(mb_substr($label, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($label, 1, null, 'UTF-8');
    }

    /**
     * Texto con significado para un valor, cuando la columna lo permite.
     * Devuelve null si no hay traducción especial (el llamador decide qué mostrar).
     */
    public static function valorLegible(string $campo, $valor): ?string
    {
        if ($campo === 'opciones') {
            return self::opciones($valor);
        }

        if (in_array($campo, self::ARCHIVOS, true)) {
            return ($valor === null || $valor === '') ? 'Sin archivo' : 'Archivo cargado';
        }

        if (isset(self::BOOLEANOS[$campo])) {
            $bool = self::aBooleano($valor);
            if ($bool !== null) {
                return $bool ? self::BOOLEANOS[$campo][0] : self::BOOLEANOS[$campo][1];
            }
        }

        if (isset(self::ENUMS[$campo]) && ($valor !== null && $valor !== '')) {
            $clave = is_string($valor) ? $valor : (string) $valor;
            if (isset(self::ENUMS[$campo][$clave])) {
                return self::ENUMS[$campo][$clave];
            }
        }

        return null;
    }

    /**
     * ¿El valor anterior y el nuevo son el mismo dato?
     *
     * Va más allá de la comparación literal a propósito: el mismo valor puede venir
     * de la BD y del formulario con distinta forma (booleano 't' de PostgreSQL vs
     * true de PHP, JSON con las claves en otro orden, '12.00' vs '12'). Sin esto el
     * historial listaba cambios que el usuario nunca hizo.
     */
    public static function iguales($a, $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $aVacio = ($a === null || $a === '');
        $bVacio = ($b === null || $b === '');
        if ($aVacio && $bVacio) {
            return true;
        }
        if ($aVacio !== $bVacio) {
            return $a == $b; // mantiene el criterio histórico (null equivale a 0)
        }

        $boolA = self::aBooleano($a);
        $boolB = self::aBooleano($b);
        if ($boolA !== null && $boolB !== null) {
            return $boolA === $boolB;
        }

        $jsonA = self::canonicoJson($a);
        $jsonB = self::canonicoJson($b);
        if ($jsonA !== null && $jsonB !== null) {
            return $jsonA === $jsonB;
        }
        if ($jsonA !== null || $jsonB !== null) {
            return false; // uno es estructura y el otro no
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return $a == $b;
    }

    /** Lista legible de una columna JSON de opciones: {"compra":true,"venta":true} → "Compras, Ventas". */
    private static function opciones($valor): string
    {
        $data = is_array($valor) ? $valor : (is_string($valor) ? json_decode($valor, true) : null);
        if (!is_array($data)) {
            return ($valor === null || $valor === '') ? '-' : (string) $valor;
        }

        // Orden estable (el del catálogo), para que dos guardados iguales se lean igual.
        $activas = [];
        foreach (self::OPCIONES as $clave => $etiqueta) {
            if (isset($data[$clave]) && self::aBooleano($data[$clave]) === true) {
                $activas[] = $etiqueta;
            }
        }
        foreach ($data as $clave => $activa) {
            if (!isset(self::OPCIONES[$clave]) && self::aBooleano($activa) === true) {
                $activas[] = self::etiqueta((string) $clave);
            }
        }

        return empty($activas) ? 'Ninguna operación' : implode(', ', $activas);
    }

    /** Representación canónica de un JSON/arreglo: claves ordenadas, sin espacios. */
    private static function canonicoJson($valor): ?string
    {
        if (is_array($valor)) {
            $data = $valor;
        } elseif (is_string($valor) && preg_match('/^\s*[\[{]/', $valor)) {
            $data = json_decode($valor, true);
        } else {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        self::ordenarClaves($data);
        return json_encode($data) ?: null;
    }

    private static function ordenarClaves(array &$data): void
    {
        foreach ($data as &$valor) {
            if (is_array($valor)) {
                self::ordenarClaves($valor);
            }
        }
        unset($valor);

        if (array_keys($data) !== range(0, count($data) - 1)) {
            ksort($data);
        }
    }

    /** Interpreta booleanos venidos de PostgreSQL ('t'/'f'), de formularios ('1'/'0') o de PHP. */
    private static function aBooleano($valor): ?bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) && ($valor === 0 || $valor === 1)) {
            return (bool) $valor;
        }
        if (is_string($valor)) {
            $v = mb_strtolower(trim($valor), 'UTF-8');
            if (in_array($v, ['t', 'true', '1'], true)) {
                return true;
            }
            if (in_array($v, ['f', 'false', '0'], true)) {
                return false;
            }
        }

        return null;
    }
}
