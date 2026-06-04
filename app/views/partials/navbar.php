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
<nav class="navbar navbar-expand-lg navbar-dark bg-primary cmg-navbar-compact">
    <div class="container-fluid gap-2 align-items-center py-0">
        <!-- Brand CaMaGaRe -->
        <a class="navbar-brand text-white fw-bold text-decoration-none py-0" href="<?= $base ?>/home/index">CaMaGaRe</a>

        <!-- Toggler para móvil -->
        <button class="navbar-toggler cmg-navbar-toggler border-0 py-1 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir menú">
            <i class="bi bi-list text-white"></i>
        </button>

        <!-- Contenido colapsable -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 gap-lg-0 w-100 py-2 py-lg-0">
                 <!-- Select empresas -->
                <div class="d-flex align-items-center order-1 order-lg-2">
                    <?php $esFavorita = (int)($_SESSION['id_empresa'] ?? 0) === (int)($idEmpresaFavorita ?? 0); ?>
                    <i id="btn-favorito-global" class="bi <?= $esFavorita ? 'bi-star-fill text-warning' : 'bi-star text-white-50' ?> cursor-pointer me-2" 
                       style="font-size: 1.1rem; cursor: pointer;" 
                       title="<?= $esFavorita ? 'Esta es tu empresa favorita' : 'Marcar como empresa favorita' ?>"
                       data-id="<?= (int)($_SESSION['id_empresa'] ?? 0) ?>"
                       onclick="setFavoriteGlobal(this)"></i>

                    <form id="form-cambiar-empresa" method="POST" action="<?= $base ?>/empresa/setEmpresa" class="cmg-empresas-form cmg-empresas-dropdown-wrap">
                        <input type="hidden" name="id_usuario" value="<?= (int) ($_SESSION['id_usuario'] ?? 0) ?>">
                        <input type="hidden" name="id_empresa" id="input-id-empresa" value="<?= (int) ($_SESSION['id_empresa'] ?? 0) ?>">
                        <input type="hidden" name="ruc_empresa" id="input-ruc-empresa" value="<?= htmlspecialchars($_SESSION['ruc_empresa'] ?? '') ?>">
                        <input type="text" id="input-empresas" class="form-control cmg-empresas-input" autocomplete="off"
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

                <!-- Label mensajes -->
                <span class="navbar-text text-white-50 text-center text-lg-center small order-2 order-lg-3 flex-grow-1" id="navbar-mensajes">&nbsp;</span>

                <!-- Buscador de Módulos -->
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
                <div class="cmg-nav-search-wrap order-3 order-lg-4">
                    <input type="text" id="cmg-nav-search" class="form-control cmg-nav-search-input shadow-none" placeholder="Buscar módulo..." autocomplete="off">
                    <i class="bi bi-search cmg-nav-search-icon"></i>
                    <div id="cmg-nav-search-results" class="cmg-nav-search-results">
                        <!-- Resultados AJAX local -->
                    </div>
                </div>

                <!-- Usuario, config, logout -->
                <div class="d-flex align-items-center justify-content-center justify-content-lg-end gap-2 order-4 order-lg-5">
                    <a href="<?= $base ?>/config/tareas-obligaciones" class="text-white text-decoration-none position-relative me-2" title="Tareas pendientes/vencidas" style="display: inline-block;" data-navbar-link="true">
                        <i class="bi bi-bell-fill" style="font-size: 1.1rem;"></i>
                        <span id="tareas-alertas-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="pedidos-pendientes-icon" href="<?= $base ?>/modulos/pedidos" class="text-white text-decoration-none position-relative me-2 d-none" title="Pedidos pendientes">
                        <i class="bi bi-cart3" style="font-size: 1.1rem;"></i>
                        <span id="pedidos-pendientes-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="facturas-borrador-icon" href="<?= $base ?>/modulos/factura-venta" class="text-white text-decoration-none position-relative me-2 d-none" title="Facturas en borrador">
                        <i class="bi bi-receipt" style="font-size: 1.1rem;"></i>
                        <span id="facturas-borrador-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="liquidaciones-borrador-icon" href="<?= $base ?>/modulos/liquidacion-compra" class="text-white text-decoration-none position-relative me-2 d-none" title="Liquidaciones de compra en borrador">
                        <i class="bi bi-file-earmark-text" style="font-size: 1.1rem;"></i>
                        <span id="liquidaciones-borrador-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="retenciones-compras-borrador-icon" href="<?= $base ?>/modulos/retenciones_compras" class="text-white text-decoration-none position-relative me-2 d-none" title="Retenciones en compras en borrador">
                        <i class="bi bi-percent" style="font-size: 1.1rem;"></i>
                        <span id="retenciones-compras-borrador-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="notas-credito-borrador-icon" href="<?= $base ?>/modulos/notas_credito" class="text-white text-decoration-none position-relative me-2 d-none" title="Notas de crédito en borrador">
                        <i class="bi bi-file-earmark-minus" style="font-size: 1.1rem;"></i>
                        <span id="notas-credito-borrador-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="guias-remision-borrador-icon" href="<?= $base ?>/modulos/guias_remision" class="text-white text-decoration-none position-relative me-2 d-none" title="Guías de remisión en borrador">
                        <i class="bi bi-truck" style="font-size: 1.1rem;"></i>
                        <span id="guias-remision-borrador-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="factura-express-pendientes-icon" href="<?= $base ?>/modulos/factura-express-solicitudes" class="text-white text-decoration-none position-relative me-2 d-none" title="Solicitudes Factura Express pendientes">
                        <i class="bi bi-qr-code" style="font-size: 1.1rem;"></i>
                        <span id="factura-express-pendientes-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="ordenes-compra-borrador-icon" href="<?= $base ?>/modulos/ordenes-compra" class="text-white text-decoration-none position-relative me-2 d-none" title="Órdenes de compra en borrador">
                        <i class="bi bi-cart-plus" style="font-size: 1.1rem;"></i>
                        <span id="ordenes-compra-borrador-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <a id="whatsapp-unread-icon" href="<?= $base ?>/modulos/whatsapp-chat" class="text-white text-decoration-none position-relative me-3 d-none" title="Mensajes de WhatsApp sin leer">
                        <i class="bi bi-whatsapp" style="font-size: 1.1rem;"></i>
                        <span id="whatsapp-unread-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger text-white" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                            0
                        </span>
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
        </div>
    </div>
