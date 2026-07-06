<?php
/**
 * Visor de videos de ayuda — página STANDALONE (se abre en ventana aparte
 * desde el ícono de ayuda del navbar). No usa el layout principal.
 *
 * @var string $titulo
 * @var bool   $esSuperadmin
 */
$base = rtrim(BASE_URL ?? '', '/');
$esSuperadmin = !empty($esSuperadmin);
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
        .va-wrap { display: flex; flex-direction: column; height: 100vh; }
        .va-header { flex: 0 0 auto; }
        .va-body { flex: 1 1 auto; min-height: 0; display: flex; }
        .va-sidebar { width: 320px; max-width: 42%; border-right: 1px solid #dee2e6; background: #fff; display: flex; flex-direction: column; }
        .va-list { overflow-y: auto; flex: 1 1 auto; }
        .va-main { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; overflow-y: auto; }
        .va-item { cursor: pointer; border: 0; border-bottom: 1px solid #f0f0f0; }
        .va-item:hover { background: #f8f9fa; }
        .va-item.active { background: var(--bs-primary-bg-subtle, #cfe2ff); }
        .va-item .va-thumb { width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #e9ecef; color: #6c757d; flex: 0 0 auto; }
        .va-player { background: #000; border-radius: 10px; overflow: hidden; }
        .va-player video { width: 100%; max-height: 62vh; display: block; background: #000; }
        .va-empty { color: #8a94a6; }
        @media (max-width: 720px) {
            body { overflow: auto; }
            .va-wrap { height: auto; min-height: 100vh; }
            .va-body { flex-direction: column; }
            .va-sidebar { width: 100%; max-width: 100%; border-right: 0; border-bottom: 1px solid #dee2e6; }
            .va-list { max-height: 40vh; }
        }
    </style>
</head>
<body>
<div class="va-wrap">
    <!-- Encabezado -->
    <div class="va-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-question-circle-fill fs-4"></i>
            <div>
                <div class="fw-semibold lh-1"><?= htmlspecialchars($titulo) ?></div>
                <small class="text-white-50">Videos de ayuda del sistema</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($esSuperadmin): ?>
            <a href="<?= $base ?>/videos-ayuda/gestion" class="btn btn-light btn-sm">
                <i class="bi bi-gear-fill me-1"></i>Administrar
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="va-body">
        <!-- Lista de videos -->
        <aside class="va-sidebar">
            <div class="p-2 border-bottom">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="va-buscar" class="form-control" placeholder="Buscar ayuda...">
                </div>
            </div>
            <div class="va-list" id="va-list">
                <div class="text-center py-4 va-empty"><span class="spinner-border spinner-border-sm"></span> Cargando...</div>
            </div>
        </aside>

        <!-- Reproductor -->
        <main class="va-main p-3">
            <div id="va-player-wrap" class="d-none">
                <div class="va-player mb-3">
                    <video id="va-player" controls preload="metadata" controlsList="nodownload" playsinline></video>
                </div>
                <h5 id="va-titulo" class="mb-1"></h5>
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <button type="button" id="va-like-btn" class="btn btn-sm btn-outline-danger" title="Me gusta">
                        <i class="bi bi-heart" id="va-like-icon"></i>
                        <span id="va-like-count">0</span>
                    </button>
                    <span id="va-categoria" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 d-none"></span>
                </div>
                <p id="va-descripcion" class="text-muted mb-0" style="white-space: pre-line;"></p>
            </div>
            <div id="va-placeholder" class="h-100 d-flex flex-column align-items-center justify-content-center text-center va-empty">
                <i class="bi bi-play-btn display-1"></i>
                <p class="mt-2 mb-0">Seleccione un video de la lista para reproducirlo.</p>
            </div>
        </main>
    </div>
</div>

<div id="va-toast" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;"></div>

<script>
(function () {
    var base = '<?= $base ?>';
    var listEl = document.getElementById('va-list');
    var buscarEl = document.getElementById('va-buscar');
    var playerWrap = document.getElementById('va-player-wrap');
    var placeholder = document.getElementById('va-placeholder');
    var player = document.getElementById('va-player');
    var tituloEl = document.getElementById('va-titulo');
    var categoriaEl = document.getElementById('va-categoria');
    var descripcionEl = document.getElementById('va-descripcion');
    var likeBtn = document.getElementById('va-like-btn');
    var likeIcon = document.getElementById('va-like-icon');
    var likeCount = document.getElementById('va-like-count');
    var todos = [];
    var activoId = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(lista) {
        if (!lista.length) {
            listEl.innerHTML = '<div class="text-center py-4 va-empty">No hay videos de ayuda.</div>';
            return;
        }
        var html = '';
        lista.forEach(function (v) {
            var cat = v.categoria ? '<div class="small text-muted text-truncate">' + esc(v.categoria) + '</div>' : '';
            html += '<a class="va-item list-group-item d-flex align-items-center gap-2 p-2' + (v.id === activoId ? ' active' : '') + '" href="#" data-id="' + v.id + '">' +
                '<span class="va-thumb"><i class="bi bi-play-fill fs-4"></i></span>' +
                '<span class="flex-grow-1" style="min-width:0;"><div class="fw-medium text-truncate">' + esc(v.titulo) + '</div>' + cat + '</span>' +
                '</a>';
        });
        listEl.innerHTML = html;
        Array.prototype.forEach.call(listEl.querySelectorAll('.va-item'), function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                reproducir(parseInt(this.getAttribute('data-id'), 10));
            });
        });
    }

    function reproducir(id) {
        var v = todos.filter(function (x) { return x.id === id; })[0];
        if (!v) return;
        activoId = id;
        placeholder.classList.add('d-none');
        playerWrap.classList.remove('d-none');
        player.src = v.src;
        player.load();
        player.play().catch(function () { /* autoplay puede bloquearse; el usuario le da play */ });
        tituloEl.textContent = v.titulo;
        descripcionEl.textContent = v.descripcion || '';
        pintarLike(v);
        if (v.categoria) {
            categoriaEl.textContent = v.categoria;
            categoriaEl.classList.remove('d-none');
        } else {
            categoriaEl.classList.add('d-none');
        }
        Array.prototype.forEach.call(listEl.querySelectorAll('.va-item'), function (a) {
            a.classList.toggle('active', parseInt(a.getAttribute('data-id'), 10) === id);
        });
    }

    function pintarLike(v) {
        likeCount.textContent = v.likes || 0;
        if (v.liked) {
            likeIcon.className = 'bi bi-heart-fill';
            likeBtn.classList.remove('btn-outline-danger');
            likeBtn.classList.add('btn-danger');
        } else {
            likeIcon.className = 'bi bi-heart';
            likeBtn.classList.add('btn-outline-danger');
            likeBtn.classList.remove('btn-danger');
        }
    }

    function showToast(msg, tipo) {
        var cont = document.getElementById('va-toast');
        if (!cont) return;
        var d = document.createElement('div');
        d.className = 'alert alert-' + (tipo === 'error' ? 'danger' : 'success') + ' shadow-sm py-2 px-3 mb-2';
        d.style.opacity = '0';
        d.style.transition = 'opacity .2s ease';
        d.innerHTML = (tipo === 'error' ? '<i class="bi bi-exclamation-triangle me-1"></i>' : '<i class="bi bi-check-circle me-1"></i>') + msg;
        cont.appendChild(d);
        requestAnimationFrame(function () { d.style.opacity = '1'; });
        setTimeout(function () { d.style.opacity = '0'; setTimeout(function () { d.remove(); }, 250); }, 2500);
    }

    likeBtn.addEventListener('click', function () {
        if (!activoId) return;
        var v = todos.filter(function (x) { return x.id === activoId; })[0];
        if (!v) return;
        // Estado de carga sin destruir los hijos (#va-like-icon / #va-like-count).
        likeBtn.disabled = true;
        likeBtn.style.opacity = '0.6';
        var fd = new FormData();
        fd.append('id', activoId);
        fetch(base + '/videos-ayuda/toggle-like', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); })
          .then(function (res) {
              if (res && res.ok) {
                  v.liked = res.liked;
                  v.likes = res.likes;
                  if (activoId === v.id) pintarLike(v);
                  showToast(res.liked ? '¡Te gusta este video!' : 'Quitaste tu me gusta', 'ok');
              } else {
                  showToast((res && res.error) ? res.error : 'No se pudo registrar el me gusta.', 'error');
              }
          })
          .catch(function () {
              showToast('Error de conexión. Intenta de nuevo.', 'error');
          })
          .then(function () {
              likeBtn.disabled = false;
              likeBtn.style.opacity = '1';
          });
    });

    // Registrar la vista al iniciar la reproducción (una vez por video por sesión de página).
    var contados = {};
    player.addEventListener('play', function () {
        if (!activoId || contados[activoId]) return;
        contados[activoId] = true;
        var fd = new FormData();
        fd.append('id', activoId);
        fetch(base + '/videos-ayuda/registrar-vista', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(function () { /* la analítica no debe afectar la reproducción */ });
    });

    // Normaliza: minúsculas y sin tildes (á→a, ñ→n) para comparar sin acentos.
    function normaliza(s) {
        return String(s == null ? '' : s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    // Palabras vacías del español que no aportan a la búsqueda (ya normalizadas, sin tildes).
    var STOPWORDS = {
        'quiero': 1, 'quisiera': 1, 'necesito': 1, 'puedo': 1, 'hacer': 1, 'crear': 1, 'ver': 1,
        'como': 1, 'para': 1, 'por': 1, 'que': 1, 'con': 1, 'una': 1, 'uno': 1, 'unos': 1, 'unas': 1,
        'los': 1, 'las': 1, 'del': 1, 'sobre': 1, 'este': 1, 'esta': 1, 'video': 1, 'videos': 1, 'ayuda': 1
    };

    // Divide la frase en palabras clave: quita signos, tildes, palabras vacías y las muy cortas.
    function tokeniza(q) {
        return normaliza(q).split(/[^a-z0-9]+/).filter(function (w) {
            return w.length >= 3 && !STOPWORDS[w];
        });
    }

    function filtrar() {
        var tokens = tokeniza(buscarEl.value || '');
        if (!tokens.length) { render(todos); return; }   // sin palabras útiles → mostrar todo
        var conPuntaje = [];
        todos.forEach(function (v) {
            var texto = normaliza(v.titulo + ' ' + v.categoria + ' ' + v.descripcion + ' ' + (v.etiquetas || ''));
            var puntaje = 0;
            tokens.forEach(function (t) { if (texto.indexOf(t) !== -1) puntaje++; });
            if (puntaje > 0) conPuntaje.push({ v: v, p: puntaje });
        });
        // Más palabras coincidentes primero; a igualdad, respeta el orden original.
        conPuntaje.sort(function (a, b) { return b.p - a.p; });
        render(conPuntaje.map(function (x) { return x.v; }));
    }

    buscarEl.addEventListener('input', filtrar);

    fetch(base + '/videos-ayuda/lista', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            todos = (res && res.ok && res.videos) ? res.videos : [];
            render(todos);
        })
        .catch(function () {
            listEl.innerHTML = '<div class="text-center py-4 text-danger">No se pudo cargar la lista.</div>';
        });
})();
</script>
</body>
</html>
