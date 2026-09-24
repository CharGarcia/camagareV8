/**
 * Config › Perfiles de lectura de tarjetas (config/conciliacion-tarjetas-perfiles, nivel 3).
 * Catálogo global: cada perfil describe cómo leer el estado de cuenta de una procesadora
 * (Payphone, Nuvei, datáfono de un banco) y lo eligen todas las empresas en
 * modulos/conciliacion-tarjetas.
 */
(function () {
    'use strict';

    const state = {
        perfiles: {}, // id -> perfil
        orden: { col: 'tipo_procesadora', dir: 'ASC' },
    };

    /** Campos del mapeo Excel/CSV: [clave, etiqueta, obligatorio] (ver ConciliacionTarjetasImportService::CAMPOS). */
    const CAMPOS_MAPEO = [
        ['fecha', 'Fecha', true],
        ['autorizacion', 'Autorización', false],
        ['referencia', 'Referencia', false],
        ['descripcion', 'Descripción', false],
        ['monto_bruto', 'Bruto', true],
        ['comision', 'Comisión', false],
        ['iva_comision', 'IVA comisión', false],
        ['retencion_ir', 'Retención renta', false],
        ['retencion_iva', 'Retención IVA', false],
        ['otros_descuentos', 'Otros descuentos', false],
        ['monto_neto', 'Neto', false],
    ];

    const PROCESADORAS = { PAYPHONE: 'Payphone', NUVEI: 'Nuvei', TARJETA: 'Tarjeta (datáfono)' };

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function fmtMoney(v) {
        return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(v) {
        if (!v) return '—';
        const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${m[3]}-${m[2]}-${m[1]}` : v;
    }

    /** d-m-Y H:i:s (estándar del sistema). */
    function fmtDateTime(v) {
        if (!v) return '—';
        const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
        return m ? `${m[3]}-${m[2]}-${m[1]} ${m[4]}:${m[5]}:${m[6]}` : v;
    }

    function esActivo(p) {
        return p.activo === true || p.activo === 't' || p.activo === 1 || p.activo === '1';
    }

    function nombreProcesadora(p) {
        return PROCESADORAS[p.tipo_procesadora] || 'Cualquiera';
    }

    function alertError(titulo, mensaje) {
        if (window.Swal) {
            Swal.fire({ icon: 'error', title: titulo, text: mensaje || 'Error desconocido.' });
        } else {
            alert(titulo + ': ' + (mensaje || 'Error desconocido.'));
        }
    }

    function alertOk(titulo) {
        if (window.Swal) {
            Swal.fire({ icon: 'success', title: titulo, timer: 1500, showConfirmButton: false });
        }
    }

    async function confirmar(texto) {
        if (window.Swal) {
            const r = await Swal.fire({ icon: 'warning', title: '¿Confirmar?', text: texto, showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar' });
            return r.isConfirmed;
        }
        return confirm(texto);
    }

    async function getJson(action, params) {
        const qs = new URLSearchParams(Object.assign({ action }, params || {}));
        const resp = await fetch(`${CTP_URL}?${qs}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return resp.json();
    }

    async function postJson(action, payload) {
        const resp = await fetch(`${CTP_URL}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload || {}),
        });
        return resp.json();
    }

    async function postForm(action, formData) {
        const resp = await fetch(`${CTP_URL}?action=${action}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
        return resp.json();
    }

    const CTP = window.CTP = {};
    const $ = (id) => document.getElementById(id);

    function pintarCamposMapeo() {
        $('ctp-mapeo-campos').innerHTML = CAMPOS_MAPEO.map(([campo, etiqueta, obligatorio]) => `
            <div class="col-6 col-md-3">
                <label for="ctp-map-${campo}" class="form-label">${esc(etiqueta)}${obligatorio ? ' <span class="text-danger">*</span>' : ''}</label>
                <input type="number" id="ctp-map-${campo}" class="form-control form-control-sm ctp-mapeo-campo" data-campo="${campo}" min="0">
            </div>`).join('');
    }

    /** Mapeo tal como se guarda en conciliacion_tarjetas_perfiles.mapeo_columnas. */
    function mapeoActual() {
        if ($('ctp-tipo').value === 'PDF') {
            const regex = $('ctp-regex').value.trim();
            return regex ? { regex_linea: regex } : {};
        }
        const mapeo = {};
        document.querySelectorAll('.ctp-mapeo-campo').forEach((i) => {
            if (i.value !== '') mapeo[i.dataset.campo] = { col: parseInt(i.value, 10) };
        });
        return mapeo;
    }

    // ── Listado ──────────────────────────────────────────────────────────────

    CTP.render = function () {
        const filtro = ($('ctp-buscar').value || '').trim().toLowerCase();
        const { col, dir } = state.orden;
        const valor = (p) => {
            if (col === 'activo') return esActivo(p) ? '1' : '0';
            if (col === 'tipo_procesadora') return nombreProcesadora(p).toLowerCase();
            return String(p[col] ?? '').toLowerCase();
        };
        const filas = Object.values(state.perfiles)
            .filter((p) => !filtro || `${p.nombre_perfil} ${nombreProcesadora(p)} ${p.nombre_banco || ''} ${p.tipo_archivo}`.toLowerCase().includes(filtro))
            .sort((a, b) => {
                const r = valor(a).localeCompare(valor(b), 'es') || a.nombre_perfil.localeCompare(b.nombre_perfil, 'es') || (b.id - a.id);
                return dir === 'DESC' ? -r : r;
            });

        const tbody = $('ctp-tbody');
        if (!filas.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-credit-card fs-3 d-block mb-2"></i>No hay perfiles registrados.</td></tr>';
            return;
        }

        tbody.innerHTML = filas.map((p) => `
            <tr class="perfil-row" role="button" tabindex="0" data-id="${p.id}">
                <td>${esc(nombreProcesadora(p))}</td>
                <td>${esc(p.nombre_banco || 'Cualquiera')}</td>
                <td>${esc(p.nombre_perfil)}</td>
                <td>${esc(p.tipo_archivo === 'EXCEL' ? 'Excel' : p.tipo_archivo)}</td>
                <td>${p.nivel === 'deposito' ? 'Depósitos' : 'Transacciones'}</td>
                <td class="text-center">${esActivo(p) ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
            </tr>
        `).join('');
    };

    CTP.recargar = async function () {
        const json = await getJson('listar');
        if (!json.ok) {
            alertError('No se pudo cargar el listado', json.error);
            return;
        }
        CTP.setPerfiles(json.data);
    };

    CTP.setPerfiles = function (lista) {
        state.perfiles = {};
        (lista || []).forEach((p) => { state.perfiles[p.id] = p; });
        CTP.render();
    };

    // ── Modal ────────────────────────────────────────────────────────────────

    CTP.abrirModal = function (id) {
        $('ctp-form').reset();
        $('ctp-id').value = '';
        $('ctp-preview-box').textContent = '— Sin previsualización aún —';
        $('ctp-auditoria').textContent = '';

        const p = id ? state.perfiles[id] : null;
        $('ctp-modal-titulo').innerHTML = p
            ? '<i class="bi bi-pencil"></i> Editar perfil de lectura'
            : '<i class="bi bi-plus-circle"></i> Nuevo perfil de lectura';
        $('ctp-btn-eliminar').style.display = p ? '' : 'none';
        $('ctp-btn-guardar').innerHTML = p ? '<i class="bi bi-check-lg"></i> Guardar' : '<i class="bi bi-check-lg"></i> Crear';

        if (p) {
            $('ctp-id').value = p.id;
            $('ctp-nombre').value = p.nombre_perfil;
            $('ctp-procesadora').value = p.tipo_procesadora || '';
            $('ctp-banco').value = p.id_banco || '';
            $('ctp-tipo').value = p.tipo_archivo;
            $('ctp-nivel').value = p.nivel || 'transaccion';
            $('ctp-separador').value = p.separador_decimal || '.';
            $('ctp-fila-inicio').value = p.fila_inicio || 0;
            $('ctp-formato-fecha').value = p.formato_fecha || 'd/m/Y';
            $('ctp-activo').value = esActivo(p) ? '1' : '0';

            const mapeo = p.mapeo_columnas || {};
            $('ctp-regex').value = mapeo.regex_linea || '';
            document.querySelectorAll('.ctp-mapeo-campo').forEach((i) => {
                const def = mapeo[i.dataset.campo];
                i.value = (def && def.col !== undefined) ? def.col : '';
            });
            $('ctp-auditoria').textContent = `Creado: ${fmtDateTime(p.created_at)} · Última modificación: ${fmtDateTime(p.updated_at)}`;
        }

        CTP.cambiarTipo();
        bootstrap.Tab.getOrCreateInstance($('ctp-tab-datos')).show();
        bootstrap.Modal.getOrCreateInstance($('ctp-modal')).show();
    };

    CTP.cambiarTipo = function () {
        const esPdf = $('ctp-tipo').value === 'PDF';
        $('ctp-mapeo-excel').style.display = esPdf ? 'none' : '';
        $('ctp-mapeo-pdf').style.display = esPdf ? '' : 'none';
        $('ctp-fila-inicio-wrap').style.display = esPdf ? 'none' : '';
        $('ctp-preview-resultado').style.display = 'none';
    };

    CTP.previsualizarMuestra = async function () {
        const archivo = $('ctp-muestra').files[0];
        if (!archivo) {
            alertError('Falta el archivo', 'Selecciona primero un archivo de muestra.');
            return;
        }
        const esPdf = $('ctp-tipo').value === 'PDF';

        const fd = new FormData();
        fd.append('archivo', archivo);
        fd.append('tipo_archivo', $('ctp-tipo').value);
        fd.append('fila_inicio', $('ctp-fila-inicio').value || 0);
        fd.append('formato_fecha', $('ctp-formato-fecha').value.trim());
        fd.append('separador_decimal', $('ctp-separador').value);
        fd.append('mapeo_prueba', JSON.stringify(mapeoActual()));

        const box = $('ctp-preview-box');
        box.textContent = 'Cargando…';
        $('ctp-preview-resultado').style.display = 'none';

        const json = await postForm('previsualizar', fd);
        if (!json.ok) {
            box.textContent = '— No se pudo leer el archivo —';
            alertError('No se pudo previsualizar', json.error);
            return;
        }

        const lineas = json.data.lineas || [];
        box.textContent = esPdf
            ? lineas.join('\n')
            : lineas.map((fila, i) => `Fila ${i}: ` + (fila || []).map((v, c) => `[${c}]${v ?? ''}`).join('  ')).join('\n');
        CTP.mostrarResultadoPrueba(json.data.filas_probadas);
    };

    CTP.mostrarResultadoPrueba = function (resultado) {
        const wrap = $('ctp-preview-resultado');
        const tbody = $('ctp-preview-resultado-tbody');
        if (!resultado) {
            wrap.style.display = 'none';
            return;
        }
        wrap.style.display = '';
        if (resultado.error) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger">${esc(resultado.error)}</td></tr>`;
            return;
        }
        if (!resultado.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">El mapeo actual no encontró ninguna línea con fecha y valor en este archivo.</td></tr>';
            return;
        }
        tbody.innerHTML = resultado.slice(0, 20).map((l) => `
            <tr>
                <td>${fmtDate(l.fecha)}</td>
                <td>${esc(l.autorizacion || '')}</td>
                <td class="text-end">${fmtMoney(l.monto_bruto)}</td>
                <td class="text-end">${fmtMoney(l.comision)}</td>
                <td class="text-end">${fmtMoney(l.monto_neto)}</td>
            </tr>
        `).join('');
    };

    CTP.guardar = async function () {
        const json = await postJson('guardar', {
            id: $('ctp-id').value || null,
            nombre_perfil: $('ctp-nombre').value.trim(),
            tipo_procesadora: $('ctp-procesadora').value || null,
            id_banco: $('ctp-banco').value || null,
            tipo_archivo: $('ctp-tipo').value,
            nivel: $('ctp-nivel').value,
            fila_inicio: parseInt($('ctp-fila-inicio').value || '0', 10),
            formato_fecha: $('ctp-formato-fecha').value.trim() || 'd/m/Y',
            separador_decimal: $('ctp-separador').value,
            activo: $('ctp-activo').value === '1',
            mapeo_columnas: mapeoActual(),
        });
        if (!json.ok) {
            alertError('No se pudo guardar el perfil', json.error);
            return;
        }
        bootstrap.Modal.getInstance($('ctp-modal'))?.hide();
        alertOk('Perfil guardado');
        await CTP.recargar();
    };

    CTP.eliminar = async function () {
        const id = $('ctp-id').value;
        if (!id) return;
        if (!await confirmar('Se eliminará este perfil. Las conciliaciones ya cargadas con él no se afectan, pero dejará de ofrecerse en Conciliación de Tarjetas.')) return;

        const json = await postJson('eliminar', { id: parseInt(id, 10) });
        if (!json.ok) {
            alertError('No se pudo eliminar el perfil', json.error);
            return;
        }
        bootstrap.Modal.getInstance($('ctp-modal'))?.hide();
        alertOk('Perfil eliminado');
        await CTP.recargar();
    };

    document.addEventListener('DOMContentLoaded', () => {
        pintarCamposMapeo();
        if (window.CTP_ORDEN) state.orden = { col: window.CTP_ORDEN.col, dir: window.CTP_ORDEN.dir };
        CTP.setPerfiles(window.CTP_PERFILES || []);
        $('ctp-buscar').addEventListener('input', CTP.render);

        // Clic (o Enter/Espacio) en una fila abre el modal de edición, como en las demás tarjetas de config.
        const tbody = $('ctp-tbody');
        tbody.addEventListener('click', (e) => {
            const row = e.target.closest('.perfil-row');
            if (row) CTP.abrirModal(parseInt(row.dataset.id, 10));
        });
        tbody.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const row = e.target.closest('.perfil-row');
            if (row) {
                e.preventDefault();
                CTP.abrirModal(parseInt(row.dataset.id, 10));
            }
        });

        // El listado entero ya está en el navegador: se ordena aquí, sin recargar (reload: false).
        if (window.CMG_initSort) {
            window.CMG_initSort('conciliacion-tarjetas-perfiles', (col, dir) => {
                state.orden = { col: col || 'tipo_procesadora', dir: dir || 'ASC' };
                CTP.render();
            }, { col: state.orden.col, dir: state.orden.dir, reload: false });
        }
    });
})();
