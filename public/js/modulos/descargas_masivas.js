/* ============================================================================
 * Descargas Masivas
 * Verifica cuántos documentos hay en el rango (contarAjax) y, si está dentro
 * del límite, descarga el ZIP navegando directo al endpoint (descargarAjax).
 * ========================================================================== */
(function () {
    'use strict';

    const app  = document.getElementById('dm-app');
    const BASE = app.dataset.base || '';
    const RUTA = app.dataset.ruta || 'modulos/descargas-masivas';
    const URL  = (accion) => `${BASE}/${RUTA}/${accion}`;

    function filtros() {
        return {
            tipo:    document.getElementById('dm-tipo').value,
            formato: (document.querySelector('input[name="dm-formato"]:checked') || {}).value || 'pdf',
            desde:   document.getElementById('dm-desde').value,
            hasta:   document.getElementById('dm-hasta').value,
        };
    }

    function mostrarResultado(html, clase) {
        const cont = document.getElementById('dm-resultado');
        const alerta = document.getElementById('dm-resultado-alert');
        cont.style.display = '';
        alerta.className = 'alert mb-3 ' + clase;
        alerta.innerHTML = html;
    }

    async function verificar() {
        const f = filtros();
        if (!f.desde || !f.hasta) {
            Swal.fire({ icon: 'info', title: 'Faltan fechas', text: 'Selecciona el rango de fechas.' });
            return;
        }
        if (f.desde > f.hasta) {
            Swal.fire({ icon: 'info', title: 'Rango inválido', text: 'La fecha "desde" no puede ser posterior a "hasta".' });
            return;
        }

        const btn = document.getElementById('dm-btn-verificar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verificando…';
        document.getElementById('dm-resultado').style.display = 'none';

        try {
            const params = new URLSearchParams(f);
            const r = await fetch(`${URL('contarAjax')}?${params.toString()}`);
            const j = await r.json();
            if (!j.ok) throw new Error(j.mensaje || 'No se pudo verificar.');

            if (j.cantidad === 0) {
                mostrarResultado('No se encontraron documentos con esos filtros.', 'alert-secondary');
                document.getElementById('dm-btn-descargar').style.display = 'none';
            } else if (!j.dentro_limite) {
                mostrarResultado(
                    `Se encontraron <strong>${j.cantidad}</strong> documentos y el máximo por descarga es ` +
                    `<strong>${j.limite}</strong>. Reduce el rango de fechas.`,
                    'alert-warning'
                );
                document.getElementById('dm-btn-descargar').style.display = 'none';
            } else {
                mostrarResultado(`Se encontraron <strong>${j.cantidad}</strong> documentos, listos para descargar.`, 'alert-success');
                document.getElementById('dm-btn-descargar').style.display = '';
            }
        } catch (e) {
            mostrarResultado(e.message, 'alert-danger');
            document.getElementById('dm-btn-descargar').style.display = 'none';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Verificar cantidad';
        }
    }

    function descargar() {
        const f = filtros();
        const params = new URLSearchParams(f);
        // Navegación directa: el navegador maneja la descarga (Content-Disposition:
        // attachment) sin salir de la pantalla actual.
        const a = document.createElement('a');
        a.href = `${URL('descargarAjax')}?${params.toString()}`;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    window.DM = { verificar, descargar };
})();
