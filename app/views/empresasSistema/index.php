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
$msg = $_SESSION['empresas_msg'] ?? null;
unset($_SESSION['empresas_msg']);
$urlBaseEmpresas = rtrim($base, '/') . '/config/empresas-sistema';

function thSortEmpresas($urlBase, $col, $label, $ordenCol, $ordenDir, $buscar, $page, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $cls = trim('btn btn-link p-0 text-decoration-none ' . $align);
    $html = '<form method="POST" action="' . htmlspecialchars($urlBase) . '" class="d-inline">';
    $html .= '<input type="hidden" name="sort" value="' . htmlspecialchars($col) . '">';
    $html .= '<input type="hidden" name="dir" value="' . $dir . '">';
    $html .= '<input type="hidden" name="page" value="' . (int)$page . '">';
    $html .= '<input type="hidden" name="b" value="' . htmlspecialchars($buscar) . '">';
    $html .= '<button type="submit" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</button>';
    $html .= '</form>';
    return $html;
}

function estadoPagoBadge($estado) {
    $estado = $estado ?? 'pendiente';
    $cls = match ($estado) {
        'pagado' => 'success',
        'vencido' => 'danger',
        default => 'warning',
    };
    $txt = match ($estado) {
        'pagado' => 'Pagado',
        'vencido' => 'Vencido',
        default => 'Pendiente',
    };
    return '<span class="badge bg-' . $cls . '">' . htmlspecialchars($txt) . '</span>';
}
?>
<style>
.empresas-sistema-header { flex-shrink: 0; }
.empresas-sistema-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
.empresas-sistema-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.empresa-row { cursor: pointer; }
.empresa-row:hover { background-color: rgba(0,0,0,.04); }
.usuarios-empresa-scroll { max-height: 280px; overflow-y: auto; }
.usuarios-empresa-scroll thead th { position: sticky; top: 0; z-index: 1; background: #fff; box-shadow: 0 1px 0 #dee2e6; }
.documentos-empresa-scroll { max-height: 280px; overflow-y: auto; }
.documentos-empresa-scroll thead th { position: sticky; top: 0; z-index: 1; background: #fff; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="empresas-sistema-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-building"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">
            <?= $nivel >= 3 ? 'Todas las empresas. Clic en fila para ver ficha.' : 'Empresas que tiene asignadas. Clic en fila para ver ficha.' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <?php if ($nivel >= 3): ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearEmpresa"><i class="bi bi-plus-lg"></i> Crear empresa</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
    <form id="form-buscar-empresas" method="POST" action="<?= $urlBaseEmpresas ?>" class="d-flex align-items-center gap-2">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
        <div class="input-group input-group-sm" style="max-width: 320px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="input-buscar-empresas" name="b" class="form-control" placeholder="Buscar por razón social, RUC, establecimiento..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
            <?php if ($buscar !== '' || $page > 1): ?>
            <a href="<?= $urlBaseEmpresas ?>" class="btn btn-outline-secondary" title="Limpiar búsqueda"><i class="bi bi-x"></i></a>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($total > 0): ?>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
        <?php if ($page <= 1): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="Anterior"><i class="fas fa-angle-left"></i></button>
        <?php else: ?>
        <form method="POST" action="<?= $urlBaseEmpresas ?>" class="d-inline">
            <input type="hidden" name="page" value="<?= $page - 1 ?>">
            <input type="hidden" name="b" value="<?= htmlspecialchars($buscar) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Anterior"><i class="fas fa-angle-left"></i></button>
        </form>
        <?php endif; ?>
        <?php if ($page >= $totalPages): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>
        <?php else: ?>
        <form method="POST" action="<?= $urlBaseEmpresas ?>" class="d-inline">
            <input type="hidden" name="page" value="<?= $page + 1 ?>">
            <input type="hidden" name="b" value="<?= htmlspecialchars($buscar) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="empresas-sistema-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'nombre', 'Razón social', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'nombre_comercial', 'Nombre comercial', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'ruc', 'RUC', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'establecimiento', 'Est.', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'direccion', 'Dirección', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'nombre_provincia', 'Provincia', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'nombre_ciudad', 'Ciudad', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'estado', 'Estado', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th class="text-center">Usuarios</th>
                        <th class="text-center">Documentos</th>
                        <?php if ($nivel >= 3): ?><th class="text-center">Acciones</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $usuarios = $r['usuarios'] ?? [];
                    $estado = $r['estado'] ?? '1';
                    ?>
                    <tr class="empresa-row" role="button" tabindex="0"
                        data-id="<?= (int)($r['id'] ?? 0) ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                        data-nombre-comercial="<?= htmlspecialchars($r['nombre_comercial'] ?? '') ?>"
                        data-ruc="<?= htmlspecialchars($r['ruc'] ?? '') ?>"
                        data-establecimiento="<?= htmlspecialchars($r['establecimiento'] ?? '') ?>"
                        data-direccion="<?= htmlspecialchars($r['direccion'] ?? '') ?>"
                        data-telefono="<?= htmlspecialchars($r['telefono'] ?? '') ?>"
                        data-mail="<?= htmlspecialchars($r['mail'] ?? '') ?>"
                        data-nom-rep-legal="<?= htmlspecialchars($r['nom_rep_legal'] ?? '') ?>"
                        data-ced-rep-legal="<?= htmlspecialchars($r['ced_rep_legal'] ?? '') ?>"
                        data-cod-prov="<?= htmlspecialchars($r['cod_prov'] ?? '') ?>"
                        data-cod-ciudad="<?= htmlspecialchars($r['cod_ciudad'] ?? '') ?>"
                        data-nombre-contador="<?= htmlspecialchars($r['nombre_contador'] ?? '') ?>"
                        data-ruc-contador="<?= htmlspecialchars($r['ruc_contador'] ?? '') ?>"
                        data-estado="<?= htmlspecialchars($estado) ?>"
                        data-valor-cobro="<?= htmlspecialchars($r['valor_cobro'] ?? '') ?>"
                        data-periodo-vigencia-desde="<?= htmlspecialchars($r['periodo_vigencia_desde'] ?? '') ?>"
                        data-periodo-vigencia-hasta="<?= htmlspecialchars($r['periodo_vigencia_hasta'] ?? '') ?>"
                        data-estado-pago="<?= htmlspecialchars($r['estado_pago'] ?? 'pendiente') ?>"
                        data-obligado-contabilidad="<?= htmlspecialchars($r['obligado_contabilidad'] ?? 'NO') ?>"
                        data-max-usuarios="<?= (int)($r['max_usuarios'] ?? 3) ?>"
                        data-id-empresa-suscripciones="<?= (int)($r['id_empresa_suscripciones'] ?? 0) ?>"
                        data-es-administradora="<?= !empty($r['es_administradora_suscripciones']) ? '1' : '0' ?>"
                        data-id-cliente-facturado="<?= (int)($r['id_cliente_facturado'] ?? 0) ?>"
                        data-id-suscripcion="<?= (int)($r['id_suscripcion'] ?? 0) ?>"
                        data-ctrl-label="<?= htmlspecialchars(trim(($r['ctrl_nombre'] ?? '') . (!empty($r['ctrl_ruc']) ? ' — ' . $r['ctrl_ruc'] . ' (' . ($r['ctrl_estab'] ?? '') . ')' : ''))) ?>"
                        data-fact-label="<?= htmlspecialchars(trim(($r['cli_nombre'] ?? '') . (!empty($r['cli_identificacion']) ? ' — ' . $r['cli_identificacion'] : ''))) ?>"
                        data-usuarios="<?= count($usuarios) ?>">
                        <td><?= htmlspecialchars($r['nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['nombre_comercial'] ?? '-') ?></td>
                        <td><code><?= htmlspecialchars($r['ruc'] ?? '') ?></code></td>
                        <td class="text-center"><code><?= htmlspecialchars($r['establecimiento'] ?? '001') ?></code></td>
                        <td class="text-truncate" style="max-width: 180px;"><?= htmlspecialchars($r['direccion'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['nombre_provincia'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['nombre_ciudad'] ?? '-') ?></td>
                        <td>
                            <?php if ($estado === '1'): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <?php
                        $maxUsu  = (int)($r['max_usuarios'] ?? 3);
                        $cntUsu  = count($usuarios);
                        $clsUsu  = $cntUsu >= $maxUsu ? 'bg-danger' : 'bg-light text-dark';
                        ?>
                        <td class="text-center">
                            <span class="badge <?= $clsUsu ?>" title="<?= $cntUsu ?> de <?= $maxUsu ?> permitidos">
                                <?= $cntUsu ?>/<?= $maxUsu ?>
                            </span>
                        </td>
                        <?php
                        // Estado de los documentos legales (acuerdo de datos + contrato de uso)
                        $ed = ($estadoDocs ?? [])[(int)$r['id']] ?? null;
                        if ($ed === null) {
                            $docTit   = 'No se han enviado los documentos legales a esta empresa.';
                            $docBadge = 'bg-danger';
                            $docTxt   = 'Sin enviar';
                            $docIco   = 'x-circle-fill';
                            $docBtn   = 'btn-danger';
                        } elseif (($ed['estado'] ?? '') === 'aceptado') {
                            $docTit   = 'Documentos ACEPTADOS el ' . date('d-m-Y H:i:s', strtotime((string)$ed['aceptado_at']));
                            $docBadge = 'bg-success';
                            $docTxt   = 'Aceptado';
                            $docIco   = 'check-circle-fill';
                            $docBtn   = 'btn-success';
                        } else {
                            $docTit   = 'Enviados el ' . date('d-m-Y H:i:s', strtotime((string)$ed['enviado_at'])) . ', pendientes de aceptación.';
                            $docBadge = 'bg-warning text-dark';
                            $docTxt   = 'Pendiente';
                            $docIco   = 'hourglass-split';
                            $docBtn   = 'btn-warning';
                        }
                        ?>
                        <td class="text-center">
                            <span class="badge <?= $docBadge ?>" title="<?= htmlspecialchars($docTit) ?>" style="font-size:.72rem;">
                                <i class="bi bi-<?= $docIco ?> me-1"></i><?= $docTxt ?>
                            </span>
                        </td>
                        <?php if ($nivel >= 3): ?>
                        <td class="text-center" onclick="event.stopPropagation()">
                            <button class="btn btn-sm <?= $docBtn ?>" title="<?= htmlspecialchars($docTit) ?> Clic para <?= $ed === null ? 'enviar' : 'reenviar' ?>."
                                    onclick="enviarDocumentosLegales(<?= $r['id'] ?>, this)">
                                <i class="bi bi-envelope-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="eliminarEmpresa(<?= $r['id'] ?>)" title="Eliminar empresa"><i class="bi bi-trash"></i></button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay empresas registradas.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Crear Empresa -->
<?php if ($nivel >= 3): ?>
<div class="modal fade" id="modalCrearEmpresa" tabindex="-1" aria-labelledby="modalCrearEmpresaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/empresas-sistema-store" id="form-crear-empresa">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearEmpresaLabel"><i class="bi bi-building-add"></i> Crear empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="crear-empresa-msg" class="d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="crear-ruc" class="form-label">RUC *</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="crear-ruc" name="ruc" class="form-control form-control-sm solo-numero" required placeholder="1234567890001" maxlength="13" inputmode="numeric" pattern="[0-9]{13}" title="13 dígitos numéricos" autocomplete="off">
                                <button type="button" id="btn-consultar-ruc" class="btn btn-outline-secondary btn-sm" title="Consultar datos del RUC"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="crear-establecimiento" class="form-label">Establecimiento *</label>
                            <input type="text" id="crear-establecimiento" name="establecimiento" class="form-control form-control-sm solo-numero" required placeholder="001" maxlength="3" value="001">
                        </div>
                        <div class="col-md-3">
                            <label for="crear-estado" class="form-label">Estado</label>
                            <select id="crear-estado" name="estado" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="crear-max-usuarios" class="form-label">Usuarios</label>
                            <input type="number" id="crear-max-usuarios" name="max_usuarios" class="form-control form-control-sm" min="1" max="9999" value="3" required>
                        </div>
                        <div class="col-12">
                            <label for="crear-nombre" class="form-label">Razón social *</label>
                            <input type="text" id="crear-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Razón social">
                        </div>
                        <div class="col-12">
                            <label for="crear-nombre-comercial" class="form-label">Nombre comercial</label>
                            <input type="text" id="crear-nombre-comercial" name="nombre_comercial" class="form-control form-control-sm" placeholder="Nombre comercial">
                        </div>
                        <div class="col-12">
                            <label for="crear-direccion" class="form-label">Dirección</label>
                            <input type="text" id="crear-direccion" name="direccion" class="form-control form-control-sm" placeholder="Dirección">
                        </div>
                        <div class="col-12">
                            <label for="crear-mail" class="form-label">Correo</label>
                            <input type="email" id="crear-mail" name="mail" class="form-control form-control-sm" placeholder="correo@empresa.com">
                        </div>
                        <div class="col-md-4">
                            <label for="crear-provincia" class="form-label">Provincia</label>
                            <select id="crear-provincia" name="cod_prov" class="form-select form-select-sm">
                                <option value="">Seleccione provincia</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="crear-ciudad" class="form-label">Ciudad</label>
                            <select id="crear-ciudad" name="cod_ciudad" class="form-select form-select-sm">
                                <option value="">Seleccione ciudad</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="crear-telefono" class="form-label">Teléfono</label>
                            <input type="text" id="crear-telefono" name="telefono" class="form-control form-control-sm" placeholder="Teléfono">
                        </div>
                        <?php
                        // Etiqueta preseleccionada de la administradora por defecto.
                        $adminLabel = '';
                        foreach (($empresasLista ?? []) as $emp) {
                            if ((int) $emp['id'] === (int) ($idAdminSuscripciones ?? 0)) {
                                $adminLabel = ($emp['nombre_comercial'] ?: $emp['nombre']) . ' — ' . ($emp['ruc'] ?? '');
                                break;
                            }
                        }
                        ?>
                        <div class="col-md-8 position-relative">
                            <label for="crear-ctrl-texto" class="form-label">Empresa que controla las suscripciones</label>
                            <input type="text" id="crear-ctrl-texto" class="form-control form-control-sm" placeholder="Buscar empresa por nombre o RUC…" autocomplete="off" value="<?= htmlspecialchars($adminLabel) ?>">
                            <input type="hidden" id="crear-ctrl-id" name="id_empresa_suscripciones" value="<?= (int) ($idAdminSuscripciones ?? 0) ?: '' ?>">
                            <div id="crear-ctrl-dropdown" class="list-group position-absolute w-100 shadow" style="display:none;z-index:2000;max-height:220px;overflow:auto;"></div>
                            <div class="form-text">Empresa cuyas suscripciones se cruzarán por RUC para esta nueva empresa.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">Administradora</label>
                            <input type="hidden" name="es_administradora_suscripciones" value="0">
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="crear-es-administradora" name="es_administradora_suscripciones" value="1">
                                <label class="form-check-label small" for="crear-es-administradora">Es la empresa administradora (por defecto)</label>
                            </div>
                        </div>
                        <div class="col-md-8 position-relative">
                            <label for="crear-fact-texto" class="form-label">Empresa a la que facturamos (reventa)</label>
                            <input type="text" id="crear-fact-texto" class="form-control form-control-sm" placeholder="Buscar cliente por nombre o identificación…" autocomplete="off">
                            <input type="hidden" id="crear-fact-id" name="id_cliente_facturado" value="">
                            <div id="crear-fact-dropdown" class="list-group position-absolute w-100 shadow" style="display:none;z-index:2000;max-height:220px;overflow:auto;"></div>
                            <div class="form-text">Busca entre los <strong>clientes de la empresa controladora</strong>. Si eliges uno, esa selección manda (ficha sin montos); si lo dejas vacío, se usa la regla por RUC propio.</div>
                        </div>
                        <input type="hidden" name="tipo" value="01">
                        <input type="hidden" name="nom_rep_legal" value="">
                        <input type="hidden" name="ced_rep_legal" value="">
                        <input type="hidden" name="nombre_contador" value="">
                        <input type="hidden" name="ruc_contador" value="">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg"></i> Crear empresa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Ficha Empresa (con pestañas) -->
<div class="modal fade" id="modalDetalleEmpresa" tabindex="-1" aria-labelledby="modalDetalleEmpresaLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleEmpresaLabel"><i class="bi bi-building"></i> <span id="modal-empresa-nombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-empresa-id" value="">
                <ul class="nav nav-tabs mb-3" id="tabsEmpresa" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-empresas-general" data-bs-toggle="tab" data-bs-target="#pane-empresas-general" type="button" role="tab">General</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas-establecimientos" data-bs-toggle="tab" data-bs-target="#pane-empresas-establecimientos" type="button" role="tab">Establecimientos</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas-usuarios" data-bs-toggle="tab" data-bs-target="#pane-empresas-usuarios" type="button" role="tab">Usuarios asignados</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas-cobro" data-bs-toggle="tab" data-bs-target="#pane-empresas-cobro" type="button" role="tab">Cobro y vigencia</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas-documentos" data-bs-toggle="tab" data-bs-target="#pane-empresas-documentos" type="button" role="tab">Documentos</button>
                    </li>
                </ul>
                    <div class="tab-content" id="tabsEmpresaContent">
                    <div class="tab-pane fade show active" id="pane-empresas-general" role="tabpanel">
                        <form method="POST" action="<?= $base ?>/config/empresas-sistema-update" id="form-editar-empresa">
                            <div id="editar-empresa-msg" class="d-none mb-3"></div>
                            <input type="hidden" name="id" id="edit-empresa-id" value="">
                            <input type="hidden" name="nom_rep_legal" value="">
                            <input type="hidden" name="ced_rep_legal" value="">
                            <input type="hidden" name="nombre_contador" value="">
                            <input type="hidden" name="ruc_contador" value="">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="edit-ruc" class="form-label">RUC</label>
                                    <input type="text" id="edit-ruc" name="ruc" class="form-control form-control-sm solo-numero" placeholder="RUC" maxlength="13" pattern="[0-9]{13}" inputmode="numeric" title="13 dígitos numéricos" autocomplete="off">
                                </div>
                                <div class="col-md-3">
                                    <label for="edit-establecimiento" class="form-label">Establecimiento</label>
                                    <input type="text" id="edit-establecimiento" name="establecimiento" class="form-control form-control-sm solo-numero" placeholder="001" maxlength="3" pattern="[0-9]{1,3}" title="3 dígitos (000 a 999)">
                                    <div class="form-text small" style="font-size: 0.6rem;">3 dígitos (000-999). No puede repetirse el mismo RUC + establecimiento.</div>
                                </div>
                                <div class="col-md-3">
                                    <label for="edit-estado" class="form-label">Estado</label>
                                    <select id="edit-estado" name="estado" class="form-select form-select-sm">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="edit-max-usuarios-gen" class="form-label">Usuarios</label>
                                    <input type="number" id="edit-max-usuarios-gen" name="max_usuarios" class="form-control form-control-sm" min="1" max="9999" value="3" required>
                                </div>
                                <div class="col-12">
                                    <label for="edit-nombre" class="form-label">Razón social</label>
                                    <input type="text" id="edit-nombre" name="nombre" class="form-control form-control-sm" placeholder="Razón social">
                                </div>
                                <div class="col-12">
                                    <label for="edit-nombre-comercial" class="form-label">Nombre comercial</label>
                                    <input type="text" id="edit-nombre-comercial" name="nombre_comercial" class="form-control form-control-sm" placeholder="Nombre comercial">
                                </div>
                                <div class="col-md-2">
                                    <label for="edit-obligado-contabilidad" class="form-label">Obligado a llevar contabilidad</label>
                                    <select id="edit-obligado-contabilidad" name="obligado_contabilidad" class="form-select form-select-sm">
                                        <option value="NO">NO</option>
                                        <option value="SI">SI</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="edit-mail" class="form-label">Correo empresa</label>
                                    <input type="email" id="edit-mail" name="mail" class="form-control form-control-sm" placeholder="correo@empresa.com">
                                </div>
                                <div class="col-md-5">
                                    <label for="edit-direccion" class="form-label">Dirección matriz</label>
                                    <input type="text" id="edit-direccion" name="direccion" class="form-control form-control-sm" placeholder="Dirección">
                                </div>
                                <div class="col-md-4">
                                    <label for="edit-provincia" class="form-label">Provincia</label>
                                    <select id="edit-provincia" name="cod_prov" class="form-select form-select-sm">
                                        <option value="">Seleccione provincia</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit-ciudad" class="form-label">Ciudad</label>
                                    <select id="edit-ciudad" name="cod_ciudad" class="form-select form-select-sm">
                                        <option value="">Seleccione ciudad</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit-telefono" class="form-label">Teléfono</label>
                                    <input type="text" id="edit-telefono" name="telefono" class="form-control form-control-sm" placeholder="Teléfono">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar cambios</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas-establecimientos" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 small text-primary"><i class="bi bi-buildings me-2"></i>Establecimientos</h6>
                        </div>
                        <div class="establecimientos-empresa-scroll">
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Estado</th><th class="text-end">Acción</th></tr></thead>
                                <tbody id="tbody-establecimientos-empresa"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas-usuarios" role="tabpanel">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small">Agregar usuario</label>
                                <select id="select-usuario-empresa" class="form-select form-select-sm">
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-agregar-usuario-empresa">
                                    <i class="bi bi-plus"></i> Asignar
                                </button>
                            </div>
                        </div>
                        <div class="usuarios-empresa-scroll">
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Nivel</th><th class="text-end">Quitar</th></tr></thead>
                                <tbody id="tbody-usuarios-empresa"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas-cobro" role="tabpanel">
                        <form method="POST" action="<?= $base ?>/config/empresas-sistema-update" id="form-editar-empresa-cobro">
                            <div id="editar-cobro-msg" class="d-none mb-3"></div>
                            <input type="hidden" name="id" id="edit-empresa-id-cobro" value="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit-valor-cobro" class="form-label">Valor asignado de cobro</label>
                                    <input type="number" id="edit-valor-cobro" name="valor_cobro" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-estado-pago" class="form-label">Estado de pago</label>
                                    <select id="edit-estado-pago" name="estado_pago" class="form-select form-select-sm">
                                        <option value="pendiente">Pendiente</option>
                                        <option value="pagado">Pagado</option>
                                        <option value="vencido">Vencido</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-vigencia-desde" class="form-label">Periodo vigencia desde</label>
                                    <input type="date" id="edit-vigencia-desde" name="periodo_vigencia_desde" class="form-control form-control-sm" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-vigencia-hasta" class="form-label">Periodo vigencia hasta</label>
                                    <input type="date" id="edit-vigencia-hasta" name="periodo_vigencia_hasta" class="form-control form-control-sm" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="col-12"><hr class="my-1"><small class="text-muted fw-bold"><i class="bi bi-arrow-repeat"></i> Suscripción del sistema</small></div>
                                <div class="col-md-8 position-relative">
                                    <label for="edit-ctrl-texto" class="form-label">Empresa que controla las suscripciones</label>
                                    <input type="text" id="edit-ctrl-texto" class="form-control form-control-sm" placeholder="Buscar empresa por nombre o RUC…" autocomplete="off">
                                    <input type="hidden" id="edit-ctrl-id" name="id_empresa_suscripciones" value="">
                                    <div id="edit-ctrl-dropdown" class="list-group position-absolute w-100 shadow" style="display:none;z-index:2000;max-height:220px;overflow:auto;"></div>
                                    <div class="form-text">Se cruza por RUC contra los clientes de esa empresa para mostrar la suscripción real.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label d-block">Administradora</label>
                                    <input type="hidden" name="es_administradora_suscripciones" value="0">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" role="switch" id="edit-es-administradora" name="es_administradora_suscripciones" value="1">
                                        <label class="form-check-label small" for="edit-es-administradora">Es la empresa administradora (por defecto)</label>
                                    </div>
                                </div>
                                <div class="col-12 position-relative">
                                    <label for="edit-fact-texto" class="form-label">Empresa a la que facturamos (reventa)</label>
                                    <input type="text" id="edit-fact-texto" class="form-control form-control-sm" placeholder="Buscar cliente por nombre o identificación…" autocomplete="off">
                                    <input type="hidden" id="edit-fact-id" name="id_cliente_facturado" value="">
                                    <div id="edit-fact-dropdown" class="list-group position-absolute w-100 shadow" style="display:none;z-index:2000;max-height:220px;overflow:auto;"></div>
                                    <div class="form-text">Busca entre los <strong>clientes de la empresa controladora</strong> seleccionada arriba. Si eliges uno, esa selección manda y la ficha muestra solo estado, periodicidad y vigencia (sin montos). Si lo dejas vacío, se aplica la regla por RUC propio.</div>
                                </div>
                                <div class="col-12" id="edit-susc-wrap" style="display:none;">
                                    <label for="edit-id-suscripcion" class="form-label">Suscripción que cubre a esta empresa</label>
                                    <select id="edit-id-suscripcion" name="id_suscripcion" class="form-select form-select-sm">
                                        <option value="">— Automático (si el cliente tiene una sola) —</option>
                                    </select>
                                    <div class="form-text">
                                        Cuando el cliente facturado tiene <strong>varias suscripciones</strong> (una por cada empresa suya),
                                        indica aquí cuál corresponde a <strong>esta</strong> empresa. Si no la eliges, la ficha avisará que falta asignarla.
                                        <br>Cada opción muestra primero el <strong>detalle adicional</strong> de la suscripción
                                        (donde se suele anotar el nombre del cliente final), para distinguirlas.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar cambios</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas-documentos" role="tabpanel">

                        <!-- Documentos legales del sistema (acuerdo de datos + contrato de uso) -->
                        <div class="card border mb-3">
                            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                <span class="small fw-semibold">
                                    <i class="bi bi-shield-check me-1"></i>Documentos legales del sistema
                                </span>
                                <span id="dl-estado-badge"></span>
                            </div>
                            <div class="card-body p-3">
                                <p class="text-muted small mb-2">
                                    Acuerdo de uso de datos y contrato de uso que se envían al correo de la empresa.
                                </p>

                                <div id="dl-info" class="small mb-2 text-muted">Cargando…</div>

                                <div class="d-flex gap-2 flex-wrap align-items-center">
                                    <a id="dl-btn-acuerdo" href="#" download class="btn btn-sm btn-outline-danger"
                                       title="Descargar el PDF del acuerdo de uso de datos">
                                        <i class="bi bi-download"></i> Acuerdo de uso de datos
                                    </a>
                                    <a id="dl-btn-contrato" href="#" download class="btn btn-sm btn-outline-danger"
                                       title="Descargar el PDF del contrato de uso">
                                        <i class="bi bi-download"></i> Contrato de uso
                                    </a>
                                    <div class="vr mx-1"></div>
                                    <button type="button" id="dl-btn-enviar" class="btn btn-sm btn-primary">
                                        <i class="bi bi-envelope"></i> <span id="dl-btn-enviar-txt">Enviar por correo</span>
                                    </button>
                                </div>

                                <div id="dl-historial" class="mt-3"></div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <p class="text-muted small mb-2">Documentos propios de la empresa: contratos, RUC, licencias, etc.</p>
                        <form method="POST" action="<?= $base ?>/config/empresas-sistema?action=uploadDocumento" enctype="multipart/form-data" class="mb-3">
                            <input type="hidden" name="action" value="uploadDocumento">
                            <input type="hidden" name="id_empresa" id="upload-id-empresa" value="">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label for="upload-tipo" class="form-label small">Tipo</label>
                                    <select id="upload-tipo" name="tipo_documento" class="form-select form-select-sm">
                                        <option value="contrato">Contrato</option>
                                        <option value="ruc">RUC</option>
                                        <option value="licencia">Licencia</option>
                                        <option value="poder">Poder</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="upload-archivo" class="form-label small">Archivo</label>
                                    <input type="file" id="upload-archivo" name="archivo" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="upload-descripcion" class="form-label small">Descripción (opcional)</label>
                                    <input type="text" id="upload-descripcion" name="descripcion" class="form-control form-control-sm" placeholder="Ej: Contrato 2024">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Subir documento</button>
                                </div>
                            </div>
                        </form>
                        <div class="documentos-empresa-scroll">
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr><th>Tipo</th><th>Archivo</th><th>Descripción</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody id="tbody-documentos-empresa"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Establecimiento (desde Sistema) -->
<div class="modal fade" id="modalEditarEstSistema" tabindex="-1">
    <div class="modal-dialog">
        <form id="form-edit-est-sistema" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold small"><i class="bi bi-buildings me-2"></i>Editar Establecimiento</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" name="action" value="updateEstablecimiento">
                <input type="hidden" name="id" id="edit-est-id-sistema">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Código</label>
                        <input type="text" name="codigo" id="edit-est-codigo-sistema" class="form-control form-control-sm fw-bold solo-numero" maxlength="3" pattern="[0-9]{1,3}" title="3 dígitos (000 a 999)" required>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label small fw-bold">Nombre</label>
                        <input type="text" name="nombre" id="edit-est-nombre-sistema" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Tipo</label>
                        <select name="tipo" id="edit-est-tipo-sistema" class="form-select form-select-sm">
                            <option value="Matriz">Casa Matriz</option>
                            <option value="Sucursal">Sucursal</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Estado</label>
                        <select name="estado" id="edit-est-estado-sistema" class="form-select form-select-sm">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Dirección</label>
                        <textarea name="direccion" id="edit-est-direccion-sistema" class="form-control form-control-sm" rows="2" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-1">
                <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">GUARDAR CAMBIOS</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var base = '<?= $base ?>';

    document.querySelectorAll('.solo-numero').forEach(function(inp) {
        inp.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        inp.addEventListener('paste', function(e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text');
            var nums = text.replace(/\D/g, '');
            var start = this.selectionStart, end = this.selectionEnd;
            this.value = this.value.substring(0, start) + nums + this.value.substring(end);
            this.setSelectionRange(start + nums.length, start + nums.length);
        });
    });

    var modal = document.getElementById('modalDetalleEmpresa');
    var tbody = document.getElementById('tbody-usuarios-empresa');
    var idEmpresa = 0;

    function cargarProvincias(selectId, callback) {
        var sel = document.getElementById(selectId);
        if (!sel) return;
        fetch(base + '/config/empresas-sistema?action=provincias', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var opts = sel.options;
                opts.length = 1;
                (data.provincias || []).forEach(function(p) {
                    var o = document.createElement('option');
                    o.value = p.codigo || '';
                    o.textContent = p.nombre || '';
                    opts.add(o);
                });
                if (typeof callback === 'function') callback();
            })
            .catch(function() { if (typeof callback === 'function') callback(); });
    }

    function cargarCiudades(codProv, selectId, valorSeleccionado) {
        var sel = document.getElementById(selectId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Seleccione ciudad</option>';
        if (!codProv) return;
        fetch(base + '/config/empresas-sistema?action=ciudades&cod_prov=' + encodeURIComponent(codProv), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var opts = sel.options;
                opts.length = 1;
                (data.ciudades || []).forEach(function(c) {
                    var o = document.createElement('option');
                    o.value = c.codigo || '';
                    o.textContent = c.nombre || '';
                    opts.add(o);
                });
                if (valorSeleccionado) sel.value = valorSeleccionado;
            })
            .catch(function() {});
    }

    function abrirModalEmpresa(el) {
        idEmpresa = parseInt(el.dataset.id, 10);
        document.getElementById('modal-empresa-id').value = idEmpresa;
        document.getElementById('edit-empresa-id').value = idEmpresa;
        document.getElementById('edit-empresa-id-cobro').value = idEmpresa;
        document.getElementById('upload-id-empresa').value = idEmpresa;
        document.getElementById('modal-empresa-nombre').textContent = el.dataset.nombre || el.dataset.nombreComercial || el.dataset.establecimiento || '';
        document.getElementById('edit-nombre-comercial').value = el.dataset.nombreComercial || '';
        document.getElementById('edit-ruc').value = el.dataset.ruc || '';
        document.getElementById('edit-establecimiento').value = el.dataset.establecimiento || '001';
        document.getElementById('edit-nombre').value = el.dataset.nombre || '';
        document.getElementById('edit-direccion').value = el.dataset.direccion || '';
        document.getElementById('edit-telefono').value = el.dataset.telefono || '';
        document.getElementById('edit-mail').value = el.dataset.mail || '';
        document.getElementById('edit-obligado-contabilidad').value = (el.dataset.obligadoContabilidad || 'NO').toUpperCase() === 'SI' ? 'SI' : 'NO';
        document.getElementById('edit-estado').value = (el.dataset.estado === '1') ? '1' : '0';
        var codProv = el.dataset.codProv || '';
        var codCiudad = el.dataset.codCiudad || '';
        cargarProvincias('edit-provincia', function() {
            document.getElementById('edit-provincia').value = codProv;
            cargarCiudades(codProv, 'edit-ciudad', codCiudad);
        });
        document.getElementById('edit-max-usuarios-gen').value = el.dataset.maxUsuarios || '3';
        document.getElementById('edit-valor-cobro').value = el.dataset.valorCobro || '';
        document.getElementById('edit-vigencia-desde').value = el.dataset.periodoVigenciaDesde || '';
        document.getElementById('edit-vigencia-hasta').value = el.dataset.periodoVigenciaHasta || '';
        document.getElementById('edit-estado-pago').value = el.dataset.estadoPago || 'pendiente';
        // Buscador: empresa que controla las suscripciones (precarga id + etiqueta)
        var ctrlId = document.getElementById('edit-ctrl-id');
        var ctrlTxt = document.getElementById('edit-ctrl-texto');
        if (ctrlId && ctrlTxt) {
            var hasCtrl = el.dataset.idEmpresaSuscripciones && el.dataset.idEmpresaSuscripciones !== '0';
            ctrlId.value  = hasCtrl ? el.dataset.idEmpresaSuscripciones : '';
            ctrlTxt.value = hasCtrl ? (el.dataset.ctrlLabel || '') : '';
        }
        var chkAdmin = document.getElementById('edit-es-administradora');
        if (chkAdmin) chkAdmin.checked = (el.dataset.esAdministradora === '1');
        // Buscador: cliente al que facturamos (reventa)
        var factId = document.getElementById('edit-fact-id');
        var factTxt = document.getElementById('edit-fact-texto');
        if (factId && factTxt) {
            var hasFact = el.dataset.idClienteFacturado && el.dataset.idClienteFacturado !== '0';
            factId.value  = hasFact ? el.dataset.idClienteFacturado : '';
            factTxt.value = hasFact ? (el.dataset.factLabel || '') : '';
        }
        // Selector de suscripción específica (solo aplica en reventa)
        cargarSuscripcionesCliente(el.dataset.idSuscripcion || '');
        document.getElementById('tab-empresas-general').click();
        cargarUsuarios();
        cargarUsuariosDisponibles();
        cargarDocumentos();
        cargarEstablecimientos();
        new bootstrap.Modal(modal).show();
    }

    function cargarUsuariosDisponibles() {
        var select = document.getElementById('select-usuario-empresa');
        select.innerHTML = '<option value="">Cargando...</option>';
        fetch(base + '/config/empresas-sistema?action=usuariosDisponiblesEmpresa&id_empresa=' + idEmpresa)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                select.innerHTML = '<option value="">Seleccione usuario...</option>';
                (data.usuarios || []).forEach(function(u) {
                    var o = document.createElement('option');
                    o.value = u.id_usuario;
                    o.textContent = (u.nombre || '') + ' (' + (u.cedula || '') + ')';
                    select.appendChild(o);
                });
            })
            .catch(function() { select.innerHTML = '<option value="">Error</option>'; });
    }

    /**
     * Carga las suscripciones del cliente facturado para elegir cuál cubre a
     * esta empresa. Solo tiene sentido en reventa (hay cliente facturado); si
     * el cliente tiene una sola, el selector queda oculto (se resuelve solo).
     */
    window.cargarSuscripcionesCliente = function(idSeleccionada) {
        var wrap = document.getElementById('edit-susc-wrap');
        var sel  = document.getElementById('edit-id-suscripcion');
        if (!wrap || !sel) return;

        var idCtrl = (document.getElementById('edit-ctrl-id') || {}).value || '';
        var idCli  = (document.getElementById('edit-fact-id') || {}).value || '';

        // Sin cliente facturado no hay reventa: no aplica.
        if (!idCli) {
            wrap.style.display = 'none';
            sel.innerHTML = '<option value="">— Automático —</option>';
            return;
        }

        fetch(base + '/config/empresas-sistema?action=suscripcionesCliente&id_controladora=' +
              encodeURIComponent(idCtrl) + '&id_cliente=' + encodeURIComponent(idCli), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var rows = (res && res.data) ? res.data : [];
            // Con 0 o 1 suscripción no hay ambigüedad: se resuelve automáticamente.
            if (rows.length < 2 && !idSeleccionada) {
                wrap.style.display = 'none';
                return;
            }
            var html = '<option value="">— Automático (si el cliente tiene una sola) —</option>';
            rows.forEach(function(s) {
                var monto = parseFloat(s.monto || 0).toFixed(2);
                // El detalle adicional (donde se anota el cliente final) va primero:
                // es lo que permite distinguir una suscripción de otra.
                var etiqueta = (s.info_texto || '').trim();
                if (!etiqueta) etiqueta = (s.items || '').trim();
                if (!etiqueta) etiqueta = (s.observaciones || '').trim();
                if (etiqueta.length > 60) etiqueta = etiqueta.substring(0, 60) + '…';

                var txt = (etiqueta ? etiqueta + '  —  ' : '') +
                          '#' + s.id + ' · ' + (s.periodicidad || 'sin periodicidad') +
                          ' · $' + monto + ' · ' + (s.estado || '') +
                          (s.proximo_cobro ? ' · próx. ' + s.proximo_cobro : '');
                html += '<option value="' + s.id + '"' +
                        (String(s.id) === String(idSeleccionada) ? ' selected' : '') + '>' +
                        txt.replace(/</g, '&lt;') + '</option>';
            });
            sel.innerHTML = html;
            wrap.style.display = '';
        })
        .catch(function() { wrap.style.display = 'none'; });
    };

    // ── Documentos legales del sistema (acuerdo de datos + contrato de uso) ──
    function fmtFecha(s) {
        if (!s) return '—';
        var d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        function p(n) { return (n < 10 ? '0' : '') + n; }
        return p(d.getDate()) + '-' + p(d.getMonth() + 1) + '-' + d.getFullYear() +
               ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }

    function cargarDocumentosLegales() {
        var badge = document.getElementById('dl-estado-badge');
        var info  = document.getElementById('dl-info');
        var hist  = document.getElementById('dl-historial');
        var btnEnviar = document.getElementById('dl-btn-enviar');
        var btnTxt = document.getElementById('dl-btn-enviar-txt');
        if (!badge || !idEmpresa) return;

        // Enlaces de descarga (siempre disponibles: se regenera el PDF)
        var urlDoc = base + '/config/empresas-sistema?action=descargarDocumentoLegal&id=' + idEmpresa + '&tipo=';
        document.getElementById('dl-btn-acuerdo').href = urlDoc + 'acuerdo_datos';
        document.getElementById('dl-btn-contrato').href = urlDoc + 'contrato_uso';

        info.textContent = 'Cargando…';
        badge.innerHTML = '';
        hist.innerHTML = '';

        fetch(base + '/config/empresas-sistema?action=historialDocumentosLegales&id=' + idEmpresa, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var rows = (res && res.data) ? res.data : [];
            btnEnviar.style.display = (res && res.puede_enviar) ? '' : 'none';

            if (!rows.length) {
                badge.innerHTML = '<span class="badge bg-secondary">Sin enviar</span>';
                info.innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>' +
                                 'Todavía no se han enviado los documentos a esta empresa.';
                btnTxt.textContent = 'Enviar por correo';
                return;
            }

            var u = rows[0];
            if (u.estado === 'aceptado') {
                badge.innerHTML = '<span class="badge bg-success">Aceptado</span>';
                info.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>' +
                    'Aceptado el <b>' + fmtFecha(u.aceptado_at) + '</b> por <b>' +
                    (u.aceptado_nombre || '—') + '</b>' +
                    (u.aceptado_identificacion ? ' (' + u.aceptado_identificacion + ')' : '') +
                    '<br><span class="text-muted">IP ' + (u.aceptado_ip || '—') +
                    ' · enviado a ' + (u.correo_destino || '—') + '</span>';
            } else {
                badge.innerHTML = '<span class="badge bg-warning text-dark">Pendiente de aceptación</span>';
                info.innerHTML = '<i class="bi bi-hourglass-split text-warning me-1"></i>' +
                    'Enviado el <b>' + fmtFecha(u.enviado_at) + '</b> a <b>' + (u.correo_destino || '—') +
                    '</b>, aún sin aceptar.';
            }
            btnTxt.textContent = 'Reenviar por correo';

            // Historial (si hay más de un envío)
            if (rows.length > 1) {
                var h = '<div class="small fw-semibold mb-1">Historial de envíos</div>' +
                        '<div style="max-height:150px;overflow:auto;">' +
                        '<table class="table table-sm table-bordered mb-0" style="font-size:.75rem;">' +
                        '<thead class="table-light"><tr><th>Enviado</th><th>Correo</th><th>Estado</th><th>Aceptado</th></tr></thead><tbody>';
                rows.forEach(function(r) {
                    h += '<tr><td>' + fmtFecha(r.enviado_at) + '</td>' +
                         '<td>' + (r.correo_destino || '—') + '</td>' +
                         '<td>' + (r.estado === 'aceptado'
                            ? '<span class="text-success">Aceptado</span>'
                            : '<span class="text-warning">Pendiente</span>') + '</td>' +
                         '<td>' + (r.aceptado_at ? fmtFecha(r.aceptado_at) + ' · ' + (r.aceptado_nombre || '') : '—') + '</td></tr>';
                });
                h += '</tbody></table></div>';
                hist.innerHTML = h;
            }
        })
        .catch(function() {
            info.innerHTML = '<span class="text-danger">Error al cargar el estado de los documentos.</span>';
        });
    }

    // Botón enviar/reenviar dentro de la pestaña Documentos
    document.addEventListener('DOMContentLoaded', function() {
        var b = document.getElementById('dl-btn-enviar');
        if (b) {
            b.addEventListener('click', function() {
                if (!idEmpresa) return;
                window.enviarDocumentosLegales(idEmpresa, b, function() {
                    cargarDocumentosLegales();
                });
            });
        }
    });

    function cargarDocumentos() {
        var tbody = document.getElementById('tbody-documentos-empresa');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Cargando...</td></tr>';
        fetch(base + '/config/empresas-sistema?action=documentosEmpresa&id=' + idEmpresa)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.html) {
                    tbody.innerHTML = data.html;
                    tbody.querySelectorAll('.btn-eliminar-doc').forEach(function(b) {
                        b.addEventListener('click', function() {
                            if (confirm('¿Eliminar este documento?')) {
                                var id = this.dataset.id;
                                var formData = new FormData();
                                formData.append('action', 'deleteDocumento');
                                formData.append('id', id);
                                fetch(base + '/config/empresas-sistema', {
                                    method: 'POST',
                                    body: formData,
                                    credentials: 'same-origin',
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                                })
                                .then(function(r) { return r.json(); })
                                .then(function(res) {
                                    if (res.ok) cargarDocumentos();
                                    else alert(res.msg || 'Error');
                                })
                                .catch(function() { alert('Error al eliminar'); });
                            }
                        });
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Sin documentos</td></tr>';
                }
            })
            .catch(function() { tbody.innerHTML = '<tr><td colspan="4" class="text-danger">Error al cargar</td></tr>'; });
    }

    function cargarEstablecimientos() {
        var tbody = document.getElementById('tbody-establecimientos-empresa');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Cargando...</td></tr>';
        fetch(base + '/config/empresas-sistema?action=establecimientosEmpresa&id=' + idEmpresa)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.html) {
                    tbody.innerHTML = data.html;
                    tbody.querySelectorAll('.btn-edit-est').forEach(function(b) {
                        b.addEventListener('click', function() {
                            var est = JSON.parse(this.dataset.est);
                            document.getElementById('edit-est-id-sistema').value = est.id;
                            document.getElementById('edit-est-codigo-sistema').value = est.codigo || '';
                            document.getElementById('edit-est-nombre-sistema').value = est.nombre || '';
                            document.getElementById('edit-est-direccion-sistema').value = est.direccion || '';
                            document.getElementById('edit-est-tipo-sistema').value = est.tipo || 'Sucursal';
                            document.getElementById('edit-est-estado-sistema').value = est.estado || 'activo';
                            
                            var mEl = document.getElementById('modalEditarEstSistema');
                            var modalEdit = bootstrap.Modal.getInstance(mEl) || new bootstrap.Modal(mEl);
                            modalEdit.show();
                        });
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">No hay establecimientos registrados.</td></tr>';
                }
            })
            .catch(function() { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error al cargar sucursales.</td></tr>'; });
    }

    // Submit form edit establecimiento sistema
    var formEditEst = document.getElementById('form-edit-est-sistema');
    if (formEditEst) {
        formEditEst.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('button[type="submit"]');
            var txtOrig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
            
            fetch(base + '/config/empresas-sistema', {
                method: 'POST',
                body: new FormData(this),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(r) { return r.json(); })
            .then(function(res) {
                btn.disabled = false;
                btn.innerHTML = txtOrig;
                if (res.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarEstSistema')).hide();
                    cargarEstablecimientos();
                    if (window.Swal) Swal.fire('Éxito', res.msg, 'success');
                    else alert(res.msg);
                } else {
                    alert(res.error || 'Error al actualizar');
                }
            }).catch(function() {
                btn.disabled = false;
                btn.innerHTML = txtOrig;
                alert('Error de conexión');
            });
        });
    }

    document.getElementById('tab-empresas-establecimientos').addEventListener('shown.bs.tab', cargarEstablecimientos);
    document.getElementById('tab-empresas-documentos').addEventListener('shown.bs.tab', function() {
        cargarDocumentos();
        cargarDocumentosLegales();
    });
    document.getElementById('tab-empresas-usuarios').addEventListener('shown.bs.tab', function() {
        cargarUsuarios();
        cargarUsuariosDisponibles();
    });

    document.querySelectorAll('.empresa-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('a')) abrirModalEmpresa(this);
        });
        row.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ' ') && !e.target.closest('a')) {
                e.preventDefault();
                abrirModalEmpresa(this);
            }
        });
    });

    function cargarUsuarios() {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Cargando...</td></tr>';
        fetch(base + '/config/empresas-sistema?action=usuariosEmpresa&id=' + idEmpresa)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.html) {
                    tbody.innerHTML = data.html;
                    tbody.querySelectorAll('.btn-quitar-usuario-empresa').forEach(function(b) {
                        b.addEventListener('click', function() {
                            if (confirm('¿Quitar este usuario de la empresa?')) {
                                var id = this.dataset.id;
                                var f = document.createElement('form');
                                f.method = 'POST';
                                f.action = base + '/config/asignar-empresas';
                                var i = document.createElement('input');
                                i.type = 'hidden'; i.name = 'action'; i.value = 'quitar';
                                var i2 = document.createElement('input');
                                i2.type = 'hidden'; i2.name = 'id'; i2.value = id;
                                var i3 = document.createElement('input');
                                i3.type = 'hidden'; i3.name = 'redirect'; i3.value = 'empresas-sistema';
                                f.appendChild(i); f.appendChild(i2); f.appendChild(i3);
                                document.body.appendChild(f);
                                f.submit();
                            }
                        });
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Sin usuarios asignados</td></tr>';
                }
                cargarUsuariosDisponibles();
            })
            .catch(function() { tbody.innerHTML = '<tr><td colspan="5" class="text-danger">Error al cargar</td></tr>'; });
    }

    document.getElementById('btn-agregar-usuario-empresa').addEventListener('click', function() {
        var idUsuario = document.getElementById('select-usuario-empresa').value;
        if (!idUsuario) { alert('Seleccione un usuario'); return; }
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = base + '/config/asignar-empresas';
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'action'; i.value = 'asignar';
        var i2 = document.createElement('input');
        i2.type = 'hidden'; i2.name = 'id_empresa'; i2.value = idEmpresa;
        var i3 = document.createElement('input');
        i3.type = 'hidden'; i3.name = 'id_usuario'; i3.value = idUsuario;
        var i4 = document.createElement('input');
        i4.type = 'hidden'; i4.name = 'redirect'; i4.value = 'empresas-sistema';
        f.appendChild(i); f.appendChild(i2); f.appendChild(i3); f.appendChild(i4);
        document.body.appendChild(f);
        f.submit();
    });

    document.getElementById('edit-provincia').addEventListener('change', function() {
        cargarCiudades(this.value, 'edit-ciudad', '');
    });

    var modalCrear = document.getElementById('modalCrearEmpresa');
    var crearProvincia = document.getElementById('crear-provincia');
    if (modalCrear) {
        modalCrear.addEventListener('shown.bs.modal', function() {
            cargarProvincias('crear-provincia');
        });
    }
    if (crearProvincia) {
        crearProvincia.addEventListener('change', function() {
            cargarCiudades(this.value, 'crear-ciudad', '');
        });
    }

    function consultarRucSri() {
        var inp = document.getElementById('crear-ruc');
        var btn = document.getElementById('btn-consultar-ruc');
        if (!inp || !btn) return;
        var num = (inp.value || '').replace(/\D/g, '');
        if (num.length !== 13) {
            return;
        }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
        fetch(base + '/config/empresas-sistema?action=sriIdentificacion&numero=' + encodeURIComponent(num), { credentials: 'same-origin' })
            .then(function(r) {
                var ct = (r.headers.get('content-type') || '');
                if (!ct.includes('application/json')) {
                    return r.text().then(function(t) {
                        throw new Error('El servidor no devolvió JSON (¿sesión expirada o error PHP?). ' + (t ? t.slice(0, 120) : ''));
                    });
                }
                return r.json();
            })
            .then(function(res) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i>';
                if (res.ok && res.data) {
                    var d = res.data;
                    document.getElementById('crear-nombre').value = d.nombre || '';
                    document.getElementById('crear-nombre-comercial').value = d.nombre_comercial || '';
                    document.getElementById('crear-direccion').value = d.direccion || '';
                    if (d.tipo && modalCrear) {
                        var tipoInput = modalCrear.querySelector('input[name="tipo"]');
                        if (tipoInput) tipoInput.value = d.tipo;
                    }
                    var codProv = d.cod_prov || '';
                    var codCiud = d.cod_ciudad || '';
                    if (codProv) {
                        document.getElementById('crear-provincia').value = codProv;
                        cargarCiudades(codProv, 'crear-ciudad', codCiud);
                    }
                } else {
                    alert(res.error || 'No se pudo consultar el RUC.');
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i>';
                var msg = (err && err.message) ? err.message
                    : 'Error de conexión al consultar el RUC. Revise sri_identification_url en config/app.php y que el servidor salga a internet (probar con curl desde el VPS).';
                alert(msg);
            });
    }

    var crearRuc = document.getElementById('crear-ruc');
    var btnConsultar = document.getElementById('btn-consultar-ruc');
    if (crearRuc) {
        crearRuc.addEventListener('blur', function() {
            var n = (this.value || '').replace(/\D/g, '');
            if (n.length === 13) consultarRucSri();
        });
    }
    if (btnConsultar) {
        btnConsultar.addEventListener('click', consultarRucSri);
    }

    function mostrarMsgForm(containerId, tipo, texto) {
        var el = document.getElementById(containerId);
        if (!el) return;
        el.className = 'alert alert-' + (tipo === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show mb-3';
        el.innerHTML = texto + ' <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
        el.classList.remove('d-none');
    }
    function ocultarMsgForm(containerId) {
        var el = document.getElementById(containerId);
        if (el) el.classList.add('d-none');
    }

    function enviarFormAjax(form, msgContainerId, url) {
        return fetch(url, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok) {
                mostrarMsgForm(msgContainerId, 'success', res.msg || 'Guardado correctamente.');
                setTimeout(function() { window.location.href = base + '/config/empresas-sistema'; }, 1500);
            } else {
                mostrarMsgForm(msgContainerId, 'error', res.error || 'Error desconocido.');
                return Promise.reject();
            }
        })
        .catch(function(err) {
            mostrarMsgForm(msgContainerId, 'error', err.message || 'Error de conexión. Intente de nuevo.');
            return Promise.reject();
        });
    }

    var formCrear = document.getElementById('form-crear-empresa');
    if (formCrear) {
        formCrear.addEventListener('submit', function(e) {
            e.preventDefault();
            ocultarMsgForm('crear-empresa-msg');
            var btn = formCrear.querySelector('button[type="submit"]');
            var txtOrig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
            enviarFormAjax(formCrear, 'crear-empresa-msg', base + '/config/empresas-sistema-store')
                .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
        });
    }

    var formEditar = document.getElementById('form-editar-empresa');
    if (formEditar) {
        formEditar.addEventListener('submit', function(e) {
            e.preventDefault();
            ocultarMsgForm('editar-empresa-msg');
            var btn = formEditar.querySelector('button[type="submit"]');
            var txtOrig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
            enviarFormAjax(formEditar, 'editar-empresa-msg', base + '/config/empresas-sistema-update')
                .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
        });
    }

    var formCobro = document.getElementById('form-editar-empresa-cobro');
    if (formCobro) {
        formCobro.addEventListener('submit', function(e) {
            e.preventDefault();
            ocultarMsgForm('editar-cobro-msg');
            var btn = formCobro.querySelector('button[type="submit"]');
            var txtOrig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
            enviarFormAjax(formCobro, 'editar-cobro-msg', base + '/config/empresas-sistema-update')
                .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
        });
    }

    var modalCrearForm = document.getElementById('modalCrearEmpresa');
    if (modalCrearForm) {
        modalCrearForm.addEventListener('show.bs.modal', function() { ocultarMsgForm('crear-empresa-msg'); });
    }
    if (modal) {
        modal.addEventListener('show.bs.modal', function() {
            ocultarMsgForm('editar-empresa-msg');
            ocultarMsgForm('editar-cobro-msg');
        });
    }

    // Búsqueda inmediata con debounce
    (function() {
        var inputBuscar = document.getElementById('input-buscar-empresas');
        var formBuscar  = document.getElementById('form-buscar-empresas');
        if (!inputBuscar || !formBuscar) return;
        var timer = null;
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                formBuscar.submit();
            }, 400);
        });
    })();

    window.eliminarEmpresa = function(id) {
        if (!confirm('¿Está seguro de eliminar esta empresa? Esta acción no se puede deshacer y solo se permite si la empresa no tiene registros vinculados.')) return;

        var formData = new FormData();
        formData.append('id', id);

        fetch(base + '/config/empresas-sistema-delete', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok) {
                alert(res.msg || 'Empresa eliminada correctamente.');
                window.location.reload();
            } else {
                alert(res.error || 'No se pudo eliminar la empresa.');
            }
        })
        .catch(function(err) {
            alert('Error de conexión. Intente de nuevo.');
        });
    };

    // Envía (o reenvía) el acuerdo de uso de datos y el contrato de uso del
    // sistema al correo registrado de la empresa.
    // onDone: si se pasa, se llama tras un envío exitoso en vez de recargar la
    // página (se usa desde la pestaña Documentos del modal).
    window.enviarDocumentosLegales = function(id, btn, onDone) {
        if (!id) return;

        var pregunta = '¿Enviar el acuerdo de uso de datos y el contrato de uso al correo de esta empresa?';
        var seguir = (typeof Swal !== 'undefined')
            ? Swal.fire({
                title: 'Enviar documentos legales',
                text: pregunta,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                reverseButtons: true
              }).then(function(r) { return r.isConfirmed; })
            : Promise.resolve(confirm(pregunta));

        seguir.then(function(ok) {
            if (!ok) return;

            var original = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.8rem;height:.8rem;"></span>';
            }

            var fd = new FormData();
            fd.append('id', id);

            fetch(base + '/config/empresas-sistema?action=enviarDocumentosLegales', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(r) { return r.json(); })
            .then(function(res) {
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
                if (res.ok) {
                    var despues = function() {
                        if (typeof onDone === 'function') { onDone(); }
                        else { window.location.reload(); }
                    };
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: 'Enviado', text: res.msg }).then(despues);
                    } else {
                        alert(res.msg); despues();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('No se pudo enviar', res.error || 'Error desconocido.', 'error');
                    } else {
                        alert(res.error || 'No se pudo enviar.');
                    }
                }
            })
            .catch(function() {
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Error de conexión. Intente de nuevo.', 'error');
                } else {
                    alert('Error de conexión.');
                }
            });
        });
    };

})();

