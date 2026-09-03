<?php
/**
 * Tarjeta flotante de "Novedades del sistema".
 *
 * Se incluye en layouts/main.php (usuarios autenticados). Al cargar la página,
 * SOLO en pantallas de PC (>= 992 px, mismo quiebre que el navbar de escritorio),
 * consulta /novedades-sistema/estado y:
 *   - pinta el contador del megáfono del navbar (.cmg-novedades-wrap / -badge);
 *   - si hay novedades sin leer, desliza desde la
 *     esquina inferior derecha una tarjeta (encima del widget de soporte) que
 *     muestra UNA novedad a la vez, con flechas para pasar a la siguiente.
 *     Se abre sola UNA vez por inicio de sesión (primera pantalla tras el
 *     login; el servidor lleva la marca en $_SESSION), no en cada página.
 * El megáfono la vuelve a abrir en cualquier momento (también las ya leídas).
 *
 * Botones: "Entendido" marca como leída la novedad en pantalla y pasa a la
 * siguiente sin leer (si no queda ninguna, la tarjeta se cierra); la X, Esc o
 * un clic fuera de la tarjeta solo la cierran: mientras queden sin leer, vuelve
 * a aparecer en el siguiente inicio de sesión.
 * Una vista puede desactivar la apertura automática con:
 *   window.CMG_NOVEDADES_NO_AUTO = true;
 */
