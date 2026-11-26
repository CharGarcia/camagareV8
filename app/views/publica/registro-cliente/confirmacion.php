<?php
/**
 * Confirmación del QR de registro de clientes.
 * @var array|null $cfg
 * @var array      $res    accion ('creado'|'actualizado'), nombre
 * @var string     $token
 */
$tituloPagina = 'Datos registrados';
$actualizado  = ($res['accion'] ?? '') === 'actualizado';
require __DIR__ . '/_cabecera.php';
?>
    <div class="card border-0 shadow-sm p-4 text-center">
        <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
        <h4 class="fw-bold mt-3 mb-2"><?= $actualizado ? '¡Datos actualizados!' : '¡Registro completo!' ?></h4>
        <p class="text-secondary mb-0">
            Gracias, <strong><?= htmlspecialchars((string) ($res['nombre'] ?? ''), ENT_QUOTES) ?></strong>.
            <?= $actualizado
                ? 'Tus datos quedaron actualizados.'
                : 'Tus datos quedaron registrados.' ?>
            Ya puedes pedir tu factura en caja.
        </p>
        <a href="<?= rtrim(BASE_URL, '/') . '/registro-cliente/' . rawurlencode($token) ?>" class="btn btn-outline-secondary btn-sm mt-4">
            <i class="bi bi-pencil-square me-1"></i>¿Te equivocaste? Corregir mis datos
        </a>
    </div>
</div>
</body>
</html>
