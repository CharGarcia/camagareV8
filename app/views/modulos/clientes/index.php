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
$urlBaseClientes = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'nombre';
$ordenDir   = $ordenDir   ?? 'asc';
$buscar     = $buscar     ?? '';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .clientes-header { flex-shrink: 0; }
    .clientes-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .clientes-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .cliente-row { cursor: pointer; }
    .cliente-row:hover { background-color: rgba(0, 0, 0, .04); }
    .field-sri-locked { background-color: #f0fff4 !important; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="clientes-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalClienteCrear()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre las columnas del listado
            // (sin sugerencias) + botón embudo que abre un modal con todos los filtros +
            // chips de los activos. Las claves (key) deben existir en los mapas de
            // ClienteRepository::getListado(). Sin pestaña Detalles: el cliente no tiene
            // tablas hijas que buscar.
            $opcFiltro = $opcionesFiltro ?? [];
            $opcionesVendedor  = array_map(fn($v) => ['v' => (string) $v['id'], 'l' => (string) $v['nombre']], $opcFiltro['vendedores'] ?? []);
            $opcionesProvincia = array_map(fn($p) => ['v' => (string) $p['codigo'], 'l' => (string) $p['nombre']], $opcFiltro['provincias'] ?? []);
            $opcionesCiudad    = array_map(fn($c) => ['v' => (string) $c['codigo'], 'l' => $c['nombre'] . (!empty($c['provincia']) ? ' (' . $c['provincia'] . ')' : '')], $opcFiltro['ciudades'] ?? []);
            $opcionesUsuario   = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => (string) $u['nombre']], $opcFiltro['usuarios'] ?? []);
            $opcionesSiNo = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
            $tC = 'Cliente';
            // Filas de 12 columnas:
            //   Identificación: [Tipo 3][RUC 3][Nombre 4][Estado 2]
            //   Contacto:       [Correo 3][Con correo 3][Teléfono 3][Dirección 3]
            //   Ubicación:      [Provincia 4][Ciudad 4][Ubicación en mapa 4]
            //   Comercial:      [Vendedor 4][Plazo 4][Cobro automático 4]
            //   Visitas:        [Día 4][Frecuencia 4][Semana del mes 4]
            //   Registro:       [Fecha de registro 6][Usuario 6]
            $filtrosClientes = [
                // ── Identificación ──
                ['tab' => $tC, 'key' => 'tipo',      'label' => 'Tipo identificación', 'icon' => 'bi-credit-card',   'type' => 'select', 'grupo' => 'Identificación', 'col' => 3, 'options' => [
                    ['v' => '04', 'l' => 'RUC'],
                    ['v' => '05', 'l' => 'Cédula'],
                    ['v' => '06', 'l' => 'Pasaporte'],
                    ['v' => '07', 'l' => 'Consumidor final'],
                    ['v' => '08', 'l' => 'Identificación del exterior'],
                ]],
                ['tab' => $tC, 'key' => 'ruc',       'label' => 'RUC / Cédula',        'icon' => 'bi-card-text',     'type' => 'text',   'grupo' => 'Identificación', 'col' => 3],
                ['tab' => $tC, 'key' => 'nombre',    'label' => 'Razón social',        'icon' => 'bi-person',        'type' => 'text',   'grupo' => 'Identificación', 'col' => 4],
                ['tab' => $tC, 'key' => 'estado',    'label' => 'Estado',              'icon' => 'bi-flag',          'type' => 'select', 'grupo' => 'Identificación', 'col' => 2, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                // ── Contacto ──
                ['tab' => $tC, 'key' => 'email',     'label' => 'Correo',              'icon' => 'bi-envelope',      'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                ['tab' => $tC, 'key' => 'con_email', 'label' => 'Correo registrado',   'icon' => 'bi-envelope-check','type' => 'select', 'grupo' => 'Contacto', 'col' => 3, 'options' => $opcionesSiNo('Con correo', 'Sin correo')],
                ['tab' => $tC, 'key' => 'telefono',  'label' => 'Teléfono',            'icon' => 'bi-telephone',     'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                ['tab' => $tC, 'key' => 'direccion', 'label' => 'Dirección',           'icon' => 'bi-geo',           'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                // ── Ubicación ──
                ['tab' => $tC, 'key' => 'cod_provincia', 'label' => 'Provincia',       'icon' => 'bi-map',           'type' => 'select', 'grupo' => 'Ubicación', 'col' => 4, 'options' => $opcionesProvincia],
                ['tab' => $tC, 'key' => 'cod_ciudad',    'label' => 'Ciudad',          'icon' => 'bi-geo-alt',       'type' => 'select', 'grupo' => 'Ubicación', 'col' => 4, 'options' => $opcionesCiudad],
                ['tab' => $tC, 'key' => 'ubicacion',     'label' => 'Ubicación en el mapa', 'icon' => 'bi-pin-map',  'type' => 'select', 'grupo' => 'Ubicación', 'col' => 4, 'options' => $opcionesSiNo('Con ubicación', 'Sin ubicación')],
                // ── Comercial ──
                ['tab' => $tC, 'key' => 'id_vendedor', 'label' => 'Vendedor',          'icon' => 'bi-person-badge',  'type' => 'select',       'grupo' => 'Comercial', 'col' => 4, 'options' => $opcionesVendedor],
                ['tab' => $tC, 'key' => 'plazo',       'label' => 'Plazo (días)',      'icon' => 'bi-calendar-range','type' => 'number_range', 'grupo' => 'Comercial', 'col' => 4],
                ['tab' => $tC, 'key' => 'cobro_auto',  'label' => 'Cobro automático',  'icon' => 'bi-cash-coin',     'type' => 'select',       'grupo' => 'Comercial', 'col' => 4, 'options' => $opcionesSiNo('Con cobro automático', 'Sin cobro automático')],
                // ── Visitas ──
                ['tab' => $tC, 'key' => 'dia_visita',    'label' => 'Día de visita',   'icon' => 'bi-calendar-week', 'type' => 'select', 'grupo' => 'Visitas', 'col' => 4, 'options' => [
                    ['v' => '1', 'l' => 'Lunes'], ['v' => '2', 'l' => 'Martes'], ['v' => '3', 'l' => 'Miércoles'],
                    ['v' => '4', 'l' => 'Jueves'], ['v' => '5', 'l' => 'Viernes'], ['v' => '6', 'l' => 'Sábado'], ['v' => '7', 'l' => 'Domingo'],
                ]],
                ['tab' => $tC, 'key' => 'frecuencia',    'label' => 'Frecuencia de visita', 'icon' => 'bi-arrow-repeat', 'type' => 'select', 'grupo' => 'Visitas', 'col' => 4,
                    'options' => array_map(fn($k, $l) => ['v' => (string) $k, 'l' => $l], array_keys(\App\Helpers\DiasVisita::FRECUENCIAS), \App\Helpers\DiasVisita::FRECUENCIAS)],
                ['tab' => $tC, 'key' => 'semana_visita', 'label' => 'Semana del mes',  'icon' => 'bi-calendar3',     'type' => 'select', 'grupo' => 'Visitas', 'col' => 4,
                    'options' => array_map(fn($k, $l) => ['v' => (string) $k, 'l' => $l], array_keys(\App\Helpers\DiasVisita::SEMANAS), \App\Helpers\DiasVisita::SEMANAS)],
                // ── Registro ──
                ['tab' => $tC, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event','type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tC, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',   'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $opcionesUsuario],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCLI"></div>
            <input type="hidden" id="buscarCliente" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorCLI',
                        hiddenInputId: 'buscarCliente',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de clientes',
                        inputWidth: 420,
                        extraId: 'fmExtraCLI',   // columnas + PDF + Excel + Mapa, pegados al final del grupo
                        fields: <?= json_encode($filtrosClientes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyClientes',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraCLI" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'identificacion' => 'Identificación',
                    'nombre_tipo_id' => 'Tipo Id.',
                    'nombre' => 'Razón Social',
                    'email' => 'Correo',
                    'telefono' => 'Teléfono',
                    'direccion' => 'Dirección',
                    'plazo' => 'Plazo',
                    'nombre_provincia' => 'Provincia',
                    'nombre_ciudad' => 'Ciudad',
                    'nombre_vendedor' => 'Vendedor',
                    'dias_visita' => 'Días de visita',
                    'status' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseClientes ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>" class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="btnExportExcel" href="<?= $urlBaseClientes ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>" class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
                <a href="<?= $urlBaseClientes ?>/mapa" class="btn btn-outline-secondary" title="Mapa de clientes"><i class="bi bi-map"></i><span class="d-none d-md-inline"> Mapa</span></a>
                <?php if ($perm['crear']): ?>
                    <button type="button" class="btn btn-outline-primary d-none" id="btnCopiarClientesEmpresa" title="Copiar todos los clientes a otra empresa" onclick="abrirModalCopiarClientesEmpresa()">
                        <i class="bi bi-arrow-left-right"></i> Copiar a otra empresa
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="clientes-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="identificacion" data-col="identificacion">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_tipo_id" data-col="nombre_tipo_id">Tipo Id. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre" data-col="nombre">Razón Social <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="email" data-col="email">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="telefono" data-col="telefono">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="direccion" data-col="direccion">Dirección <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="plazo" data-col="plazo">Plazo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_provincia" data-col="nombre_provincia">Provincia <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_ciudad" data-col="nombre_ciudad">Ciudad <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_vendedor" data-col="nombre_vendedor">Vendedor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="frecuencia_visita" data-col="dias_visita">Días de visita <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="status" data-col="status">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyClientes">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="12" class="text-center py-5 text-muted">No se encontraron clientes.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="cliente-row" role="button" data-cliente='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalClienteEditar(this)">
                                <td class="ps-3" data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars($r['identificacion'] ?? '') ?></code></td>
                                <td data-col="nombre_tipo_id"><?= htmlspecialchars($r['nombre_tipo_id'] ?? $r['tipo_id'] ?? '-') ?></td>
                                <td class="fw-medium text-truncate" style="max-width:250px" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td data-col="email"><?= htmlspecialchars($r['email'] ?? '-') ?></td>
                                <td data-col="telefono"><?= htmlspecialchars($r['telefono'] ?? '-') ?></td>
                                <td data-col="direccion" class="text-truncate" style="max-width:200px"><?= htmlspecialchars($r['direccion'] ?? '-') ?></td>
                                <td data-col="plazo" class="text-center"><?= (int)($r['plazo'] ?? 0) ?></td>
                                <td data-col="nombre_provincia"><?= htmlspecialchars($r['nombre_provincia'] ?? '-') ?></td>
                                <td data-col="nombre_ciudad"><?= htmlspecialchars($r['nombre_ciudad'] ?? '-') ?></td>
                                <td data-col="nombre_vendedor"><?= htmlspecialchars($r['nombre_vendedor'] ?? '-') ?></td>
                                <td data-col="dias_visita" class="text-nowrap"><?= \App\Helpers\DiasVisita::renderCelda($r['dias_visita'] ?? null, $r['frecuencia_visita'] ?? null, $r['semanas_visita'] ?? null, $r['hora_visita_desde'] ?? null, $r['hora_visita_hasta'] ?? null) ?></td>
                                <td class="text-center pe-3" data-col="status">
                                    <span class="badge bg-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'secondary' ?> border border-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'secondary' ?> border-opacity-25"><?= ($r['status'] ?? 1) == 1 ? 'Activo' : 'Inactivo' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_cliente.php'; ?>

<!-- Modal: Copiar todos los clientes a otra empresa -->
<div class="modal fade" id="modalCopiarClientesEmpresa" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right text-primary me-2"></i>Copiar clientes a otra empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-primary bg-primary bg-opacity-10 py-2 px-3 small border-primary border-opacity-25 mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Copia todos los clientes activos de esta empresa hacia la empresa que elija. Si un cliente ya existe allí (misma identificación), no se duplica ni se sobrescribe.
                </div>
                <label for="copiarClientesEmpresaSelect" class="form-label small fw-bold">Empresa destino</label>
                <select class="form-select form-select-sm" id="copiarClientesEmpresaSelect"></select>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-3" id="btnConfirmarCopiarClientes" onclick="confirmarCopiarClientesEmpresa()">
                    <i class="bi bi-arrow-left-right me-1"></i> Copiar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>/js/modulos/clientes_modal.js?v=<?= asset_ver('/js/modulos/clientes_modal.js') ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseClientes ?>';
        const inputBuscar = document.getElementById('buscarCliente');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir = '<?= $ordenDir ?>';
        // Orden múltiple (Shift+clic): lista completa de criterios, en el formato
        // que lee OrdenListado en PHP. currentSort/currentDir quedan como el principal.
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        window.currentPage = <?= $page ?>;
        let sorter = null;
        let timerId;

        const debounce = (func, delay = 350) => (...args) => {
            clearTimeout(timerId);
            timerId = setTimeout(() => func.apply(this, args), delay);
        };

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const orden = window.CMG_ordenParam(window.currentSorts);
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar u ordenar (que llaman a esta función directo).
            const tbody = document.getElementById('tbodyClientes');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyClientes').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    // Los íconos (incluida la prioridad 1/2/3 del orden múltiple) los
                    // repinta el motor global; aquí solo se le pide que se refresque.
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) {
                console.error(e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        // Ordenamiento (motor global: persiste la preferencia y pinta los íconos).
        // multi: clic normal ordena por una columna; Shift+clic encadena hasta 3
        // (ASC → DESC → fuera). No recarga la página: fetchSearch repinta filas,
        // paginación, contador y los enlaces de PDF/Excel.
        sorter = window.CMG_initSort('clientes', (col, dir, sorts) => {
            window.currentSort = col;
            window.currentDir = dir;
            window.currentSorts = sorts;
            fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));
    })();
</script>