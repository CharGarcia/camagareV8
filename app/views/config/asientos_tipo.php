<?php

/**
 * Vista de Configuración General de Asientos Tipo (Modelos Predefinidos)
 * Accesible únicamente por nivel 2 (Administrador) o superior.
 */
$base = BASE_URL;
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-sliders me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Configuración de modelos de asientos tipos predefinidos para la resolución automática de cuentas contables.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="ASIENTOTIPO_nuevo()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    </div>
</div>

<!-- Tarjeta principal estándar de tablas (cmg-table-card) -->
<div class="card cmg-table-card border-0 shadow-sm rounded-3 bg-white mb-4">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form id="asiento-tipo-form-buscar" class="input-group input-group-sm" style="width:280px" onsubmit="event.preventDefault(); ASIENTOTIPO_cambiarPagina(1);">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="asientoTipoInputBuscar" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar por código, referencia..." autocomplete="off">
            </form>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf text-danger"></i></button>
                <button type="button" class="btn btn-outline-secondary" title="Exportar a Excel"><i class="bi bi-file-earmark-excel text-success"></i></button>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="asientoTipoPaginationInfo" class="text-muted small fw-medium">0-0 / 0</span>
            <div id="asientoTipoWrapperPagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div style="max-height: 550px; overflow-y: auto;">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm sticky-top" style="z-index: 1;">
                    <tr>
                        <th class="ps-3" style="width: 10%;">Código</th>
                        <th style="width: 18%;">Concepto / Tipo de Asiento</th>
                        <th style="width: 18%;">Referencia</th>
                        <th class="text-center" style="width: 12%;">Naturaleza</th>
                        <th style="width: 12%;">Tipo Cuenta</th>
                        <th style="width: 25%;">Detalle Configuración / Instrucciones</th>
                        <th class="text-center" style="width: 5%;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyAsientosTipo">
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <span class="spinner-border spinner-border-sm text-primary me-2"></span> Cargando asientos tipo...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Formulario de Asiento Tipo (Crear/Editar) -->
<div class="modal fade" id="modalAsientoTipoForm" tabindex="-1" aria-labelledby="modalAsientoTipoFormLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold mb-0">
                    <i class="bi bi-sliders me-2 text-primary"></i> <span id="modalAsientoTipoFormLabel">Registrar Asiento Tipo</span>
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="formAsientoTipo" onsubmit="ASIENTOTIPO_guardar(event)">
                <input type="hidden" id="asientoTipoId" name="id" value="0">

                <div class="modal-body px-4 py-3 bg-white">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="asientoTipoSelect" class="form-label small fw-medium mb-1">Tipo de Asiento Contable <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="asientoTipoSelect" name="tipo_asiento" onchange="ASIENTOTIPO_onSelectChange()" required>
                                <option value="" disabled selected>-- Seleccione Tipo --</option>
                                <option value="ventas_factura">Ventas con Factura</option>
                                <option value="ventas_recibo">Ventas con Recibo</option>
                                <option value="adquisiciones_compras">Adquisiciones de Compras/Servicios</option>
                                <option value="retenciones_venta">Retenciones en Venta</option>
                                <option value="retenciones_compra">Retenciones en Compra</option>
                                <option value="ingresos_egresos">Ingresos y Egresos</option>
                                <option value="cobros_pagos">Cobros y Pagos</option>
                                <option value="nomina">Nómina</option>
                                <option value="__nuevo__" class="fw-bold text-success">+ Crear Nuevo Concepto...</option>
                            </select>
                        </div>

                        <div class="col-12" id="asientoTipoNuevoContenedor" style="display: none;">
                            <label for="asientoTipoNuevo" class="form-label small fw-medium mb-1 text-success"><i class="bi bi-plus-circle-fill me-1"></i> Nombre del Nuevo Concepto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm border border-success" id="asientoTipoNuevo" placeholder="Ej: Servicios Básicos o Gastos de Oficina" autocomplete="off">
                            <div class="form-text small text-success" style="font-size: 0.7rem;">Se guardará automáticamente como un identificador válido (ej: servicios_basicos).</div>
                        </div>

                        <div class="col-12">
                            <label for="asientoTipoCodigo" class="form-label small fw-medium mb-1">Código Identificador (Único) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="asientoTipoCodigo" name="codigo" placeholder="Ej: CCXCC" required autocomplete="off">
                            <div class="form-text text-muted" style="font-size: 0.7rem;">Este código sirve para identificar la cuenta de forma dinámica. Solo letras mayúsculas, números o guiones bajos.</div>
                        </div>

                        <div class="col-12">
                            <label for="asientoTipoReferencia" class="form-label small fw-medium mb-1">Referencia (Cuenta) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="asientoTipoReferencia" name="referencia" placeholder="Ej: Cuenta por cobrar" required autocomplete="off">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-medium mb-1">Efecto / Naturaleza Contable <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group" aria-label="Efecto contable">
                                <input type="radio" class="btn-check" name="debe_haber" id="eh_debe" value="debe" checked autocomplete="off">
                                <label class="btn btn-outline-success btn-sm w-50 py-2 fw-bold shadow-none" for="eh_debe"><i class="bi bi-plus-circle me-1"></i> DEBE (Débito)</label>

                                <input type="radio" class="btn-check" name="debe_haber" id="eh_haber" value="haber" autocomplete="off">
                                <label class="btn btn-outline-primary btn-sm w-50 py-2 fw-bold shadow-none" for="eh_haber"><i class="bi bi-dash-circle me-1"></i> HABER (Crédito)</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-medium mb-2">Tipos de Cuenta Permitidas</label>
                            <div class="d-flex flex-wrap gap-2 border rounded p-3 bg-light">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input tc-checkbox shadow-none" type="checkbox" id="tc_activo" value="activo">
                                    <label class="form-check-label small fw-medium" for="tc_activo">Activo</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input tc-checkbox shadow-none" type="checkbox" id="tc_pasivo" value="pasivo">
                                    <label class="form-check-label small fw-medium" for="tc_pasivo">Pasivo</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input tc-checkbox shadow-none" type="checkbox" id="tc_patrimonio" value="patrimonio">
                                    <label class="form-check-label small fw-medium" for="tc_patrimonio">Patrimonio</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input tc-checkbox shadow-none" type="checkbox" id="tc_ingreso" value="ingreso">
                                    <label class="form-check-label small fw-medium" for="tc_ingreso">Ingreso</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input tc-checkbox shadow-none" type="checkbox" id="tc_costo" value="costo">
                                    <label class="form-check-label small fw-medium" for="tc_costo">Costo</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input tc-checkbox shadow-none" type="checkbox" id="tc_gasto" value="gasto">
                                    <label class="form-check-label small fw-medium" for="tc_gasto">Gasto</label>
                                </div>
                            </div>
                            <input type="hidden" id="asientoTipoTipoCuenta" name="tipo_cuenta" value="">
                            <div class="form-text text-muted" style="font-size: 0.7rem;">Seleccione uno o más tipos de cuenta para restringir la selección contable en las empresas. Si no marca ninguno, se permitirán todos.</div>
                        </div>

                        <div class="col-12">
                            <label for="asientoTipoDetalle" class="form-label small fw-medium mb-1">Detalle Configuración / Instrucciones</label>
                            <textarea class="form-control form-control-sm" id="asientoTipoDetalle" name="detalle" rows="3" placeholder="Ej: Para configurar la cuenta por cobrar de una factura de venta..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top px-4 py-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnAsientoTipoSubmit">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Configurar la ruta de API para que use 'config' en lugar del valor por defecto
    window.BASE_URL = '<?= $base ?>';
    window.ASIENTOTIPO_ROUTE = 'config';
</script>
<script src="<?= $base ?>/js/modulos/asientos_tipo_modal.js?v=<?= time() ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar listado automáticamente al cargar la página de configuración
        if (typeof ASIENTOTIPO_cargarListado === 'function') {
            ASIENTOTIPO_cargarListado(1);
        }
    });
</script>