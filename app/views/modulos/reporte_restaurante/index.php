<?php /** @var string $rutaModulo @var array $mesas @var array $meseros @var array $menuItems @var array $categorias @var array $formasPago @var string $correoEmpresa */ ?>
<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .reporte-restaurante-scroll { overflow-x:auto; }
    .reporte-restaurante-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; }
    /* Altura idéntica y explícita para todos los controles de filtros: los -sm de
       Bootstrap no renderizan igual entre selects, inputs y botones. */
    #form-filtros-rres .form-select,
    #form-filtros-rres .form-control,
    #form-filtros-rres .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un
       contenedor interno. En móvil app.css fuerza un max-height a cualquier
       *-scroll, así que hay que neutralizarlo aquí. */
    @media (max-width: 767.98px) {
        #modulo-<?= $idModulo ?> .reporte-restaurante-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?= $idModulo ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-shop-window me-2 text-primary"></i>Reportes Restaurante</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-rres" class="d-flex flex-wrap align-items-start gap-2"
                  onsubmit="event.preventDefault(); document.getElementById('rresBtnGenerar').click();">

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Ver por</label>
                    <select id="rres-ver-por" class="form-select form-select-sm shadow-none border" style="width:180px;">
                        <option value="MESAS">Ventas por mesa</option>
                        <option value="MESERO">Ventas por mesero</option>
                        <option value="FORMA_PAGO">Resumen por forma de pago</option>
                        <option value="MENU">Ítems del menú más vendidos</option>
                        <option value="CATEGORIA">Ventas por categoría</option>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Desde</label>
                    <input type="date" id="rres-fecha-desde" class="form-control form-control-sm shadow-none border"
                           style="width:115px;" value="<?= date('Y-m-01') ?>">
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Hasta</label>
                    <input type="date" id="rres-fecha-hasta" class="form-control form-control-sm shadow-none border"
                           style="width:115px;" value="<?= date('Y-m-d') ?>">
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mesa</label>
                    <select id="rres-mesa" class="form-select form-select-sm shadow-none border" style="width:130px;">
                        <option value="">Todas</option>
                        <?php foreach ($mesas as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?><?= $m['ubicacion'] ? ' — ' . htmlspecialchars($m['ubicacion']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mesero</label>
                    <select id="rres-mesero" class="form-select form-select-sm shadow-none border" style="width:120px;">
                        <option value="">Todos</option>
                        <?php foreach ($meseros as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Ítem del menú</label>
                    <select id="rres-menu-item" class="form-select form-select-sm shadow-none border" style="width:130px;">
                        <option value="">Todos</option>
                        <?php foreach ($menuItems as $mi): ?>
                            <option value="<?= (int) $mi['id'] ?>"><?= htmlspecialchars($mi['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Categoría</label>
                    <select id="rres-categoria" class="form-select form-select-sm shadow-none border" style="width:120px;">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Forma de pago + Botones: agrupados para que nunca se separen al hacer wrap -->
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Forma de pago</label>
                        <select id="rres-forma-pago" class="form-select form-select-sm shadow-none border" style="width:130px;">
                            <option value="">Todas</option>
                            <?php foreach ($formasPago as $fp): ?>
                                <option value="<?= (int) $fp['id'] ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="rresBtnLimpiar"
                                    title="Limpiar filtros" aria-label="Limpiar filtros">
                                <i class="bi bi-eraser"></i>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" id="rresBtnGenerar">
                                <i class="bi bi-funnel me-1"></i>Generar
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
                        <div class="cmg-control-card__stat-value text-primary" id="rres-kpi-comandas">0</div>
                        <div class="cmg-control-card__stat-label">Comandas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rres-kpi-documentos">0</div>
                        <div class="cmg-control-card__stat-label">Documentos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-coin bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="rres-kpi-total">$0.00</div>
                        <div class="cmg-control-card__stat-label">Total vendido</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabla ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-danger" id="rresBtnPdf" disabled><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
                    <button type="button" class="btn btn-outline-success" id="rresBtnExcel" disabled><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
                    <button type="button" class="btn btn-outline-secondary" id="rresBtnTirilla" disabled title="Imprimir en tirilla"><i class="bi bi-receipt me-1"></i>Tirilla</button>
                    <button type="button" class="btn btn-outline-info" id="rresBtnCorreo" disabled title="Enviar por correo"><i class="bi bi-envelope me-1"></i>Correo</button>
                </div>
                <small class="text-muted fw-medium" id="rres-count-label"></small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="reporte-restaurante-scroll w-100">
                <table class="table table-sm table-hover table-striped mb-0" style="font-size:0.82rem;">
                    <thead class="table-light">
                        <tr id="rres-head-mesas">
                            <th class="ps-3">Mesa</th><th>Ubicación</th><th class="text-center">Comandas</th>
                            <th class="text-center">Documentos</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rres-head-mesero" class="d-none">
                            <th class="ps-3">Mesero</th><th class="text-center">Comandas</th>
                            <th class="text-center">Documentos</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rres-head-forma-pago" class="d-none">
                            <th class="ps-3">Forma de pago</th><th>Tipo</th>
                            <th class="text-center">Cobros</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rres-head-menu" class="d-none">
                            <th class="ps-3">Ítem</th><th>Categoría</th><th class="text-center">Cant. Vendida</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rres-head-categoria" class="d-none">
                            <th class="ps-3">Categoría</th><th class="text-center">Cant. Vendida</th><th class="text-end pe-3">Total</th>
                        </tr>
                    </thead>
                    <tbody id="rres-tbody">
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-funnel fs-3 d-block mb-2"></i>Ajuste los filtros y presione <strong>Generar</strong>.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Enviar por correo -->
<div class="modal fade" id="rresModalCorreo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-envelope me-1"></i>Enviar reporte por correo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small fw-bold mb-1" for="rres-correos">Destinatarios</label>
        <input type="text" class="form-control form-control-sm" id="rres-correos"
               placeholder="correo@dominio.com, otro@dominio.com"
               value="<?= htmlspecialchars($correoEmpresa ?? '') ?>">
        <?php if (!empty($correoEmpresa)): ?>
          <div class="form-text d-flex align-items-center gap-1 flex-wrap">
            <span>Viene el correo de la empresa (<span class="fw-semibold"><?= htmlspecialchars($correoEmpresa) ?></span>); puede cambiarlo o añadir más.</span>
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="rresBtnCorreoEmpresa">Restaurar</button>
          </div>
        <?php else: ?>
          <div class="form-text">La empresa no tiene un correo configurado (Empresa → Datos generales). Escriba el destinatario.</div>
        <?php endif; ?>
        <div class="form-text">Separe varios correos con comas. Se envía el reporte con los filtros aplicados y el PDF adjunto.</div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-info btn-sm text-white" id="rresBtnEnviarCorreo"><i class="bi bi-send me-1"></i>Enviar</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
    const RUTA = '<?= $rutaModulo ?>';
    const BASE = '<?= rtrim(BASE_URL ?? "", "/") ?>';
    const $ = id => document.getElementById(id);
    const money = n => '$' + (parseFloat(n) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const COLSPAN = { MESAS: 5, MESERO: 4, FORMA_PAGO: 4, MENU: 4, CATEGORIA: 3 };

    function filtros() {
        return {
            ver_por: $('rres-ver-por').value,
            fecha_desde: $('rres-fecha-desde').value,
            fecha_hasta: $('rres-fecha-hasta').value,
            id_mesa: $('rres-mesa').value,
            id_usuario: $('rres-mesero').value,
            id_menu_item: $('rres-menu-item').value,
            id_categoria: $('rres-categoria').value,
            id_forma_pago: $('rres-forma-pago').value,
        };
    }

    async function generar() {
        const f = filtros();
        const tbody = $('rres-tbody');
        const vp = f.ver_por;
        $('rres-head-mesas').classList.toggle('d-none', vp !== 'MESAS');
        $('rres-head-mesero').classList.toggle('d-none', vp !== 'MESERO');
        $('rres-head-forma-pago').classList.toggle('d-none', vp !== 'FORMA_PAGO');
        $('rres-head-menu').classList.toggle('d-none', vp !== 'MENU');
        $('rres-head-categoria').classList.toggle('d-none', vp !== 'CATEGORIA');

        const colspan = COLSPAN[vp] || 5;
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando…</td></tr>`;
        $('rres-count-label').textContent = '';
        habilitarAcciones(false);

        try {
            const params = new URLSearchParams(f);
            const res = await fetch(`${BASE}/${RUTA}/generarAjax?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) { tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-4">${json.error || 'Error'}</td></tr>`; return; }

            tbody.innerHTML = json.rows;
            $('rres-kpi-comandas').textContent   = json.stats.cantidad_comandas ?? 0;
            $('rres-kpi-documentos').textContent = json.stats.cantidad_documentos ?? 0;
            $('rres-kpi-total').textContent      = money(json.stats.total_vendido);

            $('rres-count-label').textContent = json.total === 1
                ? '1 resultado'
                : `${json.total} resultados`;

            habilitarAcciones(json.total > 0);
            $('rresBtnPdf').onclick   = () => window.open(json.pdf_url, '_blank');
            $('rresBtnExcel').onclick = () => window.open(json.excel_url, '_blank');
            // La tirilla se arma en el servidor con los mismos filtros: se abre
            // en una ventana angosta que se imprime sola, igual que las del POS.
            $('rresBtnTirilla').onclick = () => window.open(
                `${BASE}/${RUTA}/imprimirTirilla?${new URLSearchParams(f).toString()}`,
                '_blank', 'width=320,height=600,scrollbars=yes'
            );
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-4">Error de comunicación.</td></tr>`;
        }
    }

    /** Los cuatro botones de salida solo sirven con resultados en pantalla. */
    function habilitarAcciones(activo) {
        ['rresBtnPdf', 'rresBtnExcel', 'rresBtnTirilla', 'rresBtnCorreo']
            .forEach(id => { $(id).disabled = !activo; });
    }

    $('rresBtnGenerar').addEventListener('click', generar);
    $('rres-ver-por').addEventListener('change', generar);
    $('rresBtnLimpiar').addEventListener('click', () => {
        $('rres-fecha-desde').value = '<?= date('Y-m-01') ?>';
        $('rres-fecha-hasta').value = '<?= date('Y-m-d') ?>';
        $('rres-ver-por').value = 'MESAS';
        $('rres-mesa').value = '';
        $('rres-mesero').value = '';
        $('rres-menu-item').value = '';
        $('rres-categoria').value = '';
        $('rres-forma-pago').value = '';
        generar();
    });

    // ─── Enviar por correo ──────────────────────────────────────────────────
    const modalCorreo = new bootstrap.Modal('#rresModalCorreo');
    const CORREO_EMPRESA = <?= json_encode($correoEmpresa ?? '', JSON_UNESCAPED_UNICODE) ?>;

    $('rresBtnCorreo').addEventListener('click', () => {
        // Se precarga el correo de la empresa solo si el campo está vacío: así
        // no se pisa lo que el usuario haya escrito y cerrado sin enviar, pero
        // tras un envío (que lo limpia) vuelve a aparecer.
        if (!$('rres-correos').value.trim() && CORREO_EMPRESA) {
            $('rres-correos').value = CORREO_EMPRESA;
        }
        modalCorreo.show();
    });

    $('rresBtnCorreoEmpresa')?.addEventListener('click', () => {
        $('rres-correos').value = CORREO_EMPRESA;
        $('rres-correos').focus();
    });

    $('rresBtnEnviarCorreo').addEventListener('click', async () => {
        const correos = $('rres-correos').value.trim();
        if (!correos) {
            Swal.fire({ icon: 'warning', title: 'Falta el destinatario', text: 'Escriba al menos un correo.' });
            return;
        }

        const btn = $('rresBtnEnviarCorreo');
        const htmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';

        try {
            const fd = new FormData();
            // Se mandan los filtros vigentes: el correo lleva exactamente lo que
            // se está viendo en pantalla, no el reporte completo.
            Object.entries(filtros()).forEach(([k, v]) => fd.append(k, v));
            fd.append('correos', correos);

            const res = await fetch(`${BASE}/${RUTA}/enviarCorreoAjax`, {
                method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const json = await res.json();

            if (json.ok) {
                modalCorreo.hide();
                $('rres-correos').value = '';
                Swal.fire({ icon: 'success', title: 'Reporte enviado', text: json.mensaje, timer: 3000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo enviar', text: json.mensaje || 'Error desconocido.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de comunicación', text: 'No se pudo contactar con el servidor.' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = htmlOriginal;
        }
    });

    generar(); // primera carga
})();
</script>
