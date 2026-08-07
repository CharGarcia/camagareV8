// Módulo: Reporte de Inventarios (Existencias, Movimientos, Valorización, Consignaciones)

// ════════════════════════════════════════════════════════════════════
// HELPERS COMPARTIDOS
// ════════════════════════════════════════════════════════════════════
function RI_paramsFromIds(map) {
    const params = new URLSearchParams();
    Object.keys(map).forEach(key => {
        const el = document.getElementById(map[key]);
        if (!el) return;
        const val = (el.type === 'checkbox') ? (el.checked ? '1' : '') : el.value;
        if (val !== '' && val !== null && val !== undefined) params.set(key, val);
    });
    return params;
}

function RI_setupAutocomplete(searchId, dropdownId, hiddenId, selectedLabelId, ajaxUrl, onSelect) {
    let timer;
    const search   = document.getElementById(searchId);
    const dropdown = document.getElementById(dropdownId);
    if (!search || !dropdown) return;

    search.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { dropdown.classList.add('d-none'); return; }

        timer = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + ajaxUrl + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    const items = data.data || [];
                    if (items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            const nombre = item.nombre || '';
                            const sub = item.codigo || item.identificacion || '';
                            btn.innerHTML = `<strong>${nombre}</strong><br><small class="text-muted">${sub}</small>`;
                            btn.addEventListener('click', function () {
                                search.value = nombre;
                                dropdown.classList.add('d-none');
                                document.getElementById(hiddenId).value = item.id;
                                if (selectedLabelId) {
                                    const lbl = document.getElementById(selectedLabelId);
                                    if (lbl) lbl.textContent = nombre;
                                }
                                if (onSelect) onSelect();
                            });
                            dropdown.appendChild(btn);
                        });
                        dropdown.classList.remove('d-none');
                    } else {
                        dropdown.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
                        dropdown.classList.remove('d-none');
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!search.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('d-none');
    });
}

function RI_limpiarBusqueda(searchId, hiddenId, selectedLabelId) {
    document.getElementById(searchId).value = '';
    document.getElementById(hiddenId).value = '';
    if (selectedLabelId) {
        const lbl = document.getElementById(selectedLabelId);
        if (lbl) lbl.textContent = '';
    }
}

const RI_PALETA = [
    'rgba(13,110,253,.7)', 'rgba(220,53,69,.7)', 'rgba(25,135,84,.7)',
    'rgba(255,193,7,.7)', 'rgba(13,202,240,.7)', 'rgba(111,66,193,.7)',
    'rgba(253,126,20,.7)', 'rgba(32,201,151,.7)', 'rgba(214,51,132,.7)',
    'rgba(108,117,125,.7)',
];
function RI_colores(n) {
    return Array.from({ length: n }, (_, i) => RI_PALETA[i % RI_PALETA.length]);
}

