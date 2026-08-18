<?php

/** @var array $perm */
/** @var array $aprobaciones  Aprobaciones que la empresa ya configuró */
/** @var array $disponibles   Checkpoints del catálogo aún no configurados */
/** @var array $usuarios      Usuarios asignados a la empresa */
/** @var array $vistaConfig */
/** @var string $rutaModulo */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$aprobaciones = $aprobaciones ?? [];
$disponibles  = $disponibles ?? [];
$usuarios     = $usuarios ?? [];

// Nombre/ícono visible por módulo (fallback: humaniza la ruta MVC si no está mapeado).
$modulosInfo = [
    'modulos/cargas-inventario' => ['nombre' => 'Inventario',  'icono' => 'bi-box-seam',       'color' => 'text-primary'],
    'modulos/importaciones'     => ['nombre' => 'Inventario',  'icono' => 'bi-airplane',       'color' => 'text-primary'],
    'modulos/transferencias'    => ['nombre' => 'Tesorería',   'icono' => 'bi-bank',           'color' => 'text-success'],
    'modulos/compras'           => ['nombre' => 'Compras',     'icono' => 'bi-cart3',          'color' => 'text-warning'],
    'modulos/roles-pago'        => ['nombre' => 'Nómina',      'icono' => 'bi-people',         'color' => 'text-info'],
    'modulos/factura-venta'     => ['nombre' => 'Ventas',      'icono' => 'bi-receipt',        'color' => 'text-danger'],
];
$infoModulo = static function (string $ruta) use ($modulosInfo): array {
    if (isset($modulosInfo[$ruta])) return $modulosInfo[$ruta];
    $slug = preg_replace('#^modulos/#', '', $ruta);
    return ['nombre' => ucwords(str_replace(['-', '_'], ' ', $slug)), 'icono' => 'bi-diagram-3', 'color' => 'text-secondary'];
};

echo \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []);
?>

