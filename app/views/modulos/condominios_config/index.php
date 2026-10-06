<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var array $vistaConfig @var bool $instalado
 *  @var ?array $config @var array $pendientes @var array $puntosEmision @var array $multas @var array $metodos @var string $base */
$urlBase  = rtrim($base, '/') . '/' . $rutaModulo;
$pestanasCfg = [
    'pane-cfg-general'    => 'Condominio',
    'pane-cfg-alicuota'   => 'Alícuota y fondo',
    'pane-cfg-mora'       => 'Mora y multas',
    'pane-cfg-reajuste'   => 'Reajuste de cuotas',
    'pane-cfg-descuentos' => 'Descuentos',
];
$puedeGuardar = !empty($perm['actualizar']);
$prodChip = function (string $id, string $label, string $campo, string $ayuda) use ($rutaModulo, $base): string {
    return '<label for="' . $id . '_txt" class="d-flex align-items-center">' . $label . ' ' . \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, $id, $campo) . '</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-box-seam"></i></span>
                <input type="text" class="form-control" id="' . $id . '_txt" placeholder="Buscar servicio en Productos…" autocomplete="off">
                <input type="hidden" id="' . $id . '" name="' . $campo . '">
                <a class="btn btn-outline-secondary" href="' . rtrim($base, '/') . '/modulos/productos" target="_blank" title="Crear el servicio en Productos"><i class="bi bi-box-arrow-up-right"></i></a>
            </div>
            <div class="list-group position-absolute w-100 shadow condcfg-dropdown" id="' . $id . '_dd" style="display:none"></div>
            <div class="form-text">' . $ayuda . '</div>';
};
?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>
<style>
    .condcfg label { font-size: .83rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }
    .condcfg .nav-tabs .nav-link { font-size: .875rem; }
    .condcfg .form-text { font-size: .7rem; }
    .condcfg-dropdown { z-index: 1090; max-height: 240px; overflow-y: auto; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-gear text-primary me-2"></i><?= htmlspecialchars($titulo) ?>
        <?php if ($config): ?><span class="text-muted fw-normal fs-6 ms-2"><?= htmlspecialchars((string) ($config['nombre_condominio'] ?: '')) ?></span><?php endif; ?>
    </h5>
    <a href="<?= rtrim($base, '/') ?>/modulos/condominios" class="btn btn-outline-secondary btn-sm"><i class="bi bi-door-open me-1"></i>Ir a Inmuebles</a>
</div>

<?php if (!$instalado): ?>
    <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>
        El módulo Condominios aún no está instalado en la base de datos: falta aplicar <code>database/migrations/20261005_condominios.sql</code>.
    </div>
<?php elseif (!$config): ?>
    <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>
        Este condominio aún no está configurado. Indique el administrador y el producto para la alícuota (créelo antes en <b>Productos</b> como servicio) y pulse <b>Guardar</b>: con eso el módulo queda activo para esta empresa.
    </div>
<?php elseif ($pendientes): ?>
    <div class="alert alert-warning py-2 small" id="cfg-aviso-pendientes"><i class="bi bi-exclamation-triangle me-1"></i>
        <b>Falta para poder emitir:</b> <?= htmlspecialchars(implode(' ', $pendientes)) ?>
    </div>
<?php endif; ?>

<form id="formCondConfig" class="condcfg" novalidate>
<div class="card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-light py-2 px-3 d-flex align-items-center">
        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap" role="tablist">
            <li class="nav-item"><a class="nav-link active py-2 small" id="cfg-tab-general-btn" data-bs-toggle="tab" href="#pane-cfg-general" role="tab"><i class="bi bi-buildings me-1"></i>Condominio</a></li>
            <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#pane-cfg-alicuota" role="tab"><i class="bi bi-calculator me-1"></i>Alícuota y fondo</a></li>
            <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#pane-cfg-mora" role="tab"><i class="bi bi-hourglass-split me-1"></i>Mora y multas</a></li>
            <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#pane-cfg-reajuste" role="tab"><i class="bi bi-arrow-repeat me-1"></i>Reajuste de cuotas</a></li>
            <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#pane-cfg-descuentos" role="tab"><i class="bi bi-percent me-1"></i>Descuentos</a></li>
        </ul>
        <div class="flex-shrink-0">
            <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasCfg, $vistaConfig ?? [], basename($rutaModulo)) ?>
        </div>
    </div>

    <div class="card-body tab-content px-3 py-3">
        <!-- ══ Condominio ══ -->
        <div class="tab-pane fade show active" id="pane-cfg-general" role="tabpanel">
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="cfg_nombre_condominio">Nombre del condominio</label>
                    <input type="text" class="form-control form-control-sm" id="cfg_nombre_condominio" name="nombre_condominio" maxlength="200" value="<?= htmlspecialchars((string) (($config['nombre_condominio'] ?? '') ?: ($defaults['nombre_condominio'] ?? ''))) ?>" placeholder="Edificio Torres del Parque">
                    <div class="form-text">Por defecto, el nombre de la empresa.</div>
                </div>
                <div class="col-md-6">
                    <label for="cfg_direccion">Dirección</label>
                    <input type="text" class="form-control form-control-sm" id="cfg_direccion" name="direccion" maxlength="300" value="<?= htmlspecialchars((string) (($config['direccion'] ?? '') ?: ($defaults['direccion'] ?? ''))) ?>">
                    <div class="form-text">Por defecto, la dirección del establecimiento.</div>
                </div>
            </div>
            <div class="row g-2 mt-0">
                <div class="col-md-5">
                    <label for="cfg_administrador_nombre">Administrador/a *</label>
                    <input type="text" class="form-control form-control-sm" id="cfg_administrador_nombre" name="administrador_nombre" maxlength="150" value="<?= htmlspecialchars((string) (($config['administrador_nombre'] ?? '') ?: ($defaults['administrador_nombre'] ?? ''))) ?>" required>
                    <div class="form-text">Firma la liquidación para cobro judicial (art. 13 LPH). Por defecto, el representante legal de la empresa.</div>
                </div>
                <div class="col-md-3">
                    <label for="cfg_administrador_cedula">Cédula</label>
                    <input type="text" class="form-control form-control-sm" id="cfg_administrador_cedula" name="administrador_cedula" maxlength="20" value="<?= htmlspecialchars((string) (($config['administrador_cedula'] ?? '') ?: ($defaults['administrador_cedula'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label for="cfg_administrador_cargo">Cargo</label>
                    <input type="text" class="form-control form-control-sm" id="cfg_administrador_cargo" name="administrador_cargo" maxlength="100" value="Administrador/a">
                </div>
            </div>
            <div class="row g-2 mt-0">
                <div class="col-md-5">
                    <label for="cfg_presidente_nombre">Presidente/a de la asamblea <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" class="form-control form-control-sm" id="cfg_presidente_nombre" name="presidente_nombre" maxlength="150">
                </div>
                <div class="col-md-3">
                    <label for="cfg_presidente_cedula">Cédula</label>
                    <input type="text" class="form-control form-control-sm" id="cfg_presidente_cedula" name="presidente_cedula" maxlength="20">
                </div>
                <div class="col-md-4">
                    <label for="cfg_liquidacion_min_vencidas">Liquidación judicial desde</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control text-end" id="cfg_liquidacion_min_vencidas" name="liquidacion_min_vencidas" min="1" max="24" value="1">
                        <span class="input-group-text">expensas vencidas</span>
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-0">
                <div class="col-12">
                    <label for="cfg_observaciones">Observaciones</label>
                    <textarea class="form-control form-control-sm" id="cfg_observaciones" name="observaciones" rows="2"></textarea>
                </div>
            </div>
        </div>

        <!-- ══ Alícuota y fondo ══ -->
        <div class="tab-pane fade" id="pane-cfg-alicuota" role="tabpanel">
            <div class="row g-2">
                <div class="col-md-6 position-relative">
                    <?= $prodChip('cfg_prod_ordinaria', 'Producto para la alícuota ordinaria *', 'id_producto_ordinaria', 'Servicio creado en Productos (p. ej. «Alícuotas»). Su nombre, IVA y cuenta contable se usan en el recibo.') ?>
                </div>
                <div class="col-md-3">
                    <label for="cfg_metodo_alicuota">Método de alícuota *</label>
                    <select class="form-select form-select-sm" id="cfg_metodo_alicuota" name="metodo_alicuota">
                        <?php foreach ($metodos as $k => $v): ?><option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                    </select>
                    <div class="form-text">Cada inmueble puede tener el suyo.</div>
                </div>
                <div class="col-md-3">
                    <label for="cfg_reparto_manuales">Manuales con presupuesto</label>
                    <select class="form-select form-select-sm" id="cfg_reparto_manuales" name="reparto_manuales">
                        <option value="repartir_resto">Repartir el resto entre las demás</option>
                        <option value="aparte">Los manuales van aparte</option>
                    </select>
                    <div class="form-text">Solo cuando la base del % es un presupuesto aprobado.</div>
                </div>
            </div>
            <hr class="my-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <label for="cfg_fondo_reserva_tipo">Fondo de reserva</label>
                    <select class="form-select form-select-sm" id="cfg_fondo_reserva_tipo" name="fondo_reserva_tipo" onchange="CONDCFG.onFondo()">
                        <option value="no">No se cobra</option>
                        <option value="porcentaje">% sobre la alícuota ordinaria</option>
                        <option value="fijo">Monto fijo por inmueble</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="cfg_fondo_reserva_valor" id="cfg_fondo_reserva_valor_lbl">Valor</label>
                    <input type="number" class="form-control form-control-sm text-end" id="cfg_fondo_reserva_valor" name="fondo_reserva_valor" step="0.01" min="0" value="0">
                </div>
                <div class="col-md-6 position-relative" id="cfg-wrap-prod-fondo">
                    <?= $prodChip('cfg_prod_fondo', 'Producto para el fondo de reserva', 'id_producto_fondo', 'Línea separada en el recibo; su cuenta contable la define el producto (normalmente un pasivo/patrimonio del condominio).') ?>
                </div>
            </div>

            <!-- Valores que rigen: tarifa por m² (método m²) y monto a repartir (método %), con fecha desde la que rigen -->
            <hr class="my-3">
            <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                <h6 class="fw-bold small mb-0"><i class="bi bi-calendar-check me-1 text-primary"></i>Valores que rigen</h6>
                <span class="small text-muted">Tarifa por m² (método por m²) y monto mensual a repartir (método por %). Se agregan con fecha desde la que rigen; nunca se editan los anteriores.</span>
                <?php if ($config && $puedeGuardar): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm ms-auto" onclick="CONDCFG.nuevoValor()"><i class="bi bi-plus-lg me-1"></i>Nuevo valor desde…</button>
                <?php endif; ?>
            </div>
            <div class="border rounded-3 bg-white">
                <table class="table table-sm table-hover mb-0 small">
                    <thead class="table-light"><tr><th class="ps-2">Rige desde</th><th class="text-end">Tarifa m²</th><th class="text-end">Monto a repartir</th><th>Base</th><th>Acta</th><th>Observación</th><th>Registró</th><th class="pe-2"></th></tr></thead>
                    <tbody id="valores-body"><tr><td colspan="8" class="text-center text-muted py-3">—</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ══ Mora y multas ══ -->
        <div class="tab-pane fade" id="pane-cfg-mora" role="tabpanel">
            <div class="row g-2">
                <div class="col-md-3 pt-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="cfg_cobra_intereses" name="cobra_intereses" value="1" onchange="CONDCFG.onIntereses()">
                        <label class="form-check-label" for="cfg_cobra_intereses">Cobra intereses de mora</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="cfg_interes_tipo">Tasa</label>
                    <select class="form-select form-select-sm" id="cfg_interes_tipo" name="interes_tipo" onchange="CONDCFG.onIntereses()">
                        <option value="legal">Tasa legal vigente</option>
                        <option value="fijo">% mensual fijo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="cfg_interes_tasa_mensual">% mensual</label>
                    <input type="number" class="form-control form-control-sm text-end" id="cfg_interes_tasa_mensual" name="interes_tasa_mensual" step="0.0001" min="0" max="20" value="0">
                </div>
                <div class="col-md-2">
                    <label for="cfg_interes_destino">Dónde se cobra</label>
                    <select class="form-select form-select-sm" id="cfg_interes_destino" name="interes_destino">
                        <option value="siguiente_recibo">En el siguiente recibo</option>
                        <option value="recibo_aparte">En un recibo aparte</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="cfg_dias_gracia">Días de gracia</label>
                    <input type="number" class="form-control form-control-sm text-end" id="cfg_dias_gracia" name="dias_gracia" min="0" max="60" value="0">
                    <div class="form-text">Tras el vencimiento del recibo (plazo del cliente). Si paga dentro no hay interés; si no, corre desde el vencimiento.</div>
                </div>
            </div>
            <div class="row g-2 mt-0">
                <div class="col-md-6 position-relative" id="cfg-wrap-prod-interes">
                    <?= $prodChip('cfg_prod_interes', 'Producto para los intereses de mora', 'id_producto_interes', 'Interés simple sobre el capital vencido, proporcional a los días; nunca sobre intereses.') ?>
                </div>
                <div class="col-md-6 pt-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="cfg_cobra_multas" name="cobra_multas" value="1">
                        <label class="form-check-label" for="cfg_cobra_multas">Cobra multas del reglamento</label>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <div class="row g-2">
                <div class="col-md-4 pt-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="cfg_restriccion_auto" name="restriccion_auto" value="1">
                        <label class="form-check-label" for="cfg_restriccion_auto">Restringir áreas comunes automáticamente</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="cfg_restriccion_meses">Al superar (meses de mora)</label>
                    <input type="number" class="form-control form-control-sm text-end" id="cfg_restriccion_meses" name="restriccion_meses" min="1" max="24" value="3">
                </div>
                <div class="col-md-5 small text-muted pt-4">La marca se quita sola al quedar sin saldo vencido. También se puede marcar y quitar a mano en cada inmueble.</div>
            </div>

            <!-- Catálogo de multas (se guarda aparte, por fila) -->
            <hr class="my-3">
            <h6 class="fw-bold small mb-2"><i class="bi bi-exclamation-octagon me-1 text-primary"></i>Catálogo de multas del reglamento</h6>
            <?php if ($config && (!empty($perm['crear']) || !empty($perm['actualizar']))): ?>
            <div class="border rounded-3 p-2 bg-light mb-2" id="multa-form-wrap">
                <input type="hidden" id="multa_id" value="">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label for="multa_nombre">Nombre *</label>
                        <input type="text" class="form-control form-control-sm" id="multa_nombre" maxlength="150" placeholder="Ruido fuera de horario">
                    </div>
                    <div class="col-md-2">
                        <label for="multa_valor">Valor *</label>
                        <input type="number" class="form-control form-control-sm text-end" id="multa_valor" step="0.01" min="0" value="0">
                    </div>
                    <div class="col-md-4 position-relative">
                        <label for="multa_prod_txt">Producto (servicio) *</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-box-seam"></i></span>
                            <input type="text" class="form-control" id="multa_prod_txt" placeholder="Buscar en Productos…" autocomplete="off">
                            <input type="hidden" id="multa_id_producto">
                        </div>
                        <div class="list-group position-absolute w-100 shadow condcfg-dropdown" id="multa_prod_dd" style="display:none"></div>
                    </div>
                    <div class="col-md-2">
                        <label for="multa_estado">Estado</label>
                        <select class="form-select form-select-sm" id="multa_estado"><option value="activo">Activa</option><option value="inactivo">Inactiva</option></select>
                    </div>
                    <div class="col-md-10">
                        <label for="multa_descripcion">Descripción</label>
                        <input type="text" class="form-control form-control-sm" id="multa_descripcion" maxlength="300" placeholder="Artículo del reglamento, condiciones…">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-1">
                        <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" onclick="CONDCFG.multaGuardar()"><i class="bi bi-check2 me-1"></i>Guardar multa</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="CONDCFG.multaLimpiar()" title="Limpiar"><i class="bi bi-eraser"></i></button>
                    </div>
                </div>
            </div>
            <?php elseif (!$config): ?>
                <div class="small text-muted mb-2">Guarde primero la configuración para registrar multas.</div>
            <?php endif; ?>
            <div class="border rounded-3 bg-white">
                <table class="table table-sm table-hover mb-0 small">
                    <thead class="table-light"><tr><th class="ps-2">Multa</th><th>Descripción</th><th class="text-end">Valor</th><th>Producto</th><th>Estado</th><th class="pe-2"></th></tr></thead>
                    <tbody id="multas-body"><tr><td colspan="6" class="text-center text-muted py-3">—</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ══ Reajuste de cuotas (masivo, con programación por fecha) ══ -->
        <div class="tab-pane fade" id="pane-cfg-reajuste" role="tabpanel">
            <p class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Cambia el valor de un concepto en muchas suscripciones a la vez (p. ej. la alícuota de 500 condóminos para el próximo año). Nada se graba hasta pulsar <b>Aplicar</b>; con una fecha futura queda <b>programado</b> y se aplica solo ese día.</p>
            <div class="border rounded-3 p-2 bg-light mb-2">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label for="reaj_id_producto">Concepto *</label>
                        <select class="form-select form-select-sm" id="reaj_id_producto"><option value="">— Cargando… —</option></select>
                        <div class="form-text">Productos presentes en las suscripciones.</div>
                    </div>
                    <div class="col-md-2">
                        <label for="reaj_forma">Forma *</label>
                        <select class="form-select form-select-sm" id="reaj_forma" onchange="CONDCFG.reajusteForma()">
                            <option value="fijo">Monto fijo para todas</option>
                            <option value="porcentaje">Aumento % sobre el actual</option>
                            <option value="inmueble">Según el inmueble (valor que rige)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="reaj_parametro" id="reaj_parametro_lbl">Monto *</label>
                        <input type="number" class="form-control form-control-sm text-end" id="reaj_parametro" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-2">
                        <label for="reaj_fecha_aplicar">Aplicar desde *</label>
                        <input type="date" class="form-control form-control-sm" id="reaj_fecha_aplicar" value="<?= date('Y-m-d') ?>">
                        <div class="form-text">Hoy = en el acto; futura = programado.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="reaj_descripcion">Descripción / acta *</label>
                        <input type="text" class="form-control form-control-sm" id="reaj_descripcion" maxlength="200" placeholder="Reajuste 2027, acta N.º 5">
                    </div>
                    <div class="col-md-9 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="reaj_incluir_sin_inmueble">
                            <label class="form-check-label" for="reaj_incluir_sin_inmueble">Incluir también suscripciones sin inmueble enlazado</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end justify-content-end">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="CONDCFG.reajustePreview()"><i class="bi bi-eye me-1"></i>Vista previa</button>
                    </div>
                </div>
            </div>
            <div id="reaj-preview" class="d-none">
                <div class="row g-2 mb-2" id="reaj-kpis"></div>
                <div class="border rounded-3 bg-white" style="max-height: 40vh; overflow: auto;">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light"><tr><th class="ps-2" style="width:28px"><input type="checkbox" class="form-check-input" id="reaj-todas" checked onchange="CONDCFG.reajusteTodas(this.checked)"></th><th>Cliente</th><th>Inmueble</th><th class="text-end">Actual</th><th class="text-end">Nuevo</th><th class="pe-2">Nota</th></tr></thead>
                        <tbody id="reaj-body"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-2">
                    <?php if ($puedeGuardar): ?>
                        <button type="button" class="btn btn-primary btn-sm px-3" id="reaj-btn-aplicar" onclick="CONDCFG.reajusteAplicar()"><i class="bi bi-check2-circle me-1"></i>Aplicar</button>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="my-3">
            <h6 class="fw-bold small mb-2"><i class="bi bi-clock-history me-1 text-primary"></i>Reajustes realizados y programados</h6>
            <div class="border rounded-3 bg-white">
                <table class="table table-sm table-hover mb-0 small">
                    <thead class="table-light"><tr><th class="ps-2">Aplicar desde</th><th>Descripción</th><th>Concepto</th><th>Forma</th><th class="text-end">Suscr.</th><th class="text-end">Σ actual</th><th class="text-end">Σ nuevo</th><th>Estado</th><th>Registró</th><th class="pe-2"></th></tr></thead>
                    <tbody id="reaj-hist-body"><tr><td colspan="10" class="text-center text-muted py-3">—</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ══ Descuentos ══ -->
        <div class="tab-pane fade" id="pane-cfg-descuentos" role="tabpanel">
            <div class="row g-2">
                <div class="col-md-4 pt-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="cfg_pronto_pago_activo" name="pronto_pago_activo" value="1">
                        <label class="form-check-label" for="cfg_pronto_pago_activo">Descuento por pronto pago</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="cfg_pronto_pago_pct">% descuento</label>
                    <input type="number" class="form-control form-control-sm text-end" id="cfg_pronto_pago_pct" name="pronto_pago_pct" step="0.01" min="0" max="100" value="0">
                </div>
                <div class="col-md-2">
                    <label for="cfg_pronto_pago_dia">Hasta el día</label>
                    <input type="number" class="form-control form-control-sm text-end" id="cfg_pronto_pago_dia" name="pronto_pago_dia" min="1" max="28" value="5">
                </div>
                <div class="col-md-4 small text-muted pt-4">Se propone al cobrar en Ingresos si la fecha de pago está dentro del plazo. No aplica a intereses ni multas.</div>
            </div>
            <hr class="my-3">
            <div class="row g-2">
                <div class="col-md-4 pt-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="cfg_anticipado_activo" name="anticipado_activo" value="1">
                        <label class="form-check-label" for="cfg_anticipado_activo">Descuento por pagar varios meses</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="cfg_anticipado_pct">% descuento</label>
                    <input type="number" class="form-control form-control-sm text-end" id="cfg_anticipado_pct" name="anticipado_pct" step="0.01" min="0" max="100" value="0">
                </div>
                <div class="col-md-2">
                    <label for="cfg_anticipado_meses_min">Desde (meses)</label>
                    <input type="number" class="form-control form-control-sm text-end" id="cfg_anticipado_meses_min" name="anticipado_meses_min" min="2" max="60" value="12">
                </div>
                <div class="col-md-4 small text-muted pt-4">Un solo recibo con una línea por mes; esos meses quedan facturados y el ingreso se devenga mes a mes.</div>
            </div>
        </div>
    </div>

    <div class="card-footer bg-light border-top p-2 d-flex justify-content-between align-items-center">
        <span class="small text-muted" id="cfg-registro">
            <?php if ($config && !empty($config['updated_at'])): ?><i class="bi bi-clock-history me-1"></i>Última modificación <?= date('d-m-Y H:i:s', strtotime($config['updated_at'])) ?><?php endif; ?>
        </span>
        <?php if ($puedeGuardar): ?>
            <button type="submit" class="btn btn-primary btn-sm px-4" id="cfg-btn-guardar" <?= $instalado ? '' : 'disabled' ?>><i class="bi bi-check2-circle me-1"></i>Guardar</button>
        <?php endif; ?>
    </div>
