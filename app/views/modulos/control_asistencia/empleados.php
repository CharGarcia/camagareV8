<?php

/** @var string $titulo @var array $perm @var string $rutaModulo */
/** @var array $rows @var array $tokens @var int $total @var int $page @var int $totalPages @var string $buscar */

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>

<style>
    .casis-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .casis-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-vcard me-2 text-primary"></i> <?= $h($titulo) ?></h5>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= $urlBase ?>" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-geo-alt-fill me-1"></i> Puntos</a>
        <a href="<?= $urlBase ?>/marcaciones" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-list-check me-1"></i> Marcaciones</a>
    </div>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="get" action="<?= $urlBase ?>/empleados" class="d-flex align-items-center gap-2">
            <input type="text" name="b" value="<?= $h($buscar) ?>" class="form-control form-control-sm" style="width:280px;" placeholder="Buscar empleado...">
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small fw-medium"><?= (int) $total ?> empleados</span>
            <div class="btn-group btn-group-sm">
                <a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $urlBase ?>/empleados?b=<?= urlencode($buscar) ?>&page=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                <a class="btn btn-outline-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $urlBase ?>/empleados?b=<?= urlencode($buscar) ?>&page=<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="casis-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3">Empleado</th>
                        <th>Identificación</th>
                        <th class="text-center">Credencial</th>
                        <th class="text-center">Rostro</th>
                        <th class="text-center" style="width: 260px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No hay empleados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $idEmp = (int) $row['id'];
                            $tieneCred = isset($tokens[$idEmp]);
                            $tieneRostro = isset($rostros[$idEmp]);
                            $nombreJs = $h(addslashes($row['nombres_apellidos']));
                        ?>
                            <tr>
                                <td class="ps-3 fw-medium"><?= $h($row['nombres_apellidos']) ?></td>
                                <td><code class="text-secondary"><?= $h($row['identificacion']) ?></code></td>
                                <td class="text-center">
                                    <?php if ($tieneCred): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle me-1"></i>Vinculada</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Sin credencial</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($tieneRostro): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-person-badge me-1"></i>Enrolado</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($perm['crear'] || $tieneCred): ?>
                                        <button class="btn btn-outline-primary btn-xs px-2" onclick="verQrEmpleado(<?= $idEmp ?>, '<?= $nombreJs ?>')">
                                            <i class="bi bi-qr-code"></i> <?= $tieneCred ? 'Ver QR' : 'Generar QR' ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($perm['crear']): ?>
                                        <button class="btn btn-outline-info btn-xs px-2" onclick="abrirRostro(<?= $idEmp ?>, '<?= $nombreJs ?>')">
                                            <i class="bi bi-camera"></i> <?= $tieneRostro ? 'Actualizar rostro' : 'Registrar rostro' ?>
                                        </button>
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

<!-- Hoja de impresión del QR personal -->
<style>
@media print {
    body * { visibility: hidden !important; }
    #casisEmpHoja, #casisEmpHoja * { visibility: visible !important; }
    #casisEmpHoja { position: fixed !important; inset: 0 !important; display: flex !important; margin: 0 !important; }
}
#casisEmpHoja { display: none; flex-direction: column; align-items: center; justify-content: center; padding: 80px 40px; width: 100%; min-height: 100dvh; font-family: Arial, sans-serif; text-align: center; background: #fff; }
#casisEmpHoja .n { font-size: 1.6rem; font-weight: 700; margin-bottom: 6px; }
#casisEmpHoja .s { color: #444; margin-bottom: 28px; }
#casisEmpHoja img { width: 300px; height: 300px; }
</style>
<div id="casisEmpHoja">
    <div class="n" id="empPrintNombre"></div>
    <div class="s">Abre este QR una sola vez en tu celular para vincular tu credencial</div>
    <img id="empPrintImg" src="" alt="QR">
</div>

