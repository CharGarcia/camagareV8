<?php
/** @var array $perm */
/** @var array $rows */
/** @var int $total */
/** @var bool $esAprobador */
/** @var string $rutaModulo */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $exportQs Query string ya filtrado para los enlaces de exportar */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = (int) ($total ?? 0);
$page       = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$perPage    = (int) ($perPage ?? 20);
$buscar     = $buscar ?? '';
$ordenCol   = $ordenCol ?? 'numero';
$ordenDir   = $ordenDir ?? 'DESC';
$exportQs   = $exportQs ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

$vistaConfig = \App\Helpers\PreferenciasHelper::getPreferenciasVista($rutaModulo);

echo \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig);
?>
<style>
    .cargas-header {
        flex-shrink: 0;
    }

    .cargas-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .cargas-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .carga-row {
        cursor: pointer;
    }

    .carga-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    /* Modal de detalle: no crece con las líneas. El diálogo se queda dentro del alto de
       la ventana (modal-dialog-scrollable) y la lista de líneas hace su propio scroll
       vertical con el encabezado fijo. La clase de la lista NO lleva "-scroll": en esta
       página (app-shell) app.css le forzaría altura 100% y le quitaría el tope. */
    #ci-modal-detalle .modal-body {
        display: flex;
        flex-direction: column;
    }

    #ci-modal-detalle .ci-det-cuerpo {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
    }

    #ci-modal-detalle .ci-det-lineas {
        flex: 0 1 auto;
        min-height: 0;
        max-height: 55vh;
        overflow: auto;
    }

    #ci-modal-detalle .ci-det-lineas thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
        white-space: nowrap;
    }

    /* Tablet y móvil: el modal ocupa toda la pantalla y la lista usa todo el alto libre. */
    @media (max-width: 991.98px) {
        #ci-modal-detalle .ci-det-lineas {
            max-height: none;
        }
    }
</style>

