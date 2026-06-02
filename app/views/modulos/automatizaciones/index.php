<?php
/** @var string $titulo */
/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $rows */
/** @var int    $total */
/** @var int    $page */
/** @var int    $totalPages */
/** @var int    $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array  $modulos */
/** @var array  $vistaConfig */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 25;
$ordenCol   = $ordenCol   ?? 'nombre';
$ordenDir   = $ordenDir   ?? 'asc';
$buscar     = $buscar     ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .auto-scroll { max-height: calc(100vh - 240px); overflow-y: auto; }
    .auto-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .auto-row { cursor: pointer; }
    .auto-row:hover { background-color: rgba(0,0,0,.04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-robot text-primary me-1"></i> <?= htmlspecialchars($titulo ?? 'Automatizaciones') ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="AUTO_abrirModalCrear()">
            <i class="fas fa-plus"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

        <!-- Buscador y botones -->
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorAUTO" style="width:480px;"></div>
            <input type="hidden" id="buscarAuto" value="<?= htmlspecialchars($buscar) ?>">
            <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (!window.FiltrosBusqueda) return;
                new FiltrosBusqueda({
                    containerId:   'fbBuscadorAUTO',
                    hiddenInputId: 'buscarAuto',
                    fields: [
                        { key: 'nombre',    label: 'Nombre',    icon: 'bi-tag',           type: 'text' },
                        { key: 'modulo',    label: 'Módulo',    icon: 'bi-grid',          type: 'text' },
                        { key: 'accion',    label: 'Acción',    icon: 'bi-lightning',     type: 'text' },
                        { key: 'estado',    label: 'Estado',    icon: 'bi-flag',          type: 'select', options: [
                            { v: 'activo',     l: 'Activo' },
                            { v: 'inactivo',   l: 'Inactivo' },
                            { v: 'en_proceso', l: 'En proceso' },
                        ]},
                        { key: 'resultado', label: 'Último resultado', icon: 'bi-check-circle', type: 'select', options: [
                            { v: 'exitoso',  l: 'Exitoso' },
                            { v: 'error',    l: 'Error' },
                        ]},
                    ],
                    quickFilters: [
                        { id: 'qf_activo',   label: 'Activas',    mk: () => ({ key: 'estado', op: '=', value: 'activo',   display: 'Activo' }) },
                        { id: 'qf_inactivo', label: 'Inactivas',  mk: () => ({ key: 'estado', op: '=', value: 'inactivo', display: 'Inactivo' }) },
                        { id: 'qf_error',    label: 'Con errores',mk: () => ({ key: 'resultado', op: '=', value: 'error', display: 'Error' }) },
                    ],
                    onApply: () => window.fetchSearch && window.fetchSearch(1),
                }).init();
            });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre'            => 'Nombre',
                    'modulo'            => 'Módulo',
                    'accion'            => 'Acción',
                    'frecuencia_tipo'   => 'Frecuencia',
                    'proxima_ejecucion' => 'Próx. ejecución',
                    'ultima_ejecucion'  => 'Últ. ejecución',
                    'ultimo_resultado'  => 'Resultado',
                    'estado'            => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf"
                   href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger" title="Exportar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel"
                   href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success" title="Exportar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>
                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="auto-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre"            data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header"       role="button" data-sort="modulo"            data-col="modulo">Módulo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header"       role="button" data-sort="accion"            data-col="accion">Acción <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header"       role="button" data-sort="frecuencia_tipo"   data-col="frecuencia_tipo">Frecuencia <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header"       role="button" data-sort="proxima_ejecucion" data-col="proxima_ejecucion">Próx. ejecución <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header"       role="button" data-sort="ultima_ejecucion"  data-col="ultima_ejecucion">Últ. ejecución <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="ultimo_resultado" data-col="ultimo_resultado">Resultado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center pe-3" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyAutomatizaciones">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-robot fa-2x d-block mb-2"></i>No se encontraron automatizaciones.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $ce   = ['activo'=>'success','inactivo'=>'secondary','en_proceso'=>'warning'][$r['estado'] ?? ''] ?? 'secondary';
                            $cr   = ['exitoso'=>'success','error'=>'danger','pendiente'=>'secondary'][$r['ultimo_resultado'] ?? ''] ?? 'secondary';
                            $data = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="auto-row" role="button" tabindex="0" data-row='<?= $data ?>' onclick="AUTO_abrirModalEditar(this)">
                            <td class="ps-3 fw-medium text-truncate" style="max-width:260px;" data-col="nombre">
                                <?= htmlspecialchars($r['nombre'] ?? '') ?>
                                <?php if (!empty($r['nombre_establecimiento'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($r['nombre_establecimiento']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-col="modulo"><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['modulo'] ?? '') ?></span></td>
                            <td data-col="accion" class="text-truncate" style="max-width:180px;"><?= htmlspecialchars($r['accion'] ?? '') ?></td>
                            <td data-col="frecuencia_tipo"><?= htmlspecialchars($r['frecuencia_tipo'] ?? '') ?></td>
                            <td data-col="proxima_ejecucion" style="font-size:.82rem;">
                                <?php if (!empty($r['proxima_ejecucion_fmt'])): ?>
                                    <i class="fas fa-clock text-info me-1" style="font-size:.75rem;"></i><?= htmlspecialchars($r['proxima_ejecucion_fmt']) ?>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td data-col="ultima_ejecucion" style="font-size:.82rem;">
                                <?= !empty($r['ultima_ejecucion_fmt']) ? htmlspecialchars($r['ultima_ejecucion_fmt']) : '<span class="text-muted">Sin ejecuciones</span>' ?>
                            </td>
                            <td data-col="ultimo_resultado" class="text-center">
                                <?php if (!empty($r['ultimo_resultado'])): ?>
                                    <span class="badge bg-<?= $cr ?> bg-opacity-10 text-<?= $cr ?> border border-<?= $cr ?> border-opacity-25"><?= htmlspecialchars($r['ultimo_resultado']) ?></span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td data-col="estado" class="text-center pe-3">
                                <span class="badge bg-<?= $ce ?> bg-opacity-10 text-<?= $ce ?> border border-<?= $ce ?> border-opacity-25"><?= htmlspecialchars($r['estado'] ?? '') ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.BASE_URL  = '<?= $base ?>';
window.AUTO_MODS = <?= json_encode($modulos ?? []) ?>;
window.AUTO_ENVIO_AUTOMATICO_CORREO = <?= !empty($envioAutomaticoCorreo) ? 'true' : 'false' ?>;
</script>
<?php include __DIR__ . '/modal_automatizacion.php'; ?>

<script>
(function () {
    'use strict';
    const urlBase   = '<?= $urlBase ?>';
    const inputBusc = document.getElementById('buscarAuto');
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir  = '<?= strtoupper($ordenDir) ?>';
    window.currentPage = <?= $page ?>;

    let timerId;
    const debounce = (fn, ms = 350) => (...a) => { clearTimeout(timerId); timerId = setTimeout(() => fn(...a), ms); };

    window.cambiarPaginaAjax = n => window.fetchSearch(n);

    window.fetchSearch = async (page = 1) => {
        const term = inputBusc ? inputBusc.value.trim() : '';
        const uri  = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        try {
            const res  = await fetch(uri, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data.ok) return;
            window.currentPage = page;
            document.getElementById('tbodyAutomatizaciones').innerHTML = data.rows;
            document.getElementById('paginationContainer').innerHTML   = data.pagination;
            document.getElementById('paginationInfo').textContent      = data.info;
            document.getElementById('btnExportPdf').href               = data.pdf_url;
            document.getElementById('btnExportExcel').href             = data.excel_url;

            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon  = th.querySelector('i');
                const field = th.dataset.sort;
                if (field === window.currentSort) {
                    icon.className = window.currentDir.toLowerCase() === 'asc'
                        ? 'bi bi-sort-alpha-down text-primary ms-1'
                        : 'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        } catch (e) { console.error('Error búsqueda automatizaciones:', e); }
    };

    document.querySelectorAll('.sortable-header').forEach(h => {
        h.addEventListener('click', () => {
            const f = h.dataset.sort;
            if (window.currentSort === f) window.currentDir = window.currentDir.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
            else { window.currentSort = f; window.currentDir = 'ASC'; }
            if (typeof window.guardarOrdenacionVista === 'function')
                window.guardarOrdenacionVista('automatizaciones', window.currentSort, window.currentDir);
            fetchSearch(1);
        });
    });

    if (inputBusc) inputBusc.addEventListener('input', debounce(() => fetchSearch(1), 400));
})();
</script>
