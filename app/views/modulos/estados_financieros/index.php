<?php
/** @var string $titulo */
/** @var string $rutaModulo */
/** @var string $fechaInicio */
/** @var string $fechaFin */
/** @var array $aniosDisponibles */
/** @var array $centrosCosto */
/** @var array $proyectos */
/** @var array $perm */
/** @var bool $hayGrupoRuc */

$base = BASE_URL;
$urlBaseReporte = rtrim($base, '/') . '/' . ltrim($rutaModulo ?? '', '/');
$urlBaseActivosFijos = rtrim($base, '/') . '/modulos/activos-fijos';
?>
<div id="ef-depreciacion-warning"></div>

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
                    <option value="situacion_periodos">Estado de Situación Financiera por Periodos</option>
                    <option value="resultados_periodos">Estado de Resultados por Periodos</option>
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
            <button type="button" class="btn btn-white border px-3 btn-export-periodo-unico" title="Descargar Formato SRI" onclick="exportar('sri')">
                <i class="bi bi-file-earmark-code text-primary"></i> Renta SRI
            </button>
            <button type="button" class="btn btn-white border px-3 btn-export-periodo-unico" title="Descargar Supercias ESF" onclick="exportar('supercias_esf')">
                <i class="bi bi-bank text-info"></i> Supercias ESF
            </button>
            <button type="button" class="btn btn-white border px-3 btn-export-periodo-unico" title="Descargar Supercias ERI" onclick="exportar('supercias_eri')">
                <i class="bi bi-bank text-info"></i> Supercias ERI
            </button>
            <button type="button" class="btn btn-white border px-3 btn-export-periodo-unico" title="Descargar Supercias ECP" onclick="exportar('supercias_ecp')">
                <i class="bi bi-bank text-info"></i> Supercias ECP
            </button>
            <button type="button" class="btn btn-white border px-3 btn-export-periodo-unico" title="Ver en pantalla el Estado de Cambios en el Patrimonio (Supercias ECP) antes de descargarlo" onclick="verEcp()">
                <i class="bi bi-grid-3x3 text-info"></i> Ver ECP
            </button>
            <button type="button" class="btn btn-white border px-3 btn-export-periodo-unico" title="Descargar Supercias EFE" onclick="exportar('supercias_efe')">
                <i class="bi bi-bank text-info"></i> Supercias EFE
            </button>
        </div>
        <?php if (!empty($hayGrupoRuc)): ?>
        <button type="button" class="btn btn-outline-primary btn-sm shadow-sm ms-2" onclick="verConsolidadoRuc()">
            <i class="bi bi-diagram-3 me-1"></i> Consolidado por RUC
        </button>
        <?php endif; ?>
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

<!-- Modal: Consolidado por RUC -->
<div class="modal fade" id="modalConsolidadoRuc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i>Consolidado por RUC</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="loader-consolidado" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                </div>
                <div id="content-consolidado" class="d-none">
                    <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>El <strong>Total General Consolidado</strong> es un solo Estado de Situación Financiera / Resultados para todo el RUC: cada concepto mapeado en <a href="<?= $base ?>/modulos/balances-consolidados" target="_blank">Balances Consolidados</a> aparece una sola vez (sumado, o con un valor único si así se configuró — ej. Capital), y cada cuenta que no está mapeada se lista por su propio establecimiento. Nada se cuenta dos veces.</p>

                    <h6 class="fw-bold small text-uppercase text-muted">Total General Consolidado — Situación Financiera</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0" id="tabla-tg-situacion">
                            <thead class="table-light"><tr><th>Concepto</th><th>Origen</th><th class="text-end" style="width:140px">Valor</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold small text-uppercase text-muted">Total General Consolidado — Resultados</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0" id="tabla-tg-resultados">
                            <thead class="table-light"><tr><th>Concepto</th><th>Origen</th><th class="text-end" style="width:140px">Valor</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold small text-uppercase text-muted">Detalle de conceptos consolidados (cómo se armó cada valor del Total General)</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0" id="tabla-consolidado-grupos">
                            <thead class="table-light"><tr><th>Concepto</th><th>Tipo</th><th class="text-end">Saldo</th><th>Detalle por establecimiento</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <h6 class="fw-bold small text-uppercase text-muted">Totales por establecimiento (referencia — sin consolidar)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="tabla-consolidado-estab">
                            <thead class="table-light"><tr>
                                <th>Establecimiento</th><th class="text-end">Activos</th><th class="text-end">Pasivos</th>
                                <th class="text-end">Patrimonio</th><th class="text-end">Ingresos</th><th class="text-end">Costos</th>
                                <th class="text-end">Gastos</th><th class="text-end">Utilidad Neta</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: vista previa del Estado de Cambios en el Patrimonio (Supercias ECP) -->
