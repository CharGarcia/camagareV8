<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Pestaña «Asiento contable» de los modales de documento (compras, ingresos, egresos, notas de
 * crédito, …). Pieza única para las dos reglas que comparten todos esos modales:
 *
 *  1. **Se muestra solo si el usuario tiene acceso a Contabilidad → Asientos Contables.** Quien
 *     no puede entrar al Libro Diario tampoco ve el asiento desde el documento.
 *  2. **Se edita solo con permiso de actualizar** sobre ese mismo módulo. Sin él, la pestaña
 *     queda en solo lectura: el partial no dibuja los botones de guardar/agregar línea y el
 *     componente JS pinta las líneas como readonly.
 *
 * Nada del guardado vive aquí: la pestaña lee el asiento de
 * `modulos/asientos-contables/getDetalleAjax` y lo guarda en `…/update`, que ya traen el
 * control de permisos, la validación de cuadre contra el documento
 * (App\Helpers\CuadreDocumentoAsiento) y la auditoría.
 *
 * Uso en la vista — hacen falta las TRES cosas:
 *   1) el <li> de la pestaña,
 *   2) su entrada en el dropdown de pestañas configurables,
 *   3) el tab-pane (que incluye app/views/partials/asiento_tab.php).
 *
 *   <?php if (\App\Helpers\AsientoPestana::puedeVer()): ?>
 *     <li class="nav-item"><a class="nav-link" id="…" …>Asiento contable</a></li>
 *   <?php endif; ?>
 *   …
 *   <?php if (\App\Helpers\AsientoPestana::puedeVer()): ?>
 *     <div class="tab-pane fade" id="…" role="tabpanel">
 *       <?php $prefijo = 'mc'; require MVC_APP . '/views/partials/asiento_tab.php'; ?>
 *     </div>
 *   <?php endif; ?>
 */
class AsientoPestana
{
    /** Ruta MVC del módulo que gobierna el permiso de la pestaña. */
    public const RUTA_MODULO = 'modulos/asientos_contables';

    /** ¿Se muestra la pestaña «Asiento contable»? */
    public static function puedeVer(): bool
    {
        return Permisos::puedeVer(self::RUTA_MODULO);
    }

    /** ¿Se puede editar y guardar el asiento desde la pestaña? */
    public static function puedeEditar(): bool
    {
        return self::puedeVer() && Permisos::puedeActualizar(self::RUTA_MODULO);
    }
}
