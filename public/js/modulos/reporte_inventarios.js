// Módulo: Reporte de Inventarios (Existencias, Movimientos, Valorización, Consignaciones)
//
// Regla de la pantalla: ningún filtro consulta por su cuenta. Elegir un producto o
// cliente en el buscador, cambiar el Detalle o cambiar Año/Mes solo actualiza el
// formulario; los datos se piden únicamente al pulsar Mostrar (submit del form).
// Una consulta sin acotar recorre todo el inventario/kardex, y el usuario suele
// tocar varios filtros seguidos antes de querer verla.

// ════════════════════════════════════════════════════════════════════
// HELPERS COMPARTIDOS
// ════════════════════════════════════════════════════════════════════
function RI_paramsFromIds(map) {
    const params = new URLSearchParams();
    Object.keys(map).forEach(key => {
        const el = document.getElementById(map[key]);
        if (!el) return;
        const val = (el.type === 'checkbox') ? (el.checked ? '1' : '') : el.value;
        if (val !== '' && val !== null && val !== undefined) params.set(key, val);
    });
    return params;
}

function RI_setupAutocomplete(searchId, dropdownId, hiddenId, selectedLabelId, ajaxUrl, onSelect) {
    let timer;
    const search   = document.getElementById(searchId);
    const dropdown = document.getElementById(dropdownId);
    if (!search || !dropdown) return;

    search.addEventListener('input', function () {
        clearTimeout(timer);
        // Escribir invalida la selección anterior: si no, el hidden seguía con el id del
        // producto elegido antes y Mostrar filtraba por ese aunque el texto dijera otro.
        const hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = '';
        if (selectedLabelId) {
            const lbl = document.getElementById(selectedLabelId);
            if (lbl) lbl.textContent = '';
        }
        const q = this.value.trim();
        if (q.length < 2) { dropdown.classList.add('d-none'); return; }

        timer = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + ajaxUrl + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    const items = data.data || [];
                    if (items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            const nombre = item.nombre || '';
                            const sub = item.codigo || item.identificacion || '';
                            btn.innerHTML = `<strong>${nombre}</strong><br><small class="text-muted">${sub}</small>`;
                            btn.addEventListener('click', function () {
                                search.value = nombre;
                                dropdown.classList.add('d-none');
                                document.getElementById(hiddenId).value = item.id;
                                if (selectedLabelId) {
                                    const lbl = document.getElementById(selectedLabelId);
                                    if (lbl) lbl.textContent = nombre;
                                }
                                if (onSelect) onSelect();
                            });
                            dropdown.appendChild(btn);
                        });
                        dropdown.classList.remove('d-none');
                    } else {
                        dropdown.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
                        dropdown.classList.remove('d-none');
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!search.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('d-none');
    });
}

function RI_limpiarBusqueda(searchId, hiddenId, selectedLabelId) {
    document.getElementById(searchId).value = '';
    document.getElementById(hiddenId).value = '';
    if (selectedLabelId) {
        const lbl = document.getElementById(selectedLabelId);
        if (lbl) lbl.textContent = '';
    }
}

const RI_PALETA = [
    'rgba(13,110,253,.7)', 'rgba(220,53,69,.7)', 'rgba(25,135,84,.7)',
    'rgba(255,193,7,.7)', 'rgba(13,202,240,.7)', 'rgba(111,66,193,.7)',
    'rgba(253,126,20,.7)', 'rgba(32,201,151,.7)', 'rgba(214,51,132,.7)',
    'rgba(108,117,125,.7)',
];
function RI_colores(n) {
    return Array.from({ length: n }, (_, i) => RI_PALETA[i % RI_PALETA.length]);
}

// Última búsqueda de cada pestaña. Si se vuelve a pulsar Mostrar (o se cambia Detalle,
// Año o Mes) antes de que responda la anterior, esa anterior se cancela y su respuesta se
// ignora: si no, la que llegara última pisaría la tabla aunque fuera la más vieja.
const RI_busquedas = {};

// ════════════════════════════════════════════════════════════════════
// INDICADOR DE CARGA (bloqueo del botón Mostrar + barra de progreso)
// ════════════════════════════════════════════════════════════════════
/**
 * Mientras una pestaña genera su reporte, su botón "Mostrar" queda DESHABILITADO —
 * una consulta de este módulo puede tardar segundos y, sin bloquearlo, el usuario
 * encadenaba clics y lanzaba varias consultas pesadas seguidas — y la tabla muestra
 * un mensaje con barra de progreso y porcentaje. Con él se bloquean también el PDF,
 * el Excel y el "Corregir todo" de esa pestaña (ver _btnAcciones).
 *
 * El porcentaje son dos tramos, porque las dos mitades de la espera se miden distinto:
 *
 *   0 – 80 %   "Consultando la base de datos". El servidor arma la respuesta entera
 *              antes de enviar nada (ob_gzhandler la bufferiza), así que aquí NO hay
 *              avance real que leer: es una ESTIMACIÓN por tiempo, tomando como
 *              referencia lo que tardó la última consulta de esa misma pestaña
 *              (guardada en localStorage; 4 s la primera vez). Hasta ese tiempo la
 *              barra avanza lineal hasta 70 %; si se pasa, sigue avanzando cada vez
 *              más lento y nunca llega al tramo siguiente. Al lado van los segundos
 *              transcurridos, que sí son un dato exacto.
 *   80 – 99 %  "Recibiendo datos". Aquí el porcentaje SÍ es real: bytes leídos del
 *              stream contra el total que el servidor anuncia en la cabecera
 *              X-Json-Bytes. No se usa Content-Length porque la respuesta viaja
 *              comprimida y ese valor mide el gzip, no los bytes que el navegador va
 *              entregando (5.000 filas son ~5 MB de JSON y unos cientos de KB de gzip).
 *
 * La barra nunca retrocede y no llega a 100: la tabla con los datos la reemplaza.
 */
const RI_CARGA_ESPERA_DEFECTO = 4000;   // ms de referencia hasta tener una medición propia

