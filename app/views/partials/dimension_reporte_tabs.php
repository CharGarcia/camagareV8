<?php
/**
 * Pestañas «Documentos» y «Resumen» del modal de un proyecto o centro de costo.
 * Las alimenta CMG_dimReporte() de public/js/modulos/dimension_reporte.js.
 *
 * Uso, en dos partes (los <li> van dentro del <ul> de pestañas del modal; los paneles,
 * dentro de su .tab-content):
 *   <?php $dimParte = 'tabs';    $dimTipo = 'proyecto'; require MVC_APP . '/views/partials/dimension_reporte_tabs.php'; ?>
 *   <?php $dimParte = 'paneles'; $dimTipo = 'proyecto'; require MVC_APP . '/views/partials/dimension_reporte_tabs.php'; ?>
 *
 * Cada pestaña se dibuja solo si $dimPestanas (DimensionReporteTrait::dimPestanas(), que
 * el controlador pasa a la vista) la permite. Arrancan ocultas (d-none): el JS las muestra al abrir un
 * registro guardado, nunca al crear uno nuevo.
 *
 * Ids: dim-pane-documentos / dim-pane-resumen (claves de la preferencia de pestañas ocultas).
 */
$dimParte = $dimParte ?? 'tabs';
$dimTipo  = $dimTipo ?? 'proyecto';
// Pestañas que el usuario puede ver (DimensionReporteTrait::dimPestanas()).
$dimVer = ($dimPestanas ?? []) + ['documentos' => false, 'resumen' => false];

if ($dimParte === 'tabs'): ?>
    <?php if ($dimVer['documentos']): ?>
    <li class="nav-item dim-tab d-none">
        <a class="nav-link py-2 small" data-bs-toggle="tab" href="#dim-pane-documentos" data-bs-target="#dim-pane-documentos" role="tab"><i class="bi bi-files me-1"></i>Documentos</a>
    </li>
    <?php endif; ?>
    <?php if ($dimVer['resumen']): ?>
    <li class="nav-item dim-tab d-none">
        <a class="nav-link py-2 small" data-bs-toggle="tab" href="#dim-pane-resumen" data-bs-target="#dim-pane-resumen" role="tab"><i class="bi bi-graph-up-arrow me-1"></i><?= $dimTipo === 'proyecto' ? 'Avance' : 'Resumen' ?></a>
    </li>
    <?php endif; ?>
<?php else:
    // Barra de período: la misma en las dos pestañas (el JS las mantiene iguales).
    $barra = static function (string $pane) use ($dimTipo): string {
        $pdfExcel = $pane === 'resumen'
            ? '<div class="ms-auto d-flex gap-1">'
              . '<button type="button" class="btn btn-sm btn-outline-danger dim-btn-pdf" title="PDF del ' . ($dimTipo === 'proyecto' ? 'avance' : 'resumen') . ' con los documentos"><i class="bi bi-file-earmark-pdf"></i> PDF</button>'
              . '<button type="button" class="btn btn-sm btn-outline-success dim-btn-excel" title="Excel del resumen con los documentos"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>'
              . '</div>'
            : '';
        return '<div class="d-flex flex-wrap align-items-end gap-2 mb-2 pb-2 border-bottom">'
            . '<div><label class="form-label small fw-bold mb-0 d-block">Desde</label><input type="date" class="form-control form-control-sm dim-desde" style="width:140px;"></div>'
            . '<div><label class="form-label small fw-bold mb-0 d-block">Hasta</label><input type="date" class="form-control form-control-sm dim-hasta" style="width:140px;"></div>'
            . '<button type="button" class="btn btn-sm btn-primary dim-btn-mostrar"><i class="bi bi-search me-1"></i>Mostrar</button>'
            . '<button type="button" class="btn btn-sm btn-link text-muted dim-btn-todo" title="Quitar las fechas">Todo</button>'
            . $pdfExcel
            . '</div>';
    };
    ?>
    <style>
        .dim-tabla { max-height: 360px; overflow: auto; }
        .dim-tabla thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
        .dim-doc-row.abrible { cursor: pointer; }
        .dim-kpi { border: 1px solid #e9ecef; border-radius: .5rem; padding: .45rem .6rem; background: #fff; height: 100%; }
        .dim-kpi .lbl { font-size: .68rem; color: #6c757d; text-transform: uppercase; }
        .dim-kpi .val { font-size: 1rem; font-weight: 700; }
    </style>

    <?php if ($dimVer['documentos']): ?>
    <div class="tab-pane fade" id="dim-pane-documentos" role="tabpanel">
        <?= $barra('documentos') ?>
        <div class="dim-tabla border rounded">
            <table class="table table-sm table-hover mb-0 small">
                <thead>
                    <tr>
                        <th style="width:90px;">Fecha</th>
                        <th>Asiento</th>
                        <th>Origen</th>
                        <th>Documento</th>
                        <th>Tercero / concepto</th>
                        <th class="text-end">Debe</th>
                        <th class="text-end">Haber</th>
                    </tr>
                </thead>
                <tbody class="dim-docs-tbody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Seleccione la pestaña para cargar los documentos.</td></tr>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="5" class="text-end dim-docs-count">Totales</td>
                        <td class="text-end dim-docs-debe">0.00</td>
                        <td class="text-end dim-docs-haber">0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="small text-muted mt-1">
            Son los asientos contabilizados de producción con alguna línea de este <?= $dimTipo === 'proyecto' ? 'proyecto' : 'centro de costo' ?>;
            los importes son solo de esas líneas. Clic en una fila para ver el documento.
        </div>
    </div>

    <?php endif; ?>

    <?php if ($dimVer['resumen']): ?>
    <div class="tab-pane fade" id="dim-pane-resumen" role="tabpanel">
        <?= $barra('resumen') ?>
        <div class="dim-resumen-cuerpo">
            <div class="text-center text-muted py-4">Seleccione la pestaña para calcular el resumen.</div>
        </div>
    </div>
<?php endif; ?>
<?php endif;
