<?php
/**
 * Modal de trabajo: el cruce propiamente dicho.
 *
 * Pestaña «Conciliación»:
 *   Izquierda  = líneas del estado de cuenta de la procesadora.
 *   Derecha    = cobros con tarjeta registrados en el sistema.
 *   El usuario empareja (o acepta las sugerencias automáticas) y al cerrar elige
 *   a qué forma de cobro (banco) entró el dinero.
 * Pestaña «Asiento contable» (solo con acceso a Contabilidad → Asientos Contables):
 *   el asiento del depósito, con el componente compartido asiento_tab.php.
 * Pestaña «Configuración» (permiso de actualizar): cuentas y valores por defecto de
 *   la procesadora elegida; se guardan por procesadora y valen para las siguientes.
 *
 * Estructura estándar de modal de documento (§9): barra de acciones al inicio del
 * cuerpo, pestañas configurables por usuario y Eliminar a la izquierda del pie.
 */
$ctarVerAsiento = \App\Helpers\AsientoPestana::puedeVer();
// La configuración contable de la procesadora se edita con permiso de actualizar.
$ctarVerConfig  = !empty($perm['actualizar']);
$ctarPestanasOcultables = array_filter([
    'ctar-pane-asiento' => $ctarVerAsiento ? 'Asiento contable' : null,
    'ctar-pane-config'  => $ctarVerConfig ? 'Configuración' : null,
]);
?>
<style>
/*
 * Modal a alto fijo, sin scroll en el cuerpo: cabecera, barra de acciones, datos,
 * totales y pie quedan quietos; solo se desplazan las dos listas del cruce (y la
 * pestaña del asiento, dentro de sí misma). Antes el cuerpo entero hacía scroll
 * además de las listas y todo se movía al recorrerlas.
 * Solo desde lg: por debajo el modal es pantalla completa con las columnas
 * apiladas y necesita el scroll normal del cuerpo.
 * Las clases de las listas NO llevan "-scroll": app.css las estiraría (app-shell).
 *
 * Pestaña Conciliación en dos pasos, en tarjetas separadas:
 *   1. Encabezado + perfil + archivo (siempre).
 *   2. Estado de cuenta | Cobros del sistema, y totales — solo con la conciliación
 *      ya guardada (#ctar-m-paso2, lo muestra CTAR_habilitarAcciones).
 */
@media (min-width: 992px) {
    /* Más ancho que modal-xl (1140px): las dos listas del cruce van lado a lado. */
    #modalConciliacion .modal-dialog { max-width: min(1600px, 96vw); }
    /* Casi todo el alto de la ventana: lo que sobra se lo llevan las dos listas. */
    #modalConciliacion:not(.ctar-m-sin-cruce) .modal-dialog { margin-top: .5rem; margin-bottom: .5rem; height: calc(100vh - 1rem); }
    #modalConciliacion .modal-content { height: 100%; }
    #modalConciliacion .modal-body { display: flex; flex-direction: column; overflow: hidden; }
    #modalConciliacion .modal-body > .tab-content { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
    /* Conciliación nueva (sin paso 2 todavía): el modal se ajusta a su contenido. */
    #modalConciliacion.ctar-m-sin-cruce .modal-content { height: auto; }
    #modalConciliacion #ctar-pane-cruce.active { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #modalConciliacion #ctar-pane-asiento.active,
    #modalConciliacion #ctar-pane-config.active { flex: 1 1 auto; min-height: 0; overflow: auto; }
    #modalConciliacion .ctar-m-card { flex-shrink: 0; }
    #modalConciliacion .ctar-m-paso2:not(.d-none) { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #modalConciliacion .ctar-m-paso2 > .card { flex-shrink: 0; }
    #modalConciliacion .ctar-m-cruce { flex: 1 1 auto; min-height: 0; flex-wrap: nowrap; }
    #modalConciliacion .ctar-m-cruce > [class*="col-"] { display: flex; flex-direction: column; min-height: 0; }
    #modalConciliacion .ctar-m-card-lista { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #modalConciliacion .ctar-m-card-lista > * { flex-shrink: 0; }
    #modalConciliacion .ctar-m-card-lista > .ctar-m-lista { flex: 1 1 auto; min-height: 0; max-height: none; }
}
/* Fila única del encabezado: mismo alto explícito en todos los controles (§9) —
   select, fecha, número, archivo y botón no rinden igual con los -sm de Bootstrap. */
