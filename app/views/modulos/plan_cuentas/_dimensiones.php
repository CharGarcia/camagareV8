<?php
/**
 * Periodos de centro de costo o de proyecto de una cuenta (pestañas "Centro de costos" y
 * "Proyectos" del modal de Plan de Cuentas). Lo incluye _form_cuenta.php una vez por tipo:
 *
 *   $pcdimTipo     'centro' | 'proyecto'
 *   $pcdimOpciones lista de centros de costo / proyectos activos (id, codigo, nombre[, fecha_inicio, fecha_fin])
 *
 * Es la sugerencia que se precarga en las líneas de los asientos según la fecha del asiento;
 * cambiarla nunca toca los asientos ya registrados. Se llena con
 * window.PCDIM_cargar(idCuenta, soloLectura), que recarga los dos tipos.
 */
$pcdimTipo     = $pcdimTipo === 'proyecto' ? 'proyecto' : 'centro';
$pcdimOpciones = $pcdimOpciones ?? [];
$pcdimP        = 'pcdim_' . $pcdimTipo . '_';
$pcdimNombre   = $pcdimTipo === 'proyecto' ? 'Proyecto' : 'Centro de costo';
$pcdimFecha    = static fn($v) => !empty($v) ? substr((string) $v, 0, 10) : '';
?>
<div id="<?= $pcdimP ?>wrapper" class="pcdim-bloque" data-tipo="<?= $pcdimTipo ?>">
    <div class="form-text mt-0 mb-2" style="font-size:.7rem;">
        <?= $pcdimTipo === 'proyecto'
            ? 'El proyecto del periodo se sugiere en las líneas de los asientos según su fecha. Al elegir un proyecto se completan sus fechas de inicio y fin; puede ajustarlas.'
            : 'El centro de costo del periodo se sugiere en las líneas de los asientos según su fecha.' ?>
        Cambiarlo para otro periodo no modifica los asientos ya registrados.
    </div>

    <div class="small text-muted fst-italic d-none pcdim-sin-cuenta">Guarde la cuenta para asignarle <?= $pcdimTipo === 'proyecto' ? 'un proyecto' : 'un centro de costo' ?>.</div>
    <div class="small text-warning d-none pcdim-sin-tabla"><i class="bi bi-exclamation-triangle me-1"></i>Falta aplicar la migración 20261028_plan_cuentas_dimension_vigencia.sql.</div>

    <div class="pcdim-cuerpo d-none">
        <div class="table-responsive border rounded">
            <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.75rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width:120px;">Desde</th>
                        <th style="width:150px;">Hasta</th>
                        <th><?= $pcdimNombre ?></th>
                        <th class="pcdim-acciones" style="width:52px;"></th>
                    </tr>
                </thead>
                <tbody class="pcdim-tbody"></tbody>
                <tfoot class="pcdim-form d-none">
                    <tr>
                        <td class="p-0"><input type="date" class="form-control form-control-sm border-0 pcdim-desde" style="padding:0 4px;height:24px;font-size:.75rem;" title="Vacío = sin fecha de inicio"></td>
                        <td class="p-0"><input type="date" class="form-control form-control-sm border-0 pcdim-hasta" style="padding:0 4px;height:24px;font-size:.75rem;" title="Vacío = sin fecha de fin"></td>
                        <td class="p-0">
                            <select class="form-select form-select-sm border-0 pcdim-dimension" style="padding:0 4px;height:24px;font-size:.75rem;">
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($pcdimOpciones as $o): ?>
                                    <option value="<?= (int) $o['id'] ?>"
                                            data-inicio="<?= htmlspecialchars($pcdimFecha($o['fecha_inicio'] ?? null)) ?>"
                                            data-fin="<?= htmlspecialchars($pcdimFecha($o['fecha_fin'] ?? null)) ?>"><?= htmlspecialchars(trim(($o['codigo'] ?? '') . ' ' . $o['nombre'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="p-0 text-center text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-success border-0 py-0 px-1 pcdim-btn-guardar" title="Guardar periodo"><i class="bi bi-check-lg"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 d-none pcdim-btn-cancelar" title="Cancelar edición"><i class="bi bi-x"></i></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="form-text" style="font-size:.65rem;">Fechas vacías = sin límite. Los periodos de <?= $pcdimTipo === 'proyecto' ? 'proyecto' : 'centro de costo' ?> de una cuenta no pueden cruzarse.</div>
    </div>
</div>

<?php if (empty($GLOBALS['__pcdim_js'])): $GLOBALS['__pcdim_js'] = true; ?>
<script>
(function () {
    'use strict';
    if (window.__CMG_pcdim) return;
    window.__CMG_pcdim = true;

    const URL_PC = '<?= BASE_URL ?>/modulos/plan-cuentas';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let idCuentaActual = 0;
    const bloques = [];

    // Un bloque por tipo (centro / proyecto), cada uno con su tabla y su fila de edición.
    function crearBloque(root) {
        const tipo = root.dataset.tipo;
        const q = (s) => root.querySelector(s);
        const b = { tipo, idEditando: 0, guardando: false };
        const sel = q('.pcdim-dimension');

        b.limpiar = function () {
            b.idEditando = 0;
            q('.pcdim-desde').value = '';
            q('.pcdim-hasta').value = '';
            sel.value = '';
            q('.pcdim-btn-cancelar').classList.add('d-none');
        };

        // Proyecto: al elegirlo se completan sus fechas de vigencia (el usuario puede ajustarlas).
        if (tipo === 'proyecto') {
            sel.addEventListener('change', () => {
                const op = sel.selectedOptions[0];
                if (!op || !op.value) return;
                q('.pcdim-desde').value = op.dataset.inicio || '';
                q('.pcdim-hasta').value = op.dataset.fin || '';
            });
        }

        function pintar(rows, editable) {
            const tb = q('.pcdim-tbody');
            if (!rows.length) {
                tb.innerHTML = `<tr><td colspan="${editable ? 4 : 3}" class="text-center text-muted py-2">Sin periodos registrados.</td></tr>`;
                return;
            }
            tb.innerHTML = rows.map(r => {
                const cod = tipo === 'proyecto' ? r.proyecto_codigo : r.centro_codigo;
                const nom = tipo === 'proyecto' ? r.proyecto_nombre : r.centro_nombre;
                return `
                <tr data-id="${r.id}" class="${r.vigente_hoy ? 'table-success' : ''}">
                    <td>${r.desde_txt || '<span class="text-muted">Sin inicio</span>'}</td>
                    <td>${r.hasta_txt || '<span class="text-muted">Sin fin</span>'}${r.vigente_hoy ? ' <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Vigente</span>' : ''}</td>
                    <td>${esc(((cod || '') + ' ' + (nom || '')).trim())}</td>
                    ${editable ? `<td class="text-center text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary border-0 py-0 px-1 pcdim-editar" title="Editar"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 py-0 px-1 pcdim-eliminar" title="Eliminar"><i class="bi bi-trash"></i></button>
                    </td>` : ''}
                </tr>`;
            }).join('');
            tb.querySelectorAll('tr[data-id]').forEach(tr => {
                const r = rows.find(x => String(x.id) === tr.dataset.id);
                tr.querySelector('.pcdim-editar')?.addEventListener('click', () => {
                    b.idEditando = r.id;
                    q('.pcdim-desde').value = r.vigente_desde || '';
                    q('.pcdim-hasta').value = r.vigente_hasta || '';
                    sel.value = (tipo === 'proyecto' ? r.id_proyecto : r.id_centro_costo) || '';
                    q('.pcdim-btn-cancelar').classList.remove('d-none');
                });
                tr.querySelector('.pcdim-eliminar')?.addEventListener('click', () => b.eliminar(r.id));
            });
        }

        b.recargar = async function (soloLectura) {
            const resp = await fetch(`${URL_PC}/dimensionesAjax?tipo=${tipo}&id_cuenta=${idCuentaActual}`);
            const json = await resp.json();
            if (!json.ok) throw new Error(json.error || 'No se pudo cargar los periodos.');
            if (!json.disponible) {
                q('.pcdim-sin-tabla').classList.remove('d-none');
                q('.pcdim-cuerpo').classList.add('d-none');
                return;
            }
            const editable = !soloLectura && json.puede_editar;
            b.soloLectura = !editable;
            q('.pcdim-cuerpo').classList.remove('d-none');
            q('.pcdim-form').classList.toggle('d-none', !editable);
            root.querySelectorAll('.pcdim-acciones').forEach(el => el.classList.toggle('d-none', !editable));
            pintar(json.data || [], editable);
        };

        b.cargar = async function (soloLectura) {
            b.limpiar();
            q('.pcdim-sin-tabla').classList.add('d-none');
            q('.pcdim-tbody').innerHTML = '';
            q('.pcdim-sin-cuenta').classList.toggle('d-none', idCuentaActual > 0);
            q('.pcdim-cuerpo').classList.add('d-none');
            if (idCuentaActual <= 0) return;
            try { await b.recargar(soloLectura); } catch (e) { console.error(e); }
        };

        q('.pcdim-btn-cancelar').addEventListener('click', b.limpiar);

        q('.pcdim-btn-guardar').addEventListener('click', async function () {
            if (b.guardando || idCuentaActual <= 0) return;
            b.guardando = true;
            this.disabled = true;
            try {
                const fd = new FormData();
                fd.append('id', b.idEditando || 0);
                fd.append('tipo', tipo);
                fd.append('id_cuenta', idCuentaActual);
                fd.append('id_dimension', sel.value);
                fd.append('vigente_desde', q('.pcdim-desde').value);
                fd.append('vigente_hasta', q('.pcdim-hasta').value);
                const resp = await fetch(`${URL_PC}/guardarDimensionAjax`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) {
                    Swal.fire('No se pudo guardar', json.error || 'Error al guardar el periodo.', 'warning');
                    return;
                }
                b.limpiar();
                await b.recargar(false);
            } catch (e) {
                Swal.fire('Error', 'Error de conexión', 'error');
            } finally {
                b.guardando = false;
                this.disabled = false;
            }
        });

        b.eliminar = async function (id) {
            const r = await Swal.fire({
                title: '¿Eliminar periodo?',
                text: 'Los asientos ya registrados conservan su centro de costo / proyecto.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
            });
            if (!r.isConfirmed) return;
            try {
                const fd = new FormData();
                fd.append('id', id);
                const resp = await fetch(`${URL_PC}/eliminarDimensionAjax`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) { Swal.fire('No se pudo eliminar', json.error, 'warning'); return; }
                if (b.idEditando === id) b.limpiar();
                await b.recargar(false);
            } catch (e) {
                Swal.fire('Error', 'Error de conexión', 'error');
            }
        };

        return b;
    }

    function asegurarBloques() {
        document.querySelectorAll('.pcdim-bloque').forEach(root => {
            if (root.__pcdim) return;
            root.__pcdim = crearBloque(root);
            bloques.push(root.__pcdim);
        });
    }

    /** Lo llama el modal de cuenta al abrir. idCuenta = 0 → cuenta nueva (aún sin guardar). */
    window.PCDIM_cargar = function (idCuenta, soloLectura) {
        asegurarBloques();
        idCuentaActual = parseInt(idCuenta || 0, 10);
        return Promise.all(bloques.map(b => b.cargar(soloLectura)));
    };
})();
</script>
<?php endif; ?>
