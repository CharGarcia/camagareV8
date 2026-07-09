<?php
$base = BASE_URL;
/** @var array $empresasImport */
/** @var array $tipos */
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-cloud-download text-dark me-2"></i><?= htmlspecialchars($titulo) ?></h4>
        <p class="text-muted mb-0 small">Importa comprobantes electrónicos (XML autorizados) del sistema anterior, desde el servidor.</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver a Configuración</a>
</div>

<div class="row g-4">
    <!-- Paso 1: seleccionar y escanear -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-1-circle text-primary me-2"></i>Paso 1 · Escanear</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Empresa</label>
                    <select class="form-select" id="selEmpresa">
                        <option value="">-- Seleccione la empresa --</option>
                        <?php foreach ($empresasImport as $e): ?>
                            <option value="<?= (int)$e['id'] ?>" data-ruc="<?= htmlspecialchars($e['ruc']) ?>">
                                <?= htmlspecialchars($e['razon_social']) ?> (<?= htmlspecialchars($e['ruc']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="form-label fw-semibold small">Tipos de documento</label>
                <div class="row g-1 mb-3">
                    <?php foreach ($tipos as $cod => $label): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input chk-tipo" type="checkbox" value="<?= $cod ?>" id="tipo<?= $cod ?>" <?= $cod === '01' ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="tipo<?= $cod ?>"><?= htmlspecialchars($label) ?> (<?= $cod ?>)</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="btn btn-primary w-100" id="btnEscanear">
                    <i class="bi bi-search me-1"></i> Escanear servidor
                </button>
                <div class="form-text">Solo lee la lista de archivos (no descarga). Los ya importados se omiten.</div>
            </div>
        </div>
    </div>

    <!-- Paso 2: revisar e importar -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-2-circle text-primary me-2"></i>Paso 2 · Revisar e importar</h6>
            </div>
            <div class="card-body">
                <div id="zonaResumen" class="text-muted small py-4 text-center">
                    Escanea una empresa para ver los documentos disponibles.
                </div>

                <div id="zonaImportar" class="d-none">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Desde</label>
                            <input type="date" class="form-control form-control-sm" id="fDesde">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="fHasta">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Bloque</label>
                            <input type="number" class="form-control form-control-sm" id="fLimite" value="25" min="1" max="100" style="width:90px">
                        </div>
                        <div class="col-auto">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="fVerificar">
                                <label class="form-check-label small" for="fVerificar" title="Consulta el estado real de cada comprobante en el SRI antes de importarlo (más lento).">
                                    Verificar en el SRI
                                </label>
                            </div>
                        </div>
                        <div class="col text-end">
                            <button class="btn btn-success" id="btnImportar"><i class="bi bi-download me-1"></i> Importar</button>
                            <button class="btn btn-outline-danger d-none" id="btnDetener"><i class="bi bi-stop-circle me-1"></i> Detener</button>
                        </div>
                    </div>

                    <div class="progress mb-2" style="height:22px;">
                        <div id="barra" class="progress-bar progress-bar-striped" role="progressbar" style="width:0%">0%</div>
                    </div>
                    <div id="contadores" class="small text-muted"></div>
                    <div id="logImport" class="mt-2 small" style="max-height:180px;overflow:auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Herramienta: anular en lote por clave de acceso -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3 border-bottom-0">
        <h6 class="mb-0 fw-bold"><i class="bi bi-x-octagon text-danger me-2"></i>Anular en lote (comprobantes dados de baja en el SRI)</h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Sube un Excel/CSV con las <b>claves de acceso</b> (49 dígitos) de las facturas que fueron
            <b>anuladas en el SRI</b>. El sistema las marcará como <b>anuladas</b> (reversando cobros, asiento
            e inventario si los hubiera). Se usa la <b>empresa seleccionada arriba</b>.
        </p>
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-0">Archivo de claves (.xlsx / .csv)</label>
                <input type="file" class="form-control form-control-sm" id="fAnularArchivo" accept=".xlsx,.xls,.csv">
            </div>
            <div class="col-auto">
                <button class="btn btn-danger" id="btnAnular"><i class="bi bi-x-octagon me-1"></i> Anular en lote</button>
            </div>
        </div>
        <div id="zonaAnular" class="small mt-3"></div>
    </div>
</div>

<script>
(function () {
    const base = '<?= $base ?>';
    const $ = (id) => document.getElementById(id);
    let detener = false;

    const tiposSeleccionados = () =>
        Array.from(document.querySelectorAll('.chk-tipo:checked')).map(c => c.value);

    const nombreTipo = { '01':'Facturas','03':'Liquidaciones','04':'Notas de crédito','05':'Notas de débito','06':'Guías','07':'Retenciones' };
    const SEG_POR_DOC = 0.9; // ritmo medido (descarga FTP domina; SRI suma ~0.1s)

    function fmtTiempo(seg) {
        seg = Math.max(0, Math.round(seg));
        if (seg < 60) return seg + ' s';
        const m = Math.floor(seg / 60), s = seg % 60;
        if (m < 60) return m + ' min' + (s ? ' ' + s + ' s' : '');
        const h = Math.floor(m / 60);
        return h + ' h ' + (m % 60) + ' min';
    }

    function post(action, params) {
        const body = new URLSearchParams();
        body.append('action', action);
        for (const k in params) {
            if (Array.isArray(params[k])) params[k].forEach(v => body.append(k + '[]', v));
            else body.append(k, params[k]);
        }
        return fetch(base + '/config/importarAntiguo', { method: 'POST', body })
            .then(r => r.json());
    }

    // ── Escanear ──
    $('btnEscanear').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        if (!idEmpresa) { alert('Seleccione una empresa.'); return; }
        const tipos = tiposSeleccionados();
        if (!tipos.length) { alert('Seleccione al menos un tipo.'); return; }

        const btn = $('btnEscanear');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Escaneando...';
        try {
            const res = await post('escanear', { id_empresa: idEmpresa, tipos });
            if (!res.ok) throw new Error(res.mensaje || 'Error al escanear');
            pintarResumen(res.data);
        } catch (e) {
            $('zonaResumen').innerHTML = '<div class="alert alert-danger mb-0">' + e.message + '</div>';
        } finally {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-search me-1"></i> Escanear servidor';
        }
    });

    function pintarResumen(d) {
        let filas = (d.resumen || []).map(r =>
            `<tr><td>${nombreTipo[r.cod_doc] || r.cod_doc} (${r.cod_doc})</td>
                 <td class="text-end">${r.n}</td>
                 <td class="text-end text-success">${r.importados}</td>
                 <td class="text-end">${r.pendientes}</td></tr>`).join('');

        const pend = (d.resumen || []).reduce((a, r) => a + parseInt(r.pendientes || 0, 10), 0);
        const etaBase = fmtTiempo(pend * SEG_POR_DOC);
        const etaSri  = fmtTiempo(pend * (SEG_POR_DOC + 0.1));
        const eta = pend > 0
            ? `<div class="alert alert-secondary py-2 small mb-3">
                 <i class="bi bi-clock me-1"></i><b>${pend}</b> documento(s) pendiente(s) por importar ·
                 Tiempo estimado: <b>~${etaBase}</b>
                 <span class="text-muted">(con verificación SRI ~${etaSri})</span>
               </div>`
            : `<div class="alert alert-success py-2 small mb-3"><i class="bi bi-check2-circle me-1"></i>No hay documentos pendientes por importar.</div>`;

        $('zonaResumen').innerHTML =
            `<div class="alert alert-info py-2 small mb-3">
                Detectados <b>${d.detectados}</b> · Nuevos <b>${d.nuevos}</b> · Ya en registro <b>${d.ya_registrados}</b> (lote #${d.id_lote})
             </div>
             ${eta}
             <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Tipo</th><th class="text-end">Total</th><th class="text-end">Importados</th><th class="text-end">Pendientes</th></tr></thead>
                <tbody>${filas || '<tr><td colspan="4" class="text-center text-muted">Sin documentos</td></tr>'}</tbody>
             </table>`;
        $('zonaImportar').classList.remove('d-none');
        reset();
    }

    // ── Importar por bloques ──
    let tot = { procesados:0, importados:0, duplicados:0, omitidos:0, no_autorizados:0, errores:0 };
    function reset() { tot = { procesados:0, importados:0, duplicados:0, omitidos:0, no_autorizados:0, errores:0 }; $('barra').style.width='0%'; $('barra').textContent='0%'; $('contadores').textContent=''; $('logImport').innerHTML=''; }

    $('btnImportar').addEventListener('click', () => correr());
    $('btnDetener').addEventListener('click', () => { detener = true; });

    let startImport = 0;
    async function correr() {
        detener = false;
        startImport = Date.now();
        $('btnImportar').classList.add('d-none');
        $('btnDetener').classList.remove('d-none');
        const idEmpresa = $('selEmpresa').value;
        const tipos = tiposSeleccionados();
        const limite = $('fLimite').value || 25;
        const desde = $('fDesde').value, hasta = $('fHasta').value;
        const verificar = $('fVerificar').checked ? 1 : 0;

        let restantes = 1;
        while (restantes > 0 && !detener) {
            let res;
            try {
                res = await post('importar', { id_empresa: idEmpresa, tipos, limite, desde, hasta, verificar });
            } catch (e) { logLinea('Error de red: ' + e.message, 'danger'); break; }
            if (!res.ok) { logLinea(res.mensaje || 'Error', 'danger'); break; }
            const d = res.data;
            tot.procesados += d.procesados; tot.importados += d.importados;
            tot.duplicados += d.duplicados; tot.omitidos += d.omitidos;
            tot.no_autorizados += (d.no_autorizados || 0); tot.errores += d.errores;
            restantes = d.restantes;
            actualizar(restantes);
            if (d.procesados === 0) break;
        }
        $('btnImportar').classList.remove('d-none');
        $('btnDetener').classList.add('d-none');
        logLinea(detener ? 'Detenido por el usuario.' : 'Proceso finalizado.', detener ? 'warning' : 'success');
    }

    function actualizar(restantes) {
        const totalTrabajo = tot.procesados + restantes;
        const pct = totalTrabajo > 0 ? Math.round(tot.procesados / totalTrabajo * 100) : 100;
        $('barra').style.width = pct + '%'; $('barra').textContent = pct + '%';

        // ETA en vivo con el ritmo real observado
        const elapsed = (Date.now() - startImport) / 1000;
        const rate = tot.procesados > 0 ? elapsed / tot.procesados : SEG_POR_DOC;
        const etaTxt = restantes > 0 ? ` · Falta <b>~${fmtTiempo(restantes * rate)}</b>` : ' · <b>Listo</b>';

        $('contadores').innerHTML =
            `Procesados <b>${tot.procesados}</b> · Importados <b class="text-success">${tot.importados}</b> · `
            + `Duplicados <b>${tot.duplicados}</b> · No autorizados <b class="text-warning">${tot.no_autorizados}</b> · `
            + `Omitidos <b>${tot.omitidos}</b> · Errores <b class="text-danger">${tot.errores}</b> · `
            + `Restantes <b>${restantes}</b>${etaTxt}`;
        logLinea(`Bloque: +${tot.importados} importados, quedan ${restantes}`);
    }

    function logLinea(txt, tipo) {
        const c = tipo ? 'text-' + tipo : 'text-muted';
        $('logImport').insertAdjacentHTML('afterbegin', `<div class="${c}">• ${txt}</div>`);
    }

    // ── Anular en lote por clave de acceso ──
    $('btnAnular').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        if (!idEmpresa) { alert('Seleccione una empresa arriba.'); return; }
        const file = $('fAnularArchivo').files[0];
        if (!file) { alert('Seleccione el archivo con las claves de acceso.'); return; }
        if (!confirm('¿Anular las facturas cuyas claves están en el archivo? Los documentos quedarán marcados como anulados.')) return;

        const btn = $('btnAnular');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Anulando...';
        try {
            const body = new FormData();
            body.append('action', 'anular');
            body.append('id_empresa', idEmpresa);
            body.append('archivo', file);
            const res = await fetch(base + '/config/importarAntiguo', { method: 'POST', body }).then(r => r.json());
            if (!res.ok) throw new Error(res.mensaje || 'Error al anular');
            const d = res.data;
            $('zonaAnular').innerHTML =
                `<div class="alert alert-info py-2 mb-0">
                    Claves en archivo <b>${d.total}</b> · Anuladas <b class="text-success">${d.anuladas}</b> ·
                    Ya anuladas <b>${d.ya_anuladas}</b> · No encontradas <b class="text-warning">${d.no_encontradas}</b> ·
                    No soportadas <b>${d.no_soportado || 0}</b> · Errores <b class="text-danger">${d.errores}</b>
                 </div>`;
        } catch (e) {
            $('zonaAnular').innerHTML = '<div class="alert alert-danger py-2 mb-0">' + e.message + '</div>';
        } finally {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-x-octagon me-1"></i> Anular en lote';
        }
    });
})();
</script>
