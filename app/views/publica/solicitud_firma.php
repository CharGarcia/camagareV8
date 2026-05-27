<?php
$adjBox = function(string $campo, string $label, string $icon, string $accept) {
    echo '<div class="adj-box" id="box_' . $campo . '">'
       . '<input type="file" name="' . $campo . '" id="' . $campo . '" accept="' . $accept . '">'
       . '<div class="adj-icon bi ' . $icon . '"></div>'
       . '<div class="fw-medium small mt-1">' . htmlspecialchars($label) . '</div>'
       . '<div class="adj-preview">Haz clic o arrastra el archivo aquí</div>'
       . '</div>';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Firma Electrónica - <?= htmlspecialchars($solicitud['empresa_nombre'] ?? '', ENT_QUOTES) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --bs-primary: #3b82f6; }
        body { background: linear-gradient(135deg,#f8fafc 0%,#e2e8f0 100%); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .page-header { background: linear-gradient(135deg,#1e293b 0%,#334155 100%); color:#fff; padding: 2rem 1.5rem; border-radius: 0 0 1.5rem 1.5rem; margin-bottom: 2rem; }
        .section-title { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: .5rem; }
        .form-label { font-size: .82rem; font-weight: 500; color: #374151; margin-bottom: .25rem; }
        .form-control, .form-select { font-size: .88rem; }
        .required-star { color: #ef4444; }
        .adj-box { border: 2px dashed #cbd5e1; border-radius: .75rem; padding: 1rem; text-align: center; cursor: pointer; transition: border-color .2s, background .2s; position: relative; min-height: 110px; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .adj-box:hover { border-color: #3b82f6; background: #eff6ff; }
        .adj-box input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .adj-box .adj-preview { font-size: .76rem; color: #64748b; margin-top: .35rem; }
        .adj-box .adj-icon { font-size: 1.8rem; color: #94a3b8; }
        .adj-box.has-file { border-color: #22c55e; background: #f0fdf4; }
        .adj-box.has-file .adj-icon { color: #22c55e; }
        .price-tag { font-size: .95rem; font-weight: 600; color: #16a34a; }
    </style>
</head>
<body>

<div class="page-header text-center">
    <h4 class="mb-1 fw-bold"><?= htmlspecialchars($solicitud['empresa_nombre'] ?? '', ENT_QUOTES) ?></h4>
    <p class="mb-0 small opacity-75">Formulario de Solicitud de Firma Electrónica</p>
</div>

<div class="container" style="max-width:760px;">

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger rounded-3 shadow-sm mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Por favor corrija los siguientes errores:</strong>
        <ul class="mb-0 mt-2 ps-3">
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e, ENT_QUOTES) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST"
      action="<?= BASE_URL ?>/solicitud-firma/<?= htmlspecialchars($solicitud['token'], ENT_QUOTES) ?>/enviar"
      enctype="multipart/form-data" novalidate id="formSolicitud">
    <input type="hidden" name="token" value="<?= htmlspecialchars($solicitud['token'], ENT_QUOTES) ?>">

    <!-- TIPO PERSONA -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title mb-2"><i class="bi bi-person-badge me-1"></i>Tipo de persona</p>
            <div class="d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_persona" id="tp_natural" value="natural"
                        <?= ($post['tipo_persona'] ?? 'natural') === 'natural' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tp_natural">Persona Natural</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_persona" id="tp_juridica" value="juridica"
                        <?= ($post['tipo_persona'] ?? '') === 'juridica' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tp_juridica">Persona Jurídica</label>
                </div>
            </div>
        </div>
    </div>

    <!-- VALIDEZ / TIPO FIRMA -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-pen me-1"></i>Validez de la firma <span class="required-star">*</span></p>
            <?php if (empty($tiposFirma)): ?>
                <p class="text-muted small">No hay productos de firma configurados.</p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($tiposFirma as $tf): ?>
                        <div class="col-12 col-sm-6">
                            <div class="form-check border rounded-2 p-2 ps-4">
                                <input class="form-check-input tipo-firma-radio" type="radio" name="id_producto"
                                       id="tf_<?= $tf['id'] ?>" value="<?= $tf['id'] ?>"
                                       data-pvp="<?= number_format((float)$tf['pvp'], 2) ?>"
                                       <?= ($post['id_producto'] ?? '') == $tf['id'] ? 'checked' : '' ?>>
                                <label class="form-check-label d-flex justify-content-between align-items-center w-100 pe-1" for="tf_<?= $tf['id'] ?>">
                                    <span class="fw-medium small"><?= htmlspecialchars($tf['nombre'], ENT_QUOTES) ?></span>
                                    <span class="price-tag ms-2">$<?= number_format((float)$tf['pvp'], 2) ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="precioSeleccionado" class="mt-2 text-success small fw-medium d-none">
                    <i class="bi bi-check-circle me-1"></i>Precio total con IVA: <strong id="pvpMostrado"></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PERSONA JURÍDICA -->
    <div id="bloqueJuridica" class="card border-0 shadow-sm rounded-3 mb-3" style="display:none!important;">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-building me-1"></i>Datos de la empresa</p>
            <div class="row g-2">
                <div class="col-12 col-sm-5">
                    <label class="form-label" for="ruc_empresa">RUC de la empresa <span class="required-star">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="ruc_empresa" name="ruc_empresa" maxlength="13"
                           value="<?= htmlspecialchars($post['ruc_empresa'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-12 col-sm-7">
                    <label class="form-label" for="nombre_empresa">Nombre de la empresa <span class="required-star">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="nombre_empresa" name="nombre_empresa"
                           value="<?= htmlspecialchars($post['nombre_empresa'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="cargo">Cargo del representante <span class="required-star">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="cargo" name="cargo"
                           value="<?= htmlspecialchars($post['cargo'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- IDENTIFICACIÓN -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-card-text me-1"></i>Identificación</p>
            <div class="row g-2">
                <div class="col-12 col-sm-4">
                    <label class="form-label">Tipo <span class="required-star">*</span></label>
                    <select class="form-select form-select-sm" name="tipo_identificacion" id="tipo_identificacion">
                        <option value="">-- Seleccione --</option>
                        <option value="cedula"    <?= ($post['tipo_identificacion'] ?? '') === 'cedula'    ? 'selected' : '' ?>>Cédula</option>
                        <option value="pasaporte" <?= ($post['tipo_identificacion'] ?? '') === 'pasaporte' ? 'selected' : '' ?>>Pasaporte</option>
                        <option value="ruc"       <?= ($post['tipo_identificacion'] ?? '') === 'ruc'       ? 'selected' : '' ?>>RUC</option>
                    </select>
                </div>
                <div class="col-12 col-sm-5">
                    <label class="form-label">Número <span class="required-star">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" name="numero_identificacion" id="numero_identificacion" maxlength="13"
                               value="<?= htmlspecialchars($post['numero_identificacion'] ?? '', ENT_QUOTES) ?>">
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" id="btnBuscarSri" title="Consultar SRI">
                            <i class="bi bi-search" id="sriIcono"></i>
                            <span id="sriLoader" class="spinner-border spinner-border-sm d-none"></span>
                        </button>
                    </div>
                </div>
                <div class="col-12 col-sm-3 d-flex align-items-end pb-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="con_ruc" name="con_ruc" value="1"
                               <?= !empty($post['con_ruc']) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-medium" for="con_ruc">¿Con RUC?</label>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label" for="codigo_dactilar">Código dactilar</label>
                    <input type="text" class="form-control form-control-sm" id="codigo_dactilar" name="codigo_dactilar" maxlength="20"
                           value="<?= htmlspecialchars($post['codigo_dactilar'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- DATOS PERSONALES -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-person me-1"></i>Datos personales</p>
            <div class="row g-2">
                <div class="col-12 col-sm-6">
                    <label class="form-label">Apellidos <span class="required-star">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="apellidos" id="apellidos"
                           value="<?= htmlspecialchars($post['apellidos'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Nombres <span class="required-star">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="nombres" id="nombres"
                           value="<?= htmlspecialchars($post['nombres'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label">Sexo</label>
                    <select class="form-select form-select-sm" name="sexo">
                        <option value="">-- Seleccione --</option>
                        <option value="hombre" <?= ($post['sexo'] ?? '') === 'hombre' ? 'selected' : '' ?>>Masculino</option>
                        <option value="mujer"  <?= ($post['sexo'] ?? '') === 'mujer'  ? 'selected' : '' ?>>Femenino</option>
                    </select>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label">Fecha de nacimiento</label>
                    <input type="date" class="form-control form-control-sm" name="fecha_nacimiento"
                           value="<?= htmlspecialchars($post['fecha_nacimiento'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label">Nacionalidad</label>
                    <select class="form-select form-select-sm" name="nacionalidad">
                        <option value="Ecuatoriana"     <?= ($post['nacionalidad'] ?? 'Ecuatoriana') === 'Ecuatoriana'     ? 'selected' : '' ?>>Ecuatoriana</option>
                        <option value="Colombiana"      <?= ($post['nacionalidad'] ?? '') === 'Colombiana'      ? 'selected' : '' ?>>Colombiana</option>
                        <option value="Peruana"         <?= ($post['nacionalidad'] ?? '') === 'Peruana'         ? 'selected' : '' ?>>Peruana</option>
                        <option value="Boliviana"       <?= ($post['nacionalidad'] ?? '') === 'Boliviana'       ? 'selected' : '' ?>>Boliviana</option>
                        <option value="Argentina"       <?= ($post['nacionalidad'] ?? '') === 'Argentina'       ? 'selected' : '' ?>>Argentina</option>
                        <option value="Brasileña"       <?= ($post['nacionalidad'] ?? '') === 'Brasileña'       ? 'selected' : '' ?>>Brasileña</option>
                        <option value="Paraguaya"       <?= ($post['nacionalidad'] ?? '') === 'Paraguaya'       ? 'selected' : '' ?>>Paraguaya</option>
                        <option value="Uruguaya"        <?= ($post['nacionalidad'] ?? '') === 'Uruguaya'        ? 'selected' : '' ?>>Uruguaya</option>
                        <option value="Estadounidense"  <?= ($post['nacionalidad'] ?? '') === 'Estadounidense'  ? 'selected' : '' ?>>Estadounidense</option>
                        <option value="Canadiense"      <?= ($post['nacionalidad'] ?? '') === 'Canadiense'      ? 'selected' : '' ?>>Canadiense</option>
                        <option value="Mexicana"        <?= ($post['nacionalidad'] ?? '') === 'Mexicana'        ? 'selected' : '' ?>>Mexicana</option>
                        <option value="Española"        <?= ($post['nacionalidad'] ?? '') === 'Española'        ? 'selected' : '' ?>>Española</option>
                        <option value="Italiana"        <?= ($post['nacionalidad'] ?? '') === 'Italiana'        ? 'selected' : '' ?>>Italiana</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Teléfono <span class="required-star">*</span></label>
                    <input type="tel" class="form-control form-control-sm" name="telefono"
                           value="<?= htmlspecialchars($post['telefono'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Correo electrónico <span class="required-star">*</span></label>
                    <input type="email" class="form-control form-control-sm" name="correo"
                           value="<?= htmlspecialchars($post['correo'] ?? $solicitud['correo_destino'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- UBICACIÓN -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-geo-alt me-1"></i>Ubicación</p>
            <div class="row g-2">
                <div class="col-12 col-sm-4">
                    <label class="form-label">Provincia <span class="required-star">*</span></label>
                    <select class="form-select form-select-sm" name="cod_prov" id="cod_prov">
                        <option value="">-- Seleccione --</option>
                        <?php foreach ($provincias as $prov): ?>
                            <option value="<?= htmlspecialchars($prov['codigo'], ENT_QUOTES) ?>"
                                <?= ($post['cod_prov'] ?? '') === $prov['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nombre'], ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label">Ciudad <span class="required-star">*</span></label>
                    <select class="form-select form-select-sm" name="cod_ciudad" id="cod_ciudad">
                        <option value="">-- Seleccione provincia --</option>
                    </select>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label">Dirección <span class="required-star">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="direccion"
                           value="<?= htmlspecialchars($post['direccion'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- DOCUMENTOS PERSONALES -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-file-earmark-image me-1"></i>Documentos personales <span class="required-star">*</span></p>
            <p class="text-muted small mb-3">Fotos claras de su cédula y una selfie. Formatos: JPG, PNG, WEBP. Máx. 5 MB c/u.</p>
            <div class="row g-3">
                <div class="col-12 col-sm-4"><?php $adjBox('cedula_frontal',   'Cédula - Frente',   'bi-credit-card-fill',    'image/*'); ?></div>
                <div class="col-12 col-sm-4"><?php $adjBox('cedula_posterior', 'Cédula - Posterior', 'bi-credit-card',         'image/*'); ?></div>
                <div class="col-12 col-sm-4"><?php $adjBox('selfie_cedula',    'Selfie con cédula',  'bi-person-bounding-box', 'image/*'); ?></div>
            </div>
        </div>
    </div>

    <!-- DOCUMENTOS EMPRESA (solo jurídica) -->
    <div id="bloqueDocsEmpresa" class="card border-0 shadow-sm rounded-3 mb-3" style="display:none!important;">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-file-earmark-pdf me-1"></i>Documentos de la empresa <span class="required-star">*</span></p>
            <p class="text-muted small mb-3">Todos los documentos deben ser PDF. Máx. 5 MB c/u.</p>
            <div class="row g-3">
                <div class="col-12 col-sm-6"><?php $adjBox('ruc_empresa',            'RUC de la empresa',           'bi-file-earmark-text',   '.pdf'); ?></div>
                <div class="col-12 col-sm-6"><?php $adjBox('constitucion',            'Constitución de la compañía', 'bi-file-earmark-ruled',  '.pdf'); ?></div>
                <div class="col-12 col-sm-6"><?php $adjBox('nombramiento',            'Nombramiento',                'bi-file-earmark-person', '.pdf'); ?></div>
                <div class="col-12 col-sm-6"><?php $adjBox('aceptacion_nombramiento', 'Aceptación del nombramiento', 'bi-file-earmark-check',  '.pdf'); ?></div>
            </div>
        </div>
    </div>

    <!-- OBSERVACIONES -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <label class="form-label section-title" for="observaciones"><i class="bi bi-chat-text me-1"></i>Observaciones</label>
            <textarea class="form-control form-control-sm" name="observaciones" id="observaciones" rows="2"><?= htmlspecialchars($post['observaciones'] ?? '', ENT_QUOTES) ?></textarea>
        </div>
    </div>

    <div class="d-grid mb-5">
        <button type="submit" class="btn btn-primary btn-lg fw-semibold" id="btnEnviar">
            <i class="bi bi-send me-2"></i>Enviar Solicitud
        </button>
    </div>
</form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';
    const base = '<?= BASE_URL ?>';
    const codCiudadPost = '<?= htmlspecialchars($post['cod_ciudad'] ?? '', ENT_QUOTES) ?>';
    const codProvPost   = '<?= htmlspecialchars($post['cod_prov'] ?? '', ENT_QUOTES) ?>';

    // ── Tipo persona ───────────────────────────────────────────
    function toggleJuridica() {
        const esJ = document.querySelector('[name="tipo_persona"]:checked')?.value === 'juridica';
        document.getElementById('bloqueJuridica').style.setProperty('display', esJ ? 'block' : 'none', 'important');
        document.getElementById('bloqueDocsEmpresa').style.setProperty('display', esJ ? 'block' : 'none', 'important');
    }
    document.querySelectorAll('[name="tipo_persona"]').forEach(r => r.addEventListener('change', toggleJuridica));
    toggleJuridica();

    // ── Precio firma ───────────────────────────────────────────
    function updatePrecio() {
        const checked = document.querySelector('.tipo-firma-radio:checked');
        const box = document.getElementById('precioSeleccionado');
        if (checked && box) {
            box.classList.remove('d-none');
            document.getElementById('pvpMostrado').textContent = '$' + checked.dataset.pvp;
        }
    }
    document.querySelectorAll('.tipo-firma-radio').forEach(r => r.addEventListener('change', updatePrecio));
    updatePrecio();

    // ── Consulta SRI ───────────────────────────────────────────
    document.getElementById('btnBuscarSri')?.addEventListener('click', async () => {
        const id = document.getElementById('numero_identificacion').value.trim();
        if (!id) return;
        const icon = document.getElementById('sriIcono');
        const loader = document.getElementById('sriLoader');
        icon.classList.add('d-none');
        loader.classList.remove('d-none');
        try {
            const resp = await fetch(`${base}/solicitud-firma/sri?id=${encodeURIComponent(id)}`);
            const data = await resp.json();
            if (data.ok && data.data) {
                const d = data.data;
                if (d.nombres)   document.getElementById('nombres').value   = d.nombres;
                if (d.apellidos) document.getElementById('apellidos').value = d.apellidos;
            } else {
                alert(data.error || 'No se encontraron datos en el SRI.');
            }
        } catch { alert('Error al consultar el SRI.'); }
        finally { icon.classList.remove('d-none'); loader.classList.add('d-none'); }
    });

    // ── Ciudades ───────────────────────────────────────────────
    async function cargarCiudades(codProv, selCiudad) {
        if (!codProv) return;
        const sel = document.getElementById('cod_ciudad');
        sel.innerHTML = '<option value="">Cargando...</option>';
        try {
            const resp = await fetch(`${base}/solicitud-firma/ciudades?cod_prov=${encodeURIComponent(codProv)}`);
            const data = await resp.json();
            sel.innerHTML = '<option value="">-- Seleccione --</option>'
                + data.map(c => `<option value="${c.codigo}">${c.nombre}</option>`).join('');
            if (selCiudad) sel.value = selCiudad;
        } catch { sel.innerHTML = '<option value="">Error al cargar</option>'; }
    }
    document.getElementById('cod_prov')?.addEventListener('change', function () {
        cargarCiudades(this.value, '');
    });
    if (codProvPost) cargarCiudades(codProvPost, codCiudadPost);

    // ── Adjuntos ───────────────────────────────────────────────
    document.querySelectorAll('.adj-box').forEach(box => {
        const input = box.querySelector('input[type=file]');
        input?.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            box.classList.add('has-file');
            box.querySelector('.adj-icon').className = 'adj-icon bi bi-check-circle-fill';
            box.querySelector('.adj-preview').textContent = file.name;
        });
    });

    // ── Prevenir doble submit ──────────────────────────────────
    document.getElementById('formSolicitud')?.addEventListener('submit', function () {
        const btn = document.getElementById('btnEnviar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
        }
    });
})();
</script>
</body>
</html>
