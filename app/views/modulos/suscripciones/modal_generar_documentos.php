<!-- Modal para Generar Documentos -->
<div class="modal fade" id="modalGenerarDocumentos" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fs-6 fw-bold text-dark"><i class="bi bi-file-earmark-text text-primary me-2"></i>Generar documentos manualmente</h5>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formGenerarDocumentos" onsubmit="event.preventDefault(); window.ejecutarGeneracionDocumentos();">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label x-small fw-bold text-muted mb-1" style="font-size: 0.85rem;">
                                Serie <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm border-primary border-opacity-25" name="id_punto_emision" id="gd_id_punto_emision" required style="font-family: 'Inter', sans-serif;">
                                <?php if (empty($puntos) || count($puntos) > 1): ?>
                                    <option value="">- Seleccione -</option>
                                <?php endif; ?>
                                <?php foreach ($puntos ?? [] as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-est="<?= $p['id_establecimiento'] ?>" <?= (count($puntos ?? []) === 1) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['cod_establecimiento'] . '-' . $p['codigo_punto']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label x-small fw-bold text-muted mb-1" style="font-size: 0.85rem;">
                                Periodicidad a Ejecutar <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm border-primary border-opacity-25" name="id_periodicidad" id="gd_id_periodicidad" required style="font-family: 'Inter', sans-serif;">
                                <option value="">- Seleccione -</option>
                                <?php foreach ($periodicidades ?? [] as $per): ?>
                                    <option value="<?= $per['id'] ?>">
                                        <?= htmlspecialchars($per['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3 p-3 border rounded-3 bg-light bg-opacity-10">
                        <h6 class="fs-6 fw-bold text-dark mb-2" style="font-size: 0.85rem !important;"><i class="bi bi-card-text text-secondary me-1"></i>Detalles para el Documento</h6>
                        <div class="mb-2">
                            <label class="form-label x-small fw-bold text-muted mb-1" style="font-size: 0.8rem;">Texto para agregar a cada ítem (Opcional)</label>
                            <input type="text" class="form-control form-control-sm" name="texto_item" id="gd_texto_item" placeholder="Ej: Mes y Año, Nombre del Alumno..." style="font-family: 'Inter', sans-serif;">
                            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Se concatenará a la descripción de cada ítem.</small>
                        </div>
                        
                        <div class="row g-2 mt-2">
                            <div class="col-12">
                                <label class="form-label x-small fw-bold text-muted mb-0" style="font-size: 0.8rem;">Información Adicional (Opcional)</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" name="info_concepto" id="gd_info_concepto" placeholder="Concepto (ej. Estudiante)" style="font-family: 'Inter', sans-serif;">
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control form-control-sm" name="info_detalle" id="gd_info_detalle" placeholder="Detalle (ej. Juan Pérez)" style="font-family: 'Inter', sans-serif;">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 bg-info bg-opacity-10 text-info border-info border-opacity-25" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle me-1"></i> Se generarán los documentos considerando fecha de inicio, fin y si el próximo cobro está vencido.
                    </div>

                </form>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Cerrar
                </button>
                <button type="submit" class="btn btn-primary btn-sm px-4" id="gd-btn-submit" form="formGenerarDocumentos">
                    <i class="bi bi-play-circle me-1"></i> Generar Documentos
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.abrirModalGenerarDocumentos = function () {
        document.getElementById('formGenerarDocumentos').reset();
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalGenerarDocumentos'));
        modal.show();
    };

    window.ejecutarGeneracionDocumentos = async function () {
        const idPunto = document.getElementById('gd_id_punto_emision').value;
        const idPeriodicidad = document.getElementById('gd_id_periodicidad').value;
        const textoItem = document.getElementById('gd_texto_item').value.trim();
        const infoConcepto = document.getElementById('gd_info_concepto').value.trim();
        const infoDetalle = document.getElementById('gd_info_detalle').value.trim();

        if (!idPunto) {
            Swal.fire({
                title: 'Atención', 
                text: 'Debe seleccionar la Serie (Establecimiento - Punto de Emisión).', 
                icon: 'warning',
                didClose: () => {
                    document.getElementById('gd_id_punto_emision').focus();
                }
            });
            return;
        }

        if (!idPeriodicidad) {
            Swal.fire({
                title: 'Atención', 
                text: 'Debe seleccionar una Periodicidad a ejecutar.', 
                icon: 'warning',
                didClose: () => {
                    document.getElementById('gd_id_periodicidad').focus();
                }
            });
            return;
        }

        const btn = document.getElementById('gd-btn-submit');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('id_punto_emision', idPunto);
        fd.append('id_periodicidad', idPeriodicidad);
        fd.append('texto_item', textoItem);
        fd.append('info_concepto', infoConcepto);
        fd.append('info_detalle', infoDetalle);

        try {
            const urlBase = window.BASE_URL + '/modulos/suscripciones';
            const r = await fetch(urlBase + '/generarFacturasManualAjax', { method: 'POST', body: fd });
            const d = await r.json();

            if (d.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalGenerarDocumentos')).hide();
                Swal.fire('Proceso Completado', d.mensaje || 'Los documentos han sido generados exitosamente.', 'success');
                if (window.fetchSearch) window.fetchSearch(); // Recargar tabla
            } else {
                Swal.fire('Atención', d.mensaje || 'Error al generar facturas.', 'warning');
            }
        } catch (e) {
            Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
            console.error(e);
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    };
</script>
