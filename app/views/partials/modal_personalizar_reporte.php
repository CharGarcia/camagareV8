<?php
/**
 * Ventana «Personalizar reporte» (por empresa): columnas visibles en pantalla/Excel y en
 * el PDF, y orientación de la hoja, por cada vista del reporte.
 *
 * No incluir a mano: se pinta con App\Helpers\ColumnasReporte::modal($rutaModulo), que
 * ya valida el nivel (2 y 3) y entrega $rutaModulo y $def (entrada del catálogo).
 * El contenido lo arma public/js/personalizar-reporte.js con los endpoints comunes de
 * BaseModuloController (getPersonalizacionReporteAjax / guardarPersonalizacionReporteAjax).
 */
$__base = rtrim(BASE_URL ?? '', '/');
?>
<div class="modal fade" id="modalPersonalizarReporte" tabindex="-1" aria-hidden="true"
     data-ruta="<?= htmlspecialchars($rutaModulo, ENT_QUOTES) ?>">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fw-bold" style="font-size:1rem;">
                    <i class="bi bi-sliders me-2 text-secondary"></i>Personalizar <?= htmlspecialchars((string) $def['titulo']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="alert alert-info py-2 px-3 small mb-2">
                    <i class="bi bi-building me-1"></i>Lo que se configure aquí aplica a
                    <strong>todos los usuarios de esta empresa</strong>.
                </div>

                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Vista del reporte</label>
                        <select id="pr-vista" class="form-select form-select-sm shadow-none border" style="width:200px;"></select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Orientación del PDF</label>
                        <select id="pr-orientacion" class="form-select form-select-sm shadow-none border" style="width:200px;">
                            <option value="auto">Automática</option>
                            <option value="P">Vertical</option>
                            <option value="L">Horizontal</option>
                        </select>
                    </div>
                </div>

                <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem;">
                    <?php // Una casilla por medio (Pantalla y Excel | PDF, o Pantalla | Excel | PDF): lo arma el JS. ?>
                    <thead class="table-light">
                        <tr id="pr-cabecera"><th>Columna</th></tr>
                    </thead>
                    <tbody id="pr-columnas">
                        <tr><td colspan="4" class="text-center text-muted py-3">Cargando…</td></tr>
                    </tbody>
                </table>
                <div class="form-text mt-2">
                    <i class="bi bi-lock-fill"></i> = columna que identifica la fila; siempre se muestra.
                    &nbsp; — = la columna no existe en ese formato.
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-danger me-auto" id="pr-btn-restablecer"
                        title="Volver todas las vistas de este reporte a sus columnas y orientación originales">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restablecer
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="pr-btn-guardar">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>
<script src="<?= $__base ?>/js/personalizar-reporte.js?v=<?= asset_ver('/js/personalizar-reporte.js') ?>"></script>
