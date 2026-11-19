/**
 * Carga masiva de clientes desde Excel.
 *
 * Flujo: seleccionar archivo -> "Revisar" (valida sin escribir) -> "Aplicar".
 * El archivo queda en el servidor entre ambos pasos, identificado por un token
 * que el servidor invalida al aplicar (un segundo clic no aplica dos veces).
 */
(function () {
    'use strict';

    const URL_BASE = window.CC_URL_BASE || '';

    let archivoSeleccionado = null;
    let tokenCarga = null;
    let enCurso = false;

    const $ = (id) => document.getElementById(id);

    const dropzone     = $('ccDropzone');
    const inputArchivo = $('ccArchivo');
    const lblArchivo   = $('ccNombreArchivo');
    const btnValidar   = $('ccBtnValidar');
    const btnLimpiar   = $('ccBtnLimpiar');
    const btnAplicar   = $('ccBtnAplicar');
    const btnCancelar  = $('ccBtnCancelar');

    const panelResultado = $('ccPanelResultado');
    const panelAplicado  = $('ccPanelAplicado');
    const panelColumnas  = $('ccPanelColumnas');

    // Sin permiso de escritura la vista no pinta la zona de subida.
    if (!dropzone) return;

    const TEXTO_DROPZONE = 'Arrastre el archivo aquí o haga clic para seleccionarlo';

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

    function kpisHtml(kpis) {
        return kpis.map((k) =>
            `<div class="cc-kpi bg-${k.c} bg-opacity-10 border border-${k.c} border-opacity-25">
                <div class="cc-kpi-num text-${k.c}">${k.n}</div>
                <div class="cc-kpi-lbl text-${k.c}">${k.l}</div>
             </div>`
        ).join('');
    }

    // ── Selección de archivo ────────────────────────────────────────────────
    dropzone.addEventListener('click', () => inputArchivo.click());

    ['dragenter', 'dragover'].forEach((ev) => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.add('cc-activa');
        });
    });
    ['dragleave', 'drop'].forEach((ev) => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.remove('cc-activa');
        });
    });
    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files && e.dataTransfer.files[0]) tomarArchivo(e.dataTransfer.files[0]);
    });
    inputArchivo.addEventListener('change', () => {
        if (inputArchivo.files && inputArchivo.files[0]) tomarArchivo(inputArchivo.files[0]);
    });

    function tomarArchivo(file) {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ext !== 'xlsx') {
            aviso('El archivo debe ser un Excel (.xlsx) descargado de este módulo.', 'warning');
            return;
        }
        archivoSeleccionado = file;
        lblArchivo.innerHTML = '<i class="bi bi-file-earmark-excel text-success me-1"></i>' + esc(file.name);
        btnValidar.disabled = false;
        ocultarPaneles();
        descartarToken();
    }

    // ── Modo: carga completa o solo algunas columnas ────────────────────────
    function modoActual() {
        const r = document.querySelector('input[name="ccModo"]:checked');
        return r ? r.value : 'completa';
    }

    function columnasMarcadas() {
        return Array.from(document.querySelectorAll('.cc-col:checked')).map((c) => c.value);
    }

    // Cambiar la elección invalida una revisión ya hecha con otra.
    function eleccionCambiada() {
        if (tokenCarga || !panelResultado.classList.contains('d-none')) {
            ocultarPaneles();
            descartarToken();
        }
    }

    document.querySelectorAll('input[name="ccModo"]').forEach((r) => {
        r.addEventListener('change', () => {
            panelColumnas.classList.toggle('d-none', modoActual() !== 'parcial');
            eleccionCambiada();
        });
    });
    document.querySelectorAll('.cc-col').forEach((c) => c.addEventListener('change', eleccionCambiada));
    document.querySelectorAll('[data-cc-marcar]').forEach((b) => {
        b.addEventListener('click', () => {
            const valor = b.dataset.ccMarcar === '1';
            document.querySelectorAll('.cc-col').forEach((c) => { c.checked = valor; });
            eleccionCambiada();
        });
    });

    btnLimpiar.addEventListener('click', limpiar);
    btnCancelar.addEventListener('click', limpiar);

    function limpiar() {
        limpiarSeleccion();
        ocultarPaneles();
        descartarToken();
    }

    function limpiarSeleccion() {
        archivoSeleccionado = null;
        inputArchivo.value = '';
        lblArchivo.textContent = TEXTO_DROPZONE;
        btnValidar.disabled = true;
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

    // ── Paso 1: revisar ─────────────────────────────────────────────────────
    btnValidar.addEventListener('click', async () => {
        if (!archivoSeleccionado || enCurso) return;

        const modo = modoActual();
        const columnas = columnasMarcadas();
        if (modo === 'parcial' && !columnas.length) {
            aviso('Marque al menos una columna para actualizar.', 'warning');
            return;
        }

        enCurso = true;
        descartarToken();
        ocultarPaneles();
        cargando(btnValidar, true, 'Revisando...');

        try {
            const fd = new FormData();
            fd.append('archivo', archivoSeleccionado);
            fd.append('modo', modo);
            if (modo === 'parcial') columnas.forEach((c) => fd.append('columnas[]', c));

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
            enCurso = false;
            cargando(btnValidar, false, '<i class="bi bi-search me-1"></i> Revisar archivo');
        }
    });

    function pintarInforme(informe, hayToken) {
        const r = informe.resumen || {};
        const globales = informe.errores_globales || [];

        panelResultado.classList.remove('d-none');

        $('ccErroresGlobales').innerHTML = globales.length
            ? '<div class="alert alert-danger py-2 px-3 cc-msg mb-3">'
              + globales.map((e) => '<div><i class="bi bi-x-octagon-fill me-1"></i>' + esc(e) + '</div>').join('')
              + '</div>'
            : '';

        $('ccKpis').innerHTML = globales.length ? '' : kpisHtml([
            { n: r.crear || 0,       l: 'A crear',      c: 'success' },
            { n: r.actualizar || 0,  l: 'A actualizar', c: 'primary' },
            { n: r.sin_cambios || 0, l: 'Sin cambios',  c: 'secondary' },
            { n: r.bloqueados || 0,  l: 'Con errores',  c: 'danger' },
            { n: r.con_aviso || 0,   l: 'Avisos',       c: 'warning' }
        ]);

        const aplicables = (r.crear || 0) + (r.actualizar || 0);
        const bloqueados = r.bloqueados || 0;

        let subtitulo;
        if (globales.length) {
            subtitulo = 'El archivo no se puede procesar. Corrija lo indicado y vuelva a subirlo.';
        } else if (aplicables === 0) {
            subtitulo = bloqueados > 0
                ? `No hay cambios que aplicar: las ${bloqueados} fila(s) con cambios tienen errores.`
                : 'No hay cambios que aplicar: el archivo es igual a lo que ya está registrado.';
        } else if (bloqueados > 0) {
            subtitulo = `Se aplicarán ${aplicables} cliente(s). Las ${bloqueados} fila(s) con errores se omitirán.`;
        } else {
            subtitulo = `Todo correcto: se aplicarán ${aplicables} cliente(s).`;
        }
        if (!globales.length && informe.modo === 'parcial') {
            subtitulo += ' Solo se cambiarán las columnas marcadas.';
        }
        $('ccSubtituloResultado').textContent = subtitulo;

        $('ccAcciones').classList.toggle('d-none', !hayToken || !!globales.length);
        btnAplicar.disabled = !(hayToken && !globales.length && aplicables > 0);

        const filas = informe.filas || [];
        const wrap = $('ccDetalleWrap');
        if (filas.length) {
            wrap.classList.remove('d-none');
            $('ccDetalleBody').innerHTML = filas.map((f) => {
                const msgs = []
                    .concat((f.errores || []).map((m) => ({ m, tipo: 'error' })))
                    .concat((f.avisos  || []).map((m) => ({ m, tipo: 'aviso' })));
                return msgs.map((x) => {
                    const c = x.tipo === 'error' ? 'danger' : 'warning';
                    return `
                    <tr>
                        <td class="small">${esc(f.fila)}</td>
                        <td class="small fw-semibold">${esc(f.codigo)}</td>
                        <td class="small text-truncate" style="max-width:220px;" title="${esc(f.nombre)}">${esc(f.nombre)}</td>
                        <td><span class="badge bg-${c} bg-opacity-10 text-${c} border border-${c} border-opacity-25" style="font-size:.62rem;">${x.tipo === 'error' ? 'ERROR' : 'AVISO'}</span></td>
                        <td class="small">${esc(x.m)}</td>
                    </tr>`;
                }).join('');
            }).join('');
            $('ccRecortado').classList.toggle('d-none', !informe.recortado);
        } else {
            wrap.classList.add('d-none');
            $('ccDetalleBody').innerHTML = '';
        }
    }

    // ── Paso 2: aplicar ─────────────────────────────────────────────────────
    btnAplicar.addEventListener('click', async () => {
        if (!tokenCarga || enCurso) return;
        enCurso = true;

        try {
            const confirmar = typeof Swal !== 'undefined'
                ? (await Swal.fire({
                    title: '¿Aplicar los cambios?',
                    text: modoActual() === 'parcial'
                        ? 'Se actualizarán solo las columnas marcadas en la empresa activa.'
                        : 'Se crearán y actualizarán clientes en la empresa activa.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, aplicar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#198754'
                })).isConfirmed
                : confirm('¿Aplicar los cambios?');

            if (!confirmar) return;

            cargando(btnAplicar, true, 'Aplicando...');

            const fd = new FormData();
            fd.append('token', tokenCarga);
            // El servidor ya invalidó el token al recibirlo: no se reutiliza.
            tokenCarga = null;

            const resp = await fetch(`${URL_BASE}/aplicarAjax`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (!json.ok) {
                aviso(json.error || 'No se pudo aplicar la carga.', 'error');
                return;
            }
            pintarAplicado(json.resultado || {});
        } catch (e) {
            aviso('Se perdió la conexión al aplicar la carga. Revise el listado de Clientes antes de volver a subir el archivo: puede que ya se haya aplicado.', 'error');
        } finally {
            enCurso = false;
            cargando(btnAplicar, false, '<i class="bi bi-check2-circle me-1"></i> Aplicar cambios');
            btnAplicar.disabled = !tokenCarga;
        }
    });

    function pintarAplicado(res) {
        panelResultado.classList.add('d-none');
        panelAplicado.classList.remove('d-none');

        $('ccKpisAplicado').innerHTML = kpisHtml([
            { n: res.creados || 0,      l: 'Creados',      c: 'success' },
            { n: res.actualizados || 0, l: 'Actualizados', c: 'primary' },
            { n: res.sin_cambios || 0,  l: 'Sin cambios',  c: 'secondary' },
            { n: res.omitidos || 0,     l: 'Con errores',  c: 'warning' },
            { n: res.fallidos || 0,     l: 'Fallidos',     c: 'danger' }
        ]);

        const fallos = res.detalle || [];
        $('ccErroresAplicado').innerHTML = fallos.length
            ? '<div class="alert alert-danger py-2 px-3 cc-msg mb-0">'
              + '<div class="fw-semibold mb-1">Clientes que no se pudieron guardar:</div>'
              + fallos.map((d) => `<div>Fila ${esc(d.fila)} — ${esc(d.identificacion)} ${esc(d.nombre)}: ${esc(d.mensaje)}</div>`).join('')
              + '</div>'
            : '';

        limpiarSeleccion();
    }

    function cargando(btn, activo, html) {
        btn.disabled = activo;
        btn.innerHTML = activo
            ? `<span class="spinner-border spinner-border-sm me-1"></span>${html}`
            : html;
    }
})();
