<?php
/**
 * Pestaña «Asiento contable» de un modal de documento (compras, ingresos, egresos, notas de
 * crédito, …). Un solo HTML para todos los módulos: la tabla del asiento, sus totales, el
 * cuadre contra el documento y los botones de guardar / restaurar.
 *
 * Lo alimenta `crearAsientoTab()` de public/js/modulos/asiento_contable_tab.js, que espera
 * exactamente estos ids. La pestaña **solo debe incluirse** cuando
 * `\App\Helpers\AsientoPestana::puedeVer()` es true (el permiso lo decide la vista, no este
 * archivo, porque también hay que ocultar el <li> de la pestaña y su entrada en el dropdown
 * de pestañas configurables).
 *
 * Variables:
 *   $prefijo  string  Prefijo de los ids. Compras usa 'mc' → mc-asiento-tbody, mc-asiento-save, …
 */
$p = $prefijo ?? 'doc';
$puedeEditar = \App\Helpers\AsientoPestana::puedeEditar();
?>
<div class="border rounded-3 overflow-hidden bg-white shadow-sm">
  <div class="table-responsive" style="max-height: 350px;">
    <table class="table table-sm table-detalle mb-0 text-nowrap" id="<?= $p ?>-asiento-table">
      <thead>
        <tr class="table-light border-bottom">
          <th class="ps-3 py-2 small fw-bold text-muted" style="width:45%;">Cuenta Contable</th>
          <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">D&eacute;bito / Debe</th>
          <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Cr&eacute;dito / Haber</th>
          <th class="py-2 small fw-bold text-muted" style="width:15%;">Referencia</th>
          <th style="width:40px;"></th>
        </tr>
      </thead>
      <tbody id="<?= $p ?>-asiento-tbody">
        <tr><td colspan="5" class="text-center py-4 text-muted">Guarda el documento para generar el asiento contable.</td></tr>
      </tbody>
      <tfoot class="bg-light fw-bold border-top sticky-bottom">
        <tr>
          <td class="text-end py-2">Totales:</td>
          <td class="text-end pe-3 py-2 text-primary" id="<?= $p ?>-asiento-debe">0.00</td>
          <td class="text-end pe-3 py-2 text-primary" id="<?= $p ?>-asiento-haber">0.00</td>
          <td colspan="2" class="py-2">
            <div class="d-flex align-items-center gap-2 justify-content-end pe-3">
              <span class="x-small text-muted">Diferencia: <span id="<?= $p ?>-asiento-dif">0.00</span></span>
              <span id="<?= $p ?>-asiento-badge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Cuadrado</span>
            </div>
          </td>
        </tr>
        <!-- Cuadre contra el documento: el asiento no solo debe tener Debe = Haber, tiene que
             seguir reflejando el importe del documento que lo originó. -->
        <tr class="d-none border-top" id="<?= $p ?>-asiento-doc">
          <td class="text-end py-2 small text-muted text-uppercase" id="<?= $p ?>-asiento-doc-etiqueta">Documento</td>
          <td class="text-end pe-3 py-2" id="<?= $p ?>-asiento-doc-total">0.00</td>
          <td colspan="3" class="py-2 ps-2"><span class="small fw-normal" id="<?= $p ?>-asiento-doc-estado"></span></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center gap-2 flex-wrap">
    <div class="d-flex align-items-center gap-3">
      <?php if ($puedeEditar): ?>
      <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold d-none" id="<?= $p ?>-asiento-add">
        <i class="bi bi-plus-circle me-1"></i> Agregar l&iacute;nea
      </button>
      <?php endif; ?>
      <div class="small fw-bold text-muted">L&iacute;neas: <span id="<?= $p ?>-asiento-count">0</span></div>
    </div>
    <?php if ($puedeEditar): ?>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="<?= $p ?>-asiento-restore" title="Descartar la edición manual y volver a armar el asiento con las reglas contables">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar autom&aacute;tico
      </button>
      <button type="button" class="btn btn-primary btn-sm d-none" id="<?= $p ?>-asiento-save">
        <i class="bi bi-save me-1"></i> Guardar asiento
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>
<div class="px-1 pt-2 small text-muted" id="<?= $p ?>-asiento-status"></div>
