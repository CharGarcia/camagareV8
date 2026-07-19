<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var string $buscar */

$base = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;
$rows = $rows ?? [];
$buscar = $buscar ?? '';
?>

<style>
    .afc-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .afc-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .afc-dropdown { position: absolute; z-index: 1080; max-height: 220px; overflow-y: auto; display: none; width: 100%; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="AFC_abrirModal()">
                <i class="bi bi-plus-lg me-1"></i> Nueva Categoría
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form id="frmBuscarAFC" class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); window.AFC_buscar();">
            <div class="input-group input-group-sm" style="width: 320px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="txtBuscarAFC" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar por nombre..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="afc-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" data-col="nombre">Categoría</th>
                        <th class="text-end" data-col="porcentaje">% Anual</th>
                        <th data-col="cuenta_activo">Cuenta Activo</th>
                        <th data-col="cuenta_dep_acum">Cuenta Dep. Acumulada</th>
                        <th data-col="cuenta_gasto">Cuenta Gasto Depreciación</th>
                        <th class="text-center pe-3" data-col="estado">Estado</th>
                    </tr>
                </thead>
                <tbody id="tbodyAFC">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>No hay categorías registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            // Postgres vía PDO devuelve boolean como 't'/'f': empty('f') sería false, hay que comparar explícito.
                            $estadoActivo = ($r['estado'] === true || $r['estado'] === 't' || $r['estado'] === '1' || $r['estado'] === 1);
                            $estCls = $estadoActivo ? 'bg-success bg-opacity-10 text-success border-success' : 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                            $estTxt = $estadoActivo ? 'Activa' : 'Inactiva';
                            ?>
                            <tr role="button" onclick="AFC_abrirModal(<?= (int) $r['id'] ?>)">
                                <td class="ps-3" data-col="nombre"><?= htmlspecialchars($r['nombre']) ?></td>
                                <td class="text-end" data-col="porcentaje"><?= number_format((float) $r['porcentaje_depreciacion_anual'], 2) ?>%</td>
                                <td data-col="cuenta_activo"><?= htmlspecialchars(($r['cuenta_activo_codigo'] ?? '') . ' - ' . ($r['cuenta_activo_nombre'] ?? '')) ?></td>
                                <td data-col="cuenta_dep_acum"><?= htmlspecialchars(($r['cuenta_dep_acum_codigo'] ?? '') . ' - ' . ($r['cuenta_dep_acum_nombre'] ?? '')) ?></td>
                                <td data-col="cuenta_gasto"><?= htmlspecialchars(($r['cuenta_gasto_codigo'] ?? '') . ' - ' . ($r['cuenta_gasto_nombre'] ?? '')) ?></td>
                                <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estCls ?> border border-opacity-25"><?= $estTxt ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Categoría -->
<div class="modal fade" id="modalAFC" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i><span id="afcModalTitulo">Nueva Categoría de Activos Fijos</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAFC">
                <input type="hidden" name="id" id="afc-id" value="">
                <div class="modal-body p-3">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nombre de la Categoría</label>
                            <input type="text" name="nombre" id="afc-nombre" class="form-control form-control-sm" placeholder="Ej. Equipo de Cómputo" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">% Depreciación Anual</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0" max="100" name="porcentaje_depreciacion_anual" id="afc-porcentaje" class="form-control text-end" placeholder="10.00" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Cuentas contables que usará el asiento de depreciación de esta categoría.</p>
                    <div class="row g-3">
                        <div class="col-md-4 position-relative">
                            <label class="form-label small fw-bold">Cuenta de Activo</label>
                            <input type="text" id="afc-cuenta-activo-txt" class="form-control form-control-sm" placeholder="Buscar cuenta..." autocomplete="off" required>
                            <input type="hidden" name="id_cuenta_activo" id="afc-cuenta-activo-id">
                            <div class="list-group afc-dropdown" id="afc-cuenta-activo-dropdown"></div>
                        </div>
                        <div class="col-md-4 position-relative">
                            <label class="form-label small fw-bold">Cuenta Depreciación Acumulada</label>
                            <input type="text" id="afc-cuenta-dep-txt" class="form-control form-control-sm" placeholder="Buscar cuenta..." autocomplete="off" required>
                            <input type="hidden" name="id_cuenta_depreciacion_acumulada" id="afc-cuenta-dep-id">
                            <div class="list-group afc-dropdown" id="afc-cuenta-dep-dropdown"></div>
                        </div>
                        <div class="col-md-4 position-relative">
                            <label class="form-label small fw-bold">Cuenta Gasto Depreciación</label>
                            <input type="text" id="afc-cuenta-gasto-txt" class="form-control form-control-sm" placeholder="Buscar cuenta..." autocomplete="off" required>
                            <input type="hidden" name="id_cuenta_gasto_depreciacion" id="afc-cuenta-gasto-id">
                            <div class="list-group afc-dropdown" id="afc-cuenta-gasto-dropdown"></div>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Observaciones</label>
                            <input type="text" name="observaciones" id="afc-observaciones" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="estado" id="afc-estado" checked>
                                <label class="form-check-label small fw-bold" for="afc-estado">Categoría activa</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                    <button type="button" id="afc-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.AFC_eliminar()">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" id="afc-btn-guardar" class="btn btn-primary btn-sm px-4" onclick="window.AFC_guardar()">
                            <i class="bi bi-check-lg me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.AFC_URL_BASE = '<?= $urlBase ?>';
    window.AFC_CUENTAS_URL = '<?= $base ?>/modulos/plan-cuentas';
    window.AFC_PERM = <?= json_encode($perm) ?>;
</script>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/activos_fijos_categorias.js?v=<?= time() ?>"></script>
