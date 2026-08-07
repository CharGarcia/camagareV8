<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $buscar */
/** @var array $bancos */
/** @var array $origenDato */
/** @var array $tiposArchivo */
$base = BASE_URL;
$rows = $rows ?? [];
$buscar = $buscar ?? '';
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);

$etiquetasTipoArchivo = [
    'xlsx' => 'Excel (.xlsx)',
    'csv' => 'CSV',
    'txt_delimitado' => 'TXT delimitado',
    'txt_ancho_fijo' => 'TXT ancho fijo',
];
$rowsHtml = $rowsHtml ?? '';
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<style>
.tf-wrap { max-width: 1300px; margin: 0 auto; }
.tf-scroll { max-height: calc(100dvh - 300px); overflow-y: auto; }
.tf-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.tf-campos-tbody td { padding: 2px 4px; vertical-align: middle; }
.tf-campos-tbody input, .tf-campos-tbody select { padding: 0 4px; height: 24px; font-size: 0.76rem; }
.tf-campos-tbody .form-check-input { margin-top: 0; }
</style>

<div class="tf-wrap">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-file-earmark-spreadsheet text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Catálogo global (no varía por empresa). Define las columnas y el tipo de archivo que <code>modulos/transferencias</code> usa para generar el archivo a subir al banco.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="TF_abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nuevo formato</button>
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
    <input type="text" id="input-buscar-tf" class="form-control" placeholder="Buscar por nombre o banco..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
</div>

<div class="card cmg-table-card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="tf-scroll">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nombre</th>
                        <th>Banco</th>
                        <th>Tipo de archivo</th>
                        <th class="text-center">Campos</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyTF"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Modal Formato -->
<div class="modal fade" id="tfModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="tfForm" action="<?= $base ?>/config/transferenciaFormatosStore">
                <input type="hidden" name="id" id="tf-id" value="">
                <input type="hidden" name="campos_json" id="tf-campos-json" value="[]">
                <div class="modal-header bg-light border-bottom-0">
                    <h5 class="modal-title fw-bold" id="tfModalTitle"><i class="bi bi-plus-circle"></i> Nuevo formato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="tf-solo-lectura-aviso" class="alert alert-info small py-2 d-none">
                        <i class="bi bi-info-circle me-1"></i>Este formato lo genera una clase PHP (<code id="tf-clase-nombre"></code>), no el motor por columnas. Solo se pueden editar nombre, banco y estado.
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Nombre</label>
                            <input type="text" id="tf-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Pichincha - Transferencias">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Banco</label>
                            <select id="tf-banco" name="id_banco" class="form-select form-select-sm">
                                <option value="">Genérico (cualquier banco)</option>
                                <?php foreach ($bancos as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre_banco']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Estado</label>
                            <select id="tf-estado" name="estado" class="form-select form-select-sm">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Descripción</label>
                            <input type="text" id="tf-descripcion" name="descripcion" class="form-control form-control-sm" placeholder="Opcional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo de archivo</label>
                            <select id="tf-tipo-archivo" name="tipo_archivo" class="form-select form-select-sm">
                                <?php foreach ($tiposArchivo as $t): ?>
                                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($etiquetasTipoArchivo[$t] ?? $t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 tf-solo-xlsx">
                            <label class="form-label small fw-bold">Nombre de la hoja</label>
                            <input type="text" id="tf-nombre-hoja" name="nombre_hoja" class="form-control form-control-sm" placeholder="Transferencias">
                        </div>
                        <div class="col-md-3 tf-solo-delimitado d-none">
                            <label class="form-label small fw-bold">Delimitador</label>
                            <input type="text" id="tf-delimitador" name="delimitador" class="form-control form-control-sm" maxlength="1" placeholder=",">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" id="tf-encabezado" name="incluye_encabezado" class="form-check-input" value="1" checked>
                                <label class="form-check-label small" for="tf-encabezado">Incluye fila de encabezado</label>
                            </div>
                        </div>
                    </div>

                    <div id="tf-campos-editor">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Columnas del archivo (en orden)</label>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="TF_agregarFilaCampo()"><i class="bi bi-plus-lg"></i> Agregar columna</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:3%" class="text-center">#</th>
                                        <th style="width:14%">Etiqueta</th>
                                        <th style="width:16%">Dato de origen</th>
                                        <th style="width:10%">Valor fijo</th>
                                        <th style="width:8%">Tipo</th>
                                        <th style="width:7%">Mayús.</th>
                                        <th style="width:7%">S/tildes</th>
                                        <th style="width:7%">Máx. car.</th>
                                        <th style="width:7%">Long. fija</th>
                                        <th style="width:7%">Relleno</th>
                                        <th style="width:14%">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="tf-campos-tbody" id="tf-campos-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.TF_ORIGEN_DATO = <?= json_encode($origenDato, JSON_UNESCAPED_UNICODE) ?>;
window.TF_STORE_URL = '<?= $base ?>/config/transferenciaFormatosStore';
window.TF_UPDATE_URL = '<?= $base ?>/config/transferenciaFormatosUpdate';
</script>
<script src="<?= $base ?>/js/transferenciaFormatos.js?v=<?= time() ?>"></script>
<script>
(function() {
    // Búsqueda en tiempo real: reemplaza solo la tabla vía AJAX, sin recargar
    // la página (el input nunca pierde el foco). Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    // Los botones de fila usan onclick inline, así que no hace falta
    // re-vincular eventos tras reemplazar el tbody.
    var base = '<?= $base ?>';
    var timer = null;

    window.TF_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-tf');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyTF');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/transferencia-formatos-search?b=' + encodeURIComponent(b), {
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows;
            })
            .catch(function() {
                if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar.</td></tr>';
            });
    };

    var inputBuscar = document.getElementById('input-buscar-tf');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                TF_cargarListado();
            }, 400);
        });
    }
})();
</script>