#ctar-m-encabezado .form-select,
#ctar-m-encabezado .form-control,
#ctar-m-encabezado .btn { height: 28px; font-size: .75rem; }
#ctar-m-encabezado .form-control { padding-top: .2rem; padding-bottom: .2rem; }
#ctar-m-encabezado .form-label { font-size: .7rem; }
#ctar-m-archivo-info:empty { display: none; }
#modalConciliacion .ctar-m-lista { max-height: 65vh; overflow: auto; }
/* Filtros Desde/Hasta en el encabezado de cada lista: compactos, mismo alto. */
#modalConciliacion .ctar-m-fechas .form-control { width: 118px; height: 26px; font-size: .72rem; padding: .1rem .35rem; }
#modalConciliacion #ctar-m-buscar-cobro { height: 26px; font-size: .72rem; }
#modalConciliacion .ctar-m-lista thead th { position: sticky; top: 0; z-index: 1; }
</style>
<div class="modal fade" id="modalConciliacion" tabindex="-1" aria-labelledby="modalConciliacionLabel" aria-hidden="true"
     data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modalConciliacionLabel">
                    <i class="bi bi-credit-card-2-front me-2"></i><span id="ctar-m-titulo">Nueva conciliación</span>
                </h5>
                <span class="badge d-none ms-2" id="ctar-m-estado" style="font-size:.72rem;"></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">

                <!-- ── Barra de acciones de documento (§9: al inicio del cuerpo) ── -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap" id="ctar-m-acciones">
                    <!-- Línea manual y Cruzar automáticamente viven en el encabezado de la tarjeta
                         «Estado de cuenta de la procesadora», junto a la lista sobre la que actúan. -->
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="CTAR_pdfConciliacion()"
                            title="Comprobante en PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="CTAR_excelConciliacion()"
                            title="Detalle en Excel"><i class="bi bi-file-earmark-excel"></i></button>
                    <button type="button" class="btn btn-outline-warning btn-sm d-none" id="ctar-btn-anular" onclick="CTAR_anular()"
                            title="Anular la conciliación: revierte el asiento y los cobros vuelven a pendientes">
                        <i class="bi bi-slash-circle me-1"></i>Anular
                    </button>
                </div>

                <!-- ── Pestañas ── -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="ctar-m-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link active py-2 small" id="ctar-tab-cruce-btn"
                                    data-bs-toggle="tab" data-bs-target="#ctar-pane-cruce" role="tab">
                                <i class="bi bi-arrow-left-right me-1"></i>Conciliación
                            </button>
                        </li>
                        <?php if ($ctarVerAsiento): // solo con acceso a Contabilidad → Asientos Contables ?>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link py-2 small" id="ctar-tab-asiento-btn"
                                    data-bs-toggle="tab" data-bs-target="#ctar-pane-asiento" role="tab">
                                <i class="bi bi-calculator me-1"></i>Asiento contable
                            </button>
                        </li>
                        <?php endif; ?>
                        <?php if ($ctarVerConfig): // cuentas y valores por defecto de la procesadora ?>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link py-2 small" id="ctar-tab-config-btn"
                                    data-bs-toggle="tab" data-bs-target="#ctar-pane-config" role="tab">
                                <i class="bi bi-gear me-1"></i>Configuración
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($ctarPestanasOcultables): ?>
                    <div class="ms-auto pb-1">
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas(
                            $ctarPestanasOcultables,
                            $vistaConfig ?? [],
                            $rutaModulo
                        ) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="tab-content border-top">
                <!-- ═══ Pestaña: Conciliación ═══ -->
                <div class="tab-pane fade show active p-2 bg-light" id="ctar-pane-cruce" role="tabpanel">

                <!-- ── Paso 1: encabezado + archivo (tarjeta propia, siempre visible) ── -->
                <div class="card border-0 shadow-sm rounded-3 mb-2 ctar-m-card">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <span class="fw-bold small"><i class="bi bi-1-circle-fill me-1 text-primary"></i>Encabezado y estado de cuenta</span>
                </div>
                <div class="card-body px-3 py-2">
                    <input type="hidden" id="ctar-m-id">
                    <!-- Encabezado + estado de cuenta en UNA fila (§9, tarjeta de control): flexbox con
                         ancho fijo por campo, no el grid .row/.col. Si no cabe, salta de línea de forma
                         predecible; perfil + archivo + Cargar van agrupados para no separarse. -->
                    <div class="d-flex flex-wrap align-items-start gap-2" id="ctar-m-encabezado">
                        <div style="width:150px;">
                            <label class="form-label small fw-bold text-muted mb-1 d-block">Procesadora</label>
                            <select id="ctar-m-procesadora" class="form-select form-select-sm shadow-none border"></select>
                        </div>
                        <div style="width:125px;">
                            <label class="form-label small fw-bold text-muted mb-1 d-block">Fecha depósito</label>
                            <input type="date" id="ctar-m-fecha" class="form-control form-control-sm shadow-none border">
                        </div>
                        <!-- El período ya no se muestra: los cobros pendientes se ofrecen sin
                             límite inferior de fecha. Se conservan ocultos para no perder el
                             valor guardado en conciliaciones anteriores. -->
                        <input type="hidden" id="ctar-m-desde">
                        <input type="hidden" id="ctar-m-hasta">
                        <div style="width:170px;">
                            <label class="form-label small fw-bold text-muted mb-1 d-block">Depositado en</label>
                            <select id="ctar-m-destino" class="form-select form-select-sm shadow-none border"></select>
                        </div>
                        <div style="width:110px;">
                            <label class="form-label small fw-bold text-muted mb-1 d-block">Neto depositado</label>
                            <input type="number" step="0.01" id="ctar-m-neto" class="form-control form-control-sm shadow-none border text-end"
                                   placeholder="0.00" oninput="CTAR_recalcularDiferencia()">
                        </div>

                        <!-- Estado de cuenta: se lee con el botón Cargar (una conciliación nueva se crea
                             y carga el archivo en el mismo paso). -->
                        <!-- El grupo ocupa el resto de la fila y el perfil crece hasta llenarlo
                             (mínimo 180px); archivo y botón mantienen su ancho. -->
                        <div class="d-flex flex-wrap align-items-start gap-2" id="ctar-m-carga" style="flex:1 1 auto;">
                            <div style="flex:1 1 180px;min-width:180px;">
                                <label class="form-label small fw-bold text-muted mb-1 d-block">Perfil de lectura</label>
                                <select id="ctar-m-perfil" class="form-select form-select-sm shadow-none border"
                                        title="Formato del archivo de la procesadora"></select>
                            </div>
                            <div style="width:230px;">
                                <label class="form-label small fw-bold text-muted mb-1 d-block">Estado de cuenta (Excel, CSV o PDF)</label>
                                <input type="file" id="ctar-m-archivo" class="form-control form-control-sm shadow-none border"
                                       accept=".xlsx,.xls,.csv,.pdf">
                                <div class="small text-muted lh-sm text-truncate mt-1" id="ctar-m-archivo-info" style="font-size:.7rem;"></div>
                            </div>
                            <div>
                                <label class="form-label small fw-bold text-muted mb-1 d-block">&nbsp;</label>
                                <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" id="ctar-btn-cargar"
                                        onclick="CTAR_cargarArchivo()" title="Guardar el encabezado y leer el estado de cuenta">
                                    <i class="bi bi-upload me-1"></i>Cargar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Aviso del estado contable: por qué se generará (o no) el asiento -->
                    <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small d-none" id="ctar-m-aviso-conta">
                        <i class="bi bi-info-circle me-1"></i><span id="ctar-m-aviso-conta-texto"></span>
                    </div>
                </div>
                </div><!-- /paso 1 -->

                <!-- ── Paso 2: el cruce. Aparece cuando la conciliación ya está guardada
                     (tras Guardar con el archivo). Cada lista y los totales en su tarjeta. ── -->
                <div class="ctar-m-paso2 d-none" id="ctar-m-paso2">
                <div class="row g-2 ctar-m-cruce mb-2">
                    <!-- Izquierda: estado de cuenta -->
                    <div class="col-lg-7">
                      <div class="card border-0 shadow-sm rounded-3 overflow-hidden ctar-m-card-lista">
                        <div class="card-header bg-white px-3 py-2 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fw-bold small"><i class="bi bi-filetype-csv me-1 text-primary"></i>Estado de cuenta de la procesadora</span>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary px-2" onclick="CTAR_agregarLineaManual()"
                                            id="ctar-btn-linea" title="Línea manual: agregar a mano una línea del estado de cuenta">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-info px-2" onclick="CTAR_sugerir()"
                                            id="ctar-btn-sugerir" title="Cruzar automáticamente: emparejar líneas y cobros por autorización, referencia o monto">
                                        <i class="bi bi-magic"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1 ctar-m-fechas" title="Filtra las líneas por fecha del movimiento; los totales se calculan con lo filtrado (al cerrar se contabiliza todo lo cruzado)">
                                <span class="small text-muted">Desde</span>
                                <input type="date" id="ctar-f-lineas-desde" class="form-control form-control-sm shadow-none border"
                                       onchange="CTAR_filtrarFechas('lineas')">
                                <span class="small text-muted">Hasta</span>
                                <input type="date" id="ctar-f-lineas-hasta" class="form-control form-control-sm shadow-none border"
                                       onchange="CTAR_filtrarFechas('lineas')">
                            </div>
                            <span class="small text-muted" id="ctar-m-resumen-lineas">0 líneas</span>
                        </div>
                        <div class="ctar-m-lista">
                            <table class="table table-sm table-hover mb-0 align-middle" style="min-width:720px;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Fecha</th>
                                        <th>Autorización</th>
                                        <th class="text-end">Bruto</th>
                                        <th class="text-end">Comisión</th>
                                        <th class="text-end">Retenc.</th>
                                        <th class="text-end">Neto</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="ctar-m-tbody-lineas">
                                    <tr><td colspan="8" class="text-center py-4 text-muted small">
                                        Cargue el estado de cuenta o agregue líneas a mano.
                                    </td></tr>
                                </tbody>
                            </table>
                        </div>
                      </div>
                    </div>

                    <!-- Derecha: cobros del sistema -->
                    <div class="col-lg-5">
                      <div class="card border-0 shadow-sm rounded-3 overflow-hidden ctar-m-card-lista">
                        <div class="card-header bg-white px-3 py-2 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="fw-bold small" title="Seleccione una línea del estado de cuenta y luego el cobro que le corresponde">
                                <i class="bi bi-receipt me-1 text-success"></i>Cobros del sistema</span>
                            <div class="d-flex align-items-center gap-1 ctar-m-fechas" title="Filtra los cobros por la fecha de la factura; los totales cuentan solo los cruces de esos cobros (al cerrar se contabiliza todo lo cruzado)">
                                <span class="small text-muted">Desde</span>
                                <input type="date" id="ctar-f-cobros-desde" class="form-control form-control-sm shadow-none border"
                                       onchange="CTAR_filtrarFechas('cobros')">
                                <span class="small text-muted">Hasta</span>
                                <input type="date" id="ctar-f-cobros-hasta" class="form-control form-control-sm shadow-none border"
                                       onchange="CTAR_filtrarFechas('cobros')">
                            </div>
                            <span class="small text-muted" id="ctar-m-resumen-cobros">0 disponibles</span>
                            <input type="search" class="form-control form-control-sm shadow-none border w-100" id="ctar-m-buscar-cobro"
                                   placeholder="Filtrar por cliente, documento, fecha o valor..." oninput="CTAR_filtrarCobros(this.value)">
                        </div>
                        <div class="ctar-m-lista">
                            <table class="table table-sm table-hover mb-0 align-middle" style="min-width:500px;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Documento</th>
                                        <th>Fecha fact.</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Monto</th>
                                        <th class="text-center">Días</th>
                                    </tr>
                                </thead>
                                <tbody id="ctar-m-tbody-cobros">
                                    <tr><td colspan="5" class="text-center py-4 text-muted small">Sin cobros pendientes.</td></tr>
                                </tbody>
                            </table>
                        </div>
                      </div>
                    </div>
                </div>

                <!-- ── Totales: una sola línea compacta «etiqueta valor» ── -->
                <div class="card border-0 shadow-sm rounded-3 px-3 py-1">
                    <div class="d-flex flex-wrap align-items-center justify-content-end column-gap-3 row-gap-1 small ctar-m-totales">
                        <span class="text-warning d-none me-auto" id="ctar-m-t-aviso-filtro"
                              title="Los totales corresponden a lo filtrado (fechas del estado de cuenta, fechas o buscador de cobros)">
                            <i class="bi bi-funnel-fill me-1"></i>Totales filtrados: al cerrar se contabiliza todo lo cruzado
                            (neto $<span id="ctar-m-t-aviso-total">0.00</span>)
                        </span>
                        <span><span class="text-muted">Bruto conciliado</span> <strong>$<span id="ctar-m-t-bruto">0.00</span></strong></span>
                        <div class="vr"></div>
                        <span><span class="text-muted">Comisión</span> <strong class="text-secondary">$<span id="ctar-m-t-comision">0.00</span></strong></span>
                        <span><span class="text-muted">IVA comisión</span> <strong class="text-secondary">$<span id="ctar-m-t-iva">0.00</span></strong></span>
                        <span><span class="text-muted">Retenciones</span> <strong class="text-secondary">$<span id="ctar-m-t-retenciones">0.00</span></strong></span>
                        <div class="vr"></div>
                        <span><span class="text-muted">Neto calculado</span> <strong class="text-primary">$<span id="ctar-m-t-neto">0.00</span></strong></span>
                        <span><span class="text-muted">Diferencia</span> <strong id="ctar-m-t-diferencia-wrap">$<span id="ctar-m-t-diferencia">0.00</span></strong></span>
                    </div>
                </div>
                </div><!-- /paso 2 -->
                </div><!-- /ctar-pane-cruce -->

                <?php if ($ctarVerAsiento): ?>
                <!-- ═══ Pestaña: Asiento contable ═══ -->
                <div class="tab-pane fade p-3" id="ctar-pane-asiento" role="tabpanel">
                    <div class="alert alert-light border small d-flex align-items-center gap-2 mb-2 py-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Asiento del depósito: <em>Banco</em> (neto) + comisión, IVA y retenciones en el Debe, contra la
                            <em>cuenta puente</em> de la forma de cobro (bruto) en el Haber. Se genera al conciliar y cerrar.</span>
                    </div>
                    <?php $prefijo = 'ctar'; require MVC_APP . '/views/partials/asiento_tab.php'; ?>
                </div><!-- /ctar-pane-asiento -->
                <?php endif; ?>

                <?php if ($ctarVerConfig): ?>
                <!-- ═══ Pestaña: Configuración de la procesadora ═══
                     Se guarda por procesadora (conciliacion_tarjetas_config) y vale para todas
                     sus conciliaciones, no solo para la que está abierta. La cuenta puente NO
                     se configura aquí: es la cuenta de la propia forma de cobro. -->
                <div class="tab-pane fade p-3" id="ctar-pane-config" role="tabpanel">
                    <div class="alert alert-info py-2 px-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Esta configuración es de la procesadora <strong id="ctar-cfg-procesadora-nombre">—</strong>: al guardarla
                        se aplica a esta y a todas sus conciliaciones siguientes. La contabilidad es opcional: sin cuentas,
                        el módulo concilia igual pero no genera el asiento del depósito.
                    </div>

                    <!-- Estado de la cuenta puente de esta procesadora -->
                    <div class="p-2 border rounded-3 mb-3" id="ctar-cfg-puente">
                        <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:.65rem;">Cuenta puente (viene de Formas de Cobro/Pago)</div>
                        <div id="ctar-cfg-puente-texto" class="small">—</div>
                    </div>

                    <div class="row g-2">
                        <?php foreach ([
                            'comision' => 'Cuenta de comisión (gasto)',
                            'iva'      => 'Cuenta de IVA de la comisión',
                            'retir'    => 'Cuenta de retención de renta',
                            'retiva'   => 'Cuenta de retención de IVA',
                        ] as $ctarCfgCampo => $ctarCfgEtiqueta): ?>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1"><?= $ctarCfgEtiqueta ?></label>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                       id="ctar-cfg-<?= $ctarCfgCampo ?>-txt" data-target="ctar-cfg-<?= $ctarCfgCampo ?>" placeholder="Buscar cuenta..." autocomplete="off">
                                <input type="hidden" id="ctar-cfg-<?= $ctarCfgCampo ?>">
                                <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                     style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">% comisión</label>
                            <input type="number" step="0.0001" id="ctar-cfg-pc" class="form-control form-control-sm shadow-none border text-end">
                            <div class="form-text" style="font-size:.68rem;">Solo para precalcular; siempre editable.</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">% IVA</label>
                            <input type="number" step="0.0001" id="ctar-cfg-pi" class="form-control form-control-sm shadow-none border text-end">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Días de liquidación</label>
                            <input type="number" id="ctar-cfg-dias" class="form-control form-control-sm shadow-none border text-end" value="2">
                            <div class="form-text" style="font-size:.68rem;">Pasados estos días, el cobro se marca atrasado.</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Tolerancia</label>
                            <input type="number" step="0.01" id="ctar-cfg-tol" class="form-control form-control-sm shadow-none border text-end" value="0.05">
                            <div class="form-text" style="font-size:.68rem;">Descuadre aceptado al cerrar.</div>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-primary btn-sm" onclick="CTAR_guardarConfig()">
                            <i class="bi bi-save me-1"></i>Guardar configuración
                        </button>
                    </div>
                </div><!-- /ctar-pane-config -->
                <?php endif; ?>
                </div><!-- /tab-content -->
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="ctar-btn-eliminar" onclick="CTAR_eliminar()">
                            <i class="bi bi-trash3 me-1"></i>Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="ctar-btn-guardar" onclick="CTAR_guardar()">
                        <i class="bi bi-save me-1"></i>Guardar
                    </button>
                    <button type="button" class="btn btn-success btn-sm px-3 shadow-sm" id="ctar-btn-conciliar" onclick="CTAR_cerrarConciliacion()">
                        <i class="bi bi-check2-circle me-1"></i>Conciliar y cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- ═══ Sub-modal: sugerencias de «Cruzar automáticamente» ═══
     Nada se cruza hasta que el usuario confirma. Las de solo-monto (menos confiables)
     llegan sin marcar. -->
