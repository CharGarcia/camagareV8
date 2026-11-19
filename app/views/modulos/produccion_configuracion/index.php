<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var bool $instalado @var ?array $config
 *  @var array $bodegas @var array $metodos @var array $permBodegas @var array $establecimientos @var string $base */
$urlBase      = rtrim($base, '/') . '/' . $rutaModulo;
$cfg          = $config ?? [];
$puedeGuardar = !empty($perm['actualizar']);
$sel = fn(string $campo, int $id): string => (int) ($cfg[$campo] ?? 0) === $id ? 'selected' : '';
$chk = fn(string $campo): string => !empty($cfg[$campo]) && $cfg[$campo] !== 'f' ? 'checked' : '';
// Botón «+» junto a cada bodega: abre el modal de Bodegas y deja la nueva elegida (CLAUDE.md §9, Crear al vuelo).
$puedeCrearBodega = !empty($permBodegas['crear']);
$selectBodega = function (string $campo, string $label, string $ayuda) use ($bodegas, $sel, $puedeCrearBodega): string {
    $html = '<div class="col-md-4"><label class="form-label small fw-bold d-block" for="pcfg-' . $campo . '">' . $label . '</label>'
          . ($puedeCrearBodega ? '<div class="input-group input-group-sm">' : '')
          . '<select class="form-select form-select-sm pcfg-bodega" id="pcfg-' . $campo . '" name="' . $campo . '"><option value="">— Sin definir —</option>';
    foreach ($bodegas as $b) {
        $html .= '<option value="' . (int) $b['id'] . '" ' . $sel($campo, (int) $b['id']) . '>'
              . htmlspecialchars((string) $b['nombre'])
              . (!empty($b['establecimiento_codigo']) ? ' — Est. ' . htmlspecialchars((string) $b['establecimiento_codigo']) : '')
              . '</option>';
    }
    $html .= '</select>';
    if ($puedeCrearBodega) {
        $html .= '<button type="button" class="btn btn-outline-primary" title="Crear una bodega nueva" onclick="window.PCFG_nuevaBodega(\'' . $campo . '\')">'
               . '<i class="bi bi-plus-lg"></i></button></div>';
    }
    return $html . '<div class="form-text">' . $ayuda . '</div></div>';
};
// Ícono «?» con la explicación completa en una ventanita (popover). Va FUERA del <label>: un clic en el
// ícono no debe encender ni apagar el interruptor. El texto es HTML fijo de esta vista (no del usuario).
$ayuda = fn(string $titulo, string $html): string =>
    '<span class="pcfg-ayuda text-primary ms-1" role="button" tabindex="0" aria-label="Ayuda: ' . htmlspecialchars($titulo) . '"'
    . ' data-bs-toggle="popover" data-bs-html="true" data-bs-placement="top"'
    . ' data-bs-title="' . htmlspecialchars($titulo) . '" data-bs-content="' . htmlspecialchars($html) . '">'
    . '<i class="bi bi-question-circle-fill"></i></span>';
