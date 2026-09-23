/**
 * Config › Perfiles de mapeo de cobros bancarios (config/conciliacion-perfiles, nivel 3).
 * Catálogo global: cada perfil describe cómo leer el extracto de un banco y lo eligen
 * todas las empresas en modulos/conciliacion-cobros.
 */
(function () {
    'use strict';

    const state = {
        perfiles: {}, // id -> perfil
    };

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function fmtMoney(v) {
        return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(v) {
        if (!v) return '—';
        const d = new Date(String(v).substring(0, 10) + 'T00:00:00');
        if (isNaN(d.getTime())) return v;
        return d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
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
        const resp = await fetch(`${CP_URL}?${qs}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return resp.json();
    }

    async function postJson(action, payload) {
        const resp = await fetch(`${CP_URL}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload || {}),
        });
        return resp.json();
    }

    async function postForm(action, formData) {
        const resp = await fetch(`${CP_URL}?action=${action}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
        return resp.json();
    }

    const CP = window.CP = {};
    const $ = (id) => document.getElementById(id);

    // ── Listado ──────────────────────────────────────────────────────────────

    CP.render = function () {
        const filtro = ($('cp-buscar').value || '').trim().toLowerCase();
        const filas = Object.values(state.perfiles)
            .filter((p) => !filtro || `${p.nombre_perfil} ${p.nombre_banco || ''}`.toLowerCase().includes(filtro))
            .sort((a, b) => `${a.nombre_banco || ''}|${a.nombre_perfil}`.localeCompare(`${b.nombre_banco || ''}|${b.nombre_perfil}`, 'es'));

        const tbody = $('cp-tbody');
        if (!filas.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox d-block mb-2" style="font-size:1.5rem;"></i>No hay perfiles configurados.</td></tr>';
            return;
        }

        tbody.innerHTML = filas.map((p) => {
            const activo = esActivo(p);
            const tipo = p.tipo_archivo === 'PDF' ? 'PDF' : 'Excel / CSV';
            return `<tr>
                <td class="ps-3">${esc(p.nombre_banco || 'Genérico')}</td>
                <td>${esc(p.nombre_perfil)}</td>
                <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">${tipo}</span></td>
                <td class="text-center">${activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
                <td class="small text-muted">${fmtDateTime(p.updated_at || p.created_at)}</td>
                <td class="text-center pe-3 text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 border-0" title="Editar" onclick="CP.abrirModal(${p.id})"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-${activo ? 'secondary' : 'success'} py-0 px-1 border-0" title="${activo ? 'Desactivar' : 'Activar'}" onclick="CP.cambiarEstado(${p.id}, ${activo ? 'false' : 'true'})"><i class="bi bi-${activo ? 'pause-circle' : 'play-circle'}"></i></button>
                </td>
            </tr>`;
        }).join('');
    };

    CP.recargar = async function () {
        const json = await getJson('listar');
        if (!json.ok) {
            alertError('No se pudo cargar el listado', json.error);
            return;
        }
        CP.setPerfiles(json.data);
    };

    CP.setPerfiles = function (lista) {
        state.perfiles = {};
        (lista || []).forEach((p) => { state.perfiles[p.id] = p; });
        CP.render();
    };

    CP.cambiarEstado = async function (id, activo) {
        const json = await postJson('estado', { id, activo });
        if (!json.ok) {
            alertError('No se pudo cambiar el estado', json.error);
            return;
        }
        await CP.recargar();
    };

    // ── Modal ────────────────────────────────────────────────────────────────

    CP.abrirModal = function (id) {
        $('cp-form').reset();
        $('cp-id').value = '';
        $('cp-preview-box').textContent = '— Sin previsualización aún —';
        $('cp-auditoria').textContent = '';

        const p = id ? state.perfiles[id] : null;
        $('cp-modal-titulo').textContent = p ? 'Editar perfil de mapeo' : 'Nuevo perfil de mapeo';
        $('cp-btn-eliminar').style.display = p ? '' : 'none';

        if (p) {
            $('cp-id').value = p.id;
            $('cp-nombre').value = p.nombre_perfil;
            $('cp-banco').value = p.id_banco || '';
            $('cp-tipo').value = p.tipo_archivo;
            $('cp-separador').value = p.separador_decimal || '.';
            $('cp-fila-inicio').value = p.fila_inicio || 0;
            $('cp-formato-fecha').value = p.formato_fecha || 'd/m/Y';
            $('cp-activo').value = esActivo(p) ? '1' : '0';

            const mapeo = p.mapeo_columnas || {};
            if (p.tipo_archivo === 'PDF') {
                $('cp-map-regex-linea').value = mapeo.regex_linea || '';
                $('cp-map-tipo-credito').value = mapeo.tipo_credito || '';
            } else {
                ['fecha', 'descripcion', 'monto', 'referencia'].forEach((campo) => {
                    $(`cp-map-${campo}-col`).value = mapeo[campo] ? (mapeo[campo].col ?? '') : '';
                });
            }
            $('cp-auditoria').textContent = `Creado: ${fmtDateTime(p.created_at)} · Última modificación: ${fmtDateTime(p.updated_at)}`;
        }

        CP.cambiarTipo();
        bootstrap.Modal.getOrCreateInstance($('cp-modal')).show();
    };

    CP.cambiarTipo = function () {
        const esPdf = $('cp-tipo').value === 'PDF';
        $('cp-mapeo-excel').style.display = esPdf ? 'none' : '';
        $('cp-mapeo-pdf').style.display = esPdf ? '' : 'none';
        $('cp-fila-inicio-wrap').style.display = esPdf ? 'none' : '';
        $('cp-btn-sugerir-regex').style.display = esPdf ? '' : 'none';
        $('cp-preview-resultado').style.display = 'none';
        $('cp-sugerencia-msg').style.display = 'none';
    };

    CP.previsualizarMuestra = async function () {
        const archivo = $('cp-muestra').files[0];
        if (!archivo) {
            alertError('Falta el archivo', 'Selecciona primero un archivo de muestra.');
            return;
        }
        const tipoArchivo = $('cp-tipo').value;

        const fd = new FormData();
        fd.append('archivo', archivo);
        fd.append('tipo_archivo', tipoArchivo);
        fd.append('fila_inicio', $('cp-fila-inicio').value || 0);
        if (tipoArchivo === 'PDF') {
            fd.append('regex_prueba', $('cp-map-regex-linea').value.trim());
            fd.append('tipo_credito_prueba', $('cp-map-tipo-credito').value.trim());
        }

        const box = $('cp-preview-box');
        box.textContent = 'Cargando…';
        $('cp-preview-resultado').style.display = 'none';

        const json = await postForm('previsualizar', fd);
        if (!json.ok) {
            box.textContent = '— No se pudo leer el archivo —';
            alertError('No se pudo previsualizar', json.error);
            return;
        }

        if (tipoArchivo === 'PDF') {
            box.textContent = (json.data.lineas || []).join('\n');
            CP.mostrarResultadoPrueba(json.data.filas_probadas);
        } else {
            box.textContent = (json.data.lineas || [])
                .map((fila, i) => `Fila ${i}: ` + fila.map((v, c) => `[${c}]${v}`).join('  '))
                .join('\n');
        }
    };

    CP.sugerirRegexPdf = async function () {
        const archivo = $('cp-muestra').files[0];
        if (!archivo) {
            alertError('Falta el archivo', 'Selecciona primero un archivo de muestra (PDF).');
            return;
        }

        const btn = $('cp-btn-sugerir-regex');
        const msg = $('cp-sugerencia-msg');
        btn.disabled = true;
        msg.style.display = '';
        msg.className = 'alert alert-info small py-2 mb-3';
        msg.textContent = 'Analizando el PDF…';

        try {
            const fd = new FormData();
            fd.append('archivo', archivo);

            const json = await postForm('sugerirRegex', fd);
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

            $('cp-map-regex-linea').value = s.regex_linea;
            $('cp-formato-fecha').value = s.formato_fecha;
            $('cp-separador').value = s.separador_decimal;

            msg.className = 'alert alert-success small py-2 mb-3';
            msg.textContent = s.mensaje;

            // Muestra de una vez el resultado con el patrón propuesto (sin "Valor es crédito" — hay que revisarlo a mano).
            await CP.previsualizarMuestra();
        } finally {
            btn.disabled = false;
        }
    };

    CP.mostrarResultadoPrueba = function (resultado) {
        const wrap = $('cp-preview-resultado');
        const tbody = $('cp-preview-resultado-tbody');
        if (!resultado) {
            wrap.style.display = 'none';
            return;
        }
        wrap.style.display = '';
        if (resultado.error) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-danger">${esc(resultado.error)}</td></tr>`;
            return;
        }
        if (!resultado.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">El patrón no encontró ninguna línea de datos en este archivo.</td></tr>';
            return;
        }
        tbody.innerHTML = resultado.map((f) => `
            <tr>
                <td>${fmtDate(f.fecha)}</td>
                <td>${esc(f.descripcion)}</td>
                <td class="text-end">${fmtMoney(f.monto)}</td>
                <td>${esc(f.referencia || '')}</td>
            </tr>
        `).join('');
    };

    CP.guardar = async function () {
        const tipoArchivo = $('cp-tipo').value;
        const mapeo = {};

        if (tipoArchivo === 'PDF') {
            const regex = $('cp-map-regex-linea').value.trim();
            if (regex) mapeo.regex_linea = regex;
            const tipoCredito = $('cp-map-tipo-credito').value.trim();
            if (tipoCredito) mapeo.tipo_credito = tipoCredito;
        } else {
            ['fecha', 'descripcion', 'monto', 'referencia'].forEach((campo) => {
                const col = $(`cp-map-${campo}-col`).value;
                if (col !== '') mapeo[campo] = { col: parseInt(col, 10) };
            });
        }

        const json = await postJson('guardar', {
            id: $('cp-id').value || null,
            nombre_perfil: $('cp-nombre').value.trim(),
            id_banco: $('cp-banco').value || null,
            tipo_archivo: tipoArchivo,
            fila_inicio: parseInt($('cp-fila-inicio').value || '0', 10),
            formato_fecha: $('cp-formato-fecha').value.trim() || 'd/m/Y',
            separador_decimal: $('cp-separador').value,
            activo: $('cp-activo').value === '1',
            mapeo_columnas: mapeo,
        });
        if (!json.ok) {
            alertError('No se pudo guardar el perfil', json.error);
            return;
        }
        bootstrap.Modal.getInstance($('cp-modal'))?.hide();
        alertOk('Perfil guardado');
        await CP.recargar();
    };

    CP.eliminar = async function () {
        const id = $('cp-id').value;
        if (!id) return;
        if (!await confirmar('Se eliminará este perfil. Las cargas ya procesadas con él no se afectan, pero dejará de ofrecerse en Conciliación de Cobros.')) return;

        const json = await postJson('eliminar', { id: parseInt(id, 10) });
        if (!json.ok) {
            alertError('No se pudo eliminar el perfil', json.error);
            return;
        }
        bootstrap.Modal.getInstance($('cp-modal'))?.hide();
        alertOk('Perfil eliminado');
        await CP.recargar();
    };

    document.addEventListener('DOMContentLoaded', () => {
        CP.setPerfiles(window.CP_PERFILES || []);
        $('cp-buscar').addEventListener('input', CP.render);
    });
})();
