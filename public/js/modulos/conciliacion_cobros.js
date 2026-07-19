(function () {
    'use strict';

    const state = {
        idCargaActual: null,
        lineas: {},       // id_linea -> linea (con selección actual de cliente/documento)
        perfiles: {},     // id_perfil -> perfil (cache para editar)
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

    async function getJson(url) {
        const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return resp.json();
    }

    async function postJson(url, payload) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload || {}),
        });
        return resp.json();
    }

    async function postForm(url, formData) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
        return resp.json();
    }

    const CC = window.CC = {};

    // ── Inicialización ───────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        CC.cargarPerfiles();

        const formCarga = document.getElementById('cc-form-carga');
        if (formCarga) {
            formCarga.addEventListener('submit', CC.onSubmitCarga);
        }
    });

    // ── Perfiles de mapeo ────────────────────────────────────────────────────

    CC.cargarPerfiles = async function (seleccionarId) {
        const json = await getJson(`${CC_URL_BASE}/listarPerfilesAjax`);
        if (!json.ok) return;

        state.perfiles = {};
        (json.data || []).forEach((p) => { state.perfiles[p.id] = p; });

        const selectPrincipal = document.getElementById('cc-perfil');
        const selectExistente = document.getElementById('cc-perfil-select-existente');
        const opciones = (json.data || []).map((p) => `<option value="${p.id}">${p.nombre_perfil} (${p.tipo_archivo})</option>`).join('');

        if (selectPrincipal) {
            selectPrincipal.innerHTML = '<option value="">— Seleccione —</option>' + opciones;
            if (seleccionarId) selectPrincipal.value = seleccionarId;
        }
        if (selectExistente) {
            selectExistente.innerHTML = '<option value="">— Nuevo perfil —</option>' + opciones;
        }
    };

    CC.abrirModalPerfil = function () {
        document.getElementById('cc-form-perfil').reset();
        document.getElementById('cc-perfil-id').value = '';
        document.getElementById('cc-perfil-select-existente').value = '';
        document.getElementById('cc-preview-box').textContent = '— Sin previsualización aún —';
        CC.cambiarTipoPerfil();
        new bootstrap.Modal(document.getElementById('cc-modal-perfil')).show();
    };

    CC.cambiarTipoPerfil = function () {
        const esPdf = document.getElementById('cc-perfil-tipo').value === 'PDF';
        document.getElementById('cc-mapeo-excel').style.display = esPdf ? 'none' : '';
        document.getElementById('cc-mapeo-pdf').style.display = esPdf ? '' : 'none';
        document.getElementById('cc-fila-inicio-wrap').style.display = esPdf ? 'none' : '';
        document.getElementById('cc-btn-sugerir-regex').style.display = esPdf ? '' : 'none';
        document.getElementById('cc-preview-resultado').style.display = 'none';
        document.getElementById('cc-sugerencia-msg').style.display = 'none';
    };

    CC.cargarPerfilExistente = function (id) {
        if (!id) {
            CC.abrirModalPerfil();
            return;
        }
        const p = state.perfiles[id];
        if (!p) return;

        document.getElementById('cc-perfil-id').value = p.id;
        document.getElementById('cc-perfil-nombre').value = p.nombre_perfil;
        document.getElementById('cc-perfil-tipo').value = p.tipo_archivo;
        document.getElementById('cc-perfil-separador').value = p.separador_decimal || '.';
        document.getElementById('cc-perfil-fila-inicio').value = p.fila_inicio || 0;
        document.getElementById('cc-perfil-formato-fecha').value = p.formato_fecha || 'd/m/Y';
        CC.cambiarTipoPerfil();

        const mapeo = p.mapeo_columnas || {};
        if (p.tipo_archivo === 'PDF') {
            document.getElementById('cc-map-regex-linea').value = mapeo.regex_linea || '';
            document.getElementById('cc-map-tipo-credito').value = mapeo.tipo_credito || '';
        } else {
            ['fecha', 'descripcion', 'monto', 'referencia'].forEach((campo) => {
                document.getElementById(`cc-map-${campo}-col`).value = mapeo[campo] ? (mapeo[campo].col ?? '') : '';
            });
        }
    };

    CC.previsualizarMuestra = async function () {
        const archivo = document.getElementById('cc-perfil-muestra').files[0];
        if (!archivo) {
            alertError('Falta el archivo', 'Selecciona primero un archivo de muestra.');
            return;
        }
        const tipoArchivo = document.getElementById('cc-perfil-tipo').value;
        const filaInicio = document.getElementById('cc-perfil-fila-inicio').value || 0;

        const fd = new FormData();
        fd.append('archivo', archivo);
        fd.append('tipo_archivo', tipoArchivo);
        fd.append('fila_inicio', filaInicio);
        if (tipoArchivo === 'PDF') {
            fd.append('regex_prueba', document.getElementById('cc-map-regex-linea').value.trim());
            fd.append('tipo_credito_prueba', document.getElementById('cc-map-tipo-credito').value.trim());
        }

        const box = document.getElementById('cc-preview-box');
        box.textContent = 'Cargando…';
        document.getElementById('cc-preview-resultado').style.display = 'none';

        const json = await postForm(`${CC_URL_BASE}/previsualizarArchivoAjax`, fd);
        if (!json.ok) {
            box.textContent = '— No se pudo leer el archivo —';
            alertError('No se pudo previsualizar', json.error);
            return;
        }

        if (tipoArchivo === 'PDF') {
            box.textContent = (json.data.lineas || []).join('\n');
            CC.mostrarResultadoPrueba(json.data.filas_probadas);
        } else {
            box.textContent = (json.data.lineas || [])
                .map((fila, i) => `Fila ${i}: ` + fila.map((v, c) => `[${c}]${v}`).join('  '))
                .join('\n');
        }
    };

    CC.sugerirRegexPdf = async function () {
        const archivo = document.getElementById('cc-perfil-muestra').files[0];
        if (!archivo) {
            alertError('Falta el archivo', 'Selecciona primero un archivo de muestra (PDF).');
            return;
        }

        const btn = document.getElementById('cc-btn-sugerir-regex');
        const msg = document.getElementById('cc-sugerencia-msg');
        btn.disabled = true;
        msg.style.display = '';
        msg.className = 'alert alert-info small py-2 mb-3';
        msg.textContent = 'Analizando el PDF…';

        try {
            const fd = new FormData();
            fd.append('archivo', archivo);

            const json = await postForm(`${CC_URL_BASE}/sugerirRegexPdfAjax`, fd);
            if (!json.ok) {
                msg.className = 'alert alert-danger small py-2 mb-3';
                msg.textContent = 'No se pudo analizar el archivo: ' + json.error;
                return;
            }

            const s = json.data;
            if (!s.regex_linea) {
                msg.className = 'alert alert-warning small py-2 mb-3';
                msg.textContent = s.mensaje;
                return;
            }

            document.getElementById('cc-map-regex-linea').value = s.regex_linea;
            document.getElementById('cc-perfil-formato-fecha').value = s.formato_fecha;
            document.getElementById('cc-perfil-separador').value = s.separador_decimal;

            msg.className = 'alert alert-success small py-2 mb-3';
            msg.textContent = s.mensaje;

            // Muestra de una vez el resultado con el patrón propuesto (sin "Valor es crédito" — hay que revisarlo a mano).
            await CC.previsualizarMuestra();
        } finally {
            btn.disabled = false;
        }
    };

    CC.mostrarResultadoPrueba = function (resultado) {
        const wrap = document.getElementById('cc-preview-resultado');
        const tbody = document.getElementById('cc-preview-resultado-tbody');
        if (!resultado) {
            wrap.style.display = 'none';
            return;
        }
        if (resultado.error) {
            wrap.style.display = '';
            tbody.innerHTML = `<tr><td colspan="4" class="text-danger">${resultado.error}</td></tr>`;
            return;
        }
        wrap.style.display = '';
        if (!resultado.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">El patrón no encontró ninguna línea de datos en este archivo.</td></tr>';
            return;
        }
        tbody.innerHTML = resultado.map((f) => `
            <tr>
                <td>${fmtDate(f.fecha)}</td>
                <td>${f.descripcion}</td>
                <td class="text-end">${fmtMoney(f.monto)}</td>
                <td>${f.referencia || ''}</td>
            </tr>
        `).join('');
    };

    CC.guardarPerfil = async function () {
        const tipoArchivo = document.getElementById('cc-perfil-tipo').value;
        const mapeo = {};

        if (tipoArchivo === 'PDF') {
            const regex = document.getElementById('cc-map-regex-linea').value.trim();
            if (regex) mapeo.regex_linea = regex;
            const tipoCredito = document.getElementById('cc-map-tipo-credito').value.trim();
            if (tipoCredito) mapeo.tipo_credito = tipoCredito;
        } else {
            ['fecha', 'descripcion', 'monto', 'referencia'].forEach((campo) => {
                const col = document.getElementById(`cc-map-${campo}-col`).value;
                if (col !== '') mapeo[campo] = { col: parseInt(col, 10) };
            });
        }

        const payload = {
            id: document.getElementById('cc-perfil-id').value || null,
            nombre_perfil: document.getElementById('cc-perfil-nombre').value.trim(),
            tipo_archivo: tipoArchivo,
            fila_inicio: parseInt(document.getElementById('cc-perfil-fila-inicio').value || '0', 10),
            formato_fecha: document.getElementById('cc-perfil-formato-fecha').value.trim() || 'd/m/Y',
            separador_decimal: document.getElementById('cc-perfil-separador').value,
            mapeo_columnas: mapeo,
        };

        const json = await postJson(`${CC_URL_BASE}/guardarPerfilAjax`, payload);
        if (!json.ok) {
            alertError('No se pudo guardar el perfil', json.error);
            return;
        }
        alertOk('Perfil guardado');
        bootstrap.Modal.getInstance(document.getElementById('cc-modal-perfil')).hide();
        CC.cargarPerfiles(json.data.id);
    };

    // ── Carga del extracto ───────────────────────────────────────────────────

    CC.onSubmitCarga = async function (ev) {
        ev.preventDefault();

        const idForma = document.getElementById('cc-forma').value;
        const idPunto = document.getElementById('cc-punto').value;
        const idPerfil = document.getElementById('cc-perfil').value;
        const archivo = document.getElementById('cc-archivo').files[0];

        if (!idForma || !idPunto || !idPerfil || !archivo) {
            alertError('Faltan datos', 'Selecciona cuenta, punto de emisión, perfil y archivo.');
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
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Sin cargas todavía.</td></tr>';
            return;
        }

        const badgeClase = { completado: 'success', error: 'danger' };
        tbody.innerHTML = json.data.map((c) => {
            const clase = badgeClase[c.estado] || 'warning';
            return `<tr>
                <td>${fmtDate(c.created_at)}</td>
                <td>${c.nombre_archivo}</td>
                <td>${c.forma_pago_nombre}</td>
                <td>${c.nombre_perfil}</td>
                <td class="text-center"><span class="badge bg-${clase} bg-opacity-25 text-${clase === 'warning' ? 'warning-emphasis' : clase}">${c.estado}</span></td>
                <td class="text-end">${c.total_aplicadas} / ${c.total_lineas}</td>
                <td class="text-end"><button type="button" class="btn btn-outline-primary btn-sm" onclick="CC.abrirCarga(${c.id})"><i class="bi bi-eye"></i> Ver</button></td>
            </tr>`;
        }).join('');
    };

    CC.abrirCarga = async function (idCarga) {
        state.idCargaActual = idCarga;
        await CC.cargarLineas(idCarga);
        document.getElementById('cc-card-lineas').scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // ── Líneas del extracto ──────────────────────────────────────────────────

    CC.cargarLineas = async function (idCarga) {
        const json = await getJson(`${CC_URL_BASE}/listarLineasAjax?id_carga=${idCarga}`);
        const card = document.getElementById('cc-card-lineas');
        card.style.display = '';

        if (!json.ok) {
            alertError('No se pudieron cargar las líneas', json.error);
            return;
        }

        state.lineas = {};
        (json.data || []).forEach((l) => { state.lineas[l.id] = l; });

        CC.renderLineas();
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

        const clienteTxt = l.cliente_sugerido_nombre || '<span class="text-muted">— sin identificar —</span>';
        const docTxt = l.documento_numero
            ? `${l.tipo_documento_sugerido} ${l.documento_numero} <br><small class="text-muted">saldo: ${fmtMoney(l.documento_saldo_pendiente)}</small>`
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
            <td data-col="descripcion">${l.descripcion_original}</td>
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

    // ── Búsqueda manual de cliente/documento ────────────────────────────────

    CC.abrirBuscarDoc = function (idLinea) {
        document.getElementById('cc-buscar-id-linea').value = idLinea;
        const l = state.lineas[idLinea];

        const select = document.getElementById('cc-buscar-cliente');
        select.innerHTML = '<option value="">— Seleccione —</option>' +
            (window.CC_CLIENTES || []).map((c) => `<option value="${c.id}">${c.nombre}</option>`).join('');
        select.value = l && l.id_cliente_sugerido ? l.id_cliente_sugerido : '';

        document.getElementById('cc-buscar-docs-tbody').innerHTML =
            '<tr><td colspan="5" class="text-center text-muted py-3">Seleccione un cliente.</td></tr>';

        if (select.value) {
            CC.buscarDocumentosDeCliente();
        }

        new bootstrap.Modal(document.getElementById('cc-modal-buscar-doc')).show();
    };

    CC.buscarDocumentosDeCliente = async function () {
        const idCliente = document.getElementById('cc-buscar-cliente').value;
        const tbody = document.getElementById('cc-buscar-docs-tbody');
        if (!idCliente) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Seleccione un cliente.</td></tr>';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Buscando…</td></tr>';
        const json = await getJson(`${CC_URL_BASE}/buscarDocumentosPendientesAjax?id_cliente=${idCliente}`);
        if (!json.ok || !json.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Este cliente no tiene documentos pendientes.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map((d) => `
            <tr>
                <td>${d.tipo_documento}</td>
                <td>${d.numero_documento}</td>
                <td>${fmtDate(d.fecha_emision)}</td>
                <td class="text-end">${fmtMoney(d.saldo_pendiente)}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick='CC.seleccionarDocumento(${idCliente}, "${d.tipo_documento}", ${d.id}, "${d.numero_documento}", ${d.saldo_pendiente})'>
                        Seleccionar
                    </button>
                </td>
            </tr>
        `).join('');
    };

    CC.seleccionarDocumento = function (idCliente, tipoDocumento, idDocumento, numeroDocumento, saldoPendiente) {
        const idLinea = document.getElementById('cc-buscar-id-linea').value;
        const l = state.lineas[idLinea];
        if (!l) return;

        const clienteNombre = (window.CC_CLIENTES || []).find((c) => c.id === idCliente);

        l.id_cliente_sugerido = idCliente;
        l.cliente_sugerido_nombre = clienteNombre ? clienteNombre.nombre : l.cliente_sugerido_nombre;
        l.tipo_documento_sugerido = tipoDocumento;
        l.id_documento_sugerido = idDocumento;
        l.documento_numero = numeroDocumento;
        l.documento_saldo_pendiente = saldoPendiente;
        l.monto_aplicar = Math.min(Number(saldoPendiente), Number(l.monto));
        if (l.estado === 'SIN_MATCH') l.estado = 'SUGERIDO';

        CC.renderLineas();
        bootstrap.Modal.getInstance(document.getElementById('cc-modal-buscar-doc')).hide();
    };
})();
