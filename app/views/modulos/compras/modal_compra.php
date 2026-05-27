<!-- ===== MODAL COMPRAS ===== -->
<div class="modal fade modal-factura" id="modalCompra" tabindex="-1" aria-labelledby="modalCompraLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content cmg-favoritos-card" data-modulo="compras">

      <div class="modal-header py-2 px-3">
        <h5 class="modal-title fs-6 fw-bold" id="modalCompraLabel"><i class="bi bi-cart3 me-2 text-primary"></i><span id="mcTitulo">Nueva Compra</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-0">
        <input type="hidden" id="mcId" name="id">
        <input type="hidden" id="mcIdEstablecimiento" name="id_establecimiento">
        <!-- Barra de Acciones Superior -->
        <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
          <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="abrirModalProductoCrear()" title="Registrar nuevo producto"><i class="bi bi-box-seam fs-6"></i></button>
          <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="abrirModalProveedorCrear()" title="Registrar nuevo proveedor"><i class="bi bi-person-plus fs-6"></i></button>
          <button type="button" class="btn btn-outline-secondary btn-sm px-2 d-none" id="mcBtnDescargarXml" onclick="mcDescargarXml()" title="Descargar XML del documento electrónico">
            <i class="bi bi-file-earmark-code fs-6"></i> <span class="d-none d-md-inline small">XML</span>
          </button>
        </div>


        <!-- Pestañas -->
        <div class="d-flex align-items-center bg-light px-3 pt-2">
          <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="mcTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" id="tab_compra" data-bs-toggle="tab" href="#tabDetalleCompra" role="tab"><i class="bi bi-receipt me-1"></i>Detalle de Compra</a></li>
            <li class="nav-item"><a class="nav-link" id="tab_asiento" data-bs-toggle="tab" href="#tabAsiento" role="tab"><i class="bi bi-calculator me-1"></i>Asiento contable</a></li>
            <li class="nav-item"><a class="nav-link" id="tab_pagos" data-bs-toggle="tab" href="#tabPagos" role="tab"><i class="bi bi-credit-card me-1"></i>Pagos</a></li>
            <li class="nav-item"><a class="nav-link" id="tab-inventario-tab" data-bs-toggle="tab" href="#tabInventario" role="tab"><i class="bi bi-box-seam me-1"></i>Inventario</a></li>
            <li class="nav-item"><a class="nav-link" id="tab-retenciones-tab" data-bs-toggle="tab" href="#tabRetenciones" role="tab"><i class="bi bi-file-earmark-text me-1"></i>Retenciones</a></li>
          </ul>
          <div class="ms-auto pb-1">
            <?php
            $pestanasConfig = [
              'tab_asiento'     => 'Asiento contable',
              'tab_pagos'       => 'Pagos',
              'tab_inventario'  => 'Inventario',
              'tab_retenciones' => 'Retenciones'
            ];
            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfig, $vistaConfig ?? [], 'modulos/compras');
            ?>
          </div>
        </div>
        <div class="border-bottom bg-light mb-0"></div>

        <div class="tab-content border-top px-3 py-3" id="tabCompraContent">

          <!-- ════════════════════════════════════════
               TAB 1: DETALLE COMPRA
          ════════════════════════════════════════ -->
          <div class="tab-pane fade show active" id="tabDetalleCompra" role="tabpanel">

            <div class="row g-2">
              <!-- Fila 1 -->
              <!-- Fecha Emisión -->
              <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1 fw-semibold">Fecha Emisión <span class="text-danger">*</span></label>
                <input type="date" id="mcFechaEmision" class="form-control form-control-sm">
              </div>

              <!-- Proveedor -->
              <div class="col-12 col-md-5">
                <label class="form-label form-label-sm mb-1 fw-semibold">Proveedor <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="text" id="mcBuscarProveedor" class="form-control form-control-sm" placeholder="Buscar por nombre o RUC..." autocomplete="off">
                  <input type="hidden" id="mcIdProveedor">
                  <div id="mcListaProveedores" class="list-group position-absolute z-3 shadow-sm w-100 d-none" style="max-height:200px;overflow-y:auto;"></div>
                </div>
              </div>

              <!-- Tipo Comprobante -->
              <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">
                  Tipo Comprobante <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('compras', 'mcTipoComprobante', 'tipo_comprobante') ?> <span class="text-danger">*</span>
                </label>
                <select id="mcTipoComprobante" class="form-select form-select-sm" onchange="
                  // Foco al siguiente campo (N° Comprobante)
                  document.getElementById('mcNumeroComprobante').focus();
                  
                  // Mostrar/ocultar campos de modificación (04 = Nota de Crédito, 05 = Nota de Débito)
                  const esModificativo = ['04', '05'].includes(this.value);
                  document.getElementById('mcDivModificados').classList.toggle('d-none', !esModificativo);
                ">
                  <option value="">-- Seleccione --</option>
                  <?php foreach ($tiposComprobante as $tc): ?>
                    <option value="<?= $tc['codigo_comprobante'] ?>"><?= $tc['codigo_comprobante'] ?> - <?= $tc['comprobante'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- N° Comprobante -->
              <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1 fw-semibold">N° Comprobante <span class="text-danger">*</span></label>
                <input type="text" id="mcNumeroComprobante" class="form-control form-control-sm text-center" placeholder="000-000-000000000">
              </div>

              <!-- Fila 2 -->
              <?php
              $tipoEmp = $empresa['tipo'] ?? '01';
              $esPersonaNatural = ($tipoEmp == '01' || $tipoEmp == '1');
              $dNonePN = $esPersonaNatural ? 'd-none' : '';
              ?>
              <!-- Sustento Tributario -->
              <div class="col-12 col-md-6 <?= $dNonePN ?>">
                <label class="form-label form-label-sm mb-1 fw-semibold">
                  Sustento Tributario <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('compras', 'mcSustento', 'id_sustento_tributario') ?> <?php if (!$esPersonaNatural): ?><span class="text-danger">*</span><?php endif; ?>
                </label>
                <select id="mcSustento" class="form-select form-select-sm">
                  <option value="">-- Seleccione Tipo de Comprobante --</option>
                </select>
              </div>

              <!-- Autorización -->
              <div class="col-6 col-md-6 <?= $dNonePN ?>">
                <label class="form-label form-label-sm mb-1 fw-semibold">N° Autorización <?php if (!$esPersonaNatural): ?><span class="text-danger">*</span><?php endif; ?></label>
                <input type="text" id="mcAutorizacion" class="form-control form-control-sm" maxlength="49" placeholder="Solo números">
              </div>

              <!-- Autorización Desde -->
              <div class="col-6 col-md-2 <?= $dNonePN ?>">
                <label class="form-label form-label-sm mb-1 fw-semibold">Desde <?php if (!$esPersonaNatural): ?><span class="text-danger">*</span><?php endif; ?></label>
                <input type="text" id="mcAutorizacionDesde" class="form-control form-control-sm" placeholder="Secuencial inicial">
              </div>

              <!-- Autorización Hasta -->
              <div class="col-6 col-md-2 <?= $dNonePN ?>">
                <label class="form-label form-label-sm mb-1 fw-semibold">Hasta <?php if (!$esPersonaNatural): ?><span class="text-danger">*</span><?php endif; ?></label>
                <input type="text" id="mcAutorizacionHasta" class="form-control form-control-sm" placeholder="Secuencial final">
              </div>

              <!-- Fecha Caducidad -->
              <div class="col-6 col-md-2 <?= $dNonePN ?>">
                <label class="form-label form-label-sm mb-1 fw-semibold">Fecha Caducidad <?php if (!$esPersonaNatural): ?><span class="text-danger">*</span><?php endif; ?></label>
                <input type="date" id="mcFechaCaducidad" class="form-control form-control-sm">
              </div>

              <!-- Fila 3 -->
              <!-- Tipo de Registro -->
              <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">
                  Tipo Registro <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('compras', 'mcTipoRegistro', 'tipo_registro') ?>
                </label>
                <select id="mcTipoRegistro" class="form-select form-select-sm bg-light text-muted" disabled>
                  <option value="fisica">Física</option>
                  <option value="electronico">Electrónica</option>
                </select>
              </div>

              <!-- Deducible -->
              <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">
                  Deducible <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('compras', 'mcDeducible', 'deducible') ?>
                </label>
                <select id="mcDeducible" class="form-select form-select-sm">
                  <option value="declaracion_iva">Deducible para declaración IVA</option>
                  <option value="gasto_personal">Gasto Personal</option>
                </select>
              </div>

              <!-- Fila 4: Relacionada y Observaciones -->
              <div class="col-12">
                <div class="row g-2 mt-0">
                  <!-- Parte relacionada -->
                  <div class="col-6 col-md-2 d-flex align-items-end pb-1">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="mcParteRelacionada">
                      <label class="form-check-label small fw-semibold" for="mcParteRelacionada">Parte relacionada</label>
                    </div>
                  </div>

                  <!-- Observaciones -->
                  <div class="col-12 col-md-10">
                    <label class="form-label form-label-sm mb-1">Observaciones</label>
                    <input type="text" id="mcObservaciones" class="form-control form-control-sm" placeholder="Opcional">
                  </div>
                </div>
              </div>

              <!-- Fila 4: Solo para Notas de Crédito / Débito (Documento Modificado) -->
              <div id="mcDivModificados" class="col-12 d-none">
                <div class="row g-2 mt-1 px-0 mx-0 w-100">
                  <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm mb-1 fw-semibold text-primary">Documento que Modifica</label>
                    <input type="text" id="mcDocumentoModificado" class="form-control form-control-sm" placeholder="000-000-000000000">
                  </div>
                  <div class="col-12 col-md-8">
                    <label class="form-label form-label-sm mb-1 fw-semibold text-primary">Motivo de Modificación</label>
                    <input type="text" id="mcMotivo" class="form-control form-control-sm" placeholder="Indique el motivo...">
                  </div>
                </div>
              </div>

              <!-- Campo oculto para fecha registro -->
              <input type="hidden" id="mcFechaRegistro" value="<?= date('Y-m-d') ?>">
            </div>

            <!-- ── Detalle de Ítems ── -->
            <div class="mt-4">
              <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                <div class="table-responsive" style="max-height: 350px;">
                  <table class="table table-sm table-detalle mb-0">
                    <thead>
                      <tr class="table-light border-bottom">
                        <th class="ps-3 py-2 small fw-bold text-muted" style="width: 35%;">Descripción / Producto</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 10%;">Cant.</th>
                        <th class="py-2 small fw-bold text-muted text-end" style="width: 15%;">P. Unitario</th>
                        <th class="py-2 small fw-bold text-muted text-end" style="width: 10%;">Desc.</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 15%;">IVA</th>
                        <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 15%;">Subtotal</th>
                        <th style="width: 40px;"></th>
                      </tr>
                    </thead>
                    <tbody id="tbodyDetalle"></tbody>
                  </table>
                </div>
                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="CMG_agregarItemLibre()">
                      <i class="bi bi-plus-circle me-1"></i> Agregar línea
                    </button>
                    <div class="vr mx-1"></div>
                    <div class="input-group input-group-sm" style="width:250px">
                      <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                      <input type="text" id="inputBuscarProductoCompra" class="form-control border-0 px-1" placeholder="Buscar producto en catálogo..." autocomplete="off">
                    </div>
                  </div>
                  <div class="small fw-bold text-muted pe-3">
                    Ítems: <span id="mcCountItems">0</span>
                  </div>
                </div>
              </div>
              <div id="listaProductosCompra" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: 400px; max-height: 250px; overflow-y: auto;"></div>
            </div>

            <!-- ── Sección Inferior: Totales y Pagos ── -->
            <div class="row g-3 mt-1">
              <div class="col-md-8">
                <ul class="nav nav-tabs nav-tabs-sm mb-2" id="mc-subtabs-compra" role="tablist">
                  <li class="nav-item">
                    <button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#mc-subtab-pagos-sri" type="button">Formas de pago SRI</button>
                  </li>
                  <li class="nav-item">
                    <button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#mc-subtab-credito" type="button">Crédito</button>
                  </li>
                </ul>
                <div class="tab-content bg-white border p-2 rounded-bottom" style="min-height: 120px;">
                  <!-- Formas de Pago SRI -->
                  <div class="tab-pane fade show active" id="mc-subtab-pagos-sri" role="tabpanel">
                    <div id="mc-container-pagos-sri">
                      <div class="row g-2 align-items-center mb-1 row-pago-sri">
                        <div class="col-7">
                          <div class="d-flex align-items-center gap-1">
                            <i class="bi bi-star text-muted btn-favorito" style="cursor:pointer;" data-modulo="compras" data-campo="pago_sri_default" data-target=".input-pago-sri-id" title="Marcar como favorita"></i>
                            <select class="form-select form-select-sm border-0 bg-light input-pago-sri-id" name="pago_sri_id[]">
                              <?php foreach ($formasPago as $fp): ?>
                                <option value="<?= $fp['codigo'] ?>" data-id="<?= $fp['id'] ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-4">
                          <input type="number" class="form-control form-control-sm text-end border-0 bg-light fw-bold input-pago-sri-valor" name="pago_sri_valor[]" step="0.01" value="0.00">
                        </div>
                        <div class="col-1 text-center">
                          <span></span>
                        </div>
                      </div>
                    </div>
                    <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none small mt-1" onclick="CMG_agregarFormaPagoSRI()"><i class="bi bi-plus-circle me-1"></i>Añadir pago SRI</button>
                  </div>
                  <!-- Crédito SRI -->
                  <div class="tab-pane fade" id="mc-subtab-credito" role="tabpanel">
                    <div class="p-2">
                      <div class="row g-2">
                        <div class="col-md-6">
                          <label class="x-small text-muted mb-1">Días de crédito</label>
                          <input type="number" id="mcDiasCredito" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-6">
                          <label class="x-small text-muted mb-1">Plazo</label>
                          <select id="mcPlazoSRI" class="form-select form-select-sm">
                            <option value="Días">Días</option>
                            <option value="Meses">Meses</option>
                            <option value="Años">Años</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Derecha: Totales Verticales -->
              <div class="col-md-4">
                <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">
                  <!-- Subtotal General -->
                  <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                    <span class="text-muted">Subtotal</span>
                    <span id="mcLabelSubtotal">0.00</span>
                  </div>

                  <!-- Contenedor dinámico de subtotales por tarifa IVA -->
                  <div id="mcContenedorSubtotalesIva" class="mb-1"></div>

                  <!-- Descuento -->
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">(-) Descuento</span>
                    <span class="fw-bold text-danger" id="mcLabelDescuento">0.00</span>
                  </div>

                  <!-- Contenedor dinámico de IVAs por tarifa -->
                  <div id="mcContenedorIvasIva" class="mb-1"></div>

                  <!-- Propina -->
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">(+) Propina</span>
                    <div style="width:90px;">
                      <input type="number" id="mcInputPropina" class="form-control form-control-sm text-end border-0 bg-light fw-bold p-1"
                        value="0.00" min="0" step="0.01" oninput="CMG_recalcularTotales()">
                    </div>
                  </div>

                  <hr class="my-1 opacity-25">

                  <!-- Total Factura -->
                  <!-- Total Factura -->
                  <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                    <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                    <span class="fw-bold text-dark" style="font-size:1rem;" id="mcLabelTotal">0.00</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ════════════════════════════════════════

          <!-- ════════════════════════════════════════
               TAB 3: PAGOS
          ════════════════════════════════════════ -->
          <!-- ════════════════════════════════════════
               TAB 2: ASIENTO CONTABLE (placeholder)
          ════════════════════════════════════════ -->
          <div class="tab-pane fade" id="tabAsiento" role="tabpanel">
            <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
              <i class="bi bi-calculator fs-1 mb-3 text-success opacity-50"></i>
              <h6 class="fw-semibold">Asiento Contable Automático - Próximamente</h6>
              <p class="text-center small">La visualización del <strong>asiento contable</strong> generado por esta compra se mostrará aquí automáticamente una vez integrada la contabilidad.</p>
            </div>
          </div>

          <div class="tab-pane fade" id="tabPagos" role="tabpanel">
            <div class="p-3">
              <!-- Resumen de Deuda -->
              <div class="row g-3 mb-3">
                <div class="col-md-3">
                  <div class="border rounded-3 p-2 bg-white text-center shadow-sm border-secondary-subtle">
                    <div class="text-muted mb-0 fw-semibold" style="font-size: 0.75rem;">Total Documento</div>
                    <h4 class="fw-bold text-dark mb-0">$ <span id="pagoTotalCompra">0.00</span></h4>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded-3 p-2 bg-warning bg-opacity-10 border-warning border-opacity-25 text-center shadow-sm">
                    <div class="text-warning mb-0 fw-semibold" style="font-size: 0.75rem;">Retenciones</div>
                    <h4 class="fw-bold text-warning mb-0">$ <span id="pagoTotalRetencion">0.00</span></h4>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded-3 p-2 bg-success bg-opacity-10 border-success border-opacity-25 text-center shadow-sm">
                    <div class="text-success mb-0 fw-semibold" style="font-size: 0.75rem;">Total Abonado</div>
                    <h4 class="fw-bold text-success mb-0">$ <span id="pagoTotalAbonado">0.00</span></h4>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded-3 p-2 bg-danger bg-opacity-10 border-danger border-opacity-25 text-center shadow-sm">
                    <div class="text-danger mb-0 fw-semibold" style="font-size: 0.75rem;">Saldo Pendiente</div>
                    <h4 class="fw-bold text-danger mb-0">$ <span id="pagoSaldoPendiente">0.00</span></h4>
                  </div>
                </div>
              </div>

              <div class="row g-3">
                <!-- Izquierda: Historial de Pagos -->
                <div class="col-md-7">
                  <div class="card border border-secondary-subtle shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-light py-2 d-flex align-items-center border-bottom border-secondary-subtle">
                      <h6 class="card-title mb-0 fw-bold text-secondary" style="font-size: 0.85rem;"><i class="bi bi-list-ul me-2"></i>Historial de Pagos (Egresos)</h6>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive" style="max-height: 320px; min-height: 150px;">
                        <table class="table table-hover align-middle mb-0">
                          <thead class="table-light text-muted sticky-top border-bottom" style="font-size: 0.75rem;">
                            <tr>
                              <th class="ps-3">Fecha</th>
                              <th>Nº Egreso</th>
                              <th>Concepto / Forma</th>
                              <th class="text-end pe-3">Monto</th>
                            </tr>
                          </thead>
                          <tbody id="pagoTbodyHistorial" class="small" style="font-size: 0.8rem;">
                            <tr>
                              <td colspan="4" class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-2"></i>Cargando historial...</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Derecha: Formulario de Nuevo Pago -->
                <div class="col-md-5">
                  <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10 shadow-sm rounded-3 overflow-hidden d-none" id="pagoCardRegistro">
                    <div class="card-header bg-primary bg-opacity-25 border-0 py-2 d-flex align-items-center">
                      <h6 class="card-title mb-0 fw-bold text-primary" style="font-size: 0.85rem;"><i class="bi bi-plus-circle me-2"></i>Registrar Nuevo Pago</h6>
                    </div>
                    <div class="card-body py-3 px-3 bg-white">
                      <!-- Formulario Rápido -->
                      <form id="pagoFormNuevo" onsubmit="CMG_registrarPagoEgreso(event)">
                        <div class="row g-2">
                          <div class="col-6">
                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Serie <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="pagoPuntoEmision" required>
                              <option value="">- Seleccione -</option>
                            </select>
                          </div>
                          <div class="col-6">
                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Fecha Emisión <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm shadow-none border-secondary-subtle" id="pagoFechaEmision" value="<?= date('Y-m-d') ?>" required>
                          </div>
                          
                          <div class="col-6">
                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Concepto de Egreso <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="pagoConcepto" required>
                              <option value="">- Seleccione -</option>
                            </select>
                          </div>
                          <div class="col-6">
                            <label class="form-label fw-bold mb-0 text-danger" style="font-size:0.7rem;">Monto a Pagar ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm shadow-none fw-bold text-danger border-danger border-opacity-50" id="pagoMontoPagar" required>
                          </div>
                          <div class="col-12">
                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Forma de Pago <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="pagoFormaPago" onchange="CMG_toggleEgresoBancoForm(this.value)" required>
                              <option value="">- Seleccione Forma -</option>
                            </select>
                          </div>
                          
                          <!-- Campos Condicionales de Banco -->
                          <div class="col-12 d-none" id="pagoDivDetalleBanco">
                            <div class="border border-warning border-opacity-25 rounded-2 p-2 bg-warning bg-opacity-10 mb-1 row g-2">
                              <div class="col-6">
                                <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Op. Bancaria</label>
                                <select class="form-select form-select-sm" id="pagoTipoOp">
                                  <option value="TRANSFERENCIA">Transferencia</option>
                                  <option value="DEBITO">Débito</option>
                                  <option value="CHEQUE">Cheque</option>
                                </select>
                              </div>
                              <div class="col-6">
                                <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Nº Referencia</label>
                                <input type="text" class="form-control form-control-sm" id="pagoNumOp" placeholder="Nº doc / Transf">
                              </div>
                              <div class="col-12">
                                <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Banco</label>
                                <select class="form-select form-select-sm" id="pagoBancoId">
                                  <option value="">- Opcional -</option>
                                </select>
                              </div>
                            </div>
                          </div>

                          <div class="col-12">
                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Observaciones / Notas</label>
                            <input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle" id="pagoObservaciones" placeholder="Comentario del pago">
                          </div>

                          <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-success btn-sm w-100 py-2 fw-bold shadow-sm border-0" style="background: #198754;" id="pagoBtnRegistrar">
                              <i class="bi bi-check-circle me-2"></i>Registrar Pago y Generar Egreso
                            </button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                  
                  <!-- Alerta de Factura Completamente Pagada -->
                  <div class="alert alert-success border-success border-opacity-25 text-center py-4 shadow-sm mb-0 d-none" id="pagoAlertaPagada">
                    <i class="bi bi-check-circle-fill fs-2 mb-2 text-success d-block"></i>
                    <h6 class="fw-bold mb-1 text-success">¡Documento Completamente Pagado!</h6>
                    <p class="text-muted mb-0" style="font-size: 0.75rem;">El saldo pendiente de esta compra es de $0.00.</p>
                  </div>
                  
                  <!-- Alerta para Nuevas Compras -->
                  <div class="alert alert-secondary bg-light border-secondary-subtle text-center py-4 shadow-sm mb-0" id="pagoAlertaNueva">
                    <i class="bi bi-credit-card-2-front fs-2 mb-2 text-muted d-block opacity-50"></i>
                    <h6 class="fw-bold mb-1 text-secondary">Nuevo Registro</h6>
                    <p class="text-muted mb-0" style="font-size: 0.75rem;">Guarda la compra primero para poder habilitar el registro de pagos internos.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ════════════════════════════════════════
               TAB 4: RETENCIONES (placeholder)
          ════════════════════════════════════════ -->
          <div class="tab-pane fade" id="tabRetenciones" role="tabpanel">
            <div class="card cmg-table-card border-0 shadow-none">
              <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-primary btn-sm px-3" id="btnNuevaRetencionCompra" onclick="window.CMG_nuevaRetencionDesdeCompra()" disabled>
                    <i class="bi bi-plus-circle me-1"></i> Emitir Retención
                  </button>
                </div>
                <div class="ms-auto" id="mc-retenciones-info"></div>
              </div>

              <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                      <tr>
                        <th class="ps-3" style="width: 180px;">Nº Retención</th>
                        <th style="width: 120px;">Fecha</th>
                        <th class="text-end" style="width: 120px;">Monto</th>
                        <th class="text-center" style="width: 120px;">Estado</th>
                        <th style="width: 40px;"></th>
                      </tr>
                    </thead>
                    <tbody id="mc-tbody-retenciones">
                      <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                          <i class="bi bi-file-earmark-text d-block fs-3 mb-2"></i>
                          No hay retenciones registradas para esta compra
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- PESTAÑA: INVENTARIO -->
          <div class="tab-pane fade" id="tabInventario" role="tabpanel">
            <div class="p-3">

              <!-- Contenedor de movimientos ya procesados -->
              <div id="mc-inventario-procesado" class="mb-3 d-none">
                <div class="card border-info bg-info bg-opacity-10 shadow-sm overflow-hidden">
                  <div class="card-header bg-info bg-opacity-25 py-2 border-0">
                    <h6 class="mb-0 text-info fw-bold small"><i class="bi bi-info-circle-fill me-2"></i>Movimientos procesados en inventario</h6>
                  </div>
                  <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 150px;">
                      <table class="table table-sm table-borderless mb-0 x-small bg-white">
                        <thead class="bg-light border-bottom">
                          <tr>
                            <th class="ps-3 py-1 text-muted">Producto</th>
                            <th class="py-1 text-muted">Bodega</th>
                            <th class="py-1 text-muted text-center">Cant.</th>
                            <th class="py-1 text-muted text-center">Fecha</th>
                            <th class="py-1 text-muted">Obs.</th>
                          </tr>
                        </thead>
                        <tbody id="mc-tbody-inventario-procesado"></tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

              <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                <div class="table-responsive" style="max-height: 400px;">
                  <table class="table table-sm table-hover mb-0">
                    <thead>
                      <tr class="table-light border-bottom">
                        <th class="ps-3 py-2 small fw-bold text-muted">Producto / Descripción</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 100px;">Medida</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 120px;">Bodega</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 100px;">Cantidad</th>
                        <th class="py-2 small fw-bold text-muted text-end" style="width: 110px;">Costo Unit.</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 120px;">Lote</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 120px;">NUP / Serial</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 130px;">Caducidad</th>
                        <th class="py-2 small fw-bold text-muted text-center" style="width: 50px;"><i class="bi bi-check2-all"></i></th>
                      </tr>
                    </thead>
                    <tbody id="mc-tbody-inventario">
                      <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                          <i class="bi bi-box-seam d-block fs-3 mb-2"></i>
                          Agregue productos a la compra para verlos aquí
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                  <div class="small text-muted ms-2">
                    <span id="mc-inventario-count">0</span> productos listos para procesar
                  </div>
                  <button type="button" class="btn btn-primary btn-sm px-3" id="btnProcesarInventario" onclick="mcProcesarInventario()">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Procesar Entradas
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- ════════════════════════════════════════
               TAB 5: INFORMACIÓN (Placeholder)
               ════════════════════════════════════════ -->
          <div class="tab-pane fade" id="tabInformacion" role="tabpanel">
            <div class="p-3">
              <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                <i class="bi bi-info-circle fs-1 mb-3 text-info opacity-50"></i>
                <h6 class="fw-semibold">Auditoría e Información</h6>
                <p class="text-center small">La información de auditoría y estados se mostrará aquí.</p>
              </div>
            </div>
          </div>

        </div><!-- /tab-content -->
      </div><!-- /modal-body -->

      <div class="modal-footer justify-content-between bg-light border-top p-2">
        <div>
          <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarCompra" onclick="CMG_eliminar()">
            <i class="bi bi-trash3 me-1"></i> Eliminar
          </button>
        </div>
        <div>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            <i class="fa-solid fa-xmark me-1"></i>Cerrar
          </button>
          <button type="button" class="btn btn-primary px-4 btn-sm d-none" id="btnGuardarCompra" onclick="CMG_guardar()">
            <i class="bi bi-check2-circle me-1"></i> Guardar
          </button>
        </div>
      </div>

    </div><!-- /modal-content -->
  </div><!-- /modal-dialog -->
</div><!-- /modal -->
<!-- /MODAL COMPRAS -->