const RI_Cargando = {
    _tabs: {},

    _btnMostrar(prefijo) {
        return document.querySelector('#' + prefijo + '-form button[type="submit"]');
    },

    /** Las demás acciones de la pestaña que tampoco deben poder dispararse mientras
     *  se genera: PDF, Excel y, en Auditoría, "Corregir todo" (que además estaría
     *  operando sobre el resultado viejo). Se marcan en la vista con data-ri-accion. */
    _btnAcciones(prefijo) {
        return document.querySelectorAll('[data-ri-accion="' + prefijo + '"]');
    },

    /** Lo que tardó la última consulta de esta pestaña. localStorage puede fallar
     *  (modo privado) o traer basura: ante cualquier duda, el valor por defecto. */
    _esperaDe(tab) {
        let ms = 0;
        try { ms = parseInt(localStorage.getItem('ri_espera_' + tab) || '0', 10); } catch (e) { ms = 0; }
        return (ms >= 150 && ms <= 600000) ? ms : RI_CARGA_ESPERA_DEFECTO;
    },

    _recordarEspera(tab, ms) {
        try { localStorage.setItem('ri_espera_' + tab, String(Math.round(ms))); } catch (e) { /* sin persistencia */ }
    },

    _fmtBytes(n) {
        if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
        if (n >= 1024) return Math.round(n / 1024) + ' KB';
        return n + ' B';
    },

    iniciar(tab, ui) {
        this.terminar(tab);   // por si quedó vivo el intervalo de una consulta cancelada

        const st = {
            prefijo: ui.prefijo, inicio: Date.now(), espera: this._esperaDe(tab),
            pct: 0, bytes: 0, total: 0, recibiendo: false, msConsulta: 0, timer: null, pintado: false,
        };
        this._tabs[tab] = st;

        const btn = this._btnMostrar(ui.prefijo);
        if (btn) {
            btn.dataset.riHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Cargando';
        }
        // "Corregir todo" puede venir ya deshabilitado por su propia corrección en curso:
        // se recuerda su estado para devolvérselo y no habilitarlo por accidente.
        this._btnAcciones(ui.prefijo).forEach(b => {
            b.dataset.riOff = b.disabled ? '1' : '';
            b.disabled = true;
        });

        const tbody = document.getElementById(ui.prefijo + '-tbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="${ui.colSpan}" class="py-5">
                <div class="mx-auto text-center" style="max-width:340px;">
                    <div class="fw-semibold mb-1"><i class="bi bi-hourglass-split me-1"></i>Generando el reporte…</div>
                    <div class="small text-muted mb-2" id="${ui.prefijo}-carga-sub">Consultando la base de datos</div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             id="${ui.prefijo}-carga-barra" role="progressbar"
                             aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%"></div>
                    </div>
                    <div class="small text-muted mt-2"><span id="${ui.prefijo}-carga-pct">0</span> %</div>
                </div></td></tr>`;
            st.pintado = true;
        }

        st.timer = setInterval(() => this._tick(tab), 150);
        this._tick(tab);
    },

    /** Llegaron las cabeceras: termina el tramo estimado y empieza el real.
     *  $bytes es lo que el servidor dice que pesa el JSON sin comprimir (0 = no lo dijo). */
    recibiendo(tab, bytes) {
        const st = this._tabs[tab];
        if (!st) return;
        st.recibiendo = true;
        st.total = (bytes > 0) ? bytes : 0;
        st.msConsulta = Date.now() - st.inicio;   // lo que tardó de verdad la consulta
    },

    bytes(tab, leidos) {
        const st = this._tabs[tab];
        if (!st) return;
        st.bytes = leidos;
        // Si el total anunciado se queda corto, algo no cuadra (una cabecera vieja en
        // caché, por ejemplo): mejor quedarse sin % que mostrar uno imposible.
        if (st.total > 0 && leidos > st.total) st.total = 0;
    },

    _tick(tab) {
        const st = this._tabs[tab];
        if (!st) return;
        const t = Date.now() - st.inicio;

        let pct;
        let sub;
        if (st.recibiendo) {
            if (st.total > 0) {
                pct = 80 + 19 * Math.min(1, st.bytes / st.total);
                sub = `Recibiendo datos · ${this._fmtBytes(st.bytes)} de ${this._fmtBytes(st.total)}`;
            } else {
                pct = Math.max(st.pct, 80);
                sub = `Recibiendo datos · ${this._fmtBytes(st.bytes)}`;
            }
        } else {
            pct = (t < st.espera)
                ? 70 * (t / st.espera)
                : 70 + 10 * (1 - Math.exp(-(t - st.espera) / st.espera));
            sub = `Consultando la base de datos · ${(t / 1000).toFixed(1)} s`;
        }
        st.pct = Math.max(st.pct, pct);

        const barra = document.getElementById(st.prefijo + '-carga-barra');
        const txt   = document.getElementById(st.prefijo + '-carga-pct');
        const subEl = document.getElementById(st.prefijo + '-carga-sub');
        // Si la tabla ya se repintó, no hay nada que animar (y el botón debe volver).
        if (!barra) { if (st.pintado) this.terminar(tab); return; }
        barra.style.width = st.pct.toFixed(1) + '%';
        barra.setAttribute('aria-valuenow', Math.round(st.pct));
        if (txt) txt.textContent = Math.round(st.pct);
        if (subEl) subEl.textContent = sub;
    },

    /** Devuelve los botones de la pestaña a su estado normal. Con exito = true guarda cuánto tardó la
     *  consulta, que es la referencia del tramo estimado de la próxima vez. */
    terminar(tab, exito) {
        const st = this._tabs[tab];
        if (!st) return;
        if (st.timer) clearInterval(st.timer);
        if (exito && st.msConsulta > 0) this._recordarEspera(tab, st.msConsulta);

        const btn = this._btnMostrar(st.prefijo);
        if (btn) {
            btn.disabled = false;
            if (btn.dataset.riHtml) {
                btn.innerHTML = btn.dataset.riHtml;
                delete btn.dataset.riHtml;
            }
        }
        this._btnAcciones(st.prefijo).forEach(b => {
            b.disabled = b.dataset.riOff === '1';
            delete b.dataset.riOff;
        });
        delete this._tabs[tab];
    },
};

/**
 * Lanza la consulta de una pestaña. `ui` ({ prefijo, colSpan }) es opcional: cuando
 * viene, el botón Mostrar de esa pestaña se bloquea y la tabla muestra la barra de
 * progreso hasta que llegan los datos (ver RI_Cargando).
 */
function RI_fetchGenerar(tab, params, onOk, onError, ui) {
    params.set('tab', tab);
    if (RI_busquedas[tab]) RI_busquedas[tab].abort();
    const control = new AbortController();
    RI_busquedas[tab] = control;
    const vigente = () => RI_busquedas[tab] === control;

    if (ui) RI_Cargando.iniciar(tab, ui);

    fetch(BASE_URL + '/' + RUTA_MODULO + '/generarAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: params.toString(),
        signal: control.signal,
    })
    .then(async response => {
        // Se lee el cuerpo por trozos para poder mostrar el avance real de la descarga.
        // El total sale de X-Json-Bytes (bytes del JSON sin comprimir); Content-Length
        // mide el gzip y no sirve como referencia de lo que el navegador va recibiendo.
        RI_Cargando.recibiendo(tab, parseInt(response.headers.get('X-Json-Bytes') || '0', 10));
        if (!response.body || !response.body.getReader) return response.text();

        const reader = response.body.getReader();
        const dec = new TextDecoder('utf-8');
        let text = '';
        let leidos = 0;
        for (;;) {
            const { done, value } = await reader.read();
            if (done) break;
            leidos += value.length;
            text += dec.decode(value, { stream: true });
            RI_Cargando.bytes(tab, leidos);
        }
        return text + dec.decode();
    })
    .then(text => {
        try { return JSON.parse(text); }
        catch (e) { throw new Error(String(text).substring(0, 200)); }
    })
    .then(res => {
        if (!vigente()) return;
        RI_Cargando.terminar(tab, true);
        if (res.ok) onOk(res);
        else onError(res.error || 'Ocurrió un error al generar el reporte');
    })
    .catch(err => {
        if (err.name === 'AbortError' || !vigente()) return;
        RI_Cargando.terminar(tab);
        console.error(err);
        onError(err.message);
    })
    .finally(() => { if (vigente()) delete RI_busquedas[tab]; });
}

/**
 * Descarga el Excel o el PDF de una pestaña con CMG_descargar (app.js): aviso "Generando…"
 * y, si el reporte excede lo que el servidor puede armar (ReporteInventariosController::
 * bloquearExportPorVolumen), la explicación en un aviso en lugar de una pestaña en blanco.
 * 'consignacion' / 'consignacion-pdf' = Excel / PDF de un solo documento (botones del modal
 * de detalle de Consignaciones).
 */
function RI_descargarExport(formato, params) {
    const esPdf = formato === 'pdf' || formato === 'consignacion-pdf';
    const accion = { pdf: 'exportPdf', consignacion: 'consignacionExcel', 'consignacion-pdf': 'consignacionPdf' }[formato] || 'exportExcel';
    CMG_descargar(BASE_URL + '/' + RUTA_MODULO + '/' + accion + '?' + params.toString(), {
        nombre: esPdf ? 'PDF' : 'Excel',
        archivo: 'ReporteInventarios.' + (esPdf ? 'pdf' : 'xlsx'),
    });
}

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 1: EXISTENCIAS
// ════════════════════════════════════════════════════════════════════
/**
 * Deja una pestaña como recién abierta: todos los filtros en su valor por defecto
 * y la tabla otra vez con el mensaje inicial. NO vuelve a consultar — el botón
 * Mostrar está justo al lado y una consulta sin ningún filtro puede ser costosa.
 *
 * form.reset() ya devuelve cada control al valor por defecto del HTML (incluidos
 * los <option selected>); aparte hay que vaciar a mano los hidden de los
 * autocompletes, su etiqueta de "seleccionado" y cerrar sus dropdowns.
 */
function RI_limpiarFiltros(prefijo, etiquetas, colsPorDefecto) {
    const form = document.getElementById(prefijo + '-form');
    if (!form) return;
    form.reset();
    form.querySelectorAll('input[type="hidden"]').forEach(h => { h.value = ''; });
    form.querySelectorAll('.dropdown-predictivo').forEach(d => d.classList.add('d-none'));
    (etiquetas || []).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '';
    });

    const tbody = document.getElementById(prefijo + '-tbody');
    if (tbody) {
        const thead = document.getElementById(prefijo + '-thead');
        const cols = (thead && thead.querySelectorAll('th').length)
            || tbody.querySelector('td[colspan]')?.getAttribute('colspan')
            || colsPorDefecto || 10;
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y genera el reporte.</td></tr>`;
    }
}

// Desgloses de Existencias por debajo de producto×bodega. Deben coincidir con
// ReporteInventariosController::DESGLOSES_EXISTENCIAS.
const RI_DESGLOSES = ['LOTE', 'CADUCIDAD', 'LOTE_CADUCIDAD'];

// Desglose de Existencias que no sale del kardex sino de las líneas de consignación:
// una fila por lote/NUP entregado, con su documento, cliente y saldo. Debe coincidir
// con ReporteInventariosController::DESGLOSE_CONSIGNACION.
const RI_DESGLOSE_CONSIGNACION = 'LOTE_CONSIGNACION';

window.RI_Existencias = {
    orden: '',
    dir: 'ASC',

    /**
     * Modo con el que se pinta la tabla: manda el selector "Detalle"; solo cuando
     * está "En general" decide el "Agrupar por". Es la misma regla que aplica
     * ReporteInventariosController::generarExistencias().
     */
    modoActual() {
        const desglose = document.getElementById('ri-ex-desglose').value;
        return desglose !== 'GENERAL' ? desglose : document.getElementById('ri-ex-agrupar').value;
    },

    // Debe coincidir con ReporteInventariosController::colSpanExistencias(): la columna
    // Código suma una en todo modo cuya fila es un producto (detalle, desgloses y por producto).
    colSpan(modo) {
        if (modo === RI_DESGLOSE_CONSIGNACION) return 15;
        if (modo === 'NINGUNO' || modo === 'LOTE_CADUCIDAD') return 11;
        if (modo === 'PRODUCTO') return 9;
        if (RI_DESGLOSES.includes(modo)) return 9;
        return 8;
    },

    limpiarFiltros() {
        RI_limpiarFiltros('ri-ex', ['ri-ex-producto-seleccionado']);
        this.orden = '';
        this.dir = 'ASC';
        // El reset devuelve el Detalle a "En general", así que Agrupar por y Estado
        // vuelven a estar activos.
        document.getElementById('ri-ex-agrupar').disabled = false;
        const estado = document.getElementById('ri-ex-estado');
        if (estado) { estado.disabled = false; estado.title = ''; }
    },

    /** El desglose ya define las filas: con él activo, "Agrupar por" no pinta nada.
     *  Solo deja los controles como corresponde; la consulta la lanza Mostrar. */
    cambiarDesglose() {
        const desglose = document.getElementById('ri-ex-desglose').value;
        const agrupar = document.getElementById('ri-ex-agrupar');
        agrupar.disabled = desglose !== 'GENERAL';
        if (agrupar.disabled) agrupar.value = 'NINGUNO';

        // El estado de stock (quiebre, bajo mínimo…) se mide contra el mínimo/máximo del
        // producto en la bodega: solo tiene sentido en "En general". En los desgloses la
        // fila es un lote/caducidad o una entrega, y el filtro no se aplicaba.
        const estado = document.getElementById('ri-ex-estado');
        if (estado) {
            estado.disabled = desglose !== 'GENERAL';
            if (estado.disabled) estado.value = '';
            estado.title = estado.disabled
                ? 'Solo aplica con Detalle "En general": el mínimo y el máximo son del producto en la bodega, no de un lote.'
                : '';
        }
    },

    limpiarProducto() {
        RI_limpiarBusqueda('ri-ex-search-producto', 'ri-ex-id-producto', 'ri-ex-producto-seleccionado');
    },

    modalEditarInstance: null,

    abrirModalEditar(btn) {
        const d = btn.dataset;
        document.getElementById('ri-ex-edit-id-producto').value = d.idProducto;
        document.getElementById('ri-ex-edit-id-bodega').value = d.idBodega;
        document.getElementById('ri-ex-edit-producto-nombre').textContent = d.productoNombre;
        document.getElementById('ri-ex-edit-bodega-nombre').textContent = d.bodegaNombre;
        document.getElementById('ri-ex-edit-minimo').value = d.stockMinimo;
        document.getElementById('ri-ex-edit-minimo').dataset.original = d.stockMinimo;
        document.getElementById('ri-ex-edit-maximo').value = d.stockMaximo;
        document.getElementById('ri-ex-edit-maximo').dataset.original = d.stockMaximo;

        const selCategoria = document.getElementById('ri-ex-edit-categoria');
        selCategoria.innerHTML = '<option value="">Sin categoría</option>'
            + Array.from(document.getElementById('ri-ex-categoria').options).slice(1).map(o => o.outerHTML).join('');
        selCategoria.value = d.idCategoria || '';
        selCategoria.dataset.original = d.idCategoria || '';
        selCategoria.dataset.originalLabel = selCategoria.selectedOptions[0] ? selCategoria.selectedOptions[0].textContent : 'Sin categoría';

        const selAdjBodega = document.getElementById('ri-ex-adj-bodega');
        selAdjBodega.innerHTML = Array.from(document.getElementById('ri-ex-bodega').options).slice(1).map(o => o.outerHTML).join('');
        selAdjBodega.value = d.idBodega;

        document.getElementById('ri-ex-adj-tipo').value = '';
        document.getElementById('ri-ex-adj-cantidad').value = '';
        document.getElementById('ri-ex-adj-costo').value = d.costoUnitario || 0;
        document.getElementById('ri-ex-adj-lote').value = '';
        document.getElementById('ri-ex-adj-observaciones').value = '';

        if (!this.modalEditarInstance) {
            this.modalEditarInstance = new bootstrap.Modal(document.getElementById('ri-ex-modal-editar'));
        }
        this.modalEditarInstance.show();
    },

    confirmarGuardarEdicion() {
        const idProducto = document.getElementById('ri-ex-edit-id-producto').value;
        const idBodega = document.getElementById('ri-ex-edit-id-bodega').value;
        const minEl = document.getElementById('ri-ex-edit-minimo');
        const maxEl = document.getElementById('ri-ex-edit-maximo');
        const catEl = document.getElementById('ri-ex-edit-categoria');
        const adjBodegaEl = document.getElementById('ri-ex-adj-bodega');
        const adjTipoEl = document.getElementById('ri-ex-adj-tipo');
        const adjCantidadEl = document.getElementById('ri-ex-adj-cantidad');
        const adjCostoEl = document.getElementById('ri-ex-adj-costo');
        const adjLoteEl = document.getElementById('ri-ex-adj-lote');
        const adjObsEl = document.getElementById('ri-ex-adj-observaciones');

        const nuevoMin = parseFloat(minEl.value || 0);
        const nuevoMax = parseFloat(maxEl.value || 0);
        const original_min = parseFloat(minEl.dataset.original || 0);
        const original_max = parseFloat(maxEl.dataset.original || 0);
        const cambioMinMax = nuevoMin !== original_min || nuevoMax !== original_max;
        const cambioCategoria = catEl.value !== (catEl.dataset.original || '');
        const cantidadAjuste = parseFloat(adjCantidadEl.value || 0);
        const hayAjuste = !!adjTipoEl.value && cantidadAjuste > 0;

        if (!cambioMinMax && !cambioCategoria && !hayAjuste) {
            this.modalEditarInstance.hide();
            return;
        }
        if (nuevoMax > 0 && nuevoMax < nuevoMin) {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Datos inválidos', text: 'El máximo no puede ser menor que el mínimo.' });
            else alert('El máximo no puede ser menor que el mínimo.');
            return;
        }
        if (adjTipoEl.value && cantidadAjuste <= 0) {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Datos inválidos', text: 'Ingresa una cantidad mayor a cero para el ajuste.' });
            else alert('Ingresa una cantidad mayor a cero para el ajuste.');
            return;
        }

        let resumen = '<ul class="text-start mb-0 ps-3">';
        if (cambioMinMax) {
            resumen += `<li>Mínimo: <b>${original_min}</b> &rarr; <b>${nuevoMin}</b></li>`;
            resumen += `<li>Máximo: <b>${original_max}</b> &rarr; <b>${nuevoMax}</b></li>`;
        }
        if (cambioCategoria) {
            const nuevaLabel = catEl.selectedOptions[0] ? catEl.selectedOptions[0].textContent : 'Sin categoría';
            resumen += `<li>Categoría: <b>${catEl.dataset.originalLabel}</b> &rarr; <b>${nuevaLabel}</b></li>`;
        }
        if (hayAjuste) {
            const bodegaLabel = adjBodegaEl.selectedOptions[0] ? adjBodegaEl.selectedOptions[0].textContent : '';
            const tipoLabel = adjTipoEl.value === 'entrada' ? 'Entrada' : 'Salida';
            resumen += `<li>Ajuste de inventario: <b>${tipoLabel}</b> de <b>${cantidadAjuste}</b> en bodega <b>${bodegaLabel}</b></li>`;
        }
        resumen += '</ul>';

        const ejecutarGuardado = () => {
            const llamadas = [];
            if (cambioMinMax) {
                llamadas.push(fetch(BASE_URL + '/' + RUTA_MODULO + '/actualizarMinMaxAjax', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ id_producto: idProducto, id_bodega: idBodega, stock_minimo: nuevoMin, stock_maximo: nuevoMax }).toString(),
                }).then(r => r.json()));
            }
            if (cambioCategoria) {
                llamadas.push(fetch(BASE_URL + '/' + RUTA_MODULO + '/actualizarCategoriaAjax', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ id_producto: idProducto, id_categoria: catEl.value }).toString(),
                }).then(r => r.json()));
            }
            if (hayAjuste) {
                llamadas.push(fetch(BASE_URL + '/' + RUTA_MODULO + '/ajustarInventarioAjax', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({
                        id_producto: idProducto, id_bodega: adjBodegaEl.value, tipo_movimiento: adjTipoEl.value,
                        cantidad: cantidadAjuste, costo_unitario: adjCostoEl.value || 0,
                        numero_lote: adjLoteEl.value || '', observaciones: adjObsEl.value || '',
                    }).toString(),
                }).then(r => r.json()));
            }

            Promise.all(llamadas).then(resultados => {
                const error = resultados.find(r => !r.ok);
                if (error) {
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: error.error || 'Ocurrió un error' });
                    else alert(error.error || 'No se pudo guardar');
                    return;
                }
                this.modalEditarInstance.hide();
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Cambios guardados', timer: 1800, showConfirmButton: false });
                this.generar();
            }).catch(err => {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo comunicar con el servidor.' });
                else alert('Error de conexión');
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Confirmar cambios',
                html: resumen,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
            }).then(result => { if (result.isConfirmed) ejecutarGuardado(); });
        } else if (confirm('¿Confirmas guardar estos cambios?')) {
            ejecutarGuardado();
        }
    },

    dibujarCabecera(modo) {
        const sortIcon = '<i class="bi bi-arrow-down-up small text-muted ms-1"></i>';
        const th2 = (label, campo, extra) => `<th class="sortable-header ${extra || ''}" data-sort="${campo}" data-col="${campo}">${label}${sortIcon}</th>`;
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            th += th2('Código', 'producto_codigo', 'ps-3')
                + th2('Producto', 'producto_nombre')
                + th2('Categoría', 'categoria_nombre')
                + th2('Bodega', 'bodega_nombre')
                + th2('Consignación', 'consignado', 'text-end')
                + th2('Stock', 'stock_actual', 'text-end')
                + th2('Stock Total', 'stock_total', 'text-end')
                + th2('Mínimo', 'stock_minimo', 'text-end')
                + th2('Máximo', 'stock_maximo', 'text-end')
                + th2('Costo Unit.', 'costo_unitario', 'text-end')
                + th2('Valor total', 'valor_total', 'text-end pe-3');
        } else if (modo === RI_DESGLOSE_CONSIGNACION) {
            // La fila es una línea de consignación: sus columnas son las del documento
            // que la entregó, no las del par producto×bodega.
            th += `<th class="ps-3">Fecha</th><th>Secuencial</th><th>Cliente</th><th>Asesor</th>
                   <th>Código</th><th>Descripción</th><th>Lote</th><th>NUP</th><th>Responsable traslado</th><th>Bodega</th>
                   <th class="text-end">Consignado</th><th class="text-end">Retornado</th>
                   <th class="text-end">Facturado</th>
                   <th class="text-end" title="Entregado al cliente a cambio de otro producto (Cambios de productos)">A cambio</th>
                   <th class="text-end pe-3">Saldo</th>`;
        } else if (RI_DESGLOSES.includes(modo)) {
            // Solo las columnas que el desglose puede afirmar: "Por lotes" suma todas las
            // caducidades de un lote, así que no tiene una caducidad ni un NUP únicos.
            th += '<th class="ps-3">Código</th><th>Producto</th><th>Bodega</th>';
            if (modo === 'LOTE' || modo === 'LOTE_CADUCIDAD') th += '<th>Lote</th>';
            if (modo === 'LOTE_CADUCIDAD') th += '<th>NUP</th>';
            if (modo === 'CADUCIDAD' || modo === 'LOTE_CADUCIDAD') th += '<th>Caducidad</th>';
            th += `<th class="text-end">Stock</th><th class="text-end">Consignación</th><th class="text-end">Stock Total</th>
                   <th class="text-end">Costo Unit.</th><th class="text-end pe-3">Valor total</th>`;
        } else {
            // Agrupado por producto: el código encabeza la fila; por categoría o bodega no hay uno.
            if (modo === 'PRODUCTO') th += '<th class="ps-3">Código</th><th>Producto</th>';
            else th += '<th class="ps-3">Grupo</th>';
            th += `<th class="text-center">Productos</th>
                   <th class="text-end">Consignación</th><th class="text-end">Stock</th><th class="text-end">Stock Total</th>
                   <th class="text-end">Mínimo</th>
                   <th class="text-end">Costo Unit.</th><th class="text-end pe-3">Valor total</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-ex-thead').innerHTML = th;

        if (modo === 'NINGUNO' && typeof window.CMG_initSort === 'function') {
            // reload:false porque el callback ya repinta la tabla. Sin él, CMG_initSort
            // guardaba el orden y RECARGABA la página: la consulta recién lanzada se perdía,
            // la tabla volvía vacía y había que pulsar Mostrar otra vez.
            window.CMG_initSort('reporte_inventarios_existencias', (col, dir) => {
                this.orden = col; this.dir = dir;
                this.generar();
            }, { container: '#ri-ex-thead', col: this.orden, dir: this.dir, reload: false });
        }
        if (typeof window.initResizableColumns === 'function') {
            window.initResizableColumns();
        }
    },

    /**
     * Filtros del formulario, iguales para Mostrar, PDF y Excel. Si el usuario escribió un
     * producto pero no lo eligió de la lista, el texto viaja como "buscar" (nombre o código):
     * antes se descartaba y la tabla salía sin filtrar por producto.
     */
    _filtros() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-ex-bodega', id_categoria: 'ri-ex-categoria', id_marca: 'ri-ex-marca',
            id_producto: 'ri-ex-id-producto', estado_stock: 'ri-ex-estado', consignado: 'ri-ex-consignado', agrupar_por: 'ri-ex-agrupar',
            desglose: 'ri-ex-desglose',
            fecha_corte: 'ri-ex-fecha-corte',
            numero_lote: 'ri-ex-lote', nup: 'ri-ex-nup',
            fecha_caducidad_desde: 'ri-ex-caducidad-desde', fecha_caducidad_hasta: 'ri-ex-caducidad-hasta',
        });
        const texto = (document.getElementById('ri-ex-search-producto')?.value || '').trim();
        if (!params.get('id_producto') && texto !== '') params.set('buscar', texto);
        return params;
    },

    generar() {
        const modo = this.modoActual();
        this.dibujarCabecera(modo);

        const params = this._filtros();
        if (modo === 'NINGUNO' && this.orden) {
            params.set('orden', this.orden);
            params.set('dir', this.dir);
        }

        const tbody = document.getElementById('ri-ex-tbody');
        const colSpan = this.colSpan(modo);
        RI_fetchGenerar('existencias', params, (res) => {
            tbody.innerHTML = res.rows;
            // Filtros con los que se pintó esta tabla: el seguimiento de un negativo los reusa
            // para rehacer la misma suma, en vez de releer el formulario (que pudo cambiar).
            this.filtrosSaldo = res.filtros_saldo || {};
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
        }, { prefijo: 'ri-ex', colSpan });
    },

    exportarExcel() {
        const params = this._filtros();
        params.set('tab', 'existencias');
        RI_descargarExport('excel', params);
    },
    exportarPDF() {
        const params = this._filtros();
        params.set('tab', 'existencias');
        RI_descargarExport('pdf', params);
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 2: MOVIMIENTOS (KARDEX)
// ════════════════════════════════════════════════════════════════════
window.RI_Movimientos = {
    limpiarFiltros() {
        RI_limpiarFiltros('ri-mv', ['ri-mv-producto-seleccionado']);
        this.cambiarMesAnio();   // el reset deja el año por defecto: hay que rehacer las fechas
    },

    limpiarProducto() {
        RI_limpiarBusqueda('ri-mv-search-producto', 'ri-mv-id-producto', 'ri-mv-producto-seleccionado');
    },

    /**
     * Traduce Año/Mes a las fechas desde/hasta, que son el filtro real. Solo
     * sincroniza los campos: la consulta la lanza el botón Mostrar. La pestaña
     * arranca con un año elegido porque sin acotar la fecha el saldo corrido
     * obliga a recorrer todo el histórico del kardex.
     */
    cambiarMesAnio() {
        // La pestaña Movimientos no está en la página cuando el usuario no puede ver
        // Inventario (el controlador no la dibuja): no hay nada que sincronizar.
        const selMes  = document.getElementById('ri-mv-mes');
        const selAnio = document.getElementById('ri-mv-anio');
        if (!selMes || !selAnio) return;
        const mes = selMes.value;
        const anio = selAnio.value;
        if (!mes || !anio) return;

        if (anio === 'TODOS') {
            document.getElementById('ri-mv-fecha-desde').value = '';
            document.getElementById('ri-mv-fecha-hasta').value = '';
        } else if (mes === 'TODOS') {
            document.getElementById('ri-mv-fecha-desde').value = anio + '-01-01';
            document.getElementById('ri-mv-fecha-hasta').value = anio + '-12-31';
        } else {
            const ultimoDia = new Date(parseInt(anio), parseInt(mes), 0).getDate();
            document.getElementById('ri-mv-fecha-desde').value = `${anio}-${mes}-01`;
            document.getElementById('ri-mv-fecha-hasta').value = `${anio}-${mes}-${String(ultimoDia).padStart(2, '0')}`;
        }
    },

    dibujarCabecera(modo) {
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            th += `<th class="ps-3">Código</th><th>Fecha</th><th>Producto</th><th>Bodega</th><th class="text-center">Tipo</th>
                   <th>Origen</th><th class="text-end">Entradas</th><th class="text-end">Salidas</th><th class="text-end">Saldo</th>
                   <th class="text-end">Costo Unit.</th>
                   <th>Lote</th><th>Caducidad</th><th class="pe-3">Observaciones</th>`;
        } else {
            // Agrupado por producto: el código encabeza la fila; los demás agrupados no tienen uno.
            if (modo === 'PRODUCTO') th += '<th class="ps-3">Código</th><th>Producto</th>';
            else th += '<th class="ps-3">Grupo</th>';
            th += `<th class="text-center">Movimientos</th>
                   <th class="text-end">Entradas</th><th class="text-end">Salidas</th>
                   <th class="text-end">Saldo neto</th><th class="text-end pe-3">Costo total</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-mv-thead').innerHTML = th;
    },

    generar() {
        const modo = document.getElementById('ri-mv-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            fecha_desde: 'ri-mv-fecha-desde', fecha_hasta: 'ri-mv-fecha-hasta',
            id_bodega: 'ri-mv-bodega', id_producto: 'ri-mv-id-producto',
            id_categoria: 'ri-mv-categoria', id_marca: 'ri-mv-marca',
            tipo_movimiento: 'ri-mv-tipo', referencia_tipo: 'ri-mv-origen',
            id_usuario: 'ri-mv-usuario', numero_lote: 'ri-mv-lote', nup: 'ri-mv-nup',
            fecha_caducidad_desde: 'ri-mv-caducidad-desde', fecha_caducidad_hasta: 'ri-mv-caducidad-hasta',
            observaciones: 'ri-mv-observaciones',
            agrupar_por: 'ri-mv-agrupar',
        });

        const tbody = document.getElementById('ri-mv-tbody');
        const colSpan = modo === 'NINGUNO' ? 13 : (modo === 'PRODUCTO' ? 7 : 6);
        RI_fetchGenerar('movimientos', params, (res) => {
            tbody.innerHTML = res.rows;
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
        }, { prefijo: 'ri-mv', colSpan });
    },

    exportarExcel() {
        const params = this._filtros();
        RI_descargarExport('excel', params);
    },
    exportarPDF() {
        const params = this._filtros();
        RI_descargarExport('pdf', params);
    },
    _filtros() {
        const params = RI_paramsFromIds({
            fecha_desde: 'ri-mv-fecha-desde', fecha_hasta: 'ri-mv-fecha-hasta',
            id_bodega: 'ri-mv-bodega', id_producto: 'ri-mv-id-producto',
            id_categoria: 'ri-mv-categoria', id_marca: 'ri-mv-marca',
            tipo_movimiento: 'ri-mv-tipo', referencia_tipo: 'ri-mv-origen',
            id_usuario: 'ri-mv-usuario', numero_lote: 'ri-mv-lote', nup: 'ri-mv-nup',
            fecha_caducidad_desde: 'ri-mv-caducidad-desde', fecha_caducidad_hasta: 'ri-mv-caducidad-hasta',
            observaciones: 'ri-mv-observaciones',
            agrupar_por: 'ri-mv-agrupar',
        });
        params.set('tab', 'movimientos');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 3: VALORIZACIÓN
// ════════════════════════════════════════════════════════════════════
window.RI_Valorizacion = {
    limpiarFiltros() {
        RI_limpiarFiltros('ri-va', ['ri-va-producto-seleccionado'], 6);
        this.dibujarCabecera(document.getElementById('ri-va-agrupar').value);
    },

    /** "Por Producto" es el único agrupado con código propio; los demás (categoría,
     *  bodega, marca) no lo tienen, así que esa primera columna solo aparece ahí. */
    dibujarCabecera(modo) {
        let th = '<tr>';
        th += modo === 'PRODUCTO' ? '<th>Código</th><th>Producto</th>' : '<th>Grupo</th>';
        th += `<th class="text-center">Productos</th><th class="text-end">Stock</th>
               <th class="text-end">Costo promedio</th><th class="text-end">Valor total</th></tr>`;
        document.getElementById('ri-va-thead').innerHTML = th;
    },

    limpiarProducto() {
        RI_limpiarBusqueda('ri-va-search-producto', 'ri-va-id-producto', 'ri-va-producto-seleccionado');
    },

    generar() {
        const modo = document.getElementById('ri-va-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            id_bodega: 'ri-va-bodega', id_categoria: 'ri-va-categoria', id_marca: 'ri-va-marca',
            id_producto: 'ri-va-id-producto', agrupar_por: 'ri-va-agrupar',
        });

        const tbody = document.getElementById('ri-va-tbody');
        const colSpan = modo === 'PRODUCTO' ? 6 : 5;
        RI_fetchGenerar('valorizacion', params, (res) => {
            tbody.innerHTML = res.rows;
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
        }, { prefijo: 'ri-va', colSpan });
    },

    exportarExcel() {
        const params = this._filtros();
        RI_descargarExport('excel', params);
    },
    exportarPDF() {
        const params = this._filtros();
        RI_descargarExport('pdf', params);
    },
    _filtros() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-va-bodega', id_categoria: 'ri-va-categoria', id_marca: 'ri-va-marca',
            id_producto: 'ri-va-id-producto', agrupar_por: 'ri-va-agrupar',
        });
        params.set('tab', 'valorizacion');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 4: CONSIGNACIONES
