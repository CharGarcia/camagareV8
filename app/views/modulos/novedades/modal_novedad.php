<?php
/** @var array $perm */
/** @var array $tipos */
/** @var array $motivos */
/** @var array $meses */
/** @var array $aplicaEn */

$anioActual = (int) date('Y');
$mesActual  = (int) date('n');
?>
<style>
    /* Modo del modal Nuevo: "Un empleado" (formulario de siempre) o "Todos los empleados" (cuadrícula). */
    #modalNovedad.nov-modo-todos .nov-solo-uno { display: none !important; }
    #modalNovedad:not(.nov-modo-todos) .nov-solo-todos { display: none !important; }
    /* Modo "Préstamo empresa": empleado + monto/cuotas + tabla de cuotas. */
    #modalNovedad.nov-modo-prestamo .nov-solo-uno:not(.nov-tambien-prestamo),
    #modalNovedad.nov-modo-prestamo .nov-no-prestamo { display: none !important; }
    #modalNovedad:not(.nov-modo-prestamo) .nov-solo-prestamo { display: none !important; }
    #nov_tabla_prestamo { font-size: .78rem; }
    #nov_tabla_prestamo thead th, #nov_tabla_prestamo tfoot td { position: sticky; background: #f8f9fa; z-index: 1; font-weight: 600; }
    #nov_tabla_prestamo thead th { top: 0; }
    #nov_tabla_prestamo tfoot td { bottom: 0; }
    #nov_tabla_prestamo tr.nov-cuota-cobrada td { color: #6c757d; text-decoration: line-through; }
    #nov_tabla_prestamo tr.nov-cuota-cobrada td.nov-cuota-check { text-decoration: none; }
    .nov-prestamo-tabla { max-height: 260px; overflow: auto; }
    /* El <form> va entre .modal-content y .modal-body: sin esto el modal-dialog-scrollable de
       Bootstrap no limita el alto del body, el contenido se recorta y la rueda no desplaza. */
    #formNovedad { display: flex; flex-direction: column; min-height: 0; max-height: 100%; }
    #formNovedad > .modal-body { overflow-y: auto; min-height: 0; }
    /* Contenedor de la cuadrícula. El listado de fondo activa el app-shell (body.cmg-has-table),
       cuyo CSS fuerza height:100% / max-height:none en todo "[class*=-scroll]": se anula aquí
       con un selector de id para que la cuadrícula tenga su propio scroll (vertical y horizontal). */
    body #modalNovedad .novmasivo-scroll {
        height: auto !important;
        max-height: 52vh !important;
        overflow: auto !important;
        overscroll-behavior: contain;
    }
    /* Pantalla completa: la cuadrícula usa todo el alto que dejan los campos comunes. */
    body #modalNovedad.nov-modo-todos .novmasivo-scroll { max-height: calc(100vh - 330px) !important; min-height: 240px; }
    #nov_tabla_masivo { font-size: .78rem; width: max-content; min-width: 100%; }
    #nov_tabla_masivo thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; white-space: nowrap; vertical-align: top; font-weight: 600; }
    #nov_tabla_masivo tfoot td { position: sticky; bottom: 0; z-index: 2; background: #f8f9fa; font-weight: 600; }
    /* Empleado fijo a la izquierda al desplazarse por las columnas. */
    #nov_tabla_masivo .nov-col-emp { position: sticky; left: 0; z-index: 1; background: #fff; min-width: 230px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #nov_tabla_masivo thead .nov-col-emp, #nov_tabla_masivo tfoot .nov-col-emp { z-index: 3; background: #f8f9fa; }
    #nov_tabla_masivo td.p-0 input, #nov_tabla_masivo td.p-0 select { padding: 0 4px; height: 20px; font-size: .78rem; border-radius: 0; }
    #nov_tabla_masivo input.nov-m-valor { width: 70px; text-align: right; }
    #nov_tabla_masivo select.nov-m-sel { width: 78px; }
    #nov_tabla_masivo select.nov-m-motivo { width: 190px; }
    #nov_tabla_masivo input.nov-m-obs { width: 220px; }
    #nov_tabla_masivo th select { height: 20px; padding: 0 4px; font-size: .72rem; font-weight: 400; }
    #nov_tabla_masivo td.nov-con-valor { background: #fff8e1; }
