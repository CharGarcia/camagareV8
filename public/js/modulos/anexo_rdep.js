/**
 * Anexo RDEP (SRI): abrir el anexo del ejercicio, importar la nómina, editar
 * trabajadores, validar y generar el XML/ZIP para SRI en Línea.
 */
(function (window, document) {
    'use strict';

    const URL = BASE_URL + '/modulos/anexo-rdep';
    const $ = (id) => document.getElementById(id);
    const money = (v) => (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // Fechas en el formato estándar del sistema: d-m-Y H:i:s.
    const fecha = (v) => {
        if (!v) return '-';
        const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/);
        return m ? `${m[3]}-${m[2]}-${m[1]}${m[4] ? ` ${m[4]}:${m[5]}:${m[6]}` : ''}` : String(v);
    };
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const arr = (v) => { if (Array.isArray(v)) return v; try { const j = JSON.parse(v || '[]'); return Array.isArray(j) ? j : []; } catch (e) { return []; } };
    const truthy = (v) => v === true || v === 't' || v === '1' || v === 1;

    let modalNuevo = null, modalAnexo = null, modalTrab = null;
    const getModalNuevo = () => modalNuevo || (modalNuevo = new bootstrap.Modal($('modalNuevoRdep')));
    const getModalAnexo = () => modalAnexo || (modalAnexo = new bootstrap.Modal($('modalRdep')));
    const getModalTrab  = () => modalTrab  || (modalTrab  = new bootstrap.Modal($('modalRdepTrab')));

    let cabecera = null;
    let detalle = [];
    let ocupado = false;

    async function post(accion, datos) {
        const fd = new FormData();
        Object.entries(datos || {}).forEach(([k, v]) => fd.append(k, v));
        const resp = await fetch(`${URL}/${accion}`, { method: 'POST', body: fd });
        return resp.json();
    }
    function loader(on, texto) {
        $('rdep-loader')?.classList.toggle('d-none', !on);
        if (texto) $('rdep-loader-texto').textContent = texto;
    }
    function error(json, titulo) {
        Swal.fire({ icon: 'error', title: titulo || 'Atención', text: (json && json.error) || 'No se pudo completar la acción.' });
    }

    // ─── Nuevo ───────────────────────────────────────────────────────────────
    window.RDEP_abrirModalNuevo = function () {
        $('rdep_nuevo_anio').value = new Date().getFullYear() - 1;
        getModalNuevo().show();
    };
    $('formNuevoRdep')?.addEventListener('submit', async () => {
        if (ocupado) return;
        ocupado = true;
        const btn = $('btnNuevoRdep');
        btn.disabled = true;
        try {
            const json = await post('abrirAjax', { anio: $('rdep_nuevo_anio').value });
            if (!json.ok) { error(json); return; }
            getModalNuevo().hide();
            window.dispatchEvent(new CustomEvent('rdepActualizado'));
            await abrirAnexo(json.id);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.' });
        } finally {
            btn.disabled = false;
            ocupado = false;
        }
    });

    // ─── Abrir / pintar ──────────────────────────────────────────────────────
    window.RDEP_abrir = function (tr) {
        const row = (tr instanceof HTMLElement) ? JSON.parse(tr.dataset.row) : tr;
        if (row && row.id) abrirAnexo(row.id);
    };

    async function abrirAnexo(id) {
        $('rdep_id').value = id;
        try { new bootstrap.Tab($('rdep-tab-informante-btn')).show(); } catch (e) {}
        getModalAnexo().show();
        await cargar(id);
    }

    async function cargar(id, texto) {
        loader(true, texto || 'Cargando...');
        try {
            const resp = await fetch(`${URL}/getAnexoAjax?id=${id}`);
            const json = await resp.json();
            if (!json.ok) { error(json); return; }
            cabecera = json.cabecera;
            detalle = json.detalle || [];
            pintarCabecera(json.sin_tramos);
            pintarAvisoTramos(json);
            pintarTrabajadores();
            pintarObservaciones();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo cargar el anexo.' });
        } finally {
            loader(false);
        }
    }

    // Aviso de tabla de impuesto a la renta: dice el ejercicio que falta, qué años
    // están cargados y lleva directo a la pantalla de tramos de ese año. También avisa
    // si la fracción básica o la canasta del anexo siguen en cero.
    function pintarAvisoTramos(json) {
        const el = $('rdep_aviso_tramos');
        if (!el) return;
        const c = cabecera;
        const anios = (json.anios_tramos || []).join(', ');
        const enlace = `<a href="${esc(json.url_tramos || '#')}" target="_blank" rel="noopener" class="alert-link">Cargar la tabla de ${esc(c.anio)}</a>`;
        let html = '';
        if (json.sin_tramos) {
            html = `<i class="bi bi-exclamation-triangle me-1"></i> No hay tabla de impuesto a la renta del ejercicio <b>${esc(c.anio)}</b>`
                + (anios ? ` (años cargados: ${esc(anios)})` : ' (no hay ningún año cargado)')
                + `: el impuesto causado sale en cero. ${enlace}. Luego pulse <b>Recalcular</b>.`;
        } else if ((parseFloat(c.fraccion_basica) || 0) <= 0 || (parseFloat(c.canasta_basica) || 0) <= 0) {
            html = `<i class="bi bi-exclamation-triangle me-1"></i> La fracción básica o la canasta familiar básica del anexo están en cero y la configuración del ejercicio <b>${esc(c.anio)}</b> no las tiene. `
                + `Escríbalas aquí o complete los parámetros en la configuración (${enlace}).`;
        }
        el.innerHTML = html;
        el.classList.toggle('d-none', html === '');
    }

    function pintarCabecera(sinTramos) {
        const c = cabecera;
        $('rdep_titulo').textContent = `Anexo RDEP ${c.anio}`;
        $('rdep_c_anio').textContent = c.anio;
        $('rdep_c_ruc').value = c.num_ruc || '';
        $('rdep_c_rs').value = c.razon_social || '';
        $('rdep_c_tipo').value = c.tipo_empleador || 'PRIVADO_MIXTO';
        $('rdep_c_ente').value = c.ente_seg_social || 'IESS';
        $('rdep_c_estado').textContent = (c.estado || '').charAt(0).toUpperCase() + (c.estado || '').slice(1);
        $('rdep_c_importado').textContent = fecha(c.importado_at);
        $('rdep_c_fb').value = parseFloat(c.fraccion_basica || 0).toFixed(2);
        $('rdep_c_cfb').value = parseFloat(c.canasta_basica || 0).toFixed(2);
        $('rdep_c_pr').value = parseFloat(c.porcentaje_rebaja || 0).toFixed(2);
        $('rdep_c_ipceg').value = parseFloat(c.ipceg || 0).toFixed(3);
        $('rdep_c_obs').value = c.observaciones || '';

        const graves = parseInt(c.total_graves, 10) || 0;
        const leves = parseInt(c.total_leves, 10) || 0;
        $('rdep_badge_trab').textContent = detalle.length;
        $('rdep_badge_graves').textContent = graves; $('rdep_badge_graves').classList.toggle('d-none', graves === 0);
        $('rdep_badge_leves').textContent = leves; $('rdep_badge_leves').classList.toggle('d-none', leves === 0);
        $('rdep_resumen_obs').innerHTML = graves > 0
            ? `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${graves} grave(s)</span>${leves ? ` · <span class="text-warning-emphasis">${leves} leve(s)</span>` : ''}`
            : (leves > 0 ? `<span class="text-warning-emphasis"><i class="bi bi-exclamation-circle me-1"></i>${leves} leve(s)</span>` : `<span class="text-success"><i class="bi bi-check-circle me-1"></i>Sin observaciones</span>`);

        const generado = c.estado === 'generado' && c.archivo_xml;
        $('rdep_links').classList.toggle('d-none', !generado);
        if (generado) {
            $('rdep_link_xml').href = `${URL}/descargar?archivo=${encodeURIComponent(c.archivo_xml)}`;
            $('rdep_link_zip').href = `${URL}/descargar?archivo=${encodeURIComponent(c.archivo_xml.replace('.xml', '.zip'))}`;
        }
    }

    function filasVisibles() {
        const f = ($('rdep_filtro_trab').value || '').trim().toUpperCase();
        const soloObs = $('rdep_solo_obs').checked;
        return detalle.filter(d => {
            if (soloObs && arr(d.graves).length === 0 && arr(d.leves).length === 0) return false;
            if (!f) return true;
            return (d.id_ret + ' ' + d.apellidos + ' ' + d.nombres).toUpperCase().includes(f);
        });
    }

    // Botón de correo de la fila: sobre verde con fecha si ya se envió el 107.
    function botonCorreo(d) {
        const enviado = d.f107_enviado_at
            ? `Enviado el ${fecha(d.f107_enviado_at)} a ${d.f107_enviado_a || ''}. Clic para reenviar.`
            : (d.email_empleado ? `Enviar el Formulario 107 a ${d.email_empleado}` : 'Enviar el Formulario 107 por correo (el empleado no tiene correo en su ficha)');
        return `<button type="button" class="btn btn-xs ${d.f107_enviado_at ? 'btn-outline-success' : 'btn-outline-info'} border-0 px-1" onclick="event.stopPropagation(); RDEP_enviar107(${d.id})" title="${esc(enviado)}"><i class="bi ${d.f107_enviado_at ? 'bi-envelope-check' : 'bi-envelope'}"></i></button>`;
    }

    function pintarTrabajadores() {
        const tbody = $('rdep_tbody_trab');
        const filas = filasVisibles();
        if (!filas.length) {
            tbody.innerHTML = '<tr><td colspan="19" class="text-center text-muted py-4">Sin trabajadores. Use «Importar nómina» o agregue uno.</td></tr>';
            $('rdep_tfoot_trab').innerHTML = '';
            return;
        }
        const tot = { suel: 0, sob: 0, util: 0, d13: 0, d14: 0, fr: 0, iess: 0, gp: 0, base: 0, caus: 0, reb: 0, imp: 0, ret: 0 };
        tbody.innerHTML = filas.map(d => {
            const g = arr(d.graves).length, l = arr(d.leves).length;
            const gp = ['deduc_vivienda', 'deduc_salud', 'deduc_educ', 'deduc_aliment', 'deduc_vestim', 'deduc_turismo'].reduce((s, k) => s + (parseFloat(d[k]) || 0), 0);
            tot.suel += +d.suel_sal; tot.sob += +d.sob_suel; tot.util += +d.part_util; tot.d13 += +d.decim_ter; tot.d14 += +d.decim_cuar;
            tot.fr += +d.fondo_reserva; tot.iess += +d.apo_per_iess; tot.gp += gp; tot.base += +d.bas_imp; tot.caus += +d.imp_rent_caus;
            tot.reb += +d.rebaja_gastos; tot.imp += +d.imp_rent_rebaja; tot.ret += +d.val_ret;
            const obs = g ? `<span class="badge bg-danger" title="${esc(arr(d.graves).join(' | '))}">${g}</span>` : (l ? `<span class="badge bg-warning text-dark" title="${esc(arr(d.leves).join(' | '))}">${l}</span>` : '<i class="bi bi-check-circle text-success"></i>');
            const manual = arr(d.campos_manuales).length ? '<i class="bi bi-pencil-fill text-primary ms-1" title="Tiene campos editados a mano"></i>' : '';
            return `<tr class="${g ? 'rdep-grave' : ''}" role="button" onclick="RDEP_editarTrabajador(${d.id})">
                <td><code class="text-secondary">${esc(d.tip_id_ret)}-${esc(d.id_ret)}</code>${manual}</td>
                <td>${esc(d.apellidos)}</td><td>${esc(d.nombres)}</td>
                <td class="text-center">${esc(d.estab)}</td>
                <td class="text-end">${money(d.suel_sal)}</td><td class="text-end">${money(d.sob_suel)}</td><td class="text-end">${money(d.part_util)}</td>
                <td class="text-end">${money(d.decim_ter)}</td><td class="text-end">${money(d.decim_cuar)}</td><td class="text-end">${money(d.fondo_reserva)}</td>
                <td class="text-end">${money(d.apo_per_iess)}</td><td class="text-end">${money(gp)}</td>
                <td class="text-end">${money(d.bas_imp)}</td><td class="text-end">${money(d.imp_rent_caus)}</td><td class="text-end">${money(d.rebaja_gastos)}</td>
                <td class="text-end fw-bold">${money(d.imp_rent_rebaja)}</td><td class="text-end fw-bold">${money(d.val_ret)}</td>
                <td class="text-center">${obs}</td>
                <td class="text-center text-nowrap"><button type="button" class="btn btn-xs btn-outline-secondary border-0 px-1" onclick="event.stopPropagation(); RDEP_editarTrabajador(${d.id})" title="Editar"><i class="bi bi-pencil"></i></button><button type="button" class="btn btn-xs btn-outline-danger border-0 px-1" onclick="event.stopPropagation(); RDEP_f107(${d.id})" title="Formulario 107 (PDF)"><i class="bi bi-file-earmark-pdf"></i></button>${botonCorreo(d)}</td>
            </tr>`;
        }).join('');
        $('rdep_tfoot_trab').innerHTML = `<td colspan="4" class="text-end">Totales (${filas.length})</td>
            <td class="text-end">${money(tot.suel)}</td><td class="text-end">${money(tot.sob)}</td><td class="text-end">${money(tot.util)}</td>
            <td class="text-end">${money(tot.d13)}</td><td class="text-end">${money(tot.d14)}</td><td class="text-end">${money(tot.fr)}</td>
            <td class="text-end">${money(tot.iess)}</td><td class="text-end">${money(tot.gp)}</td>
            <td class="text-end">${money(tot.base)}</td><td class="text-end">${money(tot.caus)}</td><td class="text-end">${money(tot.reb)}</td>
            <td class="text-end">${money(tot.imp)}</td><td class="text-end">${money(tot.ret)}</td><td colspan="2"></td>`;
    }
    $('rdep_filtro_trab')?.addEventListener('input', pintarTrabajadores);
    $('rdep_solo_obs')?.addEventListener('change', pintarTrabajadores);

    function pintarObservaciones() {
        const cont = $('rdep_lista_obs');
        const conObs = detalle.filter(d => arr(d.graves).length || arr(d.leves).length);
        if (!conObs.length) {
            cont.innerHTML = '<div class="text-success"><i class="bi bi-check-circle me-1"></i> Sin observaciones. El anexo se puede generar.</div>';
            return;
        }
        cont.innerHTML = conObs.map(d => `
            <div class="border rounded-3 p-2 mb-2" role="button" onclick="RDEP_editarTrabajador(${d.id})">
                <div class="fw-bold">${esc(d.apellidos)} ${esc(d.nombres)} <span class="text-muted fw-normal">${esc(d.tip_id_ret)}-${esc(d.id_ret)}</span></div>
                ${arr(d.graves).map(m => `<div class="text-danger"><i class="bi bi-x-circle me-1"></i>${esc(m)}</div>`).join('')}
                ${arr(d.leves).map(m => `<div class="text-warning-emphasis"><i class="bi bi-exclamation-circle me-1"></i>${esc(m)}</div>`).join('')}
            </div>`).join('');
    }

    // ─── Acciones del anexo ──────────────────────────────────────────────────
    async function accion(nombre, datos, textoLoader, confirmar) {
        if (ocupado) return null;
        if (confirmar) {
            const r = await Swal.fire({ title: confirmar.titulo, text: confirmar.texto, icon: confirmar.icono || 'question', showCancelButton: true, confirmButtonText: confirmar.ok || 'Sí', cancelButtonText: 'Cancelar', confirmButtonColor: confirmar.color });
            if (!r.isConfirmed) return null;
        }
        ocupado = true;
        loader(true, textoLoader);
        try {
            const json = await post(nombre, datos);
            if (!json.ok) { error(json); return null; }
            return json;
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.' });
            return null;
        } finally {
            ocupado = false;
            loader(false);
        }
    }
    const id = () => $('rdep_id').value;

    window.RDEP_guardarCabecera = async function () {
        const fd = Object.fromEntries(new FormData($('formRdepCabecera')).entries());
        const json = await accion('guardarCabeceraAjax', { id: id(), ...fd }, 'Guardando y recalculando...');
        if (!json) return;
        Swal.fire({ icon: 'success', title: 'Guardado', text: json.msg, timer: 1400, showConfirmButton: false });
        await cargar(id());
        window.dispatchEvent(new CustomEvent('rdepActualizado'));
    };
    window.RDEP_importar = async function () {
        const json = await accion('importarAjax', { id: id() }, 'Importando la nómina del ejercicio...', {
            titulo: '¿Importar la nómina?', texto: 'Se toman los roles mensuales, décimos, utilidades y gastos personales del ejercicio. Lo que editó a mano se conserva.', ok: 'Sí, importar',
        });
        if (!json) return;
        Swal.fire({ icon: 'success', title: 'Nómina importada', text: json.msg, timer: 1800, showConfirmButton: false });
        await cargar(id());
        try { new bootstrap.Tab($('rdep-tab-trab-btn')).show(); } catch (e) {}
        window.dispatchEvent(new CustomEvent('rdepActualizado'));
    };
    window.RDEP_recalcular = async function () {
        const json = await accion('recalcularAjax', { id: id() }, 'Recalculando y validando...');
        if (!json) return;
        Swal.fire({ icon: 'success', title: 'Recalculado', text: json.msg, timer: (json.resultado && Object.keys(json.resultado.parametros_completados || {}).length) ? 3500 : 1300, showConfirmButton: false });
        await cargar(id());
        window.dispatchEvent(new CustomEvent('rdepActualizado'));
    };
    window.RDEP_generar = async function () {
        const json = await accion('generarAjax', { id: id() }, 'Generando el archivo...', {
            titulo: '¿Generar el archivo del anexo?', texto: 'Se recalcula, se valida como lo hace el SRI y se crea el XML con su ZIP. Si hay observaciones graves no se genera.', ok: 'Sí, generar',
        });
        if (!json) { await cargar(id()); return; }
        await cargar(id());
        window.dispatchEvent(new CustomEvent('rdepActualizado'));
        const r = await Swal.fire({ icon: 'success', title: 'Archivo generado', html: `${esc(json.msg)}<br><small>${json.resultado.trabajadores} trabajador(es)${json.resultado.leves ? `, ${json.resultado.leves} observación(es) leve(s)` : ''}.</small>`, showCancelButton: true, confirmButtonText: 'Descargar ZIP', cancelButtonText: 'Cerrar' });
        if (r.isConfirmed) CMG_descargar(json.url_zip || json.url_xml);
    };
    // ─── Formulario 107 (PDF) ────────────────────────────────────────────────
    // Imprime los valores GUARDADOS del anexo (los mismos del XML).
    window.RDEP_f107 = function (idDetalle) {
        CMG_pdfDocumento(`${URL}/formulario107Pdf?id_detalle=${idDetalle}`, { nombre: 'Formulario 107', archivo: 'Formulario107.pdf' });
    };
    window.RDEP_f107Todos = function () {
        if (!detalle.length) {
            Swal.fire({ icon: 'info', title: 'Sin trabajadores', text: 'Importe la nómina del ejercicio antes de imprimir el Formulario 107.' });
            return;
        }
        CMG_pdfDocumento(`${URL}/formulario107Pdf?id=${id()}`, { nombre: 'Formulario 107', archivo: 'Formulario107_todos.pdf' });
    };
    window.RDEP_f107Actual = function () {
        const idDet = $('rdep_t_id').value;
        if (!idDet) return;
        if (trabSucio) {
            Swal.fire({ icon: 'info', title: 'Cambios sin guardar', text: 'Guarde el trabajador antes de imprimir: el Formulario 107 sale de los valores guardados.' });
            return;
        }
        RDEP_f107(idDet);
    };

    // ─── Formulario 107 por correo ───────────────────────────────────────────
    // Swal se dibuja DENTRO del modal abierto: si no, la trampa de foco de Bootstrap
    // no deja escribir en el campo del correo.
    const modalAbierto = () => document.querySelector('#modalRdepTrab.show') || document.querySelector('#modalRdep.show') || undefined;
    let enviandoCorreo = false;

    window.RDEP_enviar107 = async function (idDetalle) {
        if (enviandoCorreo) return;
        const d = detalle.find(x => String(x.id) === String(idDetalle)) || {};
        if (arr(d.graves).length) {
            Swal.fire({ icon: 'warning', title: 'Tiene observaciones graves', text: 'Corrija las observaciones graves de este trabajador antes de enviarle el Formulario 107.', target: modalAbierto() });
            return;
        }
        const previo = d.f107_enviado_at ? `<div class="small text-success mb-2"><i class="bi bi-envelope-check me-1"></i>Ya se envió el ${esc(fecha(d.f107_enviado_at))} a ${esc(d.f107_enviado_a || '')}.</div>` : '';
        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar Formulario 107',
            html: `${previo}<div class="small text-muted">${esc((d.apellidos || '') + ' ' + (d.nombres || ''))}</div>`,
            input: 'text',
            inputLabel: 'Correo(s) del trabajador, separados por coma',
            inputValue: d.email_empleado || '',
            inputPlaceholder: 'trabajador@correo.com',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            target: modalAbierto(),
            inputValidator: (v) => (!v || !v.trim()) ? 'Ingrese al menos un correo.' : undefined,
        });
        if (!isConfirmed) return;

        enviandoCorreo = true;
        Swal.fire({ title: 'Enviando correo...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), target: modalAbierto() });
        try {
            const json = await post('enviarFormulario107Ajax', { id: idDetalle, correos: correos.trim() });
            if (!json.ok) { Swal.fire({ icon: 'error', title: 'No se envió', text: json.error || 'No se pudo enviar el correo.', target: modalAbierto() }); return; }
            Swal.fire({ icon: 'success', title: 'Enviado', text: json.msg, timer: 1800, showConfirmButton: false, target: modalAbierto() });
            await cargar(id());
            pintarEnvioTrabajador();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.', target: modalAbierto() });
        } finally {
            enviandoCorreo = false;
        }
    };

    window.RDEP_enviar107Actual = function () {
        const idDet = $('rdep_t_id').value;
        if (!idDet) return;
        if (trabSucio) {
            Swal.fire({ icon: 'info', title: 'Cambios sin guardar', text: 'Guarde el trabajador antes de enviar: el Formulario 107 sale de los valores guardados.', target: modalAbierto() });
            return;
        }
        RDEP_enviar107(idDet);
    };

    window.RDEP_enviar107Todos = async function () {
        if (enviandoCorreo) return;
        if (!detalle.length) {
            Swal.fire({ icon: 'info', title: 'Sin trabajadores', text: 'Importe la nómina del ejercicio antes de enviar el Formulario 107.', target: modalAbierto() });
            return;
        }
        const conCorreo = detalle.filter(d => d.email_empleado && !arr(d.graves).length).length;
        const sinCorreo = detalle.filter(d => !d.email_empleado).length;
        const conGraves = detalle.filter(d => d.email_empleado && arr(d.graves).length).length;
        if (!conCorreo) {
            Swal.fire({ icon: 'info', title: 'Nadie a quien enviar', text: 'Ningún trabajador tiene correo en su ficha (o todos tienen observaciones graves).', target: modalAbierto() });
            return;
        }
        const r = await Swal.fire({
            title: '¿Enviar el Formulario 107 a todos?',
            html: `Se enviará a <b>${conCorreo}</b> trabajador(es), al correo de su ficha de empleado.`
                + (sinCorreo ? `<br><small class="text-muted">${sinCorreo} sin correo en su ficha: no se les envía.</small>` : '')
                + (conGraves ? `<br><small class="text-danger">${conGraves} con observaciones graves: no se les envía.</small>` : ''),
            icon: 'question', showCancelButton: true, confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar', cancelButtonText: 'Cancelar', target: modalAbierto(),
        });
        if (!r.isConfirmed) return;

        enviandoCorreo = true;
        const btn = $('btnEnviar107Todos');
        if (btn) btn.disabled = true;
        Swal.fire({ title: 'Enviando correos...', text: 'Uno por trabajador; puede tardar.', allowOutsideClick: false, didOpen: () => Swal.showLoading(), target: modalAbierto() });
        try {
            const json = await post('enviarFormulario107TodosAjax', { id: id() });
            if (!json.ok) { Swal.fire({ icon: 'error', title: 'No se envió', text: json.error || 'No se pudo enviar.', target: modalAbierto() }); return; }
            const x = json.resultado;
            const lista = (t, a, cls) => a.length ? `<div class="text-start small mt-2 ${cls}"><b>${t} (${a.length}):</b> ${a.map(esc).join(', ')}</div>` : '';
            Swal.fire({
                icon: x.fallidos.length ? 'warning' : 'success',
                title: `Enviado a ${x.enviados} trabajador(es)`,
                html: (x.detenido ? '<div class="text-danger small">El primer envío falló: revise la configuración de correo de la empresa. Se detuvo para no repetir el error con todos.</div>' : '')
                    + lista('No se pudo enviar', x.fallidos, 'text-danger')
                    + lista('Sin correo en la ficha', x.sin_correo, 'text-muted')
                    + lista('Con observaciones graves', x.con_graves, 'text-warning-emphasis'),
                target: modalAbierto(),
            });
            await cargar(id());
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.', target: modalAbierto() });
        } finally {
            enviandoCorreo = false;
            if (btn) btn.disabled = false;
        }
    };

    // Línea "ya enviado" en la barra del modal del trabajador.
    function pintarEnvioTrabajador() {
        const el = $('rdep_t_envio');
        if (!el) return;
        const d = detalle.find(x => String(x.id) === String($('rdep_t_id').value));
        el.innerHTML = d && d.f107_enviado_at
            ? `<i class="bi bi-envelope-check text-success me-1"></i>Enviado el ${esc(fecha(d.f107_enviado_at))} a ${esc(d.f107_enviado_a || '')}`
            : (d && !d.email_empleado ? '<i class="bi bi-info-circle me-1"></i>Sin correo en la ficha del empleado' : '');
    }

    // Modal del trabajador encima del modal del anexo: la regla global deja todos los
    // .modal en 5060; el hijo sube a 5080 y su fondo a 5075 (ver modales anidados).
    $('modalRdepTrab')?.addEventListener('show.bs.modal', function () { this.style.setProperty('z-index', '5080', 'important'); });
    $('modalRdepTrab')?.addEventListener('shown.bs.modal', function () {
        const fondos = document.querySelectorAll('.modal-backdrop');
        if (fondos.length) fondos[fondos.length - 1].style.setProperty('z-index', '5075', 'important');
        pintarEnvioTrabajador();
    });

    window.RDEP_descargar = function (nombre) {
        CMG_descargar(`${URL}/descargar?archivo=${encodeURIComponent(nombre)}`);
    };
    window.RDEP_eliminar = async function () {
        const json = await accion('eliminarAjax', { id: id() }, 'Eliminando...', {
            titulo: '¿Eliminar este anexo?', texto: 'Se elimina lógicamente con todos sus trabajadores. Podrá abrirlo de nuevo desde Nuevo.', icono: 'warning', ok: 'Sí, eliminar', color: '#d33',
        });
        if (!json) return;
        getModalAnexo().hide();
        window.dispatchEvent(new CustomEvent('rdepActualizado'));
        Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1200, showConfirmButton: false });
    };

    // ─── Agregar trabajador ──────────────────────────────────────────────────
    let timerBuscar;
    $('rdep_buscar_emp')?.addEventListener('input', function () {
        clearTimeout(timerBuscar);
        const q = this.value.trim();
        const lista = $('rdep_buscar_emp_lista');
        if (q.length < 2) { lista.classList.add('d-none'); return; }
        timerBuscar = setTimeout(async () => {
            try {
                const json = await (await fetch(`${URL}/buscarEmpleadosAjax?q=${encodeURIComponent(q)}`)).json();
                const ya = new Set(detalle.map(d => String(d.id_empleado)));
                const items = (json.data || []).filter(e => !ya.has(String(e.id)));
                lista.innerHTML = items.length
                    ? items.map(e => `<button type="button" class="list-group-item list-group-item-action py-1 small" onclick="RDEP_agregarEmpleado(${e.id})"><code>${esc(e.identificacion)}</code> ${esc(e.nombres_apellidos)} <span class="text-muted">(${esc(e.estado)})</span></button>`).join('')
                    : '<div class="list-group-item small text-muted">Sin coincidencias (o ya están en el anexo).</div>';
                lista.classList.remove('d-none');
            } catch (e) {}
        }, 300);
    });
    document.addEventListener('click', (ev) => {
        if (!ev.target.closest('#rdep_buscar_emp') && !ev.target.closest('#rdep_buscar_emp_lista')) $('rdep_buscar_emp_lista')?.classList.add('d-none');
    });
    window.RDEP_agregarEmpleado = async function (idEmpleado) {
        $('rdep_buscar_emp_lista').classList.add('d-none');
        $('rdep_buscar_emp').value = '';
        const json = await accion('agregarTrabajadorAjax', { id_anexo: id(), id_empleado: idEmpleado }, 'Agregando...');
        if (!json) return;
        await cargar(id());
        RDEP_editarTrabajador(json.id);
    };
    window.RDEP_agregarEnBlanco = async function () {
        const json = await accion('agregarTrabajadorAjax', { id_anexo: id(), id_empleado: 0 }, 'Agregando...', {
            titulo: '¿Agregar un trabajador sin ficha?', texto: 'Se crea una fila en blanco para completarla a mano (identificación, nombres, valores).', ok: 'Sí, agregar',
        });
        if (!json) return;
        await cargar(id());
        RDEP_editarTrabajador(json.id);
    };

    // ─── Editar trabajador ───────────────────────────────────────────────────
    function llenarCombos() {
        const paises = $('rdep_t_pais');
        if (paises && !paises.options.length) {
            paises.innerHTML = Object.entries(window.RDEP_PAISES || {}).map(([v, l]) => `<option value="${esc(v)}">${esc(v)} - ${esc(l)}</option>`).join('');
        }
        const dl = $('rdep_estab_lista');
        if (dl && !dl.options.length) {
            dl.innerHTML = (window.RDEP_ESTABLECIMIENTOS || []).map(e => `<option value="${esc(e.codigo)}">${esc(e.nombre || '')}</option>`).join('');
        }
    }
    const CAMPOS = {
        tip_id_ret: 'rdep_t_tip_id_ret', id_ret: 'rdep_t_id_ret', apellidos: 'rdep_t_apellidos', nombres: 'rdep_t_nombres', estab: 'rdep_t_estab',
        residencia: 'rdep_t_residencia', pais_residencia: 'rdep_t_pais', aplica_convenio: 'rdep_t_convenio', tipo_discap: 'rdep_t_tipo_discap',
        porcentaje_discap: 'rdep_t_pct_discap', tip_id_discap: 'rdep_t_tip_id_discap', id_discap: 'rdep_t_id_discap', ben_galapagos: 'rdep_t_galapagos',
        enf_catastro: 'rdep_t_enf', num_cargas: 'rdep_t_cargas',
        suel_sal: 'rdep_t_suel_sal', sob_suel: 'rdep_t_sob_suel', part_util: 'rdep_t_part_util', imp_rent_empl: 'rdep_t_imp_rent_empl',
        decim_ter: 'rdep_t_decim_ter', decim_cuar: 'rdep_t_decim_cuar', fondo_reserva: 'rdep_t_fondo_reserva', salario_digno: 'rdep_t_salario_digno',
        otros_ing_no_grav: 'rdep_t_otros_no_grav', ing_grav_este_empl: 'rdep_t_ing_grav', int_grab_gen: 'rdep_t_int_grab_gen',
        apor_per_iess_otros: 'rdep_t_apor_otros', val_ret_otros: 'rdep_t_val_ret_otros',
        sis_sal_net: 'rdep_t_sis', apo_per_iess: 'rdep_t_apo_per_iess', deduc_vivienda: 'rdep_t_vivienda', deduc_salud: 'rdep_t_salud',
        deduc_educ: 'rdep_t_educ', deduc_aliment: 'rdep_t_aliment', deduc_vestim: 'rdep_t_vestim', deduc_turismo: 'rdep_t_turismo',
        exo_discap: 'rdep_t_exo_discap', exo_ter_ed: 'rdep_t_exo_ter',
        bas_imp: 'rdep_t_bas_imp', imp_rent_caus: 'rdep_t_causado', rebaja_gastos: 'rdep_t_rebaja', imp_rent_rebaja: 'rdep_t_imp_rebaja',
        val_ret: 'rdep_t_val_ret', val_imp_asu_este: 'rdep_t_val_imp_asu', observaciones: 'rdep_t_obs',
    };
    const MONTOS = new Set(['suel_sal', 'sob_suel', 'part_util', 'imp_rent_empl', 'decim_ter', 'decim_cuar', 'fondo_reserva', 'salario_digno', 'otros_ing_no_grav', 'ing_grav_este_empl', 'int_grab_gen', 'apor_per_iess_otros', 'val_ret_otros', 'apo_per_iess', 'deduc_vivienda', 'deduc_salud', 'deduc_educ', 'deduc_aliment', 'deduc_vestim', 'deduc_turismo', 'exo_discap', 'exo_ter_ed', 'bas_imp', 'imp_rent_caus', 'rebaja_gastos', 'imp_rent_rebaja', 'val_ret', 'val_imp_asu_este']);

    function pintarTrabajador(d) {
        llenarCombos();
        $('rdep_t_id').value = d.id;
        $('rdep_t_titulo').textContent = `${d.apellidos || ''} ${d.nombres || ''}`.trim() || 'Trabajador';
        Object.entries(CAMPOS).forEach(([k, elId]) => {
            const el = $(elId);
            if (!el) return;
            el.value = MONTOS.has(k) ? (parseFloat(d[k]) || 0).toFixed(2) : (d[k] ?? '');
        });
        $('rdep_t_val_ret_otros2').value = (parseFloat(d.val_ret_otros) || 0).toFixed(2);
        $('rdep_t_tercera').checked = truthy(d.tercera_edad);
        actualizarDiferencia();
        const g = arr(d.graves), l = arr(d.leves);
        $('rdep_t_validaciones').innerHTML = (g.length || l.length)
            ? `<div class="border rounded-3 p-2 small">${g.map(m => `<div class="text-danger"><i class="bi bi-x-circle me-1"></i>${esc(m)}</div>`).join('')}${l.map(m => `<div class="text-warning-emphasis"><i class="bi bi-exclamation-circle me-1"></i>${esc(m)}</div>`).join('')}</div>`
            : '<div class="small text-success"><i class="bi bi-check-circle me-1"></i> Sin observaciones.</div>';
        try { new bootstrap.Tab($('rdept-tab-datos-btn')).show(); } catch (e) {}
        trabSucio = false; // recién cargado o recién guardado
    }
    // Cambios sin guardar en el modal del trabajador: el 107 imprime lo guardado.
    let trabSucio = false;
    ['input', 'change'].forEach(ev => $('formRdepTrab')?.addEventListener(ev, () => { trabSucio = true; }));
    function actualizarDiferencia() {
        const v = (id) => parseFloat($(id).value) || 0;
        const dif = v('rdep_t_val_ret') + v('rdep_t_val_imp_asu') + v('rdep_t_val_ret_otros') - v('rdep_t_imp_rebaja');
        const el = $('rdep_t_diferencia');
        el.textContent = money(dif);
        el.classList.toggle('text-danger', Math.abs(dif) > 0.01);
        el.classList.toggle('text-success', Math.abs(dif) <= 0.01);
    }
    ['rdep_t_val_ret', 'rdep_t_val_imp_asu', 'rdep_t_val_ret_otros'].forEach(i => $(i)?.addEventListener('input', () => { $('rdep_t_val_ret_otros2').value = (parseFloat($('rdep_t_val_ret_otros').value) || 0).toFixed(2); actualizarDiferencia(); }));

    window.RDEP_editarTrabajador = async function (idDetalle) {
        try {
            const json = await (await fetch(`${URL}/getTrabajadorAjax?id=${idDetalle}`)).json();
            if (!json.ok) { error(json); return; }
            pintarTrabajador(json.trabajador);
            getModalTrab().show();
        } catch (e) {}
    };

    let guardandoTrab = false;
    window.RDEP_guardarTrabajador = async function () {
        if (guardandoTrab) return;
        guardandoTrab = true;
        const btn = $('btnGuardarRdepTrab');
        if (btn) btn.disabled = true;
        try {
            const fd = Object.fromEntries(new FormData($('formRdepTrab')).entries());
            delete fd.val_ret_otros_ro;
            if (!$('rdep_t_tercera').checked) fd.tercera_edad = '0';
            const json = await post('guardarTrabajadorAjax', fd);
            if (!json.ok) { error(json, 'No se pudo guardar'); return; }
            pintarTrabajador(json.trabajador);
            await cargar(id());
            window.dispatchEvent(new CustomEvent('rdepActualizado'));
            const g = arr(json.trabajador.graves).length;
            Swal.fire({ icon: g ? 'warning' : 'success', title: g ? 'Guardado con observaciones' : 'Guardado', text: g ? 'Revise las observaciones en la pestaña Resumen impositivo.' : json.msg, timer: g ? 2200 : 1300, showConfirmButton: false });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.' });
        } finally {
            guardandoTrab = false;
            if (btn) btn.disabled = false;
        }
    };
    window.RDEP_quitarTrabajador = async function () {
        const r = await Swal.fire({ title: '¿Quitar este trabajador del anexo?', text: 'Si vuelve a importar la nómina y tiene roles en el ejercicio, volverá a aparecer.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, quitar', cancelButtonText: 'Cancelar' });
        if (!r.isConfirmed) return;
        const json = await post('eliminarTrabajadorAjax', { id: $('rdep_t_id').value });
        if (!json.ok) { error(json); return; }
        getModalTrab().hide();
        await cargar(id());
        window.dispatchEvent(new CustomEvent('rdepActualizado'));
    };

})(window, document);
