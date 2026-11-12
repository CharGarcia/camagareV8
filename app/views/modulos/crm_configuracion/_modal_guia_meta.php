<?php
/**
 * Configuración del CRM → Canales: modal «Paso a paso para configurar la app en Meta»
 * (Unified login, apps de Meta desde nov. 2026) y modal previo a «Conectar».
 * Cada paso es una tarjeta que imita la pantalla de Meta (ruta de menús y campos
 * con su nombre allá) con el valor del sistema listo para copiar. La navegación
 * (Anterior / Siguiente) está en crm_configuracion.js (CRMC.guia*).
 * Diseño: docs/diseno/crm-redes-unified-login.md.
 *
 * @var callable $h         escape HTML
 * @var callable $copiable  campo de solo lectura con botón Copiar
 * @var array    $app       app de Meta de la empresa (sin App Secret)
 * @var array    $urlsMeta  direcciones que se registran en Meta
 * @var string   $urlManual artículo del manual
 * @var array    $perm
 */

/** Ruta de menús de Meta, como migas. */
$ruta = static function (array $pasos) use ($h): string {
    $out = '<div class="crmc-meta-ruta"><i class="bi bi-meta text-primary me-1"></i>';
    foreach ($pasos as $i => $p) {
        $out .= ($i ? '<i class="bi bi-chevron-right mx-1 text-body-tertiary"></i>' : '') . '<span>' . $h($p) . '</span>';
    }
    return $out . '</div>';
};
/** Un campo «como en Meta»: etiqueta de Meta + qué poner. */
$campo = static function (string $etiqueta, string $contenido) use ($h): string {
    return '<div class="crmc-meta-campo"><div class="crmc-meta-etiqueta">' . $h($etiqueta) . '</div>' . $contenido . '</div>';
};
$valor = static fn(string $t) => '<div class="crmc-meta-valor">' . $t . '</div>';
$tokenWebhook = $app['configurada']
    ? $copiable((string) $app['verify_token'], 'crmc-verify-token-guia')
    : '<div class="small text-warning-emphasis"><i class="bi bi-hourglass-split me-1"></i>Aparece al guardar la app en el paso 4.</div>';

