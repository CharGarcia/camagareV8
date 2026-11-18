<?php
/**
 * Modal «Encerar existencias» de Movimientos de Inventario (solo niveles 2 y 3).
 * Lo incluye index.php solo si $puedeEncerar. JS: public/js/modulos/inventario_encerado.js.
 *
 * @var array $bodegas Bodegas permitidas al usuario (id, nombre).
 */
$hoy = date('Y-m-d');
?>
<style>
    #modalEncerado .enc-bodegas { max-height: 180px; overflow-y: auto; }
    #modalEncerado .enc-kpi { min-width: 120px; }
    #modalEncerado .enc-detalle-scroll { max-height: 260px; overflow: auto; }
    #modalEncerado .enc-detalle-scroll thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
    #modalEncerado .enc-historial-scroll { max-height: 460px; overflow: auto; }
    #modalEncerado .enc-historial-scroll thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
    #modalEncerado td, #modalEncerado th { font-size: .78rem; }
</style>

<div class="modal fade" id="modalEncerado" tabindex="-1" aria-labelledby="modalEnceradoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold text-dark" id="modalEnceradoLabel">
                    <i class="bi bi-eraser text-danger me-2"></i>Encerar existencias
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="nav nav-tabs px-3 pt-2 bg-light" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="enc-tab-nuevo" data-bs-toggle="tab" data-bs-target="#enc-pane-nuevo" type="button" role="tab">
                            <i class="bi bi-eraser me-1"></i>Nuevo encerado
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="enc-tab-historial" data-bs-toggle="tab" data-bs-target="#enc-pane-historial" type="button" role="tab">
                            <i class="bi bi-clock-history me-1"></i>Historial
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-3">
                    <!-- ── Nuevo encerado ── -->
                    <div class="tab-pane fade show active" id="enc-pane-nuevo" role="tabpanel">
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Deja en <strong>cero</strong>, a la fecha de corte, el saldo de cada producto, lote y NUP de las bodegas elegidas.
                            No borra nada: registra en el kardex una salida (o una entrada, si el saldo es negativo) que lo compensa.
                            <strong>No genera asiento contable.</strong> Los movimientos posteriores a la fecha de corte se mantienen.
                            Se puede revertir desde la pestaña <em>Historial</em>.
                        </div>

                        <form id="formEncerado" class="row g-3" autocomplete="off" onsubmit="return false;">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold d-block" for="encFecha">Fecha de corte</label>
                                <input type="date" class="form-control form-control-sm" id="encFecha" name="fecha_corte"
                                       value="<?= $hoy ?>" max="<?= $hoy ?>" required>
                                <div class="form-text">El saldo queda en cero al cierre de ese día.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold d-block">Bodegas</label>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="encTodas" name="todas_bodegas" value="1">
                                    <label class="form-check-label small fw-semibold" for="encTodas">Todas las bodegas</label>
                                </div>
                                <div class="enc-bodegas border rounded p-2" id="encListaBodegas">
                                    <?php foreach (($bodegas ?? []) as $b): ?>
                                        <div class="form-check">
                                            <input class="form-check-input enc-bodega" type="checkbox"
                                                   id="encBod<?= (int) $b['id'] ?>" value="<?= (int) $b['id'] ?>">
                                            <label class="form-check-label small" for="encBod<?= (int) $b['id'] ?>"><?= htmlspecialchars((string) $b['nombre']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($bodegas)): ?>
                                        <div class="text-muted small">No hay bodegas activas.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold d-block" for="encObs">Observación (motivo)</label>
                                <textarea class="form-control form-control-sm" id="encObs" name="observacion" rows="3" maxlength="300"
                                          placeholder="Ej.: Inicio de inventario físico 2027"></textarea>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnEncPrevia">
                                        <i class="bi bi-search me-1"></i>Previsualizar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Resultado de la vista previa -->
                        <div id="encPrevia" class="mt-3 d-none">
                            <div class="d-flex flex-wrap gap-2 mb-2" id="encKpis"></div>
                            <div id="encAvisoDetalle" class="small text-muted mb-1"></div>
                            <div class="enc-detalle-scroll border rounded">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-2">Bodega</th>
                                            <th>Producto</th>
                                            <th>Lote</th>
                                            <th>NUP/Serial</th>
                                            <th class="text-end">Saldo a la fecha</th>
                                            <th class="text-end">Costo prom.</th>
                                            <th class="pe-2">Movimiento</th>
                                        </tr>
                                    </thead>
                                    <tbody id="encDetalle"></tbody>
                                </table>
                            </div>

                            <div class="border border-danger rounded p-2 mt-3 bg-danger bg-opacity-10">
                                <label class="form-label small fw-semibold d-block mb-1" for="encConfirmar">
                                    Para confirmar escriba <strong>ENCERAR</strong>
                                </label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <input type="text" class="form-control form-control-sm" id="encConfirmar" style="width:180px" autocomplete="off">
                                    <button type="button" class="btn btn-danger btn-sm" id="btnEncAplicar" disabled>
                                        <i class="bi bi-eraser me-1"></i>Encerar existencias
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Historial ── -->
                    <div class="tab-pane fade" id="enc-pane-historial" role="tabpanel">
                        <div class="enc-historial-scroll border rounded">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-2">#</th>
                                        <th>Fecha de corte</th>
                                        <th>Bodegas</th>
                                        <th class="text-end">Movimientos</th>
                                        <th class="text-end">Unid. salida</th>
                                        <th class="text-end">Unid. entrada</th>
                                        <th>Observación</th>
                                        <th>Registrado</th>
                                        <th>Estado</th>
                                        <th class="pe-2"></th>
                                    </tr>
                                </thead>
                                <tbody id="encHistorial">
                                    <tr><td colspan="10" class="text-center text-muted py-3">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-end bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
