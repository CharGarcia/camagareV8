<?php
$base = BASE_URL;
/** @var array $empresasMigrar */
/** @var array $entidades */
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-database-down text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h4>
        <p class="text-muted mb-0 small">Conecta a la base MySQL del sistema anterior, revisa el resumen por empresa y migra la información al sistema nuevo.</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver a Configuración</a>
</div>

<div class="row g-4">
    <!-- Paso 1: empresa + qué extraer -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-1-circle text-primary me-2"></i>Empresa y datos a extraer</h6>
                <button class="btn btn-sm btn-outline-secondary" id="btnProbar" title="Probar conexión a la base anterior">
                    <i class="bi bi-plug"></i> Probar conexión
                </button>
            </div>
            <div class="card-body">
                <div id="estadoConexion" class="small mb-3"></div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Empresa (destino)</label>
                    <div class="position-relative">
                        <input type="text" class="form-control" id="selEmpresaBuscar" autocomplete="off"
                               placeholder="Buscar por nombre, RUC o establecimiento…">
                        <input type="hidden" id="selEmpresa">
                        <div id="ddEmpresas" class="list-group position-absolute w-100 shadow-sm"
                             style="max-height:260px;overflow:auto;z-index:1050;display:none;">
                            <?php foreach ($empresasMigrar as $e):
                                $est = str_pad((string)($e['establecimiento'] ?? ''), 3, '0', STR_PAD_LEFT);
                                $busq = mb_strtolower(($e['razon_social'] ?? '') . ' ' . ($e['ruc'] ?? '') . ' ' . $est, 'UTF-8');
                            ?>
                                <button type="button" class="list-group-item list-group-item-action py-1 dd-emp"
                                        data-id="<?= (int)$e['id'] ?>"
                                        data-ruc="<?= htmlspecialchars($e['ruc']) ?>"
                                        data-est="<?= htmlspecialchars($est) ?>"
                                        data-nombre="<?= htmlspecialchars($e['razon_social']) ?>"
                                        data-search="<?= htmlspecialchars($busq) ?>">
                                    <span class="fw-semibold"><?= htmlspecialchars($e['razon_social']) ?></span><br>
                                    <span class="text-muted small">RUC <?= htmlspecialchars($e['ruc']) ?> · Est. <?= htmlspecialchars($est) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-text">Escribe para filtrar. Se busca en la base anterior por el RUC del contribuyente (todos sus establecimientos).</div>
                </div>

                <label class="form-label fw-semibold small">¿Qué quieres extraer?</label>
                <div class="mb-2">
                    <a href="#" class="small me-2" id="selTodos">Todos</a>
                    <a href="#" class="small" id="selNinguno">Ninguno</a>
                </div>
                <div class="row g-1 mb-3">
                    <?php foreach ($entidades as $key => $def): ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input chk-ent" type="checkbox" value="<?= htmlspecialchars($key) ?>" id="ent_<?= htmlspecialchars($key) ?>" checked>
                                <label class="form-check-label small" for="ent_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($def['label']) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="btn btn-primary w-100" id="btnAnalizar">
                    <i class="bi bi-clipboard-data me-1"></i> Analizar (resumen)
                </button>
            </div>
        </div>
    </div>

    <!-- Paso 2: resumen -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-2-circle text-primary me-2"></i>Resumen de la información</h6>
            </div>
            <div class="card-body">
                <div id="zonaResumen" class="text-muted small py-4 text-center">
                    Selecciona una empresa y los datos, y pulsa <b>Analizar</b> para ver cuántos registros hay en la base anterior.
                </div>
                <div id="zonaMigrar" class="d-none mt-3">
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Desde</label>
                            <input type="date" class="form-control form-control-sm" id="fDesde">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="fHasta">
                        </div>
                        <div class="col text-end">
                            <button class="btn btn-success btn-sm" id="btnMigrar"><i class="bi bi-database-down me-1"></i> Migrar seleccionados</button>
                        </div>
                    </div>
                    <div class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>El rango de fechas aplica a los <b>documentos</b> (facturas, compras, NC, retenciones, recibos). Los catálogos se migran completos. Vacío = todo el histórico.</div>
                    <div id="zonaMigrarResultado" class="small"></div>
                    <hr class="my-2">
                    <div class="mb-1">
                        <button class="btn btn-outline-warning btn-sm" id="btnVerificarAnuladas">
                            <i class="bi bi-check2-square me-1"></i> Verificar / actualizar facturas anuladas
                        </button>
                        <span id="zonaAnuladas" class="small ms-2"></span>
                    </div>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Cruza las facturas migradas contra el estado del sistema anterior y marca como <b>anuladas</b> las que corresponda.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const base = '<?= $base ?>';
    const $ = (id) => document.getElementById(id);
    const fmt = (n) => (n === null || n === undefined) ? '—' : Number(n).toLocaleString('es-EC');

    const entsSeleccionadas = () => Array.from(document.querySelectorAll('.chk-ent:checked')).map(c => c.value);

    $('selTodos').addEventListener('click', (e) => { e.preventDefault(); document.querySelectorAll('.chk-ent').forEach(c => c.checked = true); });
    $('selNinguno').addEventListener('click', (e) => { e.preventDefault(); document.querySelectorAll('.chk-ent').forEach(c => c.checked = false); });

    // ── Buscador de empresa (destino) ──
    const dd = $('ddEmpresas'), buscar = $('selEmpresaBuscar');
    const items = Array.from(document.querySelectorAll('.dd-emp'));

    function filtrarEmpresas() {
        const q = buscar.value.trim().toLowerCase();
        let visibles = 0;
        items.forEach(it => {
            const ok = q === '' || it.dataset.search.indexOf(q) !== -1;
            it.style.display = ok ? '' : 'none';
            if (ok) visibles++;
        });
        dd.style.display = visibles ? 'block' : 'none';
    }
    buscar.addEventListener('focus', filtrarEmpresas);
    buscar.addEventListener('input', () => { $('selEmpresa').value = ''; filtrarEmpresas(); });
    items.forEach(it => it.addEventListener('click', () => {
        $('selEmpresa').value = it.dataset.id;
        buscar.value = it.dataset.nombre + ' (RUC ' + it.dataset.ruc + ' · Est. ' + it.dataset.est + ')';
        dd.style.display = 'none';
    }));
    document.addEventListener('click', (e) => {
        if (!dd.contains(e.target) && e.target !== buscar) dd.style.display = 'none';
    });

    // Probar conexión
    $('btnProbar').addEventListener('click', async () => {
        $('estadoConexion').innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Probando...</span>';
        try {
            const r = await fetch(base + '/config/migrarMysql?action=probar').then(x => x.json());
            $('estadoConexion').innerHTML = r.ok
                ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${r.mensaje} — ${r.server || ''}</span>`
                : `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${r.mensaje}</span>`;
        } catch (e) {
            $('estadoConexion').innerHTML = `<span class="text-danger">Error: ${e.message}</span>`;
        }
    });

    // Analizar
    $('btnAnalizar').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        if (!idEmpresa) { alert('Seleccione una empresa.'); return; }
        const entidades = entsSeleccionadas();
        if (!entidades.length) { alert('Seleccione al menos un tipo de dato.'); return; }

        const btn = $('btnAnalizar');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analizando...';
        try {
            const body = new URLSearchParams();
            body.append('id_empresa', idEmpresa);
            entidades.forEach(v => body.append('entidades[]', v));
            const res = await fetch(base + '/config/migrarMysql?action=analizar', { method: 'POST', body }).then(r => r.json());
            if (!res.ok) throw new Error(res.mensaje || 'Error al analizar');
            pintar(res);
        } catch (e) {
            $('zonaResumen').innerHTML = '<div class="alert alert-danger mb-0">' + e.message + '</div>';
        } finally {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-clipboard-data me-1"></i> Analizar (resumen)';
        }
    });

    function fmtTiempo(seg) {
        seg = Math.max(0, Math.round(seg));
        if (seg < 60) return seg + ' s';
        const m = Math.floor(seg / 60), s = seg % 60;
        if (m < 60) return m + ' min' + (s ? ' ' + s + ' s' : '');
        const h = Math.floor(m / 60);
        return h + ' h ' + (m % 60) + ' min';
    }

    function pintar(res) {
        const rows = Object.entries(res.data).map(([k, f]) => {
            const rango = f.fecha_min ? ('desde <b>' + f.fecha_min + '</b>' + (f.fecha_max ? ' hasta <b>' + f.fecha_max + '</b>' : '')) : '<span class="text-muted">—</span>';
            return `<tr>
                <td>${f.label}</td>
                <td class="text-muted small">${f.tabla}</td>
                <td class="text-end fw-bold">${f.error ? '<span class="text-danger" title="' + f.error + '">error</span>' : fmt(f.total)}</td>
                <td class="small">${rango}</td>
            </tr>`;
        }).join('');
        const total  = Object.values(res.data).reduce((a, f) => a + (f.total || 0), 0);
        const estSeg = Object.values(res.data).reduce((a, f) => a + (f.est_segundos || 0), 0);
        $('zonaResumen').innerHTML =
            `<div class="alert alert-info py-2 small mb-3">
                RUC en base anterior: <b>${res.ruc}</b> · Total de registros: <b>${fmt(total)}</b><br>
                <i class="bi bi-clock me-1"></i>Tiempo estimado de migración (lo marcado): <b>~${fmtTiempo(estSeg)}</b>
                <span class="text-muted">— aproximado; los catálogos van más rápido que los documentos</span>
             </div>
             <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Dato</th><th class="text-muted small">Tabla origen</th><th class="text-end">Registros</th><th>Registros desde</th></tr></thead>
                <tbody>${rows}</tbody>
             </table>`;
        $('zonaMigrar').classList.remove('d-none');
        $('zonaMigrarResultado').innerHTML = '';
    }

    // Migrar los datos seleccionados (uno por uno) con modal de progreso + tiempo restante
    $('btnMigrar').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        if (!idEmpresa) { alert('Seleccione una empresa.'); return; }
        const entidades = entsSeleccionadas();
        if (!entidades.length) { alert('Seleccione al menos un dato.'); return; }

        const conf = await Swal.fire({
            title: '¿Migrar los datos seleccionados?',
            html: `Se traerán <b>${entidades.length}</b> tipo(s) de dato desde la base anterior.<br>
                   <span class="text-muted small">Es idempotente: no duplica lo ya migrado.</span>`,
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, migrar',
            cancelButtonText: 'Cancelar', confirmButtonColor: '#198754'
        });
        if (!conf.isConfirmed) return;

        const desde = $('fDesde').value, hasta = $('fHasta').value;
        const btn = $('btnMigrar');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Migrando...';
        $('zonaMigrarResultado').innerHTML = '';

        // Estimaciones de tiempo, nombres y total de registros por entidad
        const estMap = {}, labelMap = {}, totalMap = {};
        try {
            const b = new URLSearchParams();
            b.append('id_empresa', idEmpresa);
            entidades.forEach(v => b.append('entidades[]', v));
            const an = await fetch(base + '/config/migrarMysql?action=analizar', { method: 'POST', body: b }).then(r => r.json());
            if (an.ok) for (const [k, f] of Object.entries(an.data)) { estMap[k] = f.est_segundos || 0; labelMap[k] = f.label || k; totalMap[k] = f.total || 0; }
        } catch (e) { /* sin estimación: se usa un aproximado */ }

        const totalEnt = entidades.length;
        const totals = { migrados: 0, vinculados: 0, ya: 0, omitidos: 0, errores: 0 };
        let hecho = 0, entActual = '', restante = 0;
        let curDone = 0, curTotal = 0, curEst = 0, restBase = 0; // progreso fino (por registros) de la entidad actual
        const estDespuesDe = (idx) => entidades.slice(idx + 1).reduce((a, e) => a + (estMap[e] || 3), 0);
        function recalcRestante() {
            const frac = curTotal > 0 ? Math.min(1, curDone / curTotal) : 0;
            restante = restBase + curEst * (1 - frac);
        }

        function pintarSwal() {
            const body = document.getElementById('migSwalBody');
            if (!body) return;
            const pct = Math.round((hecho / totalEnt) * 100);
            const fracPct = curTotal > 0 ? Math.round(Math.min(1, curDone / curTotal) * 100) : 0;
            const fino = entActual
                ? `<div class="small text-muted mt-2 d-flex justify-content-between"><span>Registros de esta etapa</span><span>${fmt(curDone)}${curTotal ? ' / ' + fmt(curTotal) : ''}</span></div>
                   <div class="progress mt-1" style="height:14px;"><div class="progress-bar bg-info" style="width:${fracPct}%">${fracPct}%</div></div>`
                : '';
            body.innerHTML =
                `<div class="mb-2">${entActual ? ('Procesando: <b>' + (labelMap[entActual] || entActual) + '</b>') : 'Preparando…'}</div>
                 <div class="progress" style="height:20px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:${pct}%">${hecho}/${totalEnt} etapas</div>
                 </div>
                 ${fino}
                 <div class="mt-3"><i class="bi bi-clock me-1"></i>Tiempo restante estimado: <b>${fmtTiempo(restante)}</b></div>
                 <div class="text-muted small mt-1">migrados ${fmt(totals.migrados)} · vinculados ${fmt(totals.vinculados)} · omitidos ${fmt(totals.omitidos)} · errores ${fmt(totals.errores)}</div>`;
        }

        restBase = estDespuesDe(-1); curEst = 0; recalcRestante();
        let timer = null;
        Swal.fire({
            title: 'Migrando información…',
            html: '<div id="migSwalBody"></div>',
            allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false,
            didOpen: () => { pintarSwal(); timer = setInterval(() => { restante = Math.max(0, restante - 1); pintarSwal(); }, 1000); }
        });

        for (let i = 0; i < entidades.length; i++) {
            const ent = entidades[i];
            entActual = ent;
            curTotal = totalMap[ent] || 0; curEst = estMap[ent] || 3; restBase = estDespuesDe(i); curDone = 0;
            // Sondeo del progreso real (registros ya migrados/vinculados) en paralelo a la migración
            const sondear = async () => {
                try {
                    const p = await fetch(base + '/config/migrarMysql?action=progreso&id_empresa=' + encodeURIComponent(idEmpresa) + '&entidad=' + encodeURIComponent(ent)).then(r => r.json());
                    if (p.ok) { curDone = p.hechos; recalcRestante(); pintarSwal(); }
                } catch (e) { /* ignorar */ }
            };
            await sondear(); // línea base
            recalcRestante(); pintarSwal();
            const sondeo = setInterval(sondear, 800);
            logMig(ent, '<span class="text-muted">migrando…</span>');
            try {
                const body = new URLSearchParams();
                body.append('id_empresa', idEmpresa);
                body.append('entidad', ent);
                if (desde) body.append('desde', desde);
                if (hasta) body.append('hasta', hasta);
                const res = await fetch(base + '/config/migrarMysql?action=migrar', { method: 'POST', body }).then(r => r.json());
                if (!res.ok) { logMig(ent, '<span class="text-danger">' + res.mensaje + '</span>'); continue; }
                const d = res.data;
                if (d.no_implementado) { logMig(ent, '<span class="text-muted">próximamente</span>'); continue; }
                totals.migrados += d.migrados || 0; totals.vinculados += d.vinculados || 0;
                totals.ya += d.ya_migrados || 0; totals.omitidos += d.omitidos || 0; totals.errores += d.errores || 0;
                const partes = [`<span class="text-success fw-bold">migrados ${fmt(d.migrados)}</span>`];
                if (d.vinculados !== undefined) partes.push(`vinculados ${fmt(d.vinculados)}`);
                partes.push(`ya estaban ${fmt(d.ya_migrados)}`);
                if (d.omitidos !== undefined) partes.push(`omitidos <b class="${d.omitidos ? 'text-warning' : ''}">${fmt(d.omitidos)}</b>`);
                partes.push(`errores <b class="${d.errores ? 'text-danger' : ''}">${fmt(d.errores)}</b>`);
                let html = partes.join(' · ') + ` <span class="text-muted">(de ${fmt(d.total)})</span>`;
                if (d.error_muestra) html += `<br><span class="text-danger small">⚠ ${d.error_muestra}</span>`;
                if (d.omitidos > 0) html += `<br><span class="text-warning small">ℹ ${fmt(d.omitidos)} omitido(s): el documento de origen no tiene cliente/proveedor con identificación (RUC/cédula).</span>`;
                if (d.vinculados > 0) {
                    const muestra = (d.vinculados_muestra && d.vinculados_muestra.length)
                        ? ': ' + d.vinculados_muestra.map(x => String(x).replace(/</g, '&lt;')).join(', ') + (d.vinculados > d.vinculados_muestra.length ? '…' : '')
                        : '';
                    html += `<br><span class="text-info small">ℹ ${fmt(d.vinculados)} ya existía(n) en el sistema (mismo nombre/identificación o número) → se vincularon, NO se duplicaron${muestra}</span>`;
                }
                logMig(ent, html);
            } catch (e) { logMig(ent, '<span class="text-danger">' + e.message + '</span>'); }
            finally { clearInterval(sondeo); hecho++; if (curTotal) curDone = curTotal; recalcRestante(); pintarSwal(); }
        }

        if (timer) clearInterval(timer);
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-database-down me-1"></i> Migrar seleccionados';
        Swal.fire({
            icon: totals.errores ? 'warning' : 'success',
            title: 'Migración finalizada',
            html: `<div class="text-start small" style="max-width:280px;margin:0 auto;">
                    <div><b>${fmt(totals.migrados)}</b> migrados</div>
                    <div><b>${fmt(totals.vinculados)}</b> vinculados (ya existían)</div>
                    <div><b>${fmt(totals.ya)}</b> ya estaban</div>
                    <div><b class="${totals.omitidos ? 'text-warning' : ''}">${fmt(totals.omitidos)}</b> omitidos</div>
                    <div><b class="${totals.errores ? 'text-danger' : ''}">${fmt(totals.errores)}</b> errores</div>
                   </div>`,
            confirmButtonColor: '#198754'
        });
    });

    // Verificar/actualizar facturas anuladas
    $('btnVerificarAnuladas').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        if (!idEmpresa) { alert('Seleccione una empresa.'); return; }
        const btn = $('btnVerificarAnuladas');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verificando...';
        $('zonaAnuladas').innerHTML = '';
        try {
            const body = new URLSearchParams();
            body.append('id_empresa', idEmpresa);
            const res = await fetch(base + '/config/migrarMysql?action=verificar-anuladas', { method: 'POST', body }).then(r => r.json());
            if (!res.ok) throw new Error(res.mensaje || 'Error');
            const d = res.data;
            $('zonaAnuladas').innerHTML =
                `Anuladas en base anterior: <b>${fmt(d.anuladas_en_viejo)}</b> · `
                + `Anuladas ahora en el nuevo: <b class="text-success">${fmt(d.anuladas_ahora)}</b> · Ya estaban anuladas: <b>${fmt(d.ya_anuladas)}</b> · `
                + `No están en el nuevo: <b class="text-warning">${fmt(d.no_estan_en_nuevo)}</b>`
                + (d.errores ? ` · Errores: <b class="text-danger">${fmt(d.errores)}</b>` : '');
        } catch (e) {
            $('zonaAnuladas').innerHTML = '<span class="text-danger">' + e.message + '</span>';
        } finally {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-square me-1"></i> Verificar / actualizar facturas anuladas';
        }
    });

    function logMig(ent, html) {
        const id = 'mig_' + ent;
        const label = (document.querySelector('#ent_' + ent)?.nextElementSibling?.textContent) || ent;
        let el = document.getElementById(id);
        if (!el) { el = document.createElement('div'); el.id = id; el.className = 'mb-1'; $('zonaMigrarResultado').appendChild(el); }
        el.innerHTML = '<b>' + label + ':</b> ' + html;
    }
})();
</script>
