<?php
/**
 * Portal público de representantes (sin login). Vista standalone: arma su propio
 * <head>. No lleva partials/csrf.php: el controlador es público y Application lo
 * exime de CSRF (la autorización es el código enviado al correo).
 *
 * $vista = identificar | pedir_correo | codigo | formulario | resultado
 */
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$portal  = $portal ?? null;
$empresa = (string) ($portal['empresa_nombre'] ?? '');
$token   = (string) ($token ?? '');
$base    = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$accion  = fn(string $a) => $base . '/portal-alumnos/' . rawurlencode($token) . '/' . $a;
$error   = (string) ($error ?? '');

// Formulario: si vuelve con error, se repinta con lo que había escrito.
$post = $post ?? null;
$cli  = is_array($post) ? (array) ($post['cliente'] ?? []) : (array) ($cliente ?? []);
if (!is_array($post) && empty($cliente)) { $cli['email'] = $correo ?? ''; }
$listaAlumnos = is_array($post) ? array_values((array) ($post['alumnos'] ?? [])) : ($alumnos ?? []);

$sexos   = ['' => '— Seleccione —', 'M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'];
$sangres = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$relaciones = ['' => '—', 'padre' => 'Padre', 'madre' => 'Madre', 'tutor' => 'Tutor', 'abuelo' => 'Abuelo/a', 'hermano' => 'Hermano/a', 'tio' => 'Tío/a', 'otro' => 'Otro'];

$opts = function (array $o, $sel) use ($e) {
    $h = '';
    foreach ($o as $v => $l) { $h .= '<option value="' . $e($v) . '"' . ((string) $sel === (string) $v ? ' selected' : '') . '>' . $e($l) . '</option>'; }
    return $h;
};

// Campus y nivel: solo se eligen al registrar un alumno NUEVO (su matrícula).
$optCampus  = ['' => '— Seleccione —'];
foreach (($campus ?? []) as $c) { $optCampus[$c['id']] = $c['nombre']; }
$optNiveles = ['' => '— Seleccione —'];
foreach (($niveles ?? []) as $n) { $optNiveles[$n['id']] = $n['nombre']; }

