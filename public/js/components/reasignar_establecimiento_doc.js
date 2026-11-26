/**
 * Reasignar UN documento a otro establecimiento del mismo RUC desde su modal
 * (Compras, Retenciones de venta). Pieza común: el servidor es
 * App\Traits\ReasignarEstablecimientoDocTrait (endpoints verificarReasignacionEstAjax /
 * reasignarEstablecimientoAjax del módulo) y los datos `cfg` salen de su
 * datosReasignarEst() — null si el RUC tiene un solo establecimiento.
 * La reasignación en lote vive en Documentos › Reasignar establecimientos.
 *
 *   CMG_ReasignarEst.actualizar({ cfg, id, idEst, btnId, avisoId, textoId, doc })
 *     Muestra/oculta el botón y el aviso según el documento abierto (id = 0 → nuevo).
 *   CMG_ReasignarEst.abrir({ cfg, id, idEst, urlModulo, doc })
 *     Pide el destino, avisa de los vínculos que se anularían y reasigna.
 *
 * `doc` = nombre del documento en minúscula y femenino ('compra', 'retención').
 */
(function () {
    if (window.CMG_ReasignarEst) return;

    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const etiqueta = (e, conEmpresa) => `${e.codigo} - ${e.nombre}` + (conEmpresa ? ` (${e.empresa_nombre})` : '');
    const variasEmpresas = cfg => new Set(cfg.establecimientos.map(e => Number(e.id_empresa))).size > 1;
    const base = () => (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || ''));

    function actualizar({ cfg, id, idEst, btnId, avisoId, textoId, doc, bloqueo = null }) {
        const btn   = document.getElementById(btnId);
        const aviso = document.getElementById(avisoId);
        const texto = document.getElementById(textoId);
        const guardado = Number(id) > 0;
        const mostrar = !!(cfg && Array.isArray(cfg.establecimientos) && cfg.establecimientos.length > 1 && guardado);
        // `bloqueo`: motivo por el que este documento nunca se reasigna (p. ej. retención
        // atada a una factura): sin botón, y el aviso explica el motivo.
        if (btn)   btn.classList.toggle('d-none', !(mostrar && cfg.puede && !bloqueo));
        if (aviso) aviso.classList.toggle('d-none', !mostrar);
        if (!mostrar || !texto) return;

        const ests = cfg.establecimientos;
        const actual = ests.find(e => Number(e.id) === Number(idEst));
        const actualTxt = actual ? `<strong>${esc(etiqueta(actual, variasEmpresas(cfg)))}</strong>` : '<strong>sin establecimiento</strong>';
        if (bloqueo) {
            texto.innerHTML = `Esta ${esc(doc)} está en el establecimiento ${actualTxt}. ${esc(bloqueo)}`;
            return;
        }
        const modulo = cfg.puede_ver_modulo
            ? `<a href="${base()}/modulos/reasignar-establecimiento" target="_blank" class="alert-link">Documentos › Reasignar establecimientos</a>`
            : '<strong>Documentos › Reasignar establecimientos</strong>';
        const individual = cfg.puede ? ' Puede cambiarla con el botón <i class="bi bi-shop"></i> de arriba.' : '';

        texto.innerHTML = `Esta ${esc(doc)} está en el establecimiento ${actualTxt}; este RUC tiene ${ests.length} establecimientos.${individual}`
            + ` Para reasignaciones masivas use el módulo ${modulo}.`;
    }

    async function abrir({ cfg, id, idEst, urlModulo, doc }) {
        if (!cfg || !(Number(id) > 0)) {
            Swal.fire('Guarde el documento', `Primero guarde la ${doc} para poder reasignarla.`, 'info');
            return;
        }

        // 1) Vínculos que se anularían (asiento, pagos, inventario) o que bloquean (retención).
        let vinc;
        try {
            const r = await fetch(`${urlModulo}/verificarReasignacionEstAjax?id=${encodeURIComponent(id)}`);
            const j = await r.json();
            if (!j.ok) { Swal.fire('No se puede reasignar', j.mensaje || 'Error desconocido.', 'error'); return; }
            vinc = j.data || {};
        } catch (e) {
            Swal.fire('Error de conexión', 'No se pudo contactar al servidor.', 'error');
            return;
        }
        if ((vinc.bloqueados_factura || []).length) {
            Swal.fire('No se puede reasignar', `Esta ${doc} está atada a una factura: no se puede pasar a otro establecimiento.`, 'warning');
            return;
        }
        if ((vinc.bloqueados_retencion || []).length) {
            Swal.fire('No se puede reasignar', `Esta ${doc} tiene una retención emitida. Anule la retención desde su módulo y vuelva a intentar.`, 'warning');
            return;
        }

        // 2) Destinos: todos los establecimientos del RUC menos el actual, agrupados por empresa.
        const ests = cfg.establecimientos.filter(e => Number(e.id) !== Number(idEst));
        let opciones = '<option value="">-- Seleccione --</option>';
        if (variasEmpresas(cfg)) {
            const grupos = new Map();
            ests.forEach(e => {
                const k = Number(e.id_empresa);
                if (!grupos.has(k)) grupos.set(k, { nombre: e.empresa_nombre, items: [] });
                grupos.get(k).items.push(e);
            });
            grupos.forEach((g, idEmp) => {
                const lbl = g.nombre + (idEmp === Number(cfg.id_empresa) ? ' (esta empresa)' : '');
                opciones += `<optgroup label="${esc(lbl)}">`
                    + g.items.map(e => `<option value="${Number(e.id)}">${esc(etiqueta(e, false))}</option>`).join('')
                    + '</optgroup>';
            });
        } else {
            opciones += ests.map(e => `<option value="${Number(e.id)}">${esc(etiqueta(e, false))}</option>`).join('');
        }

        const partes = [];
        if (vinc.con_asiento)    partes.push('el asiento contable');
        if (vinc.con_egresos)    partes.push('los pagos (egresos)');
        if (vinc.con_inventario) partes.push('los movimientos de inventario');
        const tieneVinculos = partes.length > 0;

        const html = `
            <div class="text-start small">
              <label class="form-label fw-semibold mb-1" for="cmgReasigDestino">Establecimiento destino</label>
              <select id="cmgReasigDestino" class="form-select form-select-sm">${opciones}</select>
              <div class="form-text">El número del documento no cambia; solo a qué establecimiento queda atribuida la ${esc(doc)}.</div>
              ${tieneVinculos ? `
              <div class="alert alert-warning py-2 px-2 mt-2 mb-0">
                Esta ${esc(doc)} tiene ${partes.join(', ')}. Para reasignarla se anularán y deberá volver a generarlos en el destino.
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" id="cmgReasigAnular">
                  <label class="form-check-label" for="cmgReasigAnular">Sí, anular y reasignar</label>
                </div>
              </div>` : ''}
            </div>`;

        const conf = await Swal.fire({
            title: 'Reasignar establecimiento',
            html,
            showCancelButton: true,
            confirmButtonText: 'Reasignar',
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            preConfirm: () => {
                const dest = document.getElementById('cmgReasigDestino')?.value;
                if (!dest) { Swal.showValidationMessage('Seleccione el establecimiento destino.'); return false; }
                const anular = !!document.getElementById('cmgReasigAnular')?.checked;
                if (tieneVinculos && !anular) { Swal.showValidationMessage('Confirme que desea anular los documentos vinculados.'); return false; }
                return { dest, anular };
            }
        });
        if (!conf.isConfirmed || !conf.value) return;

        // 3) Reasignar.
        const fd = new FormData();
        fd.append('id', id);
        fd.append('id_establecimiento_destino', conf.value.dest);
        if (conf.value.anular) fd.append('anular_vinculos', '1');
        Swal.fire({ title: 'Reasignando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const res  = await fetch(`${urlModulo}/reasignarEstablecimientoAjax`, { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.ok) {
                Swal.fire('No se pudo reasignar', json.mensaje || 'Error desconocido.', 'error');
                return;
            }
            const destino = cfg.establecimientos.find(e => String(e.id) === String(conf.value.dest));
            const otraEmpresa = destino && Number(destino.id_empresa) !== Number(cfg.id_empresa);
            await Swal.fire({
                icon: 'success',
                title: 'Reasignada',
                text: otraEmpresa
                    ? `La ${doc} pasó a ${destino.empresa_nombre}: ya no aparecerá en el listado de esta empresa.`
                    : json.mensaje,
                timer: otraEmpresa ? undefined : 1600,
                showConfirmButton: otraEmpresa
            });
            window.location.reload();
        } catch (e) {
            Swal.fire('Error de conexión', 'No se pudo contactar al servidor.', 'error');
        }
    }

    window.CMG_ReasignarEst = { actualizar, abrir };
})();
