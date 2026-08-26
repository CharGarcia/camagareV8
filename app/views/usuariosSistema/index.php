<?php

/** @var string $titulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var string $buscar */
/** @var int $nivel */
$base = BASE_URL;
$rows = $rows ?? [];
$total = $total ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage = $perPage ?? 20;
$ordenCol = $ordenCol ?? 'nombre';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to = $total > 0 ? min($page * $perPage, $total) : 0;
$msg = $msg ?? null;
$limiteUsuarios = $limiteUsuarios ?? null;
$urlRecuperar = rtrim(BASE_URL, '/') . '/auth/enviar-correo-recuperar';
$empresasParaCrear = $empresasParaCrear ?? [];

$urlBaseUsuarios = rtrim($base, '/') . '/config/usuarios-sistema';
$rowsHtml = $rowsHtml ?? '';
?>
<style>
    .usuarios-sistema-header {
        flex-shrink: 0;
    }

    .usuarios-sistema-scroll {
        max-height: calc(100dvh - 280px);
        overflow-y: auto;
    }

    .usuarios-sistema-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .usuario-row {
        cursor: pointer;
    }

    .usuario-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    .empresas-usuario-scroll {
        max-height: 280px;
        overflow-y: auto;
    }

    .empresas-usuario-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #fff;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .responsables-usuario-scroll {
        max-height: 280px;
        overflow-y: auto;
    }

    .responsables-usuario-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #fff;
        box-shadow: 0 1px 0 #dee2e6;
    }
