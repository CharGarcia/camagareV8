<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Búsqueda sobre el catálogo `retenciones_sri` (códigos de retención del SRI).
 *
 * Fuente única de la búsqueda del catálogo, para que la pantalla de configuración
 * (`/config/retenciones-sri`) y los selectores de código de los modales de
 * Retenciones de Compras y de Ventas encuentren siempre lo mismo con el mismo
 * texto escrito.
 *
 * Regla: se busca en TODAS las columnas que el usuario ve (código, concepto,
 * porcentaje, impuesto, código del anexo y —en el listado— estado y vigencia).
 * Las columnas se castean a `text` porque varias son numéricas o de fecha y
 * PostgreSQL no acepta ILIKE sobre ellas.
 */
class BusquedaRetencionSri
{
    /** Columnas del selector de códigos de los modales (lo que muestra el dropdown). */
    public const COLUMNAS_CATALOGO = [
        'codigo_ret::text',
        'concepto_ret::text',
        'porcentaje_ret::text',
        'impuesto_ret::text',
        "COALESCE(cod_anexo_ret, '')::text",
    ];

    /** Columnas del listado de configuración: las del catálogo más estado y vigencia. */
    public const COLUMNAS_LISTADO = [
        'codigo_ret::text',
        'concepto_ret::text',
        'porcentaje_ret::text',
        'impuesto_ret::text',
        "COALESCE(cod_anexo_ret, '')::text",
        "CASE WHEN COALESCE(status::text, '0') IN ('1', 't', 'true') THEN 'Activo' ELSE 'Inactivo' END",
        "COALESCE(desde::text, '')",
        "COALESCE(hasta::text, '')",
    ];

    /**
     * Normaliza el texto escrito por el usuario para que coincida con lo que ve en
     * pantalla:
     *  - quita el signo "%" y usa punto decimal ("1,75 %" → "1.75");
     *  - traduce las fechas d-m-Y o d/m/Y al formato que guarda la base (Y-m-d),
     *    porque el listado las muestra como dd-mm-aaaa.
     */
    public static function normalizar(string $texto): string
    {
        $texto = str_replace('%', ' ', trim($texto));
        $texto = preg_replace('/(?<=\d),(?=\d)/', '.', $texto) ?? $texto;
        $texto = preg_replace_callback(
            '#\b(\d{1,2})[-/](\d{1,2})[-/](\d{4})\b#',
            static fn($m) => sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]),
            $texto
        ) ?? $texto;

        return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }

    /**
     * Condición SQL de búsqueda en todas las columnas indicadas.
     *
     * @param string[] $columnas Una de las constantes COLUMNAS_* de esta clase.
     * @param array    $params   Parámetros PDO (por referencia).
     * @return string Fragmento SQL entre paréntesis; '' si no hay texto que buscar.
     */
    public static function condicion(array $columnas, string $texto, array &$params, string $prefijo = 'rsri'): string
    {
        return FiltrosBusqueda::condicionTexto($columnas, self::normalizar($texto), $params, $prefijo);
    }

    /**
     * Condición SQL cuando la búsqueda se dispara desde la columna "% Ret." de un
     * modal: ahí el usuario escribe un porcentaje, así que se busca solo por
     * porcentaje (valor exacto o que empiece por lo escrito) en lugar de por texto
     * libre en todas las columnas — de otro modo teclear "2" devolvería también
     * todos los códigos y conceptos que contienen un 2.
     *
     * Si lo escrito no es un número, se cae a la búsqueda normal por todas las columnas.
     *
     * @param array $params Parámetros PDO (por referencia).
     * @return string Fragmento SQL entre paréntesis; '' si no hay texto que buscar.
     */
    public static function condicionPorcentaje(string $texto, array &$params, string $prefijo = 'rpct'): string
    {
        $valor = self::normalizar($texto);
        if ($valor === '') {
            return '';
        }
        if (!is_numeric($valor)) {
            return self::condicion(self::COLUMNAS_CATALOGO, $texto, $params, $prefijo);
        }

        $params[":{$prefijo}_num"]  = $valor;
        $params[":{$prefijo}_pref"] = $valor . '%';

        return "(porcentaje_ret = CAST(:{$prefijo}_num AS numeric) OR porcentaje_ret::text LIKE :{$prefijo}_pref)";
    }
}