$pasos = [
    [
        'titulo' => 'Crear la app',
        'ruta'   => ['developers.facebook.com', 'Mis apps', 'Crear app'],
        'enlace' => ['https://developers.facebook.com/apps', 'Abrir Mis apps en Meta'],
        'campos' => [
            ['Nombre de la app', $valor('El de su empresa, p. ej. <strong>MiEmpresa CRM</strong>. Sin «Instagram», «Facebook», «Meta» ni «WhatsApp»: Meta lo rechaza.')],
            ['Correo de contacto', $valor('Uno de la empresa que revisen seguido (ahí llegan los avisos de la revisión).')],
            ['Casos de uso', $valor('Marque <strong>solo</strong> <strong>Administrar mensajes y contenido en Instagram</strong> (filtro «Todo» si no aparece). Ni anuncios, ni WhatsApp, ni inicio de sesión con Facebook.')],
            ['Portafolio comercial', $valor('El de la empresa (si dice «Verificación completada», mejor).')],
        ],
        'nota' => 'Si la empresa ya tiene una app de Meta para WhatsApp, cree otra para el CRM: así la revisión de Instagram no toca la de WhatsApp.',
    ],
    [
        'titulo' => 'Completar la configuración básica',
        'ruta'   => ['Configuración de la app', 'Básica'],
        'campos' => [
            ['Dominios de la app', $copiable((string) (parse_url((string) $urlsMeta['oauth'], PHP_URL_HOST) ?: ''))],
            ['URL de la política de privacidad', $valor('La página pública de privacidad de la empresa.')],
            ['Eliminación de datos de usuario', $copiable((string) $urlsMeta['eliminar'])
                . '<div class="small text-muted">Elija <strong>URL de devolución de llamada para eliminar datos</strong> y pegue esta dirección.</div>'],
            ['Categoría', $valor('<strong>Empresas y páginas</strong> (o la más parecida).')],
            ['Ícono de la app', $valor('El logo de la empresa (1024 × 1024).')],
        ],
        'nota' => 'Hágalo antes de conectar: si esta pantalla queda vacía, Meta corta la conexión con «No se pudo conectar… no pudimos establecer conexión con nuestro servidor». Pulse Guardar cambios.',
    ],
    [
        'titulo' => 'Crear la configuración de inicio de sesión',
        'ruta'   => ['Casos de uso', 'Administrar mensajes y contenido en Instagram', 'Personalizar', 'API setup with Unified login', '1. Configura tu aplicación'],
        'campos' => [
            ['Crear nueva configuración', $valor('Pulse el botón. Meta muestra «Confirma los detalles de configuración de inicio de sesión».')],
            ['Identificador de acceso', $valor('<strong>Identificador de acceso de usuario del sistema</strong>')],
            ['Fecha de caducidad', $valor('<strong>El identificador no caduca nunca</strong>')],
            ['Activos', $valor('<strong>Cuentas de Instagram</strong> (las cuentas publicitarias no hacen falta)')],
            ['Permisos', $valor('Meta la crea con <code>ads_management</code> y <code>ads_read</code>. Pulse <strong>Editar permisos</strong>: quite esos dos y deje '
                . '<code>instagram_basic</code>, <code>instagram_manage_messages</code>, <code>instagram_manage_comments</code>, '
                . '<code>instagram_content_publish</code> e <code>instagram_manage_insights</code> (este da las métricas).')],
            ['Permisos y funciones', $valor('En el menú del caso de uso, los cinco deben decir <strong>Listo para prueba</strong>.')],
        ],
        'nota' => 'No use «Crear otra configuración» para corregir algo: hace una copia igual. Las configuraciones se ven en «Inicio de sesión con Facebook para empresas → Ajustes» (el identificador no cambia al editar).',
    ],
    [
        'titulo' => 'URL de redirección e ID de configuración',
        'ruta'   => ['API setup with Unified login', '1. Configura tu aplicación'],
        'campos' => [
            ['Registrar una nueva URL de redirección', $copiable((string) $urlsMeta['oauth'])
                . '<div class="small text-muted">Péguela, regístrela y déjela <strong>seleccionada</strong> («Selecciona la URL registrada…»).</div>'],
            ['Identificador de configuración', $valor('Meta lo muestra junto a la configuración (solo números) y también en la <em>URL de inicio de sesión</em>, '
                . 'después de <code>config_id=</code>. Cópielo.')],
            ['Configuración de la app → Básica', $valor('Copie el <strong>Identificador de la app</strong> (App ID) y la <strong>Clave secreta de la app</strong> (pulse «Mostrar»).')],
        ],
        'nota' => 'Luego, en esta misma tarjeta del sistema (detrás de este modal), escriba App ID, App Secret e ID de configuración y pulse Guardar.',
    ],
    [
        'titulo' => 'Configurar los webhooks',
        'ruta'   => ['API setup with Unified login', '3. Configurar webhooks'],
        'campos' => [
            ['URL de devolución de llamada', $copiable((string) $urlsMeta['webhook'])],
            ['Identificador de verificación', $tokenWebhook],
            ['Verificar y guardar', $valor('Si Meta acepta, aparece la lista de <strong>campos del webhook</strong>. Si dice «Forbidden», el identificador no coincide: cópielo de nuevo.')],
            ['Campos del webhook', $valor('Suscriba solo <strong>messages</strong>, <strong>messaging_seen</strong> y <strong>comments</strong>.')],
        ],
        'nota' => 'Está más abajo en la misma página de «API setup with Unified login», después de «2. Generar identificadores de acceso».',
    ],
    [
        'titulo' => 'Agregar la cuenta de Instagram para probar',
        'ruta'   => ['API setup with Unified login', '2. Generar identificadores de acceso'],
        'enlace' => ['https://www.instagram.com/accounts/manage_access/', 'Abrir Apps y sitios web en Instagram'],
        'campos' => [
            ['Añadir cuenta', $valor('Agregue la cuenta de Instagram de la empresa con el rol <strong>Evaluador de Instagram</strong>.')],
            ['En instagram.com', $valor('Con la cuenta de la empresa: <em>Apps y sitios web → Invitaciones de evaluador → Aceptar</em>.')],
            ['Generar identificador', $valor('<strong>No hace falta</strong>: el sistema obtiene el acceso solo al conectar.')],
        ],
        'nota' => 'Mientras la app no esté publicada, solo funcionan las cuentas con un rol en la app. Para usarla con cualquier cliente hay que pasar la revisión de Meta (App Review) y publicarla.',
    ],
    [
        'titulo' => 'Si la cuenta de Instagram es de otro portafolio',
        'ruta'   => ['business.facebook.com/settings', 'Cuentas', 'Cuentas de Instagram', 'Asignar socio'],
        'enlace' => ['https://business.facebook.com/settings', 'Abrir la configuración del portafolio'],
        'campos' => [
            ['Cuándo', $valor('Si la cuenta de Instagram tiene su propio portafolio (Meta lo crea con el nombre de la cuenta) y su única persona es la identidad de Instagram «@cuenta». Esa identidad no puede crear el usuario del sistema y la conexión falla al pulsar Guardar.')],
            ['1. En el portafolio dueño de la app', $valor('Entrando con Facebook: <em>Información del negocio</em> → copie el <strong>identificador del portafolio</strong>.')],
            ['2. En el portafolio de la cuenta', $valor('Entrando como la cuenta de Instagram: <em>Cuentas de Instagram</em> → la cuenta → <strong>Asignar socio</strong> → pegue ese identificador → todos los permisos.')],
        ],
        'nota' => 'Si Meta dice «El identificador del negocio del socio no puede ser el tuyo», copió el número del mismo portafolio. Si la cuenta ya está en el portafolio de la app, salte este paso.',
    ],
    [
        'titulo' => 'Conectar la cuenta',
        'ruta'   => ['Sistema', 'Configuración del CRM', 'Canales', 'Conectar Instagram'],
        'campos' => [
            ['Conectar Instagram', $valor('Se abre Meta en una ventana aparte. Pulse <strong>Continuar con Facebook</strong> (no con Instagram) y entre con la cuenta administradora del portafolio dueño de la app.')],
            ['Qué elegir', $valor('Portafolio: el <strong>dueño de la app</strong>. Cuenta de Instagram: la de la empresa. Cuentas publicitarias: <strong>ninguna</strong>. Luego Continuar → Guardar.')],
            ['Al terminar', $valor('La ventana de Meta se cierra sola y esta pantalla muestra el resultado.')],
            ['Mensajes', $valor('Solo se pueden responder los mensajes que lleguen <strong>después</strong> de conectar.')],
        ],
        'nota' => 'Si al enviar sale el error (#200): en la app de Instagram del celular, Configuración → Mensajes y respuestas a historias → Herramientas conectadas → Permitir acceso a los mensajes.',
    ],
];
$total = count($pasos);
?>
<style>
    #crmc-modal-guia .crmc-meta-ruta { font-size:.78rem; color:#495057; background:#f0f2f5; border-radius:.4rem; padding:6px 10px; margin-bottom:10px; display:flex; flex-wrap:wrap; align-items:center; }
    #crmc-modal-guia .crmc-meta-pantalla { border:1px solid #dadde1; border-radius:.6rem; background:#fff; padding:12px 14px; }
    #crmc-modal-guia .crmc-meta-campo { padding:8px 0; border-bottom:1px solid #f0f2f5; }
    #crmc-modal-guia .crmc-meta-campo:last-child { border-bottom:0; }
    #crmc-modal-guia .crmc-meta-etiqueta { font-size:.78rem; font-weight:600; color:#1c1e21; margin-bottom:2px; }
    #crmc-modal-guia .crmc-meta-valor { font-size:.82rem; color:#444; }
    #crmc-modal-guia .crmc-guia-puntos .crmc-punto { width:26px; height:26px; border-radius:50%; border:1px solid #ced4da; background:#fff; font-size:.75rem; }
    #crmc-modal-guia .crmc-guia-puntos .crmc-punto.activo { background:#0d6efd; border-color:#0d6efd; color:#fff; }
    #crmc-modal-guia .crmc-guia-puntos .crmc-punto.hecho { background:#e7f1ff; border-color:#b6d4fe; color:#0d6efd; }
