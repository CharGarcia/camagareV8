<?php
/**
 * Bandeja del equipo de soporte.
 *
 * Es la pantalla que consulta conversaciones de TODAS las empresas (excepción
 * documentada a §4). Llegar aquí exige permiso sobre el submódulo y, además,
 * ser agente de soporte — el controlador ya redirigió si no lo es.
 *
 * No usa cmg-table-card: no es un listado tabular sino un chat de dos paneles,
 * mismo criterio que la bandeja de WhatsApp.
 */
$base = rtrim(BASE_URL, '/');
?>

<div class="px-2">

    <div class="row m-0 border rounded shadow-sm overflow-hidden bg-white" style="height: calc(100dvh - 150px);">

        <!-- Panel izquierdo: conversaciones -->
        <div class="col-12 col-md-5 col-lg-4 border-end p-0 d-flex flex-column" id="socListaPanel" style="height:100%;">
            <div class="p-2 border-bottom bg-light">
                <div class="d-flex gap-2 mb-2">
                    <input type="text" id="socBuscar" class="form-control form-control-sm"
                           placeholder="Buscar empresa, usuario o asunto…">
                    <button class="btn btn-sm btn-outline-secondary" id="socBtnRefrescar" title="Refrescar">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <?php if (!empty($perm['actualizar'])): ?>
                    <button class="btn btn-sm btn-outline-secondary" id="socBtnConfig"
                            data-bs-toggle="modal" data-bs-target="#socModalConfig" title="Configuración del chat">
                        <i class="bi bi-gear"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <select id="socEstado" class="form-select form-select-sm" style="width:auto;">
                        <option value="">Activas</option>
                        <option value="espera">En espera</option>
                        <option value="atendiendo">Atendiendo</option>
                        <option value="resuelta">Resueltas</option>
                        <option value="cerrada">Cerradas</option>
                        <option value="todas">Todas</option>
                    </select>
                    <div class="form-check form-check-inline m-0">
                        <input class="form-check-input" type="checkbox" id="socSoloMias">
                        <label class="form-check-label small" for="socSoloMias">Solo mías</label>
                    </div>
                    <div class="form-check form-check-inline m-0">
                        <input class="form-check-input" type="checkbox" id="socArchivadas">
                        <label class="form-check-label small" for="socArchivadas">Archivadas</label>
                    </div>
                </div>
            </div>
            <div class="flex-grow-1 overflow-auto" id="socLista">
                <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>

        <!-- Panel derecho: conversación -->
        <div class="col-12 col-md-7 col-lg-8 p-0 d-flex flex-column bg-light" id="socChatPanel" style="height:100%;">

            <div id="socVacio" class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-muted">
                <i class="bi bi-chat-left-dots opacity-25" style="font-size:5rem;"></i>
                <p class="mt-3 mb-0">Selecciona una conversación para responder.</p>
            </div>

            <!-- Cabecera de la conversación -->
            <div class="p-2 border-bottom bg-white d-none" id="socHeader">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-light d-md-none" id="socBtnVolver" title="Volver">
                        <i class="bi bi-arrow-left"></i>
                    </button>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-semibold text-truncate" id="socAsunto">—</div>
                        <small class="text-muted d-block text-truncate" id="socMeta"></small>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button class="btn btn-sm btn-outline-primary" id="socBtnTomar" title="Tomar esta conversación">
                            <i class="bi bi-person-check"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" id="socBtnResolver" title="Marcar como resuelta">
                            <i class="bi bi-check2-circle"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" id="socBtnCerrar" title="Cerrar conversación">
                            <i class="bi bi-archive"></i>
                        </button>
                        <?php if (!empty($perm['eliminar'])): ?>
                        <button class="btn btn-sm btn-outline-danger" id="socBtnEliminar" title="Eliminar conversación">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-1 d-none" id="socOrigen">
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-geo-alt me-1"></i><span id="socOrigenTexto"></span>
                    </span>
                </div>
            </div>

            <!-- Mensajes -->
            <div class="flex-grow-1 overflow-auto p-3 d-none" id="socMensajes" style="background:#f7f8fa;"></div>

            <!-- Caja de escritura -->
            <div class="p-2 bg-white border-top d-none" id="socInputArea" style="position:relative;">

                <!-- Respuestas rápidas: flota sobre el chat, anclado al input -->
                <div id="socRRPanel" class="shadow"
                     style="display:none;position:absolute;bottom:100%;left:8px;right:8px;z-index:200;
                            margin-bottom:4px;max-height:340px;background:#fff;border:1px solid #dee2e6;
                            border-radius:12px;flex-direction:column;overflow:hidden;">
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-light"
                         style="flex-shrink:0;">
                        <span class="fw-semibold" style="font-size:.85rem;">
                            <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Respuestas rápidas
                        </span>
                        <button type="button" class="btn-close" style="font-size:.7rem;" id="socRRCerrar"></button>
                    </div>

                    <!-- Alta/edición en línea -->
                    <div id="socRRForm" class="px-3 py-2 border-bottom" style="display:none;flex-shrink:0;">
                        <input type="hidden" id="socRRId" value="">
                        <input type="hidden" id="socRRTipo" value="personal">
                        <input type="text" id="socRRTitulo" class="form-control form-control-sm mb-1"
                               placeholder="Título (ej: Cómo anular una factura)" maxlength="100">
                        <textarea id="socRRContenido" class="form-control form-control-sm mb-1" rows="3"
                                  placeholder="Texto completo de la respuesta…"
                                  style="resize:vertical;font-size:.82rem;"></textarea>
                        <div class="d-flex gap-1 justify-content-end">
                            <button type="button" class="btn btn-sm btn-light border" id="socRRCancelar">Cancelar</button>
                            <button type="button" class="btn btn-sm btn-primary" id="socRRGuardar">
                                <i class="bi bi-check-lg me-1"></i>Guardar
                            </button>
                        </div>
                    </div>

                    <div id="socRRLista" style="overflow-y:auto;flex:1 1 auto;font-size:.82rem;"></div>

                    <div class="d-flex border-top bg-light" style="flex-shrink:0;font-size:.78rem;">
                        <button type="button" class="btn btn-sm btn-light flex-fill rounded-0 py-1 border-end"
                                id="socRRNuevaEmpresa" title="Nueva respuesta para todo el equipo">
                            <i class="bi bi-building me-1 text-primary"></i>+ Equipo
                        </button>
                        <button type="button" class="btn btn-sm btn-light flex-fill rounded-0 py-1"
                                id="socRRNuevaPersonal" title="Nueva respuesta solo para ti">
                            <i class="bi bi-person me-1 text-secondary"></i>+ Personal
                        </button>
                    </div>
                </div>

                <?php if (!empty($copilotoActivo)): ?>
                <!-- Copiloto: redacta un borrador que el agente edita antes de enviar.
                     Es a petición, no automático: no se gastan tokens en las
                     conversaciones que el agente resuelve de memoria. -->
                <div class="d-flex align-items-center gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="socBtnSugerir"
                            title="La IA redacta un borrador con el Manual del Sistema; tú lo revisas antes de enviar">
                        <i class="bi bi-stars me-1"></i> Sugerir respuesta
                    </button>
                    <small class="text-muted d-none" id="socSugerenciaAviso"></small>
                </div>
                <?php endif; ?>

                <form id="socForm" class="d-flex align-items-end gap-2">
                    <input type="file" id="socFile" class="d-none"
                           accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.xml,.txt,.csv,.xlsx,.xls,.docx,.zip">
                    <button type="button" class="btn btn-light btn-sm border" id="socBtnAdjuntar"
                            style="height:31px;" title="Adjuntar archivo (máx. 10 MB)">
                        <i class="bi bi-paperclip"></i>
                    </button>
                    <button type="button" class="btn btn-light btn-sm border" id="socBtnRR"
                            style="height:31px;" title="Respuestas rápidas">
                        <i class="bi bi-lightning-charge-fill text-warning"></i>
                    </button>
                    <textarea id="socInput" class="form-control form-control-sm" rows="1"
                              placeholder="Escribe tu respuesta…" maxlength="4000" style="resize:none;"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm" style="width:40px;height:31px;" title="Enviar">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($perm['actualizar'])): ?>