function RI_fetchGenerar(tab, params, onOk, onError) {
    params.set('tab', tab);
    fetch(BASE_URL + '/' + RUTA_MODULO + '/generarAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: params.toString(),
    })
    .then(async response => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) { throw new Error(text.substring(0, 200)); }
    })
    .then(res => {
        if (res.ok) onOk(res);
        else onError(res.error || 'Ocurrió un error al generar el reporte');
    })
    .catch(err => { console.error(err); onError(err.message); });
}

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 1: EXISTENCIAS
// ════════════════════════════════════════════════════════════════════
window.RI_Existencias = {
    orden: '',
    dir: 'ASC',

    limpiarProducto() {
        RI_limpiarBusqueda('ri-ex-search-producto', 'ri-ex-id-producto', 'ri-ex-producto-seleccionado');
        this.generar();
    },

    modalEditarInstance: null,

    abrirModalEditar(btn) {
        const d = btn.dataset;
        document.getElementById('ri-ex-edit-id-producto').value = d.idProducto;
        document.getElementById('ri-ex-edit-id-bodega').value = d.idBodega;
        document.getElementById('ri-ex-edit-producto-nombre').textContent = d.productoNombre;
        document.getElementById('ri-ex-edit-bodega-nombre').textContent = d.bodegaNombre;
        document.getElementById('ri-ex-edit-minimo').value = d.stockMinimo;
        document.getElementById('ri-ex-edit-minimo').dataset.original = d.stockMinimo;
        document.getElementById('ri-ex-edit-maximo').value = d.stockMaximo;
        document.getElementById('ri-ex-edit-maximo').dataset.original = d.stockMaximo;

        const selCategoria = document.getElementById('ri-ex-edit-categoria');
        selCategoria.innerHTML = '<option value="">Sin categoría</option>'
            + Array.from(document.getElementById('ri-ex-categoria').options).slice(1).map(o => o.outerHTML).join('');
        selCategoria.value = d.idCategoria || '';
        selCategoria.dataset.original = d.idCategoria || '';
        selCategoria.dataset.originalLabel = selCategoria.selectedOptions[0] ? selCategoria.selectedOptions[0].textContent : 'Sin categoría';

        const selAdjBodega = document.getElementById('ri-ex-adj-bodega');
        selAdjBodega.innerHTML = Array.from(document.getElementById('ri-ex-bodega').options).slice(1).map(o => o.outerHTML).join('');
        selAdjBodega.value = d.idBodega;

        document.getElementById('ri-ex-adj-tipo').value = '';
        document.getElementById('ri-ex-adj-cantidad').value = '';
        document.getElementById('ri-ex-adj-costo').value = d.costoUnitario || 0;
        document.getElementById('ri-ex-adj-lote').value = '';
        document.getElementById('ri-ex-adj-observaciones').value = '';

        if (!this.modalEditarInstance) {
            this.modalEditarInstance = new bootstrap.Modal(document.getElementById('ri-ex-modal-editar'));
        }
        this.modalEditarInstance.show();
    },

    confirmarGuardarEdicion() {
        const idProducto = document.getElementById('ri-ex-edit-id-producto').value;
        const idBodega = document.getElementById('ri-ex-edit-id-bodega').value;
        const minEl = document.getElementById('ri-ex-edit-minimo');
        const maxEl = document.getElementById('ri-ex-edit-maximo');
        const catEl = document.getElementById('ri-ex-edit-categoria');
        const adjBodegaEl = document.getElementById('ri-ex-adj-bodega');
        const adjTipoEl = document.getElementById('ri-ex-adj-tipo');
        const adjCantidadEl = document.getElementById('ri-ex-adj-cantidad');
        const adjCostoEl = document.getElementById('ri-ex-adj-costo');
        const adjLoteEl = document.getElementById('ri-ex-adj-lote');
        const adjObsEl = document.getElementById('ri-ex-adj-observaciones');

        const nuevoMin = parseFloat(minEl.value || 0);
        const nuevoMax = parseFloat(maxEl.value || 0);
        const original_min = parseFloat(minEl.dataset.original || 0);
        const original_max = parseFloat(maxEl.dataset.original || 0);
        const cambioMinMax = nuevoMin !== original_min || nuevoMax !== original_max;
        const cambioCategoria = catEl.value !== (catEl.dataset.original || '');
        const cantidadAjuste = parseFloat(adjCantidadEl.value || 0);
        const hayAjuste = !!adjTipoEl.value && cantidadAjuste > 0;

        if (!cambioMinMax && !cambioCategoria && !hayAjuste) {
            this.modalEditarInstance.hide();
            return;
        }
        if (nuevoMax > 0 && nuevoMax < nuevoMin) {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Datos inválidos', text: 'El máximo no puede ser menor que el mínimo.' });
            else alert('El máximo no puede ser menor que el mínimo.');
            return;
        }
        if (adjTipoEl.value && cantidadAjuste <= 0) {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Datos inválidos', text: 'Ingresa una cantidad mayor a cero para el ajuste.' });
            else alert('Ingresa una cantidad mayor a cero para el ajuste.');
            return;
        }

        let resumen = '<ul class="text-start mb-0 ps-3">';
        if (cambioMinMax) {
            resumen += `<li>Mínimo: <b>${original_min}</b> &rarr; <b>${nuevoMin}</b></li>`;
            resumen += `<li>Máximo: <b>${original_max}</b> &rarr; <b>${nuevoMax}</b></li>`;
        }
        if (cambioCategoria) {
            const nuevaLabel = catEl.selectedOptions[0] ? catEl.selectedOptions[0].textContent : 'Sin categoría';
            resumen += `<li>Categoría: <b>${catEl.dataset.originalLabel}</b> &rarr; <b>${nuevaLabel}</b></li>`;
        }
        if (hayAjuste) {
            const bodegaLabel = adjBodegaEl.selectedOptions[0] ? adjBodegaEl.selectedOptions[0].textContent : '';
            const tipoLabel = adjTipoEl.value === 'entrada' ? 'Entrada' : 'Salida';
            resumen += `<li>Ajuste de inventario: <b>${tipoLabel}</b> de <b>${cantidadAjuste}</b> en bodega <b>${bodegaLabel}</b></li>`;
        }
        resumen += '</ul>';

        const ejecutarGuardado = () => {
            const llamadas = [];
            if (cambioMinMax) {
                llamadas.push(fetch(BASE_URL + '/' + RUTA_MODULO + '/actualizarMinMaxAjax', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ id_producto: idProducto, id_bodega: idBodega, stock_minimo: nuevoMin, stock_maximo: nuevoMax }).toString(),
                }).then(r => r.json()));
            }
            if (cambioCategoria) {
                llamadas.push(fetch(BASE_URL + '/' + RUTA_MODULO + '/actualizarCategoriaAjax', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ id_producto: idProducto, id_categoria: catEl.value }).toString(),
                }).then(r => r.json()));
            }
            if (hayAjuste) {
                llamadas.push(fetch(BASE_URL + '/' + RUTA_MODULO + '/ajustarInventarioAjax', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({
                        id_producto: idProducto, id_bodega: adjBodegaEl.value, tipo_movimiento: adjTipoEl.value,
                        cantidad: cantidadAjuste, costo_unitario: adjCostoEl.value || 0,
                        numero_lote: adjLoteEl.value || '', observaciones: adjObsEl.value || '',
                    }).toString(),
                }).then(r => r.json()));
            }

            Promise.all(llamadas).then(resultados => {
                const error = resultados.find(r => !r.ok);
                if (error) {
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: error.error || 'Ocurrió un error' });
                    else alert(error.error || 'No se pudo guardar');
                    return;
                }
                this.modalEditarInstance.hide();
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Cambios guardados', timer: 1800, showConfirmButton: false });
                this.generar();
            }).catch(err => {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo comunicar con el servidor.' });
                else alert('Error de conexión');
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Confirmar cambios',
                html: resumen,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
            }).then(result => { if (result.isConfirmed) ejecutarGuardado(); });
        } else if (confirm('¿Confirmas guardar estos cambios?')) {
            ejecutarGuardado();
        }
    },

    dibujarCabecera(modo) {
        const sortIcon = '<i class="bi bi-arrow-down-up small text-muted ms-1"></i>';
        const th2 = (label, campo, extra) => `<th class="sortable-header ${extra || ''}" data-sort="${campo}" data-col="${campo}">${label}${sortIcon}</th>`;
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            th += th2('Producto', 'producto_nombre', 'ps-3')
                + th2('Categoría', 'categoria_nombre')
                + th2('Bodega', 'bodega_nombre')
                + th2('Consignación', 'consignado', 'text-end')
                + th2('Stock', 'stock_actual', 'text-end')
                + th2('Stock Total', 'stock_total', 'text-end')
                + th2('Mínimo', 'stock_minimo', 'text-end')
                + th2('Máximo', 'stock_maximo', 'text-end')
                + th2('Costo Unit.', 'costo_unitario', 'text-end')
                + th2('Valor total', 'valor_total', 'text-end pe-3');
        } else {
            th += `<th class="ps-3">Grupo</th><th class="text-center">Productos</th>
                   <th class="text-end">Consignación</th><th class="text-end">Stock</th><th class="text-end">Stock Total</th>
                   <th class="text-end">Mínimo</th>
                   <th class="text-end">Costo Unit.</th><th class="text-end pe-3">Valor total</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-ex-thead').innerHTML = th;

        if (modo === 'NINGUNO' && typeof window.CMG_initSort === 'function') {
            window.CMG_initSort('reporte_inventarios_existencias', (col, dir) => {
                this.orden = col; this.dir = dir;
                this.generar();
            }, { container: '#ri-ex-thead', col: this.orden, dir: this.dir });
        }
        if (typeof window.initResizableColumns === 'function') {
            window.initResizableColumns();
        }
    },

    generar() {
        const modo = document.getElementById('ri-ex-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            id_bodega: 'ri-ex-bodega', id_categoria: 'ri-ex-categoria', id_marca: 'ri-ex-marca',
            id_producto: 'ri-ex-id-producto', estado_stock: 'ri-ex-estado', agrupar_por: 'ri-ex-agrupar',
            numero_lote: 'ri-ex-lote', nup: 'ri-ex-nup',
            fecha_caducidad_desde: 'ri-ex-caducidad-desde', fecha_caducidad_hasta: 'ri-ex-caducidad-hasta',
        });
        if (modo === 'NINGUNO' && this.orden) {
            params.set('orden', this.orden);
            params.set('dir', this.dir);
        }

        const tbody = document.getElementById('ri-ex-tbody');
        const colSpan = modo === 'NINGUNO' ? 10 : 8;
        tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('existencias', params, (res) => {
            tbody.innerHTML = res.rows;
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
        });
    },

    exportarExcel() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-ex-bodega', id_categoria: 'ri-ex-categoria', id_marca: 'ri-ex-marca',
            id_producto: 'ri-ex-id-producto', estado_stock: 'ri-ex-estado', agrupar_por: 'ri-ex-agrupar',
            numero_lote: 'ri-ex-lote', nup: 'ri-ex-nup',
            fecha_caducidad_desde: 'ri-ex-caducidad-desde', fecha_caducidad_hasta: 'ri-ex-caducidad-hasta',
        });
        params.set('tab', 'existencias');
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString(), '_blank');
    },
    exportarPDF() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-ex-bodega', id_categoria: 'ri-ex-categoria', id_marca: 'ri-ex-marca',
            id_producto: 'ri-ex-id-producto', estado_stock: 'ri-ex-estado', agrupar_por: 'ri-ex-agrupar',
            numero_lote: 'ri-ex-lote', nup: 'ri-ex-nup',
            fecha_caducidad_desde: 'ri-ex-caducidad-desde', fecha_caducidad_hasta: 'ri-ex-caducidad-hasta',
        });
        params.set('tab', 'existencias');
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params.toString(), '_blank');
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 2: MOVIMIENTOS (KARDEX)
// ════════════════════════════════════════════════════════════════════
window.RI_Movimientos = {
    limpiarProducto() {
        RI_limpiarBusqueda('ri-mv-search-producto', 'ri-mv-id-producto', 'ri-mv-producto-seleccionado');
        this.generar();
    },

    cambiarMesAnio() {
        const mes = document.getElementById('ri-mv-mes').value;
        const anio = document.getElementById('ri-mv-anio').value;
        if (!mes || !anio) return;

        if (anio === 'TODOS') {
            document.getElementById('ri-mv-fecha-desde').value = '';
            document.getElementById('ri-mv-fecha-hasta').value = '';
        } else if (mes === 'TODOS') {
            document.getElementById('ri-mv-fecha-desde').value = anio + '-01-01';
            document.getElementById('ri-mv-fecha-hasta').value = anio + '-12-31';
        } else {
            const ultimoDia = new Date(parseInt(anio), parseInt(mes), 0).getDate();
            document.getElementById('ri-mv-fecha-desde').value = `${anio}-${mes}-01`;
            document.getElementById('ri-mv-fecha-hasta').value = `${anio}-${mes}-${String(ultimoDia).padStart(2, '0')}`;
        }
        this.generar();
    },

    dibujarCabecera(modo) {
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            th += `<th class="ps-3">Fecha</th><th>Producto</th><th>Bodega</th><th class="text-center">Tipo</th>
                   <th>Origen</th><th class="text-end">Entradas</th><th class="text-end">Salidas</th><th class="text-end">Saldo</th>
                   <th class="text-end">Costo Unit.</th>
                   <th>Lote</th><th>Caducidad</th><th class="pe-3">Observaciones</th>`;
        } else {
            th += `<th class="ps-3">Grupo</th><th class="text-center">Movimientos</th>
                   <th class="text-end">Entradas</th><th class="text-end">Salidas</th>
                   <th class="text-end">Saldo neto</th><th class="text-end pe-3">Costo total</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-mv-thead').innerHTML = th;
    },

    generar() {
        const modo = document.getElementById('ri-mv-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            fecha_desde: 'ri-mv-fecha-desde', fecha_hasta: 'ri-mv-fecha-hasta',
            id_bodega: 'ri-mv-bodega', id_producto: 'ri-mv-id-producto',
            id_categoria: 'ri-mv-categoria', id_marca: 'ri-mv-marca',
            tipo_movimiento: 'ri-mv-tipo', referencia_tipo: 'ri-mv-origen',
            id_usuario: 'ri-mv-usuario', numero_lote: 'ri-mv-lote', nup: 'ri-mv-nup',
            agrupar_por: 'ri-mv-agrupar',
        });

        const tbody = document.getElementById('ri-mv-tbody');
        const colSpan = modo === 'NINGUNO' ? 12 : 6;
        tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('movimientos', params, (res) => {
            tbody.innerHTML = res.rows;
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
        });
    },

    exportarExcel() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString(), '_blank');
    },
    exportarPDF() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params.toString(), '_blank');
    },
    _filtros() {
        const params = RI_paramsFromIds({
            fecha_desde: 'ri-mv-fecha-desde', fecha_hasta: 'ri-mv-fecha-hasta',
            id_bodega: 'ri-mv-bodega', id_producto: 'ri-mv-id-producto',
            id_categoria: 'ri-mv-categoria', id_marca: 'ri-mv-marca',
            tipo_movimiento: 'ri-mv-tipo', referencia_tipo: 'ri-mv-origen',
            id_usuario: 'ri-mv-usuario', numero_lote: 'ri-mv-lote', nup: 'ri-mv-nup',
            agrupar_por: 'ri-mv-agrupar',
        });
        params.set('tab', 'movimientos');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 3: VALORIZACIÓN
// ════════════════════════════════════════════════════════════════════
window.RI_Valorizacion = {
    limpiarProducto() {
        RI_limpiarBusqueda('ri-va-search-producto', 'ri-va-id-producto', 'ri-va-producto-seleccionado');
        this.generar();
    },

    generar() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-va-bodega', id_categoria: 'ri-va-categoria', id_marca: 'ri-va-marca',
            id_producto: 'ri-va-id-producto', agrupar_por: 'ri-va-agrupar',
        });

        const tbody = document.getElementById('ri-va-tbody');
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('valorizacion', params, (res) => {
            tbody.innerHTML = res.rows;
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">${msg}</td></tr>`;
        });
    },

    exportarExcel() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString(), '_blank');
    },
    exportarPDF() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params.toString(), '_blank');
    },
    _filtros() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-va-bodega', id_categoria: 'ri-va-categoria', id_marca: 'ri-va-marca',
            id_producto: 'ri-va-id-producto', agrupar_por: 'ri-va-agrupar',
        });
        params.set('tab', 'valorizacion');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 4: CONSIGNACIONES
