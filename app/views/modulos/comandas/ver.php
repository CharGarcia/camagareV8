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
    <?php require MVC_APP . "/views/partials/csrf.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <?php
        // Favoritos (estrella de la forma de pago). Esta vista es standalone —no
        // pasa por partials/head.php—, así que las variables y el JS se cargan
        // aquí a mano, igual que se hace con el token CSRF de arriba.
        echo \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo);
    ?>
    <script src="<?= $base ?>/js/favoritos.js?v=<?= time() ?>"></script>
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
        /* El campo de propina se ajusta al alto de las demás filas del pie: un
           input-group-sm de Bootstrap mide bastante más que el texto de al lado
           y hacía crecer la fila. */
        .cm-totales .cm-propina-grp { width: 84px; }
        .cm-totales .cm-propina-grp .input-group-text,
        .cm-totales .cm-propina-grp .form-control { height: 22px; min-height: 22px; font-size: .78rem; line-height: 1; }
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
    <!--
        Aviso de mesa compartida. Varios meseros pueden estar en la misma mesa a
        la vez: agregar ítems se permite (una línea de más se anula), pero el
        cobro queda bloqueado mientras otro la tiene abierta — ahí se emitiría un
        comprobante duplicado. Lo llena CMG_Bloqueo.onBloqueado (ver más abajo).
    -->
    <div id="cm-aviso-en-uso" class="alert alert-warning border-0 rounded-0 mb-0 py-2 px-3 d-none" role="alert">
        <i class="bi bi-people-fill me-1"></i>
        <span id="cm-aviso-en-uso-texto"></span>
    </div>
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
                    <input type="radio" class="btn-check" name="cm-origen" id="cm-origen-todos" value="todos">
                    <label class="btn btn-outline-secondary" for="cm-origen-todos">Todos</label>
                    <!-- El salón trabaja con la carta: arranca filtrado en Menú
                         para no tener que buscar entre todo el stock general. -->
                    <input type="radio" class="btn-check" name="cm-origen" id="cm-origen-menu" value="menu" checked>
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
                <div class="row"><div><span class="text-muted">Subtotal</span><span id="cm-subtotal">$0.00</span></div></div>
                <div id="cm-impuestos"></div>
                <div class="row d-none" id="cm-fila-servicio">
                    <div>
                        <span class="text-muted">
                            <span id="cm-servicio-label">Servicio</span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline d-none" id="cm-btn-quitar-servicio" style="font-size:.72rem;"></button>
                        </span>
                        <span id="cm-servicio">$0.00</span>
                    </div>
                </div>
                <?php if (!empty($empresaConfig['id_producto_propina']) && !empty($perm['crear']) && ($comanda['estado'] ?? '') === 'abierta'): ?>
                <div class="row" id="cm-fila-propina">
                    <div>
                        <span class="text-muted">Propina voluntaria</span>
                        <span class="input-group input-group-sm cm-propina-grp">
                            <span class="input-group-text px-1">$</span>
                            <input type="number" class="form-control text-end px-1" id="cm-propina" step="0.01" min="0" placeholder="0.00">
                        </span>
                    </div>
                </div>
                <?php else: ?>
                <div class="row d-none" id="cm-fila-propina">
                    <div><span class="text-muted">Propina</span><span id="cm-propina-valor">$0.00</span></div>
                </div>
                <?php endif; ?>
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
        <div class="mb-2">
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="cb-modo-split" id="cb-modo-items" value="items" checked>
            <label class="btn btn-outline-secondary btn-sm" for="cb-modo-items">Por ítems</label>
            <input type="radio" class="btn-check" name="cb-modo-split" id="cb-modo-partes" value="partes_iguales">
            <label class="btn btn-outline-secondary btn-sm" for="cb-modo-partes">Partes iguales</label>
          </div>
        </div>
        <div class="mb-2 d-none" id="cb-num-partes-wrap">
          <label class="form-label small mb-1">¿Entre cuántas partes?</label>
          <input type="number" class="form-control form-control-sm" id="cb-num-partes" min="2" value="2" style="max-width:100px;">
          <div class="form-text mt-0" style="font-size:0.65rem;">Cada ítem seleccionado se reparte en partes iguales — cada parte genera su propio documento.</div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold small">Ítems sin cobrar</div>
          <button type="button" class="btn btn-sm btn-link p-0" id="cb-toggle-todos">Marcar/desmarcar todos</button>
        </div>
        <div id="cb-lista-lineas" class="mb-3"></div>
        <div class="d-flex justify-content-between fw-semibold border-top pt-2">
          <span>Total seleccionado <small class="text-muted fw-normal">(IVA incluido)</small></span><span id="cb-total-sel">$0.00</span>
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
        <!-- El salón emite siempre Factura: ya no se elige el tipo de documento.
             El valor viaja igual en el hidden para no cambiar lo que espera el
             servidor (PosVentaService sigue recibiendo tipo_documento). -->
        <div class="mb-2">
          <label class="form-label small mb-1">Documento</label>
          <div class="form-control form-control-sm bg-light d-flex align-items-center gap-2" style="cursor:default;">
            <i class="bi bi-receipt-cutoff text-primary"></i><span class="fw-semibold">Factura</span>
          </div>
          <input type="hidden" id="pg-tipo-doc" value="FACTURA">
        </div>
        <div class="mb-2 position-relative">
          <label class="form-label small mb-1">Cliente (opcional; Consumidor Final si se deja vacío)</label>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control form-control-sm" id="pg-cliente-buscar" placeholder="Buscar cliente por nombre/identificación...">
            <button type="button" class="btn btn-outline-secondary" id="pg-btn-nuevo-cliente" title="Nuevo cliente"><i class="bi bi-person-plus"></i></button>
          </div>
          <input type="hidden" id="pg-id-cliente" value="0">
          <div id="pg-cliente-resultados" class="list-group position-absolute w-100" style="z-index:1080;"></div>
        </div>
        <div id="pg-aviso-cf" class="small text-danger mb-2 d-none"></div>
        <div class="mb-2">
          <label class="form-label small mb-1">
            Forma de pago
            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'pg-forma-pago', 'id_forma_pago') ?>
          </label>
          <select class="form-select form-select-sm" id="pg-forma-pago"></select>
          <div class="form-text mt-1 d-none" id="pg-forma-sugerida" style="font-size:.72rem;">
            <i class="bi bi-qrcode me-1"></i>El cliente sugirió pagar con <b id="pg-forma-sugerida-nombre"></b>. Puedes cambiarla.
          </div>
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

