<?php
/**
 * Cron Runner — punto de entrada del cron del servidor.
 *
 * Configurar en el servidor (cPanel, crontab):
 *   * * * * * php /ruta/sistema/app/cron/cron_runner.php >> /ruta/logs/cron.log 2>&1
 *
 * Solo debe correr este archivo. Toda la lógica de qué ejecutar y cuándo
 * está en la tabla `automatizaciones` de cada empresa.
 */

define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

// Sin límite de tiempo — los scripts CLI deben correr hasta completarse
set_time_limit(0);
ini_set('memory_limit', '256M');

use App\repositories\modulos\AutomatizacionesRepository;
use App\Services\modulos\AutomatizacionesService;
use App\Rules\modulos\AutomatizacionesRules;
use App\Services\LogSistemaService;

// ── Control de concurrencia (evita ejecuciones simultáneas) ──────────────────
$lockFile = sys_get_temp_dir() . '/sistema_cron.lock';

if (file_exists($lockFile)) {
    $pid = (int)file_get_contents($lockFile);
    // En Windows no hay función posix_kill; se asume que si el lock existe es válido
    if (PHP_OS_FAMILY !== 'Windows' && $pid > 0 && posix_kill($pid, 0)) {
        echo "[" . date('Y-m-d H:i:s') . "] Cron ya en ejecución (PID {$pid}). Saliendo.\n";
        exit(0);
    }
    // Lock huérfano, continuar
}

file_put_contents($lockFile, getmypid());

