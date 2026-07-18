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

$estadoBadge = function (string $estado): string {
    return match ($estado) {
        'BORRADOR'             => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">Borrador</span>',
        'PENDIENTE_APROBACION' => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Pend. aprobación</span>',
        'APROBADO'             => '<span class="badge bg-info bg-opacity-10 text-info border border-info">Aprobado</span>',
        'RECHAZADO'            => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Rechazado</span>',
        'GENERADO'             => '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Generado</span>',
        'CONFIRMADO'           => '<span class="badge bg-success bg-opacity-10 text-success border border-success">Confirmado</span>',
        'ANULADO'              => '<span class="badge bg-dark bg-opacity-10 text-dark border border-dark">Anulado</span>',
        default                => '<span class="badge bg-secondary">' . htmlspecialchars($estado) . '</span>',
    };
};

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
        <div class="d-flex align-items-center gap-2">
            <form class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); TR_buscar(1);">
                <div class="input-group input-group-sm" style="width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="tr-buscar" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar por número, tipo, estado…" value="<?= htmlspecialchars($buscar ?? '') ?>" autocomplete="off">
                    <?php if (!empty($buscar)): ?>
                        <a href="<?= $urlBase ?>/index" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero' => 'N°', 'fecha' => 'Fecha pago', 'tipo' => 'Tipo', 'banco' => 'Cuenta origen',
                    'monto' => 'Monto total', 'pagos' => 'Pagos', 'estado' => 'Estado', 'creado' => 'Creado por',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo);
                ?>
                <a href="<?= $urlBase ?>/exportPdf?b=<?= urlencode($buscar ?? '') ?>&sort=<?= urlencode($ordenCol ?? '') ?>&dir=<?= urlencode($ordenDir ?? '') ?>" target="_blank" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a href="<?= $urlBase ?>/exportExcel?b=<?= urlencode($buscar ?? '') ?>&sort=<?= urlencode($ordenCol ?? '') ?>&dir=<?= urlencode($ordenDir ?? '') ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-medium"><?= $total ?> registros</span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="TR_buscar(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="TR_buscar(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
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
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-bank fs-2 d-block mb-2"></i> No hay lotes de pago bancario registrados.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr style="cursor:pointer;" onclick="TR_abrirExistente(<?= (int) $r['id'] ?>)">
                            <td class="ps-3 fw-bold" data-col="numero">#<?= (int) $r['numero'] ?></td>
                            <td data-col="fecha"><?= $r['fecha_pago'] ? date('d-m-Y', strtotime($r['fecha_pago'])) : '-' ?></td>
                            <td data-col="tipo" class="text-capitalize"><?= htmlspecialchars(strtolower((string) $r['tipo_lote'])) ?></td>
                            <td class="small" data-col="banco"><?= htmlspecialchars($r['forma_pago_nombre'] ?? '-') ?></td>
                            <td class="text-end" data-col="monto">$ <?= number_format((float) $r['monto_total'], 2) ?></td>
                            <td class="text-center" data-col="pagos"><?= (int) $r['cantidad_pagos'] ?></td>
                            <td class="text-center" data-col="estado"><?= $estadoBadge($r['estado'] ?? 'BORRADOR') ?></td>
                            <td class="small text-muted" data-col="creado"><?= htmlspecialchars($r['creado_por_nombre'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Lote (nuevo o existente) -->
<div class="modal fade" id="tr-modal-lote" tabindex="-1" aria-hidden="true">
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
                        <label class="form-label small fw-bold">Banco (formato de archivo)</label>
                        <select id="tr-f-banco" class="form-select form-select-sm">
                            <option value="">Genérico (Excel)</option>
                            <?php foreach (($bancosDisponibles ?? []) as $b): ?>
                                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre_banco']) ?></option>
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
                                        <th>Beneficiario</th><th>Banco</th><th>Tipo Cuenta</th><th>N° Cuenta</th><th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody id="tr-selector-body"><tr><td colspan="6" class="text-center text-muted py-3">Cargando…</td></tr></tbody>
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
                    <button type="button" id="tr-btn-generar" class="btn btn-sm btn-outline-primary d-none" onclick="TR_generarArchivo()" title="Generar archivo"><i class="bi bi-file-earmark-arrow-down me-1"></i>Generar archivo</button>
                    <a href="#" id="tr-btn-descargar" class="btn btn-sm btn-outline-success d-none" title="Descargar archivo"><i class="bi bi-download me-1"></i>Descargar</a>
                    <button type="button" id="tr-btn-confirmar" class="btn btn-sm btn-outline-success d-none" onclick="TR_confirmarEnvio()" title="Confirmar envío al banco"><i class="bi bi-shield-check me-1"></i>Confirmar envío</button>
                    <div class="vr mx-1"></div>
                    <button type="button" id="tr-btn-anular" class="btn btn-sm btn-outline-dark d-none" onclick="TR_anular()" title="Anular lote"><i class="bi bi-slash-circle me-1"></i>Anular</button>
                </div>

                <div id="tr-detalle-cuerpo" class="small text-muted"></div>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" id="tr-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="TR_eliminar()"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" id="tr-btn-enviar-aprobacion" class="btn btn-outline-primary btn-sm d-none" onclick="TR_enviarAprobacion()"><i class="bi bi-send-check me-1"></i>Enviar a aprobación</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
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
</script>
<script src="<?= $base ?>/js/modulos/transferencias.js?v=<?= time() ?>"></script>
