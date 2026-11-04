/**
 * Configuración de Producción — bodegas e interruptores (las cuentas van en Configuración Contable).
 * Guardar por primera vez activa Producción para la empresa.
 */
(function () {
    'use strict';

    const URL = window.PCFG_URL_BASE;
    let guardando = false;

    function el(id) { return document.getElementById(id); }

    function aviso(icono, titulo, texto) {
        if (typeof Swal === 'undefined') { alert(titulo + '\n' + (texto || '')); return; }
        return Swal.fire({ icon: icono, title: titulo, text: texto || '', confirmButtonColor: '#0d6efd' });
    }

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
