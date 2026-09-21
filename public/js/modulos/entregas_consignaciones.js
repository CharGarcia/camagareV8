/**
 * Módulo Entregas de Consignaciones (solo lectura): tabla + KPIs + modal de detalle
 * (mapa Leaflet + firma), construido a partir del data-row de cada fila — el listado
 * del backend ya trae todo lo necesario, sin AJAX adicional al abrir el detalle.
 * Por defecto lista las consignaciones PENDIENTES de entregar; con el filtro de estado
 * se ven también las entregadas (con su evidencia).
 */

const ENTC_NUM_COLUMNAS = 15;

function entcEscHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

document.addEventListener('DOMContentLoaded', function () {
    // Motor global de ordenamiento (favoritos.js): persiste __ordenCol__/__ordenDir__
    // y recarga la página; entcCargarGrid() solo da feedback inmediato mientras tanto.
    if (typeof window.CMG_initSort === 'function') {
        window.CMG_initSort('entregas-consignaciones', (col, dir) => {
            g_ordenCol = col;
            g_ordenDir = dir;
            g_paginaActual = 1;
            entcCargarGrid();
        }, { col: g_ordenCol, dir: g_ordenDir, container: '#tablaEntregasConsignaciones' });
    }
});

function entcCambiarPagina(p) {
    if (p < 1) return;
    g_paginaActual = p;
    entcCargarGrid();
}

async function entcCargarGrid() {
    // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa en vez de
    // vaciarse; también al paginar, ordenar o registrar una entrega.
    const tbody = document.getElementById('entc_grid_body');
    if (tbody) tbody.classList.add('fm-cargando-target');
    // Solo vale la ÚLTIMA búsqueda: la anterior se cancela y nunca pinta encima.
    if (window.ENTC_busquedaCtrl) window.ENTC_busquedaCtrl.abort();
    const ctrl = new AbortController();
    window.ENTC_busquedaCtrl = ctrl;
    try {
        const bInput = document.getElementById('b');
        const buscar = bInput ? bInput.value : '';

        const params = new URLSearchParams({
            b: buscar,
            page: g_paginaActual,
            sort: g_ordenCol,
            dir: g_ordenDir,
        });

        const res = await fetch(`${RUTA_MODULO_ENTC}/searchAjax?${params.toString()}`, { signal: ctrl.signal });
        if (!res.ok) throw new Error('Error en red');
        const data = await res.json();
        if (ctrl !== window.ENTC_busquedaCtrl) return;

        if (data.ok) {
            tbody.innerHTML = data.rows;
            document.getElementById('entc_pagination_info').textContent = data.info;
            document.getElementById('entc_pagination_controls').innerHTML = data.pagination;
            document.getElementById('entc_pdf_url').href = data.pdf_url;
            document.getElementById('entc_excel_url').href = data.excel_url;
            entcActualizarKpis(data.resumen);
        }
    } catch (e) {
        if (e.name === 'AbortError') return;
        console.error(e);
        if (window.Swal) Swal.fire('Error', 'No se pudo cargar la lista de consignaciones', 'error');
    } finally {
        if (tbody && ctrl === window.ENTC_busquedaCtrl) tbody.classList.remove('fm-cargando-target');
    }
}

function entcActualizarKpis(resumen) {
    if (!resumen) return;
    const map = {
        pendientes: resumen.pendientes ?? 0,
        total_entregas: resumen.total_entregas ?? 0,
        total_movil: resumen.total_movil ?? 0,
        total_web: resumen.total_web ?? 0,
        incompletas: resumen.incompletas ?? 0,
        horas_promedio: (resumen.horas_promedio !== null && resumen.horas_promedio !== undefined) ? `${resumen.horas_promedio}h` : '—',
    };
    Object.keys(map).forEach(key => {
        const el = document.querySelector(`[data-kpi="${key}"]`);
        if (el) el.textContent = map[key];
    });
}

// ── Marcar entrega (única acción de escritura del módulo) ────────────────────
let _entcDetalleActual = null;

// Ubicación del navegador (GPS). Nunca rechaza: si el usuario deniega o el dispositivo
// no tiene GPS, resuelve null y la entrega se registra solo con hora + usuario
// (mismo comportamiento que consObtenerUbicacion() en Consignaciones en Ventas).
function entcObtenerUbicacion() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({
                lat: pos.coords.latitude,
                lon: pos.coords.longitude,
                precision: (pos.coords.accuracy != null) ? Math.round(pos.coords.accuracy) : null,
            }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
        );
    });
}

/**
 * Confirma y registra la entrega de una consignación pendiente: pide una observación
 * opcional, captura la ubicación del navegador y llama a marcarEntregadaAjax. Al terminar
 * recarga el listado (la fila desaparece de "Pendientes") y cierra el modal si estaba abierto.
 */
