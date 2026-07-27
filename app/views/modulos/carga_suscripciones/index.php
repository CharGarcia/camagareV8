<?php

/** @var array $perm */
/** @var string $rutaModulo */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
?>

<!-- Hay tarjetas y pasos ENCIMA de la tabla de resultados: el app-shell no aplica. -->
<script>
    document.body.classList.add('cmg-no-app-shell');
</script>

<style>
    .cs-paso-num {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .75rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .cs-dropzone {
        border: 2px dashed #adb5bd;
        border-radius: .5rem;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: background-color .15s, border-color .15s;
    }

    .cs-dropzone:hover,
    .cs-dropzone.cs-activa {
        border-color: #0d6efd;
        background-color: #f1f6ff;
    }

    .cs-kpi {
        border-radius: .5rem;
        padding: .6rem .9rem;
        min-width: 116px;
    }

    .cs-kpi .cs-kpi-num {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1;
    }

    .cs-kpi .cs-kpi-lbl {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .carga-suscripciones-scroll {
        max-height: 45vh;
        overflow: auto;
    }

    .carga-suscripciones-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .cs-msg {
        font-size: .8rem;
    }
</style>

<div class="container-fluid py-3">

    <div class="mb-3 px-3">
        <h5 class="mb-1 fw-bold">
            <i class="bi bi-file-earmark-excel text-success me-2"></i> Carga de Suscripciones
        </h5>
        <p class="text-muted small mb-0">
            Cree varias suscripciones a la vez desde un archivo Excel. Descargue la plantilla
            —trae sus clientes, productos, periodicidades y tarifas de IVA—, complete la hoja
            <strong>Suscripciones</strong> (una fila por suscripción) y la hoja <strong>Detalle</strong>
            (los productos de cada una, enlazados por la columna <strong>CLAVE</strong>), y súbala.
            Esta carga solo <strong>crea</strong> suscripciones nuevas.
        </p>
    </div>

    <div class="row g-3 mx-0">

        <!-- PASO 1: descargar -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="cs-paso-num bg-primary text-white">1</span>
                        <div>
                            <div class="fw-bold">Descargue la plantilla</div>
                            <div class="text-muted small">
                                Trae sus catálogos actuales en hojas de consulta. Complete las hojas
                                Suscripciones y Detalle.
                            </div>
                        </div>
                    </div>

                    <a href="<?= $urlBase ?>/descargarPlantilla" class="btn btn-success btn-sm shadow-sm">
                        <i class="bi bi-download me-1"></i> Descargar plantilla
                    </a>

                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0 cs-msg">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>No borre ni agregue hojas</strong> al libro, ni cambie los
                        encabezados de las columnas. El sistema rechazará el archivo.
                    </div>
                </div>
            </div>
        </div>

        <!-- PASO 2: subir -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="cs-paso-num bg-primary text-white">2</span>
                        <div>
                            <div class="fw-bold">Suba el archivo</div>
                            <div class="text-muted small">
                                Primero se revisa por completo. No se guarda nada hasta que usted
                                lo confirme.
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($perm['crear'])): ?>
                        <div class="cs-dropzone" id="csDropzone">
                            <i class="bi bi-cloud-arrow-up fs-3 text-secondary d-block mb-1"></i>
                            <div class="small fw-semibold" id="csNombreArchivo">
                                Arrastre el archivo aquí o haga clic para seleccionarlo
                            </div>
                            <div class="text-muted" style="font-size: .72rem;">Formato .xlsx — máximo 20 MB</div>
                        </div>
                        <input type="file" id="csArchivo" accept=".xlsx,.xls" class="d-none">

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-primary btn-sm shadow-sm" id="csBtnValidar" disabled>
                                <i class="bi bi-search me-1"></i> Revisar archivo
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="csBtnLimpiar">
                                Limpiar
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary py-2 px-3 mb-0 cs-msg">
                            No tiene permiso para cargar suscripciones en este módulo.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 3: resultado de la revisión -->
    <div class="card border-0 shadow-sm mt-3 mx-0 d-none cmg-table-card" id="csPanelResultado">
        <div class="card-body">

            <div class="d-flex align-items-start gap-2 mb-3">
                <span class="cs-paso-num bg-primary text-white">3</span>
                <div class="flex-grow-1">
                    <div class="fw-bold">Revise y confirme</div>
                    <div class="text-muted small" id="csSubtituloResultado"></div>
                </div>
            </div>

            <div id="csErroresGlobales"></div>

            <div class="d-flex flex-wrap gap-2 mb-3" id="csKpis"></div>

            <div class="d-flex gap-2 mb-3" id="csAcciones">
                <button type="button" class="btn btn-success btn-sm shadow-sm" id="csBtnAplicar">
                    <i class="bi bi-check2-circle me-1"></i> Crear suscripciones
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="csBtnCancelar">
                    Cancelar
                </button>
            </div>

            <div id="csDetalleWrap" class="d-none">
                <div class="fw-semibold small mb-2">Detalle de filas con problemas</div>
                <div class="table-responsive carga-suscripciones-scroll border rounded">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 140px;">Hoja</th>
                                <th style="width: 70px;">Fila</th>
                                <th style="width: 140px;">Clave</th>
                                <th style="width: 90px;">Tipo</th>
                                <th>Mensaje</th>
                            </tr>
                        </thead>
                        <tbody id="csDetalleBody"></tbody>
                    </table>
                </div>
                <div class="text-muted mt-2 cs-msg d-none" id="csRecortado">
                    Se muestran las primeras 300 filas con problemas. Corrija estas y vuelva a subir el archivo.
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado de la aplicación -->
    <div class="card border-0 shadow-sm mt-3 mx-0 d-none" id="csPanelAplicado">
        <div class="card-body">
            <div class="fw-bold mb-2"><i class="bi bi-check-circle-fill text-success me-1"></i> Carga aplicada</div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="csKpisAplicado"></div>
            <div id="csErroresAplicado"></div>
        </div>
    </div>

</div>

<script>
    window.CS_URL_BASE = '<?= $urlBase ?>';
</script>
<script src="<?= $base ?>/js/modulos/carga_suscripciones.js?v=<?= time() ?>"></script>
