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
                    'producto' => 'Producto', 'estacion' => 'Preparar en',
                    'destacado' => 'Destacado', 'disponible' => 'Disponible',
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
                        <th class="text-center" data-col="estacion">Preparar en</th>
                        <th class="text-center" data-col="destacado">Destacado</th>
                        <th class="text-center pe-3" data-col="disponible">Disponible</th>
                    </tr>
                </thead>
                <tbody id="tbodyMenu">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-egg-fried fs-3 d-block mb-2"></i>No se encontraron ítems.</td></tr>
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
                                <td class="text-center" data-col="estacion">
                                    <?php if (!empty($r['estacion_nombre'])): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-printer"></i> <?= htmlspecialchars($r['estacion_nombre']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Ninguna</span>
                                    <?php endif; ?>
                                </td>
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
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Producto vinculado <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm" id="menu_producto_texto" placeholder="Buscar producto o combo..." autocomplete="off">
                                <input type="hidden" name="id_producto" id="menu_producto_id">
                                <div id="menu_producto_dropdown" class="list-group position-absolute w-100 shadow-sm" style="z-index:1080; display:none; max-height:220px; overflow-y:auto;"></div>
                            </div>
                            <div class="form-text mt-0" style="font-size:0.65rem;">
                                Todo ítem de la carta apunta a un producto: de ahí salen su precio, su foto y el movimiento de inventario. Si es un plato preparado, créalo primero en Productos (puede ser un combo con sus componentes, y el inventario de cada uno se descuenta al facturar).
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Categoría</label>
                            <select class="form-select form-select-sm" name="id_categoria" id="menu_id_categoria">
                                <option value="">Sin categoría</option>
                            </select>
                            <div class="form-text mt-0" style="font-size:0.65rem;">Son las mismas de Productos; se crean y editan en ese módulo.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Orden</label>
                            <input type="number" class="form-control form-control-sm" name="orden" id="menu_orden" step="1" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Precio *</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="precio" id="menu_precio" step="0.01" min="0" required value="0.00">
                            </div>
                            <div class="form-text mt-0 d-none" id="menu_precio_ayuda" style="font-size:0.65rem;">Lo define el producto vinculado; se cambia en Productos.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tarifa IVA <span class="text-danger" title="Obligatoria si el ítem no tiene un producto vinculado">*</span></label>
                            <select class="form-select form-select-sm" name="id_tarifa_iva" id="menu_id_tarifa_iva">
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Precio con impuestos</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="menu_precio_con_iva" step="0.01" min="0" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Preparar en</label>
                            <select class="form-select form-select-sm" name="id_estacion_impresion" id="menu_id_estacion">
                                <option value="">Ninguna</option>
                            </select>
                            <div class="form-text mt-0" style="font-size:0.65rem;">Cocina o barra donde se prepara el plato. Con "Ninguna" no pasa por preparación: se entrega directo. Las estaciones se crean en <b>Configuración Restaurante</b>.</div>
                        </div>
                    </div>
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
    let pendienteEstacion = null;

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

    // Estación del ítem: a qué cocina/barra se manda el plato. Antes salía de la
    // categoría; ahora se elige por ítem, así dos platos de la misma categoría
    // pueden ir a estaciones distintas.
    async function cargarEstacionesEnSelect() {
        const sel = document.getElementById('menu_id_estacion');
        if (!sel) return;
        try {
            const r = await fetch(`${urlBase}/getEstacionesAjax`);
            const d = await r.json();
            const actual = sel.value;
            Array.from(sel.options).slice(1).forEach(o => o.remove());
            (d.ok ? (d.data || []) : []).forEach(e => sel.add(new Option(e.nombre, e.id)));
            if (pendienteEstacion !== null) { sel.value = pendienteEstacion; pendienteEstacion = null; }
            else if (actual) { sel.value = actual; }
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

    // Guardar/Eliminar se deshabilitan mientras corre la petición. El modal es el
    // mismo nodo del DOM en cada apertura, así que si no se restauran al terminar,
    // el siguiente "Nuevo"/"Editar" los muestra todavía en "Guardando...".
    function resetBotonesModal() {
        const btnG = document.getElementById('btnGuardarMenu');
        if (btnG) { btnG.disabled = false; btnG.innerHTML = '<i class="bi bi-check-lg"></i> Guardar'; }
        const btnE = document.getElementById('btnEliminarMenu');
        if (btnE) { btnE.disabled = false; btnE.innerHTML = '<i class="bi bi-trash"></i> Eliminar'; }
    }

    function resetProducto() {
        document.getElementById('menu_producto_id').value = '';
        document.getElementById('menu_producto_texto').value = '';
        setPreciosEditables(true);
    }

    window.abrirModalMenuCrear = function () {
        form.reset();
        resetBotonesModal();
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
        resetBotonesModal();
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

        const selEst = document.getElementById('menu_id_estacion');
        if (d.id_estacion_impresion && Array.from(selEst.options).some(o => o.value == d.id_estacion_impresion)) {
            selEst.value = d.id_estacion_impresion;
        } else if (d.id_estacion_impresion) {
            pendienteEstacion = d.id_estacion_impresion;
        } else {
            selEst.value = '';
        }
        document.getElementById('menu_disponible').checked = (d.disponible === true || d.disponible === 't' || d.disponible === 'true');
        document.getElementById('menu_destacado').checked = (d.destacado === true || d.destacado === 't' || d.destacado === 'true');

        if (d.id_producto) {
            document.getElementById('menu_producto_id').value = d.id_producto;
            document.getElementById('menu_producto_texto').value = (d.producto_codigo ? d.producto_codigo + ' - ' : '') + (d.producto_nombre || '');
            // Se muestra el precio guardado del ítem, no se recopia el del
            // producto; pero como tiene producto vinculado, no se puede editar.
            setPreciosEditables(false);
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
    function setupTypeahead(inputEl, dropdownEl, hiddenEl, fetchFn, renderLabel, onSelect) {
        let debounceTimer;
        // Último lote de resultados, por id: el dropdown solo guarda id y etiqueta
        // en el DOM, y onSelect necesita el objeto completo (precio, tarifa, etc.).
        let ultimosItems = new Map();
        inputEl.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                e.preventDefault();
                hiddenEl.value = ''; inputEl.value = '';
                dropdownEl.style.display = 'none'; dropdownEl.innerHTML = '';
                onSelect?.(null);
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
                ultimosItems = new Map(items.map(it => [String(it.id), it]));
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
            onSelect?.(ultimosItems.get(String(a.dataset.id)) || null);
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
        (it) => `${it.codigo ? it.codigo + ' - ' : ''}${it.nombre}${it.tipo_produccion === '02' ? ' (combo)' : ''}`,
        aplicarProductoVinculado
    );

    /**
     * Precio del ítem: solo se escribe a mano cuando NO hay producto vinculado.
     * Con producto, el precio lo define él y aquí queda de solo lectura — se
     * cambia en Productos, no en la carta.
     * Va con `readonly` y no con `disabled` a propósito: un campo deshabilitado
     * no se envía en el submit, y el precio es obligatorio.
     */
    function setPreciosEditables(editables) {
        const $base = document.getElementById('menu_precio');
        const $conIva = document.getElementById('menu_precio_con_iva');
        [$base, $conIva].forEach(el => {
            el.readOnly = !editables;
            el.classList.toggle('bg-light', !editables);
        });
        document.getElementById('menu_precio_ayuda').classList.toggle('d-none', editables);
    }

    /**
     * Al elegir un producto vinculado se copian su precio, su tarifa de IVA y su
     * categoría, y se recalcula el precio con impuestos.
     * El precio queda bloqueado (lo manda el producto); la tarifa y la categoría
     * quedan editables: son del ítem de la carta y se pueden cambiar aquí.
     * Con `null` (se limpió el producto) se habilitan los precios; lo ya escrito
     * no se toca, para no borrar un precio puesto a mano.
     */
    function aplicarProductoVinculado(prod) {
        if (!prod) { setPreciosEditables(true); return; }

        const selTarifa = document.getElementById('menu_id_tarifa_iva');
        const idTarifa = prod.tarifa_iva ?? '';
        if (idTarifa !== '' && Array.from(selTarifa.options).some(o => o.value == idTarifa)) {
            selTarifa.value = idTarifa;
        }

        // Categoría: las del menú son las de Productos, así que la del producto
        // sirve tal cual. Si todavía no cargó la lista del select, queda
        // pendiente y cargarCategorias() la aplica al terminar.
        const selCat = document.getElementById('menu_id_categoria');
        const idCat = prod.id_categoria ?? '';
        if (idCat !== '' && idCat !== null) {
            if (Array.from(selCat.options).some(o => o.value == idCat)) selCat.value = idCat;
            else pendienteCategoria = idCat;
        }

        document.getElementById('menu_precio').value = (parseFloat(prod.precio_base) || 0).toFixed(2);

        // La foto es la del producto: la carta y el catálogo muestran la misma.
        // Si el producto no tiene, se conserva la que ya tuviera el ítem en vez
        // de dejarlo sin imagen.
        if (prod.imagen) {
            document.getElementById('menu_imagen').value = prod.imagen;
            document.getElementById('menuImagePreview').innerHTML =
                `<img src="${base}/${prod.imagen}" class="img-fluid" style="max-height:100%;object-fit:cover;">`;
            document.getElementById('menuBtnRemoveImage').classList.remove('d-none');
        }

        // El precio base es el ancla: el precio con impuestos se deriva de él.
        anclaPrecio = 'base';
        recalcularPrecios();
        setPreciosEditables(false);
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // El producto vinculado es obligatorio (MenuRules lo vuelve a exigir
            // en el servidor). Se avisa acá para no perder el resto de lo escrito
            // en un viaje de ida y vuelta.
            if (!document.getElementById('menu_producto_id').value) {
                swalError('Selecciona el producto vinculado: todo ítem del menú debe apuntar a un producto.');
                document.getElementById('menu_producto_texto').focus();
                return;
            }

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
                }
            } catch (err) {
                swalError('Error de conexión al guardar.');
            } finally {
                resetBotonesModal();
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
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Eliminando...';
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
            }
        } catch (err) {
            swalError('Error de conexión.');
        } finally {
            resetBotonesModal();
        }
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
