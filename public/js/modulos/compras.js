'use strict';

// ─── CONFIGURACIÓN INICIAL ───
let _ivaDefault = 15;
let _codigoIvaDefault = '4'; // codigoPorcentaje del SRI de la tarifa por defecto (15%)

// Deriva el codigoPorcentaje del SRI desde un porcentaje (unívoco solo para % > 0). Para 0% no es
// unívoco (0/6/7), por eso solo se usa como respaldo cuando no llega el codigoPorcentaje real.
function _codigoIvaPorPct(pct) {
    const p = parseFloat(pct) || 0;
    const lista = window.CMG_tarifasIva || [];
    const t = lista.find(x => parseFloat(x.porcentaje_iva) === p && String(x.status) === '1')
           || lista.find(x => parseFloat(x.porcentaje_iva) === p);
    return t ? String(t.codigo) : (p > 0 ? _codigoIvaDefault : '0');
}
const CMG_TIPOS_MASCARA = ['01', '03', '04', '05', '06', '09', '11', '12', '15', '16', '18', '19', '20', '21', '41', '42', '43', '47', '48'];

// Verificar si la empresa es Persona Natural
// Verificar si la empresa es Persona Natural (Acepta '1' o '01')
const _esPersonaNatural = (window.CMG_empresa?.tipo == '1' || window.CMG_empresa?.tipo == '01');

// Precio sugerido al elegir un producto del catálogo (no viene de un XML, es una entrada manual):
// se usa el decimales_precio configurado por la empresa como cantidad de decimales por defecto.
const DEC_PRECIO = parseInt(window.CMG_empresa?.decimales_precio ?? 2, 10) || 2;

// Redondeo a centavos compartido por CMG_recalcularFila y CMG_recalcularTotales — debe ser
// exactamente la misma función en ambos lados. Con valores como 1500 * 1.04347 = 1565.205 (mitad
// exacta de centavo), el binario de punto flotante puede representarlo por debajo de .205
// (1565.2049999...) y un `.toFixed(2)` directo sobre esa multiplicación redondea para abajo
// (1565.20), mientras que Math.round(v*100)/100 aplicado igual en los dos lugares sí redondea
// para arriba (1565.21) de forma consistente — evita que el subtotal de una línea individual
// no coincida con el subtotal agrupado por tarifa de IVA que lo debería igualar.
const _r2 = v => Math.round(v * 100) / 100;

// Muestra cantidad/precio_unitario TAL COMO están guardados en BD (que a su vez preserva lo que
// trajo el XML del SRI), sin forzar un número fijo de decimales: cada proveedor puede declarar
// una cantidad distinta de decimales, así que no hay un DEC_CANT/DEC_PRECIO de empresa que sirva
// para esto. Solo se recortan ceros de cola sobrantes (3.120000 -> 3.12); la escala máxima de las
// columnas (compras_detalle.cantidad/precio_unitario) es 6 decimales.
function _fmtExacto(v) {
    const n = parseFloat(v);
    if (!isFinite(n)) return '0';
    return n.toFixed(6).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.CMG_tarifasIva && window.CMG_tarifasIva.length > 0) {
        const tDefault = window.CMG_tarifasIva.find(t => parseFloat(t.porcentaje_iva) > 0) || window.CMG_tarifasIva[0];
        _ivaDefault = parseFloat(tDefault.porcentaje_iva);
        _codigoIvaDefault = String(tDefault.codigo);
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
    // Revertir cualquier bloqueo de solo-lectura de una compra abierta antes
    // (evita que los campos queden deshabilitados al pasar de una migrada/período
    // cerrado a una normal). Debe correr ANTES del bloqueo de electrónica.
    mcLimpiarBloqueoSoloLectura();

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

    // Mostrar/ocultar botones XML y PDF (ambos requieren XML del comprobante:
    // se arman a partir del sobre electrónico, no de las tablas de la compra).
    const tieneXml = !!(d.detalle_xml && d.detalle_xml.trim().length > 0);
    const btnXml = document.getElementById('mcBtnDescargarXml');
    if (btnXml) btnXml.classList.toggle('d-none', !tieneXml);
    const btnPdf = document.getElementById('mcBtnPdf');
    if (btnPdf) btnPdf.classList.toggle('d-none', !tieneXml);

    // Excel: a diferencia de PDF/XML, se arma desde las tablas de la compra
    // (compras_detalle/compras_pagos), no del XML — disponible en CUALQUIER
    // compra ya guardada, tenga o no comprobante electrónico adjunto.
    const btnExcel = document.getElementById('mcBtnExcel');
    if (btnExcel) btnExcel.classList.toggle('d-none', !(d.id > 0));

    // Pestaña de documentos relacionados (NC ↔ factura)
    mcActualizarPestanaRelacionados(d);

    // Pestaña de detalle de reembolso (factura recibida codDocReembolso=41)
    mcActualizarPestanaReembolso(d);

    // Solo lectura total: compra migrada o período contable cerrado
    // (se aplica al final para que prevalezca sobre el bloqueo de electrónica).
    mcAplicarSoloLectura(d);
}

/**
 * Revierte el bloqueo de solo-lectura: re-habilita SOLO lo que ese bloqueo
 * deshabilitó (marcado con `.mc-lock-off`) y oculta el banner. Es idempotente y
 * no toca campos deshabilitados por otra lógica (electrónica, naturales).
 */
function mcLimpiarBloqueoSoloLectura() {
    const modal = document.getElementById('modalCompra');
    if (!modal) return;
    modal.querySelectorAll('.mc-lock-off').forEach(el => {
        el.disabled = false;
        el.classList.remove('mc-lock-off');
    });
    document.getElementById('mcBloqueoAviso')?.classList.add('d-none');
}

/**
 * Bloquea el modal por completo (solo lectura) cuando la compra proviene de una
 * migración o su período contable está cerrado. Devuelve true si quedó bloqueado.
 * La limpieza de un bloqueo previo la hace `mcLimpiarBloqueoSoloLectura()` al
 * inicio de `CMG_poblarModal`/`CMG_resetModal` (antes de esta función).
 */
function mcAplicarSoloLectura(d) {
    const esV = v => (v === true || v === 't' || v === 1 || v === '1');
    const esMigrado      = esV(d.es_migrado);
    const periodoCerrado = esV(d.periodo_cerrado);
    const bloqueado      = esMigrado || periodoCerrado;

    const aviso    = document.getElementById('mcBloqueoAviso');
    const avisoTxt = document.getElementById('mcBloqueoAvisoTexto');

    if (!bloqueado) {
        aviso?.classList.add('d-none');
        return false;
    }

    let msg;
    if (esMigrado && periodoCerrado) {
        msg = 'Esta compra proviene de una migración y su período contable está cerrado: es de solo lectura.';
    } else if (esMigrado) {
        msg = 'Esta compra proviene de una migración: es de solo lectura, no puede editarse.';
    } else {
        msg = 'El período contable de esta compra está cerrado: es de solo lectura, no puede editarse.';
    }
    if (avisoTxt) avisoTxt.textContent = msg;
    aviso?.classList.remove('d-none');

    const modal    = document.getElementById('modalCompra');
    const pagoForm = document.getElementById('pagoFormNuevo');

    // Deshabilita el elemento marcándolo, salvo que YA estuviera deshabilitado por
    // otra lógica (no lo tocamos para no re-habilitarlo por error después), o que
    // sea parte del formulario de pago interno (pagar sí se permite en migradas).
    const bloquear = el => {
        if (el.disabled) return;
        if (pagoForm && pagoForm.contains(el)) return;
        el.disabled = true;
        el.classList.add('mc-lock-off');
    };

    modal.querySelectorAll('.modal-body input, .modal-body select, .modal-body textarea').forEach(bloquear);
    modal.querySelectorAll('.modal-body button').forEach(btn => {
        if (btn.id === 'mcBtnPdf' || btn.id === 'mcBtnExcel' || btn.id === 'mcBtnDescargarXml') return;
        if (btn.hasAttribute('data-bs-toggle')) return; // pestañas
        bloquear(btn);
    });

    // Sin edición: ocultar Guardar siempre que esté bloqueada (migrada o período cerrado).
    document.getElementById('btnGuardarCompra')?.classList.add('d-none');

    // Eliminar SÍ se permite para compras migradas (a diferencia de editar) — solo se
    // oculta cuando el bloqueo es por período contable cerrado (esa restricción aplica
    // igual a migradas y no migradas). El backend (ComprasService::eliminar) ya no exige
    // que la compra no sea migrada; solo valida el período, así que este ajuste de UI
    // no abre nada que el servidor no aceptara.
    if (periodoCerrado) {
        document.getElementById('btnEliminarCompra')?.classList.add('d-none');
    }

    return true;
}

