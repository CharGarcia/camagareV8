<?php

/** @var string $titulo @var array $perm @var string $rutaModulo */
/** @var array $rows @var int $total @var int $page @var int $totalPages @var int $perPage @var string $buscar @var string $ordenCol @var string $ordenDir */

use App\Helpers\PreferenciasHelper;

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$vistaConfig = PreferenciasHelper::getPreferenciasVista($rutaModulo . '/jornadas');
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$estadoColor = ['completa' => 'success', 'incompleta' => 'warning', 'falta' => 'danger', 'permiso' => 'info'];
?>

<style>
    .casis-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .casis-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
</style>

<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= $urlBase ?>/horarios" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-clock-history me-1"></i> Horarios</a>
        <a href="<?= $urlBase ?>/marcaciones" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-list-check me-1"></i> Marcaciones</a>
        <a href="<?= $urlBase ?>/configuracion" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-gear-fill me-1"></i> Configuración</a>
        <?php if ($perm['actualizar']): ?>
        <button class="btn btn-outline-primary btn-sm px-3 shadow-sm" onclick="abrirRecalcular()"><i class="bi bi-arrow-repeat me-1"></i> Recalcular</button>
        <?php endif; ?>
        <?php if ($perm['crear']): ?>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirGenerarNovedades()"><i class="bi bi-journal-plus me-1"></i> Generar Novedades</button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorCASISJ" style="width: 460px;"></div>
            <input type="hidden" id="buscarCasisJ" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorCASISJ',
                        hiddenInputId: 'buscarCasisJ',
                        fields: [
                            { key: 'empleado', label: 'Empleado', icon: 'bi-person', type: 'text' },
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'completa', l: 'Completa' }, { v: 'incompleta', l: 'Incompleta' }, { v: 'falta', l: 'Falta' }, { v: 'permiso', l: 'Permiso' }
                            ]},
                            { key: 'fecha', label: 'Fecha', icon: 'bi-calendar-date', type: 'date_range' },
                            { key: 'atraso', label: 'Atraso (min)', icon: 'bi-clock', type: 'number_range' },
                            { key: 'extra', label: 'Extra (min)', icon: 'bi-plus-slash-minus', type: 'number_range' },
                        ],
                        quickFilters: [
                            { id: 'qf_falta', label: 'Faltas', mk: () => ({ key: 'estado', op: '=', value: 'falta', display: 'Falta' }) },
                            { id: 'qf_atraso', label: 'Con atraso', mk: () => ({ key: 'atraso', op: '>', value: '0', display: '> 0' }) },
                        ],
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = ['empleado'=>'Empleado','fecha'=>'Fecha','entrada'=>'Entrada','salida'=>'Salida','horas'=>'Horas','atraso'=>'Atraso','extra'=>'Extra','estado'=>'Estado'];
                ?>
                <?= PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo . '/jornadas') ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="wrapper-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="casis-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="empleado" role="button" data-col="empleado">Empleado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="fecha" role="button" data-col="fecha">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="entrada">Entrada</th>
                        <th class="text-center" data-col="salida">Salida</th>
                        <th class="text-center sortable-header" data-sort="horas_trabajadas" role="button" data-col="horas">Horas <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="atraso_min" role="button" data-col="atraso">Atraso <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="extra_min" role="button" data-col="extra">Extra <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyJornadas">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No hay jornadas calculadas. Usa «Recalcular» para procesar un rango de fechas.</td></tr>
                    <?php else: foreach ($rows as $row):
                        $ent = $row['primera_entrada'] ? date('H:i', strtotime((string)$row['primera_entrada'])) : '—';
                        $sal = $row['ultima_salida'] ? date('H:i', strtotime((string)$row['ultima_salida'])) : '—';
                        $ec = $estadoColor[$row['estado']] ?? 'secondary';
                        $atr = (int)$row['atraso_min']; $ext = (int)$row['extra_min'];
                    ?>
                        <tr>
                            <td class="ps-3 fw-medium" data-col="empleado"><?= htmlspecialchars((string)($row['empleado_nombre'] ?? '')) ?></td>
                            <td data-col="fecha"><?= $row['fecha'] ? date('d-m-Y', strtotime((string)$row['fecha'])) : '—' ?></td>
                            <td class="text-center" data-col="entrada"><?= htmlspecialchars($ent) ?></td>
                            <td class="text-center" data-col="salida"><?= htmlspecialchars($sal) ?></td>
                            <td class="text-center fw-bold" data-col="horas"><?= number_format((float)$row['horas_trabajadas'], 2) ?></td>
                            <td class="text-center" data-col="atraso"><?= $atr > 0 ? '<span class="text-danger fw-medium">'.$atr.' min</span>' : '—' ?></td>
                            <td class="text-center" data-col="extra"><?= $ext > 0 ? '<span class="text-success fw-medium">'.$ext.' min</span>' : '—' ?></td>
                            <td class="text-center" data-col="estado"><span class="badge bg-<?= $ec ?> bg-opacity-10 text-<?= $ec ?> border border-<?= $ec ?> border-opacity-25"><?= htmlspecialchars(ucfirst((string)$row['estado'])) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Recalcular -->