if (empty($_SESSION['id_usuario'])) {
    return;
}
$nvBase = rtrim(BASE_URL ?? '', '/');
?>
<style>
    /* Tarjeta flotante: esquina inferior derecha, encima del lanzador del chat
       de soporte (.sop-widget: right 18px, bottom 18px, 56px de alto, z 1035).
       Queda un nivel por debajo para que el chat, si se abre, pase adelante. */
    .nvc-card {
        position: fixed; right: 18px; bottom: 90px; z-index: 1034;
        width: 400px; max-width: calc(100vw - 36px);
        background: #fff; border: 1px solid #d9e6ec; border-radius: 12px;
        box-shadow: 0 18px 48px rgba(31, 45, 56, .22), 0 2px 6px rgba(31, 45, 56, .08);
        display: flex; flex-direction: column; overflow: hidden;
        font-size: .9rem; color: #1f2d38;
        opacity: 0; transform: translateY(24px); pointer-events: none;
        transition: opacity .28s ease, transform .32s cubic-bezier(.2, .8, .3, 1);
    }
    .nvc-card.nvc-visible { opacity: 1; transform: translateY(0); pointer-events: auto; }
    @media (prefers-reduced-motion: reduce) { .nvc-card { transition: none; } }

    /* Cabecera amarilla (aviso), con texto oscuro para mantener el contraste. */
    .nvc-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px 10px 14px; border-bottom: 1px solid #f1d27a; background: linear-gradient(135deg, #ffe08a, #fff3c4); color: #5c4400; }
    .nvc-ico { width: 34px; height: 34px; border-radius: 9px; background: rgba(255, 255, 255, .55); color: #8a6100; display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; flex-shrink: 0; }
    .nvc-head h6 { margin: 0; font-size: .92rem; font-weight: 600; line-height: 1.2; color: #5c4400; }
    .nvc-head small { display: block; color: #8a6d1f; font-size: .74rem; }
    .nvc-close { margin-left: auto; border: 0; background: transparent; color: #8a6d1f; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; }
    .nvc-close:hover { background: rgba(0, 0, 0, .08); color: #3d2d00; }

    .nvc-body { padding: 12px 14px 4px; max-height: 52vh; overflow: auto; }
    .nvc-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-size: .74rem; color: #7a8a95; margin-bottom: 6px; }
    .nvc-chip { font-size: .64rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; border-radius: 999px; padding: 2px 8px; }
    .nvc-chip-nuevo      { background: #e3f5ec; color: #2f9e6b; }
    .nvc-chip-mejora     { background: #e2f1f8; color: #2b8fb8; }
    .nvc-chip-aviso      { background: #fbf1da; color: #c98a11; }
    .nvc-chip-correccion { background: #eee7f9; color: #8a5fc9; }
    .nvc-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--cmg-primary, #6eb5d0); display: inline-block; }
    .nvc-title { font-size: 1rem; font-weight: 600; line-height: 1.3; margin: 0 0 8px; }
    .nvc-content { color: #4b5c68; font-size: .86rem; }
    .nvc-content p { margin-bottom: .5rem; }
    .nvc-content h2, .nvc-content h3 { font-size: .92rem; font-weight: 600; margin: .6rem 0 .3rem; }
    .nvc-content ul, .nvc-content ol { padding-left: 1.2rem; margin-bottom: .5rem; }
    .nvc-links { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 8px; }
    .nvc-links a { font-size: .8rem; font-weight: 500; text-decoration: none; }
    .nvc-adj { margin-top: 10px; padding-top: 8px; border-top: 1px solid #e7eff3; }
    .nvc-adj-t { font-size: .72rem; color: #7a8a95; margin-bottom: 4px; }
    .nvc-adj a { display: flex; align-items: center; gap: 6px; font-size: .8rem; text-decoration: none; padding: 3px 0; }
    .nvc-adj img { width: 30px; height: 30px; object-fit: cover; border-radius: 4px; border: 1px solid #d9e6ec; }

    .nvc-foot { display: flex; align-items: center; gap: 6px; padding: 8px 10px 10px 14px; border-top: 1px solid #e7eff3; background: #f6fafc; }
    .nvc-nav { display: inline-flex; align-items: center; gap: 2px; font-size: .76rem; color: #7a8a95; font-variant-numeric: tabular-nums; }
    .nvc-nav button { border: 1px solid #d9e6ec; background: #fff; color: #4b5c68; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
    .nvc-nav button:disabled { opacity: .35; }
    .nvc-foot .nvc-todas { font-size: .76rem; text-decoration: none; margin-left: 6px; }
    .nvc-foot .nvc-sp { flex: 1; }

    /* Desplegable del megáfono (navbar): lista de novedades vigentes */
    .cmg-novedades-menu .cmg-novedades-item { white-space: normal; padding: 6px 12px; display: flex; gap: 8px; align-items: flex-start; cursor: pointer; }
    .cmg-novedades-menu .cmg-novedades-item .nvc-chip { flex-shrink: 0; margin-top: 2px; }
    .cmg-novedades-menu .cmg-novedades-item .nvm-t { font-size: .84rem; line-height: 1.25; color: #1f2d38; }
    .cmg-novedades-menu .cmg-novedades-item.leida .nvm-t { color: #6c7a86; font-weight: 400; }
    .cmg-novedades-menu .cmg-novedades-item:not(.leida) .nvm-t { font-weight: 600; }
    .cmg-novedades-menu .cmg-novedades-item .nvm-f { font-size: .7rem; color: #7a8a95; }
    .cmg-novedades-menu .nvc-dot { flex-shrink: 0; margin-top: 7px; }
    .cmg-novedades-menu .nvm-lista { max-height: 380px; overflow: auto; }
</style>

<div class="nvc-card" id="cmgNovedadesCard" role="dialog" aria-labelledby="cmgNovedadesTitulo" aria-live="polite" hidden>
    <div class="nvc-head">
        <span class="nvc-ico"><i class="bi bi-megaphone-fill"></i></span>
        <div class="flex-grow-1 text-truncate">
            <h6 id="cmgNovedadesTitulo">Novedades de CaMaGaRe ERP</h6>
            <small id="cmgNovedadesSub">&nbsp;</small>
        </div>
        <button type="button" class="nvc-close" id="cmgNovedadesCerrar" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="nvc-body" id="cmgNovedadesBody"></div>
    <div class="nvc-foot">
        <span class="nvc-nav">
            <button type="button" id="cmgNovedadesPrev" title="Anterior"><i class="bi bi-chevron-left"></i></button>
            <span id="cmgNovedadesPos" class="px-1">1 / 1</span>
            <button type="button" id="cmgNovedadesNext" title="Siguiente"><i class="bi bi-chevron-right"></i></button>
        </span>
        <a href="<?= $nvBase ?>/novedades-sistema" class="nvc-todas">Ver todas</a>
        <span class="nvc-sp"></span>
        <button type="button" class="btn btn-sm btn-primary" id="cmgNovedadesEntendido"><i class="bi bi-check2 me-1"></i>Entendido</button>
    </div>
</div>

<script>
(function () {
    var BASE = '<?= $nvBase ?>';
    var URL_ESTADO   = BASE + '/novedades-sistema/estado';
    var URL_LEIDAS   = BASE + '/novedades-sistema/marcar-leidas';
    var MIN_ANCHO_PC = 992;

    var card = document.getElementById('cmgNovedadesCard');
    if (!card) return;
    var bodyEl = document.getElementById('cmgNovedadesBody');
    var subEl  = document.getElementById('cmgNovedadesSub');
    var posEl  = document.getElementById('cmgNovedadesPos');
    var btnPrev = document.getElementById('cmgNovedadesPrev');
    var btnNext = document.getElementById('cmgNovedadesNext');
    var btnOk   = document.getElementById('cmgNovedadesEntendido');

    var novedades = [];
    var pendientes = 0;
    var idx = 0;
    var abierta = false;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        });
    }

    function pintarBadge() {
        document.querySelectorAll('.cmg-novedades-wrap').forEach(function (w) {
            var b = w.querySelector('.cmg-novedades-badge');
            if (b) b.textContent = pendientes;
            w.classList.toggle('d-none', novedades.length === 0);
            if (b) b.classList.toggle('d-none', pendientes === 0);
        });
        pintarMenu();
    }

    // Desplegable del megáfono: todas las vigentes (sin leer primero); clic = abrir la tarjeta ahí.
    function pintarMenu() {
        var menu = document.getElementById('cmgNovedadesMenu');
        if (!menu) return;
        var html = '<li><h6 class="dropdown-header d-flex align-items-center"><i class="bi bi-megaphone me-1"></i>Novedades vigentes'
                 + (pendientes > 0 ? '<span class="badge bg-warning text-dark rounded-pill ms-auto">' + pendientes + ' sin leer</span>' : '')
                 + '</h6></li>';
        if (!novedades.length) {
            html += '<li><span class="dropdown-item-text text-muted small">No hay novedades por ahora.</span></li>';
        } else {
            html += '<li><div class="nvm-lista">';
            novedades.forEach(function (n, i) {
                html += '<a class="dropdown-item cmg-novedades-item' + (n.leida ? ' leida' : '') + '" href="#" data-idx="' + i + '">'
                      +   '<span class="nvc-chip nvc-chip-' + esc(n.tipo) + '">' + esc(n.tipo_label) + '</span>'
                      +   '<span class="flex-grow-1"><span class="nvm-t d-block">' + esc(n.titulo) + '</span>'
                      +     '<span class="nvm-f">' + esc(n.fecha) + (n.modulo ? ' · ' + esc(n.modulo) : '') + '</span></span>'
                      +   (n.leida ? '' : '<span class="nvc-dot" title="Sin leer"></span>')
                      + '</a>';
            });
            html += '</div></li>';
        }
        html += '<li><hr class="dropdown-divider my-1"></li>'
              + '<li><a class="dropdown-item small text-center" href="' + BASE + '/novedades-sistema"><i class="bi bi-list-ul me-1"></i>Ver todas las novedades</a></li>';
        menu.innerHTML = html;
    }

    function pintarAdjuntos(lista) {
        if (!lista || !lista.length) return '';
        var html = '<div class="nvc-adj"><div class="nvc-adj-t"><i class="bi bi-paperclip me-1"></i>Archivos adjuntos</div>';
        lista.forEach(function (a) {
            var vista = a.es_imagen
                ? '<img src="' + esc(a.url_vista) + '" alt="">'
                : '<i class="bi ' + esc(a.icono) + ' fs-5"></i>';
            html += '<a href="' + esc(a.url) + '" title="Descargar">' + vista
                  + '<span class="text-truncate">' + esc(a.nombre) + '</span>'
                  + (a.tamano ? '<span class="text-muted flex-shrink-0">' + esc(a.tamano) + '</span>' : '')
                  + '<i class="bi bi-download text-muted flex-shrink-0 ms-auto"></i></a>';
        });
        return html + '</div>';
    }

    function pintar() {
        if (!novedades.length) {
            bodyEl.innerHTML = '<div class="text-muted small py-3 text-center">No hay novedades por ahora.</div>';
            subEl.textContent = '';
            posEl.textContent = '0 / 0';
            btnPrev.disabled = btnNext.disabled = true;
            btnOk.disabled = true;
            return;
        }
        if (idx < 0) idx = 0;
        if (idx > novedades.length - 1) idx = novedades.length - 1;
        var n = novedades[idx];

        subEl.textContent = pendientes > 0
            ? (pendientes + (pendientes === 1 ? ' novedad sin leer' : ' novedades sin leer'))
            : 'Estás al día';
        posEl.textContent = (idx + 1) + ' / ' + novedades.length;
        btnPrev.disabled = idx === 0;
        btnNext.disabled = idx >= novedades.length - 1;
        btnOk.disabled = !!n.leida;
        btnOk.innerHTML = n.leida ? '<i class="bi bi-check2-all me-1"></i>Leída' : '<i class="bi bi-check2 me-1"></i>Entendido';

        bodyEl.innerHTML =
              '<div class="nvc-meta">'
            +   '<span class="nvc-chip nvc-chip-' + esc(n.tipo) + '">' + esc(n.tipo_label) + '</span>'
            +   '<span>' + esc(n.fecha) + '</span>'
            +   (n.modulo ? '<span>·</span><span>' + esc(n.modulo) + '</span>' : '')
            +   (n.leida ? '' : '<span class="nvc-dot ms-auto" title="Sin leer"></span>')
            + '</div>'
            + '<h6 class="nvc-title">' + esc(n.titulo) + '</h6>'
            + '<div class="nvc-content">' + n.contenido + '</div>'   // HTML saneado en el servidor
            + '<div class="nvc-links">'
            +   (n.url_modulo ? '<a href="' + esc(n.url_modulo) + '"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir ' + esc(n.modulo || 'el módulo') + '</a>' : '')
            +   (n.url_manual ? '<a href="' + esc(n.url_manual) + '" target="_blank" rel="noopener"><i class="bi bi-journal-bookmark me-1"></i>Guía en el manual</a>' : '')
            +   (n.url_enlace ? '<a href="' + esc(n.url_enlace) + '"' + (n.enlace_externo ? ' target="_blank" rel="noopener"' : '') + '><i class="bi bi-link-45deg me-1"></i>Abrir enlace</a>' : '')
            + '</div>'
            + pintarAdjuntos(n.adjuntos);
        bodyEl.scrollTop = 0;
    }

    function primeraSinLeer() {
        for (var i = 0; i < novedades.length; i++) if (!novedades[i].leida) return i;
        return 0;
    }

    function abrir(en) {
        idx = (typeof en === 'number' && en >= 0 && en < novedades.length) ? en : primeraSinLeer();
        pintar();
        card.hidden = false;
        // Dos frames para que la transición arranque desde el estado oculto.
        requestAnimationFrame(function () { requestAnimationFrame(function () { card.classList.add('nvc-visible'); }); });
        abierta = true;
    }

    // Cerrar no marca nada: mientras la novedad siga sin leer, la tarjeta
    // volverá a aparecer en el siguiente inicio de sesión.
    function cerrar() {
        if (!abierta) return;
        abierta = false;
        card.classList.remove('nvc-visible');
        setTimeout(function () { if (!abierta) card.hidden = true; }, 350);
    }
    window.CMG_NOVEDADES = { abrir: abrir, cerrar: cerrar };

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body || ''
        }).then(function (r) { return r.json(); });
    }

    btnPrev.addEventListener('click', function () { idx--; pintar(); });
    btnNext.addEventListener('click', function () { idx++; pintar(); });

    // "Entendido": marca leída la novedad en pantalla y salta a la siguiente
    // sin leer; si ya no queda ninguna, cierra la tarjeta.
    btnOk.addEventListener('click', function () {
        var n = novedades[idx];
        if (!n || n.leida) return;
        btnOk.disabled = true;
        post(URL_LEIDAS, 'ids[]=' + encodeURIComponent(n.id)).then(function (d) {
            if (!d || !d.ok) throw new Error((d && d.error) || 'No se pudo registrar.');
            n.leida = true;
            pendientes = Math.max(0, pendientes - 1);
            pintarBadge();   // también refresca el desplegable del megáfono
            if (pendientes > 0) {
                idx = primeraSinLeer();
                pintar();
            } else {
                if (window.Toast) Toast.fire({ icon: 'success', title: 'Estás al día con las novedades' });
                cerrar();
            }
        }).catch(function (e) {
            btnOk.disabled = false;
            if (window.Swal) Swal.fire('No se pudo guardar', e.message, 'error');
        });
    });

    // La X (o Esc) solo cierra la tarjeta.
    document.getElementById('cmgNovedadesCerrar').addEventListener('click', function () { cerrar(); });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && abierta) cerrar();
    });

    // Clic fuera de la tarjeta = cerrar.
    // Se ignoran los clics dentro de la tarjeta, en el megáfono/su desplegable
    // (que es quien la abre) y en diálogos emergentes (SweetAlert).
    document.addEventListener('click', function (ev) {
        if (!abierta) return;
        if (ev.target.closest('#cmgNovedadesCard, .cmg-novedades-wrap, .swal2-container')) return;
        cerrar();
    });

    // Elegir una novedad en el desplegable del megáfono: abre la tarjeta en esa novedad.
    document.addEventListener('click', function (ev) {
        var item = ev.target.closest('.cmg-novedades-item');
        if (!item) return;
        ev.preventDefault();
        abrir(parseInt(item.getAttribute('data-idx'), 10));
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (window.innerWidth < MIN_ANCHO_PC) return;   // solo PC
        // auto=1: esta página puede abrir la tarjeta sola; el servidor concede
        // ese turno una sola vez por inicio de sesión.
        var puedeAuto = !window.CMG_NOVEDADES_NO_AUTO;
        fetch(URL_ESTADO + (puedeAuto ? '?auto=1' : ''), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) return;
                novedades  = d.novedades || [];
                pendientes = d.pendientes || 0;
                pintarBadge();
                if (d.mostrar && puedeAuto) {
                    // Pequeña espera para que la página termine de pintarse y la
                    // tarjeta "entre" después, no de golpe con la carga.
                    setTimeout(abrir, 600);
                }
            })
            .catch(function () {});
    });
})();
</script>
