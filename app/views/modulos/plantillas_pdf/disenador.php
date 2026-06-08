<?php
/** @var array $plantilla */
/** @var array $campos */
/** @var array $tiposDoc */
$base  = BASE_URL;
$ruta  = $base . '/modulos/plantillas-pdf';
$cfg   = json_decode($plantilla['configuracion'] ?? '{}', true) ?? [];
$cfgJs = json_encode($cfg, JSON_UNESCAPED_UNICODE);
?>
<style>
/* ── Layout diseñador ──────────────────────────────────────── */
#disenador-wrap { display:flex; height:calc(100dvh - 110px); overflow:hidden; gap:0; }

#panel-campos {
    width:200px; min-width:200px; background:#f8f9fa; border-right:1px solid #dee2e6;
    display:flex; flex-direction:column; overflow:hidden;
}
#panel-campos .panel-titulo {
    font-size:.75rem; font-weight:700; color:#495057; padding:8px 10px 4px;
    border-bottom:1px solid #dee2e6; background:#e9ecef; letter-spacing:.04em; text-transform:uppercase;
}
#panel-campos .campos-scroll { flex:1; overflow-y:auto; padding:6px; }
.grupo-campos summary {
    font-size:.72rem; font-weight:600; color:#6c757d; padding:4px 4px;
    cursor:pointer; user-select:none; list-style:none; display:flex; align-items:center; gap:4px;
}
.grupo-campos summary::before { content:'▸'; display:inline-block; transition:.15s; }
.grupo-campos[open] summary::before { content:'▾'; }
.campo-chip {
    display:block; font-size:.72rem; padding:3px 6px; margin:2px 0;
    background:#fff; border:1px solid #dee2e6; border-radius:4px;
    cursor:grab; user-select:none; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    transition:background .15s, border-color .15s;
}
.campo-chip:hover { background:#e7f0ff; border-color:#86b4f5; }
.campo-chip[data-tipo="tabla"] { background:#fff3e0; border-color:#ffc107; }

#panel-canvas {
    flex:1; overflow:auto; background:#6c757d; display:flex; align-items:flex-start; justify-content:center;
    padding:20px;
}
#canvas-container {
    position:relative; background:#fff; box-shadow:0 4px 24px rgba(0,0,0,.35);
}
#canvas-container canvas { display:block; }

