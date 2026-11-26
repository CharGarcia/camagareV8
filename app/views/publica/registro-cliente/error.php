<?php
/**
 * Error del QR de registro de clientes (enlace inválido, desactivado o fallo).
 * @var string $mensaje
 */
$cfg = $cfg ?? null;
$tituloPagina = 'Registro de clientes';
require __DIR__ . '/_cabecera.php';
?>
    <div class="card border-0 shadow-sm p-4 text-center">
        <i class="bi bi-qr-code-scan text-secondary" style="font-size:4rem"></i>
        <h5 class="fw-bold mt-3 mb-2">Enlace no disponible</h5>
        <p class="text-secondary mb-0"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></p>
    </div>
</div>
</body>
</html>
