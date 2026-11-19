<?php
/** @var array $vistaConfig @var string $rutaModulo @var array $metodos @var array $permProductos */
// Ícono «?» con la explicación en una ventanita (popover), igual que en Configuración de Producción.
// Va junto a la etiqueta, fuera de ella. El texto es HTML fijo de esta vista (no del usuario).
$ayudaPfo = fn(string $titulo, string $html): string =>
    '<span class="pfo-ayuda text-primary ms-1" role="button" tabindex="0" aria-label="Ayuda: ' . htmlspecialchars($titulo) . '"'
    . ' data-bs-toggle="popover" data-bs-html="true" data-bs-placement="top"'
    . ' data-bs-title="' . htmlspecialchars($titulo) . '" data-bs-content="' . htmlspecialchars($html) . '">'
    . '<i class="bi bi-question-circle-fill"></i></span>';
$pestanasFormula = [
    'pane-pfo-insumos'  => 'Insumos',
    'pane-pfo-salidas'  => 'Otras salidas',
    'pane-pfo-costos'   => 'Mano de obra y CIF',
    'pane-pfo-instr'    => 'Instrucciones',
    'pane-pfo-estimado' => 'Costo estimado',
];
// Pestaña «Información» (pieza común: quién creó/modificó y el historial de la fórmula).
$pestanasFormula += \App\Helpers\InformacionRegistro::pestanaConfigurable('pfo');
// Ayuda de la columna «Forma» (igual en Insumos y en Otras salidas).
$ayudaForma = $ayudaPfo('Forma',
    '<p>Cómo crece la cantidad de la línea cuando la orden produce más o menos de lo que <b>rinde</b> la fórmula. Ejemplo: la fórmula rinde 100 y la orden pide 250.</p>'
    . '<ul><li><b>Proporcional</b> (la normal): la cantidad es para lo que rinde la fórmula y crece en la misma proporción. 5 kg → 12,5 kg.</li>'
    . '<li><b>% del lote</b>: un porcentaje de la cantidad a producir (fórmulas de proceso: mezclas, jabones, granel). 60 % de 250 kg → 150 kg. Conviene que producto e insumos estén en la misma medida.</li>'
    . '<li><b>Fija por orden</b>: la misma cantidad en cada orden, produzca lo que produzca (una limpieza, un troquel). 1 litro → 1 litro.</li></ul>'
    . '<p class="mb-0">La <b>merma %</b> se suma al final en las tres formas.</p>');
// Ayudas de las columnas «Merma %» y «Lote» de Insumos.
$ayudaMerma = $ayudaPfo('Merma %',
    '<p>La pérdida <b>normal</b> del insumo al fabricar: lo que se evapora, se queda en la máquina o se recorta. Se suma a la cantidad que pide cada orden.</p>'
    . '<p>Ejemplo: 12,5 kg de harina con 2 % de merma → la orden pide <b>12,75 kg</b>.</p>'
    . '<p class="mb-0">Su costo queda dentro del costo del producto. La pérdida fuera de lo normal se registra aparte al cerrar la orden (<i>merma anormal</i>) y va a gasto.</p>');
$ayudaLote = $ayudaPfo('Lote',
    '<p>Si se marca y el insumo maneja lotes, al <b>consumir</b>lo en una orden hay que indicar de qué lote sale; sin lote no deja registrar el consumo. Así se sabe qué lote de materia prima terminó en qué lote producido.</p>'
    . '<p class="mb-0">Sin marcar, el sistema toma solo el lote que vence primero. Si en Configuración de Producción está encendido <i>Exigir lote en los insumos</i>, se exige en todos aunque aquí no esté marcado.</p>');