// ════════════════════════════════════════════════════════════════════
window.RI_Consignaciones = {
    limpiarFiltros() {
        RI_limpiarFiltros('ri-cv', ['ri-cv-cliente-seleccionado', 'ri-cv-producto-seleccionado']);
    },

    limpiarCliente() {
        RI_limpiarBusqueda('ri-cv-search-cliente', 'ri-cv-id-cliente', 'ri-cv-cliente-seleccionado');
    },
    limpiarProducto() {
        RI_limpiarBusqueda('ri-cv-search-producto', 'ri-cv-id-producto', 'ri-cv-producto-seleccionado');
    },

    modalInstance: null,
    docsModalInstance: null,
    /** Consignación abierta en el modal de detalle (para el PDF del estado completo). */
    idConsignacionActual: 0,

    dibujarCabecera(modo) {
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            // La fila es el documento completo: como Lote y NUP, el código llega agregado
            // con todos los productos de la consignación (el detalle por línea va en el modal).
            th += `<th class="ps-3">Código</th><th>Fecha</th><th>Cliente</th><th>Asesor</th><th>Responsable traslado</th>
                   <th>Lote</th><th>NUP</th>
                   <th class="text-end">Total productos</th><th class="text-end">Saldo</th>
                   <th class="text-center pe-3">Estado</th>`;
        } else {
            if (modo === 'PRODUCTO') th += '<th class="ps-3">Código</th><th>Producto</th>';
            else th += '<th class="ps-3">Grupo</th>';
            th += `<th class="text-center">Consignaciones</th>
                   <th class="text-end pe-3">Saldo</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-cv-thead').innerHTML = th;
    },

    /** Filtros del listado que actúan sobre la LÍNEA: el modal los reaplica para que sus totales
     *  cuadren con el "Total productos" y el "Saldo" de la fila. */
    _filtrosLinea() {
        return RI_paramsFromIds({
            id_producto: 'ri-cv-id-producto', id_bodega: 'ri-cv-bodega',
            numero_lote: 'ri-cv-lote', nup: 'ri-cv-nup',
            fecha_caducidad_desde: 'ri-cv-caducidad-desde', fecha_caducidad_hasta: 'ri-cv-caducidad-hasta',
        });
    },

    verDetalle(idConsignacion, sinFiltros = false) {
        if (!this.modalInstance) {
            this.modalInstance = new bootstrap.Modal(document.getElementById('ri-cv-modal-detalle'));
        }
        this.idConsignacionActual = idConsignacion;
        const btnPdf = document.getElementById('ri-cv-modal-btn-pdf');
        const btnExcel = document.getElementById('ri-cv-modal-btn-excel');
        if (btnPdf) btnPdf.disabled = true;
        if (btnExcel) btnExcel.disabled = true;
        const tbody = document.getElementById('ri-cv-modal-tbody');
        const tfoot = document.getElementById('ri-cv-modal-tfoot');
        const aviso = document.getElementById('ri-cv-modal-aviso');
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;
        if (tfoot) tfoot.innerHTML = '';
        if (aviso) aviso.classList.add('d-none');
        ['secuencial', 'fecha', 'cliente', 'vendedor', 'responsable', 'estado'].forEach(k => {
            document.getElementById('ri-cv-modal-' + k).textContent = '';
        });
        this.modalInstance.show();

        const params = sinFiltros ? new URLSearchParams({ sin_filtros: '1' }) : this._filtrosLinea();
        params.set('id', idConsignacion);

        fetch(BASE_URL + '/' + RUTA_MODULO + '/verConsignacionDetalleAjax?' + params.toString())
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">${res.error || 'No se pudo cargar el detalle'}</td></tr>`;
                    return;
                }
                const c = res.cabecera;
                document.getElementById('ri-cv-modal-secuencial').textContent = c.secuencial || '';
                if (btnPdf) btnPdf.disabled = false;
                if (btnExcel) btnExcel.disabled = false;
                document.getElementById('ri-cv-modal-fecha').textContent = c.fecha_emision || '';
                document.getElementById('ri-cv-modal-cliente').textContent = c.cliente + (c.identificacion ? ` (${c.identificacion})` : '');
                document.getElementById('ri-cv-modal-vendedor').textContent = c.vendedor || '-';
                document.getElementById('ri-cv-modal-responsable').textContent = c.responsable || '-';
                document.getElementById('ri-cv-modal-estado').textContent = c.estado || '';
                tbody.innerHTML = res.rows;

                if (tfoot && res.totales) {
                    tfoot.innerHTML = `<tr class="table-light fw-bold">
                        <td colspan="5" class="small text-end">Totales</td>
                        <td class="text-end small">${res.totales.consignado}</td>
                        <td class="text-end small">${res.totales.retornado}</td>
                        <td class="text-end small">${res.totales.facturado}</td>
                        <td class="text-end small">${res.totales.cambiado || '0.00'}</td>
                        <td class="text-end small">${res.totales.saldo}</td>
                    </tr>`;
                }
                if (aviso) {
                    if (res.filtrado) {
                        aviso.innerHTML = `<i class="bi bi-funnel me-1"></i>Mostrando solo las líneas que coinciden con los filtros de la búsqueda.
                            <a href="#" class="ms-1 fw-bold" onclick="window.RI_Consignaciones.verDetalle(${idConsignacion}, true); return false;">Ver todas las líneas</a>`;
                        aviso.classList.remove('d-none');
                    } else {
                        aviso.classList.add('d-none');
                    }
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">Error al cargar el detalle</td></tr>`;
            });
    },

    /** PDF del ESTADO completo de la consignación abierta en el modal: mismo diseño del
     *  comprobante de Consignaciones de Ventas, con lo facturado, lo devuelto, los documentos
     *  que lo explican y el saldo. Siempre el documento entero (no reaplica los filtros). */
    descargarPdf() {
        if (!this.idConsignacionActual) return;
        RI_descargarExport('consignacion-pdf', new URLSearchParams({ id: this.idConsignacionActual }));
    },

    /** Excel de la consignación abierta en el modal: el documento entero, como el PDF, una
     *  fila por línea con los números de las facturas y los retornos que la explican. */
    descargarExcel() {
        if (!this.idConsignacionActual) return;
        RI_descargarExport('consignacion', new URLSearchParams({ id: this.idConsignacionActual }));
    },

    /** Sub-modal (encima del de detalle, que queda fijo/abierto detrás): documentos de
     *  retorno o factura que explican la cantidad Retornado/Facturado de una línea. */
    verDocumentosLinea(idDetalle, tipo) {
        if (!this.docsModalInstance) {
            this.docsModalInstance = new bootstrap.Modal(document.getElementById('ri-cv-modal-linea-docs'));
        }
        const tbody = document.getElementById('ri-cv-docs-tbody');
        document.getElementById('ri-cv-docs-titulo').textContent = tipo === 'retorno' ? 'Retornos de esta línea' : 'Facturas de venta de esta línea';
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;
        this.docsModalInstance.show();

        fetch(BASE_URL + '/' + RUTA_MODULO + '/verDocumentosLineaConsignacionAjax?id_detalle=' + encodeURIComponent(idDetalle) + '&tipo=' + encodeURIComponent(tipo))
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">${res.error || 'No se pudo cargar el detalle'}</td></tr>`;
                    return;
                }
                // Sin permiso de lectura en el módulo dueño (Facturas de Venta / Retornos CV) el
                // backend no emite la celda del PDF: se oculta también su cabecera para que la
                // tabla no quede con una columna vacía.
                const thPdf = document.getElementById('ri-cv-docs-th-pdf');
                if (thPdf) thPdf.classList.toggle('d-none', res.puede_pdf === false);
                tbody.innerHTML = res.rows;
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Error al cargar el detalle</td></tr>`;
            });
    },

    generar() {
        const modo = document.getElementById('ri-cv-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            id_cliente: 'ri-cv-id-cliente', id_producto: 'ri-cv-id-producto',
            id_bodega: 'ri-cv-bodega', id_vendedor: 'ri-cv-vendedor', id_responsable_traslado: 'ri-cv-responsable',
            fecha_desde: 'ri-cv-fecha-desde', fecha_hasta: 'ri-cv-fecha-hasta',
            fecha_caducidad_desde: 'ri-cv-caducidad-desde', fecha_caducidad_hasta: 'ri-cv-caducidad-hasta',
            numero_lote: 'ri-cv-lote', nup: 'ri-cv-nup', secuencial: 'ri-cv-secuencial',
            estado: 'ri-cv-estado',
            agrupar_por: 'ri-cv-agrupar',
        });

        const tbody = document.getElementById('ri-cv-tbody');
        const colSpan = modo === 'NINGUNO' ? 10 : (modo === 'PRODUCTO' ? 4 : 3);
        RI_fetchGenerar('consignaciones', params, (res) => {
            tbody.innerHTML = res.rows;
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
        }, { prefijo: 'ri-cv', colSpan });
    },

    exportarExcel() {
        const params = this._filtros();
        RI_descargarExport('excel', params);
    },
    exportarPDF() {
        const params = this._filtros();
        RI_descargarExport('pdf', params);
    },
    _filtros() {
        const params = RI_paramsFromIds({
            id_cliente: 'ri-cv-id-cliente', id_producto: 'ri-cv-id-producto',
            id_bodega: 'ri-cv-bodega', id_vendedor: 'ri-cv-vendedor', id_responsable_traslado: 'ri-cv-responsable',
            fecha_desde: 'ri-cv-fecha-desde', fecha_hasta: 'ri-cv-fecha-hasta',
            fecha_caducidad_desde: 'ri-cv-caducidad-desde', fecha_caducidad_hasta: 'ri-cv-caducidad-hasta',
            numero_lote: 'ri-cv-lote', nup: 'ri-cv-nup', secuencial: 'ri-cv-secuencial',
            estado: 'ri-cv-estado',
            agrupar_por: 'ri-cv-agrupar',
        });
        params.set('tab', 'consignaciones');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 5: AUDITORÍA
// ════════════════════════════════════════════════════════════════════
window.RI_Auditoria = {
    ultimoTotal: 0,

    limpiarFiltros() {
        RI_limpiarFiltros("ri-au", ["ri-au-producto-seleccionado"]);
    },

    limpiarProducto() {
        RI_limpiarBusqueda('ri-au-search-producto', 'ri-au-id-producto', 'ri-au-producto-seleccionado');
    },

    dibujarCabecera() {
        const th = `<tr><th>Código</th><th>Producto</th><th>Bodega</th><th class="text-end">Guardado</th>
               <th class="text-end">Real (Kardex)</th><th class="text-end">Diferencia</th>
               <th class="text-center">Acción</th></tr>`;
        document.getElementById('ri-au-thead').innerHTML = th;
    },

    _filtros() {
        return RI_paramsFromIds({
            id_bodega: 'ri-au-bodega', id_producto: 'ri-au-id-producto', buscar: 'ri-au-buscar',
        });
    },

    generar() {
        const params = this._filtros();
        const colSpan = 7;

        const tbody = document.getElementById('ri-au-tbody');
        RI_fetchGenerar('auditoria', params, (res) => {
            this.dibujarCabecera();
            tbody.innerHTML = res.rows;
            const info = document.getElementById('ri-au-info-total');
            const total = (res.kpis && res.kpis.total_discrepancias) || 0;
            this.ultimoTotal = total;
            info.textContent = total > 0
                ? `${total} discrepancia${total === 1 ? '' : 's'} encontrada${total === 1 ? '' : 's'}`
                : 'Sin discrepancias — el stock guardado coincide con el Kardex.';

            const btnTodo = document.getElementById('ri-au-btn-corregir-todo');
            if (btnTodo) btnTodo.style.display = total > 0 ? '' : 'none';
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
        }, { prefijo: 'ri-au', colSpan });
    },

    corregir(btn) {
        const d = btn.dataset;
        const cacheado = parseFloat(d.cacheado || 0);
        const real = parseFloat(d.real || 0);

        const ejecutar = () => {
            btn.disabled = true;
            const params = new URLSearchParams({ id_producto: d.idProducto, id_bodega: d.idBodega });
            fetch(BASE_URL + '/' + RUTA_MODULO + '/corregirStockAuditoriaAjax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: params.toString(),
            })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    btn.disabled = false;
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'No se pudo corregir', text: res.error || 'Error desconocido' });
                    else alert(res.error || 'Error desconocido');
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Stock corregido', timer: 1500, showConfirmButton: false });
                }
                window.RI_Auditoria.generar();
            })
            .catch(err => {
                btn.disabled = false;
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo corregir el stock.' });
                else alert('No se pudo corregir el stock.');
            });
        };

        const mensaje = `<p class="mb-2">${d.productoNombre} — ${d.bodegaNombre}</p>
            <p class="mb-0">Guardado: <b>${cacheado}</b> &rarr; Real (Kardex): <b>${real}</b></p>`;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: '¿Corregir stock?',
                html: mensaje,
                showCancelButton: true,
                confirmButtonText: 'Sí, corregir',
                cancelButtonText: 'Cancelar',
            }).then(result => { if (result.isConfirmed) ejecutar(); });
        } else if (confirm('¿Corregir stock de ' + cacheado + ' a ' + real + '?')) {
            ejecutar();
        }
    },

    corregirTodo() {
        const total = this.ultimoTotal || 0;
        if (total <= 0) return;

        const ejecutar = () => {
            const btn = document.getElementById('ri-au-btn-corregir-todo');
            btn.disabled = true;
            const params = this._filtros();
            params.set('tab', 'auditoria');
            fetch(BASE_URL + '/' + RUTA_MODULO + '/corregirTodoAuditoriaAjax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: params.toString(),
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'No se pudo corregir', text: res.error || 'Error desconocido' });
                    else alert(res.error || 'Error desconocido');
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: `${res.corregidas} corregidas`, timer: 1800, showConfirmButton: false });
                }
                window.RI_Auditoria.generar();
            })
            .catch(err => {
                btn.disabled = false;
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo corregir el stock.' });
                else alert('No se pudo corregir el stock.');
            });
        };

        const mensaje = `<p class="mb-0">Vas a corregir <b>${total}</b> discrepancia${total === 1 ? '' : 's'} de esta empresa,
            dejando el stock guardado igual al del Kardex en cada una. Confirma que el Kardex está completo
            antes de continuar — si le faltan movimientos, esto solo iguala el guardado a un Kardex incompleto.</p>`;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: '¿Corregir todo?',
                html: mensaje,
                showCancelButton: true,
                confirmButtonText: 'Sí, corregir todo',
                cancelButtonText: 'Cancelar',
            }).then(result => { if (result.isConfirmed) ejecutar(); });
        } else if (confirm(`¿Corregir ${total} discrepancias ${alcance}?`)) {
            ejecutar();
        }
    },
};

// ════════════════════════════════════════════════════════════════════
// SEGUIMIENTO DE UN STOCK NEGATIVO
// ════════════════════════════════════════════════════════════════════
/**
 * Se abre desde el número rojo de la columna Stock de Existencias (el botón lo pinta
 * ReporteInventariosController::tdStockConSeguimiento, con la clave de la fila en sus
 * data-seg-*). Muestra los movimientos de kardex que componen ESA fila, en orden y con
 * saldo corrido, marcando dónde el saldo cruzó a negativo.
 *
 * La clave se toma tal cual del botón y no del selector Detalle: entre que se pintó la
 * tabla y se pulsa el número, el usuario puede haber cambiado el Detalle sin pulsar
 * Mostrar, y entonces el selector ya no describe las filas que están en pantalla.
 */
window.RI_Seguimiento = {
    abrir(btn) {
        const d = btn.dataset;
        const modalEl = document.getElementById('ri-seg-modal');
        const tbody = document.getElementById('ri-seg-tbody');
        const grupos = document.getElementById('ri-seg-grupos-tbody');
        const diag = document.getElementById('ri-seg-diagnostico');

        document.getElementById('ri-seg-clave').innerHTML = this._clave(d);
        // En "En general" la fila agrega TODOS los lotes, así que la segunda tabla no muestra
        // "los otros" sino de qué se compone la fila: el título lo dice.
        const titulo = document.getElementById('ri-seg-grupos-titulo');
        if (titulo) {
            titulo.textContent = (d.segCols || '')
                ? 'Otros lotes del mismo producto y bodega'
                : 'Lotes que componen esta fila';
        }
        diag.innerHTML = '';
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;
        grupos.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted small">Cargando…</td></tr>`;

        if (typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        const params = new URLSearchParams({
            id_producto: d.segProducto || '0',
            id_bodega: d.segBodega || '0',
            cols: d.segCols || '',
            lote: d.segLote || '',
            nup: d.segNup || '',
            caducidad: d.segCaducidad || '',
        });
        // Los filtros con los que se generó la tabla, para que la suma del seguimiento sea
        // la misma que la de la fila.
        Object.entries(this._filtros()).forEach(([k, v]) => params.set(k, v));

        fetch(BASE_URL + '/' + RUTA_MODULO + '/seguimientoNegativoAjax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: params.toString(),
        })
        .then(r => r.json())
        .then(res => {
            if (!res.ok) {
                tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger">${res.error || 'No se pudo cargar el seguimiento'}</td></tr>`;
                grupos.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted small">—</td></tr>`;
                return;
            }
            tbody.innerHTML = res.rows;
            grupos.innerHTML = res.grupos;
            diag.innerHTML = this._diagnostico(res.resumen);
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger">Error al cargar el seguimiento</td></tr>`;
            grupos.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted small">—</td></tr>`;
        });
    },

    /** Los filtros con los que se generó la tabla que está en pantalla, no los que tenga el
     *  formulario ahora: entre el último Mostrar y el clic en el número, el usuario pudo
     *  tocar los filtros sin volver a generar, y el seguimiento tiene que explicar la fila
     *  que está viendo. Los guarda RI_Existencias.generar() con lo que devuelve el servidor
     *  (filtros_saldo), que además decide cuáles aplican según el nivel de detalle. */
    _filtros() {
        return (window.RI_Existencias && window.RI_Existencias.filtrosSaldo) || {};
    },

    /** Cabecera: qué fila se está siguiendo. Solo se nombran las columnas que forman
     *  parte de su clave; las demás no las puede afirmar esa fila. */
    _clave(d) {
        const cols = (d.segCols || '').split(',').filter(Boolean);
        const partes = [`<b>${this._esc(d.segProductoNombre || '')}</b>`, this._esc(d.segBodegaNombre || '')];
        // Comparación contra '' y no un ||: solo la cadena vacía significa NULL. Un lote que
        // se llame literalmente "0" es un lote, y con || se habría rotulado "(sin lote)".
        const val = (v, vacio) => (v === undefined || v === '') ? vacio : v;
        if (cols.includes('lote')) partes.push('Lote: <b>' + this._esc(val(d.segLote, '(sin lote)')) + '</b>');
        if (cols.includes('nup')) partes.push('NUP: <b>' + this._esc(val(d.segNup, '(sin NUP)')) + '</b>');
        if (cols.includes('caducidad')) partes.push('Caducidad: <b>' + this._esc(val(this._fecha(d.segCaducidad), '(sin caducidad)')) + '</b>');
        if (!cols.length) partes.push('<span class="fst-italic">todos los lotes y caducidades</span>');
        const corte = this._filtros().fecha_corte || '';
        if (corte) partes.push('Corte: <b>' + this._esc(this._fecha(corte)) + '</b>');
        return partes.join(' &nbsp;·&nbsp; ');
    },

    /** El "por qué" en una frase, más los avisos de los dos casos que el usuario no
     *  puede deducir mirando la tabla (sin entradas, y movimientos de otro ambiente). */
    _diagnostico(r) {
        if (!r || !r.total_movimientos) {
            return '<div class="alert alert-secondary py-2 px-3 small mb-0">Esta fila no tiene movimientos de kardex.</div>';
        }
        const n = (v) => Number(v || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        let html = '';

        if (r.cruce) {
            const ref = r.cruce.referencia_id ? ` (ref. ${r.cruce.referencia_id})` : '';
            html += `<div class="alert alert-danger py-2 px-3 small mb-2">
                <i class="bi bi-exclamation-octagon me-1"></i>
                El saldo cruza a negativo el <b>${this._esc(r.cruce.fecha)}</b> con
                <b>${this._esc(r.cruce.origen)}</b>${ref}: salen <b>${n(Math.abs(r.cruce.cantidad))}</b>
                y el saldo queda en <b>${n(r.cruce.saldo)}</b>. Esa fila va resaltada abajo.</div>`;
        } else {
            html += `<div class="alert alert-success py-2 px-3 small mb-2">
                <i class="bi bi-check-circle me-1"></i>
                El saldo nunca baja de cero en este historial.</div>`;
        }

        if (r.sin_entradas) {
            html += `<div class="alert alert-warning py-2 px-3 small mb-2">
                <i class="bi bi-box-arrow-in-down me-1"></i>
                <b>Esta clave no tiene ninguna entrada</b>: solo salidas. El stock salió sin
                haber ingresado nunca con este lote/caducidad —revisa si entró con otro lote
                (tabla de abajo) o si falta registrar la compra o el ingreso.</div>`;
        }
        if (r.otro_ambiente > 0) {
            html += `<div class="alert alert-warning py-2 px-3 small mb-2">
                <i class="bi bi-eye-slash me-1"></i>
                <b>${r.otro_ambiente}</b> movimiento(s) son de <b>otro ambiente</b>. Suman en
                Existencias pero <b>no se ven en la pestaña Movimientos</b>, así que el negativo
                parece salir de la nada si solo se mira allí. Van marcados en la tabla.</div>`;
        }
        if (r.truncado) {
            html += `<div class="alert alert-secondary py-2 px-3 small mb-2">
                Se muestran los primeros movimientos, no todo el historial: el saldo de la
                última fila no es el de la existencia.</div>`;
        }

        html += `<div class="d-flex flex-wrap gap-3 small text-muted">
            <span>Movimientos: <b class="text-dark">${r.total_movimientos}</b></span>
            <span>Entradas: <b class="text-success">${n(r.entradas)}</b></span>
            <span>Salidas: <b class="text-danger">${n(r.salidas)}</b></span>
            <span>Saldo final: <b class="${Number(r.saldo_final) < 0 ? 'text-danger' : 'text-dark'}">${n(r.saldo_final)}</b></span>
            <span>Del ${this._esc(r.primero)} al ${this._esc(r.ultimo)}</span>
        </div>`;

        return html;
    },

    _fecha(iso) {
        if (!iso) return '';
        const p = String(iso).substring(0, 10).split('-');
        return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : iso;
    },

    _esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    },
};

