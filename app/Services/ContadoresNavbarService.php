<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Cache;
use App\repositories\ContadoresNavbarRepository;
use App\repositories\TareaRepository;

/**
 * Orquesta los contadores del navbar: consulta (una sola), caché en APCu y
 * filtrado por permisos de módulo.
 *
 * Estrategia de caché (para que la BD quede casi en reposo bajo polling):
 *   - Contadores por empresa: clave por empresa, compartida por todos sus usuarios.
 *     Se invalida por evento cuando se escribe en una tabla relevante (ver LogSistemaService).
 *   - Permisos del navbar: clave por empresa+usuario (cambian poco → TTL más largo).
 *   - Tareas: global por usuario.
 * Si APCu no está disponible, todo se calcula en vivo (sin caché) sin romperse.
 */
class ContadoresNavbarService
{
    /** TTL de respaldo de los contadores (segundos). La invalidación por evento da frescura inmediata. */
    private const TTL_CONTADORES = 30;
    /** TTL de los permisos evaluados (cambian rara vez). */
    private const TTL_PERMISOS = 60;

    /**
     * Contador => ruta MVC del módulo (para checar permiso 'ver').
     * 'tareas_alertas' NO está aquí: es global por usuario (solo requiere sesión).
     */
    public const RUTAS_MODULO = [
        'facturas_borrador'            => 'modulos/factura-venta',
        'liquidaciones_borrador'       => 'modulos/liquidacion-compra',
        'retenciones_compras_borrador' => 'modulos/retenciones_compras',
        'notas_credito_borrador'       => 'modulos/notas_credito',
        'guias_remision_borrador'      => 'modulos/guias_remision',
        'ordenes_compra_borrador'      => 'modulos/ordenes-compra',
        'pedidos_pendientes'           => 'modulos/pedidos',
        'factura_express_pendientes'   => 'modulos/factura-express-solicitudes',
        'whatsapp_unread'              => 'modulos/whatsapp-chat',
    ];

    /** Tablas que, al cambiar, invalidan la caché de contadores de una empresa. */
    private const TABLAS_INVALIDAN = [
        'ventas_cabecera',
        'liquidaciones_cabecera',
        'retencion_compra_cabecera',
        'notas_credito_cabecera',
        'guias_remision_cabecera',
        'ordenes_compra',
        'pedidos_cabecera',
        'factura_express_solicitudes',
        'whatsapp_chats',
    ];

    private ContadoresNavbarRepository $repo;

    public function __construct()
    {
        $this->repo = new ContadoresNavbarRepository();
    }

    private static function claveEmpresa(int $idEmpresa): string
    {
        return 'cmg_conteos_emp_' . $idEmpresa;
    }

    private static function claveTareas(int $idUsuario): string
    {
        return 'cmg_conteos_tareas_' . $idUsuario;
    }

    private static function clavePermisos(int $idEmpresa, int $idUsuario): string
    {
        return 'cmg_conteos_perm_' . $idEmpresa . '_' . $idUsuario;
    }

    /**
     * Invalida la caché de contadores de una empresa si la tabla afectada es relevante.
     * Se llama desde LogSistemaService::registrar() en cada acción auditada.
     */
    public static function invalidarPorTabla(?string $tabla, ?int $idEmpresa): void
    {
        if ($idEmpresa === null || $idEmpresa <= 0 || $tabla === null) {
            return;
        }
        if (in_array($tabla, self::TABLAS_INVALIDAN, true)) {
            Cache::delete(self::claveEmpresa($idEmpresa));
        }
    }

    /** Contadores por empresa (con caché compartida por empresa). @return array<string,int> */
    private function contadoresEmpresa(int $idEmpresa): array
    {
        $cache = Cache::get(self::claveEmpresa($idEmpresa));
        if (is_array($cache)) {
            return $cache;
        }
        $datos = $this->repo->getConteosEmpresa($idEmpresa);
        Cache::set(self::claveEmpresa($idEmpresa), $datos, self::TTL_CONTADORES);
        return $datos;
    }

    /** Contador de tareas (global por usuario, con caché). */
    private function contadorTareas(int $idUsuario): int
    {
        $cache = Cache::get(self::claveTareas($idUsuario));
        if (is_int($cache)) {
            return $cache;
        }
        $count = (new TareaRepository())->getAlertaTareasCount($idUsuario);
        Cache::set(self::claveTareas($idUsuario), $count, self::TTL_CONTADORES);
        return $count;
    }

    /**
     * Permisos 'ver' del navbar por módulo (con caché por empresa+usuario).
     * @param callable(string):bool $puedeVer  Recibe la ruta MVC y responde si tiene permiso 'ver'.
     * @return array<string,bool>
     */
    private function permisosNavbar(int $idEmpresa, int $idUsuario, callable $puedeVer): array
    {
        $cache = Cache::get(self::clavePermisos($idEmpresa, $idUsuario));
        if (is_array($cache)) {
            return $cache;
        }
        $perms = [];
        foreach (self::RUTAS_MODULO as $clave => $ruta) {
            $perms[$clave] = $puedeVer($ruta) === true;
        }
        Cache::set(self::clavePermisos($idEmpresa, $idUsuario), $perms, self::TTL_PERMISOS);
        return $perms;
    }

    /**
     * Contadores que el usuario puede ver, listos para el navbar.
     *
     * @param callable(string):bool $puedeVer  Evalúa permiso 'ver' por ruta MVC.
     * @return array<string,int>
     */
    public function getContadores(int $idEmpresa, int $idUsuario, callable $puedeVer): array
    {
        $out = [];

        if ($idEmpresa > 0) {
            $empresa = $this->contadoresEmpresa($idEmpresa);
            $perms   = $this->permisosNavbar($idEmpresa, $idUsuario, $puedeVer);
            foreach (self::RUTAS_MODULO as $clave => $ruta) {
                if (empty($perms[$clave])) {
                    continue;
                }
                $out[$clave] = (int) ($empresa[$clave] ?? 0);
            }
        }

        // Tareas: global por usuario, siempre incluido (solo requiere sesión).
        $out['tareas_alertas'] = $this->contadorTareas($idUsuario);

        return $out;
    }
}