async function entcMarcarEntregada(idConsignacion, numero) {
    if (!idConsignacion) return;

    const opciones = {
        title: `¿Marcar ${numero || 'la consignación'} como entregada?`,
        html: '<div class="small text-muted mb-2">Se registrará la entrega con la ubicación actual, la fecha/hora y su usuario.</div>',
        input: 'textarea',
        inputLabel: 'Observación (opcional)',
        inputPlaceholder: 'Ej.: recibió el guardia, se dejó en bodega…',
        inputAttributes: { maxlength: 500, rows: 3 },
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-geo-alt me-1"></i> Sí, registrar entrega',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
    };

    // Llamada desde el detalle: el modal sigue abierto DETRÁS del popup y su "focus trap"
    // (Bootstrap) devuelve el foco al modal en cuanto lo recibe algo de fuera — el popup
    // cuelga de <body>, así que el cuadro de la observación se veía pero rebotaba el foco y
    // no dejaba escribir. Con `target` el popup cuelga DENTRO del modal y el trap lo acepta
    // (mismo patrón que factura_venta y pedidos al pedir el correo). Desde el botón
    // "Entregar" de la fila no hay modal abierto: ahí el popup cuelga de <body>, como siempre
    // (si se apuntara al modal cerrado —display:none— el popup no se vería).
    const modalDetalleEl = document.getElementById('modalEntregaDetalle');
    if (modalDetalleEl && modalDetalleEl.classList.contains('show')) {
        opciones.target = modalDetalleEl;
    }

    const c = await Swal.fire(opciones);
    if (!c.isConfirmed) return;

    Swal.fire({ title: 'Registrando entrega…', text: 'Obteniendo ubicación', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const ubic = await entcObtenerUbicacion();

    try {
        const fd = new FormData();
        fd.append('id', idConsignacion);
        fd.append('observaciones', (c.value || '').trim());
        if (ubic) {
            fd.append('latitud', ubic.lat);
            fd.append('longitud', ubic.lon);
            if (ubic.precision != null) fd.append('precision_m', ubic.precision);
        }
        const res  = await fetch(`${RUTA_MODULO_ENTC}/marcarEntregadaAjax`, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'No se pudo registrar la entrega');

        const modalEl = document.getElementById('modalEntregaDetalle');
        const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
        if (modal) modal.hide();

        Swal.fire({
            icon: 'success',
            title: 'Entrega registrada',
            text: ubic ? 'Con ubicación GPS.' : 'Sin ubicación GPS (no disponible o denegada).',
            timer: 1600,
            showConfirmButton: false,
        });
        entcCargarGrid();
    } catch (e) {
        console.error(e);
        Swal.fire('Error', e.message || 'No se pudo registrar la entrega', 'error');
    }
}

/** Botón del pie del modal de detalle: usa la consignación que está abierta. */
function entcMarcarEntregadaDesdeModal() {
    const r = _entcDetalleActual;
    if (!r || !r.pendiente) return;
    entcMarcarEntregada(parseInt(r.id_consignacion, 10), `${r.serie || ''}-${r.secuencial || ''}`);
}

// ── Modal de detalle (mapa + firma) ──────────────────────────────────────────
let _entcMapa = null;

function entcBadgeEstado(estado) {
    switch (estado) {
        case 'Emitida':
            return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-2"><i class="bi bi-hourglass-split me-1"></i>Pendiente</span>';
        case 'Entregada':
            return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2"><i class="bi bi-check2-circle me-1"></i>Entregada</span>';
        case 'Facturada':
            return '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-2"><i class="bi bi-receipt me-1"></i>Facturada</span>';
        default:
            return `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2">${entcEscHtml(estado || '—')}</span>`;
    }
}

function entcBadgeCanal(canal) {
    if (!canal) return '';
    return canal === 'web'
        ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-2"><i class="bi bi-display me-1"></i>Web</span>'
        : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-2"><i class="bi bi-phone me-1"></i>App móvil</span>';
}

function entcAbrirDetalle(trEl) {
    let r;
    try {
        r = JSON.parse(trEl.dataset.row);
    } catch (e) {
        return;
    }

    const pendiente   = !!r.pendiente;
    const tieneEntrega = !!r.id;

    document.getElementById('entc_det_titulo').textContent = pendiente ? 'Pendiente de entrega' : 'Entrega';
    document.getElementById('entc_det_numero').textContent = `${r.serie || ''}-${r.secuencial || ''}`;
    document.getElementById('entc_det_canal_badge').innerHTML = entcBadgeEstado(r.estado_consignacion) + (tieneEntrega ? entcBadgeCanal(r.canal || 'movil') : '');

    document.getElementById('entc_det_cliente').textContent = r.cliente_nombre || '—';
    document.getElementById('entc_det_direccion').textContent = r.cliente_direccion || '—';
    document.getElementById('entc_det_emision').textContent = r.fecha_emision_fmt || '—';
    document.getElementById('entc_det_programada').textContent = r.fecha_entrega_fmt || '—';
    document.getElementById('entc_det_responsable').textContent = r.responsable_traslado_nombre || '—';
    document.getElementById('entc_det_dias').textContent = (r.dias_espera !== null && r.dias_espera !== undefined) ? `${r.dias_espera} día(s)` : '—';
    document.getElementById('entc_det_obs_cons').textContent = r.observaciones_consignacion || '—';

    // Bloque de evidencia: solo tiene sentido cuando existe una entrega registrada.
    document.getElementById('entc_det_evidencia').style.display = tieneEntrega ? '' : 'none';
    document.getElementById('entc_det_sin_evidencia').style.display = tieneEntrega ? 'none' : '';
    document.getElementById('entc_det_sin_evidencia').textContent = pendiente
        ? 'Esta consignación aún no ha sido entregada: no hay evidencia (GPS/firma) registrada.'
        : 'Consignación marcada como entregada sin evidencia registrada (GPS/firma).';

    document.getElementById('entc_det_fecha').textContent = r.capturado_en_fmt || '—';
    document.getElementById('entc_det_registrado_por').textContent = r.registrado_por || '—';
    document.getElementById('entc_det_dispositivo').textContent = r.dispositivo_id || '—';
    document.getElementById('entc_det_obs').textContent = r.observaciones || '—';

    const lat = parseFloat(r.latitud), lon = parseFloat(r.longitud);
    const tieneGps = tieneEntrega && !isNaN(lat) && !isNaN(lon) && (lat !== 0 || lon !== 0);

    document.getElementById('entc_det_fila_lat').style.display = tieneGps ? '' : 'none';
    document.getElementById('entc_det_fila_lon').style.display = tieneGps ? '' : 'none';
    document.getElementById('entc_det_lat').textContent = tieneGps ? lat.toFixed(6) : '—';
    document.getElementById('entc_det_lon').textContent = tieneGps ? lon.toFixed(6) : '—';
    document.getElementById('entc_det_precision').textContent = r.precision_m ? `±${parseFloat(r.precision_m).toFixed(0)} m` : '—';

    const firmaEl = document.getElementById('entc_det_firma');
    firmaEl.innerHTML = r.firma_url
        ? `<img src="${entcEscHtml(r.firma_url)}" alt="Firma de recepción" style="max-width:100%;max-height:150px;border:1px solid #dee2e6;border-radius:6px;background:#fff;">`
        : '<span class="text-muted small">Sin firma registrada.</span>';

    const mapaDiv = document.getElementById('entc_det_mapa');
    const sinGpsDiv = document.getElementById('entc_det_sin_gps');
    const gmapsDiv = document.getElementById('entc_det_gmaps');

    if (_entcMapa) { _entcMapa.remove(); _entcMapa = null; }

    if (tieneGps) {
        mapaDiv.style.display = '';
        sinGpsDiv.style.display = 'none';
        gmapsDiv.style.display = '';
        gmapsDiv.querySelector('a').href = `https://www.google.com/maps?q=${lat},${lon}`;
    } else {
        mapaDiv.style.display = 'none';
        sinGpsDiv.style.display = '';
        sinGpsDiv.textContent = pendiente
            ? 'Pendiente de entrega: el punto de entrega se registrará cuando el repartidor la confirme.'
            : 'Sin coordenadas GPS registradas para esta entrega.';
        gmapsDiv.style.display = 'none';
    }

    // Botón "Marcar como entregada" del pie: solo en pendientes y con permiso Actualizar.
    _entcDetalleActual = r;
    const btnEntregar = document.getElementById('entc_det_btn_entregar');
    if (btnEntregar) btnEntregar.style.display = (pendiente && window.ENTC_PUEDE_MARCAR) ? '' : 'none';

    const modalEl = document.getElementById('modalEntregaDetalle');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    if (tieneGps && window.L) {
        modalEl.addEventListener('shown.bs.modal', function onShown() {
            modalEl.removeEventListener('shown.bs.modal', onShown);
            try {
                _entcMapa = L.map('entc_det_mapa', { preferCanvas: true }).setView([lat, lon], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    maxZoom: 19,
                }).addTo(_entcMapa);
                L.marker([lat, lon]).addTo(_entcMapa).bindPopup('Lugar de entrega').openPopup();
                setTimeout(() => _entcMapa && _entcMapa.invalidateSize(), 200);
            } catch (mapErr) { /* si falla el mapa, los datos igual quedan visibles */ }
        }, { once: true });
    }
}