<!-- Configuración global del chat (soporte_config: fila única del sistema) -->
<div class="modal fade" id="socModalConfig" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-gear me-2"></i>Configuración del chat de soporte</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 d-flex gap-4 flex-wrap">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cfgActivo">
                            <label class="form-check-label small" for="cfgActivo">
                                Chat activo <span class="text-muted">(apaga la burbuja en todo el sistema)</span>
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cfgCopiloto">
                            <label class="form-check-label small" for="cfgCopiloto">
                                Copiloto de IA <span class="text-muted">(botón "Sugerir respuesta")</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Mensaje de bienvenida</label>
                        <input type="text" id="cfgBienvenida" class="form-control form-control-sm" maxlength="500">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Mensaje fuera de horario</label>
                        <input type="text" id="cfgFueraHorario" class="form-control form-control-sm" maxlength="500">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Días de atención</label>
                        <div class="d-flex gap-3 flex-wrap">
                            <?php
                            $diasSemana = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
                            foreach ($diasSemana as $n => $etiqueta): ?>
                                <div class="form-check">
                                    <input class="form-check-input cfg-dia" type="checkbox" value="<?= $n ?>" id="cfgDia<?= $n ?>">
                                    <label class="form-check-label small" for="cfgDia<?= $n ?>"><?= $etiqueta ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold">Desde</label>
                        <input type="time" id="cfgHoraInicio" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold">Hasta</label>
                        <input type="time" id="cfgHoraFin" class="form-control form-control-sm">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Correo para avisos</label>
                        <input type="email" id="cfgCorreo" class="form-control form-control-sm"
                               placeholder="Opcional" maxlength="150">
                        <div class="form-text" style="font-size:.72rem;">
                            Si lo deja vacío, los avisos llegan al correo de la empresa que administra
                            el chat y, si no hay ninguna marcada, a los superadministradores.
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold">Avisar tras (min)</label>
                        <input type="number" id="cfgMinutos" class="form-control form-control-sm" min="0" max="1440">
                        <div class="form-text" style="font-size:.72rem;">0 = no avisar</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">WhatsApp para avisos</label>
                        <input type="text" id="cfgWhatsapp" class="form-control form-control-sm"
                               placeholder="Opcional — con código de país, sin +" maxlength="20" inputmode="numeric">
                        <div class="form-text" style="font-size:.72rem;">
                            El mismo aviso sale también por WhatsApp con la empresa y el usuario que
                            piden soporte. Si lo deja vacío, se envía a los números ya registrados en
                            la configuración de WhatsApp de la empresa que atiende.
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-semibold">Plantilla de WhatsApp</label>
                        <input type="text" id="cfgWhatsappPlantilla" class="form-control form-control-sm"
                               placeholder="Opcional" maxlength="150">
                        <div class="form-text" style="font-size:.72rem;">
                            Nombre de la plantilla aprobada en Meta ({{1}} empresa, {{2}} usuario,
                            {{3}} asunto). Vacío = mensaje de texto, que solo llega si el número
                            escribió al negocio en las últimas 24 horas.
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold">Idioma</label>
                        <input type="text" id="cfgWhatsappIdioma" class="form-control form-control-sm"
                               placeholder="es" maxlength="10">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold">Archivar tras (días)</label>
                        <input type="number" id="cfgDiasArchivar" class="form-control form-control-sm" min="0" max="3650">
                        <div class="form-text" style="font-size:.72rem;">0 = nunca</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-sm btn-primary" id="cfgGuardar">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.soc-item { padding: 10px 12px; border-bottom: 1px solid #f1f3f5; cursor: pointer; }
