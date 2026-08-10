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

<!-- ① Migrar las empresas del sistema anterior (registro en el nuevo, sin correos) -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-building-add text-primary me-2"></i>Registrar empresas del sistema anterior</h6>
        <button class="btn btn-sm btn-outline-primary" id="btnListarEmpresas"><i class="bi bi-search me-1"></i> Buscar empresas por migrar</button>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Crea en el sistema nuevo las empresas <b>activas</b> del sistema anterior que <b>aún no existen</b> aquí.
            Se registra <b>una empresa por contribuyente</b> (RUC base) con todos sus establecimientos, su usuario
            administrador y en ambiente <b>producción</b>. <b>No se envía ningún correo</b>: la invitación del usuario
            y los documentos legales se enviarán cuando edites y <b>guardes</b> la empresa por primera vez.
        </p>
        <div id="empresasMigrarBox" style="display:none;">
            <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                <input type="text" class="form-control form-control-sm" id="empFiltro" style="max-width:280px" placeholder="Filtrar por nombre o RUC…">
                <div><a href="#" class="small me-2" id="empTodas">Todas</a><a href="#" class="small" id="empNinguna">Ninguna</a></div>
                <span class="small text-muted" id="empContador"></span>
                <button class="btn btn-sm btn-success ms-auto" id="btnMigrarEmpresas" disabled><i class="bi bi-download me-1"></i> Migrar seleccionadas</button>
            </div>
            <div style="max-height:340px;overflow:auto;" class="border rounded">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                        <tr>
                            <th style="width:38px"><input type="checkbox" id="empChkAll" title="Seleccionar todas las visibles"></th>
                            <th>Empresa</th>
                            <th style="width:130px">RUC base</th>
                            <th style="width:110px" class="text-center">Estab.</th>
                            <th>Correo</th>
                        </tr>
                    </thead>
                    <tbody id="empTbody"></tbody>
                </table>
            </div>
        </div>
        <div id="empResultado" class="small mt-2"></div>
    </div>
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
                            <button class="btn btn-outline-danger btn-sm me-1" id="btnEliminar"><i class="bi bi-trash3 me-1"></i> Eliminar migrados</button>
                            <button class="btn btn-success btn-sm" id="btnMigrar"><i class="bi bi-database-down me-1"></i> Migrar seleccionados</button>
                        </div>
                    </div>
                    <div class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>El rango de fechas aplica a los <b>documentos</b> (facturas, compras, NC, retenciones, recibos). Los catálogos se migran completos. Vacío = todo el histórico.</div>
                    <div class="small text-muted mb-2"><i class="bi bi-trash3 me-1"></i><b>Eliminar migrados</b> borra lo que la migración insertó en las entidades seleccionadas (documentos, contabilidad, inventario) para volver a migrarlas y corregir errores. <b>No toca</b> registros nativos del sistema ni los catálogos (clientes/productos/proveedores…), que se auto-corrigen al re-migrar.</div>
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

    <!-- Paso 3: importar la configuración contable (reglas generales), con revisión previa -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-3-circle text-primary me-2"></i>Configuración contable (opcional)</h6>
                <button class="btn btn-sm btn-outline-primary" id="btnCfgPrev">
                    <i class="bi bi-search me-1"></i> Revisar configuración del sistema anterior
                </button>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Trae las <b>reglas generales</b> de cuentas del sistema anterior (ventas, compras, recibos, rol de pagos).
                    Primero migre el <b>Plan de cuentas</b>: las cuentas destino se resuelven por su código.
                    Nada se guarda hasta que usted elija qué aplicar.
                </div>
                <div id="cfgResumen" class="small mb-2"></div>
                <div id="cfgTabla" class="table-responsive" style="max-height:420px;overflow:auto;"></div>
                <div id="cfgAcciones" class="mt-2 d-none">
                    <button class="btn btn-success btn-sm" id="btnCfgAplicar">
                        <i class="bi bi-check2-circle me-1"></i> Aplicar seleccionadas
                    </button>
                    <span id="cfgAplicarMsg" class="small ms-2"></span>
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
        const ambTxt = res.ambiente === '2' ? 'PRODUCCIÓN' : (res.ambiente === '1' ? 'PRUEBAS' : '—');
        const ambCls = res.ambiente === '2' ? 'text-danger' : 'text-success';
        $('zonaResumen').innerHTML =
            `<div class="alert alert-info py-2 small mb-3">
                RUC en base anterior: <b>${res.ruc}</b> · Total de registros: <b>${fmt(total)}</b><br>
                <i class="bi bi-diagram-3 me-1"></i>Ambiente de la empresa (destino): <b class="${ambCls}">${ambTxt}</b>${res.ambiente ? ' (' + res.ambiente + ')' : ''} <span class="text-muted">— los documentos migrados se marcan con este ambiente</span><br>
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

        // Feedback inmediato mientras se consulta el ambiente y se estima (puede tardar en compras)
        Swal.fire({
            title: 'Preparando migración…',
            html: '<div class="spinner-border text-success mb-2" role="status" style="width:2.2rem;height:2.2rem;"></div>'
                + '<div class="text-muted small">Consultando el ambiente de la empresa y estimando el tiempo…</div>',
            showConfirmButton: false, allowOutsideClick: false, allowEscapeKey: false
        });

        // Consultar ambiente de la empresa + estimaciones ANTES de confirmar (para mostrarlos)
        const estMap = {}, labelMap = {}, totalMap = {};
        let ambiente = null;
        try {
            const b = new URLSearchParams();
            b.append('id_empresa', idEmpresa);
            entidades.forEach(v => b.append('entidades[]', v));
            const an = await fetch(base + '/config/migrarMysql?action=analizar', { method: 'POST', body: b }).then(r => r.json());
            if (an.ok) { ambiente = an.ambiente; for (const [k, f] of Object.entries(an.data)) { estMap[k] = f.est_segundos || 0; labelMap[k] = f.label || k; totalMap[k] = f.total || 0; } }
        } catch (e) { /* sin estimación: se usa un aproximado */ }

        // Aviso: registros que YA existen en el módulo destino y NO vienen de la migración
        // (capturados en el nuevo sistema o por otra vía). Podrían duplicarse. Respeta Desde/Hasta.
        const fDesde = $('fDesde').value, fHasta = $('fHasta').value;
        let existentes = {};
        try {
            const b = new URLSearchParams();
            b.append('id_empresa', idEmpresa);
            entidades.forEach(v => b.append('entidades[]', v));
            if (fDesde) b.append('desde', fDesde);
            if (fHasta) b.append('hasta', fHasta);
            const ex = await fetch(base + '/config/migrarMysql?action=verificar-existentes', { method: 'POST', body: b }).then(r => r.json());
            if (ex.ok) existentes = ex.data || {};
        } catch (e) { /* sin verificación: se continúa igual */ }

        // Guardarraíl: el RUC de esta empresa ¿ya se migró bajo OTRO establecimiento? El origen se
        // trae por RUC (no por establecimiento), así que repetirlo duplicaría todo el histórico.
        let hermana = null;
        try {
            const b = new URLSearchParams();
            b.append('id_empresa', idEmpresa);
            const rh = await fetch(base + '/config/migrarMysql?action=verificar-ruc-migrado', { method: 'POST', body: b }).then(r => r.json());
            if (rh.ok) hermana = rh.hermana || null;
        } catch (e) { /* sin verificación: se continúa igual */ }

        let avisoHtml = '';
        if (hermana) {
            avisoHtml +=
                `<div class="alert alert-danger text-start small mt-2 mb-0">
                    <b><i class="bi bi-exclamation-octagon me-1"></i>¡Atención, RUC ya migrado!</b>
                    Este RUC ya tiene datos migrados bajo el establecimiento <b>${hermana.establecimiento}</b> (${hermana.nombre}).
                    La base anterior se consulta por RUC completo, sin distinguir establecimiento: migrar aquí también
                    <b>traerá y duplicará TODO el histórico</b> que ya se migró en ese otro establecimiento.
                    Si de verdad quiere continuar, hágalo con conocimiento de causa.
                 </div>`;
        }

        const entExist = Object.keys(existentes);
        if (entExist.length) {
            const filas = entExist.map(k => `<tr><td class="text-start">${existentes[k].label}</td><td class="text-end fw-bold text-warning">${fmt(existentes[k].nativos)}</td></tr>`).join('');
            avisoHtml +=
                `<div class="alert alert-warning text-start small mt-2 mb-0">
                    <b><i class="bi bi-exclamation-triangle me-1"></i>Atención:</b> estos módulos YA tienen registros que <b>no</b> provienen de la migración:
                    <table class="table table-sm mb-1 mt-1"><tbody>${filas}</tbody></table>
                    La migración evita duplicar por número de documento / identificación, pero <b>revíselos</b>: si un registro nativo tiene distinto número al del sistema anterior, podría <b>duplicarse</b>.
                 </div>`;
        }

        const hayAviso = !!hermana || entExist.length > 0;
        const ambTxt = ambiente === '2' ? 'PRODUCCIÓN' : (ambiente === '1' ? 'PRUEBAS' : 'desconocido');
        const ambCls = ambiente === '2' ? 'text-danger' : 'text-success';
        const conf = await Swal.fire({
            title: hermana ? '¿Migrar de todas formas? (RUC ya migrado)' : '¿Migrar los datos seleccionados?',
            html: `Se traerán <b>${entidades.length}</b> tipo(s) de dato desde la base anterior.<br>
                   Ambiente de la empresa (destino): <b class="${ambCls}">${ambTxt}</b>${ambiente ? ' (' + ambiente + ')' : ''}<br>
                   <span class="text-muted small">Es idempotente: no duplica lo ya migrado. Los documentos se marcan con este ambiente.</span>
                   ${avisoHtml}`,
            icon: hermana ? 'error' : (entExist.length ? 'warning' : 'question'), showCancelButton: true,
            confirmButtonText: hayAviso ? 'Entiendo, migrar de todas formas' : 'Sí, migrar',
            cancelButtonText: 'Cancelar', confirmButtonColor: hermana ? '#dc3545' : (entExist.length ? '#fd7e14' : '#198754')
        });
        if (!conf.isConfirmed) return;

        const desde = $('fDesde').value, hasta = $('fHasta').value;
        const btn = $('btnMigrar');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Migrando...';
        $('zonaMigrarResultado').innerHTML = '';

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
            const frac = curTotal > 0 ? Math.min(1, curDone / curTotal) : 0;
            // La barra general incluye la fracción de la etapa en curso, así avanza de forma continua
            const pct = Math.round(((hecho + frac) / totalEnt) * 100);
            const fracPct = Math.round(frac * 100);
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
        Swal.fire({
            title: 'Migrando información…',
            html: '<div id="migSwalBody"></div>',
            allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false,
            didOpen: async () => {
              pintarSwal();
              const timer = setInterval(() => { restante = Math.max(0, restante - 1); pintarSwal(); }, 1000);
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
                if (d.omitidos > 0) {
                    // Cada entidad informa su propio motivo (omitidos_motivo). Si no lo trae, es un
                    // documento y el motivo es el cliente/proveedor sin identificación.
                    const motivo = d.omitidos_motivo || 'el documento de origen no tiene cliente/proveedor con identificación (RUC/cédula)';
                    html += `<br><span class="text-warning small">ℹ ${fmt(d.omitidos)} omitido(s): ${motivo}.</span>`;
                }
                if (d.vinculados > 0) {
                    const muestra = (d.vinculados_muestra && d.vinculados_muestra.length)
                        ? ': ' + d.vinculados_muestra.map(x => String(x).replace(/</g, '&lt;')).join(', ') + (d.vinculados > d.vinculados_muestra.length ? '…' : '')
                        : '';
                    html += `<br><span class="text-info small">ℹ ${fmt(d.vinculados)} ya existía(n) en el sistema (mismo nombre/identificación o número) → se vincularon, NO se duplicaron${muestra}</span>`;
                }
                if (d.revividos > 0) {
                    html += `<br><span class="text-success small">♻ ${fmt(d.revividos)} estaban eliminado(s) y se restauraron (volvieron a mostrarse).</span>`;
                }
                logMig(ent, html);
            } catch (e) { logMig(ent, '<span class="text-danger">' + e.message + '</span>'); }
              finally { clearInterval(sondeo); hecho++; if (curTotal) curDone = curTotal; recalcRestante(); pintarSwal(); }
              }

              clearInterval(timer);
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
            }
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

    // ── Eliminar migrados (para re-migrar y corregir) ──
    // Catálogos vedados: se auto-corrigen al re-migrar (reconciliación), no se borran.
    const NO_ELIMINABLES = ['plan_cuentas', 'clientes', 'productos', 'proveedores', 'vendedores', 'bodegas'];

    $('btnEliminar').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        if (!idEmpresa) { alert('Seleccione una empresa.'); return; }
        const seleccion = entsSeleccionadas();
        const entidades = seleccion.filter(e => !NO_ELIMINABLES.includes(e));
        if (!entidades.length) {
            await Swal.fire({ icon: 'info', title: 'Nada que eliminar',
                html: 'Marque al menos una entidad de <b>documento, contabilidad o inventario</b>.<br><span class="text-muted small">Los catálogos (clientes, productos, proveedores, vendedores, bodegas, plan de cuentas) no se eliminan: se auto-corrigen al re-migrar.</span>' });
            return;
        }

        const desde = $('fDesde').value, hasta = $('fHasta').value;
        const rangoTxt = (desde || hasta)
            ? `<span class="text-primary">rango ${desde || '…'} a ${hasta || '…'}</span> (por fecha del documento)`
            : `<span class="text-danger">TODO el histórico migrado</span> (sin filtro de fechas)`;

        // Preview: cuántos se borrarían por entidad (respeta Desde/Hasta)
        let data;
        try {
            const b = new URLSearchParams();
            b.append('id_empresa', idEmpresa);
            entidades.forEach(v => b.append('entidades[]', v));
            if (desde) b.append('desde', desde);
            if (hasta) b.append('hasta', hasta);
            const pr = await fetch(base + '/config/migrarMysql?action=eliminar-preview', { method: 'POST', body: b }).then(r => r.json());
            if (!pr.ok) throw new Error(pr.mensaje || 'Error');
            data = pr.data;
        } catch (e) { await Swal.fire({ icon: 'error', title: 'Error', text: e.message }); return; }

        const filas = entidades.map(e => {
            const d = data[e];
            if (!d) return '';
            const vin = d.vinculados ? ` <span class="text-muted">(+${fmt(d.vinculados)} vinculados intactos)</span>` : '';
            return `<tr><td class="text-start">${d.label}</td><td class="text-end fw-bold ${d.insertados ? 'text-danger' : 'text-muted'}">${fmt(d.insertados)}</td><td>${vin}</td></tr>`;
        }).join('');
        const totalIns = entidades.reduce((a, e) => a + (data[e]?.insertados || 0), 0);
        if (!totalIns) {
            await Swal.fire({ icon: 'info', title: 'No hay registros migrados', text: 'Las entidades seleccionadas no tienen registros insertados por la migración.' });
            return;
        }

        const conf = await Swal.fire({
            title: '¿Eliminar los registros migrados?',
            html: `<div class="small text-start">Alcance: ${rangoTxt}.<br>Se borrarán <b class="text-danger">${fmt(totalIns)}</b> registro(s) insertados por la migración:
                   <table class="table table-sm mt-2 mb-2"><tbody>${filas}</tbody></table>
                   <div class="text-muted">Solo se borra lo que la migración insertó. Los registros nativos y los ya existentes (vinculados) <b>no se tocan</b>. Escriba <b>ELIMINAR</b> para confirmar.</div></div>`,
            input: 'text', inputPlaceholder: 'ELIMINAR',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
            preConfirm: (v) => { if ((v || '').trim().toUpperCase() !== 'ELIMINAR') { Swal.showValidationMessage('Escriba ELIMINAR para confirmar.'); return false; } return true; }
        });
        if (!conf.isConfirmed) return;

        const btn = $('btnEliminar');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Eliminando...';
        $('zonaMigrarResultado').innerHTML = '';
        const totals = { cabeceras: 0, hijos: 0, mapa: 0, errores: 0 };

        Swal.fire({
            title: 'Eliminando registros migrados…',
            html: '<div id="delSwalBody"></div>',
            allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false,
            didOpen: async () => {
                const body = document.getElementById('delSwalBody');
                for (let i = 0; i < entidades.length; i++) {
                    const ent = entidades[i];
                    if (body) body.innerHTML = `<div class="mb-2">Eliminando: <b>${(data[ent]?.label) || ent}</b> (${i + 1}/${entidades.length})</div>
                        <div class="progress" style="height:18px;"><div class="progress-bar bg-danger" style="width:${Math.round(((i) / entidades.length) * 100)}%"></div></div>`;
                    logMig(ent, '<span class="text-muted">eliminando…</span>');
                    try {
                        const b = new URLSearchParams();
                        b.append('id_empresa', idEmpresa);
                        b.append('entidad', ent);
                        if (desde) b.append('desde', desde);
                        if (hasta) b.append('hasta', hasta);
                        const res = await fetch(base + '/config/migrarMysql?action=eliminar', { method: 'POST', body: b }).then(r => r.json());
                        if (!res.ok) { logMig(ent, '<span class="text-danger">' + res.mensaje + '</span>'); totals.errores++; continue; }
                        const d = res.data;
                        totals.cabeceras += d.cabeceras || 0; totals.hijos += d.hijos || 0; totals.mapa += d.mapa || 0;
                        let html = `<span class="text-danger fw-bold">${fmt(d.cabeceras)} eliminado(s)</span> · ${fmt(d.hijos)} línea(s) · mapa ${fmt(d.mapa)}`;
                        if (d.vinculados_intactos) html += ` · <span class="text-muted">${fmt(d.vinculados_intactos)} vinculados intactos</span>`;
                        if (d.aviso) html += `<br><span class="text-warning small">⚠ ${d.aviso}</span>`;
                        logMig(ent, html);
                    } catch (e) { logMig(ent, '<span class="text-danger">' + e.message + '</span>'); totals.errores++; }
                }
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar migrados';
                await Swal.fire({
                    icon: totals.errores ? 'warning' : 'success',
                    title: 'Eliminación finalizada',
                    html: `<div class="text-start small" style="max-width:280px;margin:0 auto;">
                            <div><b>${fmt(totals.cabeceras)}</b> registros eliminados</div>
                            <div><b>${fmt(totals.hijos)}</b> líneas hijas</div>
                            <div><b>${fmt(totals.mapa)}</b> filas de mapa limpiadas</div>
                            <div><b class="${totals.errores ? 'text-danger' : ''}">${fmt(totals.errores)}</b> errores</div>
                           </div><div class="text-muted small mt-2">Ya puede volver a migrar las entidades corregidas.</div>`,
                    confirmButtonColor: '#198754'
                });
            }
        });
    });

    // ── Paso 3: configuración contable (revisión previa + aplicar lo elegido) ──
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    $('btnCfgPrev').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        if (!idEmpresa) { $('cfgResumen').innerHTML = '<span class="text-danger">Seleccione una empresa.</span>'; return; }

        $('cfgResumen').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Revisando…';
        $('cfgTabla').innerHTML = ''; $('cfgAcciones').classList.add('d-none');
        try {
            const body = new FormData(); body.append('id_empresa', idEmpresa);
            const res = await fetch(base + '/config/migrarMysql?action=config-preview', { method: 'POST', body }).then(r => r.json());
            if (!res.ok) { $('cfgResumen').innerHTML = '<span class="text-danger">' + esc(res.mensaje) + '</span>'; return; }

            const r = res.resumen;
            $('cfgResumen').innerHTML =
                `<b>${fmt(r.total)}</b> reglas generales encontradas · ` +
                `<b class="text-success">${fmt(r.listas)}</b> listas para aplicar · ` +
                `<b class="${r.sin_slot ? 'text-warning' : ''}">${fmt(r.sin_slot)}</b> sin equivalente · ` +
                `<b class="${r.sin_cuenta ? 'text-danger' : ''}">${fmt(r.sin_cuenta)}</b> sin cuenta · ` +
                `<b>${fmt(r.ya)}</b> ya configuradas`;

            // Nada marcable: casi siempre es que falta migrar el plan de cuentas (Paso 1).
            if (!r.listas && !r.ya) {
                $('cfgResumen').innerHTML +=
                    '<div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">' +
                    '<i class="bi bi-exclamation-triangle me-1"></i>' +
                    (r.sin_cuenta
                        ? 'Ninguna regla se puede aplicar porque las cuentas de destino <b>no existen todavía</b> en el plan de esta empresa. ' +
                          'Migre primero <b>Plan de cuentas</b> en el Paso 1 y vuelva a revisar.'
                        : 'Ninguna de las reglas del sistema anterior tiene equivalente en el sistema nuevo.') +
                    '</div>';
            } else if (r.sin_cuenta) {
                $('cfgResumen').innerHTML +=
                    '<div class="form-text mt-1"><i class="bi bi-info-circle me-1"></i>Las filas <b>sin cuenta</b> no se pueden marcar: ' +
                    'esa cuenta no existe en el plan de la empresa. Migre <b>Plan de cuentas</b> (Paso 1) y vuelva a revisar.</div>';
            }

            if (!res.filas.length) { $('cfgTabla').innerHTML = '<div class="text-muted small">Sin configuración general en el sistema anterior.</div>'; return; }

            const badge = {
                lista:          '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Lista</span>',
                ya_configurada: '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Ya configurada</span>',
                sin_slot:       '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Sin equivalente</span>',
                sin_cuenta:     '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Sin cuenta</span>'
            };

            let filas = '';
            res.filas.forEach((f, i) => {
                const aplicable = (f.estado === 'lista' || f.estado === 'ya_configurada');
                const chk = aplicable
                    ? `<input type="checkbox" class="form-check-input chk-cfg" data-i="${i}" ${f.estado === 'lista' ? 'checked' : ''}>`
                    : '<span class="text-muted">—</span>';
                filas +=
                    `<tr class="${aplicable ? '' : 'table-light text-muted'}">
                        <td class="text-center">${chk}</td>
                        <td class="small">${esc(f.tipo_viejo)}</td>
                        <td class="small">${esc(f.concepto_viejo)}</td>
                        <td class="small">${esc(f.cuenta_vieja)}</td>
                        <td class="small">${f.slot_nuevo ? esc(f.slot_nuevo) : '<i>(sin equivalente)</i>'}</td>
                        <td class="small">${f.cuenta_nueva
                            ? esc(f.cuenta_nueva)
                            : (f.estado === 'sin_cuenta'
                                ? `<span class="text-danger">falta <b>${esc(f.cod_casa)}</b> en el plan</span>`
                                : '—')}</td>
                        <td class="text-center">${badge[f.estado] || ''}</td>
                     </tr>`;
            });

            $('cfgTabla').innerHTML =
                `<table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light" style="position:sticky;top:0;z-index:1">
                        <tr>
                            <th style="width:34px" class="text-center"><input type="checkbox" class="form-check-input" id="chkCfgTodos" checked></th>
                            <th>Tipo (anterior)</th><th>Concepto (anterior)</th><th>Cuenta anterior</th>
                            <th>Configuración destino</th><th>Cuenta destino</th><th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>${filas}</tbody>
                 </table>`;

            window.__cfgFilas = res.filas;
            $('cfgAcciones').classList.remove('d-none');
            $('chkCfgTodos').addEventListener('change', (e) => {
                document.querySelectorAll('.chk-cfg').forEach(c => c.checked = e.target.checked);
            });
        } catch (e) {
            $('cfgResumen').innerHTML = '<span class="text-danger">Error al revisar la configuración.</span>';
        }
    });

    $('btnCfgAplicar').addEventListener('click', async () => {
        const idEmpresa = $('selEmpresa').value;
        const marcadas = Array.from(document.querySelectorAll('.chk-cfg:checked'))
            .map(c => window.__cfgFilas[Number(c.dataset.i)])
            .filter(f => f && f.id_asiento_tipo && f.id_cuenta)
            .map(f => ({ id_asiento_tipo: f.id_asiento_tipo, id_cuenta: f.id_cuenta }));

        if (!marcadas.length) { $('cfgAplicarMsg').innerHTML = '<span class="text-warning">No hay reglas seleccionadas.</span>'; return; }
        if (!confirm(`¿Aplicar ${marcadas.length} regla(s) a la configuración contable de esta empresa?`)) return;

        $('cfgAplicarMsg').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Aplicando…';
        try {
            const body = new FormData();
            body.append('id_empresa', idEmpresa);
            body.append('seleccion', JSON.stringify(marcadas));
            const res = await fetch(base + '/config/migrarMysql?action=config-aplicar', { method: 'POST', body }).then(r => r.json());
            if (!res.ok) { $('cfgAplicarMsg').innerHTML = '<span class="text-danger">' + esc(res.mensaje) + '</span>'; return; }
            const d = res.data;
            $('cfgAplicarMsg').innerHTML =
                `<span class="text-success"><b>${fmt(d.aplicadas)}</b> creadas · <b>${fmt(d.actualizadas)}</b> actualizadas` +
                (d.errores ? ` · <b class="text-danger">${fmt(d.errores)}</b> con error` : '') + '</span>';
            $('btnCfgPrev').click(); // refrescar: ahora saldrán como "Ya configurada"
        } catch (e) {
            $('cfgAplicarMsg').innerHTML = '<span class="text-danger">Error al aplicar.</span>';
        }
    });

    // ── ① Registrar empresas del sistema anterior ──
    let empData = [];

    function empRender() {
        const q = ($('empFiltro').value || '').trim().toLowerCase();
        const tb = $('empTbody');
        tb.innerHTML = '';
        let visibles = 0;
        empData.forEach((e, i) => {
            const hay = (e.nombre + ' ' + e.razon + ' ' + e.base + ' ' + (e.mail || '')).toLowerCase();
            if (q !== '' && hay.indexOf(q) === -1) return;
            visibles++;
            const correo = e.mail
                ? (e.tiene_mail ? esc(e.mail) : '<span class="text-warning" title="Correo no válido">' + esc(e.mail) + '</span>')
                : '<span class="text-muted">— sin correo —</span>';
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="checkbox" class="emp-chk" data-i="' + i + '"></td>' +
                '<td><span class="fw-semibold">' + esc(e.nombre) + '</span>' +
                (e.razon && e.razon !== e.nombre ? '<br><span class="text-muted small">' + esc(e.razon) + '</span>' : '') + '</td>' +
                '<td class="small">' + esc(e.base) + '</td>' +
                '<td class="text-center small">' + esc(e.ests) + (e.n_est > 1 ? ' <span class="badge bg-secondary">' + e.n_est + '</span>' : '') + '</td>' +
                '<td class="small">' + correo + '</td>';
            tb.appendChild(tr);
        });
        $('empContador').textContent = visibles + ' de ' + empData.length + ' empresas por migrar';
        empSync();
    }

    function empSync() {
        const marcadas = document.querySelectorAll('.emp-chk:checked').length;
        $('btnMigrarEmpresas').disabled = marcadas === 0;
        if (marcadas > 0) $('btnMigrarEmpresas').innerHTML = '<i class="bi bi-download me-1"></i> Migrar ' + marcadas + ' seleccionada(s)';
        else $('btnMigrarEmpresas').innerHTML = '<i class="bi bi-download me-1"></i> Migrar seleccionadas';
    }

    $('btnListarEmpresas').addEventListener('click', async () => {
        const btn = $('btnListarEmpresas');
        const prev = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Buscando…';
        $('empResultado').innerHTML = '';
        try {
            const res = await fetch(base + '/config/migrarMysql?action=empresas-por-migrar').then(r => r.json());
            if (!res.ok) { $('empResultado').innerHTML = '<span class="text-danger">' + esc(res.mensaje) + '</span>'; return; }
            empData = res.data || [];
            $('empresasMigrarBox').style.display = empData.length ? '' : 'none';
            if (!empData.length) {
                $('empResultado').innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>No hay empresas activas pendientes de migrar: todas ya existen en el sistema nuevo.</span>';
            }
            empRender();
        } catch (e) {
            $('empResultado').innerHTML = '<span class="text-danger">Error al consultar el sistema anterior.</span>';
        } finally {
            btn.disabled = false; btn.innerHTML = prev;
        }
    });

    $('empFiltro').addEventListener('input', empRender);
    $('empTodas').addEventListener('click', (e) => { e.preventDefault(); document.querySelectorAll('.emp-chk').forEach(c => c.checked = true); empSync(); });
    $('empNinguna').addEventListener('click', (e) => { e.preventDefault(); document.querySelectorAll('.emp-chk').forEach(c => c.checked = false); $('empChkAll').checked = false; empSync(); });
    $('empChkAll').addEventListener('change', function () { document.querySelectorAll('.emp-chk').forEach(c => c.checked = this.checked); empSync(); });
    $('empTbody').addEventListener('change', (e) => { if (e.target.classList.contains('emp-chk')) empSync(); });

    $('btnMigrarEmpresas').addEventListener('click', async () => {
        const bases = Array.from(document.querySelectorAll('.emp-chk:checked')).map(c => empData[+c.dataset.i].base);
        if (!bases.length) return;
        if (!confirm('¿Registrar ' + bases.length + ' empresa(s) en el sistema nuevo?\n\nNo se enviará ningún correo ahora; la invitación y los documentos legales se enviarán cuando edites y guardes cada empresa.')) return;
        const btn = $('btnMigrarEmpresas');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Migrando…';
        $('empResultado').innerHTML = '';
        try {
            const body = new FormData();
            bases.forEach(b => body.append('bases[]', b));
            const res = await fetch(base + '/config/migrarMysql?action=migrar-empresas', { method: 'POST', body }).then(r => r.json());
            if (!res.ok) { $('empResultado').innerHTML = '<span class="text-danger">' + esc(res.mensaje) + '</span>'; return; }
            const d = res.data;
            let html = '<div class="alert alert-success py-2 mb-2"><b>' + fmt(d.migradas) + '</b> migrada(s)' +
                (d.omitidas ? ' · <b>' + fmt(d.omitidas) + '</b> omitida(s)' : '') + '.</div>';
            const errores = (d.detalle || []).filter(x => !x.ok);
            if (errores.length) {
                html += '<div class="small"><b>Detalle:</b><ul class="mb-0">';
                errores.forEach(x => { html += '<li>' + esc(x.base) + (x.nombre ? ' — ' + esc(x.nombre) : '') + ': ' + esc(x.msg) + '</li>'; });
                html += '</ul></div>';
            }
            $('empResultado').innerHTML = html;
            // Refrescar la lista: las recién migradas ya no deben aparecer.
            $('btnListarEmpresas').click();
        } catch (e) {
            $('empResultado').innerHTML = '<span class="text-danger">Error durante la migración.</span>';
        } finally {
            empSync();
        }
    });
})();
</script>