?>
<div class="modal fade" id="modalFormula" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-diagram-3 text-primary me-2"></i><span id="pfo-titulo">Nueva fórmula</span>
                    <span id="pfo-badge" class="ms-2"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Barra de acciones superior (como Facturas de Venta): acciones de la fórmula guardada
                     y, al final, crear productos al vuelo. -->
                <div id="pfo-acciones" class="px-3 py-2 bg-light border-bottom d-none d-flex gap-1 align-items-center flex-wrap">
                    <div id="pfo-acciones-formula" class="d-none d-flex gap-1 align-items-center flex-wrap">
                        <button type="button" id="pfo-btn-activar" class="btn btn-outline-success btn-sm d-none" onclick="window.PFO_activar()" title="Dejarla vigente para producir">
                            <i class="bi bi-check2-circle me-1"></i> Activar
                        </button>
                        <button type="button" id="pfo-btn-inactivar" class="btn btn-outline-secondary btn-sm d-none" onclick="window.PFO_inactivar()" title="Dejar de usarla en órdenes nuevas">
                            <i class="bi bi-pause-circle me-1"></i> Inactivar
                        </button>
                        <div class="vr mx-1"></div>
                        <button type="button" id="pfo-btn-version" class="btn btn-outline-primary btn-sm" onclick="window.PFO_nuevaVersion()" title="Copiar como nueva versión en borrador">
                            <i class="bi bi-files me-1"></i> Nueva versión
                        </button>
                        <span id="pfo-aviso-usada" class="small text-muted ms-2 d-none"><i class="bi bi-lock me-1"></i>Ya se usó en órdenes: para cambiarla cree una nueva versión.</span>
                    </div>
                    <?php if (!empty($permProductos['crear'])): ?>
                        <div id="pfo-sep-catalogos" class="vr mx-1 d-none"></div>
                        <button type="button" id="pfo-btn-nuevo-producto" class="btn btn-outline-primary btn-sm px-2" onclick="window.PFO_nuevoProducto()"
                                title="Registrar nuevo producto (queda puesto en la fórmula)">
                            <i class="bi bi-box-seam fs-6"></i>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="p-3">
                    <input type="hidden" id="pfo-id" value="">
                    <div id="pfo-aviso-componentes" class="alert alert-warning py-2 small d-none"></div>

                    <div class="row g-2">
                        <div class="col-md-6 position-relative">
                            <label class="form-label small fw-bold mb-1" for="pfo-producto-txt">Producto que se fabrica *</label>
                            <?= $ayudaPfo('Producto que se fabrica',
                                '<p>El producto que sale de esta receta y entra al inventario al cerrar cada orden.</p>'
                                . '<p class="mb-0">Su <b>unidad de medida</b> (de la ficha en Productos) es la de la fórmula: si está en kilos, la fórmula rinde kilos. Si el producto no existe, créelo con el botón de la caja de la barra superior.</p>') ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-box-seam"></i></span>
                                <input type="text" id="pfo-producto-txt" class="form-control" placeholder="Buscar producto por código o nombre…" autocomplete="off">
                                <input type="hidden" id="pfo-producto" value="">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold mb-1" for="pfo-nombre">Descripción</label>
                            <?= $ayudaPfo('Descripción',
                                '<p>Opcional. Una etiqueta corta para reconocer la receta, por ejemplo <i>Receta económica</i> o <i>Máquina 2</i>.</p>'
                                . '<p class="mb-0">Si la deja vacía, en listados y buscadores se muestra el nombre del producto. Las notas largas van en la pestaña <b>Instrucciones</b>.</p>') ?>
                            <input type="text" id="pfo-nombre" class="form-control form-control-sm" maxlength="200" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1" for="pfo-codigo">Código</label>
                            <?= $ayudaPfo('Código',
                                '<p>Lo pone el sistema al guardar: <b>F-000001</b>, <b>F-000002</b>… No se escribe ni se cambia.</p>'
                                . '<p class="mb-0">Une las <b>versiones</b> de la receta: <i>Nueva versión</i> conserva el código y sube el número (v1, v2…). Aparece en el buscador de las órdenes y en el registro de lote.</p>') ?>
                            <input type="text" id="pfo-codigo" class="form-control form-control-sm bg-light" readonly tabindex="-1" placeholder="Automático">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1" for="pfo-cantidad-base">Rinde *</label>
                            <?= $ayudaPfo('Rinde',
                                '<p>Cuánto producto sale con las cantidades de insumos que escriba (por ejemplo 1 caja, 120 panes o 100 kg).</p>'
                                . '<p>La unidad es la del producto que se fabrica; no se elige aquí. Para producir en otra medida, cambie la unidad en la ficha del producto.</p>'
                                . '<p class="mb-0">Al crear una orden por otra cantidad, los insumos <i>proporcionales</i> se escalan solos.</p>') ?>
                            <div class="input-group input-group-sm">
                                <input type="number" id="pfo-cantidad-base" class="form-control text-end" min="0" step="any" value="1">
                                <span class="input-group-text" id="pfo-medida">u</span>
                            </div>
                            <div class="form-text">Cuánto sale con estas cantidades.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1" for="pfo-vida-util">Vida útil (días)</label>
                            <?= $ayudaPfo('Vida útil',
                                '<p class="mb-0">Días que dura el producto desde que se fabrica. Con ella, cada orden sugiere sola la <b>caducidad del lote</b> (fecha de la orden + estos días). Déjela vacía si el producto no caduca.</p>') ?>
                            <input type="number" id="pfo-vida-util" class="form-control form-control-sm text-end" min="0" step="1" placeholder="Sin caducidad">
                            <div class="form-text">Calcula la caducidad del lote.</div>
                        </div>
                        <div class="col-md"><?php // col-md sin número: ocupa todo lo que sobra de la fila (y cede espacio a «% del principal» cuando aparece). ?>
                            <label class="form-label small fw-bold mb-1" for="pfo-metodo">Reparto del costo entre coproductos</label>
                            <?= $ayudaPfo('Reparto del costo entre coproductos',
                                '<p>Solo importa si la receta saca <b>varios productos valiosos a la vez</b> (pestaña <i>Otras salidas</i>, tipo coproducto). Reparte el costo de la orden entre ellos:</p>'
                                . '<ul><li><b>Valor de venta relativo:</b> según cantidad × precio de venta.</li>'
                                . '<li><b>Unidades físicas:</b> según la cantidad (kg, litros, unidades).</li>'
                                . '<li><b>% fijo:</b> según el porcentaje que ponga en cada salida.</li></ul>'
                                . '<p class="mb-0"><i>Usar el de la configuración</i> toma el método de Configuración de Producción.</p>') ?>
                            <select id="pfo-metodo" class="form-select form-select-sm">
                                <option value="">Usar el de la configuración</option>
                                <?php foreach ($metodos as $k => $l): ?>
                                    <option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-none" id="pfo-col-reparto-principal">
                            <label class="form-label small fw-bold">% del principal</label>
                            <input type="number" id="pfo-reparto-principal" class="form-control form-control-sm text-end" min="0" max="100" step="any">
                        </div>
                    </div>

                    <div class="d-flex align-items-center mt-3 border-bottom">
                        <ul class="nav nav-tabs border-0 flex-grow-1" role="tablist">
                            <li class="nav-item"><button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-insumos" type="button"><i class="bi bi-box-arrow-in-right me-1"></i>Insumos</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-salidas" type="button"><i class="bi bi-box-arrow-right me-1"></i>Otras salidas</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-costos" type="button"><i class="bi bi-person-gear me-1"></i>Mano de obra y CIF</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-instr" type="button"><i class="bi bi-card-text me-1"></i>Instrucciones</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-pfo-estimado" type="button" onclick="window.PFO_estimar()"><i class="bi bi-calculator me-1"></i>Costo estimado</button></li>
                            <?= \App\Helpers\InformacionRegistro::boton('pfo') ?>
                        </ul>
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasFormula, $vistaConfig ?? [], $rutaModulo) ?>
                    </div>

                    <div class="tab-content pt-2">
                        <!-- Insumos -->
                        <div class="tab-pane fade show active" id="pane-pfo-insumos">
                            <div class="form-text mb-1">
                                <b>Proporcional</b>: cantidad para lo que rinde la fórmula (escala con la orden).
                                <b>% del lote</b>: porcentaje de la cantidad a producir (fórmulas de proceso).
                                <b>Fija por orden</b>: no escala (una limpieza, un troquel).
                            </div>
                            <!-- Tabla de ítems: mismo diseño que el detalle de Facturas de Venta -->
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-detalle pfo-lineas mb-0 text-nowrap">
                                        <thead><tr class="table-light border-bottom">
                                            <th class="ps-3 py-2 small fw-bold text-muted" style="min-width:260px;">Insumo</th>
                                            <th class="py-2 small fw-bold text-muted" style="width:140px;">Forma <?= $ayudaForma ?></th>
                                            <th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Cantidad</th>
                                            <th class="py-2 small fw-bold text-muted" style="width:130px;">Unidad</th>
                                            <th class="py-2 small fw-bold text-muted text-end" style="width:100px;">Merma % <?= $ayudaMerma ?></th>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:80px;">Lote <?= $ayudaLote ?></th>
                                            <th style="width:40px;"></th>
                                        </tr></thead>
                                        <tbody id="pfo-tbody-insumos"></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold pfo-editable" onclick="window.PFO_agregarLinea('insumo')">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                        </button>
                                        <span class="small text-muted ms-2" id="pfo-suma-pct"></span>
                                    </div>
                                    <div class="small fw-bold text-muted pe-3">Items: <span id="pfo-count-insumos">0</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Otras salidas -->
                        <div class="tab-pane fade" id="pane-pfo-salidas">
                            <div class="form-text mb-1">
                                La salida principal es el producto de arriba. Aquí van los <b>coproductos</b> (también se buscan y llevan parte del costo:
                                cortes, tallas), los <b>subproductos</b> (secundarios, a su valor de venta neto: retazos, cascarilla) y el <b>desperdicio</b>
                                (solo se registra la cantidad).
                            </div>
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-detalle pfo-lineas mb-0 text-nowrap">
                                        <thead><tr class="table-light border-bottom">
                                            <th class="ps-3 py-2 small fw-bold text-muted" style="width:140px;">Tipo</th>
                                            <th class="py-2 small fw-bold text-muted" style="min-width:240px;">Producto / descripción</th>
                                            <th class="py-2 small fw-bold text-muted" style="width:140px;">Forma <?= $ayudaForma ?></th>
                                            <th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Cantidad</th>
                                            <th class="py-2 small fw-bold text-muted" style="width:130px;">Unidad</th>
                                            <th class="py-2 small fw-bold text-muted text-end" style="width:110px;" title="Coproducto: % fijo del costo. Subproducto: valor neto por unidad.">% / Valor</th>
                                            <th style="width:40px;"></th>
                                        </tr></thead>
                                        <tbody id="pfo-tbody-salidas"></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold pfo-editable" onclick="window.PFO_agregarLinea('coproducto')">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3 ms-auto">Items: <span id="pfo-count-salidas">0</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Mano de obra y CIF -->
                        <div class="tab-pane fade" id="pane-pfo-costos">
                            <div class="form-text mb-1">Costos estándar que se suman a los materiales. En cada orden se pueden ajustar antes de cerrarla.</div>
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-detalle pfo-lineas mb-0 text-nowrap">
                                        <thead><tr class="table-light border-bottom">
                                            <th class="ps-3 py-2 small fw-bold text-muted" style="width:160px;">Tipo</th>
                                            <th class="py-2 small fw-bold text-muted" style="min-width:200px;">Descripción</th>
                                            <th class="py-2 small fw-bold text-muted" style="width:230px;">Base de cálculo</th>
                                            <th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Cantidad</th>
                                            <th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Tarifa</th>
                                            <th style="width:40px;"></th>
                                        </tr></thead>
                                        <tbody id="pfo-tbody-recursos"></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold pfo-editable" onclick="window.PFO_agregarRecurso()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3 ms-auto">Items: <span id="pfo-count-recursos">0</span></div>
                                </div>
                            </div>
                            <div class="form-text mt-1">
                                Por unidad: cantidad × tarifa por cada unidad producida. Fijo por orden: una vez. Por hora: horas para lo que rinde la fórmula × tarifa.
                                % de la materia prima: la tarifa es el porcentaje.
                            </div>
                        </div>

                        <!-- Instrucciones -->
                        <div class="tab-pane fade" id="pane-pfo-instr">
                            <label class="form-label small fw-bold">Instrucciones de fabricación</label>
                            <textarea id="pfo-instrucciones" class="form-control form-control-sm" rows="7" placeholder="Pasos, temperaturas, tiempos de mezcla, orden de adición… Salen impresas en el registro de lote."></textarea>
                            <label class="form-label small fw-bold mt-2">Observaciones</label>
                            <textarea id="pfo-observaciones" class="form-control form-control-sm" rows="2"></textarea>
                        </div>

                        <!-- Costo estimado -->
                        <div class="tab-pane fade" id="pane-pfo-estimado">
                            <div class="d-flex align-items-end gap-2 mb-2">
                                <div>
                                    <label class="form-label small fw-bold mb-1">Simular para</label>
                                    <input type="number" id="pfo-cantidad-estimar" class="form-control form-control-sm text-end" style="width:130px;" min="0" step="any">
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.PFO_estimar()"><i class="bi bi-calculator me-1"></i>Calcular</button>
                            </div>
                            <div id="pfo-estimado" class="small text-muted">Pulse Calcular para ver el costo con los costos actuales de los insumos.</div>
                        </div>
                        <?= \App\Helpers\InformacionRegistro::panel('pfo', $rutaModulo, 'pfo-id') ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <button type="button" id="pfo-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.PFO_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" id="pfo-btn-guardar" class="btn btn-primary btn-sm px-4" onclick="window.PFO_guardar()">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
