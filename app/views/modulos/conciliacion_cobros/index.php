<?php
/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $cuentas */
/** @var array $puntosEmision */
/** @var array $perfiles */
/** @var array $cargas */
/** @var array $clientes */

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .concobros-scroll {
        max-height: calc(100vh - 300px);
        min-height: 450px;
        overflow-y: auto;
        overflow-x: auto;
    }
    .concobros-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
        white-space: nowrap;
    }
    .cc-step-num {
        display: inline-flex; align-items: center; justify-content: center;
        width: 22px; height: 22px; border-radius: 50%;
        background: #0d6efd; color: #fff; font-size: .75rem; font-weight: bold; margin-right: .4rem;
    }
    .cc-preview-box {
        max-height: 220px; overflow: auto; background: #212529; color: #d3d3d3;
        font-family: monospace; font-size: .78rem; padding: .5rem .75rem; border-radius: .375rem; white-space: pre;
    }
    /* CONFIRMADO: check marcado, aún no generado — debe notarse a simple vista en la grilla. */
    .cc-linea-fila.cc-confirmado { background-color: rgba(25,135,84,.22); border-left: 4px solid #198754; }
    .cc-linea-fila.cc-confirmado:hover { background-color: rgba(25,135,84,.28); }
    /* APLICADO: ya generó el Ingreso — quedó resuelta, tono más apagado que confirmado. */
    .cc-linea-fila.cc-aplicada { background-color: rgba(25,135,84,.08); }
    .cc-linea-fila.cc-ignorada { background-color: rgba(108,117,125,.08); opacity: .6; }
    .cc-linea-fila.cc-error { background-color: rgba(220,53,69,.10); border-left: 4px solid #dc3545; }
</style>

<div class="container-fluid pt-2 pb-3 px-0 px-md-3" id="modulo-conciliacion_cobros">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
            <small class="text-muted">Sube el estado de cuenta del banco (Excel o PDF) y concilia automáticamente contra las facturas pendientes de cobro.</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="CC.abrirModalPerfil()">
                <i class="bi bi-sliders me-1"></i> Perfiles de Mapeo
            </button>
        </div>
    </div>

    <!-- ── Paso 1: Cuenta, punto de emisión, perfil y archivo ── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <h6 class="fw-bold mb-3"><span class="cc-step-num">1</span> Cuenta bancaria y extracto</h6>
            <form id="cc-form-carga" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Cuenta Bancaria</label>
                    <select id="cc-forma" class="form-select form-select-sm shadow-none" required>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($cuentas as $c): ?>
                            <option value="<?= (int) $c['id'] ?>">
                                <?= htmlspecialchars($c['nombre'] . ($c['nombre_banco'] ? ' — ' . $c['nombre_banco'] : '') . ($c['numero_cuenta'] ? ' (' . $c['numero_cuenta'] . ')' : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Punto de Emisión (para los Ingresos)</label>
                    <select id="cc-punto" class="form-select form-select-sm shadow-none" required>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($puntosEmision as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['cod_establecimiento'] . '-' . $p['codigo_punto']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Perfil de Mapeo</label>
                    <select id="cc-perfil" class="form-select form-select-sm shadow-none" required></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Archivo del Banco (Excel/CSV o PDF)</label>
                    <input type="file" id="cc-archivo" class="form-control form-control-sm shadow-none" accept=".xlsx,.xls,.csv,.pdf" required>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary btn-sm mt-2">
                        <i class="bi bi-upload me-1"></i> Subir y Conciliar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Paso 2: Revisión de líneas ── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3 cmg-table-card" id="cc-card-lineas" style="display:none;">
        <div class="card-body p-3 pb-0">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="fw-bold mb-0"><span class="cc-step-num">2</span> Revisar y confirmar líneas del extracto</h6>
                <div id="cc-resumen-lineas" class="small text-muted"></div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="concobros-scroll">
                <table class="table table-sm table-hover align-middle mb-0" style="table-layout:auto; min-width:100%;">
                    <thead>
                        <tr>
                            <th data-col="fecha" class="ps-3">Fecha</th>
                            <th data-col="descripcion">Descripción del Banco</th>
                            <th data-col="monto" class="text-end">Monto</th>
                            <th data-col="cliente">Cliente Sugerido</th>
                            <th data-col="documento">Documento Sugerido</th>
                            <th data-col="monto_aplicar" class="text-end">Monto a Aplicar</th>
                            <th data-col="acciones" class="text-center pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cc-tbody-lineas">
                        <tr><td colspan="7" class="text-center py-4 text-muted">Sube un archivo para ver las líneas aquí.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-body p-3 border-top d-flex justify-content-end">
            <button type="button" class="btn btn-success btn-sm" id="cc-btn-generar" onclick="CC.generarIngresos()">
                <i class="bi bi-check2-circle me-1"></i> Generar ingresos de las líneas confirmadas
            </button>
        </div>
    </div>

    <!-- ── Historial de cargas ── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-1"></i> Cargas anteriores</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="cc-tabla-cargas">
                    <thead>
                        <tr>
                            <th>Fecha</th><th>Archivo</th><th>Cuenta</th><th>Perfil</th>
                            <th class="text-center">Estado</th><th class="text-end">Aplicadas</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cargas)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Sin cargas todavía.</td></tr>
                        <?php else: foreach ($cargas as $c): ?>
                            <tr>
                                <td><?= date('d-m-Y H:i:s', strtotime($c['created_at'])) ?></td>
                                <td><?= htmlspecialchars($c['nombre_archivo']) ?></td>
                                <td><?= htmlspecialchars($c['forma_pago_nombre']) ?></td>
                                <td><?= htmlspecialchars($c['nombre_perfil']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $c['estado'] === 'completado' ? 'success' : ($c['estado'] === 'error' ? 'danger' : 'warning') ?> bg-opacity-25 text-<?= $c['estado'] === 'completado' ? 'success' : ($c['estado'] === 'error' ? 'danger' : 'warning-emphasis') ?>">
                                        <?= htmlspecialchars($c['estado']) ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= (int) $c['total_aplicadas'] ?> / <?= (int) $c['total_lineas'] ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="CC.abrirCarga(<?= (int) $c['id'] ?>)">
                                        <i class="bi bi-eye"></i> Ver
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Perfiles de mapeo (crear/editar) ── -->
<div class="modal fade" id="cc-modal-perfil" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Perfil de Mapeo de Columnas</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cc-form-perfil">
                    <input type="hidden" id="cc-perfil-id">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Nombre del perfil</label>
                            <input type="text" id="cc-perfil-nombre" class="form-control form-control-sm" placeholder="Ej: Banco Pichincha - Excel" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Tipo de archivo</label>
                            <select id="cc-perfil-tipo" class="form-select form-select-sm" onchange="CC.cambiarTipoPerfil()">
                                <option value="EXCEL">Excel / CSV</option>
                                <option value="PDF">PDF</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Separador decimal</label>
                            <select id="cc-perfil-separador" class="form-select form-select-sm">
                                <option value=".">Punto (1234.56)</option>
                                <option value=",">Coma (1234,56)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6" id="cc-fila-inicio-wrap">
                            <label class="form-label small fw-bold text-muted mb-1">Filas de encabezado a saltar (Excel)</label>
                            <input type="number" id="cc-perfil-fila-inicio" class="form-control form-control-sm" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Formato de fecha</label>
                            <input type="text" id="cc-perfil-formato-fecha" class="form-control form-control-sm" value="d/m/Y" placeholder="d/m/Y">
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-2">
                        Sube un archivo de muestra (el mismo tipo que descargas del banco) para ver la estructura real
                        y probar el mapeo antes de guardar.
                        <div class="d-flex gap-2 align-items-center mt-2">
                            <input type="file" id="cc-perfil-muestra" class="form-control form-control-sm" accept=".xlsx,.xls,.csv,.pdf">
                            <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="CC.previsualizarMuestra()">
                                <i class="bi bi-eye me-1"></i> Ver / Probar
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" id="cc-btn-sugerir-regex" style="display:none;" onclick="CC.sugerirRegexPdf()">
                                <i class="bi bi-magic me-1"></i> Sugerir patrón
                            </button>
                        </div>
                    </div>
                    <div class="cc-preview-box mb-3" id="cc-preview-box">— Sin previsualización aún —</div>
                    <div class="alert alert-info small py-2 mb-3" id="cc-sugerencia-msg" style="display:none;"></div>
                    <div id="cc-preview-resultado" class="mb-3" style="display:none;">
                        <h6 class="fw-bold small text-uppercase text-muted">Resultado de aplicar el mapeo actual</h6>
                        <div class="table-responsive" style="max-height:220px; overflow:auto;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead><tr><th>Fecha</th><th>Descripción</th><th class="text-end">Monto</th><th>Referencia</th></tr></thead>
                                <tbody id="cc-preview-resultado-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <h6 class="fw-bold small text-uppercase text-muted">Dónde está cada dato</h6>
                    <div id="cc-mapeo-excel" class="row g-2 mb-2">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Columna Fecha (0 = primera)</label>
                            <input type="number" id="cc-map-fecha-col" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Columna Descripción</label>
                            <input type="number" id="cc-map-descripcion-col" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Columna Monto (crédito)</label>
                            <input type="number" id="cc-map-monto-col" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Columna Referencia (opcional)</label>
                            <input type="number" id="cc-map-referencia-col" class="form-control form-control-sm" min="0">
                        </div>
                    </div>
                    <div id="cc-mapeo-pdf" class="row g-2 mb-2" style="display:none;">
                        <div class="col-12 mb-1">
                            <small class="text-muted">
                                Un PDF no tiene columnas: la descripción suele venir partida en varias líneas y los
                                datos (fecha, monto...) aparecen en la línea que "cierra" el movimiento. Indica un
                                patrón (regex) que reconozca esa línea, usando grupos nombrados
                                <code>(?&lt;fecha&gt;...)</code> y <code>(?&lt;monto&gt;...)</code> obligatorios, y
                                opcionalmente <code>(?&lt;tipo&gt;...)</code> y <code>(?&lt;documento&gt;...)</code>.
                            </small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small mb-1">Patrón (regex) de línea de datos</label>
                            <input type="text" id="cc-map-regex-linea" class="form-control form-control-sm font-monospace"
                                   placeholder="/(?<fecha>\d{2}\/\d{2}\/\d{4})\s+(?<documento>\d+)\s+(?<tipo>[A-Z])\s+[A-Z. ]+?\s+(?<monto>[\d,]+\.\d{2})\s+[\d,]+\.\d{2}\s+\d+\s*$/">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Valor "es crédito" (opcional)</label>
                            <input type="text" id="cc-map-tipo-credito" class="form-control form-control-sm" placeholder="C">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="CC.previsualizarMuestra()">
                                <i class="bi bi-play-fill"></i> Probar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <select id="cc-perfil-select-existente" class="form-select form-select-sm w-auto me-auto" onchange="CC.cargarPerfilExistente(this.value)">
                    <option value="">— Nuevo perfil —</option>
                </select>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="CC.guardarPerfil()">
                    <i class="bi bi-save me-1"></i> Guardar Perfil
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: buscar documento manualmente para una línea ── -->
<div class="modal fade" id="cc-modal-buscar-doc" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Buscar Cliente / Documento a Cobrar</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cc-buscar-id-linea">
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted mb-1">Cliente</label>
                    <select id="cc-buscar-cliente" class="form-select form-select-sm" onchange="CC.buscarDocumentosDeCliente()"></select>
                </div>
                <div class="table-responsive" style="max-height:320px; overflow:auto;">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Tipo</th><th>Documento</th><th>Fecha</th><th class="text-end">Saldo Pendiente</th><th></th></tr></thead>
                        <tbody id="cc-buscar-docs-tbody"><tr><td colspan="5" class="text-center text-muted py-3">Seleccione un cliente.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CC_URL_BASE = "<?= $urlBase ?>";
    const CC_PERM_CREAR = <?= !empty($perm['crear']) ? 'true' : 'false' ?>;
    window.CC_CLIENTES = <?= json_encode(array_map(fn ($c) => ['id' => (int) $c['id'], 'nombre' => $c['nombre']], $clientes), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= $base ?>/js/modulos/conciliacion_cobros.js?v=<?= time() ?>"></script>
