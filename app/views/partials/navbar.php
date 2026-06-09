<?php
$empresas = $empresas ?? [];
$nombre = $nombre ?? '';
$base = BASE_URL;
$idEmpresaSel = (int) ($_SESSION['id_empresa'] ?? 0);
$empresaSel = null;
foreach ($empresas as $e) {
    if ((int)($e['id_empresa'] ?? 0) === $idEmpresaSel) {
        $empresaSel = $e;
        break;
    }
}
$valorInicial = $empresaSel ? (($empresaSel['establecimiento'] ?? '001') . ' - ' . (!empty($empresaSel['nombre_comercial']) ? $empresaSel['nombre_comercial'] : $empresaSel['nombre'])) : '';
?>
<script>
    window.CMS_CONFIG = {
        baseUrl: '<?= $base ?>',
        idEmpresa: <?= (int)($_SESSION['id_empresa'] ?? 0) ?>,
        idUsuario: <?= (int)($_SESSION['id_usuario'] ?? 0) ?>,
        favUrl: '<?= $base ?>/preferencias/guardarEmpresaFavoritaAjax'
    };

    function setFavoriteGlobal(icon) {
        if (icon.classList.contains('cmg-loading')) return;

        // Validar que haya una empresa seleccionada
        var inputEmpresa = document.getElementById('input-empresas');
        var idEmpresaInput = document.getElementById('input-id-empresa');

        if (!inputEmpresa || !inputEmpresa.value || inputEmpresa.value.trim() === '') {
            Swal.fire({
                icon: 'warning',
                title: 'Sin empresa seleccionada',
                text: 'Por favor, selecciona una empresa antes de marcar como favorita.',
                confirmButtonText: 'Aceptar'
            });
            return;
        }

        var idEmpresa = idEmpresaInput ? idEmpresaInput.value : icon.getAttribute('data-id');
        if (!idEmpresa || idEmpresa === '0') {
            Swal.fire({
                icon: 'warning',
                title: 'Sin empresa seleccionada',
                text: 'Por favor, selecciona una empresa válida.',
                confirmButtonText: 'Aceptar'
            });
            return;
        }

        console.log("Iniciando setFavoriteGlobal para empresa:", idEmpresa);

        // Guardar estado original
        var originalClasses = icon.className;
        var originalTitle = icon.title;

        // Optimista
        icon.classList.remove('bi-star', 'text-white-50');
        icon.classList.add('bi-star-fill', 'text-warning', 'cmg-loading', 'opacity-50');
        icon.title = 'Guardando...';

        var params = new URLSearchParams();
        params.append('id_empresa', idEmpresa);

        var url = window.CMS_CONFIG.favUrl;

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        })
        .then(function(res) {
            if (!res.ok) throw new Error("Status " + res.status);
            return res.json();
        })
        .then(function(data) {
            icon.classList.remove('cmg-loading', 'opacity-50');
            if (data.ok) {
                // Actualizar visualmente el botón para mostrar que es favorito
                icon.classList.remove('bi-star', 'text-white-50');
                icon.classList.add('bi-star-fill', 'text-warning');
                icon.title = 'Esta es tu empresa favorita';

                Swal.fire({
                    icon: 'success',
                    title: '¡Empresa guardada!',
                    text: 'La empresa ha sido marcada como favorita.',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                icon.className = originalClasses;
                icon.title = originalTitle;
                Swal.fire({
                    icon: 'error',
                    title: 'Error del sistema',
                    text: data.error || 'No se pudo guardar la empresa como favorita.',
                    confirmButtonText: 'Aceptar'
                });
            }
        })
        .catch(function(err) {
            icon.className = originalClasses;
            icon.title = originalTitle;
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: err.message || 'No se pudo conectar con el servidor.',
                confirmButtonText: 'Aceptar'
            });
        });
    }
