<?php

/**
 * Configuración de las videollamadas DE ESTA EMPRESA.
 *
 * Solo lo que es propio de la empresa: cuánta gente cabe y cuánto puede durar
 * una reunión. Los servidores STUN/TURN son globales del sistema y se
 * administran en Configuración → Videollamadas.
 *
 * La excepción son los servidores propios, que solo ve el superadministrador:
 * sirven para que una empresa concreta use un proveedor distinto al del resto.
 *
 * @var array $perm
 * @var int   $maxMesh
 * @var bool  $esSuperadmin
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

                <div class="alert alert-light border py-2 px-3 small">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    Los servidores que hacen posible la conexión son globales del sistema.
                </div>

                <h6 class="small fw-bold text-uppercase text-muted" style="font-size:.7rem;">Límites de las reuniones</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Máximo de participantes</label>
                        <input type="number" id="cfg-max" class="form-control form-control-sm" min="2" max="<?= (int) $maxMesh ?>">
                        <div class="form-text small">
                            Tope técnico: <?= (int) $maxMesh ?>. Por encima de 6 la calidad baja en
                            conexiones lentas, porque cada participante envía su video a todos los demás.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Duración máxima (minutos)</label>
                        <input type="number" id="cfg-duracion" class="form-control form-control-sm" min="5" max="1440">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Umbral de proveedor externo</label>
                        <input type="number" id="cfg-umbral" class="form-control form-control-sm" min="2" max="100">
                        <div class="form-text small">
                            A partir de este número de participantes haría falta un motor de video externo.
                        </div>
                    </div>
                </div>

                <?php if (!empty($esSuperadmin)): ?>
                <hr class="my-3">

                <h6 class="small fw-bold text-uppercase text-muted" style="font-size:.7rem;">
                    Servidores propios de esta empresa
                </h6>
                <p class="small text-muted mb-2">
                    Esta empresa puede usar sus propios servidores en lugar de los del sistema. Tiene
                    sentido en dos casos: cuando se contrata un servicio <strong>más cercano o de mayor
                    capacidad</strong>, que mejora la calidad de la llamada y reduce los cortes; o cuando
                    esta empresa hace <strong>muchas más reuniones que las demás</strong> y conviene que
                    su tráfico no compita con el del resto.
                </p>
                <p class="small text-muted mb-2">
                    Si se deja <strong>vacío</strong>, la empresa usa los servidores del sistema, que es
                    lo normal. Si se llena, hay que cargar el bloque completo —dirección, usuario y
                    credencial del mismo proveedor—: combinar la dirección de uno con la credencial de
                    otro deja una configuración que no conecta con ninguno.
                </p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Servidores STUN</label>
                        <input type="text" id="cfg-stun" class="form-control form-control-sm"
                               placeholder="Vacío = hereda el global">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Servidores TURN</label>
                        <input type="text" id="cfg-turn-urls" class="form-control form-control-sm"
                               placeholder="Vacío = hereda el global">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Usuario TURN</label>
                        <input type="text" id="cfg-turn-usuario" class="form-control form-control-sm" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">
                            Credencial TURN
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-none"
                                  id="cfg-badge-credencial" style="font-size:.6rem;">GUARDADA</span>
                        </label>
                        <input type="password" id="cfg-turn-credencial" class="form-control form-control-sm"
                               placeholder="Vacío = no cambiar" autocomplete="new-password">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">
                            TURN Key ID
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-none"
                                  id="cfg-badge-token" style="font-size:.6rem;">TOKEN GUARDADO</span>
                        </label>
                        <input type="text" id="cfg-turn-key-id" class="form-control form-control-sm" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Token de API</label>
                        <input type="password" id="cfg-turn-api-token" class="form-control form-control-sm"
                               placeholder="Vacío = no cambiar" autocomplete="new-password">
                    </div>
                </div>

                <div class="mt-3 small" id="cfg-efectiva"></div>
                <?php endif; ?>
            </div>

            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm px-3" onclick="VC_guardarConfig()">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>

        </div>
    </div>
</div>
