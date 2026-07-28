<?php

/**
 * Configuración del módulo por empresa: servidores STUN/TURN y límites.
 *
 * Los secretos (credencial TURN, token de API) nunca se muestran: el campo
 * llega vacío y dejarlo así conserva el valor guardado.
 *
 * @var array $perm
 * @var int   $maxMesh
 */
?>
<div class="modal fade" id="modalConfigVC" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-gear-fill text-secondary me-2"></i> Configuración de videollamadas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 py-2 px-3 small">
                    <i class="bi bi-info-circle me-1"></i>
                    El <strong>STUN</strong> es gratuito y sirve para que los navegadores descubran su dirección
                    pública; con él se conectan la mayoría de las llamadas. El <strong>TURN</strong> es el plan B
                    para quienes están detrás de un cortafuegos o en internet móvil: sin él, entre el 10% y el 20%
                    de las llamadas no logra conectarse.
                </div>

                <h6 class="small fw-bold text-uppercase text-muted mt-3" style="font-size:.7rem;">Límites</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Máximo de participantes</label>
                        <input type="number" id="cfg-max" class="form-control form-control-sm" min="2" max="<?= (int) $maxMesh ?>">
                        <div class="form-text small">Tope del motor interno: <?= (int) $maxMesh ?>.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Duración máxima (minutos)</label>
                        <input type="number" id="cfg-duracion" class="form-control form-control-sm" min="5" max="1440">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Umbral de proveedor externo</label>
                        <input type="number" id="cfg-umbral" class="form-control form-control-sm" min="2" max="100">
                        <div class="form-text small">Sobre este número haría falta un motor externo.</div>
                    </div>
                </div>

                <hr class="my-3">

                <h6 class="small fw-bold text-uppercase text-muted" style="font-size:.7rem;">Servidores</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Servidores STUN</label>
                        <input type="text" id="cfg-stun" class="form-control form-control-sm"
                               placeholder="stun:stun.l.google.com:19302">
                        <div class="form-text small">Separados por comas. El de Google es gratuito y suele bastar.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Servidores TURN</label>
                        <input type="text" id="cfg-turn-urls" class="form-control form-control-sm"
                               placeholder="turn:turn.ejemplo.com:3478">
                        <div class="form-text small">Separados por comas.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Usuario TURN</label>
                        <input type="text" id="cfg-turn-usuario" class="form-control form-control-sm" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">
                            Credencial TURN
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-none"
                                  id="cfg-badge-credencial" style="font-size:.6rem;">GUARDADA</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="password" id="cfg-turn-credencial" class="form-control form-control-sm"
                                   placeholder="Dejar vacío para no cambiarla" autocomplete="new-password">
                            <button class="btn btn-outline-danger" type="button" onclick="VC_borrarSecreto('turn_credencial')"
                                    title="Borrar la credencial guardada">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <h6 class="small fw-bold text-uppercase text-muted" style="font-size:.7rem;">
                    Credenciales temporales (Cloudflare)
                </h6>
                <p class="small text-muted mb-2">
                    Si carga estos dos datos, el sistema pide a Cloudflare una credencial nueva y de corta duración
                    cada vez que alguien entra a una sala. Es más seguro que la credencial fija: la clave maestra
                    se queda en el servidor y nunca llega al navegador.
                </p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">TURN Key ID</label>
                        <input type="text" id="cfg-turn-key-id" class="form-control form-control-sm" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">
                            Token de API
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-none"
                                  id="cfg-badge-token" style="font-size:.6rem;">GUARDADO</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="password" id="cfg-turn-api-token" class="form-control form-control-sm"
                                   placeholder="Dejar vacío para no cambiarlo" autocomplete="new-password">
                            <button class="btn btn-outline-danger" type="button" onclick="VC_borrarSecreto('turn_api_token')"
                                    title="Borrar el token guardado">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="cfg-resultado-prueba" class="mt-3"></div>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="VC_probarTurn()">
                    <i class="bi bi-broadcast me-1"></i> Probar configuración
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <?php if ($perm['actualizar']): ?>
                        <button type="button" class="btn btn-primary btn-sm px-3" onclick="VC_guardarConfig()">
                            <i class="bi bi-save me-1"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
