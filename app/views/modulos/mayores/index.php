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
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .mayores-scroll { overflow-x:auto; }
    .tabla-reporte { width: 100%; border-collapse: collapse; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
    .tabla-reporte th { padding: 8px 10px; background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.72rem; }
    .tabla-reporte td { padding: 5px 10px; border-bottom: 1px solid #e9ecef; color: #212529; }
    .tabla-reporte tr:hover td { background-color: #f8f9fa; }
    .tr-grupo td { font-weight: bold; background-color: rgba(0,0,0,0.02); }
    .tr-total td { font-weight: bold; background-color: rgba(13, 110, 253, 0.05); color: #0d6efd; border-top: 2px solid #dee2e6; }
    .tr-total-general td { font-weight: 800; background-color: #f8f9fa; border-top: 2px solid #343a40; font-size: 0.95rem; }
    .monto-negativo { color: #dc3545; }
    /* Altura idéntica y explícita para todos los controles de filtros (selects, inputs, buscadores y botones),
       para que queden alineados sin depender de que cada variante -sm de Bootstrap renderice igual. */
    #formFiltros .form-select,
    #formFiltros .form-control,
    #formFiltros .input-group-text,
    #formFiltros .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-mayores .mayores-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-mayores">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
        </div>
        <div class="card-body p-3">
            <form id="formFiltros" onsubmit="event.preventDefault(); generarReporte();" class="d-flex flex-wrap align-items-start gap-2">

                <!-- Año -->
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                    <select class="form-select form-select-sm shadow-none border" id="filtro_anio" style="width:85px;" onchange="actualizarFechas()">
                        <?php foreach ($aniosDisponibles as $anio): ?>
                            <option value="<?= $anio ?>"><?= $anio ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Mes -->
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                    <select class="form-select form-select-sm shadow-none border" id="filtro_mes" style="width:115px;" onchange="actualizarFechas()">
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

                <!-- Fecha Inicio -->
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Inicio</label>
                    <input type="date" class="form-control form-control-sm shadow-none border" id="fecha_inicio" name="fecha_inicio" style="width:115px;"
                           value="<?= htmlspecialchars($fechaInicio) ?>" required>
                </div>

                <!-- Fecha Fin -->
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Fin</label>
                    <input type="date" class="form-control form-control-sm shadow-none border" id="fecha_fin" name="fecha_fin" style="width:115px;"
                           value="<?= htmlspecialchars($fechaFin) ?>" required>
                </div>

                <!-- Centro de costo y Proyecto: solo si la empresa tiene alguno activo -->
                <?php if (!empty($centrosCosto)): ?>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">C. Costo</label>
                    <select class="form-select form-select-sm shadow-none border" id="filtro_centro_costo" style="width:140px;">
                        <option value="">Todos</option>
                        <?php foreach ($centrosCosto ?? [] as $cc): ?>
                            <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['codigo'] . ' - ' . $cc['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (!empty($proyectos)): ?>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Proyecto</label>
                    <select class="form-select form-select-sm shadow-none border" id="filtro_proyecto" style="width:140px;">
                        <option value="">Todos</option>
                        <?php foreach ($proyectos ?? [] as $py): ?>
                            <option value="<?= $py['id'] ?>"><?= htmlspecialchars($py['codigo'] . ' - ' . $py['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Tipo de tercero -->
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Tipo Tercero</label>
                    <select class="form-select form-select-sm shadow-none border" id="filtro_tipo_entidad" style="width:115px;" onchange="onTipoEntidadChange()">
                        <option value="">Todos</option>
                        <option value="cliente">Cliente</option>
                        <option value="proveedor">Proveedor</option>
                        <option value="empleado">Empleado</option>
                    </select>
                </div>

                <!-- Cuenta + Tercero + Botones: un solo ítem flexible que ocupa el resto de la fila.
                     Los dos buscadores reparten el espacio sobrante y los botones van al final;
                     si no cabe, el grupo salta junto a la siguiente línea (nunca se separan). -->
                <div class="d-flex flex-wrap align-items-start gap-2" style="flex:1 1 520px;min-width:0;">
                    <!-- Buscador cuenta -->
                    <div class="position-relative" style="flex:1 1 200px;min-width:180px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Cuenta</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0 px-1 shadow-none" id="filtro_cuenta_texto"
                                   placeholder="Código o nombre" autocomplete="off">
                        </div>
                        <input type="hidden" id="filtro_cuenta_id" value="">
                        <div id="dropdown_cuenta" class="list-group position-absolute shadow-sm"
                             style="z-index:1050; max-height:220px; overflow:auto; display:none; width:100%; min-width:320px; margin-top:2px;"></div>
                    </div>

                    <!-- Buscador tercero (habilitado al elegir un tipo) -->
                    <div class="position-relative" style="flex:1 1 200px;min-width:180px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Tercero</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control border-start-0 px-1 shadow-none" id="filtro_tercero_texto"
                                   placeholder="Seleccione un tipo primero" autocomplete="off" disabled>
                        </div>
                        <input type="hidden" id="filtro_tercero_id" value="">
                        <div id="dropdown_tercero" class="list-group position-absolute shadow-sm"
                             style="z-index:1050; max-height:220px; overflow:auto; display:none; width:100%; min-width:320px; margin-top:2px;"></div>
                    </div>

                    <!-- Botones -->
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="limpiarFiltros()" title="Limpiar filtros">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-2 shadow-sm" id="btnGenerar" title="Generar el mayor">
                                <i class="bi bi-search me-1"></i> Generar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-journal-text bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="mayor-stat-cuentas">0</div>
                        <div class="cmg-control-card__stat-label">Cuentas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-list-ul bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="mayor-stat-movimientos">0</div>
                        <div class="cmg-control-card__stat-label">Movimientos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-arrow-down-circle bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success">$<span id="mayor-stat-debe">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Total Debe</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-arrow-up-circle bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger">$<span id="mayor-stat-haber">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Total Haber</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Reporte ── -->
    <div class="card border-0 shadow-sm rounded-3">
        <!-- Buscador en pantalla + Exportación -->
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-danger" title="Descargar PDF" onclick="exportar('pdf')">
                        <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                    </button>
                    <button type="button" class="btn btn-outline-success" title="Descargar Excel" onclick="exportar('excel')">
                        <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                    </button>
                </div>
                <input type="search" class="form-control form-control-sm shadow-none border" style="max-width:320px;"
                       id="buscadorMayorTexto" placeholder="Buscar en el reporte (cuenta, tercero, comprobante, glosa...)"
                       autocomplete="off" oninput="filtrarMayorEnPantalla()">
            </div>
        </div>

        <!-- Contenido del reporte -->
        <div class="card-body p-0">
            <div id="loader-reporte" class="text-center py-5 d-none">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="text-muted mt-2 small">Generando reporte...</p>
            </div>
            <div id="content-reporte" class="mayores-scroll w-100">
                <p class="text-muted text-center py-5 small"><i class="bi bi-info-circle me-1"></i> Seleccione el rango de fechas y presione Generar.</p>
            </div>
        </div>
    </div>
</div>

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
        // Redondear a centavos antes de mirar el signo: un residuo de coma flotante
        // (-0.0000001) o un -0 se mostraban como "-0.00" en rojo. "+ 0" convierte -0 en 0.
        const num = CMG_r2(amount) + 0;
        const formatted = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return num < 0 ? `<span class="monto-negativo">${formatted}</span>` : formatted;
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

    // ── Typeahead genérico (cuenta / tercero) ───────────────────────────────
    function setupTypeahead(inputEl, dropdownEl, hiddenEl, fetchFn, renderLabel) {
        let debounceTimer;
        // Con una selección activa (hiddenEl con valor), el input muestra una etiqueta fija
        // tipo "código - nombre": Backspace/Delete no debe editarla letra por letra, debe
        // limpiar la selección completa de una vez para volver a buscar desde cero.
        inputEl.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                e.preventDefault();
                hiddenEl.value = '';
                inputEl.value = '';
                dropdownEl.style.display = 'none';
                dropdownEl.innerHTML = '';
            }
        });
        inputEl.addEventListener('input', () => {
            hiddenEl.value = '';
            clearTimeout(debounceTimer);
            const q = inputEl.value.trim();
            if (q.length < 1) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
            debounceTimer = setTimeout(async () => {
                let items = [];
                try {
                    items = await fetchFn(q);
                } catch (e) {
                    console.error('Error buscando sugerencias:', e);
                    dropdownEl.innerHTML = '<span class="list-group-item text-danger small">Error al buscar. Revise la consola.</span>';
                    dropdownEl.style.display = 'block';
                    return;
                }
                if (!items || !items.length) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
                dropdownEl.innerHTML = items.map(it => {
                    const label = renderLabel(it);
                    return `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${it.id}" data-label="${label.replace(/"/g, '&quot;')}">${label}</a>`;
                }).join('');
                dropdownEl.style.display = 'block';
            }, 300);
        });
        dropdownEl.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-id]');
            if (!a) return;
            e.preventDefault();
            hiddenEl.value = a.dataset.id;
            inputEl.value = a.dataset.label;
            dropdownEl.style.display = 'none';
        });
        document.addEventListener('click', (e) => {
            if (e.target !== inputEl && !dropdownEl.contains(e.target)) dropdownEl.style.display = 'none';
        });
    }

    setupTypeahead(
        document.getElementById('filtro_cuenta_texto'),
        document.getElementById('dropdown_cuenta'),
        document.getElementById('filtro_cuenta_id'),
        async (q) => {
            const json = await fetchJson(`${urlBase}/getCuentasAjax?q=${encodeURIComponent(q)}`);
            return json.success ? json.data : [];
        },
        (it) => `${it.codigo} - ${it.nombre}`
    );

    const terceroEndpoints = {
        cliente: 'getClientesAjax',
        proveedor: 'getProveedoresAjax',
        empleado: 'getEmpleadosAjax',
    };

    setupTypeahead(
        document.getElementById('filtro_tercero_texto'),
        document.getElementById('dropdown_tercero'),
        document.getElementById('filtro_tercero_id'),
        async (q) => {
            const tipo = document.getElementById('filtro_tipo_entidad').value;
            if (!tipo) return [];
            const json = await fetchJson(`${urlBase}/${terceroEndpoints[tipo]}?q=${encodeURIComponent(q)}`);
            return json.success ? json.data : [];
        },
        (it) => it.identificacion ? `${it.nombre} (${it.identificacion})` : it.nombre
    );

    function onTipoEntidadChange() {
        const tipo = document.getElementById('filtro_tipo_entidad').value;
        const input = document.getElementById('filtro_tercero_texto');
        document.getElementById('filtro_tercero_id').value = '';
        input.value = '';
        if (tipo) {
            input.disabled = false;
            input.placeholder = 'Buscar...';
        } else {
            input.disabled = true;
            input.placeholder = 'Seleccione un tipo primero';
        }
    }

    function getFiltrosActuales() {
        return {
            fecha_inicio: document.getElementById('fecha_inicio').value,
            fecha_fin: document.getElementById('fecha_fin').value,
            id_cuenta: document.getElementById('filtro_cuenta_id').value,
            tipo_entidad: document.getElementById('filtro_tipo_entidad').value,
            id_entidad: document.getElementById('filtro_tercero_id').value,
            // Estos dos selects solo existen si la empresa tiene centros de costo / proyectos.
            centro_costo: document.getElementById('filtro_centro_costo')?.value || '',
            proyecto: document.getElementById('filtro_proyecto')?.value || '',
        };
    }

    async function generarReporte() {
        if (window.CMG_asientosPendientesBloqueado && window.CMG_asientosPendientesBloqueado()) return;
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

            const params = new URLSearchParams(getFiltrosActuales());
            const json = await fetchJson(`${urlBase}/generarAjax?${params.toString()}`);
            if (json.success) {
                renderMayor(json.data);
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

    // La columna Documento Ref. es un enlace al documento (factura, compra, egreso…) cuando el
    // asiento tiene uno identificado; si no, queda como texto.
    function docRefHtml(mov) {
        const texto = mov.documento_referencia || '';
        if (!mov.modulo_documento || !mov.id_documento) return texto;
        return `<a href="#" onclick="event.preventDefault(); DOCORIGEN_abrirModal('${mov.modulo_documento}', ${mov.id_documento});"
                   class="text-decoration-none" title="Ver el documento">${texto}</a>`;
    }

    // KPIs del pie de la tarjeta de control (se ponen en cero al limpiar o sin datos).
    function actualizarStats(data) {
        const cuentas = (data && data.cuentas) || [];
        const movs = cuentas.reduce((n, c) => n + (c.movimientos ? c.movimientos.length : 0), 0);
        const fmt = (v) => (parseFloat(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('mayor-stat-cuentas').textContent = cuentas.length;
        document.getElementById('mayor-stat-movimientos').textContent = movs;
        document.getElementById('mayor-stat-debe').textContent = fmt(data && data.totales ? data.totales.debe : 0);
        document.getElementById('mayor-stat-haber').textContent = fmt(data && data.totales ? data.totales.haber : 0);
    }

    function limpiarFiltros() {
        const form = document.getElementById('formFiltros');
        form.reset();
        // Los hidden no vuelven a vacío con reset(): su valor por defecto es el atributo, que cambia al asignarlo.
        document.getElementById('filtro_cuenta_id').value = '';
        document.getElementById('dropdown_cuenta').style.display = 'none';
        document.getElementById('dropdown_tercero').style.display = 'none';
        onTipoEntidadChange();
        document.getElementById('buscadorMayorTexto').value = '';
        document.getElementById('content-reporte').innerHTML =
            '<p class="text-muted text-center py-5 small"><i class="bi bi-info-circle me-1"></i> Seleccione el rango de fechas y presione Generar.</p>';
        actualizarStats(null);
    }

    function renderMayor(data) {
        actualizarStats(data);
        if (!data.cuentas || !data.cuentas.length) {
            document.getElementById('content-reporte').innerHTML =
                '<p class="text-muted text-center py-5 small"><i class="bi bi-info-circle me-1"></i> No hay movimientos con los filtros seleccionados.</p>';
            return;
        }

        let html = '<table class="tabla-reporte">';
        html += `<thead><tr>
                    <th width="9%">Fecha</th>
                    <th width="11%">Comprobante</th>
                    <th width="12%">Documento Ref.</th>
                    <th width="16%">Tercero</th>
                    <th width="24%">Glosa</th>
                    <th width="9%" class="text-end">Debe</th>
                    <th width="9%" class="text-end">Haber</th>
                    <th width="10%" class="text-end">Saldo</th>
                </tr></thead>`;

        data.cuentas.forEach(cuenta => {
            // Un <tbody> por cuenta (HTML permite varios) para poder mostrar/ocultar el
            // grupo completo al filtrar con el buscador, sin tocar el <thead>.
            html += `<tbody class="grupo-cuenta">`;
            html += `<tr class="tr-grupo"><td colspan="8"><i class="bi bi-journal-text me-2"></i> ${cuenta.codigo} - ${cuenta.nombre}</td></tr>`;

            cuenta.movimientos.forEach(mov => {
                const de = parseFloat(mov.debe) || 0;
                const ha = parseFloat(mov.haber) || 0;
                const glosa = mov.referencia_detalle || mov.concepto || '';
                html += `<tr class="fila-mov">
                    <td class="text-center">${mov.fecha_asiento}</td>
                    <td class="text-center"><a href="#" onclick="event.preventDefault(); ASIENTO_abrirModal(${mov.id_asiento});" class="text-decoration-none fw-bold" title="Ver asiento contable">${mov.numero_comprobante || 'S/N'}</a></td>
                    <td>${docRefHtml(mov)}</td>
                    <td>${mov.tercero || ''}</td>
                    <td><small>${glosa}</small></td>
                    <td class="text-end ${de > 0 ? 'text-dark' : 'text-muted'}">${formatMoney(de)}</td>
                    <td class="text-end ${ha > 0 ? 'text-dark' : 'text-muted'}">${formatMoney(ha)}</td>
                    <td class="text-end fw-bold">${formatMoney(mov.saldo_acumulado)}</td>
                </tr>`;
            });

            html += `<tr class="tr-total">
                        <td colspan="5" class="text-end">SUBTOTAL ${cuenta.codigo}</td>
                        <td class="text-end">${formatMoney(cuenta.subtotal_debe)}</td>
                        <td class="text-end">${formatMoney(cuenta.subtotal_haber)}</td>
                        <td class="text-end">${formatMoney(cuenta.saldo_final)}</td>
                    </tr>`;
            html += '<tr><td colspan="8" style="height:12px; border:none;"></td></tr>';
            html += '</tbody>';
        });

        html += `<tbody><tr class="tr-total-general">
                    <td colspan="5" class="text-end">TOTAL GENERAL</td>
                    <td class="text-end">${formatMoney(data.totales.debe)}</td>
                    <td class="text-end">${formatMoney(data.totales.haber)}</td>
                    <td></td>
                </tr></tbody>`;

        html += '</table>';
        document.getElementById('content-reporte').innerHTML = html;

        // Si ya había texto escrito en el buscador (ej. el usuario generó otro reporte
        // sin borrar la búsqueda), reaplica el filtro sobre el nuevo contenido.
        filtrarMayorEnPantalla();
    }

    // ── Buscador en pantalla: filtra sobre el reporte ya renderizado, sin ir al servidor ──
    function filtrarMayorEnPantalla() {
        const inputEl = document.getElementById('buscadorMayorTexto');
        if (!inputEl) return;
        const q = inputEl.value.trim().toLowerCase();
        const grupos = document.querySelectorAll('#content-reporte tbody.grupo-cuenta');
        let algunGrupoVisible = false;

        grupos.forEach(tbody => {
            if (!q) {
                tbody.style.display = '';
                tbody.querySelectorAll('tr.fila-mov').forEach(tr => { tr.style.display = ''; });
                algunGrupoVisible = true;
                return;
            }

            const nombreCuenta = (tbody.querySelector('tr.tr-grupo')?.textContent || '').toLowerCase();
            const coincideCuenta = nombreCuenta.includes(q);
            let algunaFilaVisible = false;

            tbody.querySelectorAll('tr.fila-mov').forEach(tr => {
                const visible = coincideCuenta || tr.textContent.toLowerCase().includes(q);
                tr.style.display = visible ? '' : 'none';
                if (visible) algunaFilaVisible = true;
            });

            const grupoVisible = coincideCuenta || algunaFilaVisible;
            tbody.style.display = grupoVisible ? '' : 'none';
            if (grupoVisible) algunGrupoVisible = true;
        });

        let msgVacio = document.getElementById('mayorSinResultadosBusqueda');
        if (q && grupos.length && !algunGrupoVisible) {
            if (!msgVacio) {
                msgVacio = document.createElement('p');
                msgVacio.id = 'mayorSinResultadosBusqueda';
                msgVacio.className = 'text-muted text-center py-4 small';
                msgVacio.innerHTML = '<i class="bi bi-search me-1"></i> Ningún movimiento coincide con la búsqueda.';
                document.getElementById('content-reporte').appendChild(msgVacio);
            }
        } else if (msgVacio) {
            msgVacio.remove();
        }
    }

    function exportar(formato) {
        if (window.CMG_asientosPendientesBloqueado && window.CMG_asientosPendientesBloqueado()) return;
        const filtros = getFiltrosActuales();
        if (!filtros.fecha_inicio || !filtros.fecha_fin) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Por favor seleccione un rango de fechas válido.' });
            return;
        }
        const params = new URLSearchParams(filtros);
        const accion = formato === 'pdf' ? 'exportPdf' : 'exportExcel';
        CMG_descargar(`${urlBase}/${accion}?${params.toString()}`);
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
            bloquear: ['#btnGenerar'],
            onGenerado: () => {
                const cont = document.getElementById('content-reporte');
                if (cont && cont.innerHTML.trim() && typeof generarReporte === 'function') generarReporte();
            }
        });
    });
</script>

<!-- Modal del Asiento Contable reutilizado para ver el detalle del comprobante -->
<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include __DIR__ . '/../asientos_contables/modal_asiento.php'; ?>
<script src="<?= $base ?>/js/modulos/asientos_contables_modal.js?v=<?= asset_ver('/js/modulos/asientos_contables_modal.js') ?>"></script>

<!-- Modal del documento origen: lo abre la columna Documento Ref. -->
<?php include __DIR__ . '/../documento_origen/modal_documento.php'; ?>
<script>window.DOCORIGEN_URL = '<?= $urlBaseReporte ?>/getDocumentoOrigenAjax';</script>
<script src="<?= $base ?>/js/modulos/documento_origen_modal.js?v=<?= asset_ver('/js/modulos/documento_origen_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/asientos_pendientes.js?v=<?= asset_ver('/js/modulos/asientos_pendientes.js') ?>"></script>
