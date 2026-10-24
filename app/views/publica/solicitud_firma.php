<?php
/**
 * Portal público del cliente: datos → documentos → pago → seguimiento del trámite.
 * Variables: $portal (FirmaElectronicaService::resolverPortal/resolverEnlaceFijo), $provincias.
 */
use App\Rules\modulos\FirmaElectronicaRules;
use App\Services\modulos\FirmaElectronicaService;

$sol       = $portal['solicitud'];
$firma     = $portal['firma'];
$f         = $firma ?? [];
$e         = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base      = rtrim(BASE_URL, '/');
$esFijo    = ($sol['token'] ?? '') === '';
$urlBase   = $esFijo ? '' : $base . '/solicitud-firma/' . $sol['token'];
$urlGuardar = $esFijo ? $base . '/solicitud-firma/e/' . $sol['enlace_codigo'] : $urlBase . '/guardar';
$estado    = (string) ($f['estado'] ?? '');
$estadoPago = (string) ($f['estado_pago'] ?? 'pendiente');
$editable  = $portal['editable'];

// Documentos ya subidos por tipo (el último de cada tipo).
$subidos = [];
foreach ($portal['adjuntos'] as $a) {
    $subidos[$a['tipo']] = $a;
}
$faltan = array_values(array_diff($portal['requeridos'], array_keys($subidos)));

// Paso actual del cliente.
if (!$firma) {
    $paso = 1;
} elseif (in_array($estado, ['borrador', 'observada'], true)) {
    $paso = 2;
} elseif ($estado === 'pendiente') {
    $paso = 3;
} else {
    $paso = 4;
}
$mostrarForm = !$firma || $estado === 'observada';
$puedePagar  = $firma && $estado === 'pendiente' && in_array($estadoPago, ['pendiente', 'rechazado'], true);