<div class="cargas-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="CI_abrirImportar()">
            <i class="bi bi-upload me-1"></i> Importar carga
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre las columnas del listado
            // (sin sugerencias) + botón embudo que abre un modal con todos los filtros +
            // chips de los activos. Las claves (key) deben existir en los mapas de
            // CargaInventarioRepository::getListado().
            // Dos pestañas: "Carga" (filtros por campo) y "Detalles" (búsqueda libre dentro
            // de las líneas de las cargas; ver `busquedaDetalle` abajo, sin filtros por campo).
            $opcFiltro = $opcionesFiltro ?? [];
            $opcIdNombre = fn(string $k) => array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcFiltro[$k] ?? []);
            $tC = 'Carga';
            // Filas de 12 columnas:
            //   Carga:    [Fecha 6][Tipo 3][Estado 3]
            //             [N° de carga 4][Líneas 4][Con líneas en error 4]
            //             [Observación 6][Motivo de rechazo 6]
            //   Personas: [Creado por 4][Aprobado por 4][Fecha de aprobación 4]
            //   Líneas:   [Producto 6][Bodega 6]
            $filtrosCargas = [
                // ── Carga ──
                ['tab' => $tC, 'key' => 'fecha',  'label' => 'Fecha de la carga', 'icon' => 'bi-calendar-event',   'type' => 'date_range', 'grupo' => 'Carga', 'col' => 6, 'atajos' => true],
                ['tab' => $tC, 'key' => 'tipo',   'label' => 'Tipo',              'icon' => 'bi-arrow-left-right', 'type' => 'select',     'grupo' => 'Carga', 'col' => 3, 'options' => [
                    ['v' => 'entrada', 'l' => 'Entrada'],
                    ['v' => 'salida',  'l' => 'Salida'],
                    ['v' => 'ajuste',  'l' => 'Ajuste'],
                ]],
                ['tab' => $tC, 'key' => 'estado', 'label' => 'Estado',            'icon' => 'bi-flag',             'type' => 'select',     'grupo' => 'Carga', 'col' => 3, 'options' => [
                    ['v' => 'pendiente', 'l' => 'Pendiente'],
                    ['v' => 'aprobada',  'l' => 'Aprobada'],
                    ['v' => 'rechazada', 'l' => 'Rechazada'],
                    ['v' => 'anulada',   'l' => 'Anulada'],
                ]],
                ['tab' => $tC, 'key' => 'numero',      'label' => 'N° de carga',         'icon' => 'bi-hash',            'type' => 'number_range', 'grupo' => 'Carga', 'col' => 4],
                ['tab' => $tC, 'key' => 'lineas',      'label' => 'Líneas',              'icon' => 'bi-list-ol',         'type' => 'number_range', 'grupo' => 'Carga', 'col' => 4],
                ['tab' => $tC, 'key' => 'con_error',   'label' => 'Líneas con error',    'icon' => 'bi-exclamation-triangle', 'type' => 'select', 'grupo' => 'Carga', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Pendiente con líneas en error'],
                    ['v' => 'no', 'l' => 'Sin líneas en error'],
                ]],
                ['tab' => $tC, 'key' => 'observacion', 'label' => 'Observación / Ref',   'icon' => 'bi-chat-left-text',  'type' => 'text',         'grupo' => 'Carga', 'col' => 6],
                ['tab' => $tC, 'key' => 'motivo',      'label' => 'Motivo de rechazo',   'icon' => 'bi-x-octagon',       'type' => 'text',         'grupo' => 'Carga', 'col' => 6],
                // ── Personas ──
                ['tab' => $tC, 'key' => 'id_creado',   'label' => 'Creado por',          'icon' => 'bi-person',          'type' => 'select',     'grupo' => 'Personas', 'col' => 4, 'options' => $opcIdNombre('creadores')],
                ['tab' => $tC, 'key' => 'id_aprobado', 'label' => 'Aprobado por',        'icon' => 'bi-person-check',    'type' => 'select',     'grupo' => 'Personas', 'col' => 4, 'options' => $opcIdNombre('aprobadores')],
                ['tab' => $tC, 'key' => 'aprobacion',  'label' => 'Fecha de aprobación', 'icon' => 'bi-calendar-check',  'type' => 'date_range', 'grupo' => 'Personas', 'col' => 4],
                // ── Líneas ──
                ['tab' => $tC, 'key' => 'producto',    'label' => 'Producto (código o nombre)', 'icon' => 'bi-box-seam', 'type' => 'text',   'grupo' => 'Líneas', 'col' => 6],
                ['tab' => $tC, 'key' => 'id_bodega',   'label' => 'Bodega',              'icon' => 'bi-house-door',      'type' => 'select', 'grupo' => 'Líneas', 'col' => 6, 'options' => $opcIdNombre('bodegas')],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCI"></div>
            <input type="hidden" id="ci-buscar" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorCI',
                        hiddenInputId: 'ci-buscar',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de cargas de inventario',
                        inputWidth: 420,
                        extraId: 'fmExtraCI',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de las líneas de las cargas.
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBase ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de las cargas',
                            placeholder: 'Código o nombre de producto, bodega, lote, NUP, observación, error, cantidad...',
                            columns: [
                                { key: 'producto',   label: 'Producto' },
                                { key: 'bodega',     label: 'Bodega' },
                                { key: 'cantidad',   label: 'Cantidad', align: 'end' },
                                { key: 'costo',      label: 'Costo unit.', align: 'end' },
                                { key: 'lote',       label: 'Lote / NUP' },
                                { key: 'nota',       label: 'Observación' },
                                { key: 'numero_txt', label: 'Carga', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',      label: 'Fecha' },
                                { key: 'tipo',       label: 'Tipo' },
                                { key: 'estado',     label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: String(row.numero) }),
                            onOpen: (row, fm) => { fm.hide(); setTimeout(() => CI_verDetalle(row.id_carga), 350); },
                        },
                        fields: <?= json_encode($filtrosCargas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#ci-tbody',   // se atenúa mientras se busca
                        onApply: () => window.CI_buscar && window.CI_buscar(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraCI" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'      => 'N°',
                    'fecha'       => 'Fecha',
                    'tipo'        => 'Tipo',
                    'lineas'      => 'Líneas',
                    'estado'      => 'Estado',
                    'creado'      => 'Creado por',
                    'aprobado'    => 'Aprobado por',
                    'observacion' => 'Observación',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo);
                ?>

                <a id="ci-btn-pdf" href="<?= $urlBase ?>/export-pdf<?= $exportQs ?>"
                   target="_blank" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="ci-btn-excel" href="<?= $urlBase ?>/export-excel<?= $exportQs ?>"
                   class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="ci-pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="ci-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="CI_buscar(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="CI_buscar(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="cargas-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2 sortable-header" role="button" data-sort="numero" data-col="numero">N° <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha" data-col="fecha">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="tipo" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="lineas" data-col="lineas">Líneas <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="creado" data-col="creado">Creado por <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="aprobado" data-col="aprobado">Aprobado por <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="pe-3 sortable-header" role="button" data-sort="observacion" data-col="observacion">Observación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="ci-tbody">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-box-seam fs-3 d-block mb-2"></i>No hay cargas de inventario registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php include __DIR__ . '/_fila.php'; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Importar carga -->
<div class="modal fade" id="ci-modal-importar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-upload me-2"></i>Importar carga de inventario</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <?php // novalidate: los avisos (p. ej. que falta el archivo) salen con SweetAlert, no con la burbuja del navegador. ?>
            <form id="ci-form-importar" novalidate>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Tipo de movimiento</label>
                            <select name="tipo_movimiento" class="form-select form-select-sm">
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                                <option value="ajuste">Ajuste</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Observación</label>
                            <input type="text" name="observacion" class="form-control form-control-sm" placeholder="Opcional">
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold mb-0">Archivo Excel</label>
                                <a href="<?= $urlBase ?>/descargarPlantilla" class="btn btn-outline-success btn-sm py-0 px-2" title="Descargar plantilla de ejemplo">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Descargar plantilla
                                </a>
                            </div>
                            <input type="file" name="archivo" accept=".xlsx,.xls,.csv" class="form-control form-control-sm" required>
                            <div class="form-text" style="font-size:0.72rem;">
                                Formato <strong>Excel (.xlsx)</strong>. Columnas: <code>codigo_producto, bodega, cantidad, costo_unitario, numero_lote, fecha_caducidad, nup, observacion</code>.
                                La plantilla incluye hojas <strong>Productos</strong> y <strong>Bodegas</strong> con los valores válidos de la empresa. El movimiento se aplica al inventario solo al aprobarse (si la empresa exige aprobación).
                                <br><strong>Ajuste</strong>: conteo físico. La cantidad es lo contado (0 si no hay); al aprobar se registra solo la diferencia. Si el producto tiene lotes, una línea por lote; sin NUP.
                                <br><strong>NUP</strong>: una serie con cualquier cantidad, o varias series (una por renglón de la celda) con cantidad igual al número de series.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalle -->
<div class="modal fade" id="ci-modal-detalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-box-seam me-2"></i>Carga de inventario <span id="ci-det-numero"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <div id="ci-modal-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Cargando información de la carga...</div>
                </div>

                <!-- Barra de acciones del documento -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap flex-shrink-0">
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="CI_exportarDetalle('pdf')" title="Descargar PDF de las líneas"><i class="bi bi-file-earmark-pdf"></i></button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="CI_exportarDetalle('excel')" title="Descargar Excel de las líneas"><i class="bi bi-file-earmark-excel"></i></button>
                    <span id="ci-det-resumen" class="ms-auto small text-muted"></span>
                </div>

                <div id="ci-detalle-cuerpo" class="ci-det-cuerpo p-3"></div>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <div class="d-flex gap-2">
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" id="ci-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="CI_eliminar()"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    <?php endif; ?>
                    <?php // Anular (solo cargas aprobadas): aprobador con permiso de eliminar. ?>
                    <?php if (!empty($perm['eliminar']) && !empty($esAprobador)): ?>
                        <button type="button" id="ci-btn-anular" class="btn btn-outline-danger btn-sm d-none" onclick="CI_anular()" title="Reversa el stock de la carga"><i class="bi bi-x-octagon me-1"></i>Anular</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <?php if (!empty($esAprobador)): ?>
                        <button type="button" id="ci-btn-rechazar" class="btn btn-outline-danger btn-sm d-none" onclick="CI_rechazar()"><i class="bi bi-x-circle me-1"></i>Rechazar</button>
                        <button type="button" id="ci-btn-aprobar" class="btn btn-success btn-sm d-none" onclick="CI_aprobar()"><i class="bi bi-check-circle me-1"></i>Aprobar</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CI_URL = '<?= $urlBase ?>';
const CI_ES_APROBADOR  = <?= !empty($esAprobador) ? 'true' : 'false' ?>;
const CI_ES_SUPERADMIN = <?= !empty($esSuperAdmin) ? 'true' : 'false' ?>;
const CI_PUEDE_CREAR   = <?= !empty($perm['crear']) ? 'true' : 'false' ?>;
const CI_ID_USUARIO    = <?= (int) ($idUsuarioActual ?? 0) ?>;
const CI_APROBADORES   = <?= json_encode(array_values($aprobadoresNombres ?? []), JSON_UNESCAPED_UNICODE) ?>;
const CI_PER_PAGE      = <?= $perPage ?>;
let CI_currentSort = '<?= $ordenCol ?>';
let CI_currentDir  = '<?= $ordenDir ?>';
// Orden múltiple (Shift+clic): lista completa de criterios, en el formato que lee
// OrdenListado en PHP. CI_currentSort/CI_currentDir quedan como el principal.
let CI_currentSorts = <?= $ordenJson ?? '[]' ?>;
let CI_currentPage = <?= $page ?>;
let CI_cargaActual = null;
let CI_sorter = null;

/** Escapa texto para insertarlo en HTML (también dentro de atributos). */
const CI_esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/** Booleano de PostgreSQL tal como llega en el JSON (true, 't' o '1'). */
const CI_bool = v => v === true || v === 't' || v === '1' || v === 1;

/** Fecha y hora de PostgreSQL ("2026-09-17 10:22:33.123") en formato d-m-Y H:i:s. */
const CI_fechaHora = s => {
    const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2}:\d{2})/);
    return m ? `${m[3]}-${m[2]}-${m[1]} ${m[4]}` : '';
};

