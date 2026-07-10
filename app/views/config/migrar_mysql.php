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

    // Migrar los datos seleccionados (uno por uno)
    $('btnMigrar').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        if (!idEmpresa) { alert('Seleccione una empresa.'); return; }
        const entidades = entsSeleccionadas();
        if (!entidades.length) { alert('Seleccione al menos un dato.'); return; }
        if (!confirm('¿Migrar los datos seleccionados desde la base anterior? Es idempotente (no duplica).')) return;

        const desde = $('fDesde').value, hasta = $('fHasta').value;
        const btn = $('btnMigrar');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Migrando...';
        $('zonaMigrarResultado').innerHTML = '';
        for (const ent of entidades) {
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
                logMig(ent, `<span class="text-success fw-bold">migrados ${fmt(d.migrados)}</span> · vinculados ${fmt(d.vinculados)} · ya estaban ${fmt(d.ya_migrados)} · omitidos ${fmt(d.omitidos)} · errores <span class="${d.errores ? 'text-danger fw-bold' : ''}">${fmt(d.errores)}</span> <span class="text-muted">(de ${fmt(d.total)})</span>`);
            } catch (e) { logMig(ent, '<span class="text-danger">' + e.message + '</span>'); }
        }
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-database-down me-1"></i> Migrar seleccionados';
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
