<?php
/**
 * Líneas de centro de costo o de proyecto de una cuenta (pestañas "Centro de costos" y
 * "Proyectos" del modal de Plan de Cuentas), con el diseño de las tablas de líneas de los
 * documentos (p. ej. Info Adicional de Facturas de Venta): filas editables + "Agregar línea".
 * Lo incluye _form_cuenta.php una vez por tipo:
 *
 *   $pcdimTipo     'centro' | 'proyecto'
 *   $pcdimOpciones centros de costo / proyectos activos (id, codigo, nombre[, fecha_inicio, fecha_fin])
 *
 * Las líneas se guardan con el botón Guardar de la cuenta (window.PCDIM_guardar), todas
 * juntas. Es la sugerencia que se precarga en las líneas de los asientos según su fecha;
 * cambiarla nunca toca los asientos ya registrados.
 */
$pcdimTipo     = $pcdimTipo === 'proyecto' ? 'proyecto' : 'centro';
$pcdimOpciones = $pcdimOpciones ?? [];
$pcdimFecha    = static fn($v) => !empty($v) ? substr((string) $v, 0, 10) : '';
?>
<div class="pcdim-bloque" data-tipo="<?= $pcdimTipo ?>">
    <div class="form-text mt-0 mb-2" style="font-size:.7rem;">
        <?= $pcdimTipo === 'proyecto'
            ? 'El proyecto de cada periodo se sugiere en las líneas de los asientos según su fecha. Al elegir un proyecto se completan sus fechas de inicio y fin; puede ajustarlas.'
            : 'El centro de costo de cada periodo se sugiere en las líneas de los asientos según su fecha.' ?>
        Fechas vacías = sin límite. Cambiarlo no modifica los asientos ya registrados.
    </div>

    <div class="small text-warning d-none pcdim-sin-tabla"><i class="bi bi-exclamation-triangle me-1"></i>Falta aplicar la migración 20261028_plan_cuentas_dimension_vigencia.sql.</div>

    <div class="border rounded-2 overflow-hidden bg-white mt-1 pcdim-cuerpo">
        <div class="table-responsive" style="max-height: 220px;">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-2 py-0 small fw-bold text-muted" style="width: 50%;"><?= $pcdimTipo === 'proyecto' ? 'Proyecto' : 'Centro de costo' ?></th>
                        <th class="py-0 small fw-bold text-muted" style="width: 20%;">Desde</th>
                        <th class="py-0 small fw-bold text-muted" style="width: 20%;">Hasta</th>
                        <th class="py-0" style="width: 10%;"></th>
                    </tr>
                </thead>
                <tbody class="pcdim-tbody"></tbody>
            </table>
        </div>
        <div class="p-1 border-top bg-light pcdim-agregar">
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2 pcdim-btn-agregar">
                <i class="bi bi-plus-circle me-1"></i> Agregar línea
            </button>
        </div>
    </div>

    <template class="pcdim-opciones">
        <option value="">— Seleccionar —</option>
        <?php foreach ($pcdimOpciones as $o): ?>
            <option value="<?= (int) $o['id'] ?>"
                    data-inicio="<?= htmlspecialchars($pcdimFecha($o['fecha_inicio'] ?? null)) ?>"
                    data-fin="<?= htmlspecialchars($pcdimFecha($o['fecha_fin'] ?? null)) ?>"><?= htmlspecialchars(trim(($o['codigo'] ?? '') . ' ' . $o['nombre'])) ?></option>
        <?php endforeach; ?>
    </template>
</div>

