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
                    <select class="form-select" id="selEmpresa">
                        <option value="">-- Seleccione la empresa --</option>
                        <?php foreach ($empresasMigrar as $e): ?>
                            <option value="<?= (int)$e['id'] ?>" data-ruc="<?= htmlspecialchars($e['ruc']) ?>">
                                <?= htmlspecialchars($e['razon_social']) ?> (<?= htmlspecialchars($e['ruc']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Se busca en la base anterior por el RUC del contribuyente (todos sus establecimientos).</div>
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
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="bi bi-tools me-1"></i>La <b>migración</b> de cada tipo de dato se está implementando por fases
                        (primero catálogos: clientes, productos, proveedores; luego documentos y cobros/pagos).
                        Este resumen ya te muestra el volumen real a migrar.
                    </div>
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

    function pintar(res) {
        const rows = Object.entries(res.data).map(([k, f]) => `
            <tr>
                <td>${f.label}</td>
                <td class="text-muted small">${f.tabla}</td>
                <td class="text-end fw-bold">${f.error ? '<span class="text-danger" title="' + f.error + '">error</span>' : fmt(f.total)}</td>
            </tr>`).join('');
        const total = Object.values(res.data).reduce((a, f) => a + (f.total || 0), 0);
        $('zonaResumen').innerHTML =
            `<div class="alert alert-info py-2 small mb-3">RUC en base anterior: <b>${res.ruc}</b> · Total de registros: <b>${fmt(total)}</b></div>
             <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Dato</th><th class="text-muted small">Tabla origen</th><th class="text-end">Registros</th></tr></thead>
                <tbody>${rows}</tbody>
             </table>`;
        $('zonaMigrar').classList.remove('d-none');
    }
})();
</script>
