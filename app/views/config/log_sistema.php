<?php
/**
 * Vista de Auditoría del sistema (consulta de log_sistema, solo lectura).
 * Nivel 2+: su empresa activa + eventos globales. Nivel 3: todo.
 */
$base = BASE_URL;
$nivel = $nivel ?? 1;
$opcionesFiltros = $opcionesFiltros ?? ['acciones' => [], 'tablas' => [], 'usuarios' => [], 'empresas' => []];
?>

<style>
    .log-scroll {
        max-height: calc(100dvh - 250px);
        overflow: auto;
    }
    .log-row { cursor: pointer; }
    .log-row:hover { background-color: rgba(0, 0, 0, .04); }
    #logFiltrosAyuda code {
        background: rgba(0,0,0,.05);
        padding: 0 4px;
        border-radius: 3px;
    }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-dark"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Bitácora de todo lo que ocurre en el sistema. Solo consulta.</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3 bg-white">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form id="logFormBuscar" class="input-group input-group-sm" style="width:320px" onsubmit="event.preventDefault(); LOGSIS_cambiarPagina(1);">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="logInputBuscar" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar…  usuario:juan  accion:eliminar" autocomplete="off">
                <button type="button" class="btn btn-outline-secondary" title="Ayuda de búsqueda" data-bs-toggle="collapse" data-bs-target="#logFiltrosAyuda"><i class="bi bi-question-lg"></i></button>
            </form>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" title="Exportar a PDF" onclick="LOGSIS_exportar('pdf')"><i class="bi bi-file-earmark-pdf text-danger"></i></button>
                <button type="button" class="btn btn-outline-secondary" title="Exportar a Excel" onclick="LOGSIS_exportar('excel')"><i class="bi bi-file-earmark-excel text-success"></i></button>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="logPaginationInfo" class="text-muted small fw-medium">0-0 / 0</span>
            <div id="logWrapperPagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="px-3 py-2 border-bottom bg-light d-flex flex-wrap align-items-end gap-2" id="logFiltros">
        <div>
            <label class="form-label small mb-0 text-muted">Usuario</label>
            <select id="fltUsuario" class="form-select form-select-sm" style="min-width:150px;height:30px;">
                <option value="">Todos</option>
                <?php foreach ($opcionesFiltros['usuarios'] as $u): ?>
                    <option value="<?= (int) $u['id_usuario'] ?>"><?= htmlspecialchars((string) $u['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($nivel >= 3 && !empty($opcionesFiltros['empresas'])): ?>
        <div>
            <label class="form-label small mb-0 text-muted">Empresa</label>
            <select id="fltEmpresa" class="form-select form-select-sm" style="min-width:160px;height:30px;">
                <option value="">Todas</option>
                <?php foreach ($opcionesFiltros['empresas'] as $e): ?>
                    <option value="<?= (int) $e['id_empresa'] ?>"><?= htmlspecialchars((string) $e['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div>
            <label class="form-label small mb-0 text-muted">Acción</label>
            <select id="fltAccion" class="form-select form-select-sm" style="min-width:130px;height:30px;">
                <option value="">Todas</option>
                <?php foreach ($opcionesFiltros['acciones'] as $a): ?>
                    <option value="<?= htmlspecialchars((string) $a['valor']) ?>"><?= htmlspecialchars((string) $a['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label small mb-0 text-muted">Módulo</label>
            <select id="fltTabla" class="form-select form-select-sm" style="min-width:150px;height:30px;">
                <option value="">Todos</option>
                <?php foreach ($opcionesFiltros['tablas'] as $t): ?>
                    <option value="<?= htmlspecialchars((string) $t['codigo']) ?>"><?= htmlspecialchars((string) $t['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label small mb-0 text-muted">Desde</label>
            <input type="date" id="fltDesde" class="form-control form-control-sm" style="height:30px;" value="<?= date('Y-01-01') ?>">
        </div>
        <div>
            <label class="form-label small mb-0 text-muted">Hasta</label>
            <input type="date" id="fltHasta" class="form-control form-control-sm" style="height:30px;" value="<?= date('Y-m-d') ?>">
        </div>

        <button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary btn-sm" style="height:30px;" title="Limpiar filtros">
            <i class="bi bi-x-circle me-1"></i> Limpiar
        </button>
    </div>

    <div id="logFiltrosAyuda" class="collapse border-bottom">
        <div class="px-3 py-2 small text-muted">
            Usá la barra de filtros de arriba o escribí aquí. Claves disponibles:
            <code>usuario:nombre</code> · <code>accion:crear</code> ·
            <code>registro:123</code> · <code>ip:190.1</code> ·
            <code>fecha:2026-07-01..2026-07-08</code> · <code>fecha:&gt;=2026-07-01</code>.
            Usá <code>-clave:valor</code> para negar y comillas para valores con espacios.
        </div>
    </div>

    <div class="card-body p-0">
        <div class="log-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm sticky-top" style="z-index: 1;">
                    <tr>
                        <th class="ps-3 log-sort" data-col="created_at" role="button" style="width: 14%;">Fecha <i class="bi bi-arrow-down-short"></i></th>
                        <th class="log-sort" data-col="usuario" role="button" style="width: 16%;">Usuario</th>
                        <th class="log-sort" data-col="empresa" role="button" style="width: 18%;">Empresa</th>
                        <th class="log-sort" data-col="accion" role="button" style="width: 12%;">Acción</th>
                        <th class="log-sort" data-col="tabla" role="button" style="width: 18%;">Módulo</th>
                        <th class="text-center" style="width: 8%;">Registro</th>
                        <th style="width: 14%;">IP</th>
                    </tr>
                </thead>
                <tbody id="tbodyLogSistema">
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <span class="spinner-border spinner-border-sm text-primary me-2"></span> Cargando auditoría...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal detalle -->
<div class="modal fade" id="modalLogDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold mb-0"><i class="bi bi-clock-history me-2 text-dark"></i> Detalle del evento</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body px-4 py-3" id="logDetalleBody">
                <div class="text-center py-4">
                    <span class="spinner-border spinner-border-sm text-primary"></span> Cargando...
                </div>
            </div>
            <div class="modal-footer bg-light border-top px-4 py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
</script>
<script src="<?= $base ?>/js/config/log_sistema.js?v=<?= @filemtime(MVC_ROOT . '/public/js/config/log_sistema.js') ?: time() ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof LOGSIS_cargarListado === 'function') LOGSIS_cargarListado(1);
    });
</script>