// ════════════════════════════════════════════════════════════════════
window.RI_Consignaciones = {
    limpiarCliente() {
        RI_limpiarBusqueda('ri-cv-search-cliente', 'ri-cv-id-cliente', 'ri-cv-cliente-seleccionado');
        this.generar();
    },
    limpiarProducto() {
        RI_limpiarBusqueda('ri-cv-search-producto', 'ri-cv-id-producto', 'ri-cv-producto-seleccionado');
        this.generar();
    },

    modalInstance: null,

    dibujarCabecera(modo) {
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            th += `<th class="ps-3">Fecha</th><th>Cliente</th><th>Vendedor</th>
                   <th class="text-center">Productos</th><th class="text-end">Saldo</th>
                   <th class="text-center pe-3">Estado</th>`;
        } else {
            th += `<th class="ps-3">Grupo</th><th class="text-center">Consignaciones</th>
                   <th class="text-end pe-3">Saldo</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-cv-thead').innerHTML = th;
    },

    verDetalle(idConsignacion) {
        if (!this.modalInstance) {
            this.modalInstance = new bootstrap.Modal(document.getElementById('ri-cv-modal-detalle'));
        }
        const tbody = document.getElementById('ri-cv-modal-tbody');
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;
        ['secuencial', 'fecha', 'cliente', 'vendedor', 'estado'].forEach(k => {
            document.getElementById('ri-cv-modal-' + k).textContent = '';
        });
        this.modalInstance.show();

        fetch(BASE_URL + '/' + RUTA_MODULO + '/verConsignacionDetalleAjax?id=' + encodeURIComponent(idConsignacion))
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${res.error || 'No se pudo cargar el detalle'}</td></tr>`;
                    return;
                }
                const c = res.cabecera;
                document.getElementById('ri-cv-modal-secuencial').textContent = c.secuencial || '';
                document.getElementById('ri-cv-modal-fecha').textContent = c.fecha_emision || '';
                document.getElementById('ri-cv-modal-cliente').textContent = c.cliente + (c.identificacion ? ` (${c.identificacion})` : '');
                document.getElementById('ri-cv-modal-vendedor').textContent = c.vendedor || '-';
                document.getElementById('ri-cv-modal-estado').textContent = c.estado || '';
                tbody.innerHTML = res.rows;
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Error al cargar el detalle</td></tr>`;
            });
    },

    generar() {
        const modo = document.getElementById('ri-cv-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            id_cliente: 'ri-cv-id-cliente', id_producto: 'ri-cv-id-producto',
            id_bodega: 'ri-cv-bodega', id_vendedor: 'ri-cv-vendedor',
            fecha_desde: 'ri-cv-fecha-desde', fecha_hasta: 'ri-cv-fecha-hasta',
            fecha_caducidad_desde: 'ri-cv-caducidad-desde', fecha_caducidad_hasta: 'ri-cv-caducidad-hasta',
            numero_lote: 'ri-cv-lote', nup: 'ri-cv-nup', secuencial: 'ri-cv-secuencial',
            estado: 'ri-cv-estado',
            incluir_liquidadas: 'ri-cv-incluir-liquidadas', agrupar_por: 'ri-cv-agrupar',
        });

        const tbody = document.getElementById('ri-cv-tbody');
        const colSpan = modo === 'NINGUNO' ? 6 : 3;
        tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('consignaciones', params, (res) => {
            tbody.innerHTML = res.rows;
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
        });
    },

    exportarExcel() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString(), '_blank');
    },
    exportarPDF() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params.toString(), '_blank');
    },
    _filtros() {
        const params = RI_paramsFromIds({
            id_cliente: 'ri-cv-id-cliente', id_producto: 'ri-cv-id-producto',
            id_bodega: 'ri-cv-bodega', id_vendedor: 'ri-cv-vendedor',
            fecha_desde: 'ri-cv-fecha-desde', fecha_hasta: 'ri-cv-fecha-hasta',
            fecha_caducidad_desde: 'ri-cv-caducidad-desde', fecha_caducidad_hasta: 'ri-cv-caducidad-hasta',
            numero_lote: 'ri-cv-lote', nup: 'ri-cv-nup', secuencial: 'ri-cv-secuencial',
            estado: 'ri-cv-estado',
            incluir_liquidadas: 'ri-cv-incluir-liquidadas', agrupar_por: 'ri-cv-agrupar',
        });
        params.set('tab', 'consignaciones');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 5: AUDITORÍA
// ════════════════════════════════════════════════════════════════════
window.RI_Auditoria = {
    limpiarProducto() {
        RI_limpiarBusqueda('ri-au-search-producto', 'ri-au-id-producto', 'ri-au-producto-seleccionado');
        this.generar();
    },

    generar() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-au-bodega', id_producto: 'ri-au-id-producto', buscar: 'ri-au-buscar',
        });

        const tbody = document.getElementById('ri-au-tbody');
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('auditoria', params, (res) => {
            tbody.innerHTML = res.rows;
            const info = document.getElementById('ri-au-info-total');
            const total = (res.kpis && res.kpis.total_discrepancias) || 0;
            info.textContent = total > 0
                ? `${total} discrepancia${total === 1 ? '' : 's'} encontrada${total === 1 ? '' : 's'}`
                : 'Sin discrepancias — el stock guardado coincide con el Kardex.';
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">${msg}</td></tr>`;
        });
    },

    corregir(btn) {
        const d = btn.dataset;
        const cacheado = parseFloat(d.cacheado || 0);
        const real = parseFloat(d.real || 0);

        const ejecutar = () => {
            btn.disabled = true;
            const params = new URLSearchParams({ id_producto: d.idProducto, id_bodega: d.idBodega });
            fetch(BASE_URL + '/' + RUTA_MODULO + '/corregirStockAuditoriaAjax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: params.toString(),
            })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    btn.disabled = false;
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'No se pudo corregir', text: res.error || 'Error desconocido' });
                    else alert(res.error || 'Error desconocido');
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Stock corregido', timer: 1500, showConfirmButton: false });
                }
                window.RI_Auditoria.generar();
            })
            .catch(err => {
                btn.disabled = false;
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo corregir el stock.' });
                else alert('No se pudo corregir el stock.');
            });
        };

        const mensaje = `<p class="mb-2">${d.productoNombre} — ${d.bodegaNombre}</p>
            <p class="mb-0">Guardado: <b>${cacheado}</b> &rarr; Real (Kardex): <b>${real}</b></p>`;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: '¿Corregir stock?',
                html: mensaje,
                showCancelButton: true,
                confirmButtonText: 'Sí, corregir',
                cancelButtonText: 'Cancelar',
            }).then(result => { if (result.isConfirmed) ejecutar(); });
        } else if (confirm('¿Corregir stock de ' + cacheado + ' a ' + real + '?')) {
            ejecutar();
        }
    },
};

