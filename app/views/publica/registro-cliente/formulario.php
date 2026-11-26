<?php
/**
 * Formulario público del QR de registro de clientes.
 * @var array       $cfg
 * @var array       $form
 * @var string|null $error
 * @var array       $tipos   codigo => etiqueta
 * @var string      $token
 */
$tituloPagina = trim((string) ($cfg['titulo'] ?? '')) ?: 'Registra tus datos';
$v = static fn (string $k): string => htmlspecialchars((string) ($form[$k] ?? ''), ENT_QUOTES);
$tipoSel = (string) ($form['tipo_id'] ?? '05');
$urlBase = rtrim(BASE_URL, '/') . '/registro-cliente/' . rawurlencode($token);
require __DIR__ . '/_cabecera.php';
?>
    <?php if (!empty($cfg['mensaje'])): ?>
        <p class="text-center text-secondary mb-4" style="white-space:pre-line"><?= htmlspecialchars((string) $cfg['mensaje'], ENT_QUOTES) ?></p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger rounded-3 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars((string) $error, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= $urlBase ?>/enviar" id="formRegistro" class="card border-0 shadow-sm p-3 p-sm-4" novalidate>
        <div class="row g-3">
            <div class="col-5">
                <label class="form-label d-block" for="tipo_id">Tipo</label>
                <select class="form-select" id="tipo_id" name="tipo_id" required>
                    <?php foreach ($tipos as $cod => $lbl): ?>
                        <option value="<?= $cod ?>" <?= $tipoSel === $cod ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-7">
                <label class="form-label d-block" for="identificacion">Identificación <span class="text-danger">*</span></label>
                <div class="position-relative">
                    <input type="text" class="form-control" id="identificacion" name="identificacion" value="<?= $v('identificacion') ?>"
                           inputmode="numeric" autocomplete="off" maxlength="20" required>
                    <span id="sriSpinner" class="spinner-border spinner-border-sm text-primary position-absolute d-none" style="right:10px;top:11px"></span>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label d-block" for="nombre">Nombre completo o razón social <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= $v('nombre') ?>" maxlength="300" autocomplete="name" required>
            </div>
            <div class="col-12">
                <label class="form-label d-block" for="email">Correo electrónico <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" value="<?= $v('email') ?>" maxlength="200" autocomplete="email" inputmode="email" required>
                <div class="form-text">Aquí recibirás tus facturas.</div>
            </div>
            <div class="col-12 col-sm-5">
                <label class="form-label d-block" for="telefono">Teléfono</label>
                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= $v('telefono') ?>" maxlength="20" autocomplete="tel" inputmode="tel">
            </div>
            <div class="col-12 col-sm-7">
                <label class="form-label d-block" for="direccion">Dirección</label>
                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= $v('direccion') ?>" maxlength="300" autocomplete="street-address">
            </div>
        </div>

        <div class="alert alert-light border small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Si ya estás registrado, se actualizarán tus datos con lo que envíes. ¿Te equivocaste? Vuelve a enviar el formulario con los datos correctos.
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100 mt-3" id="btnEnviar">
            <i class="bi bi-check2-circle me-1"></i>Enviar mis datos
        </button>
    </form>
</div>

<script>
(function () {
    'use strict';
    const urlSri = <?= json_encode($urlBase . '/sri') ?>;
    const tipo   = document.getElementById('tipo_id');
    const ident  = document.getElementById('identificacion');
    const nombre = document.getElementById('nombre');
    const spin   = document.getElementById('sriSpinner');
    let ultima = '';

    function ajustarTeclado() {
        ident.setAttribute('inputmode', tipo.value === '06' ? 'text' : 'numeric');
        ident.setAttribute('maxlength', tipo.value === '05' ? '10' : (tipo.value === '04' ? '13' : '20'));
    }
    tipo.addEventListener('change', ajustarTeclado);
    ajustarTeclado();

    // Con la cédula (10) o el RUC (13) completos se pide el nombre al SRI y se llena
    // si el campo está vacío. Nunca se muestran datos ya registrados en la empresa.
    ident.addEventListener('input', async () => {
        if (tipo.value === '06') return;
        const num = ident.value.replace(/\D/g, '');
        const largo = tipo.value === '05' ? 10 : 13;
        if (num.length !== largo || num === ultima) return;
        ultima = num;
        spin.classList.remove('d-none');
        try {
            const r = await fetch(`${urlSri}?identificacion=${encodeURIComponent(num)}`);
            const d = await r.json();
            if (d.ok && d.nombre && nombre.value.trim() === '') nombre.value = d.nombre;
        } catch (e) { /* sin SRI: el cliente escribe su nombre */ }
        spin.classList.add('d-none');
    });

    // Un envío por clic: el botón se bloquea al enviar.
    document.getElementById('formRegistro').addEventListener('submit', function () {
        const b = document.getElementById('btnEnviar');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    });
})();
</script>
</body>
</html>
