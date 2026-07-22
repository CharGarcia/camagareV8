<div class="modal fade" id="modalRolVer" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="height: 82vh;">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-list-check me-2 text-primary"></i>
                    <span id="rolver_titulo">Rol de Pago</span>
                    <span id="rolver_estado" class="badge ms-2 d-none"></span>
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-flex flex-column p-0" style="min-height:0;">
                <div class="px-3 pt-3 pb-2 border-bottom">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 small">
                        <span class="text-muted" id="rolver_periodo"></span>
                        <span class="text-muted" id="rolver_totales"></span>
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="rolver_buscar" class="form-control border-start-0 ps-0 shadow-none" placeholder="Buscar empleado por nombre o cédula..." autocomplete="off" oninput="window.rolVerFiltrar()">
                    </div>
                </div>
                <div id="rolver_avisos" class="px-3"></div>
                <div class="flex-grow-1" style="overflow-y:auto; min-height:0;">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light" style="position:sticky; top:0; z-index:5;">
                            <tr>
                                <th class="ps-3">Empleado</th>
                                <th class="text-end">Ingresos</th>
                                <th class="text-end">Egresos</th>
                                <th class="text-end pe-3">Neto</th>
                            </tr>
                        </thead>
                        <tbody id="rolver_lista">
                            <tr><td colspan="4" class="text-center py-5 text-muted">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <?php if (!empty($perm['eliminar'])): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="rolverBtnEliminar" onclick="window.rolEliminarModal()"><i class="bi bi-trash3 me-1"></i>Eliminar</button>
                <?php endif; ?>
                <span class="me-auto small text-muted" id="rolver_conteo"></span>
                <?php if (!empty($perm['actualizar'])): ?>
                    <button type="button" class="btn btn-success btn-sm" id="rolverBtnEgresos" onclick="window.abrirEgresoLote()"><i class="bi bi-cash-coin me-1"></i>Generar egresos</button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Mini-modal: Generar egresos de nómina en lote -->
<div class="modal fade" id="modalEgresoLote" tabindex="-1" aria-hidden="true" style="z-index:1075;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2 text-success"></i>Generar egresos de nómina</h6>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Se generará <b>un egreso por cada empleado marcado</b>, con su neto pendiente. Los empleados ya pagados no aparecen en la lista.</p>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Fecha de pago</label>
                        <input type="date" id="egl_fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Punto de emisión (serie)</label>
                        <select id="egl_punto" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Forma de pago</label>
                        <select id="egl_forma" class="form-select form-select-sm" onchange="window.eglFormaChange()"></select>
                    </div>
                    <div class="col-12 d-none" id="egl_banco_wrap">
                        <div class="row g-2 border rounded-2 bg-warning bg-opacity-10 p-2 mx-0">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">Operación bancaria</label>
                                <select id="egl_tipo_op" class="form-select form-select-sm" onchange="window.eglTipoOpChange()">
                                    <option value="TRANSFERENCIA" selected>Transferencia</option>
                                    <option value="DEPOSITO">Depósito</option>
                                    <option value="CHEQUE">Cheque</option>
                                </select>
                            </div>
                            <div class="col-6 d-none egl-cheque-campo" id="egl_cheque_wrap">
                                <label class="form-label small fw-bold mb-1">N° inicial de cheque</label>
                                <input type="number" id="egl_cheque_ini" class="form-control form-control-sm" min="1" step="1" placeholder="Ej. 1001">
                                <small class="text-muted">Se numera consecutivo por empleado.</small>
                            </div>
                            <!-- Solo para CHEQUE: fecha de cobro (aplica a todo el lote; control de posfechados) -->
                            <div class="col-6 d-none egl-cheque-campo" id="egl_cheque_fecha_wrap">
                                <label class="form-label small fw-bold mb-1"><i class="bi bi-calendar-date me-1"></i>Fecha de cobro</label>
                                <input type="date" id="egl_cheque_fecha" class="form-control form-control-sm">
                                <small class="text-muted">Se aplica a todos los cheques del lote.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selección de empleados a los que se generará el egreso -->
                <div class="d-flex justify-content-between align-items-center mt-3 mb-1">
                    <label class="form-label small fw-bold mb-0">Empleados a pagar</label>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="window.eglMarcarTodos(true)">Marcar todos</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="window.eglMarcarTodos(false)">Ninguno</button>
                    </div>
                </div>
                <div class="border rounded-2" style="max-height:230px;overflow:auto;">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size:0.78rem;">
                        <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                            <tr>
                                <th style="width:34px;" class="text-center">
                                    <input type="checkbox" class="form-check-input m-0" id="egl_chk_todos"
                                           onchange="window.eglMarcarTodos(this.checked)" checked>
                                </th>
                                <th>Empleado</th>
                                <th style="width:110px;" class="text-end">Neto</th>
                                <th style="width:110px;" class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody id="egl_empleados"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center bg-light border rounded-2 mt-1 px-2 py-1">
                    <span class="small text-muted"><span id="egl_sel_conteo" class="fw-bold">0</span> empleado(s) seleccionado(s)</span>
                    <span class="small">Total a pagar: <span id="egl_sel_total" class="fw-bold">0.00</span></span>
                </div>

                <div id="egl_msg" class="mt-2"></div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                <button type="button" class="btn btn-success btn-sm px-3" id="eglBtnConfirmar" onclick="window.confirmarEgresoLote()"><i class="bi bi-check2-circle me-1"></i>Generar egresos</button>
            </div>
        </div>
    </div>
</div>
