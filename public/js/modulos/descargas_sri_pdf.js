/**
 * Descargas del SRI → pestaña «Desde PDF».
 *
 * 1. leerArchivosPdf(): sube cada PDF, el servidor lo lee con IA y devuelve una propuesta.
 * 2. rpAbrir(): el usuario revisa y corrige en la ventana #modalRevisionPdf.
 * 3. rpRegistrar(): el servidor valida, comprueba que no exista y registra.
 *
 * Aquí no se calcula ni se redondea ningún valor: los importes son los del PDF y
 * las sumas las comprueba el servidor (DescargaPdfRules).
 */
(function () {
    'use strict';

    const CAT = window.CMG_PDF_CAT || { tipos: {}, iva: [], pagos: {}, sustentos: {}, maxBytes: 5242880 };
    const URL_LEER = `${BASE_URL}/modulos/descargas_sri/leerPdfSriAjax`;
    const URL_REGISTRAR = `${BASE_URL}/modulos/descargas_sri/registrarPdfSriAjax`;
    const MAX_ARCHIVOS = 10;

    /** Cola de archivos: {id, file, estado, lectura, mensaje}. estado: pendiente|leyendo|listo|registrado|existe|error */
    let cola = [];
    let seq = 0;
    let leyendo = false;
    let actual = null;          // elemento de la cola abierto en la ventana
    let registrando = false;
    let opcionesRet = [];       // opciones de código de retención por línea (las manda el servidor)

    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const num = (v, dec = 2) => {
        const n = Number(v);
        return Number.isFinite(n) ? n.toFixed(dec) : '';
    };
    /** Cantidades y precios: hasta 6 decimales, sin ceros de relleno. */
    const numLibre = (v) => {
        const n = Number(v);
        if (!Number.isFinite(n)) return '';
        return n.toFixed(6).replace(/0+$/, '').replace(/\.$/, '') || '0';
    };

    // ── IA de otra empresa (solo superadministrador) ───────────────────────
    // La elección vale mientras la página esté abierta; no se guarda. El servidor
    // ignora el parámetro si el usuario no es de nivel 3.
    let idEmpresaIa = 0;

    window.elegirIaOtraEmpresa = async function () {
        const empresas = CAT.empresasIa || [];
        if (!empresas.length) return;
        const opciones = {};
        empresas.forEach((e) => { opciones[e.id_empresa] = `${e.nombre} (${e.ruc})`; });
        const r = await Swal.fire({
            title: 'Usar IA de otra empresa',
            html: '<div class="small text-muted">Los PDF se leerán con la cuenta de IA de la empresa que elija, y el consumo se cargará a esa cuenta. El comprobante se registra en la empresa actual.</div>',
            input: 'select',
            inputOptions: opciones,
            inputValue: idEmpresaIa || '',
            inputPlaceholder: 'Elija la empresa…',
            showCancelButton: true,
            confirmButtonText: 'Usar su IA',
            cancelButtonText: 'Cancelar',
            inputValidator: (v) => (v ? undefined : 'Elija una empresa'),
        });
        if (!r.isConfirmed) return;
        idEmpresaIa = parseInt(r.value, 10) || 0;
        const elegida = empresas.find((e) => e.id_empresa === idEmpresaIa);
        $('pdf_ia_prestada_nombre').textContent = elegida ? elegida.nombre : '';
        $('pdf_ia_prestada').classList.toggle('d-none', !elegida);
    };

    window.quitarIaOtraEmpresa = function () {
        idEmpresaIa = 0;
        $('pdf_ia_prestada').classList.add('d-none');
    };

    // ── Paso 1: leer ────────────────────────────────────────────────────────

    window.leerArchivosPdf = async function () {
        if (leyendo) return;
        const input = $('archivos_pdf_input');
        const archivos = Array.from(input.files || []);
        if (archivos.length === 0) {
            Swal.fire('Atención', 'Seleccione al menos un archivo PDF.', 'warning');
            return;
        }
        if (archivos.length > MAX_ARCHIVOS) {
            Swal.fire('Atención', `Puede leer hasta ${MAX_ARCHIVOS} PDF a la vez.`, 'warning');
            return;
        }
        const grande = archivos.find((f) => f.size > CAT.maxBytes);
        if (grande) {
            Swal.fire('Atención', `«${grande.name}» pesa más de ${Math.round(CAT.maxBytes / 1048576)} MB.`, 'warning');
            return;
        }

        leyendo = true;
        const btn = $('btnLeerPdf');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Leyendo...';

        const tipo = $('pdf_tipo_documento').value;
        const nuevos = archivos.map((file) => ({ id: ++seq, file, estado: 'pendiente', lectura: null, mensaje: '' }));
        cola = nuevos.concat(cola);
        pintarCola();

        try {
            for (const item of nuevos) {
                item.estado = 'leyendo';
                pintarCola();
                try {
                    const fd = new FormData();
                    fd.append('archivo_pdf', item.file);
                    fd.append('tipo', tipo);
                    if (idEmpresaIa) fd.append('id_empresa_ia', String(idEmpresaIa));
                    const res = await fetch(URL_LEER, { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.ok) {
                        item.lectura = data;
                        item.estado = 'listo';
                    } else {
                        item.estado = 'error';
                        item.mensaje = data.error || 'No se pudo leer el PDF.';
                    }
                } catch (err) {
                    console.error(err);
                    item.estado = 'error';
                    item.mensaje = 'Problema de conexión al leer el PDF.';
                }
                pintarCola();
            }
        } finally {
            leyendo = false;
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Leer PDF';
            input.value = '';
        }

        // Con un solo archivo leído, se abre directo para revisar.
        const listos = nuevos.filter((i) => i.estado === 'listo');
        if (nuevos.length === 1 && listos.length === 1) {
            window.rpAbrir(listos[0].id);
        } else if (nuevos.length === 1) {
            Swal.fire('No se pudo leer', nuevos[0].mensaje, 'error');
        }
    };

    function pintarCola() {
        $('pdf_cola_wrap').classList.toggle('d-none', cola.length === 0);
        const badge = {
            pendiente:  ['secondary', 'En espera'],
            leyendo:    ['info', 'Leyendo…'],
            listo:      ['warning', 'Por revisar'],
            registrado: ['success', 'Registrado'],
            existe:     ['primary', 'Ya existía'],
            error:      ['danger', 'Error'],
        };
        $('pdf_cola_tbody').innerHTML = cola.map((i) => {
            const [color, texto] = badge[i.estado];
            const d = i.lectura ? i.lectura.doc : null;
            const docTxt = d
                ? `${esc(CAT.tipos[d.cod_doc] || 'Documento')} ${esc([d.estab, d.pto_emi, d.secuencial].filter(Boolean).join('-'))}`
                    + `<div class="text-muted">${esc(d.emisor.razon_social)}</div>`
                : (i.mensaje ? `<span class="text-danger">${esc(i.mensaje)}</span>` : '');
            const accion = i.estado === 'listo'
                ? `<button type="button" class="btn btn-warning btn-sm py-0 px-2" style="font-size:.75rem" onclick="rpAbrir(${i.id})"><i class="bi bi-pencil-square me-1"></i>Revisar</button>`
                : (i.estado === 'leyendo' ? '<span class="spinner-border spinner-border-sm text-info"></span>' : '');
            return `<tr>
                <td class="ps-2 text-break" style="max-width:190px">${esc(i.file.name)}</td>
                <td>${docTxt}${i.estado !== 'error' && i.mensaje ? `<div class="text-muted">${esc(i.mensaje)}</div>` : ''}</td>
                <td class="text-center"><span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25">${texto}</span></td>
                <td class="text-end pe-2">${accion}</td>
            </tr>`;
        }).join('');
    }

    // ── Paso 2: revisar ─────────────────────────────────────────────────────

    window.rpAbrir = function (id) {
        const item = cola.find((i) => i.id === id);
        if (!item || !item.lectura) return;
        actual = item;
        $('rp_archivo').textContent = item.file.name;
        llenarFormulario(item.lectura.doc, item.lectura.opciones_retencion);
        pintarMensajes(item.lectura);
        bootstrap.Modal.getOrCreateInstance($('modalRevisionPdf')).show();
    };

    window.rpVerPdf = function () {
        if (!actual) return;
        const url = URL.createObjectURL(actual.file);
        window.open(url, '_blank');
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    };

    const CAMPOS = {
        cod_doc: 'rp_cod_doc', estab: 'rp_estab', pto_emi: 'rp_pto_emi', secuencial: 'rp_secuencial',
        fecha_emision: 'rp_fecha_emision', fecha_autorizacion: 'rp_fecha_autorizacion',
        clave_acceso: 'rp_clave_acceso', numero_autorizacion: 'rp_numero_autorizacion', periodo_fiscal: 'rp_periodo_fiscal',
    };
    const CAMPOS_EMISOR = {
        ruc: 'rp_em_ruc', razon_social: 'rp_em_razon_social', nombre_comercial: 'rp_em_nombre_comercial',
        dir_matriz: 'rp_em_dir_matriz', dir_establecimiento: 'rp_em_dir_establecimiento', obligado_contabilidad: 'rp_em_obligado',
    };
    const CAMPOS_RECEPTOR = { identificacion: 'rp_re_identificacion', razon_social: 'rp_re_razon_social', direccion: 'rp_re_direccion' };
    const CAMPOS_MOD = { cod_doc: 'rp_mod_cod_doc', numero: 'rp_mod_numero', fecha: 'rp_mod_fecha', motivo: 'rp_mod_motivo' };
    const CAMPOS_TOT = { subtotal: 'rp_tot_subtotal', descuento: 'rp_tot_descuento', propina: 'rp_tot_propina', ice: 'rp_tot_ice', total: 'rp_tot_total' };
    const TABLAS = ['detalles', 'motivos', 'retenciones', 'impuestos', 'pagos', 'info_adicional'];

    function llenarFormulario(doc, opciones) {
        opcionesRet = opciones || [];
        Object.entries(CAMPOS).forEach(([k, id]) => { $(id).value = doc[k] ?? ''; });
        Object.entries(CAMPOS_EMISOR).forEach(([k, id]) => { $(id).value = doc.emisor[k] ?? ''; });
        Object.entries(CAMPOS_RECEPTOR).forEach(([k, id]) => { $(id).value = doc.receptor[k] ?? ''; });
        Object.entries(CAMPOS_MOD).forEach(([k, id]) => { $(id).value = doc.doc_modificado[k] ?? ''; });
        if (!$('rp_mod_cod_doc').value) $('rp_mod_cod_doc').value = '01';
        Object.entries(CAMPOS_TOT).forEach(([k, id]) => { $(id).value = num(doc.totales[k]); });
        TABLAS.forEach((t) => {
            $('rp_tb_' + t).innerHTML = '';
            (doc[t] || []).forEach((fila, i) => agregarFila(t, fila, i));
        });
        window.rpAjustarSecciones();
    }

    const inTxt = (campo, valor, extra = '') =>
        `<input type="text" class="form-control form-control-sm border-0" data-c="${campo}" value="${esc(valor)}" ${extra}>`;
    const inNum = (campo, valor) =>
        `<input type="text" class="form-control form-control-sm border-0 text-end" data-c="${campo}" value="${esc(valor)}" inputmode="decimal">`;
    const sel = (campo, opciones, valor) =>
        `<select class="form-select form-select-sm border-0" data-c="${campo}">${opciones.map(([v, t]) =>
            `<option value="${esc(v)}"${String(v) === String(valor) ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`;
    const btnQuitar = '<td class="text-center align-middle"><a href="#" class="text-danger" title="Quitar" onclick="this.closest(\'tr\').remove();return false;"><i class="bi bi-x-lg" style="font-size:.7rem"></i></a></td>';

    function agregarFila(tabla, f = {}, indice = -1) {
        const tr = document.createElement('tr');
        if (tabla === 'detalles') {
            tr.innerHTML = `<td>${inTxt('codigo', f.codigo ?? '', 'maxlength="25"')}</td>
                <td>${inTxt('descripcion', f.descripcion ?? '', 'maxlength="300"')}</td>
                <td>${inNum('cantidad', f.cantidad != null ? numLibre(f.cantidad) : '1')}</td>
                <td>${inNum('precio_unitario', f.precio_unitario != null ? numLibre(f.precio_unitario) : '')}</td>
                <td>${inNum('descuento', num(f.descuento ?? 0))}</td>
                <td>${inNum('subtotal', f.subtotal != null ? num(f.subtotal) : '')}</td>
                <td>${sel('cod_iva', CAT.iva.map((o) => [o.cod, o.nombre]), f.cod_iva ?? '4')}</td>${btnQuitar}`;
            tr.dataset.aux = f.codigo_auxiliar ?? '';
        } else if (tabla === 'motivos') {
            tr.innerHTML = `<td>${inTxt('razon', f.razon ?? '', 'maxlength="300"')}</td><td>${inNum('valor', f.valor != null ? num(f.valor) : '')}</td>${btnQuitar}`;
        } else if (tabla === 'impuestos') {
            tr.innerHTML = `<td>${sel('cod_iva', CAT.iva.map((o) => [o.cod, o.nombre]), f.cod_iva ?? '4')}</td>
                <td>${inNum('base', f.base != null ? num(f.base) : '')}</td><td>${inNum('valor', f.valor != null ? num(f.valor) : '')}</td>${btnQuitar}`;
        } else if (tabla === 'pagos') {
            tr.innerHTML = `<td>${sel('forma_pago', Object.entries(CAT.pagos).map(([c, n]) => [String(c).padStart(2, '0'), `${String(c).padStart(2, '0')} - ${n}`]), f.forma_pago ?? '20')}</td>
                <td>${inNum('total', f.total != null ? num(f.total) : '')}</td><td>${inNum('plazo', f.plazo ?? 0)}</td>${btnQuitar}`;
            tr.dataset.unidad = f.unidad_tiempo ?? 'dias';
        } else if (tabla === 'info_adicional') {
            tr.innerHTML = `<td>${inTxt('nombre', f.nombre ?? '', 'maxlength="300"')}</td><td>${inTxt('valor', f.valor ?? '', 'maxlength="300"')}</td>${btnQuitar}`;
        } else if (tabla === 'retenciones') {
            const ops = (indice >= 0 && opcionesRet[indice]) ? opcionesRet[indice] : [];
            const lista = `rp_dl_ret_${Date.now()}_${Math.floor(Math.random() * 1e6)}`;
            tr.innerHTML = `<td>${sel('impuesto', [['RENTA', 'Renta'], ['IVA', 'IVA'], ['ISD', 'ISD']], f.impuesto ?? 'RENTA')}</td>
                <td>${inTxt('codigo_retencion', f.codigo_retencion ?? '', `maxlength="10" list="${lista}" placeholder="${ops.length ? 'Elija el código…' : 'Código'}"`)}
                    <datalist id="${lista}">${ops.map((o) => `<option value="${esc(o.codigo)}">${esc(o.concepto)}</option>`).join('')}</datalist></td>
                <td>${inNum('base', f.base != null ? num(f.base) : '')}</td>
                <td>${inNum('porcentaje', f.porcentaje != null ? num(f.porcentaje) : '')}</td>
                <td>${inNum('valor', f.valor != null ? num(f.valor) : '')}</td>
                <td>${sel('cod_doc_sustento', [['', '']].concat(Object.entries(CAT.sustentos).map(([c, n]) => [String(c).padStart(2, '0'), `${String(c).padStart(2, '0')} - ${n}`])), f.cod_doc_sustento ?? '')}</td>
                <td>${inTxt('num_doc_sustento', f.num_doc_sustento ?? '', 'maxlength="17" placeholder="001-001-000000001"')}</td>
                <td>${inTxt('fecha_doc_sustento', f.fecha_doc_sustento ?? '', 'maxlength="10" placeholder="dd/mm/aaaa"')}</td>${btnQuitar}`;
            if (ops.length) {
                const concepto = ops.find((o) => o.codigo === (f.codigo_retencion ?? ''));
                tr.title = concepto ? concepto.concepto : 'Códigos posibles: ' + ops.map((o) => o.codigo).join(', ');
            }
        }
        $('rp_tb_' + tabla).appendChild(tr);
    }
    window.rpAgregarFila = (tabla) => agregarFila(tabla);

    /** Muestra solo las secciones del tipo de documento elegido. */
    window.rpAjustarSecciones = function () {
        const cod = $('rp_cod_doc').value;
        document.querySelectorAll('#modalRevisionPdf [data-rp-tipos]').forEach((el) => {
            el.classList.toggle('d-none', !el.dataset.rpTipos.split(',').includes(cod));
        });
        $('rp_titulo_receptor').textContent = cod === '03' ? 'Proveedor' : (cod === '07' ? 'Sujeto retenido' : 'Comprador');
    };

    function leerFormulario() {
        const val = (id) => $(id).value.trim();
        const mapa = (campos) => Object.fromEntries(Object.entries(campos).map(([k, id]) => [k, val(id)]));
        const filas = (tabla, extra) => Array.from($('rp_tb_' + tabla).querySelectorAll('tr')).map((tr) => {
            const f = {};
            tr.querySelectorAll('[data-c]').forEach((el) => { f[el.dataset.c] = el.value.trim(); });
            return extra ? Object.assign(f, extra(tr)) : f;
        });
        const cod = val('rp_cod_doc');
        const visible = (tipos) => tipos.includes(cod);

        const doc = mapa(CAMPOS);
        doc.emisor = mapa(CAMPOS_EMISOR);
        doc.receptor = mapa(CAMPOS_RECEPTOR);
        doc.doc_modificado = visible(['04', '05']) ? mapa(CAMPOS_MOD) : {};
        doc.totales = mapa(CAMPOS_TOT);
        // Solo viajan las secciones que el tipo de documento usa.
        doc.detalles = visible(['01', '03', '04']) ? filas('detalles', (tr) => ({ codigo_auxiliar: tr.dataset.aux || '' })) : [];
        doc.motivos = visible(['05']) ? filas('motivos') : [];
        doc.retenciones = visible(['07']) ? filas('retenciones') : [];
        doc.impuestos = visible(['01', '03', '04', '05']) ? filas('impuestos') : [];
        doc.pagos = visible(['01', '03', '05']) ? filas('pagos', (tr) => ({ unidad_tiempo: tr.dataset.unidad || 'dias' })) : [];
        doc.info_adicional = filas('info_adicional');
        if (!visible(['07'])) doc.periodo_fiscal = '';
        return doc;
    }

    function pintarMensajes(r) {
        const bloque = (color, icono, titulo, items) => (items && items.length)
            ? `<div class="alert alert-${color} py-2 px-3 mb-2 small"><div class="fw-bold mb-1"><i class="bi ${icono} me-1"></i>${titulo}</div><ul>${items.map((t) => `<li>${esc(t)}</li>`).join('')}</ul></div>`
            : '';
        let html = '';
        if (r.existe) {
            html += `<div class="alert alert-primary py-2 px-3 mb-2 small"><i class="bi bi-info-circle me-1"></i><strong>${esc(r.mensaje_existe)}</strong></div>`;
        }
        html += bloque('danger', 'bi-x-octagon', 'Corrija antes de registrar', r.errores);
        html += bloque('warning', 'bi-exclamation-triangle', 'Revise estas diferencias', r.avisos);
        const notas = (r.notas || []).concat(r.observaciones ? ['Lectura del PDF: ' + r.observaciones] : []);
        html += bloque('info', 'bi-robot', 'Notas de la lectura', notas);
        if (!html) {
            html = '<div class="alert alert-success py-2 px-3 mb-2 small"><i class="bi bi-check-circle me-1"></i>Los datos son coherentes. Compárelos con el PDF antes de registrar.</div>';
        }
        $('rp_mensajes').innerHTML = html;
        $('rp_btn_registrar').disabled = !!r.existe;
    }

    async function enviar(extra) {
        const fd = new FormData();
        fd.append('documento', JSON.stringify(leerFormulario()));
        fd.append('token_pdf', actual.lectura.token_pdf || '');
        Object.entries(extra || {}).forEach(([k, v]) => fd.append(k, v));
        const res = await fetch(URL_REGISTRAR, { method: 'POST', body: fd });
        return res.json();
    }

    window.rpVerificar = async function () {
        if (!actual || registrando) return;
        const btn = $('rp_btn_verificar');
        btn.disabled = true;
        try {
            const data = await enviar({ solo_revisar: '1' });
            if (data.ok) {
                // Se conservan las notas de la lectura original; cambian errores, avisos y opciones.
                actual.lectura = Object.assign({}, actual.lectura, data);
                llenarFormulario(data.doc, data.opciones_retencion);
                pintarMensajes(actual.lectura);
                $('modalRevisionPdf').querySelector('.modal-body').scrollTop = 0;
            } else {
                Swal.fire('Error', (data.errores || ['No se pudo verificar.']).join('\n'), 'error');
            }
        } catch (err) {
            console.error(err);
            Swal.fire('Error', 'Problema de conexión con el servidor.', 'error');
        } finally {
            btn.disabled = false;
        }
    };

    // ── Paso 3: registrar ───────────────────────────────────────────────────

    window.rpRegistrar = async function () {
        if (!actual || registrando) return;
        registrando = true;
        const btn = $('rp_btn_registrar');
        const htmlBtn = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Registrando...';
        let terminado = false;

        try {
            let data = await enviar();

            if (!data.ok && data.requiere_confirmacion) {
                const conf = await Swal.fire({
                    icon: 'warning',
                    title: 'Hay diferencias en los valores',
                    html: `<ul class="text-start small mb-2">${data.avisos.map((t) => `<li>${esc(t)}</li>`).join('')}</ul>¿Registrar el comprobante de todos modos?`,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, registrar',
                    cancelButtonText: 'Volver a revisar',
                });
                if (!conf.isConfirmed) {
                    actual.lectura = Object.assign({}, actual.lectura, { avisos: data.avisos, errores: [] });
                    pintarMensajes(actual.lectura);
                    return;
                }
                data = await enviar({ confirmar_avisos: '1' });
            }

            if (!data.ok) {
                actual.lectura = Object.assign({}, actual.lectura, { errores: data.errores || ['No se pudo registrar.'], avisos: data.avisos || [] });
                pintarMensajes(actual.lectura);
                $('modalRevisionPdf').querySelector('.modal-body').scrollTop = 0;
                return;
            }

            terminado = true;
            actual.estado = data.existe ? 'existe' : 'registrado';
            actual.mensaje = data.mensaje || '';
            pintarCola();
            // La fila aparece en «Resultados del Procesamiento», como las demás cargas.
            renderResultados([{
                clave: data.clave, estado: 'AUTORIZADO', ok: true,
                estado_registro: data.estado_registro, mensaje: data.mensaje,
                info: data, xml_base64: null,
            }]);
            bootstrap.Modal.getOrCreateInstance($('modalRevisionPdf')).hide();
            Swal.fire({
                icon: data.existe ? 'info' : 'success',
                title: data.existe ? 'Ya estaba registrado' : 'Registrado',
                text: data.mensaje || 'Comprobante registrado.',
            });
            actual = null;
        } catch (err) {
            console.error(err);
            Swal.fire('Error de conexión', 'No se supo si el comprobante se registró. Pulse «Verificar datos»: si ya quedó registrado, el sistema lo indicará y no lo creará otra vez.', 'error');
        } finally {
            registrando = false;
            btn.innerHTML = htmlBtn;
            // Tras registrar, el botón no vuelve a quedar activo para este comprobante.
            btn.disabled = terminado;
        }
    };
})();
