<?php
/**
 * Gestión del Manual del Sistema — página STANDALONE (solo superadmin, nivel 3).
 * Se abre desde el botón "Administrar" del visor. Tabla estándar + modales.
 *
 * @var string $titulo
 * @var array  $rows          Artículos (no eliminados)
 * @var array  $categorias    Categorías ya usadas (datalist)
 * @var array  $videos        Videos de ayuda activos que se pueden enlazar
 * @var array  $sinResultado  Búsquedas que no encontraron nada
 * @var string $ordenCol
 * @var string $ordenDir
 * @var string $buscar
 */
$base         = rtrim(BASE_URL ?? '', '/');
$rows         = $rows ?? [];
$categorias   = $categorias ?? [];
$videos       = $videos ?? [];
$sinResultado = $sinResultado ?? [];
$ordenCol     = $ordenCol ?? 'categoria';
$ordenDir     = $ordenDir ?? 'asc';
$buscar       = $buscar ?? '';
$rowsHtml     = $rowsHtml ?? '';

$fmtFecha = static function ($v): string {
    if (empty($v)) {
        return '-';
    }
    $ts = strtotime((string) $v);
    return $ts ? date('d-m-Y H:i:s', $ts) : '-';
};
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <?php require MVC_APP . "/views/partials/csrf.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { background: #f4f6f9; display: flex; flex-direction: column; overflow: hidden; }
        .dg-header, .dg-toolbar { flex: 0 0 auto; }
        .dg-card { flex: 1 1 auto; min-height: 0; margin: 0 .75rem .75rem; }
        .dg-scroll { overflow: auto; height: 100%; }
        .dg-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
        .dg-row { cursor: pointer; }
        .dg-row:hover { background: rgba(0,0,0,.04); }
        .dg-slug { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; color: #6c757d; }
        #dg-editor { height: 340px; background: #fff; }
        .dg-ayuda { font-size: .78rem; color: #6c757d; }
    </style>
</head>
<body>
    <!-- Encabezado -->
    <div class="dg-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-journal-bookmark-fill fs-4"></i>
            <div class="fw-semibold"><?= htmlspecialchars($titulo) ?></div>
        </div>
        <a href="<?= $base ?>/documentacion" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver al manual
        </a>
    </div>

    <!-- Barra de herramientas -->
    <div class="dg-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2">
        <div class="input-group input-group-sm" style="max-width: 340px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="input-buscar-dg" class="form-control" placeholder="Buscar por título, dirección o categoría…"
                   value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" id="btn-doctor"
                    title="Qué pantallas del sistema siguen sin documentar">
                <i class="bi bi-clipboard2-pulse me-1"></i>Doctor
            </button>
            <button type="button" class="btn btn-outline-info btn-sm" id="btn-sincronizar"
                    title="Publica los artículos escritos como .md en docs/manual/">
                <i class="bi bi-arrow-repeat me-1"></i>Sincronizar
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSinResultado">
                <i class="bi bi-question-circle me-1"></i>Búsquedas sin resultado
                <?php if (count($sinResultado) > 0): ?>
                <span class="badge bg-danger ms-1"><?= count($sinResultado) ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-nuevo">
                <i class="bi bi-plus-lg me-1"></i>Nuevo artículo
            </button>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card dg-card">
        <div class="card-body p-0 h-100">
            <div class="dg-scroll">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="text-center sortable-header" data-sort="orden" role="button" style="width:64px;">Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="titulo" role="button">Título <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="categoria" role="button">Categoría <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" data-sort="tipo" role="button">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center sortable-header" data-sort="visibilidad" role="button">Visibilidad <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center sortable-header" data-sort="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center sortable-header" data-sort="origen" role="button">Origen <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center sortable-header" data-sort="vistas" role="button">Lecturas <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center">Valoración</th>
                            <th class="sortable-header" data-sort="updated_at" role="button">Actualizado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end" style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyDg"><?= $rowsHtml ?></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: crear / editar artículo -->
    <div class="modal fade" id="modalArticulo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="modalArticuloTitulo">
                        <i class="bi bi-file-earmark-plus me-1"></i>Nuevo artículo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="form-articulo">
                    <input type="hidden" name="id" id="a-id">
                    <input type="hidden" name="contenido_html" id="a-contenido">
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label mb-0 small">Título <span class="text-danger">*</span></label>
                                <input type="text" name="titulo" id="a-titulo" class="form-control form-control-sm" required maxlength="200">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-0 small">Dirección (slug)</label>
                                <input type="text" name="slug" id="a-slug" class="form-control form-control-sm" maxlength="150"
                                       placeholder="modulos/clientes">
                                <div class="dg-ayuda">Se usa en el enlace del manual. Si la deja vacía se genera del título.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-0 small">Resumen</label>
                                <input type="text" name="resumen" id="a-resumen" class="form-control form-control-sm"
                                       placeholder="Una o dos líneas: qué resuelve este artículo.">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-0 small">Categoría</label>
                                <input type="text" name="categoria" id="a-categoria" class="form-control form-control-sm"
                                       list="lista-categorias" maxlength="100" placeholder="Ventas">
                                <datalist id="lista-categorias">
                                    <?php foreach ($categorias as $c): ?>
                                    <option value="<?= htmlspecialchars((string) $c) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-0 small">Ruta del módulo</label>
                                <input type="text" name="ruta_modulo" id="a-ruta-modulo" class="form-control form-control-sm"
                                       maxlength="150" placeholder="modulos/clientes">
                                <div class="dg-ayuda">Enlaza el artículo con el módulo (ayuda contextual y permisos).</div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Tipo</label>
                                <select name="tipo" id="a-tipo" class="form-select form-select-sm">
                                    <option value="modulo">Módulo</option>
                                    <option value="guia">Guía</option>
                                    <option value="concepto">Concepto</option>
                                    <option value="faq">Preguntas</option>
                                    <option value="novedad">Novedad</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Orden</label>
                                <input type="number" name="orden" id="a-orden" class="form-control form-control-sm" value="0">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-0 small">Visibilidad</label>
                                <select name="visibilidad" id="a-visibilidad" class="form-select form-select-sm">
                                    <option value="todos">Todos los usuarios</option>
                                    <option value="admin">Administradores (nivel 2+)</option>
                                    <option value="superadmin">Solo superadministrador</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small">Estado</label>
                                <select name="estado" id="a-estado" class="form-select form-select-sm">
                                    <option value="activo">Activo (visible)</option>
                                    <option value="borrador">Borrador</option>
                                    <option value="obsoleto">Obsoleto</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Versión</label>
                                <input type="text" name="version" id="a-version" class="form-control form-control-sm" maxlength="20" placeholder="1.0">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="a-requiere-permiso"
                                           name="requiere_permiso_modulo" checked>
                                    <label class="form-check-label small" for="a-requiere-permiso">
                                        Solo quien tenga acceso al módulo
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-0 small">Etiquetas</label>
                                <input type="text" name="etiquetas" id="a-etiquetas" class="form-control form-control-sm"
                                       placeholder="anular, factura, nota de crédito, devolución">
                                <div class="dg-ayuda">
                                    Palabras que la gente escribiría al buscar, incluidos sinónimos. Pesan igual que el título.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info py-1 px-2 mb-1 small" id="a-aviso-config" style="display:none;">
                                    <i class="bi bi-shield-lock me-1"></i>
                                    Los artículos de <code>config/</code> se guardan siempre como
                                    <strong>solo superadministrador</strong>, sin importar lo que se elija arriba.
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-0 small">Videos de ayuda relacionados</label>
                                <?php if (empty($videos)): ?>
                                    <div class="dg-ayuda">
                                        Todavía no hay videos activos.
                                        <a href="<?= $base ?>/videos-ayuda/gestion" target="_blank" rel="noopener">Subir uno</a>.
                                    </div>
                                <?php else: ?>
                                    <div class="border rounded p-2" style="max-height:120px;overflow:auto;">
                                        <?php foreach ($videos as $v): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input dg-video" type="checkbox" name="videos[]"
                                                   value="<?= (int) $v['id'] ?>" id="video-<?= (int) $v['id'] ?>">
                                            <label class="form-check-label small" for="video-<?= (int) $v['id'] ?>">
                                                <?= htmlspecialchars((string) $v['titulo']) ?>
                                                <?php if (!empty($v['categoria'])): ?>
                                                <span class="text-muted">· <?= htmlspecialchars((string) $v['categoria']) ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="dg-ayuda">Se muestran como botones al inicio del artículo.</div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-0 small">Contenido</label>
                                <div id="dg-editor"></div>
                                <div class="dg-ayuda mt-1">
                                    Use <strong>Encabezado 2</strong> y <strong>Encabezado 3</strong> para las secciones:
                                    cada una se indexa por separado y el buscador lleva al usuario directo a ella.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-2 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="a-btn-eliminar">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm" id="a-btn-guardar">
                                <i class="bi bi-check-lg me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: búsquedas sin resultado -->
    <div class="modal fade" id="modalSinResultado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="bi bi-question-circle me-1"></i>Búsquedas sin resultado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Lo que los usuarios buscaron en el manual y no encontraron. Es la lista de lo que falta documentar.
                    </p>
                    <?php if (empty($sinResultado)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                            Todas las búsquedas encontraron algo.
                        </div>
                    <?php else: ?>
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Término buscado</th>
                                    <th class="text-center" style="width:90px;">Veces</th>
                                    <th class="text-nowrap" style="width:170px;">Última vez</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sinResultado as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $s['termino']) ?></td>
                                    <td class="text-center"><?= (int) $s['veces'] ?></td>
                                    <td class="small text-nowrap"><?= $fmtFecha($s['ultima'] ?? null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Doctor de documentación -->
    <div class="modal fade" id="modalDoctor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="bi bi-clipboard2-pulse me-1"></i>Doctor del manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="doctor-cuerpo">
                    <div class="text-center py-4 text-muted">
                        <span class="spinner-border spinner-border-sm"></span> Revisando…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: resultado de la sincronización -->
    <div class="modal fade" id="modalSync" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-1"></i>Sincronización del manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="sync-cuerpo"></div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="sync-recargar">Ver el listado actualizado</button>
                </div>
            </div>
        </div>
    </div>

    <div id="dg-toast" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1090;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="<?= $base ?>/js/favoritos.js?v=<?= time() ?>"></script>
    <script>
    (function () {
        var base = '<?= $base ?>';
        var modalEl = document.getElementById('modalArticulo');
        var modal = new bootstrap.Modal(modalEl);
        var form = document.getElementById('form-articulo');
        var btnEliminar = document.getElementById('a-btn-eliminar');

        var quill = new Quill('#dg-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'code'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'code-block'],
                    ['link', 'clean']
                ]
            }
        });

        function toast(mensaje, tipo) {
            var cont = document.getElementById('dg-toast');
            var div = document.createElement('div');
            div.className = 'alert alert-' + (tipo || 'secondary') + ' shadow-sm py-2 px-3 mb-2';
            div.textContent = mensaje;
            cont.appendChild(div);
            setTimeout(function () { div.remove(); }, 3500);
        }

        function revisarAvisoConfig() {
            var slug = (document.getElementById('a-slug').value || '').trim();
            var ruta = (document.getElementById('a-ruta-modulo').value || '').trim();
            var esConfig = slug.indexOf('config/') === 0 || ruta.indexOf('config/') === 0;
            document.getElementById('a-aviso-config').style.display = esConfig ? '' : 'none';
        }

        function marcarVideos(ids) {
            var seleccion = ids || [];
            Array.prototype.forEach.call(document.querySelectorAll('.dg-video'), function (chk) {
                chk.checked = seleccion.indexOf(parseInt(chk.value, 10)) !== -1;
            });
        }

        function limpiar() {
            form.reset();
            document.getElementById('a-id').value = '';
            document.getElementById('a-requiere-permiso').checked = true;
            marcarVideos([]);
            quill.setContents([]);
            btnEliminar.classList.add('d-none');
            document.getElementById('modalArticuloTitulo').innerHTML =
                '<i class="bi bi-file-earmark-plus me-1"></i>Nuevo artículo';
            revisarAvisoConfig();
        }

        function cargar(id) {
            fetch(base + '/documentacion/obtener?id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.ok) { toast((d && d.error) || 'No se pudo cargar el artículo.', 'warning'); return; }
                    var a = d.articulo;
                    document.getElementById('a-id').value = a.id;
                    document.getElementById('a-titulo').value = a.titulo;
                    document.getElementById('a-slug').value = a.slug;
                    document.getElementById('a-resumen').value = a.resumen;
                    document.getElementById('a-categoria').value = a.categoria;
                    document.getElementById('a-ruta-modulo').value = a.ruta_modulo;
                    document.getElementById('a-tipo').value = a.tipo;
                    document.getElementById('a-visibilidad').value = a.visibilidad;
                    document.getElementById('a-estado').value = a.estado;
                    document.getElementById('a-version').value = a.version;
                    document.getElementById('a-orden').value = a.orden;
                    document.getElementById('a-etiquetas').value = a.etiquetas;
                    document.getElementById('a-requiere-permiso').checked = !!a.requiere_permiso_modulo;
                    marcarVideos(a.videos || []);
                    quill.root.innerHTML = a.contenido_html || '';

                    btnEliminar.classList.remove('d-none');
                    document.getElementById('modalArticuloTitulo').innerHTML =
                        '<i class="bi bi-pencil me-1"></i>Editar artículo';
                    revisarAvisoConfig();
                    modal.show();
                })
                .catch(function () { toast('No se pudo cargar el artículo.', 'danger'); });
        }

        document.getElementById('btn-nuevo').addEventListener('click', function () {
            limpiar();
            modal.show();
        });

        // Delegación de eventos: las filas se reemplazan en cada búsqueda/orden
        // AJAX, por lo que el listener va en el tbody (contenedor fijo).
        var tbodyDg = document.getElementById('tbodyDg');
        if (tbodyDg) {
            tbodyDg.addEventListener('click', function (e) {
                if (e.target.closest('.dg-editar')) {
                    cargar(e.target.closest('tr').getAttribute('data-id'));
                    return;
                }
                if (e.target.closest('a') || e.target.closest('button')) { return; }
                var tr = e.target.closest('.dg-row');
                if (tr) cargar(tr.getAttribute('data-id'));
            });
        }

        document.getElementById('a-slug').addEventListener('input', revisarAvisoConfig);
        document.getElementById('a-ruta-modulo').addEventListener('input', revisarAvisoConfig);

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var html = quill.root.innerHTML.trim();
            if (html === '<p><br></p>') { html = ''; }
            document.getElementById('a-contenido').value = html;

            var id = document.getElementById('a-id').value;
            var url = base + (id ? '/documentacion/update' : '/documentacion/store');
            var datos = new FormData(form);
            // Un checkbox desmarcado no viaja en el FormData: se envía explícito.
            datos.set('requiere_permiso_modulo', document.getElementById('a-requiere-permiso').checked ? '1' : '0');

            var btn = document.getElementById('a-btn-guardar');
            btn.disabled = true;

            fetch(url, { method: 'POST', body: datos, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    btn.disabled = false;
                    if (!d || !d.ok) { toast((d && d.error) || 'No se pudo guardar.', 'warning'); return; }
                    toast(d.msg || 'Guardado.', 'success');
                    setTimeout(function () { location.reload(); }, 600);
                })
                .catch(function () { btn.disabled = false; toast('No se pudo guardar.', 'danger'); });
        });

        // ── Doctor: qué falta por documentar ────────────────────────────
        function pintarDoctor(d) {
            var color = d.cobertura >= 80 ? 'success' : (d.cobertura >= 40 ? 'warning' : 'danger');
            var html = '<div class="mb-3">' +
                '<div class="d-flex justify-content-between align-items-end mb-1">' +
                '<span class="fw-semibold">Cobertura del manual</span>' +
                '<span class="small text-muted">' + d.documentadas + ' de ' + d.total_rutas + ' pantallas</span>' +
                '</div>' +
                '<div class="progress" style="height:20px;">' +
                '<div class="progress-bar bg-' + color + '" style="width:' + d.cobertura + '%;">' + d.cobertura + '%</div>' +
                '</div></div>';

            // Sin documentar — lo que de verdad importa.
            html += '<h6 class="mt-3"><i class="bi bi-exclamation-circle text-danger me-1"></i>Sin documentar (' + d.sin_documentar.length + ')</h6>';
            if (!d.sin_documentar.length) {
                html += '<div class="alert alert-success py-2 px-3 small mb-3">Todas las pantallas del sistema tienen artículo.</div>';
            } else {
                html += '<div class="table-responsive mb-3" style="max-height:320px;overflow:auto;">' +
                        '<table class="table table-sm table-hover align-middle mb-0">' +
                        '<thead><tr><th>Módulo</th><th>Pantalla</th><th>Ruta</th><th style="width:120px;"></th></tr></thead><tbody>';
                d.sin_documentar.forEach(function (m) {
                    html += '<tr><td class="small text-muted">' + esc(m.modulo) + '</td>' +
                            '<td>' + esc(m.nombre) + '</td>' +
                            '<td class="dg-slug">' + esc(m.ruta) + '</td>' +
                            '<td class="text-end"><button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 doctor-documentar" ' +
                            'data-ruta="' + esc(m.ruta) + '" data-nombre="' + esc(m.nombre) + '" data-modulo="' + esc(m.modulo) + '">' +
                            '<i class="bi bi-plus-lg"></i> Documentar</button></td></tr>';
                });
                html += '</tbody></table></div>';
            }

            // Problemas de los artículos que sí existen.
            var bloques = [
                { clave: 'huerfanos', titulo: 'Apuntan a una ruta que no existe', icono: 'bi-signpost-2', color: 'warning',
                  fila: function (h) { return '<td>' + esc(h.titulo) + '</td><td class="dg-slug">' + esc(h.slug) + '</td><td class="dg-slug">' + esc(h.ruta_modulo) + '</td>'; },
                  cabecera: '<th>Artículo</th><th>Dirección</th><th>Ruta inexistente</th>' },
                { clave: 'incompletos', titulo: 'Incompletos', icono: 'bi-pencil-square', color: 'secondary',
                  fila: function (h) { return '<td>' + esc(h.titulo) + '</td><td class="dg-slug">' + esc(h.slug) + '</td><td class="small">Falta: ' + esc(h.faltan.join(', ')) + '</td>'; },
                  cabecera: '<th>Artículo</th><th>Dirección</th><th>Qué falta</th>' },
                { clave: 'obsoletos', titulo: 'Obsoletos (su archivo .md ya no está)', icono: 'bi-archive', color: 'dark',
                  fila: function (h) { return '<td>' + esc(h.titulo) + '</td><td class="dg-slug">' + esc(h.slug) + '</td><td class="dg-slug">' + esc(h.archivo) + '</td>'; },
                  cabecera: '<th>Artículo</th><th>Dirección</th><th>Archivo</th>' }
            ];

            bloques.forEach(function (b) {
                var lista = d[b.clave] || [];
                if (!lista.length) { return; }
                html += '<h6 class="mt-3"><i class="bi ' + b.icono + ' text-' + b.color + ' me-1"></i>' + b.titulo + ' (' + lista.length + ')</h6>' +
                        '<table class="table table-sm table-hover align-middle mb-0"><thead><tr>' + b.cabecera + '</tr></thead><tbody>';
                lista.forEach(function (h) { html += '<tr>' + b.fila(h) + '</tr>'; });
                html += '</tbody></table>';
            });

            document.getElementById('doctor-cuerpo').innerHTML = html;

            // "Documentar" abre el formulario ya rellenado con la ruta detectada.
            Array.prototype.forEach.call(document.querySelectorAll('.doctor-documentar'), function (btn) {
                btn.addEventListener('click', function () {
                    var ruta = this.getAttribute('data-ruta');
                    bootstrap.Modal.getInstance(document.getElementById('modalDoctor')).hide();
                    limpiar();
                    document.getElementById('a-slug').value = ruta;
                    document.getElementById('a-ruta-modulo').value = ruta;
                    document.getElementById('a-titulo').value = this.getAttribute('data-nombre');
                    document.getElementById('a-categoria').value = this.getAttribute('data-modulo') || '';
                    revisarAvisoConfig();
                    modal.show();
                });
            });
        }

        document.getElementById('btn-doctor').addEventListener('click', function () {
            document.getElementById('doctor-cuerpo').innerHTML =
                '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Revisando…</div>';
            new bootstrap.Modal(document.getElementById('modalDoctor')).show();

            fetch(base + '/documentacion/doctor', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.ok) {
                        document.getElementById('doctor-cuerpo').innerHTML =
                            '<div class="alert alert-warning mb-0">' + esc((d && d.error) || 'No se pudo obtener el diagnóstico.') + '</div>';
                        return;
                    }
                    pintarDoctor(d.diagnostico);
                })
                .catch(function () {
                    document.getElementById('doctor-cuerpo').innerHTML =
                        '<div class="alert alert-danger mb-0">No se pudo obtener el diagnóstico.</div>';
                });
        });

        // ── Sincronización desde docs/manual/ ───────────────────────────
        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function pintarResumenSync(r) {
            var html = '';

            if (r.errores && r.errores.length) {
                html += '<div class="alert alert-warning py-2 px-3">' +
                        '<div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Avisos</div><ul class="mb-0 ps-3">';
                r.errores.forEach(function (e) { html += '<li>' + esc(e) + '</li>'; });
                html += '</ul></div>';
            }

            html += '<div class="row g-2 text-center mb-3">' +
                '<div class="col"><div class="border rounded py-2"><div class="fs-4 fw-semibold text-success">' + r.creados + '</div><div class="small text-muted">Creados</div></div></div>' +
                '<div class="col"><div class="border rounded py-2"><div class="fs-4 fw-semibold text-primary">' + r.actualizados + '</div><div class="small text-muted">Actualizados</div></div></div>' +
                '<div class="col"><div class="border rounded py-2"><div class="fs-4 fw-semibold text-secondary">' + r.sin_cambios + '</div><div class="small text-muted">Sin cambios</div></div></div>' +
                '<div class="col"><div class="border rounded py-2"><div class="fs-4 fw-semibold text-dark">' + r.obsoletos + '</div><div class="small text-muted">Obsoletos</div></div></div>' +
                '</div>';

            if (r.omitidos && r.omitidos.length) {
                html += '<div class="alert alert-secondary py-2 px-3 small">' +
                        '<div class="fw-semibold mb-1">Omitidos (se editan desde esta pantalla, el repositorio no los pisa)</div><ul class="mb-0 ps-3">';
                r.omitidos.forEach(function (o) { html += '<li>' + esc(o) + '</li>'; });
                html += '</ul></div>';
            }

            if (r.detalle && r.detalle.length) {
                html += '<table class="table table-sm table-hover align-middle mb-0">' +
                        '<thead><tr><th>Archivo</th><th>Dirección</th><th class="text-center" style="width:120px;">Acción</th></tr></thead><tbody>';
                r.detalle.forEach(function (d) {
                    var color = d.accion === 'creado' ? 'success'
                              : d.accion === 'actualizado' ? 'primary'
                              : d.accion === 'omitido' ? 'warning' : 'secondary';
                    html += '<tr><td class="dg-slug">' + esc(d.archivo) + '</td>' +
                            '<td class="dg-slug">' + esc(d.slug) + '</td>' +
                            '<td class="text-center"><span class="badge bg-' + color + ' bg-opacity-10 text-' + color +
                            ' border border-' + color + ' border-opacity-25">' + esc(d.accion.replace('_', ' ')) + '</span></td></tr>';
                });
                html += '</tbody></table>';
            }

            document.getElementById('sync-cuerpo').innerHTML = html;
        }

        document.getElementById('btn-sincronizar').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sincronizando…';

            fetch(base + '/documentacion/sincronizar', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sincronizar';
                    if (!d || !d.resumen) {
                        toast((d && d.error) || 'No se pudo sincronizar.', 'warning');
                        return;
                    }
                    pintarResumenSync(d.resumen);
                    new bootstrap.Modal(document.getElementById('modalSync')).show();
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sincronizar';
                    toast('No se pudo sincronizar.', 'danger');
                });
        });

        document.getElementById('sync-recargar').addEventListener('click', function () { location.reload(); });

        btnEliminar.addEventListener('click', function () {
            var id = document.getElementById('a-id').value;
            if (!id) { return; }
            if (!confirm('¿Eliminar este artículo del manual? Se puede recuperar desde la base de datos.')) { return; }

            var datos = new FormData();
            datos.append('id', id);
            fetch(base + '/documentacion/delete', {
                method: 'POST', body: datos, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.ok) { toast((d && d.error) || 'No se pudo eliminar.', 'warning'); return; }
                    toast(d.msg || 'Eliminado.', 'success');
                    setTimeout(function () { location.reload(); }, 600);
                })
                .catch(function () { toast('No se pudo eliminar.', 'danger'); });
        });

        // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX,
        // sin recargar la página (el input nunca pierde el foco). Mismo
        // patrón que ASIENTOTIPO_cargarListado
        // (public/js/modulos/asientos_tipo_modal.js).
        var dgTimer = null;
        window.DG_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
        window.DG_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

        window.DG_cargarListado = function () {
            var inputB = document.getElementById('input-buscar-dg');
            var b = inputB ? inputB.value.trim() : '';
            var tbodyEl = document.getElementById('tbodyDg');
            if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="11" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

            fetch(base + '/documentacion/gestion-search?b=' + encodeURIComponent(b) + '&sort=' + window.DG_currentSort + '&dir=' + window.DG_currentDir, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows;
                })
                .catch(function () {
                    if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">Error al cargar.</td></tr>';
                });
        };

        var inputBuscarDg = document.getElementById('input-buscar-dg');
        if (inputBuscarDg) {
            inputBuscarDg.addEventListener('input', function () {
                clearTimeout(dgTimer);
                dgTimer = setTimeout(function () { DG_cargarListado(); }, 400);
            });
        }

        if (window.CMG_initSort) {
            window.CMG_initSort('documentacion-gestion', function (col, dir) {
                window.DG_currentSort = col;
                window.DG_currentDir = dir;
                DG_cargarListado();
            }, { col: window.DG_currentSort, dir: window.DG_currentDir });
        }
    })();
    </script>
</body>
</html>
