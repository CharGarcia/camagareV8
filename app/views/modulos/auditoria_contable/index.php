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
/** @var ?string $fechaDesde */
/** @var ?string $fechaHasta */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array $vistaConfig */
/** @var array $resumen */
/** @var string $vista */
/** @var array $contadores */
/** @var array $origenes */
/** @var array $origenesRegenerables */
/** @var array $origenLabels */
/** @var array $tipoLabels */
/** @var array $corridas */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = max(1, $totalPages ?? 1);
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'detectado_at';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$resumen    = $resumen ?? [];
$origenes   = $origenes ?? [];
$origenesRegenerables = $origenesRegenerables ?? [];
$vista      = $vista ?? 'pendientes';
$contadores = $contadores ?? ['pendientes' => 0, 'corregidas' => 0];
$origenLabels = $origenLabels ?? [];
$tipoLabels   = $tipoLabels ?? [];
$corridas     = $corridas ?? [];
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

// Etiquetas de tipo (para tarjetas-resumen) y colores
$tipoOrden = ['faltante','duplicado','monto_no_coincide','descuadrado','cab_vs_detalle','huerfano','estado_incoherente','ambiente_incoherente','monto_informativo'];
$tipoColor = [
    'faltante' => 'danger', 'duplicado' => 'warning', 'monto_no_coincide' => 'warning',
    'descuadrado' => 'danger', 'cab_vs_detalle' => 'danger', 'huerfano' => 'secondary',
    'estado_incoherente' => 'primary', 'ambiente_incoherente' => 'info',
    // Informativo: se muestra para revisión pero no cuenta como error de cuadre.
    'monto_informativo' => 'secondary',
];
$totalIncidencias = array_sum($resumen);
?>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>

<style>
    .aud-scroll { max-height: calc(100dvh - 320px); overflow-y: auto; }
    .aud-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .aud-card-resumen { cursor: pointer; transition: transform .12s; }
    .aud-card-resumen:hover { transform: translateY(-2px); }
    .aud-card-resumen.activa { outline: 2px solid var(--bs-primary); }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
</div>

<!-- Barra de filtros: el período y los orígenes acotan TANTO el listado como las 3 acciones -->
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-3 bg-light bg-opacity-50">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Año</label>
                <select id="audAnio" class="form-select form-select-sm shadow-none">
                    <?php
                    $anioActual = (int) date('Y');
                    $anioSel = !empty($fechaDesde) ? (int) substr((string) $fechaDesde, 0, 4) : $anioActual;
                    for ($a = $anioActual + 1; $a >= $anioActual - 6; $a--): ?>
                        <option value="<?= $a ?>" <?= $a === $anioSel ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Mes</label>
                <select id="audMes" class="form-select form-select-sm shadow-none">
                    <option value="0">Todo el año</option>
                    <?php
                    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                    foreach ($meses as $i => $m): ?>
                        <option value="<?= $i + 1 ?>"><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Desde</label>
                <input type="date" id="audFechaDesde" class="form-control form-control-sm shadow-none"
                       value="<?= htmlspecialchars((string) ($fechaDesde ?? '')) ?>" title="Fecha desde (libre)">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Hasta</label>
                <input type="date" id="audFechaHasta" class="form-control form-control-sm shadow-none"
                       value="<?= htmlspecialchars((string) ($fechaHasta ?? '')) ?>" title="Fecha hasta (libre)">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">
                    Orígenes
                    <span class="text-muted fw-normal" id="audOrigenResumen">(todos)</span>
                </label>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start shadow-none text-truncate"
                            type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="audOrigenBtn">
                        Todos
                    </button>
                    <div class="dropdown-menu p-2" style="max-height:280px; overflow-y:auto; min-width:250px;">
                        <div class="d-flex gap-1 mb-2">
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="audOrigenTodos">Todos</button>
                            <span class="text-muted small">·</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="audOrigenNinguno">Ninguno</button>
                        </div>
                        <?php foreach ($origenes as $o): ?>
                            <label class="dropdown-item px-2 py-1 small d-flex align-items-center gap-2" style="cursor:pointer;">
                                <input type="checkbox" class="form-check-input m-0 js-aud-origen" value="<?= htmlspecialchars($o) ?>">
                                <span><?= htmlspecialchars($origenLabels[$o] ?? $o) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">&nbsp;</label>
                <button type="button" id="audLimpiarFechas" class="btn btn-sm btn-outline-secondary w-100 text-nowrap" title="Limpiar período y orígenes">
                    <i class="bi bi-x-lg me-1"></i> Limpiar filtros
                </button>
            </div>
        </div>

        <!-- Las 3 acciones, separadas y explicadas -->
        <div class="d-flex gap-2 flex-wrap mt-3 pt-3 border-top">
            <button type="button" id="btnEjecutarAuditoria" class="btn btn-primary btn-sm px-3 shadow-sm"
                    title="Solo revisa y registra hallazgos. NO modifica ningún asiento contable.">
                <i class="bi bi-search me-1"></i> Ejecutar auditoría
            </button>
            <?php if (!empty($perm['eliminar'])): ?>
                <button type="button" id="btnRegenerar" class="btn btn-warning btn-sm px-3 shadow-sm text-dark"
                        data-bs-toggle="modal" data-bs-target="#modalRegenerar"
                        title="Anula y vuelve a crear los asientos del filtro con la configuración contable actual.">
                    <i class="bi bi-arrow-repeat me-1"></i> Generar asientos
                </button>
                <button type="button" id="btnAnularMasivo" class="btn btn-outline-danger btn-sm px-3 shadow-sm"
                        data-bs-toggle="modal" data-bs-target="#modalAnular"
                        title="Anula los asientos del filtro y deja los documentos SIN contabilidad. Reversible.">
                    <i class="bi bi-slash-circle me-1"></i> Eliminar asientos
                </button>
            <?php endif; ?>
            <span class="small text-muted align-self-center ms-1">
                <i class="bi bi-info-circle me-1"></i>Las tres acciones usan el período y los orígenes seleccionados arriba.
            </span>
        </div>
    </div>