/**
 * Todos los avisos del módulo van con SweetAlert.
 * - modalId: si el aviso sale con un modal abierto, se monta dentro de él; montado en
 *   el body, el foco del modal de Bootstrap no deja escribir en el aviso (p. ej. el
 *   motivo del rechazo).
 * - heightAuto:false: la página usa app-shell (html/body al 100% de alto) y
 *   SweetAlert lo cambiaría mientras el aviso está abierto.
 */
function CI_aviso(opciones, modalId = null) {
    return Swal.fire({
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '#0d6efd',
        cancelButtonText: 'Cancelar',
        heightAuto: false,
        target: (modalId && document.getElementById(modalId)) || 'body',
        ...opciones,
    });
}

/** Aviso de "en proceso" (sin botones) mientras corre una petición al servidor. */
function CI_avisoProceso(titulo, html, modalId = null) {
    CI_aviso({
        title: titulo,
        html: html || '',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading(),
    }, modalId);
}

/**
 * Refresca el listado por AJAX (búsqueda, orden y paginación) sin recargar la
 * página: repinta filas, paginación, contador y los enlaces de PDF/Excel.
 */
window.CI_buscar = async function (p = 1) {
    const b = (document.getElementById('ci-buscar')?.value || '').trim();
    const orden = window.CMG_ordenParam(CI_currentSorts || []);
    const uri = `${CI_URL}/searchAjax?b=${encodeURIComponent(b)}&page=${p}&orden=${encodeURIComponent(orden)}`;
    // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
    // carga, también al paginar u ordenar (que llaman a esta función directo).
    const tbody = document.getElementById('ci-tbody');
    if (tbody) tbody.classList.add('fm-cargando-target');
    try {
        const resp = await fetch(uri);
        const data = await resp.json();
        if (!data.ok) return;
        // Si la página quedó vacía (p. ej. al eliminar la única carga de la última
        // página), se muestra la anterior en vez de "No hay cargas".
        if (p > 1 && data.total <= (p - 1) * CI_PER_PAGE) return await CI_buscar(p - 1);
        CI_currentPage = p;
        document.getElementById('ci-tbody').innerHTML = data.rows;
        document.getElementById('ci-pagination').innerHTML = data.pagination;
        document.getElementById('ci-pagination-info').textContent = data.info;
        document.getElementById('ci-btn-pdf').href = data.pdf_url;
        document.getElementById('ci-btn-excel').href = data.excel_url;

        // Los íconos (incluida la prioridad 1/2/3 del orden múltiple) los repinta
        // el motor global; aquí solo se le pide que se refresque.
        if (CI_sorter) CI_sorter.refreshIcons();
    } catch (e) {
        console.error('Error en búsqueda de cargas de inventario:', e);
    } finally {
        if (tbody) tbody.classList.remove('fm-cargando-target');
    }
};

