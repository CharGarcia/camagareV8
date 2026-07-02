<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// Asegurar consistencia si el modal se incluye desde otro módulo
$idUsuarioAct = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaAct = (int)($_SESSION['id_empresa'] ?? 0);
$nivelAct = (int)($_SESSION['nivel'] ?? 1);

// 1. Forzar vistaConfig de clientes para ocultar pestañas correctamente
$vistaConfigCli = \App\Helpers\PreferenciasHelper::getPreferenciasVista('clientes');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigCli, 'estiloVistaPestanasCli');

// 2. Forzar permisos de clientes
$permCli = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/clientes') {
    $modelPerm = new \App\models\PermisoSubmodulo();
    $idSubCli = $modelPerm->getIdSubmoduloPorRutaMvc('modulos/clientes');
    if ($idSubCli) {
        $mapPerm = $modelPerm->getPermisosDeUsuario($idUsuarioAct, $idEmpresaAct);
        if (isset($mapPerm[$idSubCli])) {
            $p = $mapPerm[$idSubCli];
            $permCli = [
                'ver' => !empty($p['ver']),
                'crear' => !empty($p['crear']),
                'actualizar' => !empty($p['actualizar']),
                'eliminar' => !empty($p['eliminar']),
                'todo' => !empty($p['t']),
            ];
        } else if ($nivelAct >= 3) {
            $permCli = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
        }
    }
}

$urlBaseCliShared = BASE_URL . '/modulos/clientes';
?>

<?php
// Cargar Leaflet solo una vez (evitar duplicado si ya lo cargó modal_proveedor)
if (!defined('LEAFLET_LOADED')) {
    define('LEAFLET_LOADED', true);
    echo '<link rel="stylesheet" href="' . rtrim(BASE_URL, '/') . '/vendor/leaflet/leaflet.css">';
    echo '<script src="' . rtrim(BASE_URL, '/') . '/vendor/leaflet/leaflet.js"></script>';
}
?>

