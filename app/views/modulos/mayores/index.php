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
        <h5 class="mb-0 fw-bold" style="font-family: 'Inter', sans-serif;"><i class="bi bi-journal-text text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    </div>

    <!-- Filtros -->
    <div class="px-4 pb-3 pt-2 bg-light bg-opacity-50 border-bottom border-top">
        <form id="formFiltros" class="row g-2 align-items-end" onsubmit="event.preventDefault(); generarReporte();">
            <div class="col position-relative">
                <label class="form-label small fw-bold text-muted mb-1">Cuenta</label>
                <input type="text" class="form-control form-control-sm shadow-none" id="filtro_cuenta_texto" placeholder="Código o nombre" autocomplete="off">
                <input type="hidden" id="filtro_cuenta_codigo" value="">
                <div id="dropdown_cuenta" class="list-group position-absolute shadow-sm" style="z-index:1050; max-height:220px; overflow:auto; display:none; width:100%;"></div>
            </div>
            <div class="col">
                <label class="form-label small fw-bold text-muted mb-1">Tipo Tercero</label>
                <select class="form-select form-select-sm shadow-none" id="filtro_tipo_entidad" onchange="onTipoEntidadChange()">
                    <option value="">Todos</option>
                    <option value="cliente">Cliente</option>
                    <option value="proveedor">Proveedor</option>
                    <option value="empleado">Empleado</option>
                </select>
            </div>
            <div class="col position-relative">
                <label class="form-label small fw-bold text-muted mb-1">Tercero</label>
                <input type="text" class="form-control form-control-sm shadow-none" id="filtro_tercero_texto" placeholder="Seleccione un tipo primero" autocomplete="off" disabled>
                <input type="hidden" id="filtro_tercero_id" value="">
                <div id="dropdown_tercero" class="list-group position-absolute shadow-sm" style="z-index:1050; max-height:220px; overflow:auto; display:none; width:100%;"></div>
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

<style>
    .tabla-reporte { width: 100%; border-collapse: collapse; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
    .tabla-reporte th { padding: 8px 10px; background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.72rem; }
    .tabla-reporte td { padding: 5px 10px; border-bottom: 1px solid #e9ecef; color: #212529; }
    .tabla-reporte tr:hover td { background-color: #f8f9fa; }
    .tr-grupo td { font-weight: bold; background-color: rgba(0,0,0,0.02); }
    .tr-total td { font-weight: bold; background-color: rgba(13, 110, 253, 0.05); color: #0d6efd; border-top: 2px solid #dee2e6; }
    .tr-total-general td { font-weight: 800; background-color: #f8f9fa; border-top: 2px solid #343a40; font-size: 0.95rem; }
    .monto-negativo { color: #dc3545; }
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
        const formatted = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return num < 0 ? `<span class="monto-negativo">${formatted}</span>` : formatted;
    };

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
                const items = await fetchFn(q);
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
        document.getElementById('filtro_cuenta_codigo'),
        async (q) => {
            const resp = await fetch(`${urlBase}/getCuentasAjax?q=${encodeURIComponent(q)}`);
            const json = await resp.json();
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
            const resp = await fetch(`${urlBase}/${terceroEndpoints[tipo]}?q=${encodeURIComponent(q)}`);
            const json = await resp.json();
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
            codigo_cuenta: document.getElementById('filtro_cuenta_codigo').value,
            tipo_entidad: document.getElementById('filtro_tipo_entidad').value,
            id_entidad: document.getElementById('filtro_tercero_id').value,
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

            const params = new URLSearchParams(getFiltrosActuales());
            const resp = await fetch(`${urlBase}/generarAjax?${params.toString()}`);
            const json = await resp.json();
            if (json.success) {
                renderMayor(json.data);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'Error al generar el reporte' });
            }
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor al generar el reporte.' });
        } finally {
            document.getElementById('loader-reporte').classList.add('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Generar';
        }
    }

    function renderMayor(data) {
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
                </tr></thead><tbody>`;

        data.cuentas.forEach(cuenta => {
            html += `<tr class="tr-grupo"><td colspan="8"><i class="bi bi-journal-text me-2"></i> ${cuenta.codigo} - ${cuenta.nombre}</td></tr>`;

            cuenta.movimientos.forEach(mov => {
                const de = parseFloat(mov.debe) || 0;
                const ha = parseFloat(mov.haber) || 0;
                const glosa = mov.referencia_detalle || mov.concepto || '';
                html += `<tr>
                    <td class="text-center">${mov.fecha_asiento}</td>
                    <td class="text-center"><a href="#" onclick="event.preventDefault(); ASIENTO_abrirModal(${mov.id_asiento});" class="text-decoration-none fw-bold" title="Ver asiento contable">${mov.numero_comprobante || 'S/N'}</a></td>
                    <td>${mov.documento_referencia || ''}</td>
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
        });

        html += `<tr class="tr-total-general">
                    <td colspan="5" class="text-end">TOTAL GENERAL</td>
                    <td class="text-end">${formatMoney(data.totales.debe)}</td>
                    <td class="text-end">${formatMoney(data.totales.haber)}</td>
                    <td></td>
                </tr>`;

        html += '</tbody></table>';
        document.getElementById('content-reporte').innerHTML = html;
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
</script>

<!-- Modal del Asiento Contable reutilizado para ver el detalle del comprobante -->
<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include __DIR__ . '/../asientos_contables/modal_asiento.php'; ?>
<script src="<?= $base ?>/js/modulos/asientos_contables_modal.js?v=<?= time() ?>"></script>
