<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($plantilla['nombre'] ?? 'Factura Express', ENT_QUOTES) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .page-header { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); color: #fff; padding: 2rem 1.5rem 1.5rem; }
        .form-label { font-size: .82rem; font-weight: 500; color: #374151; margin-bottom: .25rem; }
        .form-control, .form-select { font-size: .88rem; }
        .section-label { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
        .item-row td { vertical-align: middle; }
        .item-row input[type=number] { width: 80px; }
        .total-row td { font-size: .9rem; font-weight: 600; }
        #sri-spinner { display: none; }
    </style>
</head>
<body>

<div class="page-header text-center">
    <h4 class="mb-1 fw-bold"><i class="bi bi-receipt-cutoff me-2 opacity-75"></i><?= htmlspecialchars($plantilla['nombre'] ?? '', ENT_QUOTES) ?></h4>
    <?php if (!empty($plantilla['descripcion'])): ?>
        <p class="mb-0 small opacity-75"><?= htmlspecialchars($plantilla['descripcion'], ENT_QUOTES) ?></p>
    <?php endif; ?>
    <?php if (!empty($plantilla['mensaje_bienvenida'])): ?>
        <p class="mb-0 mt-2 small opacity-90 fst-italic"><?= htmlspecialchars($plantilla['mensaje_bienvenida'], ENT_QUOTES) ?></p>
    <?php endif; ?>
</div>

<div class="container py-4" style="max-width: 680px;">

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= rtrim(BASE_URL, '/') ?>/factura-express/<?= htmlspecialchars($plantilla['token'], ENT_QUOTES) ?>/enviar" novalidate id="formFexpr">
        <input type="hidden" name="csrf_token" value="">

        <!-- Datos del cliente -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="section-label"><i class="bi bi-person me-1"></i>Tus datos</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-2">

                    <?php if ($config['identificacion'] !== false): ?>
                    <div class="col-md-5">
                        <label class="form-label">Tipo de identificación</label>
                        <select name="tipo_identificacion" class="form-select form-select-sm" id="tipoIdent">
                            <?php
                            $tiposId = ['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte'];
                            $tipoSel = $formData['tipo_identificacion'] ?? 'cedula';
                            foreach ($tiposId as $v => $l):
                            ?>
                                <option value="<?= $v ?>" <?= $tipoSel === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Identificación <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="identificacion" class="form-control" id="inputIdent"
                                   value="<?= htmlspecialchars($formData['identificacion'] ?? '', ENT_QUOTES) ?>"
                                   placeholder="Número de identificación" maxlength="20" autocomplete="off">
                            <span class="input-group-text bg-white" id="sri-spinner">
                                <span class="spinner-border spinner-border-sm text-primary"></span>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($config['nombre'] !== false): ?>
                    <div class="col-12">
                        <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_cliente" id="inputNombre" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($formData['nombre_cliente'] ?? '', ENT_QUOTES) ?>"
                               placeholder="Se completará automáticamente" maxlength="150" required>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($config['correo'])): ?>
                    <div class="col-12">
                        <label class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="correo_cliente" id="inputCorreo" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($formData['correo_cliente'] ?? '', ENT_QUOTES) ?>"
                               placeholder="correo@ejemplo.com" maxlength="150" required>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($config['telefono']) && !empty($config['direccion'])): ?>
                    <div class="col-5">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" name="telefono_cliente" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($formData['telefono_cliente'] ?? '', ENT_QUOTES) ?>"
                               placeholder="0999000000" maxlength="20">
                    </div>
                    <div class="col-7">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion_cliente" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($formData['direccion_cliente'] ?? '', ENT_QUOTES) ?>"
                               placeholder="Calle, número, sector..." maxlength="200">
                    </div>
                    <?php elseif (!empty($config['telefono'])): ?>
                    <div class="col-md-5">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" name="telefono_cliente" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($formData['telefono_cliente'] ?? '', ENT_QUOTES) ?>"
                               placeholder="0999000000" maxlength="20">
                    </div>
                    <?php elseif (!empty($config['direccion'])): ?>
                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion_cliente" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($formData['direccion_cliente'] ?? '', ENT_QUOTES) ?>"
                               placeholder="Calle, número, sector..." maxlength="200">
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Ítems -->
        <?php if (!empty($items)): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="section-label"><i class="bi bi-list-check me-1"></i>Servicios / Productos</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:.85rem">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:32px"></th>
                                <th>Descripción</th>
                                <th class="text-end" style="width:90px">P. Unit.</th>
                                <th class="text-center" style="width:90px">Cantidad</th>
                                <th class="text-end pe-3" style="width:100px">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $idx => $it): ?>
                            <tr class="item-row" data-precio="<?= (float)$it['precio_unitario'] ?>" data-iva="<?= (float)$it['porcentaje_iva'] ?>">
                                <td class="ps-3 text-center">
                                    <input type="checkbox" name="items[<?= $idx ?>][id_item]" value="<?= (int)$it['id'] ?>"
                                           class="form-check-input item-check"
                                           <?= !empty($it['seleccionado_default']) ? 'checked' : '' ?>>
                                    <input type="hidden" name="items[<?= $idx ?>][id_producto]" value="<?= (int)($it['id_producto'] ?? 0) ?>">
                                    <input type="hidden" name="items[<?= $idx ?>][descripcion]" value="<?= htmlspecialchars($it['descripcion'], ENT_QUOTES) ?>">
                                    <input type="hidden" name="items[<?= $idx ?>][precio_unitario]" value="<?= (float)$it['precio_unitario'] ?>">
                                    <input type="hidden" name="items[<?= $idx ?>][porcentaje_iva]" value="<?= (float)$it['porcentaje_iva'] ?>">
                                </td>
                                <td><?= htmlspecialchars($it['descripcion']) ?></td>
                                <td class="text-end">$<?= number_format((float)$it['precio_unitario'], 2) ?></td>
                                <td class="text-center">
                                    <?php if (!empty($it['cantidad_editable'])): ?>
                                        <input type="number" name="items[<?= $idx ?>][cantidad]"
                                               class="form-control form-control-sm text-center item-cantidad p-0"
                                               style="width:70px;margin:0 auto;height:26px;font-size:.82rem"
                                               value="<?= max(1, (float)($it['cantidad_default'] ?? 1)) ?>"
                                               min="0.01" step="0.01">
                                    <?php else: ?>
                                        <span><?= number_format((float)($it['cantidad_default'] ?? 1), 2) ?></span>
                                        <input type="hidden" name="items[<?= $idx ?>][cantidad]" value="<?= (float)($it['cantidad_default'] ?? 1) ?>">
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3 item-subtotal fw-medium">
                                    $<?= number_format((float)$it['precio_unitario'] * (float)($it['cantidad_default'] ?? 1), 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totales estilo factura -->
                <div class="border-top p-3 bg-light rounded-bottom-3">
                    <div class="d-flex justify-content-end">
                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.82rem;min-width:240px;">

                            <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                <span class="text-muted">Subtotal</span>
                                <span id="fexprSubtotal">0.00</span>
                            </div>

                            <div id="fexprSubtotalesIva" class="mb-1"></div>

                            <div id="fexprIvasGrupo" class="mb-1"></div>

                            <hr class="my-1 opacity-25">

                            <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                <span class="fw-bold text-dark">TOTAL</span>
                                <span class="fw-bold text-dark" style="font-size:1rem;" id="fexprTotal">0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg shadow-sm" id="btnEnviarFexpr">
                <i class="bi bi-send me-2"></i>Enviar Solicitud
            </button>
        </div>
        <p class="text-center text-muted small mt-2">
            <i class="bi bi-shield-check me-1"></i>Tus datos están protegidos y solo serán usados para emitir tu factura.
        </p>
    </form>
</div>

<script>
(function () {
    'use strict';

    const baseUrl = '<?= rtrim(BASE_URL, '/') ?>';
    const token   = '<?= htmlspecialchars($plantilla['token'] ?? '', ENT_QUOTES) ?>';

    function recalcTotales() {
        let subtotal = 0;
        const gruposIva = {}; // { '15': { base: 0, iva: 0 }, '0': { base: 0, iva: 0 }, ... }

        document.querySelectorAll('.item-row').forEach(row => {
            const chk   = row.querySelector('.item-check');
            if (!chk?.checked) return;
            const precio = parseFloat(row.dataset.precio) || 0;
            const ivaP   = parseFloat(row.dataset.iva)   || 0;
            const cantEl = row.querySelector('.item-cantidad');
            const cant   = cantEl
                ? parseFloat(cantEl.value) || 0
                : parseFloat(row.querySelector('input[type=hidden][name*=cantidad]')?.value || 1);
            const base   = precio * cant;
            subtotal += base;
            const key = ivaP.toFixed(0);
            if (!gruposIva[key]) gruposIva[key] = { base: 0, iva: 0, pct: ivaP };
            gruposIva[key].base += base;
            gruposIva[key].iva  += base * (ivaP / 100);
        });

        // Subtotal general
        document.getElementById('fexprSubtotal').textContent = subtotal.toFixed(2);

        // Subtotales por tarifa IVA
        const elSubs = document.getElementById('fexprSubtotalesIva');
        elSubs.innerHTML = '';
        Object.entries(gruposIva).forEach(([key, g]) => {
            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between align-items-center mb-1';
            div.innerHTML = `<span class="text-muted">Subtotal ${key}%</span><span class="fw-medium">${g.base.toFixed(2)}</span>`;
            elSubs.appendChild(div);
        });

        // IVA agrupado (solo tarifas > 0)
        const elIvas = document.getElementById('fexprIvasGrupo');
        elIvas.innerHTML = '';
        let totalIva = 0;
        Object.entries(gruposIva).forEach(([key, g]) => {
            if (g.pct <= 0) return;
            totalIva += g.iva;
            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between align-items-center mb-1';
            div.innerHTML = `<span class="text-muted">(+) IVA ${key}%</span><span class="fw-medium">${g.iva.toFixed(2)}</span>`;
            elIvas.appendChild(div);
        });

        const total = subtotal + totalIva;
        document.getElementById('fexprTotal').textContent = total.toFixed(2);
    }

    document.querySelectorAll('.item-check, .item-cantidad').forEach(el => {
        el.addEventListener('change', function() {
            // Actualizar subtotal de la fila
            const row  = this.closest('.item-row');
            const chk  = row.querySelector('.item-check');
            const precio  = parseFloat(row.dataset.precio) || 0;
            const cantEl  = row.querySelector('.item-cantidad');
            const cant    = cantEl ? parseFloat(cantEl.value) || 0 : parseFloat(row.querySelector('input[type=hidden][name*=cantidad]')?.value || 1);
            const sub     = chk?.checked ? precio * cant : 0;
            row.querySelector('.item-subtotal').textContent = '$' + sub.toFixed(2);
            recalcTotales();
        });
    });

    recalcTotales();

    // Consulta identificación (local → SRI)
    let sriTimer;
    const inputIdent  = document.getElementById('inputIdent');
    const tipoIdent   = document.getElementById('tipoIdent');
    const inputNombre = document.getElementById('inputNombre');
    const inputCorreo = document.getElementById('inputCorreo');

    function longitudEsperada() {
        const tipo = tipoIdent?.value ?? 'cedula';
        if (tipo === 'cedula') return 10;
        if (tipo === 'ruc')    return 13;
        return 0; // pasaporte / sin_ruc: no consultar
    }

    function verificarYConsultar() {
        clearTimeout(sriTimer);
        const val = inputIdent?.value.replace(/\D/g, '') ?? '';
        const esp = longitudEsperada();
        if (esp > 0 && val.length === esp) {
            sriTimer = setTimeout(() => consultarIdentificacion(val), 500);
        } else {
            ocultarSriRes();
        }
    }

    function aplicarRestriccionIdent() {
        if (!inputIdent) return;
        const tipo = tipoIdent?.value ?? 'cedula';
        if (tipo === 'cedula') {
            inputIdent.maxLength = 10;
            inputIdent.setAttribute('inputmode', 'numeric');
            inputIdent.setAttribute('pattern', '[0-9]{10}');
        } else if (tipo === 'ruc') {
            inputIdent.maxLength = 13;
            inputIdent.setAttribute('inputmode', 'numeric');
            inputIdent.setAttribute('pattern', '[0-9]{13}');
        } else if (tipo === 'pasaporte') {
            inputIdent.maxLength = 20;
            inputIdent.removeAttribute('inputmode');
            inputIdent.removeAttribute('pattern');
        } else {
            // sin_ruc: consumidor final, sin restricción fuerte
            inputIdent.maxLength = 20;
            inputIdent.removeAttribute('inputmode');
            inputIdent.removeAttribute('pattern');
        }
        // Limpiar caracteres no permitidos en tiempo real
        if (tipo === 'cedula' || tipo === 'ruc') {
            inputIdent.value = inputIdent.value.replace(/\D/g, '').slice(0, inputIdent.maxLength);
        } else {
            inputIdent.value = inputIdent.value.replace(/[^a-zA-Z0-9]/g, '').slice(0, inputIdent.maxLength);
        }
    }

    inputIdent?.addEventListener('input', function() {
        aplicarRestriccionIdent();
        verificarYConsultar();
    });
    tipoIdent?.addEventListener('change', function() {
        inputIdent.value = '';
        aplicarRestriccionIdent();
        ocultarSriRes();
    });

    // Aplicar restricción inicial al cargar
    aplicarRestriccionIdent();

    async function consultarIdentificacion(id) {
        document.getElementById('sri-spinner').style.display = 'flex';
        ocultarSriRes();
        try {
            const r = await fetch(`${baseUrl}/factura-express/${token}/sri?identificacion=${encodeURIComponent(id)}`);
            const d = await r.json();
            if (d.ok && d.nombre) {
                if (inputNombre && !inputNombre.value.trim()) inputNombre.value = d.nombre;
                if (inputCorreo && !inputCorreo.value.trim() && d.correo) inputCorreo.value = d.correo;
            }
        } catch(e) {}
        document.getElementById('sri-spinner').style.display = 'none';
    }

    function ocultarSriRes() {}

    // Evitar doble envío
    document.getElementById('formFexpr').addEventListener('submit', function() {
        const btn = document.getElementById('btnEnviarFexpr');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
