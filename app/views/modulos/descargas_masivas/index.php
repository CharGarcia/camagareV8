<?php

/**
 * Descargas Masivas: PDF y/o XML de varios documentos de un mismo tipo (rango
 * de fechas o de números) comprimidos en un ZIP, o en un solo PDF si la
 * cantidad es pequeña.
 *
 * @var string $titulo
 * @var array  $perm           ['ver','crear','actualizar','eliminar','todo']
 * @var string $rutaModulo
 * @var array  $tipos          [clave => etiqueta]
 * @var array  $tiposSinXml    claves de tipos sin XML disponible (solo admiten PDF)
 * @var int    $umbralPdfUnico cantidad máxima para entregar PDF único en vez de ZIP
 */
$base = rtrim(BASE_URL ?? '', '/');
$hoy  = date('Y-m-d');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="container-fluid py-4" id="dm-app"
     data-base="<?= htmlspecialchars($base) ?>"
     data-ruta="<?= htmlspecialchars($rutaModulo) ?>"
     data-tipos-sin-xml="<?= htmlspecialchars(json_encode(array_values($tiposSinXml))) ?>">

    <h5 class="mb-3 fw-bold text-dark">
        <i class="bi bi-file-earmark-zip text-primary me-2"></i><?= htmlspecialchars($titulo) ?>
    </h5>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <p class="text-muted small mb-4">
                        Descarga el PDF y/o XML de varios documentos de un mismo tipo. Si son hasta
                        <strong><?= (int) $umbralPdfUnico ?></strong> documentos y el formato es PDF, se entrega
                        <strong>un solo PDF</strong>; si son más (o eliges XML/Ambos), se entrega un <strong>ZIP</strong>.
                        No hay límite de rango: se acota por la <strong>cantidad</strong> de documentos encontrados.
                        El archivo se genera al momento y no queda guardado en el sistema.
                    </p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Tipo de documento</label>
                        <select id="dm-tipo" class="form-select form-select-sm">
                            <?php foreach ($tipos as $clave => $etiqueta): ?>
                                <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1 d-block">Filtrar por</label>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="dm-modo" id="dm-m-fecha" value="fecha" checked>
                            <label class="btn btn-outline-secondary" for="dm-m-fecha"><i class="bi bi-calendar-range me-1"></i>Rango de fechas</label>

                            <input type="radio" class="btn-check" name="dm-modo" id="dm-m-numero" value="numero">
                            <label class="btn btn-outline-secondary" for="dm-m-numero"><i class="bi bi-123 me-1"></i>Rango de número</label>
                        </div>
                    </div>

                    <div class="row g-2 mb-3" id="dm-bloque-fecha">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Desde</label>
                            <input type="date" id="dm-desde" class="form-control form-control-sm" value="<?= $hoy ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Hasta</label>
                            <input type="date" id="dm-hasta" class="form-control form-control-sm" value="<?= $hoy ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-3" id="dm-bloque-numero" style="display:none;">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Número desde</label>
                            <input type="number" min="1" id="dm-numero-desde" class="form-control form-control-sm" placeholder="Ej: 1">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Número hasta</label>
                            <input type="number" min="1" id="dm-numero-hasta" class="form-control form-control-sm" placeholder="Ej: 100">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted mb-1 d-block">Formato</label>
                        <div class="btn-group btn-group-sm w-100" role="group" id="dm-grupo-formato">
                            <input type="radio" class="btn-check" name="dm-formato" id="dm-f-pdf" value="pdf" checked>
                            <label class="btn btn-outline-primary" for="dm-f-pdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</label>

                            <input type="radio" class="btn-check" name="dm-formato" id="dm-f-xml" value="xml">
                            <label class="btn btn-outline-primary" for="dm-f-xml"><i class="bi bi-file-earmark-code me-1"></i>XML</label>

                            <input type="radio" class="btn-check" name="dm-formato" id="dm-f-ambos" value="ambos">
                            <label class="btn btn-outline-primary" for="dm-f-ambos"><i class="bi bi-files me-1"></i>Ambos</label>
                        </div>
                        <div class="form-text small" id="dm-sin-xml-aviso" style="display:none;">
                            Este tipo de documento no tiene XML disponible: solo se puede descargar en PDF.
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary btn-sm w-100" id="dm-btn-verificar" onclick="DM.verificar()">
                        <i class="bi bi-search me-1"></i> Verificar cantidad
                    </button>

                    <div id="dm-resultado" class="mt-4" style="display:none;">
                        <div class="alert mb-3" id="dm-resultado-alert" role="alert"></div>
                        <button type="button" class="btn btn-success btn-sm w-100" id="dm-btn-descargar" onclick="DM.descargar()">
                            <i class="bi bi-download me-1"></i> Descargar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>/js/modulos/descargas_masivas.js?v=<?= time() ?>"></script>
