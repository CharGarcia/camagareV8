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

use App\Helpers\PreferenciasHelper;

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$vistaConfig = $vistaConfig ?? [];
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

$tipoColor = ['entrada' => 'success', 'salida' => 'danger', 'inicio_break' => 'warning', 'fin_break' => 'info'];
$estadoColor = ['valida' => 'success', 'sospechosa' => 'warning', 'anulada' => 'secondary'];
?>

<style>
    .marcaciones-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .marcaciones-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
</style>

<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['actualizar'])): ?>
    <button type="button" class="btn btn-primary btn-sm" onclick="abrirMarcacionManual()">
        <i class="bi bi-plus-lg me-1"></i>Registrar marcación
    </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorMARC" style="width: 460px;"></div>
            <input type="hidden" id="buscarMarc" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorMARC',
                        hiddenInputId: 'buscarMarc',
                        fields: [
                            { key: 'empleado', label: 'Empleado', icon: 'bi-person', type: 'text' },
                            { key: 'punto', label: 'Punto', icon: 'bi-geo-alt', type: 'text' },
                            { key: 'tipo', label: 'Tipo', icon: 'bi-box-arrow-in-right', type: 'select', options: [
                                { v: 'entrada', l: 'Entrada' }, { v: 'salida', l: 'Salida' },
                                { v: 'inicio_break', l: 'Inicio break' }, { v: 'fin_break', l: 'Fin break' }
                            ]},
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'valida', l: 'Válida' }, { v: 'sospechosa', l: 'Sospechosa' }, { v: 'anulada', l: 'Anulada' }
                            ]},
                            { key: 'fecha', label: 'Fecha', icon: 'bi-calendar-date', type: 'date_range' },
                        ],
                        quickFilters: [
                            { id: 'qf_susp', label: 'Sospechosas', mk: () => ({ key: 'estado', op: '=', value: 'sospechosa', display: 'Sospechosa' }) },
                        ],
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'empleado'  => 'Empleado',
                    'punto'     => 'Punto',
                    'fecha'     => 'Fecha/Hora',
                    'tipo'      => 'Tipo',
                    'distancia' => 'Distancia',
                    'estado'    => 'Estado',
                ];
                ?>
                <?= PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo) ?>
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
        <div class="marcaciones-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="empleado" role="button" data-col="empleado">Empleado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="punto" role="button" data-col="punto">Punto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="fecha_hora" role="button" data-col="fecha">Fecha/Hora <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="tipo" role="button" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="distancia">Distancia</th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyMarcaciones">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No hay marcaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $fecha = $row['fecha_hora'] ? date('d-m-Y H:i:s', strtotime((string) $row['fecha_hora'])) : '—';
                            $tc = $tipoColor[$row['tipo']] ?? 'secondary';
                            $ec = $estadoColor[$row['estado'] ?? 'valida'] ?? 'secondary';
                            $dist = $row['distancia_m'] !== null ? (int) $row['distancia_m'] . ' m' : '—';
                        ?>
                            <tr>
                                <td class="ps-3 fw-medium" data-col="empleado"><?= htmlspecialchars((string) ($row['empleado_nombre'] ?? '')) ?></td>
                                <td data-col="punto" class="small text-muted"><?= htmlspecialchars((string) ($row['punto_nombre'] ?? '—')) ?></td>
                                <td data-col="fecha"><?= htmlspecialchars($fecha) ?></td>
                                <td class="text-center" data-col="tipo">
                                    <span class="badge bg-<?= $tc ?> bg-opacity-10 text-<?= $tc ?> border border-<?= $tc ?> border-opacity-25"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $row['tipo']))) ?></span>
                                </td>
                                <td class="text-center" data-col="distancia"><?= htmlspecialchars($dist) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $ec ?> bg-opacity-10 text-<?= $ec ?> border border-<?= $ec ?> border-opacity-25"><?= htmlspecialchars(ucfirst((string) ($row['estado'] ?? 'valida'))) ?></span>
                                </td>
                                <td class="text-center pe-3">
                                    <?php if ($perm['eliminar']): ?>
                                        <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarMarcacion(<?= (int) $row['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($perm['actualizar'])): ?>
<!-- Modal: registro manual de marcación (p. ej. la salida que faltó) -->
<div class="modal fade" id="modalMarcManual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Registrar marcación</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Registra una marcación que no se hizo (por ejemplo, la salida que faltó). Al guardar, se recalcula la jornada del día.</p>
                <div class="mb-2 position-relative">
                    <label class="form-label small fw-bold mb-1">Empleado</label>
                    <input type="text" id="mm_empleado_texto" class="form-control form-control-sm" autocomplete="off"
                           placeholder="Escriba nombre o cédula...">
                    <input type="hidden" id="mm_empleado">
                    <div id="mm_empleado_dropdown" class="list-group position-absolute w-100 shadow-sm"
                         style="display:none;z-index:1085;max-height:220px;overflow-y:auto;"></div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Tipo</label>
                    <select id="mm_tipo" class="form-select form-select-sm">
                        <option value="salida">Salida</option>
                        <option value="entrada">Entrada</option>
                        <option value="inicio_break">Inicio break</option>
                        <option value="fin_break">Fin break</option>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Fecha</label>
                        <input type="date" id="mm_fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Hora</label>
                        <input type="time" id="mm_hora" class="form-control form-control-sm" step="1">
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label small fw-bold mb-1">Observación <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" id="mm_obs" class="form-control form-control-sm" maxlength="255" placeholder="Motivo de la corrección...">
                </div>
                <div id="mm_msg" class="mt-2"></div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-3" id="mmBtnGuardar" onclick="guardarMarcacionManual()"><i class="bi bi-check2-circle me-1"></i>Registrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>window.MARCACIONES_URL = '<?= $urlBase ?>';</script>
<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBase ?>';
        const inputB = document.getElementById('buscarMarc');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyMarcaciones').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        if (th.dataset.sort === currentSort) {
                            icon.className = (currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-down-alt text-primary ms-1' : 'bi bi-sort-up text-primary ms-1';
                        } else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    });
                }
            } catch (e) {}
        }

        window.eliminarMarcacion = function (id) {
            const doDelete = () => {
                const fd = new FormData(); fd.append('id_eliminar', id);
                fetch(`${urlBase}/delete`, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(j => {
                        if (j.ok) { cargarListado(window.currentPage || 1); if (window.Swal) Swal.fire({ icon: 'success', title: j.msg, timer: 1200, showConfirmButton: false }); }
                        else if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: j.error });
                        else alert(j.error);
                    });
            };
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: '¿Eliminar marcación?', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' })
                    .then(res => { if (res.isConfirmed) doDelete(); });
            } else if (confirm('¿Eliminar esta marcación?')) doDelete();
        };

        if (window.CMG_initSort) {
            window.CMG_initSort('marcaciones', (col, dir) => {
                currentSort = col; currentDir = dir; cargarListado(1);
            }, { col: currentSort, dir: currentDir });
        }

        // ── Registro manual de marcación ────────────────────────────────────
        let mModal = null, empleadosCargados = false, empleadosLista = [], mmTaInit = false;
        const modalManual = () => (mModal = mModal || (typeof bootstrap !== 'undefined' ? new bootstrap.Modal(document.getElementById('modalMarcManual')) : null));

        const escHtml = (s) => (s == null ? '' : String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])));

        window.abrirMarcacionManual = async function () {
            const msg = document.getElementById('mm_msg'); if (msg) msg.innerHTML = '';
            if (!empleadosCargados) {
                try {
                    const resp = await fetch(`${urlBase}/datosManualAjax`);
                    const j = await resp.json();
                    if (!j.ok) { if (window.Swal) Swal.fire('Atención', j.error || 'No se pudieron cargar los empleados.', 'warning'); return; }
                    empleadosLista = j.empleados || [];
                    if (!empleadosLista.length) { if (window.Swal) Swal.fire('Atención', 'No hay empleados activos.', 'warning'); return; }
                    empleadosCargados = true;
                } catch (e) { if (window.Swal) Swal.fire('Error', 'Error de red al cargar empleados.', 'error'); return; }
            }
            // Limpiar selección previa al reabrir.
            document.getElementById('mm_empleado').value = '';
            document.getElementById('mm_empleado_texto').value = '';
            document.getElementById('mm_empleado_dropdown').style.display = 'none';
            mmInitTypeahead();
            modalManual()?.show();
        };

        // Buscador de empleados sobre la lista ya cargada (nombre o cédula), estilo
        // "chip": con selección activa, Backspace/Delete limpia todo de una vez.
        function mmInitTypeahead() {
            if (mmTaInit) return;
            mmTaInit = true;
            const input = document.getElementById('mm_empleado_texto');
            const hidden = document.getElementById('mm_empleado');
            const dd = document.getElementById('mm_empleado_dropdown');

            const cerrar = () => { dd.style.display = 'none'; dd.innerHTML = ''; };

            const filtrar = (q) => {
                q = q.trim().toLowerCase();
                const base = q === ''
                    ? empleadosLista
                    : empleadosLista.filter(e =>
                        (e.nombres_apellidos || '').toLowerCase().includes(q) ||
                        (e.identificacion || '').toLowerCase().includes(q));
                return base.slice(0, 50); // no volcar 100+ de golpe
            };

            const pintar = (items) => {
                if (!items.length) { dd.innerHTML = '<span class="list-group-item small text-muted py-1 px-2">Sin coincidencias</span>'; dd.style.display = 'block'; return; }
                dd.innerHTML = items.map(e => {
                    const label = `${e.nombres_apellidos || ''} — ${e.identificacion || ''}`;
                    return `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${e.id}" data-label="${escHtml(label)}">${escHtml(label)}</a>`;
                }).join('');
                dd.style.display = 'block';
            };

            input.addEventListener('keydown', (e) => {
                if ((e.key === 'Backspace' || e.key === 'Delete') && hidden.value !== '') {
                    e.preventDefault(); hidden.value = ''; input.value = ''; cerrar();
                }
            });
            input.addEventListener('input', () => { hidden.value = ''; pintar(filtrar(input.value)); });
            input.addEventListener('focus', () => { if (hidden.value === '') pintar(filtrar(input.value)); });
            dd.addEventListener('click', (e) => {
                const a = e.target.closest('a[data-id]');
                if (!a) return;
                e.preventDefault();
                hidden.value = a.dataset.id;
                input.value = a.dataset.label;
                cerrar();
            });
            document.addEventListener('click', (e) => {
                if (e.target !== input && !dd.contains(e.target)) cerrar();
            });
        }

        window.guardarMarcacionManual = async function () {
            const btn = document.getElementById('mmBtnGuardar');
            const idEmpleado = document.getElementById('mm_empleado').value;
            const tipo = document.getElementById('mm_tipo').value;
            const fecha = document.getElementById('mm_fecha').value;
            const hora = document.getElementById('mm_hora').value;
            const obs = document.getElementById('mm_obs').value;

            if (!idEmpleado) {
                if (window.Swal) Swal.fire('Requerido', 'Busque y seleccione un empleado de la lista.', 'warning');
                return;
            }
            if (!fecha || !hora) {
                if (window.Swal) Swal.fire('Requerido', 'Complete la fecha y la hora.', 'warning');
                return;
            }
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
            try {
                const fd = new FormData();
                fd.append('id_empleado', idEmpleado); fd.append('tipo', tipo);
                fd.append('fecha', fecha); fd.append('hora', hora); fd.append('observacion', obs);
                const resp = await fetch(`${urlBase}/registrarManualAjax`, { method: 'POST', body: fd });
                const j = await resp.json();
                if (j.ok) {
                    modalManual()?.hide();
                    document.getElementById('mm_obs').value = '';
                    cargarListado(window.currentPage || 1);
                    if (window.Swal) Swal.fire({ icon: 'success', title: j.msg, timer: 1600, showConfirmButton: false });
                } else if (window.Swal) Swal.fire('Atención', j.error || 'No se pudo registrar.', 'error');
                else alert(j.error);
            } catch (e) {
                if (window.Swal) Swal.fire('Error de red', 'No se pudo conectar con el servidor.', 'error');
            }
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Registrar';
        };
    })();
</script>
