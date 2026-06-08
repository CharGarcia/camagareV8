<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// Asegurar consistencia si el modal se incluye desde otro módulo (ej. Liquidación)
$idUsuarioAct = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaAct = (int)($_SESSION['id_empresa'] ?? 0);
$nivelAct = (int)($_SESSION['nivel'] ?? 1);

// 1. Forzar vistaConfig de proveedores para ocultar pestañas correctamente
$vistaConfigProv = \App\Helpers\PreferenciasHelper::getPreferenciasVista('proveedores');
// Renderizar estilos específicos de proveedores con ID único
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigProv, 'estiloVistaPestanasProv');

// 2. Forzar permisos de proveedores para las insignias de Información
$permProv = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/proveedores') {
    $modelPerm = new \App\models\PermisoSubmodulo();
    $idSubProv = $modelPerm->getIdSubmoduloPorRutaMvc('modulos/proveedores');
    if ($idSubProv) {
        $mapPerm = $modelPerm->getPermisosDeUsuario($idUsuarioAct, $idEmpresaAct);
        if (isset($mapPerm[$idSubProv])) {
            $p = $mapPerm[$idSubProv];
            $permProv = [
                'ver' => !empty($p['ver']),
                'crear' => !empty($p['crear']),
                'actualizar' => !empty($p['actualizar']),
                'eliminar' => !empty($p['eliminar']),
                'todo' => !empty($p['t']),
            ];
        } else if ($nivelAct >= 3) {
            $permProv = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
        }
    }
}

$urlBaseProvShared = BASE_URL . '/modulos/proveedores';

