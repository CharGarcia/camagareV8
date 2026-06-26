/**
 * Componente reutilizable: pestaña "Asiento Contable" (vista previa + edición visual).
 *
 * Replica el comportamiento del asiento de la Factura de Venta para reusarlo en otros
 * módulos (Compras, Notas de crédito, etc.) sin duplicar ~250 líneas de lógica.
 *
 * Uso:
 *   const tab = crearAsientoTab({
 *     tbodyId, debeId, haberId, difId, badgeId, countId,  // ids del DOM de la tabla
 *     previewUrl,   // endpoint que devuelve { ok, detalles: [{ id_cuenta_contable, cuenta_codigo,
 *                   //   cuenta_nombre, debe, haber, referencia_detalle, documento_referencia }] }
 *     cuentasUrl,   // endpoint de autocompletado de cuentas (?q=) → { data: [{id,codigo,nombre}] }
 *     colspan = 5
 *   });
 *   tab.cargar(idDocumento);   // carga / vista previa (idDocumento = 0 → mensaje "guarda para generar")
 *   tab.capturar();            // devuelve [] de líneas (por si el módulo quiere enviarlas)
 */
(function () {
    function debounce(fn, ms) {
        let t;
        return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), ms); };
    }

    window.crearAsientoTab = function (cfg) {
        const colspan = cfg.colspan || 5;
        const $ = (id) => document.getElementById(id);
        const filas = () => document.querySelectorAll('#' + cfg.tbodyId + ' .asiento-linea-row');
        let manual = false;

        function recalcular() {
            let td = 0, th = 0;
            filas().forEach(tr => {
                td += parseFloat(tr.querySelector('.input-debe').value) || 0;
                th += parseFloat(tr.querySelector('.input-haber').value) || 0;
            });
            const lblD = $(cfg.debeId), lblH = $(cfg.haberId), lblDif = $(cfg.difId), badge = $(cfg.badgeId), cnt = $(cfg.countId);
            if (lblD) lblD.textContent = td.toFixed(2);
            if (lblH) lblH.textContent = th.toFixed(2);
            const diff = Math.abs(td - th);
            if (lblDif) {
                lblDif.textContent = diff.toFixed(2);
                lblDif.classList.toggle('text-danger', diff >= 0.005);
                lblDif.classList.toggle('text-success', diff < 0.005);
            }
            if (badge) {
                if (diff < 0.005 && (td > 0 || th > 0)) {
                    badge.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2';
                    badge.textContent = 'Cuadrado';
                } else {
                    badge.className = 'badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2';
                    badge.textContent = 'Descuadrado';
                }
            }
            if (cnt) cnt.textContent = filas().length;
        }

        function agregarLinea(idCuenta = '', codigo = '', nombre = '', debe = 0, haber = 0, referencia = '') {
            const tbody = $(cfg.tbodyId);
            if (!tbody) return;
            const ph = tbody.querySelector('td[colspan]');
            if (ph) tbody.innerHTML = '';

            const tr = document.createElement('tr');
            tr.className = 'asiento-linea-row';
            const dv = parseFloat(debe || 0), hv = parseFloat(haber || 0);
            tr.innerHTML = `
                <td class="ps-3 position-relative align-middle p-0">
                    <input type="text" class="form-control border-0 bg-transparent input-cuenta-nombre" placeholder="Escriba código o cuenta contable..." value="${nombre ? (codigo ? codigo + ' - ' + nombre : nombre) : ''}" style="padding:0 4px;height:20px;font-size:0.78rem;">
                    <input type="hidden" class="input-id-cuenta-contable" value="${idCuenta}">
                    <div class="list-group position-absolute w-100 shadow rounded-3 d-none select-cuenta-dropdown" style="z-index:1050;max-height:200px;overflow-y:auto;"></div>
                </td>
                <td class="align-middle p-0"><input type="number" step="0.01" class="form-control text-end border-0 bg-transparent fw-medium input-debe text-primary" placeholder="0.00" value="${dv.toFixed(2) === '0.00' ? '' : dv.toFixed(2)}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
                <td class="align-middle p-0"><input type="number" step="0.01" class="form-control text-end border-0 bg-transparent fw-medium input-haber text-primary" placeholder="0.00" value="${hv.toFixed(2) === '0.00' ? '' : hv.toFixed(2)}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
                <td class="align-middle p-0"><input type="text" class="form-control border-0 bg-transparent input-referencia text-muted fst-italic" placeholder="Referencia" value="${referencia}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
                <td class="text-center p-0 align-middle" style="width:40px;">
                    <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0 btn-del-asiento-linea" title="Eliminar línea"><i class="bi bi-trash3 fs-6"></i></button>
                </td>`;
            tbody.appendChild(tr);

            const inpCuenta = tr.querySelector('.input-cuenta-nombre');
            const hiddenCuenta = tr.querySelector('.input-id-cuenta-contable');
            const dropdown = tr.querySelector('.select-cuenta-dropdown');
            const inpDebe = tr.querySelector('.input-debe');
            const inpHaber = tr.querySelector('.input-haber');
            const inpRef = tr.querySelector('.input-referencia');

            tr.querySelector('.btn-del-asiento-linea').addEventListener('click', () => { tr.remove(); manual = true; recalcular(); });

            inpCuenta.addEventListener('input', debounce(async (e) => {
                const q = e.target.value.trim();
                if (q.length < 2) { dropdown.classList.add('d-none'); return; }
                try {
                    const json = await (await fetch(`${cfg.cuentasUrl}?q=${encodeURIComponent(q)}`)).json();
                    const arr = json.data || json.cuentas || [];
                    dropdown.innerHTML = '';
                    if (arr.length) {
                        arr.forEach(c => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small text-start';
                            btn.innerHTML = `<code class="text-secondary me-2">${c.codigo}</code> <span class="fw-medium">${c.nombre}</span>`;
                            btn.onmousedown = (evt) => {
                                evt.preventDefault();
                                hiddenCuenta.value = c.id;
                                inpCuenta.value = `${c.codigo} - ${c.nombre}`;
                                dropdown.classList.add('d-none');
                                manual = true;
                            };
                            dropdown.appendChild(btn);
                        });
                        dropdown.classList.remove('d-none');
                    } else {
                        dropdown.innerHTML = '<div class="list-group-item small text-muted text-center py-2">Sin cuentas</div>';
                        dropdown.classList.remove('d-none');
                    }
                } catch (err) { console.error('Autocompletar cuentas:', err); }
            }, 250));
            inpCuenta.addEventListener('blur', () => setTimeout(() => dropdown.classList.add('d-none'), 200));
            inpDebe.addEventListener('input', () => { if (parseFloat(inpDebe.value) > 0) inpHaber.value = ''; manual = true; recalcular(); });
            inpHaber.addEventListener('input', () => { if (parseFloat(inpHaber.value) > 0) inpDebe.value = ''; manual = true; recalcular(); });
            inpRef.addEventListener('input', () => { manual = true; });

            recalcular();
        }

        function placeholder(msg, cls) {
            const tbody = $(cfg.tbodyId);
            if (tbody) tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-4 ${cls || 'text-muted'}">${msg}</td></tr>`;
            recalcular();
        }

        function setStatus(html, cls) {
            const el = cfg.statusId ? $(cfg.statusId) : null;
            if (!el) return;
            el.innerHTML = html || '';
            el.className = 'px-1 pt-2 small ' + (cls || 'text-muted');
        }

        async function cargar(id) {
            const tbody = $(cfg.tbodyId);
            if (!tbody) return;
            id = parseInt(id) || 0;
            if (!id) {
                placeholder('<i class="bi bi-info-circle me-1"></i> Guarda el documento para generar el asiento contable.');
                setStatus('');
                return;
            }
            placeholder('<i class="bi bi-hourglass-split me-1"></i> Cargando asiento contable...');
            setStatus('');
            try {
                const json = await (await fetch(`${cfg.previewUrl}?id=${encodeURIComponent(id)}`)).json();
                if (json.ok && json.detalles && json.detalles.length) {
                    tbody.innerHTML = '';
                    json.detalles.forEach(d => agregarLinea(
                        d.id_cuenta_contable,
                        d.cuenta_codigo || d.codigo_cuenta || '',
                        d.cuenta_nombre || d.nombre_cuenta || '',
                        parseFloat(d.debe || 0),
                        parseFloat(d.haber || 0),
                        d.documento_referencia || d.referencia_detalle || d.referencia || ''
                    ));
                    manual = false;
                    recalcular();
                    if (json.es_guardado) {
                        setStatus('<i class="bi bi-check-circle-fill me-1"></i> Asiento contable generado y registrado.', 'text-success');
                    } else {
                        setStatus('<i class="bi bi-info-circle me-1"></i> Vista previa: este asiento se generar&aacute; al guardar el documento.', 'text-muted');
                    }
                } else if (!json.ok) {
                    placeholder('<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (json.error || 'No se pudo generar el asiento.'), 'text-danger');
                    setStatus('');
                } else {
                    placeholder('<i class="bi bi-info-circle me-1"></i> Sin asiento: guarda o actualiza el documento para generarlo.');
                    setStatus('');
                }
            } catch (err) {
                console.error('Error al cargar asiento contable:', err);
                placeholder('<i class="bi bi-exclamation-triangle-fill me-1"></i> Error al cargar el asiento contable.', 'text-danger');
                setStatus('');
            }
        }

        function capturar() {
            const detalles = [];
            filas().forEach(tr => {
                const idCuenta = tr.querySelector('.input-id-cuenta-contable').value;
                if (!idCuenta) return;
                detalles.push({
                    id_cuenta_contable: parseInt(idCuenta),
                    debe: parseFloat(tr.querySelector('.input-debe').value) || 0,
                    haber: parseFloat(tr.querySelector('.input-haber').value) || 0,
                    referencia_detalle: tr.querySelector('.input-referencia').value
                });
            });
            return detalles;
        }

        return {
            cargar,
            capturar,
            recalcular,
            agregarLinea,
            limpiar: () => placeholder('<i class="bi bi-info-circle me-1"></i> Guarda el documento para generar el asiento contable.'),
            isManual: () => manual
        };
    };
})();
