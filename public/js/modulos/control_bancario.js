(function () {
    'use strict';

    const state = {
        forma: 0,
        page: 1,
        sort: 'fecha_asiento',
        dir: 'ASC',
    };

    async function fetchJson(url) {
        const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const contentType = resp.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error(`Respuesta no-JSON (HTTP ${resp.status}) de ${url}`);
        }
        return resp.json();
    }

    function fmtDateInput(v) {
        if (!v) return '';
        return String(v).substring(0, 10);
    }

    function fmtDateDisplay(v) {
        if (!v) return '—';
        const d = new Date(String(v).substring(0, 10) + 'T00:00:00');
        if (isNaN(d.getTime())) return v;
        return d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    window.CB_actualizarFechas = function () {
        const anio = document.getElementById('cb-anio').value;
        const mes = parseInt(document.getElementById('cb-mes').value, 10);
        let fInicio, fFin;
        if (!mes) {
            fInicio = `${anio}-01-01`;
            fFin = `${anio}-12-31`;
        } else {
            const mesStr = String(mes).padStart(2, '0');
            fInicio = `${anio}-${mesStr}-01`;
            const ultimoDia = new Date(anio, mes, 0).getDate();
            fFin = `${anio}-${mesStr}-${String(ultimoDia).padStart(2, '0')}`;
        }
        document.getElementById('cb-fecha-inicio').value = fInicio;
        document.getElementById('cb-fecha-fin').value = fFin;
        window.CB_fetchSearch(1);
    };

    window.CB_cambiarCuenta = function (idForma) {
        state.forma = parseInt(idForma, 10) || 0;
        if (!state.forma) {
            document.getElementById('cb-tbody').innerHTML = '<tr><td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-bank fs-3 d-block mb-2"></i>Seleccione una cuenta bancaria.</td></tr>';
            return;
        }
        cargarSaldos();
        window.CB_fetchSearch(1);
    };

    async function cargarSaldos() {
        if (!state.forma) return;
        try {
            const json = await fetchJson(`${CB_URL_BASE}/getSaldosAjax?forma=${state.forma}`);
            if (json.ok) {
                document.getElementById('cb-stat-saldo-inicial').textContent = '$' + Number(json.data.saldo_inicial).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                document.getElementById('cb-stat-saldo-actual').textContent = '$' + Number(json.data.saldo_actual).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        } catch (e) {
            console.error(e);
        }
    }

    window.CB_fetchSearch = async function (page) {
        state.page = page || 1;
        if (!state.forma) return;

        const buscar = document.getElementById('cb-buscar').value || '';
        const fechaInicio = document.getElementById('cb-fecha-inicio').value;
        const fechaFin = document.getElementById('cb-fecha-fin').value;

        const tbody = document.getElementById('cb-tbody');
        tbody.innerHTML = '<tr><td colspan="12" class="text-center py-5"><span class="spinner-border spinner-border-sm text-primary"></span></td></tr>';

        const params = new URLSearchParams({
            forma: state.forma,
            page: state.page,
            sort: state.sort,
            dir: state.dir,
            b: buscar,
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin,
        });

        try {
            const json = await fetchJson(`${CB_URL_BASE}/searchAjax?${params.toString()}`);
            if (!json.ok) {
                tbody.innerHTML = `<tr><td colspan="12" class="text-center py-5 text-danger">${json.error || 'Error al cargar movimientos.'}</td></tr>`;
                return;
            }
            tbody.innerHTML = json.rows;
            document.getElementById('cb-pagination-container').innerHTML = json.pagination;
            document.getElementById('cb-pagination-info').textContent = json.info;
            document.getElementById('cb-btn-pdf').href = json.pdf_url;
            document.getElementById('cb-btn-excel').href = json.excel_url;
        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr><td colspan="12" class="text-center py-5 text-danger">Error de red o servidor.</td></tr>';
        }
    };

    window.CB_cambiarPaginaAjax = function (page) {
        if (page < 1) return;
        window.CB_fetchSearch(page);
    };

    // ── Modal de clasificación ──────────────────────────────────────────────
    window.CB_toggleCampoCheque = function (tipo) {
        const div = document.getElementById('cbm-div-cheque');
        div.classList.toggle('d-none', tipo !== 'CHEQUE');
        div.classList.toggle('d-flex', tipo === 'CHEQUE');
    };

    window.CB_abrirModalClasificacion = function (btn) {
        const tr = btn.closest('tr');
        const row = JSON.parse(tr.dataset.row);

        document.getElementById('cbm-id-asiento-detalle').value = row.id_asiento_detalle;
        document.getElementById('cbm-id-asiento').value = row.id_asiento;
        document.getElementById('cbm-info-fecha').textContent = fmtDateDisplay(row.fecha_asiento);
        document.getElementById('cbm-info-comprobante').textContent = row.numero_comprobante || 'S/N';
        document.getElementById('cbm-info-glosa').textContent = row.referencia_detalle || row.concepto || '';
        const monto = parseFloat(row.debe) > 0 ? row.debe : row.haber;
        document.getElementById('cbm-info-monto').textContent = '$' + Number(monto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const tipo = row.tipo_transaccion || 'OTRO';
        document.getElementById('cbm-tipo').value = tipo;
        window.CB_toggleCampoCheque(tipo);
        document.getElementById('cbm-direccion').value = row.cheque_direccion || 'RECIBIDO';
        document.getElementById('cbm-numero-cheque').value = row.numero_cheque || '';
        document.getElementById('cbm-fecha-cheque').value = fmtDateInput(row.fecha_cheque);
        document.getElementById('cbm-fecha-banco').value = fmtDateInput(row.fecha_banco);
        document.getElementById('cbm-observacion').value = row.observacion || '';

        document.getElementById('cbm-btn-quitar').classList.toggle('d-none', !row.id_clasificacion);

        new bootstrap.Modal(document.getElementById('modalClasificacionCB')).show();
    };

    window.CB_guardarClasificacion = async function () {
        const payload = {
            id_asiento_detalle: parseInt(document.getElementById('cbm-id-asiento-detalle').value, 10),
            id_forma_pago: state.forma,
            tipo_transaccion: document.getElementById('cbm-tipo').value,
            cheque_direccion: document.getElementById('cbm-tipo').value === 'CHEQUE' ? document.getElementById('cbm-direccion').value : null,
            numero_cheque: document.getElementById('cbm-numero-cheque').value || null,
            fecha_cheque: document.getElementById('cbm-fecha-cheque').value || null,
            fecha_banco: document.getElementById('cbm-fecha-banco').value || null,
            observacion: document.getElementById('cbm-observacion').value || null,
        };

        try {
            const resp = await fetch(`${CB_URL_BASE}/guardarClasificacionAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
            });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo guardar la clasificación.' });
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modalClasificacionCB')).hide();
            window.CB_fetchSearch(state.page);
            cargarSaldos();
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor.' });
        }
    };

    window.CB_quitarClasificacion = async function () {
        const idAsientoDetalle = parseInt(document.getElementById('cbm-id-asiento-detalle').value, 10);
        const result = await Swal.fire({
            icon: 'warning', title: '¿Quitar clasificación?',
            text: 'El movimiento volverá a su clasificación automática por defecto.',
            showCancelButton: true, confirmButtonText: 'Sí, quitar', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`${CB_URL_BASE}/quitarClasificacionAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id_asiento_detalle: idAsientoDetalle }),
            });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo quitar la clasificación.' });
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modalClasificacionCB')).hide();
            window.CB_fetchSearch(state.page);
            cargarSaldos();
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor.' });
        }
    };

    // ── Cheques posfechados ──────────────────────────────────────────────────
    function renderPosfechados(tbodyId, rows, terceroLabel) {
        const tbody = document.getElementById(tbodyId);
        if (!rows || !rows.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No hay cheques posfechados.</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const monto = parseFloat(r.debe) > 0 ? r.debe : r.haber;
            return `<tr>
                <td>${fmtDateDisplay(r.fecha_cheque)}</td>
                <td>${r.numero_cheque || ''}</td>
                <td>${r.forma_pago_nombre || ''}</td>
                <td>${r.nombre_entidad || ''}</td>
                <td class="text-end">$${Number(monto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>`;
        }).join('');
    }

    window.CB_abrirModalPosfechados = async function () {
        new bootstrap.Modal(document.getElementById('modalPosfechadosCB')).show();
        try {
            const [recibidos, emitidos] = await Promise.all([
                fetchJson(`${CB_URL_BASE}/chequesPosfechadosAjax?direccion=RECIBIDO`),
                fetchJson(`${CB_URL_BASE}/chequesPosfechadosAjax?direccion=EMITIDO`),
            ]);
            renderPosfechados('cb-tbody-posf-recibidos', recibidos.ok ? recibidos.data : []);
            renderPosfechados('cb-tbody-posf-emitidos', emitidos.ok ? emitidos.data : []);
        } catch (e) {
            console.error(e);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const formaSelect = document.getElementById('cb-forma');
        state.forma = parseInt(formaSelect.value, 10) || 0;

        if (window.CMG_initSort) {
            window.CMG_initSort('control_bancario', (col, dir) => {
                state.sort = col;
                state.dir = dir;
                window.CB_fetchSearch(1);
            }, { container: '#cb-tabla', col: state.sort, dir: state.dir });
        }

        if (state.forma) {
            cargarSaldos();
            window.CB_fetchSearch(1);
        }
    });
})();
