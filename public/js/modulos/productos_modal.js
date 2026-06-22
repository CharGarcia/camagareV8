/**
 * Lógica compartida para el Modal de Productos
 * Requiere que BASE_URL esté definido globalmente.
 */

(function (window, document) {
    'use strict';

    const urlBaseProd = (typeof BASE_URL !== 'undefined' ? BASE_URL : (typeof B_URL !== 'undefined' ? B_URL : '')) + '/modulos/productos';
    let modalInst = null;
    let catalogosCargados = false;
    let datosCatalogos = null;

    let preciosObj = [];
    let componentesObj = [];
    let variantesObj = [];

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined') {
            const el = document.getElementById('modalProducto');
            if (el) modalInst = new bootstrap.Modal(el);
        }
        return modalInst;
    }

    // ─── Catálogos ──────────────────────────────────────
    async function cargarCatalogos() {
        if (catalogosCargados) return;
        try {
            const resp = await fetch(`${urlBaseProd}/catalogos`);
            const json = await resp.json();
            if (json.ok) {
                datosCatalogos = json.data;
                poblarSelects();
                catalogosCargados = true;
            }
        } catch (e) { console.error('Error catálogos:', e); }
    }

    function poblarSelects() {
        if (!datosCatalogos) return;

        const buildOps = (arr, label, textFn) => {
            let html = `<option value="">${label}</option>`;
            if (Array.isArray(arr)) arr.forEach(i => { html += `<option value="${i.id}">${textFn(i)}</option>`; });
            return html;
        };

        const selCat = document.getElementById('prod_id_categoria');
        if (selCat) selCat.innerHTML = buildOps(datosCatalogos.categorias, 'Ninguna...', i => i.nombre);

        const selMar = document.getElementById('prod_id_marca');
        if (selMar) selMar.innerHTML = buildOps(datosCatalogos.marcas, 'Ninguna...', i => i.nombre);

        const selTM = document.getElementById('prod_tipo_medida');
        if (selTM) selTM.innerHTML = buildOps(datosCatalogos.tipos_medida, 'Seleccione tipo', i => i.nombre);

        const selIva = document.getElementById('prod_tarifa_iva');
        if (selIva && Array.isArray(datosCatalogos.tarifas_iva)) {
            selIva.innerHTML = datosCatalogos.tarifas_iva.map(i => `<option value="${i.id}" data-porcentaje="${i.porcentaje}">${i.nombre}</option>`).join('');
        }



        const selCompMed = document.getElementById('comp_medida');
        if (selCompMed) {
            let htmlMed = '<option value="">(Base)</option>';
            if (Array.isArray(datosCatalogos.unidades_todas)) {
                datosCatalogos.unidades_todas.forEach(u => { htmlMed += `<option value="${u.id}">${u.nombre} (${u.abreviatura})</option>`; });
            }
            selCompMed.innerHTML = htmlMed;
        }

        const selIce = document.getElementById('prod_id_ice');
        if (selIce && Array.isArray(datosCatalogos.ices)) {
            let html = '<option value="">Sin ICE</option>';
            datosCatalogos.ices.forEach(i => {
                html += `<option value="${i.id}" data-valor="${i.valor_ice}">${i.nombre_ice} (${i.codigo_ats})</option>`;
            });
            selIce.innerHTML = html;
        }

        window.calcularPreciosTotales();
        window.actualizarValorICE();
    }

    window.toggleBienesFields = function() {
        const val = document.getElementById('prod_tipo_produccion')?.value;
        const isBien = (val === '01');

        document.querySelectorAll('.bienes-field').forEach(el => {
            // Limpiar el estado anterior (compatibilidad con 'invisible')
            el.classList.remove('invisible');
            if (isBien) {
                // Mostrar: el campo vuelve a ocupar su espacio
                el.classList.remove('d-none');
                el.style.pointerEvents = 'auto';
                el.querySelectorAll('input, select, textarea').forEach(input => input.disabled = false);
            } else {
                // Ocultar colapsando (display:none) para que los demás campos suban y no quede hueco
                el.classList.add('d-none');
                el.style.pointerEvents = 'none';
                el.querySelectorAll('input, select, textarea').forEach(input => input.disabled = true);
            }
        });

        const invTab = document.getElementById('tab-inventario-btn')?.closest('.nav-item');
        const compTab = document.getElementById('tab-componentes-btn')?.closest('.nav-item');
        const homolTab = document.getElementById('tab-homologaciones-btn')?.closest('.nav-item');

        if (!isBien) {
            invTab?.classList.add('d-none');
            compTab?.classList.add('d-none');
            homolTab?.classList.add('d-none');
        } else {
            invTab?.classList.remove('d-none');
            homolTab?.classList.remove('d-none');
            window.toggleInventariableTabs();
        }

        // Al cambiar a tipo '01' en un producto nuevo sin medida seleccionada, aplicar default
        if (isBien && catalogosCargados && datosCatalogos?.medida_default) {
            const prodId   = document.getElementById('prod_id')?.value;
            const tipoEl   = document.getElementById('prod_tipo_medida');
            if (!prodId && tipoEl && !tipoEl.value) {
                tipoEl.value = datosCatalogos.medida_default.id_tipo_medida;
                window.actualizarUnidadesMedida(datosCatalogos.medida_default.id_medida);
            }
        }
    };

    window.toggleInventariableTabs = function() {
        const isInventariable = document.getElementById('prod_inventariable')?.checked;
        const compTab = document.getElementById('tab-componentes-btn')?.closest('.nav-item');

        if (isInventariable) {
            compTab?.classList.remove('d-none');
        } else {
            compTab?.classList.add('d-none');
            
            // Si estábamos en Componentes y se oculta, volver a General
            const activeTab = document.querySelector('#modalProductoTabs .nav-link.active');
            if (activeTab && activeTab.id === 'tab-componentes-btn') {
                const genBtn = document.getElementById('tab-general-btn');
                if (genBtn && typeof bootstrap !== 'undefined') (bootstrap.Tab.getInstance(genBtn) || new bootstrap.Tab(genBtn)).show();
            }
        }
    };

    window.calcularPreciosTotales = function(skipPVPUpdate = false) {
        const base = parseFloat(document.getElementById('prod_precio_base')?.value || 0);
        const icePorc = parseFloat(document.getElementById('prod_valor_ice')?.value || 0);
        const ivaSelect = document.getElementById('prod_tarifa_iva');
        const ivaPorcentaje = ivaSelect ? parseFloat(ivaSelect.options[ivaSelect.selectedIndex]?.dataset.porcentaje || 0) : 0;

        const iceCalculado = base * (icePorc / 100);
        const ivaCalculado = (base + iceCalculado) * (ivaPorcentaje / 100);
        const total = base + iceCalculado + ivaCalculado;

        const ivaDisp = document.getElementById('display_iva');
        const pvpInp = document.getElementById('prod_pvp_total');

        if (ivaDisp) ivaDisp.value = ivaCalculado.toFixed(2);
        if (pvpInp && !skipPVPUpdate) {
            pvpInp.value = total.toFixed(2);
        }
    };

    window.actualizarValorICE = function() {
        const selIce = document.getElementById('prod_id_ice');
        const inputValor = document.getElementById('prod_valor_ice');
        if (selIce && inputValor) {
            const opt = selIce.options[selIce.selectedIndex];
            const valor = opt ? parseFloat(opt.dataset.valor || 0) : 0;
            inputValor.value = valor.toFixed(4);
        }
    };

    window.calcularPrecioInverso = function() {
        const pvp = parseFloat(document.getElementById('prod_pvp_total')?.value || 0);
        const icePorc = parseFloat(document.getElementById('prod_valor_ice')?.value || 0);
        const ivaSelect = document.getElementById('prod_tarifa_iva');
        const ivaPorcentaje = ivaSelect ? parseFloat(ivaSelect.options[ivaSelect.selectedIndex]?.dataset.porcentaje || 0) : 0;

        const factorICE = (1 + (icePorc / 100));
        const factorIVA = (1 + (ivaPorcentaje / 100));
        
        // Base = PVP / (Factor ICE * Factor IVA)
        const factorTotal = factorICE * factorIVA;
        const base = factorTotal > 0 ? (pvp / factorTotal) : 0;

        const baseInp = document.getElementById('prod_precio_base');
        if (baseInp) {
            baseInp.value = (base > 0 ? base : 0).toFixed(4);
        }
        
        window.calcularPreciosTotales(true);
    };

    window.actualizarUnidadesMedida = async function(idUnidadPrevia = null) {
        const idTipo = document.getElementById('prod_tipo_medida')?.value;
        await window.cargarUnidadesEnSelect(idTipo, 'prod_id_medida', idUnidadPrevia);
    };

    window.cargarUnidadesEnSelect = async function(idTipo, selectId, idUnidadPrevia = null) {
        const medS = document.getElementById(selectId);
        if (!medS) return;
        medS.innerHTML = `<option value="">Cargando...</option>`; medS.disabled = true;
        if (!idTipo) { medS.innerHTML = `<option value="">Seleccione medida</option>`; medS.disabled = false; return; }
        try {
            const resp = await fetch(`${urlBaseProd}/getUnidadesPorTipoAjax?id_tipo=${idTipo}`);
            const json = await resp.json();
            if (json.ok) {
                medS.innerHTML = `<option value="">Seleccione medida</option>` + json.data.map(i => `<option value="${i.id}">${i.nombre} (${i.abreviatura})</option>`).join('');
                if (idUnidadPrevia) medS.value = idUnidadPrevia;
                else if (json.data.length === 1) medS.value = json.data[0].id;
            }
        } catch (e) { medS.innerHTML = `<option value="">Error</option>`; } finally { medS.disabled = false; }
    };

    window.uploadProductImage = async function(input) {
        if (!input.files || !input.files[0]) return;
        const fd = new FormData();
        fd.append('image', input.files[0]);
        try {
            const resp = await fetch(`${urlBaseProd}/uploadImage`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) mostrarImagen(json.path);
            else swalError(json.error || 'Error al subir imagen');
        } catch (e) { swalError('Error de conexión al subir imagen'); }
        input.value = '';
    };

    window.searchComponente = async function() {
        const q = document.getElementById('comp_search').value.trim();
        const resDiv = document.getElementById('comp_results');
        const selfId = document.getElementById('prod_id').value || 0;
        if (q.length < 2) { resDiv.classList.add('d-none'); return; }
        try {
            // Solo buscar productos (tipo_produccion = '01') y excluir el producto actual
            const resp = await fetch(`${urlBaseProd}/searchAjaxSimple?q=${encodeURIComponent(q)}&tipo=01&exclude=${selfId}`);
            const json = await resp.json();
            if (json.ok && json.data.length > 0) {
                resDiv.innerHTML = json.data.map(i => `<button type="button" class="list-group-item list-group-item-action py-1 small" onclick="seleccionarComponente(${JSON.stringify(i).replace(/"/g, '&quot;')})"><b>${i.codigo}</b> - ${i.nombre}</button>`).join('');
                resDiv.classList.remove('d-none');
            } else { resDiv.innerHTML = '<div class="list-group-item small text-muted">No hay resultados</div>'; resDiv.classList.remove('d-none'); }
        } catch (e) { console.error(e); }
    };

    window.seleccionarComponente = function(item) {
        document.getElementById('comp_id').value = item.id;
        document.getElementById('comp_codigo').value = item.codigo;
        document.getElementById('comp_nombre').value = item.nombre;
        document.getElementById('comp_search').value = `${item.codigo} - ${item.nombre}`;
        
        // Cargar las unidades de medida permitidas para este componente
        window.cargarUnidadesEnSelect(item.id_tipo_medida, 'comp_medida', item.id_medida);

        document.getElementById('comp_results').classList.add('d-none');
    };

    function renderListas(d = {}) {
        const tbPre = document.getElementById('tb-precios');
        if (tbPre) {
            tbPre.innerHTML = preciosObj.length === 0 ? '<tr><td colspan="5" class="text-muted py-3">No hay precios adicionales</td></tr>' : 
            preciosObj.map((p, i) => `<tr><td class="text-start">${p.nombre_precio}</td><td class="fw-bold">$${parseFloat(p.precio).toFixed(2)}</td><td>${p.valido_desde || '—'}</td><td>${p.valido_hasta || '—'}</td><td><button type="button" class="btn btn-sm text-danger p-0" onclick="quitarPrecio(${i})"><i class="bi bi-x-circle-fill"></i></button></td></tr>`).join('');
        }
        const tbComp = document.getElementById('tb-componentes');
        if (tbComp) {
            tbComp.innerHTML = componentesObj.length === 0 ? '<tr><td colspan="5" class="text-muted py-3">No hay componentes</td></tr>' :
            componentesObj.map((c, i) => `<tr><td class="text-start"><code>${c.codigo_componente}</code></td><td class="text-start">${c.nombre_componente}</td><td class="fw-bold">${parseFloat(c.cantidad).toFixed(2)}</td><td>${c.nombre_medida || '(Base)'}</td><td><button type="button" class="btn btn-sm text-danger p-0" onclick="quitarComponente(${i})"><i class="bi bi-x-circle-fill"></i></button></td></tr>`).join('');
        }
        const tbVar = document.getElementById('tb-variantes');
        if (tbVar) {
            tbVar.innerHTML = variantesObj.length === 0 ? '<tr><td colspan="4" class="text-center text-muted py-3">No hay variantes asignadas</td></tr>' :
            variantesObj.map((v, i) => `<tr><td class="text-start fw-bold text-primary">${v.nombre}</td><td class="text-start">${v.valor}</td><td class="text-end fw-bold text-success">$${parseFloat(v.precio_adicional || 0).toFixed(2)}</td><td><button type="button" class="btn btn-sm text-danger p-0" onclick="quitarVariante(${i})"><i class="bi bi-x-circle-fill"></i></button></td></tr>`).join('');
        }
        const tbInv = document.getElementById('tb-inventarios');
        if (tbInv && d.inventarios) {
            tbInv.innerHTML = d.inventarios.length === 0 ? '<tr><td colspan="3" class="text-center text-muted py-3">No hay stock en bodegas</td></tr>' :
            d.inventarios.map(iv => `<tr><td>${iv.nombre_bodega}</td><td class="text-end fw-bold">${parseFloat(iv.stock_actual).toFixed(2)}</td><td class="text-end text-muted">$${parseFloat(iv.costo_promedio || 0).toFixed(4)}</td></tr>`).join('');
            document.getElementById('div-inventarios-bodegas')?.classList.remove('d-none');
        } else if (tbInv) {
            tbInv.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No hay datos de stock</td></tr>';
            document.getElementById('div-inventarios-bodegas')?.classList.add('d-none');
        }

        const tbHom = document.getElementById('tb-homologaciones');
        if (tbHom && d.homologaciones) {
            tbHom.innerHTML = d.homologaciones.length === 0 ? '<tr><td colspan="4" class="text-center py-4 text-muted">No hay homologaciones registradas</td></tr>' :
            d.homologaciones.map(h => `
                <tr>
                    <td class="ps-3 fw-medium">${h.nombre_proveedor}</td>
                    <td>${h.descripcion_homologada || '—'}</td>
                    <td class="fw-bold text-primary"><code>${h.codigo_proveedor}</code></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm text-danger p-0" onclick="quitarHomologacion(${h.id})" title="Eliminar homologación">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        } else if (tbHom) {
            tbHom.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay homologaciones registradas</td></tr>';
        }
    }

    window.quitarHomologacion = async function(id) {
        const result = await Swal.fire({
            title: '¿Eliminar homologación?',
            text: 'El proveedor ya no estará vinculado a este producto mediante este código.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`${urlBaseProd}/deleteHomologacionAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            });
            const json = await resp.json();
            if (json.ok) {
                swalToast('success', 'Homologación eliminada');
                const prodId = document.getElementById('prod_id').value;
                if (prodId) fetchDetalleExtra(prodId);
            } else {
                swalError(json.error || 'Error al eliminar homologación');
            }
        } catch (e) { console.error(e); }
    };

    window.agregarPrecio = function() {
        const nInput = document.getElementById('pre_nombre');
        const vInput = document.getElementById('pre_valor');
        const dInput = document.getElementById('pre_desde');
        const hInput = document.getElementById('pre_hasta');

        const n = nInput.value.trim();
        const p = parseFloat(vInput.value || 0);
        if (!n || p <= 0) return;
        
        preciosObj.push({ 
            nombre_precio: n, 
            precio: p, 
            valido_desde: dInput.value, 
            valido_hasta: hInput.value 
        });

        // Limpiar campos
        nInput.value = '';
        vInput.value = '';
        dInput.value = '';
        hInput.value = '';
        nInput.focus();

        renderListas();
    };
    window.quitarPrecio = i => { preciosObj.splice(i, 1); renderListas(); };

    window.agregarComponente = function() {
        const idInput = document.getElementById('comp_id');
        const sInput = document.getElementById('comp_search');
        const cInput = document.getElementById('comp_cantidad');
        const mInput = document.getElementById('comp_medida');
        const selfId = document.getElementById('prod_id').value;

        const id = idInput.value;
        if (!id) return;

        if (id == selfId) {
            swalWarning('Un producto no puede ser componente de sí mismo.');
            return;
        }

        if (componentesObj.some(c => c.id_producto_hijo == id)) {
            swalWarning('Este producto ya está en la lista de componentes.');
            return;
        }

        componentesObj.push({
            id_producto_hijo: id,
            codigo_componente: document.getElementById('comp_codigo').value,
            nombre_componente: document.getElementById('comp_nombre').value,
            cantidad: cInput.value || 1,
            id_medida: mInput.value,
            nombre_medida: mInput.options[mInput.selectedIndex]?.text || ''
        });

        // Limpiar
        idInput.value = '';
        sInput.value = '';
        cInput.value = '1';
        sInput.focus();

        renderListas();
    };
    window.quitarComponente = i => { componentesObj.splice(i, 1); renderListas(); };

    window.agregarVariante = function() {
        const n = document.getElementById('var_nombre').value.trim();
        const v = document.getElementById('var_valor').value.trim();
        if (!n || !v) return;
        variantesObj.push({ nombre: n, valor: v, precio_adicional: document.getElementById('var_precio').value || 0 });
        renderListas();
    };
    window.quitarVariante = i => { variantesObj.splice(i, 1); renderListas(); };



    function limpiarAlertas() {
        const alertEl = document.getElementById('modalAlert');
        if (alertEl) { alertEl.textContent = ''; alertEl.className = 'alert d-none m-3 py-2 small shadow-sm border-0'; }
    }

    window.abrirModalProductoCrear = async function() {
        if (!catalogosCargados) await cargarCatalogos();
        const btn = document.getElementById('btnGuardarProducto');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar'; }
        limpiarAlertas();
        const f = document.getElementById('formProducto');
        if (f) f.reset();
        
        const prodId = document.getElementById('prod_id');
        if (prodId) prodId.value = '';
        
        const titulo = document.getElementById('tituloModalProducto');
        if (titulo) titulo.textContent = 'Nuevo Producto';
        
        const btnEliminar = document.getElementById('btnEliminarProducto');
        if (btnEliminar) btnEliminar.classList.add('d-none');
        
        const cSearch = document.getElementById('comp_search');
        if (cSearch) cSearch.value = '';
        
        const cId = document.getElementById('comp_id');
        if (cId) cId.value = '';
        
        preciosObj = []; componentesObj = []; variantesObj = [];
        renderListas();
        window.removerImagen();
        quitarRestriccionesEnUso();
        activarTabGeneral();
        window.toggleBienesFields();
        window.actualizarCodigoSiguiente();

        // Aplicar medida default para tipo '01'
        if (datosCatalogos?.medida_default) {
            const tipoEl = document.getElementById('prod_tipo_medida');
            if (tipoEl) tipoEl.value = datosCatalogos.medida_default.id_tipo_medida;
            await window.actualizarUnidadesMedida(datosCatalogos.medida_default.id_medida);
        }

        if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal();
        getModal()?.show();
    };

    window.abrirModalProductoEditar = async function(rowOrData) {
        if (!catalogosCargados) await cargarCatalogos();
        const btn = document.getElementById('btnGuardarProducto');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar'; }
        limpiarAlertas();
        const data = (rowOrData instanceof HTMLElement) ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        const f = document.getElementById('formProducto');
        if (f) f.reset();
        quitarRestriccionesEnUso();
        document.getElementById('prod_id').value = data.id;
        document.getElementById('prod_codigo').value = data.codigo || '';
        document.getElementById('prod_nombre').value = data.nombre || '';
        document.getElementById('prod_codigo_auxiliar').value = data.codigo_auxiliar || '';
        document.getElementById('prod_codigo_barras').value = data.codigo_barras || '';
        document.getElementById('prod_tipo_produccion').value = data.tipo_produccion || '01';
        document.getElementById('prod_status').value = data.status ?? 1;
        document.getElementById('prod_id_categoria').value = data.id_categoria || '';
        document.getElementById('prod_id_marca').value = data.id_marca || '';
        document.getElementById('prod_id_marca').value = data.id_marca || '';
        


        document.getElementById('prod_inventariable').checked = (data.inventariable === true || data.inventariable === 'true' || data.inventariable == 1 || data.inventariable === 't');
        document.getElementById('prod_stock_minimo').value = parseFloat(data.stock_minimo || 0).toFixed(4);
        document.getElementById('prod_stock_maximo').value = parseFloat(data.stock_maximo || 0).toFixed(4);
        document.getElementById('prod_stock_actual').value = '0.00'; // Se llenará en fetchDetalleExtra

        // Opciones de uso (compra / venta)
        const opc = typeof data.opciones === 'string'
            ? JSON.parse(data.opciones || '{"compra":true,"venta":true}')
            : (data.opciones || { compra: true, venta: true });
        document.getElementById('prod_opc_compra').checked = opc.compra ?? true;
        document.getElementById('prod_opc_venta').checked  = opc.venta  ?? true;

        document.getElementById('prod_tipo_medida').value = data.id_tipo_medida || '';
        await window.actualizarUnidadesMedida(data.id_medida);
        document.getElementById('prod_precio_base').value = parseFloat(data.precio_base || 0).toFixed(4);
        document.getElementById('prod_tarifa_iva').value = data.tarifa_iva || 2;
        document.getElementById('prod_id_ice').value = data.id_ice || '';
        document.getElementById('prod_valor_ice').value = parseFloat(data.valor_ice || 0).toFixed(4);

        window.toggleInventariableTabs();
        if (typeof window.toggleBienesFields === 'function') window.toggleBienesFields();
        window.calcularPreciosTotales();
        activarTabGeneral();
        if (typeof window.removerImagen === 'function') window.removerImagen();

        const titulo = document.getElementById('tituloModalProducto');
        if (titulo) titulo.textContent = 'Editar Producto';
        
        const btnEliminar = document.getElementById('btnEliminarProducto');
        if (btnEliminar) btnEliminar.classList.remove('d-none');
        
        const cSearch = document.getElementById('comp_search');
        if (cSearch) cSearch.value = '';
        
        const cId = document.getElementById('comp_id');
        if (cId) cId.value = '';

        fetchDetalleExtra(data.id);
        getModal()?.show();
    };

    // ─── Helpers SweetAlert ────────────────────────────────────────────────────
    function swalToast(icon, title) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title,
            showConfirmButton: false,
            timer: 2800,
            timerProgressBar: true
        });
    }

    function swalError(html) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Aceptar'
        });
    }

    function swalWarning(html) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            html,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Aceptar'
        });
    }

    // ─── Tab / restricciones ──────────────────────────────────────────────────
    function activarTabGeneral() {
        const genBtn = document.getElementById('tab-general-btn');
        if (genBtn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(genBtn) || new bootstrap.Tab(genBtn)).show();
        }
    }

    function aplicarRestriccionesEnUso() {
        const codigo = document.getElementById('prod_codigo');
        const nombre = document.getElementById('prod_nombre');
        const tipo   = document.getElementById('prod_tipo_produccion');
        if (codigo) { codigo.readOnly = true; codigo.classList.add('bg-light'); }
        if (nombre) { nombre.readOnly = true; nombre.classList.add('bg-light'); }
        if (tipo)   { tipo.disabled = true; }
    }

    function quitarRestriccionesEnUso() {
        const codigo = document.getElementById('prod_codigo');
        const nombre = document.getElementById('prod_nombre');
        const tipo   = document.getElementById('prod_tipo_produccion');
        if (codigo) { codigo.readOnly = false; codigo.classList.remove('bg-light'); }
        if (nombre) { nombre.readOnly = false; nombre.classList.remove('bg-light'); }
        if (tipo)   { tipo.disabled = false; }
    }

    async function fetchDetalleExtra(id) {
        try {
            const resp = await fetch(`${urlBaseProd}/getDetalleAjax?id=${id}`);
            const json = await resp.json();
            if (json.ok) {
                const d = json.data;
                preciosObj = d.precios || [];
                componentesObj = d.componentes || [];
                variantesObj = d.variantes || [];
                renderListas(d);
                if (d.imagen) mostrarImagen(d.imagen);
                if (d.stock_actual_general !== undefined) {
                    document.getElementById('prod_stock_actual').value = parseFloat(d.stock_actual_general).toFixed(2);
                }



                // Bloquear campos críticos si el producto ya fue usado en documentos
                if (d.en_uso) {
                    aplicarRestriccionesEnUso();
                }
            }
        } catch (e) {}
    }

    function mostrarImagen(path) {
        const prodImg = document.getElementById('prod_imagen');
        if (prodImg) prodImg.value = path;
        const preview = document.getElementById('imagePreview');
        if (preview) {
            const fullUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : (typeof B_URL !== 'undefined' ? B_URL : '')) + '/' + path;
            preview.innerHTML = `<img src="${fullUrl}" class="img-fluid" style="max-height: 100%; object-fit: contain;">`;
        }
        document.getElementById('btnRemoveImage')?.classList.remove('d-none');
    }

    window.removerImagen = function() {
        const prodImg = document.getElementById('prod_imagen');
        if (prodImg) prodImg.value = '';
        const preview = document.getElementById('imagePreview');
        if (preview) {
            preview.innerHTML = '<i class="bi bi-image text-muted opacity-25 fs-4"></i>';
        }
        document.getElementById('btnRemoveImage')?.classList.add('d-none');
    };

    window.guardarProducto = async function() {
        const f   = document.getElementById('formProducto');
        const btn = document.getElementById('btnGuardarProducto');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            const fd = new FormData(f);
            fd.append('precios',     JSON.stringify(preciosObj));
            fd.append('componentes', JSON.stringify(componentesObj));
            fd.append('variantes',   JSON.stringify(variantesObj));

            const id        = document.getElementById('prod_id').value;
            const urlAction = id ? '/update' : '/store';
            const resp      = await fetch(urlBaseProd + urlAction, { method: 'POST', body: fd });
            const json      = await resp.json();

            if (json.ok) {
                setTimeout(() => {
                    btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                    getModal().hide();
                    swalToast('success', json.msg || 'Guardado correctamente.');
                    if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
                    document.dispatchEvent(new CustomEvent('productoGuardado', { detail: json }));
                }, 500);
            } else {
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                swalError(json.error || 'Ocurrió un error inesperado.');
            }
        } catch (e) {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
            swalError('Error de conexión con el servidor.');
        }
    };

    window.eliminarProducto = async function() {
        const id = document.getElementById('prod_id').value;
        if (!id) return;

        const result = await Swal.fire({
            title: '¿Eliminar producto?',
            text: 'Esta acción no se puede revertir.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return;

        try {
            const fd = new FormData(); fd.append('id_eliminar', id);
            const resp = await fetch(urlBaseProd + '/delete', { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModal().hide();
                swalToast('success', json.msg || 'Producto eliminado.');
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } else {
                swalError(json.error || 'No se pudo eliminar el producto.');
            }
        } catch (e) {
            swalError('Error de conexión con el servidor.');
        }
    };

    window.actualizarCodigoSiguiente = async function() {
        const tipo = document.getElementById('prod_tipo_produccion')?.value || '01';
        const idProd = document.getElementById('prod_id')?.value;
        // Solo autogenerar si es un producto nuevo (sin ID)
        if (idProd) return;

        try {
            const resp = await fetch(`${urlBaseProd}/getSiguienteCodigoAjax?tipo=${tipo}`);
            const json = await resp.json();
            if (json.ok) {
                const codInp = document.getElementById('prod_codigo');
                if (codInp) codInp.value = json.codigo;
            }
        } catch (e) { console.error('Error al generar código:', e); }
    };

    // Escuchar eventos de creación de catálogos desde otros modales
    window.addEventListener('categoriaGuardada', (e) => {
        const cat = e.detail;
        if (datosCatalogos && datosCatalogos.categorias) {
            if (!datosCatalogos.categorias.some(c => c.id == cat.id)) {
                datosCatalogos.categorias.push({ id: cat.id, nombre: cat.nombre });
            }
        }
        const select = document.getElementById('prod_id_categoria');
        if (select) {
            if (!Array.from(select.options).some(opt => opt.value == cat.id)) {
                select.add(new Option(cat.nombre, cat.id));
            }
            select.value = cat.id;
        }
    });

    window.addEventListener('marcaGuardada', (e) => {
        const mar = e.detail;
        if (datosCatalogos && datosCatalogos.marcas) {
            if (!datosCatalogos.marcas.some(m => m.id == mar.id)) {
                datosCatalogos.marcas.push({ id: mar.id, nombre: mar.nombre });
            }
        }
        const select = document.getElementById('prod_id_marca');
        if (select) {
            if (!Array.from(select.options).some(opt => opt.value == mar.id)) {
                select.add(new Option(mar.nombre, mar.id));
            }
            select.value = mar.id;
        }
    });

})(window, document);
