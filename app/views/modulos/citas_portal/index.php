<?php
$urlCfg      = BASE_URL . '/modulos/citas-configuracion?tab=portal';
$urlAgenda   = BASE_URL . '/modulos/citas-agenda';
$tienePortal  = !empty($portal['slug']);
$portalActivo = $tienePortal && (bool) $portal['activo'];
$vistaConfig  = $vistaConfig ?? [];

$ESTADO_COLOR = [
    'pendiente'  => 'warning',
    'confirmada' => 'primary',
    'en_curso'   => 'info',
    'completada' => 'success',
    'cancelada'  => 'secondary',
    'no_asistio' => 'danger',
];
$ESTADO_LABEL = [
    'pendiente'  => 'Pendiente',
    'confirmada' => 'Confirmada',
    'en_curso'   => 'En curso',
    'completada' => 'Completada',
    'cancelada'  => 'Cancelada',
    'no_asistio' => 'No asistió',
];
?>

<!-- ─── ENCABEZADO ──────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-globe text-primary me-2"></i>Portal de Reservas</h4>
        <small class="text-muted">Gestión del portal público de reservas de citas</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= $urlCfg ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-gear me-1"></i>Configurar portal
        </a>
        <a href="<?= $urlAgenda ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-calendar3 me-1"></i>Ver agenda
        </a>
    </div>
</div>

<?php if (!$tienePortal): ?>
<!-- Sin configurar -->
<div class="text-center py-5">
    <i class="bi bi-globe2 text-muted" style="font-size:4rem;opacity:.3;"></i>
    <h5 class="mt-3 text-muted">El portal aún no está configurado</h5>
    <p class="text-muted small">Ve a Configuración → pestaña Portal para activar y personalizar tu portal de reservas.</p>
    <a href="<?= $urlCfg ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-gear me-1"></i>Ir a configuración
    </a>
</div>

<?php else: ?>

