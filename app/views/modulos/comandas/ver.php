<?php
/**
 * Comanda de una mesa — Punto de Venta modo Restaurante. Página STANDALONE
 * (sin layout principal), mismo criterio que caja_sesion/venta.php: selector
 * de productos + carrito, pero el carrito vive en el servidor (comanda_detalle)
 * en vez de memoria del navegador, porque varias rondas se agregan a lo largo
 * del servicio y cualquier dispositivo debe poder verlas.
 *
 * Fase 1: solo agregar/anular ítems y anular la comanda completa. El cobro
 * (genera Factura/Recibo, con posible división de cuenta) llega en una fase
 * posterior.
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var array  $perm
 * @var array  $comanda
 * @var array  $bodegas
 * @var array  $empresaConfig
 */
$base = rtrim(BASE_URL ?? '', '/');
$rutaAjax = $base . '/' . $rutaModulo;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { background: #f4f6f9; overflow: hidden; }
        .cm-wrap { display: flex; flex-direction: column; height: 100vh; }
        .cm-header { flex: 0 0 auto; }
        .cm-body { flex: 1 1 auto; min-height: 0; display: flex; }

        .cm-catalogo { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
        .cm-search { flex: 0 0 auto; padding: 8px 16px 6px; background: #fff; border-bottom: 1px solid #dee2e6; }
        .cm-grid { flex: 1 1 auto; overflow-y: auto; padding: 14px; display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; align-content: start; }
        .cm-tile { position: relative; background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 12px 10px; cursor: pointer; text-align: left; transition: border-color .15s; }
        .cm-tile:hover { border-color: #0d6efd; }
        .cm-tile .thumb { width: 100%; height: 56px; object-fit: contain; border-radius: 6px; margin-bottom: 8px; background: #f4f6f9; display: block; }
        .cm-tile .nombre { font-size: .82rem; font-weight: 600; line-height: 1.25; margin-bottom: 6px; min-height: 2.1em; }
        .cm-tile .precio-row { display: flex; align-items: baseline; gap: 5px; }
        .cm-tile .precio { font-size: .82rem; color: #0d6efd; font-weight: 700; }
        .cm-tile .iva-tag { font-size: .64rem; color: #8a94a6; }
        .cm-tile .iva-tag.iva-cero { color: #b5792c; }
        .cm-tile .dest { font-size: .68rem; color: #8a94a6; }
        .cm-tile .dest.menu-tag { color: #b5792c; font-weight: 600; }
        .cm-empty { color: #8a94a6; }

        .cm-comanda { width: 360px; max-width: 42%; background: #fff; border-left: 1px solid #dee2e6; display: flex; flex-direction: column; }
        .cm-lineas { flex: 1 1 auto; overflow-y: auto; padding: 10px 14px; }
        .cm-linea { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .cm-linea.anulado { opacity: .45; text-decoration: line-through; }
        .cm-linea .desc { flex: 1 1 auto; min-width: 0; }
        .cm-linea .desc .n { font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cm-linea .desc .p { font-size: .72rem; color: #8a94a6; }
        .cm-linea .desc .estado { font-size: .64rem; margin-top: 2px; }
        .cm-linea .total { font-size: .82rem; font-weight: 600; min-width: 56px; text-align: right; }
        .cm-linea .rm { color: #dc3545; cursor: pointer; }
        .cm-linea .entregar { font-size: .68rem; padding: 2px 8px; }
        .cm-linea .btn-desc { width: 26px; height: 22px; line-height: 1; padding: 0; font-size: .68rem; flex-shrink: 0; }
        .cm-totales { flex: 0 0 auto; padding: 12px 16px; border-top: 1px dashed #dee2e6; font-size: .85rem; }
        .cm-totales .row div { display: flex; justify-content: space-between; padding: 2px 0; }
        .cm-totales .row.total div { font-size: 1.15rem; font-weight: 700; border-top: 1px solid #dee2e6; margin-top: 6px; padding-top: 8px; }
        .cm-footer { flex: 0 0 auto; padding: 0 16px 16px; }

        .cm-grupo { border: 1px solid #dee2e6; border-radius: 8px; padding: 6px 10px; margin-bottom: 6px; font-size: .78rem; display: flex; align-items: center; justify-content: space-between; gap: 6px; }
        .cm-grupo .et { font-weight: 600; }
        .cm-grupo .doc { color: #8a94a6; font-size: .68rem; }

        .cb-linea { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: .85rem; }
        .cb-linea .desc { flex: 1 1 auto; }
        .cb-linea .total { font-weight: 600; }

        #pg-cliente-resultados { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; max-height: 220px; overflow-y: auto; }
        #pg-cliente-resultados .list-group-item { cursor: pointer; font-size: .82rem; }
        #pg-cliente-resultados:empty { display: none; border: none; }
    </style>
</head>
<body>
<div class="cm-wrap">
    <div class="cm-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <a href="<?= $base ?>/modulos/mesas/tablero" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i></a>
            <i class="bi bi-shop fs-5"></i>
            <div>
                <div class="fw-semibold lh-1">Mesa <?= htmlspecialchars($comanda['mesa_nombre'] ?? '') ?></div>
                <small class="text-white-50"><?= htmlspecialchars($comanda['numero_comanda'] ?? '') ?> &middot; <?= htmlspecialchars($comanda['mesero_nombre'] ?? '') ?></small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($perm['crear']) && ($comanda['estado'] ?? '') === 'abierta'): ?>
                <button type="button" class="btn btn-sm btn-warning position-relative" id="cm-btn-enviar-cocina" disabled>
                    <i class="bi bi-send me-1"></i>Enviar a preparación
                    <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle d-none" id="cm-badge-pendientes">0</span>
                </button>
            <?php endif; ?>
            <?php if (!empty($perm['eliminar']) && ($comanda['estado'] ?? '') === 'abierta'): ?>
                <button type="button" class="btn btn-sm btn-outline-light" id="cm-btn-anular"><i class="bi bi-x-circle me-1"></i>Anular comanda</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="cm-body">
        <div class="cm-catalogo">
            <div class="cm-search">
                <div class="row g-2 align-items-end mb-2">
                    <?php if (count($bodegas) > 1): ?>
                    <div class="col-auto" style="width:170px;">
                        <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Bodega</label>
                        <select id="cm-id-bodega" class="form-select form-select-sm">
                            <?php foreach ($bodegas as $b): ?>
                                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col">
                        <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                            <input type="text" class="form-control" id="cm-buscar" placeholder="Buscar producto o escanear código de barras..." autofocus autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="btn-group btn-group-sm" role="group" id="cm-filtro-origen">
                    <input type="radio" class="btn-check" name="cm-origen" id="cm-origen-todos" value="todos" checked>
                    <label class="btn btn-outline-secondary" for="cm-origen-todos">Todos</label>
                    <input type="radio" class="btn-check" name="cm-origen" id="cm-origen-menu" value="menu">
                    <label class="btn btn-outline-secondary" for="cm-origen-menu"><i class="bi bi-book me-1"></i>Menú</label>
                    <input type="radio" class="btn-check" name="cm-origen" id="cm-origen-producto" value="producto">
                    <label class="btn btn-outline-secondary" for="cm-origen-producto"><i class="bi bi-box-seam me-1"></i>Stock general</label>
                </div>
            </div>
            <div class="cm-grid" id="cm-grid">
                <div class="text-center py-4 cm-empty" style="grid-column: 1 / -1;"><span class="spinner-border spinner-border-sm"></span> Cargando catálogo...</div>
            </div>
        </div>
        <div class="cm-comanda">
            <div id="cm-aviso-asistencia" class="alert alert-danger alert-sm py-2 px-3 mb-0 rounded-0 d-none d-flex align-items-center justify-content-between" style="font-size:.8rem;">
                <span><i class="bi bi-hand-index-thumb me-1"></i>El cliente pidió que se acerque un mesero</span>
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" id="cm-btn-atender-asistencia">Atendido</button>
            </div>
            <div id="cm-aviso-listos" class="alert alert-success alert-sm py-2 px-3 mb-0 rounded-0 d-none" style="font-size:.8rem;">
                <i class="bi bi-check2-circle me-1"></i><span id="cm-aviso-listos-texto"></span>
            </div>
            <div class="cm-lineas" id="cm-lineas"></div>
            <div id="cm-grupos" class="px-3"></div>
            <div class="cm-totales">
                <div class="row total"><div><span>Total</span><span id="cm-total">$0.00</span></div></div>
            </div>
            <?php if (!empty($perm['crear']) && ($comanda['estado'] ?? '') === 'abierta'): ?>
            <div class="cm-footer d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="cm-btn-vista-previa" title="Imprimir la cuenta para que el cliente la revise antes de pagar (no es un documento válido)" disabled>
                    <i class="bi bi-receipt"></i>
                </button>
                <button type="button" class="btn btn-success flex-grow-1" id="cm-btn-cobrar"><i class="bi bi-cash-coin me-1"></i>Cobrar</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: seleccionar ítems a cobrar (split por ítems) -->
<div class="modal fade" id="mdCobro" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-receipt me-1"></i>Cobrar cuenta</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold small">Ítems sin cobrar</div>
          <button type="button" class="btn btn-sm btn-link p-0" id="cb-toggle-todos">Marcar/desmarcar todos</button>
        </div>
        <div id="cb-lista-lineas" class="mb-3"></div>
        <div class="d-flex justify-content-between fw-semibold border-top pt-2">
          <span>Total seleccionado</span><span id="cb-total-sel">$0.00</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-success btn-sm" id="cb-btn-armar"><i class="bi bi-check2-square me-1"></i>Cobrar seleccionados</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: forma de pago / documento para un grupo de cobro -->
<div class="modal fade" id="mdPago" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-cash-coin me-1"></i>Registrar cobro</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 fw-semibold" id="pg-monto"></div>
        <div class="mb-2">
          <label class="form-label small mb-1">Documento</label>
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="pg-tipo-doc" id="pg-doc-recibo" value="RECIBO" checked>
            <label class="btn btn-outline-primary btn-sm" for="pg-doc-recibo">Recibo de venta</label>
            <input type="radio" class="btn-check" name="pg-tipo-doc" id="pg-doc-factura" value="FACTURA">
            <label class="btn btn-outline-primary btn-sm" for="pg-doc-factura">Factura</label>
          </div>
        </div>
        <div class="mb-2 position-relative">
          <label class="form-label small mb-1">Cliente (opcional; Consumidor Final si se deja vacío)</label>
          <input type="text" class="form-control form-control-sm" id="pg-cliente-buscar" placeholder="Buscar cliente por nombre/identificación...">
          <input type="hidden" id="pg-id-cliente" value="0">
          <div id="pg-cliente-resultados" class="list-group position-absolute w-100" style="z-index:1080;"></div>
        </div>
        <div id="pg-aviso-cf" class="small text-danger mb-2 d-none"></div>
        <div class="mb-2">
          <label class="form-label small mb-1">Forma de pago</label>
          <select class="form-select form-select-sm" id="pg-forma-pago"></select>
        </div>
        <div id="pg-banco-wrap" class="d-none border rounded-2 p-2 bg-light">
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small mb-1">Op. Bancaria</label>
              <select class="form-select form-select-sm" id="pg-tipo-op-banco">
                <option value="TRANSFERENCIA" selected>Transferencia</option>
                <option value="DEPOSITO">Depósito</option>
                <option value="DEBITO">Débito</option>
                <option value="CHEQUE">Cheque</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small mb-1">Nº Ref. / Cheque</label>
              <input type="text" class="form-control form-control-sm" id="pg-numero-operacion" placeholder="Opcional">
            </div>
            <div class="col-6 d-none" id="pg-fecha-cobro-wrap">
              <label class="form-label small mb-1">Fecha de cobro</label>
              <input type="date" class="form-control form-control-sm" id="pg-fecha-cobro">
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="pg-btn-confirmar"><i class="bi bi-check-lg me-1"></i>Confirmar cobro</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: lote/caducidad (mismo patrón que caja_sesion/venta.php) -->
<div class="modal fade" id="modalLote" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-boxes me-1"></i>Selecciona el lote</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="modalLoteProducto"></p>
                <select id="modalLoteSelect" class="form-select"></select>
                <div id="modalLoteCaducidadWrap" class="d-none">
                    <label class="form-label small fw-semibold text-uppercase text-muted mt-3 mb-1">Fecha de caducidad</label>
                    <input type="date" id="modalLoteCaducidad" class="form-control form-control-sm">
                    <div class="form-text">Este lote no trae caducidad registrada; la empresa la exige.</div>
                </div>
                <div id="modalLoteNupWrap" class="d-none">
                    <label class="form-label small fw-semibold text-uppercase text-muted mt-3 mb-1">Número de serie (NUP)</label>
                    <input type="text" id="modalLoteNup" class="form-control form-control-sm" placeholder="Escanea o escribe el número de serie" autocomplete="off">
                    <div class="form-text">Este producto exige un número de serie por unidad.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="modalLoteConfirmar">Agregar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: NUP solo (sin lote) -->
<div class="modal fade" id="modalNup" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-upc me-1"></i>Número de serie</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="modalNupProducto"></p>
                <input type="text" id="modalNupInput" class="form-control" placeholder="Escanea o escribe el número de serie" autocomplete="off">
                <div class="form-text">Este producto exige un número de serie por unidad — cada unidad queda como una línea propia.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="modalNupConfirmar">Agregar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const BASE = "<?= $base ?>";
    const AJAX = BASE + '/modulos/comandas';
    const ID_COMANDA = <?= (int) $comanda['id'] ?>;
    // Mismos datos que EMPRESA_INFO en caja_sesion/venta.php — para armar el
    // encabezado de la tirilla de impresión (imprimirTicketPos), sin llamada extra.
    const EMPRESA_INFO = {
        nombre: <?= json_encode($empresaConfig['nombre'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        nombre_comercial: <?= json_encode($empresaConfig['nombre_comercial'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        ruc: <?= json_encode($empresaConfig['ruc'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        direccion: <?= json_encode($empresaConfig['direccion'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        telefono: <?= json_encode($empresaConfig['telefono'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        logo: <?= json_encode($empresaConfig['logo'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    };
    const PUEDE_CREAR = <?= !empty($perm['crear']) ? 'true' : 'false' ?>;
    const PUEDE_ACTUALIZAR = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
    // Si solo hay una bodega (o ninguna), no se muestra selector — se usa esa fija.
    const ID_BODEGA_FIJA = <?= count($bodegas) === 1 ? (int) $bodegas[0]['id'] : 'null' ?>;
    function getIdBodega() {
        const sel = document.getElementById('cm-id-bodega');
        return sel ? parseInt(sel.value, 10) : ID_BODEGA_FIJA;
    }
    <?php $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1'); ?>
    // Mismas reglas de Facturación (empresa → Facturación) que ya exige el POS mostrador.
    const OBLIGATORIO_LOTES = <?= $toBool($empresaConfig['obligatorio_lotes'] ?? false) ? 'true' : 'false' ?>;
    const OBLIGATORIO_CADUCIDAD = <?= $toBool($empresaConfig['obligatorio_caducidad'] ?? false) ? 'true' : 'false' ?>;
    const OBLIGATORIO_NUP = <?= $toBool($empresaConfig['obligatorio_nup'] ?? false) ? 'true' : 'false' ?>;
    // Venta a Consumidor Final: mismo límite configurado en empresa → Facturación (PosVentaService lo vuelve a exigir al cobrar).
    const LIMITE_CONSUMIDOR_FINAL = <?= (float) ($empresaConfig['valor_limite_consumidor_final'] ?? 50) ?>;
    const $grid = document.getElementById('cm-grid');
    const $buscar = document.getElementById('cm-buscar');
    const $lineas = document.getElementById('cm-lineas');
    const $total = document.getElementById('cm-total');
    const $grupos = document.getElementById('cm-grupos');
    let detalles = <?= json_encode($comanda['detalles'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    let grupos = <?= json_encode($comanda['grupos'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    let solicitaAsistencia = <?= !empty($comanda['solicita_asistencia']) ? 'true' : 'false' ?>;
    let buscarTimer = null;
    let formasPago = null;
    let idGrupoEnPago = null;
    let montoEnPago = 0;
    let clienteTimer = null;

    function money(v) { return '$' + (parseFloat(v || 0)).toFixed(2); }
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function swalWarning(html) {
        Swal.fire({ icon: 'warning', title: 'Atención', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function swalToast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2200, timerProgressBar: true });
    }

    const ESTADO_LABEL = {
        pendiente: ['Sin enviar', 'secondary'], enviado: ['En preparación', 'info'],
        preparando: ['Preparando', 'warning'], listo: ['Listo para servir', 'success'],
        entregado: ['Entregado', 'secondary'],
    };

    function renderLineas() {
        const vivas = detalles.filter(d => d.estado_linea !== 'anulado');
        if (!vivas.length && !detalles.length) {
            $lineas.innerHTML = '<div class="cm-empty p-3 text-center">Aún no hay ítems.</div>';
        } else {
            $lineas.innerHTML = detalles.map(d => {
                // "En preparación" solo tiene sentido si el ítem realmente pasa por una
                // estación (cocina/barra); si no tiene, se saltó directo a poder entregarse
                // y ese estado intermedio no le aplica — no se muestra ninguna etiqueta.
                const sinEstacionEnviado = !d.id_estacion_impresion && d.estado_linea === 'enviado';
                const [label, color] = sinEstacionEnviado ? [null, null] : (ESTADO_LABEL[d.estado_linea] || ['—', 'secondary']);
                const descuento = parseFloat(d.descuento || 0);
                const base = parseFloat(d.precio_unitario || 0) * parseFloat(d.cantidad || 0);
                const descTag = descuento > 0 ? ' <span class="badge bg-danger bg-opacity-10 text-danger">-' + money(descuento) + '</span>' : '';
                const totalHtml = descuento > 0
                    ? '<span class="text-decoration-line-through text-muted small d-block">' + money(base) + '</span>' + money(d.subtotal)
                    : money(d.subtotal);
                const puedeEditar = d.estado_linea !== 'anulado' && !d.id_grupo_cobro && PUEDE_ACTUALIZAR;
                // Sin estación configurada = no hay nada que preparar (ej. una bebida embotellada):
                // se puede entregar directo, sin esperar a que cocina/barra lo marque 'listo'.
                const puedeEntregar = PUEDE_ACTUALIZAR && !['entregado', 'anulado'].includes(d.estado_linea)
                    && (d.estado_linea === 'listo' || !d.id_estacion_impresion);
                return `
                <div class="cm-linea ${d.estado_linea === 'anulado' ? 'anulado' : ''}">
                    <div class="desc">
                        <div class="n">${escapeHtml(d.cantidad)} x ${escapeHtml(d.descripcion)}${descTag}</div>
                        ${d.observacion_item ? '<div class="p">' + escapeHtml(d.observacion_item) + '</div>' : ''}
                        ${d.estado_linea !== 'anulado' && label ? '<div class="estado"><span class="badge bg-' + color + '-subtle text-' + color + '-emphasis">' + label + '</span></div>' : ''}
                        ${puedeEntregar ? '<button type="button" class="btn btn-sm btn-success entregar mt-1" data-id="' + d.id + '"><i class="bi bi-check2-circle me-1"></i>Entregar</button>' : ''}
                        ${d.estado_linea === 'anulado' && PUEDE_ACTUALIZAR ? '<button type="button" class="btn btn-sm btn-outline-secondary restaurar mt-1" data-id="' + d.id + '"><i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar</button>' : ''}
                    </div>
                    ${puedeEditar ? '<button type="button" class="btn btn-outline-secondary btn-desc" data-id="' + d.id + '" title="Aplicar descuento"><i class="bi bi-percent"></i></button>' : ''}
                    <div class="total">${totalHtml}</div>
                    ${puedeEditar ? '<span class="rm" data-id="' + d.id + '" title="Eliminar ítem"><i class="bi bi-x-lg"></i></span>' : ''}
                </div>`;
            }).join('');
        }
        const total = vivas.reduce((a, d) => a + parseFloat(d.subtotal || 0), 0);
        $total.textContent = money(total);
        renderGrupos();
        actualizarBadgePendientes();
        actualizarAvisoListos();
        actualizarAvisoAsistencia();
        const $btnVistaPrevia = document.getElementById('cm-btn-vista-previa');
        if ($btnVistaPrevia) $btnVistaPrevia.disabled = vivas.length === 0;
    }

    // Aviso persistente (no un toast que se pierde) de ítems que cocina/barra
    // ya dejó listos y siguen esperando que el mesero los entregue.
    function actualizarAvisoListos() {
        const listos = detalles.filter(d => d.estado_linea === 'listo').length;
        const $aviso = document.getElementById('cm-aviso-listos');
        if (!$aviso) return;
        if (listos > 0) {
            document.getElementById('cm-aviso-listos-texto').textContent =
                listos === 1 ? '1 ítem listo para entregar.' : listos + ' ítems listos para entregar.';
            $aviso.classList.remove('d-none');
        } else {
            $aviso.classList.add('d-none');
        }
    }

    function actualizarAvisoAsistencia() {
        const $aviso = document.getElementById('cm-aviso-asistencia');
        if (!$aviso) return;
        $aviso.classList.toggle('d-none', !solicitaAsistencia);
    }

    document.getElementById('cm-btn-atender-asistencia')?.addEventListener('click', async (ev) => {
        const $btn = ev.currentTarget;
        $btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id', ID_COMANDA);
            const r = await fetch(AJAX + '/atenderAsistenciaAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo marcar como atendido.'); $btn.disabled = false; return; }
            solicitaAsistencia = false;
            actualizarAvisoAsistencia();
        } catch (e) { swalError('Error de conexión.'); }
        finally { $btn.disabled = false; }
    });

    // Cuántas líneas todavía no se enviaron a su estación de preparación
    // (cocina, barra, o cualquiera de las que el restaurante haya creado) —
    // el botón manda TODAS de una, sin importar a cuántas estaciones
    // distintas terminen repartiéndose.
    function actualizarBadgePendientes() {
        if (!$btnEnviarCocina) return;
        const pendientes = detalles.filter(d => d.estado_linea === 'pendiente').length;
        const $badge = document.getElementById('cm-badge-pendientes');
        if ($badge) {
            $badge.textContent = pendientes;
            $badge.classList.toggle('d-none', pendientes === 0);
        }
        $btnEnviarCocina.disabled = pendientes === 0;
    }

    const ESTADO_GRUPO_LABEL = { pendiente: ['Por cobrar', 'secondary'], cobrado: ['Cobrado', 'success'], anulado: ['Anulado', 'danger'] };

    function renderGrupos() {
        if (!grupos.length) { $grupos.innerHTML = ''; return; }
        $grupos.innerHTML = grupos.map(g => {
            const [label, color] = ESTADO_GRUPO_LABEL[g.estado] || ['—', 'secondary'];
            const monto = (g.lineas || []).reduce((a, l) => a + parseFloat(l.subtotal || 0), 0);
            const doc = g.estado === 'cobrado' ? `<div class="doc">${escapeHtml(g.tipo_documento || '')} ${escapeHtml(g.numero_documento || '')}</div>` : '';
            const solicitud = (g.origen === 'qr' && g.estado === 'pendiente')
                ? `<div class="doc"><i class="bi bi-qrcode me-1"></i>Pedido desde el QR — ${escapeHtml(g.cliente_nombre || '')} (${g.tipo_documento_solicitado === 'FACTURA' ? 'Factura' : 'Recibo'})</div>`
                : '';
            const btns = g.estado === 'pendiente' && PUEDE_CREAR ? `
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-success py-0 px-2 cb-cobrar-grupo" data-id="${g.id}" data-monto="${monto}" data-id-cliente="${g.id_cliente || ''}" data-cliente-nombre="${escapeHtml(g.cliente_nombre || '')}" data-tipo-doc="${g.tipo_documento_solicitado || ''}">Cobrar</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 cb-deshacer-grupo" data-id="${g.id}" title="Deshacer"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>` : (g.estado === 'cobrado' ? `
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 cb-imprimir-grupo" data-id-doc="${g.id_documento}" data-tipo-doc="${g.tipo_documento}" title="Imprimir tirilla"><i class="bi bi-receipt"></i></button>` : '');
            return `<div class="cm-grupo">
                <div>
                    <div class="et">${escapeHtml(g.etiqueta || ('Cuenta ' + g.numero_grupo))} <span class="badge bg-${color}-subtle text-${color}-emphasis">${label}</span></div>
                    <div class="doc">${money(monto)}</div>
                    ${doc}
                    ${solicitud}
                </div>
                ${btns}
            </div>`;
        }).join('');
    }

    async function refrescarComanda() {
        try {
            const r = await fetch(AJAX + '/verAjax'); // sin id: el servidor lee la comanda "actual" de sesión
            const d = await r.json();
            if (d.ok) {
                detalles = d.data.detalles || [];
                grupos = d.data.grupos || [];
                solicitaAsistencia = !!d.data.solicita_asistencia;
                renderLineas();
                if (d.data.estado === 'cerrada') {
                    swalToast('success', 'Cuenta cerrada; la mesa quedó disponible.');
                    setTimeout(() => { window.location.href = BASE + '/modulos/mesas/tablero'; }, 1500);
                }
            }
        } catch (e) { /* silencioso */ }
    }

    /**
     * Descuento por línea (Porcentaje o Valor fijo, con opción de aplicar a
     * todas las líneas editables) — mismo modal que "Aplicar descuento" del
     * POS mostrador, pero cada cambio se guarda de inmediato en el servidor
     * (la línea de la comanda ya existe, no vive en un carrito en memoria).
     */
    async function abrirDescuentoLinea(idLinea) {
        const linea = detalles.find(d => d.id === idLinea);
        if (!linea) return;

        const descActual = parseFloat(linea.descuento || 0);
        const esValorActual = descActual > 0;

        const res = await Swal.fire({
            title: 'Aplicar descuento',
            html: '<div class="text-start">' +
                  '<div class="btn-group w-100 mb-2" role="group">' +
                  '<input type="radio" class="btn-check" name="cm-desc-tipo" id="cm-desc-porc" value="P"' + (esValorActual ? '' : ' checked') + '>' +
                  '<label class="btn btn-outline-primary btn-sm" for="cm-desc-porc">Porcentaje (%)</label>' +
                  '<input type="radio" class="btn-check" name="cm-desc-tipo" id="cm-desc-val" value="V"' + (esValorActual ? ' checked' : '') + '>' +
                  '<label class="btn btn-outline-primary btn-sm" for="cm-desc-val">Valor ($)</label>' +
                  '</div>' +
                  '<label class="form-label small fw-semibold text-uppercase text-muted mb-1">Descuento</label>' +
                  '<input type="number" id="cm-desc-input" class="form-control form-control-sm" value="' + descActual + '" step="any" min="0">' +
                  '<div class="form-check form-switch mt-2">' +
                  '<input class="form-check-input" type="checkbox" id="cm-desc-todo">' +
                  '<label class="form-check-label small" for="cm-desc-todo">Aplicar a todos los ítems de la comanda</label>' +
                  '</div>' +
                  '</div>',
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            focusConfirm: false,
            didOpen: () => {
                const $input = document.getElementById('cm-desc-input');
                $input.focus();
                $input.select();
            },
            preConfirm: () => {
                const tipo = document.querySelector('input[name="cm-desc-tipo"]:checked').value;
                const valor = parseFloat(document.getElementById('cm-desc-input').value) || 0;
                const todo = document.getElementById('cm-desc-todo').checked;
                if (valor < 0) { Swal.showValidationMessage('El descuento no puede ser negativo.'); return false; }
                if (tipo === 'P' && valor > 100) { Swal.showValidationMessage('El porcentaje no puede ser mayor a 100.'); return false; }
                return { tipo, valor, todo };
            },
        });
        if (!res.isConfirmed) return;

        const { tipo, valor, todo } = res.value;
        const calcularDescuento = (d) => {
            const base = parseFloat(d.precio_unitario || 0) * parseFloat(d.cantidad || 0);
            return tipo === 'P' ? Math.round(base * valor) / 100 : Math.min(valor, base);
        };
        const objetivo = todo
            ? detalles.filter(d => d.estado_linea !== 'anulado' && !d.id_grupo_cobro)
            : [linea];

        try {
            for (const d of objetivo) {
                const fd = new FormData();
                fd.append('id_linea', d.id);
                fd.append('id_comanda', ID_COMANDA);
                fd.append('descuento', calcularDescuento(d));
                const r = await fetch(AJAX + '/actualizarDescuentoLineaAjax', { method: 'POST', body: fd });
                const dJson = await r.json();
                if (!dJson.ok) { swalError(dJson.error || 'No se pudo aplicar el descuento.'); break; }
            }
            await refrescarComanda();
        } catch (e) { swalError('Error de conexión.'); }
    }

    $lineas.addEventListener('click', async (ev) => {
        const rm = ev.target.closest('.rm');
        const entregar = ev.target.closest('.entregar');
        const restaurar = ev.target.closest('.restaurar');
        const desc = ev.target.closest('.btn-desc');
        if (desc) { abrirDescuentoLinea(parseInt(desc.dataset.id, 10)); return; }
        if (rm) {
            const fd = new FormData();
            fd.append('id_linea', rm.dataset.id);
            fd.append('id_comanda', ID_COMANDA);
            try {
                const r = await fetch(AJAX + '/anularLineaAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo quitar el ítem.'); return; }
                await refrescarComanda();
            } catch (e) { swalError('Error de conexión.'); }
            return;
        }
        if (restaurar) {
            const fd = new FormData();
            fd.append('id_linea', restaurar.dataset.id);
            fd.append('id_comanda', ID_COMANDA);
            restaurar.disabled = true;
            try {
                const r = await fetch(AJAX + '/restaurarLineaAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo restaurar el ítem.'); restaurar.disabled = false; return; }
                swalToast('success', 'Ítem restaurado.');
                await refrescarComanda();
            } catch (e) { swalError('Error de conexión.'); restaurar.disabled = false; }
            return;
        }
        if (entregar) {
            const fd = new FormData();
            fd.append('id_linea', entregar.dataset.id);
            entregar.disabled = true;
            try {
                const r = await fetch(AJAX + '/marcarEntregadoAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo marcar como entregado.'); entregar.disabled = false; return; }
                await refrescarComanda();
            } catch (e) { swalError('Error de conexión.'); entregar.disabled = false; }
        }
    });

    const $btnEnviarCocina = document.getElementById('cm-btn-enviar-cocina');
    if ($btnEnviarCocina) {
        $btnEnviarCocina.addEventListener('click', async () => {
            const fd = new FormData();
            fd.append('id_comanda', ID_COMANDA);
            $btnEnviarCocina.disabled = true;
            try {
                const r = await fetch(AJAX + '/enviarCocinaAjax', { method: 'POST', body: fd });
                const d = await r.json();
                // En éxito o error, refrescarComanda()/actualizarBadgePendientes() dejan
                // el botón habilitado o no según lo que realmente quede pendiente —
                // no se fuerza a "habilitado" a ciegas (podría quedar en 0 pendientes).
                if (!d.ok) { swalError(d.error || 'No se pudo enviar a preparación.'); actualizarBadgePendientes(); return; }
                swalToast('success', d.msg || 'Enviado');
                await refrescarComanda();
            } catch (e) { swalError('Error de conexión.'); actualizarBadgePendientes(); }
        });
    }

    function renderGrid(rows) {
        if (!rows.length) {
            $grid.innerHTML = '<div class="text-center py-4 cm-empty" style="grid-column: 1 / -1;"><i class="bi bi-box-seam fs-3 d-block mb-2"></i>Sin resultados.</div>';
            return;
        }
        $grid.innerHTML = '';
        rows.forEach(p => {
            const tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'cm-tile';
            tile._item = p; // objeto completo (conserva tipos: inventariable booleano, etc.)

            const thumbHtml = p.imagen ? `<img class="thumb" src="${BASE}/${escapeHtml(p.imagen)}" alt="" loading="lazy">` : '';
            const pctIva = parseFloat(p.porcentaje_iva || 0);
            const ivaTag = `<span class="iva-tag${pctIva === 0 ? ' iva-cero' : ''}">IVA ${pctIva}%</span>`;
            const precioConIva = parseFloat(p.precio_base || 0) * (1 + pctIva / 100);
            const destHtml = p.origen === 'menu'
                ? '<div class="dest menu-tag"><i class="bi bi-book me-1"></i>Menú</div>'
                : `<div class="dest">${escapeHtml(p.codigo || '')}</div>`;

            tile.innerHTML = thumbHtml +
                `<div class="nombre">${escapeHtml(p.nombre || '')}</div>` +
                `<div class="precio-row"><span class="precio">${money(precioConIva)}</span>${ivaTag}</div>` +
                destHtml;
            $grid.appendChild(tile);
        });
    }

    let catalogoCompleto = [];
    let filtroOrigen = 'todos';

    function aplicarFiltroOrigen() {
        const rows = filtroOrigen === 'todos' ? catalogoCompleto : catalogoCompleto.filter(p => p.origen === filtroOrigen);
        renderGrid(rows);
    }

    document.querySelectorAll('#cm-filtro-origen input[name="cm-origen"]').forEach(radio => {
        radio.addEventListener('change', () => {
            filtroOrigen = radio.value;
            aplicarFiltroOrigen();
        });
    });

    async function buscarProductos(q) {
        $grid.innerHTML = '<div class="text-center py-4 cm-empty" style="grid-column: 1 / -1;"><span class="spinner-border spinner-border-sm"></span> Buscando...</div>';
        try {
            const r = await fetch(AJAX + '/getProductosAjax?q=' + encodeURIComponent(q || '') + '&id_bodega=' + (getIdBodega() || ''));
            const d = await r.json();
            catalogoCompleto = d.ok ? d.data : [];
            aplicarFiltroOrigen();
        } catch (e) { $grid.innerHTML = '<div class="text-center py-4 text-danger" style="grid-column: 1 / -1;">Error al buscar.</div>'; }
    }

    document.getElementById('cm-id-bodega')?.addEventListener('change', () => buscarProductos($buscar.value.trim()));

    $buscar.addEventListener('input', () => {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(() => buscarProductos($buscar.value.trim()), 300);
    });

    // Los lectores de código de barras "escriben" el código y rematan con Enter
    // (mismo patrón que caja_sesion/venta.php).
    $buscar.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            escanearCodigo($buscar.value.trim());
            return;
        }
        // Backspace/Delete limpian el campo de una vez — útil para corregir un escaneo fallido rápido.
        if (ev.key === 'Backspace' || ev.key === 'Delete') {
            ev.preventDefault();
            $buscar.value = '';
            buscarProductos('');
        }
    });

    // ─── Lote / caducidad / NUP (mismas reglas de Facturación que el POS mostrador) ───

    function esInventariableControlado(p) {
        const inv = p.inventariable === true || p.inventariable === 't' || p.inventariable === 'true' || p.inventariable == 1;
        return inv && p.tipo_produccion !== '02';
    }
    function requiereLote(p) { return OBLIGATORIO_LOTES && esInventariableControlado(p); }
    function requiereNup(p) { return OBLIGATORIO_NUP && esInventariableControlado(p); }

    const modalLoteEl = document.getElementById('modalLote');
    const modalLote = modalLoteEl ? new bootstrap.Modal(modalLoteEl) : null;
    const modalNupEl = document.getElementById('modalNup');
    const modalNup = modalNupEl ? new bootstrap.Modal(modalNupEl) : null;

    function seleccionarLote(p, necesitaNup) {
        return fetch(AJAX + '/getLotesAjax?id_producto=' + p.id_producto + '&id_bodega=' + (getIdBodega() || ''))
            .then(res => res.json())
            .then(json => {
                const lotes = (json.data || []).filter(l => parseFloat(l.stock_lote || 0) > 0);
                if (!lotes.length) {
                    swalToast('warning', 'No hay stock con lote disponible para "' + p.nombre + '".');
                    return null;
                }
                const faltaCaducidad = OBLIGATORIO_CADUCIDAD && !lotes[0].fecha_caducidad;
                if (lotes.length === 1 && !faltaCaducidad && !necesitaNup) {
                    const l = lotes[0];
                    return { lote: l.numero_lote === 'sin_lote' ? '' : l.numero_lote, caducidad: l.fecha_caducidad || '', nup: '' };
                }
                return abrirModalLote(p, lotes, necesitaNup);
            })
            .catch(() => {
                swalError('Error de conexión al consultar los lotes de "' + p.nombre + '".');
                return null;
            });
    }

    function abrirModalLote(p, lotes, necesitaNup) {
        return new Promise(resolve => {
            document.getElementById('modalLoteProducto').textContent = p.nombre;
            const $sel = document.getElementById('modalLoteSelect');
            const $cadWrap = document.getElementById('modalLoteCaducidadWrap');
            const $cad = document.getElementById('modalLoteCaducidad');
            const $nupWrap = document.getElementById('modalLoteNupWrap');
            const $nup = document.getElementById('modalLoteNup');
            $sel.innerHTML = '';
            lotes.forEach(l => {
                const opt = document.createElement('option');
                opt.value = l.numero_lote;
                const cadTxt = l.fecha_caducidad ? (' · vence ' + l.fecha_caducidad) : '';
                opt.textContent = (l.numero_lote === 'sin_lote' ? 'Sin lote' : l.numero_lote) + ' — stock ' + l.stock_lote + cadTxt;
                $sel.appendChild(opt);
            });

            const sincronizarCaducidad = () => {
                const l = lotes.find(x => x.numero_lote === $sel.value);
                $cad.value = l?.fecha_caducidad || '';
            };
            if (OBLIGATORIO_CADUCIDAD) {
                $cadWrap.classList.remove('d-none');
                sincronizarCaducidad();
            } else {
                $cadWrap.classList.add('d-none');
                $cad.value = '';
            }
            $sel.addEventListener('change', sincronizarCaducidad);

            if (necesitaNup) {
                $nupWrap.classList.remove('d-none');
                $nup.value = '';
            } else {
                $nupWrap.classList.add('d-none');
            }

            let resuelto = false;
            const $btnConfirmar = document.getElementById('modalLoteConfirmar');
            const onConfirmar = () => {
                if (OBLIGATORIO_CADUCIDAD && !$cad.value) { $cad.focus(); return; }
                if (necesitaNup && !$nup.value.trim()) { $nup.focus(); return; }
                resuelto = true;
                const val = $sel.value;
                modalLote.hide();
                resolve({ lote: val === 'sin_lote' ? '' : val, caducidad: $cad.value || '', nup: necesitaNup ? $nup.value.trim() : '' });
            };
            const onHidden = () => {
                modalLoteEl.removeEventListener('hidden.bs.modal', onHidden);
                $btnConfirmar.removeEventListener('click', onConfirmar);
                $sel.removeEventListener('change', sincronizarCaducidad);
                if (!resuelto) resolve(null);
            };

            $btnConfirmar.addEventListener('click', onConfirmar);
            modalLoteEl.addEventListener('hidden.bs.modal', onHidden);
            modalLote.show();
        });
    }

    function capturarNup(p) {
        return new Promise(resolve => {
            document.getElementById('modalNupProducto').textContent = p.nombre;
            const $input = document.getElementById('modalNupInput');
            $input.value = '';

            let resuelto = false;
            const $btnConfirmar = document.getElementById('modalNupConfirmar');
            const confirmar = () => {
                const val = $input.value.trim();
                if (!val) { $input.focus(); return; }
                resuelto = true;
                modalNup.hide();
                resolve(val);
            };
            const onEnter = (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); confirmar(); } };
            const onHidden = () => {
                modalNupEl.removeEventListener('hidden.bs.modal', onHidden);
                $btnConfirmar.removeEventListener('click', confirmar);
                $input.removeEventListener('keydown', onEnter);
                if (!resuelto) resolve(null);
            };

            $btnConfirmar.addEventListener('click', confirmar);
            $input.addEventListener('keydown', onEnter);
            modalNupEl.addEventListener('hidden.bs.modal', onHidden);
            modalNup.show();
        });
    }

    async function agregarDesdeTile(p) {
        if (!PUEDE_CREAR) { swalError('No tienes permiso para agregar ítems.'); return; }

        const necesitaNup = requiereNup(p);
        let lote = '', caducidad = '', nup = '';
        if (requiereLote(p)) {
            const elegido = await seleccionarLote(p, necesitaNup);
            if (!elegido) return; // cancelado o sin stock
            lote = elegido.lote;
            caducidad = elegido.caducidad;
            nup = elegido.nup || '';
        } else if (necesitaNup) {
            const val = await capturarNup(p);
            if (val === null) return; // cancelado
            nup = val;
        }

        const fd = new FormData();
        fd.append('id_comanda', ID_COMANDA);
        if (p.id_menu_item) fd.append('id_menu_item', p.id_menu_item);
        if (p.id_producto) fd.append('id_producto', p.id_producto);
        fd.append('descripcion', p.nombre);
        fd.append('cantidad', '1');
        fd.append('precio_unitario', p.precio_base || 0);
        fd.append('lote', lote);
        fd.append('caducidad', caducidad);
        fd.append('nup', nup);
        return fetch(AJAX + '/agregarLineaAjax', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(async (d) => {
                if (!d.ok) { swalError(d.error || 'No se pudo agregar el ítem.'); return; }
                swalToast('success', 'Agregado');
                await refrescarComanda();
            })
            .catch(() => swalError('Error de conexión.'));
    }

    async function escanearCodigo(valor) {
        if (!valor) return;
        clearTimeout(buscarTimer);
        try {
            const r = await fetch(AJAX + '/getProductosAjax?q=' + encodeURIComponent(valor) + '&id_bodega=' + (getIdBodega() || ''));
            const d = await r.json();
            const rows = d.ok ? d.data : [];
            catalogoCompleto = rows;

            const valorNorm = valor.toLowerCase();
            let match = rows.find(p =>
                (p.codigo_barras || '').toLowerCase() === valorNorm ||
                (p.codigo || '').toLowerCase() === valorNorm ||
                (p.codigo_auxiliar || '').toLowerCase() === valorNorm
            );
            if (!match && rows.length === 1) match = rows[0];

            if (match) {
                await agregarDesdeTile(match);
                $buscar.value = '';
                buscarProductos('');
            } else if (rows.length > 1) {
                aplicarFiltroOrigen();
                swalToast('warning', 'Varios ítems coinciden con "' + valor + '" — elige uno de la lista.');
            } else {
                swalToast('warning', 'No se encontró ningún ítem con el código "' + valor + '".');
            }
        } catch (e) {
            swalToast('error', 'Error de conexión al buscar el código.');
        }
    }

    $grid.addEventListener('click', (ev) => {
        const tile = ev.target.closest('.cm-tile');
        if (!tile || !tile._item) return;
        agregarDesdeTile(tile._item);
    });

    const $btnAnular = document.getElementById('cm-btn-anular');
    if ($btnAnular) {
        $btnAnular.addEventListener('click', async () => {
            const { isConfirmed } = await Swal.fire({
                title: '¿Anular esta comanda?',
                text: 'La mesa quedará disponible y se perderán los ítems agregados.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (!isConfirmed) return;
            const fd = new FormData();
            fd.append('id', ID_COMANDA);
            try {
                const r = await fetch(AJAX + '/anularAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo anular.'); return; }
                window.location.href = BASE + '/modulos/mesas/tablero';
            } catch (e) { swalError('Error de conexión.'); }
        });
    }

    // ─── Cobro / división de cuenta ────────────────────────────────────────

    const mdCobro = document.getElementById('mdCobro') ? new bootstrap.Modal('#mdCobro') : null;
    const mdPago = document.getElementById('mdPago') ? new bootstrap.Modal('#mdPago') : null;
    const $cbLista = document.getElementById('cb-lista-lineas');
    const $cbTotalSel = document.getElementById('cb-total-sel');

    function renderListaCobro() {
        const sinGrupo = detalles.filter(d => d.estado_linea !== 'anulado' && !d.id_grupo_cobro);
        if (!sinGrupo.length) {
            $cbLista.innerHTML = '<div class="cm-empty small">No hay ítems pendientes de cobro.</div>';
        } else {
            $cbLista.innerHTML = sinGrupo.map(d => {
                // 'preparando' todavía no se puede cobrar (ver ComandaRules::validarLineaCobrable) —
                // se muestra pero deshabilitado, para que se entienda por qué falta en vez de desaparecer sin más.
                const enPreparacion = d.estado_linea === 'preparando';
                return `
                <label class="cb-linea${enPreparacion ? ' text-muted' : ''}">
                    <input type="checkbox" class="form-check-input cb-check" data-id="${d.id}" data-monto="${d.subtotal}" ${enPreparacion ? 'disabled' : 'checked'}>
                    <span class="desc">${escapeHtml(d.cantidad)} x ${escapeHtml(d.descripcion)}${enPreparacion ? ' <span class="badge bg-warning-subtle text-warning-emphasis">en preparación</span>' : ''}</span>
                    <span class="total">${money(d.subtotal)}</span>
                </label>`;
            }).join('');
        }
        recalcularSeleccion();
    }

    function recalcularSeleccion() {
        const total = Array.from($cbLista.querySelectorAll('.cb-check:checked'))
            .reduce((a, c) => a + parseFloat(c.dataset.monto || 0), 0);
        $cbTotalSel.textContent = money(total);
    }

    const $btnCobrar = document.getElementById('cm-btn-cobrar');
    if ($btnCobrar && mdCobro) {
        $btnCobrar.addEventListener('click', () => { renderListaCobro(); mdCobro.show(); });
    }

    $cbLista.addEventListener('change', (ev) => { if (ev.target.classList.contains('cb-check')) recalcularSeleccion(); });

    document.getElementById('cb-toggle-todos').addEventListener('click', () => {
        const boxes = Array.from($cbLista.querySelectorAll('.cb-check:not(:disabled)'));
        const algunoSinMarcar = boxes.some(b => !b.checked);
        boxes.forEach(b => b.checked = algunoSinMarcar);
        recalcularSeleccion();
    });

    async function cargarFormasPago() {
        if (formasPago) return formasPago;
        try {
            const r = await fetch(AJAX + '/getFormasPagoAjax');
            const d = await r.json();
            formasPago = d.ok ? d.data : [];
        } catch (e) { formasPago = []; }
        return formasPago;
    }

    // Venta a Consumidor Final: mismo límite que exige empresa → Facturación
    // (PosVentaService::cobrar() lo vuelve a exigir al cobrar; esto solo
    // avisa antes para no descubrirlo recién al confirmar).
    function revisarLimiteConsumidorFinal() {
        const $btn = document.getElementById('pg-btn-confirmar');
        const $aviso = document.getElementById('pg-aviso-cf');
        const sinCliente = (document.getElementById('pg-id-cliente').value || '0') === '0';
        const superaLimite = sinCliente && montoEnPago >= LIMITE_CONSUMIDOR_FINAL;
        if (superaLimite) {
            $aviso.textContent = 'Venta a Consumidor Final: máximo ' + money(LIMITE_CONSUMIDOR_FINAL) + '. Selecciona o crea un cliente para continuar.';
            $aviso.classList.remove('d-none');
        } else {
            $aviso.classList.add('d-none');
        }
        $btn.disabled = superaLimite;
    }

    async function abrirModalPago(idGrupo, monto, preset = {}) {
        idGrupoEnPago = idGrupo;
        montoEnPago = monto;
        document.getElementById('pg-monto').textContent = 'Total a cobrar: ' + money(monto);
        // Si el grupo viene de una solicitud por QR, el cliente y el tipo de
        // documento ya llegan resueltos — el mesero no tiene que volver a
        // pedírselos, solo confirma la forma de pago física.
        document.getElementById('pg-id-cliente').value = preset.idCliente || '0';
        document.getElementById('pg-cliente-buscar').value = preset.clienteNombre || '';
        document.getElementById('pg-cliente-resultados').innerHTML = '';
        document.getElementById('pg-numero-operacion').value = '';
        document.getElementById('pg-fecha-cobro').value = '';
        document.getElementById('pg-tipo-op-banco').value = 'TRANSFERENCIA';
        document.getElementById('pg-fecha-cobro-wrap').classList.add('d-none');
        document.getElementById(preset.tipoDocumento === 'FACTURA' ? 'pg-doc-factura' : 'pg-doc-recibo').checked = true;

        const $sel = document.getElementById('pg-forma-pago');
        const formas = await cargarFormasPago();
        $sel.innerHTML = formas.map(f => `<option value="${f.id}" data-tipo="${escapeHtml(f.tipo || '')}" data-cod="${f.codigo_sri}">${escapeHtml(f.nombre)}</option>`).join('');
        toggleCamposBanco();
        revisarLimiteConsumidorFinal();

        mdCobro && mdCobro.hide();
        mdPago && mdPago.show();
    }

    // Solo la forma de pago tipo BANCO pide precisar Transferencia/Depósito/
    // Débito/Cheque (mismo criterio que caja_sesion/venta.php) — Tarjeta y
    // Payphone no piden nada extra.
    function toggleCamposBanco() {
        const $sel = document.getElementById('pg-forma-pago');
        const opt = $sel.options[$sel.selectedIndex];
        const tipo = opt ? (opt.dataset.tipo || '').toUpperCase() : '';
        const esBanco = tipo === 'BANCO';
        document.getElementById('pg-banco-wrap').classList.toggle('d-none', !esBanco);
        if (!esBanco) {
            document.getElementById('pg-tipo-op-banco').value = 'TRANSFERENCIA';
            document.getElementById('pg-numero-operacion').value = '';
            document.getElementById('pg-fecha-cobro-wrap').classList.add('d-none');
            document.getElementById('pg-fecha-cobro').value = '';
        }
    }
    document.getElementById('pg-forma-pago').addEventListener('change', toggleCamposBanco);
    document.getElementById('pg-tipo-op-banco').addEventListener('change', (ev) => {
        const esCheque = ev.target.value === 'CHEQUE';
        document.getElementById('pg-fecha-cobro-wrap').classList.toggle('d-none', !esCheque);
        if (!esCheque) document.getElementById('pg-fecha-cobro').value = '';
    });

    document.getElementById('cb-btn-armar').addEventListener('click', async () => {
        const ids = Array.from($cbLista.querySelectorAll('.cb-check:checked')).map(c => c.dataset.id);
        if (!ids.length) { swalError('Selecciona al menos un ítem.'); return; }
        const monto = Array.from($cbLista.querySelectorAll('.cb-check:checked')).reduce((a, c) => a + parseFloat(c.dataset.monto || 0), 0);

        const fd = new FormData();
        fd.append('id_comanda', ID_COMANDA);
        fd.append('ids_lineas', JSON.stringify(ids));
        try {
            const r = await fetch(AJAX + '/crearGrupoCobroAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo armar el grupo de cobro.'); return; }
            await refrescarComanda();
            await abrirModalPago(d.id, monto);
        } catch (e) { swalError('Error de conexión.'); }
    });

    $grupos.addEventListener('click', async (ev) => {
        const btnCobrar = ev.target.closest('.cb-cobrar-grupo');
        const btnDeshacer = ev.target.closest('.cb-deshacer-grupo');
        const btnImprimir = ev.target.closest('.cb-imprimir-grupo');
        if (btnCobrar) {
            await abrirModalPago(btnCobrar.dataset.id, parseFloat(btnCobrar.dataset.monto || 0), {
                idCliente: btnCobrar.dataset.idCliente,
                clienteNombre: btnCobrar.dataset.clienteNombre,
                tipoDocumento: btnCobrar.dataset.tipoDoc,
            });
        }
        if (btnImprimir) {
            imprimirTicketPos(parseInt(btnImprimir.dataset.idDoc, 10), btnImprimir.dataset.tipoDoc);
        }
        if (btnDeshacer) {
            const { isConfirmed } = await Swal.fire({
                title: '¿Deshacer este grupo?', text: 'Sus ítems volverán a quedar disponibles para cobro.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, deshacer', cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
            const fd = new FormData();
            fd.append('id_grupo', btnDeshacer.dataset.id);
            fd.append('id_comanda', ID_COMANDA);
            try {
                const r = await fetch(AJAX + '/eliminarGrupoCobroAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo deshacer el grupo.'); return; }
                await refrescarComanda();
            } catch (e) { swalError('Error de conexión.'); }
        }
    });

    // Typeahead de cliente (mismo patrón simple: buscar por texto, click para fijar).
    document.getElementById('pg-cliente-buscar').addEventListener('input', (ev) => {
        clearTimeout(clienteTimer);
        const q = ev.target.value.trim();
        document.getElementById('pg-id-cliente').value = '0';
        revisarLimiteConsumidorFinal();
        if (q.length < 2) { document.getElementById('pg-cliente-resultados').innerHTML = ''; return; }
        clienteTimer = setTimeout(async () => {
            try {
                const r = await fetch(AJAX + '/getClientesAjax?q=' + encodeURIComponent(q));
                const d = await r.json();
                const rows = d.ok ? d.data : [];
                const $res = document.getElementById('pg-cliente-resultados');
                if (!rows.length) { $res.innerHTML = '<div class="list-group-item disabled small">Sin resultados.</div>'; return; }
                $res.innerHTML = rows.map(c => `<button type="button" class="list-group-item list-group-item-action" data-id="${c.id}" data-nombre="${escapeHtml(c.nombre)}">${escapeHtml(c.nombre)} <small class="text-muted">${escapeHtml(c.identificacion || '')}</small></button>`).join('');
            } catch (e) { /* silencioso */ }
        }, 300);
    });

    document.getElementById('pg-cliente-resultados').addEventListener('click', (ev) => {
        const item = ev.target.closest('[data-id]');
        if (!item) return;
        document.getElementById('pg-id-cliente').value = item.dataset.id;
        document.getElementById('pg-cliente-buscar').value = item.dataset.nombre;
        document.getElementById('pg-cliente-resultados').innerHTML = '';
        revisarLimiteConsumidorFinal();
    });

    // ─── Vista previa de la cuenta (ANTES de cobrar) ────────────────────────
    // Para que el cliente vea qué debe antes de decidir cómo pagar/dividir.
    // Se arma 100% con lo que ya está cargado en pantalla (detalles), sin
    // llamar al servidor — el IVA mostrado es informativo (viene de
    // ComandaRepository::getLineas()); al cobrar, PosVentaService lo vuelve a
    // resolver desde el producto, así que esto nunca decide lo que se cobra.
    function imprimirVistaPreviaComanda() {
        const vivas = detalles.filter(d => d.estado_linea !== 'anulado');
        if (!vivas.length) return;
        const fmt = (n) => parseFloat(n || 0).toFixed(2);

        let subtotal = 0, totalIva = 0;
        const impMap = {};
        vivas.forEach(d => {
            const base = parseFloat(d.subtotal || 0);
            subtotal += base;
            const pct = parseFloat(d.porcentaje_iva || 0);
            const lbl = `IVA ${pct}%`;
            impMap[lbl] = (impMap[lbl] || 0) + (base * pct / 100);
        });
        Object.values(impMap).forEach(v => totalIva += v);
        const total = subtotal + totalIva;

        const lineas = vivas.map(d => {
            const pct = parseFloat(d.porcentaje_iva || 0);
            const descExtra = parseFloat(d.descuento || 0) > 0 ? ` — desc. $${fmt(d.descuento)}` : '';
            return `<tr><td colspan="2" style="padding:1px 0;">${escapeHtml(d.descripcion + descExtra)}</td></tr>
                <tr><td style="padding:1px 0;color:#555;">${fmt(d.cantidad)} x $${fmt(d.precio_unitario)} (IVA ${pct}%)</td>
                <td style="padding:1px 0;text-align:right;font-weight:bold;">$${fmt(d.subtotal)}</td></tr>`;
        }).join('<tr><td colspan="2"><hr style="margin:2px 0;border-color:#ccc;"></td></tr>');

        const ivaLineas = Object.entries(impMap).map(([lbl, val]) =>
            `<tr><td>${lbl}</td><td style="text-align:right;">$${fmt(val)}</td></tr>`).join('');

        const logoHtml = EMPRESA_INFO.logo
            ? `<img src="${BASE}/${EMPRESA_INFO.logo}" style="max-width:120px;max-height:60px;margin-bottom:4px;">`
            : '';

        const html = `<!DOCTYPE html><html lang="es"><head>
            <meta charset="UTF-8">
            <title>Cuenta - Mesa <?= htmlspecialchars($comanda['mesa_nombre'] ?? '') ?></title>
            <style>
                @page { size: 80mm auto; margin: 3mm; }
                * { box-sizing: border-box; }
                body { font-family: 'Courier New', Courier, monospace; font-size: 9px; width: 74mm; margin: 0; padding: 0; color: #000; }
                .center { text-align: center; }
                .bold { font-weight: bold; }
                .sep { border: none; border-top: 1px dashed #000; margin: 3px 0; }
                table { width: 100%; border-collapse: collapse; }
                td { vertical-align: top; font-size: 9px; }
                .totales td { padding: 1px 0; }
                .totales tr:last-child td { font-weight: bold; font-size: 10px; }
                h2 { font-size: 11px; margin: 2px 0; }
                @media print { body { width: 74mm; } button { display: none; } }
            </style>
        </head><body>
            <div class="center">
                ${logoHtml}
                <h2>${escapeHtml(EMPRESA_INFO.nombre_comercial || EMPRESA_INFO.nombre)}</h2>
            </div>
            <hr class="sep">
            <div class="center bold" style="font-size:10px;">CUENTA — MESA <?= htmlspecialchars($comanda['mesa_nombre'] ?? '') ?></div>
            <div class="center">Fecha: ${escapeHtml(new Date().toLocaleDateString('es-EC'))}</div>
            <hr class="sep">
            <table><tbody>${lineas}</tbody></table>
            <hr class="sep">
            <table class="totales">
                <tr><td>Subtotal</td><td style="text-align:right;">$${fmt(subtotal)}</td></tr>
                ${ivaLineas}
                <tr><td>TOTAL A PAGAR</td><td style="text-align:right;">$${fmt(total)}</td></tr>
            </table>
            <hr class="sep">
            <div class="center" style="font-size:8px;">Esta cuenta es solo una vista previa.<br>No tiene validez tributaria.</div>
            <br><br>
            <script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
        </body></html>`;

        const win = window.open('', '_blank', 'width=320,height=600,scrollbars=yes');
        if (!win) { swalWarning('Permite ventanas emergentes para imprimir la cuenta.'); return; }
        win.document.write(html);
        win.document.close();
    }

    document.getElementById('cm-btn-vista-previa')?.addEventListener('click', imprimirVistaPreviaComanda);

    // ─── Ticket / tirilla (mismo patrón que caja_sesion/venta.php: 100%
    // client-side vía getFacturaAjax del módulo del documento + ventana nueva
    // + window.print(), sin PDF) — recibe el tipo porque un grupo puede
    // haber generado Factura o Recibo según lo elegido al cobrar. ───────
    async function imprimirTicketPos(idDocumento, tipoDocumento) {
        if (!idDocumento) return;
        const rutaDoc = tipoDocumento === 'FACTURA' ? 'modulos/factura-venta' : 'modulos/recibo-venta';

        try {
            const resp = await fetch(`${BASE}/${rutaDoc}/getFacturaAjax?id=${idDocumento}`);
            const json = await resp.json();
            if (!json.ok) { swalError(json.error || 'No se pudo cargar el documento.'); return; }

            const cab = json.cabecera;
            const detalles = json.detalles || [];
            const pagos = json.pagos || [];

            const num = `${cab.establecimiento || '000'}-${cab.punto_emision || '000'}-${String(cab.secuencial || '').padStart(9, '0')}`;
            const fecha = cab.fecha_emision ? (() => {
                const d = new Date(cab.fecha_emision);
                return isNaN(d) ? cab.fecha_emision : d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
            })() : '';

            const fmt = (n) => parseFloat(n || 0).toFixed(2);

            let subtotal = 0, totalIva = 0, totalIce = 0, totalDescuento = 0;
            const impMap = {};
            detalles.forEach(d => {
                subtotal += parseFloat(d.precio_total_sin_impuesto || 0);
                totalDescuento += parseFloat(d.descuento || 0);
                (d.impuestos || []).forEach(imp => {
                    const lbl = `IVA ${parseFloat(imp.tarifa || 0).toFixed(0)}%`;
                    impMap[lbl] = (impMap[lbl] || 0) + parseFloat(imp.valor || 0);
                    if (String(imp.codigo_impuesto) === '3') totalIce += parseFloat(imp.valor || 0);
                });
            });
            Object.values(impMap).forEach(v => totalIva += v);
            const total = subtotal + totalIva + totalIce + parseFloat(cab.propina || 0);

            const logoHtml = EMPRESA_INFO.logo
                ? `<img src="${BASE}/${EMPRESA_INFO.logo}" style="max-width:120px;max-height:60px;margin-bottom:4px;">`
                : '';

            const lineas = detalles.map(d => {
                const cant = parseFloat(d.cantidad || 1);
                const pu = parseFloat(d.precio_unitario || 0);
                const desc = parseFloat(d.descuento || 0);
                const tot = parseFloat(d.precio_total_sin_impuesto || 0);
                const ivaPct = (d.impuestos && d.impuestos[0]) ? parseFloat(d.impuestos[0].tarifa || 0).toFixed(0) : '0';
                return `<tr><td colspan="2" style="padding:1px 0;">${escapeHtml(d.descripcion)}</td></tr>
                    <tr><td style="padding:1px 0;color:#555;">${fmt(cant)} x $${fmt(pu)}${desc > 0 ? ` desc.$${fmt(desc)}` : ''} (IVA ${ivaPct}%)</td>
                    <td style="padding:1px 0;text-align:right;font-weight:bold;">$${fmt(tot)}</td></tr>`;
            }).join('<tr><td colspan="2"><hr style="margin:2px 0;border-color:#ccc;"></td></tr>');

            const ivaLineas = Object.entries(impMap).map(([lbl, val]) =>
                `<tr><td>${lbl}</td><td style="text-align:right;">$${fmt(val)}</td></tr>`).join('');

            const pagoLineas = pagos.map(p =>
                `<tr><td>${escapeHtml(p.nombre_forma_pago || p.forma_pago || 'Efectivo')}</td><td style="text-align:right;">$${fmt(p.total)}</td></tr>`).join('');

            const tituloDoc = tipoDocumento === 'FACTURA' ? 'FACTURA DE VENTA' : 'RECIBO DE VENTA';

            const html = `<!DOCTYPE html><html lang="es"><head>
                <meta charset="UTF-8">
                <title>Ticket - ${escapeHtml(num)}</title>
                <style>
                    @page { size: 80mm auto; margin: 3mm; }
                    * { box-sizing: border-box; }
                    body { font-family: 'Courier New', Courier, monospace; font-size: 9px; width: 74mm; margin: 0; padding: 0; color: #000; }
                    .center { text-align: center; }
                    .bold { font-weight: bold; }
                    .sep { border: none; border-top: 1px dashed #000; margin: 3px 0; }
                    table { width: 100%; border-collapse: collapse; }
                    td { vertical-align: top; font-size: 9px; }
                    .totales td { padding: 1px 0; }
                    .totales tr:last-child td { font-weight: bold; font-size: 10px; }
                    h2 { font-size: 11px; margin: 2px 0; }
                    h3 { font-size: 9px; margin: 1px 0; font-weight: normal; }
                    @media print { body { width: 74mm; } button { display: none; } }
                </style>
            </head><body>
                <div class="center">
                    ${logoHtml}
                    <h2>${escapeHtml(EMPRESA_INFO.nombre_comercial || EMPRESA_INFO.nombre)}</h2>
                    <h3>RUC: ${escapeHtml(EMPRESA_INFO.ruc)}</h3>
                    ${EMPRESA_INFO.direccion ? `<h3>${escapeHtml(EMPRESA_INFO.direccion)}</h3>` : ''}
                    ${EMPRESA_INFO.telefono ? `<h3>Tel: ${escapeHtml(EMPRESA_INFO.telefono)}</h3>` : ''}
                </div>
                <hr class="sep">
                <div class="center bold" style="font-size:10px;">${tituloDoc}</div>
                <div class="center">No. ${escapeHtml(num)}</div>
                <div class="center">Fecha: ${escapeHtml(fecha)}</div>
                <hr class="sep">
                <table>
                    <tr><td class="bold">Cliente:</td><td>${escapeHtml(cab.cliente_nombre)}</td></tr>
                    <tr><td class="bold">RUC/CI:</td><td>${escapeHtml(cab.cliente_ruc)}</td></tr>
                    ${cab.cliente_direccion ? `<tr><td class="bold">Dir:</td><td>${escapeHtml(cab.cliente_direccion)}</td></tr>` : ''}
                </table>
                <hr class="sep">
                <table><tbody>${lineas}</tbody></table>
                <hr class="sep">
                <table class="totales">
                    <tr><td>Subtotal sin imp.</td><td style="text-align:right;">$${fmt(subtotal)}</td></tr>
                    ${totalDescuento > 0 ? `<tr><td>Descuento</td><td style="text-align:right;">-$${fmt(totalDescuento)}</td></tr>` : ''}
                    ${ivaLineas}
                    ${totalIce > 0 ? `<tr><td>ICE</td><td style="text-align:right;">$${fmt(totalIce)}</td></tr>` : ''}
                    ${parseFloat(cab.propina || 0) > 0 ? `<tr><td>Propina</td><td style="text-align:right;">$${fmt(cab.propina)}</td></tr>` : ''}
                    <tr><td>TOTAL</td><td style="text-align:right;">$${fmt(total)}</td></tr>
                </table>
                ${pagos.length ? `<hr class="sep"><div class="bold" style="font-size:9px;">FORMA DE PAGO</div><table class="totales">${pagoLineas}</table>` : ''}
                ${cab.observaciones ? `<hr class="sep"><div style="font-size:8px;">${escapeHtml(cab.observaciones)}</div>` : ''}
                <hr class="sep">
                <div class="center" style="font-size:8px;">¡Gracias por su compra!</div>
                <br><br>
                <script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
            </body></html>`;

            const win = window.open('', '_blank', 'width=320,height=600,scrollbars=yes');
            if (!win) { swalWarning('Permite ventanas emergentes para imprimir el ticket.'); return; }
            win.document.write(html);
            win.document.close();
        } catch (e) {
            swalError('No se pudo generar el ticket.');
        }
    }

    document.getElementById('pg-btn-confirmar').addEventListener('click', async () => {
        if (!idGrupoEnPago) return;
        const $sel = document.getElementById('pg-forma-pago');
        const opt = $sel.options[$sel.selectedIndex];
        if (!opt) { swalError('No hay formas de pago configuradas para esta empresa.'); return; }
        const tipoDoc = document.querySelector('input[name="pg-tipo-doc"]:checked').value;
        const esBanco = (opt.dataset.tipo || '').toUpperCase() === 'BANCO';
        const tipoOperacionBancaria = esBanco ? document.getElementById('pg-tipo-op-banco').value : '';
        const fechaCobro = document.getElementById('pg-fecha-cobro').value;

        if (esBanco && tipoOperacionBancaria === 'CHEQUE' && !fechaCobro) {
            swalError('Indica la fecha de cobro del cheque.');
            return;
        }

        const fd = new FormData();
        fd.append('id_grupo', idGrupoEnPago);
        fd.append('id_cliente', document.getElementById('pg-id-cliente').value || '0');
        fd.append('tipo_documento', tipoDoc);
        fd.append('forma_pago', opt.dataset.cod || '01');
        fd.append('id_forma_pago_empresa', opt.value);
        fd.append('tipo_operacion_bancaria', tipoOperacionBancaria);
        fd.append('numero_operacion', document.getElementById('pg-numero-operacion').value);
        fd.append('fecha_cobro', fechaCobro);
        fd.append('id_bodega', getIdBodega() || '');

        const $btn = document.getElementById('pg-btn-confirmar');
        $btn.disabled = true;
        try {
            const r = await fetch(AJAX + '/cobrarGrupoAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo registrar el cobro.'); return; }
            mdPago && mdPago.hide();
            idGrupoEnPago = null;
            await refrescarComanda();

            const etiquetaDoc = d.data.tipo_documento === 'FACTURA' ? 'Factura' : 'Recibo';
            Swal.fire({
                icon: 'success',
                title: 'Cobro registrado',
                html: etiquetaDoc + ' <b>' + escapeHtml(d.data.numero_documento) + '</b> por <b>' + money(d.data.importe_total) + '</b>.' +
                      '<div class="d-flex gap-2 justify-content-center mt-3">' +
                      '<button type="button" class="btn btn-outline-secondary btn-sm" id="cm-swal-btn-ticket"><i class="bi bi-receipt me-1"></i>Imprimir tirilla</button>' +
                      '</div>',
                confirmButtonColor: '#198754',
                confirmButtonText: 'Aceptar',
                didOpen: () => {
                    document.getElementById('cm-swal-btn-ticket')?.addEventListener('click', () => imprimirTicketPos(d.data.id_documento, d.data.tipo_documento));
                },
            });
            if (d.data.aviso_ingreso) {
                setTimeout(() => swalWarning(escapeHtml(d.data.aviso_ingreso) + ' Regístralo manualmente desde el módulo Ingresos.'), 300);
            }
        } catch (e) { swalError('Error de conexión.'); }
        finally { $btn.disabled = false; }
    });

    renderLineas();
    buscarProductos('');
    setInterval(refrescarComanda, 8000);
})();
</script>
</body>
</html>