function mcActualizarBloqueoCampos() {
    const regEl = document.getElementById('mcTipoRegistro');
    if (!regEl) return;
    const isElectronico = regEl.value === 'electronico';
    
    // IDs de campos internos (clasificación propia, no del comprobante) que
    // SIEMPRE deben ser editables. La propina NO va aquí: afecta el total, que
    // en una compra electrónica debe coincidir con el XML autorizado.
    const permitidos = ['mcDeducible', 'mcMotivo', 'mcObservaciones'];
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

    // Propina: bloqueada en electrónica (forma parte del total del comprobante)
    const propinaEl = document.getElementById('mcInputPropina');
    if (propinaEl) propinaEl.disabled = isElectronico;

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

    // Compra nueva = tipo_registro 'fisica' por defecto (ver arriba); su forma de
    // pago SRI se preselecciona en '20' (Otros con utilización del sistema
    // financiero). Sigue siendo editable, es solo el valor inicial.
    CMG_agregarFormaPagoSRI('20', 0);

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

    // Ocultar aviso de líneas sin vincular
    document.getElementById('mc-inventario-aviso-vinculacion')?.classList.add('d-none');

    // Reset pestaña Orden de Compra (compra nueva = sin vincular todavía)
    document.getElementById('oc-tab-vinculada')?.classList.add('d-none');
    document.getElementById('oc-tab-sin-vincular')?.classList.remove('d-none');
    window._ocTabOrdenesAbiertas = [];
    const ocBuscar = document.getElementById('oc-tab-buscar');
    if (ocBuscar) { ocBuscar.value = ''; ocBuscar.disabled = true; ocBuscar.placeholder = 'Seleccione un proveedor primero...'; }
    const ocIdOrden = document.getElementById('oc-tab-id-orden');
    if (ocIdOrden) ocIdOrden.value = '';
    document.getElementById('oc-tab-lista')?.classList.add('d-none');
    document.getElementById('oc-tab-btn-vincular')?.setAttribute('disabled', 'disabled');
    document.getElementById('oc-tab-sin-abiertas')?.classList.add('d-none');

    // Reset botones barra superior
    document.getElementById('btnEliminarCompraBar')?.classList.add('d-none');

    CMG_recalcularTotales();

    // Ocultar botón XML
    const btnXml = document.getElementById('mcBtnDescargarXml');
    if (btnXml) btnXml.classList.add('d-none');

    // Ocultar botón PDF (compra nueva / sin guardar)
    const btnPdf = document.getElementById('mcBtnPdf');
    if (btnPdf) btnPdf.classList.add('d-none');

    // Ocultar botón Excel (compra nueva / sin guardar)
    const btnExcel = document.getElementById('mcBtnExcel');
    if (btnExcel) btnExcel.classList.add('d-none');

    // Revertir bloqueo de solo lectura y ocultar su aviso (migrada / período cerrado)
    mcLimpiarBloqueoSoloLectura();

    // Ocultar pestaña de documentos relacionados
    document.getElementById('tab-relacionados-li')?.classList.add('d-none');

    // Ocultar pestaña de detalle de reembolso (compra nueva) y su bloqueo de sustento
    document.getElementById('tab-reembolso-li')?.classList.add('d-none');
    mcAplicarBloqueoSustento(false);

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

function mcAutosizeTextareaInv(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
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

            // Re-render completo de la fila en Inventario (en vez de parchear el DOM a mano)
            // para que se apliquen también el botón "Quitar vinculación" y el resto de reglas
            // de la fila (medida por defecto, bodega por defecto, etc.) igual que en cualquier
            // otro camino de vinculación.
            mcSincronizarInventario();

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
            codigo_porcentaje: p.codigo_porcentaje_iva || _codigoIvaPorPct(iva),
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

// Dropdown de resultados actualmente visible (para reposicionarlo/cerrarlo desde
// un único listener de scroll/resize; ver mcRepositionVinculacionInline).
let _vincListaAbierta = null;
let _vincInputAbierta = null;

function mcRepositionVinculacionInline() {
    if (!_vincListaAbierta || !_vincInputAbierta) return;
    const grupo = _vincInputAbierta.closest('.input-group') || _vincInputAbierta;
    const r = grupo.getBoundingClientRect();
    _vincListaAbierta.style.top = (r.bottom + 2) + 'px';
    _vincListaAbierta.style.left = r.left + 'px';
    _vincListaAbierta.style.width = r.width + 'px';
}

if (!window._mcVincScrollListenerInit) {
    window._mcVincScrollListenerInit = true;
    window.addEventListener('scroll', mcRepositionVinculacionInline, true);
    window.addEventListener('resize', mcRepositionVinculacionInline);
}

window.CMG_mostrarBuscadorInline = function(idx) {
    const cont = document.getElementById(`vinc-cont-${idx}`);
    if (!cont) return;

    cont.querySelector('.info-vinculacion').classList.add('d-none');
    const divBusq = cont.querySelector('.buscador-inline-div');
    divBusq.classList.remove('d-none');
    const input = divBusq.querySelector('.input-buscar-inline');
    const lista = divBusq.querySelector('.lista-resultados-inline');

    // La tabla de inventario tiene scroll propio (.table-responsive con overflow
    // auto); un dropdown "position: absolute" queda recortado por ese scroll.
    // Se posiciona como "fixed" sobre el input y con z-index por encima del
    // modal (.modal = 5060 !important, ver app.css) para que se vea por
    // arriba de todo el modal en vez de encajonado dentro del listado.
    lista.style.position = 'fixed';
    lista.style.zIndex = 5070;
    _vincListaAbierta = lista;
    _vincInputAbierta = input;
    mcRepositionVinculacionInline();

    input.focus();

    // Timer local para el debounce
    let timerInline;
    input.oninput = function(e) {
        clearTimeout(timerInline);
        const q = e.target.value.trim();

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
                            btn.innerHTML = `<div class="d-flex justify-content-between align-items-center gap-2">
                                <span>${_esc(p.nombre)}</span>
                                <small class="text-muted font-monospace flex-shrink-0">${_esc(p.codigo_principal || p.codigo || '')}</small>
                            </div>`;
                            btn.onclick = () => {
                                _idxAVincularInv = idx;
                                lista.classList.add('d-none');
                                if (_vincListaAbierta === lista) {
                                    _vincListaAbierta = null;
                                    _vincInputAbierta = null;
                                }
                                CMG_seleccionarProducto(p);
                            };
                            lista.appendChild(btn);
                        });
                    }
                    mcRepositionVinculacionInline();
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
    const lista = cont.querySelector('.lista-resultados-inline');
    lista.classList.add('d-none');
    if (_vincListaAbierta === lista) {
        _vincListaAbierta = null;
        _vincInputAbierta = null;
    }
};

