<?php
/**
 * Lectura del mapa config/contabilidad_modulos.php.
 *
 * Traduce entre los tres identificadores que se cruzan en la generación
 * automática de asientos:
 *
 *   ruta MVC        'modulos/factura-venta'   (lo que ve el navegador)
 *   clave de módulo 'facturas_venta'          (el trabajo del sincronizador)
 *   definición      conceptos / referencias / tablas (cómo se sabe si hay
 *                   configuración contable y cómo se firma)
 *
 * Se usa desde la vista (para decidir si disparar), desde el controlador (para
 * validar que la ruta pedida es una de las declaradas) y desde el servicio.
 */

declare(strict_types=1);

namespace App\Helpers;

class ContabilidadModulos
{
    /** @var array<string,array>|null Mapa cargado una sola vez por request. */
    private static ?array $mapa = null;

    /** @var array<string,string[]>|null Índice inverso ruta MVC → claves. */
    private static ?array $porRuta = null;

    /** @return array<string,array> Definiciones por clave de módulo. */
    public static function todos(): array
    {
        if (self::$mapa === null) {
            $archivo = MVC_CONFIG . '/contabilidad_modulos.php';
            $mapa = file_exists($archivo) ? require $archivo : [];
            self::$mapa = is_array($mapa) ? $mapa : [];
        }
        return self::$mapa;
    }

    /** Definición de un trabajo, o null si la clave no está declarada. */
    public static function definicion(string $clave): ?array
    {
        return self::todos()[$clave] ?? null;
    }

    /**
     * Claves de trabajo que dispara una ruta MVC. Array vacío si esa ruta no
     * lleva contabilidad (que es el caso de la gran mayoría de los módulos).
     *
     * @return string[]
     */
    public static function clavesPorRuta(string $rutaMvc): array
    {
        if (self::$porRuta === null) {
            $indice = [];
            foreach (self::todos() as $clave => $def) {
                foreach ((array) ($def['rutas'] ?? []) as $ruta) {
                    $indice[self::normalizarRuta($ruta)][] = $clave;
                }
            }
            self::$porRuta = $indice;
        }
        return self::$porRuta[self::normalizarRuta($rutaMvc)] ?? [];
    }

    /** ¿Esta ruta MVC genera asientos automáticamente? */
    public static function rutaTieneContabilidad(string $rutaMvc): bool
    {
        return self::clavesPorRuta($rutaMvc) !== [];
    }

    /**
     * Ruta tal como está declarada en el mapa, a partir de una equivalente.
     *
     * Las rutas del sistema mezclan guión y guion bajo, y los permisos se
     * resuelven por coincidencia exacta contra submodulos_menu.ruta: consultar
     * 'modulos/notas-credito' cuando lo registrado es 'modulos/notas_credito'
     * devuelve "sin permiso" para un usuario que sí lo tiene. Por eso todo lo
     * que dependa de la ruta (permisos incluidos) usa esta forma canónica y no
     * la que venga en la petición.
     */
    public static function rutaCanonica(string $rutaMvc): ?string
    {
        $buscada = self::normalizarRuta($rutaMvc);
        foreach (self::todos() as $def) {
            foreach ((array) ($def['rutas'] ?? []) as $ruta) {
                if (self::normalizarRuta($ruta) === $buscada) {
                    return $ruta;
                }
            }
        }
        return null;
    }

    /**
     * Ruta MVC del módulo a partir de la URL que se está sirviendo, o null si
     * la petición no corresponde a la pantalla principal de un módulo.
     *
     * Solo devuelve algo para /modulos/{nombre} y /modulos/{nombre}/index: en
     * cualquier otra acción (guardar, pdf, un ajax) no se dispara nada, porque
     * el disparo va en la carga de la pantalla, no en cada petición.
     */
    public static function rutaDesdeUri(?string $requestUri): ?string
    {
        if ($requestUri === null || $requestUri === '') {
            return null;
        }

        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $base = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $partes = array_values(array_filter(explode('/', trim($path, '/')), static fn($p) => $p !== ''));
        if (count($partes) < 2 || strtolower($partes[0]) !== 'modulos') {
            return null;
        }
        // Solo /modulos/{nombre} y /modulos/{nombre}/index. Cualquier otra
        // acción (guardar, pdf, un ajax) no dispara nada.
        $esPantallaPrincipal = count($partes) === 2
            || (count($partes) === 3 && strtolower($partes[2]) === 'index');
        if (!$esPantallaPrincipal) {
            return null;
        }

        return 'modulos/' . strtolower($partes[1]);
    }

    /**
     * Las rutas MVC del sistema mezclan guión y guion bajo por razones
     * históricas ('modulos/notas_credito' vs 'modulos/factura-venta'), así que
     * el índice se arma con una forma normalizada para que ambas escrituras
     * resuelvan al mismo trabajo.
     */
    private static function normalizarRuta(string $ruta): string
    {
        return str_replace('_', '-', strtolower(trim($ruta, '/')));
    }
}
