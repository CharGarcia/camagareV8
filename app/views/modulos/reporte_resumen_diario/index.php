<?php /** @var string $rutaModulo @var string $base @var array $perm @var array $formas @var array $usuarios */
$puedeTraslado = !empty($perm['crear']);
$puedeApertura = !empty($perm['actualizar']) && !empty($perm['todo']);
$puedeCaja     = !empty($perm['crear']);                                   // abrir y cerrar caja
$puedeReabrir  = !empty($perm['actualizar']) && !empty($perm['todo']); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .rrd-scroll { overflow-x: auto; }
    .rrd-scroll thead th { background: #f8f9fa; white-space: nowrap; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-rrd .form-select,
    #form-filtros-rrd .form-control,
    #form-filtros-rrd .btn { height:28px; font-size:.75rem; }
    /* El contenido se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-reporte_resumen_diario .rrd-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-reporte_resumen_diario">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-day me-2 text-primary"></i>Resumen Diario</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-rrd" class="d-flex flex-wrap align-items-start gap-2" onsubmit="return false;">
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="rrd-fecha">Día</label>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="rrdBtnAnterior" title="Día anterior"><i class="bi bi-chevron-left"></i></button>
                        <input type="date" id="rrd-fecha" class="form-control form-control-sm shadow-none border" style="width:125px;" value="<?= date('Y-m-d') ?>">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="rrdBtnSiguiente" title="Día siguiente"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="rrd-borradores">Borradores</label>
                    <select id="rrd-borradores" class="form-select form-select-sm shadow-none border" style="width:150px;"
                            title="Facturas, notas, liquidaciones y retenciones electrónicas aún no autorizadas">
                        <option value="">Sin borradores</option>
                        <option value="INCLUIR">Con borradores</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="rrd-saldos">Caja</label>
                    <select id="rrd-saldos" class="form-select form-select-sm shadow-none border" style="width:170px;"
                            title="Neto del día: solo lo que entró y salió ese día. Con saldos iniciales: además, el saldo que traía cada forma de pago y el saldo final. Cierre de caja: abrir la caja con su saldo inicial y cerrarla con lo contado">
                        <option value="">Neto del día</option>
                        <option value="SI">Con saldos iniciales</option>
                        <option value="CIERRE">Cierre de caja</option>
                    </select>
                </div>
                <?php if ($usuarios): ?>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="rrd-usuario">Usuario</label>
                    <select id="rrd-usuario" class="form-select form-select-sm shadow-none border" style="width:180px;"
                            title="Todos: lo de toda la empresa. Un usuario: solo lo que registró ese usuario (y su caja)">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) $u['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rrdBtnLimpiar" title="Volver a hoy"><i class="bi bi-eraser me-1"></i>Limpiar</button>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm" id="rrdBtnMostrar"><i class="bi bi-search me-1"></i>Mostrar</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrd-kpi-ventas">$0.00</div>
                        <div class="cmg-control-card__stat-label">Ventas netas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cart bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrd-kpi-compras">$0.00</div>
                        <div class="cmg-control-card__stat-label">Compras netas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-arrow-down-circle bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="rrd-kpi-ingresos">$0.00</div>
                        <div class="cmg-control-card__stat-label">Ingresos (cobros)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-arrow-up-circle bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger" id="rrd-kpi-egresos">$0.00</div>
                        <div class="cmg-control-card__stat-label">Egresos (pagos)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-wallet2 bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-primary" id="rrd-kpi-neto">$0.00</div>
                        <div class="cmg-control-card__stat-label">Neto del día</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat d-none" id="rrd-stat-saldo">
                    <i class="bi bi-safe bg-dark bg-opacity-10 text-dark"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrd-kpi-saldo">$0.00</div>
                        <div class="cmg-control-card__stat-label" id="rrd-kpi-saldo-lbl">Saldo final</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat d-none" id="rrd-stat-contado">
                    <i class="bi bi-cash-stack bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrd-kpi-contado">$0.00</div>
                        <div class="cmg-control-card__stat-label">Contado</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat d-none" id="rrd-stat-diferencia">
                    <i class="bi bi-plus-slash-minus bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrd-kpi-diferencia">$0.00</div>
                        <div class="cmg-control-card__stat-label">Diferencia</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Detalle del día ── -->
    <div class="card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-danger" id="rrdBtnPdf" disabled><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                    <button type="button" class="btn btn-outline-success" id="rrdBtnExcel" disabled><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small" id="rrd-titulo-dia"></span>
                    <span class="badge d-none" id="rrd-caja-estado"></span>
                    <?php if ($puedeCaja): ?>
                        <button type="button" class="btn btn-success btn-sm d-none" id="rrdBtnAbrirCaja" title="Fijar el saldo inicial de cada forma de pago">
                            <i class="bi bi-unlock me-1"></i>Abrir caja
                        </button>
                        <button type="button" class="btn btn-danger btn-sm d-none" id="rrdBtnCerrarCaja" title="Arqueo: escribir lo contado en cada forma de pago">
                            <i class="bi bi-lock me-1"></i>Cerrar caja
                        </button>
                    <?php endif; ?>
                    <?php if ($puedeReabrir): ?>
                        <button type="button" class="btn btn-outline-warning btn-sm d-none" id="rrdBtnReabrirCaja" title="Volver a abrir la caja para corregir (borra el arqueo)">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reabrir caja
                        </button>
                    <?php endif; ?>
                    <?php if ($puedeTraslado): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="rrdBtnTraslado"
                                title="Dinero que pasa de una forma de pago a otra (p. ej. depositar el efectivo en el banco)">
                            <i class="bi bi-arrow-left-right me-1"></i>Nuevo traslado
                        </button>
                    <?php endif; ?>
                    <?php if ($puedeApertura): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rrdBtnApertura"
                                title="Saldo con que arranca cada forma de pago">
                            <i class="bi bi-safe me-1"></i>Saldos de apertura
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-body p-0 pb-2" id="rrd-contenido">
            <div class="text-center py-5 text-muted"><i class="bi bi-calendar-day fs-3 d-block mb-2"></i>Elija el día y presione <strong>Mostrar</strong>.</div>
        </div>
    </div>
</div>

<?php if ($puedeTraslado): ?>
<!-- ── Nuevo traslado entre formas de pago ── -->
<div class="modal fade" id="rrdModalTraslado" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-1"></i>Nuevo traslado</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">Dinero que pasa de una forma de pago a otra sin ser ingreso ni egreso, por ejemplo depositar el efectivo en el banco. No genera asiento contable.</p>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small fw-bold mb-1 d-block" for="rrd-tr-fecha">Fecha</label>
            <input type="date" class="form-control form-control-sm" id="rrd-tr-fecha">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold mb-1 d-block" for="rrd-tr-valor">Valor</label>
            <input type="number" class="form-control form-control-sm text-end" id="rrd-tr-valor" step="0.01" min="0.01" placeholder="0.00">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold mb-1 d-block" for="rrd-tr-origen">Desde</label>
            <select class="form-select form-select-sm" id="rrd-tr-origen">
              <option value="">Elija…</option>
              <?php foreach ($formas as $fp): ?>
                <option value="<?= (int) $fp['id'] ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold mb-1 d-block" for="rrd-tr-destino">Hacia</label>
            <select class="form-select form-select-sm" id="rrd-tr-destino">
              <option value="">Elija…</option>
              <?php foreach ($formas as $fp): ?>
                <option value="<?= (int) $fp['id'] ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-bold mb-1 d-block" for="rrd-tr-obs">Observaciones</label>
            <input type="text" class="form-control form-control-sm" id="rrd-tr-obs" maxlength="300" placeholder="Ej.: depósito papeleta 123456">
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" id="rrdBtnGuardarTraslado"><i class="bi bi-check-lg me-1"></i>Guardar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($puedeApertura): ?>
<!-- ── Saldos de apertura por forma de pago ── -->
<div class="modal fade" id="rrdModalApertura" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-safe me-1"></i>Saldos de apertura</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">
          El saldo con que arranca cada forma de pago al <strong>inicio</strong> de la fecha indicada. Desde esa fecha el saldo se
          acumula día a día con los Ingresos, Egresos y traslados. Sin apertura, el saldo se acumula desde el primer movimiento.
          Para quitar una apertura, borre su fecha.
        </p>
        <table class="table table-sm align-middle mb-0" style="font-size:.8rem;">
          <thead class="table-light">
            <tr><th>Forma de pago</th><th style="width:150px;">Fecha</th><th class="text-end" style="width:140px;">Saldo</th></tr>
          </thead>
          <tbody id="rrd-ap-tbody">
            <tr><td colspan="3" class="text-center text-muted py-3">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" id="rrdBtnGuardarApertura" disabled><i class="bi bi-check-lg me-1"></i>Guardar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($puedeCaja): ?>
<!-- ── Abrir caja: saldo inicial por forma de pago ── -->
<div class="modal fade" id="rrdModalAbrirCaja" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-unlock me-1"></i>Abrir caja <span class="fw-normal text-muted" id="rrd-ac-titulo"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">
          El saldo con que arranca la caja en cada forma de pago (en efectivo, el fondo). Se propone lo contado en el
          último cierre más lo que se movió en los días sin cerrar; puede corregirlo. Deje en cero las formas que no usa.
        </p>
        <table class="table table-sm align-middle mb-2" style="font-size:.8rem;">
          <thead class="table-light">
            <tr><th>Forma de pago</th><th class="text-end" style="width:160px;">Saldo inicial</th></tr>
          </thead>
          <tbody id="rrd-ac-tbody">
            <tr><td colspan="2" class="text-center text-muted py-3">Cargando…</td></tr>
          </tbody>
        </table>
        <label class="form-label small fw-bold mb-1 d-block" for="rrd-ac-obs">Observaciones</label>
        <input type="text" class="form-control form-control-sm" id="rrd-ac-obs" maxlength="300">
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="rrdBtnGuardarAbrirCaja" disabled><i class="bi bi-check-lg me-1"></i>Abrir caja</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Cerrar caja: arqueo por forma de pago ── -->
<div class="modal fade" id="rrdModalCerrarCaja" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-lock me-1"></i>Cerrar caja <span class="fw-normal text-muted" id="rrd-cc-titulo"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <p class="small text-muted mb-0">
            Escriba lo que hay realmente en cada forma de pago (el efectivo contado, el saldo del banco…). El esperado es
            saldo inicial + ingresos − egresos ± traslados del día; el sistema lo vuelve a calcular al guardar.
          </p>
          <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" id="rrdBtnCopiarEsperado" title="Poner el esperado como contado en todas las formas">
            <i class="bi bi-clipboard-check me-1"></i>Copiar esperado
          </button>
        </div>
        <table class="table table-sm align-middle mb-2" style="font-size:.8rem;">
          <thead class="table-light">
            <tr><th>Forma de pago</th><th class="text-end">Esperado</th><th class="text-end" style="width:150px;">Contado</th><th class="text-end">Diferencia</th></tr>
          </thead>
          <tbody id="rrd-cc-tbody">
            <tr><td colspan="4" class="text-center text-muted py-3">Cargando…</td></tr>
          </tbody>
          <tfoot>
            <tr class="fw-bold table-light">
              <td>Total</td><td class="text-end" id="rrd-cc-tot-esp"></td><td class="text-end" id="rrd-cc-tot-cont"></td><td class="text-end" id="rrd-cc-tot-dif"></td>
            </tr>
          </tfoot>
        </table>
        <label class="form-label small fw-bold mb-1 d-block" for="rrd-cc-obs">Observaciones</label>
        <input type="text" class="form-control form-control-sm" id="rrd-cc-obs" maxlength="500" placeholder="Ej.: faltante de $2 por cambio">
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger btn-sm" id="rrdBtnGuardarCerrarCaja" disabled><i class="bi bi-lock me-1"></i>Cerrar caja</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
    window.RRD_RUTA = <?= json_encode($rutaModulo) ?>;
    window.RRD_BASE = <?= json_encode($base) ?>;
    window.RRD_HOY  = <?= json_encode(date('Y-m-d')) ?>;
    window.RRD_PUEDE_ELIMINAR = <?= json_encode(!empty($perm['eliminar'])) ?>;
</script>
<script src="<?= $base ?>/js/modulos/reporte_resumen_diario.js?v=<?= asset_ver('/js/modulos/reporte_resumen_diario.js') ?>"></script>
