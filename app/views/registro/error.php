<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Invitación - CaMaGaRe</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
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

        .error-card {
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.1), 0 8px 10px -6px rgba(220, 38, 38, 0.1);
            width: 100%;
            max-width: 450px;
            padding: 3rem 2rem;
            text-align: center;
            border: 1px solid rgba(254, 202, 202, 0.5);
        }

        .error-icon {
            font-size: 5rem;
            color: #ef4444;
            margin-bottom: 1.5rem;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .btn-retry {
            background-color: #ef4444;
            border: none;
            border-radius: 0.75rem;
            padding: 0.875rem 2rem;
            font-weight: 600;
            color: #ffffff;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-retry:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);
            color: #ffffff;
        }
    </style>
</head>

<body>
    <div class="error-card">
        <div class="error-icon">
            <i class="bi bi-exclamation-octagon-fill"></i>
        </div>
        <h1>Acceso Denegado</h1>
        <p><?= htmlspecialchars($mensaje ?? 'El enlace de invitación no es válido, ha expirado o ya fue utilizado anteriormente.', ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= BASE_URL ?>/" class="btn-retry">Volver al Inicio</a>
    </div>
</body>

</html>
