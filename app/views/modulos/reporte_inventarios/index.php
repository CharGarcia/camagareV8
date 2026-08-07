<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .ri-header { flex-shrink: 0; }
    .ri-ex-scroll, .ri-mv-scroll, .ri-va-scroll, .ri-cv-scroll, .ri-au-scroll { max-height: 500px; overflow-y: auto; }
    .ri-ex-scroll thead th, .ri-mv-scroll thead th, .ri-va-scroll thead th, .ri-cv-scroll thead th, .ri-au-scroll thead th {
        position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6;
    }
    /* Texto largo: se envuelve dentro de la celda y la fila crece, en vez de
       recortarse con ellipsis (a diferencia del estándar de listados con
       cmg-table-card, que sí usa ellipsis de una línea). */
    .ri-ex-scroll table td, .ri-mv-scroll table td, .ri-va-scroll table td, .ri-cv-scroll table td, .ri-au-scroll table td {
        white-space: normal !important;
        word-break: break-word;
    }
    /* El nombre del producto/cliente seleccionado en los buscadores no debe
       envolver a varias líneas: eso estira la columna y descuadra el resto de
       la fila de filtros (p. ej. el botón "Generar" queda desalineado). */
    #riTabsContent small[id$="-seleccionado"] {
        display: block; overflow: hidden; text-overflow: ellipsis;
        white-space: nowrap; max-width: 100%;
    }
    .ri-kpi-icon { width: 48px; height: 48px; }
    .ri-nav-tabs .nav-link { font-weight: 600; font-size: .85rem; }
    .ri-nav-tabs .nav-link.active { border-bottom: 3px solid var(--bs-primary); }
</style>

