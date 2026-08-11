/* ============================================================================
 * Descargas Masivas
 * Verifica cuántos documentos hay en el rango (contarAjax) y, si está dentro
 * del límite, descarga navegando directo al endpoint (descargarAjax). El
 * servidor decide si entrega un solo PDF o un ZIP según la cantidad.
 * ========================================================================== */
(function () {
    'use strict';

    const app  = document.getElementById('dm-app');
    const BASE = app.dataset.base || '';
    const RUTA = app.dataset.ruta || 'modulos/descargas-masivas';
    const URL  = (accion) => `${BASE}/${RUTA}/${accion}`;

    let TIPOS_SIN_XML = [];
    try { TIPOS_SIN_XML = JSON.parse(app.dataset.tiposSinXml || '[]'); } catch (e) { TIPOS_SIN_XML = []; }

    const elTipo = document.getElementById('dm-tipo');
    const elBloqueFecha = document.getElementById('dm-bloque-fecha');
    const elBloqueNumero = document.getElementById('dm-bloque-numero');
    const elFxml = document.getElementById('dm-f-xml');
    const elFambos = document.getElementById('dm-f-ambos');
    const elFpdf = document.getElementById('dm-f-pdf');
    const elAvisoSinXml = document.getElementById('dm-sin-xml-aviso');

    function modoActual() {
        return (document.querySelector('input[name="dm-modo"]:checked') || {}).value || 'fecha';
    }

    function actualizarBloquesModo() {
        const numero = modoActual() === 'numero';
        elBloqueFecha.style.display = numero ? 'none' : '';
        elBloqueNumero.style.display = numero ? '' : 'none';
        document.getElementById('dm-resultado').style.display = 'none';
    }

    function actualizarFormatoDisponible() {
        const sinXml = TIPOS_SIN_XML.includes(elTipo.value);
        elFxml.disabled = sinXml;
        elFambos.disabled = sinXml;
        if (sinXml && (elFxml.checked || elFambos.checked)) {
            elFpdf.checked = true;
        }
        elAvisoSinXml.style.display = sinXml ? '' : 'none';
        document.getElementById('dm-resultado').style.display = 'none';
    }

    document.querySelectorAll('input[name="dm-modo"]').forEach((r) => r.addEventListener('change', actualizarBloquesModo));
    elTipo.addEventListener('change', actualizarFormatoDisponible);
    actualizarBloquesModo();
    actualizarFormatoDisponible();

    function filtros() {
        const f = {
            tipo:    elTipo.value,
            formato: (document.querySelector('input[name="dm-formato"]:checked') || {}).value || 'pdf',
            modo:    modoActual(),
        };
        if (f.modo === 'numero') {
            f.numero_desde = document.getElementById('dm-numero-desde').value;
            f.numero_hasta = document.getElementById('dm-numero-hasta').value;
        } else {
            f.desde = document.getElementById('dm-desde').value;
            f.hasta = document.getElementById('dm-hasta').value;
        }
        return f;
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
        if (f.modo === 'numero') {
            if (!f.numero_desde || !f.numero_hasta) {
                Swal.fire({ icon: 'info', title: 'Faltan números', text: 'Indica el número "desde" y "hasta".' });
                return;
            }
            if (parseInt(f.numero_desde, 10) > parseInt(f.numero_hasta, 10)) {
                Swal.fire({ icon: 'info', title: 'Rango inválido', text: 'El número "desde" no puede ser mayor al "hasta".' });
                return;
            }
        } else {
            if (!f.desde || !f.hasta) {
                Swal.fire({ icon: 'info', title: 'Faltan fechas', text: 'Selecciona el rango de fechas.' });
                return;
            }
            if (f.desde > f.hasta) {
                Swal.fire({ icon: 'info', title: 'Rango inválido', text: 'La fecha "desde" no puede ser posterior a "hasta".' });
                return;
            }
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

            const btnDescargar = document.getElementById('dm-btn-descargar');
            if (j.cantidad === 0) {
                mostrarResultado('No se encontraron documentos con esos filtros.', 'alert-secondary');
                btnDescargar.style.display = 'none';
            } else if (!j.dentro_limite) {
                mostrarResultado(
                    `Se encontraron <strong>${j.cantidad}</strong> documentos y el máximo por descarga es ` +
                    `<strong>${j.limite}</strong>. Reduce el rango.`,
                    'alert-warning'
                );
                btnDescargar.style.display = 'none';
            } else {
                const comoPdf = j.salida === 'pdf';
                mostrarResultado(
                    `Se encontraron <strong>${j.cantidad}</strong> documentos, listos para descargar` +
                    (comoPdf ? ' en <strong>un solo PDF</strong>.' : ' en un <strong>ZIP</strong>.'),
                    'alert-success'
                );
                btnDescargar.innerHTML = comoPdf
                    ? '<i class="bi bi-download me-1"></i> Descargar PDF'
                    : '<i class="bi bi-download me-1"></i> Descargar ZIP';
                btnDescargar.style.display = '';
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
