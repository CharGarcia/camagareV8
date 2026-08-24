/**
 * Carga masiva de facturas de venta desde Excel.
 *
 * Flujo: seleccionar archivo -> "Revisar" (valida sin escribir) -> "Crear facturas".
 * El archivo queda en el servidor entre ambos pasos, identificado por un token.
 */
(function () {
    'use strict';

    const URL_BASE = window.CF_URL_BASE || '';

    let archivoSeleccionado = null;
    let tokenCarga = null;

    const $ = (id) => document.getElementById(id);

    const dropzone     = $('cfDropzone');
    const inputArchivo = $('cfArchivo');
    const lblArchivo   = $('cfNombreArchivo');
    const btnValidar   = $('cfBtnValidar');
    const btnLimpiar   = $('cfBtnLimpiar');
    const btnAplicar   = $('cfBtnAplicar');
    const btnCancelar  = $('cfBtnCancelar');

    const panelResultado = $('cfPanelResultado');
    const panelAplicado  = $('cfPanelAplicado');

    // Sin permiso de crear la vista no pinta la zona de subida.
    if (!dropzone) return;

    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    const money = (n) => (Number(n) || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });

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
            dropzone.classList.add('cf-activa');
        });
    });
    ['dragleave', 'drop'].forEach((ev) => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.remove('cf-activa');
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
        limpiarSeleccion();
        ocultarPaneles();
        descartarToken();
    }

    function limpiarSeleccion() {
        archivoSeleccionado = null;
        inputArchivo.value = '';
        lblArchivo.textContent = 'Arrastre el archivo aquí o haga clic para seleccionarlo';
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

        // Errores que invalidan el archivo entero (hojas borradas, otra empresa...).
        $('cfErroresGlobales').innerHTML = globales.length
            ? '<div class="alert alert-danger py-2 px-3 cf-msg mb-3">'
              + globales.map((e) => '<div><i class="bi bi-x-octagon-fill me-1"></i>' + esc(e) + '</div>').join('')
              + '</div>'
            : '';

        const kpis = [
            { n: r.aplicables || 0,        l: 'A crear',      c: 'success' },
            { n: r.bloqueadas || 0,        l: 'Bloqueadas',   c: 'danger'  },
            { n: r.productos_nuevos || 0,  l: 'Ítems nuevos', c: 'info' },
            { n: r.con_aviso || 0,         l: 'Avisos',       c: 'warning' },
            { n: '$' + money(r.total_general), l: 'Total a facturar', c: 'primary' }
        ];
        $('cfKpis').innerHTML = globales.length ? '' : kpis.map((k) =>
            `<div class="cf-kpi bg-${k.c} bg-opacity-10 border border-${k.c} border-opacity-25">
                <div class="cf-kpi-num text-${k.c}">${k.n}</div>
                <div class="cf-kpi-lbl text-${k.c}">${k.l}</div>
             </div>`
        ).join('');

        const aplicables = r.aplicables || 0;
        const bloqueadas = r.bloqueadas || 0;

        let subtitulo;
        if (globales.length) {
            subtitulo = 'El archivo no se puede procesar. Corrija lo indicado y vuelva a subirlo.';
        } else if (bloqueadas > 0) {
            subtitulo = `Se crearán ${aplicables} factura(s) en borrador. Las ${bloqueadas} con errores se omitirán.`;
        } else {
            subtitulo = `Todo correcto: se crearán ${aplicables} factura(s) en borrador.`;
        }
        $('cfSubtituloResultado').textContent = subtitulo;

        // Solo se puede aplicar si hay token y algo que aplicar.
        const puedeAplicar = hayToken && !globales.length && aplicables > 0;
        $('cfAcciones').classList.toggle('d-none', !hayToken || !!globales.length);
        btnAplicar.disabled = !puedeAplicar;

        pintarFacturas(informe);
        pintarProblemas(informe);
    }

    // La forma de pago no está en el archivo: sale de la ficha del cliente o de
    // la configuración del establecimiento. Mostrar su procedencia evita que el
    // usuario tenga que adivinar con qué se va a facturar.
    const ORIGEN_PAGO = {
        cliente:         { texto: 'del cliente',   color: 'primary' },
        establecimiento: { texto: 'de la empresa', color: 'info'    }
    };

    function pagoHtml(f) {
        if (!f.forma_pago) return '<span class="text-muted">—</span>';
        const o = ORIGEN_PAGO[f.pago_origen] || { texto: '', color: 'secondary' };
        return `<span class="fw-semibold">${esc(f.forma_pago)}</span>`
             + `<span class="badge bg-${o.color} bg-opacity-10 text-${o.color} border border-${o.color} border-opacity-25 ms-1" style="font-size:.6rem;">${esc(o.texto)}</span>`;
    }

    function pintarFacturas(informe) {
        const facturas = informe.facturas || [];

        $('cfFacturasBody').innerHTML = facturas.length
            ? facturas.map((f) => `
                <tr class="${f.bloqueada ? 'table-danger' : ''}">
                    <td class="small fw-semibold">${esc(f.clave)}</td>
                    <td class="small text-muted">${esc(f.fila)}</td>
                    <td class="small">${esc(f.fecha)}</td>
                    <td class="small">
                        ${esc(f.cliente)}
                        ${f.cliente_nombre ? `<div class="text-muted text-truncate" style="font-size:.68rem;max-width:220px;" title="${esc(f.cliente_nombre)}">${esc(f.cliente_nombre)}</div>` : ''}
                    </td>
                    <td class="small">${esc(f.punto)}</td>
                    <td class="small text-end">${esc(f.lineas)}</td>
                    <td class="small text-end">${money(f.total)}</td>
                    <td class="small">${pagoHtml(f)}</td>
                    <td>
                        <span class="badge bg-${f.bloqueada ? 'danger' : 'success'} bg-opacity-10 text-${f.bloqueada ? 'danger' : 'success'} border border-${f.bloqueada ? 'danger' : 'success'} border-opacity-25" style="font-size:.62rem;">
                            ${f.bloqueada ? 'BLOQUEADA' : 'SE CREARÁ'}
                        </span>
                    </td>
                </tr>`).join('')
            : '<tr><td colspan="9" class="text-center text-muted small py-3">Sin facturas en el archivo.</td></tr>';

        $('cfRecortadoFacturas').classList.toggle('d-none', !informe.recortado_facturas);
    }

    function pintarProblemas(informe) {
        const filas = informe.filas || [];
        const badge = $('cfBadgeProblemas');

        let total = 0;
        const html = filas.map((f) => {
            const msgs = []
                .concat((f.errores || []).map((m) => ({ m, tipo: 'error' })))
                .concat((f.avisos  || []).map((m) => ({ m, tipo: 'aviso' })));
            total += msgs.length;
            return msgs.map((x) => `
                <tr>
                    <td class="small text-muted">${esc(f.hoja)}</td>
                    <td class="small">${esc(f.fila)}</td>
                    <td class="small fw-semibold">${esc(f.clave)}</td>
                    <td><span class="badge bg-${x.tipo === 'error' ? 'danger' : 'warning'} bg-opacity-10 text-${x.tipo === 'error' ? 'danger' : 'warning'} border border-${x.tipo === 'error' ? 'danger' : 'warning'} border-opacity-25" style="font-size:.62rem;">${x.tipo === 'error' ? 'ERROR' : 'AVISO'}</span></td>
                    <td class="small">${esc(x.m)}</td>
                </tr>`).join('');
        }).join('');

        $('cfDetalleBody').innerHTML = html
            || '<tr><td colspan="5" class="text-center text-muted small py-3">Sin problemas detectados.</td></tr>';

        badge.textContent = total;
        badge.classList.toggle('d-none', total === 0);
        $('cfRecortado').classList.toggle('d-none', !informe.recortado);
    }

    // ── Paso 2: aplicar ─────────────────────────────────────────────────────
    btnAplicar.addEventListener('click', async () => {
        if (!tokenCarga) return;

        const confirmar = typeof Swal !== 'undefined'
            ? (await Swal.fire({
                title: '¿Crear las facturas?',
                text: 'Se crearán en estado borrador, tomando el siguiente secuencial de cada punto de emisión. No se enviará nada al SRI.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, crear',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754'
            })).isConfirmed
            : confirm('¿Crear las facturas?');

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
            aviso('Error de conexión al crear las facturas.', 'error');
        } finally {
            cargando(btnAplicar, false, '<i class="bi bi-check2-circle me-1"></i> Crear facturas');
        }
    });

    btnCancelar.addEventListener('click', limpiar);

    function pintarAplicado(res) {
        panelResultado.classList.add('d-none');
        panelAplicado.classList.remove('d-none');

        const kpis = [
            { n: res.creadas || 0,           l: 'Creadas',           c: 'success' },
            { n: res.omitidas || 0,          l: 'Omitidas',          c: 'secondary' },
            { n: res.fallidas || 0,          l: 'Fallidas',          c: 'danger' },
            { n: res.productos_creados || 0, l: 'Ítems creados', c: 'info' },
            { n: '$' + money(res.total_facturado), l: 'Total facturado', c: 'primary' }
        ];
        $('cfKpisAplicado').innerHTML = kpis.map((k) =>
            `<div class="cf-kpi bg-${k.c} bg-opacity-10 border border-${k.c} border-opacity-25">
                <div class="cf-kpi-num text-${k.c}">${k.n}</div>
                <div class="cf-kpi-lbl text-${k.c}">${k.l}</div>
             </div>`
        ).join('');

        const detalle = res.detalle || [];

        const creadas = detalle.filter((d) => d.estado === 'creada');
        $('cfCreadasWrap').classList.toggle('d-none', creadas.length === 0);
        $('cfCreadasBody').innerHTML = creadas.map((d) => `
            <tr>
                <td class="small fw-semibold">${esc(d.clave)}</td>
                <td class="small">${esc(d.numero)}</td>
            </tr>`).join('');

        const fallos = detalle.filter((d) => d.estado === 'error');
        $('cfErroresAplicado').innerHTML = fallos.length
            ? '<div class="alert alert-danger py-2 px-3 cf-msg mb-0">'
              + '<div class="fw-semibold mb-1">No se pudieron crear:</div>'
              + fallos.map((d) => `<div>${esc(d.clave)}: ${esc(d.mensaje)}</div>`).join('')
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
