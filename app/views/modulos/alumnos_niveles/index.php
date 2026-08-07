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
$urlBaseNivel = $base . '/modulos/alumnos-niveles';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .alumnos-niveles-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .alumnos-niveles-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .nivel-row { cursor: pointer; }
    .nivel-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Niveles / Cursos</h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalNivelCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="buscarNivel" class="form-control border-start-0 ps-0 shadow-none" placeholder="Buscar nivel/curso..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
            </div>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = ['nombre' => 'Nombre', 'orden' => 'Orden', 'estado' => 'Estado'];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                ?>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfoNivel" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="paginationContainerNivel" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaNivelAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaNivelAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="alumnos-niveles-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="nombre" data-col="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="orden" data-col="orden" role="button">Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" data-col="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyNivel">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="3" class="text-center py-5 text-muted">No se encontraron niveles/cursos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="nivel-row" onclick="abrirModalNivelEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-bold"><?= htmlspecialchars((string)($row['nombre'] ?? '')) ?></td>
                                <td class="text-center"><?= (int)($row['orden'] ?? 0) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> border border-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> border-opacity-10">
                                        <?= ($row['estado'] ?? 'activo') === 'activo' ? 'Activo' : 'Inactivo' ?>
                                    </span>
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
<?php include 'modal_nivel.php'; ?>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseNivel ?>';
        const inputB = document.getElementById('buscarNivel');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';
        let timer;

        window.cambiarPaginaNivelAjax = (p) => fetchSearch(p);

        async function fetchSearch(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    document.getElementById('tbodyNivel').innerHTML = data.rows;
                    document.getElementById('paginationContainerNivel').innerHTML = data.pagination;
                    document.getElementById('paginationInfoNivel').textContent = data.info;
                }
            } catch (e) {}
        }
        window.fetchSearchNivel = fetchSearch;

        if (inputB) inputB.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => fetchSearch(1), 400);
        });

        document.querySelectorAll('.sortable-header').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                currentDir = (currentSort === col && currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                currentSort = col;
                fetchSearch(1);
            });
        });
    })();
</script>
