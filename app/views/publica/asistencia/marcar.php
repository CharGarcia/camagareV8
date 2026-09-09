<?php
/** @var string $base @var string $tokenPunto @var string $puntoNombre @var bool $exigeGps @var bool $valido */
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0d6efd">
    <title>Marcar asistencia</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #10131a; color: #fff; min-height: 100dvh; display: flex; flex-direction: column; }
        header { padding: 16px; text-align: center; background: #0d6efd; }
        header .punto { font-weight: 700; font-size: 1.1rem; }
        header .sub { font-size: .8rem; opacity: .85; }
        .wrap { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 18px; gap: 14px; }
        .cam { position: relative; width: 100%; max-width: 340px; aspect-ratio: 3/4; background: #000; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.4); }
        video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        .who { font-size: 1rem; }
        .who b { color: #66b0ff; }
        .btns { display: flex; gap: 12px; width: 100%; max-width: 340px; }
        .btn { flex: 1; border: 0; border-radius: 14px; padding: 18px 8px; font-size: 1.05rem; font-weight: 700; color: #fff; box-shadow: 0 6px 16px rgba(0,0,0,.3); }
        .btn:disabled { opacity: .5; }
        .btn-in { background: #1a9c53; }
        .btn-out { background: #d13438; }
        .msg { min-height: 22px; font-size: .85rem; text-align: center; color: #ffd; }
        .panel { background: #fff; color: #222; border-radius: 16px; padding: 22px; max-width: 340px; width: 100%; text-align: center; }
        .panel h2 { margin: 0 0 8px; font-size: 1.1rem; }
        .panel p { color: #555; font-size: .9rem; }
        .panel input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 10px; margin: 10px 0; font-size: .95rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .panel button { width: 100%; border: 0; background: #0d6efd; color: #fff; padding: 13px; border-radius: 10px; font-weight: 700; }
        .panel button:disabled { opacity: .6; }
        .panel .aviso { min-height: 20px; font-size: .82rem; margin-top: 10px; color: #666; }
        .panel .aviso.error { color: #d13438; font-weight: 600; }
        /* Overlay resultado */
        .overlay { position: fixed; inset: 0; background: rgba(10,13,20,.96); display: none; flex-direction: column; align-items: center; justify-content: center; padding: 30px; text-align: center; z-index: 50; }
        .overlay .big { width: 96px; height: 96px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 52px; margin-bottom: 18px; }
        .overlay.ok .big { background: #1a9c53; }
        .overlay.warn .big { background: #e0a800; }
        .overlay.err .big { background: #d13438; }
        .overlay h2 { margin: 0 0 6px; }
        .overlay p { color: #cbd; }
        .ov-btns { display: flex; flex-direction: column; gap: 10px; margin-top: 22px; width: 100%; max-width: 320px; }
        .ov-btns button { border: 0; background: #0d6efd; color: #fff; padding: 14px 24px; border-radius: 12px; font-weight: 700; font-size: 1rem; }
        .ov-btns button.sec { background: transparent; color: #cbd; border: 1px solid #4a5164; font-weight: 600; font-size: .9rem; }
    </style>
</head>
<body>
<?php if (!$valido): ?>
    <div class="wrap">
        <div class="panel">
            <h2>QR no válido</h2>
            <p>Este punto de servicio no existe o está inactivo. Verifica con tu supervisor.</p>
        </div>
    </div>
<?php else: ?>
    <header>
        <div class="punto"><?= $h($puntoNombre) ?></div>
        <div class="sub">Marcación de asistencia</div>
    </header>

    <!-- Panel cuando no hay credencial en el dispositivo (o dejó de ser válida) -->
    <div class="wrap" id="panelNoIdent" style="display:none;">
        <div class="panel">
            <h2>Identifícate</h2>
            <p>Este teléfono aún no tiene tu credencial. Abre una vez tu <b>enlace personal</b> (o escanea tu QR personal), o pega aquí tu <b>código de empleado</b>:</p>
            <input type="text" id="inpToken" placeholder="EMP-..." autocapitalize="off" autocorrect="off" autocomplete="off" spellcheck="false" inputmode="text">
            <button type="button" id="btnGuardarToken" onclick="guardarToken()">Guardar credencial</button>
            <div class="aviso" id="identMsg"></div>
        </div>
    </div>

    <!-- Flujo de marcación -->
    <div class="wrap" id="panelMarca" style="display:none;">
        <div class="who">Marca: <b id="whoNombre"></b></div>
        <div class="cam"><video id="video" autoplay playsinline muted></video></div>
        <div class="msg" id="msg"></div>
        <div class="btns">
            <button class="btn btn-in" id="btnEntrada" onclick="marcar('entrada')">Entrada</button>
            <button class="btn btn-out" id="btnSalida" onclick="marcar('salida')">Salida</button>
        </div>
    </div>

    <div class="overlay" id="overlay">
        <div class="big" id="ovIco"></div>
        <h2 id="ovTitulo"></h2>
        <p id="ovTexto"></p>
        <div class="ov-btns" id="ovBtns"></div>
    </div>

    <canvas id="canvas" style="display:none;"></canvas>

    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
    <script src="<?= $h($base) ?>/js/modulos/face_asistencia.js"></script>
    <script>
    (function () {
        'use strict';
        const BASE = <?= json_encode($base) ?>;
        const TOKEN_PUNTO = <?= json_encode($tokenPunto) ?>;
        const EXIGE_GPS = <?= $exigeGps ? 'true' : 'false' ?>;
        // Código con el que el servidor avisa "la credencial de este teléfono ya no sirve"
        // (MarcacionService::ERR_CREDENCIAL).
        const ERR_CREDENCIAL = 4401;
        // Fallos seguidos de la verificación facial antes de ofrecer marcar igual
        // (esa marca queda SOSPECHOSA para que la revise el supervisor).
        const MAX_FALLOS_ROSTRO = 3;

        let empToken = '';
        let empNombre = '';
        let stream = null;
        let storedDesc = null;   // descriptor facial enrolado (si existe)
        let faceReady = false;   // modelos cargados
        let fallosRostro = 0;    // fallos seguidos de la verificación facial
        let ultimoMotivo = '';   // 'sin_rostro' | 'no_coincide'
        let ultimaConfianza = null;

        const $ = (id) => document.getElementById(id);
        const setMsg = (t) => { $('msg').textContent = t || ''; };
        const esperar = (ms) => new Promise(r => setTimeout(r, ms));

        async function init() {
            try {
                empToken = localStorage.getItem('casis_emp_token') || '';
                empNombre = localStorage.getItem('casis_emp_nombre') || '';
            } catch (e) {}
            if (!empToken) {
                $('panelNoIdent').style.display = 'flex';
                return;
            }
            $('panelMarca').style.display = 'flex';
            $('whoNombre').textContent = empNombre || 'empleado';
            iniciarCamara();
            // La credencial se comprueba ANTES de que el empleado tome la selfie: si fue
            // regenerada o revocada se avisa aquí, no al final cuando ya creía haber marcado.
            if (!(await validarCredencial())) return;
            prepararRostro();
        }

        /** ¿La credencial guardada en este teléfono sigue viva? De paso refresca el nombre. */
        async function validarCredencial() {
            try {
                const r = await fetch(BASE + '/asistencia/info-qr?e=' + encodeURIComponent(empToken));
                const j = await r.json();
                if (j && j.ok && !j.valido) {
                    credencialInvalida('La credencial de este teléfono ya no es válida: fue regenerada o revocada. Vuelve a escanear tu QR personal o pega tu código nuevamente.');
                    return false;
                }
                if (j && j.nombre) {
                    empNombre = j.nombre;
                    $('whoNombre').textContent = empNombre;
                    try { localStorage.setItem('casis_emp_nombre', empNombre); } catch (e) {}
                }
            } catch (e) {
                // Sin red no se puede validar: no se bloquea, ya lo dirá el registro.
            }
            return true;
        }

        /** Olvida la credencial del dispositivo y vuelve a pedir identificación. */
        function credencialInvalida(texto) {
            try {
                localStorage.removeItem('casis_emp_token');
                localStorage.removeItem('casis_emp_nombre');
            } catch (e) {}
            if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
            cerrarOverlay();
            $('panelMarca').style.display = 'none';
            $('panelNoIdent').style.display = 'flex';
            identMsg(texto, true);
        }

        // Trae el descriptor facial del empleado (si está enrolado) y carga los modelos.
        async function prepararRostro() {
            try {
                const fd = new FormData(); fd.append('tokenEmpleado', empToken);
                const r = await fetch(BASE + '/asistencia/descriptor', { method: 'POST', body: fd });
                const j = await r.json();
                if (!j.ok || !j.descriptor) return; // sin rostro enrolado → solo QR
                storedDesc = j.descriptor;
                setMsg('Preparando verificación facial...');
                await window.CASIS_FACE.loadModels();
                faceReady = true;
                setMsg('');
            } catch (e) {
                faceReady = false; // si falla, se marca solo con QR + GPS
                // El rostro sí está enrolado pero no se pudieron cargar los modelos (datos
                // lentos, CDN bloqueado): la marca saldría sin verificar y el empleado debe saberlo.
                setMsg(storedDesc ? 'Verificación facial no disponible: se registrará sin verificar el rostro.' : '');
            }
        }

        function identMsg(texto, esError) {
            const el = $('identMsg');
            el.textContent = texto || '';
            el.className = 'aviso' + (esError ? ' error' : '');
        }

        /**
         * Acepta lo que el empleado tenga a mano: el código pelado, el código escrito en
         * mayúsculas (el token real es hexadecimal en minúsculas y la comparación en base
         * de datos distingue mayúsculas) o el enlace personal completo pegado.
         */
        function normalizarToken(v) {
            v = (v || '').trim();
            const enlace = v.match(/[?&]e=([^&\s]+)/i);
            if (enlace) {
                try { v = decodeURIComponent(enlace[1]); } catch (e) { v = enlace[1]; }
            }
            v = v.trim().replace(/\s+/g, '');
            const hex = v.match(/^(?:EMP-)?([0-9a-fA-F]{32})$/);
            if (hex) v = 'EMP-' + hex[1].toLowerCase();
            return v;
        }

        window.guardarToken = async function () {
            const btn = $('btnGuardarToken');
            const t = normalizarToken($('inpToken').value);
            if (!t) { identMsg('Escribe o pega tu código personal.', true); return; }

            btn.disabled = true;
            identMsg('Validando...', false);
            try {
                const r = await fetch(BASE + '/asistencia/info-qr?e=' + encodeURIComponent(t));
                const j = await r.json();
                if (!j.ok || !j.valido) {
                    identMsg('Ese código no corresponde a una credencial activa. Revísalo o pide a tu empresa que te reenvíe tu QR personal.', true);
                    btn.disabled = false;
                    return;
                }
                let guardado = false;
                try {
                    localStorage.setItem('casis_emp_token', t);
                    localStorage.setItem('casis_emp_nombre', j.nombre || '');
                    guardado = localStorage.getItem('casis_emp_token') === t;
                } catch (e) {}
                if (!guardado) {
                    identMsg('Tu navegador está bloqueando el almacenamiento del sitio (¿ventana privada?). Ábrelo en una ventana normal para poder marcar.', true);
                    btn.disabled = false;
                    return;
                }
                identMsg('Credencial vinculada: ' + (j.nombre || ''), false);
                location.reload();
            } catch (e) {
                identMsg('No se pudo validar el código. Revisa tu señal e inténtalo de nuevo.', true);
                btn.disabled = false;
            }
        };

        async function iniciarCamara() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                $('video').srcObject = stream;
            } catch (e) {
                setMsg('Sin cámara: se marcará sin selfie.');
            }
        }

        function capturarSelfie() {
            const v = $('video');
            if (!v || !v.videoWidth) return '';
            const c = $('canvas');
            const w = 480, ratio = v.videoHeight / v.videoWidth;
            c.width = w; c.height = Math.round(w * ratio);
            const ctx = c.getContext('2d');
            ctx.drawImage(v, 0, 0, c.width, c.height);
            return c.toDataURL('image/jpeg', 0.7);
        }

        function obtenerGps() {
            return new Promise((resolve) => {
                if (!navigator.geolocation) return resolve(null);
                navigator.geolocation.getCurrentPosition(
                    (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
                    () => resolve(null),
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        }

        /**
         * Verificación facial 1:1 con varios intentos: la cámara del celular tarda en
         * enfocar y el primer fotograma casi nunca sirve para detectar el rostro.
         * Devuelve { ok, motivo, confianza }.
         */
        async function verificarRostro() {
            let live = null;
            for (let i = 0; i < 3 && !live; i++) {
                try {
                    live = await window.CASIS_FACE.descriptor($('video'));
                } catch (e) {
                    live = null;
                }
                if (!live) await esperar(350);
            }
            if (!live) return { ok: false, motivo: 'sin_rostro', confianza: null };

            const dist = window.CASIS_FACE.distancia(storedDesc, live);
            const conf = Math.max(0, Math.min(100, Math.round((1 - dist) * 100)));
            return { ok: dist <= window.CASIS_FACE.THRESHOLD, motivo: 'no_coincide', confianza: conf };
        }

        function overlay(tipo, titulo, texto, botones) {
            const ov = $('overlay');
            ov.className = 'overlay ' + tipo;
            $('ovIco').textContent = tipo === 'ok' ? '✓' : (tipo === 'warn' ? '!' : '✕');
            $('ovTitulo').textContent = titulo;
            $('ovTexto').textContent = texto || '';
            const cont = $('ovBtns');
            cont.innerHTML = '';
            const lista = (botones && botones.length) ? botones : [{ txt: 'Aceptar', fn: () => location.reload() }];
            lista.forEach(function (b) {
                const el = document.createElement('button');
                el.type = 'button';
                el.textContent = b.txt;
                if (b.clase) el.className = b.clase;
                el.onclick = b.fn;
                cont.appendChild(el);
            });
            ov.style.display = 'flex';
        }

        function cerrarOverlay() { $('overlay').style.display = 'none'; }

        function setBusy(b) {
            $('btnEntrada').disabled = b;
            $('btnSalida').disabled = b;
        }

        /**
         * @param tipo   'entrada' | 'salida'
         * @param forzar true = el empleado eligió marcar pese a que su rostro no se pudo
         *               verificar: la marca queda SOSPECHOSA para revisión del supervisor.
         */
        window.marcar = async function (tipo, forzar) {
            setBusy(true);
            setMsg('Obteniendo ubicación...');
            const gps = await obtenerGps();
            if (EXIGE_GPS && !gps) {
                setMsg('');
                setBusy(false);
                overlay('err', 'Ubicación requerida', 'Activa el GPS y permite la ubicación para marcar en este punto.', [
                    { txt: 'Intentar de nuevo', fn: cerrarOverlay }
                ]);
                return;
            }

            // Verificación facial 1:1 (si el empleado tiene rostro enrolado).
            let confianza = null, faceSusp = false, faceMotivo = '';
            if (storedDesc && faceReady) {
                if (forzar) {
                    faceSusp = true;
                    faceMotivo = ultimoMotivo || 'no_coincide';
                    confianza = ultimaConfianza;
                } else {
                    setMsg('Verificando rostro...');
                    const r = await verificarRostro();
                    ultimaConfianza = r.confianza;
                    if (!r.ok) {
                        fallosRostro++;
                        ultimoMotivo = r.motivo;
                        setMsg('');
                        setBusy(false);

                        const titulo = (r.motivo === 'sin_rostro')
                            ? 'No se reconoció el rostro'
                            : 'No pudimos confirmar que eres tú';
                        let texto = (r.motivo === 'sin_rostro')
                            ? 'La cámara no detectó ninguna cara. Colócate de frente, con el rostro completo y buena luz, e intenta de nuevo.'
                            : 'El rostro no coincide con el registrado. Quítate la gorra o los lentes, busca mejor luz e intenta de nuevo.';

                        const botones = [{ txt: 'Intentar de nuevo', fn: cerrarOverlay }];
                        if (fallosRostro >= MAX_FALLOS_ROSTRO) {
                            texto += ' Si el problema continúa puedes marcar de todas formas: la marca quedará registrada como SOSPECHOSA para que la revise tu supervisor.';
                            botones.push({
                                txt: 'Marcar de todas formas',
                                clase: 'sec',
                                fn: function () { cerrarOverlay(); window.marcar(tipo, true); }
                            });
                        }
                        overlay('warn', titulo, texto, botones);
                        return;
                    }
                    confianza = r.confianza;
                }
            }

            setMsg('Registrando...');
            const selfie = capturarSelfie();

            const fd = new FormData();
            fd.append('tokenEmpleado', empToken);
            fd.append('tokenPunto', TOKEN_PUNTO);
            fd.append('tipo', tipo);
            if (gps) { fd.append('latitud', gps.lat); fd.append('longitud', gps.lng); }
            if (selfie) fd.append('selfie', selfie);
            if (confianza !== null && confianza !== undefined) fd.append('confianza', confianza);
            if (faceSusp) { fd.append('face_sospechosa', '1'); fd.append('face_motivo', faceMotivo); }

            try {
                const r = await fetch(BASE + '/asistencia/registrar', { method: 'POST', body: fd });
                const j = await r.json();
                setMsg('');
                if (!j.ok) {
                    setBusy(false);
                    if (j.codigo === ERR_CREDENCIAL) {
                        credencialInvalida(j.error);
                        return;
                    }
                    overlay('err', 'No se registró', j.error || 'Error desconocido.', [
                        { txt: 'Intentar de nuevo', fn: cerrarOverlay }
                    ]);
                    return;
                }
                fallosRostro = 0;
                if (j.estado === 'sospechosa') {
                    overlay('warn', (j.tipo === 'entrada' ? 'Entrada' : 'Salida') + ' registrada', 'Quedó marcada como SOSPECHOSA: ' + (j.observacion || 'fuera de la ubicación del punto.'));
                } else {
                    overlay('ok', (j.tipo === 'entrada' ? 'Entrada' : 'Salida') + ' registrada', '¡Gracias, ' + (empNombre || '') + '!');
                }
                if (stream) stream.getTracks().forEach(t => t.stop());
            } catch (e) {
                setMsg('');
                setBusy(false);
                overlay('err', 'Sin conexión', 'No se pudo registrar. Revisa tu señal e inténtalo de nuevo.', [
                    { txt: 'Intentar de nuevo', fn: cerrarOverlay }
                ]);
            }
        };

        init();
    })();
    </script>
<?php endif; ?>
</body>
</html>
