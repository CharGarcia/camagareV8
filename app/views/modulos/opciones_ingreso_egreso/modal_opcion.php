<?php

/**
 * Modal de Configuración de Opciones de Ingreso / Egreso
 */
$idUsuarioActOIE = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaActOIE = (int)($_SESSION['id_empresa'] ?? 0);
?>

<!-- MODAL DE OPCIONES INGRESO EGRESO -->
<div class="modal fade" id="modalOpcion" data-bs-backdrop="static" tabindex="-1" style="z-index: 1070;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="frmModalOIE">
                <input type="hidden" name="id" id="oie-id">

                <div class="modal-header bg-light py-3 border-bottom">
                    <h5 class="modal-title fw-bold"><i class="bi bi-tags text-primary me-2"></i> <span id="modalOIETitulo">Nueva Opción</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        
                        <!-- Fila 1: Concepto y Relación -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nombre / Concepto <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="oie-nombre" class="form-control form-control-sm" placeholder="Ej: SRI, IESS..." required maxlength="20" autocomplete="off">
                            <div class="form-text" style="font-size: 0.7rem;">Máx. 20 caracteres para evitar desbordes.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold" title="Define si esta opción dispara lógicas especiales en las pantallas">Relacionado con:</label>
                            <select name="comportamiento" id="oie-comportamiento" class="form-select form-select-sm">
                                <option value="GENERAL">Sin relación con módulos del sistema</option>
                                <option value="COMPRA">Módulo de compras</option>
                                <option value="LIQUIDACION">Módulo de liquidaciones de compras</option>
                                <option value="FACTURA_VENTA">Módulo facturas de venta</option>
                                <option value="RECIBO_VENTA">Módulo recibo de venta</option>
                                <option value="ROL">Nómina (roles de pago)</option>
                                <option value="QUINCENA">Nómina (quincenas)</option>
                                <option value="PRESTAMO">Préstamo a empleado</option>
                                <option value="ANTICIPO_CLIENTE">Anticipo de cliente</option>
                                <option value="ANTICIPO_PROVEEDOR">Anticipo a proveedor</option>
                            </select>
                        </div>

                        <!-- Fila 2: Aplica, Cuenta Contable y Estado -->
                        <!-- Col 1: Radios Mutuamente Excluyentes -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold d-block">Aplica para:</label>
                            <div class="d-flex gap-3 mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="oie_aplica_tipo" id="oie-rdo-ingreso" value="INGRESO" checked>
                                    <label class="form-check-label small fw-medium" for="oie-rdo-ingreso">Ingreso</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="oie_aplica_tipo" id="oie-rdo-egreso" value="EGRESO">
                                    <label class="form-check-label small fw-medium" for="oie-rdo-egreso">Egreso</label>
                                </div>
                            </div>
                        </div>

                        <!-- Col 2: Cuenta Contable -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Cuenta Contable</label>
                            <div class="position-relative">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="bi bi-calculator"></i></span>
                                    <input type="text" id="oie-src-cuenta" class="form-control" placeholder="Buscar cuenta..." autocomplete="off">
                                    <button class="btn btn-outline-secondary d-none" type="button" id="oie-btn-clear-cuenta" onclick="selectCuentaOIE(null)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <input type="hidden" name="id_cuenta_contable" id="oie-idcuenta">
                                </div>
                                <div id="oie-cuenta-drop" class="list-group shadow-sm position-absolute w-100 dropdown-predictivo d-none" style="max-height: 160px; overflow-y:auto; z-index:2000;"></div>
                            </div>
                        </div>

                        <!-- Col 3: Switch de Estado -->
                        <div class="col-md-3 d-flex align-items-end pb-1">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" id="oie-sw-activo" checked>
                                <input type="hidden" name="estado" id="oie-input-estado" value="ACTIVO">
                                <label class="form-check-label fw-bold small" for="oie-sw-activo">Estado: Activo</label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light py-3 border-top">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarOIE" onclick="eliminarOIE()">
                                <i class="bi bi-trash me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarOIE"><i class="bi bi-check-circle me-1"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let modalInstanciaOIE = null;

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('modalOpcion');
        if (modalEl) {
            modalInstanciaOIE = new bootstrap.Modal(modalEl);
        }

        // Listeners de Cuenta Contable Predictiva
        const inpCta = document.getElementById('oie-src-cuenta');
        const dropCta = document.getElementById('oie-cuenta-drop');
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
                    fetch(`<?= $urlBase ?>/searchCuentasAjax?q=${encodeURIComponent(val)}`)
                        .then(r => r.json())
                        .then(res => {
                            dropCta.innerHTML = '';
                            if (res.ok && res.data.length > 0) {
                                res.data.forEach(item => {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'list-group-item list-group-item-action py-2 small';
                                    btn.innerHTML = `<strong>${item.codigo}</strong> - ${item.nombre}`;
                                    btn.onclick = () => selectCuentaOIE(item);
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
        }

        // Automatización Inteligente: Cambiar Aplica Según Relación Seleccionada
        const selComp = document.getElementById('oie-comportamiento');
        if (selComp) {
            selComp.addEventListener('change', function() {
                const comp = this.value;
                // Comportamientos puramente de egresos
                if (['COMPRA', 'LIQUIDACION', 'ROL', 'QUINCENA', 'PRESTAMO', 'ANTICIPO_PROVEEDOR'].includes(comp)) {
                    document.getElementById('oie-rdo-egreso').checked = true;
                }
                // Comportamientos puramente de ingresos
                else if (['FACTURA_VENTA', 'RECIBO_VENTA', 'ANTICIPO_CLIENTE'].includes(comp)) {
                    document.getElementById('oie-rdo-ingreso').checked = true;
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (dropCta && !dropCta.contains(e.target) && e.target !== inpCta) dropCta.classList.add('d-none');
        });

        // Manejo del Switch de Estado dinámico
        const swAct = document.getElementById('oie-sw-activo');
        if (swAct) {
            swAct.addEventListener('change', function() {
                const hidden = document.getElementById('oie-input-estado');
                hidden.value = this.checked ? 'ACTIVO' : 'INACTIVO';
                // Actualizar etiqueta visual
                this.nextElementSibling.nextElementSibling.innerText = this.checked ? 'Estado: Activo' : 'Estado: Inactivo';
            });
        }

        // Enviar Formulario
        const frmOIE = document.getElementById('frmModalOIE');
        if (frmOIE) {
            frmOIE.addEventListener('submit', function(e) {
                e.preventDefault();
                const isIng = document.getElementById('oie-rdo-ingreso').checked;
                const isEgr = document.getElementById('oie-rdo-egreso').checked;

                const btn = document.getElementById('btnGuardarOIE');
                const origTxt = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

                const formData = new FormData(this);
                formData.append('aplica_ingresos', isIng ? '1' : '0');
                formData.append('aplica_egresos', isEgr ? '1' : '0');

                fetch(`<?= $urlBase ?>/guardarAjax`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.ok) {
                            if (typeof window.onOpcionCreada === 'function') {
                                const comp = formData.get('comportamiento') || 'GENERAL';
                                window.onOpcionCreada(res.id, formData.get('nombre'), comp);
                                if (modalInstanciaOIE) modalInstanciaOIE.hide();
                            } else {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Éxito!',
                                    text: res.mensaje,
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            }
                        } else {
                            Swal.fire('Error', res.mensaje, 'error');
                            btn.disabled = false;
                            btn.innerHTML = origTxt;
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Error en conexión.', 'error');
                        btn.disabled = false;
                        btn.innerHTML = origTxt;
                    });
            });
        }
    });

    function selectCuentaOIE(item) {
        const inpId = document.getElementById('oie-idcuenta');
        const inpSrc = document.getElementById('oie-src-cuenta');
        const drop = document.getElementById('oie-cuenta-drop');
        const btnClear = document.getElementById('oie-btn-clear-cuenta');

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

    function abrirModalOpcion(id = null) {
        const frm = document.getElementById('frmModalOIE');
        if (!frm) return;
        frm.reset();
        document.getElementById('oie-id').value = '';

        const btnElim = document.getElementById('btnEliminarOIE');
        if (btnElim) btnElim.classList.add('d-none');

        selectCuentaOIE(null);

        document.getElementById('oie-rdo-ingreso').checked = true;
        document.getElementById('oie-comportamiento').value = 'GENERAL';
        const swActivo = document.getElementById('oie-sw-activo');
        swActivo.checked = true;
        swActivo.nextElementSibling.nextElementSibling.innerText = 'Estado: Activo';
        document.getElementById('oie-input-estado').value = 'ACTIVO';

        if (!id) {
            document.getElementById('modalOIETitulo').innerText = 'Nueva Opción';
            if (modalInstanciaOIE) modalInstanciaOIE.show();
        } else {
            document.getElementById('modalOIETitulo').innerText = 'Editar Opción';
            fetch(`<?= $urlBase ?>/getAjax?id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        const d = res.data;
                        document.getElementById('oie-id').value = d.id;
                        document.getElementById('oie-nombre').value = d.nombre;
                        document.getElementById('oie-comportamiento').value = d.comportamiento || 'GENERAL';

                        const checkBool = (val) => val === true || val === 't' || val === '1' || val === 1;
                        const resIng = checkBool(d.aplica_ingresos);
                        const resEgr = checkBool(d.aplica_egresos);
                        
                        if (resIng) {
                            document.getElementById('oie-rdo-ingreso').checked = true;
                        } else if (resEgr) {
                            document.getElementById('oie-rdo-egreso').checked = true;
                        }

                        const isActive = (d.estado && d.estado.toUpperCase() === 'ACTIVO');
                        const swObj = document.getElementById('oie-sw-activo');
                        swObj.checked = isActive;
                        swObj.nextElementSibling.nextElementSibling.innerText = isActive ? 'Estado: Activo' : 'Estado: Inactivo';
                        document.getElementById('oie-input-estado').value = isActive ? 'ACTIVO' : 'INACTIVO';

                        if (d.id_cuenta_contable) {
                            selectCuentaOIE({
                                id: d.id_cuenta_contable,
                                codigo: d.cuenta_codigo,
                                nombre: d.cuenta_nombre
                            });
                        }

                        if (btnElim) btnElim.classList.remove('d-none');
                        if (modalInstanciaOIE) modalInstanciaOIE.show();
                    } else {
                        Swal.fire('Error', res.mensaje, 'error');
                    }
                });
        }
    }

    function eliminarOIE() {
        const id = document.getElementById('oie-id').value;
        if (!id) return;

        Swal.fire({
            title: '¿Está seguro?',
            text: "Esta acción desactivará lógicamente la opción.",
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
                fetch(`<?= $urlBase ?>/eliminarAjax`, {
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