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
            $optServicio = array_map(fn($p) => ['v' => (string) $p['id'], 'l' => trim(($p['codigo'] ? $p['codigo'] . ' - ' : '') . $p['nombre'])], $opcionesServicio ?? []);
            // Meses para «Facturado en el mes»: del mes que viene hacia atrás, un año.
            $optMes = [];
            $nombresMes = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            for ($m = 1; $m >= -12; $m--) {
                $t = strtotime(date('Y-m-01') . " {$m} month");
                $optMes[] = ['v' => date('Y-m', $t), 'l' => $nombresMes[(int) date('n', $t)] . ' ' . date('Y', $t)];
            }
            $filtrosAlumnos = [
                ['key' => 'campus', 'label' => 'Campus',      'icon' => 'bi-geo-alt',  'type' => 'select', 'grupo' => 'Matrícula', 'col' => 6, 'options' => $optCampus],
                ['key' => 'nivel',  'label' => 'Nivel / Curso', 'icon' => 'bi-mortarboard', 'type' => 'select', 'grupo' => 'Matrícula', 'col' => 6, 'options' => $optNivel],
                ['key' => 'matricula', 'label' => 'Matrícula', 'icon' => 'bi-journal-check', 'type' => 'select', 'grupo' => 'Matrícula', 'col' => 6,
                 'options' => [['v' => 'vigente', 'l' => 'Vigente'], ['v' => 'sin', 'l' => 'Sin matrícula vigente']]],
                ['key' => 'estado', 'label' => 'Estado académico', 'icon' => 'bi-person-check', 'type' => 'select', 'grupo' => 'Matrícula', 'col' => 6,
                 'options' => [['v' => 'activo', 'l' => 'Activo'], ['v' => 'retirado', 'l' => 'Retirado'], ['v' => 'egresado', 'l' => 'Egresado'], ['v' => 'suspendido', 'l' => 'Suspendido']]],
                ['key' => 'servicio', 'label' => 'Servicio / producto', 'icon' => 'bi-cart-check', 'type' => 'select', 'grupo' => 'Facturación', 'col' => 12, 'options' => $optServicio],
                ['key' => 'facturado', 'label' => 'Facturado en el mes', 'icon' => 'bi-receipt', 'type' => 'select', 'grupo' => 'Facturación', 'col' => 6,
                 'options' => [['v' => 'si', 'l' => 'Con factura'], ['v' => 'no', 'l' => 'Sin factura']]],
                ['key' => 'mes_factura', 'label' => 'Mes (vacío = el actual)', 'icon' => 'bi-calendar-month', 'type' => 'select', 'grupo' => 'Facturación', 'col' => 6, 'options' => $optMes],
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
                <?php if (!empty($perm['actualizar'])): ?>
                <button type="button" class="btn btn-outline-primary" onclick="aluAbrirPortal()" title="QR y enlace para que los representantes actualicen sus datos"><i class="bi bi-qr-code"></i> QR representantes</button>
                <button type="button" class="btn btn-outline-primary" onclick="aluAbrirCargaFacturacion()" title="Cargar desde Excel los servicios que se facturan a cada alumno"><i class="bi bi-upload"></i> Cargar facturación</button>
                <?php endif; ?>
                <?php if (!empty($puedeFacturar)): ?>
                <button type="button" class="btn btn-outline-success" onclick="aluAbrirFacturarMasivo()" title="Generar la factura del mes de todos los alumnos del listado"><i class="bi bi-receipt-cutoff"></i> Generar facturas</button>
                <?php endif; ?>
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
                <?php // Los tres botones en una sola fila: se reparten el ancho y no se parten en dos líneas. ?>
                <div class="d-flex flex-nowrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill text-nowrap" onclick="aluPortalImprimir()" title="Imprimir una hoja con el QR y las instrucciones"><i class="bi bi-printer me-1"></i>Imprimir</button>
                    <button type="button" class="btn btn-outline-primary btn-sm flex-fill text-nowrap" onclick="aluPortalEnviar()" title="Envía el enlace por correo a los representantes de los alumnos del listado (con el filtro actual)"><i class="bi bi-envelope me-1"></i>Enviar por correo</button>
                    <button type="button" class="btn btn-outline-danger btn-sm flex-fill text-nowrap" onclick="aluPortalRegenerar()" title="Crea un QR nuevo; el anterior deja de funcionar"><i class="bi bi-arrow-repeat me-1"></i>Regenerar QR</button>
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

<?php
// Pestañas ocultas de esta ventana: preferencia propia (no la de 'alumnos'), porque el
// menú guarda la lista completa y pisaría las pestañas ocultas de la ficha del alumno.
$vistaCargaAlu = \App\Helpers\PreferenciasHelper::getPreferenciasVista('alumnos_carga_facturacion');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaCargaAlu, 'estiloVistaPestanasAluCarga');
?>
<!-- Modal: Cargar facturación desde Excel (servicios de cada alumno) -->
<div class="modal fade" id="modalCargaFactAlu" tabindex="-1" aria-hidden="true" data-cmg-nav="off">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold mb-0"><i class="bi bi-upload me-2 text-primary"></i>Cargar facturación desde Excel</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <!-- Pestañas: Importar / Historial (como la carga de Novedades) -->
            <div class="d-flex align-items-center bg-light px-3 pt-2">
                <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active py-2 small fw-bold" id="alu-cf-btn-importar" data-bs-toggle="tab"
                           href="#alu-cf-pane-importar" data-bs-target="#alu-cf-pane-importar" role="tab">
                            <i class="bi bi-upload me-1"></i> Importar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 small fw-bold" id="alu-cf-btn-historial" data-bs-toggle="tab"
                           href="#alu-cf-pane-historial" data-bs-target="#alu-cf-pane-historial" role="tab">
                            <i class="bi bi-clock-history me-1"></i> Historial
                        </a>
                    </li>
                </ul>
                <div class="ms-auto pb-1">
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas(['alu-cf-pane-historial' => 'Historial'], $vistaCargaAlu, 'alumnos_carga_facturacion', '__pestanas_ocultas__', 'estiloVistaPestanasAluCarga') ?>
                </div>
            </div>
            <div class="modal-body tab-content">
                <div class="tab-pane fade show active" id="alu-cf-pane-importar" role="tabpanel">
                <h6 class="fw-bold mb-1"><span class="badge bg-secondary me-1">1</span> Descargar la plantilla</h6>
                <p class="small text-muted mb-2">
                    Trae una fila por cada <b>alumno activo</b> y una columna por cada <b>servicio activo</b> de Productos,
                    con las celdas vacías.
                </p>
                <a href="<?= $urlBaseAlu ?>/plantilla-facturacion" class="btn btn-outline-secondary btn-sm mb-3">
                    <i class="bi bi-download me-1"></i>Descargar plantilla
                </a>

                <h6 class="fw-bold mb-1"><span class="badge bg-secondary me-1">2</span> Subir la plantilla completada</h6>
                <p class="small text-muted mb-2">
                    Escriba el precio para agregar o cambiar un servicio, <b>X</b> para el precio base y <b>0</b> para
                    quitárselo. Las celdas vacías no cambian nada de lo que el alumno ya tiene.
                </p>
                <div class="alert alert-info py-2 px-3 small mb-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Se conservan la cantidad, el descuento, el texto Adicional y el IVA que cada servicio tenga en la
                    ficha del alumno. La carga es <b>todo o nada</b>: con un solo error no se cambia ningún alumno.
                </div>
                <input type="file" id="aluCargaFactFile" class="form-control form-control-sm mb-3" accept=".xlsx,.xls">

                <h6 class="fw-bold mb-1"><span class="badge bg-secondary me-1">3</span> Textos de la factura <span class="fw-normal text-muted small">(opcional)</span></h6>
                <p class="small text-muted mb-2">
                    Se aplican a los alumnos del archivo que tengan alguna celda escrita. Vacíos: cada alumno conserva lo que ya tiene.
                </p>
                <label class="form-label small fw-bold text-muted mb-1 d-block" for="aluCargaFactDetalle">Adicional de cada ítem</label>
                <input type="text" id="aluCargaFactDetalle" class="form-control form-control-sm mb-1" maxlength="300" placeholder="Ej. Pensión {MES} {anio} - {alumno}">
                <div class="text-muted mb-3" style="font-size:.72rem;">Sale bajo la descripción de cada servicio escrito en el archivo (precio o X).</div>

                <div class="small fw-bold text-muted mb-1">Información adicional de la factura</div>
                <div class="border rounded overflow-hidden">
                    <table class="table table-sm mb-0" id="aluCargaFactInfo">
                        <thead>
                            <tr class="border-bottom">
                                <th style="width:35%;">Concepto</th>
                                <th>Detalle</th>
                                <th style="width:30px;"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div class="p-2 border-top bg-light">
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="aluCargaFactAgregarInfo()">
                            <i class="bi bi-plus-circle me-1"></i> Agregar información
                        </button>
                    </div>
                </div>
                <div class="text-muted mt-1" style="font-size:.72rem;">
                    Reemplaza la información adicional de cada alumno del archivo. Marcadores: <code>{niño}</code> <code>{alumno}</code>
                    <code>{nombres}</code> <code>{apellidos}</code> <code>{cedula}</code> <code>{campus}</code> <code>{curso}</code>
                    <code>{mes}</code> <code>{MES}</code> <code>{anio}</code> <code>{mes_anio}</code>.
                </div>
                <div id="aluCargaFactResult" class="mt-3"></div>
                </div>
                <!-- ── Pestaña: Historial ─────────────────────────────────── -->
                <div class="tab-pane fade" id="alu-cf-pane-historial" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="small text-muted">
                            Últimas <b><?= \App\Services\modulos\AlumnoCargaFacturacionService::HISTORIAL ?></b> cargas. Una carga se puede eliminar
                            (los alumnos vuelven a como estaban antes) mientras <b>ninguno</b> de sus alumnos se haya facturado ni modificado después.
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary border-0 px-2" onclick="aluCargarHistorialFact()" title="Actualizar">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div id="aluCargasFactLista" class="border rounded-2" style="max-height:50vh;overflow:auto;"></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm ms-auto" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm alu-cf-accion" id="btnAluCargaFact" onclick="aluImportarFacturacion()"><i class="bi bi-upload me-1"></i> Importar</button>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseAlu ?>';
        const el = () => document.getElementById('modalCargaFactAlu');
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        let importando = false;

        const tbodyInfo = () => document.querySelector('#aluCargaFactInfo tbody');

        // Misma lista de conceptos sugeridos que la ficha del alumno (datalist aluConceptosInfo).
        window.aluCargaFactAgregarInfo = function () {
            if (tbodyInfo().rows.length >= 10) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `<td class="p-0"><input type="text" class="form-control form-control-sm border-0 shadow-none alu-cf-concepto" maxlength="300" list="aluConceptosInfo" style="padding:0 4px;height:24px;font-size:0.78rem;"></td>
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 shadow-none alu-cf-detalle" maxlength="300" style="padding:0 4px;height:24px;font-size:0.78rem;"></td>
                <td class="p-0 text-center align-middle"><button type="button" class="btn btn-link btn-sm text-danger p-0" title="Quitar" onclick="this.closest('tr').remove()"><i class="bi bi-x-lg"></i></button></td>`;
            tbodyInfo().appendChild(tr);
            tr.querySelector('input').focus();
        };

        window.aluAbrirCargaFacturacion = function () {
            document.getElementById('aluCargaFactFile').value = '';
            document.getElementById('aluCargaFactResult').innerHTML = '';
            // Siempre se abre en la pestaña Importar.
            const btnTab = document.getElementById('alu-cf-btn-importar');
            if (btnTab && !btnTab.classList.contains('active')) bootstrap.Tab.getOrCreateInstance(btnTab).show();
            document.querySelectorAll('.alu-cf-accion').forEach(x => x.classList.remove('d-none'));
            bootstrap.Modal.getOrCreateInstance(el()).show();
        };

        // El botón Importar solo aplica a la pestaña Importar; el historial se carga al entrar.
        document.getElementById('alu-cf-btn-historial')?.addEventListener('shown.bs.tab', () => {
            document.querySelectorAll('.alu-cf-accion').forEach(x => x.classList.add('d-none'));
            window.aluCargarHistorialFact();
        });
        document.getElementById('alu-cf-btn-importar')?.addEventListener('shown.bs.tab', () => {
            document.querySelectorAll('.alu-cf-accion').forEach(x => x.classList.remove('d-none'));
        });

        // ── Historial de cargas: listado y reversión ─────────────────────────
        function filaCarga(c, puedeEliminar) {
            let accion;
            if (!puedeEliminar) {
                accion = '<span class="text-muted" title="No tiene permiso para eliminar"><i class="bi bi-lock-fill"></i></span>';
            } else if (c.reversible) {
                accion = `<button type="button" class="btn btn-outline-danger btn-sm border-0 px-2"
                                  onclick="aluEliminarCargaFact(${c.id}, ${c.alumnos})" title="Eliminar la carga: los alumnos vuelven a como estaban antes">
                              <i class="bi bi-trash"></i></button>`;
            } else {
                accion = `<span class="text-muted" title="${esc(c.motivo_bloqueo)}"><i class="bi bi-lock-fill"></i></span>`;
            }
            const cambios = [
                c.agregados ? `${c.agregados} agregado(s)` : '',
                c.cambiados ? `${c.cambiados} cambiado(s)` : '',
                c.quitados ? `${c.quitados} quitado(s)` : '',
                c.info ? `info. adicional en ${c.info}` : '',
            ].filter(Boolean).join(', ');
            return `<tr>
                        <td class="ps-2 text-nowrap">${esc(c.fecha)}</td>
                        <td class="text-truncate" style="max-width:170px;" title="${esc(c.archivo)}">${esc(c.archivo) || '—'}</td>
                        <td class="text-center">${c.alumnos}</td>
                        <td class="small">${esc(cambios) || '—'}${c.reversible ? '' : `<div class="text-muted" style="font-size:.7rem;">${esc(c.motivo_bloqueo)}</div>`}</td>
                        <td class="text-truncate" style="max-width:120px;">${esc(c.usuario)}</td>
                        <td class="text-center pe-2">${accion}</td>
                    </tr>`;
        }

        window.aluCargarHistorialFact = async function () {
            const cont = document.getElementById('aluCargasFactLista');
            cont.innerHTML = '<div class="text-center text-muted small py-3"><span class="spinner-border spinner-border-sm me-1"></span>Cargando…</div>';
            try {
                const resp = await fetch(`${urlBase}/cargas-facturacion-ajax`);
                const json = await resp.json();
                if (!json.ok) {
                    cont.innerHTML = `<div class="text-center text-muted small py-3">${esc(json.error)}</div>`;
                    return;
                }
                if (!json.data.length) {
                    cont.innerHTML = '<div class="text-center text-muted small py-3">Todavía no hay cargas registradas.</div>';
                    return;
                }
                cont.innerHTML = `<table class="table table-sm table-hover align-middle mb-0" style="font-size:.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-2">Fecha</th><th>Archivo</th><th class="text-center">Alumnos</th>
                                <th>Cambios</th><th>Usuario</th><th class="text-center pe-2" style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>${json.data.map(c => filaCarga(c, json.puede_eliminar)).join('')}</tbody>
                    </table>`;
            } catch (e) {
                cont.innerHTML = '<div class="text-center text-muted small py-3">No se pudo cargar el historial.</div>';
            }
        };

        let eliminandoCarga = false;
        window.aluEliminarCargaFact = async function (id, alumnos) {
            if (eliminandoCarga) return;
            eliminandoCarga = true;
            try {
                const r = await Swal.fire({
                    target: el(), heightAuto: false, icon: 'warning',
                    title: '¿Eliminar la carga?',
                    html: `Los <b>${alumnos}</b> alumno(s) de esta carga volverán a tener los servicios, precios y textos que tenían antes de subirla.`,
                    showCancelButton: true, confirmButtonColor: '#d33', reverseButtons: true,
                    confirmButtonText: 'Sí, eliminar la carga', cancelButtonText: 'Cancelar',
                });
                if (!r.isConfirmed) return;
                const fd = new FormData();
                fd.append('id_carga', id);
                const resp = await fetch(`${urlBase}/eliminar-carga-facturacion`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ target: el(), heightAuto: false, icon: 'success', title: 'Carga eliminada', text: json.msg, timer: 2500, showConfirmButton: false });
                } else {
                    Swal.fire({ target: el(), heightAuto: false, icon: 'error', title: 'No se pudo eliminar', text: json.error || 'Error al eliminar la carga.', confirmButtonText: 'Aceptar' });
                }
                window.aluCargarHistorialFact();
            } catch (e) {
                Swal.fire({ target: el(), heightAuto: false, icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.', confirmButtonText: 'Aceptar' });
            } finally {
                eliminandoCarga = false;
            }
        };

        window.aluImportarFacturacion = async function () {
            if (importando) return;
            const fileInput = document.getElementById('aluCargaFactFile');
            if (!fileInput.files.length) {
                Swal.fire({ target: el(), icon: 'info', title: 'Seleccione un archivo', timer: 1500, showConfirmButton: false });
                return;
            }
            importando = true;
            const btn = document.getElementById('btnAluCargaFact');
            const cont = document.getElementById('aluCargaFactResult');
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importando...';
            cont.innerHTML = '';
            try {
                const fd = new FormData(); fd.append('archivo', fileInput.files[0]);
                fd.append('detalle', document.getElementById('aluCargaFactDetalle').value.trim());
                const info = [...tbodyInfo().rows].map(tr => ({
                    concepto: tr.querySelector('.alu-cf-concepto').value.trim(),
                    detalle: tr.querySelector('.alu-cf-detalle').value.trim(),
                })).filter(f => f.concepto !== '' || f.detalle !== '');
                fd.append('info_adicional_json', JSON.stringify(info));
                const resp = await fetch(`${urlBase}/importar-facturacion`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) {
                    cont.innerHTML = `<div class="alert alert-danger small py-2 mb-0">${esc(json.error)}</div>`;
                } else if (json.errores && json.errores.length) {
                    let html = `<div class="alert alert-danger small py-2 mb-2"><b>No se cambió ningún alumno.</b>
                            Corrija ${json.errores.length} error(es) de la plantilla y vuelva a subirla.</div>
                        <div class="alert alert-warning small py-2 mb-0" style="max-height:220px;overflow:auto;">
                            <b>Filas con error:</b><ul class="mb-0 mt-1 ps-3">`;
                    json.errores.forEach(e => {
                        html += `<li>Fila ${e.fila}${e.servicio ? ' · <b>' + esc(e.servicio) + '</b>' : ''}: ${esc(e.error)}</li>`;
                    });
                    cont.innerHTML = html + '</ul></div>';
                } else if (!json.alumnos) {
                    cont.innerHTML = `<div class="alert alert-secondary small py-2 mb-0">No hubo cambios: el archivo coincide con lo que ya tienen los alumnos (${json.sin_cambios} revisado(s)).</div>`;
                } else {
                    cont.innerHTML = `<div class="alert alert-success small py-2 mb-0">
                            <b>${json.alumnos}</b> alumno(s) actualizado(s): ${json.agregados} servicio(s) agregado(s),
                            ${json.cambiados} cambiado(s) o reactivado(s) y ${json.quitados} quitado(s)${json.info ? `; información adicional en ${json.info} alumno(s)` : ''}.
                            <span class="text-muted">${json.sin_cambios} alumno(s) sin cambios.</span></div>`;
                }
            } catch (e) {
                cont.innerHTML = '<div class="alert alert-danger small py-2 mb-0">Error de red al importar.</div>';
            }
            importando = false;
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload me-1"></i> Importar';
        };
    })();
