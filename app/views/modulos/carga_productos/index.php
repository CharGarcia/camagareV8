<?php

/** @var array $perm */
/** @var string $rutaModulo */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
?>

<!-- Esta página tiene tarjetas y pasos ENCIMA de la tabla de resultados, por lo
     que el app-shell (título + una sola tabla a pantalla completa) no aplica:
     bloquearía el scroll del body y la pantalla se vería inmóvil. -->
<script>
    document.body.classList.add('cmg-no-app-shell');
</script>

<style>
    .cp-paso-num {
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

    .cp-dropzone {
        border: 2px dashed #adb5bd;
        border-radius: .5rem;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: background-color .15s, border-color .15s;
    }

    .cp-dropzone:hover,
    .cp-dropzone.cp-activa {
        border-color: #0d6efd;
        background-color: #f1f6ff;
    }

    .cp-kpi {
        border-radius: .5rem;
        padding: .6rem .9rem;
        min-width: 116px;
    }

    .cp-kpi .cp-kpi-num {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1;
    }

    .cp-kpi .cp-kpi-lbl {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .carga-productos-scroll {
        max-height: 45vh;
        overflow: auto;
    }

    .carga-productos-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .cp-msg {
        font-size: .8rem;
    }
</style>

<div class="container-fluid py-3">

    <div class="mb-3 px-3">
        <h5 class="mb-1 fw-bold">
            <i class="bi bi-file-earmark-excel text-success me-2"></i> Carga de Productos y Servicios
        </h5>
        <p class="text-muted small mb-0">
            Cree y actualice varios productos y servicios a la vez desde un archivo Excel.
            Descargue la plantilla —viene con todo su catálogo actual—, edite lo que necesite
            y súbala: los códigos que ya existen se <strong>actualizan</strong> y los nuevos se
            <strong>crean</strong>. Incluye precios, variantes, componentes, stock mínimo/máximo
            por bodega y homologaciones con proveedores. Desde el Excel no se elimina nada;
            para retirar un producto de circulación márquelo como <strong>Inactivo</strong>.
        </p>
    </div>

    <div class="row g-3 mx-0">

        <!-- PASO 1: descargar -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="cp-paso-num bg-primary text-white">1</span>
                        <div>
                            <div class="fw-bold">Descargue la plantilla</div>
                            <div class="text-muted small">
                                Viene con todos sus productos y servicios actuales. Edite lo que
                                necesite y agregue los nuevos al final.
                            </div>
                        </div>
                    </div>

                    <a href="<?= $urlBase ?>/descargarPlantilla" class="btn btn-success btn-sm shadow-sm">
                        <i class="bi bi-download me-1"></i> Descargar plantilla
                    </a>

                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0 cp-msg">
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
                        <span class="cp-paso-num bg-primary text-white">2</span>
                        <div>
                            <div class="fw-bold">Suba el archivo editado</div>
                            <div class="text-muted small">
                                Primero se revisa por completo. No se guarda nada hasta que usted
                                lo confirme.
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($perm['crear'])): ?>
                        <div class="cp-dropzone" id="cpDropzone">
                            <i class="bi bi-cloud-arrow-up fs-3 text-secondary d-block mb-1"></i>
                            <div class="small fw-semibold" id="cpNombreArchivo">
                                Arrastre el archivo aquí o haga clic para seleccionarlo
                            </div>
                            <div class="text-muted" style="font-size: .72rem;">Formato .xlsx — máximo 20 MB</div>
                        </div>
                        <input type="file" id="cpArchivo" accept=".xlsx,.xls" class="d-none">

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-primary btn-sm shadow-sm" id="cpBtnValidar" disabled>
                                <i class="bi bi-search me-1"></i> Revisar archivo
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="cpBtnLimpiar">
                                Limpiar
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary py-2 px-3 mb-0 cp-msg">
                            No tiene permiso para cargar productos en este módulo.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 3: resultado de la revisión -->
    <div class="card border-0 shadow-sm mt-3 mx-0 d-none cmg-table-card" id="cpPanelResultado">
        <div class="card-body">

            <div class="d-flex align-items-start gap-2 mb-3">
                <span class="cp-paso-num bg-primary text-white">3</span>
                <div class="flex-grow-1">
                    <div class="fw-bold">Revise y confirme</div>
                    <div class="text-muted small" id="cpSubtituloResultado"></div>
                </div>
            </div>

            <div id="cpErroresGlobales"></div>

            <div class="d-flex flex-wrap gap-2 mb-3" id="cpKpis"></div>

            <div class="d-flex gap-2 mb-3" id="cpAcciones">
                <button type="button" class="btn btn-success btn-sm shadow-sm" id="cpBtnAplicar">
                    <i class="bi bi-check2-circle me-1"></i> Aplicar cambios
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cpBtnCancelar">
                    Cancelar
                </button>
            </div>

            <div id="cpDetalleWrap" class="d-none">
                <div class="fw-semibold small mb-2">Detalle de filas con problemas</div>
                <div class="table-responsive carga-productos-scroll border rounded">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 130px;">Hoja</th>
                                <th style="width: 70px;">Fila</th>
                                <th style="width: 140px;">Código</th>
                                <th style="width: 90px;">Tipo</th>
                                <th>Mensaje</th>
                            </tr>
                        </thead>
                        <tbody id="cpDetalleBody"></tbody>
                    </table>
                </div>
                <div class="text-muted mt-2 cp-msg d-none" id="cpRecortado">
                    Se muestran las primeras 300 filas con problemas. Corrija estas y vuelva a subir el archivo.
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado de la aplicación -->
    <div class="card border-0 shadow-sm mt-3 mx-0 d-none" id="cpPanelAplicado">
        <div class="card-body">
            <div class="fw-bold mb-2"><i class="bi bi-check-circle-fill text-success me-1"></i> Carga aplicada</div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="cpKpisAplicado"></div>
            <div id="cpErroresAplicado"></div>
        </div>
    </div>

</div>

<script>
    window.CP_URL_BASE = '<?= $urlBase ?>';
</script>
<script src="<?= $base ?>/js/modulos/carga_productos.js?v=<?= time() ?>"></script>
