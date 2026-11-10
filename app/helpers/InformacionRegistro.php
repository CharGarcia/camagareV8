<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Pestaña «Información» de los modales (solo lectura): quién creó y quién modificó el registro,
 * con fecha y hora, la línea de tiempo de sus acciones (log_sistema) y, si el documento mueve
 * stock, los movimientos de inventario que generó (también los anulados).
 *
 * Pieza única para todo el sistema. Reglas que comparten todos los modales:
 *  1. **Solo la ven administradores y superadministradores (niveles 2 y 3).** La vista no la
 *     dibuja para el nivel 1 y el endpoint común la rechaza con 403.
 *  2. **Es de solo lectura** y no lleva tarjeta de permisos.
 *  3. Un usuario sin «acceso total» en el módulo solo consulta registros que él creó (§6).
 *
 * Para agregarla a un modal hacen falta DOS cosas:
 *
 *  A) Registrar el módulo en CATALOGO (clave = ruta MVC, la de getRutaModulo()).
 *
 *  B) En la vista del modal, tres líneas (el prefijo es el del modal: 'cons', 'ped', …):
 *
 *     1) el botón, dentro del <ul class="nav nav-tabs">:
 *          <?= \App\Helpers\InformacionRegistro::boton('cons') ?>
 *     2) su entrada en las pestañas configurables (renderDropdownPestanas):
 *          $pestanas += \App\Helpers\InformacionRegistro::pestanaConfigurable('cons');
 *     3) el panel, dentro del <div class="tab-content"> y FUERA del <form> si el modal
 *        deshabilita sus controles al bloquearse:
 *          <?= \App\Helpers\InformacionRegistro::panel('cons', $rutaModulo, 'cons_id') ?>
 *        El tercer argumento es el id del <input> que guarda el id del registro abierto.
 *
 * No hay que escribir JavaScript ni un endpoint: `public/js/informacion-registro.js` (cargado en
 * partials/scripts.php) detecta el panel y consulta
 * `{ruta}/getInformacionRegistroAjax`, que vive en BaseModuloController. Una vista *standalone*
 * (sin partials/scripts.php) debe incluir ese JS a mano.
 *
 * Manual: docs/manual/guias/pestana-informacion.md.
 */
class InformacionRegistro
{
    /** Nivel mínimo de usuario que ve la pestaña (2 = administrador). */
    public const NIVEL_MINIMO = 2;

    /**
     * Módulos con pestaña Información. Clave: ruta MVC del módulo.
     *   tabla  → tabla de la cabecera; es también `tabla_afectada` en log_sistema. Debe tener
     *            created_at, updated_at, created_by y updated_by (§5).
     *   nombre → cómo se nombra el registro en los textos ("la consignación", "el pedido").
     *   kardex → valores de inventario_kardex.referencia_tipo del documento; vacío u omitido si
     *            el módulo no mueve stock (no se dibuja esa sección).
     *   kardex_detalle → solo si el kardex del documento referencia sus LÍNEAS y no la cabecera
     *            (inventario_kardex.referencia_id = id de la línea): ['tabla' => tabla de
     *            líneas, 'fk' => columna que apunta a la cabecera].
     *   global → true si la tabla NO lleva id_empresa (catálogo global).
     */
    public const CATALOGO = [
        'modulos/consignaciones_ventas' => [
            'tabla'  => 'consignaciones_ventas',
            'nombre' => 'la consignación',
            'kardex' => ['CONSIGNACION_VENTA', 'EDICION_CONSIGNACION_VENTA'],
        ],
        'modulos/factura_venta' => [
            'tabla'  => 'ventas_cabecera',
            'nombre' => 'la factura',
            'kardex' => ['factura_venta'],
        ],
        'modulos/compras' => [
            'tabla'  => 'compras_cabecera',
            'nombre' => 'la compra',
            'kardex' => ['compra', 'compra_item', 'nota_credito_compra'],
            // El kardex de compras apunta a compras_detalle.id, no a la cabecera.
            'kardex_detalle' => ['tabla' => 'compras_detalle', 'fk' => 'id_compra'],
        ],
        'modulos/notas_credito' => [
            'tabla'  => 'notas_credito_cabecera',
            'nombre' => 'la nota de crédito',
            'kardex' => ['nota_credito'],
        ],
        'modulos/nota_debito' => [
            'tabla'  => 'nota_debito_cabecera',
            'nombre' => 'la nota de débito',
        ],
        'modulos/proformas' => [
            'tabla'  => 'proformas_cabecera',
            'nombre' => 'la proforma',
        ],
        'modulos/recibo_venta' => [
            'tabla'  => 'recibos_venta_cabecera',
            'nombre' => 'el recibo',
            'kardex' => ['recibo_venta'],
        ],
        'modulos/retenciones_compras' => [
            'tabla'  => 'retencion_compra_cabecera',
            'nombre' => 'la retención',
        ],
        'modulos/retenciones_ventas' => [
            'tabla'  => 'retencion_venta_cabecera',
            'nombre' => 'la retención',
        ],
        'modulos/clientes' => [
            'tabla'  => 'clientes',
            'nombre' => 'el cliente',
        ],
        'modulos/liquidacion_compra' => [
            'tabla'  => 'liquidaciones_cabecera',
            'nombre' => 'la liquidación',
        ],
    ];

