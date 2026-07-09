<?php

/** @var string $titulo @var array $perm @var string $rutaModulo @var string $atrasoModo */

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>

<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-gear-fill me-2 text-primary"></i> <?= $h($titulo) ?></h5>
    <a href="<?= $urlBase ?>/jornadas" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-calendar-check me-1"></i> Jornadas</a>
</div>

<div class="card border-0 shadow-sm rounded-3" style="max-width: 640px;">
    <div class="card-header bg-white py-2 px-3">
        <span class="fw-bold"><i class="bi bi-clock-history me-1 text-primary"></i> Tratamiento de atrasos</span>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Define cómo se trasladan los <b>minutos de atraso</b> acumulados al generar las
            Novedades del período (paso «Generar Novedades» en Jornadas). Las <b>faltas</b> y
            <b>horas extra</b> siempre se generan; esto solo afecta a los atrasos.
        </p>

        <div class="list-group mb-3">
            <label class="list-group-item d-flex gap-3 align-items-start">
                <input class="form-check-input mt-1" type="radio" name="atrasoModo" value="informativo" <?= $atrasoModo === 'informativo' ? 'checked' : '' ?>>
                <span>
                    <span class="fw-semibold d-block">Solo informativo</span>
                    <span class="small text-muted">No se genera ninguna novedad por atrasos. Solo quedan visibles en Jornadas.</span>
                </span>
            </label>
            <label class="list-group-item d-flex gap-3 align-items-start">
                <input class="form-check-input mt-1" type="radio" name="atrasoModo" value="descuento" <?= $atrasoModo === 'descuento' ? 'checked' : '' ?>>
                <span>
                    <span class="fw-semibold d-block">Descuento en dinero</span>
                    <span class="small text-muted">Se crea una novedad <b>Descuento</b>. Monto = horas de atraso × (sueldo base ÷ 240).</span>
                </span>
            </label>
            <label class="list-group-item d-flex gap-3 align-items-start">
                <input class="form-check-input mt-1" type="radio" name="atrasoModo" value="dias" <?= $atrasoModo === 'dias' ? 'checked' : '' ?>>
                <span>
                    <span class="fw-semibold d-block">Fracción de día no laborado</span>
                    <span class="small text-muted">Se crea una novedad <b>Días no laborados</b> aparte. Valor = (horas de atraso ÷ 8).</span>
                </span>
            </label>
        </div>

        <?php if ($perm['actualizar']): ?>
        <button class="btn btn-primary btn-sm px-4" onclick="guardarConfigAsistencia()"><i class="bi bi-check-lg me-1"></i> Guardar</button>
        <?php else: ?>
        <div class="alert alert-secondary small mb-0">No tienes permiso para modificar esta configuración.</div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlBase ?>';
    window.guardarConfigAsistencia = function () {
        const sel = document.querySelector('input[name="atrasoModo"]:checked');
        if (!sel) return;
        const fd = new FormData();
        fd.append('atraso_modo', sel.value);
        fetch(`${urlBase}/guardarConfiguracionAjax`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
                if (j.ok) { if (window.Swal) Swal.fire({icon:'success',title:j.msg,timer:1200,showConfirmButton:false}); }
                else { if (window.Swal) Swal.fire({icon:'error',title:'Error',text:j.error}); else alert(j.error); }
            })
            .catch(() => { if (window.Swal) Swal.fire({icon:'error',title:'Error de red'}); });
    };
})();
</script>
