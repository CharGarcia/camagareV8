/**
 * Aviso de asientos contables pendientes de generar.
 *
 * Se usa en los módulos que muestran contabilidad ya consolidada (Libro Diario / Asientos,
 * Estados Financieros y Mayores). Al abrir el módulo consulta cuántos documentos operativos
 * están SIN asiento generado y, si hay, pregunta al usuario si desea generarlos ahora o
 * continuar sin generar. NO genera nada por su cuenta: la generación solo ocurre si el
 * usuario la acepta explícitamente.
 *
 * Endpoints esperados en el controlador del módulo (mismo urlBase):
 *   GET {urlBase}/contarPendientesAjax  → { ok: true, pendientes: <int> }
 *   GET {urlBase}/sincronizarAjax       → { success: true, generados: <int>, warnings: [<string>] }
 *
 * Uso:
 *   CMG_verificarAsientosPendientes({ urlBase: '<...>', onGenerado: () => { ... } });
 */
(function () {
    'use strict';

    const escapeHtml = (s) => String(s).replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function generar(urlBase, onGenerado) {
        if (window.Swal) {
            Swal.fire({
                title: 'Generando asientos…',
                html: 'Espere un momento; puede tardar si hay muchos documentos.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
        }

        return fetch(`${urlBase}/sincronizarAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                if (!json || json.success === false) {
                    const msg = (json && json.error) ? json.error : 'No se pudieron generar los asientos.';
                    if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    return;
                }

                // 'resumen' es un único texto corto agrupado por módulo (ej. "Hay 20 en Facturas de
                // Venta, 3 en Facturas de Compra. Revise la configuración contable."). El detalle
                // motivo-por-motivo/documento-por-documento sigue viajando en 'warnings' pero ya NO
                // se pinta aquí como lista larga (queda en el log del servidor para soporte).
                const resumen = (typeof json.resumen === 'string' && json.resumen.trim() !== '') ? json.resumen.trim() : null;
                const warns = Array.isArray(json.warnings) ? json.warnings : [];
                const generados = json.generados || 0;
                const hayPendientes = !!resumen || warns.length > 0;

                let html = '';
                if (generados > 0) {
                    html += `<div class="mb-2"><i class="bi bi-check-circle-fill text-success me-1"></i> Se generaron <strong>${generados}</strong> asiento(s) contable(s).</div>`;
                }
                if (resumen) {
                    html += `<div class="text-start small mb-2"><i class="bi bi-exclamation-triangle text-warning me-1"></i> ${escapeHtml(resumen)}</div>`;
                }
                if (warns.length) {
                    // Avisos sin documentos asociados (ej. configuración incompleta detectada de forma
                    // proactiva, o fallas de migración): siguen siendo pocos y cortos, se listan aparte.
                    html += `<div class="text-start small"><strong>Otros avisos:</strong>`
                        + `<ul class="mb-0 mt-1 small">${warns.map(w => `<li class="mb-1">${escapeHtml(w)}</li>`).join('')}</ul></div>`;
                }
                if (!html) html = 'No quedaron asientos por generar.';

                if (window.Swal) {
                    Swal.fire({
                        icon: hayPendientes ? 'warning' : 'success',
                        title: hayPendientes ? 'Generación completada con avisos' : 'Asientos generados',
                        html: html,
                        confirmButtonText: 'Aceptar',
                    }).then(() => { if (typeof onGenerado === 'function') onGenerado(); });
                } else if (typeof onGenerado === 'function') {
                    onGenerado();
                }
            })
            .catch(() => {
                if (window.Swal) Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo completar la generación de asientos.' });
            });
    }

    /**
     * @param {Object} opts
     * @param {string} opts.urlBase      Base del módulo (p. ej. ".../modulos/asientos_contables").
     * @param {Function} [opts.onGenerado] Callback tras generar con éxito (p. ej. refrescar la tabla/reporte).
     */
    window.CMG_verificarAsientosPendientes = function (opts) {
        opts = opts || {};
        const urlBase = opts.urlBase;
        const onGenerado = opts.onGenerado;
        if (!urlBase) return;

        fetch(`${urlBase}/contarPendientesAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                if (!json || !json.ok) return;
                const n = parseInt(json.pendientes, 10) || 0;
                if (n < 1) return;

                if (!window.Swal) {
                    // Sin SweetAlert: confirm nativo como respaldo.
                    if (window.confirm(`Hay ${n} documento(s) sin asiento contable generado. ¿Desea generarlos ahora?`)) {
                        generar(urlBase, onGenerado);
                    }
                    return;
                }

                Swal.fire({
                    icon: 'question',
                    title: 'Asientos pendientes',
                    html: `Hay <strong>${n}</strong> documento(s) sin asiento contable generado.<br>¿Desea generarlos ahora?`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="bi bi-gear-fill me-1"></i> Generar ahora',
                    cancelButtonText: 'Continuar sin generar',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false,
                    reverseButtons: true,
                }).then(res => {
                    if (res.isConfirmed) generar(urlBase, onGenerado);
                });
            })
            .catch(() => { /* Silencioso: no bloquear el módulo por el aviso. */ });
    };
})();
