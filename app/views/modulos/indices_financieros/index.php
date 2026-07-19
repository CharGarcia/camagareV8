<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var string $fechaInicio */
/** @var string $fechaFin */
/** @var array $indices */
/** @var array $cuentasSinClasificar */
/** @var array $clasificacion */
/** @var array $grupos */
/** @var array $catalogoIndices */

$base = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$categorias = [
    'liquidez'      => ['titulo' => 'Liquidez', 'icono' => 'bi-droplet-half', 'color' => 'primary'],
    'endeudamiento' => ['titulo' => 'Endeudamiento', 'icono' => 'bi-bar-chart-steps', 'color' => 'warning'],
    'rentabilidad'  => ['titulo' => 'Rentabilidad', 'icono' => 'bi-graph-up-arrow', 'color' => 'success'],
    'actividad'     => ['titulo' => 'Actividad', 'icono' => 'bi-arrow-repeat', 'color' => 'info'],
];

$ifinFormatoValor = function (?float $valor, string $unidad): string {
    if ($valor === null) {
        return 'N/D';
    }
    return match ($unidad) {
        'porcentaje' => number_format($valor * 100, 2) . '%',
        'dias'       => number_format($valor, 1) . ' días',
        'monto'      => '$' . number_format($valor, 2),
        default      => number_format($valor, 2),
    };
};
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <form id="frmPeriodoIF" class="d-flex align-items-center gap-2" onsubmit="return false;">
        <label class="small text-muted mb-0">Desde</label>
        <input type="date" id="if-fecha-inicio" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($fechaInicio) ?>">
        <label class="small text-muted mb-0">Hasta</label>
        <input type="date" id="if-fecha-fin" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($fechaFin) ?>">
        <button type="button" class="btn btn-primary btn-sm" onclick="window.IF_recalcular()"><i class="bi bi-arrow-clockwise me-1"></i>Calcular</button>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="window.IF_imprimirPdf()" title="Imprimir PDF"><i class="bi bi-file-earmark-pdf"></i></button>
    </form>
</div>

<ul class="nav nav-tabs mb-3" id="ifTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#if-tab-tablero" type="button">Tablero</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#if-tab-clasificacion" type="button">
        Clasificación de Cuentas
        <span id="if-badge-sin-clasificar" class="badge bg-danger rounded-pill ms-1<?= empty($cuentasSinClasificar) ? ' d-none' : '' ?>"><?= count($cuentasSinClasificar) ?></span>
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#if-tab-grupos" type="button">Grupos Personalizados</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#if-tab-indices" type="button">Índices Personalizados</button></li>
</ul>

<div class="tab-content">

