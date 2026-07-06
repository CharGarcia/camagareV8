'use strict';

// ─── CONFIGURACIÓN INICIAL ───
let _ivaDefault = 15;
const CMG_TIPOS_MASCARA = ['01', '03', '04', '05', '06', '09', '11', '12', '15', '16', '18', '19', '20', '21', '41', '42', '43', '47', '48'];

// Verificar si la empresa es Persona Natural
// Verificar si la empresa es Persona Natural (Acepta '1' o '01')
const _esPersonaNatural = (window.CMG_empresa?.tipo == '1' || window.CMG_empresa?.tipo == '01');

document.addEventListener('DOMContentLoaded', () => {
    if (window.CMG_tarifasIva && window.CMG_tarifasIva.length > 0) {
        const tDefault = window.CMG_tarifasIva.find(t => parseFloat(t.porcentaje_iva) > 0) || window.CMG_tarifasIva[0];
        _ivaDefault = parseFloat(tDefault.porcentaje_iva);
    }
    

    if (_esPersonaNatural) {
        // Ocultar campos no requeridos para Persona Natural
        const selectors = [
            '#mcSustento', 
            '#mcAutorizacion', 
            '#mcAutorizacionDesde', 
            '#mcAutorizacionHasta', 
            '#mcFechaCaducidad'
        ];
        selectors.forEach(s => {
            const el = document.querySelector(s);
            if (el) {
                const col = el.closest('[class*="col-"]');
                if (col) col.classList.add('d-none');
            }
        });
    }

    // Flujo de foco con Enter en el modal
    const modal = document.getElementById('modalCompra');
    if (modal) {
        modal.addEventListener('keydown', function(e) {
            const isInputOrSelect = ['INPUT', 'SELECT', 'TEXTAREA'].includes(e.target.tagName);
            if (e.key === 'Enter' && isInputOrSelect && !e.target.classList.contains('input-descripcion')) {
                e.preventDefault();
                const formInputs = Array.from(modal.querySelectorAll('input, select, textarea')).filter(i => {
                    const style = window.getComputedStyle(i);
                    return i.type !== 'hidden' && !i.disabled && style.display !== 'none' && style.visibility !== 'hidden';
                });
                const index = formInputs.indexOf(e.target);
                if (index > -1 && index < formInputs.length - 1) {
                    formInputs[index + 1].focus();
                    if (formInputs[index + 1].tagName === 'INPUT') formInputs[index + 1].select();
                }
            }
        });

        // Inicializar botón de favoritos del modal
        const btnFav = document.getElementById('btnConfigurarFavoritosCompra');
        if (btnFav && typeof CMG_abrirConfiguracionFavoritos === 'function') {
            btnFav.onclick = () => CMG_abrirConfiguracionFavoritos('modulos/compras');
        }
        
        // Iniciar auto-guardado en LocalStorage
        if (typeof mcRegistrarAutoGuardado === 'function') {
            mcRegistrarAutoGuardado();
        }

        // Sincronizar inventario al cambiar a la pestaña de inventario
        const tabInv = document.getElementById('tab-inventario-tab');
            tabInv.addEventListener('shown.bs.tab', function() {
                mcCargarStatusInventario().then(() => {
                    mcSincronizarInventario();
                });
            });
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// MODAL — ABRIR NUEVO
// ─────────────────────────────────────────────────────────────────────────────
window.abrirModalCompraCrear = function () {
    try {
        CMG_resetModal();
        document.getElementById('mcTitulo').textContent = 'Nueva Compra';
        const btnGuardar = document.getElementById('btnGuardarCompra');
        if (btnGuardar) {
            btnGuardar.classList.remove('d-none');
            btnGuardar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }
        document.getElementById('btnEliminarCompra').classList.add('d-none');
        // Barra superior
        document.getElementById('btnEliminarCompraBar')?.classList.add('d-none');

        const d_now = new Date();
        const hoy = d_now.getFullYear() + '-' + String(d_now.getMonth() + 1).padStart(2, '0') + '-' + String(d_now.getDate()).padStart(2, '0');
        document.getElementById('mcFechaEmision').value  = hoy;
        document.getElementById('mcFechaRegistro').value = hoy;
        
        // Aplicar favoritos usando la función estándar
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalCompra');
        }
        
        // Aplicar los límites de autorización según el favorito seleccionado
        if (typeof aplicarLimiteAutorizacion === 'function') aplicarLimiteAutorizacion();
        
        // Cargar sustentos dependientes
        const tipoVal = document.getElementById('mcTipoComprobante').value;
        if (tipoVal) {
            const estrellaSustento = document.querySelector('.btn-favorito[data-target="#mcSustento"]');
            let sustentoId = null;
            if (estrellaSustento && typeof APP_FAVORITOS !== 'undefined' && APP_FAVORITOS[estrellaSustento.dataset.campo]) {
                sustentoId = APP_FAVORITOS[estrellaSustento.dataset.campo];
            }
            CMG_cargarSustentos(tipoVal, sustentoId);
        }

        // Verificar si hay borrador y mostrar el mismo aviso que en ventas
        if (typeof mcCheckBorrador === 'function' && mcCheckBorrador()) {
            return; // El modal se abrirá después de la decisión del usuario
        }
        
        const modalEl = document.getElementById('modalCompra');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    } catch (e) {
        console.error('Error al abrir modal de compra:', e);
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// MODAL — ABRIR EDITAR (click fila)
// ─────────────────────────────────────────────────────────────────────────────
window.abrirModalCompra = function (el) {
    try {
        const row = JSON.parse(el.dataset.row);
        CMG_resetModal();
        document.getElementById('mcTitulo').textContent = 'Compra #' + (row.establecimiento_prov||'') + '-' + (row.punto_emision_prov||'') + '-' + (row.secuencial_prov||'') + ' - ' + (row.proveedor_nombre || '');
        // Cargar datos completos
        fetch(`${window.CMG_urlBase}/getCompraAjax?id=${row.id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
                CMG_poblarModal(res.data);
            }).catch(e => console.error(e));

        const modalEl = document.getElementById('modalCompra');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    } catch (e) {
        console.error('Error al abrir modal para editar:', e);
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// PESTAÑA ASIENTO CONTABLE (vista previa reutilizable)
// ─────────────────────────────────────────────────────────────────────────────
let _mcAsientoTab = null;
function mcAsientoTab() {
    if (!_mcAsientoTab && typeof window.crearAsientoTab === 'function') {
        _mcAsientoTab = window.crearAsientoTab({
            tbodyId: 'mc-asiento-tbody',
            debeId:  'mc-asiento-debe',
            haberId: 'mc-asiento-haber',
            difId:   'mc-asiento-dif',
            badgeId: 'mc-asiento-badge',
            countId: 'mc-asiento-count',
            statusId: 'mc-asiento-status',
            previewUrl: `${window.CMG_urlBase}/getAsientoSugeridoAjax`,
            cuentasUrl: `${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`
        });
        const addBtn = document.getElementById('mc-asiento-add');
        if (addBtn) addBtn.addEventListener('click', () => _mcAsientoTab.agregarLinea());
    }
    return _mcAsientoTab;
}

document.addEventListener('DOMContentLoaded', function () {
    const btnTab = document.getElementById('tab_asiento');
    if (btnTab) {
        btnTab.addEventListener('shown.bs.tab', function () {
            const tab = mcAsientoTab();
            if (tab) tab.cargar(document.getElementById('mcId') ? document.getElementById('mcId').value : 0);
        });
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// POBLAR MODAL CON DATOS EXISTENTES
// ─────────────────────────────────────────────────────────────────────────────
function CMG_poblarModal(d) {
    document.getElementById('mcId').value                = d.id || '';
    document.getElementById('mcIdProveedor').value       = d.id_proveedor || '';
    document.getElementById('mcBuscarProveedor').value   = d.proveedor_nombre || '';
    document.getElementById('mcTipoComprobante').value   = d.tipo_comprobante || '';
    document.getElementById('mcIdEstablecimiento').value = d.id_establecimiento || '';
    
    // Unificar número
    const num = (d.establecimiento_prov || '') + '-' + (d.punto_emision_prov || '') + '-' + (d.secuencial_prov || '');
    document.getElementById('mcNumeroComprobante').value = num;

    document.getElementById('mcAutorizacion').value      = d.numero_autorizacion || '';
    document.getElementById('mcAutorizacionDesde').value = d.autorizacion_desde || '';
    document.getElementById('mcAutorizacionHasta').value = d.autorizacion_hasta || '';
    document.getElementById('mcFechaCaducidad').value    = d.fecha_caducidad ? d.fecha_caducidad.slice(0,10) : '';

    document.getElementById('mcTipoRegistro').value      = d.tipo_registro || 'fisica';
    if (typeof aplicarLimiteAutorizacion === 'function') aplicarLimiteAutorizacion();
    document.getElementById('mcDeducible').value         = d.deducible || 'declaracion_iva';
    document.getElementById('mcDocumentoModificado').value = d.documento_modificado || '';
    document.getElementById('mcMotivo').value            = d.motivo || '';

    document.getElementById('mcFechaEmision').value      = d.fecha_emision ? d.fecha_emision.slice(0,10) : '';
    document.getElementById('mcFechaRegistro').value     = d.fecha_registro ? d.fecha_registro.slice(0,10) : '';
    document.getElementById('mcParteRelacionada').checked = (d.parte_relacionada === true || d.parte_relacionada === 't');
    document.getElementById('mcObservaciones').value     = d.observaciones || '';
    if (document.getElementById('mcInputPropina')) {
        document.getElementById('mcInputPropina').value  = d.propina || 0;
    }

    // Cargar sustentos filtrados y seleccionar el actual
    CMG_cargarSustentos(d.tipo_comprobante, d.id_sustento_tributario);


    
    // Detalles
    document.getElementById('tbodyDetalle').innerHTML = '';
    (d.detalles || []).forEach(det => CMG_agregarFilaDetalle(det));
    CMG_recalcularTotales();
    mcCargarStatusInventario().then(() => {
        mcSincronizarInventario();
    });

    // Pagos
    const containerPagos = document.getElementById('mc-container-pagos-sri');
    if (containerPagos) {
        containerPagos.innerHTML = '';
        if (d.pagos && d.pagos.length) {
            d.pagos.forEach(p => CMG_agregarFormaPagoSRI(p.forma_pago, p.total));
        } else {
            CMG_agregarFormaPagoSRI('', d.importe_total || 0);
        }
    }

    // Crédito
    if (d.pagos && d.pagos.length) {
        document.getElementById('mcDiasCredito').value = d.pagos[0].plazo || 0;
        document.getElementById('mcPlazoSRI').value = d.pagos[0].unidad_tiempo || 'Días';
    }

    CMG_recalcularTotales();

    // Botones según permisos
    console.log('[DEBUG] Estado:', d.estado, 'Permisos:', window.CMG_perm);
    
    const btnGuardar = document.getElementById('btnGuardarCompra');
    if (btnGuardar) {
        btnGuardar.classList.remove('d-none');
    }

    const btnEliminar = document.getElementById('btnEliminarCompra');
    if (btnEliminar) {
        btnEliminar.classList.remove('d-none');
    }

    // Guardar id actual
    document.getElementById('modalCompra').dataset.id = d.id;
    
    // Cargar retenciones vinculadas (refresca botón y lista)
    if (typeof window.CMG_cargarRetencionesCompra === 'function') {
        window.CMG_cargarRetencionesCompra();
    }
    
    // Cargar status de inventario y sincronizar tabla
    if (typeof window.mcCargarStatusInventario === 'function') {
        window.mcCargarStatusInventario();
    }
    
    // Bloquear campos si es electrónica
    mcActualizarBloqueoCampos();

    // Mostrar/ocultar botones XML y PDF (ambos requieren XML del comprobante)
    const tieneXml = !!(d.detalle_xml && d.detalle_xml.trim().length > 0);
    const btnXml = document.getElementById('mcBtnDescargarXml');
    if (btnXml) btnXml.classList.toggle('d-none', !tieneXml);
    const btnPdf = document.getElementById('mcBtnPdf');
    if (btnPdf) btnPdf.classList.toggle('d-none', !tieneXml);
}

function mcActualizarBloqueoCampos() {
    const regEl = document.getElementById('mcTipoRegistro');
    if (!regEl) return;
    const isElectronico = regEl.value === 'electronico';
    
    // IDs de campos que SIEMPRE deben ser editables (según pedido del usuario)
    const permitidos = ['mcDeducible', 'mcMotivo', 'mcObservaciones', 'mcInputPropina'];
    const checkboxParteRel = 'mcParteRelacionada';

    // Bloquear campos de cabecera
    const selectors = [
        '#mcIdProveedor', '#mcBuscarProveedor', '#mcTipoComprobante', '#mcNumeroComprobante',
        '#mcAutorizacion', '#mcAutorizacionDesde', '#mcAutorizacionHasta', '#mcFechaCaducidad',
        '#mcFechaEmision', '#mcFechaRegistro', '#mcTipoRegistro'
    ];
    
    selectors.forEach(s => {
        const el = document.querySelector(s);
        if (el) el.disabled = isElectronico;
    });

    // Especial para Deducible y Parte Relacionada (Permitidos)
    document.getElementById('mcDeducible').disabled = false;
    document.getElementById('mcMotivo').disabled = false;
    document.getElementById('mcObservaciones').disabled = false;
    document.getElementById('mcParteRelacionada').disabled = false;
    if (document.getElementById('mcSustento')) document.getElementById('mcSustento').disabled = false;

    // Bloquear tabla de detalles
    document.querySelectorAll('#tbodyDetalle input, #tbodyDetalle select, #tbodyDetalle button').forEach(el => {
        el.disabled = isElectronico;
    });

    // Botones de agregar línea y buscador
    const btnAgregar = document.querySelector('button[onclick="CMG_agregarItemLibre()"]');
    if (btnAgregar) btnAgregar.disabled = isElectronico;
    
    const inputBusq = document.getElementById('inputBuscarProductoCompra');
    if (inputBusq) inputBusq.disabled = isElectronico;

    // Bloquear pestaña de Pagos SRI y Crédito
    const containerPagos = document.getElementById('mc-container-pagos-sri');
    if (containerPagos) {
        containerPagos.querySelectorAll('input, select, button').forEach(el => el.disabled = isElectronico);
    }
    
    const btnAddPago = document.querySelector('button[onclick="CMG_agregarFormaPagoSRI()"]');
    if (btnAddPago) btnAddPago.disabled = isElectronico;

    const diasCredito = document.getElementById('mcDiasCredito');
    if (diasCredito) diasCredito.disabled = isElectronico;

    const plazoSRI = document.getElementById('mcPlazoSRI');
    if (plazoSRI) plazoSRI.disabled = isElectronico;
}

// ─────────────────────────────────────────────────────────────────────────────
// RESET MODAL
// ─────────────────────────────────────────────────────────────────────────────
function CMG_resetModal() {
    const ids = [
        'mcId', 'mcIdProveedor', 'mcBuscarProveedor', 'mcTipoComprobante', 'mcNumeroComprobante',
        'mcAutorizacion', 'mcAutorizacionDesde', 'mcAutorizacionHasta', 'mcFechaCaducidad',
        'mcDocumentoModificado', 'mcMotivo', 'mcFechaEmision', 'mcObservaciones'
    ];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    
    if (document.getElementById('mcIdEstablecimiento')) {
        document.getElementById('mcIdEstablecimiento').value = window.CMG_sucursal?.id || '';
    }

    if (document.getElementById('mcSustento')) {
        document.getElementById('mcSustento').innerHTML = '<option value="">-- Seleccione Comprobante primero --</option>';
    }
    if (document.getElementById('mcTipoRegistro')) document.getElementById('mcTipoRegistro').value = 'fisica';
    if (document.getElementById('mcDeducible')) document.getElementById('mcDeducible').value = 'declaracion_iva';
    const d_now = new Date();
    const hoy = d_now.getFullYear() + '-' + String(d_now.getMonth() + 1).padStart(2, '0') + '-' + String(d_now.getDate()).padStart(2, '0');
    if (document.getElementById('mcFechaRegistro')) document.getElementById('mcFechaRegistro').value = hoy;
    if (document.getElementById('mcParteRelacionada')) document.getElementById('mcParteRelacionada').checked = false;
    
    if (document.getElementById('mcInputPropina')) {
        document.getElementById('mcInputPropina').value = '0.00';
    }
    
    if (document.getElementById('tbodyDetalle')) document.getElementById('tbodyDetalle').innerHTML = '';
    if (document.getElementById('mc-container-pagos-sri')) document.getElementById('mc-container-pagos-sri').innerHTML = '';
    
    CMG_agregarFormaPagoSRI('', 0);

    if (document.getElementById('mcDiasCredito')) document.getElementById('mcDiasCredito').value = 0;
    if (document.getElementById('mcPlazoSRI')) document.getElementById('mcPlazoSRI').value = 'Días';

    const modal = document.getElementById('modalCompra');
    if (modal) modal.dataset.id = '';
    
    // Limpiar tablas secundarias
    const tbodyRet = document.getElementById('mc-tbody-retenciones');
    if (tbodyRet) tbodyRet.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-file-earmark-text d-block fs-3 mb-2"></i>No hay retenciones registradas</td></tr>';
    
    const tbodyInvProc = document.getElementById('mc-tbody-inventario-procesado');
    if (tbodyInvProc) tbodyInvProc.innerHTML = '';
    
    const contInvProc = document.getElementById('mc-inventario-procesado');
    if (contInvProc) contInvProc.classList.add('d-none');
    
    const tbodyInv = document.getElementById('mc-tbody-inventario');
    if (tbodyInv) tbodyInv.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="bi bi-box-seam d-block fs-3 mb-2"></i>Agregue productos a la compra para verlos aquí</td></tr>';

    // Reset botones barra superior
    document.getElementById('btnEliminarCompraBar')?.classList.add('d-none');

    CMG_recalcularTotales();

    // Ocultar botón XML
    const btnXml = document.getElementById('mcBtnDescargarXml');
    if (btnXml) btnXml.classList.add('d-none');

    // Ocultar botón PDF (compra nueva / sin guardar)
    const btnPdf = document.getElementById('mcBtnPdf');
    if (btnPdf) btnPdf.classList.add('d-none');
    
    // Ir a primera pestaña
    const tabDetalle = document.getElementById('tab-detalle-tab') || document.getElementById('tab_compra');
    if (tabDetalle) {
        bootstrap.Tab.getOrCreateInstance(tabDetalle).show();
    }
    mcCargarStatusInventario();
}

// ─────────────────────────────────────────────────────────────────────────────
// MÁSCARA Y EVENTOS DE CAMPOS
// ─────────────────────────────────────────────────────────────────────────────

// Máscara 000-000-000000000 para número de comprobante
document.getElementById('mcNumeroComprobante').addEventListener('input', function(e) {
    let val = this.value.replace(/\D/g, '');
    let formatted = '';
    if (val.length > 0) formatted += val.substring(0, 3);
    if (val.length > 3) formatted += '-' + val.substring(3, 6);
    if (val.length > 6) formatted += '-' + val.substring(6, 15);
    this.value = formatted;
});

// Autocompletar con ceros al perder el foco
document.getElementById('mcNumeroComprobante').addEventListener('blur', function() {
    let parts = this.value.split('-');
    if (parts.length > 0 && parts[0].length > 0) parts[0] = parts[0].padStart(3, '0');
    if (parts.length > 1 && parts[1].length > 0) parts[1] = parts[1].padStart(3, '0');
    if (parts.length > 2 && parts[2].length > 0) parts[2] = parts[2].padStart(9, '0');
    this.value = parts.join('-');
});

document.getElementById('mcAutorizacionDesde').addEventListener('blur', function() {
    if (this.value) this.value = this.value.padStart(9, '0');
});

document.getElementById('mcAutorizacionHasta').addEventListener('blur', function() {
    if (this.value) this.value = this.value.padStart(9, '0');
});

// Solo números para autorización con límite dependiente del tipo de registro
function aplicarLimiteAutorizacion() {
    const elAuth = document.getElementById('mcAutorizacion');
    const tipoRegistro = document.getElementById('mcTipoRegistro').value;
    const maxLen = (tipoRegistro === 'fisica') ? 10 : 49;
    
    let val = elAuth.value.replace(/\D/g, '');
    if (val.length > maxLen) {
        val = val.substring(0, maxLen);
    }
    elAuth.value = val;
    elAuth.setAttribute('maxlength', maxLen);
}

document.getElementById('mcAutorizacion').addEventListener('input', aplicarLimiteAutorizacion);
document.getElementById('mcTipoRegistro').addEventListener('change', aplicarLimiteAutorizacion);

// Filtrado de sustento tributario
document.getElementById('mcTipoComprobante').addEventListener('change', function() {
    const val = this.value;
    CMG_cargarSustentos(val);
    
    // Mostrar/ocultar campos de modificación (04 = Nota de Crédito, 05 = Nota de Débito)
    const esModificativo = ['04', '05'].includes(val);
    document.getElementById('mcDivModificados').classList.toggle('d-none', !esModificativo);
});

// Máscara para documento modificado
document.getElementById('mcDocumentoModificado').addEventListener('input', function(e) {
    let val = this.value.replace(/\D/g, '');
    let formatted = '';
    if (val.length > 0) formatted += val.substring(0, 3);
    if (val.length > 3) formatted += '-' + val.substring(3, 6);
    if (val.length > 6) formatted += '-' + val.substring(6, 15);
    this.value = formatted;
});

// Autocompletar con ceros al perder el foco
document.getElementById('mcDocumentoModificado').addEventListener('blur', function() {
    let parts = this.value.split('-');
    if (parts.length > 0 && parts[0].length > 0) parts[0] = parts[0].padStart(3, '0');
    if (parts.length > 1 && parts[1].length > 0) parts[1] = parts[1].padStart(3, '0');
    if (parts.length > 2 && parts[2].length > 0) parts[2] = parts[2].padStart(9, '0');
    this.value = parts.join('-');
});

async function CMG_cargarSustentos(tipo, selectedId = null) {
    const el = document.getElementById('mcSustento');
    
    // Actualizar visibilidad de modificados por seguridad
    const esModificativo = ['04', '05'].includes(tipo);
    document.getElementById('mcDivModificados').classList.toggle('d-none', !esModificativo);

    if (!tipo) {
        el.innerHTML = '<option value="">-- Seleccione Comprobante primero --</option>';
        return;
    }

    el.innerHTML = '<option value="">Cargando...</option>';
    try {
        const res  = await fetch(`${window.CMG_urlBase}/getSustentosAjax?tipo=${tipo}`);
        const data = await res.json();
        if (data.ok) {
            el.innerHTML = '<option value="">-- Seleccione --</option>' + 
                data.data.map(s => `<option value="${s.id}" ${selectedId == s.id ? 'selected' : ''}>${s.codigo} - ${s.nombre}</option>`).join('');
        } else {
            el.innerHTML = '<option value="">Error al cargar</option>';
        }
    } catch(e) {
        el.innerHTML = '<option value="">Error de conexión</option>';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PROVEEDOR — BÚSQUEDA AJAX
// ─────────────────────────────────────────────────────────────────────────────
let _timerProv;
document.getElementById('mcBuscarProveedor').addEventListener('input', function() {
    clearTimeout(_timerProv);
    const q = this.value.trim();
    const lista = document.getElementById('mcListaProveedores');
    if (q.length < 2) { lista.classList.add('d-none'); return; }
    _timerProv = setTimeout(() => CMG_buscarProveedores(q), 300);
});

// Limpiar proveedor con Backspace/Delete o si queda vacío
document.getElementById('mcBuscarProveedor').addEventListener('keydown', function(e) {
    if (['Backspace', 'Delete'].includes(e.key)) {
        this.value = ''; // Limpiar el texto visible
        document.getElementById('mcIdProveedor').value = ''; // Limpiar ID oculto
        // Restablecer deducible por defecto
        document.getElementById('mcDeducible').value = 'declaracion_iva';
        // Ocultar lista si estaba abierta
        document.getElementById('mcListaProveedores').classList.add('d-none');
    }
});

document.getElementById('mcBuscarProveedor').addEventListener('blur', function() {
    if (this.value.trim() === '') {
        document.getElementById('mcIdProveedor').value = '';
        document.getElementById('mcDeducible').value = 'declaracion_iva';
    }
});

async function CMG_buscarProveedores(q) {
    const lista = document.getElementById('mcListaProveedores');
    try {
        const res  = await fetch(`${window.CMG_urlBase}/getProveedoresAjax?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (data.ok) {
            lista.innerHTML = '';
            if (!data.data.length) {
                lista.innerHTML = '<div class="list-group-item small text-muted">No se encontraron resultados</div>';
            } else {
                data.data.forEach(p => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
                    btn.innerHTML = `<strong>${_esc(p.identificacion)}</strong> — ${_esc(p.nombre)}`;
                    btn.onclick = () => CMG_seleccionarProveedor(p);
                    lista.appendChild(btn);
                });
            }
            lista.classList.remove('d-none');
        }
    } catch (e) {
        console.error('Error buscar proveedores:', e);
    }
}

window.CMG_seleccionarProveedor = function(p) {
    document.getElementById('mcIdProveedor').value     = p.id;
    document.getElementById('mcBuscarProveedor').value = p.nombre;
    document.getElementById('mcListaProveedores').classList.add('d-none');
    
    // Si el proveedor tiene Cédula (SRI: 05), sugerir Gasto Personal
    if (p.tipo_id === '05') {
        document.getElementById('mcDeducible').value = 'gasto_personal';
    } else {
        document.getElementById('mcDeducible').value = 'declaracion_iva';
    }

    // Auto-completar Información de Crédito y Parte Relacionada
    const diasCredito = document.getElementById('mcDiasCredito');
    const plazoSRI    = document.getElementById('mcPlazoSRI');
    const relacionada = document.getElementById('mcParteRelacionada');

    if (diasCredito) diasCredito.value = p.plazo || 0;
    if (plazoSRI)    plazoSRI.value    = (p.unidad_tiempo || 'DIAS').toLowerCase();
    if (relacionada) relacionada.checked = (p.relacionado === true || p.relacionado === 'true' || p.relacionado === 't');

    document.getElementById('mcTipoComprobante').focus();
};
// Cerrar lista proveedor al hacer click fuera
document.addEventListener('click', e => {
    if (!e.target.closest('#mcBuscarProveedor') && !e.target.closest('#mcListaProveedores')) {
        document.getElementById('mcListaProveedores').classList.add('d-none');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// INTEGRACIÓN CON MODALES DE PROVEEDOR / PRODUCTO (creación rápida desde la compra)
// ─────────────────────────────────────────────────────────────────────────────

// Al guardar un proveedor desde el modal compartido, seleccionarlo en la compra
document.addEventListener('proveedorGuardado', (e) => {
    const res = e.detail;
    if (!res || !res.ok || !res.data) return;
    // El modal de compras solo debe reaccionar si está abierto
    const modalEl = document.getElementById('modalCompra');
    if (!modalEl || !modalEl.classList.contains('show')) return;

    CMG_seleccionarProveedor({
        id:             res.id || res.data.id,
        nombre:         res.nombre || res.data.razon_social || res.data.nombre || '',
        tipo_id:        res.data.tipo_id_proveedor || '',
        plazo:          res.data.plazo || 0,
        unidad_tiempo:  res.data.unidad_tiempo || 'DIAS',
        relacionado:    res.data.relacionado
    });
});

// Al guardar un producto desde el modal compartido, agregarlo como línea de la compra
document.addEventListener('productoGuardado', async (e) => {
    const res = e.detail;
    if (!res || !res.ok || !res.id) return;
    const modalEl = document.getElementById('modalCompra');
    if (!modalEl || !modalEl.classList.contains('show')) return;

    // El endpoint de creación solo devuelve el id; recuperamos el producto completo
    // buscándolo por su código (el formulario aún conserva el valor tras guardar).
    const codigo = document.getElementById('prod_codigo')?.value?.trim() || '';
    const nombre = document.getElementById('prod_nombre')?.value?.trim() || '';
    const termino = codigo || nombre;
    if (!termino) return;

    try {
        const resp = await fetch(`${window.CMG_urlBase}/getProductosAjax?q=${encodeURIComponent(termino)}`);
        const data = await resp.json();
        if (data.ok && Array.isArray(data.data)) {
            const prod = data.data.find(p => String(p.id) === String(res.id)) || data.data[0];
            if (prod) {
                CMG_seleccionarProducto(prod);
                return;
            }
        }
    } catch (err) {
        console.error('Error al recuperar producto recién creado:', err);
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success',
            title: 'Producto creado. Búscalo en el catálogo para agregarlo.',
            showConfirmButton: false, timer: 2500 });
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// DETALLE — BÚSQUEDA DE PRODUCTOS
// ─────────────────────────────────────────────────────────────────────────────
// PRODUCTO — BÚSQUEDA AJAX
// ─────────────────────────────────────────────────────────────────────────────
let _timerProd;
document.getElementById('inputBuscarProductoCompra').addEventListener('input', function() {
    clearTimeout(_timerProd);
    const q = this.value.trim();
    const lista = document.getElementById('listaProductosCompra');
    if (q.length < 2) { lista.classList.add('d-none'); return; }
    _timerProd = setTimeout(() => CMG_buscarProductos(q), 300);
});

async function CMG_buscarProductos(q) {
    const lista = document.getElementById('listaProductosCompra');
    try {
        const res  = await fetch(`${window.CMG_urlBase}/getProductosAjax?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (data.ok) {
            lista.innerHTML = '';
            if (!data.data.length) {
                lista.innerHTML = '<div class="list-group-item small text-muted">No se encontraron productos</div>';
            } else {
                data.data.forEach(p => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
                    btn.innerHTML = `<div class="d-flex justify-content-between">
                        <span>${_esc(p.nombre)}</span>
                        <small class="text-muted">${_esc(p.codigo_principal || p.codigo || '')}</small>
                    </div>`;
                    btn.onclick = () => {
                        CMG_seleccionarProducto(p);
                        document.getElementById('inputBuscarProductoCompra').value = '';
                        lista.classList.add('d-none');
                        // El foco se queda en el buscador por si desea agregar otro, o podemos moverlo a la cantidad de la última fila
                        const rows = document.querySelectorAll('#tbodyDetalle tr');
                        if (rows.length > 0) {
                            rows[rows.length - 1].querySelector('.input-cantidad').focus();
                        }
                    };
                    lista.appendChild(btn);
                });
            }
            lista.classList.remove('d-none');
        }
    } catch (e) {
        console.error('Error buscar productos:', e);
    }
}

let _idxAVincularInv = null;

window.CMG_seleccionarProducto = function(p) {
    if (_idxAVincularInv !== null) {
        const tr = document.querySelector(`#tbodyDetalle tr[data-idx="${_idxAVincularInv}"]`);
        if (tr) {
            tr.querySelector('.input-id-producto').value = p.id;
            tr.querySelector('.input-id-medida').value = p.id_medida || '';
            tr.querySelector('.input-id-tipo-medida').value = p.id_tipo_medida || '';
            tr.dataset.idProducto = p.id;
            tr.dataset.productoNombre = p.nombre;
            
            const descInput = tr.querySelector('.input-descripcion');
            if (descInput && !descInput.value.trim()) {
                descInput.value = p.nombre;
            }

            const trInv = document.querySelector(`#mc-tbody-inventario tr[data-index="${_idxAVincularInv}"]`);
            if (trInv) {
                trInv.querySelector('.input-inv-id-producto').value = p.id;
                trInv.dataset.idProducto = p.id;

                const spanNombre = trInv.querySelector('.text-truncate');
                if (spanNombre) {
                    spanNombre.classList.add('text-primary', 'fw-bold');
                    spanNombre.innerHTML = `<i class="bi bi-tag-fill me-1"></i>${_esc(p.nombre)}`;
                    
                    // Mostrar descripción original del documento debajo
                    const divCont = spanNombre.closest('.fw-medium.small');
                    const descOriginal = tr.dataset.descripcionOriginal || '';
                    if (divCont && descOriginal && descOriginal !== p.nombre) {
                        let smallDesc = divCont.querySelector('.small-original');
                        if (!smallDesc) {
                            smallDesc = document.createElement('small');
                            smallDesc.className = 'text-muted d-block small-original';
                            smallDesc.style.fontSize = '0.65rem';
                            smallDesc.style.fontStyle = 'italic';
                            divCont.appendChild(smallDesc);
                        }
                        smallDesc.textContent = `Documento: ${descOriginal}`;
                    }
                }

                const selMedida = trInv.querySelector('.input-inv-medida');
                if (selMedida) {
                    const idTipoNuevo = p.id_tipo_medida || '0';
                    const opc = (window.CMG_unidadesMedida || [])
                        .filter(u => u.id_tipo == idTipoNuevo || idTipoNuevo == '0')
                        .map(u => `<option value="${u.id}">${u.nombre} (${u.abreviatura})</option>`)
                        .join('');
                    selMedida.innerHTML = opc;
                    if (p.id_medida) selMedida.value = p.id_medida;
                }

                const selBodega = trInv.querySelector('.input-inv-bodega');
                if (selBodega && !selBodega.value) {
                    const defBod = (window.CMG_bodegas || []).find(b => b.es_default);
                    if (defBod) selBodega.value = defBod.id;
                }

                const vincCont = trInv.querySelector('.vinculacion-inline-container');
                if (vincCont) vincCont.classList.add('d-none');
            }

            const idProv = document.getElementById('mcIdProveedor').value;
            const codProv = tr.querySelector('.input-codigo')?.value || tr.dataset.descripcionOriginal;
            
            if (idProv && codProv) {
                const fdV = new FormData();
                fdV.append('id_proveedor', idProv);
                fdV.append('codigo_proveedor', codProv);
                fdV.append('id_producto', p.id);

                fetch(`${window.CMG_urlBase}/guardarVinculacionAjax`, {
                    method: 'POST',
                    body: fdV,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
            }

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Producto vinculado y guardado en memoria',
                showConfirmButton: false,
                timer: 2000
            });


        }

        _idxAVincularInv = null;
        document.getElementById('inputBuscarProductoCompra').placeholder = 'Buscar producto en catálogo...';
        document.getElementById('inputBuscarProductoCompra').classList.remove('bg-warning', 'bg-opacity-10');
        mcActualizarContadorInventario();
        return;
    }

    // --- SELECCIÓN NORMAL (Desde el buscador principal de productos) ---
    const iva = parseFloat(p.porcentaje_iva || p.iva || _ivaDefault);
    CMG_agregarFilaDetalle({
        id_producto: p.id,
        producto_nombre: p.nombre,
        codigo: p.codigo_principal || p.codigo,
        descripcion: p.nombre,
        cantidad: 1,
        precio_unitario: parseFloat(p.costo_producto || p.costo || 0),
        descuento: 0,
        id_medida: p.id_medida,
        id_tipo_medida: p.id_tipo_medida,
        impuestos: [{
            codigo_impuesto: '2',
            codigo_porcentaje: p.codigo_porcentaje_iva || '2',
            tarifa: iva,
            base_imponible: 0,
            valor: 0
        }]
    });
    mcSincronizarInventario();
};



window.CMG_iniciarVinculacionInv = function(idx, descripcion) {
    _idxAVincularInv = idx;
    
    // Feedback UI
    const searchInput = document.getElementById('inputBuscarProductoCompra');
    searchInput.placeholder = 'VINCULANDO: ' + descripcion;
    searchInput.classList.add('bg-warning', 'bg-opacity-10');
    
    // Ir a la pestaña de detalle para que el usuario vea el buscador si es necesario, 
    // o simplemente avisar que use el buscador de abajo.
    const tabDetalle = document.getElementById('tab-detalle-tab') || document.getElementById('tab_compra');
    if (tabDetalle) bootstrap.Tab.getOrCreateInstance(tabDetalle).show();
    
    searchInput.focus();
};

window.CMG_mostrarBuscadorInline = function(idx) {
    const cont = document.getElementById(`vinc-cont-${idx}`);
    if (!cont) return;
    
    cont.querySelector('.info-vinculacion').classList.add('d-none');
    const divBusq = cont.querySelector('.buscador-inline-div');
    divBusq.classList.remove('d-none');
    const input = divBusq.querySelector('.input-buscar-inline');
    input.focus();
    
    // Timer local para el debounce
    let timerInline;
    input.oninput = function(e) {
        clearTimeout(timerInline);
        const q = e.target.value.trim();
        const lista = divBusq.querySelector('.lista-resultados-inline');
        
        if (q.length < 2) {
            lista.classList.add('d-none');
            return;
        }

        timerInline = setTimeout(async () => {
            try {
                const res = await fetch(`${window.CMG_urlBase}/getProductosAjax?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                if (data.ok) {
                    lista.innerHTML = '';
                    if (!data.data.length) {
                        lista.innerHTML = '<div class="list-group-item small text-muted p-1">No hay resultados</div>';
                    } else {
                        data.data.forEach(p => {
                            const btn = document.createElement('button');
                            btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
                            btn.style.fontSize = '0.65rem';
                            btn.innerHTML = `<div class="d-flex justify-content-between">
                                <span>${_esc(p.nombre)}</span>
                                <small class="text-muted">(${_esc(p.codigo_principal || '')})</small>
                            </div>`;
                            btn.onclick = () => {
                                _idxAVincularInv = idx;
                                CMG_seleccionarProducto(p);
                            };
                            lista.appendChild(btn);
                        });
                    }
                    lista.classList.remove('d-none');
                }
            } catch (err) { console.error(err); }
        }, 300);
    };

    // Cerrar al perder foco? O mejor con el botón X.
};

window.CMG_cancelarVinculacionInline = function(idx) {
    const cont = document.getElementById(`vinc-cont-${idx}`);
    if (!cont) return;
    cont.querySelector('.info-vinculacion').classList.remove('d-none');
    cont.querySelector('.buscador-inline-div').classList.add('d-none');
    cont.querySelector('.lista-resultados-inline').classList.add('d-none');
};


window.CMG_agregarItemLibre = function() {
    CMG_agregarFilaDetalle({ descripcion:'', cantidad:1, precio_unitario:0, descuento:0, impuestos:[{codigo_impuesto:'2',codigo_porcentaje:'4',tarifa:_ivaDefault,base_imponible:0,valor:0}] });
};

function CMG_agregarFilaDetalle(det) {
    const tbody = document.getElementById('tbodyDetalle');
    const idx   = tbody.rows.length;
    

    const ivaPct = det.impuestos && det.impuestos.length ? parseFloat(det.impuestos[0].tarifa||0) : _ivaDefault;
    const opcIva = (window.CMG_tarifasIva || []).map(t =>
        `<option value="${t.codigo_porcentaje||t.id}" data-tarifa="${t.porcentaje_iva}" ${parseFloat(t.porcentaje_iva)===ivaPct?'selected':''}>${t.tarifa || (t.porcentaje_iva + '%')}</option>`
    ).join('');

    const tr = document.createElement('tr');
    tr.className = 'row-detalle';
    tr.dataset.idx = idx;
    tr.dataset.productoNombre = det.producto_nombre || '';
    tr.dataset.descripcionOriginal = det.descripcion || '';
    tr.innerHTML = `
        <td class="ps-3">
            <input type="text" class="form-control form-control-sm input-detalle input-descripcion" value="${_esc(det.descripcion||'')}" placeholder="Descripción del producto..." oninput="CMG_recalcularTotales()">
            <input type="hidden" class="input-id-detalle" value="${det.id || ''}">
            <input type="hidden" class="input-id-producto" value="${det.id_producto || det.id_producto_vinculado || ''}">
            <input type="hidden" class="input-codigo" value="${det.codigo_principal || ''}">
            <input type="hidden" class="input-id-medida" value="${det.product_id_medida || det.id_medida || ''}">
            <input type="hidden" class="input-id-tipo-medida" value="${det.product_id_tipo_medida || det.id_tipo_medida || ''}">
        </td>
        <td><input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad" value="${parseFloat(det.cantidad||1)}" min="0.0001" step="any" oninput="CMG_recalcularFila(this)"></td>
        <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio" value="${parseFloat(det.precio_unitario||0).toFixed(4)}" min="0" step="any" oninput="CMG_recalcularFila(this)"></td>
        <td><input type="number" class="form-control form-control-sm input-detalle text-end text-danger input-desc" value="${parseFloat(det.descuento||0).toFixed(2)}" min="0" step="any" oninput="CMG_recalcularFila(this)"></td>
        <td class="text-center"><select class="form-select form-select-sm input-detalle input-iva" onchange="CMG_recalcularFila(this)">${opcIva}</select></td>
        <td class="text-end pe-4 align-middle fw-semibold"><span class="subtotal-line">0.00</span></td>
        <td class="text-center p-0 align-middle">
            <button type="button" class="btn btn-sm btn-link text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove();CMG_recalcularTotales()">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);

    // Si viene código de proveedor pero no id_producto, intentamos buscar homologación
    if ((!det.id_producto || det.id_producto == '0') && det.codigo_principal) {
        const idProv = document.getElementById('mcIdProveedor').value;
        if (idProv) {
            mcConsultarHomologacion(idProv, det.codigo_principal, tr);
        }
    }
    CMG_recalcularFila(tr.querySelector('.input-cantidad'));
}

function CMG_recalcularFila(input) {
    const tr    = input.closest('tr');
    const cant  = parseFloat(tr.querySelector('.input-cantidad').value || 0);
    const prec  = parseFloat(tr.querySelector('.input-precio').value || 0);
    const desc  = parseFloat(tr.querySelector('.input-desc').value || 0);
    const neto  = Math.max(0, (cant * prec) - desc);
    tr.querySelector('.subtotal-line').textContent = neto.toFixed(2);
    CMG_recalcularTotales();
}

function CMG_recalcularTotales() {
    let totalDesc = 0, subTotalBruto = 0;
    const grupos = {}; // Para agrupar por tarifa IVA
    const rows = document.querySelectorAll('#tbodyDetalle tr');
    
    rows.forEach(tr => {
        const cant  = parseFloat(tr.querySelector('.input-cantidad')?.value || 0);
        const prec  = parseFloat(tr.querySelector('.input-precio')?.value || 0);
        const desc  = parseFloat(tr.querySelector('.input-desc')?.value || 0);
        const sel   = tr.querySelector('.input-iva');
        const tarifa = sel ? parseFloat(sel.selectedOptions[0]?.dataset.tarifa || 0) : 0;
        const codPct = sel ? sel.value : '0';
        
        const brutoFila = cant * prec;
        const netoFila  = Math.max(0, brutoFila - desc);
        
        subTotalBruto += brutoFila;
        totalDesc += desc;

        if (!grupos[codPct]) {
            grupos[codPct] = { tarifa: tarifa, base: 0, iva: 0 };
        }
        grupos[codPct].base += netoFila;
        grupos[codPct].iva += netoFila * (tarifa / 100);
    });

    // Renderizar Subtotales por IVA
    let htmlSubtotales = '';
    let totalIva = 0;
    let sumaBases = 0;

    Object.values(grupos).forEach(g => {
        sumaBases += g.base;
        totalIva += g.iva;
        htmlSubtotales += `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-muted">Subtotal ${g.tarifa}%</span>
                <span>${g.base.toFixed(2)}</span>
            </div>`;
    });

    // Renderizar IVAs por Tarifa (> 0)
    let htmlIvas = '';
    Object.values(grupos).forEach(g => {
        if (g.tarifa > 0) {
            htmlIvas += `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">(+) IVA ${g.tarifa}%</span>
                    <span class="fw-bold text-dark">${g.iva.toFixed(2)}</span>
                </div>`;
        }
    });

    if (htmlIvas === '') {
        htmlIvas = `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-muted">(+) IVA</span>
                <span class="fw-bold text-dark">0.00</span>
            </div>`;
    }

    const inputPropina = document.getElementById('mcInputPropina');
    const propina      = inputPropina ? parseFloat(inputPropina.value || 0) : 0;
    const totalFinal   = sumaBases + totalIva + propina;

    const modalEl = document.getElementById('modalCompra');
    if (modalEl) {
        modalEl.dataset.subtotalNeto = sumaBases.toFixed(2);
        modalEl.dataset.totalIva     = totalIva.toFixed(2);
    }

    document.getElementById('mcLabelSubtotal').textContent = subTotalBruto.toFixed(2);
    document.getElementById('mcContenedorSubtotalesIva').innerHTML = htmlSubtotales;
    document.getElementById('mcLabelDescuento').textContent = totalDesc.toFixed(2);
    document.getElementById('mcContenedorIvasIva').innerHTML = htmlIvas;
    document.getElementById('mcLabelTotal').textContent = totalFinal.toFixed(2);
    
    // Sincronizar con el total de la pestaña de pagos si existe
    if (document.getElementById('totalComprobanteRef')) {
        document.getElementById('totalComprobanteRef').textContent = '$' + totalFinal.toFixed(2);
    }

    // Contador de ítems
    const countEl = document.getElementById('mcCountItems');
    if (countEl) countEl.textContent = rows.length;

    // Auto-completar Formas de Pago SRI si solo hay una fila
    const pagosSRI = document.querySelectorAll('.input-pago-sri-valor');
    if (pagosSRI.length === 1) {
        pagosSRI[0].value = totalFinal.toFixed(2);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PAGOS (Próximamente)
// ─────────────────────────────────────────────────────────────────────────────
// La gestión de pagos internos se implementará en una fase posterior.

// ─────────────────────────────────────────────────────────────────────────────
// GUARDAR
// ─────────────────────────────────────────────────────────────────────────────
window.CMG_guardar = async function() {
    const modal = document.getElementById('modalCompra');
    const id    = document.getElementById('mcId').value || modal.dataset.id || '';
    const tipo  = document.getElementById('mcTipoComprobante').value;
    const usaMascara = CMG_TIPOS_MASCARA.includes(tipo);

    const detalles = [];
    document.querySelectorAll('#tbodyDetalle tr').forEach(tr => {
        const descInput = tr.querySelector('.input-descripcion');
        if (!descInput) return; // Fila inválida
        
        const tarifa = parseFloat(tr.querySelector('select')?.selectedOptions[0]?.dataset.tarifa || 0);
        const cant = parseFloat(tr.querySelector('.input-cantidad')?.value || 1);
        const precio = parseFloat(tr.querySelector('.input-precio')?.value || 0);
        const descVal = parseFloat(tr.querySelector('.input-desc')?.value || 0);
        
        const neto = Math.max(0, cant * precio - descVal);
        const ivaVal = neto * tarifa / 100;
        
        detalles.push({
            id_producto: tr.querySelector('.input-id-producto')?.value || null,
            codigo_principal: tr.querySelector('.input-codigo')?.value || '',
            descripcion: descInput.value.trim(),
            cantidad: cant,
            precio_unitario: precio,
            descuento: descVal,
            precio_total_sin_impuesto: neto,
            impuestos: [{ codigo_impuesto:'2', codigo_porcentaje: tarifa>0?'4':'0', tarifa, base_imponible: neto, valor: ivaVal }]
        });
    });



    const numCompleto   = document.getElementById('mcNumeroComprobante').value;
    const partesNum     = numCompleto.split('-');
    const secuencialRaw = partesNum[2] || '';
    const secuencial    = secuencialRaw ? parseInt(secuencialRaw) : 0;
    const fechaEmision  = document.getElementById('mcFechaEmision').value;
    const idProveedor   = document.getElementById('mcIdProveedor').value;
    let sustentoId      = document.getElementById('mcSustento').value;
    let numAuth         = document.getElementById('mcAutorizacion').value;
    let desdeVal        = document.getElementById('mcAutorizacionDesde').value;
    let hastaVal        = document.getElementById('mcAutorizacionHasta').value;
    let caducidadVal    = document.getElementById('mcFechaCaducidad').value;
    const tipoRegistro  = document.getElementById('mcTipoRegistro').value;

    // 1. Validar Fecha de Emisión
    if (!fechaEmision) { 
        Swal.fire('Atención', 'La fecha de emisión es obligatoria.', 'warning'); 
        document.getElementById('mcFechaEmision').focus();
        return; 
    }

    // 2. Validar Proveedor
    if (!idProveedor) { 
        Swal.fire('Atención', 'Debe seleccionar un proveedor.', 'warning'); 
        document.getElementById('mcBuscarProveedor').focus();
        return; 
    }

    // 3. Validar Tipo de Comprobante
    if (!tipo) { 
        Swal.fire('Atención', 'El tipo de comprobante es obligatorio.', 'warning'); 
        document.getElementById('mcTipoComprobante').focus();
        return; 
    }

    // 4. Validar Número de Comprobante
    if (secuencial <= 0) { 
        Swal.fire('Atención', 'El número de comprobante es inválido.', 'warning'); 
        document.getElementById('mcNumeroComprobante').focus();
        return; 
    }

    // AUTO-COMPLETADO PARA PERSONA NATURAL (Se hace antes de validar sustento/auth si aplica)
    if (_esPersonaNatural) {
        if (!sustentoId && window.CMG_sustentos) {
            const s00 = window.CMG_sustentos.find(s => s.codigo === '00');
            if (s00) sustentoId = s00.id;
        }
        if (!numAuth) {
            const len = (tipoRegistro === 'fisica' ? 10 : 49);
            numAuth = ""; // Reset if null
            for (let i = 0; i < len; i++) numAuth += Math.floor(Math.random() * 10);
        }
        if (!desdeVal) desdeVal = secuencialRaw;
        if (!hastaVal) hastaVal = secuencialRaw;
        if (!caducidadVal) caducidadVal = fechaEmision;
    }

    // 5. Validar Sustento Tributario
    if (!sustentoId) { 
        Swal.fire('Atención', 'El sustento tributario es obligatorio.', 'warning'); 
        document.getElementById('mcSustento').focus();
        return; 
    }

    // 6. Validar Autorización
    if (!numAuth) { 
        Swal.fire('Atención', 'El número de autorización es obligatorio.', 'warning'); 
        document.getElementById('mcAutorizacion').focus();
        return; 
    }
    if (tipoRegistro === 'fisica' && numAuth.length !== 10) {
        Swal.fire('Atención', 'Para registros físicos, el número de autorización debe tener 10 dígitos.', 'warning'); 
        document.getElementById('mcAutorizacion').focus();
        return;
    }
    if (tipoRegistro === 'electronica' && numAuth.length !== 49) {
        Swal.fire('Atención', 'Para registros electrónicos, el número de autorización debe tener 49 dígitos.', 'warning'); 
        document.getElementById('mcAutorizacion').focus();
        return;
    }

    // 7. Validar Campos Desde / Hasta Obligatorios
    if (!desdeVal) {
        Swal.fire('Atención', 'El rango "Desde" es obligatorio. Es el rango inicial de la autorización.', 'warning');
        document.getElementById('mcAutorizacionDesde').focus();
        return;
    }
    if (!hastaVal) {
        Swal.fire('Atención', 'El campo "Hasta" es obligatorio. Es el rango final de la autorización.', 'warning');
        document.getElementById('mcAutorizacionHasta').focus();
        return;
    }

    // 8. Validar Desde/Hasta (Rango Numérico)
    const nDesde = desdeVal ? Number(desdeVal) : NaN;
    const nHasta = hastaVal ? Number(hastaVal) : NaN;

    if (!isNaN(nDesde) && secuencial < nDesde) {
        Swal.fire('Atención', `El número secuencial (${secuencial}) no esta dentro del rango permitido (${nDesde}).`, 'warning'); 
        document.getElementById('mcNumeroComprobante').focus();
        return;
    }
    if (!isNaN(nHasta) && secuencial > nHasta) {
        Swal.fire('Atención', `El número secuencial (${secuencial}) no esta dentro del rango permitido (${nHasta}).`, 'warning'); 
        document.getElementById('mcNumeroComprobante').focus();
        return;
    }

    // 9. Validar Fecha de Caducidad
    if (!caducidadVal) { 
        Swal.fire('Atención', 'La fecha de caducidad es obligatoria.', 'warning'); 
        document.getElementById('mcFechaCaducidad').focus();
        return; 
    }
    if (fechaEmision > caducidadVal) {
        Swal.fire('Atención', 'El documento no es valido de acuerdo a la fecha de caducidad y emisión.', 'warning'); 
        document.getElementById('mcFechaEmision').focus();
        return;
    }

    // 10. Validar Ítems (al menos uno)
    if (detalles.length === 0) { 
        Swal.fire('Atención', 'Debe agregar al menos un ítem a la compra.', 'warning'); 
        const searchInput = document.querySelector('.input-producto-search');
        if (searchInput) searchInput.focus();
        return; 
    }

    // 11. Validar Formas de Pago SRI (debe cuadrar con el total)
    const totalFactura = parseFloat(document.getElementById('mcLabelTotal').textContent || 0);
    let totalPagosSRI = 0;
    const pagos = [];
    const plazo = parseInt(document.getElementById('mcDiasCredito').value || 0);
    const unidad = document.getElementById('mcPlazoSRI').value || 'dias';

    let pagoSinFormaSRI = false;
    document.querySelectorAll('.row-pago-sri').forEach(div => {
        const cod = div.querySelector('.input-pago-sri-id').value;
        const val = parseFloat(div.querySelector('.input-pago-sri-valor').value || 0);
        // Con placeholder, un monto sin forma de pago seleccionada no es válido.
        if (val > 0 && !cod) pagoSinFormaSRI = true;
        totalPagosSRI += val;
        pagos.push({
            forma_pago: cod,
            total: val,
            plazo: plazo,
            unidad_tiempo: unidad
        });
    });

    if (pagoSinFormaSRI) {
        Swal.fire('Atención', 'Seleccione la forma de pago SRI para cada monto ingresado.', 'warning');
        return;
    }

    if (Math.abs(totalFactura - totalPagosSRI) >= 0.01) {
        Swal.fire('Atención', `Las formas de pago SRI ($${totalPagosSRI.toFixed(2)}) no coinciden con el total de la compra ($${totalFactura.toFixed(2)}).`, 'warning');
        const firstPagoInput = document.querySelector('.input-pago-sri-valor');
        if (firstPagoInput) firstPagoInput.focus();
        return;
    }

    const payload = {
        id: id || undefined,
        id_proveedor: document.getElementById('mcIdProveedor').value,
        id_establecimiento: document.getElementById('mcIdEstablecimiento').value,
        tipo_comprobante: tipo,
        id_sustento_tributario: sustentoId,
        establecimiento_prov: partesNum[0] || '',
        punto_emision_prov: partesNum[1] || '',
        secuencial_prov: secuencialRaw,
        numero_autorizacion: numAuth,
        autorizacion_desde: desdeVal,
        autorizacion_hasta: hastaVal,
        fecha_caducidad: caducidadVal,
        tipo_registro: tipoRegistro,
        deducible: document.getElementById('mcDeducible').value,
        documento_modificado: document.getElementById('mcDocumentoModificado').value,
        motivo: document.getElementById('mcMotivo').value,
        fecha_emision: fechaEmision,
        fecha_registro: document.getElementById('mcFechaRegistro').value,
        parte_relacionada: document.getElementById('mcParteRelacionada').checked,
        observaciones: document.getElementById('mcObservaciones').value,
        propina: parseFloat(document.getElementById('mcInputPropina').value || 0),
        detalles, pagos, retenciones: []
    };

    const btn = document.getElementById('btnGuardarCompra');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

    try {
        const fd = new FormData();
        fd.append('data', JSON.stringify(payload));
        const res  = await fetch(`${window.CMG_urlBase}/guardarAjax`, { 
            method:'POST', 
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
            if (typeof mcLimpiarBorrador === 'function') mcLimpiarBorrador();
            
            // En lugar de cerrar, recargamos la compra para habilitar retenciones y otros procesos
            const compRes = await fetch(`${window.CMG_urlBase}/getCompraAjax?id=${data.id}`);
            const compData = await compRes.json();
            if (compData.ok) {
                CMG_poblarModal(compData.data);
            }
            
            CMG_fetchSearch(window.CMG_currentPage);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
        }
    } catch(e) {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: e.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// ANULAR / ELIMINAR
// ─────────────────────────────────────────────────────────────────────────────


window.CMG_eliminar = async function() {
    const confirm = await Swal.fire({
        title: '¿Eliminar esta compra?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    const id = document.getElementById('modalCompra').dataset.id;
    const fd = new FormData(); fd.append('id', id);
    const res  = await fetch(`${window.CMG_urlBase}/eliminarAjax`, { 
        method:'POST', 
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (data.ok) { 
        Swal.fire('Eliminado', data.mensaje, 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalCompra')).hide(); 
        CMG_fetchSearch(1); 
    } else {
        Swal.fire('Error', data.mensaje, 'error');
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

window._esc = function(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

function CMG_formatFecha(f) {
    if (!f) return '—';
    const d = new Date(f);
    return isNaN(d) ? f : d.toLocaleDateString('es-EC') + ' ' + d.toLocaleTimeString('es-EC');
}

function CMG_actualizarEstadoBadge(estado) {
    const badge = document.getElementById('mcEstadoBadge');
    const map = { borrador:'bg-secondary', registrado:'bg-success', anulado:'bg-danger' };
    badge.className = 'badge ' + (map[estado] || 'bg-secondary');
    badge.textContent = estado.charAt(0).toUpperCase() + estado.slice(1);
}

// IVA default desde taridas
if (window.CMG_tarifasIva && window.CMG_tarifasIva.length) {
    const t15 = window.CMG_tarifasIva.find(t => parseFloat(t.porcentaje_iva) === 15);
    if (t15) _ivaDefault = 15;
}

// =====================================================================
// LOCAL STORAGE — Auto-guardado de borrador de compra
// =====================================================================
const COMPRA_STORAGE_KEY = 'compra_borrador_' + (window.CMG_empresa?.id || 0) + '_' + (window.CMG_usuario?.id || 0);

function mcCapturarEstado() {
    const estado = {};
    estado.id_proveedor = document.getElementById('mcIdProveedor')?.value || '';
    estado.buscar_proveedor = document.getElementById('mcBuscarProveedor')?.value || '';
    estado.tipo_comprobante = document.getElementById('mcTipoComprobante')?.value || '';
    estado.sustento = document.getElementById('mcSustento')?.value || '';
    estado.numero = document.getElementById('mcNumeroComprobante')?.value || '';
    estado.autorizacion = document.getElementById('mcAutorizacion')?.value || '';
    estado.aut_desde = document.getElementById('mcAutorizacionDesde')?.value || '';
    estado.aut_hasta = document.getElementById('mcAutorizacionHasta')?.value || '';
    estado.caducidad = document.getElementById('mcFechaCaducidad')?.value || '';
    estado.tipo_registro = document.getElementById('mcTipoRegistro')?.value || '';
    estado.deducible = document.getElementById('mcDeducible')?.value || '';
    estado.documento_modificado = document.getElementById('mcDocumentoModificado')?.value || '';
    estado.motivo = document.getElementById('mcMotivo')?.value || '';
    estado.fecha_emision = document.getElementById('mcFechaEmision')?.value || '';
    estado.fecha_registro = document.getElementById('mcFechaRegistro')?.value || '';
    estado.parte_relacionada = document.getElementById('mcParteRelacionada')?.checked || false;
    estado.observaciones = document.getElementById('mcObservaciones')?.value || '';
    estado.propina = document.getElementById('mcInputPropina')?.value || '0.00';

    // Detalles
    estado.detalles = [];
    document.querySelectorAll('#tbodyDetalle tr').forEach(tr => {
        const idProd = tr.querySelector('.input-id-producto')?.value || '';
        const desc = tr.querySelector('.input-descripcion')?.value || '';
        if (idProd || desc.trim()) {
            estado.detalles.push({
                id_producto: idProd,
                codigo_principal: tr.querySelector('.input-codigo')?.value || '',
                descripcion: desc,
                cantidad: tr.querySelector('.input-cantidad')?.value || '',
                precio_unitario: tr.querySelector('.input-precio')?.value || '',
                descuento: tr.querySelector('.input-desc')?.value || '',
                iva: parseFloat(tr.querySelector('.input-iva')?.selectedOptions[0]?.dataset.tarifa || _ivaDefault)
            });
        }
    });
    return estado;
}

function mcAutoGuardar() {
    try {
        const idActual = document.getElementById('modalCompra')?.dataset.id;
        if (idActual) return; // No auto-guardar si se edita
        
        const estado = mcCapturarEstado();
        if (!estado.id_proveedor && !estado.detalles.length && !estado.numero) {
            localStorage.removeItem(COMPRA_STORAGE_KEY);
            return;
        }
        localStorage.setItem(COMPRA_STORAGE_KEY, JSON.stringify(estado));
    } catch (e) {}
}

function mcLimpiarBorrador() {
    try {
        localStorage.removeItem(COMPRA_STORAGE_KEY);
    } catch (e) {}
}

function mcCheckBorrador() {
    let borrador = null;
    try {
        const raw = localStorage.getItem(COMPRA_STORAGE_KEY);
        if (raw) borrador = JSON.parse(raw);
    } catch (e) {}

    if (borrador && (borrador.id_proveedor || (borrador.detalles && borrador.detalles.length > 0))) {
        // Mostrar aviso antes de abrir el modal
        const div = document.createElement('div');
        div.id = 'mc-borrador-aviso';
        div.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
        
        const provName = borrador.buscar_proveedor || 'desconocido';
        div.innerHTML = `
            <div class="bg-white rounded-3 shadow-lg p-4" style="max-width:420px;width:90%;">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                    <h6 class="fw-bold mb-0">Compra sin guardar</h6>
                </div>
                <p class="small text-muted mb-4">Hay una compra en borrador del proveedor <strong>${_esc(provName)}</strong> que no fue guardada. ¿Qué desea hacer?</p>
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary" id="mc-aviso-nueva">
                        <i class="bi bi-file-earmark-plus me-1"></i> Nueva compra
                    </button>
                    <button class="btn btn-sm btn-primary" id="mc-aviso-restaurar">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Cargar borrador
                    </button>
                </div>
            </div>`;
        document.body.appendChild(div);

        document.getElementById('mc-aviso-restaurar').onclick = () => {
            div.remove();
            new bootstrap.Modal(document.getElementById('modalCompra')).show();
            setTimeout(() => { mcEjecutarRestauracion(borrador); }, 100);
        };

        document.getElementById('mc-aviso-nueva').onclick = () => {
            mcLimpiarBorrador();
            div.remove();
            new bootstrap.Modal(document.getElementById('modalCompra')).show();
        };
        return true;
    }
    return false;
}

async function mcEjecutarRestauracion(estado) {
    document.getElementById('mcIdProveedor').value = estado.id_proveedor || '';
    document.getElementById('mcBuscarProveedor').value = estado.buscar_proveedor || '';
    document.getElementById('mcTipoComprobante').value = estado.tipo_comprobante || '';
    
    if (estado.tipo_comprobante) {
        await CMG_cargarSustentos(estado.tipo_comprobante, estado.sustento || null);
    }
    
    document.getElementById('mcNumeroComprobante').value = estado.numero || '';
    document.getElementById('mcAutorizacion').value = estado.autorizacion || '';
    document.getElementById('mcAutorizacionDesde').value = estado.aut_desde || '';
    document.getElementById('mcAutorizacionHasta').value = estado.aut_hasta || '';
    if (estado.caducidad) document.getElementById('mcFechaCaducidad').value = estado.caducidad;
    if (estado.tipo_registro) document.getElementById('mcTipoRegistro').value = estado.tipo_registro;
    if (estado.deducible) document.getElementById('mcDeducible').value = estado.deducible;
    document.getElementById('mcDocumentoModificado').value = estado.documento_modificado || '';
    document.getElementById('mcMotivo').value = estado.motivo || '';
    if (estado.fecha_emision) document.getElementById('mcFechaEmision').value = estado.fecha_emision;
    if (estado.fecha_registro) document.getElementById('mcFechaRegistro').value = estado.fecha_registro;
    if (document.getElementById('mcParteRelacionada')) document.getElementById('mcParteRelacionada').checked = estado.parte_relacionada;
    document.getElementById('mcObservaciones').value = estado.observaciones || '';
    if (document.getElementById('mcInputPropina')) document.getElementById('mcInputPropina').value = estado.propina || '0.00';
    
    // Restaurar detalles
    document.getElementById('tbodyDetalle').innerHTML = '';
    if (estado.detalles && estado.detalles.length) {
        estado.detalles.forEach(d => {
            CMG_agregarFilaDetalle({
                id_producto: d.id_producto,
                codigo_principal: d.codigo_principal,
                descripcion: d.descripcion,
                cantidad: d.cantidad,
                precio_unitario: d.precio_unitario,
                descuento: d.descuento,
                impuestos: [{ tarifa: d.iva }]
            });
        });
    } else {
        // Al menos una fila vacía
        CMG_agregarItemLibre();
    }
    CMG_recalcularTotales();
}

function mcDebounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function mcRegistrarAutoGuardado() {
    const modal = document.getElementById('modalCompra');
    if (!modal) return;
    const debouncedGuardar = mcDebounce(mcAutoGuardar, 800);
    modal.addEventListener('input', debouncedGuardar);
    modal.addEventListener('change', debouncedGuardar);
}


// ─── FORMAS DE PAGO SRI ───
window.CMG_agregarFormaPagoSRI = function(codigo = '', valor = 0) {
    const container = document.getElementById('mc-container-pagos-sri');
    if (!container) return;

    const opcFP = '<option value="">-- Seleccione forma de pago --</option>' +
        (window.CMG_formasPago || []).map(f =>
            `<option value="${f.codigo}" ${f.codigo === codigo ? 'selected' : ''}>${f.nombre}</option>`
        ).join('');

    const uniqueId = 'sri-' + Date.now() + Math.floor(Math.random()*1000);
    const div = document.createElement('div');
    div.className = 'row g-2 align-items-center mb-1 row-pago-sri';
    div.innerHTML = `
        <div class="col-7">
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-star text-muted btn-favorito" style="cursor:pointer;" data-modulo="compras" data-campo="pago_sri_default" data-target="#${uniqueId}" title="Marcar como favorita"></i>
                <select id="${uniqueId}" class="form-select form-select-sm border-0 bg-light input-pago-sri-id">${opcFP}</select>
            </div>
        </div>
        <div class="col-4">
            <input type="number" class="form-control form-control-sm text-end border-0 bg-light fw-bold input-pago-sri-valor" step="0.01" value="${parseFloat(valor).toFixed(2)}">
        </div>
        <div class="col-1 text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0 shadow-none border-0" onclick="this.closest('.row-pago-sri').remove()"><i class="bi bi-trash"></i></button>
        </div>
    `;
    container.appendChild(div);
    
    if (typeof initFavoritosEstrellas === 'function') {
        initFavoritosEstrellas();
    }
};

/**
 * Consulta si un código de proveedor ya tiene una vinculación con un producto del catálogo.
 * Si existe, actualiza la fila automáticamente.
 */
async function mcConsultarHomologacion(idProv, codigoProv, tr) {
    if (!idProv || !codigoProv || !tr) return;
    if (tr.dataset.consultandoHomologacion === 'true') return;
    
    tr.dataset.consultandoHomologacion = 'true';
    try {
        const response = await fetch(`${window.CMG_urlBase}/getHomologacionAjax?id_proveedor=${idProv}&codigo_proveedor=${encodeURIComponent(codigoProv)}`);
        const res = await response.json();
        if (res.ok && res.data) {
            const prod = res.data;
            if (document.contains(tr)) {
                // Actualizar inputs ocultos
                tr.querySelector('.input-id-producto').value = prod.id;
                tr.querySelector('.input-id-medida').value = prod.id_medida || '';
                tr.querySelector('.input-id-tipo-medida').value = prod.id_tipo_medida || '';
                tr.dataset.productoNombre = prod.nombre;
                
                // Si la descripción está vacía o es igual al código, poner el nombre del producto
                const inputDesc = tr.querySelector('.input-descripcion');
                if (inputDesc && (!inputDesc.value || inputDesc.value.trim() === codigoProv.trim())) {
                    inputDesc.value = prod.nombre;
                }

                // Si el precio es 0, poner el costo sugerido
                const inputPrecio = tr.querySelector('.input-precio');
                if (inputPrecio && parseFloat(inputPrecio.value || 0) === 0) {
                    inputPrecio.value = parseFloat(prod.costo || 0).toFixed(4);
                }
                
                // Sincronizar con la pestaña de inventario
                mcSincronizarInventario();
                CMG_recalcularFila(tr.querySelector('.input-cantidad'));
            }
        }
    } catch (err) {
        console.error('Error al consultar homologación:', err);
    } finally {
        delete tr.dataset.consultandoHomologacion;
    }
}

// ─── INVENTARIO ───

/**
 * Sincroniza la pestaña de inventario con los ítems agregados al detalle de la compra.
 */
/**
 * Carga el estado del inventario para la compra actual.
 * Si ya existen movimientos no anulados, los muestra y bloquea el botón de procesar si el total ya fue enviado.
 */
window.mcInventarioProcesadoMap = {}; // id_detalle => total_enviado

window.mcCargarStatusInventario = async function() {
    const id = document.getElementById('mcId').value;
    const btnProcesar = document.getElementById('btnProcesarInventario');
    const container = document.getElementById('mc-inventario-procesado');
    const tbodyStatus = document.getElementById('mc-tbody-inventario-procesado');
    
    window.mcInventarioProcesadoMap = {};
    
    if (!id || id == "0" || id == "") {
        if (container) container.classList.add('d-none');
        if (btnProcesar) {
            btnProcesar.disabled = false;
            btnProcesar.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Procesar Entradas';
        }
        return;
    }

    try {
        const response = await fetch(`${window.CMG_urlBase}/getInventarioStatusAjax?id_compra=${id}`);
        const res = await response.json();
        
        if (tbodyStatus) tbodyStatus.innerHTML = '';
        
        if (res.ok && res.data && res.data.length > 0) {
            if (container) container.classList.remove('d-none');
            
            res.data.forEach(m => {
                // Acumular totales por detalle de compra
                const idDet = m.referencia_id;
                if (!window.mcInventarioProcesadoMap[idDet]) window.mcInventarioProcesadoMap[idDet] = 0;
                window.mcInventarioProcesadoMap[idDet] += parseFloat(m.cantidad);

                if (tbodyStatus) {
                    const tr = document.createElement('tr');
                    tr.classList.add('border-bottom');
                    tr.innerHTML = `
                        <td class="ps-3 py-1 text-dark">
                            <span class="fw-medium">${_esc(m.producto_nombre)}</span>
                            <small class="text-muted d-block" style="font-size:0.65rem;">${_esc(m.producto_codigo)}</small>
                        </td>
                        <td class="py-1">${_esc(m.bodega_nombre)}</td>
                        <td class="py-1 text-center fw-bold text-primary">
                            ${m.cantidad} 
                            <small class="text-muted fw-normal">${_esc(m.medida_abreviatura || '')}</small>
                        </td>
                        <td class="py-1 text-center small text-muted">${(() => {
                            if(!m.fecha_movimiento) return '-';
                            const [d, t] = m.fecha_movimiento.split(' ');
                            const [y, mon, day] = d.split('-');
                            const time = t ? t.split('.')[0] : '00:00:00';
                            return `${day}-${mon}-${y} ${time}`;
                        })()}</td>
                        <td class="py-1 small text-truncate" style="max-width:200px;" title="${_esc(m.observaciones||'')}">${_esc(m.observaciones || '-')}</td>
                        <td class="py-1 text-center pe-3">
                            <button type="button" class="btn btn-link text-danger p-0" onclick="mcEliminarMovimientoInventario(${m.id})" title="Eliminar este ingreso de inventario">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    `;
                    tbodyStatus.appendChild(tr);
                }
            });

            // Refrescar la tabla de inventario para aplicar los bloqueos y actualizar pendientes (forzando reset de cantidades)
            if (typeof mcSincronizarInventario === 'function') {
                mcSincronizarInventario(true);
            }

        } else {
            if (container) container.classList.add('d-none');
            mcActualizarUIInventario();
        }
    } catch (error) {
        console.error('Error al cargar status de inventario:', error);
    }
};

window.mcEliminarMovimientoInventario = async function(idMov) {
    const result = await Swal.fire({
        title: '¿Eliminar este ingreso?',
        text: "El stock regresará a su estado anterior. Esta acción quedará registrada en auditoría.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('id', idMov);

            const response = await fetch(`${window.CMG_urlBase}/eliminarMovimientoInventarioAjax`, {
                method: 'POST',
                body: formData
            });
            const res = await response.json();

            if (res.ok) {
                Swal.fire('Eliminado', res.mensaje, 'success');
                // Recargar todo el estado y la tabla forzando reset
                await mcCargarStatusInventario();
                mcSincronizarInventario(true);
            } else {
                Swal.fire('Error', res.mensaje, 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'No se pudo procesar la solicitud.', 'error');
        }
    }
};

/**
 * Actualiza visualmente la tabla de envío a inventario basándose en lo ya procesado.
 */
window.mcActualizarUIInventario = function() {
    const btnProcesar = document.getElementById('btnProcesarInventario');
    let todosProcesados = true;
    let hayParaProcesar = false;

    // Copia profunda del mapa de procesados para ir descontando
    const procesadosRestantes = JSON.parse(JSON.stringify(window.mcInventarioProcesadoMap || {}));

    document.querySelectorAll('#mc-tbody-inventario .row-inv').forEach(tr => {
        const idDet = tr.dataset.idDetalle; // Usar ID del detalle, no del producto
        const cantCompra = parseFloat(tr.querySelector('.input-inv-cantidad').dataset.cantOriginal || tr.querySelector('.input-inv-cantidad').value || 0);
        
        let procesadoEnFila = 0;
        if (idDet && procesadosRestantes[idDet] > 0) {
            procesadoEnFila = Math.min(cantCompra, procesadosRestantes[idDet]);
            procesadosRestantes[idDet] -= procesadoEnFila;
        }

        const check = tr.querySelector('.input-inv-check');

        if (procesadoEnFila >= cantCompra) {
            tr.classList.add('table-success', 'bg-opacity-10');
            if (check) {
                check.disabled = true;
            }
        } else {
            tr.classList.remove('table-success', 'bg-opacity-10');
            if (check) {
                check.disabled = false;
                if (check.checked) hayParaProcesar = true;
            }
            todosProcesados = false;
        }
    });

    if (btnProcesar) {
        const rows = document.querySelectorAll('#mc-tbody-inventario .row-inv').length;
        if (todosProcesados && rows > 0) {
            btnProcesar.disabled = true;
            btnProcesar.innerHTML = '<i class="bi bi-check2-all me-1"></i> Inventario Completo';
            btnProcesar.classList.replace('btn-primary', 'btn-outline-success');
        } else {
            btnProcesar.disabled = !hayParaProcesar;
            btnProcesar.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Procesar Entradas';
            btnProcesar.classList.replace('btn-outline-success', 'btn-primary');
        }
    }

    // Actualizar badges en la pestaña principal (Detalle de Compra)
    document.querySelectorAll('#tbodyDetalle .row-detalle').forEach(tr => {
        const idDet = tr.querySelector('.input-id-detalle')?.value;
        const cantCompra = parseFloat(tr.querySelector('.input-cantidad')?.value || 0);
        const procesado = idDet ? (window.mcInventarioProcesadoMap[idDet] || 0) : 0;
        
    });

    // No llamamos a mcSincronizarInventario aquí para evitar bucles infinitos y pérdida de datos manuales.
    // La sincronización debe ocurrir solo al abrir la pestaña o cambiar datos en la compra.
};

window.mcSincronizarInventario = function(forceReset = false) {
    if (window._mcSincronizando) return;
    window._mcSincronizando = true;

    const tbody = document.getElementById('mc-tbody-inventario');
    if (!tbody) return;

    const itemsCompra = [];
    document.querySelectorAll('#tbodyDetalle tr').forEach((tr, i) => {
        const idProdRaw = tr.querySelector('.input-id-producto')?.value;
        const idProd = (idProdRaw && idProdRaw != '0') ? idProdRaw : '';
        const idDet  = tr.querySelector('.input-id-detalle')?.value || '';
        const desc   = tr.querySelector('.input-descripcion')?.value || '';
        const cant   = parseFloat(tr.querySelector('.input-cantidad')?.value || 0);
        const precio = parseFloat(tr.querySelector('.input-precio')?.value || 0);
        const idMed  = tr.querySelector('.input-id-medida')?.value || '';
        const idTipo = tr.querySelector('.input-id-tipo-medida')?.value || '';
        const cod    = tr.querySelector('.input-codigo')?.value || '';
        
        if (idProd || desc.trim()) {
            const trRef = tr; // Mantener referencia
            if (cod && !idProd) {
                const idProv = document.getElementById('mcIdProveedor').value;
                if (idProv) mcConsultarHomologacion(idProv, cod, trRef);
            }

            const prodName = tr.dataset.productoNombre || '';
            itemsCompra.push({ 
                id_detalle: idDet,
                id_producto: idProd, 
                codigo: cod,
                descripcion: desc, 
                descripcion_original: tr.dataset.descripcionOriginal || desc,
                producto_nombre: idProd ? (prodName || desc) : desc,
                cantidad: cant, 
                costo: precio, 
                id_medida: idMed,
                id_tipo_medida: idTipo,
                index: tr.dataset.idx || i 
            });
        }
    });

    if (itemsCompra.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-box-seam d-block fs-3 mb-2"></i>Agregue productos a la compra para verlos aquí</td></tr>`;
        document.getElementById('mc-inventario-count').textContent = '0';
        return;
    }

    // Mantener valores ingresados si ya existen filas
    const valoresPrevios = {};
    tbody.querySelectorAll('.row-inv').forEach(tr => {
        const key = tr.dataset.index;
        valoresPrevios[key] = {
            id_producto: tr.dataset.idProducto || '',
            id_medida: tr.querySelector('.input-inv-medida')?.value,
            id_bodega: tr.querySelector('.input-inv-bodega')?.value,
            lote: tr.querySelector('.input-inv-lote')?.value,
            nup: tr.querySelector('.input-inv-nup')?.value,
            caducidad: tr.querySelector('.input-inv-caducidad')?.value,
            cantidad: tr.querySelector('.input-inv-cantidad')?.value,
            procesar: tr.querySelector('.input-inv-check')?.checked
        };
    });

    const opcMedida = (window.CMG_unidadesMedida || []).map(m => `<option value="${m.id}">${m.abreviatura} - ${m.nombre}</option>`).join('');
    const opcBodega = (window.CMG_bodegas || []).map(b => `<option value="${b.id}">${b.nombre}</option>`).join('');

    // Clonar el mapa de procesados para ir "consumiendo" las cantidades en las filas
    const procesadosRestantes = JSON.parse(JSON.stringify(window.mcInventarioProcesadoMap || {}));

    tbody.innerHTML = itemsCompra.map(item => {
        const prev = valoresPrevios[item.index] || {};
        
        // Calcular cuánto de este item ya fue procesado "consumiendo" del mapa por ID de detalle
        const idDet = item.id_detalle;
        let procesadoEnFila = 0;
        if (idDet && procesadosRestantes[idDet] > 0) {
            procesadoEnFila = Math.min(item.cantidad, procesadosRestantes[idDet]);
            procesadosRestantes[idDet] -= procesadoEnFila;
        }
        
        const pendiente = Math.max(0, item.cantidad - procesadoEnFila);
        
        // Si ya está procesado en esta fila, por defecto no marcar check
        const isChecked = prev.procesar !== undefined ? prev.procesar : false;
        const isDisabled = pendiente <= 0;

        let opcMedidaLocal = '<option value="">—</option>';
        if (item.id_producto) {
            // Filtrar medidas por el tipo del producto
            let filteredMedidas = window.CMG_unidadesMedida || [];
            if (item.id_tipo_medida && item.id_tipo_medida != '0') {
                filteredMedidas = filteredMedidas.filter(m => m.id_tipo == item.id_tipo_medida);
            }
            
            // Lógica de selección de medida:
            // Si el producto en esta fila cambió o es nuevo, usamos la medida que viene del catálogo (item.id_medida)
            let targetId = item.id_medida || '0';
            
            // Solo respetamos la selección previa si el producto es el mismo
            if (prev.id_producto && String(prev.id_producto) === String(item.id_producto) && prev.id_medida && prev.id_medida != '0') {
                targetId = prev.id_medida;
            }

            opcMedidaLocal = filteredMedidas.map(m => {
                const selected = (m.id == targetId) ? 'selected' : '';
                return `<option value="${m.id}" ${selected}>${m.abreviatura} - ${m.nombre}</option>`;
            }).join('');
        }

        const targetBodegaId = prev.id_bodega || (window.CMG_bodegas || []).find(b => b.es_default)?.id || '';
        const opcBodegaLocal = (window.CMG_bodegas || []).map(b => `<option value="${b.id}" ${b.id == targetBodegaId ? 'selected' : ''}>${b.nombre}</option>`).join('');
        
        return `
            <tr class="row-inv ${isDisabled ? 'table-success bg-opacity-10' : ''}" data-index="${item.index}" data-id-producto="${item.id_producto || ''}" data-id-detalle="${item.id_detalle || ''}">
                <input type="hidden" class="input-inv-id-producto" value="${item.id_producto || ''}">
                <td class="ps-3 py-2">
                    <div class="fw-medium small">
                        <div class="d-flex align-items-center">
                            <span class="text-truncate ${(item.id_producto && item.id_producto != '0') ? 'text-primary fw-bold' : ''}" style="max-width: 250px;">
                                ${(item.id_producto && item.id_producto != '0') ? 
                                    `<i class="bi bi-tag-fill me-1"></i>${_esc(item.producto_nombre || item.descripcion)}` : 
                                    _esc(item.descripcion)}
                            </span>
                            ${procesadoEnFila >= item.cantidad ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2"><i class="bi bi-check-all me-1"></i>Enviado</span>' : ''}
                        </div>
                        ${(procesadoEnFila > 0 && procesadoEnFila < item.cantidad) ? `<div class="text-warning x-small mt-1 fw-bold" style="font-size:0.7rem;"><i class="bi bi-info-circle me-1"></i>Saldo por enviar a inventario: ${(item.cantidad - procesadoEnFila).toFixed(2)}</div>` : ''}
                        ${(item.id_producto && item.id_producto != '0') && item.producto_nombre && item.producto_nombre !== item.descripcion_original ? 
                            `<small class="text-muted d-block" style="font-size: 0.65rem; font-style: italic;">Documento: ${_esc(item.descripcion_original)}</small>` : ''}
                    </div>
                    ${(!item.id_producto || item.id_producto == '0') ? `
                        <div class="vinculacion-inline-container position-relative" id="vinc-cont-${item.index}">
                            <div class="d-flex align-items-center mt-1 info-vinculacion">
                                <small class="text-danger me-2" style="font-size: 0.7rem;">Sin producto vinculado</small>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 0.65rem;" onclick="CMG_mostrarBuscadorInline(${item.index})">
                                    <i class="bi bi-search me-1"></i> Vincular
                                </button>
                            </div>
                            <div class="mt-1 d-none buscador-inline-div" style="max-width: 220px;">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control form-control-xs input-buscar-inline py-0 px-1" placeholder="Buscar código o nombre..." style="font-size: 0.7rem;">
                                    <button class="btn btn-outline-secondary py-0 px-1" type="button" onclick="CMG_cancelarVinculacionInline(${item.index})"><i class="bi bi-x"></i></button>
                                </div>
                                <div class="list-group shadow dropdown-predictivo position-absolute d-none lista-resultados-inline w-100" style="z-index: 1060; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                        </div>` : ''}
                </td>
                <td><select class="form-select form-select-sm border-0 bg-light input-inv-medida" style="font-size: 0.75rem;">${opcMedidaLocal}</select></td>
                <td><select class="form-select form-select-sm border-0 bg-light input-inv-bodega" style="font-size: 0.75rem;">${opcBodegaLocal}</select></td>
                <td><input type="number" class="form-control form-control-sm border-0 bg-light text-center fw-bold input-inv-cantidad" value="${(forceReset || prev.cantidad === undefined) ? pendiente : prev.cantidad}" data-cant-original="${item.cantidad}" min="0.0001" max="${pendiente}" step="any" ${isDisabled ? 'readonly' : ''} style="font-size: 0.75rem;"></td>
                <td><input type="number" class="form-control form-control-sm border-0 bg-light text-end input-inv-costo" value="${item.costo.toFixed(4)}" readonly style="font-size: 0.75rem;"></td>
                <td><input type="text" class="form-control form-control-sm border-0 bg-light text-center input-inv-lote" value="${_esc(prev.lote||'')}" placeholder="Lote..." style="font-size: 0.75rem;"></td>
                <td><input type="text" class="form-control form-control-sm border-0 bg-light text-center input-inv-nup" value="${_esc(prev.nup||'')}" placeholder="NUP/Serial..." style="font-size: 0.75rem;"></td>
                <td><input type="date" class="form-control form-control-sm border-0 bg-light input-inv-caducidad" value="${prev.caducidad||''}" style="font-size: 0.75rem;"></td>
                <td class="text-center align-middle">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input input-inv-check" type="checkbox" ${isChecked && !isDisabled ? 'checked' : ''} ${isDisabled ? 'disabled' : ''} onchange="mcActualizarContadorInventario()">
                    </div>
                </td>
            </tr>`;
    }).join('');

    // Restaurar valores seleccionados
    tbody.querySelectorAll('.row-inv').forEach(tr => {
        const key = tr.dataset.index;
        const prev = valoresPrevios[key];
        if (prev) {
            if (prev.id_medida) tr.querySelector('.input-inv-medida').value = prev.id_medida;
            if (prev.id_bodega) tr.querySelector('.input-inv-bodega').value = prev.id_bodega;
        }
    });

    mcActualizarContadorInventario();
    window._mcSincronizando = false;
}

window.mcActualizarContadorInventario = function() {
    const count = document.querySelectorAll('#mc-tbody-inventario .input-inv-check:checked').length;
    document.getElementById('mc-inventario-count').textContent = count;
    mcActualizarUIInventario();
};

window.mcProcesarInventario = async function() {
    const idCompra = document.getElementById('modalCompra').dataset.id;
    if (!idCompra) {
        Swal.fire('Atención', 'Primero debe guardar la compra para poder procesar el inventario.', 'warning');
        return;
    }

    const items = [];
    const errors = [];
    document.querySelectorAll('#mc-tbody-inventario .row-inv').forEach(tr => {
        const check = tr.querySelector('.input-inv-check');
        if (check && check.checked && !check.disabled) {
            // Obtener IDs de forma explícita
            const idDet  = tr.getAttribute('data-id-detalle') || '';
            const idProd = tr.getAttribute('data-id-producto') || '';
            const idMed  = tr.querySelector('.input-inv-medida')?.value || '';
            const idBod  = tr.querySelector('.input-inv-bodega')?.value || '';
            const cant   = parseFloat(tr.querySelector('.input-inv-cantidad')?.value || 0);
            const costo  = parseFloat(tr.querySelector('.input-inv-costo')?.value || 0);
            
            const descSpan = tr.querySelector('.text-truncate');
            const desc = descSpan ? descSpan.textContent.trim() : 'Producto';

            // Validaciones locales
            if (!idProd) {
                errors.push(`El ítem "${desc}" no tiene un producto vinculado.`);
            }
            if (!idMed || idMed === '0') {
                errors.push(`Seleccione una medida para "${desc}".`);
            }
            if (!idBod || idBod === '0') {
                errors.push(`Seleccione una bodega para "${desc}".`);
            }
            if (cant <= 0) {
                errors.push(`La cantidad para "${desc}" debe ser mayor a 0.`);
            }
            if (costo <= 0) {
                // El costo unitario debe ser mayor a cero, pero es opcional en el sentido que el sistema ya debería tenerlo
                // El usuario dijo "el costo unitario debe ser mayor a cero y es opcional ponerlo"
                // Entiendo que si no lo pone el usuario, se usa el de la compra. Pero si la compra tiene costo 0, es error.
                errors.push(`El costo unitario para "${desc}" debe ser mayor a 0.`);
            }

            items.push({
                id_detalle: idDet,
                id_producto: idProd,
                descripcion: desc,
                cantidad: cant,
                costo: costo,
                id_medida: idMed,
                id_bodega: idBod,
                lote: tr.querySelector('.input-inv-lote').value,
                nup: tr.querySelector('.input-inv-nup').value,
                caducidad: tr.querySelector('.input-inv-caducidad').value
            });
        }
    });

    if (errors.length > 0) {
        Swal.fire('Atención', `Revise los siguientes errores:<br><ul class="text-start mt-2">${errors.map(e => `<li>${e}</li>`).join('')}</ul>`, 'warning');
        return;
    }

    if (items.length === 0) {
        Swal.fire('Atención', 'Seleccione al menos un ítem para procesar.', 'info');
        return;
    }

    const confirm = await Swal.fire({
        title: '¿Procesar Inventario?',
        text: `Se registrarán ${items.length} entradas en el inventario vinculadas a esta compra.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, procesar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    const btn = document.getElementById('btnProcesarInventario');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

    try {
        const fd = new FormData();
        fd.append('data', JSON.stringify({ id_compra: idCompra, items: items }));
        const res = await fetch(`${window.CMG_urlBase}/procesarInventarioAjax`, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.ok) {
            Swal.fire('Éxito', data.mensaje, 'success');
            // Refrescar status de inventario de forma inmediata
            await mcCargarStatusInventario();
            mcSincronizarInventario(); 
        } else {
            Swal.fire('Error', data.mensaje, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Error de conexión: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
};

// Escuchar cambio de pestaña para sincronizar inventario o retenciones
const mcTabsEl = document.getElementById('mcTabs');
if (mcTabsEl) {
    mcTabsEl.addEventListener('shown.bs.tab', function (e) {
        if (e.target.id === 'tab-retenciones-tab') {
            CMG_cargarRetencionesCompra();
        } else if (e.target.id === 'tab-inventario-tab') {
            if (typeof mcSincronizarInventario === 'function') mcSincronizarInventario();
        }
    });
}

/**
 * Carga el listado de retenciones vinculadas a la compra actual
 */
window.CMG_cargarRetencionesCompra = async function() {
    const idCompra = document.getElementById('mcId').value || document.getElementById('modalCompra').dataset.id;
    const btnNueva = document.getElementById('btnNuevaRetencionCompra');
    const tbody = document.getElementById('mc-tbody-retenciones');
    
    if (!idCompra) {
        if (btnNueva) btnNueva.disabled = true;
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Primero debe guardar la compra para emitir una retención.</td></tr>';
        return;
    }

    // Determinar habilitación básica
    const tipoComp = (document.getElementById('mcTipoComprobante')?.value || '').padStart(2, '0');
    const permitidos = ['01', '03', '06'];
    let habilitado = idCompra && permitidos.includes(tipoComp);

    // Aplicar estado inicial al botón
    if (btnNueva) {
        btnNueva.disabled = !habilitado;
        btnNueva.title = !idCompra ? "Guarde la compra antes de emitir una retención." : 
                        (!permitidos.includes(tipoComp) ? "Tipo de comprobante no permite retención." : "");
    }

    const baseUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || ''));
    try {
        const res = await fetch(baseUrl + '/modulos/retenciones_compras/getPorCompraAjax?id_compra=' + idCompra);
        const data = await res.json();
        
        if (data.ok && data.rows.length > 0) {
            // Verificar si ya existe una retención que NO esté anulada
            const tieneRetencionActiva = data.rows.some(r => r.estado !== 'anulada' && r.estado !== 'anulado');
            if (tieneRetencionActiva) {
                habilitado = false;
                if (btnNueva) {
                    btnNueva.disabled = true;
                    btnNueva.title = "Ya existe una retención activa para este documento.";
                }
            }

            tbody.innerHTML = data.rows.map(r => {
                const est = (r.estado || '').toLowerCase();
                const isAut = est.includes('autoriza');
                const isBor = est.includes('borrador');
                const badgeCls = isAut ? 'success' : (isBor ? 'secondary' : 'warning');
                
                return `
                <tr style="cursor:pointer" onclick="window.RET_abrirModalDesdeLista('${r.id}')">
                    <td class="ps-3 fw-medium"><code>${r.establecimiento}-${r.punto_emision}-${r.secuencial}</code></td>
                    <td>${r.fecha_emision}</td>
                    <td class="text-end fw-bold">$${parseFloat(r.total_retenido).toFixed(2)}</td>
                    <td class="text-center">
                        <span class="badge bg-${badgeCls} bg-opacity-10 text-${badgeCls} border border-${badgeCls} border-opacity-25">
                            ${r.estado.toUpperCase()}
                        </span>
                    </td>
                    <td class="text-center">
                        <i class="bi bi-chevron-right text-muted"></i>
                    </td>
                </tr>`;
            }).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-file-earmark-text d-block fs-3 mb-2"></i>No hay retenciones registradas para esta compra</td></tr>';
        }
    } catch (e) {
        console.error('Error al cargar retenciones:', e);
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error al cargar retenciones vinculadas.</td></tr>';
    }
};

/**
 * Abre el modal de retención pre-cargando los datos de la compra
 */
window.CMG_nuevaRetencionDesdeCompra = function() {
    const idCompra = document.getElementById('mcId').value || document.getElementById('modalCompra').dataset.id;
    if (!idCompra) return;

    const modalCompra = document.getElementById('modalCompra');
    const idProv = document.getElementById('mcIdProveedor').value;
    const nombreProv = document.getElementById('mcBuscarProveedor').value;
    const numDoc = document.getElementById('mcNumeroComprobante').value;
    const fechaDoc = document.getElementById('mcFechaEmision').value;
    
    const subtotal = modalCompra.dataset.subtotalNeto || '0.00';
    const totalIva = modalCompra.dataset.totalIva || '0.00';

    if (typeof window.RET_abrirModalNuevo === 'function') {
        window.RET_abrirModalNuevo();
        
        setTimeout(() => {
            const form = document.getElementById('formRetencion');
            if (form) {
                form.dataset.compraSubtotal = subtotal;
                form.dataset.compraIva      = totalIva;
            }

            const elIdCompra = document.getElementById('ret_id_compra');
            if (elIdCompra) elIdCompra.value = idCompra;
            
            const elIdProv = document.getElementById('ret_id_proveedor');
            if (elIdProv) elIdProv.value = idProv;
            
            const elSearchProv = document.getElementById('ret_proveedor_search');
            if (elSearchProv) elSearchProv.value = nombreProv;
            
            const elNumDoc = document.getElementById('ret_num_doc_sustento');
            if (elNumDoc) elNumDoc.value = numDoc;
            
            const elFechaDoc = document.getElementById('ret_fecha_emision_doc_sustento');
            if (elFechaDoc) elFechaDoc.value = fechaDoc;

            // Totales del documento sustento (desde la compra)
            const elSub = document.getElementById('ret_doc_subtotal');
            const elIva = document.getElementById('ret_doc_iva');
            if (elSub) elSub.value = subtotal;
            if (elIva) elIva.value = totalIva;
            if (typeof window.RET_calcTotalSustento === 'function') window.RET_calcTotalSustento();
        }, 300);
    }
};

window.RET_abrirModalDesdeLista = function(id) {
    const tr = { dataset: { row: JSON.stringify({ id: id }) } };
    if (typeof window.RET_abrirModal === 'function') {
        window.RET_abrirModal(tr);
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// GESTIÓN DE PAGOS INTERNOS (EGRESOS) DESDE EL MODAL DE COMPRAS
// ─────────────────────────────────────────────────────────────────────────────
let _egresoDepsCargados = false;
let _egresoDeps = null;

// Hook para inicializar al cargar la pestaña de Pagos
document.addEventListener('DOMContentLoaded', () => {
    const tabPagos = document.getElementById('tab_pagos');
    if (tabPagos) {
        tabPagos.addEventListener('shown.bs.tab', () => {
            window.CMG_cargarPagosTab();
        });
    }
});

window.CMG_cargarPagosTab = async function() {
    const idCompra = document.getElementById('mcId').value || document.getElementById('modalCompra').dataset.id;
    
    const alertaNueva = document.getElementById('pagoAlertaNueva');
    const alertaPagada = document.getElementById('pagoAlertaPagada');
    const cardRegistro = document.getElementById('pagoCardRegistro');
    const tbody = document.getElementById('pagoTbodyHistorial');
    
    if (!alertaNueva || !alertaPagada || !cardRegistro || !tbody) return;

    // Resetear estados visuales
    alertaNueva.classList.remove('d-none');
    alertaPagada.classList.add('d-none');
    cardRegistro.classList.add('d-none');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-2"></i>Cargando historial...</td></tr>';

    if (!idCompra || idCompra === '') {
        // Caso: Compra nueva, no se puede pagar aún
        document.getElementById('pagoTotalCompra').textContent = '0.00';
        document.getElementById('pagoTotalAbonado').textContent = '0.00';
        document.getElementById('pagoSaldoPendiente').textContent = '0.00';
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Guarda la compra para poder registrar pagos internos.</td></tr>';
        return;
    }

    alertaNueva.classList.add('d-none');

    try {
        // 1. Cargar catálogos de Egresos si no están cargados
        if (!_egresoDepsCargados) {
            const respDeps = await fetch(`${window.CMG_urlBase}/getEgresoDependenciesAjax`);
            const resDeps = await respDeps.json();
            if (resDeps.ok) {
                _egresoDeps = resDeps.data;
                _egresoDepsCargados = true;
                
                // Poblar combos una sola vez
                const comboPto = document.getElementById('pagoPuntoEmision');
                if (comboPto) {
                    comboPto.innerHTML = '<option value="">— Seleccione Punto —</option>' + 
                        (_egresoDeps.puntos || []).map(p => `<option value="${p.id_punto}">${p.estab}-${p.punto}</option>`).join('');
                    if (_egresoDeps.puntos && _egresoDeps.puntos.length > 0) comboPto.selectedIndex = 1;
                }

                const comboConc = document.getElementById('pagoConcepto');
                if (comboConc) {
                    comboConc.innerHTML = '<option value="">— Seleccione Concepto —</option>' + 
                        (_egresoDeps.conceptos || []).map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
                    
                    // Autoseleccionar el primer concepto que tenga comportamiento 'COMPRA'
                    let cCompra = (_egresoDeps.conceptos || []).find(c => c.comportamiento === 'COMPRA');
                    if (!cCompra) {
                        cCompra = (_egresoDeps.conceptos || []).find(c => {
                            const n = (c.nombre || '').toLowerCase();
                            return n.includes('compra') || n.includes('proveedor');
                        });
                    }
                    if (cCompra) {
                        comboConc.value = cCompra.id;
                        comboConc.disabled = true;
                        comboConc.classList.add('bg-light');
                        comboConc.title = 'El concepto se define automáticamente para egresos de compras.';
                        
                        // Asegurarnos de que el valor se envíe en el FormData a pesar de estar disabled
                        let hd = document.getElementById('pagoConcepto_hidden');
                        if (!hd) {
                            hd = document.createElement('input');
                            hd.type = 'hidden';
                            hd.id = 'pagoConcepto_hidden';
                            hd.name = 'id_egreso_concepto'; // o el nombre que use para enviar al backend
                            comboConc.parentNode.appendChild(hd);
                        }
                        hd.value = cCompra.id;
                    }
                }

                const comboFP = document.getElementById('pagoFormaPago');
                if (comboFP) {
                    comboFP.innerHTML = '<option value="">— Seleccione Forma —</option>' + 
                        (_egresoDeps.formas_pago || []).map(fp => `<option value="${fp.id}">${fp.nombre}</option>`).join('');
                }

                const comboBanco = document.getElementById('pagoBancoId');
                if (comboBanco) {
                    comboBanco.innerHTML = '<option value="">— Opcional —</option>' + 
                        (_egresoDeps.bancos || []).map(b => `<option value="${b.id}">${b.nombre_banco}</option>`).join('');
                }
            }
        }

        // 2. Cargar datos actualizados de la compra y sus egresos
        const resp = await fetch(`${window.CMG_urlBase}/getCompraAjax?id=${idCompra}`);
        const res = await resp.json();

        if (!res.ok) throw new Error(res.mensaje || 'Error al consultar compra');

        const compra = res.data;
        const totalFactura = parseFloat(compra.importe_total || 0);
        
        // Calcular abonos activos (ignorando los que tengan estado anulado)
        let totalAbonado = 0;
        tbody.innerHTML = '';

        if (compra.egresos_vinculados && compra.egresos_vinculados.length > 0) {
            compra.egresos_vinculados.forEach(eg => {
                const esAnulado = (eg.estado || '').toLowerCase() === 'anulado';
                const montoVal = parseFloat(eg.monto_pagado || 0);
                if (!esAnulado) {
                    totalAbonado += montoVal;
                }

                const tr = document.createElement('tr');
                if (esAnulado) tr.classList.add('table-danger', 'text-decoration-line-through', 'opacity-50');
                
                const fEmis = eg.fecha_emision ? eg.fecha_emision.slice(0,10).split('-').reverse().join('/') : '—';
                
                tr.innerHTML = `
                    <td class="ps-3">${fEmis}</td>
                    <td>
                        <code class="text-secondary fw-bold">${_esc(eg.numero_egreso || '')}</code>
                        ${esAnulado ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1" style="font-size: 0.6rem;">ANULADO</span>' : ''}
                    </td>
                    <td>
                        <div class="fw-medium">${_esc(eg.concepto_nombre || '')}</div>
                        <small class="text-muted" style="font-size: 0.65rem;">${_esc(eg.formas_pago || '—')}</small>
                    </td>
                    <td class="text-end fw-bold pe-3">$ ${montoVal.toFixed(2)}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay egresos ni pagos registrados aún.</td></tr>';
        }

        // Obtener y sumar las retenciones vinculadas
        let totalRetenido = 0;
        try {
            const baseUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || ''));
            const resRet = await fetch(baseUrl + '/modulos/retenciones_compras/getPorCompraAjax?id_compra=' + idCompra);
            const dataRet = await resRet.json();
            if (dataRet.ok && dataRet.rows) {
                dataRet.rows.forEach(r => {
                    const est = (r.estado || '').toLowerCase();
                    if (est !== 'anulada' && est !== 'anulado') {
                        totalRetenido += parseFloat(r.total_retenido || 0);
                    }
                });
            }
        } catch (e) {
            console.error('Error al obtener retenciones para restar del pago:', e);
        }

        const saldo = Math.max(0, totalFactura - totalAbonado - totalRetenido);

        // Actualizar paneles superiores
        document.getElementById('pagoTotalCompra').textContent = totalFactura.toFixed(2);
        if (document.getElementById('pagoTotalRetencion')) document.getElementById('pagoTotalRetencion').textContent = totalRetenido.toFixed(2);
        document.getElementById('pagoTotalAbonado').textContent = totalAbonado.toFixed(2);
        document.getElementById('pagoSaldoPendiente').textContent = saldo.toFixed(2);

        // Determinar visibilidad de registro
        if (saldo < 0.01) {
            alertaPagada.classList.remove('d-none');
            cardRegistro.classList.add('d-none');
        } else {
            alertaPagada.classList.add('d-none');
            cardRegistro.classList.remove('d-none');
            
            // Prefillar datos para registrar pago rápido
            const montoInput = document.getElementById('pagoMontoPagar');
            if (montoInput) {
                montoInput.value = saldo.toFixed(2);
                montoInput.max = saldo;
            }
            
            const obsInput = document.getElementById('pagoObservaciones');
            if (obsInput) {
                const numComp = document.getElementById('mcNumeroComprobante').value;
                obsInput.value = `Pago de Compra #${numComp}`;
            }
            
            const fEmisInput = document.getElementById('pagoFechaEmision');
            if (fEmisInput) {
                const d_now = new Date();
                const hoy = d_now.getFullYear() + '-' + String(d_now.getMonth() + 1).padStart(2, '0') + '-' + String(d_now.getDate()).padStart(2, '0');
                fEmisInput.value = hoy;
            }
        }

    } catch (e) {
        console.error('Error al renderizar pestaña de pagos:', e);
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Fallo al cargar detalles: ${e.message}</td></tr>`;
    }
};

window.CMG_toggleEgresoBancoForm = function(formaPagoId) {
    const divBanco = document.getElementById('pagoDivDetalleBanco');
    if (!divBanco) return;
    
    const fpId = parseInt(formaPagoId, 10) || 0;
    const fp = (_egresoDeps?.formas_pago || []).find(x => parseInt(x.id, 10) === fpId);
    
    if (fp && fp.tipo === 'BANCO') {
        divBanco.classList.remove('d-none');
        const inputOp = document.getElementById('pagoTipoOp');
        if (inputOp) inputOp.required = true;
        
        // Disparar toggle interno
        if (inputOp && window.CMG_toggleTipoOp) {
            window.CMG_toggleTipoOp(inputOp.value);
        }
    } else {
        divBanco.classList.add('d-none');
        const inputOp = document.getElementById('pagoTipoOp');
        if (inputOp) inputOp.required = false;
        // Limpiar campos
        const inputNum = document.getElementById('pagoNumOp');
        if (inputNum) inputNum.value = '';
        const inputFC = document.getElementById('pagoFechaCobro');
        if (inputFC) inputFC.value = '';
    }
};

window.CMG_toggleTipoOp = function(tipo) {
    const divNumOp = document.getElementById('pagoDivNumOp');
    const lblNumOp = document.getElementById('pagoLblNumOp');
    const divFechaCobro = document.getElementById('pagoDivFechaCobro');
    const inputNumOp = document.getElementById('pagoNumOp');

    if (tipo === 'CHEQUE') {
        if (lblNumOp) lblNumOp.innerHTML = '<i class="bi bi-card-checklist me-1"></i>Nº Cheque';
        if (inputNumOp) inputNumOp.placeholder = 'Autogenerado / Nº';
        if (divFechaCobro) divFechaCobro.classList.remove('d-none');
        
        // Fecha cobro por defecto a hoy si esta vacía
        const inputFC = document.getElementById('pagoFechaCobro');
        if (inputFC && !inputFC.value) {
            const d = new Date();
            inputFC.value = d.toISOString().split('T')[0];
        }
    } else {
        if (lblNumOp) lblNumOp.textContent = 'Nº Referencia';
        if (inputNumOp) inputNumOp.placeholder = 'Nº doc / Transf';
        if (divFechaCobro) divFechaCobro.classList.add('d-none');
    }
};

window.CMG_registrarPagoEgreso = async function(e) {
    if (e) e.preventDefault();
    
    const btn = document.getElementById('pagoBtnRegistrar');
    const idCompra = document.getElementById('mcId').value || document.getElementById('modalCompra').dataset.id;
    
    if (!idCompra) {
        Swal.fire('Atención', 'Debe guardar la compra antes de emitir un pago.', 'warning');
        return;
    }

    const pPunto = document.getElementById('pagoPuntoEmision');
    if (!pPunto || !pPunto.value) {
        Swal.fire('Atención', 'Debe seleccionar un punto de emisión.', 'warning');
        return;
    }

    const pConcepto = document.getElementById('pagoConcepto');
    if (!pConcepto || !pConcepto.value) {
        Swal.fire('Atención', 'Debe seleccionar un concepto de egreso.', 'warning');
        return;
    }

    const pFP = document.getElementById('pagoFormaPago');
    if (!pFP || !pFP.value) {
        Swal.fire('Atención', 'Debe seleccionar una forma de pago.', 'warning');
        return;
    }

    const montoPagar = parseFloat(document.getElementById('pagoMontoPagar').value) || 0;
    const saldoActual = parseFloat(document.getElementById('pagoSaldoPendiente').textContent) || 0;

    if (montoPagar <= 0) {
        Swal.fire('Atención', 'El monto a pagar debe ser mayor a cero.', 'warning');
        return;
    }

    if (montoPagar > (saldoActual + 0.01)) {
        const sConf = await Swal.fire({
            title: '¿Monto superior?',
            text: `El valor ingresado ($${montoPagar.toFixed(2)}) es mayor al saldo pendiente ($${saldoActual.toFixed(2)}). ¿Está seguro de continuar?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Modificar'
        });
        if (!sConf.isConfirmed) return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="spinner-grow spinner-grow-sm me-2"></i>Registrando...';
    }

    try {
        const divBanco = document.getElementById('pagoDivDetalleBanco');
        const esBanco = divBanco && !divBanco.classList.contains('d-none');

        const payload = {
            id_compra: parseInt(idCompra, 10),
            monto_pagar: montoPagar,
            saldo_actual: saldoActual,
            id_punto_emision: parseInt(document.getElementById('pagoPuntoEmision').value, 10),
            fecha_emision: document.getElementById('pagoFechaEmision').value,
            id_egreso_concepto: parseInt(document.getElementById('pagoConcepto').value, 10),
            id_forma_pago: parseInt(document.getElementById('pagoFormaPago').value, 10),
            tipo_operacion_bancaria: esBanco ? (document.getElementById('pagoTipoOp')?.value || null) : null,
            numero_operacion: esBanco ? (document.getElementById('pagoNumOp')?.value || null) : null,
            fecha_cobro: esBanco && document.getElementById('pagoTipoOp')?.value === 'CHEQUE' ? (document.getElementById('pagoFechaCobro')?.value || null) : null,
            observaciones: document.getElementById('pagoObservaciones')?.value || ''
        };

        const resp = await fetch(`${window.CMG_urlBase}/registrarEgresoAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const res = await resp.json();

        if (res.ok) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: res.msg || 'Egreso generado correctamente.',
                timer: 2000,
                showConfirmButton: false
            });

            // Limpiar form parcial
            const fNum = document.getElementById('pagoNumOp');
            if (fNum) fNum.value = '';
            
            // Refrescar datos del tab de pagos
            await window.CMG_cargarPagosTab();
            
            // Recargar listado principal de compras en el fondo para reflejar saldos si aplica
            if (typeof window.CMG_fetchSearch === 'function') {
                window.CMG_fetchSearch(window.CMG_currentPage || 1);
            }
        } else {
            Swal.fire('Error', res.error || 'No se pudo generar el pago.', 'error');
        }
    } catch (err) {
        console.error('Error al registrar pago:', err);
        Swal.fire('Error de Red', 'Ocurrió un fallo inesperado al conectarse al servidor.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Registrar Pago y Generar Egreso';
        }
    }
};


// ─────────────────────────────────────────────────────────────────────────────
// DESCARGAR XML DEL DOCUMENTO ELECTRONICO
// ─────────────────────────────────────────────────────────────────────────────
window.mcDescargarXml = function () {
    const id = document.getElementById('mcId')?.value;
    if (!id) {
        Swal.fire('Sin ID', 'No hay compra seleccionada.', 'warning');
        return;
    }
    const url = `${window.CMG_urlBase}/descargarXmlAjax?id=${id}`;
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};

// Exportar (descargar) PDF de la compra (mismo formato que facturas de venta)
window.mcExportarPdf = function () {
    const id = document.getElementById('mcId')?.value || document.getElementById('modalCompra')?.dataset.id;
    if (!id) {
        Swal.fire('Atención', 'Guarde la compra primero para generar el PDF.', 'warning');
        return;
    }
    const a = document.createElement('a');
    a.href = `${window.CMG_urlBase}/exportar-pdf-ajax?id=${id}`;
    a.download = '';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};
