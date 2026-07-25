<?php
/**
 * Visor del Manual del Sistema — página STANDALONE (se abre en ventana aparte
 * desde el ícono de ayuda del navbar). No usa el layout principal, igual que el
 * visor de videos de ayuda.
 *
 * Todo lo que se muestra aquí ya viene filtrado por visibilidad desde el
 * servidor (DocumentacionService::contexto() → condiciones SQL). Esta vista
 * NO decide qué puede ver el usuario.
 *
 * @var string $titulo
 * @var bool   $esSuperadmin
 * @var string $slugInicial   Artículo a abrir al entrar (deep-link o contextual)
 * @var string $anclaInicial  Sección a la que saltar
 */
$base = rtrim(BASE_URL ?? '', '/');
$esSuperadmin = !empty($esSuperadmin);
$slugInicial  = (string) ($slugInicial ?? '');
$anclaInicial = (string) ($anclaInicial ?? '');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { background: #f4f6f9; overflow: hidden; }
        .doc-wrap { display: flex; flex-direction: column; height: 100vh; }
        .doc-header { flex: 0 0 auto; }
        .doc-body { flex: 1 1 auto; min-height: 0; display: flex; }

        .doc-sidebar { width: 320px; max-width: 42%; border-right: 1px solid #dee2e6; background: #fff; display: flex; flex-direction: column; }
        .doc-list { overflow-y: auto; flex: 1 1 auto; }
        .doc-cat { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; background: #f8f9fa; padding: .35rem .75rem; position: sticky; top: 0; z-index: 1; border-bottom: 1px solid #eee; }
        .doc-item { cursor: pointer; border: 0; border-bottom: 1px solid #f2f2f2; display: block; padding: .5rem .75rem; text-decoration: none; color: inherit; }
        .doc-item:hover { background: #f8f9fa; }
        .doc-item.active { background: var(--bs-primary-bg-subtle, #cfe2ff); }
        .doc-item .doc-item-tit { font-weight: 500; }
        .doc-item .doc-item-sub { font-size: .78rem; color: #6c757d; }
        .doc-item mark { background: #fff3cd; padding: 0 .1rem; }

        .doc-main { flex: 1 1 auto; min-width: 0; display: flex; overflow: hidden; }
        .doc-content { flex: 1 1 auto; min-width: 0; overflow-y: auto; padding: 1.25rem 1.5rem 3rem; }
        .doc-toc { width: 230px; flex: 0 0 auto; border-left: 1px solid #dee2e6; background: #fff; overflow-y: auto; padding: 1rem .75rem; }
        .doc-toc a { display: block; font-size: .82rem; color: #495057; text-decoration: none; padding: .18rem 0; border-left: 2px solid transparent; padding-left: .5rem; }
        .doc-toc a:hover { color: var(--bs-primary, #0d6efd); border-left-color: var(--bs-primary, #0d6efd); }
        .doc-toc a.nivel-3 { padding-left: 1.15rem; font-size: .78rem; }

        /* Tipografía del artículo */
        .doc-article { max-width: 860px; }
        .doc-article h2 { font-size: 1.22rem; font-weight: 600; margin: 1.9rem 0 .7rem; padding-bottom: .3rem; border-bottom: 1px solid #e9ecef; scroll-margin-top: .5rem; }
        .doc-article h3 { font-size: 1.03rem; font-weight: 600; margin: 1.3rem 0 .5rem; scroll-margin-top: .5rem; }
        .doc-article h4 { font-size: .95rem; font-weight: 600; margin: 1rem 0 .4rem; }
        .doc-article p, .doc-article li { line-height: 1.72; }
        .doc-article ul, .doc-article ol { padding-left: 1.3rem; }
        .doc-article table { width: 100%; border-collapse: collapse; margin: .8rem 0; font-size: .88rem; }
        .doc-article th, .doc-article td { border: 1px solid #dee2e6; padding: .4rem .55rem; vertical-align: top; }
        .doc-article th { background: #f8f9fa; text-align: left; }
        .doc-article code { background: #f1f3f5; padding: .1rem .3rem; border-radius: 4px; font-size: .87em; }
        .doc-article pre { background: #f8f9fa; border: 1px solid #e9ecef; padding: .75rem; border-radius: 6px; overflow-x: auto; }
        .doc-article pre code { background: none; padding: 0; }
        .doc-article blockquote { border-left: 3px solid var(--bs-primary, #0d6efd); background: #f8f9fa; padding: .5rem .8rem; margin: .8rem 0; color: #495057; }
        .doc-article img { max-width: 100%; height: auto; border-radius: 6px; }

        .doc-empty { color: #8a94a6; }
        .doc-buscador { max-width: 460px; }

        @media (max-width: 992px) { .doc-toc { display: none; } }
        @media (max-width: 720px) {
            body { overflow: auto; }
            .doc-wrap { height: auto; min-height: 100vh; }
            .doc-body { flex-direction: column; }
            .doc-sidebar { width: 100%; max-width: 100%; border-right: 0; border-bottom: 1px solid #dee2e6; }
            .doc-list { max-height: 38vh; }
        }
        @media print {
            .doc-header, .doc-sidebar, .doc-toc, .doc-no-print { display: none !important; }
            body, .doc-wrap, .doc-main, .doc-content { height: auto; overflow: visible; }
        }
    </style>
</head>
<body>
<div class="doc-wrap">
    <!-- Encabezado -->
    <div class="doc-header d-flex align-items-center gap-3 px-3 py-2 bg-primary text-white shadow-sm flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-journal-bookmark-fill fs-4"></i>
            <div>
                <div class="fw-semibold lh-1"><?= htmlspecialchars($titulo) ?></div>
                <small class="text-white-50">Documentación de todos los módulos</small>
            </div>
        </div>

        <div class="input-group input-group-sm doc-buscador flex-grow-1">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="doc-buscar" class="form-control" autofocus
                   placeholder="Buscar en el manual… (ej: anular una factura)">
            <button class="btn btn-light" type="button" id="doc-limpiar" title="Limpiar búsqueda">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <a href="<?= $base ?>/documentacion/completo" class="btn btn-outline-light btn-sm"
               title="Ver todo el manual en una página (imprimir o guardar en PDF)">
                <i class="bi bi-file-earmark-pdf"></i>
            </a>
            <?php if ($esSuperadmin): ?>
            <a href="<?= $base ?>/documentacion/gestion" class="btn btn-light btn-sm">
                <i class="bi bi-gear-fill me-1"></i>Administrar
            </a>
            <?php endif; ?>
            <a href="<?= $base ?>/videos-ayuda" class="btn btn-outline-light btn-sm" title="Videos de ayuda">
                <i class="bi bi-play-btn-fill"></i>
            </a>
        </div>
    </div>

    <div class="doc-body">
        <!-- Índice / resultados -->
        <aside class="doc-sidebar">
            <div class="doc-list" id="doc-list">
                <div class="text-center py-4 doc-empty"><span class="spinner-border spinner-border-sm"></span> Cargando…</div>
            </div>
        </aside>

        <!-- Artículo -->
        <main class="doc-main">
            <div class="doc-content" id="doc-content">
                <div id="doc-placeholder" class="h-100 d-flex flex-column align-items-center justify-content-center text-center doc-empty">
                    <i class="bi bi-journal-text display-1"></i>
                    <p class="mt-2 mb-0">Busque arriba o elija un tema del índice.</p>
                </div>

                <article id="doc-articulo" class="doc-article d-none">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                        <div>
                            <div class="small text-muted" id="doc-migaja"></div>
                            <h4 class="fw-semibold mb-1" id="doc-titulo"></h4>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm doc-no-print" id="doc-imprimir" title="Imprimir / Guardar como PDF">
                            <i class="bi bi-printer"></i>
                        </button>
                    </div>
                    <p class="text-muted" id="doc-resumen"></p>

                    <div id="doc-videos" class="d-none mb-3"></div>

                    <div id="doc-cuerpo"></div>

                    <hr class="mt-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 doc-no-print">
                        <div class="small text-muted" id="doc-pie"></div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted">¿Le resultó útil?</span>
                            <button type="button" class="btn btn-outline-success btn-sm" id="doc-util">
                                <i class="bi bi-hand-thumbs-up"></i> <span id="doc-util-n">0</span>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="doc-no-util">
                                <i class="bi bi-hand-thumbs-down"></i> <span id="doc-no-util-n">0</span>
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <nav class="doc-toc d-none" id="doc-toc">
                <div class="small text-uppercase text-muted fw-semibold mb-2" style="font-size:.7rem;letter-spacing:.05em;">En esta página</div>
                <div id="doc-toc-items"></div>
            </nav>
        </main>
    </div>
</div>

<div id="doc-toast" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;"></div>

<script>
(function () {
    var base         = '<?= $base ?>';
    var slugInicial  = <?= json_encode($slugInicial, JSON_UNESCAPED_UNICODE) ?>;
    var anclaInicial = <?= json_encode($anclaInicial, JSON_UNESCAPED_UNICODE) ?>;

    var listEl     = document.getElementById('doc-list');
    var buscarEl   = document.getElementById('doc-buscar');
    var limpiarEl  = document.getElementById('doc-limpiar');
    var placeholder= document.getElementById('doc-placeholder');
    var articuloEl = document.getElementById('doc-articulo');
    var contentEl  = document.getElementById('doc-content');
    var tocEl      = document.getElementById('doc-toc');
    var tocItemsEl = document.getElementById('doc-toc-items');

    var arbol = [];
    var slugActivo = null;
    var idActivo = null;
    var temporizador = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function toast(mensaje, tipo) {
        var cont = document.getElementById('doc-toast');
        var div = document.createElement('div');
        div.className = 'alert alert-' + (tipo || 'secondary') + ' shadow-sm py-2 px-3 mb-2';
        div.textContent = mensaje;
        cont.appendChild(div);
        setTimeout(function () { div.remove(); }, 3500);
    }

    // ── Índice ──────────────────────────────────────────────────────────
    function cargarArbol() {
        fetch(base + '/documentacion/arbol', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                arbol = (d && d.ok && d.categorias) ? d.categorias : [];
                pintarArbol();
                if (slugInicial) { abrir(slugInicial, anclaInicial); }
            })
            .catch(function () {
                listEl.innerHTML = '<div class="text-center py-4 doc-empty">No se pudo cargar el índice.</div>';
            });
    }

    function pintarArbol() {
        if (!arbol.length) {
            listEl.innerHTML = '<div class="text-center py-4 doc-empty">Todavía no hay artículos publicados.</div>';
            return;
        }
        var html = '';
        arbol.forEach(function (cat) {
            html += '<div class="doc-cat">' + esc(cat.categoria) + '</div>';
            cat.articulos.forEach(function (a) {
                html += '<a class="doc-item' + (a.slug === slugActivo ? ' active' : '') + '" href="#" data-slug="' + esc(a.slug) + '">' +
                        '<div class="doc-item-tit">' + esc(a.titulo) + '</div>' +
                        (a.resumen ? '<div class="doc-item-sub text-truncate">' + esc(a.resumen) + '</div>' : '') +
                        '</a>';
            });
        });
        listEl.innerHTML = html;
        enlazarItems();
    }

    function enlazarItems() {
        Array.prototype.forEach.call(listEl.querySelectorAll('.doc-item'), function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                abrir(this.getAttribute('data-slug'), this.getAttribute('data-ancla') || '');
            });
        });
    }

    // ── Búsqueda ────────────────────────────────────────────────────────
    function buscar(q) {
        fetch(base + '/documentacion/buscar?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) { return; }
                pintarResultados(d.resultados || [], q);
            })
            .catch(function () { /* silencioso: el índice sigue disponible */ });
    }

    function pintarResultados(res, q) {
        if (!res.length) {
            listEl.innerHTML = '<div class="p-3 text-center doc-empty">' +
                '<i class="bi bi-search fs-3 d-block mb-2"></i>' +
                'Sin resultados para <strong>' + esc(q) + '</strong>.' +
                '<div class="small mt-2">Pruebe con otras palabras. Su búsqueda quedó registrada para ampliar el manual.</div>' +
                '</div>';
            return;
        }
        var html = '<div class="doc-cat">' + res.length + ' resultado' + (res.length === 1 ? '' : 's') + '</div>';
        res.forEach(function (r) {
            var donde = esc(r.categoria || '');
            if (r.seccion_titulo) { donde += (donde ? ' › ' : '') + esc(r.seccion_titulo); }
            html += '<a class="doc-item" href="#" data-slug="' + esc(r.slug) + '" data-ancla="' + esc(r.ancla || '') + '">' +
                    '<div class="doc-item-tit">' + esc(r.titulo) + '</div>' +
                    (donde ? '<div class="doc-item-sub">' + donde + '</div>' : '') +
                    // El fragmento ya viene escapado desde el servidor: solo trae <mark>.
                    (r.fragmento ? '<div class="doc-item-sub mt-1">' + r.fragmento + '</div>' : '') +
                    '</a>';
        });
        listEl.innerHTML = html;
        enlazarItems();
    }

    // ── Artículo ────────────────────────────────────────────────────────
    function abrir(slug, ancla) {
        if (!slug) { return; }
        fetch(base + '/documentacion/articulo?slug=' + encodeURIComponent(slug), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) {
                    toast((d && d.error) || 'No se pudo abrir el artículo.', 'warning');
                    return;
                }
                pintarArticulo(d.articulo, ancla);
            })
            .catch(function () { toast('No se pudo abrir el artículo.', 'danger'); });
    }

    function pintarArticulo(a, ancla) {
        slugActivo = a.slug;
        idActivo = a.id;

        placeholder.classList.add('d-none');
        articuloEl.classList.remove('d-none');

        document.getElementById('doc-migaja').textContent = a.categoria || '';
        document.getElementById('doc-titulo').textContent = a.titulo;
        document.getElementById('doc-resumen').textContent = a.resumen || '';
        // Contenido saneado en el servidor con HTMLPurifier antes de guardarse.
        document.getElementById('doc-cuerpo').innerHTML = a.contenido || '<p class="doc-empty">Este artículo aún no tiene contenido.</p>';

        document.getElementById('doc-util-n').textContent = a.utiles || 0;
        document.getElementById('doc-no-util-n').textContent = a.no_utiles || 0;

        var pie = [];
        if (a.version)     { pie.push('Versión ' + a.version); }
        if (a.actualizado) { pie.push('Actualizado el ' + a.actualizado); }
        document.getElementById('doc-pie').textContent = pie.join(' · ');

        pintarVideos(a.videos || []);
        pintarToc(a.secciones || []);

        // Marcar el artículo activo en el índice (si está a la vista).
        Array.prototype.forEach.call(listEl.querySelectorAll('.doc-item'), function (el) {
            el.classList.toggle('active', el.getAttribute('data-slug') === slugActivo);
        });

        contentEl.scrollTop = 0;
        if (ancla) { setTimeout(function () { irA(ancla); }, 50); }
    }

    function pintarVideos(videos) {
        var cont = document.getElementById('doc-videos');
        if (!videos.length) { cont.classList.add('d-none'); cont.innerHTML = ''; return; }
        var html = '<div class="d-flex flex-wrap gap-2 doc-no-print">';
        videos.forEach(function (v) {
            html += '<a class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" href="' + base + '/videos-ayuda">' +
                    '<i class="bi bi-play-btn-fill me-1"></i>' + esc(v.titulo) + '</a>';
        });
        html += '</div>';
        cont.innerHTML = html;
        cont.classList.remove('d-none');
    }

    function pintarToc(secciones) {
        if (!secciones.length) { tocEl.classList.add('d-none'); return; }
        var html = '';
        secciones.forEach(function (s) {
            html += '<a href="#" class="nivel-' + s.nivel + '" data-ancla="' + esc(s.ancla) + '">' + esc(s.titulo) + '</a>';
        });
        tocItemsEl.innerHTML = html;
        tocEl.classList.remove('d-none');
        Array.prototype.forEach.call(tocItemsEl.querySelectorAll('a'), function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                irA(this.getAttribute('data-ancla'));
            });
        });
    }

    /** El contenido vive dentro de un contenedor con scroll propio, así que no
     *  se puede usar location.hash: hay que desplazar el elemento a la vista. */
    function irA(ancla) {
        if (!ancla) { return; }
        var destino = document.getElementById(ancla);
        if (destino) { destino.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    // ── Valoración ──────────────────────────────────────────────────────
    function votar(util) {
        if (!idActivo) { return; }
        var body = new FormData();
        body.append('id', idActivo);
        body.append('util', util ? '1' : '0');
        fetch(base + '/documentacion/feedback', {
            method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) { toast((d && d.error) || 'No se pudo registrar su valoración.', 'warning'); return; }
                document.getElementById('doc-util-n').textContent = d.utiles;
                document.getElementById('doc-no-util-n').textContent = d.no_utiles;
                toast('¡Gracias por su valoración!', 'success');
            })
            .catch(function () { toast('No se pudo registrar su valoración.', 'danger'); });
    }

    // ── Eventos ─────────────────────────────────────────────────────────
    buscarEl.addEventListener('input', function () {
        var q = this.value.trim();
        clearTimeout(temporizador);
        if (q.length < 2) { pintarArbol(); return; }
        temporizador = setTimeout(function () { buscar(q); }, 250);
    });

    buscarEl.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { this.value = ''; pintarArbol(); }
    });

    limpiarEl.addEventListener('click', function () {
        buscarEl.value = '';
        buscarEl.focus();
        pintarArbol();
    });

    document.getElementById('doc-imprimir').addEventListener('click', function () { window.print(); });
    document.getElementById('doc-util').addEventListener('click', function () { votar(true); });
    document.getElementById('doc-no-util').addEventListener('click', function () { votar(false); });

    cargarArbol();
})();
</script>
</body>
</html>
