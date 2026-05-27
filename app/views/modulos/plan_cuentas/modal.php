<?php
/**
 * Componente Modal Reutilizable para Plan de Cuentas
 * Permite Crear/Editar cuentas desde cualquier módulo.
 */
?>
<!-- Modal Principal (Edit/Create) -->
<div class="modal fade" id="modalPC" tabindex="-1" aria-hidden="true" data-bs-backdrop="static text-dark">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form id="formPC" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i><span id="tituloModalPC">Cuenta</span></h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="modalAlertPC" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>
                    
                    <div id="wrapper-parent-info-pc" class="alert alert-primary py-1 mb-2 small border-0 shadow-sm d-none" style="background-color: rgba(13, 110, 253, 0.05); color: #0d6efd;">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <span>La cuenta que se va a crear estará dentro de: <strong id="parent-account-name-pc"></strong></span>
                    </div>

                    <!-- Visualización de cuentas existentes en el mismo nivel -->
                    <div id="wrapper-existing-accounts-pc" class="mb-3 d-none p-2 border rounded bg-light bg-opacity-50">
                        <h6 class="small fw-bold text-muted mb-1" style="font-size: 0.7rem;"><i class="bi bi-list-ul me-1"></i> CUENTAS EXISTENTES EN ESTE NIVEL</h6>
                        <div id="existing-accounts-list-pc" class="list-group list-group-flush" style="max-height: 100px; overflow-y: auto;">
                            <!-- Items dinámicos -->
                        </div>
                    </div>

                    <input type="hidden" name="id" id="pc_id" value="">

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Código</label>
                            <input type="text" class="form-control form-control-sm bg-light fw-bold" name="codigo" id="pc_codigo_edit" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Nivel</label>
                            <input type="text" class="form-control form-control-sm bg-light text-center" name="nivel" id="pc_nivel_edit" readonly>
                        </div>
                        <div class="col-md-4 text-end">
                            <label class="form-label small fw-bold d-block">Estado</label>
                            <div class="form-check form-switch d-inline-block mt-1">
                                <input class="form-check-input shadow-none" type="checkbox" role="switch" name="status" id="pc_status" value="1" checked>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Nombre de la Cuenta *</label>
                            <input type="text" class="form-control form-control-sm shadow-none" name="nombre" id="pc_nombre_edit" required placeholder="Ingrese el nombre...">
                        </div>
                        
                        <!-- Campos exclusivos de Nivel 5 -->
                        <div class="col-md-6 wrapper-nivel-5-pc d-none">
                            <label class="form-label small fw-bold">Centro de Costo</label>
                            <select class="form-select form-select-sm shadow-none" name="id_centro_costos" id="pc_id_centro_costos">
                                <option value="">-- Seleccionar --</option>
                                <?php if(isset($centros)): foreach ($centros as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6 wrapper-nivel-5-pc d-none">
                            <label class="form-label small fw-bold">Proyecto</label>
                            <select class="form-select form-select-sm shadow-none" name="id_proyecto" id="pc_id_proyecto">
                                <option value="">-- Seleccionar --</option>
                                <?php if(isset($proyectos)): foreach ($proyectos as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="wrapper-nivel-5-pc d-none mt-3">
                        <div class="accordion accordion-flush" id="accordionPCControl">
                            <div class="accordion-item border-0 bg-transparent">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 px-0 fw-bold small text-muted bg-transparent shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePCControl" style="border-bottom: 1px dashed #dee2e6;">
                                        <i class="bi bi-gear-fill me-2"></i> Configurar códigos entidades de control
                                    </button>
                                </h2>
                                <div id="collapsePCControl" class="accordion-collapse collapse">
                                    <div class="accordion-body px-0 pt-3">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small fw-bold text-muted">Código SRI</label>
                                                <input type="text" class="form-control form-control-sm shadow-none" name="codigo_sri" id="pc_codigo_sri">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-muted">Supercias ESF</label>
                                                <input type="text" class="form-control form-control-sm shadow-none" name="supercias_esf" id="pc_supercias_esf">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-muted">Supercias ERI</label>
                                                <input type="text" class="form-control form-control-sm shadow-none" name="supercias_eri" id="pc_supercias_eri">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-muted">Supercias ECP Subcódigo</label>
                                                <input type="text" class="form-control form-control-sm shadow-none" name="supercias_ecp_subcodigo" id="pc_supercias_ecp_subcodigo">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-muted">Supercias ECP Código</label>
                                                <input type="text" class="form-control form-control-sm shadow-none" name="supercias_ecp_codigo" id="pc_supercias_ecp_codigo">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-0 px-4 py-3">
                    <div id="wrapper-auditoria-pc" class="small text-muted d-none">
                        <i class="bi bi-info-circle me-1"></i> <span id="info_creado_at_pc" title="Creado"></span> | <span id="info_creado_por_pc"></span>
                    </div>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-link text-muted btn-sm text-decoration-none px-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarPC"><i class="bi bi-check-lg me-1"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const urlBasePC = '<?= BASE_URL ?>/modulos/plan-cuentas';
        let modalPCInst = null;
        let pcuentasRawLocal = []; // Cache local si es necesario, pero usualmente vendrá del padre o se recargará

        function getModalPC() {
            if (!modalPCInst) {
                modalPCInst = new bootstrap.Modal(document.getElementById('modalPC'));
            }
            return modalPCInst;
        }

        // Exponer funciones al global
        window.abrirModalPCGeneral = function(data = null) {
            const form = document.getElementById('formPC');
            form.reset();
            document.getElementById('pc_id').value = '';
            document.getElementById('wrapper-auditoria-pc').classList.add('d-none');
            document.getElementById('wrapper-parent-info-pc').classList.add('d-none');
            document.getElementById('wrapper-existing-accounts-pc').classList.add('d-none');
            document.getElementById('modalAlertPC').classList.add('d-none');
            
            if (data) {
                // Modo Edición
                document.getElementById('tituloModalPC').textContent = 'Editar Cuenta';
                document.getElementById('pc_id').value = data.id;
                document.getElementById('pc_codigo_edit').value = data.codigo;
                document.getElementById('pc_nivel_edit').value = data.nivel;
                document.getElementById('pc_nombre_edit').value = data.nombre;
                document.getElementById('pc_status').checked = (parseInt(data.status) === 1);
                
                if (parseInt(data.nivel) === 5) {
                    document.querySelectorAll('.wrapper-nivel-5-pc').forEach(el => el.classList.remove('d-none'));
                    document.getElementById('pc_id_centro_costos').value = data.id_centro_costos || '';
                    document.getElementById('pc_id_proyecto').value = data.id_proyecto || '';
                    document.getElementById('pc_codigo_sri').value = data.codigo_sri || '';
                    document.getElementById('pc_supercias_esf').value = data.supercias_esf || '';
                    document.getElementById('pc_supercias_eri').value = data.supercias_eri || '';
                    document.getElementById('pc_supercias_ecp_codigo').value = data.supercias_ecp_codigo || '';
                    document.getElementById('pc_supercias_ecp_subcodigo').value = data.supercias_ecp_subcodigo || '';
                } else {
                    document.querySelectorAll('.wrapper-nivel-5-pc').forEach(el => el.classList.add('d-none'));
                }
            }
            getModalPC().show();
        };

        window.abrirModalCrearHijoBusqueda = async function(codigoPadre) {
            try {
                const resp = await fetch(`${urlBasePC}/getNextCodigoAjax?padre=${codigoPadre}`);
                const json = await resp.json();
                if (!json.ok) return alert(json.error);

                abrirModalPCGeneral({
                    codigo: json.codigo,
                    nivel: json.nivel,
                    status: 1
                });
                
                document.getElementById('tituloModalPC').textContent = 'Nueva Subcuenta';
                
                // Intentar cargar breadcrumb si pcuentasRaw existe en el global del padre
                if (window.getBreadcrumb) {
                    const breadcrumb = window.getBreadcrumb(codigoPadre);
                    if (breadcrumb) {
                        document.getElementById('wrapper-parent-info-pc').classList.remove('d-none');
                        document.getElementById('parent-account-name-pc').textContent = breadcrumb;
                    }
                }
            } catch (e) { console.error(e); }
        };

        document.getElementById('formPC').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnGuardarPC');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            try {
                const formData = new FormData(this);
                const id = formData.get('id');
                const url = `${urlBasePC}/${id ? 'update' : 'store'}`;

                const resp = await fetch(url, { method: 'POST', body: formData });
                const json = await resp.json();

                if (json.ok) {
                    getModalPC().hide();
                    if (window.onAccountSaved) window.onAccountSaved(json);
                    // Si estamos en la página del plan de cuentas, recargar el árbol
                    if (window.fetchTree) window.fetchTree();
                } else {
                    const alertDiv = document.getElementById('modalAlertPC');
                    alertDiv.textContent = json.error || 'Error al guardar';
                    alertDiv.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                    alertDiv.classList.remove('d-none');
                }
            } catch (e) {
                console.error(e);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
            }
        });

        // Mayúsculas para niveles 1-4
        document.getElementById('pc_nombre_edit').addEventListener('input', function() {
            const nivel = parseInt(document.getElementById('pc_nivel_edit').value);
            if (nivel >= 1 && nivel <= 4) this.value = this.value.toUpperCase();
        });

    })();
</script>
