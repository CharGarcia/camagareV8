<?php
/** @var array $perm */
/** @var array $rows */
/** @var int $total */
/** @var bool $esAprobador */
/** @var string $rutaModulo */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;
$rows    = $rows ?? [];
$total   = (int) ($total ?? 0);
$page    = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$vistaConfig = \App\Helpers\PreferenciasHelper::getPreferenciasVista($rutaModulo);
$buscar = $buscar ?? '';
$qsExport = 'b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol ?? '') . '&dir=' . urlencode($ordenDir ?? '');

echo \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig);
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 px-1">
    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-bank text-primary me-2"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="TR_abrirNuevo()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3 w-100">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre N°, cuenta origen,
            // creado por y observaciones (sin estado ni tipo) + botón embudo con el
            // modal de filtros + chips de los activos. Las claves (key) deben existir
            // en los mapas de TransferenciaLoteRepository::getListado(). Sin pestaña
            // Detalles. Al entrar se muestran todos los lotes (sin filtro de estado).
            $tL = 'Lote';
            $etqEstado = $etiquetasEstado ?? [];
            $opcionesEstado = array_merge(
                [['v' => implode(',', \App\repositories\modulos\TransferenciaLoteRepository::ESTADOS_NO_APROBADOS), 'l' => 'Sin aprobar (borrador y pendiente)']],
                array_map(fn($e) => ['v' => $e, 'l' => $etqEstado[$e] ?? $e], $estadosLote ?? [])
            );
            $opcIdNombre = fn(array $lista) => array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $lista);
            // Filas de 12 columnas:
            //   Lote:     [N° 4][Estado 4][Tipo 4]
            //   Pago:     [Fecha de pago 6][Monto total 6]
            //   Banco:    [Cuenta origen 4][Formato 4][Cantidad de pagos 4]
            //   Registro: [Fecha de registro 6][Creado por 6]
            $filtrosTransferencias = [
                ['tab' => $tL, 'key' => 'numero',   'label' => 'N° de lote',         'icon' => 'bi-hash',            'type' => 'number_range', 'grupo' => 'Lote',     'col' => 4],
                ['tab' => $tL, 'key' => 'estado',   'label' => 'Estado',             'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Lote',     'col' => 4, 'options' => $opcionesEstado],
                ['tab' => $tL, 'key' => 'tipo',     'label' => 'Tipo',               'icon' => 'bi-people',          'type' => 'select',       'grupo' => 'Lote',     'col' => 4, 'options' => [
                    ['v' => 'PROVEEDORES', 'l' => 'Proveedores'],
                    ['v' => 'NOMINA',      'l' => 'Nómina'],
                    ['v' => 'AMBOS',       'l' => 'Ambos'],
                ]],
                ['tab' => $tL, 'key' => 'fecha',    'label' => 'Fecha de pago',      'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Pago',     'col' => 6, 'atajos' => true],
                ['tab' => $tL, 'key' => 'monto',    'label' => 'Monto total',        'icon' => 'bi-cash-stack',      'type' => 'number_range', 'grupo' => 'Pago',     'col' => 6],
                ['tab' => $tL, 'key' => 'cuenta',   'label' => 'Cuenta origen',      'icon' => 'bi-bank',            'type' => 'select',       'grupo' => 'Banco',    'col' => 4, 'options' => $opcIdNombre($formasPagoOrigen ?? [])],
                ['tab' => $tL, 'key' => 'formato',  'label' => 'Formato del banco',  'icon' => 'bi-file-earmark-text', 'type' => 'select',     'grupo' => 'Banco',    'col' => 4, 'options' => $opcIdNombre($formatosTransferencia ?? [])],
                ['tab' => $tL, 'key' => 'pagos',    'label' => 'Cantidad de pagos',  'icon' => 'bi-list-ol',         'type' => 'number_range', 'grupo' => 'Banco',    'col' => 4],
                ['tab' => $tL, 'key' => 'registro', 'label' => 'Fecha de registro',  'icon' => 'bi-calendar-plus',   'type' => 'date_range',   'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tL, 'key' => 'usuario',  'label' => 'Creado por',         'icon' => 'bi-person-gear',     'type' => 'select',       'grupo' => 'Registro', 'col' => 6, 'options' => $opcIdNombre($usuariosLotes ?? [])],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorTR"></div>
            <input type="hidden" id="tr-buscar" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorTR',
                        hiddenInputId: 'tr-buscar',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de lotes de pago bancario',
                        inputWidth: 420,
                        extraId: 'fmExtraTR',   // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosTransferencias, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tr-tbody',
                        onApply: () => TR_buscar(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraTR" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero' => 'N°', 'fecha' => 'Fecha pago', 'tipo' => 'Tipo', 'banco' => 'Cuenta origen',
                    'monto' => 'Monto total', 'pagos' => 'Pagos', 'estado' => 'Estado', 'creado' => 'Creado por',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo);
                ?>
                <a id="tr-btn-pdf" href="<?= $urlBase ?>/exportPdf?<?= $qsExport ?>" target="_blank" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="tr-btn-excel" href="<?= $urlBase ?>/exportExcel?<?= $qsExport ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="tr-pag-info" class="text-muted small fw-medium"><?= $total ?> registros</span>
            <div class="btn-group btn-group-sm">
                <button type="button" id="tr-pag-prev" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="TR_buscar(TR_paginaActual - 1)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" id="tr-pag-next" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="TR_buscar(TR_paginaActual + 1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="transferencias-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2" data-col="numero">N°</th>
                        <th data-col="fecha">Fecha pago</th>
                        <th data-col="tipo">Tipo</th>
                        <th data-col="banco">Cuenta origen</th>
                        <th class="text-end" data-col="monto">Monto total</th>
                        <th class="text-center" data-col="pagos">Pagos</th>
                        <th class="text-center" data-col="estado">Estado</th>
                        <th data-col="creado">Creado por</th>
                    </tr>
                </thead>
                <tbody id="tr-tbody">
                    <?= $filasHtml ?? '' ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Lote (nuevo o existente) -->
<div class="modal fade" id="tr-modal-lote" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-bank me-2"></i><span id="tr-modal-titulo">Nuevo lote de pago bancario</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tr-detalle-msg"></div>

                <!-- Datos del lote -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tipo de lote</label>
                        <select id="tr-f-tipo" class="form-select form-select-sm">
                            <option value="AMBOS">Proveedores y Nómina</option>
                            <option value="PROVEEDORES">Proveedores</option>
                            <option value="NOMINA">Nómina</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Fecha de pago</label>
                        <input type="date" id="tr-f-fecha" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Cuenta de origen (empresa)</label>
                        <select id="tr-f-forma" class="form-select form-select-sm">
                            <option value="">Seleccione…</option>
                            <?php foreach (($formasPagoOrigen ?? []) as $fp): ?>
                                <option value="<?= (int) $fp['id'] ?>"><?= htmlspecialchars($fp['nombre']) ?><?= !empty($fp['banco_nombre']) ? ' — ' . htmlspecialchars($fp['banco_nombre']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Formato de archivo</label>
                        <select id="tr-f-banco" class="form-select form-select-sm">
                            <option value="">Seleccione…</option>
                            <?php foreach (($formatosTransferencia ?? []) as $f): ?>
                                <option value="<?= (int) $f['id'] ?>"><?= htmlspecialchars($f['nombre']) ?><?= !empty($f['nombre_banco']) ? ' (' . htmlspecialchars($f['nombre_banco']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Observaciones</label>
                        <input type="text" id="tr-f-obs" class="form-control form-control-sm" placeholder="Opcional">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="TR_mostrarPagosPendientes()">
                            <i class="bi bi-search me-1"></i>Mostrar pagos pendientes de transferencia
                        </button>
                    </div>
                </div>

                <!-- Bloque de pagos pendientes: disponible al crear y mientras el lote esté en Borrador -->
                <div id="tr-bloque-agregar-pagos" class="mb-3">
                    <div id="tr-selector" class="d-none mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold small mb-0">Pagos pendientes de transferencia</h6>
                            <div class="input-group input-group-sm" style="width: 260px;">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" id="tr-selector-buscar" class="form-control" placeholder="Buscar beneficiario…">
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                            <table class="table table-sm mb-0" style="font-size:.78rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:30px;"><input type="checkbox" class="form-check-input" id="tr-sel-todos" onchange="TR_marcarTodos(this)" title="Seleccionar todos"></th>
                                        <th>N° Egreso</th><th>Tipo</th><th>F. Emisión</th><th>Beneficiario</th><th>Banco</th><th>Tipo Cuenta</th><th>N° Cuenta</th><th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody id="tr-selector-body"><tr><td colspan="9" class="text-center text-muted py-3">Cargando…</td></tr></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" id="tr-btn-agregar-sel" class="btn btn-sm btn-primary d-none" onclick="TR_agregarSeleccionados()"><i class="bi bi-plus-lg me-1"></i>Agregar seleccionados</button>
                        </div>
                    </div>
                </div>

                <!-- Barra de acciones (solo visible con un lote ya guardado) -->
                <div id="tr-barra-acciones" class="d-none d-flex gap-1 align-items-center flex-wrap border-bottom pb-2 mb-3">
                    <button type="button" id="tr-btn-aprobar" class="btn btn-sm btn-outline-success d-none" onclick="TR_aprobar()" title="Aprobar"><i class="bi bi-check-circle me-1"></i>Aprobar</button>
                    <button type="button" id="tr-btn-rechazar" class="btn btn-sm btn-outline-danger d-none" onclick="TR_rechazar()" title="Rechazar"><i class="bi bi-x-circle me-1"></i>Rechazar</button>
                    <a href="#" id="tr-btn-descargar" class="btn btn-sm btn-outline-success d-none" title="Descargar archivo"><i class="bi bi-download me-1"></i>Descargar</a>
                    <button type="button" id="tr-btn-confirmar" class="btn btn-sm btn-outline-success d-none" onclick="TR_confirmarEnvio()" title="Confirmar envío al banco"><i class="bi bi-shield-check me-1"></i>Confirmar envío</button>
                </div>

                <div id="tr-detalle-cuerpo" class="small text-muted"></div>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" id="tr-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="TR_eliminar()"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    <?php endif; ?>
                    <button type="button" id="tr-btn-anular" class="btn btn-sm btn-outline-dark d-none" onclick="TR_anular()" title="Anular lote"><i class="bi bi-slash-circle me-1"></i>Anular</button>
                </div>
                <div>
                    <button type="button" id="tr-btn-enviar-aprobacion" class="btn btn-outline-primary btn-sm d-none" onclick="TR_enviarAprobacion()"><i class="bi bi-send-check me-1"></i>Enviar a aprobación</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" id="tr-btn-generar" class="btn btn-primary btn-sm d-none" onclick="TR_generarArchivo()" title="Generar archivo"><i class="bi bi-file-earmark-arrow-down me-1"></i>Generar archivo</button>
                    <?php if (!empty($perm['crear']) || !empty($perm['actualizar'])): ?>
                        <button type="button" id="tr-btn-guardar" class="btn btn-primary btn-sm px-4 d-none" onclick="TR_guardarCabecera()"><i class="bi bi-check2-circle me-1"></i>Guardar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const TR_URL = '<?= $urlBase ?>';
// Valores iniciales (del renderizado del listado); TR_cargarConfigAprobacion() los
// refresca cada vez que se abre el modal, por si la config cambió mientras tanto.
let TR_ES_APROBADOR  = <?= !empty($esAprobador) ? 'true' : 'false' ?>;
const TR_ES_SUPERADMIN = <?= !empty($esSuperAdmin) ? 'true' : 'false' ?>;
const TR_ID_USUARIO    = <?= (int) ($idUsuarioActual ?? 0) ?>;
let TR_APROBADORES   = <?= json_encode(array_values($aprobadoresNombres ?? []), JSON_UNESCAPED_UNICODE) ?>;
let TR_currentSort = '<?= $ordenCol ?? 'numero' ?>';
let TR_currentDir  = '<?= $ordenDir ?? 'DESC' ?>';
let TR_paginaActual = <?= (int) $page ?>;
</script>
<script src="<?= $base ?>/js/modulos/transferencias.js?v=<?= asset_ver('/js/modulos/transferencias.js') ?>"></script>
