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

                    // 2. Avanzar el próximo cobro
                    $suscRepo->updateProximoCobro($idSusc, $nuevoProximo);

                    // 3. Registrar el pago con el documento generado
                    $suscRepo->insertPago([
                        'id_suscripcion' => $idSusc,
                        'id_empresa'     => $idEmpresa,
                        'id_factura'     => $res['id_factura'],
                        'id_recibo'      => $res['id_recibo'],
                        'fecha_cobro'    => $hoy,
                        'monto'          => $res['importe'],
                        'estado'         => 'exitoso',
                        'id_usuario'     => $idUsuario,
                    ]);

                    $generadas++;
                    $proximo = $nuevoProximo;
                } catch (\Throwable $e) {
                    // La factura falló: NO se avanzó la fecha → se reintenta en la próxima corrida.
                    $errores++;
                    break; // pasar a la siguiente suscripción
                }
            }
        }

        $msg = "Se generaron {$generadas} documento(s) de suscripciones.";
        if ($errores > 0) $msg .= " ({$errores} suscripción(es) con error)";
        return ['registros' => $generadas, 'mensaje' => $msg];
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
            // {{1}}=cliente, {{2}}=fecha_vencimiento, {{3}}=días, {{4}}=periodicidad, {{5}}=empresa
            $ctx = [
                $nombreCliente,
                $this->formatearFecha((string)($susc['proximo_cobro'] ?? '')),
                (string)$diasAntes,
                (string)($susc['periodicidad_nombre'] ?? ''),
                $empresaNombre,
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
