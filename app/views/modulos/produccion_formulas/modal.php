<?php
/** @var array $vistaConfig @var string $rutaModulo @var array $metodos */
$pestanasFormula = [
    'pane-pfo-insumos'  => 'Insumos',
    'pane-pfo-salidas'  => 'Otras salidas',
    'pane-pfo-costos'   => 'Mano de obra y CIF',
    'pane-pfo-instr'    => 'Instrucciones',
    'pane-pfo-estimado' => 'Costo estimado',
];
?>
<div class="modal fade" id="modalFormula" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-diagram-3 text-primary me-2"></i><span id="pfo-titulo">Nueva fórmula</span>
                    <span id="pfo-badge" class="ms-2"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Barra de acciones (fórmula guardada) -->
                <div id="pfo-acciones" class="px-3 py-2 bg-light border-bottom d-none d-flex gap-1 align-items-center flex-wrap">
                    <button type="button" id="pfo-btn-activar" class="btn btn-outline-success btn-sm d-none" onclick="window.PFO_activar()" title="Dejarla vigente para producir">
                        <i class="bi bi-check2-circle me-1"></i> Activar
                    </button>
                    <button type="button" id="pfo-btn-inactivar" class="btn btn-outline-secondary btn-sm d-none" onclick="window.PFO_inactivar()" title="Dejar de usarla en órdenes nuevas">
                        <i class="bi bi-pause-circle me-1"></i> Inactivar
                    </button>
                    <div class="vr mx-1"></div>
                    <button type="button" id="pfo-btn-version" class="btn btn-outline-primary btn-sm" onclick="window.PFO_nuevaVersion()" title="Copiar como nueva versión en borrador">
                        <i class="bi bi-files me-1"></i> Nueva versión
                    </button>
                    <span id="pfo-aviso-usada" class="small text-muted ms-2 d-none"><i class="bi bi-lock me-1"></i>Ya se usó en órdenes: para cambiarla cree una nueva versión.</span>
                </div>

                <div class="p-3">
                    <input type="hidden" id="pfo-id" value="">
                    <div id="pfo-aviso-componentes" class="alert alert-warning py-2 small d-none"></div>

                    <div class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Código *</label>
                            <input type="text" id="pfo-codigo" class="form-control form-control-sm text-uppercase" maxlength="30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Nombre *</label>
                            <input type="text" id="pfo-nombre" class="form-control form-control-sm" maxlength="200" placeholder="Ej.: Caja para teclado estándar">
                        </div>
                        <div class="col-md-6 position-relative">
                            <label class="form-label small fw-bold">Producto que se fabrica *</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-box-seam"></i></span>
                                <input type="text" id="pfo-producto-txt" class="form-control" placeholder="Buscar producto por código o nombre…" autocomplete="off">
                                <input type="hidden" id="pfo-producto" value="">
                            </div>
                            <div id="pfo-producto-dd" class="list-group position-absolute shadow pfo-dd d-none" style="width:calc(100% - 12px);"></div>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Rinde *</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="pfo-cantidad-base" class="form-control text-end" min="0" step="any" value="1">
                                <span class="input-group-text" id="pfo-medida">u</span>
                            </div>
                            <div class="form-text">Cuánto sale con estas cantidades.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Vida útil (días)</label>
                            <input type="number" id="pfo-vida-util" class="form-control form-control-sm text-end" min="0" step="1" placeholder="Sin caducidad">
                            <div class="form-text">Calcula la caducidad del lote.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Reparto del costo entre coproductos</label>
                            <select id="pfo-metodo" class="form-select form-select-sm">
                                <option value="">Usar el de la configuración</option>
                                <?php foreach ($metodos as $k => $l): ?>
                                    <option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-none" id="pfo-col-reparto-principal">
                            <label class="form-label small fw-bold">% del principal</label>
                            <input type="number" id="pfo-reparto-principal" class="form-control form-control-sm text-end" min="0" max="100" step="any">
                        </div>
                    </div>

                    <div class="d-flex align-items-center mt-3 border-bottom">
                        <ul class="nav nav-tabs border-0 flex-grow-1" role="tablist">
                            <li class="nav-item"><button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-insumos" type="button"><i class="bi bi-box-arrow-in-right me-1"></i>Insumos</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-salidas" type="button"><i class="bi bi-box-arrow-right me-1"></i>Otras salidas</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-costos" type="button"><i class="bi bi-person-gear me-1"></i>Mano de obra y CIF</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-instr" type="button"><i class="bi bi-card-text me-1"></i>Instrucciones</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-estimado" type="button" onclick="window.PFO_estimar()"><i class="bi bi-calculator me-1"></i>Costo estimado</button></li>
                        </ul>
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasFormula, $vistaConfig ?? [], $rutaModulo) ?>
                    </div>

                    <div class="tab-content pt-2">
                        <!-- Insumos -->
                        <div class="tab-pane fade show active" id="pane-pfo-insumos">
                            <div class="form-text mb-1">
                                <b>Proporcional</b>: cantidad para lo que rinde la fórmula (escala con la orden).
                                <b>% del lote</b>: porcentaje de la cantidad a producir (fórmulas de proceso).
                                <b>Fija por orden</b>: no escala (una limpieza, un troquel).
                            </div>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0 pfo-lineas" style="font-size:.78rem;">
                                    <thead class="table-light"><tr>
                                        <th style="min-width:260px;">Insumo</th>
                                        <th style="width:130px;">Forma</th>
                                        <th style="width:110px;" class="text-end">Cantidad</th>
                                        <th style="width:80px;">Unidad</th>
                                        <th style="width:90px;" class="text-end">Merma %</th>
                                        <th style="width:70px;" class="text-center" title="Exigir lote al consumir">Lote</th>
                                        <th style="width:36px;"></th>
                                    </tr></thead>
                                    <tbody id="pfo-tbody-insumos"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 pfo-editable" onclick="window.PFO_agregarLinea('insumo')"><i class="bi bi-plus-lg me-1"></i>Agregar insumo</button>
                            <span class="small text-muted ms-2" id="pfo-suma-pct"></span>
                        </div>

                        <!-- Otras salidas -->
                        <div class="tab-pane fade" id="pane-pfo-salidas">
                            <div class="form-text mb-1">
                                La salida principal es el producto de arriba. Aquí van los <b>coproductos</b> (también se buscan y llevan parte del costo:
                                cortes, tallas), los <b>subproductos</b> (secundarios, a su valor de venta neto: retazos, cascarilla) y el <b>desperdicio</b>
                                (solo se registra la cantidad).
                            </div>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0 pfo-lineas" style="font-size:.78rem;">
                                    <thead class="table-light"><tr>
                                        <th style="width:130px;">Tipo</th>
                                        <th style="min-width:240px;">Producto / descripción</th>
                                        <th style="width:130px;">Forma</th>
                                        <th style="width:110px;" class="text-end">Cantidad</th>
                                        <th style="width:80px;">Unidad</th>
                                        <th style="width:110px;" class="text-end" title="Coproducto: % fijo del costo. Subproducto: valor neto por unidad.">% / Valor</th>
                                        <th style="width:36px;"></th>
                                    </tr></thead>
                                    <tbody id="pfo-tbody-salidas"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 pfo-editable" onclick="window.PFO_agregarLinea('coproducto')"><i class="bi bi-plus-lg me-1"></i>Agregar salida</button>
                        </div>

                        <!-- Mano de obra y CIF -->
                        <div class="tab-pane fade" id="pane-pfo-costos">
                            <div class="form-text mb-1">Costos estándar que se suman a los materiales. En cada orden se pueden ajustar antes de cerrarla.</div>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0 pfo-lineas" style="font-size:.78rem;">
                                    <thead class="table-light"><tr>
                                        <th style="width:150px;">Tipo</th>
                                        <th style="min-width:200px;">Descripción</th>
                                        <th style="width:220px;">Base de cálculo</th>
                                        <th style="width:110px;" class="text-end">Cantidad</th>
                                        <th style="width:110px;" class="text-end">Tarifa</th>
                                        <th style="width:36px;"></th>
                                    </tr></thead>
                                    <tbody id="pfo-tbody-recursos"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 pfo-editable" onclick="window.PFO_agregarRecurso()"><i class="bi bi-plus-lg me-1"></i>Agregar costo</button>
                            <div class="form-text mt-1">
                                Por unidad: cantidad × tarifa por cada unidad producida. Fijo por orden: una vez. Por hora: horas para lo que rinde la fórmula × tarifa.
                                % de la materia prima: la tarifa es el porcentaje.
                            </div>
                        </div>

                        <!-- Instrucciones -->
                        <div class="tab-pane fade" id="pane-pfo-instr">
                            <label class="form-label small fw-bold">Instrucciones de fabricación</label>
                            <textarea id="pfo-instrucciones" class="form-control form-control-sm" rows="7" placeholder="Pasos, temperaturas, tiempos de mezcla, orden de adición… Salen impresas en el registro de lote."></textarea>
                            <label class="form-label small fw-bold mt-2">Observaciones</label>
                            <textarea id="pfo-observaciones" class="form-control form-control-sm" rows="2"></textarea>
                        </div>

                        <!-- Costo estimado -->
                        <div class="tab-pane fade" id="pane-pfo-estimado">
                            <div class="d-flex align-items-end gap-2 mb-2">
                                <div>
                                    <label class="form-label small fw-bold mb-1">Simular para</label>
                                    <input type="number" id="pfo-cantidad-estimar" class="form-control form-control-sm text-end" style="width:130px;" min="0" step="any">
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.PFO_estimar()"><i class="bi bi-calculator me-1"></i>Calcular</button>
                            </div>
                            <div id="pfo-estimado" class="small text-muted">Pulse Calcular para ver el costo con los costos actuales de los insumos.</div>
                        </div>
                    </div>

                    <div class="small text-muted mt-3 d-none" id="pfo-auditoria"></div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <button type="button" id="pfo-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.PFO_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" id="pfo-btn-guardar" class="btn btn-primary btn-sm px-4" onclick="window.PFO_guardar()">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
