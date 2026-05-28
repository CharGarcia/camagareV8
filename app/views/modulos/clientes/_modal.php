<?php
$vistaConfigCliInline = \App\Helpers\PreferenciasHelper::getPreferenciasVista('clientes');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigCliInline, 'estiloVistaPestanasCliInline');
?>
<div class="modal fade" id="modalCliente" tabindex="-1" aria-labelledby="modalClienteLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $urlBaseClientes ?>/store" id="formCliente" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalClienteLabel">
                        <i class="bi bi-person-lines-fill me-2"></i><span id="tituloModalCliente">Nuevo Cliente</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pb-0">
                    <div id="modalAlert" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="cliente_id" value="">

                    <div class="d-flex align-items-center mb-3">
                    <ul class="nav nav-tabs flex-grow-1" id="tabsCliente" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-general" data-bs-toggle="tab" data-bs-target="#pane-general" type="button" role="tab"><i class="bi bi-card-text me-1"></i>General</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-contable" data-bs-toggle="tab" data-bs-target="#pane-contable" type="button" role="tab"><i class="bi bi-bank me-1"></i>Contable</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-cobros" data-bs-toggle="tab" data-bs-target="#pane-cobros" type="button" role="tab"><i class="bi bi-cash-coin me-1"></i>Cobros</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-comercial" data-bs-toggle="tab" data-bs-target="#pane-comercial" type="button" role="tab"><i class="bi bi-bar-chart-fill me-1"></i>Comercial</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-informacion" data-bs-toggle="tab" data-bs-target="#pane-informacion" type="button" role="tab"><i class="bi bi-info-circle me-1"></i>Información</button>
                        </li>
                    </ul>
                    <div class="ms-auto pb-1">
                        <?php
                        $pestanasCliInline = [
                            'pane-contable'    => 'Contable',
                            'pane-cobros'      => 'Cobros',
                            'pane-comercial'   => 'Comercial',
                            'pane-informacion' => 'Información',
                        ];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasCliInline, $vistaConfigCliInline, 'clientes');
                        ?>
                    </div>
                    </div>

                    <div class="tab-content pb-3" id="tabsClienteContent">
                        <!-- Pestaña 1: GENERAL -->
                        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                            <div class="row g-3">
                                <!-- Tipo de Identificación -->
                                <div class="col-md-4">
                                    <label for="cliente_tipo_id" class="form-label small fw-bold d-flex align-items-center">Tipo Identificación * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('clientes', 'cliente_tipo_id', 'tipo_id') ?></label>
                                    <select class="form-select form-select-sm" name="tipo_id" id="cliente_tipo_id" required>
                                        <option value="">Cargando...</option>
                                    </select>
                                </div>

                                <!-- Identificación con feedback SRI -->
                                <div class="col-md-5">
                                    <label for="cliente_identificacion" class="form-label small fw-bold d-flex justify-content-between align-items-center">
                                        <span>Identificación *</span>
                                        <span id="sriBadge" class="badge d-none"></span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" name="identificacion" id="cliente_identificacion"
                                            required maxlength="20" autocomplete="off" inputmode="numeric">
                                        <span class="input-group-text bg-white px-2 d-none" id="sriSpinnerWrap">
                                            <div class="spinner-border spinner-border-sm text-primary" id="sriSpinner" role="status">
                                                <span class="visually-hidden">Consultando SRI...</span>
                                            </div>
                                        </span>
                                    </div>
                                    <div id="identificacionError" class="invalid-feedback d-block text-danger small mt-1" style="display:none!important"></div>
                                </div>

                                <!-- Estado -->
                                <div class="col-md-3">
                                    <label for="cliente_status" class="form-label small fw-bold d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('clientes', 'cliente_status', 'status') ?></label>
                                    <select class="form-select form-select-sm" name="status" id="cliente_status">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>

                                <!-- Razón Social -->
                                <div class="col-md-12">
                                    <label for="cliente_nombre" class="form-label small fw-bold">Razón Social / Nombre *</label>
                                    <input type="text" class="form-control form-control-sm" name="nombre" id="cliente_nombre"
                                        required maxlength="150" autocomplete="off">
                                </div>

                                <!-- Correo Electrónico (multi-mail) -->
                                <div class="col-md-6">
                                    <label for="cliente_email" class="form-label small fw-bold">Correo Electrónico *
                                        <small class="text-muted fw-normal">(varios separados por coma)</small>
                                    </label>
                                    <input type="text" class="form-control form-control-sm" name="email" id="cliente_email"
                                        maxlength="255" placeholder="ej@mail.com, otro@mail.com" autocomplete="off" required>
                                    <div id="emailError" class="text-danger small mt-1" style="display:none"></div>
                                </div>

                                <!-- Teléfono -->
                                <div class="col-md-6">
                                    <label for="cliente_telefono" class="form-label small fw-bold">Teléfono</label>
                                    <input type="text" class="form-control form-control-sm" name="telefono" id="cliente_telefono" maxlength="50">
                                </div>

                                <!-- Provincia -->
                                <div class="col-md-6">
                                    <label for="cliente_provincia" class="form-label small fw-bold d-flex align-items-center">Provincia <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('clientes', 'cliente_provincia', 'provincia') ?></label>
                                    <select class="form-select form-select-sm" name="provincia" id="cliente_provincia">
                                        <option value="">�- Seleccione provincia �-</option>
                                    </select>
                                </div>

                                <!-- Ciudad -->
                                <div class="col-md-6">
                                    <label for="cliente_ciudad" class="form-label small fw-bold">Ciudad</label>
                                    <select class="form-select form-select-sm" name="ciudad" id="cliente_ciudad">
                                        <option value="">�- Seleccione ciudad �-</option>
                                    </select>
                                </div>

                                <!-- Dirección -->
                                <div class="col-md-12">
                                    <label for="cliente_direccion" class="form-label small fw-bold">Dirección Completa</label>
                                    <input type="text" class="form-control form-control-sm" name="direccion" id="cliente_direccion" maxlength="255">
                                </div>

                                <!-- Plazo & Vendedor -->
                                <div class="col-md-6">
                                    <label for="cliente_plazo" class="form-label small fw-bold">Plazo de Crédito (Días)</label>
                                    <input type="number" class="form-control form-control-sm" name="plazo" id="cliente_plazo" min="0" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_vendedor" class="form-label small fw-bold">Vendedor asignado <small class="text-muted fw-normal">(Opcional)</small></label>
                                    <div class="input-group input-group-sm">
                                        <select class="form-select" name="id_vendedor" id="cliente_vendedor">
                                            <option value="">�- Sin vendedor asignado �-</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary d-none" id="btnNuevoVendedorRapido" onclick="abrirModalVendedorRapido()" title="Crear nuevo vendedor">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña 2: CONTABLE -->
                        <div class="tab-pane fade" id="pane-contable" role="tabpanel">
                            <div class="alert alert-secondary bg-light border-0 small mb-3">
                                <i class="bi bi-info-circle me-1"></i> Asigne las cuenta contables predeterminadas para las transacciones de este cliente.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="cliente_cuenta_cobrar" class="form-label small fw-bold">Cuenta por Cobrar</label>
                                    <input type="number" class="form-control form-control-sm" name="id_cuenta_cobrar" id="cliente_cuenta_cobrar" placeholder="Ej. 110201">
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_cuenta_ingreso" class="form-label small fw-bold">Cuenta de Ingreso</label>
                                    <input type="number" class="form-control form-control-sm" name="id_cuenta_ingreso" id="cliente_cuenta_ingreso" placeholder="Ej. 410101">
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña 3: COMERCIAL -->
                        <div class="tab-pane fade" id="pane-comercial" role="tabpanel">
                            <div class="row g-3 mb-2">
                                <div class="col-6 col-md-3">
                                    <div class="card bg-light border-0 h-100">
                                        <div class="card-body p-2 text-center">
                                            <span class="d-block small text-muted mb-1">Facturas Emitidas</span>
                                            <h5 class="mb-0 text-dark fw-bold" id="stat_facturas">0</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card bg-light border-0 h-100">
                                        <div class="card-body p-2 text-center">
                                            <span class="d-block small text-muted mb-1">Total Ventas</span>
                                            <h5 class="mb-0 text-success fw-bold" id="stat_ventas">$0.00</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card bg-light border-0 h-100">
                                        <div class="card-body p-2 text-center">
                                            <span class="d-block small text-muted mb-1">Notas Crédito (NC)</span>
                                            <h5 class="mb-0 text-warning fw-bold" id="stat_nc">$0.00</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card bg-light border-0 h-100">
                                        <div class="card-body p-2 text-center">
                                            <span class="d-block small text-muted mb-1">Facturas Anuladas</span>
                                            <h5 class="mb-0 text-danger fw-bold" id="stat_anuladas">0</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pesta�a: COBROS -->
                        <div class="tab-pane fade" id="pane-cobros" role="tabpanel">
                            <div class="alert alert-secondary bg-light border-0 small mb-3">
                                <i class="bi bi-info-circle me-1"></i> Configure los par�metros de cobros para este cliente.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="cliente_id_forma_pago_sri" class="form-label small fw-bold">Forma de pago predefinida sri para facturaci�n al sri</label>
                                    <select class="form-select form-select-sm" name="id_forma_pago_sri" id="cliente_id_forma_pago_sri">
                                        <option value="">- Seleccione forma de pago SRI -</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_id_forma_cobro_predeterminada" class="form-label small fw-bold">Forma de cobro predefinida para este cliente</label>
                                    <select class="form-select form-select-sm" name="id_forma_cobro_predeterminada" id="cliente_id_forma_cobro_predeterminada">
                                        <option value="">- Seleccione forma de cobro -</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_monto_maximo_auto_cobro" class="form-label small fw-bold">Monto m�ximo auto generar ingreso</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.01" class="form-control form-control-sm" name="monto_maximo_auto_cobro" id="cliente_monto_maximo_auto_cobro" placeholder="Ej. 100.00">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña 4: INFORMACI�“N -->
                        <div class="tab-pane fade" id="pane-informacion" role="tabpanel">
                            <ul class="list-group list-group-flush small" id="auditoriaList">
                                <li class="list-group-item bg-transparent px-0 text-muted">Aún no existe historial para este cliente.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarCliente" onclick="eliminarCliente()">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarCliente"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </div>
            </form>

            <?php if ($perm['eliminar']): ?>
                <form id="formEliminarCliente" method="POST" action="<?= $urlBaseClientes ?>/delete" class="d-none">
                    <input type="hidden" name="id_eliminar" id="id_eliminar">
                </form>
            <?php endif; ?>
        </div>
    </div>
    <!-- Modal Creación Rápida de Vendedor -->
    <div class="modal fade" id="modalVendedorRapido" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nuevo Vendedor</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <div id="alertVendedorRapido" class="alert d-none py-1 px-2 small mb-2 border-0"></div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Nombre *</label>
                        <input type="text" id="rapido_vendedor_nombre" class="form-control form-control-sm" placeholder="Nombre completo">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold mb-1">Identificación</label>
                        <input type="text" id="rapido_vendedor_identificacion" class="form-control form-control-sm" placeholder="Cédula/RUC">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-3" onclick="guardarVendedorRapido()" id="btnSaveVendedorRapido">Crear</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            'use strict';

            // �”€�”€�”€ Constantes �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            const urlBase = '<?= $urlBaseClientes ?>';
            const formCliente = document.getElementById('formCliente');
            let canCreateVend = <?= json_encode($canCreateVend ?? false) ?>;

            let modalClienteInst = null;
            let tiposIdCargados = false; // se cargan una sola vez
            let provinciasCargadas = false; // idem
            let vendedoresCargados = false; // idem
            let sriDebounceTimer = null;

            // �”€�”€�”€ Bootstrap Modal �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            function getModalCliente() {
                if (!modalClienteInst && typeof bootstrap !== 'undefined') {
                    const el = document.getElementById('modalCliente');
                    if (el) modalClienteInst = new bootstrap.Modal(el);
                }
                return modalClienteInst;
            }

            // �”€�”€�”€ Helpers para el campo identificación �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€

            /** Código normalizado del tipo seleccionado (uppercase, sin espacios) */
            function getTipoNormalizado() {
                const sel = document.getElementById('cliente_tipo_id');
                if (!sel) return '';
                // Buscamos el texto del option seleccionado para detectar el tipo
                const codigo = (sel.value || '').trim().toUpperCase();
                const textoOpt = (sel.options[sel.selectedIndex]?.text || '').toUpperCase();
                // Detectar por texto o código
                if (textoOpt.includes('CONSUMIDOR') || codigo.includes('CONSUMIDOR')) return 'CONSUMIDOR_FINAL';
                if (textoOpt.includes('PASAPORTE') || codigo.includes('PAS')) return 'PASAPORTE';
                if (textoOpt.includes('CEDULA') || textoOpt.includes('C�‰DULA') || codigo.includes('CED')) return 'CEDULA';
                if (textoOpt.includes('RUC')) return 'RUC';
                return codigo; // fallback
            }

            /** Aplica restricciones al campo de identificación según tipo */
            function aplicarReglasIdentificacion() {
                const tipo = getTipoNormalizado();
                const campo = document.getElementById('cliente_identificacion');
                const nombre = document.getElementById('cliente_nombre');
                if (!campo) return;

                // Reset estado
                campo.readOnly = false;
                campo.classList.remove('field-sri-locked');
                campo.setAttribute('inputmode', 'numeric');
                campo.setAttribute('pattern', '');
                limpiarBadgeSri();

                if (nombre) {
                    nombre.readOnly = false;
                    nombre.classList.remove('field-sri-locked');
                }

                switch (tipo) {
                    case 'RUC':
                        campo.maxLength = 13;
                        campo.setAttribute('inputmode', 'numeric');
                        break;

                    case 'CEDULA':
                        campo.maxLength = 10;
                        campo.setAttribute('inputmode', 'numeric');
                        break;

                    case 'PASAPORTE':
                        campo.maxLength = 20;
                        campo.setAttribute('inputmode', 'text');
                        break;

                    case 'CONSUMIDOR_FINAL':
                        campo.maxLength = 13;
                        campo.value = '9999999999999';
                        campo.readOnly = true;
                        campo.classList.add('field-sri-locked');
                        if (nombre) {
                            nombre.value = 'CONSUMIDOR FINAL';
                            nombre.readOnly = true;
                            nombre.classList.add('field-sri-locked');
                        }
                        limpiarBadgeSri();
                        break;
                }
            }

            /** Filtra teclas no numéricas en campos que solo admiten dígitos */
            function soloNumerosEnInput(e) {
                const tipo = getTipoNormalizado();
                if (tipo === 'RUC' || tipo === 'CEDULA') {
                    // Permitir: dígitos, backspace, delete, tab, flechas, ctrl/cmd combos
                    const permitidos = ['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
                    if (e.ctrlKey || e.metaKey) return; // ctrl+a, ctrl+c, etc.
                    if (!permitidos.includes(e.key) && !/^\d$/.test(e.key)) {
                        e.preventDefault();
                    }
                }
            }

            // �”€�”€�”€ Validación de identificación �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            function validarIdentificacion() {
                const tipo = getTipoNormalizado();
                const valor = (document.getElementById('cliente_identificacion')?.value || '').trim();
                const errEl = document.getElementById('identificacionError');

                const mostrarError = (msg) => {
                    if (errEl) {
                        errEl.textContent = msg;
                        errEl.style.setProperty('display', 'block', 'important');
                    }
                };
                const limpiarError = () => {
                    if (errEl) {
                        errEl.textContent = '';
                        errEl.style.setProperty('display', 'none', 'important');
                    }
                };

                switch (tipo) {
                    case 'RUC':
                        if (!/^\d{13}$/.test(valor)) {
                            mostrarError('El RUC debe tener exactamente 13 dígitos numéricos.');
                            return false;
                        }
                        const ultTres = valor.slice(-3);
                        if (ultTres !== '001' && ultTres !== '002') {
                            mostrarError('Los últimos 3 dígitos del RUC deben ser 001 o 002.');
                            return false;
                        }
                        limpiarError();
                        return true;

                    case 'CEDULA':
                        if (!/^\d{10}$/.test(valor)) {
                            mostrarError('La cédula debe tener exactamente 10 dígitos numéricos.');
                            return false;
                        }
                        limpiarError();
                        return true;

                    case 'PASAPORTE':
                        if (valor.length === 0 || valor.length > 20) {
                            mostrarError('El pasaporte puede tener hasta 20 caracteres alfanuméricos.');
                            return false;
                        }
                        limpiarError();
                        return true;

                    case 'CONSUMIDOR_FINAL':
                        limpiarError();
                        return true;

                    default:
                        if (valor.length === 0) {
                            mostrarError('Ingrese la identificación.');
                            return false;
                        }
                        limpiarError();
                        return true;
                }
            }

            // �”€�”€�”€ Consulta SRI �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            function mostrarBadgeSri(texto, cls) {
                const b = document.getElementById('sriBadge');
                if (!b) return;
                b.textContent = texto;
                b.className = 'badge ' + cls;
                b.classList.remove('d-none');
            }

            function limpiarBadgeSri() {
                const b = document.getElementById('sriBadge');
                const sw = document.getElementById('sriSpinnerWrap');
                if (b) {
                    b.className = 'badge d-none';
                    b.textContent = '';
                }
                if (sw) sw.classList.add('d-none');
            }

            function mostrarSpinnerSri(visible) {
                const sw = document.getElementById('sriSpinnerWrap');
                if (sw) sw.classList.toggle('d-none', !visible);
            }

            async function consultarSri(identificacion) {
                mostrarSpinnerSri(true);
                mostrarBadgeSri('Consultando SRI�€�', 'bg-secondary');
                try {
                    const fd = new FormData();
                    fd.append('identificacion', identificacion);
                    const resp = await fetch(urlBase + '/consultarSri', {
                        method: 'POST',
                        body: fd
                    });
                    const json = await resp.json();
                    mostrarSpinnerSri(false);

                    if (!json.ok) {
                        mostrarBadgeSri('No encontrado', 'bg-warning text-dark');
                        return;
                    }

                    const d = json.data;
                    mostrarBadgeSri('�œ“ SRI', 'bg-success');

                    // Razón social
                    if (d.nombre) {
                        const el = document.getElementById('cliente_nombre');
                        if (el && !el.readOnly) el.value = d.nombre;
                    }
                    // Dirección
                    if (d.direccion) {
                        const el = document.getElementById('cliente_direccion');
                        if (el) el.value = d.direccion;
                    }
                    // Provincia y ciudad
                    if (d.cod_prov && d.cod_prov !== '') {
                        const selProv = document.getElementById('cliente_provincia');
                        if (selProv) {
                            selProv.value = d.cod_prov;
                            if (selProv.value !== d.cod_prov) {
                                // Opción no existe aún �€“ esperar carga de ciudades
                            }
                            // Disparar cambio para cargar ciudades
                            await cargarCiudades(d.cod_prov, d.cod_ciudad || '');
                        }
                    }

                } catch (err) {
                    mostrarSpinnerSri(false);
                    mostrarBadgeSri('Error', 'bg-danger');
                    console.error('Error consultando SRI:', err);
                }
            }

            function onIdentificacionInput() {
                const tipo = getTipoNormalizado();
                const valor = (document.getElementById('cliente_identificacion')?.value || '').trim();

                limpiarBadgeSri();
                clearTimeout(sriDebounceTimer);

                const longitudesValidas = {
                    RUC: 13,
                    CEDULA: 10
                };
                const longEsperada = longitudesValidas[tipo];

                if (!longEsperada) return; // Pasaporte o Consumidor Final no consultan SRI

                if (valor.length === longEsperada) {
                    sriDebounceTimer = setTimeout(() => {
                        if (validarIdentificacion()) {
                            consultarSri(valor);
                        }
                    }, 700);
                }
            }

            // �”€�”€�”€ Validación de emails �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            function validarEmails() {
                const campo = document.getElementById('cliente_email');
                const errEl = document.getElementById('emailError');
                if (!campo || !errEl) return true;

                const raw = campo.value.trim();
                if (raw === '') {
                    errEl.textContent = 'El correo electrónico es obligatorio.';
                    errEl.style.display = 'block';
                    campo.classList.add('is-invalid');
                    return false;
                }

                // Separar por coma (con o sin espacio)
                const correos = raw.split(',').map(s => s.trim()).filter(s => s !== '');
                const reEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const invalidos = correos.filter(c => !reEmail.test(c));

                if (invalidos.length > 0) {
                    errEl.textContent = 'Correos inválidos: ' + invalidos.join(', ');
                    errEl.style.display = 'block';
                    campo.classList.add('is-invalid');
                    return false;
                }
                errEl.style.display = 'none';
                campo.classList.remove('is-invalid');
                return true;
            }

            // �”€�”€�”€ Carga de Tipos de Identificación �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            async function cargarTiposId(valorActual) {
                if (tiposIdCargados) {
                    // Solo actualizar selección
                    const sel = document.getElementById('cliente_tipo_id');
                    if (sel && valorActual) preSelectByValue(sel, valorActual);
                    return;
                }
                const sel = document.getElementById('cliente_tipo_id');
                if (!sel) return;
                try {
                    const resp = await fetch(urlBase + '/tiposId');
                    const json = await resp.json();
                    if (!json.ok) throw new Error(json.error || 'Error');

                    sel.innerHTML = '';
                    json.data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.codigo;
                        opt.textContent = item.nombre;
                        sel.appendChild(opt);
                    });

                    tiposIdCargados = true;

                    if (valorActual) preSelectByValue(sel, valorActual);
                    else if (sel.options.length > 0) sel.selectedIndex = 0;

                    aplicarReglasIdentificacion();
                } catch (e) {
                    sel.innerHTML = '<option value="">Error al cargar tipos</option>';
                    console.error('Error cargando tipos de ID:', e);
                }
            }

            /** Intenta seleccionar por value exacto, o por coincidencia parcial en texto */
            function preSelectByValue(sel, valor) {
                if (!valor) return;
                const v = valor.toString().toUpperCase();
                // Exacto
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value.toUpperCase() === v) {
                        sel.selectedIndex = i;
                        return;
                    }
                }
                // Coincidencia en texto del option
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].text.toUpperCase().includes(v)) {
                        sel.selectedIndex = i;
                        return;
                    }
                }
            }

            // �”€�”€�”€ Carga de Provincias �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            async function cargarProvincias(valorActual) {
                const sel = document.getElementById('cliente_provincia');
                if (!sel) return;

                if (provinciasCargadas) {
                    if (valorActual) sel.value = valorActual;
                    return;
                }

                try {
                    const resp = await fetch(urlBase + '/provincias');
                    const json = await resp.json();
                    if (!json.ok) throw new Error(json.error || 'Error');

                    sel.innerHTML = '<option value="">�- Seleccione provincia �-</option>';
                    json.data.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.codigo;
                        opt.textContent = p.nombre;
                        sel.appendChild(opt);
                    });

                    provinciasCargadas = true;

                    if (valorActual) sel.value = valorActual;
                } catch (e) {
                    console.error('Error cargando provincias:', e);
                }
            }

            // �”€�”€�”€ Carga de Ciudades �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            async function cargarCiudades(codProv, valorCiudad) {
                const sel = document.getElementById('cliente_ciudad');
                if (!sel) return;

                if (!codProv || codProv === '') {
                    sel.innerHTML = '<option value="">�- Seleccione ciudad �-</option>';
                    return;
                }

                sel.innerHTML = '<option value="">Cargando...</option>';
                sel.disabled = true;

                try {
                    const resp = await fetch(urlBase + '/ciudades?cod_prov=' + encodeURIComponent(codProv));
                    const json = await resp.json();
                    if (!json.ok) throw new Error(json.error || 'Error');

                    sel.innerHTML = '<option value="">�- Seleccione ciudad �-</option>';
                    json.data.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.codigo;
                        opt.textContent = c.nombre;
                        sel.appendChild(opt);
                    });

                    if (valorCiudad) sel.value = valorCiudad;
                } catch (e) {
                    sel.innerHTML = '<option value="">Error al cargar ciudades</option>';
                    console.error('Error cargando ciudades:', e);
                } finally {
                    sel.disabled = false;
                }
            }

            // �”€�”€�”€ Carga de Vendedores �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            async function cargarVendedores(valorActual) {
                const sel = document.getElementById('cliente_vendedor');
                if (!sel) return;

                if (vendedoresCargados) {
                    if (valorActual) sel.value = valorActual;
                    return;
                }

                try {
                    const resp = await fetch(urlBase + '/vendedores');
                    const json = await resp.json();
                    if (!json.ok) throw new Error(json.error || 'Error');

                    // Actualizar permiso si cambia dinámicamente
                    if (typeof json.can_create_vendedor !== 'undefined') {
                        canCreateVend = json.can_create_vendedor;
                    }

                    const btnRapido = document.getElementById('btnNuevoVendedorRapido');
                    if (btnRapido) {
                        btnRapido.classList.toggle('d-none', !canCreateVend);
                    }

                    sel.innerHTML = '<option value="">�- Sin vendedor asignado �-</option>';
                    json.data.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = v.nombre + (v.identificacion ? ' (' + v.identificacion + ')' : '');
                        sel.appendChild(opt);
                    });

                    vendedoresCargados = true;
                    if (valorActual) sel.value = valorActual;
                } catch (e) {
                    console.error('Error cargando vendedores:', e);
                }
            }

            // ─── Carga de Formas de Pago SRI ─────────────────────────────────────────
            let formasPagoSriCargadas = false;
            async function cargarFormasPagoSri(valorActual) {
                const sel = document.getElementById('cliente_id_forma_pago_sri');
                if (!sel) return;

                if (formasPagoSriCargadas) {
                    if (valorActual) sel.value = valorActual;
                    return;
                }

                try {
                    const resp = await fetch(urlBase + '/formasPagoSri');
                    const json = await resp.json();
                    if (!json.ok) throw new Error(json.error || 'Error');

                    sel.innerHTML = '<option value="">- Seleccione forma de pago SRI -</option>';
                    json.data.forEach(f => {
                        const opt = document.createElement('option');
                        opt.value = f.id;
                        opt.textContent = f.codigo + ' - ' + f.nombre;
                        sel.appendChild(opt);
                    });

                    formasPagoSriCargadas = true;
                    if (valorActual) sel.value = valorActual;
                } catch (e) {
                    console.error('Error cargando formas de pago SRI:', e);
                }
            }

            // ─── L�gica para Crear Vendedor R�pido ──────────────────────────────
            let modalVendRapInst = null;

            function getModalVendedorRapido() {
                if (!modalVendRapInst && typeof bootstrap !== 'undefined') {
                    const el = document.getElementById('modalVendedorRapido');
                    if (el) modalVendRapInst = new bootstrap.Modal(el);
                }
                return modalVendRapInst;
            }

            window.abrirModalVendedorRapido = function() {
                document.getElementById('rapido_vendedor_nombre').value = '';
                document.getElementById('rapido_vendedor_identificacion').value = '';
                document.getElementById('alertVendedorRapido').classList.add('d-none');
                getModalVendedorRapido()?.show();
            };

            window.guardarVendedorRapido = async function() {
                const nombre = document.getElementById('rapido_vendedor_nombre').value.trim();
                const iden = document.getElementById('rapido_vendedor_identificacion').value.trim();
                const alertV = document.getElementById('alertVendedorRapido');
                const btn = document.getElementById('btnSaveVendedorRapido');

                if (!nombre) {
                    alertV.textContent = 'El nombre es obligatorio.';
                    alertV.className = 'alert alert-danger py-1 px-2 small mb-2';
                    alertV.classList.remove('d-none');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                try {
                    const fd = new FormData();
                    fd.append('nombre', nombre);
                    fd.append('identificacion', iden);
                    fd.append('status', 1);

                    // Reutilizamos el store de vendedores (ruta: BASE_URL/modulos/vendedores/store)
                    const urlStore = '<?= BASE_URL ?>/modulos/vendedores/store';
                    const resp = await fetch(urlStore, {
                        method: 'POST',
                        body: fd
                    });
                    const json = await resp.json();

                    if (json.ok) {
                        alertV.textContent = '�Creado!';
                        alertV.className = 'alert alert-success py-1 px-2 small mb-2';
                        alertV.classList.remove('d-none');

                        // Forzar recarga de vendedores y seleccionar el nuevo
                        vendedoresCargados = false;
                        await cargarVendedores(json.id);

                        // Esperar un poco y cerrar
                        setTimeout(() => {
                            getModalVendedorRapido()?.hide();
                        }, 600);
                    } else {
                        alertV.textContent = json.error || 'Error al crear';
                        alertV.className = 'alert alert-danger py-1 px-2 small mb-2';
                        alertV.classList.remove('d-none');
                    }
                } catch (e) {
                    alertV.textContent = 'Error de conexi�n';
                    alertV.className = 'alert alert-danger py-1 px-2 small mb-2';
                    alertV.classList.remove('d-none');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = 'Crear';
                }
            };

            // --- Reset formulario ------------------------------------------------
            function resetFormulario() {
                if (!formCliente) return;
                try {
                    formCliente.reset();
                } catch (e) {}

                document.getElementById('cliente_id').value = '';
                document.getElementById('identificacionError').style.setProperty('display', 'none', 'important');

                const modalAlert = document.getElementById('modalAlert');
                if (modalAlert) {
                    modalAlert.classList.add('d-none');
                    modalAlert.innerHTML = '';
                }

                // Los favoritos se aplicar�n DESPU�S de que carguen los datos as�ncronos en abrirModalClienteCrear

                const emailErr = document.getElementById('emailError');
                if (emailErr) emailErr.style.display = 'none';
                const campoEmailReset = document.getElementById('cliente_email');
                if (campoEmailReset) campoEmailReset.classList.remove('is-invalid');

                const btnDlt = document.getElementById('btnEliminarCliente');
                if (btnDlt) {
                    btnDlt.classList.add('d-none');
                    btnDlt.disabled = false;
                    btnDlt.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
                }

                document.getElementById('tituloModalCliente').textContent = 'Nuevo Cliente';

                // Volver a pesta�a General
                const primerTab = document.getElementById('tab-general');
                if (primerTab && typeof bootstrap !== 'undefined') {
                    try {
                        const t = bootstrap.Tab.getInstance(primerTab) || new bootstrap.Tab(primerTab);
                        t.show();
                    } catch (e) {}
                }

                // Limpiar auditor�a
                const auditList = document.getElementById('auditoriaList');
                if (auditList) auditList.innerHTML = '<li class="list-group-item bg-transparent px-0 text-muted">A�n no existe historial para este cliente.</li>';

                // Limpiar ciudades
                const selCiudad = document.getElementById('cliente_ciudad');
                if (selCiudad) selCiudad.innerHTML = '<option value="">- Seleccione ciudad -</option>';

                // Reset vendedor al primer valor (sin asignar)
                const selVend = document.getElementById('cliente_vendedor');
                if (selVend) selVend.value = '';

                const selFp = document.getElementById('cliente_id_forma_pago_sri');
                if (selFp) selFp.value = '';
                const selFc = document.getElementById('cliente_id_forma_cobro_predeterminada');
                if (selFc) selFc.value = '';
                const txtMx = document.getElementById('cliente_monto_maximo_auto_cobro');
                if (txtMx) txtMx.value = '';

                // Limpiar campos sri-locked
                const nombre = document.getElementById('cliente_nombre');
                const idCampo = document.getElementById('cliente_identificacion');
                if (nombre) {
                    nombre.readOnly = false;
                    nombre.classList.remove('field-sri-locked');
                }
                if (idCampo) {
                    idCampo.readOnly = false;
                    idCampo.classList.remove('field-sri-locked');
                }

                limpiarBadgeSri();
                clearTimeout(sriDebounceTimer);

                // Reactivar bot�n guardar
                const btnSave = document.getElementById('btnGuardarCliente');
                if (btnSave) {
                    btnSave.disabled = false;
                    btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                }
            }

            // --- Abrir modal Nuevo -----------------------------------------------
            window.abrirModalClienteCrear = async function() {
                const modal = getModalCliente();
                if (!modal) {
                    console.error('Bootstrap no cargado o modal no existe.');
                    return;
                }
                resetFormulario();
                formCliente.action = urlBase + '/store';
                // Cargar tipos e identificaci�n
                await cargarTiposId('');
                await cargarProvincias('');
                await cargarVendedores('');
                await cargarFormasPagoSri('');
                
                // Aplicamos favoritos solo en "Modo Crear" una vez todos los combos tienen sus opciones
                if (typeof aplicarFavoritosModal === 'function') {
                    aplicarFavoritosModal();
                }

                aplicarReglasIdentificacion();
                modal.show();
            };

            // --- Abrir modal Editar ----------------------------------------------
            window.abrirModalClienteEditar = async function(row) {
                const modal = getModalCliente();
                if (!modal) return;
                resetFormulario();
                formCliente.action = urlBase + '/update';

                try {
                    const data = JSON.parse(row.dataset.cliente);
                    document.getElementById('cliente_id').value = data.id || '';
                    document.getElementById('cliente_identificacion').value = data.identificacion || '';
                    document.getElementById('cliente_email').value = data.email || '';
                    document.getElementById('cliente_telefono').value = data.telefono || '';
                    document.getElementById('cliente_direccion').value = data.direccion || '';
                    document.getElementById('cliente_plazo').value = (data.plazo !== null && typeof data.plazo !== 'undefined') ? data.plazo : 0;
                    document.getElementById('cliente_status').value = typeof data.status !== 'undefined' ? data.status : '1';
                    document.getElementById('cliente_vendedor').value = data.id_vendedor || '';
                    document.getElementById('cliente_cuenta_cobrar').value = data.id_cuenta_cobrar || '';
                    document.getElementById('cliente_cuenta_ingreso').value = data.id_cuenta_ingreso || '';
                    document.getElementById('cliente_monto_maximo_auto_cobro').value = data.monto_maximo_auto_cobro || '';

                    // Cargar tipos y seleccionar el que corresponde al cliente
                    await cargarTiposId(data.tipo_id || '');
                    aplicarReglasIdentificacion();

                    // Raz�n social (puede haberla bloqueado el tipo consumidor final)
                    const nombre = document.getElementById('cliente_nombre');
                    if (nombre && !nombre.readOnly) nombre.value = data.nombre || '';

                    // Cargar provincias y luego ciudades
                    await cargarProvincias(data.provincia || '');
                    if (data.provincia) {
                        await cargarCiudades(data.provincia, data.ciudad || '');
                    }

                    // Cargar vendedores y forma de pago SRI
                    await cargarVendedores(data.id_vendedor || '');
                    await cargarFormasPagoSri(data.id_forma_pago_sri || '');
                    
                    const selFc = document.getElementById('cliente_id_forma_cobro_predeterminada');
                    if(selFc) selFc.value = data.id_forma_cobro_predeterminada || '';

                    // Auditor�a
                    const auditList = document.getElementById('auditoriaList');
                    if (auditList) {
                        let html = '';
                        if (data.created_at) html += `<li class="list-group-item bg-transparent px-0 py-2 border-light"><i class="bi bi-calendar-check text-success me-2"></i><strong>Creado el:</strong> ${data.created_at}</li>`;
                        if (data.updated_at && data.updated_at !== data.created_at) html += `<li class="list-group-item bg-transparent px-0 py-2 border-light"><i class="bi bi-pencil-square text-primary me-2"></i><strong>�ltima edici�n el:</strong> ${data.updated_at}</li>`;
                        if (!html) html = '<li class="list-group-item bg-transparent px-0 text-muted">Datos antiguos sin historial registrado.</li>';
                        auditList.innerHTML = html;
                    }

                    document.getElementById('tituloModalCliente').textContent = 'Ficha de Cliente';
                    const btnDelete = document.getElementById('btnEliminarCliente');
                    if (btnDelete) btnDelete.classList.remove('d-none');
                } catch (e) {
                    console.error('Error cargando ficha:', e);
                }

                modal.show();
            };

            // �”€�”€�”€ Eliminar cliente �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            window.eliminarCliente = async function() {
                const id = document.getElementById('cliente_id').value;
                if (!id) return;
                if (confirm('¿Está seguro de eliminar este cliente? Esta acción lo enviará a la papelera.')) {
                    const btnDlt = document.getElementById('btnEliminarCliente');
                    const alertEl = document.getElementById('modalAlert');
                    const formDlt = document.getElementById('formEliminarCliente');

                    if (btnDlt) {
                        btnDlt.disabled = true;
                        btnDlt.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Eliminando...';
                    }

                    try {
                        const fd = new FormData();
                        fd.append('id_eliminar', id);
                        const resp = await fetch(formDlt.action, {
                            method: 'POST',
                            body: fd
                        });
                        const json = await resp.json();

                        if (alertEl) {
                            alertEl.textContent = json.msg || json.error || 'Error al eliminar';
                            alertEl.className = 'alert mb-3 py-2 small shadow-sm border-0 ' + (json.ok ? 'alert-success' : 'alert-danger');
                            alertEl.classList.remove('d-none');
                        }

                        if (json.ok) {
                            setTimeout(() => {
                                // Cerrar modal automáticamente usando la API de Bootstrap
                                const modalEl = document.getElementById('modalCliente');
                                if (modalEl && typeof bootstrap !== 'undefined') {
                                    const inst = bootstrap.Modal.getInstance(modalEl);
                                    if (inst) inst.hide();
                                }
                                // Refrescar solo la tabla en la página actual (sin recargar la página completa)
                                if (typeof window.fetchSearch === 'function') {
                                    window.fetchSearch(window.currentPage || 1);
                                }
                            }, 800);
                        } else {
                            if (btnDlt) {
                                btnDlt.disabled = false;
                                btnDlt.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
                            }
                        }
                    } catch (err) {
                        console.error('Error al eliminar:', err);
                        if (btnDlt) {
                            btnDlt.disabled = false;
                            btnDlt.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
                        }
                    }
                }
            };

            // �”€�”€�”€ Event listeners �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
            document.addEventListener('DOMContentLoaded', function() {
                // Cambio de tipo de identificación
                const selTipo = document.getElementById('cliente_tipo_id');
                if (selTipo) {
                    selTipo.addEventListener('change', function() {
                        aplicarReglasIdentificacion();
                        limpiarBadgeSri();
                        clearTimeout(sriDebounceTimer);
                        // Limpiar campo identificación al cambiar tipo (excepto si es consumidor final que ya se llenó)
                        const tipo = getTipoNormalizado();
                        if (tipo !== 'CONSUMIDOR_FINAL') {
                            const ic = document.getElementById('cliente_identificacion');
                            if (ic && ic.readOnly === false) ic.value = '';
                        }
                    });
                }

                // Input identificación
                const campoId = document.getElementById('cliente_identificacion');
                if (campoId) {
                    campoId.addEventListener('keydown', soloNumerosEnInput);
                    campoId.addEventListener('input', onIdentificacionInput);
                    campoId.addEventListener('paste', function(e) {
                        const tipo = getTipoNormalizado();
                        if (tipo === 'RUC' || tipo === 'CEDULA') {
                            // Limpiar el texto pegado para solo números
                            setTimeout(() => {
                                campoId.value = campoId.value.replace(/\D/g, '');
                                onIdentificacionInput();
                            }, 0);
                        }
                    });
                }

                // Cambio de provincia �†’ recargar ciudades
                const selProv = document.getElementById('cliente_provincia');
                if (selProv) {
                    selProv.addEventListener('change', function() {
                        cargarCiudades(this.value, '');
                    });
                }

                // Validación email en blur
                const campoEmail = document.getElementById('cliente_email');
                if (campoEmail) {
                    campoEmail.addEventListener('blur', validarEmails);
                }

                // Validación y envío AJAX
                if (formCliente) {
                    formCliente.addEventListener('submit', async function(e) {
                        e.preventDefault();

                        const idOk = validarIdentificacion();
                        const emailOk = validarEmails();
                        if (!idOk || !emailOk) {
                            // Ir a pestaña general si hay error de identificación o email
                            const primer = document.getElementById('tab-general');
                            if (primer && typeof bootstrap !== 'undefined') {
                                const t = bootstrap.Tab.getInstance(primer) || new bootstrap.Tab(primer);
                                t.show();
                            }
                            return;
                        }

                        // Envío AJAX
                        const btnSave = document.getElementById('btnGuardarCliente');
                        const modalAlert = document.getElementById('modalAlert');

                        if (btnSave) {
                            btnSave.disabled = true;
                            btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
                        }

                        if (modalAlert) {
                            modalAlert.classList.add('d-none');
                        }

                        try {
                            const fd = new FormData(formCliente);
                            const resp = await fetch(formCliente.action, {
                                method: 'POST',
                                body: fd
                            });
                            const json = await resp.json();

                            if (modalAlert) {
                                modalAlert.textContent = json.msg || json.error || 'Error desconocido';
                                modalAlert.className = 'alert mb-3 py-2 small shadow-sm border-0 ' + (json.ok ? 'alert-success' : 'alert-danger');
                                modalAlert.classList.remove('d-none');
                            }

                            if (json.ok) {
                                // �‰xito: refrescar tabla directamente en lugar de recargar para permanecer en la página actual
                                setTimeout(() => {
                                    // Cerrar modal automáticamente
                                    const modalEl = document.getElementById('modalCliente');
                                    if (modalEl && typeof bootstrap !== 'undefined') {
                                        const inst = bootstrap.Modal.getInstance(modalEl);
                                        if (inst) inst.hide();
                                    }
                                    // Refrescar tabla en la página actual si es una edición/guardado
                                    if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
                                }, 1000);
                            } else {
                                // Error: reactivar botón
                                if (btnSave) {
                                    btnSave.disabled = false;
                                    btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                                }
                            }
                        } catch (err) {
                            console.error('Error al enviar:', err);
                            if (modalAlert) {
                                modalAlert.textContent = 'Error de conexión con el servidor.';
                                modalAlert.className = 'alert mb-3 py-2 small shadow-sm border-0 alert-danger';
                                modalAlert.classList.remove('d-none');
                            }
                            if (btnSave) {
                                btnSave.disabled = false;
                                btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                            }
                        }
                    });
                }

                // �”€�”€�”€ Buscador en Tiempo Real �”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€�”€
                const inputBuscar = document.getElementById('buscarCliente');
                window.currentSort = '<?= $ordenCol ?>';
                window.currentDir = '<?= $ordenDir ?>';
                window.currentPage = <?= $page ?>; // Seguimiento global de página actual

                function debounce(func, timeout = 300) {
                    let timer;
                    return (...args) => {
                        clearTimeout(timer);
                        timer = setTimeout(() => {
                            func.apply(this, args);
                        }, timeout);
                    };
                }

                // Función global para que el HTML devuelto por AJAX pueda llamarla
                window.cambiarPaginaAjax = function(n) {
                    window.fetchSearch(n);
                };

                window.fetchSearch = async (page = 1) => {
                    const term = inputBuscar ? inputBuscar.value.trim() : '';
                    const url = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;

                    try {
                        const resp = await fetch(url);
                        const data = await resp.json();

                        if (data.ok) {
                            window.currentPage = page; // Actualizar página actual tras éxito de carga
                            document.getElementById('tbodyClientes').innerHTML = data.rows;
                            document.getElementById('paginationContainer').innerHTML = data.pagination;
                            document.getElementById('paginationInfo').textContent = data.info;

                            // Actualizar links de exportación
                            document.getElementById('btnExportPdf').href = data.pdf_url;
                            document.getElementById('btnExportExcel').href = data.excel_url;

                            // Actualizar iconos de ordenamiento en headers
                            document.querySelectorAll('.sortable-header').forEach(th => {
                                const icon = th.querySelector('i');
                                const field = th.dataset.sort;
                                if (field === window.currentSort) {
                                    icon.className = (window.currentDir.toLowerCase() === 'asc') ?
                                        'bi bi-sort-alpha-down text-primary ms-1' :
                                        'bi bi-sort-alpha-up text-primary ms-1';
                                } else {
                                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                                }
                            });
                        }
                    } catch (err) {
                        console.error('Error en búsqueda AJAX:', err);
                    }
                };

                // Manejador de ordenamiento por columnas
                document.querySelectorAll('.sortable-header').forEach(header => {
                    header.addEventListener('click', () => {
                        const sortField = header.dataset.sort;
                        if (window.currentSort === sortField) {
                            window.currentDir = (window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                        } else {
                            window.currentSort = sortField;
                            window.currentDir = 'ASC';
                        }
                        
                        if (typeof window.guardarOrdenacionVista === 'function') {
                            window.guardarOrdenacionVista('clientes', window.currentSort, window.currentDir);
                        }

                        fetchSearch(1); // Al cambiar orden volvemos a página 1
                    });
                });

                if (inputBuscar) {
                    inputBuscar.addEventListener('input', debounce(() => {
                        fetchSearch(1);
                    }, 400));
                }
            });

        })();
    </script>