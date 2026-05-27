<?php
/** @var array  $perm */
/** @var array  $tiposRows */
/** @var int    $tiposTotal */
/** @var array  $recursosRows */
/** @var int    $recursosTotal */
/** @var array  $horarios */
/** @var array  $recursosActivos */
/** @var array|null $portalConfig */
/** @var string $tabActiva */
/** @var string $rutaModulo */
/** @var array  $vistaConfig */
/** @var array  $vistaConfigTipos */
/** @var array  $vistaConfigRecursos */
/** @var string $tiposSortCol */
/** @var string $tiposSortDir */
/** @var string $recSortCol */
/** @var string $recSortDir */

$base        = BASE_URL;
$urlBase     = $base . '/' . $rutaModulo;
$vistaConfig         = $vistaConfig         ?? [];
$vistaConfigTipos    = $vistaConfigTipos    ?? [];
$vistaConfigRecursos = $vistaConfigRecursos ?? [];
$tiposSortCol = $tiposSortCol ?? 'nombre';
$tiposSortDir = $tiposSortDir ?? 'ASC';
$recSortCol   = $recSortCol   ?? 'nombre';
$recSortDir   = $recSortDir   ?? 'ASC';

$diasNombre = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$tiposTotal    = $tiposTotal    ?? 0;
$recursosTotal = $recursosTotal ?? 0;
$tabActiva     = $tabActiva     ?? 'tipos';

// ─── Columnas visibles por tab ────────────────────────────────────────────────
$colsTipos = [
    'nombre'    => 'Nombre',
    'duracion'  => 'Duración',
    'precio'    => 'Precio',
    'tipo_pago' => 'Tipo pago',
    'estado'    => 'Estado',
];
$colsRecursos = [
    'nombre'      => 'Nombre',
    'tipo_rec'    => 'Tipo',
    'descripcion' => 'Descripción',
    'estado_rec'  => 'Estado',
];
?>

<!-- FiltrosBusqueda -->
<link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
<script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfigTipos,    'estiloVistaTipos') ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfigRecursos, 'estiloVistaRecursos') ?>