</div>

<!-- Tarjetas-resumen por tipo de hallazgo -->
<div class="row g-2 mb-3" id="audResumen">
    <?php foreach ($tipoOrden as $t): $n = $resumen[$t] ?? 0; $color = $tipoColor[$t] ?? 'secondary'; ?>
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm rounded-3 aud-card-resumen h-100 bg-<?= $color ?> bg-opacity-10 border border-<?= $color ?> border-opacity-25" data-tipo="<?= $t ?>">
                <div class="card-body py-2 px-3">
                    <div class="fs-4 fw-bold text-<?= $color ?>"><?= (int) $n ?></div>
                    <div class="small text-<?= $color ?>"><?= htmlspecialchars($tipoLabels[$t] ?? $t) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Pestañas: lo corregido sale del listado principal y queda en su propio histórico -->
<ul class="nav nav-tabs mb-0" id="audTabs">
    <li class="nav-item">
        <button class="nav-link <?= $vista === 'pendientes' ? 'active' : '' ?>" data-vista="pendientes" type="button">
            <i class="bi bi-exclamation-triangle me-1"></i> Por corregir
            <span class="badge bg-danger bg-opacity-75 ms-1" id="audCntPendientes"><?= (int) ($contadores['pendientes'] ?? 0) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $vista === 'corregidas' ? 'active' : '' ?>" data-vista="corregidas" type="button">
            <i class="bi bi-check2-circle me-1"></i> Corregidas
            <span class="badge bg-success bg-opacity-75 ms-1" id="audCntCorregidas"><?= (int) ($contadores['corregidas'] ?? 0) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $vista === 'todas' ? 'active' : '' ?>" data-vista="todas" type="button">
            <i class="bi bi-list-ul me-1"></i> Todas
        </button>
    </li>
</ul>

