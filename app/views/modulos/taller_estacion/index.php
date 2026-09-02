<?php
/**
 * Pantalla de estación del taller — página STANDALONE pensada para la tablet
 * que queda fija en cada departamento (mecánica, pintura, armado…).
 *
 * Igual que el KDS, el departamento va en la URL y no en la sesión: puede haber
 * varias tablets abiertas al mismo tiempo, cada una en su departamento, y cada
 * una debe quedarse en el suyo. Se refresca sola por polling (sin WebSockets,
 * por la restricción de infraestructura del servidor).
 *
 * Desde aquí el operario: toma el trabajo, agrega los repuestos y servicios que
 * usó, deja notas y fotos, y al terminar envía el vehículo al siguiente
 * departamento. Todo queda firmado con su departamento, su usuario y la hora.
 *
 * @var string $titulo
 * @var string $rutaModulo  modulos/taller-estacion — todos sus endpoints cuelgan de aquí
 * @var array  $perm
 * @var array  $departamentos
 * @var int    $idDepartamento
 * @var array  $ordenes
 * @var array  $empleados
 * @var array  $bodegas
 */
$base     = rtrim(BASE_URL ?? '', '/');
$rutaAjax = $base . '/' . $rutaModulo;

$depActual = null;
foreach ($departamentos as $d) {
    if ((int) $d['id'] === (int) $idDepartamento) { $depActual = $d; break; }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <?php require MVC_APP . "/views/partials/csrf.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html, body { height: 100%; }
        body { background: #14171c; color: #fff; -webkit-tap-highlight-color: transparent; }

        .tw-header { padding: 10px 16px; background: #1e222a; border-bottom: 1px solid #2c313a; }
        .tw-tabs { overflow-x: auto; white-space: nowrap; }
        .tw-tabs a {
            color: #8a94a6; text-decoration: none; padding: 8px 16px; border-radius: 999px;
            font-size: .9rem; font-weight: 600; display: inline-block;
        }
        .tw-tabs a.active { background: #0d6efd; color: #fff; }

        .tw-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; padding: 16px; }
        .tw-card { background: #1e222a; border: 1px solid #2c313a; border-radius: 14px; overflow: hidden; }
        .tw-card.urgente { border-color: #dc3545; }
        .tw-card.sin-aprobar { border-color: #ffc107; }

        .tw-card-header { padding: 12px 14px; background: #262b34; }
        .tw-placa { font-size: 1.5rem; font-weight: 800; letter-spacing: 1px; }
        .tw-veh { font-size: .86rem; color: #adb5bd; }
        .tw-tiempo { font-size: .78rem; color: #8a94a6; }
        .tw-motivo { padding: 10px 14px; font-size: .9rem; border-bottom: 1px solid #262b34; }
        .tw-body { padding: 10px 14px; }

        /* Botones grandes: se usan con guantes y con el dedo. */
        .tw-btn {
            border: none; border-radius: 10px; padding: 13px 10px; font-size: .92rem; font-weight: 600;
            width: 100%; color: #fff; background: #2c313a; display: flex; align-items: center;
            justify-content: center; gap: 6px;
        }
        .tw-btn:active { transform: scale(.98); }
        .tw-btn-primary { background: #0d6efd; }
        .tw-btn-success { background: #198754; }
        .tw-btn-warning { background: #fd7e14; }
        .tw-btn:disabled { opacity: .45; }
        .tw-acciones { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 0 14px 14px; }
        .tw-acciones .full { grid-column: 1 / -1; }

        .tw-empty { color: #6c757d; text-align: center; padding: 70px 20px; grid-column: 1 / -1; }
        .tw-chip {
            display: inline-block; padding: 2px 10px; border-radius: 999px;
            font-size: .72rem; font-weight: 700;
        }
        .tw-linea { font-size: .84rem; padding: 4px 0; border-bottom: 1px solid #262b34; color: #ced4da; }
        .tw-linea:last-child { border-bottom: none; }

        /* El modal de trabajo también es táctil: campos y botones amplios. */
        .modal-content.tw-modal { background: #1e222a; color: #fff; border: 1px solid #2c313a; }
        .tw-modal .form-control, .tw-modal .form-select {
            background: #262b34; border-color: #343a44; color: #fff; font-size: 1rem; padding: 10px 12px;
        }
        .tw-modal .form-control:focus, .tw-modal .form-select:focus {
            background: #262b34; color: #fff; border-color: #0d6efd; box-shadow: none;
        }
        .tw-modal .form-label { font-size: .8rem; color: #8a94a6; font-weight: 700; text-transform: uppercase; }
        .tw-modal .nav-link { color: #8a94a6; }
        .tw-modal .nav-link.active { background: #0d6efd; color: #fff; }
    </style>
</head>
<body>

<div class="tw-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3 flex-grow-1">
        <i class="bi bi-wrench-adjustable-circle fs-3"></i>
        <div>
            <div class="fw-bold"><?= $depActual ? htmlspecialchars($depActual['nombre']) : 'Estación del taller' ?></div>
            <div class="small text-secondary" id="tw-reloj"></div>
        </div>
        <div class="tw-tabs d-flex gap-1 ms-3 flex-grow-1">
            <?php if (empty($departamentos)): ?>
                <span class="text-muted small">No hay departamentos configurados. Créelos en «Departamentos del taller».</span>
            <?php endif; ?>
            <?php foreach ($departamentos as $d): ?>
                <a href="<?= $rutaAjax ?>?id_departamento=<?= (int) $d['id'] ?>"
                   class="<?= (int) $d['id'] === (int) $idDepartamento ? 'active' : '' ?>">
                    <i class="bi <?= htmlspecialchars($d['icono'] ?? 'bi-tools') ?>"></i>
                    <?= htmlspecialchars($d['nombre']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-light" onclick="twRefrescar()" title="Actualizar ahora">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
</div>

<div id="tw-grid" class="tw-grid"></div>

<!-- Modal de trabajo del departamento -->
<div class="modal fade" id="twModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content tw-modal">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="twModalTitulo">Trabajo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tw_id_orden">
                <input type="hidden" id="tw_id_etapa">

                <ul class="nav nav-pills nav-fill mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tw-pane-trabajo" type="button">Trabajo</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tw-pane-consumos" type="button">Repuestos <span id="tw_badge_lineas" class="badge bg-secondary">0</span></button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tw-pane-info" type="button">Vehículo</button></li>
                </ul>

                <div class="tab-content">
                    <!-- Trabajo realizado -->
                    <div class="tab-pane fade show active" id="tw-pane-trabajo">
                        <div class="mb-3">
                            <label class="form-label">Técnico responsable</label>
                            <select id="tw_empleado" class="form-select">
                                <option value="">— Seleccionar —</option>
                                <?php foreach (($empleados ?? []) as $e): ?>
                                    <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['nombres_apellidos'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">¿Qué se hizo en este departamento?</label>
                            <textarea id="tw_trabajo" class="form-control" rows="4"
                                      placeholder="Ej. Se desmontó la puerta trasera izquierda, se enderezó el panel y se aplicó masilla."></textarea>
                            <div class="form-text text-secondary">Este texto sale en el informe técnico que recibe el cliente.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <input type="text" id="tw_observaciones" class="form-control" placeholder="Algo que deba saber el siguiente departamento">
                        </div>
                        <button type="button" class="tw-btn tw-btn-primary mb-2" onclick="twGuardarAvance()">
                            <i class="bi bi-save"></i> Guardar avance
                        </button>
                        <div class="mb-2">
                            <label class="form-label">Al terminar, enviar el vehículo a</label>
                            <select id="tw_dep_siguiente" class="form-select">
                                <option value="">— Queda listo para entrega —</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <?php if ((int) $d['id'] === (int) $idDepartamento) continue; ?>
                                    <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="tw-btn tw-btn-success" onclick="twTerminar()">
                            <i class="bi bi-check2-circle"></i> Terminar y enviar
                        </button>
                    </div>

                    <!-- Repuestos y servicios usados -->
                    <div class="tab-pane fade" id="tw-pane-consumos">
                        <div class="p-2 rounded mb-3" style="background:#262b34">
                            <div class="row g-2">
                                <div class="col-5">
                                    <label class="form-label">Tipo</label>
                                    <select id="tw_l_tipo" class="form-select" onchange="twTipoChange()">
                                        <option value="repuesto">Repuesto</option>
                                        <option value="mano_obra">Mano de obra</option>
                                        <option value="insumo">Insumo</option>
                                        <option value="tercero">Terceros</option>
                                    </select>
                                </div>
                                <div class="col-7">
                                    <label class="form-label">Bodega</label>
                                    <select id="tw_l_bodega" class="form-select">
                                        <option value="">— Usar la de la orden —</option>
                                        <?php foreach (($bodegas ?? []) as $b): ?>
                                            <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 position-relative">
                                    <label class="form-label">Repuesto o trabajo</label>
                                    <input type="text" id="tw_l_descripcion" class="form-control"
                                           placeholder="Buscar en el catálogo o escribir libre..." autocomplete="off"
                                           oninput="twBuscarProductos(this.value)">
                                    <input type="hidden" id="tw_l_id_producto">
                                    <div id="tw_prod_dropdown" class="list-group position-absolute w-100 shadow d-none"
                                         style="z-index:1090;max-height:220px;overflow:auto;"></div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" step="0.01" min="0" id="tw_l_cantidad" class="form-control text-end" value="1">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Horas</label>
                                    <input type="number" step="0.25" min="0" id="tw_l_horas" class="form-control text-end" value="0" disabled>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Precio</label>
                                    <input type="number" step="0.01" min="0" id="tw_l_precio" class="form-control text-end" value="0">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tw_l_provisto">
                                        <label class="form-check-label small" for="tw_l_provisto">Lo trajo el cliente (no se factura)</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="button" class="tw-btn tw-btn-primary" onclick="twAgregarLinea()">
                                        <i class="bi bi-plus-lg"></i> Agregar a la orden
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="tw_lineas"></div>
                    </div>

                    <!-- Datos del vehículo y notas -->
                    <div class="tab-pane fade" id="tw-pane-info">
                        <div id="tw_info_vehiculo" class="mb-3"></div>
                        <div class="mb-2">
                            <label class="form-label">Dejar una nota en la bitácora</label>
                            <input type="text" id="tw_nota" class="form-control mb-2" placeholder="Ej. El cliente pidió revisar también el aire.">
                            <button type="button" class="tw-btn" onclick="twAgregarNota()">
                                <i class="bi bi-chat-left-text"></i> Agregar nota
                            </button>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Foto del trabajo</label>
                            <input type="file" id="tw_foto" accept="image/*" capture="environment" class="form-control mb-2">
                            <button type="button" class="tw-btn" onclick="twSubirFoto()">
                                <i class="bi bi-camera"></i> Subir foto
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.TW_RUTA = "<?= $rutaAjax ?>";
    window.TW_ID_DEPARTAMENTO = <?= (int) $idDepartamento ?>;
    window.TW_DEP_NOMBRE = "<?= htmlspecialchars($depActual['nombre'] ?? '', ENT_QUOTES) ?>";
    // El departamento de diagnóstico puede trabajar antes de que el cliente
    // apruebe: justamente existe para producir el presupuesto que se aprueba.
    window.TW_ES_DIAGNOSTICO = <?= \App\Helpers\Booleano::es($depActual['es_diagnostico'] ?? false) ? 'true' : 'false' ?>;
    window.TW_ORDENES_INICIALES = <?= json_encode($ordenes ?? []) ?>;
    window.EMPRESA_CONFIG = {
        decimales_precio: <?= (int) ($decimalesPrecio ?? 2) ?>
    };
</script>
<script src="<?= $base ?>/js/modulos/taller_estacion.js?v=<?= time() ?>"></script>
</body>
</html>
