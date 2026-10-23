<?php
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
/** @var array $provincias */
/** @var array $tiposFirma */

$base    = BASE_URL;
$urlBase = $base . '/modulos/firmas_electronicas';
$from    = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to      = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .firmas-header { flex-shrink: 0; }

    .firmas-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .firmas-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .firma-row { cursor: pointer; }
    .firma-row:hover { background-color: rgba(0, 0, 0, .04); }

    /* Panel solicitudes */
    .sol-badge-pendiente  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .sol-badge-completado { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .sol-badge-expirado   { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
    .sol-badge-cancelado  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="firmas-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-pen-fill me-2 text-primary"></i>Firmas Electrónicas</h5>
    <div class="d-flex gap-2">
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalFirmaCrear()">
                <i class="bi bi-plus-lg me-1"></i> Nueva
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- ── Modal: Enlaces enviados ─────────────────────────────── -->
<div class="modal fade" id="modalEnlacesEnviados" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-3">
            <div class="modal-header py-2 px-3">
                <h6 class="modal-title fw-semibold"><i class="bi bi-link-45deg me-2 text-secondary"></i>Enlaces enviados a clientes</h6>
                <div class="d-flex gap-2 align-items-center ms-auto">
                    <button type="button" class="btn btn-success btn-sm px-3" onclick="abrirModalEnviarFormulario()">
                        <i class="bi bi-send me-1"></i> Enviar link
                    </button>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0">
            <table class="table table-sm table-hover mb-0 align-middle small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Destino</th>
                        <th>Nombre</th>
                        <th class="text-center">Estado</th>
                        <th>Enviado</th>
                        <th>Expira</th>
                        <th>Firma generada</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodySolicitudes">
                    <tr><td colspan="7" class="text-center py-3 text-muted">Cargando…</td></tr>
                </tbody>
            </table>
            </div>
            <div class="modal-footer py-2 px-3 justify-content-between">
                <span class="small text-muted" id="infoSolicitudes"></span>
                <div class="btn-group btn-group-sm" id="pagSolicitudes"></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Tabla principal ─────────────────────────────────────── -->
<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador: texto libre sobre las columnas del listado (sin sugerencias) +
            // botón embudo que abre el modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de FirmaElectronicaRepository::getListado().
            // Una sola pestaña, sin "Detalles": los adjuntos son archivos (no se buscan
            // rutas ni nombres de archivo) y el formulario enviado vive en su propio panel.
            // Ningún filtro expone datos sensibles (código dactilar, nacimiento, dirección).
            $opcionesFiltro  = $opcionesFiltro ?? [];
            $opcionesProducto = array_map(static fn($p) => ['v' => (string) $p['id'], 'l' => (string) $p['nombre']], $opcionesFiltro['productos'] ?? []);
            $opcionesUsuario  = array_map(static fn($u) => ['v' => (string) $u['id'], 'l' => (string) $u['nombre']], $opcionesFiltro['usuarios'] ?? []);
            // Filas de 12 columnas:
            //   Firma:   [Fecha de registro 6][Estado 3][Pago 3]
            //            [Factura 4][Tipo de firma 4][Usuario que registró 4]
            //            [Caducidad 6][Vigencia 6]
            //   Titular: [Nombres 4][Apellidos 4][Identificación 4]
            //            [Tipo de identificación 4][Tipo de persona 4][Con RUC 4]
            //            [Teléfono 4][Correo 4][Empresa (RUC / razón social) 4]
            $filtrosFirmas = [
                ['key' => 'fecha',               'label' => 'Fecha de registro',      'icon' => 'bi-calendar-event',      'type' => 'date_range', 'grupo' => 'Firma',   'col' => 6, 'atajos' => true],
                ['key' => 'estado',              'label' => 'Estado del trámite',     'icon' => 'bi-flag',                'type' => 'select',     'grupo' => 'Firma',   'col' => 3, 'options' => [
                    ['v' => 'borrador',      'l' => 'Borrador (cliente completando)'],
                    ['v' => 'pendiente',     'l' => 'Pendiente de pago'],
                    ['v' => 'por_registrar', 'l' => 'Por registrar en Uanataca'],
                    ['v' => 'en_proceso',    'l' => 'En trámite'],
                    ['v' => 'observada',     'l' => 'Observada'],
                    ['v' => 'emitida',       'l' => 'Emitida'],
                    ['v' => 'cancelada',     'l' => 'Cancelada'],
                ]],
                ['key' => 'estado_pago',         'label' => 'Estado del pago',        'icon' => 'bi-credit-card-2-front', 'type' => 'select',     'grupo' => 'Firma',   'col' => 3, 'options' => [
                    ['v' => 'pendiente',   'l' => 'Pendiente'],
                    ['v' => 'en_revision', 'l' => 'Comprobante en revisión'],
                    ['v' => 'confirmado',  'l' => 'Confirmado'],
                    ['v' => 'rechazado',  'l' => 'Rechazado'],
                ]],
                ['key' => 'factura',             'label' => 'Factura',                'icon' => 'bi-receipt',             'type' => 'select',     'grupo' => 'Firma',   'col' => 4, 'options' => [
                    // Estados reales de ventas_cabecera (autorizado/anulado, en masculino)
                    ['v' => 'por_facturar', 'l' => 'Por facturar'],
                    ['v' => 'borrador',     'l' => 'Borrador'],
                    ['v' => 'autorizado',   'l' => 'Facturado (autorizado)'],
                    ['v' => 'anulado',      'l' => 'Anulado'],
                ]],
                ['key' => 'id_producto',         'label' => 'Tipo de firma',          'icon' => 'bi-pen',                 'type' => 'select',     'grupo' => 'Firma',   'col' => 4, 'options' => $opcionesProducto],
                ['key' => 'usuario',             'label' => 'Usuario que registró',   'icon' => 'bi-person-badge',        'type' => 'select',     'grupo' => 'Firma',   'col' => 4, 'options' => $opcionesUsuario],
                ['key' => 'caducidad',           'label' => 'Fecha de vencimiento',   'icon' => 'bi-calendar-x',          'type' => 'date_range', 'grupo' => 'Firma',   'col' => 6],
                ['key' => 'vigencia',            'label' => 'Vigencia',               'icon' => 'bi-hourglass-split',     'type' => 'select',     'grupo' => 'Firma',   'col' => 6, 'options' => [
                    ['v' => 'vigente',    'l' => 'Vigente (más de 30 días)'],
                    ['v' => 'por_vencer', 'l' => 'Por vencer (30 días o menos)'],
                    ['v' => 'vencida',    'l' => 'Vencida'],
                    ['v' => 'sin_fecha',  'l' => 'Sin fecha de vencimiento'],
                ]],
                ['key' => 'nombres',             'label' => 'Nombres',                'icon' => 'bi-person',              'type' => 'text',       'grupo' => 'Titular', 'col' => 4],
                ['key' => 'apellidos',           'label' => 'Apellidos',              'icon' => 'bi-person',              'type' => 'text',       'grupo' => 'Titular', 'col' => 4],
                ['key' => 'identificacion',      'label' => 'Identificación',         'icon' => 'bi-card-text',           'type' => 'text',       'grupo' => 'Titular', 'col' => 4],
                ['key' => 'tipo_identificacion', 'label' => 'Tipo de identificación', 'icon' => 'bi-credit-card',         'type' => 'select',     'grupo' => 'Titular', 'col' => 4, 'options' => [
                    ['v' => 'cedula',    'l' => 'Cédula'],
                    ['v' => 'ruc',       'l' => 'RUC'],
                    ['v' => 'pasaporte', 'l' => 'Pasaporte'],
                ]],
                ['key' => 'tipo_persona',        'label' => 'Tipo de persona',        'icon' => 'bi-people',              'type' => 'select',     'grupo' => 'Titular', 'col' => 4, 'options' => [
                    ['v' => 'natural',  'l' => 'Natural'],
                    ['v' => 'juridica', 'l' => 'Jurídica'],
                ]],
                ['key' => 'con_ruc',             'label' => 'Con RUC',                'icon' => 'bi-building',            'type' => 'select',     'grupo' => 'Titular', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Sí'],
                    ['v' => 'no', 'l' => 'No'],
                ]],
                ['key' => 'telefono',            'label' => 'Teléfono',               'icon' => 'bi-telephone',           'type' => 'text',       'grupo' => 'Titular', 'col' => 4],
                ['key' => 'correo',              'label' => 'Correo',                 'icon' => 'bi-envelope',            'type' => 'text',       'grupo' => 'Titular', 'col' => 4],
                ['key' => 'empresa',             'label' => 'Empresa (RUC o nombre)', 'icon' => 'bi-briefcase',           'type' => 'text',       'grupo' => 'Titular', 'col' => 4],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorFIRMAS"></div>
            <input type="hidden" id="buscarFirma" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    // Instancia global: abrirFirmaPorNombre() limpia la búsqueda a través de ella.
                    window.FIRMAS_fm = new FiltrosModal({
                        containerId: 'fmBuscadorFIRMAS',
                        hiddenInputId: 'buscarFirma',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de firmas electrónicas',
                        inputWidth: 420,
                        extraId: 'fmExtraFIRMAS',   // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosFirmas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyFirmas',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    });
                    window.FIRMAS_fm.init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraFIRMAS" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombres'               => 'Nombres',
                    'numero_identificacion' => 'Identificación',
                    'nombre_producto'       => 'Tipo Firma',
                    'telefono'              => 'Teléfono',
                    'correo'                => 'Correo',
                    'estado_pago'           => 'Pago',
                    'factura_estado'        => 'Factura',
                    'estado'                => 'Estado',
                    'fecha_caducidad'       => 'Vencimiento',
                    'created_at'            => 'Fecha',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                ?>
                <a id="btnFirmaPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="btnFirmaExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
                <?php if ($perm['crear']): ?>
                    <button type="button" class="btn btn-outline-secondary" title="Enlaces enviados a clientes" onclick="abrirEnlacesEnviados()"><i class="bi bi-link-45deg"></i></button>
                    <button type="button" class="btn btn-outline-success" title="Enviar enlace al cliente (WhatsApp / correo)" onclick="abrirModalEnviarFormulario()"><i class="bi bi-send"></i><span class="d-none d-md-inline"> Enviar link</span></button>
                <?php endif; ?>
                <?php if ($perm['actualizar']): ?>
                    <button type="button" class="btn btn-outline-secondary" title="Configuración del módulo" onclick="abrirConfigFirmas()"><i class="bi bi-gear"></i></button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="firmasPaginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="firmasPaginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaFirmas(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaFirmas(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="firmas-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header-f" data-sort="nombres" data-col="nombres" role="button">Nombres <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header-f" data-sort="numero_identificacion" data-col="numero_identificacion" role="button">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header-f" data-sort="nombre_producto" data-col="nombre_producto" role="button">Tipo Firma <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header-f" data-sort="telefono" data-col="telefono" role="button">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header-f" data-sort="correo" data-col="correo" role="button">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header-f" data-sort="estado_pago" data-col="estado_pago" role="button">Pago <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header-f" data-sort="factura_estado" data-col="factura_estado" role="button">Factura <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header-f" data-sort="estado" data-col="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header-f" data-sort="fecha_caducidad" data-col="fecha_caducidad" role="button">Vencimiento <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end pe-3 sortable-header-f" data-sort="created_at" data-col="created_at" role="button">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyFirmas">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-pen fs-3 d-block mb-2"></i>No se encontraron firmas electrónicas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $estadosFirma = [
                            'borrador' => ['secondary', 'Borrador'], 'pendiente' => ['warning', 'Pendiente de pago'],
                            'por_registrar' => ['info', 'Por registrar'], 'en_proceso' => ['primary', 'En trámite'],
                            'observada' => ['danger', 'Observada'], 'emitida' => ['success', 'Emitida'], 'cancelada' => ['dark', 'Cancelada'],
                        ];
                        $estadosPago = [
                            'pendiente' => ['warning', 'Pendiente'], 'en_revision' => ['info', 'En revisión'],
                            'confirmado' => ['success', 'Confirmado'], 'rechazado' => ['danger', 'Rechazado'],
                        ];
                        foreach ($rows as $row):
                            [$colE, $lblE] = $estadosFirma[$row['estado'] ?? 'pendiente'] ?? ['secondary', ucfirst((string) ($row['estado'] ?? ''))];
                            $badgeEstado = "<span class=\"badge bg-{$colE} bg-opacity-10 text-{$colE} border border-{$colE} border-opacity-25\">{$lblE}</span>";
                            [$colP, $lblP] = $estadosPago[$row['estado_pago'] ?? 'pendiente'] ?? ['secondary', ucfirst((string) ($row['estado_pago'] ?? ''))];
                            $badgePago = "<span class=\"badge bg-{$colP} bg-opacity-10 text-{$colP} border border-{$colP} border-opacity-25\">{$lblP}</span>";
                            $factElim  = ($row['factura_eliminada'] ?? false) === true;
                            $factId    = $row['id_factura'] ? (int)$row['id_factura'] : null;
                            $factEst   = $row['factura_estado'] ?? null;
                            $badgeFactura = !$factId || $factElim
                                ? '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.7rem;">Por Facturar</span>'
                                : match($factEst) {
                                    'borrador'   => '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:.7rem;">Borrador</span>',
                                    'autorizado' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.7rem;"><i class="bi bi-check-circle me-1"></i>Facturado</span>',
                                    'anulado'    => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.7rem;">Anulada</span>',
                                    default      => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.7rem;">' . htmlspecialchars(ucfirst($factEst ?? '?')) . '</span>',
                                };
                            $fCad = $row['fecha_caducidad'] ?? null;
                            $diff = $fCad ? (int)((strtotime($fCad) - time()) / 86400) : null;
                            $badgeCaducidad = $fCad === null
                                ? '<span class="text-muted small">-</span>'
                                : ($diff < 0
                                    ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><i class="bi bi-exclamation-circle me-1"></i>' . date('d-m-Y', strtotime($fCad)) . '</span>'
                                    : ($diff <= 30
                                        ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><i class="bi bi-clock me-1"></i>' . date('d-m-Y', strtotime($fCad)) . '</span>'
                                        : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">' . date('d-m-Y', strtotime($fCad)) . '</span>'
                                    ));
                        ?>
                            <tr class="firma-row" onclick="abrirModalFirmaEditar(this)"
                                data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="nombres">
                                    <?= htmlspecialchars(trim(($row['nombres'] ?? '') . ' ' . ($row['apellidos'] ?? ''))) ?>
                                </td>
                                <td data-col="numero_identificacion">
                                    <code class="text-secondary"><?= htmlspecialchars($row['numero_identificacion'] ?? '') ?></code>
                                </td>
                                <td data-col="nombre_producto" class="small text-muted"><?= htmlspecialchars($row['nombre_producto'] ?? '-') ?></td>
                                <td data-col="telefono"><?= htmlspecialchars($row['telefono'] ?? '-') ?></td>
                                <td data-col="correo" class="small"><?= htmlspecialchars($row['correo'] ?? '-') ?></td>
                                <td class="text-center" data-col="estado_pago"><?= $badgePago ?></td>
                                <td class="text-center" data-col="factura_estado"><?= $badgeFactura ?></td>
                                <td class="text-center" data-col="estado"><?= $badgeEstado ?></td>
                                <td class="text-center" data-col="fecha_caducidad"><?= $badgeCaducidad ?></td>
                                <td class="text-end pe-3 small text-muted" data-col="created_at"><?= $row['created_at'] ?? '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Modal: Enviar Formulario ───────────────────────────── -->
<div class="modal fade" id="modalEnviarFormulario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content rounded-3 position-relative">
            <!-- Loader mientras se envía (cubre el modal para que no se toque nada a medio envío) -->
            <div id="solLoaderEnvio" class="position-absolute top-0 start-0 w-100 h-100 rounded-3 d-none flex-column align-items-center justify-content-center text-center px-4"
                 style="z-index:10;background:rgba(255,255,255,.92);">
                <div class="spinner-border text-success mb-3" style="width:2.6rem;height:2.6rem;" role="status"></div>
                <div class="fw-semibold" id="solLoaderTitulo">Enviando enlace…</div>
                <div class="small text-muted mt-1" id="solLoaderDetalle"></div>
            </div>
            <div class="modal-header py-2 px-3">
                <h6 class="modal-title fw-semibold"><i class="bi bi-send me-2 text-success"></i>Enviar enlace al cliente</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-3 py-3">
                <p class="small text-muted mb-2">El cliente llena sus datos, sube sus documentos y paga desde el enlace. Con el mismo enlace sigue el estado de su firma.</p>
                <input type="hidden" id="solIdFirma" value="">
                <div class="mb-2">
                    <label class="form-label form-label-sm fw-medium">Nombre del cliente <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" class="form-control form-control-sm" id="solNombreDestino" placeholder="Ej: Juan Pérez">
                </div>
                <div class="mb-2">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" id="solPorCorreo" checked>
                        <label class="form-check-label small fw-medium" for="solPorCorreo"><i class="bi bi-envelope me-1"></i>Enviar por correo</label>
                    </div>
                    <input type="email" class="form-control form-control-sm" id="solCorreoDestino" placeholder="cliente@ejemplo.com">
                </div>
                <div class="mb-0">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" id="solPorWhatsapp">
                        <label class="form-check-label small fw-medium" for="solPorWhatsapp"><i class="bi bi-whatsapp me-1 text-success"></i>Enviar por WhatsApp</label>
                    </div>
                    <input type="tel" class="form-control form-control-sm" id="solTelefonoDestino" placeholder="09XXXXXXXX">
                    <div class="form-text small">Con la plantilla <code>solicitud_firma</code> aprobada en Meta se envía sola; si no, podrá abrir WhatsApp con el mensaje listo.</div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnConfirmarEnviarFormulario" onclick="confirmarEnviarFormulario()">
                    <i class="bi bi-send me-1"></i> Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($perm['actualizar']): $cfgM = $configModulo ?? []; ?>
<!-- ── Modal: Configuración del módulo ─────────────────────── -->
<div class="modal fade" id="modalConfigFirmas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
        <div class="modal-content rounded-3">
            <div class="modal-header py-2 px-3">
                <h6 class="modal-title fw-semibold"><i class="bi bi-gear me-2 text-secondary"></i>Configuración de firmas electrónicas</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <form id="formConfigFirmas" onsubmit="return false;">
            <div class="modal-body px-3 py-3">
                <div id="alertConfigFirmas" class="alert d-none mb-3 py-2 small"></div>
                <div class="d-flex align-items-center mb-3">
                    <ul class="nav nav-tabs flex-grow-1" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active fw-medium py-1" id="tab-cfg-general-btn" data-bs-toggle="tab" data-bs-target="#tab-cfg-general" type="button">
                                <i class="bi bi-sliders me-1"></i>General
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-medium py-1" id="tab-cfg-uanataca-btn" data-bs-toggle="tab" data-bs-target="#tab-cfg-uanataca" type="button">
                                <i class="bi bi-patch-check me-1"></i>Uanataca
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-medium py-1" id="tab-cfg-avisos-btn" data-bs-toggle="tab" data-bs-target="#tab-cfg-avisos" type="button">
                                <i class="bi bi-bell me-1"></i>Avisos
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-medium py-1" id="tab-cfg-vigencia-btn" data-bs-toggle="tab" data-bs-target="#tab-cfg-vigencia" type="button">
                                <i class="bi bi-calendar-check me-1"></i>Vigencia
                            </button>
                        </li>
                    </ul>
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas(['tab-cfg-uanataca-btn' => 'Uanataca', 'tab-cfg-avisos-btn' => 'Avisos', 'tab-cfg-vigencia-btn' => 'Vigencia'], \App\Helpers\PreferenciasHelper::getPreferenciasVista('firmas_electronicas'), 'firmas_electronicas') ?>
                </div>
                <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-cfg-general" role="tabpanel">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1">Serie de facturación</label>
                        <select class="form-select form-select-sm" name="id_punto_emision">
                            <option value="0">La primera serie activa</option>
                            <?php foreach ($seriesFact ?? [] as $se): ?>
                                <option value="<?= (int) $se['id'] ?>" <?= (int) ($cfgM['id_punto_emision'] ?? 0) === (int) $se['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($se['serie'] . ($se['establecimiento'] !== '' ? ' — ' . $se['establecimiento'] : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1">Usuario responsable</label>
                        <select class="form-select form-select-sm" name="id_usuario_responsable">
                            <option value="0">Yo (quien guarda)</option>
                            <?php foreach ($usuariosEmp ?? [] as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= (int) ($cfgM['id_usuario_responsable'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Dueño de las solicitudes del enlace fijo y de sus facturas.</div>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="payphone_activo" id="cfgPayphone" value="1" <?= !empty($cfgM['payphone_activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="cfgPayphone">El cliente puede pagar con tarjeta (Payphone)</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="transferencia_activa" id="cfgTransfer" value="1" <?= !empty($cfgM['transferencia_activa']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="cfgTransfer">El cliente puede pagar por transferencia y subir el comprobante</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted mb-1">Datos para la transferencia (los ve el cliente)</label>
                        <textarea class="form-control form-control-sm" name="datos_transferencia" rows="3" placeholder="Banco Pichincha · Cta. corriente 2200000000 · A nombre de … · RUC …"><?= htmlspecialchars($cfgM['datos_transferencia'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="enlace_fijo_activo" id="cfgEnlaceFijo" value="1" <?= !empty($cfgM['enlace_fijo_activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="cfgEnlaceFijo">Enlace público fijo (para la web, redes o un QR)</label>
                        </div>
                        <div class="input-group input-group-sm mt-1">
                            <input type="text" class="form-control" id="cfgUrlEnlaceFijo" readonly value="<?= htmlspecialchars($urlEnlaceFijo ?? '') ?>" placeholder="Se genera al guardar">
                            <button type="button" class="btn btn-outline-secondary" title="Copiar" onclick="copiarTextoFirma(document.getElementById('cfgUrlEnlaceFijo').value)"><i class="bi bi-clipboard"></i></button>
                            <button type="button" class="btn btn-outline-danger" title="Generar un enlace nuevo (el anterior deja de funcionar)" onclick="guardarConfigFirmas(true)"><i class="bi bi-arrow-repeat"></i></button>
                        </div>
                        <div class="form-text small">Cualquier persona con este enlace puede iniciar una solicitud (máximo 5 por hora desde la misma conexión).</div>
                    </div>
                </div>
                </div><!-- /tab-cfg-general -->

                <!-- Proveedor de firmas: Uanataca (API Precompra). Otro proveedor con convenio = otra pestaña igual. -->
                <div class="tab-pane fade" id="tab-cfg-uanataca" role="tabpanel">
                <div class="row g-2">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="uanataca_activo" id="cfgUanActivo" value="1" <?= !empty($cfgM['uanataca_activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="cfgUanActivo">Enviar las solicitudes a Uanataca por API</label>
                        </div>
                        <div class="form-text small mb-2">Credenciales de distribuidor (Precompra) que entrega Uanataca. Cada solicitud consume Uanacréditos.</div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold text-muted mb-1">Ambiente</label>
                        <select class="form-select form-select-sm" name="uanataca_ambiente">
                            <option value="pruebas" <?= ($cfgM['uanataca_ambiente'] ?? 'pruebas') === 'pruebas' ? 'selected' : '' ?>>Pruebas</option>
                            <option value="produccion" <?= ($cfgM['uanataca_ambiente'] ?? '') === 'produccion' ? 'selected' : '' ?>>Producción</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold text-muted mb-1">Usuario</label>
                        <input type="text" class="form-control form-control-sm" name="uanataca_usuario" autocomplete="off" value="<?= htmlspecialchars($cfgM['uanataca_usuario'] ?? '') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold text-muted mb-1">Clave</label>
                        <input type="password" class="form-control form-control-sm" name="uanataca_clave" autocomplete="new-password"
                               placeholder="<?= !empty($cfgM['uanataca_tiene_clave']) ? 'Guardada (vacío = no cambiar)' : '' ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="uanataca_envio_automatico" id="cfgUanAuto" value="1" <?= !empty($cfgM['uanataca_envio_automatico']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="cfgUanAuto">Enviar automáticamente al confirmarse el pago</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="uanataca_exigir_correo" id="cfgUanCorreo" value="1" <?= !isset($cfgM['uanataca_exigir_correo']) || !empty($cfgM['uanataca_exigir_correo']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="cfgUanCorreo">Esperar a que el cliente confirme su correo antes del envío automático</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="probarUanataca()"><i class="bi bi-plug me-1"></i>Probar conexión</button>
                        <span class="small text-muted" id="cfgUanResultado"></span>
                    </div>

                    <!-- Productos de Uanataca: enlace de cada firma con su producto en Uanataca -->
                    <div class="col-12 mt-3 pt-2 border-top">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="small fw-bold"><i class="bi bi-diagram-2 me-1 text-secondary"></i>Productos de Uanataca</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto py-0" title="Volver a consultar el catálogo de Uanataca" onclick="cargarProductosUanataca()"><i class="bi bi-arrow-clockwise"></i></button>
                        </div>
                        <div class="form-text small mb-2">Elija a qué producto de Uanataca corresponde cada firma que vende. Sin enlace, esa firma no se puede enviar por API.</div>
                        <div id="alertMapeoUanataca" class="alert d-none mb-2 py-2 small"></div>
                        <div style="max-height:240px;overflow:auto;">
                            <table class="table table-sm align-middle small mb-0">
                                <thead class="table-light"><tr><th>Firma en el sistema</th><th>Producto de Uanataca</th></tr></thead>
                                <tbody id="tbodyMapeoUanataca"><tr><td colspan="2" class="text-muted text-center py-3">Se carga al abrir esta pestaña.</td></tr></tbody>
                            </table>
                        </div>
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnGuardarMapeoUanataca" onclick="guardarMapeoUanataca()" disabled><i class="bi bi-check-lg me-1"></i>Guardar productos</button>
                        </div>
                    </div>
                </div>
                </div><!-- /tab-cfg-uanataca -->

                <!-- Avisos de vencimiento al cliente -->
                <div class="tab-pane fade" id="tab-cfg-avisos" role="tabpanel">
                <div class="row g-2">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="avisos_vencimiento_activo" id="cfgAvisos" value="1" <?= !empty($cfgM['avisos_vencimiento_activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="cfgAvisos">Avisar al cliente que su firma va a vencer</label>
                        </div>
                        <div class="form-text small mb-2">Días antes del vencimiento, separados por coma (0 = el mismo día). Cada aviso lleva un enlace para renovar con sus datos ya llenos.</div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-envelope me-1"></i>Por correo</label>
                        <input type="text" class="form-control form-control-sm" name="avisos_dias_correo" value="<?= htmlspecialchars($cfgM['avisos_dias_correo'] ?? '30,15,5,0') ?>" placeholder="30,15,5,0">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-whatsapp me-1"></i>Por WhatsApp</label>
                        <input type="text" class="form-control form-control-sm" name="avisos_dias_whatsapp" value="<?= htmlspecialchars($cfgM['avisos_dias_whatsapp'] ?? '30,5,0') ?>" placeholder="30,5,0">
                        <div class="form-text small">Requiere la plantilla <code>renovacion_firma_electronica</code> aprobada en Meta.</div>
                    </div>

                </div>
                </div><!-- /tab-cfg-avisos -->

                <!-- Vigencia de cada tipo de firma (para la fecha de vencimiento) -->
                <div class="tab-pane fade" id="tab-cfg-vigencia" role="tabpanel">
                    <div>
                        <div class="form-text small mb-2">Con ella se calcula la fecha de vencimiento al elegir la firma y al emitirla. Vacío = la que dice el nombre del producto.</div>
                        <?php $unidadesVig = \App\Rules\modulos\FirmaElectronicaRules::UNIDADES_VIGENCIA; ?>
                        <table class="table table-sm align-middle small mb-0">
                            <tbody>
                            <?php foreach ($tiposFirma ?? [] as $tf):
                                $ded = $tf['vigencia_deducida'] ?? null;
                                $esAjuste = ($tf['vigencia_origen'] ?? '') === 'ajuste'; ?>
                                <tr>
                                    <td><?= htmlspecialchars($tf['nombre']) ?></td>
                                    <td style="width:90px;">
                                        <input type="number" min="1" max="3650" class="form-control form-control-sm" name="vig[<?= (int) $tf['id'] ?>][cantidad]"
                                               value="<?= $esAjuste ? (int) $tf['vigencia_cantidad'] : '' ?>" placeholder="<?= $ded ? (int) $ded['cantidad'] : '?' ?>">
                                    </td>
                                    <td style="width:110px;">
                                        <select class="form-select form-select-sm" name="vig[<?= (int) $tf['id'] ?>][unidad]">
                                            <?php $uSel = $esAjuste ? $tf['vigencia_unidad'] : ($ded['unidad'] ?? 'anios');
                                            foreach ($unidadesVig as $k => $lbl): ?>
                                                <option value="<?= $k ?>" <?= $uSel === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="text-muted" style="width:150px;">
                                        <?php $singular = ['dias' => 'día', 'meses' => 'mes', 'anios' => 'año']; ?>
                                        <?= $ded ? 'Nombre: ' . (int) $ded['cantidad'] . ' ' . ((int) $ded['cantidad'] === 1 ? $singular[$ded['unidad']] : $unidadesVig[$ded['unidad']]) : '<span class="text-warning">El nombre no la indica</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($tiposFirma)): ?>
                                <tr><td class="text-muted">No hay productos en una categoría «FIRMA».</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div><!-- /tab-cfg-vigencia -->
                </div><!-- /tab-content -->
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnGuardarConfigFirmas" onclick="guardarConfigFirmas(false)"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    window.BASE_URL = '<?= $base ?>';
    window.FIRMAS_FORMAS_COBRO = <?= json_encode(array_values($formasCobro ?? []), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    window.FIRMAS_PERM = <?= json_encode(['actualizar' => !empty($perm['actualizar']), 'crear' => !empty($perm['crear'])]) ?>;
</script>
<?php include 'modal_firma.php'; ?>

<script>
(function () {
    'use strict';
    const urlBase   = '<?= $urlBase ?>';
    const inputBuscar = document.getElementById('buscarFirma');
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir  = '<?= $ordenDir ?>';
    window.currentPage = <?= $page ?>;

    let timerId;

    function debounce(func, delay = 350) {
        return (...args) => {
            clearTimeout(timerId);
            timerId = setTimeout(() => func.apply(this, args), delay);
        };
    }

    window.cambiarPaginaFirmas = (n) => window.fetchSearch(n);
    // El modal de la ficha refresca el listado con este nombre.
    window.fetchSearchFirmas = () => window.fetchSearch(window.currentPage || 1);

    // "Enviar enlace" se abre también desde la ficha (modal sobre modal): la regla global
    // .modal { z-index:5060 !important } lo dejaría detrás, así que se sube inline.
    ['modalEnviarFormulario'].forEach(id => document.getElementById(id)?.addEventListener('shown.bs.modal', function () {
        if (!window.CMG_modalSobreOtro || !CMG_modalSobreOtro(this)) return;
        this.style.setProperty('z-index', '5080', 'important');
        const fondos = document.querySelectorAll('.modal-backdrop');
        fondos[fondos.length - 1]?.style.setProperty('z-index', '5075', 'important');
    }));

    window.fetchSearch = async (page = 1) => {
        const term = inputBuscar ? inputBuscar.value.trim() : '';
        const uri  = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa en vez de
        // vaciarse. Aquí también para paginar y ordenar, que llaman a esta función directo.
        const tbody = document.getElementById('tbodyFirmas');
        if (tbody) tbody.classList.add('fm-cargando-target');
        try {
            const resp = await fetch(uri);
            const data = await resp.json();
            if (data.ok) {
                window.currentPage = page;
                document.getElementById('tbodyFirmas').innerHTML              = data.rows;
                document.getElementById('firmasPaginationContainer').innerHTML = data.pagination;
                document.getElementById('firmasPaginationInfo').textContent    = data.info;
                document.getElementById('btnFirmaPdf').href                   = data.pdf_url;
                document.getElementById('btnFirmaExcel').href                 = data.excel_url;

                document.querySelectorAll('.sortable-header-f').forEach(th => {
                    const icon = th.querySelector('i');
                    if (!icon) return;
                    if (th.dataset.sort === window.currentSort) {
                        icon.className = window.currentDir.toLowerCase() === 'asc'
                            ? 'bi bi-sort-alpha-down text-primary ms-1'
                            : 'bi bi-sort-alpha-up text-primary ms-1';
                    } else {
                        icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    }
                });
            }
        } catch (e) {
            console.error('Error en búsqueda de firmas:', e);
        } finally {
            if (tbody) tbody.classList.remove('fm-cargando-target');
        }
    };

    document.querySelectorAll('.sortable-header-f').forEach(h => {
        h.addEventListener('click', () => {
            const f = h.dataset.sort;
            if (window.currentSort === f) window.currentDir = window.currentDir.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
            else { window.currentSort = f; window.currentDir = 'ASC'; }
            if (typeof window.guardarOrdenacionVista === 'function') window.guardarOrdenacionVista('firmas_electronicas', window.currentSort, window.currentDir);
            window.fetchSearch(1);
        });
    });

    if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => window.fetchSearch(1), 400));

    // ── Enlaces enviados (modal) ───────────────────────────────
    const modalEnlaces = () => document.getElementById('modalEnlacesEnviados');
    const enlacesAbierto = () => modalEnlaces()?.classList.contains('show');
    let paginaSolicitudes = 1;

    window.abrirEnlacesEnviados = function () {
        bootstrap.Modal.getOrCreateInstance(modalEnlaces()).show();
        cargarSolicitudes(1);
    };

    window.copiarTextoFirma = async function (texto) {
        if (!texto) return;
        try { await navigator.clipboard.writeText(texto); }
        catch {
            const ta = document.createElement('textarea');
            ta.value = texto; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
        }
        if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copiado', showConfirmButton: false, timer: 1200 });
    };

    /** Abre el envío del enlace. Con datos de una firma, el enlace es el de esa firma. */
    window.abrirModalEnviarFormulario = function (firma = null) {
        document.getElementById('solIdFirma').value         = firma?.id || '';
        document.getElementById('solNombreDestino').value   = firma ? `${firma.nombres || ''} ${firma.apellidos || ''}`.trim() : '';
        document.getElementById('solCorreoDestino').value   = firma?.correo || '';
        document.getElementById('solTelefonoDestino').value = firma?.telefono || '';
        document.getElementById('solPorCorreo').checked     = true;
        document.getElementById('solPorWhatsapp').checked   = !!firma?.telefono;
        enviandoEnlace = false;
        const btn = document.getElementById('btnConfirmarEnviarFormulario');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
        mostrarLoaderEnvio(null);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEnviarFormulario')).show();
    };

    let enviandoEnlace = false;
    // A medio envío no se cierra el modal (Esc o clic afuera): el usuario no vería el resultado.
    document.getElementById('modalEnviarFormulario')?.addEventListener('hide.bs.modal', e => { if (enviandoEnlace) e.preventDefault(); });
    // Loader que cubre el modal "Enviar enlace" mientras se envía; null lo oculta.
    function mostrarLoaderEnvio(titulo, detalle = '') {
        const el = document.getElementById('solLoaderEnvio');
        el.classList.toggle('d-none', !titulo);
        el.classList.toggle('d-flex', !!titulo);
        if (!titulo) return;
        document.getElementById('solLoaderTitulo').textContent = titulo;
        document.getElementById('solLoaderDetalle').textContent = detalle;
    }
    // Resultado del envío (como en Facturas de Venta: SweetAlert). WhatsApp nunca abre otra
    // página por su cuenta: sin la plantilla aprobada en Meta, el usuario decide abrirlo.
    function mostrarResultadoEnvio(data) {
        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
        const lineas = [];
        if (data.correo === true)  lineas.push('<i class="bi bi-envelope-check text-success me-1"></i>Correo enviado.');
        if (data.correo === false) lineas.push(`<i class="bi bi-envelope-x text-danger me-1"></i>El correo no se pudo enviar: ${esc(data.correo_error)}`);
        if (data.whatsapp === 'api')   lineas.push('<i class="bi bi-whatsapp text-success me-1"></i>WhatsApp enviado.');
        if (data.whatsapp === 'wa_me') lineas.push('<i class="bi bi-whatsapp text-warning me-1"></i>WhatsApp no se envió solo (falta la plantilla <code>solicitud_firma</code> aprobada en Meta). Puede abrirlo con el mensaje listo.');
        const incompleto = data.correo === false || data.whatsapp === 'wa_me';
        Swal.fire({
            icon: incompleto ? 'warning' : 'success',
            title: incompleto ? 'Enlace creado' : '¡Enviado!',
            html: `<div class="text-start small">${lineas.map(l => `<div class="mb-1">${l}</div>`).join('')}</div>
                   <div class="input-group input-group-sm mt-3">
                       <input type="text" class="form-control" id="swalEnlaceFirma" readonly value="${esc(data.url)}">
                       <button type="button" class="btn btn-outline-secondary" id="swalCopiarEnlace" title="Copiar enlace"><i class="bi bi-clipboard"></i></button>
                   </div>`,
            showConfirmButton: data.whatsapp === 'wa_me',
            confirmButtonText: '<i class="bi bi-whatsapp me-1"></i>Abrir WhatsApp',
            confirmButtonColor: '#198754',
            showCancelButton: true,
            cancelButtonText: 'Cerrar',
            didOpen: () => {
                // Copia aquí mismo: copiarTextoFirma() muestra un toast que cerraría este aviso.
                document.getElementById('swalCopiarEnlace').addEventListener('click', async function () {
                    const input = document.getElementById('swalEnlaceFirma');
                    try { await navigator.clipboard.writeText(input.value); } catch { input.select(); document.execCommand('copy'); }
                    this.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                });
            },
        }).then(r => { if (r.isConfirmed && data.wa_me) window.open(data.wa_me, '_blank'); });
    }

    window.confirmarEnviarFormulario = async function () {
        if (enviandoEnlace) return;
        const btn    = document.getElementById('btnConfirmarEnviarFormulario');
        const porCorreo   = document.getElementById('solPorCorreo').checked;
        const porWhatsapp = document.getElementById('solPorWhatsapp').checked;
        if (!porCorreo && !porWhatsapp) {
            Swal.fire('Atención', 'Elija correo, WhatsApp o ambos.', 'warning');
            return;
        }

        enviandoEnlace = true;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';
        const canales = [porCorreo ? 'correo' : '', porWhatsapp ? 'WhatsApp' : ''].filter(Boolean).join(' y ');
        mostrarLoaderEnvio(`Enviando enlace por ${canales}…`,
            porCorreo ? 'El envío del correo puede tardar unos segundos. No cierre esta ventana.' : 'No cierre esta ventana.');

        try {
            const fd = new FormData();
            fd.append('nombre_destino', document.getElementById('solNombreDestino').value.trim());
            fd.append('correo_destino', document.getElementById('solCorreoDestino').value.trim());
            fd.append('telefono_destino', document.getElementById('solTelefonoDestino').value.trim());
            fd.append('id_firma', document.getElementById('solIdFirma').value);
            if (porCorreo) fd.append('por_correo', '1');
            if (porWhatsapp) fd.append('por_whatsapp', '1');
            // Tope en el navegador por si el servidor no responde (el SMTP tiene 20 s por intento).
            const resp = await fetch(`${urlBase}/enviarSolicitud`, { method: 'POST', body: fd, signal: AbortSignal.timeout(90000) });
            const data = await resp.json();

            if (data.ok) {
                if (enlacesAbierto()) cargarSolicitudes(paginaSolicitudes);
                if (window.FIRMA_recargarTramite) window.FIRMA_recargarTramite();
                // El enlace ya se creó: no se reactiva Enviar (sigue disabled) para no crear otro con un segundo clic.
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Enviado';
                enviandoEnlace = false;
                mostrarLoaderEnvio(null);
                bootstrap.Modal.getInstance(document.getElementById('modalEnviarFormulario'))?.hide();
                mostrarResultadoEnvio(data);
                return;
            }
            Swal.fire('Error', data.error || 'No se pudo enviar el enlace.', 'error');
        } catch (e) {
            Swal.fire('Error', e?.name === 'TimeoutError'
                ? 'El servidor no respondió a tiempo. Revise en «Enlaces enviados» si el enlace se creó antes de volver a enviarlo.'
                : 'Error de red al enviar el enlace.', 'error');
        } finally {
            mostrarLoaderEnvio(null);
        }
        enviandoEnlace = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
    };

    // ── API de Uanataca ───────────────────────────────────────
    async function postFirmas(endpoint, fd) {
        return (await fetch(`${urlBase}/${endpoint}`, { method: 'POST', body: fd || new FormData() })).json();
    }

    window.probarUanataca = async function () {
        const res = document.getElementById('cfgUanResultado');
        res.className = 'small text-muted';
        res.textContent = 'Guardando y probando…';
        // Se prueba con lo guardado: primero se guarda la configuración del formulario.
        const g = await postFirmas('guardarConfigAjax', new FormData(document.getElementById('formConfigFirmas')));
        if (!g.ok) { res.className = 'small text-danger'; res.textContent = g.error; return; }
        try {
            const d = await postFirmas('probarUanatacaAjax');
            res.className = `small ${d.ok ? 'text-success' : 'text-danger'}`;
            res.textContent = d.ok ? d.msg : d.error;
            // Con la conexión ya probada, el catálogo de productos puede cargarse.
            if (d.ok) window.cargarProductosUanataca();
        } catch { res.className = 'small text-danger'; res.textContent = 'Error de conexión.'; }
    };

    // Productos de Uanataca (sección de la pestaña Uanataca): se consulta el catálogo
    // la primera vez que se abre la pestaña; el botón ↻ lo vuelve a pedir.
    let productosUanCargados = false;
    window.cargarProductosUanataca = async function () {
        const tbody = document.getElementById('tbodyMapeoUanataca');
        const alerta = document.getElementById('alertMapeoUanataca');
        const btnGuardar = document.getElementById('btnGuardarMapeoUanataca');
        productosUanCargados = true;
        btnGuardar.disabled = true;
        alerta.className = 'alert d-none mb-2 py-2 small';
        tbody.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Cargando catálogo de Uanataca…</td></tr>';
        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
        try {
            const d = await (await fetch(`${urlBase}/catalogoUanatacaAjax`)).json();
            if (!d.ok) throw new Error(d.error);
            const opciones = sel => '<option value="">— Sin enlace —</option>' + d.catalogo.map(c =>
                `<option value="${esc(c.uuid)}" ${c.uuid === sel ? 'selected' : ''}>${esc(c.code)} · ${esc(c.name)} ($${Number(c.price).toFixed(2)})</option>`).join('');
            tbody.innerHTML = d.productos.length
                ? d.productos.map(p => `<tr><td>${esc(p.nombre)}</td><td><select class="form-select form-select-sm" data-producto="${p.id}">${opciones(p.uanataca_uuid)}</select></td></tr>`).join('')
                : '<tr><td colspan="2" class="text-muted text-center py-3">No hay productos en una categoría «FIRMA».</td></tr>';
            btnGuardar.disabled = !d.productos.length;
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Sin catálogo.</td></tr>';
            alerta.className = 'alert alert-warning mb-2 py-2 small';
            alerta.textContent = e.message || 'No se pudo cargar el catálogo.';
        }
    };
    document.getElementById('tab-cfg-uanataca-btn')?.addEventListener('shown.bs.tab', () => {
        if (!productosUanCargados) window.cargarProductosUanataca();
    });

    window.guardarMapeoUanataca = async function () {
        const btn = document.getElementById('btnGuardarMapeoUanataca');
        const alerta = document.getElementById('alertMapeoUanataca');
        const mapeo = {};
        document.querySelectorAll('#tbodyMapeoUanataca select[data-producto]').forEach(s => { mapeo[s.dataset.producto] = s.value; });
        const fd = new FormData();
        fd.append('mapeo', JSON.stringify(mapeo));
        btn.disabled = true;
        try {
            const d = await postFirmas('guardarMapeoUanatacaAjax', fd);
            alerta.className = `alert alert-${d.ok ? 'success' : 'danger'} mb-2 py-2 small`;
            alerta.textContent = d.ok ? d.msg : d.error;
        } catch { alerta.className = 'alert alert-danger mb-2 py-2 small'; alerta.textContent = 'Error de conexión.'; }
        finally { btn.disabled = false; }
    };

    window.abrirConfigFirmas = function () {
        document.getElementById('alertConfigFirmas').className = 'alert d-none mb-3 py-2 small';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfigFirmas')).show();
    };

    window.guardarConfigFirmas = async function (regenerar) {
        if (regenerar && !confirm('Se generará un enlace fijo nuevo y el anterior dejará de funcionar. ¿Continuar?')) return;
        const btn = document.getElementById('btnGuardarConfigFirmas');
        const alerta = document.getElementById('alertConfigFirmas');
        btn.disabled = true;
        try {
            const fd = new FormData(document.getElementById('formConfigFirmas'));
            if (regenerar) fd.append('regenerar_enlace', '1');
            const data = await (await fetch(`${urlBase}/guardarConfigAjax`, { method: 'POST', body: fd })).json();
            alerta.className = `alert alert-${data.ok ? 'success' : 'danger'} mb-3 py-2 small`;
            alerta.textContent = data.ok ? data.msg : data.error;
            if (data.ok) document.getElementById('cfgUrlEnlaceFijo').value = data.url_enlace_fijo;
        } catch {
            alerta.className = 'alert alert-danger mb-3 py-2 small';
            alerta.textContent = 'Error de conexión.';
        } finally {
            btn.disabled = false;
        }
    };

    async function cargarSolicitudes(page = 1) {
        const tbody = document.getElementById('tbodySolicitudes');
        const info  = document.getElementById('infoSolicitudes');
        const pag   = document.getElementById('pagSolicitudes');
        paginaSolicitudes = page;
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Cargando…</td></tr>';
        try {
            const resp = await fetch(`${urlBase}/getSolicitudes?page=${page}`);
            const data = await resp.json();
            const porPagina = 15;
            const total = data.ok ? Number(data.total || 0) : 0;
            const paginas = Math.max(1, Math.ceil(total / porPagina));
            info.textContent = total ? `${(page - 1) * porPagina + 1}-${Math.min(page * porPagina, total)}/${total}` : '';
            pag.innerHTML = total > porPagina
                ? `<button type="button" class="btn btn-outline-secondary" ${page <= 1 ? 'disabled' : ''} onclick="cargarSolicitudesPagina(${page - 1})"><i class="bi bi-chevron-left"></i></button>
                   <button type="button" class="btn btn-outline-secondary" ${page >= paginas ? 'disabled' : ''} onclick="cargarSolicitudesPagina(${page + 1})"><i class="bi bi-chevron-right"></i></button>`
                : '';
            if (!data.ok || !data.rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">No hay enlaces enviados.</td></tr>';
                return;
            }
            const badges = {
                pendiente:  '<span class="badge sol-badge-pendiente">Pendiente</span>',
                completado: '<span class="badge sol-badge-completado">Completado</span>',
                expirado:   '<span class="badge sol-badge-expirado">Expirado</span>',
                cancelado:  '<span class="badge sol-badge-cancelado">Cancelado</span>',
            };
            const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
            tbody.innerHTML = data.rows.map(r => `
                <tr>
                    <td class="ps-3">${esc(r.correo_destino || '')}${r.telefono_destino ? `<div class="text-muted"><i class="bi bi-whatsapp me-1"></i>${esc(r.telefono_destino)}</div>` : ''}${r.origen === 'enlace_fijo' ? '<div><span class="badge bg-light text-secondary border">Enlace fijo</span></div>' : ''}</td>
                    <td>${r.nombre_destino ? esc(r.nombre_destino) : '<span class="text-muted">-</span>'}</td>
                    <td class="text-center">${badges[r.estado] || r.estado}</td>
                    <td class="text-muted">${r.created_at}</td>
                    <td class="text-muted">${r.expira_at}</td>
                    <td>${r.firma_nombre ? `<a href="#" onclick="event.preventDefault();abrirFirmaDesdeEnlaces('${Number(r.id_firma_generada)}')" class="small text-primary">${esc(r.firma_nombre)}</a>` : '<span class="text-muted small">-</span>'}</td>
                    <td class="text-end pe-3 text-nowrap">
                        ${r.estado !== 'cancelado' && r.estado !== 'expirado'
                            ? `<button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem;" title="Copiar enlace" onclick="copiarTextoFirma(this.dataset.v)" data-v="${esc(urlEnlace(r.token))}"><i class="bi bi-clipboard"></i></button>`
                            : ''}
                        ${r.estado === 'pendiente'
                            ? `<button class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:.75rem;" title="Cancelar enlace" onclick="cancelarSolicitud(${r.id})"><i class="bi bi-x-circle"></i></button>`
                            : ''}
                    </td>
                </tr>`).join('');
        } catch {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Error al cargar.</td></tr>';
        }
    }

    window.cargarSolicitudesPagina = (n) => cargarSolicitudes(n);
    const urlEnlace = token => `${window.location.origin}${window.BASE_URL || ''}/solicitud-firma/${token}`;

    // La ficha se abre al cerrar el modal de enlaces (no se apilan dos modales grandes).
    window.abrirFirmaDesdeEnlaces = function (idFirma) {
        const m = modalEnlaces();
        m.addEventListener('hidden.bs.modal', () => window.abrirFirmaPorNombre(idFirma), { once: true });
        bootstrap.Modal.getOrCreateInstance(m).hide();
    };

    window.cancelarSolicitud = async function (id) {
        if (!confirm('¿Cancelar esta solicitud?')) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const resp = await fetch(`${urlBase}/cancelarSolicitud`, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) cargarSolicitudes(paginaSolicitudes);
            else alert(data.msg);
        } catch { alert('Error de conexión.'); }
    };

    window.abrirFirmaPorNombre = function (idFirma) {
        const rows = document.querySelectorAll('#tbodyFirmas tr[data-row]');
        for (const row of rows) {
            try {
                const d = JSON.parse(row.dataset.row);
                if (String(d.id) === String(idFirma)) { row.click(); return; }
            } catch {}
        }
        document.getElementById('buscarFirma').value = '';
        // Deja el buscador (FiltrosModal) coherente con la búsqueda vaciada: sin texto ni chips.
        // Sin aplicar: la búsqueda la lanza fetchSearch justo debajo.
        if (window.FIRMAS_fm) window.FIRMAS_fm.limpiar(false);
        window.fetchSearch(1).then(() => {
            document.querySelectorAll('#tbodyFirmas tr[data-row]').forEach(row => {
                try {
                    const d = JSON.parse(row.dataset.row);
                    if (String(d.id) === String(idFirma)) { row.click(); }
                } catch {}
            });
        });
    };
})();
</script>
