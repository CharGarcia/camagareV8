<?php
/**
 * Apertura/cierre de caja del Punto de Venta — página STANDALONE (se abre
 * en ventana aparte). No usa el layout principal. Mismo patrón que el
 * visor de Videos de Ayuda (app/views/videosAyuda/visor.php).
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var array  $perm
 */
$base = rtrim(BASE_URL ?? '', '/');
$rutaAjax = $base . '/' . $rutaModulo;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body {
            background: #f4f6f9;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }
        .cx-wrap { width: 100%; max-width: 460px; }
        .cx-brand {
            display: flex; align-items: center; gap: 10px; margin-bottom: 18px; color: #495057;
        }
        .cx-brand i { font-size: 1.4rem; color: #0d6efd; }
        .cx-card { border: none; border-radius: 14px; box-shadow: 0 10px 30px -12px rgba(20,26,36,.25); }
        .cx-card .card-body { padding: 28px 26px; }
        .cx-live { width: 8px; height: 8px; border-radius: 50%; background: #198754; display: inline-block; box-shadow: 0 0 0 3px rgba(25,135,84,.18); }
        .cx-stat { text-align: right; }
        .cx-stat small { display: block; text-transform: uppercase; letter-spacing: .04em; font-size: .68rem; color: #8a94a6; }
        .cx-stat b { font-variant-numeric: tabular-nums; }
        #cx-loading { min-height: 40px; }
    </style>
</head>
<body>
<div class="cx-wrap">
    <div class="cx-brand">
        <i class="bi bi-cash-coin"></i>
        <div>
            <div class="fw-semibold lh-1"><?= htmlspecialchars($titulo) ?></div>
            <small class="text-muted">Selecciona el punto de emisión para abrir o cerrar el turno</small>
        </div>
    </div>

    <div class="card cx-card">
        <div class="card-body">

            <div id="cx-selectores">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-uppercase text-muted">Establecimiento</label>
                    <select id="cx-establecimiento" class="form-select"></select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold text-uppercase text-muted">Punto de emisión</label>
                    <select id="cx-punto" class="form-select" disabled></select>
                </div>
            </div>

            <div id="cx-loading" class="text-center text-muted py-3 d-none">
                <span class="spinner-border spinner-border-sm"></span> Consultando turno...
            </div>

            <!-- Estado: SIN sesión abierta -->
            <div id="cx-panel-abrir" class="d-none pt-2 border-top mt-3">
                <label class="form-label small fw-semibold text-uppercase text-muted">Fondo inicial (efectivo)</label>
                <div class="input-group mb-3">
                    <span class="input-group-text">$</span>
                    <input type="number" id="cx-fondo-inicial" class="form-control" step="0.01" min="0" value="0.00">
                </div>
                <button id="cx-btn-abrir" class="btn btn-primary w-100" type="button">
                    <i class="bi bi-unlock-fill me-1"></i>Abrir turno y continuar
                </button>
            </div>

            <!-- Estado: CON sesión abierta -->
            <div id="cx-panel-turno" class="d-none pt-2 border-top mt-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="cx-live"></span>
                        <div>
                            <div class="fw-semibold" id="cx-turno-cajero">—</div>
                            <small class="text-muted" id="cx-turno-desde">—</small>
                        </div>
                    </div>
                    <div class="cx-stat">
                        <small>Fondo inicial</small>
                        <b id="cx-turno-fondo">$0.00</b>
                    </div>
                </div>

                <div id="cx-cierre-form" class="d-none">
                    <label class="form-label small fw-semibold text-uppercase text-muted">Monto contado (arqueo)</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text">$</span>
                        <input type="number" id="cx-monto-contado" class="form-control" step="0.01" min="0" value="0.00">
                    </div>
                    <textarea id="cx-observaciones" class="form-control mb-3" rows="2" placeholder="Observaciones del cierre (opcional)"></textarea>
                    <div class="d-flex gap-2">
                        <button id="cx-btn-cancelar-cierre" class="btn btn-outline-secondary flex-fill" type="button">Cancelar</button>
                        <button id="cx-btn-confirmar-cierre" class="btn btn-danger flex-fill" type="button">Confirmar cierre</button>
                    </div>
                </div>

                <div id="cx-turno-acciones">
                    <a href="#" id="cx-btn-continuar" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-arrow-right-circle-fill me-1"></i>Continuar al Punto de Venta
                    </a>
                    <button class="btn btn-link btn-sm text-danger w-100 text-decoration-none" type="button" id="cx-btn-cerrar">
                        <i class="bi bi-lock-fill me-1"></i>Cerrar caja (arqueo)
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const AJAX = "<?= $rutaAjax ?>";
    const $est = document.getElementById('cx-establecimiento');
    const $pto = document.getElementById('cx-punto');
    const $loading = document.getElementById('cx-loading');
    const $panelAbrir = document.getElementById('cx-panel-abrir');
    const $panelTurno = document.getElementById('cx-panel-turno');
    let sesionActual = null;

    function money(v) {
        return '$' + (parseFloat(v || 0)).toFixed(2);
    }

    function swalToast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2800, timerProgressBar: true });
    }
    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function swalWarning(html) {
        Swal.fire({ icon: 'warning', title: 'Atención', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }

    function ocultarPaneles() {
        $panelAbrir.classList.add('d-none');
        $panelTurno.classList.add('d-none');
        document.getElementById('cx-cierre-form').classList.add('d-none');
        document.getElementById('cx-turno-acciones').classList.remove('d-none');
    }

    async function cargarEstablecimientos() {
        const res = await fetch(AJAX + '/getEstablecimientosAjax', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        $est.innerHTML = '<option value="">Seleccione...</option>';
        (json.data || []).forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = e.codigo + ' — ' + e.nombre;
            $est.appendChild(opt);
        });
    }

    async function cargarPuntos(idEstablecimiento) {
        $pto.innerHTML = '<option value="">Cargando...</option>';
        $pto.disabled = true;
        ocultarPaneles();
        if (!idEstablecimiento) { $pto.innerHTML = '<option value="">Seleccione un establecimiento</option>'; return; }

        const res = await fetch(AJAX + '/getPuntosEmisionAjax?id_establecimiento=' + idEstablecimiento, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        $pto.innerHTML = '<option value="">Seleccione...</option>';
        (json.data || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.codigo_punto + ' — ' + (p.nombre || 'Punto de emisión');
            $pto.appendChild(opt);
        });
        $pto.disabled = false;
    }

    async function consultarEstado(idPuntoEmision) {
        ocultarPaneles();
        if (!idPuntoEmision) return;

        $loading.classList.remove('d-none');
        try {
            const res = await fetch(AJAX + '/estadoActualAjax?id_punto_emision=' + idPuntoEmision, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            $loading.classList.add('d-none');
            if (!json.ok) { swalError(json.error || 'No se pudo consultar el turno.'); return; }

            sesionActual = json.sesion;
            if (sesionActual) {
                document.getElementById('cx-turno-cajero').textContent = 'Cajero: ' + (sesionActual.cajero_nombre || '—');
                document.getElementById('cx-turno-desde').textContent = 'Abierta: ' + sesionActual.fecha_apertura;
                document.getElementById('cx-turno-fondo').textContent = money(sesionActual.fondo_inicial);
                document.getElementById('cx-btn-continuar').href = AJAX + '/venta';
                $panelTurno.classList.remove('d-none');
            } else {
                $panelAbrir.classList.remove('d-none');
            }
        } catch (e) {
            $loading.classList.add('d-none');
            swalError('Error de conexión al consultar el turno.');
        }
    }

    $est.addEventListener('change', () => cargarPuntos($est.value));
    $pto.addEventListener('change', () => consultarEstado($pto.value));

    document.getElementById('cx-btn-abrir').addEventListener('click', async () => {
        const idPunto = $pto.value;
        const fondo = document.getElementById('cx-fondo-inicial').value;
        if (!idPunto) { swalWarning('Seleccione un punto de emisión.'); return; }

        const fd = new FormData();
        fd.append('id_punto_emision', idPunto);
        fd.append('fondo_inicial', fondo);

        const res = await fetch(AJAX + '/abrirAjax', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!json.ok) { swalError(json.error || 'No se pudo abrir la caja.'); return; }

        swalToast('success', 'Caja abierta correctamente.');
        consultarEstado(idPunto);
    });

    document.getElementById('cx-btn-cerrar').addEventListener('click', () => {
        document.getElementById('cx-cierre-form').classList.remove('d-none');
        document.getElementById('cx-turno-acciones').classList.add('d-none');
    });

    document.getElementById('cx-btn-cancelar-cierre').addEventListener('click', () => {
        document.getElementById('cx-cierre-form').classList.add('d-none');
        document.getElementById('cx-turno-acciones').classList.remove('d-none');
    });

    document.getElementById('cx-btn-confirmar-cierre').addEventListener('click', async () => {
        if (!sesionActual) return;
        const montoContado = document.getElementById('cx-monto-contado').value;
        const observaciones = document.getElementById('cx-observaciones').value;

        const fd = new FormData();
        fd.append('id', sesionActual.id);
        fd.append('monto_contado', montoContado);
        fd.append('observaciones_cierre', observaciones);

        const res = await fetch(AJAX + '/cerrarAjax', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!json.ok) { swalError(json.error || 'No se pudo cerrar la caja.'); return; }

        const dif = parseFloat(json.sesion.diferencia || 0);
        if (Math.abs(dif) < 0.01) {
            swalToast('success', 'Caja cerrada correctamente.');
        } else {
            swalWarning('Caja cerrada con una diferencia de <b>' + money(dif) + '</b> respecto a lo esperado.');
        }
        consultarEstado($pto.value);
    });

    cargarEstablecimientos();
})();
</script>
</body>
</html>
