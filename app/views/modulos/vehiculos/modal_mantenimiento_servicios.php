<?php
/**
 * Catálogo de servicios de mantenimiento (aceite de motor, aceite de caja…): qué servicios
 * controla la empresa, cada cuántos km y con cuántos km de anticipación avisar.
 * Se abre encima de la orden de Car-Wash o de la ficha del vehículo con
 * mantAbrirServicios(urlModulo). Lo incluye modal_vehiculo.php (fuera de su modal).
 */
?>
<div class="modal fade" id="modalMantServicios" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title"><i class="bi bi-gear-wide-connected me-1 text-primary"></i> Servicios de mantenimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2">
                    Servicios que se controlan por kilometraje. <b>Cada (km)</b> es el intervalo por defecto: se usa cuando el
                    vehículo no tiene uno propio ni hay otros de su misma marca de dónde sugerirlo.
                </div>
                <div id="mant_srv_aviso"></div>
                <div class="border rounded-3 bg-white overflow-auto" style="max-height:55vh;">
                    <table class="table table-sm mb-0 align-middle text-nowrap" style="font-size:.8rem;">
                        <thead class="table-light" style="position:sticky; top:0; z-index:1;">
                            <tr>
                                <th class="ps-2">Servicio</th>
                                <th style="width:110px;">Cada (km)</th>
                                <th style="width:120px;" title="Cuántos km antes del cambio se considera «próximo» y se avisa al cliente">Avisar antes (km)</th>
                                <th style="width:70px;" title="Orden en que se muestra">Orden</th>
                                <th class="text-center" style="width:60px;">Activo</th>
                                <th class="text-center" style="width:80px;"></th>
                            </tr>
                        </thead>
                        <tbody id="mant_srv_body">
                            <tr><td colspan="6" class="text-center text-muted py-3">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-link btn-sm p-0 mt-2 text-decoration-none fw-bold d-none" id="mant_srv_agregar">
                    <i class="bi bi-plus-circle me-1"></i> Agregar servicio
                </button>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/js/modulos/mantenimiento_servicios.js?v=<?= asset_ver('/js/modulos/mantenimiento_servicios.js') ?>"></script>
