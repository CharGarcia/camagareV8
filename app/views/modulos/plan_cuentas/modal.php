<?php
/**
 * Componente Modal Reutilizable para Plan de Cuentas
 * Permite Crear/Editar cuentas desde cualquier módulo.
 */
?>
<!-- Modal Principal (Edit/Create) -->
<div class="modal fade" id="modalPC" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form id="formPC" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i><span id="tituloModalPC">Cuenta</span></h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <?php
                    // Pestañas General / Centro de costos / Proyectos / SRI / Supercias (compartidas con el otro modal de cuenta).
                    $pcIds = ['alert' => 'modalAlertPC', 'parent' => 'wrapper-parent-info-pc', 'parentName' => 'parent-account-name-pc', 'existing' => 'wrapper-existing-accounts-pc', 'existingList' => 'existing-accounts-list-pc', 'nivel5' => 'wrapper-nivel-5-pc'];
                    include __DIR__ . '/_form_cuenta.php';
                    ?>
                </div>
                <div class="modal-footer justify-content-between bg-light border-0 px-4 py-3">
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

        // Campos editables del formulario (el código y el nivel son siempre de solo lectura:
        // el código de una cuenta nunca se modifica una vez creada; lo refuerza el Service).
        const camposEditablesPC = ['pc_status', 'pc_nombre_edit',
            'pc_codigo_sri', 'pc_supercias_esf', 'pc_supercias_eri', 'pc_supercias_ecp_codigo', 'pc_supercias_ecp_subcodigo'];

        function setSoloLecturaPC(soloLectura) {
            camposEditablesPC.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.disabled = !!soloLectura;
            });
            document.getElementById('btnGuardarPC').classList.toggle('d-none', !!soloLectura);
        }

        /**
         * Abre el modal. `data` = cuenta a editar (null = crear).
         * `opts.soloLectura` = true abre en modo consulta (campos deshabilitados, sin Guardar),
         * para usuarios sin permiso de actualizar en Plan de Cuentas.
         */
        window.abrirModalPCGeneral = function(data = null, opts = {}) {
            const form = document.getElementById('formPC');
            form.reset();
            document.getElementById('pc_id').value = '';
            document.getElementById('wrapper-parent-info-pc').classList.add('d-none');
            document.getElementById('wrapper-existing-accounts-pc').classList.add('d-none');
            document.getElementById('modalAlertPC').classList.add('d-none');
            setSoloLecturaPC(!!opts.soloLectura);

            if (data) {
                // Modo Edición
                document.getElementById('tituloModalPC').textContent = opts.soloLectura ? 'Cuenta Contable' : 'Editar Cuenta';
                document.getElementById('pc_id').value = data.id;
                document.getElementById('pc_codigo_edit').value = data.codigo;
                document.getElementById('pc_nivel_edit').value = data.nivel;
                document.getElementById('pc_nombre_edit').value = data.nombre;
                document.getElementById('pc_status').checked = (parseInt(data.status) === 1);

                if (parseInt(data.nivel) === 5) {
                    document.getElementById('pc_codigo_sri').value = data.codigo_sri || '';
                    document.getElementById('pc_supercias_esf').value = data.supercias_esf || '';
                    document.getElementById('pc_supercias_eri').value = data.supercias_eri || '';
                    document.getElementById('pc_supercias_ecp_codigo').value = data.supercias_ecp_codigo || '';
                    document.getElementById('pc_supercias_ecp_subcodigo').value = data.supercias_ecp_subcodigo || '';
                }
            }
            // Pestañas: vuelve a General; Centro de costos, Proyectos, SRI y Supercias solo en nivel 5.
            window.PCFORM_abrir(data ? (data.id || 0) : 0, data ? data.nivel : 0, !!opts.soloLectura);
            getModalPC().show();
        };

        window.abrirModalCrearHijoBusqueda = async function(codigoPadre) {
            try {
                const resp = await fetch(`${urlBasePC}/getNextCodigoAjax?padre=${codigoPadre}`);
                const json = await resp.json();
                if (!json.ok) return Swal.fire('Error', json.error, 'error');

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
