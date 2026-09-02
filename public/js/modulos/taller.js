/**
 * Taller Mecánico — lógica del modal de la orden de trabajo.
 *
 * El modal es el puesto de trabajo del asesor: recibe el vehículo, arma el
 * presupuesto, registra la aprobación del cliente, mueve el vehículo entre
 * departamentos y cierra con la entrega y la factura.
 *
 * Los operarios no usan esta pantalla: ellos trabajan desde la tablet de su
 * departamento (taller_estacion.js).
 */
(function () {
    'use strict';

    const RUTA = window.RUTA_MODULO_TALLER;
    const RUTA_DEPARTAMENTOS = window.RUTA_TALLER_DEPARTAMENTOS;
    const RUTA_CHECKLIST = window.RUTA_TALLER_CHECKLIST;
    const PERM = window.TLL_PERM || {};
    /** Se reemplaza al crear departamentos al vuelo, por eso no es const. */
    let DEPARTAMENTOS = window.TLL_DEPARTAMENTOS || [];
    const EMPLEADOS = window.TLL_EMPLEADOS || [];
    const CHECKLIST_BASE = window.TLL_CHECKLIST_BASE || [];
    const PUNTOS = window.TLL_PUNTOS || [];
    const BODEGAS = window.TLL_BODEGAS || [];
    const FORMAS_PAGO = window.TLL_FORMAS_PAGO || [];

    /** Orden que está abierta en el modal. */
    let ordenActual = null;
    let infoAdicional = [];
    let checklist = [];
    let debounceProd = null;
    let debounceCli = null;
    let debounceVeh = null;

    // ─── Utilidades ──────────────────────────────────────────────────────────

    const $ = (id) => document.getElementById(id);
    const val = (id) => ($(id) ? $(id).value.trim() : '');
    const setVal = (id, v) => { if ($(id)) $(id).value = (v === null || v === undefined) ? '' : v; };
    const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmt = (n) => num(n).toFixed(2);
    // Precio unitario: respeta los decimales configurados por la empresa (no
    // siempre 2) — si no, un precio con más precisión (ej. 109.5650) se trunca
    // antes de calcular el IVA y el total con impuestos queda 1 centavo distinto
    // del precio real del catálogo.
    const DEC_PRECIO = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_precio) || 2;

    function fechaHora(v) {
        if (!v) return '';
        const d = new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return String(v);
        const p = (x) => String(x).padStart(2, '0');
        return `${p(d.getDate())}-${p(d.getMonth() + 1)}-${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    }

    function paraInput(v) {
        if (!v) return '';
        return String(v).replace(' ', 'T').substring(0, 16);
    }

    function toast(msg, tipo) {
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 2600,
            icon: tipo || 'success', title: msg
        });
    }

    /**
     * Diálogo anclado al modal abierto.
     *
     * Bootstrap atrapa el foco dentro del modal activo: un SweetAlert montado en
     * el <body> se ve, pero el modal le devuelve el foco y sus campos de texto
     * no aceptan escritura. Anclarlo al modal lo deja usable.
     */
    function dialogo(opciones) {
        const modalAbierto = document.querySelector('.modal.show');
        return Swal.fire(modalAbierto ? Object.assign({ target: modalAbierto }, opciones) : opciones);
    }

    function error(msg) {
        return dialogo({
            icon: 'error',
            title: 'Atención',
            text: msg || 'Ocurrió un error inesperado.'
        });
    }

    async function post(url, body, esJson) {
        const opts = { method: 'POST' };
        if (esJson) {
            opts.headers = { 'Content-Type': 'application/json' };
            opts.body = JSON.stringify(body);
        } else {
            const fd = new FormData();
            Object.entries(body || {}).forEach(([k, v]) => fd.append(k, v === null || v === undefined ? '' : v));
            opts.body = fd;
        }
        const res = await fetch(url, opts);
        return res.json();
    }

    const depNombre = (id) => (DEPARTAMENTOS.find((d) => d.id === Number(id)) || {}).nombre || '';
    const depColor = (id) => (DEPARTAMENTOS.find((d) => d.id === Number(id)) || {}).color || '#6c757d';

    const ETIQUETA_TIPO = {
        repuesto: 'Repuesto', mano_obra: 'Mano obra', insumo: 'Insumo', tercero: 'Terceros'
    };
    const ETIQUETA_ESTADO_LINEA = {
        sugerida: ['warning', 'Sugerida'], aprobada: ['primary', 'Aprobada'],
        rechazada: ['danger', 'Rechazada'], ejecutada: ['success', 'Ejecutada']
    };
    const ETIQUETA_ESTADO = {
        recepcion: 'Recepción', diagnostico: 'Diagnóstico', presupuesto: 'Presupuesto',
        aprobada: 'Aprobada', en_proceso: 'En proceso', control_calidad: 'Control de calidad',
        terminada: 'Terminada', entregada: 'Entregada', facturada: 'Facturada', anulada: 'Anulada'
    };

    /** Una orden cerrada ya no se toca. */
    function estaCerrada() {
        if (!ordenActual) return false;
        return ['entregada', 'facturada', 'anulada'].includes(ordenActual.estado) || !!ordenActual.id_documento;
    }

    // ─── Apertura del modal ──────────────────────────────────────────────────

    window.tllAbrirNuevo = function () {
        if (!PERM.crear) return error('No tiene permiso para registrar órdenes.');

        ordenActual = null;
        infoAdicional = [];
        checklist = [];
        // Se revalida contra el punto de emisión que quede seleccionado.
        window.TLL_SECUENCIAL_CONFIGURADO = undefined;
        limpiarFormulario();

        $('tllTitulo').innerHTML = '<i class="bi bi-wrench-adjustable-circle me-1 text-primary"></i> Nueva orden de trabajo';
        $('tll_estado_badge').classList.add('d-none');
        $('tll_aprob_badge').classList.add('d-none');
        $('tll_btn_eliminar').classList.add('d-none');

        const ahora = new Date();
        ahora.setMinutes(ahora.getMinutes() - ahora.getTimezoneOffset());
        setVal('tll_fecha_ingreso', ahora.toISOString().substring(0, 16));

        cargarChecklistBase();
        pintarTodo();
        tllSerieChange();

        new bootstrap.Modal($('modalOrdenTaller')).show();
    };

    window.tllAbrirVer = async function (tr) {
        const row = JSON.parse(tr.getAttribute('data-row'));
        await abrirOrden(row.id);
    };

    /** Permite abrir una orden por id desde fuera (p. ej. el tablero). */
    window.tllAbrirPorId = abrirOrden;

    async function abrirOrden(id) {
        try {
            const res = await fetch(`${RUTA}/getDetalleAjax?id=${id}`);
            const data = await res.json();
            if (!data.ok) return error(data.error);

            ordenActual = data.data;
            infoAdicional = Array.isArray(ordenActual.info_adicional) ? ordenActual.info_adicional : [];
            checklist = (ordenActual.checklist || []).map((c) => ({
                grupo: c.grupo, item: c.item, valor: c.valor, observacion: c.observacion || '', orden: c.orden
            }));

            cargarEnFormulario(ordenActual);
            pintarTodo();

            const modalEl = $('modalOrdenTaller');
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        } catch (e) {
            console.error(e);
            error('No se pudo abrir la orden.');
        }
    }

    function limpiarFormulario() {
        ['tll_id', 'tll_id_vehiculo', 'tll_id_cliente', 'tll_numero_orden', 'tll_cliente_busqueda',
            'tll_vehiculo_busqueda', 'tll_kilometraje', 'tll_motivo_ingreso', 'tll_observaciones',
            'tll_diagnostico', 'tll_nombre_usuario', 'tll_telefono_contacto', 'tll_correo_contacto',
            'tll_fecha_estimada_entrega', 'tll_aseguradora', 'tll_numero_siniestro', 'tll_ajustador',
            'tll_entregado_a', 'tll_kilometraje_salida', 'tll_proximo_mantenimiento_km',
            'tll_proxima_cita', 'tll_recomendaciones', 'tll_aprobado_por', 'tll_aprobado_observacion',
            'tll_nota_concepto', 'tll_nota_detalle'
        ].forEach((id) => setVal(id, ''));

        setVal('tll_deducible', '0');
        setVal('tll_garantia_dias', '0');
        setVal('tll_garantia_km', '0');
        setVal('tll_prioridad', 'normal');
        setVal('tll_tipo_servicio', 'correctivo');
        setVal('tll_nivel_combustible', '');
        setVal('tll_id_empleado_asesor', '');
        setVal('tll_id_empleado_jefe', '');
        if ($('tll_es_siniestro')) $('tll_es_siniestro').checked = false;
        $('tll_bloque_siniestro').classList.add('d-none');
        $('tll_info_vehiculo').innerHTML = '';
        $('tll_doc_info').textContent = '';
    }

    function cargarEnFormulario(o) {
        setVal('tll_id', o.id);
        setVal('tll_id_vehiculo', o.id_vehiculo);
        setVal('tll_id_cliente', o.id_cliente || '');
        setVal('tll_numero_orden', o.numero_orden);
        setVal('tll_secuencial', o.secuencial);
        setVal('tll_id_punto_emision', o.id_punto_emision || '');
        setVal('tll_id_establecimiento', o.id_establecimiento || '');
        if ($('tll_select_serie') && o.id_punto_emision) $('tll_select_serie').value = o.id_punto_emision;

        setVal('tll_fecha_ingreso', paraInput(o.fecha_ingreso));
        setVal('tll_fecha_estimada_entrega', paraInput(o.fecha_estimada_entrega));
        setVal('tll_kilometraje', o.kilometraje ?? '');
        setVal('tll_nivel_combustible', o.nivel_combustible || '');
        setVal('tll_prioridad', o.prioridad || 'normal');
        setVal('tll_tipo_servicio', o.tipo_servicio || 'correctivo');
        setVal('tll_id_bodega', o.id_bodega || '');
        setVal('tll_motivo_ingreso', o.motivo_ingreso || '');
        setVal('tll_observaciones', o.observaciones || '');
        setVal('tll_diagnostico', o.diagnostico_texto || '');
        setVal('tll_recomendaciones', o.recomendaciones || '');
        setVal('tll_nombre_usuario', o.nombre_usuario || '');
        setVal('tll_telefono_contacto', o.telefono_contacto || '');
        setVal('tll_correo_contacto', o.correo_contacto || '');
        setVal('tll_id_empleado_asesor', o.id_empleado_asesor || '');
        setVal('tll_id_empleado_jefe', o.id_empleado_jefe || '');

        const sin = o.es_siniestro === true || o.es_siniestro === 't' || o.es_siniestro === 'true';
        if ($('tll_es_siniestro')) $('tll_es_siniestro').checked = sin;
        $('tll_bloque_siniestro').classList.toggle('d-none', !sin);
        setVal('tll_aseguradora', o.aseguradora || '');
        setVal('tll_numero_siniestro', o.numero_siniestro || '');
        setVal('tll_deducible', o.deducible ?? 0);
        setVal('tll_ajustador', o.ajustador || '');

        setVal('tll_garantia_dias', o.garantia_dias ?? 0);
        setVal('tll_garantia_km', o.garantia_km ?? 0);
        setVal('tll_proximo_mantenimiento_km', o.proximo_mantenimiento_km ?? '');
        setVal('tll_proxima_cita', o.proxima_cita ? String(o.proxima_cita).substring(0, 10) : '');
        setVal('tll_entregado_a', o.entregado_a || '');
        setVal('tll_kilometraje_salida', o.kilometraje_salida ?? '');

        setVal('tll_cliente_busqueda', o.cliente_nombre || '');
        setVal('tll_vehiculo_busqueda', [o.placa, o.marca, o.modelo].filter(Boolean).join(' · '));

        $('tllTitulo').innerHTML = `<i class="bi bi-wrench-adjustable-circle me-1 text-primary"></i> Orden ${esc(o.numero_orden)}`;
        $('tll_estado_badge').textContent = ETIQUETA_ESTADO[o.estado] || o.estado;
        $('tll_estado_badge').classList.remove('d-none');

        pintarInfoVehiculo(o);
        cargarHistorialVehiculo(o.id_vehiculo, o.id);
    }

    function pintarInfoVehiculo(o) {
        const partes = [];
        if (o.anio) partes.push(`<span><i class="bi bi-calendar3 text-muted"></i> ${esc(o.anio)}</span>`);
        if (o.color) partes.push(`<span><i class="bi bi-palette text-muted"></i> ${esc(o.color)}</span>`);
        if (o.chasis) partes.push(`<span><i class="bi bi-upc text-muted"></i> ${esc(o.chasis)}</span>`);
        if (o.motor) partes.push(`<span><i class="bi bi-gear text-muted"></i> ${esc(o.motor)}</span>`);
        if (o.cliente_identificacion) partes.push(`<span><i class="bi bi-person-vcard text-muted"></i> ${esc(o.cliente_identificacion)}</span>`);
        $('tll_info_vehiculo').innerHTML = partes.join('');
    }

    // ─── Serie / secuencial ──────────────────────────────────────────────────

    window.tllSerieChange = async function () {
        const sel = $('tll_select_serie');
        if (!sel || !sel.value) {
            // Sin punto de emisión no hay nada que numerar.
            window.TLL_SECUENCIAL_CONFIGURADO = false;
            return;
        }

        const opt = sel.options[sel.selectedIndex];
        setVal('tll_id_punto_emision', sel.value);
        setVal('tll_id_establecimiento', opt.dataset.idEst || '');

        // En una orden ya guardada el secuencial no se vuelve a pedir.
        if (val('tll_id')) return;

        const inputSec = $('tll_secuencial');
        try {
            const res = await fetch(`${RUTA}/getSecuencialAjax?id_punto_emision=${sel.value}`);
            const data = await res.json();

            if (data.ok) {
                setVal('tll_secuencial', data.formateado || '');
                if (inputSec) inputSec.title = data.detalle || 'Siguiente consecutivo';

                // El secuencial 'Ordenes de taller' se configura en Empresa igual
                // que el resto; si falta, se avisa y no se deja emitir la orden.
                window.TLL_SECUENCIAL_CONFIGURADO = (data.configurado !== false);
                if (inputSec) inputSec.classList.toggle('border-danger', data.configurado === false);
                if (data.configurado === false) avisarSecuencialNoConfigurado();
            } else {
                setVal('tll_secuencial', '000000001');
                window.TLL_SECUENCIAL_CONFIGURADO = false;
                if (inputSec) inputSec.classList.add('border-danger');
                avisarSecuencialNoConfigurado();
            }
        } catch (e) {
            console.error(e);
            window.TLL_SECUENCIAL_CONFIGURADO = false;
        }
    };

    /** Sin secuencial configurado no se puede numerar la orden. */
    function avisarSecuencialNoConfigurado() {
        const sinPunto = !val('tll_id_punto_emision');
        dialogo({
            icon: 'warning',
            title: sinPunto ? 'Sin punto de emisión' : 'Secuencial no configurado',
            html: sinPunto
                ? 'Esta empresa no tiene puntos de emisión activos.<br>'
                  + 'Créelos en <strong>Empresa → Puntos de emisión</strong> antes de registrar órdenes.'
                : 'Esta serie no tiene configurado el secuencial <strong>«Ordenes de taller»</strong>.<br>'
                  + 'Créelo en <strong>Empresa → Secuenciales</strong>: elija el punto de emisión y agréguelo '
                  + 'desde el selector <strong>«Agregar Tipo Documento»</strong>.',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#f39c12'
        });
    }

    // ─── Autocompletes ───────────────────────────────────────────────────────

    window.tllBuscarClientes = function (q) {
        clearTimeout(debounceCli);
        const box = $('tll_cli_dropdown');
        if (!q || q.length < 2) { box.classList.add('d-none'); return; }

        debounceCli = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarClientesAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (!data.ok || !data.data.length) { box.classList.add('d-none'); return; }

            box.innerHTML = data.data.map((c) => `
                <button type="button" class="list-group-item list-group-item-action py-1 small"
                        data-cliente='${esc(JSON.stringify(c))}'>
                    <strong>${esc(c.nombre)}</strong><br>
                    <span class="text-muted">${esc(c.identificacion || '')} ${c.telefono ? '· ' + esc(c.telefono) : ''}</span>
                </button>`).join('');
            box.querySelectorAll('[data-cliente]').forEach((b) => {
                b.addEventListener('click', () => seleccionarCliente(JSON.parse(b.getAttribute('data-cliente'))));
            });
            box.classList.remove('d-none');
        }, 300);
    };

    function seleccionarCliente(c) {
        setVal('tll_id_cliente', c.id);
        setVal('tll_cliente_busqueda', c.nombre);
        if (!val('tll_telefono_contacto') && c.telefono) setVal('tll_telefono_contacto', c.telefono);
        if (!val('tll_correo_contacto') && c.correo) setVal('tll_correo_contacto', c.correo);

        // El correo del cliente pasa a Info. adicional: es el que usará la
        // factura o el recibo que se emita desde esta orden.
        ordenActual = ordenActual || {};
        ordenActual._clienteCorreo = c.correo || '';
        actualizarInfoCorreoCliente(correoDelCliente());

        $('tll_cli_dropdown').classList.add('d-none');
    }

    window.tllBuscarVehiculos = function (q) {
        clearTimeout(debounceVeh);
        const box = $('tll_veh_dropdown');
        if (!q || q.length < 2) { box.classList.add('d-none'); return; }

        debounceVeh = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarVehiculosAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (!data.ok || !data.data.length) { box.classList.add('d-none'); return; }

            box.innerHTML = data.data.map((v) => `
                <button type="button" class="list-group-item list-group-item-action py-1 small"
                        data-veh='${esc(JSON.stringify(v))}'>
                    <strong>${esc(v.placa)}</strong> — ${esc(v.marca || '')} ${esc(v.modelo || '')}<br>
                    <span class="text-muted">${esc(v.propietario || '')}</span>
                </button>`).join('');
            box.querySelectorAll('[data-veh]').forEach((b) => {
                b.addEventListener('click', () => seleccionarVehiculo(JSON.parse(b.getAttribute('data-veh'))));
            });
            box.classList.remove('d-none');
        }, 300);
    };

    function seleccionarVehiculo(v) {
        setVal('tll_id_vehiculo', v.id);
        setVal('tll_vehiculo_busqueda', [v.placa, v.marca, v.modelo].filter(Boolean).join(' · '));
        if (!val('tll_kilometraje') && v.kilometraje_actual) setVal('tll_kilometraje', v.kilometraje_actual);
        if (!val('tll_nombre_usuario') && v.propietario) setVal('tll_nombre_usuario', v.propietario);
        if (!val('tll_telefono_contacto') && v.telefono) setVal('tll_telefono_contacto', v.telefono);
        if (!val('tll_correo_contacto') && v.correo) setVal('tll_correo_contacto', v.correo);

        // El vehículo puede venir ya vinculado a un cliente del sistema.
        if (!val('tll_id_cliente') && v.id_cliente) {
            setVal('tll_id_cliente', v.id_cliente);
            setVal('tll_cliente_busqueda', v.cliente_nombre || '');
        }

        // Guardamos el snapshot que viaja con la orden.
        ordenActual = ordenActual || {};
        ordenActual._snapshot = {
            placa: v.placa, marca: v.marca, modelo: v.modelo, anio: v.anio,
            color: v.color, chasis: v.chasis, motor: v.motor
        };
        pintarInfoVehiculo(Object.assign({}, ordenActual._snapshot, { cliente_identificacion: v.cliente_identificacion }));

        $('tll_veh_dropdown').classList.add('d-none');
        cargarHistorialVehiculo(v.id, val('tll_id') || 0);
    }

    async function cargarHistorialVehiculo(idVehiculo, excluir) {
        const box = $('tll_historial_vehiculo');
        if (!box || !idVehiculo) return;

        try {
            const res = await fetch(`${RUTA}/historialVehiculoAjax?id_vehiculo=${idVehiculo}&excluir=${excluir || 0}`);
            const data = await res.json();
            if (!data.ok || !data.data.length) {
                box.innerHTML = '<span class="text-muted">Este vehículo no tiene visitas anteriores.</span>';
                return;
            }
            box.innerHTML = `
                <table class="table table-sm mb-0">
                    <thead><tr><th>Fecha</th><th>Orden</th><th>Km</th><th>Motivo</th><th class="text-end">Total</th></tr></thead>
                    <tbody>${data.data.map((h) => `
                        <tr>
                            <td>${esc(fechaHora(h.fecha_ingreso))}</td>
                            <td class="text-primary fw-semibold">${esc(h.numero_orden)}</td>
                            <td>${h.kilometraje ? esc(h.kilometraje) : '—'}</td>
                            <td class="text-truncate" style="max-width:260px">${esc(h.motivo_ingreso || '')}</td>
                            <td class="text-end">${fmt(h.total)}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>`;
        } catch (e) {
            console.error(e);
        }
    }

    /**
     * Busca en el catálogo de productos y servicios. Trae ambos: un taller
     * factura repuestos (bienes) y mano de obra (servicios), y cada resultado
     * indica cuál es para no confundirlos.
     */
    window.tllBuscarProductos = function (q) {
        clearTimeout(debounceProd);
        const box = $('tll_prod_dropdown');
        if (!box) return;
        if (!q || q.length < 2) { box.classList.add('d-none'); return; }

        debounceProd = setTimeout(async () => {
            try {
                const params = new URLSearchParams({
                    q: q,
                    id_bodega: val('tll_id_bodega') || 0,
                    id_orden: val('tll_id') || 0
                });
                const res = await fetch(`${RUTA}/getProductosAjax?${params}`);
                const data = await res.json();

                if (!data.ok || !data.data.length) {
                    box.innerHTML = '<div class="list-group-item small text-muted py-2">'
                        + 'Nada en el catálogo con ese texto. Puede escribirlo libre y se cobra igual.</div>';
                    box.classList.remove('d-none');
                    return;
                }

                box.innerHTML = data.data.map((p) => {
                    const precio = (p.precios_lista && p.precios_lista.length) ? p.precios_lista[0].precio : (p.precio_venta || 0);
                    // Servicio = no inventariable. Un elaborado sí es un bien
                    // aunque no lleve stock propio, por eso no vale usar controla_stock.
                    const esServicio = !(p.inventariable === true || p.inventariable === 't'
                        || p.inventariable === '1' || p.inventariable === 1);
                    const etiqueta = esServicio
                        ? '<span class="badge bg-info bg-opacity-10 text-info ms-1">Servicio</span>'
                        : (p.controla_stock
                            ? `<span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">Stock: ${fmt(p.stock_actual)}</span>`
                            : '<span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">Repuesto</span>');

                    return `<button type="button" class="list-group-item list-group-item-action py-1 small"
                                data-prod='${esc(JSON.stringify({
                                    id: p.id, nombre: p.nombre, precio: precio,
                                    codigo: p.codigo, servicio: esServicio
                                }))}'>
                                <strong>${esc(p.nombre)}</strong>${etiqueta}<br>
                                <span class="text-muted">${esc(p.codigo || '')} · $ ${fmt(precio)}</span>
                            </button>`;
                }).join('');

                box.querySelectorAll('[data-prod]').forEach((b) => {
                    b.addEventListener('click', () => seleccionarProducto(JSON.parse(b.getAttribute('data-prod'))));
                });
                box.classList.remove('d-none');
            } catch (e) {
                console.error(e);
                box.classList.add('d-none');
            }
        }, 300);
    };

    function seleccionarProducto(p) {
        setVal('tll_l_id_producto', p.id);
        setVal('tll_l_descripcion', p.nombre);
        setVal('tll_l_precio', num(p.precio).toFixed(DEC_PRECIO));

        // El tipo se ajusta a lo que realmente es: un servicio del catálogo es
        // mano de obra, y un bien es repuesto. Solo se corrige cuando la
        // elección contradice al catálogo, para no pisar 'insumo' ni 'tercero'.
        const tipo = val('tll_l_tipo');
        if (p.servicio && tipo === 'repuesto') setVal('tll_l_tipo', 'mano_obra');
        if (!p.servicio && tipo === 'mano_obra') setVal('tll_l_tipo', 'repuesto');
        aplicarTipoLinea();

        $('tll_prod_dropdown').classList.add('d-none');
    }

    // Escribir a mano descarta el producto del catálogo: pasa a ser un ítem libre.
    document.addEventListener('DOMContentLoaded', () => {
        const desc = $('tll_l_descripcion');
        if (desc) {
            desc.addEventListener('keydown', (ev) => {
                if (ev.key === 'Backspace' || ev.key === 'Delete') setVal('tll_l_id_producto', '');
            });
        }
        // Si aún no hay cliente, el correo de contacto es el que se usará para
        // enviar el comprobante: se refleja en Info. adicional al escribirlo.
        const correoContacto = $('tll_correo_contacto');
        if (correoContacto) {
            correoContacto.addEventListener('change', () => actualizarInfoCorreoCliente(correoDelCliente()));
        }

        // Cerrar los buscadores al tocar fuera, sin cerrarlos cuando el clic es
        // sobre su propio campo de texto (ahí el usuario está por escribir).
        const buscadores = [
            ['tll_prod_dropdown', 'tll_l_descripcion'],
            ['tll_cli_dropdown', 'tll_cliente_busqueda'],
            ['tll_veh_dropdown', 'tll_vehiculo_busqueda']
        ];
        document.addEventListener('click', (ev) => {
            buscadores.forEach(([idBox, idInput]) => {
                const box = $(idBox);
                if (!box) return;
                if (box.contains(ev.target) || ev.target === $(idInput)) return;
                box.classList.add('d-none');
            });
        });
    });

    /**
     * El usuario cambió el tipo desde el selector: los importes del tipo
     * anterior ya no valen (un repuesto y una hora de taller no cuestan lo
     * mismo), así que se limpian para que nadie los arrastre por descuido.
     */
    window.tllTipoLineaChange = function () {
        setVal('tll_l_horas', '0');
        setVal('tll_l_precio', '0');
        setVal('tll_l_descuento', '0');
        setVal('tll_l_departamento', '');

        aplicarTipoLinea();
        const desc = $('tll_l_descripcion');
        if (desc) desc.focus();
    };

    /**
     * Ajusta la fila al tipo activo sin tocar lo que haya escrito. La usa la
     * selección de un producto del catálogo, que corrige el tipo pero acaba de
     * cargar el precio y no debe perderlo.
     */
    function aplicarTipoLinea() {
        const tipo = val('tll_l_tipo');

        const horas = $('tll_l_horas');
        if (horas) horas.disabled = (tipo !== 'mano_obra');

        const desc = $('tll_l_descripcion');
        if (desc) {
            desc.placeholder = {
                repuesto:  'Buscar repuesto del catálogo, o escribirlo libre...',
                mano_obra: 'Buscar servicio o mano de obra del catálogo, o escribirlo libre...',
                insumo:    'Buscar insumo del catálogo, o escribirlo libre...',
                tercero:   'Describir el trabajo que hace el tercero...'
            }[tipo] || 'Buscar en el catálogo, o escribir libre...';
        }
    }

    // ─── Checklist ───────────────────────────────────────────────────────────

    window.tllCargarChecklistBase = async function () {
        if (checklist.length) {
            const c = await dialogo({
                icon: 'warning',
                title: '¿Reemplazar el checklist?',
                text: 'Se descarta lo marcado hasta ahora y se vuelve a cargar la plantilla del taller.',
                showCancelButton: true, confirmButtonText: 'Reemplazar', cancelButtonText: 'Cancelar'
            });
            if (!c.isConfirmed) return;
        }
        cargarChecklistBase();
        pintarChecklist();
    };

    function cargarChecklistBase() {
        checklist = CHECKLIST_BASE.map((c) => ({
            grupo: c.grupo, item: c.item, valor: 'no', observacion: '', orden: c.orden
        }));
    }

    function pintarChecklist() {
        const cont = $('tll_checklist');
        if (!cont) return;

        if (!checklist.length) {
            cont.innerHTML = '<div class="col-12 text-muted small">Sin checklist configurado. Defina la plantilla en Departamentos del taller.</div>';
            return;
        }

        const grupos = {};
        checklist.forEach((c, i) => {
            (grupos[c.grupo] = grupos[c.grupo] || []).push({ c, i });
        });

        const etiquetaGrupo = {
            accesorios: 'Accesorios', carroceria: 'Carrocería',
            documentos: 'Documentos', niveles: 'Niveles'
        };

        cont.innerHTML = Object.entries(grupos).map(([grupo, items]) => `
            <div class="col-12 col-md-6">
                <div class="border rounded p-1 mb-1">
                    <div class="x-small fw-bold text-muted text-uppercase mb-1">${esc(etiquetaGrupo[grupo] || grupo)}</div>
                    ${items.map(({ c, i }) => `
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <select class="form-select form-select-sm py-0" style="width:74px;height:24px;font-size:.72rem;"
                                    onchange="tllChecklistCambio(${i}, 'valor', this.value)">
                                <option value="si" ${c.valor === 'si' ? 'selected' : ''}>Sí</option>
                                <option value="no" ${c.valor === 'no' ? 'selected' : ''}>No</option>
                                <option value="na" ${c.valor === 'na' ? 'selected' : ''}>N/A</option>
                            </select>
                            <span class="small flex-shrink-0" style="min-width:130px">${esc(c.item)}</span>
                            <input type="text" class="form-control form-control-sm py-0" style="height:24px;font-size:.72rem;"
                                   placeholder="Observación" value="${esc(c.observacion || '')}"
                                   onchange="tllChecklistCambio(${i}, 'observacion', this.value)">
                        </div>`).join('')}
                </div>
            </div>`).join('');
    }

    window.tllChecklistCambio = function (i, campo, valor) {
        if (checklist[i]) checklist[i][campo] = valor;
    };

    // ─── Fotos ───────────────────────────────────────────────────────────────

    window.tllSubirFoto = async function (input) {
        if (!input.files || !input.files[0]) return;
        if (!val('tll_id')) return error('Guarde la orden antes de adjuntar fotos.');

        const fd = new FormData();
        fd.append('id_orden', val('tll_id'));
        fd.append('momento', val('tll_foto_momento') || 'ingreso');
        fd.append('foto', input.files[0]);

        try {
            const res = await fetch(`${RUTA}/subirFotoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            input.value = '';
            if (!data.ok) return error(data.error);

            toast('Foto agregada.');
            await abrirOrden(val('tll_id'));
        } catch (e) {
            console.error(e);
            error('No se pudo subir la foto.');
        }
    };

    window.tllEliminarFoto = async function (id) {
        const c = await dialogo({
            title: '¿Eliminar la foto?', icon: 'warning',
            showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'
        });
        if (!c.isConfirmed) return;

        const data = await post(`${RUTA}/eliminarFotoAjax`, { id: id });
        if (!data.ok) return error(data.error);
        toast('Foto eliminada.');
        await abrirOrden(val('tll_id'));
    };

    function pintarFotos() {
        const cont = $('tll_fotos');
        const ayuda = $('tll_fotos_ayuda');
        if (!cont) return;

        const guardada = !!val('tll_id');
        $('tll_btn_foto').disabled = !guardada || estaCerrada();
        ayuda.classList.toggle('d-none', guardada);

        const fotos = (ordenActual && ordenActual.fotos) || [];
        if (!fotos.length) { cont.innerHTML = ''; return; }

        const base = window.location.origin;
        cont.innerHTML = fotos.map((f) => {
            const url = `${base}/${String(f.ruta_archivo).replace(/^\//, '')}`;
            return `<div class="position-relative">
                        <img src="${esc(url)}" class="tll-foto" title="${esc(f.momento)} — ${esc(f.descripcion || '')}"
                             onclick="window.open('${esc(url)}','_blank')">
                        ${PERM.eliminar && !estaCerrada() ? `
                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 py-0 px-1"
                                    style="font-size:.6rem;line-height:1.2" onclick="tllEliminarFoto(${f.id})">
                                <i class="bi bi-x"></i>
                            </button>` : ''}
                    </div>`;
        }).join('');
    }

    // ─── Guardar la recepción ────────────────────────────────────────────────

    window.tllGuardar = async function () {
        // Sin secuencial configurado no se puede numerar la orden. Solo bloquea
        // al crear: una orden existente ya tiene su número asignado.
        if (!val('tll_id') && window.TLL_SECUENCIAL_CONFIGURADO === false) {
            avisarSecuencialNoConfigurado();
            return;
        }

        const snap = (ordenActual && ordenActual._snapshot) || ordenActual || {};

        const payload = {
            id: val('tll_id') || null,
            id_vehiculo: val('tll_id_vehiculo'),
            id_cliente: val('tll_id_cliente') || null,
            id_bodega: val('tll_id_bodega') || null,
            id_punto_emision: val('tll_id_punto_emision'),
            id_establecimiento: val('tll_id_establecimiento'),
            establecimiento: ($('tll_select_serie') && $('tll_select_serie').selectedIndex >= 0)
                ? $('tll_select_serie').options[$('tll_select_serie').selectedIndex].dataset.codEst : '',
            punto_emision: ($('tll_select_serie') && $('tll_select_serie').selectedIndex >= 0)
                ? $('tll_select_serie').options[$('tll_select_serie').selectedIndex].dataset.codPunto : '',
            secuencial: val('tll_secuencial'),
            placa: snap.placa || '',
            marca: snap.marca || '',
            modelo: snap.modelo || '',
            anio: snap.anio || '',
            color: snap.color || '',
            chasis: snap.chasis || '',
            motor: snap.motor || '',
            kilometraje: val('tll_kilometraje'),
            nivel_combustible: val('tll_nivel_combustible'),
            nombre_usuario: val('tll_nombre_usuario'),
            telefono_contacto: val('tll_telefono_contacto'),
            correo_contacto: val('tll_correo_contacto'),
            id_empleado_asesor: val('tll_id_empleado_asesor') || null,
            id_empleado_jefe: val('tll_id_empleado_jefe') || null,
            fecha_ingreso: val('tll_fecha_ingreso'),
            fecha_estimada_entrega: val('tll_fecha_estimada_entrega'),
            tipo_servicio: val('tll_tipo_servicio'),
            prioridad: val('tll_prioridad'),
            motivo_ingreso: val('tll_motivo_ingreso'),
            diagnostico_texto: val('tll_diagnostico'),
            observaciones: val('tll_observaciones'),
            recomendaciones: val('tll_recomendaciones'),
            es_siniestro: $('tll_es_siniestro') ? $('tll_es_siniestro').checked : false,
            aseguradora: val('tll_aseguradora'),
            numero_siniestro: val('tll_numero_siniestro'),
            deducible: val('tll_deducible'),
            ajustador: val('tll_ajustador'),
            garantia_dias: val('tll_garantia_dias'),
            garantia_km: val('tll_garantia_km'),
            proximo_mantenimiento_km: val('tll_proximo_mantenimiento_km'),
            proxima_cita: val('tll_proxima_cita'),
            info_adicional: recolectarInfoAdicional(),
            checklist: checklist
        };

        try {
            $('tll_btn_guardar').disabled = true;
            const data = await post(`${RUTA}/store`, payload, true);
            if (!data.ok) { error(data.error); return; }

            toast(data.msg);
            await abrirOrden(data.id);
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (e) {
            console.error(e);
            error('No se pudo guardar la orden.');
        } finally {
            $('tll_btn_guardar').disabled = false;
        }
    };

    window.tllEliminar = async function () {
        if (!val('tll_id')) return;

        const c = await dialogo({
            title: '¿Eliminar la orden?',
            text: 'Se devolverán al stock los repuestos que haya consumido.',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;

        const data = await post(`${RUTA}/eliminar`, { id: val('tll_id') });
        if (!data.ok) return error(data.error);

        toast(data.msg);
        bootstrap.Modal.getInstance($('modalOrdenTaller')).hide();
        if (typeof cargarGrid === 'function') cargarGrid();
    };

    // ─── Diagnóstico ─────────────────────────────────────────────────────────

    window.tllGuardarDiagnostico = async function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        if (!val('tll_diagnostico')) return error('Escriba el diagnóstico.');

        const data = await post(`${RUTA}/guardarDiagnosticoAjax`, {
            id: val('tll_id'),
            diagnostico: val('tll_diagnostico')
        });
        if (!data.ok) return error(data.error);

        toast(data.msg);
        await abrirOrden(val('tll_id'));
    };

    // ─── Líneas ──────────────────────────────────────────────────────────────

    window.tllAgregarLinea = async function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        if (!val('tll_l_descripcion')) return error('Escriba qué repuesto o trabajo se agrega.');

        const payload = {
            id_orden: val('tll_id'),
            tipo_linea: val('tll_l_tipo'),
            id_producto: val('tll_l_id_producto') || null,
            descripcion: val('tll_l_descripcion'),
            cantidad: val('tll_l_cantidad') || 1,
            horas: val('tll_l_horas') || 0,
            precio_unitario: val('tll_l_precio') || 0,
            descuento: val('tll_l_descuento') || 0,
            id_departamento: val('tll_l_departamento') || null,
            id_empleado_tecnico: val('tll_l_tecnico') || null,
            id_bodega: val('tll_id_bodega') || null,
            provisto_cliente: $('tll_l_provisto_cliente') ? $('tll_l_provisto_cliente').checked : false,
            observacion: val('tll_l_observacion') || null
        };

        const data = await post(`${RUTA}/agregarLineaAjax`, payload, true);
        if (!data.ok) return error(data.error);

        toast(data.msg);
        ['tll_l_descripcion', 'tll_l_id_producto', 'tll_l_observacion'].forEach((id) => setVal(id, ''));
        setVal('tll_l_cantidad', '1');
        setVal('tll_l_horas', '0');
        setVal('tll_l_precio', '0');
        setVal('tll_l_descuento', '0');
        if ($('tll_l_provisto_cliente')) $('tll_l_provisto_cliente').checked = false;

        await abrirOrden(val('tll_id'));
    };

    window.tllEliminarLinea = async function (id) {
        const c = await dialogo({
            title: '¿Quitar esta línea?', icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;

        const data = await post(`${RUTA}/eliminarLineaAjax`, { id: id });
        if (!data.ok) return error(data.error);

        toast(data.msg);
        await abrirOrden(val('tll_id'));
    };

    window.tllEstadoLinea = async function (id, estado) {
        let motivo = '';
        if (estado === 'rechazada') {
            const r = await dialogo({
                icon: 'question',
                title: 'Rechazo del cliente',
                input: 'text',
                inputLabel: '¿Por qué no lo aprueba?',
                inputPlaceholder: 'Ej. lo va a hacer en otro taller',
                showCancelButton: true,
                confirmButtonText: 'Rechazar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                // El foco se fuerza al abrir: el modal de Bootstrap se lo
                // disputa y sin esto el campo queda sin cursor.
                didOpen: () => {
                    const inp = Swal.getInput();
                    if (inp) setTimeout(() => inp.focus(), 50);
                }
            });
            if (!r.isConfirmed) return;
            motivo = r.value || '';
        }

        const data = await post(`${RUTA}/estadoLineaAjax`, { id: id, estado: estado, motivo: motivo });
        if (!data.ok) return error(data.error);

        toast(data.msg);
        await abrirOrden(val('tll_id'));
    };

    function pintarLineas() {
        const tbody = $('tll_tbody_lineas');
        if (!tbody) return;

        const lineas = (ordenActual && ordenActual.detalles) || [];
        $('tll_badge_lineas').textContent = lineas.length;

        if (!lineas.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3 small">Sin repuestos ni trabajos registrados.</td></tr>';
            return;
        }

        const cerrada = estaCerrada();
        tbody.innerHTML = lineas.map((l) => {
            const [color, txt] = ETIQUETA_ESTADO_LINEA[l.estado_linea] || ['secondary', l.estado_linea];
            const provisto = (l.provisto_cliente === true || l.provisto_cliente === 't')
                ? ' <span class="badge bg-info bg-opacity-10 text-info x-small">del cliente</span>' : '';

            // Los tres iconos son distintos a propósito: el pulgar decide qué
            // dijo el cliente y la papelera borra la línea. El rojo queda
            // reservado para eliminar, que es lo único irreversible.
            let acciones = '';
            if (!cerrada && PERM.actualizar) {
                if (l.estado_linea === 'sugerida') {
                    acciones += `<button type="button" class="btn btn-outline-success btn-sm py-0 px-1" title="El cliente lo aprueba"
                                    onclick="tllEstadoLinea(${l.id}, 'aprobada')"><i class="bi bi-hand-thumbs-up"></i></button>`;
                    acciones += `<button type="button" class="btn btn-outline-warning btn-sm py-0 px-1 ms-1" title="El cliente lo rechaza"
                                    onclick="tllEstadoLinea(${l.id}, 'rechazada')"><i class="bi bi-hand-thumbs-down"></i></button>`;
                }
                if (PERM.eliminar) {
                    acciones += `<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 ms-1" title="Quitar la línea de la orden"
                                    onclick="tllEliminarLinea(${l.id})"><i class="bi bi-trash3"></i></button>`;
                }
            }

            return `<tr class="row-detalle">
                    <td class="ps-2 small">${esc(l.descripcion)}${provisto}</td>
                    <td class="small">${esc(ETIQUETA_TIPO[l.tipo_linea] || l.tipo_linea)}</td>
                    <td class="small">${l.departamento_nombre
                        ? `<span class="badge rounded-pill" style="background:${esc(l.departamento_color || '#6c757d')}1a;color:${esc(l.departamento_color || '#6c757d')}">${esc(l.departamento_nombre)}</span>`
                        : '<span class="text-muted">—</span>'}</td>
                    <td class="small text-truncate" style="max-width:120px">${esc(l.tecnico_nombre || '—')}</td>
                    <td class="text-end small">${fmt(l.cantidad)}</td>
                    <td class="text-end small">${fmt(l.precio_unitario)}</td>
                    <td class="text-end small fw-semibold">${fmt(l.total_linea)}</td>
                    <td class="text-center"><span class="badge bg-${color} bg-opacity-10 text-${color}">${esc(txt)}</span></td>
                    <td class="text-center pe-2">${acciones}</td>
                </tr>`;
        }).join('');
    }

    function pintarTotales() {
        const o = ordenActual || {};
        setTexto('tll_t_repuestos', fmt(o.subtotal_repuestos));
        setTexto('tll_t_mano_obra', fmt(o.subtotal_mano_obra));
        setTexto('tll_t_descuento', fmt(o.descuento));
        setTexto('tll_t_iva', fmt(o.iva));
        setTexto('tll_t_total', fmt(o.total));
    }

    const setTexto = (id, t) => { if ($(id)) $(id).textContent = t; };

    // ─── Aprobación ──────────────────────────────────────────────────────────

    window.tllAprobar = async function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        if (!val('tll_aprobado_por')) return error('Indique quién aprobó por parte del cliente.');

        const data = await post(`${RUTA}/aprobarAjax`, {
            id: val('tll_id'),
            aprobado_por: val('tll_aprobado_por'),
            aprobado_medio: val('tll_aprobado_medio'),
            aprobado_observacion: val('tll_aprobado_observacion')
        });
        if (!data.ok) return error(data.error);

        toast(data.msg);
        await abrirOrden(val('tll_id'));
        if (typeof cargarGrid === 'function') cargarGrid();
    };

    function pintarAprobacion() {
        const o = ordenActual || {};
        const aprobado = o.aprobado === true || o.aprobado === 't' || o.aprobado === 'true';

        const badge = $('tll_aprob_badge');
        badge.classList.toggle('d-none', !o.id);
        if (aprobado) {
            badge.className = 'badge bg-success bg-opacity-10 text-success ms-2';
            badge.innerHTML = '<i class="bi bi-check-circle"></i> Aprobada';
        } else {
            badge.className = 'badge bg-warning bg-opacity-10 text-warning ms-2';
            badge.innerHTML = '<i class="bi bi-clock-history"></i> Sin aprobar';
        }

        const hecha = $('tll_aprobacion_hecha');
        const form = $('tll_aprobacion_form');
        if (aprobado) {
            hecha.classList.remove('d-none');
            form.classList.add('d-none');
            hecha.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>
                Aprobó <strong>${esc(o.aprobado_por || '')}</strong> el ${esc(fechaHora(o.aprobado_fecha))}
                vía ${esc(o.aprobado_medio || '')}.
                ${o.aprobado_observacion ? '<br><span class="text-muted">' + esc(o.aprobado_observacion) + '</span>' : ''}`;
        } else {
            hecha.classList.add('d-none');
            form.classList.remove('d-none');
            $('tll_btn_aprobar').disabled = !o.id || estaCerrada() || !PERM.actualizar;
        }
    }

    // ─── Departamentos / etapas ──────────────────────────────────────────────

    window.tllEnviarDepartamento = async function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        if (!val('tll_dep_destino')) return error('Seleccione el departamento de destino.');

        const data = await post(`${RUTA}/enviarDepartamentoAjax`, {
            id: val('tll_id'),
            id_departamento: val('tll_dep_destino')
        });
        if (!data.ok) return error(data.error);

        toast(data.msg);
        await abrirOrden(val('tll_id'));
        if (typeof cargarGrid === 'function') cargarGrid();
    };

    function pintarEtapas() {
        const cont = $('tll_etapas');
        if (!cont) return;

        const o = ordenActual || {};
        const etapas = o.etapas || [];

        $('tll_dep_actual_txt').innerHTML = o.departamento_nombre
            ? `El vehículo está en <strong>${esc(o.departamento_nombre)}</strong>.`
            : (o.id ? 'El vehículo no está asignado a ningún departamento.' : '');

        if (!etapas.length) {
            cont.innerHTML = '<div class="text-muted small">Todavía no pasó por ningún departamento.</div>';
            return;
        }

        const estadoEtapa = {
            pendiente: ['secondary', 'Pendiente'], en_proceso: ['primary', 'En proceso'],
            terminada: ['success', 'Terminada'], omitida: ['secondary', 'Omitida']
        };

        cont.innerHTML = etapas.map((e, i) => {
            const [color, txt] = estadoEtapa[e.estado] || ['secondary', e.estado];
            const dur = duracion(e.fecha_inicio, e.fecha_fin);
            return `
                <div class="border rounded mb-2">
                    <div class="d-flex justify-content-between align-items-center px-2 py-1"
                         style="background:${esc(e.departamento_color || '#6c757d')}14;border-left:3px solid ${esc(e.departamento_color || '#6c757d')}">
                        <div class="fw-bold small">
                            <i class="bi ${esc(e.departamento_icono || 'bi-tools')} me-1"></i>
                            ${i + 1}. ${esc(e.departamento_nombre || '')}
                        </div>
                        <div class="d-flex align-items-center gap-2 x-small">
                            <span class="badge bg-${color} bg-opacity-10 text-${color}">${esc(txt)}</span>
                            <span class="text-muted">${esc(fechaHora(e.fecha_inicio) || 'sin iniciar')} → ${esc(fechaHora(e.fecha_fin) || '—')}</span>
                            ${dur ? `<span class="text-muted"><i class="bi bi-stopwatch"></i> ${esc(dur)}</span>` : ''}
                        </div>
                    </div>
                    <div class="px-2 py-1 small">
                        <div class="text-muted x-small">Responsable: ${esc(e.responsable_nombre || e.usuario_fin_nombre || e.usuario_inicio_nombre || '—')}</div>
                        <div>${e.trabajo_realizado ? esc(e.trabajo_realizado) : '<span class="text-muted">Sin descripción de trabajo.</span>'}</div>
                        ${e.observaciones ? `<div class="text-muted fst-italic x-small mt-1">${esc(e.observaciones)}</div>` : ''}
                    </div>
                </div>`;
        }).join('');
    }

    function duracion(desde, hasta) {
        if (!desde || !hasta) return '';
        const d = new Date(String(desde).replace(' ', 'T'));
        const h = new Date(String(hasta).replace(' ', 'T'));
        if (isNaN(d) || isNaN(h) || h < d) return '';

        const min = Math.round((h - d) / 60000);
        if (min < 60) return `${min} min`;
        const horas = Math.floor(min / 60);
        if (horas < 24) return `${horas}h ${min % 60}min`;
        return `${Math.floor(horas / 24)} d ${horas % 24}h`;
    }

    // ─── Bitácora ────────────────────────────────────────────────────────────

    window.tllAgregarNota = async function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        if (!val('tll_nota_concepto')) return error('Escriba el título de la nota.');

        const data = await post(`${RUTA}/agregarNotaAjax`, {
            id_orden: val('tll_id'),
            concepto: val('tll_nota_concepto'),
            detalle: val('tll_nota_detalle')
        });
        if (!data.ok) return error(data.error);

        setVal('tll_nota_concepto', '');
        setVal('tll_nota_detalle', '');
        toast(data.msg);
        await abrirOrden(val('tll_id'));
    };

    function pintarBitacora() {
        const cont = $('tll_bitacora');
        if (!cont) return;

        const eventos = (ordenActual && ordenActual.bitacora) || [];
        if (!eventos.length) {
            cont.innerHTML = '<div class="text-muted small">Sin eventos registrados.</div>';
            return;
        }

        cont.innerHTML = eventos.map((b) => `
            <div class="tll-ev ev-${esc(b.tipo_evento)}">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold small">${esc(b.concepto)}</span>
                    <span class="text-muted x-small">${esc(fechaHora(b.fecha))}</span>
                </div>
                ${b.detalle ? `<div class="small text-muted">${esc(b.detalle)}</div>` : ''}
                <div class="x-small text-muted">
                    ${esc(b.usuario_nombre || '')}
                    ${b.departamento_nombre ? ' · ' + esc(b.departamento_nombre) : ''}
                </div>
            </div>`).join('');
    }

    // ─── Entrega ─────────────────────────────────────────────────────────────

    window.tllEntregar = async function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        if (!val('tll_entregado_a')) return error('Indique a quién se entrega el vehículo.');

        const c = await dialogo({
            title: '¿Registrar la entrega?',
            text: 'La orden quedará cerrada para edición.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Entregar', cancelButtonText: 'Cancelar'
        });
        if (!c.isConfirmed) return;

        const data = await post(`${RUTA}/entregarAjax`, {
            id: val('tll_id'),
            entregado_a: val('tll_entregado_a'),
            kilometraje_salida: val('tll_kilometraje_salida'),
            recomendaciones: val('tll_recomendaciones'),
            proximo_mantenimiento_km: val('tll_proximo_mantenimiento_km'),
            proxima_cita: val('tll_proxima_cita'),
            garantia_dias: val('tll_garantia_dias'),
            garantia_km: val('tll_garantia_km')
        });
        if (!data.ok) return error(data.error);

        toast(data.msg);
        await abrirOrden(val('tll_id'));
        if (typeof cargarGrid === 'function') cargarGrid();
    };

    // ─── Info adicional ──────────────────────────────────────────────────────

    /**
     * Igual que en Factura de Venta: las filas viven en el DOM y se leen al
     * guardar. Así las filas fijas (el correo del cliente) conviven con las que
     * escribe el usuario sin pisarse entre sí al repintar.
     */
    const ESTILO_INFO = 'padding:0 4px;height:20px;font-size:0.78rem;';

    window.tllAgregarInfo = function (conFoco) {
        const tbody = $('tll_tbody_info');
        if (!tbody) return;

        const tr = document.createElement('tr');
        tr.className = 'tll-row-info';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent tll-info-concepto"
                    style="${ESTILO_INFO}" placeholder="Concepto..."></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent tll-info-detalle"
                    style="${ESTILO_INFO}" placeholder="Detalle..."></td>
            <td class="p-0 text-center pe-1">
                <button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none"
                        onclick="this.closest('tr').remove();">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </td>`;

        // Las filas nuevas van siempre antes de las fijas.
        const primeraFija = tbody.querySelector('tr[data-tipo]');
        if (primeraFija) tbody.insertBefore(tr, primeraFija);
        else tbody.appendChild(tr);

        // Al pintar la orden se agrega una línea en blanco sin robar el foco;
        // solo lo toma cuando el usuario pulsa «Agregar línea».
        if (conFoco !== false) tr.querySelector('.tll-info-concepto').focus();
    };

    /** Repuebla la tabla al abrir una orden. */
    function pintarInfoAdicional() {
        const tbody = $('tll_tbody_info');
        if (!tbody) return;

        tbody.innerHTML = '';
        infoAdicional
            // El correo tiene su propia fila fija; no se duplica como línea suelta.
            .filter((ia) => String(ia.nombre || '').toLowerCase() !== 'correo del cliente')
            .forEach((ia) => {
                const tr = document.createElement('tr');
                tr.className = 'tll-row-info';
                tr.innerHTML = `
                    <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent tll-info-concepto"
                            style="${ESTILO_INFO}" value="${esc(ia.nombre)}"></td>
                    <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent tll-info-detalle"
                            style="${ESTILO_INFO}" value="${esc(ia.valor)}"></td>
                    <td class="p-0 text-center pe-1">
                        <button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none"
                                onclick="this.closest('tr').remove();">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </td>`;
                tbody.appendChild(tr);
            });

        // Siempre queda una línea en blanco lista para escribir, igual que en
        // Factura de Venta. Al guardar, las incompletas se descartan solas.
        tllAgregarInfo(false);

        actualizarInfoCorreoCliente(correoDelCliente());
    }

    /**
     * Correo con el que se emitirá el comprobante. Manda el del cliente; si la
     * orden todavía no tiene cliente, sirve el del contacto que dejó el vehículo.
     * El recién seleccionado gana sobre el que traía la orden.
     */
    function correoDelCliente() {
        const o = ordenActual || {};
        return String(o._clienteCorreo || o.cliente_email || val('tll_correo_contacto') || '').trim();
    }

    /**
     * Fila fija con el correo del cliente. No se puede borrar (se actualiza al
     * cambiar de cliente) pero sí editar, porque es la dirección a la que sale
     * la factura generada desde la orden.
     */
    function actualizarInfoCorreoCliente(email) {
        const tbody = $('tll_tbody_info');
        if (!tbody) return;

        let fila = tbody.querySelector('tr[data-tipo="correo-cliente"]');

        if (!email) {
            if (fila) fila.remove();
            return;
        }
        if (fila) {
            fila.querySelector('.tll-info-detalle').value = email;
            return;
        }

        const tr = document.createElement('tr');
        tr.className = 'tll-row-info';
        tr.dataset.tipo = 'correo-cliente';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent tll-info-concepto"
                    style="${ESTILO_INFO}" value="Correo del cliente" readonly></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent tll-info-detalle"
                    style="${ESTILO_INFO}" value="${esc(email)}"></td>
            <td class="p-0 text-center pe-1">
                <span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span>
            </td>`;
        tbody.appendChild(tr);
    }

    /** Lee la tabla al guardar; descarta las filas incompletas. */
    function recolectarInfoAdicional() {
        const filas = [];
        document.querySelectorAll('#tll_tbody_info tr.tll-row-info').forEach((tr) => {
            const nombre = (tr.querySelector('.tll-info-concepto') || {}).value || '';
            const valor = (tr.querySelector('.tll-info-detalle') || {}).value || '';
            if (nombre.trim() && valor.trim()) {
                filas.push({ nombre: nombre.trim(), valor: valor.trim() });
            }
        });
        return filas;
    }

    // ─── Documentos ──────────────────────────────────────────────────────────

    window.tllPdf = function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        window.open(`${RUTA}/exportarPdfAjax?id=${val('tll_id')}`, '_blank');
    };

    window.tllInforme = function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        window.open(`${RUTA}/informeTecnicoAjax?id=${val('tll_id')}`, '_blank');
    };

    window.tllPrecuenta = function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');
        window.open(`${RUTA}/precuentaAjax?id=${val('tll_id')}`, '_blank');
    };

    /** Documentos que se pueden enviar al cliente. */
    const DOCUMENTOS = [
        { v: 'orden',     l: 'Orden de trabajo' },
        { v: 'informe',   l: 'Informe técnico' },
        { v: 'precuenta', l: 'Precuenta (valores a pagar)' }
    ];

    window.tllCorreo = async function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');

        const o = ordenActual || {};
        const opcionesDoc = DOCUMENTOS.map((d) => `<option value="${d.v}">${esc(d.l)}</option>`).join('');

        const { value: form } = await dialogo({
            title: 'Enviar por correo',
            html: `
                <select id="swal_tipo" class="form-select form-select-sm mb-2">${opcionesDoc}</select>
                <input id="swal_correos" class="form-control form-control-sm"
                       placeholder="correo@ejemplo.com" value="${esc(o.correo_contacto || o.cliente_email || '')}">
                <div class="form-text text-start">Separe varios correos con coma.</div>`,
            showCancelButton: true, confirmButtonText: 'Enviar', cancelButtonText: 'Cancelar',
            preConfirm: () => ({
                tipo: document.getElementById('swal_tipo').value,
                correos: document.getElementById('swal_correos').value.trim()
            })
        });
        if (!form) return;

        dialogo({ title: 'Enviando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        const data = await post(`${RUTA}/enviarCorreoAjax`, {
            id: val('tll_id'), tipo: form.tipo, correos: form.correos
        });
        Swal.close();

        if (!data.ok) return error(data.mensaje);
        toast(data.mensaje);
    };

    /**
     * Envío por WhatsApp con plantilla aprobada por Meta, igual que Factura de
     * Venta: el PDF viaja adjunto al mensaje. Se puede mandar la orden de
     * trabajo, el informe técnico o la precuenta.
     *
     * Si la empresa no tiene WhatsApp configurado, se ofrece el enlace directo
     * de wa.me como alternativa: al menos permite escribirle al cliente.
     */
    window.tllWhatsapp = async function () {
        if (!val('tll_id')) return error('Guarde primero la orden.');

        let datos;
        try {
            const res = await fetch(`${RUTA}/getPlantillasWhatsappAjax?id=${val('tll_id')}`);
            datos = await res.json();
        } catch (e) {
            console.error(e);
            return error('No se pudieron cargar las plantillas de WhatsApp.');
        }
        if (!datos.ok) return error(datos.error);

        if (datos.configurado === false) return whatsappSinConfigurar();
        if (!datos.plantillas || !datos.plantillas.length) {
            return error('No hay plantillas de WhatsApp aprobadas por Meta. Créelas en Plantillas de WhatsApp.');
        }

        const opcionesDoc = DOCUMENTOS.map((d) => `<option value="${d.v}">${esc(d.l)}</option>`).join('');
        const opcionesPlantilla = datos.plantillas.map((p) =>
            `<option value="${p.id}" ${Number(p.id) === Number(datos.id_plantilla_default) ? 'selected' : ''}>${esc(p.nombre)}</option>`
        ).join('');

        const { value: form } = await dialogo({
            title: 'Enviar por WhatsApp',
            html: `
                <div class="text-start">
                    <label class="form-label small fw-bold mb-1">Documento a enviar</label>
                    <select id="swal_wa_tipo" class="form-select form-select-sm mb-2">${opcionesDoc}</select>

                    <label class="form-label small fw-bold mb-1">Plantilla</label>
                    <select id="swal_wa_plantilla" class="form-select form-select-sm mb-2">${opcionesPlantilla}</select>
                    <div class="form-text mb-2">Solo se listan las plantillas aprobadas por Meta.</div>

                    <label class="form-label small fw-bold mb-1">Teléfono</label>
                    <input id="swal_wa_telefono" class="form-control form-control-sm"
                           value="${esc(datos.telefono_cliente || '593')}" placeholder="593987654321">
                    <div class="form-text">Código de país + número, sin el signo +.</div>
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            preConfirm: () => ({
                tipo: document.getElementById('swal_wa_tipo').value,
                id_plantilla: document.getElementById('swal_wa_plantilla').value,
                telefono: document.getElementById('swal_wa_telefono').value.trim()
            })
        });
        if (!form) return;

        if (!form.telefono) return error('Escriba el número de teléfono.');

        dialogo({ title: 'Enviando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        const data = await post(`${RUTA}/enviarWhatsappAjax`, {
            id: val('tll_id'),
            tipo: form.tipo,
            id_plantilla: form.id_plantilla,
            telefono: form.telefono
        });
        Swal.close();

        if (!data.ok) return error(data.error);
        toast(data.mensaje);
        await abrirOrden(val('tll_id'));
    };

    /** Sin WhatsApp Business configurado: se ofrece abrir el chat a mano. */
    async function whatsappSinConfigurar() {
        const o = ordenActual || {};
        const tel = String(o.telefono_contacto || o.cliente_telefono || '').replace(/\D/g, '');

        const c = await dialogo({
            icon: 'info',
            title: 'WhatsApp no configurado',
            html: 'Esta empresa no tiene conectada la cuenta de WhatsApp Business, '
                + 'así que no se puede adjuntar el PDF.<br>'
                + 'Configúrela en <strong>Configuración de WhatsApp</strong>.'
                + (tel ? '<br><br>¿Abrir el chat con el cliente para escribirle?' : ''),
            showCancelButton: !!tel,
            confirmButtonText: tel ? 'Abrir chat' : 'Entendido',
            cancelButtonText: 'Cerrar'
        });
        if (!tel || !c.isConfirmed) return;

        // Ecuador: los números locales de 10 dígitos que empiezan en 0 llevan +593.
        const destino = tel.length === 10 && tel.startsWith('0') ? '593' + tel.substring(1) : tel;
        const msg = `Hola, le escribimos por su vehículo ${o.placa || ''}. `
            + `Orden de trabajo ${o.numero_orden || ''}. Estado actual: ${ETIQUETA_ESTADO[o.estado] || o.estado || ''}.`;
        window.open(`https://wa.me/${destino}?text=${encodeURIComponent(msg)}`, '_blank');
    }

    window.tllGenerarDocumento = async function (tipo) {
        // El botón queda siempre activo: si algo falta, se explica al pulsarlo
        // en vez de dejar un botón gris que no dice por qué.
        const impedimento = motivoNoEmitir();
        if (impedimento) return avisarNoSePuedeEmitir(impedimento);

        const opcionesPago = FORMAS_PAGO.map((f) => `<option value="${esc(f.codigo)}">${esc(f.nombre)}</option>`).join('');
        const opcionesBodega = BODEGAS.map((b) =>
            `<option value="${b.id}" ${String(b.id) === val('tll_id_bodega') ? 'selected' : ''}>${esc(b.nombre)}</option>`).join('');

        const { value: form } = await dialogo({
            title: tipo === 'FACTURA' ? 'Generar factura' : 'Generar recibo',
            html: `
                <div class="text-start">
                    <label class="form-label small mb-1">Forma de pago</label>
                    <select id="swal_forma" class="form-select form-select-sm mb-2">${opcionesPago}</select>
                    <label class="form-label small mb-1">Bodega</label>
                    <select id="swal_bodega" class="form-select form-select-sm">${opcionesBodega}</select>
                    <div class="form-text">Solo se facturan las líneas aprobadas y facturables.</div>
                </div>`,
            showCancelButton: true, confirmButtonText: 'Generar', cancelButtonText: 'Cancelar',
            preConfirm: () => ({
                forma_pago: document.getElementById('swal_forma').value,
                id_bodega: document.getElementById('swal_bodega').value
            })
        });
        if (!form) return;

        dialogo({ title: 'Generando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        const data = await post(`${RUTA}/generarDocumentoAjax`, {
            id_orden: val('tll_id'), tipo: tipo,
            forma_pago: form.forma_pago, id_bodega: form.id_bodega
        });
        Swal.close();

        if (!data.ok) return error(data.error);
        dialogo({ icon: 'success', title: 'Listo', text: data.msg });
        await abrirOrden(val('tll_id'));
        if (typeof cargarGrid === 'function') cargarGrid();
    };

    // ─── Modales auxiliares (crear entidades al vuelo) ───────────────────────

    window.tllCrearVehiculo = function () {
        if (typeof abrirModalVehiculo === 'function') abrirModalVehiculo();
        else new bootstrap.Modal($('modalVehiculo')).show();
    };

    window.tllCrearCliente = function () {
        if (typeof abrirModalCliente === 'function') abrirModalCliente();
        else new bootstrap.Modal($('modalCliente')).show();
    };

    window.tllCrearProducto = function () {
        if (typeof abrirModalProducto === 'function') abrirModalProducto();
        else new bootstrap.Modal($('modalProducto')).show();
    };

    // ─── Alta rápida de departamento ─────────────────────────────────────────

    window.tllCrearDepartamento = function () {
        setVal('tll_dep_nuevo_nombre', '');
        setVal('tll_dep_nuevo_codigo', '');
        setVal('tll_dep_nuevo_orden', siguienteOrdenDepartamento());
        setVal('tll_dep_nuevo_color', '#0d6efd');
        setVal('tll_dep_nuevo_icono', 'bi-tools');
        if ($('tll_dep_nuevo_diagnostico')) $('tll_dep_nuevo_diagnostico').checked = false;

        new bootstrap.Modal($('modalTallerDepRapido')).show();
    };

    /** Deja hueco entre departamentos para poder intercalar después. */
    function siguienteOrdenDepartamento() {
        if (!DEPARTAMENTOS.length) return 10;
        return Math.max(...DEPARTAMENTOS.map((d) => Number(d.orden) || 0)) + 10;
    }

    window.tllGuardarDepartamentoRapido = async function () {
        const nombre = val('tll_dep_nuevo_nombre');
        if (!nombre) return error('Escriba el nombre del departamento.');

        try {
            const res = await fetch(`${RUTA_DEPARTAMENTOS}/store`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nombre: nombre,
                    codigo: val('tll_dep_nuevo_codigo'),
                    color: val('tll_dep_nuevo_color'),
                    icono: val('tll_dep_nuevo_icono'),
                    orden: val('tll_dep_nuevo_orden') || 0,
                    es_diagnostico: $('tll_dep_nuevo_diagnostico') ? $('tll_dep_nuevo_diagnostico').checked : false,
                    es_control_calidad: false,
                    activo: true
                })
            });
            const data = await res.json();
            if (!data.ok) return error(data.error);

            bootstrap.Modal.getInstance($('modalTallerDepRapido')).hide();
            await recargarDepartamentos(data.id);
            toast('Departamento creado.');
        } catch (e) {
            console.error(e);
            error('No se pudo crear el departamento.');
        }
    };

    /** Refresca los selectores del modal y deja seleccionado el recién creado. */
    async function recargarDepartamentos(idSeleccionar) {
        try {
            const res = await fetch(`${RUTA}/departamentosAjax`);
            const data = await res.json();
            if (!data.ok) return;

            DEPARTAMENTOS = (data.data || []).map((d) => ({
                id: Number(d.id), nombre: d.nombre, color: d.color, icono: d.icono, orden: d.orden
            }));

            // Selectores que ofrecen departamentos: línea del presupuesto y destino del vehículo.
            [
                { id: 'tll_l_departamento', vacio: '—' },
                { id: 'tll_dep_destino', vacio: 'Seleccione departamento...' }
            ].forEach(({ id, vacio }) => {
                const sel = $(id);
                if (!sel) return;
                const previo = sel.value;
                sel.innerHTML = `<option value="">${esc(vacio)}</option>`
                    + DEPARTAMENTOS.map((d) => `<option value="${d.id}">${esc(d.nombre)}</option>`).join('');
                // Se respeta lo que el usuario ya tenía elegido; el recién creado
                // solo se selecciona donde no había nada.
                sel.value = previo || (idSeleccionar ? String(idSeleccionar) : '');
            });
        } catch (e) {
            console.error(e);
        }
    }

    // ─── Alta rápida de un ítem del checklist de recepción ───────────────────

    window.tllCrearAccesorio = function () {
        setVal('tll_acc_nuevo_grupo', 'accesorios');
        setVal('tll_acc_nuevo_item', '');
        new bootstrap.Modal($('modalTallerAccesorioRapido')).show();
    };

    window.tllGuardarAccesorioRapido = async function () {
        const item = val('tll_acc_nuevo_item');
        const grupo = val('tll_acc_nuevo_grupo') || 'accesorios';
        if (!item) return error('Escriba el ítem que se va a revisar.');

        try {
            const data = await post(`${RUTA_CHECKLIST}/store`, {
                grupo: grupo,
                item: item,
                orden: (checklist.length + 1) * 10
            });
            if (!data.ok) return error(data.error);

            // Queda en la plantilla y también en el checklist de esta orden.
            CHECKLIST_BASE.push({ grupo: grupo, item: item, orden: (checklist.length + 1) * 10 });
            checklist.push({ grupo: grupo, item: item, valor: 'no', observacion: '', orden: (checklist.length + 1) * 10 });
            pintarChecklist();

            bootstrap.Modal.getInstance($('modalTallerAccesorioRapido')).hide();
            toast('Agregado al checklist.');
        } catch (e) {
            console.error(e);
            error('No se pudo agregar el ítem.');
        }
    };

    window.tllToggleSiniestro = function () {
        $('tll_bloque_siniestro').classList.toggle('d-none', !$('tll_es_siniestro').checked);
    };

    // ─── Pintado general y estado de los botones ─────────────────────────────

    function pintarTodo() {
        pintarChecklist();
        pintarFotos();
        pintarLineas();
        pintarTotales();
        pintarAprobacion();
        pintarEtapas();
        pintarBitacora();
        pintarInfoAdicional();
        actualizarBotones();
    }

    function actualizarBotones() {
        const o = ordenActual || {};
        const guardada = !!o.id;
        const cerrada = estaCerrada();
        const aprobada = o.aprobado === true || o.aprobado === 't' || o.aprobado === 'true';
        const tieneDoc = !!o.id_documento;

        // Los botones de Factura y Recibo no existen en el DOM si el usuario no
        // tiene permiso sobre esos módulos, así que se habilitan solo si están.
        const habilitar = (id, condicion) => {
            const btn = $(id);
            if (btn) btn.disabled = !condicion;
        };

        habilitar('tll_btn_pdf', guardada);
        habilitar('tll_btn_informe', guardada);
        habilitar('tll_btn_precuenta', guardada);
        habilitar('tll_btn_correo', guardada);
        habilitar('tll_btn_whatsapp', guardada);

        // Factura y Recibo quedan siempre activos: al pulsarlos se explica qué
        // falta. Un botón gris no comunica el motivo y deja al usuario probando.
        habilitar('tll_btn_factura', true);
        habilitar('tll_btn_recibo', true);
        explicarEmision(motivoNoEmitir());

        habilitar('tll_btn_agregar_linea', guardada && !cerrada && PERM.crear);
        habilitar('tll_btn_enviar_dep', guardada && !cerrada && PERM.actualizar);
        habilitar('tll_btn_nota', guardada && PERM.actualizar);
        habilitar('tll_btn_entregar', guardada && !cerrada && aprobada && PERM.actualizar);
        habilitar('tll_btn_guardar', !cerrada && (guardada ? PERM.actualizar : PERM.crear));

        const btnEliminar = $('tll_btn_eliminar');
        if (btnEliminar) {
            btnEliminar.classList.toggle('d-none', !guardada || tieneDoc || !PERM.eliminar);
        }
    }

    /**
     * Qué impide emitir el documento, o null si ya se puede.
     *
     * Son las mismas condiciones que valida el backend, en el mismo orden, para
     * que el mensaje en pantalla coincida con el error que daría al intentarlo.
     * Cada motivo indica además a qué pestaña hay que ir a resolverlo.
     *
     * @returns {{texto: string, ayuda?: string, pestana?: string}|null}
     */
    function motivoNoEmitir() {
        const o = ordenActual || {};

        if (!o.id) {
            return {
                texto: 'La orden todavía no está guardada.',
                ayuda: 'Complete los datos de recepción y pulse Guardar.'
            };
        }
        if (o.id_documento) {
            return {
                texto: 'Esta orden ya generó el documento ' + (o.numero_documento || '') + '.',
                ayuda: 'Una orden solo puede facturarse una vez.'
            };
        }
        if (o.estado === 'anulada') {
            return { texto: 'La orden está anulada.', ayuda: 'Una orden anulada no se puede facturar.' };
        }
        if (!o.id_cliente) {
            return {
                texto: 'Falta asignar el cliente a la orden.',
                ayuda: 'Búsquelo en el campo <strong>Cliente</strong> de la cabecera. Si no existe, créelo con el botón de la barra.'
            };
        }

        const aprobada = o.aprobado === true || o.aprobado === 't' || o.aprobado === 'true';
        if (!aprobada) {
            return {
                texto: 'El cliente todavía no aprueba el presupuesto.',
                ayuda: 'En la pestaña <strong>Presupuesto</strong>, registre quién aprobó y por qué medio.',
                pestana: 'tll-pane-presupuesto'
            };
        }

        const cobrables = (o.detalles || []).filter((d) =>
            (d.facturable === true || d.facturable === 't') &&
            ['aprobada', 'ejecutada'].includes(d.estado_linea) &&
            num(d.cantidad) > 0
        );
        if (!cobrables.length) {
            return {
                texto: 'No hay repuestos ni trabajos aprobados que cobrar.',
                ayuda: 'Agregue líneas en la pestaña <strong>Presupuesto</strong> y apruébelas. '
                     + 'Lo rechazado y lo que trajo el cliente no se factura.',
                pestana: 'tll-pane-presupuesto'
            };
        }

        return null;
    }

    /** Explica qué falta y, si aplica, lleva a la pestaña donde se resuelve. */
    async function avisarNoSePuedeEmitir(motivo) {
        const irA = motivo.pestana;

        const r = await dialogo({
            icon: 'warning',
            title: 'Todavía no se puede facturar',
            html: `<p class="mb-2">${esc(motivo.texto)}</p>`
                + (motivo.ayuda ? `<p class="small text-muted mb-0">${motivo.ayuda}</p>` : ''),
            showCancelButton: !!irA,
            confirmButtonText: irA ? 'Ir al presupuesto' : 'Entendido',
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#f39c12'
        });

        if (irA && r.isConfirmed) {
            const tab = document.querySelector(`[data-bs-target="#${irA}"]`);
            if (tab) new bootstrap.Tab(tab).show();
        }
    }

    /** Deja el motivo a la vista: en el tooltip del botón y junto a la barra. */
    function explicarEmision(motivo) {
        ['tll_btn_factura', 'tll_btn_recibo'].forEach((id) => {
            const btn = $(id);
            if (!btn) return;
            const accion = id === 'tll_btn_factura' ? 'Generar factura electrónica' : 'Generar recibo de venta';
            btn.title = motivo ? motivo.texto : accion;
            // Se atenúa para que se note que falta algo, pero sigue pulsable.
            btn.classList.toggle('opacity-50', !!motivo);
        });

        const info = $('tll_doc_info');
        if (!info) return;

        const o = ordenActual || {};
        if (o.numero_documento) {
            info.className = 'ms-auto small text-success';
            info.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>${esc(o.tipo_documento || 'Documento')} ${esc(o.numero_documento)}`;
            return;
        }
        if (!o.id) { info.textContent = ''; return; }

        if (motivo) {
            info.className = 'ms-auto small text-warning';
            info.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>Para facturar: ${esc(motivo.texto)}`;
        } else {
            info.className = 'ms-auto small text-success';
            info.innerHTML = '<i class="bi bi-check-circle me-1"></i>Lista para facturar';
        }
    }
})();