/** @param {string} observacion Texto inicial de la observación (p. ej. al reemplazar una carga anulada). */
function CI_abrirImportar(observacion = '') {
    const form = document.getElementById('ci-form-importar');
    form.reset();
    form.querySelector('input[name=observacion]').value = observacion;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ci-modal-importar')).show();
}

document.getElementById('ci-form-importar').addEventListener('submit', async function (e) {
    e.preventDefault();
    const archivo = this.querySelector('input[name=archivo]').files[0];
    if (!archivo) {
        CI_aviso({ icon: 'warning', title: 'Falta el archivo', text: 'Seleccione el archivo Excel con las líneas de la carga.' }, 'ci-modal-importar');
        return;
    }

    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    // Mientras el servidor lee el archivo y comprueba cada línea contra los productos y
    // bodegas de la empresa (con archivos grandes tarda unos segundos).
    CI_avisoProceso('Cargando archivo…',
        `Importando <b>${CI_esc(archivo.name)}</b> y comprobando cada línea.<br><span class="small text-muted">No cierre esta ventana.</span>`,
        'ci-modal-importar');

    let json;
    try {
        const res = await fetch(`${CI_URL}/importarAjax`, { method: 'POST', body: new FormData(this) });
        json = await res.json();
    } catch (err) {
        json = { ok: false, mensaje: 'No se pudo comunicar con el servidor. Intente de nuevo.' };
    } finally {
        btn.disabled = false;
    }

    if (!json.ok) {
        CI_aviso({ icon: 'error', title: 'No se pudo importar', text: json.mensaje || json.error || 'Error al importar el archivo.' }, 'ci-modal-importar');
        return;
    }

    // La carga ya quedó registrada: se cierra el formulario, se refresca el listado sin
    // recargar la página (conserva búsqueda, filtros y orden) y se informa el resultado.
    bootstrap.Modal.getInstance(document.getElementById('ci-modal-importar'))?.hide();
    CI_buscar(1);
    const r = await CI_aviso(CI_resultadoImportacion(json.data));
    if (r.isConfirmed) CI_verDetalle(json.data.id);
});

