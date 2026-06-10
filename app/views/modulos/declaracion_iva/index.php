<style>
    .sri-container { background: #fff; border: 1px solid #ccc; padding: 10px; font-family: 'Arial', sans-serif; overflow-y: auto; overflow-x: auto; max-height: 50vh; margin-bottom: 10px; }
    .sri-section-title { background: #333; color: white; padding: 3px 8px; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; border: 1px solid #000; }
    .casillero-tag { background: #eee; color: #000; border: 1px solid #999; padding: 0px 4px; font-weight: 700; font-size: 0.65rem; min-width: 30px; display: inline-block; text-align: center; border-radius: 1px; margin-right: 3px; }
    .val-cell { background: #fff; border: 1px solid #bbb; padding: 1px 4px; text-align: right; font-family: 'Courier New', monospace; font-weight: 700; font-size: 0.78rem; flex-grow: 1; min-height: 22px; }
    .sri-table td { padding: 2px 4px !important; vertical-align: middle; border: 1px solid #ccc; font-size: 0.7rem; }
    .sri-table .row-bold { background-color: #f2f2f2; font-weight: 700; }
    .nav-tabs .nav-link { font-weight: 700; font-size: 0.8rem; color: #555; }
    .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; }
</style>

<div class="container-fluid py-2">
    <!-- Título -->
    <div class="row mb-1 print-none">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h5 mb-0 text-dark fw-bold">Declaración de IVA (form 104 SRI)</h1>
                <p class="text-muted mb-0 small" style="font-size: 0.7rem;">Detalle de la declaración de IVA</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary btn-xs px-3" style="font-size: 0.65rem;" onclick="window.print()">
                    <i class="bi bi-printer-fill me-1"></i> IMPRIMIR
                </button>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-3 mt-1 cmg-table-card print-none">
        <div class="card-body p-2 text-center">
            <form id="formDeclaracion" class="row g-2 align-items-end justify-content-center">
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-uppercase text-muted mb-1" style="font-size: 0.6rem;">Período</label>
                    <div class="btn-group btn-group-sm w-100">
                        <input type="radio" class="btn-check" name="tipo_periodo" id="tipo_mensual" value="mensual" checked>
                        <label class="btn btn-outline-primary fw-bold" for="tipo_mensual">Mensual</label>
                        <input type="radio" class="btn-check" name="tipo_periodo" id="tipo_semestral" value="semestral">
                        <label class="btn btn-outline-primary fw-bold" for="tipo_semestral">Semestral</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-uppercase text-muted mb-1" style="font-size: 0.6rem;">Año</label>
                    <select name="anio" class="form-select form-select-sm border-0 bg-light fw-bold" id="anio">
                        <?php foreach ($anios as $a): ?><option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-uppercase text-muted mb-1" id="labelPeriodo" style="font-size: 0.6rem;">Mes</label>
                    <select name="periodo" class="form-select form-select-sm border-0 bg-light fw-bold" id="periodo"></select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold py-1">GENERAR</button>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña d-none print-none" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button" role="tab">Resumen 104</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="detalle-tab" data-bs-toggle="tab" data-bs-target="#detalle" type="button" role="tab">Detalle de Casilleros</button>
        </li>
    </ul>

    <div class="tab-content border-top bg-white p-3 d-none" id="myTabContent">
        <!-- Pestaña 1 -->
        <div class="tab-pane fade show active" id="resumen" role="tabpanel">
            <div id="formSRI" class="sri-container"></div>
        </div>
        
        <!-- Pestaña 2 -->
        <div class="tab-pane fade" id="detalle" role="tabpanel">
            <div id="accordionDetalle" class="accordion accordion-flush" style="max-height: 50vh; overflow-y: auto;"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formDeclaracion');
        const selPeriodo = document.getElementById('periodo');
        const labelPeriodo = document.getElementById('labelPeriodo');
        const formSRI = document.getElementById('formSRI');
        const tabsContainer = document.getElementById('myTab');
        const tabContent = document.getElementById('myTabContent');

        const meses = {
            '01': 'Enero', '02': 'Febrero', '03': 'Marzo', '04': 'Abril',
            '05': 'Mayo', '06': 'Junio', '07': 'Julio', '08': 'Agosto',
            '09': 'Septiembre', '10': 'Octubre', '11': 'Noviembre', '12': 'Diciembre'
        };
        const semestres = { '1': 'Primer Semestre', '2': 'Segundo Semestre' };

        const mesDefault = '<?= htmlspecialchars((string) $mes) ?>';

        function actualizarPeriodos() {
            const tipo = document.querySelector('input[name="tipo_periodo"]:checked').value;
            selPeriodo.innerHTML = '';
            if (tipo === 'mensual') {
                labelPeriodo.innerText = 'Mes';
                for (let [v, m] of Object.entries(meses)) selPeriodo.insertAdjacentHTML('beforeend', `<option value="${v}">${m}</option>`);
                if (meses[mesDefault]) selPeriodo.value = mesDefault;
            } else {
                labelPeriodo.innerText = 'Semestre';
                for (let [v, s] of Object.entries(semestres)) selPeriodo.insertAdjacentHTML('beforeend', `<option value="${v}">${s}</option>`);
            }
        }
        document.querySelectorAll('input[name="tipo_periodo"]').forEach(el => el.addEventListener('change', actualizarPeriodos));
        actualizarPeriodos();

        form.addEventListener('submit', e => {
            e.preventDefault();
            generar();
        });

        function generar() {
            const params = new URLSearchParams(new FormData(form)).toString() + '&sincronizar=1';
            tabsContainer.classList.remove('d-none');
            tabContent.classList.remove('d-none');
            formSRI.innerHTML = '<div class="text-center py-5 small text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><br>Sincronizando y generando reporte...</div>';
            document.getElementById('accordionDetalle').innerHTML = '<div class="text-center py-3">Cargando detalles...</div>';

            fetch(`<?= $base ?>/<?= $rutaModulo ?>/generar-ajax?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(res => res.json()).then(data => {
                if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                renderVentas(data.resumen_completo);
                renderDetalle(data.detalle_documentos);
            });
        }

        function renderVentas(resumenData) {
            const layout = resumenData.layout;
            const valores = resumenData.valores;

            let currentSeccion = '';
            let html = '';

            layout.forEach(r => {
                if (r.seccion !== currentSeccion) {
                    if (currentSeccion !== '') html += '</tbody></table></div>';
                    html += `<div class="sri-section-title mt-3">SECCIÓN: ${r.seccion}</div>`;
                    html += `<div class="table-responsive"><table class="table table-bordered table-sm sri-table align-middle w-100 mb-0" style="font-size: 0.8rem;">`;
                    html += `<thead class="table-light text-center">
                                <tr>
                                    <th style="width:40%;">Concepto</th>
                                    <th style="width:5%;">Cas.</th><th style="width:15%;">Valor Bruto</th>
                                    <th style="width:5%;">Cas.</th><th style="width:15%;">Valor Neto</th>
                                    <th style="width:5%;">Cas.</th><th style="width:15%;">Impuesto Gen.</th>
                                </tr>
                             </thead><tbody>`;
                    currentSeccion = r.seccion;
                }

                const marginLeft = r.indent > 0 ? (r.indent * 15) + 'px' : '0px';
                const rowClass = r.bold ? 'fw-bold text-dark bg-light' : '';
                const descFormatted = r.descripcion;
                
                if (r.tipo === 'titulo') {
                    html += `<tr class="${rowClass}"><td colspan="7" class="ps-2 py-2" style="padding-left: calc(0.5rem + ${marginLeft}) !important;">${descFormatted}</td></tr>`;
                } else {
                    const cBruto = r.casillero_bruto || '';
                    const vBruto = cBruto ? (parseFloat(valores[cBruto]) || 0) : null;
                    const cNeto = r.casillero_neto || '';
                    const vNeto = cNeto ? (parseFloat(valores[cNeto]) || 0) : null;
                    const cImp = r.casillero_impuesto || '';
                    const vImp = cImp ? (parseFloat(valores[cImp]) || 0) : null;

                    html += `<tr class="${rowClass}">
                        <td class="ps-2" style="padding-left: calc(0.5rem + ${marginLeft}) !important;">${descFormatted}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${cBruto ? '<b>'+cBruto+'</b>' : ''}</td>
                        <td class="text-end">${vBruto !== null ? vBruto.toLocaleString('en-US',{minimumFractionDigits:2}) : ''}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${cNeto ? '<b>'+cNeto+'</b>' : ''}</td>
                        <td class="text-end">${vNeto !== null ? vNeto.toLocaleString('en-US',{minimumFractionDigits:2}) : ''}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${cImp ? '<b>'+cImp+'</b>' : ''}</td>
                        <td class="text-end">${vImp !== null ? vImp.toLocaleString('en-US',{minimumFractionDigits:2}) : ''}</td>
                    </tr>`;
                }
            });
            if (currentSeccion !== '') html += '</tbody></table></div>';
            formSRI.innerHTML = html;
        }

        function renderDetalle(detalle) {
            let html = '';
            const accordionDetalle = document.getElementById('accordionDetalle');
            if (detalle.length === 0) {
                accordionDetalle.innerHTML = '<div class="text-center text-muted py-3">No hay documentos sincronizados.</div>';
                return;
            }

            // Agrupar por docNum
            const grupos = {};
            detalle.forEach(d => {
                const docNum = d.establecimiento ? `${d.establecimiento}-${d.punto_emision}-${d.secuencial}` : `ID: ${d.id_origen}`;
                const key = `${d.origen}_${docNum}`;
                if (!grupos[key]) {
                    grupos[key] = {
                        origen: d.origen,
                        docNum: docNum,
                        fecha: d.fecha,
                        entidad: d.entidad || '',
                        items: [],
                        total: 0
                    };
                }
                grupos[key].items.push(d);
                grupos[key].total += parseFloat(d.valor) || 0;
            });

            let i = 0;
            for (const key in grupos) {
                const g = grupos[key];
                const headerId = 'heading' + i;
                const collapseId = 'collapse' + i;
                
                // Agrupar items por concepto unificando Base e IVA
                const conceptosMap = {};
                g.items.forEach(d => {
                    let concepto = d.concepto || 'Sin concepto';
                    concepto = concepto.replace(/\s\((Base|IVA)\)$/i, '');
                    if (!conceptosMap[concepto]) conceptosMap[concepto] = [];
                    conceptosMap[concepto].push(d);
                });

                let filasHtml = '';
                for (const concepto in conceptosMap) {
                    const casillerosItems = conceptosMap[concepto];
                    
                    // Ordenar casilleros de menor a mayor
                    casillerosItems.sort((a, b) => parseInt(a.casillero) - parseInt(b.casillero));
                    
                    let badgesHtml = '';
                    casillerosItems.forEach(d => {
                        const val = parseFloat(d.valor) || 0;
                        const modBadge = d.editado_manualmente ? `<i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Editado Manualmente" style="font-size: 0.75rem;"></i>` : '';
                        
                        badgesHtml += `
                        <div class="d-inline-flex align-items-center bg-light border rounded px-2 py-1 me-2 mb-1" 
                             style="cursor: pointer; transition: all 0.2s ease;"
                             onmouseover="this.classList.add('shadow-sm', 'border-primary')"
                             onmouseout="this.classList.remove('shadow-sm', 'border-primary')"
                             onclick="editarCasillero(${d.id}, '${d.casillero}')" 
                             title="Clic para cambiar a qué casillero pertenece este valor">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 me-2">${d.casillero}</span>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;">${val.toLocaleString('en-US',{minimumFractionDigits:2})}</span>
                            ${modBadge}
                        </div>`;
                    });

                    filasHtml += `<tr>
                        <td class="text-start ps-3 fw-medium text-dark align-middle">${concepto}</td>
                        <td class="text-start pe-3 align-middle py-2">${badgesHtml}</td>
                    </tr>`;
                }

                // Determinar color por origen
                let colorOrigen = 'secondary';
                if (g.origen === 'facturas de venta') colorOrigen = 'primary';
                else if (g.origen === 'compras') colorOrigen = 'success';
                else if (g.origen === 'notas_credito') colorOrigen = 'warning';
                else if (g.origen === 'liquidaciones_compras') colorOrigen = 'info';

                html += `
                <div class="accordion-item border-0 mb-2 shadow-sm rounded">
                    <h2 class="accordion-header" id="${headerId}">
                        <button class="accordion-button collapsed py-3 rounded" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" style="background-color: #f8f9fa;">
                            <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-${colorOrigen} bg-opacity-10 text-${colorOrigen} border border-${colorOrigen} border-opacity-25 px-2 py-1 text-uppercase" style="font-size: 0.7rem;">${g.origen.replace(/_/g, ' ')}</span>
                                    <span class="fw-bold text-dark font-monospace" style="font-size: 0.85rem;">#${g.docNum}</span>
                                    <span class="fw-medium text-secondary" style="font-size: 0.85rem;"><i class="bi bi-person-fill text-muted me-1"></i>${g.entidad ? g.entidad : 'Sin entidad asignada'}</span>
                                    <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>${new Date(g.fecha).toLocaleDateString('es-ES', {day: '2-digit', month: 'short', year: 'numeric'})}</span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="${collapseId}" class="accordion-collapse collapse" data-bs-parent="#accordionDetalle">
                        <div class="accordion-body p-3 bg-white border border-top-0 rounded-bottom">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.8rem;">
                                    <thead class="table-light text-start border-bottom">
                                        <tr>
                                            <th class="text-start ps-3 text-muted fw-semibold" style="width: 40%;">Concepto del Valor</th>
                                            <th class="text-muted fw-semibold">Casilleros Reportados <small class="text-primary fw-normal ms-2">(Clic en un casillero para editarlo)</small></th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">${filasHtml}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>`;
                i++;
            }
            accordionDetalle.innerHTML = html;
        }

        window.editarCasillero = function(id, casilleroActual) {
            Swal.fire({
                title: 'Editar Casillero',
                input: 'text',
                inputLabel: 'Ingresa el nuevo código de casillero para este valor',
                inputValue: casilleroActual,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    if (!value) return 'El casillero no puede estar vacío';
                    if (!/^[0-9]{3}$/.test(value)) return 'El casillero debe ser de 3 dígitos numéricos';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('id', id);
                    fd.append('casillero', result.value);

                    fetch(`<?= $base ?>/<?= $rutaModulo ?>/actualizar-casillero-ajax`, {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.ok) {
                            Swal.fire({ title: 'Éxito', text: 'Casillero actualizado.', icon: 'success', timer: 1500, showConfirmButton: false });
                            generar(); // Recargar datos
                        } else {
                            Swal.fire('Error', data.mensaje, 'error');
                        }
                    });
                }
            });
        };
    });
</script>
