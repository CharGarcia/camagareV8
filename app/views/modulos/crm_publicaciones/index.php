<?php
/**
 * Publicaciones en redes (modulos/crm_publicaciones), fase 3 del CRM: tarjeta de
 * control fija, vistas Calendario / Lista / Biblioteca y el modal de la
 * publicación. Todo se carga por AJAX desde CrmPublicacionesController; la
 * lógica de pantalla vive en public/js/modulos/crm_publicaciones.js.
 * Diseño: docs/diseno/crm-redes-fase3.md §4.
 *
 * @var array  $perm
 * @var string $rutaModulo
 * @var array  $vistaConfig
 * @var string $ordenJson
 * @var int    $perPage
 * @var bool   $disponible
 * @var array  $columnasLista
 * @var array  $estados
 * @var int    $idUsuario
 */
use App\Helpers\PreferenciasHelper;

$base    = rtrim(BASE_URL, '/');
$urlBase = $base . '/' . ltrim($rutaModulo, '/');
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    #form-filtros-pub .form-select,
    #form-filtros-pub .form-control,
    #form-filtros-pub .input-group-text,
    #form-filtros-pub .btn { height:28px; font-size:.75rem; }

    .pub-scroll { overflow-x:auto; }
    .pub-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; }
    .pub-scroll thead th.sortable-header { cursor:pointer; user-select:none; }
    .pub-scroll td { white-space:nowrap; }
    .pub-row { cursor:pointer; }
    @media (max-width: 767.98px) {
        #modulo-publicaciones .pub-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }

    /* Calendario mensual */
    .pub-cal { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); border-left:1px solid #e3e6ea; border-top:1px solid #e3e6ea; }
    .pub-cal-dia { min-height:108px; border-right:1px solid #e3e6ea; border-bottom:1px solid #e3e6ea; padding:4px; background:#fff; }
    .pub-cal-dia.fuera { background:#f8f9fa; color:#adb5bd; }
    .pub-cal-dia.hoy .pub-cal-num { background:#0d6efd; color:#fff; border-radius:999px; padding:0 6px; }
    .pub-cal-num { font-size:.72rem; font-weight:600; }
    .pub-cal-cab { font-size:.7rem; font-weight:700; text-transform:uppercase; color:#6c757d; padding:4px; border-right:1px solid #e3e6ea; border-bottom:1px solid #e3e6ea; background:#f8f9fa; }
    .pub-cal-item { display:block; font-size:.7rem; border-radius:4px; padding:1px 4px; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
                    cursor:pointer; border-left:3px solid var(--pub-c, #6c757d); background:#f4f6f8; color:#212529; }
    .pub-cal-item:hover { background:#e9eef4; }
    @media (max-width: 767.98px) { .pub-cal-dia { min-height:64px; } .pub-cal-item span.t { display:none; } }

    /* Biblioteca */
    .pub-medios { display:grid; grid-template-columns:repeat(auto-fill, minmax(130px, 1fr)); gap:10px; }
    .pub-medio { position:relative; border:1px solid #e3e6ea; border-radius:.5rem; overflow:hidden; background:#fff; cursor:pointer; }
    .pub-medio img, .pub-medio video { width:100%; aspect-ratio:1/1; object-fit:cover; display:block; background:#f1f3f5; }
    .pub-medio .pub-medio-pie { font-size:.68rem; padding:3px 6px; color:#6c757d; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .pub-medio .pub-medio-tipo { position:absolute; top:4px; left:4px; }
    .pub-medio.elegido { outline:3px solid #0d6efd; }
    .pub-medio .pub-medio-orden { position:absolute; top:4px; right:4px; }

    /* Medios dentro del modal */
    .pub-sel { display:flex; flex-wrap:wrap; gap:8px; }
    .pub-sel-item { width:96px; border:1px solid #dee2e6; border-radius:.4rem; overflow:hidden; background:#fff; font-size:.68rem; }
    .pub-sel-item img, .pub-sel-item video { width:96px; height:96px; object-fit:cover; display:block; background:#f1f3f5; }
    .pub-sel-item .btn { padding:0 4px; font-size:.7rem; }

    /* Vista previa */
    .pub-prev-ig { width:320px; max-width:100%; border:1px solid #dbdbdb; border-radius:8px; background:#fff; overflow:hidden; }
    .pub-prev-ig .marco { width:100%; aspect-ratio:1/1; background:#f1f3f5; overflow:hidden; }
    .pub-prev-ig .marco.reel { aspect-ratio:9/16; max-height:420px; }
    .pub-prev-ig .marco img, .pub-prev-ig .marco video { width:100%; height:100%; object-fit:cover; }
    .pub-prev-li { width:420px; max-width:100%; border:1px solid #dbdbdb; border-radius:8px; background:#fff; overflow:hidden; }
    .pub-prev-li .rejilla { display:grid; gap:2px; }
    .pub-prev-li .rejilla img { width:100%; height:100%; object-fit:cover; aspect-ratio:1/1; }
    .pub-prev-texto { white-space:pre-wrap; font-size:.8rem; }
    textarea.pub-prev-texto { resize:vertical; }
</style>

<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-publicaciones">

<?php if (!$disponible): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mt-2">
        <i class="bi bi-database-exclamation fs-4"></i>
        <div>Las publicaciones todavía no están instaladas en la base de datos. Faltan aplicar
            <code>database/2026-11-08_crm_fase1.sql</code> y <code>database/2026-11-08_crm_fase3_publicaciones.sql</code>.</div>
    </div>
<?php else: ?>

    <!-- ── Tarjeta de control fija ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-primary"></i>Publicaciones</h5>
            <?php if (!empty($perm['actualizar'])): ?>
                <button type="button" class="btn btn-link btn-sm ms-auto text-muted" onclick="PUB.abrirConfig()" title="Aprobación previa y tope diario"><i class="bi bi-gear"></i></button>
            <?php endif; ?>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-pub" onsubmit="event.preventDefault(); PUB.recargar();" class="d-flex flex-wrap align-items-start gap-2">
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                    <select id="pub-f-estado" class="form-select form-select-sm shadow-none border" style="width:150px;" onchange="PUB.recargar()">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $k => $t): ?><option value="<?= $h($k) ?>"><?= $h($t) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div style="width:440px;max-width:100%;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="search" id="pub-f-buscar" class="form-control border-start-0 px-1 shadow-none" autocomplete="off"
                                   placeholder="Nombre o texto… o programada:2026-12, estado:publicada"
                                   title="Texto libre o filtros clave:valor — titulo, responsable, estado, programada, publicada, creada">
                        </div>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="PUB.limpiarFiltros()"><i class="bi bi-eraser me-1"></i>Limpiar</button>
                            <button type="submit" class="btn btn-primary btn-sm px-2 shadow-sm"><i class="bi bi-search me-1"></i>Mostrar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-calendar-event bg-primary bg-opacity-10 text-primary"></i>
                    <div><div class="cmg-control-card__stat-value" id="pub-k-programadas">0</div><div class="cmg-control-card__stat-label">Programadas</div></div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-pencil-square bg-secondary bg-opacity-10 text-secondary"></i>
                    <div><div class="cmg-control-card__stat-value" id="pub-k-borradores">0</div><div class="cmg-control-card__stat-label">Por preparar <span id="pub-k-revision" class="text-warning-emphasis"></span></div></div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-check2-circle bg-success bg-opacity-10 text-success"></i>
                    <div><div class="cmg-control-card__stat-value text-success" id="pub-k-publicadas">0</div><div class="cmg-control-card__stat-label">Publicadas este mes</div></div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-exclamation-triangle bg-danger bg-opacity-10 text-danger"></i>
                    <div><div class="cmg-control-card__stat-value text-danger" id="pub-k-error">0</div><div class="cmg-control-card__stat-label">Con error</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Vistas ── -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <ul class="nav nav-pills nav-sm gap-1">
            <li class="nav-item"><button class="nav-link py-1 px-3 active" data-pub-vista="calendario" type="button"><i class="bi bi-calendar3 me-1"></i>Calendario</button></li>
            <li class="nav-item"><button class="nav-link py-1 px-3" data-pub-vista="lista" type="button"><i class="bi bi-list-ul me-1"></i>Lista</button></li>
            <li class="nav-item"><button class="nav-link py-1 px-3" data-pub-vista="biblioteca" type="button"><i class="bi bi-images me-1"></i>Biblioteca</button></li>
        </ul>
        <?php if (!empty($perm['crear'])): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="PUB.nueva()"><i class="bi bi-plus-lg me-1"></i>Nueva publicación</button>
        <?php endif; ?>
    </div>

    <!-- Calendario -->
    <section data-pub-panel="calendario">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 d-flex align-items-center gap-2 flex-wrap">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="PUB.mes(-1)" title="Mes anterior"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="PUB.mes(0)">Hoy</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="PUB.mes(1)" title="Mes siguiente"><i class="bi bi-chevron-right"></i></button>
                </div>
                <h5 class="mb-0 fw-bold fs-6 text-capitalize" id="pub-cal-titulo"></h5>
                <div class="ms-auto small text-muted d-flex flex-wrap gap-2" id="pub-cal-leyenda"></div>
            </div>
            <div class="card-body p-0"><div id="pub-cal"></div></div>
        </div>
    </section>

    <!-- Lista -->
    <section data-pub-panel="lista" class="d-none">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold fs-6">Publicaciones</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="PUB.exportar('Pdf')" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></button>
                        <button type="button" class="btn btn-outline-success" onclick="PUB.exportar('Excel')" title="Exportar a Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></button>
                    </div>
                    <?= PreferenciasHelper::renderDropdownColumnas($columnasLista, $vistaConfig ?? [], $rutaModulo) ?>
                    <small class="text-muted fw-medium" id="pub-lista-info"></small>
                    <div class="btn-group btn-group-sm" id="pub-lista-paginacion"></div>
                    <?= PreferenciasHelper::renderSelectorPorPagina($perPage, 'pub-por-pagina', 'PUB.cambiarPorPagina(this.value)') ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="pub-scroll w-100">
                    <table class="table table-hover table-sm mb-0 align-middle" style="table-layout:auto;min-width:100%;">
                        <thead>
                            <tr>
                                <?php foreach ($columnasLista as $col => $etq): ?>
                                    <?php if ($col === 'destinos'): ?>
                                        <th data-col="destinos"><?= $h($etq) ?></th>
                                    <?php else: ?>
                                        <th class="sortable-header <?= $col === 'titulo' ? 'ps-3' : '' ?> <?= $col === 'estado' ? 'text-center' : '' ?>" role="button"
                                            data-sort="<?= $h($col) ?>" data-col="<?= $h($col) ?>"><?= $h($etq) ?> <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="pub-lista-tbody">
                            <tr><td colspan="<?= count($columnasLista) ?>" class="text-center text-muted py-4">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Biblioteca -->
    <section data-pub-panel="biblioteca" class="d-none">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 d-flex align-items-center gap-2 flex-wrap">
                <h5 class="mb-0 fw-bold fs-6">Biblioteca</h5>
                <select class="form-select form-select-sm" id="pub-b-tipo" style="width:130px;" onchange="PUB.cargarBiblioteca(1)">
                    <option value="">Todo</option><option value="imagen">Imágenes</option><option value="video">Videos</option>
                </select>
                <small class="text-muted" id="pub-b-info"></small>
                <div class="btn-group btn-group-sm" id="pub-b-paginacion"></div>
                <?php if (!empty($perm['crear'])): ?>
                    <input type="file" id="pub-b-archivos" class="d-none" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime" onchange="PUB.subir(this)">
                    <button type="button" class="btn btn-primary btn-sm ms-auto" onclick="document.getElementById('pub-b-archivos').click()"><i class="bi bi-upload me-1"></i>Subir</button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2">Imágenes JPG, PNG, WEBP o GIF (se guardan como JPEG, máx. 1440 px) y videos MP4/MOV H.264 de 3 a 90 s y hasta 100 MB.
                    Lo que no se usa en 90 días se borra solo.</div>
                <div id="pub-b-subiendo" class="small mb-2"></div>
                <div class="pub-medios" id="pub-b-grilla"></div>
            </div>
        </div>
    </section>

<?php endif; ?>
</div>

<?php if ($disponible): ?>
    <?php include __DIR__ . '/modal_publicacion.php'; ?>
    <script>
        window.PUB_CONFIG = {
            urlBase:    <?= json_encode($urlBase) ?>,
            rutaModulo: <?= json_encode($rutaModulo) ?>,
            sorts:      <?= $ordenJson ?: '[]' ?>,
            perPage:    <?= (int) $perPage ?>,
            idUsuario:  <?= (int) $idUsuario ?>,
            estados:    <?= json_encode($estados, JSON_UNESCAPED_UNICODE) ?>,
            perm: {
                crear:      <?= json_encode(!empty($perm['crear'])) ?>,
                actualizar: <?= json_encode(!empty($perm['actualizar'])) ?>,
                eliminar:   <?= json_encode(!empty($perm['eliminar'])) ?>,
            },
        };
    </script>
    <script src="<?= $base ?>/js/modulos/crm_publicaciones.js?v=<?= asset_ver('/js/modulos/crm_publicaciones.js') ?>"></script>
<?php endif; ?>
