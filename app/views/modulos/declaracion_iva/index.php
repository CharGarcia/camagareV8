<style>
    .sri-container {
        background: #fff;
        border: 1px solid #ccc;
        padding: 10px;
        font-family: 'Arial', sans-serif;
    }

    .sri-section-title {
        background: #333;
        color: white;
        padding: 3px 8px;
        font-weight: 700;
        font-size: 0.72rem;
        text-transform: uppercase;
        border: 1px solid #000;
    }

    .sri-sub-title {
        background: #eee;
        color: #444;
        padding: 2px 8px;
        font-weight: 800;
        font-size: 0.68rem;
        text-transform: uppercase;
        border: 1px solid #ccc;
        border-top: 0;
    }

    .casillero-tag {
        background: #eee;
        color: #000;
        border: 1px solid #999;
        padding: 0px 4px;
        font-weight: 700;
        font-size: 0.65rem;
        min-width: 30px;
        display: inline-block;
        text-align: center;
        border-radius: 1px;
        margin-right: 3px;
    }

    .val-cell {
        background: #fff;
        border: 1px solid #bbb;
        padding: 1px 4px;
        text-align: right;
        font-family: 'Courier New', monospace;
        font-weight: 700;
        font-size: 0.78rem;
        flex-grow: 1;
        min-height: 22px;
    }

    .sri-table td {
        padding: 2px 4px !important;
        vertical-align: middle;
        border: 1px solid #ccc;
        font-size: 0.7rem;
    }

    .sri-table .row-bold {
        background-color: #f2f2f2;
        font-weight: 700;
    }

    .sri-header-row {
        background: #ddd;
        font-weight: 800;
        color: #333;
        font-size: 0.6rem;
        text-transform: uppercase;
        text-align: center;
    }
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
                <button type="button" class="btn btn-dark btn-xs px-3" style="font-size: 0.65rem;" onclick="window.print()">
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
                        <label class="btn btn-outline-dark fw-bold" for="tipo_mensual">Mensual</label>
                        <input type="radio" class="btn-check" name="tipo_periodo" id="tipo_semestral" value="semestral">
                        <label class="btn btn-outline-dark fw-bold" for="tipo_semestral">Semestral</label>
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
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold py-1">PROCESAR</button>
                </div>
                <div class="col-md-2">
                    <button type="button" id="btnSinc" class="btn btn-outline-success btn-sm w-100 fw-bold py-1 d-none">SINCRONIZAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alert Auditoría -->
    <div id="integridadAlert" class="alert alert-danger border-0 d-none mb-2 p-1 small print-none" style="font-size: 0.65rem;">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><b>AUDITORÍA:</b> Descuadres detectados. <a href="#" onclick="verAuditoria()" class="alert-link">Ver detalles</a>
    </div>

    <!-- Contenedor del Formulario -->
    <div id="formSRI" class="sri-container d-none"></div>
</div>

