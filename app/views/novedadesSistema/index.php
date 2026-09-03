<?php
/**
 * Lista completa de novedades vigentes ("Ver todas las novedades").
 * Cualquier usuario autenticado. Las no leídas van primero, resaltadas.
 */
$base = rtrim(BASE_URL ?? '', '/');
$novedades = $novedades ?? [];
$pendientes = (int) ($pendientes ?? 0);
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<script>document.body.classList.add('cmg-no-app-shell'); window.CMG_NOVEDADES_NO_AUTO = true;</script>
<style>
    .nvl-card { border: 1px solid #e3ebf0; border-radius: .5rem; background: #fff; }
    .nvl-card.nvl-pendiente { border-left: 4px solid var(--cmg-primary, #6eb5d0); }
    .nvl-chip { font-size: .66rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; border-radius: 999px; padding: 2px 8px; }
    .nvl-contenido { color: #4b5c68; max-width: 70ch; }
    .nvl-contenido p { margin-bottom: .5rem; }
    .nvl-contenido h2, .nvl-contenido h3 { font-size: 1rem; font-weight: 600; margin: .7rem 0 .3rem; }
    .nv-chip-nuevo      { background: #e3f5ec; color: #2f9e6b; }
    .nv-chip-mejora     { background: #e2f1f8; color: #2b8fb8; }
    .nv-chip-aviso      { background: #fbf1da; color: #c98a11; }
    .nv-chip-correccion { background: #eee7f9; color: #8a5fc9; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-megaphone-fill me-2 text-primary"></i><?= $e($titulo ?? 'Novedades del sistema') ?></h5>
        <p class="text-muted mb-0 small">
            <?php if ($pendientes > 0): ?>
                Tienes <b><?= $pendientes ?></b> <?= $pendientes === 1 ? 'novedad sin leer' : 'novedades sin leer' ?>.
            <?php else: ?>
                Estás al día con las novedades del sistema.
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($pendientes > 0): ?>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" id="nvlMarcarTodas">
                <i class="bi bi-check2-all me-1"></i> Marcar todas como leídas
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($novedades)): ?>
    <div class="nvl-card p-5 text-center text-muted">
        <i class="bi bi-megaphone fs-2 d-block mb-2"></i>
        Aún no hay novedades publicadas.
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-3" style="max-width: 900px;">
        <?php foreach ($novedades as $n): ?>
            <article class="nvl-card p-3 p-md-4<?= empty($n['leida']) ? ' nvl-pendiente' : '' ?>">
                <div class="d-flex align-items-center gap-2 small text-muted mb-2 flex-wrap">
                    <span class="nvl-chip nv-chip-<?= $e($n['tipo']) ?>"><?= $e($n['tipo_label']) ?></span>
                    <span>Publicado el <?= $e($n['fecha']) ?></span>
                    <?php if (!empty($n['modulo'])): ?><span>·</span><span><?= $e($n['modulo']) ?></span><?php endif; ?>
                    <?php if (empty($n['leida'])): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-auto">Sin leer</span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-semibold mb-2"><?= $e($n['titulo']) ?></h5>
                <div class="nvl-contenido"><?= $n['contenido'] /* HTML saneado al guardar */ ?></div>
                <div class="d-flex flex-wrap gap-3 mt-2">
                    <?php if (!empty($n['url_modulo'])): ?>
                        <a href="<?= $e($n['url_modulo']) ?>" class="small fw-medium text-decoration-none">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Abrir <?= $e($n['modulo'] ?: 'el módulo') ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($n['url_manual'])): ?>
                        <a href="<?= $e($n['url_manual']) ?>" target="_blank" rel="noopener" class="small fw-medium text-decoration-none">
                            <i class="bi bi-journal-bookmark me-1"></i>Ver la guía en el Manual del sistema
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($n['url_enlace'])): ?>
                        <a href="<?= $e($n['url_enlace']) ?>"<?= !empty($n['enlace_externo']) ? ' target="_blank" rel="noopener"' : '' ?> class="small fw-medium text-decoration-none">
                            <i class="bi bi-link-45deg me-1"></i>Abrir enlace
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($n['adjuntos'])): ?>
                    <div class="mt-3 pt-2 border-top">
                        <div class="small text-muted mb-1"><i class="bi bi-paperclip me-1"></i>Archivos adjuntos</div>
                        <div class="d-flex flex-column gap-1">
                            <?php foreach ($n['adjuntos'] as $a): ?>
                                <a href="<?= $e($a['url']) ?>" class="d-flex align-items-center text-decoration-none small" title="Descargar">
                                    <?php if (!empty($a['es_imagen'])): ?>
                                        <img src="<?= $e($a['url_vista']) ?>" alt="" class="rounded border me-2" style="width:34px;height:34px;object-fit:cover;">
                                    <?php else: ?>
                                        <i class="bi <?= $e($a['icono']) ?> fs-5 me-2"></i>
                                    <?php endif; ?>
                                    <span class="text-truncate"><?= $e($a['nombre']) ?></span>
                                    <?php if (!empty($a['tamano'])): ?><span class="text-muted ms-2 flex-shrink-0"><?= $e($a['tamano']) ?></span><?php endif; ?>
                                    <i class="bi bi-download ms-2 text-muted flex-shrink-0"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(function () {
    var btn = document.getElementById('nvlMarcarTodas');
    if (!btn) return;
    btn.addEventListener('click', function () {
        btn.disabled = true;
        fetch('<?= $base ?>/novedades-sistema/marcar-leidas', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: ''
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.ok) throw new Error((d && d.error) || 'No se pudo registrar.');
            window.location.reload();
        }).catch(function (e) {
            btn.disabled = false;
            if (window.Swal) Swal.fire('No se pudo guardar', e.message, 'error');
        });
    });
})();
</script>
