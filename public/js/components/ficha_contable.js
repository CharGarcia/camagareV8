/**
 * Pestaña «Contable» de las fichas de proveedor y cliente (HTML: app/views/partials/ficha_contable.php).
 *
 * Muestra y edita las reglas POR ENTIDAD de Configuración Contable (tabla asientos_programados,
 * tipo_referencia = 'proveedor' | 'cliente') sin salir de la ficha: la misma ficha Debe | Haber
 * de /modulos/configuracion-contable → «Reglas por Proveedores / Clientes». No tiene backend
 * propio: usa los endpoints de configuracion-contable, que validan los permisos de ese módulo.
 *
 * - Cada concepto trae la cuenta propia de la entidad, o como marcador la de la General.
 * - Elegir una cuenta la guarda al vuelo; vaciar el campo (o Retroceso/Suprimir sobre una cuenta
 *   ya elegida) la quita.
 * - Se carga al abrir la pestaña, se vuelve a pedir si cambia la ficha o el tipo de asiento y tras
 *   guardar la ficha.
 * - Sus controles no llevan "name" y Enter no envía el formulario: viven dentro del <form> de la ficha.
 *
 * Cada panel [data-ficha-contable] es independiente (una página puede tener la ficha de proveedor
 * y la de cliente a la vez); su configuración viaja en data-* (ver el partial).
 *
 * Opciones del empleado (todas opt-in por data-*; sin ellas el componente se comporta como siempre):
 * - data-modo-asiento: selector «Asiento: General | Personalizado». En General todo es solo lectura
 *   (se ve qué cuenta usará cada concepto); Personalizado habilita elegir cuentas propias; volver a
 *   General quita todas las propias (con confirmación). Está en Personalizado si tiene alguna propia.
 * - data-nomina (App\Helpers\NominaConceptos::paraJs) + data-opciones ({fr,dt,dc,iess} => id del
 *   <select> de la ficha): solo se muestran los conceptos que aplican a lo elegido en la pestaña
 *   Laboral (se repinta al cambiarlo); una cuenta propia de un concepto que ya no aplica se muestra
 *   marcada «no se usa»; y el marcador de un concepto vacío dice qué cuenta se usará siguiendo la
 *   cadena de respaldo, en el mismo orden que RolAsientoService::cuentaCadena().
 */
