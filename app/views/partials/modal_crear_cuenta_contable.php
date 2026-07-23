<?php
/**
 * Modal reutilizable: crear una cuenta contable de nivel 5 desde cualquier módulo.
 * Incluir con: require_once MVC_APP . '/views/partials/modal_crear_cuenta_contable.php';
 * (solo si el usuario tiene permiso de crear en modulos/plan-cuentas).
 *
 * Uso: window.abrirModalCrearCuentaContable(function(cuenta){ ... });
 * `cuenta` = {id, codigo, nombre}.
 */
?>
<div class="modal fade" id="modalCrearCuentaContable" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Crear Cuenta Contable</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div id="ccc-alert" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>

                <!-- Paso 1: cuenta madre -->
                <div id="ccc-step1">
                    <label class="form-label small fw-bold text-secondary">1. Cuenta madre</label>
                    <div id="ccc-raices" class="d-flex flex-wrap gap-2">
                        <div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</div>
                    </div>
                </div>

                <!-- Paso 2: cuenta de nivel 4 -->
                <div id="ccc-step2" class="d-none mt-3">
                    <label class="form-label small fw-bold text-secondary d-flex justify-content-between">
                        <span>2. Cuenta de nivel 4</span>
                        <a href="#" id="ccc-cambiar-raiz" class="text-decoration-none small">« Cambiar cuenta madre</a>
                    </label>
                    <input type="text" class="form-control form-control-sm shadow-none mb-2" id="ccc-filtro-nivel4" placeholder="Filtrar...">
                    <div id="ccc-nivel4-list" class="list-group list-group-flush border rounded" style="max-height: 180px; overflow-y: auto;"></div>
                </div>

                <!-- Paso 3: código + nombre -->
                <div id="ccc-step3" class="d-none mt-3">
                    <label class="form-label small fw-bold text-secondary d-flex justify-content-between">
                        <span>3. Nueva cuenta (Nivel 5)</span>
                        <a href="#" id="ccc-cambiar-nivel4" class="text-decoration-none small">« Cambiar cuenta nivel 4</a>
                    </label>
                    <div class="small text-muted mb-2">Dentro de: <strong id="ccc-padre-label"></strong></div>
                    <div class="row g-2">
                        <div class="col-5">
                            <input type="text" class="form-control form-control-sm bg-light fw-bold shadow-none" id="ccc-codigo" readonly>
                        </div>
                        <div class="col-7">
                            <input type="text" class="form-control form-control-sm shadow-none" id="ccc-nombre" placeholder="Nombre de la nueva cuenta...">
                        </div>
                    </div>
                    <div id="ccc-similares-wrap" class="mt-2 d-none">
                        <div class="small fw-bold text-muted" style="font-size: 0.7rem;"><i class="bi bi-search me-1"></i>CUENTAS EXISTENTES CON NOMBRE PARECIDO</div>
                        <div id="ccc-similares-list" class="list-group list-group-flush border rounded mt-1" style="max-height: 120px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 px-4 py-3">
                <button type="button" class="btn btn-link text-muted btn-sm text-decoration-none px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="ccc-btn-guardar" disabled><i class="bi bi-check-lg me-1"></i> Crear Cuenta</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const urlBaseCCC = '<?= rtrim(BASE_URL, '/') ?>/modulos/plan-cuentas';
    let cccModalInst = null;
    let cccOnSaved = null;
    let cccCodigoNivel4 = null;
    let cccDebounceTimer = null;

    function getCccModal() {
        if (!cccModalInst) cccModalInst = new bootstrap.Modal(document.getElementById('modalCrearCuentaContable'));
        return cccModalInst;
    }

    function cccAlerta(msg, tipo) {
        const el = document.getElementById('ccc-alert');
        if (!msg) { el.classList.add('d-none'); return; }
        el.textContent = msg;
        el.className = 'alert alert-' + (tipo || 'danger') + ' mb-3 py-2 small shadow-sm border-0';
    }

    function cccReset() {
        cccAlerta(null);
        cccCodigoNivel4 = null;
        document.getElementById('ccc-step1').classList.remove('d-none');
        document.getElementById('ccc-step2').classList.add('d-none');
        document.getElementById('ccc-step3').classList.add('d-none');
        document.getElementById('ccc-nivel4-list').innerHTML = '';
        document.getElementById('ccc-filtro-nivel4').value = '';
        document.getElementById('ccc-codigo').value = '';
        document.getElementById('ccc-nombre').value = '';
        document.getElementById('ccc-similares-wrap').classList.add('d-none');
        document.getElementById('ccc-similares-list').innerHTML = '';
        document.getElementById('ccc-btn-guardar').disabled = true;
    }

    async function cccFetchJson(url) {
        const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!resp.ok) throw new Error('Error de red (' + resp.status + ')');
        const json = await resp.json();
        if (!json.ok) throw new Error(json.error || 'Error desconocido');
        return json;
    }

    async function cccCargarRaices() {
        const cont = document.getElementById('ccc-raices');
        cont.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</div>';
        try {
            const json = await cccFetchJson(urlBaseCCC + '/getCuentasPorNivelAjax?nivel=1');
            if (!json.data.length) {
                cont.innerHTML = '<div class="text-muted small">Esta empresa aún no tiene un Plan de Cuentas inicial. Créalo primero en el módulo Plan de Cuentas.</div>';
                return;
            }
            cont.innerHTML = json.data.map(r => {
                const id = 'ccc-raiz-' + r.id;
                return `<input type="radio" class="btn-check" name="ccc-raiz" id="${id}" autocomplete="off" data-codigo="${r.codigo}" data-nombre="${r.nombre.replace(/"/g, '&quot;')}">
                        <label class="btn btn-outline-primary btn-sm" for="${id}">${r.codigo} · ${r.nombre}</label>`;
            }).join('');
            cont.querySelectorAll('input[name="ccc-raiz"]').forEach(input => {
                input.addEventListener('change', () => cccSeleccionarRaiz(input.dataset.codigo, input.dataset.nombre));
            });
        } catch (e) {
            cont.innerHTML = '<div class="text-danger small">' + e.message + '</div>';
        }
    }

    async function cccSeleccionarRaiz(codigoRaiz, nombreRaiz) {
        document.getElementById('ccc-step2').classList.remove('d-none');
        document.getElementById('ccc-step3').classList.add('d-none');
        document.getElementById('ccc-btn-guardar').disabled = true;
        cccCodigoNivel4 = null;

        const cont = document.getElementById('ccc-nivel4-list');
        cont.innerHTML = '<div class="text-muted small p-2"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</div>';
        try {
            const json = await cccFetchJson(urlBaseCCC + '/getCuentasPorNivelAjax?nivel=4&prefijo=' + encodeURIComponent(codigoRaiz));
            cccRenderNivel4(json.data);
        } catch (e) {
            cont.innerHTML = '<div class="text-danger small p-2">' + e.message + '</div>';
        }
    }

    let cccNivel4Cache = [];
    function cccRenderNivel4(data) {
        cccNivel4Cache = data;
        const cont = document.getElementById('ccc-nivel4-list');
        if (!data.length) {
            cont.innerHTML = '<div class="text-muted small p-2">No hay cuentas de nivel 4 bajo esta raíz.</div>';
            return;
        }
        cont.innerHTML = '';
        data.forEach(c => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action py-1 px-2 small';
            item.innerHTML = '<code class="text-secondary me-1">' + c.codigo + '</code>' + c.nombre;
            item.addEventListener('click', () => cccSeleccionarNivel4(c.codigo, c.nombre));
            cont.appendChild(item);
        });
    }

    document.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'ccc-filtro-nivel4') {
            const q = e.target.value.trim().toLowerCase();
            const filtrado = !q ? cccNivel4Cache : cccNivel4Cache.filter(c =>
                c.codigo.toLowerCase().includes(q) || c.nombre.toLowerCase().includes(q));
            cccRenderNivel4(filtrado);
        }
    });

    async function cccSeleccionarNivel4(codigo, nombre) {
        cccCodigoNivel4 = codigo;
        document.getElementById('ccc-padre-label').textContent = codigo + ' · ' + nombre;
        document.getElementById('ccc-step3').classList.remove('d-none');
        document.getElementById('ccc-codigo').value = '...';
        document.getElementById('ccc-btn-guardar').disabled = true;
        try {
            const json = await cccFetchJson(urlBaseCCC + '/getNextCodigoAjax?padre=' + encodeURIComponent(codigo));
            document.getElementById('ccc-codigo').value = json.codigo;
            document.getElementById('ccc-btn-guardar').disabled = false;
            document.getElementById('ccc-nombre').focus();
        } catch (e) {
            cccAlerta(e.message);
        }
    }

    document.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'ccc-nombre') {
            clearTimeout(cccDebounceTimer);
            const q = e.target.value.trim();
            if (q.length < 2) {
                document.getElementById('ccc-similares-wrap').classList.add('d-none');
                return;
            }
            cccDebounceTimer = setTimeout(() => cccBuscarSimilares(q), 300);
        }
    });

    async function cccBuscarSimilares(q) {
        try {
            const json = await cccFetchJson(urlBaseCCC + '/searchAjaxCuentas?q=' + encodeURIComponent(q));
            const wrap = document.getElementById('ccc-similares-wrap');
            const list = document.getElementById('ccc-similares-list');
            if (!json.data.length) { wrap.classList.add('d-none'); return; }
            list.innerHTML = '';
            json.data.forEach(c => {
                const item = document.createElement('div');
                item.className = 'list-group-item py-1 px-2 small text-muted';
                item.innerHTML = '<code class="text-secondary me-1">' + c.codigo + '</code>' + c.nombre;
                list.appendChild(item);
            });
            wrap.classList.remove('d-none');
        } catch (e) { /* búsqueda de apoyo: sin bloquear el flujo si falla */ }
    }

    document.getElementById('ccc-cambiar-raiz').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('ccc-step2').classList.add('d-none');
        document.getElementById('ccc-step3').classList.add('d-none');
        document.getElementById('ccc-btn-guardar').disabled = true;
        document.querySelectorAll('input[name="ccc-raiz"]').forEach(r => r.checked = false);
    });

    document.getElementById('ccc-cambiar-nivel4').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('ccc-step3').classList.add('d-none');
        document.getElementById('ccc-btn-guardar').disabled = true;
        cccCodigoNivel4 = null;
    });

    document.getElementById('ccc-btn-guardar').addEventListener('click', async function() {
        const codigo = document.getElementById('ccc-codigo').value.trim();
        const nombre = document.getElementById('ccc-nombre').value.trim();
        cccAlerta(null);

        if (!nombre) { cccAlerta('El nombre de la cuenta es obligatorio.'); return; }
        if (!codigo || !cccCodigoNivel4) { cccAlerta('Selecciona primero la cuenta de nivel 4.'); return; }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

        try {
            const fd = new FormData();
            fd.append('codigo', codigo);
            fd.append('nivel', '5');
            fd.append('nombre', nombre);
            fd.append('status', '1');

            const resp = await fetch(urlBaseCCC + '/store', { method: 'POST', body: fd });
            const json = await resp.json();

            if (!json.ok) {
                cccAlerta(json.error || 'No se pudo crear la cuenta.');
                return;
            }

            getCccModal().hide();
            if (typeof cccOnSaved === 'function') {
                cccOnSaved({ id: json.id, codigo: codigo, nombre: nombre });
            }
        } catch (e) {
            cccAlerta('Error de red al guardar.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Crear Cuenta';
        }
    });

    window.abrirModalCrearCuentaContable = function(onSaved) {
        cccOnSaved = (typeof onSaved === 'function') ? onSaved : null;
        cccReset();
        getCccModal().show();
        cccCargarRaices();
    };
})();
</script>
