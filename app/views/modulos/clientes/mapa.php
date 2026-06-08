<?php
/** @var string  $titulo */
/** @var array   $perm */
/** @var string  $rutaModulo */
/** @var array   $clientes */
/** @var int     $totalClientes */
/** @var int     $sinCoordenadas */

$urlBase = rtrim(BASE_URL, '/') . '/' . ltrim($rutaModulo, '/');
?>

<!-- Leaflet CSS local -->
<link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/vendor/leaflet/leaflet.css">

<style>
    #mapa_clientes_principal {
        height: calc(100dvh - 220px);
        min-height: 400px;
        border-radius: 0 0 12px 12px;
        z-index: 0;
    }
    .mapa-stat-card {
        border-radius: 8px;
        font-size: 0.78rem;
    }
    .mapa-buscador {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 999;
        width: 240px;
    }
    .leaflet-popup-content-wrapper { border-radius: 10px; }
    .popup-cliente-nombre { font-weight: 600; font-size: 0.9rem; color: #212529; }
    .popup-cliente-info   { font-size: 0.78rem; color: #6c757d; margin-top: 4px; }
    .popup-cliente-info i { width: 14px; }
    .popup-btn-editar {
        display: inline-block;
        margin-top: 8px;
        font-size: 0.75rem;
        padding: 2px 10px;
        border-radius: 20px;
        background: #0d6efd;
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        border: none;
    }
    .popup-btn-editar:hover { background: #0a58ca; color: #fff; }
</style>

<!-- Encabezado -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= $urlBase ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver al listado
        </a>
        <h5 class="mb-0 fw-bold"><i class="bi bi-map me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <div class="mapa-stat-card px-3 py-1 bg-success bg-opacity-10 border border-success border-opacity-25 text-success d-flex align-items-center gap-2">
            <i class="bi bi-geo-alt-fill"></i>
            <span><strong><?= $totalClientes ?></strong> con ubicación</span>
        </div>
        <?php if ($sinCoordenadas > 0): ?>
        <div class="mapa-stat-card px-3 py-1 bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning d-flex align-items-center gap-2">
            <i class="bi bi-geo-alt"></i>
            <span><strong><?= $sinCoordenadas ?></strong> sin ubicación</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($totalClientes === 0): ?>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-geo-alt fs-1 d-block mb-3"></i>
            <h6 class="fw-semibold">Ningún cliente tiene ubicación registrada</h6>
            <p class="small mb-3">Abra la ficha de un cliente, vaya a la pestaña <strong>Ubicación</strong> y use el botón <em>"Obtener desde dirección"</em> para geocodificarlo.</p>
            <a href="<?= $urlBase ?>" class="btn btn-primary btn-sm px-4">
                <i class="bi bi-people me-1"></i> Ir al listado de clientes
            </a>
        </div>
    </div>
<?php else: ?>

<!-- Controles sobre el mapa -->
<div class="card border-0 shadow-sm rounded-3 overflow-hidden">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <!-- Filtro de estado -->
            <div class="btn-group btn-group-sm" id="filtroEstado">
                <button type="button" class="btn btn-outline-secondary active" data-filtro="todos">Todos</button>
                <button type="button" class="btn btn-outline-success" data-filtro="activos">Activos</button>
                <button type="button" class="btn btn-outline-secondary" data-filtro="inactivos">Inactivos</button>
            </div>
            <span class="text-muted small" id="mapaContadorVisibles">
                Mostrando <strong id="numVisibles"><?= $totalClientes ?></strong> clientes
            </span>
        </div>
        <!-- Buscador sobre el mapa -->
        <div style="width: 260px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="mapaFiltroTexto" class="form-control form-control-sm" placeholder="Buscar cliente en el mapa...">
            </div>
        </div>
    </div>

    <!-- Mapa principal -->
    <div class="position-relative">
        <div id="mapa_clientes_principal"></div>
    </div>

    <!-- Leyenda -->
    <div class="card-footer bg-white border-top py-1 px-3 d-flex gap-4 small text-muted">
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#198754;margin-right:4px;"></span>Cliente activo</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#6c757d;margin-right:4px;"></span>Cliente inactivo</span>
        <span class="ms-auto"><i class="bi bi-hand-index me-1"></i>Haga clic en un marcador para ver la información</span>
    </div>
</div>

<!-- Datos de clientes para el mapa (JSON) -->
<script>
const CLIENTES_MAPA = <?= json_encode(array_map(function($c) {
    return [
        'id'               => (int)$c['id'],
        'nombre'           => $c['nombre'] ?? '',
        'identificacion'   => $c['identificacion'] ?? '',
        'telefono'         => $c['telefono'] ?? '',
        'email'            => $c['email'] ?? '',
        'direccion'        => $c['direccion'] ?? '',
        'nombre_ciudad'    => $c['nombre_ciudad'] ?? '',
        'nombre_provincia' => $c['nombre_provincia'] ?? '',
        'nombre_vendedor'  => $c['nombre_vendedor'] ?? '',
        'status'           => (int)($c['status'] ?? 1),
        'lat'              => (float)$c['latitud'],
        'lng'              => (float)$c['longitud'],
    ];
}, $clientes), JSON_UNESCAPED_UNICODE) ?>;
const URL_BASE_CLIENTES = '<?= $urlBase ?>';
</script>

<script src="<?= rtrim(BASE_URL, '/') ?>/vendor/leaflet/leaflet.js"></script>
<script>
(function () {
    'use strict';

    // ── Iconos personalizados ───────────────────────────────────────────────
    function crearIcono(color) {
        return L.divIcon({
            className: '',
            html: `<div style="
                width:16px;height:16px;border-radius:50%;
                background:${color};border:2px solid #fff;
                box-shadow:0 1px 4px rgba(0,0,0,.4);">
            </div>`,
            iconSize:   [16, 16],
            iconAnchor: [8, 8],
            popupAnchor:[0, -10]
        });
    }

    const iconoActivo   = crearIcono('#198754');
    const iconoInactivo = crearIcono('#6c757d');

    // ── Inicializar mapa ────────────────────────────────────────────────────
    const mapa = L.map('mapa_clientes_principal', { zoomControl: true });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(mapa);

    // ── Crear marcadores ────────────────────────────────────────────────────
    const marcadores = [];

    CLIENTES_MAPA.forEach(c => {
        const icono = c.status === 1 ? iconoActivo : iconoInactivo;

        const infoLineas = [
            c.identificacion ? `<div class="popup-cliente-info"><i class="bi bi-card-text"></i> ${c.identificacion}</div>` : '',
            c.telefono       ? `<div class="popup-cliente-info"><i class="bi bi-telephone"></i> ${c.telefono}</div>`       : '',
            c.email          ? `<div class="popup-cliente-info"><i class="bi bi-envelope"></i> ${c.email}</div>`            : '',
            c.direccion      ? `<div class="popup-cliente-info"><i class="bi bi-geo"></i> ${c.direccion}</div>`             : '',
            (c.nombre_ciudad || c.nombre_provincia)
                ? `<div class="popup-cliente-info"><i class="bi bi-map"></i> ${[c.nombre_ciudad, c.nombre_provincia].filter(Boolean).join(', ')}</div>` : '',
            c.nombre_vendedor ? `<div class="popup-cliente-info"><i class="bi bi-person-badge"></i> ${c.nombre_vendedor}</div>` : '',
        ].filter(Boolean).join('');

        const estadoBadge = c.status === 1
            ? '<span style="font-size:0.65rem;padding:1px 6px;border-radius:10px;background:rgba(25,135,84,.12);color:#198754;border:1px solid rgba(25,135,84,.3);">Activo</span>'
            : '<span style="font-size:0.65rem;padding:1px 6px;border-radius:10px;background:rgba(108,117,125,.12);color:#6c757d;border:1px solid rgba(108,117,125,.3);">Inactivo</span>';

        const popupHtml = `
            <div style="min-width:200px;max-width:260px;">
                <div class="popup-cliente-nombre">${c.nombre} ${estadoBadge}</div>
                ${infoLineas}
                <button class="popup-btn-editar" onclick="abrirFichaCliente(${c.id})">
                    <i class="bi bi-pencil-square me-1"></i>Ver ficha
                </button>
            </div>`;

        const marker = L.marker([c.lat, c.lng], { icon: icono })
            .addTo(mapa)
            .bindPopup(popupHtml, { maxWidth: 280 });

        marker._clienteData = c;
        marcadores.push(marker);
    });

    // ── Ajustar vista al grupo de marcadores ────────────────────────────────
    if (marcadores.length > 0) {
        const grupo = L.featureGroup(marcadores);
        mapa.fitBounds(grupo.getBounds().pad(0.1));
    } else {
        mapa.setView([-1.8312, -78.1834], 7); // Centro de Ecuador
    }

    // ── Filtro de estado ────────────────────────────────────────────────────
    let filtroEstadoActual = 'todos';
    let textoBusqueda     = '';

    function actualizarMarcadores() {
        let visibles = 0;
        marcadores.forEach(m => {
            const c = m._clienteData;
            const pasaEstado =
                filtroEstadoActual === 'todos'    ? true :
                filtroEstadoActual === 'activos'  ? c.status === 1 :
                filtroEstadoActual === 'inactivos'? c.status !== 1 : true;

            const busq = textoBusqueda.toLowerCase();
            const pasaBusqueda = busq === '' || [
                c.nombre, c.identificacion, c.email,
                c.telefono, c.direccion, c.nombre_ciudad, c.nombre_provincia
            ].some(v => (v || '').toLowerCase().includes(busq));

            if (pasaEstado && pasaBusqueda) {
                if (!mapa.hasLayer(m)) mapa.addLayer(m);
                visibles++;
            } else {
                if (mapa.hasLayer(m)) mapa.removeLayer(m);
            }
        });
        document.getElementById('numVisibles').textContent = visibles;
    }

    // Botones de filtro estado
    document.querySelectorAll('#filtroEstado [data-filtro]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#filtroEstado [data-filtro]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filtroEstadoActual = this.dataset.filtro;
            actualizarMarcadores();
        });
    });

    // Buscador de texto
    let buscarTimer;
    document.getElementById('mapaFiltroTexto').addEventListener('input', function () {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(() => {
            textoBusqueda = this.value.trim();
            actualizarMarcadores();
        }, 300);
    });

    // ── Abrir ficha (redirige al listado con modal) ─────────────────────────
    window.abrirFichaCliente = function(id) {
        window.location.href = URL_BASE_CLIENTES + '?open_id=' + id;
    };

})();
</script>

<?php endif; ?>
