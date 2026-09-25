(function () {
    'use strict';

    const state = {
        idCargaActual: null,
        lineas: {},       // id_linea -> linea (con selección actual de cliente/documento)
    };

    function fmtMoney(v) {
        return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(v) {
        if (!v) return '—';
        const d = new Date(String(v).substring(0, 10) + 'T00:00:00');
        if (isNaN(d.getTime())) return v;
        return d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function alertError(titulo, mensaje) {
        if (window.Swal) {
            Swal.fire({ icon: 'error', title: titulo, text: mensaje || 'Error desconocido.' });
        } else {
            alert(titulo + ': ' + (mensaje || 'Error desconocido.'));
        }
    }

    function alertOk(titulo, mensaje) {
        if (window.Swal) {
            Swal.fire({ icon: 'success', title: titulo, text: mensaje || '', timer: 1800, showConfirmButton: false });
        } else {
            alert(titulo);
        }
    }

    /**
     * Lee la respuesta como JSON sin fallar en silencio: si el servidor devuelve HTML (sesión
     * vencida, error de PHP, tiempo agotado) o la red falla, se devuelve {ok:false, error} para
     * que quien llama muestre el aviso, en vez de lanzar una excepción que nadie ve.
     */
    async function leerJson(promesa) {
        try {
            const resp = await promesa;
            const texto = await resp.text();
            try {
                return JSON.parse(texto);
            } catch (e) {
                return { ok: false, error: `El servidor no respondió correctamente (HTTP ${resp.status}). Recargue la página; si persiste, puede que su sesión haya vencido.` };
            }
        } catch (e) {
            return { ok: false, error: 'No se pudo conectar con el servidor.' };
        }
    }

    function getJson(url) {
        return leerJson(fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }));
    }

    function postJson(url, payload) {
        return leerJson(fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload || {}),
        }));
    }

    function postForm(url, formData) {
        return leerJson(fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        }));
    }

    const CC = window.CC = {};

    // ── Inicialización ───────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        CC.cargarPerfiles();
        document.getElementById('cc-forma')?.addEventListener('change', CC.cargarPerfiles);

        const formCarga = document.getElementById('cc-form-carga');
        if (formCarga) {
            formCarga.addEventListener('submit', CC.onSubmitCarga);
        }

        // Historial de cargas: la fila completa abre el detalle (delegado, sirve también para
        // las filas que se repintan con refrescarCargas()).
        document.querySelector('#cc-tabla-cargas tbody')?.addEventListener('click', (ev) => {
            const fila = ev.target.closest('tr[data-id-carga]');
            if (fila) CC.abrirCarga(parseInt(fila.dataset.idCarga, 10));
        });

        // Modal de búsqueda: marcar/desmarcar documentos y editar montos (delegado).
        document.getElementById('cc-buscar-docs-tbody')?.addEventListener('change', (ev) => {
            if (ev.target.matches('input[data-doc-key]')) CC.toggleDocumento(ev.target.dataset.docKey, ev.target.checked);
        });
        // Clic en cualquier parte de la fila = marcar/desmarcar su casilla (el clic sobre la
        // propia casilla ya lo resuelve el navegador y dispara el 'change' de arriba).
        document.getElementById('cc-buscar-docs-tbody')?.addEventListener('click', (ev) => {
            if (ev.target.matches('input[data-doc-key]')) return;
            const chk = ev.target.closest('tr[data-fila-key]')?.querySelector('input[data-doc-key]');
            if (!chk) return;
            chk.checked = !chk.checked;
            chk.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.getElementById('cc-buscar-sel-tbody')?.addEventListener('change', (ev) => {
            if (ev.target.matches('input[data-monto-key]')) CC.cambiarMontoSeleccion(ev.target.dataset.montoKey, ev.target);
        });
        document.getElementById('cc-buscar-sel-tbody')?.addEventListener('click', (ev) => {
            const btn = ev.target.closest('button[data-quitar-key]');
            if (btn) CC.toggleDocumento(btn.dataset.quitarKey, false);
        });
    });

    // ── Formato del banco (perfiles de mapeo) ────────────────────────────────
    // Los perfiles son un catálogo global que administra el nivel 3 en
    // config/conciliacion-perfiles; aquí solo se elige uno. Al escoger la cuenta
    // bancaria se muestran los formatos de ese banco y los genéricos (sin banco);
    // si el banco no tiene ninguno, se muestran todos para no bloquear la carga.

    function escHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    CC.cargarPerfiles = function () {
        const select = document.getElementById('cc-perfil');
        if (!select) return;

        const perfiles = window.CC_PERFILES || [];
        if (!perfiles.length) return; // sin formatos activos: queda solo "— Seleccione —"

        const optCuenta = document.getElementById('cc-forma')?.selectedOptions[0];
        const idBanco = optCuenta ? parseInt(optCuenta.dataset.banco || '0', 10) : 0;

        let lista = perfiles;
        if (idBanco) {
            const delBanco = perfiles.filter((p) => p.id_banco === idBanco || p.id_banco === null);
            if (delBanco.length) lista = delBanco;
        }

        const anterior = select.value;
        select.innerHTML = '<option value="">— Seleccione —</option>' + lista.map((p) => {
            const banco = p.nombre_banco ? `${p.nombre_banco} — ` : '';
            return `<option value="${p.id}">${escHtml(banco + p.nombre_perfil)} (${escHtml(p.tipo_archivo)})</option>`;
        }).join('');

        if (lista.some((p) => String(p.id) === anterior)) {
            select.value = anterior;
        } else if (idBanco && lista.length === 1) {
            select.value = String(lista[0].id);
        }
    };

    // ── Carga del extracto ───────────────────────────────────────────────────

    CC.onSubmitCarga = async function (ev) {
        ev.preventDefault();

        const idForma = document.getElementById('cc-forma').value;
        const idPunto = document.getElementById('cc-punto').value;
        const idPerfil = document.getElementById('cc-perfil').value;
        const archivo = document.getElementById('cc-archivo').files[0];

        if (!idForma || !idPunto || !idPerfil || !archivo) {
            alertError('Faltan datos', 'Selecciona cuenta, punto de emisión, formato del banco y archivo.');
            return;
        }

        const fd = new FormData();
        fd.append('id_forma_pago', idForma);
        fd.append('id_punto_emision', idPunto);
        fd.append('id_perfil', idPerfil);
        fd.append('archivo', archivo);

        const btn = ev.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando…';

        try {
            const json = await postForm(`${CC_URL_BASE}/subirArchivoAjax`, fd);
            if (!json.ok) {
                alertError('No se pudo procesar el archivo', json.error);
                return;
            }
            alertOk('Archivo procesado', `${json.data.total_lineas} líneas encontradas.`);
            state.idCargaActual = json.data.id;
            await CC.cargarLineas(json.data.id);
            await CC.refrescarCargas();
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-upload me-1"></i> Subir y Conciliar';
        }
    };

    CC.refrescarCargas = async function () {
        const json = await getJson(`${CC_URL_BASE}/listarCargasAjax`);
        if (!json.ok) return;
        const tbody = document.querySelector('#cc-tabla-cargas tbody');
        if (!tbody) return;

        if (!json.data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin cargas todavía.</td></tr>';
            return;
        }

        const badgeClase = { completado: 'success', error: 'danger' };
        tbody.innerHTML = json.data.map((c) => {
            const clase = badgeClase[c.estado] || 'warning';
            return `<tr class="cc-carga-fila" data-id-carga="${c.id}" title="Clic para ver sus líneas">
                <td>${fmtDateTime(c.created_at)}</td>
                <td>${escHtml(c.nombre_archivo)}</td>
                <td>${escHtml(c.forma_pago_nombre)}</td>
                <td>${escHtml(c.nombre_perfil)}</td>
                <td class="text-center"><span class="badge bg-${clase} bg-opacity-25 text-${clase === 'warning' ? 'warning-emphasis' : clase}">${escHtml(c.estado)}</span></td>
                <td class="text-end">${c.total_aplicadas} / ${c.total_lineas}</td>
            </tr>`;
        }).join('');
        marcarCargaActiva();
    };

    function fmtDateTime(v) {
        if (!v) return '—';
        const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
        return m ? `${m[3]}-${m[2]}-${m[1]} ${m[4]}:${m[5]}:${m[6]}` : fmtDate(v);
    }

    /** Resalta en el historial la carga cuyas líneas se están mostrando. */
    function marcarCargaActiva() {
        document.querySelectorAll('#cc-tabla-cargas tr[data-id-carga]').forEach((tr) => {
            tr.classList.toggle('cc-carga-activa', parseInt(tr.dataset.idCarga, 10) === state.idCargaActual);
        });
        const fila = document.querySelector(`#cc-tabla-cargas tr[data-id-carga="${state.idCargaActual}"]`);
        const etiqueta = document.getElementById('cc-carga-actual');
        if (etiqueta) {
            etiqueta.textContent = fila ? `— ${fila.cells[1].textContent.trim()} (${fila.cells[0].textContent.trim()})` : '';
        }
    }

    CC.abrirCarga = async function (idCarga) {
        state.idCargaActual = idCarga;
        marcarCargaActiva();
        const ok = await CC.cargarLineas(idCarga);
        if (ok) {
            document.getElementById('cc-card-lineas').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    // ── Líneas del extracto ──────────────────────────────────────────────────

    CC.cargarLineas = async function (idCarga) {
        const card = document.getElementById('cc-card-lineas');
        card.style.display = '';
        document.getElementById('cc-tbody-lineas').innerHTML =
            '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando líneas…</td></tr>';

        const json = await getJson(`${CC_URL_BASE}/listarLineasAjax?id_carga=${idCarga}`);

        if (!json.ok) {
            document.getElementById('cc-tbody-lineas').innerHTML =
                `<tr><td colspan="7" class="text-center py-4 text-danger">${escHtml(json.error || 'No se pudieron cargar las líneas.')}</td></tr>`;
            alertError('No se pudieron cargar las líneas', json.error);
            return false;
        }

        state.lineas = {};
        (json.data || []).forEach((l) => { state.lineas[l.id] = l; });

        CC.renderLineas();
        marcarCargaActiva();
        return true;
    };

    function badgeEstado(estado) {
        const map = {
            CONFIRMADO: 'success', APLICADO: 'success', IGNORADO: 'secondary',
            ERROR: 'danger', SUGERIDO: 'info', SIN_MATCH: 'warning',
        };
        return map[estado] || 'secondary';
    }

    CC.renderLineas = function () {
        const tbody = document.getElementById('cc-tbody-lineas');
        const lineas = Object.values(state.lineas);

        if (!lineas.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Esta carga no tiene líneas.</td></tr>';
        } else {
            tbody.innerHTML = lineas.map((l) => CC.renderFila(l)).join('');
        }

        const total = lineas.length;
        const confirmadas = lineas.filter((l) => l.estado === 'CONFIRMADO').length;
        const aplicadas = lineas.filter((l) => l.estado === 'APLICADO').length;
        const ignoradas = lineas.filter((l) => l.estado === 'IGNORADO').length;
        document.getElementById('cc-resumen-lineas').textContent =
            `${total} líneas — ${confirmadas} confirmadas, ${aplicadas} aplicadas, ${ignoradas} ignoradas`;
    };

    CC.renderFila = function (l) {
        // El monto a aplicar se bloquea al confirmar: si se quiere cambiar, primero hay que
        // quitar la confirmación (botón ↺) para evitar que quede desincronizado con lo guardado.
        const bloqueada = l.estado === 'APLICADO' || l.estado === 'IGNORADO' || l.estado === 'CONFIRMADO';
        const clasesPorEstado = {
            CONFIRMADO: 'cc-confirmado',
            APLICADO: 'cc-aplicada',
            IGNORADO: 'cc-ignorada',
            ERROR: 'cc-error',
        };
        const claseFila = clasesPorEstado[l.estado] || '';

        const clienteTxt = l.cliente_sugerido_nombre ? escHtml(l.cliente_sugerido_nombre) : '<span class="text-muted">— sin identificar —</span>';
        const docTxt = l.documento_numero
            ? `${escHtml(l.tipo_documento_sugerido)} ${escHtml(l.documento_numero)} <br><small class="text-muted">saldo: ${fmtMoney(l.documento_saldo_pendiente)}</small>`
            : '<span class="text-muted">— sin documento —</span>';

        // Tope real del monto a aplicar: no puede superar ni lo recibido en el banco ni el
        // saldo pendiente del documento elegido (si ya hay uno sugerido/seleccionado).
        const topeMonto = l.documento_saldo_pendiente != null
            ? Math.min(Number(l.monto), Number(l.documento_saldo_pendiente))
            : Number(l.monto);
        const montoAplicar = Math.min(l.monto_aplicar != null ? Number(l.monto_aplicar) : Number(l.monto), topeMonto);

        let acciones;
        if (l.estado === 'APLICADO' && l.ingreso_valido === false) {
            // El Ingreso que generó esta línea fue anulado/eliminado después: se puede reactivar
            // sin resubir el extracto (conserva cliente/documento/monto ya elegidos).
            acciones = `
                <div class="d-flex gap-1 justify-content-center align-items-center flex-nowrap">
                    <span class="badge bg-warning bg-opacity-25 text-warning-emphasis" title="El Ingreso generado fue anulado o eliminado">Ingreso anulado</span>
                    <button type="button" class="btn btn-outline-warning btn-sm" title="Reactivar para volver a generar el cobro" onclick="CC.reactivarLineaAplicada(${l.id})"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>`;
        } else if (l.estado === 'APLICADO') {
            acciones = `<span class="badge bg-${badgeEstado(l.estado)} bg-opacity-25 text-${badgeEstado(l.estado)}">${l.estado}</span>`;
        } else if (l.estado === 'IGNORADO') {
            acciones = `
                <div class="d-flex gap-1 justify-content-center align-items-center flex-nowrap">
                    <span class="badge bg-secondary bg-opacity-25 text-secondary">IGNORADO</span>
                    <button type="button" class="btn btn-outline-warning btn-sm" title="Reactivar (la ignoré por error)" onclick="CC.reactivarLinea(${l.id})"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>`;
        } else if (l.estado === 'ERROR') {
            acciones = `
                <div class="d-flex gap-1 justify-content-center align-items-center flex-nowrap">
                    <span class="badge bg-danger bg-opacity-25 text-danger" title="${(l.mensaje_error || '').replace(/"/g, '&quot;')}">ERROR</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" title="Buscar cliente/documento" onclick="CC.abrirBuscarDoc(${l.id})"><i class="bi bi-search"></i></button>
                </div>`;
        } else if (l.estado === 'CONFIRMADO') {
            acciones = `
                <div class="d-flex gap-1 justify-content-center align-items-center flex-nowrap">
                    <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Confirmado</span>
                    <button type="button" class="btn btn-outline-warning btn-sm" title="Quitar confirmación (la marqué por error)" onclick="CC.desconfirmarLinea(${l.id})"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" title="Cambiar cliente/documento" onclick="CC.abrirBuscarDoc(${l.id})"><i class="bi bi-search"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm" title="Ignorar" onclick="CC.ignorarLinea(${l.id})"><i class="bi bi-x-lg"></i></button>
                </div>`;
        } else {
            acciones = `
                <div class="d-flex gap-1 justify-content-center align-items-center flex-nowrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" title="Buscar cliente/documento" onclick="CC.abrirBuscarDoc(${l.id})"><i class="bi bi-search"></i></button>
                    <button type="button" class="btn btn-success btn-sm" title="Confirmar" onclick="CC.confirmarLinea(${l.id})"><i class="bi bi-check-lg"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm" title="Ignorar" onclick="CC.ignorarLinea(${l.id})"><i class="bi bi-x-lg"></i></button>
                </div>`;
        }

        return `<tr class="cc-linea-fila ${claseFila}" data-id-linea="${l.id}">
            <td class="ps-3" data-col="fecha">${fmtDate(l.fecha_movimiento)}</td>
            <td data-col="descripcion">${escHtml(l.descripcion_original)}</td>
            <td class="text-end" data-col="monto">${fmtMoney(l.monto)}</td>
            <td data-col="cliente">${clienteTxt}</td>
            <td data-col="documento">${docTxt}</td>
            <td class="text-end" data-col="monto_aplicar">
                <input type="number" step="0.01" min="0.01" max="${topeMonto}" class="form-control form-control-sm text-end"
                       style="max-width:120px; display:inline-block;" value="${montoAplicar.toFixed(2)}"
                       ${bloqueada ? 'disabled' : ''} onchange="CC.actualizarMontoAplicar(${l.id}, this)">
            </td>
            <td class="text-center pe-3" data-col="acciones">${acciones}</td>
        </tr>`;
    };

    CC.actualizarMontoAplicar = function (idLinea, input) {
        const l = state.lineas[idLinea];
        if (!l) return;

        const tope = l.documento_saldo_pendiente != null
            ? Math.min(Number(l.monto), Number(l.documento_saldo_pendiente))
            : Number(l.monto);
        let valor = parseFloat(input.value) || 0;

        if (valor > tope) {
            valor = tope;
            input.value = tope.toFixed(2);
            alertError('Monto ajustado', `El monto a aplicar no puede superar ${fmtMoney(tope)} (saldo pendiente del documento o monto recibido).`);
        }

        l.monto_aplicar = valor;
    };

    CC.confirmarLinea = async function (idLinea) {
        const l = state.lineas[idLinea];
        if (!l || !l.id_cliente_sugerido || !l.tipo_documento_sugerido || !l.id_documento_sugerido) {
            alertError('Falta información', 'Selecciona el cliente y el documento a cobrar antes de confirmar (botón de lupa).');
            return;
        }

        const json = await postJson(`${CC_URL_BASE}/confirmarLineaAjax`, {
            id_linea: idLinea,
            id_cliente: l.id_cliente_sugerido,
            tipo_documento: l.tipo_documento_sugerido,
            id_documento: l.id_documento_sugerido,
            monto_aplicar: l.monto_aplicar != null ? l.monto_aplicar : l.monto,
        });

        if (!json.ok) {
            alertError('No se pudo confirmar', json.error);
            return;
        }

        state.lineas[idLinea] = Object.assign({}, l, json.data);
        CC.renderLineas();
    };

    CC.desconfirmarLinea = async function (idLinea) {
        const json = await postJson(`${CC_URL_BASE}/desconfirmarLineaAjax`, { id_linea: idLinea });
        if (!json.ok) {
            alertError('No se pudo quitar la confirmación', json.error);
            return;
        }
        if (state.lineas[idLinea]) state.lineas[idLinea].estado = 'SUGERIDO';
        CC.renderLineas();
    };

    CC.reactivarLinea = async function (idLinea) {
        const json = await postJson(`${CC_URL_BASE}/reactivarLineaAjax`, { id_linea: idLinea });
        if (!json.ok) {
            alertError('No se pudo reactivar la línea', json.error);
            return;
        }
        if (state.lineas[idLinea]) state.lineas[idLinea].estado = 'SUGERIDO';
        CC.renderLineas();
    };

    CC.reactivarLineaAplicada = async function (idLinea) {
        const json = await postJson(`${CC_URL_BASE}/reactivarLineaAplicadaAjax`, { id_linea: idLinea });
        if (!json.ok) {
            alertError('No se pudo reactivar la línea', json.error);
            return;
        }
        state.lineas[idLinea] = Object.assign({}, state.lineas[idLinea], json.data, { ingreso_valido: undefined });
        CC.renderLineas();
    };

    CC.ignorarLinea = async function (idLinea) {
        const json = await postJson(`${CC_URL_BASE}/ignorarLineaAjax`, { id_linea: idLinea });
        if (!json.ok) {
            alertError('No se pudo ignorar la línea', json.error);
            return;
        }
        if (state.lineas[idLinea]) state.lineas[idLinea].estado = 'IGNORADO';
        CC.renderLineas();
    };

    CC.generarIngresos = async function () {
        if (!state.idCargaActual) return;
        const confirmadas = Object.values(state.lineas).filter((l) => l.estado === 'CONFIRMADO').length;
        if (!confirmadas) {
            alertError('Nada que generar', 'No hay líneas confirmadas en esta carga.');
            return;
        }

        const btn = document.getElementById('cc-btn-generar');
        btn.disabled = true;

        try {
            const json = await postJson(`${CC_URL_BASE}/generarIngresosAjax`, { id_carga: state.idCargaActual });
            if (!json.ok) {
                alertError('No se pudieron generar los cobros', json.error);
                return;
            }
            const ok = json.data.filter((r) => r.ok).length;
            const fallidos = json.data.filter((r) => !r.ok).length;
            const conDiferencia = json.data.filter((r) => r.ok && r.id_linea_diferencia).length;
            let mensaje = `${ok} ingreso(s) generado(s)${fallidos ? `, ${fallidos} con error` : ''}.`;
            if (conDiferencia) {
                mensaje += ` ${conDiferencia} quedó(aron) con un pago parcial: la diferencia se agregó como línea nueva para seguir conciliándola.`;
            }
            alertOk('Proceso terminado', mensaje);
            await CC.cargarLineas(state.idCargaActual);
            await CC.refrescarCargas();
        } finally {
            btn.disabled = false;
        }
    };

    // ── Búsqueda manual de cliente/documento(s) ─────────────────────────────
    // Se pueden marcar uno o varios documentos, incluso de clientes distintos (un solo
    // depósito que paga facturas de varios clientes). Lo marcado vive en `buscar.sel` y se
    // conserva al cambiar de cliente. Con un documento la línea se asigna como siempre; con
    // varios, el servidor la divide en una línea por documento (dividirLineaAjax).

    const buscar = {
        idLinea: null,
        montoLinea: 0,
        docs: {},          // clave -> documento del cliente consultado (con id_cliente/cliente_nombre)
        sel: new Map(),    // clave -> {id_cliente, cliente_nombre, tipo_documento, id_documento, numero_documento, saldo, monto}
    };

    const r2 = (v) => Math.round(Number(v || 0) * 100) / 100;
    const claveDoc = (tipo, id) => `${tipo}:${id}`;

    function nombreCliente(idCliente) {
        const c = (window.CC_CLIENTES || []).find((x) => x.id === Number(idCliente));
        return c ? c.nombre : '';
    }

    function totalAsignado() {
        let t = 0;
        buscar.sel.forEach((s) => { t += Number(s.monto) || 0; });
        return r2(t);
    }

    function checkboxDoc(clave) {
        return Array.from(document.querySelectorAll('#cc-buscar-docs-tbody input[data-doc-key]'))
            .find((el) => el.dataset.docKey === clave) || null;
    }

    CC.abrirBuscarDoc = function (idLinea) {
        const l = state.lineas[idLinea];
        if (!l) return;

        document.getElementById('cc-buscar-id-linea').value = idLinea;
        buscar.idLinea = idLinea;
        buscar.montoLinea = r2(l.monto);
        buscar.docs = {};
        buscar.sel = new Map();

        // Si la línea ya tiene un documento elegido, llega preseleccionado.
        if (l.id_cliente_sugerido && l.tipo_documento_sugerido && l.id_documento_sugerido && l.documento_numero) {
            const saldo = l.documento_saldo_pendiente != null ? Number(l.documento_saldo_pendiente) : Number(l.monto);
            buscar.sel.set(claveDoc(l.tipo_documento_sugerido, l.id_documento_sugerido), {
                id_cliente: Number(l.id_cliente_sugerido),
                cliente_nombre: l.cliente_sugerido_nombre || nombreCliente(l.id_cliente_sugerido),
                tipo_documento: l.tipo_documento_sugerido,
                id_documento: Number(l.id_documento_sugerido),
                numero_documento: l.documento_numero,
                saldo: saldo,
                monto: r2(Math.min(l.monto_aplicar != null ? Number(l.monto_aplicar) : Number(l.monto), saldo, Number(l.monto))),
            });
        }

        document.getElementById('cc-buscar-desc').textContent = `${fmtDate(l.fecha_movimiento)} · ${l.descripcion_original || ''}`;
        document.getElementById('cc-buscar-monto').textContent = fmtMoney(buscar.montoLinea);

        const select = document.getElementById('cc-buscar-cliente');
        select.innerHTML = '<option value="">— Seleccione —</option>' +
            (window.CC_CLIENTES || []).map((c) => `<option value="${c.id}">${escHtml(c.nombre)}</option>`).join('');
        select.value = l.id_cliente_sugerido ? String(l.id_cliente_sugerido) : '';

        document.getElementById('cc-buscar-docs-tbody').innerHTML =
            '<tr><td colspan="5" class="text-center text-muted py-3">Seleccione un cliente.</td></tr>';
        CC.renderSeleccion();

        if (select.value) {
            CC.buscarDocumentosDeCliente();
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('cc-modal-buscar-doc')).show();
    };

    CC.buscarDocumentosDeCliente = async function () {
        const idCliente = parseInt(document.getElementById('cc-buscar-cliente').value || '0', 10);
        const tbody = document.getElementById('cc-buscar-docs-tbody');
        if (!idCliente) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Seleccione un cliente.</td></tr>';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Buscando…</td></tr>';
        const json = await getJson(`${CC_URL_BASE}/buscarDocumentosPendientesAjax?id_cliente=${idCliente}`);
        // Si mientras cargaba se eligió otro cliente, esta respuesta ya no aplica.
        if (parseInt(document.getElementById('cc-buscar-cliente').value || '0', 10) !== idCliente) return;

        if (!json.ok) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3">${escHtml(json.error || 'No se pudieron consultar los documentos.')}</td></tr>`;
            return;
        }
        if (!json.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Este cliente no tiene documentos pendientes.</td></tr>';
            return;
        }

        const clienteNombre = nombreCliente(idCliente);
        tbody.innerHTML = json.data.map((d) => {
            const clave = claveDoc(d.tipo_documento, d.id);
            buscar.docs[clave] = Object.assign({}, d, { id_cliente: idCliente, cliente_nombre: clienteNombre });
            return `<tr class="cc-doc-fila${buscar.sel.has(clave) ? ' table-primary' : ''}" data-fila-key="${escHtml(clave)}">
                <td class="text-center"><input type="checkbox" class="form-check-input m-0" data-doc-key="${escHtml(clave)}" ${buscar.sel.has(clave) ? 'checked' : ''}></td>
                <td>${escHtml(d.tipo_documento)}</td>
                <td>${escHtml(d.numero_documento)}</td>
                <td>${fmtDate(d.fecha_emision)}</td>
                <td class="text-end">${fmtMoney(d.saldo_pendiente)}</td>
            </tr>`;
        }).join('');
    };

    /** Marca o desmarca un documento; al marcarlo propone min(saldo, lo que falta por asignar). */
    CC.toggleDocumento = function (clave, marcado) {
        if (marcado) {
            const d = buscar.docs[clave];
            if (!d) return;
            const restante = r2(buscar.montoLinea - totalAsignado());
            if (restante <= 0) {
                alertError('Monto completo', 'Ya se asignó todo el monto recibido. Reduzca el monto de otro documento o desmárquelo antes de agregar uno nuevo.');
                const chk = checkboxDoc(clave);
                if (chk) chk.checked = false;
                return;
            }
            buscar.sel.set(clave, {
                id_cliente: d.id_cliente,
                cliente_nombre: d.cliente_nombre,
                tipo_documento: d.tipo_documento,
                id_documento: Number(d.id),
                numero_documento: d.numero_documento,
                saldo: Number(d.saldo_pendiente),
                monto: r2(Math.min(Number(d.saldo_pendiente), restante)),
            });
        } else {
            buscar.sel.delete(clave);
            const chk = checkboxDoc(clave);
            if (chk) chk.checked = false;
        }
        CC.renderSeleccion();
    };

    /** Monto a aplicar de un documento marcado: no puede superar su saldo ni lo que queda del depósito. */
    CC.cambiarMontoSeleccion = function (clave, input) {
        const s = buscar.sel.get(clave);
        if (!s) return;
        const disponible = r2(buscar.montoLinea - (totalAsignado() - Number(s.monto)));
        const tope = r2(Math.min(s.saldo, disponible));
        let valor = r2(parseFloat(input.value) || 0);
        if (valor > tope) {
            valor = tope;
            alertError('Monto ajustado', `El monto no puede superar ${fmtMoney(tope)} (saldo del documento o lo que queda del depósito).`);
        }
        if (valor < 0) valor = 0;
        s.monto = valor;
        CC.renderSeleccion();
    };

    CC.renderSeleccion = function () {
        const tbody = document.getElementById('cc-buscar-sel-tbody');
        const items = Array.from(buscar.sel.entries());

        tbody.innerHTML = items.length
            ? items.map(([clave, s]) => `<tr>
                <td class="text-truncate" style="max-width:260px;" title="${escHtml(s.cliente_nombre)}">${escHtml(s.cliente_nombre)}</td>
                <td>${escHtml(s.tipo_documento)} ${escHtml(s.numero_documento)}</td>
                <td class="text-end">${fmtMoney(s.saldo)}</td>
                <td class="text-end"><input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end" style="height:26px;" data-monto-key="${escHtml(clave)}" value="${Number(s.monto).toFixed(2)}"></td>
                <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" title="Quitar" data-quitar-key="${escHtml(clave)}"><i class="bi bi-x-lg"></i></button></td>
            </tr>`).join('')
            : '<tr><td colspan="5" class="text-center text-muted py-3">Ningún documento seleccionado.</td></tr>';

        document.querySelectorAll('#cc-buscar-docs-tbody tr[data-fila-key]').forEach((tr) => {
            const marcado = buscar.sel.has(tr.dataset.filaKey);
            tr.classList.toggle('table-primary', marcado);
            const chk = tr.querySelector('input[data-doc-key]');
            if (chk) chk.checked = marcado;
        });

        const asignado = totalAsignado();
        const restante = r2(buscar.montoLinea - asignado);
        document.getElementById('cc-buscar-asignado').textContent = fmtMoney(asignado);
        const elRest = document.getElementById('cc-buscar-restante');
        elRest.textContent = fmtMoney(restante);
        elRest.className = restante > 0 ? 'text-warning-emphasis' : 'text-success';
        document.getElementById('cc-buscar-sel-count').textContent = items.length;

        const btn = document.getElementById('cc-buscar-aplicar');
        btn.innerHTML = items.length > 1
            ? `<i class="bi bi-diagram-3 me-1"></i> Aplicar a ${items.length} documentos`
            : '<i class="bi bi-check2 me-1"></i> Aplicar selección';
    };

    CC.aplicarSeleccion = async function () {
        const l = state.lineas[buscar.idLinea];
        if (!l) return;
        const items = Array.from(buscar.sel.values());

        if (!items.length) {
            alertError('Sin selección', 'Marque al menos un documento.');
            return;
        }
        if (items.some((s) => !(Number(s.monto) > 0))) {
            alertError('Monto inválido', 'Cada documento seleccionado debe tener un monto a aplicar mayor a cero.');
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cc-modal-buscar-doc'));

        // Un solo documento: se asigna a la línea como siempre (se confirma con el botón ✓).
        if (items.length === 1) {
            const s = items[0];
            const eraConfirmada = l.estado === 'CONFIRMADO';
            Object.assign(l, {
                id_cliente_sugerido: s.id_cliente,
                cliente_sugerido_nombre: s.cliente_nombre || l.cliente_sugerido_nombre,
                tipo_documento_sugerido: s.tipo_documento,
                id_documento_sugerido: s.id_documento,
                documento_numero: s.numero_documento,
                documento_saldo_pendiente: s.saldo,
                monto_aplicar: s.monto,
            });
            if (l.estado === 'SIN_MATCH' || l.estado === 'ERROR') l.estado = 'SUGERIDO';

            modal.hide();
            // Si ya estaba confirmada, se reconfirma con el documento nuevo: si no, el servidor
            // seguiría con el documento anterior aunque la pantalla muestre el nuevo.
            if (eraConfirmada) {
                await CC.confirmarLinea(l.id);
            } else {
                CC.renderLineas();
            }
            return;
        }

        // Varios documentos: la línea se divide en el servidor.
        const sobrante = r2(buscar.montoLinea - totalAsignado());
        // Al generar, las partes se cobran juntas: un ingreso por cliente con un solo pago.
        const clientes = new Set(items.map((s) => s.id_cliente)).size;
        const texto = `La línea de ${fmtMoney(buscar.montoLinea)} se dividirá en ${items.length} líneas confirmadas (una por documento)`
            + (sobrante > 0 ? ` y una línea más con ${fmtMoney(sobrante)} sin asignar.` : '.')
            + (clientes === 1
                ? ` Al generar, se creará UN solo ingreso con los ${items.length} documentos y un pago de ${fmtMoney(totalAsignado())}.`
                : ` Al generar, se creará un ingreso por cliente (${clientes}), cada uno con sus documentos y un solo pago.`);
        if (window.Swal) {
            const r = await Swal.fire({ icon: 'question', title: '¿Repartir el depósito?', text: texto, showCancelButton: true, confirmButtonText: 'Sí, repartir', cancelButtonText: 'Cancelar' });
            if (!r.isConfirmed) return;
        } else if (!confirm(texto)) {
            return;
        }

        const btn = document.getElementById('cc-buscar-aplicar');
        btn.disabled = true;
        try {
            const json = await postJson(`${CC_URL_BASE}/dividirLineaAjax`, {
                id_linea: l.id,
                asignaciones: items.map((s) => ({
                    id_cliente: s.id_cliente,
                    tipo_documento: s.tipo_documento,
                    id_documento: s.id_documento,
                    monto_aplicar: s.monto,
                })),
            });
            if (!json.ok) {
                alertError('No se pudo repartir la línea', json.error);
                return;
            }
            modal.hide();
            await CC.cargarLineas(state.idCargaActual);
            alertOk('Línea repartida', `Se generaron ${json.data.ids.length} líneas. Pulse «Generar ingresos» cuando termine de revisar.`);
        } finally {
            btn.disabled = false;
        }
    };
})();
