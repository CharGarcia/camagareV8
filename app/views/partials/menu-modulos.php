<?php
/** @var array $menuModulos */
/** @var string $base */
$menuModulos = $menuModulos ?? [];
$base = $base ?? BASE_URL;
$menuModulos = array_values(array_filter($menuModulos, fn($m) => !empty($m['submodulos'] ?? [])));
?>
<nav class="cmg-menu-modulos" aria-label="Módulos del sistema">
    <button type="button" class="cmg-menu-scroll-btn cmg-menu-scroll-left" aria-label="Desplazar izquierda">
        <i class="bi bi-chevron-left"></i>
    </button>
    <div class="cmg-menu-scroll-wrap">
        <ul class="cmg-menu-list">
            <?php if (empty($menuModulos)): ?>
            <li class="cmg-menu-item"><span class="cmg-menu-empty text-muted small">Sin módulos asignados</span></li>
            <?php else: ?>
            <?php foreach ($menuModulos as $mod): ?>
            <li class="cmg-menu-item dropdown cmg-menu-hover">
                <button type="button" class="cmg-menu-modulo-btn" aria-expanded="false" aria-haspopup="true">
                    <i class="<?= htmlspecialchars(iconoClase($mod['icono_modulo'] ?? '')) ?>"></i>
                    <span><?= htmlspecialchars($mod['nombre_modulo'] ?? '') ?></span>
                    <i class="bi bi-chevron-down cmg-menu-chevron"></i>
                </button>
                <?php if (!empty($mod['submodulos'])): ?>
                <ul class="dropdown-menu cmg-menu-dropdown-menu" role="menu">
                    <?php foreach ($mod['submodulos'] as $sub): ?>
                    <?php
                    $href = $sub['ruta'] ?? '#';
                    if ($href !== '#' && !preg_match('#^https?://#', $href) && !str_starts_with($href, '/')) {
                        $href = rtrim($base, '/') . '/' . ltrim($href, '/');
                    }
                    ?>
                    <li>
                        <a class="dropdown-item" href="<?= htmlspecialchars($href) ?>">
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
    <button type="button" class="cmg-menu-scroll-btn cmg-menu-scroll-right" aria-label="Desplazar derecha">
        <i class="bi bi-chevron-right"></i>
    </button>
</nav>

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
</script>
