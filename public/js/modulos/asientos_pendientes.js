/**
 * Aviso de asientos contables pendientes de generar.
 *
 * Se usa en los módulos que muestran contabilidad ya consolidada (Libro Diario / Asientos,
 * Estados Financieros, Balance de Comprobación y Mayores). Al abrir el módulo consulta cuántos
 * documentos operativos están SIN asiento generado y, si hay, pregunta al usuario si desea
 * generarlos ahora o continuar sin generar. NO genera nada por su cuenta: la generación solo
 * ocurre si el usuario la acepta explícitamente.
 *
 * La generación se hace PASO A PASO (un módulo/verificación por llamada, ver
 * SincronizadorAsientosService::ejecutarPaso()) en vez de una sola llamada bloqueante: así se
 * puede mostrar una barra de progreso real y el usuario puede cancelar entre pasos — cada
 * llamada HTTP es corta, así que "cancelar" simplemente deja de pedir el siguiente paso.
 *
 * Endpoints esperados en el controlador del módulo (mismo urlBase):
 *   GET {urlBase}/contarPendientesAjax        → { ok: true, pendientes: <int>, migrados_sin_asiento: <int> }
 *   GET {urlBase}/sincronizarPasoAjax?paso=N  → { ok: true, paso, totalPasos, nombrePaso,
 *                                                 terminado, generados, warnings, detalle,
 *                                                 resumenPorModulo, info }
 *
 * `migrados_sin_asiento` e `info` son el canal INFORMATIVO (azul, no es error ni pendiente):
 * documentos traídos de la migración que siguen sin asiento. La generación automática no los
 * toca (su contabilidad debía venir en el histórico migrado), así que no cuentan como
 * pendientes, pero el usuario debe saber que existen y cómo resolverlo.
 *   GET {urlBase}/sincronizarAjax             → (respaldo sin barra de progreso, solo si no hay SweetAlert2)
 *   GET {urlBase}/sincronizarMigradosPasoAjax?paso=N → igual que sincronizarPasoAjax, pero SOLO para los
 *                                                 documentos migrados sin asiento (acción explícita, con
 *                                                 confirmación; ver confirmarYGenerarMigrados()).
 *
 * Uso:
 *   CMG_verificarAsientosPendientes({ urlBase: '<...>', onGenerado: () => { ... } });
 */
