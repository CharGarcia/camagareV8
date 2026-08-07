<?php

/**
 * Descargas Masivas: PDF y/o XML de varios documentos de un mismo tipo (rango
 * de fechas) comprimidos en un ZIP.
 *
 * @var string $titulo
 * @var array  $perm       ['ver','crear','actualizar','eliminar','todo']
 * @var string $rutaModulo
 * @var array  $tipos      [clave => etiqueta]
 */
$base = rtrim(BASE_URL ?? '', '/');
$hoy  = date('Y-m-d');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="container-fluid py-4" id="dm-app"
     data-base="<?= htmlspecialchars($base) ?>"
     data-ruta="<?= htmlspecialchars($rutaModulo) ?>">

    <h5 class="mb-3 fw-bold text-dark">
        <i class="bi bi-file-earmark-zip text-primary me-2"></i><?= htmlspecialchars($titulo) ?>
    </h5>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <p class="text-muted small mb-4">
                        Descarga el PDF y/o XML de varios documentos de un mismo tipo, en un solo ZIP.
                        No hay límite de rango de fechas: se acota por la <strong>cantidad</strong> de
                        documentos encontrados. El archivo se genera al momento y no queda guardado en
                        el sistema.
                    </p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Tipo de documento</label>
                        <select id="dm-tipo" class="form-select form-select-sm">
                            <?php foreach ($tipos as $clave => $etiqueta): ?>
                                <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Desde</label>
                            <input type="date" id="dm-desde" class="form-control form-control-sm" value="<?= $hoy ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Hasta</label>
                            <input type="date" id="dm-hasta" class="form-control form-control-sm" value="<?= $hoy ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted mb-1 d-block">Formato</label>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="dm-formato" id="dm-f-pdf" value="pdf" checked>
                            <label class="btn btn-outline-primary" for="dm-f-pdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</label>

                            <input type="radio" class="btn-check" name="dm-formato" id="dm-f-xml" value="xml">
                            <label class="btn btn-outline-primary" for="dm-f-xml"><i class="bi bi-file-earmark-code me-1"></i>XML</label>

                            <input type="radio" class="btn-check" name="dm-formato" id="dm-f-ambos" value="ambos">
                            <label class="btn btn-outline-primary" for="dm-f-ambos"><i class="bi bi-files me-1"></i>Ambos</label>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary btn-sm w-100" id="dm-btn-verificar" onclick="DM.verificar()">
                        <i class="bi bi-search me-1"></i> Verificar cantidad
                    </button>

                    <div id="dm-resultado" class="mt-4" style="display:none;">
                        <div class="alert mb-3" id="dm-resultado-alert" role="alert"></div>
                        <button type="button" class="btn btn-success btn-sm w-100" id="dm-btn-descargar" onclick="DM.descargar()">
                            <i class="bi bi-download me-1"></i> Descargar ZIP
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>/js/modulos/descargas_masivas.js?v=<?= time() ?>"></script>
