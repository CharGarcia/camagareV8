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
/** @var array $bancos */

$base = BASE_URL;
$urlBaseEmp = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .emp-scroll {
        max-height: calc(100dvh - 250px);
        overflow-y: auto;
    }

    .emp-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
    }

    .empleado-row {
        cursor: pointer;
    }

    .empleado-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    .scrollbar-hidden::-webkit-scrollbar {
        display: none;
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form id="emp-form-buscar" class="input-group input-group-sm" style="width:300px" onsubmit="event.preventDefault(); window.cambiarPaginaAjax(1);">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="inputBuscar" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar identificación, nombre..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
            </form>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'identificacion'    => 'Identificación',
                    'nombres_apellidos' => 'Nombre',
                    'email'             => 'Correo',
                    'telefono'          => 'Teléfono',
                    'sexo'              => 'Sexo',
                    'fecha_nacimiento'  => 'F. Nac.',
                    'direccion'         => 'Dirección',
                    'cargo'             => 'Cargo',
                    'departamento'      => 'Departamento',
                    'sueldo_base'       => 'Sueldo Base',
                    'valor_semanal'     => 'V. Semanal',
                    'valor_quincena'    => 'V. Quincena',
                    'region'            => 'Región',
                    'nombre_banco'      => 'Banco',
                    'tipo_cuenta'       => 'T. Cuenta',
                    'numero_cuenta'     => 'N. Cuenta',
                    'tipo_id'           => 'Tipo ID',
                    'estado'            => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseEmp ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBaseEmp ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
                <?php if (!empty($perm['crear'])): ?>
                    <button type="button" class="btn btn-outline-primary" title="Cargar empleados desde Excel" onclick="window.abrirImportEmp()"><i class="bi bi-upload"></i> Importar</button>
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
        <div class="emp-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="identificacion" role="button" data-col="identificacion">ID <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombres_apellidos" role="button" data-col="nombres_apellidos">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="email" role="button" data-col="email">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="telefono" role="button" data-col="telefono">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="sexo" role="button" data-col="sexo">Sexo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="fecha_nacimiento" role="button" data-col="fecha_nacimiento">F. Nac. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="direccion" role="button" data-col="direccion">Dirección <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="cargo" role="button" data-col="cargo">Cargo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="departamento" role="button" data-col="departamento">Depto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="sueldo_base" role="button" data-col="sueldo_base">Sueldo Base <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="valor_semanal" role="button" data-col="valor_semanal">V. Semanal <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="valor_quincena" role="button" data-col="valor_quincena">V. Quincena <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="region" role="button" data-col="region">Región <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_banco" role="button" data-col="nombre_banco">Banco <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tipo_cuenta" role="button" data-col="tipo_cuenta">T. Cuenta <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="numero_cuenta" role="button" data-col="numero_cuenta">N. Cuenta <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="tipo_id" role="button" data-col="tipo_id">Tipo ID <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyEmpleados">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="19" class="text-center py-5 text-muted">No se encontraron empleados registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="empleado-row" onclick="abrirModalEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3" data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars((string)($row['identificacion'] ?? '')) ?></code></td>
                                <td class="fw-medium" data-col="nombres_apellidos"><?= htmlspecialchars((string)($row['nombres_apellidos'] ?? '')) ?></td>
                                <td data-col="email"><?= htmlspecialchars((string)($row['email'] ?? '-')) ?></td>
                                <td data-col="telefono"><?= htmlspecialchars((string)($row['telefono'] ?? '-')) ?></td>
                                <td class="text-center" data-col="sexo"><?= htmlspecialchars((string)($row['sexo'] ?? '-')) ?></td>
                                <td data-col="fecha_nacimiento"><?= htmlspecialchars((string)($row['fecha_nacimiento'] ?? '-')) ?></td>
                                <td data-col="direccion" class="small text-muted"><?= htmlspecialchars((string)($row['direccion'] ?? '-')) ?></td>
                                <td data-col="cargo"><?= htmlspecialchars((string)($row['cargo'] ?? '-')) ?></td>
                                <td data-col="departamento"><?= htmlspecialchars((string)($row['departamento'] ?? '-')) ?></td>
                                <td data-col="sueldo_base" class="text-end fw-bold">$<?= number_format((float)($row['sueldo_base'] ?? 0), 2) ?></td>
                                <td data-col="valor_semanal" class="text-end">$<?= number_format((float)($row['valor_semanal'] ?? 0), 2) ?></td>
                                <td data-col="valor_quincena" class="text-end">$<?= number_format((float)($row['valor_quincena'] ?? 0), 2) ?></td>
                                <td data-col="region" class="text-capitalize"><?= htmlspecialchars((string)($row['region'] ?? '-')) ?></td>
                                <td data-col="nombre_banco"><?= htmlspecialchars((string)($row['nombre_banco'] ?? '-')) ?></td>
                                <td data-col="tipo_cuenta" class="text-capitalize"><?= htmlspecialchars((string)($row['tipo_cuenta'] ?? '-')) ?></td>
                                <td data-col="numero_cuenta"><?= htmlspecialchars((string)($row['numero_cuenta'] ?? '-')) ?></td>
                                <td class="text-center" data-col="tipo_id"><span class="small text-muted"><?= htmlspecialchars((string)($row['tipo_id'] ?? '')) ?></span></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> border border-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> border-opacity-25">
                                        <?= ucfirst($row['estado'] ?? 'activo') ?>
                                    </span>
                                </td>
                                <td class="text-center pe-3" onclick="event.stopPropagation()">
                                    <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(<?= $row['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
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
</script>
<?php include 'modal_empleado.php'; ?>
<script src="<?= $base ?>/js/modulos/empleados_modal.js?v=<?= time() ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseEmp ?>';
        const inputB = document.getElementById('inputBuscar');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';
        let timer;

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyEmpleados').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        if (th.dataset.sort === currentSort) {
                            icon.className = (currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                        } else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    });
                }
            } catch (e) {}
        }

        window.CMG_initSort('empleados', (col, dir) => {
            currentSort = col;
            currentDir = dir;
            cargarListado(1);
        }, { col: currentSort, dir: currentDir });

        if (inputB) inputB.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => cargarListado(1), 400);
        });
    })();
