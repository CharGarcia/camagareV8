<?php
/**
 * Vista: Errores del sistema (consulta de errores_sistema, solo lectura, nivel 3).
 */
$base = BASE_URL;
?>

<style>
    .err-scroll { max-height: calc(100dvh - 220px); overflow: auto; }
    .err-row { cursor: pointer; }
    .err-row:hover { background-color: rgba(0, 0, 0, .04); }
    #errFiltrosAyuda code { background: rgba(0,0,0,.05); padding: 0 4px; border-radius: 3px; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-bug me-2 text-danger"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Registro técnico de errores del sistema para diagnóstico. Solo consulta.</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3 bg-white">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form id="errFormBuscar" class="input-group input-group-sm" style="width:340px" onsubmit="event.preventDefault(); ERRSIS_cambiarPagina(1);">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="errInputBuscar" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar…  tipo:fatal  sqlstate:22P02" autocomplete="off">
                <button type="button" class="btn btn-outline-secondary" title="Ayuda de búsqueda" data-bs-toggle="collapse" data-bs-target="#errFiltrosAyuda"><i class="bi bi-question-lg"></i></button>
            </form>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="errPaginationInfo" class="text-muted small fw-medium">0-0 / 0</span>
            <div id="errWrapperPagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div id="errFiltrosAyuda" class="collapse border-bottom">
        <div class="px-3 py-2 small text-muted">
            Escribí texto libre (busca en mensaje, clase, ruta y SQLSTATE) o usá claves:
            <code>tipo:fatal</code> · <code>tipo:excepcion</code> ·
            <code>sqlstate:22P02</code> · <code>ruta:factura</code> ·
            <code>usuario:5</code> · <code>fecha:2026-07-01..2026-07-27</code> ·
            <code>fecha:&gt;=2026-07-01</code>. Usá <code>-clave:valor</code> para negar.
        </div>
    </div>

    <div class="card-body p-0">
        <div class="err-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm sticky-top" style="z-index: 1;">
                    <tr>
                        <th class="ps-3 err-sort" data-col="created_at" role="button" style="width: 14%;">Fecha <i class="bi bi-arrow-down-short"></i></th>
                        <th class="err-sort" data-col="tipo" role="button" style="width: 9%;">Tipo</th>
                        <th class="err-sort text-center" data-col="sql_state" role="button" style="width: 9%;">SQLSTATE</th>
                        <th class="err-sort" data-col="ruta" role="button" style="width: 18%;">Ruta</th>
                        <th style="width: 38%;">Mensaje</th>
                        <th style="width: 12%;">Usuario</th>
                    </tr>
                </thead>
                <tbody id="tbodyErrores">
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <span class="spinner-border spinner-border-sm text-primary me-2"></span> Cargando errores...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal detalle -->
<div class="modal fade" id="modalErrorDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold mb-0"><i class="bi bi-bug me-2 text-danger"></i> Detalle del error</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body px-4 py-3" id="errDetalleBody">
                <div class="text-center py-4">
                    <span class="spinner-border spinner-border-sm text-primary"></span> Cargando...
                </div>
            </div>
            <div class="modal-footer bg-light border-top px-4 py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
</script>
<script src="<?= $base ?>/js/config/errores_sistema.js?v=<?= @filemtime(MVC_ROOT . '/public/js/config/errores_sistema.js') ?: time() ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof ERRSIS_cargarListado === 'function') ERRSIS_cargarListado(1);
    });
</script>
