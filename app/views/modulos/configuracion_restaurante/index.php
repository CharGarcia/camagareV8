<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */

$base = BASE_URL;
$urlBaseCR = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'nombre';
$ordenDir   = $ordenDir ?? 'asc';
$buscar     = $buscar ?? '';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

// El tbody lo arma el controlador (renderFilas), para que la carga inicial y el
// refresco por AJAX pinten exactamente lo mismo.
$filasHtml = $filasHtml ?? '';
?>
<style>
    .cr-header { flex-shrink: 0; }
    .cr-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .cr-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .estacion-row { cursor: pointer; }
    .estacion-row:hover { background-color: rgba(0, 0, 0, .04); }
    /* La estrella de "predeterminada" se acciona desde la propia fila. */
    .cr-estrella { background: none; border: 0; padding: 2px 6px; line-height: 1; color: #ced4da; }
    .cr-estrella:hover { color: #ffc107; }
    .cr-estrella.activa { color: #ffc107; }
    .cr-estrella:disabled { cursor: not-allowed; opacity: .45; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="cr-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-shop"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex align-items-center gap-2">
        <?php // Ajustes que no son de una estación concreta, sino del salón entero. ?>
        <?php if ($perm['actualizar']): ?>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label small fw-bold mb-0" for="cr-ancho-tirilla" title="Ancho del papel de la cuenta y la factura; ajusta el tamaño de letra">
                    <i class="bi bi-receipt me-1 text-muted"></i>Papel de la tirilla
                </label>
                <select class="form-select form-select-sm" id="cr-ancho-tirilla" style="width:100px;">
                    <option value="80" <?= (int) ($anchoTirilla ?? 80) === 80 ? 'selected' : '' ?>>80 mm</option>
                    <option value="58" <?= (int) ($anchoTirilla ?? 80) === 58 ? 'selected' : '' ?>>58 mm</option>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="CR_abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nueva</button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y exportación -->
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorCR" style="width: 420px;"></div>
            <input type="hidden" id="buscarEstacion" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorCR',
                        hiddenInputId: 'buscarEstacion',
                        fields: [
                            { key: 'nombre', label: 'Nombre', icon: 'bi-printer', type: 'text' },
                            { key: 'tipo',   label: 'Tipo',   icon: 'bi-fire',    type: 'select', options: [
                                { v: 'cocina', l: 'Cocina' },
                                { v: 'barra',  l: 'Barra' },
                                { v: 'otro',   l: 'Otro' },
                            ]},
                            { key: 'imprime', label: 'Imprime órdenes', icon: 'bi-printer-fill', type: 'select', options: [
                                { v: 'true',  l: 'Sí' },
                                { v: 'false', l: 'No' },
                            ]},
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'true',  l: 'Activa' },
                                { v: 'false', l: 'Inactiva' },
                            ]},
                            { key: 'papel',  label: 'Papel (mm)', icon: 'bi-file-earmark', type: 'number_range' },
                            { key: 'copias', label: 'Copias',     icon: 'bi-files',        type: 'number_range' },
                        ],
                        quickFilters: [
                            { id: 'qf_activas',  label: 'Activas',        mk: () => ({ key: 'estado',  op: '=', value: 'true',  display: 'Activa' }) },
                            { id: 'qf_imprimen', label: 'Con impresora',  mk: () => ({ key: 'imprime', op: '=', value: 'true',  display: 'Sí' }) },
                            { id: 'qf_pantalla', label: 'Solo pantalla',  mk: () => ({ key: 'imprime', op: '=', value: 'false', display: 'No' }) },
                        ],
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre'         => 'Nombre',
                    'tipo'           => 'Tipo',
                    'impresion'      => 'Impresión',
                    'ancho_papel'    => 'Papel',
                    'copias'         => 'Copias',
                    'predeterminada' => 'Predeterminada',
                    'activo'         => 'Estado',
                    'usos'           => 'En uso',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBaseCR ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseCR ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="cr-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="tipo" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="imprime_ordenes" data-col="impresion">Impresión <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="ancho_papel" data-col="ancho_papel">Papel <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="copias" data-col="copias">Copias <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="predeterminada" title="Recoge los ítems que no tienen estación propia">Predeterminada</th>
                        <th class="text-center sortable-header" role="button" data-sort="activo" data-col="activo">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="usos" data-col="usos">En uso <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyEstaciones"><?= $filasHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: crear / editar estación -->
<div class="modal fade" id="modalEstacion" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-printer me-1"></i><span id="tituloModalEstacion">Nueva estación</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEstacion" onsubmit="return false;">
                <div class="modal-body">
                    <input type="hidden" id="est_id" value="">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold d-block" for="est_nombre">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="est_nombre" maxlength="60" placeholder="Ej. Cocina, Barra 1, Parrilla">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold d-block" for="est_tipo">Tipo</label>
                            <select class="form-select form-select-sm" id="est_tipo">
                                <option value="cocina">Cocina</option>
                                <option value="barra">Barra</option>
                                <option value="otro">Otro</option>
                            </select>
                            <div class="form-text mt-0" style="font-size:.68rem;">Solo define el ícono y el color.</div>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="est_activo" checked>
                        <label class="form-check-label small" for="est_activo">Activa</label>
                    </div>

                    <div class="border rounded p-2 mt-3 bg-light">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="est_imprime">
                            <label class="form-check-label small fw-bold" for="est_imprime">
                                <i class="bi bi-printer me-1"></i>Imprime las órdenes en papel
                            </label>
                        </div>
                        <div id="est_bloque_impresion" class="row g-2 align-items-end d-none">
                            <div class="col-4">
                                <label class="form-label small fw-bold d-block" for="est_ancho">Papel</label>
                                <select class="form-select form-select-sm" id="est_ancho">
                                    <option value="80">80 mm</option>
                                    <option value="58">58 mm</option>
                                </select>
                            </div>
                            <div class="col-3">
                                <label class="form-label small fw-bold d-block" for="est_copias">Copias</label>
                                <input type="number" class="form-control form-control-sm" id="est_copias" min="1" max="5" value="1">
                            </div>
                            <div class="col-5">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="est_auto" checked>
                                    <label class="form-check-label small" for="est_auto">Sale sola al enviar a cocina</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-text mt-0" style="font-size:.68rem;">
                                    Quien saca el papel es la <b>pantalla de preparación</b> de esta estación: debe estar
                                    abierta en un equipo con la impresora conectada.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-text mt-2" style="font-size:.68rem;">
                        La <b>estación predeterminada</b> —la que recoge los ítems agregados desde el stock
                        general— se marca con la estrella de cada fila del listado.
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light py-2">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarEstacion" onclick="CR_eliminar()"><i class="bi bi-trash"></i> Eliminar</button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm" id="btnGuardarEstacion" onclick="CR_guardar()"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const CR_URL = "<?= $urlBaseCR ?>";
    const CR_PERM = {
        crear: <?= !empty($perm['crear']) ? 'true' : 'false' ?>,
        actualizar: <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>,
        eliminar: <?= !empty($perm['eliminar']) ? 'true' : 'false' ?>
    };
    let CR_ORDEN = { col: "<?= htmlspecialchars($ordenCol) ?>", dir: "<?= htmlspecialchars($ordenDir) ?>" };
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/configuracion_restaurante.js?v=<?= time() ?>"></script>
