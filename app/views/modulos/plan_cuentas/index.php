<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var int $conteoTotal */
/** @var array $centros */
/** @var array $proyectos */

$base = BASE_URL;
$urlBasePC = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$conteoTotal = $conteoTotal ?? 0;
$rows       = $rows ?? [];
$centros    = $centros ?? [];
$proyectos  = $proyectos ?? [];
?>
<style>
    .pc-header { flex-shrink: 0; }
    .pc-content { max-height: calc(100dvh - 200px); overflow-y: auto; padding-bottom: 20px; }
    .accordion-button:not(.collapsed) { background-color: rgba(13, 110, 253, 0.05); color: #0d6efd; box-shadow: none; }
    .accordion-button:focus { box-shadow: none; border-color: rgba(0,0,0,.125); }
    .accordion-item { border: 1px solid rgba(0,0,0,.08); margin-bottom: 4px; border-radius: 8px !important; overflow: hidden; }
    .accordion-body { padding: 0.25rem 0.5rem 0.25rem 1rem; border-top: 1px solid rgba(0,0,0,0.05); }
    .pc-row-item { transition: background 0.2s; border-left: 3px solid transparent; }
    .pc-row-item:hover { background: #f8f9fa; }
    .pc-row-item.nivel-5 { border-left-color: #0dcaf0; }
    .pc-row-item.nivel-4 { border-left-color: #ffc107; }
    .pc-row-item.nivel-3 { border-left-color: #6610f2; }
    .pc-row-item.nivel-2 { border-left-color: #fd7e14; }
    .pc-row-item.nivel-1 { border-left-color: #0d6efd; }
    .badge-nivel { width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
</style>

<div class="pc-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center">
        <h5 class="mb-0 fw-bold me-3 align-middle"><i class="bi bi-list-nested"></i> <?= htmlspecialchars($titulo) ?></h5>
        <?php if ($perm['crear'] && $conteoTotal === 0): ?>
            <button type="button" id="btnInitPlan" class="btn btn-primary shadow-sm btn-sm px-3" onclick="cargarPlanModelo()">
                <i class="bi bi-magic"></i> Cargar Plan de Cuentas Modelo
            </button>
        <?php endif; ?>
        <?php if (!empty($perm['eliminar']) && $conteoTotal > 0): ?>
            <button type="button" id="btnEliminarPlan" class="btn btn-outline-danger shadow-sm btn-sm px-3" onclick="eliminarPlanCompleto()">
                <i class="bi bi-trash3"></i> Eliminar Plan de Cuentas
            </button>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <div class="input-group input-group-sm" style="width: 250px;">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="buscarPC" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar cuenta..." onkeyup="debounceSearch()">
        </div>
        <div class="btn-group btn-group-sm shadow-sm">
            <a href="<?= $urlBasePC ?>/export-pdf" class="btn btn-white border px-3" title="Descargar PDF"><i class="bi bi-file-earmark-pdf text-danger"></i></a>
            <a href="<?= $urlBasePC ?>/export-excel" class="btn btn-white border px-3" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet text-success"></i></a>
            <button type="button" class="btn btn-white border px-3" title="Importar desde Excel" onclick="document.getElementById('importFile').click()"><i class="bi bi-upload text-primary"></i></button>
            <a href="<?= $urlBasePC ?>/download-example" class="btn btn-white border px-3" title="Ejemplo Excel"><i class="bi bi-download text-muted"></i></a>
        </div>
        <input type="file" id="importFile" class="d-none" accept=".xlsx, .xls" onchange="handleImport(this)">
    </div>
</div>

<div class="pc-content" id="pc-accordion-container">
    <?php if ($conteoTotal === 0): ?>
        <div class="card p-5 text-center text-muted border-dashed">
            <i class="bi bi-magic fs-1 d-block mb-3 text-primary opacity-25"></i>
            <h5>Plan de Cuentas Vacío</h5>
            <p>Haga clic en el botón superior para inicializar la estructura contable base.</p>
        </div>
    <?php else: ?>
        <div id="tree-container" class="accordion accordion-flush bg-transparent">
            <!-- Cargando... -->
        </div>
    <?php endif; ?>
</div>

<!-- Modal Principal (Edit/Create Child) -->
<div class="modal fade" id="modalPC" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form id="formPC" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i><span id="tituloModal">Cuenta</span></h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="modalAlert" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>
                    
                    <div id="wrapper-parent-info" class="alert alert-primary py-1 mb-2 small border-0 shadow-sm d-none" style="background-color: rgba(13, 110, 253, 0.05); color: #0d6efd;">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <span>La cuenta que se va a crear estará dentro de: <strong id="parent-account-name"></strong></span>
                    </div>

                    <!-- Visualización de cuentas existentes en el mismo nivel -->
                    <div id="wrapper-existing-accounts" class="mb-3 d-none p-2 border rounded bg-light bg-opacity-50">
                        <h6 class="small fw-bold text-muted mb-1" style="font-size: 0.7rem;"><i class="bi bi-list-ul me-1"></i> CUENTAS EXISTENTES EN ESTE NIVEL</h6>
                        <div id="existing-accounts-list" class="list-group list-group-flush" style="max-height: 100px; overflow-y: auto;">
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
                        <div class="col-md-6 wrapper-nivel-5 d-none">
                            <label class="form-label small fw-bold">Centro de Costo</label>
                            <select class="form-select form-select-sm shadow-none" name="id_centro_costos" id="pc_id_centro_costos">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($centros as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 wrapper-nivel-5 d-none">
                            <label class="form-label small fw-bold">Proyecto</label>
                            <select class="form-select form-select-sm shadow-none" name="id_proyecto" id="pc_id_proyecto">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($proyectos as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="wrapper-nivel-5 d-none mt-3">
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
                <div class="modal-footer flex-column align-items-stretch bg-light border-0 px-4 py-3">

                    <div class="d-flex justify-content-between align-items-center">
                        <div id="wrapper-auditoria-simple" class="small text-muted d-none">
                            <i class="bi bi-info-circle me-1"></i> <span id="info_creado_at" title="Creado"></span>
                        </div>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-link text-muted btn-sm text-decoration-none px-3" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardar"><i class="bi bi-check-lg me-1"></i> Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBasePC ?>';
        const form = document.getElementById('formPC');
        let modalInst = null;
        let pcuentasRaw = []; // Almacenará la lista plana para procesar el árbol

        function getModal() {
            if (!modalInst) modalInst = new bootstrap.Modal(document.getElementById('modalPC'));
            return modalInst;
        }

        function toggleLevel5Fields(nivel) {
            const isNivel5 = (parseInt(nivel) === 5);
            const wrappers = document.querySelectorAll('.wrapper-nivel-5');
            wrappers.forEach(el => isNivel5 ? el.classList.remove('d-none') : el.classList.add('d-none'));
        }

        // Auto-uppercase logic for levels 1-4
        document.getElementById('pc_nombre_edit').addEventListener('input', function() {
            const nivel = parseInt(document.getElementById('pc_nivel_edit').value);
            if (nivel >= 1 && nivel <= 4) this.value = this.value.toUpperCase();
        });

        function renderExistingSiblings(codigoPadre) {
            const wrapper = document.getElementById('wrapper-existing-accounts');
            const list = document.getElementById('existing-accounts-list');
            const countDots = (str) => (str.match(/\./g) || []).length;
            
            const siblings = pcuentasRaw.filter(x => {
                if (!codigoPadre) return parseInt(x.nivel) === 1;
                return x.codigo.startsWith(codigoPadre + '.') && countDots(x.codigo) === countDots(codigoPadre) + 1;
            });

            if (siblings.length > 0) {
                wrapper.classList.remove('d-none');
                list.innerHTML = siblings.map(s => `
                    <div class="list-group-item bg-transparent py-1 px-0 border-0 d-flex justify-content-between align-items-center">
                        <div class="text-truncate" style="max-width: 85%;">
                            <code class="text-primary fw-bold me-2" style="font-size: 0.85rem;">${s.codigo}</code> 
                            <span class="text-dark small">${s.nombre}</span>
                        </div>
                        <span class="status-dot" style="background-color: ${s.status == 1 ? '#198754' : '#6c757d'}"></span>
                    </div>
                `).join('');
            } else {
                wrapper.classList.add('d-none');
            }
        }

        function countDots(str) {
            return (str.match(/\./g) || []).length;
        }

        function getBreadcrumb(codigo) {
            if (!codigo) return '';
            const parts = codigo.split('.');
            const crumbs = [];
            for (let i = 1; i <= parts.length; i++) {
                const subCode = parts.slice(0, i).join('.');
                const match = pcuentasRaw.find(x => x.codigo === subCode);
                if (match) crumbs.push(match.nombre);
            }
            return crumbs.join(' / ');
        }
        function buildTree(list) {
            const map = {};
            const roots = [];
            
            list.forEach(item => {
                map[item.codigo] = { ...item, children: [] };
            });

            list.forEach(item => {
                const parts = item.codigo.split('.');
                if (parts.length === 1) {
                    roots.push(map[item.codigo]);
                } else {
                    const parentCode = parts.slice(0, -1).join('.');
                    if (map[parentCode]) {
                        map[parentCode].children.push(map[item.codigo]);
                    } else {
                        // Fallback si el padre no está (ej: por filtrado)
                        roots.push(map[item.codigo]);
                    }
                }
            });
            return roots;
        }

        function renderAccordionTree(nodes, containerId, parentCollapseId = 'root') {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            nodes.forEach((node, nodeIdx) => {
                const safeId = 'acc-' + node.codigo.replace(/\./g, '-');
                const collapseId = 'collapse-' + safeId;
                const hasChildren = node.children && node.children.length > 0;
                
                const item = document.createElement('div');
                item.className = 'accordion-item border-0';
                
                // Generar badges de control
                const sriTag = node.codigo_sri ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-2 px-2" style="font-size: 0.6rem;">SRI: ${node.codigo_sri}</span>` : '';
                const esfTag = node.supercias_esf ? `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-1 px-2" style="font-size: 0.6rem;">ESF: ${node.supercias_esf}</span>` : '';
                const eriTag = node.supercias_eri ? `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-1 px-2" style="font-size: 0.6rem;">ERI: ${node.supercias_eri}</span>` : '';
                const ecpTag = node.supercias_ecp_codigo ? `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-1 px-2" style="font-size: 0.6rem;">ECP: ${node.supercias_ecp_subcodigo ? node.supercias_ecp_subcodigo + '-' : ''}${node.supercias_ecp_codigo}</span>` : '';
                
                const controlTags = `${sriTag}${esfTag}${eriTag}${ecpTag}`;

                if (hasChildren || parseInt(node.nivel) < 5) {
                    const header = `
                        <div class="accordion-header d-flex align-items-center pc-row-item nivel-${node.nivel}">
                            <button class="accordion-button collapsed py-1 shadow-none flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                                <span class="badge bg-light text-dark border badge-nivel me-2" style="width: 20px; height: 20px; font-size: 0.65rem;">${node.nivel}</span>
                                <code class="text-muted small me-2" style="font-size: 0.75rem;">${node.codigo}</code>
                                <span class="${parseInt(node.nivel) < 5 ? 'fw-bold text-uppercase' : ''} text-dark" style="font-size: 0.85rem;">${node.nombre} ${controlTags}</span>
                                <span class="status-dot ms-2" style="background-color: ${node.status == 1 ? '#198754' : '#6c757d'}"></span>
                            </button>
                            <div class="pe-3 d-flex gap-1 bg-white">
                                <button class="btn btn-outline-primary btn-xs border-0 rounded-circle" onclick="abrirModalEditarLocal('${node.codigo}')" title="Configuración"><i class="bi bi-gear"></i></button>
                                ${parseInt(node.nivel) < 5 ? `<button class="btn btn-outline-success btn-xs border-0 rounded-circle" onclick="abrirModalCrearHijo('${node.codigo}')" title="Añadir"><i class="bi bi-plus-circle"></i></button>` : ''}
                                ${parseInt(node.nivel) > 1 && !hasChildren ? `<button class="btn btn-outline-danger btn-xs border-0 rounded-circle" onclick="eliminarAccionDetalle(${node.id})" title="Borrar"><i class="bi bi-trash"></i></button>` : ''}
                            </div>
                        </div>
                        <div id="${collapseId}" class="accordion-collapse collapse">
                            <div class="accordion-body pe-0 py-0 ms-3" id="body-${collapseId}">
                                <!-- Hijos se renderizan aquí -->
                            </div>
                        </div>
                    `;
                    item.innerHTML = header;
                    container.appendChild(item);
                    
                    if (hasChildren) {
                        renderAccordionTree(node.children, `body-${collapseId}`);
                    } else {
                        const body = document.getElementById(`body-${collapseId}`);
                        if (body) body.innerHTML = '<div class="py-2 text-muted small ps-4">Sin subcuentas</div>';
                    }
                } else {
                    item.innerHTML = `
                        <div class="d-flex align-items-center py-1 px-3 pc-row-item nivel-${node.nivel}">
                            <span class="ms-3 me-2 text-muted opacity-50"><i class="bi bi-dash"></i></span>
                            <span class="badge bg-light text-dark border badge-nivel me-2" style="width: 20px; height: 20px; font-size: 0.65rem;">${node.nivel}</span>
                            <code class="text-muted small me-2" style="font-size: 0.75rem;">${node.codigo}</code>
                            <span class="text-dark flex-grow-1" style="font-size: 0.85rem;">${node.nombre} ${controlTags}</span>
                            <span class="status-dot ms-2" style="background-color: ${node.status == 1 ? '#198754' : '#6c757d'}"></span>
                            <div class="d-flex gap-1 ms-3">
                                <button class="btn btn-outline-primary btn-xs border-0 rounded-circle" onclick="abrirModalEditarLocal('${node.codigo}')" title="Configuración"><i class="bi bi-gear"></i></button>
                                <button class="btn btn-outline-danger btn-xs border-0 rounded-circle" onclick="eliminarAccionDetalle(${node.id})" title="Borrar"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    `;
                    container.appendChild(item);
                }
            });
        }

        window.fetchTree = async function() {
            const buscar = document.getElementById('buscarPC').value;
            const url = `${urlBase}/searchAjax?b=${encodeURIComponent(buscar)}&page=1&perPage=1000`; // Traer todos para armar el árbol
            try {
                const resp = await fetch(url);
                const json = await resp.json();
                if (json.ok) {
                    pcuentasRaw = json.data_raw || []; // Necesitamos devolver data_raw en el controller
                    const tree = buildTree(pcuentasRaw);
                    renderAccordionTree(tree, 'tree-container');
                }
            } catch (e) { console.error(e); }
        };

        let searchTimeout;
        window.debounceSearch = function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => fetchTree(), 400);
        };

        window.handleImport = async function(input) {
            if (!input.files || input.files.length === 0) return;
            if (!confirm('Este proceso cargará las cuentas desde el archivo. ¿Continuar?')) {
                input.value = '';
                return;
            }
            
            const fd = new FormData();
            fd.append('excel_file', input.files[0]);
            
            try {
                const resp = await fetch(`${urlBase}/importExcel`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    alert(json.msg);
                    location.reload();
                } else {
                    alert('Error: ' + json.error);
                }
            } catch (e) { alert('Error de conexión'); }
            finally { input.value = ''; }
        };

        window.cargarPlanModelo = async function() {
            const result = await Swal.fire({
                title: 'Cargar Plan Modelo',
                text: 'Se cargarán las cuentas faltantes de la estructura contable comercial estándar. ¿Deseas continuar?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, cargar cuentas',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;

            try {
                const formData = new FormData();
                formData.append('configurar', 'false');
                
                const resp = await fetch(`${urlBase}/cargarModeloAjax`, {
                    method: 'POST',
                    body: formData
                });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire('¡Éxito!', json.msg, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', json.error, 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Error de conexión', 'error');
            }
        };

        window.eliminarPlanCompleto = async function() {
            const result = await Swal.fire({
                title: '¿Eliminar todo el plan de cuentas?',
                html: 'Se eliminarán <b>todas</b> las cuentas de esta empresa.<br>Esta acción solo es posible si ninguna cuenta tiene movimientos contables.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar todo',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });

            if (!result.isConfirmed) return;

            try {
                const resp = await fetch(`${urlBase}/eliminarPlanAjax`, { method: 'POST' });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire('Eliminado', json.msg, 'success').then(() => location.reload());
                } else {
                    Swal.fire('No se pudo eliminar', json.error, 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Error de conexión', 'error');
            }
        };

        window.abrirModalEditarLocal = function(codigo) {
            const item = pcuentasRaw.find(x => x.codigo === codigo);
            if (!item) return;
            
            form.reset();
            document.getElementById('pc_id').value = item.id;
            document.getElementById('pc_codigo_edit').value = item.codigo;
            document.getElementById('pc_nivel_edit').value = item.nivel;
            document.getElementById('pc_nombre_edit').value = item.nombre;
            document.getElementById('pc_id_centro_costos').value = item.id_centro_costos || '';
            document.getElementById('pc_id_proyecto').value = item.id_proyecto || '';
            document.getElementById('pc_codigo_sri').value = item.codigo_sri || '';
            document.getElementById('pc_supercias_esf').value = item.supercias_esf || '';
            document.getElementById('pc_supercias_eri').value = item.supercias_eri || '';
            document.getElementById('pc_supercias_ecp_codigo').value = item.supercias_ecp_codigo || '';
            document.getElementById('pc_supercias_ecp_subcodigo').value = item.supercias_ecp_subcodigo || '';
            document.getElementById('pc_status').checked = (parseInt(item.status) === 1);
            
            toggleLevel5Fields(item.nivel);
            document.getElementById('tituloModal').textContent = 'Configuración de Cuenta';
            document.getElementById('modalAlert').classList.add('d-none');
            document.getElementById('wrapper-parent-info').classList.add('d-none');
            
            // Mostrar hermanos al editar también para referencia
            const parts = item.codigo.split('.');
            const codigoPadre = parts.length > 1 ? parts.slice(0, -1).join('.') : '';
            renderExistingSiblings(codigoPadre);

            getModal().show();
        };



        window.abrirModalCrearHijo = async function(codigoPadre) {
            try {
                const resp = await fetch(`${urlBase}/getNextCodigoAjax?padre=${codigoPadre}`);
                const json = await resp.json();
                if (!json.ok) { alert(json.error); return; }

                form.reset();
                document.getElementById('pc_id').value = '';
                document.getElementById('tituloModal').textContent = 'Nueva Subcuenta';
                document.getElementById('pc_codigo_edit').value = json.codigo;
                document.getElementById('pc_nivel_edit').value = json.nivel;
                const audSimple = document.getElementById('wrapper-auditoria-simple');
                if (audSimple) audSimple.classList.add('d-none');
                document.getElementById('modalAlert').classList.add('d-none');
                
                // Mostrar info del padre
                const breadcrumb = getBreadcrumb(codigoPadre);
                if (breadcrumb) {
                    document.getElementById('wrapper-parent-info').classList.remove('d-none');
                    document.getElementById('parent-account-name').textContent = breadcrumb;
                } else {
                    document.getElementById('wrapper-parent-info').classList.add('d-none');
                }
                
                toggleLevel5Fields(json.nivel);
                renderExistingSiblings(codigoPadre);
                getModal().show();
                setTimeout(() => document.getElementById('pc_nombre_edit').focus(), 400);
            } catch (e) { console.error(e); }
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('pc_id').value;
            const btn = document.getElementById('btnGuardar');
            const alertEl = document.getElementById('modalAlert');
            btn.disabled = true;
            try {
                const fd = new FormData(form);
                const resp = await fetch(id ? `${urlBase}/update` : `${urlBase}/store`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    alertEl.textContent = json.msg;
                    alertEl.className = 'alert alert-success py-2 small shadow-sm border-0';
                    alertEl.classList.remove('d-none');
                    setTimeout(() => { getModal().hide(); fetchTree(); }, 800);
                } else {
                    alertEl.textContent = json.error;
                    alertEl.className = 'alert alert-danger py-2 small shadow-sm border-0';
                    alertEl.classList.remove('d-none');
                }
            } catch (err) { alert('Error de red'); }
            finally { btn.disabled = false; }
        });

        window.eliminarAccionDetalle = async function(id) {
            if (!confirm('¿Seguro que desea eliminar esta cuenta?')) return;
            try {
                const fd = new FormData();
                fd.append('id_eliminar', id);
                const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) fetchTree(); else alert(json.error);
            } catch (e) { alert('Error de conexión'); }
        };

        // Inicializar carga
        if (<?= $conteoTotal ?> > 0) fetchTree();

        // Expansión recursiva al abrir nivel 1
        document.addEventListener('show.bs.collapse', function (event) {
            const el = event.target;
            // Identificar si es un colapsable de nivel 1 (ej: collapse-acc-1)
            // Los códigos de nivel 1 no tienen puntos, por lo que el ID tiene 3 partes: [collapse, acc, codigo]
            if (el.id.startsWith('collapse-acc-') && el.id.split('-').length === 3) {
                const children = el.querySelectorAll('.accordion-collapse');
                children.forEach(child => {
                    const inst = bootstrap.Collapse.getOrCreateInstance(child, { toggle: false });
                    inst.show();
                });
            }
        });

    })();
</script>