<div class="modal fade" id="modalRecalc" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
    <div class="modal-header bg-light py-3"><h6 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Recalcular jornadas</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">Procesa las marcaciones del rango y calcula horas, atrasos, extras y faltas.</p>
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Desde</label><input type="date" id="rec_desde" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Hasta</label><input type="date" id="rec_hasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        </div>
    </div>
    <div class="modal-footer bg-light p-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
        <button class="btn btn-primary btn-sm px-4" id="btnRecalc" onclick="ejecutarRecalcular()"><i class="bi bi-arrow-repeat me-1"></i>Recalcular</button>
    </div>
</div></div></div>

<!-- Modal Generar Novedades -->
<div class="modal fade" id="modalGenNov" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
    <div class="modal-header bg-light py-3"><h6 class="modal-title fw-bold"><i class="bi bi-journal-plus me-2 text-primary"></i>Generar Novedades</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">
            Traduce las jornadas del período (faltas, horas extra y, según la
            <a href="<?= $urlBase ?>/configuracion" target="_blank">configuración</a>, atrasos) en Novedades
            para el rol de pagos. Se puede repetir sin duplicar.
        </p>
        <div class="row g-2 mb-2">
            <div class="col-7">
                <label class="form-label small fw-semibold">Mes</label>
                <select id="gn_mes" class="form-select form-select-sm">
                    <?php foreach ($meses as $n => $nom): ?>
                        <option value="<?= $n ?>" <?= $n == (int) date('n') ? 'selected' : '' ?>><?= htmlspecialchars($nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-5">
                <label class="form-label small fw-semibold">Año</label>
                <input type="number" id="gn_anio" class="form-control form-control-sm" value="<?= (int) date('Y') ?>" min="2000" max="2100">
            </div>
        </div>
        <div class="mb-1">
            <label class="form-label small fw-semibold">Afecta a</label>
            <select id="gn_aplica" class="form-select form-select-sm">
                <?php foreach ($aplicaEnOpts as $k => $v): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'rol' ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="modal-footer bg-light p-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
        <button class="btn btn-primary btn-sm px-4" id="btnGenNov" onclick="ejecutarGenerarNovedades()"><i class="bi bi-journal-plus me-1"></i>Generar</button>
    </div>
</div></div></div>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBase ?>';
        const inputB = document.getElementById('buscarCasisJ');
        let currentSort = '<?= $ordenCol ?>', currentDir = '<?= $ordenDir ?>', mRec = null;

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/jornadasSearchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(uri); const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyJornadas').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i'); if (!icon) return;
                        if (th.dataset.sort === currentSort) icon.className = (currentDir.toLowerCase()==='asc')?'bi bi-sort-down-alt text-primary ms-1':'bi bi-sort-up text-primary ms-1';
                        else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    });
                }
            } catch (e) {}
        }

        window.abrirRecalcular = function () { mRec = mRec || new bootstrap.Modal(document.getElementById('modalRecalc')); mRec.show(); };

        let mGenNov = null;
        window.abrirGenerarNovedades = function () { mGenNov = mGenNov || new bootstrap.Modal(document.getElementById('modalGenNov')); mGenNov.show(); };
        window.ejecutarGenerarNovedades = function () {
            const btn = document.getElementById('btnGenNov');
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando...';
            const fd = new FormData();
            fd.append('periodo_mes', document.getElementById('gn_mes').value);
            fd.append('periodo_anio', document.getElementById('gn_anio').value);
            fd.append('aplica_en', document.getElementById('gn_aplica').value);
            fetch(`${urlBase}/generarNovedadesAjax`, {method:'POST',body:fd})
                .then(r=>r.json()).then(j=> {
                    btn.disabled = false; btn.innerHTML = '<i class="bi bi-journal-plus me-1"></i>Generar';
                    if (j.ok) { if (mGenNov) mGenNov.hide(); if (window.Swal) Swal.fire({icon:'success',title:'Novedades generadas',text:j.msg}); else alert(j.msg); }
                    else if (window.Swal) Swal.fire({icon:'error',title:'Error',text:j.error}); else alert(j.error);
                }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-journal-plus me-1"></i>Generar'; });
        };
        window.ejecutarRecalcular = function () {
            const btn = document.getElementById('btnRecalc');
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';
            const fd = new FormData();
            fd.append('desde', document.getElementById('rec_desde').value);
            fd.append('hasta', document.getElementById('rec_hasta').value);
            fetch(`${urlBase}/recalcularJornadasAjax`, {method:'POST',body:fd})
                .then(r=>r.json()).then(j=> {
                    btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Recalcular';
                    if (j.ok) { mRec.hide(); if (window.Swal) Swal.fire({icon:'success',title:j.msg,timer:1500,showConfirmButton:false}); cargarListado(1); }
                    else if (window.Swal) Swal.fire({icon:'error',title:'Error',text:j.error}); else alert(j.error);
                }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat me-1"></i>Recalcular'; });
        };

        if (window.CMG_initSort) window.CMG_initSort('control_asistencia_jorn', (col, dir) => { currentSort=col; currentDir=dir; cargarListado(1); }, { col: currentSort, dir: currentDir });
    })();
</script>