(function () {
    'use strict';

    const escapeHtml = (s) => String(s).replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function buildProgressHtml(actual, total, nombrePaso) {
        const pct = total > 0 ? Math.round((Math.min(actual, total) / total) * 100) : 0;
        return `
            <div class="text-start small mb-2 text-truncate">${nombrePaso ? escapeHtml(nombrePaso) : 'Preparando…'}</div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                     style="width: ${pct}%;" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100">${pct}%</div>
            </div>
            <div class="text-muted small mt-1">Paso ${Math.min(actual, total)} de ${total}</div>
        `;
    }

    /** Arma y muestra el resultado final (generados + resumen + detalle + avisos), acumulados de todos los pasos. */
    function mostrarResultado(opts) {
        const { resumen, detalle, warnings, generados, interrumpido, onGenerado } = opts;
        const info = Array.isArray(opts.info) ? opts.info : [];
        const hayPendientes = !!resumen || warnings.length > 0;

        let html = '';
        if (interrumpido) {
            html += `<div class="mb-2"><i class="bi bi-slash-circle text-secondary me-1"></i> Proceso interrumpido por el usuario: se muestra lo generado hasta el momento.</div>`;
        }
        if (generados > 0) {
            html += `<div class="mb-2"><i class="bi bi-check-circle-fill text-success me-1"></i> Se generaron <strong>${generados}</strong> asiento(s) contable(s).</div>`;
        }
        if (resumen) {
            html += `<div class="text-start small mb-2"><i class="bi bi-exclamation-triangle text-warning me-1"></i> ${escapeHtml(resumen)}</div>`;
        }
        if (detalle.length) {
            const idDetalle = `asientosPendDetalle_${Date.now()}`;
            html += `<div class="text-start small mb-2">`
                + `<a href="#" class="link-secondary" onclick="event.preventDefault(); `
                + `var d=document.getElementById('${idDetalle}'); d.style.display = d.style.display==='none' ? '' : 'none';">`
                + `<i class="bi bi-chevron-down me-1"></i>Ver detalle (qué cuenta falta, qué documentos)</a>`
                + `<ul id="${idDetalle}" class="mb-0 mt-1 small" style="display:none;">`
                + detalle.map(d => `<li class="mb-1">${escapeHtml(d)}</li>`).join('') + `</ul></div>`;
        }
        if (warnings.length) {
            html += `<div class="text-start small"><strong>Otros avisos:</strong>`
                + `<ul class="mb-0 mt-1 small">${warnings.map(w => `<li class="mb-1">${escapeHtml(w)}</li>`).join('')}</ul></div>`;
        }
        if (!html) html = 'No quedaron asientos por generar.';
        if (info.length) {
            // Informativo: no cambia el ícono ni el título (no es un pendiente ni un error).
            html += `<div class="text-start small alert alert-info py-2 px-3 mt-3 mb-0"><strong><i class="bi bi-info-circle-fill me-1"></i>Información:</strong>`
                + `<ul class="mb-0 mt-1 small">${info.map(i => `<li class="mb-1">${escapeHtml(i)}</li>`).join('')}</ul></div>`;
        }

        if (window.Swal) {
            Swal.fire({
                icon: interrumpido ? 'info' : (hayPendientes ? 'warning' : 'success'),
                title: interrumpido ? 'Generación interrumpida' : (hayPendientes ? 'Generación completada con avisos' : 'Asientos generados'),
                html: html,
                width: (detalle.length || info.length) ? 640 : undefined,
                confirmButtonText: 'Aceptar',
            }).then(() => { if (typeof onGenerado === 'function') onGenerado(); });
        } else if (typeof onGenerado === 'function') {
            onGenerado();
        }
    }

    /** Respaldo sin barra de progreso (SweetAlert2 no disponible): una sola llamada bloqueante, como antes. */
    function generarSinProgreso(urlBase, onGenerado) {
        return fetch(`${urlBase}/sincronizarAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                if (!json || json.success === false) return;
                if (typeof onGenerado === 'function') onGenerado();
            })
            .catch(() => { /* silencioso: no hay Swal para avisar el error */ });
    }

    function generar(urlBase, onGenerado, accion) {
        accion = accion || 'sincronizarPasoAjax';
        if (!window.Swal) {
            return generarSinProgreso(urlBase, onGenerado);
        }

        let cancelado = false;
        Swal.fire({
            title: 'Generando asientos…',
            html: buildProgressHtml(0, 1, 'Preparando…'),
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '<i class="bi bi-x-circle me-1"></i> Cancelar',
            cancelButtonColor: '#6c757d',
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) cancelado = true;
        });

        const acumWarnings = [];
        const acumDetalle = [];
        const acumInfo = [];
        const acumResumen = {};
        let generados = 0;

        (async () => {
            let n = 0;
            let totalPasos = 1;
            let interrumpido = false;
            let errorMsg = null;

            while (n < totalPasos) {
                if (cancelado) { interrumpido = true; break; }

                let json;
                try {
                    const res = await fetch(`${urlBase}/${accion}?paso=${n}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    json = await res.json();
                } catch (e) {
                    errorMsg = 'No se pudo completar la generación de asientos (error de red).';
                    break;
                }
                if (!json || json.ok === false) {
                    errorMsg = (json && json.error) ? json.error : 'No se pudieron generar los asientos.';
                    break;
                }

                totalPasos = json.totalPasos || totalPasos;
                generados += json.generados || 0;
                if (Array.isArray(json.warnings)) acumWarnings.push(...json.warnings);
                if (Array.isArray(json.detalle)) acumDetalle.push(...json.detalle);
                if (Array.isArray(json.info)) json.info.forEach(i => { if (!acumInfo.includes(i)) acumInfo.push(i); });
                if (json.resumenPorModulo && typeof json.resumenPorModulo === 'object') {
                    Object.entries(json.resumenPorModulo).forEach(([mod, cnt]) => {
                        acumResumen[mod] = (acumResumen[mod] || 0) + (parseInt(cnt, 10) || 0);
                    });
                }

                n++;
                if (!cancelado) {
                    Swal.update({ html: buildProgressHtml(n, totalPasos, json.nombrePaso || '') });
                }
            }

            Swal.close();

            if (errorMsg) {
                Swal.fire({ icon: 'error', title: 'Error', text: errorMsg });
                return;
            }

            const partes = Object.entries(acumResumen).map(([mod, cnt]) => `${cnt} en ${mod}`);
            const resumen = partes.length ? `Hay asiento(s) por generar: ${partes.join(', ')}. Revise la configuración contable.` : null;

            mostrarResultado({ resumen, detalle: acumDetalle, warnings: acumWarnings, info: acumInfo, generados, interrumpido, onGenerado });
        })();
    }

    /**
     * Generación EXPLÍCITA de asientos para documentos migrados que siguen sin asiento. Pide
     * confirmar que la migración de contabilidad ya se corrió con el rango completo: si no, un
     * documento cuyo asiento histórico todavía no se enlazó recibiría un segundo asiento.
     */
    function confirmarYGenerarMigrados(urlBase, onGenerado) {
        Swal.fire({
            icon: 'warning',
            title: 'Generar asientos a documentos migrados',
            html: `<div class="text-start small">
                <p class="mb-2">Se generarán asientos, con la configuración contable actual, para los documentos traídos del sistema anterior que <strong>no tienen asiento</strong>.</p>
                <p class="mb-2">Hágalo solo si ya volvió a correr la <strong>migración de contabilidad</strong> con el rango completo de fechas: ese paso enlaza los documentos que sí tenían asiento en el sistema anterior, y los que siguen sin asiento después son los que nunca se contabilizaron.</p>
                <p class="mb-0 text-danger">Si la migración de contabilidad no se ha vuelto a correr, algunos documentos podrían quedar con dos asientos.</p>
            </div>`,
            input: 'checkbox',
            inputPlaceholder: 'Ya volví a correr la migración de contabilidad con el rango completo',
            inputValidator: (v) => v ? undefined : 'Debe confirmar para continuar.',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-gear-fill me-1"></i> Generar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            width: 600,
        }).then(res => {
            if (res.isConfirmed) generar(urlBase, onGenerado, 'sincronizarMigradosPasoAjax');
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
                const m = parseInt(json.migrados_sin_asiento, 10) || 0;
                if (n < 1 && m < 1) return;

                // Documentos migrados sin asiento: informativo. La generación no los contabiliza
                // (su contabilidad debía venir en el histórico migrado), así que no se ofrece
                // "generar": se explica qué son y cómo resolverlos.
                const textoMigrados = m > 0
                    ? `Hay <strong>${m}</strong> documento(s) traídos del sistema anterior que siguen <strong>sin asiento contable</strong>. `
                      + `La generación automática no los contabiliza para no duplicar el histórico migrado. `
                      + `Para resolverlo, vuelva a correr la <strong>migración de contabilidad</strong> de la empresa (enlaza cada documento con su asiento histórico) `
                      + `los que sigan sin asiento después de eso nunca se contabilizaron en el sistema anterior: `
                      + `genérelos con <strong>Generar asientos a los migrados</strong> o regístrelos desde la pestaña <em>Asiento contable</em> del documento.`
                    : '';

                if (!window.Swal) {
                    // Sin SweetAlert: confirm/alert nativos como respaldo.
                    if (n >= 1 && window.confirm(`Hay ${n} documento(s) sin asiento contable generado. ¿Desea generarlos ahora?`)) {
                        generar(urlBase, onGenerado);
                    }
                    if (m >= 1) window.alert(`Hay ${m} documento(s) migrados sin asiento contable. Vuelva a correr la migración de contabilidad o registre el asiento desde el documento.`);
                    return;
                }

                if (n < 1) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Documentos migrados sin asiento',
                        html: `<div class="text-start small">${textoMigrados}</div>`,
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-gear-fill me-1"></i> Generar asientos a los migrados',
                        cancelButtonText: 'Entendido',
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                        reverseButtons: true,
                        width: 560,
                    }).then(res => {
                        if (res.isConfirmed) confirmarYGenerarMigrados(urlBase, onGenerado);
                    });
                    return;
                }

                Swal.fire({
                    icon: 'question',
                    title: 'Asientos pendientes',
                    html: `Hay <strong>${n}</strong> documento(s) sin asiento contable generado.<br>¿Desea generarlos ahora?`
                        + (m > 0 ? `<div class="text-start small alert alert-info py-2 px-3 mt-3 mb-0">${textoMigrados}</div>` : ''),
                    width: m > 0 ? 560 : undefined,
                    showDenyButton: m > 0,
                    denyButtonText: '<i class="bi bi-box-arrow-in-down me-1"></i> Migrados sin asiento…',
                    denyButtonColor: '#0dcaf0',
                    showCancelButton: true,
                    confirmButtonText: '<i class="bi bi-gear-fill me-1"></i> Generar ahora',
                    cancelButtonText: 'Continuar sin generar',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false,
                    reverseButtons: true,
                }).then(res => {
                    if (res.isConfirmed) generar(urlBase, onGenerado);
                    else if (res.isDenied) confirmarYGenerarMigrados(urlBase, onGenerado);
                });
            })
            .catch(() => { /* Silencioso: no bloquear el módulo por el aviso. */ });
    };
})();
