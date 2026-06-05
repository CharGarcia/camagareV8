<?php $base = BASE_URL; ?>
<?php
// Validaciones de completitud por pestaña
$warnGeneral = empty($empresa['nombre_comercial']) || empty($empresa['tipo']) || empty($empresa['direccion']);
$warnEmisor = empty($empresa['id_tipo_regimen']) || empty($empresa['tipo_ambiente']);
// Con "Usar correo de Camagare" el correo ya está configurado (correo central),
// por eso solo se marca pendiente cuando se usa correo propio y faltan datos SMTP.
$tipoCorreoCfg = $correo['tipo_correo'] ?? 'camagare';
$warnCorreo = ($tipoCorreoCfg === 'propio') && (empty($correo['host']) || empty($correo['correo_emisor']));
$warnFirma = empty($firmas);
$estPrincipal = $establecimientos[0] ?? null;
$warnEst = !$estPrincipal || empty($estPrincipal['nombre']) || empty($estPrincipal['direccion']);
$warnPuntos = empty($puntos);

$warnIcon = '<i class="bi bi-exclamation-circle-fill text-warning ms-1" title="Configuración pendiente"></i>';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold"><i class="bi bi-building-gear me-2"></i>Configuración de Empresa</h4>
            <p class="text-muted small">Administra la información legal, emisor electrónico y puntos de emisión de tu empresa.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white border-bottom-0 pt-3">
            <ul class="nav nav-pills card-header-pills" id="empresaTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        Información General <?= $warnGeneral ? $warnIcon : '' ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="emisor-tab" data-bs-toggle="tab" data-bs-target="#emisor" type="button" role="tab">
                        Emisor Electrónico <?= $warnEmisor ? $warnIcon : '' ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="correo-tab" data-bs-toggle="tab" data-bs-target="#correo" type="button" role="tab">
                        Configuración Correo <span id="warnCorreoIcon" style="display: <?= $warnCorreo ? 'inline' : 'none' ?>;"><?= $warnIcon ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="firma-tab" data-bs-toggle="tab" data-bs-target="#firma" type="button" role="tab">
                        Firma Electrónica <?= $warnFirma ? $warnIcon : '' ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="establecimientos-tab" data-bs-toggle="tab" data-bs-target="#establecimientos" type="button" role="tab">
                        Establecimiento <?= $warnEst ? $warnIcon : '' ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="puntos-tab" data-bs-toggle="tab" data-bs-target="#puntos" type="button" role="tab">
                        Puntos de Emisión <?= $warnPuntos ? $warnIcon : '' ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="secuenciales-tab" data-bs-toggle="tab" data-bs-target="#secuenciales" type="button" role="tab">Secuenciales</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="decimales-tab" data-bs-toggle="tab" data-bs-target="#decimales" type="button" role="tab">Decimales</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="iva-tab" data-bs-toggle="tab" data-bs-target="#iva" type="button" role="tab">Form 104 IVA</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ice-tab" data-bs-toggle="tab" data-bs-target="#ice" type="button" role="tab">Configuración ICE</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="facturacion_config-tab" data-bs-toggle="tab" data-bs-target="#facturacion_config" type="button" role="tab">Reglas Facturación</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="inventario_config-tab" data-bs-toggle="tab" data-bs-target="#inventario_config" type="button" role="tab">Reglas Inventario</button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="empresaTabsContent">

                <!-- Pestaña: Información General -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <form id="form-general" method="POST">
                        <input type="hidden" name="section" value="general">
                        <div class="form-msg mb-3"></div>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">RUC</label>
                                <input type="text" class="form-control form-control-sm bg-light fw-bold" value="<?= htmlspecialchars($empresa['ruc'] ?? '') ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Número Establecimiento</label>
                                <input type="text" class="form-control form-control-sm bg-light" value="<?= htmlspecialchars($empresa['establecimiento'] ?? '') ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Razón Social</label>
                                <input type="text" name="nombre" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['nombre'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nombre Comercial</label>
                                <input type="text" name="nombre_comercial" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['nombre_comercial'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Tipo de Contribuyente</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($tiposEmpresa as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= ($empresa['tipo'] == $t['id']) ? 'selected' : '' ?>><?= $t['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Teléfono Empresa</label>
                                <input type="text" name="telefono" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Correo Empresa</label>
                                <input type="email" name="mail" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['mail'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Dirección Matriz</label>
                                <input type="text" name="direccion" class="form-control form-control-sm" id="direccion_empresa_general" value="<?= htmlspecialchars($empresa['direccion'] ?? '') ?>">
                            </div>



                            <!-- Representante Legal -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nombre Representante Legal</label>
                                <input type="text" name="nom_rep_legal" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['nom_rep_legal'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Identificación Rep. Legal</label>
                                <input type="text" name="ced_rep_legal" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['ced_rep_legal'] ?? '') ?>">
                            </div>

                            <!-- Contador y Estado -->
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Nombre Contador</label>
                                <input type="text" name="nombre_contador" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['nombre_contador'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">RUC Contador</label>
                                <input type="text" name="ruc_contador" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['ruc_contador'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Estado Empresa</label>
                                <select name="estado" class="form-select form-select-sm bg-light" disabled>
                                    <option value="1" <?= ($empresa['estado'] == '1') ? 'selected' : '' ?>>Activa</option>
                                    <option value="0" <?= ($empresa['estado'] == '0') ? 'selected' : '' ?>>Inactiva</option>
                                </select>
                            </div>

                            <!-- Cobro y Vigencia (Solo Lectura) -->
                            <div class="col-12">
                                <div class="card bg-light border-0 mt-3">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold mb-3 small text-primary"><i class="bi bi-shield-check me-2"></i>Suscripción y Vigencia del Sistema</h6>
                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-2">
                                                <div class="text-muted mb-1" style="font-size: 0.65rem;">Costo de Suscripción</div>
                                                <div class="fw-bold text-dark" style="font-size: 0.8rem;">$ <?= number_format((float)($empresa['valor_cobro'] ?? 0), 2) ?></div>
                                            </div>
                                            <div class="col-md-3 border-start ps-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="cancelar_renovacion" id="cancelar_renovacion" <?= ($empresa['cancelar_renovacion'] ?? false) ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold text-danger" for="cancelar_renovacion" style="font-size: 0.7rem;">CANCELAR RENOVACIÓN</label>
                                                </div>
                                                <div class="text-muted mt-1" style="font-size: 0.6rem;">No renovar automáticamente</div>
                                            </div>
                                            <div class="col-md-2 border-start ps-3">
                                                <div class="text-muted mb-1" style="font-size: 0.65rem;">Estado de Pago</div>
                                                <?php $estP = strtolower($empresa['estado_pago'] ?? 'pendiente'); ?>
                                                <span class="badge bg-<?= ($estP === 'pagado') ? 'success' : 'warning' ?> bg-opacity-10 text-<?= ($estP === 'pagado') ? 'success' : 'warning' ?> rounded-pill" style="font-size: 0.65rem;">
                                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.4rem;"></i> <?= strtoupper($estP) ?>
                                                </span>
                                            </div>
                                            <div class="col-md-5 border-start ps-4">
                                                <?php
                                                $desde = $empresa['periodo_vigencia_desde'] ?? null;
                                                $hasta = $empresa['periodo_vigencia_hasta'] ?? null;
                                                $porcentaje = 0;
                                                $diasRestantes = 0;
                                                $colorBar = 'primary';
                                                if ($desde && $hasta) {
                                                    $t1 = strtotime($desde);
                                                    $t2 = strtotime($hasta);
                                                    $now = time();
                                                    $total = $t2 - $t1;
                                                    if ($total > 0) {
                                                        $transcurrido = $now - $t1;
                                                        $porcentaje = min(100, max(0, round(($transcurrido / $total) * 100)));
                                                        $diasRestantes = ceil(($t2 - $now) / 86400);
                                                        if ($diasRestantes < 15) $colorBar = 'danger';
                                                        elseif ($diasRestantes < 30) $colorBar = 'warning';
                                                    }
                                                }
                                                ?>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <div class="text-muted" style="font-size: 0.65rem;">Vigencia: <span class="text-dark fw-bold"><?= $desde ? date('d-m-Y', strtotime($desde)) : '-' ?></span> al <span class="text-dark fw-bold"><?= $hasta ? date('d-m-Y', strtotime($hasta)) : '-' ?></span></div>
                                                    <div class="fw-bold text-<?= $colorBar ?>" style="font-size: 0.65rem;"><?= max(0, $diasRestantes) ?> días restantes</div>
                                                </div>
                                                <div class="progress" style="height: 6px; background: #e2e8f0;">
                                                    <div class="progress-bar bg-<?= $colorBar ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= 100 - $porcentaje ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4">Guardar Información General</button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña: Emisor Electrónico -->
                <div class="tab-pane fade" id="emisor" role="tabpanel">
                    <form id="form-emisor" class="row g-3" method="POST">
                        <input type="hidden" name="section" value="emisor">
                        <div class="col-12 form-msg mb-0"></div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Número Resolución Contribuyente Especial</label>
                            <input type="text" name="resolucion_contribuyente" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['resolucion_contribuyente'] ?? '') ?>" placeholder="Ej: 001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tipo Régimen SRI</label>
                            <select name="id_tipo_regimen" class="form-select form-select-sm">
                                <option value="">Seleccione...</option>
                                <?php foreach ($regimenes as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($empresa['id_tipo_regimen'] == $r['id']) ? 'selected' : '' ?>><?= $r['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo Ambiente</label>
                            <select name="tipo_ambiente" class="form-select form-select-sm">
                                <option value="1" <?= ($empresa['tipo_ambiente'] == 1) ? 'selected' : '' ?>>Pruebas</option>
                                <option value="2" <?= ($empresa['tipo_ambiente'] == 2) ? 'selected' : '' ?>>Producción</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Agente de Retención</label>
                            <input type="text" name="agente_retencion" class="form-control form-control-sm" value="<?= htmlspecialchars($empresa['agente_retencion'] ?? '') ?>" placeholder="Nro. Resolución">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo Emisión</label>
                            <select name="tipo_emision" class="form-select form-select-sm">
                                <option value="1" selected>Normal</option>
                            </select>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4">Guardar Configuración SRI</button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña: Configuración Correo -->
                <div class="tab-pane fade" id="correo" role="tabpanel">
                    <form id="form-correo" method="POST">
                        <input type="hidden" name="section" value="correo">
                        <div class="form-msg mb-3"></div>

                        <div class="row g-3">
                            <div class="col-12 mb-2">
                                <label class="form-label small fw-bold d-block">Tipo de Correo</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_correo" id="tipo_camagare" value="camagare" <?= (($correo['tipo_correo'] ?? 'camagare') === 'camagare') ? 'checked' : '' ?> onchange="toggleSmtpFields()">
                                    <label class="form-check-label small" for="tipo_camagare">Usar correo de Camagare</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_correo" id="tipo_propio" value="propio" <?= (($correo['tipo_correo'] ?? '') === 'propio') ? 'checked' : '' ?> onchange="toggleSmtpFields()">
                                    <label class="form-check-label small" for="tipo_propio">Usar correo propio</label>
                                </div>
                            </div>
                            
                            <div class="col-12 mb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="envio_automatico" id="envio_automatico" <?= ($correo['envio_automatico'] ?? false) ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-bold" for="envio_automatico">Enviar correos de forma automática (después de que un documento haya sido autorizado por el SRI)</label>
                                </div>
                            </div>

                            <div class="col-12" id="smtp_fields_container" style="display: <?= (($correo['tipo_correo'] ?? 'camagare') === 'propio') ? 'block' : 'none' ?>;">
                                <div class="row g-3 border rounded p-2 bg-light mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Host SMTP</label>
                                        <input type="text" name="host" class="form-control form-control-sm" value="<?= htmlspecialchars($correo['host'] ?? '') ?>" placeholder="ej: smtp.gmail.com">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Puerto</label>
                                        <input type="number" name="puerto" class="form-control form-control-sm" value="<?= htmlspecialchars($correo['puerto'] ?? '') ?>" placeholder="587">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch mt-4 pt-1">
                                            <input class="form-check-input" type="checkbox" name="ssl_habilitado" id="ssl_habilitado" <?= ($correo['ssl_habilitado'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="ssl_habilitado">SSL/TLS Habilitado</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Usuario/Correo Emisor</label>
                                        <input type="email" name="correo_emisor" class="form-control form-control-sm" value="<?= htmlspecialchars($correo['correo_emisor'] ?? '') ?>" placeholder="correo@ejemplo.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Contraseña del Correo</label>
                                        <div class="input-group input-group-sm">
                                            <input type="password" name="password_correo_emisor" class="form-control" value="<?= htmlspecialchars($correo['password_correo_emisor'] ?? '') ?>">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12 mt-2">
                                <label class="form-label small fw-bold">Asunto predeterminado del correo</label>
                                <input type="text" name="asunto_correo" class="form-control form-control-sm" value="<?= htmlspecialchars($correo['asunto_correo'] ?? '') ?>" placeholder="Envío de Comprobante Electrónico">
                            </div>
                            <div class="col-md-12 mt-2">
                                <label class="form-label small fw-bold">Cuerpo del correo (Diseño HTML)</label>
                                <!-- Contenedor para Quill -->
                                <div id="cuerpo_correo_editor" style="height: 250px; background: white;"><?= $correo['cuerpo_correo'] ?? '' ?></div>
                                <!-- Input oculto donde se guardará el HTML para enviar al servidor -->
                                <input type="hidden" name="cuerpo_correo" id="cuerpo_correo_input" value="<?= htmlspecialchars($correo['cuerpo_correo'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4">Guardar Configuración SMTP</button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña: Firma Electrónica -->
                <div class="tab-pane fade" id="firma" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <form id="form-firma" class="p-4 border rounded bg-light shadow-sm" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="section" value="firma">
                                <div class="form-msg mb-3"></div>
                                <h6 class="fw-bold mb-3 small"><i class="bi bi-key-fill me-2"></i>Cargar Nueva Firma</h6>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Archivo de Firma (.p12)</label>
                                    <input type="file" name="archivo_p12" class="form-control form-control-sm" accept=".p12" required>
                                    <div class="form-text small" style="font-size: 0.65rem;">Sube tu certificado de firma electrónica emitido por el SRI.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold">Contraseña de la Firma</label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" name="password_firma" class="form-control" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100 fw-bold py-2">ACTIVAR FIRMA</button>
                            </form>
                        </div>
                        <div class="col-md-7">
                            <h6 class="fw-bold mb-3 small"><i class="bi bi-clock-history me-2"></i>Historial de Firmas</h6>
                            <div class="table-responsive" style="max-height: 300px;">
                                <table class="table table-sm table-hover small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Archivo</th>
                                            <th>Fecha Emisión</th>
                                            <th>Vigencia Hasta</th>
                                            <th>Contraseña</th>
                                            <th class="text-center">Estado</th>
                                            <th class="text-center">Descargar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($firmas)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No hay firmas cargadas anteriormente.</td>
                                            </tr>
                                            <?php else: foreach ($firmas as $f): ?>
                                                <tr>
                                                    <td class="align-middle"><?= htmlspecialchars($f['archivo_nombre']) ?></td>
                                                    <td class="align-middle"><?= $f['fecha_emision'] ? date('d-m-Y', strtotime($f['fecha_emision'])) : '-' ?></td>
                                                    <td class="align-middle"><?= $f['fecha_expiracion'] ? date('d-m-Y', strtotime($f['fecha_expiracion'])) : '-' ?></td>
                                                    <td class="align-middle">
                                                        <div class="d-flex align-items-center">
                                                            <input type="password" class="form-control form-control-sm border-0 bg-transparent p-0 shadow-none text-muted" value="<?= htmlspecialchars($f['password_firma'] ?? '') ?>" readonly style="width: 70px;">
                                                            <button class="btn btn-link text-muted p-0 ms-2 text-decoration-none shadow-none" type="button" onclick="togglePassword(this)">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <span class="badge bg-<?= $f['es_activo'] ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $f['es_activo'] ? 'success' : 'secondary' ?> border border-<?= $f['es_activo'] ? 'success' : 'secondary' ?> rounded-pill" style="font-size: 0.65rem;">
                                                            <?= $f['es_activo'] ? 'VIGENTE' : 'CADUCADA/ANTERIOR' ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="<?= $base ?>/storage/descargar-firma?id=<?= $f['id'] ?>" class="btn btn-link btn-sm text-primary p-0 shadow-none"><i class="bi bi-cloud-download fs-5"></i></a>
                                                    </td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña: Establecimiento -->
                <div class="tab-pane fade" id="establecimientos" role="tabpanel">
                    <?php
                    $est = !empty($establecimientos) ? $establecimientos[0] : null;
                    ?>
                    <?php if ($est): ?>
                        <form id="form-establecimiento-directo" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="section" value="establecimiento">
                            <input type="hidden" name="id" value="<?= htmlspecialchars((string)($est['id'] ?? '')) ?>">
                            <div class="form-msg mb-3"></div>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Código</label>
                                    <input type="text" name="codigo" class="form-control form-control-sm bg-light fw-bold" value="<?= htmlspecialchars((string)($est['codigo'] ?? '')) ?>" readonly>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label small fw-bold">Nombre Establecimiento</label>
                                    <input type="text" name="nombre" class="form-control form-control-sm" required value="<?= htmlspecialchars((string)($est['nombre'] ?? '')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Tipo</label>
                                    <select name="tipo" class="form-select form-select-sm">
                                        <option value="Matriz" <?= ($est['tipo'] == 'Matriz') ? 'selected' : '' ?>>Casa Matriz</option>
                                        <option value="Sucursal" <?= ($est['tipo'] == 'Sucursal') ? 'selected' : '' ?>>Sucursal</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Estado</label>
                                    <select name="estado" class="form-select form-select-sm">
                                        <option value="activo" <?= ($est['estado'] == 'activo') ? 'selected' : '' ?>>Activa</option>
                                        <option value="inactivo" <?= ($est['estado'] == 'inactivo') ? 'selected' : '' ?>>Inactiva</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Dirección Completa</label>
                                    <textarea name="direccion" id="est-direccion-input" class="form-control form-control-sm" rows="2" required><?= htmlspecialchars((string)($est['direccion'] ?? '')) ?></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Logo del Establecimiento</label>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">

                                        <!-- Miniatura actual / preview -->
                                        <div class="border rounded-2 bg-light d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0"
                                             style="width:110px;height:80px;" id="est-logo-wrap">
                                            <?php if (!empty($est['logo_ruta'])): ?>
                                                <img id="est-logo-preview"
                                                     src="<?= htmlspecialchars((string)$est['logo_ruta']) ?>"
                                                     alt="Logo actual"
                                                     style="max-width:108px;max-height:78px;object-fit:contain;"
                                                     onerror="this.style.display='none';document.getElementById('est-logo-placeholder').style.display='flex';">
                                                <div id="est-logo-placeholder" class="flex-column align-items-center justify-content-center text-muted" style="display:none;font-size:.65rem;text-align:center;">
                                                    <i class="bi bi-image fs-4 d-block mb-1"></i>Sin logo
                                                </div>
                                            <?php else: ?>
                                                <img id="est-logo-preview" src="" alt="" style="max-width:108px;max-height:78px;object-fit:contain;display:none;">
                                                <div id="est-logo-placeholder" class="flex-column align-items-center justify-content-center text-muted" style="display:flex;font-size:.65rem;text-align:center;">
                                                    <i class="bi bi-image fs-4 d-block mb-1"></i>Sin logo
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Input de archivo -->
                                        <div class="flex-grow-1">
                                            <input type="file" name="logo_establecimiento" id="est-logo-input"
                                                   class="form-control form-control-sm" accept="image/png,image/jpeg,image/gif,image/webp"
                                                   onchange="previewLogoEst(this)">
                                            <div class="form-text" style="font-size:.65rem;">
                                                PNG, JPG o GIF. Recomendado fondo transparente. Máx. 2 MB.
                                                <?php if (!empty($est['logo_ruta'])): ?>
                                                    <br><span class="text-success"><i class="bi bi-check-circle me-1"></i>Logo actual guardado.</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <div class="col-12 mt-3">
                                    <h6 class="fw-bold fs-6 text-primary mb-3"><i class="bi bi-file-earmark-text me-2"></i>Mensaje Personalizado en PDF</h6>
                                    <p class="text-muted small mb-3">Este mensaje aparecerá en la parte inferior del PDF de la factura (ideal para Cuentas Bancarias, Notas, etc.).</p>
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label small fw-bold">Título del Mensaje</label>
                                            <input type="text" name="leyenda_pdf_titulo" class="form-control form-control-sm" placeholder="Ej: CUENTAS BANCARIAS" value="<?= htmlspecialchars((string)($est['leyenda_pdf_titulo'] ?? '')) ?>">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label small fw-bold">Contenido del Mensaje</label>
                                            <textarea name="leyenda_pdf_mensaje" class="form-control form-control-sm" rows="3" placeholder="Escribe aquí los datos..."><?= htmlspecialchars((string)($est['leyenda_pdf_mensaje'] ?? '')) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mt-4 text-end">
                                <button type="submit" class="btn btn-primary btn-sm px-4">Guardar Información del Establecimiento</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">No se encontró información del establecimiento.</div>
                    <?php endif; ?>
                </div>

                <!-- Pestaña: Puntos de Emisión -->
                <div class="tab-pane fade" id="puntos" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold mb-0 small">Mis Puntos de Emisión Registrados</h6>
                        <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="nuevoPunto()"><i class="bi bi-plus-lg me-1"></i>Nuevo Punto</button>
                    </div>
                    <div class="row g-3" id="puntos-container">
                        <?php foreach ($puntos as $p): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 border shadow-none point-card" role="button"
                                    data-punto='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>'
                                    onclick="editarPunto(this)">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                                <i class="bi bi-shop fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h6 class="mb-0 small fw-bold text-truncate"><?= htmlspecialchars($p['codigo_punto']) ?> - <?= htmlspecialchars($p['nombre']) ?></h6>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?= ($p['estado'] == 'activo') ? 'success' : 'danger' ?> bg-opacity-10 text-<?= ($p['estado'] == 'activo') ? 'success' : 'danger' ?> border border-<?= ($p['estado'] == 'activo') ? 'success' : 'danger' ?> rounded-pill" style="font-size: 0.65rem;">
                                                <?= strtoupper($p['estado']) ?>
                                            </span>
                                            <i class="bi bi-pencil-square text-muted opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pestaña: Secuenciales -->
                <div class="tab-pane fade" id="secuenciales" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-3 border-end">
                            <label class="form-label small fw-bold mb-3 text-primary">Punto de Emisión</label>
                            <div class="list-group list-group-flush small rounded-3 border" id="secuenciales-puntos-list">
                                <?php foreach ($puntos as $idx => $p): ?>
                                    <a href="#" class="list-group-item list-group-item-action py-3 <?= ($idx === 0) ? 'active' : '' ?>"
                                        onclick="cargarSecuenciales(this, <?= (int)($p['id'] ?? 0) ?>)">
                                        <div class="d-flex justify-content-between align-items-center w-100">
                                            <span class="fw-medium"><?= $p['codigo_punto'] ?> - <?= $p['nombre'] ?></span>
                                            <i class="bi bi-chevron-right small opacity-50"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-9 bg-light bg-opacity-50 p-4 rounded-3 border">
                            <form id="form-secuenciales" method="POST">
                                <input type="hidden" name="section" value="secuenciales">
                                <input type="hidden" name="id_punto_emision" id="sec-punto-id" value="<?= $puntos[0]['id'] ?? '' ?>">
                                <div class="form-msg mb-3"></div>
                                <div class="row g-3" id="secuenciales-fields">
                                    <div class="col-12 text-center py-4 text-muted small">
                                        <i class="bi bi-info-circle me-2"></i>Seleccione un punto de emisión para ver sus secuenciales.
                                    </div>
                                </div>
                                <div class="mt-5 border-top pt-3 d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="btn-agregar-sec" onclick="agregarSecuencialPersonalizado()">
                                        <i class="bi bi-plus-lg me-1"></i>Agregar Tipo Documento
                                    </button>
                                    <button type="submit" id="btn-guardar-sec" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm fw-bold">
                                        <i class="bi bi-save me-1"></i>GUARDAR SECUENCIALES
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pestaña: Decimales -->
                <div class="tab-pane fade" id="decimales" role="tabpanel">
                    <form id="form-decimales" method="POST">
                        <input type="hidden" name="section" value="decimales">
                        <input type="hidden" name="id_establecimiento" value="<?= (int)($estPrincipal['id'] ?? 0) ?>">
                        <div class="form-msg mb-3"></div>
                        <div class="row g-4">
                            <div class="col-md-6 col-lg-4">
                                <label class="form-label small fw-bold">Decimales para Cantidad</label>
                                <select name="decimales_cantidad" class="form-select form-select-sm">
                                    <?php for ($i = 0; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>" <?= (($empresa['decimales_cantidad'] ?? 2) == $i) ? 'selected' : '' ?>><?= $i ?> decimales</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <label class="form-label small fw-bold">Decimales para Precio</label>
                                <select name="decimales_precio" class="form-select form-select-sm">
                                    <?php for ($i = 0; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>" <?= (($empresa['decimales_precio'] ?? 2) == $i) ? 'selected' : '' ?>><?= $i ?> decimales</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4">Guardar Decimales</button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña: Form 104 IVA -->
                <div class="tab-pane fade" id="iva" role="tabpanel">
                    <form id="form-iva" method="POST">
                        <input type="hidden" name="section" value="iva">
                        <input type="hidden" name="id_establecimiento" value="<?= (int)($estPrincipal['id'] ?? 0) ?>">
                        <div class="form-msg mb-3"></div>

                        <div class="row g-4">

                            <!-- Tabla: Casilleros IVA por Tarifa -->
                            <div class="col-12">
                                <h6 class="fw-bold fs-6 text-primary mb-3"><i class="bi bi-list-check me-2"></i>Casilleros SRI Formulario 104 IVA</h6>
                                <p class="text-muted small mb-4">Configura los códigos de los casilleros del SRI para cada tarifa de IVA. Estos códigos se utilizarán al categorizar productos y generar reportes tributarios.</p>

                                <div class="table-responsive rounded-3 border mb-5">
                                    <table class="table table-sm table-hover mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3 py-2" style="width: 15%;">Tarifa</th>
                                                <th class="py-2 text-center" style="width: 7%;">%</th>
                                                <th class="py-2 text-center" colspan="2" style="width: 39%;">Ventas</th>
                                                <th class="py-2 text-center" colspan="2" style="width: 39%;">Compras</th>
                                            </tr>
                                            <tr class="table-light border-top-0">
                                                <th colspan="2"></th>
                                                <th class="text-center small py-1 bg-light bg-opacity-50">Subtotal</th>
                                                <th class="text-center small py-1 bg-light bg-opacity-50">IVA</th>
                                                <th class="text-center small py-1 bg-light bg-opacity-50">Subtotal</th>
                                                <th class="text-center small py-1 bg-light bg-opacity-50">IVA</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tarifasIva as $tar):
                                                $idTar = $tar['id'];
                                                $vSub  = $iva_casilleros[$idTar]['subtotal_ventas'] ?? '';
                                                $vIva  = $iva_casilleros[$idTar]['iva_ventas'] ?? '';
                                                $cSub  = $iva_casilleros[$idTar]['subtotal_compras'] ?? '';
                                                $cIva  = $iva_casilleros[$idTar]['iva_compras'] ?? '';
                                                $tabla = $iva_casilleros[$idTar]['tabla'] ?? 'iva';
                                            ?>
                                                <tr>
                                                    <td class="ps-3 align-middle fw-medium"><?= htmlspecialchars($tar['tarifa']) ?></td>
                                                    <td class="text-center align-middle">
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $tar['porcentaje_iva'] ?>%</span>
                                                    </td>
                                                    <td class="align-middle">
                                                        <input type="text" name="iva_casilleros[<?= $idTar ?>][subtotal_ventas]"
                                                            class="form-control form-control-sm border-0 bg-light text-center"
                                                            value="<?= htmlspecialchars($vSub) ?>"
                                                            placeholder="código casillero">
                                                    </td>
                                                    <td class="align-middle">
                                                        <input type="text" name="iva_casilleros[<?= $idTar ?>][iva_ventas]"
                                                            class="form-control form-control-sm border-0 bg-light text-center"
                                                            value="<?= htmlspecialchars($vIva) ?>"
                                                            placeholder="código casillero">
                                                    </td>
                                                    <td class="align-middle">
                                                        <input type="text" name="iva_casilleros[<?= $idTar ?>][subtotal_compras]"
                                                            class="form-control form-control-sm border-0 bg-light text-center"
                                                            value="<?= htmlspecialchars($cSub) ?>"
                                                            placeholder="código casillero">
                                                    </td>
                                                    <td class="align-middle">
                                                        <input type="text" name="iva_casilleros[<?= $idTar ?>][iva_compras]"
                                                            class="form-control form-control-sm border-0 bg-light text-center"
                                                            value="<?= htmlspecialchars($cIva) ?>"
                                                            placeholder="código casillero">
                                                        <input type="hidden" name="iva_casilleros[<?= $idTar ?>][tabla]" value="<?= htmlspecialchars($tabla) ?>">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tabla: Retenciones SRI - IVA -->
                            <div class="col-12">
                                <h6 class="fw-bold fs-6 text-primary mb-3"><i class="bi bi-percent me-2"></i>Retenciones SRI - IVA</h6>
                                <p class="text-muted small mb-4">Conceptos de retención de IVA del SRI. Configure los casilleros del Formulario 104 para cada porcentaje de retención.</p>

                                <div class="table-responsive rounded-3 border">
                                    <table class="table table-sm table-hover mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3 py-2" style="width: 45%;">Concepto</th>
                                                <th class="py-2 text-center" style="width: 10%;">%</th>
                                                <th class="py-2 text-center" style="width: 22%;">Cas. Compras</th>
                                                <th class="py-2 text-center pe-3" style="width: 23%;">Cas. Ventas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($retenciones_sri_iva)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4 text-muted">
                                                        <i class="bi bi-info-circle me-2"></i>No hay retenciones de IVA registradas en la tabla <code>retenciones_sri</code>.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($retenciones_sri_iva as $ret):
                                                    $idRet      = (int)$ret['id'];
                                                    $casComp    = $retenciones_casilleros[$idRet]['casillero_compras'] ?? '';
                                                    $casVen     = $retenciones_casilleros[$idRet]['casillero_ventas']  ?? '';
                                                ?>
                                                    <tr>
                                                        <td class="ps-3 align-middle"><?= htmlspecialchars($ret['concepto_ret']) ?></td>
                                                        <td class="text-center align-middle">
                                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill"><?= htmlspecialchars($ret['porcentaje']) ?>%</span>
                                                        </td>
                                                        <td class="text-center align-middle">
                                                            <input type="text" name="ret_casilleros[<?= $idRet ?>][cas_compras]"
                                                                class="form-control form-control-sm border-0 bg-light text-center"
                                                                value="<?= htmlspecialchars($casComp) ?>"
                                                                placeholder="cód. casillero">
                                                        </td>
                                                        <td class="pe-3 text-center align-middle">
                                                            <input type="text" name="ret_casilleros[<?= $idRet ?>][cas_ventas]"
                                                                class="form-control form-control-sm border-0 bg-light text-center"
                                                                value="<?= htmlspecialchars($casVen) ?>"
                                                                placeholder="cód. casillero">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>

                        <div class="col-12 mt-5 text-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm fw-bold">
                                <i class="bi bi-save me-2"></i>GUARDAR CONFIGURACIÓN IVA
                            </button>
                        </div>
                    </form>
                </div>


                <!-- Pestaña: Configuración ICE -->
                <div class="tab-pane fade" id="ice" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h6 class="fw-bold mb-1 fs-6 text-primary"><i class="bi bi-percent me-2"></i>Configuración de ICE</h6>
                            <p class="text-muted small mb-0">Gestiona los códigos de Impuesto a los Consumos Especiales (ICE) para tus productos.</p>
                        </div>
                        <button class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" onclick="nuevoIce()">
                            <i class="bi bi-plus-lg me-1"></i>NUEVO ICE
                        </button>
                    </div>

                    <div class="table-responsive rounded-3 border">
                        <table class="table table-sm table-hover mb-0 small" id="tabla-ice">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 py-2">Nombre ICE</th>
                                    <th class="py-2">Código ATS</th>
                                    <th class="py-2 text-center">Valor / %</th>
                                    <th class="py-2">Casillero Base ICE</th>
                                    <th class="py-2">Casillero ICE</th>
                                    <th class="py-2 pe-3 text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ices)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="bi bi-info-circle me-2"></i>No hay configuraciones de ICE registradas.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ices as $i): ?>
                                        <tr class="align-middle">
                                            <td class="ps-3 fw-medium"><?= htmlspecialchars($i['nombre_ice']) ?></td>
                                            <td><code class="text-secondary"><?= htmlspecialchars($i['codigo_ats']) ?></code></td>
                                            <td class="text-center">
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill">
                                                    <?= number_format($i['valor_ice'], 2) ?>
                                                </span>
                                            </td>
                                            <td><span class="small text-muted"><?= htmlspecialchars($i['casillero_base_ice'] ?? '') ?></span></td>
                                            <td><span class="small text-muted"><?= htmlspecialchars($i['casillero_ice'] ?? '') ?></span></td>
                                            <td class="pe-3 text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary border-0 shadow-none px-2" onclick='editarIce(<?= json_encode($i) ?>)'>
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger border-0 shadow-none px-2" onclick="eliminarIce(<?= $i['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pestaña: Reglas Facturación -->
                <div class="tab-pane fade" id="facturacion_config" role="tabpanel">
                    <form id="form-facturacion-config" method="POST">
                        <input type="hidden" name="section" value="facturacion_config">
                        <input type="hidden" name="id_establecimiento" value="<?= (int)($estPrincipal['id'] ?? 0) ?>">
                        <div class="form-msg mb-3"></div>
                        <div class="row g-4">

                            <div class="col-md-12">
                                <h6 class="fw-bold fs-6 text-primary border-bottom pb-2 mb-3">Reglas de Facturación</h6>
                                <div class="row g-3">
                                    <div class="col-md-6 border-end">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="facturacion_libre" id="sw_libre" <?= (($empresa['facturacion_libre'] ?? false) === 'true' || ($empresa['facturacion_libre'] ?? false) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_libre">Permitir ingreso de registros libremente (solo servicios)</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Permite escribir libremente un servicio en la factura, sin que se haya registrado previamente.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="facturacion_inventario" id="sw_inv" <?= (($empresa['facturacion_inventario'] ?? true) === 'true' || ($empresa['facturacion_inventario'] ?? true) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_inv">La facturación afecta al inventario</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Si está activo, las ventas descontarán stock automáticamente, siempre que el producto sea inventariable.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="factura_solo_stock_positivo" id="sw_solo_pos" <?= (($empresa['factura_solo_stock_positivo'] ?? false) === 'true' || ($empresa['factura_solo_stock_positivo'] ?? false) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_solo_pos">¿Trabajar con stock positivo?</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Si está activo, solo se podrá facturar productos con saldo positivo en la bodega seleccionada.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="obligatorio_lotes" id="sw_lotes" <?= (($empresa['obligatorio_lotes'] ?? false) === 'true' || ($empresa['obligatorio_lotes'] ?? false) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_lotes">Obligatorio usar Lotes</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Requiere seleccionar un lote en facturación de forma obligatoria.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="obligatorio_caducidad" id="sw_cad" <?= (($empresa['obligatorio_caducidad'] ?? false) === 'true' || ($empresa['obligatorio_caducidad'] ?? false) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_cad">Obligatorio usar Fecha de Caducidad</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Requiere seleccionar fecha de caducidad en facturación de forma obligatoria.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="obligatorio_nup" id="sw_nup" <?= (($empresa['obligatorio_nup'] ?? false) === 'true' || ($empresa['obligatorio_nup'] ?? false) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_nup">Obligatorio usar NUP (Número Único / Serial)</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Requiere seleccionar un NUP en facturación de forma obligatoria.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="mostrar_unidad_medida" id="sw_medida" <?= (($empresa['mostrar_unidad_medida'] ?? true) === 'true' || ($empresa['mostrar_unidad_medida'] ?? true) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_medida">Mostrar unidad de medida en facturación</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Habilita la columna y selección de unidades en el detalle de la factura.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 ps-4">

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="mostrar_cajero_factura" id="sw_cajero" <?= (($empresa['mostrar_cajero_factura'] ?? false) === 'true' || ($empresa['mostrar_cajero_factura'] ?? false) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_cajero">Mostrar nombre del cajero en la factura</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Agrega automáticamente en información adicional el nombre del usuario que genera la factura.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="mostrar_vendedor_factura" id="sw_vendedor" <?= (($empresa['mostrar_vendedor_factura'] ?? false) === 'true' || ($empresa['mostrar_vendedor_factura'] ?? false) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_vendedor">Mostrar nombre del vendedor en la factura</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Agrega automáticamente en información adicional el vendedor seleccionado en la factura.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="editar_precio_factura" id="sw_edit_precio" <?= (($empresa['editar_precio_factura'] ?? true) === 'true' || ($empresa['editar_precio_factura'] ?? true) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_edit_precio">¿Se puede editar el precio de un producto o servicio en la factura?</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Permite modificar el precio unitario directamente en el detalle de la factura.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="editar_iva_factura" id="sw_edit_iva" <?= (($empresa['editar_iva_factura'] ?? true) === 'true' || ($empresa['editar_iva_factura'] ?? true) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_edit_iva">¿Se puede editar el tipo de iva en un producto o servicio en la factura?</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Permite cambiar la tarifa de IVA asignada a un ítem en el detalle de la factura.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="editar_descuento_factura" id="sw_edit_desc" <?= (($empresa['editar_descuento_factura'] ?? true) === 'true' || ($empresa['editar_descuento_factura'] ?? true) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_edit_desc">¿Se puede editar el descuento en un producto o servicio en la factura?</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Habilita la edición manual del valor de descuento por cada ítem.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" name="mostrar_propina_factura" id="sw_propina" <?= (($empresa['mostrar_propina_factura'] ?? false) === 'true' || ($empresa['mostrar_propina_factura'] ?? false) === true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold" for="sw_propina">¿Mostrar el campo de propina en la factura?</label>
                                            <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">Habilita el campo de propina en el pie de la factura (aplicable a ciertos sectores).</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row pt-3 border-top mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Forma de Pago Predeterminada SRI</label>
                                        <select class="form-select form-select-sm" name="id_forma_pago_sri_def">
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach ($formasPagoSri as $fp): ?>
                                                <option value="<?= $fp['id'] ?>" <?= ((int)($empresa['id_forma_pago_sri_def'] ?? 0) === (int)$fp['id']) ? 'selected' : '' ?>>
                                                    <?= $fp['codigo'] ?> - <?= $fp['nombre'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text mt-1 sub-text" style="font-size:0.65rem;">
                                            Esta forma de pago se usará por defecto si el cliente no tiene una asignada.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 mt-3">
                            <h6 class="fw-bold fs-6 text-primary border-bottom pb-2 mb-3">Límites de Facturación</h6>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold" for="valor_limite_cf">
                                        Valor límite a consumidor final
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control form-control-sm" id="valor_limite_cf"
                                            name="valor_limite_consumidor_final" min="0" step="0.01"
                                            value="<?= isset($empresa['valor_limite_consumidor_final']) && $empresa['valor_limite_consumidor_final'] !== null ? htmlspecialchars($empresa['valor_limite_consumidor_final']) : '' ?>"
                                            placeholder="Sin límite">
                                    </div>
                                    <div class="form-text mt-0" style="font-size:0.65rem;">
                                        Monto máximo (impuestos incluidos) por factura a consumidor final. Deje vacío para no aplicar límite.
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 mt-2">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Método de cálculo de IVA en facturación</label>
                                    <select name="calculo_iva_facturacion" class="form-select form-select-sm">
                                        <option value="linea_linea" <?= (($empresa['calculo_iva_facturacion'] ?? 'linea_linea') == 'linea_linea') ? 'selected' : '' ?>>Línea por línea</option>
                                        <option value="subtotal" <?= (($empresa['calculo_iva_facturacion'] ?? '') == 'subtotal') ? 'selected' : '' ?>>Al subtotal</option>
                                    </select>
                                    <div class="form-text mt-0 sub-text" style="font-size:0.65rem;">
                                        Determina cómo se calcula el impuesto en documentos con múltiples ítems.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4">Guardar Configuración</button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña: Reglas Inventario -->
                <div class="tab-pane fade" id="inventario_config" role="tabpanel">
                    <form id="form-inventario-config" method="POST">
                        <input type="hidden" name="section" value="inventario_config">
                        <input type="hidden" name="id_establecimiento" value="<?= (int)($estPrincipal['id'] ?? 0) ?>">
                        <div class="form-msg mb-3"></div>
                        <div class="row g-4">
                            <div class="col-md-12">
                                <h6 class="fw-bold fs-6 text-primary border-bottom pb-2 mb-3">Reglas de Inventario</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Método de Costeo de Inventario</label>
                                        <select name="metodo_costeo" class="form-select form-select-sm">
                                            <option value="promedio" <?= (($empresa['metodo_costeo'] ?? 'promedio') == 'promedio') ? 'selected' : '' ?>>Promedio Ponderado</option>
                                            <option value="fifo" <?= (($empresa['metodo_costeo'] ?? '') == 'fifo') ? 'selected' : '' ?>>FIFO (Primeras entradas, primeras salidas)</option>
                                            <option value="lifo" <?= (($empresa['metodo_costeo'] ?? '') == 'lifo') ? 'selected' : '' ?>>LIFO (Últimas entradas, primeras salidas)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4">Guardar Configuración</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal: ICE -->
<div class="modal fade" id="modalIce" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-bottom-0 pb-0">
                <h6 class="modal-title fw-bold" id="tituloModalIce">Nuevo ICE</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <form id="form-ice" method="POST">
                    <input type="hidden" name="id" id="ice-id">
                    <div class="form-msg mb-3"></div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Nombre del ICE</label>
                            <input type="text" name="nombre_ice" id="ice-nombre" class="form-control form-control-sm" required placeholder="Ej: Bebidas Gaseosas 12%">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Código ATS</label>
                            <input type="text" name="codigo_ats" id="ice-codigo" class="form-control form-control-sm" required placeholder="Ej: 3011">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Casillero Base ICE (SRI)</label>
                            <input type="text" name="casillero_base_ice" id="ice-casillero-base" class="form-control form-control-sm" placeholder="Ej: 851">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Casillero ICE (SRI)</label>
                            <input type="text" name="casillero_ice" id="ice-casillero" class="form-control form-control-sm" placeholder="Ej: 301">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Valor (%)</label>
                            <input type="number" step="0.0001" name="valor_ice" id="ice-valor" class="form-control form-control-sm" required value="0">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" id="btn-eliminar-ice" class="btn btn-outline-danger btn-sm px-3 rounded-pill d-none" onclick="eliminarIceDirecto()">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-light btn-sm px-3 me-2 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm fw-bold">Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Punto de Emisión -->
<div class="modal fade" id="modalPunto" tabindex="-1">
    <div class="modal-dialog">
        <form id="form-punto" class="modal-content border-0 shadow-lg" method="POST" enctype="multipart/form-data">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-shop me-2"></i>Punto de Emisión</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="section" value="punto">
                <input type="hidden" name="id" id="punto-id">
                <div class="form-msg mb-3"></div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Establecimiento</label>
                        <input type="text" class="form-control form-control-sm bg-light fw-bold" readonly value="<?= htmlspecialchars((string)($establecimientos[0]['codigo'] ?? '')) ?> - <?= htmlspecialchars((string)($establecimientos[0]['nombre'] ?? '')) ?>">
                        <input type="hidden" name="id_establecimiento" id="punto-id-est-hidden" value="<?= htmlspecialchars((string)($establecimientos[0]['id'] ?? '')) ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Nombre del Punto</label>
                        <input type="text" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Caja 1 o Ventanilla Principal">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Código Punto</label>
                        <input type="text" name="codigo_punto" class="form-control form-control-sm" required placeholder="001" maxlength="3">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 fw-bold d-none" id="btn-eliminar-punto" onclick="eliminarPunto()">
                        <i class="bi bi-trash me-1"></i>ELIMINAR
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">GUARDAR PUNTO</button>
                </div>
            </div>
        </form>
    </div>
</div>


<script>
    // ── Preview logo establecimiento ──────────────────────────────────────────
    function previewLogoEst(input) {
        const preview     = document.getElementById('est-logo-preview');
        const placeholder = document.getElementById('est-logo-placeholder');
        if (!preview || !input.files || !input.files[0]) return;

        const file = input.files[0];
        if (!file.type.startsWith('image/')) {
            Swal.fire('Archivo no válido', 'Seleccione una imagen (PNG, JPG, GIF, WEBP).', 'warning');
            input.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire('Archivo muy grande', 'El logo no debe superar los 2 MB.', 'warning');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    function togglePassword(btn) {
        const input = btn.previousElementSibling;
        if (input.type === "password") {
            input.type = "text";
            btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            input.type = "password";
            btn.innerHTML = '<i class="bi bi-eye"></i>';
        }
    }



    function nuevoPunto() {
        document.getElementById('form-punto').reset();
        document.getElementById('punto-id').value = '';
        document.getElementById('btn-eliminar-punto').classList.add('d-none');
        const modal = new bootstrap.Modal(document.getElementById('modalPunto'));
        modal.show();
    }

    function editarPunto(el) {
        const data = JSON.parse(el.getAttribute('data-punto'));
        const form = document.getElementById('form-punto');
        form.querySelector('[name=id]').value = data.id || '';
        // El id_establecimiento ahora es fijo por el campo hidden en el modal
        form.querySelector('[name=nombre]').value = data.nombre || '';
        form.querySelector('[name=codigo_punto]').value = data.codigo_punto || '';
        form.querySelector('[name=estado]').value = data.estado || 'activo';

        document.getElementById('btn-eliminar-punto').classList.remove('d-none');

        const modal = new bootstrap.Modal(document.getElementById('modalPunto'));
        modal.show();
    }

    async function eliminarPunto() {
        const idPunto = document.getElementById('punto-id').value;
        if (!idPunto) return;

        if (!confirm("¿Eliminar punto de emisión? Esta acción no se puede deshacer.")) return;

        try {
            const response = await fetch('<?= $base ?>/modulos/empresa/deletePunto', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `id=${idPunto}`
            });
            const res = await response.json();
            if (res.ok) {
                location.reload();
            } else {
                alert(res.error || 'No se pudo eliminar');
            }
        } catch (err) {
            alert('Error de conexión');
        }
    }

    function agregarSecuencialPersonalizado() {
        const container = document.getElementById('secuenciales-fields');
        const name = prompt('Ingrese el nombre del tipo de documento:');
        if (name) {
            const div = document.createElement('div');
            div.className = 'col-md-6 col-lg-4';
            div.innerHTML = `
            <label class="form-label small fw-bold text-muted">${name}</label>
            <input type="number" name="secuenciales[${name}]" class="form-control form-control-sm border-0 shadow-sm" value="1" min="1">
        `;
            container.appendChild(div);
        }
    }

    function _setBtnSecuencialesEstado(tieneRegistros) {
        const btn = document.getElementById('btn-guardar-sec');
        const btnAgregar = document.getElementById('btn-agregar-sec');
        if (!btn) return;
        if (tieneRegistros) {
            btn.type = 'submit';
            btn.className = 'btn btn-primary btn-sm px-4 rounded-pill shadow-sm fw-bold';
            btn.innerHTML = '<i class="bi bi-save me-1"></i>GUARDAR SECUENCIALES';
            btn.onclick = null;
            if (btnAgregar) btnAgregar.style.display = '';
        } else {
            btn.type = 'button';
            btn.className = 'btn btn-success btn-sm px-4 rounded-pill shadow-sm fw-bold';
            btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>CREAR INICIALES DE SECUENCIALES';
            btn.onclick = crearSecuencialesIniciales;
            if (btnAgregar) btnAgregar.style.display = 'none';
        }
    }

    async function crearSecuencialesIniciales() {
        const idPunto = document.getElementById('sec-punto-id').value;
        if (!idPunto) return;

        const btn = document.getElementById('btn-guardar-sec');
        const msgContainer = document.querySelector('#form-secuenciales .form-msg');
        if (msgContainer) msgContainer.innerHTML = '';

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creando...';

        try {
            const fd = new FormData();
            fd.append('section', 'secuenciales_iniciales');
            fd.append('id_punto_emision', idPunto);
            const res = await fetch(`<?= $base ?>/modulos/empresa/save`, { method: 'POST', body: fd });
            const json = await res.json();

            if (json.ok) {
                const link = document.querySelector('#secuenciales-puntos-list a.active');
                if (link) await cargarSecuenciales(link, parseInt(idPunto));
            } else {
                const errMsg = json.error || json.msg || 'Error al crear los secuenciales iniciales.';
                if (msgContainer) {
                    msgContainer.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0">${errMsg}</div>`;
                }
                btn.disabled = false;
                _setBtnSecuencialesEstado(false);
            }
        } catch (e) {
            if (msgContainer) {
                msgContainer.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0">Error de conexión con el servidor.</div>`;
            }
            btn.disabled = false;
            _setBtnSecuencialesEstado(false);
        }
    }

    async function cargarSecuenciales(el, id) {
        document.querySelectorAll('#secuenciales-puntos-list a').forEach(a => a.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('sec-punto-id').value = id;

        const container = document.getElementById('secuenciales-fields');
        container.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div><span class="ms-2 small">Cargando secuenciales...</span></div>';

        try {
            const res = await fetch(`<?= $base ?>/modulos/empresa/getSecuenciales?id_punto=${id}`);
            const json = await res.json();

            container.innerHTML = '';

            if (json.ok) {
                const entries = Object.entries(json.data);
                if (entries.length === 0) {
                    container.innerHTML = '<div class="col-12 text-center py-4 text-muted small"><i class="bi bi-info-circle me-2"></i>Este punto aún no tiene secuenciales registrados. Use el botón para crear los tipos estándar.</div>';
                    _setBtnSecuencialesEstado(false);
                    return;
                }

                for (const [nombre, valor] of entries) {
                    const div = document.createElement('div');
                    div.className = 'col-md-6 col-lg-4';
                    div.innerHTML = `
                        <label class="form-label small fw-bold text-muted">${nombre}</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-0 shadow-sm text-muted small">#</span>
                            <input type="number" name="secuenciales[${nombre}]" class="form-control border-0 shadow-sm" value="${valor}" min="1">
                        </div>
                    `;
                    container.appendChild(div);
                }
                _setBtnSecuencialesEstado(true);
            }
        } catch (err) {
            container.innerHTML = '<div class="col-12 text-center py-4 text-danger small">Error al conectar con el servidor.</div>';
        }
    }

    function agregarSecuencialPersonalizado() {
        const container = document.getElementById('secuenciales-fields');
        // Si hay un mensaje de "No hay secuenciales", lo quitamos
        if (container.querySelector('.text-muted')) container.innerHTML = '';

        const name = prompt('Ingrese el nombre del tipo de documento (Ej: Factura, Nota de Crédito, etc):');
        if (name) {
            const div = document.createElement('div');
            div.className = 'col-md-6 col-lg-4';
            div.innerHTML = `
                <label class="form-label small fw-bold text-muted">${name}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-0 shadow-sm text-muted small">#</span>
                    <input type="number" name="secuenciales[${name}]" class="form-control border-0 shadow-sm" value="1" min="1">
                    <button type="button" class="btn btn-outline-danger border-0 bg-white" onclick="this.closest('.col-md-6').remove()">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `;
            container.appendChild(div);
        }
    }

    /* ---------------------------------------------------------
       Gestión de ICE
    --------------------------------------------------------- */
    window.nuevoIce = function() {
        const f = document.getElementById('form-ice');
        f.reset();
        document.getElementById('ice-id').value = '';
        document.getElementById('tituloModalIce').textContent = 'Nuevo ICE';
        document.getElementById('btn-eliminar-ice').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('modalIce')).show();
    }

    window.editarIce = function(data) {
        const f = document.getElementById('form-ice');
        f.reset();
        document.getElementById('ice-id').value = data.id;
        document.getElementById('ice-nombre').value = data.nombre_ice;
        document.getElementById('ice-codigo').value = data.codigo_ats;
        document.getElementById('ice-casillero').value = data.casillero_ice || '';
        document.getElementById('ice-casillero-base').value = data.casillero_base_ice || '';
        document.getElementById('ice-valor').value = data.valor_ice;

        document.getElementById('tituloModalIce').textContent = 'Editar ICE';
        document.getElementById('btn-eliminar-ice').classList.remove('d-none');
        new bootstrap.Modal(document.getElementById('modalIce')).show();
    }

    window.eliminarIceDirecto = function() {
        const id = document.getElementById('ice-id').value;
        if (id) eliminarIce(id);
    }

    window.eliminarIce = async function(id) {
        if (!confirm('¿Seguro que desea eliminar esta configuración de ICE?')) return;

        try {
            const response = await fetch('<?= $base ?>/modulos/empresa/deleteIce', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `id=${id}`
            });
            const res = await response.json();
            if (res.ok) {
                location.reload();
            } else {
                alert(res.error || 'No se pudo eliminar');
            }
        } catch (err) {
            alert('Error de conexión');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const forms = ['form-general', 'form-emisor', 'form-correo', 'form-firma', 'form-punto', 'form-secuenciales', 'form-establecimiento-directo', 'form-decimales', 'form-iva', 'form-facturacion-config', 'form-inventario-config', 'form-ice'];
        forms.forEach(id => {
            const f = document.getElementById(id);
            if (!f) return;
            f.addEventListener('submit', async (e) => {
                e.preventDefault();

                const msgContainer = f.querySelector('.form-msg');
                if (msgContainer) msgContainer.innerHTML = '';

                const formData = new FormData(f);

                try {
                    const formIdStr = f.getAttribute('id');
                    const urlTarget = formIdStr === 'form-ice' ? '<?= $base ?>/modulos/empresa/saveIce' : '<?= $base ?>/modulos/empresa/save';
                    const response = await fetch(urlTarget, {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();
                    if (res.ok) {
                        if (msgContainer) {
                            msgContainer.innerHTML = `<div class="alert alert-success py-2 px-3 small mb-0">${res.msg || 'Cambios guardados correctamente'}</div>`;
                            setTimeout(() => {
                                if (msgContainer) msgContainer.innerHTML = '';
                            }, 3000);
                        }
                        if (id === 'form-firma' || id === 'form-punto' || id === 'form-ice') setTimeout(() => location.reload(), 1000);
                    } else {
                        if (res.confirm) {
                            if (confirm(res.msg)) {
                                if (!f.querySelector('input[name="forzar"]')) {
                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = 'forzar';
                                    input.value = '1';
                                    f.appendChild(input);
                                }
                                f.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                                return;
                            }
                        }
                        if (msgContainer) {
                            msgContainer.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0">${res.error || res.msg || 'Error al guardar'}</div>`;
                        }
                    }
                } catch (err) {
                    console.error(err);
                    if (msgContainer) {
                        msgContainer.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0">Error de conexión con el servidor</div>`;
                    }
                }
            });
        });

        // Auto-cargar secuenciales del primer punto al mostrar la pestaña
        <?php if (!empty($puntos)): ?>
            const secTab = document.getElementById('secuenciales-tab');
            if (secTab) {
                secTab.addEventListener('shown.bs.tab', function() {
                    const firstLink = document.querySelector('#secuenciales-puntos-list a');
                    if (firstLink && !firstLink.dataset.loaded) {
                        firstLink.dataset.loaded = '1';
                        cargarSecuenciales(firstLink, <?= (int)($puntos[0]['id'] ?? 0) ?>);
                    }
                }, {
                    once: true
                });
            }
        <?php endif; ?>

        // Control lógico para "Facturación Libre" vs "Lotes/Caducidad/NUP/Inventario"
        const swLibre = document.getElementById('sw_libre');
        const swInv = document.getElementById('sw_inv');

        const toggleDependencies = () => {
            if (!swLibre || !swInv) return;

            const libreChecked = swLibre.checked;
            const invChecked = swInv.checked;

            // Dependencias totales de Facturación Libre
            const allDeps = ['sw_inv', 'sw_lotes', 'sw_cad', 'sw_nup', 'sw_solo_pos', 'sw_medida'];

            // Reglas que dependen de que el Inventario esté activo
            const invDeps = ['sw_lotes', 'sw_cad', 'sw_nup', 'sw_solo_pos'];

            if (libreChecked) {
                // Si es libre, desactivamos y bloqueamos TODO
                allDeps.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.checked = false;
                        el.disabled = true;
                    }
                });
            } else {
                // Si NO es libre, sw_inv y sw_medida están activos
                swInv.disabled = false;
                const swMedida = document.getElementById('sw_medida');
                if (swMedida) swMedida.disabled = false;

                // Pero las reglas de lotes/stock positivo dependen de sw_inv
                invDeps.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        if (!invChecked) {
                            el.checked = false;
                            el.disabled = true;
                        } else {
                            el.disabled = false;
                        }
                    }
                });
            }
        };

        if (swLibre) swLibre.addEventListener('change', toggleDependencies);
        if (swInv) swInv.addEventListener('change', toggleDependencies);

        // Ejecutar al cargar
        toggleDependencies();
    });

    // Inicializar estado de campos SMTP al cargar
    document.addEventListener('DOMContentLoaded', function() {
        toggleSmtpFields();
    });

    function toggleSmtpFields() {
        const isPropio = document.getElementById('tipo_propio').checked;
        const container = document.getElementById('smtp_fields_container');
        container.style.display = isPropio ? 'block' : 'none';
        container.querySelectorAll('input, select, textarea').forEach(el => {
            el.disabled = !isPropio;
        });
        actualizarAvisoCorreo();
    }

    // El aviso "Configuración pendiente" solo aplica al usar correo propio sin datos SMTP.
    // Con "Usar correo de Camagare" el correo ya está configurado.
    function actualizarAvisoCorreo() {
        const icon = document.getElementById('warnCorreoIcon');
        if (!icon) return;
        const isPropio = document.getElementById('tipo_propio').checked;
        if (!isPropio) {
            icon.style.display = 'none';
            return;
        }
        const host   = (document.querySelector('[name="host"]')?.value || '').trim();
        const emisor = (document.querySelector('[name="correo_emisor"]')?.value || '').trim();
        icon.style.display = (host === '' || emisor === '') ? 'inline' : 'none';
    }
</script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('cuerpo_correo_editor')) {
            var quillCuerpo = new Quill('#cuerpo_correo_editor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'align': [] }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });

            quillCuerpo.on('text-change', function() {
                var html = quillCuerpo.root.innerHTML;
                if (html === '<p><br></p>') html = '';
                document.getElementById('cuerpo_correo_input').value = html;
            });
        }
    });
</script>

<style>
    #empresaTabs .nav-link {
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 500;
        border-radius: 6px;
        padding: 0.3rem 0.4rem;
        margin-right: 0.2rem;
        transition: all 0.2s;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    #empresaTabs .nav-link:hover {
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    #empresaTabs .nav-link.active {
        background: #ffffff !important;
        color: #0d6efd !important;
        font-weight: 600;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border-color: #e2e8f0;
    }

    .tab-content {
        min-height: 450px;
    }

    /* Asegurar que SweetAlert aparezca sobre los modales (z-index modal: 5060) */
    .swal2-container {
        z-index: 10000 !important;
    }

    .point-card {
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .point-card h6 {
        font-size: 0.7rem !important;
    }

    .point-card .bg-primary {
        padding: 0.5rem !important;
    }

    .point-card .bi-shop {
        font-size: 1rem !important;
    }

    .list-group-item {
        padding: 0.4rem 0.75rem !important;
        font-size: 0.75rem !important;
    }

    .form-control-sm,
    .form-select-sm,
    .btn-sm {
        padding: 0.35rem 0.6rem !important;
        font-size: 0.75rem !important;
    }

    .form-label {
        margin-bottom: 0.2rem !important;
        font-size: 0.7rem !important;
    }
</style>