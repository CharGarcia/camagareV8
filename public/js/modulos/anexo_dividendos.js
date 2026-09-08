/**
 * Anexo de Dividendos (ADI).
 *
 * Toda la pantalla trabaja sobre un único "paquete" que devuelve el servidor
 * (anexo + beneficiarios + detalles + totales + validaciones). Cada acción que
 * modifica algo responde con el paquete actualizado, así que la pantalla se
 * vuelve a pintar de una sola fuente y no hay estado duplicado en el navegador.
 */
(function () {
    'use strict';

    const URL_BASE = window.ADI_URL_BASE;
    const PERM = window.ADI_PERM || {};
    const CAT = window.ADI_CAT || {};

    /** Último paquete recibido del servidor. */
    let estado = null;

    // ── Utilidades ───────────────────────────────────────────────────────────

    function el(id) { return document.getElementById(id); }

    function money(v) {
        return (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function aviso(titulo, texto, icono) {
        if (window.Swal) { Swal.fire(titulo, texto, icono || 'info'); return; }
        alert(titulo + '\n\n' + texto);
    }

    async function pedir(url, opciones) {
        const r = await fetch(url, opciones);
        let json;
        try {
            json = await r.json();
        } catch (e) {
            throw new Error('El servidor respondió algo que no se pudo leer (HTTP ' + r.status + ').');
        }
        if (!json.ok) throw new Error(json.mensaje || 'No se pudo completar la operación.');
        return json;
    }

    function post(accion, datos) {
        const body = new FormData();
        Object.keys(datos).forEach(k => {
            const v = datos[k];
            body.append(k, v === null || v === undefined ? '' : (typeof v === 'object' ? JSON.stringify(v) : v));
        });
        return pedir(`${URL_BASE}/${accion}`, { method: 'POST', body });
    }

    /**
     * app.css fuerza .modal { z-index: 5060 !important }, así que un modal
     * abierto sobre otro queda detrás salvo que se le suba el z-index inline
     * con !important (y a su backdrop).
     */
    function abrirModalEncima(idModal) {
        const nodo = el(idModal);
        const modal = bootstrap.Modal.getOrCreateInstance(nodo);
        nodo.addEventListener('shown.bs.modal', function subir() {
            nodo.style.setProperty('z-index', '5080', 'important');
            const fondos = document.querySelectorAll('.modal-backdrop');
            const ultimo = fondos[fondos.length - 1];
            if (ultimo) ultimo.style.setProperty('z-index', '5075', 'important');
            nodo.removeEventListener('shown.bs.modal', subir);
        });
        modal.show();
    }

    // ── Listado: búsqueda, orden y paginación ────────────────────────────────

    const inputBuscar = el('buscarAdi');
    window.currentSort = (window.ADI_SORT_INI && window.ADI_SORT_INI.col) || 'anio';
    window.currentDir = ((window.ADI_SORT_INI && window.ADI_SORT_INI.dir) || 'desc').toUpperCase();
    window.currentPage = 1;

    let temporizadorListado;

    function debounce(fn, espera) {
        return function (...args) {
            clearTimeout(temporizadorListado);
            temporizadorListado = setTimeout(() => fn.apply(this, args), espera || 350);
        };
    }

    window.cambiarPaginaAjax = function (n) { window.fetchSearch(n); };

    window.fetchSearch = async function (page) {
        page = page || 1;
        const termino = inputBuscar ? inputBuscar.value.trim() : '';
        const uri = `${URL_BASE}/searchAjax?b=${encodeURIComponent(termino)}&page=${page}` +
                    `&sort=${window.currentSort}&dir=${window.currentDir}`;
        try {
            const res = await (await fetch(uri)).json();
            if (!res.ok) return;

            window.currentPage = page;
            el('tbodyAdi').innerHTML = res.rows;
            el('paginationContainerAdi').innerHTML = res.pagination;
            el('paginationInfoAdi').textContent = res.info;
            el('btnExportPdfAdi').href = res.pdf_url;
            el('btnExportExcelAdi').href = res.excel_url;
        } catch (e) {
            console.error('Error al buscar anexos de dividendos:', e);
        }
    };

    // Motor global de ordenamiento: persiste la preferencia del usuario.
    if (typeof window.CMG_initSort === 'function') {
        window.CMG_initSort(window.ADI_MODULO || 'anexo_dividendos', (col, dir) => {
            window.currentSort = col;
            window.currentDir = dir;
            window.fetchSearch(1);
        }, { col: window.currentSort, dir: window.currentDir });
    }

    inputBuscar?.addEventListener('input', debounce(() => window.fetchSearch(1), 400));

    /**
     * Abre el modal en blanco para un período nuevo. El anexo todavía no existe
     * en la base: se crea al guardar, con el año que elija el usuario en la
     * pestaña «Informante y origen».
     */
    window.ADI_nuevo = function () {
        estado = null;

        const defaults = window.ADI_DEFAULTS || {};
        el('adi-id').value = '';
        el('adi-titulo-anio').textContent = '';
        pintarInformante(defaults);
        el('adi-razon-social').value = defaults.razon_social || '';
        el('adi-sbu').value = '0.00';
        el('adi-observaciones').value = '';

        ['adi-b1', 'adi-b2', 'adi-b3', 'adi-b4', 'adi-b5', 'adi-b6', 'adi-b7', 'adi-b8']
            .forEach(id => { el(id).value = '0.00'; });

        // Un año ya usado no puede repetirse: el SRI presenta un anexo por
        // período y la base lo impide con un índice único.
        marcarAniosUsados();

        el('tbodyAdi') && (el('adi-badge-detalles').textContent = '0');
        el('adi-tbody-dividendos').innerHTML =
            '<tr><td colspan="12" class="text-center text-muted py-4">Guarde el período para registrar dividendos.</td></tr>';
        el('adi-sin-tercero').classList.add('d-none');
        el('adi-lista-errores').innerHTML = '';
        el('adi-lista-advertencias').innerHTML = '';
        el('adi-resumen-cabecera').textContent = '';
        el('adi-resumen-dividendos').textContent = '';
        el('adi-tabla-cuentas-div').innerHTML =
            '<tbody><tr><td class="text-muted small">Guarde el período para elegir las cuentas contables.</td></tr></tbody>';
        el('adi-tabla-cuentas-res').innerHTML = el('adi-tabla-cuentas-div').innerHTML;

        pintarEstado('borrador');
        pintarCuadreB();
        aplicarModoNuevo(true);

        // Siempre se entra por la primera pestaña: el resto necesita el anexo guardado.
        document.querySelector('#adi-tab-informante')?.click();
        bootstrap.Modal.getOrCreateInstance(el('modalAdi')).show();
    };

    window.ADI_abrir = function (id) {
        pedir(`${URL_BASE}/detalleAjax?id=${id}`)
            .then(res => { pintar(res.data); mostrarModalPrincipal(); })
            .catch(e => aviso('No se pudo abrir el anexo', e.message, 'error'));
    };

    function mostrarModalPrincipal() {
        bootstrap.Modal.getOrCreateInstance(el('modalAdi')).show();
        cargarCuentas();
    }

    /**
     * Alterna la pantalla entre «período nuevo» y «anexo guardado». Mientras no
     * exista el anexo, el año se puede elegir pero nada más funciona: importar,
     * recalcular, generar y el detalle necesitan un id en la base.
     */
    function aplicarModoNuevo(esNuevo) {
        const selAnio = el('adi-anio');
        if (selAnio) {
            selAnio.disabled = !esNuevo;
            // El motivo del bloqueo se explica en el propio control, sin
            // depender de que exista un texto de ayuda bajo el campo.
            selAnio.title = esNuevo
                ? 'Período que se informa al SRI'
                : 'El año no se cambia una vez creado el anexo: para otro período, cree uno nuevo';
        }

        document.querySelectorAll('#modalAdi .adi-requiere-anexo').forEach(nodo => {
            nodo.classList.toggle('disabled', esNuevo);
            if ('disabled' in nodo) nodo.disabled = esNuevo;
        });

        ['adi-tab-dividendos', 'adi-tab-validacion'].forEach(id => {
            const tab = el(id);
            if (!tab) return;
            tab.classList.toggle('disabled', esNuevo);
            tab.setAttribute('aria-disabled', esNuevo ? 'true' : 'false');
        });
    }

    /**
     * Deja en el selector solo los años sin anexo, para no chocar contra el
     * índice único de la base al guardar.
     */
    function marcarAniosUsados() {
        const select = el('adi-anio');
        if (!select) return;

        const usados = new Set(
            Array.from(document.querySelectorAll('#tbodyAdi tr td[data-col="anio"]'))
                .map(td => td.textContent.trim())
        );

        let primeroLibre = null;
        Array.from(select.options).forEach(op => {
            const usado = usados.has(op.value);
            op.disabled = usado;
            op.textContent = usado ? `${op.value} (ya registrado)` : op.value;
            if (!usado && primeroLibre === null) primeroLibre = op.value;
        });

        if (primeroLibre !== null) select.value = primeroLibre;
    }

    // Al cerrar el anexo, la fila del listado puede haber cambiado (totales,
    // estado, beneficiarios): se refresca la página actual en lugar de recargar
    // toda la pantalla.
    el('modalAdi')?.addEventListener('hidden.bs.modal', () => {
        if (estado) window.fetchSearch(window.currentPage || 1);
    });

    // ── Pintado del modal principal ──────────────────────────────────────────

    function pintar(data) {
        estado = data;
        const a = data.anexo;

        el('adi-id').value = a.id;
        el('adi-titulo-anio').textContent = a.anio;

        // El año del anexo guardado se muestra pero no se cambia: es parte de su
        // clave en la base y en el propio anexo del SRI.
        const selAnio = el('adi-anio');
        if (selAnio) {
            if (!Array.from(selAnio.options).some(op => op.value === String(a.anio))) {
                selAnio.add(new Option(a.anio, a.anio));
            }
            Array.from(selAnio.options).forEach(op => {
                op.disabled = false;
                op.textContent = op.value;
            });
            selAnio.value = String(a.anio);
        }
        aplicarModoNuevo(false);

        pintarInformante(a);
        el('adi-razon-social').value = a.razon_social || '';
        el('adi-sbu').value = parseFloat(a.sbu || 0).toFixed(2);
        el('adi-observaciones').value = a.observaciones || '';

        el('adi-b1').value = parseFloat(a.utilidad_ejercicio).toFixed(2);
        el('adi-b2').value = parseFloat(a.utilidad_distribuida_distinta_reinv).toFixed(2);
        el('adi-b3').value = parseFloat(a.utilidad_reinvertida_con_derecho).toFixed(2);
        el('adi-b4').value = parseFloat(a.utilidad_reinvertida_sin_derecho).toFixed(2);
        el('adi-b5').value = parseFloat(a.utilidad_pagada_anticipado).toFixed(2);
        el('adi-b6').value = parseFloat(a.utilidad_no_distribuida).toFixed(2);
        el('adi-b7').value = parseFloat(a.utilidad_no_distrib_ejer_ant).toFixed(2);
        el('adi-b8').value = parseFloat(a.utilidad_distrib_ejercicios_ant).toFixed(2);

        pintarEstado(a.estado);
        pintarCuadreB();
        pintarDividendos(data);
        pintarValidaciones(data);
        pintarEnlaces(a);
    }

    /**
     * Muestra la identificación del informante, que no se captura: sale de la
     * empresa activa. Sirve para que se vea qué va a llevar el archivo antes de
     * generarlo, y para avisar si a la empresa le falta el RUC o el tipo de
     * contribuyente.
     */
    function pintarInformante(datos) {
        const tipo = datos.tipo_informante || '';
        const tipoIdentificacion = datos.tipo_id_informante || 'R';

        el('adi-info-identificacion').textContent = datos.id_informante || '—';
        el('adi-info-tipo-id').textContent =
            (CAT.tipo_identificacion || {})[tipoIdentificacion] || tipoIdentificacion || '—';
        el('adi-info-tipo-informante').textContent =
            (CAT.tipo_informante || {})[tipo] || tipo || '—';

        const ayuda = el('adi-info-ayuda');
        if (!ayuda) return;

        const faltantes = [];
        if (datos.sin_ruc || !datos.id_informante) faltantes.push('el RUC');
        if (datos.sin_tipo) faltantes.push('el tipo de contribuyente');

        if (faltantes.length) {
            ayuda.className = 'form-text adi-nota text-danger';
            ayuda.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Falta ' + faltantes.join(' y ') +
                ' en la configuración de la empresa. El anexo no se podrá presentar hasta completarlo.';
            return;
        }

        // Persona natural y sucesión indivisa no reportan utilidades ni
        // dividendos distribuidos: para ellas el anexo es solo la sección F
        // (dividendos recibidos del exterior), que este módulo todavía no
        // genera. Conviene decirlo al entrar y no al intentar generar.
        if (tipo === '02' || tipo === '03') {
            ayuda.className = 'form-text adi-nota text-warning-emphasis';
            ayuda.innerHTML = '<i class="bi bi-info-circle me-1"></i>La empresa está registrada como <strong>' +
                esc((CAT.tipo_informante || {})[tipo] || '') + '</strong>, y ese tipo de informante no reporta ' +
                'utilidades ni dividendos distribuidos: solo los dividendos recibidos del exterior, que este ' +
                'módulo aún no genera. Si en realidad es una sociedad, corrija el tipo de contribuyente en la ' +
                'configuración de la empresa.';
            return;
        }

        ayuda.className = 'form-text adi-nota';
        ayuda.textContent = 'Se toman de la empresa activa. Para cambiarlos, edite el RUC o el tipo de ' +
            'contribuyente en la configuración de la empresa.';
    }

    function pintarEstado(estadoAnexo) {
        const badge = el('adi-badge-estado');
        const mapa = {
            generado: ['bg-success bg-opacity-10 text-success border-success', 'Generado'],
            presentado: ['bg-primary bg-opacity-10 text-primary border-primary', 'Presentado'],
        };
        const [cls, txt] = mapa[estadoAnexo] || ['bg-secondary bg-opacity-10 text-secondary border-secondary', 'Borrador'];
        badge.className = 'badge border border-opacity-25 ms-2 ' + cls;
        badge.textContent = txt;
    }

    function pintarCuadreB() {
        const b1 = parseFloat(el('adi-b1').value) || 0;
        const b2 = parseFloat(el('adi-b2').value) || 0;
        const b3 = parseFloat(el('adi-b3').value) || 0;
        const b4 = parseFloat(el('adi-b4').value) || 0;
        const b6 = Math.round((b1 - b2 - b3 - b4) * 100) / 100;
        el('adi-b6').value = b6.toFixed(2);

        const cuadre = el('adi-cuadre-b');
        if (!cuadre) return;
        cuadre.innerHTML = b6 < 0
            ? '<i class="bi bi-exclamation-triangle text-danger me-1"></i> Lo distribuido y reinvertido supera la ' +
              'utilidad del ejercicio: la utilidad no distribuida quedaría en ' + money(b6) + '.'
            : '<i class="bi bi-check-circle text-success me-1"></i> Utilidad no distribuida: <strong>' +
              money(b6) + '</strong> (utilidad del ejercicio menos lo distribuido y lo reinvertido).';
    }

    ['adi-b1', 'adi-b2', 'adi-b3', 'adi-b4'].forEach(id => {
        el(id)?.addEventListener('input', pintarCuadreB);
    });

    function pintarDividendos(data) {
        const tbody = el('adi-tbody-dividendos');
        const detalles = data.detalles || [];
        el('adi-badge-detalles').textContent = detalles.length;

        if (!detalles.length) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">' +
                'Sin dividendos registrados. Use «Importar de contabilidad» o «Nuevo dividendo».</td></tr>';
        } else {
            tbody.innerHTML = detalles.map(d => {
                const pagado = d.dividendo_pagado === '01' ? 'Sí' : 'No';
                const benef = estado.beneficiarios.find(b => String(b.id) === String(d.id_beneficiario)) || {};
                return `<tr>
                    <td>${esc(d.secuencial)}</td>
                    <td>
                        <span class="d-block">${esc(d.nombre_beneficiario || '(sin nombre)')}</span>
                        <span class="text-muted small">${esc(d.numero_id_perceptor)}</span>
                    </td>
                    <td>${esc(d.tipo_beneficiario)}</td>
                    <td>${esc(benef.pais_residencia || '')}</td>
                    <td>${esc(d.anio_genera_utilidad)}</td>
                    <td title="${esc(nombreDividendo(d.tipo_dividendo))}">${esc(d.tipo_dividendo)}</td>
                    <td>${esc(fechaCorta(d.fecha_registro_contable))}</td>
                    <td class="text-end">${money(d.monto_dividendo_distribuido)}</td>
                    <td class="text-end">${money(d.ingreso_gravado)}</td>
                    <td class="text-end">${money(d.monto_retencion)}</td>
                    <td>${pagado}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Editar dividendo"
                                onclick="ADI_modalDetalle(${d.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-primary py-0 px-1" title="Editar beneficiario"
                                onclick="ADI_modalBeneficiario(${d.id_beneficiario})"><i class="bi bi-person"></i></button>
                    </td>
                </tr>`;
            }).join('');
        }

        const t = data.totales || {};
        el('adi-resumen-dividendos').innerHTML =
            `${data.beneficiarios.length} beneficiario(s) · Distribuido <strong>${money(t.distribuido)}</strong> · ` +
            `Gravado <strong>${money(t.gravado)}</strong> · Retenido <strong>${money(t.retencion)}</strong>`;
        el('adi-resumen-cabecera').innerHTML =
            `${detalles.length} dividendo(s) · ${money(t.distribuido)} distribuidos`;

        // Beneficiarios sin ningún dividendo: se listan aparte para no perderlos.
        const huerfanos = (data.beneficiarios || []).filter(b => Number(b.total_detalles) === 0);
        const caja = el('adi-sin-tercero');
        if (huerfanos.length) {
            caja.classList.remove('d-none');
            caja.innerHTML = '<strong>Beneficiarios sin dividendos:</strong> ' +
                huerfanos.map(b => `<a href="#" onclick="ADI_modalBeneficiario(${b.id});return false;">` +
                    `${esc(b.nombre_beneficiario || b.numero_id_perceptor)}</a>`).join(', ') +
                '. No se incluyen en el archivo mientras no tengan al menos un dividendo.';
        } else {
            caja.classList.add('d-none');
            caja.innerHTML = '';
        }
    }

    function pintarValidaciones(data) {
        const errores = data.errores || [];
        const advertencias = data.advertencias || [];

        const badge = el('adi-badge-errores');
        badge.textContent = errores.length;
        badge.classList.toggle('d-none', errores.length === 0);

        el('adi-lista-errores').innerHTML = errores.length
            ? '<div class="alert alert-danger adi-nota"><strong><i class="bi bi-x-octagon me-1"></i>' +
              errores.length + ' error(es) que impiden generar el archivo</strong><ul class="mb-0 mt-2">' +
              errores.map(e => `<li>${esc(e)}</li>`).join('') + '</ul></div>'
            : '<div class="alert alert-success adi-nota mb-2"><i class="bi bi-check2-circle me-1"></i>' +
              'Sin errores: el anexo se puede generar.</div>';

        el('adi-lista-advertencias').innerHTML = advertencias.length
            ? '<div class="alert alert-warning adi-nota"><strong><i class="bi bi-exclamation-triangle me-1"></i>' +
              advertencias.length + ' advertencia(s)</strong><ul class="mb-0 mt-2">' +
              advertencias.map(e => `<li>${esc(e)}</li>`).join('') + '</ul></div>'
            : '';
    }

    function pintarEnlaces(anexo) {
        const xml = el('adi-link-xml');
        const zip = el('adi-link-zip');
        const generado = anexo.estado === 'generado' || anexo.estado === 'presentado';
        const url = n => `${URL_BASE}/descargar?archivo=${encodeURIComponent(n)}`;

        xml.href = url(`ADI-${anexo.anio}.xml`);
        zip.href = url(`ADI-${anexo.anio}.zip`);
        xml.classList.toggle('disabled', !generado);
        zip.classList.toggle('disabled', !generado);
    }

    function nombreDividendo(codigo) {
        const def = (CAT.tipo_dividendo || {})[codigo];
        return def ? def[0] : codigo;
    }

    function fechaCorta(f) {
        if (!f) return '';
        const p = String(f).substring(0, 10).split('-');
        return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : f;
    }

    // ── Origen contable ──────────────────────────────────────────────────────

    function cargarCuentas() {
        const id = el('adi-id').value;
        if (!id) return;

        pedir(`${URL_BASE}/cuentasAjax?id=${id}`)
            .then(res => pintarCuentas(res.data))
            .catch(() => {
                el('adi-tabla-cuentas-div').innerHTML =
                    '<tbody><tr><td class="text-danger small">No se pudo cargar el plan de cuentas.</td></tr></tbody>';
            });
    }

    function pintarCuentas(cuentas) {
        // Para no volcar cientos de cuentas, se muestran las sugeridas por el
        // sistema más las que ya estén marcadas en este anexo.
        const div = cuentas.filter(c => c.sugerida_div || c.sel_dividendos);
        const res = cuentas.filter(c => c.sugerida_res || c.sel_resultados);

        el('adi-tabla-cuentas-div').innerHTML = '<tbody>' + (div.length ? div.map(c => `
            <tr>
                <td style="width:26px;">
                    <input class="form-check-input adi-cta-div" type="checkbox" value="${c.id}"
                           ${c.sel_dividendos ? 'checked' : ''}>
                </td>
                <td><span class="text-muted small">${esc(c.codigo)}</span> ${esc(c.nombre)}</td>
                <td style="width:92px;">
                    <select class="form-select form-select-sm adi-cta-div-lado" data-id="${c.id}">
                        <option value="haber" ${c.lado_dividendos === 'haber' ? 'selected' : ''}>Haber</option>
                        <option value="debe" ${c.lado_dividendos === 'debe' ? 'selected' : ''}>Debe</option>
                    </select>
                </td>
            </tr>`).join('') : '<tr><td class="text-muted small">No se encontraron cuentas de dividendos en el plan. ' +
            'Cree una cuenta con «dividendo» en el nombre o mapeada al casillero 2010706 de SuperCías.</td></tr>') + '</tbody>';

        el('adi-tabla-cuentas-res').innerHTML = '<tbody>' + (res.length ? res.map(c => `
            <tr>
                <td style="width:26px;">
                    <input class="form-check-input adi-cta-res" type="checkbox" value="${c.id}"
                           ${c.sel_resultados ? 'checked' : ''}>
                </td>
                <td><span class="text-muted small">${esc(c.codigo)}</span> ${esc(c.nombre)}</td>
            </tr>`).join('') : '<tr><td class="text-muted small">No se encontraron cuentas de resultados acumulados.</td></tr>') + '</tbody>';
    }

    function cuentasSeleccionadas() {
        const div = Array.from(document.querySelectorAll('.adi-cta-div:checked')).map(chk => {
            const lado = document.querySelector(`.adi-cta-div-lado[data-id="${chk.value}"]`);
            return { id: Number(chk.value), lado: lado ? lado.value : 'haber' };
        });
        const res = Array.from(document.querySelectorAll('.adi-cta-res:checked'))
            .map(chk => ({ id: Number(chk.value), lado: 'debe' }));
        return { div, res };
    }

    // ── Guardar / importar / recalcular / generar ────────────────────────────

    /** Payload de la cabecera tal como está en pantalla. */
    function datosCabecera() {
        const cuentas = cuentasSeleccionadas();
        return {
            id: el('adi-id').value,
            // El tipo de informante, el tipo de identificación y la
            // identificación no se envían: el servidor los toma de la empresa
            // activa en cada guardado.
            razon_social: el('adi-razon-social').value.trim(),
            utilidad_ejercicio: el('adi-b1').value,
            utilidad_distribuida_distinta_reinv: el('adi-b2').value,
            utilidad_reinvertida_con_derecho: el('adi-b3').value,
            utilidad_reinvertida_sin_derecho: el('adi-b4').value,
            utilidad_pagada_anticipado: el('adi-b5').value,
            utilidad_no_distrib_ejer_ant: el('adi-b7').value,
            utilidad_distrib_ejercicios_ant: el('adi-b8').value,
            cuentas_dividendos: cuentas.div,
            cuentas_resultados_acum: cuentas.res,
            sbu: el('adi-sbu').value,
            observaciones: el('adi-observaciones').value.trim(),
        };
    }

    /**
     * Guarda la cabecera. Si el anexo todavía no existe, primero lo crea con el
     * año elegido y luego guarda el resto de campos sobre él.
     */
    function guardarCabecera() {
        const id = el('adi-id').value;
        if (id) {
            return post('guardarCabeceraAjax', datosCabecera());
        }

        const anio = el('adi-anio').value;
        if (!anio) {
            return Promise.reject(new Error('Seleccione el año a informar.'));
        }

        return post('abrirAjax', { anio })
            .then(res => {
                // El año ya tenía anexo (puede estar en otra página del
                // listado): se abre el existente sin pisar sus datos.
                if (res.ya_existia) {
                    return { ...res, soloAbrir: true };
                }
                el('adi-id').value = res.data.anexo.id;
                return post('guardarCabeceraAjax', datosCabecera());
            });
    }

    window.ADI_guardarCabecera = function () {
        const esNuevo = !el('adi-id').value;

        guardarCabecera()
            .then(res => {
                pintar(res.data);
                cargarCuentas();

                if (res.soloAbrir) {
                    aviso('Ese período ya existía', res.mensaje, 'info');
                    return;
                }
                aviso('Guardado', esNuevo
                    ? 'Período ' + res.data.anexo.anio + ' creado. Ya puede elegir las cuentas e importar de contabilidad.'
                    : res.mensaje, 'success');
            })
            .catch(e => aviso('No se pudo guardar', e.message, 'error'));
    };

    window.ADI_importar = function () {
        const seguir = () => {
            const boton = el('adi-btn-importar');
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importando...';

            // La importación usa las cuentas guardadas, así que primero se
            // persiste la selección que el usuario tenga en pantalla.
            guardarCabecera()
                .then(previo => {
                    // Si el año resultó ser de un anexo ya existente, no se
                    // importa nada sobre él sin que el usuario lo vea antes.
                    if (previo && previo.soloAbrir) {
                        pintar(previo.data);
                        throw new Error(previo.mensaje);
                    }
                    return post('importarAjax', { id: el('adi-id').value });
                })
                .then(res => {
                    pintar(res.data);
                    mostrarResultadoImportacion(res);
                })
                .catch(e => aviso('No se pudo importar', e.message, 'error'))
                .finally(() => {
                    boton.disabled = false;
                    boton.innerHTML = '<i class="bi bi-cloud-download"></i> Importar de contabilidad';
                });
        };

        if (!window.Swal) { if (confirm('Se reemplazarán los dividendos importados. ¿Continuar?')) seguir(); return; }

        Swal.fire({
            title: 'Importar de contabilidad',
            html: 'Se reemplazarán los dividendos <strong>importados anteriormente</strong> con los asientos del año.<br>' +
                  'Los registros creados o corregidos a mano se conservan.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Importar',
            cancelButtonText: 'Cancelar',
        }).then(r => { if (r.isConfirmed) seguir(); });
    };

    function mostrarResultadoImportacion(res) {
        let html = `<p>${esc(res.mensaje)}</p>`;
        if (res.notas && res.notas.length) {
            html += '<ul class="text-start small">' + res.notas.map(n => `<li>${esc(n)}</li>`).join('') + '</ul>';
        }
        if (res.sin_tercero && res.sin_tercero.length) {
            html += '<div class="alert alert-warning text-start small mt-2"><strong>Movimientos sin beneficiario:</strong>' +
                '<ul class="mb-0 mt-1">' + res.sin_tercero.slice(0, 10).map(m =>
                    `<li>${esc(fechaCorta(m.fecha))} · ${esc(m.asiento)} · ${money(m.monto)} — ${esc(m.concepto)}</li>`
                ).join('') + '</ul>' +
                (res.sin_tercero.length > 10 ? `<span class="text-muted">y ${res.sin_tercero.length - 10} más.</span>` : '') +
                '</div>';
        }
        if (window.Swal) {
            Swal.fire({ title: 'Importación terminada', html, icon: 'success', width: 640 });
        } else {
            alert(res.mensaje);
        }
    }

    window.ADI_recalcular = function () {
        post('recalcularAjax', { id: el('adi-id').value })
            .then(res => {
                pintar(res.data);
                const notas = (res.notas || []).map(n => `<li>${esc(n)}</li>`).join('');
                if (window.Swal) {
                    Swal.fire({
                        title: 'Recálculo terminado',
                        html: `<p>${esc(res.mensaje)}</p>` + (notas ? `<ul class="text-start small">${notas}</ul>` : ''),
                        icon: 'success',
                        width: 620,
                    });
                }
            })
            .catch(e => aviso('No se pudo recalcular', e.message, 'error'));
    };

    window.ADI_generar = function () {
        post('generarAjax', { id: el('adi-id').value })
            .then(res => {
                pintar(res.data);
                let html = `<p>Se generó <strong>${esc(res.xml)}</strong> con ${res.registros} dividendo(s) ` +
                    `de ${res.beneficiarios} beneficiario(s).</p>`;
                if (res.errores && res.errores.length) {
                    html += '<div class="alert alert-danger text-start small">El archivo no valida contra el esquema:' +
                        '<ul class="mb-0 mt-1">' + res.errores.map(e => `<li>${esc(e)}</li>`).join('') + '</ul></div>';
                }
                html += `<div class="d-flex gap-2 justify-content-center mt-3">
                    <a class="btn btn-sm btn-outline-dark" href="${res.url_xml}"><i class="bi bi-download"></i> XML</a>
                    ${res.url_zip ? `<a class="btn btn-sm btn-outline-dark" href="${res.url_zip}"><i class="bi bi-file-zip"></i> ZIP</a>` : ''}
                </div>`;
                if (window.Swal) {
                    Swal.fire({ title: 'Anexo generado', html, icon: 'success', width: 620 });
                } else {
                    window.location.href = res.url_zip || res.url_xml;
                }
            })
            .catch(e => {
                aviso('No se pudo generar', e.message + ' Revise la pestaña «Validaciones».', 'error');
                document.querySelector('#adi-tab-validacion')?.click();
            });
    };

    window.ADI_eliminar = function () {
        const seguir = () => post('eliminarAjax', { id: el('adi-id').value })
            .then(() => {
                estado = null;
                bootstrap.Modal.getInstance(el('modalAdi'))?.hide();
                window.fetchSearch(1);
            })
            .catch(e => aviso('No se pudo eliminar', e.message, 'error'));

        if (!window.Swal) { if (confirm('¿Eliminar el anexo con todo su detalle?')) seguir(); return; }
        Swal.fire({
            title: '¿Eliminar el anexo?',
            text: 'Se eliminan también sus beneficiarios y dividendos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        }).then(r => { if (r.isConfirmed) seguir(); });
    };

    // ── Modal de beneficiario ────────────────────────────────────────────────

    window.ADI_modalBeneficiario = function (id) {
        const b = id ? (estado.beneficiarios || []).find(x => String(x.id) === String(id)) : null;

        el('adi-ben-id').value = b ? b.id : '';
        el('adi-ben-titulo').textContent = b ? 'Editar beneficiario' : 'Nuevo beneficiario';
        el('adi-ben-tipo-id').value = b ? b.tipo_id_perceptor : 'C';
        el('adi-ben-numero-id').value = b ? b.numero_id_perceptor : '';
        el('adi-ben-nombre').value = b ? (b.nombre_beneficiario || '') : '';
        el('adi-ben-tipo-entidad').value = b ? (b.tipo_entidad || '') : '';
        el('adi-ben-id-entidad').value = b ? (b.id_entidad || '') : '';
        el('adi-ben-buscar').value = '';
        el('adi-ben-resultados').classList.add('d-none');

        refrescarTiposBeneficiario(b ? b.tipo_beneficiario : null);
        el('adi-ben-pais').value = b ? b.pais_residencia : '593';
        el('adi-ben-regimen').value = b ? (b.regimen_fiscal_preferente || '') : '';
        el('adi-ben-tipo-efec').value = b ? (b.tipo_id_beneficiario_efectivo || '') : '';
        el('adi-ben-num-efec').value = b ? (b.numero_id_beneficiario_efectivo || '') : '';

        el('adi-ben-btn-eliminar').classList.toggle('d-none', !b || !PERM.eliminar);
        aplicarCondicionalesBeneficiario();
        abrirModalEncima('modalAdiBeneficiario');
    };

    /** Los tipos de beneficiario dependen del tipo de identificación (tabla 2). */
    function refrescarTiposBeneficiario(seleccionado) {
        const tipoId = el('adi-ben-tipo-id').value;
        const permitidos = (CAT.benef_por_tipo_id || {})[tipoId] || [];
        const select = el('adi-ben-tipo');

        select.innerHTML = permitidos.map(cod =>
            `<option value="${cod}">${cod} - ${esc((CAT.tipo_beneficiario || {})[cod] || '')}</option>`
        ).join('');

        if (seleccionado && permitidos.includes(seleccionado)) {
            select.value = seleccionado;
        }
    }

    function aplicarCondicionalesBeneficiario() {
        const tipo = el('adi-ben-tipo').value;
        const esEcuador = (CAT.benef_pais_ecuador || []).includes(tipo);
        const aplicaRegimen = (CAT.benef_regimen || []).includes(tipo);
        const aplicaEfectivo = (CAT.benef_efectivo || []).includes(tipo);

        const pais = el('adi-ben-pais');
        if (esEcuador) { pais.value = '593'; }
        pais.disabled = esEcuador;

        el('adi-ben-wrap-regimen').classList.toggle('d-none', !aplicaRegimen);
        if (!aplicaRegimen) el('adi-ben-regimen').value = '';
        else if (!el('adi-ben-regimen').value) el('adi-ben-regimen').value = '02';

        el('adi-ben-wrap-tipo-efec').classList.toggle('d-none', !aplicaEfectivo);
        el('adi-ben-wrap-num-efec').classList.toggle('d-none', !aplicaEfectivo);
        if (!aplicaEfectivo) { el('adi-ben-tipo-efec').value = ''; el('adi-ben-num-efec').value = ''; }

        el('adi-ben-ayuda').innerHTML = '<i class="bi bi-info-circle me-1"></i>' +
            esc((CAT.tipo_beneficiario || {})[tipo] || '') +
            (esEcuador ? ' · El país queda fijo en Ecuador (593) para este tipo de beneficiario.' : '');
    }

    el('adi-ben-tipo-id')?.addEventListener('change', () => {
        refrescarTiposBeneficiario(null);
        aplicarCondicionalesBeneficiario();
    });
    el('adi-ben-tipo')?.addEventListener('change', aplicarCondicionalesBeneficiario);

    let temporizadorBusqueda = null;
    el('adi-ben-buscar')?.addEventListener('input', function () {
        clearTimeout(temporizadorBusqueda);
        const texto = this.value.trim();
        const caja = el('adi-ben-resultados');

        if (texto.length < 2) { caja.classList.add('d-none'); return; }

        temporizadorBusqueda = setTimeout(() => {
            pedir(`${URL_BASE}/buscarTercerosAjax?q=${encodeURIComponent(texto)}`)
                .then(res => {
                    const filas = res.data || [];
                    if (!filas.length) { caja.classList.add('d-none'); return; }
                    caja.innerHTML = filas.map((t, i) => `
                        <button type="button" class="list-group-item list-group-item-action py-1" data-idx="${i}">
                            <span class="small fw-semibold">${esc(t.nombre)}</span>
                            <span class="text-muted small d-block">${esc(t.identificacion)} · ${esc(t.tipo_entidad)}</span>
                        </button>`).join('');
                    caja.classList.remove('d-none');
                    caja.querySelectorAll('button').forEach(btn => {
                        btn.addEventListener('click', () => {
                            elegirTercero(filas[Number(btn.dataset.idx)]);
                            caja.classList.add('d-none');
                        });
                    });
                })
                .catch(() => caja.classList.add('d-none'));
        }, 250);
    });

    function elegirTercero(t) {
        el('adi-ben-numero-id').value = t.identificacion || '';
        el('adi-ben-nombre').value = t.nombre || '';
        el('adi-ben-tipo-entidad').value = t.tipo_entidad || '';
        el('adi-ben-id-entidad').value = t.id_entidad || '';
        el('adi-ben-buscar').value = t.nombre || '';

        if (t.tipo_id_adi) {
            el('adi-ben-tipo-id').value = t.tipo_id_adi;
            refrescarTiposBeneficiario(t.tipo_beneficiario || null);
            aplicarCondicionalesBeneficiario();
        }
    }

    window.ADI_guardarBeneficiario = function () {
        post('guardarBeneficiarioAjax', {
            id: el('adi-ben-id').value,
            id_anexo: el('adi-id').value,
            tipo_id_perceptor: el('adi-ben-tipo-id').value,
            numero_id_perceptor: el('adi-ben-numero-id').value.trim(),
            nombre_beneficiario: el('adi-ben-nombre').value.trim(),
            tipo_beneficiario: el('adi-ben-tipo').value,
            pais_residencia: el('adi-ben-pais').value,
            regimen_fiscal_preferente: el('adi-ben-regimen').value,
            tipo_id_beneficiario_efectivo: el('adi-ben-tipo-efec').value,
            numero_id_beneficiario_efectivo: el('adi-ben-num-efec').value.trim(),
            tipo_entidad: el('adi-ben-tipo-entidad').value,
            id_entidad: el('adi-ben-id-entidad').value,
        })
            .then(res => {
                pintar(res.data);
                bootstrap.Modal.getInstance(el('modalAdiBeneficiario'))?.hide();
            })
            .catch(e => aviso('No se pudo guardar el beneficiario', e.message, 'error'));
    };

    window.ADI_eliminarBeneficiario = function () {
        const seguir = () => post('eliminarBeneficiarioAjax', {
            id: el('adi-ben-id').value,
            id_anexo: el('adi-id').value,
        })
            .then(res => {
                pintar(res.data);
                bootstrap.Modal.getInstance(el('modalAdiBeneficiario'))?.hide();
            })
            .catch(e => aviso('No se pudo eliminar', e.message, 'error'));

        if (!window.Swal) { if (confirm('¿Eliminar el beneficiario y sus dividendos?')) seguir(); return; }
        Swal.fire({
            title: '¿Eliminar el beneficiario?',
            text: 'Se eliminan también todos sus dividendos del anexo.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        }).then(r => { if (r.isConfirmed) seguir(); });
    };

    // ── Modal de dividendo ───────────────────────────────────────────────────

    window.ADI_modalDetalle = function (id) {
        if (!estado || !(estado.beneficiarios || []).length) {
            aviso('Falta el beneficiario', 'Registre primero al menos un beneficiario del dividendo.', 'info');
            return;
        }

        const d = id ? (estado.detalles || []).find(x => String(x.id) === String(id)) : null;
        const anio = Number(estado.anexo.anio);

        el('adi-det-id').value = d ? d.id : '';
        el('adi-det-titulo').textContent = d ? 'Editar dividendo distribuido' : 'Nuevo dividendo distribuido';

        el('adi-det-beneficiario').innerHTML = estado.beneficiarios.map(b =>
            `<option value="${b.id}">${esc(b.numero_id_perceptor)} — ${esc(b.nombre_beneficiario || '(sin nombre)')}</option>`
        ).join('');
        if (d) el('adi-det-beneficiario').value = d.id_beneficiario;

        el('adi-det-anio').value = d ? d.anio_genera_utilidad : (anio - 1);
        el('adi-det-fecha').value = d ? String(d.fecha_registro_contable).substring(0, 10) : `${anio}-12-31`;
        el('adi-det-monto').value = d ? parseFloat(d.monto_dividendo_distribuido).toFixed(2) : '';
        el('adi-det-gravado').value = d ? parseFloat(d.ingreso_gravado).toFixed(2) : '0.00';
        el('adi-det-retencion').value = d ? parseFloat(d.monto_retencion).toFixed(2) : '0.00';
        el('adi-det-pagado').value = d ? d.dividendo_pagado : '02';
        el('adi-det-isd').value = d ? parseFloat(d.isd_pagado).toFixed(2) : '0.00';

        refrescarTiposDividendo(d ? d.tipo_dividendo : null);
        el('adi-det-btn-eliminar').classList.toggle('d-none', !d || !PERM.eliminar);
        abrirModalEncima('modalAdiDetalle');
    };

    /** Los tipos de dividendo dependen del beneficiario y del año (tabla 9). */
    function refrescarTiposDividendo(seleccionado) {
        const idBenef = el('adi-det-beneficiario').value;
        const benef = (estado.beneficiarios || []).find(b => String(b.id) === String(idBenef));
        const anio = Number(estado.anexo.anio);
        const select = el('adi-det-tipo');

        if (!benef) { select.innerHTML = ''; return; }

        const opciones = Object.entries(CAT.tipo_dividendo || {}).filter(([cod, def]) => {
            const [, desde, hasta, tipos] = def;
            if (desde !== null && anio < desde) return false;
            if (hasta !== null && anio > hasta) return false;
            return (tipos || []).includes(benef.tipo_beneficiario);
        });

        select.innerHTML = opciones.map(([cod, def]) => `<option value="${cod}">${cod} - ${esc(def[0])}</option>`).join('');
        if (seleccionado && opciones.some(([cod]) => cod === seleccionado)) {
            select.value = seleccionado;
        }
        pintarAyudaDetalle();
    }

    function pintarAyudaDetalle() {
        const cod = el('adi-det-tipo').value;
        const def = (CAT.tipo_dividendo || {})[cod];
        const gravado = def ? def[4] : false;
        el('adi-det-ayuda').innerHTML = '<i class="bi bi-info-circle me-1"></i>' +
            (def ? esc(def[0]) : '') +
            (gravado
                ? ' · Dividendo <strong>gravado</strong>: registre el ingreso gravado y la retención.'
                : ' · Dividendo <strong>exento</strong>: el ingreso gravado y la retención van en 0,00.');
    }

    el('adi-det-beneficiario')?.addEventListener('change', () => refrescarTiposDividendo(null));
    el('adi-det-tipo')?.addEventListener('change', pintarAyudaDetalle);

    /**
     * Sugerencia local del ingreso gravado y la retención, con las mismas reglas
     * del servidor. Es una ayuda de captura; el cálculo definitivo lo hace
     * «Recalcular», que además considera el acumulado anual del beneficiario.
     */
    window.ADI_sugerirCalculo = function () {
        const cod = el('adi-det-tipo').value;
        const def = (CAT.tipo_dividendo || {})[cod];
        const monto = parseFloat(el('adi-det-monto').value) || 0;
        const fecha = el('adi-det-fecha').value;

        if (!def || !def[4]) {
            el('adi-det-gravado').value = '0.00';
            el('adi-det-retencion').value = '0.00';
            pintarAyudaDetalle();
            return;
        }

        const idBenef = el('adi-det-beneficiario').value;
        const benef = (estado.beneficiarios || []).find(b => String(b.id) === String(idBenef)) || {};
        const sbu = parseFloat(estado.anexo.sbu) || 0;
        const impuestoUnico = fecha >= '2025-09-01';

        let gravado;
        if (!impuestoUnico) {
            gravado = monto * 0.40;
        } else if (benef.tipo_beneficiario === '01' && sbu > 0) {
            gravado = Math.max(monto - sbu * 3, 0);
        } else {
            gravado = monto;
        }

        const residente = ['01', '03', '04', '05', '06'].includes(benef.tipo_beneficiario);
        const paraiso = ['09', '11'].includes(benef.tipo_beneficiario) || cod === '17';
        let tarifa;
        if (impuestoUnico) {
            tarifa = paraiso ? 14 : (residente ? 12 : 10);
        } else {
            tarifa = paraiso ? 35 : (benef.tipo_beneficiario === '01' ? 0 : 25);
        }

        el('adi-det-gravado').value = gravado.toFixed(2);
        el('adi-det-retencion').value = (gravado * tarifa / 100).toFixed(2);

        el('adi-det-ayuda').innerHTML = '<i class="bi bi-magic me-1"></i>Sugerencia: ingreso gravado ' +
            money(gravado) + ' y retención al ' + tarifa + ' %. ' +
            (!impuestoUnico && benef.tipo_beneficiario === '01'
                ? 'Para personas naturales residentes antes de septiembre de 2025 la retención sigue la tabla ' +
                  'progresiva anual: use «Recalcular» para aplicarla sobre el acumulado del año.'
                : 'Verifíquela antes de presentar.');
    };

    window.ADI_guardarDetalle = function () {
        post('guardarDetalleAjax', {
            id: el('adi-det-id').value,
            id_anexo: el('adi-id').value,
            id_beneficiario: el('adi-det-beneficiario').value,
            anio_genera_utilidad: el('adi-det-anio').value,
            tipo_dividendo: el('adi-det-tipo').value,
            fecha_registro_contable: el('adi-det-fecha').value,
            monto_dividendo_distribuido: el('adi-det-monto').value,
            ingreso_gravado: el('adi-det-gravado').value,
            monto_retencion: el('adi-det-retencion').value,
            dividendo_pagado: el('adi-det-pagado').value,
            isd_pagado: el('adi-det-isd').value,
        })
            .then(res => {
                pintar(res.data);
                bootstrap.Modal.getInstance(el('modalAdiDetalle'))?.hide();
            })
            .catch(e => aviso('No se pudo guardar el dividendo', e.message, 'error'));
    };

    window.ADI_eliminarDetalle = function () {
        const seguir = () => post('eliminarDetalleAjax', {
            id: el('adi-det-id').value,
            id_anexo: el('adi-id').value,
        })
            .then(res => {
                pintar(res.data);
                bootstrap.Modal.getInstance(el('modalAdiDetalle'))?.hide();
            })
            .catch(e => aviso('No se pudo eliminar', e.message, 'error'));

        if (!window.Swal) { if (confirm('¿Eliminar el dividendo?')) seguir(); return; }
        Swal.fire({
            title: '¿Eliminar el dividendo?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        }).then(r => { if (r.isConfirmed) seguir(); });
    };
})();