<!-- Modal Auditoría -->
<div class="modal fade" id="modalAudit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white py-1">
                <h6 class="modal-title fw-bold small">Descuadres Encontrados</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-hover mb-0 fw-bold" style="font-size: 0.65rem;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-2">FACTURA</th>
                            <th>FECHA</th>
                            <th class="text-end">BASE+IVA</th>
                            <th class="text-end">REGISTRADO</th>
                            <th class="text-end pe-2 text-danger">DIF.</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyAudit"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formDeclaracion');
        const selPeriodo = document.getElementById('periodo');
        const labelPeriodo = document.getElementById('labelPeriodo');
        const formSRI = document.getElementById('formSRI');
        const alertInt = document.getElementById('integridadAlert');
        const btnSinc = document.getElementById('btnSinc');

        const meses = {
            '01': 'Enero',
            '02': 'Febrero',
            '03': 'Marzo',
            '04': 'Abril',
            '05': 'Mayo',
            '06': 'Junio',
            '07': 'Julio',
            '08': 'Agosto',
            '09': 'Septiembre',
            '10': 'Octubre',
            '11': 'Noviembre',
            '12': 'Diciembre'
        };
        const semestres = {
            '1': 'Primer Semestre',
            '2': 'Segundo Semestre'
        };

        function actualizarPeriodos() {
            const tipo = document.querySelector('input[name="tipo_periodo"]:checked').value;
            selPeriodo.innerHTML = '';
            if (tipo === 'mensual') {
                labelPeriodo.innerText = 'Mes';
                for (let [v, m] of Object.entries(meses)) selPeriodo.insertAdjacentHTML('beforeend', `<option value="${v}">${m}</option>`);
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
        btnSinc.addEventListener('click', sincronizar);
        window.verAuditoria = () => new bootstrap.Modal(document.getElementById('modalAudit')).show();

        function generar() {
            const params = new URLSearchParams(new FormData(form)).toString();
            formSRI.innerHTML = '<div class="text-center py-5 small text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><br>Cargando reporte de ventas...</div>';
            formSRI.classList.remove('d-none');
            alertInt.classList.add('d-none');
            btnSinc.classList.add('d-none');

            fetch(`<?= $base ?>/<?= $rutaModulo ?>/auditar-ajax?${params}`).then(res => res.json()).then(data => {
                if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                renderVentas(data.estructura, data.resumen);
                renderAudit(data.descuadres);
            });
        }

        function renderVentas(estructura, resumen) {
            const map = {};
            resumen.forEach(r => map[r.casillero] = parseFloat(r.total));

            // Estructura de Triplets (Section 400)
            const rubrosVentas = [{
                    desc: 'Ventas locales tarifa diferente de cero',
                    c: ['401', '411', '421']
                },
                {
                    desc: 'Ventas de activos fijos tarifa diferente de cero',
                    c: ['402', '412', '422']
                },
                {
                    desc: 'Ventas locales 0% (sin derecho a crédito)',
                    c: ['403', '413', null]
                },
                {
                    desc: 'Ventas locales 0% (con derecho a crédito)',
                    c: ['405', '415', null]
                },
                {
                    desc: 'Exportaciones de bienes',
                    c: ['407', '417', null]
                },
                {
                    desc: 'Exportaciones de servicios y/o derechos',
                    c: ['408', '418', null]
                },
                {
                    desc: 'Transferencias no objeto o exentas de IVA',
                    c: ['431', '441', null]
                },
                {
                    desc: 'Ventas de activos fijos gravadas diferente 0% (Simplificado)',
                    c: ['409', '419', null]
                },
                {
                    desc: 'Notas de crédito por compensar el próximo mes',
                    c: ['442', '443', null]
                }
            ];

            let html = `
            <div class="sri-section-title">1. RESUMEN DE VENTAS Y OTRAS OPERACIONES DEL PERÍODO QUE SE DECLARA</div>
            <table class="table sri-table w-100 mb-0">
                <tr class="sri-header-row"><th style="width: 46%; text-align: left;">RUBRO</th><th colspan="2" style="width: 18%;">VALOR BRUTO</th><th colspan="2" style="width: 18%;">VALOR NETO</th><th colspan="2" style="width: 18%;">IMPUESTO GENERADO</th></tr>
        `;
            rubrosVentas.forEach(f => {
                html += `<tr><td class="ps-2">${f.desc}</td>`;
                f.c.forEach(casId => {
                    if (casId) {
                        html += `<td class="text-center" style="width: 4%; border-right: 0;"><span class="casillero-tag">${casId}</span></td><td class="text-end" style="width: 14%; border-left: 0;"><div class="val-cell">$${(map[casId]||0).toLocaleString('en-US',{minimumFractionDigits:2})}</div></td>`;
                    } else {
                        html += `<td colspan="2" class="bg-light bg-opacity-10"></td>`;
                    }
                });
                html += `</tr>`;
            });
            html += '</table>';

            // 2. LIQUIDACIÓN DEL IVA EN EL MES
            const rubrosLiq = [{
                    desc: 'Total transferencias gravadas tarifa diferente de cero a contado este mes',
                    c: '480'
                },
                {
                    desc: 'Total transferencias gravadas tarifa diferente de cero a crédito este mes',
                    c: '481'
                },
                {
                    desc: 'Total impuesto generado',
                    c: '429',
                    bold: true
                },
                {
                    desc: 'Impuesto a liquidar del mes anterior',
                    c: '483'
                },
                {
                    desc: 'Impuesto a liquidar en este mes',
                    c: '484',
                    bold: true
                },
                {
                    desc: 'Impuesto a liquidar en el próximo mes',
                    c: '485'
                }
            ];
            html += `<div class="sri-sub-title">LIQUIDACIÓN DEL IVA EN EL MES</div><table class="table sri-table w-100 mb-0">`;
            rubrosLiq.forEach(f => {
                const val = map[f.c] || 0;
                html += `<tr class="${f.bold?'row-bold':''}"><td class="ps-2">${f.desc}</td><td class="text-center" style="width: 4%; border-right: 0;"><span class="casillero-tag">${f.c}</span></td><td class="text-end" style="width: 20%; border-left: 0;"><div class="val-cell">$${val.toLocaleString('en-US',{minimumFractionDigits:2})}</div></td></tr>`;
            });
            html += `</table>`;

            // 3. TOTAL IMPUESTO A LIQUIDAR
            html += `
            <div class="sri-sub-title">TOTAL IMPUESTO A LIQUIDAR EN ESTE MES</div>
            <table class="table sri-table w-100 mb-0">
                <tr class="row-bold text-dark"><td class="ps-2">TOTAL IMPUESTO A LIQUIDAR EN ESTE MES</td><td class="text-center" style="width: 4%; border-right: 0;"><span class="casillero-tag">499</span></td><td class="text-end" style="width: 20%; border-left: 0;"><div class="val-cell">$${(map['499']||0).toLocaleString('en-US',{minimumFractionDigits:2})}</div></td></tr>
            </table>
        `;

            // 4. CONTROL DOCUMENTAL (INFORMACIÓN)
            const info = [{
                    d: 'C.V. Emitidos',
                    c: '111',
                    d2: 'C.V. Anulados',
                    c2: '113'
                },
                {
                    d: 'Ret. Emitidas',
                    c: '115',
                    d2: 'Ret. Anuladas',
                    c2: '117'
                },
                {
                    d: 'N.C. Emitidas',
                    c: '119',
                    d2: 'N.C. Anuladas',
                    c2: '121'
                },
                {
                    d: 'N.D. Emitidas',
                    c: '123',
                    d2: 'N.D. Anuladas',
                    c2: '125'
                },
                {
                    d: 'Guías Emitidas',
                    c: '127',
                    d2: 'Guías Anuladas',
                    c2: '129'
                }
            ];
            html += `<div class="sri-sub-title">CONTROL DOCUMENTAL (INFORMATIVO)</div><table class="table sri-table w-100 mb-0">`;
            info.forEach(f => {
                html += `<tr>
                <td class="ps-2" style="width: 25%">${f.d}</td><td class="text-center" style="width: 5%"><span class="casillero-tag">${f.c}</span></td><td class="text-end" style="width: 20%"><div class="val-cell">${(map[f.c]||0)}</div></td>
                <td class="ps-2" style="width: 25%">${f.d2}</td><td class="text-center" style="width: 5%"><span class="casillero-tag">${f.c2}</span></td><td class="text-end" style="width: 20%"><div class="val-cell">${(map[f.c2]||0)}</div></td>
            </tr>`;
            });
            html += `</table>`;

            formSRI.innerHTML = html;
        }

        function renderAudit(descuadres) {
            const tb = document.getElementById('tbodyAudit');
            tb.innerHTML = '';
            if (descuadres.length > 0) {
                alertInt.classList.remove('d-none');
                btnSinc.classList.remove('d-none');
                descuadres.forEach(d => {
                    const diff = Math.abs(parseFloat(d.total_esperado_total) - parseFloat(d.total_registrado_casilleros));
                    tb.insertAdjacentHTML('beforeend', `<tr><td class="ps-2">${d.establecimiento}-${d.punto_emision}-${d.secuencial}</td><td>${new Date(d.fecha_emision).toLocaleDateString()}</td><td class="text-end">$${parseFloat(d.total_esperado_total).toFixed(2)}</td><td class="text-end">$${parseFloat(d.total_registrado_casilleros).toFixed(2)}</td><td class="text-end pe-2 text-danger">$${diff.toFixed(2)}</td></tr>`);
                });
            }
        }

        function sincronizar() {
            btnSinc.disabled = true;
            fetch(`<?= $base ?>/<?= $rutaModulo ?>/sincronizar-ajax`, {
                    method: 'POST',
                    body: new FormData(form)
                })
                .then(res => res.json()).then(data => {
                    Swal.fire(data.ok ? 'Éxito' : 'Error', data.mensaje, data.ok ? 'success' : 'error');
                    if (data.ok) generar();
                })
                .finally(() => btnSinc.disabled = false);
        }
    });
</script>