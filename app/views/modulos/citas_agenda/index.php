<?php
/** @var array  $perm */
/** @var array  $tipos */
/** @var array  $recursos */
/** @var array  $vistaConfig */
/** @var string $rutaModulo */

$urlBase     = BASE_URL . '/modulos/citas-agenda';
$urlCfg      = BASE_URL . '/modulos/citas-configuracion';
$vistaConfig = $vistaConfig ?? [];
$rutaModulo  = $rutaModulo  ?? 'modulos/citas-agenda';
?>
<!-- FullCalendar (solo en esta vista) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.css">
<!-- FiltrosBusqueda -->
<link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
<script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>

<style>
    .agenda-scroll { max-height: calc(100vh - 260px); overflow-y: auto; }
    .agenda-scroll thead th {
        position: sticky; top: 0; z-index: 1;
        background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6;
    }
    .cita-row { cursor: pointer; }
    .cita-row:hover { background-color: rgba(0,0,0,.04); }
    /* resizer visual para columnas */
    .cmg-resizer {
        position: absolute; right: 0; top: 0; height: 100%;
        width: 5px; cursor: col-resize; user-select: none;
    }
    .cmg-resizer:hover, .cmg-resizer.resizing { background: #0d6efd44; }
    thead th { position: relative; }
</style>

<script>
const URL_AGENDA = '<?= $urlBase ?>';
</script>

<!-- ─── ENCABEZADO ──────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-calendar3 text-primary me-2"></i>Agenda de Citas</h4>
        <small class="text-muted">Gestión y visualización de citas</small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-primary active" id="btnVistaCalendario" onclick="cambiarVista('calendario')">
                <i class="bi bi-calendar3 me-1"></i>Calendario
            </button>
            <button type="button" class="btn btn-outline-primary" id="btnVistaLista" onclick="cambiarVista('lista')">
                <i class="bi bi-list-ul me-1"></i>Lista
            </button>
        </div>
        <?php if ($perm['crear']): ?>
        <button class="btn btn-success btn-sm" onclick="abrirModalCita()">
            <i class="bi bi-plus-circle me-1"></i>Nueva Cita
        </button>
        <?php endif; ?>
        <a href="<?= $urlCfg ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-gear me-1"></i>Configuración
        </a>
    </div>
</div>

<!-- ─── FILTROS COMPARTIDOS (estado, tipo, recurso + extras lista) ────────────── -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <div class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small mb-1">Estado</label>
                <select id="flt-estado" class="form-select form-select-sm" onchange="aplicarFiltros()">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="confirmada">Confirmada</option>
                    <option value="en_curso">En curso</option>
                    <option value="completada">Completada</option>
                    <option value="cancelada">Cancelada</option>
                    <option value="no_asistio">No asistió</option>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">Tipo de cita</label>
                <select id="flt-tipo" class="form-select form-select-sm" onchange="aplicarFiltros()">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tipos as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">Recurso</label>
                <select id="flt-recurso" class="form-select form-select-sm" onchange="aplicarFiltros()">
                    <option value="">Todos los recursos</option>
                    <?php foreach ($recursos as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Filtros fecha: solo en vista lista -->
            <div class="col-auto d-none lista-extra">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" id="flt-desde" class="form-control form-control-sm" onchange="citasListaCargar()">
            </div>
            <div class="col-auto d-none lista-extra">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" id="flt-hasta" class="form-control form-control-sm" onchange="citasListaCargar()">
            </div>
        </div>
    </div>
</div>

<!-- ─── VISTA CALENDARIO ─────────────────────────────────────────────────────── -->
<div id="vistaCalendario" class="card border-0 shadow-sm">
    <div class="card-body p-2 p-md-3">
        <div id="calendarioCitas" style="min-height:600px;"></div>
    </div>
</div>

<!-- ─── VISTA LISTA ──────────────────────────────────────────────────────────── -->
<div id="vistaLista" class="d-none">
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

            <!-- Izquierda: FiltrosBusqueda + columnas + exportación -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Contenedor FiltrosBusqueda (lazy init en cambiarVista) -->
                <div id="fbBuscadorCitas" style="width:460px;"></div>
                <input type="hidden" id="buscarCitas" value="">

                <?php
                $columnasTabla = [
                    'fecha_inicio'   => 'Fecha inicio',
                    'nombre_tipo'    => 'Tipo',
                    'nombre_cliente' => 'Cliente',
                    'nombre_recurso' => 'Recurso',
                    'titulo'         => 'Título',
                    'estado'         => 'Estado',
                    'origen'         => 'Origen',
                ];
                ?>
                <div class="btn-group btn-group-sm">
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo) ?>
                    <a id="btnExportPdfCitas" href="<?= $urlBase ?>/export-pdf"
                       class="btn btn-outline-danger" title="Exportar PDF">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </a>
                    <a id="btnExportExcelCitas" href="<?= $urlBase ?>/export-excel"
                       class="btn btn-outline-success" title="Exportar Excel">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Derecha: paginación -->
            <div class="d-flex align-items-center gap-3">
                <span id="listaPaginaInfo" class="text-muted small fw-medium"></span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="btnPrevCitas" disabled
                            onclick="cambiarPaginaCitas(_listaPagina - 1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnNextCitas" disabled
                            onclick="cambiarPaginaCitas(_listaPagina + 1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card-body p-0">
            <div class="agenda-scroll w-100">
                <table class="table table-hover table-sm mb-0" style="table-layout:fixed;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:150px;" class="sortable-header ps-3" role="button"
                                data-sort="fecha_inicio" data-col="fecha_inicio">
                                Fecha inicio <i class="bi bi-arrow-down-up ms-1 text-muted small"></i>
                            </th>
                            <th style="width:140px;" class="sortable-header" role="button"
                                data-sort="nombre_tipo" data-col="nombre_tipo">
                                Tipo <i class="bi bi-arrow-down-up ms-1 text-muted small"></i>
                            </th>
                            <th class="sortable-header" role="button"
                                data-sort="nombre_cliente" data-col="nombre_cliente">
                                Cliente <i class="bi bi-arrow-down-up ms-1 text-muted small"></i>
                            </th>
                            <th style="width:150px;" class="sortable-header" role="button"
                                data-sort="nombre_recurso" data-col="nombre_recurso">
                                Recurso <i class="bi bi-arrow-down-up ms-1 text-muted small"></i>
                            </th>
                            <th style="width:150px;" class="sortable-header" role="button"
                                data-sort="titulo" data-col="titulo">
                                Título <i class="bi bi-arrow-down-up ms-1 text-muted small"></i>
                            </th>
                            <th style="width:115px;" class="sortable-header" role="button"
                                data-sort="estado" data-col="estado">
                                Estado <i class="bi bi-arrow-down-up ms-1 text-muted small"></i>
                            </th>
                            <th style="width:85px;" class="sortable-header" role="button"
                                data-sort="origen" data-col="origen">
                                Origen <i class="bi bi-arrow-down-up ms-1 text-muted small"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCitas">
                        <tr><td colspan="7" class="text-center py-4 text-muted">
                            <span class="spinner-border spinner-border-sm me-2"></span>Cargando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ─── MODAL ────────────────────────────────────────────────────────────────── -->
<?php require __DIR__ . '/_modal_cita.php'; ?>

<!-- ─── LEYENDA ──────────────────────────────────────────────────────────────── -->
<div class="d-flex flex-wrap gap-2 mt-3" id="vistaCalendarioLeyenda">
    <small class="text-muted me-1">Estados:</small>
    <span class="badge rounded-pill" style="background:#ffc107;color:#000;">Pendiente</span>
    <span class="badge rounded-pill" style="background:#0d6efd;">Confirmada</span>
    <span class="badge rounded-pill" style="background:#0dcaf0;color:#000;">En curso</span>
    <span class="badge rounded-pill" style="background:#198754;">Completada</span>
    <span class="badge rounded-pill" style="background:#6c757d;">Cancelada</span>
    <span class="badge rounded-pill" style="background:#dc3545;">No asistió</span>
</div>

<!-- ─── FULLCALENDAR JS ───────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>

<script>
let _calendarioCitas  = null;
let _vistaActual      = 'calendario';
let _listaPagina      = 1;
let _listaTotalPags   = 1;
let _listaSort        = '<?= htmlspecialchars($vistaConfig['__ordenCol__'] ?? 'fecha_inicio') ?>';
let _listaSortDir     = '<?= htmlspecialchars(strtoupper($vistaConfig['__ordenDir__'] ?? 'DESC')) ?>';
let _debounceTimer    = null;
let _fbCitas          = null;   // instancia de FiltrosBusqueda
let _fbIniciado       = false;

// ─── INIT ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    iniciarCalendario();

    // Ordenamiento de columnas en la lista
    document.querySelectorAll('th.sortable-header').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.sort;
            if (_listaSort === col) {
                _listaSortDir = _listaSortDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                _listaSort    = col;
                _listaSortDir = 'ASC';
            }
            _listaPagina = 1;
            actualizarIconosOrden();
            if (typeof window.guardarOrdenacionVista === 'function') {
                window.guardarOrdenacionVista('citas_agenda', _listaSort, _listaSortDir);
            }
            citasListaCargar();
        });
    });

    // Marcar icono de orden inicial
    actualizarIconosOrden();
});

