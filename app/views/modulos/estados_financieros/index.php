<?php
/** @var string $titulo */
/** @var string $rutaModulo */
/** @var string $fechaInicio */
/** @var string $fechaFin */
/** @var array $aniosDisponibles */
/** @var array $centrosCosto */
/** @var array $proyectos */
/** @var array $perm */

$base = BASE_URL;
$urlBaseReporte = rtrim($base, '/') . '/' . ltrim($rutaModulo ?? '', '/');
?>
<!-- Estado de la generación de asientos pendientes (se completa en segundo plano vía JS) -->
<div id="ef-sync-status" class="alert alert-info d-flex align-items-center shadow-sm mb-3" role="alert">
    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
    <span>Verificando y generando asientos contables pendientes, espere un momento…</span>
</div>
<div id="ef-warnings"></div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold" style="font-family: 'Inter', sans-serif;"><i class="bi bi-bar-chart-line text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    </div>
    
    <!-- Filtros -->
    <div class="px-4 pb-3 pt-2 bg-light bg-opacity-50 border-bottom border-top">
        <form id="formFiltros" class="row g-2 align-items-end" onsubmit="event.preventDefault(); generarReporte();">
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Tipo de Reporte</label>
                <select class="form-select form-select-sm shadow-none" id="tipo_reporte" name="tipo_reporte" onchange="setTipoReporte(this.value)">
                    <option value="situacion">Estado de Situación Financiera</option>
                    <option value="resultados">Estado de Resultados</option>
                </select>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Nivel</label>
                <select class="form-select form-select-sm shadow-none" id="filtro_nivel">
                    <option value="5">Nivel 5 (Todos)</option>
                    <option value="4">Nivel 4</option>
                    <option value="3">Nivel 3</option>
                    <option value="2">Nivel 2</option>
                    <option value="1">Nivel 1</option>
                </select>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Año</label>
                <select class="form-select form-select-sm shadow-none" id="filtro_anio" onchange="actualizarFechas()">
                    <?php foreach ($aniosDisponibles as $anio): ?>
                        <option value="<?= $anio ?>"><?= $anio ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Mes</label>
                <select class="form-select form-select-sm shadow-none" id="filtro_mes" onchange="actualizarFechas()">
                    <option value="0">Todos</option>
                    <option value="1">Enero</option>
                    <option value="2">Febrero</option>
                    <option value="3">Marzo</option>
                    <option value="4">Abril</option>
                    <option value="5">Mayo</option>
                    <option value="6">Junio</option>
                    <option value="7">Julio</option>
                    <option value="8">Agosto</option>
                    <option value="9">Septiembre</option>
                    <option value="10">Octubre</option>
                    <option value="11">Noviembre</option>
                    <option value="12">Diciembre</option>
                </select>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">C. Costo</label>
                <select class="form-select form-select-sm shadow-none" id="filtro_centro_costo">
                    <option value="">Todos</option>
                    <?php foreach ($centrosCosto ?? [] as $cc): ?>
                        <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['codigo'] . ' - ' . $cc['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Proyecto</label>
                <select class="form-select form-select-sm shadow-none" id="filtro_proyecto">
                    <option value="">Todos</option>
                    <?php foreach ($proyectos ?? [] as $py): ?>
                        <option value="<?= $py['id'] ?>"><?= htmlspecialchars($py['codigo'] . ' - ' . $py['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Fecha Inicio</label>
                <input type="date" class="form-control form-control-sm shadow-none" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>" required>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Fecha Fin</label>
                <input type="date" class="form-control form-control-sm shadow-none" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>" required>
            </div>
            <div class="col">
                <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm w-100" id="btnGenerar">
                    <i class="bi bi-search me-1"></i> Generar
                </button>
            </div>
        </form>
    </div>

    <!-- Exportación -->
    <div class="d-flex justify-content-end bg-light px-3 py-2 border-bottom">
        <div class="btn-group btn-group-sm shadow-sm">
            <button type="button" class="btn btn-white border px-3" title="Descargar PDF" onclick="exportar('pdf')">
                <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
            </button>
            <button type="button" class="btn btn-white border px-3" title="Descargar Excel" onclick="exportar('excel')">
                <i class="bi bi-file-earmark-excel text-success"></i> Excel
            </button>
            <button type="button" class="btn btn-white border px-3" title="Descargar Formato SRI" onclick="exportar('sri')">
                <i class="bi bi-file-earmark-code text-primary"></i> Renta SRI
            </button>
            <button type="button" class="btn btn-white border px-3" title="Descargar Supercias ESF" onclick="exportar('supercias_esf')">
                <i class="bi bi-bank text-info"></i> Supercias ESF
            </button>
            <button type="button" class="btn btn-white border px-3" title="Descargar Supercias ERI" onclick="exportar('supercias_eri')">
                <i class="bi bi-bank text-info"></i> Supercias ERI
            </button>
            <button type="button" class="btn btn-white border px-3" title="Descargar Supercias ECP" onclick="exportar('supercias_ecp')">
                <i class="bi bi-bank text-info"></i> Supercias ECP
            </button>
            <button type="button" class="btn btn-white border px-3" title="Descargar Supercias EFE" onclick="exportar('supercias_efe')">
                <i class="bi bi-bank text-info"></i> Supercias EFE
            </button>
        </div>
    </div>

    <!-- Contenido del reporte -->
    <div class="px-3 py-3" style="min-height: 400px;">
        <div id="loader-reporte" class="text-center py-5 d-none">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="text-muted mt-2 small">Generando reporte...</p>
        </div>
        <div id="content-reporte" class="table-responsive">
            <p class="text-muted text-center py-5 small"><i class="bi bi-info-circle me-1"></i> Seleccione el rango de fechas y presione Generar.</p>
        </div>
    </div>
</div>

<!-- Modal para Libro Mayor Auxiliar -->
<div class="modal fade" id="modalMayor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold" id="tituloModalMayor"><i class="bi bi-journal-text text-primary me-2"></i> Mayor Auxiliar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="loader-mayor" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                    <p class="text-muted mt-2 small">Cargando movimientos...</p>
                </div>
                <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                    <table class="table table-sm table-hover table-bordered mb-0" id="tablaMayor" style="font-size: 0.85rem;">
                        <thead class="table-light text-center" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>Fecha</th>
                                <th>Asiento</th>
                                <th>Referencia</th>
                                <th>Concepto</th>
                                <th>Debe</th>
                                <th>Haber</th>
                                <th>Saldo</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyMayor">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .tabla-reporte { width: 100%; border-collapse: collapse; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
    .tabla-reporte th { padding: 8px 12px; background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; }
    .tabla-reporte td { padding: 6px 12px; border-bottom: 1px solid #e9ecef; color: #212529; }
    .tabla-reporte tr:hover td { background-color: #f8f9fa; }
    .tr-grupo td { font-weight: bold; background-color: rgba(0,0,0,0.02); }
    .tr-total td { font-weight: bold; background-color: rgba(13, 110, 253, 0.05); color: #0d6efd; border-top: 2px solid #dee2e6; }
    .tr-total-general td { font-weight: 800; background-color: #f8f9fa; border-top: 2px solid #343a40; font-size: 0.95rem; }
    .monto-negativo { color: #dc3545; }
</style>

<script>
    let tipoReporteActivo = 'situacion';
    const urlBase = '<?= $urlBaseReporte ?>';

    // ── Generación de asientos pendientes en segundo plano ──────────────────────────
    // La página ya cargó; disparamos la sincronización sin bloquearla y mostramos el
    // avance en el banner #ef-sync-status. Al terminar se muestran los avisos (si hay).
    (function sincronizarAsientosPendientes() {
        const box = document.getElementById('ef-sync-status');
        const warnBox = document.getElementById('ef-warnings');
        if (!box) return;

        const escapeHtml = (s) => String(s).replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        fetch(`${urlBase}/sincronizarAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                box.remove();
                let html = '';

                if (json && !json.success) {
                    // Falló la corrida completa (no se pudo ni ejecutar la generación).
                    html =
                        `<div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
                            <i class="bi bi-x-octagon-fill me-2"></i> No se pudieron generar los asientos pendientes: ${escapeHtml(json.error || 'error')}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>`;
                } else if (json && json.success) {
                    // Asientos generados en esta corrida (aviso informativo).
                    if (json.generados > 0) {
                        html +=
                            `<div class="alert alert-success alert-dismissible fade show shadow-sm mb-3" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i> Se generaron <strong>${json.generados}</strong> asiento(s) contable(s) que estaban pendientes.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>`;
                    }
                    // Pendientes con error / avisos de configuración: incluyen el motivo real.
                    if (Array.isArray(json.warnings) && json.warnings.length) {
                        const items = json.warnings.map(w => `<li class="mb-1">${escapeHtml(w)}</li>`).join('');
                        html +=
                            `<div class="alert alert-warning alert-dismissible fade show shadow-sm mb-3" role="alert">
                                <strong><i class="bi bi-exclamation-triangle-fill me-2"></i> Asientos pendientes o con error:</strong>
                                <ul class="mb-0 mt-2">${items}</ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>`;
                    }
                }

                warnBox.innerHTML = html;
            })
            .catch(() => {
                const sp = box.querySelector('.spinner-border'); if (sp) sp.remove();
                box.classList.remove('alert-info');
                box.classList.add('alert-danger');
                box.querySelector('span').textContent =
                    'No se pudo completar la generación de asientos. Recargue la página para reintentar.';
            });
    })();

    function setTipoReporte(tipo) {
        tipoReporteActivo = tipo;
        document.getElementById('content-reporte').innerHTML = '<p class="text-muted text-center py-5 small"><i class="bi bi-info-circle me-1"></i> Presione Generar para actualizar el reporte.</p>';
    }

    function actualizarFechas() {
        const anio = document.getElementById('filtro_anio').value;
        const mes = parseInt(document.getElementById('filtro_mes').value);
        
        let fInicio, fFin;
        
        if (mes === 0) {
            fInicio = `${anio}-01-01`;
            fFin = `${anio}-12-31`;
        } else {
            const mesStr = mes.toString().padStart(2, '0');
            fInicio = `${anio}-${mesStr}-01`;
            // Calcular el último día del mes (el día 0 del mes siguiente)
            const ultimoDia = new Date(anio, mes, 0).getDate();
            const ultimoDiaStr = ultimoDia.toString().padStart(2, '0');
            fFin = `${anio}-${mesStr}-${ultimoDiaStr}`;
        }
        
        document.getElementById('fecha_inicio').value = fInicio;
        document.getElementById('fecha_fin').value = fFin;
    }

    const formatMoney = (amount) => {
        const num = parseFloat(amount) || 0;
        const formatted = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return num < 0 ? `<span class="monto-negativo">${formatted}</span>` : formatted;
    };

    async function generarReporte() {
        const form = document.getElementById('formFiltros');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const btn = document.getElementById('btnGenerar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generando...';

        const fInicio = document.getElementById('fecha_inicio').value;
        const fFin = document.getElementById('fecha_fin').value;
        const nivel = document.getElementById('filtro_nivel').value;
        const centro = document.getElementById('filtro_centro_costo').value;
        const proyecto = document.getElementById('filtro_proyecto').value;
        
        try {
            document.getElementById('loader-reporte').classList.remove('d-none');
            document.getElementById('content-reporte').innerHTML = '';
            
            if (tipoReporteActivo === 'resultados') {
                const resp = await fetch(`${urlBase}/generarEstadoResultados?fecha_inicio=${fInicio}&fecha_fin=${fFin}&nivel=${nivel}&centro_costo=${centro}&proyecto=${proyecto}`);
                const json = await resp.json();
                if (json.success) {
                    renderResultados(json.data, nivel);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'Error al generar el reporte' });
                }
            } else {
                const resp = await fetch(`${urlBase}/generarEstadoSituacionFinanciera?fecha_inicio=${fInicio}&fecha_fin=${fFin}&nivel=${nivel}&centro_costo=${centro}&proyecto=${proyecto}`);
                const json = await resp.json();
                if (json.success) {
                    renderSituacion(json.data, nivel);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'Error al generar el reporte' });
                }
            }
            document.getElementById('loader-reporte').classList.add('d-none');
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor al generar el reporte.' });
            document.getElementById('loader-reporte').classList.add('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Generar';
        }
    }

    function generarCabecera(nivelFiltro) {
        const nf = parseInt(nivelFiltro);
        let html = `<thead><tr><th width="10%">Código</th><th>Cuenta</th>`;
        for (let i = nf; i >= 1; i--) {
            html += `<th width="13%" class="text-end">Nivel ${i}</th>`;
        }
        return html + `</tr></thead><tbody>`;
    }

    function generarFila(item, nivelSeleccionado) {
        const nivelItem = parseInt(item.nivel);
        const nivelFiltro = parseInt(nivelSeleccionado);
        const esPadre = nivelItem < nivelFiltro;
        const indent = (nivelItem - 1) * 20; 
        const fwClass = esPadre ? 'fw-bold text-dark' : '';
        const paddingStyle = `padding-left: ${15 + indent}px !important;`;
        
        let cursorClass = '';
        let onclickAttr = '';
        if (nivelItem === 5 && !esPadre) {
            cursorClass = 'text-primary cursor-pointer fw-medium';
            onclickAttr = `onclick="verMayorAuxiliar('${item.codigo}', '${item.nombre}')" style="cursor:pointer; text-decoration: underline;" title="Ver detalle del Mayor"`;
        }
        
        let tdsNiveles = '';
        for (let i = nivelFiltro; i >= 1; i--) {
            if (i === nivelItem) {
                tdsNiveles += `<td class="text-end ${fwClass}">${formatMoney(item.saldo_final)}</td>`;
            } else {
                tdsNiveles += `<td></td>`;
            }
        }
        
        return `<tr>
            <td class="${fwClass}">${item.codigo}</td>
            <td style="${paddingStyle}" class="${fwClass} ${cursorClass}" ${onclickAttr}>${item.nombre}</td>
            ${tdsNiveles}
        </tr>`;
    }

    function generarFilaTotal(titulo, monto, nivelFiltro, colorClass = '', esGeneral = false) {
        const nf = parseInt(nivelFiltro);
        let tdsNiveles = '';
        const claseFila = esGeneral ? 'tr-total-general' : 'tr-total';
        for (let i = nf; i >= 1; i--) {
            if (i === 1) {
                tdsNiveles += `<td class="text-end ${colorClass}">${formatMoney(monto)}</td>`;
            } else {
                tdsNiveles += `<td></td>`;
            }
        }
        return `<tr class="${claseFila}"><td colspan="2" class="text-end ${colorClass}">${titulo}</td>${tdsNiveles}</tr>`;
    }

    function generarFilaGrupo(titulo, icono, nivelFiltro) {
        const cols = 2 + parseInt(nivelFiltro);
        return `<tr class="tr-grupo"><td colspan="${cols}"><i class="${icono} me-2"></i> ${titulo}</td></tr>`;
    }

    function generarFilaEspacio(nivelFiltro, h = 15) {
        const cols = 2 + parseInt(nivelFiltro);
        return `<tr><td colspan="${cols}" style="height:${h}px; border:none;"></td></tr>`;
    }

    function renderResultados(data, nivel) {
        let html = '<table class="tabla-reporte">';
        html += generarCabecera(nivel);
        
        // INGRESOS
        html += generarFilaGrupo('INGRESOS', 'bi bi-arrow-up-right-circle text-success', nivel);
        data.ingresos.forEach(item => { html += generarFila(item, nivel); });
        html += generarFilaTotal('TOTAL INGRESOS', data.totales.ingresos, nivel);
        html += generarFilaEspacio(nivel);

        // COSTOS
        html += generarFilaGrupo('COSTOS', 'bi bi-box text-warning', nivel);
        data.costos.forEach(item => { html += generarFila(item, nivel); });
        html += generarFilaTotal('TOTAL COSTOS', data.totales.costos, nivel, 'text-dark');
        html += generarFilaEspacio(nivel, 10);

        // UTILIDAD BRUTA
        const lblBruta = data.totales.utilidad_bruta >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA';
        html += generarFilaTotal(lblBruta, data.totales.utilidad_bruta, nivel, '', true);
        html += generarFilaEspacio(nivel);

        // GASTOS
        html += generarFilaGrupo('GASTOS', 'bi bi-arrow-down-right-circle text-danger', nivel);
        data.gastos.forEach(item => { html += generarFila(item, nivel); });
        html += generarFilaTotal('TOTAL GASTOS', data.totales.gastos, nivel, 'text-danger');
        html += generarFilaEspacio(nivel);

        // UTILIDAD NETA
        const lblNeta = data.totales.utilidad_neta >= 0 ? 'UTILIDAD DEL EJERCICIO' : 'PÉRDIDA DEL EJERCICIO';
        const classNeta = data.totales.utilidad_neta >= 0 ? 'text-success' : 'text-danger';
        html += generarFilaTotal(lblNeta, data.totales.utilidad_neta, nivel, classNeta, true);

        html += '</tbody></table>';
        document.getElementById('content-reporte').innerHTML = html;
    }

    function renderSituacion(data, nivel) {
        const totalActivos = parseFloat(data.totales.activos) || 0;
        const totalPasivoPatrimonio = parseFloat(data.totales.pasivo_patrimonio) || 0;
        const diferencia = totalActivos - totalPasivoPatrimonio;
        const cuadra = Math.abs(diferencia) < 0.01;

        let html = '';
        if (!cuadra) {
            html += `<div class="alert alert-danger d-flex align-items-center shadow-sm mb-3" role="alert">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                <div>
                    <strong>El balance no cuadra con el principio de partida doble.</strong>
                    Activos (${formatMoney(totalActivos)}) ≠ Pasivo + Patrimonio (${formatMoney(totalPasivoPatrimonio)}) —
                    diferencia de <strong>${formatMoney(Math.abs(diferencia))}</strong>.
                </div>
            </div>`;
        }

        html += '<table class="tabla-reporte">';
        html += generarCabecera(nivel);

        // ACTIVOS
        html += generarFilaGrupo('ACTIVOS', 'bi bi-bank text-primary', nivel);
        data.activos.forEach(item => { html += generarFila(item, nivel); });
        html += generarFilaTotal('TOTAL ACTIVOS', data.totales.activos, nivel);
        html += generarFilaEspacio(nivel);

        // PASIVOS
        html += generarFilaGrupo('PASIVOS', 'bi bi-credit-card text-warning', nivel);
        data.pasivos.forEach(item => { html += generarFila(item, nivel); });
        html += generarFilaTotal('TOTAL PASIVOS', data.totales.pasivos, nivel, 'text-dark');
        html += generarFilaEspacio(nivel);

        // PATRIMONIO
        html += generarFilaGrupo('PATRIMONIO', 'bi bi-pie-chart text-info', nivel);
        data.patrimonio.forEach(item => { html += generarFila(item, nivel); });
        html += generarFilaTotal('TOTAL PATRIMONIO', data.totales.patrimonio, nivel, 'text-dark');
        html += generarFilaEspacio(nivel);

        // TOTAL PASIVO + PATRIMONIO
        html += generarFilaTotal('TOTAL PASIVO + PATRIMONIO', data.totales.pasivo_patrimonio, nivel, cuadra ? '' : 'text-danger', true);

        html += '</tbody></table>';
        document.getElementById('content-reporte').innerHTML = html;
    }

    function exportar(formato) {
        const fInicio = document.getElementById('fecha_inicio').value;
        const fFin = document.getElementById('fecha_fin').value;
        const nivel = document.getElementById('filtro_nivel').value;
        const centro = document.getElementById('filtro_centro_costo').value;
        const proyecto = document.getElementById('filtro_proyecto').value;
        if (!fInicio || !fFin) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Por favor seleccione un rango de fechas válido.' });
            return;
        }
        
        let url = `${urlBase}/exportar?tipo=${tipoReporteActivo}&formato=${formato}&fecha_inicio=${fInicio}&fecha_fin=${fFin}&nivel=${nivel}&centro_costo=${centro}&proyecto=${proyecto}`;
        window.open(url, '_blank');
    }

    async function verMayorAuxiliar(codigoCuenta, nombreCuenta) {
        const modal = new bootstrap.Modal(document.getElementById('modalMayor'));
        document.getElementById('tituloModalMayor').innerHTML = `<i class="bi bi-journal-text text-primary me-2"></i> Mayor: ${codigoCuenta} - ${nombreCuenta}`;
        const tbody = document.getElementById('tbodyMayor');
        const loader = document.getElementById('loader-mayor');
        
        tbody.innerHTML = '';
        loader.classList.remove('d-none');
        modal.show();

        const fInicio = document.getElementById('fecha_inicio').value;
        const fFin = document.getElementById('fecha_fin').value;
        const centro = document.getElementById('filtro_centro_costo').value;
        const proyecto = document.getElementById('filtro_proyecto').value;

        try {
            const resp = await fetch(`${urlBase}/generarMayorAuxiliar?codigo_cuenta=${codigoCuenta}&fecha_inicio=${fInicio}&fecha_fin=${fFin}&centro_costo=${centro}&proyecto=${proyecto}`);
            const json = await resp.json();
            loader.classList.add('d-none');
            
            if (json.success) {
                if (json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No hay movimientos en este rango de fechas.</td></tr>';
                    return;
                }
                
                let html = '';
                json.data.forEach(item => {
                    const de = parseFloat(item.debe) || 0;
                    const ha = parseFloat(item.haber) || 0;

                    html += `<tr>
                        <td class="text-center">${item.fecha_asiento}</td>
                        <td class="text-center"><a href="#" onclick="event.preventDefault(); ASIENTO_abrirModal(${item.id_asiento});" class="text-decoration-none fw-bold" title="Ver asiento contable">${item.numero_comprobante || 'S/N'}</a></td>
                        <td>${item.documento_referencia || ''}</td>
                        <td><small>${item.referencia_detalle || item.concepto || ''}</small></td>
                        <td class="text-end ${de > 0 ? 'text-dark' : 'text-muted'}">${formatMoney(de)}</td>
                        <td class="text-end ${ha > 0 ? 'text-dark' : 'text-muted'}">${formatMoney(ha)}</td>
                        <td class="text-end fw-bold">${formatMoney(item.saldo_acumulado)}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${json.error || 'Error al obtener el mayor'}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            loader.classList.add('d-none');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error de red o servidor.</td></tr>';
        }
    }
</script>

<!-- Modal del Asiento Contable reutilizado para ver/editar el asiento desde el Mayor -->
<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include __DIR__ . '/../asientos_contables/modal_asiento.php'; ?>
<script src="<?= $base ?>/js/modulos/asientos_contables_modal.js?v=<?= time() ?>"></script>
<script>
    // Apilar el modal del Asiento por encima del modal del Mayor (z-index correcto).
    document.addEventListener('show.bs.modal', function (e) {
        const abiertos = document.querySelectorAll('.modal.show').length;
        if (abiertos > 0) {
            const z = 1056 + abiertos * 20;
            e.target.style.zIndex = z;
            setTimeout(() => {
                const backs = document.querySelectorAll('.modal-backdrop:not(.modal-stack-fixed)');
                const last = backs[backs.length - 1];
                if (last) { last.style.zIndex = z - 1; last.classList.add('modal-stack-fixed'); }
            }, 0);
        }
    });
</script>
