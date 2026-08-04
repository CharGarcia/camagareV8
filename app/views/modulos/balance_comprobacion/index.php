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
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold" style="font-family: 'Inter', sans-serif;"><i class="bi bi-calculator text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    </div>

    <!-- Filtros -->
    <div class="px-4 pb-3 pt-2 bg-light bg-opacity-50 border-bottom border-top">
        <form id="formFiltros" class="row g-2 align-items-end" onsubmit="event.preventDefault(); generarReporte();">
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
                <label class="form-label small fw-bold text-muted mb-1">Fecha Inicio</label>
                <input type="date" class="form-control form-control-sm shadow-none" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>" required>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Fecha Fin</label>
                <input type="date" class="form-control form-control-sm shadow-none" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>" required>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Nivel</label>
                <select class="form-select form-select-sm shadow-none" id="filtro_nivel">
                    <option value="1">1 - Mayor</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5" selected>5 - Detalle</option>
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
                <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm w-100" id="btnGenerar">
                    <i class="bi bi-search me-1"></i> Generar
                </button>
            </div>
        </form>
    </div>

    <!-- Buscador en pantalla + Exportación -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 bg-light px-3 py-2 border-bottom">
        <div class="position-relative" style="max-width: 320px; width: 100%;">
            <i class="bi bi-search position-absolute text-muted" style="left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; pointer-events: none;"></i>
            <input type="text" class="form-control form-control-sm shadow-none ps-4" id="buscadorTexto"
                   placeholder="Buscar en el reporte (código o nombre de cuenta...)"
                   autocomplete="off" oninput="filtrarEnPantalla()">
        </div>
        <div class="btn-group btn-group-sm shadow-sm">
            <button type="button" class="btn btn-white border px-3" title="Descargar PDF" onclick="exportar('pdf')">
                <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
            </button>
            <button type="button" class="btn btn-white border px-3" title="Descargar Excel" onclick="exportar('excel')">
                <i class="bi bi-file-earmark-excel text-success"></i> Excel
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
        <div id="alerta-cuadre" class="d-none"></div>
        <div id="content-reporte" class="table-responsive">
            <p class="text-muted text-center py-5 small"><i class="bi bi-info-circle me-1"></i> Seleccione el rango de fechas y presione Generar.</p>
        </div>
    </div>
</div>

