<?php
/**
 * Conciliación de Tarjetas — pantalla principal.
 *
 * Dos vistas sobre lo mismo:
 *   • Pendientes por depositar: cobros con tarjeta que aún no aparecen en ningún
 *     estado de cuenta de la procesadora (con semáforo de atraso).
 *   • Conciliaciones: las sesiones de cruce ya armadas.
 *
 * Página con filtros y KPIs encima de la tabla → app-shell desactivado y tarjeta
 * de control fija (§9).
 *
 * Cada vista es su propia tarjeta de listado (`cmg-table-card`) con su propio menú de
 * columnas: el redimensionado de favoritos.js guarda los anchos en el módulo del
 * primer menú de la tarjeta, y cada tabla necesita el suyo para no pisar al otro.
 * La tabla de pendientes guarda sus preferencias (orden, columnas, anchos) en
 * `$rutaPrefsPend`.
 */
$idModulo = basename($rutaModulo);
$puedeCrear      = !empty($perm['crear']);
$puedeActualizar = !empty($perm['actualizar']);
$puedeEliminar   = !empty($perm['eliminar']);

$vistaConfig = $vistaConfig ?? [];
$vistaPend   = $vistaPend ?? [];

// Una sola hoja de estilos para las dos tablas: los data-col no se repiten entre ellas.
$vistaColumnas = [
    '__columnas_ocultas__' => array_merge(
        (array) ($vistaConfig['__columnas_ocultas__'] ?? []),
        (array) ($vistaPend['__columnas_ocultas__'] ?? [])
    ),
    '__columnas_anchos__' => (array) ($vistaConfig['__columnas_anchos__'] ?? [])
                           + (array) ($vistaPend['__columnas_anchos__'] ?? []),
];

$listados = [
    'pendientes' => [
        'ruta'     => $rutaPrefsPend,
        'tabla'    => 'tabla-ctar-pendientes',
        'tbody'    => 'ctar-tbody-pendientes',
        'minWidth' => 1180,
        'vacio'    => '<i class="bi bi-credit-card-2-front fs-3 d-block mb-2 text-primary opacity-50"></i>Cargando cobros pendientes…',
        'columnas' => [
            // data-col => [etiqueta, data-sort, clases]
            'ct_fecha'        => ['Fecha cobro', 'fecha', 'ps-3'],
            'ct_procesadora'  => ['Procesadora', 'procesadora', ''],
            'ct_documento'    => ['Documento', 'documento', ''],
            'ct_cliente'      => ['Cliente', 'cliente', ''],
            'ct_ingreso'      => ['Ingreso', 'ingreso', ''],
            'ct_autorizacion' => ['Autorización', 'autorizacion', ''],
            'ct_monto'        => ['Monto', 'monto', 'text-end'],
            'ct_dias'         => ['Días', 'dias', 'text-center pe-3'],
        ],
    ],
    'conciliaciones' => [
        'ruta'     => $rutaModulo,
        'tabla'    => 'tabla-ctar',
        'tbody'    => 'ctar-tbody',
        'minWidth' => 1240,
        'vacio'    => '<i class="bi bi-list-check fs-3 d-block mb-2 text-primary opacity-50"></i>Cargando conciliaciones…',
        'columnas' => [
            'c_numero'      => ['Número', 'numero', 'ps-3'],
            'c_fecha'       => ['Fecha', 'fecha', ''],
            'c_procesadora' => ['Procesadora', 'procesadora', ''],
            'c_destino'     => ['Depositado en', 'destino', ''],
            'c_cobros'      => ['Cobros', 'cobros', 'text-center'],
            'c_bruto'       => ['Bruto', 'bruto', 'text-end'],
            'c_comision'    => ['Comisión', 'comision', 'text-end'],
            'c_retenciones' => ['Retenciones', 'retenciones', 'text-end'],
            'c_neto'        => ['Neto', 'neto', 'text-end'],
            'c_estado'      => ['Estado', 'estado', 'text-center'],
            'c_asiento'     => ['Asiento', 'asiento', 'text-center'],
            'c_acciones'    => ['Acciones', null, 'text-center pe-3'],
        ],
    ],
];
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaColumnas) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig) ?>