<div class="card w-100 border-0 shadow-sm rounded-3 rounded-top-0">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <input type="text" id="audBuscar" class="form-control form-control-sm" style="width:360px"
                   placeholder="Buscar… (p. ej. tipo:faltante origen:factura_venta revision:pendiente)"
                   value="<?= htmlspecialchars($buscar) ?>">
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'tipo'            => 'Tipo',
                    'origen'          => 'Origen',
                    'documento'       => 'Documento',
                    'entidad'         => 'Cliente / Proveedor',
                    'asiento'         => 'ID asiento',
                    'asiento_numero'  => 'N° comprobante',
                    'asiento_tipo'    => 'Tipo comp.',
                    'asiento_fecha'   => 'Fecha asiento',
                    'asiento_estado'  => 'Estado asiento',
                    'monto_documento' => 'Monto doc.',
                    'monto_asiento'   => 'Monto asiento',
                    'diferencia'      => 'Diferencia',
                    'fecha'           => 'Fecha doc.',
                    'revision'        => 'Revisión',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="audPaginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div class="btn-group btn-group-sm">
                <button type="button" id="audPrev" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" id="audNext" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="aud-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <?php
                        $ths = [
                            'tipo' => 'Tipo', 'origen' => 'Origen', 'documento' => 'Documento',
                            'entidad' => 'Cliente / Proveedor', 'asiento' => 'ID asiento',
                            'asiento_numero' => 'N° comprobante', 'asiento_tipo' => 'Tipo comp.',
                            'asiento_fecha' => 'Fecha asiento', 'asiento_estado' => 'Estado asiento',
                            'monto_documento' => 'Monto doc.', 'monto_asiento' => 'Monto asiento', 'diferencia' => 'Diferencia',
                            'fecha' => 'Fecha doc.', 'revision' => 'Revisión',
                        ];
                        $sortMap = [
                            'tipo' => 'tipo_hallazgo', 'origen' => 'modulo_origen', 'documento' => 'documento_numero',
                            'entidad' => 'entidad_nombre', 'asiento' => 'id_asiento',
                            'asiento_numero' => 'id_asiento', 'asiento_tipo' => 'id_asiento',
                            'asiento_fecha' => 'id_asiento', 'asiento_estado' => 'id_asiento',
                            'monto_documento' => 'diferencia', 'monto_asiento' => 'diferencia',
                            'diferencia' => 'diferencia', 'fecha' => 'fecha_documento', 'revision' => 'estado_revision',
                        ];
                        foreach ($ths as $col => $label):
                            $sortKey = $sortMap[$col];
                            $icon = $ordenCol === $sortKey
                                ? ($ordenDir === 'ASC' ? 'bi-sort-down-alt text-primary' : 'bi-sort-up text-primary')
                                : 'bi-arrow-down-up small text-muted';
                        ?>
                            <th class="sortable-header" role="button" data-sort="<?= $sortKey ?>" data-col="<?= $col ?>">
                                <?= $label ?> <i class="bi <?= $icon ?> ms-1"></i>
                            </th>
                        <?php endforeach; ?>
                        <th class="text-end pe-3" data-col="acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody id="audTbody">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="15" class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-check fs-3 d-block mb-2"></i>
                            <?= $vista === 'corregidas'
                                ? 'Todavía no hay incidencias corregidas.'
                                : 'Sin incidencias pendientes. Pulse «Ejecutar auditoría» para verificar.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?= $filasHtml ?? "" ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Historial de corridas -->
