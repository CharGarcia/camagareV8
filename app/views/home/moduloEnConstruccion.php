<?php
/** @var string $titulo */
$base = BASE_URL ?? '';
?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-tools display-4 text-warning mb-3"></i>
        <h4 class="card-title">Módulo en desarrollo</h4>
        <p class="card-text text-muted">
            Este módulo se está migrando al nuevo sistema. Estará disponible pronto.
        </p>
        <a href="<?= rtrim($base, '/') ?>/home/index" class="btn btn-primary mt-3">
            <i class="bi bi-house"></i> Volver al inicio
        </a>
    </div>
</div>