<!-- Modal QR personal -->
<div class="modal fade" id="modalEmpQr" tabindex="-1" style="z-index:1070">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-vcard text-primary me-2"></i>Credencial personal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="fw-bold mb-1" id="empQrNombre"></p>
                <p class="text-muted small mb-3">El empleado abre este QR una vez en su celular para vincularse. Luego marca escaneando el QR del punto.</p>
                <div id="empQrSpinner" class="py-4"><div class="spinner-border text-primary"></div><div class="small text-muted mt-2">Generando...</div></div>
                <img id="empQrImg" src="" alt="QR" class="img-fluid rounded-3 border shadow-sm d-none" style="max-width:280px;">
                <div class="mt-3 p-2 bg-light rounded-3 border">
                    <small class="text-muted d-block mb-1">Enlace personal:</small>
                    <span id="empQrLink" class="small text-primary text-break"></span>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2 justify-content-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="empCopiar()"><i class="bi bi-clipboard me-1"></i>Copiar</button>
                <?php if ($perm['actualizar']): ?>
                <button type="button" class="btn btn-outline-warning btn-sm" onclick="empRegenerar()"><i class="bi bi-arrow-repeat me-1"></i>Regenerar</button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.CASIS_URL_BASE = '<?= $urlBase ?>';
    (function () {
        'use strict';
        const urlBase = '<?= $urlBase ?>';
        let modalQr = null;
        let empActual = null;

        const swalErr = (m) => window.Swal ? Swal.fire({ icon: 'error', title: 'Error', text: m }) : alert(m);

        function getModal() {
            if (!modalQr && typeof bootstrap !== 'undefined') modalQr = new bootstrap.Modal(document.getElementById('modalEmpQr'));
            return modalQr;
        }

        function pintar(nombre, link) {
            document.getElementById('empQrNombre').textContent = nombre;
            document.getElementById('empQrLink').textContent = link;
            document.getElementById('empPrintNombre').textContent = nombre;
            const img = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(link)}&size=300x300&margin=10`;
            const el = document.getElementById('empQrImg');
            const sp = document.getElementById('empQrSpinner');
            sp.classList.remove('d-none'); el.classList.add('d-none');
            el.onload = () => { sp.classList.add('d-none'); el.classList.remove('d-none'); };
            el.src = img;
            document.getElementById('empPrintImg').src = img;
        }

        window.verQrEmpleado = function (id, nombre) {
            empActual = { id, nombre };
            const fd = new FormData(); fd.append('id_empleado', id);
            fetch(`${urlBase}/enrolarEmpleadoAjax`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(j => {
                    if (!j.ok) { swalErr(j.error); return; }
                    empActual.link = j.link;
                    pintar(nombre, j.link);
                    getModal()?.show();
                })
                .catch(() => swalErr('Error de red.'));
        };

        window.empCopiar = function () {
            if (empActual && navigator.clipboard) navigator.clipboard.writeText(empActual.link)
                .then(() => window.Swal && Swal.fire({ icon: 'success', title: 'Copiado', timer: 1000, showConfirmButton: false }));
        };

        window.empRegenerar = function () {
            if (!empActual) return;
            const run = () => {
                const fd = new FormData(); fd.append('id_empleado', empActual.id);
                fetch(`${urlBase}/regenerarEmpleadoAjax`, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(j => {
                        if (!j.ok) { swalErr(j.error); return; }
                        empActual.link = j.link;
                        pintar(empActual.nombre, j.link);
                        window.Swal && Swal.fire({ icon: 'success', title: 'QR regenerado', timer: 1200, showConfirmButton: false });
                    });
            };
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: '¿Regenerar credencial?', text: 'El QR anterior dejará de funcionar en el celular del empleado.', showCancelButton: true, confirmButtonText: 'Regenerar', cancelButtonText: 'Cancelar' })
                    .then(res => { if (res.isConfirmed) run(); });
            } else if (confirm('¿Regenerar credencial?')) run();
        };
    })();
</script>

<!-- Modal: registrar rostro -->
<div class="modal fade" id="modalRostro" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
    <div class="modal-header bg-light py-3"><h6 class="modal-title fw-bold"><i class="bi bi-camera text-info me-2"></i>Registrar rostro — <span id="rostroNombre"></span></h6><button class="btn-close" data-bs-dismiss="modal" onclick="cerrarRostro()"></button></div>
    <div class="modal-body text-center">
        <div class="alert alert-info small py-2 text-start mb-3">
            <i class="bi bi-info-circle me-1"></i> El empleado debe mirar a la cámara con buena luz. Se toman 3 muestras. Sus datos biométricos se guardan como vector, no como foto.
        </div>
        <div style="position:relative;width:100%;max-width:320px;aspect-ratio:3/4;margin:0 auto;background:#000;border-radius:14px;overflow:hidden;">
            <video id="rostroVideo" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);"></video>
        </div>
        <div id="rostroMsg" class="small text-muted mt-2" style="min-height:20px;"></div>
        <div class="form-check d-inline-flex align-items-center gap-1 mt-2 text-start">
            <input class="form-check-input" type="checkbox" id="rostroConsent">
            <label class="form-check-label small" for="rostroConsent">El empleado autoriza el registro de su rostro (LOPDP).</label>
        </div>
    </div>
    <div class="modal-footer bg-light p-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal" onclick="cerrarRostro()">Cancelar</button>
        <button class="btn btn-info btn-sm text-white px-4" id="btnCapturarRostro" onclick="capturarRostro()"><i class="bi bi-camera me-1"></i>Capturar y guardar</button>
    </div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
<script>window.CASIS_FACE_MODELS = window.CASIS_FACE_MODELS || null;</script>
<script src="<?= $base ?>/js/modulos/face_asistencia.js?v=<?= time() ?>"></script>
<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBase ?>';
        let modal = null, stream = null, empId = null, modelsReady = false;
        const msg = (t) => document.getElementById('rostroMsg').textContent = t;
        const err = (m) => window.Swal ? Swal.fire({ icon: 'error', title: 'Error', text: m }) : alert(m);

        window.abrirRostro = async function (id, nombre) {
            empId = id;
            document.getElementById('rostroNombre').textContent = nombre;
            document.getElementById('rostroConsent').checked = false;
            msg('Cargando modelos faciales...');
            modal = modal || new bootstrap.Modal(document.getElementById('modalRostro'));
            modal.show();
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                document.getElementById('rostroVideo').srcObject = stream;
            } catch (e) { msg('No se pudo abrir la cámara.'); return; }
            try { await window.CASIS_FACE.loadModels(); modelsReady = true; msg('Listo. Presiona «Capturar y guardar».'); }
            catch (e) { modelsReady = false; msg('No se pudieron cargar los modelos faciales (revisa la conexión).'); }
        };

        window.cerrarRostro = function () {
            if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        };

        window.capturarRostro = async function () {
            if (!document.getElementById('rostroConsent').checked) { err('Marca el consentimiento del empleado.'); return; }
            if (!modelsReady) { err('Los modelos faciales no están listos.'); return; }
            const btn = document.getElementById('btnCapturarRostro');
            btn.disabled = true;
            const video = document.getElementById('rostroVideo');
            const muestras = [];
            try {
                for (let i = 0; i < 3; i++) {
                    msg(`Capturando muestra ${i + 1} de 3...`);
                    const d = await window.CASIS_FACE.descriptor(video);
                    if (d) muestras.push(d);
                    await new Promise(r => setTimeout(r, 400));
                }
            } catch (e) {}
            if (muestras.length === 0) { msg(''); btn.disabled = false; err('No se detectó ningún rostro. Acércate a la cámara con buena luz.'); return; }
            const desc = window.CASIS_FACE.promediar(muestras);
            const fd = new FormData();
            fd.append('id_empleado', empId);
            fd.append('descriptor', JSON.stringify(desc));
            fd.append('consentimiento', '1');
            try {
                const r = await fetch(`${urlBase}/enrolarRostroAjax`, { method: 'POST', body: fd });
                const j = await r.json();
                btn.disabled = false;
                if (j.ok) { cerrarRostro(); modal.hide(); if (window.Swal) Swal.fire({ icon: 'success', title: j.msg, timer: 1400, showConfirmButton: false }).then(() => location.reload()); else location.reload(); }
                else err(j.error);
            } catch (e) { btn.disabled = false; err('Error de red al guardar.'); }
        };
    })();
</script>
