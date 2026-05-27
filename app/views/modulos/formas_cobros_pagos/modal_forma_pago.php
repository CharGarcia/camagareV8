<?php

/**
 * Modal Compartido de Formas de Pago
 * Se puede incluir en Index de Formas de Pago, Egreso, Ingreso, etc.
 */

$idUsuarioActFP = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaActFP = (int)($_SESSION['id_empresa'] ?? 0);

// Endpoint fijo del módulo compartido
$urlBaseFPShared = BASE_URL . '/modulos/formas_cobros_pagos';

// Cargar Bancos dinámicamente para garantizar independencia absoluta de la vista madre
try {
    $dbFP = \App\core\Database::getConnection();
    $bancosFP = $dbFP->query("SELECT id, nombre_banco FROM bancos_ecuador ORDER BY nombre_banco ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $bancosFP = [];
}
?>
<!-- MODAL DE GESTION DE FORMA DE PAGO -->
<div class="modal fade" id="modalFP" data-bs-backdrop="static" tabindex="-1" style="z-index: 1070;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="frmModalFP">
                <input type="hidden" name="id" id="fp-id">

                <div class="modal-header bg-light py-3 border-bottom">
                    <h5 class="modal-title fw-bold"><i class="bi bi-credit-card-2-front text-primary me-2"></i> <span id="modalFPTitulo">Nueva Forma de Pago</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        <!-- Fila 1: Tipo y Concepto -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo de Forma</label>
                            <select name="tipo" id="fp-tipo" class="form-select form-select-sm" onchange="toggleCamposBanco(this.value)" required>
                                <option value="EFECTIVO">Efectivo</option>
                                <option value="BANCO">Bancaria</option>
                                <option value="TARJETA">Tarjeta</option>
                                <option value="OTRO">Otros</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Concepto <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="fp-nombre" class="form-control form-control-sm" placeholder="Ej: Efectivo, caja chica, etc." required maxlength="20" autocomplete="off">
                            <div class="form-text" style="font-size: 0.7rem;">Máx. 20 caracteres para evitar desbordes.</div>
                        </div>

                        <!-- Fila 2: Aplica Para -->
                        <div class="col-md-12">
                            <label class="form-label small fw-bold d-block">Aplica para:</label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="fp-chk-ingreso" checked>
                                    <label class="form-check-label small fw-medium" for="fp-chk-ingreso">Ingreso</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="fp-chk-egreso" checked>
                                    <label class="form-check-label small fw-medium" for="fp-chk-egreso">Egreso</label>
                                </div>
                            </div>
                            <input type="hidden" name="aplica_en" id="fp-aplica" value="AMBAS">
                        </div>

                        <!-- SECCION DE BANCOS CONDICIONAL -->
                        <div class="col-12 d-none" id="sec-banco">
                            <div class="card border border-primary bg-light bg-opacity-10 p-3">
                                <h6 class="fw-bold text-primary small mb-3 border-bottom pb-1"><i class="bi bi-bank me-1"></i> Información Bancaria</h6>
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold">Institución Bancaria</label>
                                        <select name="id_banco" id="fp-banco" class="form-select form-select-sm">
                                            <option value="">-- Seleccione Banco --</option>
                                            <?php foreach ($bancosFP as $b): ?>
                                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre_banco']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Tipo de Cuenta</label>
                                        <select name="tipo_cuenta" id="fp-tipocuenta" class="form-select form-select-sm">
                                            <option value="">-- Seleccionar --</option>
                                            <option value="AHORROS">AHORROS</option>
                                            <option value="CORRIENTE">CORRIENTE</option>
                                            <option value="VIRTUAL">VIRTUAL / DIGITAL</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Número de Cuenta</label>
                                        <input type="text" name="numero_cuenta" id="fp-numcuenta" class="form-control form-control-sm" placeholder="Ej: 220123456">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fila 4: Cuenta Contable y Estado -->
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Cuenta Contable (Opcional)</label>
                            <div class="position-relative">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="bi bi-calculator"></i></span>
                                    <input type="text" id="fp-src-cuenta" class="form-control" placeholder="Buscar por código o nombre..." autocomplete="off">
                                    <button class="btn btn-outline-secondary d-none" type="button" id="fp-btn-clear-cuenta" onclick="selectCuenta(null)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <input type="hidden" name="id_cuenta_contable" id="fp-idcuenta">
                                </div>
                                <div id="fp-cuenta-drop" class="list-group shadow-sm position-absolute w-100 dropdown-predictivo d-none" style="max-height: 180px; overflow-y:auto; z-index:2000;"></div>
                            </div>
                        </div>

                        <div class="col-md-4 d-flex align-items-end pb-1">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" id="fp-activo-sw" checked>
                                <input type="hidden" name="activo" id="fp-activo" value="1">
                                <label class="form-check-label fw-bold small" for="fp-activo-sw">Estado: Activo</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarFP" onclick="eliminarFP()">
                            <i class="bi bi-trash me-1"></i> Eliminar
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm"><i class="bi bi-check-circle me-1"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Usar URL base global compartida para garantizar llamadas Ajax correctas desde cualquier módulo que incluya el modal
    const URL_FP_SHARED = '<?= $urlBaseFPShared ?>';
    let modalInstanciaFP = null;

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('modalFP');
        if (modalEl) {
            modalInstanciaFP = new bootstrap.Modal(modalEl);
        }

        // Listener Buscador Predictivo Cuentas
        const inpCta = document.getElementById('fp-src-cuenta');
        const dropCta = document.getElementById('fp-cuenta-drop');
        let tmoCta = null;

        if (inpCta) {
            inpCta.addEventListener('input', (e) => {
                clearTimeout(tmoCta);
                const val = e.target.value.trim();
                if (val.length < 2) {
                    dropCta.classList.add('d-none');
                    return;
                }
                tmoCta = setTimeout(() => {
                    fetch(`${URL_FP_SHARED}/searchCuentasAjax?q=${encodeURIComponent(val)}`)
                        .then(r => r.json())
                        .then(res => {
                            dropCta.innerHTML = '';
                            if (res.ok && res.data.length > 0) {
                                res.data.forEach(item => {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'list-group-item list-group-item-action py-2 small';
                                    btn.innerHTML = `<strong>${item.codigo}</strong> - ${item.nombre}`;
                                    btn.onclick = () => selectCuenta(item);
                                    dropCta.appendChild(btn);
                                });
                                dropCta.classList.remove('d-none');
                            } else {
                                dropCta.innerHTML = '<div class="list-group-item small text-muted">Sin resultados.</div>';
                                dropCta.classList.remove('d-none');
                            }
                        });
                }, 300);
            });

            inpCta.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' || e.key === 'Delete') {
                    if (!inpCta.readOnly && inpCta.value === '') selectCuenta(null);
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (dropCta && !dropCta.contains(e.target) && e.target !== inpCta) dropCta.classList.add('d-none');
        });

        // Enviar Formulario
        // Manejo del Switch de Estado interactivo
        const swFP = document.getElementById('fp-activo-sw');
        if (swFP) {
            swFP.addEventListener('change', function() {
                const hidden = document.getElementById('fp-activo');
                hidden.value = this.checked ? '1' : '0';
                // Actualizar etiqueta visual (checkbox -> hidden -> label)
                this.nextElementSibling.nextElementSibling.innerText = this.checked ? 'Estado: Activo' : 'Estado: Inactivo';
            });
        }

        // Enviar Formulario
        const frmFP = document.getElementById('frmModalFP');
        if (frmFP) {
            frmFP.addEventListener('submit', function(e) {
                e.preventDefault();
                const isIng = document.getElementById('fp-chk-ingreso').checked;
                const isEgr = document.getElementById('fp-chk-egreso').checked;
                let aplicaVal = 'AMBAS';

                if (!isIng && !isEgr) {
                    Swal.fire('Atención', 'Debe seleccionar al menos una aplicación (Ingreso o Egreso).', 'warning');
                    return;
                } else if (isIng && !isEgr) {
                    aplicaVal = 'INGRESO';
                } else if (!isIng && isEgr) {
                    aplicaVal = 'EGRESO';
                }

                const formData = new FormData(this);
                formData.set('aplica_en', aplicaVal);

                // Validacion
                if (formData.get('tipo') === 'BANCO' && (!formData.get('id_banco') || !formData.get('numero_cuenta'))) {
                    Swal.fire('Atención', 'Debe completar los campos de banco y número de cuenta para este tipo.', 'warning');
                    return;
                }

                fetch(`${URL_FP_SHARED}/guardarAjax`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.ok) {
                            Swal.fire('¡Éxito!', res.mensaje, 'success').then(() => {
                                // Si existe una funcion de callback global, dispararla para refrescar listados externos
                                if (typeof window.onFormaPagoCreada === 'function') {
                                    window.onFormaPagoCreada(res.id, formData.get('nombre'));
                                    modalInstanciaFP.hide();
                                } else {
                                    location.reload();
                                }
                            });
                        } else {
                            Swal.fire('Error', res.mensaje, 'error');
                        }
                    });
            });
        }
    });

    function selectCuenta(item) {
        const inpId = document.getElementById('fp-idcuenta');
        const inpSrc = document.getElementById('fp-src-cuenta');
        const drop = document.getElementById('fp-cuenta-drop');
        const btnClear = document.getElementById('fp-btn-clear-cuenta');

        if (item) {
            inpId.value = item.id;
            if (inpSrc) {
                inpSrc.value = `${item.codigo} - ${item.nombre}`;
                inpSrc.classList.add('bg-light', 'fw-medium', 'text-primary');
                inpSrc.readOnly = true;
            }
            if (btnClear) btnClear.classList.remove('d-none');
        } else {
            inpId.value = '';
            if (inpSrc) {
                inpSrc.value = '';
                inpSrc.classList.remove('bg-light', 'fw-medium', 'text-primary');
                inpSrc.readOnly = false;
            }
            if (btnClear) btnClear.classList.add('d-none');
        }
        if (drop) drop.classList.add('d-none');
    }

    function toggleCamposBanco(tipo) {
        const sec = document.getElementById('sec-banco');
        if (!sec) return;
        if (tipo === 'BANCO' || tipo === 'TARJETA') {
            sec.classList.remove('d-none');
        } else {
            sec.classList.add('d-none');
            document.getElementById('fp-banco').value = '';
            document.getElementById('fp-tipocuenta').value = '';
            document.getElementById('fp-numcuenta').value = '';
        }
    }

    function abrirModalFP(id = null) {
        const frm = document.getElementById('frmModalFP');
        if (!frm) return;
        frm.reset();
        document.getElementById('fp-id').value = '';
        document.getElementById('btnEliminarFP').classList.add('d-none');
        selectCuenta(null);
        document.getElementById('fp-tipo').value = 'EFECTIVO';
        toggleCamposBanco('EFECTIVO');

        document.getElementById('fp-chk-ingreso').checked = true;
        document.getElementById('fp-chk-egreso').checked = true;
        
        const swAct = document.getElementById('fp-activo-sw');
        if (swAct) {
            swAct.checked = true;
            swAct.nextElementSibling.nextElementSibling.innerText = 'Estado: Activo';
        }
        document.getElementById('fp-activo').value = '1';

        if (!id) {
            document.getElementById('modalFPTitulo').innerText = 'Nueva Forma de Pago';
            if (modalInstanciaFP) modalInstanciaFP.show();
        } else {
            document.getElementById('modalFPTitulo').innerText = 'Editar Forma de Pago';
            fetch(`${URL_FP_SHARED}/getFormaAjax?id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        const d = res.data;
                        document.getElementById('fp-id').value = d.id;
                        document.getElementById('fp-nombre').value = d.nombre;
                        document.getElementById('fp-tipo').value = d.tipo;
                        document.getElementById('fp-aplica').value = d.aplica_en;

                        document.getElementById('fp-chk-ingreso').checked = (d.aplica_en === 'AMBAS' || d.aplica_en === 'INGRESO');
                        document.getElementById('fp-chk-egreso').checked = (d.aplica_en === 'AMBAS' || d.aplica_en === 'EGRESO');
                        
                        const isActive = (d.activo === true || d.activo === 't' || d.activo === '1' || d.activo === 1 || d.activo === 'ACTIVO');
                        const swObj = document.getElementById('fp-activo-sw');
                        if (swObj) {
                            swObj.checked = isActive;
                            swObj.nextElementSibling.nextElementSibling.innerText = isActive ? 'Estado: Activo' : 'Estado: Inactivo';
                        }
                        document.getElementById('fp-activo').value = isActive ? '1' : '0';

                        toggleCamposBanco(d.tipo);
                        if (d.tipo === 'BANCO' || d.tipo === 'TARJETA') {
                            document.getElementById('fp-banco').value = d.id_banco || '';
                            document.getElementById('fp-tipocuenta').value = d.tipo_cuenta || '';
                            document.getElementById('fp-numcuenta').value = d.numero_cuenta || '';
                        }

                        if (d.id_cuenta_contable) {
                            selectCuenta({
                                id: d.id_cuenta_contable,
                                codigo: d.cuenta_contable_codigo,
                                nombre: d.cuenta_contable_nombre
                            });
                        }

                        document.getElementById('btnEliminarFP').classList.remove('d-none');
                        if (modalInstanciaFP) modalInstanciaFP.show();
                    } else {
                        Swal.fire('Error', res.mensaje, 'error');
                    }
                });
        }
    }

    function eliminarFP() {
        const id = document.getElementById('fp-id').value;
        if (!id) return;

        Swal.fire({
            title: '¿Está seguro?',
            text: "Esta acción desactivará lógicamente la forma de pago.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const data = new FormData();
                data.append('id', id);
                fetch(`${URL_FP_SHARED}/eliminarAjax`, {
                        method: 'POST',
                        body: data
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.ok) {
                            Swal.fire('Eliminado', res.mensaje, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.mensaje, 'error');
                        }
                    });
            }
        });
    }
</script>