</div>
</form>

<!-- ══ Modal: nuevo valor que rige (con vista previa de la cuota de cada inmueble) ══ -->
<div class="modal fade condcfg" id="modalCondValor" tabindex="-1" data-bs-backdrop="static" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-calendar-check text-primary me-2"></i>Nuevo valor que rige</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label for="val_vigente_desde">Rige desde (mes) *</label>
                        <input type="month" class="form-control form-control-sm" id="val_vigente_desde">
                    </div>
                    <div class="col-md-2">
                        <label for="val_tarifa_m2">Tarifa por m²</label>
                        <input type="number" class="form-control form-control-sm text-end" id="val_tarifa_m2" step="0.0001" min="0" placeholder="0.0000">
                        <div class="form-text">Para inmuebles por m².</div>
                    </div>
                    <div class="col-md-2">
                        <label for="val_monto_a_repartir">Monto a repartir</label>
                        <input type="number" class="form-control form-control-sm text-end" id="val_monto_a_repartir" step="0.01" min="0" placeholder="0.00">
                        <div class="form-text">Mensual, para inmuebles por %.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="val_id_presupuesto">…o tomarlo de un presupuesto aprobado</label>
                        <select class="form-select form-select-sm" id="val_id_presupuesto" onchange="CONDCFG.valorPresupuesto()">
                            <option value="">— Monto manual —</option>
                        </select>
                        <div class="form-text">Costos y gastos presupuestados del mes desde el que rige (módulo Presupuestos).</div>
                    </div>
                    <div class="col-md-2">
                        <label for="val_acta">Acta</label>
                        <input type="text" class="form-control form-control-sm" id="val_acta" maxlength="120" placeholder="Asamblea N.º…">
                    </div>
                    <div class="col-md-10">
                        <label for="val_observacion">Observación</label>
                        <input type="text" class="form-control form-control-sm" id="val_observacion" maxlength="300">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="CONDCFG.valorPreview()"><i class="bi bi-eye me-1"></i>Vista previa</button>
                    </div>
                </div>
                <div id="val-preview" class="d-none mt-3">
                    <div class="row g-2 mb-2" id="val-kpis"></div>
                    <div class="border rounded-3 bg-white" style="max-height: 45vh; overflow: auto;">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light"><tr><th class="ps-2">Inmueble</th><th>Propietario</th><th>Método</th><th class="text-end">m²</th><th class="text-end">%</th><th class="text-end">Cuota</th><th class="text-end">Fondo</th><th class="text-end pe-2">Total</th></tr></thead>
                            <tbody id="val-body"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="val-nota"></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-3 d-none" id="val-btn-guardar" onclick="CONDCFG.valorGuardar()"><i class="bi bi-check2-circle me-1"></i>Guardar valor</button>
            </div>
        </div>
    </div>
</div>

<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<script>
    window.CONDCFG_CFG = {
        url: <?= json_encode($urlBase) ?>,
        perm: <?= json_encode(['crear' => !empty($perm['crear']), 'actualizar' => !empty($perm['actualizar']), 'eliminar' => !empty($perm['eliminar'])]) ?>,
        instalado: <?= $instalado ? 'true' : 'false' ?>,
        config: <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>,
        multas: <?= json_encode($multas, JSON_UNESCAPED_UNICODE) ?>,
    };
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/condominios_config.js?v=<?= asset_ver('/js/modulos/condominios_config.js') ?>"></script>
