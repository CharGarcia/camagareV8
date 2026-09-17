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
/** @var array $vistaConfig */

$base = BASE_URL;
$urlBaseModulo = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .asiento-scroll {
        max-height: calc(100dvh - 250px);
        overflow-y: auto;
    }

    .asiento-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
    }

    .asiento-row {
        cursor: pointer;
    }

    .asiento-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="ASIENTO_abrirModal()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador: texto libre sobre las columnas del listado (sin sugerencias) +
            // botón embudo que abre el modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de AsientoContableRepository::getListado().
            $opcionesFiltro = $opcionesFiltro ?? [];
            // Etiquetas legibles de los valores guardados; lo desconocido se muestra tal cual.
            $etiquetasTipo = [
                'apertura' => 'Apertura', 'cierre' => 'Cierre', 'compras' => 'Compras',
                'compras_servicios' => 'Compras de servicios', 'consignacion' => 'Consignación',
                'diario' => 'Diario', 'egresos' => 'Egresos', 'ingresos' => 'Ingresos', 'nomina' => 'Nómina',
                'retenciones_compras' => 'Retenciones compras', 'retenciones_ventas' => 'Retenciones ventas',
                'retorno_consignacion' => 'Retorno consignación', 'ventas' => 'Ventas',
            ];
            $etiquetaLegible = fn(string $v, array $mapa) => $mapa[$v] ?? ucfirst(str_replace('_', ' ', mb_strtolower($v)));
            $opcionesTipo    = array_map(fn($t) => ['v' => (string) $t, 'l' => $etiquetaLegible((string) $t, $etiquetasTipo)], $opcionesFiltro['tipos'] ?? []);
            // Origen: catálogo completo (AsientoContableService::getOpcionesFiltroListado), con nombre legible.
            $opcionesModulo  = array_map(fn($m) => ['v' => (string) $m, 'l' => \App\Helpers\OrigenAsiento::etiqueta((string) $m)], $opcionesFiltro['modulos'] ?? []);
            $opcionesUsuario = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $opcionesFiltro['usuarios'] ?? []);
            // Dos pestañas: "Asiento" (filtros por campo) y "Detalles" (solo la búsqueda
            // libre dentro de las líneas, ver `busquedaDetalle`).
            $tA = 'Asiento';
            // Filas de 12 columnas:
            //   Documento: [Fecha 6][Estado 3][Tipo 3]
            //              [N° comprobante 4][Origen 4][Usuario que registró 4]
            //              [Total 4][Cuadrado 4][Editado a mano 4]
            //              [Fecha de registro 4][Concepto 4][Observaciones 4]
            //   Líneas:    [Cuenta contable 6][Referencia / documento 6]  (el asiento entra si ALGUNA línea coincide)
            $filtrosAsientos = [
                ['tab' => $tA, 'key' => 'fecha',         'label' => 'Fecha del asiento',     'icon' => 'bi-calendar-event',     'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tA, 'key' => 'estado',        'label' => 'Estado',                'icon' => 'bi-flag',               'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'contabilizado', 'l' => 'Contabilizado'],
                    ['v' => 'borrador',      'l' => 'Borrador'],
                    ['v' => 'anulado',       'l' => 'Anulado'],
                ]],
                ['tab' => $tA, 'key' => 'tipo',          'label' => 'Tipo',                  'icon' => 'bi-journal',            'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesTipo],
                ['tab' => $tA, 'key' => 'numero',        'label' => 'N° comprobante',        'icon' => 'bi-hash',               'type' => 'text',         'grupo' => 'Documento', 'col' => 4, 'placeholder' => 'VE-000059'],
                ['tab' => $tA, 'key' => 'modulo',        'label' => 'Origen',                'icon' => 'bi-box-arrow-in-right', 'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesModulo],
                ['tab' => $tA, 'key' => 'usuario',       'label' => 'Usuario que registró',  'icon' => 'bi-person-badge',       'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesUsuario],
                ['tab' => $tA, 'key' => 'total',         'label' => 'Total',                 'icon' => 'bi-currency-dollar',    'type' => 'number_range', 'grupo' => 'Documento', 'col' => 4],
                ['tab' => $tA, 'key' => 'cuadrado',      'label' => 'Debe = Haber',          'icon' => 'bi-check2-square',      'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Cuadrado'],
                    ['v' => 'no', 'l' => 'Descuadrado'],
                ]],
                ['tab' => $tA, 'key' => 'editado',       'label' => 'Editado a mano',        'icon' => 'bi-pencil-square',      'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Sí'],
                    ['v' => 'no', 'l' => 'No'],
                ]],
                ['tab' => $tA, 'key' => 'registro',      'label' => 'Fecha de registro',     'icon' => 'bi-clock-history',      'type' => 'date_range',   'grupo' => 'Documento', 'col' => 4],
                ['tab' => $tA, 'key' => 'concepto',      'label' => 'Concepto',              'icon' => 'bi-chat-left-text',     'type' => 'text',         'grupo' => 'Documento', 'col' => 4],
                ['tab' => $tA, 'key' => 'observaciones', 'label' => 'Observaciones',         'icon' => 'bi-card-text',          'type' => 'text',         'grupo' => 'Documento', 'col' => 4],
                ['tab' => $tA, 'key' => 'cuenta',        'label' => 'Cuenta contable',       'icon' => 'bi-diagram-3',          'type' => 'text',         'grupo' => 'Líneas',    'col' => 6, 'placeholder' => 'Código o nombre'],
                ['tab' => $tA, 'key' => 'referencia',    'label' => 'Referencia / documento','icon' => 'bi-link-45deg',         'type' => 'text',         'grupo' => 'Líneas',    'col' => 6],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorASIENTOS"></div>
            <input type="hidden" id="buscarAsiento" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorASIENTOS',
                        hiddenInputId: 'buscarAsiento',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de asientos contables',
                        inputWidth: 420,
                        extraId: 'fmExtraASIENTOS',   // columnas, pegadas al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de las líneas de los asientos.
                        // Cada coincidencia dice a qué asiento pertenece.
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBaseModulo ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de los asientos',
                            placeholder: 'Cuenta (código o nombre), referencia, documento, tercero, centro de costo, valor...',
                            columns: [
                                { key: 'cuenta',     label: 'Cuenta' },
                                { key: 'referencia', label: 'Referencia' },
                                { key: 'tercero',    label: 'Tercero' },
                                { key: 'debe',       label: 'Debe',  align: 'end' },
                                { key: 'haber',      label: 'Haber', align: 'end' },
                                { key: 'numero',     label: 'Asiento', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',      label: 'Fecha' },
                                { key: 'estado',     label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero }),
                            onOpen: (row, fm) => { fm.hide(); setTimeout(() => ASIENTO_abrirModal(row.id_asiento), 350); },
                        },
                        fields: <?= json_encode($filtrosAsientos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyAsientos',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraASIENTOS" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_comprobante' => 'Comprobante',
                    'fecha_asiento' => 'Fecha',
                    'tipo_comprobante' => 'Tipo',
                    'concepto' => 'Concepto',
                    'modulo_origen' => 'Origen',
                    'total_debe' => 'Total',
                    'estado' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="wrapper-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="asiento-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="numero_comprobante" role="button" data-col="numero_comprobante">Comprobante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="fecha_asiento" role="button" data-col="fecha_asiento">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tipo_comprobante" role="button" data-col="tipo_comprobante">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="concepto" role="button" data-col="concepto">Concepto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="modulo_origen" role="button" data-col="modulo_origen">Origen <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="total_debe" role="button" data-col="total_debe">Total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyAsientos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No se encontraron asientos contables.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $statusBadge = '';
                            if ($r['estado'] === 'contabilizado') $statusBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Contabilizado</span>';
                            elseif ($r['estado'] === 'anulado') $statusBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>';
                            else $statusBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>';
                            ?>
                            <tr class="asiento-row" role="button" onclick="ASIENTO_abrirModal(<?= $r['id'] ?>)">
                                <td class="ps-3 fw-bold" data-col="numero_comprobante"><?= htmlspecialchars($r['numero_comprobante'] ?? '') ?></td>
                                <td data-col="fecha_asiento"><?= htmlspecialchars($r['fecha_asiento'] ?? '') ?></td>
                                <td data-col="tipo_comprobante" class="text-capitalize"><?= htmlspecialchars($r['tipo_comprobante'] ?? '') ?></td>
                                <td data-col="concepto" class="small text-truncate" style="max-width: 250px;"><?= htmlspecialchars($r['concepto'] ?? '') ?></td>
                                <td data-col="modulo_origen" class="text-capitalize small text-muted"><?= str_replace('_', ' ', htmlspecialchars($r['modulo_origen'] ?? '')) ?></td>
                                <td data-col="total_debe" class="text-end fw-bold">$<?= number_format((float)($r['total_debe'] ?? 0), 2) ?></td>
                                <td class="text-center" data-col="estado"><?= $statusBadge ?></td>
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
<?php include 'modal_asiento.php'; ?>
<?php include __DIR__ . '/../documento_origen/modal_documento.php'; ?>
<script>window.DOCORIGEN_URL = '<?= $urlBaseModulo ?>/getDocumentoOrigenAjax';</script>
<script src="<?= $base ?>/js/modulos/documento_origen_modal.js?v=<?= asset_ver('/js/modulos/documento_origen_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/asientos_contables_modal.js?v=<?= asset_ver('/js/modulos/asientos_contables_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/asientos_pendientes.js?v=<?= asset_ver('/js/modulos/asientos_pendientes.js') ?>"></script>

<script>
    // El modal de Documento Origen se abre ENCIMA del de Asiento Contable: la regla
    // global .modal { z-index: 5060 !important } (public/css/app.css) los deja a la misma
    // altura, así que hay que subir el submodal (y su backdrop) por encima con inline
    // !important, que sí gana a la regla global. Mismo fix que modulos/proformas.
    (function() {
        'use strict';
        var Z_SUBMODAL = 5080;
        var Z_BACKDROP = 5075;
        document.addEventListener('show.bs.modal', function(ev) {
            if (ev.target.id !== 'modalDocumentoOrigen') return;
            ev.target.style.setProperty('z-index', String(Z_SUBMODAL), 'important');
            setTimeout(function() {
                var bds = document.querySelectorAll('.modal-backdrop');
                if (bds.length) {
                    bds[bds.length - 1].style.setProperty('z-index', String(Z_BACKDROP), 'important');
                }
            }, 0);
        });
    })();
</script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseModulo ?>';
        const inputB = document.getElementById('buscarAsiento');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';

        window.fetchSearch = async function(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa en vez de
            // vaciarse. Aquí también para paginar y ordenar, que llaman a esta función directo.
            const tbody = document.getElementById('tbodyAsientos');
            if (tbody) tbody.classList.add('fm-cargando-target');
            // Una búsqueda nueva cancela la anterior: solo la última pinta la tabla y quita el
            // atenuado (sin esto, una respuesta lenta que llega tarde pisaba el resultado nuevo).
            if (window.ASIENTO_busquedaCtrl) window.ASIENTO_busquedaCtrl.abort();
            const ctrl = new AbortController();
            window.ASIENTO_busquedaCtrl = ctrl;
            try {
                const resp = await fetch(uri, { signal: ctrl.signal });
                const data = await resp.json();
                if (ctrl !== window.ASIENTO_busquedaCtrl) return;
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyAsientos').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    // Los íconos de ordenamiento NO se repintan acá: los mantiene CMG_initSort
                    // (ver abajo), y esta recarga solo reemplaza el tbody y la paginación,
                    // nunca el thead.
                }
            } catch (e) {
                if (e.name !== 'AbortError') console.error(e);
            } finally {
                if (tbody && ctrl === window.ASIENTO_busquedaCtrl) tbody.classList.remove('fm-cargando-target');
            }
        };

        // Alias para compatibilidad con llamadas existentes (guardar, anular, etc.)
        window.cambiarPaginaAjax = (p) => window.fetchSearch(p);

        // ── Ordenamiento: motor global (window.CMG_initSort, en public/js/favoritos.js) ──
        // Antes esta vista tenía su propio binding inline, que enganchaba el clic y
        // persistía la preferencia, pero solo pintaba el ícono de la columna activa dentro
        // del callback de fetchSearch(). Al recargar la página —lo que hace justamente
        // guardarOrdenacionVista— los 7 encabezados los renderiza PHP con el ícono neutro
        // y nada volvía a marcar la columna: el listado venía bien ordenado desde el
        // servidor, pero sin el distintivo. El motor global llama refreshIcons() también al
        // inicializar, así que el ícono queda pintado desde la carga.
        if (window.CMG_initSort) {
            window.CMG_initSort('asientos_contables', function(col, dir) {
                currentSort = col;
                currentDir  = dir;
                window.fetchSearch(1);
            }, { col: currentSort, dir: currentDir });
        }

        // ── Aviso de asientos pendientes de generar ─────────────────────────────────
        // Al cargar, se consulta cuántos documentos están sin asiento y se pregunta al
        // usuario si desea generarlos ahora o continuar sin generar. Si genera, se refresca
        // el listado para mostrar los asientos nuevos.
        if (typeof window.CMG_verificarAsientosPendientes === 'function') {
            window.CMG_verificarAsientosPendientes({
                urlBase: urlBase,
                onGenerado: () => {
                    if (typeof window.fetchSearch === 'function') {
                        window.fetchSearch(window.currentPage || 1);
                    }
                }
            });
        }
    })();
</script>