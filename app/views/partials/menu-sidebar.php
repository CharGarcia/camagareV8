<?php
/**
 * Menú de módulos LATERAL (sidebar). Alternativa a partials/menu-modulos.php
 * (barra horizontal), que es la que se ve por defecto. Cada usuario elige el
 * tipo de menú con el botón de la propia barra.
 *
 * La personalización (tipo de menú, orden de los módulos y sidebar contraído)
 * es del USUARIO, igual en todas sus empresas: UsuarioPreferenciaService::
 * obtenerMenuUsuario()/guardarMenuUsuario(), endpoint Preferencias/guardarMenuAjax.
 * Usa los mismos $menuModulos (ya filtrados por permisos y ordenados) que la
 * barra horizontal, así que ambos menús muestran siempre los mismos módulos.
 *
 * Solo escritorio (≥ 992px): en móvil sigue el panel lateral del navbar.
 *
 * @var array  $menuModulos
 * @var string $base
 */
$menuModulos = $menuModulos ?? [];
$base = $base ?? BASE_URL;
$menuModulos = array_values(array_filter($menuModulos, fn($m) => !empty($m['submodulos'] ?? [])));

// Pantalla actual, para resaltar el submódulo y dejar abierto su módulo.
$pathActual = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$hrefSidebar = static function (string $ruta) use ($base): string {
    $href = $ruta !== '' ? $ruta : '#';
    if ($href !== '#' && !preg_match('#^https?://#', $href) && !str_starts_with($href, '/')) {
        $href = rtrim($base, '/') . '/' . ltrim($href, '/');
    }
    return $href;
};
// Coincidencia más larga: /modulos/control-bancario/posfechados gana a /modulos/control-bancario.
$hrefActivo = '';
foreach ($menuModulos as $mod) {
    foreach ($mod['submodulos'] as $sub) {
        $p = rtrim((string) (parse_url($hrefSidebar((string) ($sub['ruta'] ?? '')), PHP_URL_PATH) ?: ''), '/');
        if ($p === '' || $p === '#') {
            continue;
        }
        if (($pathActual === $p || str_starts_with($pathActual, $p . '/')) && strlen($p) > strlen($hrefActivo)) {
            $hrefActivo = $p;
        }
    }
}
?>
<aside class="cmg-sidebar d-none d-lg-flex" id="cmg-sidebar" aria-label="Módulos del sistema">
    <div class="cmg-sidebar__tools">
        <button type="button" class="cmg-sidebar__tool" id="cmg-sidebar-colapsar" title="Contraer / expandir menú">
            <i class="bi bi-chevron-double-left cmg-sidebar__icono-colapsar"></i>
        </button>
        <?php if (!empty($menuModulos)): ?>
        <button type="button" class="cmg-sidebar__tool cmg-sidebar__solo-expandido" id="cmg-sidebar-reordenar" title="Reordenar módulos" aria-pressed="false">
            <i class="bi bi-arrows-move"></i>
        </button>
        <button type="button" class="cmg-sidebar__tool cmg-sidebar__solo-expandido d-none" id="cmg-sidebar-restablecer" title="Restablecer orden original">
            <i class="bi bi-arrow-counterclockwise"></i>
        </button>
        <?php endif; ?>
        <button type="button" class="cmg-sidebar__tool ms-auto" data-cmg-tipo-menu="horizontal" title="Cambiar a menú horizontal">
            <i class="bi bi-window"></i>
        </button>
    </div>

    <?php // El cuerpo no hace scroll: dibuja el degradado de «hay más» arriba/abajo sobre la lista. ?>
    <div class="cmg-sidebar__cuerpo" id="cmg-sidebar-cuerpo">
    <ul class="cmg-sidebar__lista" id="cmg-sidebar-lista">
        <?php if (empty($menuModulos)): ?>
        <li class="cmg-sidebar__vacio text-muted small">Sin módulos asignados</li>
        <?php endif; ?>
        <?php foreach ($menuModulos as $mod):
            $links = [];
            $contieneActivo = false;
            foreach ($mod['submodulos'] as $sub) {
                $href = $hrefSidebar((string) ($sub['ruta'] ?? ''));
                $p = rtrim((string) (parse_url($href, PHP_URL_PATH) ?: ''), '/');
                $activo = $hrefActivo !== '' && $p === $hrefActivo;
                $contieneActivo = $contieneActivo || $activo;
                $links[] = [$sub, $href, $activo];
            }
        ?>
        <li class="cmg-sidebar__item<?= $contieneActivo ? ' abierto' : '' ?>" data-id-modulo="<?= (int) ($mod['id_modulo'] ?? 0) ?>">
            <button type="button" class="cmg-sidebar__modulo<?= $contieneActivo ? ' activo' : '' ?>" aria-expanded="<?= $contieneActivo ? 'true' : 'false' ?>">
                <i class="bi bi-grip-vertical cmg-sidebar__grip" title="Arrastrar para reordenar"></i>
                <i class="<?= htmlspecialchars(iconoClase($mod['icono_modulo'] ?? '')) ?> cmg-sidebar__icono"></i>
                <span class="cmg-sidebar__texto"><?= htmlspecialchars($mod['nombre_modulo'] ?? '') ?></span>
                <i class="bi bi-chevron-down cmg-sidebar__chevron"></i>
            </button>
            <ul class="cmg-sidebar__sub">
                <li class="cmg-sidebar__sub-titulo"><?= htmlspecialchars($mod['nombre_modulo'] ?? '') ?></li>
                <?php foreach ($links as [$sub, $href, $activo]): ?>
                <li>
                    <a class="cmg-sidebar__link<?= $activo ? ' activo' : '' ?>" href="<?= htmlspecialchars($href) ?>"<?= rutaAbrePestanaNueva($sub['ruta'] ?? '') ? ' target="_blank" rel="noopener"' : '' ?>
                       title="<?= htmlspecialchars($sub['nombre_submodulo'] ?? '') ?>">
                        <i class="<?= htmlspecialchars(iconoClase($sub['icono_submodulo'] ?? '')) ?>"></i>
                        <span><?= htmlspecialchars($sub['nombre_submodulo'] ?? '') ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>
    </ul>
    </div>
