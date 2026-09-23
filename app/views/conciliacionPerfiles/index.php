<?php
/** @var string $titulo */
/** @var array $perfiles */
/** @var array $bancos */
$base = BASE_URL;
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<style>
.cp-wrap { max-width: 1300px; margin: 0 auto; }
.cp-scroll { max-height: calc(100dvh - 300px); overflow-y: auto; overflow-x: auto; }
.cp-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
.cp-preview-box {
    max-height: 220px; overflow: auto; background: #212529; color: #d3d3d3;
    font-family: monospace; font-size: .78rem; padding: .5rem .75rem; border-radius: .375rem; white-space: pre;
}
</style>

<div class="cp-wrap">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-bank text-success"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Catálogo global (no varía por empresa). Define cómo leer el estado de cuenta de cada banco; en <code>modulos/conciliacion-cobros</code> cada empresa elige uno de estos formatos al subir el extracto.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="CP.abrirModal()"><i class="bi bi-plus-lg"></i> Nuevo perfil</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show py-2 small" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="input-group input-group-sm mb-3" style="max-width: 360px;">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="cp-buscar" class="form-control" placeholder="Buscar por nombre o banco..." autocomplete="off">
</div>

<div class="card cmg-table-card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="cp-scroll">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Banco</th>
                        <th>Nombre del perfil</th>
                        <th>Tipo de archivo</th>
                        <th class="text-center">Estado</th>
                        <th>Actualizado</th>
                        <th class="text-center pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="cp-tbody"></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ── Modal: Perfil de mapeo (crear/editar) ── -->
<div class="modal fade" id="cp-modal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="cp-modal-titulo">Perfil de Mapeo de Columnas</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cp-form" onsubmit="return false;">
                    <input type="hidden" id="cp-id">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Nombre del perfil</label>
                            <input type="text" id="cp-nombre" class="form-control form-control-sm" maxlength="150" placeholder="Ej: Banco Pichincha - Excel" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Banco</label>
                            <select id="cp-banco" class="form-select form-select-sm">
                                <option value="">Genérico (cualquier banco)</option>
                                <?php foreach ($bancos as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre_banco']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Tipo de archivo</label>
                            <select id="cp-tipo" class="form-select form-select-sm" onchange="CP.cambiarTipo()">
                                <option value="EXCEL">Excel / CSV</option>
                                <option value="PDF">PDF</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Separador decimal</label>
                            <select id="cp-separador" class="form-select form-select-sm">
                                <option value=".">Punto (1234.56)</option>
                                <option value=",">Coma (1234,56)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Formato de fecha</label>
                            <input type="text" id="cp-formato-fecha" class="form-control form-control-sm" value="d/m/Y" placeholder="d/m/Y">
                        </div>
                        <div class="col-md-2" id="cp-fila-inicio-wrap">
                            <label class="form-label small fw-bold text-muted mb-1" title="Filas de encabezado a saltar (solo Excel)">Filas a saltar</label>
                            <input type="number" id="cp-fila-inicio" class="form-control form-control-sm" value="0" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Estado</label>
                            <select id="cp-activo" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-2">
                        Sube un archivo de muestra (el mismo tipo que se descarga del banco) para ver la estructura real
                        y probar el mapeo antes de guardar. El archivo no se guarda.
                        <div class="d-flex gap-2 align-items-center mt-2">
                            <input type="file" id="cp-muestra" class="form-control form-control-sm" accept=".xlsx,.xls,.csv,.pdf">
                            <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="CP.previsualizarMuestra()">
                                <i class="bi bi-eye me-1"></i> Ver / Probar
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" id="cp-btn-sugerir-regex" style="display:none;" onclick="CP.sugerirRegexPdf()">
                                <i class="bi bi-magic me-1"></i> Sugerir patrón
                            </button>
                        </div>
                    </div>
                    <div class="cp-preview-box mb-3" id="cp-preview-box">— Sin previsualización aún —</div>
                    <div class="alert alert-info small py-2 mb-3" id="cp-sugerencia-msg" style="display:none;"></div>
                    <div id="cp-preview-resultado" class="mb-3" style="display:none;">
                        <h6 class="fw-bold small text-uppercase text-muted">Resultado de aplicar el mapeo actual</h6>
                        <div class="table-responsive" style="max-height:220px; overflow:auto;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead><tr><th>Fecha</th><th>Descripción</th><th class="text-end">Monto</th><th>Referencia</th></tr></thead>
                                <tbody id="cp-preview-resultado-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <h6 class="fw-bold small text-uppercase text-muted">Dónde está cada dato</h6>
                    <div id="cp-mapeo-excel" class="row g-2 mb-2">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Columna Fecha (0 = primera)</label>
                            <input type="number" id="cp-map-fecha-col" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Columna Descripción</label>
                            <input type="number" id="cp-map-descripcion-col" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Columna Monto (crédito)</label>
                            <input type="number" id="cp-map-monto-col" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Columna Referencia (opcional)</label>
                            <input type="number" id="cp-map-referencia-col" class="form-control form-control-sm" min="0">
                        </div>
                    </div>
                    <div id="cp-mapeo-pdf" class="row g-2 mb-2" style="display:none;">
                        <div class="col-12 mb-1">
                            <small class="text-muted">
                                Un PDF no tiene columnas: la descripción suele venir partida en varias líneas y los
                                datos (fecha, monto...) aparecen en la línea que "cierra" el movimiento. Indica un
                                patrón (regex) que reconozca esa línea, usando grupos nombrados
                                <code>(?&lt;fecha&gt;...)</code> y <code>(?&lt;monto&gt;...)</code> obligatorios, y
                                opcionalmente <code>(?&lt;tipo&gt;...)</code> y <code>(?&lt;documento&gt;...)</code>.
                            </small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small mb-1">Patrón (regex) de línea de datos</label>
                            <input type="text" id="cp-map-regex-linea" class="form-control form-control-sm font-monospace"
                                   placeholder="/(?<fecha>\d{2}\/\d{2}\/\d{4})\s+(?<documento>\d+)\s+(?<tipo>[A-Z])\s+[A-Z. ]+?\s+(?<monto>[\d,]+\.\d{2})\s+[\d,]+\.\d{2}\s+\d+\s*$/">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Valor "es crédito" (opcional)</label>
                            <input type="text" id="cp-map-tipo-credito" class="form-control form-control-sm" placeholder="C">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="CP.previsualizarMuestra()">
                                <i class="bi bi-play-fill"></i> Probar
                            </button>
                        </div>
                    </div>
                    <div class="small text-muted mt-2" id="cp-auditoria"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="cp-btn-eliminar" onclick="CP.eliminar()">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="CP.guardar()">
                    <i class="bi bi-save me-1"></i> Guardar Perfil
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const CP_URL = "<?= rtrim($base, '/') ?>/config/conciliacion-perfiles";
    window.CP_PERFILES = <?= json_encode($perfiles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= $base ?>/js/conciliacionPerfiles.js?v=<?= asset_ver('/js/conciliacionPerfiles.js') ?>"></script>
