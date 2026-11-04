/**
 * Configuración de Producción — bodegas, interruptores y cuentas del módulo.
 * Guardar por primera vez activa Producción para la empresa.
 */
(function () {
    'use strict';

    const URL = window.PCFG_URL_BASE;
    let guardando = false;

    function el(id) { return document.getElementById(id); }

    function esc(t) {
        return String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function aviso(icono, titulo, texto) {
        if (typeof Swal === 'undefined') { alert(titulo + '\n' + (texto || '')); return; }
        return Swal.fire({ icon: icono, title: titulo, text: texto || '', confirmButtonColor: '#0d6efd' });
    }

    // ── Buscador de cuentas (selección tipo chip) ────────────────────────────

    document.querySelectorAll('.pcfg-cuenta-txt').forEach(input => {
        const campo  = input.dataset.campo;
        const oculto = el('pcfg-' + campo);
        const dd     = el('pcfg-' + campo + '_dd');
        let timer = null;

        const cerrar = () => { dd.classList.add('d-none'); dd.innerHTML = ''; };

        input.addEventListener('keydown', e => {
            // Con una cuenta ya elegida, Backspace/Delete limpian toda la selección de una vez.
            if ((e.key === 'Backspace' || e.key === 'Delete') && oculto.value) {
                e.preventDefault();
                oculto.value = '';
                input.value = '';
                cerrar();
            }
        });

        input.addEventListener('input', () => {
            if (oculto.value) oculto.value = '';
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { cerrar(); return; }
            timer = setTimeout(() => {
                fetch(`${URL}/buscar-cuentas-ajax?q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(res => {
                        const data = res.data || [];
                        if (!data.length) {
                            dd.innerHTML = '<div class="list-group-item small text-muted">Sin cuentas de movimiento con ese texto.</div>';
                        } else {
                            dd.innerHTML = data.map(c =>
                                `<button type="button" class="list-group-item list-group-item-action small py-1" data-id="${c.id}" data-txt="${esc(c.codigo + ' - ' + c.nombre)}">
                                    <code>${esc(c.codigo)}</code> ${esc(c.nombre)}</button>`).join('');
                        }
                        dd.classList.remove('d-none');
                    })
                    .catch(e => console.error('PCFG cuentas', e));
            }, 250);
        });

        dd.addEventListener('click', e => {
            const b = e.target.closest('[data-id]');
            if (!b) return;
            oculto.value = b.dataset.id;
            input.value = b.dataset.txt;
            cerrar();
        });

        document.addEventListener('click', e => {
            if (!dd.contains(e.target) && e.target !== input) cerrar();
        });
    });

    // ── Guardar ──────────────────────────────────────────────────────────────

    window.PCFG_guardar = function () {
        if (!window.PCFG_PUEDE_GUARDAR) { aviso('warning', 'Sin permiso', 'No tiene permiso para modificar la configuración.'); return; }
        if (guardando) return;
        guardando = true;

        const form = el('pcfg-form');
        const fd = new FormData(form);
        // Los interruptores apagados no viajan en el FormData: se envían explícitos.
        form.querySelectorAll('input[type="checkbox"]').forEach(c => fd.set(c.name, c.checked ? '1' : '0'));

        const btn = el('pcfg-btn-guardar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';

        fetch(`${URL}/guardar-ajax`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) { aviso('error', 'No se guardó', res.mensaje || 'Error desconocido.'); return; }
                aviso('success', 'Configuración guardada', res.mensaje);
            })
            .catch(e => { console.error(e); aviso('error', 'Error', 'No se recibió respuesta del servidor.'); })
            .finally(() => {
                guardando = false;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
            });
    };
})();
