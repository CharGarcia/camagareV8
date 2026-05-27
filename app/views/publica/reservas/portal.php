<?php
$color      = $portalConfig['color_primario'] ?? '#0d6efd';
$empresa    = htmlspecialchars($portalConfig['nombre_empresa']     ?? '', ENT_QUOTES);
$titulo     = htmlspecialchars($portalConfig['titulo']             ?? 'Reserva tu cita', ENT_QUOTES);
$bienvenida = htmlspecialchars($portalConfig['mensaje_bienvenida'] ?? '', ENT_QUOTES);
$baseUrl    = rtrim(BASE_URL, '/');
$urlBase    = $baseUrl . '/reservas/' . $slug;

// Color derivados (oscuro para hover)
$colorDark = $color; // se manejará con opacity en CSS
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?> — <?= $empresa ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --portal-color: <?= $color ?>; }
        body { background: #f1f5f9; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }

        .portal-header { background: var(--portal-color); color: #fff; padding: 2rem 1.5rem 1.2rem; text-align: center; }
        .portal-header h4 { font-weight: 700; margin-bottom: .25rem; }
        .portal-header p  { opacity: .85; margin-bottom: 0; font-size: .9rem; }

        /* Pasos */
        .pasos { display: flex; justify-content: center; gap: .5rem; padding: 1.2rem 1rem; background: #fff; border-bottom: 1px solid #e5e7eb; }
        .paso { display: flex; align-items: center; gap: .4rem; font-size: .78rem; color: #9ca3af; font-weight: 500; }
        .paso .num { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; flex-shrink: 0; }
        .paso.activo { color: var(--portal-color); }
        .paso.activo .num { border-color: var(--portal-color); background: var(--portal-color); color: #fff; }
        .paso.completado { color: #6b7280; }
        .paso.completado .num { border-color: #6b7280; background: #6b7280; color: #fff; }
        .separador { flex: 0 0 20px; height: 2px; background: #e5e7eb; align-self: center; margin: 0 -.2rem; }
        .separador.completado { background: #6b7280; }

        /* Tarjetas de tipo */
        .tipo-card { border: 2px solid #e5e7eb; border-radius: 12px; padding: 1rem; cursor: pointer; transition: all .2s; }
        .tipo-card:hover { border-color: var(--portal-color); background: #fafafa; }
        .tipo-card.seleccionado { border-color: var(--portal-color); background: rgba(var(--portal-color-rgb, 13,110,253),.06); }
        .tipo-color { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }

        /* Calendario */
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .cal-header { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 4px; }
        .cal-header span { text-align: center; font-size: .72rem; font-weight: 600; color: #6b7280; }
        .cal-day { text-align: center; padding: 8px 4px; border-radius: 8px; font-size: .85rem; cursor: pointer; border: 1px solid transparent; transition: all .15s; }
        .cal-day:not(.disabled):not(.empty):hover { background: #f3f4f6; border-color: var(--portal-color); }
        .cal-day.seleccionado { background: var(--portal-color) !important; color: #fff !important; }
        .cal-day.hoy { font-weight: 700; color: var(--portal-color); }
        .cal-day.disabled { color: #d1d5db; cursor: default; }
        .cal-day.empty { cursor: default; }

        /* Slots */
        .slot-btn { border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 8px 12px; font-size: .85rem; cursor: pointer; transition: all .15s; background: #fff; }
        .slot-btn:hover { border-color: var(--portal-color); background: #fafafa; }
        .slot-btn.seleccionado { border-color: var(--portal-color); background: var(--portal-color); color: #fff; }

        /* Recurso cards */
        .recurso-card { border: 2px solid #e5e7eb; border-radius: 10px; padding: .75rem 1rem; cursor: pointer; transition: all .2s; }
        .recurso-card:hover, .recurso-card.seleccionado { border-color: var(--portal-color); }
        .recurso-card.seleccionado { background: rgba(var(--portal-color-rgb, 13,110,253),.06); }

        /* Botones */
        .btn-portal { background: var(--portal-color); border-color: var(--portal-color); color: #fff; }
        .btn-portal:hover { filter: brightness(.9); color: #fff; }
        .btn-portal:disabled { opacity: .5; }

        /* Secciones */
        .seccion { display: none; }
        .seccion.activa { display: block; }

        .spinner-portal { display: none; }
        .spinner-portal.visible { display: inline-block; }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="portal-header">
    <h4><?= $titulo ?></h4>
    <?php if ($bienvenida): ?>
    <p><?= $bienvenida ?></p>
    <?php else: ?>
    <p>Selecciona el tipo de cita y elige la fecha y hora que prefieras.</p>
    <?php endif; ?>
</div>

<!-- PASOS -->
<div class="pasos" id="baraPasos">
    <div class="paso activo" data-paso="1"><div class="num">1</div><span class="d-none d-sm-inline">Tipo</span></div>
    <div class="separador" id="sep-1-2"></div>
    <?php if (count($recursos) > 1): ?>
    <div class="paso" data-paso="2"><div class="num">2</div><span class="d-none d-sm-inline">Recurso</span></div>
    <div class="separador" id="sep-2-3"></div>
    <?php endif; ?>
    <div class="paso" data-paso="3"><div class="num">3</div><span class="d-none d-sm-inline">Fecha y hora</span></div>
    <div class="separador" id="sep-3-4"></div>
    <div class="paso" data-paso="4"><div class="num">4</div><span class="d-none d-sm-inline">Tus datos</span></div>
    <div class="separador" id="sep-4-5"></div>
    <div class="paso" data-paso="5"><div class="num">5</div><span class="d-none d-sm-inline">Confirmar</span></div>
</div>

<div class="container py-3" style="max-width:600px;">

    <!-- ── PASO 1: Tipo de cita ─────────────────────────────────────── -->
    <div class="seccion activa" id="paso1">
        <h6 class="fw-bold mb-3">¿Qué tipo de cita necesitas?</h6>
        <?php if (empty($tipos)): ?>
            <div class="alert alert-info">No hay tipos de cita disponibles en este momento.</div>
        <?php else: ?>
        <div class="d-flex flex-column gap-2" id="listaTipos">
            <?php foreach ($tipos as $t): ?>
            <div class="tipo-card" data-id="<?= $t['id'] ?>"
                 data-nombre="<?= htmlspecialchars($t['nombre'], ENT_QUOTES) ?>"
                 data-duracion="<?= (int)$t['duracion_minutos'] ?>"
                 data-precio="<?= (float)$t['precio'] ?>"
                 data-tipo-pago="<?= htmlspecialchars($t['tipo_pago'] ?? 'sin_pago', ENT_QUOTES) ?>"
                 data-anticipo="<?= (float)($t['anticipo_porcentaje'] ?? 0) ?>"
                 data-color="<?= htmlspecialchars($t['color'] ?? '#0d6efd', ENT_QUOTES) ?>"
                 onclick="seleccionarTipo(this)">
                <div class="d-flex align-items-start gap-2">
                    <span class="tipo-color mt-1" style="background:<?= htmlspecialchars($t['color'] ?? '#0d6efd', ENT_QUOTES) ?>;"></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small"><?= htmlspecialchars($t['nombre'], ENT_QUOTES) ?></div>
                        <?php if ($t['descripcion']): ?>
                        <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($t['descripcion'], ENT_QUOTES) ?></div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mt-1 flex-wrap" style="font-size:.75rem;">
                            <span class="text-muted"><i class="bi bi-clock me-1"></i><?= (int)$t['duracion_minutos'] ?> min</span>
                            <?php if ((float)$t['precio'] > 0): ?>
                            <span class="fw-semibold" style="color:var(--portal-color);">
                                $<?= number_format((float)$t['precio'], 2) ?>
                                <?php if (($t['tipo_pago'] ?? '') === 'anticipo'): ?>
                                    <span class="text-muted fw-normal">(anticipo <?= (int)($t['anticipo_porcentaje'] ?? 0) ?>%)</span>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted small mt-1"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── PASO 2: Recurso (solo si hay múltiples) ──────────────────── -->
    <?php if (count($recursos) > 1): ?>
    <div class="seccion" id="paso2">
        <h6 class="fw-bold mb-3">¿Con quién / en qué sala?</h6>
        <div class="d-flex flex-column gap-2" id="listaRecursos">
            <div class="recurso-card" data-id="" onclick="seleccionarRecurso(this)">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shuffle text-muted"></i>
                    <div>
                        <div class="fw-semibold small">Sin preferencia</div>
                        <div class="text-muted" style="font-size:.75rem;">Asignación automática</div>
                    </div>
                </div>
            </div>
            <?php foreach ($recursos as $r):
                $icono = $r['tipo'] === 'sala' ? 'bi-door-open' : ($r['tipo'] === 'equipo' ? 'bi-tools' : 'bi-person');
            ?>
            <div class="recurso-card" data-id="<?= $r['id'] ?>"
                 data-nombre="<?= htmlspecialchars($r['nombre'], ENT_QUOTES) ?>"
                 onclick="seleccionarRecurso(this)">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi <?= $icono ?> text-muted"></i>
                    <div>
                        <div class="fw-semibold small"><?= htmlspecialchars($r['nombre'], ENT_QUOTES) ?></div>
                        <?php if ($r['descripcion']): ?>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($r['descripcion'], ENT_QUOTES) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="seccion d-none" id="paso2"></div><!-- placeholder -->
    <?php endif; ?>

    <!-- ── PASO 3: Fecha y hora ──────────────────────────────────────── -->
    <div class="seccion" id="paso3">
        <h6 class="fw-bold mb-3">Elige la fecha y hora</h6>
        <!-- Mini calendario -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <button id="calBtnPrev" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="calNavegar(-1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <strong id="calTituloMes" class="small"></strong>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="calNavegar(1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <div class="cal-header">
                    <span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span>
                    <span>Vi</span><span>Sá</span><span>Do</span>
                </div>
                <div class="cal-grid" id="calGrid"></div>
            </div>
        </div>
        <!-- Slots -->
        <div id="secSlots" class="d-none">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-bold mb-0 small">Horarios disponibles — <span id="lblFechaSelec"></span></h6>
                <span class="spinner-portal spinner-border spinner-border-sm text-secondary" id="spinSlots"></span>
            </div>
            <div class="d-flex flex-wrap gap-2" id="listaSlots"></div>
            <div id="msgSinSlots" class="d-none text-muted small py-2">
                <i class="bi bi-info-circle me-1"></i>No hay horarios disponibles para esta fecha.
            </div>
        </div>
        <!-- Nav -->
        <div class="d-flex justify-content-between mt-3">
            <button class="btn btn-outline-secondary btn-sm" onclick="irPaso(pasoAnterior())">
                <i class="bi bi-arrow-left me-1"></i>Atrás
            </button>
            <button class="btn btn-portal btn-sm px-4" id="btnPaso3Next" disabled onclick="irPaso(4)">
                Siguiente <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>

    <!-- ── PASO 4: Datos del cliente ─────────────────────────────────── -->
    <div class="seccion" id="paso4">
        <h6 class="fw-bold mb-1">Tus datos</h6>
        <p class="text-muted small mb-3">Ingresa tu cédula o email para verificar si ya eres cliente.</p>

        <!-- Verificación rápida -->
        <div id="secVerificar" class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-body p-3">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold mb-1">Cédula / RUC</label>
                        <input type="text" id="vrf-identificacion" class="form-control form-control-sm"
                               placeholder="Ej: 1712345678" maxlength="20" autocomplete="off">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold mb-1">Email</label>
                        <input type="email" id="vrf-email" class="form-control form-control-sm"
                               placeholder="correo@ejemplo.com" autocomplete="off">
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-portal btn-sm px-3" onclick="verificarCliente()">
                            <span class="spinner-portal spinner-border spinner-border-sm me-1" id="spinVerificar"></span>
                            <i class="bi bi-search me-1"></i>Verificar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resultado verificación — cliente encontrado -->
        <div id="secClienteEncontrado" class="d-none">
            <div class="alert alert-success py-2 small mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-person-check-fill fs-5"></i>
                <div>
                    <div class="fw-semibold" id="lblClienteNombre">—</div>
                    <div class="text-muted" id="lblClienteDetalle">—</div>
                </div>
                <button class="btn btn-sm btn-outline-secondary ms-auto py-0 px-2" onclick="resetVerificacion()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>

        <!-- Formulario cliente nuevo -->
        <div id="secClienteNuevo" class="d-none">
            <p class="small text-muted mb-2">No encontramos tu cuenta. Completa tus datos:</p>
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Nombres <span class="text-danger">*</span></label>
                            <input type="text" id="cli-nombres" class="form-control form-control-sm" maxlength="100" required autocomplete="given-name">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Apellidos</label>
                            <input type="text" id="cli-apellidos" class="form-control form-control-sm" maxlength="100" autocomplete="family-name">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Cédula / RUC</label>
                            <input type="text" id="cli-identificacion" class="form-control form-control-sm" maxlength="20">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Email</label>
                            <input type="email" id="cli-email" class="form-control form-control-sm" autocomplete="email">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Teléfono</label>
                            <input type="tel" id="cli-telefono" class="form-control form-control-sm" maxlength="20" autocomplete="tel">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nav -->
        <div class="d-flex justify-content-between mt-3">
            <button class="btn btn-outline-secondary btn-sm" onclick="irPaso(3)">
                <i class="bi bi-arrow-left me-1"></i>Atrás
            </button>
            <button class="btn btn-portal btn-sm px-4" id="btnPaso4Next" onclick="irPaso(5)">
                Siguiente <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>

    <!-- ── PASO 5: Confirmar ─────────────────────────────────────────── -->
    <div class="seccion" id="paso5">
        <h6 class="fw-bold mb-3">Confirma tu reserva</h6>
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-body p-4">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Tipo de cita</dt>
                    <dd class="col-7 fw-semibold" id="res-tipo">—</dd>

                    <dt class="col-5 text-muted">Fecha y hora</dt>
                    <dd class="col-7 fw-semibold" id="res-fecha">—</dd>

                    <dt class="col-5 text-muted d-none" id="lbl-recurso">Con / Sala</dt>
                    <dd class="col-7 d-none" id="res-recurso">—</dd>

                    <dt class="col-5 text-muted">Cliente</dt>
                    <dd class="col-7" id="res-cliente">—</dd>

                    <dt class="col-5 text-muted" id="lbl-precio" style="display:none">Precio</dt>
                    <dd class="col-7 fw-semibold" id="res-precio" style="display:none">—</dd>
                </dl>
            </div>
        </div>

        <!-- Notas opcionales -->
        <div class="mb-3">
            <label class="form-label small fw-semibold mb-1">Notas u observaciones (opcional)</label>
            <textarea id="res-notas" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Escribe aquí cualquier detalle adicional..."></textarea>
        </div>

        <!-- Error -->
        <div id="errorReserva" class="alert alert-danger d-none py-2 small mb-3"></div>

        <!-- Nav -->
        <div class="d-flex justify-content-between">
            <button class="btn btn-outline-secondary btn-sm" onclick="irPaso(4)">
                <i class="bi bi-arrow-left me-1"></i>Atrás
            </button>
            <button class="btn btn-portal btn-sm px-4" id="btnConfirmar" onclick="confirmarReserva()">
                <span class="spinner-portal spinner-border spinner-border-sm me-1" id="spinConfirmar"></span>
                <i class="bi bi-check-circle me-1"></i>Confirmar reserva
            </button>
        </div>
    </div>

</div><!-- /container -->

<script>
const URL_PORTAL   = '<?= $urlBase ?>';
const URL_BASE     = '<?= $baseUrl ?>';
const SLUG         = '<?= $slug ?>';
const MAX_DIAS     = <?= (int)($portalConfig['max_dias_anticipacion'] ?? 30) ?>;
// Mapa tipo_id → [recursos disponibles] para filtrado dinámico
const TIPOS_DATA   = <?= json_encode(array_values($tipos), JSON_UNESCAPED_UNICODE) ?>;
const RECURSOS_ALL = <?= json_encode(array_values($recursos), JSON_UNESCAPED_UNICODE) ?>;
// HAY_RECURSOS se recalcula dinámicamente al seleccionar tipo
let HAY_RECURSOS   = <?= count($recursos) > 1 ? 'true' : 'false' ?>;
let RECURSOS_TIPO  = []; // recursos filtrados para el tipo seleccionado

// Estado de la reserva
const reserva = {
    idTipoCita:    null,
    nombreTipo:    '',
    duracion:      30,
    precio:        0,
    tipoPago:      'sin_pago',
    anticipoPct:   0,
    colorTipo:     '',
    idRecurso:     null,
    nombreRecurso: '',
    fecha:         '',
    horaInicio:    '',
    horaFin:       '',
    fechaHoraIni:  '',
    fechaHoraFin:  '',
    idCliente:     null,
    clienteNombre: '',
    // Datos nuevo cliente
    nombres:       '',
    apellidos:     '',
    identificacion:'',
    email:         '',
    telefono:      '',
};

let pasoActual    = 1;
let calAnio       = new Date().getFullYear();
let calMes        = new Date().getMonth(); // 0-based
let fechaSelec    = '';

// ─── NAVEGACIÓN ───────────────────────────────────────────────────────────────

function irPaso(n) {
    if (n < 1 || n > 5) return;
    // Saltar paso2 si no hay múltiples recursos
    if (n === 2 && !HAY_RECURSOS) { n = n > pasoActual ? 3 : 1; }

    pasoActual = n;
    document.querySelectorAll('.seccion').forEach(s => s.classList.remove('activa'));
    const sec = document.getElementById('paso' + n);
    if (sec) sec.classList.add('activa');
    actualizarBaraPasos(n);
    window.scrollTo({top: 0, behavior: 'smooth'});

    if (n === 3) renderCalendario();
    if (n === 5) rellenarResumen();
}

function pasoAnterior() {
    return (pasoActual === 3 && HAY_RECURSOS) ? 2 : (pasoActual === 3 ? 1 : pasoActual - 1);
}

function actualizarBaraPasos(activo) {
    document.querySelectorAll('.paso').forEach(el => {
        const n = parseInt(el.dataset.paso);
        el.classList.remove('activo', 'completado');
        if (n === activo) el.classList.add('activo');
        else if (n < activo) el.classList.add('completado');
    });
    document.querySelectorAll('.separador').forEach((sep, i) => {
        const n = i + 1; // sep entre paso n y n+1
        sep.classList.toggle('completado', n < activo);
    });
}

// ─── PASO 1: TIPO ─────────────────────────────────────────────────────────────

function seleccionarTipo(el) {
    document.querySelectorAll('.tipo-card').forEach(c => c.classList.remove('seleccionado'));
    el.classList.add('seleccionado');
    reserva.idTipoCita  = el.dataset.id;
    reserva.nombreTipo  = el.dataset.nombre;
    reserva.duracion    = parseInt(el.dataset.duracion) || 30;
    reserva.precio      = parseFloat(el.dataset.precio) || 0;
    reserva.tipoPago    = el.dataset.tipoPago || 'sin_pago';
    reserva.anticipoPct = parseFloat(el.dataset.anticipo) || 0;
    reserva.colorTipo   = el.dataset.color;

    // Determinar recursos disponibles para este tipo
    const tipoData = TIPOS_DATA.find(t => String(t.id) === String(reserva.idTipoCita));
    if (tipoData && tipoData.recursos_ids && tipoData.recursos_ids.length > 0) {
        RECURSOS_TIPO = RECURSOS_ALL.filter(r => tipoData.recursos_ids.includes(parseInt(r.id)));
    } else {
        RECURSOS_TIPO = RECURSOS_ALL; // Sin restricción → todos
    }

    // Si solo hay 1 recurso disponible → auto-seleccionar y saltar paso 2
    if (RECURSOS_TIPO.length === 1) {
        reserva.idRecurso    = RECURSOS_TIPO[0].id;
        reserva.nombreRecurso = RECURSOS_TIPO[0].nombre;
        HAY_RECURSOS = false;
    } else if (RECURSOS_TIPO.length > 1) {
        // Refrescar lista de recursos del paso 2
        reserva.idRecurso    = null;
        reserva.nombreRecurso = '';
        HAY_RECURSOS = true;
        renderRecursosPaso2();
    } else {
        reserva.idRecurso    = null;
        reserva.nombreRecurso = '';
        HAY_RECURSOS = false;
    }

    setTimeout(() => irPaso(HAY_RECURSOS ? 2 : 3), 200);
}

function renderRecursosPaso2() {
    const lista = document.getElementById('listaRecursos');
    if (!lista) return;
    lista.innerHTML = '';

    // Opción "Sin preferencia"
    const sinPref = document.createElement('div');
    sinPref.className = 'recurso-card';
    sinPref.dataset.id = '';
    sinPref.onclick = () => seleccionarRecurso(sinPref);
    sinPref.innerHTML = `<div class="d-flex align-items-center gap-2">
        <i class="bi bi-shuffle text-muted"></i>
        <div><div class="fw-semibold small">Sin preferencia</div>
        <div class="text-muted" style="font-size:.75rem;">Asignación automática</div></div>
    </div>`;
    lista.appendChild(sinPref);

    RECURSOS_TIPO.forEach(r => {
        const icono = r.tipo === 'sala' ? 'bi-door-open' : (r.tipo === 'equipo' ? 'bi-tools' : 'bi-person');
        const card  = document.createElement('div');
        card.className = 'recurso-card';
        card.dataset.id     = r.id;
        card.dataset.nombre = r.nombre;
        card.onclick = () => seleccionarRecurso(card);
        card.innerHTML = `<div class="d-flex align-items-center gap-2">
            <i class="bi ${icono} text-muted"></i>
            <div><div class="fw-semibold small">${r.nombre}</div>
            ${r.descripcion ? `<div class="text-muted" style="font-size:.75rem;">${r.descripcion}</div>` : ''}
            </div>
        </div>`;
        lista.appendChild(card);
    });
}

// ─── PASO 2: RECURSO ──────────────────────────────────────────────────────────

function seleccionarRecurso(el) {
    document.querySelectorAll('.recurso-card').forEach(c => c.classList.remove('seleccionado'));
    el.classList.add('seleccionado');
    reserva.idRecurso    = el.dataset.id || null;
    reserva.nombreRecurso = el.dataset.nombre || '';
    setTimeout(() => irPaso(3), 200);
}

// ─── PASO 3: CALENDARIO ───────────────────────────────────────────────────────

function renderCalendario() {
    const hoy     = new Date();
    const hoyAnio = hoy.getFullYear();
    const hoyMes  = hoy.getMonth();
    const hoyDia  = hoy.getDate();

    // Fecha máxima: MAX_DIAS días desde hoy (al final del día)
    const maxDate = new Date(hoyAnio, hoyMes, hoyDia + MAX_DIAS, 23, 59, 59);

    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    document.getElementById('calTituloMes').textContent = meses[calMes] + ' ' + calAnio;

    // Ocultar/mostrar el botón de navegación izquierda
    const btnPrev = document.getElementById('calBtnPrev');
    const esMesActual = (calAnio === hoyAnio && calMes === hoyMes);
    if (btnPrev) btnPrev.style.visibility = esMesActual ? 'hidden' : 'visible';

    const primero = new Date(calAnio, calMes, 1);
    const diasMes = new Date(calAnio, calMes + 1, 0).getDate();
    // ISO: 1=Lun, 7=Dom → ajuste para grilla que empieza en Lunes
    let inicioSemana = primero.getDay(); // 0=Dom
    inicioSemana = inicioSemana === 0 ? 6 : inicioSemana - 1;

    const grid = document.getElementById('calGrid');
    grid.innerHTML = '';

    // Celdas vacías
    for (let i = 0; i < inicioSemana; i++) {
        const div = document.createElement('div');
        div.className = 'cal-day empty';
        grid.appendChild(div);
    }

    // Fecha de hoy a medianoche para comparación exacta
    const hoyMidnight = new Date(hoyAnio, hoyMes, hoyDia);

    for (let dia = 1; dia <= diasMes; dia++) {
        const fecha = new Date(calAnio, calMes, dia);
        const yyyy  = calAnio;
        const mm    = String(calMes + 1).padStart(2, '0');
        const dd    = String(dia).padStart(2, '0');
        const fStr  = `${yyyy}-${mm}-${dd}`;

        const div = document.createElement('div');
        div.textContent = dia;
        div.className   = 'cal-day';

        const esHoy    = (calAnio === hoyAnio && calMes === hoyMes && dia === hoyDia);
        const pasado   = fecha < hoyMidnight;
        const muFuturo = fecha > maxDate;

        if (esHoy)              div.classList.add('hoy');
        if (pasado || muFuturo) {
            div.classList.add('disabled');
            div.setAttribute('aria-disabled', 'true');
        } else {
            div.onclick = () => seleccionarFecha(fStr);
        }
        if (fStr === fechaSelec) div.classList.add('seleccionado');
        grid.appendChild(div);
    }
}

function calNavegar(delta) {
    const hoy = new Date();
    const nuevoMes  = calMes + delta;
    let nuevoAnio   = calAnio;
    let mesFinal    = nuevoMes;

    if (nuevoMes < 0)  { mesFinal = 11; nuevoAnio--; }
    if (nuevoMes > 11) { mesFinal = 0;  nuevoAnio++; }

    // No permitir navegar a un mes/año anterior al actual
    if (nuevoAnio < hoy.getFullYear() ||
        (nuevoAnio === hoy.getFullYear() && mesFinal < hoy.getMonth())) {
        return;
    }

    calMes  = mesFinal;
    calAnio = nuevoAnio;
    renderCalendario();
}

function seleccionarFecha(fecha) {
    // Validación extra: rechazar fechas pasadas aunque se llegue por otra vía
    const hoy = new Date();
    const hoyMidnight = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
    const partes = fecha.split('-');
    const fechaObj = new Date(parseInt(partes[0]), parseInt(partes[1]) - 1, parseInt(partes[2]));
    if (fechaObj < hoyMidnight) return;

    fechaSelec = fecha;
    reserva.fecha = fecha;
    document.querySelectorAll('.cal-day.seleccionado').forEach(d => d.classList.remove('seleccionado'));
    // Marcar el día seleccionado: buscar por día del mes en el mes/año actual del calendario
    const dia = parseInt(partes[2]);
    document.querySelectorAll('.cal-day').forEach(d => {
        if (parseInt(d.textContent) === dia && !d.classList.contains('disabled') && !d.classList.contains('empty')) {
            d.classList.add('seleccionado');
        }
    });
    cargarSlots(fecha);
}

function cargarSlots(fecha) {
    const sec = document.getElementById('secSlots');
    const spin = document.getElementById('spinSlots');
    const lista = document.getElementById('listaSlots');
    const msgSin = document.getElementById('msgSinSlots');
    const btn = document.getElementById('btnPaso3Next');

    sec.classList.remove('d-none');
    spin.classList.add('visible');
    lista.innerHTML = '';
    msgSin.classList.add('d-none');
    btn.disabled = true;

    const f = fecha.split('-');
    const meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    document.getElementById('lblFechaSelec').textContent = `${parseInt(f[2])} de ${meses[parseInt(f[1])-1]}`;

    const params = new URLSearchParams({
        fecha,
        id_tipo_cita: reserva.idTipoCita,
        id_recurso:   reserva.idRecurso || '',
        slug: SLUG,
    });

    fetch(`${URL_PORTAL}/disponibilidad?${params}`)
        .then(r => r.json())
        .then(res => {
            spin.classList.remove('visible');
            if (!res.ok) {
                msgSin.classList.remove('d-none');
                msgSin.textContent = res.mensaje || 'No hay horarios disponibles.';
                return;
            }
            if (!res.slots.length) {
                msgSin.classList.remove('d-none');
                return;
            }
            res.slots.forEach(slot => {
                const el = document.createElement('div');
                el.className   = 'slot-btn';
                el.textContent = slot.inicio;
                el.dataset.inicio    = slot.inicio;
                el.dataset.fin       = slot.fin;
                el.dataset.fechaHora = slot.fecha_hora;
                el.dataset.fechaHoraFin = slot.fecha_hora_fin;
                el.onclick = () => seleccionarSlot(el);
                lista.appendChild(el);
            });
        })
        .catch(() => {
            spin.classList.remove('visible');
            msgSin.classList.remove('d-none');
        });
}

function seleccionarSlot(el) {
    document.querySelectorAll('.slot-btn.seleccionado').forEach(s => s.classList.remove('seleccionado'));
    el.classList.add('seleccionado');
    reserva.horaInicio   = el.dataset.inicio;
    reserva.horaFin      = el.dataset.fin;
    reserva.fechaHoraIni = el.dataset.fechaHora;
    reserva.fechaHoraFin = el.dataset.fechaHoraFin;
    document.getElementById('btnPaso3Next').disabled = false;
}

// ─── PASO 4: CLIENTE ──────────────────────────────────────────────────────────

function verificarCliente() {
    const ident = document.getElementById('vrf-identificacion').value.trim();
    const email = document.getElementById('vrf-email').value.trim();
    if (!ident && !email) { alert('Ingresa tu cédula o email para buscar.'); return; }

    const spin = document.getElementById('spinVerificar');
    spin.classList.add('visible');

    const fd = new FormData();
    fd.append('identificacion', ident);
    fd.append('email', email);

    fetch(`${URL_PORTAL}/verificar-cliente?slug=${SLUG}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            spin.classList.remove('visible');
            if (!res.ok) { alert(res.mensaje || 'Error al verificar.'); return; }

            if (res.encontrado && res.cliente) {
                const c = res.cliente;
                reserva.idCliente     = c.id;
                reserva.clienteNombre = c.nombre;
                // Limpiar nuevo
                reserva.nombres = ''; reserva.apellidos = ''; reserva.identificacion = '';
                reserva.email = ''; reserva.telefono = '';

                document.getElementById('lblClienteNombre').textContent  = c.nombre;
                document.getElementById('lblClienteDetalle').textContent = [c.identificacion, c.email].filter(Boolean).join(' · ');
                document.getElementById('secClienteEncontrado').classList.remove('d-none');
                document.getElementById('secClienteNuevo').classList.add('d-none');
                document.getElementById('secVerificar').classList.add('d-none');
            } else {
                reserva.idCliente = null;
                // Pre-llenar campos con lo ingresado
                document.getElementById('cli-identificacion').value = ident;
                document.getElementById('cli-email').value          = email;
                document.getElementById('secClienteNuevo').classList.remove('d-none');
            }
        })
        .catch(() => { spin.classList.remove('visible'); alert('Error de conexión.'); });
}

function resetVerificacion() {
    reserva.idCliente = null;
    reserva.clienteNombre = '';
    document.getElementById('secClienteEncontrado').classList.add('d-none');
    document.getElementById('secClienteNuevo').classList.add('d-none');
    document.getElementById('secVerificar').classList.remove('d-none');
    document.getElementById('vrf-identificacion').value = '';
    document.getElementById('vrf-email').value = '';
}

// ─── PASO 5: RESUMEN ──────────────────────────────────────────────────────────

function rellenarResumen() {
    document.getElementById('res-tipo').textContent  = reserva.nombreTipo || '—';

    const f = reserva.fecha.split('-');
    const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const fechaStr = `${parseInt(f[2])} ${meses[parseInt(f[1])-1]} ${f[0]} · ${reserva.horaInicio} – ${reserva.horaFin}`;
    document.getElementById('res-fecha').textContent = fechaStr;

    if (reserva.nombreRecurso) {
        document.getElementById('lbl-recurso').classList.remove('d-none');
        document.getElementById('res-recurso').classList.remove('d-none');
        document.getElementById('res-recurso').textContent = reserva.nombreRecurso;
    }

    // Obtener nombre del cliente
    let clienteStr = '—';
    if (reserva.idCliente) {
        clienteStr = reserva.clienteNombre;
    } else {
        const nombres = document.getElementById('cli-nombres')?.value.trim() || '';
        const ape     = document.getElementById('cli-apellidos')?.value.trim() || '';
        if (nombres) clienteStr = nombres + (ape ? ' ' + ape : '');
    }
    document.getElementById('res-cliente').textContent = clienteStr;

    if (reserva.precio > 0) {
        document.getElementById('lbl-precio').style.display = '';
        document.getElementById('res-precio').style.display = '';
        let precioStr = '$' + reserva.precio.toFixed(2);
        if (reserva.tipoPago === 'anticipo') {
            const anticipo = (reserva.precio * reserva.anticipoPct / 100).toFixed(2);
            precioStr += ` (anticipo: $${anticipo})`;
        }
        document.getElementById('res-precio').textContent = precioStr;
    }
}

// ─── CONFIRMAR RESERVA ────────────────────────────────────────────────────────

function confirmarReserva() {
    const btn  = document.getElementById('btnConfirmar');
    const spin = document.getElementById('spinConfirmar');
    const err  = document.getElementById('errorReserva');

    err.classList.add('d-none');
    btn.disabled = true;
    spin.classList.add('visible');

    const fd = new FormData();
    fd.append('id_tipo_cita',  reserva.idTipoCita  || '');
    fd.append('id_recurso',    reserva.idRecurso    || '');
    fd.append('id_cliente',    reserva.idCliente    || '');
    fd.append('fecha_inicio',  reserva.fechaHoraIni);
    fd.append('fecha_fin',     reserva.fechaHoraFin);
    fd.append('notas',         document.getElementById('res-notas').value.trim());

    if (!reserva.idCliente) {
        fd.append('nombres',        document.getElementById('cli-nombres')?.value.trim()       || '');
        fd.append('apellidos',      document.getElementById('cli-apellidos')?.value.trim()     || '');
        fd.append('identificacion', document.getElementById('cli-identificacion')?.value.trim()|| '');
        fd.append('email',          document.getElementById('cli-email')?.value.trim()         || '');
        fd.append('telefono',       document.getElementById('cli-telefono')?.value.trim()      || '');
    }

    fetch(`${URL_PORTAL}/reservar?slug=${SLUG}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            spin.classList.remove('visible');
            if (res.ok) {
                window.location.href = `${URL_BASE}/reservas/${SLUG}/confirmacion?id=${res.id}`;
            } else {
                btn.disabled = false;
                err.classList.remove('d-none');
                err.textContent = res.mensaje || 'Error al procesar la reserva.';
            }
        })
        .catch(() => {
            spin.classList.remove('visible');
            btn.disabled = false;
            err.textContent = 'Error de conexión. Inténtalo de nuevo.';
            err.classList.remove('d-none');
        });
}

// ─── INIT ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    actualizarBaraPasos(1);
});
</script>
</body>
</html>
