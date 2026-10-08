/**
 * Estados Financieros — "Cuadre con módulos": la Comprobación con Contabilidad vista desde
 * la contabilidad. Una fila por cuenta bancaria y por cada módulo con saldo propio (Cuentas
 * por Cobrar, Cuentas por Pagar, Inventarios), con el saldo según el módulo y según sus
 * cuentas contables. "Ver detalle" abre la pantalla común documento por documento
 * (public/js/comprobacion_contable.js). Servidor: App\Services\modulos\CuadreModulosService.
 *
 * Usa `urlBase` (ruta de Estados Financieros) definida en la vista.
 */
(function () {
    'use strict';

    // Cómo se llama el saldo de cada módulo y qué explica la nota del detalle.
    const MODULOS = {
        banco: {
            etiquetaCuenta: 'cuenta del banco',
            etiqueta: 'Según Ingresos/Egresos',
            nota: '"Según Ingresos/Egresos" es el saldo en libros: saldo inicial de Saldos Iniciales más todos los cobros, pagos y traspasos de la cuenta, con los cheques desde que se emiten.',
            sinCuenta: 'se asigna en Formas de cobro y pago',
        },
        caja: {
            etiquetaCuenta: 'cuenta de caja',
            etiqueta: 'Según Ingresos/Egresos',
            nota: '"Según Ingresos/Egresos" es el saldo de la caja: saldo inicial de Saldos Iniciales más los cobros, menos los pagos, más los traspasos recibidos y menos los enviados (el mismo saldo que muestra Traspasos).',
            tipos: { traspaso: 'Traspaso' },
            sinCuenta: 'se asigna en Formas de cobro y pago',
        },
        anticipos_clientes: {
            etiquetaCuenta: 'cuenta de anticipos',
            etiqueta: 'Según Anticipos',
            nota: '"Según Anticipos" es lo que se debe a los clientes por anticipos: saldo inicial más los anticipos recibidos (ingresos con una opción de anticipo) menos los aplicados a cobros con la forma Anticipo. Es el total de la pestaña Anticipos de las fichas de cliente.',
            sinCuenta: 'se asigna a la opción de anticipo en Opciones de ingreso/egreso y a la forma de pago Anticipo',
        },
        anticipos_proveedores: {
            etiquetaCuenta: 'cuenta de anticipos',
            etiqueta: 'Según Anticipos',
            nota: '"Según Anticipos" es lo que los proveedores deben por anticipos entregados: saldo inicial más los anticipos entregados (egresos con una opción de anticipo) menos los aplicados a pagos con la forma Anticipo. Es el total de la pestaña Anticipos de las fichas de proveedor.',
            sinCuenta: 'se asigna a la opción de anticipo en Opciones de ingreso/egreso y a la forma de pago Anticipo',
        },
        tarjetas: {
            etiquetaCuenta: 'cuenta puente de la tarjeta',
            etiqueta: 'Según Ingresos/Conciliación',
            nota: 'Lo cobrado con la tarjeta (Ingresos) que la procesadora todavía no liquida: saldo inicial más los cobros, menos los pagos y traspasos, menos lo cruzado en las conciliaciones de tarjetas CERRADAS (en su fecha de conciliación). Una conciliación en borrador no resta: su asiento nace al cerrarla.',
            sinCuenta: 'se asigna en Formas de cobro y pago',
        },
        activos_costo: {
            etiquetaCuenta: 'cuenta del activo',
            etiqueta: 'Según Activos Fijos',
            nota: 'El valor de adquisición de los activos registrados, a su fecha de adquisición. El alta manual tiene su propio asiento; el activo que viene de una compra se contabiliza con el asiento de esa compra.',
            tipos: { activo_fijo: 'Activo', compra: 'Compra' },
            sinCuenta: 'se asigna en cada activo fijo',
        },
        activos_depreciacion: {
            etiquetaCuenta: 'cuenta de depreciación acumulada',
            etiqueta: 'Según Activos Fijos',
            nota: 'Lo depreciado en cada lote mensual generado en el módulo, al último día de su mes. La depreciación anterior al sistema solo existe en contabilidad y aparece en el saldo inicial.',
            tipos: { depreciacion: '' },
            sinCuenta: 'se asigna en cada activo fijo',
        },
        cxc: {
            etiquetaCuenta: 'cuenta por cobrar',
            etiqueta: 'Según Cartera',
            nota: '"Según Cartera" es la suma de los saldos de todas las facturas, recibos y saldos iniciales (incluidos los ya cobrados), con los cobros, retenciones y notas de crédito hasta cada fecha.',
        },
        cxp: {
            etiquetaCuenta: 'cuenta por pagar',
            etiqueta: 'Según Cartera',
            nota: '"Según Cartera" es la suma de los saldos de todas las compras, liquidaciones, facturas del exterior y saldos iniciales (incluidos los ya pagados), con los pagos, retenciones y notas de crédito hasta cada fecha.',
            tipos: { compra: 'Comprobante de compra' },
        },
        inventario: {
            etiquetaCuenta: 'cuenta de inventario',
            etiqueta: 'Según Kardex',
            nota: '"Según Kardex" es el valor del inventario por movimientos, de todas las bodegas: cada entrada suma su costo y cada salida resta el costo con que salió. No es el valor de la Valorización (stock por último costo).',
            tipos: {
                consignacion_venta: 'Consignación', retorno_cv: 'Retorno de consignación',
                FACTURACION_CV: 'Facturación de consignación', cambio_producto_cv: 'Cambio de producto',
                ajuste_manual: 'Ajuste manual', ajuste_inventario: 'Ajuste de inventario', carga_inventario: 'Carga de inventario', migracion: 'Migración',
                taller_orden: 'Orden de taller',
            },
        },
    };

    let filas = [];

    const el = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const dinero = v => '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fecha = v => { const p = String(v || '').substring(0, 10).split('-'); return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : ''; };
    const celdaDif = v => {
        const n = Number(v || 0);
        return `<span class="fw-bold ${Math.abs(n) < 0.005 ? 'text-success' : 'text-danger'}">${dinero(n)}</span>`;
    };

    window.EF_cuadreModulos = function () {
        el('ef-cuadre-desde').value = el('fecha_inicio')?.value || '';
        el('ef-cuadre-hasta').value = el('fecha_fin')?.value || '';
        el('ef-cuadre-contenido').innerHTML = '<div style="min-height:160px;"></div>';
        bootstrap.Modal.getOrCreateInstance(el('modalCuadreModulos')).show();
        EF_cuadreModulosCargar();
    };

    window.EF_cuadreModulosCargar = async function () {
        const desde = el('ef-cuadre-desde').value;
        const hasta = el('ef-cuadre-hasta').value;
        const cont = el('ef-cuadre-contenido');
        el('ef-cuadre-loader').classList.remove('d-none');
        try {
            const resp = await fetch(`${urlBase}/cuadreModulosAjax?${new URLSearchParams({ fecha_inicio: desde, fecha_fin: hasta })}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!(resp.headers.get('content-type') || '').includes('application/json')) throw new Error(`HTTP ${resp.status}`);
            const json = await resp.json();
            if (!json.ok) {
                cont.innerHTML = `<div class="alert alert-danger small mb-0">${esc(json.error || 'No se pudo hacer el cuadre.')}</div>`;
                return;
            }
            filas = json.data.filas || [];
            cont.innerHTML = render(json.data);
        } catch (e) {
            console.error(e);
            cont.innerHTML = '<div class="alert alert-danger small mb-0">Error de red o del servidor.</div>';
        } finally {
            el('ef-cuadre-loader').classList.add('d-none');
        }
    };

    function render(d) {
        const cuadran = filas.filter(f => !f.sin_cuentas && Math.abs(f.fin.diferencia) < 0.005).length;
        const conCuentas = filas.filter(f => !f.sin_cuentas).length;
        const cuerpo = filas.map((f, i) => {
            if (f.sin_cuentas) {
                return `<tr>
                    <td class="ps-3 fw-medium">${esc(f.nombre)}</td>
                    <td colspan="5" class="text-muted small">Sin cuenta contable configurada: no hay contra qué comparar
                        (${(MODULOS[f.modulo] || {}).sinCuenta || 'se asigna en Configuración Contable'}).</td>
                    <td></td></tr>`;
            }
            const ok = Math.abs(f.fin.diferencia) < 0.005;
            const cuentas = (f.cuentas || []).map(c => `${esc(c.codigo)} ${esc(c.nombre)}`).join('<br>');
            return `<tr>
                <td class="ps-3 fw-medium">${esc(f.nombre)}</td>
                <td class="small text-muted">${cuentas}</td>
                <td class="text-end text-nowrap">${dinero(f.fin.libros)}</td>
                <td class="text-end text-nowrap">${dinero(f.fin.contable)}</td>
                <td class="text-end text-nowrap">${celdaDif(f.fin.diferencia)}
                    ${Math.abs(f.inicio.diferencia) >= 0.005 ? `<div class="text-muted" style="font-size:.7rem;" title="Diferencia que ya traía al inicio del período">al inicio ${dinero(f.inicio.diferencia)}</div>` : ''}</td>
                <td class="text-center">${ok
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle-fill"></i> Cuadra</span>'
                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">No cuadra</span>'}</td>
                <td class="text-end pe-3"><button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="EF_cuadreDetalle(${i})">Ver detalle</button></td>
            </tr>`;
        }).join('');

        return `
            <div class="p-2 border rounded-3 bg-light mb-2 small d-flex flex-wrap gap-3">
                <div><span class="text-muted">Saldos al:</span> <span class="fw-bold">${fecha(d.fecha_fin)}</span></div>
                <div><span class="text-muted">Cuadran:</span> <span class="fw-bold">${cuadran} de ${conCuentas}</span></div>
            </div>
            <div class="card ef-cuadre-card border-0 shadow-sm rounded-3">
                <div class="table-responsive">
                <table class="table table-hover table-sm small mb-0 align-middle">
                    <thead><tr><th class="ps-3">Módulo</th><th>Cuentas contables</th><th class="text-end">Según el módulo</th>
                        <th class="text-end">Según Contabilidad</th><th class="text-end">Diferencia</th><th class="text-center">Estado</th><th class="pe-3"></th></tr></thead>
                    <tbody>${cuerpo || '<tr><td colspan="7" class="text-center text-muted py-3">No hay módulos para comparar.</td></tr>'}</tbody>
                </table>
                </div>
            </div>
            <div class="form-text mt-2">Saldos al final del período (Hasta). "Ver detalle" muestra, documento por documento,
                dónde se descuadra cada módulo dentro del período Desde–Hasta.</div>`;
    }

    window.EF_cuadreDetalle = function (i) {
        const f = filas[i];
        if (!f) return;
        const m = MODULOS[f.modulo] || {};
        const params = new URLSearchParams({ modulo: f.modulo });
        if (f.id_forma) params.set('forma', f.id_forma);
        CMG_comprobacionContable.abrir({
            url: `${urlBase}/cuadreModuloDetalleAjax?${params}`,
            modulo: f.nombre,
            etiquetaLibros: m.etiqueta,
            etiquetaCuenta: m.etiquetaCuenta,
            nota: m.nota,
            tipos: m.tipos,
            desde: el('ef-cuadre-desde').value,
            hasta: el('ef-cuadre-hasta').value,
        });
    };
})();
