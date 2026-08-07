<?php

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

$base = BASE_URL;
$urlBaseAlu = $base . '/modulos/alumnos';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .alumnos-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .alumnos-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .alumno-row { cursor: pointer; }
    .alumno-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Alumnos</h5>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/modulos/alumnos-campus" class="btn btn-outline-secondary btn-sm"><i class="bi bi-geo-alt me-1"></i>Campus</a>
        <a href="<?= $base ?>/modulos/alumnos-niveles" class="btn btn-outline-secondary btn-sm"><i class="bi bi-mortarboard me-1"></i>Niveles</a>
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalAlumnoCrear()">
                <i class="bi bi-plus-lg me-1"></i> Nuevo
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 260px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="buscarAlumno" class="form-control border-start-0 ps-0 shadow-none" placeholder="Buscar alumno, código o representante..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
            </div>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombres'          => 'Alumno',
                    'codigo_alumno'    => 'Código',
                    'campus'           => 'Campus',
                    'nivel'            => 'Nivel/Curso',
                    'representante'    => 'Representante',
                    'matricula'        => 'Matrícula',
                    'estado_academico' => 'Estado',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                ?>
                <a id="btnExportPdf" href="<?= $urlBaseAlu ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBaseAlu ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="alumnos-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="nombres" data-col="nombres" role="button">Alumno <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="codigo_alumno" class="sortable-header" data-sort="codigo_alumno" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="campus" class="sortable-header" data-sort="campus" role="button">Campus <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="nivel" class="sortable-header" data-sort="nivel" role="button">Nivel/Curso <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="representante" class="sortable-header" data-sort="representante" role="button">Representante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="matricula">Matrícula</th>
                        <th class="text-center sortable-header" data-sort="estado_academico" data-col="estado_academico" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyAlumnos">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No se encontraron alumnos.</td></tr>
                    <?php else: ?>
                        <?php
                        $estadoBadges = ['activo' => 'success', 'retirado' => 'secondary', 'egresado' => 'info', 'suspendido' => 'danger'];
                        foreach ($rows as $row):
                            $estado = $row['estado_academico'] ?? 'activo';
                            $color = $estadoBadges[$estado] ?? 'secondary';
                            $vigente = !empty($row['matricula_vigente']) && in_array($row['matricula_vigente'], [true, 't', 1, '1'], true);
                        ?>
                            <tr class="alumno-row" onclick="abrirModalAlumnoEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-bold"><?= htmlspecialchars(trim(($row['apellidos'] ?? '') . ' ' . ($row['nombres'] ?? ''))) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars((string)($row['codigo_alumno'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($row['campus_actual_nombre'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string)($row['nivel_actual_nombre'] ?? '—')) ?></td>
                                <td class="small"><?= htmlspecialchars((string)($row['representante_nombre'] ?? '—')) ?></td>
                                <td class="text-center">
                                    <?php if ($vigente): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10">Vigente</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10">Sin matrícula</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?> border-opacity-10"><?= ucfirst($estado) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_alumno.php'; ?>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseAlu ?>';
        const inputB = document.getElementById('buscarAlumno');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';
        let timer;

        window.cambiarPaginaAjax = (p) => fetchSearch(p);

        async function fetchSearch(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    document.getElementById('tbodyAlumnos').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
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
        window.fetchSearchAlumnos = fetchSearch;

        if (typeof window.CMG_initSort === 'function') {
            window.CMG_initSort('alumnos', (col, dir) => {
                currentSort = col;
                currentDir = dir;
                fetchSearch(1);
            }, { col: currentSort, dir: currentDir });
        } else {
            document.querySelectorAll('.sortable-header').forEach(th => {
                th.addEventListener('click', () => {
                    const col = th.dataset.sort;
                    currentDir = (currentSort === col && currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                    currentSort = col;
                    fetchSearch(1);
                });
            });
        }

        if (inputB) inputB.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => fetchSearch(1), 400);
        });
    })();
</script>
