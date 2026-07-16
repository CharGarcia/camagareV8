(function () {
    'use strict';

    const TRP_URL = window.TRP_URL_BASE;
    const FORMAS = window.TRP_FORMAS_PAGO || [];

    // ¿La serie activa tiene secuenciales configurados? (se actualiza en TRP_syncSecuencial)
    window.TRP_SECUENCIAL_CONFIGURADO = true;

    function trpAvisarSecuencialNoConfigurado(tipo) {
        if (typeof Swal === 'undefined') return;
        const html = (tipo === 'serie')
            ? 'No hay una serie / punto de emisión disponible.<br>Configure los puntos de emisión y sus secuenciales en <strong>Empresa → Secuenciales</strong> antes de registrar el traspaso.'
            : 'No están configurados los secuenciales de <strong>Traspasos</strong> para esta serie.<br>Configúrelos en <strong>Empresa → Secuenciales</strong> antes de registrar el traspaso.';
        Swal.fire({
            icon: 'warning',
            title: 'Secuenciales no configurados',
            html: html,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#f39c12',
            target: document.getElementById('modalTraspaso'),
        });
    }

    window.currentSort = window.currentSort || window.TRP_ORDEN_COL || 'fecha_emision';
    window.currentDir  = window.currentDir  || window.TRP_ORDEN_DIR || 'DESC';

    function fmtMoney(v) {
        return '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Listado / búsqueda / paginación ──────────────────────────────────────

    window.TRP_buscar = function (p = 1) {
        window.TRP_fetchSearch(p);
    };

    window.TRP_fetchSearch = async function (p = 1) {
        const b = document.getElementById('txtBuscarTRP')?.value || '';
        const tbody = document.getElementById('tbodyTraspasos');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><span class="spinner-border text-primary"></span></td></tr>';
        try {
            const res = await (await fetch(`${TRP_URL}/searchAjax?b=${encodeURIComponent(b)}&page=${p}&sort=${window.currentSort}&dir=${window.currentDir}`)).json();
            if (tbody) tbody.innerHTML = res.rows;
            const pag = document.getElementById('paginationContainer');
            if (pag) pag.innerHTML = res.pagination;
            const info = document.getElementById('paginationInfo');
            if (info) info.innerText = res.info;

            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                if (!icon) return;
                if (th.dataset.col === window.currentSort) {
                    icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        } catch (e) {
            console.error(e);
        }
    };

    window.TRP_cambiarPaginaAjax = function (p) {
        window.TRP_fetchSearch(p);
    };

    window.TRP_sort = function (col) {
        if (window.currentSort === col) {
            window.currentDir = (window.currentDir.toUpperCase() === 'ASC') ? 'DESC' : 'ASC';
        } else {
            window.currentSort = col;
            window.currentDir = 'ASC';
        }
        if (navigator.sendBeacon && typeof APP_VISTAS_URL !== 'undefined') {
            const fd = new FormData();
            fd.append('modulo', 'traspasos');
            fd.append('vistaPayload', JSON.stringify({ __ordenCol__: window.currentSort, __ordenDir__: window.currentDir }));
            navigator.sendBeacon(APP_VISTAS_URL, fd);
        }
        window.TRP_fetchSearch(1);
    };

    document.getElementById('txtBuscarTRP')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); window.TRP_buscar(1); }
    });

    // ── Formulario / modal ───────────────────────────────────────────────────

    function resetForm() {
        const f = document.getElementById('formTraspasoModal');
        f.reset();
        document.getElementById('trp-input-id').value = '';
        document.getElementById('trp-input-fecha').value = new Date().toISOString().slice(0, 10);
        document.getElementById('trp-input-secuencial').classList.remove('border-danger');
        window.TRP_SECUENCIAL_CONFIGURADO = true;
        document.getElementById('trp-badge-estado').innerHTML = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Registrado</span>';
        document.getElementById('trp-btn-anular').classList.add('d-none');
        document.getElementById('trp-btn-guardar').classList.remove('d-none');
        document.getElementById('btnPdfTraspaso').classList.add('d-none');
        setCamposHabilitados(true);
        document.getElementById('trp-asiento-contenido').innerHTML = '<p class="text-muted small mb-0">El asiento contable se genera automáticamente al guardar el traspaso.</p>';
        window.TRP_onCambioFormas();
    }

    function setCamposHabilitados(habilitado) {
        ['trp-input-fecha', 'trp-select-punto', 'trp-select-origen', 'trp-select-destino', 'trp-input-monto', 'trp-input-obs']
            .forEach(id => { const el = document.getElementById(id); if (el) el.disabled = !habilitado; });
    }

    window.abrirModalTraspaso = function () {
        resetForm();
        document.getElementById('modalTraspasoTitulo').textContent = 'Nuevo Traspaso de Fondos';
        const selPunto = document.getElementById('trp-select-punto');
        window.TRP_syncSecuencial(selPunto.value);
        const modal = new bootstrap.Modal(document.getElementById('modalTraspaso'));
        modal.show();
    };

    window.abrirModalTraspasoVer = function (id) {
        fetch(`${TRP_URL}/getTraspasoAjax?id=${id}`).then(r => r.json()).then(res => {
            if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
            const t = res.data;
            resetForm();
            document.getElementById('modalTraspasoTitulo').textContent = `Traspaso #${t.numero_traspaso}`;
            document.getElementById('trp-input-id').value = t.id;
            document.getElementById('trp-input-fecha').value = t.fecha_emision;
            if (t.id_punto_emision) document.getElementById('trp-select-punto').value = t.id_punto_emision;
            document.getElementById('trp-input-secuencial').value = String(t.secuencial ?? '').padStart(9, '0');
            document.getElementById('trp-select-origen').value = t.id_forma_origen;
            document.getElementById('trp-select-destino').value = t.id_forma_destino;
            document.getElementById('trp-input-monto').value = parseFloat(t.monto).toFixed(2);
            document.getElementById('trp-input-obs').value = t.observaciones || '';

            const anulado = t.estado === 'anulado';
            document.getElementById('trp-badge-estado').innerHTML = anulado
                ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Anulado</span>'
                : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Registrado</span>';

            setCamposHabilitados(false);
            document.getElementById('trp-btn-guardar').classList.add('d-none');
            const btnAnular = document.getElementById('trp-btn-anular');
            btnAnular.classList.toggle('d-none', anulado);
            btnAnular.dataset.id = t.id;

            const btnPdf = document.getElementById('btnPdfTraspaso');
            btnPdf.classList.remove('d-none');
            btnPdf.dataset.id = t.id;

            window.TRP_onCambioFormas();
            cargarAsientoTraspaso(t.id);

            const modal = new bootstrap.Modal(document.getElementById('modalTraspaso'));
            modal.show();
        }).catch(() => Swal.fire('Error', 'No se pudo cargar el traspaso.', 'error'));
    };

    window.TRP_syncSecuencial = function (idPunto) {
        const s = document.getElementById('trp-select-punto');
        const o = s?.options[s.selectedIndex];
        const inputSec = document.getElementById('trp-input-secuencial');
        if (!o) return;
        document.getElementById('trp-id-establecimiento').value = o.dataset.est || '';
        document.getElementById('trp-txt-establecimiento').value = o.dataset.codEst || '';
        document.getElementById('trp-txt-punto').value = o.dataset.codPunto || '';

        if (!idPunto) {
            window.TRP_SECUENCIAL_CONFIGURADO = false;
            inputSec.value = '000000001';
            inputSec.classList.add('border-danger');
            trpAvisarSecuencialNoConfigurado('serie');
            return;
        }

        fetch(`${TRP_URL}/getSecuencialAjax?id_punto_emision=${idPunto}`).then(r => r.json()).then(res => {
            if (document.getElementById('trp-input-id')?.value) return; // no recalcular en modo ver

            if (res.ok) {
                inputSec.value = String(res.secuencial).padStart(9, '0');
            } else {
                inputSec.value = '000000001';
            }

            // ¿Está configurado el secuencial de Traspasos para esta serie?
            window.TRP_SECUENCIAL_CONFIGURADO = (res.configurado !== false);
            if (res.configurado === false) {
                inputSec.classList.add('border-danger');
                trpAvisarSecuencialNoConfigurado('secuencial');
            } else {
                inputSec.classList.remove('border-danger');
            }
        }).catch(console.error);
    };

    // ── PDF ───────────────────────────────────────────────────────────────────

    window.TRP_abrirPdf = function () {
        const id = document.getElementById('btnPdfTraspaso')?.dataset.id;
        if (!id) return;
        window.open(`${TRP_URL}/pdf?id=${id}`, '_blank');
    };

    // ── Formas de pago: saldo disponible + evitar elegir la misma en ambos lados ──

    window.TRP_onCambioFormas = function () {
        const selOrigen  = document.getElementById('trp-select-origen');
        const selDestino = document.getElementById('trp-select-destino');
        const hintOrigen  = document.getElementById('trp-saldo-origen');
        const hintDestino = document.getElementById('trp-saldo-destino');

        const optOrigen  = selOrigen.options[selOrigen.selectedIndex];
        const optDestino = selDestino.options[selDestino.selectedIndex];

        if (selOrigen.value) {
            const saldo = parseFloat(optOrigen?.dataset.saldo || 0);
            hintOrigen.innerHTML = 'Saldo disponible: <strong class="' + (saldo > 0 ? 'text-success' : 'text-danger') + '">' + fmtMoney(saldo) + '</strong>';
        } else {
            hintOrigen.innerHTML = '&nbsp;';
        }

        if (selDestino.value) {
            const saldo = parseFloat(optDestino?.dataset.saldo || 0);
            hintDestino.innerHTML = 'Saldo actual: <strong>' + fmtMoney(saldo) + '</strong>';
        } else {
            hintDestino.innerHTML = '&nbsp;';
        }

        // Deshabilitar en cada select la opción ya elegida en el otro.
        [...selDestino.options].forEach(o => { o.disabled = (o.value !== '' && o.value === selOrigen.value); });
        [...selOrigen.options].forEach(o => { o.disabled = (o.value !== '' && o.value === selDestino.value); });
    };

    // ── Asiento contable (pestaña) ────────────────────────────────────────────

    function cargarAsientoTraspaso(id) {
        const cont = document.getElementById('trp-asiento-contenido');
        cont.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-1"></span> Cargando asiento…</div>';
        fetch(`${TRP_URL}/getAsientoContableAjax?id=${id}`).then(r => r.json()).then(res => {
            if (!res.ok || !res.asiento || !(res.asiento.detalles || []).length) {
                cont.innerHTML = '<p class="text-muted small mb-0"><i class="bi bi-exclamation-circle me-1"></i>Aún no se ha generado el asiento contable. Verifique que ambas formas de pago tengan cuenta contable configurada.</p>';
                return;
            }
            const a = res.asiento;
            let html = '<table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Cuenta</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead><tbody>';
            (a.detalles || []).forEach(d => {
                const debe = parseFloat(d.debe || 0), haber = parseFloat(d.haber || 0);
                html += `<tr>
                    <td><code class="text-primary">${d.codigo_cuenta || ''}</code> ${d.nombre_cuenta || ''}</td>
                    <td class="text-end">${debe > 0 ? debe.toFixed(2) : ''}</td>
                    <td class="text-end">${haber > 0 ? haber.toFixed(2) : ''}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            cont.innerHTML = html;
        }).catch(() => { cont.innerHTML = '<p class="text-danger small mb-0">Error al cargar el asiento.</p>'; });
    }

    // ── Guardar / anular ──────────────────────────────────────────────────────

    window.TRP_guardar = async function () {
        const form = document.getElementById('formTraspasoModal');
        if (!form.reportValidity()) return;

        // ── Bloqueo: secuenciales no configurados (solo al CREAR un nuevo traspaso) ──
        const _esNuevo = !document.getElementById('trp-input-id').value;
        if (_esNuevo && window.TRP_SECUENCIAL_CONFIGURADO === false) {
            trpAvisarSecuencialNoConfigurado('secuencial');
            return;
        }

        const data = {
            fecha_emision:     document.getElementById('trp-input-fecha').value,
            id_punto_emision:  document.getElementById('trp-select-punto').value,
            id_establecimiento: document.getElementById('trp-id-establecimiento').value,
            establecimiento:   document.getElementById('trp-txt-establecimiento').value,
            punto_emision:     document.getElementById('trp-txt-punto').value,
            secuencial:        document.getElementById('trp-input-secuencial').value,
            id_forma_origen:   document.getElementById('trp-select-origen').value,
            id_forma_destino:  document.getElementById('trp-select-destino').value,
            monto:             parseFloat(document.getElementById('trp-input-monto').value || 0),
            observaciones:     document.getElementById('trp-input-obs').value,
        };

        if (!data.id_forma_origen || !data.id_forma_destino) {
            Swal.fire('Atención', 'Debe seleccionar la forma de origen y la de destino.', 'warning');
            return;
        }
        if (data.id_forma_origen === data.id_forma_destino) {
            Swal.fire('Atención', 'La forma de origen y destino no pueden ser la misma.', 'warning');
            return;
        }

        try {
            const periodoRes = await fetch(`${TRP_URL}/verificarPeriodoAjax?fecha=${encodeURIComponent(data.fecha_emision)}`);
            const periodoJson = await periodoRes.json();
            if (!periodoJson.ok) {
                Swal.fire({ icon: 'error', title: 'Periodo Contable Cerrado', text: periodoJson.mensaje });
                return;
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo verificar el periodo contable. Intente de nuevo.', 'error');
            return;
        }

        const btn = document.getElementById('trp-btn-guardar');
        btn.disabled = true;
        fetch(`${TRP_URL}/guardarAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'data=' + encodeURIComponent(JSON.stringify(data))
        }).then(r => r.json()).then(res => {
            btn.disabled = false;
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalTraspaso'))?.hide();
                window.TRP_fetchSearch(window.TRP_PAGE || 1);
                Swal.fire('Éxito', res.mensaje, 'success');
            } else {
                Swal.fire('Error al guardar', res.mensaje, 'error');
            }
        }).catch(() => {
            btn.disabled = false;
            Swal.fire('Error de Red', 'No se pudo completar la operación en este momento.', 'error');
        });
    };

    window.TRP_anular = function () {
        const id = document.getElementById('trp-btn-anular')?.dataset.id;
        if (!id) return;
        Swal.fire({
            title: '¿Anular este traspaso?',
            text: 'Se anulará el traspaso y su asiento contable asociado. Esta acción no elimina el registro.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then(result => {
            if (!result.isConfirmed) return;
            fetch(`${TRP_URL}/anularAjax`, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=' + id
            }).then(r => r.json()).then(res => {
                if (res.ok) {
                    Swal.fire('Anulado', res.mensaje, 'success').then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalTraspaso'))?.hide();
                        window.TRP_fetchSearch(window.TRP_PAGE || 1);
                    });
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
        });
    };
})();
