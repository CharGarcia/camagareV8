/**
 * Cotizador de Planes (modulos/cotizador-planes).
 * El precio NO se calcula aquí: cada cambio pide /calcularAjax al servidor
 * (CotizadorPlanesCalculo), así pantalla, cotización guardada y proforma usan el mismo número.
 */
(function () {
    'use strict';

    const CFG = window.CP_CFG || {};
    const URL = CFG.url;
    const $ = (id) => document.getElementById(id);
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (v) => '$' + Number(v || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // Redondeo a 2 decimales del sistema (app.js); igual al del servidor (CotizadorPlanesCalculo::r2).
    const r2 = (v) => (typeof window.CMG_r2 === 'function' ? window.CMG_r2(v) : Number(Number(v).toFixed(2)));
    const pct = (v) => Number(v || 0).toLocaleString('es-EC', { maximumFractionDigits: 2 }) + ' %';

    async function getJson(url) {
        const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return r.json();
    }
    async function postJson(url, data, extraHeaders) {
        const r = await fetch(url, {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, extraHeaders || {}),
            body: JSON.stringify(data || {}),
        });
        return r.json();
    }
    const alerta = (icon, title, text) => (window.Swal ? Swal.fire({ icon, title, text }) : alert(title + '\n' + (text || '')));

    // ═════════════════════════ Listado ═════════════════════════
    let paginaActual = CFG.pagina || 1;
    let sorter = null;

    window.CP_buscar = async function (page) {
        paginaActual = page || 1;
        const b = ($('buscarCP')?.value || '').trim();
        const orden = window.CMG_ordenParam ? window.CMG_ordenParam(window.currentSorts || []) : '';
        const tbody = $('tbodyCP');
        tbody?.classList.add('fm-cargando-target');
        try {
            const j = await getJson(`${URL}/searchAjax?b=${encodeURIComponent(b)}&page=${paginaActual}&orden=${encodeURIComponent(orden)}`);
            if (j.ok) {
                tbody.innerHTML = j.rows;
                $('paginacionCP').innerHTML = j.pagination;
                $('paginacionInfoCP').textContent = j.info;
                $('btnPdfCP').href = j.pdf_url;
                $('btnExcelCP').href = j.excel_url;
                sorter?.refreshIcons?.();
            }
        } catch (e) { console.error(e); }
        finally { tbody?.classList.remove('fm-cargando-target'); }
    };
    window.cambiarPaginaAjax = (p) => window.CP_buscar(p);

    if (typeof window.CMG_initSort === 'function') {
        sorter = window.CMG_initSort('cotizador_planes', (col, dir, sorts) => {
            window.currentSorts = sorts;
            window.CP_buscar(1);
        }, { sorts: window.currentSorts, multi: true, container: '.cotizador-scroll', reload: false });
    }

    // ═════════════════════════ Cotización ═════════════════════════
    const modalEl = $('modalCP');
    let modal = null;
    const getModal = () => (modal ||= new bootstrap.Modal(modalEl));

    let catalogo = [];        // ítems activos
    let catalogoPorId = {};
    let paquetes = [];        // paquetes activos
    let paquetesPorId = {};
    let parametros = {};
    let estado = null;        // estado de la cotización abierta
    let establecimientos = []; // [{codigo, nombre, direccion, elegidos:[ids]}]
    let ultimoCalculo = null;
    let tokenGuardado = '';
    let guardando = false;
    let sucio = false;
    let timerCalc = null;
    let secuenciaCalc = 0;

    async function cargarCatalogo() {
        const j = await getJson(`${URL}/getCatalogoAjax`);
        if (!j.ok) throw new Error(j.error || 'No se pudo cargar el catálogo de precios.');
        catalogo = j.items || [];
        parametros = j.parametros || {};
        catalogoPorId = {};
        catalogo.forEach((it) => { catalogoPorId[it.id] = it; });
        paquetes = j.paquetes || [];
        paquetesPorId = {};
        paquetes.forEach((p) => { paquetesPorId[p.id] = p; });
    }

    function editable() {
        return !estado || estado.estado === 'borrador';
    }

    function aviso(tipo, html) {
        const el = $('cpAviso');
        if (!html) { el.classList.add('d-none'); return; }
        el.className = `alert alert-${tipo} mx-3 mt-2 mb-0 py-2 small`;
        el.innerHTML = html;
    }

    const CAMPOS = ['identificacion', 'razon_social', 'nombre_comercial', 'tipo_contribuyente', 'obligado_contabilidad',
        'contacto_nombre', 'correo', 'telefono', 'direccion', 'cod_prov', 'cod_ciudad', 'id_vendedor', 'observaciones',
        'usuarios', 'modalidad', 'descuento_vendedor_pct'];

    function leerFormulario() {
        const d = { id: parseInt($('cp_id').value || 0, 10) || 0 };
        CAMPOS.forEach((c) => { d[c] = $('cp_' + c)?.value ?? ''; });
        d.usuarios = parseInt(d.usuarios || 0, 10) || 0;
        d.descuento_vendedor_pct = parseFloat(d.descuento_vendedor_pct || 0) || 0;
        d.establecimientos = establecimientos.map((e) => ({ codigo: e.codigo, nombre: e.nombre, direccion: e.direccion, id_paquete: e.id_paquete || null, elegidos: e.elegidos.slice() }));
        if (!d.id) d.token_guardado = tokenGuardado;
        return d;
    }

    function llenarFormulario(c) {
        CAMPOS.forEach((k) => {
            const el = $('cp_' + k);
            if (!el) return;
            let v = c ? c[k] : '';
            if (k === 'obligado_contabilidad') v = (v === null || v === undefined || v === '') ? '' : (v ? '1' : '0');
            if (k === 'usuarios') v = c ? c.usuarios : (parametros.usuarios_por_establecimiento || 3);
            if (k === 'modalidad') v = c ? c.modalidad : 'anual';
            if (k === 'descuento_vendedor_pct') v = c ? c.descuento_vendedor_pct : 0;
            // Nueva (o sin vendedor): se propone el vendedor vinculado al usuario.
            if (k === 'id_vendedor' && !(c && c.id_vendedor)) v = Number(el.dataset.propio || 0) || '';
            el.value = v ?? '';
        });
    }

    function aplicarModoLectura() {
        const ed = editable();
        modalEl.querySelectorAll('.cp-editable').forEach((el) => { el.disabled = !ed; });
        // Nivel 1 no cambia el vendedor: la cotización queda a nombre del suyo (lo fija el servidor).
        if ($('cp_id_vendedor')?.dataset.fijo === '1') $('cp_id_vendedor').disabled = true;
        $('cpBtnGuardar')?.classList.toggle('d-none', !ed || !(estado ? CFG.perm.actualizar : CFG.perm.crear));
        $('cpBtnProforma')?.classList.toggle('d-none', !ed);
        $('cpBtnEliminar')?.classList.toggle('d-none', !(estado && ed && CFG.perm.eliminar));
        const ver = $('cpBtnVerProforma');
        if (estado && estado.id_proforma && estado._url_proforma) {
            ver.href = estado._url_proforma;
            ver.classList.remove('d-none');
        } else {
            ver.classList.add('d-none');
        }
        const descOk = !!parametros.permitir_descuento_vendedor;
        $('cpDescuentoWrap').classList.toggle('d-none', !descOk && !(estado && Number(estado.descuento_vendedor_pct) > 0));
        $('cpDescuentoAyuda').textContent = descOk ? `Máximo ${pct(parametros.descuento_vendedor_max_pct)}` : '';
    }

    function pintarEncabezado() {
        const badge = $('cpEstado');
        if (!estado) {
            $('cpTitulo').textContent = 'Nueva cotización';
            badge.className = 'badge ms-2 d-none';
            return;
        }
        $('cpTitulo').textContent = `Cotización N.º ${estado.numero} — ${estado.razon_social || ''}`;
        const map = { borrador: ['Borrador', 'secondary'], proforma: ['Proforma generada', 'success'], anulada: ['Anulada', 'danger'] };
        const [t, c] = map[estado.estado] || [estado.estado, 'secondary'];
        badge.className = `badge ms-2 bg-${c} bg-opacity-10 text-${c} border border-${c} border-opacity-25`;
        badge.textContent = t;
    }

    function nuevoEstablecimiento(codigo, nombre, direccion) {
        // Un establecimiento nuevo empieza vacío: el vendedor elige el paquete y/o los módulos.
        return { codigo, nombre: nombre || '', direccion: direccion || '', id_paquete: null, elegidos: [] };
    }

    function siguienteCodigo() {
        const usados = establecimientos.map((e) => parseInt(e.codigo, 10) || 0);
        let n = 1;
        while (usados.includes(n)) n++;
        return String(n).padStart(3, '0');
    }

    window.CP_nueva = async function () {
        try { await cargarCatalogo(); } catch (e) { return alerta('error', 'Cotizador', e.message); }
        if (!catalogo.length) {
            return alerta('warning', 'Catálogo de precios vacío', 'Un administrador debe cargar los módulos y sus precios (botón Configuración) antes de cotizar.');
        }
        estado = null;
        $('cp_id').value = '';
        tokenGuardado = typeof window.CMG_nuevoTokenGuardado === 'function' ? window.CMG_nuevoTokenGuardado() : '';
        llenarFormulario(null);
        establecimientos = [nuevoEstablecimiento('001', '', '')];
        $('cpSriInfo').innerHTML = '';
        aviso();
        pintarEncabezado();
        aplicarModoLectura();
        pintarEstablecimientos(null);
        bootstrap.Tab.getOrCreateInstance($('cp-tab-cliente-btn')).show();
        sucio = false;
        getModal().show();
        recalcular(true);
        setTimeout(() => $('cp_identificacion').focus(), 400);
    };

    window.CP_abrir = async function (id) {
        try {
            await cargarCatalogo();
            const j = await getJson(`${URL}/getCotizacionAjax?id=${id}`);
            if (!j.ok) return alerta('error', 'Cotización', j.error);
            estado = j.data;
            if (estado.id_proforma) estado._url_proforma = estado.url_proforma;
        } catch (e) { return alerta('error', 'Cotización', 'Error de conexión.'); }
        $('cp_id').value = estado.id;
        tokenGuardado = '';
        llenarFormulario(estado);
        establecimientos = (estado.establecimientos || []).map((e) => ({ codigo: e.codigo, nombre: e.nombre || '', direccion: e.direccion || '', id_paquete: e.id_paquete || null, elegidos: (e.elegidos || []).map(Number) }));
        $('cpSriInfo').innerHTML = '';
        aviso();
        if (estado.estado === 'proforma') {
            aviso('success', `<i class="bi bi-check-circle me-1"></i>Esta cotización ya generó la proforma <strong>${esc(estado.numero_proforma || '')}</strong>. El seguimiento continúa en el <strong>CRM</strong> y en <strong>Proformas</strong>.`);
        }
        pintarEncabezado();
        aplicarModoLectura();
        pintarEstablecimientos(null);
        bootstrap.Tab.getOrCreateInstance($('cp-tab-cliente-btn')).show();
        sucio = false;
        getModal().show();
        if (editable()) {
            recalcular(true);
        } else {
            // Cotización cerrada: se muestran sus precios guardados, sin recalcular contra el catálogo.
            mostrarGuardada();
        }
    };

    /** Vista de una cotización cerrada con sus precios copiados (no el catálogo vigente). */
    function mostrarGuardada() {
        const est = (estado.establecimientos || []).map((e) => ({
            codigo: e.codigo, nombre: e.nombre, direccion: e.direccion, es_principal: e.es_principal,
            paquete_nombre: e.paquete_nombre, paquete_descuento_pct: e.paquete_descuento_pct,
            paquete_tipo_precio: e.paquete_tipo_precio, paquete_precio_anual: e.paquete_precio_anual,
            items: (e.items || []).map((it) => ({ ...it, requerido_por: [] })),
        }));
        $('cpTotAnual').textContent = money(estado.total_anual);
        $('cpTotMensual').textContent = money(estado.total_mensual);
        $('cpTotSemestral').textContent = money(estado.total_semestral);
        $('cpTotSemestralNota').textContent = 'por semestre';
        $('cpTotUnico').textContent = money(estado.total_unico);
        $('cpTotUsuarios').textContent = estado.usuarios;
        $('cpTotAnualNota').textContent = 'al año';
        $('cpTotMensualNota').textContent = 'al mes';
        $('cpTotUsuariosNota').textContent = '';
        $('cpResumenFilas').innerHTML = '<tr><td colspan="8" class="text-muted">Los valores por establecimiento están en el anexo de condiciones de la proforma.</td></tr>';
        $('cpResumenPie').innerHTML = '';
        pintarEstablecimientos({ establecimientos: est });
    }

    // ── Establecimientos ───────────────────────────────────────────────────
    function opcionesCatalogo(est) {
        const yaEsta = new Set();
        (est.items || []).forEach((it) => yaEsta.add(Number(it.id_item)));
        est.elegidos.forEach((id) => yaEsta.add(Number(id)));
        const grupos = {};
        catalogo.forEach((it) => {
            if (yaEsta.has(it.id)) return;
            const g = it.tipo === 'servicio' ? 'Servicios' : (it.grupo || 'Otros');
            (grupos[g] ||= []).push(it);
        });
        let html = '<option value="">Agregar módulo o servicio…</option>';
        Object.keys(grupos).sort().forEach((g) => {
            html += `<optgroup label="${esc(g)}">` + grupos[g].map((it) =>
                `<option value="${it.id}">${esc(it.nombre)} — ${money(it.precio_anual)}${it.cobro === 'unico' ? ' (único)' : '/año'}</option>`).join('') + '</optgroup>';
        });
        return html;
    }

    /** Selector del paquete del establecimiento (o su nombre si la cotización está cerrada). */
    function selectorPaquete(e, f, i, ed) {
        const pctTxt = (p) => Number(p) > 0 ? ` (−${pct(p)})` : '';
        // Paquete de precio fijo: «($300.00/año)»; por descuento: «(−15 %)».
        const precioTxt = (x) => x.tipo_precio === 'fijo' || x.paquete_tipo_precio === 'fijo'
            ? ` (${money(x.precio_anual ?? x.paquete_precio_anual)}/año)` : pctTxt(x.descuento_pct ?? x.paquete_descuento_pct);
        if (!ed) {
            const nom = f.paquete_nombre || '';
            return nom ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Paquete ${esc(nom)}${esc(precioTxt(f))}</span>` : '';
        }
        if (!paquetes.length && !e.id_paquete) return '';
        const opts = ['<option value="">Sin paquete (módulos sueltos)</option>']
            .concat(paquetes.map((p) => `<option value="${p.id}" ${Number(e.id_paquete) === p.id ? 'selected' : ''}>${esc(p.nombre)}${esc(precioTxt(p))}</option>`));
        return `<select class="form-select form-select-sm" style="width:220px;" title="Paquete del establecimiento" onchange="CP_estCampo(${i}, 'id_paquete', this.value)">${opts.join('')}</select>`;
    }

    /** Pinta los bloques. Con calc: usa los ítems ya resueltos por el servidor (con dependencias). */
    function pintarEstablecimientos(calc) {
        const cont = $('cpEstablecimientos');
        const ed = editable();
        const filas = calc?.establecimientos || [];
        cont.innerHTML = establecimientos.map((e, i) => {
            const f = filas[i] || {};
            const items = f.items || e.elegidos.map((id) => ({ id_item: id, nombre: catalogoPorId[id]?.nombre || ('Ítem ' + id), precio_anual: catalogoPorId[id]?.precio_anual || 0, cobro: catalogoPorId[id]?.cobro, por_dependencia: false, grupo: catalogoPorId[id]?.grupo }));
            const principal = f.es_principal && establecimientos.length > 1
                ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-2" title="Plan base de mayor valor: paga completo">Principal</span>' : '';
            const filasItems = items.length ? items.map((it) => {
                const dep = !!it.por_dependencia;
                const dePaq = !!it.de_paquete;
                const quien = dep && it.requerido_por?.length ? `requerido por ${it.requerido_por.join(', ')}` : (dep ? 'requerido' : '');
                // Los módulos del paquete no se quitan (solo cambiando o quitando el paquete).
                const quitar = ed && !dep && !dePaq
                    ? `<button type="button" class="btn btn-link btn-sm text-danger p-0" title="Quitar" onclick="CP_quitarItem(${i}, ${Number(it.id_item)})"><i class="bi bi-x-circle"></i></button>` : '';
                const pctPaq = dePaq && it.cobro !== 'unico' ? Number(it.descuento_paquete_pct || 0) : 0;
                const precio = it.incluido
                    ? `<span class="text-muted text-decoration-line-through small me-1">${money(it.precio_anual)}</span><span class="text-success fw-semibold" title="${dePaq ? 'Va incluido en el precio del paquete' : 'Va incluido porque otro módulo lo requiere'}">Incluido</span>`
                    : pctPaq > 0
                        ? `<span class="text-muted text-decoration-line-through small me-1">${money(it.precio_anual)}</span>${money(r2(Number(it.precio_anual) - r2(Number(it.precio_anual) * pctPaq / 100)))}`
                        : money(it.precio_anual);
                return `<tr class="${dep ? 'cp-dep' : ''}">
                    <td>${dep ? '<i class="bi bi-link-45deg me-1" title="Entró porque otro módulo lo necesita"></i>' : ''}${esc(it.nombre)}
                        ${dePaq ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-1">Paquete</span>' : ''}
                        ${quien ? `<span class="small text-muted ms-1">(${esc(quien)})</span>` : ''}</td>
                    <td class="small text-muted">${esc(it.cobro === 'unico' ? 'Pago único' : (it.grupo || ''))}</td>
                    <td class="text-end" style="width:170px;">${precio}${it.cobro === 'unico' || it.incluido ? '' : '<span class="text-muted small">/año</span>'}</td>
                    <td class="text-center" style="width:32px;">${quitar}</td></tr>`;
            }).join('') : '<tr><td colspan="4" class="text-muted small">Sin módulos todavía: elija un paquete y/o agregue módulos sueltos.</td></tr>';
            return `<div class="cp-est">
                <div class="cp-est-head d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-semibold small"><i class="bi bi-shop me-1"></i>Establecimiento</span>
                    <input type="text" class="form-control form-control-sm" style="width:64px;" maxlength="3" value="${esc(e.codigo)}" ${ed ? '' : 'disabled'}
                        onchange="CP_estCampo(${i}, 'codigo', this.value)" title="Código del establecimiento (001, 002…)">
                    <input type="text" class="form-control form-control-sm flex-grow-1" style="min-width:180px;max-width:360px;" maxlength="300" placeholder="Nombre del local" value="${esc(e.nombre)}" ${ed ? '' : 'disabled'}
                        onchange="CP_estCampo(${i}, 'nombre', this.value)">
                    ${selectorPaquete(e, f, i, ed)}
                    ${principal}
                    <span class="ms-auto small">${f.anual !== undefined ? `<strong>${money(f.anual)}</strong>/año · ${money(f.semestral)}/semestre · ${money(f.mensual)}/mes` : ''}</span>
                    ${ed && establecimientos.length > 1 ? `<button type="button" class="btn btn-outline-danger btn-sm" title="Quitar establecimiento" onclick="CP_quitarEst(${i})"><i class="bi bi-trash3"></i></button>` : ''}
                </div>
                <div class="p-2">
                    ${Number(f.paquete_precio) > 0 ? `<div class="small mb-1"><i class="bi bi-box-seam me-1 text-info"></i>Paquete <strong>${esc(f.paquete_nombre || '')}</strong>: ${money(f.paquete_precio)}/año (sus módulos van incluidos)</div>` : ''}
                    <table class="table table-sm mb-2 cp-items"><tbody>${filasItems}</tbody></table>
                    ${ed ? `<select class="form-select form-select-sm" style="max-width:520px;" onchange="CP_agregarItem(${i}, this.value); this.value='';">${opcionesCatalogo({ elegidos: e.elegidos, items })}</select>` : ''}
                </div></div>`;
        }).join('');
    }

    window.CP_estCampo = function (i, campo, valor) {
        if (campo === 'codigo') {
            valor = String(valor || '').replace(/\D/g, '').padStart(3, '0').slice(-3);
        }
        if (campo === 'id_paquete') {
            valor = parseInt(valor || 0, 10) || null;
        }
        establecimientos[i][campo] = valor;
        marcarSucio();
    };
    window.CP_agregarItem = function (i, id) {
        id = parseInt(id, 10);
        if (!id || establecimientos[i].elegidos.includes(id)) return;
        establecimientos[i].elegidos.push(id);
        marcarSucio();
    };
    window.CP_quitarItem = function (i, id) {
        establecimientos[i].elegidos = establecimientos[i].elegidos.filter((x) => x !== id);
        marcarSucio();
    };
    window.CP_quitarEst = async function (i) {
        if (window.Swal) {
            const r = await Swal.fire({ icon: 'question', title: `¿Quitar el establecimiento ${establecimientos[i].codigo}?`, showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar' });
            if (!r.isConfirmed) return;
        }
        establecimientos.splice(i, 1);
        marcarSucio();
    };
    $('cpBtnAgregarEst')?.addEventListener('click', () => {
        establecimientos.push(nuevoEstablecimiento(siguienteCodigo(), '', ''));
        marcarSucio();
    });

    function marcarSucio() {
        sucio = true;
        recalcular();
    }

    // ── Cálculo en vivo (servidor) ─────────────────────────────────────────
    function recalcular(inmediato) {
        clearTimeout(timerCalc);
        timerCalc = setTimeout(ejecutarCalculo, inmediato ? 0 : 300);
    }

    async function ejecutarCalculo() {
        if (!editable()) return;
        const mio = ++secuenciaCalc;
        $('cpCalculando').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Calculando…';
        try {
            const d = leerFormulario();
            const j = await postJson(`${URL}/calcularAjax`, d, { 'X-Permitir-Repetido': '1' });
            if (mio !== secuenciaCalc) return; // llegó una respuesta vieja
            if (!j.ok) { $('cpCalculando').textContent = j.error || ''; return; }
            ultimoCalculo = j.calculo;
            // El servidor devuelve los ítems realmente elegidos (sin los inactivos).
            (ultimoCalculo.establecimientos || []).forEach((f, i) => {
                if (establecimientos[i]) {
                    // Solo los sueltos: los del paquete y las dependencias los pone el servidor.
                    establecimientos[i].elegidos = (f.elegidos || []).map(Number);
                    establecimientos[i].id_paquete = f.id_paquete || null;
                }
            });
            pintarCalculo(ultimoCalculo);
            pintarEstablecimientos(ultimoCalculo);
            $('cpCalculando').textContent = '';
            if (ultimoCalculo.avisos?.length) aviso('warning', ultimoCalculo.avisos.map(esc).join('<br>'));
        } catch (e) {
            if (mio === secuenciaCalc) $('cpCalculando').textContent = 'No se pudo calcular (conexión).';
        }
    }

    function pintarCalculo(c) {
        const p = c.parametros || {};
        $('cpTotAnual').textContent = money(c.total_anual);
        $('cpTotMensual').textContent = money(c.total_mensual);
        $('cpTotSemestral').textContent = money(c.total_semestral);
        $('cpTotSemestralNota').textContent = `${money(c.dos_semestres)} en el año`;
        $('cpTotUnico').textContent = money(c.total_unico);
        $('cpTotUsuarios').textContent = c.usuarios;
        $('cpTotAnualNota').textContent = c.ahorro_anual > 0 ? `ahorra ${money(c.ahorro_anual)} vs. mensual` : 'al año';
        $('cpTotMensualNota').textContent = `${money(c.doce_mensualidades)} en 12 meses`;
        $('cpTotUsuariosNota').textContent = `${c.usuarios_incluidos} incluidos` + (c.usuarios_adicionales > 0 ? ` · ${c.usuarios_adicionales} adicionales` : '');
        $('cpUsuariosAyuda').textContent = `${p.usuarios_por_establecimiento} por establecimiento, compartidos. Adicional: ${money(p.precio_usuario_adicional_anual)}/año.`;

        $('cpResumenFilas').innerHTML = (c.establecimientos || []).map((f) => `<tr>
            <td>${esc(f.codigo)} ${esc(f.nombre || '')}${f.es_principal && c.establecimientos.length > 1 ? ' <span class="badge bg-primary bg-opacity-10 text-primary">Principal</span>' : ''}</td>
            <td class="text-end">${money(f.base)}</td><td class="text-end">${money(f.adicional)}</td>
            <td class="text-end">${f.descuento_establecimiento > 0 ? '−' + money(f.descuento_establecimiento) : '—'}</td>
            <td class="text-end">${money(f.anual)}</td><td class="text-end">${money(f.semestral)}</td><td class="text-end">${money(f.mensual)}</td><td class="text-end">${money(f.unico)}</td></tr>`).join('')
            || '<tr><td colspan="8" class="text-muted">Sin establecimientos.</td></tr>';
        let pie = '';
        if (c.usuarios_adicionales > 0) {
            pie += `<tr><td colspan="4">Usuarios adicionales (${c.usuarios_adicionales})</td><td class="text-end">${money(c.usuarios_anual)}</td><td class="text-end">${money(c.usuarios_semestral)}</td><td class="text-end">${money(c.usuarios_mensual)}</td><td></td></tr>`;
        }
        if (c.descuento_vendedor_pct > 0) {
            pie += `<tr><td colspan="4">Descuento comercial (${pct(c.descuento_vendedor_pct)})</td><td class="text-end">−${money(c.descuento_vendedor_anual)}</td><td class="text-end">−${money(c.descuento_vendedor_semestral)}</td><td class="text-end">−${money(c.descuento_vendedor_mensual)}</td><td></td></tr>`;
        }
        pie += `<tr class="table-light"><td colspan="4">Total</td><td class="text-end">${money(c.total_anual)}</td><td class="text-end">${money(c.total_semestral)}</td><td class="text-end">${money(c.total_mensual)}</td><td class="text-end">${money(c.total_unico)}</td></tr>`;
        $('cpResumenPie').innerHTML = pie;
        $('cpResumenNota').innerHTML = `Valores sin IVA. Semestral = anual ÷ 2 × ${(1 + (p.recargo_semestral_pct || 0) / 100).toFixed(2)}. Mensual = anual ÷ 12 × ${(1 + (p.recargo_mensual_pct || 0) / 100).toFixed(2)}. `
            + `Descuento por establecimiento adicional: ${pct(p.descuento_establecimiento_pct)} sobre los módulos base, solo en plan anual; el de plan base más caro paga completo.`;
    }

    ['cp_usuarios', 'cp_descuento_vendedor_pct', 'cp_modalidad'].forEach((id) => $(id)?.addEventListener('input', marcarSucio));
    modalEl?.querySelectorAll('#cp-tab-cliente .cp-editable').forEach((el) => el.addEventListener('change', () => { sucio = true; }));

    // ── SRI ────────────────────────────────────────────────────────────────
    $('cpBtnSri')?.addEventListener('click', async () => {
        const num = ($('cp_identificacion').value || '').replace(/\D/g, '');
        $('cp_identificacion').value = num;
        const btn = $('cpBtnSri');
        btn.disabled = true;
        $('cpSriInfo').innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Consultando…</span>';
        try {
            const j = await getJson(`${URL}/consultarSriAjax?identificacion=${encodeURIComponent(num)}`);
            if (!j.ok) { $('cpSriInfo').innerHTML = `<span class="text-danger">${esc(j.error)}</span>`; return; }
            const d = j.data;
            ['razon_social', 'nombre_comercial', 'tipo_contribuyente', 'direccion', 'cod_prov', 'cod_ciudad'].forEach((k) => { if (d[k]) $('cp_' + k).value = d[k]; });
            // Obligado a llevar contabilidad: '1' / '0' según el SRI (o sociedad); vacío = lo elige el vendedor.
            if (d.obligado_contabilidad === '1' || d.obligado_contabilidad === '0') $('cp_obligado_contabilidad').value = d.obligado_contabilidad;
            if (d.correo && !$('cp_correo').value) $('cp_correo').value = d.correo;
            if (d.telefono && !$('cp_telefono').value) $('cp_telefono').value = d.telefono;
            const abiertos = (d.establecimientos || []).filter((x) => x.estado === 'ABIERTO' && x.codigo);
            let info = j.cliente ? `<span class="text-success"><i class="bi bi-person-check me-1"></i>Ya es cliente: ${esc(j.cliente.nombre)}</span>` : '<span class="text-muted">No es cliente todavía: se registrará al generar la proforma.</span>';
            if (j.sri_ok) info += ` · <span class="text-muted">${abiertos.length} establecimiento(s) abierto(s) en el SRI</span>`;
            if (j.aviso) info += `<div class="text-warning">${esc(j.aviso)}</div>`;
            $('cpSriInfo').innerHTML = info;
            sucio = true;
            if (abiertos.length) await ofrecerEstablecimientos(abiertos);
            recalcular(true);
        } catch (e) {
            $('cpSriInfo').innerHTML = '<span class="text-danger">Error de conexión al consultar el SRI.</span>';
        } finally {
            btn.disabled = false;
        }
    });

    async function ofrecerEstablecimientos(abiertos) {
        const lista = abiertos.map((x) => `${x.codigo}${x.nombre ? ' — ' + x.nombre : ''}`).join('<br>');
        let usar = true;
        if (window.Swal) {
            const r = await Swal.fire({
                icon: 'info', title: `El RUC tiene ${abiertos.length} establecimiento(s) abierto(s)`,
                html: `<div class="small text-start">${lista}</div><div class="small mt-2">¿Cargarlos en la cotización? Luego puede quitar los que no se contraten.</div>`,
                showCancelButton: true, confirmButtonText: 'Cargarlos', cancelButtonText: 'No',
            });
            usar = r.isConfirmed;
        }
        if (!usar) return;
        // Conserva los módulos ya elegidos en el primer establecimiento como punto de partida.
        const plantilla = establecimientos[0]?.elegidos?.slice() || [];
        const paqPlantilla = establecimientos[0]?.id_paquete || null;
        establecimientos = abiertos.map((x) => ({ codigo: x.codigo, nombre: x.nombre || '', direccion: x.direccion || '', id_paquete: paqPlantilla, elegidos: plantilla.slice() }));
    }

    // ── Guardar / eliminar / proforma ──────────────────────────────────────
    async function guardar(silencioso) {
        if (guardando) return null;
        guardando = true;
        const btn = $('cpBtnGuardar');
        if (btn) btn.disabled = true;
        try {
            const d = leerFormulario();
            const j = await postJson(`${URL}/guardarAjax`, d);
            if (!j.ok) { alerta('warning', 'No se guardó', j.error); return null; }
            // El id queda puesto ANTES de reactivar el botón: el siguiente clic actualiza, no crea otra (§8).
            $('cp_id').value = j.id;
            tokenGuardado = '';
            sucio = false;
            const fresca = await getJson(`${URL}/getCotizacionAjax?id=${j.id}`);
            if (fresca.ok) { estado = fresca.data; pintarEncabezado(); aplicarModoLectura(); }
            if (!silencioso) {
                window.Swal ? Swal.fire({ icon: 'success', title: j.msg, timer: 1600, showConfirmButton: false }) : null;
            }
            window.CP_buscar(paginaActual);
            return j.id;
        } catch (e) {
            alerta('error', 'Error de conexión', 'Revise el listado antes de volver a guardar: puede que sí se haya guardado.');
            return null;
        } finally {
            guardando = false;
            if (btn) btn.disabled = false;
        }
    }
    $('cpBtnGuardar')?.addEventListener('click', () => guardar(false));

    // PDF: sale de lo guardado; si hay cambios sin guardar (o es nueva), primero se guarda.
    let generandoPdf = false;
    $('cpBtnPdf')?.addEventListener('click', async () => {
        if (generandoPdf) return;
        generandoPdf = true;
        try {
            let id = parseInt($('cp_id').value || 0, 10);
            if (editable() && (!id || sucio)) {
                if (!(estado ? CFG.perm.actualizar : CFG.perm.crear)) {
                    return alerta('info', 'Guarde primero', 'Guarde la cotización para generar su PDF.');
                }
                id = await guardar(true);
                if (!id) return;
            }
            if (!id) return alerta('info', 'Guarde primero', 'Guarde la cotización para generar su PDF.');
            window.CMG_pdfDocumento(`${URL}/pdf?id=${id}`);
        } finally {
            generandoPdf = false;
        }
    });

    $('cpBtnEliminar')?.addEventListener('click', async () => {
        const id = parseInt($('cp_id').value || 0, 10);
        if (!id) return;
        if (window.Swal) {
            const r = await Swal.fire({ icon: 'warning', title: '¿Eliminar esta cotización?', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#d33', reverseButtons: true });
            if (!r.isConfirmed) return;
        }
        const j = await postJson(`${URL}/eliminarAjax`, { id });
        if (!j.ok) return alerta('warning', 'No se eliminó', j.error);
        getModal().hide();
        window.CP_buscar(paginaActual);
    });

    let generando = false;
    $('cpBtnProforma')?.addEventListener('click', async () => {
        if (generando) return;
        generando = true;
        const btn = $('cpBtnProforma');
        btn.disabled = true;
        try {
            // Primero se guarda lo que está en pantalla: la proforma sale de lo guardado.
            let id = parseInt($('cp_id').value || 0, 10);
            if (!id || sucio) {
                id = await guardar(true);
                if (!id) return;
            }
            if (!($('cp_correo').value || '').trim()) {
                bootstrap.Tab.getOrCreateInstance($('cp-tab-cliente-btn')).show();
                return alerta('warning', 'Falta el correo', 'Escriba el correo del cliente: hace falta para registrarlo y enviarle la proforma.');
            }
            const s = await getJson(`${URL}/getSeriesAjax`);
            const series = s.ok ? s.data : [];
            if (!series.length) return alerta('warning', 'Sin serie de proformas', 'No hay una serie activa con secuencial de Proformas configurado.');
            let idPunto = series[0].id;
            if (series.length > 1 && window.Swal) {
                const opts = {};
                series.forEach((x) => { opts[x.id] = x.texto; });
                const r = await Swal.fire({ title: 'Serie de la proforma', input: 'select', inputOptions: opts, inputValue: String(idPunto), showCancelButton: true, confirmButtonText: 'Generar', cancelButtonText: 'Cancelar' });
                if (!r.isConfirmed) return;
                idPunto = parseInt(r.value, 10);
            } else if (window.Swal) {
                const r = await Swal.fire({ icon: 'question', title: '¿Generar la proforma?', text: `Serie ${series[0].texto}. Se registrará el cliente y la oportunidad en el CRM si no existen.`, showCancelButton: true, confirmButtonText: 'Generar', cancelButtonText: 'Cancelar' });
                if (!r.isConfirmed) return;
            }
            const j = await postJson(`${URL}/generarProformaAjax`, { id, id_punto_emision: idPunto });
            if (!j.ok) return alerta('warning', 'No se generó la proforma', j.error);
            const fresca = await getJson(`${URL}/getCotizacionAjax?id=${id}`);
            if (fresca.ok) { estado = fresca.data; estado._url_proforma = fresca.data.url_proforma || j.url_proforma; }
            pintarEncabezado();
            aplicarModoLectura();
            aviso('success', `<i class="bi bi-check-circle me-1"></i>${esc(j.msg)}${j.aviso ? '<br><span class="text-warning">' + esc(j.aviso) + '</span>' : ''}`
                + ` <a href="${esc(j.url_proforma)}" class="ms-1">Abrir en Proformas</a>`);
            window.CP_buscar(paginaActual);
        } catch (e) {
            alerta('error', 'Error de conexión', 'Revise Proformas antes de reintentar: puede que la proforma sí se haya creado. Un reintento no crea otra.');
        } finally {
            generando = false;
            btn.disabled = false;
        }
    });

    modalEl?.addEventListener('hide.bs.modal', (ev) => {
        if (sucio && editable() && !guardando && !confirm('Hay cambios sin guardar. ¿Cerrar de todos modos?')) {
            ev.preventDefault();
        }
    });

    // ── Documentos para vendedores (botón «Documentos» y pestaña de Configuración) ──
    function pintarDocumentos(cont, docs) {
        const descripcion = {
            presentacion: 'Qué decir: quiénes somos, el cliente, módulos, precios, membresía, preguntas frecuentes y proceso de venta.',
            capacitacion: 'Igual a la anterior, pero con las respuestas de la evaluación. Solo para quien dicta la capacitación: no entregarla a los vendedores.',
            capacitacion_vendedor: 'Cómo aprenderlo: programa, guion de demo, ejercicios del Cotizador, objeciones, reglas, evaluación y comisiones.',
        };
        cont.innerHTML = docs.map((d) => `
            <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 d-flex gap-3 align-items-start">
                    <i class="bi bi-file-earmark-pdf text-danger fs-3"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${esc(d.titulo)}</div>
                        <div class="small text-muted">${d.version ? 'Versión ' + esc(d.version) : ''}${d.fecha ? ' · ' + esc(d.fecha) : ''}</div>
                        <div class="small mt-1">${esc(descripcion[d.clave] || '')}</div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" onclick="CP_descargarDocumento('${esc(d.clave)}')">
                        <i class="bi bi-download"></i> Descargar</button>
                </div>
            </div>`).join('') || '<div class="col-12 small text-muted">No hay documentos disponibles.</div>';
    }
    window.CP_abrirDocumentos = async function () {
        $('cpDocumentos').innerHTML = '<div class="col-12 small text-muted">Cargando…</div>';
        bootstrap.Modal.getOrCreateInstance($('modalCPDocumentos')).show();
        try {
            const j = await getJson(`${URL}/getDocumentosAjax`);
            if (!j.ok) throw new Error(j.error || 'No se pudo cargar.');
            pintarDocumentos($('cpDocumentos'), j.documentos || []);
        } catch (e) {
            $('cpDocumentos').innerHTML = `<div class="col-12 small text-danger">${esc(e.message)}</div>`;
        }
    };
    window.CP_descargarDocumento = function (clave) {
        CMG_descargar(`${URL}/descargarDocumento?doc=${encodeURIComponent(clave)}`);
    };

    // ═════════════════════════ Configuración (niveles 2 y 3) ═════════════════════════
    const cfgEl = $('modalCPConfig');
    if (!cfgEl) return;

    /**
     * Modal de detalle que se abre ENCIMA de la Configuración. Todos los .modal
     * comparten z-index (app.css), así que este y su fondo se suben un nivel.
     */
    function modalSobreConfig(id, idFoco) {
        const el = $(id);
        el.addEventListener('show.bs.modal', () => el.style.setProperty('z-index', '5070', 'important'));
        el.addEventListener('shown.bs.modal', () => {
            const fondos = document.querySelectorAll('.modal-backdrop');
            if (fondos.length > 1) fondos[fondos.length - 1].style.setProperty('z-index', '5065', 'important');
            $(idFoco)?.focus();
        });
        // Este archivo carga ANTES que Bootstrap (el layout lo incluye al final):
        // la instancia se pide recién al abrir/cerrar, nunca al cargar la página.
        const inst = () => bootstrap.Modal.getOrCreateInstance(el);
        return { show: () => inst().show(), hide: () => inst().hide() };
    }
    let cfgModal = null;
    let cfgItems = [];
    let cfgSubmodulos = [];
    let cfgPaquetes = [];
    let productosMapa = {}; // texto del datalist → id

    window.CP_abrirConfig = async function () {
        await cargarConfig();
        (cfgModal ||= new bootstrap.Modal(cfgEl)).show();
    };

    async function cargarConfig() {
        const j = await getJson(`${URL}/getConfigAjax`);
        if (!j.ok) return alerta('error', 'Configuración', j.error);
        const c = j.config;
        ['recargo_mensual_pct', 'recargo_semestral_pct', 'descuento_establecimiento_pct', 'usuarios_por_establecimiento', 'precio_usuario_adicional_anual', 'descuento_vendedor_max_pct', 'dias_vigencia']
            .forEach((k) => { $('cfg_' + k).value = c[k]; });
        $('cfg_permitir_descuento_vendedor').checked = !!c.permitir_descuento_vendedor;
        $('cfg_id_producto_resumen_anual').value = c.id_producto_resumen_anual || '';
        $('cfg_producto_anual_txt').value = c.producto_resumen_anual || '';
        $('cfg_id_producto_resumen_mensual').value = c.id_producto_resumen_mensual || '';
        $('cfg_producto_mensual_txt').value = c.producto_resumen_mensual || '';
        $('cfg_id_producto_resumen_semestral').value = c.id_producto_resumen_semestral || '';
        $('cfg_producto_semestral_txt').value = c.producto_resumen_semestral || '';
        if (c.producto_resumen_semestral) productosMapa[c.producto_resumen_semestral] = c.id_producto_resumen_semestral;
        if (c.producto_resumen_anual) productosMapa[c.producto_resumen_anual] = c.id_producto_resumen_anual;
        if (c.producto_resumen_mensual) productosMapa[c.producto_resumen_mensual] = c.id_producto_resumen_mensual;
        cfgItems = j.items || [];
        cfgPaquetes = j.paquetes || [];
        cfgSubmodulos = j.submodulos || [];
        pintarDocumentos($('cfgDocumentos'), j.documentos || []);
        pintarCfgItems();
        pintarCfgPaquetes();
    }

    // ── Lista con casillas (paquetes y «Requiere») ─────────────────────────
    /**
     * Pinta en `cont` una fila con casilla por ítem, agrupadas por área, para elegir
     * varios sin Ctrl + clic. `marcados`: ids ya elegidos. `excluir`: id que no se ofrece.
     */
    function pintarCasillas(cont, marcados, excluir) {
        const sel = new Set((marcados || []).map(Number));
        const grupos = {};
        cfgItems.forEach((it) => {
            if (excluir && it.id === Number(excluir)) return;
            (grupos[it.tipo === 'servicio' ? 'Servicios' : (it.grupo || 'Otros')] ||= []).push(it);
        });
        cont.innerHTML = Object.keys(grupos).sort().map((g) =>
            `<div class="cas-grupo"><div class="px-2 py-1 bg-light border-bottom small fw-semibold text-secondary">${esc(g)}</div>`
            + grupos[g].map((it) => `<label class="cas-fila d-flex align-items-center gap-2 px-2 py-1 border-bottom small mb-0" role="button" data-texto="${esc((g + ' ' + it.nombre).toLowerCase())}">
                    <input type="checkbox" class="form-check-input mt-0 cas-check" value="${it.id}" ${sel.has(it.id) ? 'checked' : ''}>
                    <span class="flex-grow-1">${esc(it.nombre)}${it.cobro === 'unico' ? ' <span class="text-muted">(pago único)</span>' : ''}${it.estado !== 'activo' ? ' <span class="text-muted">(inactivo)</span>' : ''}</span>
                    <span class="text-muted">${money(it.precio_anual)}</span>
                </label>`).join('')
            + '</div>').join('') || '<div class="small text-muted p-2">No hay otros módulos cargados.</div>';
    }
    function idsMarcados(cont) {
        return Array.from(cont.querySelectorAll('.cas-check:checked')).map((c) => Number(c.value));
    }
    /** Buscador de una lista con casillas: oculta filas y grupos que no coinciden; no desmarca nada. */
    function filtrarCasillas(cont, texto) {
        const q = (texto || '').toLowerCase().trim();
        cont.querySelectorAll('.cas-grupo').forEach((g) => {
            let visibles = 0;
            g.querySelectorAll('.cas-fila').forEach((f) => {
                const ver = !q || f.dataset.texto.includes(q);
                f.classList.toggle('d-none', !ver);
                if (ver) visibles++;
            });
            g.classList.toggle('d-none', visibles === 0);
        });
    }
    function contarCasillas(cont, etiqueta) {
        const n = idsMarcados(cont).length;
        etiqueta.textContent = n ? `(${n} marcados)` : '';
    }

    // ── Paquetes (configuración) ───────────────────────────────────────────
    /** Suma anual de los módulos de un paquete y su valor con el descuento (los únicos no se descuentan). */
    function valorPaquete(ids, pctDesc, tipo, precioFijo) {
        let suma = 0, desc = 0, unico = 0;
        ids.forEach((id) => {
            const it = cfgItems.find((x) => x.id === Number(id));
            if (!it) return;
            const precio = Number(it.precio_anual || 0);
            suma += precio;
            if (it.cobro === 'unico') unico = r2(unico + precio);
            else desc = r2(desc + r2(precio * Number(pctDesc || 0) / 100));
        });
        // Precio fijo: el paquete vale su precio; los pagos únicos del paquete van aparte.
        if (tipo === 'fijo') return { suma: r2(suma), desc: r2(suma - unico - Number(precioFijo || 0)), total: r2(Number(precioFijo || 0) + unico) };
        return { suma: r2(suma), desc, total: r2(suma - desc) };
    }

    function pintarCfgPaquetes() {
        $('cfgPaquetes').querySelector('tbody').innerHTML = cfgPaquetes.map((p) => {
            const v = valorPaquete(p.items || [], p.descuento_pct, p.tipo_precio, p.precio_anual);
            const nombres = (p.items || []).map((id) => cfgItems.find((x) => x.id === Number(id))?.nombre).filter(Boolean);
            return `<tr role="button" onclick="CP_editarPaquete(${p.id})">
                <td class="fw-medium">${esc(p.nombre)}${p.descripcion ? `<div class="small text-muted">${esc(p.descripcion)}</div>` : ''}</td>
                <td class="small cp-celda" style="max-width:260px;" title="${esc(nombres.join(', '))}">${nombres.length} · ${esc(nombres.join(', '))}</td>
                <td class="text-end">${money(v.suma)}</td>
                <td class="text-end">${p.tipo_precio === 'fijo' ? '<span class="badge bg-primary bg-opacity-10 text-primary">Precio fijo</span>' : (Number(p.descuento_pct) > 0 ? '−' + pct(p.descuento_pct) : '—')}</td>
                <td class="text-end fw-semibold">${money(v.total)}</td>
                <td class="text-center">${p.estado === 'activo' ? '<span class="badge bg-success bg-opacity-10 text-success">Activo</span>' : '<span class="badge bg-secondary bg-opacity-10 text-secondary">Inactivo</span>'}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="text-muted text-center py-3">Aún no hay paquetes. Cree uno con «Nuevo paquete» (p. ej. Emprendedor).</td></tr>';
    }

    function actualizarValorPaquete() {
        const ids = idsMarcados($('pq_items'));
        contarCasillas($('pq_items'), $('pq_contador'));
        const fijo = $('pq_tipo_precio').value === 'fijo';
        $('pq_wrap_descuento').classList.toggle('d-none', fijo);
        $('pq_wrap_precio').classList.toggle('d-none', !fijo);
        $('pq_nota').textContent = fijo
            ? 'Los módulos del paquete van incluidos; los pagos únicos del paquete se cobran aparte.'
            : 'Los pagos únicos del paquete no llevan el descuento.';
        const v = valorPaquete(ids, parseFloat($('pq_descuento_pct').value || 0), $('pq_tipo_precio').value, parseFloat($('pq_precio_anual').value || 0));
        $('pq_suma').textContent = money(v.suma);
        $('pq_desc_lbl').textContent = fijo ? 'Ahorro frente a la suma' : 'Descuento';
        $('pq_desc').textContent = (v.desc < 0 ? '+' : '−') + money(Math.abs(v.desc));
        $('pq_total').textContent = money(v.total);
    }

    // El detalle del paquete es un modal propio, encima de la Configuración.
    const modalPaquete = modalSobreConfig('modalCPPaquete', 'pq_nombre');

    function abrirEditorPaquete(p) {
        $('pq_titulo').textContent = p.id ? `Paquete ${p.nombre || ''}` : 'Nuevo paquete';
        $('pq_id').value = p.id || '';
        $('pq_nombre').value = p.nombre || '';
        $('pq_tipo_precio').value = p.tipo_precio === 'fijo' ? 'fijo' : 'descuento';
        $('pq_descuento_pct').value = p.descuento_pct ?? 0;
        $('pq_precio_anual').value = p.precio_anual ?? 0;
        $('pq_orden').value = p.orden || 0;
        $('pq_activo').checked = (p.estado || 'activo') === 'activo';
        $('pq_descripcion').value = p.descripcion || '';
        // Una fila con casilla por módulo, agrupadas por área (sin Ctrl + clic).
        pintarCasillas($('pq_items'), p.items || []);
        $('pq_buscar').value = '';
        $('cfgBtnEliminarPaquete').classList.toggle('d-none', !p.id);
        actualizarValorPaquete();
        modalPaquete.show();
    }
    window.CP_editarPaquete = function (id) {
        const p = cfgPaquetes.find((x) => Number(x.id) === Number(id));
        if (p) abrirEditorPaquete(p);
    };
    $('cfgBtnNuevoPaquete').addEventListener('click', () => {
        if (!cfgItems.length) return alerta('info', 'Catálogo vacío', 'Primero cargue módulos con su precio en la pestaña Catálogo de precios.');
        abrirEditorPaquete({ estado: 'activo', tipo_precio: 'fijo', descuento_pct: 0, precio_anual: 0, items: [] });
    });
    $('pq_items').addEventListener('change', actualizarValorPaquete);
    $('pq_buscar').addEventListener('input', () => filtrarCasillas($('pq_items'), $('pq_buscar').value));
    $('pq_descuento_pct').addEventListener('input', actualizarValorPaquete);
    $('pq_precio_anual').addEventListener('input', actualizarValorPaquete);
    $('pq_tipo_precio').addEventListener('change', actualizarValorPaquete);

    let guardandoPaquete = false;
    $('cfgBtnGuardarPaquete').addEventListener('click', async () => {
        if (guardandoPaquete) return;
        guardandoPaquete = true;
        try {
            const j = await postJson(`${URL}/guardarPaqueteAjax`, {
                id: parseInt($('pq_id').value || 0, 10) || 0,
                nombre: $('pq_nombre').value,
                descripcion: $('pq_descripcion').value,
                tipo_precio: $('pq_tipo_precio').value,
                descuento_pct: $('pq_descuento_pct').value,
                precio_anual: $('pq_precio_anual').value,
                orden: parseInt($('pq_orden').value || 0, 10) || 0,
                estado: $('pq_activo').checked ? 'activo' : 'inactivo',
                items: idsMarcados($('pq_items')),
            });
            if (!j.ok) return alerta('warning', 'No se guardó', j.error);
            modalPaquete.hide();
            await cargarConfig();
            bootstrap.Tab.getOrCreateInstance(document.querySelector('a[href="#cfg-tab-paquetes"]')).show();
        } catch (e) {
            alerta('error', 'Error de conexión', 'No se pudo guardar el paquete.');
        } finally {
            guardandoPaquete = false;
        }
    });
    $('cfgBtnEliminarPaquete').addEventListener('click', async () => {
        const id = parseInt($('pq_id').value || 0, 10);
        if (!id) return;
        if (window.Swal) {
            const r = await Swal.fire({ icon: 'warning', title: '¿Eliminar este paquete?', text: 'Las cotizaciones ya hechas conservan su paquete. Si solo quiere dejar de ofrecerlo, márquelo inactivo.', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#d33', reverseButtons: true });
            if (!r.isConfirmed) return;
        }
        const j = await postJson(`${URL}/eliminarPaqueteAjax`, { id });
        if (!j.ok) return alerta('warning', 'No se eliminó', j.error);
        modalPaquete.hide();
        await cargarConfig();
        bootstrap.Tab.getOrCreateInstance(document.querySelector('a[href="#cfg-tab-paquetes"]')).show();
    });

    /** Ítems cargados + submódulos del menú que aún no están en el catálogo (a $0, «Sin cargar»). */
    function filasCatalogo() {
        const usados = new Set(cfgItems.filter((it) => it.id_submodulo).map((it) => Number(it.id_submodulo)));
        const filas = cfgItems.map((it) => ({ ...it, _k: 'i' + it.id }));
        cfgSubmodulos.forEach((sm) => {
            if (usados.has(sm.id_submodulo)) return;
            filas.push({
                _k: 's' + sm.id_submodulo, _virtual: true, tipo: 'modulo', id_submodulo: sm.id_submodulo, ruta: sm.ruta,
                nombre: sm.nombre, grupo: sm.grupo, precio_anual: 0, cobro: 'recurrente',
                aplica_descuento_establecimiento: true, por_defecto: false, sin_costo_dependencia: false, estado: 'activo', requiere: [],
            });
        });
        const orden = (x) => [x.tipo === 'servicio' ? 1 : 0, (x.grupo || '').toLowerCase(), (x.nombre || '').toLowerCase()];
        return filas.sort((x, y) => {
            const a = orden(x), b = orden(y);
            for (let i = 0; i < a.length; i++) { if (a[i] < b[i]) return -1; if (a[i] > b[i]) return 1; }
            return 0;
        });
    }

    function pintarCfgItems() {
        const f = ($('cfgFiltro').value || '').toLowerCase();
        const ver = $('cfgVer').value;
        const nombres = {};
        cfgItems.forEach((it) => { nombres[it.id] = it.nombre; });
        const todas = filasCatalogo();
        const filas = todas.filter((it) => (!f || `${it.grupo || ''} ${it.nombre}`.toLowerCase().includes(f))
            && (ver === 'todos' || (ver === 'cargados' ? !it._virtual : it._virtual)));
        const sinCargar = todas.filter((it) => it._virtual).length;
        $('cfgResumen').textContent = `${todas.length - sinCargar} cargados · ${sinCargar} sin cargar`;

        // Conservar el scroll y el campo con foco al repintar (para escribir precios seguidos con Tab).
        const caja = $('cfgItems').closest('.table-responsive');
        const scroll = caja ? caja.scrollTop : 0;
        const foco = document.activeElement?.dataset?.k || null;

        $('cfgItems').querySelector('tbody').innerHTML = filas.map((it) => {
            const estado = it._virtual
                ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Sin cargar</span>'
                : (it.estado === 'activo' ? '<span class="badge bg-success bg-opacity-10 text-success">Activo</span>' : '<span class="badge bg-secondary bg-opacity-10 text-secondary">Inactivo</span>');
            const accion = it._virtual
                ? `<button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="CP_cargarItem('${it._k}')" title="Cargar al catálogo con el precio escrito">Cargar</button>`
                : `<button type="button" class="btn btn-link btn-sm p-0" onclick="CP_editarItem('${it._k}')" title="Editar"><i class="bi bi-pencil-square"></i></button>`;
            return `<tr class="${it._virtual ? 'text-muted' : ''}" role="button" data-k="${it._k}" title="Clic para ver el detalle">
                <td class="small">${esc(it.tipo === 'servicio' ? 'Servicios' : (it.grupo || ''))}</td>
                <td class="fw-medium">${esc(it.nombre)}</td>
                <td class="small">${it.cobro === 'unico' ? 'Una vez' : 'Cada periodo'}</td>
                <td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end py-0" style="height:24px;"
                        data-k="${it._k}" value="${Number(it.precio_anual || 0).toFixed(2)}" onchange="CP_precioCatalogo('${it._k}', this)"></td>
                <td class="text-center small">${it.cobro === 'unico' ? '—' : (it.aplica_descuento_establecimiento ? 'Base' : 'Adicional')}</td>
                <td class="text-center">${it.sin_costo_dependencia ? '<i class="bi bi-check-lg text-success" title="Sin costo cuando lo requiere otro módulo"></i>' : ''}</td>
                <td class="small cp-celda" style="max-width:200px;" title="${esc((it.requiere || []).map((r) => nombres[r] || r).join(', '))}">${esc((it.requiere || []).map((r) => nombres[r] || r).join(', '))}</td>
                <td class="text-center">${estado}</td>
                <td class="text-center">${accion}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="9" class="text-muted text-center py-3">No hay módulos con ese filtro.</td></tr>';

        if (caja) caja.scrollTop = scroll;
        if (foco) {
            const el = $('cfgItems').querySelector(`input[data-k="${foco}"]`);
            if (el) el.focus();
        }
    }
    $('cfgVer').addEventListener('change', pintarCfgItems);

    function filaPorClave(k) {
        return filasCatalogo().find((x) => x._k === k) || null;
    }

    /** Datos completos de un ítem en el formato de guardarItemAjax. */
    function payloadItem(it, cambios) {
        const d = {
            id: it._virtual ? 0 : it.id, tipo: it.tipo, id_submodulo: it.id_submodulo || 0, ruta: it.ruta || '',
            nombre: it.nombre, grupo: it.grupo || '', descripcion: it.descripcion || '', precio_anual: it.precio_anual,
            cobro: it.cobro, aplica_descuento_establecimiento: !!it.aplica_descuento_establecimiento,
            por_defecto: false, sin_costo_dependencia: !!it.sin_costo_dependencia, estado: it.estado || 'activo', orden: it.orden || 0,
            id_producto: it.id_producto || 0, requiere: (it.requiere || []).map(Number),
        };
        return Object.assign(d, cambios || {});
    }

    // Los guardados van en cola: escribir precios seguidos (Tab) no pierde ninguno.
    let colaGuardado = Promise.resolve();
    function guardarFila(k, precio) {
        colaGuardado = colaGuardado.then(() => guardarFilaAhora(k, precio)).catch(() => {});
        return colaGuardado;
    }
    async function guardarFilaAhora(k, precio) {
        let it = filaPorClave(k);
        // Un submódulo que se acaba de cargar ya tiene clave de ítem.
        if (!it && k.startsWith('s')) {
            const real = cfgItems.find((x) => Number(x.id_submodulo) === Number(k.slice(1)));
            it = real ? filaPorClave('i' + real.id) : null;
        }
        if (!it) return;
        const valor = precio === undefined ? Number(it.precio_anual || 0) : precio;
        if (!(valor >= 0)) return alerta('warning', 'Precio no válido', 'El precio debe ser un número mayor o igual a cero.');
        try {
            const j = await postJson(`${URL}/guardarItemAjax`, payloadItem(it, { precio_anual: valor }));
            if (!j.ok) { alerta('warning', 'No se guardó', j.error); pintarCfgItems(); return; }
            if (it._virtual) {
                cfgItems.push(payloadItem(it, { id: j.id, precio_anual: valor, estado: 'activo' }));
                // El foco sigue en la fila: su clave cambia de submódulo a ítem.
                const el = document.activeElement;
                if (el?.dataset?.k === it._k) el.dataset.k = 'i' + j.id;
            } else {
                const real = cfgItems.find((x) => x.id === it.id);
                if (real) real.precio_anual = valor;
            }
            pintarCfgItems();
        } catch (e) {
            alerta('error', 'Error de conexión', 'No se guardó el precio.');
        }
    }
    window.CP_precioCatalogo = function (k, el) {
        guardarFila(k, r2(parseFloat(el.value || 0)));
    };
    window.CP_cargarItem = function (k) {
        const el = $('cfgItems').querySelector(`input[data-k="${k}"]`);
        guardarFila(k, el ? r2(parseFloat(el.value || 0)) : 0);
    };
    $('cfgFiltro').addEventListener('input', pintarCfgItems);
    $('cfgItems').querySelector('tbody').addEventListener('click', (ev) => {
        if (ev.target.closest('input, button, a, select')) return; // precio en línea y botones siguen igual
        const tr = ev.target.closest('tr[data-k]');
        if (tr) window.CP_editarItem(tr.dataset.k);
    });

    // El detalle del ítem es un modal propio, encima de la Configuración.
    const modalItem = modalSobreConfig('modalCPItem', 'it_nombre');

    function abrirEditor(it) {
        $('it_titulo').textContent = it.id ? (it.nombre || 'Detalle del módulo') : (it.tipo === 'servicio' ? 'Nuevo servicio' : `Cargar módulo: ${it.nombre || ''}`);
        $('it_id').value = it.id || '';
        $('it_tipo').value = it.tipo;
        $('it_id_submodulo').value = it.id_submodulo || '';
        $('it_ruta').value = it.ruta || '';
        $('it_nombre').value = it.nombre || '';
        $('it_grupo').value = it.grupo || '';
        $('it_precio_anual').value = it.precio_anual ?? 0;
        $('it_cobro').value = it.cobro || 'recurrente';
        $('it_aplica_descuento_establecimiento').checked = it.aplica_descuento_establecimiento !== false;
        $('it_sin_costo_dependencia').checked = !!it.sin_costo_dependencia;
        $('it_activo').checked = (it.estado || 'activo') === 'activo';
        $('it_orden').value = it.orden || 0;
        $('it_descripcion').value = it.descripcion || '';
        $('it_id_producto').value = it.id_producto || '';
        $('it_producto_txt').value = it.producto_nombre || '';
        if (it.producto_nombre) productosMapa[it.producto_nombre] = it.id_producto;
        $('it_origen').textContent = it.tipo === 'modulo' ? `Submódulo del menú: ${it.ruta || ''}` : 'Servicio (concepto libre)';
        pintarCasillas($('it_requiere'), it.requiere || [], it.id);
        $('it_requiere_buscar').value = '';
        contarCasillas($('it_requiere'), $('it_requiere_contador'));
        $('cfgBtnEliminarItem').classList.toggle('d-none', !it.id);
        actualizarEditorCobro();
        modalItem.show();
    }
    function actualizarEditorCobro() {
        const unico = $('it_cobro').value === 'unico';
        $('it_aplica_descuento_establecimiento').disabled = unico;
        if (unico) $('it_aplica_descuento_establecimiento').checked = false;
        $('it_producto_wrap').classList.toggle('d-none', !unico);
    }
    $('it_cobro').addEventListener('change', actualizarEditorCobro);
    $('it_requiere').addEventListener('change', () => contarCasillas($('it_requiere'), $('it_requiere_contador')));
    $('it_requiere_buscar').addEventListener('input', () => filtrarCasillas($('it_requiere'), $('it_requiere_buscar').value));

    window.CP_editarItem = function (k) {
        const it = filaPorClave(k);
        if (!it) return;
        const datos = { ...it };
        if (it._virtual) delete datos.id; // aún no está en el catálogo: el editor lo crea
        abrirEditor(datos);
    };
    $('cfgBtnAgregarServicio').addEventListener('click', () => {
        abrirEditor({ tipo: 'servicio', nombre: '', grupo: 'Servicios', precio_anual: 0, cobro: 'unico', aplica_descuento_establecimiento: false, estado: 'activo' });
    });

    let guardandoItem = false;
    $('cfgBtnGuardarItem').addEventListener('click', async () => {
        if (guardandoItem) return;
        guardandoItem = true;
        try {
            const prodTxt = $('it_producto_txt').value.trim();
            const d = {
                id: parseInt($('it_id').value || 0, 10) || 0,
                tipo: $('it_tipo').value,
                id_submodulo: parseInt($('it_id_submodulo').value || 0, 10) || 0,
                ruta: $('it_ruta').value,
                nombre: $('it_nombre').value,
                grupo: $('it_grupo').value,
                descripcion: $('it_descripcion').value,
                precio_anual: $('it_precio_anual').value,
                cobro: $('it_cobro').value,
                aplica_descuento_establecimiento: $('it_aplica_descuento_establecimiento').checked,
                por_defecto: false,
                sin_costo_dependencia: $('it_sin_costo_dependencia').checked,
                estado: $('it_activo').checked ? 'activo' : 'inactivo',
                orden: parseInt($('it_orden').value || 0, 10) || 0,
                id_producto: prodTxt ? (productosMapa[prodTxt] || parseInt($('it_id_producto').value || 0, 10) || 0) : 0,
                requiere: idsMarcados($('it_requiere')),
            };
            const j = await postJson(`${URL}/guardarItemAjax`, d);
            if (!j.ok) return alerta('warning', 'No se guardó', j.error);
            modalItem.hide();
            await cargarConfig();
        } catch (e) {
            alerta('error', 'Error de conexión', 'No se pudo guardar el ítem.');
        } finally {
            guardandoItem = false;
        }
    });
    $('cfgBtnEliminarItem').addEventListener('click', async () => {
        const id = parseInt($('it_id').value || 0, 10);
        if (!id) return;
        if (window.Swal) {
            const r = await Swal.fire({ icon: 'warning', title: '¿Quitar este ítem del catálogo?', text: 'Las cotizaciones ya hechas conservan su precio. Si solo quiere dejar de ofrecerlo, márquelo inactivo.', showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#d33', reverseButtons: true });
            if (!r.isConfirmed) return;
        }
        const j = await postJson(`${URL}/eliminarItemAjax`, { id });
        if (!j.ok) return alerta('warning', 'No se quitó', j.error);
        modalItem.hide();
        await cargarConfig();
    });

    // Buscador de productos (datalist compartido por los tres campos de producto).
    let timerProd = null;
    function buscarProductos(q) {
        clearTimeout(timerProd);
        timerProd = setTimeout(async () => {
            const j = await getJson(`${URL}/buscarProductosAjax?q=${encodeURIComponent(q)}`);
            if (!j.ok) return;
            $('cfgProductosLista').innerHTML = (j.data || []).map((p) => {
                const t = `${p.codigo} - ${p.nombre}`;
                productosMapa[t] = p.id;
                return `<option value="${esc(t)}"></option>`;
            }).join('');
        }, 250);
    }
    [['cfg_producto_anual_txt', 'cfg_id_producto_resumen_anual'], ['cfg_producto_semestral_txt', 'cfg_id_producto_resumen_semestral'], ['cfg_producto_mensual_txt', 'cfg_id_producto_resumen_mensual'], ['it_producto_txt', 'it_id_producto']]
        .forEach(([txt, hid]) => {
            $(txt).addEventListener('input', (ev) => {
                const v = ev.target.value.trim();
                $(hid).value = productosMapa[v] || '';
                if (v.length >= 2 && !productosMapa[v]) buscarProductos(v);
            });
        });

    let guardandoParam = false;
    $('cfgBtnGuardarParametros').addEventListener('click', async () => {
        if (guardandoParam) return;
        guardandoParam = true;
        try {
            const d = {};
            ['recargo_mensual_pct', 'recargo_semestral_pct', 'descuento_establecimiento_pct', 'usuarios_por_establecimiento', 'precio_usuario_adicional_anual', 'descuento_vendedor_max_pct', 'dias_vigencia']
                .forEach((k) => { d[k] = $('cfg_' + k).value; });
            d.permitir_descuento_vendedor = $('cfg_permitir_descuento_vendedor').checked;
            d.id_producto_resumen_anual = $('cfg_producto_anual_txt').value.trim() ? (parseInt($('cfg_id_producto_resumen_anual').value || 0, 10) || 0) : 0;
            d.id_producto_resumen_mensual = $('cfg_producto_mensual_txt').value.trim() ? (parseInt($('cfg_id_producto_resumen_mensual').value || 0, 10) || 0) : 0;
            d.id_producto_resumen_semestral = $('cfg_producto_semestral_txt').value.trim() ? (parseInt($('cfg_id_producto_resumen_semestral').value || 0, 10) || 0) : 0;
            if ($('cfg_producto_semestral_txt').value.trim() && !d.id_producto_resumen_semestral) return alerta('warning', 'Producto no encontrado', 'Elija el producto del plan semestral de la lista.');
            if ($('cfg_producto_anual_txt').value.trim() && !d.id_producto_resumen_anual) return alerta('warning', 'Producto no encontrado', 'Elija el producto del plan anual de la lista.');
            if ($('cfg_producto_mensual_txt').value.trim() && !d.id_producto_resumen_mensual) return alerta('warning', 'Producto no encontrado', 'Elija el producto del plan mensual de la lista.');
            const j = await postJson(`${URL}/guardarConfigAjax`, d);
            if (!j.ok) return alerta('warning', 'No se guardó', j.error);
            window.Swal ? Swal.fire({ icon: 'success', title: j.msg, timer: 1400, showConfirmButton: false }) : null;
            await cargarConfig();
        } catch (e) {
            alerta('error', 'Error de conexión', 'No se pudieron guardar los parámetros.');
        } finally {
            guardandoParam = false;
        }
    });
})();
