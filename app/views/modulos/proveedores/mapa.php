<?php
/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $proveedores */
/** @var int $sinCoordenadas */

$base            = BASE_URL;
$urlBaseProv     = rtrim($base, '/') . '/modulos/proveedores';
$proveedores     = $proveedores ?? [];
$sinCoordenadas  = $sinCoordenadas ?? 0;
$totalGeo        = count($proveedores);
?>
<?php
if (!defined('LEAFLET_LOADED')) {
    define('LEAFLET_LOADED', true);
    echo '<link rel="stylesheet" href="' . rtrim(BASE_URL, '/') . '/vendor/leaflet/leaflet.css">';
    echo '<script src="' . rtrim(BASE_URL, '/') . '/vendor/leaflet/leaflet.js"></script>';
}
?>
<style>
    #mapa-proveedores {
        height: calc(100dvh - 220px);
        min-height: 400px;
        border-radius: 10px;
        border: 1px solid #dee2e6;
    }
</style>

<!-- Encabezado -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-map text-primary me-2"></i> <?= htmlspecialchars($titulo) ?>
    </h5>
    <a href="<?= $urlBaseProv ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
    </a>
</div>

<!-- Estadísticas y filtros -->
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body py-2 px-3 d-flex align-items-center flex-wrap gap-3">
        <span class="small text-muted">
            <i class="bi bi-pin-map-fill text-success me-1"></i>
            <strong><?= $totalGeo ?></strong> con ubicación
        </span>
        <?php if ($sinCoordenadas > 0): ?>
            <span class="small text-muted">
                <i class="bi bi-geo-alt text-danger me-1"></i>
                <strong><?= $sinCoordenadas ?></strong> sin geocodificar
            </span>
        <?php endif; ?>
        <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
            <!-- Filtro estado -->
            <div class="btn-group btn-group-sm" id="filtroEstado">
                <button type="button" class="btn btn-outline-secondary active" data-filtro="todos">Todos</button>
                <button type="button" class="btn btn-outline-success" data-filtro="activos">Activos</button>
                <button type="button" class="btn btn-outline-secondary" data-filtro="inactivos">Inactivos</button>
            </div>
            <!-- Buscador -->
            <div class="input-group input-group-sm" style="width:240px;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="buscarMapa" class="form-control border-start-0 ps-0 shadow-none" placeholder="Buscar proveedor...">
            </div>
        </div>
    </div>
</div>

<?php if (empty($proveedores)): ?>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-map fs-1 d-block mb-2 text-secondary opacity-50"></i>
            <p class="mb-1 fw-medium">Aún no hay proveedores geocodificados.</p>
            <p class="small">Abre la ficha de un proveedor, ve a la pestaña <strong>Ubicación</strong> y haz clic en <em>Obtener desde dirección</em>.</p>
            <a href="<?= $urlBaseProv ?>" class="btn btn-primary btn-sm mt-2">
                <i class="bi bi-arrow-left me-1"></i> Ir al listado
            </a>
        </div>
    </div>
<?php else: ?>
    <div id="mapa-proveedores"></div>
<?php endif; ?>