<details class="mt-3">
    <summary class="text-muted small">Historial de ejecuciones (<?= count($corridas) ?>)</summary>
    <div class="table-responsive mt-2">
        <table class="table table-sm table-bordered small mb-0">
            <thead class="table-light"><tr>
                <th>Fecha</th><th>Tipo</th><th>Origen</th><th>Detectadas</th><th>Anulados</th><th>Regenerados</th><th>Omitidos</th><th>Estado</th><th>Mensaje</th><th>Usuario</th>
            </tr></thead>
            <tbody>
                <?php foreach ($corridas as $c): ?>
                    <tr>
                        <td><?= !empty($c['ejecutado_at']) ? date('d-m-Y H:i:s', strtotime((string) $c['ejecutado_at'])) : '' ?></td>
                        <td><?= htmlspecialchars((string) $c['tipo_corrida']) ?></td>
                        <td><?= htmlspecialchars((string) ($origenLabels[$c['modulo_origen']] ?? $c['modulo_origen'] ?? 'Todos')) ?></td>
                        <td class="text-end"><?= (int) $c['total_detectadas'] ?></td>
                        <td class="text-end"><?= (int) $c['total_anulados'] ?></td>
                        <td class="text-end"><?= (int) $c['total_regenerados'] ?></td>
                        <td class="text-end"><?= (int) $c['total_omitidos'] ?></td>
                        <td><?= htmlspecialchars((string) $c['estado']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars((string) ($c['mensaje'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($c['ejecutado_por'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>

<!-- Modal: Regeneración masiva -->
<div class="modal fade" id="modalRegenerar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat text-danger me-2"></i>Generar asientos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Esta acción <strong>anula</strong> los asientos del origen elegido y los <strong>vuelve a generar</strong> con la configuración actual.
                    No se tocan los asientos de períodos contables cerrados, los de la migración,
                    ni los de <strong>tipo Diario</strong> (asientos manuales y de activos fijos).
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Origen</label>
                    <select id="regOrigen" class="form-select form-select-sm">
                        <option value="__todos__">Toda la contabilidad (todos los orígenes, excepto Diario)</option>
                        <?php foreach (($origenesRegenerables ?? $origenes) as $o): ?>
                            <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($origenLabels[$o] ?? $o) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small">Solo se listan los módulos que saben rehacer su asiento.</div>
                </div>
                <div class="row g-2">
                    <div class="col">
                        <label class="form-label small fw-semibold">Desde (opcional)</label>
                        <input type="date" id="regDesde" class="form-control form-control-sm">
                    </div>
                    <div class="col">
                        <label class="form-label small fw-semibold">Hasta (opcional)</label>
                        <input type="date" id="regHasta" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="regFaltantes" checked>
                    <label class="form-check-label small" for="regFaltantes">
                        Al terminar, generar también los asientos <strong>faltantes</strong>
                        (documentos vigentes que nunca tuvieron asiento)
                    </label>
                </div>

                <!-- Progreso de la corrida (se muestra al ejecutar) -->
                <div id="regProgresoBox" class="mt-3 d-none">
                    <div class="d-flex justify-content-between align-items-center small fw-semibold mb-1">
                        <span id="regProgresoPaso" class="text-muted">Preparando…</span>
                        <span id="regProgresoPct" class="text-muted">0%</span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div id="regProgresoBarra" class="progress-bar progress-bar-striped progress-bar-animated bg-danger"
                             role="progressbar" style="width:0%"></div>
                    </div>
                    <div id="regProgresoLog" class="border rounded-3 bg-light mt-2 p-2 small text-muted"
                         style="max-height:180px;overflow:auto;font-family:monospace;font-size:.72rem"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCerrarRegenerar" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarRegenerar" class="btn btn-danger btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Generar asientos</button>
            </div>
        </div>
    </div>
</div>

<!-- Anulación masiva: solo anula, NO regenera. Deja los documentos sin contabilidad. -->
<div class="modal fade" id="modalAnular" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-slash-circle text-danger me-2"></i>Eliminar asientos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger small">
                    <strong><i class="bi bi-exclamation-octagon me-1"></i>Qué hace y qué repercute</strong>
                    <ul class="mb-1 mt-2 ps-3">
                        <li><strong>Anula</strong> los asientos del período y orígenes seleccionados y <strong>desvincula</strong> sus documentos.</li>
                        <li>Los documentos quedan <strong>SIN contabilidad</strong> hasta que se regeneren: sus valores desaparecen de Balance, Estado de Resultados y Mayores.</li>
                        <li>Es <strong>reversible</strong>: los asientos quedan marcados como anulados (no se borran), y se pueden volver a generar con «Generar asientos».</li>
                        <li><strong>No</strong> se tocan: períodos contables cerrados, asientos de <strong>tipo Diario</strong> (manuales y de activos fijos), ni los insertados por la migración.</li>
                    </ul>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Origen</label>
                    <select id="anuOrigen" class="form-select form-select-sm">
                        <option value="__todos__">Todos los orígenes (excepto Diario)</option>
                        <?php foreach ($origenes as $o): ?>
                            <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($origenLabels[$o] ?? $o) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small">Se precarga con lo seleccionado en la barra de filtros.</div>
                </div>
                <div class="row g-2">
                    <div class="col">
                        <label class="form-label small fw-semibold">Desde (opcional)</label>
                        <input type="date" id="anuDesde" class="form-control form-control-sm">
                    </div>
                    <div class="col">
                        <label class="form-label small fw-semibold">Hasta (opcional)</label>
                        <input type="date" id="anuHasta" class="form-control form-control-sm">
                    </div>
                </div>

                <div id="anuProgresoBox" class="mt-3 d-none">
                    <div class="d-flex justify-content-between align-items-center small fw-semibold mb-1">
                        <span id="anuProgresoPaso" class="text-muted">Preparando…</span>
                        <span id="anuProgresoPct" class="text-muted">0%</span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div id="anuProgresoBarra" class="progress-bar progress-bar-striped progress-bar-animated bg-danger"
                             role="progressbar" style="width:0%"></div>
                    </div>
                    <div id="anuProgresoLog" class="border rounded-3 bg-light mt-2 p-2 small text-muted"
                         style="max-height:180px;overflow:auto;font-family:monospace;font-size:.72rem"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCerrarAnular" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarAnular" class="btn btn-danger btn-sm"><i class="bi bi-slash-circle me-1"></i>Eliminar asientos</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Resolver duplicados -->
<div class="modal fade" id="modalDuplicados" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-files text-warning me-2"></i>Resolver duplicado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Asientos vivos del mismo documento. Anule los sobrantes y conserve uno.</p>
                <div id="dupListado" class="table-responsive"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Marcar revisión -->
<div class="modal fade" id="modalRevision" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-check2-square text-primary me-2"></i>Marcar revisión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="revIncidenciaId">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Estado</label>
                    <select id="revEstado" class="form-select form-select-sm">
                        <option value="revisada">Revisada</option>
                        <option value="justificada">Justificada</option>
                        <option value="pendiente">Pendiente</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Nota (opcional)</label>
                    <textarea id="revNota" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnGuardarRevision" class="btn btn-primary btn-sm">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.AUD_CONFIG = {
        base: '<?= $base ?>',
        rutaModulo: '<?= $rutaModulo ?>',
        urlBase: '<?= $urlBase ?>',
        perm: <?= json_encode($perm) ?>,
        vista: '<?= $vista ?>',
        ordenCol: '<?= $ordenCol ?>',
        ordenDir: '<?= $ordenDir ?>',
        page: <?= (int) $page ?>,
        totalPages: <?= (int) $totalPages ?>
    };
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/auditoria_contable.js?v=<?= time() ?>"></script>
