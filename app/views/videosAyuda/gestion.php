<?php
/**
 * Gestión de videos de ayuda — página STANDALONE (solo superadmin, nivel 3).
 * Se abre desde el botón "Administrar" del visor. Tabla estándar + modales.
 *
 * @var string $titulo
 * @var array  $rows
 * @var string $ordenCol
 * @var string $ordenDir
 * @var string $buscar
 */
$base = rtrim(BASE_URL ?? '', '/');
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'orden';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$rowsHtml = $rowsHtml ?? '';

// Límite efectivo de subida del servidor = min(upload_max_filesize, post_max_size).
$iniABytes = static function (string $v): int {
    $v = trim($v);
    if ($v === '') return 0;
    $u = strtolower($v[strlen($v) - 1]);
    $n = (int) $v;
    return match ($u) {
        'g' => $n * 1024 * 1024 * 1024,
        'm' => $n * 1024 * 1024,
        'k' => $n * 1024,
        default => (int) $v,
    };
};
$limiteSubida = min($iniABytes((string) ini_get('upload_max_filesize')), $iniABytes((string) ini_get('post_max_size')));
$maxServicio = 524288000; // 500 MB (tope del Service)
$limiteEfectivo = ($limiteSubida > 0) ? min($limiteSubida, $maxServicio) : $maxServicio;

