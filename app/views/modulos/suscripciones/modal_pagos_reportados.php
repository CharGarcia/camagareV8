<?php
/**
 * Modal «Pagos reportados»: pagos de suscripción que los clientes informaron desde el
 * aviso de vigencia (mensaje + comprobante). Datos: SuscripcionesController::pagosReportadosAjax.
 * El cobro se registra como siempre; «Rechazar» (permiso actualizar) devuelve el aviso al
 * cliente y le envía el motivo por correo.
 *
 * @var string $urlBase
 * @var array  $perm
 */
$puedeRechazar = !empty($perm['actualizar']);
?>
<div class="modal fade" id="modalPagosReportados" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-inbox me-2 text-primary"></i>Pagos reportados por los clientes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-3 py-2 small text-muted border-bottom">
                    Últimos <?= (int) \App\Services\SuscripcionReportePagoService::DIAS_LISTADO ?> días.
                    Mientras un reporte está <strong>en revisión</strong> el cliente no ve el aviso
                    (máximo <?= (int) \App\Services\SuscripcionReportePagoService::DIAS_REVISION ?> días).
                    Si el pago es correcto, registra el cobro como siempre y el aviso desaparece solo.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">Reportado</th>
                                <th>Empresa</th>
                                <th>Usuario</th>
                                <th class="text-nowrap">Fecha límite</th>
                                <th>Documento</th>
                                <th class="text-end">Saldo</th>
                                <th>Mensaje</th>
                                <th>Comprobante</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="susc-reportes-body">
                            <tr><td colspan="10" class="text-center text-muted py-4">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URL_BASE = <?= json_encode($urlBase) ?>;
    const PUEDE_RECHAZAR = <?= $puedeRechazar ? 'true' : 'false' ?>;
    const ESTADOS = {
        en_revision:   ['info', 'En revisión'],
        sin_respuesta: ['warning', 'Sin respuesta'],
        rechazado:     ['danger', 'Rechazado'],
    };
    const esc = function (t) {
        const d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    };
    const dinero = function (v) {
        return v === null || v === undefined || v === '' ? '' : '$ ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const body = document.getElementById('susc-reportes-body');

    function fila(r) {
        const est = ESTADOS[r.estado] || ['secondary', r.estado];
        let estado = '<span class="badge bg-' + est[0] + ' bg-opacity-10 text-' + est[0] + ' border border-' + est[0] + '">' + esc(est[1]) + '</span>';
        if (r.estado === 'rechazado') {
            estado += '<div class="text-muted mt-1" style="font-size:.72rem;">' + esc(r.revisado_por || '') + ' · ' + esc(r.revisado_at_fmt || '') + '</div>'
                   +  (r.motivo_rechazo ? '<div class="text-danger" style="font-size:.72rem;">' + esc(r.motivo_rechazo) + '</div>' : '');
        }
        if (!r.correo_enviado) {
            estado += '<div class="text-warning" style="font-size:.72rem;" title="El correo de aviso no se pudo enviar"><i class="bi bi-envelope-exclamation"></i> Sin correo</div>';
        }
        const puedeRechazar = PUEDE_RECHAZAR && (r.estado === 'en_revision' || r.estado === 'sin_respuesta');
        return '<tr data-id="' + r.id + '">'
            + '<td class="text-nowrap">' + esc(r.created_at_fmt) + '</td>'
            + '<td>' + esc(r.empresa) + '<div class="text-muted" style="font-size:.72rem;">' + esc(r.ruc) + '</div></td>'
            + '<td>' + esc(r.usuario || '') + '<div class="text-muted" style="font-size:.72rem;">' + esc(r.usuario_mail || '') + '</div></td>'
            + '<td class="text-nowrap">' + esc(r.fecha_limite_fmt) + '</td>'
            + '<td class="text-nowrap">' + esc(r.documento || '') + '</td>'
            + '<td class="text-end text-nowrap">' + dinero(r.saldo) + '</td>'
            + '<td style="min-width:200px; white-space:pre-wrap;">' + esc(r.mensaje || '') + '</td>'
            + '<td>' + (r.tiene_archivo
                ? '<a href="' + URL_BASE + '/comprobanteReporte?id=' + r.id + '" target="_blank" rel="noopener" class="text-decoration-none"><i class="bi bi-paperclip"></i> ' + esc(r.archivo_nombre) + '</a>'
                : '<span class="text-muted">—</span>') + '</td>'
            + '<td>' + estado + '</td>'
            + '<td class="text-end">' + (puedeRechazar
                ? '<button type="button" class="btn btn-sm btn-outline-danger py-0" data-rechazar="' + r.id + '"><i class="bi bi-x-circle"></i> Rechazar</button>'
                : '') + '</td>'
            + '</tr>';
    }

    function cargar() {
        body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Cargando…</td></tr>';
        return fetch(URL_BASE + '/pagosReportadosAjax', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) { throw new Error((d && d.mensaje) || 'No se pudieron cargar los pagos reportados.'); }
                const rows = d.rows || [];
                body.innerHTML = rows.length
                    ? rows.map(fila).join('')
                    : '<tr><td colspan="10" class="text-center text-muted py-4">No hay pagos reportados.</td></tr>';
                const enRev = rows.filter(function (r) { return r.estado === 'en_revision'; }).length;
                const badge = document.getElementById('susc-reportes-badge');
                if (badge) { badge.textContent = enRev; badge.classList.toggle('d-none', enRev === 0); }
            })
            .catch(function (e) {
                body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">' + esc(e.message) + '</td></tr>';
            });
    }

    // Rechazar: el motivo se escribe en una fila debajo (sin Swal: su input no recibe
    // foco dentro de un modal de Bootstrap).
    body.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-rechazar]');
        if (btn) {
            const tr = btn.closest('tr');
            if (tr.nextElementSibling && tr.nextElementSibling.classList.contains('susc-rechazo-row')) {
                tr.nextElementSibling.remove();
                return;
            }
            const id = btn.getAttribute('data-rechazar');
            const extra = document.createElement('tr');
            extra.className = 'susc-rechazo-row table-danger';
            extra.innerHTML = '<td colspan="10">'
                + '<div class="d-flex gap-2 align-items-start flex-wrap">'
                +   '<textarea class="form-control form-control-sm" rows="2" maxlength="2000" style="max-width:600px; font-size:.8rem;" placeholder="Motivo del rechazo (se le envía al cliente por correo)"></textarea>'
                +   '<button type="button" class="btn btn-sm btn-danger" data-confirmar="' + id + '"><i class="bi bi-x-circle me-1"></i>Confirmar rechazo</button>'
                + '</div></td>';
            tr.after(extra);
            extra.querySelector('textarea').focus();
            return;
        }
        const conf = e.target.closest('[data-confirmar]');
        if (!conf || conf.disabled) return;
        const motivo = conf.closest('td').querySelector('textarea').value.trim();
        if (!motivo) {
            conf.closest('td').querySelector('textarea').classList.add('is-invalid');
            return;
        }
        conf.disabled = true;
        const fd = new FormData();
        fd.append('id', conf.getAttribute('data-confirmar'));
        fd.append('motivo', motivo);
        fetch(URL_BASE + '/rechazarReporteAjax', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (window.Swal) {
                    Swal.fire({ icon: d && d.ok ? 'success' : 'error', text: (d && d.mensaje) || 'No se pudo rechazar el reporte.', target: document.getElementById('modalPagosReportados') });
                }
                if (d && d.ok) { cargar(); } else { conf.disabled = false; }
            })
            .catch(function () { conf.disabled = false; });
    });

    window.SUSC_abrirPagosReportados = function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPagosReportados')).show();
        cargar();
    };

    // Desde el aviso del navbar (?reportados=1): abrir directamente.
    if (new URLSearchParams(window.location.search).get('reportados') === '1') {
        document.addEventListener('DOMContentLoaded', function () { window.SUSC_abrirPagosReportados(); });
        if (document.readyState !== 'loading') { window.SUSC_abrirPagosReportados(); }
    }
})();
</script>
