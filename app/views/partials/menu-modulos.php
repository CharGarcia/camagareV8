<?php
/** @var array $menuModulos */
/** @var string $base */
/** @var string $tipoMenu 'horizontal' | 'horizontal_filas' (Controller::getLayoutData) */
$menuModulos = $menuModulos ?? [];
$base = $base ?? BASE_URL;
$menuModulos = array_values(array_filter($menuModulos, fn($m) => !empty($m['submodulos'] ?? [])));
// Dos filas: si los módulos no caben, pasan a la fila de abajo en vez de mostrar flechas.
$menuFilas = ($tipoMenu ?? 'horizontal') === 'horizontal_filas';
?>
<nav class="cmg-menu-modulos<?= $menuFilas ? ' cmg-menu-modulos--filas' : '' ?> d-none d-lg-flex" aria-label="Módulos del sistema">
    <?php if (!empty($menuModulos)): ?>
    <?php // Solo visible en el modo «Reordenar módulos» (botón «Personalizar menú»). ?>
    <div class="cmg-menu-franja-reordenar d-none" id="cmg-menu-franja-reordenar">
        <span class="cmg-menu-franja-reordenar__texto"><i class="bi bi-arrows-move"></i> Arrastre los módulos para ordenarlos</span>
        <button type="button" class="btn btn-sm btn-light border" data-cmg-reordenar-cancelar>Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" data-cmg-reordenar-listo>Listo</button>
    </div>
    <?php endif; ?>
    <?php if (!$menuFilas): ?>
    <button type="button" class="cmg-menu-scroll-btn cmg-menu-scroll-left" aria-label="Desplazar izquierda">
        <i class="bi bi-chevron-double-left"></i>
    </button>
    <?php endif; ?>
    <div class="cmg-menu-scroll-wrap">
        <ul class="cmg-menu-list" id="cmg-menu-list">
            <?php if (empty($menuModulos)): ?>
            <li class="cmg-menu-item"><span class="cmg-menu-empty text-muted small">Sin módulos asignados</span></li>
            <?php else: ?>
            <?php foreach ($menuModulos as $mod): ?>
            <li class="cmg-menu-item dropdown cmg-menu-hover" data-id-modulo="<?= (int) ($mod['id_modulo'] ?? 0) ?>">
                <button type="button" class="cmg-menu-modulo-btn" aria-expanded="false" aria-haspopup="true">
                    <i class="bi bi-grip-vertical cmg-menu-drag-handle" title="Arrastrar para reordenar"></i>
                    <i class="<?= htmlspecialchars(iconoClase($mod['icono_modulo'] ?? '')) ?>"></i>
                    <span><?= htmlspecialchars($mod['nombre_modulo'] ?? '') ?></span>
                    <i class="bi bi-chevron-down cmg-menu-chevron"></i>
                </button>
                <?php if (!empty($mod['submodulos'])): ?>
                <ul class="dropdown-menu cmg-menu-dropdown-menu" role="menu">
                    <?php foreach ($mod['submodulos'] as $sub): ?>
                    <?php
                    $href = $sub['ruta'] ?? '#';
                    $nuevaPestana = rutaAbrePestanaNueva($sub['ruta'] ?? '');
                    if ($href !== '#' && !preg_match('#^https?://#', $href) && !str_starts_with($href, '/')) {
                        $href = rtrim($base, '/') . '/' . ltrim($href, '/');
                    }
                    ?>
                    <li>
                        <a class="dropdown-item" href="<?= htmlspecialchars($href) ?>"<?= $nuevaPestana ? ' target="_blank" rel="noopener"' : '' ?>>
                            <i class="<?= htmlspecialchars(iconoClase($sub['icono_submodulo'] ?? '')) ?> me-2"></i>
                            <?= htmlspecialchars($sub['nombre_submodulo'] ?? '') ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    <?php if (!$menuFilas): ?>
    <button type="button" class="cmg-menu-scroll-btn cmg-menu-scroll-right" aria-label="Desplazar derecha">
        <i class="bi bi-chevron-double-right"></i>
    </button>
    <?php endif; ?>
    <button type="button" class="cmg-menu-scroll-btn cmg-menu-ajustes-btn" id="cmg-menu-ajustes-btn" title="Personalizar menú">
        <i class="bi bi-sliders2"></i>
    </button>
</nav>

