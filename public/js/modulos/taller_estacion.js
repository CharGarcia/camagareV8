/**
 * Pantalla de estación del taller — la tablet de cada departamento.
 *
 * Muestra los vehículos que están ahora en el departamento y permite al operario
 * tomar el trabajo, registrar lo que hizo, agregar los repuestos que consumió y
 * enviar el vehículo al siguiente departamento.
 *
 * Se refresca por polling porque la infraestructura del servidor no admite
 * WebSockets. El refresco se pausa mientras hay un modal abierto para no
 * pisarle la pantalla al operario mientras escribe.
 */
(function () {
    'use strict';

    const RUTA = window.TW_RUTA;
    const ID_DEP = window.TW_ID_DEPARTAMENTO;
    const ES_DIAGNOSTICO = window.TW_ES_DIAGNOSTICO === true;
    const INTERVALO_MS = 20000;

    let ordenes = window.TW_ORDENES_INICIALES || [];
    let ordenAbierta = null;
    let modal = null;
    let timer = null;
    let debounceProd = null;

    const $ = (id) => document.getElementById(id);
    const val = (id) => ($(id) ? String($(id).value).trim() : '');
    const setVal = (id, v) => { if ($(id)) $(id).value = (v === null || v === undefined) ? '' : v; };
    const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const fmt = (n) => num(n).toFixed(2);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function toast(msg, tipo) {
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 2500,
            icon: tipo || 'success', title: msg
        });
    }

    const error = (msg) => Swal.fire('Atención', msg || 'Ocurrió un error.', 'error');

    async function post(url, body) {
        const fd = new FormData();
        Object.entries(body || {}).forEach(([k, v]) => fd.append(k, v === null || v === undefined ? '' : v));
        const res = await fetch(url, { method: 'POST', body: fd });
        return res.json();
    }

    /** Cuánto lleva el vehículo esperando o trabajándose en este departamento. */
    function transcurrido(desde) {
        if (!desde) return '';
        const d = new Date(String(desde).replace(' ', 'T'));
        if (isNaN(d)) return '';

        const min = Math.round((Date.now() - d.getTime()) / 60000);
        if (min < 1) return 'recién';
        if (min < 60) return `${min} min`;
        const h = Math.floor(min / 60);
        if (h < 24) return `${h}h ${min % 60}min`;
        return `${Math.floor(h / 24)} d ${h % 24}h`;
    }

    // ─── Render del tablero de la estación ───────────────────────────────────

    function pintar() {
        const grid = $('tw-grid');
        if (!grid) return;

        if (!ordenes.length) {
            grid.innerHTML = `<div class="tw-empty">
                    <i class="bi bi-check2-circle fs-1 d-block mb-3"></i>
                    No hay vehículos en ${esc(window.TW_DEP_NOMBRE || 'este departamento')}.
                </div>`;
            return;
        }

        grid.innerHTML = ordenes.map((o) => {
            const aprobada = o.aprobado === true || o.aprobado === 't' || o.aprobado === 'true';
            // Diagnóstico trabaja antes de la aprobación; el resto necesita el visto bueno.
            const puedeTrabajar = aprobada || ES_DIAGNOSTICO;
            const enProceso = o.etapa_estado === 'en_proceso';
            const clases = ['tw-card'];
            if (o.prioridad === 'urgente' || o.prioridad === 'alta') clases.push('urgente');
            if (!puedeTrabajar) clases.push('sin-aprobar');

            const chipAprob = aprobada
                ? '<span class="tw-chip" style="background:#198754;color:#fff">APROBADA</span>'
                : '<span class="tw-chip" style="background:#ffc107;color:#000">SIN APROBAR</span>';
            const chipPrio = (o.prioridad === 'urgente' || o.prioridad === 'alta')
                ? `<span class="tw-chip" style="background:#dc3545;color:#fff">${esc(String(o.prioridad).toUpperCase())}</span>` : '';

            const vehiculo = [o.marca, o.modelo, o.anio].filter(Boolean).join(' ');
            const referencia = enProceso ? o.etapa_inicio : o.fecha_ingreso;
            const etiquetaTiempo = enProceso ? 'trabajando hace' : 'esperando hace';

            return `
                <div class="${clases.join(' ')}">
                    <div class="tw-card-header d-flex justify-content-between align-items-start">
                        <div>
                            <div class="tw-placa">${esc(o.placa || '—')}</div>
                            <div class="tw-veh">${esc(vehiculo)}${o.color ? ' · ' + esc(o.color) : ''}</div>
                            <div class="tw-tiempo mt-1">
                                <i class="bi bi-stopwatch"></i> ${etiquetaTiempo} ${esc(transcurrido(referencia))}
                            </div>
                        </div>
                        <div class="text-end d-flex flex-column gap-1">
                            ${chipPrio}
                            ${chipAprob}
                            <span class="tw-tiempo">${esc(o.numero_orden || '')}</span>
                        </div>
                    </div>

                    <div class="tw-motivo">
                        <i class="bi bi-chat-quote text-secondary"></i>
                        ${esc(o.motivo_ingreso || 'Sin motivo registrado.')}
                    </div>

                    ${o.trabajo_realizado ? `<div class="tw-body small text-secondary">
                        <i class="bi bi-journal-text"></i> ${esc(o.trabajo_realizado)}
                    </div>` : ''}

                    <div class="tw-acciones">
                        ${enProceso
                            ? `<button class="tw-btn tw-btn-primary full" onclick="twAbrir(${o.id}, ${o.id_etapa})">
                                   <i class="bi bi-pencil-square"></i> Registrar trabajo
                               </button>`
                            : `<button class="tw-btn tw-btn-warning" onclick="twIniciar(${o.id_etapa})" ${puedeTrabajar ? '' : 'disabled'}>
                                   <i class="bi bi-play-fill"></i> Tomar
                               </button>
                               <button class="tw-btn" onclick="twAbrir(${o.id}, ${o.id_etapa})">
                                   <i class="bi bi-eye"></i> Ver
                               </button>`}
                        ${!puedeTrabajar ? `<div class="full small text-warning text-center">
                            <i class="bi bi-exclamation-triangle"></i> Falta que el cliente apruebe el presupuesto
                        </div>` : ''}
                    </div>
                </div>`;
        }).join('');
    }

    // ─── Polling ─────────────────────────────────────────────────────────────

    async function refrescar() {
        // Mientras el operario tiene el modal abierto no se toca la pantalla.
        if (document.querySelector('.modal.show')) return;

        try {
            const res = await fetch(`${RUTA}/estacionAjax?id_departamento=${ID_DEP}`);
            const data = await res.json();
            if (data.ok) {
                ordenes = data.data || [];
                pintar();
            }
        } catch (e) {
            console.error(e);
        }
    }

    window.twRefrescar = refrescar;

    // ─── Tomar el trabajo ────────────────────────────────────────────────────

    window.twIniciar = async function (idEtapa) {
        const data = await post(`${RUTA}/iniciarEtapaAjax`, { id_etapa: idEtapa });
        if (!data.ok) return error(data.error);

        toast('Trabajo iniciado.');
        await refrescar();
    };

    // ─── Modal de trabajo ────────────────────────────────────────────────────

    window.twAbrir = async function (idOrden, idEtapa) {
        try {
            const res = await fetch(`${RUTA}/estacionOrdenAjax?id=${idOrden}&id_departamento=${ID_DEP}`);
            const data = await res.json();
            if (!data.ok) return error(data.error);

            ordenAbierta = data.data;
            setVal('tw_id_orden', idOrden);
            setVal('tw_id_etapa', idEtapa || (ordenAbierta.etapa ? ordenAbierta.etapa.id : ''));

            $('twModalTitulo').innerHTML =
                `<span class="fw-bold">${esc(ordenAbierta.placa || '')}</span>
                 <span class="text-secondary small ms-2">${esc(ordenAbierta.numero_orden || '')}</span>`;

            const etapa = ordenAbierta.etapa || {};
            setVal('tw_trabajo', etapa.trabajo_realizado || '');
            setVal('tw_observaciones', etapa.observaciones || '');
            setVal('tw_empleado', etapa.id_empleado_responsable || '');

            pintarLineas();
            pintarInfo();

            modal = modal || new bootstrap.Modal($('twModal'));
            modal.show();
        } catch (e) {
            console.error(e);
            error('No se pudo abrir la orden.');
        }
    };

    function pintarInfo() {
        const o = ordenAbierta || {};
        const filas = [
            ['Vehículo', [o.marca, o.modelo, o.anio].filter(Boolean).join(' ')],
            ['Color', o.color],
            ['Kilometraje', o.kilometraje],
            ['Cliente', o.cliente_nombre],
            ['Motivo de ingreso', o.motivo_ingreso],
            ['Diagnóstico', o.diagnostico_texto]
        ].filter(([, v]) => v !== null && v !== undefined && String(v).trim() !== '');

        $('tw_info_vehiculo').innerHTML = filas.map(([k, v]) => `
            <div class="d-flex justify-content-between border-bottom border-secondary py-1">
                <span class="text-secondary small">${esc(k)}</span>
                <span class="text-end ms-3">${esc(v)}</span>
            </div>`).join('');
    }

    function pintarLineas() {
        const cont = $('tw_lineas');
        const lineas = (ordenAbierta && ordenAbierta.detalles) || [];
        $('tw_badge_lineas').textContent = lineas.length;

        if (!lineas.length) {
            cont.innerHTML = '<div class="text-secondary small text-center py-3">Este departamento todavía no registró consumos.</div>';
            return;
        }

        const estados = {
            sugerida: ['#ffc107', '#000', 'Sugerida'], aprobada: ['#0d6efd', '#fff', 'Aprobada'],
            rechazada: ['#dc3545', '#fff', 'Rechazada'], ejecutada: ['#198754', '#fff', 'Ejecutada']
        };

        cont.innerHTML = lineas.map((l) => {
            const [bg, fg, txt] = estados[l.estado_linea] || ['#6c757d', '#fff', l.estado_linea];
            return `<div class="tw-linea d-flex justify-content-between align-items-center">
                    <div>
                        <div>${esc(l.descripcion)}</div>
                        <div class="text-secondary" style="font-size:.76rem">
                            Cant: ${fmt(l.cantidad)} · $ ${fmt(l.total_linea)}
                            <span class="tw-chip ms-1" style="background:${bg};color:${fg}">${esc(txt)}</span>
                        </div>
                    </div>
                    ${l.estado_linea === 'sugerida'
                        ? `<button class="btn btn-sm btn-outline-danger" onclick="twQuitarLinea(${l.id})"><i class="bi bi-trash3"></i></button>`
                        : ''}
                </div>`;
        }).join('');
    }

    // ─── Agregar repuestos y trabajos ────────────────────────────────────────

    window.twTipoChange = function () {
        const horas = $('tw_l_horas');
        if (horas) horas.disabled = (val('tw_l_tipo') !== 'mano_obra');
    };

    window.twBuscarProductos = function (q) {
        clearTimeout(debounceProd);
        const box = $('tw_prod_dropdown');
        if (!q || q.length < 2) { box.classList.add('d-none'); return; }

        debounceProd = setTimeout(async () => {
            const params = new URLSearchParams({
                q: q,
                id_bodega: val('tw_l_bodega') || (ordenAbierta ? (ordenAbierta.id_bodega || 0) : 0),
                id_orden: val('tw_id_orden') || 0
            });
            const res = await fetch(`${RUTA}/getProductosAjax?${params}`);
            const data = await res.json();
            if (!data.ok || !data.data.length) { box.classList.add('d-none'); return; }

            box.innerHTML = data.data.map((p) => {
                const precio = (p.precios_lista && p.precios_lista.length) ? p.precios_lista[0].precio : (p.precio_venta || 0);
                // Servicio = no inventariable; el resto son repuestos e insumos.
                const esServicio = !(p.inventariable === true || p.inventariable === 't'
                    || p.inventariable === '1' || p.inventariable === 1);
                const detalle = esServicio
                    ? ' · Servicio'
                    : (p.controla_stock ? ` · Stock: ${fmt(p.stock_actual)}` : '');

                return `<button type="button" class="list-group-item list-group-item-action py-2"
                            data-prod='${esc(JSON.stringify({ id: p.id, nombre: p.nombre, precio: precio, servicio: esServicio }))}'>
                            <strong>${esc(p.nombre)}</strong><br>
                            <span class="text-muted small">$ ${fmt(precio)}${esc(detalle)}</span>
                        </button>`;
            }).join('');

            box.querySelectorAll('[data-prod]').forEach((b) => {
                b.addEventListener('click', () => {
                    const p = JSON.parse(b.getAttribute('data-prod'));
                    setVal('tw_l_id_producto', p.id);
                    setVal('tw_l_descripcion', p.nombre);
                    setVal('tw_l_precio', fmt(p.precio));

                    // El tipo se corrige solo cuando contradice al catálogo.
                    const tipo = val('tw_l_tipo');
                    if (p.servicio && tipo === 'repuesto') setVal('tw_l_tipo', 'mano_obra');
                    if (!p.servicio && tipo === 'mano_obra') setVal('tw_l_tipo', 'repuesto');
                    twTipoChange();

                    box.classList.add('d-none');
                });
            });
            box.classList.remove('d-none');
        }, 350);
    };

    window.twAgregarLinea = async function () {
        if (!val('tw_l_descripcion')) return error('Escriba qué repuesto o trabajo agrega.');

        const payload = {
            id_orden: val('tw_id_orden'),
            tipo_linea: val('tw_l_tipo'),
            id_producto: val('tw_l_id_producto') || null,
            descripcion: val('tw_l_descripcion'),
            cantidad: val('tw_l_cantidad') || 1,
            horas: val('tw_l_horas') || 0,
            precio_unitario: val('tw_l_precio') || 0,
            id_departamento: ID_DEP,
            id_empleado_tecnico: val('tw_empleado') || null,
            id_bodega: val('tw_l_bodega') || null,
            provisto_cliente: $('tw_l_provisto') ? $('tw_l_provisto').checked : false
        };

        const res = await fetch(`${RUTA}/agregarLineaAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.ok) return error(data.error);

        toast('Agregado a la orden.');
        setVal('tw_l_descripcion', '');
        setVal('tw_l_id_producto', '');
        setVal('tw_l_cantidad', '1');
        setVal('tw_l_horas', '0');
        setVal('tw_l_precio', '0');
        if ($('tw_l_provisto')) $('tw_l_provisto').checked = false;

        await recargarOrden();
    };

    window.twQuitarLinea = async function (id) {
        const c = await Swal.fire({
            title: '¿Quitar esta línea?', icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;

        const data = await post(`${RUTA}/eliminarLineaAjax`, { id: id });
        if (!data.ok) return error(data.error);

        toast('Línea quitada.');
        await recargarOrden();
    };

    async function recargarOrden() {
        const res = await fetch(`${RUTA}/estacionOrdenAjax?id=${val('tw_id_orden')}&id_departamento=${ID_DEP}`);
        const data = await res.json();
        if (data.ok) {
            ordenAbierta = data.data;
            pintarLineas();
        }
    }

    // ─── Avance, notas y fotos ───────────────────────────────────────────────

    window.twGuardarAvance = async function () {
        const data = await post(`${RUTA}/avanceEtapaAjax`, {
            id_etapa: val('tw_id_etapa'),
            trabajo_realizado: val('tw_trabajo'),
            observaciones: val('tw_observaciones'),
            id_empleado_responsable: val('tw_empleado')
        });
        if (!data.ok) return error(data.error);
        toast('Avance guardado.');
    };

    window.twAgregarNota = async function () {
        if (!val('tw_nota')) return error('Escriba la nota.');

        const data = await post(`${RUTA}/agregarNotaAjax`, {
            id_orden: val('tw_id_orden'),
            concepto: val('tw_nota'),
            id_departamento: ID_DEP
        });
        if (!data.ok) return error(data.error);

        setVal('tw_nota', '');
        toast('Nota agregada.');
    };

    window.twSubirFoto = async function () {
        const input = $('tw_foto');
        if (!input.files || !input.files[0]) return error('Elija o tome una foto.');

        const fd = new FormData();
        fd.append('id_orden', val('tw_id_orden'));
        fd.append('id_departamento', ID_DEP);
        fd.append('momento', 'proceso');
        fd.append('foto', input.files[0]);

        try {
            const res = await fetch(`${RUTA}/subirFotoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            input.value = '';
            if (!data.ok) return error(data.error);
            toast('Foto subida.');
        } catch (e) {
            console.error(e);
            error('No se pudo subir la foto.');
        }
    };

    // ─── Cerrar el trabajo del departamento ──────────────────────────────────

    window.twTerminar = async function () {
        if (!val('tw_trabajo')) {
            return error('Describa el trabajo realizado antes de cerrar. Es lo que ve el cliente en el informe técnico.');
        }

        const destino = val('tw_dep_siguiente');
        const texto = destino
            ? 'El vehículo pasará al siguiente departamento.'
            : 'El vehículo quedará listo para la entrega.';

        const c = await Swal.fire({
            title: '¿Terminar el trabajo?', text: texto, icon: 'question',
            showCancelButton: true, confirmButtonText: 'Terminar', cancelButtonText: 'Cancelar'
        });
        if (!c.isConfirmed) return;

        const data = await post(`${RUTA}/terminarEtapaAjax`, {
            id_etapa: val('tw_id_etapa'),
            trabajo_realizado: val('tw_trabajo'),
            observaciones: val('tw_observaciones'),
            id_empleado_responsable: val('tw_empleado'),
            id_departamento_siguiente: destino || 0
        });
        if (!data.ok) return error(data.error);

        toast('Trabajo cerrado.');
        if (modal) modal.hide();
        await refrescar();
    };

    // ─── Arranque ────────────────────────────────────────────────────────────

    function reloj() {
        const el = $('tw-reloj');
        if (!el) return;
        const d = new Date();
        const p = (x) => String(x).padStart(2, '0');
        el.textContent = `${p(d.getDate())}-${p(d.getMonth() + 1)}-${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        pintar();
        reloj();
        setInterval(reloj, 1000);

        // El tiempo transcurrido se recalcula solo, sin pedir datos al servidor.
        setInterval(() => { if (!document.querySelector('.modal.show')) pintar(); }, 60000);

        timer = setInterval(refrescar, INTERVALO_MS);

        // Al volver a la pestaña, refrescar de inmediato.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refrescar();
        });

        // Cerrar el dropdown de productos al tocar fuera.
        document.addEventListener('click', (ev) => {
            const box = $('tw_prod_dropdown');
            const input = $('tw_l_descripcion');
            if (box && !box.contains(ev.target) && ev.target !== input) box.classList.add('d-none');
        });
    });
})();
