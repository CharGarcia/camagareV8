<?php
/** @var string|null $error */
$base = BASE_URL;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | CaMaGaRe ERP</title>
    <link rel="shortcut icon" type="image/png" href="<?= rtrim(BASE_URL ?? '', '/') ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <?php require __DIR__ . '/../partials/theme-vars.php'; ?>
    <link href="/sistema/public/css/app.css" rel="stylesheet">
    <link href="/sistema/public/css/theme.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
        <div class="card-header bg-primary text-white text-center py-3">
            <h4 class="mb-0 fw-bold">CaMaGaRe ERP</h4>
            <small class="opacity-75">Iniciar sesión</small>
        </div>
        <div class="card-body">
            <?php if ($error === '1'): ?>
            <div class="alert alert-danger py-2" role="alert">
                Usuario o contraseña incorrectos.
            </div>
            <?php endif; ?>
            <?php if ($error === '2'): ?>
            <div class="alert alert-warning py-2" role="alert">
                El usuario no tiene empresas asignadas.
            </div>
            <?php endif; ?>
            <form method="POST" action="<?= $base ?>/auth/login">
                <div class="mb-3">
                    <label for="cedula" class="form-label">Cédula</label>
                    <input type="text" class="form-control" id="cedula" name="cedula" placeholder="Ingresa tu cédula" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Ingresa tu contraseña" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                </button>
                <div class="text-center">
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btn-recuperar" title="Recuperar contraseña">
                        <i class="bi bi-key"></i> Recuperar contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal recuperar contraseña -->
    <div class="modal fade" id="modalRecuperar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key"></i> Recuperar contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Ingresa el correo electrónico asociado a tu cuenta. Recibirás un enlace para restablecer tu contraseña.</p>
                    <div class="mb-3">
                        <label for="email-recuperar" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="email-recuperar" placeholder="tu@correo.com">
                        <div id="msg-recuperar" class="form-text"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-enviar-recuperar">
                        <span class="spinner-border spinner-border-sm d-none" id="spinner-recuperar"></span>
                        <span id="texto-enviar">Enviar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        var modal = new bootstrap.Modal(document.getElementById('modalRecuperar'));
        var btnRecuperar = document.getElementById('btn-recuperar');
        var btnEnviar = document.getElementById('btn-enviar-recuperar');
        var emailInput = document.getElementById('email-recuperar');
        var msgDiv = document.getElementById('msg-recuperar');
        var spinner = document.getElementById('spinner-recuperar');
        var textoEnviar = document.getElementById('texto-enviar');
        var urlSolicitar = '<?= rtrim($base ?? BASE_URL, "/") ?>/auth/solicitar-recuperar';
        var urlEnviar = '<?= rtrim($base ?? BASE_URL, "/") ?>/auth/enviar-correo-recuperar';

        btnRecuperar.addEventListener('click', function() {
            emailInput.value = '';
            msgDiv.textContent = '';
            msgDiv.className = 'form-text';
            modal.show();
        });

        btnEnviar.addEventListener('click', function() {
            var correo = emailInput.value.trim();
            if (!correo) {
                msgDiv.textContent = 'Ingresa tu correo electrónico.';
                msgDiv.className = 'form-text text-danger';
                return;
            }
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!regex.test(correo)) {
                msgDiv.textContent = 'El correo no es válido.';
                msgDiv.className = 'form-text text-danger';
                return;
            }

            btnEnviar.disabled = true;
            spinner.classList.remove('d-none');
            textoEnviar.textContent = 'Enviando...';
            msgDiv.textContent = '';
            msgDiv.className = 'form-text';

            var formData = new FormData();
            formData.append('correo', correo);

            fetch(urlSolicitar, {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.text(); })
            .then(function(text) {
                var data;
                try { data = JSON.parse(text); } catch(e) { data = { status: 'error', message: 'Error en la respuesta' }; }
                if (data.status === 'success') {
                    msgDiv.textContent = 'Enviando correo...';
                    msgDiv.className = 'form-text text-muted';
                    enviarCorreo(data.id_user, data.nombre, correo, msgDiv, modal).finally(function() {
                        btnEnviar.disabled = false;
                        spinner.classList.add('d-none');
                        textoEnviar.textContent = 'Enviar';
                    });
                } else {
                    msgDiv.textContent = data.message || 'No se encontró el correo en el sistema.';
                    msgDiv.className = 'form-text text-danger';
                    btnEnviar.disabled = false;
                    spinner.classList.add('d-none');
                    textoEnviar.textContent = 'Enviar';
                }
            })
            .catch(function(err) {
                msgDiv.textContent = 'Error de conexión. Intenta de nuevo.';
                msgDiv.className = 'form-text text-danger';
                btnEnviar.disabled = false;
                spinner.classList.add('d-none');
                textoEnviar.textContent = 'Enviar';
            });
        });

        function enviarCorreo(idUser, nombre, correo, msgEl, modalEl) {
            var formData = new FormData();
            formData.append('id_user', idUser);
            formData.append('nombre', nombre);
            formData.append('correo', correo);
            return fetch(urlEnviar, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(r) {
                return r.text().then(function(t) {
                    try { return JSON.parse(t); } catch(e) { return {}; }
                });
            })
            .catch(function() { return {}; })
            .then(function(res) {
                if (res && res.ok === true) {
                    msgEl.textContent = 'Revisa tu correo (y carpeta de spam) para restablecer la contraseña.';
                    msgEl.className = 'form-text text-success';
                    setTimeout(function() { if (modalEl) modalEl.hide(); }, 2500);
                } else {
                    msgEl.textContent = (res && res.error) || 'No se pudo enviar el correo. Verifica la configuración en Config → Correos.';
                    msgEl.className = 'form-text text-danger';
                }
            })
            .catch(function(err) {
                msgEl.textContent = 'Error de conexión. Revisa la consola (F12) o storage/logs/email_errors.log';
                msgEl.className = 'form-text text-danger';
            });
        }
    })();
    </script>
</body>
</html>