<!-- ═══════════════ TABLERO ═══════════════ -->
<div class="tab-pane fade show active" id="if-tab-tablero">
    <div id="if-tablero-contenido" class="row g-3">
        <?php foreach ($categorias as $catKey => $catInfo): ?>
            <div class="col-12">
                <h6 class="fw-bold text-<?= $catInfo['color'] ?>"><i class="bi <?= $catInfo['icono'] ?> me-1"></i><?= $catInfo['titulo'] ?></h6>
                <div class="row g-3 mb-2">
                    <?php if (empty($indices[$catKey])): ?>
                        <div class="col-12 text-muted small">Sin índices en esta categoría.</div>
                    <?php endif; ?>
                    <?php foreach (($indices[$catKey] ?? []) as $ind): ?>
                        <div class="col-md-3 col-sm-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-3">
                                    <div class="small text-muted text-truncate" title="<?= htmlspecialchars($ind['descripcion'] ?? '') ?>"><?= htmlspecialchars($ind['nombre']) ?></div>
                                    <div class="fs-4 fw-bold text-<?= $catInfo['color'] ?>"><?= $ifinFormatoValor($ind['valor'], $ind['unidad']) ?></div>
                                    <?php if (!empty($ind['interpretacion'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($ind['interpretacion']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══════════════ CLASIFICACIÓN DE CUENTAS ═══════════════ -->
<div class="tab-pane fade" id="if-tab-clasificacion">
    <p class="text-muted small">Cuentas de Activo y Pasivo que aún no están clasificadas entre Corriente / No Corriente. Mientras no se clasifiquen, no participan en Razón Corriente, Prueba Ácida ni Capital de Trabajo.</p>

    <div class="card cmg-table-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white py-2 px-3 border-bottom"><strong class="small">Cuentas sin clasificar</strong></div>
        <div class="card-body p-0">
            <div class="indices-financieros-scroll" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" data-col="codigo">Código</th>
                            <th data-col="nombre">Cuenta</th>
                            <th data-col="grupo" style="width: 260px;">Clasificar como</th>
                        </tr>
                    </thead>
                    <tbody id="if-tbody-sin-clasificar">
                        <?php if (empty($cuentasSinClasificar)): ?>
                            <tr class="if-fila-vacia"><td colspan="3" class="text-center py-4 text-muted">Todas las cuentas de Activo/Pasivo están clasificadas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cuentasSinClasificar as $c): ?>
                                <tr class="if-fila-cuenta" data-id-cuenta="<?= (int) $c['id'] ?>">
                                    <td class="ps-3"><?= htmlspecialchars($c['codigo']) ?></td>
                                    <td><?= htmlspecialchars($c['nombre']) ?></td>
                                    <td>
                                        <select class="form-select form-select-sm" onchange="window.IF_guardarClasificacion(<?= (int) $c['id'] ?>, this)">
                                            <option value="">Sin clasificar</option>
                                            <option value="ACTIVO_CORRIENTE" <?= $c['sugerencia'] === 'ACTIVO_CORRIENTE' ? 'selected' : '' ?>>Activo Corriente</option>
                                            <option value="ACTIVO_NO_CORRIENTE" <?= $c['sugerencia'] === 'ACTIVO_NO_CORRIENTE' ? 'selected' : '' ?>>Activo No Corriente</option>
                                            <option value="PASIVO_CORRIENTE" <?= $c['sugerencia'] === 'PASIVO_CORRIENTE' ? 'selected' : '' ?>>Pasivo Corriente</option>
                                            <option value="PASIVO_NO_CORRIENTE" <?= $c['sugerencia'] === 'PASIVO_NO_CORRIENTE' ? 'selected' : '' ?>>Pasivo No Corriente</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card cmg-table-card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom"><strong class="small">Cuentas ya clasificadas</strong></div>
        <div class="card-body p-0">
            <div class="indices-financieros-scroll" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" data-col="codigo">Código</th>
                            <th data-col="nombre">Cuenta</th>
                            <th data-col="grupo" style="width: 260px;">Grupo</th>
                        </tr>
                    </thead>
                    <tbody id="if-tbody-clasificadas">
                        <?php if (empty($clasificacion)): ?>
                            <tr class="if-fila-vacia"><td colspan="3" class="text-center py-4 text-muted">Aún no hay cuentas clasificadas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($clasificacion as $c): ?>
                                <tr class="if-fila-cuenta" data-id-cuenta="<?= (int) $c['id_cuenta'] ?>">
                                    <td class="ps-3"><?= htmlspecialchars($c['codigo']) ?></td>
                                    <td><?= htmlspecialchars($c['nombre']) ?></td>
                                    <td>
                                        <select class="form-select form-select-sm" onchange="window.IF_guardarClasificacion(<?= (int) $c['id_cuenta'] ?>, this)">
                                            <option value="ACTIVO_CORRIENTE" <?= $c['grupo'] === 'ACTIVO_CORRIENTE' ? 'selected' : '' ?>>Activo Corriente</option>
                                            <option value="ACTIVO_NO_CORRIENTE" <?= $c['grupo'] === 'ACTIVO_NO_CORRIENTE' ? 'selected' : '' ?>>Activo No Corriente</option>
                                            <option value="PASIVO_CORRIENTE" <?= $c['grupo'] === 'PASIVO_CORRIENTE' ? 'selected' : '' ?>>Pasivo Corriente</option>
                                            <option value="PASIVO_NO_CORRIENTE" <?= $c['grupo'] === 'PASIVO_NO_CORRIENTE' ? 'selected' : '' ?>>Pasivo No Corriente</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ GRUPOS PERSONALIZADOS ═══════════════ -->
<div class="tab-pane fade" id="if-tab-grupos">
    <div class="d-flex justify-content-end mb-2">
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.IF_abrirModalGrupo()"><i class="bi bi-plus-lg me-1"></i>Nuevo Grupo</button>
        <?php endif; ?>
    </div>
    <div class="card cmg-table-card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="indices-financieros-scroll" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" data-col="codigo">Código</th>
                            <th data-col="nombre">Nombre</th>
                            <th data-col="cuentas" class="text-center">Cuentas</th>
                            <th data-col="descripcion">Descripción</th>
                        </tr>
                    </thead>
                    <tbody id="if-tbody-grupos">
                        <?php if (empty($grupos)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No hay grupos personalizados todavía.</td></tr>
                        <?php else: ?>
                            <?php foreach ($grupos as $g): ?>
                                <tr role="button" data-id-grupo="<?= (int) $g['id'] ?>" onclick="window.IF_abrirModalGrupo(<?= (int) $g['id'] ?>)">
                                    <td class="ps-3"><?= htmlspecialchars($g['codigo']) ?></td>
                                    <td><?= htmlspecialchars($g['nombre']) ?></td>
                                    <td class="text-center"><?= (int) $g['total_cuentas'] ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($g['descripcion'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ ÍNDICES PERSONALIZADOS ═══════════════ -->
<div class="tab-pane fade" id="if-tab-indices">
    <div class="d-flex justify-content-end mb-2">
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.IF_abrirModalIndice()"><i class="bi bi-plus-lg me-1"></i>Nuevo Índice</button>
        <?php endif; ?>
    </div>
    <div class="card cmg-table-card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="indices-financieros-scroll" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" data-col="codigo">Código</th>
                            <th data-col="nombre">Nombre</th>
                            <th data-col="categoria">Categoría</th>
                            <th data-col="tipo">Tipo</th>
                            <th class="text-center" data-col="activo">Activo</th>
                        </tr>
                    </thead>
                    <tbody id="if-tbody-indices">
                        <?php foreach ($catalogoIndices as $ind): ?>
                            <?php $esEstandar = ($ind['tipo'] === 'estandar'); ?>
                            <tr role="button" onclick="<?= $esEstandar ? '' : 'window.IF_abrirModalIndice(' . (int) $ind['id'] . ')' ?>">
                                <td class="ps-3"><?= htmlspecialchars($ind['codigo']) ?></td>
                                <td><?= htmlspecialchars($ind['nombre']) ?></td>
                                <td><?= htmlspecialchars($ind['categoria']) ?></td>
                                <td><span class="badge <?= $esEstandar ? 'bg-secondary' : 'bg-primary' ?> bg-opacity-10 text-<?= $esEstandar ? 'secondary' : 'primary' ?>"><?= $esEstandar ? 'Estándar' : 'Personalizado' ?></span></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center m-0" onclick="event.stopPropagation();">
                                        <input class="form-check-input" type="checkbox" <?= ($ind['activo'] === true || $ind['activo'] === 't') ? 'checked' : '' ?> onchange="window.IF_cambiarActivoIndice(<?= (int) $ind['id'] ?>, this.checked)">
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Modal Grupo Personalizado -->
<div class="modal fade" id="modalIFGrupo" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><span id="ifGrupoModalTitulo">Nuevo Grupo</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="ifg-id" value="">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Código</label>
                        <input type="text" id="ifg-codigo" class="form-control form-control-sm text-uppercase" placeholder="EJ_MI_GRUPO" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Nombre</label>
                        <input type="text" id="ifg-nombre" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Descripción</label>
                        <input type="text" id="ifg-descripcion" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 position-relative">
                        <label class="form-label small fw-bold">Cuentas del grupo</label>
                        <input type="text" id="ifg-buscar-cuenta" class="form-control form-control-sm" placeholder="Buscar cuenta por código o nombre..." autocomplete="off">
                        <div class="list-group ifg-dropdown" id="ifg-dropdown-cuentas" style="position:absolute; z-index:1080; max-height:220px; overflow-y:auto; display:none; width: calc(100% - 1.5rem);"></div>
                        <div id="ifg-chips-cuentas" class="d-flex flex-wrap gap-1 mt-2"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" id="ifg-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.IF_eliminarGrupo()"><i class="bi bi-trash me-1"></i>Eliminar</button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="window.IF_guardarGrupo()"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Índice Personalizado -->
<div class="modal fade" id="modalIFIndice" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><span id="ifIndiceModalTitulo">Nuevo Índice Personalizado</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="ifi-id" value="">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Código</label>
                        <input type="text" id="ifi-codigo" class="form-control form-control-sm text-uppercase" placeholder="MI_INDICE" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Nombre</label>
                        <input type="text" id="ifi-nombre" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Categoría</label>
                        <select id="ifi-categoria" class="form-select form-select-sm">
                            <option value="liquidez">Liquidez</option>
                            <option value="endeudamiento">Endeudamiento</option>
                            <option value="rentabilidad">Rentabilidad</option>
                            <option value="actividad">Actividad</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Unidad</label>
                        <select id="ifi-unidad" class="form-select form-select-sm">
                            <option value="razon">Razón</option>
                            <option value="porcentaje">Porcentaje</option>
                            <option value="dias">Días</option>
                            <option value="monto">Monto</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Descripción</label>
                        <input type="text" id="ifi-descripcion" class="form-control form-control-sm">
                    </div>

                    <div class="col-12"><hr class="my-1"></div>

                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Numerador</label>
                        <div id="ifi-numerador-terminos" class="d-flex flex-column gap-1 mb-1"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.IF_agregarTermino('numerador')"><i class="bi bi-plus-lg me-1"></i>Agregar término</button>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="ifi-tiene-denominador" onchange="window.IF_toggleDenominador(this.checked)">
                            <label class="form-check-label small fw-bold" for="ifi-tiene-denominador">Dividir por un denominador</label>
                        </div>
                    </div>

                    <div class="col-12 d-none" id="ifi-bloque-denominador">
                        <label class="form-label small fw-bold mb-1">Denominador</label>
                        <div id="ifi-denominador-terminos" class="d-flex flex-column gap-1 mb-1"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.IF_agregarTermino('denominador')"><i class="bi bi-plus-lg me-1"></i>Agregar término</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" id="ifi-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.IF_eliminarIndice()"><i class="bi bi-trash me-1"></i>Eliminar</button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="window.IF_guardarIndice()"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.IF_URL_BASE = '<?= $urlBase ?>';
    window.IF_PERM = <?= json_encode($perm) ?>;
    window.IF_GRUPOS_ESTANDAR = [
        { codigo: 'ACTIVO_CORRIENTE', nombre: 'Activo Corriente' },
        { codigo: 'ACTIVO_NO_CORRIENTE', nombre: 'Activo No Corriente' },
        { codigo: 'PASIVO_CORRIENTE', nombre: 'Pasivo Corriente' },
        { codigo: 'PASIVO_NO_CORRIENTE', nombre: 'Pasivo No Corriente' },
    ];
    window.IF_GRUPOS_PERSONALIZADOS = <?= json_encode(array_map(fn ($g) => ['codigo' => $g['codigo'], 'nombre' => $g['nombre']], $grupos)) ?>;
    window.IF_FUENTES = [
        { codigo: 'ACTIVO_TOTAL', nombre: 'Activo Total' },
        { codigo: 'PASIVO_TOTAL', nombre: 'Pasivo Total' },
        { codigo: 'PATRIMONIO', nombre: 'Patrimonio' },
        { codigo: 'INGRESOS', nombre: 'Ingresos' },
        { codigo: 'COSTOS', nombre: 'Costos' },
        { codigo: 'GASTOS', nombre: 'Gastos' },
        { codigo: 'UTILIDAD_BRUTA', nombre: 'Utilidad Bruta' },
        { codigo: 'UTILIDAD_NETA', nombre: 'Utilidad Neta' },
        { codigo: 'CXC_SALDO', nombre: 'Saldo por Cobrar' },
        { codigo: 'CXP_SALDO', nombre: 'Saldo por Pagar' },
        { codigo: 'INVENTARIO_VALOR', nombre: 'Valor de Inventario' },
        { codigo: 'ACTIVO_FIJO_NETO', nombre: 'Activo Fijo Neto' },
        { codigo: 'VENTAS', nombre: 'Ventas del Período' },
        { codigo: 'COMPRAS', nombre: 'Compras del Período' },
    ];
    window.IF_INDICES_DATA = <?= json_encode(array_map(fn ($i) => ['id' => (int) $i['id'], 'codigo' => $i['codigo'], 'nombre' => $i['nombre'], 'categoria' => $i['categoria'], 'unidad' => $i['unidad'], 'descripcion' => $i['descripcion'], 'tipo' => $i['tipo'], 'formula' => $i['formula']], $catalogoIndices)) ?>;
</script>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/indices_financieros.js?v=<?= time() ?>"></script>
