<?php
/** @var string $titulo */
/** @var array $perfiles */
/** @var array $bancos */
/** @var string $ordenCol */
/** @var string $ordenDir */
$base = BASE_URL;
$ordenCol = $ordenCol ?? 'nombre_banco';
$ordenDir = $ordenDir ?? 'ASC';
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);
?>
<style>
.perfil-row { cursor: pointer; }
.perfil-row:hover { background-color: rgba(0,0,0,.04); }
.conciliacion-perfiles-header { flex-shrink: 0; }
.conciliacion-perfiles-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.conciliacion-perfiles-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.cp-preview-box {
    max-height: 220px; overflow: auto; background: #212529; color: #d3d3d3;
    font-family: monospace; font-size: .78rem; padding: .5rem .75rem; border-radius: .375rem; white-space: pre;
}
</style>
<div class="conciliacion-perfiles-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-bank"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Formato del estado de cuenta de cada banco que usa Conciliación de Cobros (todas las empresas).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="CP.abrirModal()"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="input-group input-group-sm mb-3" style="max-width: 320px;">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="cp-buscar" class="form-control" placeholder="Buscar en nombre, banco, tipo..." autocomplete="off">
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="conciliacion-perfiles-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="nombre_banco" role="button">Banco <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_perfil" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tipo_archivo" role="button">Tipo de archivo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="activo" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="cp-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Perfil de mapeo (crear / editar) -->
<div class="modal fade" id="cp-modal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="cp-form" onsubmit="CP.guardar(); return false;">
                <input type="hidden" id="cp-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="cp-modal-titulo"><i class="bi bi-plus-circle"></i> Nuevo perfil de mapeo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="cp-tab-datos" data-bs-toggle="tab" data-bs-target="#cp-pane-datos" type="button" role="tab">Datos del perfil</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#cp-pane-mapeo" type="button" role="tab">Mapeo de columnas</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#cp-pane-prueba" type="button" role="tab">Probar con archivo</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <!-- Datos del perfil -->
                        <div class="tab-pane fade show active" id="cp-pane-datos" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="cp-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" id="cp-nombre" class="form-control form-control-sm" maxlength="150" placeholder="Ej: Banco Pichincha - Excel">
                                </div>
                                <div class="col-md-4">
                                    <label for="cp-activo" class="form-label">Estado</label>
                                    <select id="cp-activo" class="form-select form-select-sm">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label for="cp-banco" class="form-label">Banco</label>
                                    <select id="cp-banco" class="form-select form-select-sm">
                                        <option value="">Genérico (cualquier banco)</option>
                                        <?php foreach ($bancos as $b): ?>
                                            <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre_banco']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="cp-tipo" class="form-label">Tipo de archivo <span class="text-danger">*</span></label>
                                    <select id="cp-tipo" class="form-select form-select-sm" onchange="CP.cambiarTipo()">
                                        <option value="EXCEL">Excel / CSV</option>
                                        <option value="PDF">PDF</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="cp-formato-fecha" class="form-label">Formato de fecha</label>
                                    <input type="text" id="cp-formato-fecha" class="form-control form-control-sm" value="d/m/Y" placeholder="d/m/Y">
                                </div>
                                <div class="col-md-4">
                                    <label for="cp-separador" class="form-label">Separador decimal</label>
                                    <select id="cp-separador" class="form-select form-select-sm">
                                        <option value=".">Punto (1234.56)</option>
                                        <option value=",">Coma (1234,56)</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="cp-fila-inicio-wrap">
                                    <label for="cp-fila-inicio" class="form-label">Filas de encabezado a saltar</label>
                                    <input type="number" id="cp-fila-inicio" class="form-control form-control-sm" value="0" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Mapeo de columnas -->
                        <div class="tab-pane fade" id="cp-pane-mapeo" role="tabpanel">
                            <div id="cp-mapeo-excel" class="row g-3">
                                <div class="col-12">
                                    <small class="text-muted">Número de columna de cada dato en el Excel/CSV del banco. La primera columna es la 0.</small>
                                </div>
                                <div class="col-md-3">
                                    <label for="cp-map-fecha-col" class="form-label">Fecha <span class="text-danger">*</span></label>
                                    <input type="number" id="cp-map-fecha-col" class="form-control form-control-sm" min="0">
                                </div>
                                <div class="col-md-3">
                                    <label for="cp-map-descripcion-col" class="form-label">Descripción <span class="text-danger">*</span></label>
                                    <input type="number" id="cp-map-descripcion-col" class="form-control form-control-sm" min="0">
                                </div>
                                <div class="col-md-3">
                                    <label for="cp-map-monto-col" class="form-label">Monto (crédito) <span class="text-danger">*</span></label>
                                    <input type="number" id="cp-map-monto-col" class="form-control form-control-sm" min="0">
                                </div>
                                <div class="col-md-3">
                                    <label for="cp-map-referencia-col" class="form-label">Referencia</label>
                                    <input type="number" id="cp-map-referencia-col" class="form-control form-control-sm" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label for="cp-map-descripcion_extra-col" class="form-label">Descripción adicional</label>
                                    <input type="number" id="cp-map-descripcion_extra-col" class="form-control form-control-sm" min="0">
                                    <small class="text-muted">Se une a la descripción (p. ej. nombre de quien paga + tipo de transacción).</small>
                                </div>
                                <div class="col-md-4">
                                    <label for="cp-map-tipo-col" class="form-label">Tipo / signo</label>
                                    <input type="number" id="cp-map-tipo-col" class="form-control form-control-sm" min="0">
                                    <small class="text-muted">Columna que indica si es ingreso o egreso.</small>
                                </div>
                                <div class="col-md-4">
                                    <label for="cp-map-tipo-credito-excel" class="form-label">Valor "es crédito"</label>
                                    <input type="text" id="cp-map-tipo-credito-excel" class="form-control form-control-sm" placeholder="+ o C">
                                    <small class="text-muted">Solo se importan las filas con este valor.</small>
                                </div>
                            </div>
                            <div id="cp-mapeo-pdf" class="row g-3" style="display:none;">
                                <div class="col-12">
                                    <small class="text-muted">
                                        Un PDF no tiene columnas: la descripción suele venir partida en varias líneas y los
                                        datos (fecha, monto...) aparecen en la línea que "cierra" el movimiento. Indique un
                                        patrón (regex) que reconozca esa línea, con los grupos nombrados
                                        <code>(?&lt;fecha&gt;...)</code> y <code>(?&lt;monto&gt;...)</code> obligatorios, y
                                        opcionalmente <code>(?&lt;tipo&gt;...)</code> y <code>(?&lt;documento&gt;...)</code>.
                                    </small>
                                </div>
                                <div class="col-md-9">
                                    <label for="cp-map-regex-linea" class="form-label">Patrón (regex) de línea de datos <span class="text-danger">*</span></label>
                                    <input type="text" id="cp-map-regex-linea" class="form-control form-control-sm font-monospace"
                                           placeholder="/(?<fecha>\d{2}\/\d{2}\/\d{4})\s+(?<documento>\d+)\s+(?<tipo>[A-Z])\s+[A-Z. ]+?\s+(?<monto>[\d,]+\.\d{2})\s+[\d,]+\.\d{2}\s+\d+\s*$/">
                                </div>
                                <div class="col-md-3">
                                    <label for="cp-map-tipo-credito" class="form-label">Valor "es crédito"</label>
                                    <input type="text" id="cp-map-tipo-credito" class="form-control form-control-sm" placeholder="C">
                                </div>
                            </div>
                        </div>

                        <!-- Probar con archivo de muestra -->
                        <div class="tab-pane fade" id="cp-pane-prueba" role="tabpanel">
                            <div class="mb-3">
                                <label for="cp-muestra" class="form-label">Archivo de muestra del banco</label>
                                <div class="input-group input-group-sm">
                                    <input type="file" id="cp-muestra" class="form-control form-control-sm" accept=".xlsx,.xls,.csv,.pdf">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="CP.previsualizarMuestra()"><i class="bi bi-eye"></i> Ver / Probar</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="cp-btn-sugerir-regex" style="display:none;" onclick="CP.sugerirRegexPdf()"><i class="bi bi-magic"></i> Sugerir patrón</button>
                                </div>
                                <small class="text-muted">Muestra el archivo tal como lo lee el sistema y prueba el mapeo actual. El archivo no se guarda.</small>
                            </div>
                            <div class="alert alert-info small py-2 mb-3" id="cp-sugerencia-msg" style="display:none;"></div>
                            <div class="cp-preview-box mb-3" id="cp-preview-box">— Sin previsualización aún —</div>
                            <div id="cp-preview-resultado" style="display:none;">
                                <label class="form-label small fw-semibold">Resultado de aplicar el mapeo actual</label>
                                <div class="table-responsive" style="max-height:220px; overflow:auto;">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light"><tr><th>Fecha</th><th>Descripción</th><th class="text-end">Monto</th><th>Referencia</th></tr></thead>
                                        <tbody id="cp-preview-resultado-tbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-3" id="cp-auditoria"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="cp-btn-eliminar" onclick="CP.eliminar()"><i class="bi bi-trash"></i> Eliminar</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="cp-btn-guardar"><i class="bi bi-check-lg"></i> Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const CP_URL = "<?= rtrim($base, '/') ?>/config/conciliacion-perfiles";
    window.CP_PERFILES = <?= json_encode($perfiles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    window.CP_ORDEN = { col: <?= json_encode($ordenCol) ?>, dir: <?= json_encode($ordenDir) ?> };
</script>
<script src="<?= $base ?>/js/conciliacionPerfiles.js?v=<?= asset_ver('/js/conciliacionPerfiles.js') ?>"></script>
