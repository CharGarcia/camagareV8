<?php
/**
 * Cuerpo del Resumen diario en pantalla (lo pide generarAjax). Mismo contenido que el PDF
 * y el Excel: los bloques con sus secciones y, al final, el resumen del día. Solo llega
 * lo que existe: el Service ya quitó las secciones y bloques sin documentos.
 *
 * @var array $datos Salida de ReporteResumenDiarioService::generar()
 * @var array $perm  Permisos del módulo (ver, crear, actualizar, eliminar, todo)
 */
$e   = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = static fn ($v): string => ((float) $v < 0 ? '-$' : '$') . number_format(abs((float) $v), 2);
$r   = $datos['resumen'];
$titulo = 'border-bottom border-2 text-dark fw-bold text-uppercase px-3 pt-2 pb-1 mt-3 mb-2';

/** Tabla de una sección: encabezados, filas y total. En traslados, botón de eliminar. */
$tabla = static function (array $s) use ($e, $fmt, $perm): void {
    $borrar = $s['clave'] === 'traslados' && !empty($perm['eliminar']);
    ?>
    <div class="rrd-scroll">
        <table class="table table-sm table-hover mb-0" style="font-size:.78rem;">
            <thead class="table-light">
                <tr>
                    <?php foreach ($s['columnas'] as $c): ?>
                        <th class="py-1 <?= !empty($c['num']) ? 'text-end' : '' ?>"><?= $e($c['lbl']) ?></th>
                    <?php endforeach; ?>
                    <?php if ($borrar): ?><th class="py-1"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($s['filas'] as $fila): ?>
                    <tr>
                        <?php foreach ($s['columnas'] as $c): ?>
                            <?php if (!empty($c['num'])): ?>
                                <td class="py-1 text-end text-nowrap"><?= $e($fmt($fila[$c['k']])) ?></td>
                            <?php else: ?>
                                <td class="py-1 <?= $c['k'] === 'numero' ? 'text-nowrap' : '' ?>"><?= $e($fila[$c['k']]) ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($borrar): ?>
                            <td class="py-1 text-end">
                                <button type="button" class="btn btn-link btn-sm text-danger p-0 rrd-eliminar-traslado"
                                        data-id="<?= (int) $fila['id'] ?>" title="Eliminar traslado"><i class="bi bi-trash"></i></button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold table-light">
                    <?php foreach ($s['columnas'] as $i => $c): ?>
                        <?php if (!empty($c['num'])): ?>
                            <td class="py-1 text-end text-nowrap"><?= $e($fmt($s['totales'][$c['k']] ?? 0)) ?></td>
                        <?php else: ?>
                            <td class="py-1"><?= $i === 0 ? 'Total' : '' ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($borrar): ?><td class="py-1"></td><?php endif; ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php
};
?>
<?php if (!$datos['grupos'] && !$r['caja']): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>No hay documentos ni movimientos en este día.</div>
    <?php return; ?>
<?php endif; ?>

<?php foreach ($datos['grupos'] as $g): ?>
    <div class="<?= $titulo ?>" style="font-size:.8rem;"><?= $e($g['titulo']) ?></div>

    <?php foreach ($g['secciones'] as $s): ?>
        <div class="px-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;"><?= $e($s['titulo']) ?></span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= count($s['filas']) ?></span>
                <?php if ($s['nota'] !== ''): ?>
                    <span class="small text-muted fst-italic"><?= $e($s['nota']) ?></span>
                <?php endif; ?>
            </div>
            <?php $tabla($s); ?>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<div class="<?= $titulo ?>" style="font-size:.8rem;">Resumen del día</div>
<div class="row g-3 px-3 pb-3">
    <?php foreach ([['Ventas', $r['ventas']], ['Compras', $r['compras']]] as [$bloque, $lineas]): ?>
        <?php if (!$lineas) continue; ?>
        <div class="col-md-6 col-xl-3">
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
    <?php if ($r['caja']): ?>
        <div class="col-xl-6">
            <div class="small fw-bold text-uppercase text-muted mb-1" style="font-size:.7rem;">
                Caja por forma de pago
                <?php if (!empty($datos['saldo_restringido'])): ?>
                    <span class="fw-normal text-lowercase fst-italic">(sin saldos: solo ve lo que usted registró)</span>
                <?php endif; ?>
            </div>
            <?php $tabla($r['caja']); ?>
        </div>
    <?php endif; ?>
</div>
