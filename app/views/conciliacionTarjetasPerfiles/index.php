<?php
/** @var string $titulo */
/** @var array $perfiles */
/** @var array $bancos */
/** @var string $ordenCol */
/** @var string $ordenDir */
$base = BASE_URL;
$ordenCol = $ordenCol ?? 'tipo_procesadora';
$ordenDir = $ordenDir ?? 'ASC';
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);
?>
<style>
.perfil-row { cursor: pointer; }
.perfil-row:hover { background-color: rgba(0,0,0,.04); }
.conciliacion-tarjetas-perfiles-header { flex-shrink: 0; }
.conciliacion-tarjetas-perfiles-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.conciliacion-tarjetas-perfiles-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.ctp-preview-box {
    max-height: 220px; overflow: auto; background: #212529; color: #d3d3d3;
    font-family: monospace; font-size: .78rem; padding: .5rem .75rem; border-radius: .375rem; white-space: pre;
}
</style>
<div class="conciliacion-tarjetas-perfiles-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-credit-card"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Formato del estado de cuenta de cada procesadora (Payphone, Nuvei, datáfono) que usa Conciliación de Tarjetas (todas las empresas).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="CTP.abrirModal()"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
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
    <input type="text" id="ctp-buscar" class="form-control" placeholder="Buscar en nombre, procesadora, banco..." autocomplete="off">
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="conciliacion-tarjetas-perfiles-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="tipo_procesadora" role="button">Procesadora <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_banco" role="button">Banco <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_perfil" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tipo_archivo" role="button">Archivo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nivel" role="button">Contenido <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="activo" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="ctp-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Perfil de lectura (crear / editar) -->
<div class="modal fade" id="ctp-modal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="ctp-form" onsubmit="CTP.guardar(); return false;">
                <input type="hidden" id="ctp-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="ctp-modal-titulo"><i class="bi bi-plus-circle"></i> Nuevo perfil de lectura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="ctp-tab-datos" data-bs-toggle="tab" data-bs-target="#ctp-pane-datos" type="button" role="tab">Datos del perfil</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ctp-pane-mapeo" type="button" role="tab">Mapeo de columnas</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ctp-pane-prueba" type="button" role="tab">Probar con archivo</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <!-- Datos del perfil -->
                        <div class="tab-pane fade show active" id="ctp-pane-datos" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="ctp-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" id="ctp-nombre" class="form-control form-control-sm" maxlength="100" placeholder="Ej: Payphone - reporte mensual">
                                </div>
                                <div class="col-md-4">
                                    <label for="ctp-activo" class="form-label">Estado</label>
                                    <select id="ctp-activo" class="form-select form-select-sm">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="ctp-procesadora" class="form-label">Procesadora</label>
                                    <select id="ctp-procesadora" class="form-select form-select-sm">
                                        <option value="">Cualquiera</option>
                                        <option value="PAYPHONE">Payphone</option>
                                        <option value="NUVEI">Nuvei</option>
                                        <option value="TARJETA">Tarjeta (datáfono)</option>
                                    </select>
                                    <small class="text-muted">Tipo de la forma de cobro que se concilia.</small>
                                </div>
                                <div class="col-md-8">
                                    <label for="ctp-banco" class="form-label">Banco</label>
                                    <select id="ctp-banco" class="form-select form-select-sm">
                                        <option value="">Cualquier banco</option>
                                        <?php foreach ($bancos as $b): ?>
                                            <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre_banco']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Útil para el datáfono: el reporte cambia según el banco de la forma de cobro.</small>
                                </div>
                                <div class="col-md-4">
                                    <label for="ctp-tipo" class="form-label">Tipo de archivo <span class="text-danger">*</span></label>
                                    <select id="ctp-tipo" class="form-select form-select-sm" onchange="CTP.cambiarTipo()">
                                        <option value="EXCEL">Excel</option>
                                        <option value="CSV">CSV</option>
                                        <option value="PDF">PDF</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label for="ctp-nivel" class="form-label">El archivo trae <span class="text-danger">*</span></label>
                                    <select id="ctp-nivel" class="form-select form-select-sm">
                                        <option value="transaccion">Una línea por transacción (cada cobro)</option>
                                        <option value="deposito">Los depósitos consolidados (un total por día/lote)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="ctp-formato-fecha" class="form-label">Formato de fecha</label>
                                    <input type="text" id="ctp-formato-fecha" class="form-control form-control-sm" value="d/m/Y" placeholder="d/m/Y">
                                </div>
                                <div class="col-md-4">
                                    <label for="ctp-separador" class="form-label">Separador decimal</label>
                                    <select id="ctp-separador" class="form-select form-select-sm">
                                        <option value=".">Punto (1234.56)</option>
                                        <option value=",">Coma (1234,56)</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="ctp-fila-inicio-wrap">
                                    <label for="ctp-fila-inicio" class="form-label">Filas de encabezado a saltar</label>
                                    <input type="number" id="ctp-fila-inicio" class="form-control form-control-sm" value="1" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Mapeo de columnas -->
                        <div class="tab-pane fade" id="ctp-pane-mapeo" role="tabpanel">
                            <div id="ctp-mapeo-excel">
                                <small class="text-muted d-block mb-2">Número de columna de cada dato en el Excel/CSV de la procesadora. La primera columna es la 0. Deje vacío lo que el archivo no trae.</small>
                                <div class="row g-3" id="ctp-mapeo-campos"></div>
                            </div>
                            <div id="ctp-mapeo-pdf" class="row g-3" style="display:none;">
                                <div class="col-12">
                                    <small class="text-muted">
                                        Un PDF no tiene columnas: indique un patrón (regex) que reconozca cada línea de datos,
                                        con los grupos nombrados <code>(?&lt;fecha&gt;...)</code> y <code>(?&lt;monto_bruto&gt;...)</code>
                                        obligatorios, y opcionalmente <code>(?&lt;autorizacion&gt;...)</code>, <code>(?&lt;referencia&gt;...)</code>,
                                        <code>(?&lt;comision&gt;...)</code>, <code>(?&lt;monto_neto&gt;...)</code>, etc.
                                    </small>
                                </div>
                                <div class="col-12">
                                    <label for="ctp-regex" class="form-label">Patrón (regex) de línea de datos <span class="text-danger">*</span></label>
                                    <input type="text" id="ctp-regex" class="form-control form-control-sm font-monospace"
                                           placeholder="/(?<fecha>\d{2}\/\d{2}\/\d{4})\s+(?<autorizacion>\d+)\s+(?<monto_bruto>[\d,]+\.\d{2})/">
                                </div>
                            </div>
                        </div>

                        <!-- Probar con archivo de muestra -->
                        <div class="tab-pane fade" id="ctp-pane-prueba" role="tabpanel">
                            <div class="mb-3">
                                <label for="ctp-muestra" class="form-label">Archivo de muestra de la procesadora</label>
                                <div class="input-group input-group-sm">
                                    <input type="file" id="ctp-muestra" class="form-control form-control-sm" accept=".xlsx,.xls,.csv,.pdf">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="CTP.previsualizarMuestra()"><i class="bi bi-eye"></i> Ver / Probar</button>
                                </div>
                                <small class="text-muted">Muestra el archivo tal como lo lee el sistema y prueba el mapeo actual. El archivo no se guarda.</small>
                            </div>
                            <div class="ctp-preview-box mb-3" id="ctp-preview-box">— Sin previsualización aún —</div>
                            <div id="ctp-preview-resultado" style="display:none;">
                                <label class="form-label small fw-semibold">Resultado de aplicar el mapeo actual</label>
                                <div class="table-responsive" style="max-height:220px; overflow:auto;">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr><th>Fecha</th><th>Autorización</th><th class="text-end">Bruto</th><th class="text-end">Comisión</th><th class="text-end">Neto</th></tr>
                                        </thead>
                                        <tbody id="ctp-preview-resultado-tbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-3" id="ctp-auditoria"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="ctp-btn-eliminar" onclick="CTP.eliminar()"><i class="bi bi-trash"></i> Eliminar</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="ctp-btn-guardar"><i class="bi bi-check-lg"></i> Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const CTP_URL = "<?= rtrim($base, '/') ?>/config/conciliacion-tarjetas-perfiles";
    window.CTP_PERFILES = <?= json_encode($perfiles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    window.CTP_ORDEN = { col: <?= json_encode($ordenCol) ?>, dir: <?= json_encode($ordenDir) ?> };
</script>
<script src="<?= $base ?>/js/conciliacionTarjetasPerfiles.js?v=<?= asset_ver('/js/conciliacionTarjetasPerfiles.js') ?>"></script>