// ─── INICIALIZAR FiltrosBusqueda (lazy, al mostrar lista) ─────────────────────

function initFiltrosBusqueda() {
    if (_fbIniciado || !window.FiltrosBusqueda) return;
    _fbIniciado = true;

    _fbCitas = new FiltrosBusqueda({
        containerId:   'fbBuscadorCitas',
        hiddenInputId: 'buscarCitas',
        placeholder:   'Buscar citas...',
        fields: [
            { key: 'q',       label: 'General',    icon: 'bi-search',      type: 'text' },
            { key: 'cliente', label: 'Cliente',    icon: 'bi-person',       type: 'text' },
            { key: 'titulo',  label: 'Título',     icon: 'bi-tag',          type: 'text' },
            { key: 'tipo',    label: 'Tipo cita',  icon: 'bi-bookmark',     type: 'text' },
            { key: 'recurso', label: 'Recurso',    icon: 'bi-person-gear',  type: 'text' },
            { key: 'fecha',   label: 'Fecha',      icon: 'bi-calendar',     type: 'date_range' },
        ],
        quickFilters: [
            { id: 'qf_hoy',        label: 'Hoy',          mk: () => FiltrosBusqueda.helpers.hoyMismo('fecha') },
            { id: 'qf_mes',        label: 'Este mes',     mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
            { id: 'qf_anterior',   label: 'Mes anterior', mk: () => FiltrosBusqueda.helpers.mesPasado('fecha') },
            { id: 'qf_pendiente',  label: 'Pendientes',   mk: () => ({ key: 'estado', op: '=', value: 'pendiente',  display: 'Pendiente'  }) },
            { id: 'qf_confirmada', label: 'Confirmadas',  mk: () => ({ key: 'estado', op: '=', value: 'confirmada', display: 'Confirmada' }) },
            { id: 'qf_portal',     label: 'Del portal',   mk: () => ({ key: 'origen', op: '=', value: 'portal',     display: 'Portal'     }) },
        ],
        onApply: () => { _listaPagina = 1; citasListaCargar(); },
    });
    _fbCitas.init();
}

// ─── CALENDARIO ───────────────────────────────────────────────────────────────

function iniciarCalendario() {
    const el = document.getElementById('calendarioCitas');
    if (!el) return;

    _calendarioCitas = new FullCalendar.Calendar(el, {
        locale:        'es',
        initialView:   'dayGridMonth',
        headerToolbar: {
            left:  'prev,next today',
            center:'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        buttonText: { today:'Hoy', month:'Mes', week:'Semana', day:'Día', list:'Agenda' },
        height:       'auto',
        nowIndicator: true,
        editable:     false,
        selectable:   true,
        allDaySlot:   false,
        slotMinTime:  '06:00:00',
        slotMaxTime:  '22:00:00',
        slotDuration: '00:30:00',
        eventTimeFormat: { hour:'2-digit', minute:'2-digit', hour12: false },

        events(info, success, failure) {
            const params = new URLSearchParams({
                start:        info.startStr,
                end:          info.endStr,
                estado:       document.getElementById('flt-estado').value,
                id_recurso:   document.getElementById('flt-recurso').value,
                id_tipo_cita: document.getElementById('flt-tipo').value,
            });
            fetch(`${URL_AGENDA}/eventos-ajax?${params}`)
                .then(r => r.json()).then(data => success(data)).catch(() => failure());
        },

        select(info) {
            <?php if ($perm['crear']): ?>
            abrirModalCita(null, info.startStr);
            <?php endif; ?>
        },

        eventClick(info) {
            abrirCitaById(parseInt(info.event.id));
        },

        eventDidMount(info) {
            const ep = info.event.extendedProps;
            let tip = ep.nombre_tipo || '';
            if (ep.nombre_cliente) tip += (tip ? ' · ' : '') + ep.nombre_cliente;
            if (ep.nombre_recurso) tip += (tip ? ' · ' : '') + ep.nombre_recurso;
            if (ep.notas)          tip += '\n' + ep.notas;
            if (tip) info.el.title = tip;
        },
    });

    _calendarioCitas.render();
}

function aplicarFiltros() {
    if (_vistaActual === 'calendario' && _calendarioCitas) {
        _calendarioCitas.refetchEvents();
    } else {
        _listaPagina = 1;
        citasListaCargar();
    }
}

// ─── TOGGLE VISTA ─────────────────────────────────────────────────────────────

function cambiarVista(vista) {
    _vistaActual = vista;
    const esCal  = vista === 'calendario';

    document.getElementById('vistaCalendario').classList.toggle('d-none', !esCal);
    document.getElementById('vistaLista').classList.toggle('d-none', esCal);
    document.getElementById('vistaCalendarioLeyenda').classList.toggle('d-none', !esCal);

    document.getElementById('btnVistaCalendario').className = esCal
        ? 'btn btn-primary btn-sm active'
        : 'btn btn-outline-primary btn-sm';
    document.getElementById('btnVistaLista').className = !esCal
        ? 'btn btn-primary btn-sm active'
        : 'btn btn-outline-primary btn-sm';

    // Mostrar/ocultar filtros de rango de fechas (solo en lista)
    document.querySelectorAll('.lista-extra').forEach(el => el.classList.toggle('d-none', esCal));

    if (!esCal) {
        // Iniciar FiltrosBusqueda la primera vez que se muestra la lista
        initFiltrosBusqueda();
        _listaPagina = 1;
        citasListaCargar();
    } else if (_calendarioCitas) {
        _calendarioCitas.updateSize();
    }
}

// ─── LISTA ────────────────────────────────────────────────────────────────────

function cambiarPaginaCitas(n) {
    if (n < 1 || n > _listaTotalPags) return;
    _listaPagina = n;
    citasListaCargar();
}

function citasListaCargar() {
    const params = new URLSearchParams({
        q:            document.getElementById('buscarCitas')?.value ?? '',
        estado:       document.getElementById('flt-estado').value,
        id_tipo_cita: document.getElementById('flt-tipo').value,
        id_recurso:   document.getElementById('flt-recurso').value,
        fecha_desde:  document.getElementById('flt-desde')?.value ?? '',
        fecha_hasta:  document.getElementById('flt-hasta')?.value ?? '',
        page:         _listaPagina,
        per_page:     20,
        sort:         _listaSort,
        dir:          _listaSortDir,
    });

    const tbody = document.getElementById('tbodyCitas');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">'
            + '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...</td></tr>';
    }

    fetch(`${URL_AGENDA}/search-ajax?${params}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) return;
            renderListaCitas(res.data, res.total, res.page ?? _listaPagina, res.per_page ?? 20);

            // Info paginación
            document.getElementById('listaPaginaInfo').textContent = res.info ?? '';

            // URLs de exportación dinámicas
            if (res.pdf_url)   document.getElementById('btnExportPdfCitas').href   = res.pdf_url;
            if (res.excel_url) document.getElementById('btnExportExcelCitas').href = res.excel_url;

            // Botones prev/next
            const total     = res.total ?? 0;
            const perPage   = res.per_page ?? 20;
            _listaTotalPags = Math.max(1, Math.ceil(total / perPage));
            document.getElementById('btnPrevCitas').disabled = _listaPagina <= 1;
            document.getElementById('btnNextCitas').disabled = _listaPagina >= _listaTotalPags;
        });
}

// ─── RENDER FILAS ─────────────────────────────────────────────────────────────

const ESTADO_COLOR = {
    pendiente:'warning', confirmada:'primary', en_curso:'info',
    completada:'success', cancelada:'secondary', no_asistio:'danger',
};
const ESTADO_LABEL = {
    pendiente:'Pendiente', confirmada:'Confirmada', en_curso:'En curso',
    completada:'Completada', cancelada:'Cancelada', no_asistio:'No asistió',
};
const ORIGEN_BADGE = {
    interno: '<span class="badge bg-info-subtle text-info border border-info-subtle">Interno</span>',
    portal:  '<span class="badge bg-success-subtle text-success border border-success-subtle">Portal</span>',
    widget:  '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Widget</span>',
};

function renderListaCitas(rows, total) {
    const tbody = document.getElementById('tbodyCitas');
    if (!rows || !rows.length) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">'
            + '<i class="bi bi-calendar-x fs-3 d-block mb-2"></i>No hay citas para mostrar.</td></tr>';
        return;
    }
    if (!tbody) return;

    tbody.innerHTML = rows.map(r => {
        const inicio    = r.fecha_inicio ? r.fecha_inicio.replace('T',' ').substring(0,16) : '—';
        const estadoCls = ESTADO_COLOR[r.estado] || 'secondary';
        const estadoLbl = ESTADO_LABEL[r.estado] || r.estado;
        const cliente   = r.nombre_cliente || '<em class="text-muted">—</em>';
        const tipo      = r.nombre_tipo    || '<em class="text-muted">—</em>';
        const recurso   = r.nombre_recurso || '<em class="text-muted">—</em>';
        const titulo    = r.titulo         || '<em class="text-muted">—</em>';
        const origen    = ORIGEN_BADGE[r.origen] || `<span class="badge bg-light text-dark border">${r.origen}</span>`;
        const dot       = r.color
            ? `<span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:${r.color};margin-right:4px;flex-shrink:0;"></span>`
            : '';
        return `<tr class="cita-row" onclick="abrirCitaById(${r.id})">
            <td class="text-nowrap small ps-3" data-col="fecha_inicio">${inicio}</td>
            <td class="small" data-col="nombre_tipo" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${dot}${tipo}</td>
            <td class="small" data-col="nombre_cliente" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${cliente}</td>
            <td class="small" data-col="nombre_recurso" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${recurso}</td>
            <td class="small" data-col="titulo" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${titulo}</td>
            <td data-col="estado"><span class="badge bg-${estadoCls}-subtle text-${estadoCls} border border-${estadoCls}-subtle">${estadoLbl}</span></td>
            <td data-col="origen">${origen}</td>
        </tr>`;
    }).join('');

    actualizarIconosOrden();
}

function actualizarIconosOrden() {
    document.querySelectorAll('th.sortable-header').forEach(th => {
        const icon  = th.querySelector('i');
        const field = th.dataset.sort;
        if (!icon) return;
        if (field === _listaSort) {
            icon.className = _listaSortDir === 'ASC'
                ? 'bi bi-sort-alpha-down text-primary ms-1'
                : 'bi bi-sort-alpha-up text-primary ms-1';
        } else {
            icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
        }
    });
}

// ─── ABRIR CITA ───────────────────────────────────────────────────────────────

function abrirCitaById(id) {
    fetch(`${URL_AGENDA}/get-ajax?id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (res.ok) abrirModalCita(res.data);
            else Swal.fire('Error', res.mensaje, 'error');
        });
}
</script>
