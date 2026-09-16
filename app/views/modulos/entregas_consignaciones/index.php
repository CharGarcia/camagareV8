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
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= asset_ver('/css/components/filtros_busqueda.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= asset_ver('/js/components/filtros_busqueda.js') ?>"></script>
            <div id="fbBuscadorENTC" style="width: 400px;"></div>
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
                    if (!window.FiltrosBusqueda) return;
                    window.entcFiltros = new FiltrosBusqueda({
                        containerId: 'fbBuscadorENTC',
                        hiddenInputId: 'b',
                        placeholder: 'Buscar consignaciones...',
                        fields: [
                            { key: 'estado',      label: 'Estado de entrega', icon: 'bi-hourglass-split', type: 'select', options: [
                                { v: 'pendiente', l: 'Pendientes' },
                                { v: 'entregada', l: 'Entregadas' },
                                { v: 'todas',     l: 'Todas' },
                            ]},
                            { key: 'emision',     label: 'Fecha emisión',     icon: 'bi-calendar',        type: 'date_range' },
                            { key: 'programada',  label: 'Entrega programada', icon: 'bi-calendar-check', type: 'date_range' },
                            { key: 'entrega',     label: 'Fecha entrega real', icon: 'bi-calendar2-check', type: 'date_range' },
                            { key: 'producto',    label: 'Producto',          icon: 'bi-box-seam',        type: 'text' },
                            { key: 'secuencial',  label: 'N° Consignación',   icon: 'bi-hash',            type: 'text' },
                            { key: 'cliente',     label: 'Cliente',           icon: 'bi-person',          type: 'text' },
                            { key: 'direccion',   label: 'Dirección',         icon: 'bi-geo',             type: 'text' },
                            { key: 'responsable', label: 'Responsable',       icon: 'bi-truck',           type: 'text' },
                            { key: 'canal',       label: 'Canal',             icon: 'bi-broadcast',       type: 'select', options: [
                                { v: 'movil', l: 'App móvil' },
                                { v: 'web',   l: 'Web (manual)' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_hoy', label: 'Hoy',      mk: () => FiltrosBusqueda.helpers.hoyMismo('emision') },
                            { id: 'qf_mes', label: 'Este mes', mk: () => FiltrosBusqueda.helpers.esteMes('emision') },
                        ],
                        onApply: () => { entcSincronizarSelectsDesdeFiltros(); g_paginaActual = 1; entcCargarGrid(); },
                    });
                    window.entcFiltros.init();

                    document.getElementById('entc_estado').addEventListener('change', entcAplicarEstado);
                    document.getElementById('entc_anio').addEventListener('change', entcAplicarAnioMes);
                    document.getElementById('entc_mes').addEventListener('change', entcAplicarAnioMes);
                });

                // El select de estado es un atajo sobre el filtro "estado" del buscador. "Pendientes"
                // es el valor por defecto del backend, así que en ese caso se quita el chip en vez de
                // agregar uno redundante.
                function entcAplicarEstado() {
                    const valor = document.getElementById('entc_estado').value;
                    const etiquetas = { pendiente: 'Pendientes', entregada: 'Entregadas', todas: 'Todas' };
                    if (!valor || valor === 'pendiente') {
                        window.entcFiltros.state.filters = window.entcFiltros.state.filters.filter(f => f.key !== 'estado');
                        window.entcFiltros.recomputeActiveQuick();
                        window.entcFiltros.renderChips();
                        window.entcFiltros.apply();
                        return;
                    }
                    window.entcFiltros.addFilter({ key: 'estado', op: '=', value: valor, display: etiquetas[valor] || valor });
                }

                // Año/Mes son atajos sobre el mismo filtro "emision" (valor parcial: "2026" o
                // "2026-08"), reutilizando FiltrosBusqueda::normalizarFecha() en el backend en
                // vez de duplicar lógica de fechas — ver EntregasConsignacionesRepository.
                function entcAplicarAnioMes() {
                    const anio = document.getElementById('entc_anio').value;
                    const mes  = document.getElementById('entc_mes').value;
                    if (!anio) {
                        // Sin año no se puede componer "emision:" — quitar el filtro si existía.
                        window.entcFiltros.state.filters = window.entcFiltros.state.filters.filter(f => f.key !== 'emision');
                        window.entcFiltros.recomputeActiveQuick();
                        window.entcFiltros.renderChips();
                        window.entcFiltros.apply();
                        return;
                    }
                    const valor = mes ? `${anio}-${String(mes).padStart(2, '0')}` : anio;
                    window.entcFiltros.addFilter({ key: 'emision', op: '=', value: valor, display: valor });
                }
                // Si el usuario borra un chip a mano, reflejarlo en los selects.
                function entcSincronizarSelectsDesdeFiltros() {
                    const fEmision = window.entcFiltros.state.filters.find(x => x.key === 'emision');
                    if (!fEmision) {
                        document.getElementById('entc_anio').value = '';
                        document.getElementById('entc_mes').value = '';
                    }
                    const fEstado = window.entcFiltros.state.filters.find(x => x.key === 'estado');
                    const selEstado = document.getElementById('entc_estado');
                    const v = fEstado ? String(Array.isArray(fEstado.value) ? fEstado.value[0] : fEstado.value).toLowerCase() : 'pendiente';
                    selEstado.value = ['pendiente', 'entregada', 'todas'].includes(v) ? v : 'pendiente';
                }
            </script>

            <div class="btn-group btn-group-sm">
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

                <a id="entc_pdf_url" class="btn btn-outline-danger pdf-export-btn" href="<?= $urlBaseEnt ?>/exportPdf?b=<?= urlencode($buscar) ?><?= $qsOrden ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="entc_excel_url" class="btn btn-outline-success excel-export-btn" href="<?= $urlBaseEnt ?>/exportExcel?b=<?= urlencode($buscar) ?><?= $qsOrden ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
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
