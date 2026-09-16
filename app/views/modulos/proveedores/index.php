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
$urlBaseProv = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'razon_social';
$ordenDir   = $ordenDir ?? 'asc';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .prov-header {
        flex-shrink: 0;
    }

    .prov-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .prov-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .proveedor-row {
        cursor: pointer;
    }

    .proveedor-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="prov-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-truck"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="PROV_abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nuevo</button>
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
            // ProveedorRepository::getListado(). Sin pestaña Detalles: el proveedor no
            // tiene tablas hijas que buscar.
            $opcFiltro = $opcionesFiltro ?? [];
            $opcIdNombre = fn(string $k) => array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcFiltro[$k] ?? []);
            $opcionesProvincia = array_map(fn($p) => ['v' => (string) $p['codigo'], 'l' => (string) $p['nombre']], $opcFiltro['provincias'] ?? []);
            $opcionesCiudad    = array_map(fn($c) => ['v' => (string) $c['codigo'], 'l' => $c['nombre'] . (!empty($c['provincia']) ? ' (' . $c['provincia'] . ')' : '')], $opcFiltro['ciudades'] ?? []);
            $opcionesSiNo = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
            $tP = 'Proveedor';
            // Filas de 12 columnas:
            //   Identificación: [Tipo 3][RUC 3][Razón social 3][Nombre comercial 3]
            //   Clasificación:  [Tipo de empresa 4][Relacionado SRI 4][Estado 4]
            //   Contacto:       [Correo 3][Con correo 3][Teléfono 3][Dirección 3]
            //   Ubicación:      [Provincia 4][Ciudad 4][Ubicación en mapa 4]
            //   Pago:           [Banco 4][Plazo 4][Pago automático 4]
            //   Tributario:     [Retención renta 4][Retención IVA 4][Sustento 4]
            //   Registro:       [Fecha de registro 6][Usuario 6]
            $filtrosProveedores = [
                // ── Identificación ──
                ['tab' => $tP, 'key' => 'tipo',      'label' => 'Tipo identificación', 'icon' => 'bi-credit-card', 'type' => 'select', 'grupo' => 'Identificación', 'col' => 3, 'options' => [
                    ['v' => '04', 'l' => 'RUC'],
                    ['v' => '05', 'l' => 'Cédula'],
                    ['v' => '06', 'l' => 'Pasaporte'],
                    ['v' => '07', 'l' => 'Consumidor final'],
                    ['v' => '08', 'l' => 'Identificación del exterior'],
                ]],
                ['tab' => $tP, 'key' => 'ruc',       'label' => 'RUC / Identificación', 'icon' => 'bi-card-text',  'type' => 'text', 'grupo' => 'Identificación', 'col' => 3],
                ['tab' => $tP, 'key' => 'nombre',    'label' => 'Razón social',        'icon' => 'bi-building',    'type' => 'text', 'grupo' => 'Identificación', 'col' => 3],
                ['tab' => $tP, 'key' => 'comercial', 'label' => 'Nombre comercial',    'icon' => 'bi-shop',        'type' => 'text', 'grupo' => 'Identificación', 'col' => 3],
                // ── Clasificación ──
                ['tab' => $tP, 'key' => 'id_tipo_empresa', 'label' => 'Tipo de empresa', 'icon' => 'bi-briefcase', 'type' => 'select', 'grupo' => 'Clasificación', 'col' => 4, 'options' => $opcIdNombre('tipos_empresa')],
                ['tab' => $tP, 'key' => 'relacionado', 'label' => 'Relacionado SRI',   'icon' => 'bi-link-45deg',  'type' => 'select', 'grupo' => 'Clasificación', 'col' => 4, 'options' => $opcionesSiNo('Sí', 'No')],
                ['tab' => $tP, 'key' => 'estado',      'label' => 'Estado',            'icon' => 'bi-flag',        'type' => 'select', 'grupo' => 'Clasificación', 'col' => 4, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                // ── Contacto ──
                ['tab' => $tP, 'key' => 'email',     'label' => 'Correo',              'icon' => 'bi-envelope',       'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                ['tab' => $tP, 'key' => 'con_email', 'label' => 'Correo registrado',   'icon' => 'bi-envelope-check', 'type' => 'select', 'grupo' => 'Contacto', 'col' => 3, 'options' => $opcionesSiNo('Con correo', 'Sin correo')],
                ['tab' => $tP, 'key' => 'telefono',  'label' => 'Teléfono',            'icon' => 'bi-telephone',      'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                ['tab' => $tP, 'key' => 'direccion', 'label' => 'Dirección',           'icon' => 'bi-geo',            'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                // ── Ubicación ──
                ['tab' => $tP, 'key' => 'cod_provincia', 'label' => 'Provincia',       'icon' => 'bi-map',     'type' => 'select', 'grupo' => 'Ubicación', 'col' => 4, 'options' => $opcionesProvincia],
                ['tab' => $tP, 'key' => 'cod_ciudad',    'label' => 'Ciudad',          'icon' => 'bi-geo-alt', 'type' => 'select', 'grupo' => 'Ubicación', 'col' => 4, 'options' => $opcionesCiudad],
                ['tab' => $tP, 'key' => 'ubicacion',     'label' => 'Ubicación en el mapa', 'icon' => 'bi-pin-map', 'type' => 'select', 'grupo' => 'Ubicación', 'col' => 4, 'options' => $opcionesSiNo('Con ubicación', 'Sin ubicación')],
                // ── Pago ──
                ['tab' => $tP, 'key' => 'id_banco',  'label' => 'Banco',               'icon' => 'bi-bank',           'type' => 'select',       'grupo' => 'Pago', 'col' => 4, 'options' => $opcIdNombre('bancos')],
                ['tab' => $tP, 'key' => 'plazo',     'label' => 'Plazo (días)',        'icon' => 'bi-calendar-range', 'type' => 'number_range', 'grupo' => 'Pago', 'col' => 4],
                ['tab' => $tP, 'key' => 'pago_auto', 'label' => 'Pago automático',     'icon' => 'bi-cash-coin',      'type' => 'select',       'grupo' => 'Pago', 'col' => 4, 'options' => $opcionesSiNo('Con pago automático', 'Sin pago automático')],
                // ── Tributario ──
                ['tab' => $tP, 'key' => 'id_retencion_renta', 'label' => 'Retención de renta', 'icon' => 'bi-percent',      'type' => 'select', 'grupo' => 'Tributario', 'col' => 4, 'options' => $opcIdNombre('retenciones_renta')],
                ['tab' => $tP, 'key' => 'id_retencion_iva',   'label' => 'Retención de IVA',   'icon' => 'bi-percent',      'type' => 'select', 'grupo' => 'Tributario', 'col' => 4, 'options' => $opcIdNombre('retenciones_iva')],
                ['tab' => $tP, 'key' => 'id_sustento',        'label' => 'Sustento tributario','icon' => 'bi-journal-text', 'type' => 'select', 'grupo' => 'Tributario', 'col' => 4, 'options' => $opcIdNombre('sustentos')],
                // ── Registro ──
                ['tab' => $tP, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tP, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $opcIdNombre('usuarios')],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorPROV"></div>
            <input type="hidden" id="buscarProveedor" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorPROV',
                        hiddenInputId: 'buscarProveedor',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de proveedores',
                        inputWidth: 420,
                        extraId: 'fmExtraPROV',   // columnas + PDF + Excel + Mapa, pegados al final del grupo
                        fields: <?= json_encode($filtrosProveedores, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyProveedores',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraPROV" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'identificacion' => 'Identificación',
                    'nombre_tipo_id' => 'Tipo Id.',
                    'razon_social'   => 'Razón Social',
                    'nombre_comercial' => 'Nombre Comercial',
                    'email'          => 'Correo',
                    'telefono'       => 'Teléfono',
                    'direccion'      => 'Dirección',
                    'plazo'          => 'Plazo (Días)',
                    'relacionado'    => 'Rela. SRI',
                    'nombre_banco'   => 'Banco',
                    'nombre_tipo_empresa' => 'Tipo Empresa',
                    'nombre_provincia' => 'Provincia',
                    'nombre_ciudad'  => 'Ciudad',
                    'status'         => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBaseProv ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseProv ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
                <a href="<?= $urlBaseProv ?>/mapa" class="btn btn-outline-secondary" title="Mapa de proveedores">
                    <i class="bi bi-map"></i><span class="d-none d-md-inline"> Mapa</span>
                </a>
                <?php if ($perm['crear']): ?>
                    <button type="button" class="btn btn-outline-primary d-none" id="btnCopiarProveedoresEmpresa" title="Copiar todos los proveedores a otra empresa" onclick="abrirModalCopiarProveedoresEmpresa()">
                        <i class="bi bi-arrow-left-right"></i> Copiar a otra empresa
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>

                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="prov-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="identificacion" data-col="identificacion">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_tipo_id" data-col="nombre_tipo_id">Tipo Id. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="razon_social" data-col="razon_social">Razón Social <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_comercial" data-col="nombre_comercial">Nombre Comercial <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="email" data-col="email">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="telefono" data-col="telefono">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="direccion" data-col="direccion">Dirección <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="plazo" data-col="plazo">Plazo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="relacionado" data-col="relacionado">Rela. SRI <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_banco" data-col="nombre_banco">Banco <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_tipo_empresa" data-col="nombre_tipo_empresa">Tipo Empresa <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_provincia" data-col="nombre_provincia">Provincia <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_ciudad" data-col="nombre_ciudad">Ciudad <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="status" data-col="status">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyProveedores">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="14" class="text-center py-5 text-muted"><i class="bi bi-truck fs-3 d-block mb-2"></i>No se encontraron proveedores.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="proveedor-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="PROV_abrirModalEditar(this)">
                                <td class="ps-3" data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars($r['identificacion'] ?? '') ?></code></td>
                                <td data-col="nombre_tipo_id"><?= htmlspecialchars($r['nombre_tipo_id'] ?? '-') ?></td>
                                <td class="fw-medium text-truncate" style="max-width:300px" data-col="razon_social"><?= htmlspecialchars($r['razon_social'] ?? '') ?></td>
                                <td data-col="nombre_comercial" class="text-truncate" style="max-width:200px"><?= htmlspecialchars($r['nombre_comercial'] ?? '-') ?></td>
                                <td data-col="email"><?= htmlspecialchars($r['email'] ?? '-') ?></td>
                                <td data-col="telefono"><?= htmlspecialchars($r['telefono'] ?? '-') ?></td>
                                <td data-col="direccion" class="text-truncate" style="max-width:200px"><?= htmlspecialchars($r['direccion'] ?? '-') ?></td>
                                <td data-col="plazo" class="text-center"><?= (int)($r['plazo'] ?? 0) ?></td>
                                <td data-col="relacionado" class="text-center"><?= (isset($r['relacionado']) && ($r['relacionado'] === '1' || $r['relacionado'] === 't' || $r['relacionado'] === 1 || $r['relacionado'] === true) ? 'Sí' : 'No') ?></td>
                                <td data-col="nombre_banco"><?= htmlspecialchars($r['nombre_banco'] ?? '-') ?></td>
                                <td data-col="nombre_tipo_empresa"><?= htmlspecialchars($r['nombre_tipo_empresa'] ?? '-') ?></td>
                                <td data-col="nombre_provincia"><?= htmlspecialchars($r['nombre_provincia'] ?? '-') ?></td>
                                <td data-col="nombre_ciudad"><?= htmlspecialchars($r['nombre_ciudad'] ?? '-') ?></td>
                                <td class="text-center pe-3" data-col="status">
                                    <?php if ($r['status'] ?? true): ?>
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
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
</script>
<?php include __DIR__ . '/modal_proveedor.php'; ?>

<!-- Modal: Copiar todos los proveedores a otra empresa -->
<div class="modal fade" id="modalCopiarProveedoresEmpresa" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right text-primary me-2"></i>Copiar proveedores a otra empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-primary bg-primary bg-opacity-10 py-2 px-3 small border-primary border-opacity-25 mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Copia todos los proveedores de esta empresa hacia la empresa que elija. Si un proveedor ya existe allí (misma identificación), no se duplica ni se sobrescribe.
                </div>
                <label for="copiarProveedoresEmpresaSelect" class="form-label small fw-bold">Empresa destino</label>
                <select class="form-select form-select-sm" id="copiarProveedoresEmpresaSelect"></select>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-3" id="btnConfirmarCopiarProveedores" onclick="confirmarCopiarProveedoresEmpresa()">
                    <i class="bi bi-arrow-left-right me-1"></i> Copiar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>/js/modulos/proveedores_modal.js?v=<?= asset_ver('/js/modulos/proveedores_modal.js') ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseProv ?>';
        const inputBuscar = document.getElementById('buscarProveedor');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir = '<?= $ordenDir ?>';
        // Orden multiple (Shift+clic): lista completa de criterios, en el formato que lee
        // OrdenListado en PHP. currentSort/currentDir quedan como el principal.
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        window.currentPage = <?= $page ?>;
        let sorter = null;

        let timerId;

        function debounce(func, delay = 350) {
            return (...args) => {
                clearTimeout(timerId);
                timerId = setTimeout(() => func.apply(this, args), delay);
            };
        }

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const orden = window.CMG_ordenParam(window.currentSorts || []);
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar u ordenar (que llaman a esta función directo).
            const tbody = document.getElementById('tbodyProveedores');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyProveedores').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    // Los iconos (incluida la prioridad 1/2/3 del orden multiple) los
                    // repinta el motor global; aqui solo se le pide que se refresque.
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) {
                console.error('Error en búsqueda de proveedores:', e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        // multi: clic normal ordena por una columna; Shift+clic encadena hasta 3
        // (ASC -> DESC -> fuera del orden), con la prioridad numerada en cada encabezado.
        // reload:false porque fetchSearch repinta todo lo que depende del orden.
        sorter = window.CMG_initSort('proveedores', (col, dir, sorts) => {
            window.currentSort  = col;
            window.currentDir   = dir;
            window.currentSorts = sorts;
            fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.prov-scroll', reload: false });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));
    })();
</script>