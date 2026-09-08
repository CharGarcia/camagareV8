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
/** @var array $empleados */
/** @var array $tipos */
/** @var array $motivos */
/** @var array $meses */

use App\models\CatalogoNovedades;

$base = BASE_URL;
$urlBaseNov = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .nov-scroll {
        max-height: calc(100dvh - 250px);
        overflow-y: auto;
    }
    .nov-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
    }
    .nov-cargas-scroll {
        max-height: 240px;
        overflow-y: auto;
    }
    .nov-cargas-scroll table {
        margin-bottom: 0;
        font-size: .78rem;
    }
    .novedad-row { cursor: pointer; }
    .novedad-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-plus me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorNOV" style="width: 460px;"></div>
            <input type="hidden" id="buscarNov" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorNOV',
                        hiddenInputId: 'buscarNov',
                        fields: [
                            { key: 'empleado', label: 'Empleado', icon: 'bi-person', type: 'text' },
                            { key: 'codigo', label: 'Tipo de novedad', icon: 'bi-clipboard', type: 'select', options: [
                                <?php foreach (CatalogoNovedades::tipos() as $t): ?>{ v: '<?= htmlspecialchars($t['codigo']) ?>', l: '<?= htmlspecialchars($t['nombre']) ?>' },<?php endforeach; ?>
                            ]},
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'activo', l: 'Activo' }, { v: 'anulado', l: 'Anulado' }
                            ]},
                            { key: 'mes', label: 'Mes', icon: 'bi-calendar-month', type: 'select', options: [
                                <?php foreach ($meses as $n => $nom): ?>{ v: '<?= $n ?>', l: '<?= htmlspecialchars($nom) ?>' },<?php endforeach; ?>
                            ]},
                            { key: 'anio', label: 'Año', icon: 'bi-calendar', type: 'text' },
                            { key: 'valor', label: 'Valor', icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'fecha', label: 'Fecha', icon: 'bi-calendar-date', type: 'date_range' },
                            { key: 'observacion', label: 'Observación', icon: 'bi-chat-left-text', type: 'text' },
                        ],
                        quickFilters: [
                            { id: 'qf_activo',  label: 'Activos',  mk: () => ({ key: 'estado', op: '=', value: 'activo',  display: 'Activo' }) },
                            { id: 'qf_anulado', label: 'Anulados', mk: () => ({ key: 'estado', op: '=', value: 'anulado', display: 'Anulado' }) },
                        ],
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'empleado'       => 'Empleado',
                    'identificacion' => 'Identificación',
                    'tipo'           => 'Tipo',
                    'fecha'          => 'Fecha',
                    'periodo'        => 'Período',
                    'valor'          => 'Valor',
                    'aplica_en'      => 'Afecta a',
                    'motivo'         => 'Motivo',
                    'estado'         => 'Estado',
                    'pago'           => 'Pago',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseNov ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBaseNov ?>/export-excel?b=<?= urlencode($buscar) ?>" class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
                <?php if ($perm['crear']): ?>
                    <button type="button" class="btn btn-outline-primary" title="Cargar novedades desde Excel" onclick="window.abrirImportNov()"><i class="bi bi-upload"></i> Importar</button>
                <?php endif; ?>
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
        <div class="nov-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="empleado" role="button" data-col="empleado">Empleado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="identificacion" role="button" data-col="identificacion">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tipo_nombre" role="button" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="fecha" role="button" data-col="fecha">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="periodo">Período</th>
                        <th class="text-end sortable-header" data-sort="valor" role="button" data-col="valor">Valor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="aplica_en">Afecta a</th>
                        <th data-col="motivo">Motivo</th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="pago">Pago</th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyNovedades">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="11" class="text-center py-5 text-muted">No se encontraron novedades registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $mes = $meses[(int) $row['periodo_mes']] ?? $row['periodo_mes'];
                            $estadoOk = ($row['estado'] ?? 'activo') === 'activo';
                            $pagada = !empty($row['pagada']);
                        ?>
                            <tr class="novedad-row" onclick="abrirModalEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="empleado"><?= htmlspecialchars((string) ($row['empleado_nombre'] ?? '')) ?></td>
                                <td data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars((string) ($row['empleado_identificacion'] ?? '')) ?></code></td>
                                <td data-col="tipo"><?= htmlspecialchars((string) ($row['tipo_nombre'] ?? '')) ?></td>
                                <td data-col="fecha"><?= $row['fecha'] ? date('d-m-Y', strtotime((string) $row['fecha'])) : '—' ?></td>
                                <td data-col="periodo"><?= htmlspecialchars($mes . ' ' . $row['periodo_anio']) ?></td>
                                <td class="text-end fw-bold" data-col="valor"><?= htmlspecialchars(CatalogoNovedades::formatValor((string) $row['tipo_codigo'], $row['valor'])) ?></td>
                                <td data-col="aplica_en"><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?= htmlspecialchars(CatalogoNovedades::nombreAplicaEn((string) ($row['aplica_en'] ?? 'rol'))) ?></span></td>
                                <td data-col="motivo" class="small text-muted"><?= htmlspecialchars((string) ($row['motivo_nombre'] ?? '—')) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $estadoOk ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $estadoOk ? 'success' : 'secondary' ?> border border-<?= $estadoOk ? 'success' : 'secondary' ?> border-opacity-25"><?= $estadoOk ? 'Activo' : 'Anulado' ?></span>
                                </td>
                                <td class="text-center" data-col="pago">
                                    <?php if ($pagada): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle me-1"></i>Pagada</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-3" onclick="event.stopPropagation()">
                                    <?php if (!empty($row['bloqueada'])): ?>
                                        <span class="text-muted" title="Bloqueada: el rol ya está pagado o ya fue desembolsada por egreso"><i class="bi bi-lock-fill"></i></span>
                                    <?php else: ?>
                                        <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(<?= $row['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
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

<script>
    window.BASE_URL = '<?= $base ?>';
    window.NOVEDAD_CATALOGO = <?= json_encode(CatalogoNovedades::paraJs(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php include 'modal_novedad.php'; ?>
<script src="<?= $base ?>/js/modulos/novedades.js?v=<?= time() ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseNov ?>';
        const inputB = document.getElementById('buscarNov');
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
                    document.getElementById('tbodyNovedades').innerHTML = data.rows;
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

        if (window.CMG_initSort) {
            window.CMG_initSort('novedades', (col, dir) => {
                currentSort = col;
                currentDir = dir;
                cargarListado(1);
            }, { col: currentSort, dir: currentDir });
        }

        window.addEventListener('novedadGuardada', () => cargarListado(window.currentPage || 1));
    })();
</script>

<!-- Modal Importar Novedades -->
<div class="modal fade" id="modalImportNov" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2 text-primary"></i>Importar Novedades desde Excel</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small py-2 mb-3">
                    Descarga la <a href="<?= $urlBaseNov ?>/plantilla-excel" class="fw-bold">plantilla</a>, complétala y súbela.
                    Columnas: <b>IDENTIFICACION, TIPO, VALOR, MES, ANIO, AFECTA_A, FECHA, OBSERVACION, MOTIVO</b>.
                    El <b>TIPO</b> y <b>AFECTA_A</b> pueden ir por código o nombre (ver hoja «Referencia» de la plantilla).
                </div>
                <input type="file" id="nov_import_file" class="form-control form-control-sm" accept=".xlsx,.xls">
                <div id="nov_import_result" class="mt-3"></div>

                <hr class="my-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-1 text-secondary"></i>Cargas realizadas</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary border-0 px-2" onclick="window.cargarCargasNov()" title="Actualizar">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div class="small text-muted mb-2">
                    Una carga se puede eliminar completa mientras <b>ninguna</b> de sus novedades se haya usado
                    (rol del período pagado, o anticipo/préstamo ya desembolsado por egreso).
                </div>
                <div id="nov_cargas_lista" class="nov-cargas-scroll border rounded-2"></div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <a href="<?= $urlBaseNov ?>/plantilla-excel" class="btn btn-outline-secondary btn-sm me-auto"><i class="bi bi-download me-1"></i>Plantilla</a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnImportarNov" onclick="window.importarNov()"><i class="bi bi-upload me-1"></i> Importar</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';
        const urlImport = '<?= $urlBaseNov ?>';
        let modalImp = null;
        const esc = (s) => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])));

        window.abrirImportNov = function () {
            document.getElementById('nov_import_file').value = '';
            document.getElementById('nov_import_result').innerHTML = '';
            if (!modalImp && typeof bootstrap !== 'undefined') modalImp = new bootstrap.Modal(document.getElementById('modalImportNov'));
            modalImp?.show();
            window.cargarCargasNov();
        };

        // ── Cargas realizadas: listado y reversión ───────────────────────────
        function filaCarga(c, puedeEliminar) {
            const usadas = c.usadas > 0
                ? `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">${c.usadas} usada(s)</span>`
                : '';
            let accion;
            if (!puedeEliminar) {
                accion = '<span class="text-muted" title="No tiene permiso para eliminar"><i class="bi bi-lock-fill"></i></span>';
            } else if (c.reversible) {
                accion = `<button type="button" class="btn btn-outline-danger btn-sm border-0 px-2"
                                  onclick="window.eliminarCargaNov(${c.id}, ${c.vigentes})" title="Eliminar toda la carga">
                              <i class="bi bi-trash"></i></button>`;
            } else {
                accion = `<span class="text-muted" title="${esc(c.motivo_bloqueo)}"><i class="bi bi-lock-fill"></i></span>`;
            }
            return `<tr>
                        <td class="ps-2">${esc(c.fecha)}</td>
                        <td class="text-truncate" style="max-width:180px;" title="${esc(c.archivo)}">${esc(c.archivo) || '—'}</td>
                        <td class="text-center">${c.vigentes} / ${c.creadas} ${usadas}</td>
                        <td class="text-truncate" style="max-width:130px;">${esc(c.usuario)}</td>
                        <td class="text-center pe-2">${accion}</td>
                    </tr>`;
        }

        window.cargarCargasNov = async function () {
            const cont = document.getElementById('nov_cargas_lista');
            if (!cont) return;
            cont.innerHTML = '<div class="text-center text-muted small py-3"><span class="spinner-border spinner-border-sm me-1"></span>Cargando…</div>';
            try {
                const resp = await fetch(`${urlImport}/cargas-ajax`);
                const json = await resp.json();
                if (!json.ok) {
                    cont.innerHTML = `<div class="text-center text-muted small py-3">${esc(json.error)}</div>`;
                    return;
                }
                if (!json.data.length) {
                    cont.innerHTML = '<div class="text-center text-muted small py-3">Todavía no hay cargas registradas.</div>';
                    return;
                }
                cont.innerHTML = `<table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-2">Fecha</th><th>Archivo</th>
                                <th class="text-center">Vigentes / Creadas</th><th>Usuario</th>
                                <th class="text-center pe-2" style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>${json.data.map(c => filaCarga(c, json.puede_eliminar)).join('')}</tbody>
                    </table>`;
            } catch (e) {
                cont.innerHTML = '<div class="text-center text-muted small py-3">No se pudo cargar el historial.</div>';
            }
        };

        window.eliminarCargaNov = async function (id, vigentes) {
            const result = await Swal.fire({
                title: '¿Eliminar toda la carga?',
                html: `Se eliminarán <b>${vigentes}</b> novedad(es) importadas en esa carga.<br>Esta acción no se puede revertir.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar la carga',
                cancelButtonText: 'Cancelar'
            });
            if (!result.isConfirmed) return;

            try {
                const fd = new FormData();
                fd.append('id_carga', id);
                const resp = await fetch(`${urlImport}/eliminar-carga`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: 'Carga eliminada', text: json.msg, timer: 2000, showConfirmButton: false });
                    window.cargarCargasNov();
                    window.dispatchEvent(new CustomEvent('novedadGuardada'));
                } else {
                    Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: json.error || 'Error al eliminar la carga.' });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
            }
        };

        window.importarNov = async function () {
            const fileInput = document.getElementById('nov_import_file');
            if (!fileInput.files.length) { Swal.fire({ icon: 'info', title: 'Seleccione un archivo', timer: 1500, showConfirmButton: false }); return; }
            const btn = document.getElementById('btnImportarNov');
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importando...';
            const cont = document.getElementById('nov_import_result');
            cont.innerHTML = '';
            try {
                const fd = new FormData(); fd.append('archivo', fileInput.files[0]);
                const resp = await fetch(`${urlImport}/importar-excel`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) {
                    cont.innerHTML = `<div class="alert alert-danger small py-2 mb-0">${esc(json.error)}</div>`;
                } else {
                    let html = `<div class="alert alert-success small py-2 mb-2"><b>${json.creadas}</b> novedad(es) importada(s) de ${json.total}.</div>`;
                    if (json.errores && json.errores.length) {
                        html += `<div class="alert alert-warning small py-2 mb-0" style="max-height:220px;overflow:auto;"><b>${json.errores.length} fila(s) con error:</b><ul class="mb-0 mt-1 ps-3">`;
                        json.errores.forEach(e => { html += `<li>Fila ${e.fila}: ${esc(e.error)}</li>`; });
                        html += '</ul></div>';
                    }
                    cont.innerHTML = html;
                    window.cargarCargasNov();
                    if (json.creadas > 0) window.dispatchEvent(new CustomEvent('novedadGuardada'));
                }
            } catch (e) {
                cont.innerHTML = '<div class="alert alert-danger small py-2 mb-0">Error de red al importar.</div>';
            }
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload me-1"></i> Importar';
        };
    })();
</script>