</aside>

<script>
(function() {
    var sidebar = document.getElementById('cmg-sidebar');
    var lista = document.getElementById('cmg-sidebar-lista');
    if (!sidebar || !lista) return;
    var body = document.body;
    var reordenando = false;

    /* Personalización del menú del USUARIO (igual en todas sus empresas):
       {tipo, orden, colapsado}. Misma función que en partials/menu-modulos.php. */
    window.CMG_guardarMenuUsuario = window.CMG_guardarMenuUsuario || function(cambios) {
        var fd = new FormData();
        fd.append('menu', JSON.stringify(cambios));
        return fetch('<?= rtrim($base, '/') ?>/Preferencias/guardarMenuAjax', {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    };

    function colapsado() { return body.classList.contains('cmg-sidebar-colapsado'); }
    function items() { return Array.prototype.slice.call(lista.querySelectorAll('.cmg-sidebar__item[data-id-modulo]')); }

    /* ── Contraer / expandir (se guarda en la preferencia del usuario) ── */
    function ocultarFlyouts() {
        lista.querySelectorAll('.cmg-sidebar__sub.cmg-flyout-visible').forEach(function(s) {
            s.classList.remove('cmg-flyout-visible');
            s.style.top = s.style.left = '';
        });
    }
    document.getElementById('cmg-sidebar-colapsar').addEventListener('click', function() {
        var c = !colapsado();
        if (c && reordenando) activarReordenar(false);
        body.classList.toggle('cmg-sidebar-colapsado', c);
        ocultarFlyouts();
        window.CMG_guardarMenuUsuario({ colapsado: c }).catch(function() {});
        // Lo que se mide por JS (tablas, barras de scroll) se recalcula con el nuevo ancho.
        setTimeout(function() { window.dispatchEvent(new Event('resize')); }, 200);
    });

    /* ── Expandido: acordeón (un módulo abierto a la vez) ──
       Contraído: los submódulos salen flotando a la derecha al pasar el mouse. */
    items().forEach(function(li) {
        var btn = li.querySelector('.cmg-sidebar__modulo');
        var sub = li.querySelector('.cmg-sidebar__sub');
        var timer;

        btn.addEventListener('click', function() {
            if (colapsado()) { mostrarFlyout(); return; }
            var abrir = !li.classList.contains('abierto');
            items().forEach(function(o) {
                o.classList.remove('abierto');
                o.querySelector('.cmg-sidebar__modulo').setAttribute('aria-expanded', 'false');
            });
            li.classList.toggle('abierto', abrir);
            btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        });

        function mostrarFlyout() {
            if (!colapsado() || reordenando) return;
            clearTimeout(timer);
            ocultarFlyouts();
            var r = btn.getBoundingClientRect();
            sub.style.left = sidebar.getBoundingClientRect().right + 'px';
            sub.style.top = r.top + 'px';
            sub.classList.add('cmg-flyout-visible');
            var h = sub.getBoundingClientRect().height;
            if (r.top + h > window.innerHeight - 8) {
                sub.style.top = Math.max(8, window.innerHeight - h - 8) + 'px';
            }
        }
        li.addEventListener('mouseenter', mostrarFlyout);
        li.addEventListener('mouseleave', function() {
            timer = setTimeout(function() {
                sub.classList.remove('cmg-flyout-visible');
                sub.style.top = sub.style.left = '';
            }, 150);
        });
    });
    lista.addEventListener('scroll', ocultarFlyouts);

    /* ── Degradado de «hay más» (la lista no muestra barra de scroll) ──
       Arriba si ya se bajó algo; abajo si quedan módulos fuera de la vista.
       Se recalcula al hacer scroll, al cambiar el tamaño (ventana, contraer)
       y al abrir/cerrar o reordenar módulos, sin engancharse a cada botón. */
    var cuerpo = document.getElementById('cmg-sidebar-cuerpo');
    function actualizarSombras() {
        if (!cuerpo) return;
        var arriba = lista.scrollTop > 2;
        var abajo = lista.scrollTop + lista.clientHeight < lista.scrollHeight - 2;
        cuerpo.classList.toggle('cmg-sidebar__cuerpo--mas-arriba', arriba);
        cuerpo.classList.toggle('cmg-sidebar__cuerpo--mas-abajo', abajo);
    }
    var sombraPendiente = false;
    function programarSombras() {
        if (sombraPendiente) return;
        sombraPendiente = true;
        requestAnimationFrame(function() { sombraPendiente = false; actualizarSombras(); });
    }
    lista.addEventListener('scroll', programarSombras, { passive: true });
    if (window.ResizeObserver) new ResizeObserver(programarSombras).observe(lista);
    if (window.MutationObserver) {
        new MutationObserver(programarSombras).observe(lista, {
            subtree: true, childList: true, attributes: true, attributeFilter: ['class']
        });
    }
    window.addEventListener('resize', programarSombras);
    actualizarSombras();

    /* ── Reordenar (mismo orden del usuario que la barra horizontal) ── */
    var btnReordenar = document.getElementById('cmg-sidebar-reordenar');
    var btnRestablecer = document.getElementById('cmg-sidebar-restablecer');
    var dragEl = null;

    function activarReordenar(activo) {
        reordenando = activo;
        sidebar.classList.toggle('cmg-sidebar--reordenando', activo);
        if (btnReordenar) {
            btnReordenar.classList.toggle('active', activo);
            btnReordenar.setAttribute('aria-pressed', activo ? 'true' : 'false');
        }
        if (btnRestablecer) btnRestablecer.classList.toggle('d-none', !activo);
        items().forEach(function(li) { li.setAttribute('draggable', activo ? 'true' : 'false'); });
    }
    if (btnReordenar) {
        btnReordenar.addEventListener('click', function() { activarReordenar(!reordenando); });
    }
    if (btnRestablecer) {
        btnRestablecer.addEventListener('click', function() {
            window.CMG_guardarMenuUsuario({ orden: [] }).finally(function() { window.location.reload(); });
        });
    }
    lista.addEventListener('dragstart', function(e) {
        var li = e.target.closest('.cmg-sidebar__item[data-id-modulo]');
        if (!li || !reordenando) { e.preventDefault(); return; }
        dragEl = li;
        li.classList.add('cmg-sidebar__item--arrastrando');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', li.dataset.idModulo || ''); } catch (err) {}
    });
    lista.addEventListener('dragover', function(e) {
        if (!dragEl) return;
        e.preventDefault();
        var li = e.target.closest('.cmg-sidebar__item[data-id-modulo]');
        if (!li || li === dragEl) return;
        var r = li.getBoundingClientRect();
        lista.insertBefore(dragEl, (e.clientY - r.top) > (r.height / 2) ? li.nextSibling : li);
    });
    lista.addEventListener('drop', function(e) { e.preventDefault(); });
    lista.addEventListener('dragend', function() {
        if (!dragEl) return;
        dragEl.classList.remove('cmg-sidebar__item--arrastrando');
        dragEl = null;
        window.CMG_guardarMenuUsuario({ orden: items().map(function(li) { return li.dataset.idModulo; }) }).catch(function() {});
    });

    /* ── Volver al menú horizontal ── */
    sidebar.querySelectorAll('[data-cmg-tipo-menu]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            btn.disabled = true;
            window.CMG_guardarMenuUsuario({ tipo: btn.getAttribute('data-cmg-tipo-menu') })
                .finally(function() { window.location.reload(); });
        });
    });

    // Dejar a la vista el submódulo de la pantalla actual.
    var activo = lista.querySelector('.cmg-sidebar__link.activo');
    if (activo && !colapsado()) activo.scrollIntoView({ block: 'nearest' });
})();
</script>
