<div class="modal fade" id="modalActivoFijo" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-building text-primary me-2"></i><span id="afModalTitulo">Nuevo Activo Fijo</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Pestañas -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="tabsModalAF" role="tablist">
                        <li class="nav-item"><a class="nav-link active py-2 small fw-bold" data-bs-toggle="tab" href="#af-tab-general" role="tab"><i class="bi bi-card-text me-1"></i> General</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small fw-bold" data-bs-toggle="tab" href="#af-tab-depreciacion" role="tab"><i class="bi bi-graph-down-arrow me-1"></i> Depreciación</a></li>
                    </ul>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <form id="formActivoFijo">
                    <input type="hidden" name="id" id="af-id" value="">
                    <input type="hidden" name="origen" id="af-origen" value="manual">
                    <input type="hidden" name="id_compra" id="af-id-compra" value="">
                    <input type="hidden" name="id_compra_detalle" id="af-id-compra-detalle" value="">
                    <input type="hidden" name="id_proveedor" id="af-id-proveedor" value="">

                    <div class="tab-content">
                        <!-- PESTAÑA GENERAL -->
                        <div class="tab-pane fade show active p-3" id="af-tab-general" role="tabpanel">

                            <div id="af-origen-toggle" class="btn-group btn-group-sm mb-3" role="group">
                                <button type="button" class="btn btn-outline-primary active" id="af-btn-origen-manual" onclick="window.AF_setOrigen('manual')">
                                    <i class="bi bi-pencil me-1"></i> Manual
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="af-btn-origen-compra" onclick="window.AF_setOrigen('compra')">
                                    <i class="bi bi-receipt me-1"></i> Desde factura de compra
                                </button>
                            </div>

                            <!-- Panel: Desde factura de compra -->
                            <div id="af-panel-compra" class="d-none">
                                <div class="row g-2 align-items-end mb-2">
                                    <div class="col-md-8 position-relative">
                                        <label class="form-label small fw-bold">Buscar factura de compra</label>
                                        <input type="text" id="af-compra-buscar" class="form-control form-control-sm" placeholder="Número, proveedor o RUC..." autocomplete="off">
                                        <div class="list-group af-dropdown" id="af-compra-dropdown"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div id="af-compra-info" class="small text-muted">&nbsp;</div>
                                    </div>
                                </div>
                                <div id="af-compra-lineas-cont" class="d-none">
                                    <label class="form-label small fw-bold">Seleccione la línea a registrar como activo fijo</label>
                                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light"><tr><th></th><th>Descripción</th><th class="text-end">Cantidad</th><th class="text-end">Valor</th></tr></thead>
                                            <tbody id="af-compra-lineas-body"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Panel: Manual -->
                            <div id="af-panel-manual">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label small fw-bold">Nombre / Descripción del Activo</label>
                                        <input type="text" name="nombre" id="af-nombre" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Código (opcional)</label>
                                        <input type="text" name="codigo" id="af-codigo" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Valor de Adquisición</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0.01" name="valor_adquisicion" id="af-valor-adquisicion" class="form-control text-end" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Fecha de Adquisición</label>
                                        <input type="date" name="fecha_adquisicion" id="af-fecha-adquisicion" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Proveedor (texto libre, opcional)</label>
                                        <input type="text" name="proveedor_texto" id="af-proveedor-texto" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Categoría</label>
                                    <select name="id_categoria" id="af-categoria" class="form-select form-select-sm" required></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Valor Residual</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" name="valor_residual" id="af-valor-residual" class="form-control text-end" value="0">
                                    </div>
                                </div>
                                <div class="col-md-4" id="af-contrapartida-cont">
                                    <label class="form-label small fw-bold">Cuenta Contrapartida (opcional)</label>
                                    <div class="position-relative">
                                        <input type="text" id="af-contrapartida-txt" class="form-control form-control-sm" placeholder="Usa la regla general si no se elige" autocomplete="off">
                                        <input type="hidden" name="id_cuenta_contrapartida_alta" id="af-contrapartida-id">
                                        <div class="list-group af-dropdown" id="af-contrapartida-dropdown"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Observaciones</label>
                                    <input type="text" name="observaciones" id="af-observaciones" class="form-control form-control-sm">
                                </div>
                            </div>

                            <div id="af-resumen" class="d-none mt-3 p-2 border rounded-3 bg-white shadow-sm">
                                <div class="row text-center g-2 small">
                                    <div class="col"><div class="text-muted">Depreciación Acumulada</div><div class="fw-bold" id="af-resumen-acumulada">$0.00</div></div>
                                    <div class="col"><div class="text-muted">Valor en Libros</div><div class="fw-bold" id="af-resumen-libros">$0.00</div></div>
                                    <div class="col"><div class="text-muted">Meses de Vida Útil</div><div class="fw-bold" id="af-resumen-meses">-</div></div>
                                    <div class="col"><div class="text-muted">Estado</div><div class="fw-bold" id="af-resumen-estado">-</div></div>
                                </div>
                            </div>
                        </div>

                        <!-- PESTAÑA DEPRECIACIÓN -->
                        <div class="tab-pane fade p-3" id="af-tab-depreciacion" role="tabpanel">
                            <div id="af-depreciacion-contenido">
                                <p class="text-muted small mb-0">Guarde el activo para poder generar su depreciación mensual.</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" id="af-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.AF_eliminar()">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" id="af-btn-guardar" class="btn btn-primary btn-sm px-4" onclick="window.AF_guardar()">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
