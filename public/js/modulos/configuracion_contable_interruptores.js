/**
 * Configuración Contable → «Módulos que contabilizan».
 *
 * Interruptor por empresa para cada módulo que genera asientos automáticos
 * (config/contabilidad_modulos.php). El backend es ContabilidadInterruptorService:
 *   GET  /modulos/configuracion-contable/getInterruptoresAjax   → { ok, grupos, puede_editar }
 *   POST /modulos/configuracion-contable/guardarInterruptorAjax  (clave, contabiliza) → { ok, cambio, aviso }
 *
 * Los módulos con `sigue_a` (Retornos y Facturación CV) se muestran sin interruptor: su asiento
 * depende de la consignación de origen.
 */
(function () {
    'use strict';

    const API = `${window.BASE_URL || ''}/modulos/configuracion-contable`;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function alerta(titulo, texto, icono) {
        if (window.Swal) { Swal.fire(titulo, texto, icono); } else { alert(texto); }
    }

    function pintar(grupos, puedeEditar) {
        const cont = document.getElementById('contabIntLista');
        let html = '';
        Object.keys(grupos).forEach(grupo => {
            html += `<div class="fw-bold small text-uppercase text-secondary mt-2 mb-1">${esc(grupo)}</div>
                     <div class="list-group list-group-flush border rounded-3 mb-2">`;
            grupos[grupo].forEach(m => {
                const id = `contabInt_${m.clave}`;
                const control = m.sigue_a
                    ? `<span class="badge bg-secondary bg-opacity-10 text-secondary border" title="Genera su asiento solo si la consignación de origen lo tiene">Sigue a ${esc(m.sigue_a)}</span>`
                    : `<div class="form-check form-switch mb-0">
                           <input class="form-check-input" type="checkbox" role="switch" id="${id}" data-clave="${esc(m.clave)}"
                                  ${m.contabiliza ? 'checked' : ''} ${puedeEditar ? '' : 'disabled'}>
                       </div>`;
                html += `<div class="list-group-item d-flex justify-content-between align-items-start gap-3 py-2">
                            <div>
                                <label class="fw-medium small mb-0" ${m.sigue_a ? '' : `for="${id}"`}>${esc(m.nombre)}</label>
                                ${m.ayuda ? `<div class="text-muted" style="font-size:.72rem;">${esc(m.ayuda)}</div>` : ''}
                            </div>
                            <div class="flex-shrink-0 pt-1">${control}</div>
                         </div>`;
            });
            html += '</div>';
        });
        cont.innerHTML = html || '<div class="text-muted small">No hay módulos contables declarados.</div>';

        cont.querySelectorAll('input[data-clave]').forEach(chk => chk.addEventListener('change', () => cambiar(chk)));
    }

    async function cargar() {
        const cont = document.getElementById('contabIntLista');
        try {
            const res = await (await fetch(`${API}/getInterruptoresAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })).json();
            if (!res.ok) throw new Error(res.error || 'No se pudo cargar.');
            pintar(res.grupos || {}, !!res.puede_editar);
        } catch (e) {
            cont.innerHTML = `<div class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>${esc(e.message)}</div>`;
        }
    }

    async function cambiar(chk) {
        const contabiliza = chk.checked;
        const nombre = chk.closest('.list-group-item')?.querySelector('label')?.textContent?.trim() || '';

        if (!contabiliza && window.Swal) {
            const r = await Swal.fire({
                title: '¿Dejar de contabilizar?',
                html: `Los documentos nuevos de <b>${esc(nombre)}</b> no generarán asiento contable. `
                    + 'Los que ya lo tienen se conservan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, apagar',
                cancelButtonText: 'Cancelar',
            });
            if (!r.isConfirmed) { chk.checked = true; return; }
        }

        chk.disabled = true;
        try {
            const fd = new FormData();
            fd.append('clave', chk.dataset.clave);
            fd.append('contabiliza', contabiliza ? '1' : '0');
            const res = await (await fetch(`${API}/guardarInterruptorAjax`, { method: 'POST', body: fd })).json();
            if (!res.ok) throw new Error(res.error || 'No se pudo guardar.');
            if (res.aviso) {
                alerta(contabiliza ? 'Módulo encendido' : 'Módulo apagado', res.aviso, 'info');
            } else if (window.Swal) {
                Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 })
                    .fire({ icon: 'success', title: contabiliza ? 'Contabilización encendida' : 'Contabilización apagada' });
            }
        } catch (e) {
            chk.checked = !contabiliza;
            alerta('Error', e.message, 'error');
        } finally {
            chk.disabled = false;
        }
    }

    window.CONTAB_INT_abrir = function () {
        const el = document.getElementById('modalInterruptoresContables');
        if (!el) return;
        bootstrap.Modal.getOrCreateInstance(el).show();
        cargar();
    };
})();
