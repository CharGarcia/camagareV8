/* Módulo: Reasignar establecimiento. CSRF lo maneja el layout (csrf.js envuelve fetch). */
(function () {
    const RUTA = window.REASIGNAR_RUTA;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmtMoney = (n) => (Number(n) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    let filas = [];

    function contarSeleccion() {
        const sel = document.querySelectorAll('.re-chk:checked').length;
        $('reContadorSel').textContent = sel + ' seleccionados';
        $('reBtnReasignar').disabled = sel === 0 || !$('reEstDestino').value;
    }

    function render() {
        const tb = $('reTbody');
        tb.innerHTML = '';
        if (!filas.length) {
            tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin resultados para el filtro.</td></tr>';
            contarSeleccion();
            return;
        }
        filas.forEach((r, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="checkbox" class="re-chk" data-i="' + i + '"></td>' +
                '<td class="small">' + esc(r.fecha) + '</td>' +
                '<td class="small">' + esc(r.tipo_doc) + '</td>' +
                '<td class="small">' + esc(r.numero) + '</td>' +
                '<td class="small">' + (r.ident ? '<span class="text-muted">' + esc(r.ident) + '</span> ' : '') + esc(r.contraparte) + '</td>' +
                '<td class="small text-end">' + fmtMoney(r.total) + '</td>' +
                '<td class="small"><span class="badge bg-secondary bg-opacity-25 text-dark">' + esc(r.est_codigo) + '</span> ' + esc(r.est_nombre) + '</td>';
            tb.appendChild(tr);
        });
        contarSeleccion();
    }

    async function buscar() {
        const btn = $('reBtnBuscar');
        const prev = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Buscando…';
        $('reResumen').textContent = '';
        try {
            const p = new URLSearchParams({
                tipo: $('reTipo').value,
                desde: $('reDesde').value,
                hasta: $('reHasta').value,
                id_est_origen: $('reEstOrigen').value,
                buscar: $('reBuscar').value
            });
            const res = await fetch(RUTA + '/listarAjax?' + p.toString()).then(r => r.json());
            if (!res.ok) { $('reResumen').innerHTML = '<span class="text-danger">' + esc(res.mensaje) + '</span>'; filas = []; render(); return; }
            filas = res.rows || [];
            let resumen = 'Mostrando ' + filas.length + ' de ' + res.total + '.';
            if (res.resumen && res.resumen.length) {
                resumen += ' Por establecimiento actual: ' + res.resumen.map(x => (x.est_codigo || '—') + '=' + x.n).join(', ') + '.';
            }
            if (res.total > filas.length) resumen += ' (Se listan hasta 200; afina el filtro para reasignar por bloques).';
            $('reResumen').textContent = resumen;
            $('reChkAll').checked = false;
            render();
        } catch (e) {
            $('reResumen').innerHTML = '<span class="text-danger">Error al consultar.</span>';
        } finally {
            btn.disabled = false; btn.innerHTML = prev;
        }
    }

    async function reasignar() {
        const seleccion = Array.from(document.querySelectorAll('.re-chk:checked')).map(c => filas[+c.dataset.i]);
        const destino = $('reEstDestino').value;
        const destinoTxt = $('reEstDestino').options[$('reEstDestino').selectedIndex].text;
        if (!seleccion.length || !destino) return;
        if (!confirm('¿Reasignar ' + seleccion.length + ' documento(s) al establecimiento «' + destinoTxt + '»?\n\nNo cambia el número del documento. Se puede revertir volviendo a reasignar.')) return;

        const btn = $('reBtnReasignar');
        const prev = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Reasignando…';
        try {
            const body = new FormData();
            body.append('tipo', $('reTipo').value);
            body.append('id_establecimiento_destino', destino);
            seleccion.forEach(r => body.append('ids[]', r.id));
            const res = await fetch(RUTA + '/reasignarAjax', { method: 'POST', body }).then(r => r.json());
            if (!res.ok) { alert(res.mensaje || 'No se pudo reasignar.'); return; }
            alert(res.mensaje);
            await buscar(); // refrescar
        } catch (e) {
            alert('Error durante la reasignación.');
        } finally {
            btn.disabled = false; btn.innerHTML = prev;
        }
    }

    // ── Año / Mes tomados de los datos reales del tipo elegido ──
    const MESES = { '01': 'Enero', '02': 'Febrero', '03': 'Marzo', '04': 'Abril', '05': 'Mayo', '06': 'Junio', '07': 'Julio', '08': 'Agosto', '09': 'Septiembre', '10': 'Octubre', '11': 'Noviembre', '12': 'Diciembre' };
    let periodos = {}; // { '2022': ['01','03', ...], ... }

    async function cargarPeriodos() {
        periodos = {};
        $('reAnio').innerHTML = '<option value="">Todos</option>';
        $('reMes').innerHTML = '<option value="">Todos</option>';
        try {
            const res = await fetch(RUTA + '/periodosAjax?tipo=' + encodeURIComponent($('reTipo').value)).then(r => r.json());
            if (!res.ok) return;
            (res.data || []).forEach(p => { (periodos[p.anio] = periodos[p.anio] || []).push(p.mes); });
            Object.keys(periodos).sort((a, b) => b - a).forEach(anio => {
                $('reAnio').insertAdjacentHTML('beforeend', '<option value="' + anio + '">' + anio + '</option>');
            });
        } catch (e) { /* sin años: se queda en Todos */ }
    }

    function poblarMeses() {
        const anio = $('reAnio').value;
        const meses = anio ? (periodos[anio] || []) : [];
        $('reMes').innerHTML = '<option value="">Todos</option>' +
            meses.map(m => '<option value="' + m + '">' + (MESES[m] || m) + '</option>').join('');
    }

    // Año + Mes → autocompletan Desde/Hasta.
    function aplicarPeriodo() {
        const anio = $('reAnio').value, mes = $('reMes').value;
        if (!anio) { $('reDesde').value = ''; $('reHasta').value = ''; return; }
        if (mes) {
            const ultimo = new Date(Number(anio), Number(mes), 0).getDate(); // último día del mes (feb incluido)
            $('reDesde').value = anio + '-' + mes + '-01';
            $('reHasta').value = anio + '-' + mes + '-' + String(ultimo).padStart(2, '0');
        } else {
            $('reDesde').value = anio + '-01-01';
            $('reHasta').value = anio + '-12-31';
        }
    }

    $('reAnio').addEventListener('change', () => { poblarMeses(); aplicarPeriodo(); });
    $('reMes').addEventListener('change', aplicarPeriodo);

    $('reBtnBuscar').addEventListener('click', buscar);
    $('reBuscar').addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });
    $('reTipo').addEventListener('change', () => { filas = []; render(); $('reResumen').textContent = ''; $('reDesde').value = ''; $('reHasta').value = ''; cargarPeriodos(); });
    $('reEstDestino').addEventListener('change', contarSeleccion);
    $('reChkAll').addEventListener('change', function () { document.querySelectorAll('.re-chk').forEach(c => c.checked = this.checked); contarSeleccion(); });
    $('reTbody').addEventListener('change', e => { if (e.target.classList.contains('re-chk')) contarSeleccion(); });
    $('reSelTodos').addEventListener('click', e => { e.preventDefault(); document.querySelectorAll('.re-chk').forEach(c => c.checked = true); $('reChkAll').checked = true; contarSeleccion(); });
    $('reSelNinguno').addEventListener('click', e => { e.preventDefault(); document.querySelectorAll('.re-chk').forEach(c => c.checked = false); $('reChkAll').checked = false; contarSeleccion(); });
    $('reBtnReasignar').addEventListener('click', reasignar);

    cargarPeriodos(); // poblar Año/Mes desde los datos del tipo inicial
})();
