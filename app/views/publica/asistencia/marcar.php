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
        .panel input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 10px; margin: 10px 0; font-size: .95rem; }
        .panel button { width: 100%; border: 0; background: #0d6efd; color: #fff; padding: 13px; border-radius: 10px; font-weight: 700; }
        /* Overlay resultado */
        .overlay { position: fixed; inset: 0; background: rgba(10,13,20,.96); display: none; flex-direction: column; align-items: center; justify-content: center; padding: 30px; text-align: center; z-index: 50; }
        .overlay .big { width: 96px; height: 96px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 52px; margin-bottom: 18px; }
        .overlay.ok .big { background: #1a9c53; }
        .overlay.warn .big { background: #e0a800; }
        .overlay.err .big { background: #d13438; }
        .overlay h2 { margin: 0 0 6px; }
        .overlay p { color: #cbd; }
        .overlay button { margin-top: 22px; border: 0; background: #0d6efd; color: #fff; padding: 12px 28px; border-radius: 12px; font-weight: 700; }
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

    <!-- Panel cuando no hay credencial en el dispositivo -->
    <div class="wrap" id="panelNoIdent" style="display:none;">
        <div class="panel">
            <h2>Identifícate</h2>
            <p>Este teléfono aún no tiene tu credencial. Abre tu <b>enlace personal</b> una vez, o pega tu código personal:</p>
            <input type="text" id="inpToken" placeholder="EMP-..." autocapitalize="characters">
            <button onclick="guardarToken()">Guardar credencial</button>
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
        <button onclick="location.reload()">Aceptar</button>
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
        let empToken = '';
        let empNombre = '';
        let stream = null;
        let storedDesc = null;   // descriptor facial enrolado (si existe)
        let faceReady = false;   // modelos cargados

        function init() {
            try {
                empToken = localStorage.getItem('casis_emp_token') || '';
                empNombre = localStorage.getItem('casis_emp_nombre') || '';
            } catch (e) {}
            if (!empToken) {
                document.getElementById('panelNoIdent').style.display = 'flex';
                return;
            }
            document.getElementById('panelMarca').style.display = 'flex';
            document.getElementById('whoNombre').textContent = empNombre || 'empleado';
            iniciarCamara();
            prepararRostro();
        }

        // Trae el descriptor facial del empleado (si está enrolado) y carga los modelos.
        async function prepararRostro() {
            try {
                const fd = new FormData(); fd.append('tokenEmpleado', empToken);
                const r = await fetch(BASE + '/asistencia/descriptor', { method: 'POST', body: fd });
                const j = await r.json();
                if (!j.ok || !j.descriptor) return; // sin rostro enrolado → solo QR
                storedDesc = j.descriptor;
                document.getElementById('msg').textContent = 'Preparando verificación facial...';
                await window.CASIS_FACE.loadModels();
                faceReady = true;
                document.getElementById('msg').textContent = '';
            } catch (e) {
                faceReady = false; // si falla, se marca solo con QR + GPS
            }
        }

        window.guardarToken = function () {
            const t = document.getElementById('inpToken').value.trim();
            if (!t) return;
            try { localStorage.setItem('casis_emp_token', t); } catch (e) {}
            location.reload();
        };

        async function iniciarCamara() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                document.getElementById('video').srcObject = stream;
            } catch (e) {
                document.getElementById('msg').textContent = 'Sin cámara: se marcará sin selfie.';
            }
        }

        function capturarSelfie() {
            const v = document.getElementById('video');
            if (!v || !v.videoWidth) return '';
            const c = document.getElementById('canvas');
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

        function overlay(tipo, titulo, texto) {
            const ov = document.getElementById('overlay');
            ov.className = 'overlay ' + tipo;
            document.getElementById('ovIco').textContent = tipo === 'ok' ? '✓' : (tipo === 'warn' ? '!' : '✕');
            document.getElementById('ovTitulo').textContent = titulo;
            document.getElementById('ovTexto').textContent = texto || '';
            ov.style.display = 'flex';
        }

        function setBusy(b) {
            document.getElementById('btnEntrada').disabled = b;
            document.getElementById('btnSalida').disabled = b;
        }

        window.marcar = async function (tipo) {
            setBusy(true);
            document.getElementById('msg').textContent = 'Obteniendo ubicación...';
            const gps = await obtenerGps();
            if (EXIGE_GPS && !gps) {
                document.getElementById('msg').textContent = '';
                overlay('err', 'Ubicación requerida', 'Activa el GPS y permite la ubicación para marcar en este punto.');
                setBusy(false);
                return;
            }
            // Verificación facial 1:1 (si el empleado tiene rostro enrolado).
            let confianza = null, faceSusp = false;
            if (storedDesc && faceReady) {
                document.getElementById('msg').textContent = 'Verificando rostro...';
                try {
                    const live = await window.CASIS_FACE.descriptor(document.getElementById('video'));
                    if (live) {
                        const dist = window.CASIS_FACE.distancia(storedDesc, live);
                        confianza = Math.max(0, Math.min(100, Math.round((1 - dist) * 100)));
                        if (dist > window.CASIS_FACE.THRESHOLD) faceSusp = true;
                    } else {
                        faceSusp = true; // no se detectó rostro
                    }
                } catch (e) {}
            }

            document.getElementById('msg').textContent = 'Registrando...';
            const selfie = capturarSelfie();

            const fd = new FormData();
            fd.append('tokenEmpleado', empToken);
            fd.append('tokenPunto', TOKEN_PUNTO);
            fd.append('tipo', tipo);
            if (gps) { fd.append('latitud', gps.lat); fd.append('longitud', gps.lng); }
            if (selfie) fd.append('selfie', selfie);
            if (confianza !== null) fd.append('confianza', confianza);
            if (faceSusp) fd.append('face_sospechosa', '1');

            try {
                const r = await fetch(BASE + '/asistencia/registrar', { method: 'POST', body: fd });
                const j = await r.json();
                document.getElementById('msg').textContent = '';
                if (!j.ok) {
                    overlay('err', 'No se registró', j.error || 'Error desconocido.');
                } else if (j.estado === 'sospechosa') {
                    overlay('warn', (j.tipo === 'entrada' ? 'Entrada' : 'Salida') + ' registrada', 'Quedó marcada como SOSPECHOSA: ' + (j.observacion || 'fuera de la ubicación del punto.'));
                } else {
                    overlay('ok', (j.tipo === 'entrada' ? 'Entrada' : 'Salida') + ' registrada', '¡Gracias, ' + (empNombre || '') + '!');
                }
                if (stream) stream.getTracks().forEach(t => t.stop());
            } catch (e) {
                document.getElementById('msg').textContent = '';
                overlay('err', 'Sin conexión', 'No se pudo registrar. Revisa tu señal e inténtalo de nuevo.');
                setBusy(false);
            }
        };

        init();
    })();
    </script>
<?php endif; ?>
</body>
</html>
