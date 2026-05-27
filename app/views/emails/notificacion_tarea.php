<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Notificación de Tarea</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
        .header { background-color: #f8f9fa; padding: 15px; text-align: center; border-bottom: 2px solid #0056b3; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #0056b3; }
        .content { margin-bottom: 20px; }
        .footer { text-align: center; font-size: 0.85em; color: #777; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px; }
        .info-box { background-color: #f1f8ff; border-left: 4px solid #0056b3; padding: 10px 15px; margin-bottom: 15px; }
        .cancel-box { background-color: #fff5f5; border-left: 4px solid #dc3545; padding: 10px 15px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Gestión de Obligaciones</h2>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong><?= htmlspecialchars($data['cliente_nombre']) ?></strong>,</p>

            <?php if ($data['estado'] === 'cancelada'): ?>
                <div class="cancel-box">
                    <p>Le informamos que la actividad <strong><?= htmlspecialchars($data['obligacion_nombre']) ?></strong> obligada a presentarse o cumplirse en fecha <strong><?= htmlspecialchars($data['fecha_tarea']) ?></strong>, <strong>NO se ha realizado</strong>.</p>
                </div>
                <p><strong>Motivo de la cancelación:</strong><br>
                <?= nl2br(htmlspecialchars($data['motivo_cancelacion'])) ?></p>
                <p>Por lo tanto, le comunicamos que no se va a hacer en el futuro esta tarea.</p>

            <?php else: // realizada_continua O realizada_finalizada ?>
                <div class="info-box">
                    <p>Le informamos que la obligación <strong><?= htmlspecialchars($data['obligacion_nombre']) ?></strong> a presentarse o cumplirse en fecha <strong><?= htmlspecialchars($data['fecha_tarea']) ?></strong>, fue <strong>REALIZADA</strong> el <strong><?= htmlspecialchars($data['fecha_realizacion']) ?></strong>.</p>
                </div>

                <p><strong>Resumen de lo realizado:</strong><br>
                <?= nl2br(htmlspecialchars($data['resumen'])) ?></p>

                <p>Se adjuntan los documentos comprobantes en este correo electrónico.</p>
                
                <p><strong>Responsables a cargo de esta gestión:</strong><br>
                <?= htmlspecialchars($data['responsables_str'] ?: 'No asignados') ?></p>

                <?php if ($data['estado'] === 'realizada_continua'): ?>
                    <p>Adicionalmente, le informamos que esta tarea ha sido reprogramada para realizarse nuevamente o dar continuidad en la siguiente fecha: <strong><?= htmlspecialchars($data['proxima_fecha']) ?></strong>.</p>
                <?php elseif ($data['estado'] === 'realizada_finalizada'): ?>
                    <p>Adicionalmente informamos que esta obligación <strong>ya no se va a seguir haciendo a futuro</strong> ya que la tarea general se finalizó de forma permanente.</p>
                <?php endif; ?>
            <?php endif; ?>

            <p>Agradecemos su atención.</p>
        </div>

        <div class="footer">
            <p>Este es un correo generado automáticamente por el sistema. Por favor no responda directamente a este mensaje.</p>
        </div>
    </div>
</body>
</html>