<!-- Modal Ficha de Cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-labelledby="modalClienteLabel" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form method="POST" action="<?= $urlBaseCliShared ?>/store" id="formCliente" novalidate>
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold" id="modalClienteLabel">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i><span id="tituloModalCliente">Nuevo Cliente</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlert" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="cliente_id" value="">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsCliente" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" href="#pane-general" role="tab"><i class="bi bi-card-text me-1"></i>General</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link py-2 small" id="tab-comercial-btn" data-bs-toggle="tab" href="#pane-comercial" role="tab"><i class="bi bi-bar-chart-fill me-1"></i>Comercial</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link py-2 small" id="tab-cobros-btn" data-bs-toggle="tab" href="#pane-cobros" role="tab"><i class="bi bi-cash-coin me-1"></i>Cobros</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link py-2 small" id="tab-ubicacion-btn" data-bs-toggle="tab" href="#pane-ubicacion" role="tab"><i class="bi bi-geo-alt-fill me-1"></i>Ubicación</a>
                            </li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfigCli = [
                                'tab-comercial'  => 'Comercial',
                                'tab-cobros'     => 'Cobros',
                                'tab-ubicacion'  => 'Ubicación',
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigCli, $vistaConfigCli ?? [], 'clientes');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-3 py-3" id="tabsClienteContent">
                        <!-- Pestaña 1: GENERAL -->
                        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold d-flex align-items-center">Tipo Identificación * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('clientes', 'cliente_tipo_id', 'tipo_id') ?></label>
                                    <select class="form-select form-select-sm" name="tipo_id" id="cliente_tipo_id" required>
                                        <option value="">-- Seleccione --</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="cliente_identificacion" class="form-label small fw-bold d-flex justify-content-between align-items-center">
                                        <span>Identificación *</span>
                                        <span id="sriBadge" class="badge d-none"></span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" name="identificacion" id="cliente_identificacion" required maxlength="20" autocomplete="off" inputmode="numeric">
                                        <span class="input-group-text bg-white px-2 d-none" id="sriSpinnerWrap">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        </span>
                                    </div>
                                    <div id="identificacionError" class="invalid-feedback d-block text-danger small mt-1" style="display:none!important"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('clientes', 'cliente_status', 'status') ?></label>
                                    <select class="form-select form-select-sm" name="status" id="cliente_status">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label for="cliente_nombre" class="form-label small fw-bold">Razón Social / Nombre *</label>
                                    <input type="text" class="form-control form-control-sm" name="nombre" id="cliente_nombre" required maxlength="150" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_email" class="form-label small fw-bold">Correo Electrónico * <small class="text-muted fw-normal">(separados por coma)</small></label>
                                    <input type="text" class="form-control form-control-sm" name="email" id="cliente_email" maxlength="255" placeholder="ej@mail.com, otro@mail.com" autocomplete="off" required>
                                    <div id="emailError" class="text-danger small mt-1" style="display:none"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_telefono" class="form-label small fw-bold">Teléfono</label>
                                    <input type="text" class="form-control form-control-sm" name="telefono" id="cliente_telefono" maxlength="50">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold d-flex align-items-center">Provincia <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('clientes', 'cliente_provincia', 'provincia') ?></label>
                                    <select class="form-select form-select-sm" name="provincia" id="cliente_provincia">
                                        <option value="">- Seleccione provincia -</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_ciudad" class="form-label small fw-bold">Ciudad</label>
                                    <select class="form-select form-select-sm" name="ciudad" id="cliente_ciudad">
                                        <option value="">- Seleccione ciudad -</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label for="cliente_direccion" class="form-label small fw-bold">Dirección Completa</label>
                                    <input type="text" class="form-control form-control-sm" name="direccion" id="cliente_direccion" maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_plazo" class="form-label small fw-bold">Plazo de Crédito (Días)</label>
                                    <input type="number" class="form-control form-control-sm" name="plazo" id="cliente_plazo" min="0" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_vendedor" class="form-label small fw-bold">Vendedor asignado</label>
                                    <select class="form-select form-select-sm" name="id_vendedor" id="cliente_vendedor">
                                        <option value="">- Sin vendedor asignado -</option>
                                    </select>
                                </div>
                            </div>
                        </div>



                        <!-- Pestaña 3: COMERCIAL -->
                        <div class="tab-pane fade" id="pane-comercial" role="tabpanel">
                            <div class="row g-2">
                                <div class="col-6 col-md">
                                    <div class="card bg-light border-0 text-center p-2">
                                        <span class="small text-muted d-block" style="font-size: 0.7rem;">FACTURAS</span>
                                        <h6 class="mb-0 fw-bold" id="stat_facturas">0</h6>
                                    </div>
                                </div>
                                <div class="col-6 col-md">
                                    <div class="card bg-light border-0 text-center p-2">
                                        <span class="small text-muted d-block" style="font-size: 0.7rem;">VENTAS (CON IVA)</span>
                                        <h6 class="mb-0 fw-bold text-success" id="stat_ventas">$0.00</h6>
                                    </div>
                                </div>
                                <div class="col-6 col-md">
                                    <div class="card bg-light border-0 text-center p-2">
                                        <span class="small text-muted d-block" style="font-size: 0.7rem;">VENTAS (SIN IVA)</span>
                                        <h6 class="mb-0 fw-bold text-primary" id="stat_subtotal">$0.00</h6>
                                    </div>
                                </div>
                                <div class="col-6 col-md">
                                    <div class="card bg-light border-0 text-center p-2">
                                        <span class="small text-muted d-block" style="font-size: 0.7rem;">NOTAS CRÉDITO</span>
                                        <h6 class="mb-0 fw-bold text-warning" id="stat_nc">$0.00</h6>
                                    </div>
                                </div>
                                <div class="col-6 col-md">
                                    <div class="card bg-light border-0 text-center p-2">
                                        <span class="small text-muted d-block" style="font-size: 0.7rem;">ANULADAS</span>
                                        <h6 class="mb-0 fw-bold text-danger" id="stat_anuladas">0</h6>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña 4: COBROS -->
                        <div class="tab-pane fade" id="pane-cobros" role="tabpanel">
                            
                            <!-- Mensaje explicativo de auto-cobro -->
                            <div class="alert alert-primary bg-primary bg-opacity-10 py-2 px-3 small border-primary border-opacity-25 d-flex align-items-start mb-3">
                                <i class="bi bi-info-circle-fill text-primary me-2 mt-1 fs-6"></i>
                                <div style="color: #052c65;">
                                    <strong>Generación automática de cobros:</strong> Al establecer una <em>Forma de Cobro</em> predeterminada, el sistema registrará automáticamente un ingreso con esta forma de cobro cada vez que se genere una factura de venta para este cliente y sea autorizada por el SRI.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="cliente_id_forma_pago_sri" class="form-label small fw-bold">Forma de Pago SRI (Predeterminada)</label>
                                    <select class="form-select form-select-sm" name="id_forma_pago_sri" id="cliente_id_forma_pago_sri">
                                        <option value="">- Seleccione -</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_id_forma_cobro_predeterminada" class="form-label small fw-bold">Forma de Cobro</label>
                                    <select class="form-select form-select-sm" name="id_forma_cobro_predeterminada" id="cliente_id_forma_cobro_predeterminada">
                                        <option value="">- Seleccione -</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold"><i class="bi bi-tags me-1"></i>Concepto de ingreso predeterminado</label>
                                    <select class="form-select form-select-sm bg-light" id="cliente_id_ingreso_concepto" disabled>
                                        <option value="">-- Seleccione --</option>
                                    </select>
                                    <input type="hidden" name="id_ingreso_concepto_predeterminado" id="cliente_id_ingreso_concepto_hidden">
                                    <div class="form-text text-muted" style="font-size: 10px;">Concepto utilizado para generar el cobro automático.</div>
                                </div>
                                <div class="col-md-6 d-none" id="wrapper_tipo_operacion_bancaria">
                                    <label for="cliente_tipo_operacion_bancaria" class="form-label small fw-bold">Operación Bancaria</label>
                                    <select class="form-select form-select-sm bg-warning bg-opacity-10" name="tipo_operacion_bancaria_predeterminada" id="cliente_tipo_operacion_bancaria">
                                        <option value="DEPOSITO">Depósito</option>
                                        <option value="TRANSFERENCIA" selected>Transferencia</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_monto_maximo_auto_cobro" class="form-label small fw-bold">Monto Máx. Auto-Generar</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.01" class="form-control" name="monto_maximo_auto_cobro" id="cliente_monto_maximo_auto_cobro">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: UBICACIÓN -->
                        <div class="tab-pane fade" id="pane-ubicacion" role="tabpanel">
                            <!-- Inputs ocultos enviados con el formulario -->
                            <input type="hidden" name="latitud"  id="cliente_latitud">
                            <input type="hidden" name="longitud" id="cliente_longitud">

                            <div class="row g-2">
                                <!-- Estado de coordenadas -->
                                <div class="col-12 d-flex align-items-center gap-2 flex-wrap">
                                    <div id="geo_coords_display" class="d-none small">
                                        <i class="bi bi-pin-map-fill text-success me-1"></i>
                                        <span id="geo_lat_txt"></span>, <span id="geo_lng_txt"></span>
                                        <span id="geo_fecha_txt" class="text-muted ms-2" style="font-size:0.7rem;"></span>
                                    </div>
                                    <div id="geo_no_coords" class="small text-muted">
                                        <i class="bi bi-geo-alt me-1"></i> Sin coordenadas registradas.
                                    </div>
                                </div>

                                <!-- Botones -->
                                <div class="col-12 d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnGeocodificar" onclick="geocodificarDesdeAPI()">
                                        <i class="bi bi-search me-1"></i> Obtener desde dirección
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnUsarGps" onclick="usarGps()">
                                        <i class="bi bi-crosshair2 me-1"></i> Usar mi ubicación (GPS)
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnLimpiarCoordenadas" onclick="limpiarCoordenadas()">
                                        <i class="bi bi-x-circle me-1"></i> Limpiar
                                    </button>
                                </div>

                                <div class="col-12">
                                    <div id="geo_alert" class="alert d-none py-1 px-2 small border-0 mb-0"></div>
                                </div>

                                <!-- Mapa -->
                                <div class="col-12">
                                    <div id="mapa_cliente" style="height:300px; border-radius:8px; border:1px solid #dee2e6; background:#f8f9fa; position:relative;">
                                        <div id="mapa_placeholder" class="d-flex align-items-center justify-content-center h-100 text-muted small flex-column">
                                            <i class="bi bi-map fs-3 mb-1"></i>
                                            Use los botones para obtener la ubicación
                                        </div>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:0.7rem;">
                                        <i class="bi bi-info-circle me-1"></i> Puede arrastrar el marcador para ajustar la posición exacta.
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if ($permCli['eliminar'] ?? true): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarCliente" onclick="eliminarCliente()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardarCliente">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
