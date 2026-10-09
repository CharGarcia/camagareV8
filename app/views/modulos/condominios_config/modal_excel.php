<?php
/** @var array $perm @var string $rutaModulo */
if (empty($perm['crear'])) {
    return;
}
$urlBaseCond = rtrim(BASE_URL, '/') . '/' . $rutaModulo;
?>
<div class="modal fade modal-cond" id="modalCondExcel" tabindex="-1" data-bs-backdrop="static" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-file-earmark-arrow-up text-success me-2"></i>Cargar inmuebles desde Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                    <a href="<?= $urlBaseCond ?>/plantillaExcel" class="btn btn-outline-success btn-sm" onclick="event.preventDefault(); CMG_descargar(this.href)"><i class="bi bi-download me-1"></i>Descargar plantilla</a>
                    <div class="vr mx-1"></div>
                    <div>
                        <label for="excel_archivo" class="small fw-bold d-block mb-1">Archivo (.xlsx)</label>
                        <input type="file" class="form-control form-control-sm" id="excel_archivo" accept=".xlsx,.xls" style="max-width:360px">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="excel-btn-leer" onclick="COND.excelLeer()"><i class="bi bi-eye me-1"></i>Vista previa</button>
                </div>
                <div class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Una fila por inmueble. El propietario (y el arrendatario, si viene) se cruza con Clientes por cédula/RUC; si no existe, se crea. Si el código ya existe, el inmueble se <b>actualiza</b>. Nada se graba hasta pulsar <b>Aplicar</b>.</div>
                <div id="excel-resultado" class="d-none">
                    <div id="excel-avisos"></div>
                    <div class="border rounded-3 bg-white" style="max-height: 50vh; overflow: auto;">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light"><tr><th class="ps-2">Fila</th><th>Acción</th><th>Código</th><th>Nombre</th><th>Tipo</th><th class="text-end">m²</th><th class="text-end">%</th><th>Propietario</th><th>Arrendatario</th><th>Paga</th><th>Método</th><th class="text-end pe-2">Manual</th></tr></thead>
                            <tbody id="excel-body"></tbody>
                        </table>
                    </div>
                    <div id="excel-errores" class="mt-2"></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2 d-flex justify-content-between">
                <span class="small text-muted" id="excel-resumen"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <button type="button" class="btn btn-success btn-sm px-3 d-none" id="excel-btn-aplicar" onclick="COND.excelAplicar()"><i class="bi bi-check2-circle me-1"></i>Aplicar</button>
                </div>
            </div>
        </div>
    </div>
</div>
