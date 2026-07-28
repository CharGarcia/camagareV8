<?php

/**
 * Configuración de videollamadas (pantalla de /config, solo nivel 3).
 *
 * Dos ámbitos:
 *   GLOBAL       → servidores que hereda todo el sistema. Se cargan una vez.
 *   ESTA EMPRESA → límites propios y, si hace falta, sus propios servidores.
 *
 * Los secretos nunca se muestran: el campo llega vacío y dejarlo así conserva
 * el valor guardado.
 *
 * @var array $empresa
 * @var array $global
 * @var array $efectiva
 * @var int   $maxMesh
 */

$base = rtrim(BASE_URL, '/');
$url  = $base . '/config/videollamadas';
?>

<div class="container-fluid px-3 px-lg-4 py-3" style="max-width: 1100px;">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-camera-video-fill me-1 text-primary"></i> Configuración de videollamadas
        </h5>
        <div class="d-flex gap-2">
            <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Configuración
            </a>
            <a href="<?= $base ?>/modulos/videollamadas" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Ir a las reuniones
            </a>
        </div>
    </div>

    <!-- Qué se está usando ahora mismo -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body py-2 px-3">
            <div class="small fw-bold text-uppercase text-muted mb-1" style="font-size:.66rem;">
                En uso por esta empresa
            </div>
            <div id="cfg-efectiva" class="small text-muted">
                <?php
                $etiqueta = static fn (string $origen): string => $origen === 'empresa'
                    ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:.6rem;">PROPIO</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.6rem;">HEREDADO</span>';
                $hayTurn = $efectiva['turn_urls'] !== '' || $efectiva['turn_key_id'] !== '';
                ?>
                <div><strong>STUN:</strong> <?= htmlspecialchars($efectiva['stun_urls']) ?: '<em>ninguno</em>' ?></div>
                <?php if ($hayTurn): ?>
                    <div>
                        <strong>TURN:</strong>
                        <?= htmlspecialchars($efectiva['turn_urls']) ?: '<em>por credencial temporal</em>' ?>
                        <?= $etiqueta($efectiva['turn_urls'] !== '' ? $efectiva['origen_turn'] : $efectiva['origen_cloud']) ?>
                    </div>
                <?php else: ?>
                    <div class="text-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Sin TURN: entre el 10% y el 20% de las llamadas no va a conectar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white pt-2 pb-0 px-3 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
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
        </div>

        <div class="card-body p-0">
            <div class="tab-content">

                <!-- ─── GLOBAL ──────────────────────────────────────────── -->
                <div class="tab-pane fade show active p-3" id="cfg-tab-global" role="tabpanel">

                    <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 py-2 px-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Estos servidores los usan <strong>todas las empresas</strong> del sistema. El
                        <strong>STUN</strong> es gratuito y resuelve la mayoría de las conexiones; el
                        <strong>TURN</strong> es el plan B para quienes están detrás de un cortafuegos o
                        en internet móvil.
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Servidores STUN</label>
                            <input type="text" id="cfgg-stun" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($global['stun_urls']) ?>"
                                   placeholder="stun:stun.l.google.com:19302">
                            <div class="form-text small">Separados por comas. El de Google es gratuito y suele bastar.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Servidores TURN</label>
                            <input type="text" id="cfgg-turn-urls" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($global['turn_urls']) ?>"
                                   placeholder="turn:turn.cloudflare.com:3478">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Usuario TURN</label>
                            <input type="text" id="cfgg-turn-usuario" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($global['turn_usuario']) ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">
                                Credencial TURN
                                <?php if ($global['turn_credencial_puesta']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"
                                          style="font-size:.6rem;">GUARDADA</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="password" id="cfgg-turn-credencial" class="form-control form-control-sm"
                                       placeholder="Dejar vacío para no cambiarla" autocomplete="new-password">
                                <button class="btn btn-outline-danger" type="button"
                                        onclick="VCFG_borrarSecreto('turn_credencial', true)" title="Borrar la credencial guardada">
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
                        Con estos dos datos, el sistema pide a Cloudflare una credencial nueva y de corta
                        duración cada vez que alguien entra a una sala. Es más seguro que la credencial
                        fija: la clave maestra se queda en el servidor y nunca llega al navegador.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">TURN Key ID</label>
                            <input type="text" id="cfgg-turn-key-id" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($global['turn_key_id']) ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">
                                Token de API
                                <?php if ($global['turn_api_token_puesto']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"
                                          style="font-size:.6rem;">GUARDADO</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="password" id="cfgg-turn-api-token" class="form-control form-control-sm"
                                       placeholder="Dejar vacío para no cambiarlo" autocomplete="new-password">
                                <button class="btn btn-outline-danger" type="button"
                                        onclick="VCFG_borrarSecreto('turn_api_token', true)" title="Borrar el token guardado">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Participantes por defecto</label>
                            <input type="number" id="cfgg-max-def" class="form-control form-control-sm"
                                   min="2" max="<?= (int) $maxMesh ?>" value="<?= (int) $global['max_participantes_defecto'] ?>">
                            <div class="form-text small">Para las empresas nuevas.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Duración por defecto (minutos)</label>
                            <input type="number" id="cfgg-dur-def" class="form-control form-control-sm"
                                   min="5" max="1440" value="<?= (int) $global['duracion_max_defecto'] ?>">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="cfgg-override"
                                       <?= $global['permite_override_empresa'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="cfgg-override">
                                    Permitir que una empresa use sus propios servidores
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-primary btn-sm px-3" onclick="VCFG_guardarGlobal()">
                            <i class="bi bi-save me-1"></i> Guardar configuración global
                        </button>
                    </div>
                </div>

                <!-- ─── ESTA EMPRESA ────────────────────────────────────── -->
                <div class="tab-pane fade p-3" id="cfg-tab-empresa" role="tabpanel">

                    <div class="alert alert-secondary bg-secondary bg-opacity-10 border-secondary border-opacity-25 py-2 px-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Los <strong>límites</strong> son siempre propios de esta empresa. Los
                        <strong>servidores</strong> se heredan de la configuración global: solo hay que
                        llenarlos aquí si esta empresa debe usar un proveedor distinto. Si deja un bloque
                        vacío, hereda el global completo.
                    </div>

                    <h6 class="small fw-bold text-uppercase text-muted" style="font-size:.7rem;">Límites</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Máximo de participantes</label>
                            <input type="number" id="cfg-max" class="form-control form-control-sm"
                                   min="2" max="<?= (int) $maxMesh ?>" value="<?= (int) $empresa['max_participantes'] ?>">
                            <div class="form-text small">Tope del motor interno: <?= (int) $maxMesh ?>.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Duración máxima (minutos)</label>
                            <input type="number" id="cfg-duracion" class="form-control form-control-sm"
                                   min="5" max="1440" value="<?= (int) $empresa['duracion_max_minutos'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Umbral de proveedor externo</label>
                            <input type="number" id="cfg-umbral" class="form-control form-control-sm"
                                   min="2" max="100" value="<?= (int) $empresa['umbral_proveedor_externo'] ?>">
                            <div class="form-text small">Sobre este número haría falta un motor externo.</div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <h6 class="small fw-bold text-uppercase text-muted" style="font-size:.7rem;">
                        Servidores propios <span class="fw-normal text-lowercase">(opcional)</span>
                    </h6>

                    <?php if (!$efectiva['puede_anular']): ?>
                        <div class="alert alert-warning py-2 px-3 small">
                            <i class="bi bi-lock me-1"></i>
                            La configuración global no permite que las empresas usen servidores propios.
                            Lo que se cargue aquí no se aplicará.
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Servidores STUN</label>
                            <input type="text" id="cfg-stun" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($empresa['stun_urls']) ?>"
                                   placeholder="Vacío = hereda el global">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Servidores TURN</label>
                            <input type="text" id="cfg-turn-urls" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($empresa['turn_urls']) ?>"
                                   placeholder="Vacío = hereda el global">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Usuario TURN</label>
                            <input type="text" id="cfg-turn-usuario" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($empresa['turn_usuario']) ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">
                                Credencial TURN
                                <?php if ($empresa['turn_credencial_puesta']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"
                                          style="font-size:.6rem;">GUARDADA</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="password" id="cfg-turn-credencial" class="form-control form-control-sm"
                                       placeholder="Dejar vacío para no cambiarla" autocomplete="new-password">
                                <button class="btn btn-outline-danger" type="button"
                                        onclick="VCFG_borrarSecreto('turn_credencial', false)" title="Borrar la credencial guardada">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">TURN Key ID</label>
                            <input type="text" id="cfg-turn-key-id" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($empresa['turn_key_id']) ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">
                                Token de API
                                <?php if ($empresa['turn_api_token_puesto']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"
                                          style="font-size:.6rem;">GUARDADO</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="password" id="cfg-turn-api-token" class="form-control form-control-sm"
                                       placeholder="Dejar vacío para no cambiarlo" autocomplete="new-password">
                                <button class="btn btn-outline-danger" type="button"
                                        onclick="VCFG_borrarSecreto('turn_api_token', false)" title="Borrar el token guardado">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-primary btn-sm px-3" onclick="VCFG_guardarEmpresa()">
                            <i class="bi bi-save me-1"></i> Guardar configuración de la empresa
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="VCFG_probar()">
                <i class="bi bi-broadcast me-1"></i> Probar configuración
            </button>
            <div id="cfg-resultado-prueba" class="small"></div>
        </div>
    </div>
</div>

<script>
    window.VCFG_URL = '<?= $url ?>';
</script>
<script src="<?= $base ?>/js/config/videollamadas.js?v=<?= time() ?>"></script>