.soc-item:hover  { background: #f8f9fa; }
.soc-item.activo { background: #e7f1ff !important; }
.soc-item-prev {
    font-size: .76rem; color: #6c757d;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

#socMensajes { display: flex; flex-direction: column; gap: 8px; }
.soc-burbuja { max-width: 72%; padding: 8px 12px; border-radius: 12px; font-size: .86rem; box-shadow: 0 1px 1px rgba(0,0,0,.06); }
.soc-burbuja-usuario { background: #fff;    align-self: flex-start; border-bottom-left-radius: 3px; }
.soc-burbuja-agente  { background: #d1e7ff; align-self: flex-end;   border-bottom-right-radius: 3px; }
.soc-burbuja-sistema {
    background: transparent; align-self: center; color: #6c757d;
    font-size: .76rem; font-style: italic; box-shadow: none;
}
.soc-hora { font-size: .68rem; color: rgba(0,0,0,.45); margin-top: 3px; text-align: right; }

@media (max-width: 767.98px) {
    #socListaPanel, #socChatPanel { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
    #socChatPanel { display: none !important; }
    #socChatPanel.soc-abierto  { display: flex !important; }
    #socListaPanel.soc-oculto  { display: none !important; }
}
</style>

<script>
    window.SOC_BASE = '<?= $base ?>';
</script>
<script src="<?= $base ?>/js/modulos/soporte_chat.js?v=<?= @filemtime(MVC_ROOT . '/public/js/modulos/soporte_chat.js') ?: time() ?>"></script>
