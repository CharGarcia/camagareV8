<?php
/** @var array $theme */
/** @var string $titulo */
$body = $theme['body'] ?? [];
$primary = $theme['primary'] ?? [];
$links = $theme['links'] ?? [];
$typography = $theme['typography'] ?? [];
$borders = $theme['borders'] ?? [];
$presets = $theme['presets'] ?? [];
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);
$base = BASE_URL;
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-palette"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Personaliza colores, tipografía y apariencia del sistema.</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="<?= $base ?>/config/saveAppearance" class="card">
    <div class="card-body">
        <ul class="nav nav-tabs mb-4" id="appearanceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-colors" data-bs-toggle="tab" data-bs-target="#panel-colors" type="button">Colores</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-typography" data-bs-toggle="tab" data-bs-target="#panel-typography" type="button">Tipografía</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-borders" data-bs-toggle="tab" data-bs-target="#panel-borders" type="button">Bordes</button>
            </li>
        </ul>

        <div class="tab-content" id="appearanceTabsContent">
            <!-- Pestaña Colores -->
            <div class="tab-pane fade show active" id="panel-colors" role="tabpanel">
                <h6 class="text-muted mb-3">Color principal (navbar, botones)</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Principal</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color" id="primary_main_picker" value="<?= htmlspecialchars($primary['main'] ?? '#6eb5d0') ?>">
                            <input type="text" class="form-control" name="primary_main" id="primary_main" value="<?= htmlspecialchars($primary['main'] ?? '#6eb5d0') ?>" maxlength="7">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hover</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color" id="primary_hover_picker" value="<?= htmlspecialchars($primary['hover'] ?? '#5ca3bd') ?>">
                            <input type="text" class="form-control" name="primary_hover" value="<?= htmlspecialchars($primary['hover'] ?? '#5ca3bd') ?>" maxlength="7">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Texto sobre principal</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color" id="primary_text_picker" value="<?= htmlspecialchars($primary['text'] ?? '#ffffff') ?>">
                            <input type="text" class="form-control" name="primary_text" value="<?= htmlspecialchars($primary['text'] ?? '#ffffff') ?>" maxlength="7">
                        </div>
                    </div>
                </div>

                <h6 class="text-muted mb-3">Presets rápidos</h6>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php foreach ($presets as $presetNombre => $hex): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary preset-btn" data-hex="<?= htmlspecialchars($hex) ?>" title="<?= htmlspecialchars($presetNombre) ?>">
                        <span class="d-inline-block rounded me-1" style="width:14px;height:14px;background:<?= htmlspecialchars($hex) ?>"></span>
                        <?= htmlspecialchars(str_replace('_', ' ', $presetNombre)) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <h6 class="text-muted mb-3">Enlaces</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Color de enlaces</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color" id="links_color_picker" value="<?= htmlspecialchars($links['color'] ?? '#0d6efd') ?>">
                            <input type="text" class="form-control" name="links_color" value="<?= htmlspecialchars($links['color'] ?? '#0d6efd') ?>" maxlength="7">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Enlaces al pasar el mouse</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color" id="links_hover_picker" value="<?= htmlspecialchars($links['hover'] ?? '#0a58ca') ?>">
                            <input type="text" class="form-control" name="links_hover" value="<?= htmlspecialchars($links['hover'] ?? '#0a58ca') ?>" maxlength="7">
                        </div>
                    </div>
                </div>

                <h6 class="text-muted mb-3">Fondo (degradado)</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Inicio del degradado</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color" id="gradient_start_picker" value="<?= htmlspecialchars($body['gradient_start'] ?? '#e8f4f8') ?>">
                            <input type="text" class="form-control" name="gradient_start" value="<?= htmlspecialchars($body['gradient_start'] ?? '#e8f4f8') ?>" maxlength="7">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fin del degradado</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color" id="gradient_end_picker" value="<?= htmlspecialchars($body['gradient_end'] ?? '#f0f7fa') ?>">
                            <input type="text" class="form-control" name="gradient_end" value="<?= htmlspecialchars($body['gradient_end'] ?? '#f0f7fa') ?>" maxlength="7">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ángulo</label>
                        <select class="form-select form-select-sm" name="gradient_angle">
                            <?php foreach (['90deg', '135deg', '180deg', '225deg', '270deg'] as $ang): ?>
                            <option value="<?= $ang ?>" <?= ($body['gradient_angle'] ?? '135deg') === $ang ? 'selected' : '' ?>><?= $ang ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Pestaña Tipografía -->
            <div class="tab-pane fade" id="panel-typography" role="tabpanel">
                <h6 class="text-muted mb-3">Tamaño de fuente base</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Tamaño</label>
                        <select class="form-select form-select-sm" name="font_size_base">
                            <?php
                            $sizes = ['0.8125rem' => '13px (pequeño)', '0.875rem' => '14px', '0.9375rem' => '15px', '1rem' => '16px', '1.0625rem' => '17px', '1.125rem' => '18px'];
                            $currentSize = $typography['font_size_base'] ?? '0.9375rem';
                            foreach ($sizes as $val => $label):
                            ?>
                            <option value="<?= $val ?>" <?= $currentSize === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Familia de fuente</label>
                        <select class="form-select form-select-sm" name="font_family">
                            <?php
                            $fonts = [
                                'system-ui, -apple-system, sans-serif' => 'Sistema',
                                'Inter, system-ui, sans-serif' => 'Inter',
                                'Segoe UI, Tahoma, sans-serif' => 'Segoe UI',
                                'Georgia, serif' => 'Georgia',
                                'monospace' => 'Monospace',
                            ];
                            $currentFont = $typography['font_family'] ?? 'system-ui, -apple-system, sans-serif';
                            foreach ($fonts as $val => $label):
                            ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= $currentFont === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Pestaña Bordes -->
            <div class="tab-pane fade" id="panel-borders" role="tabpanel">
                <h6 class="text-muted mb-3">Radio de bordes redondeados</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">General (botones, inputs)</label>
                        <select class="form-select form-select-sm" name="radius">
                            <?php
                            $radii = ['0' => '0 (cuadrado)', '0.2rem' => '3px', '0.25rem' => '4px', '0.375rem' => '6px (default)', '0.5rem' => '8px', '0.75rem' => '12px'];
                            $current = $borders['radius'] ?? '0.375rem';
                            foreach ($radii as $val => $label):
                            ?>
                            <option value="<?= $val ?>" <?= $current === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pequeño</label>
                        <select class="form-select form-select-sm" name="radius_sm">
                            <?php
                            $currentSm = $borders['radius_sm'] ?? '0.25rem';
                            foreach (['0' => '0', '0.15rem' => '2px', '0.2rem' => '3px', '0.25rem' => '4px'] as $val => $label):
                            ?>
                            <option value="<?= $val ?>" <?= $currentSm === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Grande (tarjetas)</label>
                        <select class="form-select form-select-sm" name="radius_lg">
                            <?php
                            $currentLg = $borders['radius_lg'] ?? '0.5rem';
                            foreach (['0' => '0', '0.375rem' => '6px', '0.5rem' => '8px', '0.75rem' => '12px', '1rem' => '16px'] as $val => $label):
                            ?>
                            <option value="<?= $val ?>" <?= $currentLg === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">
        <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar cambios</button>
            <a href="<?= $base ?>/config/restoreTheme" class="btn btn-outline-warning" onclick="return confirm('¿Restaurar todos los valores por defecto?');">
                <i class="bi bi-arrow-counterclockwise"></i> Restaurar por defecto
            </a>
        </div>
    </div>
</form>

<script>
(function() {
    var pairs = [
        ['primary_main_picker', 'primary_main'],
        ['primary_hover_picker', 'primary_hover'],
        ['primary_text_picker', 'primary_text'],
        ['links_color_picker', 'links_color'],
        ['links_hover_picker', 'links_hover'],
        ['gradient_start_picker', 'gradient_start'],
        ['gradient_end_picker', 'gradient_end']
    ];
    pairs.forEach(function(p) {
        var picker = document.getElementById(p[0]);
        var textInput = document.querySelector('input[name="' + p[1] + '"]');
        if (picker && textInput) {
            picker.addEventListener('input', function() { textInput.value = this.value; });
            textInput.addEventListener('input', function() { if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) picker.value = this.value; });
        }
    });

    document.querySelectorAll('.preset-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var hex = this.getAttribute('data-hex');
            var main = document.getElementById('primary_main');
            var picker = document.getElementById('primary_main_picker');
            if (main) main.value = hex;
            if (picker) picker.value = hex;
        });
    });
})();
</script>