</script>
<style>
    
    /* Agrupación de íconos en móvil */
    .cmg-mobile-icons-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        text-align: center;
    }
    .cmg-mobile-icons-grid a {
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 0.75rem;
        color: var(--bs-primary) !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        border: 1px solid #dee2e6;
        position: relative;
    }
    .cmg-mobile-icons-grid a i {
        font-size: 1.5rem !important;
        margin-bottom: 0.25rem;
    }
    .cmg-mobile-icons-grid a span.badge {
        font-size: 0.7rem !important;
        top: 5px !important;
        right: 5px !important;
        transform: none !important;
        left: auto !important;
    }
    .cmg-offcanvas-menu-accordion .accordion-button {
        padding: 1rem;
        font-weight: 500;
        color: var(--bs-dark);
        background-color: transparent;
        box-shadow: none;
    }
    .cmg-offcanvas-menu-accordion .accordion-button:not(.collapsed) {
        color: var(--bs-primary);
        background-color: rgba(var(--bs-primary-rgb), 0.05);
    }
    .cmg-offcanvas-menu-accordion .accordion-body {
        padding: 0;
    }
    .cmg-offcanvas-menu-accordion .list-group-item {
        border: none;
        border-radius: 0;
        padding: 0.75rem 1rem 0.75rem 3rem;
        color: var(--bs-secondary);
        background-color: #f8f9fa;
    }
    .cmg-offcanvas-menu-accordion .list-group-item:hover {
        background-color: #e9ecef;
        color: var(--bs-primary);
    }