<div class="container-fluid py-2 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">
    <!-- ── Cabecera ── -->
    <div class="ri-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-boxes me-2 text-primary"></i>Reporte de Inventarios</h5>
            <small class="text-muted">Existencias, movimientos, valorización, consignaciones y auditoría</small>
        </div>
    </div>

    <!-- ── Pestañas ── -->
    <ul class="nav nav-tabs ri-nav-tabs mb-3" id="riTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ri-tab-existencias-btn" data-bs-toggle="tab" data-bs-target="#ri-tab-existencias" type="button" role="tab">
                <i class="bi bi-box-seam me-1"></i>Existencias
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ri-tab-movimientos-btn" data-bs-toggle="tab" data-bs-target="#ri-tab-movimientos" type="button" role="tab">
                <i class="bi bi-arrow-left-right me-1"></i>Movimientos (Kardex)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ri-tab-valorizacion-btn" data-bs-toggle="tab" data-bs-target="#ri-tab-valorizacion" type="button" role="tab">
                <i class="bi bi-cash-coin me-1"></i>Valorización
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ri-tab-consignaciones-btn" data-bs-toggle="tab" data-bs-target="#ri-tab-consignaciones" type="button" role="tab">
                <i class="bi bi-truck me-1"></i>Consignaciones
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ri-tab-auditoria-btn" data-bs-toggle="tab" data-bs-target="#ri-tab-auditoria" type="button" role="tab">
                <i class="bi bi-shield-check me-1"></i>Auditoría
            </button>
        </li>
    </ul>

    <div class="tab-content" id="riTabsContent">

        <!-- ════════════════════════════════════════════════════════ -->
        <!-- PESTAÑA 1: EXISTENCIAS -->
        <!-- ════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="ri-tab-existencias" role="tabpanel">
            <div class="accordion mb-3 shadow-sm border-0" id="ri-ex-accordion">
                <div class="accordion-item border-0 rounded-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#ri-ex-collapse">
                            <i class="bi bi-funnel me-2 text-primary"></i><span class="fw-bold small">Filtros — Existencias</span>
                        </button>
                    </h2>
                    <div id="ri-ex-collapse" class="accordion-collapse collapse show">
                        <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                            <form id="ri-ex-form" onsubmit="event.preventDefault(); window.RI_Existencias.generar();" class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Bodega</label>
                                    <select id="ri-ex-bodega" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($bodegas ?? []) as $b): ?>
                                            <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Categoría</label>
                                    <select id="ri-ex-categoria" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($categorias ?? []) as $c): ?>
                                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Marca</label>
                                    <select id="ri-ex-marca" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($marcas ?? []) as $m): ?>
                                            <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                                    <select id="ri-ex-estado" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todos</option>
                                        <option value="QUIEBRE">En quiebre</option>
                                        <option value="ALERTA">Bajo mínimo</option>
                                        <option value="NORMAL">Normal</option>
                                        <option value="EXCESO">Sobre máximo</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Agrupar por</label>
                                    <select id="ri-ex-agrupar" class="form-select form-select-sm shadow-none border">
                                        <option value="NINGUNO">Detallado</option>
                                        <option value="PRODUCTO">Por Producto</option>
                                        <option value="CATEGORIA">Por Categoría</option>
                                        <option value="BODEGA">Por Bodega</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm shadow-sm w-100"><i class="bi bi-search me-1"></i>Mostrar</button>
                                </div>

                                <div class="col-md-4 position-relative">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Producto</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-start-0 px-1 shadow-none" id="ri-ex-search-producto" placeholder="Buscar producto..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary" title="Limpiar" onclick="window.RI_Existencias.limpiarProducto();"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                    <input type="hidden" id="ri-ex-id-producto">
                                    <div id="ri-ex-dropdown-producto" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:calc(100% - 1.5rem);max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                                    <small class="text-muted fst-italic" id="ri-ex-producto-seleccionado"></small>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Lote</label>
                                    <input type="text" id="ri-ex-lote" class="form-control form-control-sm shadow-none border" placeholder="Nro. lote">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">NUP</label>
                                    <input type="text" id="ri-ex-nup" class="form-control form-control-sm shadow-none border" placeholder="NUP">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Caducidad desde</label>
                                    <input type="date" id="ri-ex-caducidad-desde" class="form-control form-control-sm shadow-none border">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Caducidad hasta</label>
                                    <input type="date" id="ri-ex-caducidad-hasta" class="form-control form-control-sm shadow-none border">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RI_Existencias.exportarPDF()"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RI_Existencias.exportarExcel()"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="ri-ex-scroll w-100">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light" id="ri-ex-thead"></thead>
                            <tbody id="ri-ex-tbody">
                                <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y genera el reporte.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════ -->
        <!-- PESTAÑA 2: MOVIMIENTOS (KARDEX) -->
        <!-- ════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="ri-tab-movimientos" role="tabpanel">
            <div class="accordion mb-3 shadow-sm border-0" id="ri-mv-accordion">
                <div class="accordion-item border-0 rounded-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#ri-mv-collapse">
                            <i class="bi bi-funnel me-2 text-primary"></i><span class="fw-bold small">Filtros — Movimientos</span>
                        </button>
                    </h2>
                    <div id="ri-mv-collapse" class="accordion-collapse collapse show">
                        <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                            <form id="ri-mv-form" onsubmit="event.preventDefault(); window.RI_Movimientos.generar();" class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                                    <select id="ri-mv-mes" class="form-select form-select-sm shadow-none border" onchange="window.RI_Movimientos.cambiarMesAnio();">
                                        <option value="TODOS">Todos</option>
                                        <option value="01">Enero</option><option value="02">Febrero</option><option value="03">Marzo</option>
                                        <option value="04">Abril</option><option value="05">Mayo</option><option value="06">Junio</option>
                                        <option value="07">Julio</option><option value="08">Agosto</option><option value="09">Septiembre</option>
                                        <option value="10">Octubre</option><option value="11">Noviembre</option><option value="12">Diciembre</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                                    <select id="ri-mv-anio" class="form-select form-select-sm shadow-none border" onchange="window.RI_Movimientos.cambiarMesAnio();">
                                        <option value="TODOS">Todos</option>
                                        <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                            <option value="<?= htmlspecialchars((string) $a) ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= htmlspecialchars((string) $a) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Desde</label>
                                    <input type="date" id="ri-mv-fecha-desde" class="form-control form-control-sm shadow-none border" value="<?= date('Y-m-01') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Hasta</label>
                                    <input type="date" id="ri-mv-fecha-hasta" class="form-control form-control-sm shadow-none border" value="<?= date('Y-m-t') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Bodega</label>
                                    <select id="ri-mv-bodega" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($bodegas ?? []) as $b): ?><option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Tipo de movimiento</label>
                                    <select id="ri-mv-tipo" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todos</option>
                                        <option value="entrada">Entrada</option><option value="salida">Salida</option>
                                        <option value="ajuste">Ajuste</option><option value="transferencia">Transferencia</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Categoría</label>
                                    <select id="ri-mv-categoria" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($categorias ?? []) as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Marca</label>
                                    <select id="ri-mv-marca" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($marcas ?? []) as $m): ?><option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Origen</label>
                                    <select id="ri-mv-origen" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todos</option>
                                        <option value="ajuste_manual">Ajuste manual</option>
                                        <?php foreach (($origenes ?? []) as $o): ?>
                                            <?php if ((string) $o === 'ajuste_manual') continue; ?>
                                            <option value="<?= htmlspecialchars((string) $o) ?>"><?= htmlspecialchars(\App\repositories\modulos\ReporteInventarioRepository::labelOrigen((string) $o)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Usuario</label>
                                    <select id="ri-mv-usuario" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todos</option>
                                        <?php foreach (($usuarios ?? []) as $u): ?><option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Lote</label>
                                    <input type="text" id="ri-mv-lote" class="form-control form-control-sm shadow-none border" placeholder="Nro. lote">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">NUP</label>
                                    <input type="text" id="ri-mv-nup" class="form-control form-control-sm shadow-none border" placeholder="NUP">
                                </div>

                                <div class="col-md-6 position-relative">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Producto</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-start-0 px-1 shadow-none" id="ri-mv-search-producto" placeholder="Buscar producto..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary" title="Limpiar" onclick="window.RI_Movimientos.limpiarProducto();"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                    <input type="hidden" id="ri-mv-id-producto">
                                    <div id="ri-mv-dropdown-producto" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:calc(100% - 1.5rem);max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                                    <small class="text-muted fst-italic" id="ri-mv-producto-seleccionado"></small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Agrupar por</label>
                                    <select id="ri-mv-agrupar" class="form-select form-select-sm shadow-none border">
                                        <option value="NINGUNO">Detallado</option>
                                        <option value="PRODUCTO">Por Producto</option>
                                        <option value="BODEGA">Por Bodega</option>
                                        <option value="TIPO">Por Tipo</option>
                                        <option value="ORIGEN">Por Origen</option>
                                        <option value="FECHA">Por Fecha</option>
                                        <option value="MES">Por Mes</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm shadow-sm w-100"><i class="bi bi-search me-1"></i>Mostrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RI_Movimientos.exportarPDF()"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RI_Movimientos.exportarExcel()"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="ri-mv-scroll w-100">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light" id="ri-mv-thead"></thead>
                            <tbody id="ri-mv-tbody">
                                <tr><td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y genera el reporte.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════ -->
        <!-- PESTAÑA 3: VALORIZACIÓN -->
        <!-- ════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="ri-tab-valorizacion" role="tabpanel">
            <div class="accordion mb-3 shadow-sm border-0" id="ri-va-accordion">
                <div class="accordion-item border-0 rounded-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#ri-va-collapse">
                            <i class="bi bi-funnel me-2 text-primary"></i><span class="fw-bold small">Filtros — Valorización</span>
                        </button>
                    </h2>
                    <div id="ri-va-collapse" class="accordion-collapse collapse show">
                        <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                            <form id="ri-va-form" onsubmit="event.preventDefault(); window.RI_Valorizacion.generar();" class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Bodega</label>
                                    <select id="ri-va-bodega" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($bodegas ?? []) as $b): ?><option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Categoría</label>
                                    <select id="ri-va-categoria" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($categorias ?? []) as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Marca</label>
                                    <select id="ri-va-marca" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($marcas ?? []) as $m): ?><option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Agrupar por</label>
                                    <select id="ri-va-agrupar" class="form-select form-select-sm shadow-none border">
                                        <option value="PRODUCTO">Por Producto</option>
                                        <option value="CATEGORIA">Por Categoría</option>
                                        <option value="BODEGA">Por Bodega</option>
                                        <option value="MARCA">Por Marca</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm shadow-sm w-100"><i class="bi bi-search me-1"></i>Mostrar</button>
                                </div>
                                <div class="col-md-4 position-relative">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Producto</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-start-0 px-1 shadow-none" id="ri-va-search-producto" placeholder="Buscar producto..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary" title="Limpiar" onclick="window.RI_Valorizacion.limpiarProducto();"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                    <input type="hidden" id="ri-va-id-producto">
                                    <div id="ri-va-dropdown-producto" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:calc(100% - 1.5rem);max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                                    <small class="text-muted fst-italic" id="ri-va-producto-seleccionado"></small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RI_Valorizacion.exportarPDF()"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RI_Valorizacion.exportarExcel()"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="ri-va-scroll w-100">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr><th>Grupo</th><th class="text-center">Productos</th><th class="text-end">Stock</th><th class="text-end">Costo promedio</th><th class="text-end">Valor total</th></tr>
                            </thead>
                            <tbody id="ri-va-tbody">
                                <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y genera el reporte.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════ -->
        <!-- PESTAÑA 4: CONSIGNACIONES -->
        <!-- ════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="ri-tab-consignaciones" role="tabpanel">
            <div class="accordion mb-3 shadow-sm border-0" id="ri-cv-accordion">
                <div class="accordion-item border-0 rounded-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#ri-cv-collapse">
                            <i class="bi bi-funnel me-2 text-primary"></i><span class="fw-bold small">Filtros — Consignaciones</span>
                        </button>
                    </h2>
                    <div id="ri-cv-collapse" class="accordion-collapse collapse show">
                        <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                            <form id="ri-cv-form" onsubmit="event.preventDefault(); window.RI_Consignaciones.generar();" class="row g-3">
                                <div class="col-md-3 position-relative">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Cliente</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-start-0 px-1 shadow-none" id="ri-cv-search-cliente" placeholder="Buscar cliente..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary" title="Limpiar" onclick="window.RI_Consignaciones.limpiarCliente();"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                    <input type="hidden" id="ri-cv-id-cliente">
                                    <div id="ri-cv-dropdown-cliente" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:calc(100% - 1.5rem);max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                                    <small class="text-muted fst-italic" id="ri-cv-cliente-seleccionado"></small>
                                </div>
                                <div class="col-md-3 position-relative">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Producto</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-start-0 px-1 shadow-none" id="ri-cv-search-producto" placeholder="Buscar producto..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary" title="Limpiar" onclick="window.RI_Consignaciones.limpiarProducto();"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                    <input type="hidden" id="ri-cv-id-producto">
                                    <div id="ri-cv-dropdown-producto" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:calc(100% - 1.5rem);max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                                    <small class="text-muted fst-italic" id="ri-cv-producto-seleccionado"></small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Bodega</label>
                                    <select id="ri-cv-bodega" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($bodegas ?? []) as $b): ?><option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Vendedor</label>
                                    <select id="ri-cv-vendedor" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todos</option>
                                        <?php foreach (($vendedores ?? []) as $v): ?><option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Desde</label>
                                    <input type="date" id="ri-cv-fecha-desde" class="form-control form-control-sm shadow-none border">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Hasta</label>
                                    <input type="date" id="ri-cv-fecha-hasta" class="form-control form-control-sm shadow-none border">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Caducidad desde</label>
                                    <input type="date" id="ri-cv-caducidad-desde" class="form-control form-control-sm shadow-none border">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Caducidad hasta</label>
                                    <input type="date" id="ri-cv-caducidad-hasta" class="form-control form-control-sm shadow-none border">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Lote</label>
                                    <input type="text" id="ri-cv-lote" class="form-control form-control-sm shadow-none border" placeholder="Nro. lote">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">NUP</label>
                                    <input type="text" id="ri-cv-nup" class="form-control form-control-sm shadow-none border" placeholder="NUP">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">N° Consignación</label>
                                    <input type="text" id="ri-cv-secuencial" class="form-control form-control-sm shadow-none border" placeholder="Ej. 000000010">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                                    <select id="ri-cv-estado" class="form-select form-select-sm shadow-none border">
                                        <option value="TODOS" selected>Todos</option>
                                        <option value="Entregada">Entregada</option>
                                        <option value="Emitida">Emitida</option>
                                        <option value="Anulada">Anulada</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Agrupar por</label>
                                    <select id="ri-cv-agrupar" class="form-select form-select-sm shadow-none border">
                                        <option value="NINGUNO">Detallado</option>
                                        <option value="CLIENTE">Por Cliente</option>
                                        <option value="PRODUCTO">Por Producto</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ri-cv-incluir-liquidadas">
                                        <label class="form-check-label small" for="ri-cv-incluir-liquidadas">Incluir liquidadas</label>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm shadow-sm w-100"><i class="bi bi-search me-1"></i>Mostrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RI_Consignaciones.exportarPDF()"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RI_Consignaciones.exportarExcel()"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="ri-cv-scroll w-100">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light" id="ri-cv-thead"></thead>
                            <tbody id="ri-cv-tbody">
                                <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y genera el reporte.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════ -->
        <!-- PESTAÑA 5: AUDITORÍA -->
        <!-- ════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="ri-tab-auditoria" role="tabpanel">
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Compara el stock cacheado (<code>productos_bodegas.stock_actual</code>) contra el saldo real calculado
                en vivo desde el Kardex. Solo se muestran los que difieren. Antes de "Corregir" confirma que el Kardex
                está completo para ese producto/bodega (idealmente con un conteo físico).
            </div>
            <div class="accordion mb-3 shadow-sm border-0" id="ri-au-accordion">
                <div class="accordion-item border-0 rounded-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#ri-au-collapse">
                            <i class="bi bi-funnel me-2 text-primary"></i><span class="fw-bold small">Filtros — Auditoría</span>
                        </button>
                    </h2>
                    <div id="ri-au-collapse" class="accordion-collapse collapse show">
                        <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                            <form id="ri-au-form" onsubmit="event.preventDefault(); window.RI_Auditoria.generar();" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Bodega</label>
                                    <select id="ri-au-bodega" class="form-select form-select-sm shadow-none border">
                                        <option value="">Todas</option>
                                        <?php foreach (($bodegas ?? []) as $b): ?><option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 position-relative">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Producto</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-start-0 px-1 shadow-none" id="ri-au-search-producto" placeholder="Buscar producto..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary" title="Limpiar" onclick="window.RI_Auditoria.limpiarProducto();"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                    <input type="hidden" id="ri-au-id-producto">
                                    <div id="ri-au-dropdown-producto" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:calc(100% - 1.5rem);max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                                    <small class="text-muted fst-italic" id="ri-au-producto-seleccionado"></small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Buscar</label>
                                    <input type="text" id="ri-au-buscar" class="form-control form-control-sm shadow-none border" placeholder="Nombre o código...">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm shadow-sm w-100"><i class="bi bi-search me-1"></i>Mostrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="text-muted small fw-medium" id="ri-au-info-total">&nbsp;</span>
                </div>
                <div class="card-body p-0">
                    <div class="ri-au-scroll w-100">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th>Bodega</th>
                                    <th class="text-end">Cacheado</th>
                                    <th class="text-end">Real (Kardex)</th>
                                    <th class="text-end">Diferencia</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="ri-au-tbody">
                                <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y genera el reporte.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL: DETALLE DE PRODUCTOS DE UNA CONSIGNACIÓN -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="ri-cv-modal-detalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold mb-0">
                    <i class="bi bi-truck text-primary me-2"></i>Consignación <span id="ri-cv-modal-secuencial"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3 small">
                    <div class="col-md-3"><span class="text-muted">Fecha:</span> <span id="ri-cv-modal-fecha" class="fw-bold"></span></div>
                    <div class="col-md-4"><span class="text-muted">Cliente:</span> <span id="ri-cv-modal-cliente" class="fw-bold"></span></div>
                    <div class="col-md-2"><span class="text-muted">Vendedor:</span> <span id="ri-cv-modal-vendedor" class="fw-bold"></span></div>
                    <div class="col-md-3"><span class="text-muted">Estado:</span> <span id="ri-cv-modal-estado" class="fw-bold"></span></div>
                </div>
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr class="text-secondary">
                                <th>Producto</th><th>Bodega</th><th>Lote</th><th>NUP</th>
                                <th class="text-end">Consignado</th><th class="text-end">Retornado</th><th class="text-end">Facturado</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody id="ri-cv-modal-tbody">
                            <tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL: EDITAR MÍNIMO/MÁXIMO/CATEGORÍA (Existencias) -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="ri-ex-modal-editar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold mb-0">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Editar existencia
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ri-ex-edit-id-producto">
                <input type="hidden" id="ri-ex-edit-id-bodega">
                <div class="mb-3 small">
                    <div><span class="text-muted">Producto:</span> <span id="ri-ex-edit-producto-nombre" class="fw-bold"></span></div>
                    <div><span class="text-muted">Bodega:</span> <span id="ri-ex-edit-bodega-nombre" class="fw-bold"></span></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Mínimo</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="ri-ex-edit-minimo">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Máximo</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="ri-ex-edit-maximo">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Categoría</label>
                        <select class="form-select form-select-sm" id="ri-ex-edit-categoria"></select>
                    </div>
                </div>

                <hr class="my-3">
                <div class="fw-bold small text-uppercase text-muted mb-2"><i class="bi bi-box-seam me-1"></i>Ajustar inventario</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Bodega</label>
                        <select class="form-select form-select-sm" id="ri-ex-adj-bodega"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Tipo</label>
                        <select class="form-select form-select-sm" id="ri-ex-adj-tipo">
                            <option value="">— Sin ajuste —</option>
                            <option value="entrada">Entrada</option>
                            <option value="salida">Salida</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Cantidad</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="ri-ex-adj-cantidad">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Costo unitario</label>
                        <input type="number" step="0.0001" min="0" class="form-control form-control-sm" id="ri-ex-adj-costo">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Lote</label>
                        <input type="text" class="form-control form-control-sm" id="ri-ex-adj-lote" placeholder="Opcional">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Observaciones</label>
                        <input type="text" class="form-control form-control-sm" id="ri-ex-adj-observaciones" placeholder="Ajuste manual">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.RI_Existencias.confirmarGuardarEdicion()"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO = "<?php echo $rutaModulo; ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/js/modulos/reporte_inventarios.js?v=<?php echo time(); ?>"></script>