</nav>

<script>
    window.updateGuiasRemisionBorradorBadge = async function() {
        const icon  = document.getElementById('guias-remision-borrador-icon');
        const badge = document.getElementById('guias-remision-borrador-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/guias_remision/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    window.updateNotasCreditoBorradorBadge = async function() {
        const icon  = document.getElementById('notas-credito-borrador-icon');
        const badge = document.getElementById('notas-credito-borrador-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/notas_credito/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    window.updateRetencionesComprasBorradorBadge = async function() {
        const icon  = document.getElementById('retenciones-compras-borrador-icon');
        const badge = document.getElementById('retenciones-compras-borrador-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/retenciones_compras/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    window.updateLiquidacionesBorradorBadge = async function() {
        const icon  = document.getElementById('liquidaciones-borrador-icon');
        const badge = document.getElementById('liquidaciones-borrador-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/liquidacion-compra/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    window.updateFacturasBorradorBadge = async function() {
        const icon  = document.getElementById('facturas-borrador-icon');
        const badge = document.getElementById('facturas-borrador-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/factura-venta/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    window.updateOrdenesCompraBorradorBadge = async function() {
        const icon  = document.getElementById('ordenes-compra-borrador-icon');
        const badge = document.getElementById('ordenes-compra-borrador-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/ordenes-compra/countBorradoresAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    window.updateFacturaExpressPendientesBadge = async function() {
        const icon  = document.getElementById('factura-express-pendientes-icon');
        const badge = document.getElementById('factura-express-pendientes-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/factura-express-solicitudes/countPendientesAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    window.updatePedidosPendientesBadge = async function() {
        const icon  = document.getElementById('pedidos-pendientes-icon');
        const badge = document.getElementById('pedidos-pendientes-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/pedidos/countPendientesAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    window.updateTareasBadge = async function() {
        const badge = document.getElementById('tareas-alertas-badge');
        if (!badge) return;
        try {
            const response = await fetch('<?= $base ?>/config/tareas-obligaciones?action=tareas-alertas-count');
            const data = await response.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        } catch (error) {
            console.error('Error al obtener alertas de tareas:', error);
        }
    };

    window.updateWhatsappUnreadBadge = async function() {
        const icon  = document.getElementById('whatsapp-unread-icon');
        const badge = document.getElementById('whatsapp-unread-badge');
        if (!icon || !badge) return;
        try {
            const resp = await fetch('<?= $base ?>/modulos/whatsapp-chat/countUnreadAjax');
            const data = await resp.json();
            if (data.ok && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                icon.classList.remove('d-none');
            } else {
                icon.classList.add('d-none');
            }
        } catch (e) {}
    };

    document.addEventListener('DOMContentLoaded', function() {
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