    /** ¿El usuario en sesión ve la pestaña? */
    public static function puedeVer(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 1) >= self::NIVEL_MINIMO;
    }

    /** Configuración del módulo, o null si no está en el catálogo. Acepta la ruta con guion o guion bajo. */
    public static function config(string $rutaModulo): ?array
    {
        $ruta = trim($rutaModulo, '/');
        $cfg  = self::CATALOGO[$ruta] ?? self::CATALOGO[str_replace('-', '_', $ruta)] ?? null;
        if ($cfg === null) {
            return null;
        }
        return $cfg + ['nombre' => 'el registro', 'kardex' => [], 'kardex_detalle' => null, 'global' => false];
    }

    /** id del .tab-pane (es la clave que usan las pestañas configurables). */
    public static function idPanel(string $prefijo): string
    {
        return $prefijo . '-tab-informacion';
    }

    /** Entrada para renderDropdownPestanas(); vacía si el usuario no ve la pestaña. */
    public static function pestanaConfigurable(string $prefijo): array
    {
        return self::puedeVer() ? [self::idPanel($prefijo) => 'Información'] : [];
    }

    /**
     * <li> de la pestaña; cadena vacía si el usuario no la ve.
     *
     * @param string $clases clases extra del enlace, para igualarlo al resto de pestañas del modal
     *                       (p. ej. 'py-2 small' en los modales de documentos electrónicos).
     */
    public static function boton(string $prefijo, string $clases = ''): string
    {
        if (!self::puedeVer()) {
            return '';
        }
        $id    = htmlspecialchars(self::idPanel($prefijo), ENT_QUOTES, 'UTF-8');
        $clase = htmlspecialchars(trim('nav-link ' . $clases), ENT_QUOTES, 'UTF-8');
        return '<li class="nav-item" role="presentation">'
            . '<a class="' . $clase . '" id="' . $id . '-btn" data-bs-toggle="tab" data-bs-target="#' . $id . '" href="#' . $id . '"'
            . ' role="tab" style="white-space:nowrap;" title="Información del registro"><i class="bi bi-clock-history me-1"></i> Información</a>'
            . '</li>';
    }

    /**
     * .tab-pane de la pestaña; cadena vacía si el usuario no la ve o el módulo no está en el catálogo.
     *
     * @param string $idInputRegistro id del <input> con el id del registro abierto en el modal. Si
     *                                el modal lo guarda en una variable JS y no en un input, pasar
     *                                'fn:nombreFuncion': una función global que devuelve ese id
     *                                (ref.: Facturas de Venta, `fn:FV_idRegistroInformacion`).
     */
    public static function panel(string $prefijo, string $rutaModulo, string $idInputRegistro): string
    {
        $cfg = self::config($rutaModulo);
        if (!self::puedeVer() || $cfg === null) {
            return '';
        }
        $e   = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $url = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/') . '/' . trim($rutaModulo, '/') . '/getInformacionRegistroAjax';
        $origenId = str_starts_with($idInputRegistro, 'fn:')
            ? ' data-id-fn="' . $e(substr($idInputRegistro, 3)) . '"'
            : ' data-id-input="' . $e($idInputRegistro) . '"';

        return '<div class="tab-pane fade p-3" id="' . $e(self::idPanel($prefijo)) . '" role="tabpanel"'
            . ' data-info-registro data-url="' . $e($url) . '"' . $origenId
            . ' data-nombre="' . $e((string) $cfg['nombre']) . '">'
            . '<div class="p-4 text-center text-muted small">Cargando información...</div>'
            . '</div>';
    }
}