</script>
<?php endif; ?>

<?php if (!empty($puedeFacturar)): ?>
<style>
    .alu-fm-scroll { max-height: 48vh; overflow: auto; }
    .alu-fm-scroll thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; }
    #formAluFacturarMasivo .form-select, #formAluFacturarMasivo .form-control, #formAluFacturarMasivo .btn { height: 28px; font-size: .75rem; }
</style>
<!-- Modal: Generar las facturas del mes de los alumnos del listado -->
<div class="modal fade" id="modalAluFacturarMasivo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-cmg-nav="off">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold mb-0"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Generar facturas de alumnos</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formAluFacturarMasivo" class="d-flex flex-wrap align-items-start gap-2 mb-2" onsubmit="return false;">
                    <div style="width:140px;">
                        <label class="form-label small fw-bold text-muted mb-1 d-block" for="aluFmMes">Mes</label>
                        <input type="month" class="form-control form-control-sm" id="aluFmMes">
                    </div>
                    <div style="width:150px;">
                        <label class="form-label small fw-bold text-muted mb-1 d-block" for="aluFmSerie">Serie</label>
                        <select class="form-select form-select-sm" id="aluFmSerie">
                            <?php if (count($seriesFacturacion ?? []) !== 1): ?>
                                <option value=""><?= empty($seriesFacturacion) ? '-- No hay series activas --' : '-- Seleccione --' ?></option>
                            <?php endif; ?>
                            <?php foreach ($seriesFacturacion ?? [] as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" title="<?= htmlspecialchars((string) ($s['nombre'] ?? '')) ?>"><?= htmlspecialchars((string) $s['serie']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="width:300px;">
                        <label class="form-label small fw-bold text-muted mb-1 d-block" for="aluFmTexto" title="Se usa en los servicios que no tienen «Adicional» propio">Texto para ítems sin adicional</label>
                        <input type="text" class="form-control form-control-sm" id="aluFmTexto" maxlength="300" placeholder="Ej. {MES} {anio}">
                    </div>
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        <div>
                            <label class="form-label small mb-1 d-block">&nbsp;</label>
                            <div class="form-check small mb-0">
                                <input class="form-check-input" type="checkbox" id="aluFmSerieAlumno" checked>
                                <label class="form-check-label" for="aluFmSerieAlumno" title="Si el alumno tiene una serie en su pestaña Facturación, se usa esa; si no, la elegida aquí">Usar la serie de cada alumno</label>
                            </div>
                            <div class="form-check small mb-0">
                                <input class="form-check-input" type="checkbox" id="aluFmIncluirFacturados">
                                <label class="form-check-label" for="aluFmIncluirFacturados">Permitir alumnos ya facturados en el mes</label>
                            </div>
                        </div>
                        <div>
                            <label class="form-label small mb-1 d-block">&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-sm px-3" id="btnAluFmMostrar" onclick="aluFmMostrar()"><i class="bi bi-search me-1"></i>Mostrar</button>
                        </div>
                    </div>
                </form>
                <div class="small text-muted mb-2" id="aluFmAlcance"></div>
                <div class="border rounded alu-fm-scroll">
                    <table class="table table-sm table-hover mb-0 text-nowrap" style="font-size:.78rem;">
                        <thead>
                            <tr class="border-bottom">
                                <th style="width:30px;" class="text-center"><input type="checkbox" class="form-check-input" id="aluFmTodos" title="Marcar / desmarcar todos"></th>
                                <th>Alumno</th>
                                <th>Campus</th>
                                <th>Curso</th>
                                <th>Cliente que factura</th>
                                <th class="text-center">Servicios</th>
                                <th class="text-end">Subtotal</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="aluFmFilas">
                            <tr><td colspan="8" class="text-center text-muted py-4">Elija el mes y pulse <b>Mostrar</b>.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="aluFmProgreso" class="mt-3 d-none">
                    <div class="progress" style="height:18px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="aluFmBarra" style="width:0%">0%</div>
                    </div>
                    <div class="small text-muted mt-1" id="aluFmProgresoTxt"></div>
                </div>
                <div id="aluFmResultado" class="mt-3"></div>
                <div class="small text-muted mt-2">
                    Cada alumno marcado recibe <b>una factura en borrador</b> con todos sus servicios activos, su información adicional y el adicional de cada ítem,
                    igual que con el botón <i class="bi bi-receipt-cutoff"></i> de su ficha. Se envían al SRI desde Facturas de Venta.
                    Los alumnos que ya tienen factura del mes se omiten, salvo que marque la opción de arriba.
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <div class="small fw-bold me-auto" id="aluFmResumen"></div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="btnAluFmCerrar"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                <button type="button" class="btn btn-success btn-sm px-4 shadow-sm" id="btnAluFmGenerar" onclick="aluFmGenerar()" disabled><i class="bi bi-receipt-cutoff me-1"></i> Generar</button>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseAlu ?>';
        const el = () => document.getElementById('modalAluFacturarMasivo');
        const $ = (id) => document.getElementById(id);
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const money = (v) => (typeof window.CMG_r2 === 'function' ? window.CMG_r2(v) : Number(v || 0)).toFixed(2);
        const swal = (o) => Swal.fire(Object.assign({ target: el(), heightAuto: false, confirmButtonText: 'Aceptar' }, o));
        const ESTADOS = {
            listo:         ['Lista para facturar', 'success'],
            facturado:     ['Ya facturado en el mes', 'warning'],
            sin_servicios: ['Sin servicios con monto', 'secondary'],
            sin_cliente:   ['Sin cliente que factura', 'danger'],
        };
        let filas = [];
        let lote = 10;
        let mesMostrado = '';
        let generando = false;

        const marcable = (f) => f.estado === 'listo' || (f.estado === 'facturado' && $('aluFmIncluirFacturados').checked);

        function pintar() {
            const tbody = $('aluFmFilas');
            if (!filas.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No hay alumnos activos en el listado actual.</td></tr>';
            } else {
                tbody.innerHTML = filas.map((f, i) => {
                    const [txt, color] = ESTADOS[f.estado] || [f.estado, 'secondary'];
                    const ok = marcable(f);
                    return `<tr>
                        <td class="text-center"><input type="checkbox" class="form-check-input alu-fm-chk" data-i="${i}" ${ok && f.marcado ? 'checked' : ''} ${ok ? '' : 'disabled'}></td>
                        <td>${esc(f.alumno)}</td>
                        <td>${esc(f.campus)}</td>
                        <td>${esc(f.nivel)}</td>
                        <td title="${esc(f.cliente_identificacion)}">${esc(f.cliente)}</td>
                        <td class="text-center">${f.servicios}</td>
                        <td class="text-end">${money(f.subtotal)}</td>
                        <td><span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25">${esc(f.resultado || txt)}</span></td>
                    </tr>`;
                }).join('');
            }
            resumen();
        }

        function seleccionados() { return filas.filter(f => marcable(f) && f.marcado); }

        function resumen() {
            const sel = seleccionados();
            const total = sel.reduce((a, f) => a + Number(f.subtotal || 0), 0);
            $('aluFmResumen').textContent = filas.length
                ? `${sel.length} alumno(s) a facturar · subtotal ${money(total)} (sin IVA)`
                : '';
            $('btnAluFmGenerar').disabled = generando || !sel.length;
            $('btnAluFmGenerar').innerHTML = `<i class="bi bi-receipt-cutoff me-1"></i> Generar${sel.length ? ' ' + sel.length + ' factura(s)' : ''}`;
        }

        $('aluFmFilas').addEventListener('change', (e) => {
            if (!e.target.classList.contains('alu-fm-chk')) return;
            filas[+e.target.dataset.i].marcado = e.target.checked;
            resumen();
        });
        $('aluFmTodos').addEventListener('change', (e) => {
            filas.forEach(f => { if (marcable(f)) f.marcado = e.target.checked; });
            pintar();
        });
        $('aluFmIncluirFacturados').addEventListener('change', () => pintar());
        // Si cambia el mes después de mostrar, la lista ya no corresponde: hay que volver a mostrar.
        $('aluFmMes').addEventListener('change', () => {
            if (mesMostrado && $('aluFmMes').value !== mesMostrado) {
                filas = []; mesMostrado = '';
                $('aluFmFilas').innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Cambió el mes: pulse <b>Mostrar</b>.</td></tr>';
                resumen();
            }
        });
        // No se cierra a mitad de la generación (los lotes los manda esta pantalla).
        el().addEventListener('hide.bs.modal', (e) => {
            if (generando) { e.preventDefault(); swal({ icon: 'info', title: 'Generando facturas', text: 'Espere a que termine el proceso.' }); }
        });

        window.aluAbrirFacturarMasivo = function () {
            if (!$('aluFmMes').value) {
                const d = new Date();
                $('aluFmMes').value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            }
            const b = document.getElementById('buscarAlumno')?.value || '';
            $('aluFmAlcance').innerHTML = b
                ? `<i class="bi bi-funnel me-1"></i>Alumnos activos del listado con el filtro aplicado: <code>${esc(b)}</code>`
                : '<i class="bi bi-people me-1"></i>Todos los alumnos activos del listado.';
            $('aluFmResultado').innerHTML = '';
            bootstrap.Modal.getOrCreateInstance(el()).show();
        };

        window.aluFmMostrar = async function () {
            if (generando) return;
            const mes = $('aluFmMes').value;
            if (!mes) { swal({ icon: 'info', title: 'Elija el mes' }); return; }
            const b = document.getElementById('buscarAlumno')?.value || '';
            $('aluFmFilas').innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></td></tr>';
            $('aluFmResultado').innerHTML = '';
            try {
                const r = await fetch(`${urlBase}/prepararFacturasMasivoAjax?mes=${encodeURIComponent(mes)}&b=${encodeURIComponent(b)}`);
                const j = await r.json();
                if (!j.ok) { filas = []; pintar(); swal({ icon: 'error', title: 'Error', text: j.error || 'No se pudo preparar.' }); return; }
                lote = j.lote || 10;
                mesMostrado = mes;
                filas = j.filas.map(f => Object.assign(f, { marcado: f.estado === 'listo', resultado: '' }));
                $('aluFmTodos').checked = true;
                pintar();
            } catch (e) {
                filas = []; pintar();
                swal({ icon: 'error', title: 'Error', text: 'Error de conexión.' });
            }
        };

        window.aluFmGenerar = async function () {
            if (generando) return;
            generando = true; // antes del primer await (CLAUDE.md §8)
            resumen();
            const sel = seleccionados();
            const serieSel = $('aluFmSerie');
            if (!sel.length || !serieSel.value || mesMostrado !== $('aluFmMes').value) {
                generando = false; resumen();
                swal({ icon: 'info', title: 'Atención', text: !serieSel.value ? 'Seleccione la serie de las facturas.' : 'Pulse Mostrar para cargar los alumnos del mes.' });
                return;
            }
            const serieTxt = serieSel.options[serieSel.selectedIndex].text;
            const mesTxt = mesMostrado.split('-').reverse().join('-');
            const conf = await swal({
                icon: 'question', title: 'Generar facturas',
                html: `Se generarán <b>${sel.length}</b> factura(s) en <b>borrador</b> del mes <b>${esc(mesTxt)}</b>, serie <b>${esc(serieTxt)}</b>`
                    + ($('aluFmSerieAlumno').checked ? ' (o la serie de cada alumno)' : '') + '.',
                showCancelButton: true, confirmButtonText: 'Generar', cancelButtonText: 'Cancelar', reverseButtons: true,
            });
            if (!conf.isConfirmed) { generando = false; resumen(); return; }

            document.querySelectorAll('#formAluFacturarMasivo input, #formAluFacturarMasivo select, #formAluFacturarMasivo button, .alu-fm-chk, #aluFmTodos')
                .forEach(x => { x.dataset.antes = x.disabled ? '1' : ''; x.disabled = true; });
            $('btnAluFmCerrar').disabled = true;
            $('aluFmProgreso').classList.remove('d-none');
            $('aluFmResultado').innerHTML = '';

            const res = { generada: [], omitida: [], error: [] };
            let hechos = 0;
            const avance = () => {
                const pct = Math.round(hechos * 100 / sel.length);
                $('aluFmBarra').style.width = pct + '%';
                $('aluFmBarra').textContent = pct + '%';
                $('aluFmProgresoTxt').textContent = `${hechos} de ${sel.length} alumno(s) procesados`;
            };
            avance();
            for (let i = 0; i < sel.length; i += lote) {
                const grupo = sel.slice(i, i + lote);
                const fd = new FormData();
                fd.append('ids', JSON.stringify(grupo.map(f => f.id)));
                fd.append('mes', mesMostrado);
                fd.append('id_punto_emision', serieSel.value);
                fd.append('usar_serie_alumno', $('aluFmSerieAlumno').checked ? '1' : '');
                fd.append('incluir_facturados', $('aluFmIncluirFacturados').checked ? '1' : '');
                fd.append('texto_item', $('aluFmTexto').value.trim());
                let lista = null;
                try {
                    const r = await fetch(`${urlBase}/generarFacturasMasivoAjax`, { method: 'POST', body: fd });
                    const j = await r.json();
                    lista = j.ok ? j.resultados : grupo.map(f => ({ id: f.id, alumno: f.alumno, estado: 'error', mensaje: j.error || 'Error' }));
                } catch (e) {
                    // Sin respuesta: puede que se hayan generado. Al volver a Mostrar, los ya facturados salen marcados así y no se repiten.
                    lista = grupo.map(f => ({ id: f.id, alumno: f.alumno, estado: 'error', mensaje: 'Error de conexión: pulse Mostrar para ver si se generó antes de reintentar.' }));
                }
                lista.forEach(x => {
                    (res[x.estado] || res.error).push(x);
                    const f = filas.find(y => y.id === x.id);
                    if (f) {
                        f.resultado = x.estado === 'generada' ? 'Factura generada' : (x.estado === 'omitida' ? 'Omitido' : 'Con error');
                        f.estado = x.estado === 'generada' ? 'facturado' : f.estado;
                        f.marcado = false;
                    }
                });
                hechos += grupo.length;
                avance();
            }

            generando = false;
            document.querySelectorAll('#formAluFacturarMasivo input, #formAluFacturarMasivo select, #formAluFacturarMasivo button, #aluFmTodos')
                .forEach(x => { x.disabled = x.dataset.antes === '1'; });
            $('btnAluFmCerrar').disabled = false;
            $('aluFmBarra').classList.remove('progress-bar-animated');
            pintar();

            const total = res.generada.reduce((a, x) => a + Number(x.importe || 0), 0);
            let html = `<div class="alert alert-${res.error.length ? 'warning' : 'success'} small py-2 mb-2">
                    <b>${res.generada.length}</b> factura(s) generada(s) en borrador por <b>${money(total)}</b>.
                    ${res.omitida.length ? `${res.omitida.length} alumno(s) omitido(s). ` : ''}${res.error.length ? `<b>${res.error.length}</b> con error.` : ''}
                    Envíelas al SRI desde <b>Facturas de Venta</b>.</div>`;
            const detalle = [...res.error, ...res.omitida, ...res.generada.filter(x => x.mensaje)];
            if (detalle.length) {
                html += '<div class="alert alert-light border small py-2 mb-0" style="max-height:200px;overflow:auto;"><ul class="mb-0 ps-3">'
                    + detalle.map(x => `<li><b>${esc(x.alumno)}</b>: ${esc(x.mensaje)}</li>`).join('') + '</ul></div>';
            }
            $('aluFmResultado').innerHTML = html;
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