// Cargar Leaflet solo una vez (evitar duplicado si ya lo cargó modal_cliente)
if (!defined('LEAFLET_LOADED')) {
    define('LEAFLET_LOADED', true);
    echo '<link rel="stylesheet" href="' . rtrim(BASE_URL, '/') . '/vendor/leaflet/leaflet.css">';
    echo '<script src="' . rtrim(BASE_URL, '/') . '/vendor/leaflet/leaflet.js"></script>';
}
?>
<!-- Modal Proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <form method="POST" action="<?= $urlBaseProvShared ?>/store" id="prov_formProveedor" novalidate>
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-truck text-primary me-2"></i>
                        <span id="prov_tituloModal">Nuevo Proveedor</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <input type="hidden" name="id" id="prov_id" value="">

                    <!-- Pestañas -->
                    <style>
                        #tabsProveedor .nav-link {
                            padding: 6px 9px;
                            font-size: 0.8rem;
                            white-space: nowrap;
                        }
                        #tabsProveedor .nav-link i {
                            font-size: 0.85rem;
                        }
                    </style>
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" id="tabsProveedor" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active" id="prov-tab-general-btn" data-bs-toggle="tab" href="#prov-tab-general" role="tab" title="General"><i class="bi bi-person-vcard me-1"></i> General</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="prov-tab-comercial-btn" data-bs-toggle="tab" href="#prov-tab-comercial" role="tab" title="Comercial"><i class="bi bi-shop me-1"></i> Comercial</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="prov-tab-banco-btn" data-bs-toggle="tab" href="#prov-tab-banco" role="tab" title="Banco"><i class="bi bi-bank me-1"></i> Banco</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="prov-tab-retenciones-btn" data-bs-toggle="tab" href="#prov-tab-retenciones" role="tab" title="Retenciones"><i class="bi bi-percent me-1"></i> Retenciones</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="prov-tab-pagos-btn" data-bs-toggle="tab" href="#prov-tab-pagos" role="tab" title="Pagos"><i class="bi bi-cash-coin me-1"></i> Pagos</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="prov-tab-sri-btn" data-bs-toggle="tab" href="#prov-tab-sri" role="tab" title="SRI"><i class="bi bi-file-earmark-text me-1"></i> SRI</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="prov-tab-ubicacion-btn" data-bs-toggle="tab" href="#prov-pane-ubicacion" role="tab" title="Ubicación"><i class="bi bi-geo-alt-fill me-1"></i> Ubicación</a>
                            </li>
                        </ul>
                        <div class="pb-1 flex-shrink-0">
                            <?php
                            $pestanasConfigProv = [
                                'prov-tab-comercial'  => 'Comercial',
                                'prov-tab-banco'      => 'Banco',
                                'prov-tab-retenciones'=> 'Retenciones',
                                'prov-tab-pagos'      => 'Pagos',
                                'prov-tab-sri'        => 'SRI',
                                'prov-tab-ubicacion'  => 'Ubicación',
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigProv, $vistaConfigProv ?? [], 'proveedores');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-3 py-3" id="tabsProveedorContent" style="overflow: visible !important;">
                        <!-- Pestaña General -->
                        <div class="tab-pane fade show active" id="prov-tab-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold d-flex align-items-center">Tipo ID * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('proveedores', 'prov_tipo_id', 'tipo_id_proveedor') ?></label>
                                    <select class="form-select form-select-sm" name="tipo_id_proveedor" id="prov_tipo_id" required>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="prov_identificacion" class="form-label small fw-bold d-flex justify-content-between align-items-center" style="margin-bottom: 0.5rem;">
                                        <span>Identificación *</span>
                                        <span id="prov_sriBadge" class="badge d-none"></span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" name="identificacion" id="prov_identificacion" required maxlength="30" autocomplete="off" placeholder="RUC/Cédula">
                                        <span class="input-group-text bg-white px-2 d-none" id="prov_sriSpinnerWrap">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="visually-hidden">Consultando...</span>
                                            </div>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('proveedores', 'prov_status', 'status') ?></label>
                                    <select class="form-select form-select-sm" name="status" id="prov_status">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Razón Social *</label>
                                    <input type="text" class="form-control form-control-sm" name="razon_social" id="prov_razon" required maxlength="200" autocomplete="off">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Nombre Comercial</label>
                                    <input type="text" class="form-control form-control-sm" name="nombre_comercial" id="prov_nombre_comercial" maxlength="200" autocomplete="off">
                                </div>
                                <div class="col-md-9">
                                    <label for="prov_email" class="form-label small fw-bold">Correo Electrónico <small class="text-muted fw-normal">(separados por coma)</small></label>
                                    <input type="text" class="form-control form-control-sm" name="email" id="prov_email" maxlength="150" autocomplete="off" placeholder="Ej: info@proveedor.com, ventas@proveedor.com">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Teléfono/Celular</label>
                                    <input type="text" class="form-control form-control-sm" name="telefono" id="prov_telefono" maxlength="50" autocomplete="off">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Dirección</label>
                                    <input type="text" class="form-control form-control-sm" name="direccion" id="prov_direccion" maxlength="255" autocomplete="off">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold d-flex align-items-center">Tipo de Empresa <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('proveedores', 'prov_tipo_empresa', 'tipo_empresa') ?></label>
                                    <select class="form-select form-select-sm" name="tipo_empresa" id="prov_tipo_empresa"></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold d-flex align-items-center">Provincia <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('proveedores', 'prov_provincia', 'provincia') ?></label>
                                    <select class="form-select form-select-sm" name="provincia" id="prov_provincia">
                                        <option value="">-- Seleccione --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Ciudad</label>
                                    <select class="form-select form-select-sm" name="ciudad" id="prov_ciudad">
                                        <option value="">-- Seleccione Provincia --</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña Comercial -->
                        <div class="tab-pane fade" id="prov-tab-comercial" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Días de Crédito / Plazo</label>
                                    <input type="number" step="1" min="0" class="form-control form-control-sm" name="plazo" id="prov_plazo" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Parte Relacionada</label>
                                    <select class="form-select form-select-sm" name="relacionado" id="prov_relacionado">
                                        <option value="0">No Relacionada</option>
                                        <option value="1">Sí (Parte Relacionada SRI)</option>
                                    </select>
                                </div>

                                <!-- Resumen Comercial (Lectura) -->
                                <div class="col-12 mt-4">
                                    <h6 class="small fw-bold text-primary mb-3 border-bottom pb-2"><i class="bi bi-graph-up me-2"></i>Resumen Comercial</h6>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Documentos Recibidos</label>
                                    <input type="text" class="form-control form-control-sm fw-medium" id="stat_documentos" readonly value="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Total Compras</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control form-control-sm fw-medium text-end" id="stat_total" readonly value="0.00">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Por Pagar</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control form-control-sm fw-medium text-end text-danger" id="stat_por_pagar" readonly value="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña Banco -->
                        <div class="tab-pane fade" id="prov-tab-banco" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Banco</label>
                                    <select class="form-select form-select-sm" name="id_banco" id="prov_banco"></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Tipo Cuenta</label>
                                    <select class="form-select form-select-sm" name="tipo_cta" id="prov_tipo_cta">
                                        <option value="">-- Seleccione --</option>
                                        <option value="1">Ahorros</option>
                                        <option value="2">Corriente</option>
                                        <option value="3">Virtual</option>
                                        <option value="4">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Número de Cuenta</label>
                                    <input type="text" class="form-control form-control-sm" name="numero_cta" id="prov_numero_cta" maxlength="50" autocomplete="off">
                                </div>
                            </div>
                        </div>



                        <!-- Pestaña Pagos -->
                        <div class="tab-pane fade" id="prov-tab-pagos" role="tabpanel">
                            <div class="alert alert-secondary bg-light border-0 small mb-3">
                                <i class="bi bi-info-circle me-1"></i> Determine la forma de pago por defecto y el monto máximo para auto generar pagos al registrar compras.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Forma de pago predeterminada</label>
                                    <select class="form-select form-select-sm" name="id_forma_pago_predeterminada" id="prov_forma_pago">
                                        <option value="">- Seleccione forma de pago -</option>
                                    </select>
                                </div>
                                <div class="col-md-6 d-none" id="prov_div_op_bancaria">
                                    <label class="form-label small fw-bold text-dark"><i class="bi bi-bank me-1"></i>Operación bancaria predeterminada</label>
                                    <select class="form-select form-select-sm bg-warning bg-opacity-10 border-warning border-opacity-25" name="tipo_operacion_bancaria_predeterminada" id="prov_tipo_operacion_bancaria">
                                        <option value="">- Seleccione -</option>
                                        <option value="TRANSFERENCIA">Transferencia</option>
                                        <option value="DEBITO">Débito</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Monto máximo para auto generar pago</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-secondary-subtle">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm border-secondary-subtle" name="monto_maximo_auto_pago" id="prov_monto_maximo" placeholder="0.00" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold"><i class="bi bi-tags me-1"></i>Concepto de egreso predeterminado</label>
                                    <select class="form-select form-select-sm bg-light" id="prov_id_egreso_concepto" disabled>
                                        <option value="">-- Seleccione --</option>
                                    </select>
                                    <input type="hidden" name="id_egreso_concepto_predeterminado" id="prov_id_egreso_concepto_hidden">
                                    <div class="form-text text-muted" style="font-size: 10px;">Concepto utilizado para generar el egreso.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña Retenciones -->
                        <div class="tab-pane fade" id="prov-tab-retenciones" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="alert alert-info bg-opacity-10 border-0 py-2 small">
                                        <i class="bi bi-info-circle me-1"></i> Configure las retenciones predeterminadas para este proveedor. Se aplicarán automáticamente en las compras.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Retención en la Fuente (Renta)</label>
                                    <div class="input-group input-group-sm mb-1 position-relative">
                                        <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-search"></i></span>
                                        <input type="text" id="busqueda_retencion_renta" class="form-control shadow-none border-secondary-subtle" placeholder="Buscar retención renta..." oninput="buscarRetencionSRI(this, 'RENTA')">
                                        <input type="hidden" name="id_retencion_renta" id="prov_id_retencion_renta">
                                        <div id="results_retencion_renta" class="ac-results d-none position-absolute w-100 bg-white border shadow-lg rounded-bottom" style="top: 100%; left: 0; z-index: 2000; max-height: 200px; overflow-y: auto;"></div>
                                    </div>
                                    <div class="form-text extra-small">Configuración de renta predeterminada.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Retención de IVA</label>
                                    <div class="input-group input-group-sm mb-1 position-relative">
                                        <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-search"></i></span>
                                        <input type="text" id="busqueda_retencion_iva" class="form-control shadow-none border-secondary-subtle" placeholder="Buscar retención IVA..." oninput="buscarRetencionSRI(this, 'IVA')">
                                        <input type="hidden" name="id_retencion_iva" id="prov_id_retencion_iva">
                                        <div id="results_retencion_iva" class="ac-results d-none position-absolute w-100 bg-white border shadow-lg rounded-bottom" style="top: 100%; left: 0; z-index: 2000; max-height: 200px; overflow-y: auto;"></div>
                                    </div>
                                    <div class="form-text extra-small">Configuración de IVA predeterminada.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña SRI -->
                        <div class="tab-pane fade" id="prov-tab-sri" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="alert alert-light border py-2 small">
                                        <i class="bi bi-info-circle text-primary me-1"></i> Configure los parámetros de reporte del SRI para este proveedor.
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Sustento Tributario Predeterminado</label>
                                    <select name="id_sustento_tributario" id="prov_id_sustento_tributario" class="form-select form-select-sm shadow-none border-secondary-subtle">
                                        <option value="">-- Seleccione Sustento --</option>
                                    </select>
                                    <div class="form-text extra-small">Este sustento se asignará por defecto al registrar compras de este proveedor.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña UBICACIÓN -->
                        <div class="tab-pane fade" id="prov-pane-ubicacion" role="tabpanel">
                            <!-- Inputs ocultos enviados con el formulario -->
                            <input type="hidden" name="latitud"  id="prov_latitud">
                            <input type="hidden" name="longitud" id="prov_longitud">

                            <div class="row g-2">
                                <!-- Estado de coordenadas -->
                                <div class="col-12 d-flex align-items-center gap-2 flex-wrap">
                                    <div id="prov_geo_coords_display" class="d-none small">
                                        <i class="bi bi-pin-map-fill text-success me-1"></i>
                                        <span id="prov_geo_lat_txt"></span>, <span id="prov_geo_lng_txt"></span>
                                        <span id="prov_geo_fecha_txt" class="text-muted ms-2" style="font-size:0.7rem;"></span>
                                    </div>
                                    <div id="prov_geo_no_coords" class="small text-muted">
                                        <i class="bi bi-geo-alt me-1"></i> Sin coordenadas registradas.
                                    </div>
                                </div>

                                <!-- Botones -->
                                <div class="col-12 d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="prov_btnGeocodificar" onclick="provGeocodificarDesdeAPI()">
                                        <i class="bi bi-search me-1"></i> Obtener desde dirección
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="prov_btnUsarGps" onclick="provUsarGps()">
                                        <i class="bi bi-crosshair2 me-1"></i> Usar mi ubicación (GPS)
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="prov_btnLimpiarCoordenadas" onclick="provLimpiarCoordenadas()">
                                        <i class="bi bi-x-circle me-1"></i> Limpiar
                                    </button>
                                </div>

                                <div class="col-12">
                                    <div id="prov_geo_alert" class="alert d-none py-1 px-2 small border-0 mb-0"></div>
                                </div>

                                <!-- Mapa -->
                                <div class="col-12">
                                    <div id="prov_mapa_proveedor" style="height:300px; border-radius:8px; border:1px solid #dee2e6; background:#f8f9fa; position:relative;">
                                        <div id="prov_mapa_placeholder" class="d-flex align-items-center justify-content-center h-100 text-muted small flex-column">
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
                        <?php if ($permProv['eliminar'] ?? true): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="prov_btnEliminar" onclick="eliminarProveedor()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="prov_btnGuardar">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>

            <?php if ($permProv['eliminar'] ?? true): ?>
                <form id="formEliminar" method="POST" action="<?= $urlBaseProvShared ?>/delete" class="d-none">
                    <input type="hidden" name="id_eliminar" id="id_eliminar_input">
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>