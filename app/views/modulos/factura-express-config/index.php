<?php
/** @var array  $perm */
/** @var array  $rows */
/** @var int    $total */
/** @var int    $page */
/** @var int    $totalPages */
/** @var int    $perPage */
/** @var int    $from */
/** @var int    $to */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array  $vistaConfig */
/** @var string $rutaModulo */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/modulos/factura-express-config';

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'created_at';
$ordenDir   = $ordenDir   ?? 'DESC';
$buscar     = $buscar     ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .fexqr-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .fexqr-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .fexqr-row { cursor: pointer; }
    .fexqr-row:hover { background: rgba(0,0,0,.04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-qr-code text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="fexqrAbrirCrear()">
            <i class="bi bi-plus-lg"></i> Nueva Plantilla QR
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

        <!-- Buscador + exportación -->
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:300px">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="fexqrBuscar" class="form-control border-start-0 ps-0 shadow-none border"
                       placeholder="Buscar plantilla..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                <?php if ($buscar !== ''): ?>
                    <a href="<?= $urlBase ?>" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre'       => 'Nombre',
                    'descripcion'  => 'Descripción',
                    'total_items'  => 'Ítems',
                    'solicitudes'  => 'Solicitudes',
                    'activo'       => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="fexqrBtnPdf"
                   href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="fexqrBtnExcel"
                   href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="fexqrPaginInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="fexqrPaginContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="fexqrCambiarPagina(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="fexqrCambiarPagina(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="fexqr-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">
                            Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="descripcion" data-col="descripcion">
                            Descripción <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="text-center sortable-header" role="button" data-sort="total_items" data-col="total_items">
                            Ítems <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="text-center sortable-header" role="button" data-sort="solicitudes" data-col="solicitudes">
                            Solicitudes <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="text-center sortable-header" role="button" data-sort="activo" data-col="activo">
                            Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="text-center pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="fexqrTbody">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-qr-code fs-3 d-block mb-2 opacity-25"></i>
                            No hay plantillas QR. Crea la primera para comenzar.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $activo     = (bool)$r['activo'];
                            $cls        = $activo ? 'success' : 'secondary';
                            $lbl        = $activo ? 'Activo' : 'Inactivo';
                            $pendientes = (int)($r['solicitudes_pendientes'] ?? 0);
                            $urlPublica = rtrim($base, '/') . '/factura-express/' . htmlspecialchars($r['token']);
                        ?>
                            <tr class="fexqr-row" role="button" tabindex="0"
                                data-row="<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>"
                                onclick="fexqrAbrirEditar(this)">
                                <td class="ps-3 fw-medium" data-col="nombre"><?= htmlspecialchars($r['nombre']) ?></td>
                                <td data-col="descripcion"><small class="text-muted"><?= htmlspecialchars($r['descripcion'] ?? '-') ?></small></td>
                                <td class="text-center" data-col="total_items"><?= (int)($r['total_items'] ?? 0) ?></td>
                                <td class="text-center" data-col="solicitudes">
                                    <?php if ($pendientes > 0): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fw-bold">
                                            <?= $pendientes ?> pendiente<?= $pendientes > 1 ? 's' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= (int)($r['total_solicitudes'] ?? 0) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" data-col="activo">
                                    <span class="badge bg-<?= $cls ?> bg-opacity-10 text-<?= $cls ?> border border-<?= $cls ?> border-opacity-25"><?= $lbl ?></span>
                                </td>
                                <td class="text-center pe-3 text-nowrap">
                                    <button type="button" class="btn btn-outline-warning btn-sm py-1 px-2 me-1 position-relative"
                                            onclick="event.stopPropagation();fexqrMostrarQrAdmin('<?= rtrim($base, '/') ?>/modulos/factura-express-solicitudes?empresa=<?= (int)$_SESSION['id_empresa'] ?>', '<?= htmlspecialchars($r['nombre'], ENT_QUOTES) ?>')"
                                            title="QR Solicitudes">
                                        <i class="bi bi-qr-code-scan me-1"></i> QR Solicitudes
                                        <?php if ($pendientes > 0): ?>
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem">
                                                <?= $pendientes ?><span class="visually-hidden">pendientes</span>
                                            </span>
                                        <?php endif; ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2"
                                            onclick="event.stopPropagation();fexqrMostrarQr('<?= $r['token'] ?>','<?= htmlspecialchars($r['nombre'], ENT_QUOTES) ?>','<?= htmlspecialchars($r['descripcion'] ?? '', ENT_QUOTES) ?>')"
                                            title="QR Clientes">
                                        <i class="bi bi-qr-code me-1"></i> QR Clientes
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_plantilla.php'; ?>
<?php include __DIR__ . '/modal_qr.php'; ?>

<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlBase ?>';
    const baseUrl = '<?= rtrim($base, '/') ?>';
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir  = '<?= $ordenDir ?>';
    let timer;

    window.fexqrCambiarPagina = p => fexqrBuscar(p);

    window.fexqrBuscar = async function(page = 1) {
        const b   = document.getElementById('fexqrBuscar').value.trim();
        const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        try {
            const r = await fetch(uri, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (d.ok) {
                document.getElementById('fexqrTbody').innerHTML          = d.rows;
                document.getElementById('fexqrPaginContainer').innerHTML = d.pagination;
                document.getElementById('fexqrPaginInfo').textContent    = d.info;
                if (d.pdf_url)   document.getElementById('fexqrBtnPdf').href   = d.pdf_url;
                if (d.excel_url) document.getElementById('fexqrBtnExcel').href = d.excel_url;

                document.querySelectorAll('.sortable-header').forEach(th => {
                    const icon  = th.querySelector('i');
                    const field = th.dataset.sort;
                    if (field === window.currentSort) {
                        icon.className = window.currentDir.toLowerCase() === 'asc'
                            ? 'bi bi-sort-alpha-down text-primary ms-1'
                            : 'bi bi-sort-alpha-up text-primary ms-1';
                    } else {
                        icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    }
                });
            }
        } catch(e) { console.error(e); }
    };

    document.getElementById('fexqrBuscar').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => fexqrBuscar(1), 400);
    });

    document.querySelectorAll('.sortable-header').forEach(th => {
        th.addEventListener('click', () => {
            const f = th.dataset.sort;
            if (window.currentSort === f) {
                window.currentDir = window.currentDir.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
            } else {
                window.currentSort = f;
                window.currentDir  = 'ASC';
            }
            fexqrBuscar(1);
        });
    });

    window.fexqrAbrirCrear = () => {
        window._fexqrEditando = null;
        document.getElementById('fexqrModalTitulo').textContent = 'Nueva Plantilla QR';
        document.getElementById('formFexqr').reset();
        document.getElementById('fexqrTbodyItems').innerHTML = '';
        document.getElementById('btnEliminarFexqr')?.classList.add('d-none');
        document.getElementById('fexqrChkActivo').checked     = true;
        document.getElementById('fexqrChkAprobacion').checked = true;
        // Resetear serie
        const selEst = document.getElementById('fexqr_id_establecimiento');
        if (selEst) { selEst.value = ''; selEst.dispatchEvent(new Event('change')); }
        const inpSerie = document.getElementById('fexqrSeriePreview');
        if (inpSerie) inpSerie.value = '';
        new bootstrap.Modal(document.getElementById('modalFexqr')).show();
    };

    window.fexqrAbrirEditar = async function(el) {
        const r = JSON.parse(el.dataset.row);
        window._fexqrEditando = r;
        document.getElementById('fexqrModalTitulo').textContent = 'Editar: ' + r.nombre;

        try {
            const res = await fetch(`${urlBase}/getPlantillaAjax?id=${r.id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d   = await res.json();
            if (!d.ok) throw new Error(d.mensaje);
            const p = d.data;
            document.getElementById('fexqr_id').value             = p.id;
            document.getElementById('fexqr_nombre').value         = p.nombre ?? '';
            document.getElementById('fexqr_descripcion').value    = p.descripcion ?? '';
            document.getElementById('fexqrChkActivo').checked     = !!p.activo;
            document.getElementById('fexqrChkAprobacion').checked = !!p.requiere_aprobacion;
            document.getElementById('fexqr_bienvenida').value     = p.mensaje_bienvenida ?? '';
            document.getElementById('fexqr_gracias').value        = p.mensaje_gracias ?? '';
            document.getElementById('fexqr_max_hora').value       = p.max_solicitudes_hora ?? 10;

            const cfg = JSON.parse(p.campos_config ?? '{}');
            document.getElementById('fexqr_campo_nombre').checked         = cfg.nombre !== false;
            document.getElementById('fexqr_campo_identificacion').checked = cfg.identificacion !== false;
            document.getElementById('fexqr_campo_correo').checked         = cfg.correo !== false;
            document.getElementById('fexqr_campo_telefono').checked       = !!cfg.telefono;
            document.getElementById('fexqr_campo_direccion').checked      = !!cfg.direccion;

            // Serie: cargar establecimiento y punto de emisión
            const selEst = document.getElementById('fexqr_id_establecimiento');
            if (selEst && p.id_establecimiento) {
                selEst.value = p.id_establecimiento;
                selEst.dispatchEvent(new Event('change')); // filtra puntos de emisión
                // Esperar un tick para que se filtren antes de asignar el punto
                setTimeout(() => {
                    const selPe = document.getElementById('fexqr_id_punto_emision');
                    if (selPe && p.id_punto_emision) selPe.value = p.id_punto_emision;
                    selPe?.dispatchEvent(new Event('change'));
                }, 0);
            }

            // Forma de pago
            const selFp = document.getElementById('fexqr_forma_pago');
            if (selFp && p.forma_pago) selFp.value = p.forma_pago;

            document.getElementById('fexqrTbodyItems').innerHTML = '';
            (p.items ?? []).forEach(item => fexqrAgregarItemFila(item));

            document.getElementById('btnEliminarFexqr')?.classList.remove('d-none');
        } catch(e) {
            Swal.fire({ icon:'error', title:'Error', text: 'Error al cargar la plantilla: ' + e.message });
            return;
        }

        new bootstrap.Modal(document.getElementById('modalFexqr')).show();
    };

    window.fexqrMostrarQr = function(token, nombre, descripcion) {
        document.getElementById('fexqrQrNombre').textContent      = nombre;
        document.getElementById('fexqrQrDescripcion').textContent = descripcion || '';
        document.getElementById('fexqrQrImg').src = '';
        document.getElementById('fexqrQrUrl').textContent = '';
        document.getElementById('fexqrQrSpinner').classList.remove('d-none');
        document.getElementById('fexqrQrUrlLabel').textContent = 'URL del formulario público:';
        new bootstrap.Modal(document.getElementById('modalFexqrQr')).show();

        let url = `${baseUrl}/factura-express/${token}`;
        if (!url.startsWith('http')) {
            url = window.location.origin + (url.startsWith('/') ? '' : '/') + url;
        }

        const qr  = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(url)}&size=280x280&margin=10`;

        document.getElementById('fexqrQrImg').onload = () => {
            document.getElementById('fexqrQrSpinner').classList.add('d-none');
            // Sincronizar hoja de impresión
            document.getElementById('fexqrPrintNombre').textContent      = nombre;
            document.getElementById('fexqrPrintDescripcion').textContent = descripcion || '';
            document.getElementById('fexqrPrintImg').src                 = qr;
        };
        document.getElementById('fexqrQrImg').src     = qr;
        document.getElementById('fexqrQrUrl').textContent = url;
        document.getElementById('fexqrQrUrlLink').href    = url;
    };

    window.fexqrMostrarQrAdmin = function(url, nombre) {
        document.getElementById('fexqrQrNombre').textContent      = 'Panel de Solicitudes';
        document.getElementById('fexqrQrDescripcion').textContent = 'Acceso a autorizaciones (Plantilla: ' + nombre + ')';
        document.getElementById('fexqrQrImg').src = '';
        document.getElementById('fexqrQrUrl').textContent = '';
        document.getElementById('fexqrQrSpinner').classList.remove('d-none');
        document.getElementById('fexqrQrUrlLabel').textContent = 'URL del panel de solicitudes (requiere login):';
        new bootstrap.Modal(document.getElementById('modalFexqrQr')).show();

        let finalUrl = url;
        if (!finalUrl.startsWith('http')) {
            finalUrl = window.location.origin + (finalUrl.startsWith('/') ? '' : '/') + finalUrl;
        }

        const qr  = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(finalUrl)}&size=280x280&margin=10`;

        document.getElementById('fexqrQrImg').onload = () => {
            document.getElementById('fexqrQrSpinner').classList.add('d-none');
            // Sincronizar hoja de impresión
            document.getElementById('fexqrPrintNombre').textContent      = 'Panel de Solicitudes';
            document.getElementById('fexqrPrintDescripcion').textContent = 'Acceso a autorizaciones (Plantilla: ' + nombre + ')';
            document.getElementById('fexqrPrintImg').src                 = qr;
        };
        document.getElementById('fexqrQrImg').src         = qr;
        document.getElementById('fexqrQrUrl').textContent = finalUrl;
        document.getElementById('fexqrQrUrlLink').href    = finalUrl;
    };

    window.fexqrCopiarUrl = function() {
        const url = document.getElementById('fexqrQrUrl').textContent;
        navigator.clipboard.writeText(url).then(() => {
            const btn = document.getElementById('btnCopiarUrl');
            btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copiado';
            setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copiar URL', 2000);
        });
    };
})();
</script>
