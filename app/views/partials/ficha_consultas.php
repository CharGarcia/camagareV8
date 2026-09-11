<?php
/**
 * Pestañas de consulta de las fichas de proveedor y cliente: «Transacciones» (productos y
 * servicios de sus documentos) y «Estado de cuenta» (kardex con su historial de pagos o
 * cobros). Un solo HTML para ambos modales; lo alimenta FichaConsultas.iniciar() de
 * public/js/components/ficha_consultas.js, que ubica cada control por su atributo data-fc
 * dentro del panel (no por id).
 *
 * El modal decide los permisos: pasa null en el panel que el usuario no puede ver y omite
 * también su <li> y su entrada en el dropdown de pestañas configurables.
 *
 * Variables:
 *   $fichaConsultas = [
 *       'prefijo'       => 'prov',                    // para los ids de <label for>
 *       'transacciones' => 'prov-tab-transacciones',  // id del panel, o null para no pintarlo
 *       'estado_cuenta' => 'prov-tab-estado-cuenta',  // id del panel, o null
 *       'textos'        => [...],                     // ver valores por defecto abajo
 *   ];
 *
 * Los estilos y el script del componente salen una sola vez por página: este partial vive
 * dentro de modales que se incrustan en muchos módulos, así que no se le pide a cada página
 * que cargue el script.
 */
$fcConf   = $fichaConsultas ?? [];
$fcPref   = preg_replace('/[^a-z0-9_-]/i', '', (string) ($fcConf['prefijo'] ?? 'fc'));
$fcPanTrx = $fcConf['transacciones'] ?? null;
$fcPanEc  = $fcConf['estado_cuenta'] ?? null;
$fcTxt    = ($fcConf['textos'] ?? []) + [
    'sin_guardar_trx' => 'Guarde la ficha para ver sus transacciones.',
    'sin_guardar_ec'  => 'Guarde la ficha para ver su estado de cuenta.',
    'nota_trx'        => 'Las notas de crédito restan.',
    'cargos'          => 'CARGOS',
    'pagos'           => 'PAGOS',
    'otros'           => 'RETENCIONES Y NC',
    'saldo'           => 'SALDO',
    'filtro_pagos'    => 'Historial de pagos',
    'ayuda_pago'      => 'Haga clic en un pago para ver su detalle.',
];
$fcEsc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if (!defined('FICHA_CONSULTAS_ASSETS')):
    define('FICHA_CONSULTAS_ASSETS', true);