<style>
    .table-container { max-height: calc(100vh - 310px); overflow-y: auto; }
    .table-container thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .clickable-row { cursor: pointer; }
    .clickable-row:hover { background-color: rgba(0,0,0,.03); }
    .color-dot { width: 16px; height: 16px; border-radius: 50%; display: inline-block; border: 1px solid rgba(0,0,0,.15); }
    .horario-grid .dia-col { min-width: 110px; font-weight: 600; font-size: .82rem; }
    .tab-card { background: #fff; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 .5rem .5rem; padding: 0; }
    .cfg-sort-header { cursor: pointer; user-select: none; white-space: nowrap; }
    .cfg-sort-header:hover { background-color: #e9ecef; }
</style>

<!-- Encabezado -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold text-dark">
        <i class="bi bi-gear-fill text-primary me-2"></i> <?= htmlspecialchars($titulo) ?>
    </h5>
</div>

<!-- Pestañas -->
<ul class="nav nav-tabs" id="tabsCitasCfg" role="tablist">
    <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'tipos' ? 'active' : '' ?> d-flex align-items-center gap-2"
                data-bs-toggle="tab" data-bs-target="#tab-tipos" type="button">
            <i class="bi bi-tags"></i> Tipos de Cita
            <span class="badge bg-secondary bg-opacity-50 ms-1 rounded-pill"><?= $tiposTotal ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'recursos' ? 'active' : '' ?> d-flex align-items-center gap-2"
                data-bs-toggle="tab" data-bs-target="#tab-recursos" type="button">
            <i class="bi bi-person-gear"></i> Recursos
            <span class="badge bg-secondary bg-opacity-50 ms-1 rounded-pill"><?= $recursosTotal ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'horarios' ? 'active' : '' ?> d-flex align-items-center gap-2"
                data-bs-toggle="tab" data-bs-target="#tab-horarios" type="button">
            <i class="bi bi-clock"></i> Horarios
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'portal' ? 'active' : '' ?> d-flex align-items-center gap-2"
                data-bs-toggle="tab" data-bs-target="#tab-portal" type="button">
            <i class="bi bi-globe"></i> Portal de Reservas
            <?php if (!empty($portalConfig['activo'])): ?>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Activo</span>
            <?php endif; ?>
        </button>
    </li>
</ul>

<div class="tab-content tab-card">

    <!-- ═══ TAB TIPOS DE CITA ═══════════════════════════════════════════════ -->
    <div class="tab-pane fade <?= $tabActiva === 'tipos' ? 'show active' : '' ?>" id="tab-tipos" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom flex-wrap gap-2">
            <!-- Buscador + columnas -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div id="fbBuscadorTipos" style="width:420px;"></div>
                <input type="hidden" id="buscarTiposHidden" value="">
                <div class="btn-group btn-group-sm">
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($colsTipos, $vistaConfigTipos, $rutaModulo . '-tipos') ?>
                </div>
            </div>
            <?php if ($perm['crear']): ?>
                <button class="btn btn-primary btn-sm px-3" onclick="abrirModalTipo()">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo tipo
                </button>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <table class="table table-hover table-sm mb-0" id="tablaTipos">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2" style="width:24px;"></th>
                        <th class="py-2 cfg-sort-header" data-col="nombre" data-sort-key="nombre"
                            onclick="ordenarTabla('tbodyTipos', 1, 'texto', 'nombre', 'citas-configuracion-tipos')">
                            Nombre <i class="bi bi-arrow-down-up small text-muted ms-1 sort-icon-tipos"></i>
                        </th>
                        <th class="py-2 text-center cfg-sort-header" style="width:110px;" data-col="duracion" data-sort-key="duracion"
                            onclick="ordenarTabla('tbodyTipos', 2, 'numero', 'duracion', 'citas-configuracion-tipos')">
                            Duración <i class="bi bi-arrow-down-up small text-muted ms-1 sort-icon-tipos"></i>
                        </th>
                        <th class="py-2 text-end cfg-sort-header" style="width:100px;" data-col="precio" data-sort-key="precio"
                            onclick="ordenarTabla('tbodyTipos', 3, 'numero', 'precio', 'citas-configuracion-tipos')">
                            Precio <i class="bi bi-arrow-down-up small text-muted ms-1 sort-icon-tipos"></i>
                        </th>
                        <th class="py-2 text-center cfg-sort-header" style="width:130px;" data-col="tipo_pago" data-sort-key="tipo_pago"
                            onclick="ordenarTabla('tbodyTipos', 4, 'texto', 'tipo_pago', 'citas-configuracion-tipos')">
                            Tipo pago <i class="bi bi-arrow-down-up small text-muted ms-1 sort-icon-tipos"></i>
                        </th>
                        <th class="py-2 text-center pe-3 cfg-sort-header" style="width:90px;" data-col="estado" data-sort-key="estado"
                            onclick="ordenarTabla('tbodyTipos', 5, 'texto', 'estado', 'citas-configuracion-tipos')">
                            Estado <i class="bi bi-arrow-down-up small text-muted ms-1 sort-icon-tipos"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyTipos">
                    <?php if (empty($tiposRows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-tags fs-2 d-block mb-2"></i>
                            No hay tipos de cita configurados.
                            <?php if ($perm['crear']): ?>
                                <a href="#" onclick="abrirModalTipo()" class="d-block mt-1 small">Crear el primero</a>
                            <?php endif; ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($tiposRows as $t): ?>
                            <?php
                            $badge = match($t['tipo_pago'] ?? 'sin_pago') {
                                'total'    => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Pago total</span>',
                                'anticipo' => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Anticipo ' . ($t['anticipo_porcentaje'] ?? 0) . '%</span>',
                                default    => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Sin pago</span>',
                            };
                            $activo = (int)($t['status'] ?? 1) === 1;
                            $data   = htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="clickable-row" data-row='<?= $data ?>' onclick='abrirModalTipo(<?= $data ?>)'>
                                <td class="ps-3"><span class="color-dot" style="background:<?= htmlspecialchars($t['color'] ?? '#0d6efd') ?>"></span></td>
                                <td class="fw-medium" data-col="nombre"><?= htmlspecialchars($t['nombre']) ?></td>
                                <td class="text-center" data-col="duracion"><?= (int)($t['duracion_minutos'] ?? 30) ?> min</td>
                                <td class="text-end font-monospace" data-col="precio">$<?= number_format((float)($t['precio'] ?? 0), 2) ?></td>
                                <td class="text-center" data-col="tipo_pago"><?= $badge ?></td>
                                <td class="text-center pe-3" data-col="estado">
                                    <?php if ($activo): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══ TAB RECURSOS ════════════════════════════════════════════════════ -->
    <div class="tab-pane fade <?= $tabActiva === 'recursos' ? 'show active' : '' ?>" id="tab-recursos" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom flex-wrap gap-2">
            <!-- Buscador + columnas -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div id="fbBuscadorRecursos" style="width:420px;"></div>
                <input type="hidden" id="buscarRecursosHidden" value="">
                <div class="btn-group btn-group-sm">
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($colsRecursos, $vistaConfigRecursos, $rutaModulo . '-recursos') ?>
                </div>
            </div>
            <?php if ($perm['crear']): ?>
                <button class="btn btn-primary btn-sm px-3" onclick="abrirModalRecurso()">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo recurso
                </button>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <table class="table table-hover table-sm mb-0" id="tablaRecursos">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2 cfg-sort-header" data-col="nombre" data-sort-key="nombre"
                            onclick="ordenarTabla('tbodyRecursos', 0, 'texto', 'nombre', 'citas-configuracion-recursos')">
                            Nombre <i class="bi bi-arrow-down-up small text-muted ms-1 sort-icon-recursos"></i>
                        </th>
                        <th class="py-2 cfg-sort-header" style="width:120px;" data-col="tipo_rec" data-sort-key="tipo"
                            onclick="ordenarTabla('tbodyRecursos', 1, 'texto', 'tipo', 'citas-configuracion-recursos')">
                            Tipo <i class="bi bi-arrow-down-up small text-muted ms-1 sort-icon-recursos"></i>
                        </th>
                        <th class="py-2" data-col="descripcion">Descripción</th>
                        <th class="py-2 text-center pe-3 cfg-sort-header" style="width:90px;" data-col="estado_rec" data-sort-key="estado"
                            onclick="ordenarTabla('tbodyRecursos', 3, 'texto', 'estado', 'citas-configuracion-recursos')">
                            Estado <i class="bi bi-arrow-down-up small text-muted ms-1 sort-icon-recursos"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyRecursos">
                    <?php if (empty($recursosRows)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-person-gear fs-2 d-block mb-2"></i>
                            No hay recursos configurados.
                            <?php if ($perm['crear']): ?>
                                <a href="#" onclick="abrirModalRecurso()" class="d-block mt-1 small">Crear el primero</a>
                            <?php endif; ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($recursosRows as $r):
                            $iconoTipo = match($r['tipo'] ?? 'persona') {
                                'sala'   => 'bi-door-open',
                                'equipo' => 'bi-tools',
                                default  => 'bi-person',
                            };
                            $activo = (int)($r['status'] ?? 1) === 1;
                            $data   = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="clickable-row" data-row='<?= $data ?>' onclick='abrirModalRecurso(<?= $data ?>)'>
                                <td class="ps-3 fw-medium" data-col="nombre"><?= htmlspecialchars($r['nombre']) ?></td>
                                <td data-col="tipo_rec"><span class="badge bg-light text-dark border small"><i class="bi <?= $iconoTipo ?> me-1"></i><?= ucfirst($r['tipo']) ?></span></td>
                                <td data-col="descripcion" class="text-muted small text-truncate" style="max-width:250px;"><?= htmlspecialchars($r['descripcion'] ?? '—') ?></td>
                                <td class="text-center pe-3" data-col="estado_rec">
                                    <?php if ($activo): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══ TAB HORARIOS ════════════════════════════════════════════════════ -->
    <div class="tab-pane fade <?= $tabActiva === 'horarios' ? 'show active' : '' ?>" id="tab-horarios" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom flex-wrap gap-2">
            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> Los horarios definen cuándo se pueden agendar citas. <strong>General</strong> aplica a toda la empresa.</span>
            <?php if ($perm['crear']): ?>
                <button class="btn btn-primary btn-sm px-3" onclick="abrirModalHorario()">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo bloque
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($horarios)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clock fs-2 d-block mb-2"></i>
                No hay horarios configurados.
                <?php if ($perm['crear']): ?>
                    <a href="#" onclick="abrirModalHorario()" class="d-block mt-1 small">Agregar primer bloque</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php
            $grupos = [];
            foreach ($horarios as $h) {
                $key = $h['id_recurso'] ?? 'general';
                $grupos[$key][] = $h;
            }
            ?>
            <div class="p-3 horario-grid">
                <?php foreach ($grupos as $key => $bloques): ?>
                    <?php $primerBloque = $bloques[0]; ?>
                    <div class="mb-3 p-2 border rounded-3 bg-white shadow-sm">
                        <div class="fw-bold mb-2 small text-primary">
                            <?php if ($key === 'general'): ?>
                                <i class="bi bi-building me-1"></i> General (toda la empresa)
                            <?php else: ?>
                                <?php $iconoTipo = match($primerBloque['tipo'] ?? 'persona') { 'sala' => 'bi-door-open', 'equipo' => 'bi-tools', default => 'bi-person' }; ?>
                                <i class="bi <?= $iconoTipo ?> me-1"></i>
                                <?= htmlspecialchars($primerBloque['nombre_recurso'] ?? 'Recurso') ?>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($bloques as $b):
                                $data = htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8');
                            ?>
                                <div class="badge bg-light text-dark border px-2 py-2 clickable-row" style="cursor:pointer;font-size:.8rem;" onclick='abrirModalHorario(<?= $data ?>)'>
                                    <strong><?= $diasNombre[$b['dia_semana']] ?? '?' ?></strong>
                                    <?= substr($b['hora_inicio'], 0, 5) ?> – <?= substr($b['hora_fin'], 0, 5) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ═══ TAB PORTAL DE RESERVAS ══════════════════════════════════════════ -->
    <div class="tab-pane fade <?= $tabActiva === 'portal' ? 'show active' : '' ?>" id="tab-portal" role="tabpanel">
        <div class="p-4">
            <?php
            $pc   = $portalConfig ?? [];
            $slug = $pc['slug'] ?? '';
            $linkPublico = $base . '/reservas/' . $slug;
            ?>

            <?php if (!empty($slug)): ?>
                <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
                    <i class="bi bi-link-45deg fs-4 flex-shrink-0"></i>
                    <div>
                        <div class="fw-bold small mb-1">Link público de reservas</div>
                        <a href="<?= htmlspecialchars($linkPublico) ?>" target="_blank" class="small text-break"><?= htmlspecialchars($linkPublico) ?></a>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto flex-shrink-0" onclick="copiarLink('<?= htmlspecialchars($linkPublico) ?>')">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            <?php endif; ?>

            <form id="frmPortal">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">Slug (URL amigable) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text text-muted small"><?= $base ?>/reservas/</span>
                            <input type="text" name="slug" id="port-slug" class="form-control form-control-sm font-monospace"
                                   value="<?= htmlspecialchars($slug) ?>" placeholder="mi-empresa" required maxlength="100" autocomplete="off">
                        </div>
                        <div class="form-text" style="font-size:.7rem;">Solo letras minúsculas, números y guiones.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">Título del portal</label>
                        <input type="text" name="titulo" id="port-titulo" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($pc['titulo'] ?? '') ?>" placeholder="Reserva tu cita" maxlength="200">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Color principal</label>
                        <input type="color" name="color_primario" id="port-color"
                               class="form-control form-control-color form-control-sm border w-100"
                               value="<?= htmlspecialchars($pc['color_primario'] ?? '#0d6efd') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Mensaje de bienvenida</label>
                        <textarea name="mensaje_bienvenida" id="port-msg" class="form-control form-control-sm" rows="3" maxlength="1000"
                                  placeholder="Mensaje que verán los clientes al ingresar al portal..."><?= htmlspecialchars($pc['mensaje_bienvenida'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Máx. días de anticipación</label>
                        <input type="number" name="max_dias_anticipacion" id="port-maxdias" class="form-control form-control-sm"
                               value="<?= (int)($pc['max_dias_anticipacion'] ?? 30) ?>" min="1" max="365">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Mín. horas de anticipación</label>
                        <input type="number" name="min_horas_anticipacion" id="port-minhoras" class="form-control form-control-sm"
                               value="<?= (int)($pc['min_horas_anticipacion'] ?? 2) ?>" min="0" max="168">
                    </div>
                    <div class="col-md-6 d-flex flex-column gap-2 justify-content-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" id="port-activo" value="1" <?= !empty($pc['activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="port-activo">Portal activo (visible al público)</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="requiere_confirmacion" id="port-confirmacion" value="1" <?= !empty($pc['requiere_confirmacion']) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="port-confirmacion">Requiere confirmación manual</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permite_pagos_online" id="port-pagos" value="1" <?= !empty($pc['permite_pagos_online']) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="port-pagos">Habilitar pagos online</label>
                        </div>
                    </div>
                    <div class="col-12 mt-2 pt-2 border-top d-flex justify-content-end gap-2">
                        <?php if ($perm['actualizar'] || $perm['crear']): ?>
                            <button type="submit" class="btn btn-primary btn-sm px-4">
                                <i class="bi bi-check-circle me-1"></i> Guardar configuración del portal
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div><!-- /tab-content -->

<script>
const URL_CITAS_CFG = '<?= $urlBase ?>';

// ─── Estado de ordenamiento (inicializado desde preferencias guardadas) ────────
const _cfgSort = {
    tbodyTipos:    { col: '<?= htmlspecialchars($tiposSortCol) ?>', dir: '<?= strtolower($tiposSortDir) ?>' },
    tbodyRecursos: { col: '<?= htmlspecialchars($recSortCol)   ?>', dir: '<?= strtolower($recSortDir)   ?>' },
};

// ─── Parser simplificado de FiltrosBusqueda (para filtrado DOM local) ─────────
function parseFB(raw) {
    const filtros = {};
    let texto = (raw || '').trim();
    const regex = /(-?)([a-záéíóúñ_]+):(?:"([^"]*)"|([^\s"]+))/gi;
    let m;
    while ((m = regex.exec(raw || '')) !== null) {
        filtros[m[2].toLowerCase()] = { neg: m[1] === '-', val: m[3] !== undefined ? m[3] : m[4] };
        texto = texto.replace(m[0], '').trim();
    }
    return { texto: texto.replace(/\s+/g, ' ').trim(), filtros };
}

function rowData(tr) {
    try { return JSON.parse(tr.dataset.row || '{}'); } catch { return {}; }
}

// ─── Filtrado DOM: tipos ──────────────────────────────────────────────────────
function filtrarTiposDOM() {
    const raw = document.getElementById('buscarTiposHidden')?.value || '';
    const { texto, filtros } = parseFB(raw);
    document.querySelectorAll('#tbodyTipos tr[data-row]').forEach(tr => {
        const d = rowData(tr);
        let ok = true;
        if (texto) {
            const txt = Array.from(tr.cells).map(td => td.textContent).join(' ').toLowerCase();
            ok = ok && txt.includes(texto.toLowerCase());
        }
        if (filtros.nombre) {
            const match = (d.nombre || '').toLowerCase().includes(filtros.nombre.val.toLowerCase());
            ok = ok && (filtros.nombre.neg ? !match : match);
        }
        if (filtros.tipo_pago) {
            const match = (d.tipo_pago ?? 'sin_pago') === filtros.tipo_pago.val;
            ok = ok && (filtros.tipo_pago.neg ? !match : match);
        }
        if (filtros.estado) {
            const activo = (parseInt(d.status ?? 1) === 1) ? 'activo' : 'inactivo';
            const match  = activo === filtros.estado.val;
            ok = ok && (filtros.estado.neg ? !match : match);
        }
        tr.style.display = ok ? '' : 'none';
    });
}

// ─── Filtrado DOM: recursos ────────────────────────────────────────────────────
function filtrarRecursosDOM() {
    const raw = document.getElementById('buscarRecursosHidden')?.value || '';
    const { texto, filtros } = parseFB(raw);
    document.querySelectorAll('#tbodyRecursos tr[data-row]').forEach(tr => {
        const d = rowData(tr);
        let ok = true;
        if (texto) {
            const txt = Array.from(tr.cells).map(td => td.textContent).join(' ').toLowerCase();
            ok = ok && txt.includes(texto.toLowerCase());
        }
        if (filtros.nombre) {
            const match = (d.nombre || '').toLowerCase().includes(filtros.nombre.val.toLowerCase());
            ok = ok && (filtros.nombre.neg ? !match : match);
        }
        if (filtros.tipo) {
            const match = (d.tipo ?? '') === filtros.tipo.val;
            ok = ok && (filtros.tipo.neg ? !match : match);
        }
        if (filtros.estado) {
            const activo = (parseInt(d.status ?? 1) === 1) ? 'activo' : 'inactivo';
            const match  = activo === filtros.estado.val;
            ok = ok && (filtros.estado.neg ? !match : match);
        }
        tr.style.display = ok ? '' : 'none';
    });
}

// ─── Ordenar tabla + persistencia ─────────────────────────────────────────────
function ordenarTabla(tbodyId, colIdx, tipo, sortKey, moduloCfg) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    const st = _cfgSort[tbodyId] || { col: null, dir: 'asc' };
    if (sortKey && st.col === sortKey) {
        st.dir = st.dir === 'asc' ? 'desc' : 'asc';
    } else {
        st.col = sortKey;
        st.dir = 'asc';
    }
    _cfgSort[tbodyId] = st;

    const asc  = st.dir === 'asc';
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(tr => tr.cells.length > 1);
    rows.sort((a, b) => {
        const va = a.cells[colIdx]?.textContent.trim() ?? '';
        const vb = b.cells[colIdx]?.textContent.trim() ?? '';
        if (tipo === 'numero') {
            const na = parseFloat(va.replace(/[^0-9.-]/g, '')) || 0;
            const nb = parseFloat(vb.replace(/[^0-9.-]/g, '')) || 0;
            return asc ? na - nb : nb - na;
        }
        return asc ? va.localeCompare(vb, 'es') : vb.localeCompare(va, 'es');
    });
    rows.forEach(tr => tbody.appendChild(tr));

    // Actualizar íconos del encabezado
    const iconClass = tbodyId === 'tbodyTipos' ? 'sort-icon-tipos' : 'sort-icon-recursos';
    tbody.closest('table').querySelectorAll(`th[data-sort-key]`).forEach(th => {
        const icon = th.querySelector('.' + iconClass);
        if (!icon) return;
        if (th.dataset.sortKey === sortKey) {
            icon.className = asc
                ? `bi bi-sort-alpha-down text-primary ms-1 ${iconClass}`
                : `bi bi-sort-alpha-up text-primary ms-1 ${iconClass}`;
        } else {
            icon.className = `bi bi-arrow-down-up small text-muted ms-1 ${iconClass}`;
        }
    });

    // Persistir ordenamiento en preferencias del usuario
    if (sortKey && moduloCfg && typeof window.guardarOrdenacionVista === 'function') {
        window.guardarOrdenacionVista(moduloCfg, sortKey, st.dir.toUpperCase());
    }
}

// ─── Auto-aplicar ordenamiento guardado al cargar ─────────────────────────────
function autoSortInit(tbodyId, sortCol, sortDir, colMap) {
    if (!sortCol) return;
    const info = colMap[sortCol];
    if (!info) return;
    // Forzar dirección opuesta para que `ordenarTabla` la invierta correctamente
    if (_cfgSort[tbodyId]) {
        _cfgSort[tbodyId] = { col: sortCol, dir: sortDir === 'asc' ? 'desc' : 'asc' };
    }
    ordenarTabla(tbodyId, info[0], info[1], sortCol, null); // null = no persistir
}

// ─── INIT ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // ── FiltrosBusqueda: Tipos ───────────────────────────────────────────────
    if (window.FiltrosBusqueda && document.getElementById('fbBuscadorTipos')) {
        new FiltrosBusqueda({
            containerId:   'fbBuscadorTipos',
            hiddenInputId: 'buscarTiposHidden',
            placeholder:   'Buscar tipos de cita...',
            fields: [
                { key: 'nombre',    label: 'Nombre',     icon: 'bi-tags',         type: 'text' },
                { key: 'tipo_pago', label: 'Tipo pago',  icon: 'bi-credit-card',  type: 'select', options: [
                    { v: 'sin_pago',  l: 'Sin pago'    },
                    { v: 'total',     l: 'Pago total'  },
                    { v: 'anticipo',  l: 'Anticipo'    },
                ]},
                { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                    { v: 'activo',   l: 'Activo'   },
                    { v: 'inactivo', l: 'Inactivo' },
                ]},
            ],
            quickFilters: [
                { id: 'qf_activo',   label: 'Activos',     mk: () => ({ key: 'estado',    op: '=', value: 'activo',   display: 'Activo'    }) },
                { id: 'qf_inactivo', label: 'Inactivos',   mk: () => ({ key: 'estado',    op: '=', value: 'inactivo', display: 'Inactivo'  }) },
                { id: 'qf_sinpago',  label: 'Sin pago',    mk: () => ({ key: 'tipo_pago', op: '=', value: 'sin_pago', display: 'Sin pago'  }) },
                { id: 'qf_total',    label: 'Pago total',  mk: () => ({ key: 'tipo_pago', op: '=', value: 'total',    display: 'Pago total'}) },
            ],
            onApply: () => filtrarTiposDOM(),
        }).init();
    }

    // ── FiltrosBusqueda: Recursos ────────────────────────────────────────────
    if (window.FiltrosBusqueda && document.getElementById('fbBuscadorRecursos')) {
        new FiltrosBusqueda({
            containerId:   'fbBuscadorRecursos',
            hiddenInputId: 'buscarRecursosHidden',
            placeholder:   'Buscar recursos...',
            fields: [
                { key: 'nombre', label: 'Nombre', icon: 'bi-person-gear', type: 'text' },
                { key: 'tipo',   label: 'Tipo',   icon: 'bi-tag',         type: 'select', options: [
                    { v: 'persona', l: 'Persona' },
                    { v: 'sala',    l: 'Sala'    },
                    { v: 'equipo',  l: 'Equipo'  },
                ]},
                { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                    { v: 'activo',   l: 'Activo'   },
                    { v: 'inactivo', l: 'Inactivo' },
                ]},
            ],
            quickFilters: [
                { id: 'qf_activo',   label: 'Activos',   mk: () => ({ key: 'estado', op: '=', value: 'activo',   display: 'Activo'   }) },
                { id: 'qf_inactivo', label: 'Inactivos', mk: () => ({ key: 'estado', op: '=', value: 'inactivo', display: 'Inactivo' }) },
                { id: 'qf_persona',  label: 'Personas',  mk: () => ({ key: 'tipo',   op: '=', value: 'persona',  display: 'Persona'  }) },
                { id: 'qf_sala',     label: 'Salas',     mk: () => ({ key: 'tipo',   op: '=', value: 'sala',     display: 'Sala'     }) },
                { id: 'qf_equipo',   label: 'Equipos',   mk: () => ({ key: 'tipo',   op: '=', value: 'equipo',   display: 'Equipo'   }) },
            ],
            onApply: () => filtrarRecursosDOM(),
        }).init();
    }

    // ── Visibilidad de columnas: manejadores específicos por tab ─────────────
    // (override de favoritos.js para usar los style elements correctos)
    document.querySelectorAll('.dropdown-vista-columnas').forEach(div => {
        const menu   = div.querySelector('.dropdown-menu');
        const modulo = menu?.dataset.modulo ?? '';
        const esTipos    = modulo.includes('tipos');
        const esRecursos = modulo.includes('recursos');
        if (!esTipos && !esRecursos) return;

        div.querySelectorAll('.toggle-columna-vista').forEach(chk => {
            chk.addEventListener('change', () => {
                const ocultas  = Array.from(div.querySelectorAll('.toggle-columna-vista:not(:checked)')).map(c => c.value);
                const styleId  = esTipos ? 'estiloVistaTipos' : 'estiloVistaRecursos';
                const styleEl  = document.getElementById(styleId);
                if (styleEl) {
                    styleEl.innerHTML = ocultas.map(oc =>
                        `th[data-col="${oc}"], td[data-col="${oc}"] { display: none !important; }`
                    ).join('\n');
                }
                if (typeof guardarPreferenciaVista === 'function') {
                    guardarPreferenciaVista(modulo, '__columnas_ocultas__', ocultas, 'Columnas actualizadas');
                }
            });
        });
    });

    // ── Auto-aplicar ordenamiento guardado ────────────────────────────────────
    const mapaTipos = {
        nombre:    [1, 'texto'],
        duracion:  [2, 'numero'],
        precio:    [3, 'numero'],
        tipo_pago: [4, 'texto'],
        estado:    [5, 'texto'],
    };
    const mapaRecursos = {
        nombre: [0, 'texto'],
        tipo:   [1, 'texto'],
        estado: [3, 'texto'],
    };
    autoSortInit('tbodyTipos',    '<?= htmlspecialchars($tiposSortCol) ?>',
                                  '<?= strtolower($tiposSortDir) ?>', mapaTipos);
    autoSortInit('tbodyRecursos', '<?= htmlspecialchars($recSortCol)   ?>',
                                  '<?= strtolower($recSortDir)   ?>', mapaRecursos);

    // ── Sincronizar hash de URL al cambiar pestaña ────────────────────────────
    document.querySelectorAll('#tabsCitasCfg [data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', e => {
            const target = e.target.dataset.bsTarget?.replace('#tab-', '') ?? 'tipos';
            history.replaceState(null, '', `?tab=${target}`);
        });
    });
});

// ─── COPIAR LINK ─────────────────────────────────────────────────────────────
function copiarLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        Swal.fire({ icon: 'success', title: 'Copiado', text: 'Link copiado al portapapeles.', timer: 1200, showConfirmButton: false });
    });
}

// ─── FORM PORTAL ─────────────────────────────────────────────────────────────
document.getElementById('frmPortal')?.addEventListener('submit', e => {
    e.preventDefault();
    const btn = e.target.querySelector('[type="submit"]');
    const txtOrig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...'; }

    const fd = new FormData(e.target);
    ['activo', 'requiere_confirmacion', 'permite_pagos_online'].forEach(name => {
        if (!fd.has(name)) fd.set(name, '0');
    });

    fetch(`${URL_CITAS_CFG}/guardar-portal`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; }
            if (res.ok) {
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: res.mensaje, timer: 1800, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', res.mensaje, 'error');
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; }
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        });
});
</script>

<?php include __DIR__ . '/_modal_tipo.php'; ?>
<?php include __DIR__ . '/_modal_recurso.php'; ?>
<?php include __DIR__ . '/_modal_horario.php'; ?>