$fmtTam = static function ($bytes): string {
    $b = (float) $bytes;
    if ($b <= 0) return '-';
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return number_format($b, $b < 10 && $i > 0 ? 1 : 0) . ' ' . $u[$i];
};

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { background: #f4f6f9; display: flex; flex-direction: column; overflow: hidden; }
        .vg-header { flex: 0 0 auto; }
        .vg-toolbar { flex: 0 0 auto; }
        .vg-card { flex: 1 1 auto; min-height: 0; margin: 0 .75rem .75rem; }
        .vg-scroll { overflow: auto; height: 100%; }
        .vg-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
        .vg-row { cursor: pointer; }
        .vg-row:hover { background: rgba(0,0,0,.04); }
    </style>
</head>
<body>
    <!-- Encabezado -->
    <div class="vg-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-collection-play-fill fs-4"></i>
            <div class="fw-semibold"><?= htmlspecialchars($titulo) ?></div>
        </div>
        <a href="<?= $base ?>/videos-ayuda" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver al visor</a>
    </div>

    <!-- Barra de herramientas -->
    <div class="vg-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2">
        <div class="input-group input-group-sm" style="max-width: 320px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="input-buscar-vg" class="form-control" placeholder="Buscar por título o categoría..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="btn-nuevo-video">
            <i class="bi bi-cloud-upload me-1"></i>Subir video
        </button>
    </div>

    <!-- Tabla -->
    <div class="card vg-card">
        <div class="card-body p-0 h-100">
            <div class="vg-scroll">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="text-center sortable-header" data-sort="orden" role="button" style="width:70px;">Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="titulo" role="button">Título <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="categoria" role="button">Categoría <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center sortable-header" data-sort="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center sortable-header" data-sort="vistas" role="button">Vistas <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center">Likes</th>
                            <th class="text-end sortable-header" data-sort="tamano_bytes" role="button">Tamaño <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end" style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyVideos"><?= $rowsHtml ?></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Subir / Editar -->
    <div class="modal fade" id="modalVideo" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="form-video" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="v-id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalVideoTitulo"><i class="bi bi-cloud-upload me-1"></i>Subir video</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="v-msg" class="d-none"></div>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="v-titulo" class="form-label">Título <span class="text-danger">*</span></label>
                                <input type="text" id="v-titulo" name="titulo" class="form-control form-control-sm" required maxlength="200">
                            </div>
                            <div class="col-md-4">
                                <label for="v-categoria" class="form-label">Categoría</label>
                                <input type="text" id="v-categoria" name="categoria" class="form-control form-control-sm" list="v-categorias" maxlength="100" placeholder="Ej: Ventas">
                                <datalist id="v-categorias">
                                    <option value="General"><option value="Ventas"><option value="Compras">
                                    <option value="Inventario"><option value="Contabilidad"><option value="Configuración">
                                </datalist>
                            </div>
                            <div class="col-12">
                                <label for="v-descripcion" class="form-label">Descripción</label>
                                <textarea id="v-descripcion" name="descripcion" class="form-control form-control-sm" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <label for="v-etiquetas" class="form-label">
                                    Etiquetas / palabras clave
                                    <i class="bi bi-info-circle text-muted" title="Palabras separadas por comas que la gente usaría al buscar (sinónimos incluidos). Ej: vender, venta, cobrar, cliente"></i>
                                </label>
                                <input type="text" id="v-etiquetas" name="etiquetas" class="form-control form-control-sm" placeholder="Ej: vender, venta, cobrar, cliente, comprobante">
                                <small class="text-muted">Separadas por comas. Mejoran la búsqueda del visor (incluye sinónimos).</small>
                            </div>
                            <div class="col-md-3">
                                <label for="v-orden" class="form-label">Orden</label>
                                <input type="number" id="v-orden" name="orden" class="form-control form-control-sm" value="0" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="v-estado" class="form-label">Estado</label>
                                <select id="v-estado" name="estado" class="form-select form-select-sm">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="v-archivo" class="form-label">
                                    Archivo de video <span class="text-danger" id="v-archivo-req">*</span>
                                </label>
                                <input type="file" id="v-archivo" name="archivo" class="form-control form-control-sm" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.ogv,.mov,.m4v">
                                <small class="text-muted" id="v-archivo-hint">MP4, WebM u OGG. Máx. 150 MB.</small>
                            </div>
                        </div>
                        <div class="progress mt-3 d-none" id="v-progress-wrap" style="height: 18px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="v-progress" style="width:0%">0%</div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-outline-danger d-none" id="v-btn-eliminar"><i class="bi bi-trash me-1"></i>Eliminar</button>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="v-btn-guardar"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: quién ha visto el video -->
    <div class="modal fade" id="modalVistas" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-people me-1"></i>Quién ha visto: <span id="mv-titulo" class="fw-normal"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="mv-body">
                        <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Cargando...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $base ?>/js/favoritos.js?v=<?= time() ?>"></script>
    <script>
        window.VA_BASE = '<?= $base ?>';
        window.VA_MAX_UPLOAD = <?= (int) $limiteEfectivo ?>;
        window.VA_MAX_UPLOAD_TXT = '<?= htmlspecialchars($fmtTam($limiteEfectivo), ENT_QUOTES) ?>';
    </script>
    <script src="<?= $base ?>/js/videos-ayuda.js?v=<?= time() ?>"></script>
    <script>
    (function () {
        // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX,
        // sin recargar la página (el input nunca pierde el foco). Mismo
        // patrón que ASIENTOTIPO_cargarListado
        // (public/js/modulos/asientos_tipo_modal.js).
        var base = '<?= $base ?>';
        var timer = null;
        window.VG_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
        window.VG_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

        window.VG_cargarListado = function () {
            var inputB = document.getElementById('input-buscar-vg');
            var b = inputB ? inputB.value.trim() : '';
            var tbodyEl = document.getElementById('tbodyVideos');
            if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

            fetch(base + '/videos-ayuda/gestion-search?b=' + encodeURIComponent(b) + '&sort=' + window.VG_currentSort + '&dir=' + window.VG_currentDir, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows;
                })
                .catch(function () {
                    if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Error al cargar.</td></tr>';
                });
        };

        var inputBuscarVg = document.getElementById('input-buscar-vg');
        if (inputBuscarVg) {
            inputBuscarVg.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { VG_cargarListado(); }, 400);
            });
        }

        if (window.CMG_initSort) {
            window.CMG_initSort('videos-ayuda-gestion', function (col, dir) {
                window.VG_currentSort = col;
                window.VG_currentDir = dir;
                VG_cargarListado();
            }, { col: window.VG_currentSort, dir: window.VG_currentDir });
        }
    })();
    </script>
</body>
</html>