/** Bloque de un alumno (existente o nuevo). $i = índice en el POST. */
$bloqueAlumno = function ($i, array $a) use ($e, $opts, $sexos, $sangres, $relaciones, $optCampus, $optNiveles) {
    $id = (int) ($a['id'] ?? 0);
    $titulo = $id > 0 ? trim(($a['apellidos'] ?? '') . ' ' . ($a['nombres'] ?? '')) : 'Nuevo alumno';
    $reps = array_values((array) ($a['representantes'] ?? []));
    ob_start(); ?>
    <details class="alumno" <?= $id === 0 ? 'open' : '' ?>>
        <summary><span class="ico"><?= $id > 0 ? '🎒' : '➕' ?></span> <?= $e($titulo !== '' ? $titulo : 'Alumno') ?></summary>
        <div class="alumno-cuerpo">
            <input type="hidden" name="alumnos[<?= $i ?>][id]" value="<?= $id ?>">
            <?php if ($id === 0 && (count($optCampus) > 1 || count($optNiveles) > 1)): ?>
            <div class="fila">
                <?php if (count($optCampus) > 1): ?>
                <div class="campo"><label>Campus</label><select name="alumnos[<?= $i ?>][id_campus]"><?= $opts($optCampus, $a['id_campus'] ?? '') ?></select></div>
                <?php endif; ?>
                <?php if (count($optNiveles) > 1): ?>
                <div class="campo"><label>Nivel / Curso</label><select name="alumnos[<?= $i ?>][id_nivel]"><?= $opts($optNiveles, $a['id_nivel'] ?? '') ?></select></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="fila">
                <div class="campo"><label>Nombres *</label><input type="text" name="alumnos[<?= $i ?>][nombres]" maxlength="150" value="<?= $e($a['nombres'] ?? '') ?>"></div>
                <div class="campo"><label>Apellidos *</label><input type="text" name="alumnos[<?= $i ?>][apellidos]" maxlength="150" value="<?= $e($a['apellidos'] ?? '') ?>"></div>
            </div>
            <div class="fila">
                <div class="campo"><label>Tipo de identificación</label>
                    <select name="alumnos[<?= $i ?>][tipo_identificacion]"><?= $opts(['' => '—', '05' => 'Cédula', '06' => 'Pasaporte'], $a['tipo_identificacion'] ?? '') ?></select></div>
                <div class="campo"><label>Número</label><input type="text" name="alumnos[<?= $i ?>][numero_identificacion]" maxlength="20" inputmode="text" value="<?= $e($a['numero_identificacion'] ?? '') ?>"></div>
            </div>
            <div class="fila">
                <div class="campo"><label>Fecha de nacimiento</label><input type="date" name="alumnos[<?= $i ?>][fecha_nacimiento]" max="<?= date('Y-m-d') ?>" value="<?= $e($a['fecha_nacimiento'] ?? '') ?>"></div>
                <div class="campo"><label>Sexo</label><select name="alumnos[<?= $i ?>][sexo]"><?= $opts($sexos, $a['sexo'] ?? '') ?></select></div>
                <div class="campo"><label>Nacionalidad</label><input type="text" name="alumnos[<?= $i ?>][nacionalidad]" maxlength="80" value="<?= $e($a['nacionalidad'] ?? '') ?>"></div>
            </div>
            <h3>Salud y emergencia</h3>
            <div class="fila">
                <div class="campo corto"><label>Tipo de sangre</label><select name="alumnos[<?= $i ?>][tipo_sangre]"><?= $opts(array_combine($sangres, array_map(fn($s) => $s === '' ? '—' : $s, $sangres)), $a['tipo_sangre'] ?? '') ?></select></div>
                <div class="campo"><label>Alergias / condiciones médicas</label><input type="text" name="alumnos[<?= $i ?>][alergias_condiciones]" maxlength="500" value="<?= $e($a['alergias_condiciones'] ?? '') ?>"></div>
            </div>
            <div class="fila">
                <div class="campo"><label>Contacto de emergencia</label><input type="text" name="alumnos[<?= $i ?>][contacto_emergencia_nombre]" maxlength="150" value="<?= $e($a['contacto_emergencia_nombre'] ?? '') ?>"></div>
                <div class="campo"><label>Teléfono de emergencia</label><input type="tel" name="alumnos[<?= $i ?>][contacto_emergencia_telefono]" maxlength="30" value="<?= $e($a['contacto_emergencia_telefono'] ?? '') ?>"></div>
            </div>
            <h3>Representantes y personas autorizadas a retirar</h3>
            <div class="reps" data-i="<?= $i ?>" data-n="<?= count($reps) ?>">
                <?php foreach ($reps as $j => $r):
                    $retira = in_array(($r['puede_retirar'] ?? false), [true, 't', 1, '1', 'on'], true); ?>
                    <div class="rep">
                        <input type="text" name="alumnos[<?= $i ?>][representantes][<?= $j ?>][nombres]" maxlength="200" placeholder="Nombres y apellidos" value="<?= $e($r['nombres'] ?? '') ?>">
                        <input type="text" name="alumnos[<?= $i ?>][representantes][<?= $j ?>][identificacion]" maxlength="20" placeholder="Cédula" value="<?= $e($r['identificacion'] ?? '') ?>">
                        <input type="tel" name="alumnos[<?= $i ?>][representantes][<?= $j ?>][telefono]" maxlength="30" placeholder="Teléfono" value="<?= $e($r['telefono'] ?? '') ?>">
                        <select name="alumnos[<?= $i ?>][representantes][<?= $j ?>][relacion]"><?= $opts($relaciones, $r['relacion'] ?? '') ?></select>
                        <label class="check"><input type="checkbox" name="alumnos[<?= $i ?>][representantes][<?= $j ?>][puede_retirar]" value="1" <?= $retira ? 'checked' : '' ?>> Puede retirar</label>
                        <button type="button" class="quitar" onclick="this.closest('.rep').remove()" title="Quitar">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="enlace" onclick="agregarRep(this)">+ Agregar persona</button>
        </div>
    </details>
    <?php return ob_get_clean();
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Datos de mis hijos<?= $empresa !== '' ? ' · ' . $e($empresa) : '' ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px 10px; background: #f4f6f9; font-family: Arial, Helvetica, sans-serif; color: #222; }
        .caja { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.08); overflow: hidden; }
        .cab { background: #0d6efd; color: #fff; padding: 18px 22px; }
        .cab h1 { margin: 0; font-size: 19px; }
        .cab p { margin: 4px 0 0; font-size: 13px; opacity: .9; }
        .cuerpo { padding: 22px; }
        h2 { font-size: 16px; margin: 4px 0 12px; color: #0d6efd; }
        h3 { font-size: 13px; margin: 16px 0 8px; color: #555; text-transform: uppercase; letter-spacing: .3px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #444; margin: 0 0 5px; }
        .campo { margin-bottom: 12px; flex: 1 1 180px; }
        .campo.corto { flex: 0 1 130px; }
        .fila { display: flex; gap: 12px; flex-wrap: wrap; }
        input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select {
            width: 100%; padding: 9px 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 15px; font-family: inherit; background: #fff; color: #222;
        }
        input:focus, select:focus { outline: none; border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.12); }
        input[readonly] { background: #f1f3f5; color: #555; }
        .codigo { font-size: 26px; letter-spacing: 8px; text-align: center; font-weight: bold; }
        .ayuda { font-size: 12px; color: #6c757d; margin: 4px 0 0; }
        .aviso { padding: 11px 14px; border-radius: 8px; font-size: 13px; line-height: 1.5; margin-bottom: 16px; }
        .aviso.error { background: #fdecea; border-left: 4px solid #dc3545; color: #842029; }
        .aviso.info { background: #fff8e1; border-left: 4px solid #ffc107; color: #664d03; }
        .aviso.ok { background: #e8f6ed; border-left: 4px solid #198754; color: #0f5132; }
        button.principal { width: 100%; padding: 13px; background: #0d6efd; color: #fff; border: 0; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 8px; }
        button.principal:hover { background: #0b5ed7; }
        button.principal[disabled] { opacity: .6; cursor: wait; }
        .enlace { background: none; border: 0; color: #0d6efd; font-weight: bold; cursor: pointer; padding: 6px 0; font-size: 14px; }
        details.alumno { border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 12px; }
        details.alumno > summary { padding: 12px 14px; cursor: pointer; font-weight: bold; background: #f8f9fa; border-radius: 8px; list-style: none; }
        details.alumno[open] > summary { border-bottom: 1px solid #dee2e6; border-radius: 8px 8px 0 0; }
        .alumno-cuerpo { padding: 14px; }
        .rep { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto auto; gap: 6px; align-items: center; margin-bottom: 8px; }
        .rep input, .rep select { padding: 7px 8px; font-size: 14px; }
        .rep .check { display: flex; gap: 4px; align-items: center; font-weight: normal; font-size: 12px; margin: 0; white-space: nowrap; }
        .quitar { background: none; border: 0; color: #dc3545; font-size: 16px; cursor: pointer; }
        @media (max-width: 600px) { .rep { grid-template-columns: 1fr 1fr; } .rep input:first-child { grid-column: 1 / -1; } }
        .pie { font-size: 11px; color: #999; text-align: center; margin-top: 14px; }
    </style>
</head>
<body>
<div class="caja">
    <div class="cab">
        <h1>Datos de mis hijos</h1>
        <p><?= $empresa !== '' ? $e($empresa) . ' · ' : '' ?>Portal para representantes</p>
    </div>
    <div class="cuerpo">
        <?php if ($error !== ''): ?><div class="aviso error"><?= $e($error) ?></div><?php endif; ?>

        <?php if ($vista === 'resultado'): ?>
            <div class="aviso <?= ($tipo ?? '') === 'ok' ? 'ok' : 'info' ?>"><?= $e($mensaje ?? '') ?></div>
            <?php if ($portal && $token !== ''): ?><a class="enlace" href="<?= $e($base . '/portal-alumnos/' . rawurlencode($token)) ?>">Volver al inicio</a><?php endif; ?>

        <?php elseif ($vista === 'identificar'): ?>
            <p style="font-size:14px;line-height:1.6;margin-top:0;">Actualice los datos de facturación y la información de sus hijos, o registre un alumno nuevo.
                Escriba <b>su</b> cédula o RUC (la del representante a quien se le factura).</p>
            <form method="post" action="<?= $e($accion('codigo')) ?>" onsubmit="this.querySelector('button').disabled=true">
                <div class="campo"><label>Cédula o RUC del representante</label>
                    <input type="text" name="identificacion" inputmode="numeric" maxlength="20" required autofocus value="<?= $e($identificacion ?? '') ?>"></div>
                <button type="submit" class="principal">Continuar</button>
            </form>

        <?php elseif ($vista === 'pedir_correo'): ?>
            <div class="aviso info">No encontramos un representante con la identificación <b><?= $e($identificacion ?? '') ?></b>. Puede registrarse: escriba su correo y le enviaremos un código de verificación.</div>
            <form method="post" action="<?= $e($accion('codigo')) ?>" onsubmit="this.querySelector('button').disabled=true">
                <input type="hidden" name="identificacion" value="<?= $e($identificacion ?? '') ?>">
                <div class="campo"><label>Correo electrónico</label>
                    <input type="email" name="correo" maxlength="150" required autofocus value="<?= $e($correo_escrito ?? '') ?>"></div>
                <button type="submit" class="principal">Enviar código</button>
            </form>
            <a class="enlace" href="<?= $e($base . '/portal-alumnos/' . rawurlencode($token)) ?>">← Usar otra identificación</a>

        <?php elseif ($vista === 'codigo'): ?>
            <div class="aviso ok">Enviamos un código de 6 dígitos a <b><?= $e($correo ?? '') ?></b>. Revise también la carpeta de correo no deseado.</div>
            <form method="post" action="<?= $e($accion('verificar')) ?>" onsubmit="this.querySelector('button').disabled=true">
                <input type="hidden" name="id_codigo" value="<?= (int) ($id_codigo ?? 0) ?>">
                <input type="hidden" name="correo_mascara" value="<?= $e($correo ?? '') ?>">
                <div class="campo"><label>Código de verificación</label>
                    <input type="text" name="codigo" class="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
                <p class="ayuda">Vence en 10 minutos y sirve para un solo envío.</p>
                <button type="submit" class="principal">Verificar</button>
            </form>
            <a class="enlace" href="<?= $e($base . '/portal-alumnos/' . rawurlencode($token)) ?>">← Pedir otro código</a>

        <?php elseif ($vista === 'formulario'): ?>
            <form method="post" action="<?= $e($accion('guardar')) ?>" id="formPortal" onsubmit="return enviarPortal(this)">
                <input type="hidden" name="sesion" value="<?= $e($sesion ?? '') ?>">

                <h2>Datos para la factura</h2>
                <div class="fila">
                    <div class="campo"><label>Cédula / RUC</label><input type="text" value="<?= $e($identificacion ?? '') ?>" readonly></div>
                    <div class="campo" style="flex-basis:320px;"><label>Nombres completos o razón social *</label><input type="text" name="cliente[nombre]" maxlength="300" required value="<?= $e($cli['nombre'] ?? '') ?>"></div>
                </div>
                <div class="fila">
                    <div class="campo"><label>Correo para la factura *</label><input type="email" name="cliente[email]" maxlength="150" required value="<?= $e($cli['email'] ?? '') ?>"></div>
                    <div class="campo"><label>Teléfono</label><input type="tel" name="cliente[telefono]" maxlength="50" value="<?= $e($cli['telefono'] ?? '') ?>"></div>
                </div>
                <div class="campo"><label>Dirección</label><input type="text" name="cliente[direccion]" maxlength="300" value="<?= $e($cli['direccion'] ?? '') ?>"></div>

                <h2 style="margin-top:18px;">Mis hijos</h2>
                <div id="alumnos">
                    <?php foreach ($listaAlumnos as $i => $a) { echo $bloqueAlumno($i, (array) $a); } ?>
                </div>
                <?php if (!$listaAlumnos): ?><p class="ayuda" id="sinAlumnos">Aún no tiene alumnos registrados: agregue a su hijo o hija.</p><?php endif; ?>
                <button type="button" class="enlace" onclick="agregarAlumno()">+ Agregar un alumno</button>

                <button type="submit" class="principal">Guardar mis datos</button>
                <p class="ayuda">Se guarda una sola vez. Para hacer otro cambio después, vuelva a ingresar y pida un código nuevo.</p>
            </form>
            <template id="tplAlumno"><?= $bloqueAlumno('__I__', ['id' => 0, 'representantes' => []]) ?></template>
            <template id="tplRep">
                <div class="rep">
                    <input type="text" name="alumnos[__I__][representantes][__J__][nombres]" maxlength="200" placeholder="Nombres y apellidos">
                    <input type="text" name="alumnos[__I__][representantes][__J__][identificacion]" maxlength="20" placeholder="Cédula">
                    <input type="tel" name="alumnos[__I__][representantes][__J__][telefono]" maxlength="30" placeholder="Teléfono">
                    <select name="alumnos[__I__][representantes][__J__][relacion]"><?= $opts($relaciones, '') ?></select>
                    <label class="check"><input type="checkbox" name="alumnos[__I__][representantes][__J__][puede_retirar]" value="1" checked> Puede retirar</label>
                    <button type="button" class="quitar" onclick="this.closest('.rep').remove()" title="Quitar">✕</button>
                </div>
            </template>
            <script>
                let siguienteAlumno = <?= count($listaAlumnos) ?>;
                function agregarAlumno() {
                    const html = document.getElementById('tplAlumno').innerHTML.replaceAll('__I__', String(siguienteAlumno++));
                    document.getElementById('alumnos').insertAdjacentHTML('beforeend', html);
                    document.getElementById('sinAlumnos')?.remove();
                }
                function agregarRep(btn) {
                    const cont = btn.previousElementSibling;
                    const j = Number(cont.dataset.n || 0);
                    cont.dataset.n = String(j + 1);
                    cont.insertAdjacentHTML('beforeend', document.getElementById('tplRep').innerHTML
                        .replaceAll('__I__', cont.dataset.i).replaceAll('__J__', String(j)));
                }
                function enviarPortal(f) {
                    f.querySelector('button.principal').disabled = true;
                    return true;
                }
                <?php if (!$listaAlumnos): ?>agregarAlumno();<?php endif; ?>
            </script>
        <?php endif; ?>
    </div>
</div>
<p class="pie">Sus datos se usan solo para la facturación y la atención de sus hijos en la institución.</p>
</body>
</html>
