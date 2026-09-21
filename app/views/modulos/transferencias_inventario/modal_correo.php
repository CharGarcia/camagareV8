<?php
/**
 * Modal de envío del acta de la transferencia por correo.
 * Los destinatarios se escriben libremente y además se pueden agregar con un
 * clic desde la lista de usuarios del sistema (los que operan la bodega de
 * destino aparecen primero). El correo lleva el enlace con el que el
 * destinatario confirma la recepción.
 *
 * Se puede abrir suelto (desde la fila del listado) o encima del modal del
 * documento: el JS del módulo lo eleva sobre ese otro modal al mostrarlo.
 */
?>
<div class="modal fade" id="modalTransferenciaCorreo" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-light py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-envelope text-info me-2"></i>
                    Enviar acta por correo
                    <span id="tri-correo-numero" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-3">
                <input type="hidden" id="tri-correo-id" value="">

                <div id="tri-correo-estado" class="alert alert-success py-2 px-3 small d-none mb-3"></div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Usuarios del sistema</label>
                    <select id="tri-correo-usuarios" class="form-select form-select-sm" onchange="window.TRI_agregarCorreoUsuario(this)">
                        <option value="">Seleccione un usuario para agregar su correo…</option>
                    </select>
                    <div class="form-text small">Se agrega a la lista de destinatarios; puede elegir varios.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold" for="tri-correo-para">Para *</label>
                    <textarea id="tri-correo-para" class="form-control form-control-sm" rows="2"
                              placeholder="correo@empresa.com, otro@empresa.com"></textarea>
                    <div class="form-text small">Separe varios correos con coma o punto y coma.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold" for="tri-correo-mensaje">Mensaje adicional (opcional)</label>
                    <textarea id="tri-correo-mensaje" class="form-control form-control-sm" rows="2"
                              placeholder="Nota para quien recibe…"></textarea>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="tri-correo-pdf" checked>
                    <label class="form-check-label small" for="tri-correo-pdf">Adjuntar el acta en PDF</label>
                </div>

                <p class="small text-muted mb-0 mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    El correo incluye un enlace con el que el destinatario confirma (o rechaza) la recepción.
                    Confirmar no mueve stock: el inventario ya se trasladó al registrar la transferencia.
                </p>
            </div>

            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="tri-correo-btn" class="btn btn-info btn-sm px-4 text-white" onclick="window.TRI_enviarCorreo()">
                    <i class="bi bi-send me-1"></i> Enviar
                </button>
            </div>
        </div>
    </div>
</div>
