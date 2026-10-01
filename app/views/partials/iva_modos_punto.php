<?php
/**
 * Modo de cálculo del IVA por punto de emisión (id_punto => 'subtotal' | 'linea_linea').
 * Lo consume CMG_modoIvaPunto() de public/js/app.js: los modales de documentos calculan
 * el IVA con la configuración del establecimiento de la serie elegida.
 * Se puede incluir más de una vez en la misma página (p. ej. el modal de NC dentro de
 * Facturas de Venta): solo la primera inclusión consulta y emite el mapa.
 */
if (!empty($GLOBALS['__cmgIvaModosPuntoIncluido'])) {
    return;
}
$GLOBALS['__cmgIvaModosPuntoIncluido'] = true;
$__ivaModosPunto = \App\Helpers\IvaSubtotal::modosPorPunto((int) ($_SESSION['id_empresa'] ?? 0));
?>
<script>window.CMG_IVA_MODOS_PUNTO = <?= json_encode((object) $__ivaModosPunto) ?>;</script>
