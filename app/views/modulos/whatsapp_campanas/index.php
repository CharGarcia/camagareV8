<?php
// Vista de Campañas Masivas
?>

<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="mb-0 text-dark" style="font-family: 'Outfit', sans-serif; font-weight: 600;">
                <i class="bi bi-megaphone-fill text-success me-2"></i> Campañas Masivas
            </h4>
        </div>
    </div>

    <?php if (!$configurado): ?>
    <div class="alert alert-warning border-0 shadow-sm rounded-3">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning me-3"></i>
            <div>
                <h5 class="mb-1 fw-bold">WhatsApp no está configurado</h5>
                <p class="mb-0">Para enviar campañas masivas, primero debes configurar tu Token de Acceso y Phone ID en el módulo de <strong>Configuración de WhatsApp</strong>.</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <div class="row">
        <!-- Configuracion de Plantilla -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0" style="font-family: 'Outfit', sans-serif;"><i class="bi bi-file-earmark-text me-2"></i>1. Configurar Mensaje</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold">Seleccionar Plantilla Aprobada</label>
                        <select class="form-select" id="campanaPlantilla">
                            <option value="">Cargando plantillas...</option>
                        </select>
                        <div class="form-text">Solo se muestran plantillas sincronizadas y con estado APPROVED.</div>
                    </div>
                    
                    <div id="campanaVariablesContainer" class="d-none">
                        <hr>
                        <h6 class="fw-bold mb-3">Mapeo de Variables</h6>
                        <p class="text-muted small">Esta plantilla requiere que definas qué dato del cliente se insertará en cada variable.</p>
                        <div id="campanaVariablesInputs"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seleccion de Clientes -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0 rounded-4 cmg-table-card h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="font-family: 'Outfit', sans-serif;"><i class="bi bi-people-fill me-2"></i>2. Destinatarios</h5>
                    <div class="d-flex align-items-center">
                        <div class="input-group input-group-sm me-3" style="width: 250px;">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="buscadorClientes" class="form-control border-start-0 bg-light" placeholder="Buscar cliente...">
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3">
                            <span id="contadorSeleccionados">0</span> seleccionados
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-hover align-middle mb-0" id="tablaClientesCampana">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-4" style="width: 40px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="checkAllClientes">
                                        </div>
                                    </th>
                                    <th>Nombre del Cliente</th>
                                    <th>Identificación</th>
                                    <th>Teléfono</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyClientes">
                                <tr><td colspan="4" class="text-center py-4 text-muted">Cargando clientes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ejecucion -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-4 bg-light">
                <div class="card-body p-4 text-center">
                    <div class="d-flex flex-column align-items-center">
                        <i class="bi bi-send-fill fs-1 text-success mb-3"></i>
                        <h4 style="font-family: 'Outfit', sans-serif;">Iniciar Envío Masivo</h4>
                        <p class="text-muted w-50 mx-auto">Al iniciar, el sistema enviará los mensajes uno a uno. Por favor, <strong>no cierres ni recargues esta pestaña</strong> hasta que el proceso haya finalizado.</p>
                        
                        <div class="w-75 mx-auto mt-3 d-none" id="progresoContainer">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small fw-bold" id="progresoTexto">Enviando 0 de 0</span>
                                <span class="text-success small fw-bold" id="progresoPorcentaje">0%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progresoBarra" role="progressbar" style="width: 0%"></div>
                            </div>
                            
                            <div class="mt-3 text-start bg-white p-3 rounded border" style="max-height: 150px; overflow-y: auto; font-family: monospace; font-size: 12px;" id="progresoLog">
                                <div class="text-muted">Esperando iniciar...</div>
                            </div>
                        </div>

                        <button class="btn btn-success btn-lg mt-4 px-5 rounded-pill shadow-sm" id="btnIniciarCampana">
                            <i class="bi bi-rocket-takeoff me-2"></i> Iniciar Campaña
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cargar JS Específico -->
    <script>
        const B_URL = '<?= rtrim(BASE_URL, '/') ?>';
    </script>
    <script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/whatsapp_campanas.js?v=<?= time() ?>"></script>
    <?php endif; ?>
</div>
