<?php
/**
 * Modal de la publicación (modulos/crm_publicaciones) y modales auxiliares:
 * elegir de la biblioteca, datos de un archivo y configuración del módulo.
 * Lo maneja public/js/modulos/crm_publicaciones.js.
 *
 * Reglas del sistema aplicadas: barra de acciones arriba (§9), Eliminar en el pie
 * a la izquierda, pestañas que cada usuario puede ocultar, sin pestaña
 * «Información», guardado único con token_guardado (§8).
 */
use App\Helpers\PreferenciasHelper;
?>
<div class="modal fade" id="pubModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-megaphone text-primary me-2"></i><span id="pub-m-titulo">Nueva publicación</span>
                    <span class="badge ms-2" id="pub-m-estado"></span></h5>
                <?= PreferenciasHelper::renderDropdownPestanas(['pub-pane-previa' => 'Vista previa', 'pub-pane-resultados' => 'Resultados'], $vistaConfig ?? [], $rutaModulo) ?>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-2">
                <!-- Barra de acciones del documento -->
                <div class="d-flex gap-1 align-items-center flex-wrap border-bottom pb-2 mb-2" id="pub-m-acciones">
                    <div class="d-flex align-items-center gap-1" id="pub-m-programar-box">
                        <input type="datetime-local" class="form-control form-control-sm" id="pub-m-fecha" style="width:200px;" title="Vacío = publicar ahora">
                        <button type="button" class="btn btn-sm btn-success" id="pub-m-btn-programar" onclick="PUB.accion('programar')"><i class="bi bi-send me-1"></i><span>Programar</span></button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="pub-m-btn-revision" onclick="PUB.accion('revision')"><i class="bi bi-eye me-1"></i>Enviar a revisión</button>
                    <button type="button" class="btn btn-sm btn-outline-success d-none" id="pub-m-btn-aprobar" onclick="PUB.accion('aprobar')"><i class="bi bi-check2-circle me-1"></i>Aprobar</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="pub-m-btn-devolver" onclick="PUB.accion('devolver')"><i class="bi bi-arrow-counterclockwise me-1"></i>Devolver a borrador</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="pub-m-btn-desprogramar" onclick="PUB.accion('desprogramar')"><i class="bi bi-calendar-x me-1"></i>Desprogramar</button>
                    <button type="button" class="btn btn-sm btn-outline-warning d-none" id="pub-m-btn-cancelar" onclick="PUB.accion('cancelar')"><i class="bi bi-slash-circle me-1"></i>Cancelar lo pendiente</button>
                    <div class="small text-muted ms-auto" id="pub-m-ayuda"></div>
                </div>

                <ul class="nav nav-tabs nav-sm mb-2" role="tablist">
                    <li class="nav-item"><button class="nav-link active py-1" data-bs-toggle="tab" data-bs-target="#pub-pane-contenido" type="button"><i class="bi bi-card-text me-1"></i>Contenido</button></li>
                    <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#pub-pane-destinos" type="button"><i class="bi bi-diagram-3 me-1"></i>Dónde</button></li>
                    <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#pub-pane-previa" type="button" onclick="PUB.vistaPrevia()"><i class="bi bi-phone me-1"></i>Vista previa</button></li>
                    <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#pub-pane-resultados" type="button"><i class="bi bi-graph-up me-1"></i>Resultados</button></li>
                </ul>

                <form id="pub-form" autocomplete="off" onsubmit="event.preventDefault(); PUB.guardar();">
                    <input type="hidden" id="pub-m-id">
                    <input type="hidden" id="pub-m-token">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pub-pane-contenido">
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold mb-1">Nombre interno <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="pub-m-nombre" maxlength="150" placeholder="Solo lo ve el equipo (no se publica)">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check small" title="Instagram la muestra como «Hecho con IA». Márquela si la imagen o el video los generó una IA.">
                                        <input class="form-check-input" type="checkbox" id="pub-m-ia">
                                        <label class="form-check-label" for="pub-m-ia">Contenido hecho con IA <i class="bi bi-info-circle text-muted"></i></label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center mb-1">
                                        <label class="form-label small fw-bold mb-0">Texto</label>
                                        <?php if (!empty($perm['crear'])): ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm ms-auto py-0" id="pub-ia-btn-abrir" onclick="PUB.panelIa()">
                                                <i class="bi bi-stars me-1"></i>Proponer con IA</button>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Asistente de redacción: propone; la persona decide qué usar y lo edita -->
                                    <div class="border rounded-3 p-2 mb-2 bg-light d-none" id="pub-ia-panel">
                                        <label class="form-label small fw-bold mb-1">¿De qué trata? (producto, oferta, a quién va, tono…)</label>
                                        <textarea class="form-control form-control-sm" id="pub-ia-indicaciones" rows="3" maxlength="2000"
                                                  placeholder="Ej.: Lanzamos la colección de verano de camisas de lino. Público: hombres 25-45. Envío gratis a todo el país esta semana. Tono alegre."></textarea>
                                        <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                            <div class="form-check small mb-0">
                                                <input class="form-check-input" type="checkbox" id="pub-ia-mejorar" checked>
                                                <label class="form-check-label" for="pub-ia-mejorar">Partir del texto actual</label>
                                            </div>
                                            <div class="form-check small mb-0">
                                                <input class="form-check-input" type="checkbox" id="pub-ia-alt" checked>
                                                <label class="form-check-label" for="pub-ia-alt">Proponer el texto alternativo de las imágenes</label>
                                            </div>
                                            <button type="button" class="btn btn-primary btn-sm ms-auto" id="pub-ia-btn" onclick="PUB.proponerIa()"><i class="bi bi-stars me-1"></i>Proponer</button>
                                        </div>
                                        <div class="small text-muted mt-1">Usa la IA de la empresa (IA Soporte → Configuración). No inventa precios ni fechas: lo que falte lo deja entre [corchetes]. Revise todo antes de publicar.</div>
                                        <div id="pub-ia-resultado" class="mt-2"></div>
                                    </div>
                                    <textarea class="form-control form-control-sm" id="pub-m-texto" rows="6" maxlength="3000" oninput="PUB.contar()"
                                              placeholder="Lo que sale en la publicación. En «Dónde» puede escribir un texto distinto para cada red."></textarea>
                                    <div class="small text-muted d-flex flex-wrap gap-3 mt-1" id="pub-m-contador"></div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="small fw-bold">Imágenes y videos</span>
                                        <span class="small text-muted">(el orden es el del carrusel)</span>
                                        <button type="button" class="btn btn-outline-primary btn-sm ms-auto" onclick="PUB.elegirMedios()"><i class="bi bi-images me-1"></i>Elegir de la biblioteca</button>
                                    </div>
                                    <div class="pub-sel" id="pub-m-medios"></div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pub-pane-destinos">
                            <div class="small text-muted mb-2">Marque dónde sale y en qué formato. El texto propio es opcional: si lo deja vacío, sale el de «Contenido».</div>
                            <div id="pub-m-destinos" class="d-flex flex-column gap-2"></div>
                        </div>

                        <div class="tab-pane fade" id="pub-pane-previa">
                            <div class="small text-muted mb-2">Aproximación de cómo se verá; cada red puede recortar o ajustar distinto.</div>
                            <div class="d-flex flex-wrap gap-3" id="pub-m-previa"></div>
                        </div>

                        <div class="tab-pane fade" id="pub-pane-resultados">
                            <div id="pub-m-resultados"></div>
                        </div>
                    </div>
                </form>
                <div class="small text-muted mt-3 border-top pt-2" id="pub-m-pie"></div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="pub-m-btn-eliminar" onclick="PUB.eliminar()"><i class="bi bi-trash"></i> Eliminar</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-3" id="pub-m-btn-guardar" onclick="PUB.guardar()"><i class="bi bi-check2-circle me-1"></i>Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Elegir archivos de la biblioteca -->