// ════════════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {
    if (typeof aplicarFavoritosModal === 'function') {
        aplicarFavoritosModal();
    }

    const mesEl = document.getElementById('ri-mv-mes');
    if (mesEl && (!mesEl.value || mesEl.value === 'TODOS')) {
        mesEl.value = (new Date().getMonth() + 1).toString().padStart(2, '0');
    }

    RI_setupAutocomplete('ri-ex-search-producto', 'ri-ex-dropdown-producto', 'ri-ex-id-producto', 'ri-ex-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Existencias.generar());
    RI_setupAutocomplete('ri-mv-search-producto', 'ri-mv-dropdown-producto', 'ri-mv-id-producto', 'ri-mv-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Movimientos.generar());
    RI_setupAutocomplete('ri-va-search-producto', 'ri-va-dropdown-producto', 'ri-va-id-producto', 'ri-va-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Valorizacion.generar());
    RI_setupAutocomplete('ri-cv-search-producto', 'ri-cv-dropdown-producto', 'ri-cv-id-producto', 'ri-cv-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Consignaciones.generar());
    RI_setupAutocomplete('ri-cv-search-cliente', 'ri-cv-dropdown-cliente', 'ri-cv-id-cliente', 'ri-cv-cliente-seleccionado', '/getClientesAjax?q=', () => window.RI_Consignaciones.generar());
    RI_setupAutocomplete('ri-au-search-producto', 'ri-au-dropdown-producto', 'ri-au-id-producto', 'ri-au-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Auditoria.generar());
});
