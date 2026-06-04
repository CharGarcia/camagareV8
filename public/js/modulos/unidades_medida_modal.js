(function () {
    'use strict';

    // ══════════════════════════════════════════════════════════════════════
    // LÓGICA TIPO DE MEDIDA
    // ══════════════════════════════════════════════════════════════════════

    const urlBaseTipo = (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || '')) + '/modulos/unidades-medida';
    const modalTipoEl = document.getElementById('modalTipoMedida');
    let modalTipoInst = null;

    function getModalTipo() {
        if (!modalTipoInst && typeof bootstrap !== 'undefined' && modalTipoEl) {
            modalTipoInst = new bootstrap.Modal(modalTipoEl);
        }
        return modalTipoInst;
    }

    function resetAlertTipo() {
        const a = document.getElementById('alertModalTipo');
        if (a) {
            a.className = 'alert d-none mb-3 py-2 small shadow-sm border-0';
            a.textContent = '';
        }
    }

    window.abrirModalTipoCrear = function () {
        const form = document.getElementById('formTipoModal');
        if (form) form.reset();
        const idInput = document.getElementById('tipo_id_modal');
        if (idInput) idInput.value = '';
        const titulo = document.getElementById('tituloModalTipo');
        if (titulo) titulo.textContent = 'Nuevo Tipo de Medida';
        const tabBtn = document.getElementById('tab-tipo-info-btn');
        if (tabBtn) tabBtn.classList.add('disabled');
        const btnEliminar = document.getElementById('btnEliminarTipoModal');
        if (btnEliminar) btnEliminar.classList.add('d-none');
        resetAlertTipo();
        const tabGen = document.getElementById('tab-tipo-general-btn');
        if (tabGen && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(tabGen).show();
        }
        const m = getModalTipo();
        if (m) m.show();
        const focusEl = document.getElementById('tipo_nombre_modal');
        if (focusEl) setTimeout(() => focusEl.focus(), 450);
    };

    window.abrirModalTipoEditar = function (row) {
        const data = typeof row.dataset.row === 'string' ? JSON.parse(row.dataset.row) : row;
        const form = document.getElementById('formTipoModal');
        if (form) form.reset();
        
        const idInput = document.getElementById('tipo_id_modal');
        if (idInput) idInput.value = data.id;
        const codigoInput = document.getElementById('tipo_codigo_modal');
        if (codigoInput) codigoInput.value = data.codigo || '';
        const nombreInput = document.getElementById('tipo_nombre_modal');
        if (nombreInput) nombreInput.value = data.nombre || '';
        const statusSelect = document.getElementById('tipo_status_modal');
        if (statusSelect) statusSelect.value = (data.status === true || data.status === 't' || data.status === '1' || data.status === 1) ? '1' : '0';
        
        const titulo = document.getElementById('tituloModalTipo');
        if (titulo) titulo.textContent = 'Editar Tipo de Medida';
        const tabBtn = document.getElementById('tab-tipo-info-btn');
        if (tabBtn) tabBtn.classList.remove('disabled');
        const btnEliminar = document.getElementById('btnEliminarTipoModal');
        if (btnEliminar) btnEliminar.classList.remove('d-none');
        
        resetAlertTipo();
        const tabGen = document.getElementById('tab-tipo-general-btn');
        if (tabGen && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(tabGen).show();
        }
        
        fetchDetalleTipo(data.id);
        fetchHistorialTipo(data.id);
        const m = getModalTipo();
        if (m) m.show();
    };

    async function fetchDetalleTipo(id) {
        try {
            const r = await fetch(`${urlBaseTipo}/getDetalleTipoAjax?id=${id}`);
            const j = await r.json();
            if (j.ok) {
                const uSpan = document.getElementById('info_tipo_unidades');
                if (uSpan) uSpan.textContent = j.data.total_unidades ?? '0';
                const cSpan = document.getElementById('info_tipo_created_at');
                if (cSpan) cSpan.textContent = j.data.creado_at ?? '—';
                const cbSpan = document.getElementById('info_tipo_created_by');
                if (cbSpan) cbSpan.textContent = j.data.creado_por ?? '—';
                const uatSpan = document.getElementById('info_tipo_updated_at');
                if (uatSpan) uatSpan.textContent = j.data.actualizado_at ?? '—';
            }
        } catch (e) {}
    }

    async function fetchHistorialTipo(id) {
        const c = document.getElementById('historialTipoContainer');
        if (!c) return;
        try {
            const r = await fetch(`${urlBaseTipo}/getHistorialAjax?id=${id}&tabla=tipo_medida`);
            const j = await r.json();
            if (j.ok && j.data.length > 0) {
                let html = '<div class="timeline-border position-absolute h-100 border-start border-2 border-primary border-opacity-10" style="left:10px;top:0;"></div>';
                j.data.forEach(log => {
                    const icon = log.accion.includes('Crear') ? 'bi-plus-circle-fill text-success' :
                        log.accion.includes('Actualizar') ? 'bi-pencil-fill text-primary' :
                        log.accion.includes('Eliminar') ? 'bi-trash-fill text-danger' :
                        'bi-clock-history text-secondary';
                    html += `<div class="timeline-item position-relative mb-3 ps-4">
                        <div class="timeline-icon position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center shadow-sm border"
                             style="left:0;top:0;width:22px;height:22px;z-index:2;">
                            <i class="bi ${icon}" style="font-size:0.7rem;"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-center mb-0">
                                <span class="fw-bold" style="font-size:0.75rem;">${log.accion}</span>
                                <span class="text-muted" style="font-size:0.65rem;">${log.created_at}</span>
                            </div>
                            <div class="text-muted mb-1" style="font-size:0.7rem;"><i class="bi bi-person me-1"></i>${log.usuario_nombre || 'SISTEMA'}</div>
                        </div>
                    </div>`;
                });
                c.innerHTML = html;
            } else {
                c.innerHTML = '<div class="text-center py-3 text-muted small">Sin historial.</div>';
            }
        } catch (e) {
            c.innerHTML = '<div class="text-center py-2 text-danger small">Error al cargar.</div>';
        }
    }

    window.guardarTipoModal = async function () {
        const form = document.getElementById('formTipoModal');
        const idInput = document.getElementById('tipo_id_modal');
        if (!form || !idInput) return;
        
        const id = idInput.value;
        const action = id ? `${urlBaseTipo}/updateTipo` : `${urlBaseTipo}/storeTipo`;
        const btn = document.getElementById('btnGuardarTipoModal');
        const alert = document.getElementById('alertModalTipo');

        if (!form.checkValidity()) { form.reportValidity(); return; }

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd = new FormData(form);
            const resp = await fetch(action, { method: 'POST', body: fd });
            const json = await resp.json();

            if (alert) {
                alert.textContent = json.msg || json.error || '';
                alert.className = `alert mb-3 py-2 small shadow-sm border-0 ${json.ok ? 'alert-success' : 'alert-danger'}`;
                alert.classList.remove('d-none');
            }

            if (json.ok) {
                setTimeout(() => {
                    const m = getModalTipo();
                    if (m) m.hide();
                    if (window.fetchSearchTipos) window.fetchSearchTipos(1);
                    window.dispatchEvent(new CustomEvent('tipoMedidaGuardado'));
                }, 700);
            }
        } catch (e) {
            if (alert) {
                alert.textContent = 'Error de conexión.';
                alert.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                alert.classList.remove('d-none');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    };

    window.eliminarTipoModal = async function () {
        if (!confirm('¿Eliminar este tipo de medida? Esta acción no se puede deshacer.')) return;
        const idInput = document.getElementById('tipo_id_modal');
        if (!idInput) return;
        const id = idInput.value;
        const btn = document.getElementById('btnEliminarTipoModal');
        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBaseTipo}/deleteTipo`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                const m = getModalTipo();
                if (m) m.hide();
                if (window.fetchSearchTipos) window.fetchSearchTipos(1);
            } else {
                const alert = document.getElementById('alertModalTipo');
                if (alert) {
                    alert.textContent = json.error || 'Error al eliminar.';
                    alert.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                    alert.classList.remove('d-none');
                }
            }
        } catch (e) {} finally { if (btn) btn.disabled = false; }
    };

    // ══════════════════════════════════════════════════════════════════════
    // LÓGICA UNIDAD DE MEDIDA
    // ══════════════════════════════════════════════════════════════════════

    const urlBaseUnidad = (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || '')) + '/modulos/unidades-medida';
    const modalUnidadEl = document.getElementById('modalUnidadMedida');
    let modalUnidadInst = null;

    function getModalUnidad() {
        if (!modalUnidadInst && typeof bootstrap !== 'undefined' && modalUnidadEl) {
            modalUnidadInst = new bootstrap.Modal(modalUnidadEl);
        }
        return modalUnidadInst;
    }

    function resetAlertUnidad() {
        const a = document.getElementById('alertModalUnidad');
        if (a) {
            a.className = 'alert d-none mb-3 py-2 small shadow-sm border-0';
            a.textContent = '';
        }
    }

    window.toggleFactorBase = function (chk) {
        const wrapper = document.getElementById('wrapperFactorBase');
        const input = document.getElementById('unidad_factor_modal');
        if (chk.checked) {
            if (wrapper) wrapper.style.display = 'none';
            if (input) input.value = '1';
        } else {
            if (wrapper) wrapper.style.display = '';
        }
    };

    window.abrirModalUnidadCrear = function (idTipoPrecargado) {
        const form = document.getElementById('formUnidadModal');
        if (form) form.reset();
        
        const idInput = document.getElementById('unidad_id_modal');
        if (idInput) idInput.value = '';
        const factorInput = document.getElementById('unidad_factor_modal');
        if (factorInput) factorInput.value = '1';
        const esBaseCheck = document.getElementById('unidad_esbase_modal');
        if (esBaseCheck) esBaseCheck.checked = false;
        const wrapperFactor = document.getElementById('wrapperFactorBase');
        if (wrapperFactor) wrapperFactor.style.display = '';
        const titulo = document.getElementById('tituloModalUnidad');
        if (titulo) titulo.textContent = 'Nueva Unidad de Medida';
        const tabInfoBtn = document.getElementById('tab-uni-info-btn');
        if (tabInfoBtn) tabInfoBtn.classList.add('disabled');
        const btnEliminar = document.getElementById('btnEliminarUnidadModal');
        if (btnEliminar) btnEliminar.classList.add('d-none');
        
        resetAlertUnidad();

        if (idTipoPrecargado) {
            const tipoSelect = document.getElementById('unidad_tipo_modal');
            if (tipoSelect) tipoSelect.value = idTipoPrecargado;
        }

        const tabGen = document.getElementById('tab-uni-general-btn');
        if (tabGen && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(tabGen).show();
        }
        const m = getModalUnidad();
        if (m) m.show();
        const focusEl = document.getElementById('unidad_nombre_modal');
        if (focusEl) setTimeout(() => focusEl.focus(), 450);
    };

    window.abrirModalUnidadEditar = function (row) {
        const data = typeof row.dataset.row === 'string' ? JSON.parse(row.dataset.row) : row;
        const esBase = (data.es_base === true || data.es_base === 't' || data.es_base === '1' || data.es_base === 1);
        const form = document.getElementById('formUnidadModal');
        if (form) form.reset();
        
        const idInput = document.getElementById('unidad_id_modal');
        if (idInput) idInput.value = data.id;
        const tipoSelect = document.getElementById('unidad_tipo_modal');
        if (tipoSelect) tipoSelect.value = data.id_tipo || '';
        const codigoInput = document.getElementById('unidad_codigo_modal');
        if (codigoInput) codigoInput.value = data.codigo || '';
        const nombreInput = document.getElementById('unidad_nombre_modal');
        if (nombreInput) nombreInput.value = data.nombre || '';
        const abreviaturaInput = document.getElementById('unidad_abreviatura_modal');
        if (abreviaturaInput) abreviaturaInput.value = data.abreviatura || '';
        const esBaseCheck = document.getElementById('unidad_esbase_modal');
        if (esBaseCheck) esBaseCheck.checked = esBase;
        const factorInput = document.getElementById('unidad_factor_modal');
        if (factorInput) factorInput.value = data.factor_base ?? '1';
        const wrapperFactor = document.getElementById('wrapperFactorBase');
        if (wrapperFactor) wrapperFactor.style.display = esBase ? 'none' : '';
        const statusSelect = document.getElementById('unidad_status_modal');
        if (statusSelect) statusSelect.value = (data.status === true || data.status === 't' || data.status === '1' || data.status === 1) ? '1' : '0';
        
        const titulo = document.getElementById('tituloModalUnidad');
        if (titulo) titulo.textContent = 'Editar Unidad de Medida';
        const tabInfoBtn = document.getElementById('tab-uni-info-btn');
        if (tabInfoBtn) tabInfoBtn.classList.remove('disabled');
        const btnEliminar = document.getElementById('btnEliminarUnidadModal');
        if (btnEliminar) btnEliminar.classList.remove('d-none');
        
        resetAlertUnidad();
        const tabGen = document.getElementById('tab-uni-general-btn');
        if (tabGen && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(tabGen).show();
        }
        
        fetchDetalleUnidad(data.id);
        fetchHistorialUnidad(data.id);
        const m = getModalUnidad();
        if (m) m.show();
    };

    async function fetchDetalleUnidad(id) {
        try {
            const r = await fetch(`${urlBaseUnidad}/getDetalleUnidadAjax?id=${id}`);
            const j = await r.json();
            if (j.ok) {
                const tSpan = document.getElementById('info_uni_tipo');
                if (tSpan) tSpan.textContent = j.data.tipo_nombre ?? '—';
                const cSpan = document.getElementById('info_uni_created_at');
                if (cSpan) cSpan.textContent = j.data.creado_at ?? '—';
                const cbSpan = document.getElementById('info_uni_created_by');
                if (cbSpan) cbSpan.textContent = j.data.creado_por ?? '—';
                const uatSpan = document.getElementById('info_uni_updated_at');
                if (uatSpan) uatSpan.textContent = j.data.actualizado_at ?? '—';
            }
        } catch (e) {}
    }

    async function fetchHistorialUnidad(id) {
        const c = document.getElementById('historialUnidadContainer');
        if (!c) return;
        try {
            const r = await fetch(`${urlBaseUnidad}/getHistorialAjax?id=${id}&tabla=unidades_medida`);
            const j = await r.json();
            if (j.ok && j.data.length > 0) {
                let html = '<div class="timeline-border position-absolute h-100 border-start border-2 border-primary border-opacity-10" style="left:10px;top:0;"></div>';
                j.data.forEach(log => {
                    const icon = log.accion.includes('Crear') ? 'bi-plus-circle-fill text-success' :
                        log.accion.includes('Actualizar') ? 'bi-pencil-fill text-primary' :
                        log.accion.includes('Eliminar') ? 'bi-trash-fill text-danger' :
                        'bi-clock-history text-secondary';
                    html += `<div class="timeline-item position-relative mb-3 ps-4">
                        <div class="timeline-icon position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center shadow-sm border"
                             style="left:0;top:0;width:22px;height:22px;z-index:2;">
                            <i class="bi ${icon}" style="font-size:0.7rem;"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-center mb-0">
                                <span class="fw-bold" style="font-size:0.75rem;">${log.accion}</span>
                                <span class="text-muted" style="font-size:0.65rem;">${log.created_at}</span>
                            </div>
                            <div class="text-muted mb-1" style="font-size:0.7rem;"><i class="bi bi-person me-1"></i>${log.usuario_nombre || 'SISTEMA'}</div>
                        </div>
                    </div>`;
                });
                c.innerHTML = html;
            } else {
                c.innerHTML = '<div class="text-center py-3 text-muted small">Sin historial.</div>';
            }
        } catch (e) {
            c.innerHTML = '<div class="text-center py-2 text-danger small">Error al cargar.</div>';
        }
    }

    window.guardarUnidadModal = async function () {
        const form = document.getElementById('formUnidadModal');
        const idInput = document.getElementById('unidad_id_modal');
        if (!form || !idInput) return;
        
        const id = idInput.value;
        const action = id ? `${urlBaseUnidad}/updateUnidad` : `${urlBaseUnidad}/storeUnidad`;
        const btn = document.getElementById('btnGuardarUnidadModal');
        const alert = document.getElementById('alertModalUnidad');

        if (!form.checkValidity()) { form.reportValidity(); return; }

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd = new FormData(form);
            const resp = await fetch(action, { method: 'POST', body: fd });
            const json = await resp.json();

            if (alert) {
                alert.textContent = json.msg || json.error || '';
                alert.className = `alert mb-3 py-2 small shadow-sm border-0 ${json.ok ? 'alert-success' : 'alert-danger'}`;
                alert.classList.remove('d-none');
            }

            if (json.ok) {
                setTimeout(() => {
                    const m = getModalUnidad();
                    if (m) m.hide();
                    if (window.fetchSearchUnidades) window.fetchSearchUnidades(1);
                    window.dispatchEvent(new CustomEvent('unidadMedidaGuardada'));
                }, 700);
            }
        } catch (e) {
            if (alert) {
                alert.textContent = 'Error de conexión.';
                alert.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                alert.classList.remove('d-none');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    };

    window.eliminarUnidadModal = async function () {
        if (!confirm('¿Eliminar esta unidad de medida?')) return;
        const idInput = document.getElementById('unidad_id_modal');
        if (!idInput) return;
        const id = idInput.value;
        const btn = document.getElementById('btnEliminarUnidadModal');
        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBaseUnidad}/deleteUnidad`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                const m = getModalUnidad();
                if (m) m.hide();
                if (window.fetchSearchUnidades) window.fetchSearchUnidades(1);
            } else {
                const alert = document.getElementById('alertModalUnidad');
                if (alert) {
                    alert.textContent = json.error || 'Error al eliminar.';
                    alert.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                    alert.classList.remove('d-none');
                }
            }
        } catch (e) {} finally { if (btn) btn.disabled = false; }
    };

})();
