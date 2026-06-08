<?php
/** @var array $empresa */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var array $bodegas */
/** @var array $productos */
/** @var array $filtros */
/** @var array $perm */
/** @var string $base */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$buscar   = $filtros['buscar'] ?? '';
$ordenCol = $filtros['sort'] ?? 'fecha_movimiento';
$ordenDir = $filtros['dir'] ?? 'desc';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

$columnasTabla = [
    'fecha_movimiento' => 'Fecha',
    'producto_nombre'  => 'Producto',
    'bodega_nombre'    => 'Bodega',
    'tipo_movimiento'  => 'Tipo',
    'cantidad'         => 'Cant.',
    'nombre_medida'    => 'Medida',
    'numero_lote'      => 'Lote',
    'fecha_caducidad'  => 'Caducidad',
    'nup'              => 'NUP/Serial',
    'usuario_nombre'   => 'Usuario',
    'observaciones'    => 'Observaciones'
];
?>
<style>
    .inv-header { flex-shrink: 0; }
    .inv-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
    .inv-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .inventario-row { cursor: pointer; }
    .inventario-row:hover { background-color: rgba(0, 0, 0, .04); }
    
    .badge-entrada { background: rgba(25,135,84,.1);  color:#198754; border:1px solid rgba(25,135,84,.2); }
    .badge-salida  { background: rgba(220,53,69,.1);  color:#dc3545; border:1px solid rgba(220,53,69,.2); }
    .badge-ajuste  { background: rgba(13,110,253,.1); color:#0d6efd; border:1px solid rgba(13,110,253,.2); }

    /* Estilos para buscador predictivo en modal */
    .dropdown-predictivo { z-index: 1060; width: 100%; max-height: 200px; overflow-y: auto; }
    .predictivo-item { cursor: pointer; font-size: 0.85rem; padding: 8px 12px; border-bottom: 1px solid #f1f1f1; background: #fff; }
    .predictivo-item:hover, .predictivo-item.active { background-color: #f8f9fa; color: #0d6efd; }
    .predictivo-item .item-codigo { font-weight: bold; color: #6c757d; margin-right: 8px; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="container-fluid px-0">

    <!-- ── Cabecera ── -->
    <div class="inv-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Movimientos de Inventario</h5>
            <small class="text-muted">Historial detallado de entradas, salidas y ajustes</small>
        </div>
        <div class="d-flex gap-2">
            <?php if ($perm['crear']): ?>
                <button type="button" class="btn btn-outline-success btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportExcel">
                    <i class="bi bi-file-earmark-excel"></i> Importar
                </button>
                <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalNuevoAjuste()">
                    <i class="bi bi-plus-lg"></i> Nuevo
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Filtros Avanzados ── -->
    <div class="accordion mb-3 shadow-sm border-0" id="accordionFiltros">
        <div class="accordion-item border-0 rounded-3">
            <h2 class="accordion-header" id="headingFiltros">
                <button class="accordion-button bg-white text-dark py-2 shadow-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltros" aria-expanded="false" aria-controls="collapseFiltros">
                    <i class="bi bi-funnel me-2 text-primary"></i> <span class="fw-bold small">Filtros Avanzados</span>
                </button>
            </h2>
            <div id="collapseFiltros" class="accordion-collapse collapse" aria-labelledby="headingFiltros" data-bs-parent="#accordionFiltros">
                <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                    <form id="formFiltros" class="row g-3">
                        <!-- Fila 1: Tipo y Producto -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Tipo de Movimiento</label>
                            <select name="tipo_mov" class="form-select form-select-sm shadow-none border">
                                <option value="">Todos los tipos</option>
                                <option value="entrada" <?= ($filtros['tipo_movimiento'] ?? '') === 'entrada' ? 'selected' : '' ?>>Entradas (+)</option>
                                <option value="salida" <?= ($filtros['tipo_movimiento'] ?? '') === 'salida' ? 'selected' : '' ?>>Salidas (-)</option>
                            </select>
                        </div>
                        <div class="col-md-5 position-relative">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Producto (Buscador)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" id="filtro_busqueda_prod" class="form-control form-control-sm shadow-none border-start-0" placeholder="Escribe código o nombre..." autocomplete="off">
                                <input type="hidden" name="id_producto" id="filtro_id_producto" value="<?= htmlspecialchars($filtros['id_producto'] ?? '') ?>">
                            </div>
                            <div id="filtro_resultados_prod" class="dropdown-predictivo border shadow-sm p-0 d-none"></div>
                        </div>

                        <!-- Fila 2: Fechas y Bodega -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Desde</label>
                            <input type="date" name="desde" class="form-control form-control-sm shadow-none border" value="<?= htmlspecialchars($filtros['desde'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Hasta</label>
                            <input type="date" name="hasta" class="form-control form-control-sm shadow-none border" value="<?= htmlspecialchars($filtros['hasta'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Bodega</label>
                            <select name="id_bodega" class="form-select form-select-sm shadow-none border">
                                <option value="">Todas las bodegas</option>
                                <?php foreach($bodegas as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= (int)($filtros['id_bodega'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= $b['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fila 3: Usuario y Medida -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Usuario Responsable</label>
                            <select name="id_usuario" class="form-select form-select-sm shadow-none border">
                                <option value="">Todos los usuarios</option>
                                <?php foreach($usuarios as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= (int)($filtros['id_usuario'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= $u['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Unidad de Medida</label>
                            <select name="id_medida" id="filtro_id_medida" class="form-select form-select-sm shadow-none border">
                                <option value="">Todas las medidas</option>
                                <?php foreach($medidas as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= (int)($filtros['id_medida'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nombre']) ?> (<?= htmlspecialchars($m['abreviatura']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fila 4: Lote, NUP, Referencia -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Número de Lote</label>
                            <input type="text" name="numero_lote" class="form-control form-control-sm shadow-none border" placeholder="Buscar lote..." value="<?= htmlspecialchars($filtros['numero_lote'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">NUP / Serial</label>
                            <input type="text" name="nup" class="form-control form-control-sm shadow-none border" placeholder="Buscar serial..." value="<?= htmlspecialchars($filtros['nup'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Origen / Referencia</label>
                            <select name="referencia_tipo" class="form-select form-select-sm shadow-none border">
                                <option value="">Todos los orígenes</option>
                                <?php foreach($tipos_ref as $tr): ?>
                                    <option value="<?= $tr ?>" <?= ($filtros['referencia_tipo'] ?? '') === $tr ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $tr)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 d-flex justify-content-between align-items-center border-top pt-3">
                            <!-- Saldo Informativo -->
                            <div id="cardSaldo" class="bg-white border rounded-3 px-3 py-2 d-flex align-items-center animate__animated animate__fadeIn" style="min-width: 200px;">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                    <i class="bi bi-calculator text-primary fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem; letter-spacing: 0.5px;">Saldo Filtrado</div>
                                    <div id="saldoValor" class="h5 fw-bold mb-0 text-primary">0.00</div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm px-3" onclick="limpiarFiltros()">
                                    <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm shadow-sm px-4">
                                    <i class="bi bi-search"></i> Aplicar Filtros
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjeta Principal ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <!-- Buscador y Exportación -->
                <div class="d-flex align-items-center gap-2">
                    <form id="formBusqueda" class="d-flex align-items-center m-0">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="buscarInventario" class="form-control border-start-0 ps-0 shadow-none border" 
                                   placeholder="Buscar producto, código..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                        </div>
                    </form>

                    <div class="btn-group btn-group-sm">
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                        
<?php $qStr = http_build_query($filtros); ?>
                        <a id="btnExportPdf" href="<?= $urlBase ?>/exportPdf?<?= $qStr ?>"
                            class="btn btn-outline-danger" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                        <a id="btnExportExcel" href="<?= $urlBase ?>/exportExcel?<?= $qStr ?>"
                            class="btn btn-outline-success" title="Descargar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </a>
                    </div>
                </div>

                <!-- Paginación Corta -->
                <div class="d-flex align-items-center gap-3">
                    <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
                    <div id="paginationContainer" class="btn-group btn-group-sm shadow-sm">
                        <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="inv-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 sortable-header" data-sort="fecha_movimiento" data-col="fecha_movimiento">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="producto_nombre" data-col="producto_nombre">Producto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="bodega_nombre" data-col="bodega_nombre">Bodega <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center sortable-header" data-sort="tipo_movimiento" data-col="tipo_movimiento">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end sortable-header" data-sort="cantidad" data-col="cantidad">Cant. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="nombre_medida" data-col="nombre_medida">Medida <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="numero_lote" data-col="numero_lote">Lote <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="fecha_caducidad" data-col="fecha_caducidad">Caducidad <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="nup" data-col="nup">NUP/Serial <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="usuario_nombre" data-col="usuario_nombre">Usuario <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="observaciones" data-col="observaciones">Obs. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyInventario">
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="bi bi-info-circle me-1"></i> No se encontraron movimientos con los filtros actuales
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr class="inventario-row" onclick="editarMovimiento(<?= $row['id'] ?>)">
                                    <td class="ps-3 small text-nowrap" data-col="fecha_movimiento"><?= date('d-m-Y H:i:s', strtotime($row['fecha_movimiento'])) ?></td>
                                    <td data-col="producto_nombre">
                                        <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($row['producto_nombre']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['producto_codigo']) ?></small>
                                    </td>
                                    <td class="small" data-col="bodega_nombre"><?= htmlspecialchars($row['bodega_nombre']) ?></td>
                                    <td class="text-center" data-col="tipo_movimiento">
                                        <?php 
                                            $badgeClass = ($row['tipo_movimiento'] === 'entrada') ? 'badge-entrada' : 'badge-salida'; 
                                            $label = ($row['tipo_movimiento'] === 'entrada') ? 'ENTRADA' : 'SALIDA';
                                        ?>
                                        <span class="badge <?= $badgeClass ?> rounded-pill px-2" style="font-size:0.7rem;"><?= $label ?></span>
                                    </td>
                                    <td class="text-end fw-bold" data-col="cantidad">
                                        <span class="<?= $row['tipo_movimiento'] === 'entrada' ? 'text-success' : 'text-danger' ?>">
                                            <?= $row['tipo_movimiento'] === 'entrada' ? '+' : '-' ?><?= number_format(abs((float)$row['cantidad']), 2) ?>
                                        </span>
                                    </td>
                                    <td class="small" data-col="nombre_medida">
                                        <?= htmlspecialchars($row['nombre_medida'] ?? '-') ?>
                                        <?php if (!empty($row['abreviatura_medida'])): ?>
                                            <small class="text-muted">(<?= htmlspecialchars($row['abreviatura_medida']) ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small" data-col="numero_lote"><?= htmlspecialchars($row['numero_lote'] ?? '-') ?></td>
                                    <td class="small" data-col="fecha_caducidad"><?= $row['fecha_caducidad'] ? date('d-m-Y', strtotime($row['fecha_caducidad'])) : '-' ?></td>
                                    <td class="small" data-col="nup"><?= htmlspecialchars($row['nup'] ?? '-') ?></td>
                                    <td class="small" data-col="usuario_nombre"><?= htmlspecialchars($row['usuario_nombre'] ?? '-') ?></td>
                                    <td class="small text-truncate" style="max-width: 150px;" data-col="observaciones" title="<?= htmlspecialchars($row['observaciones'] ?? '') ?>">
                                        <?= htmlspecialchars($row['observaciones'] ?? '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Modales ── -->
<?php include __DIR__ . '/modal.php'; ?>

<!-- ── Scripts ── -->
<script>
    (function() {
        const urlBase = '<?= $urlBase ?>';
        let page = <?= $page ?>;
        let totalPages = <?= $totalPages ?>;
        let ordenCol = '<?= $ordenCol ?>';
        let ordenDir = '<?= $ordenDir ?>';

        const formFiltros = document.getElementById('formFiltros');
        const filtroBusquedaProd = document.getElementById('filtro_busqueda_prod');
        const filtroIdProd = document.getElementById('filtro_id_producto');
        const filtroResDiv = document.getElementById('filtro_resultados_prod');
        const filtroMedida = document.getElementById('filtro_id_medida');
        const formBusqueda = document.getElementById('formBusqueda');
        const inputBuscar = document.getElementById('buscarInventario');
        
        let timerFiltroProd;

        // Búsqueda de productos en filtros
        filtroBusquedaProd.addEventListener('input', () => {
            const q = filtroBusquedaProd.value.trim();
            filtroResDiv.classList.add('d-none');
            filtroIdProd.value = '';
            
            if (q.length < 2) return;

            clearTimeout(timerFiltroProd);
            timerFiltroProd = setTimeout(async () => {
                try {
                    const resp = await fetch('<?= BASE_URL ?>/modulos/productos/searchAjaxSimple?q=' + encodeURIComponent(q));
                    const json = await resp.json();
                    if (json.ok && json.data.length > 0) {
                        filtroResDiv.innerHTML = json.data.map(item => `
                            <div class="predictivo-item" onclick="seleccionarProductoFiltro(${item.id}, '${item.codigo.replace(/'/g, "\\'")}', '${item.nombre.replace(/'/g, "\\'")}')">
                                <span class="item-codigo">[${item.codigo}]</span>
                                <span class="item-nombre">${item.nombre}</span>
                            </div>
                        `).join('');
                        filtroResDiv.classList.remove('d-none');
                    }
                } catch (e) { console.error(e); }
            }, 400);
        });

        window.seleccionarProductoFiltro = (id, cod, nom) => {
            filtroIdProd.value = id;
            filtroBusquedaProd.value = `[${cod}] ${nom}`;
            filtroResDiv.classList.add('d-none');
            cargarMedidasFiltro(id);
        };

        async function cargarMedidasFiltro(idProd) {
            if (!idProd) {
                filtroMedida.innerHTML = '<option value="">Todas las medidas</option>';
                return;
            }
            try {
                const resp = await fetch(`<?= BASE_URL ?>/modulos/inventario/getMedidasProductoAjax?id_producto=${idProd}`);
                const json = await resp.json();
                if (json.ok) {
                    let html = '<option value="">Todas las medidas</option>';
                    json.medidas.forEach(m => {
                        html += `<option value="${m.id}">${m.nombre} (${m.abreviatura})</option>`;
                    });
                    filtroMedida.innerHTML = html;
                }
            } catch (e) { console.error(e); }
        }

        window.limpiarFiltros = function() {
            formFiltros.reset();
            filtroIdProd.value = '';
            filtroBusquedaProd.value = '';
            filtroMedida.innerHTML = '<option value="">Todas las medidas</option>';
            inputBuscar.value = '';
            page = 1;
            cargarListado();
        };

        document.addEventListener('click', (e) => {
            if (!filtroResDiv.contains(e.target) && e.target !== filtroBusquedaProd) {
                filtroResDiv.classList.add('d-none');
            }
        });

        // Búsqueda general
        formBusqueda.addEventListener('submit', (e) => {
            e.preventDefault();
            page = 1;
            cargarListado();
        });

        inputBuscar.addEventListener('input', debounce(() => {
            page = 1;
            cargarListado();
        }, 500));

        // Filtros
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            page = 1;
            cargarListado();
        });


        // Ordenamiento
        document.querySelectorAll('.sortable-header').forEach(th => {
            th.addEventListener('click', () => {
                const newSort = th.dataset.sort;
                if (!newSort) return;

                if (ordenCol === newSort) {
                    ordenDir = (ordenDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                } else {
                    ordenCol = newSort;
                    ordenDir = 'ASC';
                }
                
                // Guardar preferencia de columna
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('modulos/inventario', ordenCol, ordenDir);
                }

                page = 1;
                cargarListado();
            });
        });

        window.editarMovimiento = function(id) {
            if (typeof window.abrirModalAjuste === 'function') {
                window.abrirModalAjuste(id);
            }
        };

        window.eliminarMovimiento = async function(id) {
            if (!confirm('¿Está seguro de eliminar este movimiento? El stock será revertido automáticamente.')) return;

            try {
                const resp = await fetch(`${urlBase}/eliminarAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
                const json = await resp.json();
                if (json.ok) {
                    cargarListado();
                } else {
                    alert(json.mensaje);
                }
            } catch (e) {
                console.error('Error al eliminar:', e);
            }
        };

        window.cambiarPaginaAjax = function(p) {
            if (p < 1 || p > totalPages) return;
            page = p;
            cargarListado();
        };

        async function cargarListado() {
            const formData = new FormData(formFiltros);
            const params = new URLSearchParams(formData);
            params.append('b', inputBuscar.value.trim());
            params.append('page', page);
            params.append('sort', ordenCol);
            params.append('dir', ordenDir);

            try {
                const resp = await fetch(`${urlBase}/searchAjax?${params.toString()}`);
                const json = await resp.json();
                if (json.ok) {
                    document.getElementById('tbodyInventario').innerHTML = json.rows;
                    document.getElementById('paginationInfo').textContent = json.info;
                    document.getElementById('paginationContainer').innerHTML = json.pagination;
                    
                    if (json.saldo !== undefined) {
                        const saldoEl = document.getElementById('saldoValor');
                        if (saldoEl) saldoEl.textContent = json.saldo;
                    }
                    
                    if (json.pdf_url) document.getElementById('btnExportPdf').href = json.pdf_url;
                    if (json.excel_url) document.getElementById('btnExportExcel').href = json.excel_url;
                    
                    totalPages = json.totalPages || 1;
                    updateSortIcons();
                }
            } catch (e) {
                console.error('Error cargando listado:', e);
            }
        }

        function updateSortIcons() {
            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                if (!icon) return;
                const col = th.dataset.sort;
                icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                if (col === ordenCol) {
                    icon.className = (ordenDir.toLowerCase() === 'asc') 
                        ? 'bi bi-sort-alpha-down small text-primary ms-1' 
                        : 'bi bi-sort-alpha-up small text-primary ms-1';
                }
            });
        }

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        // ── Auto-open si viene de Productos ──
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoOpen') === '1') {
            setTimeout(() => {
                if (typeof abrirModalNuevoAjuste === 'function') abrirModalNuevoAjuste();
            }, 600);
        }

        updateSortIcons();
    })();
</script>