/** Opciones del aviso que resume una importación recién registrada. */
function CI_resultadoImportacion(d) {
    const n = Number(d.lineas) || 0;
    const registradas = n === 1 ? 'Se registró 1 línea' : `Se registraron ${n} líneas`;
    const botones = {
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-list-ul me-1"></i>Ver líneas',
        cancelButtonText: 'Cerrar',
    };

    if (!d.validada) {
        const errores = d.errores || [];
        return {
            ...botones,
            icon: 'warning',
            title: `Carga #${d.numero} registrada con errores`,
            width: 640,
            html: `<div class="text-start small">
                <p class="mb-2">${errores.length} de ${n} ${n === 1 ? 'línea' : 'líneas'} no ${errores.length === 1 ? 'pasó' : 'pasaron'} la comprobación.
                    La carga quedó <b>pendiente</b> y no se podrá aprobar hasta corregirlas:</p>
                <ul class="mb-2 ps-3 text-danger" style="max-height:220px;overflow:auto;">${errores.map(x => `<li>${CI_esc(x)}</li>`).join('')}</ul>
                <p class="mb-0 text-muted">Las líneas no se editan en el sistema: corrija el archivo (la fila 1 es el encabezado),
                    elimine esta carga e impórtela de nuevo.</p>
            </div>`,
        };
    }
    if (d.estado === 'aprobada') {
        return { ...botones, icon: 'success', title: `Carga #${d.numero} aplicada al inventario`, text: `${registradas} y el stock ya se actualizó.` };
    }
    const quien = CI_APROBADORES.length ? ` por ${CI_APROBADORES.join(', ')}` : '';
    return {
        ...botones,
        icon: 'success',
        title: `Carga #${d.numero} registrada`,
        text: `${registradas}. Queda pendiente de aprobación${quien}; el stock se actualiza al aprobarla.`,
    };
}