<div class="modal fade" id="pubModalElegir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-images text-primary me-2"></i>Elegir de la biblioteca</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <small class="text-muted">Toque para elegir o quitar; el número es el orden.</small>
                    <?php if (!empty($perm['crear'])): ?>
                        <input type="file" id="pub-e-archivos" class="d-none" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime" onchange="PUB.subir(this, true)">
                        <button type="button" class="btn btn-outline-primary btn-sm ms-auto" onclick="document.getElementById('pub-e-archivos').click()"><i class="bi bi-upload me-1"></i>Subir</button>
                    <?php endif; ?>
                </div>
                <div id="pub-e-subiendo" class="small mb-2"></div>
                <div class="pub-medios" id="pub-e-grilla"></div>
                <div class="text-center mt-2"><button type="button" class="btn btn-link btn-sm d-none" id="pub-e-mas" onclick="PUB.masElegir()">Ver más</button></div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="PUB.confirmarElegidos()"><i class="bi bi-check2 me-1"></i>Usar los elegidos</button>
            </div>
        </div>
    </div>
</div>

<!-- Datos de un archivo de la biblioteca -->
<div class="modal fade" id="pubModalMedio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-image text-primary me-2"></i>Archivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pub-md-id">
                <div class="text-center mb-2" id="pub-md-vista"></div>
                <div class="small text-muted mb-2" id="pub-md-datos"></div>
                <label class="form-label small fw-bold mb-1">Texto alternativo (para personas con discapacidad visual)</label>
                <textarea class="form-control form-control-sm" id="pub-md-alt" rows="2" maxlength="1000"></textarea>
                <div class="form-check small mt-2">
                    <input class="form-check-input" type="checkbox" id="pub-md-ia">
                    <label class="form-check-label" for="pub-md-ia">Lo generó una IA</label>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="PUB.eliminarMedio()"><i class="bi bi-trash"></i> Eliminar</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <?php if (!empty($perm['actualizar'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="PUB.guardarMedio()"><i class="bi bi-check2 me-1"></i>Guardar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configuración del módulo -->
<div class="modal fade" id="pubModalConfig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-gear text-primary me-2"></i>Configuración de publicaciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="pub-c-aprobacion">
                    <label class="form-check-label" for="pub-c-aprobacion">Exigir aprobación antes de publicar</label>
                    <div class="small text-muted">Solo sale lo que aprobó un usuario con permiso de Modificar.</div>
                </div>
                <label class="form-label small fw-bold mb-1">Tope de publicaciones por día (todas las cuentas)</label>
                <input type="number" class="form-control form-control-sm" id="pub-c-tope" min="1" max="100" style="width:120px;">
                <div class="small text-muted">Lo que pase del tope sale al día siguiente. Instagram tiene además su propio tope de 24 horas.</div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="PUB.guardarConfig()"><i class="bi bi-check2 me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>