<div class="modal fade" id="modalEcp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold"><i class="bi bi-grid-3x3 text-info me-2"></i>Estado de Cambios en el Patrimonio (Supercias ECP)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="loader-ecp" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                </div>
                <div id="content-ecp" class="d-none">
                    <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>
                        Cada <strong>columna</strong> es el componente del patrimonio que tiene asignado la cuenta (campo <em>Supercias ECP Columna</em>).
                        <strong>990101</strong> es el saldo de apertura del rango, las filas <strong>9902xx</strong> son los movimientos del año
                        (en la fila fijada en la cuenta o en la fila por defecto de su columna) y <strong>990210</strong> es el resultado del ejercicio del balance.
                        La fila <strong>99</strong> sale de la fórmula del casillero (normalmente el ESF); la fila <em>Diferencia</em> compara 99 con 9901 + 9902 y debe ser cero.
                    </p>
                    <div class="table-responsive mb-3" id="ecp-matriz-wrap"></div>
                    <h6 class="fw-bold small text-uppercase text-muted">Cuentas que alimentan el ECP</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="tabla-ecp-detalle">
                            <thead class="table-light"><tr>
                                <th>Cuenta</th><th>Nombre</th><th>Columna</th><th>Fila de cambios</th>
                                <th class="text-end">Saldo inicial</th><th class="text-end">Movimiento del año</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="exportar('supercias_ecp')"><i class="bi bi-download me-1"></i> Descargar TXT</button>
            </div>
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
                                <th>Documento Ref.</th>
                                <th>Glosa</th>
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
    /* Anchos: código y entidades de control al mínimo, valores compactos (1%: el ancho lo fija
       el contenido con nowrap), y la columna Cuenta se queda con todo el espacio restante. */
    .tabla-reporte th.th-codigo, .tabla-reporte th.th-ent-control, .tabla-reporte th.th-valor { width: 1%; white-space: nowrap; }
    .tabla-reporte td.text-end, .tabla-reporte td:first-child { white-space: nowrap; }
    .tabla-reporte td.td-ent-control { white-space: nowrap; font-size: 0.7rem; }
    .tabla-reporte td.td-ent-control .badge { font-size: 0.68rem; padding: 2px 5px; }
    .tr-grupo td { font-weight: bold; background-color: rgba(0,0,0,0.02); }
    .tr-total td { font-weight: bold; background-color: rgba(13, 110, 253, 0.05); color: #0d6efd; border-top: 2px solid #dee2e6; }
    .tr-total-general td { font-weight: 800; background-color: #f8f9fa; border-top: 2px solid #343a40; font-size: 0.95rem; }
    .monto-negativo { color: #dc3545; }
</style>

<script>
    let tipoReporteActivo = 'situacion';
    const urlBase = '<?= $urlBaseReporte ?>';
    const urlBaseActivosFijos = '<?= $urlBaseActivosFijos ?>';

    // ── Aviso de asientos pendientes de generar ─────────────────────────────────────
    // Al cargar, se consulta cuántos documentos están sin asiento y se pregunta al usuario
    // si desea generarlos ahora o continuar sin generar. Si genera, se refresca el reporte.
    // Se difiere a DOMContentLoaded porque el helper (asientos_pendientes.js) se carga al
    // final del cuerpo, después de este script inline.
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof window.CMG_verificarAsientosPendientes === 'function') {
            window.CMG_verificarAsientosPendientes({
                urlBase: urlBase,
                onGenerado: () => {
                    if (typeof generarReporte === 'function') generarReporte();
                }
            });
        }
    });

    // ── Aviso de depreciación de activos fijos pendiente ─────────────────────────
    // Solo informativo (sin botón de acceso directo): revisa el año/mes que el usuario
    // tiene seleccionado en el reporte y, si hay activos sin depreciar ese período,
    // lo muestra en #ef-depreciacion-warning. No dispara ninguna generación.
    (function verificarDepreciacionPendiente() {
        const box = document.getElementById('ef-depreciacion-warning');
        if (!box) return;

        const anio = document.getElementById('filtro_anio')?.value;
        const mes = document.getElementById('filtro_mes')?.value;
        if (!anio) return;

        const qs = mes && mes !== '0' ? `anio=${anio}&mes=${mes}` : `anio=${anio}`;

        fetch(`${urlBaseActivosFijos}/periodosPendientesAjax?${qs}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                if (!json || !json.ok || !Array.isArray(json.data) || !json.data.length) return;
                const periodos = json.data.map(p => `${p.mes_nombre} ${p.anio}`).join(', ');
                box.innerHTML =
                    `<div class="alert alert-warning d-flex align-items-center shadow-sm mb-3" role="alert">
                        <i class="bi bi-graph-down-arrow me-2"></i>
                        <span>Falta generar la depreciación de activos fijos de: <strong>${periodos}</strong>.</span>
                    </div>`;
            })
            .catch(() => { /* silencioso: es solo un aviso informativo */ });
    })();

    function setTipoReporte(tipo) {
        tipoReporteActivo = tipo;
        document.getElementById('content-reporte').innerHTML = '<p class="text-muted text-center py-5 small"><i class="bi bi-info-circle me-1"></i> Presione Generar para actualizar el reporte.</p>';

        // Renta SRI y Supercias son formatos de un solo corte; no aplican a los reportes por periodos.
        const esPeriodos = tipo === 'resultados_periodos' || tipo === 'situacion_periodos';
        document.querySelectorAll('.btn-export-periodo-unico').forEach(btn => {
            btn.classList.toggle('d-none', esPeriodos);
        });
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

    async function verConsolidadoRuc() {
        const modalEl = document.getElementById('modalConsolidadoRuc');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        document.getElementById('loader-consolidado').classList.remove('d-none');
        document.getElementById('content-consolidado').classList.add('d-none');

        const params = new URLSearchParams({
            fecha_inicio: document.getElementById('fecha_inicio').value,
            fecha_fin: document.getElementById('fecha_fin').value,
            nivel: document.getElementById('filtro_nivel').value,
            centro_costo: document.getElementById('filtro_centro_costo').value,
            proyecto: document.getElementById('filtro_proyecto').value,
        });

        try {
            const res = await fetch(`${urlBase}/generarConsolidadoRucAjax?${params.toString()}`).then(r => r.json());
            if (!res.success) { Swal.fire('Error', res.error || 'No se pudo cargar el consolidado.', 'error'); modal.hide(); return; }
            const d = res.data;

            const escTG = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const origenTG = (l) => {
                if (l.origen === 'grupo') return '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Grupo consolidado</span>';
                if (l.origen === 'resultado') return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Resultado del período</span>';
                return `<span class="text-muted small">${escTG(l.establecimiento || '')}</span>`;
            };
            const filaTG = (l) => `<tr>
                <td>${escTG(l.nombre)}${l.codigo ? ` <span class="text-muted small">(${escTG(l.codigo)})</span>` : ''}</td>
                <td>${origenTG(l)}</td>
                <td class="text-end">${formatMoney(l.valor)}</td>
            </tr>`;
            const filaTotalTG = (nombre, valor, clase) => `<tr class="${clase || 'tr-total'}"><td colspan="2">${nombre}</td><td class="text-end">${formatMoney(valor)}</td></tr>`;

            const tg = d.total_general;
            const tbTgSituacion = document.querySelector('#tabla-tg-situacion tbody');
            const tbTgResultados = document.querySelector('#tabla-tg-resultados tbody');
            if (!tg) {
                tbTgSituacion.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No aplica.</td></tr>';
                tbTgResultados.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No aplica.</td></tr>';
            } else {
                tbTgSituacion.innerHTML =
                    tg.activo.map(filaTG).join('') + filaTotalTG('TOTAL ACTIVOS', tg.totales.activo) +
                    tg.pasivo.map(filaTG).join('') + filaTotalTG('TOTAL PASIVOS', tg.totales.pasivo) +
                    tg.patrimonio.map(filaTG).join('') + filaTotalTG('TOTAL PATRIMONIO', tg.totales.patrimonio) +
                    filaTotalTG('TOTAL PASIVO + PATRIMONIO', tg.totales.pasivo_patrimonio, 'tr-total-general');

                tbTgResultados.innerHTML =
                    tg.ingreso.map(filaTG).join('') + filaTotalTG('TOTAL INGRESOS', tg.totales.ingreso) +
                    tg.costo.map(filaTG).join('') + filaTotalTG('TOTAL COSTOS', tg.totales.costo) +
                    filaTotalTG('UTILIDAD BRUTA', tg.totales.utilidad_bruta) +
                    tg.gasto.map(filaTG).join('') + filaTotalTG('TOTAL GASTOS', tg.totales.gasto) +
                    filaTotalTG('UTILIDAD NETA', tg.totales.utilidad_neta, 'tr-total-general');
            }

            const tbGrupos = document.querySelector('#tabla-consolidado-grupos tbody');
            if (!d.consolidado || !d.consolidado.length) {
                tbGrupos.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No hay conceptos consolidados configurados. Ir a <a href="<?= $base ?>/modulos/balances-consolidados" target="_blank">Balances Consolidados</a>.</td></tr>';
            } else {
                tbGrupos.innerHTML = d.consolidado.map(g => {
                    const detalle = (g.detalle || []).map(x => {
                        const linea = `${x.establecimiento}: ${x.codigo} - ${x.nombre} (${formatMoney(x.valor)})`;
                        return x.incluido === false ? `<span class="text-decoration-line-through" title="No se suma: este grupo es de cuenta única (modo UNICA)">${linea}</span>` : linea;
                    }).join('<br>');
                    const badgeUnica = g.modo === 'UNICA' ? ' <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25">Única</span>' : '';
                    return `<tr>
                        <td class="fw-bold">${g.nombre}${badgeUnica}</td>
                        <td>${g.tipo}</td>
                        <td class="text-end fw-bold">${formatMoney(g.saldo)}</td>
                        <td class="small text-muted">${detalle}</td>
                    </tr>`;
                }).join('');
            }

            const tbEstab = document.querySelector('#tabla-consolidado-estab tbody');
            tbEstab.innerHTML = (d.por_establecimiento || []).map(e => {
                const s = e.situacion.totales, r = e.resultados.totales;
                return `<tr>
                    <td class="fw-bold">${e.etiqueta}</td>
                    <td class="text-end">${formatMoney(s.activos)}</td>
                    <td class="text-end">${formatMoney(s.pasivos)}</td>
                    <td class="text-end">${formatMoney(s.patrimonio)}</td>
                    <td class="text-end">${formatMoney(r.ingresos)}</td>
                    <td class="text-end">${formatMoney(r.costos)}</td>
                    <td class="text-end">${formatMoney(r.gastos)}</td>
                    <td class="text-end fw-bold">${formatMoney(r.utilidad_neta)}</td>
                </tr>`;
            }).join('');

            document.getElementById('loader-consolidado').classList.add('d-none');
            document.getElementById('content-consolidado').classList.remove('d-none');
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de red o servidor.', 'error');
            modal.hide();
        }
    }

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
            
            const endpoints = {
                resultados: { url: 'generarEstadoResultados', render: renderResultados },
                situacion: { url: 'generarEstadoSituacionFinanciera', render: renderSituacion },
                resultados_periodos: { url: 'generarEstadoResultadosPorPeriodos', render: renderResultadosPeriodos },
                situacion_periodos: { url: 'generarEstadoSituacionFinancieraPorPeriodos', render: renderSituacionPeriodos },
            };
            const ep = endpoints[tipoReporteActivo] || endpoints.situacion;

            const resp = await fetch(`${urlBase}/${ep.url}?fecha_inicio=${fInicio}&fecha_fin=${fFin}&nivel=${nivel}&centro_costo=${centro}&proyecto=${proyecto}`);
            const json = await resp.json();
            if (json.success) {
                ep.render(json.data, nivel);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'Error al generar el reporte' });
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

    // Celda del código: en cuentas de nivel 2 a 5 es clicable y abre la ficha de la cuenta
    // (modal reutilizable de Plan de Cuentas). En nivel 5 incluye además los códigos de
    // entidades de control; en niveles 2 a 4 solo nombre y estado. Nivel 1 (grupos raíz) no.
    function celdaCodigo(item, esPadre, fwClass) {
        const nivelItem = parseInt(item.nivel);
        const idCuenta = parseInt(item.id_cuenta || 0);
        if (nivelItem >= 2 && idCuenta > 0) {
            const titulo = PC_PUEDE_ACTUALIZAR ? 'Ver / editar la cuenta contable' : 'Ver la cuenta contable';
            return `<td class="${fwClass}"><a href="javascript:void(0)" class="text-decoration-none fw-medium" onclick="abrirCuentaContable(${idCuenta})" title="${titulo}"><i class="bi bi-pencil-square small me-1 text-muted"></i>${item.codigo}</a></td>`;
        }
        return `<td class="${fwClass}">${item.codigo}</td>`;
    }

    // Celda con los códigos de entidades de control de la cuenta (SRI, Supercias ESF/ERI/ECP).
    // Solo muestra los que están asignados; en cuentas sin ninguno queda vacía.
    function celdaEntidadesControl(item) {
        const partes = [];
        const add = (lbl, val, cls) => { if (val) partes.push(`<span class="badge ${cls} fw-normal" title="${lbl}">${lbl} ${val}</span>`); };
        add('SRI', item.codigo_sri, 'bg-secondary bg-opacity-10 text-secondary border');
        add('ESF', item.supercias_esf, 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25');
        add('ERI', item.supercias_eri, 'bg-success bg-opacity-10 text-success border border-success border-opacity-25');
        const ecp = item.supercias_ecp_codigo ? item.supercias_ecp_codigo + (item.supercias_ecp_subcodigo ? '.' + item.supercias_ecp_subcodigo : '') : '';
        add('ECP', ecp, 'bg-info bg-opacity-10 text-info border border-info border-opacity-25');
        return `<td class="td-ent-control">${partes.join(' ')}</td>`;
    }

    function generarCabecera(nivelFiltro) {
        const nf = parseInt(nivelFiltro);
        let html = `<thead><tr><th class="th-codigo">Código</th><th class="th-ent-control">Ent. control</th><th>Cuenta</th>`;
        for (let i = nf; i >= 1; i--) {
            html += `<th class="text-end th-valor">Nivel ${i}</th>`;
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
            ${celdaCodigo(item, esPadre, fwClass)}
            ${celdaEntidadesControl(item)}
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
        return `<tr class="${claseFila}"><td colspan="3" class="text-end ${colorClass}">${titulo}</td>${tdsNiveles}</tr>`;
    }

    function generarFilaGrupo(titulo, icono, nivelFiltro) {
        const cols = 3 + parseInt(nivelFiltro);
        return `<tr class="tr-grupo"><td colspan="${cols}"><i class="${icono} me-2"></i> ${titulo}</td></tr>`;
    }

    function generarFilaEspacio(nivelFiltro, h = 15) {
        const cols = 3 + parseInt(nivelFiltro);
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

    // ── Reportes horizontales "por periodos" (una columna por mes) ─────────────────────────

    function generarCabeceraPeriodos(periodos, conTotal) {
        let html = `<thead><tr><th class="th-codigo">Código</th><th class="th-ent-control">Ent. control</th><th>Cuenta</th>`;
        Object.values(periodos).forEach(lbl => { html += `<th class="text-end th-valor">${lbl}</th>`; });
        if (conTotal) html += `<th class="text-end th-valor">Total</th>`;
        return html + `</tr></thead><tbody>`;
    }

    function generarFilaPeriodos(item, periodosClaves, conTotal, nivelSeleccionado) {
        const nivelItem = parseInt(item.nivel);
        const nivelFiltro = parseInt(nivelSeleccionado);
        const esPadre = nivelItem < nivelFiltro;
        const indent = (nivelItem - 1) * 20;
        const fwClass = esPadre ? 'fw-bold text-dark' : '';

        let cursorClass = '';
        let onclickAttr = '';
        if (nivelItem === 5 && !esPadre) {
            cursorClass = 'text-primary cursor-pointer fw-medium';
            onclickAttr = `onclick="verMayorAuxiliar('${item.codigo}', '${item.nombre}')" style="cursor:pointer; text-decoration: underline;" title="Ver detalle del Mayor"`;
        }

        let tds = '';
        periodosClaves.forEach(p => { tds += `<td class="text-end ${fwClass}">${formatMoney(item.valores[p] || 0)}</td>`; });
        if (conTotal) tds += `<td class="text-end fw-bold ${fwClass}">${formatMoney(item.total || 0)}</td>`;

        return `<tr>
            ${celdaCodigo(item, esPadre, fwClass)}
            ${celdaEntidadesControl(item)}
            <td style="padding-left: ${15 + indent}px !important;" class="${fwClass} ${cursorClass}" ${onclickAttr}>${item.nombre}</td>
            ${tds}
        </tr>`;
    }

    function generarFilaTotalPeriodos(titulo, porPeriodo, periodosClaves, conTotal, claseFila = 'tr-total', colorClass = '') {
        let tds = '';
        periodosClaves.forEach(p => { tds += `<td class="text-end ${colorClass}">${formatMoney(porPeriodo[p] || 0)}</td>`; });
        if (conTotal) tds += `<td class="text-end ${colorClass}">${formatMoney(porPeriodo.total || 0)}</td>`;
        return `<tr class="${claseFila}"><td colspan="3" class="text-end ${colorClass}">${titulo}</td>${tds}</tr>`;
    }

    function generarFilaGrupoPeriodos(titulo, icono, periodosClaves, conTotal) {
        const cols = 3 + periodosClaves.length + (conTotal ? 1 : 0);
        return `<tr class="tr-grupo"><td colspan="${cols}"><i class="${icono} me-2"></i> ${titulo}</td></tr>`;
    }

    function generarFilaEspacioPeriodos(periodosClaves, conTotal, h = 15) {
        const cols = 3 + periodosClaves.length + (conTotal ? 1 : 0);
        return `<tr><td colspan="${cols}" style="height:${h}px; border:none;"></td></tr>`;
    }

    function renderResultadosPeriodos(data, nivel) {
        const claves = Object.keys(data.periodos);
        let html = '<table class="tabla-reporte">';
        html += generarCabeceraPeriodos(data.periodos, true);

        html += generarFilaGrupoPeriodos('INGRESOS', 'bi bi-arrow-up-right-circle text-success', claves, true);
        data.ingresos.forEach(item => { html += generarFilaPeriodos(item, claves, true, nivel); });
        html += generarFilaTotalPeriodos('TOTAL INGRESOS', data.totales.ingresos, claves, true);
        html += generarFilaEspacioPeriodos(claves, true);

        html += generarFilaGrupoPeriodos('COSTOS', 'bi bi-box text-warning', claves, true);
        data.costos.forEach(item => { html += generarFilaPeriodos(item, claves, true, nivel); });
        html += generarFilaTotalPeriodos('TOTAL COSTOS', data.totales.costos, claves, true, 'tr-total', 'text-dark');
        html += generarFilaEspacioPeriodos(claves, true, 10);

        const lblBruta = data.totales.utilidad_bruta.total >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA';
        html += generarFilaTotalPeriodos(lblBruta, data.totales.utilidad_bruta, claves, true, 'tr-total-general');
        html += generarFilaEspacioPeriodos(claves, true);

        html += generarFilaGrupoPeriodos('GASTOS', 'bi bi-arrow-down-right-circle text-danger', claves, true);
        data.gastos.forEach(item => { html += generarFilaPeriodos(item, claves, true, nivel); });
        html += generarFilaTotalPeriodos('TOTAL GASTOS', data.totales.gastos, claves, true, 'tr-total', 'text-danger');
        html += generarFilaEspacioPeriodos(claves, true);

        const lblNeta = data.totales.utilidad_neta.total >= 0 ? 'UTILIDAD DEL EJERCICIO' : 'PÉRDIDA DEL EJERCICIO';
        const classNeta = data.totales.utilidad_neta.total >= 0 ? 'text-success' : 'text-danger';
        html += generarFilaTotalPeriodos(lblNeta, data.totales.utilidad_neta, claves, true, 'tr-total-general', classNeta);

        html += '</tbody></table>';
        document.getElementById('content-reporte').innerHTML = html;
    }

    function renderSituacionPeriodos(data, nivel) {
        const claves = Object.keys(data.periodos);
        const ultimaClave = claves[claves.length - 1];
        const totalActivos = parseFloat(data.totales.activos[ultimaClave]) || 0;
        const totalPasivoPatrimonio = parseFloat(data.totales.pasivo_patrimonio[ultimaClave]) || 0;
        const diferencia = totalActivos - totalPasivoPatrimonio;
        const cuadra = Math.abs(diferencia) < 0.01;

        let html = '';
        if (!cuadra) {
            html += `<div class="alert alert-danger d-flex align-items-center shadow-sm mb-3" role="alert">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                <div>
                    <strong>El balance no cuadra con el principio de partida doble (último periodo: ${data.periodos[ultimaClave]}).</strong>
                    Activos (${formatMoney(totalActivos)}) ≠ Pasivo + Patrimonio (${formatMoney(totalPasivoPatrimonio)}) —
                    diferencia de <strong>${formatMoney(Math.abs(diferencia))}</strong>.
                </div>
            </div>`;
        }

        html += '<table class="tabla-reporte">';
        html += generarCabeceraPeriodos(data.periodos, false);

        html += generarFilaGrupoPeriodos('ACTIVOS', 'bi bi-bank text-primary', claves, false);
        data.activos.forEach(item => { html += generarFilaPeriodos(item, claves, false, nivel); });
        html += generarFilaTotalPeriodos('TOTAL ACTIVOS', data.totales.activos, claves, false);
        html += generarFilaEspacioPeriodos(claves, false);

        html += generarFilaGrupoPeriodos('PASIVOS', 'bi bi-credit-card text-warning', claves, false);
        data.pasivos.forEach(item => { html += generarFilaPeriodos(item, claves, false, nivel); });
        html += generarFilaTotalPeriodos('TOTAL PASIVOS', data.totales.pasivos, claves, false, 'tr-total', 'text-dark');
        html += generarFilaEspacioPeriodos(claves, false);

        html += generarFilaGrupoPeriodos('PATRIMONIO', 'bi bi-pie-chart text-info', claves, false);
        data.patrimonio.forEach(item => { html += generarFilaPeriodos(item, claves, false, nivel); });
        html += generarFilaTotalPeriodos('TOTAL PATRIMONIO', data.totales.patrimonio, claves, false, 'tr-total', 'text-dark');
        html += generarFilaEspacioPeriodos(claves, false);

        html += generarFilaTotalPeriodos('TOTAL PASIVO + PATRIMONIO', data.totales.pasivo_patrimonio, claves, false, 'tr-total-general', cuadra ? '' : 'text-danger');

        html += '</tbody></table>';
        document.getElementById('content-reporte').innerHTML = html;
    }

    // Vista previa del ECP (Supercias): matriz fila × columna con los filtros de pantalla.
    async function verEcp() {
        const fInicio = document.getElementById('fecha_inicio').value;
        const fFin = document.getElementById('fecha_fin').value;
        if (!fInicio || !fFin) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Por favor seleccione un rango de fechas válido.' });
            return;
        }
        const centro = document.getElementById('filtro_centro_costo').value;
        const proyecto = document.getElementById('filtro_proyecto').value;

        const modal = new bootstrap.Modal(document.getElementById('modalEcp'));
        document.getElementById('loader-ecp').classList.remove('d-none');
        document.getElementById('content-ecp').classList.add('d-none');
        modal.show();

        try {
            const params = new URLSearchParams({ fecha_inicio: fInicio, fecha_fin: fFin, centro_costo: centro, proyecto: proyecto });
            const res = await fetch(`${urlBase}/generarEcpAjax?${params.toString()}`).then(r => r.json());
            if (!res.success) { Swal.fire('Error', res.error || 'No se pudo calcular el ECP.', 'error'); modal.hide(); return; }
            renderEcp(res.data);
            document.getElementById('loader-ecp').classList.add('d-none');
            document.getElementById('content-ecp').classList.remove('d-none');
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de red o servidor al calcular el ECP.', 'error');
            modal.hide();
        }
    }

    function renderEcp(d) {
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const cols = Object.keys(d.columnas);
        const filasTotal = ['99', '9901', '9902'];
        const cell = (v, bold) => {
            const n = parseFloat(v) || 0;
            const cls = 'text-end text-nowrap' + (bold ? ' fw-bold' : '') + (n < 0 ? ' text-danger' : '');
            return `<td class="${cls}">${Math.abs(n) < 0.005 ? '' : formatMoney(n)}</td>`;
        };

        let html = '<table class="table table-sm table-bordered mb-0" style="font-size:.75rem;"><thead class="table-light"><tr>';
        html += '<th style="min-width:260px">Concepto</th><th>Código</th>';
        cols.forEach(c => { html += `<th class="text-end" title="${esc(d.columnas[c])}">${esc(c)}<br><span class="fw-normal text-muted" style="font-size:.65rem;">${esc(d.columnas[c])}</span></th>`; });
        html += '<th class="text-end">Total</th></tr></thead><tbody>';

        Object.keys(d.filas).forEach(f => {
            const esTotal = filasTotal.includes(f);
            const indent = f.length === 6 ? 'ps-4' : (f.length === 4 ? 'ps-2' : '');
            html += `<tr class="${esTotal ? 'table-light' : ''}"><td class="${indent} ${esTotal ? 'fw-bold' : ''}">${esc(d.filas[f])}</td><td class="text-muted">${f}</td>`;
            cols.forEach(c => { html += cell(d.valores[f] ? d.valores[f][c] : 0, esTotal); });
            html += cell(d.totales_fila[f], true) + '</tr>';
        });

        let hayDif = false;
        let difRow = '<tr class="table-warning"><td class="fw-bold">Diferencia (99 − 9901 − 9902)</td><td></td>';
        let difTotal = 0;
        cols.forEach(c => { const v = parseFloat(d.diferencias[c]) || 0; difTotal += v; if (Math.abs(v) >= 0.01) hayDif = true; difRow += cell(v, true); });
        difRow += cell(difTotal, true) + '</tr>';
        html += (hayDif ? difRow : difRow.replace('table-warning', 'table-success')) + '</tbody></table>';
        document.getElementById('ecp-matriz-wrap').innerHTML = html;

        const tb = document.querySelector('#tabla-ecp-detalle tbody');
        if (!d.detalle || d.detalle.length === 0) {
            tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Ninguna cuenta de patrimonio tiene asignada una columna ECP (campo <em>Supercias ECP Columna</em> en Plan de Cuentas).</td></tr>';
            return;
        }
        tb.innerHTML = d.detalle.map(x => `<tr>
            <td class="text-nowrap">${esc(x.codigo)}</td><td>${esc(x.nombre)}</td>
            <td>${esc(x.columna)} <span class="text-muted small">${esc(d.columnas[x.columna] || '')}</span></td>
            <td>${esc(x.fila_cambio)} <span class="text-muted small">${esc(d.filas[x.fila_cambio] || '')}</span></td>
            ${cell(x.saldo_inicial)}${cell(x.movimiento)}
        </tr>`).join('');
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

    // La columna Documento Ref. es un enlace al documento (factura, compra, egreso…) cuando el
    // asiento tiene uno identificado; si no, queda como texto.
    function docRefHtml(item) {
        const texto = item.documento_referencia || '';
        if (!item.modulo_documento || !item.id_documento) return texto;
        return `<a href="#" onclick="event.preventDefault(); DOCORIGEN_abrirModal('${item.modulo_documento}', ${item.id_documento});"
                   class="text-decoration-none" title="Ver el documento">${texto}</a>`;
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
                        <td>${docRefHtml(item)}</td>
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
<script src="<?= $base ?>/js/modulos/asientos_pendientes.js?v=<?= time() ?>"></script>

<!-- Modal reutilizable de Plan de Cuentas: lo abre el código de cada cuenta de nivel 5 del reporte.
     Necesita $centros y $proyectos (los envía el controlador). -->
<?php include __DIR__ . '/../plan_cuentas/modal.php'; ?>
<script>
    const PC_PUEDE_ACTUALIZAR = <?= !empty($permPlanCuentas['actualizar']) ? 'true' : 'false' ?>;
    const urlPlanCuentas = '<?= rtrim($base, '/') ?>/modulos/plan-cuentas';

    // Abre la ficha de la cuenta contable (código, nombre, estado, centro de costo, proyecto y
    // códigos SRI / Supercias). El código y el nivel nunca se editan; el resto solo si el usuario
    // tiene permiso de actualizar en Plan de Cuentas (lo confirma también el servidor).
    async function abrirCuentaContable(idCuenta) {
        try {
            const resp = await fetch(`${urlPlanCuentas}/getCuentaAjax?id=${idCuenta}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await resp.json();
            if (!json.ok) { Swal.fire('Error', json.error || 'No se pudo cargar la cuenta.', 'error'); return; }
            const puedeEditar = PC_PUEDE_ACTUALIZAR && !!(json.permisos && json.permisos.actualizar);
            abrirModalPCGeneral(json.data, { soloLectura: !puedeEditar });
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de red al cargar la cuenta.', 'error');
        }
    }

    // Tras guardar desde el modal, regenerar el reporte para reflejar el nombre/estado nuevos.
    window.onAccountSaved = function(json) {
        Swal.fire({ icon: 'success', title: 'Cuenta actualizada', text: json.msg || '', timer: 1500, showConfirmButton: false });
        if (document.getElementById('content-reporte').innerHTML.trim() !== '') generarReporte();
    };
</script>

<!-- Modal del documento origen: lo abre la columna Documento Ref. del mayor auxiliar -->
<?php include __DIR__ . '/../documento_origen/modal_documento.php'; ?>
<script>window.DOCORIGEN_URL = '<?= $urlBaseReporte ?>/getDocumentoOrigenAjax';</script>
<script src="<?= $base ?>/js/modulos/documento_origen_modal.js?v=<?= time() ?>"></script>

<script>
    // Apilar el modal del Asiento y el del Documento por encima del modal del Mayor (z-index).
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