<!-- Modal: nuevo cliente (mini-formulario, mismo patrón que caja_sesion/venta.php) -->
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
            <label class="form-label small fw-semibold text-uppercase text-muted mb-1">Tipo ID</label>
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
<script src="<?= $base ?>/js/bloqueo-edicion.js?v=<?= time() ?>"></script>
<script>
// Igual que el tablero de mesas: si el navegador restaura esta comanda desde el
// bfcache al ir "atrás", se recarga contra el servidor — el turno de caja pudo
// cerrarse y la pantalla se veía operativa.
window.addEventListener('pageshow', function (e) {
    if (e.persisted) { window.location.reload(); }
});
(function () {
    const BASE = "<?= $base ?>";
    const AJAX = BASE + '/modulos/comandas';
    const ID_COMANDA = <?= (int) $comanda['id'] ?>;

    // Mesa compartida: se toma el bloqueo de la comanda al abrirla y se suelta al
    // salir. NO deja la pantalla en solo lectura (en el salón dos meseros pueden
    // atender la misma mesa); solo avisa quién está dentro. Lo que sí queda
    // vetado es el cobro, y eso lo decide el servidor
    // (ComandasController::cobrarGrupoAjax), no este aviso.
    function mostrarEnUso(texto) {
        const $aviso = document.getElementById('cm-aviso-en-uso');
        const $texto = document.getElementById('cm-aviso-en-uso-texto');
        if (!$aviso || !$texto) return;
        $texto.textContent = texto;
        $aviso.classList.remove('d-none');
    }
    if (window.CMG_Bloqueo && ID_COMANDA > 0) {
        CMG_Bloqueo.iniciar({
            urlBase: AJAX,
            idRegistro: ID_COMANDA,
            moduloContexto: 'comandas',
            onBloqueado: (info) => mostrarEnUso(
                'Esta mesa la está atendiendo ' + ((info && info.usuario) ? info.usuario : 'otro usuario') +
                '. Puedes seguir tomando el pedido, pero el cobro lo hace quien la tiene abierta.'
            ),
            onPerdido: () => mostrarEnUso('Se perdió el control de esta mesa por inactividad. Recarga antes de cobrar.'),
        });
        // Soltar el lock al salir (volver al tablero, cerrar la pestaña): si no,
        // la mesa queda "ocupada" para los demás hasta que expire el TTL.
        window.addEventListener('pagehide', () => { CMG_Bloqueo.detener(); });
    }
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
    // Recargo por servicio (el "10%" de restaurantes). Se cobra como PROPINA en
    // el comprobante: se calcula sobre la base sin impuestos y se suma después
    // del IVA, que es como lo admite el XML del SRI (campo <propina>, topado al
    // 10% del subtotal).
    // El modo lo fija el establecimiento: 'no' | 'obligatorio' | 'opcional'.
    // Solo en 'opcional' el mesero puede quitarlo de una cuenta concreta.
    // servicio_efectivo lo resuelve ComandaService: ya trae aplicadas las reglas
    // (obligatorio manda sobre el estado de la comanda, apagado gana siempre),
    // así que 0 significa "no se cobra" sin más análisis en la pantalla.
    const SERVICIO_MODO = <?= json_encode((string) ($empresaConfig['servicio_restaurante'] ?? 'no')) ?>;
    let porcentajeServicio = <?= (float) ($comanda['servicio_efectivo'] ?? 0) ?>;
    let aplicaServicio = porcentajeServicio > 0;

    // Venta a Consumidor Final: mismo límite configurado en empresa → Facturación (PosVentaService lo vuelve a exigir al cobrar).
    const LIMITE_CONSUMIDOR_FINAL = <?= (float) ($empresaConfig['valor_limite_consumidor_final'] ?? 50) ?>;
    const $grid = document.getElementById('cm-grid');
    const $buscar = document.getElementById('cm-buscar');
    const $lineas = document.getElementById('cm-lineas');
    const $total = document.getElementById('cm-total');
    const $subtotal = document.getElementById('cm-subtotal');
    const $impuestos = document.getElementById('cm-impuestos');
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

    // Las cantidades vienen de Postgres como numeric ("1.000000"). Se muestran
    // con los decimales que tenga configurada la empresa (Empresa → Facturación
    // → Decimales), igual que en Factura de Venta.
    const DEC_CANT = <?= (int) ($empresaConfig['decimales_cantidad'] ?? 2) ?>;
    function cantidad(v) { return (parseFloat(v) || 0).toFixed(DEC_CANT); }

    // Lo mismo para el PRECIO UNITARIO, que puede llevar más decimales que el
    // dinero (un producto a 15.6522). Solo aplica al unitario: los importes
    // —subtotales, IVA, totales— son dinero y van siempre a 2 decimales.
    const DEC_PRECIO = <?= (int) ($empresaConfig['decimales_precio'] ?? 2) ?>;
    function precio(v) { return (parseFloat(v) || 0).toFixed(DEC_PRECIO); }
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

    // ─── Totales de la comanda ──────────────────────────────────────────────
    // comanda_detalle.subtotal es la base SIN impuestos, así que el "Total" que
    // ve el mesero (y el cliente en la cuenta) tiene que sumarle el IVA: es el
    // valor que se va a cobrar.
    // El IVA se redondea LÍNEA POR LÍNEA y luego se suma, exactamente como lo
    // hace PosVentaService::cobrar() al emitir el documento. Agruparlo por
    // tarifa y redondear el grupo daría a veces un centavo distinto del que
    // termina en la factura, y esa diferencia la ve el cliente al comparar la
    // cuenta con su comprobante.
    // El porcentaje es el informativo de ComandaRepository::getLineas(); al
    // cobrar, PosVentaService lo resuelve de nuevo desde el producto — esto
    // muestra, no decide.
    const round2 = (n) => Math.round((parseFloat(n) || 0) * 100) / 100;

    // IVA y total CON impuestos de UNA línea. El recargo por servicio queda
    // fuera a propósito: es un recargo de la cuenta (se muestra y se cobra una
    // sola vez en el pie), no del ítem — sumarlo aquí lo mostraría dos veces.
    const ivaLinea = (d) => round2(round2(d.subtotal) * (parseFloat(d.porcentaje_iva) || 0) / 100);
    const totalLineaConIva = (d) => round2(round2(d.subtotal) + ivaLinea(d));

    // La propina voluntaria del cliente viaja como una línea más (el campo
    // <propina> del comprobante ya lo ocupa el recargo por servicio, y es uno
    // solo). Se reconoce por su producto, el configurado en el establecimiento.
    const ID_PRODUCTO_PROPINA = <?= (int) ($empresaConfig['id_producto_propina'] ?? 0) ?>;
    const esLineaPropina = (d) => ID_PRODUCTO_PROPINA > 0 && parseInt(d.id_producto, 10) === ID_PRODUCTO_PROPINA;

    /**
     * ¿Esta línea todavía se puede tocar? Sí mientras no esté en una cuenta, o
     * esté en una que aún no se cobró: el cliente puede pedir la cuenta y
     * después decidir dejar más propina. Una vez cobrada pertenece a un
     * documento emitido y no se toca. Mismo criterio que
     * ComandaRepository::getLineaPropina.
     */
    function lineaEditable(d) {
        if (!d.id_grupo_cobro) return true;
        const g = grupos.find(x => String(x.id) === String(d.id_grupo_cobro));
        // Solo lo cobrado está protegido: un grupo anulado ya no representa nada.
        return !g || g.estado !== 'cobrado';
    }

    /**
     * Propina que se edita desde el pie: la que no está en ninguna cuenta o está
     * en una todavía pendiente de cobro.
     */
    function propinaLibre() {
        return round2(detalles
            .filter(d => d.estado_linea !== 'anulado' && esLineaPropina(d) && lineaEditable(d))
            .reduce((a, d) => a + (parseFloat(d.subtotal) || 0), 0));
    }

    // El recargo por servicio se calcula sobre la base sin impuestos de ESE
    // conjunto de líneas, así que al dividir la cuenta cada parte carga el suyo
    // — igual que PosVentaService, que aplica el porcentaje sobre el subtotal
    // del documento al emitirlo.
    function calcularTotales(vivas) {
        let subtotal = 0, totalImpuestos = 0, propina = 0;
        const impuestos = {};
        vivas.forEach(d => {
            const base = round2(d.subtotal);
            subtotal = round2(subtotal + base);
            if (esLineaPropina(d)) propina = round2(propina + base);
            const pct = parseFloat(d.porcentaje_iva || 0);
            if (pct > 0) {
                const lbl = `IVA ${pct}%`;
                const iva = round2(base * pct / 100);
                impuestos[lbl] = round2((impuestos[lbl] || 0) + iva);
                totalImpuestos = round2(totalImpuestos + iva);
            }
        });
        // El recargo se calcula sobre el CONSUMO, así que la propina voluntaria
        // —que es una línea más— se descuenta de la base. Mismo criterio que
        // PosVentaService al emitir: si no, dejar propina subiría el recargo.
        const baseServicio = round2(subtotal - propina);
        const servicio = (aplicaServicio && porcentajeServicio > 0)
            ? round2(baseServicio * porcentajeServicio / 100)
            : 0;
        return { subtotal, impuestos, totalImpuestos, servicio, propina, total: round2(subtotal + totalImpuestos + servicio) };
    }

    /**
     * Pie de la comanda: línea del recargo por servicio.
     * - 'no'          → la fila no existe.
     * - 'obligatorio' → se ve el valor, sin botón: nadie lo quita desde el salón.
     * - 'opcional'    → se ve con un enlace para quitarlo/volver a ponerlo. Si
     *                   está quitado, la fila sigue visible (en gris, con el
     *                   ofrecimiento de aplicarlo) para que no se olvide.
     */
    function renderServicio(valor) {
        const $fila = document.getElementById('cm-fila-servicio');
        const $btn = document.getElementById('cm-btn-quitar-servicio');
        if (!$fila) return;

        const hayServicio = SERVICIO_MODO !== 'no' && (aplicaServicio || SERVICIO_MODO === 'opcional');
        $fila.classList.toggle('d-none', !hayServicio);
        if (!hayServicio) return;

        const pct = porcentajeServicio > 0 ? porcentajeServicio : 0;
        document.getElementById('cm-servicio-label').textContent =
            aplicaServicio ? `Servicio ${pct}%` : 'Servicio (no aplicado)';
        document.getElementById('cm-servicio').textContent = money(aplicaServicio ? valor : 0);

        const puedeCambiar = SERVICIO_MODO === 'opcional' && PUEDE_ACTUALIZAR;
        $btn.classList.toggle('d-none', !puedeCambiar);
        if (puedeCambiar) {
            $btn.textContent = aplicaServicio ? 'Quitar' : 'Aplicar';
            $btn.className = 'btn btn-link btn-sm p-0 ms-1 align-baseline ' + (aplicaServicio ? 'text-danger' : 'text-success');
            $btn.style.fontSize = '.72rem';
        }
    }

    const $btnServicio = document.getElementById('cm-btn-quitar-servicio');
    if ($btnServicio) {
        $btnServicio.addEventListener('click', async () => {
            const nuevo = !aplicaServicio;
            $btnServicio.disabled = true;
            try {
                const fd = new FormData();
                fd.append('id', ID_COMANDA);
                fd.append('aplica', nuevo ? '1' : '0');
                const r = await fetch(AJAX + '/cambiarServicioAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo cambiar el servicio.'); return; }
                swalToast('success', d.msg);
                await refrescarComanda();
            } catch (e) {
                swalError('Error de conexión.');
            } finally {
                $btnServicio.disabled = false;
            }
        });
    }

    // ─── Propina voluntaria ─────────────────────────────────────────────────
    // Se guarda al salir del campo (o con Enter), no en cada tecla: cada guardado
    // crea o actualiza la línea en el servidor.
    const $propina = document.getElementById('cm-propina');
    const $propinaValor = document.getElementById('cm-propina-valor');
    const $filaPropina = document.getElementById('cm-fila-propina');
    let guardandoPropina = false;

    /** Refleja en el pie lo que realmente hay guardado (el input no se pisa mientras se escribe). */
    function renderPropina(valor) {
        if ($propina) {
            if (document.activeElement !== $propina) {
                $propina.value = valor > 0 ? valor.toFixed(2) : '';
            }
            return;
        }
        // Sin permiso o comanda cerrada: solo se muestra, y únicamente si hay algo.
        if (!$filaPropina || !$propinaValor) return;
        $filaPropina.classList.toggle('d-none', !(valor > 0));
        $propinaValor.textContent = money(valor);
    }

    /** Guarda un monto de propina (0 la quita). Lo usan el campo del pie y la "x" de su fila. */
    async function guardarPropinaMonto(monto) {
        if (guardandoPropina) return;
        guardandoPropina = true;
        if ($propina) $propina.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_comanda', ID_COMANDA);
            fd.append('monto', String(monto));
            const r = await fetch(AJAX + '/guardarPropinaAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo guardar la propina.'); return; }
            swalToast('success', d.msg);
            await refrescarComanda();
        } catch (e) {
            swalError('Error de conexión.');
        } finally {
            guardandoPropina = false;
            if ($propina) $propina.disabled = false;
        }
    }

    async function guardarPropina() {
        if (!$propina || guardandoPropina) return;
        const monto = parseFloat($propina.value) || 0;
        if (monto < 0) { swalError('La propina no puede ser negativa.'); $propina.value = ''; return; }

        // Si no cambió respecto de lo guardado, no se molesta al servidor.
        if (round2(monto) === propinaLibre()) return;

        await guardarPropinaMonto(monto);
    }

    if ($propina) {
        $propina.addEventListener('blur', guardarPropina);
        $propina.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') { ev.preventDefault(); $propina.blur(); }
        });
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
                // Un ítem que no pasa por ninguna estación no tiene un estado que
                // contar: decir "Entregado" sobre una bebida embotellada no le
                // dice nada a nadie. Solo lleva etiqueta mientras esté pendiente
                // de confirmar, que es lo único accionable. La propina, ni eso.
                const esPropina = esLineaPropina(d);
                const sinEstacion = !d.id_estacion_impresion;
                const ocultarEstado = esPropina || (sinEstacion && d.estado_linea !== 'pendiente');
                const [label, color] = ocultarEstado ? [null, null] : (ESTADO_LABEL[d.estado_linea] || ['—', 'secondary']);
                const descuento = parseFloat(d.descuento || 0);
                // El importe de cada ítem se muestra CON impuestos: mismo criterio
                // con el que se ve el precio en el catálogo (tarjetas del grid) y
                // el que el cliente compara contra su comprobante. El pie sigue
                // desglosando Subtotal / IVA / Servicio / Total.
                const pctLinea = parseFloat(d.porcentaje_iva) || 0;
                const base = round2(parseFloat(d.precio_unitario || 0) * parseFloat(d.cantidad || 0) * (1 + pctLinea / 100));
                const descTag = descuento > 0 ? ' <span class="badge bg-danger bg-opacity-10 text-danger">-' + money(descuento) + '</span>' : '';
                const totalHtml = descuento > 0
                    ? '<span class="text-decoration-line-through text-muted small d-block">' + money(base) + '</span>' + money(totalLineaConIva(d))
                    : money(totalLineaConIva(d));
                const editable = d.estado_linea !== 'anulado' && !d.id_grupo_cobro && PUEDE_ACTUALIZAR;
                // La propina no admite descuento (no es un consumo), pero sí se
                // puede quitar desde su propia fila: es lo más directo cuando el
                // cliente se arrepiente. La quita el mismo endpoint que el campo
                // del pie (guardarPropina con 0), no el de anular un ítem: así
                // desaparece en vez de quedar como una línea anulada con opción
                // de restaurar, que para una propina no tiene sentido.
                const puedeEditar = editable && !esPropina;
                // Con PUEDE_CREAR y no PUEDE_ACTUALIZAR: quitar la propina va por
                // guardarPropinaAjax, que pide permiso de crear — el mismo con el
                // que se muestra su campo en el pie.
                // Se puede quitar aunque la cuenta ya esté pedida (mientras no se
                // haya cobrado): si el cliente cambia de idea sobre la propina, el
                // mesero la borra y pone otra, y esa cuenta se recalcula sola.
                const puedeQuitarPropina = esPropina && PUEDE_CREAR && d.estado_linea !== 'anulado' && lineaEditable(d);
                // Solo se entrega lo que cocina/barra marcó 'listo'. Un ítem sin
                // estación no pasa por preparación (nace entregado), así que no
                // tiene nada que entregar; si arrastra un estado viejo, tampoco
                // se le ofrece el botón.
                const puedeEntregar = PUEDE_ACTUALIZAR && d.estado_linea === 'listo' && !!d.id_estacion_impresion;
                return `
                <div class="cm-linea ${d.estado_linea === 'anulado' ? 'anulado' : ''}">
                    <div class="desc">
                        <div class="n">${esPropina ? '' : cantidad(d.cantidad) + ' x '}${escapeHtml(d.descripcion)}${descTag}</div>
                        ${d.observacion_item ? '<div class="p">' + escapeHtml(d.observacion_item) + '</div>' : ''}
                        ${d.estado_linea !== 'anulado' && label ? '<div class="estado"><span class="badge bg-' + color + '-subtle text-' + color + '-emphasis">' + label + '</span></div>' : ''}
                        ${puedeEntregar ? '<button type="button" class="btn btn-sm btn-success entregar mt-1" data-id="' + d.id + '"><i class="bi bi-check2-circle me-1"></i>Entregar</button>' : ''}
                        ${d.estado_linea === 'anulado' && PUEDE_ACTUALIZAR ? '<button type="button" class="btn btn-sm btn-outline-secondary restaurar mt-1" data-id="' + d.id + '"><i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar</button>' : ''}
                    </div>
                    ${puedeEditar ? '<button type="button" class="btn btn-outline-secondary btn-desc" data-id="' + d.id + '" title="Aplicar descuento"><i class="bi bi-percent"></i></button>' : ''}
                    <div class="total" title="IVA incluido">${totalHtml}</div>
                    ${puedeEditar ? '<span class="rm" data-id="' + d.id + '" title="Eliminar ítem"><i class="bi bi-x-lg"></i></span>' : ''}
                    ${puedeQuitarPropina ? '<span class="rm rm-propina" title="Quitar la propina"><i class="bi bi-x-lg"></i></span>' : ''}
                </div>`;
            }).join('');
        }
        const t = calcularTotales(vivas);
        $subtotal.textContent = money(t.subtotal);
        $impuestos.innerHTML = Object.entries(t.impuestos).map(([lbl, val]) =>
            `<div class="row"><div><span class="text-muted">${lbl}</span><span>${money(val)}</span></div></div>`).join('');
        renderServicio(t.servicio);
        // El campo del pie edita solo la propina que todavía no está en ninguna
        // cuenta: la que ya viajó en un grupo pertenece a esa cuenta y no se
        // toca (mismo criterio que ComandaRepository::getLineaPropina).
        renderPropina(propinaLibre());
        $total.textContent = money(t.total);
        renderGrupos();
        actualizarBadgePendientes();
        actualizarAvisoListos();
        actualizarAvisoAsistencia();
        const $btnVistaPrevia = document.getElementById('cm-btn-vista-previa');
        if ($btnVistaPrevia) $btnVistaPrevia.disabled = vivas.length === 0;
        actualizarBotonCobrar();
    }

    /**
     * El botón "Cobrar" del pie arma una cuenta nueva. Si el cliente ya pidió la
     * suya desde el QR, esa cuenta ya está armada y esperando: el mesero la
     * cobra con el botón del propio grupo, no armando otra. Se deshabilita para
     * que no se le pase por alto la que el cliente pidió.
     */
    function actualizarBotonCobrar() {
        const $btn = document.getElementById('cm-btn-cobrar');
        if (!$btn) return;
        const pedidaPorCliente = grupos.some(g => g.origen === 'qr' && g.estado === 'pendiente');
        $btn.disabled = pedidaPorCliente;
        $btn.title = pedidaPorCliente
            ? 'El cliente ya pidió su cuenta desde el QR: cóbrala con el botón de esa cuenta.'
            : '';
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
        // Cuentan todas las pendientes, tengan estación o no: el botón es el que
        // cierra el pedido. Las que no tienen estación no van a cocina — quedan
        // entregadas de una vez (ver enviarLineasACocina) — pero igual hay que
        // poder despacharlas, o se quedarían pendientes para siempre.
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
            const monto = calcularTotales(g.lineas || []).total; // con IVA: es lo que se le cobra a esa cuenta
            const doc = g.estado === 'cobrado' ? `<div class="doc">${escapeHtml(g.tipo_documento || '')} ${escapeHtml(g.numero_documento || '')}</div>` : '';
            const solicitud = (g.origen === 'qr' && g.estado === 'pendiente')
                ? `<div class="doc"><i class="bi bi-qrcode me-1"></i>Pedido desde el QR — ${escapeHtml(g.cliente_nombre || '')} (${g.tipo_documento_solicitado === 'FACTURA' ? 'Factura' : 'Recibo'})</div>`
                : '';
            // Cómo dijo el cliente que piensa pagar. Es una sugerencia: se
            // precarga al cobrar, pero el mesero elige la forma definitiva.
            const sugerencia = (g.estado === 'pendiente' && g.forma_pago_sugerida_nombre)
                ? `<div class="doc"><i class="bi bi-cash-coin me-1"></i>Sugiere pagar con <b>${escapeHtml(g.forma_pago_sugerida_nombre)}</b></div>`
                : '';
            const btns = g.estado === 'pendiente' && PUEDE_CREAR ? `
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-success py-0 px-2 cb-cobrar-grupo" data-id="${g.id}" data-monto="${monto}" data-id-cliente="${g.id_cliente || ''}" data-cliente-nombre="${escapeHtml(g.cliente_nombre || '')}" data-tipo-doc="${g.tipo_documento_solicitado || ''}" data-forma-sugerida="${g.id_forma_pago_sugerida || ''}" data-forma-sugerida-nombre="${escapeHtml(g.forma_pago_sugerida_nombre || '')}">Cobrar</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 cb-deshacer-grupo" data-id="${g.id}" title="Deshacer"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>` : (g.estado === 'cobrado' ? `
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 cb-imprimir-grupo" data-id-doc="${g.id_documento}" data-tipo-doc="${g.tipo_documento}" title="Imprimir tirilla"><i class="bi bi-receipt"></i></button>` : '');
            return `<div class="cm-grupo">
                <div>
                    <div class="et">${escapeHtml(g.etiqueta || ('Cuenta ' + g.numero_grupo))} <span class="badge bg-${color}-subtle text-${color}-emphasis">${label}</span></div>
                    <div class="doc">${money(monto)}</div>
                    ${doc}
                    ${solicitud}
                    ${sugerencia}
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
                // Otro usuario (o un cambio de configuración) pudo alterar el recargo.
                porcentajeServicio = parseFloat(d.data.servicio_efectivo || 0);
                aplicaServicio = porcentajeServicio > 0;
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
        // La propina se quita poniéndola en 0: mismo camino que el campo del pie,
        // así la línea desaparece en vez de quedar anulada.
        if (rm && rm.classList.contains('rm-propina')) {
            if ($propina) { $propina.value = ''; }
            await guardarPropinaMonto(0);
            return;
        }
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
    // Arranca en 'menu', igual que el radio marcado en el HTML: el mesero toma
    // pedidos de la carta, no del stock general.
    let filtroOrigen = 'menu';

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
            const tieneItems = detalles.length > 0;
            const { value: motivo, isConfirmed } = await Swal.fire({
                title: '¿Anular esta comanda?',
                html: tieneItems
                    ? 'La mesa quedará disponible y se perderán los ítems agregados. <strong>Esta comanda ya tiene ítems registrados — indica el motivo:</strong>'
                    : 'La mesa quedará disponible.',
                icon: 'warning', showCancelButton: true,
                input: tieneItems ? 'textarea' : undefined,
                inputPlaceholder: 'Ej. el cliente se fue sin pedir, pedido duplicado, etc.',
                confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                preConfirm: (val) => {
                    if (tieneItems && !String(val || '').trim()) {
                        Swal.showValidationMessage('Escribe el motivo de la anulación.');
                        return false;
                    }
                    return val;
                },
            });
            if (!isConfirmed) return;
            const fd = new FormData();
            fd.append('id', ID_COMANDA);
            fd.append('motivo', motivo || '');
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
    const modalClienteNuevo = document.getElementById('modalClienteNuevo') ? new bootstrap.Modal('#modalClienteNuevo') : null;
    let tiposIdCargados = false;
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
                    <input type="checkbox" class="form-check-input cb-check" data-id="${d.id}" ${enPreparacion ? 'disabled' : 'checked'}>
                    <span class="desc">${esLineaPropina(d) ? '' : cantidad(d.cantidad) + ' x '}${escapeHtml(d.descripcion)}${enPreparacion ? ' <span class="badge bg-warning-subtle text-warning-emphasis">en preparación</span>' : ''}</span>
                    <span class="total">${money(totalLineaConIva(d))}</span>
                </label>`;
            }).join('');
        }
        recalcularSeleccion();
    }

    /** Líneas realmente marcadas en el modal de cobro (para totalizarlas con el mismo criterio que el pie de la comanda). */
    function lineasSeleccionadasCobro() {
        const ids = new Set(Array.from($cbLista.querySelectorAll('.cb-check:checked')).map(c => String(c.dataset.id)));
        return detalles.filter(d => ids.has(String(d.id)));
    }

    function recalcularSeleccion() {
        $cbTotalSel.textContent = money(calcularTotales(lineasSeleccionadasCobro()).total);
    }

    function actualizarModoSplit() {
        const esPartes = document.getElementById('cb-modo-partes').checked;
        document.getElementById('cb-num-partes-wrap').classList.toggle('d-none', !esPartes);
        document.getElementById('cb-btn-armar').innerHTML = esPartes
            ? '<i class="bi bi-collection me-1"></i>Dividir en partes'
            : '<i class="bi bi-check2-square me-1"></i>Cobrar seleccionados';
    }
    document.querySelectorAll('input[name="cb-modo-split"]').forEach(r => r.addEventListener('change', actualizarModoSplit));

    const $btnCobrar = document.getElementById('cm-btn-cobrar');
    if ($btnCobrar && mdCobro) {
        $btnCobrar.addEventListener('click', () => {
            document.getElementById('cb-modo-items').checked = true;
            document.getElementById('cb-num-partes').value = '2';
            actualizarModoSplit();
            renderListaCobro();
            mdCobro.show();
        });
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
        // El tipo de documento ya no se elige: siempre Factura, también cuando el
        // pedido vino del QR pidiendo recibo (preset.tipoDocumento se ignora).
        document.getElementById('pg-tipo-doc').value = 'FACTURA';

        const $sel = document.getElementById('pg-forma-pago');
        const formas = await cargarFormasPago();
        $sel.innerHTML = formas.map(f => `<option value="${f.id}" data-tipo="${escapeHtml(f.tipo || '')}" data-cod="${f.codigo_sri}">${escapeHtml(f.nombre)}</option>`).join('');

        // Forma de pago favorita del usuario (la estrella junto al rótulo): se
        // aplica recién ahora, porque el select se llena por AJAX y antes no
        // existía la opción. El 'change' sincroniza la estrella y los campos de
        // banco, que dependen del tipo de la forma elegida.
        // Si el cliente sugirió una forma desde el QR, esa gana sobre el
        // favorito del cajero: es específica de esta cuenta. Igual queda
        // editable — quien decide el cobro es el mesero.
        const favFormaPago = (typeof APP_FAVORITOS !== 'undefined') ? APP_FAVORITOS['id_forma_pago'] : null;
        const formaPreseleccionada = preset.formaSugerida || favFormaPago;
        if (formaPreseleccionada && Array.from($sel.options).some(o => o.value == formaPreseleccionada)) {
            $sel.value = formaPreseleccionada;
        }

        // Se dice explícitamente que la forma viene del cliente: si no, el mesero
        // no distinguiría una sugerencia suya de su propio favorito.
        const $notaSugerida = document.getElementById('pg-forma-sugerida');
        if ($notaSugerida) {
            const hayNombre = !!preset.formaSugeridaNombre;
            $notaSugerida.classList.toggle('d-none', !hayNombre);
            if (hayNombre) document.getElementById('pg-forma-sugerida-nombre').textContent = preset.formaSugeridaNombre;
        }
        $sel.dispatchEvent(new Event('change'));
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
        const monto = calcularTotales(lineasSeleccionadasCobro()).total;
        const esPartes = document.getElementById('cb-modo-partes').checked;

        if (esPartes) {
            const numPartes = parseInt(document.getElementById('cb-num-partes').value || '0', 10);
            if (numPartes < 2) { swalError('Divide entre al menos 2 partes.'); return; }
            const fd = new FormData();
            fd.append('id_comanda', ID_COMANDA);
            fd.append('ids_lineas', JSON.stringify(ids));
            fd.append('num_partes', numPartes);
            try {
                const r = await fetch(AJAX + '/crearGruposPartesIgualesAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo dividir la cuenta.'); return; }
                mdCobro && mdCobro.hide();
                await refrescarComanda();
                swalToast('success', d.msg || 'Cuenta dividida.');
            } catch (e) { swalError('Error de conexión.'); }
            return;
        }

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
                formaSugerida: btnCobrar.dataset.formaSugerida,
                formaSugeridaNombre: btnCobrar.dataset.formaSugeridaNombre,
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

    // ─── Nuevo cliente desde el cobro (mismo patrón que caja_sesion/venta.php) ──
    function fijarClienteEnPago(idCliente, nombre) {
        document.getElementById('pg-id-cliente').value = idCliente;
        document.getElementById('pg-cliente-buscar').value = nombre;
        document.getElementById('pg-cliente-resultados').innerHTML = '';
        revisarLimiteConsumidorFinal();
    }

    document.getElementById('pg-btn-nuevo-cliente').addEventListener('click', async () => {
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
        modalClienteNuevo && modalClienteNuevo.show();
    });

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
        mostrarBadgeSriNc('Consultando…', 'bg-secondary');
        try {
            const fd = new FormData();
            fd.append('identificacion', identificacion);
            const res = await fetch(BASE + '/modulos/clientes/consultarSri', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) { mostrarBadgeSriNc('No encontrado', 'bg-warning text-dark'); return; }

            if (json.source === 'cliente') {
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
                    fijarClienteEnPago(json.data.id, json.data.nombre);
                    modalClienteNuevo && modalClienteNuevo.hide();
                } else {
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
            if (match) fijarClienteEnPago(match.id, match.nombre);
            modalClienteNuevo && modalClienteNuevo.hide();
            swalToast('success', 'Cliente creado y seleccionado.');
        } catch (e) {
            swalError('Error de conexión al crear el cliente.');
        } finally {
            $btn.disabled = false;
        }
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

        const { subtotal, impuestos: impMap, servicio, total } = calcularTotales(vivas);

        const lineas = vivas.map(d => {
            const pct = parseFloat(d.porcentaje_iva || 0);
            const descExtra = parseFloat(d.descuento || 0) > 0 ? ` — desc. $${fmt(d.descuento)}` : '';
            // La propina va en UNA sola fila, con el importe a la derecha del
            // nombre: no es un consumo con cantidad y precio unitario, así que no
            // tiene segunda línea de detalle — sin esto el valor quedaba colgando
            // un renglón más abajo que su texto.
            if (esLineaPropina(d)) {
                return `<tr><td>${escapeHtml(d.descripcion)}</td>
                    <td class="num bold">$${fmt(d.subtotal)}</td></tr>`;
            }
            return `<tr><td colspan="2">${escapeHtml(d.descripcion + descExtra)}</td></tr>
                <tr><td class="sub">${cantidad(d.cantidad)} x $${precio(d.precio_unitario)} (IVA ${pct}%)</td>
                <td class="num bold">$${fmt(d.subtotal)}</td></tr>`;
        }).join('<tr><td colspan="2"><hr></td></tr>');

        const ivaLineas = Object.entries(impMap).map(([lbl, val]) =>
            `<tr><td>${lbl}</td><td class="num">$${fmt(val)}</td></tr>`).join('');

        const logoHtml = EMPRESA_INFO.logo
            ? `<img src="${BASE}/${EMPRESA_INFO.logo}" style="margin-bottom:4px;">`
            : '';

        const html = `<!DOCTYPE html><html lang="es"><head>
            <meta charset="UTF-8">
            <title>Cuenta - Mesa <?= htmlspecialchars($comanda['mesa_nombre'] ?? '') ?></title>
            <?php require MVC_APP . "/views/partials/tirilla_estilos.php"; ?>
        </head><body>
            <div class="center">
                ${logoHtml}
                <h2>${escapeHtml(EMPRESA_INFO.nombre_comercial || EMPRESA_INFO.nombre)}</h2>
            </div>
            <hr class="sep">
            <div class="center bold" style="font-size:12px;">CUENTA — MESA <?= htmlspecialchars($comanda['mesa_nombre'] ?? '') ?></div>
            <div class="center">Fecha: ${escapeHtml(new Date().toLocaleDateString('es-EC'))}</div>
            <hr class="sep">
            <table class="t-detalle"><colgroup><col><col style="width:19mm"></colgroup><tbody>${lineas}</tbody></table>
            <hr class="sep">
            <table class="t-totales"><colgroup><col><col style="width:22mm"></colgroup>
                <tr><td>Subtotal</td><td class="num">$${fmt(subtotal)}</td></tr>
                ${ivaLineas}
                ${servicio > 0 ? `<tr><td>Servicio ${porcentajeServicio}%</td><td class="num">$${fmt(servicio)}</td></tr>` : ''}
                <tr><td>TOTAL A PAGAR</td><td class="num">$${fmt(total)}</td></tr>
            </table>
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
                ? `<img src="${BASE}/${EMPRESA_INFO.logo}" style="margin-bottom:4px;">`
                : '';

            const lineas = detalles.map(d => {
                const cant = parseFloat(d.cantidad || 1);
                const pu = parseFloat(d.precio_unitario || 0);
                const desc = parseFloat(d.descuento || 0);
                const tot = parseFloat(d.precio_total_sin_impuesto || 0);
                const ivaPct = (d.impuestos && d.impuestos[0]) ? parseFloat(d.impuestos[0].tarifa || 0).toFixed(0) : '0';
                // La propina va en UNA sola fila, con el importe junto al nombre:
                // sin cantidad ni precio unitario no hay segunda línea que
                // ocupar, y el valor quedaría colgando un renglón más abajo.
                if (esLineaPropina(d)) {
                    return `<tr><td>${escapeHtml(d.descripcion)}</td>
                        <td class="num bold">$${fmt(tot)}</td></tr>`;
                }
                return `<tr><td colspan="2">${escapeHtml(d.descripcion)}</td></tr>
                    <tr><td class="sub">${cantidad(cant)} x $${precio(pu)}${desc > 0 ? ` desc.$${fmt(desc)}` : ''} (IVA ${ivaPct}%)</td>
                    <td class="num bold">$${fmt(tot)}</td></tr>`;
            }).join('<tr><td colspan="2"><hr></td></tr>');

            const ivaLineas = Object.entries(impMap).map(([lbl, val]) =>
                `<tr><td>${lbl}</td><td class="num">$${fmt(val)}</td></tr>`).join('');

            const tituloDoc = tipoDocumento === 'FACTURA' ? 'FACTURA DE VENTA' : 'RECIBO DE VENTA';

            const html = `<!DOCTYPE html><html lang="es"><head>
                <meta charset="UTF-8">
                <title>Ticket - ${escapeHtml(num)}</title>
<?php require MVC_APP . "/views/partials/tirilla_estilos.php"; ?>
            </head><body>
                <div class="center">
                    ${logoHtml}
                    <h2>${escapeHtml(EMPRESA_INFO.nombre_comercial || EMPRESA_INFO.nombre)}</h2>
                    <h3>RUC: ${escapeHtml(EMPRESA_INFO.ruc)}</h3>
                    ${EMPRESA_INFO.direccion ? `<h3>${escapeHtml(EMPRESA_INFO.direccion)}</h3>` : ''}
                    ${EMPRESA_INFO.telefono ? `<h3>Tel: ${escapeHtml(EMPRESA_INFO.telefono)}</h3>` : ''}
                </div>
                <hr class="sep">
                <div class="center bold" style="font-size:12px;">${tituloDoc}</div>
                <div class="center">No. ${escapeHtml(num)}</div>
                <div class="center">Fecha: ${escapeHtml(fecha)}</div>
                <hr class="sep">
                <table class="t-datos"><colgroup><col style="width:16mm"><col></colgroup>
                    <tr><td class="bold">Cliente:</td><td>${escapeHtml(cab.cliente_nombre)}</td></tr>
                    <tr><td class="bold">RUC/CI:</td><td>${escapeHtml(cab.cliente_ruc)}</td></tr>
                    ${cab.cliente_direccion ? `<tr><td class="bold">Dir:</td><td>${escapeHtml(cab.cliente_direccion)}</td></tr>` : ''}
                </table>
                <hr class="sep">
                <table class="t-detalle"><colgroup><col><col style="width:19mm"></colgroup><tbody>${lineas}</tbody></table>
                <hr class="sep">
                <table class="t-totales"><colgroup><col><col style="width:22mm"></colgroup>
                    <tr><td>Subtotal sin imp.</td><td class="num">$${fmt(subtotal)}</td></tr>
                    ${totalDescuento > 0 ? `<tr><td>Descuento</td><td class="num">-$${fmt(totalDescuento)}</td></tr>` : ''}
                    ${ivaLineas}
                    ${totalIce > 0 ? `<tr><td>ICE</td><td class="num">$${fmt(totalIce)}</td></tr>` : ''}
                    ${parseFloat(cab.propina || 0) > 0 ? `<tr><td>Propina</td><td class="num">$${fmt(cab.propina)}</td></tr>` : ''}
                    <tr><td>TOTAL</td><td class="num">$${fmt(total)}</td></tr>
                </table>
                ${cab.observaciones ? `<hr class="sep"><div style="font-size:10px;">${escapeHtml(cab.observaciones)}</div>` : ''}
                <hr class="sep">
                <div class="center" style="font-size:10px;">¡Gracias por su compra!</div>
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
        const tipoDoc = document.getElementById('pg-tipo-doc').value;
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
