<?php
/** @var string $titulo */
/** @var array $permisos */

$base = BASE_URL;
$urlModulo = rtrim($base, '/') . '/modulos/plantillas-whatsapp';
$urlSearchAjax = $urlModulo . '/searchAjax';
$urlSincronizarAjax = $urlModulo . '/sincronizarAjax';
$urlStore = $urlModulo . '/store';

// Valores iniciales por defecto (como en proveedores)
$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'nombre';
$ordenDir   = $ordenDir ?? 'asc';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .wa-header {
        flex-shrink: 0;
    }

    .wa-scroll {
        max-height: calc(100vh - 240px);
        overflow-y: auto;
    }

    .wa-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .plantilla-row {
        cursor: pointer;
    }

    .plantilla-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="wa-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="fab fa-whatsapp text-success"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="btn-group">
        <?php if (in_array('crear', $permisos)): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="WA_abrirModalCrear()">
                <i class="fas fa-plus"></i> Nueva Plantilla
            </button>
        <?php endif; ?>
        <?php if (in_array('actualizar', $permisos)): ?>
            <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="WA_sincronizarPlantillas()">
                <i class="fas fa-sync-alt"></i> Sincronizar Plantillas
            </button>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/modal_plantilla_whatsapp.php'; ?>

<!-- Modal Detalles Plantilla -->
<div class="modal fade" id="modalDetallesPlantilla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-whatsapp text-success me-2"></i>Detalles de Plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3" id="divDetallesPlantilla">
                <div class="text-center text-muted py-4">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <p class="mb-0">Cargando detalles...</p>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0 rounded-bottom-3">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Probar Envío -->
<div class="modal fade" id="modalProbarEnvio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-send text-primary me-2"></i>Probar Envío</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formProbarEnvio">
                <div class="modal-body pt-3">
                    <input type="hidden" name="id_plantilla" id="testIdPlantilla">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Número de Teléfono <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="telefono" class="form-control" placeholder="Ej: 593981234567" required>
                        </div>
                        <div class="form-text">Debe incluir el código de país sin el signo +, ej: 593 para Ecuador.</div>
                    </div>

                    <div id="divFormVariables" class="bg-light p-3 rounded border d-none">
                        <!-- Aquí se inyectarán los inputs de las variables y/o el adjunto -->
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 rounded-bottom-3">
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="btnEnviarPrueba">
                        <i class="bi bi-send me-1"></i> Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2">
            <!-- Si deseas filtros avanzados, aquí puedes usar FiltrosBusqueda. Por ahora, un buscador simple. -->
            <div id="fbBuscadorWA" style="width: 480px;">
                <input type="text" id="buscarPlantilla" class="form-control form-control-sm w-100" placeholder="Buscar plantilla..." value="<?= htmlspecialchars($buscar) ?>">
            </div>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre'   => 'Nombre',
                    'categoria'=> 'Categoría',
                    'idioma'   => 'Idioma',
                    'estado'   => 'Estado (Meta)'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], 'modulos/plantillas-whatsapp') ?>

                <a id="btnExportPdf" href="#" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="#" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>

                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="wa-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="categoria" data-col="categoria">Categoría <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="idioma" data-col="idioma">Idioma <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="estado" data-col="estado">Estado (Meta) <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyPlantillas">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fab fa-whatsapp fs-3 d-block mb-2 text-success opacity-50"></i>
                                Aún no hay plantillas sincronizadas. Configura la API y presiona "Sincronizar Plantillas".
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="plantilla-row" role="button" tabindex="0">
                                <td class="ps-3" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td data-col="categoria"><?= htmlspecialchars($r['categoria'] ?? '') ?></td>
                                <td data-col="idioma"><?= htmlspecialchars($r['idioma'] ?? '') ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><?= htmlspecialchars($r['estado'] ?? 'APPROVED') ?></span>
                                </td>
                                <td class="text-center pe-3">
                                    <button class="btn btn-sm btn-outline-secondary me-1" title="Ver detalles" onclick="WA_verDetalles(<?= $r['id'] ?>)"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-sm btn-outline-primary" title="Probar Envío" onclick="WA_abrirModalProbar(<?= $r['id'] ?>)"><i class="bi bi-send"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlModulo ?>';
        window.WA_URL_BASE = urlBase;
        const inputBuscar = document.getElementById('buscarPlantilla');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir = '<?= $ordenDir ?>';
        window.currentPage = <?= $page ?>;

        let timerId;

        function debounce(func, delay = 350) {
            return (...args) => {
                clearTimeout(timerId);
                timerId = setTimeout(() => func.apply(this, args), delay);
            };
        }

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            // Preparar para cuando el controlador tenga searchAjax
            console.log(`Buscando: ${term}, página: ${page}, sort: ${window.currentSort}, dir: ${window.currentDir}`);
            
            // Lógica de flechas para el ordenamiento
            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                const field = th.dataset.sort;
                if (field === window.currentSort) {
                    icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        };

        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sort;
                if (window.currentSort === f) window.currentDir = (window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                else {
                    window.currentSort = f;
                    window.currentDir = 'ASC';
                }
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('plantillas_whatsapp', window.currentSort, window.currentDir);
                }
                fetchSearch(1);
            });
        });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));
    })();
</script>

<script src="<?= $base ?>/js/modulos/plantillas_whatsapp.js?v=<?= time() ?>"></script>