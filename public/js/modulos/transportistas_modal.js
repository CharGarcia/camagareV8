/**
 * Lógica compartida para el Modal de Transportistas
 */
(function (window, document) {
    'use strict';

    // Se asume que BASE_URL está definido globalmente
    function getBaseUrlTr() {
        if (typeof B_URL !== 'undefined' && B_URL) return B_URL;
        if (typeof BASE_URL !== 'undefined' && BASE_URL) return BASE_URL;
        if (typeof window.B_URL !== 'undefined' && window.B_URL) return window.B_URL;
        if (typeof window.BASE_URL !== 'undefined' && window.BASE_URL) return window.BASE_URL;
        
        // Fallback dinámico: extraer de la etiqueta script actual
        const scripts = document.getElementsByTagName('script');
        for (let i = 0; i < scripts.length; i++) {
            const src = scripts[i].src;
            if (src && src.includes('/js/modulos/transportistas_modal.js')) {
                return src.split('/js/modulos/transportistas_modal.js')[0];
            }
        }
        return '';
    }
    const urlBaseTr = getBaseUrlTr() + '/modulos/transportistas';
    const urlClientes = getBaseUrlTr() + '/modulos/clientes';
    let timerSriTr = null;

    // --- Funciones Globales ---
    window.TR_abrirCrear = function () {
        TR_resetModal();
        document.getElementById('tr-modal-titulo').textContent = 'Nuevo Transportista';
        const btnEl = document.getElementById('btn-tr-eliminar');
        if (btnEl) btnEl.classList.add('d-none');
        
        const modalEl = document.getElementById('modalTransportista');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    };

    window.TR_abrirEditar = function (data) {
        const r = (data instanceof HTMLElement) ? JSON.parse(data.dataset.row) : data;
        TR_resetModal();
        document.getElementById('tr-modal-titulo').textContent  = 'Editar Transportista';
        document.getElementById('tr-id').value                  = r.id;
        document.getElementById('tr-tipo-id').value             = r.tipo_id        || '05';
        document.getElementById('tr-identificacion').value      = r.identificacion  || '';
        document.getElementById('tr-nombre').value              = r.nombre          || '';
        document.getElementById('tr-placa').value               = r.placa           || '';
        document.getElementById('tr-telefono').value            = r.telefono        || '';
        document.getElementById('tr-email').value               = r.email           || '';
        document.getElementById('tr-direccion').value           = r.direccion       || '';
        document.getElementById('tr-estado').value              = r.estado          || 'activo';
        
        window.TR_cambiarTipoId(false);
        const btnEl = document.getElementById('btn-tr-eliminar');
        if (btnEl) btnEl.classList.remove('d-none');
        
        const modalEl = document.getElementById('modalTransportista');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    };

    function TR_resetModal() {
        ['tr-id','tr-identificacion','tr-nombre','tr-placa','tr-telefono','tr-email','tr-direccion'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        document.getElementById('tr-tipo-id').value = '05';
        document.getElementById('tr-estado').value  = 'activo';
        TR_limpiarBadgeSri();
        const errEmail = document.getElementById('tr-email-error');
        if (errEmail) { errEmail.textContent = ''; errEmail.classList.add('d-none'); }
        const btn = document.getElementById('btn-tr-guardar');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; }
        window.TR_cambiarTipoId(false);
    }

    window.TR_cambiarTipoId = function (limpiar = true) {
        const sel = document.getElementById('tr-tipo-id');
        const campo = document.getElementById('tr-identificacion');
        if (!sel || !campo) return;
        
        const tipo = sel.value;
        if (limpiar) { campo.value = ''; TR_limpiarBadgeSri(); }
        
        if (tipo === '04') { campo.maxLength = 13; campo.inputMode = 'numeric'; campo.placeholder = 'RUC (13 dígitos)'; }
        else if (tipo === '05') { campo.maxLength = 10; campo.inputMode = 'numeric'; campo.placeholder = 'Cédula (10 dígitos)'; }
        else { campo.maxLength = 20; campo.inputMode = 'text'; campo.placeholder = 'Número de pasaporte'; }
    };

    window.TR_onIdentificacionInput = function () {
        const tipo  = document.getElementById('tr-tipo-id').value;
        const valor = document.getElementById('tr-identificacion').value.trim();
        TR_limpiarBadgeSri();
        clearTimeout(timerSriTr);
        if ((tipo === '04' && valor.length === 13) || (tipo === '05' && valor.length === 10)) {
            timerSriTr = setTimeout(() => TR_consultarSri(valor), 700);
        }
    };

    function TR_limpiarBadgeSri() {
        const sw = document.getElementById('tr-sri-spinner-wrap');
        const bd = document.getElementById('tr-sri-badge');
        if (sw) sw.classList.add('d-none');
        if (bd) { bd.className = 'badge d-none'; bd.textContent = ''; }
    }

    window.TR_mostrarBadgeSri = function (texto, cls) {
        const bd = document.getElementById('tr-sri-badge');
        if (bd) { bd.textContent = texto; bd.className = 'badge ' + cls; bd.classList.remove('d-none'); }
    };

    async function TR_consultarSri(identificacion) {
        const sw = document.getElementById('tr-sri-spinner-wrap');
        if (sw) sw.classList.remove('d-none');
        window.TR_mostrarBadgeSri('Consultando…', 'bg-secondary');
        try {
            const fd = new FormData();
            fd.append('identificacion', identificacion);
            const resp = await fetch(urlClientes + '/consultarSri', { method: 'POST', body: fd });
            const json = await resp.json();
            if (sw) sw.classList.add('d-none');
            if (!json.ok) {
                window.TR_mostrarBadgeSri(json.error || 'No encontrado', 'bg-warning text-dark');
                return;
            }
            window.TR_mostrarBadgeSri('✓ SRI', 'bg-success');
            const d = json.data;
            if (d.nombre) {
                const el = document.getElementById('tr-nombre');
                if (el) el.value = d.nombre.toUpperCase();
            }
            if (d.direccion) {
                const el = document.getElementById('tr-direccion');
                if (el) el.value = d.direccion;
            }
        } catch {
            if (sw) sw.classList.add('d-none');
            window.TR_mostrarBadgeSri('Error', 'bg-danger');
        }
    }

    window.TR_validarEmails = function () {
        const campo = document.getElementById('tr-email');
        const errEl = document.getElementById('tr-email-error');
        const raw   = (campo?.value || '').trim();
        if (!campo || !errEl) return true;
        if (raw === '') {
            errEl.textContent = 'El correo electrónico es obligatorio.';
            errEl.classList.remove('d-none');
            campo.classList.add('is-invalid');
            return false;
        }
        const correos  = raw.split(',').map(s => s.trim()).filter(s => s);
        const invalidos = correos.filter(c => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c));
        if (invalidos.length) {
            errEl.textContent = 'Correos inválidos: ' + invalidos.join(', ');
            errEl.classList.remove('d-none');
            campo.classList.add('is-invalid');
            return false;
        }
        errEl.classList.add('d-none');
        campo.classList.remove('is-invalid');
        return true;
    };

    window.TR_guardar = function () {
        const nombreInput = document.getElementById('tr-nombre');
        const identInput  = document.getElementById('tr-identificacion');
        if (!nombreInput || !identInput) return;

        const nombre = nombreInput.value.trim();
        const ident  = identInput.value.trim();
        if (!ident)  return Swal.fire({ icon: 'warning', title: 'Atención', text: 'La identificación es obligatoria.' });
        if (!nombre) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'El nombre / razón social es obligatorio.' });
        if (!window.TR_validarEmails()) return;

        const id  = document.getElementById('tr-id').value;
        const btn = document.getElementById('btn-tr-guardar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando…';
        }

        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('tipo_id',        document.getElementById('tr-tipo-id').value);
        fd.append('identificacion', ident);
        fd.append('nombre',         nombre);
        fd.append('placa',          document.getElementById('tr-placa').value);
        fd.append('telefono',       document.getElementById('tr-telefono').value);
        fd.append('email',          document.getElementById('tr-email').value.trim());
        fd.append('direccion',      document.getElementById('tr-direccion').value);
        fd.append('estado',         document.getElementById('tr-estado').value);

        fetch(urlBaseTr + '/guardar-ajax', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
                }
                if (d.ok) {
                    const modalEl = document.getElementById('modalTransportista');
                    if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
                    
                    if (typeof window.TR_fetchSearch === 'function') {
                        window.TR_fetchSearch(id ? window.TR_currentPage : 1);
                    }
                    
                    Swal.fire({ icon: 'success', title: '¡Guardado!', text: d.mensaje, timer: 2000, showConfirmButton: false });
                    
                    // Disparar evento por si otros módulos lo necesitan
                    document.dispatchEvent(new CustomEvent('transportistaGuardado', { detail: d }));
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: d.mensaje });
                }
            })
            .catch(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
                }
                Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' });
            });
    };

    window.TR_eliminar = async function () {
        const id     = document.getElementById('tr-id').value;
        const nombre = document.getElementById('tr-nombre').value;
        if (!id) return;

        const conf   = await Swal.fire({
            icon: 'warning', title: '¿Eliminar transportista?',
            html: `<strong>${nombre}</strong> será eliminado.`,
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        });
        if (!conf.isConfirmed) return;
        
        fetch(urlBaseTr + '/eliminar-ajax', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                const modalEl = document.getElementById('modalTransportista');
                if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
                
                if (typeof window.TR_fetchSearch === 'function') window.TR_fetchSearch(1);
                Swal.fire({ icon: 'success', title: 'Eliminado', text: d.mensaje, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.mensaje });
            }
        });
    };

    // Inicialización de mayúsculas al cargar
    function initTrEvents() {
        ['tr-nombre', 'tr-placa'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', function () { this.value = this.value.toUpperCase(); });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTrEvents);
    } else {
        initTrEvents();
    }

})(window, document);
