<!-- ===== MODAL IMPORTACIONES ===== -->
<div class="modal fade modal-factura" id="modalImportacion" tabindex="-1" aria-labelledby="modalImportacionLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content cmg-favoritos-card" data-modulo="importaciones">

      <div class="modal-header py-2 px-3">
        <h5 class="modal-title fs-6 fw-bold" id="modalImportacionLabel"><i class="bi bi-globe-americas me-2 text-primary"></i><span id="impTitulo">Nueva Importación</span></h5>
        <span id="impEstadoBadge" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2 d-none">Borrador</span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-0">
        <input type="hidden" id="impId" name="id">

        <!-- Pestañas -->
        <div class="d-flex align-items-center bg-light px-3 pt-2">
          <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="impTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" id="imp-tab-general" data-bs-toggle="tab" href="#impTabGeneral" role="tab"><i class="bi bi-card-list me-1"></i>Datos generales</a></li>
            <li class="nav-item"><a class="nav-link" id="imp-tab-productos" data-bs-toggle="tab" href="#impTabProductos" role="tab"><i class="bi bi-box-seam me-1"></i>Productos (FOB)</a></li>
            <li class="nav-item"><a class="nav-link" id="imp-tab-facturas" data-bs-toggle="tab" href="#impTabFacturas" role="tab"><i class="bi bi-receipt me-1"></i>Facturas del exterior</a></li>
            <li class="nav-item"><a class="nav-link" id="imp-tab-gastos" data-bs-toggle="tab" href="#impTabGastos" role="tab"><i class="bi bi-cash-coin me-1"></i>Gastos de nacionalización</a></li>
            <li class="nav-item"><a class="nav-link" id="imp-tab-prorrateo" data-bs-toggle="tab" href="#impTabProrrateo" role="tab"><i class="bi bi-pie-chart me-1"></i>Prorrateo / Resumen</a></li>
            <li class="nav-item"><a class="nav-link" id="imp-tab-asiento" data-bs-toggle="tab" href="#impTabAsiento" role="tab"><i class="bi bi-calculator me-1"></i>Asiento contable</a></li>
          </ul>
          <div class="ms-auto pb-1">
            <?php
            $pestanasConfigImp = [
              'impTabProductos'  => 'Productos (FOB)',
              'impTabFacturas'   => 'Facturas del exterior',
              'impTabGastos'     => 'Gastos de nacionalización',
              'impTabProrrateo'  => 'Prorrateo / Resumen',
              'impTabAsiento'    => 'Asiento contable',
            ];
            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigImp, $vistaConfig ?? [], 'modulos/importaciones');
            ?>
          </div>
        </div>
        <div class="border-bottom bg-light mb-0"></div>

        <div class="tab-content border-top px-3 py-3" id="impTabContent">

          <!-- ════════════════════════════════════════
               TAB 1: DATOS GENERALES
          ════════════════════════════════════════ -->
          <div class="tab-pane fade show active" id="impTabGeneral" role="tabpanel">
            <!-- Fila 1: Serie, N° Importación, Referencia DAI, Incoterm, Bodega destino -->
            <div class="row g-2">
              <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1 fw-semibold">Serie <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="impPuntoEmision" onchange="window.IMP_syncSecuencial(this.value)">
                  <option value="">-- Seleccione --</option>
                  <?php foreach (($puntos ?? []) as $p): ?>
                    <option value="<?= $p['id'] ?>"
                        data-id-est="<?= $p['id_establecimiento'] ?? $sucursal_principal['id'] ?? '' ?>"
                        data-est="<?= $p['cod_establecimiento'] ?>"
                        data-punto="<?= $p['codigo_punto'] ?>">
                        <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1 fw-semibold">N° Importación</label>
                <input type="text" id="impSecuencial" class="form-control form-control-sm bg-light" readonly placeholder="000000001">
              </div>

              <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">Referencia DAI</label>
                <input type="text" id="impReferenciaDai" class="form-control form-control-sm" placeholder="Nº de la declaración aduanera">
              </div>

              <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1 fw-semibold">Incoterm</label>
                <input type="text" id="impIncoterm" class="form-control form-control-sm text-uppercase" list="impIncotermList" maxlength="10" placeholder="FOB, CIF...">
                <datalist id="impIncotermList">
                  <option value="FOB"><option value="CFR"><option value="CIF"><option value="EXW">
                  <option value="FCA"><option value="DAP"><option value="DDP">
                </datalist>
              </div>

              <div class="col-12 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">Bodega destino <span class="text-danger">*</span></label>
                <select id="impIdBodegaDestino" class="form-select form-select-sm">
                  <option value="">-- Seleccione --</option>
                  <?php foreach (($bodegas ?? []) as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Fila 2: Proveedor del exterior + Agente afianzado (100% entre los dos) -->
            <div class="row g-2 mt-1">
              <div class="col-12 col-md-6">
                <label class="form-label form-label-sm mb-1 fw-semibold">Proveedor del exterior <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="text" id="impBuscarProveedor" class="form-control form-control-sm" placeholder="Buscar por nombre o identificación..." autocomplete="off">
                  <input type="hidden" id="impIdProveedor">
                  <div id="impListaProveedores" class="list-group shadow-sm d-none" style="max-height:200px;overflow-y:auto;"></div>
                </div>
                <div class="form-text x-small">Debe tener identificación tipo "Exterior" (sin RUC ecuatoriano).</div>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label form-label-sm mb-1 fw-semibold">Agente afianzado (opcional)</label>
                <div class="position-relative">
                  <input type="text" id="impBuscarAgente" class="form-control form-control-sm" placeholder="Buscar proveedor local..." autocomplete="off">
                  <input type="hidden" id="impIdAgente">
                  <div id="impListaAgentes" class="list-group shadow-sm d-none" style="max-height:200px;overflow-y:auto;"></div>
                </div>
              </div>
            </div>

            <!-- Fila 3: Criterio de prorrateo, Fecha embarque, Fecha llegada, Fecha nacionalización -->
            <div class="row g-2 mt-1">
              <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">Criterio de prorrateo</label>
                <select id="impCriterioProrrateo" class="form-select form-select-sm">
                  <option value="fob">Por valor FOB</option>
                  <option value="peso">Por peso (Kg)</option>
                  <option value="volumen">Por volumen (m3)</option>
                  <option value="cantidad">Por cantidad</option>
                </select>
              </div>

              <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">Fecha embarque</label>
                <input type="date" id="impFechaEmbarque" class="form-control form-control-sm">
              </div>

              <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">Fecha llegada</label>
                <input type="date" id="impFechaLlegada" class="form-control form-control-sm">
              </div>

              <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1 fw-semibold">Fecha nacionalización</label>
                <input type="date" id="impFechaNacionalizacion" class="form-control form-control-sm">
                <div class="form-text x-small">Se actualiza automáticamente al procesar el inventario.</div>
              </div>
            </div>

            <!-- Fila 4: Observaciones (100%) -->
            <div class="row g-2 mt-1">
              <div class="col-12">
                <label class="form-label form-label-sm mb-1 fw-semibold">Observaciones</label>
                <textarea id="impObservaciones" class="form-control form-control-sm" rows="2" placeholder="Opcional"></textarea>
              </div>
            </div>

            <!-- Totales de solo lectura -->
            <div class="row g-2 mt-3">
              <div class="col-12">
                <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.8rem;">
                  <div class="row text-center g-2">
                    <div class="col-6 col-md-2">
                      <div class="text-muted x-small">Subtotal FOB</div>
                      <div class="fw-bold" id="impLblSubtotalFob">0.00</div>
                    </div>
                    <div class="col-6 col-md-2">
                      <div class="text-muted x-small">Gastos capitalizables</div>
                      <div class="fw-bold" id="impLblGastosCap">0.00</div>
                    </div>
                    <div class="col-6 col-md-2">
                      <div class="text-muted x-small">IVA</div>
                      <div class="fw-bold" id="impLblIva">0.00</div>
                    </div>
                    <div class="col-6 col-md-2">
                      <div class="text-muted x-small">ISD</div>
                      <div class="fw-bold" id="impLblIsd">0.00</div>
                    </div>
                    <div class="col-6 col-md-2">
                      <div class="text-muted x-small">Otros gastos</div>
                      <div class="fw-bold" id="impLblOtros">0.00</div>
                    </div>
                    <div class="col-6 col-md-2">
                      <div class="text-muted x-small">Costo total nacionalizado</div>
                      <div class="fw-bold text-primary" id="impLblCostoTotal" style="font-size:1rem;">0.00</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ════════════════════════════════════════
               TAB 2: PRODUCTOS (FOB)
          ════════════════════════════════════════ -->
          <div class="tab-pane fade" id="impTabProductos" role="tabpanel">
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
              <div class="table-responsive" style="max-height: 400px;">
                <table class="table table-sm table-detalle mb-0 text-nowrap">
                  <thead>
                    <tr class="table-light border-bottom">
                      <th class="ps-3 py-2 small fw-bold text-muted" style="width:20%;">Producto / Descripción</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:8%;">Cant.</th>
                      <th class="py-2 small fw-bold text-muted text-end" style="width:9%;">P. Unit. FOB</th>
                      <th class="py-2 small fw-bold text-muted text-end" style="width:9%;">Total FOB</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:7%;">Peso Kg</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:7%;">Vol. m3</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">Lote</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:10%;">Caducidad</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">NUP</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:10%;">Bodega</th>
                      <th class="py-2 small fw-bold text-muted text-end imp-col-nacionalizado d-none" style="width:10%;">Costo Unit. Nac.</th>
                      <th class="py-2 small fw-bold text-muted text-end imp-col-nacionalizado d-none" style="width:10%;">Costo Total Nac.</th>
                      <th style="width:40px;"></th>
                    </tr>
                  </thead>
                  <tbody id="tbodyProductosFob"></tbody>
                </table>
              </div>
              <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex gap-2 align-items-center">
                  <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="IMP_agregarLineaProductoVacia()">
                    <i class="bi bi-plus-circle me-1"></i> Agregar línea
                  </button>
                  <div class="vr mx-1"></div>
                  <input type="file" id="impFileExcelProductos" accept=".xlsx,.xls,.csv" class="d-none">
                  <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="document.getElementById('impFileExcelProductos').click()" title="Cargar varias líneas desde un archivo Excel o CSV">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Cargar desde Excel/CSV
                  </button>
                  <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold text-primary" onclick="IMP_descargarPlantillaExcel()" title="Descargar plantilla de ejemplo con los productos de la empresa">
                    <i class="bi bi-download me-1"></i> Plantilla
                  </button>
                  <div class="vr mx-1"></div>
                  <div class="position-relative" style="width:250px">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                      <input type="text" id="inputBuscarProductoImp" class="form-control border-0 px-1" placeholder="Buscar producto en catálogo..." autocomplete="off">
                    </div>
                    <div id="listaProductosImp" class="list-group shadow dropdown-predictivo d-none" style="max-height: 250px; overflow-y: auto;"></div>
                  </div>
                </div>
                <div class="small fw-bold text-muted pe-3">
                  Líneas: <span id="impCountProductos">0</span>
                </div>
              </div>
            </div>
            <div class="form-text x-small mt-2">Peso (Kg) es obligatorio en todas las líneas si el criterio de prorrateo es "por peso"; volumen (m3) es obligatorio si el criterio es "por volumen". El Excel debe tener las columnas <code>codigo_producto, descripcion, cantidad, precio_unitario_fob, peso_kg, volumen_m3, numero_lote, fecha_caducidad, nup</code> (descargue la plantilla para verlas con ejemplos y la lista de códigos válidos).</div>
          </div>

          <!-- ════════════════════════════════════════
               TAB 3: FACTURAS DEL EXTERIOR
          ════════════════════════════════════════ -->
          <div class="tab-pane fade" id="impTabFacturas" role="tabpanel">
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
              <div class="table-responsive" style="max-height: 350px;">
                <table class="table table-sm table-detalle mb-0 text-nowrap">
                  <thead>
                    <tr class="table-light border-bottom">
                      <th class="ps-3 py-2 small fw-bold text-muted" style="width:28%;">Proveedor del exterior</th>
                      <th class="py-2 small fw-bold text-muted" style="width:18%;">N° Factura</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:14%;">Fecha</th>
                      <th class="py-2 small fw-bold text-muted text-end" style="width:12%;">Monto USD</th>
                      <th class="py-2 small fw-bold text-muted" style="width:16%;">Forma de pago</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:10%;">Plazo (días)</th>
                      <th style="width:40px;"></th>
                    </tr>
                  </thead>
                  <tbody id="tbodyFacturasExterior"></tbody>
                </table>
              </div>
              <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="IMP_agregarFilaFactura()">
                  <i class="bi bi-plus-circle me-1"></i> Agregar factura
                </button>
                <div class="small fw-bold text-muted pe-3">
                  Total facturado: $<span id="impTotalFacturasExterior">0.00</span>
                </div>
              </div>
            </div>
          </div>

          <!-- ════════════════════════════════════════
               TAB 4: GASTOS DE NACIONALIZACIÓN
          ════════════════════════════════════════ -->
          <div class="tab-pane fade" id="impTabGastos" role="tabpanel">
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
              <div class="table-responsive" style="max-height: 400px;">
                <table class="table table-sm table-detalle mb-0 text-nowrap">
                  <thead>
                    <tr class="table-light border-bottom">
                      <th class="ps-3 py-2 small fw-bold text-muted" style="width:14%;">Origen</th>
                      <th class="py-2 small fw-bold text-muted" style="width:15%;">Tipo de gasto</th>
                      <th class="py-2 small fw-bold text-muted" style="width:27%;">Descripción / Documento vinculado</th>
                      <th class="py-2 small fw-bold text-muted text-end" style="width:12%;">Monto</th>
                      <th class="py-2 small fw-bold text-muted text-center" style="width:10%;">Prorrateable</th>
                      <th style="width:40px;"></th>
                    </tr>
                  </thead>
                  <tbody id="tbodyGastosImp"></tbody>
                </table>
              </div>
              <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="IMP_agregarFilaGasto()">
                  <i class="bi bi-plus-circle me-1"></i> Agregar gasto
                </button>
                <div class="small fw-bold text-muted pe-3">
                  Total gastos: $<span id="impTotalGastos">0.00</span>
                </div>
              </div>
            </div>
            <div class="form-text x-small mt-2">IVA de importación e ISD nunca se capitalizan (no forman parte del costo del inventario); el resto de tipos de gasto sí, salvo que se desmarque "Prorrateable".</div>
          </div>

          <!-- ════════════════════════════════════════
               TAB 5: PRORRATEO / RESUMEN
          ════════════════════════════════════════ -->
          <div class="tab-pane fade" id="impTabProrrateo" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <button type="button" class="btn btn-outline-primary btn-sm" id="impBtnCalcularProrrateo" onclick="IMP_calcularProrrateo()">
                <i class="bi bi-calculator me-1"></i> Calcular prorrateo
              </button>
              <button type="button" class="btn btn-success btn-sm px-3 d-none" id="impBtnProcesarInventario" onclick="IMP_procesarInventario()">
                <i class="bi bi-box-arrow-in-right me-1"></i> Procesar Inventario / Nacionalizar
              </button>
              <div class="d-none gap-2" id="impGrupoAprobacion">
                <button type="button" class="btn btn-success btn-sm px-3" id="impBtnAprobar" onclick="IMP_aprobarNacionalizacion()">
                  <i class="bi bi-check-circle me-1"></i> Aprobar nacionalización
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm px-3" id="impBtnRechazar" onclick="IMP_rechazarNacionalizacion()">
                  <i class="bi bi-x-circle me-1"></i> Rechazar
                </button>
              </div>
            </div>
            <div class="alert alert-info py-2 px-3 small d-none mb-3" id="impAlertPendienteAprobacion">
              <i class="bi bi-hourglass-split me-1"></i> Esta importación está <strong>pendiente de aprobación</strong> antes de nacionalizarse. No se puede editar mientras tanto.
            </div>

            <div class="border rounded-3 overflow-hidden bg-white shadow-sm mb-3">
              <div class="table-responsive" style="max-height: 300px;">
                <table class="table table-sm table-hover mb-0">
                  <thead>
                    <tr class="table-light border-bottom">
                      <th class="ps-3 py-2 small fw-bold text-muted">Producto</th>
                      <th class="py-2 small fw-bold text-muted text-center">Cantidad</th>
                      <th class="py-2 small fw-bold text-muted text-end">Costo Unit. Nacionalizado</th>
                      <th class="py-2 small fw-bold text-muted text-end pe-3">Costo Total Nacionalizado</th>
                    </tr>
                  </thead>
                  <tbody id="impProrrateoBody">
                    <tr><td colspan="4" class="text-center py-4 text-muted">Guarda la importación y presiona "Calcular prorrateo" para ver la vista previa.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.8rem;">
              <div class="row text-center g-2">
                <div class="col-6 col-md-2">
                  <div class="text-muted x-small">Total factura exterior</div>
                  <div class="fw-bold" id="impProrrateoTotalFactura">0.00</div>
                </div>
                <div class="col-6 col-md-2">
                  <div class="text-muted x-small">Capitalizable manual</div>
                  <div class="fw-bold" id="impProrrateoCapManual">0.00</div>
                </div>
                <div class="col-6 col-md-2">
                  <div class="text-muted x-small">Capitalizable vinculado</div>
                  <div class="fw-bold" id="impProrrateoCapVinc">0.00</div>
                </div>
                <div class="col-6 col-md-2">
                  <div class="text-muted x-small">IVA / ISD</div>
                  <div class="fw-bold" id="impProrrateoIvaIsd">0.00</div>
                </div>
                <div class="col-6 col-md-2">
                  <div class="text-muted x-small">Otros gastos</div>
                  <div class="fw-bold" id="impProrrateoOtros">0.00</div>
                </div>
                <div class="col-6 col-md-2">
                  <div class="text-muted x-small">Costo total nacionalizado</div>
                  <div class="fw-bold text-primary" id="impProrrateoCostoTotal" style="font-size:1rem;">0.00</div>
                </div>
              </div>
            </div>
            <div class="px-1 pt-2 small text-muted" id="impProrrateoStatus"></div>
          </div>

          <!-- ════════════════════════════════════════
               TAB 6: ASIENTO CONTABLE
          ════════════════════════════════════════ -->
          <div class="tab-pane fade" id="impTabAsiento" role="tabpanel">
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
              <div class="table-responsive" style="max-height: 350px;">
                <table class="table table-sm table-detalle mb-0 text-nowrap" id="imp-table-asiento">
                  <thead>
                    <tr class="table-light border-bottom">
                      <th class="ps-3 py-2 small fw-bold text-muted" style="width:45%;">Cuenta Contable</th>
                      <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">D&eacute;bito / Debe</th>
                      <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Cr&eacute;dito / Haber</th>
                      <th class="py-2 small fw-bold text-muted" style="width:15%;">Referencia</th>
                      <th style="width:40px;"></th>
                    </tr>
                  </thead>
                  <tbody id="imp-asiento-tbody">
                    <tr><td colspan="5" class="text-center py-4 text-muted">El asiento se genera al procesar el inventario.</td></tr>
                  </tbody>
                  <tfoot class="bg-light fw-bold border-top sticky-bottom">
                    <tr>
                      <td class="text-end py-2">Totales:</td>
                      <td class="text-end pe-3 py-2 text-primary" id="imp-asiento-debe">0.00</td>
                      <td class="text-end pe-3 py-2 text-primary" id="imp-asiento-haber">0.00</td>
                      <td colspan="2" class="py-2">
                        <div class="d-flex align-items-center gap-2 justify-content-end pe-3">
                          <span class="x-small text-muted">Diferencia: <span id="imp-asiento-dif">0.00</span></span>
                          <span id="imp-asiento-badge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Cuadrado</span>
                        </div>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
            <div class="px-1 pt-2 small text-muted" id="imp-asiento-status"></div>
          </div>

        </div><!-- /tab-content -->
      </div><!-- /modal-body -->

      <div class="modal-footer justify-content-between bg-light border-top p-2">
        <div>
          <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarImportacion" onclick="eliminarImportacion()">
            <i class="bi bi-trash3 me-1"></i> Eliminar
          </button>
        </div>
        <div>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            <i class="fa-solid fa-xmark me-1"></i>Cerrar
          </button>
          <button type="button" class="btn btn-primary px-4 btn-sm" id="btnGuardarImportacion" onclick="guardarImportacion()">
            <i class="bi bi-check2-circle me-1"></i> Guardar
          </button>
        </div>
      </div>

    </div><!-- /modal-content -->
  </div><!-- /modal-dialog -->
</div><!-- /modal -->
<!-- /MODAL IMPORTACIONES -->