async function CI_verDetalle(id) {
    CI_cargaActual = null;
    const cuerpo = document.getElementById('ci-detalle-cuerpo');
    cuerpo.innerHTML = '';
    document.getElementById('ci-det-numero').textContent = '';
    document.getElementById('ci-det-resumen').innerHTML = '';
    ['ci-btn-aprobar', 'ci-btn-rechazar', 'ci-btn-eliminar', 'ci-btn-anular'].forEach(b => document.getElementById(b)?.classList.add('d-none'));
    const modalEl = document.getElementById('ci-modal-detalle');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    const loader = document.getElementById('ci-modal-loader');
    loader?.classList.remove('d-none');

    let json;
    try {
        const res = await fetch(`${CI_URL}/getDetalleAjax?id=${id}`);
        json = await res.json();
    } catch (err) {
        json = { ok: false, mensaje: 'No se pudo comunicar con el servidor. Intente de nuevo.' };
    } finally {
        loader?.classList.add('d-none');
    }
    if (!json.ok) {
        await CI_aviso({ icon: 'error', title: 'No se pudo abrir la carga', text: json.mensaje || json.error || 'Carga no encontrada.' }, 'ci-modal-detalle');
        bootstrap.Modal.getInstance(modalEl)?.hide();
        return;
    }

    const c = json.data;
    CI_cargaActual = c;
    document.getElementById('ci-det-numero').textContent = '#' + c.numero;

    const validada = CI_bool(c.validada);
    const detalle  = c.detalle || [];
    const conError = detalle.filter(d => !CI_bool(d.linea_valida)).length;
    // Segregación de funciones: quien registró la carga no la aprueba (salvo super admin).
    const esCreador    = String(c.created_by) === String(CI_ID_USUARIO);
    const puedeAprobar = c.estado === 'pendiente' && CI_ES_APROBADOR && (CI_ES_SUPERADMIN || !esCreador);

    // Ajuste (conteo físico): la cantidad es lo contado. Aprobado, se muestran el saldo que
    // tenía el sistema y la diferencia registrada; pendiente, una estimación con el saldo de
    // hoy (al aprobar se recalcula).
    const esAjuste   = c.tipo_movimiento === 'ajuste';
    const ajusteHoy  = esAjuste && c.estado === 'pendiente';
    const saldoDe    = d => ajusteHoy ? d.saldo_actual : d.saldo_sistema;
    const difDe      = d => ajusteHoy ? d.diferencia_estimada : d.diferencia;
    const conDif     = esAjuste ? detalle.filter(d => difDe(d) != null && Math.abs(parseFloat(difDe(d))) > 0.000001).length : 0;
    const celdaNum   = v => (v == null || v === '') ? '<span class="text-muted">—</span>' : String(parseFloat(v));
    const celdaDif   = v => {
        if (v == null || v === '') return '<span class="text-muted">—</span>';
        const n = parseFloat(v);
        if (Math.abs(n) < 0.000001) return '<span class="text-muted">0</span>';
        return n > 0 ? `<span class="text-success fw-semibold">+${n}</span>` : `<span class="text-danger fw-semibold">${n}</span>`;
    };

    // Resumen junto a los botones de exportar: la lista de líneas hace su propio scroll.
    document.getElementById('ci-det-resumen').innerHTML = `${detalle.length} ${detalle.length === 1 ? 'línea' : 'líneas'}`
        + (esAjuste && detalle.some(d => difDe(d) != null) ? ` · ${conDif} con diferencia${ajusteHoy ? ' (estimado)' : ''}` : '')
        + (conError ? ` · <span class="text-danger fw-semibold">${conError} con error</span>` : '');

    // Líneas en el orden del archivo. El motivo lleva la fila del Excel (fila 1 =
    // encabezado), con la misma numeración de la comprobación al importar.
    const filas = detalle.map((d, i) => {
        const ok       = CI_bool(d.linea_valida);
        const producto = d.producto_nombre || '—';
        const bodega   = d.bodega_nombre || d.cod_bodega_raw || '—';
        return `<tr>
            <td class="ps-2 text-nowrap">${CI_esc(d.producto_codigo || d.cod_producto_raw || '—')}</td>
            <td class="text-truncate" style="max-width:280px" title="${CI_esc(producto)}">${CI_esc(producto)}</td>
            <td class="text-truncate" style="max-width:160px" title="${CI_esc(bodega)}">${CI_esc(bodega)}</td>
            <td class="text-end text-nowrap">${parseFloat(d.cantidad || 0)}</td>
            ${esAjuste ? `<td class="text-end text-nowrap">${celdaNum(saldoDe(d))}</td><td class="text-end text-nowrap">${celdaDif(difDe(d))}</td>` : ''}
            <td class="text-end text-nowrap">$ ${parseFloat(d.costo_unitario || 0).toFixed(2)}</td>
            <td style="min-width:180px">${CI_esc(d.observacion || '')}</td>
            <td class="text-center">${ok ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>'}</td>
            <td class="pe-2 text-danger" style="min-width:220px">${ok ? '' : `Fila ${i + 2}: ${CI_esc(d.error_linea || 'Línea con error')}`}</td>
        </tr>`;
    }).join('');

    const campo = (etiqueta, valor, col = 'col-6 col-md-3', claseValor = 'fw-bold') =>
        `<div class="${col}"><div class="text-muted" style="font-size:.65rem;">${etiqueta}</div><div class="${claseValor}">${valor}</div></div>`;
    const estadoTxt = { pendiente: 'Pendiente', aprobada: 'Aprobada', rechazada: 'Rechazada', anulada: 'Anulada' }[c.estado] || CI_esc(c.estado);
    const anuladaPor = [c.anulado_por_nombre, CI_fechaHora(c.anulada_at)].filter(Boolean).join(' · ');
    const aprobadores = CI_APROBADORES.length ? CI_esc(CI_APROBADORES.join(', ')) : 'un usuario autorizado (configúrelos en el módulo Aprobaciones)';

    cuerpo.innerHTML = `
        <div class="row g-2 small mb-3 flex-shrink-0">
            ${campo('Fecha', c.fecha ? CI_esc(c.fecha.split('-').reverse().join('-')) : '-')}
            ${campo('Tipo', CI_esc(c.tipo_movimiento), undefined, 'fw-bold text-capitalize')}
            ${campo('Estado', estadoTxt)}
            ${campo('Comprobada', validada ? 'Sí' : 'No', undefined, `fw-bold ${validada ? 'text-success' : 'text-danger'}`)}
            ${c.observacion ? campo('Observación', CI_esc(c.observacion), 'col-12', '') : ''}
            ${c.motivo_rechazo ? campo('Motivo rechazo', CI_esc(c.motivo_rechazo), 'col-12', 'text-danger') : ''}
            ${c.estado === 'anulada' ? campo('Anulada por', CI_esc(anuladaPor || '-'), 'col-12', '') : ''}
            ${c.motivo_anulacion ? campo('Motivo de anulación', CI_esc(c.motivo_anulacion), 'col-12', 'text-danger') : ''}
            ${c.estado === 'pendiente' && !puedeAprobar ? campo('Pendiente de aprobación por', aprobadores, 'col-12') : ''}
        </div>
        ${c.estado === 'pendiente' && !validada ? `<div class="small text-danger mb-2 flex-shrink-0">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>No se puede aprobar mientras haya líneas con error: corrija el archivo,
            <strong>elimine</strong> esta carga e impórtela de nuevo.
        </div>` : ''}
        ${esAjuste ? `<div class="small text-muted mb-2 flex-shrink-0">
            <i class="bi bi-clipboard-check me-1"></i>Ajuste por conteo físico: cada producto queda con la cantidad contada y solo se registra la diferencia.
            ${ajusteHoy ? 'El saldo y la diferencia son los de <strong>hoy</strong>; al aprobar se recalculan con el saldo de ese momento.' : ''}
        </div>` : ''}
        <div class="ci-det-lineas border rounded-3">
            <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.78rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-2">Código</th>
                        <th>Producto</th>
                        <th>Bodega</th>
                        <th class="text-end">${esAjuste ? 'Contado' : 'Cantidad'}</th>
                        ${esAjuste ? `<th class="text-end">${ajusteHoy ? 'Saldo actual' : 'Saldo sistema'}</th><th class="text-end">${ajusteHoy ? 'Diferencia estimada' : 'Diferencia'}</th>` : ''}
                        <th class="text-end">Costo</th>
                        <th>Observación</th>
                        <th class="text-center">OK</th>
                        <th class="pe-2">Motivo</th>
                    </tr>
                </thead>
                <tbody>${filas || `<tr><td colspan="${esAjuste ? 10 : 8}" class="text-center text-muted py-3">Sin líneas</td></tr>`}</tbody>
            </table>
        </div>`;

    const btnAp = document.getElementById('ci-btn-aprobar');
    if (btnAp && puedeAprobar) {
        btnAp.classList.remove('d-none');
        btnAp.disabled = !validada;
        btnAp.title = validada ? '' : 'La carga tiene líneas con error';
    }
    if (puedeAprobar) document.getElementById('ci-btn-rechazar')?.classList.remove('d-none');
    // Pendientes y rechazadas no movieron el stock: se eliminan. Una aprobada se anula
    // (lo hace otro aprobador, no quien la registró); una anulada queda como constancia.
    if (c.estado === 'pendiente' || c.estado === 'rechazada') document.getElementById('ci-btn-eliminar')?.classList.remove('d-none');
    if (c.estado === 'aprobada' && (CI_ES_SUPERADMIN || !esCreador)) document.getElementById('ci-btn-anular')?.classList.remove('d-none');
}

