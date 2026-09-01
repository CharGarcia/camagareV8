/**
 * Componente reutilizable: pestaña "Asiento Contable" de los modales de documento.
 *
 * Una sola pieza para Compras, Notas de crédito, Ingresos, Egresos, etc. — muestra el asiento
 * del documento, permite corregirlo a mano y lo guarda contra el módulo de Asientos Contables,
 * que es quien valida el cuadre contra el documento, los permisos y la auditoría.
 *
 * Reglas que implementa (ver app/helpers/AsientoPestana.php):
 *  - La pestaña solo existe si el usuario ve `modulos/asientos_contables` (lo decide la vista).
 *  - Solo se edita si además puede actualizar ese módulo Y el asiento ya está generado: sobre una
 *    vista previa no hay nada que guardar todavía.
 *  - El asiento debe cuadrar en base al documento: se avisa en vivo con el mismo criterio que
 *    aplica el servidor (línea de cartera si el documento tiene cuenta configurada; total Debe
 *    si no la tiene) y el guardado con diferencia exige confirmación.
 *
 * Uso (ids: los que genera app/views/partials/asiento_tab.php con el mismo prefijo):
 *   const tab = crearAsientoTab({
 *     prefijo: 'mc',                                     // → mc-asiento-tbody, mc-asiento-save, …
 *     previewUrl: `${CMG_urlBase}/getAsientoSugeridoAjax`, // { ok, detalles, es_guardado, asiento, cuadre_documento }
 *     cuentasUrl: `${BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`,
 *     asientosUrl: `${BASE_URL}/modulos/asientos-contables`,
 *     onGuardado: () => { … }                            // opcional: refrescar el documento
 *   });
 *   tab.cargar(idDocumento);   // idDocumento = 0 → "guarda el documento para generar el asiento"
 *
 * Compatibilidad: los módulos que aún pasan ids sueltos (tbodyId, debeId, …) siguen funcionando;
 * sin `prefijo` no hay botones de guardar y la pestaña se comporta como la vista previa anterior.
 */