<script src="<?= rtrim($base, '/') ?>/js/menu-personalizar.js?v=<?= asset_ver('/js/menu-personalizar.js') ?>"></script>
<script>
(function() {
    var wrap = document.querySelector('.cmg-menu-scroll-wrap');
    var btnLeft = document.querySelector('.cmg-menu-scroll-left');
    var btnRight = document.querySelector('.cmg-menu-scroll-right');
    if (!wrap || !btnLeft || !btnRight) return;

    var scrollStep = 200;
    function updateButtons() {
        var el = wrap;
        btnLeft.style.visibility = el.scrollLeft <= 0 ? 'hidden' : 'visible';
        btnRight.style.visibility = el.scrollLeft >= el.scrollWidth - el.clientWidth - 2 ? 'hidden' : 'visible';
    }
    btnLeft.addEventListener('click', function() {
        wrap.scrollBy({ left: -scrollStep, behavior: 'smooth' });
        setTimeout(updateButtons, 300);
    });
    btnRight.addEventListener('click', function() {
        wrap.scrollBy({ left: scrollStep, behavior: 'smooth' });
        setTimeout(updateButtons, 300);
    });
    wrap.addEventListener('scroll', updateButtons);
    new ResizeObserver(updateButtons).observe(wrap);
    updateButtons();
})();

(function() {
    var items = document.querySelectorAll('.cmg-menu-hover');
    var hideTimer;

    function hideAllMenus() {
        document.querySelectorAll('.cmg-menu-dropdown-menu.cmg-dropdown-visible').forEach(function(m) {
            m.classList.remove('cmg-dropdown-visible');
        });
    }

    items.forEach(function(item) {
        var btn = item.querySelector('.cmg-menu-modulo-btn');
        var menu = item.querySelector('.cmg-menu-dropdown-menu');
        if (!btn || !menu) return;

        function showMenu() {
            if (window.__cmgMenuReordenando) return;
            clearTimeout(hideTimer);
            hideAllMenus();
            var portal = document.getElementById('cmg-dropdown-portal');
            if (portal) portal.appendChild(menu);
            var rect = btn.getBoundingClientRect();
            menu.style.minWidth = Math.max(rect.width, 200) + 'px';
            menu.style.left = rect.left + 'px';
            menu.style.top = (rect.bottom - 2) + 'px';
            menu.classList.add('cmg-dropdown-visible');

            requestAnimationFrame(function() {
                var menuRect = menu.getBoundingClientRect();
                var vw = window.innerWidth;
                var pad = 8;
                if (menuRect.right > vw - pad) {
                    menu.style.left = (vw - menuRect.width - pad) + 'px';
                } else if (menuRect.left < pad) {
                    menu.style.left = pad + 'px';
                }
            });
        }
        function hideMenu() {
            hideTimer = setTimeout(function() {
                menu.classList.remove('cmg-dropdown-visible');
                item.appendChild(menu);
            }, 150);
        }
        item.addEventListener('mouseenter', showMenu);
        item.addEventListener('mouseleave', hideMenu);
        menu.addEventListener('mouseenter', function() { clearTimeout(hideTimer); });
        menu.addEventListener('mouseleave', hideMenu);
    });
})();

/* Botón «Personalizar menú»: tipo de menú y reordenar (public/js/menu-personalizar.js). */
(function() {
    var boton = document.getElementById('cmg-menu-ajustes-btn');
    if (!boton || !window.CMG_MenuPersonalizar) return;
    window.CMG_MenuPersonalizar.init({
        boton: boton,
        url: '<?= rtrim($base, '/') ?>/Preferencias/guardarMenuAjax',
        tipoActual: '<?= $menuFilas ? 'horizontal_filas' : 'horizontal' ?>',
        inicioActual: '<?= ($inicioMenu ?? '') === 'dashboard' ? 'dashboard' : 'accesos' ?>',
        puedeDashboard: <?= !empty($puedeDashboardMenu) ? 'true' : 'false' ?>,
        contenedor: document.querySelector('.cmg-menu-modulos'),
        claseModo: 'cmg-menu-reordenando',
        lista: document.getElementById('cmg-menu-list'),
        selectorItem: '.cmg-menu-item[data-id-modulo]',
        claseArrastre: 'cmg-menu-dragging',
        eje: 'x',
        scroller: <?= $menuFilas ? 'null' : "document.querySelector('.cmg-menu-scroll-wrap')" ?>,
        franja: document.getElementById('cmg-menu-franja-reordenar'),
        alEntrar: function() {
            // Cierra el submenú que hubiera quedado abierto.
            document.querySelectorAll('.cmg-menu-dropdown-menu.cmg-dropdown-visible').forEach(function(m) {
                m.classList.remove('cmg-dropdown-visible');
            });
        }
    });
})();
</script>