/**
 * Quita la vinculación de un producto en la pestaña Inventario (por si el usuario se
 * equivocó al vincular). Solo se ofrece en líneas aún sin nada procesado a inventario;
 * lo procesado ya quedó registrado con el producto original y no se toca.
 */
window.mcQuitarVinculacionInv = async function(idx) {
    const trInv = document.querySelector(`#mc-tbody-inventario tr[data-index="${idx}"]`);
    if (!trInv) return;

    const nombre = trInv.querySelector('.mc-nombre-inv')?.value || 'este producto';
    const codigo = trInv.dataset.codigo || '';
    const idProv = document.getElementById('mcIdProveedor')?.value || '';

    const confirm = await Swal.fire({
        title: '¿Quitar vinculación?',
        html: `Se quitará la vinculación de <strong>${_esc(nombre)}</strong> con el producto del catálogo y se olvidará la homologación guardada para este código de proveedor, para poder buscar y vincular el producto correcto.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirm.isConfirmed) return;

    if (idProv && codigo) {
        try {
            const fd = new FormData();
            fd.append('id_proveedor', idProv);
            fd.append('codigo_proveedor', codigo);
            await fetch(`${window.CMG_urlBase}/eliminarVinculacionAjax`, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        } catch (err) { console.error('Error al eliminar homologación:', err); }
    }

    // Limpiar el vínculo en memoria (Detalle + Inventario); se persiste al Guardar la
    // compra, igual que ocurre al vincular desde CMG_seleccionarProducto.
    const trDet = document.querySelector(`#tbodyDetalle tr[data-idx="${idx}"]`);
    if (trDet) {
        const inputIdProd = trDet.querySelector('.input-id-producto');
        if (inputIdProd) inputIdProd.value = '';
        const inputIdMedida = trDet.querySelector('.input-id-medida');
        if (inputIdMedida) inputIdMedida.value = '';
        const inputIdTipoMedida = trDet.querySelector('.input-id-tipo-medida');
        if (inputIdTipoMedida) inputIdTipoMedida.value = '';
        trDet.dataset.productoNombre = '';
    }

    // Misma secuencia que al cambiar a la pestaña Inventario (ver listener de
    // 'shown.bs.tab'): refrescar primero el status procesado y luego re-sincronizar,
    // para evitar que quede una sincronización a medias con datos obsoletos.
    window._mcSincronizando = false;
    if (typeof mcCargarStatusInventario === 'function') {
        await mcCargarStatusInventario();
    }
    mcSincronizarInventario();

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Vinculación eliminada',
        showConfirmButton: false,
        timer: 2000
    });
};


window.CMG_agregarItemLibre = function() {
    CMG_agregarFilaDetalle({ descripcion:'', cantidad:1, precio_unitario:0, descuento:0, impuestos:[{codigo_impuesto:'2',codigo_porcentaje:_codigoIvaDefault,tarifa:_ivaDefault,base_imponible:0,valor:0}] });
};

