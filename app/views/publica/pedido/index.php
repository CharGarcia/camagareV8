<?php
/**
 * Portal público QR — cliente pidiendo desde su celular en la mesa. Página
 * standalone (sin login, sin layout del sistema), mobile-first. El "carrito"
 * vive en el servidor (comanda_detalle de la mesa): si dos personas de la
 * misma mesa abren el link cada una desde su teléfono, ambas ven y agregan al
 * mismo pedido (se sincroniza por polling, ver estadoAjax).
 *
 * @var string $titulo
 * @var string $token
 * @var array  $mesa
 * @var array  $comanda
 * @var array  $documentos ['factura' => bool, 'recibo' => bool] — qué documento(s) permite pedir esta mesa
 */
$base = rtrim(BASE_URL ?? '', '/');
$ajax = $base . '/pedido/' . $token;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?= htmlspecialchars($titulo) ?></title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { background: #f4f6f9; overscroll-behavior-y: contain; }
        .pq-header { position: sticky; top: 0; z-index: 10; background: #0d6efd; color: #fff; padding: 12px 16px; }
        .pq-header .mesa { font-weight: 700; font-size: 1.05rem; }
        .pq-header .sub { font-size: .76rem; color: rgba(255,255,255,.8); }

        .pq-menu-wrap { padding: 14px 0 6px; }
        .pq-menu-title { padding: 0 16px 8px; font-weight: 700; font-size: .92rem; color: #2b2f36; }
        .pq-menu-scroll { display: flex; gap: 10px; overflow-x: auto; padding: 0 16px 8px; scroll-snap-type: x proximity; -webkit-overflow-scrolling: touch; }
        .pq-card { scroll-snap-align: start; flex: 0 0 auto; width: 150px; background: #fff; border: 1px solid #e5e8ec; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(16,24,40,.06); }
        .pq-card .foto { width: 100%; height: 100px; object-fit: cover; background: #eceef1; display: block; }
        .pq-card .foto-vacia { width: 100%; height: 100px; background: #eceef1; display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 1.6rem; }
        .pq-card .body { padding: 8px 10px 10px; }
        .pq-card .nombre { font-size: .8rem; font-weight: 600; line-height: 1.2; min-height: 2.1em; color: #1c1f24; }
        .pq-card .precio { font-size: .82rem; font-weight: 700; color: #0d6efd; margin-top: 4px; }
        .pq-card .descripcion { font-size: .68rem; color: #8a94a6; line-height: 1.25; margin-top: 2px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .pq-card .btn-agregar { width: 100%; margin-top: 6px; font-size: .74rem; padding: 4px; }

        .pq-pedido { margin: 10px 16px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16,24,40,.06); }
        .pq-pedido-header { padding: 12px 14px; font-weight: 700; font-size: .9rem; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .pq-linea { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #f5f5f5; font-size: .84rem; }
        .pq-linea:last-child { border-bottom: none; }
        .pq-linea .desc { flex: 1 1 auto; min-width: 0; }
        .pq-linea .nombre { font-weight: 600; }
        .pq-linea .estado { font-size: .66rem; margin-top: 2px; }
        .pq-linea .total { font-weight: 700; white-space: nowrap; }
        .pq-linea .quitar { color: #dc3545; cursor: pointer; padding: 2px; }
        .pq-empty { color: #8a94a6; text-align: center; padding: 24px; font-size: .84rem; }

        .pq-footer { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e5e8ec; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); box-shadow: 0 -2px 8px rgba(16,24,40,.06); }
        .pq-footer .total-row { display: flex; justify-content: space-between; font-size: 1.05rem; font-weight: 700; margin-bottom: 8px; }

        .pq-header .btn-mesero {
            border: none; background: #ffc107; color: #3d2e00;
            width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,.3);
            animation: pq-bell-pulse 1.8s ease-in-out infinite, pq-bell-shake 3.2s ease-in-out infinite;
        }
        .pq-header .btn-mesero:disabled {
            animation: none; background: rgba(255,255,255,.2); color: #fff; box-shadow: none; opacity: .8;
        }
        @keyframes pq-bell-pulse {
            0%, 100% { box-shadow: 0 2px 10px rgba(0,0,0,.3), 0 0 0 0 rgba(255,193,7,.7); }
            50% { box-shadow: 0 2px 10px rgba(0,0,0,.3), 0 0 0 10px rgba(255,193,7,0); }
        }
        @keyframes pq-bell-shake {
            0%, 88%, 100% { transform: rotate(0deg); }
            90% { transform: rotate(-15deg); }
            92% { transform: rotate(12deg); }
            94% { transform: rotate(-10deg); }
            96% { transform: rotate(6deg); }
            98% { transform: rotate(0deg); }
        }
        .pq-aviso-asistencia { margin: 10px 16px 0; padding: 8px 12px; background: #fff3cd; color: #664d03; border: 1px solid #ffe69c; border-radius: 10px; font-size: .78rem; display: flex; align-items: center; gap: 8px; }
        .pq-aviso-asistencia span { flex: 1 1 auto; }
        .pq-aviso-asistencia .cancelar { border: none; background: transparent; color: #664d03; text-decoration: underline; font-size: .76rem; padding: 0; white-space: nowrap; }

        .pq-cuenta-item { display: flex; align-items: center; gap: 10px; padding: 8px 4px; border-bottom: 1px solid #f0f0f0; font-size: .85rem; }
        .pq-cuenta-item:last-child { border-bottom: none; }
        .pq-cuenta-item .nombre { flex: 1 1 auto; }
        .pq-cuenta-item .total { font-weight: 600; white-space: nowrap; }
    </style>
</head>
<body>

<div class="pq-header d-flex justify-content-between align-items-start">
    <div>
        <div class="mesa"><i class="bi bi-shop me-1"></i>Mesa <?= htmlspecialchars($mesa['nombre'] ?? '') ?></div>
        <div class="sub"><?= htmlspecialchars($comanda['numero_comanda'] ?? '') ?> &middot; Escanea, elige y confirma tu pedido</div>
    </div>
    <button type="button" class="btn btn-sm btn-mesero" id="pq-btn-mesero" title="Llamar al mesero">
        <i class="bi bi-bell"></i>
    </button>
</div>

<div class="pq-aviso-asistencia d-none" id="pq-aviso-asistencia">
    <i class="bi bi-bell-fill"></i>
    <span>Ya avisamos a un mesero, en un momento te atienden.</span>
    <button type="button" class="cancelar" id="pq-btn-cancelar-asistencia">¿Fue un error? Cancelar</button>
</div>

<div class="pq-aviso-asistencia d-none" id="pq-aviso-cuenta-pedida">
    <i class="bi bi-receipt-cutoff"></i>
    <span>Ya pediste tu cuenta. Si quieres pedir algo más, avísale al mesero.</span>
</div>

<div class="pq-menu-wrap">
    <div class="pq-menu-title">Menú</div>
    <div class="pq-menu-scroll" id="pq-menu"><div class="pq-empty">Cargando menú...</div></div>
</div>

<div class="pq-pedido">
    <div class="pq-pedido-header">
        <span><i class="bi bi-receipt me-1"></i>Tu pedido</span>
        <span id="pq-badge-listos" class="badge bg-success d-none"></span>
    </div>
    <div id="pq-lineas"></div>
</div>

<div class="pq-pedido d-none" id="pq-cuenta-card">
    <div class="pq-pedido-header">
        <span><i class="bi bi-receipt-cutoff me-1"></i>¿Ya terminaste?</span>
    </div>
    <div style="padding: 12px 14px;">
        <button type="button" class="btn btn-outline-primary btn-sm w-100" id="pq-btn-pedir-cuenta">
            <i class="bi bi-cash-coin me-1"></i>Pedir mi cuenta
        </button>
    </div>
</div>

<div style="height: 90px;"></div>

<div class="modal fade" id="mdCuenta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title mb-0"><i class="bi bi-receipt-cutoff me-1"></i>Pedir mi cuenta</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 small text-muted">Elige lo que quieres pagar:</div>
        <div id="pq-cuenta-items"></div>
        <?php if (!empty($idProductoPropina)): ?>
        <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center" id="pq-propina-wrap">
            <label class="small mb-0" for="pq-propina">
                ¿Dejas propina?
                <span class="d-block text-muted" style="font-size:.7rem;">Opcional, para el equipo</span>
            </label>
            <div class="input-group input-group-sm" style="width:96px;">
                <span class="input-group-text px-1">$</span>
                <input type="number" class="form-control text-end px-1" id="pq-propina" step="0.01" min="0" placeholder="0.00" inputmode="decimal">
            </div>
        </div>
        <?php endif; ?>
        <div class="border-top pt-2 mt-2" style="font-size:.82rem;">
            <div class="d-flex justify-content-between" style="padding:2px 0;">
                <span class="text-muted">Subtotal</span><span id="pq-cuenta-subtotal">$0.00</span>
            </div>
            <div id="pq-cuenta-impuestos"></div>
            <div class="d-flex justify-content-between d-none" id="pq-cuenta-fila-servicio" style="padding:2px 0;">
                <span class="text-muted" id="pq-cuenta-servicio-label">Servicio</span><span id="pq-cuenta-servicio">$0.00</span>
            </div>
            <div class="d-flex justify-content-between d-none" id="pq-cuenta-fila-propina" style="padding:2px 0;">
                <span class="text-muted">Propina</span><span id="pq-cuenta-propina">$0.00</span>
            </div>
        </div>
        <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-2">
            <span>Total a pagar</span><span id="pq-cuenta-total">$0.00</span>
        </div>
        <hr>
        <?php if (!empty($formasPago)): ?>
        <div class="mb-2">
            <label class="form-label small mb-1 d-block" for="pq-forma-pago">Forma de pago sugerida</label>
            <select class="form-select form-select-sm" id="pq-forma-pago">
                <option value="">Lo decido con el mesero</option>
                <?php foreach ($formasPago as $f): ?>
                    <option value="<?= (int) $f['id'] ?>"><?= htmlspecialchars($f['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text mt-0" style="font-size:.7rem;">Es solo para avisarle al mesero; él confirma el cobro.</div>
        </div>
        <?php endif; ?>
        <div class="mb-2" id="pq-doc-wrap">
            <label class="form-label small mb-1 d-block">Quiero que me den</label>
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="pq-tipo-doc" id="pq-doc-factura" value="FACTURA">
                <label class="btn btn-outline-secondary btn-sm" for="pq-doc-factura">Factura</label>
                <input type="radio" class="btn-check" name="pq-tipo-doc" id="pq-doc-recibo" value="RECIBO">
                <label class="btn btn-outline-secondary btn-sm" for="pq-doc-recibo">Recibo</label>
            </div>
        </div>
        <div class="mb-2">
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="pq-modo-cliente" id="pq-modo-datos" value="datos" checked>
                <label class="btn btn-outline-secondary btn-sm" for="pq-modo-datos">Con mis datos</label>
                <input type="radio" class="btn-check" name="pq-modo-cliente" id="pq-modo-cf" value="cf">
                <label class="btn btn-outline-secondary btn-sm" for="pq-modo-cf">Consumidor Final</label>
            </div>
        </div>
        <div id="pq-cf-nota" class="alert alert-secondary py-2 px-3 small mb-2 d-none">
            <i class="bi bi-person me-1"></i>Se emitirá a nombre de <strong>Consumidor Final</strong>.
        </div>
        <div id="pq-datos-cliente-wrap">
            <div class="row g-2 mb-2">
                <div class="col-5">
                    <label class="form-label small mb-1">Tipo ID</label>
                    <select class="form-select form-select-sm" id="pq-cli-tipo-id">
                        <option value="cedula">Cédula</option>
                        <option value="ruc">RUC</option>
                        <option value="pasaporte">Pasaporte</option>
                    </select>
                </div>
                <div class="col-7">
                    <label class="form-label small mb-1">Identificación</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm" id="pq-cli-identificacion" placeholder="0999999999">
                        <span class="input-group-text" id="pq-cli-id-spinner" style="display:none;"><span class="spinner-border spinner-border-sm"></span></span>
                    </div>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1">Nombre completo</label>
                <input type="text" class="form-control form-control-sm" id="pq-cli-nombre" placeholder="Nombre y apellido">
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1">Correo electrónico</label>
                <input type="email" class="form-control form-control-sm" id="pq-cli-correo" placeholder="correo@ejemplo.com">
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label small mb-1">Teléfono (opcional)</label>
                    <input type="text" class="form-control form-control-sm" id="pq-cli-telefono">
                </div>
                <div class="col-6">
                    <label class="form-label small mb-1">Dirección (opcional)</label>
                    <input type="text" class="form-control form-control-sm" id="pq-cli-direccion">
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="pq-btn-confirmar-cuenta">
            <i class="bi bi-send-check me-1"></i>Enviar solicitud
        </button>
      </div>
    </div>
  </div>
</div>

<div class="pq-footer">
    <div class="total-row d-none" id="pq-fila-servicio" style="font-size:.85rem;font-weight:500;">
        <span class="text-muted" id="pq-servicio-label">Servicio</span><span id="pq-servicio">$0.00</span>
    </div>
    <div class="total-row d-none" id="pq-fila-propina-pie" style="font-size:.85rem;font-weight:500;">
        <span class="text-muted">Propina</span><span id="pq-propina-pie">$0.00</span>
    </div>
    <div class="total-row"><span>Total</span><span id="pq-total">$0.00</span></div>
    <button type="button" class="btn btn-success w-100" id="pq-btn-confirmar" disabled>
        <i class="bi bi-send me-1"></i>Confirmar pedido <span id="pq-badge-confirmar" class="badge bg-light text-success ms-1 d-none"></span>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const BASE = "<?= $base ?>";
    const AJAX = "<?= $ajax ?>";
    let detalles = <?= json_encode($comanda['detalles'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    let grupos = <?= json_encode($comanda['grupos'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    let solicitaAsistencia = <?= !empty($comanda['solicita_asistencia']) ? 'true' : 'false' ?>;

    /**
     * ¿Ya pidió la cuenta? Con una cuenta viva (esperando al mesero o ya
     * cobrada) el cliente deja de poder agregar por su cuenta: lo que quiera
     * pedir después pasa por el mesero. Un grupo anulado no cuenta — esa cuenta
     * se deshizo y la mesa volvió a quedar abierta.
     */
    function cuentaYaPedida() {
        return grupos.some(g => g.estado === 'pendiente' || g.estado === 'cobrado');
    }
    <?php $svcOn = in_array((string) ($comanda['aplica_servicio'] ?? ''), ['1', 't', 'true'], true) || ($comanda['aplica_servicio'] ?? false) === true; ?>
    const APLICA_SERVICIO = <?= $svcOn ? 'true' : 'false' ?>;
    const PORCENTAJE_SERVICIO = <?= (float) ($comanda['porcentaje_servicio'] ?? 0) ?>;
    const DOC_PERMITIDOS = { factura: <?= !empty($documentos['factura']) ? 'true' : 'false' ?>, recibo: <?= !empty($documentos['recibo']) ? 'true' : 'false' ?> };
    let menu = [];
    const mdCuenta = new bootstrap.Modal(document.getElementById('mdCuenta'));
    // Al cerrar el modal, el pie vuelve a mostrar la propina realmente guardada:
    // lo que el cliente hubiera escrito sin confirmar no cuenta.
    document.getElementById('mdCuenta').addEventListener('hidden.bs.modal', () => renderLineas());

    function money(v) { return '$' + (parseFloat(v || 0)).toFixed(2); }
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Ups', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function swalToast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 1800, timerProgressBar: true });
    }

    const ESTADO_LABEL = {
        pendiente: ['Sin enviar', 'secondary'], enviado: ['En preparación', 'info'],
        preparando: ['Preparando', 'warning'], listo: ['Listo para servir', 'success'], entregado: ['Entregado', 'secondary'],
    };

    const round2 = (n) => Math.round((parseFloat(n) || 0) * 100) / 100;

    // Cantidades con los decimales que configuró el establecimiento: Postgres
    // devuelve numeric como "1.000000" y sin esto el cliente vería eso.
    // No hace falta el equivalente para el precio: en este portal todo importe
    // que ve el cliente es dinero (money, 2 decimales) — el precio unitario con
    // decimales extendidos solo aparece en las tirillas del salón.
    const DEC_CANT = <?= (int) ($decimales['cantidad'] ?? 2) ?>;
    function cantidad(v) { return (parseFloat(v) || 0).toFixed(DEC_CANT); }

    // La propina voluntaria viaja como una línea más de la comanda, pero no
    // genera recargo por servicio. Se reconoce por su producto, el mismo que
    // usan el salón y la emisión del documento.
    const ID_PRODUCTO_PROPINA = <?= (int) ($idProductoPropina ?? 0) ?>;
    const esLineaPropina = (d) => ID_PRODUCTO_PROPINA > 0 && parseInt(d.id_producto, 10) === ID_PRODUCTO_PROPINA;

    // Producto marcado "no aplica recargo de servicio" (envases, empaques, etc.):
    // igual que la propina voluntaria, no genera recargo, aunque se cobre como
    // una línea normal. Mismo criterio que el salón y PosVentaService::cobrar().
    const excluyeRecargo = (d) => d.excluir_recargo_servicio === true || d.excluir_recargo_servicio === 'true'
        || d.excluir_recargo_servicio == 1 || d.excluir_recargo_servicio === 't';

    // El subtotal de la línea (comanda_detalle.subtotal) es sin impuestos. El
    // IVA se redondea LÍNEA POR LÍNEA, igual que en el salón y que
    // PosVentaService::cobrar() al emitir: agrupar y redondear al final daría a
    // veces un centavo distinto del que termina en la factura, y el cliente
    // compara ese número con lo que le cobraron.
    function lineaConIva(d) {
        const base = round2(d.subtotal);
        return round2(base + round2(base * (parseFloat(d.porcentaje_iva) || 0) / 100));
    }

    // Recargo por servicio ("el 10%"): sobre la base sin impuestos DEL CONSUMO,
    // sin la propina voluntaria. Se le muestra al cliente aparte para que sepa
    // qué está pagando antes de confirmar.
    function servicioDe(lineas) {
        if (!APLICA_SERVICIO || PORCENTAJE_SERVICIO <= 0) return 0;
        const base = lineas.reduce((a, d) => (esLineaPropina(d) || excluyeRecargo(d)) ? a : a + (parseFloat(d.subtotal) || 0), 0);
        return round2(round2(base) * PORCENTAJE_SERVICIO / 100);
    }

    function renderMenu() {
        const $menu = document.getElementById('pq-menu');
        if (!menu.length) { $menu.innerHTML = '<div class="pq-empty">El menú todavía no tiene ítems disponibles.</div>'; return; }
        // Con la cuenta ya pedida el menú queda a la vista pero sin poder
        // agregar: el aviso de arriba explica por qué (el servidor lo rechaza
        // igual, esto solo evita el intento).
        const bloqueado = cuentaYaPedida();
        $menu.innerHTML = menu.map(m => {
            const foto = m.imagen
                ? `<img class="foto" src="${BASE}/${escapeHtml(m.imagen)}" alt="" loading="lazy">`
                : '<div class="foto-vacia"><i class="bi bi-cup-hot"></i></div>';
            // Precio con impuestos incluidos (lo que el cliente realmente paga por unidad).
            const precioConIva = parseFloat(m.precio || 0) * (1 + parseFloat(m.porcentaje_iva || 0) / 100);
            return `<div class="pq-card">
                ${foto}
                <div class="body">
                    <div class="nombre">${escapeHtml(m.nombre)}</div>
                    <div class="precio">${money(precioConIva)}</div>
                    ${m.descripcion ? '<div class="descripcion">' + escapeHtml(m.descripcion) + '</div>' : ''}
                    <button type="button" class="btn btn-sm btn-primary btn-agregar" data-id="${m.id}" data-nombre="${escapeHtml(m.nombre)}" ${bloqueado ? 'disabled' : ''}>
                        <i class="bi bi-plus-lg"></i> Agregar
                    </button>
                </div>
            </div>`;
        }).join('');
    }

    function renderLineas() {
        const vivas = detalles.filter(d => d.estado_linea !== 'anulado');
        const $lineas = document.getElementById('pq-lineas');
        if (!vivas.length) {
            $lineas.innerHTML = '<div class="pq-empty">Todavía no has agregado nada. Elige algo del menú arriba 👆</div>';
        } else {
            $lineas.innerHTML = vivas.map(d => {
                // Sin estación de preparación no hay estado que mostrarle al
                // cliente: "Entregado" sobre una bebida embotellada no aporta
                // nada. Solo se etiqueta mientras esté pendiente de confirmar.
                // La propina, ni eso.
                const esPropina = esLineaPropina(d);
                const sinEstacion = !d.id_estacion_impresion;
                const ocultarEstado = esPropina || (sinEstacion && d.estado_linea !== 'pendiente');
                const [label, color] = ocultarEstado ? [null, null] : (ESTADO_LABEL[d.estado_linea] || ['—', 'secondary']);
                // La propina se puede quitar siempre (mientras no esté ya en una
                // cuenta cobrada): la dejó el cliente y puede arrepentirse. Los
                // demás ítems, solo antes de mandarlos a preparación.
                const puedeQuitar = esPropina ? !d.id_grupo_cobro : d.estado_linea === 'pendiente';
                return `<div class="pq-linea">
                    <div class="desc">
                        <div class="nombre">${esPropina ? '' : cantidad(d.cantidad) + ' x '}${escapeHtml(d.descripcion)}</div>
                        ${label ? '<div class="estado"><span class="badge bg-' + color + '-subtle text-' + color + '-emphasis">' + label + '</span></div>' : ''}
                    </div>
                    <div class="total">${money(lineaConIva(d))}</div>
                    ${puedeQuitar ? '<span class="quitar" data-id="' + d.id + '"><i class="bi bi-x-lg"></i></span>' : ''}
                </div>`;
            }).join('');
        }

        const servicio = servicioDe(vivas);
        const $filaSrv = document.getElementById('pq-fila-servicio');
        $filaSrv.classList.toggle('d-none', servicio <= 0);
        if (servicio > 0) {
            document.getElementById('pq-servicio-label').textContent = `Servicio ${PORCENTAJE_SERVICIO}%`;
            document.getElementById('pq-servicio').textContent = money(servicio);
        }

        // La propina va en su propia fila del pie. Con el modal abierto se
        // muestra lo que el cliente está escribiendo (todavía sin guardar), para
        // que vea el efecto en su cuenta mientras decide; con el modal cerrado,
        // la que está guardada en la comanda.
        const propinaGuardada = round2(vivas.filter(esLineaPropina).reduce((a, d) => a + (parseFloat(d.subtotal) || 0), 0));
        const modalAbierto = document.getElementById('mdCuenta')?.classList.contains('show');
        const propina = modalAbierto ? propinaEscrita() : propinaGuardada;
        const $filaProp = document.getElementById('pq-fila-propina-pie');
        $filaProp.classList.toggle('d-none', propina <= 0);
        if (propina > 0) document.getElementById('pq-propina-pie').textContent = money(propina);

        // Las líneas ya traen la propina guardada; se descuenta para no contarla
        // dos veces y se suma la que corresponde mostrar.
        const totalLineas = round2(vivas.reduce((a, d) => a + lineaConIva(d), 0));
        const total = round2(totalLineas - propinaGuardada + propina + servicio);
        document.getElementById('pq-total').textContent = money(total);

        const pendientes = vivas.filter(d => d.estado_linea === 'pendiente').length;
        const $btnConfirmar = document.getElementById('pq-btn-confirmar');
        const $badgeConfirmar = document.getElementById('pq-badge-confirmar');
        // Con la cuenta ya pedida el botón desaparece: el cliente cerró su
        // pedido y lo que quiera agregar después pasa por el mesero. Deshabilitado
        // no alcanza — seguiría ofreciendo una acción que ya no le corresponde.
        const yaPidioCuenta = cuentaYaPedida();
        $btnConfirmar.classList.toggle('d-none', yaPidioCuenta);
        $btnConfirmar.disabled = pendientes === 0;
        if (pendientes > 0 && !yaPidioCuenta) { $badgeConfirmar.textContent = pendientes; $badgeConfirmar.classList.remove('d-none'); }
        else { $badgeConfirmar.classList.add('d-none'); }

        const listos = vivas.filter(d => d.estado_linea === 'listo').length;
        const $badgeListos = document.getElementById('pq-badge-listos');
        if (listos > 0) { $badgeListos.textContent = listos + ' listo(s)'; $badgeListos.classList.remove('d-none'); }
        else { $badgeListos.classList.add('d-none'); }

        // Misma lista que ofrece el modal (la propina no cuenta como ítem
        // pagable): si no, con solo una propina suelta se ofrecería pedir la
        // cuenta y el modal respondería que no hay nada que pagar.
        document.getElementById('pq-cuenta-card').classList.toggle('d-none', lineasEntregadasSinCuenta().length === 0);
    }

    function actualizarAvisoAsistencia() {
        document.getElementById('pq-aviso-asistencia').classList.toggle('d-none', !solicitaAsistencia);
        const $btn = document.getElementById('pq-btn-mesero');
        $btn.disabled = solicitaAsistencia;
        $btn.innerHTML = solicitaAsistencia ? '<i class="bi bi-bell-fill"></i>' : '<i class="bi bi-bell"></i>';
    }

    function actualizarAvisoCuentaPedida() {
        document.getElementById('pq-aviso-cuenta-pedida').classList.toggle('d-none', !cuentaYaPedida());
    }

    async function cargarMenu() {
        try {
            const r = await fetch(AJAX + '/menu');
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo cargar el menú.'); return; }
            menu = d.data;
            renderMenu();
        } catch (e) { document.getElementById('pq-menu').innerHTML = '<div class="pq-empty">Error al cargar el menú.</div>'; }
    }

    // Última propina que vino del servidor. Sirve para distinguir un cambio
    // hecho por el mesero (el valor guardado cambió) de una edición del propio
    // cliente (el guardado sigue igual y lo que difiere es el campo).
    let propinaSincronizada = null;

    /**
     * Mantiene el modal "Pedir mi cuenta" al día mientras está abierto: la
     * comanda se sigue moviendo del lado del mesero (puede quitar la propina,
     * anular un ítem o entregar otro) y el cliente no puede quedarse mirando un
     * total que ya no existe.
     *
     * El campo de propina solo se pisa cuando el cambio viene DEL SERVIDOR. Si
     * se sobreescribiera cada vez que difiere del guardado, el cliente no podría
     * borrarla: al vaciar el campo se le repondría en el siguiente refresco.
     */
    function sincronizarModalCuenta() {
        const guardada = propinaExistente();
        const cambioDelMesero = propinaSincronizada !== null && round2(guardada) !== round2(propinaSincronizada);
        propinaSincronizada = guardada;

        const $modal = document.getElementById('mdCuenta');
        if (!$modal || !$modal.classList.contains('show')) return;

        const $prop = document.getElementById('pq-propina');
        if ($prop && cambioDelMesero && document.activeElement !== $prop) {
            $prop.value = guardada > 0 ? guardada.toFixed(2) : '';
        }
        recalcularTotalCuenta();
    }

    async function refrescarEstado() {
        try {
            const r = await fetch(AJAX + '/estado');
            const d = await r.json();
            if (d.ok) {
                detalles = d.data.detalles || [];
                grupos = d.data.grupos || [];
                solicitaAsistencia = !!d.data.solicita_asistencia;
                renderLineas();
                // El menú se repinta porque el bloqueo de "Agregar" depende de
                // si ya hay una cuenta pedida, y eso puede cambiar del lado del
                // mesero (deshace la cuenta y el cliente vuelve a poder pedir).
                renderMenu();
                actualizarAvisoAsistencia();
                actualizarAvisoCuentaPedida();
                sincronizarModalCuenta();
            }
        } catch (e) { /* silencioso: reintenta en el próximo ciclo */ }
    }

    document.getElementById('pq-menu').addEventListener('click', async (ev) => {
        const btn = ev.target.closest('.btn-agregar');
        if (!btn) return;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('id_menu_item', btn.dataset.id);
        fd.append('cantidad', '1');
        try {
            const r = await fetch(AJAX + '/agregar', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo agregar.'); btn.disabled = false; return; }
            swalToast('success', btn.dataset.nombre + ' agregado');
            await refrescarEstado();
        } catch (e) { swalError('Error de conexión.'); }
        finally { btn.disabled = false; }
    });

    document.getElementById('pq-lineas').addEventListener('click', async (ev) => {
        const quitar = ev.target.closest('.quitar');
        if (!quitar) return;
        const fd = new FormData();
        fd.append('id_linea', quitar.dataset.id);
        try {
            const r = await fetch(AJAX + '/quitar', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo quitar.'); return; }
            await refrescarEstado();
        } catch (e) { swalError('Error de conexión.'); }
    });

    document.getElementById('pq-btn-confirmar').addEventListener('click', async () => {
        const { isConfirmed } = await Swal.fire({
            title: '¿Confirmar pedido?', text: 'Se enviará a preparación — ya no podrás quitar estos ítems.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, confirmar', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
        });
        if (!isConfirmed) return;
        const $btn = document.getElementById('pq-btn-confirmar');
        $btn.disabled = true;
        try {
            const r = await fetch(AJAX + '/enviar', { method: 'POST' });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo confirmar el pedido.'); return; }
            swalToast('success', d.msg || 'Pedido confirmado');
            await refrescarEstado();
        } catch (e) { swalError('Error de conexión.'); }
        finally { $btn.disabled = false; }
    });

    document.getElementById('pq-btn-mesero').addEventListener('click', async () => {
        const $btn = document.getElementById('pq-btn-mesero');
        $btn.disabled = true;
        try {
            const r = await fetch(AJAX + '/asistencia', { method: 'POST' });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo avisar al mesero.'); $btn.disabled = false; return; }
            solicitaAsistencia = true;
            actualizarAvisoAsistencia();
            swalToast('success', d.msg || 'Ya avisamos a un mesero');
        } catch (e) { swalError('Error de conexión.'); $btn.disabled = false; }
    });

    document.getElementById('pq-btn-cancelar-asistencia').addEventListener('click', async () => {
        const $btn = document.getElementById('pq-btn-cancelar-asistencia');
        $btn.disabled = true;
        try {
            const r = await fetch(AJAX + '/cancelar-asistencia', { method: 'POST' });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo cancelar el aviso.'); return; }
            solicitaAsistencia = false;
            actualizarAvisoAsistencia();
        } catch (e) { swalError('Error de conexión.'); }
        finally { $btn.disabled = false; }
    });

    // ─── Pedir mi cuenta ────────────────────────────────────────────────────
    /**
     * Ítems que el cliente puede pagar. La línea de propina queda FUERA a
     * propósito: se administra desde su campo del modal, no como un ítem más.
     * Si apareciera en la lista se contaría dos veces (como ítem y en el campo),
     * y el cliente podría desmarcarla dejándola huérfana en la comanda.
     */
    function lineasEntregadasSinCuenta() {
        return detalles.filter(d => d.estado_linea === 'entregado' && !d.id_grupo_cobro && !esLineaPropina(d));
    }

    /** La propina ya cargada en la comanda (la puso el mesero o el propio cliente antes). */
    function propinaExistente() {
        const l = detalles.find(d => esLineaPropina(d) && !d.id_grupo_cobro);
        return l ? round2(l.subtotal) : 0;
    }

    /** Lo que el cliente escribió en el campo de propina (0 si no hay campo o está vacío). */
    function propinaEscrita() {
        const $p = document.getElementById('pq-propina');
        const v = $p ? parseFloat($p.value) : 0;
        return (v > 0) ? round2(v) : 0;
    }

    /**
     * Desglose de lo que el cliente marcó, con el mismo criterio que el pie de
     * la comanda y que la emisión del documento: IVA redondeado línea por línea
     * y agrupado por tarifa, y recargo por servicio sobre el consumo.
     */
    function recalcularTotalCuenta() {
        const marcados = Array.from(document.querySelectorAll('#pq-cuenta-items .form-check-input:checked'));

        let subtotal = 0;
        const impuestos = {};
        marcados.forEach(chk => {
            const base = round2(chk.dataset.base);
            subtotal = round2(subtotal + base);
            const pct = parseFloat(chk.dataset.pct) || 0;
            if (pct > 0) {
                const lbl = `IVA ${pct}%`;
                impuestos[lbl] = round2((impuestos[lbl] || 0) + round2(base * pct / 100));
            }
        });
        const totalImpuestos = round2(Object.values(impuestos).reduce((a, v) => a + v, 0));

        // El recargo por servicio de esta parte de la cuenta, para que el total
        // que ve el cliente sea el mismo que le va a cobrar Payphone. Se manda
        // id_producto porque servicioDe() debe dejar fuera la línea de propina.
        const servicio = servicioDe(marcados.map(chk => ({
            subtotal: chk.dataset.base,
            id_producto: chk.dataset.idProducto,
            excluir_recargo_servicio: chk.dataset.excluyeRecargo === '1',
        })));
        // La propina que escribe el cliente se suma tal cual: no paga impuestos
        // ni genera recargo por servicio.
        const propina = propinaEscrita();

        document.getElementById('pq-cuenta-subtotal').textContent = money(subtotal);
        document.getElementById('pq-cuenta-impuestos').innerHTML = Object.entries(impuestos).map(([lbl, val]) =>
            `<div class="d-flex justify-content-between" style="padding:2px 0;"><span class="text-muted">${lbl}</span><span>${money(val)}</span></div>`).join('');

        const $filaSrv = document.getElementById('pq-cuenta-fila-servicio');
        $filaSrv.classList.toggle('d-none', servicio <= 0);
        if (servicio > 0) {
            document.getElementById('pq-cuenta-servicio-label').textContent = `Servicio ${PORCENTAJE_SERVICIO}%`;
            document.getElementById('pq-cuenta-servicio').textContent = money(servicio);
        }

        const $filaProp = document.getElementById('pq-cuenta-fila-propina');
        $filaProp.classList.toggle('d-none', propina <= 0);
        if (propina > 0) document.getElementById('pq-cuenta-propina').textContent = money(propina);

        document.getElementById('pq-cuenta-total').textContent = money(round2(subtotal + totalImpuestos + servicio + propina));
    }

    function configurarSelectorDocumento() {
        const $wrap = document.getElementById('pq-doc-wrap');
        const $factura = document.getElementById('pq-doc-factura');
        const $recibo = document.getElementById('pq-doc-recibo');
        if (DOC_PERMITIDOS.factura && DOC_PERMITIDOS.recibo) {
            $wrap.classList.remove('d-none');
            $factura.checked = true; // Factura es la opción principal
        } else {
            $wrap.classList.add('d-none');
            $factura.checked = DOC_PERMITIDOS.factura;
            $recibo.checked = !DOC_PERMITIDOS.factura;
        }
    }

    function configurarModoCliente() {
        const esCf = document.getElementById('pq-modo-cf').checked;
        document.getElementById('pq-cf-nota').classList.toggle('d-none', !esCf);
        document.getElementById('pq-datos-cliente-wrap').classList.toggle('d-none', esCf);
    }

    function abrirModalCuenta() {
        const lineas = lineasEntregadasSinCuenta();
        if (!lineas.length) { swalError('No tienes ítems entregados pendientes por pagar.'); return; }
        document.getElementById('pq-cuenta-items').innerHTML = lineas.map(d => `
            <label class="pq-cuenta-item mb-0">
                <input type="checkbox" class="form-check-input mt-0" checked data-total="${lineaConIva(d)}" data-base="${d.subtotal}" data-pct="${d.porcentaje_iva || 0}" data-id-producto="${d.id_producto || ''}" data-excluye-recargo="${excluyeRecargo(d) ? '1' : ''}">
                <span class="nombre">${cantidad(d.cantidad)} x ${escapeHtml(d.descripcion)}</span>
                <span class="total">${money(lineaConIva(d))}</span>
            </label>`).join('');
        // Si la comanda ya trae una propina (la puso el mesero, o el cliente en
        // un intento anterior), se muestra en el campo para que se vea y se
        // pueda cambiar; si no hay, arranca vacío.
        const $prop = document.getElementById('pq-propina');
        if ($prop) {
            const yaHay = propinaExistente();
            $prop.value = yaHay > 0 ? yaHay.toFixed(2) : '';
            // Punto de partida para distinguir después un cambio del mesero de
            // una edición del cliente (ver sincronizarModalCuenta).
            propinaSincronizada = yaHay;
        }
        recalcularTotalCuenta();
        renderLineas();
        configurarSelectorDocumento();
        document.getElementById('pq-modo-datos').checked = true;
        configurarModoCliente();
        document.getElementById('pq-cli-nombre').value = '';
        document.getElementById('pq-cli-identificacion').value = '';
        document.getElementById('pq-cli-correo').value = '';
        document.getElementById('pq-cli-telefono').value = '';
        document.getElementById('pq-cli-direccion').value = '';
        document.getElementById('pq-cli-tipo-id').value = 'cedula';
        mdCuenta.show();
    }

    document.getElementById('pq-btn-pedir-cuenta').addEventListener('click', abrirModalCuenta);
    document.querySelectorAll('input[name="pq-modo-cliente"]').forEach(r => r.addEventListener('change', configurarModoCliente));
    document.getElementById('pq-cuenta-items').addEventListener('change', (ev) => {
        if (ev.target.matches('.form-check-input')) recalcularTotalCuenta();
    });
    // El total se actualiza mientras el cliente escribe la propina, para que vea
    // de inmediato cuánto va a pagar: tanto en el modal como en la cuenta que
    // queda detrás.
    document.getElementById('pq-propina')?.addEventListener('input', () => {
        recalcularTotalCuenta();
        renderLineas();
    });

    // ─── Consulta SRI por identificación (autocompleta nombre/correo/teléfono/dirección) ──
    let sriDebounceTimer = null;
    function mostrarSpinnerSri(visible) {
        document.getElementById('pq-cli-id-spinner').style.display = visible ? '' : 'none';
    }
    async function consultarSri(identificacion) {
        mostrarSpinnerSri(true);
        try {
            const r = await fetch(AJAX + '/sri?identificacion=' + encodeURIComponent(identificacion));
            const d = await r.json();
            if (d.ok) {
                if (d.nombre) document.getElementById('pq-cli-nombre').value = d.nombre;
                if (d.correo) document.getElementById('pq-cli-correo').value = d.correo;
                if (d.telefono) document.getElementById('pq-cli-telefono').value = d.telefono;
                if (d.direccion) document.getElementById('pq-cli-direccion').value = d.direccion;
            }
        } catch (e) { /* silencioso: el cliente puede llenar los datos a mano */ }
        finally { mostrarSpinnerSri(false); }
    }
    function onIdentificacionInput() {
        clearTimeout(sriDebounceTimer);
        const tipo = document.getElementById('pq-cli-tipo-id').value;
        const valor = document.getElementById('pq-cli-identificacion').value.replace(/\D/g, '');
        const longEsperada = tipo === 'ruc' ? 13 : (tipo === 'cedula' ? 10 : null);
        if (!longEsperada || valor.length !== longEsperada) return;
        sriDebounceTimer = setTimeout(() => consultarSri(valor), 700);
    }
    document.getElementById('pq-cli-identificacion').addEventListener('input', onIdentificacionInput);
    document.getElementById('pq-cli-tipo-id').addEventListener('change', onIdentificacionInput);

    document.getElementById('pq-btn-confirmar-cuenta').addEventListener('click', async () => {
        const lineas = lineasEntregadasSinCuenta();
        const checks = Array.from(document.querySelectorAll('#pq-cuenta-items .form-check-input'));
        const idsSeleccionados = checks
            .map((chk, i) => chk.checked ? lineas[i].id : null)
            .filter(id => id !== null);
        if (!idsSeleccionados.length) { swalError('Selecciona al menos un ítem para pagar.'); return; }

        const esConsumidorFinal = document.getElementById('pq-modo-cf').checked;
        const tipoDocumento = document.querySelector('input[name="pq-tipo-doc"]:checked').value;
        const fd = new FormData();
        fd.append('ids_lineas', JSON.stringify(idsSeleccionados));
        fd.append('tipo_documento', tipoDocumento);
        // El servidor crea la línea de propina y la mete en esta misma cuenta.
        fd.append('propina', String(propinaEscrita()));
        // Sugerencia de forma de pago: el mesero la ve y decide.
        fd.append('id_forma_pago_sugerida', document.getElementById('pq-forma-pago')?.value || '');

        if (esConsumidorFinal) {
            fd.append('consumidor_final', '1');
        } else {
            const nombre = document.getElementById('pq-cli-nombre').value.trim();
            const identificacion = document.getElementById('pq-cli-identificacion').value.trim();
            const correo = document.getElementById('pq-cli-correo').value.trim();
            if (!nombre) { swalError('Escribe tu nombre completo.'); return; }
            if (!identificacion) { swalError('Escribe tu número de identificación.'); return; }
            if (!correo) { swalError('Escribe tu correo electrónico.'); return; }

            fd.append('nombre', nombre);
            fd.append('tipo_identificacion', document.getElementById('pq-cli-tipo-id').value);
            fd.append('identificacion', identificacion);
            fd.append('correo', correo);
            fd.append('telefono', document.getElementById('pq-cli-telefono').value.trim());
            fd.append('direccion', document.getElementById('pq-cli-direccion').value.trim());
        }

        const $btn = document.getElementById('pq-btn-confirmar-cuenta');
        $btn.disabled = true;
        try {
            const r = await fetch(AJAX + '/cuenta', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo enviar la solicitud.'); return; }
            mdCuenta.hide();
            await refrescarEstado();

            // El pago lo cierra el mesero: el cliente solo avisa que quiere su
            // cuenta y con qué piensa pagar (sugerencia). Por eso este aviso ya
            // no ofrece pagar en línea.
            await Swal.fire({
                icon: 'success', title: '¡Listo!', text: d.msg || 'Ya avisamos para que te cobren.',
                confirmButtonText: 'Entendido', confirmButtonColor: '#0d6efd',
            });
        } catch (e) { swalError('Error de conexión.'); }
        finally { $btn.disabled = false; }
    });

    renderLineas();
    actualizarAvisoAsistencia();
    actualizarAvisoCuentaPedida();
    cargarMenu();
    setInterval(refrescarEstado, 6000);

    // Vuelta desde Payphone tras un pago aprobado (url_exito) — el cobro real
    // ya se procesó del lado del servidor; esto solo avisa al cliente.
    if (new URLSearchParams(location.search).get('pago') === 'ok') {
        Swal.fire({ icon: 'success', title: '¡Pago aprobado!', text: 'Gracias, tu cuenta quedó pagada.', confirmButtonColor: '#0d6efd' });
        history.replaceState(null, '', location.pathname);
        refrescarEstado();
    }
})();
</script>
</body>
</html>