<style>
    .ctar-scroll { overflow-x:auto; }
    .ctar-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; }
    .ctar-scroll tbody td { white-space:nowrap; }
    /* Cabeceras ordenables (motor global CMG_initSort) */
    .ctar-scroll thead th.sortable-header { cursor:pointer; user-select:none; }
    .ctar-scroll thead th.sortable-header:hover { background:#eef2f5; }

    /* Semáforo de atraso: días que un cobro lleva sin aparecer en el estado de cuenta */
    .ctar-dias-ok      { background:rgba(25,135,84,.12);  color:#198754; border:1px solid rgba(25,135,84,.25); }
    .ctar-dias-alerta  { background:rgba(255,193,7,.15);  color:#856404; border:1px solid rgba(255,193,7,.35); }
    .ctar-dias-tarde   { background:rgba(220,53,69,.12);  color:#dc3545; border:1px solid rgba(220,53,69,.25); }

    .ctar-estado-borrador { background:rgba(108,117,125,.12); color:#6c757d; border:1px solid rgba(108,117,125,.25); }
    .ctar-estado-cerrada  { background:rgba(25,135,84,.12);   color:#198754; border:1px solid rgba(25,135,84,.25); }
    .ctar-estado-anulada  { background:rgba(220,53,69,.12);   color:#dc3545; border:1px solid rgba(220,53,69,.25); }

    /* Altura idéntica y explícita en todos los controles de filtros (§9) */
    #form-filtros-ctar .form-select,
    #form-filtros-ctar .form-control,
    #form-filtros-ctar .input-group-text,
    #form-filtros-ctar .btn { height:28px; font-size:.75rem; }

    /* La tabla se extiende libremente: hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-<?php echo $idModulo; ?> .ctar-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>Conciliación de Tarjetas</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-ctar" onsubmit="event.preventDefault(); CTAR_cargar();" class="d-flex flex-wrap align-items-start gap-2">

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Procesadora</label>
                    <select id="ctar-procesadora" class="form-select form-select-sm shadow-none border" style="width:160px;" onchange="CTAR_cargar()">
                        <option value="">Todas</option>
                        <?php foreach ($procesadoras as $p): ?>
                            <option value="<?php echo (int) $p['id']; ?>"
                                    data-cuenta="<?php echo htmlspecialchars((string) ($p['cuenta_codigo'] ?? '')); ?>"
                                    data-dias="<?php echo (int) ($p['dias_liquidacion'] ?? 2); ?>">
                                <?php echo htmlspecialchars($p['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                    <select id="ctar-estado" class="form-select form-select-sm shadow-none border" style="width:130px;" onchange="CTAR_cargar()"
                            title="Estado de la conciliación (solo aplica a la pestaña Conciliaciones)">
                        <option value="">Todos</option>
                        <option value="borrador">En borrador</option>
                        <option value="cerrada">Cerradas</option>
                        <option value="anulada">Anuladas</option>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Desde</label>
                    <input type="date" id="ctar-fecha-desde" class="form-control form-control-sm shadow-none border" style="width:115px;">
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                    <input type="date" id="ctar-fecha-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;">
                </div>

                <!-- Buscador + botones: agrupados para que nunca se separen al hacer wrap -->
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="position-relative" style="width:440px;max-width:100%;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="ctar-buscar" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Cliente, documento, número o autorización..." autocomplete="off"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();CTAR_cargar();}">
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="CTAR_limpiarFiltros()">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm">
                                <i class="bi bi-search me-1"></i>Aplicar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats" id="ctar-stats-row">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-hourglass-split bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-warning">$<span id="ctar-stat-pendiente">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Por depositar</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="ctar-stat-cobros">0</div>
                        <div class="cmg-control-card__stat-label">Cobros pendientes</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-clock-history bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger"><span id="ctar-stat-dias">0</span></div>
                        <div class="cmg-control-card__stat-label">Días del más antiguo</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-check2-circle bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success">$<span id="ctar-stat-conciliado">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Conciliado (filtro)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-percent bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="ctar-stat-comision">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Comisiones</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjetas de listado: una por vista (solo una visible a la vez) ── -->
    <?php foreach ($listados as $vista => $cfg): ?>
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3 <?php echo $vista === 'pendientes' ? '' : 'd-none'; ?>"
         id="ctar-vista-<?php echo $vista; ?>">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Menú de columnas primero: favoritos.js toma de él el módulo donde guardar los anchos -->
                    <div class="btn-group btn-group-sm">
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas(
                            array_map(static fn($c) => $c[0], $cfg['columnas']),
                            $vista === 'pendientes' ? $vistaPend : $vistaConfig,
                            $cfg['ruta']
                        ) ?>
                        <button type="button" class="btn btn-outline-danger" onclick="CTAR_exportarPDF()" title="Exportar a PDF">
                            <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="CTAR_exportarExcel()" title="Exportar a Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                        </button>
                    </div>

                    <div class="btn-group btn-group-sm" role="group" aria-label="Vista del listado">
                        <button type="button" class="btn <?php echo $vista === 'pendientes' ? 'btn-primary' : 'btn-outline-primary'; ?>"
                                onclick="CTAR_setVista('pendientes')" title="Cobros con tarjeta que aún no aparecen en un estado de cuenta">
                            <i class="bi bi-hourglass-split"></i><span class="d-none d-md-inline"> Pendientes por depositar</span>
                            <span class="badge rounded-pill bg-warning text-dark ms-1 ctar-badge-pendientes">0</span>
                        </button>
                        <button type="button" class="btn <?php echo $vista === 'conciliaciones' ? 'btn-primary' : 'btn-outline-primary'; ?>"
                                onclick="CTAR_setVista('conciliaciones')" title="Conciliaciones registradas">
                            <i class="bi bi-list-check"></i><span class="d-none d-md-inline"> Conciliaciones</span>
                        </button>
                    </div>

                    <?php if ($puedeActualizar): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="CTAR_abrirConfig()"
                                title="Cuentas contables y valores por defecto de cada procesadora">
                            <i class="bi bi-gear"></i><span class="d-none d-md-inline"> Configuración</span>
                        </button>
                    <?php endif; ?>
                    <?php if ($puedeCrear): ?>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm" onclick="CTAR_nueva()" title="Nueva conciliación">
                            <i class="bi bi-plus-lg"></i><span class="d-none d-md-inline"> Nueva conciliación</span>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <small class="text-muted fw-medium" id="ctar-info-<?php echo $vista; ?>"></small>
                    <div class="btn-group btn-group-sm" id="ctar-pag-<?php echo $vista; ?>">
                        <button type="button" class="btn btn-outline-secondary" disabled data-pag="prev" title="Página anterior"><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="btn btn-outline-secondary" disabled data-pag="next" title="Página siguiente"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="ctar-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="<?php echo $cfg['tabla']; ?>"
                       style="min-width:<?php echo (int) $cfg['minWidth']; ?>px;">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($cfg['columnas'] as $col => [$etiqueta, $sort, $clases]): ?>
                                <?php if ($sort !== null): ?>
                                    <th class="<?php echo $clases; ?> sortable-header" data-col="<?php echo $col; ?>" data-sort="<?php echo $sort; ?>">
                                        <?php echo htmlspecialchars($etiqueta); ?> <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                                    </th>
                                <?php else: ?>
                                    <th class="<?php echo $clases; ?>" data-col="<?php echo $col; ?>"><?php echo htmlspecialchars($etiqueta); ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="<?php echo $cfg['tbody']; ?>">
                        <tr><td colspan="<?php echo count($cfg['columnas']); ?>" class="text-center py-5 text-muted"><?php echo $cfg['vacio']; ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/modal_conciliacion.php'; ?>
<?php include __DIR__ . '/modal_config.php'; ?>

<script>
    const CTAR_URL   = '<?php echo BASE_URL; ?>/modulos/conciliacion-tarjetas';
    const CTAR_PERM  = {
        crear:      <?php echo $puedeCrear ? 'true' : 'false'; ?>,
        actualizar: <?php echo $puedeActualizar ? 'true' : 'false'; ?>,
        eliminar:   <?php echo $puedeEliminar ? 'true' : 'false'; ?>
    };
    const CTAR_PROCESADORAS = <?php echo json_encode($procesadoras, JSON_UNESCAPED_UNICODE); ?>;
    const CTAR_DESTINOS     = <?php echo json_encode($destinos, JSON_UNESCAPED_UNICODE); ?>;
    // Estado inicial del orden de cada tabla (OrdenListado::aJson), para CMG_initSort.
    const CTAR_ORDEN = {
        pendientes:     { modulo: '<?php echo $rutaPrefsPend; ?>', sorts: <?php echo $ordenPendJson; ?> },
        conciliaciones: { modulo: '<?php echo $rutaModulo; ?>',    sorts: <?php echo $ordenJson; ?> }
    };
</script>
<script src="<?php echo BASE_URL; ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/modulos/conciliacion_tarjetas.js?v=<?= asset_ver('/js/modulos/conciliacion_tarjetas.js') ?>"></script>
