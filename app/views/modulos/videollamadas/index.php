<?php

/**
 * Listado del módulo Videollamadas.
 *
 * @var string $titulo
 * @var array  $perm
 * @var string $rutaModulo
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $totalPages
 * @var int    $perPage
 * @var string $buscar
 * @var string $ordenCol
 * @var string $ordenDir
 * @var array  $vistaConfig
 * @var array  $usuarios
 * @var int    $maxMesh
 * @var int    $idUsuario
 */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'fecha_inicio';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .videollamadas-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }
    .vc-row { cursor: pointer; }
    .vc-row:hover { background-color: rgba(0, 0, 0, .04); }
    .vc-td-titulo { max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Punto que late junto al título mientras la reunión está en curso. */
    .vc-punto-vivo {
        display: inline-block;
        width: 8px;
        height: 8px;
        margin-right: 6px;
        border-radius: 50%;
        background: #198754;
        animation: vc-latido 1.6s ease-in-out infinite;
    }
    @keyframes vc-latido {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%      { opacity: .35; transform: scale(.75); }
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-camera-video-fill me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?>
    </h5>
    <div class="d-flex align-items-center gap-2">
        <?php if ($perm['actualizar']): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="VC_abrirConfig()"
                    title="Límites de las reuniones de esta empresa">
                <i class="bi bi-gear me-1"></i> Configurar
            </button>
        <?php endif; ?>
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="VC_abrirModalNuevo()">
                <i class="bi bi-plus-lg me-1"></i> Nueva
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($avisoEntrada)): ?>
    <div class="alert alert-warning alert-dismissible fade show py-2 px-3 small" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= htmlspecialchars($avisoEntrada) ?>
        <button type="button" class="btn-close btn-sm py-2" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form id="frmBuscarVC" class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); window.VC_buscar(1);">
                <div class="input-group input-group-sm" style="width: 320px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="txtBuscarVC" class="form-control border-start-0 ps-0 shadow-none border"
                           placeholder="Buscar por título, código o anfitrión..."
                           value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                </div>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'titulo'              => 'Reunión',
                    'codigo'              => 'Código',
                    'tipo'                => 'Tipo',
                    'fecha_inicio'        => 'Fecha y hora',
                    'anfitrion_nombre'    => 'Anfitrión',
                    'total_participantes' => 'Participantes',
                    'estado'              => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?>
                        onclick="window.VC_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?>
                        onclick="window.VC_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="videollamadas-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <?php
                        $renderHeader = function (string $key, string $label, string $align = 'left', string $extraClass = '') use ($ordenCol, $ordenDir): void {
                            $isCur = ($ordenCol === $key);
                            $icon  = 'bi-arrow-down-up small text-muted';
                            if ($isCur) {
                                $icon = (strtoupper($ordenDir) === 'ASC') ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary';
                            }
                            $cls = match ($align) {
                                'center' => 'text-center',
                                'right'  => 'text-end',
                                default  => '',
                            };
                            echo '<th class="ps-3 py-2 sortable-header ' . $cls . ' ' . $extraClass . '" role="button" data-col="' . $key . '" onclick="window.VC_sort(\'' . $key . '\')">'
                               . $label . ' <i class="bi ' . $icon . ' ms-1"></i></th>';
                        };
                        ?>
                        <?php $renderHeader('titulo', 'Reunión'); ?>
                        <?php $renderHeader('codigo', 'Código'); ?>
                        <?php $renderHeader('tipo', 'Tipo'); ?>
                        <?php $renderHeader('fecha_inicio', 'Fecha y hora'); ?>
                        <th data-col="anfitrion_nombre" class="ps-3 py-2">Anfitrión</th>
                        <th data-col="total_participantes" class="py-2 text-center">Participantes</th>
                        <?php $renderHeader('estado', 'Estado', 'center', 'pe-3'); ?>
                    </tr>
                </thead>
                <tbody id="tbodyVideollamadas">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-camera-video fs-3 d-block mb-2"></i>No se encontraron reuniones.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php include __DIR__ . '/_fila.php'; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_sala.php'; ?>
<?php if ($perm['actualizar']) { include __DIR__ . '/modal_config.php'; } ?>

<script>
    window.VC_URL_BASE  = '<?= $urlBase ?>';
    // Base del enlace personal de cada invitado externo (lleva su token).
    window.VC_URL_INVITADO = '<?= $base ?>/videollamada-invitado';
    window.VC_USUARIOS  = <?= json_encode($usuarios, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    window.VC_ORDEN_COL = '<?= $ordenCol ?>';
    window.VC_ORDEN_DIR = '<?= $ordenDir ?>';
    window.VC_PAGE      = <?= $page ?>;
    window.VC_MAX_MESH  = <?= (int) $maxMesh ?>;
    window.VC_ID_USUARIO = <?= (int) $idUsuario ?>;
    window.VC_ES_SUPERADMIN = <?= !empty($esSuperadmin) ? 'true' : 'false' ?>;
    window.VC_PERM = <?= json_encode([
        'crear'      => (bool) $perm['crear'],
        'actualizar' => (bool) $perm['actualizar'],
        'eliminar'   => (bool) $perm['eliminar'],
    ]) ?>;
</script>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/videollamadas.js?v=<?= time() ?>"></script>