</script>

<!-- Modal Importar Empleados -->
<div class="modal fade" id="modalImportEmp" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2 text-primary"></i>Importar Empleados desde Excel</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small py-2 mb-3">
                    Descarga la <a href="<?= $urlBaseEmp ?>/plantilla-excel" class="fw-bold">plantilla</a>, complétala y súbela.
                    Solo <b>IDENTIFICACION</b> y <b>NOMBRES_APELLIDOS</b> son obligatorios; el resto es opcional
                    (ver hoja «Referencia» para los valores válidos). Los aportes IESS se asignan automáticamente.
                </div>
                <input type="file" id="emp_import_file" class="form-control form-control-sm" accept=".xlsx,.xls">
                <div id="emp_import_result" class="mt-3"></div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <a href="<?= $urlBaseEmp ?>/plantilla-excel" class="btn btn-outline-secondary btn-sm me-auto"><i class="bi bi-download me-1"></i>Plantilla</a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnImportarEmp" onclick="window.importarEmp()"><i class="bi bi-upload me-1"></i> Importar</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';
        const urlImp = '<?= $urlBaseEmp ?>';
        let modalImp = null;
        const esc = (s) => (s == null ? '' : String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])));

        window.abrirImportEmp = function () {
            document.getElementById('emp_import_file').value = '';
            document.getElementById('emp_import_result').innerHTML = '';
            if (!modalImp && typeof bootstrap !== 'undefined') modalImp = new bootstrap.Modal(document.getElementById('modalImportEmp'));
            modalImp?.show();
        };

        window.importarEmp = async function () {
            const fileInput = document.getElementById('emp_import_file');
            if (!fileInput.files.length) { Swal.fire({ icon: 'info', title: 'Seleccione un archivo', timer: 1500, showConfirmButton: false }); return; }
            const btn = document.getElementById('btnImportarEmp');
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importando...';
            const cont = document.getElementById('emp_import_result');
            cont.innerHTML = '';
            try {
                const fd = new FormData(); fd.append('archivo', fileInput.files[0]);
                const resp = await fetch(`${urlImp}/importar-excel`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) {
                    cont.innerHTML = `<div class="alert alert-danger small py-2 mb-0">${esc(json.error)}</div>`;
                } else {
                    let html = `<div class="alert alert-success small py-2 mb-2"><b>${json.creadas}</b> empleado(s) importado(s) de ${json.total}.</div>`;
                    if (json.errores && json.errores.length) {
                        html += `<div class="alert alert-warning small py-2 mb-0" style="max-height:220px;overflow:auto;"><b>${json.errores.length} fila(s) con error:</b><ul class="mb-0 mt-1 ps-3">`;
                        json.errores.forEach(e => { html += `<li>Fila ${e.fila}: ${esc(e.error)}</li>`; });
                        html += '</ul></div>';
                    }
                    cont.innerHTML = html;
                    if (json.creadas > 0 && typeof window.cambiarPaginaAjax === 'function') window.cambiarPaginaAjax(window.currentPage || 1);
                }
            } catch (e) {
                cont.innerHTML = '<div class="alert alert-danger small py-2 mb-0">Error de red al importar.</div>';
            }
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload me-1"></i> Importar';
        };
    })();
</script>