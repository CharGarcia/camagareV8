/**
 * Descargas del SRI → pestañas «Desde PDF» e «Ingreso manual».
 *
 * 1. leerArchivosPdf(): sube cada PDF, el servidor lo lee con IA y devuelve una propuesta.
 *    rpNuevoManual(): abre la misma ventana vacía (sin IA), solo para comprobantes emitidos.
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
        $('pdf_cola_count').textContent = cola.length;
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
                ? `<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="rpAbrir(${i.id})"><i class="bi bi-pencil-square me-1"></i>Revisar</button>`
                : (i.estado === 'leyendo' ? '<span class="spinner-border spinner-border-sm text-info"></span>' : '');
            return `<tr>
                <td class="ps-3 text-break" style="max-width:190px">${esc(i.file.name)}</td>
                <td>${docTxt}${i.estado !== 'error' && i.mensaje ? `<div class="text-muted">${esc(i.mensaje)}</div>` : ''}</td>
                <td class="text-center"><span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25">${texto}</span></td>
                <td class="text-end pe-3">${accion}</td>
            </tr>`;
        }).join('');
    }

    // ── Paso 2: revisar ─────────────────────────────────────────────────────

    window.rpAbrir = function (id) {
        const item = cola.find((i) => i.id === id);
        if (!item || !item.lectura) return;
        actual = item;
        ajustarModo(false, item.file);
        llenarFormulario(item.lectura.doc, item.lectura.opciones_retencion);
        pintarMensajes(item.lectura);
        bootstrap.Modal.getOrCreateInstance($('modalRevisionPdf')).show();
    };

    /** La misma ventana sirve para «Desde PDF» (leído con IA) y para el «Ingreso manual». */
    function ajustarModo(manual, file) {
        $('rp_titulo_texto').textContent = manual ? 'Ingresar comprobante emitido' : 'Revisar comprobante leído del PDF';
        $('rp_archivo').textContent = file ? file.name : '';
        $('rp_btn_ver_pdf').classList.toggle('d-none', !file);
        $('rp_sep_ver_pdf').classList.toggle('d-none', !file);
        $('rp_ayuda').textContent = manual
            ? 'Escriba primero la clave de acceso: completa el tipo, el número y la fecha. Solo comprobantes emitidos por esta empresa.'
            : 'Compare estos datos con el PDF, corrija lo que haga falta y luego registre.';
        // En el ingreso manual el emisor es siempre la empresa: su RUC no se cambia.
        $('rp_em_ruc').readOnly = manual;
        // Ingreso manual: el emisor es la empresa (datos de Empresas), no se muestra ni se digita.
        $('rp_col_emisor').classList.toggle('d-none', manual);
        $('rp_col_receptor').classList.toggle('col-lg-6', !manual);
        $('rp_col_receptor').classList.toggle('col-12', manual);
        $('rp_tot_ivas').innerHTML = '';
    }

    /** Lleva la vista al inicio de la ventana (avisos). La ventana no tiene scroll propio: se desplaza completa. */
    function subirAlInicio() {
        $('modalRevisionPdf').scrollTop = 0;
    }

    // ── Ingreso manual (sin IA): solo comprobantes emitidos por la empresa ──

    function docVacio(codDoc) {
        const emisor = Object.assign({ ruc: '', razon_social: '', nombre_comercial: '', dir_matriz: '', dir_establecimiento: '', obligado_contabilidad: '' },
            CAT.emisorManual || {});
        return {
            cod_doc: codDoc, estab: '', pto_emi: '', secuencial: '', fecha_emision: '', fecha_autorizacion: '',
            clave_acceso: '', numero_autorizacion: '', periodo_fiscal: '',
            emisor,
            receptor: { tipo_identificacion: '', identificacion: '', razon_social: '', direccion: '' },
            doc_modificado: { cod_doc: '01', numero: '', fecha: '', motivo: '' },
            totales: { subtotal: '', descuento: 0, propina: 0, ice: 0, total: '' },
            detalles: [{}], motivos: [{}], retenciones: [{}], impuestos: [{}], pagos: [{}], info_adicional: [],
        };
    }

    window.rpNuevoManual = function () {
        const input = $('manual_pdf_input');
        const file = (input.files || [])[0] || null;
        if (file && !/\.pdf$/i.test(file.name)) {
            Swal.fire('Atención', 'El respaldo debe ser un archivo PDF (.pdf).', 'warning');
            input.value = '';
            return;
        }
        if (file && file.size > CAT.maxBytes) {
            Swal.fire('Atención', `El PDF pesa más de ${Math.round(CAT.maxBytes / 1048576)} MB.`, 'warning');
            return;
        }
        // El tipo se elige en la ventana; arranca en Factura y la clave de acceso lo corrige.
        const doc = docVacio('01');
        actual = { id: ++seq, file, estado: 'listo', manual: true, mensaje: '',
            lectura: { doc, token_pdf: '', errores: [], avisos: [], notas: [] } };
        ajustarModo(true, file);
        llenarFormulario(doc, []);
        pintarMensajes(actual.lectura);
        $('rp_btn_registrar').disabled = false;
        bootstrap.Modal.getOrCreateInstance($('modalRevisionPdf')).show();
    };

    /**
     * Ingreso manual: la clave de acceso trae fecha (1-8), tipo (9-10), RUC (11-23),
     * ambiente (24), establecimiento (25-27), punto (28-30) y secuencial (31-39).
     * Se completan esos campos; el servidor vuelve a comprobar todo (dígito verificador incluido).
     */
    // La clave de acceso y el número de autorización casi siempre son el mismo número: lo que
    // se escribe en la clave se replica en la autorización mientras esta siga igual a la clave
    // (o vacía). Si el usuario pone ahí otro número a propósito, se deja de replicar.
    let claveAntes = '';
    $('rp_clave_acceso').addEventListener('focus', function () { claveAntes = this.value; });
    $('rp_clave_acceso').addEventListener('input', function () {
        const aut = $('rp_numero_autorizacion');
        if (aut.value === '' || aut.value === claveAntes) aut.value = this.value;
        claveAntes = this.value;
    });

    // La fecha de autorización toma por defecto la fecha de emisión (a las 00:00:00): se replica
    // mientras siga vacía o igual a la emisión anterior. Si el usuario pone otra, se respeta.
    const autDesdeEmision = (f) => (/^\d{2}\/\d{2}\/\d{4}$/.test(f) ? `${f} 00:00:00` : f);
    function replicarFechaAutorizacion(emisionAntes, emisionNueva) {
        const aut = $('rp_fecha_autorizacion');
        if (aut.value === '' || aut.value === autDesdeEmision(emisionAntes) || aut.value === emisionAntes) {
            aut.value = autDesdeEmision(emisionNueva);
        }
    }
    let emisionAntes = '';
    $('rp_fecha_emision').addEventListener('focus', function () { emisionAntes = this.value; });
    $('rp_fecha_emision').addEventListener('input', function () {
        replicarFechaAutorizacion(emisionAntes, this.value);
        emisionAntes = this.value;
    });

    $('rp_clave_acceso').addEventListener('input', function () {
        if (!actual || !actual.manual) return;
        const c = this.value.replace(/\D/g, '');
        if (c.length !== 49) return;
        const cod = c.substr(8, 2);
        if (CAT.tipos[cod]) {
            $('rp_cod_doc').value = cod;
            window.rpAjustarSecciones();
        }
        const emisionPrevia = $('rp_fecha_emision').value;
        $('rp_fecha_emision').value = `${c.substr(0, 2)}/${c.substr(2, 2)}/${c.substr(4, 4)}`;
        replicarFechaAutorizacion(emisionPrevia, $('rp_fecha_emision').value);
        $('rp_estab').value = c.substr(24, 3);
        $('rp_pto_emi').value = c.substr(27, 3);
        $('rp_secuencial').value = c.substr(30, 9);
        pintarNumero();
        if (cod === '07' && !$('rp_periodo_fiscal').value.trim()) {
            $('rp_periodo_fiscal').value = `${c.substr(2, 2)}/${c.substr(4, 4)}`;
        }
        if (CAT.emisorManual && CAT.emisorManual.ruc && c.substr(10, 13) !== CAT.emisorManual.ruc) {
            Swal.fire('Atención', `La clave de acceso es de otro emisor (RUC ${c.substr(10, 13)}). El ingreso manual es solo para comprobantes emitidos por esta empresa.`, 'warning');
        }
    });

    window.rpVerPdf = function () {
        if (!actual || !actual.file) return;
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
    const CAMPOS_RECEPTOR = { tipo_identificacion: 'rp_re_tipo_id', identificacion: 'rp_re_identificacion', razon_social: 'rp_re_razon_social', direccion: 'rp_re_direccion' };
    const CAMPOS_MOD = { cod_doc: 'rp_mod_cod_doc', numero: 'rp_mod_numero', fecha: 'rp_mod_fecha', motivo: 'rp_mod_motivo' };
    const CAMPOS_TOT = { subtotal: 'rp_tot_subtotal', descuento: 'rp_tot_descuento', propina: 'rp_tot_propina', ice: 'rp_tot_ice', total: 'rp_tot_total' };
    const TABLAS = ['detalles', 'motivos', 'retenciones', 'impuestos', 'pagos', 'info_adicional'];

    function llenarFormulario(doc, opciones) {
        opcionesRet = opciones || [];
        Object.entries(CAMPOS).forEach(([k, id]) => { $(id).value = doc[k] ?? ''; });
        // Sin fecha de autorización (el PDF no la trae o es un ingreso manual): la de emisión.
        if (!$('rp_fecha_autorizacion').value.trim()) {
            $('rp_fecha_autorizacion').value = autDesdeEmision($('rp_fecha_emision').value);
        }
        Object.entries(CAMPOS_EMISOR).forEach(([k, id]) => { $(id).value = doc.emisor[k] ?? ''; });
        // El selector solo tiene Sí y No: si el PDF no lo trae, queda en No (se puede cambiar).
        if (!['SI', 'NO'].includes($('rp_em_obligado').value)) $('rp_em_obligado').value = 'NO';
        Object.entries(CAMPOS_RECEPTOR).forEach(([k, id]) => { $(id).value = doc.receptor[k] ?? ''; });
        if (!$('rp_re_tipo_id').value) $('rp_re_tipo_id').value = tipoIdSugerido($('rp_re_identificacion').value);
        Object.entries(CAMPOS_MOD).forEach(([k, id]) => { $(id).value = doc.doc_modificado[k] ?? ''; });
        if (!$('rp_mod_cod_doc').value) $('rp_mod_cod_doc').value = '01';
        Object.entries(CAMPOS_TOT).forEach(([k, id]) => { $(id).value = num(doc.totales[k]); });
        TABLAS.forEach((t) => {
            $('rp_tb_' + t).innerHTML = '';
            (doc[t] || []).forEach((fila, i) => agregarFila(t, fila, i));
            contar(t);
        });
        pintarNumero();
        window.rpAjustarSecciones();
    }

    // ── Número del comprobante en un solo campo: 001-001-000000009 ──────────
    // Lo visible es #rp_numero; al servidor viajan los ocultos rp_estab, rp_pto_emi y rp_secuencial.
    const LARGOS_NUMERO = [3, 3, 9];

    /** Partes escritas, respetando los guiones del usuario y saltando de parte al completar su largo. */
    function partesNumero(texto) {
        const partes = [''];
        let k = 0;
        for (const ch of String(texto)) {
            if (ch === '-') {
                if (partes[k] !== '' && k < 2) partes[++k] = '';
                continue;
            }
            if (!/\d/.test(ch)) continue;
            if (partes[k].length === LARGOS_NUMERO[k] && k < 2) partes[++k] = '';
            if (partes[k].length < LARGOS_NUMERO[k]) partes[k] += ch;
        }
        return partes;
    }

    /** Copia a los ocultos (cada parte completada con ceros a la izquierda). */
    function repartirNumero(partes) {
        ['rp_estab', 'rp_pto_emi', 'rp_secuencial'].forEach((id, i) => {
            const p = partes[i] || '';
            $(id).value = p === '' ? '' : p.padStart(LARGOS_NUMERO[i], '0');
        });
    }

    /** Muestra en #rp_numero lo que tienen los ocultos (datos leídos del PDF o de la clave). */
    function pintarNumero() {
        const partes = [$('rp_estab').value, $('rp_pto_emi').value, $('rp_secuencial').value];
        while (partes.length && partes[partes.length - 1] === '') partes.pop();
        $('rp_numero').value = partes.join('-');
    }

    $('rp_numero').addEventListener('input', function () {
        const partes = partesNumero(this.value);
        this.value = partes.join('-');
        repartirNumero(partes);
    });
    // Al salir del campo se completan los ceros: «1-1-9» queda «001-001-000000009».
    $('rp_numero').addEventListener('blur', function () {
        repartirNumero(partesNumero(this.value));
        pintarNumero();
    });

    // Mismo diseño que el detalle de Factura de Venta: celdas sin borde (input-detalle)
    // que se marcan al editar, filas row-detalle y papelera para quitar.
    const inTxt = (campo, valor, extra = '') =>
        `<input type="text" class="form-control form-control-sm input-detalle" data-c="${campo}" value="${esc(valor)}" ${extra}>`;
    const inNum = (campo, valor) =>
        `<input type="text" class="form-control form-control-sm input-detalle text-end" data-c="${campo}" value="${esc(valor)}" inputmode="decimal">`;
    const sel = (campo, opciones, valor) =>
        `<select class="form-select form-select-sm input-detalle" data-c="${campo}">${opciones.map(([v, t]) =>
            `<option value="${esc(v)}"${String(v) === String(valor) ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`;
    const btnQuitar = '<td class="text-center p-0 align-middle" style="width:40px;"><button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" title="Quitar línea" onclick="rpQuitarFila(this)"><i class="bi bi-trash3 fs-6"></i></button></td>';

    /** Contador «Ítems» del pie de cada tabla. */
    function contar(tabla) {
        const span = $('rp_count_' + tabla);
        if (span) span.textContent = $('rp_tb_' + tabla).querySelectorAll('tr').length;
    }
    window.rpQuitarFila = function (btn) {
        const tbody = btn.closest('tbody');
        btn.closest('tr').remove();
        contar(tbody.id.replace('rp_tb_', ''));
        if (tbody.id === 'rp_tb_detalles') recalcular();
    };

    function agregarFila(tabla, f = {}, indice = -1) {
        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        if (tabla === 'detalles') {
            tr.innerHTML = `<td>${inTxt('codigo', f.codigo ?? '', 'maxlength="25"')}</td>
                <td>${inTxt('descripcion', f.descripcion ?? '', 'maxlength="300"')}</td>
                <td>${inTxt('adicional', f.adicional ?? '', 'maxlength="300" placeholder="Info adicional"')}</td>
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
        contar(tabla);
    }
    window.rpAgregarFila = (tabla) => agregarFila(tabla);

    // ── Ingreso manual: IVA por tarifa y totales calculados desde el detalle ──
    // Solo en el ingreso manual y en los tipos con detalle (factura, liquidación, nota de
    // crédito). En «Desde PDF» los valores son los del documento leído y no se tocan.
    // Regla §9 del sistema: el modo (al subtotal o línea a línea) es el del establecimiento
    // de la serie del documento, y el redondeo es CMG_r2 / CMG_iva (public/js/app.js).
    function calculoAutomatico() {
        return !!(actual && actual.manual) && ['01', '03', '04'].includes($('rp_cod_doc').value);
    }
    const pctIva = (cod) => (CAT.iva.find((o) => o.cod === String(cod)) || {}).pct || 0;
    const valorCelda = (tr, c) => parseFloat((tr.querySelector(`[data-c="${c}"]`) || {}).value) || 0;

    function recalcular() {
        if (!calculoAutomatico()) return;
        const r2 = window.CMG_r2;
        const ivaDe = window.CMG_iva;
        const grupos = {};          // cod_iva => {base, ivaLineas}
        let subtotal = 0;
        let descuento = 0;

        $('rp_tb_detalles').querySelectorAll('tr').forEach((tr) => {
            const cant = valorCelda(tr, 'cantidad');
            const precio = valorCelda(tr, 'precio_unitario');
            const desc = valorCelda(tr, 'descuento');
            const base = r2(cant * precio - desc);
            tr.querySelector('[data-c="subtotal"]').value = num(base);
            const cod = tr.querySelector('[data-c="cod_iva"]').value;
            const g = grupos[cod] || (grupos[cod] = { base: 0, ivaLineas: 0 });
            g.base += base;
            g.ivaLineas += ivaDe(base, pctIva(cod));
            subtotal += base;
            descuento += desc;
        });

        const serie = `${$('rp_estab').value}-${$('rp_pto_emi').value}`;
        const modo = (CAT.modosIva || {})[serie] === 'subtotal' ? 'subtotal' : 'linea_linea';
        let ivaTotal = 0;
        let desglose = '';
        $('rp_tb_impuestos').innerHTML = '';
        Object.entries(grupos).forEach(([cod, g]) => {
            const base = r2(g.base);
            if (base === 0) return;
            // Al subtotal: el % se aplica una sola vez a la suma de bases de la tarifa.
            // Línea a línea: suma del IVA redondeado de cada línea.
            const valor = modo === 'subtotal' ? ivaDe(base, pctIva(cod)) : r2(g.ivaLineas);
            ivaTotal += valor;
            agregarFila('impuestos', { cod_iva: cod, base, valor });
            const nombre = (CAT.iva.find((o) => o.cod === cod) || {}).nombre || cod;
            desglose += `<div class="d-flex justify-content-between text-muted mb-1"><span>Subtotal ${esc(nombre)}</span><span>${num(base)}</span></div>`;
            if (pctIva(cod) > 0) {
                desglose += `<div class="d-flex justify-content-between mb-1"><span class="text-muted">(+) IVA ${esc(nombre)}</span><span class="fw-bold">${num(valor)}</span></div>`;
            }
        });
        contar('impuestos');
        $('rp_tot_ivas').innerHTML = desglose;

        const propina = parseFloat($('rp_tot_propina').value) || 0;
        const total = r2(r2(subtotal) + r2(ivaTotal) + propina);
        $('rp_tot_subtotal').value = num(r2(subtotal));
        $('rp_tot_descuento').value = num(r2(descuento));
        $('rp_tot_total').value = num(total);
        // Con una sola forma de pago, toma el total del comprobante.
        const pagos = $('rp_tb_pagos').querySelectorAll('tr');
        if (pagos.length === 1) pagos[0].querySelector('[data-c="total"]').value = num(total);
    }

    // Se recalcula al cambiar cantidad, precio, descuento o tarifa (no al escribir el subtotal).
    ['input', 'change'].forEach((ev) => $('rp_tb_detalles').addEventListener(ev, (e) => {
        if (['cantidad', 'precio_unitario', 'descuento', 'cod_iva'].includes(e.target.dataset.c)) recalcular();
    }));
    $('rp_tot_propina').addEventListener('input', recalcular);
    $('rp_numero').addEventListener('change', recalcular);   // otra serie = quizá otro modo de IVA
    $('rp_cod_doc').addEventListener('change', recalcular);

    // ── Ingreso manual: elegir el ítem desde Productos ─────────────────────
    const URL_PRODUCTOS = `${BASE_URL}/modulos/descargas_sri/buscarProductosAjax`;
    const listaProd = document.createElement('div');
    listaProd.className = 'list-group shadow d-none';
    listaProd.style.cssText = 'position:fixed; z-index:1070; max-height:260px; overflow-y:auto; min-width:360px;';
    $('modalRevisionPdf').appendChild(listaProd);
    let prodFila = null;
    let prodEncontrados = [];
    let prodActivo = -1;
    let prodTemporizador = null;
    let prodBusqueda = 0;

    function cerrarListaProductos() {
        listaProd.classList.add('d-none');
        listaProd.innerHTML = '';
        prodEncontrados = [];
        prodActivo = -1;
    }

    function pintarListaProductos(input) {
        listaProd.innerHTML = prodEncontrados.length
            ? prodEncontrados.map((p, i) => `
                <button type="button" class="list-group-item list-group-item-action py-1 small${i === prodActivo ? ' active' : ''}" data-i="${i}">
                    <div class="fw-semibold">${esc(p.nombre)}</div>
                    <div class="${i === prodActivo ? '' : 'text-muted'}" style="font-size:.72rem">${esc(p.codigo)} · ${num(p.precio)} · IVA ${esc((CAT.iva.find((o) => o.cod === p.cod_iva) || {}).nombre || '—')}</div>
                </button>`).join('')
            : '<div class="list-group-item small text-muted">No se encontraron productos.</div>';
        const r = input.getBoundingClientRect();
        listaProd.style.left = `${r.left}px`;
        listaProd.style.top = `${r.bottom + 2}px`;
        listaProd.style.width = `${Math.max(r.width, 360)}px`;
        listaProd.classList.remove('d-none');
    }

    function elegirProducto(p) {
        if (!prodFila || !p) return;
        const c = (k) => prodFila.querySelector(`[data-c="${k}"]`);
        c('codigo').value = p.codigo;
        c('descripcion').value = p.nombre;
        c('precio_unitario').value = numLibre(p.precio);
        if (!(parseFloat(c('cantidad').value) > 0)) c('cantidad').value = '1';
        if (CAT.iva.some((o) => o.cod === p.cod_iva)) c('cod_iva').value = p.cod_iva;
        cerrarListaProductos();
        recalcular();
    }

    // Se busca al escribir en Código o Descripción de una línea del detalle.
    $('rp_tb_detalles').addEventListener('input', (e) => {
        if (!actual || !actual.manual || !['codigo', 'descripcion'].includes(e.target.dataset.c)) return;
        clearTimeout(prodTemporizador);
        const input = e.target;
        const q = input.value.trim();
        if (q.length < 2) { cerrarListaProductos(); return; }
        prodTemporizador = setTimeout(async () => {
            const n = ++prodBusqueda;
            const tipo = $('rp_cod_doc').value === '03' ? 'compra' : 'venta';
            try {
                const res = await fetch(`${URL_PRODUCTOS}?q=${encodeURIComponent(q)}&tipo=${tipo}`);
                const data = await res.json();
                if (n !== prodBusqueda || document.activeElement !== input) return;
                prodFila = input.closest('tr');
                prodEncontrados = data.ok ? data.productos : [];
                prodActivo = prodEncontrados.length ? 0 : -1;
                pintarListaProductos(input);
            } catch (err) {
                console.error(err);
            }
        }, 250);
    });

    $('rp_tb_detalles').addEventListener('keydown', (e) => {
        if (listaProd.classList.contains('d-none') || !['codigo', 'descripcion'].includes(e.target.dataset.c)) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!prodEncontrados.length) return;
            prodActivo = (prodActivo + (e.key === 'ArrowDown' ? 1 : -1) + prodEncontrados.length) % prodEncontrados.length;
            pintarListaProductos(e.target);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            elegirProducto(prodEncontrados[prodActivo]);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation(); // que Esc cierre la lista y no la ventana
            cerrarListaProductos();
        }
    });
    // mousedown (no click) para elegir antes de que el blur cierre la lista.
    listaProd.addEventListener('mousedown', (e) => {
        const item = e.target.closest('[data-i]');
        if (!item) return;
        e.preventDefault();
        elegirProducto(prodEncontrados[Number(item.dataset.i)]);
    });
    $('rp_tb_detalles').addEventListener('focusout', () => setTimeout(cerrarListaProductos, 150));
    // La lista va en posición fija: al desplazar la ventana se cierra para no quedar desfasada.
    $('modalRevisionPdf').addEventListener('scroll', cerrarListaProductos, true);

    /** Muestra solo las secciones del tipo de documento elegido. */
    window.rpAjustarSecciones = function () {
        const cod = $('rp_cod_doc').value;
        document.querySelectorAll('#modalRevisionPdf [data-rp-tipos]').forEach((el) => {
            el.classList.toggle('d-none', !el.dataset.rpTipos.split(',').includes(cod));
        });
        const receptor = cod === '03' ? 'Proveedor' : (cod === '07' ? 'Sujeto retenido' : 'Comprador');
        $('rp_titulo_receptor').textContent = receptor;
        // La tabla «IVA por tarifa» se oculta cuando se calcula sola con el detalle (ingreso
        // manual de factura, liquidación y nota de crédito; su desglose va en Totales). Se ve
        // en «Desde PDF» (trae lo impreso) y en la nota de débito (sin detalle, se digita).
        // La retención (07) no lleva IVA por tarifa.
        const ivaAutomatico = calculoAutomatico();
        $('rp_col_impuestos').classList.toggle('d-none', ivaAutomatico || !['01', '03', '04', '05'].includes(cod));
        if (!ivaAutomatico) $('rp_tot_ivas').innerHTML = '';
        // El buscador es de clientes: en liquidación (03) y retención (07) el receptor es un proveedor.
        $('rp_buscar_cliente_wrap').classList.toggle('d-none', cod === '03' || cod === '07');
        pintarRequisitos(cod, receptor.toLowerCase());
    };

    // ── Buscador de clientes (tarjeta «Comprador») ──────────────────────────
    const URL_CLIENTES = `${BASE_URL}/modulos/descargas_sri/buscarClientesAjax`;
    let clientesEncontrados = [];
    let clienteActivo = -1;
    let temporizadorClientes = null;
    let busquedaClientes = 0;

    function cerrarListaClientes() {
        $('rp_clientes_lista').classList.add('d-none');
        $('rp_clientes_lista').innerHTML = '';
        clientesEncontrados = [];
        clienteActivo = -1;
    }

    function pintarListaClientes() {
        const lista = $('rp_clientes_lista');
        if (!clientesEncontrados.length) {
            lista.innerHTML = '<div class="list-group-item small text-muted">No se encontraron clientes.</div>';
        } else {
            lista.innerHTML = clientesEncontrados.map((c, i) => `
                <button type="button" class="list-group-item list-group-item-action py-1 small${i === clienteActivo ? ' active' : ''}" data-i="${i}">
                    <div class="fw-semibold">${esc(c.nombre)}</div>
                    <div class="${i === clienteActivo ? '' : 'text-muted'}" style="font-size:.72rem">${esc(c.identificacion)}${c.direccion ? ' · ' + esc(c.direccion) : ''}</div>
                </button>`).join('');
        }
        lista.classList.remove('d-none');
    }

    function elegirCliente(c) {
        $('rp_re_identificacion').value = c.identificacion;
        $('rp_re_tipo_id').value = tipoIdSugerido(c.identificacion);
        $('rp_re_razon_social').value = c.nombre;
        $('rp_re_direccion').value = c.direccion || '';
        $('rp_buscar_cliente').value = '';
        cerrarListaClientes();
    }

    /**
     * Tipo de identificación propuesto según el número (mismo criterio que
     * ComprobantePdf::tipoIdentificacion en el servidor). El usuario puede cambiarlo
     * (p. ej. a «Identificación del exterior»).
     */
    function tipoIdSugerido(id) {
        const v = String(id || '').trim();
        if (v === '9999999999999') return '07';
        if (/^\d{13}$/.test(v)) return '04';
        if (/^\d{10}$/.test(v)) return '05';
        return '06';
    }
    // Al escribir la identificación se propone el tipo, mientras el tipo siga siendo el que se
    // propuso para el número anterior. Si el usuario eligió otro a mano, se respeta.
    let identificacionAntes = '';
    $('rp_re_identificacion').addEventListener('focus', function () { identificacionAntes = this.value; });
    $('rp_re_identificacion').addEventListener('input', function () {
        const tipo = $('rp_re_tipo_id');
        if (identificacionAntes.trim() === '' || tipo.value === tipoIdSugerido(identificacionAntes)) {
            if (this.value.trim() !== '') tipo.value = tipoIdSugerido(this.value);
        }
        identificacionAntes = this.value;
    });

    $('rp_buscar_cliente').addEventListener('input', function () {
        clearTimeout(temporizadorClientes);
        const q = this.value.trim();
        if (q.length < 2) { cerrarListaClientes(); return; }
        temporizadorClientes = setTimeout(async () => {
            const n = ++busquedaClientes;
            try {
                const res = await fetch(`${URL_CLIENTES}?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                if (n !== busquedaClientes) return; // llegó una búsqueda más nueva
                clientesEncontrados = data.ok ? data.clientes : [];
                clienteActivo = clientesEncontrados.length ? 0 : -1;
                pintarListaClientes();
            } catch (err) {
                console.error(err);
            }
        }, 250);
    });

    $('rp_buscar_cliente').addEventListener('keydown', function (e) {
        if ($('rp_clientes_lista').classList.contains('d-none')) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!clientesEncontrados.length) return;
            const paso = e.key === 'ArrowDown' ? 1 : -1;
            clienteActivo = (clienteActivo + paso + clientesEncontrados.length) % clientesEncontrados.length;
            pintarListaClientes();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (clientesEncontrados[clienteActivo]) elegirCliente(clientesEncontrados[clienteActivo]);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation(); // que Esc cierre la lista y no la ventana
            cerrarListaClientes();
        }
    });

    // mousedown (no click) para elegir antes de que el blur del buscador cierre la lista.
    $('rp_clientes_lista').addEventListener('mousedown', function (e) {
        const item = e.target.closest('[data-i]');
        if (!item) return;
        e.preventDefault();
        elegirCliente(clientesEncontrados[Number(item.dataset.i)]);
    });
    $('rp_buscar_cliente').addEventListener('blur', () => setTimeout(cerrarListaClientes, 150));

    /**
     * Qué hace falta para registrar, según el tipo (lo mismo que exige DescargaPdfRules
     * en el servidor; si cambia una regla allá, ajustar este texto).
     */
    function pintarRequisitos(cod, receptor) {
        const comunes = [
            'Tipo de documento, número (001-001-000000009) y fecha de emisión.',
            'Clave de acceso completa de 49 dígitos (el sistema comprueba su dígito verificador y que coincida con el tipo, el número, la fecha y el RUC del emisor).',
            (actual && actual.manual)
                ? 'Emisor: es esta empresa; sus datos se toman de Empresas (no hay que digitarlos).'
                : 'Emisor: RUC de 13 dígitos y razón social.',
            `${receptor.charAt(0).toUpperCase() + receptor.slice(1)}: tipo de identificación, identificación y nombre o razón social (RUC de 13 dígitos, cédula de 10, consumidor final 9999999999999).`,
        ];
        const porTipo = {
            '01': ['Detalle: al menos una línea con descripción y cantidad mayor que cero.', 'IVA por tarifa, formas de pago y valor total mayor que cero.'],
            '03': ['Detalle: al menos una línea con descripción y cantidad mayor que cero.', 'IVA por tarifa, formas de pago y valor total mayor que cero.'],
            '04': ['Documento que modifica: tipo, número, fecha y motivo.', 'Detalle: al menos una línea con descripción y cantidad mayor que cero.', 'IVA por tarifa y valor total mayor que cero.'],
            '05': ['Documento que modifica: tipo, número y fecha.', 'Motivos: al menos uno con su razón y valor.', 'IVA por tarifa, formas de pago y valor total mayor que cero.'],
            '07': ['Periodo fiscal (mm/aaaa).', 'Retenciones: al menos una línea con su código de retención, base, porcentaje y valor retenido.'],
        };
        let delTipo = porTipo[cod] || [];
        if (calculoAutomatico()) {
            // Ingreso manual con detalle: el IVA por tarifa y los totales salen de las líneas.
            delTipo = delTipo.map((t) => t.replace(/^IVA por tarifa(, formas de pago)? y valor total mayor que cero\.$/,
                (m, pagos) => (pagos ? 'Formas de pago. ' : '') + 'El IVA por tarifa y los totales se calculan solos con el detalle.'));
        }
        const lista = cod ? comunes.concat(delTipo) : ['Elija el tipo de documento (o escriba la clave de acceso) para ver lo que se necesita.'];
        $('rp_requisitos').innerHTML = lista.map((t) => `<li>${esc(t)}</li>`).join('');
    }

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
        // Nombre comercial y dirección del establecimiento no se muestran: si no vienen del PDF
        // (o de la empresa, en el ingreso manual), se usan la razón social y la dirección matriz.
        if (!doc.emisor.nombre_comercial) doc.emisor.nombre_comercial = doc.emisor.razon_social;
        if (!doc.emisor.dir_establecimiento) doc.emisor.dir_establecimiento = doc.emisor.dir_matriz;
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
            html = (actual && actual.manual)
                ? '<div class="alert alert-info py-2 px-3 mb-2 small"><i class="bi bi-pencil-square me-1"></i>Complete los datos del comprobante tal como constan en el documento autorizado. Pulse <strong>Verificar datos</strong> para comprobarlos antes de registrar.</div>'
                : '<div class="alert alert-success py-2 px-3 mb-2 small"><i class="bi bi-check-circle me-1"></i>Los datos son coherentes. Compárelos con el PDF antes de registrar.</div>';
        }
        $('rp_mensajes').innerHTML = html;
        $('rp_btn_registrar').disabled = !!r.existe;
    }

    async function enviar(extra) {
        const fd = new FormData();
        fd.append('documento', JSON.stringify(leerFormulario()));
        fd.append('token_pdf', actual.lectura.token_pdf || '');
        if (actual.manual) {
            fd.append('origen', 'manual');
            // El PDF de respaldo solo viaja al registrar, no en cada verificación.
            if (actual.file && !(extra && extra.solo_revisar)) fd.append('archivo_pdf', actual.file);
        }
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
                subirAlInicio();
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
                subirAlInicio();
                return;
            }

            terminado = true;
            actual.estado = data.existe ? 'existe' : 'registrado';
            actual.mensaje = data.mensaje || '';
            if (actual.manual) {
                $('manual_pdf_input').value = '';
            } else {
                pintarCola();
            }
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
