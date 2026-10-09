/**
 * Lógica compartida para el Modal de Vendedores
 */

(function (window, document) {
    'use strict';

    const urlBaseVendedores = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/vendedores') : (window.location.origin + '/sistema/public/modulos/vendedores');
    const formV = document.getElementById('formVendedor');
    let modalInstV = null;

    function getModalV() {
        if (!modalInstV) {
            const el = document.getElementById('modalVendedor');
            if (el) modalInstV = new bootstrap.Modal(el);
        }
        return modalInstV;
    }

    /**
     * Llena el select "Usuario del sistema" con los usuarios de la empresa.
     * Los que ya son otro vendedor salen deshabilitados (lo impide un índice
     * único en la base). Si aún no se ejecutó
     * database/vendedores_usuario_vinculado.sql, el campo queda oculto.
     */
    async function cargarUsuariosVinculablesV(idVendedor, idSeleccionado) {
        const wrap = document.getElementById('vendedor_usuario_wrap');
        const sel = document.getElementById('vendedor_id_usuario_vinculado');
        if (!wrap || !sel) return;

        sel.innerHTML = '<option value="">— Sin vincular —</option>';
        try {
            const resp = await fetch(`${urlBaseVendedores}/usuariosVinculablesAjax?id_vendedor=${idVendedor || 0}`);
            const json = await resp.json();
            if (!json.ok || !json.disponible) {
                wrap.classList.add('d-none');
                return;
            }

            (json.usuarios || []).forEach(u => {
                const etiqueta = u.cedula ? `${u.nombre} (${u.cedula})` : u.nombre;
                const opt = new Option(
                    u.tomado_por ? `${etiqueta} — ya es ${u.tomado_por}` : etiqueta,
                    u.id
                );
                if (u.tomado_por) opt.disabled = true;
                sel.add(opt);
            });

            sel.value = idSeleccionado ? String(idSeleccionado) : '';
            wrap.classList.remove('d-none');
        } catch (e) {
            wrap.classList.add('d-none');
        }
    }

    window.abrirModalVendedorCrear = function() {
        if (!formV) return;
        formV.reset();
        cargarUsuariosVinculablesV(0, null);
        
        const vid = document.getElementById('vendedor_id');
        if (vid) vid.value = '';
        
        const title = document.getElementById('tituloModalVendedorLabel');
        if (title) title.textContent = 'Nuevo Vendedor';
        
        const btnElim = document.getElementById('btnEliminarVendedorActual');
        if (btnElim) btnElim.classList.add('d-none');

        if (typeof bootstrap !== 'undefined') {
            const tabEl = document.getElementById('tab-general-vendedor-btn');
            if (tabEl) (bootstrap.Tab.getInstance(tabEl) || new bootstrap.Tab(tabEl)).show();
        }
        resetearInfoExtraV();

        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalVendedor');
        }

        const alertEl = document.getElementById('modalAlertVendedor');
        if (alertEl) alertEl.classList.add('d-none');

        getModalV()?.show();
    };

    window.abrirModalVendedorEditar = function(rowOrData) {
        let data;
        if (rowOrData instanceof HTMLElement) {
            data = JSON.parse(rowOrData.dataset.row || rowOrData.dataset.vendedor);
        } else {
            data = rowOrData;
        }

        if (!formV || !data) return;
        formV.reset();
        
        const vid = document.getElementById('vendedor_id');
        if (vid) vid.value = data.id;
        
        const vnom = document.getElementById('vendedor_nombre');
        if (vnom) vnom.value = data.nombre || '';
        
        const viden = document.getElementById('vendedor_identificacion');
        if (viden) viden.value = data.identificacion || '';
        
        const vcor = document.getElementById('vendedor_correo');
        if (vcor) vcor.value = data.correo || '';
        
        const vtel = document.getElementById('vendedor_telefono');
        if (vtel) vtel.value = data.telefono || '';
        
        const vdir = document.getElementById('vendedor_direccion');
        if (vdir) vdir.value = data.direccion || '';
        
        const vstat = document.getElementById('vendedor_status');
        if (vstat) vstat.value = data.status ?? 1;

        cargarUsuariosVinculablesV(data.id, data.id_usuario_vinculado || null);

        const title = document.getElementById('tituloModalVendedorLabel');
        if (title) title.textContent = 'Editar Vendedor';
        
        const btnElim = document.getElementById('btnEliminarVendedorActual');
        if (btnElim) btnElim.classList.remove('d-none');

        if (typeof bootstrap !== 'undefined') {
            const tabEl = document.getElementById('tab-general-vendedor-btn');
            if (tabEl) (bootstrap.Tab.getInstance(tabEl) || new bootstrap.Tab(tabEl)).show();
        }
        
        fetchInformacionExtraV(data.id);

        const alertEl = document.getElementById('modalAlertVendedor');
        if (alertEl) alertEl.classList.add('d-none');

        getModalV()?.show();
    };

    async function fetchInformacionExtraV(id) {
        resetearInfoExtraV('Cargando...');
        try {
            const resp = await fetch(`${urlBaseVendedores}/getDetalleAjax?id=${id}`);
            const json = await resp.json();
            if (json.ok) {
                const d = json.data;
                const elCount = document.getElementById('info_clientes_count_v');
                if (elCount) elCount.textContent = `${d.clientes_count} clientes`;
            } else {
                resetearInfoExtraV('Error al cargar');
            }
        } catch (e) {
            resetearInfoExtraV('Error de red');
        }
    }

    function resetearInfoExtraV(msg = '—') {
        const elCount = document.getElementById('info_clientes_count_v');
        if (elCount) elCount.textContent = msg === '—' ? '0 clientes' : msg;
    }

    window.eliminarVendedor = async function() {
        const id = document.getElementById('vendedor_id')?.value;
        if (!id || !confirm('¿Está seguro de eliminar este vendedor?')) return;

        const fd = new FormData();
        fd.append('id_eliminar', id);

        try {
            const resp = await fetch(urlBaseVendedores + '/delete', {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();
            if (json.ok) {
                getModalV()?.hide();
                if (typeof window.cargarListado === 'function' && window.location.href.includes('vendedores')) {
                    window.cargarListado();
                }
            } else {
                alert(json.error);
            }
        } catch (e) {
            alert('Error al eliminar vendedor');
        }
    };

    function initVendedorModalEvents() {
        if (!formV) return;

        formV.addEventListener('submit', async (e) => {
            e.preventDefault();
            const vid = document.getElementById('vendedor_id')?.value;
            const action = vid ? '/update' : '/store';
            const fd = new FormData(formV);
            const btn = document.getElementById('btnGuardarVendedorActual');
            const alertEl = document.getElementById('modalAlertVendedor');

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
            }
            if (alertEl) alertEl.classList.add('d-none');

            try {
                const resp = await fetch(urlBaseVendedores + action, {
                    method: 'POST',
                    body: fd
                });
                const json = await resp.json();

                if (alertEl) {
                    alertEl.textContent = json.msg || json.error || 'Error';
                    alertEl.className = 'alert mb-3 py-2 small shadow-sm border-0 ' + (json.ok ? 'alert-success' : 'alert-danger');
                    alertEl.classList.remove('d-none');
                }

                if (json.ok) {
                    // Si estamos en Clientes, podríamos necesitar actualizar el select (si es que no recarga catálogos)
                    // Nota: En clientes_modal.js ya forzamos la recarga de catálogos si se usa guardarVendedorRapido.
                    // Pero si se usa este modal estándar, podemos intentar lo mismo.
                    
                    const selClientes = document.getElementById('cliente_vendedor');
                    if (selClientes && !vid) {
                        const opt = new Option(fd.get('nombre'), json.id);
                        selClientes.add(opt);
                        selClientes.value = json.id;
                    }

                    // Crear al vuelo (CLAUDE.md §9): avisa a quien abrió el modal desde un
                    // documento (p. ej. Consignaciones) para que agregue el vendedor a su
                    // selector y lo deje seleccionado. El modal se cierra más abajo.
                    document.dispatchEvent(new CustomEvent('vendedorGuardado', { detail: {
                        ...json,
                        id:     json.id || vid,
                        nombre: (fd.get('nombre') || '').toString().trim(),
                        nuevo:  !vid
                    } }));

                    setTimeout(() => {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                        }
                        getModalV()?.hide();
                        if (typeof window.cargarListado === 'function' && window.location.href.includes('vendedores')) {
                            window.cargarListado();
                        }
                    }, 800);
                } else {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                    }
                }
            } catch (err) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVendedorModalEvents);
    } else {
        initVendedorModalEvents();
    }

})(window, document);