/** Botones PDF / Excel del modal: descargan las líneas de la carga abierta. */
function CI_exportarDetalle(tipo) {
    if (!CI_cargaActual) {
        CI_aviso({ icon: 'info', title: 'Espere un momento', text: 'La carga todavía se está abriendo.' }, 'ci-modal-detalle');
        return;
    }
    const accion = tipo === 'pdf' ? 'export-detalle-pdf' : 'export-detalle-excel';
    window.open(`${CI_URL}/${accion}?id=${CI_cargaActual.id}`, '_blank');
}

/**
 * Aprobar / rechazar / eliminar / anular: aviso de proceso, resultado y listado al día.
 * @param {Function|null} alTerminar Si se indica, reemplaza el aviso de éxito (recibe la respuesta).
 */
async function CI_accion(url, body, textoProceso, alTerminar = null) {
    CI_avisoProceso(textoProceso, '', 'ci-modal-detalle');
    let json;
    try {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
        json = await res.json();
    } catch (err) {
        json = { ok: false, mensaje: 'No se pudo comunicar con el servidor. Intente de nuevo.' };
    }
    if (!json.ok) {
        CI_aviso({ icon: 'error', title: 'No se pudo completar', text: json.mensaje || json.error || 'Error al procesar la carga.' }, 'ci-modal-detalle');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('ci-modal-detalle'))?.hide();
    CI_buscar(CI_currentPage);
    if (alTerminar) {
        alTerminar(json);
        return;
    }
    CI_aviso({ icon: 'success', titleText: json.mensaje || 'Listo' });
}

async function CI_aprobar() {
    if (!CI_cargaActual) return;
    const r = await CI_aviso({
        icon: 'question',
        title: `¿Aprobar la carga #${CI_cargaActual.numero}?`,
        text: CI_cargaActual.tipo_movimiento === 'ajuste'
            ? 'Cada producto quedará con la cantidad contada: se registrará solo la diferencia con el saldo que tenga en este momento.'
            : 'Se aplicará al inventario y el stock se actualizará.',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        confirmButtonColor: '#198754',
    }, 'ci-modal-detalle');
    if (!r.isConfirmed) return;
    CI_accion(`${CI_URL}/aprobarAjax`, `id=${CI_cargaActual.id}`, 'Aplicando la carga al inventario…');
}

async function CI_rechazar() {
    if (!CI_cargaActual) return;
    const r = await CI_aviso({
        icon: 'warning',
        title: `Rechazar la carga #${CI_cargaActual.numero}`,
        input: 'textarea',
        inputLabel: 'Motivo del rechazo',
        inputPlaceholder: 'Escriba el motivo…',
        showCancelButton: true,
        confirmButtonText: 'Rechazar',
        confirmButtonColor: '#dc3545',
        inputValidator: v => (!v || !v.trim()) ? 'Indique el motivo del rechazo.' : undefined,
    }, 'ci-modal-detalle');
    if (!r.isConfirmed) return;
    CI_accion(`${CI_URL}/rechazarAjax`, `id=${CI_cargaActual.id}&motivo=${encodeURIComponent(r.value.trim())}`, 'Rechazando la carga…');
}