<?php if (empty($GLOBALS['__pcdim_js'])): $GLOBALS['__pcdim_js'] = true; ?>
<script>
(function () {
    'use strict';
    if (window.__CMG_pcdim) return;
    window.__CMG_pcdim = true;

    const URL_PC = '<?= BASE_URL ?>/modulos/plan-cuentas';
    const ESTILO = 'padding:0 4px;height:20px;font-size:0.78rem;';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const bloques = [];
    let disponible = true;
    let activo = false; // solo mientras el modal muestra una cuenta de nivel 5

    function crearBloque(root) {
        const tipo = root.dataset.tipo;
        const tbody = root.querySelector('.pcdim-tbody');
        const opcionesHtml = root.querySelector('.pcdim-opciones').innerHTML;
        const b = { tipo, soloLectura: false };

        function filaVacia() {
            const cols = 4;
            tbody.innerHTML = `<tr class="pcdim-vacia"><td colspan="${cols}" class="text-center text-muted small py-2">Sin líneas registradas.</td></tr>`;
        }

        // r = periodo guardado (o null para una línea nueva).
        b.agregar = function (r = null) {
            tbody.querySelector('.pcdim-vacia')?.remove();
            const tr = document.createElement('tr');
            tr.dataset.id = r ? r.id : 0;
            if (r && r.vigente_hoy) {
                tr.className = 'table-success';
                tr.title = 'Vigente hoy';
            }
            tr.innerHTML = `
                <td class="p-0"><select class="form-select form-select-sm border-0 bg-transparent pcdim-dimension" style="${ESTILO}">${opcionesHtml}</select></td>
                <td class="p-0"><input type="date" class="form-control form-control-sm border-0 bg-transparent pcdim-desde" style="${ESTILO}" title="Vacío = sin fecha de inicio"></td>
                <td class="p-0"><input type="date" class="form-control form-control-sm border-0 bg-transparent pcdim-hasta" style="${ESTILO}" title="Vacío = sin fecha de fin"></td>
                <td class="p-0 text-center pe-1">
                    <button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none pcdim-quitar" title="Quitar línea"><i class="bi bi-x-circle-fill"></i></button>
                </td>`;
            const sel = tr.querySelector('.pcdim-dimension');
            if (r) {
                const idDim = String((tipo === 'proyecto' ? r.id_proyecto : r.id_centro_costo) || '');
                // Un centro / proyecto inactivo no está en la lista de activos: se agrega para no perderlo.
                if (idDim && !sel.querySelector(`option[value="${idDim}"]`)) {
                    const cod = tipo === 'proyecto' ? r.proyecto_codigo : r.centro_codigo;
                    const nom = tipo === 'proyecto' ? r.proyecto_nombre : r.centro_nombre;
                    sel.insertAdjacentHTML('beforeend', `<option value="${esc(idDim)}">${esc(((cod || '') + ' ' + (nom || '')).trim())} (inactivo)</option>`);
                }
                sel.value = idDim;
                tr.querySelector('.pcdim-desde').value = r.vigente_desde || '';
                tr.querySelector('.pcdim-hasta').value = r.vigente_hasta || '';
            }
            // Proyecto: al elegirlo se completan sus fechas de vigencia (el usuario puede ajustarlas).
            if (tipo === 'proyecto') {
                sel.addEventListener('change', () => {
                    const op = sel.selectedOptions[0];
                    if (!op || !op.value) return;
                    tr.querySelector('.pcdim-desde').value = op.dataset.inicio || '';
                    tr.querySelector('.pcdim-hasta').value = op.dataset.fin || '';
                });
            }
            tr.querySelector('.pcdim-quitar').addEventListener('click', () => {
                tr.remove();
                if (!tbody.querySelector('tr')) filaVacia();
            });
            if (b.soloLectura) {
                tr.querySelectorAll('select, input').forEach(el => { el.disabled = true; });
                tr.querySelector('.pcdim-quitar').classList.add('d-none');
            }
            tbody.appendChild(tr);
            if (!r) sel.focus();
        };

        root.querySelector('.pcdim-btn-agregar').addEventListener('click', () => b.agregar());

        b.cargar = async function (idCuenta, soloLectura) {
            b.soloLectura = !!soloLectura;
            root.querySelector('.pcdim-agregar').classList.toggle('d-none', b.soloLectura);
            filaVacia();
            const resp = await fetch(`${URL_PC}/dimensionesAjax?tipo=${tipo}&id_cuenta=${idCuenta}`);
            const json = await resp.json();
            if (!json.ok) throw new Error(json.error || 'No se pudo cargar las líneas.');
            disponible = !!json.disponible;
            root.querySelector('.pcdim-sin-tabla').classList.toggle('d-none', disponible);
            root.querySelector('.pcdim-cuerpo').classList.toggle('d-none', !disponible);
            (json.data || []).forEach(r => b.agregar(r));
        };

        // Líneas para guardar. Una línea completamente vacía se ignora; una con fechas pero
        // sin centro / proyecto se envía para que el servidor diga qué falta.
        b.lineas = function () {
            const out = [];
            tbody.querySelectorAll('tr:not(.pcdim-vacia)').forEach(tr => {
                const l = {
                    id: parseInt(tr.dataset.id || 0, 10),
                    id_dimension: tr.querySelector('.pcdim-dimension').value,
                    vigente_desde: tr.querySelector('.pcdim-desde').value,
                    vigente_hasta: tr.querySelector('.pcdim-hasta').value,
                };
                if (!l.id && !l.id_dimension && !l.vigente_desde && !l.vigente_hasta) return;
                out.push(l);
            });
            return out;
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
        activo = true;
        return Promise.all(bloques.map(b => b.cargar(parseInt(idCuenta || 0, 10), soloLectura).catch(e => console.error(e))));
    };

    /**
     * Después de guardar: si hay asientos ya registrados de la cuenta con el centro de costo /
     * proyecto VACÍO dentro de sus periodos, pregunta si completarlos. Devuelve true si mostró
     * algún aviso (el llamador entonces no muestra su "Guardado").
     */
    window.PCDIM_ofrecerRelleno = async function (idCuenta) {
        if (!activo || !idCuenta || !disponible) return false;
        let info;
        try {
            const resp = await fetch(`${URL_PC}/lineasPorRellenarAjax?id_cuenta=${idCuenta}`);
            const json = await resp.json();
            if (!json.ok) return false;
            info = json.data;
        } catch (e) { return false; }

        const n = (t, k) => (info[t] && info[t][k]) || 0;
        const total = n('centro', 'aplicables') + n('proyecto', 'aplicables');
        if (total === 0) return false;

        const filas = [];
        if (n('centro', 'aplicables'))   filas.push(`<li><b>${n('centro', 'aplicables')}</b> sin centro de costo</li>`);
        if (n('proyecto', 'aplicables')) filas.push(`<li><b>${n('proyecto', 'aplicables')}</b> sin proyecto</li>`);
        const cerrados = n('centro', 'en_cerrado') + n('proyecto', 'en_cerrado');
        const r = await Swal.fire({
            icon: 'question',
            title: 'Cuenta guardada',
            html: `<div class="text-start small">
                    Esta cuenta ya tiene líneas de asientos dentro de sus periodos:
                    <ul class="mb-2 mt-1">${filas.join('')}</ul>
                    ¿Asignarles el centro de costo / proyecto de su periodo?
                    <div class="text-muted mt-2" style="font-size:.75rem;">
                        Solo se completan las líneas vacías: las que ya tienen un centro de costo o proyecto no cambian.
                        No se tocan asientos anulados, de cierre o apertura del ejercicio ni de períodos cerrados${cerrados ? ` (${cerrados} línea${cerrados === 1 ? '' : 's'} en períodos cerrados quedan como están)` : ''}.
                    </div>
                   </div>`,
            showCancelButton: true,
            confirmButtonText: 'Sí, asignar',
            cancelButtonText: 'No, solo los nuevos'
        });
        if (!r.isConfirmed) return true;

        try {
            const fd = new FormData();
            fd.append('id_cuenta', idCuenta);
            const resp = await fetch(`${URL_PC}/rellenarAsientosAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire('No se pudo asignar', json.error || 'Error al completar los asientos.', 'warning');
                return true;
            }
            const hechas = (json.data.centro || 0) + (json.data.proyecto || 0);
            Swal.fire({ icon: 'success', title: 'Asientos actualizados', text: `Se completaron ${hechas} línea${hechas === 1 ? '' : 's'} de asientos.`, timer: 2200, showConfirmButton: false });
        } catch (e) {
            Swal.fire('Error', 'Error de conexión', 'error');
        }
        return true;
    };

    /** La cuenta abierta no es de nivel 5: no hay líneas que guardar. */
    window.PCDIM_desactivar = function () { activo = false; };

    /**
     * Guarda las líneas de las dos pestañas de la cuenta (después de guardar la cuenta, que
     * ya tiene id). Devuelve {ok, error}. Sin pestañas, sin la migración o en solo lectura
     * no hace nada.
     */
    window.PCDIM_guardar = async function (idCuenta) {
        asegurarBloques();
        const editables = bloques.filter(b => !b.soloLectura);
        if (!activo || !idCuenta || !disponible || !editables.length) return { ok: true };
        const dims = {};
        editables.forEach(b => { dims[b.tipo] = b.lineas(); });
        const fd = new FormData();
        fd.append('id_cuenta', idCuenta);
        fd.append('dimensiones', JSON.stringify(dims));
        try {
            const resp = await fetch(`${URL_PC}/sincronizarDimensionesAjax`, { method: 'POST', body: fd });
            return await resp.json();
        } catch (e) {
            return { ok: false, error: 'Error de conexión al guardar centros de costo / proyectos.' };
        }
    };
})();
</script>
<?php endif; ?>
