<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\repositories\modulos\SuscripcionesRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\ReciboVentaRepository;
use App\Rules\modulos\FacturaVentaRules;
use App\Rules\modulos\ReciboVentaRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\Services\modulos\FacturaVentaService;
use App\Services\modulos\ReciboVentaService;
use App\Services\modulos\SuscripcionFacturacionService;
use App\Services\modulos\SuscripcionesService;
use App\Rules\modulos\SuscripcionesRules;

class SuscripcionesHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        return match ($this->accion) {
            'generar_facturacion'               => $this->generarFacturacion($idEmpresa, $idUsuario, $parametros),
            'cobrar_suscripciones_nuvei'        => $this->cobrarSuscripcionesNuvei($idEmpresa, $idUsuario, $parametros),
            'enviar_aviso_vencimiento'          => $this->enviarAvisoVencimiento($idEmpresa, $parametros),
            'enviar_aviso_vencimiento_whatsapp' => $this->enviarAvisoVencimientoWhatsapp($idEmpresa, $parametros),
            default                             => throw new \RuntimeException("Acción '{$this->accion}' no implementada en SuscripcionesHandler."),
        };
    }

    // ── Generar facturación ───────────────────────────────────────────────────
    // Solo crea las facturas (estado borrador). El SRI lo envía la automatización
    // separada de "Facturas de venta → Enviar al SRI".
    private function generarFacturacion(int $idEmpresa, int $idUsuario, array $p): array
    {
        $idPuntoEmision = (int)($p['id_punto_emision'] ?? 0);
        if ($idPuntoEmision <= 0) {
            return ['registros' => 0, 'mensaje' => 'No se configuró la serie (punto de emisión) en la automatización.'];
        }

        $suscRepo = new SuscripcionesRepository();

        // Serie elegida
        $estabConfig = $suscRepo->getEstablecimientoPorPunto($idEmpresa, $idPuntoEmision);
        if (!$estabConfig) {
            return ['registros' => 0, 'mensaje' => 'La serie configurada no es válida o está inactiva.'];
        }

        // Config de empresa
        $empresaConfig = (new \App\models\Empresa())->getPorId($idEmpresa);
        if (empty($empresaConfig)) {
            return ['registros' => 0, 'mensaje' => 'No hay configuración de empresa.'];
        }

        // Suscripciones con períodos vencidos
        $vencidas = $suscRepo->getVencidasPorEmpresa($idEmpresa);
        if (empty($vencidas)) {
            return ['registros' => 0, 'mensaje' => 'No hay suscripciones con períodos pendientes de facturar.'];
        }

        $facturacion = new SuscripcionFacturacionService(
            new FacturaVentaService(new FacturaVentaRepository(), new FacturaVentaRules(), new LogSistemaService()),
            new SecuencialService(),
            new ReciboVentaService(new ReciboVentaRepository(), new ReciboVentaRules(), new LogSistemaService())
        );
        $suscService = new SuscripcionesService($suscRepo, new SuscripcionesRules(), new LogSistemaService());

        // Overrides por periodicidad (puede venir como string JSON si viene del modal)
        $porPeriodicidad = $p['por_periodicidad'] ?? [];
        if (is_string($porPeriodicidad) && $porPeriodicidad !== '') {
            $porPeriodicidad = json_decode($porPeriodicidad, true) ?? [];
        }

        $hoy       = date('Y-m-d');
        $generadas = 0;
        $errores   = 0;

        foreach ($vencidas as $susc) {
            $idSusc  = (int)$susc['id'];
            $detalle = $suscRepo->getDetalle($idSusc);
            if (empty($detalle)) {
                continue;
            }

            $proximo  = (string)$susc['proximo_cobro'];
            $fechaFin = $susc['fecha_fin'] ?? null;
            $meses    = (int)($susc['periodicidad_meses'] ?? 1);
            $codigo   = (string)($susc['periodicidad_codigo'] ?? '');

            // Override de textos según la periodicidad de esta suscripción
            $override = $porPeriodicidad[strtoupper($codigo)] ?? [];
            $extras = [
                'texto_item'    => trim($override['texto_item']    ?? $p['texto_item']    ?? ''),
                'info_concepto' => trim($override['info_concepto'] ?? $p['info_concepto'] ?? ''),
                'info_detalle'  => trim($override['info_detalle']  ?? $p['info_detalle']  ?? ''),
            ];

            // Bucle de "ponerse al día": una factura por cada período vencido,
            // sin pasar la fecha_fin de la suscripción.
            while ($proximo <= $hoy && ($fechaFin === null || $proximo <= $fechaFin)) {
                // Calcular el siguiente período ANTES de crear la factura.
                // Protección anti-bucle: la fecha SIEMPRE debe avanzar.
                $nuevoProximo = $suscService->calcularProximoCobro($proximo, $meses, $codigo);
                if ($nuevoProximo <= $proximo) {
                    $errores++;
                    break;
                }

                try {
                    // 1. Crear el documento: factura o recibo según tipo_comprobante
                    //    (cada service maneja su propia transacción).
                    //    $proximo = fecha del período facturado → alimenta los placeholders {mes}, {anio}, etc.
                    $res = $facturacion->generarUnPeriodo(
                        $idEmpresa, $idUsuario, $susc, $detalle, $estabConfig, $empresaConfig, $extras, $proximo
                    );

                    // 2. Avanzar el próximo cobro. La generación del documento está
                    //    DESACOPLADA del cobro: si la suscripción usa tarjeta vía Nuvei,
                    //    el cargo real lo hace la automatización separada "Cobrar
                    //    suscripciones (Nuvei)" — aquí solo se deja el pago en 'pendiente'.
                    //    Crédito y Kushki no se tocan: siguen marcando 'exitoso' de inmediato.
                    $suscRepo->updateProximoCobro($idSusc, $nuevoProximo);

                    $esNuveiTarjeta = ($susc['forma_cobro'] ?? '') === 'tarjeta'
                        && ($susc['pasarela_tarjeta'] ?? '') === 'nuvei'
                        && !empty($susc['id_nuvei_tarjeta']);

                    $suscRepo->insertPago([
                        'id_suscripcion' => $idSusc,
                        'id_empresa'     => $idEmpresa,
                        'id_factura'     => $res['id_factura'],
                        'id_recibo'      => $res['id_recibo'],
                        'fecha_cobro'    => $hoy,
                        'monto'          => $res['importe'],
                        'estado'         => $esNuveiTarjeta ? 'pendiente' : 'exitoso',
                        'id_usuario'     => $idUsuario,
                    ]);

                    $generadas++;
                    $proximo = $nuevoProximo;
                } catch (\Throwable $e) {
                    // La factura falló: NO se avanzó la fecha → se reintenta en la próxima corrida.
                    $errores++;
                    \App\Services\ErrorLogService::registrar($e, [
                        'ruta'   => static::class,
                        'accion' => 'generarFacturacion#suscripcion_' . $idSusc,
                    ]);
                    break; // pasar a la siguiente suscripción
                }
            }
        }

        $msg = "Se generaron {$generadas} documento(s) de suscripciones.";
        if ($errores > 0) $msg .= " ({$errores} suscripción(es) con error)";
        return ['registros' => $generadas, 'mensaje' => $msg];
    }

    // ── Cobrar suscripciones (Nuvei) — desacoplada de la generación ───────────
    // Cobra los pagos que quedaron 'pendiente' (o 'fallido' con intentos
    // restantes) al generarse el documento. Reintenta hasta agotar el tope de
    // intentos configurado; al agotarlo, el pago queda 'fallido' definitivo.
    private function cobrarSuscripcionesNuvei(int $idEmpresa, int $idUsuario, array $p): array
    {
        $maxIntentos = max(1, (int) ($p['max_intentos'] ?? 3));

        $suscRepo   = new SuscripcionesRepository();
        $pendientes = $suscRepo->getPagosPendientesNuvei($idEmpresa, $maxIntentos);
        if (empty($pendientes)) {
            return ['registros' => 0, 'mensaje' => 'No hay cobros de suscripción pendientes con Nuvei.'];
        }

        $empresaConfig = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $empresaNombre = trim((string) ($empresaConfig['nombre_comercial'] ?? $empresaConfig['nombre'] ?? ''));
        $nuveiService  = new \App\Services\NuveiService(new \App\repositories\NuveiRepository());

        $cobrados   = 0;
        $rechazados = 0;
        $errores    = 0;

        foreach ($pendientes as $pago) {
            // Forma "suscripción" mínima para reutilizar los helpers de notificación/ingreso.
            $suscLike = [
                'id'                  => $pago['id_suscripcion'],
                'cliente_nombre'      => $pago['cliente_nombre'],
                'cliente_email'       => $pago['cliente_email'],
                'periodicidad_nombre' => $pago['periodicidad_nombre'],
            ];

            try {
                $descripcion = 'Suscripción #' . $pago['id_suscripcion'] . ' — ' . ($pago['periodicidad_nombre'] ?? 'período');
                $cargo = $nuveiService->debitarConToken(
                    $idEmpresa,
                    (int) $pago['id_nuvei_tarjeta'],
                    (float) $pago['monto'],
                    $descripcion,
                    'suscripcion',
                    (int) $pago['id_suscripcion'],
                    $idUsuario
                );

                $aprobado      = !empty($cargo['ok']) && ($cargo['estado'] ?? '') === 'aprobado';
                $nuevoIntentos = (int) $pago['intentos'] + 1;

                if ($aprobado) {
                    $suscRepo->updatePago((int) $pago['id'], [
                        'estado'               => 'exitoso',
                        'id_factura'           => $pago['id_factura'],
                        'nuvei_transaction_id' => $cargo['transaccion']['nuvei_transaction_id'] ?? null,
                        'nuvei_response'       => $cargo['transaccion']['response_data']        ?? null,
                        'intentos'             => $nuevoIntentos,
                    ]);
                    $suscRepo->resetIntentosFallidos((int) $pago['id_suscripcion']);

                    try {
                        $this->generarIngresoDesdeSuscripcionNuvei(
                            $idEmpresa,
                            ['id_factura' => $pago['id_factura'], 'id_recibo' => $pago['id_recibo'], 'importe' => $pago['monto']],
                            $suscLike,
                            (string) ($cargo['transaccion']['dev_reference'] ?? ''),
                            $cargo['transaccion']['authorization_code'] ?? null,
                            $idUsuario
                        );
                    } catch (\Throwable $e) {
                        error_log('[Suscripciones] Error generando Ingreso del cobro Nuvei: ' . $e->getMessage());
                    }

                    $this->notificarCobroNuveiCliente(
                        $idEmpresa, $suscLike, (float) $pago['monto'],
                        $cargo['transaccion']['nuvei_transaction_id'] ?? null,
                        $cargo['transaccion']['authorization_code']   ?? null,
                        $empresaNombre
                    );

                    $cobrados++;
                } else {
                    $agotado = $nuevoIntentos >= $maxIntentos;
                    $suscRepo->updatePago((int) $pago['id'], [
                        'estado'               => $agotado ? 'fallido' : 'pendiente',
                        'id_factura'           => $pago['id_factura'],
                        'nuvei_transaction_id' => $cargo['transaccion']['nuvei_transaction_id'] ?? null,
                        'nuvei_response'       => $cargo['transaccion']['response_data']        ?? null,
                        'intentos'             => $nuevoIntentos,
                    ]);
                    $suscRepo->incrementarIntentosFallidos((int) $pago['id_suscripcion']);

                    $this->notificarCobroNuveiRechazado($idEmpresa, $suscLike, (float) $pago['monto'], $empresaNombre, $agotado, $nuevoIntentos, $maxIntentos);

                    $rechazados++;
                }
            } catch (\Throwable $e) {
                $errores++;
                error_log('[Suscripciones] Error cobrando pago #' . $pago['id'] . ' con Nuvei: ' . $e->getMessage());
            }
        }

        $msg = "Se cobraron {$cobrados} suscripción(es) con Nuvei.";
        if ($rechazados > 0) $msg .= " ({$rechazados} rechazada(s))";
        if ($errores > 0)    $msg .= " ({$errores} con error técnico)";
        return ['registros' => $cobrados, 'mensaje' => $msg];
    }

    // ── Enviar aviso de vencimiento ───────────────────────────────────────────
    // Envía un correo a los clientes cuya suscripción vence en EXACTAMENTE N días.
    private function enviarAvisoVencimiento(int $idEmpresa, array $p): array
    {
        $diasAntes = max(0, (int)($p['dias_antes'] ?? 5));
        $asuntoTpl = trim($p['asunto'] ?? '');
        $cuerpoTpl = trim($p['cuerpo'] ?? '');

        if ($asuntoTpl === '' || $cuerpoTpl === '') {
            return ['registros' => 0, 'mensaje' => 'Debe configurar el asunto y el cuerpo del correo en la automatización.'];
        }

        $suscRepo = new SuscripcionesRepository();
        $proximas = $suscRepo->getProximasAVencer($idEmpresa, $diasAntes);
        if (empty($proximas)) {
            return ['registros' => 0, 'mensaje' => "No hay suscripciones que venzan en {$diasAntes} día(s)."];
        }

        $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $empresaNombre = trim((string)($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? ''));

        $mailService = new \App\Services\EnvioDocumentosSRIService();

        $enviadas = 0;
        $sinCorreo = 0;
        $errores   = 0;

        foreach ($proximas as $susc) {
            $email = trim((string)($susc['cliente_email'] ?? ''));
            if ($email === '') {
                $sinCorreo++;
                continue;
            }

            $nombreCliente = trim((string)($susc['cliente_nombre'] ?? 'Cliente'));
            $reemplazos    = $this->construirReemplazosAviso($susc, $empresaNombre, $diasAntes);
            // {link_tarjeta}: enlace para registrar tarjeta y activar el cobro automático
            // con Nuvei — solo se genera si la suscripción lo necesita (forma_cobro=tarjeta,
            // pasarela=nuvei, sin tarjeta registrada aún). El admin decide si lo incluye en el cuerpo.
            $reemplazos['{link_tarjeta}'] = $this->generarLinkRegistroTarjetaSiAplica($idEmpresa, $susc, $email);

            $asunto = strtr($asuntoTpl, $reemplazos);
            // El cuerpo se escribe en texto plano: respetar saltos de línea en HTML.
            $cuerpo = nl2br(htmlspecialchars(strtr($cuerpoTpl, $reemplazos), ENT_QUOTES, 'UTF-8'));
            $cuerpoHtml = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>{$cuerpo}</div>";

            try {
                $ok = $mailService->enviarAvisoSimple($idEmpresa, $email, $nombreCliente, $asunto, $cuerpoHtml, $empresaNombre);
                if ($ok) { $enviadas++; } else { $errores++; }
            } catch (\Throwable $e) {
                $errores++;
            }
        }

        $msg = "Se enviaron {$enviadas} aviso(s) de vencimiento.";
        if ($sinCorreo > 0) $msg .= " ({$sinCorreo} sin correo registrado)";
        if ($errores > 0)   $msg .= " ({$errores} con error de envío)";
        return ['registros' => $enviadas, 'mensaje' => $msg];
    }

    // ── Enviar aviso de vencimiento por WhatsApp ───────────────────────────────
    // Usa una plantilla aprobada por Meta; las variables {{1}},{{2}}... se rellenan
    // con los valores configurados (separados por "|") tras reemplazar las etiquetas.
    private function enviarAvisoVencimientoWhatsapp(int $idEmpresa, array $p): array
    {
        $diasAntes   = max(0, (int)($p['dias_antes'] ?? 5));
        $plantilla   = trim($p['plantilla_whatsapp'] ?? '');

        if ($plantilla === '') {
            return ['registros' => 0, 'mensaje' => 'Debe seleccionar la plantilla de WhatsApp en la automatización.'];
        }

        // Resolver idioma, texto del BODY y n.º de variables del cuerpo
        $plModel   = new \App\models\WhatsappPlantilla();
        $idioma    = 'es';
        $bodyText  = '';
        $numVars   = 0;
        $existe    = false;
        foreach ($plModel->getPlantillasAprobadas($idEmpresa) as $pl) {
            if ((string)$pl['nombre'] === $plantilla) {
                $idioma = (string)($pl['idioma'] ?? 'es');
                $comps  = json_decode($pl['componentes'] ?? '[]', true) ?: [];
                foreach ($comps as $c) {
                    if (strtoupper($c['type'] ?? '') === 'BODY') {
                        $bodyText = (string)($c['text'] ?? '');
                        if (preg_match_all('/{{(\d+)}}/', $bodyText, $m)) {
                            $numVars = max($numVars, (int)max($m[1]));
                        }
                    }
                }
                $existe = true;
                break;
            }
        }
        if (!$existe) {
            return ['registros' => 0, 'mensaje' => "La plantilla '{$plantilla}' no existe o no está aprobada."];
        }

        $suscRepo = new SuscripcionesRepository();
        $proximas = $suscRepo->getProximasAVencer($idEmpresa, $diasAntes);
        if (empty($proximas)) {
            return ['registros' => 0, 'mensaje' => "No hay suscripciones que venzan en {$diasAntes} día(s)."];
        }

        $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $empresaNombre = trim((string)($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? ''));

        $waService = new \App\services\WhatsappService();
        $waRepo    = new \App\repositories\modulos\WhatsappMensajeRepository();

        $enviadas   = 0;
        $sinTel     = 0;
        $errores    = 0;

        foreach ($proximas as $susc) {
            $telefono = $this->normalizarTelefono((string)($susc['cliente_telefono'] ?? ''));
            if ($telefono === '') {
                $sinTel++;
                continue;
            }

            $nombreCliente = trim((string)($susc['cliente_nombre'] ?? 'Cliente'));

            // Variables del cuerpo: se rellenan solas con el contexto, en orden fijo:
            // {{1}}=cliente, {{2}}=fecha_vencimiento, {{3}}=días, {{4}}=periodicidad, {{5}}=empresa,
            // {{6}}=link de registro de tarjeta (solo si la plantilla aprobada en Meta define una
            // sexta variable; si no, simplemente no se usa — numVars se calcula del texto real).
            $ctx = [
                $nombreCliente,
                $this->formatearFecha((string)($susc['proximo_cobro'] ?? '')),
                (string)$diasAntes,
                (string)($susc['periodicidad_nombre'] ?? ''),
                $empresaNombre,
                $this->generarLinkRegistroTarjetaSiAplica($idEmpresa, $susc, ''),
            ];

            $valoresFinales = [];
            $components     = [];
            if ($numVars > 0) {
                $params = [];
                for ($i = 0; $i < $numVars; $i++) {
                    $valor = $ctx[$i] ?? '';
                    $valoresFinales[] = $valor;
                    $params[] = ['type' => 'text', 'text' => $valor];
                }
                $components[] = ['type' => 'body', 'parameters' => $params];
            }

            try {
                $resp = $waService->sendTemplateMessage($idEmpresa, $telefono, $plantilla, $idioma, $components);
                if ($resp['success'] ?? false) {
                    $enviadas++;
                    // Registrar en el Chat Center (no debe interrumpir el envío si falla)
                    try {
                        $metaMsgId = $resp['data']['messages'][0]['id'] ?? null;

                        // Texto de la plantilla con las variables reemplazadas, para el historial
                        $templateText = $bodyText;
                        foreach ($valoresFinales as $i => $valor) {
                            $templateText = str_replace('{{' . ($i + 1) . '}}', $valor, $templateText);
                        }

                        $idChat = $waRepo->getOrCreateChat(
                            $idEmpresa, $telefono, $nombreCliente,
                            'Aviso vencimiento: ' . $plantilla, false
                        );
                        $waRepo->saveMessage(
                            $idEmpresa, $idChat, 'OUT', $telefono, 'template',
                            [
                                'template'      => $plantilla,
                                'variables'     => $valoresFinales,
                                'template_text' => $templateText,
                            ],
                            $metaMsgId, 'sent'
                        );
                    } catch (\Throwable $e) {
                        error_log('[Aviso WhatsApp] Enviado pero no registrado en chat: ' . $e->getMessage());
                    }
                } else {
                    $errores++;
                }
            } catch (\Throwable $e) {
                $errores++;
            }
        }

        $msg = "Se enviaron {$enviadas} aviso(s) de vencimiento por WhatsApp.";
        if ($sinTel > 0)  $msg .= " ({$sinTel} sin teléfono registrado)";
        if ($errores > 0) $msg .= " ({$errores} con error de envío)";
        return ['registros' => $enviadas, 'mensaje' => $msg];
    }

    /** Etiquetas comunes para los avisos (correo y WhatsApp). */
    private function construirReemplazosAviso(array $susc, string $empresaNombre, int $diasAntes): array
    {
        return [
            '{cliente}'           => trim((string)($susc['cliente_nombre'] ?? 'Cliente')),
            '{empresa}'           => $empresaNombre,
            '{fecha_vencimiento}' => $this->formatearFecha((string)($susc['proximo_cobro'] ?? '')),
            '{dias}'              => (string)$diasAntes,
            '{periodicidad}'      => (string)($susc['periodicidad_nombre'] ?? ''),
        ];
    }

    /**
     * Genera el enlace público de registro de tarjeta (Add Card Nuvei) para una
     * suscripción, solo si aplica: forma_cobro='tarjeta', pasarela='nuvei' y aún
     * sin tarjeta vinculada. Retorna cadena vacía si no aplica o si falla —
     * nunca debe interrumpir el envío del aviso de vencimiento.
     *
     * Usa url_absoluta() porque este código corre desde el cron (sin contexto
     * HTTP): requiere que config/local.php tenga 'app_url' configurado.
     */
    private function generarLinkRegistroTarjetaSiAplica(int $idEmpresa, array $susc, string $email): string
    {
        if (($susc['forma_cobro'] ?? '') !== 'tarjeta'
            || ($susc['pasarela_tarjeta'] ?? '') !== 'nuvei'
            || !empty($susc['id_nuvei_tarjeta'])
            || empty($susc['id_cliente'])
            || empty($susc['id'])
        ) {
            return '';
        }

        try {
            $nuvei = new \App\Services\NuveiService(new \App\repositories\NuveiRepository());
            $sol   = $nuvei->prepararSolicitudTarjeta($idEmpresa, [
                'id_cliente'    => (int) $susc['id_cliente'],
                'modulo'        => 'suscripcion',
                'id_referencia' => (int) $susc['id'],
                'email'         => $email,
            ]);
            if (!$sol['ok']) {
                return '';
            }
            return function_exists('url_absoluta') ? url_absoluta('/nuvei/tarjeta/' . $sol['token']) : '';
        } catch (\Throwable $e) {
            error_log('[Suscripciones] Error generando link de registro de tarjeta: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Registra el cobro aprobado de Nuvei como un Ingreso real (con su asiento
     * contable vía IngresoService::crear()) — mismo patrón que
     * FacturaVentaService::generarIngresoDesdeNuvei() para el Checkout de
     * Facturas de Venta, adaptado para que aplique tanto a facturas como a
     * recibos de venta (según cuál haya generado SuscripcionFacturacionService).
     * Idempotente: vincula la transacción al ingreso creado.
     *
     * @param array $res Resultado de SuscripcionFacturacionService::generarUnPeriodo()
     *                   (trae id_factura, id_recibo, importe).
     */
    private function generarIngresoDesdeSuscripcionNuvei(int $idEmpresa, array $res, array $susc, string $devReference, ?string $authCode, int $idUsuario): ?int
    {
        $esFactura = !empty($res['id_factura']);
        $idDoc     = $esFactura ? (int) $res['id_factura'] : (int) ($res['id_recibo'] ?? 0);
        if ($idDoc <= 0) {
            return null;
        }

        $db    = \App\core\Database::getConnection();
        $tabla = $esFactura ? 'ventas_cabecera' : 'recibos_venta_cabecera';
        $stDoc = $db->prepare(
            "SELECT id, id_cliente, id_punto_emision, establecimiento, punto_emision, secuencial, importe_total
             FROM {$tabla} WHERE id = ? AND id_empresa = ? AND eliminado = false"
        );
        $stDoc->execute([$idDoc, $idEmpresa]);
        $doc = $stDoc->fetch(\PDO::FETCH_ASSOC);
        if (!$doc) {
            return null;
        }

        // Forma de cobro tipo NUVEI de la empresa (misma que usa Facturas de Venta)
        $nvRepo       = new \App\repositories\NuveiRepository();
        $formaTarjeta = $nvRepo->getFormaCobroNuvei($idEmpresa);
        if (!$formaTarjeta) {
            return null;
        }

        // Punto de emisión del documento
        $stPunto = $db->prepare(
            "SELECT p.id, e.codigo AS establecimiento, p.codigo_punto AS punto, p.id_establecimiento
             FROM empresa_punto_emision p
             JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
             WHERE p.id = ? AND p.id_empresa = ? AND p.eliminado = false"
        );
        $stPunto->execute([(int) $doc['id_punto_emision'], $idEmpresa]);
        $punto = $stPunto->fetch(\PDO::FETCH_ASSOC);
        if (!$punto) {
            return null;
        }

        $tipoDocIngreso = $esFactura ? 'FACTURA' : 'RECIBO';

        // Saldo anterior (por si ya hubiera cobros previos del mismo documento)
        $stSaldo = $db->prepare(
            "SELECT COALESCE(SUM(id2.monto_cobrado), 0)
             FROM ingresos_detalle id2
             INNER JOIN ingresos_cabecera ic2 ON id2.id_ingreso = ic2.id
             WHERE id2.tipo_documento = ?
               AND id2.id_referencia_documento = ?
               AND ic2.estado != 'anulado'
               AND ic2.eliminado = false"
        );
        $stSaldo->execute([$tipoDocIngreso, $idDoc]);
        $totalCobrado  = (float) $stSaldo->fetchColumn();
        $saldoAnterior = round((float) $doc['importe_total'] - $totalCobrado, 2);

        $monto  = round((float) $res['importe'], 2);
        $numDoc = $doc['establecimiento'] . '-' . $doc['punto_emision'] . '-' . $doc['secuencial'];

        // Se abre la transacción ANTES de calcular el secuencial y se mantiene hasta el INSERT
        // final (IngresoService::crear()): el lock de obtenerSiguienteSecuencial() se libera
        // solo al COMMIT/ROLLBACK (CLAUDE.md §8).
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
        $secuencialService = new SecuencialService();
        $secRes            = $secuencialService->obtenerSiguienteSecuencial((int) $punto['id'], 'Ingresos');
        $numeroIngreso      = str_pad((string) $punto['establecimiento'], 3, '0', STR_PAD_LEFT) . '-'
                            . str_pad((string) $punto['punto'], 3, '0', STR_PAD_LEFT) . '-'
                            . str_pad((string) $secRes['secuencial'], 9, '0', STR_PAD_LEFT);

        $payload = [
            'id_empresa'          => $idEmpresa,
            'id_establecimiento'  => (int) ($punto['id_establecimiento'] ?? 0),
            'id_punto_emision'    => (int) $punto['id'],
            'id_cliente'          => (int) $doc['id_cliente'],
            'id_usuario'          => $idUsuario,
            'fecha_emision'       => date('Y-m-d'),
            'establecimiento'     => $punto['establecimiento'],
            'punto_emision'       => $punto['punto'],
            'secuencial'          => $secRes['formateado'],
            'numero_ingreso'      => $numeroIngreso,
            'tipo_ingreso'        => $esFactura ? 'FACTURA_VENTA' : 'RECIBO_VENTA',
            'id_ingreso_concepto' => null,
            'monto_total'         => $monto,
            'observaciones'       => 'Cobro automático suscripción #' . $susc['id'] . ' (Nuvei) — autorización ' . ($authCode ?? ''),
            'recibo_de'           => trim((string) ($susc['cliente_nombre'] ?? '')),
            'id_recibo_cliente'   => (int) $doc['id_cliente'],
            'detalles'            => [[
                'tipo_documento'          => $tipoDocIngreso,
                'id_referencia_documento' => $idDoc,
                'numero_documento'        => $numDoc,
                'descripcion'             => 'Cobro de ' . strtolower($tipoDocIngreso) . ' ' . $numDoc,
                'monto_documento'         => (float) $doc['importe_total'],
                'saldo_anterior'          => $saldoAnterior,
                'monto_cobrado'           => $monto,
                'saldo_actual'            => max(0.0, $saldoAnterior - $monto),
            ]],
            'pagos' => [[
                'id_forma_cobro'          => (int) $formaTarjeta['id'],
                'monto'                   => $monto,
                'referencia'              => $authCode,
                'tipo_operacion_bancaria' => null,
                'numero_cheque'           => null,
            ]],
        ];

        $ingresoService = new \App\Services\modulos\IngresoService(
            new \App\repositories\modulos\IngresoRepository(),
            new \App\Rules\modulos\IngresoRules(),
            new LogSistemaService()
        );

        $idIngreso = $ingresoService->crear($payload);

        if ($devReference !== '') {
            $nvRepo->vincularIngreso($devReference, (int) $idIngreso);
        }

        if ($managedTransaction) {
            $db->commit();
        }

        return (int) $idIngreso;
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Notifica al CLIENTE que su cobro recurrente con Nuvei fue aprobado —
     * requisito explícito de Nuvei de confirmar cada transacción. Nunca debe
     * interrumpir la generación de facturación si el envío falla.
     */
    private function notificarCobroNuveiCliente(int $idEmpresa, array $susc, float $monto, ?string $transId, ?string $authCode, string $empresaNombre): void
    {
        $email = trim((string) ($susc['cliente_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            (new \App\Services\EnvioDocumentosSRIService())->enviarConfirmacionPagoNuvei(
                $idEmpresa,
                $email,
                trim((string) ($susc['cliente_nombre'] ?? 'Cliente')),
                $empresaNombre,
                $monto,
                'Suscripción #' . $susc['id'] . ' — ' . ($susc['periodicidad_nombre'] ?? 'período'),
                true,
                (string) ($transId ?? ''),
                (string) ($authCode ?? '')
            );
        } catch (\Throwable $e) {
            error_log('[Suscripciones] Error enviando confirmación de cobro Nuvei al cliente: ' . $e->getMessage());
        }
    }

    /**
     * Notifica a la EMPRESA (correo de contacto, empresas.mail — mismo patrón
     * que DeclaracionIvaHandler::avisarIvaAPagar()) que el cobro automático de
     * una suscripción fue rechazado por Nuvei, para que hagan seguimiento
     * manual con el cliente. Nunca debe interrumpir la generación de facturación.
     */
    /**
     * Notifica el rechazo de un cobro de suscripción a AMBAS partes:
     *  - Cliente: correo de "pago rechazado" (mismo requisito de Nuvei que ya
     *    se usa para Checkout — confirmar cada transacción resuelta).
     *  - Empresa: detalle completo + si se reintentará automáticamente al día
     *    siguiente o si ya se agotaron los intentos (fallido definitivo).
     */
    private function notificarCobroNuveiRechazado(int $idEmpresa, array $susc, float $monto, string $empresaNombre, bool $agotado, int $intentoActual, int $maxIntentos): void
    {
        $descripcion = 'Suscripción #' . $susc['id'] . ' — ' . ($susc['periodicidad_nombre'] ?? 'período');

        $mensajeRechazoCliente = $agotado
            ? 'No pudimos procesar el cobro automático de tu suscripción después de varios intentos. '
              . 'Por favor contáctanos para regularizar tu pago.'
            : 'No pudimos procesar el cobro automático de tu suscripción. Reintentaremos automáticamente '
              . 'en los próximos días; no necesitas hacer nada.';

        $email = trim((string) ($susc['cliente_email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                (new \App\Services\EnvioDocumentosSRIService())->enviarConfirmacionPagoNuvei(
                    $idEmpresa, $email, trim((string) ($susc['cliente_nombre'] ?? 'Cliente')), $empresaNombre,
                    $monto, $descripcion, false, '', '', $mensajeRechazoCliente
                );
            } catch (\Throwable $e) {
                error_log('[Suscripciones] Error enviando aviso de cobro rechazado al cliente: ' . $e->getMessage());
            }
        }

        try {
            $empresaArr = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $destino    = trim((string) ($empresaArr['mail'] ?? ''));
            if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $estadoTexto = $agotado
                ? 'Se agotaron los ' . $maxIntentos . ' intentos permitidos: el cobro queda marcado como <strong>fallido definitivo</strong>. '
                  . 'Gestiónalo manualmente (contacta al cliente, cóbralo por otro medio, o pide que registre una tarjeta nueva).'
                : 'Se reintentará automáticamente en la próxima corrida de esta automatización (intento ' . ($intentoActual + 1) . ' de ' . $maxIntentos . ').';

            $asunto = 'Cobro de suscripción rechazado — Suscripción #' . $susc['id'];
            $cuerpo = '<div style="font-family:Arial,sans-serif;line-height:1.6;">'
                . '<p>El cobro automático con Nuvei de la siguiente suscripción fue <strong>rechazado</strong>:</p>'
                . '<ul>'
                . '<li><strong>Suscripción:</strong> #' . (int) $susc['id'] . '</li>'
                . '<li><strong>Cliente:</strong> ' . htmlspecialchars((string) ($susc['cliente_nombre'] ?? '')) . '</li>'
                . '<li><strong>Monto:</strong> $' . number_format($monto, 2) . '</li>'
                . '<li><strong>Intento:</strong> ' . $intentoActual . ' de ' . $maxIntentos . '</li>'
                . '<li><strong>Fecha:</strong> ' . date('d-m-Y H:i:s') . '</li>'
                . '</ul>'
                . '<p>' . $estadoTexto . '</p>'
                . '</div>';

            (new \App\Services\EnvioDocumentosSRIService())->enviarAvisoSimple(
                $idEmpresa, $destino, $empresaNombre, $asunto, $cuerpo, $empresaNombre
            );
        } catch (\Throwable $e) {
            error_log('[Suscripciones] Error enviando aviso de cobro rechazado a la empresa: ' . $e->getMessage());
        }
    }

    /** Normaliza un teléfono a formato internacional Ecuador (593...). Vacío si no es válido. */
    private function normalizarTelefono(string $telefono): string
    {
        $tel = preg_replace('/\D/', '', $telefono);
        if ($tel === '' || strlen($tel) < 9) return '';
        if (str_starts_with($tel, '593')) return $tel;
        if (str_starts_with($tel, '0'))   return '593' . substr($tel, 1);
        return '593' . $tel;
    }

    /** Formatea una fecha Y-m-d a d-m-Y. */
    private function formatearFecha(string $fecha): string
    {
        if ($fecha === '') return '';
        try {
            return (new \DateTime($fecha))->format('d-m-Y');
        } catch (\Throwable) {
            return $fecha;
        }
    }
}