// ════════════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {
    if (typeof aplicarFavoritosModal === 'function') {
        aplicarFavoritosModal();
    }

    RI_setupAutocomplete('ri-ex-search-producto', 'ri-ex-dropdown-producto', 'ri-ex-id-producto', 'ri-ex-producto-seleccionado', '/getProductosAjax?q=');
    RI_setupAutocomplete('ri-mv-search-producto', 'ri-mv-dropdown-producto', 'ri-mv-id-producto', 'ri-mv-producto-seleccionado', '/getProductosAjax?q=');
    RI_setupAutocomplete('ri-va-search-producto', 'ri-va-dropdown-producto', 'ri-va-id-producto', 'ri-va-producto-seleccionado', '/getProductosAjax?q=');
    RI_setupAutocomplete('ri-cv-search-producto', 'ri-cv-dropdown-producto', 'ri-cv-id-producto', 'ri-cv-producto-seleccionado', '/getProductosAjax?q=');
    RI_setupAutocomplete('ri-cv-search-cliente', 'ri-cv-dropdown-cliente', 'ri-cv-id-cliente', 'ri-cv-cliente-seleccionado', '/getClientesAjax?q=');
    RI_setupAutocomplete('ri-au-search-producto', 'ri-au-dropdown-producto', 'ri-au-id-producto', 'ri-au-producto-seleccionado', '/getProductosAjax?q=');

    // El selector Año de Movimientos viene con un año elegido; las fechas (el filtro
    // real) hay que derivarlas al cargar, sin lanzar todavía ninguna consulta.
    window.RI_Movimientos.cambiarMesAnio();
});
