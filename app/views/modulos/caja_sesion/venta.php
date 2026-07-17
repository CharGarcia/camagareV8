<?php
/**
 * Punto de Venta — Diseño A (Grid Retail). Página STANDALONE (sin layout
 * principal). Cobra generando Factura o Recibo de Venta (según lo elegido y
 * el permiso del usuario) a través de PosVentaService::cobrar() — mismo
 * motor que Factura de Venta/Recibos de Venta, sin duplicar lógica fiscal.
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var int    $idPuntoEmision
 * @var array  $sesion
 * @var bool   $obligatorioLotes
 * @var bool   $obligatorioCaducidad
 * @var bool   $obligatorioNup
 * @var bool   $soloStockPositivo
 * @var float  $limiteConsumidorFinal
 * @var bool   $puedeFactura
 * @var bool   $puedeRecibo
 * @var array  $bodegas
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
    <script src="<?= $base ?>/js/favoritos.js?v=<?= time() ?>"></script>
    <?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
    <style>
        html, body { height: 100%; }
        body { background: #f4f6f9; overflow: hidden; }
        .pv-wrap { display: flex; flex-direction: column; height: 100vh; }
        .pv-header { flex: 0 0 auto; }
        .pv-body { flex: 1 1 auto; min-height: 0; display: flex; }

        .pv-tickets { display: flex; align-items: center; gap: 6px; overflow-x: auto; max-width: 46vw; }
        .pv-ticket-tab {
            display: flex; align-items: center; gap: 6px; white-space: nowrap; cursor: pointer;
            background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.35); color: #fff;
            border-radius: 999px; padding: 4px 6px 4px 12px; font-size: .78rem; flex-shrink: 0;
        }
        .pv-ticket-tab.active { background: #fff; color: #0d6efd; border-color: #fff; font-weight: 600; }
        .pv-ticket-tab .badge {
            font-size: .62rem; font-weight: 600; padding: 2px 6px; border-radius: 999px;
            background: rgba(255,255,255,.3); color: #fff;
        }
        .pv-ticket-tab.active .badge { background: #0d6efd; color: #fff; }
        .pv-ticket-tab .cerrar { opacity: .65; padding: 2px; border-radius: 50%; }
        .pv-ticket-tab .cerrar:hover { opacity: 1; background: rgba(0,0,0,.08); }
        .pv-btn-nuevo-ticket {
            width: 28px; height: 28px; flex-shrink: 0; border-radius: 50%; border: 1px solid rgba(255,255,255,.4);
            background: rgba(255,255,255,.15); color: #fff; display: flex; align-items: center; justify-content: center;
        }
        .pv-btn-nuevo-ticket:hover { background: rgba(255,255,255,.3); }

        .pv-catalogo { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
        .pv-search { flex: 0 0 auto; padding: 12px 16px; background: #fff; border-bottom: 1px solid #dee2e6; }
        .pv-grid { flex: 1 1 auto; overflow-y: auto; padding: 14px; display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; align-content: start; }
        .pv-tile { position: relative; background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 12px 10px; cursor: pointer; text-align: left; transition: border-color .15s; }
        .pv-tile:hover { border-color: #0d6efd; }
        .pv-tile .thumb { width: 100%; height: 56px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; background: #f4f6f9; display: block; }
        .pv-tile .nombre { font-size: .82rem; font-weight: 600; line-height: 1.25; margin-bottom: 6px; min-height: 2.1em; }
        .pv-tile .precio-row { display: flex; align-items: baseline; gap: 5px; }
        .pv-tile .precio { font-size: .82rem; color: #0d6efd; font-weight: 700; }
        .pv-tile .iva-tag { font-size: .64rem; color: #8a94a6; }
        .pv-tile .iva-tag.iva-cero { color: #b5792c; }
        .pv-tile .codigo { font-size: .68rem; color: #8a94a6; }
        .pv-tile .sin-stock {
            position: absolute; top: 6px; right: 6px; width: 22px; height: 22px; border-radius: 50%;
            background: #dc3545; color: #fff; display: flex; align-items: center; justify-content: center; font-size: .7rem;
        }
        .pv-empty { color: #8a94a6; }

        .pv-carrito { width: 340px; max-width: 40%; background: #fff; border-left: 1px solid #dee2e6; display: flex; flex-direction: column; }
        .pv-lineas { flex: 1 1 auto; overflow-y: auto; padding: 10px 14px; }
        .pv-linea { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .pv-linea .desc { flex: 1 1 auto; min-width: 0; }
        .pv-linea .desc .n { font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pv-linea .desc .p { font-size: .72rem; color: #8a94a6; }
        .pv-qty { display: flex; align-items: center; gap: 4px; }
        .pv-qty button { width: 22px; height: 22px; line-height: 1; padding: 0; }
        .pv-qty span { min-width: 20px; text-align: center; font-size: .8rem; }
        .pv-linea .total { font-size: .82rem; font-weight: 600; min-width: 56px; text-align: right; }
        .pv-linea .rm { color: #dc3545; cursor: pointer; }

        .pv-totales { flex: 0 0 auto; padding: 12px 16px; border-top: 1px dashed #dee2e6; font-size: .85rem; }
        .pv-totales .row div { display: flex; justify-content: space-between; padding: 2px 0; }
        .pv-totales .row.total div { font-size: 1.15rem; font-weight: 700; border-top: 1px solid #dee2e6; margin-top: 6px; padding-top: 8px; }

        .pv-pagos { padding: 0 16px 10px; }

        .pv-cobrar { padding: 0 16px 16px; }
    </style>
</head>
<body>
<div class="pv-wrap">
    <div class="pv-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-cash-coin fs-5"></i>
            <div>
                <div class="fw-semibold lh-1">Punto de Venta</div>
                <small class="text-white-50">
                    Cajero: <?= htmlspecialchars($sesion['cajero_nombre'] ?? '—') ?> ·
                    Fondo: $<?= number_format((float) $sesion['fondo_inicial'], 2) ?>
                </small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-1" style="min-width:0;">
            <div class="pv-tickets" id="pv-tickets"></div>
            <button type="button" class="pv-btn-nuevo-ticket" id="pv-btn-nuevo-ticket" title="Nueva venta">
                <i class="bi bi-plus-lg"></i>
            </button>
            <a href="<?= $rutaAjax ?>" class="btn btn-light btn-sm flex-shrink-0">
                <i class="bi bi-lock-fill me-1"></i>Cerrar caja
            </a>
        </div>
    </div>

    <div class="pv-body">
        <div class="pv-catalogo">
            <div class="pv-search">
                <div class="row g-2 align-items-end">
                    <?php if (count($bodegas) > 1): ?>
                    <div class="col-2">
                        <label class="form-label small fw-semibold text-uppercase text-muted mb-1">
                            Bodega
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'pv-id-bodega', 'id_bodega') ?>
                        </label>
                        <select id="pv-id-bodega" class="form-select">
                            <?php foreach ($bodegas as $b): ?>
                                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="<?= count($bodegas) > 1 ? 'col-10' : 'col-12' ?>">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                            <input type="text" id="pv-buscar" class="form-control" placeholder="Buscar producto o escanear código de barras..." autofocus autocomplete="off">
                        </div>
                    </div>
                </div>
                <?php
                    $reglasInv = [];
                    if ($obligatorioLotes) $reglasInv[] = 'lote';
                    if ($obligatorioCaducidad) $reglasInv[] = 'fecha de caducidad';
                    if ($obligatorioNup) $reglasInv[] = 'serie (NUP)';
                ?>
                <?php if (!empty($reglasInv)): ?>
                <div class="small text-muted mt-1">
                    <i class="bi bi-boxes"></i> Esta empresa exige <?= implode(', ', $reglasInv) ?> en productos inventariados según su configuración.
                </div>
                <?php endif; ?>
            </div>
            <div class="pv-grid" id="pv-grid">
                <div class="text-center py-4 pv-empty" style="grid-column: 1 / -1;">
                    <span class="spinner-border spinner-border-sm"></span> Cargando productos...
                </div>
            </div>
        </div>

        <div class="pv-carrito">
            <?php if ($puedeFactura && $puedeRecibo): ?>
            <div class="px-3 pt-3 pb-2 border-bottom">
                <label class="form-label small fw-semibold text-uppercase text-muted mb-1">
                    Documento
                    <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'pv-tipo-documento', 'tipo_documento') ?>
                </label>
                <select id="pv-tipo-documento" class="form-select form-select-sm">
                    <option value="FACTURA">Factura de venta</option>
                    <option value="RECIBO">Recibo de venta</option>
                </select>
            </div>
            <?php elseif (!$puedeFactura && !$puedeRecibo): ?>
            <div class="alert alert-warning small m-3 mb-0 py-2">
                No tienes permiso para generar Facturas ni Recibos de Venta. Pide que te asignen uno de los dos.
            </div>
            <?php endif; ?>
            <div class="pv-cliente px-3 pt-3 pb-2 border-bottom">
                <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Cliente</label>
                <div id="pv-cliente-actual" class="d-flex align-items-center justify-content-between gap-2">
                    <span id="pv-cliente-nombre" class="small text-truncate">Consumidor Final</span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="pv-btn-cambiar-cliente">Cambiar</button>
                </div>
                <div id="pv-cliente-buscar" class="d-none">
                    <div class="input-group input-group-sm mb-1">
                        <input type="text" id="pv-cliente-input" class="form-control" placeholder="Buscar por nombre o identificación...">
                        <button type="button" class="btn btn-outline-secondary" id="pv-btn-nuevo-cliente" title="Nuevo cliente"><i class="bi bi-person-plus"></i></button>
                        <button type="button" class="btn btn-outline-secondary" id="pv-btn-cerrar-cliente" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div id="pv-cliente-resultados" class="list-group list-group-flush small" style="max-height:150px; overflow-y:auto;"></div>
                </div>
            </div>
            <div class="pv-lineas" id="pv-lineas">
                <div class="text-center py-4 pv-empty small">El carrito está vacío.<br>Toca un producto para agregarlo.</div>
            </div>
            <div class="pv-totales">
                <div class="row">
                    <div><span>Subtotal</span><span id="pv-subtotal">$0.00</span></div>
                    <div><span>IVA</span><span id="pv-iva">$0.00</span></div>
                </div>
                <div class="row total">
                    <div><span>Total</span><span id="pv-total">$0.00</span></div>
                </div>
                <div id="pv-aviso-cf" class="small text-danger mt-1 d-none"></div>
            </div>
            <div class="pv-pagos">
                <label class="form-label small fw-semibold text-uppercase text-muted mb-1">
                    Forma de pago
                    <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'pv-pagos', 'id_forma_pago') ?>
                </label>
                <select id="pv-pagos" class="form-select form-select-sm" disabled>
                    <option>Cargando...</option>
                </select>
                <div id="pv-pago-banco" class="small text-muted mt-1 d-none"></div>

                <div id="pv-pago-banco-extra" class="d-none border rounded-2 p-2 bg-light mt-1">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Op. Bancaria</label>
                            <select id="pv-tipo-op-banco" class="form-select form-select-sm">
                                <option value="TRANSFERENCIA" selected>Transferencia</option>
                                <option value="DEPOSITO">Depósito</option>
                                <option value="DEBITO">Débito</option>
                                <option value="CHEQUE">Cheque</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Nº Ref. / Cheque</label>
                            <input type="text" id="pv-num-op-banco" class="form-control form-control-sm" placeholder="Opcional">
                        </div>
                        <div class="col-6 d-none" id="pv-wrap-fecha-cheque">
                            <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Fecha de cobro</label>
                            <input type="date" id="pv-fecha-cheque" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                <button type="button" id="pv-btn-link-whatsapp" class="btn btn-outline-success btn-sm w-100 mt-2 d-none">
                    <i class="bi bi-whatsapp me-1"></i>Enviar link de pago por WhatsApp
                </button>
            </div>
            <div class="pv-cobrar">
                <button id="pv-btn-cobrar" class="btn btn-success w-100" type="button" disabled>
                    <i class="bi bi-check-circle-fill me-1"></i>Cobrar $0.00
                </button>
            </div>
        </div>
    </div>
</div>

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
                <button type="button" class="btn btn-primary btn-sm" id="modalLoteConfirmar">Agregar al carrito</button>
            </div>
        </div>
    </div>
</div>

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

<div class="modal fade" id="modalClienteNuevo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-person-plus-fill me-1"></i>Nuevo cliente</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-uppercase text-muted mb-1">
                            Tipo ID
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'ncTipoId', 'tipo_id') ?>
                        </label>
                        <select id="ncTipoId" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-uppercase text-muted mb-1 d-flex align-items-center justify-content-between">
                            <span>Identificación</span>
                            <span id="ncSriEstado" class="badge bg-secondary d-none" style="font-size:.62rem;"></span>
                        </label>
                        <input type="text" id="ncIdentificacion" class="form-control form-control-sm" autocomplete="off">
                        <div id="ncIdentificacionError" class="text-danger" style="display:none; font-size:.72rem;"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Nombre / Razón social</label>
                        <input type="text" id="ncNombre" class="form-control form-control-sm">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-uppercase text-muted mb-1">
                            Correo(s) <small class="text-muted text-lowercase">— para factura electrónica, separados por coma</small>
                        </label>
                        <input type="text" id="ncEmail" class="form-control form-control-sm" placeholder="correo@ejemplo.com, otro@ejemplo.com" autocomplete="off">
                        <div id="ncEmailError" class="text-danger" style="display:none; font-size:.72rem;"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Teléfono</label>
                        <input type="text" id="ncTelefono" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnGuardarClienteNuevo">Guardar y usar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const AJAX = "<?= $rutaAjax ?>";
    const BASE = "<?= $base ?>";
    const ID_PUNTO = <?= (int) $idPuntoEmision ?>;
    // Ligado al turno (id de caja_sesiones): al abrir un turno nuevo en el
    // mismo punto de emisión, las pestañas guardadas del turno anterior
    // quedan huérfanas en localStorage (no se muestran) en vez de mezclarse.
    const STORAGE_KEY = 'pos_tickets_' + ID_PUNTO + '_' + <?= (int) ($sesion['id'] ?? 0) ?>;
    const OBLIGATORIO_LOTES = <?= $obligatorioLotes ? 'true' : 'false' ?>;
    const OBLIGATORIO_CADUCIDAD = <?= $obligatorioCaducidad ? 'true' : 'false' ?>;
    const OBLIGATORIO_NUP = <?= $obligatorioNup ? 'true' : 'false' ?>;
    const SOLO_STOCK_POSITIVO = <?= $soloStockPositivo ? 'true' : 'false' ?>;
    const LIMITE_CONSUMIDOR_FINAL = <?= (float) $limiteConsumidorFinal ?>;
    // Si solo tienes permiso para uno de los dos documentos, no se muestra
    // selector — se usa ese fijo. Si no tienes ninguno, queda null (se
    // deshabilita el cobro; el aviso ya se muestra en el HTML).
    const TIPO_DOC_FIJO = <?= $puedeFactura ? "'FACTURA'" : ($puedeRecibo ? "'RECIBO'" : 'null') ?>;
    // Si solo hay una bodega (o ninguna), no se muestra selector — se usa esa
    // fija (o null, y el backend cae a "primera bodega activa" como antes).
    const ID_BODEGA_FIJA = <?= count($bodegas) === 1 ? (int) $bodegas[0]['id'] : 'null' ?>;
    function getIdBodega() {
        const sel = document.getElementById('pv-id-bodega');
        return sel ? parseInt(sel.value, 10) : ID_BODEGA_FIJA;
    }
    function getTipoDocumento() {
        const sel = document.getElementById('pv-tipo-documento');
        return sel ? sel.value : TIPO_DOC_FIJO;
    }
    const modalLoteEl = document.getElementById('modalLote');
    const modalLote = new bootstrap.Modal(modalLoteEl);
    const modalNupEl = document.getElementById('modalNup');
    const modalNup = new bootstrap.Modal(modalNupEl);
    const modalClienteNuevoEl = document.getElementById('modalClienteNuevo');
    const modalClienteNuevo = new bootstrap.Modal(modalClienteNuevoEl);
    const $grid = document.getElementById('pv-grid');
    const $buscar = document.getElementById('pv-buscar');
    const $lineas = document.getElementById('pv-lineas');
    const $btnCobrar = document.getElementById('pv-btn-cobrar');
    const $tickets = document.getElementById('pv-tickets');
    const $btnNuevoTicket = document.getElementById('pv-btn-nuevo-ticket');
    let cart = [];
    let formaPago = '01';
    let idFormaPagoEmpresa = 0;
    let buscarTimer = null;
    let lineSeq = 0;
    let clienteSeleccionado = null; // null = Consumidor Final (default del backend)
    let clienteBuscarTimer = null;
    let tiposIdCargados = false;
    // ─── Pestañas de venta: varias ventas en curso en la misma ventana, una
    // por cliente. "cart"/"clienteSeleccionado"/etc. son siempre los de la
    // pestaña ACTIVA; al cambiar de pestaña se guarda el estado saliente y
    // se carga el entrante (no hay un objeto "ticket" separado en vivo).
    let tickets = [];
    let ticketActivoId = null;
    let ticketSeq = 0;

    function money(v) { return '$' + (parseFloat(v || 0)).toFixed(2); }

    function swalToast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2800, timerProgressBar: true });
    }
    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function swalWarning(html) {
        Swal.fire({ icon: 'warning', title: 'Atención', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }

    // ─── Cliente ─────────────────────────────────────────────────────────
    const $clienteNombre = document.getElementById('pv-cliente-nombre');
    const $clienteBuscarWrap = document.getElementById('pv-cliente-buscar');
    const $clienteInput = document.getElementById('pv-cliente-input');
    const $clienteResultados = document.getElementById('pv-cliente-resultados');

    document.getElementById('pv-btn-cambiar-cliente').addEventListener('click', () => {
        $clienteBuscarWrap.classList.remove('d-none');
        $clienteInput.value = '';
        $clienteInput.focus();
        renderResultadosCliente([]);
    });

    document.getElementById('pv-btn-cerrar-cliente').addEventListener('click', () => {
        $clienteBuscarWrap.classList.add('d-none');
    });

    $clienteInput.addEventListener('input', () => {
        clearTimeout(clienteBuscarTimer);
        clienteBuscarTimer = setTimeout(() => buscarClientes($clienteInput.value.trim()), 350);
    });

    async function buscarClientes(q) {
        try {
            const res = await fetch(AJAX + '/getClientesAjax?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            renderResultadosCliente(json.data || []);
        } catch (e) {
            swalToast('error', 'Error al buscar clientes.');
        }
    }

    function renderResultadosCliente(rows) {
        $clienteResultados.innerHTML = '';

        const itemConsumidor = document.createElement('button');
        itemConsumidor.type = 'button';
        itemConsumidor.className = 'list-group-item list-group-item-action py-1';
        itemConsumidor.innerHTML = '<span class="text-muted">Consumidor Final (por defecto)</span>';
        itemConsumidor.addEventListener('click', () => seleccionarCliente(null));
        $clienteResultados.appendChild(itemConsumidor);

        rows.forEach(c => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action py-1';
            item.innerHTML = '<div>' + escapeHtml(c.nombre) + '</div><div class="text-muted" style="font-size:.72rem;">' + escapeHtml(c.identificacion || '') + '</div>';
            item.addEventListener('click', () => seleccionarCliente(c));
            $clienteResultados.appendChild(item);
        });
    }

    function seleccionarCliente(c) {
        clienteSeleccionado = c ? { id: parseInt(c.id, 10), nombre: c.nombre, telefono: c.telefono || '' } : null;
        $clienteNombre.textContent = clienteSeleccionado ? clienteSeleccionado.nombre : 'Consumidor Final';
        $clienteBuscarWrap.classList.add('d-none');
        renderCart();
    }

    document.getElementById('pv-btn-nuevo-cliente').addEventListener('click', async () => {
        if (!tiposIdCargados) {
            try {
                const res = await fetch(AJAX + '/getTiposIdClienteAjax', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const json = await res.json();
                const $sel = document.getElementById('ncTipoId');
                $sel.innerHTML = '<option value="">Seleccione...</option>';
                (json.data || []).forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.codigo;
                    opt.textContent = t.nombre;
                    $sel.appendChild(opt);
                });
                tiposIdCargados = true;
            } catch (e) {
                swalError('No se pudo cargar el catálogo de tipos de identificación.');
                return;
            }
        }
        ['ncIdentificacion', 'ncNombre', 'ncEmail', 'ncTelefono'].forEach(id => document.getElementById(id).value = '');
        limpiarErrorIdentificacionNc();
        limpiarBadgeSriNc();
        document.getElementById('ncEmailError').style.display = 'none';
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalClienteNuevo');
        }
        aplicarReglasIdentificacionNc();
        modalClienteNuevo.show();
    });

    // ─── Reglas de identificación + consulta SRI (mismas reglas que el
    // módulo Clientes, adaptadas a los IDs de este mini-formulario) ───────
    function getTipoNormalizadoNc() {
        const sel = document.getElementById('ncTipoId');
        if (!sel) return '';
        const codigo = (sel.value || '').trim().toUpperCase();
        const texto = (sel.options[sel.selectedIndex]?.text || '').toUpperCase();
        if (texto.includes('PASAPORTE') || codigo.includes('PAS')) return 'PASAPORTE';
        if (texto.includes('CEDULA') || texto.includes('CÉDULA') || codigo.includes('CED')) return 'CEDULA';
        if (texto.includes('RUC')) return 'RUC';
        return codigo;
    }

    function aplicarReglasIdentificacionNc() {
        const tipo = getTipoNormalizadoNc();
        const campo = document.getElementById('ncIdentificacion');
        campo.setAttribute('inputmode', tipo === 'PASAPORTE' ? 'text' : 'numeric');
        campo.maxLength = tipo === 'RUC' ? 13 : (tipo === 'CEDULA' ? 10 : 20);
        limpiarErrorIdentificacionNc();
        limpiarBadgeSriNc();
    }

    function validarIdentificacionNc() {
        const tipo = getTipoNormalizadoNc();
        const valor = document.getElementById('ncIdentificacion').value.trim();
        switch (tipo) {
            case 'RUC':
                if (!/^\d{13}$/.test(valor)) { mostrarErrorIdentificacionNc('El RUC debe tener exactamente 13 dígitos numéricos.'); return false; }
                if (!['001', '002'].includes(valor.slice(-3))) { mostrarErrorIdentificacionNc('Los últimos 3 dígitos del RUC deben ser 001 o 002.'); return false; }
                break;
            case 'CEDULA':
                if (!/^\d{10}$/.test(valor)) { mostrarErrorIdentificacionNc('La cédula debe tener exactamente 10 dígitos numéricos.'); return false; }
                break;
            case 'PASAPORTE':
                if (valor.length === 0 || valor.length > 20) { mostrarErrorIdentificacionNc('El pasaporte puede tener hasta 20 caracteres.'); return false; }
                break;
            default:
                if (valor.length === 0) { mostrarErrorIdentificacionNc('Ingrese la identificación.'); return false; }
        }
        limpiarErrorIdentificacionNc();
        return true;
    }

    function mostrarErrorIdentificacionNc(msg) {
        const el = document.getElementById('ncIdentificacionError');
        el.textContent = msg;
        el.style.display = 'block';
    }
    function limpiarErrorIdentificacionNc() {
        const el = document.getElementById('ncIdentificacionError');
        el.textContent = '';
        el.style.display = 'none';
    }
    function mostrarBadgeSriNc(texto, clase) {
        const el = document.getElementById('ncSriEstado');
        el.className = 'badge ' + clase;
        el.textContent = texto;
        el.classList.remove('d-none');
    }
    function limpiarBadgeSriNc() {
        document.getElementById('ncSriEstado').classList.add('d-none');
    }

    // Uno o varios correos separados por coma — igual que en el módulo Clientes,
    // porque ahí es a donde se envía la factura electrónica.
    function validarEmailsNc() {
        const campo = document.getElementById('ncEmail');
        const errEl = document.getElementById('ncEmailError');
        const raw = campo.value.trim();

        if (raw === '') {
            errEl.textContent = 'El correo es obligatorio (se usa para enviar la factura electrónica).';
            errEl.style.display = 'block';
            return false;
        }

        const correos = raw.split(',').map(s => s.trim()).filter(s => s !== '');
        const reEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const invalidos = correos.filter(c => !reEmail.test(c));
        if (invalidos.length > 0) {
            errEl.textContent = 'Correo(s) inválido(s): ' + invalidos.join(', ');
            errEl.style.display = 'block';
            return false;
        }
        errEl.style.display = 'none';
        return true;
    }
    document.getElementById('ncEmail').addEventListener('blur', validarEmailsNc);

    document.getElementById('ncTipoId').addEventListener('change', aplicarReglasIdentificacionNc);

    document.getElementById('ncIdentificacion').addEventListener('keydown', (ev) => {
        const tipo = getTipoNormalizadoNc();
        if (tipo !== 'RUC' && tipo !== 'CEDULA') return;
        const permitidos = ['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
        if (ev.ctrlKey || ev.metaKey || permitidos.includes(ev.key)) return;
        if (!/^\d$/.test(ev.key)) ev.preventDefault();
    });

    let sriDebounceNc = null;
    document.getElementById('ncIdentificacion').addEventListener('input', () => {
        limpiarErrorIdentificacionNc();
        limpiarBadgeSriNc();
        clearTimeout(sriDebounceNc);
        const tipo = getTipoNormalizadoNc();
        const valor = document.getElementById('ncIdentificacion').value.trim();
        const longEsperada = { RUC: 13, CEDULA: 10 }[tipo];
        if (!longEsperada || valor.length !== longEsperada) return;
        sriDebounceNc = setTimeout(() => {
            if (validarIdentificacionNc()) consultarSriNc(valor);
        }, 700);
    });

    async function consultarSriNc(identificacion) {
        // Primero busca en clientes/proveedores de ESTA empresa (rápido, sin
        // salir a internet); si no hay match local, recién consulta al SRI.
        mostrarBadgeSriNc('Consultando…', 'bg-secondary');
        try {
            const fd = new FormData();
            fd.append('identificacion', identificacion);
            const res = await fetch(BASE + '/modulos/clientes/consultarSri', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) { mostrarBadgeSriNc('No encontrado', 'bg-warning text-dark'); return; }

            if (json.source === 'cliente') {
                // Ya es cliente de esta empresa: no tiene sentido crear un duplicado
                // (el backend lo rechazaría igual) — se ofrece usar el existente.
                mostrarBadgeSriNc('Ya existe', 'bg-info text-dark');
                const usar = await Swal.fire({
                    icon: 'info',
                    title: 'Este cliente ya existe',
                    html: 'Ya tienes registrado a <b>' + escapeHtml(json.data.nombre) + '</b> con esta identificación.',
                    showCancelButton: true,
                    confirmButtonText: 'Usar este cliente',
                    cancelButtonText: 'Seguir editando',
                    confirmButtonColor: '#0d6efd',
                });
                if (usar.isConfirmed) {
                    seleccionarCliente({ id: json.data.id, nombre: json.data.nombre });
                    modalClienteNuevo.hide();
                } else {
                    // Sigue editando para crear uno nuevo igual: se precarga lo que
                    // ya se sabe (el cliente existente siempre trae correo).
                    if (json.data.nombre) document.getElementById('ncNombre').value = json.data.nombre;
                    if (json.data.mail) document.getElementById('ncEmail').value = json.data.mail;
                }
                return;
            }

            const etiqueta = json.source === 'proveedor' ? '✓ Ya es proveedor' : '✓ SRI';
            mostrarBadgeSriNc(etiqueta, 'bg-success');
            if (json.data?.nombre) document.getElementById('ncNombre').value = json.data.nombre;
            if (json.data?.mail) document.getElementById('ncEmail').value = json.data.mail;
        } catch (e) {
            mostrarBadgeSriNc('Error de consulta', 'bg-danger');
        }
    }

    document.getElementById('btnGuardarClienteNuevo').addEventListener('click', async () => {
        const tipoId = document.getElementById('ncTipoId').value;
        const identificacion = document.getElementById('ncIdentificacion').value.trim();
        const nombre = document.getElementById('ncNombre').value.trim();
        const email = document.getElementById('ncEmail').value.trim();
        const telefono = document.getElementById('ncTelefono').value.trim();

        if (!tipoId || !identificacion || !nombre || !email) {
            swalWarning('Completa tipo de identificación, identificación, nombre y correo.');
            return;
        }
        if (!validarIdentificacionNc() || !validarEmailsNc()) {
            return;
        }

        const fd = new FormData();
        fd.append('tipo_id', tipoId);
        fd.append('identificacion', identificacion);
        fd.append('nombre', nombre);
        fd.append('email', email);
        fd.append('telefono', telefono);

        const $btn = document.getElementById('btnGuardarClienteNuevo');
        $btn.disabled = true;
        try {
            const res = await fetch(BASE + '/modulos/clientes/store', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) {
                swalError(json.error || 'No se pudo crear el cliente.');
                return;
            }
            // store() no devuelve el id creado: se re-consulta por identificación para autoseleccionarlo.
            const resBuscar = await fetch(AJAX + '/getClientesAjax?q=' + encodeURIComponent(identificacion), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const jsonBuscar = await resBuscar.json();
            const match = (jsonBuscar.data || []).find(c => c.identificacion === identificacion) || (jsonBuscar.data || [])[0];
            if (match) seleccionarCliente(match);
            modalClienteNuevo.hide();
            swalToast('success', 'Cliente creado y seleccionado.');
        } catch (e) {
            swalError('Error de conexión al crear el cliente.');
        } finally {
            $btn.disabled = false;
        }
    });

    async function buscarProductos(q) {
        $grid.innerHTML = '<div class="text-center py-4 pv-empty" style="grid-column: 1 / -1;"><span class="spinner-border spinner-border-sm"></span> Buscando...</div>';
        try {
            const res = await fetch(AJAX + '/getProductosAjax?q=' + encodeURIComponent(q) + '&id_bodega=' + (getIdBodega() || ''), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            renderGrid(json.data || []);
        } catch (e) {
            $grid.innerHTML = '<div class="text-center py-4 text-danger" style="grid-column: 1 / -1;">Error al cargar productos.</div>';
        }
    }

    function renderGrid(rows) {
        if (!rows.length) {
            $grid.innerHTML = '<div class="text-center py-4 pv-empty" style="grid-column: 1 / -1;"><i class="bi bi-box-seam fs-3 d-block mb-2"></i>Sin resultados.</div>';
            return;
        }
        $grid.innerHTML = '';
        rows.forEach(p => {
            const tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'pv-tile';
            const thumbHtml = p.imagen
                ? '<img class="thumb" src="' + BASE + '/' + escapeHtml(p.imagen) + '" alt="" loading="lazy">'
                : '';
            const sinStock = (SOLO_STOCK_POSITIVO && p.stock_pos !== undefined && parseFloat(p.stock_pos) <= 0)
                ? '<span class="sin-stock" title="Sin stock"><i class="bi bi-exclamation-triangle-fill"></i></span>'
                : '';
            const pctIva = parseFloat(p.porcentaje_iva_final || 0);
            const ivaTag = '<span class="iva-tag' + (pctIva === 0 ? ' iva-cero' : '') + '">IVA ' + pctIva + '%</span>';
            tile.innerHTML = sinStock + thumbHtml +
                '<div class="nombre">' + escapeHtml(p.nombre || '') + '</div>' +
                '<div class="precio-row"><span class="precio">' + money(p.precio_base) + '</span>' + ivaTag + '</div>' +
                '<div class="codigo">' + escapeHtml(p.codigo || '') + '</div>';
            tile.addEventListener('click', () => addToCart(p));
            $grid.appendChild(tile);
        });
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function esInventariableControlado(p) {
        const inv = p.inventariable === true || p.inventariable === 't' || p.inventariable === 'true' || p.inventariable == 1;
        return inv && p.tipo_produccion !== '02';
    }
    function requiereLote(p) { return OBLIGATORIO_LOTES && esInventariableControlado(p); }
    function requiereNup(p) { return OBLIGATORIO_NUP && esInventariableControlado(p); }

    async function addToCart(p) {
        const idProducto = parseInt(p.id, 10);
        const necesitaNup = requiereNup(p);

        // "¿Trabajar con stock positivo?" (empresa → Facturación): no se puede
        // vender lo que no hay. Se valida aquí, al escoger el producto, no
        // recién al cobrar — igual que ya hace el backend en Factura/Recibo.
        if (SOLO_STOCK_POSITIVO && esInventariableControlado(p)) {
            const stockDisponible = parseFloat(p.stock_pos ?? 0);
            const yaEnCarrito = cart.filter(l => l.id_producto === idProducto).reduce((s, l) => s + l.cantidad, 0);
            if (yaEnCarrito + 1 > stockDisponible) {
                swalWarning('No hay stock suficiente de "' + escapeHtml(p.nombre) + '" (disponible: ' + stockDisponible + ').');
                $buscar.focus();
                return;
            }
        }

        // Con NÚP obligatorio cada unidad es su propia línea (un número de
        // serie por línea) — nunca se fusiona con una existente.
        if (!necesitaNup) {
            const existente = cart.find(l => l.id_producto === idProducto);
            if (existente) {
                existente.cantidad += 1;
                renderCart();
                $buscar.focus();
                return;
            }
        }

        // Si hace falta lote Y NUP a la vez, se piden juntos en un solo modal
        // (antes eran dos modales seguidos y era fácil quedarse en el primero
        // sin darse cuenta de que faltaba el NUP).
        let lote = '', caducidad = '', nup = '';
        if (requiereLote(p)) {
            const elegido = await seleccionarLote(p, necesitaNup);
            if (!elegido) { $buscar.focus(); return; } // cancelado o sin stock
            lote = elegido.lote;
            caducidad = elegido.caducidad;
            nup = elegido.nup || '';
        } else if (necesitaNup) {
            const val = await capturarNup(p);
            if (val === null) { $buscar.focus(); return; } // cancelado
            nup = val;
        }

        cart.push({
            uid: ++lineSeq,
            id_producto: idProducto,
            descripcion: p.nombre,
            precio_unitario: parseFloat(p.precio_base || 0),
            pct_iva: parseFloat(p.porcentaje_iva_final || 0),
            cantidad: 1,
            lote,
            caducidad,
            nup,
        });
        renderCart();
        $buscar.focus();
    }

    function seleccionarLote(p, necesitaNup) {
        return fetch(AJAX + '/getLotesAjax?id_producto=' + p.id + '&id_bodega=' + (getIdBodega() || ''), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.json())
            .then(json => {
                const lotes = (json.data || []).filter(l => parseFloat(l.stock_lote || 0) > 0);
                if (!lotes.length) {
                    swalWarning('No hay stock con lote disponible para "' + escapeHtml(p.nombre) + '".');
                    return null;
                }
                const faltaCaducidad = OBLIGATORIO_CADUCIDAD && !lotes[0].fecha_caducidad;
                // Con NÚP obligatorio siempre se abre el modal (hay que escribir el
                // número de serie a mano, nunca se puede autorresolver).
                if (lotes.length === 1 && !faltaCaducidad && !necesitaNup) {
                    const l = lotes[0];
                    return { lote: l.numero_lote === 'sin_lote' ? '' : l.numero_lote, caducidad: l.fecha_caducidad || '', nup: '' };
                }
                return abrirModalLote(p, lotes, necesitaNup);
            })
            .catch(() => {
                swalError('Error de conexión al consultar los lotes de "' + escapeHtml(p.nombre) + '".');
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
                if (OBLIGATORIO_CADUCIDAD && !$cad.value) {
                    $cad.focus();
                    return;
                }
                if (necesitaNup && !$nup.value.trim()) {
                    $nup.focus();
                    return;
                }
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
            setTimeout(() => $input.focus(), 300);
        });
    }

    function totalCarrito() {
        let subtotal = 0, iva = 0;
        cart.forEach(l => {
            const base = l.precio_unitario * l.cantidad;
            subtotal += base;
            iva += base * l.pct_iva / 100;
        });
        return { subtotal, iva, total: subtotal + iva };
    }

    function cambiarCantidad(uid, delta) {
        const linea = cart.find(l => l.uid === uid);
        if (!linea) return;
        linea.cantidad += delta;
        if (linea.cantidad <= 0) {
            cart = cart.filter(l => l.uid !== uid);
        }
        renderCart();
    }

    function quitarLinea(uid) {
        cart = cart.filter(l => l.uid !== uid);
        renderCart();
    }

    function renderCart() {
        if (!cart.length) {
            $lineas.innerHTML = '<div class="text-center py-4 pv-empty small">El carrito está vacío.<br>Toca un producto para agregarlo.</div>';
        } else {
            $lineas.innerHTML = '';
            cart.forEach(l => {
                const base = l.precio_unitario * l.cantidad;
                const row = document.createElement('div');
                row.className = 'pv-linea';
                const loteTag = l.lote ? ' <span class="badge bg-secondary bg-opacity-25 text-secondary">Lote ' + escapeHtml(l.lote) + '</span>' : '';
                const nupTag = l.nup ? ' <span class="badge bg-info bg-opacity-25 text-info">S/N ' + escapeHtml(l.nup) + '</span>' : '';
                const qtyHtml = l.nup
                    ? '<span class="small text-muted">1 unidad</span>'
                    : '<div class="pv-qty">' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-act="menos">-</button>' +
                        '<span>' + l.cantidad + '</span>' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-act="mas">+</button>' +
                      '</div>';
                row.innerHTML =
                    '<div class="desc"><div class="n">' + escapeHtml(l.descripcion) + loteTag + nupTag + '</div><div class="p">' + money(l.precio_unitario) + ' c/u</div></div>' +
                    qtyHtml +
                    '<div class="total">' + money(base) + '</div>' +
                    '<i class="bi bi-x-lg rm" data-act="rm"></i>';
                row.querySelector('[data-act="menos"]')?.addEventListener('click', () => cambiarCantidad(l.uid, -1));
                row.querySelector('[data-act="mas"]')?.addEventListener('click', () => cambiarCantidad(l.uid, 1));
                row.querySelector('[data-act="rm"]').addEventListener('click', () => quitarLinea(l.uid));
                $lineas.appendChild(row);
            });
        }

        const { subtotal, iva, total } = totalCarrito();
        document.getElementById('pv-subtotal').textContent = money(subtotal);
        document.getElementById('pv-iva').textContent = money(iva);
        document.getElementById('pv-total').textContent = money(total);

        const $avisoCf = document.getElementById('pv-aviso-cf');
        const superaLimiteSinCliente = !clienteSeleccionado && total >= LIMITE_CONSUMIDOR_FINAL;
        if (superaLimiteSinCliente) {
            $avisoCf.textContent = 'Venta a Consumidor Final: máximo ' + money(LIMITE_CONSUMIDOR_FINAL) + '. Selecciona o crea un cliente para continuar.';
            $avisoCf.classList.remove('d-none');
        } else {
            $avisoCf.classList.add('d-none');
        }

        $btnCobrar.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Cobrar ' + money(total);
        $btnCobrar.disabled = cart.length === 0 || superaLimiteSinCliente || !getTipoDocumento();

        renderTickets();
        guardarTicketsStorage();
    }

    const $pagos = document.getElementById('pv-pagos');
    const $pagoBanco = document.getElementById('pv-pago-banco');
    const $btnLinkWhatsapp = document.getElementById('pv-btn-link-whatsapp');
    let formasPagoCargadas = [];

    async function cargarFormasPago() {
        try {
            const res = await fetch(AJAX + '/getFormasPagoAjax', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            renderFormasPago(json.data || []);
        } catch (e) {
            renderFormasPago([]);
        }
    }

    function renderFormasPago(formas) {
        // Si la empresa no tiene ninguna forma de pago configurada para
        // Ingresos, se cae a un "Efectivo" genérico para que el POS siga
        // siendo usable.
        if (!formas.length) {
            formas = [{ id: 0, nombre: 'Efectivo', codigo_sri: '01', tipo: 'EFECTIVO' }];
        }
        formasPagoCargadas = formas;
        $pagos.disabled = false;
        $pagos.innerHTML = '';
        formas.forEach(f => {
            const opt = document.createElement('option');
            opt.value = String(f.id);
            opt.textContent = f.nombre + (f.banco_nombre ? ' — ' + f.banco_nombre : '');
            $pagos.appendChild(opt);
        });
        $pagos.addEventListener('change', actualizarFormaPagoSeleccionada);

        // El favorito se aplica recién ahora: al iniciar la página el select
        // todavía decía "Cargando...", sin opciones reales que precargar.
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('.pv-carrito');
        }
        actualizarFormaPagoSeleccionada();
    }

    const $pagoBancoExtra = document.getElementById('pv-pago-banco-extra');
    const $tipoOpBanco = document.getElementById('pv-tipo-op-banco');
    const $numOpBanco = document.getElementById('pv-num-op-banco');
    const $wrapFechaCheque = document.getElementById('pv-wrap-fecha-cheque');
    const $fechaCheque = document.getElementById('pv-fecha-cheque');

    function actualizarFormaPagoSeleccionada() {
        if (!formasPagoCargadas.length) return; // aún no responde getFormasPagoAjax
        const f = formasPagoCargadas.find(x => String(x.id) === $pagos.value) || formasPagoCargadas[0];
        formaPago = f.codigo_sri;
        idFormaPagoEmpresa = parseInt(f.id, 10) || 0;

        const tipo = (f.tipo || '').toUpperCase();
        if (tipo === 'BANCO') {
            const partes = [f.banco_nombre, f.tipo_cuenta, f.numero_cuenta ? ('Cta. ' + f.numero_cuenta) : null].filter(Boolean);
            $pagoBanco.textContent = partes.join(' · ');
            $pagoBanco.classList.remove('d-none');
            $pagoBancoExtra.classList.remove('d-none');
        } else {
            $pagoBanco.classList.add('d-none');
            $pagoBancoExtra.classList.add('d-none');
            $tipoOpBanco.value = 'TRANSFERENCIA';
            $numOpBanco.value = '';
            $wrapFechaCheque.classList.add('d-none');
            $fechaCheque.value = '';
        }

        $btnLinkWhatsapp.classList.toggle('d-none', tipo !== 'TARJETA');
    }

    $tipoOpBanco.addEventListener('change', () => {
        const esCheque = $tipoOpBanco.value === 'CHEQUE';
        $wrapFechaCheque.classList.toggle('d-none', !esCheque);
        if (!esCheque) $fechaCheque.value = '';
    });

    $btnLinkWhatsapp.addEventListener('click', async () => {
        const { total } = totalCarrito();
        if (total <= 0) { swalWarning('El carrito está vacío.'); return; }

        const res = await Swal.fire({
            title: 'Enviar link de pago',
            html:
                '<div class="text-start">' +
                '<label class="form-label small fw-semibold text-uppercase text-muted mb-1">WhatsApp del cliente</label>' +
                '<input type="tel" id="swalTelefono" class="form-control form-control-sm mb-2" placeholder="0991234567 o 593991234567" value="' + escapeHtml(clienteSeleccionado?.telefono || '') + '">' +
                '<div class="small text-muted">Se enviará un enlace por <b>' + money(total) + '</b> a nombre de <b>' + escapeHtml(clienteSeleccionado?.nombre || 'Consumidor Final') + '</b>.</div>' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            focusConfirm: false,
            preConfirm: () => {
                const val = document.getElementById('swalTelefono').value.trim();
                if (!val) { Swal.showValidationMessage('Ingresa un número de WhatsApp.'); return false; }
                return val;
            },
        });
        if (!res.isConfirmed) return;

        $btnLinkWhatsapp.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_punto_emision', ID_PUNTO);
            fd.append('telefono', res.value);
            fd.append('nombre_cliente', clienteSeleccionado?.nombre || 'Consumidor Final');
            fd.append('monto', total.toFixed(2));
            const resp = await fetch(AJAX + '/enviarLinkPagoWhatsappAjax', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await resp.json();
            if (!json.ok) {
                swalError(json.error || 'No se pudo enviar el enlace de pago.');
                return;
            }
            swalToast('success', json.msg || 'Enlace enviado.');
        } catch (e) {
            swalError('Error de conexión al enviar el enlace de pago.');
        } finally {
            $btnLinkWhatsapp.disabled = false;
        }
    });

    // ─── Pestañas de venta ──────────────────────────────────────────────
    function snapshotTicketActual() {
        const t = tickets.find(x => x.id === ticketActivoId);
        if (!t) return;
        t.cart = cart;
        t.clienteSeleccionado = clienteSeleccionado;
        t.tipoDocumento = getTipoDocumento();
        t.pagoSelId = $pagos.value;
        t.formaPago = formaPago;
        t.idFormaPagoEmpresa = idFormaPagoEmpresa;
        t.tipoOpBanco = $tipoOpBanco.value;
        t.numOpBanco = $numOpBanco.value;
        t.fechaCheque = $fechaCheque.value;
    }

    // "Venta N" usa el menor número no ocupado por una pestaña abierta, no un
    // contador que solo sube: si cierras las pestañas ya cobradas, el
    // siguiente "+" vuelve a ofrecer "Venta 1" en vez de seguir subiendo
    // durante todo el turno.
    function siguienteNumeroTicket() {
        const usados = new Set(tickets.map(t => t.numero));
        let n = 1;
        while (usados.has(n)) n++;
        return n;
    }

    function nuevoTicket(activar = true) {
        if (ticketActivoId !== null) snapshotTicketActual();
        ticketSeq += 1;
        const t = {
            id: ticketSeq,
            numero: siguienteNumeroTicket(),
            cart: [],
            clienteSeleccionado: null,
            tipoDocumento: null,
            pagoSelId: '',
            formaPago: '01',
            idFormaPagoEmpresa: 0,
            tipoOpBanco: 'TRANSFERENCIA',
            numOpBanco: '',
            fechaCheque: '',
        };
        tickets.push(t);
        if (activar) {
            activarTicket(t.id);
        } else {
            renderTickets();
        }
        return t;
    }

    function activarTicket(id) {
        if (ticketActivoId !== null && ticketActivoId !== id) snapshotTicketActual();
        const t = tickets.find(x => x.id === id);
        if (!t) return;
        ticketActivoId = id;

        cart = t.cart;
        clienteSeleccionado = t.clienteSeleccionado;
        $clienteNombre.textContent = clienteSeleccionado ? clienteSeleccionado.nombre : 'Consumidor Final';
        $clienteBuscarWrap.classList.add('d-none');

        const $selDoc = document.getElementById('pv-tipo-documento');
        if ($selDoc && t.tipoDocumento) $selDoc.value = t.tipoDocumento;

        if (formasPagoCargadas.length) {
            if (t.pagoSelId) $pagos.value = t.pagoSelId;
            actualizarFormaPagoSeleccionada();
        } else {
            formaPago = t.formaPago;
            idFormaPagoEmpresa = t.idFormaPagoEmpresa;
        }
        $tipoOpBanco.value = t.tipoOpBanco;
        $numOpBanco.value = t.numOpBanco;
        $fechaCheque.value = t.fechaCheque;
        $wrapFechaCheque.classList.toggle('d-none', t.tipoOpBanco !== 'CHEQUE');

        renderCart();
    }

    function cerrarTicket(id) {
        const idx = tickets.findIndex(x => x.id === id);
        if (idx === -1) return;
        const t = tickets[idx];
        const tieneProductos = (t.id === ticketActivoId ? cart : t.cart).length > 0;
        if (tieneProductos) {
            Swal.fire({
                icon: 'warning',
                title: 'Cerrar venta en curso',
                html: 'Esta pestaña tiene productos sin cobrar. ¿Cerrarla de todas formas?',
                showCancelButton: true,
                confirmButtonText: 'Cerrar y descartar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            }).then(res => {
                if (res.isConfirmed) quitarTicket(idx, id);
            });
            return;
        }
        quitarTicket(idx, id);
    }

    function quitarTicket(idx, id) {
        tickets.splice(idx, 1);
        if (!tickets.length) {
            ticketActivoId = null;
            nuevoTicket();
            return;
        }
        if (ticketActivoId === id) {
            const siguiente = tickets[Math.max(0, idx - 1)];
            ticketActivoId = null;
            activarTicket(siguiente.id);
        } else {
            renderTickets();
        }
    }

    function renderTickets() {
        $tickets.innerHTML = '';
        tickets.forEach(t => {
            const activo = t.id === ticketActivoId;
            const tab = document.createElement('div');
            tab.className = 'pv-ticket-tab' + (activo ? ' active' : '');
            const cantidad = (activo ? cart : t.cart).length;
            const cliente = activo ? clienteSeleccionado : t.clienteSeleccionado;
            const label = cliente ? cliente.nombre : ('Venta ' + t.numero);
            tab.innerHTML = '<span class="nombre">' + escapeHtml(label) + '</span>' +
                (cantidad ? ' <span class="badge">' + cantidad + '</span>' : '') +
                (tickets.length > 1 ? ' <i class="bi bi-x cerrar" data-id="' + t.id + '"></i>' : '');
            tab.addEventListener('click', (ev) => {
                if (ev.target.closest('.cerrar')) return;
                if (t.id !== ticketActivoId) activarTicket(t.id);
            });
            const $cerrar = tab.querySelector('.cerrar');
            if ($cerrar) $cerrar.addEventListener('click', (ev) => { ev.stopPropagation(); cerrarTicket(t.id); });
            $tickets.appendChild(tab);
        });
    }

    function guardarTicketsStorage() {
        try {
            snapshotTicketActual();
            // Una pestaña vacía no es "una venta en curso" — no tiene sentido restaurarla
            // al recargar, así que ni se guarda.
            const conItems = tickets.filter(t => t.cart.length > 0);
            if (!conItems.length) {
                localStorage.removeItem(STORAGE_KEY);
                return;
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ tickets: conItems, ticketActivoId, ticketSeq }));
        } catch (e) {
            // localStorage lleno, bloqueado o modo privado: se sigue trabajando sin persistir.
        }
    }

    function cargarTicketsStorage() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return false;
            const data = JSON.parse(raw);
            if (!data || !Array.isArray(data.tickets)) return false;

            // Filtro de seguridad por si quedó guardada una pestaña vacía de antes de este cambio.
            tickets = data.tickets.filter(t => Array.isArray(t.cart) && t.cart.length > 0);
            if (!tickets.length) return false;

            ticketSeq = data.ticketSeq || tickets.reduce((m, t) => Math.max(m, t.numero || 0), 0);
            const idActivo = tickets.some(t => t.id === data.ticketActivoId) ? data.ticketActivoId : tickets[0].id;
            ticketActivoId = null; // evita que activarTicket intente hacer snapshot de una pestaña que aún no existe en vivo
            activarTicket(idActivo);
            swalToast('info', 'Se restauraron las ventas en curso.');
            return true;
        } catch (e) {
            return false;
        }
    }

    $btnNuevoTicket.addEventListener('click', () => nuevoTicket());

    $btnCobrar.addEventListener('click', async () => {
        if (!cart.length) return;

        const bancoVisible = !$pagoBancoExtra.classList.contains('d-none');
        if (bancoVisible && $tipoOpBanco.value === 'CHEQUE' && !$fechaCheque.value) {
            swalWarning('Indica la fecha de cobro del cheque.');
            $fechaCheque.focus();
            return;
        }

        $btnCobrar.disabled = true;
        $btnCobrar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';

        const fd = new FormData();
        fd.append('id_punto_emision', ID_PUNTO);
        fd.append('forma_pago', formaPago);
        fd.append('id_forma_pago', idFormaPagoEmpresa || '');
        fd.append('id_cliente', clienteSeleccionado ? clienteSeleccionado.id : '');
        fd.append('id_bodega', getIdBodega() || '');
        fd.append('tipo_documento', getTipoDocumento() || 'RECIBO');
        if (bancoVisible) {
            fd.append('tipo_operacion_bancaria', $tipoOpBanco.value);
            fd.append('numero_operacion', $numOpBanco.value.trim());
            if ($tipoOpBanco.value === 'CHEQUE') fd.append('fecha_cobro', $fechaCheque.value);
        }
        fd.append('items', JSON.stringify(cart.map(l => ({
            id_producto: l.id_producto,
            descripcion: l.descripcion,
            cantidad: l.cantidad,
            precio_unitario: l.precio_unitario,
            lote: l.lote || '',
            caducidad: l.caducidad || '',
            nup: l.nup || '',
        }))));

        try {
            const res = await fetch(AJAX + '/cobrarAjax', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) {
                swalError(json.error || 'No se pudo registrar la venta.');
                renderCart();
                return;
            }
            const esFactura = json.data.tipo_documento === 'FACTURA';
            const etiquetaDoc = esFactura ? 'Factura' : 'Recibo';
            const moduloDestino = esFactura ? 'Facturas de Venta' : 'Recibos de Venta';
            const notaFactura = esFactura
                ? 'Queda pendiente de enviar al SRI — hazlo desde el módulo'
                : 'Queda guardada como recibo interno — puedes verla, imprimirla o enviarla desde el módulo';
            Swal.fire({
                icon: 'success',
                title: 'Venta registrada',
                html: etiquetaDoc + ' <b>' + escapeHtml(json.data.numero_documento) + '</b> por <b>' + money(json.data.importe_total) + '</b>.' +
                      '<br><br><span class="text-muted small">Ya se descontó el inventario y se generó el asiento contable. ' + notaFactura + ' <b>' + moduloDestino + '</b>.</span>',
                confirmButtonColor: '#198754',
                confirmButtonText: 'Nueva venta'
            });
            if (json.data.aviso_ingreso) {
                setTimeout(() => swalWarning(escapeHtml(json.data.aviso_ingreso) + ' Regístralo manualmente desde el módulo Ingresos.'), 300);
            }
            cart = [];
            seleccionarCliente(null);
            $numOpBanco.value = '';
            $tipoOpBanco.value = 'TRANSFERENCIA';
            $wrapFechaCheque.classList.add('d-none');
            $fechaCheque.value = '';
            renderCart();
            snapshotTicketActual();
        } catch (e) {
            swalError('Error de conexión al cobrar.');
            renderCart();
        }
    });

    $buscar.addEventListener('input', () => {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(() => buscarProductos($buscar.value.trim()), 350);
    });

    // Los lectores de código de barras "escriben" el código y rematan con Enter.
    $buscar.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            escanearCodigo($buscar.value.trim());
            return;
        }
        // Backspace/Delete limpian el campo de una vez, en vez de borrar
        // carácter por carácter — útil para corregir un escaneo fallido rápido.
        if (ev.key === 'Backspace' || ev.key === 'Delete') {
            ev.preventDefault();
            $buscar.value = '';
            buscarProductos('');
        }
    });

    async function escanearCodigo(valor) {
        if (!valor) return;
        clearTimeout(buscarTimer);

        try {
            const res = await fetch(AJAX + '/getProductosAjax?q=' + encodeURIComponent(valor) + '&id_bodega=' + (getIdBodega() || ''), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            const rows = json.data || [];

            const valorNorm = valor.toLowerCase();
            let match = rows.find(p =>
                (p.codigo_barras || '').toLowerCase() === valorNorm ||
                (p.codigo || '').toLowerCase() === valorNorm ||
                (p.codigo_auxiliar || '').toLowerCase() === valorNorm
            );
            if (!match && rows.length === 1) {
                match = rows[0];
            }

            if (match) {
                addToCart(match);
                $buscar.value = '';
                renderGrid(rows.filter(p => p.id !== match.id));
            } else if (rows.length > 1) {
                renderGrid(rows);
                swalToast('warning', 'Varios productos coinciden con "' + valor + '" — elige uno de la lista.');
            } else {
                swalToast('warning', 'No se encontró ningún producto con el código "' + valor + '".');
            }
        } catch (e) {
            swalToast('error', 'Error de conexión al buscar el código.');
        } finally {
            $buscar.focus();
        }
    }

    const $selBodega = document.getElementById('pv-id-bodega');
    let bodegaConfirmada = null;
    $selBodega?.addEventListener('change', async () => {
        const nueva = getIdBodega();
        if (cart.length) {
            const res = await Swal.fire({
                icon: 'warning',
                title: 'Cambiar de bodega',
                html: 'El carrito tiene productos con lote/stock de la bodega anterior. Cambiar de bodega vacía el carrito.',
                showCancelButton: true,
                confirmButtonText: 'Cambiar y vaciar carrito',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (!res.isConfirmed) {
                $selBodega.value = String(bodegaConfirmada);
                return;
            }
            cart = [];
            renderCart();
        }
        bodegaConfirmada = nueva;
        buscarProductos($buscar.value.trim());
    });

    if (typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('.pv-carrito');
    }
    bodegaConfirmada = getIdBodega();
    cargarFormasPago();
    if (!cargarTicketsStorage()) {
        nuevoTicket();
    }
    buscarProductos('');
    $buscar.focus();
})();
</script>
</body>
</html>
