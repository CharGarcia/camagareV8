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
/** @var array $resumen */
/** @var array $origenes */
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
$origenLabels = $origenLabels ?? [];
$tipoLabels   = $tipoLabels ?? [];
$corridas     = $corridas ?? [];
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

// Etiquetas de tipo (para tarjetas-resumen) y colores
$tipoOrden = ['faltante','duplicado','monto_no_coincide','descuadrado','cab_vs_detalle','huerfano','estado_incoherente','ambiente_incoherente'];
$tipoColor = [
    'faltante' => 'danger', 'duplicado' => 'warning', 'monto_no_coincide' => 'warning',
    'descuadrado' => 'danger', 'cab_vs_detalle' => 'danger', 'huerfano' => 'secondary',
    'estado_incoherente' => 'primary', 'ambiente_incoherente' => 'info',
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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <select id="audOrigenAuditar" class="form-select form-select-sm" style="width:auto" title="Limitar a un origen">
            <option value="">Todos los orígenes</option>
            <?php foreach ($origenes as $o): ?>
                <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($origenLabels[$o] ?? $o) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="btnEjecutarAuditoria" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-play-circle me-1"></i> Ejecutar auditoría
        </button>
        <?php if (!empty($perm['eliminar'])): ?>
            <button type="button" id="btnRegenerar" class="btn btn-outline-danger btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRegenerar">
                <i class="bi bi-arrow-repeat me-1"></i> Regenerar asientos
            </button>
        <?php endif; ?>
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

<div class="card w-100 border-0 shadow-sm rounded-3">
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
                    'asiento'         => 'Asiento',
                    'monto_documento' => 'Monto doc.',
                    'monto_asiento'   => 'Monto asiento',
                    'diferencia'      => 'Diferencia',
                    'fecha'           => 'Fecha',
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
                            'tipo' => 'Tipo', 'origen' => 'Origen', 'documento' => 'Documento', 'asiento' => 'Asiento',
                            'monto_documento' => 'Monto doc.', 'monto_asiento' => 'Monto asiento', 'diferencia' => 'Diferencia',
                            'fecha' => 'Fecha', 'revision' => 'Revisión',
                        ];
                        $sortMap = [
                            'tipo' => 'tipo_hallazgo', 'origen' => 'modulo_origen', 'documento' => 'id_documento',
                            'asiento' => 'id_asiento', 'monto_documento' => 'diferencia', 'monto_asiento' => 'diferencia',
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
                        <tr><td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-check fs-3 d-block mb-2"></i>
                            Sin incidencias. Pulse «Ejecutar auditoría» para verificar.
                        </td></tr>
                    <?php else: ?>
                        <?php
                        // Render inicial server-side reutilizando la misma estructura del searchAjax.
                        $tipoClase = [
                            'faltante' => 'bg-danger bg-opacity-10 text-danger border-danger',
                            'duplicado' => 'bg-warning bg-opacity-10 text-warning border-warning',
                            'monto_no_coincide' => 'bg-warning bg-opacity-10 text-warning border-warning',
                            'descuadrado' => 'bg-danger bg-opacity-10 text-danger border-danger',
                            'cab_vs_detalle' => 'bg-danger bg-opacity-10 text-danger border-danger',
                            'huerfano' => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                            'estado_incoherente' => 'bg-primary bg-opacity-10 text-primary border-primary',
                            'ambiente_incoherente' => 'bg-info bg-opacity-10 text-info border-info',
                        ];
                        $revClase = [
                            'pendiente' => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                            'revisada' => 'bg-info bg-opacity-10 text-info border-info',
                            'justificada' => 'bg-success bg-opacity-10 text-success border-success',
                            'resuelta' => 'bg-success bg-opacity-10 text-success border-success',
                        ];
                        $revLabel = ['pendiente'=>'Pendiente','revisada'=>'Revisada','justificada'=>'Justificada','resuelta'=>'Resuelta'];
                        foreach ($rows as $r):
                            $tipo = (string) $r['tipo_hallazgo'];
                            $rev  = (string) ($r['estado_revision'] ?? 'pendiente');
                            $origen = (string) $r['modulo_origen'];
                        ?>
                            <tr data-id="<?= (int) $r['id'] ?>" data-tipo="<?= htmlspecialchars($tipo) ?>"
                                data-origen="<?= htmlspecialchars($origen) ?>"
                                data-doc="<?= (int) ($r['id_documento'] ?? 0) ?>" data-asiento="<?= (int) ($r['id_asiento'] ?? 0) ?>">
                                <td data-col="tipo"><span class="badge <?= $tipoClase[$tipo] ?? 'bg-secondary' ?> border"><?= htmlspecialchars($tipoLabels[$tipo] ?? $tipo) ?></span></td>
                                <td data-col="origen"><?= htmlspecialchars($origenLabels[$origen] ?? $origen) ?></td>
                                <td data-col="documento" class="text-center"><?= $r['id_documento'] !== null ? '#' . (int) $r['id_documento'] : '—' ?></td>
                                <td data-col="asiento" class="text-center"><?= $r['id_asiento'] !== null ? '#' . (int) $r['id_asiento'] : '—' ?></td>
                                <td data-col="monto_documento" class="text-end"><?= $r['monto_documento'] !== null ? number_format((float) $r['monto_documento'], 2) : '—' ?></td>
                                <td data-col="monto_asiento" class="text-end"><?= $r['monto_asiento'] !== null ? number_format((float) $r['monto_asiento'], 2) : '—' ?></td>
                                <td data-col="diferencia" class="text-end"><?= $r['diferencia'] !== null ? number_format((float) $r['diferencia'], 2) : '—' ?></td>
                                <td data-col="fecha" class="text-center"><?= !empty($r['fecha_documento']) ? date('d-m-Y', strtotime((string) $r['fecha_documento'])) : '—' ?></td>
                                <td data-col="revision"><span class="badge <?= $revClase[$rev] ?? 'bg-secondary' ?> border"><?= $revLabel[$rev] ?? $rev ?></span><div class="small text-muted"><?= htmlspecialchars((string) ($r['detalle'] ?? '')) ?></div></td>
                                <td data-col="acciones" class="text-end pe-3 text-nowrap">
                                    <?php if ($tipo === 'faltante' && !empty($perm['crear'])): ?>
                                        <button class="btn btn-sm btn-outline-success js-aud-generar" title="Generar asiento"><i class="bi bi-magic"></i></button>
                                    <?php endif; ?>
                                    <?php if ($tipo === 'duplicado' && !empty($perm['eliminar'])): ?>
                                        <button class="btn btn-sm btn-outline-warning js-aud-duplicado" title="Resolver duplicado"><i class="bi bi-files"></i></button>
                                    <?php endif; ?>
                                    <?php if ($tipo === 'ambiente_incoherente' && !empty($perm['actualizar'])): ?>
                                        <button class="btn btn-sm btn-outline-info js-aud-ambiente" title="Corregir ambiente"><i class="bi bi-arrow-repeat"></i></button>
                                    <?php endif; ?>
                                    <?php if (!empty($perm['actualizar'])): ?>
                                        <button class="btn btn-sm btn-outline-primary js-aud-revisar" title="Marcar revisión"><i class="bi bi-check2-square"></i></button>
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
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat text-danger me-2"></i>Regenerar asientos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Esta acción <strong>anula</strong> los asientos del origen elegido y los <strong>vuelve a generar</strong> con la configuración actual.
                    No se tocan los asientos de períodos contables cerrados.
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Origen</label>
                    <select id="regOrigen" class="form-select form-select-sm">
                        <?php foreach ($origenes as $o): ?>
                            <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($origenLabels[$o] ?? $o) ?></option>
                        <?php endforeach; ?>
                    </select>
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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarRegenerar" class="btn btn-danger btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Regenerar</button>
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
        ordenCol: '<?= $ordenCol ?>',
        ordenDir: '<?= $ordenDir ?>',
        page: <?= (int) $page ?>,
        totalPages: <?= (int) $totalPages ?>
    };
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/auditoria_contable.js?v=<?= time() ?>"></script>