$documentos = [
    'cedula_frontal'          => ['bi-credit-card-fill',    'image/*'],
    'cedula_posterior'        => ['bi-credit-card',         'image/*'],
    'selfie'                  => ['bi-person-bounding-box', 'image/*'],
    'ruc_empresa'             => ['bi-file-earmark-text',   'application/pdf'],
    'constitucion_compania'   => ['bi-file-earmark-ruled',  'application/pdf'],
    'nombramiento'            => ['bi-file-earmark-person', 'application/pdf'],
    'aceptacion_nombramiento' => ['bi-file-earmark-check',  'application/pdf'],
    'cedula_representante'    => ['bi-person-vcard',        'image/*,application/pdf'],
    'autorizacion'            => ['bi-file-earmark-medical', 'application/pdf'],
];
$tipoPersona = $f['tipo_persona'] ?? 'natural';
$colorEstado = [
    'borrador' => 'secondary', 'pendiente' => 'warning', 'por_registrar' => 'info', 'en_proceso' => 'primary', 'aprobada' => 'success',
    'observada' => 'danger', 'emitida' => 'success', 'cancelada' => 'dark',
];
$etiquetaCliente = [
    'borrador'      => 'Faltan tus documentos',
    'pendiente'     => $estadoPago === 'en_revision' ? 'Pago en revisión' : 'Pendiente de pago',
    'por_registrar' => 'Pagada — en preparación',
    'en_proceso'    => 'En trámite con la entidad certificadora',
    'aprobada'      => 'Aprobada: falta solo la emisión de tu firma',
    'observada'     => 'Requiere corrección',
    'emitida'       => 'Firma emitida',
    'cancelada'     => 'Cancelada',
];
$v = fn($k, $def = '') => $e($f[$k] ?? $def);
$fechaNac = !empty($f['fecha_nacimiento']) ? date('Y-m-d', strtotime($f['fecha_nacimiento'])) : '';
$mismos   = !in_array($f['facturacion_mismos_datos'] ?? true, [false, 'f', 0, '0'], true);
$nacionalidades = ['Ecuatoriana', 'Colombiana', 'Peruana', 'Venezolana', 'Cubana', 'Boliviana', 'Argentina', 'Chilena',
                   'Brasileña', 'Paraguaya', 'Uruguaya', 'Estadounidense', 'Canadiense', 'Mexicana', 'Española', 'Italiana', 'Otra'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Electrónica - <?= $e($sol['empresa_nombre'] ?? '') ?></title>
    <?php require MVC_APP . '/views/partials/csrf.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,#f8fafc 0%,#e2e8f0 100%); min-height: 100dvh; font-family: 'Segoe UI', sans-serif; }
        .page-header { background: linear-gradient(135deg,#1e293b 0%,#334155 100%); color:#fff; padding: 1.75rem 1.25rem 2.5rem; border-radius: 0 0 1.5rem 1.5rem; }
        .stepper { display:flex; justify-content:space-between; max-width:560px; margin: -1.6rem auto 1.5rem; padding: 0 .75rem; }
        .step { flex:1; text-align:center; font-size:.72rem; color:#64748b; position:relative; }
        .step .dot { width:34px; height:34px; border-radius:50%; background:#fff; border:2px solid #cbd5e1; display:flex; align-items:center; justify-content:center; margin:0 auto .3rem; font-weight:700; color:#94a3b8; }
        .step.done .dot { background:#22c55e; border-color:#22c55e; color:#fff; }
        .step.active .dot { border-color:#3b82f6; color:#3b82f6; box-shadow:0 0 0 4px rgba(59,130,246,.15); }
        .step.active { color:#1e293b; font-weight:600; }
        .section-title { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: .5rem; }
        .form-label { font-size: .82rem; font-weight: 500; color: #374151; margin-bottom: .25rem; }
        .form-control, .form-select { font-size: .88rem; }
        .req { color: #ef4444; }
        .adj-box { border: 2px dashed #cbd5e1; border-radius: .75rem; padding: .9rem; text-align: center; cursor: pointer; position: relative; min-height: 118px; display:flex; flex-direction:column; align-items:center; justify-content:center; transition: border-color .2s, background .2s; }
        .adj-box:hover { border-color: #3b82f6; background: #eff6ff; }
        .adj-box input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .adj-box .adj-icon { font-size: 1.7rem; color: #94a3b8; }
        .adj-box .adj-name { font-size: .74rem; color: #64748b; margin-top: .3rem; word-break: break-all; }
        .adj-box.ok { border-style: solid; border-color: #22c55e; background: #f0fdf4; }
        .adj-box.ok .adj-icon { color: #22c55e; }
        .adj-box.cargando { opacity: .6; pointer-events: none; }
        .price-tag { font-size: .95rem; font-weight: 600; color: #16a34a; }
        .timeline { list-style:none; padding-left:0; margin:0; }
        .timeline li { position:relative; padding: 0 0 .9rem 1.4rem; font-size:.85rem; }
        .timeline li::before { content:''; position:absolute; left:.33rem; top:.35rem; width:.6rem; height:.6rem; border-radius:50%; background:#3b82f6; }
        .timeline li::after { content:''; position:absolute; left:.6rem; top:1rem; bottom:0; width:1px; background:#cbd5e1; }
        .timeline li:last-child::after { display:none; }
        .total-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:.75rem; padding:.75rem 1rem; }
        #avisoFlotante { position: fixed; top: 1rem; left: 50%; transform: translateX(-50%); z-index: 2000; min-width: 280px; max-width: 92vw; }
    </style>
</head>
<body>

<div id="avisoFlotante"></div>

<div class="page-header text-center">
    <h4 class="mb-1 fw-bold"><?= $e($sol['empresa_nombre'] ?? '') ?></h4>
    <p class="mb-0 small opacity-75">Solicitud de Firma Electrónica</p>
</div>

<div class="stepper">
    <?php foreach ([1 => 'Tus datos', 2 => 'Documentos', 3 => 'Pago', 4 => 'Trámite'] as $n => $lbl): ?>
        <div class="step <?= $n < $paso ? 'done' : ($n === $paso ? 'active' : '') ?>">
            <div class="dot"><?= $n < $paso ? '<i class="bi bi-check-lg"></i>' : $n ?></div><?= $lbl ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="container pb-5" style="max-width:760px;">

<?php if ($firma): ?>
    <!-- ESTADO -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3 d-flex flex-wrap align-items-center gap-2">
            <div class="flex-grow-1">
                <div class="section-title mb-1">Estado de tu solicitud</div>
                <span class="badge bg-<?= $colorEstado[$estado] ?? 'secondary' ?> fs-6"><?= $e($etiquetaCliente[$estado] ?? $estado) ?></span>
            </div>
            <div class="text-end small text-muted">
                <?= $e(trim(($f['nombres'] ?? '') . ' ' . ($f['apellidos'] ?? ''))) ?><br>
                <?= $e($f['nombre_producto'] ?? '') ?>
            </div>
        </div>
        <?php if ($estado === 'observada' && !empty($f['observacion_cliente'])): ?>
            <div class="alert alert-danger rounded-0 rounded-bottom-3 mb-0 small">
                <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Debes corregir:</strong>
                <?= nl2br($e($f['observacion_cliente'])) ?>
                <div class="mt-1">Corrige tus datos o documentos y pulsa <b>Enviar corrección</b>.</div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($mostrarForm || ($editable && $firma)): ?>
<!-- ═══ PASO 1: DATOS ═══ -->
<?php if ($firma && !$mostrarForm): ?>
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3 d-flex align-items-center">
            <div class="flex-grow-1 small">
                <div class="section-title mb-1"><i class="bi bi-person-check me-1"></i>Tus datos</div>
                <?= $e(($f['apellidos'] ?? '') . ' ' . ($f['nombres'] ?? '')) ?> · <?= $e($f['numero_identificacion'] ?? '') ?> · <?= $e($f['correo'] ?? '') ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#bloqueDatos">
                <i class="bi bi-pencil me-1"></i>Editar
            </button>
        </div>
    </div>
<?php endif; ?>

<div id="bloqueDatos" class="<?= ($firma && !$mostrarForm) ? 'collapse' : '' ?>">
<form id="formDatos" novalidate>
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title mb-2"><i class="bi bi-person-badge me-1"></i>Tipo de persona</p>
            <div class="d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_persona" id="tp_natural" value="natural" <?= $tipoPersona === 'natural' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tp_natural">Persona Natural</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_persona" id="tp_juridica" value="juridica" <?= $tipoPersona === 'juridica' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tp_juridica">Representante legal</label>
                </div>
                <?php // «Miembro de empresa» ya no se ofrece; solo se ve en una solicitud que ya lo tenía. ?>
                <?php if ($tipoPersona === 'miembro'): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_persona" id="tp_miembro" value="miembro" checked>
                    <label class="form-check-label" for="tp_miembro">Miembro de empresa</label>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-text small" id="ayudaTipoPersona"></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-pen me-1"></i>Validez de la firma <span class="req">*</span></p>
            <?php if (empty($portal['tiposFirma'])): ?>
                <p class="text-muted small mb-0">La empresa aún no tiene firmas disponibles para la venta.</p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($portal['tiposFirma'] as $tf): ?>
                        <div class="col-12 col-sm-6">
                            <div class="form-check border rounded-2 p-2 ps-4">
                                <input class="form-check-input" type="radio" name="id_producto" id="tf_<?= (int) $tf['id'] ?>" value="<?= (int) $tf['id'] ?>"
                                       <?= (int) ($f['id_producto'] ?? 0) === (int) $tf['id'] ? 'checked' : '' ?>>
                                <label class="form-check-label d-flex justify-content-between align-items-center w-100 pe-1" for="tf_<?= (int) $tf['id'] ?>">
                                    <span class="fw-medium small"><?= $e($tf['nombre']) ?></span>
                                    <span class="price-tag ms-2">$<?= number_format((float) $tf['pvp'], 2) ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted mt-2">Precios con IVA incluido.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="bloqueJuridica" class="card border-0 shadow-sm rounded-3 mb-3 d-none">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-building me-1"></i>Datos de la empresa</p>
            <div class="row g-2">
                <div class="col-12 col-sm-5">
                    <label class="form-label" for="ruc_empresa">RUC de la empresa <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="ruc_empresa" name="ruc_empresa" maxlength="13" inputmode="numeric" value="<?= $v('ruc_empresa') ?>">
                </div>
                <div class="col-12 col-sm-7">
                    <label class="form-label" for="nombre_empresa">Razón social <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="nombre_empresa" name="nombre_empresa" value="<?= $v('nombre_empresa') ?>">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="cargo">Su cargo en la empresa <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="cargo" name="cargo" value="<?= $v('cargo') ?>" placeholder="Ej. Gerente general">
                </div>
                <div class="col-12 col-sm-6 solo-miembro">
                    <label class="form-label" for="departamento">Departamento / área <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="departamento" name="departamento" value="<?= $v('departamento') ?>" placeholder="Ej. Contabilidad">
                </div>
                <div class="col-12">
                    <label class="form-label" for="motivo">¿Para qué usará la firma? <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="motivo" name="motivo" value="<?= $v('motivo', 'Facturación electrónica y firma de documentos') ?>">
                </div>
            </div>
            <div class="solo-miembro mt-3">
                <p class="section-title mb-1"><i class="bi bi-person-vcard me-1"></i>Representante legal de la empresa</p>
                <div class="row g-2">
                    <div class="col-12 col-sm-4">
                        <label class="form-label">Tipo</label>
                        <select class="form-select form-select-sm" name="rep_tipo_identificacion">
                            <option value="cedula" <?= ($f['rep_tipo_identificacion'] ?? 'cedula') === 'cedula' ? 'selected' : '' ?>>Cédula</option>
                            <option value="pasaporte" <?= ($f['rep_tipo_identificacion'] ?? '') === 'pasaporte' ? 'selected' : '' ?>>Pasaporte</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-8">
                        <label class="form-label">Identificación <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="rep_identificacion" maxlength="20" value="<?= $v('rep_identificacion') ?>">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label">Nombres <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="rep_nombres" value="<?= $v('rep_nombres') ?>">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label">Apellidos <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="rep_apellidos" value="<?= $v('rep_apellidos') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-card-text me-1"></i>Identificación</p>
            <div class="row g-2">
                <div class="col-12 col-sm-4">
                    <label class="form-label">Tipo <span class="req">*</span></label>
                    <select class="form-select form-select-sm" name="tipo_identificacion" id="tipo_identificacion">
                        <?php foreach (['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= ($f['tipo_identificacion'] ?? 'cedula') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-5">
                    <label class="form-label">Número <span class="req">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" name="numero_identificacion" id="numero_identificacion" maxlength="20" value="<?= $v('numero_identificacion') ?>">
                        <button type="button" class="btn btn-outline-primary px-2" id="btnBuscarSri" title="Buscar mis datos">
                            <i class="bi bi-search" id="sriIcono"></i><span id="sriLoader" class="spinner-border spinner-border-sm d-none"></span>
                        </button>
                    </div>
                </div>
                <div class="col-12 col-sm-3 d-flex align-items-end pb-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="con_ruc" name="con_ruc" value="1" <?= in_array($f['con_ruc'] ?? false, [true, 't', 1, '1'], true) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-medium" for="con_ruc">¿Tienes RUC?</label>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label" for="codigo_dactilar">Código dactilar <span class="req" id="reqDactilar">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="codigo_dactilar" name="codigo_dactilar" maxlength="20" value="<?= $v('codigo_dactilar') ?>" placeholder="Ej. V4443V4442">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-person me-1"></i>Datos personales</p>
            <div class="row g-2">
                <div class="col-6 col-sm-3">
                    <label class="form-label">Primer apellido <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="apellido_paterno" id="apellido_paterno" value="<?= $e($f['apellido_paterno'] ?? ($f['apellidos'] ?? '')) ?>">
                </div>
                <div class="col-6 col-sm-3">
                    <label class="form-label">Segundo apellido</label>
                    <input type="text" class="form-control form-control-sm" name="apellido_materno" id="apellido_materno" value="<?= $v('apellido_materno') ?>">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Nombres <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="nombres" id="nombres" value="<?= $v('nombres') ?>">
                </div>
                <div class="col-6 col-sm-4">
                    <label class="form-label">Sexo <span class="req">*</span></label>
                    <select class="form-select form-select-sm" name="sexo">
                        <option value="">-- Seleccione --</option>
                        <option value="hombre" <?= ($f['sexo'] ?? '') === 'hombre' ? 'selected' : '' ?>>Masculino</option>
                        <option value="mujer"  <?= ($f['sexo'] ?? '') === 'mujer'  ? 'selected' : '' ?>>Femenino</option>
                    </select>
                </div>
                <div class="col-6 col-sm-4">
                    <label class="form-label">Fecha de nacimiento <span class="req">*</span></label>
                    <input type="date" class="form-control form-control-sm" name="fecha_nacimiento" value="<?= $e($fechaNac) ?>">
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label">Nacionalidad</label>
                    <select class="form-select form-select-sm" name="nacionalidad">
                        <?php foreach ($nacionalidades as $n): ?>
                            <option value="<?= $n ?>" <?= ($f['nacionalidad'] ?? 'Ecuatoriana') === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Celular <span class="req">*</span></label>
                    <input type="tel" class="form-control form-control-sm" name="telefono" value="<?= $e($f['telefono'] ?? ($sol['telefono_destino'] ?? '')) ?>" placeholder="09XXXXXXXX">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Correo electrónico <span class="req">*</span></label>
                    <input type="email" class="form-control form-control-sm" name="correo" value="<?= $e($f['correo'] ?? ($sol['correo_destino'] ?? '')) ?>">
                    <div class="form-text small">Aquí te llegará la factura y la activación de tu firma.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <p class="section-title"><i class="bi bi-geo-alt me-1"></i>Ubicación</p>
            <div class="row g-2">
                <div class="col-12 col-sm-4">
                    <label class="form-label">Provincia <span class="req">*</span></label>
                    <select class="form-select form-select-sm" name="cod_prov" id="cod_prov">
                        <option value="">-- Seleccione --</option>
                        <?php foreach ($provincias as $prov): ?>
                            <option value="<?= $e($prov['codigo']) ?>" <?= ($f['cod_prov'] ?? '') === $prov['codigo'] ? 'selected' : '' ?>><?= $e($prov['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label">Ciudad <span class="req">*</span></label>
                    <select class="form-select form-select-sm" name="cod_ciudad" id="cod_ciudad">
                        <option value="">-- Seleccione provincia --</option>
                    </select>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label">Dirección <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="direccion" minlength="10" maxlength="255" placeholder="Calle, número, sector (mín. 10 caracteres)" value="<?= $v('direccion') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="facturarOtro" <?= $mismos ? '' : 'checked' ?>>
                <label class="form-check-label small fw-medium" for="facturarOtro">Quiero la factura a otro nombre</label>
            </div>
            <input type="hidden" name="facturacion_mismos_datos" id="facturacion_mismos_datos" value="<?= $mismos ? '1' : '0' ?>">
            <div id="bloqueFacturacion" class="row g-2 mt-1 <?= $mismos ? 'd-none' : '' ?>">
                <div class="col-12 col-sm-4">
                    <label class="form-label">Tipo</label>
                    <select class="form-select form-select-sm" name="facturacion_tipo_id" id="facturacion_tipo_id">
                        <?php foreach (['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= ($f['facturacion_tipo_id'] ?? '') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-8">
                    <label class="form-label">Identificación</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" name="facturacion_num_id" id="facturacion_num_id" maxlength="13" inputmode="numeric" value="<?= $v('facturacion_num_id') ?>">
                        <button type="button" class="btn btn-outline-primary px-2" id="btnBuscarSriFact" title="Buscar en el SRI">
                            <i class="bi bi-search" id="sriFactIcono"></i><span id="sriFactLoader" class="spinner-border spinner-border-sm d-none"></span>
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Nombre o razón social</label>
                    <input type="text" class="form-control form-control-sm" name="facturacion_nombres" id="facturacion_nombres" value="<?= $v('facturacion_nombres') ?>">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Correo</label>
                    <input type="email" class="form-control form-control-sm" name="facturacion_correo" value="<?= $v('facturacion_correo') ?>">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Teléfono</label>
                    <input type="text" class="form-control form-control-sm" name="facturacion_telefono" value="<?= $v('facturacion_telefono') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Dirección</label>
                    <input type="text" class="form-control form-control-sm" name="facturacion_direccion" id="facturacion_direccion" value="<?= $v('facturacion_direccion') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <label class="form-label section-title" for="observaciones"><i class="bi bi-chat-text me-1"></i>Observaciones</label>
            <textarea class="form-control form-control-sm" name="observaciones" id="observaciones" rows="2"><?= $v('observaciones') ?></textarea>
        </div>
    </div>

    <div class="d-grid mb-4">
        <button type="submit" class="btn btn-primary btn-lg fw-semibold" id="btnGuardarDatos">
            <i class="bi bi-arrow-right-circle me-2"></i><?= $firma ? 'Guardar cambios' : 'Continuar' ?>
        </button>
    </div>
</form>
</div>
<?php endif; ?>

<?php if ($firma && $editable): ?>
<!-- ═══ PASO 2: DOCUMENTOS ═══ -->
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-3">
        <p class="section-title"><i class="bi bi-file-earmark-image me-1"></i>Tus documentos</p>
        <p class="text-muted small mb-3">Fotos claras y completas de tu cédula y una selfie sosteniéndola. Los documentos de la empresa en PDF. Máximo 5 MB cada uno. Cada archivo se guarda al subirlo.</p>
        <div class="row g-3">
            <?php foreach ($portal['requeridos'] as $tipo):
                [$icono, $accept] = $documentos[$tipo];
                $sub = $subidos[$tipo] ?? null; ?>
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="adj-box <?= $sub ? 'ok' : '' ?>" data-tipo="<?= $tipo ?>">
                        <input type="file" accept="<?= $accept ?>">
                        <div class="adj-icon bi <?= $sub ? 'bi-check-circle-fill' : $icono ?>"></div>
                        <div class="fw-medium small mt-1"><?= $e(FirmaElectronicaRules::ETIQUETAS_ADJUNTO[$tipo]) ?> <span class="req">*</span></div>
                        <div class="adj-name"><?= $sub ? $e($sub['nombre_original']) . ' · cambiar' : 'Toca para subir' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (in_array($estado, ['borrador', 'observada'], true)): ?>
        <div class="d-grid mt-3">
            <button type="button" class="btn btn-success fw-semibold" id="btnEnviarDocs">
                <i class="bi bi-send me-2"></i><?= $estado === 'observada' ? 'Enviar corrección' : 'Enviar documentos' ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($firma && $estado === 'pendiente'): ?>
<!-- ═══ PASO 3: PAGO ═══ -->
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-3">
        <p class="section-title"><i class="bi bi-credit-card me-1"></i>Pago</p>
        <?php if ($portal['total'] !== null): ?>
            <div class="total-box d-flex justify-content-between align-items-center mb-3">
                <span class="small"><?= $e($f['nombre_producto'] ?? 'Firma electrónica') ?></span>
                <span class="fs-5 fw-bold text-success">$<?= number_format((float) $portal['total'], 2) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($estadoPago === 'en_revision'): ?>
            <div class="alert alert-info small mb-0"><i class="bi bi-hourglass-split me-1"></i>Recibimos tu comprobante y lo estamos revisando. Te avisaremos por correo.</div>
        <?php elseif ($puedePagar): ?>
            <?php if ($estadoPago === 'rechazado' && !empty($f['pago_motivo_rechazo'])): ?>
                <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i><b>Tu comprobante no fue aceptado:</b> <?= $e($f['pago_motivo_rechazo']) ?></div>
            <?php endif; ?>
            <?php if ($portal['pago']['payphone']): ?>
                <div class="d-grid mb-3">
                    <button type="button" class="btn btn-primary btn-lg fw-semibold" id="btnPagarPayphone">
                        <i class="bi bi-credit-card-2-front me-2"></i>Pagar con tarjeta
                    </button>
                    <div class="text-center small text-muted mt-1">Pago seguro con Payphone. Tu factura se genera al instante.</div>
                </div>
            <?php endif; ?>
            <?php if ($portal['pago']['transferencia']): ?>
                <div class="border rounded-3 p-3">
                    <div class="fw-semibold small mb-2"><i class="bi bi-bank me-1"></i>Pagar por transferencia o depósito</div>
                    <?php if ($portal['pago']['datos'] !== ''): ?>
                        <div class="small bg-light rounded-2 p-2 mb-2"><?= nl2br($e($portal['pago']['datos'])) ?></div>
                    <?php endif; ?>
                    <div class="adj-box" id="boxComprobante" style="min-height:90px;">
                        <input type="file" accept="image/*,application/pdf">
                        <div class="adj-icon bi bi-receipt"></div>
                        <div class="fw-medium small mt-1">Sube tu comprobante de pago</div>
                        <div class="adj-name">Foto o PDF · máx. 5 MB</div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($firma && in_array($estado, ['por_registrar', 'en_proceso', 'aprobada', 'emitida'], true)): ?>
<!-- ═══ PASO 4: TRÁMITE ═══ -->
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-3 small">
        <p class="section-title"><i class="bi bi-patch-check me-1"></i>Tu trámite</p>
        <?php if (!empty($f['factura_secuencial'])): ?>
            <div class="mb-2"><i class="bi bi-receipt me-1 text-success"></i>Factura <b><?= $e($f['factura_establecimiento'] . '-' . $f['factura_punto_emision'] . '-' . $f['factura_secuencial']) ?></b></div>
        <?php endif; ?>
        <?php if (empty($f['correo_confirmado_at'])): ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-envelope-exclamation me-1"></i>Te enviamos la factura a <b><?= $e($f['correo']) ?></b>.
                Abre ese correo y pulsa <b>«Confirmo que recibí mi factura»</b>: ahí mismo te llegará la activación de tu firma.
                Revisa también la carpeta de spam.
            </div>
        <?php elseif ($estado === 'emitida'): ?>
            <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>Tu firma fue emitida<?= !empty($f['fecha_caducidad']) ? ' y está vigente hasta el <b>' . date('d-m-Y', strtotime($f['fecha_caducidad'])) . '</b>' : '' ?>.</div>
        <?php else: ?>
            <div class="alert alert-info mb-0"><i class="bi bi-envelope-check me-1"></i>Correo confirmado. Estamos gestionando tu firma con la entidad certificadora; recibirás un correo de Uanataca para activarla.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($portal['eventos'])): ?>
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-3">
        <p class="section-title"><i class="bi bi-clock-history me-1"></i>Historial</p>
        <ul class="timeline">
            <?php foreach (array_reverse($portal['eventos']) as $ev): ?>
                <li>
                    <div class="fw-medium"><?= $e($ev['titulo']) ?></div>
                    <?php if (!empty($ev['detalle'])): ?><div class="text-muted"><?= nl2br($e($ev['detalle'])) ?></div><?php endif; ?>
                    <div class="text-muted" style="font-size:.72rem;"><?= date('d-m-Y H:i:s', strtotime($ev['created_at'])) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<p class="text-center small text-muted">Guarda este enlace: con él puedes volver a ver el estado de tu solicitud.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';
    const base     = <?= json_encode($base) ?>;
    const urlBase  = <?= json_encode($urlBase) ?>;
    const urlGuardar = <?= json_encode($urlGuardar) ?>;
    const token    = <?= json_encode((string) ($sol['token'] ?? '')) ?>;
    const codigo   = <?= json_encode((string) ($sol['enlace_codigo'] ?? '')) ?>;
    const codCiudadIni = <?= json_encode((string) ($f['cod_ciudad'] ?? '')) ?>;
    const MAX_BYTES = 5 * 1024 * 1024;

    function aviso(msg, tipo = 'danger') {
        const box = document.getElementById('avisoFlotante');
        box.innerHTML = `<div class="alert alert-${tipo} shadow small mb-0"></div>`;
        box.firstChild.textContent = msg;
        clearTimeout(aviso._t);
        aviso._t = setTimeout(() => { box.innerHTML = ''; }, tipo === 'danger' ? 9000 : 4000);
    }

    async function enviar(url, fd) {
        const resp = await fetch(url, { method: 'POST', body: fd });
        let data;
        try { data = await resp.json(); } catch { data = { ok: false, error: 'Error de conexión. Intente nuevamente.' }; }
        if (!data.ok) throw new Error(data.error || 'No se pudo completar la acción.');
        return data;
    }

    function ocupado(btn, si, texto) {
        if (!btn) return;
        if (si) { btn.dataset.html = btn.innerHTML; btn.disabled = true; btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${texto}`; }
        else { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; }
    }

    // ── Paso 1: datos ─────────────────────────────────────────
    const form = document.getElementById('formDatos');
    if (form) {
        const ayudas = {
            natural: 'Para uso personal o si tienes RUC como persona natural.',
            juridica: 'Eres el representante legal de la empresa (consta en el nombramiento).',
            miembro: 'Trabajas en la empresa y el representante legal te autoriza a firmar por ella.',
        };
        const toggleJuridica = () => {
            const tipo = form.querySelector('[name="tipo_persona"]:checked')?.value || 'natural';
            document.getElementById('bloqueJuridica').classList.toggle('d-none', tipo === 'natural');
            document.querySelectorAll('.solo-miembro').forEach(el => el.classList.toggle('d-none', tipo !== 'miembro'));
            document.getElementById('ayudaTipoPersona').textContent = ayudas[tipo] || '';
        };
        form.querySelectorAll('[name="tipo_persona"]').forEach(r => r.addEventListener('change', toggleJuridica));
        toggleJuridica();

        document.getElementById('facturarOtro')?.addEventListener('change', function () {
            document.getElementById('bloqueFacturacion').classList.toggle('d-none', !this.checked);
            document.getElementById('facturacion_mismos_datos').value = this.checked ? '0' : '1';
        });

        async function cargarCiudades(codProv, sel) {
            const s = document.getElementById('cod_ciudad');
            if (!codProv) { s.innerHTML = '<option value="">-- Seleccione provincia --</option>'; return; }
            s.innerHTML = '<option value="">Cargando...</option>';
            try {
                const r = await fetch(`${base}/solicitud-firma/ciudades?cod_prov=${encodeURIComponent(codProv)}`);
                const data = await r.json();
                s.innerHTML = '<option value="">-- Seleccione --</option>';
                data.forEach(c => { const o = document.createElement('option'); o.value = c.codigo; o.textContent = c.nombre; s.appendChild(o); });
                if (sel) s.value = sel;
            } catch { s.innerHTML = '<option value="">Error al cargar</option>'; }
        }
        const prov = document.getElementById('cod_prov');
        prov.addEventListener('change', () => cargarCiudades(prov.value, ''));
        if (prov.value) cargarCiudades(prov.value, codCiudadIni);

        // ── Consulta al SRI ───────────────────────────────────
        // El servicio devuelve el nombre completo en "nombre" ("PEREZ ANDRADE JUAN CARLOS"):
        // los dos primeros términos son los apellidos y el resto los nombres, igual que en la
        // ficha interna (splitNombreApellido de firmas_electronicas_modal.js).
        async function consultarSri(id) {
            const q = new URLSearchParams({ id, token, codigo });
            return (await fetch(`${base}/solicitud-firma/sri?${q}`)).json();
        }
        const valor = idCampo => document.getElementById(idCampo)?.value.trim() || '';
        const poner = (idCampo, v, pisar) => {
            const el = document.getElementById(idCampo);
            if (el && v && (pisar || !el.value.trim())) el.value = v;
        };

        let ultimaSri = '';
        async function buscarTitularSri(manual) {
            const id = valor('numero_identificacion').replace(/\D/g, '');
            if (!/^\d{10}(\d{3})?$/.test(id)) {
                if (manual) aviso('Escriba una cédula (10 dígitos) o RUC (13 dígitos).');
                return;
            }
            if (!manual && id === ultimaSri) return;
            ultimaSri = id;
            const icon = document.getElementById('sriIcono'), loader = document.getElementById('sriLoader');
            icon.classList.add('d-none'); loader.classList.remove('d-none');
            try {
                const data = await consultarSri(id);
                const d = data.data || {};
                if (data.ok && d.nombre) {
                    const partes = d.nombre.trim().split(/\s+/).filter(Boolean);
                    const ape = partes.length >= 3 ? partes.slice(0, 2) : partes.slice(0, 1);
                    const nom = partes.slice(ape.length);
                    // Con el botón se reemplaza lo escrito; al teclear solo se llenan los campos vacíos.
                    poner('apellido_paterno', ape[0] || '', manual);
                    poner('apellido_materno', ape[1] || '', manual);
                    poner('nombres', nom.join(' '), manual);
                    if (d.direccion) poner('direccion', d.direccion, false);
                    if (d.cod_prov && !valor('cod_prov')) {
                        document.getElementById('cod_prov').value = d.cod_prov;
                        cargarCiudades(d.cod_prov, d.cod_ciudad || '');
                    }
                    if (id.length === 10) document.getElementById('tipo_identificacion').value = 'cedula';
                    else if (id.length === 13) document.getElementById('tipo_identificacion').value = 'ruc';
                    aviso('Encontramos tus datos en el SRI. Revísalos antes de continuar.', 'success');
                } else if (manual || data.advertencia) {
                    aviso(data.advertencia || data.error || 'No se encontraron datos. Escríbalos a mano.', 'warning');
                }
            } catch { if (manual) aviso('No se pudo consultar. Escriba sus datos a mano.', 'warning'); }
            finally { icon.classList.remove('d-none'); loader.classList.add('d-none'); }
        }
        document.getElementById('btnBuscarSri')?.addEventListener('click', () => buscarTitularSri(true));
        document.getElementById('numero_identificacion')?.addEventListener('input', function () {
            const n = this.value.replace(/\D/g, '');
            if (n.length === 10 || n.length === 13) buscarTitularSri(false);
        });

        // Factura a otro nombre: nombre completo / razón social y dirección.
        let ultimaSriFact = '';
        async function buscarFacturacionSri(manual) {
            const id = valor('facturacion_num_id').replace(/\D/g, '');
            if (!/^\d{10}(\d{3})?$/.test(id)) {
                if (manual) aviso('Escriba una cédula (10 dígitos) o RUC (13 dígitos).');
                return;
            }
            if (!manual && id === ultimaSriFact) return;
            ultimaSriFact = id;
            const icon = document.getElementById('sriFactIcono'), loader = document.getElementById('sriFactLoader');
            icon.classList.add('d-none'); loader.classList.remove('d-none');
            try {
                const data = await consultarSri(id);
                const d = data.data || {};
                if (data.ok && d.nombre) {
                    poner('facturacion_nombres', d.nombre, manual);
                    if (d.direccion) poner('facturacion_direccion', d.direccion, manual);
                    document.getElementById('facturacion_tipo_id').value = id.length === 13 ? 'ruc' : 'cedula';
                    aviso('Encontramos los datos para la factura en el SRI.', 'success');
                } else if (manual || data.advertencia) {
                    aviso(data.advertencia || data.error || 'No se encontraron datos. Escríbalos a mano.', 'warning');
                }
            } catch { if (manual) aviso('No se pudo consultar. Escriba los datos a mano.', 'warning'); }
            finally { icon.classList.remove('d-none'); loader.classList.add('d-none'); }
        }
        document.getElementById('btnBuscarSriFact')?.addEventListener('click', () => buscarFacturacionSri(true));
        document.getElementById('facturacion_num_id')?.addEventListener('input', function () {
            const n = this.value.replace(/\D/g, '');
            if (n.length === 10 || n.length === 13) buscarFacturacionSri(false);
        });

        // RUC de la empresa (representante legal / miembro): completa la razón social.
        let ultimoRucEmp = '';
        document.getElementById('ruc_empresa')?.addEventListener('input', async function () {
            const ruc = this.value.replace(/\D/g, '');
            if (ruc.length !== 13 || ruc === ultimoRucEmp) return;
            ultimoRucEmp = ruc;
            try {
                const data = await consultarSri(ruc);
                if (data.ok && data.data?.nombre) poner('nombre_empresa', data.data.nombre, false);
            } catch { /* se escribe a mano */ }
        });

        let guardando = false;
        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            if (guardando) return;
            guardando = true;
            const btn = document.getElementById('btnGuardarDatos');
            ocupado(btn, true, 'Guardando...');
            try {
                const data = await enviar(urlGuardar, new FormData(form));
                if (data.url) { window.location.href = data.url; return; }
                window.location.reload();
            } catch (err) {
                aviso(err.message);
                ocupado(btn, false);
                guardando = false;
            }
        });
    }

    // ── Paso 2: documentos (se suben uno por uno) ────────────
    document.querySelectorAll('.adj-box[data-tipo]').forEach(box => {
        box.querySelector('input[type=file]').addEventListener('change', async function () {
            const file = this.files[0];
            if (!file) return;
            if (file.size > MAX_BYTES) { aviso('El archivo supera 5 MB.'); this.value = ''; return; }
            const fd = new FormData();
            fd.append('tipo', box.dataset.tipo);
            fd.append('archivo', file);
            box.classList.add('cargando');
            box.querySelector('.adj-name').textContent = 'Subiendo...';
            try {
                const data = await enviar(`${urlBase}/adjunto`, fd);
                box.classList.add('ok');
                box.querySelector('.adj-icon').className = 'adj-icon bi bi-check-circle-fill';
                box.querySelector('.adj-name').textContent = data.adjunto.nombre_original + ' · cambiar';
            } catch (err) {
                aviso(err.message);
                box.querySelector('.adj-name').textContent = box.classList.contains('ok') ? 'Ya subido · cambiar' : 'Toca para subir';
            } finally {
                box.classList.remove('cargando');
                this.value = '';
            }
        });
    });

    document.getElementById('btnEnviarDocs')?.addEventListener('click', async function () {
        if (this.disabled) return;
        ocupado(this, true, 'Enviando...');
        try {
            await enviar(`${urlBase}/documentos`, new FormData());
            window.location.reload();
        } catch (err) { aviso(err.message); ocupado(this, false); }
    });

    // ── Paso 3: pago ──────────────────────────────────────────
    document.getElementById('btnPagarPayphone')?.addEventListener('click', async function () {
        if (this.disabled) return;
        ocupado(this, true, 'Preparando el pago...');
        try {
            const data = await enviar(`${urlBase}/pagar`, new FormData());
            window.location.href = data.url;
        } catch (err) { aviso(err.message); ocupado(this, false); }
    });

    const boxComp = document.getElementById('boxComprobante');
    boxComp?.querySelector('input[type=file]').addEventListener('change', async function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > MAX_BYTES) { aviso('El archivo supera 5 MB.'); this.value = ''; return; }
        const fd = new FormData();
        fd.append('archivo', file);
        boxComp.classList.add('cargando');
        boxComp.querySelector('.adj-name').textContent = 'Subiendo...';
        try {
            const data = await enviar(`${urlBase}/comprobante`, fd);
            aviso(data.msg, 'success');
            setTimeout(() => window.location.reload(), 1200);
        } catch (err) {
            aviso(err.message);
            boxComp.classList.remove('cargando');
            boxComp.querySelector('.adj-name').textContent = 'Foto o PDF · máx. 5 MB';
            this.value = '';
        }
    });
})();
</script>
</body>
</html>