<style>
    .tabla-reporte { width: 100%; border-collapse: collapse; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
    .tabla-reporte th { padding: 8px 10px; background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.72rem; }
    .tabla-reporte td { padding: 5px 10px; border-bottom: 1px solid #e9ecef; color: #212529; }
    .tabla-reporte tr:hover td { background-color: #f8f9fa; }
    .tr-total-general td { font-weight: 800; background-color: #f8f9fa; border-top: 2px solid #343a40; font-size: 0.95rem; }
</style>

<script>
    const urlBase = '<?= $urlBaseReporte ?>';

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
            const ultimoDia = new Date(anio, mes, 0).getDate();
            const ultimoDiaStr = ultimoDia.toString().padStart(2, '0');
            fFin = `${anio}-${mesStr}-${ultimoDiaStr}`;
        }

        document.getElementById('fecha_inicio').value = fInicio;
        document.getElementById('fecha_fin').value = fFin;
    }

    const formatMoney = (amount) => {
        const num = parseFloat(amount) || 0;
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // Fetch con el header que el backend usa para detectar peticiones AJAX (ver
    // PermisoModuloTrait::esAjaxRequest) y validación explícita de la respuesta,
    // para que un 403/redirect no falle en silencio como un JSON.parse roto.
    async function fetchJson(url) {
        const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const contentType = resp.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error(`Respuesta no-JSON (HTTP ${resp.status}) de ${url}`);
        }
        return resp.json();
    }

    function getFiltrosActuales() {
        return {
            fecha_inicio: document.getElementById('fecha_inicio').value,
            fecha_fin: document.getElementById('fecha_fin').value,
            nivel: document.getElementById('filtro_nivel').value,
            centro_costo: document.getElementById('filtro_centro_costo').value,
            proyecto: document.getElementById('filtro_proyecto').value,
        };
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

        try {
            document.getElementById('loader-reporte').classList.remove('d-none');
            document.getElementById('content-reporte').innerHTML = '';
            document.getElementById('alerta-cuadre').classList.add('d-none');

            const params = new URLSearchParams(getFiltrosActuales());
            const json = await fetchJson(`${urlBase}/generarAjax?${params.toString()}`);
            if (json.success) {
                renderBalance(json.data);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'Error al generar el reporte' });
            }
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor al generar el reporte: ' + e.message });
        } finally {
            document.getElementById('loader-reporte').classList.add('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Generar';
        }
    }

    function renderBalance(data) {
        const alerta = document.getElementById('alerta-cuadre');
        if (!data.cuadrado) {
            alerta.className = 'alert alert-warning py-2 px-3 small mb-3';
            alerta.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> El balance no cuadra (Debe ≠ Haber). Revise si hay asientos pendientes de generar, el rango de fechas o asientos descuadrados.';
        } else {
            alerta.className = 'alert alert-success py-2 px-3 small mb-3';
            alerta.innerHTML = '<i class="bi bi-check-circle me-1"></i> El balance cuadra.';
        }
        alerta.classList.remove('d-none');

        if (!data.cuentas || !data.cuentas.length) {
            document.getElementById('content-reporte').innerHTML =
                '<p class="text-muted text-center py-5 small"><i class="bi bi-info-circle me-1"></i> No hay movimientos con los filtros seleccionados.</p>';
            return;
        }

        let html = '<table class="tabla-reporte">';
        html += `<thead><tr>
                    <th width="12%">Código</th>
                    <th width="38%">Cuenta</th>
                    <th width="12%" class="text-end">Debe</th>
                    <th width="12%" class="text-end">Haber</th>
                    <th width="13%" class="text-end">Saldo Deudor</th>
                    <th width="13%" class="text-end">Saldo Acreedor</th>
                </tr></thead><tbody id="filas-balance">`;

        data.cuentas.forEach(cuenta => {
            html += `<tr class="fila-cuenta" data-texto="${(cuenta.codigo + ' ' + cuenta.nombre).toLowerCase()}">
                <td>${cuenta.codigo}</td>
                <td>${cuenta.nombre}</td>
                <td class="text-end">${formatMoney(cuenta.debe)}</td>
                <td class="text-end">${formatMoney(cuenta.haber)}</td>
                <td class="text-end">${formatMoney(cuenta.saldo_deudor)}</td>
                <td class="text-end">${formatMoney(cuenta.saldo_acreedor)}</td>
            </tr>`;
        });

        html += `</tbody><tbody><tr class="tr-total-general">
                    <td colspan="2" class="text-end">TOTALES</td>
                    <td class="text-end">${formatMoney(data.totales.debe)}</td>
                    <td class="text-end">${formatMoney(data.totales.haber)}</td>
                    <td class="text-end">${formatMoney(data.totales.saldo_deudor)}</td>
                    <td class="text-end">${formatMoney(data.totales.saldo_acreedor)}</td>
                </tr></tbody>`;

        html += '</table>';
        document.getElementById('content-reporte').innerHTML = html;

        // Si ya había texto escrito en el buscador (ej. el usuario generó otro reporte
        // sin borrar la búsqueda), reaplica el filtro sobre el nuevo contenido.
        filtrarEnPantalla();
    }

    // ── Buscador en pantalla: filtra sobre el reporte ya renderizado, sin ir al servidor ──
    function filtrarEnPantalla() {
        const inputEl = document.getElementById('buscadorTexto');
        if (!inputEl) return;
        const q = inputEl.value.trim().toLowerCase();
        const filas = document.querySelectorAll('#filas-balance tr.fila-cuenta');
        let algunaVisible = false;

        filas.forEach(tr => {
            const visible = !q || tr.dataset.texto.includes(q);
            tr.style.display = visible ? '' : 'none';
            if (visible) algunaVisible = true;
        });

        let msgVacio = document.getElementById('balanceSinResultadosBusqueda');
        if (q && filas.length && !algunaVisible) {
            if (!msgVacio) {
                msgVacio = document.createElement('p');
                msgVacio.id = 'balanceSinResultadosBusqueda';
                msgVacio.className = 'text-muted text-center py-4 small';
                msgVacio.innerHTML = '<i class="bi bi-search me-1"></i> Ninguna cuenta coincide con la búsqueda.';
                document.getElementById('content-reporte').appendChild(msgVacio);
            }
        } else if (msgVacio) {
            msgVacio.remove();
        }
    }

    function exportar(formato) {
        const filtros = getFiltrosActuales();
        if (!filtros.fecha_inicio || !filtros.fecha_fin) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Por favor seleccione un rango de fechas válido.' });
            return;
        }
        const params = new URLSearchParams(filtros);
        const accion = formato === 'pdf' ? 'exportPdf' : 'exportExcel';
        window.open(`${urlBase}/${accion}?${params.toString()}`, '_blank');
    }

    // ── Aviso de asientos pendientes de generar ─────────────────────────────────────
    // Al abrir el módulo se consulta cuántos documentos están sin asiento y se pregunta al
    // usuario si desea generarlos ahora o continuar sin generar. Si genera y ya había un
    // reporte en pantalla, se vuelve a generar para reflejar los asientos nuevos. Se difiere
    // a DOMContentLoaded porque el helper (asientos_pendientes.js) se carga al final del cuerpo.
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof window.CMG_verificarAsientosPendientes !== 'function') return;
        window.CMG_verificarAsientosPendientes({
            urlBase: urlBase,
            onGenerado: () => {
                const cont = document.getElementById('content-reporte');
                if (cont && cont.innerHTML.trim() && typeof generarReporte === 'function') generarReporte();
            }
        });
    });
</script>

<script>window.BASE_URL = '<?= $base ?>';</script>
<script src="<?= $base ?>/js/modulos/asientos_pendientes.js?v=<?= time() ?>"></script>