<script>
    const PROVEEDORES_MAPA    = <?= json_encode($proveedores) ?>;
    const URL_BASE_PROVEEDORES = '<?= $urlBaseProv ?>';

    document.addEventListener('DOMContentLoaded', () => {
        if (!PROVEEDORES_MAPA.length) return;

        // Inicializar mapa centrado en Ecuador
        const mapa = L.map('mapa-proveedores').setView([-1.8312, -78.1834], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(mapa);

        // Icono personalizado
        function crearIcono(color) {
            return L.divIcon({
                className: '',
                html: `<div style="
                    width:28px; height:28px; border-radius:50% 50% 50% 0;
                    background:${color}; border:2px solid #fff;
                    box-shadow:0 2px 6px rgba(0,0,0,.35);
                    transform:rotate(-45deg);
                "></div>`,
                iconSize: [28, 28],
                iconAnchor: [14, 28],
                popupAnchor: [0, -30],
            });
        }

        const iconoActivo   = crearIcono('#198754');
        const iconoInactivo = crearIcono('#6c757d');

        // Crear marcadores
        const marcadores = PROVEEDORES_MAPA.map(p => {
            const activo   = p.status === true || p.status === 't' || p.status === '1' || p.status === 1;
            const icono    = activo ? iconoActivo : iconoInactivo;
            const badge    = activo
                ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Activo</span>'
                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 small">Inactivo</span>';
            const fecha    = p.geocodificado_en ? `<div class="text-muted mt-1" style="font-size:0.7rem;"><i class="bi bi-clock me-1"></i>${p.geocodificado_en}</div>` : '';

            const popup = `
                <div style="min-width:220px; max-width:280px;">
                    <div class="fw-bold mb-1">${p.razon_social}</div>
                    ${p.nombre_comercial ? `<div class="text-muted small mb-1">${p.nombre_comercial}</div>` : ''}
                    <div class="mb-1">${badge}</div>
                    ${p.identificacion ? `<div class="small"><i class="bi bi-card-text me-1 text-muted"></i>${p.identificacion}</div>` : ''}
                    ${p.telefono       ? `<div class="small"><i class="bi bi-telephone me-1 text-muted"></i>${p.telefono}</div>` : ''}
                    ${p.email          ? `<div class="small"><i class="bi bi-envelope me-1 text-muted"></i>${p.email}</div>` : ''}
                    ${p.direccion      ? `<div class="small"><i class="bi bi-geo-alt me-1 text-muted"></i>${p.direccion}</div>` : ''}
                    ${p.nombre_ciudad  ? `<div class="small"><i class="bi bi-buildings me-1 text-muted"></i>${p.nombre_ciudad}${p.nombre_provincia ? ', ' + p.nombre_provincia : ''}</div>` : ''}
                    ${fecha}
                    <div class="mt-2">
                        <a href="#" onclick="event.preventDefault(); document.dispatchEvent(new CustomEvent('abrirProvModal', {detail: ${p.id}}))"
                           class="btn btn-outline-primary btn-sm w-100" style="font-size:0.75rem;">
                            <i class="bi bi-pencil me-1"></i> Ver ficha
                        </a>
                    </div>
                </div>`;

            const marker = L.marker([parseFloat(p.latitud), parseFloat(p.longitud)], { icon: icono })
                .addTo(mapa)
                .bindPopup(popup);

            return {
                marker,
                activo,
                texto: [p.razon_social, p.nombre_comercial, p.identificacion, p.email, p.telefono, p.direccion, p.nombre_ciudad, p.nombre_provincia]
                    .filter(Boolean).join(' ').toLowerCase()
            };
        });

        // Ajustar vista a todos los marcadores
        if (marcadores.length) {
            const group = L.featureGroup(marcadores.map(m => m.marker));
            mapa.fitBounds(group.getBounds().pad(0.1));
        }

        // Filtrado
        let filtroActual = 'todos';
        let textoBusqueda = '';

        function aplicarFiltros() {
            marcadores.forEach(({ marker, activo, texto }) => {
                const pasaEstado = filtroActual === 'todos'
                    || (filtroActual === 'activos'   && activo)
                    || (filtroActual === 'inactivos' && !activo);
                const pasaTexto = textoBusqueda === '' || texto.includes(textoBusqueda);
                if (pasaEstado && pasaTexto) {
                    mapa.addLayer(marker);
                } else {
                    mapa.removeLayer(marker);
                }
            });
        }

        document.getElementById('filtroEstado')?.addEventListener('click', e => {
            const btn = e.target.closest('[data-filtro]');
            if (!btn) return;
            document.querySelectorAll('#filtroEstado [data-filtro]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filtroActual = btn.dataset.filtro;
            aplicarFiltros();
        });

        let debounceTimer;
        document.getElementById('buscarMapa')?.addEventListener('input', e => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                textoBusqueda = e.target.value.toLowerCase().trim();
                aplicarFiltros();
            }, 300);
        });
    });
</script>
