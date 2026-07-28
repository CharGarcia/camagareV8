<?php

/**
 * Configuración del módulo. Solo la ve y la edita el superadministrador.
 *
 * Dos ámbitos:
 *   GLOBAL      → servidores que hereda todo el sistema. Se cargan una sola vez.
 *   ESTA EMPRESA→ límites propios y, si hace falta, sus propios servidores.
 *
 * Los secretos nunca se muestran: el campo llega vacío y dejarlo así conserva
 * el valor guardado.
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

            <div class="modal-body p-0">

                <!-- Resumen de lo que se está usando ahora mismo -->
                <div class="px-3 py-2 bg-light border-bottom">
                    <div class="small fw-bold text-uppercase text-muted mb-1" style="font-size:.66rem;">
                        En uso por esta empresa
                    </div>
                    <div id="cfg-efectiva" class="small text-muted">Cargando...</div>
                </div>

                <ul class="nav nav-tabs px-3 pt-2 bg-light" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active py-2 small fw-bold" data-bs-toggle="tab" href="#cfg-tab-global" role="tab">
                            <i class="bi bi-globe2 me-1"></i> Global
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 small fw-bold" data-bs-toggle="tab" href="#cfg-tab-empresa" role="tab">
                            <i class="bi bi-building me-1"></i> Esta empresa
                        </a>
                    </li>
                </ul>

                <div class="tab-content border-top">

                    <!-- ─── GLOBAL ──────────────────────────────────────── -->
                    <div class="tab-pane fade show active p-3" id="cfg-tab-global" role="tabpanel">

                        <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 py-2 px-3 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Estos servidores los usan <strong>todas las empresas</strong> del sistema. Se cargan una
                            sola vez aquí. El <strong>STUN</strong> es gratuito y resuelve la mayoría de las conexiones;
                            el <strong>TURN</strong> es el plan B para quienes están detrás de un cortafuegos o en
                            internet móvil.
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Servidores STUN</label>
                                <input type="text" id="cfgg-stun" class="form-control form-control-sm"
                                       placeholder="stun:stun.l.google.com:19302">
                                <div class="form-text small">Separados por comas. El de Google es gratuito y suele bastar.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Servidores TURN</label>
                                <input type="text" id="cfgg-turn-urls" class="form-control form-control-sm"
                                       placeholder="turn:turn.cloudflare.com:3478">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Usuario TURN</label>
                                <input type="text" id="cfgg-turn-usuario" class="form-control form-control-sm" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">
                                    Credencial TURN
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-none"
                                          id="cfgg-badge-credencial" style="font-size:.6rem;">GUARDADA</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="password" id="cfgg-turn-credencial" class="form-control form-control-sm"
                                           placeholder="Dejar vacío para no cambiarla" autocomplete="new-password">
                                    <button class="btn btn-outline-danger" type="button"
                                            onclick="VC_borrarSecreto('turn_credencial', true)" title="Borrar la credencial guardada">
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
                            Con estos dos datos, el sistema pide a Cloudflare una credencial nueva y de corta duración
                            cada vez que alguien entra a una sala. Es más seguro que la credencial fija: la clave
                            maestra se queda en el servidor y nunca llega al navegador.
                        </p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">TURN Key ID</label>
                                <input type="text" id="cfgg-turn-key-id" class="form-control form-control-sm" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">
                                    Token de API
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-none"
                                          id="cfgg-badge-token" style="font-size:.6rem;">GUARDADO</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="password" id="cfgg-turn-api-token" class="form-control form-control-sm"
                                           placeholder="Dejar vacío para no cambiarlo" autocomplete="new-password">
                                    <button class="btn btn-outline-danger" type="button"
                                            onclick="VC_borrarSecreto('turn_api_token', true)" title="Borrar el token guardado">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Participantes por defecto</label>
                                <input type="number" id="cfgg-max-def" class="form-control form-control-sm" min="2" max="<?= (int) $maxMesh ?>">
                                <div class="form-text small">Para las empresas nuevas.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Duración por defecto</label>
                                <input type="number" id="cfgg-dur-def" class="form-control form-control-sm" min="5" max="1440">
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" id="cfgg-override">
                                    <label class="form-check-label small" for="cfgg-override">
                                        Permitir que una empresa use sus propios servidores
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary btn-sm px-3" onclick="VC_guardarConfigGlobal()">
                                <i class="bi bi-save me-1"></i> Guardar configuración global
                            </button>
                        </div>
                    </div>

                    <!-- ─── ESTA EMPRESA ────────────────────────────────── -->
                    <div class="tab-pane fade p-3" id="cfg-tab-empresa" role="tabpanel">

                        <div class="alert alert-secondary bg-secondary bg-opacity-10 border-secondary border-opacity-25 py-2 px-3 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Los <strong>límites</strong> son siempre propios de esta empresa. Los <strong>servidores</strong>
                            se heredan de la configuración global: solo hay que llenarlos aquí si esta empresa debe usar
                            un proveedor distinto. Si deja un bloque vacío, hereda el global completo.
                        </div>

                        <h6 class="small fw-bold text-uppercase text-muted" style="font-size:.7rem;">Límites</h6>
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

                        <h6 class="small fw-bold text-uppercase text-muted" style="font-size:.7rem;">
                            Servidores propios <span class="fw-normal text-lowercase">(opcional)</span>
                        </h6>

                        <div id="cfg-aviso-override" class="alert alert-warning py-2 px-3 small d-none">
                            <i class="bi bi-lock me-1"></i>
                            La configuración global no permite que las empresas usen servidores propios.
                            Lo que se cargue aquí no se aplicará.
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Servidores STUN</label>
                                <input type="text" id="cfg-stun" class="form-control form-control-sm"
                                       placeholder="Vacío = hereda el global">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Servidores TURN</label>
                                <input type="text" id="cfg-turn-urls" class="form-control form-control-sm"
                                       placeholder="Vacío = hereda el global">
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
                                    <button class="btn btn-outline-danger" type="button"
                                            onclick="VC_borrarSecreto('turn_credencial', false)" title="Borrar la credencial guardada">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
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
                                    <button class="btn btn-outline-danger" type="button"
                                            onclick="VC_borrarSecreto('turn_api_token', false)" title="Borrar el token guardado">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary btn-sm px-3" onclick="VC_guardarConfig()">
                                <i class="bi bi-save me-1"></i> Guardar configuración de la empresa
                            </button>
                        </div>
                    </div>
                </div>

                <div id="cfg-resultado-prueba" class="px-3 pb-3"></div>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="VC_probarTurn()">
                    <i class="bi bi-broadcast me-1"></i> Probar configuración
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>
