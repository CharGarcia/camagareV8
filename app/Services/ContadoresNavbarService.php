<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Cache;
use App\models\PermisoSubmodulo;
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

    /**
     * Tipo de novedad SRI => ruta MVC del módulo (para permiso 'ver').
     * Las claves coinciden con las que devuelve ContadoresNavbarRepository::getNovedadesSri().
     */
    public const NOVEDAD_RUTAS = [
        'facturas'            => 'modulos/factura-venta',
        'liquidaciones'       => 'modulos/liquidacion-compra',
        'retenciones_compras' => 'modulos/retenciones_compras',
        'notas_credito'       => 'modulos/notas_credito',
        'guias_remision'      => 'modulos/guias_remision',
    ];

    /** Ruta MVC del módulo de empresa (aviso de suscripción/vigencia del sistema). */
    public const RUTA_EMPRESA = 'modulos/empresa';

    /** Umbral BASE en días para avisar que la suscripción está por vencer (semestral/anual/manual). */
    private const UMBRAL_VIGENCIA_DIAS = 15;

    /** Umbral en días para avisar que la firma electrónica está por caducar (incluye caducadas). */
    private const UMBRAL_FIRMA_DIAS = 5;

    /** Ruta MVC del módulo de suscripciones (aviso para la empresa ADMINISTRADORA). */
    public const RUTA_SUSCRIPCIONES = 'modulos/suscripciones';

    /** Ventana en días para "suscripciones por vencer" (cobros próximos de los clientes). */
    private const DIAS_SUSCRIPCIONES_POR_VENCER = 7;

    /**
     * Umbral de aviso ESCALADO según la periodicidad de la suscripción.
     * Evita que una suscripción mensual mantenga el badge encendido todo el mes
     * (su próximo cobro siempre está a ≤ ~30 días).
     */
    private function umbralVigencia(?int $meses): int
    {
        if ($meses === null) {
            return self::UMBRAL_VIGENCIA_DIAS; // manual / sin periodicidad
        }
        if ($meses <= 1) {
            return 5;   // mensual → avisar solo en los últimos 5 días (o vencida)
        }
        if ($meses <= 3) {
            return 10;  // trimestral
        }
        return self::UMBRAL_VIGENCIA_DIAS; // semestral / anual → 15
    }

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
        'sri_envio_log', // cualquier acción SRI (devuelta/autorizado/…) cambia las novedades
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

    private static function claveSubmodulosNuevos(int $idEmpresa, int $idUsuario): string
    {
        return 'cmg_submod_nuevos_' . $idEmpresa . '_' . $idUsuario;
    }

    /** Invalida el aviso "submódulo nuevo" de un usuario+empresa puntual. */
    public static function invalidarSubmodulosNuevos(int $idUsuario, int $idEmpresa): void
    {
        if ($idUsuario <= 0 || $idEmpresa <= 0) {
            return;
        }
        Cache::delete(self::claveSubmodulosNuevos($idEmpresa, $idUsuario));
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

    /** Contadores por empresa (con caché compartida por empresa). @return array<string,mixed> */
    private function contadoresEmpresa(int $idEmpresa): array
    {
        $cache = Cache::get(self::claveEmpresa($idEmpresa));
        if (is_array($cache)) {
            return $cache;
        }
        $datos = $this->repo->getConteosEmpresa($idEmpresa);
        // Novedades SRI: si la tabla/columna no existe en algún ambiente, no debe romper el resto.
        try {
            $datos['__novedad_sri'] = $this->repo->getNovedadesSri($idEmpresa);
        } catch (\Throwable $e) {
            $datos['__novedad_sri'] = [];
        }
        // Vigencia de la suscripción del sistema (null si no aplica / columnas sin migrar).
        try {
            $datos['__vigencia'] = $this->repo->getDiasVigenciaSuscripcion($idEmpresa);
        } catch (\Throwable $e) {
            $datos['__vigencia'] = null;
        }
        // Suscripciones que gestiona la empresa activa como ADMINISTRADORA (0/0 si no gestiona).
        try {
            $datos['__suscripciones'] = $this->repo->getAlertaSuscripciones($idEmpresa, self::DIAS_SUSCRIPCIONES_POR_VENCER);
        } catch (\Throwable $e) {
            $datos['__suscripciones'] = null;
        }
        // Estado de la firma electrónica (['sin_firma'=>true] | ['dias'=>int] | null).
        try {
            $datos['__firma'] = $this->repo->getEstadoFirma($idEmpresa);
        } catch (\Throwable $e) {
            $datos['__firma'] = null;
        }
        Cache::set(self::claveEmpresa($idEmpresa), $datos, self::TTL_CONTADORES);
        return $datos;
    }

    /**
     * Alertas de tareas (global por usuario, con caché): desglose vencidas / por vencer
     * y total. @return array{vencidas:int,por_vencer:int,total:int}
     */
    private function contadorTareas(int $idUsuario): array
    {
        $cache = Cache::get(self::claveTareas($idUsuario));
        if (is_array($cache) && isset($cache['total'])) {
            return $cache;
        }
        $d = (new TareaRepository())->getAlertaTareasDetalle($idUsuario);
        $det = [
            'vencidas'   => (int) ($d['vencidas'] ?? 0),
            'por_vencer' => (int) ($d['por_vencer'] ?? 0),
        ];
        $det['total'] = $det['vencidas'] + $det['por_vencer'];
        Cache::set(self::claveTareas($idUsuario), $det, self::TTL_CONTADORES);
        return $det;
    }

    /**
     * Submódulos con permiso asignado que el usuario aún no ha visitado (caché por
     * empresa+usuario, TTL corto). Alimenta el aviso "submódulo nuevo" del navbar.
     * @return array<int, array{id_submodulo:int,nombre_submodulo:string,ruta:string,nombre_modulo:string}>
     */
    private function submodulosNuevos(int $idEmpresa, int $idUsuario): array
    {
        $cache = Cache::get(self::claveSubmodulosNuevos($idEmpresa, $idUsuario));
        if (is_array($cache)) {
            return $cache;
        }
        try {
            $datos = (new PermisoSubmodulo())->getSubmodulosNuevosDeUsuario($idUsuario, $idEmpresa);
        } catch (\Throwable $e) {
            $datos = [];
        }
        Cache::set(self::claveSubmodulosNuevos($idEmpresa, $idUsuario), $datos, self::TTL_CONTADORES);
        return $datos;
    }

    /**
     * Permiso 'ver' por RUTA MVC (con caché por empresa+usuario). Evalúa la unión de
     * rutas de contadores + novedades (rara vez cambian → TTL más largo).
     * @param callable(string):bool $puedeVer  Recibe la ruta MVC y responde si tiene permiso 'ver'.
     * @return array<string,bool>  ruta => bool
     */
    private function permisosNavbar(int $idEmpresa, int $idUsuario, callable $puedeVer): array
    {
        $cache = Cache::get(self::clavePermisos($idEmpresa, $idUsuario));
        if (is_array($cache)) {
            return $cache;
        }
        $rutas = array_unique(array_merge(
            array_values(self::RUTAS_MODULO),
            array_values(self::NOVEDAD_RUTAS),
            [self::RUTA_EMPRESA, self::RUTA_SUSCRIPCIONES]
        ));
        $perms = [];
        foreach ($rutas as $ruta) {
            $perms[$ruta] = $puedeVer($ruta) === true;
        }
        Cache::set(self::clavePermisos($idEmpresa, $idUsuario), $perms, self::TTL_PERMISOS);
        return $perms;
    }

    /**
     * Contadores que el usuario puede ver, listos para el navbar.
     *
     * @param callable(string):bool $puedeVer  Evalúa permiso 'ver' por ruta MVC.
     * @return array<string,mixed>
     */
    public function getContadores(int $idEmpresa, int $idUsuario, callable $puedeVer): array
    {
        $out = [];

        if ($idEmpresa > 0) {
            $empresa = $this->contadoresEmpresa($idEmpresa);
            $permRuta = $this->permisosNavbar($idEmpresa, $idUsuario, $puedeVer);

            // Contadores de borrador/pendiente/whatsapp
            foreach (self::RUTAS_MODULO as $clave => $ruta) {
                if (empty($permRuta[$ruta])) {
                    continue;
                }
                $out[$clave] = (int) ($empresa[$clave] ?? 0);
            }

            // Novedades SRI por tipo (solo módulos permitidos)
            $novData = is_array($empresa['__novedad_sri'] ?? null) ? $empresa['__novedad_sri'] : [];
            $novedad = [];
            foreach (self::NOVEDAD_RUTAS as $tipo => $ruta) {
                if (empty($permRuta[$ruta])) {
                    continue;
                }
                $novedad[$tipo] = (int) ($novData[$tipo] ?? 0);
            }
            if (!empty($novedad)) {
                $out['novedad_sri'] = $novedad;
            }

            // Suscripción del sistema: avisar si está por vencer (≤ umbral escalado por
            // periodicidad) o vencida. Así una suscripción mensual no queda siempre encendida.
            if (!empty($permRuta[self::RUTA_EMPRESA])) {
                $vig = $empresa['__vigencia'] ?? null;
                if (is_array($vig) && isset($vig['dias'])) {
                    $dias   = (int) $vig['dias'];
                    $umbral = $this->umbralVigencia($vig['meses'] ?? null);
                    if ($dias <= $umbral) {
                        $out['suscripcion'] = [
                            'dias'   => $dias,
                            'estado' => $dias < 0 ? 'vencida' : 'por_vencer',
                        ];
                    }
                }

                // Suscripciones que la empresa activa gestiona como ADMINISTRADORA:
                // avisar si hay de sus clientes vencidas o por vencer.
                if (!empty($permRuta[self::RUTA_SUSCRIPCIONES])) {
                    $sus = $empresa['__suscripciones'] ?? null;
                    if (is_array($sus)) {
                        $sVenc = (int) ($sus['vencidas'] ?? 0);
                        $sProx = (int) ($sus['por_vencer'] ?? 0);
                        if ($sVenc > 0 || $sProx > 0) {
                            $out['suscripciones_gestion'] = [
                                'vencidas'   => $sVenc,
                                'por_vencer' => $sProx,
                                'total'      => $sVenc + $sProx,
                            ];
                        }
                    }
                }

                // Firma electrónica: avisar si no hay firma vigente, o si está por caducar/caducada.
                $firma = $empresa['__firma'] ?? null;
                if (is_array($firma)) {
                    if (!empty($firma['sin_firma'])) {
                        $out['firma'] = ['dias' => null, 'estado' => 'sin_firma'];
                    } elseif (isset($firma['dias']) && (int) $firma['dias'] <= self::UMBRAL_FIRMA_DIAS) {
                        $dias = (int) $firma['dias'];
                        $out['firma'] = [
                            'dias'   => $dias,
                            'estado' => $dias < 0 ? 'caducada' : 'por_caducar',
                        ];
                    }
                }
            }
        }

        // Submódulos nuevos (sin visitar): global por usuario+empresa, no depende de permiso 'ver'
        // sobre otro módulo (el permiso YA es sobre el submódulo mismo).
        if ($idEmpresa > 0 && $idUsuario > 0) {
            $out['submodulos_nuevos'] = $this->submodulosNuevos($idEmpresa, $idUsuario);
        }

        // Tareas: global por usuario, siempre incluido (solo requiere sesión).
        $tareas = $this->contadorTareas($idUsuario);
        $out['tareas_alertas'] = $tareas['total']; // total: el badge lo muestra
        $out['tareas_detalle'] = [                  // desglose para el tooltip de la campana
            'vencidas'   => $tareas['vencidas'],
            'por_vencer' => $tareas['por_vencer'],
        ];

        return $out;
    }
}
