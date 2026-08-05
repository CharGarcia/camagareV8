<?php
/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array $vistaConfig */
/** @var array $resumen */
/** @var int $anioDesde */
/** @var int $anioHasta */

$base       = BASE_URL;
$urlBaseEnt = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

// KPIs + filtros arriba de la tabla: el app-shell (borde a borde) asume título + una
// sola tabla, así que se desactiva para que la página tenga scroll normal (§9 CLAUDE.md).
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .entc-scroll {
        max-height: calc(100dvh - 330px);
        overflow-y: auto;
    }
    .entc-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
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
            <div class="entc-kpi-label text-muted">Entregas</div>
            <div class="entc-kpi-value text-dark" data-kpi="total_entregas"><?= (int) ($resumen['total_entregas'] ?? 0) ?></div>
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
            <div class="entc-kpi-label text-muted">Pendientes</div>
            <div class="entc-kpi-value text-warning" data-kpi="pendientes"><?= (int) ($resumen['pendientes'] ?? 0) ?></div>
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
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorENTC" style="width: 400px;"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <select id="entc_anio" class="form-select form-select-sm" style="width:100px" title="Año">
                <option value="">Año</option>
                <?php for ($a = $anioHasta; $a >= $anioDesde; $a--): ?>
                    <option value="<?= $a ?>"><?= $a ?></option>
                <?php endfor; ?>
            </select>
            <select id="entc_mes" class="form-select form-select-sm" style="width:110px" title="Mes">
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
                        placeholder: 'Buscar entregas...',
                        fields: [
                            { key: 'entrega',     label: 'Fecha entrega',   icon: 'bi-calendar',      type: 'date_range' },
                            { key: 'producto',    label: 'Producto',        icon: 'bi-box-seam',      type: 'text' },
                            { key: 'secuencial',  label: 'N° Consignación', icon: 'bi-hash',           type: 'text' },
                            { key: 'cliente',     label: 'Cliente',         icon: 'bi-person',         type: 'text' },
                            { key: 'responsable', label: 'Responsable',     icon: 'bi-truck',          type: 'text' },
                            { key: 'canal',       label: 'Canal',           icon: 'bi-broadcast',      type: 'select', options: [
                                { v: 'movil', l: 'App móvil' },
                                { v: 'web',   l: 'Web (manual)' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_hoy', label: 'Hoy',      mk: () => FiltrosBusqueda.helpers.hoyMismo('entrega') },
                            { id: 'qf_mes', label: 'Este mes', mk: () => FiltrosBusqueda.helpers.esteMes('entrega') },
                        ],
                        onApply: () => { entcSincronizarAnioMesDesdeFiltro(); g_paginaActual = 1; entcCargarGrid(); },
                    });
                    window.entcFiltros.init();

                    document.getElementById('entc_anio').addEventListener('change', entcAplicarAnioMes);
                    document.getElementById('entc_mes').addEventListener('change', entcAplicarAnioMes);
                });

                // Año/Mes son atajos sobre el mismo filtro "entrega" (valor parcial: "2026" o
                // "2026-08"), reutilizando FiltrosBusqueda::normalizarFecha() en el backend en
                // vez de duplicar lógica de fechas — ver EntregasConsignacionesRepository.
                function entcAplicarAnioMes() {
                    const anio = document.getElementById('entc_anio').value;
                    const mes  = document.getElementById('entc_mes').value;
                    if (!anio) {
                        // Sin año no se puede componer "entrega:" — quitar el filtro si existía.
                        window.entcFiltros.state.filters = window.entcFiltros.state.filters.filter(f => f.key !== 'entrega');
                        window.entcFiltros.recomputeActiveQuick();
                        window.entcFiltros.renderChips();
                        window.entcFiltros.apply();
                        return;
                    }
                    const valor = mes ? `${anio}-${String(mes).padStart(2, '0')}` : anio;
                    window.entcFiltros.addFilter({ key: 'entrega', op: '=', value: valor, display: valor });
                }
                // Si el usuario borra el chip "Fecha entrega" a mano, limpiar los selects también.
                function entcSincronizarAnioMesDesdeFiltro() {
                    const f = window.entcFiltros.state.filters.find(x => x.key === 'entrega');
                    if (!f) {
                        document.getElementById('entc_anio').value = '';
                        document.getElementById('entc_mes').value = '';
                    }
                }
            </script>

            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'capturado_en'   => 'Fecha/hora entrega',
                    'secuencial'     => 'Consignación',
                    'cliente'        => 'Cliente',
                    'responsable'    => 'Responsable',
                    'canal'          => 'Canal',
                    'firma'          => 'Firma',
                    'gps'            => 'GPS',
                    'registrado_por' => 'Registrado por',
                    'observaciones'  => 'Observaciones',
                ], $vistaConfig ?? [], basename($rutaModulo)); ?>

                <a id="entc_pdf_url" class="btn btn-outline-danger pdf-export-btn" href="<?= $urlBaseEnt ?>/exportPdf?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="entc_excel_url" class="btn btn-outline-success excel-export-btn" href="<?= $urlBaseEnt ?>/exportExcel?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
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
                        <th class="ps-3 sortable-header" role="button" data-col="capturado_en" data-sort="capturado_en">Fecha/hora entrega <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="secuencial" data-sort="secuencial">Consignación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="cliente" data-sort="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="responsable" data-sort="responsable">Responsable <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-col="canal" data-sort="canal">Canal <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="firma">Firma</th>
                        <th class="text-center" data-col="gps">GPS</th>
                        <th data-col="registrado_por">Registrado por</th>
                        <th data-col="observaciones">Observaciones</th>
                    </tr>
                </thead>
                <tbody id="entc_grid_body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-geo-alt fs-3 d-block mb-2"></i>
                                No se encontraron entregas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                            $numero   = htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? ''));
                            $badgeCanal = ($r['canal'] ?? 'movil') === 'web'
                                ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-display me-1"></i>Web</span>'
                                : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-phone me-1"></i>App móvil</span>';
                        ?>
                            <tr class="entc-row" role="button" tabindex="0" data-row="<?= $dataJson ?>" onclick="entcAbrirDetalle(this)">
                                <td class="ps-3" data-col="capturado_en"><?= htmlspecialchars($r['capturado_en_fmt'] ?? '—') ?></td>
                                <td data-col="secuencial" class="fw-bold text-primary"><?= $numero ?></td>
                                <td data-col="cliente" class="text-truncate" style="max-width:220px" title="<?= htmlspecialchars($r['cliente_nombre'] ?? '') ?>"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                <td data-col="responsable" class="text-truncate" style="max-width:160px"><?= htmlspecialchars($r['responsable_traslado_nombre'] ?? '—') ?></td>
                                <td class="text-center" data-col="canal"><?= $badgeCanal ?></td>
                                <td class="text-center" data-col="firma"><?= !empty($r['tiene_firma']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>' ?></td>
                                <td class="text-center" data-col="gps"><?= !empty($r['tiene_gps']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>' ?></td>
                                <td data-col="registrado_por" class="text-truncate" style="max-width:150px"><?= htmlspecialchars($r['registrado_por'] ?? '—') ?></td>
                                <td data-col="observaciones" class="text-truncate" style="max-width:220px" title="<?= htmlspecialchars($r['observaciones'] ?? '') ?>"><?= htmlspecialchars($r['observaciones'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_detalle.php'; ?>

<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/entregas_consignaciones.js?v=<?= time() ?>"></script>
<script>
    window.RUTA_MODULO_ENTC = '<?= $urlBaseEnt ?>';
    let g_ordenCol = '<?= addslashes($ordenCol) ?>';
    let g_ordenDir = '<?= addslashes($ordenDir) ?>';
    let g_paginaActual = <?= (int) $page ?>;
</script>