/* ---------------------------------------------------------
   Buscadores (typeahead) de suscripciones:
   - Empresa que controla las suscripciones → busca empresas.
   - Empresa a la que facturamos (reventa)  → busca CLIENTES de la controladora.
   Con una selección activa, Backspace/Delete limpia toda la selección.
--------------------------------------------------------- */
(function () {
    var base = '<?= $base ?>';

    function setupTypeahead(inputEl, dropdownEl, hiddenEl, fetchFn, onPick) {
        if (!inputEl || !dropdownEl || !hiddenEl) return;
        var debounceTimer;

        inputEl.addEventListener('keydown', function (e) {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                e.preventDefault();
                hiddenEl.value = '';
                inputEl.value = '';
                dropdownEl.style.display = 'none';
                dropdownEl.innerHTML = '';
                if (onPick) onPick(null);
            }
        });

        inputEl.addEventListener('input', function () {
            hiddenEl.value = '';
            if (onPick) onPick(null);
            clearTimeout(debounceTimer);
            var q = inputEl.value.trim();
            if (q.length < 1) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
            debounceTimer = setTimeout(async function () {
                var res;
                try { res = await fetchFn(q); } catch (err) {
                    dropdownEl.innerHTML = '<span class="list-group-item text-danger small py-1 px-2">Error al buscar.</span>';
                    dropdownEl.style.display = 'block';
                    return;
                }
                if (res && res.error) {
                    dropdownEl.innerHTML = '<span class="list-group-item text-muted small py-1 px-2">' + res.error + '</span>';
                    dropdownEl.style.display = 'block';
                    return;
                }
                var items = (res && res.data) || [];
                if (!items.length) {
                    dropdownEl.innerHTML = '<span class="list-group-item text-muted small py-1 px-2">Sin resultados.</span>';
                    dropdownEl.style.display = 'block';
                    return;
                }
                dropdownEl.innerHTML = items.map(function (it) {
                    var label = String(it.label).replace(/</g, '&lt;');
                    return '<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="' + it.id + '" data-label="' + label.replace(/"/g, '&quot;') + '">' + label + '</a>';
                }).join('');
                dropdownEl.style.display = 'block';
            }, 300);
        });

        dropdownEl.addEventListener('click', function (e) {
            var a = e.target.closest('a[data-id]');
            if (!a) return;
            e.preventDefault();
            hiddenEl.value = a.dataset.id;
            inputEl.value = a.dataset.label;
            dropdownEl.style.display = 'none';
            if (onPick) onPick(a.dataset.id);
        });

        document.addEventListener('click', function (e) {
            if (e.target !== inputEl && !dropdownEl.contains(e.target)) dropdownEl.style.display = 'none';
        });
    }

    async function fetchJson(url) {
        var r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return await r.json();
    }

    // Limpia el cliente facturado cuando cambia la controladora (sus clientes son otros).
    function limpiarFacturado(prefijo) {
        var fId = document.getElementById(prefijo + '-fact-id');
        var fTx = document.getElementById(prefijo + '-fact-texto');
        if (fId) fId.value = '';
        if (fTx) fTx.value = '';
    }

    ['crear', 'edit'].forEach(function (p) {
        // Controladora → empresas
        setupTypeahead(
            document.getElementById(p + '-ctrl-texto'),
            document.getElementById(p + '-ctrl-dropdown'),
            document.getElementById(p + '-ctrl-id'),
            function (q) { return fetchJson(base + '/config/empresas-sistema?action=buscarEmpresas&q=' + encodeURIComponent(q)); },
            function () { limpiarFacturado(p); }
        );
        // Facturada → CLIENTES de la controladora seleccionada
        setupTypeahead(
            document.getElementById(p + '-fact-texto'),
            document.getElementById(p + '-fact-dropdown'),
            document.getElementById(p + '-fact-id'),
            function (q) {
                var ctrl = document.getElementById(p + '-ctrl-id');
                var idEmp = ctrl ? ctrl.value : '';
                if (!idEmp) {
                    return Promise.resolve({ error: 'Seleccione primero la empresa que controla las suscripciones.' });
                }
                return fetchJson(base + '/config/empresas-sistema?action=buscarClientes&q=' + encodeURIComponent(q) + '&id_empresa=' + encodeURIComponent(idEmp));
            },
            // Al cambiar el cliente facturado, recargar sus suscripciones.
            function () {
                if (p === 'edit' && typeof window.cargarSuscripcionesCliente === 'function') {
                    window.cargarSuscripcionesCliente('');
                }
            }
        );
    });
})();
</script>
