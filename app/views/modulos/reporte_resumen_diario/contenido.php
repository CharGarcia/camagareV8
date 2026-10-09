<?php
/**
 * Cuerpo del Resumen diario en pantalla (lo pide generarAjax). Mismo contenido que el PDF
 * y el Excel: los dos bloques (ventas e ingresos / compras y egresos) con sus secciones
 * y, al final, el resumen del día. Solo llega lo que existe: el Service ya quitó las
 * secciones y bloques sin documentos.
 *
 * @var array $datos Salida de ReporteResumenDiarioService::generar()
 */
$e   = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = static fn ($v): string => ((float) $v < 0 ? '-$' : '$') . number_format(abs((float) $v), 2);
$r   = $datos['resumen'];
$titulo = 'border-bottom border-2 text-dark fw-bold text-uppercase px-3 pt-2 pb-1 mt-3 mb-2';
?>
<?php if (!$datos['grupos']): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>No hay documentos ni movimientos en este día.</div>
    <?php return; ?>
<?php endif; ?>
<?php foreach ($datos['grupos'] as $g): ?>
    <div class="<?= $titulo ?>" style="font-size:.8rem;"><?= $e($g['titulo']) ?></div>

    <?php foreach ($g['secciones'] as $s): ?>
        <?php $n = count($s['filas']); ?>
        <div class="px-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;"><?= $e($s['titulo']) ?></span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $n ?></span>
                <?php if ($s['nota'] !== ''): ?>
                    <span class="small text-muted fst-italic"><?= $e($s['nota']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($n): ?>
                <div class="rrd-scroll">
                    <table class="table table-sm table-hover mb-0" style="font-size:.78rem;">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($s['columnas'] as $c): ?>
                                    <th class="py-1 <?= !empty($c['num']) ? 'text-end' : '' ?>"><?= $e($c['lbl']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($s['filas'] as $fila): ?>
                                <tr>
                                    <?php foreach ($s['columnas'] as $c): ?>
                                        <?php if (!empty($c['num'])): ?>
                                            <td class="py-1 text-end text-nowrap"><?= $e($fmt($fila[$c['k']])) ?></td>
                                        <?php else: ?>
                                            <td class="py-1 <?= in_array($c['k'], ['numero'], true) ? 'text-nowrap' : '' ?>"><?= $e($fila[$c['k']]) ?></td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold table-light">
                                <?php $primera = true; ?>
                                <?php foreach ($s['columnas'] as $c): ?>
                                    <?php if (!empty($c['num'])): ?>
                                        <td class="py-1 text-end text-nowrap"><?= $e($fmt($s['totales'][$c['k']] ?? 0)) ?></td>
                                    <?php else: ?>
                                        <td class="py-1"><?= $primera ? 'Total' : '' ?></td>
                                    <?php endif; ?>
                                    <?php $primera = false; ?>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<div class="<?= $titulo ?>" style="font-size:.8rem;">Resumen del día</div>
<div class="row g-3 px-3 pb-3">
    <?php foreach ([['Ventas', $r['ventas']], ['Compras', $r['compras']]] as [$bloque, $lineas]): ?>
        <?php if (!$lineas) continue; ?>
        <div class="col-md-4">
            <div class="small fw-bold text-uppercase text-muted mb-1" style="font-size:.7rem;"><?= $e($bloque) ?></div>
            <table class="table table-sm mb-0" style="font-size:.78rem;">
                <?php foreach ($lineas as [$lbl, $val, $estilo]): ?>
                    <tr class="<?= $estilo === 'bold' ? 'fw-bold table-light' : ($estilo === 'sub' ? 'text-muted small' : '') ?>">
                        <td class="py-1"><?= $e($lbl) ?></td>
                        <td class="py-1 text-end text-nowrap"><?= $e($fmt($val)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endforeach; ?>
    <?php if ($r['formas']): ?>
    <div class="col-md-4">
        <div class="small fw-bold text-uppercase text-muted mb-1" style="font-size:.7rem;">Caja por forma de pago</div>
        <table class="table table-sm mb-0" style="font-size:.78rem;">
            <thead class="table-light">
                <tr><th class="py-1">Forma</th><th class="py-1 text-end">Ingresos</th><th class="py-1 text-end">Egresos</th><th class="py-1 text-end">Neto</th></tr>
            </thead>
            <tbody>
                <?php foreach ($r['formas'] as $fp): ?>
                    <tr>
                        <td class="py-1"><?= $e($fp['forma']) ?></td>
                        <td class="py-1 text-end text-nowrap text-success"><?= $e($fmt($fp['ingresos'])) ?></td>
                        <td class="py-1 text-end text-nowrap text-danger"><?= $e($fmt($fp['egresos'])) ?></td>
                        <td class="py-1 text-end text-nowrap fw-semibold"><?= $e($fmt($fp['neto'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold table-light">
                    <td class="py-1">Total</td>
                    <td class="py-1 text-end text-nowrap"><?= $e($fmt($r['caja']['ingresos'])) ?></td>
                    <td class="py-1 text-end text-nowrap"><?= $e($fmt($r['caja']['egresos'])) ?></td>
                    <td class="py-1 text-end text-nowrap"><?= $e($fmt($r['caja']['neto'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
