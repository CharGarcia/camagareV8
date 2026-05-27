<?php
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
/** @var array $provincias */
/** @var array $tiposFirma */

$base    = BASE_URL;
$urlBase = $base . '/modulos/firmas_electronicas';
$from    = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to      = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .firmas-scroll { max-height: calc(100vh - 250px); overflow-y: auto; }
    .firmas-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .firma-row { cursor: pointer; }
    .firma-row:hover { background-color: rgba(0,0,0,.04); }

    /* Panel solicitudes */
    #panelSolicitudes { display: none; }
    .sol-badge-pendiente  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .sol-badge-completado { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .sol-badge-expirado   { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
    .sol-badge-cancelado  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-pen-fill me-2 text-primary"></i>Firmas Electrónicas</h5>
    <div class="d-flex gap-2">
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" id="btnToggleSolicitudes" onclick="togglePanelSolicitudes()">
                <i class="bi bi-envelope-arrow-up me-1"></i> Formularios enviados
            </button>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalFirmaCrear()">
                <i class="bi bi-plus-lg me-1"></i> Nueva
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- ── Panel Solicitudes ───────────────────────────────────── -->
<div id="panelSolicitudes" class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold small"><i class="bi bi-envelope-arrow-up me-2 text-secondary"></i>Formularios Enviados por Email</span>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalEnviarFormulario()">
                <i class="bi bi-send me-1"></i> Enviar formulario
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="togglePanelSolicitudes()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div style="max-height:260px;overflow-y:auto;">
            <table class="table table-sm table-hover mb-0 align-middle small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Correo</th>
                        <th>Nombre</th>
                        <th class="text-center">Estado</th>
                        <th>Enviado</th>
                        <th>Expira</th>
                        <th>Firma generada</th>
                        <th class="text-end pe-3">Acción</th>
                    </tr>
                </thead>
                <tbody id="tbodySolicitudes">
                    <tr><td colspan="7" class="text-center py-3 text-muted">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
        <div id="solPaginacion" class="px-3 py-2 border-top d-flex justify-content-between align-items-center small text-muted" style="display:none!important;"></div>
    </div>
</div>

<!-- ── Tabla principal ─────────────────────────────────────── -->
<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:280px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="buscarFirma" class="form-control border-start-0 ps-0 shadow-none"
                    placeholder="Buscar por nombre, identificación…"
                    value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
            </div>
            <div class="btn-group btn-group-sm">
                <a id="btnFirmaPdf"   href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnFirmaExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="firmasPaginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="firmasPaginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaFirmas(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaFirmas(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="firmas-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header-f" data-sort="nombres" role="button">Nombres <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header-f" data-sort="numero_identificacion" role="button">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header-f" data-sort="nombre_producto" role="button">Tipo Firma <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header-f" data-sort="telefono" role="button">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header-f d-none d-lg-table-cell" data-sort="correo" role="button">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header-f" data-sort="estado_pago" role="button">Pago <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-sort="factura_estado">Factura</th>
                        <th class="text-center sortable-header-f" data-sort="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header-f" data-sort="fecha_caducidad" role="button">Caducidad <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end pe-3 sortable-header-f" data-sort="created_at" role="button">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyFirmas">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-pen fs-3 d-block mb-2"></i>No se encontraron firmas electrónicas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $badgeEstado = match($row['estado'] ?? 'pendiente') {
                                'en_proceso' => '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">En Proceso</span>',
                                'emitida'    => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Emitida</span>',
                                'cancelada'  => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Cancelada</span>',
                                default      => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Pendiente</span>',
                            };
                            $badgePago = match($row['estado_pago'] ?? 'pendiente') {
                                'confirmado' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Confirmado</span>',
                                'rechazado'  => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Rechazado</span>',
                                default      => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Pendiente</span>',
                            };
                            $factElim  = ($row['factura_eliminada'] ?? false) === true;
                            $factId    = $row['id_factura'] ? (int)$row['id_factura'] : null;
                            $factEst   = $row['factura_estado'] ?? null;
                            $badgeFactura = !$factId || $factElim
                                ? '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.7rem;">Por Facturar</span>'
                                : match($factEst) {
                                    'borrador'   => '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:.7rem;">Borrador</span>',
                                    'autorizada' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.7rem;"><i class="bi bi-check-circle me-1"></i>Facturado</span>',
                                    'anulada'    => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.7rem;">Anulada</span>',
                                    default      => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.7rem;">' . htmlspecialchars(ucfirst($factEst ?? '?')) . '</span>',
                                };
                            $fCad  = $row['fecha_caducidad'] ?? null;
                            $diff  = $fCad ? (int)(( strtotime($fCad) - time()) / 86400) : null;
                            $badgeCaducidad = $fCad === null ? '<span class="text-muted small">-</span>'
                                : ($diff < 0
                                    ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><i class="bi bi-exclamation-circle me-1"></i>' . date('d-m-Y', strtotime($fCad)) . '</span>'
                                    : ($diff <= 30
                                        ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><i class="bi bi-clock me-1"></i>' . date('d-m-Y', strtotime($fCad)) . '</span>'
                                        : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">' . date('d-m-Y', strtotime($fCad)) . '</span>'
                                    ));
                        ?>
                            <tr class="firma-row" onclick="abrirModalFirmaEditar(this)"
                                data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="nombres">
                                    <?= htmlspecialchars(($row['nombres'] ?? '') . ' ' . ($row['apellidos'] ?? '')) ?>
                                </td>
                                <td data-col="numero_identificacion">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 me-1" style="font-size:.65rem"><?= strtoupper($row['tipo_identificacion'] ?? '') ?></span>
                                    <?= htmlspecialchars($row['numero_identificacion'] ?? '') ?>
                                </td>
                                <td data-col="nombre_producto" class="small text-muted"><?= htmlspecialchars($row['nombre_producto'] ?? '-') ?></td>
                                <td data-col="telefono"><?= htmlspecialchars($row['telefono'] ?? '-') ?></td>
                                <td data-col="correo" class="d-none d-lg-table-cell small"><?= htmlspecialchars($row['correo'] ?? '-') ?></td>
                                <td class="text-center" data-col="estado_pago"><?= $badgePago ?></td>
                                <td class="text-center" data-col="factura_estado"><?= $badgeFactura ?></td>
                                <td class="text-center" data-col="estado"><?= $badgeEstado ?></td>
                                <td class="text-center" data-col="fecha_caducidad"><?= $badgeCaducidad ?></td>
                                <td class="text-end pe-3 small text-muted" data-col="created_at"><?= $row['created_at'] ?? '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Modal: Enviar Formulario ───────────────────────────── -->
<div class="modal fade" id="modalEnviarFormulario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content rounded-3">
            <div class="modal-header py-2 px-3">
                <h6 class="modal-title fw-semibold"><i class="bi bi-envelope-arrow-up me-2 text-primary"></i>Enviar Formulario al Cliente</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-3 py-3">
                <div id="alertEnviarFormulario" class="alert d-none mb-3 py-2 small"></div>
                <div class="mb-2">
                    <label class="form-label form-label-sm fw-medium">Nombre del cliente <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" class="form-control form-control-sm" id="solNombreDestino" placeholder="Ej: Juan Pérez">
                </div>
                <div class="mb-0">
                    <label class="form-label form-label-sm fw-medium">Correo electrónico <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" id="solCorreoDestino" placeholder="cliente@ejemplo.com">
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnConfirmarEnviarFormulario" onclick="confirmarEnviarFormulario()">
                    <i class="bi bi-send me-1"></i> Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_firma.php'; ?>

<script>
(function () {
    'use strict';
    const urlBase  = '<?= $urlBase ?>';
    const inputB   = document.getElementById('buscarFirma');
    let currentSort = '<?= $ordenCol ?>';
    let currentDir  = '<?= $ordenDir ?>';
    let timer;

    window.cambiarPaginaFirmas = (p) => fetchSearchFirmas(p);

    async function fetchSearchFirmas(page = 1) {
        const b   = inputB ? inputB.value.trim() : '';
        const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
        try {
            const resp = await fetch(uri);
            const data = await resp.json();
            if (data.ok) {
                document.getElementById('tbodyFirmas').innerHTML               = data.rows;
                document.getElementById('firmasPaginationContainer').innerHTML  = data.pagination;
                document.getElementById('firmasPaginationInfo').textContent     = data.info;
                document.getElementById('btnFirmaPdf').href                    = data.pdf_url;
                document.getElementById('btnFirmaExcel').href                  = data.excel_url;

                document.querySelectorAll('.sortable-header-f').forEach(th => {
                    const icon = th.querySelector('i');
                    if (!icon) return;
                    if (th.dataset.sort === currentSort) {
                        icon.className = currentDir.toLowerCase() === 'asc'
                            ? 'bi bi-sort-alpha-down text-primary ms-1'
                            : 'bi bi-sort-alpha-up text-primary ms-1';
                    } else {
                        icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    }
                });
            }
        } catch (e) {}
    }
    window.fetchSearchFirmas = fetchSearchFirmas;

    document.querySelectorAll('.sortable-header-f').forEach(h => {
        h.addEventListener('click', () => {
            const f = h.dataset.sort;
            if (currentSort === f) currentDir = currentDir.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
            else { currentSort = f; currentDir = 'ASC'; }
            fetchSearchFirmas(1);
        });
    });

    if (inputB) inputB.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => fetchSearchFirmas(1), 400);
    });

    // ── Panel solicitudes ──────────────────────────────────────
    let panelVisible = false;

    window.togglePanelSolicitudes = function () {
        panelVisible = !panelVisible;
        const panel = document.getElementById('panelSolicitudes');
        panel.style.display = panelVisible ? 'block' : 'none';
        if (panelVisible) cargarSolicitudes(1);
    };

    window.abrirModalEnviarFormulario = function () {
        document.getElementById('solNombreDestino').value = '';
        document.getElementById('solCorreoDestino').value = '';
        const alerta = document.getElementById('alertEnviarFormulario');
        alerta.className = 'alert d-none mb-3 py-2 small';
        alerta.textContent = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEnviarFormulario')).show();
    };

    window.confirmarEnviarFormulario = async function () {
        const correo = document.getElementById('solCorreoDestino').value.trim();
        const nombre = document.getElementById('solNombreDestino').value.trim();
        const alerta = document.getElementById('alertEnviarFormulario');
        const btn    = document.getElementById('btnConfirmarEnviarFormulario');

        if (!correo) {
            alerta.className = 'alert alert-warning mb-3 py-2 small';
            alerta.textContent = 'Ingrese el correo electrónico.';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';

        try {
            const fd = new FormData();
            fd.append('correo_destino', correo);
            fd.append('nombre_destino', nombre);
            const resp = await fetch(`${urlBase}/enviarSolicitud`, { method: 'POST', body: fd });
            const data = await resp.json();

            alerta.className = `alert alert-${data.ok ? 'success' : 'danger'} mb-3 py-2 small`;
            alerta.textContent = data.msg;

            if (data.ok) {
                setTimeout(() => {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEnviarFormulario')).hide();
                    if (panelVisible) cargarSolicitudes(1);
                }, 1500);
            }
        } catch {
            alerta.className = 'alert alert-danger mb-3 py-2 small';
            alerta.textContent = 'Error de conexión.';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
        }
    };

    async function cargarSolicitudes(page = 1) {
        const tbody = document.getElementById('tbodySolicitudes');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Cargando…</td></tr>';
        try {
            const resp = await fetch(`${urlBase}/getSolicitudes?page=${page}`);
            const data = await resp.json();
            if (!data.ok || !data.rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">No hay formularios enviados.</td></tr>';
                return;
            }
            const badges = {
                pendiente:  '<span class="badge sol-badge-pendiente">Pendiente</span>',
                completado: '<span class="badge sol-badge-completado">Completado</span>',
                expirado:   '<span class="badge sol-badge-expirado">Expirado</span>',
                cancelado:  '<span class="badge sol-badge-cancelado">Cancelado</span>',
            };
            tbody.innerHTML = data.rows.map(r => `
                <tr>
                    <td class="ps-3">${r.correo_destino}</td>
                    <td>${r.nombre_destino || '<span class="text-muted">-</span>'}</td>
                    <td class="text-center">${badges[r.estado] || r.estado}</td>
                    <td class="text-muted">${r.created_at}</td>
                    <td class="text-muted">${r.expira_at}</td>
                    <td>${r.firma_nombre ? `<a href="#" onclick="event.preventDefault();abrirFirmaPorNombre('${r.id_firma_generada}')" class="small text-primary">${r.firma_nombre}</a>` : '<span class="text-muted small">-</span>'}</td>
                    <td class="text-end pe-3">
                        ${r.estado === 'pendiente'
                            ? `<button class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:.75rem;" onclick="cancelarSolicitud(${r.id})"><i class="bi bi-x-circle"></i></button>`
                            : ''}
                    </td>
                </tr>`).join('');
        } catch {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Error al cargar.</td></tr>';
        }
    }

    window.cancelarSolicitud = async function (id) {
        if (!confirm('¿Cancelar esta solicitud?')) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const resp = await fetch(`${urlBase}/cancelarSolicitud`, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) cargarSolicitudes(1);
            else alert(data.msg);
        } catch { alert('Error de conexión.'); }
    };

    window.abrirFirmaPorNombre = function(idFirma) {
        // Buscar la fila en la tabla principal y abrirla si existe, si no buscar via AJAX
        const rows = document.querySelectorAll('#tbodyFirmas tr[data-row]');
        for (const row of rows) {
            try {
                const d = JSON.parse(row.dataset.row);
                if (String(d.id) === String(idFirma)) {
                    row.click();
                    return;
                }
            } catch {}
        }
        // Si no está en la página actual, buscar
        document.getElementById('buscarFirma').value = '';
        fetchSearchFirmas(1).then(() => {
            const r2 = document.querySelectorAll('#tbodyFirmas tr[data-row]');
            for (const row of r2) {
                try {
                    const d = JSON.parse(row.dataset.row);
                    if (String(d.id) === String(idFirma)) { row.click(); return; }
                } catch {}
            }
        });
    };
})();
</script>