<div class="modal fade" id="modalSugerenciasTarjeta" tabindex="-1" aria-hidden="true" style="z-index:5080;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-magic me-2 text-info"></i>Cruces sugeridos</h5>
                <button type="button" class="btn-close" aria-label="Cerrar" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-3 py-2 small text-muted border-bottom bg-light">
                    Revise cada emparejamiento y desmarque los que no correspondan. Los encontrados
                    <strong>solo por monto</strong> son los menos seguros: llegan sin marcar.
                </div>
                <div class="ctar-sug-lista" style="max-height:60vh;overflow:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                            <tr>
                                <th class="ps-3" style="width:36px;">
                                    <input type="checkbox" class="form-check-input" id="ctar-sug-todas" title="Marcar / desmarcar todas">
                                </th>
                                <th>Línea del estado de cuenta</th>
                                <th class="text-end">Bruto</th>
                                <th>Cobro(s) del sistema</th>
                                <th class="text-end">Monto</th>
                                <th>Criterio</th>
                            </tr>
                        </thead>
                        <tbody id="ctar-sug-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2 justify-content-between">
                <span class="small text-muted" id="ctar-sug-resumen"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-info btn-sm text-white" id="ctar-sug-aplicar" onclick="CTAR_aplicarSugerencias()">
                        <i class="bi bi-check2-all me-1"></i>Cruzar seleccionadas
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Sub-modal: línea manual / edición de línea ═══ -->
<div class="modal fade" id="modalLineaTarjeta" tabindex="-1" aria-hidden="true" style="z-index:5080;">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-pencil-square me-2"></i><span id="ctar-linea-titulo">Línea del estado de cuenta</span></h5>
                <button type="button" class="btn-close" aria-label="Cerrar" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="ctar-linea-id">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Fecha</label>
                        <input type="date" id="ctar-linea-fecha" class="form-control form-control-sm shadow-none border">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Tipo</label>
                        <select id="ctar-linea-tipo" class="form-select form-select-sm shadow-none border">
                            <option value="transaccion">Transacción individual</option>
                            <option value="deposito">Depósito consolidado</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Autorización</label>
                        <input type="text" id="ctar-linea-autorizacion" class="form-control form-control-sm shadow-none border" maxlength="60">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Referencia</label>
                        <input type="text" id="ctar-linea-referencia" class="form-control form-control-sm shadow-none border" maxlength="120">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted mb-1">Descripción</label>
                        <input type="text" id="ctar-linea-descripcion" class="form-control form-control-sm shadow-none border" maxlength="500">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Bruto</label>
                        <input type="number" step="0.01" id="ctar-linea-bruto" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Comisión</label>
                        <input type="number" step="0.01" id="ctar-linea-comision" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">IVA comisión</label>
                        <input type="number" step="0.01" id="ctar-linea-iva" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Retención renta</label>
                        <input type="number" step="0.01" id="ctar-linea-retir" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Retención IVA</label>
                        <input type="number" step="0.01" id="ctar-linea-retiva" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Otros descuentos</label>
                        <input type="number" step="0.01" id="ctar-linea-otros" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-12">
                        <div class="p-2 border rounded-3 bg-light d-flex justify-content-between">
                            <span class="small fw-bold text-muted">Neto de la línea</span>
                            <span class="fw-bold text-primary">$<span id="ctar-linea-neto">0.00</span></span>
                        </div>
                    </div>
                </div>
                <div class="form-text mt-2" style="font-size:.7rem;">
                    Las retenciones se digitan tal como vienen en el comprobante de la procesadora; el sistema no las calcula.
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="CTAR_guardarLinea()">
                    <i class="bi bi-save me-1"></i>Guardar línea
                </button>
            </div>
        </div>
    </div>
</div>