function CMG_agregarFilaDetalle(det) {
    const tbody = document.getElementById('tbodyDetalle');
    const idx   = tbody.rows.length;
    

    // Preseleccionar por el codigoPorcentaje real del detalle (para no colapsar 0%/Exento/No objeto,
    // que comparten porcentaje 0). Solo si no llega el código se usa el porcentaje como respaldo.
    const codDet = (det.impuestos && det.impuestos.length && (det.impuestos[0].codigo_porcentaje ?? '') !== '')
        ? String(det.impuestos[0].codigo_porcentaje) : '';
    const ivaPct = det.impuestos && det.impuestos.length ? parseFloat(det.impuestos[0].tarifa||0) : _ivaDefault;
    let _ivaMatched = false;
    let opcIva = (window.CMG_tarifasIva || []).map(t => {
        const sel = codDet !== '' ? String(t.codigo) === codDet : parseFloat(t.porcentaje_iva) === ivaPct;
        if (sel) _ivaMatched = true;
        return `<option value="${t.codigo}" data-codigo="${t.codigo}" data-tarifa="${t.porcentaje_iva}" ${sel?'selected':''}>${t.tarifa || (t.porcentaje_iva + '%')}</option>`;
    }).join('');
    if (!_ivaMatched && det.impuestos && det.impuestos.length && !isNaN(ivaPct)) {
        // Tarifa histórica que ya no está activa en el catálogo (p.ej. IVA 12% de años anteriores,
        // hoy inactivo): se agrega la opción SOLO para este documento, para mostrar el IVA real con
        // que se registró. No afecta a documentos nuevos (el catálogo sigue ofreciendo solo activas).
        const _codH = codDet !== '' ? codDet : (typeof _codigoIvaPorPct === 'function' ? _codigoIvaPorPct(ivaPct) : '');
        opcIva += `<option value="${_codH}" data-codigo="${_codH}" data-tarifa="${ivaPct}" selected>${ivaPct}%</option>`;
    }

    // Subtotal DECLARADO en el XML del SRI para esta línea (precioTotalSinImpuesto), ya guardado
    // en compras_detalle.precio_total_sin_impuesto. Mientras la línea no se edite, se usa TAL CUAL
    // (no se recalcula cantidad*precio en el navegador, que puede redondear distinto que el emisor
    // del comprobante) tanto para el subtotal de esta fila como para los totales agrupados. En
    // cuanto el usuario edita cantidad/precio/descuento/IVA de la línea, deja de haber un valor
    // "declarado" que respetar y se recalcula en vivo (ver los oninput/onchange de abajo).
    const esLineaExistente = !!det.id && det.precio_total_sin_impuesto !== undefined && det.precio_total_sin_impuesto !== null;

    const tr = document.createElement('tr');
    tr.className = 'row-detalle';
    tr.dataset.idx = idx;
    tr.dataset.productoNombre = det.producto_nombre || '';
    tr.dataset.descripcionOriginal = det.descripcion || '';
    tr.dataset.subtotalOriginal = esLineaExistente ? String(det.precio_total_sin_impuesto) : '';
    tr.innerHTML = `
        <td class="ps-3">
            <input type="text" class="form-control form-control-sm input-detalle input-descripcion" value="${_esc(det.descripcion||'')}" placeholder="Descripción del producto..." oninput="CMG_recalcularTotales()">
            <input type="hidden" class="input-id-detalle" value="${det.id || ''}">
            <input type="hidden" class="input-id-producto" value="${det.id_producto || det.id_producto_vinculado || ''}">
            <input type="hidden" class="input-codigo" value="${det.codigo_principal || ''}">
            <input type="hidden" class="input-id-medida" value="${det.product_id_medida || det.id_medida || ''}">
            <input type="hidden" class="input-id-tipo-medida" value="${det.product_id_tipo_medida || det.id_tipo_medida || ''}">
        </td>
        <td><input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad" value="${det.cantidad != null ? _fmtExacto(det.cantidad) : '1'}" min="0.0001" step="any" oninput="this.closest('tr').dataset.subtotalOriginal='';CMG_recalcularFila(this)"></td>
        <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio" value="${det.precio_unitario != null ? _fmtExacto(det.precio_unitario) : '0'}" min="0" step="any" oninput="this.closest('tr').dataset.subtotalOriginal='';CMG_recalcularFila(this)"></td>
        <td><input type="number" class="form-control form-control-sm input-detalle text-end text-danger input-desc" value="${parseFloat(det.descuento||0).toFixed(2)}" min="0" step="any" oninput="this.closest('tr').dataset.subtotalOriginal='';CMG_recalcularFila(this)"></td>
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
    if (esLineaExistente) {
        tr.querySelector('.subtotal-line').textContent = parseFloat(det.precio_total_sin_impuesto).toFixed(2);
    } else {
        CMG_recalcularFila(tr.querySelector('.input-cantidad'));
    }
}

function CMG_recalcularFila(input) {
    const tr    = input.closest('tr');
    const cant  = parseFloat(tr.querySelector('.input-cantidad').value || 0);
    const prec  = parseFloat(tr.querySelector('.input-precio').value || 0);
    const desc  = _r2(parseFloat(tr.querySelector('.input-desc').value || 0));
    const bruto = _r2(cant * prec);
    const neto  = _r2(Math.max(0, bruto - Math.min(desc, bruto)));
    tr.querySelector('.subtotal-line').textContent = neto.toFixed(2);
    CMG_recalcularTotales();
}

function CMG_recalcularTotales() {
    // Redondear a centavos en cada acumulación (no solo al mostrar), con la MISMA función _r2 que
    // usa CMG_recalcularFila para el subtotal de cada línea — para que la suma de los
    // "Subtotal"/"IVA" que se ven en pantalla coincida siempre con el "Total General" y con el
    // subtotal individual de cada línea (ver comentario de _r2 más arriba en el archivo).
    const r2 = _r2;

    let totalDesc = 0, subTotalBruto = 0;
    const grupos = {}; // Para agrupar por tarifa IVA
    const rows = document.querySelectorAll('#tbodyDetalle tr');

    rows.forEach(tr => {
        const cant  = parseFloat(tr.querySelector('.input-cantidad')?.value || 0);
        const prec  = parseFloat(tr.querySelector('.input-precio')?.value || 0);
        const desc  = r2(parseFloat(tr.querySelector('.input-desc')?.value || 0));
        const sel   = tr.querySelector('.input-iva');
        const tarifa = sel ? parseFloat(sel.selectedOptions[0]?.dataset.tarifa || 0) : 0;
        const codPct = sel ? sel.value : '0';
        // Etiqueta descriptiva del tipo de IVA (texto de la opción: "IVA 15%",
        // "0%", "No objeto de IVA", "Exento"...), igual que en facturas de venta.
        const label  = sel ? (sel.selectedOptions[0]?.text || (tarifa + '%')) : (tarifa + '%');

        // Si la línea no fue editada y viene de un documento ya guardado, se respeta el subtotal
        // TAL CUAL lo declaró el XML del SRI (precioTotalSinImpuesto) en vez de recalcular
        // cantidad*precio, que puede redondear distinto que el sistema del proveedor (ver
        // CMG_agregarFilaDetalle). El bruto (pre-descuento) se deriva de ese valor confiable, no al
        // revés, para que Subtotal - Descuento siga coincidiendo con la suma de bases por IVA.
        const original = tr.dataset.subtotalOriginal;
        let brutoFila, descLinea, netoFila;
        if (original) {
            netoFila  = r2(parseFloat(original));
            descLinea = desc;
            brutoFila = r2(netoFila + descLinea);
        } else {
            brutoFila = r2(cant * prec);
            // Si el descuento de la línea supera su propio subtotal, se acota a este (evita bases
            // negativas) y se usa ese mismo valor acotado para totalDesc — así Subtotal - Descuento
            // sigue coincidiendo exactamente con la suma de las bases por tarifa de IVA.
            descLinea = Math.min(desc, brutoFila);
            netoFila  = r2(brutoFila - descLinea);
        }

        subTotalBruto = r2(subTotalBruto + brutoFila);
        totalDesc = r2(totalDesc + descLinea);

        if (!grupos[codPct]) {
            grupos[codPct] = { tarifa: tarifa, label: label, base: 0, iva: 0 };
        }
        grupos[codPct].base = r2(grupos[codPct].base + netoFila);
        grupos[codPct].iva = r2(grupos[codPct].iva + r2(netoFila * (tarifa / 100)));
    });

    // Renderizar Subtotales por IVA
    let htmlSubtotales = '';
    let totalIva = 0;

    Object.values(grupos).forEach(g => {
        totalIva = r2(totalIva + g.iva);
        htmlSubtotales += `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-muted">Subtotal ${g.label}</span>
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
    const propina      = r2(inputPropina ? parseFloat(inputPropina.value || 0) : 0);

    // Total General = Subtotal (bruto) - Descuento + IVA + Propina, tal cual se ve en pantalla.
    const subtotalNeto = r2(subTotalBruto - totalDesc);
    const totalFinal    = r2(subtotalNeto + totalIva + propina);

    const modalEl = document.getElementById('modalCompra');
    if (modalEl) {
        modalEl.dataset.subtotalNeto = subtotalNeto.toFixed(2);
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
        
        const optIva = tr.querySelector('.input-iva')?.selectedOptions[0];
        const tarifa = parseFloat(optIva?.dataset.tarifa || 0);
        const codPct = optIva?.dataset.codigo || optIva?.value || _codigoIvaPorPct(tarifa);
        const cant = parseFloat(tr.querySelector('.input-cantidad')?.value || 1);
        const precio = parseFloat(tr.querySelector('.input-precio')?.value || 0);
        const descVal = parseFloat(tr.querySelector('.input-desc')?.value || 0);

        // Si la línea no fue editada, se envía el subtotal TAL CUAL lo declaró el XML del SRI
        // (mismo criterio que CMG_recalcularTotales/CMG_agregarFilaDetalle) en vez de recalcularlo
        // aquí — de lo contrario, con solo guardar sin tocar nada se sobrescribiría en la BD el
        // precio_total_sin_impuesto original con el recálculo de cantidad*precio del navegador,
        // que puede redondear distinto que el emisor del comprobante.
        let neto;
        if (tr.dataset.subtotalOriginal) {
            neto = _r2(parseFloat(tr.dataset.subtotalOriginal));
        } else {
            const bruto = _r2(cant * precio);
            neto = _r2(bruto - Math.min(_r2(descVal), bruto));
        }
        const ivaVal = _r2(neto * tarifa / 100);
        
        detalles.push({
            id: tr.querySelector('.input-id-detalle')?.value || null,
            id_producto: tr.querySelector('.input-id-producto')?.value || null,
            codigo_principal: tr.querySelector('.input-codigo')?.value || '',
            descripcion: descInput.value.trim(),
            cantidad: cant,
            precio_unitario: precio,
            descuento: descVal,
            precio_total_sin_impuesto: neto,
            impuestos: [{ codigo_impuesto:'2', codigo_porcentaje: codPct, tarifa, base_imponible: neto, valor: ivaVal }]
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
                iva: parseFloat(tr.querySelector('.input-iva')?.selectedOptions[0]?.dataset.tarifa || _ivaDefault),
                codigo_iva: tr.querySelector('.input-iva')?.selectedOptions[0]?.dataset.codigo || ''
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
                impuestos: [{ tarifa: d.iva, codigo_porcentaje: d.codigo_iva || '' }]
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
                    inputPrecio.value = parseFloat(prod.costo || 0).toFixed(DEC_PRECIO);
                    // Ya no es el precio del XML: invalida el subtotal "declarado" de la línea
                    // para que a partir de ahora se recalcule en vivo (ver CMG_recalcularTotales).
                    tr.dataset.subtotalOriginal = '';
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

    // Conteo de líneas sin vincular a un producto del catálogo (bloquean el registro)
    let pendientesTotal = 0;       // líneas aún no procesadas del todo
    let pendientesSinVincular = 0; // de esas, cuántas no tienen producto
    let checkedSinVincular = 0;    // seleccionadas sin producto → impiden procesar

    document.querySelectorAll('#mc-tbody-inventario .row-inv').forEach(tr => {
        const idDet = tr.dataset.idDetalle; // Usar ID del detalle, no del producto
        const cantCompra = parseFloat(tr.querySelector('.input-inv-cantidad').dataset.cantOriginal || tr.querySelector('.input-inv-cantidad').value || 0);

        let procesadoEnFila = 0;
        if (idDet && procesadosRestantes[idDet] > 0) {
            procesadoEnFila = Math.min(cantCompra, procesadosRestantes[idDet]);
            procesadosRestantes[idDet] -= procesadoEnFila;
        }

        const check = tr.querySelector('.input-inv-check');
        const sinVincular = !(tr.dataset.idProducto || '').trim();

        if (procesadoEnFila >= cantCompra) {
            tr.classList.add('table-success', 'bg-opacity-10');
            if (check) {
                check.disabled = true;
            }
        } else {
            tr.classList.remove('table-success', 'bg-opacity-10');
            pendientesTotal++;
            if (sinVincular) pendientesSinVincular++;
            if (check) {
                check.disabled = false;
                if (check.checked) {
                    hayParaProcesar = true;
                    if (sinVincular) checkedSinVincular++;
                }
            }
            todosProcesados = false;
        }
    });

    // Aviso de líneas sin vincular
    const aviso = document.getElementById('mc-inventario-aviso-vinculacion');
    if (aviso) {
        if (pendientesSinVincular > 0) {
            const elSin = document.getElementById('mc-inv-sin-vincular');
            const elTot = document.getElementById('mc-inv-total-lineas');
            if (elSin) elSin.textContent = pendientesSinVincular;
            if (elTot) elTot.textContent = pendientesTotal;
            aviso.classList.remove('d-none');
        } else {
            aviso.classList.add('d-none');
        }
    }

    if (btnProcesar) {
        const rows = document.querySelectorAll('#mc-tbody-inventario .row-inv').length;
        if (todosProcesados && rows > 0) {
            btnProcesar.disabled = true;
            btnProcesar.innerHTML = '<i class="bi bi-check2-all me-1"></i> Inventario Completo';
            btnProcesar.classList.replace('btn-primary', 'btn-outline-success');
            btnProcesar.title = '';
        } else if (checkedSinVincular > 0) {
            // Hay seleccionadas sin producto: procesar fallaría, se bloquea con motivo visible.
            btnProcesar.disabled = true;
            btnProcesar.innerHTML = '<i class="bi bi-link-45deg me-1"></i> Vincule ' + checkedSinVincular + ' línea(s) para continuar';
            btnProcesar.classList.replace('btn-outline-success', 'btn-primary');
            btnProcesar.title = 'Las líneas seleccionadas deben estar vinculadas a un producto del catálogo.';
        } else {
            btnProcesar.disabled = !hayParaProcesar;
            btnProcesar.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Procesar Entradas';
            btnProcesar.classList.replace('btn-outline-success', 'btn-primary');
            btnProcesar.title = '';
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
    if (!tbody) {
        window._mcSincronizando = false;
        return;
    }

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
        window._mcSincronizando = false;
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
            costo: tr.querySelector('.input-inv-costo')?.value,
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
            <tr class="row-inv ${isDisabled ? 'table-success bg-opacity-10' : ''}" data-index="${item.index}" data-id-producto="${item.id_producto || ''}" data-id-detalle="${item.id_detalle || ''}" data-codigo="${_esc(item.codigo || '')}">
                <input type="hidden" class="input-inv-id-producto" value="${item.id_producto || ''}">
                <td class="ps-3 py-2">
                    <div class="fw-medium small">
                        <div class="d-flex align-items-start">
                            ${(item.id_producto && item.id_producto != '0') ? '<i class="bi bi-tag-fill me-1 mt-1"></i>' : ''}
                            <textarea class="form-control form-control-sm border-0 bg-transparent p-0 mc-nombre-inv ${(item.id_producto && item.id_producto != '0') ? 'text-primary fw-bold' : ''}" readonly rows="1" style="resize:none; overflow:hidden; flex:1 1 auto; min-width:0; font-size:.8rem; line-height:1.25;">${(item.id_producto && item.id_producto != '0') ? _esc(item.producto_nombre || item.descripcion) : _esc(item.descripcion)}</textarea>
                            ${procesadoEnFila >= item.cantidad ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2 mt-1"><i class="bi bi-check-all me-1"></i>Enviado</span>' : ''}
                            ${(item.id_producto && item.id_producto != '0' && procesadoEnFila === 0) ? `<button type="button" class="btn btn-xs btn-link text-danger p-0 ms-1 mt-1" style="font-size:0.8rem; line-height:1;" title="Quitar vinculación (producto equivocado)" onclick="mcQuitarVinculacionInv(${item.index})"><i class="bi bi-x-circle"></i></button>` : ''}
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
                            <div class="mt-1 d-none buscador-inline-div" style="max-width: 420px;">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control form-control-xs input-buscar-inline py-0 px-1" placeholder="Buscar código o nombre..." style="font-size: 0.7rem;">
                                    <button class="btn btn-outline-secondary py-0 px-1" type="button" onclick="CMG_cancelarVinculacionInline(${item.index})"><i class="bi bi-x"></i></button>
                                </div>
                                <div class="list-group shadow dropdown-predictivo d-none lista-resultados-inline" style="max-height: 260px; overflow-y: auto;"></div>
                            </div>
                        </div>` : ''}
                </td>
                <td><select class="form-select form-select-sm border-0 bg-light input-inv-medida" style="font-size: 0.75rem;">${opcMedidaLocal}</select></td>
                <td><select class="form-select form-select-sm border-0 bg-light input-inv-bodega" style="font-size: 0.75rem;">${opcBodegaLocal}</select></td>
                <td><input type="number" class="form-control form-control-sm border-0 bg-light text-center fw-bold input-inv-cantidad" value="${(forceReset || prev.cantidad === undefined) ? pendiente : prev.cantidad}" data-cant-original="${item.cantidad}" min="0.0001" step="any" ${isDisabled ? 'readonly' : ''} style="font-size: 0.75rem;" title="Sugerido según lo comprado: ${pendiente}. Puedes ajustarlo si la unidad de conteo es distinta."></td>
                <td><input type="number" class="form-control form-control-sm border-0 bg-light text-end input-inv-costo" value="${(prev.costo === undefined || prev.costo === '') ? item.costo.toFixed(4) : prev.costo}" min="0.0001" step="any" ${isDisabled ? 'readonly' : ''} style="font-size: 0.75rem;"></td>
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

    tbody.querySelectorAll('.mc-nombre-inv').forEach(mcAutosizeTextareaInv);

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
            
            const descTa = tr.querySelector('.mc-nombre-inv');
            const desc = descTa ? descTa.value.trim() : 'Producto';

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
        } else if (e.target.id === 'tab-relacionados-tab') {
            mcCargarDocumentosRelacionados();
        } else if (e.target.id === 'tab_orden_compra') {
            mcCargarOrdenCompraTab();
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// ORDEN DE COMPRA (vincular la compra con el pedido interno que le dio origen)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Carga la pestaña "Orden de Compra": si ya está vinculada, la comparación; si no, el buscador.
 * IMPORTANTE: nunca usar contSin.innerHTML aquí — destruiría el input/dropdown del buscador
 * (#oc-tab-buscar / #oc-tab-lista) y sus listeners quedarían apuntando a nodos ya eliminados
 * del DOM, dejando el buscador roto para el resto de la sesión del modal. Los tres estados
 * (form / aviso-guardar / error) son contenedores fijos que solo se ocultan/muestran.
 */
window.mcCargarOrdenCompraTab = async function () {
    const idCompra    = document.getElementById('mcId')?.value || '';
    const contSin     = document.getElementById('oc-tab-sin-vincular');
    const contVinc    = document.getElementById('oc-tab-vinculada');
    const avisoGuardar = document.getElementById('oc-tab-aviso-guardar');
    const avisoError   = document.getElementById('oc-tab-error');
    const form          = document.getElementById('oc-tab-form');
    if (!contSin || !contVinc) return;

    avisoGuardar?.classList.add('d-none');
    avisoError?.classList.add('d-none');
    form?.classList.add('d-none');

    if (!idCompra) {
        contVinc.classList.add('d-none');
        contSin.classList.remove('d-none');
        avisoGuardar?.classList.remove('d-none');
        return;
    }

    try {
        const res  = await fetch(`${window.CMG_urlBase}/getComparacionOrdenAjax?id=${idCompra}`);
        const data = await res.json();
        if (!data.ok) throw new Error(data.mensaje || 'Error');

        if (data.vinculada) {
            contSin.classList.add('d-none');
            contVinc.classList.remove('d-none');
            mcRenderOrdenComparacion(data);
        } else {
            contVinc.classList.add('d-none');
            contSin.classList.remove('d-none');
            form?.classList.remove('d-none');
            await mcCargarOrdenesAbiertas(idCompra);
        }
    } catch (e) {
        contVinc.classList.add('d-none');
        contSin.classList.remove('d-none');
        if (avisoError) {
            avisoError.textContent = 'Error al cargar: ' + e.message;
            avisoError.classList.remove('d-none');
        }
    }
};

/** Trae del servidor las órdenes abiertas del proveedor de la compra actual (una vez por carga de pestaña). */
window._ocTabOrdenesAbiertas = [];
window.mcCargarOrdenesAbiertas = async function (idCompra) {
    const idProveedor  = document.getElementById('mcIdProveedor')?.value || '';
    const input        = document.getElementById('oc-tab-buscar');
    const hiddenId      = document.getElementById('oc-tab-id-orden');
    const btn           = document.getElementById('oc-tab-btn-vincular');
    const avisoVacio    = document.getElementById('oc-tab-sin-abiertas');
    const lista          = document.getElementById('oc-tab-lista');
    if (!input) return;

    window._ocTabOrdenesAbiertas = [];
    if (hiddenId) hiddenId.value = '';
    if (btn) btn.disabled = true;
    if (avisoVacio) avisoVacio.classList.add('d-none');
    lista?.classList.add('d-none');

    if (!idProveedor) {
        input.value = '';
        input.disabled = true;
        input.placeholder = 'Seleccione un proveedor primero...';
        return;
    }
    input.disabled = false;
    input.placeholder = 'Buscar por número de orden...';
    input.value = '';

    try {
        const res  = await fetch(`${window.CMG_urlBase}/buscarOrdenesCompraAjax?id_proveedor=${idProveedor}&id_compra=${idCompra}`);
        const data = await res.json();
        if (!data.ok) throw new Error(data.mensaje || 'Error');

        window._ocTabOrdenesAbiertas = data.data || [];
        if (!window._ocTabOrdenesAbiertas.length && avisoVacio) {
            avisoVacio.classList.remove('d-none');
        }
    } catch (e) {
        if (avisoVacio) {
            avisoVacio.textContent = 'Error al cargar las órdenes de este proveedor.';
            avisoVacio.classList.remove('d-none');
        }
    }
};

/** Etiqueta legible de una orden para el buscador: número — fecha — total (estado). */
function _ocTabEtiqueta(o) {
    const fecha = o.fecha_orden ? String(o.fecha_orden).slice(0, 10).split('-').reverse().join('/') : '';
    const estado = o.estado === 'parcial' ? 'recibido parcial' : o.estado;
    return `${o.numero_orden} — ${fecha} — $${parseFloat(o.total || 0).toFixed(2)} (${estado})`;
}

/** Pinta el dropdown del buscador con las órdenes que coinciden con lo tecleado (filtro en cliente). */
function _ocTabRenderLista(filtro) {
    const lista = document.getElementById('oc-tab-lista');
    if (!lista) return;
    const q = (filtro || '').trim().toLowerCase();
    const coincidencias = window._ocTabOrdenesAbiertas.filter(o => !q || String(o.numero_orden).toLowerCase().includes(q));

    if (!coincidencias.length) {
        lista.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias.</div>';
        lista.classList.remove('d-none');
        return;
    }
    lista.innerHTML = '';
    coincidencias.forEach(o => {
        const item = document.createElement('a');
        item.href = '#';
        item.className = 'list-group-item list-group-item-action py-1 px-2 small';
        item.textContent = _ocTabEtiqueta(o);
        item.addEventListener('mousedown', (e) => {
            e.preventDefault();
            document.getElementById('oc-tab-buscar').value = _ocTabEtiqueta(o);
            document.getElementById('oc-tab-id-orden').value = o.id;
            document.getElementById('oc-tab-btn-vincular').disabled = false;
            lista.classList.add('d-none');
        });
        lista.appendChild(item);
    });
    lista.classList.remove('d-none');
}

document.getElementById('oc-tab-buscar')?.addEventListener('input', function () {
    // Si el usuario edita el texto de una selección ya hecha, se invalida hasta elegir de nuevo.
    document.getElementById('oc-tab-id-orden').value = '';
    document.getElementById('oc-tab-btn-vincular').disabled = true;
    _ocTabRenderLista(this.value);
});
document.getElementById('oc-tab-buscar')?.addEventListener('focus', function () {
    _ocTabRenderLista(this.value);
});
document.getElementById('oc-tab-buscar')?.addEventListener('keydown', function (e) {
    if ((e.key === 'Backspace' || e.key === 'Delete') && document.getElementById('oc-tab-id-orden')?.value) {
        e.preventDefault();
        this.value = '';
        document.getElementById('oc-tab-id-orden').value = '';
        document.getElementById('oc-tab-btn-vincular').disabled = true;
        _ocTabRenderLista('');
    }
});
document.addEventListener('click', function (e) {
    const lista = document.getElementById('oc-tab-lista');
    if (lista && !lista.contains(e.target) && e.target.id !== 'oc-tab-buscar') {
        lista.classList.add('d-none');
    }
});

window.mcVincularOrdenCompra = async function () {
    const idCompra      = document.getElementById('mcId')?.value || '';
    const idOrdenCompra = document.getElementById('oc-tab-id-orden')?.value || '';
    if (!idCompra || !idOrdenCompra) return;

    const btn = document.getElementById('oc-tab-btn-vincular');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    try {
        const fd = new FormData();
        fd.append('id', idCompra);
        fd.append('id_orden_compra', idOrdenCompra);
        const res  = await fetch(`${window.CMG_urlBase}/vincularOrdenCompraAjax`, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Vinculada', text: data.mensaje, timer: 1600, showConfirmButton: false });
            mcCargarOrdenCompraTab();
        } else {
            Swal.fire('Error', data.mensaje || 'No se pudo vincular la orden de compra.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Error de conexión: ' + e.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-link-45deg me-1"></i> Vincular'; }
    }
};

window.mcDesvincularOrdenCompra = async function () {
    const idCompra = document.getElementById('mcId')?.value || '';
    if (!idCompra) return;

    const confirm = await Swal.fire({
        icon: 'warning',
        title: '¿Desvincular orden de compra?',
        text: 'La orden volverá a estado "Aprobado" y podrá vincularse a otra compra.',
        showCancelButton: true,
        confirmButtonText: 'Sí, desvincular',
        cancelButtonText: 'Cancelar',
    });
    if (!confirm.isConfirmed) return;

    try {
        const fd = new FormData();
        fd.append('id', idCompra);
        const res  = await fetch(`${window.CMG_urlBase}/desvincularOrdenCompraAjax`, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            mcCargarOrdenCompraTab();
        } else {
            Swal.fire('Error', data.mensaje || 'No se pudo desvincular.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Error de conexión: ' + e.message, 'error');
    }
};

/** Cierra manualmente una orden en Recibido Parcial cuando el proveedor no va a entregar el saldo. */
window.mcCerrarOrdenCompra = async function () {
    const idCompra = document.getElementById('mcId')?.value || '';
    if (!idCompra) return;

    const confirm = await Swal.fire({
        icon: 'question',
        title: '¿Cerrar esta orden como recibida?',
        text: 'Úselo cuando el proveedor ya no va a entregar el saldo pendiente. La orden quedará en Recibido, con el faltante registrado como no entregado.',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar',
        cancelButtonText: 'Cancelar',
    });
    if (!confirm.isConfirmed) return;

    try {
        const fd = new FormData();
        fd.append('id', idCompra);
        const res  = await fetch(`${window.CMG_urlBase}/cerrarOrdenCompraAjax`, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            mcCargarOrdenCompraTab();
        } else {
            Swal.fire('Error', data.mensaje || 'No se pudo cerrar la orden.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Error de conexión: ' + e.message, 'error');
    }
};

/** Pinta la cabecera de la orden vinculada + la tabla comparativa pedido vs. facturado. */
function mcRenderOrdenComparacion(data) {
    const orden = data.orden || {};
    const fecha = orden.fecha_orden ? String(orden.fecha_orden).slice(0, 10).split('-').reverse().join('/') : '—';
    document.getElementById('oc-tab-numero').textContent = orden.numero_orden || '—';
    document.getElementById('oc-tab-fecha').textContent  = fecha;

    const estadoBadge = { aprobado: 'info', parcial: 'warning', recibido: 'success' }[orden.estado] || 'info';
    const estadoEl = document.getElementById('oc-tab-estado');
    estadoEl.className = `badge bg-${estadoBadge} bg-opacity-10 text-${estadoBadge} border border-${estadoBadge} border-opacity-25 ms-2`;
    let estadoTexto = (orden.estado || '').charAt(0).toUpperCase() + (orden.estado || '').slice(1);
    if (orden.estado === 'parcial') estadoTexto = 'Recibido parcial';
    if (orden.estado === 'recibido' && orden.cierre_forzado) estadoTexto += ' (cierre manual)';
    estadoEl.textContent = estadoTexto;

    // "Cerrar orden" solo tiene sentido mientras falte saldo por recibir.
    document.getElementById('oc-tab-btn-cerrar')?.classList.toggle('d-none', orden.estado !== 'parcial');

    // Historial de compras vinculadas a esta orden (entregas parciales del proveedor).
    const contCompras = document.getElementById('oc-tab-compras-vinculadas');
    const compras = data.compras_vinculadas || [];
    if (contCompras) {
        if (compras.length <= 1) {
            contCompras.innerHTML = '';
        } else {
            const items = compras.map(c => {
                const f = c.fecha_emision ? String(c.fecha_emision).slice(0, 10).split('-').reverse().join('/') : '—';
                const marca = c.es_esta ? ' <strong>(esta compra)</strong>' : '';
                return `<li>${_esc(c.numero)} — ${f} — $${parseFloat(c.importe_total || 0).toFixed(2)}${marca}</li>`;
            }).join('');
            contCompras.innerHTML = `<i class="bi bi-clock-history me-1"></i>Esta orden se está recibiendo en ${compras.length} entregas:<ul class="mb-0 mt-1">${items}</ul>`;
        }
    }

    const badgeMap = {
        ok:         { cls: 'success', label: 'OK' },
        diferencia: { cls: 'warning', label: 'Diferencia' },
        pendiente:  { cls: 'secondary', label: 'Pendiente' },
        extra:      { cls: 'info',    label: 'No pedido' },
    };

    const tbody = document.getElementById('oc-tab-tbody');
    const filas = data.filas || [];
    if (!filas.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No hay líneas con producto del catálogo para comparar.</td></tr>';
    } else {
        tbody.innerHTML = filas.map(f => {
            const b = badgeMap[f.estado] || badgeMap.ok;
            const rowCls = f.estado === 'ok' ? '' : ` table-${b.cls}`;
            return `<tr class="${rowCls}">
                <td class="ps-3">${_esc(f.descripcion)}</td>
                <td class="text-center">${parseFloat(f.cantidad_pedida).toFixed(2)}</td>
                <td class="text-center">${parseFloat(f.cantidad_facturada).toFixed(2)}</td>
                <td class="text-end">${parseFloat(f.precio_pedido).toFixed(2)}</td>
                <td class="text-end">${parseFloat(f.precio_facturado).toFixed(2)}</td>
                <td class="text-center"><span class="badge bg-${b.cls} bg-opacity-10 text-${b.cls} border border-${b.cls} border-opacity-25">${b.label}</span></td>
            </tr>`;
        }).join('');
    }

    const sinProdOrden  = data.sin_producto_orden  || [];
    const sinProdCompra = data.sin_producto_compra || [];
    const cont = document.getElementById('oc-tab-sin-producto');
    const lista = document.getElementById('oc-tab-sin-producto-lista');
    if ((sinProdOrden.length || sinProdCompra.length) && cont && lista) {
        cont.classList.remove('d-none');
        lista.innerHTML = [
            ...sinProdOrden.map(l => `<li>Pedido: ${_esc(l.descripcion)} (cant. ${parseFloat(l.cantidad).toFixed(2)})</li>`),
            ...sinProdCompra.map(l => `<li>Facturado: ${_esc(l.descripcion)} (cant. ${parseFloat(l.cantidad).toFixed(2)})</li>`),
        ].join('');
    } else if (cont) {
        cont.classList.add('d-none');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DOCUMENTOS RELACIONADOS (factura ↔ notas de crédito)
// ─────────────────────────────────────────────────────────────────────────────

/** Muestra u oculta la pestaña de documentos relacionados según el tipo. */
function mcActualizarPestanaRelacionados(d) {
    const li    = document.getElementById('tab-relacionados-li');
    const label = document.getElementById('tab-relacionados-label');
    if (!li) return;

    const esNota  = String(d.tipo_comprobante || '') === '04';
    const tieneNc = parseFloat(d.total_nc || 0) > 0.001;

    if (esNota) {
        if (label) label.textContent = 'Factura Relacionada';
        li.classList.remove('d-none');
    } else if (tieneNc) {
        if (label) label.textContent = 'Notas de Crédito';
        li.classList.remove('d-none');
    } else {
        li.classList.add('d-none');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DETALLE DE REEMBOLSO (factura recibida, codDoc=01 con codDocReembolso=41)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Bloquea/desbloquea el selector de Sustento Tributario. Una Factura de
 * Reembolso recibida siempre debe declararse con sustento código 08 (Tabla 5
 * SRI); se deja fijo para que el usuario no pueda cambiarlo por error al
 * editar manualmente la compra (el servidor también lo re-fuerza al guardar).
 */
function mcAplicarBloqueoSustento(esReembolso) {
    const sel  = document.getElementById('mcSustento');
    const help = document.getElementById('mcSustentoReembolsoHelp');
    if (!sel) return;
    if (esReembolso) {
        sel.disabled = true;
        sel.classList.add('mc-lock-reembolso');
        help?.classList.remove('d-none');
    } else if (sel.classList.contains('mc-lock-reembolso')) {
        sel.disabled = false;
        sel.classList.remove('mc-lock-reembolso');
        help?.classList.add('d-none');
    }
}

/** Muestra u oculta la pestaña "Detalle de Reembolso" y llena su tabla. */
function mcActualizarPestanaReembolso(d) {
    const li = document.getElementById('tab-reembolso-li');
    if (!li) return;

    const terceros = d.terceros_reembolso || [];
    const esReembolso = String(d.cod_doc_reembolso || '') === '41';
    mcAplicarBloqueoSustento(esReembolso);

    if (!esReembolso && !terceros.length) {
        li.classList.add('d-none');
        return;
    }
    li.classList.remove('d-none');

    const fmt = (v) => (parseFloat(v) || 0).toFixed(2);
    document.getElementById('mc-reembolso-total-base').textContent = fmt(d.total_base_imponible_reembolso);
    document.getElementById('mc-reembolso-total-impuesto').textContent = fmt(d.total_impuesto_reembolso);
    document.getElementById('mc-reembolso-total-comprobantes').textContent = fmt(d.total_comprobantes_reembolso);

    const tbody = document.getElementById('mc-reembolso-tbody');
    if (!tbody) return;
    if (!terceros.length) {
        tbody.innerHTML = '<tr id="mc-reembolso-vacio"><td colspan="6" class="text-center text-muted small py-3">Sin terceros reembolsados.</td></tr>';
        return;
    }

    tbody.innerHTML = terceros.map(t => {
        const numero = `${t.estab_doc_reembolso || ''}-${t.pto_emi_doc_reembolso || ''}-${t.secuencial_doc_reembolso || ''}`;
        const fecha = t.fecha_emision_doc_reembolso ? String(t.fecha_emision_doc_reembolso).slice(0, 10) : '';
        return `<tr>
            <td class="ps-2">${t.identificacion_proveedor_reembolso || ''}</td>
            <td>${t.cod_doc_reembolso || ''}</td>
            <td>${numero}</td>
            <td>${fecha}</td>
            <td class="text-end">${fmt(t.base_imponible_total)}</td>
            <td class="text-end pe-2">${fmt(t.impuesto_total)}</td>
        </tr>`;
    }).join('');
}

window.mcCargarDocumentosRelacionados = async function () {
    const idCompra = document.getElementById('mcId').value || document.getElementById('modalCompra').dataset.id;
    const cont = document.getElementById('mc-relacionados-cont');
    const info = document.getElementById('mc-relacionados-info');
    if (!cont || !idCompra) return;

    cont.innerHTML = '<div class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-2"></i>Cargando documentos relacionados...</div>';
    try {
        const res  = await fetch(`${window.CMG_urlBase}/getDocumentosRelacionadosAjax?id=${idCompra}`);
        const data = await res.json();
        if (!data.ok) throw new Error(data.mensaje || 'Error');

        const docs      = data.documentos || [];
        const esFactura = data.relacion === 'factura';

        if (info) {
            info.textContent = esFactura
                ? 'Factura de compra que esta nota de crédito modifica:'
                : `Notas de crédito que modifican esta compra (${docs.length}):`;
        }

        if (!docs.length) {
            cont.innerHTML = `<div class="text-center py-4 text-muted"><i class="bi bi-inbox d-block fs-3 mb-2"></i>${esFactura ? 'No se encontró la factura relacionada.' : 'No hay notas de crédito para esta compra.'}</div>`;
            return;
        }

        cont.innerHTML = docs.map(doc => mcRenderDocRelacionado(doc)).join('');
    } catch (e) {
        cont.innerHTML = `<div class="text-center py-4 text-danger">Error al cargar: ${_esc(e.message)}</div>`;
    }
};

/** Renderiza una tarjeta de documento relacionado con sus líneas de detalle. */
function mcRenderDocRelacionado(doc) {
    const fecha = doc.fecha_emision ? String(doc.fecha_emision).slice(0, 10).split('-').reverse().join('/') : '—';
    const total = parseFloat(doc.importe_total || 0).toFixed(2);
    const nombre = _esc(doc.tipo_comprobante_nombre || doc.tipo_comprobante || 'Documento');

    const filas = (doc.detalles || []).map(det => {
        const sub = parseFloat(det.precio_total_sin_impuesto || 0);
        return `<tr>
            <td class="ps-2">${_esc(det.codigo_principal || det.producto_codigo || '')}</td>
            <td>${_esc(det.descripcion || det.producto_nombre || '')}</td>
            <td class="text-center">${_fmtExacto(det.cantidad || 0)}</td>
            <td class="text-end">${_fmtExacto(det.precio_unitario || 0)}</td>
            <td class="text-end pe-2">${sub.toFixed(2)}</td>
        </tr>`;
    }).join('') || '<tr><td colspan="5" class="text-center text-muted py-2">Sin líneas de detalle.</td></tr>';

    return `
    <div class="border rounded-3 overflow-hidden bg-white shadow-sm mb-3">
        <div class="d-flex justify-content-between align-items-center bg-light border-bottom px-3 py-2">
            <div>
                <span class="fw-bold"><i class="bi bi-file-earmark-text me-1 text-primary"></i>${nombre}</span>
                <code class="text-secondary ms-2">${_esc(doc.numero || '')}</code>
                <span class="text-muted small ms-2"><i class="bi bi-calendar-event me-1"></i>${fecha}</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold">$ ${total}</span>
                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" onclick="mcAbrirRelacionado(${parseInt(doc.id, 10)})" title="Abrir este documento">
                    <i class="bi bi-box-arrow-up-right"></i>
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0" style="font-size: 0.78rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-2" style="width:16%;">Código</th>
                        <th>Descripción</th>
                        <th class="text-center" style="width:12%;">Cant.</th>
                        <th class="text-end" style="width:15%;">P. Unit.</th>
                        <th class="text-end pe-2" style="width:15%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>
        </div>
    </div>`;
}

/** Abre un documento relacionado dentro del mismo modal. */
window.mcAbrirRelacionado = function (id) {
    if (!id) return;
    fetch(`${window.CMG_urlBase}/getCompraAjax?id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { Swal.fire('Error', res.mensaje || 'No se pudo abrir el documento.', 'error'); return; }
            CMG_poblarModal(res.data);
            const tabDetalle = document.getElementById('tab_compra');
            if (tabDetalle) bootstrap.Tab.getOrCreateInstance(tabDetalle).show();
        })
        .catch(e => Swal.fire('Error', e.message, 'error'));
};

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
        document.getElementById('pagoCardNc')?.classList.add('d-none');
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

        // Notas de crédito que modifican esta compra (restan del saldo).
        const totalNc = parseFloat(compra.total_nc || 0);

        const saldo = Math.max(0, totalFactura - totalAbonado - totalRetenido - totalNc);

        // Actualizar paneles superiores
        document.getElementById('pagoTotalCompra').textContent = totalFactura.toFixed(2);
        if (document.getElementById('pagoTotalRetencion')) document.getElementById('pagoTotalRetencion').textContent = totalRetenido.toFixed(2);
        document.getElementById('pagoTotalAbonado').textContent = totalAbonado.toFixed(2);
        document.getElementById('pagoSaldoPendiente').textContent = saldo.toFixed(2);

        // Tarjeta de Notas de Crédito (solo si hay)
        const cardNc = document.getElementById('pagoCardNc');
        if (cardNc) {
            if (totalNc > 0.001) {
                const lblNc = document.getElementById('pagoTotalNc');
                if (lblNc) lblNc.textContent = totalNc.toFixed(2);
                cardNc.classList.remove('d-none');
            } else {
                cardNc.classList.add('d-none');
            }
        }

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
            inputFC.value = CMG_fechaLocal();
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

// Exportar (descargar) Excel de la compra
window.mcExportarExcel = function () {
    const id = document.getElementById('mcId')?.value || document.getElementById('modalCompra')?.dataset.id;
    if (!id) {
        Swal.fire('Atención', 'Guarde la compra primero para generar el Excel.', 'warning');
        return;
    }
    const a = document.createElement('a');
    a.href = `${window.CMG_urlBase}/exportar-excel-ajax?id=${id}`;
    a.download = '';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};