</style>
<div class="modal fade" id="modalNovedad" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" id="nov_dialog">
        <div class="modal-content shadow-lg border-0">
            <form id="formNovedad" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-clipboard-plus me-2 text-primary"></i>
                        <span id="tituloModalNov">Nueva Novedad</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body position-relative">
                    <!-- Loader mientras carga la información completa de la novedad vía AJAX -->
                    <div id="nov-modal-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                        <div class="spinner-border text-primary mb-2" role="status"></div>
                        <div class="small text-muted">Cargando información...</div>
                    </div>
                    <div id="novBloqueoAviso" class="alert alert-secondary d-flex align-items-center gap-2 py-2 px-3 mb-3 d-none">
                        <i class="bi bi-lock-fill"></i>
                        <span class="small" id="novBloqueoAvisoTexto">Esta novedad no se puede modificar: el rol de este período ya está pagado, o ya fue desembolsada por egreso (Anticipo/Préstamo Empresa). Anule el pago/egreso correspondiente, o registre una novedad nueva de ajuste, si necesita corregirla.</span>
                    </div>
                    <input type="hidden" name="id" id="nov_id" value="">
                    <!-- Solo al crear: registrar para un empleado o para todos los empleados activos -->
                    <div id="nov_modo_wrap" class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Registrar para">
                            <input type="radio" class="btn-check" name="nov_modo" id="nov_modo_uno" value="uno" autocomplete="off" checked>
                            <label class="btn btn-outline-primary" for="nov_modo_uno"><i class="bi bi-person me-1"></i>Un empleado</label>
                            <input type="radio" class="btn-check" name="nov_modo" id="nov_modo_todos" value="todos" autocomplete="off">
                            <label class="btn btn-outline-primary" for="nov_modo_todos"><i class="bi bi-people me-1"></i>Todos los empleados</label>
                            <input type="radio" class="btn-check" name="nov_modo" id="nov_modo_prestamo" value="prestamo" autocomplete="off">
                            <label class="btn btn-outline-primary" for="nov_modo_prestamo"><i class="bi bi-cash-coin me-1"></i>Préstamo empresa</label>
                        </div>
                        <span class="small text-muted nov-solo-todos">Escriba el valor solo en las novedades que correspondan a cada empleado: las celdas vacías o en 0 no crean nada. La observación de cada empleado vale para todas sus novedades; vacía, se guarda «Tipo - Mes Año».</span>
                        <span class="small text-muted nov-solo-prestamo">Registra el préstamo y crea una cuota de Préstamo Empresa por mes, que se descuenta en el rol.</span>
                    </div>
                    <!-- Al editar una cuota de un préstamo registrado con tabla de cuotas -->
                    <div id="nov_prestamo_info" class="alert alert-info d-flex align-items-center gap-2 py-2 px-3 mb-3 d-none">
                        <i class="bi bi-cash-coin"></i>
                        <span class="small" id="nov_prestamo_info_texto"></span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8 position-relative nov-solo-uno nov-tambien-prestamo">
                            <label class="form-label mb-1 small fw-bold text-muted">Empleado *</label>
                            <input type="hidden" name="id_empleado" id="nov_id_empleado" value="">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control form-control-sm shadow-none" id="nov_empleado_buscar" placeholder="Buscar por nombre o identificación..." autocomplete="off">
                            </div>
                            <div id="nov_empleado_resultados" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1080; max-height:220px; overflow-y:auto;"></div>
                        </div>
                        <div class="col-md-4 nov-solo-uno" id="nov_container_estado">
                            <label class="form-label mb-1 small fw-bold text-muted">Estado</label>
                            <select class="form-select form-select-sm shadow-none" name="estado" id="nov_estado">
                                <option value="activo">Activo</option>
                                <option value="anulado">Anulado</option>
                            </select>
                        </div>

                        <div class="col-md-6 nov-solo-uno">
                            <label class="form-label mb-1 small fw-bold text-muted">Tipo de Novedad * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('novedades', 'nov_tipo_codigo', 'tipo_codigo') ?></label>
                            <select class="form-select form-select-sm shadow-none" name="tipo_codigo" id="nov_tipo_codigo" required onchange="window.novToggleCampos()">
                                <option value="">-- Seleccionar tipo --</option>
                                <?php foreach ($tipos as $t): ?>
                                    <option value="<?= htmlspecialchars($t['codigo']) ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 nov-solo-uno" id="nov_container_valor">
                            <label class="form-label mb-1 small fw-bold text-muted" id="nov_label_valor">Monto ($)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm shadow-none fw-bold" name="valor" id="nov_valor" value="0.00">
                        </div>

                        <!-- Solo Otros Ingresos y horas: si el ingreso aporta al IESS (como los rubros fijos del empleado) -->
                        <div class="col-md-3 d-none nov-solo-uno" id="nov_container_iess">
                            <label class="form-label mb-1 small fw-bold text-muted" for="nov_aporta_iess">Aporta IESS</label>
                            <select class="form-select form-select-sm shadow-none" name="aporta_iess" id="nov_aporta_iess">
                                <option value="si">Sí</option>
                                <option value="no">No</option>
                            </select>
                            <div class="form-text small mt-1 d-none" id="nov_iess_aviso">El empleado no aporta al IESS.</div>
                        </div>

                        <!-- Solo Anticipo, Quincena y Préstamo Empresa: ¿ya se entregó fuera del sistema? -->
                        <div class="col-md-3 d-none nov-solo-uno" id="nov_container_pago">
                            <label class="form-label mb-1 small fw-bold text-muted" for="nov_ya_pagado">Pago</label>
                            <select class="form-select form-select-sm shadow-none" name="ya_pagado" id="nov_ya_pagado"
                                    title="Por pagar: se paga en Egresos → Nómina y el rol descuenta lo pagado. Ya pagado: se entregó fuera del sistema y el rol lo descuenta directo.">
                                <option value="por">Por pagar</option>
                                <option value="ya">Ya pagado</option>
                            </select>
                        </div>

                        <div class="col-md-6 d-none nov-solo-uno" id="nov_container_motivo">
                            <label class="form-label mb-1 small fw-bold text-muted">Motivo de Salida *</label>
                            <select class="form-select form-select-sm shadow-none" name="motivo_codigo" id="nov_motivo_codigo">
                                <option value="">-- Seleccionar motivo --</option>
                                <?php foreach ($motivos as $m): ?>
                                    <option value="<?= htmlspecialchars($m['codigo']) ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Fecha *</label>
                            <input type="date" class="form-control form-control-sm shadow-none" name="fecha" id="nov_fecha" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted"><span class="nov-no-prestamo">Mes del Rol *</span><span class="nov-solo-prestamo">Mes 1.ª cuota *</span></label>
                            <select class="form-select form-select-sm shadow-none" name="periodo_mes" id="nov_periodo_mes">
                                <?php foreach ($meses as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $num === $mesActual ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted"><span class="nov-no-prestamo">Año del Rol *</span><span class="nov-solo-prestamo">Año 1.ª cuota *</span></label>
                            <input type="number" class="form-control form-control-sm shadow-none" name="periodo_anio" id="nov_periodo_anio" value="<?= $anioActual ?>" min="2000" max="2100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Afecta a * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('novedades', 'nov_aplica_en', 'aplica_en') ?></label>
                            <select class="form-select form-select-sm shadow-none" name="aplica_en" id="nov_aplica_en">
                                <?php foreach ($aplicaEn as $val => $lbl): ?>
                                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small mt-1 d-none" id="nov_aplica_aviso">Este tipo solo afecta al rol mensual.</div>
                        </div>

                        <!-- Préstamo empresa: monto, cuotas, desembolso y tabla de cuotas -->
                        <div class="col-12 nov-solo-prestamo">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted" for="nov_pre_monto">Monto a prestar ($) *</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm shadow-none fw-bold" id="nov_pre_monto" placeholder="0.00">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted" for="nov_pre_cuotas">N° de cuotas *</label>
                                    <input type="number" step="1" min="1" max="120" class="form-control form-control-sm shadow-none" id="nov_pre_cuotas" placeholder="1">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label mb-1 small fw-bold text-muted" for="nov_pre_desembolso">Desembolso *</label>
                                    <select class="form-select form-select-sm shadow-none" id="nov_pre_desembolso">
                                        <option value="por_desembolsar">Por desembolsar (egreso en Egresos → Nómina)</option>
                                        <option value="entregado">Ya fue entregado</option>
                                    </select>
                                    <div class="form-text small mt-1" id="nov_pre_desembolso_ayuda">Las cuotas se descuentan en el rol cuando el préstamo esté desembolsado por egreso.</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-3 mb-1">
                                <span class="small fw-bold text-muted">Cuotas</span>
                                <span class="small text-muted">Marque «Ya cobrada» solo en las cuotas que ya se descontaron antes de registrar el préstamo en el sistema: no se vuelven a descontar.</span>
                            </div>
                            <div class="nov-prestamo-tabla border rounded">
                                <table class="table table-sm table-bordered table-hover mb-0 align-middle" id="nov_tabla_prestamo">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:60px;">#</th>
                                            <th>Mes a cobrar</th>
                                            <th class="text-end" style="width:130px;">Valor</th>
                                            <th class="text-center" style="width:110px;">¿Ya cobrada?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="4" class="text-center text-muted py-3">Escriba el monto y el número de cuotas.</td></tr>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="2">Total del préstamo</td>
                                            <td class="text-end" id="nov_pre_total">0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Por cobrar en el rol</td>
                                            <td class="text-end" id="nov_pre_por_cobrar">0.00</td>
                                            <td class="text-center small" id="nov_pre_por_cobrar_n"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- En "Todos los empleados" la observación va por empleado (columna de la cuadrícula). -->
                        <div class="col-12 nov-solo-uno nov-tambien-prestamo">
                            <label class="form-label mb-1 small fw-bold text-muted">Observación</label>
                            <textarea class="form-control form-control-sm shadow-none" name="observacion" id="nov_observacion" rows="2"></textarea>
                        </div>

                        <!-- Cuadrícula "Todos los empleados": una fila por empleado activo, una columna por tipo -->
                        <div class="col-12 nov-solo-todos">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <div class="input-group input-group-sm" style="width:300px;">
                                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control form-control-sm shadow-none" id="nov_masivo_buscar" placeholder="Filtrar empleados..." autocomplete="off">
                                </div>
                                <span class="small text-muted" id="nov_masivo_info"></span>
                                <span class="ms-auto small fw-bold text-primary" id="nov_masivo_contador">0 novedades por registrar</span>
                            </div>
                            <div class="novmasivo-scroll border rounded">
                                <table class="table table-sm table-bordered table-hover mb-0 align-middle" id="nov_tabla_masivo">
                                    <thead class="table-light"></thead>
                                    <tbody></tbody>
                                    <tfoot class="table-light"></tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if (!empty($perm['eliminar'])): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarNov" onclick="window.eliminarNovedadModal()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarNov"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
