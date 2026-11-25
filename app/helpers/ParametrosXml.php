<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Lectura de config/parametros.xml (no versionado, uno por servidor) para credenciales opcionales
 * de integraciones (MySQL del sistema anterior, FTP de documentos, etc.).
 *
 * Diferencia los tres motivos por los que una credencial "no está", para que el mensaje al usuario
 * diga qué corregir: el archivo no existe, el XML está mal formado (típico: una clave con `&` o `<`
 * sin escapar) o la etiqueta falta / está vacía. Nunca devuelve ni muestra los valores en los mensajes.
 */
final class ParametrosXml
{
    /** @var array{xml: ?\SimpleXMLElement, problema: ?string}|null */
    private static ?array $cache = null;

    public static function ruta(): string
    {
        return (defined('MVC_CONFIG') ? MVC_CONFIG : dirname(__DIR__, 2) . '/config') . '/parametros.xml';
    }

    /**
     * Carga el archivo una vez por petición.
     *
     * @return array{xml: ?\SimpleXMLElement, problema: ?string} `problema` es null si el XML se leyó bien.
     */
    public static function cargar(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $file = self::ruta();
        if (!is_file($file)) {
            return self::$cache = ['xml' => null, 'problema' => 'No existe config/parametros.xml en el servidor.'];
        }

        $previo = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml = simplexml_load_file($file);
        $errores = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if ($xml === false) {
            $detalle = '';
            if (!empty($errores)) {
                $e = $errores[0];
                $detalle = ' (línea ' . (int) $e->line . ': ' . trim((string) $e->message) . ')';
            }
            return self::$cache = [
                'xml'      => null,
                'problema' => 'config/parametros.xml del servidor tiene un error de formato' . $detalle . '. '
                    . 'Si una clave o usuario lleva los caracteres & o <, escríbalos como &amp; y &lt;.',
            ];
        }
        return self::$cache = ['xml' => $xml, 'problema' => null];
    }

    /** Valor de una etiqueta de primer nivel; '' si el archivo no se pudo leer o la etiqueta falta/está vacía. */
    public static function valor(string $etiqueta): string
    {
        $xml = self::cargar()['xml'];
        if ($xml === null || !isset($xml->{$etiqueta})) {
            return '';
        }
        return trim((string) $xml->{$etiqueta});
    }

    /**
     * Mensaje para credenciales obligatorias que no se pudieron obtener, o null si todas tienen valor.
     *
     * @param string   $que         Con su preposición, p. ej. "de la BD anterior (MySQL)" o "del FTP de documentos".
     * @param string[] $requeridas  Etiquetas obligatorias (usuario, clave).
     * @param string[] $opcionales  Etiquetas con valor por defecto, solo para la ayuda.
     */
    public static function problemaCredenciales(string $que, array $requeridas, array $opcionales = []): ?string
    {
        $carga = self::cargar();
        if ($carga['problema'] !== null) {
            return 'No se pudieron leer las credenciales ' . $que . ': ' . $carga['problema'];
        }
        $faltan = array_values(array_filter($requeridas, static fn(string $e): bool => self::valor($e) === ''));
        if (empty($faltan)) {
            return null;
        }
        $tags = implode(' y ', array_map(static fn(string $e): string => '<' . $e . '>', $faltan));
        $msg = 'Faltan las credenciales ' . $que . '. En config/parametros.xml del servidor falta o está vacío: ' . $tags . '.';
        if (!empty($opcionales)) {
            $msg .= ' (Opcionales, si cambian: '
                . implode(', ', array_map(static fn(string $e): string => '<' . $e . '>', $opcionales)) . '.)';
        }
        return $msg;
    }
}