</style>

<!-- Paso a paso para configurar la app en Meta -->
<div class="modal fade" id="crmc-modal-guia" tabindex="-1" aria-labelledby="crmc-modal-guia-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="crmc-modal-guia-titulo"><i class="bi bi-meta text-primary me-1"></i>Configurar la app de Meta — paso a paso</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-1 mb-3 crmc-guia-puntos" role="tablist">
                    <?php foreach ($pasos as $i => $p): ?>
                        <button type="button" class="crmc-punto" data-paso="<?= $i ?>" title="<?= $h(($i + 1) . '. ' . $p['titulo']) ?>"
                                onclick="CRMC.guiaIr(<?= $i ?>)"><?= $i + 1 ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($pasos as $i => $p): ?>
                    <div class="crmc-guia-paso <?= $i === 0 ? '' : 'd-none' ?>" data-paso="<?= $i ?>">
                        <div class="small text-muted mb-1">Paso <?= $i + 1 ?> de <?= $total ?></div>
                        <h6 class="fw-bold mb-2"><?= $h($p['titulo']) ?></h6>
                        <?= $ruta($p['ruta']) ?>
                        <div class="crmc-meta-pantalla">
                            <?php foreach ($p['campos'] as [$etq, $html]): ?>
                                <?= $campo($etq, $html) ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($p['nota'])): ?>
                            <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i><?= $h($p['nota']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($p['enlace'])): ?>
                            <a class="btn btn-outline-primary btn-sm mt-2" href="<?= $h($p['enlace'][0]) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right me-1"></i><?= $h($p['enlace'][1]) ?></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="small text-body-tertiary mt-3 border-top pt-2">
                    Apps de Meta anteriores a noviembre de 2026 con «API setup with Instagram login»: use el <em>Instagram</em> App ID y Secret y deje vacío el
                    ID de configuración. Detalle en el manual: <a href="<?= $h($urlManual) ?>" target="_blank" rel="noopener">App de Meta para Instagram</a>.
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm me-auto" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="crmc-guia-anterior" onclick="CRMC.guiaMover(-1)"><i class="bi bi-chevron-left me-1"></i>Anterior</button>
                <button type="button" class="btn btn-primary btn-sm" id="crmc-guia-siguiente" onclick="CRMC.guiaMover(1)">Siguiente<i class="bi bi-chevron-right ms-1"></i></button>
                <?php if (!empty($perm['crear'])): ?>
                    <button type="button" class="btn btn-success btn-sm d-none" id="crmc-guia-conectar" onclick="CRMC.guiaConectar()"
                            <?= $app['configurada'] ? '' : 'disabled title="Primero registre la app (paso 4)"' ?>><i class="bi bi-instagram me-1"></i>Conectar Instagram</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Antes de ir a Meta / LinkedIn: qué va a pasar (la autorización se abre en una ventana aparte) -->
<div class="modal fade" id="crmc-modal-conectar" tabindex="-1" aria-labelledby="crmc-modal-conectar-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="crmc-modal-conectar-titulo"><i class="bi bi-box-arrow-up-right me-1 text-primary"></i><span data-red-titulo>Conectar</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body small">
                <div data-estado="antes">
                    <p class="mb-2">Se abrirá <strong data-red-nombre>la red</strong> en una <strong>ventana aparte</strong>. El sistema CaMaGaRe queda abierto aquí.</p>
                    <ol class="ps-3 mb-2" data-red-pasos></ol>
                    <p class="mb-0 text-muted">Al terminar, esa ventana se <strong>cierra sola</strong> y esta pantalla muestra el resultado. Si cierra la
                        ventana antes de terminar, no se conecta nada.</p>
                </div>
                <div data-estado="esperando" class="d-none text-center py-3">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div>Complete la autorización en la ventana de <strong data-red-nombre>la red</strong>…</div>
                    <div class="text-muted mt-1">¿No ve la ventana? Puede estar detrás de esta.</div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm me-auto" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" data-accion="abrir" onclick="CRMC.conectarAbrir()"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir y conectar</button>
            </div>
        </div>
    </div>
</div>