// ── Tareas (FIJO, no configurable) ────────────────────────────────────────────
//    1) En CADA tick: marcar vencidas por fecha (barato; mantiene el estado al día).
//    2) Recordatorio por correo a responsables: se autolimita a UN envío/día (06:00).
try {
    $svcTareas = new \App\Services\TareaRecordatorioService();

    $nVenc = $svcTareas->marcarVencidas();
    if ($nVenc > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Tareas marcadas vencidas: {$nVenc}.\n";
    }

    $rec = $svcTareas->ejecutarSiCorresponde();
    if (!empty($rec['ejecutado'])) {
        echo "[" . date('Y-m-d H:i:s') . "] Recordatorio tareas: {$rec['correos']} correo(s), {$rec['tareas']} tarea(s).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en tareas (vencidas/recordatorio): " . $e->getMessage() . "\n";
}

// ── Chat de soporte: archivar conversaciones cerradas (FIJO, 1 vez al día) ────
//    Archivar NO borra: solo saca de la bandeja las conversaciones cerradas que
//    ya cumplieron soporte_config.dias_archivar_cerradas. Se autolimita con una
//    marca diaria porque el cron corre cada minuto y esto no tiene por qué.
try {
    $marcaArchivado = sys_get_temp_dir() . '/sistema_soporte_archivado.txt';
    $hoy = date('Y-m-d');

    if ((int) date('H') >= 3 && @file_get_contents($marcaArchivado) !== $hoy) {
        $svcSoporte = new \App\Services\modulos\SoporteChatService(
            new \App\repositories\modulos\SoporteChatRepository(),
            new \App\Rules\modulos\SoporteChatRules(),
            new LogSistemaService(),
        );
        $nArchivadas = $svcSoporte->archivarCerradas();
        file_put_contents($marcaArchivado, $hoy);

        if ($nArchivadas > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Soporte: {$nArchivadas} conversación(es) archivada(s).\n";
        }
    }

    // Aviso de consultas sin atender: sí corre en CADA tick (el retraso en
    // responder importa por minutos, no por días). El propio servicio evita
    // reenviar mientras no cambie la lista, así que no genera spam.
    $svcAlerta = new \App\Services\modulos\SoporteChatService(
        new \App\repositories\modulos\SoporteChatRepository(),
        new \App\Rules\modulos\SoporteChatRules(),
        new LogSistemaService(),
    );
    $alerta = $svcAlerta->alertarSinAtender();
    if (!empty($alerta['enviado'])) {
        $canales = [];
        if (!empty($alerta['correo'])) {
            $canales[] = 'correo';
        }
        if (!empty($alerta['whatsapp'])) {
            $canales[] = "WhatsApp ({$alerta['whatsapp']} número/s)";
        }
        echo "[" . date('Y-m-d H:i:s') . "] Soporte: aviso enviado por {$alerta['conversaciones']} consulta(s) sin atender"
            . ($canales !== [] ? ' — ' . implode(' + ', $canales) : '') . ".\n";
    }
} catch (\Throwable $e) {
    // Si el módulo de soporte aún no está desplegado, el resto del cron sigue.
    echo "[" . date('Y-m-d H:i:s') . "] Error archivando conversaciones de soporte: " . $e->getMessage() . "\n";
}

// ── CRM: renovar el acceso de las cuentas de Instagram (FIJO, 1 vez al día) ──
//    El token dura ~60 días; se renueva cuando faltan 10 o menos. Uno vencido
//    queda «Acceso vencido» y solo se recupera reconectando la cuenta. Sin el SQL
//    del CRM (o sin la app de Meta configurada) no hace nada.
try {
    $marcaCrm = sys_get_temp_dir() . '/sistema_crm_tokens.txt';
    $hoy = date('Y-m-d');
    if ((int) date('H') >= 4 && @file_get_contents($marcaCrm) !== $hoy) {
        file_put_contents($marcaCrm, $hoy);
        $svcCanales = \App\Services\modulos\CrmCanalService::crear();
        // Cada canal se renueva con la app de Meta de su empresa; si la empresa
        // quitó su app, ese canal cuenta como error y vencerá (hay que reconectar).
        if (\App\Services\modulos\CrmConfiguracionService::crear()->disponible()) {
            $rTok = $svcCanales->renovarTokens();
            if ($rTok['renovados'] || $rTok['vencidos'] || $rTok['errores']) {
                echo "[" . date('Y-m-d H:i:s') . "] CRM Instagram: {$rTok['renovados']} acceso(s) renovado(s), {$rTok['vencidos']} vencido(s), {$rTok['errores']} error(es).\n";
            }
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error renovando accesos de Instagram (CRM): " . $e->getMessage() . "\n";
}

// ── CRM: cola del webhook de Instagram (FIJO, en CADA tick) ──────────────────
//    El webhook procesa cada evento al recibirlo; aquí se retoman los que
//    quedaron pendientes o con error (hasta 5 intentos). Una vez al día se
//    purgan los ya resueltos con más de 30 días (es una cola técnica).
try {
    $svcWebhook = \App\Services\modulos\CrmWebhookService::crear();
    if ($svcWebhook->disponible()) {
        $rCola = $svcWebhook->procesarPendientes(200);
        if ($rCola['procesados'] || $rCola['errores']) {
            echo "[" . date('Y-m-d H:i:s') . "] CRM webhook: {$rCola['procesados']} evento(s) procesado(s), {$rCola['ignorados']} ignorado(s), {$rCola['errores']} con error.\n";
        }
        $marcaPurga = sys_get_temp_dir() . '/sistema_crm_purga_eventos.txt';
        if ((int) date('H') >= 3 && @file_get_contents($marcaPurga) !== date('Y-m-d')) {
            file_put_contents($marcaPurga, date('Y-m-d'));
            $nPurga = $svcWebhook->purgar(30);
            if ($nPurga > 0) {
                echo "[" . date('Y-m-d H:i:s') . "] CRM webhook: {$nPurga} evento(s) antiguos purgados.\n";
            }
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error procesando la cola del webhook de Instagram (CRM): " . $e->getMessage() . "\n";
}

// ── CRM: publicar lo programado en Instagram y LinkedIn (FIJO, en CADA tick) ─
//    Cada destino (red/cuenta) se toma con SKIP LOCKED y se «alquila» 10 min.
//    Sin el SQL de la fase 3 no hace nada. Diseño: docs/diseno/crm-redes-fase3.md.
try {
    $svcPub = \App\Services\modulos\CrmPublicadorService::crear();
    if ($svcPub->disponible()) {
        $rPub = $svcPub->procesarPendientes(10);
        if ($rPub['publicadas'] || $rPub['errores']) {
            echo "[" . date('Y-m-d H:i:s') . "] CRM publicaciones: {$rPub['publicadas']} publicada(s), {$rPub['esperando']} en espera, {$rPub['errores']} con error.\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error publicando en redes (CRM): " . $e->getMessage() . "\n";
}

// ── CRM: métricas de lo publicado (FIJO, en CADA tick) y seguidores (1 vez al día) ─
//    Cortes a las 24 h, 7 días y 30 días de publicada; de a 30 por tick. Los
//    seguidores de las cuentas de Instagram, una vez al día desde las 06:00.
try {
    $svcMet = \App\Services\modulos\CrmMetricaService::crear();
    if ($svcMet->disponible()) {
        $rMet = $svcMet->tomarCortes(30);
        if ($rMet['tomadas'] || $rMet['errores']) {
            echo "[" . date('Y-m-d H:i:s') . "] CRM métricas: {$rMet['tomadas']} corte(s) tomado(s), {$rMet['errores']} con error.\n";
        }
        $marcaSeg = sys_get_temp_dir() . '/sistema_crm_seguidores.txt';
        if ((int) date('H') >= 6 && @file_get_contents($marcaSeg) !== date('Y-m-d')) {
            file_put_contents($marcaSeg, date('Y-m-d'));
            $svcMet->tomarSeguidores();
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error tomando métricas de redes (CRM): " . $e->getMessage() . "\n";
}

// ── CRM: purga de medios sin uso de las publicaciones (FIJO, 1 vez al día) ──
//    Imágenes y videos que nadie usa hace 90 días y que ninguna publicación
//    pendiente necesita: se borra el archivo y la fila queda como eliminada.
//    Sin el SQL de la fase 3 no hace nada. Diseño: docs/diseno/crm-redes-fase3.md.
try {
    $marcaMedios = sys_get_temp_dir() . '/sistema_crm_purga_medios.txt';
    if ((int) date('H') >= 3 && @file_get_contents($marcaMedios) !== date('Y-m-d')) {
        file_put_contents($marcaMedios, date('Y-m-d'));
        $nMedios = \App\Services\modulos\CrmMedioService::crear()->purgar();
        if ($nMedios > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] CRM publicaciones: {$nMedios} archivo(s) sin uso purgado(s).\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error purgando medios de publicaciones (CRM): " . $e->getMessage() . "\n";
}

// ── CRM: asistente virtual de Instagram (FIJO, en CADA tick) ────────────────
//    Responde los mensajes directos en cola (bot_pendiente_at vencido). Va
//    después de la cola del webhook para atender lo que esta acaba de guardar.
//    Sin el SQL de la fase 2 (crm_bot_config) no hace nada.
//    Diseño: docs/diseno/crm-redes-fase2.md.
try {
    $svcBot = \App\Services\modulos\CrmBotService::crear();
    if ($svcBot->disponible()) {
        $rBot = $svcBot->procesarPendientes(20);
        if ($rBot['respondidas'] || $rBot['traspasos'] || $rBot['errores']) {
            echo "[" . date('Y-m-d H:i:s') . "] CRM asistente: {$rBot['respondidas']} respuesta(s), {$rBot['traspasos']} pasada(s) a una persona, {$rBot['omitidas']} omitida(s), {$rBot['errores']} con error.\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en el asistente virtual de Instagram (CRM): " . $e->getMessage() . "\n";
}

// ── Videollamadas: recordatorio de las reuniones que están por empezar ───────
//    Corre en CADA tick a propósito: el aviso debe llegar minutos antes, no una
//    vez al día. No necesita marca de "ya corrí": la consulta descarta las
//    reuniones que ya tienen su recordatorio en la bitácora, así que cada una
//    recibe exactamente uno.
try {
    $svcVc = new \App\Services\modulos\VideollamadaService(
        new \App\repositories\modulos\VideollamadaRepository(),
        new \App\Rules\modulos\VideollamadaRules(),
        new LogSistemaService(),
    );
    $rec = $svcVc->enviarRecordatoriosPendientes(15);
    if ($rec['salas'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Videollamadas: {$rec['salas']} reunión(es) recordada(s), {$rec['correos']} correo(s).\n";
    }

    // Cierre de reuniones abandonadas. También en CADA tick: una sala colgada
    // en 'en_curso' bloquea su eliminación y aparece como reunión activa, así
    // que conviene limpiarla pronto. No necesita marca de "ya corrí": la
    // condición es el latido de cada sala, y una vez finalizada deja de
    // aparecer en la cola.
    $inact = $svcVc->finalizarInactivas(10);
    if ($inact['salas'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Videollamadas: {$inact['salas']} reunión(es) finalizada(s) por inactividad, "
            . "{$inact['participantes']} participante(s) cerrado(s).\n";
    }
} catch (\Throwable $e) {
    // Si el módulo aún no está desplegado (faltan las tablas), el resto sigue.
    echo "[" . date('Y-m-d H:i:s') . "] Error en recordatorios de videollamadas: " . $e->getMessage() . "\n";
}

// ── SRI: reintentar comprobantes pendientes de resolución ────────────────────
//    Corre en CADA tick a propósito: la elegibilidad la da la antigüedad de cada
//    comprobante (mínimo 5 min desde el último intento), no una marca de "ya
//    corrí". Es seguro e idempotente: SriEnvioService nunca reenvía una clave
//    de acceso que el SRI ya tiene en cola, solo vuelve a consultar su estado.
try {
    $svcSriPend = new \App\Services\Sri\SriReintentosPendientesService();
    $resSriPend = $svcSriPend->procesar();
    if ($resSriPend['reintentados'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] SRI pendientes: {$resSriPend['reintentados']} reintentado(s), "
            . "{$resSriPend['resueltos']} resuelto(s), {$resSriPend['avisos']} aviso(s) enviado(s).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en reintentos de comprobantes SRI: " . $e->getMessage() . "\n";
}

// ── SRI: comprobantes emitidos entre empresas del sistema ────────────────────
//    Va DESPUÉS de los reintentos: lo que acaban de autorizar ya queda encolado y
//    se registra en la empresa receptora en este mismo tick. Corre en cada tick;
//    idempotente (la cola y el registrador descartan duplicados).
try {
    $resEntreEmp = (new \App\Services\modulos\DocumentoEntreEmpresasService())->procesarPendientes();
    if ($resEntreEmp['procesados'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Documentos entre empresas: {$resEntreEmp['procesados']} procesado(s), "
            . "{$resEntreEmp['registrados']} registrado(s), {$resEntreEmp['existentes']} ya existían, "
            . "{$resEntreEmp['ignorados']} ignorado(s), {$resEntreEmp['errores']} error(es).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en documentos entre empresas: " . $e->getMessage() . "\n";
}

// ── Firma electrónica: aviso por correo de caducidad (FIJO, 1 vez al día) ─────
//    Complementa al ícono del navbar: avisa al correo registrado en la ficha de
//    la empresa (empresas.mail) 15 días antes si es sociedad o 5 si es persona
//    natural, y otra vez a 2 días (o ya caducada) con el enlace para solicitar la
//    firma nueva. Se autolimita a UNA revisión diaria desde las 06:00 y a UN
//    correo por etapa y firma (la marca vive en log_sistema).
try {
    $svcFirma = new \App\Services\FirmaCaducidadAvisoService();
    $resFirma = $svcFirma->ejecutarSiCorresponde();
    if (!empty($resFirma['ejecutado']) && $resFirma['firmas'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Firma electrónica: {$resFirma['firmas']} firma(s) por caducar, "
            . "{$resFirma['correos']} correo(s) enviado(s), {$resFirma['sin_correo']} sin correo, "
            . "{$resFirma['fallidos']} fallido(s).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en aviso de caducidad de firma electrónica: " . $e->getMessage() . "\n";
}

// ── Firmas vendidas a clientes: correo con la factura y la confirmación ──────
//    Cuando el SRI autoriza la factura después del pago (reintentos de arriba), el
//    cliente recibe aquí su factura con el botón "Confirmo que recibí mi factura".
//    Idempotente: solo toma firmas pagadas sin ese correo enviado.
try {
    $resFirmasCli = \App\Services\modulos\FirmaElectronicaService::instancia()->procesarCorreosPendientes();
    if ($resFirmasCli['enviados'] > 0 || $resFirmasCli['fallidos'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Firmas (clientes): {$resFirmasCli['enviados']} correo(s) de confirmación, "
            . "{$resFirmasCli['fallidos']} fallido(s).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en correos de confirmación de firmas: " . $e->getMessage() . "\n";
}

// ── Firmas vendidas a clientes: avisos de vencimiento (1 vez al día) ─────────
//    Desde las 08:00, correo y WhatsApp a los titulares cuya firma vence pronto, en
//    los días configurados por empresa. La bitácora (firmas_avisos_vencimiento)
//    impide repetir un aviso aunque la marca diaria se pierda.
try {
    $marcaAvisosFirma = sys_get_temp_dir() . '/sistema_firmas_avisos_vencimiento.txt';
    $hoyAvisos = date('Y-m-d');
    if ((int) date('H') >= 8 && @file_get_contents($marcaAvisosFirma) !== $hoyAvisos) {
        @file_put_contents($marcaAvisosFirma, $hoyAvisos);
        $resAv = \App\Services\modulos\FirmaElectronicaService::instancia()->procesarAvisosVencimiento();
        if ($resAv['correos'] + $resAv['whatsapp'] + $resAv['fallidos'] > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Avisos de vencimiento de firmas: {$resAv['correos']} correo(s), "
                . "{$resAv['whatsapp']} WhatsApp, {$resAv['fallidos']} fallido(s).\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en avisos de vencimiento de firmas: " . $e->getMessage() . "\n";
}

// ── Firmas vendidas a clientes: API de Uanataca ──────────────────────────────
//    Envía las solicitudes pagadas (empresas con envío automático) y consulta el
//    estado de las ya enviadas (Uanataca no tiene webhooks). Cada firma se consulta
//    como máximo cada 10 min y un envío fallido se reintenta a la hora.
try {
    $resUan = \App\Services\modulos\FirmaElectronicaService::instancia()->procesarUanataca();
    if ($resUan['enviadas'] + $resUan['sincronizadas'] + $resUan['errores'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Uanataca: {$resUan['enviadas']} enviada(s), "
            . "{$resUan['sincronizadas']} consultada(s), {$resUan['errores']} error(es).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en la integración con Uanataca: " . $e->getMessage() . "\n";
}

// ── Devengo de ingresos de suscripciones, NIIF 15 (FIJO, 1 vez al día) ───────
//    Pasa al ingreso lo diferido de los meses ya cumplidos y provisiona el mes caído,
//    en TODAS las empresas que tienen algo pendiente, sin que nadie lo configure.
//    Corre antes que las automatizaciones de cada empresa: el día 1 la provisión de mes
//    caído ya existe cuando «Generar facturación» emite la factura que la cancela.
//    Idempotente: correrlo de nuevo no duplica (cada mes toma solo lo pendiente).
try {
    $marcaDevengo = sys_get_temp_dir() . '/sistema_devengo_suscripciones.txt';
    $hoyDevengo   = date('Y-m-d');
    if (@file_get_contents($marcaDevengo) !== $hoyDevengo) {
        $resDevengo = \App\Services\modulos\SuscripcionDevengoService::crear()->devengoAutomatico();
        file_put_contents($marcaDevengo, $hoyDevengo);
        foreach ($resDevengo as $idEmp => $msg) {
            echo "[" . date('Y-m-d H:i:s') . "] Devengo suscripciones (empresa {$idEmp}): {$msg}\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en devengo de suscripciones: " . $e->getMessage() . "\n";
}

// ── Condominios: reajustes de cuotas programados (FIJO, 1 vez al día) ─────────
//    Un reajuste masivo programado para una fecha («desde el 1 de enero») se aplica ese día a
//    las suscripciones de los inmuebles, en todas las empresas. Idempotente: solo toma los
//    pendientes con fecha cumplida y los deja en 'aplicado' (o 'error').
try {
    $marcaReaj = sys_get_temp_dir() . '/sistema_condominios_reajustes.txt';
    $hoyReaj   = date('Y-m-d');
    if (@file_get_contents($marcaReaj) !== $hoyReaj) {
        $resReaj = \App\Services\modulos\CondominioService::crear()->aplicarReajustesProgramados();
        file_put_contents($marcaReaj, $hoyReaj);
        foreach ($resReaj as $idReaj => $msg) {
            echo "[" . date('Y-m-d H:i:s') . "] Reajuste condominio #{$idReaj}: {$msg}\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en reajustes de condominios: " . $e->getMessage() . "\n";
}

// ── Condominios: emisiones en bloque que quedaron abiertas ───────────────────
//    Si al crear la emisión no se pudo lanzar su worker (o se cortó), se relanza aquí, como
//    máximo cada 10 minutos. El worker tiene candado por emisión: si ya hay uno trabajando, el
//    relanzado sale sin hacer nada; los documentos a medias quedan «para revisar», nunca se repiten.
try {
    $marcaEmi = sys_get_temp_dir() . '/sistema_condominios_emisiones.txt';
    if (time() - (int) @file_get_contents($marcaEmi) >= 600) {
        file_put_contents($marcaEmi, (string) time());
        foreach (\App\Services\modulos\CondominioCobroService::crear()->retomarAbiertas() as $idEmi) {
            echo "[" . date('Y-m-d H:i:s') . "] Emisión de condominio #{$idEmi}: worker relanzado\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error al retomar emisiones de condominios: " . $e->getMessage() . "\n";
}

// ── Métricas del sistema: resumen diario (FIJO, 1 vez al día, desde la 01:00) ─
//    Recalcula metricas_diarias de los últimos 3 días (absorbe autorizaciones y anulaciones
//    tardías). Datos derivados: reemplaza esas filas desde las tablas de origen. Si la tabla
//    aún no existe (SQL sin aplicar), se salta sin error.
try {
    $marcaMetricas = sys_get_temp_dir() . '/sistema_metricas_diarias.txt';
    $hoyMetricas   = date('Y-m-d');
    if ((int) date('H') >= 1 && @file_get_contents($marcaMetricas) !== $hoyMetricas) {
        $svcMetricas = new \App\Services\MetricasDiariasService();
        file_put_contents($marcaMetricas, $hoyMetricas);
        if ($svcMetricas->disponible()) {
            $t0 = microtime(true);
            $resMetricas = $svcMetricas->ejecutarDiario();
            echo "[" . date('Y-m-d H:i:s') . "] Métricas diarias: {$resMetricas['dias']} día(s), {$resMetricas['filas']} fila(s), "
                . round((microtime(true) - $t0) * 1000) . "ms.\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en métricas diarias: " . $e->getMessage() . "\n";
}

// ── Métricas del sistema: relleno automático del historial (madrugada, 00:00-05:59) ─
//    Sin correr nada a mano: en cada pasada del cron calcula UN tramo de 31 días hacia
//    atrás, hasta llegar al documento más antiguo (incluidos los migrados). Unos 12 tramos
//    por año de historia, así que años de datos se completan en una sola madrugada.
//    Cuando termina, lo vuelve a comprobar una vez por día (por si se migró una empresa
//    con documentos más antiguos). Marca: JSON {fecha, inicio, completo}.
try {
    $hora = (int) date('H');
    if ($hora < 6) {
        $marcaRelleno = sys_get_temp_dir() . '/sistema_metricas_relleno.txt';
        $estRelleno   = json_decode((string) @file_get_contents($marcaRelleno), true) ?: [];
        $hoyRelleno   = date('Y-m-d');
        $yaCompleto   = ($estRelleno['fecha'] ?? '') === $hoyRelleno && !empty($estRelleno['completo']);

        if (!$yaCompleto) {
            $svcRelleno = new \App\Services\MetricasDiariasService();
            if ($svcRelleno->disponible()) {
                // La fecha del dato más antiguo se calcula una vez por día y se reutiliza en cada tramo.
                $inicioCache = ($estRelleno['fecha'] ?? '') === $hoyRelleno ? ($estRelleno['inicio'] ?? null) : null;
                $t0  = microtime(true);
                $res = $svcRelleno->rellenarSiguienteTramo($inicioCache);
                file_put_contents($marcaRelleno, json_encode([
                    'fecha'    => $hoyRelleno,
                    'inicio'   => $res['inicio'] ?? $inicioCache,
                    'completo' => $res === null,
                ]));
                if ($res !== null) {
                    echo "[" . date('Y-m-d H:i:s') . "] Métricas: relleno {$res['desde']} .. {$res['hasta']} ({$res['filas']} filas, "
                        . round((microtime(true) - $t0) * 1000) . "ms; historia desde {$res['inicio']}).\n";
                }
            }
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en relleno de métricas: " . $e->getMessage() . "\n";
}

// ── Cambio temporal de IVA en productos (FIJO, cada tick) ─────────────────────
//    Prestadores de servicios turísticos (IVA 8% en feriados): al llegar la fecha desde cambia la
//    tarifa de sus productos y al pasar la fecha hasta la restaura. Barato: una
//    consulta indexada; solo escribe cuando hay algo que aplicar o restaurar.
try {
    foreach (\App\Services\modulos\IvaCambioTemporalService::crear()->procesarPendientes() as $linea) {
        echo "[" . date('Y-m-d H:i:s') . "] {$linea}\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en cambio temporal de IVA: " . $e->getMessage() . "\n";
}

// ── Ejecutar ──────────────────────────────────────────────────────────────────
try {
    $repository = new AutomatizacionesRepository();
    $rules      = new AutomatizacionesRules();
    $logService = new LogSistemaService();
    $service    = new AutomatizacionesService($repository, $rules, $logService);

    $pendientes = $repository->getPendientes();

    if (empty($pendientes)) {
        unlink($lockFile);
        exit(0);
    }

    $count = count($pendientes);
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando cron — {$count} tareas pendientes.\n";

    foreach ($pendientes as $tarea) {
        $inicio = microtime(true);
        echo "[" . date('Y-m-d H:i:s') . "] Ejecutando: [{$tarea['modulo']}:{$tarea['accion']}] \"{$tarea['nombre']}\" (ID {$tarea['id']}, empresa {$tarea['id_empresa']})... ";

        try {
            $resultado = $service->ejecutarTarea($tarea, 'cron');
            $ms = round((microtime(true) - $inicio) * 1000);
            echo "OK ({$resultado['resultado']}, {$resultado['registros']} reg., {$ms}ms)\n";
        } catch (\Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Cron finalizado.\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error fatal en cron: " . $e->getMessage() . "\n";
} finally {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
