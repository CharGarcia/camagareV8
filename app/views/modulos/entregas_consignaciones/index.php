<?php
/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var string $rowsHtml   Filas ya renderizadas por el controlador (mismo HTML que searchAjax) */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $estadoEntrega  pendiente | entregada | todas (resuelto del filtro `estado:`) */
/** @var bool $puedeMarcar    Permiso Actualizar: muestra el botón "Entregar" en las pendientes */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array $vistaConfig */
/** @var array $resumen */
/** @var int $anioDesde */
/** @var int $anioHasta */

$base       = BASE_URL;
$urlBaseEnt = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$qsOrden    = '&orden=' . urlencode($ordenCol . ':' . $ordenDir);

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

// KPIs + filtros arriba de la tabla: el app-shell (borde a borde) asume título + una
// sola tabla, así que se desactiva para que la página tenga scroll normal (§9 CLAUDE.md).
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .entc-scroll {
        max-height: calc(100dvh - 330px);
        overflow: auto;
    }
    .entc-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
        white-space: nowrap;
    }
    .entc-row { cursor: pointer; }
    .entc-row:hover { background-color: rgba(0, 0, 0, .04); }
    .entc-kpi-card {
        border: 0;
        border-radius: .75rem;
        box-shadow: 0 .125rem .5rem rgba(0,0,0,.05);
        padding: .9rem 1.1rem;
        height: 100%;
    }
    .entc-kpi-value { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
    .entc-kpi-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; opacity: .75; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<!-- Encabezado -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($titulo) ?></h5>
</div>

<!-- KPIs -->
<div class="row g-2 mb-3" id="entc_kpis">
    <div class="col-6 col-md-2">
        <div class="entc-kpi-card bg-white">
            <div class="entc-kpi-label text-muted"><i class="bi bi-hourglass-split"></i> Pendientes de entregar</div>
            <div class="entc-kpi-value text-warning" data-kpi="pendientes"><?= (int) ($resumen['pendientes'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="entc-kpi-card bg-white">
            <div class="entc-kpi-label text-muted"><i class="bi bi-check2-circle"></i> Entregadas</div>
            <div class="entc-kpi-value text-success" data-kpi="total_entregas"><?= (int) ($resumen['total_entregas'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="entc-kpi-card bg-white">
            <div class="entc-kpi-label text-muted"><i class="bi bi-phone"></i> App móvil</div>
            <div class="entc-kpi-value text-primary" data-kpi="total_movil"><?= (int) ($resumen['total_movil'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="entc-kpi-card bg-white">
            <div class="entc-kpi-label text-muted"><i class="bi bi-display"></i> Web (manual)</div>
            <div class="entc-kpi-value text-info" data-kpi="total_web"><?= (int) ($resumen['total_web'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="entc-kpi-card bg-white">
            <div class="entc-kpi-label text-muted">Tiempo prom. emisión→entrega</div>
            <div class="entc-kpi-value text-dark" data-kpi="horas_promedio"><?= isset($resumen['horas_promedio']) && $resumen['horas_promedio'] !== null ? htmlspecialchars((string) $resumen['horas_promedio']) . 'h' : '—' ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="entc-kpi-card bg-white">
            <div class="entc-kpi-label text-muted">Evidencia incompleta</div>
            <div class="entc-kpi-value text-danger" data-kpi="incompletas"><?= (int) ($resumen['incompletas'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- Filtros y tabla -->
<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador: texto libre sobre las columnas del listado (sin sugerencias) + botón
            // embudo que abre un modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en EntregasConsignacionesRepository::construirWhere()
            // (`estado` y `producto` se resuelven aparte en ese repositorio).
            $opcionesResponsable = array_map(fn($r) => ['v' => (string) $r['id'], 'l' => $r['nombre']], $opcionesFiltros['responsables'] ?? []);
            $opcionesUsuario     = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $opcionesFiltros['usuarios'] ?? []);
            $siNo = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
            // Dos pestañas: "Entrega" (filtros por campo) y "Detalles" (solo la búsqueda libre
            // dentro de las consignaciones, ver `busquedaDetalle` abajo).
            $tE = 'Entrega';
            // Orden pensado en filas de 12 columnas:
            //   Documento: [Fecha de emisión 6][Entrega programada 6]
            //              [Fecha de entrega real 6][Estado de entrega 3][Canal 3]
            //              [Nº consignación 4][Firma 4][GPS 4]
            //              [Días 4][Registrado por 4][Producto 4]
            //   Cliente:   [Cliente 4][RUC 4][Dirección 4]
            //              [Responsable 6][Observaciones 6]
            $filtrosEntregas = [
                // ── Documento ──
                ['tab' => $tE, 'key' => 'emision',        'label' => 'Fecha de emisión',      'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tE, 'key' => 'programada',     'label' => 'Entrega programada',    'icon' => 'bi-calendar-check',  'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6],
                ['tab' => $tE, 'key' => 'entrega',        'label' => 'Fecha de entrega real', 'icon' => 'bi-calendar2-check', 'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6],
                // Sin filtro el listado muestra las pendientes (así lo resuelve el repositorio).
                ['tab' => $tE, 'key' => 'estado',         'label' => 'Estado de entrega',     'icon' => 'bi-hourglass-split', 'type' => 'select',       'grupo' => 'Documento', 'col' => 3,
                    'placeholderOption' => 'Pendientes de entregar', 'options' => [
                    ['v' => 'entregada', 'l' => 'Entregadas (y facturadas)'],
                    ['v' => 'todas',     'l' => 'Todas'],
                ]],
                ['tab' => $tE, 'key' => 'canal',          'label' => 'Canal',                 'icon' => 'bi-broadcast',       'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'movil', 'l' => 'App móvil'],
                    ['v' => 'web',   'l' => 'Web (manual)'],
                ]],
                ['tab' => $tE, 'key' => 'secuencial',     'label' => 'Nº consignación',       'icon' => 'bi-hash',            'type' => 'text',         'grupo' => 'Documento', 'col' => 4, 'placeholder' => '000000123'],
                ['tab' => $tE, 'key' => 'firma',          'label' => 'Firma',                 'icon' => 'bi-pen',             'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $siNo('Con firma', 'Sin firma')],
                ['tab' => $tE, 'key' => 'gps',            'label' => 'GPS',                   'icon' => 'bi-geo-alt',         'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $siNo('Con ubicación GPS', 'Sin ubicación GPS')],
                ['tab' => $tE, 'key' => 'dias',           'label' => 'Días',                  'icon' => 'bi-stopwatch',       'type' => 'number_range', 'grupo' => 'Documento', 'col' => 4],
                ['tab' => $tE, 'key' => 'id_usuario',     'label' => 'Registrado por',        'icon' => 'bi-person-gear',     'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesUsuario],
                ['tab' => $tE, 'key' => 'producto',       'label' => 'Producto',              'icon' => 'bi-box-seam',        'type' => 'text',         'grupo' => 'Documento', 'col' => 4, 'placeholder' => 'Código o nombre'],
                // ── Cliente ──
                ['tab' => $tE, 'key' => 'cliente',        'label' => 'Cliente',               'icon' => 'bi-person',          'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tE, 'key' => 'ruc',            'label' => 'RUC / Cédula',          'icon' => 'bi-card-text',       'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tE, 'key' => 'direccion',      'label' => 'Dirección',             'icon' => 'bi-geo',             'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tE, 'key' => 'id_responsable', 'label' => 'Responsable de traslado', 'icon' => 'bi-truck',         'type' => 'select',       'grupo' => 'Cliente', 'col' => 6, 'options' => $opcionesResponsable],
                ['tab' => $tE, 'key' => 'observaciones',  'label' => 'Observaciones de la entrega', 'icon' => 'bi-chat-left-text', 'type' => 'text',   'grupo' => 'Cliente', 'col' => 6],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorENTC"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <select id="entc_estado" class="form-select form-select-sm" style="width:130px" title="Estado de entrega">
                <option value="pendiente" <?= $estadoEntrega === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                <option value="entregada" <?= $estadoEntrega === 'entregada' ? 'selected' : '' ?>>Entregadas</option>
                <option value="todas" <?= $estadoEntrega === 'todas' ? 'selected' : '' ?>>Todas</option>
            </select>

            <select id="entc_anio" class="form-select form-select-sm" style="width:100px" title="Año de emisión">
                <option value="">Año</option>
                <?php for ($a = $anioHasta; $a >= $anioDesde; $a--): ?>
                    <option value="<?= $a ?>"><?= $a ?></option>
                <?php endfor; ?>
            </select>
            <select id="entc_mes" class="form-select form-select-sm" style="width:110px" title="Mes de emisión">
                <option value="">Mes</option>
                <?php
                $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
                foreach ($meses as $num => $nom): ?>
                    <option value="<?= $num ?>"><?= $nom ?></option>
                <?php endforeach; ?>
            </select>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    // Se guarda la instancia: los selects Estado / Año / Mes del encabezado
                    // aplican sus filtros a través de ella (entcAplicarEstado / entcAplicarAnioMes).
                    window.entcFiltros = new FiltrosModal({
                        containerId: 'fmBuscadorENTC',
                        hiddenInputId: 'b',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de entregas de consignaciones',
                        inputWidth: 420,
                        extraId: 'fmExtraENTC',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de las consignaciones (productos con
                        // lote/NUP y evidencias de entrega), en pendientes y entregadas.
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBaseEnt ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de las consignaciones',
                            placeholder: 'Producto, código, lote, NUP, bodega, dispositivo u observación de la entrega...',
                            columns: [
                                { key: 'origen',      label: 'Tipo' },
                                { key: 'tipo',        label: 'Código / Canal' },
                                { key: 'descripcion', label: 'Descripción' },
                                { key: 'extra',       label: 'Lote / NUP / Fecha', class: 'font-monospace' },
                                { key: 'cantidad',    label: 'Cant.', align: 'end' },
                                { key: 'numero',      label: 'Consignación', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',       label: 'Emisión' },
                                { key: 'cliente',     label: 'Cliente' },
                                { key: 'estado',      label: 'Estado' },
                            ],
                            // La coincidencia puede estar fuera del estado de entrega filtrado
                            // (por defecto, pendientes): se muestra con el estado que le corresponde.
                            onSelect: (row, fm) => {
                                // Cambios encadenados sin buscar; la búsqueda la lanza el último.
                                fm.quitarFiltro('estado', undefined, false);
                                if (!row.pendiente) fm.aplicarFiltro({ key: 'estado', op: '=', value: 'entregada' }, false, false);
                                fm.aplicarFiltro({ key: 'numero', value: row.numero });
                            },
                            onOpen: (row, fm) => {
                                fm.hide();
                                // entcAbrirDetalle lee la fila del listado de dataset.row.
                                setTimeout(() => entcAbrirDetalle({ dataset: { row: JSON.stringify(row.registro || {}) } }), 350);
                            },
                        },
                        fields: <?= json_encode($filtrosEntregas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#entc_grid_body',   // se atenúa mientras se busca
                        onApply: () => { entcSincronizarSelectsDesdeFiltros(); g_paginaActual = 1; return entcCargarGrid(); },
                    });
                    window.entcFiltros.init();

                    document.getElementById('entc_estado').addEventListener('change', entcAplicarEstado);
                    document.getElementById('entc_anio').addEventListener('change', entcAplicarAnioMes);
                    document.getElementById('entc_mes').addEventListener('change', entcAplicarAnioMes);
                });

                /** Quita un filtro (no negado) de la instancia y vuelve a buscar. */
                function entcQuitarFiltro(key) {
                    // quitarFiltro no busca si no había ese filtro: el listado ya está así.
                    window.entcFiltros.quitarFiltro(key);
                }

                // El select de estado es un atajo sobre el filtro "estado" del buscador. "Pendientes"
                // es el valor por defecto del backend, así que en ese caso se quita el chip en vez de
                // agregar uno redundante.
                function entcAplicarEstado() {
                    if (!window.entcFiltros) return;
                    const valor = document.getElementById('entc_estado').value;
                    if (!valor || valor === 'pendiente') {
                        entcQuitarFiltro('estado');
                        return;
                    }
                    window.entcFiltros.aplicarFiltro({ key: 'estado', op: '=', value: valor }, false);
                }

                // Año/Mes son atajos sobre el mismo filtro "emision": arman el rango completo del
                // año o del mes (emision:2026-08-01..2026-08-31), así el modal de filtros muestra
                // las dos fechas y el filtro no se pierde al pulsar Aplicar en él.
                function entcAplicarAnioMes() {
                    if (!window.entcFiltros) return;
                    const anio = document.getElementById('entc_anio').value;
                    const mes  = document.getElementById('entc_mes').value;
                    if (!anio) {
                        // Sin año no se puede componer "emision:" — quitar el filtro si existía.
                        entcQuitarFiltro('emision');
                        return;
                    }
                    const pad = n => String(n).padStart(2, '0');
                    const rango = mes
                        ? [`${anio}-${pad(mes)}-01`, `${anio}-${pad(mes)}-${pad(new Date(parseInt(anio, 10), parseInt(mes, 10), 0).getDate())}`]
                        : [`${anio}-01-01`, `${anio}-12-31`];
                    window.entcFiltros.aplicarFiltro({ key: 'emision', op: 'BETWEEN', value: rango }, false);
                }
                // Si el usuario borra un chip a mano (o cambia el filtro en el modal), reflejarlo en
                // los selects: solo muestran año/mes cuando el rango es exactamente un año o un mes.
                function entcSincronizarSelectsDesdeFiltros() {
                    if (!window.entcFiltros) return;
                    const fEmision = window.entcFiltros.getFiltro('emision');
                    let anioSel = '', mesSel = '';
                    if (fEmision && fEmision.op === 'BETWEEN' && Array.isArray(fEmision.value)) {
                        const [ini, fin] = fEmision.value.map(String);
                        const mi = ini.match(/^(\d{4})-(\d{2})-01$/);
                        const mf = fin.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        if (mi && mf && mi[1] === mf[1]) {
                            const anioOpt = document.querySelector(`#entc_anio option[value="${mi[1]}"]`);
                            if (mi[2] === '01' && mf[2] === '12' && mf[3] === '31') {
                                anioSel = anioOpt ? mi[1] : '';
                            } else if (mi[2] === mf[2] && parseInt(mf[3], 10) === new Date(parseInt(mi[1], 10), parseInt(mi[2], 10), 0).getDate()) {
                                anioSel = anioOpt ? mi[1] : '';
                                mesSel  = anioSel ? String(parseInt(mi[2], 10)) : '';
                            }
                        }
                    }
                    document.getElementById('entc_anio').value = anioSel;
                    document.getElementById('entc_mes').value  = mesSel;
                    const fEstado = window.entcFiltros.getFiltro('estado');
                    const selEstado = document.getElementById('entc_estado');
                    const v = fEstado ? String(Array.isArray(fEstado.value) ? fEstado.value[0] : fEstado.value).toLowerCase() : 'pendiente';
                    selEstado.value = ['pendiente', 'entregada', 'todas'].includes(v) ? v : 'pendiente';
                }
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraENTC" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha_emision'  => 'Emisión',
                    'secuencial'     => 'Consignación',
                    'cliente'        => 'Cliente',
                    'direccion'      => 'Dirección',
                    'responsable'    => 'Responsable',
                    'fecha_entrega'  => 'Entrega programada',
                    'estado'         => 'Estado',
                    'dias'           => 'Días',
                    'capturado_en'   => 'Fecha/hora entrega',
                    'canal'          => 'Canal',
                    'firma'          => 'Firma',
                    'gps'            => 'GPS',
                    'registrado_por' => 'Registrado por',
                    'observaciones'  => 'Observaciones',
                    'acciones'       => 'Acciones',
                ], $vistaConfig ?? [], basename($rutaModulo)); ?>

                <a id="entc_pdf_url" class="btn btn-outline-danger pdf-export-btn" href="<?= $urlBaseEnt ?>/exportPdf?b=<?= urlencode($buscar) ?><?= $qsOrden ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="entc_excel_url" class="btn btn-outline-success excel-export-btn" href="<?= $urlBaseEnt ?>/exportExcel?b=<?= urlencode($buscar) ?><?= $qsOrden ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i><span class="d-none d-md-inline"> Excel</span></a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="entc_pagination_info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="entc_pagination_controls" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="entcCambiarPagina(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="entcCambiarPagina(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="entc-scroll w-100">
            <table class="table table-hover table-sm mb-0" id="tablaEntregasConsignaciones">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-col="fecha_emision" data-sort="fecha_emision">Emisión <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="secuencial" data-sort="secuencial">Consignación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="cliente" data-sort="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="direccion">Dirección</th>
                        <th class="sortable-header" role="button" data-col="responsable" data-sort="responsable">Responsable <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="fecha_entrega" data-sort="fecha_entrega">Entrega programada <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-col="estado" data-sort="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-col="dias" data-sort="dias" title="Días desde la emisión hasta la entrega (o hasta hoy si sigue pendiente)">Días <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="capturado_en" data-sort="capturado_en">Fecha/hora entrega <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-col="canal" data-sort="canal">Canal <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="firma">Firma</th>
                        <th class="text-center" data-col="gps">GPS</th>
                        <th data-col="registrado_por">Registrado por</th>
                        <th data-col="observaciones">Observaciones</th>
                        <th class="text-center pe-3" data-col="acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody id="entc_grid_body">
                    <?= $rowsHtml ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_detalle.php'; ?>

<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/entregas_consignaciones.js?v=<?= asset_ver('/js/modulos/entregas_consignaciones.js') ?>"></script>
<script>
    window.RUTA_MODULO_ENTC = '<?= $urlBaseEnt ?>';
    window.ENTC_PUEDE_MARCAR = <?= !empty($puedeMarcar) ? 'true' : 'false' ?>;
    let g_ordenCol = '<?= addslashes($ordenCol) ?>';
    let g_ordenDir = '<?= addslashes($ordenDir) ?>';
    let g_paginaActual = <?= (int) $page ?>;
</script>