</style>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary cmg-navbar-compact position-relative">
    <div class="container-fluid gap-2 align-items-center py-1 d-flex flex-wrap flex-lg-nowrap">
        <!-- 1. Brand CaMaGaRe -->
        <a class="navbar-brand text-white fw-bold text-decoration-none py-0 order-0 m-0" href="<?= $base ?>/home/index">CaMaGaRe</a>

        <!-- 2. Select empresas (Desktop: order 1, Mobile: order 1 flex-grow) -->
        <div class="d-flex align-items-center order-1 me-lg-auto flex-grow-1 flex-lg-grow-0" style="min-width: 0;">
            <?php $esFavorita = (int)($_SESSION['id_empresa'] ?? 0) === (int)($idEmpresaFavorita ?? 0); ?>
            <i id="btn-favorito-global" class="bi <?= $esFavorita ? 'bi-star-fill text-warning' : 'bi-star text-white-50' ?> cursor-pointer me-2" 
               style="font-size: 1.1rem; cursor: pointer;" 
               title="<?= $esFavorita ? 'Esta es tu empresa favorita' : 'Marcar como empresa favorita' ?>"
               data-id="<?= (int)($_SESSION['id_empresa'] ?? 0) ?>"
               onclick="setFavoriteGlobal(this)"></i>

            <form id="form-cambiar-empresa" method="POST" action="<?= $base ?>/empresa/setEmpresa" class="cmg-empresas-form cmg-empresas-dropdown-wrap flex-grow-1">
                <input type="hidden" name="id_usuario" value="<?= (int) ($_SESSION['id_usuario'] ?? 0) ?>">
                <input type="hidden" name="id_empresa" id="input-id-empresa" value="<?= (int) ($_SESSION['id_empresa'] ?? 0) ?>">
                <input type="hidden" name="ruc_empresa" id="input-ruc-empresa" value="<?= htmlspecialchars($_SESSION['ruc_empresa'] ?? '') ?>">
                <input type="text" id="input-empresas" class="form-control cmg-empresas-input w-100" autocomplete="off"
                    placeholder="Seleccionar empresa..."
                    value="<?= htmlspecialchars($valorInicial) ?>"
                    data-options="<?= htmlspecialchars(json_encode(array_map(function ($e) {
                                        $nombre = !empty($e['nombre_comercial']) ? $e['nombre_comercial'] : ($e['nombre'] ?? $e['ruc'] ?? '');
                                        $texto = ($e['establecimiento'] ?? '001') . ' - ' . $nombre;
                                        return ['id' => (int)$e['id_empresa'], 'text' => $texto, 'ruc' => $e['ruc'] ?? ''];
                                    }, $empresas))) ?>">
                <div class="cmg-empresas-dropdown" id="dropdown-empresas" role="listbox">
                    <?php foreach ($empresas as $emp):
                        $nombreEmp = !empty($emp['nombre_comercial']) ? $emp['nombre_comercial'] : ($emp['nombre'] ?? $emp['ruc'] ?? '');
                        $textoEmp = ($emp['establecimiento'] ?? '001') . ' - ' . $nombreEmp;
                    ?>
                        <div class="cmg-empresas-dropdown-item" role="option" data-id="<?= (int)($emp['id_empresa'] ?? 0) ?>" data-text="<?= htmlspecialchars($textoEmp) ?>" data-ruc="<?= htmlspecialchars($emp['ruc'] ?? '') ?>">
                            <?= htmlspecialchars($textoEmp) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>

        <!-- Fila extra en móvil -->
        <div class="w-100 d-lg-none order-2 m-0 p-0"></div>

        <!-- 3. Buscador de Módulos (Mobile: order 3, Desktop: order 2) -->
        <?php
        $flatModulos = [];
        $menuModulosParaSearch = $menuModulos ?? [];
        foreach ($menuModulosParaSearch as $mod) {
            if (!empty($mod['submodulos'])) {
                foreach ($mod['submodulos'] as $sub) {
                    $href = $sub['ruta'] ?? '#';
                    if ($href !== '#' && !preg_match('#^https?://#', $href) && !str_starts_with($href, '/')) {
                        $href = rtrim($base, '/') . '/' . ltrim($href, '/');
                    }
                    $flatModulos[] = [
                        'title' => $sub['nombre_submodulo'],
                        'path' => $mod['nombre_modulo'],
                        'icon' => iconoClase($sub['icono_submodulo'] ?? $mod['icono_modulo'] ?? ''),
                        'url' => $href
                    ];
                }
            }
        }
        ?>
        <style>
            .cmg-mobile-row-2 { width: 100%; margin-top: 0.5rem; }
            @media (min-width: 992px) { .cmg-mobile-row-2 { width: auto; margin-top: 0; } }
        </style>
        <div class="d-flex align-items-center order-3 order-lg-2 cmg-mobile-row-2">
            <div class="cmg-nav-search-wrap d-flex align-items-center flex-grow-1 flex-lg-grow-0 position-relative" id="cmg-nav-search-wrap">
                <input type="text" id="cmg-nav-search" class="form-control cmg-nav-search-input shadow-none w-100" placeholder="Buscar módulo..." autocomplete="off">
                <i class="bi bi-search cmg-nav-search-icon d-none d-lg-block position-absolute" style="right: 10px;"></i>
                <div id="cmg-nav-search-results" class="cmg-nav-search-results" style="top: 100%; right: 0; left: 0;"></div>
            </div>

            <!-- 4. Toggler para móvil (Mobile: order 4, Desktop: hidden) -->
            <button class="navbar-toggler cmg-navbar-toggler border-0 py-1 px-2 ms-2 d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMobileMenu" aria-controls="offcanvasMobileMenu" aria-expanded="false" aria-label="Abrir menú">
                <i class="bi bi-list text-white" style="font-size: 1.6rem;"></i>
            </button>
        </div>

        <!-- 5. Iconos, Usuario, Config, Logout (Desktop: order 5, Mobile: hidden) -->
        <div class="d-none d-lg-flex flex-row align-items-center gap-2 order-lg-5 ms-lg-3">
            <span class="navbar-text text-white-50 small me-2" id="navbar-mensajes">&nbsp;</span>
            <a id="tareas-alertas-link" href="<?= $base ?>/config/tareas-obligaciones" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update tareas-alertas-link" title="Tareas pendientes/vencidas" data-navbar-link="true">
                <i class="bi bi-bell-fill" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger tareas-alertas-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/pedidos" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update pedidos-pendientes-icon" title="Pedidos pendientes">
                <i class="bi bi-cart3" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark pedidos-pendientes-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/factura-venta" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update facturas-borrador-icon" title="Facturas en borrador">
                <i class="bi bi-receipt" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark facturas-borrador-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/liquidacion-compra" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update liquidaciones-borrador-icon" title="Liquidaciones en borrador">
                <i class="bi bi-file-earmark-text" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark liquidaciones-borrador-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/retenciones_compras" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update retenciones-compras-borrador-icon" title="Retenciones en borrador">
                <i class="bi bi-percent" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark retenciones-compras-borrador-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/notas_credito" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update notas-credito-borrador-icon" title="Notas crédito borrador">
                <i class="bi bi-file-earmark-minus" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark notas-credito-borrador-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/guias_remision" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update guias-remision-borrador-icon" title="Guías en borrador">
                <i class="bi bi-truck" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark guias-remision-borrador-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/factura-express-solicitudes" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update factura-express-pendientes-icon" title="Solicitudes Factura Express">
                <i class="bi bi-qr-code" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark factura-express-pendientes-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/ordenes-compra" class="text-white text-decoration-none position-relative me-2 d-none cmg-icon-update ordenes-compra-borrador-icon" title="Órdenes borrador">
                <i class="bi bi-cart-plus" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark ordenes-compra-borrador-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            <a href="<?= $base ?>/modulos/whatsapp-chat" class="text-white text-decoration-none position-relative me-3 d-none cmg-icon-update whatsapp-unread-icon" title="WhatsApp sin leer">
                <i class="bi bi-whatsapp" style="font-size: 1.1rem;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger text-white whatsapp-unread-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em;">0</span>
            </a>
            
            <a href="<?= $base ?>/perfil" class="text-white text-decoration-none" style="font-size:0.8rem" title="Mi perfil"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($nombre) ?></a>
            <a href="<?= $base ?>/config" class="btn btn-outline-light btn-sm cmg-navbar-btn" title="Configuración">
                <i class="bi bi-gear-fill"></i>
            </a>
            <a href="<?= rtrim($base ?? BASE_URL ?? '', '/') ?>/auth/logout" class="btn btn-outline-light btn-sm cmg-navbar-btn" title="Cerrar sesión">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<!-- Offcanvas Móvil (Menu Lateral) -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="offcanvasMobileMenu" aria-labelledby="offcanvasMobileMenuLabel">
    <div class="offcanvas-header bg-primary text-white align-items-center py-3">
        <h5 class="offcanvas-title fw-bold m-0" id="offcanvasMobileMenuLabel">
            <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($nombre) ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body bg-light p-0" style="min-height: 0; -webkit-overflow-scrolling: touch;">
    
        <!-- Opciones de Empresa y Acceso Rapido -->
        <div class="p-3 bg-white border-bottom">
            <label class="form-label small fw-bold text-muted mb-1">Empresa actual:</label>
            <div class="d-flex align-items-center mb-3">
                <i class="bi <?= $esFavorita ? 'bi-star-fill text-warning' : 'bi-star text-secondary' ?> me-2" style="font-size: 1.2rem;"></i>
                <div class="flex-grow-1 text-truncate fw-bold text-primary" style="font-size: 0.9rem;">
                    <?= htmlspecialchars($valorInicial) ?>
                </div>
            </div>

            <label class="form-label small fw-bold text-muted mb-2">Notificaciones y Tareas</label>
            <div class="cmg-mobile-icons-grid mb-3">
                <a class="cmg-icon-update tareas-alertas-link d-none" href="<?= $base ?>/config/tareas-obligaciones">
                    <i class="bi bi-bell-fill"></i>
                    <span class="position-absolute badge rounded-pill bg-danger tareas-alertas-badge">0</span>
                    <small>Tareas</small>
                </a>
                <a class="cmg-icon-update pedidos-pendientes-icon d-none" href="<?= $base ?>/modulos/pedidos">
                    <i class="bi bi-cart3"></i>
                    <span class="position-absolute badge rounded-pill bg-warning text-dark pedidos-pendientes-badge">0</span>
                    <small>Pedidos</small>
                </a>
                <a class="cmg-icon-update facturas-borrador-icon d-none" href="<?= $base ?>/modulos/factura-venta">
                    <i class="bi bi-receipt"></i>
                    <span class="position-absolute badge rounded-pill bg-warning text-dark facturas-borrador-badge">0</span>
                    <small>Facturas</small>
                </a>
                <a class="cmg-icon-update liquidaciones-borrador-icon d-none" href="<?= $base ?>/modulos/liquidacion-compra">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="position-absolute badge rounded-pill bg-warning text-dark liquidaciones-borrador-badge">0</span>
                    <small>Liquida.</small>
                </a>
                <a class="cmg-icon-update retenciones-compras-borrador-icon d-none" href="<?= $base ?>/modulos/retenciones_compras">
                    <i class="bi bi-percent"></i>
                    <span class="position-absolute badge rounded-pill bg-warning text-dark retenciones-compras-borrador-badge">0</span>
                    <small>Reten.</small>
                </a>
                <a class="cmg-icon-update notas-credito-borrador-icon d-none" href="<?= $base ?>/modulos/notas_credito">
                    <i class="bi bi-file-earmark-minus"></i>
                    <span class="position-absolute badge rounded-pill bg-warning text-dark notas-credito-borrador-badge">0</span>
                    <small>N/C</small>
                </a>
                <a class="cmg-icon-update guias-remision-borrador-icon d-none" href="<?= $base ?>/modulos/guias_remision">
                    <i class="bi bi-truck"></i>
                    <span class="position-absolute badge rounded-pill bg-warning text-dark guias-remision-borrador-badge">0</span>
                    <small>Guías</small>
                </a>
                <a class="cmg-icon-update factura-express-pendientes-icon d-none" href="<?= $base ?>/modulos/factura-express-solicitudes">
                    <i class="bi bi-qr-code"></i>
                    <span class="position-absolute badge rounded-pill bg-warning text-dark factura-express-pendientes-badge">0</span>
                    <small>Express</small>
                </a>
                <a class="cmg-icon-update ordenes-compra-borrador-icon d-none" href="<?= $base ?>/modulos/ordenes-compra">
                    <i class="bi bi-cart-plus"></i>
                    <span class="position-absolute badge rounded-pill bg-warning text-dark ordenes-compra-borrador-badge">0</span>
                    <small>Órdenes</small>
                </a>
                <a class="cmg-icon-update whatsapp-unread-icon d-none" href="<?= $base ?>/modulos/whatsapp-chat">
                    <i class="bi bi-whatsapp"></i>
                    <span class="position-absolute badge rounded-pill bg-danger text-white whatsapp-unread-badge">0</span>
                    <small>WhatsApp</small>
                </a>
            </div>

            <div class="d-flex gap-2">
                <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm flex-grow-1">
                    <i class="bi bi-gear-fill me-1"></i>Ajustes
                </a>
                <a href="<?= rtrim($base ?? BASE_URL ?? '', '/') ?>/auth/logout" class="btn btn-outline-danger btn-sm flex-grow-1">
                    <i class="bi bi-box-arrow-right me-1"></i>Salir
                </a>
            </div>
        </div>

        <!-- Módulos Móvil (Acordeón) -->
        <div class="bg-white">
            <div class="accordion accordion-flush cmg-offcanvas-menu-accordion" id="accordionMobileMenu">
                <?php 
                $modulosMovil = array_values(array_filter($menuModulos ?? [], fn($m) => !empty($m['submodulos'] ?? [])));
                foreach ($modulosMovil as $index => $mod): 
                    $headingId = "flush-heading" . $index;
                    $collapseId = "flush-collapse" . $index;
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="<?= $headingId ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
                            <i class="<?= htmlspecialchars(iconoClase($mod['icono_modulo'] ?? '')) ?> me-2"></i>
                            <?= htmlspecialchars($mod['nombre_modulo'] ?? '') ?>
                        </button>
                    </h2>
                    <div id="<?= $collapseId ?>" class="accordion-collapse collapse" aria-labelledby="<?= $headingId ?>" data-bs-parent="#accordionMobileMenu">
                        <div class="accordion-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($mod['submodulos'] as $sub): 
                                    $href = $sub['ruta'] ?? '#';
                                    if ($href !== '#' && !preg_match('#^https?://#', $href) && !str_starts_with($href, '/')) {
                                        $href = rtrim($base, '/') . '/' . ltrim($href, '/');
                                    }
                                ?>
                                <a href="<?= htmlspecialchars($href) ?>" class="list-group-item list-group-item-action">
                                    <i class="<?= htmlspecialchars(iconoClase($sub['icono_submodulo'] ?? '')) ?> me-2"></i>
                                    <?= htmlspecialchars($sub['nombre_submodulo'] ?? '') ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Safari/iOS FIX: Mover el offcanvas al final del body para escapar 
    // de cualquier restricción de position: sticky del header padre.
    document.addEventListener("DOMContentLoaded", function() {
        var offcanvasMenu = document.getElementById('offcanvasMobileMenu');
        if (offcanvasMenu && offcanvasMenu.parentNode !== document.body) {
            document.body.appendChild(offcanvasMenu);
        }
    });
    window.updateGuiasRemisionBorradorBadge = async function() {
        const icons = document.querySelectorAll('.guias-remision-borrador-icon');
        const badges = document.querySelectorAll('.guias-remision-borrador-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/guias_remision/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    window.updateNotasCreditoBorradorBadge = async function() {
        const icons = document.querySelectorAll('.notas-credito-borrador-icon');
        const badges = document.querySelectorAll('.notas-credito-borrador-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/notas_credito/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    window.updateRetencionesComprasBorradorBadge = async function() {
        const icons = document.querySelectorAll('.retenciones-compras-borrador-icon');
        const badges = document.querySelectorAll('.retenciones-compras-borrador-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/retenciones_compras/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    window.updateLiquidacionesBorradorBadge = async function() {
        const icons = document.querySelectorAll('.liquidaciones-borrador-icon');
        const badges = document.querySelectorAll('.liquidaciones-borrador-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/liquidacion-compra/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    window.updateFacturasBorradorBadge = async function() {
        const icons = document.querySelectorAll('.facturas-borrador-icon');
        const badges = document.querySelectorAll('.facturas-borrador-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/factura-venta/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    window.updateOrdenesCompraBorradorBadge = async function() {
        const icons = document.querySelectorAll('.ordenes-compra-borrador-icon');
        const badges = document.querySelectorAll('.ordenes-compra-borrador-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/ordenes-compra/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    window.updateFacturaExpressPendientesBadge = async function() {
        const icons = document.querySelectorAll('.factura-express-pendientes-icon');
        const badges = document.querySelectorAll('.factura-express-pendientes-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/factura-express-solicitudes/countPendientesAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    window.updatePedidosPendientesBadge = async function() {
        const icons = document.querySelectorAll('.pedidos-pendientes-icon');
        const badges = document.querySelectorAll('.pedidos-pendientes-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/pedidos/countPendientesAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    window.updateTareasBadge = async function() {
        const links = document.querySelectorAll('.tareas-alertas-link');
        const badges = document.querySelectorAll('.tareas-alertas-badge');
        if (badges.length === 0 || links.length === 0) return;
        try {
            const response = await fetch('<?= $base ?>/config/tareas-obligaciones?action=tareas-alertas-count');
            const data = await response.json();
            if (data.ok && data.count > 0) {
                // Mostrar el enlace y actualizar el badge
                links.forEach(l => l.classList.remove('d-none'));
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
            } else {
                // Ocultar el enlace cuando no hay avisos
                links.forEach(l => l.classList.add('d-none'));
            }
        } catch (error) {
            console.error('Error al obtener alertas de tareas:', error);
        }
    };

    window.updateWhatsappUnreadBadge = async function() {
        const icons = document.querySelectorAll('.whatsapp-unread-icon');
        const badges = document.querySelectorAll('.whatsapp-unread-badge');
        if (icons.length === 0 || badges.length === 0) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/whatsapp-chat/countUnreadAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badges.forEach(b => b.textContent = data.count > 99 ? '99+' : data.count);
                icons.forEach(i => i.classList.remove('d-none'));
            } else {
                icons.forEach(i => i.classList.add('d-none'));
            }
        } catch (e) {}
    };

    // Actualizar botón de favorito cuando cambia la empresa seleccionada
    function actualizarBtnFavorito() {
        var idEmpresaActual = document.getElementById('input-id-empresa');
        var idEmpresaFavorita = <?= (int)($idEmpresaFavorita ?? 0) ?>;
        var btnFavorito = document.getElementById('btn-favorito-global');

        if (idEmpresaActual && btnFavorito) {
            var esFavorita = parseInt(idEmpresaActual.value) === idEmpresaFavorita;

            if (esFavorita) {
                btnFavorito.classList.remove('bi-star', 'text-white-50');
                btnFavorito.classList.add('bi-star-fill', 'text-warning');
                btnFavorito.title = 'Esta es tu empresa favorita';
            } else {
                btnFavorito.classList.remove('bi-star-fill', 'text-warning');
                btnFavorito.classList.add('bi-star', 'text-white-50');
                btnFavorito.title = 'Marcar como empresa favorita';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Actualizar favorito cuando se selecciona una empresa
        var inputEmpresas = document.getElementById('input-empresas');
        if (inputEmpresas) {
            inputEmpresas.addEventListener('change', actualizarBtnFavorito);
        }

        // Actualizar al cargar la página
        actualizarBtnFavorito();

        window.updateTareasBadge();
        window.updatePedidosPendientesBadge();
        window.updateFacturasBorradorBadge();
        window.updateLiquidacionesBorradorBadge();
        window.updateRetencionesComprasBorradorBadge();
        window.updateNotasCreditoBorradorBadge();
        window.updateGuiasRemisionBorradorBadge();
        window.updateFacturaExpressPendientesBadge();
        window.updateOrdenesCompraBorradorBadge();
        window.updateWhatsappUnreadBadge();
        // Actualizar cada 1 minuto
        setInterval(window.updateTareasBadge, 60000);
        setInterval(window.updatePedidosPendientesBadge, 60000);
        setInterval(window.updateFacturasBorradorBadge, 60000);
        setInterval(window.updateLiquidacionesBorradorBadge, 60000);
        setInterval(window.updateRetencionesComprasBorradorBadge, 60000);
        setInterval(window.updateNotasCreditoBorradorBadge, 60000);
        setInterval(window.updateGuiasRemisionBorradorBadge, 60000);
        setInterval(window.updateFacturaExpressPendientesBadge, 60000);
        setInterval(window.updateOrdenesCompraBorradorBadge, 60000);
        setInterval(window.updateWhatsappUnreadBadge, 15000); // 15s para Whatsapp

                const btnMobileSearchToggle = document.getElementById('btn-mobile-search-toggle');
        const searchWrap = document.getElementById('cmg-nav-search-wrap');
        const searchInputEl = document.getElementById('cmg-nav-search');
        if (btnMobileSearchToggle && searchWrap) {
            btnMobileSearchToggle.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevenir que el click se propague
                searchWrap.classList.toggle('expanded');
                if (searchWrap.classList.contains('expanded')) {
                    setTimeout(() => searchInputEl.focus(), 100);
                } else {
                    const searchResults = document.getElementById('cmg-nav-search-results');
                    if(searchResults) searchResults.classList.remove('show');
                }
            });
            // Ocultar buscador si se hace clic fuera de él en móvil
            document.addEventListener('click', function(e) {
                if (window.innerWidth < 992 && searchWrap.classList.contains('expanded')) {
                    if (!searchWrap.contains(e.target)) {
                        searchWrap.classList.remove('expanded');
                        const searchResults = document.getElementById('cmg-nav-search-results');
                        if(searchResults) searchResults.classList.remove('show');
                    }
                }
            });
        }

                // Lógica del buscador de módulos
        const searchInput = document.getElementById('cmg-nav-search');
        const searchResults = document.getElementById('cmg-nav-search-results');
        const portal = document.getElementById('cmg-dropdown-portal');
        const modulos = <?= json_encode($flatModulos) ?>;

        if (searchInput && searchResults && portal) {
            const updatePosition = () => {
                const rect = searchInput.getBoundingClientRect();
                const width = window.innerWidth < 992 ? (window.innerWidth - 20) : 300;

                searchResults.style.top = (rect.bottom + 6) + 'px';
                searchResults.style.backgroundColor = '#ffffff'; // Asegurar fondo blanco sólido
                searchResults.style.opacity = '1';

                if (window.innerWidth < 992) {
                    searchResults.style.left = '10px';
                    searchResults.style.width = width + 'px';
                } else {
                    // Alineado al input (aprox 280-300px)
                    searchResults.style.left = (rect.right - 300) + 'px';
                    searchResults.style.width = '300px';
                }
            };

            const normalize = (str) => {
                return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
            };

            searchInput.addEventListener('input', function() {
                const queryStr = this.value.trim();
                if (queryStr.length === 0) {
                    searchResults.innerHTML = '';
                    searchResults.classList.remove('show');
                    return;
                }

                const queryWords = normalize(queryStr).split(/\s+/);
                
                const filtered = modulos.filter(m => {
                    const title = normalize(m.title);
                    const path = normalize(m.path);
                    const combined = title + ' ' + path;
                    
                    // Cada palabra de la consulta debe estar presente en el título o ruta
                    return queryWords.every(word => combined.includes(word));
                }).slice(0, 10);

                if (filtered.length === 0) {
                    searchResults.innerHTML = '<div class="p-3 text-center text-muted small bg-white" style="background:#fff !important; opacity:1 !important;">No se encontraron resultados</div>';
                } else {
                    searchResults.innerHTML = filtered.map(m => `
                    <a href="${m.url}" class="cmg-search-result-item bg-white" style="background:#fff !important; opacity:1 !important;">
                        <div class="cmg-search-result-icon">
                            <i class="${m.icon}"></i>
                        </div>
                        <div class="cmg-search-result-content">
                            <span class="cmg-search-result-title">${m.title}</span>
                            <span class="cmg-search-result-path">${m.path}</span>
                        </div>
                    </a>
                `).join('');
                }

                if (!portal.contains(searchResults)) {
                    portal.appendChild(searchResults);
                }
                updatePosition();
                searchResults.classList.add('show');
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.remove('show');
                }
            });

            window.addEventListener('resize', () => {
                if (searchResults.classList.contains('show')) updatePosition();
            });
            window.addEventListener('scroll', () => {
                if (searchResults.classList.contains('show')) updatePosition();
            }, true);

            searchInput.addEventListener('keydown', function(e) {
                const items = searchResults.querySelectorAll('.cmg-search-result-item');
                const current = searchResults.querySelector('.cmg-search-result-item.active');
                let index = Array.from(items).indexOf(current);

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const next = items[index + 1] || items[0];
                    items.forEach(i => i.classList.remove('active'));
                    if (next) next.classList.add('active');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prev = items[index - 1] || items[items.length - 1];
                    items.forEach(i => i.classList.remove('active'));
                    if (prev) prev.classList.add('active');
                } else if (e.key === 'Enter') {
                    const active = searchResults.querySelector('.cmg-search-result-item.active');
                    if (active) {
                        e.preventDefault();
                        window.location.href = active.href;
                    } else if (items.length > 0) {
                        window.location.href = items[0].href;
                    }
                } else if (e.key === 'Escape') {
                    searchResults.classList.remove('show');
                    searchInput.blur();
                }
            });
        }
    });
</script>