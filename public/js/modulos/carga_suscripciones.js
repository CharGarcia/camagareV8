/**
 * Carga masiva de suscripciones desde Excel.
 *
 * Flujo: seleccionar archivo -> "Revisar" (valida sin escribir) -> "Crear".
 * El archivo queda en el servidor entre ambos pasos, identificado por un token.
 */
(function () {
    'use strict';

    const URL_BASE = window.CS_URL_BASE || '';

    let archivoSeleccionado = null;
    let tokenCarga = null;

    const $ = (id) => document.getElementById(id);

    const dropzone     = $('csDropzone');
    const inputArchivo = $('csArchivo');
    const lblArchivo   = $('csNombreArchivo');
    const btnValidar   = $('csBtnValidar');
    const btnLimpiar   = $('csBtnLimpiar');
    const btnAplicar   = $('csBtnAplicar');
    const btnCancelar  = $('csBtnCancelar');

    const panelResultado = $('csPanelResultado');
    const panelAplicado  = $('csPanelAplicado');

    if (!dropzone) return;

    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    function aviso(mensaje, tipo) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: tipo || 'info', text: mensaje, confirmButtonColor: '#0d6efd' });
        } else {
            alert(mensaje);
        }
    }

    // ── Selección de archivo ────────────────────────────────────────────────
    dropzone.addEventListener('click', () => inputArchivo.click());

    ['dragenter', 'dragover'].forEach((ev) => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.add('cs-activa');
        });
    });
    ['dragleave', 'drop'].forEach((ev) => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.remove('cs-activa');
        });
    });
    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            tomarArchivo(e.dataTransfer.files[0]);
        }
    });

    inputArchivo.addEventListener('change', () => {
        if (inputArchivo.files && inputArchivo.files[0]) tomarArchivo(inputArchivo.files[0]);
    });

    function tomarArchivo(file) {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ext !== 'xlsx' && ext !== 'xls') {
            aviso('El archivo debe ser un Excel (.xlsx).', 'warning');
            return;
        }
        archivoSeleccionado = file;
        lblArchivo.innerHTML = '<i class="bi bi-file-earmark-excel text-success me-1"></i>' + esc(file.name);
        btnValidar.disabled = false;
        ocultarPaneles();
    }

    btnLimpiar.addEventListener('click', limpiar);

    function limpiar() {
        archivoSeleccionado = null;
        inputArchivo.value = '';
        lblArchivo.textContent = 'Arrastre el archivo aquí o haga clic para seleccionarlo';
        btnValidar.disabled = true;
        ocultarPaneles();
        descartarToken();
    }

    function ocultarPaneles() {
        panelResultado.classList.add('d-none');
        panelAplicado.classList.add('d-none');
    }

    function descartarToken() {
        if (!tokenCarga) return;
        const fd = new FormData();
        fd.append('token', tokenCarga);
        fetch(`${URL_BASE}/cancelarAjax`, { method: 'POST', body: fd }).catch(() => {});
        tokenCarga = null;
    }

    // ── Paso 1: validar ─────────────────────────────────────────────────────
    btnValidar.addEventListener('click', async () => {
        if (!archivoSeleccionado) return;

        descartarToken();
        ocultarPaneles();
        cargando(btnValidar, true, 'Revisando...');

        try {
            const fd = new FormData();
            fd.append('archivo', archivoSeleccionado);

            const resp = await fetch(`${URL_BASE}/validarAjax`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (json.error) {
                aviso(json.error, 'error');
                return;
            }

            tokenCarga = json.token || null;
            pintarInforme(json.informe || {}, !!json.ok);
        } catch (e) {
            aviso('Error de conexión al revisar el archivo.', 'error');
        } finally {
            cargando(btnValidar, false, '<i class="bi bi-search me-1"></i> Revisar archivo');
        }
    });

    function pintarInforme(informe, hayToken) {
        const r = informe.resumen || {};
        const globales = informe.errores_globales || [];

        panelResultado.classList.remove('d-none');

        $('csErroresGlobales').innerHTML = globales.length
            ? '<div class="alert alert-danger py-2 px-3 cs-msg mb-3">'
              + globales.map((e) => '<div><i class="bi bi-x-octagon-fill me-1"></i>' + esc(e) + '</div>').join('')
              + '</div>'
            : '';

        const kpis = [
            { n: r.crear || 0,           l: 'A crear',       c: 'success' },
            { n: r.bloqueados || 0,      l: 'Bloqueadas',    c: 'danger'  },
            { n: r.filas_con_error || 0, l: 'Filas c/error', c: 'danger'  },
            { n: r.con_aviso || 0,       l: 'Avisos',        c: 'warning' }
        ];
        $('csKpis').innerHTML = globales.length ? '' : kpis.map((k) =>
            `<div class="cs-kpi bg-${k.c} bg-opacity-10 border border-${k.c} border-opacity-25">
                <div class="cs-kpi-num text-${k.c}">${k.n}</div>
                <div class="cs-kpi-lbl text-${k.c}">${k.l}</div>
             </div>`
        ).join('');

        const aplicables = r.crear || 0;
        const bloqueados = r.bloqueados || 0;

        let subtitulo;
        if (globales.length) {
            subtitulo = 'El archivo no se puede procesar. Corrija lo indicado y vuelva a subirlo.';
        } else if (bloqueados > 0) {
            subtitulo = `Se crearán ${aplicables} suscripción(es). Las ${bloqueados} con errores se omitirán.`;
        } else {
            subtitulo = `Todo correcto: se crearán ${aplicables} suscripción(es).`;
        }
        $('csSubtituloResultado').textContent = subtitulo;

        const puedeAplicar = hayToken && !globales.length && aplicables > 0;
        $('csAcciones').classList.toggle('d-none', !hayToken || !!globales.length);
        btnAplicar.disabled = !puedeAplicar;

        const filas = informe.filas || [];
        const wrap = $('csDetalleWrap');
        if (filas.length) {
            wrap.classList.remove('d-none');
            $('csDetalleBody').innerHTML = filas.map((f) => {
                const msgs = []
                    .concat((f.errores || []).map((m) => ({ m, tipo: 'error' })))
                    .concat((f.avisos  || []).map((m) => ({ m, tipo: 'aviso' })));
                return msgs.map((x) => `
                    <tr>
                        <td class="small text-muted">${esc(f.hoja)}</td>
                        <td class="small">${esc(f.fila)}</td>
                        <td class="small fw-semibold">${esc(f.codigo)}</td>
                        <td><span class="badge bg-${x.tipo === 'error' ? 'danger' : 'warning'} bg-opacity-10 text-${x.tipo === 'error' ? 'danger' : 'warning'} border border-${x.tipo === 'error' ? 'danger' : 'warning'} border-opacity-25" style="font-size:.62rem;">${x.tipo === 'error' ? 'ERROR' : 'AVISO'}</span></td>
                        <td class="small">${esc(x.m)}</td>
                    </tr>`).join('');
            }).join('');
            $('csRecortado').classList.toggle('d-none', !informe.recortado);
        } else {
            wrap.classList.add('d-none');
            $('csDetalleBody').innerHTML = '';
        }
    }

    // ── Paso 2: aplicar ─────────────────────────────────────────────────────
    btnAplicar.addEventListener('click', async () => {
        if (!tokenCarga) return;

        const confirmar = typeof Swal !== 'undefined'
            ? (await Swal.fire({
                title: '¿Crear las suscripciones?',
                text: 'Se crearán las suscripciones en la empresa activa.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, crear',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754'
            })).isConfirmed
            : confirm('¿Crear las suscripciones?');

        if (!confirmar) return;

        cargando(btnAplicar, true, 'Creando...');
        try {
            const fd = new FormData();
            fd.append('token', tokenCarga);

            const resp = await fetch(`${URL_BASE}/aplicarAjax`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (!json.ok) {
                aviso(json.error || 'No se pudo aplicar la carga.', 'error');
                return;
            }

            tokenCarga = null;
            pintarAplicado(json.resultado || {});
        } catch (e) {
            aviso('Error de conexión al aplicar la carga.', 'error');
        } finally {
            cargando(btnAplicar, false, '<i class="bi bi-check2-circle me-1"></i> Crear suscripciones');
        }
    });

    btnCancelar.addEventListener('click', limpiar);

    function pintarAplicado(res) {
        panelResultado.classList.add('d-none');
        panelAplicado.classList.remove('d-none');

        const kpis = [
            { n: res.creadas || 0,  l: 'Creadas',  c: 'success' },
            { n: res.omitidas || 0, l: 'Omitidas', c: 'secondary' },
            { n: res.fallidas || 0, l: 'Fallidas', c: 'danger' }
        ];
        $('csKpisAplicado').innerHTML = kpis.map((k) =>
            `<div class="cs-kpi bg-${k.c} bg-opacity-10 border border-${k.c} border-opacity-25">
                <div class="cs-kpi-num text-${k.c}">${k.n}</div>
                <div class="cs-kpi-lbl text-${k.c}">${k.l}</div>
             </div>`
        ).join('');

        const fallos = (res.detalle || []).filter((d) => d.estado === 'error');
        $('csErroresAplicado').innerHTML = fallos.length
            ? '<div class="alert alert-danger py-2 px-3 cs-msg mb-0">'
              + '<div class="fw-semibold mb-1">Suscripciones que no se pudieron crear:</div>'
              + fallos.map((d) => `<div>${esc(d.codigo)}: ${esc(d.mensaje)}</div>`).join('')
              + '</div>'
            : '';

        limpiarSeleccion();
    }

    function limpiarSeleccion() {
        archivoSeleccionado = null;
        inputArchivo.value = '';
        lblArchivo.textContent = 'Arrastre el archivo aquí o haga clic para seleccionarlo';
        btnValidar.disabled = true;
    }

    function cargando(btn, activo, html) {
        btn.disabled = activo;
        btn.innerHTML = activo
            ? `<span class="spinner-border spinner-border-sm me-1"></span>${html}`
            : html;
    }
})();
