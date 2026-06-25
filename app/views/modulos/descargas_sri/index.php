<?php

/** @var string $titulo */
/** @var string $rucEmpresa */
/** @var array $perm */
$base = rtrim(BASE_URL ?? '', '/');
$rucEmpresa = htmlspecialchars($rucEmpresa ?? '');
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-cloud-arrow-down text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h4>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom-0 pt-3 pb-0 px-4">
            <ul class="nav nav-tabs border-bottom" id="descargasTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold text-primary" id="tab-clave-btn" data-bs-toggle="tab" data-bs-target="#tab-clave" type="button" role="tab" aria-controls="tab-clave" aria-selected="true">
                        <i class="bi bi-key me-1"></i> Por Clave de Acceso
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold text-muted" id="tab-txt-btn" data-bs-toggle="tab" data-bs-target="#tab-txt" type="button" role="tab" aria-controls="tab-txt" aria-selected="false">
                        <i class="bi bi-file-earmark-text me-1"></i> Por Archivo TXT (SRI)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold text-muted" id="tab-xml-btn" data-bs-toggle="tab" data-bs-target="#tab-xml" type="button" role="tab" aria-controls="tab-xml" aria-selected="false">
                        <i class="bi bi-filetype-xml me-1"></i> Carga Masiva XML
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold text-muted" id="tab-ignorados-btn" data-bs-toggle="tab" data-bs-target="#tab-ignorados" type="button" role="tab" aria-controls="tab-ignorados" aria-selected="false" onclick="listarDocumentosIgnorados()">
                        <i class="bi bi-slash-circle me-1"></i> Documentos para no descargar
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold text-muted" id="tab-auto-btn" data-bs-toggle="tab" data-bs-target="#tab-auto" type="button" role="tab" aria-controls="tab-auto" aria-selected="false" onclick="cargarConfigDescarga()">
                        <i class="bi bi-robot me-1"></i> Descarga Automática
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body px-4 py-4">
            <div class="tab-content" id="descargasTabsContent">

                <!-- Tab: Clave de Acceso -->
                <div class="tab-pane fade show active" id="tab-clave" role="tabpanel" aria-labelledby="tab-clave-btn">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Clave de Acceso</label>
                            <input type="text" id="clave_acceso_input" class="form-control form-control-sm shadow-none" maxlength="49" placeholder="Ingrese la clave de acceso de 49 dígitos..." inputmode="numeric">
                            <div class="form-text">Ejemplo: 0101202401179000000000120010000000000001234567812</div>
                            <button type="button" class="btn btn-primary btn-sm mt-3 shadow-sm px-4" id="btnProcesarClaves" onclick="procesarClaveAcceso()">
                                <i class="bi bi-cloud-download me-1"></i> Procesar Clave
                            </button>
                        </div>
                        <div class="col-md-6 border-start">
                            <div class="p-3 bg-light rounded text-muted small">
                                <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i>Instrucciones</h6>
                                <p class="mb-1">1. Ingrese una clave de acceso de comprobantes electrónicos (49 dígitos).</p>
                                <p class="mb-1">2. El sistema se conectará directamente a los servidores del SRI para recuperar el documento autorizado.</p>
                                <p class="mb-0">3. Podrá visualizar el resultado de cada solicitud en la tabla inferior.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Archivo TXT -->
                <div class="tab-pane fade" id="tab-txt" role="tabpanel" aria-labelledby="tab-txt-btn">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Subir archivo TXT descargado del SRI</label>
                            <input type="file" class="form-control form-control-sm shadow-none" id="archivo_txt_input" accept=".txt">
                            <div class="form-text">Sube el archivo .txt que descargas desde la plataforma del SRI.</div>

                            <div id="txt_claves_detectadas" class="d-none mt-3 p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3">
                                <strong class="text-success" id="txt_total_claves">0</strong> <span class="text-success small">claves detectadas listas para descargar.</span>
                                <button type="button" class="btn btn-success btn-sm ms-3" id="btnDescargarTxtSRI" onclick="iniciarDescargaClavesTxt()">
                                    <i class="bi bi-play-circle me-1"></i> Iniciar Descarga
                                </button>
                            </div>

                        </div>
                        <div class="col-md-6 border-start">
                            <div class="p-3 bg-light rounded text-muted small">
                                <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i>Instrucciones</h6>
                                <p class="mb-1">1. Descargue el reporte en formato TXT desde la plataforma de SRI en línea (Comprobantes Electrónicos Recibidos).</p>
                                <p class="mb-1">2. Cargue el archivo aquí. El sistema detectará automáticamente todas las claves de acceso contenidas.</p>
                                <p class="mb-0">3. Inicie el proceso para descargar y registrar cada XML.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Masivo XML -->
                <div class="tab-pane fade" id="tab-xml" role="tabpanel" aria-labelledby="tab-xml-btn">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Seleccionar Carpeta o Archivos XML</label>
                            <input type="file" class="form-control form-control-sm shadow-none" id="archivos_xml_input" accept=".xml" multiple webkitdirectory directory>
                            <div class="form-text">Puedes subir múltiples archivos XML o seleccionar una carpeta entera.</div>

                            <button type="button" class="btn btn-primary btn-sm mt-3 shadow-sm px-4" id="btnProcesarXml" onclick="procesarArchivosXml()">
                                <i class="bi bi-upload me-1"></i> Procesar XMLs
                            </button>
                        </div>
                        <div class="col-md-6 border-start">
                            <div class="p-3 bg-light rounded text-muted small">
                                <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i>Instrucciones</h6>
                                <p class="mb-1">1. Si ya tiene descargados los archivos XML físicos en su computadora, utilice esta opción.</p>
                                <p class="mb-1">2. Al seleccionar la carpeta, el sistema cargará y validará todos los comprobantes masivamente.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Documentos Ignorados -->
                <div class="tab-pane fade" id="tab-ignorados" role="tabpanel" aria-labelledby="tab-ignorados-btn">
                    <div class="row mb-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-muted">Clave de Acceso a Ignorar</label>
                            <input type="text" id="ignorado_clave_input" class="form-control form-control-sm" maxlength="49" placeholder="49 dígitos...">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-muted">Observaciones (Opcional)</label>
                            <input type="text" id="ignorado_obs_input" class="form-control form-control-sm" placeholder="Ej: Error en el SRI, Duplicado, etc.">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="agregarDocumentoIgnorado()">
                                <i class="bi bi-plus-circle me-1"></i> Agregar
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive border rounded-3 bg-white" style="max-height: 300px;">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3 py-2 small fw-bold">Fecha Doc.</th>
                                    <th class="py-2 small fw-bold">Clave de Acceso</th>
                                    <th class="py-2 small fw-bold">Emisor / Proveedor</th>
                                    <th class="py-2 small fw-bold">Observaciones</th>
                                    <th class="py-2 small fw-bold">Fecha Registro</th>
                                    <th class="py-2 small fw-bold text-center" style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="tbodyIgnorados">
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Cargando lista negra...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Descarga Automática -->
                <div class="tab-pane fade" id="tab-auto" role="tabpanel" aria-labelledby="tab-auto-btn">

                    <div class="row g-3">

                        <!-- Columna izquierda: Configuración -->
                        <div class="col-lg-5">

                            <!-- Acordeón configuración (colapsado por defecto) -->
                            <div class="accordion shadow-sm mb-3" id="accordionConfigSri">
                              <div class="accordion-item border border-secondary border-opacity-25 rounded-3 overflow-hidden">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-secondary bg-opacity-10 text-secondary fw-bold small py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConfigSri" aria-expanded="false" aria-controls="collapseConfigSri">
                                        <i class="bi bi-key-fill me-2"></i>Contraseña SRI
                                        <span id="auto_estado_badge" class="badge ms-2 bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:0.7rem;">Inactivo</span>
                                    </button>
                                </h2>
                                <div id="collapseConfigSri" class="accordion-collapse collapse" data-bs-parent="#accordionConfigSri">
                                  <div class="accordion-body px-3 py-3">

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Usuario SRI en Línea</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                            <input type="text" id="auto_sri_usuario" class="form-control form-control-sm shadow-none bg-light"
                                                value="<?= $rucEmpresa ?>" readonly>
                                        </div>
                                        <div class="form-text">RUC de la empresa activa - se usa automáticamente.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">
                                            Clave del portal SRI
                                            <span id="auto_clave_guardada_badge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2 d-none" style="font-size:0.7rem;">
                                                <i class="bi bi-lock-fill me-1"></i>Guardada
                                            </span>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <input type="password" id="auto_sri_clave" class="form-control form-control-sm shadow-none"
                                                placeholder="Dejar vacío para conservar la actual" autocomplete="new-password" maxlength="100">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleVerClave()" title="Ver/ocultar clave">
                                                <i class="bi bi-eye" id="iconoOjoClave"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Se almacena cifrada en la base de datos.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Estado</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="auto_estado" id="auto_estado_activo" value="activo" onchange="actualizarBadgeEstado('activo')">
                                                <label class="form-check-label small" for="auto_estado_activo">
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="auto_estado" id="auto_estado_inactivo" value="inactivo" checked onchange="actualizarBadgeEstado('inactivo')">
                                                <label class="form-check-label small" for="auto_estado_inactivo">
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-3">
                                        <button type="button" class="btn btn-primary btn-sm px-4" onclick="guardarConfigDescarga()">
                                            <i class="bi bi-floppy me-1"></i> Guardar
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="generarAgenteToken()" title="Token para el agente de escritorio (descarga desde tu PC)">
                                            <i class="bi bi-key me-1"></i> Token del agente
                                        </button>
                                    </div>

                                    <div id="agente_token_box" class="mt-3 d-none">
                                        <label class="form-label fw-semibold small text-muted mb-1">Tu token personal <span class="text-muted">(pégalo en la extensión de Chrome — sirve para todas tus empresas)</span></label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" id="agente_token_val" class="form-control form-control-sm font-monospace" readonly>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copiarAgenteToken()" title="Copiar"><i class="bi bi-clipboard"></i></button>
                                        </div>
                                        <div class="form-text text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Cópielo ahora. Si genera otro, el anterior deja de funcionar.</div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <!-- Descarga del SRI con la extensión -->
                            <div class="card border border-primary border-opacity-25 shadow-sm rounded-3 mb-3">
                                <div class="card-header bg-primary bg-opacity-10 border-bottom border-primary border-opacity-25 py-2 px-3">
                                    <h6 class="mb-0 fw-bold text-primary small"><i class="bi bi-cloud-arrow-down me-2"></i>Descarga del SRI</h6>
                                </div>
                                <div class="card-body px-3 py-3">
                                    <button type="button" class="btn btn-primary px-4" id="btnGenerarDescargaSri" onclick="generarDescargaSri()">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Generar descarga del SRI
                                    </button>
                                    <div class="small text-muted mt-3">
                                        <div class="fw-semibold mb-1">¿Qué hace este botón?</div>
                                        <ol class="ps-3 mb-2">
                                            <li>Abre el portal del SRI <strong>ya logueado</strong> con la empresa activa (la extensión escribe el RUC y la clave por ti).</li>
                                            <li>Aplica los <strong>filtros</strong> y consulta tus comprobantes recibidos (resuelve el captcha como siempre).</li>
                                            <li>Pulsa <strong>«Enviar comprobantes al sistema»</strong> en el portal. El resultado aparece en el historial de aquí.</li>
                                        </ol>
                                        <div class="text-muted">Requiere la <strong>extensión de Chrome</strong> instalada y tu <strong>token</strong> configurado (botón de arriba). La descarga automática por cron queda suspendida.</div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Columna derecha: Estado + Historial -->
                        <div class="col-lg-7">

                            <!-- Última ejecución -->
                            <div class="card border-0 shadow-sm rounded-3 mb-3">
                                <div class="card-header bg-white border-bottom py-2 px-3">
                                    <h6 class="mb-0 fw-bold text-dark small"><i class="bi bi-activity text-success me-2"></i>Última Ejecución</h6>
                                </div>
                                <div class="card-body px-3 py-3" id="auto_ultimo_estado_card">
                                    <p class="text-muted small mb-0">Sin datos de ejecución todavía.</p>
                                </div>
                            </div>

                            <!-- Historial -->
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 fw-bold text-dark small"><i class="bi bi-table text-muted me-2"></i>Historial de Descargas</h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <select id="historial_limite" class="form-select form-select-sm shadow-none py-0" style="width:auto;font-size:0.75rem;" onchange="cargarHistorialDescargas()" title="Cantidad de registros">
                                            <option value="5" selected>5 registros</option>
                                            <option value="20">20 registros</option>
                                            <option value="50">50 registros</option>
                                            <option value="100">100 registros</option>
                                            <option value="200">200 registros</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" onclick="cargarHistorialDescargas()" title="Actualizar">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height:500px;overflow-y:auto;">
                                        <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.78rem;">
                                            <thead class="bg-light sticky-top" style="top:0;z-index:1;">
                                                <tr>
                                                    <th class="ps-3 py-2">Fecha proceso</th>
                                                    <th class="py-2">Período</th>
                                                    <th class="py-2 text-center">Nuevas</th>
                                                    <th class="py-2 text-center">Exist.</th>
                                                    <th class="py-2 text-center">Errores</th>
                                                    <th class="py-2 text-center">Estado</th>
                                                    <th class="py-2 text-center">Origen</th>
                                                    <th class="py-2 text-center pe-2"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyHistorialDescargas">
                                                <tr>
                                                    <td colspan="8" class="text-center py-4 text-muted">Cargando historial...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Resultados -->
    <div class="card border-0 shadow-sm rounded-3 mt-4">
        <div class="card-header bg-white border-bottom-0 pt-3">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check text-success me-2"></i>Resultados del Procesamiento</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover table-sm align-middle mb-0" id="tablaResultados">
                    <thead class="bg-light sticky-top">
                        <tr>
                            <th class="ps-4">Documento</th>
                            <th>Número de Documento</th>
                            <th>Cliente / Proveedor</th>
                            <th class="text-end">Valor Total</th>
                            <th class="text-center">Estado</th>
                            <th class="pe-4">Mensaje</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyResultados">
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No se ha procesado ningún documento todavía.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/js/modulos/descargas_sri.js?v=<?= time() ?>"></script>