</style>
<div class="usuarios-sistema-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-people"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">
            <?= $nivel >= 3 ? 'Todos los usuarios del sistema. Clic en fila para ver detalles.' : 'Usuarios que tiene asignados. Clic en fila para ver detalles.' ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if ($limiteUsuarios !== null): ?>
            <?php
            $limiteLleno  = $limiteUsuarios['actual'] >= $limiteUsuarios['max'];
            $disponibles  = max(0, $limiteUsuarios['max'] - $limiteUsuarios['actual']);
            ?>
            <span class="badge <?= $limiteLleno ? 'bg-danger' : 'bg-success bg-opacity-10 text-success border border-success' ?> px-3 py-2"
                  style="font-size:.8rem;" title="Usuarios de esta empresa">
                <i class="bi bi-people me-1"></i>
                <?= $limiteUsuarios['actual'] ?>/<?= $limiteUsuarios['max'] ?> usuarios &nbsp;·&nbsp;
                <?= $limiteLleno ? '<strong>Sin cupos disponibles</strong>' : "<strong>{$disponibles}</strong> disponible" . ($disponibles !== 1 ? 's' : '') ?>
            </span>
        <?php endif; ?>
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <?php if (!$limiteLleno || $nivel >= 3): ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
            <i class="bi bi-person-plus"></i> Crear usuario
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled title="Límite de usuarios alcanzado para esta empresa">
            <i class="bi bi-person-plus"></i> Crear usuario
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg[1]) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($limiteLleno ?? false): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 py-2 px-3" role="alert">
    <i class="bi bi-whatsapp text-success fs-4 flex-shrink-0"></i>
    <div class="small">
        <strong>Ha alcanzado el límite de usuarios para esta empresa.</strong>
        Para ampliar el número de usuarios, comuníquese con el administrador por WhatsApp:
        <a href="https://wa.me/593958924831" target="_blank" class="fw-bold text-success text-decoration-none ms-1">
            <i class="bi bi-whatsapp"></i> 0958924831
        </a>
    </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="input-buscar-usuarios" class="form-control" placeholder="Buscar por nombre, cédula, correo, nivel o estado..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
    </div>
    <div class="d-flex align-items-center gap-2" id="usrSisPagWrap">
        <span class="text-muted small" id="usrSisPagInfo"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
        <div id="usrSisPagBtns" class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="USRSIS_cambiarPagina(<?= $page - 1 ?>)" aria-label="Anterior"><i class="fas fa-angle-left"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="USRSIS_cambiarPagina(<?= $page + 1 ?>)" aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>
        </div>
    </div>
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="usuarios-sistema-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="cedula" role="button">Cédula <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="mail" role="button">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nivel" role="button">Nivel <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" data-sort="puede_app_movil" role="button">App Móvil <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center">Empresas</th>
                    </tr>
                </thead>
                <tbody id="tbodyUsuarios"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/crear-usuario" id="form-crear-usuario">
                <input type="hidden" name="redirect" value="usuarios-sistema">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearUsuarioLabel"><i class="bi bi-person-plus"></i> Crear usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Se enviará un correo al nuevo usuario para que complete su registro y defina su contraseña.</p>
                    <div class="mb-3">
                        <label for="crear-correo" class="form-label">Correo electrónico</label>
                        <input type="email" id="crear-correo" name="correo" class="form-control" required placeholder="correo@ejemplo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Empresa<?= count($empresasParaCrear) > 1 ? '(s)' : '' ?> a asignar</label>
                        <?php if (empty($empresasParaCrear)): ?>
                            <p class="text-danger small mb-0">No tiene empresas asignadas para asociar al nuevo usuario. Contacte al super administrador.</p>
                        <?php elseif (count($empresasParaCrear) === 1): ?>
                            <?php $unica = $empresasParaCrear[0]; ?>
                            <p class="mb-1">
                                <span class="badge bg-info bg-opacity-10 text-info border border-info">
                                    <?= htmlspecialchars($unica['nombre_comercial'] ?? '') ?>
                                </span>
                            </p>
                            <input type="hidden" name="empresas[]" value="<?= (int)($unica['id_empresa'] ?? 0) ?>">
                            <small class="text-muted">Se asignará automáticamente esta empresa al nuevo usuario.</small>
                        <?php else: ?>
                            <input type="text" id="crear-empresa-filtro" class="form-control form-control-sm mb-2" placeholder="Buscar empresa...">
                            <div class="border rounded p-2" id="crear-empresas-lista" style="max-height:180px; overflow-y:auto;">
                                <?php foreach ($empresasParaCrear as $e): ?>
                                    <?php
                                    $idEmp = (int)($e['id_empresa'] ?? 0);
                                    $nombreEmp = $e['nombre_comercial'] ?? '';
                                    ?>
                                    <div class="form-check crear-empresa-item" data-nombre="<?= htmlspecialchars(mb_strtolower($nombreEmp)) ?>">
                                        <!-- Sin premarcar ninguna: debe elegirse a propósito. Premarcar la
                                             empresa activa en la sesión de quien crea el usuario llevaba a
                                             asignarle esa empresa por descuido, sin relación con el usuario
                                             que realmente se está dando de alta. -->
                                        <input class="form-check-input" type="checkbox" name="empresas[]" value="<?= $idEmp ?>" id="crear-emp-<?= $idEmp ?>">
                                        <label class="form-check-label small" for="crear-emp-<?= $idEmp ?>">
                                            <?= htmlspecialchars($nombreEmp) ?>
                                            <span class="text-muted">(<?= htmlspecialchars($e['ruc'] ?? '') ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Seleccione al menos una empresa para el nuevo usuario.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" <?= empty($empresasParaCrear) ? 'disabled' : '' ?>><i class="bi bi-person-plus"></i> Crear usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle Usuario (con pestañas) -->
<div class="modal fade" id="modalDetalleUsuario" tabindex="-1" aria-labelledby="modalDetalleUsuarioLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleUsuarioLabel"><i class="bi bi-person"></i> <span id="modal-usuario-nombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pb-0">
                <div id="usuario-modal-msg"></div>
                <input type="hidden" id="modal-usuario-id" value="">
                <ul class="nav nav-tabs mb-3" id="tabsUsuario" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-general" data-bs-toggle="tab" data-bs-target="#pane-general" type="button" role="tab">General</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas" data-bs-toggle="tab" data-bs-target="#pane-empresas" type="button" role="tab">Empresas asignadas</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-responsables" data-bs-toggle="tab" data-bs-target="#pane-responsables" type="button" role="tab">Responsables de traslado</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-restablecer" data-bs-toggle="tab" data-bs-target="#pane-restablecer" type="button" role="tab">Contraseña</button>
                    </li>
                </ul>
                <div class="tab-content mb-3" id="tabsUsuarioContent">
                    <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                        <form method="POST" action="<?= $base ?>/config/usuariosSistemaUpdate" id="form-editar-usuario">
                            <input type="hidden" name="id" id="edit-usuario-id" value="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit-nombre" class="form-label">Nombre</label>
                                    <input type="text" id="edit-nombre" name="nombre" class="form-control form-control-sm" maxlength="100" autocomplete="off" placeholder="Nombre y apellido">
                                    <div class="form-text small">Solo es la etiqueta con la que se muestra; no sirve para iniciar sesión.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-cedula" class="form-label">Identificación</label>
                                    <input type="text" id="edit-cedula" name="cedula" class="form-control form-control-sm" maxlength="15" autocomplete="off" placeholder="Cédula, RUC o pasaporte">
                                    <!-- La identificación es la credencial de ingreso: cambiarla cambia
                                         con qué número entra el usuario al sistema. -->
                                    <div class="form-text small" id="ayuda-cedula">Con este número inicia sesión el usuario.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-mail" class="form-label">Correo</label>
                                    <input type="email" id="edit-mail" name="mail" class="form-control form-control-sm" placeholder="correo@ejemplo.com">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-nivel" class="form-label">Nivel</label>
                                    <select id="edit-nivel" name="nivel" class="form-select form-select-sm">
                                        <option value="1">Usuario</option>
                                        <?php if ($nivel >= 3): ?>
                                            <option value="2">Administrador</option>
                                            <option value="3">Super administrador</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-estado" class="form-label">Estado</label>
                                    <select id="edit-estado" name="estado" class="form-select form-select-sm">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <?php if ($nivel >= 3): ?>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="edit-puede-app-movil" name="puede_app_movil" value="1">
                                        <label class="form-check-label" for="edit-puede-app-movil">Puede usar la app móvil</label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <!-- Botón anterior removido -->
                            </div>
                            <div id="section-reenviar-invitacion" class="mt-4 p-3 bg-light border rounded d-none">
                                <h6 class="text-primary mb-2"><i class="bi bi-envelope-plus"></i> Invitación pendiente</h6>
                                <p class="text-muted small mb-3">Este usuario aún no ha completado su registro. Puede reenviarle el correo de bienvenida.</p>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-reenviar-invitacion">
                                    <i class="bi bi-send"></i> Reenviar correo de invitación
                                </button>
                                <div id="aviso-invitacion-sin-correo" class="form-text text-danger mt-2 d-none">
                                    El usuario no tiene un correo registrado: escriba uno en la ficha y guarde los cambios para poder invitarlo.
                                </div>
                                <div id="msg-resend-invitation" class="form-text mt-2"></div>
                            </div>
                            <?php if ($nivel >= 3): ?>
                            <!-- Intentos de acceso: tras varios fallos seguidos el sistema bloquea
                                 temporalmente el ingreso. Aquí el superadministrador ve el conteo
                                 y puede ponerlo a cero sin esperar a que expire el bloqueo. -->
                            <div id="section-intentos-acceso" class="mt-4 p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                    <div>
                                        <h6 class="mb-1"><i class="bi bi-shield-lock"></i> Intentos de acceso</h6>
                                        <div id="intentos-estado" class="small text-muted">Consultando...</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-warning" id="btn-reiniciar-intentos" disabled>
                                        <i class="bi bi-arrow-counterclockwise"></i> Reiniciar intentos
                                    </button>
                                </div>
                                <div id="msg-intentos" class="form-text mt-2"></div>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas" role="tabpanel">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label for="input-empresa-usuario" class="form-label small">Agregar empresa</label>
                                <div class="position-relative">
                                    <input type="text" id="input-empresa-usuario" class="form-control form-control-sm" placeholder="Buscar empresa por nombre o RUC..." autocomplete="off">
                                    <input type="hidden" id="select-empresa-usuario" value="">
                                    <div id="dropdown-empresa-usuario" class="list-group position-absolute shadow-sm" style="z-index:1060; max-height:220px; overflow:auto; display:none; width:100%;"></div>
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-agregar-empresa-usuario">
                                    <i class="bi bi-plus"></i> Asignar
                                </button>
                            </div>
                        </div>
                        <div class="empresas-usuario-scroll">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Empresa</th>
                                        <th>RUC</th>
                                        <th class="text-end">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-empresas-usuario"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pane-responsables" role="tabpanel">
                        <p class="text-muted small mb-2">
                            Solo aplica a usuarios que hacen entregas desde la app móvil sin "Acceso total" en el módulo Entregas:
                            verán únicamente las consignaciones de los responsables de traslado vinculados aquí.
                        </p>
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <label class="form-label small">Empresa</label>
                                <select id="select-empresa-responsable" class="form-select form-select-sm">
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">Agregar responsable</label>
                                <select id="select-responsable-traslado" class="form-select form-select-sm" disabled>
                                    <option value="">Seleccione una empresa primero...</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-primary w-100" id="btn-agregar-responsable" disabled>
                                    <i class="bi bi-plus"></i> Agregar
                                </button>
                            </div>
                        </div>
                        <div id="msg-responsables" class="form-text mb-2"></div>
                        <div class="responsables-usuario-scroll">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Responsable de traslado</th>
                                        <th>Identificación</th>
                                        <th class="text-end">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-responsables-usuario"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pane-restablecer" role="tabpanel">
                        <h6 class="mb-1"><i class="bi bi-envelope"></i> Restablecer por correo</h6>
                        <p class="text-muted small">Se enviará un correo al usuario con un enlace para restablecer su contraseña.</p>
                        <p class="small mb-2"><strong>Correo de envío:</strong> <span id="correo-restablecer-actual" class="text-primary"></span></p>
                        <p class="small text-warning-emphasis mb-2 d-none" id="correo-restablecer-aviso"><i class="bi bi-exclamation-circle"></i> Modificó el correo en la pestaña General y aún no lo guarda: el envío irá al correo guardado. Guarde el cambio si quiere usar el nuevo.</p>
                        <button type="button" class="btn btn-warning" id="btn-enviar-restablecer">
                            <i class="bi bi-envelope"></i> Enviar correo para restablecer contraseña
                        </button>
                        <div id="msg-restablecer" class="form-text mt-2"></div>

                        <hr class="my-3">

                        <!-- Salida cuando el correo no llega (buzón lleno, dominio que lo
                             rechaza, dirección mal escrita): el administrador fija la clave
                             y se la entrega al usuario por otro medio. -->
                        <h6 class="mb-1"><i class="bi bi-key"></i> Clave provisional</h6>
                        <p class="text-muted small mb-2">
                            Fije usted una contraseña y entréguesela al usuario por otro medio. Al asignarla,
                            el enlace de invitación anterior deja de servir y el usuario ya puede entrar con su
                            identificación; pídale que la cambie desde su perfil.
                        </p>
                        <div id="aviso-clave-provisional" class="alert alert-warning py-2 small d-none"></div>
                        <div class="row g-2">
                            <!-- Solo para el invitado que nunca se registró: su identificación
                                 actual es provisional, así que se fija junto con la clave. -->
                            <div class="col-md-5 d-none" id="campo-cedula-provisional">
                                <label for="input-cedula-provisional" class="form-label small">Identificación</label>
                                <input type="text" id="input-cedula-provisional" class="form-control form-control-sm" maxlength="15" autocomplete="off" placeholder="Cédula, RUC o pasaporte">
                                <div class="form-text small">Con este número iniciará sesión.</div>
                            </div>
                            <div class="col-md-7">
                                <label for="input-clave-provisional" class="form-label small">Nueva contraseña</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="input-clave-provisional" class="form-control" maxlength="72" autocomplete="off" placeholder="Mínimo 4 caracteres">
                                    <button type="button" class="btn btn-outline-secondary" id="btn-generar-clave" title="Generar una contraseña">
                                        <i class="bi bi-shuffle"></i>
                                    </button>
                                </div>
                                <div class="form-text small">Se muestra en pantalla a propósito, para poder copiarla y entregarla.</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm mt-2" id="btn-clave-provisional">
                            <i class="bi bi-key"></i> Asignar clave provisional
                        </button>
                        <div id="msg-clave-provisional" class="form-text mt-2"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-usuario">
                        <i class="bi bi-trash"></i> Eliminar usuario
                    </button>
                </div>
                <div class="d-flex gap-2">

                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" form="form-editar-usuario" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg"></i> Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var base = '<?= $base ?>';
        var urlRecuperar = '<?= $urlRecuperar ?>';
        var modal = document.getElementById('modalDetalleUsuario');
        var tbody = document.getElementById('tbody-empresas-usuario');
        var selectEmpresa = document.getElementById('select-empresa-usuario');
        var idUsuario = 0;
        var mailOriginal = '';
        var cedulaOriginal = '';
        // Un usuario "no registrado" nunca fijó su identificación: la que tiene es
        // provisional (el hash de su correo) y con ella no puede iniciar sesión,
        // así que la clave provisional no le serviría de nada todavía.
        var cedulaEsProvisional = false;

        // El enlace de recuperación se valida cruzando correo + token, así que solo
        // funciona con el correo GUARDADO. Si se cambió el correo y no se ha guardado,
        // se avisa en lugar de prometer un envío a una dirección que no serviría.
        function actualizarCorreoRestablecer() {
            var enFormulario = document.getElementById('edit-mail').value.trim();
            document.getElementById('correo-restablecer-actual').textContent = mailOriginal || '(sin correo)';
            var aviso = document.getElementById('correo-restablecer-aviso');
            if (enFormulario !== '' && mailOriginal !== '' && enFormulario !== mailOriginal) {
                aviso.classList.remove('d-none');
            } else {
                aviso.classList.add('d-none');
            }
        }

        // Muestra u oculta el bloque de "Invitación pendiente" del modal.
        // pendiente = el usuario todavía NO completó su registro.
        // tieneCorreo = hay una dirección a la cual mandar el correo.
        function aplicarEstadoRegistro(pendiente, tieneCorreo) {
            var sec = document.getElementById('section-reenviar-invitacion');
            var btn = document.getElementById('btn-reenviar-invitacion');
            var aviso = document.getElementById('aviso-invitacion-sin-correo');
            if (!sec) return;
            if (pendiente) {
                sec.classList.remove('d-none');
            } else {
                sec.classList.add('d-none');
            }
            if (btn) btn.disabled = !tieneCorreo;
            if (aviso) {
                if (pendiente && !tieneCorreo) {
                    aviso.classList.remove('d-none');
                } else {
                    aviso.classList.add('d-none');
                }
            }
        }

        // Cada apertura del modal pregunta al servidor si el usuario ya se activó.
        // Los data-* de la fila son la foto del momento en que se pintó la tabla:
        // si entre medio el usuario completó su registro, o se le guardó un cambio
        // de ficha, esa foto queda vieja y la invitación se perdía de vista.
        function verificarEstadoRegistro(id, fila) {
            if (!id) return;
            fetch(base + '/config/usuarios-sistema-estado-registro?id=' + encodeURIComponent(id), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    // El modal pudo cerrarse o cambiarse de usuario mientras respondía.
                    if (!res || !res.ok || parseInt(id, 10) !== idUsuario) return;
                    aplicarEstadoRegistro(!res.registrado, !!res.tiene_correo);
                    // La cédula provisional y su texto de ayuda dependen del mismo dato.
                    cedulaEsProvisional = !res.registrado;
                    document.getElementById('ayuda-cedula').textContent = !res.registrado
                        ? 'Provisional: el usuario definirá su identificación al completar el registro.'
                        : 'Con este número inicia sesión el usuario.';
                    actualizarAvisoClaveProvisional();
                    // Se deja al día el atributo de la fila para la próxima apertura.
                    if (fila) fila.dataset.registrado = res.registrado ? '1' : '0';
                })
                .catch(function() { /* sin red se conserva lo que trae la fila */ });
        }

        function abrirModalUsuario(el) {
            idUsuario = parseInt(el.dataset.id, 10);
            document.getElementById('modal-usuario-id').value = idUsuario;
            document.getElementById('edit-usuario-id').value = idUsuario;
            document.getElementById('modal-usuario-nombre').textContent = el.dataset.nombre || '';
            document.getElementById('edit-nombre').value = el.dataset.nombre || '';
            cedulaOriginal = el.dataset.cedula || '';
            document.getElementById('edit-cedula').value = cedulaOriginal;
            cedulaEsProvisional = el.dataset.registrado === '0';
            // En un usuario que aún no completa su registro la cédula es provisional
            // (se guardó su correo): él mismo la fijará al registrarse.
            document.getElementById('ayuda-cedula').textContent = el.dataset.registrado === '0'
                ? 'Provisional: el usuario definirá su identificación al completar el registro.'
                : 'Con este número inicia sesión el usuario.';
            mailOriginal = el.dataset.mail || '';
            document.getElementById('edit-mail').value = mailOriginal;
            document.getElementById('edit-nivel').value = el.dataset.nivel || '1';
            document.getElementById('edit-estado').value = el.dataset.estado === '1' ? '1' : '0';

            var chkAppMovil = document.getElementById('edit-puede-app-movil');
            if (chkAppMovil) {
                chkAppMovil.checked = el.dataset.puedeAppMovil === '1';
            }

            // Por estado de registro, no por token: enviar un correo de restablecimiento
            // le graba un token nuevo a un usuario YA registrado, y con eso el bloque
            // aparecía en usuarios que no admiten invitación (el backend la rechaza).
            // El atributo de la fila es solo la primera pintada (evita el parpadeo);
            // enseguida se confirma contra el servidor con el estado de AHORA.
            aplicarEstadoRegistro(el.dataset.registrado === '0', mailOriginal !== '' && mailOriginal !== '-');
            document.getElementById('msg-resend-invitation').textContent = '';
            verificarEstadoRegistro(idUsuario, el);

            // Limpiar mensajes previos
            var msgModal = document.getElementById('usuario-modal-msg');
            if (msgModal) msgModal.innerHTML = '';

            var mailVal = mailOriginal;
            if (mailVal && mailVal !== '-') {
                document.getElementById('btn-enviar-restablecer').disabled = false;
            } else {
                document.getElementById('btn-enviar-restablecer').disabled = true;
            }
            document.getElementById('msg-restablecer').textContent = '';
            document.getElementById('msg-restablecer').className = 'form-text mt-2';
            document.getElementById('input-clave-provisional').value = '';
            document.getElementById('input-cedula-provisional').value = '';
            document.getElementById('msg-clave-provisional').textContent = '';
            document.getElementById('msg-clave-provisional').className = 'form-text mt-2';
            actualizarCorreoRestablecer();
            actualizarAvisoClaveProvisional();
            cargarIntentosAcceso();
            bootstrap.Tab.getInstance(document.getElementById('tab-general')) || new bootstrap.Tab(document.getElementById('tab-general'));
            document.getElementById('tab-general').click();
            cargarEmpresas();
            limpiarBuscadorEmpresaUsuario();
            cargarEmpresasParaResponsables();
            new bootstrap.Modal(modal).show();
        }

        // ── Intentos de acceso (solo superadministrador) ──────────────────────
        // El bloqueo por fuerza bruta vive en login_intentos, no en el usuario:
        // se consulta por su cédula cada vez que se abre la ficha.
        function fechaLegible(valor) {
            if (!valor) return '';
            var m = String(valor).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
            if (!m) return valor;
            return m[3] + '-' + m[2] + '-' + m[1] + ' ' + m[4] + ':' + m[5] + ':' + m[6];
        }

        function pintarEstadoIntentos(estado) {
            var cont = document.getElementById('intentos-estado');
            var btn = document.getElementById('btn-reiniciar-intentos');
            if (!cont || !btn) return;

            if (!estado || !estado.disponible) {
                cont.innerHTML = '<span class="text-muted">No se pudo consultar el registro de intentos.</span>';
                btn.disabled = true;
                return;
            }

            var html = '';
            if (estado.bloqueado) {
                html += '<span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Bloqueado</span> ';
                html += '<span class="text-danger">Se desbloquea solo en ' + estado.minutos + ' minuto' + (estado.minutos === 1 ? '' : 's') + '.</span><br>';
            } else if (estado.fallos > 0) {
                html += '<span class="badge bg-warning text-dark">' + estado.fallos + ' de ' + estado.max + ' intentos fallidos</span><br>';
            } else {
                html += '<span class="badge bg-success bg-opacity-10 text-success border border-success">Sin intentos fallidos</span><br>';
            }
            if (!estado.activo) {
                html += '<span class="text-muted">El bloqueo por intentos está desactivado en la configuración.</span><br>';
            }
            if (estado.ultimo_fallo) {
                html += 'Último fallo: ' + fechaLegible(estado.ultimo_fallo) + '. ';
            }
            if (estado.ultimo_exito) {
                html += 'Último acceso correcto: ' + fechaLegible(estado.ultimo_exito) + '.';
            }
            cont.innerHTML = html;
            btn.disabled = (estado.fallos === 0);
        }

        function cargarIntentosAcceso() {
            var cont = document.getElementById('intentos-estado');
            if (!cont) return;   // el bloque solo se renderiza para nivel 3
            cont.textContent = 'Consultando...';
            document.getElementById('msg-intentos').textContent = '';
            document.getElementById('btn-reiniciar-intentos').disabled = true;

            var fd = new FormData();
            fd.append('id', idUsuario);
            fetch(base + '/config/usuariosSistemaIntentosEstado', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    pintarEstadoIntentos(res.estado);
                } else {
                    cont.innerHTML = '<span class="text-danger">' + (res.msg || 'No se pudo consultar.') + '</span>';
                }
            })
            .catch(function() {
                cont.innerHTML = '<span class="text-danger">Error de conexión al consultar los intentos.</span>';
            });
        }

        var btnReiniciarIntentos = document.getElementById('btn-reiniciar-intentos');
        if (btnReiniciarIntentos) {
            btnReiniciarIntentos.addEventListener('click', function() {
                var btn = this;
                var msg = document.getElementById('msg-intentos');
                btn.disabled = true;
                msg.className = 'form-text mt-2';
                msg.textContent = 'Reiniciando...';

                var fd = new FormData();
                fd.append('id', idUsuario);
                fetch(base + '/config/usuariosSistemaReiniciarIntentos', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    msg.className = 'form-text mt-2 ' + (res.ok ? 'text-success' : 'text-danger');
                    msg.textContent = res.msg || (res.ok ? 'Intentos reiniciados.' : 'No se pudo reiniciar.');
                    if (res.ok) {
                        pintarEstadoIntentos(res.estado);
                    } else {
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    msg.className = 'form-text mt-2 text-danger';
                    msg.textContent = 'Error de conexión al reiniciar los intentos.';
                    btn.disabled = false;
                });
            });
        }

        // Filtro de empresas en el modal de crear usuario (cuando hay varias)
        var filtroEmpresa = document.getElementById('crear-empresa-filtro');
        if (filtroEmpresa) {
            filtroEmpresa.addEventListener('input', function() {
                var q = this.value.trim().toLowerCase();
                document.querySelectorAll('#crear-empresas-lista .crear-empresa-item').forEach(function(item) {
                    item.style.display = item.dataset.nombre.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        }

        // Crear usuario (AJAX + SweetAlert)
        var formCrear = document.getElementById('form-crear-usuario');
        if (formCrear) {
            formCrear.addEventListener('submit', function(e) {
                e.preventDefault();

                var checks = formCrear.querySelectorAll('input[name="empresas[]"]');
                if (checks.length > 1) {
                    var algunaMarcada = Array.prototype.some.call(checks, function(c) { return c.checked; });
                    if (!algunaMarcada) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Atención', 'Seleccione al menos una empresa para el nuevo usuario.', 'warning');
                        } else {
                            alert('Seleccione al menos una empresa para el nuevo usuario.');
                        }
                        return;
                    }
                }

                var btnSubmit = formCrear.querySelector('button[type="submit"]');
                if (btnSubmit) btnSubmit.disabled = true;

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Creando usuario...',
                        text: 'Enviando correo de invitación al nuevo usuario.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: function() { Swal.showLoading(); }
                    });
                }

                var formData = new FormData(formCrear);
                fetch(formCrear.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.ok) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Usuario creado', text: res.msg }).then(function() {
                                location.reload();
                            });
                        } else {
                            alert(res.msg);
                            location.reload();
                        }
                    } else {
                        if (btnSubmit) btnSubmit.disabled = false;
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('No se pudo crear', res.msg || 'Error desconocido.', 'error');
                        } else {
                            alert(res.msg || 'Error al crear el usuario.');
                        }
                    }
                })
                .catch(function() {
                    if (btnSubmit) btnSubmit.disabled = false;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', 'Error de conexión. Intente de nuevo.', 'error');
                    } else {
                        alert('Error de conexión. Intente de nuevo.');
                    }
                });
            });
        }

        // Interceptar formulario de edición (AJAX)
        var formEditar = document.getElementById('form-editar-usuario');
        if (formEditar) {
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                var msgModal = document.getElementById('usuario-modal-msg');
                msgModal.innerHTML = '<div class="alert alert-info py-2 small">Guardando...</div>';
                
                var formData = new FormData(this);
                fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        msgModal.innerHTML = '<div class="alert alert-success py-2 small">' + res.msg + '</div>';
                        setTimeout(() => { location.reload(); }, 1200);
                    } else {
                        msgModal.innerHTML = '<div class="alert alert-danger py-2 small">' + res.msg + '</div>';
                    }
                })
                .catch(() => {
                    msgModal.innerHTML = '<div class="alert alert-danger py-2 small">Error de conexión al servidor.</div>';
                });
            });
        }

        // Delegación de eventos: las filas se reemplazan en cada búsqueda/orden/página
        // AJAX, por lo que el listener va en el tbody (contenedor fijo), no en cada <tr>.
        var tbodyUsuarios = document.getElementById('tbodyUsuarios');
        if (tbodyUsuarios) {
            tbodyUsuarios.addEventListener('click', function(e) {
                if (e.target.closest('a, button')) return;
                var row = e.target.closest('.usuario-row');
                if (row) abrirModalUsuario(row);
            });
            tbodyUsuarios.addEventListener('keydown', function(e) {
                if ((e.key !== 'Enter' && e.key !== ' ') || e.target.closest('a, button')) return;
                var row = e.target.closest('.usuario-row');
                if (row) {
                    e.preventDefault();
                    abrirModalUsuario(row);
                }
            });
        }

        function cargarEmpresas() {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center">Cargando...</td></tr>';
            fetch(base + '/config/asignar-empresas?action=empresasUsuario&id=' + idUsuario)
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.html) {
                        tbody.innerHTML = data.html;
                        tbody.querySelectorAll('.btn-quitar-empresa').forEach(function(b) {
                            b.addEventListener('click', function() {
                                if (confirm('¿Quitar esta empresa?')) {
                                    var id = this.dataset.id;
                                    var f = document.createElement('form');
                                    f.method = 'POST';
                                    f.action = base + '/config/asignar-empresas';
                                    var i = document.createElement('input');
                                    i.type = 'hidden';
                                    i.name = 'action';
                                    i.value = 'quitar';
                                    var i2 = document.createElement('input');
                                    i2.type = 'hidden';
                                    i2.name = 'id';
                                    i2.value = id;
                                    var i3 = document.createElement('input');
                                    i3.type = 'hidden';
                                    i3.name = 'redirect';
                                    i3.value = 'usuarios-sistema';
                                    f.appendChild(i);
                                    f.appendChild(i2);
                                    f.appendChild(i3);
                                    document.body.appendChild(f);
                                    f.submit();
                                }
                            });
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Sin empresas asignadas</td></tr>';
                    }
                })
                .catch(function() {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-danger">Error al cargar</td></tr>';
                });
        }

        // Buscador tipo "chip" de empresas disponibles: mismo patrón que
        // setupTypeahead() en app/views/modulos/mayores/index.php.
        var inputEmpresaUsuario = document.getElementById('input-empresa-usuario');
        var dropdownEmpresaUsuario = document.getElementById('dropdown-empresa-usuario');

        function setupTypeaheadEmpresa(inputEl, dropdownEl, hiddenEl, fetchFn, renderLabel) {
            var debounceTimer;
            inputEl.addEventListener('keydown', function(e) {
                if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                    e.preventDefault();
                    hiddenEl.value = '';
                    inputEl.value = '';
                    dropdownEl.style.display = 'none';
                    dropdownEl.innerHTML = '';
                }
            });
            inputEl.addEventListener('input', function() {
                hiddenEl.value = '';
                clearTimeout(debounceTimer);
                var q = inputEl.value.trim();
                if (q.length < 1) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
                debounceTimer = setTimeout(function() {
                    fetchFn(q).then(function(items) {
                        if (!items || !items.length) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
                        dropdownEl.innerHTML = items.map(function(it) {
                            var label = renderLabel(it);
                            return '<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="' + it.id_empresa + '" data-label="' + label.replace(/"/g, '&quot;') + '">' + label + '</a>';
                        }).join('');
                        dropdownEl.style.display = 'block';
                    }).catch(function() {
                        dropdownEl.innerHTML = '<span class="list-group-item text-danger small">Error al buscar.</span>';
                        dropdownEl.style.display = 'block';
                    });
                }, 300);
            });
            dropdownEl.addEventListener('click', function(e) {
                var a = e.target.closest('a[data-id]');
                if (!a) return;
                e.preventDefault();
                hiddenEl.value = a.dataset.id;
                inputEl.value = a.dataset.label;
                dropdownEl.style.display = 'none';
            });
            document.addEventListener('click', function(e) {
                if (e.target !== inputEl && !dropdownEl.contains(e.target)) dropdownEl.style.display = 'none';
            });
        }

        setupTypeaheadEmpresa(
            inputEmpresaUsuario,
            dropdownEmpresaUsuario,
            selectEmpresa,
            function(q) {
                return fetch(base + '/config/asignar-empresas?action=empresasDisponibles&id_usuario=' + idUsuario + '&q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(data) { return data.empresas || []; });
            },
            function(e) { return (e.nombre_comercial || '') + ' (' + (e.ruc || '') + ')'; }
        );

        function limpiarBuscadorEmpresaUsuario() {
            inputEmpresaUsuario.value = '';
            selectEmpresa.value = '';
            dropdownEmpresaUsuario.style.display = 'none';
            dropdownEmpresaUsuario.innerHTML = '';
        }

        document.getElementById('btn-agregar-empresa-usuario').addEventListener('click', function() {
            var idEmp = selectEmpresa.value;
            if (!idEmp) {
                alert('Busque y seleccione una empresa de la lista.');
                return;
            }
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = base + '/config/asignar-empresas';
            var i = document.createElement('input');
            i.type = 'hidden';
            i.name = 'action';
            i.value = 'asignar';
            var i2 = document.createElement('input');
            i2.type = 'hidden';
            i2.name = 'id_empresa';
            i2.value = idEmp;
            var i3 = document.createElement('input');
            i3.type = 'hidden';
            i3.name = 'id_usuario';
            i3.value = idUsuario;
            var i4 = document.createElement('input');
            i4.type = 'hidden';
            i4.name = 'redirect';
            i4.value = 'usuarios-sistema';
            f.appendChild(i);
            f.appendChild(i2);
            f.appendChild(i3);
            f.appendChild(i4);
            document.body.appendChild(f);
            f.submit();
        });

        // --- Responsables de traslado vinculados (para el módulo Entregas de la app móvil) ---
        var selectEmpresaResp = document.getElementById('select-empresa-responsable');
        var selectResponsable = document.getElementById('select-responsable-traslado');
        var btnAgregarResp = document.getElementById('btn-agregar-responsable');
        var tbodyResp = document.getElementById('tbody-responsables-usuario');
        var msgResp = document.getElementById('msg-responsables');

        function cargarEmpresasParaResponsables() {
            selectEmpresaResp.innerHTML = '<option value="">Cargando...</option>';
            selectResponsable.innerHTML = '<option value="">Seleccione una empresa primero...</option>';
            selectResponsable.disabled = true;
            btnAgregarResp.disabled = true;
            tbodyResp.innerHTML = '';
            msgResp.textContent = '';
            fetch(base + '/config/usuario-responsables-traslado?action=empresasUsuario&id=' + idUsuario)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var empresas = data.empresas || [];
                    if (empresas.length === 0) {
                        selectEmpresaResp.innerHTML = '<option value="">Sin empresas asignadas</option>';
                        return;
                    }
                    selectEmpresaResp.innerHTML = '<option value="">Seleccione empresa...</option>';
                    empresas.forEach(function(e) {
                        var o = document.createElement('option');
                        o.value = e.id_empresa;
                        o.textContent = e.nombre_comercial || '';
                        selectEmpresaResp.appendChild(o);
                    });
                    if (empresas.length === 1) {
                        selectEmpresaResp.value = empresas[0].id_empresa;
                        onEmpresaResponsableChange();
                    }
                })
                .catch(function() {
                    selectEmpresaResp.innerHTML = '<option value="">Error</option>';
                });
        }

        function onEmpresaResponsableChange() {
            var idEmp = selectEmpresaResp.value;
            if (!idEmp) {
                selectResponsable.innerHTML = '<option value="">Seleccione una empresa primero...</option>';
                selectResponsable.disabled = true;
                btnAgregarResp.disabled = true;
                tbodyResp.innerHTML = '';
                return;
            }
            selectResponsable.disabled = false;
            btnAgregarResp.disabled = false;
            cargarResponsablesVinculados(idEmp);
            cargarResponsablesDisponibles(idEmp);
        }

        function cargarResponsablesVinculados(idEmp) {
            tbodyResp.innerHTML = '<tr><td colspan="3" class="text-center">Cargando...</td></tr>';
            fetch(base + '/config/usuario-responsables-traslado?action=listar&id_usuario=' + idUsuario + '&id_empresa=' + idEmp)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var rows = data.rows || [];
                    if (rows.length === 0) {
                        tbodyResp.innerHTML = '<tr><td colspan="3" class="text-muted">Sin responsables vinculados</td></tr>';
                        return;
                    }
                    tbodyResp.innerHTML = '';
                    rows.forEach(function(r) {
                        var tr = document.createElement('tr');
                        var tdNombre = document.createElement('td');
                        tdNombre.textContent = r.nombre || '';
                        var tdIdent = document.createElement('td');
                        tdIdent.textContent = r.identificacion || '';
                        var tdQuitar = document.createElement('td');
                        tdQuitar.className = 'text-end';
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-sm btn-outline-danger btn-quitar-responsable';
                        btn.title = 'Quitar';
                        btn.dataset.id = r.id;
                        btn.innerHTML = '<i class="bi bi-trash"></i>';
                        tdQuitar.appendChild(btn);
                        tr.appendChild(tdNombre);
                        tr.appendChild(tdIdent);
                        tr.appendChild(tdQuitar);
                        tbodyResp.appendChild(tr);
                    });
                    tbodyResp.querySelectorAll('.btn-quitar-responsable').forEach(function(b) {
                        b.addEventListener('click', function() {
                            if (!confirm('¿Quitar este responsable de traslado?')) return;
                            var idReg = this.dataset.id;
                            var idEmpActual = selectEmpresaResp.value;
                            msgResp.textContent = 'Quitando...';
                            msgResp.className = 'form-text mb-2';
                            var fd = new FormData();
                            fd.append('id', idReg);
                            fd.append('id_empresa', idEmpActual);
                            fetch(base + '/config/usuario-responsables-traslado?action=desvincular', {
                                    method: 'POST',
                                    body: fd,
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                                })
                                .then(function(r) { return r.json(); })
                                .then(function(res) {
                                    msgResp.textContent = res.msg || '';
                                    msgResp.className = 'form-text mb-2 ' + (res.ok ? 'text-success' : 'text-danger');
                                    if (res.ok) {
                                        cargarResponsablesVinculados(idEmpActual);
                                        cargarResponsablesDisponibles(idEmpActual);
                                    }
                                })
                                .catch(function() {
                                    msgResp.textContent = 'Error de conexión.';
                                    msgResp.className = 'form-text mb-2 text-danger';
                                });
                        });
                    });
                })
                .catch(function() {
                    tbodyResp.innerHTML = '<tr><td colspan="3" class="text-danger">Error al cargar</td></tr>';
                });
        }

        function cargarResponsablesDisponibles(idEmp) {
            selectResponsable.innerHTML = '<option value="">Cargando...</option>';
            fetch(base + '/config/usuario-responsables-traslado?action=disponibles&id_usuario=' + idUsuario + '&id_empresa=' + idEmp)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var responsables = data.responsables || [];
                    selectResponsable.innerHTML = '<option value="">Seleccione responsable...</option>';
                    responsables.forEach(function(r) {
                        var o = document.createElement('option');
                        o.value = r.id;
                        o.textContent = r.nombre + (r.identificacion ? ' (' + r.identificacion + ')' : '');
                        selectResponsable.appendChild(o);
                    });
                })
                .catch(function() {
                    selectResponsable.innerHTML = '<option value="">Error</option>';
                });
        }

        selectEmpresaResp.addEventListener('change', onEmpresaResponsableChange);

        btnAgregarResp.addEventListener('click', function() {
            var idEmp = selectEmpresaResp.value;
            var idResp = selectResponsable.value;
            if (!idEmp) { alert('Seleccione una empresa'); return; }
            if (!idResp) { alert('Seleccione un responsable'); return; }
            msgResp.textContent = 'Agregando...';
            msgResp.className = 'form-text mb-2';
            var fd = new FormData();
            fd.append('id_usuario', idUsuario);
            fd.append('id_empresa', idEmp);
            fd.append('id_responsable_traslado', idResp);
            fetch(base + '/config/usuario-responsables-traslado?action=vincular', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    msgResp.textContent = res.msg || '';
                    msgResp.className = 'form-text mb-2 ' + (res.ok ? 'text-success' : 'text-danger');
                    if (res.ok) {
                        cargarResponsablesVinculados(idEmp);
                        cargarResponsablesDisponibles(idEmp);
                    }
                })
                .catch(function() {
                    msgResp.textContent = 'Error de conexión.';
                    msgResp.className = 'form-text mb-2 text-danger';
                });
        });

        document.getElementById('edit-mail').addEventListener('input', actualizarCorreoRestablecer);
        document.getElementById('tab-restablecer').addEventListener('shown.bs.tab', actualizarCorreoRestablecer);

        document.getElementById('btn-enviar-restablecer').addEventListener('click', function() {
            var btn = this;
            var msgDiv = document.getElementById('msg-restablecer');
            if (!mailOriginal) {
                msgDiv.textContent = 'El usuario no tiene correo registrado.';
                msgDiv.className = 'form-text mt-2 text-danger';
                return;
            }
            btn.disabled = true;
            msgDiv.textContent = 'Enviando...';
            msgDiv.className = 'form-text mt-2';
            // Solo el id: el servidor toma el correo guardado del usuario.
            var formData = new FormData();
            formData.append('id_user', idUsuario);
            fetch(urlRecuperar, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json().catch(function() {
                        return {};
                    });
                })
                .then(function(res) {
                    if (res && res.ok) {
                        msgDiv.textContent = 'Se ha enviado el correo para restablecer la contraseña.';
                        msgDiv.className = 'form-text mt-2 text-success';
                    } else {
                        msgDiv.textContent = (res && res.error) || 'Error al enviar. Verifique Config → Correos.';
                        msgDiv.className = 'form-text mt-2 text-danger';
                    }
                })
                .catch(function() {
                    msgDiv.textContent = 'Error al enviar. Intente de nuevo.';
                    msgDiv.className = 'form-text mt-2 text-danger';
                })
                .finally(function() {
                    btn.disabled = false;
                });
        });

        document.getElementById('btn-eliminar-usuario').addEventListener('click', function() {
            if (!idUsuario) return;
            var nombre = document.getElementById('edit-nombre').value;
            var msg = '¿Está seguro de eliminar al usuario "' + nombre + '"?';

            if (confirm(msg)) {
                var msgModal = document.getElementById('usuario-modal-msg');
                msgModal.innerHTML = '<div class="alert alert-info py-2 small">Eliminando...</div>';
                
                var formData = new FormData();
                formData.append('id', idUsuario);
                
                fetch(base + '/config/usuariosSistemaEliminar', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => {
                    if (!r.ok) throw new Error('Error en la respuesta del servidor');
                    return r.json().catch(err => {
                        console.error('JSON parse error:', err);
                        throw new Error('La respuesta del servidor no es válida (JSON error)');
                    });
                })
                .then(res => {
                    if (res.ok) {
                        msgModal.innerHTML = '<div class="alert alert-success py-2 small">' + res.msg + '</div>';
                        setTimeout(() => { location.reload(); }, 1200);
                    } else {
                        msgModal.innerHTML = '<div class="alert alert-danger py-2 small">' + res.msg + '</div>';
                    }
                })
                .catch(err => {
                    msgModal.innerHTML = '<div class="alert alert-danger py-2 small">' + (err.message || 'Error de conexión') + '</div>';
                });
            }
        });

        // ── Clave provisional ────────────────────────────────────────────────
        // La contraseña sirve de poco sin la identificación con la que se entra:
        // si el usuario aún carga la provisional, o si se cambió la del formulario
        // sin guardarla, se avisa antes de dejar asignar la clave.
        function actualizarAvisoClaveProvisional() {
            var aviso = document.getElementById('aviso-clave-provisional');
            var btn = document.getElementById('btn-clave-provisional');
            if (!aviso || !btn) return;

            var cedulaEnFormulario = document.getElementById('edit-cedula').value.trim();
            var campoCedula = document.getElementById('campo-cedula-provisional');

            if (cedulaEsProvisional) {
                // Nunca completó su registro: la clave se guarda junto con la
                // identificación, en un solo paso, para no dejarlo a medias.
                campoCedula.classList.remove('d-none');
                aviso.innerHTML = '<i class="bi bi-info-circle"></i> Este usuario nunca completó su registro, así que todavía no tiene identificación propia. Indique aquí con qué número iniciará sesión: se guardará junto con la contraseña.';
                aviso.classList.remove('d-none');
                btn.disabled = false;
                return;
            }

            campoCedula.classList.add('d-none');

            if (cedulaEnFormulario !== cedulaOriginal) {
                aviso.innerHTML = '<i class="bi bi-exclamation-circle"></i> Modificó la identificación en <strong>General</strong> y aún no la guarda. Guárdela primero: es el número con el que el usuario iniciará sesión.';
                aviso.classList.remove('d-none');
                btn.disabled = true;
                return;
            }

            aviso.classList.add('d-none');
            aviso.innerHTML = '';
            btn.disabled = false;
        }

        document.getElementById('edit-cedula').addEventListener('input', actualizarAvisoClaveProvisional);
        document.getElementById('tab-restablecer').addEventListener('shown.bs.tab', actualizarAvisoClaveProvisional);

        document.getElementById('btn-generar-clave').addEventListener('click', function() {
            // Sin caracteres ambiguos (O/0, l/1): la clave se dicta o se copia a mano.
            var abc = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
            var clave = '';
            if (window.crypto && window.crypto.getRandomValues) {
                var buf = new Uint32Array(10);
                window.crypto.getRandomValues(buf);
                for (var i = 0; i < buf.length; i++) clave += abc.charAt(buf[i] % abc.length);
            } else {
                for (var j = 0; j < 10; j++) clave += abc.charAt(Math.floor(Math.random() * abc.length));
            }
            document.getElementById('input-clave-provisional').value = clave;
        });

        document.getElementById('btn-clave-provisional').addEventListener('click', function() {
            var btn = this;
            var input = document.getElementById('input-clave-provisional');
            var msgDiv = document.getElementById('msg-clave-provisional');
            var clave = input.value.trim();

            var cedulaNueva = cedulaEsProvisional
                ? document.getElementById('input-cedula-provisional').value.trim()
                : '';

            if (!idUsuario) return;
            if (clave.length < 4) {
                msgDiv.textContent = 'La contraseña provisional debe tener al menos 4 caracteres.';
                msgDiv.className = 'form-text mt-2 text-danger';
                return;
            }
            if (cedulaEsProvisional && cedulaNueva === '') {
                msgDiv.textContent = 'Indique la identificación con la que el usuario iniciará sesión.';
                msgDiv.className = 'form-text mt-2 text-danger';
                return;
            }
            if (!confirm('Se reemplazará la contraseña actual del usuario y el enlace de invitación anterior dejará de servir. ¿Continuar?')) {
                return;
            }

            btn.disabled = true;
            msgDiv.textContent = 'Guardando...';
            msgDiv.className = 'form-text mt-2';

            var formData = new FormData();
            formData.append('id', idUsuario);
            formData.append('clave', clave);
            if (cedulaEsProvisional) {
                formData.append('cedula', cedulaNueva);
            }

            fetch(base + '/config/usuarios-sistema-clave-provisional', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    msgDiv.textContent = res.msg;
                    msgDiv.className = 'form-text mt-2 text-success';
                    // El listado cambia (deja de figurar como pendiente de registro),
                    // así que se refresca para que la ficha no quede desfasada.
                    setTimeout(function() { location.reload(); }, 4000);
                } else {
                    msgDiv.textContent = res.msg || 'No se pudo asignar la contraseña.';
                    msgDiv.className = 'form-text mt-2 text-danger';
                    btn.disabled = false;
                }
            })
            .catch(function() {
                msgDiv.textContent = 'Error de conexión.';
                msgDiv.className = 'form-text mt-2 text-danger';
                btn.disabled = false;
            });
        });

        // Reenviar invitación (NUEVO)
        var btnReenviar = document.getElementById('btn-reenviar-invitacion');
        if (btnReenviar) {
            btnReenviar.addEventListener('click', function() {
                var btn = this;
                var msgDiv = document.getElementById('msg-resend-invitation');
                if (!idUsuario) return;
                
                btn.disabled = true;
                msgDiv.textContent = 'Enviando...';
                msgDiv.className = 'form-text mt-2';
                
                var formData = new FormData();
                formData.append('id', idUsuario);
                
                fetch(base + '/config/usuarios-sistema-reenviar-invitacion', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        msgDiv.textContent = res.msg;
                        msgDiv.className = 'form-text mt-2 text-success';
                    } else {
                        msgDiv.textContent = res.msg || 'Error al enviar.';
                        msgDiv.className = 'form-text mt-2 text-danger';
                    }
                })
                .catch(() => {
                    msgDiv.textContent = 'Error de conexión.';
                    msgDiv.className = 'form-text mt-2 text-danger';
                })
                .finally(() => {
                    btn.disabled = false;
                });
            });
        }
    })();

    // Búsqueda, orden y paginación en tiempo real: reemplazan solo la tabla vía
    // AJAX, sin recargar la página (el input nunca pierde el foco). Mismo patrón
    // que ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    (function() {
        var base = '<?= $base ?>';
        var timer = null;
        window.USRSIS_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
        window.USRSIS_currentDir = '<?= htmlspecialchars($ordenDir) ?>';
        window.USRSIS_currentPage = <?= (int) $page ?>;

        window.USRSIS_cargarListado = function(page) {
            page = page || 1;
            window.USRSIS_currentPage = page;
            var inputB = document.getElementById('input-buscar-usuarios');
            var b = inputB ? inputB.value.trim() : '';
            var tbodyEl = document.getElementById('tbodyUsuarios');
            if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

            fetch(base + '/config/usuarios-sistema-search?b=' + encodeURIComponent(b) + '&page=' + page + '&sort=' + window.USRSIS_currentSort + '&dir=' + window.USRSIS_currentDir, {
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.ok) return;
                    if (tbodyEl) tbodyEl.innerHTML = data.rows;
                    var info = document.getElementById('usrSisPagInfo');
                    if (info) info.textContent = data.info;
                    var btns = document.getElementById('usrSisPagBtns');
                    if (btns) btns.innerHTML = data.pagination || (
                        '<button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="Anterior"><i class="fas fa-angle-left"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>'
                    );
                })
                .catch(function() {
                    if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar.</td></tr>';
                });
        };

        window.USRSIS_cambiarPagina = function(page) {
            if (page < 1) return;
            USRSIS_cargarListado(page);
        };

        var inputBuscar = document.getElementById('input-buscar-usuarios');
        if (inputBuscar) {
            inputBuscar.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    USRSIS_cargarListado(1);
                }, 400);
            });
        }

        if (window.CMG_initSort) {
            window.CMG_initSort('usuarios-sistema', function(col, dir) {
                window.USRSIS_currentSort = col;
                window.USRSIS_currentDir = dir;
                USRSIS_cargarListado(1);
            }, { col: window.USRSIS_currentSort, dir: window.USRSIS_currentDir });
        }
    })();

    // Reenviar invitación directo desde la fila del listado (usuarios no registrados).
    window.reenviarInvitacionUsuario = function (id, btn) {
        if (!id) return;
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.8rem;height:.8rem;"></span>';
        var fd = new FormData();
        fd.append('id', id);
        fetch('<?= $base ?>/config/usuarios-sistema-reenviar-invitacion', {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { alert(res.msg || (res.ok ? 'Invitación reenviada.' : 'No se pudo reenviar.')); })
        .catch(function () { alert('Error de conexión.'); })
        .finally(function () { btn.disabled = false; btn.innerHTML = original; });
    };
</script>