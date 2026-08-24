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
    .cf-paso-num {
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

    .cf-dropzone {
        border: 2px dashed #adb5bd;
        border-radius: .5rem;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: background-color .15s, border-color .15s;
    }

    .cf-dropzone:hover,
    .cf-dropzone.cf-activa {
        border-color: #0d6efd;
        background-color: #f1f6ff;
    }

    .cf-kpi {
        border-radius: .5rem;
        padding: .6rem .9rem;
        min-width: 116px;
    }

    .cf-kpi .cf-kpi-num {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1;
    }

    .cf-kpi .cf-kpi-lbl {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .carga-facturas-scroll {
        max-height: 40vh;
        overflow: auto;
    }

    .carga-facturas-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .cf-msg {
        font-size: .8rem;
    }
</style>

<div class="container-fluid py-3">

    <div class="mb-3 px-3">
        <h5 class="mb-1 fw-bold">
            <i class="bi bi-file-earmark-excel text-success me-2"></i> Carga de Facturas de Venta
        </h5>
        <p class="text-muted small mb-0">
            Cree varias facturas de venta a la vez desde un archivo Excel. Descargue la plantilla,
            escriba una fila por factura en la hoja <strong>Facturas</strong> y sus
            líneas en la hoja <strong>Detalles</strong>. Las facturas se crean en estado
            <strong>borrador</strong> con el secuencial que sigue en cada punto de emisión;
            <strong>nada se envía al SRI</strong> desde aquí. La forma de pago se toma sola de la
            ficha del cliente o, si no tiene, de la configuración del establecimiento —igual que en
            la pantalla de Factura de Venta.
        </p>
    </div>

    <div class="row g-3 mx-0">

        <!-- PASO 1: descargar -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="cf-paso-num bg-primary text-white">1</span>
                        <div>
                            <div class="fw-bold">Descargue la plantilla</div>
                            <div class="text-muted small">
                                Las hojas de datos vienen vacías. Las hojas <code>Ref_</code> traen
                                lo que no se puede adivinar: tarifas de IVA, puntos de emisión,
                                bodegas y vendedores. Clientes y productos no vienen en el libro —se
                                escribe su identificación o código y el sistema los busca en la base.
                            </div>
                        </div>
                    </div>

                    <a href="<?= $urlBase ?>/descargarPlantilla" class="btn btn-success btn-sm shadow-sm">
                        <i class="bi bi-download me-1"></i> Descargar plantilla
                    </a>

                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0 cf-msg">
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
                        <span class="cf-paso-num bg-primary text-white">2</span>
                        <div>
                            <div class="fw-bold">Suba el archivo editado</div>
                            <div class="text-muted small">
                                Primero se revisa por completo. No se crea ninguna factura hasta que
                                usted lo confirme.
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($perm['crear'])): ?>
                        <div class="cf-dropzone" id="cfDropzone">
                            <i class="bi bi-cloud-arrow-up fs-3 text-secondary d-block mb-1"></i>
                            <div class="small fw-semibold" id="cfNombreArchivo">
                                Arrastre el archivo aquí o haga clic para seleccionarlo
                            </div>
                            <div class="text-muted" style="font-size: .72rem;">Formato .xlsx — máximo 20 MB</div>
                        </div>
                        <input type="file" id="cfArchivo" accept=".xlsx,.xls" class="d-none">

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-primary btn-sm shadow-sm" id="cfBtnValidar" disabled>
                                <i class="bi bi-search me-1"></i> Revisar archivo
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="cfBtnLimpiar">
                                Limpiar
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary py-2 px-3 mb-0 cf-msg">
                            No tiene permiso para cargar facturas en este módulo.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 3: resultado de la revisión -->
    <div class="card border-0 shadow-sm mt-3 mx-0 d-none cmg-table-card" id="cfPanelResultado">
        <div class="card-body">

            <div class="d-flex align-items-start gap-2 mb-3">
                <span class="cf-paso-num bg-primary text-white">3</span>
                <div class="flex-grow-1">
                    <div class="fw-bold">Revise y confirme</div>
                    <div class="text-muted small" id="cfSubtituloResultado"></div>
                </div>
            </div>

            <div id="cfErroresGlobales"></div>

            <div class="d-flex flex-wrap gap-2 mb-3" id="cfKpis"></div>

            <div class="d-flex gap-2 mb-3" id="cfAcciones">
                <button type="button" class="btn btn-success btn-sm shadow-sm" id="cfBtnAplicar">
                    <i class="bi bi-check2-circle me-1"></i> Crear facturas
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cfBtnCancelar">
                    Cancelar
                </button>
            </div>

            <ul class="nav nav-tabs mb-2" id="cfTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cfTabFacturas" type="button">
                        Facturas a crear
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#cfTabProblemas" type="button">
                        Problemas <span class="badge bg-danger ms-1 d-none" id="cfBadgeProblemas">0</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="cfTabFacturas">
                    <div class="table-responsive carga-facturas-scroll border rounded">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 110px;">ID_FACTURA</th>
                                    <th style="width: 70px;">Fila</th>
                                    <th style="width: 110px;">Fecha</th>
                                    <th style="width: 230px;">Cliente</th>
                                    <th style="width: 100px;">Punto</th>
                                    <th style="width: 80px;" class="text-end">Líneas</th>
                                    <th style="width: 110px;" class="text-end">Total</th>
                                    <th style="width: 150px;">Forma de pago</th>
                                    <th style="width: 110px;">Estado</th>
                                </tr>
                            </thead>
                            <tbody id="cfFacturasBody"></tbody>
                        </table>
                    </div>
                    <div class="text-muted mt-2 cf-msg d-none" id="cfRecortadoFacturas">
                        Se muestran las primeras 300 facturas del archivo.
                    </div>
                </div>

                <div class="tab-pane fade" id="cfTabProblemas">
                    <div class="table-responsive carga-facturas-scroll border rounded">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 130px;">Hoja</th>
                                    <th style="width: 70px;">Fila</th>
                                    <th style="width: 110px;">ID_FACTURA</th>
                                    <th style="width: 90px;">Tipo</th>
                                    <th>Mensaje</th>
                                </tr>
                            </thead>
                            <tbody id="cfDetalleBody"></tbody>
                        </table>
                    </div>
                    <div class="text-muted mt-2 cf-msg d-none" id="cfRecortado">
                        Se muestran las primeras 300 filas con problemas. Corrija estas y vuelva a subir el archivo.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado de la aplicación -->
    <div class="card border-0 shadow-sm mt-3 mx-0 d-none" id="cfPanelAplicado">
        <div class="card-body">
            <div class="fw-bold mb-2"><i class="bi bi-check-circle-fill text-success me-1"></i> Carga aplicada</div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="cfKpisAplicado"></div>

            <div id="cfCreadasWrap" class="d-none mb-3">
                <div class="fw-semibold small mb-2">Facturas creadas</div>
                <div class="table-responsive carga-facturas-scroll border rounded">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 140px;">ID_FACTURA</th>
                                <th>Número asignado</th>
                            </tr>
                        </thead>
                        <tbody id="cfCreadasBody"></tbody>
                    </table>
                </div>
            </div>

            <div id="cfErroresAplicado"></div>

            <a href="<?= rtrim($base, '/') ?>/modulos/factura-venta" class="btn btn-outline-primary btn-sm mt-2">
                <i class="bi bi-receipt me-1"></i> Ir a Facturas de Venta
            </a>
        </div>
    </div>

</div>

<script>
    window.CF_URL_BASE = '<?= $urlBase ?>';
</script>
<script src="<?= $base ?>/js/modulos/carga_facturas.js?v=<?= time() ?>"></script>
