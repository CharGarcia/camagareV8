<div class="modal fade" id="modalPunto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt-fill me-2 text-primary"></i><span id="puntoModalTitulo">Nuevo punto de servicio</span></h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formPunto" onsubmit="return false;">
                <input type="hidden" id="punto_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="punto_nombre" class="form-control form-control-sm" maxlength="150" placeholder="Ej: Garita Norte - Cliente X">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Dirección</label>
                        <input type="text" id="punto_direccion" class="form-control form-control-sm" placeholder="Referencia del sitio">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Latitud</label>
                            <input type="text" id="punto_latitud" class="form-control form-control-sm" placeholder="-0.180653">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Longitud</label>
                            <input type="text" id="punto_longitud" class="form-control form-control-sm" placeholder="-78.467834">
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="usarMiUbicacionPunto()">
                            <i class="bi bi-crosshair me-1"></i> Usar mi ubicación actual
                        </button>
                        <span id="punto_geo_msg" class="small text-muted ms-2"></span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Radio geocerca (m)</label>
                            <input type="number" id="punto_radio_m" class="form-control form-control-sm" value="150" min="10" max="5000">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Estado</label>
                            <select id="punto_estado" class="form-select form-select-sm">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="punto_exige_gps" checked>
                        <label class="form-check-label small" for="punto_exige_gps">
                            Exigir GPS (rechaza marcas fuera de la geocerca del punto)
                        </label>
                    </div>
                    <div class="alert alert-info small py-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Al guardar se genera el QR del punto. El empleado lo escanea desde su celular para registrar entrada/salida.
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-2">
                    <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="btnEliminarPunto" style="display:none;" onclick="eliminarPuntoDesdeModal()">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" onclick="guardarPunto()"><i class="bi bi-check-lg me-1"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
