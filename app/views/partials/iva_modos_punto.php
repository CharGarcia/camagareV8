<?php
/**
 * Modo de cálculo del IVA por punto de emisión (id_punto => 'subtotal' | 'linea_linea').
 * Lo consume CMG_modoIvaPunto() de public/js/app.js: los modales de documentos calculan
 * el IVA con la configuración del establecimiento de la serie elegida.
 * Además emite los decimales de precio/cantidad por punto (id_punto => {precio, cantidad}),
 * que consume CMG_decimalesPunto(): misma regla, la serie del documento manda.
 * Se puede incluir más de una vez en la misma página (p. ej. el modal de NC dentro de
 * Facturas de Venta): solo la primera inclusión consulta y emite el mapa.
 */
if (!empty($GLOBALS['__cmgIvaModosPuntoIncluido'])) {
    return;
}
$GLOBALS['__cmgIvaModosPuntoIncluido'] = true;
$__ivaModosPunto = \App\Helpers\IvaSubtotal::modosPorPunto((int) ($_SESSION['id_empresa'] ?? 0));
try {
    $__decPunto = (new \App\repositories\modulos\EmpresaRepository())->getDecimalesPorPunto((int) ($_SESSION['id_empresa'] ?? 0));
} catch (\Throwable $e) {
    $__decPunto = [];
}
?>
<script>window.CMG_IVA_MODOS_PUNTO = <?= json_encode((object) $__ivaModosPunto) ?>;
window.CMG_DEC_PUNTO = <?= json_encode((object) $__decPunto) ?>;</script>
