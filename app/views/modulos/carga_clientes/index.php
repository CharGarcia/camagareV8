<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $columnasElegibles */
/** @var bool $soloPropios */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$puedeEscribir = !empty($perm['crear']) || !empty($perm['actualizar']);
?>

<!-- Página con pasos ENCIMA de la tabla de resultados: el app-shell (título +
     una sola tabla a pantalla completa) no aplica. -->
<script>
    document.body.classList.add('cmg-no-app-shell');
</script>

<style>
    .cc-paso-num {
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

    .cc-dropzone {
        border: 2px dashed #adb5bd;
        border-radius: .5rem;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: background-color .15s, border-color .15s;
    }

    .cc-dropzone:hover,
    .cc-dropzone.cc-activa {
        border-color: #0d6efd;
        background-color: #f1f6ff;
    }

    .cc-kpi {
        border-radius: .5rem;
        padding: .6rem .9rem;
        min-width: 116px;
    }

    .cc-kpi .cc-kpi-num {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1;
    }

    .cc-kpi .cc-kpi-lbl {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .carga-clientes-scroll {
        max-height: 45vh;
        overflow: auto;
    }

    .carga-clientes-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .cc-msg {
        font-size: .8rem;
    }

    .cc-checks {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
        column-gap: .75rem;
    }
</style>

<div class="container-fluid py-3">

    <div class="mb-3 px-3">
        <h5 class="mb-1 fw-bold">
            <i class="bi bi-people text-success me-2"></i> Carga de Clientes
        </h5>
        <p class="text-muted small mb-0">
            Descargue a Excel todos sus clientes, edítelos y vuelva a subirlos: las identificaciones
            que ya existen se <strong>actualizan</strong> y las nuevas se <strong>crean</strong>.
            Las filas que no cambió se ignoran. Si solo quiere cambiar algunos datos (por ejemplo el
            vendedor o el plazo), elija <strong>Actualizar solo algunas columnas</strong>.
            Desde el Excel no se elimina nada; para dejar de usar un cliente márquelo como
            <strong>Inactivo</strong>.
        </p>
    </div>

    <div class="row g-3 mx-0">

        <!-- PASO 1: descargar -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="cc-paso-num bg-primary text-white">1</span>
                        <div>
                            <div class="fw-bold">Descargue la plantilla</div>
                            <div class="text-muted small">
                                <?php if ($soloPropios): ?>
                                    Viene con los clientes que usted registró (no tiene acceso total).
                                <?php else: ?>
                                    Viene con todos los clientes de la empresa.
                                <?php endif; ?>
                                Edite lo que necesite y agregue los nuevos al final.
                            </div>
                        </div>
                    </div>

                    <a href="<?= $urlBase ?>/descargarPlantilla" class="btn btn-success btn-sm shadow-sm">
                        <i class="bi bi-download me-1"></i> Descargar plantilla
                    </a>

                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0 cc-msg">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>No borre ni agregue hojas</strong> al libro, ni cambie los encabezados
                        de las columnas. La columna <strong>IDENTIFICACION</strong> identifica al cliente
                        y no se puede cambiar.
                    </div>
                </div>
            </div>
        </div>

        <!-- PASO 2: subir -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="cc-paso-num bg-primary text-white">2</span>
                        <div>
                            <div class="fw-bold">Suba el archivo editado</div>
                            <div class="text-muted small">
                                Primero se revisa por completo. No se guarda nada hasta que usted lo confirme.
                            </div>
                        </div>
                    </div>

                    <?php if ($puedeEscribir): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ccModo" id="ccModoCompleta" value="completa" checked>
                                <label class="form-check-label small" for="ccModoCompleta">
                                    <strong>Carga completa</strong> — crea las identificaciones nuevas y actualiza
                                    todas las columnas de los existentes (una celda vacía borra ese dato).
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ccModo" id="ccModoParcial" value="parcial">
                                <label class="form-check-label small" for="ccModoParcial">
                                    <strong>Actualizar solo algunas columnas</strong> — cambia únicamente lo que
                                    marque; el resto se conserva y no se crean clientes nuevos.
                                </label>
                            </div>
                        </div>

                        <div class="border rounded p-2 mb-3 d-none" id="ccPanelColumnas">
                            <div class="d-flex align-items-center mb-1">
                                <span class="small fw-semibold flex-grow-1">Columnas que se actualizarán</span>
                                <button type="button" class="btn btn-link btn-sm p-0 me-2 cc-msg" data-cc-marcar="1">Todas</button>
                                <button type="button" class="btn btn-link btn-sm p-0 cc-msg" data-cc-marcar="0">Ninguna</button>
                            </div>
                            <div class="cc-checks">
                                <?php foreach ($columnasElegibles as $col => $rotulo): ?>
                                    <div class="form-check">
                                        <input class="form-check-input cc-col" type="checkbox" value="<?= htmlspecialchars($col) ?>" id="ccCol_<?= htmlspecialchars($col) ?>">
                                        <label class="form-check-label small" for="ccCol_<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($rotulo) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="cc-dropzone" id="ccDropzone">
                            <i class="bi bi-cloud-arrow-up fs-3 text-secondary d-block mb-1"></i>
                            <div class="small fw-semibold" id="ccNombreArchivo">
                                Arrastre el archivo aquí o haga clic para seleccionarlo
                            </div>
                            <div class="text-muted" style="font-size: .72rem;">Formato .xlsx — máximo 20 MB</div>
                        </div>
                        <input type="file" id="ccArchivo" accept=".xlsx" class="d-none">

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-primary btn-sm shadow-sm" id="ccBtnValidar" disabled>
                                <i class="bi bi-search me-1"></i> Revisar archivo
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="ccBtnLimpiar">
                                Limpiar
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary py-2 px-3 mb-0 cc-msg">
                            No tiene permiso para cargar ni modificar clientes en este módulo.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 3: resultado de la revisión -->
    <div class="card border-0 shadow-sm mt-3 mx-0 d-none" id="ccPanelResultado">
        <div class="card-body">

            <div class="d-flex align-items-start gap-2 mb-3">
                <span class="cc-paso-num bg-primary text-white">3</span>
                <div class="flex-grow-1">
                    <div class="fw-bold">Revise y confirme</div>
                    <div class="text-muted small" id="ccSubtituloResultado"></div>
                </div>
            </div>

            <div id="ccErroresGlobales"></div>

            <div class="d-flex flex-wrap gap-2 mb-3" id="ccKpis"></div>

            <div class="d-flex gap-2 mb-3" id="ccAcciones">
                <button type="button" class="btn btn-success btn-sm shadow-sm" id="ccBtnAplicar">
                    <i class="bi bi-check2-circle me-1"></i> Aplicar cambios
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="ccBtnCancelar">
                    Cancelar
                </button>
            </div>

            <div id="ccDetalleWrap" class="d-none">
                <div class="fw-semibold small mb-2">Detalle de filas con problemas</div>
                <div class="table-responsive carga-clientes-scroll border rounded">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Fila</th>
                                <th style="width: 140px;">Identificación</th>
                                <th style="width: 220px;">Nombre</th>
                                <th style="width: 90px;">Tipo</th>
                                <th>Mensaje</th>
                            </tr>
                        </thead>
                        <tbody id="ccDetalleBody"></tbody>
                    </table>
                </div>
                <div class="text-muted mt-2 cc-msg d-none" id="ccRecortado">
                    Se muestran las primeras 300 filas con problemas. Corrija estas y vuelva a subir el archivo.
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado de la aplicación -->
    <div class="card border-0 shadow-sm mt-3 mx-0 d-none" id="ccPanelAplicado">
        <div class="card-body">
            <div class="fw-bold mb-2"><i class="bi bi-check-circle-fill text-success me-1"></i> Carga aplicada</div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="ccKpisAplicado"></div>
            <div id="ccErroresAplicado"></div>
        </div>
    </div>

</div>

<script>
    window.CC_URL_BASE = '<?= $urlBase ?>';
</script>
<script src="<?= $base ?>/js/modulos/carga_clientes.js?v=<?= asset_ver('/js/modulos/carga_clientes.js') ?>"></script>