?>
<style>
    .pcfg .form-text { font-size: .72rem; }
    .pcfg-ayuda { cursor: pointer; font-size: .85rem; }
    /* z-index: por encima del navbar (5000), submenús (5065) y modales (5060); debajo de SweetAlert (10000). */
    .pcfg-popover { --bs-popover-max-width: 380px; --bs-popover-font-size: .78rem; --bs-popover-zindex: 9000; z-index: 9000; }
    .pcfg-popover ul { padding-left: 1.1rem; margin-bottom: .35rem; }
    .pcfg-popover p { margin-bottom: .35rem; }
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
                    <div class="form-text mt-2">Son bodegas normales del módulo Bodegas. Cada orden puede elegir otras.<?= $puedeCrearBodega ? ' Con <i class="bi bi-plus-lg"></i> crea una bodega sin salir de esta pantalla.' : '' ?></div>
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
                                <?= $ayuda('Exigir lote en los insumos',
                                    '<p>Qué pasa cuando consume un insumo que maneja lotes y no indica de qué lote sale.</p>'
                                    . '<ul><li><b>Encendido:</b> no deja registrar el consumo hasta que elija el lote. Así sabe qué lote de materia prima terminó en qué lote producido (necesario para un retiro del mercado en alimentos, cosméticos o farmacéuticos).</li>'
                                    . '<li><b>Apagado:</b> el sistema toma solo el lote que <b>vence primero</b>; si no alcanza, sigue con el siguiente.</li></ul>'
                                    . '<p class="mb-0">Aunque esté apagado, una línea de la fórmula puede exigir lote por su cuenta. Este ajuste lo vuelve obligatorio para todos los insumos.</p>') ?>
                            </div>
                            <div class="form-text">Al consumir, cada insumo que maneja lotes debe indicar de qué lote sale (alimentos, cosméticos, farmacéuticos). Si se apaga, el sistema toma el lote que vence primero.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pcfg-usar_cuarentena" name="usar_cuarentena" value="1" <?= $chk('usar_cuarentena') ?>>
                                <label class="form-check-label small fw-bold" for="pcfg-usar_cuarentena">Usar cuarentena</label>
                                <?= $ayuda('Usar cuarentena',
                                    '<p>A qué bodega entra lo producido.</p>'
                                    . '<ul><li><b>Encendido:</b> entra a la <b>bodega de cuarentena</b> y queda ahí, separado de lo que se puede vender, hasta que alguien (por ejemplo, Calidad) lo revisa y lo pasa a producto terminado con una <b>Transferencia de inventario</b>.</li>'
                                    . '<li><b>Apagado:</b> entra directo a la bodega de producto terminado.</li></ul>'
                                    . '<p class="mb-0">Si lo enciende sin elegir bodega de cuarentena, se usa la de producto terminado. Cada orden puede cambiar su bodega de destino.</p>') ?>
                            </div>
                            <div class="form-text">Lo producido entra a la bodega de cuarentena en vez de la de producto terminado.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pcfg-permitir_sin_stock" name="permitir_sin_stock" value="1" <?= $chk('permitir_sin_stock') ?>>
                                <label class="form-check-label small fw-bold" for="pcfg-permitir_sin_stock">Permitir consumir sin stock</label>
                                <?= $ayuda('Permitir consumir sin stock',
                                    '<p>Si se puede consumir más insumo del que hay en la bodega.</p>'
                                    . '<ul><li><b>Apagado</b> (recomendado): si no hay stock suficiente, el consumo se rechaza con un aviso de cuánto hay y cuánto se pide.</li>'
                                    . '<li><b>Encendido:</b> el consumo pasa y el insumo queda en <b>negativo</b>. Útil cuando la planta produce antes de registrar la compra de la materia prima.</li></ul>'
                                    . '<p class="mb-0">Mientras el insumo esté en negativo, su costo puede no ser el real hasta que entre la compra. Úselo solo si de verdad le pasa eso.</p>') ?>
                            </div>
                            <div class="form-text">Apagado por defecto. Si se enciende, el insumo puede quedar en negativo (por ejemplo, cuando la compra se registra después).</div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label small fw-bold mb-0" for="pcfg-metodo_reparto">Reparto del costo entre coproductos</label>
                                <?= $ayuda('Reparto del costo entre coproductos',
                                    '<p>Solo importa cuando una fórmula saca <b>varios productos valiosos a la vez</b> (coproductos): un camal saca lomo, costilla y vísceras; una piladora, arroz entero y partido. El costo de la orden (materia prima, mano de obra y costos indirectos) se reparte entre ellos así:</p>'
                                    . '<ul><li><b>Valor de venta relativo</b> (por defecto): según cantidad × precio de venta. El producto que más vale absorbe más costo.</li>'
                                    . '<li><b>Unidades físicas:</b> según la cantidad (kg, litros, unidades). 60 kg de lomo y 40 kg de costilla = 60 % y 40 % del costo.</li>'
                                    . '<li><b>% fijo de la fórmula:</b> según el porcentaje que puso en cada salida de la fórmula.</li></ul>'
                                    . '<p>Antes del reparto, los <b>subproductos</b> (cascarilla, aserrín…) se valoran a su precio neto de venta y ese valor se descuenta del costo.</p>'
                                    . '<p class="mb-0">Si el método elegido no tiene datos (sin precios o sin porcentajes), se reparte por unidades físicas. Cada fórmula puede usar otro método; este es el que se aplica cuando la fórmula no indica ninguno.</p>') ?>
                            </div>
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

        <?php if ($config): ?>
            <?php
            // Las cuentas se configuran en Configuración Contable → Producción (una sola fuente para
            // todos los asientos); aquí solo se muestra su estado y un acceso directo.
            $faltanObligatorias = array_filter($cuentasContables ?? [], fn($c) => $c['obligatoria'] && $c['cuenta'] === null);
            $puedeIrContable = !empty($permConfigContable['ver']);
            ?>
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>Cuentas contables
                        <?= $ayuda('Cuentas contables',
                            '<p>Con estas cuentas el sistema arma los asientos de Producción (prefijo <b>PR-</b>):</p>'
                            . '<ul><li><b>Al consumir</b> insumos: Productos en proceso (Debe) contra Inventario de materia prima (Haber).</li>'
                            . '<li><b>Al cerrar</b> la orden: Inventario de producto terminado (Debe) contra Productos en proceso, Mano de obra aplicada y Costos indirectos (Haber); la merma anormal va a su cuenta de gasto.</li></ul>'
                            . '<p class="mb-0">Si un producto tiene su propia cuenta de inventario en su ficha, se usa esa. Se configuran en <b>Configuración Contable → Producción</b>.</p>') ?>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($contabilizaProd): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle me-1"></i>Contabilización encendida</span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" title="Se enciende en Configuración Contable → Módulos que contabilizan"><i class="bi bi-pause-circle me-1"></i>Contabilización apagada</span>
                        <?php endif; ?>
                        <?php if ($puedeIrContable): ?>
                            <a href="<?= rtrim($base, '/') ?>/modulos/configuracion-contable" class="btn btn-outline-primary btn-sm" data-pcfg-config-contable>
                                <i class="bi bi-gear me-1"></i> Configurar cuentas
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($faltanObligatorias): ?>
                        <div class="alert alert-warning rounded-0 border-0 border-bottom py-2 px-3 small mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>Faltan cuentas obligatorias: sin ellas no se generan los asientos de las órdenes.
                        </div>
                    <?php endif; ?>
                    <table class="table table-sm mb-0 align-middle">
                        <tbody>
                        <?php foreach ($cuentasContables ?? [] as $c): ?>
                            <tr>
                                <td class="ps-3 small fw-bold" style="width:40%;"><?= htmlspecialchars($c['etiqueta']) ?>
                                    <?= $c['obligatoria'] ? '<span class="text-danger">*</span>' : '<span class="text-muted fw-normal">(según uso)</span>' ?></td>
                                <td class="small pe-3">
                                    <?php if ($c['cuenta'] !== null): ?>
                                        <i class="bi bi-check-circle-fill text-success me-1"></i><?= htmlspecialchars($c['cuenta']) ?>
                                    <?php else: ?>
                                        <i class="bi <?= $c['obligatoria'] ? 'bi-x-circle-fill text-danger' : 'bi-dash-circle text-muted' ?> me-1"></i>
                                        <span class="text-muted">Sin configurar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="form-text px-3 pb-2">* Obligatorias. Las demás solo hacen falta si las órdenes tienen mano de obra, costos indirectos o merma anormal.</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($instalado && $puedeCrearBodega): ?>
    <?php include_once MVC_APP . '/views/modulos/bodegas/modal.php'; ?>
<?php endif; ?>

<script>
    window.PCFG_URL_BASE = '<?= $urlBase ?>';
    window.PCFG_PUEDE_GUARDAR = <?= $puedeGuardar ? 'true' : 'false' ?>;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/produccion_configuracion.js?v=<?= asset_ver('/js/modulos/produccion_configuracion.js') ?>"></script>
