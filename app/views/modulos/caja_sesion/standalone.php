<?php
/**
 * Apertura/cierre de caja del Punto de Venta — página STANDALONE (se abre
 * en ventana aparte). No usa el layout principal. Mismo patrón que el
 * visor de Videos de Ayuda (app/views/videosAyuda/visor.php).
 *
 * @var string  $titulo
 * @var string  $rutaModulo
 * @var array   $perm
 * @var ?string $volverA  'mesas' si se llegó desde el tablero de mesas (POS Restaurantes); null = mostrador (comportamiento de siempre)
 */
$base = rtrim(BASE_URL ?? '', '/');
$rutaAjax = $base . '/' . $rutaModulo;
$volverA = $volverA ?? null;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <?php require MVC_APP . "/views/partials/csrf.php"; ?>
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
                    <!-- Arqueo por forma de pago: el sistema pone lo que registró
                         (columna Cobrado) y el cajero confirma lo que realmente
                         entró por cada una (columna Contado). El monto contado del
                         cierre es la SUMA de esa columna, no un campo suelto. -->
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-uppercase text-muted">Arqueo por forma de pago</label>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-1" style="font-size:.82rem;">
                                <thead>
                                    <tr class="text-muted" style="font-size:.72rem;">
                                        <th class="fw-normal">Forma de pago</th>
                                        <th class="fw-normal text-end">Cobrado</th>
                                        <th class="fw-normal text-end" style="width:108px;">Contado</th>
                                    </tr>
                                </thead>
                                <tbody id="cx-formas-pago">
                                    <tr><td colspan="3" class="text-muted text-center py-2">
                                        <span class="spinner-border spinner-border-sm me-1"></span>Cargando…
                                    </td></tr>
                                </tbody>
                                <tfoot>
                                    <tr class="border-top">
                                        <th>Total</th>
                                        <th class="text-end" id="cx-total-cobrado">$0.00</th>
                                        <th class="text-end" id="cx-total-contado">$0.00</th>
                                    </tr>
                                    <tr id="cx-fila-diferencia" class="d-none">
                                        <td class="border-0 pt-0">Diferencia</td>
                                        <td class="border-0 pt-0"></td>
                                        <td class="border-0 pt-0 text-end fw-bold" id="cx-diferencia">$0.00</td>
                                    </tr>
                                    <!-- La propina ya está dentro del total: es un
                                         "de esto, tanto se reparte al personal". -->
                                    <tr class="text-muted">
                                        <td class="border-0 pt-0">Propina</td>
                                        <td class="border-0 pt-0"></td>
                                        <td class="border-0 pt-0 text-end" id="cx-propina">$0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <!-- El fondo no es un cobro, así que no entra en el arqueo;
                             se recuerda aquí porque sí está en el cajón. -->
                        <div class="small text-muted" id="cx-nota-efectivo"></div>
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

            <!--
                Salida sin abrir caja. Esta pantalla es standalone (sin navbar ni
                menú), así que quien entra y decide no operar no tiene por dónde
                irse: queda con el botón "atrás" o cerrando la pestaña. Siempre
                visible, no depende del panel de turno.
            -->
            <div class="pt-3 mt-3 border-top">
                <a href="<?= $base ?>/home/index" class="btn btn-link btn-sm text-muted w-100 text-decoration-none">
                    <i class="bi bi-box-arrow-left me-1"></i>Volver al sistema
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const AJAX = "<?= $rutaAjax ?>";
    const BASE = "<?= $base ?>";
    // 'mesas' si se llegó desde el tablero de mesas — "Continuar" debe volver
    // ahí, no al mostrador (modulos/caja-pos/venta). Ver CajaPosController::index().
    const VOLVER_A = <?= json_encode($volverA) ?>;
    const URL_CONTINUAR = VOLVER_A === 'mesas' ? (BASE + '/modulos/mesas/tablero') : (AJAX + '/venta');
    const $est = document.getElementById('cx-establecimiento');
    const $pto = document.getElementById('cx-punto');
    const $loading = document.getElementById('cx-loading');
    const $panelAbrir = document.getElementById('cx-panel-abrir');
    const $panelTurno = document.getElementById('cx-panel-turno');
    let sesionActual = null;

    function money(v) {
        return '$' + (parseFloat(v || 0)).toFixed(2);
    }

    /** Los nombres de las formas de pago los escribe el usuario: nunca al HTML sin escapar. */
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        ));
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
        const lista = json.data || [];
        $est.innerHTML = '<option value="">Seleccione...</option>';
        lista.forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = e.codigo + ' — ' + e.nombre;
            $est.appendChild(opt);
        });
        // Con un solo establecimiento no hay nada que elegir: se selecciona y se
        // encadena la carga de sus puntos (que a su vez se autoselecciona si es
        // uno solo). La mayoría de locales tiene un establecimiento y un punto;
        // hacerlos elegir dos veces lo obvio solo estorba al abrir el turno.
        if (lista.length === 1) {
            $est.value = lista[0].id;
            await cargarPuntos($est.value);
        }
    }

    async function cargarPuntos(idEstablecimiento) {
        $pto.innerHTML = '<option value="">Cargando...</option>';
        $pto.disabled = true;
        ocultarPaneles();
        if (!idEstablecimiento) { $pto.innerHTML = '<option value="">Seleccione un establecimiento</option>'; return; }

        const res = await fetch(AJAX + '/getPuntosEmisionAjax?id_establecimiento=' + idEstablecimiento, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        const lista = json.data || [];
        $pto.innerHTML = '<option value="">Seleccione...</option>';
        lista.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.codigo_punto + ' — ' + (p.nombre || 'Punto de emisión');
            $pto.appendChild(opt);
        });
        $pto.disabled = false;
        // Un solo punto de emisión: se selecciona y se consulta su turno, así se
        // llega directo a "abrir caja" o a "continuar". Con varios NO se elige
        // por el usuario: en el salón cada quien decide por cuál trabaja.
        if (lista.length === 1) {
            $pto.value = lista[0].id;
            await consultarEstado($pto.value);
        }
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
                document.getElementById('cx-btn-continuar').href = URL_CONTINUAR;
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
        cargarResumenTurno();
    });

    /** Cobrado del turno por forma de pago, para arquear sabiendo qué entró y por dónde. */
    async function cargarResumenTurno() {
        if (!sesionActual) return;
        const $tbody = document.getElementById('cx-formas-pago');

        try {
            const res = await fetch(AJAX + '/resumenTurnoAjax?id=' + encodeURIComponent(sesionActual.id),
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) {
                $tbody.innerHTML = '<tr><td colspan="2" class="text-danger text-center py-2">'
                    + (json.error || 'No se pudo cargar el resumen.') + '</td></tr>';
                return;
            }

            const r = json.resumen;
            $tbody.innerHTML = r.formas_pago.length
                ? r.formas_pago.map(f => {
                    // Los cobros sin Ingreso no se pueden atribuir a una forma:
                    // se marcan para que el cajero sepa qué falta registrar.
                    const sinRegistrar = !f.id_forma_pago;
                    return '<tr>'
                        + '<td>' + escapeHtml(f.nombre)
                        + (sinRegistrar ? ' <i class="bi bi-exclamation-triangle text-warning" title="Cobros sin Ingreso registrado"></i>' : '')
                        + ' <span class="text-muted">(' + f.documentos + ')</span></td>'
                        + '<td class="text-end">' + money(f.total) + '</td>'
                        + '<td class="text-end p-1">'
                        + '<input type="number" class="form-control form-control-sm text-end cx-contado"'
                        + ' step="0.01" min="0" value="' + Number(f.total).toFixed(2) + '"'
                        + ' data-id="' + f.id_forma_pago + '"'
                        + ' data-nombre="' + escapeHtml(f.nombre) + '"'
                        + ' data-cobrado="' + Number(f.total) + '">'
                        + '</td></tr>';
                }).join('')
                : '<tr><td colspan="3" class="text-muted text-center py-2">Sin cobros en este turno.</td></tr>';

            document.getElementById('cx-total-cobrado').textContent = money(r.total_cobrado);
            document.getElementById('cx-propina').textContent       = money(r.propina);

            // El fondo inicial no es un cobro: no entra en el arqueo, pero sí
            // está en el cajón, así que se recuerda para no confundir al contar.
            const $nota = document.getElementById('cx-nota-efectivo');
            $nota.textContent = r.fondo_inicial > 0
                ? 'Fondo inicial: ' + money(r.fondo_inicial) + '. No entra en el arqueo; en el cajón debe estar aparte del efectivo cobrado.'
                : '';

            $tbody.querySelectorAll('.cx-contado').forEach(i => i.addEventListener('input', recalcularArqueo));
            recalcularArqueo();
        } catch (e) {
            $tbody.innerHTML = '<tr><td colspan="3" class="text-danger text-center py-2">Error de comunicación.</td></tr>';
        }
    }

    /** Total contado = suma de lo que el cajero confirma en cada forma de pago. */
    function recalcularArqueo() {
        let contado = 0, cobrado = 0;
        document.querySelectorAll('.cx-contado').forEach(i => {
            contado += parseFloat(i.value) || 0;
            cobrado += parseFloat(i.dataset.cobrado) || 0;
            // Cada fila se marca sola cuando no coincide, para no tener que
            // buscar dónde está el descuadre.
            i.classList.toggle('border-danger',
                Math.abs((parseFloat(i.value) || 0) - (parseFloat(i.dataset.cobrado) || 0)) >= 0.01);
        });

        const dif = Math.round((contado - cobrado) * 100) / 100;
        document.getElementById('cx-total-contado').textContent = money(contado);

        const $fila = document.getElementById('cx-fila-diferencia');
        const $dif  = document.getElementById('cx-diferencia');
        $fila.classList.toggle('d-none', Math.abs(dif) < 0.01);
        $dif.textContent = money(dif);
        $dif.className = 'border-0 pt-0 text-end fw-bold ' + (dif < 0 ? 'text-danger' : 'text-success');
    }

    document.getElementById('cx-btn-cancelar-cierre').addEventListener('click', () => {
        document.getElementById('cx-cierre-form').classList.add('d-none');
        document.getElementById('cx-turno-acciones').classList.remove('d-none');
    });

    document.getElementById('cx-btn-confirmar-cierre').addEventListener('click', async () => {
        if (!sesionActual) return;
        const observaciones = document.getElementById('cx-observaciones').value;

        // Lo contado por forma de pago; el servidor lo suma para el monto
        // contado del cierre (no se le manda el total ya sumado).
        const contadas = Array.from(document.querySelectorAll('.cx-contado')).map(i => ({
            id_forma_pago: parseInt(i.dataset.id, 10) || 0,
            nombre: i.dataset.nombre || '',
            contado: parseFloat(i.value) || 0,
        }));

        const fd = new FormData();
        fd.append('id', sesionActual.id);
        fd.append('formas_contadas', JSON.stringify(contadas));
        fd.append('observaciones_cierre', observaciones);

        const res = await fetch(AJAX + '/cerrarAjax', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!json.ok) { swalError(json.error || 'No se pudo cerrar la caja.'); return; }

        const dif = parseFloat(json.sesion.diferencia || 0);
        if (Math.abs(dif) < 0.01) {
            swalToast('success', 'Caja cerrada correctamente. El detalle se envió por correo.');
        } else {
            swalWarning('Caja cerrada con una diferencia de <b>' + money(dif) + '</b> respecto a lo esperado.');
        }
        // El correo del cierre no bloquea nada: si no salió, se avisa aparte
        // para que alguien lo mire, con la caja ya cerrada.
        if (json.aviso_correo) {
            setTimeout(() => swalWarning('La caja se cerró correctamente, pero ' + escapeHtml(json.aviso_correo)), 400);
        }
        consultarEstado($pto.value);
    });

    cargarEstablecimientos();
})();
</script>
</body>
</html>
