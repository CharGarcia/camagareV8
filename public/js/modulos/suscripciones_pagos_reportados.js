/**
 * Modal «Pagos reportados por los clientes» (empresa controladora de suscripciones).
 * Se abre desde el ícono de suscripciones del navbar, en cualquier página: el navbar
 * carga este archivo al primer clic y llama SUSC_abrirPagosReportados(base).
 * Datos y acciones: SuscripcionesController::pagosReportadosAjax / aceptarReporteAjax /
 * rechazarReporteAjax / comprobanteReporte.
 *
 * Al registrar el cobro el reporte se cierra solo («Pagado»). «Pago verificado» lo cierra
 * a mano; «Rechazar» devuelve el aviso al cliente y le envía el motivo.
 */
(function () {
    if (window.SUSC_abrirPagosReportados) return;

    const ESTADOS = {
        en_revision:   ['info', 'En revisión'],
        sin_respuesta: ['warning', 'Sin respuesta'],
        rechazado:     ['danger', 'Rechazado'],
        aceptado:      ['success', 'Pago verificado'],
    };
    const esc = function (t) {
        const d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    };
    const dinero = function (v) {
        return v === null || v === undefined || v === '' ? '' : '$ ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    let modalEl = null;
    let body = null;
    let urlBase = '';
    let puedeActualizar = false;

    function crearModal(base) {
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.id = 'modalPagosReportados';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML =
              '<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">'
            +   '<div class="modal-header py-2">'
            +     '<h5 class="modal-title fs-6 fw-bold"><i class="bi bi-inbox me-2 text-primary"></i>Pagos reportados por los clientes</h5>'
            +     '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>'
            +   '</div>'
            +   '<div class="modal-body p-0">'
            +     '<div class="px-3 py-2 small text-muted border-bottom" data-susc-rep-ayuda></div>'
            +     '<div class="table-responsive">'
            +       '<table class="table table-sm table-hover align-middle mb-0" style="font-size:.8rem;">'
            +         '<thead class="table-light"><tr>'
            +           '<th class="text-nowrap">Reportado</th><th>Empresa</th><th>Usuario</th>'
            +           '<th class="text-nowrap">Fecha límite</th><th>Documento</th><th class="text-end">Saldo</th>'
            +           '<th>Mensaje</th><th>Comprobante</th><th>Estado</th><th></th>'
            +         '</tr></thead>'
            +         '<tbody data-susc-rep-body></tbody>'
            +       '</table>'
            +     '</div>'
            +   '</div>'
            +   '<div class="modal-footer py-2">'
            +     '<a class="btn btn-sm btn-outline-primary me-auto" href="' + esc(base) + '/modulos/suscripciones"><i class="bi bi-arrow-repeat me-1"></i>Ir a Suscripciones</a>'
            +     '<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>'
            +   '</div>'
            + '</div></div>';
        document.body.appendChild(modalEl);
        body = modalEl.querySelector('[data-susc-rep-body]');
        body.addEventListener('click', onClick);
    }

    function fila(r) {
        let est = ESTADOS[r.estado] || ['secondary', r.estado];
        if (r.estado === 'aceptado' && r.cierre_automatico) { est = ['success', 'Pagado']; }
        let estado = '<span class="badge bg-' + est[0] + ' bg-opacity-10 text-' + est[0] + ' border border-' + est[0] + '">' + esc(est[1]) + '</span>';
        if (r.estado === 'aceptado') {
            estado += '<div class="text-muted mt-1" style="font-size:.72rem;">'
                   +  (r.cierre_automatico ? 'Cobro registrado' : esc(r.revisado_por || ''))
                   +  ' · ' + esc(r.revisado_at_fmt || '') + '</div>';
        }
        if (r.estado === 'rechazado') {
            estado += '<div class="text-muted mt-1" style="font-size:.72rem;">' + esc(r.revisado_por || '') + ' · ' + esc(r.revisado_at_fmt || '') + '</div>'
                   +  (r.motivo_rechazo ? '<div class="text-danger" style="font-size:.72rem;">' + esc(r.motivo_rechazo) + '</div>' : '');
        }
        if (!r.correo_enviado) {
            estado += '<div class="text-warning" style="font-size:.72rem;" title="El correo de aviso no se pudo enviar"><i class="bi bi-envelope-exclamation"></i> Sin correo</div>';
        }
        const acciones = puedeActualizar && (r.estado === 'en_revision' || r.estado === 'sin_respuesta');
        return '<tr data-id="' + r.id + '">'
            + '<td class="text-nowrap">' + esc(r.created_at_fmt) + '</td>'
            + '<td>' + esc(r.empresa) + '<div class="text-muted" style="font-size:.72rem;">' + esc(r.ruc) + '</div></td>'
            + '<td>' + esc(r.usuario || '') + '<div class="text-muted" style="font-size:.72rem;">' + esc(r.usuario_mail || '') + '</div></td>'
            + '<td class="text-nowrap">' + esc(r.fecha_limite_fmt) + '</td>'
            + '<td class="text-nowrap">' + esc(r.documento || '') + '</td>'
            + '<td class="text-end text-nowrap">' + dinero(r.saldo) + '</td>'
            + '<td style="min-width:200px; white-space:pre-wrap;">' + esc(r.mensaje || '') + '</td>'
            + '<td>' + (r.tiene_archivo
                ? '<a href="' + urlBase + '/comprobanteReporte?id=' + r.id + '" target="_blank" rel="noopener" class="text-decoration-none"><i class="bi bi-paperclip"></i> ' + esc(r.archivo_nombre) + '</a>'
                : '<span class="text-muted">—</span>') + '</td>'
            + '<td>' + estado + '</td>'
            + '<td class="text-end text-nowrap">' + (acciones
                ? '<button type="button" class="btn btn-sm btn-outline-success py-0 me-1" data-aceptar="' + r.id + '" title="El pago es correcto: quitarlo del aviso"><i class="bi bi-check-circle"></i> Pago verificado</button>'
                + '<button type="button" class="btn btn-sm btn-outline-danger py-0" data-rechazar="' + r.id + '"><i class="bi bi-x-circle"></i> Rechazar</button>'
                : '') + '</td>'
            + '</tr>';
    }

    function cargar() {
        body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Cargando…</td></tr>';
        return fetch(urlBase + '/pagosReportadosAjax', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) { throw new Error((d && d.mensaje) || 'No se pudieron cargar los pagos reportados.'); }
                puedeActualizar = !!d.puede_actualizar;
                modalEl.querySelector('[data-susc-rep-ayuda]').innerHTML =
                      'Últimos ' + (parseInt(d.dias_listado, 10) || 60) + ' días. Mientras un reporte está <strong>en revisión</strong> '
                    + 'el cliente no ve el aviso (máximo ' + (parseInt(d.dias_revision, 10) || 3) + ' días). '
                    + 'Si el pago es correcto, registra el cobro como siempre: el reporte pasa solo a <strong>Pagado</strong>. '
                    + 'Si aún no puedes registrarlo, usa <strong>Pago verificado</strong> para quitarlo del aviso sin que el cliente vuelva a ver su vencimiento.';
                const rows = d.rows || [];
                body.innerHTML = rows.length
                    ? rows.map(fila).join('')
                    : '<tr><td colspan="10" class="text-center text-muted py-4">No hay pagos reportados.</td></tr>';
            })
            .catch(function (e) {
                body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">' + esc(e.message) + '</td></tr>';
            });
    }

    // El navbar recalcula su contador (la acción queda en log_sistema e invalida su caché).
    function refrescarNavbar() {
        if (typeof window.CMG_refreshContadores === 'function') {
            try { window.CMG_refreshContadores(true); } catch (e) { /* sin efecto */ }
        }
    }

    function avisar(ok, texto) {
        if (window.Swal) {
            Swal.fire({ icon: ok ? 'success' : 'error', text: texto, target: modalEl });
        }
    }

    function onClick(e) {
        // «Pago verificado»: sin confirmación extra (no avisa nada al cliente).
        const acep = e.target.closest('[data-aceptar]');
        if (acep) {
            if (acep.disabled) return;
            acep.disabled = true;
            const fdA = new FormData();
            fdA.append('id', acep.getAttribute('data-aceptar'));
            fetch(urlBase + '/aceptarReporteAjax', { method: 'POST', body: fdA, headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    avisar(d && d.ok, (d && d.mensaje) || 'No se pudo marcar el pago como verificado.');
                    if (d && d.ok) { cargar(); refrescarNavbar(); } else { acep.disabled = false; }
                })
                .catch(function () { acep.disabled = false; });
            return;
        }

        // Rechazar: el motivo se escribe en una fila debajo (sin Swal: su input no recibe
        // foco dentro de un modal de Bootstrap).
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
        const ta = conf.closest('td').querySelector('textarea');
        const motivo = ta.value.trim();
        if (!motivo) {
            ta.classList.add('is-invalid');
            return;
        }
        conf.disabled = true;
        const fd = new FormData();
        fd.append('id', conf.getAttribute('data-confirmar'));
        fd.append('motivo', motivo);
        fetch(urlBase + '/rechazarReporteAjax', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                avisar(d && d.ok, (d && d.mensaje) || 'No se pudo rechazar el reporte.');
                if (d && d.ok) { cargar(); refrescarNavbar(); } else { conf.disabled = false; }
            })
            .catch(function () { conf.disabled = false; });
    }

    /** @param {string} base URL base del sistema (sin barra final). */
    window.SUSC_abrirPagosReportados = function (base) {
        urlBase = String(base || '') + '/modulos/suscripciones';
        if (!modalEl) { crearModal(String(base || '')); }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        cargar();
    };
})();
