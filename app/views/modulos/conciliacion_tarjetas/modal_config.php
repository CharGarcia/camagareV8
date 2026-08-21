<?php
/**
 * Configuración del módulo, en dos pestañas:
 *   • Contabilidad: cuentas de comisión, IVA y retenciones por procesadora, más
 *     los valores por defecto (días de liquidación, tolerancia).
 *   • Perfiles: cómo leer el estado de cuenta de cada procesadora/banco.
 *
 * La cuenta puente NO se configura aquí: es la cuenta contable de la propia
 * forma de cobro (Formas de Cobro/Pago), que es la que usa el asiento del cobro.
 */
?>
<div class="modal fade" id="modalConfigTarjetas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-gear me-2"></i>Configuración de Conciliación de Tarjetas</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <ul class="nav nav-tabs px-3 pt-2" id="ctar-config-tabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ctar-tab-contabilidad" type="button">
                            <i class="bi bi-calculator me-1"></i>Contabilidad
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ctar-tab-perfiles" type="button">
                            <i class="bi bi-filetype-csv me-1"></i>Perfiles de lectura
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-3">
                    <!-- ── Contabilidad ── -->
                    <div class="tab-pane fade show active" id="ctar-tab-contabilidad">
                        <div class="alert alert-info py-2 px-3 small">
                            <i class="bi bi-info-circle me-1"></i>
                            La contabilidad es opcional: si no configura cuentas, el módulo concilia igual
                            pero no genera el asiento del depósito.
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Procesadora</label>
                            <select id="ctar-cfg-procesadora" class="form-select form-select-sm shadow-none border"
                                    onchange="CTAR_cargarConfig()"></select>
                        </div>

                        <!-- Estado de la cuenta puente de esta procesadora -->
                        <div class="p-2 border rounded-3 mb-3" id="ctar-cfg-puente">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:.65rem;">Cuenta puente (viene de Formas de Cobro/Pago)</div>
                            <div id="ctar-cfg-puente-texto" class="small">—</div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">Cuenta de comisión (gasto)</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                           id="ctar-cfg-comision-txt" data-target="ctar-cfg-comision" placeholder="Buscar cuenta..." autocomplete="off">
                                    <input type="hidden" id="ctar-cfg-comision">
                                    <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                         style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">Cuenta de IVA de la comisión</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                           id="ctar-cfg-iva-txt" data-target="ctar-cfg-iva" placeholder="Buscar cuenta..." autocomplete="off">
                                    <input type="hidden" id="ctar-cfg-iva">
                                    <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                         style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">Cuenta de retención de renta</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                           id="ctar-cfg-retir-txt" data-target="ctar-cfg-retir" placeholder="Buscar cuenta..." autocomplete="off">
                                    <input type="hidden" id="ctar-cfg-retir">
                                    <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                         style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">Cuenta de retención de IVA</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                           id="ctar-cfg-retiva-txt" data-target="ctar-cfg-retiva" placeholder="Buscar cuenta..." autocomplete="off">
                                    <input type="hidden" id="ctar-cfg-retiva">
                                    <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                         style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold mb-1">% comisión</label>
                                <input type="number" step="0.0001" id="ctar-cfg-pc" class="form-control form-control-sm shadow-none border text-end">
                                <div class="form-text" style="font-size:.68rem;">Solo para precalcular; siempre editable.</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold mb-1">% IVA</label>
                                <input type="number" step="0.0001" id="ctar-cfg-pi" class="form-control form-control-sm shadow-none border text-end">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold mb-1">Días de liquidación</label>
                                <input type="number" id="ctar-cfg-dias" class="form-control form-control-sm shadow-none border text-end" value="2">
                                <div class="form-text" style="font-size:.68rem;">Pasados estos días, el cobro se marca atrasado.</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold mb-1">Tolerancia</label>
                                <input type="number" step="0.01" id="ctar-cfg-tol" class="form-control form-control-sm shadow-none border text-end" value="0.05">
                                <div class="form-text" style="font-size:.68rem;">Descuadre aceptado al cerrar.</div>
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary btn-sm" onclick="CTAR_guardarConfig()">
                                <i class="bi bi-save me-1"></i>Guardar configuración
                            </button>
                        </div>
                    </div>

                    <!-- ── Perfiles ── -->
                    <div class="tab-pane fade" id="ctar-tab-perfiles">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="small text-muted">
                                Cada procesadora y banco entrega el archivo a su manera: un perfil dice dónde está cada dato.
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="CTAR_nuevoPerfil()">
                                <i class="bi bi-plus-lg me-1"></i>Nuevo perfil
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Perfil</th>
                                        <th>Procesadora</th>
                                        <th class="text-center">Archivo</th>
                                        <th class="text-center">Contenido</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="ctar-tbody-perfiles">
                                    <tr><td colspan="5" class="text-center py-3 text-muted small">Sin perfiles todavía.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Editor de perfil -->
                        <div class="border rounded-3 p-3 mt-3 d-none" id="ctar-perfil-editor">
                            <input type="hidden" id="ctar-perfil-id">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold mb-1">Nombre del perfil</label>
                                    <input type="text" id="ctar-perfil-nombre" class="form-control form-control-sm shadow-none border"
                                           placeholder="Ej: Payphone — reporte mensual" maxlength="100">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Procesadora</label>
                                    <select id="ctar-perfil-forma" class="form-select form-select-sm shadow-none border"></select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Archivo</label>
                                    <select id="ctar-perfil-tipo" class="form-select form-select-sm shadow-none border" onchange="CTAR_perfilTipoCambio()">
                                        <option value="EXCEL">Excel</option>
                                        <option value="CSV">CSV</option>
                                        <option value="PDF">PDF</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Contenido</label>
                                    <select id="ctar-perfil-nivel" class="form-select form-select-sm shadow-none border">
                                        <option value="transaccion">Una línea por transacción</option>
                                        <option value="deposito">Depósitos consolidados</option>
                                    </select>
                                </div>

                                <div class="col-6 col-md-2">
                                    <label class="form-label small fw-bold mb-1">Fila de inicio</label>
                                    <input type="number" id="ctar-perfil-fila" class="form-control form-control-sm shadow-none border" value="1">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small fw-bold mb-1">Formato fecha</label>
                                    <input type="text" id="ctar-perfil-fecha" class="form-control form-control-sm shadow-none border" value="d/m/Y">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small fw-bold mb-1">Separador decimal</label>
                                    <select id="ctar-perfil-separador" class="form-select form-select-sm shadow-none border">
                                        <option value=".">Punto (1234.56)</option>
                                        <option value=",">Coma (1234,56)</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-6">
                                    <label class="form-label small fw-bold mb-1">Archivo de muestra</label>
                                    <input type="file" id="ctar-perfil-muestra" class="form-control form-control-sm shadow-none border"
                                           accept=".xls,.xlsx,.csv,.pdf" onchange="CTAR_previsualizarMuestra()">
                                </div>
                            </div>

                            <!-- Mapeo Excel/CSV -->
                            <div id="ctar-perfil-mapeo-excel" class="mt-3">
                                <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size:.65rem;">
                                    ¿En qué columna está cada dato? (deje vacío lo que el archivo no traiga)
                                </div>
                                <div class="row g-2" id="ctar-perfil-campos"></div>
                            </div>

                            <!-- Mapeo PDF -->
                            <div id="ctar-perfil-mapeo-pdf" class="mt-3 d-none">
                                <label class="form-label small fw-bold mb-1">Patrón de línea (regex con grupos nombrados)</label>
                                <input type="text" id="ctar-perfil-regex" class="form-control form-control-sm shadow-none border font-monospace"
                                       placeholder="/(?<fecha>\d{2}\/\d{2}\/\d{4}).+?(?<autorizacion>\d+).+?(?<monto_bruto>[\d.,]+)/">
                                <div class="form-text" style="font-size:.7rem;">
                                    Debe incluir al menos <code>(?&lt;fecha&gt;…)</code> y <code>(?&lt;monto_bruto&gt;…)</code>.
                                </div>
                            </div>

                            <!-- Vista previa -->
                            <div class="mt-3 d-none" id="ctar-perfil-preview-wrap">
                                <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:.65rem;">Vista previa del archivo</div>
                                <div class="border rounded-3" style="max-height:220px; overflow:auto;">
                                    <table class="table table-sm table-bordered mb-0" style="font-size:.72rem;">
                                        <tbody id="ctar-perfil-preview"></tbody>
                                    </table>
                                </div>
                                <div class="mt-2 d-none" id="ctar-perfil-probado-wrap">
                                    <div class="small fw-bold text-success mb-1"><i class="bi bi-check2 me-1"></i>Así quedarían las líneas con este mapeo:</div>
                                    <div class="border rounded-3" style="max-height:180px; overflow:auto;">
                                        <table class="table table-sm mb-0" style="font-size:.72rem;">
                                            <thead class="table-light"><tr>
                                                <th>Fecha</th><th>Autorización</th><th class="text-end">Bruto</th>
                                                <th class="text-end">Comisión</th><th class="text-end">Neto</th>
                                            </tr></thead>
                                            <tbody id="ctar-perfil-probado"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="CTAR_cancelarPerfil()">Cancelar</button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="CTAR_previsualizarMuestra()">
                                    <i class="bi bi-eye me-1"></i>Probar mapeo
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="CTAR_guardarPerfil()">
                                    <i class="bi bi-save me-1"></i>Guardar perfil
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
