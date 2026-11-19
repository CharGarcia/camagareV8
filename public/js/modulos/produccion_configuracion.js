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

    // ── Ayudas «?» de la tarjeta Funciones ─────────────────────────────────
    // Se abren al pasar el mouse o al tocar el ícono (foco), y se cierran al salir o tocar fuera.
    // Ojo: este archivo se carga ANTES que bootstrap.bundle (layouts/main.php lo trae al final, en
    // partials/scripts.php), así que los popovers se crean recién en DOMContentLoaded.
    function initAyudas() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Popover) return;
        document.querySelectorAll('.pcfg-ayuda[data-bs-toggle="popover"]').forEach(elAyuda => {
            bootstrap.Popover.getOrCreateInstance(elAyuda, { trigger: 'hover focus', customClass: 'pcfg-popover', container: 'body' });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAyudas);
    } else {
        initAyudas();
    }

    // ── «Configurar cuentas» → Configuración Contable, abierta en Producción ──
    // URL limpia (sin parámetros): el tipo de asiento viaja en sesión con entrarAjax(), igual que
    // el aviso de asientos pendientes; la pantalla destino lo lee y abre la sección sola.
    document.addEventListener('click', e => {
        const a = e.target.closest ? e.target.closest('a[data-pcfg-config-contable]') : null;
        if (!a) return;
        e.preventDefault();
        const url = a.getAttribute('href');
        fetch(`${url}/entrarAjax`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ tipo: 'produccion', seccion: 'general' }).toString(),
        }).catch(() => null).finally(() => { window.location.href = url; });
    });

    // ── Crear bodega al vuelo ───────────────────────────────────────────────
    // El «+» de cada selector abre el modal reutilizable de Bodegas (bodegas/modal.php). Al crearla,
    // el modal se cierra y emite `bodegaGuardado`: se recargan los tres selectores desde el servidor
    // (sin perder lo elegido) y la nueva queda puesta en el selector desde el que se pidió.

    let campoBodegaPendiente = '';

    window.PCFG_nuevaBodega = function (campo) {
        if (typeof window.abrirModalBodegaCrear !== 'function') { aviso('warning', 'No disponible', 'No tiene permiso para crear bodegas.'); return; }
        campoBodegaPendiente = campo;
        window.abrirModalBodegaCrear({ cerrarAlCrear: true });
    };

    function etiquetaBodega(b) {
        return b.nombre + (b.establecimiento_codigo ? ' — Est. ' + b.establecimiento_codigo : '');
    }

    document.addEventListener('bodegaGuardado', (e) => {
        const d = e.detail || {};
        const campo = campoBodegaPendiente;
        campoBodegaPendiente = '';
        if (!d.nuevo || !campo || !d.id) return;
        fetch(`${URL}/bodegas-ajax`).then(r => r.json()).then(res => {
            if (!res.ok) { aviso('error', 'Error', res.mensaje || res.error || 'No se pudieron recargar las bodegas.'); return; }
            const lista = res.data || [];
            document.querySelectorAll('select.pcfg-bodega').forEach(sel => {
                const actual = sel.value;
                sel.innerHTML = '<option value="">— Sin definir —</option>';
                lista.forEach(b => {
                    const o = document.createElement('option');
                    o.value = String(b.id);
                    o.textContent = etiquetaBodega(b);
                    sel.appendChild(o);
                });
                sel.value = actual;
            });
            const destino = el('pcfg-' + campo);
            if (!lista.some(b => +b.id === +d.id)) {
                aviso('info', 'Bodega creada', 'La bodega se creó inactiva, por eso no aparece en la lista. Actívela en el módulo Bodegas para usarla.');
                return;
            }
            if (destino) destino.value = String(d.id);
        }).catch(err => { console.error(err); aviso('error', 'Error', 'No se pudieron recargar las bodegas.'); });
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
