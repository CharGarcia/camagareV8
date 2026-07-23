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

$base = BASE_URL;
$urlBaseMenu = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'orden';
$ordenDir   = $ordenDir ?? 'asc';
$buscar     = $buscar ?? '';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .menu-header { flex-shrink: 0; }
    .menu-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .menu-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .menu-row { cursor: pointer; }
    .menu-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="menu-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-egg-fried"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalMenuCrear()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorMENU" style="width: 420px;"></div>
            <input type="hidden" id="buscarMenu" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorMENU',
                        hiddenInputId: 'buscarMenu',
                        fields: [
                            { key: 'nombre',    label: 'Nombre',    icon: 'bi-egg-fried', type: 'text' },
                            { key: 'categoria', label: 'Categoría', icon: 'bi-tags',      type: 'text' },
                            { key: 'producto',  label: 'Producto vinculado', icon: 'bi-box-seam', type: 'text' },
                            { key: 'precio',    label: 'Precio',    icon: 'bi-cash',      type: 'number_range' },
                            { key: 'disponible', label: 'Disponible', icon: 'bi-toggle-on', type: 'select', options: [
                                { v: 'true',  l: 'Sí' },
                                { v: 'false', l: 'No' },
                            ]},
                            { key: 'destacado', label: 'Destacado', icon: 'bi-star', type: 'select', options: [
                                { v: 'true',  l: 'Sí' },
                                { v: 'false', l: 'No' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_disponibles', label: 'Disponibles',    mk: () => ({ key: 'disponible', op: '=', value: 'true',  display: 'Sí' }) },
                            { id: 'qf_agotados',    label: 'No disponibles', mk: () => ({ key: 'disponible', op: '=', value: 'false', display: 'No' }) },
                            { id: 'qf_destacados',  label: 'Destacados',     mk: () => ({ key: 'destacado',  op: '=', value: 'true',  display: 'Sí' }) },
                        ],
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'foto' => 'Foto', 'nombre' => 'Nombre', 'categoria' => 'Categoría',
                    'precio' => 'Precio', 'iva' => 'IVA', 'precio_con_iva' => 'Precio c/IVA',
                    'producto' => 'Producto', 'destacado' => 'Destacado', 'disponible' => 'Disponible',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseMenu ?>/export-pdf?b=<?= urlencode($buscar) ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBaseMenu ?>/export-excel?b=<?= urlencode($buscar) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= ($page <= 1) ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= ($page >= $totalPages) ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="menu-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" data-col="foto">Foto</th>
                        <th class="sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="categoria" data-col="categoria">Categoría <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="precio" data-col="precio">Precio <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="iva">IVA</th>
                        <th class="text-end" data-col="precio_con_iva">Precio c/IVA</th>
                        <th class="text-center" data-col="producto">Producto</th>
                        <th class="text-center" data-col="destacado">Destacado</th>
                        <th class="text-center pe-3" data-col="disponible">Disponible</th>
                    </tr>
                </thead>
                <tbody id="tbodyMenu">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-egg-fried fs-3 d-block mb-2"></i>No se encontraron ítems.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $pct = (float) ($r['porcentaje_iva'] ?? 0);
                                $precioConIva = (float) ($r['precio'] ?? 0) * (1 + $pct / 100);
                            ?>
                            <tr class="menu-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalMenuEditar(this)">
                                <td class="ps-3" data-col="foto">
                                    <?php if (!empty($r['imagen'])): ?>
                                        <img src="<?= htmlspecialchars(rtrim($base, '/') . '/' . $r['imagen']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted" style="width:40px;height:40px;"><i class="bi bi-image"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-medium" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td class="text-center" data-col="categoria"><?= htmlspecialchars($r['categoria_nombre'] ?? '') ?></td>
                                <td class="text-end" data-col="precio">$<?= number_format((float) ($r['precio'] ?? 0), 2) ?></td>
                                <td class="text-center" data-col="iva"><?= $pct > 0 ? number_format($pct, 0) . '%' : '—' ?></td>
                                <td class="text-end" data-col="precio_con_iva">$<?= number_format($precioConIva, 2) ?></td>
                                <td class="text-center" data-col="producto"><?= htmlspecialchars($r['producto_nombre'] ?? '—') ?></td>
                                <td class="text-center" data-col="destacado">
                                    <?php if (!empty($r['destacado'])): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><i class="bi bi-star-fill"></i> Destacado</span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-3" data-col="disponible">
                                    <?php if (!empty($r['disponible'])): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Disponible</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">No disponible</span>
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

<!-- Modal Menú -->
<div class="modal fade" id="modalMenu" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $urlBaseMenu ?>/store" id="formMenu" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-egg-fried me-2 text-primary"></i> <span id="tituloModalMenu">Nuevo ítem del menú</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pb-0">
                    <input type="hidden" name="id" id="menu_id" value="">

                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="tab-menu-general-btn" data-bs-toggle="tab" data-bs-target="#tab-menu-general" type="button">General</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="tab-menu-categorias-btn" data-bs-toggle="tab" data-bs-target="#tab-menu-categorias" type="button" onclick="cargarListaMenuCategorias()">
                                <i class="bi bi-tags me-1"></i>Categorías
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="tab-menu-estaciones-btn" data-bs-toggle="tab" data-bs-target="#tab-menu-estaciones" type="button" onclick="cargarListaEstaciones()">
                                <i class="bi bi-printer me-1"></i>Estaciones
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-menu-general">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Foto</label>
                            <div class="d-flex flex-column align-items-start gap-2">
                                <div id="menuImagePreview" class="rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden" style="width:160px;height:160px;cursor:pointer" onclick="document.getElementById('menuInputImage').click()" title="Clic para cambiar imagen">
                                    <i class="bi bi-image text-muted" style="font-size:2.5rem;opacity:0.25"></i>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('menuInputImage').click()"><i class="bi bi-upload me-1"></i> Subir</button>
                                    <button type="button" id="menuBtnRemoveImage" class="btn btn-outline-danger btn-sm d-none" onclick="removerImagenMenu()"><i class="bi bi-trash"></i></button>
                                </div>
                                <input type="hidden" name="imagen" id="menu_imagen">
                                <input type="file" id="menuInputImage" class="d-none" accept="image/*" onchange="uploadMenuImage(this)">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Nombre *</label>
                                    <input type="text" class="form-control form-control-sm" name="nombre" id="menu_nombre" required maxlength="200" placeholder="Ej. Hamburguesa clásica">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Descripción</label>
                                    <textarea class="form-control form-control-sm" name="descripcion" id="menu_descripcion" rows="2" maxlength="500" placeholder="Ingredientes, tamaño, notas para el cliente..."></textarea>
                                </div>
                                <div class="col-12 d-flex gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="disponible" id="menu_disponible" checked>
                                        <label class="form-check-label small fw-bold" for="menu_disponible">Disponible</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="destacado" id="menu_destacado">
                                        <label class="form-check-label small fw-bold" for="menu_destacado">Destacado (plato del día)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Precio *</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="precio" id="menu_precio" step="0.01" min="0" required value="0.00">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tarifa IVA <span class="text-danger" title="Obligatoria si el ítem no tiene un producto vinculado">*</span></label>
                            <select class="form-select form-select-sm" name="id_tarifa_iva" id="menu_id_tarifa_iva">
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Categoría del menú</label>
                            <select class="form-select form-select-sm" name="id_categoria" id="menu_id_categoria">
                                <option value="">Sin categoría</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Precio con impuestos</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="menu_precio_con_iva" step="0.01" min="0" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Producto vinculado (opcional)</label>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm" id="menu_producto_texto" placeholder="Buscar producto o combo..." autocomplete="off">
                                <input type="hidden" name="id_producto" id="menu_producto_id">
                                <div id="menu_producto_dropdown" class="list-group position-absolute w-100 shadow-sm" style="z-index:1080; display:none; max-height:220px; overflow-y:auto;"></div>
                            </div>
                            <div class="form-text mt-0" style="font-size:0.65rem;">
                                Si lo vinculas a un producto compuesto (combo armado en Productos → Componentes), el inventario de cada componente se descuenta automáticamente al facturar. Déjalo vacío si es un ítem sin inventario.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Orden</label>
                            <input type="number" class="form-control form-control-sm" name="orden" id="menu_orden" step="1" value="0">
                        </div>
                    </div>
                    </div>

                    <div class="tab-pane fade" id="tab-menu-categorias">
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-5">
                                <label class="form-label small fw-bold">Nombre</label>
                                <input type="text" class="form-control form-control-sm" id="mcat_nombre" maxlength="60" placeholder="Ej. Entradas">
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-bold">Enviar a</label>
                                <select class="form-select form-select-sm" id="mcat_estacion">
                                    <option value="">Ninguna</option>
                                </select>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="btnAgregarMenuCategoria"><i class="bi bi-plus-lg"></i> Agregar</button>
                            </div>
                        </div>
                        <div class="form-text mt-0 mb-2" style="font-size:0.65rem;">Las estaciones (impresoras/pantallas de cocina o barra) se crean en la pestaña "Estaciones".</div>
                        <input type="hidden" id="mcat_id_editando" value="">
                        <div id="mcat_lista" class="list-group" style="max-height:220px; overflow-y:auto;"></div>
                    </div>

                    <div class="tab-pane fade" id="tab-menu-estaciones">
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-5">
                                <label class="form-label small fw-bold">Nombre</label>
                                <input type="text" class="form-control form-control-sm" id="est_nombre" maxlength="60" placeholder="Ej. Barra 1, Cocina Caliente...">
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-bold">Tipo</label>
                                <select class="form-select form-select-sm" id="est_tipo">
                                    <option value="cocina">Cocina</option>
                                    <option value="barra">Barra</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="btnAgregarEstacion"><i class="bi bi-plus-lg"></i> Agregar</button>
                            </div>
                        </div>
                        <div class="form-text mt-0 mb-2" style="font-size:0.65rem;">"Tipo" solo define el ícono/color en el tablero — puedes crear tantas estaciones de cada tipo como necesites (ej. 5 barras, 3 cocinas).</div>
                        <input type="hidden" id="est_id_editando" value="">
                        <div id="est_lista" class="list-group" style="max-height:220px; overflow-y:auto;"></div>
                    </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light mt-3">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarMenu" onclick="eliminarMenu()"><i class="bi bi-trash"></i> Eliminar</button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarMenu"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlBaseMenu ?>';
    const base = '<?= rtrim($base, "/") ?>';
    const form = document.getElementById('formMenu');
    let modalInst = null;
    let pendienteCategoria = null;
    let pendienteTarifa = null;

    async function cargarCategorias() {
        try {
            const r = await fetch(`${urlBase}/getMenuCategoriasAjax`);
            const d = await r.json();
            const sel = document.getElementById('menu_id_categoria');
            if (d.ok && sel) {
                const actual = sel.value;
                Array.from(sel.options).slice(1).forEach(o => o.remove());
                (d.data || []).forEach(c => sel.add(new Option(c.nombre, c.id)));
                if (pendienteCategoria !== null) { sel.value = pendienteCategoria; pendienteCategoria = null; }
                else if (actual) { sel.value = actual; }
            }
        } catch (e) {}
    }

    async function cargarTarifasIva() {
        try {
            const r = await fetch(`${urlBase}/getTarifasIvaAjax`);
            const d = await r.json();
            const sel = document.getElementById('menu_id_tarifa_iva');
            if (d.ok && sel) {
                (d.data || []).forEach(t => {
                    const opt = new Option(`${t.tarifa || t.codigo} (${t.porcentaje_iva}%)`, t.id);
                    opt.dataset.pct = t.porcentaje_iva;
                    sel.add(opt);
                });
                if (pendienteTarifa !== null) { sel.value = pendienteTarifa; pendienteTarifa = null; }
            }
        } catch (e) {}
        recalcularPrecios();
    }

    // ─── Precio base vs. precio con impuestos: el último que edites manualmente
    // queda como "ancla"; el otro se recalcula a partir de él. Si cambias la
    // tarifa de IVA, se recalcula el que NO es el ancla (el ancla no se toca).
    let anclaPrecio = 'base'; // 'base' | 'conIva'

    function getPctIvaSeleccionado() {
        const sel = document.getElementById('menu_id_tarifa_iva');
        return parseFloat(sel?.selectedOptions?.[0]?.dataset?.pct) || 0;
    }

    function recalcularPrecios() {
        const pct = getPctIvaSeleccionado();
        const $base = document.getElementById('menu_precio');
        const $conIva = document.getElementById('menu_precio_con_iva');
        if (anclaPrecio === 'conIva') {
            const conIva = parseFloat($conIva.value) || 0;
            $base.value = (conIva / (1 + pct / 100)).toFixed(2);
        } else {
            const base = parseFloat($base.value) || 0;
            $conIva.value = (base * (1 + pct / 100)).toFixed(2);
        }
    }

    document.getElementById('menu_precio').addEventListener('input', () => {
        anclaPrecio = 'base';
        recalcularPrecios();
    });
    document.getElementById('menu_precio_con_iva').addEventListener('input', () => {
        anclaPrecio = 'conIva';
        recalcularPrecios();
    });
    document.getElementById('menu_id_tarifa_iva').addEventListener('change', recalcularPrecios);

    async function cargarEstacionesEnSelect() {
        const sel = document.getElementById('mcat_estacion');
        if (!sel) return;
        try {
            const r = await fetch(`${urlBase}/getEstacionesAjax`);
            const d = await r.json();
            const actual = sel.value;
            Array.from(sel.options).slice(1).forEach(o => o.remove());
            (d.ok ? (d.data || []) : []).forEach(e => sel.add(new Option(e.nombre, e.id)));
            sel.value = actual;
        } catch (e) {}
    }

    cargarCategorias();
    cargarTarifasIva();
    cargarEstacionesEnSelect();

    function swalErrorCat(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function escapeHtmlMcat(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // ─── Pestaña "Categorías" del menú (propias, separadas de Productos) ──────

    window.cargarListaMenuCategorias = async function () {
        await cargarEstacionesEnSelect();
        const cont = document.getElementById('mcat_lista');
        cont.innerHTML = '<div class="text-center text-muted small py-3"><span class="spinner-border spinner-border-sm"></span></div>';
        try {
            const r = await fetch(`${urlBase}/getMenuCategoriasAjax`);
            const d = await r.json();
            const rows = d.ok ? (d.data || []) : [];
            if (!rows.length) {
                cont.innerHTML = '<div class="text-center text-muted small py-3">Aún no hay categorías del menú.</div>';
                return;
            }
            cont.innerHTML = rows.map(c => {
                const badge = c.estacion_nombre
                    ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-2">${escapeHtmlMcat(c.estacion_nombre)}</span>`
                    : `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2">Sin estación</span>`;
                return `<div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <span class="fw-medium">${escapeHtmlMcat(c.nombre)}</span>
                                ${badge}
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="editarMenuCategoria(${c.id}, '${escapeHtmlMcat(c.nombre)}', ${c.id_estacion_impresion || 'null'})"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarMenuCategoria(${c.id})"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>`;
            }).join('');
        } catch (e) {
            cont.innerHTML = '<div class="text-center text-danger small py-3">Error al cargar.</div>';
        }
    };

    window.editarMenuCategoria = function (id, nombre, idEstacion) {
        document.getElementById('mcat_id_editando').value = id;
        document.getElementById('mcat_nombre').value = nombre;
        document.getElementById('mcat_estacion').value = idEstacion || '';
        document.getElementById('btnAgregarMenuCategoria').innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
    };

    function resetFormMenuCategoria() {
        document.getElementById('mcat_id_editando').value = '';
        document.getElementById('mcat_nombre').value = '';
        document.getElementById('mcat_estacion').value = '';
        document.getElementById('btnAgregarMenuCategoria').innerHTML = '<i class="bi bi-plus-lg"></i> Agregar';
    }

    document.getElementById('btnAgregarMenuCategoria').addEventListener('click', async () => {
        const nombre = document.getElementById('mcat_nombre').value.trim();
        if (!nombre) { swalErrorCat('Ingresa un nombre para la categoría.'); return; }
        const id = document.getElementById('mcat_id_editando').value;
        const fd = new FormData();
        fd.append('nombre', nombre);
        fd.append('id_estacion_impresion', document.getElementById('mcat_estacion').value);
        if (id) fd.append('id', id);

        try {
            const r = await fetch(`${urlBase}/${id ? 'actualizarMenuCategoriaAjax' : 'crearMenuCategoriaAjax'}`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalErrorCat(d.error || 'No se pudo guardar la categoría.'); return; }
            resetFormMenuCategoria();
            await Promise.all([cargarListaMenuCategorias(), cargarCategorias()]);
        } catch (e) { swalErrorCat('Error de conexión.'); }
    });

    window.eliminarMenuCategoria = async function (id) {
        const { isConfirmed } = await Swal.fire({
            title: '¿Eliminar esta categoría del menú?', icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
        });
        if (!isConfirmed) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const r = await fetch(`${urlBase}/eliminarMenuCategoriaAjax`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalErrorCat(d.error || 'No se pudo eliminar.'); return; }
            await Promise.all([cargarListaMenuCategorias(), cargarCategorias()]);
        } catch (e) { swalErrorCat('Error de conexión.'); }
    };

    // ─── Pestaña "Estaciones" (catálogo compartido: Productos + Menú + KDS) ───

    const TIPO_ESTACION_LABEL = { cocina: ['Cocina', 'warning'], barra: ['Barra', 'info'], otro: ['Otro', 'secondary'] };

    window.cargarListaEstaciones = async function () {
        const cont = document.getElementById('est_lista');
        cont.innerHTML = '<div class="text-center text-muted small py-3"><span class="spinner-border spinner-border-sm"></span></div>';
        try {
            const r = await fetch(`${urlBase}/getEstacionesAjax`);
            const d = await r.json();
            const rows = d.ok ? (d.data || []) : [];
            if (!rows.length) {
                cont.innerHTML = '<div class="text-center text-muted small py-3">Aún no hay estaciones. Crea, por ejemplo, "Cocina" y "Barra".</div>';
                return;
            }
            cont.innerHTML = rows.map(e => {
                const [lbl, color] = TIPO_ESTACION_LABEL[e.tipo] || TIPO_ESTACION_LABEL.otro;
                return `<div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <span class="fw-medium">${escapeHtmlMcat(e.nombre)}</span>
                                <span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25 ms-2">${lbl}</span>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="editarEstacion(${e.id}, '${escapeHtmlMcat(e.nombre)}', '${e.tipo}')"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarEstacion(${e.id})"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>`;
            }).join('');
        } catch (e) {
            cont.innerHTML = '<div class="text-center text-danger small py-3">Error al cargar.</div>';
        }
    };

    window.editarEstacion = function (id, nombre, tipo) {
        document.getElementById('est_id_editando').value = id;
        document.getElementById('est_nombre').value = nombre;
        document.getElementById('est_tipo').value = tipo;
        document.getElementById('btnAgregarEstacion').innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
    };

    function resetFormEstacion() {
        document.getElementById('est_id_editando').value = '';
        document.getElementById('est_nombre').value = '';
        document.getElementById('est_tipo').value = 'cocina';
        document.getElementById('btnAgregarEstacion').innerHTML = '<i class="bi bi-plus-lg"></i> Agregar';
    }

    document.getElementById('btnAgregarEstacion').addEventListener('click', async () => {
        const nombre = document.getElementById('est_nombre').value.trim();
        if (!nombre) { swalErrorCat('Ingresa un nombre para la estación.'); return; }
        const id = document.getElementById('est_id_editando').value;
        const fd = new FormData();
        fd.append('nombre', nombre);
        fd.append('tipo', document.getElementById('est_tipo').value);
        if (id) fd.append('id', id);

        try {
            const r = await fetch(`${urlBase}/${id ? 'actualizarEstacionAjax' : 'crearEstacionAjax'}`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalErrorCat(d.error || 'No se pudo guardar la estación.'); return; }
            resetFormEstacion();
            await Promise.all([cargarListaEstaciones(), cargarEstacionesEnSelect()]);
        } catch (e) { swalErrorCat('Error de conexión.'); }
    });

    window.eliminarEstacion = async function (id) {
        const { isConfirmed } = await Swal.fire({
            title: '¿Eliminar esta estación?', icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
        });
        if (!isConfirmed) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const r = await fetch(`${urlBase}/eliminarEstacionAjax`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalErrorCat(d.error || 'No se pudo eliminar.'); return; }
            await Promise.all([cargarListaEstaciones(), cargarEstacionesEnSelect()]);
        } catch (e) { swalErrorCat('Error de conexión.'); }
    };

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined') {
            modalInst = new bootstrap.Modal(document.getElementById('modalMenu'));
        }
        return modalInst;
    }
    function swalToast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2500, timerProgressBar: true });
    }
    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }

    function resetProducto() {
        document.getElementById('menu_producto_id').value = '';
        document.getElementById('menu_producto_texto').value = '';
    }

    function volverATabGeneral() {
        resetFormMenuCategoria();
        resetFormEstacion();
        if (typeof bootstrap !== 'undefined') {
            const tabEl = document.getElementById('tab-menu-general-btn');
            (bootstrap.Tab.getInstance(tabEl) || new bootstrap.Tab(tabEl)).show();
        }
    }

    window.abrirModalMenuCrear = function () {
        form.reset();
        volverATabGeneral();
        document.getElementById('menu_id').value = '';
        document.getElementById('menu_imagen').value = '';
        document.getElementById('menuImagePreview').innerHTML = '<i class="bi bi-image text-muted" style="font-size:2.5rem;opacity:0.25"></i>';
        document.getElementById('menuBtnRemoveImage').classList.add('d-none');
        document.getElementById('menu_disponible').checked = true;
        document.getElementById('menu_destacado').checked = false;
        resetProducto();
        document.getElementById('tituloModalMenu').textContent = 'Nuevo ítem del menú';
        document.getElementById('btnEliminarMenu')?.classList.add('d-none');
        anclaPrecio = 'base';
        recalcularPrecios();
        getModal()?.show();
        setTimeout(() => document.getElementById('menu_nombre')?.focus(), 400);
    };

    window.abrirModalMenuEditar = function (row) {
        const d = JSON.parse(row.dataset.row);
        form.reset();
        volverATabGeneral();
        document.getElementById('menu_id').value = d.id;
        document.getElementById('menu_nombre').value = d.nombre || '';
        document.getElementById('menu_descripcion').value = d.descripcion || '';
        document.getElementById('menu_precio').value = parseFloat(d.precio || 0).toFixed(2);
        document.getElementById('menu_orden').value = d.orden || 0;

        const selCat = document.getElementById('menu_id_categoria');
        if (d.id_categoria && Array.from(selCat.options).some(o => o.value == d.id_categoria)) {
            selCat.value = d.id_categoria;
        } else if (d.id_categoria) {
            pendienteCategoria = d.id_categoria; // aún no cargó la lista; se aplica cuando termine
        } else {
            selCat.value = '';
        }

        const selTarifa = document.getElementById('menu_id_tarifa_iva');
        if (d.id_tarifa_iva && Array.from(selTarifa.options).some(o => o.value == d.id_tarifa_iva)) {
            selTarifa.value = d.id_tarifa_iva;
        } else if (d.id_tarifa_iva) {
            pendienteTarifa = d.id_tarifa_iva;
        } else {
            selTarifa.value = '';
        }
        document.getElementById('menu_disponible').checked = (d.disponible === true || d.disponible === 't' || d.disponible === 'true');
        document.getElementById('menu_destacado').checked = (d.destacado === true || d.destacado === 't' || d.destacado === 'true');

        if (d.id_producto) {
            document.getElementById('menu_producto_id').value = d.id_producto;
            document.getElementById('menu_producto_texto').value = (d.producto_codigo ? d.producto_codigo + ' - ' : '') + (d.producto_nombre || '');
        } else {
            resetProducto();
        }

        document.getElementById('menu_imagen').value = d.imagen || '';
        if (d.imagen) {
            document.getElementById('menuImagePreview').innerHTML = `<img src="${base}/${d.imagen}" class="img-fluid" style="max-height:100%;object-fit:cover;">`;
            document.getElementById('menuBtnRemoveImage').classList.remove('d-none');
        } else {
            document.getElementById('menuImagePreview').innerHTML = '<i class="bi bi-image text-muted" style="font-size:2.5rem;opacity:0.25"></i>';
            document.getElementById('menuBtnRemoveImage').classList.add('d-none');
        }

        document.getElementById('tituloModalMenu').textContent = 'Editar ítem del menú';
        document.getElementById('btnEliminarMenu')?.classList.remove('d-none');
        anclaPrecio = 'base';
        recalcularPrecios();
        getModal()?.show();
    };

    window.uploadMenuImage = async function (input) {
        const file = input.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('image', file);
        try {
            const r = await fetch(`${urlBase}/uploadImageAjax`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo subir la imagen.'); return; }
            document.getElementById('menu_imagen').value = d.path;
            document.getElementById('menuImagePreview').innerHTML = `<img src="${base}/${d.path}" class="img-fluid" style="max-height:100%;object-fit:cover;">`;
            document.getElementById('menuBtnRemoveImage').classList.remove('d-none');
        } catch (e) { swalError('Error de conexión al subir la imagen.'); }
    };

    window.removerImagenMenu = function () {
        document.getElementById('menu_imagen').value = '';
        document.getElementById('menuImagePreview').innerHTML = '<i class="bi bi-image text-muted" style="font-size:2.5rem;opacity:0.25"></i>';
        document.getElementById('menuBtnRemoveImage').classList.add('d-none');
    };

    // ─── Typeahead del producto vinculado (mismo patrón que mayores/index.php) ─
    function setupTypeahead(inputEl, dropdownEl, hiddenEl, fetchFn, renderLabel) {
        let debounceTimer;
        inputEl.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                e.preventDefault();
                hiddenEl.value = ''; inputEl.value = '';
                dropdownEl.style.display = 'none'; dropdownEl.innerHTML = '';
            }
        });
        inputEl.addEventListener('input', () => {
            hiddenEl.value = '';
            clearTimeout(debounceTimer);
            const q = inputEl.value.trim();
            if (q.length < 1) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
            debounceTimer = setTimeout(async () => {
                let items = [];
                try { items = await fetchFn(q); } catch (e) { return; }
                if (!items.length) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
                dropdownEl.innerHTML = items.map(it => {
                    const label = renderLabel(it);
                    return `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${it.id}" data-label="${label.replace(/"/g, '&quot;')}">${label}</a>`;
                }).join('');
                dropdownEl.style.display = 'block';
            }, 300);
        });
        dropdownEl.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-id]');
            if (!a) return;
            e.preventDefault();
            hiddenEl.value = a.dataset.id;
            inputEl.value = a.dataset.label;
            dropdownEl.style.display = 'none';
        });
        document.addEventListener('click', (e) => {
            if (e.target !== inputEl && !dropdownEl.contains(e.target)) dropdownEl.style.display = 'none';
        });
    }

    setupTypeahead(
        document.getElementById('menu_producto_texto'),
        document.getElementById('menu_producto_dropdown'),
        document.getElementById('menu_producto_id'),
        async (q) => {
            const r = await fetch(`${urlBase}/getProductosAjax?q=${encodeURIComponent(q)}`);
            const d = await r.json();
            return d.ok ? d.data : [];
        },
        (it) => `${it.codigo ? it.codigo + ' - ' : ''}${it.nombre}${it.tipo_produccion === '02' ? ' (combo)' : ''}`
    );

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnGuardarMenu');
            const actionUrl = document.getElementById('menu_id').value ? `${urlBase}/update` : `${urlBase}/store`;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
            try {
                const fd = new FormData(form);
                const resp = await fetch(actionUrl, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    swalToast('success', json.msg || 'Guardado correctamente.');
                    getModal()?.hide();
                    fetchSearch(window.currentPage || 1);
                } else {
                    swalError(json.error || 'No se pudo guardar el ítem.');
                    btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                }
            } catch (err) {
                swalError('Error de conexión al guardar.');
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
            }
        });
    }

    window.eliminarMenu = async function () {
        const id = document.getElementById('menu_id').value;
        if (!id) return;
        const { isConfirmed } = await Swal.fire({
            title: '¿Eliminar este ítem del menú?', text: 'Esta acción no se puede deshacer.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
        });
        if (!isConfirmed) return;
        const btn = document.getElementById('btnEliminarMenu');
        btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                swalToast('success', json.msg || 'Ítem eliminado.');
                getModal()?.hide();
                fetchSearch(window.currentPage || 1);
            } else {
                swalError(json.error || 'No se pudo eliminar.');
                btn.disabled = false;
            }
        } catch (err) { swalError('Error de conexión.'); btn.disabled = false; }
    };

    const inputBuscar = document.getElementById('buscarMenu');
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir = '<?= $ordenDir ?>';
    window.currentPage = 1;

    window.fetchSearch = async (page = 1) => {
        const term = inputBuscar ? inputBuscar.value.trim() : '';
        const url = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        try {
            const resp = await fetch(url);
            const data = await resp.json();
            if (data.ok) {
                window.currentPage = page;
                document.getElementById('tbodyMenu').innerHTML = data.rows;
                document.getElementById('paginationContainer').innerHTML = data.pagination;
                document.getElementById('paginationInfo').textContent = data.info;
                document.getElementById('btnExportPdf').href = data.pdf_url;
                document.getElementById('btnExportExcel').href = data.excel_url;
                document.querySelectorAll('.sortable-header').forEach(th => {
                    const icon = th.querySelector('i');
                    if (th.dataset.sort === window.currentSort) {
                        icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                    } else {
                        icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    }
                });
            }
        } catch (err) {}
    };

    window.cambiarPaginaAjax = function (n) { window.fetchSearch(n); };

    document.querySelectorAll('.sortable-header').forEach(header => {
        header.addEventListener('click', () => {
            const sortField = header.dataset.sort;
            window.currentDir = (window.currentSort === sortField && window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
            window.currentSort = sortField;
            if (typeof window.guardarOrdenacionVista === 'function') {
                window.guardarOrdenacionVista('menu', window.currentSort, window.currentDir);
            }
            fetchSearch(1);
        });
    });

})();
</script>