(function (window, document) {
    'use strict';

    if (window.FichaContable) return; // script incluido dos veces en la misma página

    function esc(valor) {
        const div = document.createElement('div');
        div.textContent = valor == null ? '' : String(valor);
        return div.innerHTML;
    }

    function toast(icono, titulo) {
        if (!window.Swal) return;
        Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2200, timerProgressBar: true })
            .fire({ icon: icono, title: titulo });
    }

    function error(msg) {
        if (window.Swal) Swal.fire('No se pudo completar', msg || 'Error inesperado.', 'error');
        else alert(msg || 'Error inesperado.');
    }

    async function getJson(url, opciones) {
        const r = await fetch(url, opciones);
        return r.json();
    }

    function leerJson(texto) {
        if (!texto) return null;
        try { return JSON.parse(texto); } catch (e) { console.error(e); return null; }
    }

    /** ¿El concepto es una tarifa de IVA? (no tiene id_asiento_tipo propio: se cruza por tarifa). */
    function esConceptoIva(c) {
        return !!c && (c.tipo_referencia === 'iva_compras_factura' || c.tipo_referencia === 'iva_ventas_factura' || c.tipo_referencia === 'iva_recibos_venta');
    }

    function esConceptoValido(c) {
        return parseInt(c.id_asiento_tipo, 10) > 0 || esConceptoIva(c);
    }

    // Conceptos visibles de entrada por tipo de asiento (código de asientos_tipo): la cuenta de ventas
    // o de gasto/costo (Subtotal), que es la que cambia de un cliente/proveedor a otro. El resto va
    // tras «Mostrar las demás cuentas». Mismo criterio que las tarjetas de Configuración Contable
    // (ASIENTOPROG_CONCEPTOS_PRINCIPALES.*.entidad en configuracion_contable_modal.js).
    const CONCEPTOS_PRINCIPALES = {
        ventas_factura:        ['SUBTOTALFACTURAVENTA'],
        recibos_venta:         ['SUBTOTALRECIBOVENTA'],
        adquisiciones_compras: ['SUBTOTALFACTURACOMPRA'],
    };

    (function estilos() {
        if (document.getElementById('cc-extra-estilos')) return;
        const st = document.createElement('style');
        st.id = 'cc-extra-estilos';
        st.textContent = `
            .cc-compacta .cc-extra, .cc-compacta .cc-col-extra { display: none !important; }
            .cc-compacta .cc-col-principal { flex: 0 0 100%; max-width: 100%; }`;
        document.head.appendChild(st);
    })();

    function crear(panel) {
        const cfg = panel.dataset;
        const q = (rol) => panel.querySelector(`[data-fctb="${rol}"]`);
        const prefijo = `fctb_${cfg.tipoReferencia}`;

        let cargadoPara = '';      // "idEntidad|tipoAsiento" pintado en la pestaña
        let conceptos = [];        // conceptos del tipo de asiento con su cuenta General
        let reglas = [];           // reglas propias de la entidad
        let idRecienGuardado = ''; // id de la ficha recién creada, mientras el input aún no lo tiene
        let debounceTimer = null;
        let expandida = false;     // «Mostrar las demás cuentas» desplegado (se reinicia al cambiar de ficha)
        let personalizando = false; // modo «Personalizado» (solo con data-modo-asiento)

        const modoAsiento = cfg.modoAsiento === '1';
        const nomina = leerJson(cfg.nomina);          // {cadenas, aplica} o null
        const fuentes = leerJson(cfg.opciones) || {}; // {fr|dt|dc|iess: id del select}

        const idEntidad = () => (document.getElementById(cfg.idInput)?.value || '').trim() || idRecienGuardado;
        const tipoAsiento = () => q('tipo')?.value || '';
        const puedeCrear = () => cfg.puedeCrear === '1';
        const puedeEliminar = () => cfg.puedeEliminar === '1';
        // Se pueden elegir cuentas: permiso y, con selector de modo, estar en Personalizado.
        // En General (data-modo-asiento) también se puede: ahí se edita la cuenta General de la
        // empresa (para todos), con confirmación; mismo permiso que en Configuración Contable.
        const editable = () => puedeCrear();
        // ¿Lo que se elige ahora cambia la configuración General de la empresa?
        const editaGeneral = () => modoAsiento && !personalizando;

        /** Opciones actuales de la ficha (pestaña Laboral del empleado). */
        function opcionesActuales() {
            const o = {};
            Object.entries(fuentes).forEach(([clave, id]) => {
                const el = document.getElementById(id);
                o[clave] = el ? String(el.value || '') : '';
            });
            return o;
        }

        /** ¿El concepto aplica con lo elegido en la ficha? Mismo criterio que NominaConceptos::aplica(). */
        function aplica(c) {
            if (!nomina || !nomina.aplica) return true;
            const regla = nomina.aplica[c.codigo];
            if (regla == null) return true;
            if (regla === 'nunca') return false;
            const o = opcionesActuales();
            return Object.entries(regla).every(([clave, valores]) => !o[clave] || valores.includes(o[clave]));
        }

        /** Conceptos que se pintan: los que aplican y los que tienen cuenta propia aunque ya no apliquen. */
        const visibles = () => conceptos.filter(c => aplica(c) || propiaDe(c));

        /**
         * Qué cuenta se usará para un concepto sin cuenta propia, siguiendo su cadena de respaldo:
         * primero una cuenta propia en otro eslabón, después la General del primero que la tenga
         * (RolAsientoService::cuentaCadena). null = ninguna.
         */
        function cuentaEfectiva(c) {
            const cadena = (nomina && nomina.cadenas && nomina.cadenas[c.codigo]) || [c.codigo];
            const porCodigo = {};
            conceptos.forEach(x => { porCodigo[x.codigo] = x; });
            for (const cod of cadena) {
                const x = porCodigo[cod];
                const p = x && propiaDe(x);
                if (p) return { texto: `${p.cuenta_codigo} - ${p.cuenta_nombre}`, de: x, propia: true };
            }
            for (const cod of cadena) {
                const x = porCodigo[cod];
                if (x && x.id_cuenta) return { texto: `${x.cuenta_codigo} - ${x.cuenta_nombre || ''}`, de: x, propia: false };
            }
            return null;
        }

        function propiaDe(c) {
            if (parseInt(c.id_asiento_tipo, 10) > 0) {
                return reglas.find(r => parseInt(r.id_asiento_tipo, 10) === parseInt(c.id_asiento_tipo, 10));
            }
            return reglas.find(r => parseInt(r.id_asiento_tipo, 10) === 0 && r.codigo_tarifa_iva != null
                && String(r.codigo_tarifa_iva) === String(c.id_referencia));
        }

        function mostrarSinGuardar(sinGuardar) {
            q('sin-guardar')?.classList.toggle('d-none', !sinGuardar);
            q('wrap')?.classList.toggle('d-none', sinGuardar);
        }

        async function cargar(forzar) {
            const id = idEntidad();
            if (!id) {
                cargadoPara = '';
                mostrarSinGuardar(true);
                return;
            }
            mostrarSinGuardar(false);

            const clave = `${id}|${tipoAsiento()}`;
            if (!forzar && clave === cargadoPara) return;
            if (clave !== cargadoPara) { expandida = false; personalizando = false; }
            cargadoPara = clave;

            const cuerpo = q('cuerpo');
            cuerpo.innerHTML = '<div class="text-center text-muted small py-4"><span class="spinner-border spinner-border-sm me-1"></span> Cargando...</div>';

            try {
                const ta = encodeURIComponent(tipoAsiento());
                const [resConf, resReglas] = await Promise.all([
                    getJson(`${cfg.urlContable}/cargarConfiguracionAjax?tipo_asiento=${ta}`),
                    getJson(`${cfg.urlContable}/cargarReglasDimensionAjax?tipo_asiento=${ta}&tipo_referencia=${encodeURIComponent(cfg.tipoReferencia)}&id_referencia=${encodeURIComponent(id)}`),
                ]);
                if (clave !== cargadoPara) return; // cambió la ficha mientras cargaba

                if (!resConf.ok) throw new Error(resConf.error || resConf.message || 'No se pudo leer la configuración General.');
                if (!resReglas.ok) throw new Error(resReglas.error || resReglas.message || 'No se pudieron leer las reglas.');

                conceptos = (resConf.data || []).filter(esConceptoValido);
                // Defensa: quedarse solo con las reglas de esta entidad.
                reglas = (resReglas.data || []).filter(r => String(r.id_referencia) === String(id));
                // Con cuentas propias, la ficha está en Personalizado.
                if (reglas.length) personalizando = true;
                pintar();
            } catch (e) {
                console.error(e);
                cargadoPara = '';
                cuerpo.innerHTML = `<div class="text-center text-danger small py-4">${esc(e.message || 'Error de conexión.')}</div>`;
            }
        }

        /**
         * Sin cuenta propia: qué pasa hoy con ese concepto. Con data-nomina se sigue la cadena de
         * respaldo; si no, lo cubre la General, un respaldo o nadie (como siempre).
         */
        function marcadorDe(c) {
            if (nomina) {
                const ef = cuentaEfectiva(c);
                if (!ef) return 'sin cuenta';
                if (ef.de === c) return `General: ${ef.texto}`;
                return ef.propia
                    ? `Usa la cuenta propia de «${ef.de.concepto}»: ${ef.texto}`
                    : `Usa «${ef.de.concepto}» (General): ${ef.texto}`;
            }
            return c.cuenta_codigo
                ? `General: ${c.cuenta_codigo} - ${c.cuenta_nombre || ''}`
                : (c.respaldo_concepto ? `usa ${c.respaldo_concepto}` : 'sin cuenta');
        }

        /** ¿El concepto no tiene cuenta en ningún lado? (se pinta en rojo y cuenta como faltante) */
        function faltaDe(c) {
            if (propiaDe(c)) return false;
            if (nomina) return !cuentaEfectiva(c);
            return !c.id_cuenta && !c.respaldo_concepto;
        }

        function pintar() {
            const cuerpo = q('cuerpo');
            const puedeEditar = editable();
            const lista = visibles();

            if (!lista.length) {
                cuerpo.innerHTML = '<div class="text-center text-muted small py-4">Este tipo de asiento no tiene conceptos configurables.</div>';
                pintarEstado();
                return;
            }

            // Vista resumida: solo el concepto principal del tipo de asiento (Subtotal de ventas o
            // de compras), más los que ya tienen cuenta propia o no tienen cuenta en ningún lado.
            const principales = CONCEPTOS_PRINCIPALES[tipoAsiento()] || null;
            const esExtra = (c) => !!principales && !principales.includes(c.codigo) && !propiaDe(c) && !faltaDe(c);
            const extras = lista.filter(esExtra).length;
            const compacta = extras > 0 && !expandida;

            const linea = (c) => {
                const propia = propiaDe(c);
                const key = esConceptoIva(c) ? `iva_${c.id_referencia}` : c.id_asiento_tipo;
                const inputId = `${prefijo}_${key}`;
                // En General el campo muestra (y edita) la cuenta General del propio concepto; si
                // no la tiene, el marcador en gris dice cuál se usará por respaldo.
                const general = editaGeneral() && !esConceptoIva(c) && c.id_cuenta;
                const valor = propia
                    ? `${propia.cuenta_codigo} - ${propia.cuenta_nombre}`
                    : (general ? `${c.cuenta_codigo} - ${c.cuenta_nombre || ''}` : '');
                const marcador = marcadorDe(c);
                const conPapelera = editaGeneral() ? !!general : !!propia;
                const falta = faltaDe(c);
                // Cuenta propia de un concepto que ya no aplica con las opciones de la ficha: se
                // conserva (por si la opción vuelve), pero se avisa que el asiento no la usa.
                const inactiva = !!propia && !aplica(c);
                const etiquetaInactiva = inactiva
                    ? ' <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" title="No se usa con las opciones actuales de la pestaña Laboral. Se conserva por si la opción vuelve.">no se usa</span>'
                    : '';
                return `
                <div class="d-flex align-items-center gap-1 border-bottom py-1${esExtra(c) ? ' cc-extra' : ''}${inactiva ? ' opacity-50' : ''}">
                    <span class="small text-truncate${falta ? ' text-danger' : ''}" style="flex:0 0 40%;" title="${esc(c.detalle || c.concepto)}">${esc(c.concepto)}${etiquetaInactiva}</span>
                    <div class="position-relative flex-grow-1">
                        <input type="text" class="form-control form-control-sm py-0 bg-white text-dark${falta ? ' border-danger' : ''}"
                               style="height:26px; font-size:.78rem;" id="${inputId}" value="${esc(valor)}"
                               placeholder="${esc(marcador)}" title="${esc(valor || marcador)}" autocomplete="off"
                               data-fctb-cuenta="1"
                               data-asiento-tipo="${esc(c.id_asiento_tipo)}"
                               data-tarifa-iva="${esConceptoIva(c) ? esc(c.id_referencia) : ''}"
                               data-tipo-cuenta="${esc(c.tipo_cuenta || '')}"
                               data-regla="${propia ? esc(propia.id) : ''}"
                               data-general="${general ? '1' : ''}"
                               data-concepto="${esc(c.concepto)}"
                               ${puedeEditar ? '' : 'readonly'}>
                        <div class="list-group position-absolute w-100 shadow-sm bg-white border rounded"
                             data-fctb-sug="1" style="display:none; z-index:2200; max-height:180px; overflow-y:auto;"></div>
                    </div>
                    <button type="button" class="btn btn-link text-danger p-0 border-0 lh-1${conPapelera && puedeEliminar() ? '' : ' invisible'}"
                            data-fctb-quitar="${propia ? esc(propia.id) : ''}"
                            data-fctb-quitar-general="${general ? esc(c.id_asiento_tipo) : ''}"
                            title="${editaGeneral() ? 'Quitar la cuenta General de este concepto (para todos los empleados)' : 'Quitar esta cuenta'}">
                        <i class="bi bi-trash small"></i>
                    </button>
                </div>`;
            };

            const esDebe = (c) => String(c.debe_haber || 'debe').toLowerCase() === 'debe';
            const colClase = (l) => !extras ? '' : (l.length && l.every(esExtra) ? 'cc-col-extra' : 'cc-col-principal');
            const columna = (titulo, fondo, color, icono, l) => `
                <div class="col-md-6 ${colClase(l)}">
                    <div class="border rounded-3 h-100">
                        <div class="fw-bold small px-3 py-2 d-flex align-items-center gap-2" style="background:${fondo}; color:${color}; border-top-left-radius:.45rem; border-top-right-radius:.45rem;">
                            <i class="bi ${icono}"></i> ${titulo}
                        </div>
                        <div class="px-2">${l.map(linea).join('') || '<div class="small text-muted py-1">—</div>'}</div>
                    </div>
                </div>`;

            let pie = '';
            if (modoAsiento && !personalizando) {
                pie = '<div class="small text-muted mt-2"><i class="bi bi-diagram-3 me-1"></i>Asiento <b>General</b>: se usan las cuentas de la configuración General según las opciones de la pestaña Laboral.'
                    + (puedeCrear()
                        ? ' <span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Cambiar una cuenta aquí cambia la configuración General de la empresa: afecta a <b>todos los empleados</b>.</span> Para cambiarla solo a este empleado, elija <b>Personalizado</b>.'
                        : '<br><i class="bi bi-lock me-1"></i>Solo lectura: no tiene permiso para crear en Configuración Contable.')
                    + '</div>';
            } else if (!puedeCrear()) {
                pie = '<div class="small text-muted mt-2"><i class="bi bi-lock me-1"></i>Solo lectura: no tiene permiso para crear en Configuración Contable.</div>';
            } else if (modoAsiento) {
                pie = '<div class="small text-muted mt-2"><i class="bi bi-person-gear me-1"></i>Asiento <b>Personalizado</b>: lo que deje vacío sigue usando la cuenta que se muestra en gris.</div>';
            }

            cuerpo.classList.toggle('cc-compacta', compacta);
            const textoExtras = compacta
                ? `<i class="bi bi-chevron-down me-1"></i>Mostrar las demás cuentas (${extras})`
                : '<i class="bi bi-chevron-up me-1"></i>Ocultar las demás cuentas';
            cuerpo.innerHTML = `
                <div class="row g-3">
                    ${columna('Debe', '#E6F1FB', '#0C447C', 'bi-arrow-down-right', lista.filter(esDebe))}
                    ${columna('Haber', '#FAEEDA', '#633806', 'bi-arrow-up-right', lista.filter(c => !esDebe(c)))}
                </div>
                ${extras ? `<button type="button" class="btn btn-link btn-sm p-0 mt-2 text-decoration-none" style="font-size:.78rem;" data-fctb-extras="1">${textoExtras}</button>` : ''}
                ${pie}`;

            pintarEstado();
        }

        /** Resumen de la cabecera y estado de los botones. */
        function pintarEstado() {
            const propias = reglas.length;
            const faltan = visibles().filter(c => aplica(c) && faltaDe(c)).length;
            let html = `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">${propias} cuenta(s) propia(s)</span>`;
            if (!propias && !modoAsiento) {
                html += '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" title="Sin cuentas propias: se contabiliza con la General y el reparto por producto/categoría/marca">usa General</span>';
            }
            if (conceptos.length) {
                html += faltan
                    ? `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" title="Conceptos sin cuenta ni aquí ni en la configuración General">faltan ${faltan}</span>`
                    : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check2 me-1"></i>completa</span>';
            }
            const estado = q('estado');
            if (estado) estado.innerHTML = html;

            // Copiar de General a cuentas propias solo tiene sentido en Personalizado.
            q('copiar')?.classList.toggle('d-none', !editable() || editaGeneral());
            const btnQuitar = q('quitar');
            if (btnQuitar) {
                btnQuitar.classList.toggle('d-none', !puedeEliminar());
                btnQuitar.disabled = !propias;
            }

            if (modoAsiento) {
                const marcar = (b, activo) => {
                    if (!b) return;
                    b.classList.toggle('btn-secondary', activo);
                    b.classList.toggle('active', activo);
                    b.classList.toggle('btn-outline-secondary', !activo);
                };
                const bGen = q('modo-general');
                const bPer = q('modo-personalizado');
                marcar(bGen, !personalizando);
                marcar(bPer, personalizando);
                // Pasar a Personalizado pide permiso de crear; volver a General con cuentas
                // propias las quita, así que pide permiso de eliminar.
                if (bPer) bPer.disabled = !puedeCrear() && !personalizando;
                if (bGen) bGen.disabled = personalizando && propias > 0 && !puedeEliminar();
            }
        }

        function formRegla(idAsientoTipo, idCuenta, tarifaIva) {
            const fd = new FormData();
            fd.append('id_asiento_tipo', String(idAsientoTipo || '0'));
            fd.append('id_cuenta', idCuenta);
            fd.append('tipo_asiento', tipoAsiento());
            fd.append('tipo_referencia', cfg.tipoReferencia);
            fd.append('id_referencia', idEntidad());
            if (tarifaIva !== '' && tarifaIva != null) fd.append('codigo_tarifa_iva', String(tarifaIva));
            return fd;
        }

        /** Confirmación antes de tocar la configuración General desde la ficha (afecta a todos). */
        async function confirmarGeneral(accion, concepto) {
            const texto = `${accion} la cuenta <b>General</b> de <b>${esc(concepto)}</b>.<br>`
                + 'Afecta a <b>todos los empleados</b> que no tengan una cuenta propia en ese concepto, '
                + 'en los roles que se contabilicen desde ahora. Para cambiarla solo a este empleado, use <b>Personalizado</b>.';
            if (window.Swal) {
                const conf = await Swal.fire({
                    title: '¿Cambiar la configuración General?',
                    html: texto,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, cambiar para todos',
                    cancelButtonText: 'Cancelar',
                });
                return conf.isConfirmed;
            }
            return confirm(`${accion} la cuenta General de ${concepto} (afecta a todos los empleados). ¿Continuar?`);
        }

        /** Modo General: guarda la cuenta General del concepto (mismo endpoint que Configuración Contable). */
        async function guardarGeneral(input, idCuenta) {
            if (!await confirmarGeneral('Se cambiará', input.dataset.concepto || '')) { pintar(); return; }
            const fd = new FormData();
            fd.append('id_asiento_tipo', String(input.dataset.asientoTipo || '0'));
            fd.append('id_cuenta', idCuenta);
            fd.append('tipo_asiento', tipoAsiento());
            try {
                const res = await getJson(`${cfg.urlContable}/guardarReglaGeneralAjax`, { method: 'POST', body: fd });
                if (!res.ok) { error(res.error || res.message); pintar(); return; }
                toast('success', 'Cuenta General guardada.');
                cargar(true);
            } catch (e) { console.error(e); error('Error de conexión al guardar la cuenta.'); pintar(); }
        }

        /** Modo General: quita la cuenta General del concepto (el concepto queda con su respaldo o sin cuenta). */
        async function quitarGeneral(idAsientoTipo, concepto) {
            if (!idAsientoTipo || !puedeEliminar()) { pintar(); return; }
            if (!await confirmarGeneral('Se quitará', concepto)) { pintar(); return; }
            const fd = new FormData();
            fd.append('id_asiento_tipo', String(idAsientoTipo));
            try {
                const res = await getJson(`${cfg.urlContable}/eliminarReglaGeneralAjax`, { method: 'POST', body: fd });
                if (!res.ok) { error(res.error || res.message); pintar(); return; }
                toast('success', 'Cuenta General quitada.');
                cargar(true);
            } catch (e) { console.error(e); error('Error de conexión al quitar la cuenta.'); pintar(); }
        }

        async function guardarCuenta(input, idCuenta) {
            if (editaGeneral()) { guardarGeneral(input, idCuenta); return; }
            try {
                const fd = formRegla(input.dataset.asientoTipo, idCuenta, input.dataset.tarifaIva);
                const res = await getJson(`${cfg.urlContable}/guardarReglaDimensionAjax`, { method: 'POST', body: fd });
                if (!res.ok) { error(res.error || res.message); return; }
                toast('success', 'Cuenta guardada.');
                cargar(true);
            } catch (e) { console.error(e); error('Error de conexión al guardar la cuenta.'); }
        }

        async function quitarCuenta(idRegla) {
            if (!idRegla) return;
            const fd = new FormData();
            fd.append('id', idRegla);
            try {
                const res = await getJson(`${cfg.urlContable}/eliminarReglaDimensionAjax`, { method: 'POST', body: fd });
                if (!res.ok) { error(res.error || res.message); cargar(true); return; }
                toast('success', 'Cuenta quitada.');
                cargar(true);
            } catch (e) { console.error(e); error('Error de conexión al quitar la cuenta.'); }
        }

        async function copiarDeGeneral() {
            // Solo los conceptos que aplican con las opciones de la ficha (todos, sin data-nomina).
            const aplicables = conceptos.filter(c => c.id_cuenta && !propiaDe(c) && aplica(c));
            if (!aplicables.length) {
                toast('info', 'No hay cuentas de General que copiar (o ya las tiene todas).');
                return;
            }
            let copiadas = 0;
            for (const c of aplicables) {
                try {
                    const fd = formRegla(c.id_asiento_tipo, c.id_cuenta, esConceptoIva(c) ? c.id_referencia : '');
                    const res = await getJson(`${cfg.urlContable}/guardarReglaDimensionAjax`, { method: 'POST', body: fd });
                    if (res.ok) copiadas++;
                } catch (e) { console.error(e); }
            }
            toast(copiadas ? 'success' : 'warning', copiadas ? `Se copiaron ${copiadas} cuenta(s) de General.` : 'No se pudo copiar ninguna cuenta.');
            cargar(true);
        }

        async function quitarTodo() {
            const nombre = (cfg.nombreInput && document.getElementById(cfg.nombreInput)?.value) || cfg.entidad || '';
            if (window.Swal) {
                const conf = await Swal.fire({
                    title: '¿Quitar toda la configuración?',
                    html: `Se quitarán <b>todas</b> las cuentas propias de <b>${esc(nombre)}</b>.<br>Pasará a contabilizarse con la configuración General.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Sí, quitar todo',
                    cancelButtonText: 'Cancelar',
                });
                if (!conf.isConfirmed) return;
            } else if (!confirm('¿Quitar toda la configuración contable?')) {
                return;
            }

            const fd = new FormData();
            fd.append('tipo_asiento', tipoAsiento());
            fd.append('tipo_referencia', cfg.tipoReferencia);
            fd.append('id_referencia', idEntidad());
            try {
                const res = await getJson(`${cfg.urlContable}/eliminarReglasEntidadAjax`, { method: 'POST', body: fd });
                if (!res.ok) { error(res.error || res.message); return; }
                toast('success', res.msg || 'Configuración quitada.');
                personalizando = false;
                cargar(true);
            } catch (e) { console.error(e); error('Error de conexión.'); }
        }

        /** Selector «Asiento: General | Personalizado» (data-modo-asiento). */
        function elegirPersonalizado() {
            if (personalizando || !puedeCrear()) return;
            personalizando = true;
            pintar();
        }

        function elegirGeneral() {
            if (!personalizando) return;
            // Con cuentas propias, volver a General es quitarlas todas (pide confirmación).
            if (reglas.length) { quitarTodo(); return; }
            personalizando = false;
            pintar();
        }

        const sugDe = (input) => input.parentElement.querySelector('[data-fctb-sug]');

        function cerrarSugerencias(excepto) {
            panel.querySelectorAll('[data-fctb-sug]').forEach(s => { if (s !== excepto) s.style.display = 'none'; });
        }

        function buscarCuentas(input) {
            const sug = sugDe(input);
            const texto = input.value.trim();
            if (texto.length < 2) { sug.style.display = 'none'; return; }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                try {
                    const url = `${cfg.urlCuentas}?q=${encodeURIComponent(texto)}&tipo=${encodeURIComponent(input.dataset.tipoCuenta || '')}`;
                    const res = await getJson(url);
                    sug.innerHTML = '';
                    if (res.ok && Array.isArray(res.data) && res.data.length) {
                        res.data.forEach(c => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small text-dark bg-white text-start';
                            btn.textContent = `${c.codigo} - ${c.nombre}`;
                            btn.addEventListener('click', () => {
                                input.value = `${c.codigo} - ${c.nombre}`;
                                sug.style.display = 'none';
                                guardarCuenta(input, c.id);
                            });
                            sug.appendChild(btn);
                        });
                        cerrarSugerencias(sug);
                        sug.style.display = 'block';
                    } else {
                        sug.style.display = 'none';
                    }
                } catch (e) { console.error(e); }
            }, 300);
        }

        // ── Eventos ─────────────────────────────────────────────────────────
        document.querySelector(`[data-bs-target="#${panel.id}"]`)?.addEventListener('shown.bs.tab', () => cargar(false));
        q('tipo')?.addEventListener('change', () => cargar(true));
        q('copiar')?.addEventListener('click', copiarDeGeneral);
        q('quitar')?.addEventListener('click', quitarTodo);
        q('modo-personalizado')?.addEventListener('click', elegirPersonalizado);
        q('modo-general')?.addEventListener('click', elegirGeneral);

        // Cambiar una opción de la ficha (Fondos de Reserva, Décimos, IESS) cambia qué conceptos
        // aplican: se repinta con lo ya cargado, sin volver a pedirlo al servidor.
        Object.values(fuentes).forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => {
                if (conceptos.length) pintar();
            });
        });

        // Delegación: los inputs se repintan en cada carga.
        panel.addEventListener('input', (e) => {
            const input = e.target.closest('input[data-fctb-cuenta]');
            if (!input || input.readOnly) return;
            if (input.value.trim() === '') {
                sugDe(input).style.display = 'none';
                // En General, vaciar el campo solo deja escribir otra cuenta: quitar la cuenta
                // General (afecta a todos) se hace únicamente con la papelera, con confirmación.
                if (!editaGeneral() && input.dataset.regla && puedeEliminar()) quitarCuenta(input.dataset.regla);
                return;
            }
            buscarCuentas(input);
        });

        panel.addEventListener('keydown', (e) => {
            const input = e.target.closest('input[data-fctb-cuenta]');
            if (!input) return;
            // Enter no debe enviar el formulario de la ficha.
            if (e.key === 'Enter') { e.preventDefault(); return; }
            if (e.key === 'Escape') { sugDe(input).style.display = 'none'; return; }
            // Cuenta ya elegida: Retroceso/Suprimir la limpia de una vez (no letra por letra).
            // En General, la cuenta General elegida se limpia de una vez para escribir otra (sin quitarla).
            if ((e.key === 'Backspace' || e.key === 'Delete') && editaGeneral() && input.dataset.general && !input.readOnly
                && input.value === input.defaultValue) {
                e.preventDefault();
                input.value = '';
                return;
            }
            if ((e.key === 'Backspace' || e.key === 'Delete') && !editaGeneral() && input.dataset.regla && !input.readOnly) {
                e.preventDefault();
                if (!puedeEliminar()) return;
                input.value = '';
                quitarCuenta(input.dataset.regla);
            }
        });

        panel.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-fctb-quitar]');
            if (btn && btn.dataset.fctbQuitarGeneral && editaGeneral()) {
                const fila = btn.closest('.d-flex');
                quitarGeneral(btn.dataset.fctbQuitarGeneral, fila?.querySelector('input[data-fctb-cuenta]')?.dataset.concepto || '');
            } else if (btn && btn.dataset.fctbQuitar) {
                quitarCuenta(btn.dataset.fctbQuitar);
            }
            if (e.target.closest('[data-fctb-extras]')) {
                expandida = !expandida;
                pintar();
            }
        });

        document.addEventListener('click', (e) => {
            if (!panel.contains(e.target) || !e.target.closest('.position-relative')) cerrarSugerencias(null);
        });

        // Otra ficha o ficha nueva: lo pintado deja de valer.
        const modalEl = document.getElementById(cfg.modal);
        modalEl?.addEventListener('show.bs.modal', (e) => {
            if (e.target !== modalEl) return;
            cargadoPara = '';
            idRecienGuardado = '';
        });
        modalEl?.addEventListener('hidden.bs.modal', (e) => {
            if (e.target !== modalEl) return;
            cargadoPara = '';
            idRecienGuardado = '';
            conceptos = [];
            reglas = [];
            personalizando = false;
            q('cuerpo').innerHTML = '';
        });

        // El evento sale ANTES de que la ficha escriba el id nuevo en su input: se toma del detalle.
        // Se escucha en document (cliente/proveedor) y en window (el empleado lo emite ahí); un
        // mismo evento que llegue por las dos vías se atiende una sola vez.
        if (cfg.evento) {
            let ultimo = null;
            const alGuardar = (e) => {
                if (e === ultimo) return;
                ultimo = e;
                cargadoPara = '';
                idRecienGuardado = String(e.detail?.id || '');
                if (panel.classList.contains('active')) cargar(true);
            };
            document.addEventListener(cfg.evento, alGuardar);
            window.addEventListener(cfg.evento, alGuardar);
        }

        return { recargar: () => cargar(true) };
    }

    const instancias = {};

    function iniciar() {
        document.querySelectorAll('[data-ficha-contable]').forEach(panel => {
            if (panel.dataset.fctbIniciado) return;
            panel.dataset.fctbIniciado = '1';
            instancias[panel.dataset.tipoReferencia] = crear(panel);
        });
    }

    window.FichaContable = {
        iniciar,
        recargar: (tipoReferencia) => instancias[tipoReferencia]?.recargar(),
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})(window, document);