#panel-props {
    width:230px; min-width:230px; background:#f8f9fa; border-left:1px solid #dee2e6;
    display:flex; flex-direction:column; overflow:hidden;
}
#panel-props .panel-titulo {
    font-size:.75rem; font-weight:700; color:#495057; padding:8px 10px 4px;
    border-bottom:1px solid #dee2e6; background:#e9ecef; letter-spacing:.04em; text-transform:uppercase;
}
#props-scroll { flex:1; overflow-y:auto; padding:8px; }
.prop-row { margin-bottom:6px; }
.prop-label { font-size:.72rem; color:#6c757d; margin-bottom:2px; display:block; }
.prop-control { width:100%; font-size:.78rem; padding:2px 6px; height:26px; border:1px solid #ced4da; border-radius:4px; }
.prop-control-color { width:32px; height:26px; padding:1px 2px; border:1px solid #ced4da; border-radius:4px; cursor:pointer; }
.prop-row-inline { display:flex; gap:4px; align-items:center; }

/* Toolbar */
#disenador-toolbar {
    display:flex; align-items:center; gap:6px; padding:5px 10px;
    background:#fff; border-bottom:1px solid #dee2e6; flex-wrap:wrap;
}
#disenador-toolbar .btn { padding:3px 8px; font-size:.78rem; }
#disenador-toolbar .sep { width:1px; height:20px; background:#dee2e6; margin:0 2px; }
#zoom-label { font-size:.78rem; min-width:46px; text-align:center; }
</style>

<!-- Toolbar superior -->
<div id="disenador-toolbar">
    <a href="<?= $ruta ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div class="sep"></div>
    <button id="btn-add-texto"  class="btn btn-outline-dark btn-sm"    title="Agregar texto fijo"><i class="bi bi-type"></i> Texto</button>
    <button id="btn-add-campo"  class="btn btn-outline-primary btn-sm" title="Agregar campo"><i class="bi bi-braces"></i> Campo</button>
    <button id="btn-add-rect"   class="btn btn-outline-secondary btn-sm" title="Rectángulo"><i class="bi bi-square"></i></button>
    <button id="btn-add-linea"  class="btn btn-outline-secondary btn-sm" title="Línea"><i class="bi bi-dash-lg"></i></button>
    <button id="btn-add-img"    class="btn btn-outline-secondary btn-sm" title="Imagen/Logo"><i class="bi bi-image"></i></button>
    <button id="btn-add-tabla"  class="btn btn-outline-warning  btn-sm" title="Tabla"><i class="bi bi-table"></i> Tabla</button>
    <button id="btn-add-barcode" class="btn btn-outline-secondary btn-sm" title="Código de barras"><i class="bi bi-upc"></i></button>
    <div class="sep"></div>
    <button id="btn-undo"  class="btn btn-outline-secondary btn-sm" title="Deshacer (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
    <button id="btn-redo"  class="btn btn-outline-secondary btn-sm" title="Rehacer (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
    <button id="btn-del"   class="btn btn-outline-danger    btn-sm" title="Eliminar selección (Del)"><i class="bi bi-trash"></i></button>
    <div class="sep"></div>
    <button id="btn-zoom-out" class="btn btn-outline-secondary btn-sm"><i class="bi bi-zoom-out"></i></button>
    <span id="zoom-label">100%</span>
    <button id="btn-zoom-in"  class="btn btn-outline-secondary btn-sm"><i class="bi bi-zoom-in"></i></button>
    <div class="sep"></div>
    <!-- Configuración de página -->
    <span style="font-size:.72rem;color:#6c757d;white-space:nowrap">Página:</span>
    <select id="pg-formato" title="Formato de hoja" style="height:24px;font-size:.78rem;border:1px solid #ced4da;border-radius:4px;padding:0 6px;background:#fff">
        <option value="A4">A4</option>
        <option value="A5">A5</option>
        <option value="Letter">Carta</option>
        <option value="Legal">Oficio</option>
    </select>
    <div class="btn-group btn-group-sm" role="group" title="Orientación">
        <button id="pg-portrait"  class="btn btn-outline-secondary btn-sm active" title="Vertical"><i class="bi bi-file-earmark-text"></i></button>
        <button id="pg-landscape" class="btn btn-outline-secondary btn-sm"        title="Horizontal"><i class="bi bi-file-earmark-text" style="display:inline-block;transform:rotate(90deg)"></i></button>
    </div>
    <!-- Márgenes dropdown -->
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Márgenes" style="font-size:.75rem">
            <i class="bi bi-layout-text-window"></i>
        </button>
        <div class="dropdown-menu p-2" style="min-width:200px;font-size:.8rem">
            <div class="fw-semibold mb-2 text-muted" style="font-size:.72rem">MÁRGENES (mm)</div>
            <div class="d-flex align-items-center gap-1 mb-1">
                <label style="width:65px">Superior:</label>
                <input type="number" id="pg-marg-t" class="form-control form-control-sm" style="height:22px;font-size:.78rem" value="10" min="0" max="50" step="1">
            </div>
            <div class="d-flex align-items-center gap-1 mb-1">
                <label style="width:65px">Inferior:</label>
                <input type="number" id="pg-marg-b" class="form-control form-control-sm" style="height:22px;font-size:.78rem" value="15" min="0" max="50" step="1">
            </div>
            <div class="d-flex align-items-center gap-1 mb-1">
                <label style="width:65px">Izquierdo:</label>
                <input type="number" id="pg-marg-l" class="form-control form-control-sm" style="height:22px;font-size:.78rem" value="10" min="0" max="50" step="1">
            </div>
            <div class="d-flex align-items-center gap-1 mb-1">
                <label style="width:65px">Derecho:</label>
                <input type="number" id="pg-marg-r" class="form-control form-control-sm" style="height:22px;font-size:.78rem" value="10" min="0" max="50" step="1">
            </div>
            <button id="btn-aplicar-margenes" class="btn btn-sm btn-outline-primary w-100 mt-1" style="font-size:.75rem">Aplicar</button>
        </div>
    </div>
    <div class="sep"></div>
    <button id="btn-guardar" class="btn btn-success btn-sm"><i class="bi bi-floppy"></i> Guardar</button>
    <span id="msg-guardado" class="text-muted small d-none"></span>
</div>

<!-- Cuerpo diseñador -->
<div id="disenador-wrap">

    <!-- Panel izquierdo: campos disponibles -->
    <div id="panel-campos">
        <div class="panel-titulo"><i class="bi bi-list-ul"></i> Campos</div>
        <div class="campos-scroll">
            <?php foreach ($campos as $grupo => $items): ?>
            <details class="grupo-campos mb-1" <?= $grupo === array_key_first($campos) ? 'open' : '' ?>>
                <summary><?= htmlspecialchars($grupo) ?></summary>
                <?php foreach ($items as $campo => $etiqueta): ?>
                <?php $esTablala = str_starts_with($campo, 'tabla:'); ?>
                <span class="campo-chip"
                      data-campo="<?= htmlspecialchars($campo) ?>"
                      data-etiqueta="<?= htmlspecialchars($etiqueta) ?>"
                      data-tipo="<?= $esTablala ? 'tabla' : 'campo' ?>"
                      draggable="true"
                      title="<?= htmlspecialchars($campo) ?>">
                    <?php if ($esTablala): ?>
                    <i class="bi bi-table" style="font-size:.68rem"></i>
                    <?php else: ?>
                    <i class="bi bi-braces" style="font-size:.68rem"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($etiqueta) ?>
                </span>
                <?php endforeach; ?>
            </details>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Canvas central -->
    <div id="panel-canvas">
        <div id="canvas-container">
            <canvas id="cv-disenador"></canvas>
        </div>
    </div>

    <!-- Panel derecho: propiedades -->
    <div id="panel-props">
        <div class="panel-titulo"><i class="bi bi-sliders"></i> Propiedades</div>
        <div id="props-scroll">
            <p id="props-vacio" class="text-muted small p-2">Selecciona un elemento para editar sus propiedades.</p>

            <div id="props-panel" class="d-none">
                <!-- Info del elemento seleccionado -->
                <div id="props-info" class="mx-2 mb-2 px-2 py-1 rounded" style="background:#f0f4ff;border:1px solid #d0dbf5">
                    <div class="d-flex align-items-center">
                        <i id="props-info-icon" class="bi bi-question me-1" style="font-size:.85rem;color:#4a6fa5"></i>
                        <strong id="props-info-tipo" style="font-size:.76rem;color:#2d4a7a"></strong>
                    </div>
                    <div id="props-info-campo" class="text-muted" style="font-size:.70rem;word-break:break-all;line-height:1.3"></div>
                </div>
                <!-- Posición y tamaño -->
                <div class="prop-row-inline">
                    <div class="flex-fill">
                        <span class="prop-label">X (mm)</span>
                        <input type="number" id="prop-x" class="prop-control" step="0.5">
                    </div>
                    <div class="flex-fill">
                        <span class="prop-label">Y (mm)</span>
                        <input type="number" id="prop-y" class="prop-control" step="0.5">
                    </div>
                </div>
                <div class="prop-row-inline mt-1">
                    <div class="flex-fill">
                        <span class="prop-label">Ancho (mm)</span>
                        <input type="number" id="prop-w" class="prop-control" step="0.5" min="1">
                    </div>
                    <div class="flex-fill">
                        <span class="prop-label">Alto (mm)</span>
                        <input type="number" id="prop-h" class="prop-control" step="0.5" min="1">
                    </div>
                </div>

                <!-- Texto -->
                <div id="props-texto-group">
                    <hr class="my-2">
                    <div class="prop-row">
                        <span class="prop-label">Contenido / Campo</span>
                        <input type="text" id="prop-contenido" class="prop-control">
                    </div>
                    <div class="prop-row-inline">
                        <div style="flex:2">
                            <span class="prop-label">Fuente</span>
                            <select id="prop-fuente" class="prop-control">
                                <option value="helvetica">Helvetica</option>
                                <option value="times">Times</option>
                                <option value="courier">Courier</option>
                            </select>
                        </div>
                        <div style="flex:1">
                            <span class="prop-label">Tamaño</span>
                            <input type="number" id="prop-tam" class="prop-control" value="8" min="5" max="72" step="0.5">
                        </div>
                    </div>
                    <div class="prop-row-inline mt-1">
                        <button id="prop-bold"   class="btn btn-sm btn-outline-secondary" style="padding:1px 6px;font-size:.8rem" title="Negrita"><b>B</b></button>
                        <button id="prop-italic" class="btn btn-sm btn-outline-secondary" style="padding:1px 6px;font-size:.8rem" title="Cursiva"><i>I</i></button>
                        <select id="prop-align" class="prop-control" style="flex:1;height:26px;font-size:.72rem">
                            <option value="L">Izquierda</option>
                            <option value="C">Centro</option>
                            <option value="R">Derecha</option>
                        </select>
                    </div>
                    <div class="prop-row-inline mt-1">
                        <div class="flex-fill">
                            <span class="prop-label">Color texto</span>
                            <input type="color" id="prop-color-texto" class="prop-control-color" value="#000000">
                        </div>
                        <div class="flex-fill">
                            <span class="prop-label">Color fondo</span>
                            <input type="color" id="prop-color-fondo" class="prop-control-color" value="#ffffff">
                        </div>
                    </div>
                </div>

                <!-- Borde -->
                <div id="props-borde-group">
                    <hr class="my-2">
                    <div class="prop-row-inline">
                        <div style="flex:1">
                            <span class="prop-label">Borde</span>
                            <select id="prop-borde-lados" class="prop-control" style="font-size:.72rem;height:26px">
                                <option value="">Sin borde</option>
                                <option value="LTBR">Todos</option>
                                <option value="LR">Laterales</option>
                                <option value="TB">Superior/Inf</option>
                                <option value="T">Superior</option>
                                <option value="B">Inferior</option>
                            </select>
                        </div>
                        <div style="flex:1">
                            <span class="prop-label">Grosor</span>
                            <input type="number" id="prop-borde-grosor" class="prop-control" value="0.3" min="0.1" max="5" step="0.1">
                        </div>
                    </div>
                    <div class="prop-row-inline mt-1">
                        <div class="flex-fill">
                            <span class="prop-label">Color borde</span>
                            <input type="color" id="prop-borde-color" class="prop-control-color" value="#000000">
                        </div>
                        <div class="flex-fill">
                            <span class="prop-label">Radio (mm)</span>
                            <input type="number" id="prop-radio" class="prop-control" value="0" min="0" max="20" step="0.5">
                        </div>
                    </div>
                </div>

                <!-- Configuración de tabla -->
                <div id="props-tabla-group" class="d-none">
                    <hr class="my-2">
                    <div class="px-2">
                        <button class="btn btn-outline-primary btn-sm w-100" id="btn-config-tabla" style="font-size:.76rem">
                            <i class="bi bi-table me-1"></i> Configurar columnas y estilos
                        </button>
                    </div>
                </div>

                <!-- Padding -->
                <div>
                    <hr class="my-2">
                    <div class="prop-row-inline">
                        <div class="flex-fill">
                            <span class="prop-label">Padding (mm)</span>
                            <input type="number" id="prop-padding" class="prop-control" value="1" min="0" step="0.5">
                        </div>
                        <div class="flex-fill">
                            <span class="prop-label">Orden (z)</span>
                            <input type="number" id="prop-z" class="prop-control" value="0" min="0" step="1">
                        </div>
                    </div>
                </div>

                <hr class="my-2">
                <button id="btn-duplicar" class="btn btn-outline-secondary btn-sm w-100 mb-1" style="font-size:.78rem"><i class="bi bi-copy"></i> Duplicar elemento</button>
                <button id="btn-eliminar-el" class="btn btn-outline-danger btn-sm w-100" style="font-size:.78rem"><i class="bi bi-trash"></i> Eliminar elemento</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Configuración de tabla -->
<div class="modal fade" id="modal-tabla-config" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-table me-1"></i> Configurar Tabla</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Columnas -->
                <div class="d-flex align-items-center mb-2">
                    <strong style="font-size:.8rem">Columnas</strong>
                    <small class="text-muted ms-2" style="font-size:.71rem">Ancho 0 = flexible (ocupa el espacio restante)</small>
                </div>
                <table class="table table-sm table-bordered mb-3" style="font-size:.74rem;table-layout:fixed">
                    <colgroup>
                        <col style="width:40px">
                        <col style="width:160px">
                        <col>
                        <col style="width:85px">
                        <col style="width:95px">
                    </colgroup>
                    <thead class="table-light">
                        <tr>
                            <th class="text-center p-1">Vis.</th>
                            <th class="p-1">Campo</th>
                            <th class="p-1">Etiqueta encabezado</th>
                            <th class="p-1">Ancho (mm)</th>
                            <th class="p-1">Alineación</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-cols-body"></tbody>
                </table>

                <!-- Estilos -->
                <strong style="font-size:.8rem">Estilos de la tabla</strong>
                <div class="row g-2 mt-1">
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-0" style="font-size:.72rem">Fondo encabezado</label>
                        <input type="color" id="tc-header-bg" class="form-control form-control-sm form-control-color w-100" value="#e6e6e6">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-0" style="font-size:.72rem">Color texto enc.</label>
                        <input type="color" id="tc-header-color" class="form-control form-control-sm form-control-color w-100" value="#000000">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-0" style="font-size:.72rem">Tamaño enc. (pt)</label>
                        <input type="number" id="tc-header-size" class="form-control form-control-sm" value="6.5" min="5" max="20" step="0.5">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-0" style="font-size:.72rem">Tamaño fila (pt)</label>
                        <input type="number" id="tc-row-size" class="form-control form-control-sm" value="7" min="5" max="20" step="0.5">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-0" style="font-size:.72rem">Fondo fila alternante</label>
                        <input type="color" id="tc-alt-bg" class="form-control form-control-sm form-control-color w-100" value="#fafafa">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-0" style="font-size:.72rem">Alto fila (mm)</label>
                        <input type="number" id="tc-lh" class="form-control form-control-sm" value="5" min="3" max="20" step="0.5">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-0" style="font-size:.72rem">Color borde</label>
                        <input type="color" id="tc-borde-color" class="form-control form-control-sm form-control-color w-100" value="#000000">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-0" style="font-size:.72rem">Grosor borde (mm)</label>
                        <input type="number" id="tc-borde-grosor" class="form-control form-control-sm" value="0.3" min="0.1" max="5" step="0.1">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-guardar-tabla-config">
                    <i class="bi bi-check-lg me-1"></i>Aplicar configuración
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Fabric.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

<script>
(function () {
// ── Constantes ───────────────────────────────────────────────
const ID_PLANTILLA = <?= (int)$plantilla['id'] ?>;
const RUTA         = '<?= $ruta ?>';
const CONFIG_INI   = <?= $cfgJs ?>;

// Limpiar la URL: quitar ?action=disenador&id=X para que siempre muestre /modulos/plantillas-pdf
history.replaceState({}, '', RUTA);

const MM = 3;   // px por mm al zoom 100%
let   zoom = 1.0;

// Dimensiones por formato en mm (portrait base)
const FORMATOS_MM = {
    'A4':     { w: 210,   h: 297   },
    'A5':     { w: 148,   h: 210   },
    'Letter': { w: 215.9, h: 279.4 },
    'Legal':  { w: 215.9, h: 355.6 },
};

// Configuración mutable de página (se carga del JSON guardado)
const pagCfg = {
    formato:      CONFIG_INI.pagina?.formato      ?? 'A4',
    orientacion:  CONFIG_INI.pagina?.orientacion  ?? 'P',
    margenLeft:   CONFIG_INI.pagina?.margenLeft   ?? 10,
    margenRight:  CONFIG_INI.pagina?.margenRight  ?? 10,
    margenTop:    CONFIG_INI.pagina?.margenTop    ?? 10,
    margenBottom: CONFIG_INI.pagina?.margenBottom ?? 15,
};

function pgDims() {
    const f = FORMATOS_MM[pagCfg.formato] ?? FORMATOS_MM['A4'];
    return pagCfg.orientacion === 'L'
        ? { w: f.h, h: f.w }
        : { w: f.w, h: f.h };
}

let PG_W_MM = pgDims().w;
let PG_H_MM = pgDims().h;
const MARGIN_MM = { l: pagCfg.margenLeft, r: pagCfg.margenRight, t: pagCfg.margenTop, b: pagCfg.margenBottom };

const TIPO_INFO = {
    texto:        { icono: 'bi-fonts',    etiqueta: 'Texto fijo'       },
    campo:        { icono: 'bi-braces',   etiqueta: 'Campo dinámico'   },
    rectangulo:   { icono: 'bi-square',   etiqueta: 'Rectángulo'       },
    linea:        { icono: 'bi-slash-lg', etiqueta: 'Línea'            },
    tabla:        { icono: 'bi-table',    etiqueta: 'Tabla'            },
    codigoBarras: { icono: 'bi-upc',      etiqueta: 'Código de barras' },
    imagen:       { icono: 'bi-image',    etiqueta: 'Imagen'           },
};
const FONT_MAP  = { helvetica: 'Helvetica', times: 'Times New Roman', courier: 'Courier New' };
const ALIGN_MAP = { L: 'left', C: 'center', R: 'right' };

const COLUMNAS_DEFAULT = {
    'tabla:detalles': [
        { key: 'codigo_principal',          titulo: 'Cód.\nPrincipal',  ancho: 16, alineacion: 'L', visible: true },
        { key: 'codigo_auxiliar',           titulo: 'Cód.\nAuxiliar',   ancho: 14, alineacion: 'L', visible: true },
        { key: 'cantidad',                  titulo: 'Cantidad',          ancho: 14, alineacion: 'R', visible: true },
        { key: 'descripcion',               titulo: 'Descripción',       ancho: 0,  alineacion: 'L', visible: true },
        { key: 'detalle_adicional',         titulo: 'Det.\nAdicional',   ancho: 22, alineacion: 'L', visible: true },
        { key: 'precio_unitario',           titulo: 'Precio\nUnitario',  ancho: 20, alineacion: 'R', visible: true },
        { key: 'descuento',                 titulo: 'Descuento',         ancho: 16, alineacion: 'R', visible: true },
        { key: 'precio_total_sin_impuesto', titulo: 'Precio\nTotal',     ancho: 18, alineacion: 'R', visible: true },
    ],
    'tabla:pagos': [
        { key: 'nombre_forma_pago', titulo: 'Forma de pago', ancho: 0,  alineacion: 'L', visible: true },
        { key: 'total',             titulo: 'Valor',          ancho: 28, alineacion: 'R', visible: true },
        { key: 'plazo',             titulo: 'Días Crédito',   ancho: 22, alineacion: 'C', visible: true },
        { key: 'unidad_tiempo',     titulo: 'Plazo',          ancho: 22, alineacion: 'C', visible: true },
    ],
    'tabla:info_adicional': [
        { key: 'nombre', titulo: 'Concepto', ancho: 0,  alineacion: 'L', visible: true },
        { key: 'valor',  titulo: 'Valor',    ancho: 50, alineacion: 'L', visible: true },
    ],
};
const ESTILOS_TABLA_DEFAULT = {
    headerBg:    '#e6e6e6',
    headerColor: '#000000',
    headerSize:  6.5,
    rowSize:     7,
    altBg:       '#fafafa',
    lineaAltura: 5,
    bordeColor:  '#000000',
    bordeGrosor: 0.3,
};

// Sincronizar controles de toolbar con config cargada
function sincronizarControlesPagina() {
    const sel = document.getElementById('pg-formato');
    if (sel) sel.value = pagCfg.formato;
    document.getElementById('pg-portrait')?.classList.toggle('active',  pagCfg.orientacion !== 'L');
    document.getElementById('pg-landscape')?.classList.toggle('active', pagCfg.orientacion === 'L');
    const t = document.getElementById('pg-marg-t'); if (t) t.value = pagCfg.margenTop;
    const b = document.getElementById('pg-marg-b'); if (b) b.value = pagCfg.margenBottom;
    const l = document.getElementById('pg-marg-l'); if (l) l.value = pagCfg.margenLeft;
    const r = document.getElementById('pg-marg-r'); if (r) r.value = pagCfg.margenRight;
}

// ── Canvas ───────────────────────────────────────────────────
const cvEl = document.getElementById('cv-disenador');
cvEl.width  = Math.round(PG_W_MM * MM);
cvEl.height = Math.round(PG_H_MM * MM);
document.getElementById('canvas-container').style.width  = cvEl.width  + 'px';
document.getElementById('canvas-container').style.height = cvEl.height + 'px';

const canvas = new fabric.Canvas('cv-disenador', {
    backgroundColor: '#ffffff',
    selection: true,
    preserveObjectStacking: true,
});

// Dibujar marcas de margen (guías)
function dibujarGuias() {
    canvas.getObjects().filter(o => o.data?.guia).forEach(o => canvas.remove(o));
    const opts = { stroke:'#aad4f5', strokeWidth:0.5, strokeDashArray:[4,4], selectable:false, evented:false, excludeFromExport:true, data:{guia:true} };
    const mL = MARGIN_MM.l * MM * zoom;
    const mR = (PG_W_MM - MARGIN_MM.r) * MM * zoom;
    const mT = MARGIN_MM.t * MM * zoom;
    const mB = (PG_H_MM - MARGIN_MM.b) * MM * zoom;
    const h  = PG_H_MM * MM * zoom;
    const w  = PG_W_MM * MM * zoom;
    canvas.add(new fabric.Line([mL, 0, mL, h], opts));
    canvas.add(new fabric.Line([mR, 0, mR, h], opts));
    canvas.add(new fabric.Line([0, mT, w, mT], opts));
    canvas.add(new fabric.Line([0, mB, w, mB], opts));
    canvas.renderAll();
}

// Actualizar página cuando el usuario cambia formato/orientación/márgenes
function actualizarPagina() {
    const dims = pgDims();
    PG_W_MM = dims.w;
    PG_H_MM = dims.h;
    MARGIN_MM.l = pagCfg.margenLeft;
    MARGIN_MM.r = pagCfg.margenRight;
    MARGIN_MM.t = pagCfg.margenTop;
    MARGIN_MM.b = pagCfg.margenBottom;
    setZoom(zoom); // redimensiona canvas
    dibujarGuias();
}

// ── Historial undo/redo ──────────────────────────────────────
let historial = [], hIdx = -1, pausarHistorial = false;

function guardarEstado() {
    if (pausarHistorial) return;
    const json = JSON.stringify(canvas.toJSON(['data']));
    historial = historial.slice(0, hIdx + 1);
    historial.push(json);
    if (historial.length > 40) historial.shift();
    hIdx = historial.length - 1;
}

function undo() {
    if (hIdx <= 0) return;
    hIdx--;
    restaurar(historial[hIdx]);
}
function redo() {
    if (hIdx >= historial.length - 1) return;
    hIdx++;
    restaurar(historial[hIdx]);
}
function restaurar(json) {
    pausarHistorial = true;
    canvas.loadFromJSON(json, () => {
        dibujarGuias();
        canvas.renderAll();
        pausarHistorial = false;
    });
}

canvas.on('object:added',    () => guardarEstado());
canvas.on('object:removed',  () => guardarEstado());
canvas.on('object:modified', () => guardarEstado());

// ── Crear elementos ──────────────────────────────────────────
function mm(v) { return v * MM * zoom; }
function px2mm(v) { return Math.round((v / (MM * zoom)) * 10) / 10; }

function elementoBase(tipo, extra = {}) {
    return Object.assign({
        data: { tipo, campo:'', contenido:'', fuente:'helvetica', tamano:8, estilo:'',
                alineacion:'L', colorTexto:'#000000', colorFondo:'#ffffff',
                borde:{ lados:'', grosor:0.3, color:'#000000', radio:0 }, padding:1, z:0 },
    }, extra);
}

function addTexto(xmm = 15, ymm = 20, texto = 'Texto fijo') {
    const obj = new fabric.Textbox(texto, Object.assign(elementoBase('texto'), {
        left: mm(xmm), top: mm(ymm), width: mm(50), fontSize: 8 * zoom,
        fontFamily: 'Helvetica', fill: '#000000', backgroundColor: 'transparent',
        borderColor:'#3b82f6', cornerColor:'#3b82f6', cornerSize:7,
    }));
    obj.data.contenido = texto;
    canvas.add(obj); canvas.setActiveObject(obj); canvas.renderAll();
}

function addCampo(campo, etiqueta, xmm = 15, ymm = 20) {
    const obj = new fabric.Textbox('[' + etiqueta + ']', Object.assign(elementoBase('campo'), {
        left: mm(xmm), top: mm(ymm), width: mm(60), fontSize: 8 * zoom,
        fontFamily: 'Helvetica', fill: '#1a56db', backgroundColor: '#e7f0ff',
        borderColor:'#3b82f6', cornerColor:'#3b82f6', cornerSize:7,
    }));
    obj.data.campo = campo;
    obj.data.tipo  = campo.startsWith('{empresa_logo}') ? 'imagen' : 'campo';
    canvas.add(obj); canvas.setActiveObject(obj); canvas.renderAll();
}

function addRect(xmm = 15, ymm = 30) {
    const obj = new fabric.Rect(Object.assign(elementoBase('rectangulo'), {
        left: mm(xmm), top: mm(ymm), width: mm(50), height: mm(20),
        fill: '#ffffff', stroke: '#000000', strokeWidth: 0.5 * zoom, rx: 0, ry: 0,
        borderColor:'#3b82f6', cornerColor:'#3b82f6', cornerSize:7,
    }));
    canvas.add(obj); canvas.setActiveObject(obj); canvas.renderAll();
}

function addLinea(xmm = 10, ymm = 50, x2mm = 200) {
    const obj = new fabric.Line([mm(xmm), mm(ymm), mm(x2mm), mm(ymm)], Object.assign(elementoBase('linea'), {
        stroke: '#000000', strokeWidth: 0.5 * zoom,
        borderColor:'#3b82f6', cornerColor:'#3b82f6', cornerSize:7,
    }));
    canvas.add(obj); canvas.setActiveObject(obj); canvas.renderAll();
}

function addTabla(campo, etiqueta, xmm = 10, ymm = 100) {
    const obj = new fabric.Rect(Object.assign(elementoBase('tabla'), {
        left: mm(xmm), top: mm(ymm), width: mm(190), height: mm(30),
        fill: '#fffde7', stroke: '#ffc107', strokeWidth: 1 * zoom,
        borderColor:'#ffc107', cornerColor:'#ffc107', cornerSize:7,
    }));
    obj.data.campo    = campo;
    obj.data.etiqueta = etiqueta;
    const label = new fabric.Text('⊞ TABLA: ' + etiqueta, {
        left: mm(xmm) + 6, top: mm(ymm) + 6, fontSize: 9 * zoom,
        fontFamily:'Helvetica', fill:'#b45309', selectable:false, evented:false,
        data:{ owner: obj.cacheKey }
    });
    canvas.add(obj);
    canvas.add(label);
    canvas.setActiveObject(obj); canvas.renderAll();
}

function addBarcode(xmm = 10, ymm = 50) {
    const obj = new fabric.Rect(Object.assign(elementoBase('codigoBarras'), {
        left: mm(xmm), top: mm(ymm), width: mm(76), height: mm(14),
        fill: '#f1f5f9', stroke: '#334155', strokeWidth: 0.5 * zoom,
        borderColor:'#334155', cornerColor:'#334155', cornerSize:7,
    }));
    obj.data.campo = '{clave_acceso}';
    const label = new fabric.Text('▐▌▐▌ Código de barras', {
        left: mm(xmm) + 4, top: mm(ymm) + 3, fontSize: 8 * zoom,
        fontFamily:'Helvetica', fill:'#334155', selectable:false, evented:false,
    });
    canvas.add(obj); canvas.add(label);
    canvas.setActiveObject(obj); canvas.renderAll();
}

// ── Toolbar botones ──────────────────────────────────────────
document.getElementById('btn-add-texto')?.addEventListener('click', () => addTexto());
document.getElementById('btn-add-campo')?.addEventListener('click', () => {
    const campo = prompt('Ingresa el campo, ej: {cliente_nombre}');
    if (campo) addCampo(campo, campo);
});
document.getElementById('btn-add-rect')?.addEventListener('click',   () => addRect());
document.getElementById('btn-add-linea')?.addEventListener('click',  () => addLinea());
document.getElementById('btn-add-tabla')?.addEventListener('click',  () => addTabla('tabla:detalles','Ítems / Detalles'));
document.getElementById('btn-add-barcode')?.addEventListener('click',() => addBarcode());
document.getElementById('btn-undo')?.addEventListener('click', undo);
document.getElementById('btn-redo')?.addEventListener('click', redo);
document.getElementById('btn-del')?.addEventListener('click', () => {
    const activos = canvas.getActiveObjects();
    activos.forEach(o => canvas.remove(o));
    canvas.discardActiveObject(); canvas.renderAll();
});

// Drag desde panel de campos
document.querySelectorAll('.campo-chip').forEach(chip => {
    chip.addEventListener('dragstart', e => {
        e.dataTransfer.setData('campo',   chip.dataset.campo);
        e.dataTransfer.setData('etiqueta',chip.dataset.etiqueta);
        e.dataTransfer.setData('tipo',    chip.dataset.tipo);
    });
});
const panelCanvas = document.getElementById('panel-canvas');
panelCanvas.addEventListener('dragover', e => e.preventDefault());
panelCanvas.addEventListener('drop', e => {
    e.preventDefault();
    const campo    = e.dataTransfer.getData('campo');
    const etiqueta = e.dataTransfer.getData('etiqueta');
    const tipo     = e.dataTransfer.getData('tipo');
    const rect     = document.getElementById('canvas-container').getBoundingClientRect();
    const xmm = px2mm(e.clientX - rect.left);
    const ymm = px2mm(e.clientY - rect.top);
    if (tipo === 'tabla') addTabla(campo, etiqueta, xmm, ymm);
    else addCampo(campo, etiqueta, xmm, ymm);
});

// ── Panel propiedades ─────────────────────────────────────────
canvas.on('selection:created',  mostrarProps);
canvas.on('selection:updated',  mostrarProps);
canvas.on('selection:cleared',  ocultarProps);
canvas.on('object:scaling',     () => { const o = canvas.getActiveObject(); if (o) sincronizarDesdeFabric(o); });
canvas.on('object:moving',      () => { const o = canvas.getActiveObject(); if (o) sincronizarDesdeFabric(o); });

function mostrarProps({ selected }) {
    const obj = canvas.getActiveObject();
    if (!obj || canvas.getActiveObjects().length > 1) { ocultarProps(); return; }
    document.getElementById('props-vacio').classList.add('d-none');
    document.getElementById('props-panel').classList.remove('d-none');
    sincronizarDesdeFabric(obj);
}
function ocultarProps() {
    document.getElementById('props-vacio').classList.remove('d-none');
    document.getElementById('props-panel').classList.add('d-none');
}
function sincronizarDesdeFabric(obj) {
    const xmm = Math.round(px2mm(obj.left ?? 0) * 10) / 10;
    const ymm = Math.round(px2mm(obj.top  ?? 0) * 10) / 10;
    const wmm = Math.round(px2mm((obj.width  ?? 0) * (obj.scaleX ?? 1)) * 10) / 10;
    const hmm = Math.round(px2mm((obj.height ?? 0) * (obj.scaleY ?? 1)) * 10) / 10;
    document.getElementById('prop-x').value = xmm;
    document.getElementById('prop-y').value = ymm;
    document.getElementById('prop-w').value = wmm;
    document.getElementById('prop-h').value = hmm;

    const d = obj.data ?? {};
    document.getElementById('prop-contenido').value       = d.contenido ?? obj.text ?? '';
    document.getElementById('prop-fuente').value          = d.fuente    ?? 'helvetica';
    document.getElementById('prop-tam').value             = d.tamano    ?? 8;
    document.getElementById('prop-align').value           = d.alineacion?? 'L';
    document.getElementById('prop-color-texto').value     = d.colorTexto?? '#000000';
    document.getElementById('prop-color-fondo').value     = d.colorFondo?? '#ffffff';
    document.getElementById('prop-borde-lados').value     = d.borde?.lados   ?? '';
    document.getElementById('prop-borde-grosor').value    = d.borde?.grosor  ?? 0.3;
    document.getElementById('prop-borde-color').value     = d.borde?.color   ?? '#000000';
    document.getElementById('prop-radio').value           = d.borde?.radio   ?? 0;
    document.getElementById('prop-padding').value         = d.padding ?? 1;
    document.getElementById('prop-z').value               = d.z ?? 0;

    const esTexto = ['texto','campo'].includes(d.tipo);
    document.getElementById('props-texto-group').classList.toggle('d-none', !esTexto);

    // Info del elemento
    const info = TIPO_INFO[d.tipo] ?? { icono: 'bi-question-circle', etiqueta: d.tipo ?? '' };
    const iconEl  = document.getElementById('props-info-icon');
    const tipoEl  = document.getElementById('props-info-tipo');
    const campoEl = document.getElementById('props-info-campo');
    if (iconEl)  iconEl.className    = 'bi ' + info.icono + ' me-1';
    if (tipoEl)  tipoEl.textContent  = info.etiqueta;
    if (campoEl) campoEl.textContent = d.campo
        ? 'Campo: ' + d.campo
        : (d.contenido ? '"' + d.contenido.slice(0, 50) + '"' : '');

    // Sincronizar estado visual de botones bold/italic
    const estilo = d.estilo ?? '';
    document.getElementById('prop-bold')?.classList.toggle('active', estilo.includes('B'));
    document.getElementById('prop-italic')?.classList.toggle('active', estilo.includes('I'));

    // Mostrar sección de configuración de tabla solo para tipo tabla
    document.getElementById('props-tabla-group')?.classList.toggle('d-none', d.tipo !== 'tabla');
}

function aplicarVisual() {
    const obj = canvas.getActiveObject();
    if (!obj) return;
    obj.data ??= {};
    const xmm = parseFloat(document.getElementById('prop-x').value) || 0;
    const ymm = parseFloat(document.getElementById('prop-y').value) || 0;
    const wmm = parseFloat(document.getElementById('prop-w').value) || 10;
    const hmm = parseFloat(document.getElementById('prop-h').value) || 5;

    obj.set({ left: mm(xmm), top: mm(ymm) });
    if (obj.type !== 'line') {
        obj.set({ width: mm(wmm) / (obj.scaleX || 1), height: mm(hmm) / (obj.scaleY || 1) });
    }

    obj.data.contenido  = document.getElementById('prop-contenido').value;
    obj.data.fuente     = document.getElementById('prop-fuente').value;
    obj.data.tamano     = parseFloat(document.getElementById('prop-tam').value) || 8;
    obj.data.alineacion = document.getElementById('prop-align').value;
    obj.data.colorTexto = document.getElementById('prop-color-texto').value;
    obj.data.colorFondo = document.getElementById('prop-color-fondo').value;
    obj.data.borde      = {
        lados:  document.getElementById('prop-borde-lados').value,
        grosor: parseFloat(document.getElementById('prop-borde-grosor').value) || 0.3,
        color:  document.getElementById('prop-borde-color').value,
        radio:  parseFloat(document.getElementById('prop-radio').value) || 0,
    };
    obj.data.padding = parseFloat(document.getElementById('prop-padding').value) || 1;
    obj.data.z       = parseInt(document.getElementById('prop-z').value) || 0;

    if (obj.type === 'textbox' || obj.type === 'text') {
        obj.set({
            text:            obj.data.contenido !== '' ? obj.data.contenido : (obj.text ?? ''),
            fill:            obj.data.colorTexto,
            backgroundColor: obj.data.colorFondo !== '#ffffff' ? obj.data.colorFondo : 'transparent',
            fontSize:        obj.data.tamano * zoom,
            fontFamily:      FONT_MAP[obj.data.fuente]    ?? 'Helvetica',
            fontWeight:      (obj.data.estilo ?? '').includes('B') ? 'bold'   : 'normal',
            fontStyle:       (obj.data.estilo ?? '').includes('I') ? 'italic' : 'normal',
            textAlign:       ALIGN_MAP[obj.data.alineacion] ?? 'left',
        });
    }
    if (obj.type === 'rect') {
        obj.set({
            fill:        obj.data.colorFondo,
            stroke:      obj.data.borde.color,
            strokeWidth: obj.data.borde.grosor * zoom,
            rx:          obj.data.borde.radio  * zoom,
            ry:          obj.data.borde.radio  * zoom,
        });
    }
    if (obj.type === 'line') {
        obj.set({
            stroke:      obj.data.borde?.color   ?? '#000000',
            strokeWidth: (obj.data.borde?.grosor ?? 0.5) * zoom,
        });
    }

    canvas.renderAll();
}

function aplicarProps() {
    aplicarVisual();
    guardarEstado();
}

['prop-x','prop-y','prop-w','prop-h','prop-contenido','prop-fuente','prop-tam','prop-align',
 'prop-color-texto','prop-color-fondo','prop-borde-lados','prop-borde-grosor','prop-borde-color',
 'prop-radio','prop-padding','prop-z'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input',  aplicarVisual);   // vista previa inmediata
    el.addEventListener('change', aplicarProps);    // confirmar en historial
});

document.getElementById('prop-bold')?.addEventListener('click', () => {
    const obj = canvas.getActiveObject();
    if (!obj?.data) return;
    const e = obj.data.estilo ?? '';
    obj.data.estilo = e.includes('B') ? e.replace('B', '') : e + 'B';
    document.getElementById('prop-bold').classList.toggle('active', obj.data.estilo.includes('B'));
    aplicarProps();
});
document.getElementById('prop-italic')?.addEventListener('click', () => {
    const obj = canvas.getActiveObject();
    if (!obj?.data) return;
    const e = obj.data.estilo ?? '';
    obj.data.estilo = e.includes('I') ? e.replace('I', '') : e + 'I';
    document.getElementById('prop-italic').classList.toggle('active', obj.data.estilo.includes('I'));
    aplicarProps();
});

// ── Configuración de tabla ────────────────────────────────────
let _tablaObj = null;

document.getElementById('btn-config-tabla')?.addEventListener('click', () => {
    const obj = canvas.getActiveObject();
    if (!obj?.data || obj.data.tipo !== 'tabla') return;
    _tablaObj = obj;
    abrirConfigTabla(obj);
});

function abrirConfigTabla(obj) {
    const campo     = obj.data.campo ?? 'tabla:detalles';
    const cfg       = obj.data.tablaConfig ?? {};
    const savedCols = cfg.columnas;
    const defCols   = COLUMNAS_DEFAULT[campo] ?? [];

    // Usar columnas guardadas como base; añadir keys nuevas del default que no existan
    let cols;
    if (savedCols && savedCols.length > 0) {
        cols = savedCols.map(sc => Object.assign({}, defCols.find(d => d.key === sc.key) ?? {}, sc));
        defCols.forEach(d => { if (!cols.find(c => c.key === d.key)) cols.push({ ...d }); });
    } else {
        cols = defCols.map(d => ({ ...d }));
    }

    const est = Object.assign({}, ESTILOS_TABLA_DEFAULT, cfg.estilos ?? {});

    // Poblar tabla de columnas
    const tbody = document.getElementById('tabla-cols-body');
    tbody.innerHTML = '';
    cols.forEach(col => {
        const tr = document.createElement('tr');
        tr.dataset.key = col.key;
        tr.innerHTML = `
            <td class="text-center p-0" style="vertical-align:middle">
                <input type="checkbox" class="form-check-input tc-col-vis" ${col.visible !== false ? 'checked' : ''}>
            </td>
            <td class="p-0" style="font-size:.70rem;color:#555;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:0 4px!important">${col.key}</td>
            <td class="p-0">
                <input type="text" class="form-control form-control-sm tc-col-titulo border-0"
                    value="${col.titulo.replace(/\n/g, ' ')}"
                    style="padding:0 4px;height:22px;font-size:.72rem;border-radius:0">
            </td>
            <td class="p-0">
                <input type="number" class="form-control form-control-sm tc-col-ancho border-0"
                    value="${col.ancho}" min="0" max="200" step="1"
                    style="padding:0 4px;height:22px;font-size:.72rem;border-radius:0">
            </td>
            <td class="p-0">
                <select class="form-select form-select-sm tc-col-align border-0"
                    style="padding:0 4px;height:22px;font-size:.72rem;border-radius:0">
                    <option value="L" ${col.alineacion === 'L' ? 'selected' : ''}>Izquierda</option>
                    <option value="C" ${col.alineacion === 'C' ? 'selected' : ''}>Centro</option>
                    <option value="R" ${col.alineacion === 'R' ? 'selected' : ''}>Derecha</option>
                </select>
            </td>`;
        tbody.appendChild(tr);
    });

    // Poblar estilos
    document.getElementById('tc-header-bg').value    = est.headerBg;
    document.getElementById('tc-header-color').value = est.headerColor;
    document.getElementById('tc-header-size').value  = est.headerSize;
    document.getElementById('tc-row-size').value      = est.rowSize;
    document.getElementById('tc-alt-bg').value        = est.altBg;
    document.getElementById('tc-lh').value            = est.lineaAltura;
    document.getElementById('tc-borde-color').value   = est.bordeColor;
    document.getElementById('tc-borde-grosor').value  = est.bordeGrosor;

    new bootstrap.Modal(document.getElementById('modal-tabla-config')).show();
}

document.getElementById('btn-guardar-tabla-config')?.addEventListener('click', () => {
    if (!_tablaObj) return;

    const columnas = Array.from(document.getElementById('tabla-cols-body').querySelectorAll('tr')).map(tr => ({
        key:        tr.dataset.key,
        titulo:     tr.querySelector('.tc-col-titulo').value,
        ancho:      parseFloat(tr.querySelector('.tc-col-ancho').value) || 0,
        alineacion: tr.querySelector('.tc-col-align').value,
        visible:    tr.querySelector('.tc-col-vis').checked,
    }));

    _tablaObj.data.tablaConfig = {
        columnas,
        estilos: {
            headerBg:    document.getElementById('tc-header-bg').value,
            headerColor: document.getElementById('tc-header-color').value,
            headerSize:  parseFloat(document.getElementById('tc-header-size').value)  || 6.5,
            rowSize:     parseFloat(document.getElementById('tc-row-size').value)      || 7,
            altBg:       document.getElementById('tc-alt-bg').value,
            lineaAltura: parseFloat(document.getElementById('tc-lh').value)            || 5,
            bordeColor:  document.getElementById('tc-borde-color').value,
            bordeGrosor: parseFloat(document.getElementById('tc-borde-grosor').value)  || 0.3,
        },
    };

    bootstrap.Modal.getInstance(document.getElementById('modal-tabla-config'))?.hide();
    guardarEstado();
});

document.getElementById('btn-duplicar')?.addEventListener('click', () => {
    const obj = canvas.getActiveObject();
    if (!obj) return;
    obj.clone(cloned => {
        cloned.set({ left: obj.left + 10, top: obj.top + 10 });
        cloned.data = JSON.parse(JSON.stringify(obj.data ?? {}));
        canvas.add(cloned); canvas.setActiveObject(cloned); canvas.renderAll();
    });
});
document.getElementById('btn-eliminar-el')?.addEventListener('click', () => {
    const obj = canvas.getActiveObject();
    if (obj) { canvas.remove(obj); canvas.discardActiveObject(); canvas.renderAll(); }
});

// ── Configuración de página ───────────────────────────────────
document.getElementById('pg-formato')?.addEventListener('change', e => {
    pagCfg.formato = e.target.value;
    actualizarPagina();
});
document.getElementById('pg-portrait')?.addEventListener('click', () => {
    if (pagCfg.orientacion === 'P') return;
    pagCfg.orientacion = 'P';
    document.getElementById('pg-portrait').classList.add('active');
    document.getElementById('pg-landscape').classList.remove('active');
    actualizarPagina();
});
document.getElementById('pg-landscape')?.addEventListener('click', () => {
    if (pagCfg.orientacion === 'L') return;
    pagCfg.orientacion = 'L';
    document.getElementById('pg-landscape').classList.add('active');
    document.getElementById('pg-portrait').classList.remove('active');
    actualizarPagina();
});
document.getElementById('btn-aplicar-margenes')?.addEventListener('click', () => {
    pagCfg.margenTop    = parseFloat(document.getElementById('pg-marg-t').value) || 10;
    pagCfg.margenBottom = parseFloat(document.getElementById('pg-marg-b').value) || 15;
    pagCfg.margenLeft   = parseFloat(document.getElementById('pg-marg-l').value) || 10;
    pagCfg.margenRight  = parseFloat(document.getElementById('pg-marg-r').value) || 10;
    actualizarPagina();
    // Cerrar dropdown
    const dd = document.querySelector('[data-bs-toggle="dropdown"]');
    bootstrap.Dropdown.getInstance(dd)?.hide();
});

// ── Zoom ─────────────────────────────────────────────────────
function setZoom(z) {
    zoom = Math.max(0.4, Math.min(2.5, z));
    const w = Math.round(PG_W_MM * MM * zoom);
    const h = Math.round(PG_H_MM * MM * zoom);
    canvas.setWidth(w); canvas.setHeight(h);
    canvas.setZoom(zoom);
    document.getElementById('canvas-container').style.width  = w + 'px';
    document.getElementById('canvas-container').style.height = h + 'px';
    document.getElementById('zoom-label').textContent = Math.round(zoom * 100) + '%';
    dibujarGuias();
    canvas.renderAll();
}
document.getElementById('btn-zoom-in')?.addEventListener('click',  () => setZoom(zoom + 0.15));
document.getElementById('btn-zoom-out')?.addEventListener('click', () => setZoom(zoom - 0.15));

// ── Guardar diseño ────────────────────────────────────────────
document.getElementById('btn-guardar')?.addEventListener('click', async () => {
    const objetos = canvas.getObjects()
        .filter(o => !o.data?.guia)
        .map(o => {
            const xmm = px2mm(o.left ?? 0);
            const ymm = px2mm(o.top  ?? 0);
            const wmm = px2mm((o.width  ?? 0) * (o.scaleX ?? 1));
            const hmm = px2mm((o.height ?? 0) * (o.scaleY ?? 1));
            return Object.assign({}, o.data ?? {}, {
                id:   o.data?.id ?? (Math.random().toString(36).slice(2)),
                x: xmm, y: ymm, w: wmm, h: hmm,
            });
        });

    const config = {
        pagina: {
            formato:      pagCfg.formato,
            orientacion:  pagCfg.orientacion,
            margenTop:    pagCfg.margenTop,
            margenBottom: pagCfg.margenBottom,
            margenLeft:   pagCfg.margenLeft,
            margenRight:  pagCfg.margenRight,
        },
        elementos: objetos,
    };

    const fd = new FormData();
    fd.append('action', 'guardar-diseno');
    fd.append('id', ID_PLANTILLA);
    fd.append('configuracion', JSON.stringify(config));

    const msgEl = document.getElementById('msg-guardado');
    try {
        const r = await fetch(RUTA, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        const j = await r.json();
        msgEl.textContent = j.ok ? '✓ Guardado' : '✗ ' + j.mensaje;
        msgEl.className   = j.ok ? 'text-success small' : 'text-danger small';
        msgEl.classList.remove('d-none');
        setTimeout(() => msgEl.classList.add('d-none'), 3000);
    } catch (e) {
        msgEl.textContent = '✗ Error de red'; msgEl.className = 'text-danger small'; msgEl.classList.remove('d-none');
    }
});

// ── Teclado ──────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'z') { e.preventDefault(); undo(); }
    if (e.ctrlKey && e.key === 'y') { e.preventDefault(); redo(); }
    if ((e.key === 'Delete' || e.key === 'Backspace') && document.activeElement === document.body) {
        const activos = canvas.getActiveObjects();
        activos.forEach(o => canvas.remove(o));
        canvas.discardActiveObject(); canvas.renderAll();
    }
});

// ── Cargar diseño existente ───────────────────────────────────
(function cargarDiseno() {
    // Aplicar config de página guardada al canvas y sincronizar controles
    sincronizarControlesPagina();
    actualizarPagina();

    const elementos = CONFIG_INI.elementos ?? [];
    if (elementos.length === 0) { dibujarGuias(); guardarEstado(); return; }

    elementos.forEach(el => {
        const x = mm(el.x ?? 0);
        const y = mm(el.y ?? 0);
        const w = mm(el.w ?? 40);
        const h = mm(el.h ?? 8);

        let obj;
        switch (el.tipo) {
            case 'texto':
            case 'campo':
                obj = new fabric.Textbox(el.contenido || '[' + (el.campo ?? '') + ']', {
                    left: x, top: y, width: w, fontSize: (el.tamano ?? 8) * zoom,
                    fontFamily:  FONT_MAP[el.fuente] ?? 'Helvetica',
                    fontWeight:  (el.estilo ?? '').includes('B') ? 'bold'   : 'normal',
                    fontStyle:   (el.estilo ?? '').includes('I') ? 'italic' : 'normal',
                    textAlign:   ALIGN_MAP[el.alineacion] ?? 'left',
                    fill:            el.colorTexto ?? '#000000',
                    backgroundColor: (el.colorFondo && el.colorFondo !== '#ffffff') ? el.colorFondo : 'transparent',
                });
                break;
            case 'rectangulo':
            case 'tabla':
            case 'codigoBarras':
                obj = new fabric.Rect({
                    left: x, top: y, width: w, height: h,
                    fill: el.colorFondo ?? '#ffffff',
                    stroke: el.borde?.color ?? '#000000',
                    strokeWidth: (el.borde?.grosor ?? 0.3) * zoom,
                    rx: (el.borde?.radio ?? 0) * zoom,
                    ry: (el.borde?.radio ?? 0) * zoom,
                });
                break;
            case 'linea':
                obj = new fabric.Line([x, y, x + w, y], {
                    stroke: el.borde?.color ?? '#000000',
                    strokeWidth: (el.borde?.grosor ?? 0.5) * zoom,
                });
                break;
            default:
                return;
        }
        obj.data = Object.assign({ tipo:'texto', campo:'', contenido:'', fuente:'helvetica', tamano:8, estilo:'', alineacion:'L', colorTexto:'#000000', colorFondo:'#ffffff', borde:{lados:'',grosor:0.3,color:'#000000',radio:0}, padding:1, z:0 }, el);
        canvas.add(obj);
    });

    dibujarGuias();
    guardarEstado();
    canvas.renderAll();
})();

// Zoom inicial ajustado a pantalla
setZoom(0.9);

})();
</script>
