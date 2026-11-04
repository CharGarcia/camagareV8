<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var bool $instalado @var ?array $config
 *  @var array $bodegas @var array $metodos @var string $base */
$urlBase      = rtrim($base, '/') . '/' . $rutaModulo;
$cfg          = $config ?? [];
$puedeGuardar = !empty($perm['actualizar']);
$sel = fn(string $campo, int $id): string => (int) ($cfg[$campo] ?? 0) === $id ? 'selected' : '';
$chk = fn(string $campo): string => !empty($cfg[$campo]) && $cfg[$campo] !== 'f' ? 'checked' : '';
$selectBodega = function (string $campo, string $label, string $ayuda) use ($bodegas, $sel): string {
    $html = '<div class="col-md-4"><label class="form-label small fw-bold d-block" for="pcfg-' . $campo . '">' . $label . '</label>'
          . '<select class="form-select form-select-sm" id="pcfg-' . $campo . '" name="' . $campo . '"><option value="">— Sin definir —</option>';
    foreach ($bodegas as $b) {
        $html .= '<option value="' . (int) $b['id'] . '" ' . $sel($campo, (int) $b['id']) . '>'
              . htmlspecialchars((string) $b['nombre'])
              . (!empty($b['establecimiento_codigo']) ? ' — Est. ' . htmlspecialchars((string) $b['establecimiento_codigo']) : '')
              . '</option>';
    }
    return $html . '</select><div class="form-text">' . $ayuda . '</div></div>';
};
?>
<style>
    .pcfg .form-text { font-size: .72rem; }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3 pcfg" id="modulo-produccion_configuracion">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-sliders text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
        <?php if ($instalado && $puedeGuardar): ?>
            <button type="button" id="pcfg-btn-guardar" class="btn btn-primary btn-sm px-4 shadow-sm" onclick="window.PCFG_guardar()">
                <i class="bi bi-check-lg me-1"></i> Guardar
            </button>
        <?php endif; ?>
    </div>

    <?php if (!$instalado): ?>
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>
            El módulo Producción aún no está instalado en la base de datos: falta aplicar <code>database/2026-11-03_produccion.sql</code>.
        </div>
    <?php else: ?>
        <?php if (!$config): ?>
            <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>
                Producción aún no está configurado en esta empresa. Revise las opciones y pulse <b>Guardar</b>: con eso el módulo queda activo
                y ya puede crear fórmulas y órdenes de producción. Nada cambia en el resto del sistema.
            </div>
        <?php endif; ?>

        <form id="pcfg-form" onsubmit="event.preventDefault();">
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-boxes me-2 text-primary"></i>Bodegas por defecto</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <?= $selectBodega('id_bodega_insumos', 'Bodega de insumos', 'De dónde salen las materias primas al consumir.') ?>
                        <?= $selectBodega('id_bodega_terminado', 'Bodega de producto terminado', 'A dónde entra lo producido.') ?>
                        <?= $selectBodega('id_bodega_cuarentena', 'Bodega de cuarentena', 'Solo si usa cuarentena: lo producido entra aquí hasta que Calidad lo libere con una transferencia.') ?>
                    </div>
                    <div class="form-text mt-2">Son bodegas normales del módulo Bodegas. Cada orden puede elegir otras.</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-toggles me-2 text-primary"></i>Funciones</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pcfg-exigir_lote_insumos" name="exigir_lote_insumos" value="1" <?= $chk('exigir_lote_insumos') ?>>
                                <label class="form-check-label small fw-bold" for="pcfg-exigir_lote_insumos">Exigir lote en los insumos</label>
                            </div>
                            <div class="form-text">Al consumir, cada insumo que maneja lotes debe indicar de qué lote sale (alimentos, cosméticos, farmacéuticos). Si se apaga, el sistema toma el lote que vence primero.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pcfg-usar_cuarentena" name="usar_cuarentena" value="1" <?= $chk('usar_cuarentena') ?>>
                                <label class="form-check-label small fw-bold" for="pcfg-usar_cuarentena">Usar cuarentena</label>
                            </div>
                            <div class="form-text">Lo producido entra a la bodega de cuarentena en vez de la de producto terminado.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pcfg-permitir_sin_stock" name="permitir_sin_stock" value="1" <?= $chk('permitir_sin_stock') ?>>
                                <label class="form-check-label small fw-bold" for="pcfg-permitir_sin_stock">Permitir consumir sin stock</label>
                            </div>
                            <div class="form-text">Apagado por defecto. Si se enciende, el insumo puede quedar en negativo (por ejemplo, cuando la compra se registra después).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold d-block" for="pcfg-metodo_reparto">Reparto del costo entre coproductos</label>
                            <select class="form-select form-select-sm" id="pcfg-metodo_reparto" name="metodo_reparto">
                                <?php foreach ($metodos as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= ($cfg['metodo_reparto'] ?? 'valor_venta') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo aplica cuando una fórmula tiene coproductos (camal, piladora, aserradero). Cada fórmula puede usar otro.</div>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    <?php endif; ?>
</div>

<script>
    window.PCFG_URL_BASE = '<?= $urlBase ?>';
    window.PCFG_PUEDE_GUARDAR = <?= $puedeGuardar ? 'true' : 'false' ?>;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/produccion_configuracion.js?v=<?= asset_ver('/js/modulos/produccion_configuracion.js') ?>"></script>
