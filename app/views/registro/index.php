<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?? 'Completar Registro' ?> - CaMaGaRe</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .registro-card {
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .card-header-registro {
            background: #1e293b;
            padding: 2.5rem 2rem;
            text-align: center;
            color: #ffffff;
        }

        .card-header-registro h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .card-header-registro p {
            color: #94a3b8;
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        .card-body-registro {
            padding: 2.5rem 2rem;
        }

        .form-label {
            font-weight: 500;
            color: #475569;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 0.9375rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            background-color: #ffffff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: 0;
        }

        .input-group-text {
            border-radius: 0.75rem 0 0 0.75rem;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .form-control.with-icon {
            border-left: none;
            border-radius: 0 0.75rem 0.75rem 0;
        }

        .btn-primary-registro {
            background-color: var(--primary-color);
            border: none;
            border-radius: 0.75rem;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            transition: all 0.2s;
            margin-top: 1rem;
        }

        .btn-primary-registro:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-primary-registro:active {
            transform: translateY(0);
        }

        .alert-custom {
            border-radius: 0.75rem;
            font-size: 0.875rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2rem;
            opacity: 0.6;
            font-size: 0.75rem;
            color: #64748b;
        }

        .footer-logo img {
            height: 20px;
            margin-right: 8px;
        }

        /* Micro-animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .registro-card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body>
    <div class="registro-card">
        <div class="card-header-registro">
            <h1>CaMaGaRe</h1>
            <p>Completa tu registro de usuario</p>
        </div>
        <div class="card-body-registro">
            <div id="alert-container"></div>

            <form id="form-registro">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-4">
                    <label class="form-label">Nombre Completo *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="nombre" class="form-control with-icon" value="" placeholder="Ej: Juan Pérez" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Identificación (Cédula) *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                        <input type="text" name="cedula" class="form-control with-icon" placeholder="Ej: 1712345678" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Teléfono</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="text" name="telefono" class="form-control with-icon" placeholder="Ej: 0998765432">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Nueva Contraseña *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control with-icon" placeholder="Mínimo 4 caracteres" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Confirmar Contraseña *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                        <input type="password" name="confirmar_password" class="form-control with-icon" placeholder="Repite la contraseña" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-primary-registro" id="btn-submit">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span class="btn-text">Finalizar Registro</span>
                </button>
            </form>

            <div class="footer-logo">
                &copy; <?= date('Y') ?> CaMaGaRe System
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('form-registro').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const btn = document.getElementById('btn-submit');
            const spinner = btn.querySelector('.spinner-border');
            const btnText = btn.querySelector('.btn-text');
            const alertContainer = document.getElementById('alert-container');

            // Reset UI
            alertContainer.innerHTML = '';
            btn.disabled = true;
            spinner.classList.remove('d-none');
            btnText.innerText = 'Procesando...';

            const formData = new FormData(form);

            fetch('<?= BASE_URL ?>/registro/completar', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.ok) {
                        alertContainer.innerHTML = `
                        <div class="alert alert-success alert-custom shadow-sm border-0 d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <div>${data.msg}</div>
                        </div>
                    `;
                        form.innerHTML = `
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                            <h3 class="mt-4 font-weight-bold">¡Bienvenido!</h3>
                            <p class="text-muted mb-4">Tu cuenta ha sido activada correctamente.</p>
                            <a href="<?= BASE_URL ?>/" class="btn btn-primary-registro">Ir al Login</a>
                        </div>
                    `;
                    } else {
                        alertContainer.innerHTML = `
                        <div class="alert alert-danger alert-custom shadow-sm border-0 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>${data.error}</div>
                        </div>
                    `;
                        btn.disabled = false;
                        spinner.classList.add('d-none');
                        btnText.innerText = 'Finalizar Registro';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alertContainer.innerHTML = `
                    <div class="alert alert-danger alert-custom shadow-sm border-0 d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div>Hubo un error al procesar la solicitud.</div>
                    </div>
                `;
                    btn.disabled = false;
                    spinner.classList.add('d-none');
                    btnText.innerText = 'Finalizar Registro';
                });
        });
    </script>
</body>

</html>