<!-- ─── CARDS DE ESTADÍSTICAS ──────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-primary"><?= (int)($stats['total_portal'] ?? 0) ?></div>
                <div class="small text-muted">Total portal</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-warning"><?= (int)($stats['pendientes'] ?? 0) ?></div>
                <div class="small text-muted">Pendientes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-success"><?= (int)($stats['confirmadas'] ?? 0) ?></div>
                <div class="small text-muted">Confirmadas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-info"><?= (int)($stats['ultimos_30_dias'] ?? 0) ?></div>
                <div class="small text-muted">Últimos 30 días</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ─── COLUMNA IZQUIERDA: Link y embed ─────────────────────────── -->
    <div class="col-lg-5">

        <!-- Estado del portal -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold mb-0 small">Estado del portal</h6>
                    <?php if ($portalActivo): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>Activo
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                            <i class="bi bi-circle me-1" style="font-size:.5rem;"></i>Inactivo
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($portalActivo): ?>
                <!-- Link público -->
                <label class="form-label small text-muted mb-1">Link público</label>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" class="form-control form-control-sm font-monospace"
                           id="inputLinkPortal" value="<?= htmlspecialchars($urlPortal, ENT_QUOTES) ?>" readonly>
                    <button class="btn btn-outline-secondary" onclick="copiarLink('inputLinkPortal', this)" title="Copiar">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <a href="<?= htmlspecialchars($urlPortal, ENT_QUOTES) ?>" target="_blank"
                       class="btn btn-outline-primary" title="Abrir portal">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>

                <!-- Compartir -->
                <div class="d-flex gap-2 flex-wrap">
                    <a href="https://wa.me/?text=<?= urlencode('Reserva tu cita aquí: ' . $urlPortal) ?>"
                       target="_blank" class="btn btn-sm btn-success d-flex align-items-center gap-1">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </a>
                    <a href="mailto:?subject=<?= urlencode('Reserva tu cita') ?>&body=<?= urlencode('Puedes reservar tu cita en: ' . $urlPortal) ?>"
                       class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                        <i class="bi bi-envelope"></i> Email
                    </a>
                </div>

                <!-- QR -->
                <div class="text-center mt-3">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($urlPortal) ?>"
                         alt="QR Portal" class="rounded" style="width:140px;height:140px;">
                    <div class="text-muted small mt-1">Código QR</div>
                </div>
                <?php else: ?>
                <p class="text-muted small mb-2">El portal está inactivo. Actívalo en la configuración.</p>
                <a href="<?= $urlCfg ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-toggle-on me-1"></i>Activar portal
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($portalActivo): ?>
        <!-- Código embed (iframe) -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <h6 class="fw-bold small mb-2">Código para tu web (iframe)</h6>
                <?php $iframeCode = '<iframe src="' . $urlPortal . '" width="100%" height="700" style="border:none;border-radius:12px;" title="Reserva de citas"></iframe>'; ?>
                <textarea class="form-control form-control-sm font-monospace" id="iframeCode"
                          rows="3" readonly style="font-size:.72rem;resize:none;"><?= htmlspecialchars($iframeCode, ENT_QUOTES) ?></textarea>
                <div class="text-end mt-1">
                    <button class="btn btn-outline-secondary btn-sm" onclick="copiarLink('iframeCode', this)">
                        <i class="bi bi-clipboard me-1"></i>Copiar código
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ─── COLUMNA DERECHA: Últimas reservas ───────────────────────── -->
    <div class="col-lg-7">
        <?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig, 'estiloVistaPortal') ?>
        <style>
            .portal-reservas-scroll { max-height: 520px; overflow-y: auto; }
            .portal-reservas-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
            .portal-res-sort { cursor: pointer; user-select: none; }
            .portal-res-sort:hover { background: #e9ecef !important; }
        </style>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="fw-bold mb-0 small">Últimas reservas del portal</h6>
                <div class="d-flex align-items-center gap-2">
                    <!-- Búsqueda rápida -->
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted" style="font-size:.75rem;"></i></span>
                        <input type="text" class="form-control border-start-0 shadow-none form-control-sm"
                               placeholder="Filtrar..." oninput="filtrarPortalRes(this.value)"
                               style="font-size:.8rem;">
                    </div>
                    <!-- Columnas -->
                    <?php
                    $colsPortalRes = [
                        'pres_fecha'    => 'Fecha cita',
                        'pres_cliente'  => 'Cliente',
                        'pres_tipo'     => 'Tipo',
                        'pres_estado'   => 'Estado',
                        'pres_reg'      => 'Registrada',
                        'pres_id'       => 'ID',
                    ];
                    ?>
                    <div class="btn-group btn-group-sm">
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($colsPortalRes, $vistaConfig, $rutaModulo) ?>
                    </div>
                    <a href="<?= $urlAgenda ?>" class="text-decoration-none small ms-1">
                        Ver agenda <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($ultimas)): ?>
                <div class="text-center py-5 text-muted small">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                    Aún no hay reservas desde el portal.
                </div>
                <?php else: ?>
                <div class="portal-reservas-scroll">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small ps-3 portal-res-sort" data-col="pres_id"
                                    onclick="ordenarPortalRes(0)" style="width:55px;">
                                    # <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                                </th>
                                <th class="small portal-res-sort" data-col="pres_fecha"
                                    onclick="ordenarPortalRes(1)">
                                    Fecha cita <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                                </th>
                                <th class="small portal-res-sort" data-col="pres_cliente"
                                    onclick="ordenarPortalRes(2)">
                                    Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                                </th>
                                <th class="small portal-res-sort" data-col="pres_tipo"
                                    onclick="ordenarPortalRes(3)">
                                    Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                                </th>
                                <th class="small portal-res-sort" data-col="pres_estado"
                                    onclick="ordenarPortalRes(4)">
                                    Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                                </th>
                                <th class="small portal-res-sort" data-col="pres_reg"
                                    onclick="ordenarPortalRes(5)">
                                    Registrada <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tbodyPortalRes">
                        <?php foreach ($ultimas as $r):
                            $fechaIni  = $r['fecha_inicio'] ? date('d-m-Y H:i', strtotime($r['fecha_inicio'])) : '—';
                            $createdAt = $r['created_at']   ? date('d-m-Y H:i', strtotime($r['created_at']))   : '—';
                            $estadoCls = $ESTADO_COLOR[$r['estado'] ?? ''] ?? 'secondary';
                            $estadoLbl = $ESTADO_LABEL[$r['estado'] ?? ''] ?? $r['estado'];

                            if (!empty($r['nombre_cliente'])) {
                                $cliente = htmlspecialchars($r['nombre_cliente'], ENT_QUOTES);
                            } elseif (!empty($r['ext_nombres'])) {
                                $cliente = htmlspecialchars(trim($r['ext_nombres'] . ' ' . ($r['ext_apellidos'] ?? '')), ENT_QUOTES);
                            } else {
                                $cliente = '<span class="text-muted fst-italic">—</span>';
                            }
                        ?>
                        <tr>
                            <td class="small ps-3 text-muted" data-col="pres_id"><?= (int)$r['id'] ?></td>
                            <td class="small text-nowrap" data-col="pres_fecha"><?= $fechaIni ?></td>
                            <td class="small" data-col="pres_cliente" style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $cliente ?></td>
                            <td class="small" data-col="pres_tipo" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars($r['nombre_tipo'] ?? '—', ENT_QUOTES) ?>
                            </td>
                            <td data-col="pres_estado">
                                <span class="badge bg-<?= $estadoCls ?>-subtle text-<?= $estadoCls ?> border border-<?= $estadoCls ?>-subtle">
                                    <?= $estadoLbl ?>
                                </span>
                            </td>
                            <td class="small text-muted text-nowrap" data-col="pres_reg"><?= $createdAt ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /row -->
<?php endif; ?>

<script>
function copiarLink(inputId, btn) {
    const el = document.getElementById(inputId);
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(() => {
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-clipboard-check text-success';
            setTimeout(() => icon.className = 'bi bi-clipboard', 2000);
        }
    }).catch(() => {
        el.select();
        document.execCommand('copy');
    });
}

// ─── FILTRO LOCAL tabla portal ────────────────────────────────────────────────
function filtrarPortalRes(texto) {
    const tbody = document.getElementById('tbodyPortalRes');
    if (!tbody) return;
    const q = texto.toLowerCase().trim();
    tbody.querySelectorAll('tr').forEach(tr => {
        const coincide = Array.from(tr.cells).some(td => td.textContent.toLowerCase().includes(q));
        tr.style.display = coincide ? '' : 'none';
    });
}

// ─── ORDENAR tabla portal ─────────────────────────────────────────────────────
const _sortPortal = {};
function ordenarPortalRes(colIdx) {
    const tbody = document.getElementById('tbodyPortalRes');
    if (!tbody) return;
    const key = `portal_${colIdx}`;
    _sortPortal[key] = _sortPortal[key] === 'asc' ? 'desc' : 'asc';
    const asc = _sortPortal[key] === 'asc';
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
        const va = a.cells[colIdx]?.textContent.trim() ?? '';
        const vb = b.cells[colIdx]?.textContent.trim() ?? '';
        if (colIdx === 0) {
            return asc ? parseInt(va) - parseInt(vb) : parseInt(vb) - parseInt(va);
        }
        return asc ? va.localeCompare(vb, 'es') : vb.localeCompare(va, 'es');
    });
    rows.forEach(tr => tbody.appendChild(tr));
}
</script>