(function () {
    function debounce(fn, ms) {
        let t;
        return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), ms); };
    }

    window.crearAsientoTab = function (cfg) {
        const colspan = cfg.colspan || 5;
        const p = cfg.prefijo || null;
        // Los ids explícitos mandan sobre el prefijo: así los módulos que ya tenían su HTML
        // propio pueden migrar campo por campo sin romperse.
        const ids = {
            tbody:      cfg.tbodyId      || (p ? `${p}-asiento-tbody` : null),
            debe:       cfg.debeId       || (p ? `${p}-asiento-debe` : null),
            haber:      cfg.haberId      || (p ? `${p}-asiento-haber` : null),
            dif:        cfg.difId        || (p ? `${p}-asiento-dif` : null),
            badge:      cfg.badgeId      || (p ? `${p}-asiento-badge` : null),
            count:      cfg.countId      || (p ? `${p}-asiento-count` : null),
            status:     cfg.statusId     || (p ? `${p}-asiento-status` : null),
            add:        cfg.addBtnId     || (p ? `${p}-asiento-add` : null),
            save:       cfg.saveBtnId    || (p ? `${p}-asiento-save` : null),
            restore:    cfg.restoreBtnId || (p ? `${p}-asiento-restore` : null),
            docFila:    p ? `${p}-asiento-doc` : null,
            docEtiqueta:p ? `${p}-asiento-doc-etiqueta` : null,
            docTotal:   p ? `${p}-asiento-doc-total` : null,
            docEstado:  p ? `${p}-asiento-doc-estado` : null,
        };

        const $ = (id) => (id ? document.getElementById(id) : null);
        const filas = () => (ids.tbody ? document.querySelectorAll('#' + ids.tbody + ' .asiento-linea-row') : []);

        let manual = false;        // ¿el usuario tocó algo desde que se cargó?
        let guardable = false;     // ¿se puede guardar el asiento desde aquí? (asiento ya registrado)
        let cabecera = null;       // cabecera del asiento guardado (null = vista previa)
        let cuadreDoc = null;      // importe del documento y cuentas de cartera con qué comparar
        let editable = false;      // ¿esta carga admite edición?
        let idDocumento = 0;

        // ── Totales y cuadre ──────────────────────────────────────────────────────

        function recalcular() {
            let td = 0, th = 0;
            filas().forEach(tr => {
                td += parseFloat(tr.querySelector('.input-debe').value) || 0;
                th += parseFloat(tr.querySelector('.input-haber').value) || 0;
            });
            const lblD = $(ids.debe), lblH = $(ids.haber), lblDif = $(ids.dif), badge = $(ids.badge), cnt = $(ids.count);
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
            renderCuadreDocumento();
        }

        /**
         * Con qué monto del asiento se compara el importe del documento. Réplica exacta del
         * criterio del servidor (AsientoContableRules::evaluarCuadreDocumento): si el documento
         * tiene cuenta de cartera configurada se compara esa línea —el Debe total incluye costo
         * de ventas y descuentos, así que no sirve de referencia—; si no, el total Debe.
         */
        function montoComparableConDocumento() {
            const cuentas = (cuadreDoc.cuentas_cartera || []).map(Number);
            const lado = cuadreDoc.lado === 'haber' ? 'haber' : 'debe';

            let totalDebe = 0, montoCartera = 0, hayCartera = false;
            filas().forEach(tr => {
                const idCuenta = parseInt(tr.querySelector('.input-id-cuenta-contable')?.value || 0, 10);
                totalDebe += parseFloat(tr.querySelector('.input-debe')?.value || 0);
                if (cuentas.length > 0 && cuentas.includes(idCuenta)) {
                    hayCartera = true;
                    montoCartera += parseFloat(tr.querySelector(lado === 'haber' ? '.input-haber' : '.input-debe')?.value || 0);
                }
            });

            return hayCartera
                ? { monto: montoCartera, base: 'cartera' }
                : { monto: totalDebe, base: cuentas.length > 0 ? 'sin_cartera' : 'total_debe' };
        }

        function renderCuadreDocumento() {
            const fila = $(ids.docFila);
            if (!fila) return;

            if (!cuadreDoc || filas().length === 0) {
                fila.classList.add('d-none');
                return;
            }
            fila.classList.remove('d-none');

            const total = parseFloat(cuadreDoc.total_documento || 0);
            const tolerancia = parseFloat(cuadreDoc.tolerancia || 0.03);
            const doc = (cuadreDoc.etiqueta || 'Documento')
                      + (cuadreDoc.numero_documento ? ' ' + cuadreDoc.numero_documento : '');

            const elEtq = $(ids.docEtiqueta), elTot = $(ids.docTotal), elEst = $(ids.docEstado);
            if (elEtq) elEtq.textContent = doc;
            if (elTot) elTot.textContent = total.toFixed(2);
            if (!elEst) return;

            const { monto, base } = montoComparableConDocumento();
            const dif = Math.abs(total - monto);

            if (base === 'sin_cartera') {
                elEst.textContent = 'Falta la línea de la cuenta por ' + (cuadreDoc.lado === 'haber' ? 'pagar' : 'cobrar') + ': sin ella no se puede guardar.';
                elEst.className = 'small fw-bold text-danger';
            } else if (dif <= tolerancia) {
                elEst.textContent = base === 'cartera'
                    ? 'Coincide con la cuenta por ' + (cuadreDoc.lado === 'haber' ? 'pagar' : 'cobrar') + ' del asiento.'
                    : 'Coincide con el total del asiento.';
                elEst.className = 'small text-success';
            } else {
                elEst.textContent = (base === 'cartera' ? 'Cartera del asiento: ' : 'Asiento: ')
                                  + monto.toFixed(2) + ' · diferencia: ' + dif.toFixed(2);
                elEst.className = 'small fw-bold text-danger';
            }
        }

        // ── Líneas ────────────────────────────────────────────────────────────────

        function agregarLinea(idCuenta = '', codigo = '', nombre = '', debe = 0, haber = 0, referencia = '', extra = null) {
            const tbody = $(ids.tbody);
            if (!tbody) return;
            const ph = tbody.querySelector('td[colspan]');
            if (ph) tbody.innerHTML = '';

            const tr = document.createElement('tr');
            tr.className = 'asiento-linea-row';
            // Centro de costo y proyecto no se editan en la pestaña (eso vive en el modal del
            // Libro Diario), pero viajan con la línea: sin esto, guardar desde aquí los borraría.
            if (extra && extra.id_centro_costo) tr.dataset.idCentroCosto = extra.id_centro_costo;
            if (extra && extra.id_proyecto) tr.dataset.idProyecto = extra.id_proyecto;
            const dv = parseFloat(debe || 0), hv = parseFloat(haber || 0);
            const ro = editable ? '' : 'readonly';
            tr.innerHTML = `
                <td class="ps-3 position-relative align-middle p-0">
                    <input type="text" class="form-control border-0 bg-transparent input-cuenta-nombre" ${ro} placeholder="${editable ? 'Escriba código o cuenta contable...' : ''}" value="${nombre ? (codigo ? codigo + ' - ' + nombre : nombre) : ''}" style="padding:0 4px;height:20px;font-size:0.78rem;">
                    <input type="hidden" class="input-id-cuenta-contable" value="${idCuenta}">
                    <div class="list-group position-absolute w-100 shadow rounded-3 d-none select-cuenta-dropdown" style="z-index:1050;max-height:200px;overflow-y:auto;"></div>
                </td>
                <td class="align-middle p-0"><input type="number" step="0.01" class="form-control text-end border-0 bg-transparent fw-medium input-debe text-primary" ${ro} placeholder="0.00" value="${dv.toFixed(2) === '0.00' ? '' : dv.toFixed(2)}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
                <td class="align-middle p-0"><input type="number" step="0.01" class="form-control text-end border-0 bg-transparent fw-medium input-haber text-primary" ${ro} placeholder="0.00" value="${hv.toFixed(2) === '0.00' ? '' : hv.toFixed(2)}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
                <td class="align-middle p-0"><input type="text" class="form-control border-0 bg-transparent input-referencia text-muted fst-italic" ${ro} placeholder="${editable ? 'Referencia' : ''}" value="${referencia}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
                <td class="text-center p-0 align-middle" style="width:40px;">
                    ${editable ? '<button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0 btn-del-asiento-linea" title="Eliminar línea"><i class="bi bi-trash3 fs-6"></i></button>' : ''}
                </td>`;
            tbody.appendChild(tr);

            const inpCuenta = tr.querySelector('.input-cuenta-nombre');
            const hiddenCuenta = tr.querySelector('.input-id-cuenta-contable');
            const dropdown = tr.querySelector('.select-cuenta-dropdown');
            const inpDebe = tr.querySelector('.input-debe');
            const inpHaber = tr.querySelector('.input-haber');
            const inpRef = tr.querySelector('.input-referencia');

            if (!editable) { recalcular(); return; }

            tr.querySelector('.btn-del-asiento-linea').addEventListener('click', () => { tr.remove(); manual = true; recalcular(); });

            inpCuenta.addEventListener('input', debounce(async (e) => {
                const q = e.target.value.trim();
                // El texto ya no describe la cuenta fijada: se suelta el id para que el cuadre en
                // vivo deje de contar esa línea como cartera hasta que se elija una cuenta real.
                hiddenCuenta.value = '';
                recalcular();
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
                                recalcular();
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
            const tbody = $(ids.tbody);
            if (tbody) tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-4 ${cls || 'text-muted'}">${msg}</td></tr>`;
            recalcular();
        }

        function setStatus(html, cls) {
            const el = $(ids.status);
            if (!el) return;
            el.innerHTML = html || '';
            el.className = 'px-1 pt-2 small ' + (cls || 'text-muted');
        }

        /**
         * Muestra u oculta los botones según lo que admita esta carga.
         *
         * «Editable» y «guardable» no son lo mismo: en los módulos con `previewEditable`
         * (consignaciones, retornos y cambios de producto) las líneas de la VISTA PREVIA se
         * pueden completar a mano, pero no hay asiento que guardar todavía — esas líneas viajan
         * con el documento al pulsar Guardar en el modal.
         */
        function pintarBotones() {
            const add = $(ids.add), save = $(ids.save), restore = $(ids.restore);
            if (add) add.classList.toggle('d-none', !editable);
            if (save) save.classList.toggle('d-none', !guardable);
            if (restore) restore.classList.toggle('d-none', !(guardable && cabecera && cabecera.editado_manual));
        }

        // ── Carga ─────────────────────────────────────────────────────────────────

        function esVerdadero(v) { return v === true || v === 't' || v === '1' || v === 1; }

        /**
         * Asiento REAL del documento, tomado del módulo de Asientos Contables. Devuelve la
         * cabecera cuando ya existe (entonces la pestaña se puede editar y guardar) o null
         * cuando el documento todavía no se contabilizó — en ese caso deja igual el contexto
         * de cuadre, que el endpoint manda también en la rama "sin asiento" para que el
         * importe del documento se vea mientras se mira la vista previa.
         */
        async function cargarAsientoReal() {
            if (!cfg.moduloOrigen) return null;
            try {
                const url = `${urlAsientos()}/getDetalleAjax?modulo=${encodeURIComponent(cfg.moduloOrigen)}&id_ref=${idDocumento}`;
                const res = await (await fetch(url)).json();
                if (!res.ok || !res.data) {
                    cuadreDoc = res.cuadre_documento || null;
                    return null;
                }
                const d = res.data;
                cuadreDoc = d.cuadre_documento || null;
                const estado = String(d.estado || '').toLowerCase().trim();
                const tipo   = String(d.tipo_comprobante || '').toLowerCase().trim();
                return {
                    id: parseInt(d.id, 10),
                    fecha_asiento: String(d.fecha_asiento || '').slice(0, 10),
                    tipo_comprobante: d.tipo_comprobante || 'diario',
                    numero_comprobante: d.numero_comprobante || '',
                    concepto: d.concepto || '',
                    estado: d.estado || 'contabilizado',
                    observaciones: d.observaciones || '',
                    modulo_origen: d.modulo_origen || cfg.moduloOrigen,
                    id_referencia_origen: d.id_referencia_origen || idDocumento,
                    editado_manual: esVerdadero(d.editado_manual),
                    // Un asiento anulado solo se modifica si es de tipo Diario: misma regla que
                    // aplica AsientosContablesController::update().
                    modificable: estado !== 'anulado' || tipo === 'diario',
                    detalles: d.detalles || [],
                };
            } catch (err) {
                console.error('Asiento del documento:', err);
                return null;
            }
        }

        /**
         * @param {number} id id del documento.
         * @param {{vistaPrevia?: boolean}} [opciones] `vistaPrevia: true` salta el asiento
         *   registrado y muestra lo que armarían las reglas con los valores que hay AHORA en el
         *   formulario. Lo usa la factura de venta mientras se editan sus líneas.
         */
        async function cargar(id, opciones) {
            const soloVistaPrevia = !!(opciones && opciones.vistaPrevia);
            const tbody = $(ids.tbody);
            if (!tbody) return;
            idDocumento = parseInt(id) || 0;
            cabecera = null;
            cuadreDoc = null;
            editable = false;
            guardable = false;
            pintarBotones();

            if (!idDocumento && !soloVistaPrevia) {
                placeholder('<i class="bi bi-info-circle me-1"></i> Guarda el documento para generar el asiento contable.');
                setStatus('');
                return;
            }
            placeholder('<i class="bi bi-hourglass-split me-1"></i> Cargando asiento contable...');
            setStatus('');

            try {
                // 1. Asiento ya registrado: es el que se edita.
                cabecera = soloVistaPrevia ? null : await cargarAsientoReal();
                if (cabecera && cabecera.detalles.length) {
                    // Solo se guarda desde aquí un asiento existente. Los botones existen solo si
                    // el usuario puede actualizar asientos contables (la vista no los renderiza
                    // sin ese permiso).
                    guardable = !!(ids.save && $(ids.save) && cabecera.modificable);
                    editable = guardable;
                    pintarBotones();

                    tbody.innerHTML = '';
                    cabecera.detalles.forEach(d => agregarLinea(
                        d.id_cuenta_contable,
                        d.codigo_cuenta || d.cuenta_codigo || '',
                        d.nombre_cuenta || d.cuenta_nombre || '',
                        parseFloat(d.debe || 0),
                        parseFloat(d.haber || 0),
                        d.documento_referencia || d.referencia_detalle || '',
                        { id_centro_costo: d.id_centro_costo, id_proyecto: d.id_proyecto }
                    ));
                    manual = false;
                    recalcular();

                    if (cabecera.editado_manual) {
                        setStatus('<i class="bi bi-pencil-fill me-1"></i> Asiento editado a mano: el sistema ya no lo regenera al guardar el documento.', 'text-warning-emphasis');
                    } else if (guardable) {
                        setStatus('<i class="bi bi-check-circle-fill me-1"></i> Asiento contable registrado. Puedes corregirlo aquí y guardarlo.', 'text-success');
                    } else {
                        setStatus('<i class="bi bi-check-circle-fill me-1"></i> Asiento contable generado y registrado.', 'text-success');
                    }
                    return;
                }

                // 2. Todavía sin asiento: vista previa de lo que armarán las reglas contables.
                //    Los módulos que no ofrecen vista previa (no todos tienen un endpoint que la
                //    calcule) se quedan con el aviso de "sin asiento".
                cabecera = null;
                guardable = false;
                // `previewEditable`: el módulo persiste el asiento con el documento a partir de
                // estas líneas (enviándolas como `asiento_detalles`), así que la vista previa se
                // deja completar a mano — es la única forma de rellenar una cuenta que la
                // configuración contable no resolvió. Aun así no hay botón Guardar asiento: se
                // guarda con el documento.
                editable = !!(cfg.previewEditable && ids.add && $(ids.add));
                pintarBotones();
                if (!cfg.previewUrl) {
                    placeholder('<i class="bi bi-info-circle me-1"></i> A&uacute;n no se ha generado el asiento contable de este documento.');
                    setStatus('');
                    return;
                }
                // Parámetros extra de la vista previa: algunos módulos la calculan con los
                // valores que hay en el formulario, no solo con el id (ver factura de venta).
                const qs = new URLSearchParams({ id: idDocumento });
                if (typeof cfg.previewParams === 'function') {
                    const extra = cfg.previewParams(soloVistaPrevia) || {};
                    Object.keys(extra).forEach(k => qs.set(k, extra[k]));
                }
                const json = await (await fetch(`${cfg.previewUrl}?${qs.toString()}`)).json();
                if (json.ok && json.detalles && json.detalles.length) {
                    tbody.innerHTML = '';
                    json.detalles.forEach(d => agregarLinea(
                        d.id_cuenta_contable,
                        d.cuenta_codigo || d.codigo_cuenta || '',
                        d.cuenta_nombre || d.nombre_cuenta || '',
                        parseFloat(d.debe || 0),
                        parseFloat(d.haber || 0),
                        d.documento_referencia || d.referencia_detalle || d.referencia || '',
                        { id_centro_costo: d.id_centro_costo, id_proyecto: d.id_proyecto }
                    ));
                    manual = false;
                    recalcular();
                    setStatus(editable
                        ? '<i class="bi bi-info-circle me-1"></i> Vista previa: complete las cuentas que falten; el asiento se registra al guardar el documento.'
                        : '<i class="bi bi-info-circle me-1"></i> Vista previa: este asiento se generar&aacute; al guardar el documento. Una vez generado, se podr&aacute; corregir aqu&iacute;.',
                        'text-muted');
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
                    referencia_detalle: tr.querySelector('.input-referencia').value,
                    documento_referencia: tr.querySelector('.input-referencia').value,
                    id_centro_costo: tr.dataset.idCentroCosto || null,
                    id_proyecto: tr.dataset.idProyecto || null
                });
            });
            return detalles;
        }

        // ── Guardado ──────────────────────────────────────────────────────────────

        function urlAsientos() {
            return cfg.asientosUrl
                || `${(typeof window.BASE_URL !== 'undefined' ? window.BASE_URL : '')}/modulos/asientos-contables`;
        }

        async function guardar() {
            if (!editable || !cabecera) return;

            // Toda línea debe tener cuenta: una fila a medias se descartaría en silencio y el
            // asiento quedaría descuadrado sin que se note.
            let incompleta = false;
            filas().forEach(tr => {
                const inp = tr.querySelector('.input-cuenta-nombre');
                const ok = !!tr.querySelector('.input-id-cuenta-contable').value;
                inp.classList.toggle('is-invalid', !ok);
                if (!ok) incompleta = true;
            });
            if (incompleta) {
                Swal.fire('Falta la cuenta contable', 'Seleccione una cuenta válida en todas las líneas del asiento.', 'warning');
                return;
            }

            const detalles = capturar();
            if (!detalles.length) {
                Swal.fire('Asiento vacío', 'Agregue al menos una línea al asiento.', 'warning');
                return;
            }

            const totalDebe = detalles.reduce((s, d) => s + d.debe, 0);
            const totalHaber = detalles.reduce((s, d) => s + d.haber, 0);
            if (Math.abs(totalDebe - totalHaber) >= 0.005) {
                Swal.fire('El asiento no cuadra', `Debe ${totalDebe.toFixed(2)} y Haber ${totalHaber.toFixed(2)} deben ser iguales.`, 'warning');
                return;
            }

            const btn = $(ids.save);
            const htmlOrig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }

            const fd = new FormData();
            fd.append('id', cabecera.id);
            fd.append('fecha_asiento', cabecera.fecha_asiento);
            fd.append('tipo_comprobante', cabecera.tipo_comprobante);
            fd.append('numero_comprobante', cabecera.numero_comprobante);
            fd.append('concepto', cabecera.concepto);
            fd.append('estado', cabecera.estado);
            fd.append('observaciones', cabecera.observaciones || '');
            fd.append('modulo_origen', cabecera.modulo_origen);
            fd.append('id_referencia_origen', cabecera.id_referencia_origen);
            fd.append('detalles_json', JSON.stringify(detalles));

            try {
                const url = `${urlAsientos()}/update`;
                let res = await (await fetch(url, { method: 'POST', body: fd })).json();

                // El asiento ya no refleja el importe del documento. No se impide guardarlo —puede
                // haber un ajuste legítimo—, pero se avisa y la confirmación queda en la auditoría.
                if (!res.ok && res.requiere_confirmacion) {
                    const conf = await Swal.fire({
                        icon: 'warning',
                        title: 'El asiento no cuadra con el documento',
                        text: res.mensaje,
                        showCancelButton: true,
                        confirmButtonText: 'Guardar de todos modos',
                        cancelButtonText: 'Revisar el asiento',
                        confirmButtonColor: '#d33',
                        reverseButtons: true,
                    });
                    if (!conf.isConfirmed) return;
                    fd.append('confirmar_descuadre', '1');
                    res = await (await fetch(url, { method: 'POST', body: fd })).json();
                }

                if (!res.ok) {
                    Swal.fire('No se pudo guardar', res.error || 'Error al guardar el asiento.', 'error');
                    return;
                }

                manual = false;
                await Swal.fire({ icon: 'success', title: 'Asiento guardado', text: res.msg || 'Asiento actualizado correctamente.', timer: 1800, showConfirmButton: false });
                if (typeof cfg.onGuardado === 'function') cfg.onGuardado(res);
                await cargar(idDocumento);
            } catch (e) {
                console.error('Guardar asiento:', e);
                Swal.fire('Error de red', 'Verifique su conexión e intente nuevamente.', 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = htmlOrig; }
            }
        }

        /** Descarta la edición manual y vuelve a armar el asiento con las reglas contables. */
        async function restaurar() {
            if (!cabecera) return;
            const conf = await Swal.fire({
                icon: 'question',
                title: '¿Restaurar el asiento automático?',
                text: 'Se descartan las correcciones hechas a mano y el asiento se vuelve a armar con la configuración contable.',
                showCancelButton: true,
                confirmButtonText: 'Restaurar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            });
            if (!conf.isConfirmed) return;

            const fd = new FormData();
            fd.append('id', cabecera.id);
            try {
                const res = await (await fetch(`${urlAsientos()}/restaurarAutomaticoAjax`, { method: 'POST', body: fd })).json();
                if (!res.ok) {
                    Swal.fire('No se pudo restaurar', res.error || 'Error al restaurar el asiento.', 'error');
                    return;
                }
                await Swal.fire({ icon: 'success', title: 'Listo', text: res.msg, timer: 2200, showConfirmButton: false });
                if (typeof cfg.onGuardado === 'function') cfg.onGuardado(res);
                await cargar(idDocumento);
            } catch (e) {
                console.error('Restaurar asiento:', e);
                Swal.fire('Error de red', 'Verifique su conexión e intente nuevamente.', 'error');
            }
        }

        // Botones del pie de la pestaña (solo existen si la vista los renderizó, es decir, si el
        // usuario tiene permiso de actualizar asientos contables).
        const btnAdd = $(ids.add);
        if (btnAdd) btnAdd.addEventListener('click', () => { agregarLinea(); manual = true; });
        const btnSave = $(ids.save);
        if (btnSave) btnSave.addEventListener('click', guardar);
        const btnRestore = $(ids.restore);
        if (btnRestore) btnRestore.addEventListener('click', restaurar);

        return {
            cargar,
            capturar,
            recalcular,
            agregarLinea,
            guardar,
            limpiar: () => {
                cabecera = null; cuadreDoc = null; editable = false; guardable = false; pintarBotones();
                placeholder('<i class="bi bi-info-circle me-1"></i> Guarda el documento para generar el asiento contable.');
                setStatus('');
            },
            isManual: () => manual,
            esEditable: () => editable
        };
    };
})();
