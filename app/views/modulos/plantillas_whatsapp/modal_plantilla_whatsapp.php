<!-- Modal Crear Plantilla -->
<div class="modal fade" id="modalCrearPlantilla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i>Nueva Plantilla WhatsApp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="formCrearPlantilla">
                <div class="modal-body p-4">
                    <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 small mb-4">
                        <i class="fas fa-info-circle me-1"></i> <strong>Nota:</strong> Las plantillas deben ser aprobadas por Meta. Usa nombres en minúsculas sin espacios.
                    </div>

                    <div class="row mb-3 bg-light p-3 rounded border mx-0">
                        <div class="col-md-12 mb-2">
                            <label class="form-label fw-bold text-primary"><i class="fas fa-magic me-1"></i>Tipo de Plantilla <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_creacion" id="tipoRapida" value="rapida" required>
                                    <label class="form-check-label fw-medium" for="tipoRapida">Plantilla Rápida (Variables Automáticas)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_creacion" id="tipoLibre" value="libre" required>
                                    <label class="form-check-label fw-medium" for="tipoLibre">Plantilla Libre (Solo Texto/Documento)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4 bg-white p-3 rounded border mx-0" id="contenedorPlantillasRapidas" style="display: none; border-left: 4px solid #0d6efd !important;">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-list me-1"></i>Seleccione la Plantilla del Sistema <span class="text-danger">*</span></label>
                            <select class="form-select border-primary" id="selectPlantillaRapida" name="plantilla_rapida">
                                <option value="">-- Seleccione --</option>
                                <option value="aviso_mensajes_pendientes">Aviso de Mensajes Pendientes</option>
                                <option value="factura_por_cobrar">Factura por Cobrar</option>
                                <option value="factura_venta">Factura de Venta</option>
                                <option value="cuenta_por_cobrar">Cuenta por Cobrar</option>
                                <option value="renovacion_suscripcion">Renovación de Suscripción</option>
                                <option value="renovacion_firma_electronica">Renovación Firma Electrónica</option>
                                <option value="retencion_compra">Retención en Compras</option>
                                <option value="nota_credito">Nota de Crédito</option>
                                <option value="nota_debito">Nota de Débito</option>
                                <option value="guia_remision">Guía de Remisión</option>
                                <option value="rol_pagos">Rol de Pagos</option>
                                <option value="descuento_empleado">Descuentos a Empleados</option>
                            </select>
                            <div class="form-text mt-2" id="helpPlantillaRapida"><i class="fas fa-info-circle me-1"></i> Al seleccionar, se pre-llenará el formulario con el nombre y parámetros permitidos.</div>
                        </div>
                    </div>

                    <div id="contenedorRestoFormulario" style="display: none;">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium small text-muted">Nombre de la Plantilla</label>
                            <input type="text" class="form-control" name="nombre" placeholder="ejemplo_notificacion_pago" required pattern="[a-z0-9_]+">
                            <div class="form-text" style="font-size: 0.75rem;">Solo minúsculas, números y guiones bajos.</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-medium small text-muted">Categoría</label>
                            <select class="form-select" name="categoria" required>
                                <option value="MARKETING">Marketing</option>
                                <option value="UTILITY" selected>Utilidad (Utility)</option>
                                <option value="AUTHENTICATION">Autenticación</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-medium small text-muted">Idioma</label>
                            <select class="form-select" name="idioma" required>
                                <option value="es" selected>Español (es)</option>
                                <option value="en_US">Inglés (en_US)</option>
                                <option value="es_AR">Español AR (es_AR)</option>
                                <option value="es_MX">Español MX (es_MX)</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-medium small text-muted">Tipo de Cabecera (Opcional)</label>
                            <select class="form-select w-auto mb-2" id="selectTipoCabecera" name="tipo_cabecera">
                                <option value="NONE" selected>Ninguna / Solo Texto</option>
                                <option value="DOCUMENT">Documento (PDF)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3 d-none" id="divPdfEjemplo">
                            <label class="form-label fw-medium small text-muted">PDF de Ejemplo (Obligatorio para Meta)</label>
                            <input type="file" class="form-control" name="pdf_ejemplo" id="inputPdfEjemplo" accept="application/pdf">
                            <div class="form-text" style="font-size: 0.75rem;">Sube un PDF de muestra. Cuando envíes mensajes reales podrás adjuntar el PDF definitivo (ej. la factura real).</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Cuerpo del Mensaje (Body)</label>
                        <textarea class="form-control" name="cuerpo" id="cuerpoPlantilla" rows="5" placeholder="" required></textarea>
                        <div class="mt-2" id="botonesVariablesRapidas"></div>
                        <div class="form-text" id="helpCuerpoPlantilla" style="font-size: 0.75rem;">Puedes escribir el texto libremente.</div>
                    </div>

                    </div> <!-- FIN contenedorRestoFormulario -->

                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarPlantilla">
                        <i class="fas fa-paper-plane me-1"></i> Enviar a Revisión
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Plantilla -->
<div class="modal fade" id="modalEditarPlantilla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit text-warning me-2"></i>Editar Plantilla WhatsApp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="formEditarPlantilla">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_plantilla" id="editarIdPlantilla">
                    
                    <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 small mb-4">
                        <i class="fas fa-exclamation-triangle me-1"></i> <strong>Aviso:</strong> Los cambios enviarán la plantilla a revisión en Meta nuevamente.
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium small text-muted">Nombre de la Plantilla</label>
                            <input type="text" class="form-control bg-light" id="editarNombre" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-medium small text-muted">Categoría</label>
                            <input type="text" class="form-control bg-light" id="editarCategoria" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-medium small text-muted">Idioma</label>
                            <input type="text" class="form-control bg-light" id="editarIdioma" readonly>
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-medium small text-muted">Tipo de Cabecera (Opcional)</label>
                            <select class="form-select w-auto mb-2" id="editarTipoCabecera" name="tipo_cabecera">
                                <option value="NONE">Ninguna / Solo Texto</option>
                                <option value="DOCUMENT">Documento (PDF)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3 d-none" id="divEditarPdfEjemplo">
                            <label class="form-label fw-medium small text-muted">PDF de Ejemplo (Obligatorio para Meta si cambias la cabecera)</label>
                            <input type="file" class="form-control" name="pdf_ejemplo" id="inputEditarPdfEjemplo" accept="application/pdf">
                            <div class="form-text" style="font-size: 0.75rem;">Sube un PDF de muestra solo si necesitas enviar un nuevo ejemplo.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Cuerpo del Mensaje (Body)</label>
                        <textarea class="form-control" name="cuerpo" id="editarCuerpo" rows="5" required></textarea>
                        <div class="form-text" style="font-size: 0.75rem;">Puedes usar variables usando llaves dobles: {{1}}, {{2}}, etc.</div>
                    </div>

                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning" id="btnActualizarPlantilla">
                        <i class="fas fa-save me-1"></i> Guardar y Enviar a Revisión
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