<style>
    /* Campo de aprobadores: se ve como un input pero contiene los chips + el buscador. */
    .apr-chips-wrap { min-height: 34px; cursor: text; }
    .apr-chips-wrap .form-control { box-shadow: none; }
    .apr-dropdown { z-index: 5090; max-height: 220px; overflow: auto; }
    .apr-vacio-ico { font-size: 2.4rem; opacity: .25; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 px-1">
    <h5 class="mb-0 fw-bold text-dark">
        <i class="bi bi-check2-square text-primary me-2"></i><?= htmlspecialchars($titulo ?? 'Aprobaciones') ?>
    </h5>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3 w-100">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted small">
            Cada fila es un proceso que exige aprobación antes de ejecutarse, con sus propios aprobadores.
        </span>
        <div class="d-flex align-items-center gap-2">
            <?php
            $columnasTabla = [
                'modulo'      => 'Módulo',
                'proceso'     => 'Proceso',
                'aprobadores' => 'Aprobadores',
                'umbral'      => 'Monto mínimo',
                'estado'      => 'Estado',
            ];
            ?>
            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
            </div>
            <?php if (!empty($perm['crear'])): ?>
                <button type="button" class="btn btn-primary btn-sm" id="apr-btn-nueva">
                    <i class="bi bi-plus-lg me-1"></i>Nueva aprobación
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="aprobaciones-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2" data-col="modulo" style="width:150px;">Módulo</th>
                        <th data-col="proceso" style="min-width:260px;">Proceso</th>
                        <th data-col="aprobadores" style="min-width:280px;">Aprobadores</th>
                        <th class="text-end" data-col="umbral" style="width:130px;">Monto mínimo</th>
                        <th class="text-center" data-col="estado" style="width:100px;">Estado</th>
                        <th class="text-end pe-3" style="width:70px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($aprobaciones)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-check2-square d-block mb-2 apr-vacio-ico"></i>
                                <div class="text-muted">Todavía no hay aprobaciones configuradas en esta empresa.</div>
                                <?php if (!empty($perm['crear'])): ?>
                                    <div class="text-muted small mt-1">
                                        Usa <strong>Nueva aprobación</strong> para elegir un proceso y sus aprobadores.
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: foreach ($aprobaciones as $a):
                        $idTipo = (int) $a['id_tipo'];
                        $info   = $infoModulo($a['modulo_ruta'] ?? '');
                        $activa = !empty($a['requiere_aprobacion']);
                    ?>
                        <tr data-tipo="<?= $idTipo ?>">
                            <td class="ps-3" data-col="modulo">
                                <i class="bi <?= $info['icono'] ?> <?= $info['color'] ?> me-1"></i><?= htmlspecialchars($info['nombre']) ?>
                            </td>
                            <td data-col="proceso" style="max-width:340px;">
                                <div class="fw-medium"><?= htmlspecialchars($a['nombre']) ?></div>
                                <?php if (!empty($a['descripcion'])): ?>
                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($a['descripcion']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-col="aprobadores">
                                <div class="d-flex flex-wrap gap-1" id="apr-lista-<?= $idTipo ?>"></div>
                            </td>
                            <td class="text-end" data-col="umbral">
                                <?php if ($a['umbral_monto'] !== null && $a['umbral_monto'] !== ''): ?>
                                    <span class="fw-medium">$ <?= number_format((float) $a['umbral_monto'], 2) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Siempre</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" data-col="estado">
                                <span class="badge <?= $activa ? 'bg-success' : 'bg-secondary' ?> bg-opacity-10 <?= $activa ? 'text-success' : 'text-secondary' ?> border <?= $activa ? 'border-success' : 'border-secondary' ?>">
                                    <?= $activa ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <?php if (!empty($perm['actualizar']) || !empty($perm['eliminar'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary border-0 apr-editar"
                                            data-tipo="<?= $idTipo ?>" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Nueva / Editar aprobación -->
<div class="modal fade" id="modalAprobacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-bottom py-2 px-3">
                <h6 class="modal-title fw-bold" id="apr-modal-titulo">Nueva aprobación</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body pt-3">
                <input type="hidden" id="apr-id-tipo" value="">

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1" for="apr-proceso">Proceso <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="apr-proceso"></select>
                    <div class="text-muted mt-1" id="apr-proceso-desc" style="font-size:.72rem;"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1" for="apr-buscar">
                        Aprobadores <span class="text-danger">*</span>
                        <span class="text-muted fw-normal">(uno o varios)</span>
                    </label>
                    <div class="position-relative">
                        <div class="form-control form-control-sm d-flex flex-wrap align-items-center gap-1 apr-chips-wrap" id="apr-wrap">
                            <div class="d-flex flex-wrap gap-1" id="apr-chips"></div>
                            <input type="text" class="border-0 flex-grow-1 p-0" id="apr-buscar"
                                   placeholder="Buscar usuario…" autocomplete="off" style="outline:none; min-width:110px;">
                        </div>
                        <div class="list-group shadow-sm d-none position-absolute w-100 apr-dropdown" id="apr-dropdown"></div>
                    </div>
                    <div class="text-muted mt-1" style="font-size:.72rem;">
                        Cualquiera de ellos puede aprobar. Los superadministradores siempre pueden aprobar.
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-7">
                        <label class="form-label small fw-bold mb-1" for="apr-umbral">Monto mínimo</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="apr-umbral" placeholder="Sin mínimo">
                        </div>
                        <div class="text-muted mt-1" style="font-size:.72rem;">
                            Vacío = siempre pide aprobación.
                        </div>
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-bold mb-1 d-block">Estado</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="apr-activa" checked>
                            <label class="form-check-label small" for="apr-activa">Activa</label>
                        </div>
                        <div class="text-muted mt-1" style="font-size:.72rem;">
                            Inactiva = no pide aprobación, pero conserva la configuración.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white border-top py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-danger" id="apr-btn-eliminar">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" id="apr-btn-guardar">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const APR_URL = '<?= $urlBase ?>';
    const APR_PERM = {
        crear:      <?= !empty($perm['crear']) ? 'true' : 'false' ?>,
        actualizar: <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>,
        eliminar:   <?= !empty($perm['eliminar']) ? 'true' : 'false' ?>
    };
    const APR_USUARIOS = <?= json_encode(array_map(static fn($u) => ['id' => (int) $u['id'], 'nombre' => $u['nombre']], $usuarios), JSON_UNESCAPED_UNICODE) ?>;
    // Checkpoints del catálogo que aún no se configuran en esta empresa.
    const APR_DISPONIBLES = <?= json_encode(array_map(static fn($t) => [
        'id' => (int) $t['id'], 'nombre' => $t['nombre'], 'descripcion' => $t['descripcion'] ?? '',
    ], $disponibles), JSON_UNESCAPED_UNICODE) ?>;
    // Aprobaciones ya configuradas, indexadas por id_tipo (para abrir el modal en edición).
    const APR_CONFIGURADAS = <?php
        $mapaConfig = [];
        foreach ($aprobaciones as $a) {
            $mapaConfig[(int) $a['id_tipo']] = [
                'id'          => (int) $a['id_tipo'],
                'nombre'      => $a['nombre'],
                'descripcion' => $a['descripcion'] ?? '',
                'aprobadores' => array_values(array_map('intval', $a['usuarios_aprobadores'])),
                'umbral'      => $a['umbral_monto'],
                'activa'      => !empty($a['requiere_aprobacion']),
            ];
        }
        // Siempre objeto: con el array vacío json_encode devolvería [] y el
        // acceso por id_tipo dejaría de leerse como mapa.
        echo empty($mapaConfig) ? '{}' : json_encode($mapaConfig, JSON_UNESCAPED_UNICODE);
    ?>;

    // Aprobadores seleccionados en el modal abierto.
    let APR_SELECCION = new Set();
    let APR_MODAL = null;

    function aprEscapar(txt) {
        return String(txt ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }

    function aprNombreUsuario(id) {
        const u = APR_USUARIOS.find(x => x.id === id);
        return u ? u.nombre : ('Usuario #' + id);
    }

    function aprChip(nombre, conX) {
        return '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary d-inline-flex align-items-center gap-1" style="font-weight:500;">'
            + aprEscapar(nombre)
            + (conX ? ' <i class="bi bi-x-lg" style="cursor:pointer; font-size:.65rem;"></i>' : '')
            + '</span>';
    }

    // ─── Listado: pinta los chips de aprobadores de cada fila ───────────────────
    function aprPintarListado() {
        Object.values(APR_CONFIGURADAS).forEach(cfg => {
            const cont = document.getElementById('apr-lista-' + cfg.id);
            if (!cont) return;
            cont.innerHTML = cfg.aprobadores.length
                ? cfg.aprobadores.map(uid => aprChip(aprNombreUsuario(uid), false)).join('')
                : '<span class="text-muted small">Sin aprobadores</span>';
        });
    }

    // ─── Modal ─────────────────────────────────────────────────────────────────
    function aprRenderChips() {
        const cont = document.getElementById('apr-chips');
        cont.innerHTML = '';
        APR_SELECCION.forEach(uid => {
            const wrap = document.createElement('span');
            wrap.innerHTML = aprChip(aprNombreUsuario(uid), true);
            const chip = wrap.firstElementChild;
            chip.querySelector('i').addEventListener('click', () => {
                APR_SELECCION.delete(uid);
                aprRenderChips();
            });
            cont.appendChild(chip);
        });
    }

    function aprMostrarDescripcion() {
        const sel = document.getElementById('apr-proceso');
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('apr-proceso-desc').textContent = opt ? (opt.dataset.desc || '') : '';
    }

    /**
     * Abre el modal. Sin idTipo = alta: el select ofrece solo los procesos que
     * la empresa aún no configuró. Con idTipo = edición: el proceso queda fijo.
     */
    function aprAbrirModal(idTipo) {
        const sel = document.getElementById('apr-proceso');
        const esEdicion = !!idTipo;
        const cfg = esEdicion ? APR_CONFIGURADAS[idTipo] : null;

        if (esEdicion && !cfg) return;

        document.getElementById('apr-modal-titulo').textContent = esEdicion ? 'Editar aprobación' : 'Nueva aprobación';
        document.getElementById('apr-id-tipo').value = esEdicion ? idTipo : '';

        // Al editar, el proceso no se cambia: cambiarlo sería otra aprobación distinta.
        sel.innerHTML = '';
        if (esEdicion) {
            sel.innerHTML = '<option value="' + cfg.id + '" data-desc="' + aprEscapar(cfg.descripcion) + '">' + aprEscapar(cfg.nombre) + '</option>';
            sel.disabled = true;
        } else {
            sel.disabled = false;
            sel.innerHTML = '<option value="" data-desc="">Selecciona un proceso…</option>'
                + APR_DISPONIBLES.map(t =>
                    '<option value="' + t.id + '" data-desc="' + aprEscapar(t.descripcion) + '">' + aprEscapar(t.nombre) + '</option>'
                ).join('');
        }

        APR_SELECCION = new Set(esEdicion ? cfg.aprobadores : []);
        aprRenderChips();
        aprMostrarDescripcion();

        document.getElementById('apr-umbral').value = (esEdicion && cfg.umbral !== null && cfg.umbral !== '') ? cfg.umbral : '';
        document.getElementById('apr-activa').checked = esEdicion ? cfg.activa : true;
        document.getElementById('apr-buscar').value = '';

        // El botón Eliminar solo aplica a una aprobación ya creada.
        const btnEliminar = document.getElementById('apr-btn-eliminar');
        btnEliminar.classList.toggle('d-none', !esEdicion || !APR_PERM.eliminar);

        const puedeGuardar = esEdicion ? APR_PERM.actualizar : APR_PERM.crear;
        document.getElementById('apr-btn-guardar').classList.toggle('d-none', !puedeGuardar);
        document.getElementById('apr-buscar').disabled = !puedeGuardar;
        document.getElementById('apr-umbral').disabled = !puedeGuardar;
        document.getElementById('apr-activa').disabled = !puedeGuardar;

        APR_MODAL.show();
    }

    function aprSetupBuscador() {
        const input = document.getElementById('apr-buscar');
        const dropdown = document.getElementById('apr-dropdown');
        const wrap = document.getElementById('apr-wrap');

        function ocultar() { dropdown.classList.add('d-none'); dropdown.innerHTML = ''; }

        function buscar() {
            const q = input.value.trim().toLowerCase();
            if (q === '') { ocultar(); return; }
            const candidatos = APR_USUARIOS
                .filter(u => !APR_SELECCION.has(u.id) && u.nombre.toLowerCase().includes(q))
                .slice(0, 8);
            if (candidatos.length === 0) { ocultar(); return; }
            dropdown.innerHTML = candidatos.map(u =>
                '<button type="button" class="list-group-item list-group-item-action small py-1 px-2" data-uid="' + u.id + '">' + aprEscapar(u.nombre) + '</button>'
            ).join('');
            dropdown.classList.remove('d-none');
            dropdown.querySelectorAll('[data-uid]').forEach(btn => {
                btn.addEventListener('click', () => {
                    APR_SELECCION.add(parseInt(btn.dataset.uid, 10));
                    aprRenderChips();
                    input.value = '';
                    ocultar();
                    input.focus();
                });
            });
        }

        wrap.addEventListener('click', () => { if (!input.disabled) input.focus(); });
        input.addEventListener('input', buscar);
        input.addEventListener('focus', buscar);
        input.addEventListener('keydown', (e) => {
            // Con el campo vacío, Backspace/Delete quita el último aprobador agregado.
            if ((e.key === 'Backspace' || e.key === 'Delete') && input.value === '' && APR_SELECCION.size > 0) {
                e.preventDefault();
                const ultimo = Array.from(APR_SELECCION).pop();
                APR_SELECCION.delete(ultimo);
                aprRenderChips();
                ocultar();
            }
        });
        document.addEventListener('click', (e) => {
            if (!wrap.contains(e.target) && !dropdown.contains(e.target)) ocultar();
        });
    }

    // ─── Guardar / eliminar ────────────────────────────────────────────────────
    async function aprGuardar() {
        const idTipo = document.getElementById('apr-proceso').value;
        if (!idTipo) {
            Swal.fire('Falta el proceso', 'Selecciona el proceso que va a requerir aprobación.', 'warning');
            return;
        }
        if (APR_SELECCION.size === 0) {
            Swal.fire('Falta un aprobador', 'Agrega al menos un usuario que pueda aprobar.', 'warning');
            return;
        }

        const btn = document.getElementById('apr-btn-guardar');
        btn.disabled = true;

        const fd = new FormData();
        fd.append('id_tipo', idTipo);
        if (document.getElementById('apr-activa').checked) fd.append('requiere_aprobacion', '1');
        const umbral = document.getElementById('apr-umbral').value;
        if (umbral !== '') fd.append('umbral_monto', umbral);
        APR_SELECCION.forEach(uid => fd.append('usuarios_aprobadores[]', uid));

        try {
            const res = await fetch(APR_URL + '/guardarAjax', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.ok) {
                Swal.fire('No se pudo guardar', json.mensaje || 'Error desconocido.', 'error');
                btn.disabled = false;
                return;
            }
            APR_MODAL.hide();
            Swal.fire({ icon: 'success', title: json.mensaje, timer: 1200, showConfirmButton: false })
                .then(() => window.location.reload());
        } catch (err) {
            Swal.fire('Error de conexión', 'No se pudo contactar al servidor.', 'error');
            btn.disabled = false;
        }
    }

    async function aprEliminar() {
        const idTipo = document.getElementById('apr-id-tipo').value;
        if (!idTipo) return;
        const cfg = APR_CONFIGURADAS[idTipo];

        const conf = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar la aprobación?',
            html: '<div class="small">Se quitará <strong>' + aprEscapar(cfg ? cfg.nombre : '') + '</strong> del listado.<br>'
                + 'Ese proceso dejará de pedir aprobación y se ejecutará directamente.</div>',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        });
        if (!conf.isConfirmed) return;

        const fd = new FormData();
        fd.append('id_tipo', idTipo);
        try {
            const res = await fetch(APR_URL + '/eliminarAjax', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.ok) {
                Swal.fire('No se pudo eliminar', json.mensaje || 'Error desconocido.', 'error');
                return;
            }
            APR_MODAL.hide();
            Swal.fire({ icon: 'success', title: json.mensaje, timer: 1200, showConfirmButton: false })
                .then(() => window.location.reload());
        } catch (err) {
            Swal.fire('Error de conexión', 'No se pudo contactar al servidor.', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        APR_MODAL = new bootstrap.Modal(document.getElementById('modalAprobacion'));
        aprPintarListado();
        aprSetupBuscador();

        document.getElementById('apr-proceso').addEventListener('change', aprMostrarDescripcion);
        document.getElementById('apr-btn-guardar').addEventListener('click', aprGuardar);
        document.getElementById('apr-btn-eliminar').addEventListener('click', aprEliminar);

        const btnNueva = document.getElementById('apr-btn-nueva');
        if (btnNueva) {
            btnNueva.addEventListener('click', () => {
                if (APR_DISPONIBLES.length === 0) {
                    Swal.fire(
                        'No hay procesos disponibles',
                        'Ya configuraste todos los procesos aprobables del sistema.',
                        'info'
                    );
                    return;
                }
                aprAbrirModal(null);
            });
        }

        document.querySelectorAll('.apr-editar').forEach(btn => {
            btn.addEventListener('click', () => aprAbrirModal(parseInt(btn.dataset.tipo, 10)));
        });
    });
</script>