async function CI_eliminar() {
    if (!CI_cargaActual) return;
    const r = await CI_aviso({
        icon: 'warning',
        title: `¿Eliminar la carga #${CI_cargaActual.numero}?`,
        text: 'Dejará de aparecer en el listado. No afecta el stock porque no fue aprobada.',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
    }, 'ci-modal-detalle');
    if (!r.isConfirmed) return;
    CI_accion(`${CI_URL}/eliminarAjax`, `id=${CI_cargaActual.id}`, 'Eliminando la carga…');
}

/**
 * Anular una carga aprobada (reversa su stock). Primero se pregunta al servidor si se puede
 * —entre otras cosas, si sus productos ya se usaron después de aplicarla— y solo entonces se
 * pide el motivo. Una carga aprobada no se edita: se anula y se importa el archivo corregido.
 */
async function CI_anular() {
    if (!CI_cargaActual) return;
    const carga = CI_cargaActual;
    CI_avisoProceso('Comprobando la carga…', 'Revisando si sus productos ya se usaron después de aplicarla.', 'ci-modal-detalle');

    let chk;
    try {
        const res = await fetch(`${CI_URL}/comprobarAnulacionAjax?id=${carga.id}`);
        chk = await res.json();
    } catch (err) {
        chk = { ok: false, mensaje: 'No se pudo comunicar con el servidor. Intente de nuevo.' };
    }
    if (!chk.ok) {
        CI_aviso({ icon: 'error', title: 'No se pudo comprobar la carga', text: chk.mensaje || chk.error || 'Error al comprobar la carga.' }, 'ci-modal-detalle');
        return;
    }
    if (!chk.puede) {
        const conflictos = chk.conflictos || [];
        CI_aviso({
            icon: 'error',
            title: `No se puede anular la carga #${carga.numero}`,
            ...(conflictos.length ? { width: 720 } : {}),
            html: `<div class="text-start small">
                <p class="mb-2">${CI_esc(chk.mensaje)}</p>
                ${conflictos.length ? `<ul class="mb-2 ps-3 text-danger" style="max-height:260px;overflow:auto;">${conflictos.map(x => `<li>${CI_esc(x)}</li>`).join('')}</ul>
                <p class="mb-0 text-muted">Anule o elimine primero esos documentos, o corrija el stock con una carga nueva (entrada o salida) sin anular esta.</p>` : ''}
            </div>`,
        }, 'ci-modal-detalle');
        return;
    }

    const n = Number(chk.movimientos) || 0;
    const r = await CI_aviso({
        icon: 'warning',
        title: `¿Anular la carga #${carga.numero}?`,
        html: `<div class="text-start small">
            <p class="mb-2">${n === 0
                ? 'Esta carga no movió el stock (el conteo coincidía con el sistema): no hay nada que reversar, solo quedará anulada.'
                : `${n === 1 ? 'Se reversará 1 movimiento' : `Se reversarán ${n} movimientos`} de inventario y el stock volverá a como estaba antes de aplicarla. Sus productos no se usaron después, así que se puede anular.`}</p>
            <p class="mb-0 text-muted">La carga queda en el listado como <b>Anulada</b>. Si tenía un error, importe después el archivo corregido como una carga nueva.</p>
        </div>`,
        input: 'textarea',
        inputLabel: 'Motivo de la anulación',
        inputPlaceholder: 'Escriba el motivo…',
        showCancelButton: true,
        confirmButtonText: 'Sí, anular',
        confirmButtonColor: '#dc3545',
        inputValidator: v => (!v || !v.trim()) ? 'Indique el motivo de la anulación.' : undefined,
    }, 'ci-modal-detalle');
    if (!r.isConfirmed) return;

    CI_accion(`${CI_URL}/anularAjax`, `id=${carga.id}&motivo=${encodeURIComponent(r.value.trim())}`, 'Anulando la carga y reversando el stock…', async json => {
        const fin = await CI_aviso({
            icon: 'success',
            titleText: json.mensaje || 'Carga anulada',
            text: CI_PUEDE_CREAR ? 'Si la carga tenía un error, importe ahora el archivo corregido como una carga nueva.' : '',
            showCancelButton: CI_PUEDE_CREAR,
            confirmButtonText: CI_PUEDE_CREAR ? '<i class="bi bi-upload me-1"></i>Importar archivo corregido' : 'Aceptar',
            cancelButtonText: 'Cerrar',
        });
        if (CI_PUEDE_CREAR && fin.isConfirmed) CI_abrirImportar(`Corrige la carga #${carga.numero} (anulada)`);
    });
}

// multi: clic normal ordena por una columna; Shift+clic encadena hasta 3
// (ASC → DESC → fuera del orden), con la prioridad numerada en cada encabezado.
// reload:false porque CI_buscar repinta todo lo que depende del orden.
CI_sorter = window.CMG_initSort('<?= $rutaModulo ?>', (col, dir, sorts) => {
    CI_currentSort  = col;
    CI_currentDir   = dir;
    CI_currentSorts = sorts;
    CI_buscar(1);
}, { sorts: CI_currentSorts, multi: true, container: '.cargas-scroll', reload: false });
</script>
