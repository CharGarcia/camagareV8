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

$base = BASE_URL;
$urlBaseAlu = $base . '/modulos/alumnos';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .alumnos-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .alumnos-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .alumno-row { cursor: pointer; }
    .alumno-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Alumnos</h5>
    <div class="d-flex gap-2">
        <?php if (!empty($perm['actualizar'])): ?>
            <button type="button" class="btn btn-outline-primary btn-sm px-3 shadow-sm" onclick="aluAbrirPortal()" title="QR y enlace para que los representantes actualicen sus datos">
                <i class="bi bi-qr-code me-1"></i> Portal de representantes
            </button>
        <?php endif; ?>
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalAlumnoCrear()">
                <i class="bi bi-plus-lg me-1"></i> Nuevo
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <?php
            // Buscador estándar (FiltrosModal, igual que Ingresos): texto libre + botón Filtros con
            // Campus y Nivel/Curso de la matrícula. Viaja como `campus:ID nivel:ID` en ?b=, así que
            // PDF y Excel respetan los mismos filtros.
            $optCampus = array_map(fn($c) => ['v' => (string) $c['id'], 'l' => $c['nombre']], $opcionesCampus ?? []);
            $optNivel  = array_map(fn($n) => ['v' => (string) $n['id'], 'l' => $n['nombre']], $opcionesNivel ?? []);
            $filtrosAlumnos = [
                ['key' => 'campus', 'label' => 'Campus',      'icon' => 'bi-geo-alt',  'type' => 'select', 'grupo' => 'Matrícula', 'col' => 6, 'options' => $optCampus],
                ['key' => 'nivel',  'label' => 'Nivel / Curso', 'icon' => 'bi-mortarboard', 'type' => 'select', 'grupo' => 'Matrícula', 'col' => 6, 'options' => $optNivel],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorALU"></div>
            <input type="hidden" id="buscarAlumno" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorALU',
                        hiddenInputId: 'buscarAlumno',
                        placeholder: 'Buscar alumno, cédula, representante, campus o nivel...',
                        titulo: 'Filtros de alumnos',
                        inputWidth: 380,
                        extraId: 'fmExtraALU',
                        fields: <?= json_encode($filtrosAlumnos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyAlumnos',
                        onApply: () => window.fetchSearchAlumnos && window.fetchSearchAlumnos(1),
                    }).init();
                });
            </script>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraALU" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombres'          => 'Alumno',
                    'campus'           => 'Campus',
                    'nivel'            => 'Nivel/Curso',
                    'representante'    => 'Representante',
                    'matricula'        => 'Matrícula',
                    'estado_academico' => 'Estado',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                ?>
                <a id="btnExportPdf" href="<?= $urlBaseAlu ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBaseAlu ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="alumnos-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="nombres" data-col="nombres" role="button">Alumno <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="campus" class="sortable-header" data-sort="campus" role="button">Campus <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="nivel" class="sortable-header" data-sort="nivel" role="button">Nivel/Curso <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="representante" class="sortable-header" data-sort="representante" role="button">Representante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="matricula">Matrícula</th>
                        <th class="text-center sortable-header" data-sort="estado_academico" data-col="estado_academico" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyAlumnos">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No se encontraron alumnos.</td></tr>
                    <?php else: ?>
                        <?php
                        $estadoBadges = ['activo' => 'success', 'retirado' => 'secondary', 'egresado' => 'info', 'suspendido' => 'danger'];
                        foreach ($rows as $row):
                            $estado = $row['estado_academico'] ?? 'activo';
                            $color = $estadoBadges[$estado] ?? 'secondary';
                            $vigente = !empty($row['matricula_vigente']) && in_array($row['matricula_vigente'], [true, 't', 1, '1'], true);
                        ?>
                            <tr class="alumno-row" onclick="abrirModalAlumnoEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-bold"><?= htmlspecialchars(trim(($row['apellidos'] ?? '') . ' ' . ($row['nombres'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars((string)($row['campus_actual_nombre'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string)($row['nivel_actual_nombre'] ?? '—')) ?></td>
                                <td class="small"><?= htmlspecialchars((string)($row['representante_nombre'] ?? '—')) ?></td>
                                <td class="text-center">
                                    <?php if ($vigente): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10">Vigente</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10">Sin matrícula</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?> border-opacity-10"><?= ucfirst($estado) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_alumno.php'; ?>

<?php if (!empty($perm['actualizar'])): ?>
<!-- Modal: Portal de representantes (QR general del colegio) -->
<div class="modal fade" id="modalPortalAlu" tabindex="-1" aria-hidden="true" data-cmg-nav="off">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold mb-0"><i class="bi bi-qr-code me-2 text-primary"></i>Portal de representantes</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Los representantes escanean este QR, se identifican con su cédula o RUC y, con un código que les llega al correo, actualizan una vez sus datos de facturación y los de sus hijos, o registran un alumno nuevo. Los cambios se aplican al instante.</p>
                <div class="text-center mb-3">
                    <div id="portalAluSpinner" class="spinner-border text-primary my-5" role="status"></div>
                    <img id="portalAluQr" src="" alt="QR del portal" class="img-fluid border rounded-3 d-none" style="max-width:240px;">
                </div>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" id="portalAluUrl" class="form-control" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="aluPortalCopiar()" title="Copiar enlace"><i class="bi bi-clipboard"></i></button>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="portalAluActivo" onchange="aluPortalActivar(this.checked)">
                    <label class="form-check-label small" for="portalAluActivo">Portal activo (si se desactiva, el QR deja de funcionar)</label>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="aluPortalImprimir()"><i class="bi bi-printer me-1"></i>Imprimir hoja con QR</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="aluPortalEnviar()" title="Envía el enlace por correo a los representantes de los alumnos del listado (con el filtro actual)"><i class="bi bi-envelope me-1"></i>Enviar por correo</button>
                    <button type="button" class="btn btn-outline-danger btn-sm ms-auto" onclick="aluPortalRegenerar()" title="Crea un QR nuevo; el anterior deja de funcionar"><i class="bi bi-arrow-repeat me-1"></i>Regenerar QR</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseAlu ?>';
        const el = () => document.getElementById('modalPortalAlu');
        const swalOpts = (o) => Object.assign({ target: el(), heightAuto: false, confirmButtonText: 'Aceptar' }, o);
        const aviso = (icon, text) => (typeof Swal !== 'undefined') ? Swal.fire(swalOpts({ icon, title: icon === 'success' ? 'Listo' : (icon === 'error' ? 'Error' : 'Atención'), text })) : alert(text);

        function pintar(d) {
            const img = document.getElementById('portalAluQr');
            const sp = document.getElementById('portalAluSpinner');
            document.getElementById('portalAluUrl').value = d.url;
            document.getElementById('portalAluActivo').checked = !!d.activo;
            img.classList.add('d-none'); sp.classList.remove('d-none');
            img.onload = () => { sp.classList.add('d-none'); img.classList.remove('d-none'); };
            // El QR lleva la URL ABSOLUTA (url_absoluta en el servidor): se abre desde la cámara del celular.
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?data=' + encodeURIComponent(d.url) + '&size=300x300&margin=10';
            img.style.opacity = d.activo ? '1' : '.35';
        }

        async function llamar(accion, body) {
            const opt = body ? { method: 'POST', body } : {};
            const r = await fetch(urlBase + '/' + accion, opt);
            return r.json();
        }

        window.aluAbrirPortal = async function () {
            bootstrap.Modal.getOrCreateInstance(el()).show();
            try {
                const j = await llamar('portalAjax');
                j.ok ? pintar(j) : aviso('error', j.error || 'No se pudo abrir el portal.');
            } catch (e) { aviso('error', 'Error de conexión.'); }
        };
        window.aluPortalCopiar = function () {
            const v = document.getElementById('portalAluUrl').value;
            if (navigator.clipboard) navigator.clipboard.writeText(v).then(() => aviso('success', 'Enlace copiado.'));
        };
        window.aluPortalImprimir = function () { window.open(urlBase + '/portalImprimir', '_blank'); };
        window.aluPortalActivar = async function (activo) {
            const fd = new FormData(); fd.append('activo', activo ? '1' : '');
            try {
                const j = await llamar('portalActivarAjax', fd);
                j.ok ? pintar(j) : aviso('error', j.error || 'No se pudo cambiar.');
            } catch (e) { aviso('error', 'Error de conexión.'); }
        };
        window.aluPortalRegenerar = async function () {
            const r = await Swal.fire(swalOpts({ icon: 'warning', title: '¿Regenerar el QR?', text: 'El QR y el enlace actuales dejarán de funcionar; habrá que imprimir y enviar el nuevo.', showCancelButton: true, confirmButtonText: 'Sí, regenerar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545', reverseButtons: true }));
            if (!r.isConfirmed) return;
            try {
                const j = await llamar('portalRegenerarAjax', new FormData());
                j.ok ? (pintar(j), aviso('success', 'QR regenerado.')) : aviso('error', j.error || 'No se pudo regenerar.');
            } catch (e) { aviso('error', 'Error de conexión.'); }
        };
        window.aluPortalEnviar = async function () {
            const b = document.getElementById('buscarAlumno')?.value || '';
            try {
                const c = await llamar('portalContarAjax?b=' + encodeURIComponent(b));
                if (!c.ok) { aviso('error', c.error || 'Error'); return; }
                if (!c.representantes) { aviso('info', 'No hay alumnos con representante en el listado actual.'); return; }
                const r = await Swal.fire(swalOpts({ icon: 'question', title: 'Enviar el enlace por correo',
                    html: `Se enviará a <b>${c.representantes}</b> representante(s) de <b>${c.alumnos}</b> alumno(s) del listado actual${b ? ' (con el filtro aplicado)' : ''}.`,
                    showCancelButton: true, confirmButtonText: 'Enviar', cancelButtonText: 'Cancelar', reverseButtons: true }));
                if (!r.isConfirmed) return;
                Swal.fire(swalOpts({ title: 'Enviando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() }));
                const fd = new FormData(); fd.append('b', b);
                const j = await llamar('portalEnviarAjax', fd);
                if (!j.ok) { aviso('error', j.error || 'No se pudo enviar.'); return; }
                let txt = `Enviados: ${j.enviados}.`;
                if (j.sin_correo) txt += ` Sin correo registrado: ${j.sin_correo}` + (j.sin_correo_muestra && j.sin_correo_muestra.length ? ` (${j.sin_correo_muestra.join(', ')}${j.sin_correo > j.sin_correo_muestra.length ? '…' : ''})` : '') + '.';
                if (j.fallidos) txt += ` Fallidos: ${j.fallidos} (revise la configuración de correo).`;
                aviso(j.fallidos || j.sin_correo ? 'warning' : 'success', txt);
            } catch (e) { aviso('error', 'Error de conexión.'); }
        };
    })();
</script>
<?php endif; ?>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseAlu ?>';
        const inputB = document.getElementById('buscarAlumno');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';
        let timer;

        window.cambiarPaginaAjax = (p) => fetchSearch(p);

        async function fetchSearch(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    document.getElementById('tbodyAlumnos').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        if (th.dataset.sort === currentSort) {
                            icon.className = (currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                        } else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    });
                }
            } catch (e) {}
        }
        window.fetchSearchAlumnos = (p) => fetchSearch(p);

        if (typeof window.CMG_initSort === 'function') {
            window.CMG_initSort('alumnos', (col, dir) => {
                currentSort = col;
                currentDir = dir;
                fetchSearch(1);
            }, { col: currentSort, dir: currentDir });
        } else {
            document.querySelectorAll('.sortable-header').forEach(th => {
                th.addEventListener('click', () => {
                    const col = th.dataset.sort;
                    currentDir = (currentSort === col && currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                    currentSort = col;
                    fetchSearch(1);
                });
            });
        }

    })();
</script>