?>
<style>
    .ficha-consulta-scroll {
        max-height: 380px;
        overflow: auto;
    }
    .ficha-consulta-scroll > table {
        font-size: 0.76rem;
    }
    .ficha-consulta-scroll > table > thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
        white-space: nowrap;
    }
    .ficha-consulta-scroll > table > thead th[data-orden] {
        cursor: pointer;
        user-select: none;
    }
    .ficha-consulta-scroll > table > tbody > tr > td {
        white-space: nowrap;
        vertical-align: middle;
    }
    .ficha-consulta-scroll > table > tbody > tr > td.fc-col-desc {
        max-width: 340px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .fc-fila-abrible {
        cursor: pointer;
    }
    .fc-fila-abrible:hover > td {
        background: rgba(13, 110, 253, .06);
    }
    .ficha-consulta-scroll > table > tbody > tr.fc-detalle-pago > td {
        background: #f8f9fa;
        white-space: normal !important;
    }
</style>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/components/ficha_consultas.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php if ($fcPanTrx): ?>
    <!-- Pestaña Transacciones (solo lectura): productos y servicios de los documentos -->
    <div class="tab-pane fade" id="<?= $fcEsc($fcPanTrx) ?>" role="tabpanel">
        <div class="text-center text-muted small py-5" data-fc="sin-guardar">
            <i class="bi bi-receipt fs-3 d-block mb-2"></i>
            <?= $fcEsc($fcTxt['sin_guardar_trx']) ?>
        </div>
        <div class="d-none" data-fc="con-datos">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="input-group input-group-sm" style="width: 420px; max-width: 100%;">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" data-fc="buscar" autocomplete="off" aria-label="Buscar transacciones"
                        placeholder="Producto, código o documento…"
                        title="Texto libre o filtros: fecha:2026-08, precio:>10, cantidad:1..5, documento:582276, tipo:credito, -tipo:credito">
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Vista">
                    <button type="button" class="btn btn-outline-secondary active" data-fc-vista="detalle"><i class="bi bi-list-ul me-1"></i>Detalle</button>
                    <button type="button" class="btn btn-outline-secondary" data-fc-vista="producto"><i class="bi bi-box-seam me-1"></i>Por producto</button>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <span class="small text-muted fw-medium" data-fc="info">0-0/0</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-fc="prev" title="Anterior" disabled><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-fc="next" title="Siguiente" disabled><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
            <div class="ficha-consulta-scroll border rounded">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr data-fc="thead"></tr>
                    </thead>
                    <tbody data-fc="tbody"></tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap justify-content-between gap-2 mt-1 small text-muted">
                <span><i class="bi bi-info-circle me-1"></i><?= $fcEsc($fcTxt['nota_trx']) ?></span>
                <span>Total neto (sin impuestos): <b class="text-dark" data-fc="total">$0.00</b></span>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($fcPanEc): ?>
    <!-- Pestaña Estado de cuenta (solo lectura): kardex e historial de pagos o cobros -->
    <div class="tab-pane fade" id="<?= $fcEsc($fcPanEc) ?>" role="tabpanel">
        <div class="text-center text-muted small py-5" data-fc="sin-guardar">
            <i class="bi bi-journal-text fs-3 d-block mb-2"></i>
            <?= $fcEsc($fcTxt['sin_guardar_ec']) ?>
        </div>
        <div class="d-none" data-fc="con-datos">
            <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
                <div>
                    <label class="form-label small fw-bold d-block mb-1" for="<?= $fcPref ?>_fc_desde">Desde</label>
                    <input type="date" class="form-control form-control-sm" id="<?= $fcPref ?>_fc_desde" data-fc="desde" style="width: 140px;">
                </div>
                <div>
                    <label class="form-label small fw-bold d-block mb-1" for="<?= $fcPref ?>_fc_hasta">Hasta</label>
                    <input type="date" class="form-control form-control-sm" id="<?= $fcPref ?>_fc_hasta" data-fc="hasta" style="width: 140px;">
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-fc="limpiar" title="Quitar el rango de fechas"><i class="bi bi-eraser"></i></button>
                <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Movimientos">
                    <button type="button" class="btn btn-outline-secondary active" data-fc-filtro="todos"><i class="bi bi-list-columns-reverse me-1"></i>Todos los movimientos</button>
                    <button type="button" class="btn btn-outline-secondary" data-fc-filtro="pagos"><i class="bi bi-cash-coin me-1"></i><?= $fcEsc($fcTxt['filtro_pagos']) ?></button>
                </div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6 col-md d-none" data-fc="card-anterior">
                    <div class="card bg-light border-0 text-center p-2">
                        <span class="small text-muted d-block" style="font-size: 0.7rem;">SALDO ANTERIOR</span>
                        <h6 class="mb-0 fw-bold" data-fc="saldo-anterior">$0.00</h6>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card bg-light border-0 text-center p-2">
                        <span class="small text-muted d-block" style="font-size: 0.7rem;"><?= $fcEsc($fcTxt['cargos']) ?></span>
                        <h6 class="mb-0 fw-bold text-primary" data-fc="cargos">$0.00</h6>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card bg-light border-0 text-center p-2">
                        <span class="small text-muted d-block" style="font-size: 0.7rem;"><?= $fcEsc($fcTxt['pagos']) ?></span>
                        <h6 class="mb-0 fw-bold text-success" data-fc="pagos">$0.00</h6>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card bg-light border-0 text-center p-2">
                        <span class="small text-muted d-block" style="font-size: 0.7rem;"><?= $fcEsc($fcTxt['otros']) ?></span>
                        <h6 class="mb-0 fw-bold text-warning" data-fc="otros">$0.00</h6>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card bg-light border-0 text-center p-2">
                        <span class="small text-muted d-block" style="font-size: 0.7rem;"><?= $fcEsc($fcTxt['saldo']) ?></span>
                        <h6 class="mb-0 fw-bold text-danger" data-fc="saldo">$0.00</h6>
                    </div>
                </div>
            </div>
            <div class="ficha-consulta-scroll border rounded">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="ps-2">Fecha</th>
                            <th>Movimiento</th>
                            <th>Documento</th>
                            <th>Detalle</th>
                            <th class="text-end">Cargo</th>
                            <th class="text-end">Abono</th>
                            <th class="text-end pe-2">Saldo</th>
                        </tr>
                    </thead>
                    <tbody data-fc="tbody"></tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                <div class="small text-muted d-none" data-fc="ayuda-pago">
                    <i class="bi bi-hand-index me-1"></i><?= $fcEsc($fcTxt['ayuda_pago']) ?>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <span class="small text-muted fw-medium" data-fc="info">0-0/0</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-fc="prev" title="Anterior" disabled><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-fc="next" title="Siguiente" disabled><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php
// Este archivo se incluye dentro de la vista de cada página: no dejar variables sueltas.
unset($fcConf, $fcPref, $fcPanTrx, $fcPanEc, $fcTxt, $fcEsc);
