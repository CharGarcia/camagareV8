<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\VehiculoMantenimientoRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Avisos al cliente del próximo mantenimiento por kilometraje (cambio de aceite de motor,
 * de caja y demás servicios del catálogo).
 *
 * Cuándo se avisa: el sistema solo conoce el km cuando el vehículo llega, así que estima
 * el km de HOY con las lecturas del vehículo (VehiculoMantenimientoRepository::sqlEstimacion:
 * último km + km/día × días desde la última lectura). Se avisa cuando faltan los km de
 * aviso del servicio o menos (*próximo*) o ya se pasó (*vencido*).
 *
 *  - Automático (Automatizaciones → Car-Wash → Aviso de próximo mantenimiento): un primer
 *    aviso por cambio y canal; si sigue vencido, recordatorios cada N días hasta M veces.
 *    Un solo mensaje por vehículo con todos sus servicios por avisar.
 *  - Manual: desde la ficha del vehículo (pestaña Mantenimiento), por correo o WhatsApp.
 *
 * Cada aviso queda en carwash_recordatorios (una fila por servicio avisado), junto con los
 * recordatorios de cita: historial y control de "ya avisado".
 * Destinatario: correo / teléfono del VEHÍCULO y, si no tiene, los del cliente de su
 * última orden de Car-Wash (misma regla que los recordatorios de cita).
 */
class MantenimientoAvisoService
{
    /** Recorrido supuesto de un vehículo con una sola lectura de km. */
    public const KM_MES_DEFECTO = 1000;

    public const ASUNTO_DEFECTO  = 'Mantenimiento de su vehículo {placa} - {empresa}';
    public const MENSAJE_DEFECTO = "Hola {cliente}:\n\nLe recordamos que a su vehículo {placa} le corresponde:\n{servicios}\n\nLe esperamos en {empresa}.";
    public const ETIQUETAS       = '{cliente} {placa} {marca} {servicios} {km_estimado} {empresa}';

    private VehiculoMantenimientoRepository $repo;
    private CarWashRecordatorioService $rec;
    private LogSistemaService $log;

    /** Motivo del último envío fallido (lo muestra el log de Automatizaciones). */
    public ?string $ultimoError = null;

    public function __construct(?VehiculoMantenimientoRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo = $repo ?? new VehiculoMantenimientoRepository();
        $this->log  = $log ?? new LogSistemaService();
        $this->rec  = new CarWashRecordatorioService(null, $this->log);
    }

    public function disponible(): bool
    {
        return $this->repo->avisosDisponible();
    }

    public static function kmDia(int $kmMes): float
    {
        return max(0, $kmMes) / 30;
    }

    // ─── Quién recibe aviso ───────────────────────────────────────────────────

    /**
     * Vehículos con mantenimientos por avisar automáticamente por $canal, agrupados:
     * [ {contacto…, km_est, items: [ {id_vehiculo_mantenimiento, id_servicio, servicio,
     *   proximo_km, faltan_km, estado, aviso_n}, … ]}, … ].
     *
     * $p: reavisar_cada_dias (0 = solo el primer aviso), max_reavisos, km_mes_defecto,
     *     servicios (nombres separados por coma; vacío = todos los activos).
     */
    public function pendientes(int $idEmpresa, string $canal, array $p = []): array
    {
        if (!$this->disponible()) return [];
        $reavisar = max(0, (int) ($p['reavisar_cada_dias'] ?? 7));
        $maxRe    = max(0, (int) ($p['max_reavisos'] ?? 3));
        $kmMes    = (int) ($p['km_mes_defecto'] ?? self::KM_MES_DEFECTO) ?: self::KM_MES_DEFECTO;
        $ids      = $this->idsServicios($idEmpresa, (string) ($p['servicios'] ?? ''));
        if ($ids === null) return []; // se nombraron servicios y ninguno existe

        $ahora  = time();
        $grupos = [];
        foreach ($this->repo->candidatosAviso($idEmpresa, $canal, self::kmDia($kmMes), $ids) as $r) {
            $faltan = (int) $r['faltan_km'];
            $estado = $faltan <= 0 ? 'vencido' : 'proximo';
            $n = (int) $r['avisos_enviados'];
            if ($n === 0) {
                $avisoN = 1;
            } elseif ($estado === 'vencido' && $reavisar > 0 && ($n - 1) < $maxRe
                && !empty($r['ultimo_aviso']) && strtotime((string) $r['ultimo_aviso']) <= $ahora - $reavisar * 86400 + 3600) {
                // +1 h de holgura: la automatización diaria no debe saltarse un día por minutos.
                $avisoN = $n + 1;
            } else {
                continue;
            }
            $idV = (int) $r['id_vehiculo'];
            $grupos[$idV] ??= $this->contacto($r) + ['km_est' => (int) $r['km_est'], 'items' => []];
            $grupos[$idV]['items'][] = [
                'id_vehiculo_mantenimiento' => (int) $r['id_vehiculo_mantenimiento'],
                'id_servicio' => (int) $r['id_servicio'],
                'servicio'    => (string) $r['servicio'],
                'proximo_km'  => (int) $r['proximo_km'],
                'faltan_km'   => $faltan,
                'estado'      => $estado,
                'aviso_n'     => $avisoN,
            ];
        }
        return array_values($grupos);
    }

    /**
     * Aviso manual desde la ficha: los servicios próximos o vencidos (si no hay, todos los
     * que tienen próximo cambio), con el asunto y el mensaje por defecto ya armados.
     */
    public function grupoManual(int $idVehiculo, int $idEmpresa): array
    {
        if (!$this->disponible()) {
            throw new Exception('Falta aplicar el SQL de avisos de mantenimiento (database/migrations/20261031_mantenimientos_avisos.sql).');
        }
        $c = $this->repo->contactoVehiculo($idVehiculo, $idEmpresa);
        if (!$c) throw new Exception('Vehículo no encontrado.');
        $todos = []; $urgentes = []; $kmEst = null;
        foreach ($this->repo->mantenimientosConProximo($idVehiculo, $idEmpresa, self::kmDia(self::KM_MES_DEFECTO)) as $r) {
            $kmEst = (int) $r['km_est'];
            $faltan = (int) $r['faltan_km'];
            $it = [
                'id_vehiculo_mantenimiento' => (int) $r['id_vehiculo_mantenimiento'], 'id_servicio' => (int) $r['id_servicio'],
                'servicio' => (string) $r['servicio'], 'proximo_km' => (int) $r['proximo_km'], 'faltan_km' => $faltan,
                'estado' => $faltan <= 0 ? 'vencido' : ($faltan <= (int) $r['aviso_km'] ? 'proximo' : 'al_dia'), 'aviso_n' => null,
            ];
            $todos[] = $it;
            if ($it['estado'] !== 'al_dia') $urgentes[] = $it;
        }
        if (!$todos) throw new Exception('El vehículo no tiene próximos cambios registrados.');
        $g = $this->contacto($c) + ['km_est' => $kmEst, 'items' => $urgentes ?: $todos];
        $empresa = $this->rec->empresaNombre($idEmpresa);
        $dest = $this->rec->destinatarios($g);
        return $g + [
            'correo_destino'  => $dest['correo'],
            'telefono_destino'=> $dest['telefono'],
            'nombre_destino'  => $dest['nombre'],
            'asunto_defecto'  => $this->aplicarEtiquetas(self::ASUNTO_DEFECTO, $g, $empresa),
            'mensaje_defecto' => $this->aplicarEtiquetas(self::MENSAJE_DEFECTO, $g, $empresa),
        ];
    }

    private function contacto(array $r): array
    {
        return array_intersect_key($r, array_flip([
            'id_vehiculo', 'placa', 'marca', 'vehiculo_propietario', 'vehiculo_correo', 'vehiculo_telefono',
            'id_cliente', 'cliente_nombre', 'cliente_email', 'cliente_telefono',
        ]));
    }

    /** Nombres de servicios separados por coma → ids (null si se nombraron y ninguno existe). */
    private function idsServicios(int $idEmpresa, string $nombres): ?array
    {
        $buscar = array_filter(array_map(fn($n) => mb_strtolower(trim($n)), explode(',', $nombres)));
        if (!$buscar) return [];
        $ids = [];
        foreach ($this->repo->getServicios($idEmpresa) as $s) {
            if (in_array(mb_strtolower(trim((string) $s['nombre'])), $buscar, true)) $ids[] = (int) $s['id'];
        }
        return $ids ?: null;
    }

    // ─── Texto del aviso ──────────────────────────────────────────────────────

    /** "- Aceite de motor: a los 85.000 km (faltan 300 km)", una línea por servicio. */
    public static function textoServicios(array $items, bool $unaLinea = false): string
    {
        $partes = array_map(function ($it) use ($unaLinea) {
            $km = VehiculoMantenimientoService::km((int) $it['proximo_km']);
            $f = (int) $it['faltan_km'];
            $estado = $f <= 0 ? 'ya le toca' : 'faltan aprox. ' . VehiculoMantenimientoService::km($f);
            return $unaLinea ? "{$it['servicio']} a los {$km}" : "- {$it['servicio']}: a los {$km} ({$estado})";
        }, $items);
        return implode($unaLinea ? ', ' : "\n", $partes);
    }

    public function aplicarEtiquetas(string $plantilla, array $g, string $empresa): string
    {
        return strtr($plantilla, [
            '{cliente}'     => $this->rec->destinatarios($g)['nombre'] ?: 'Cliente',
            '{placa}'       => (string) ($g['placa'] ?? ''),
            '{marca}'       => (string) ($g['marca'] ?? ''),
            '{servicios}'   => self::textoServicios($g['items'] ?? []),
            '{km_estimado}' => isset($g['km_est']) ? VehiculoMantenimientoService::km((int) $g['km_est']) : '',
            '{empresa}'     => $empresa,
        ]);
    }

    // ─── Envío ────────────────────────────────────────────────────────────────

    /**
     * Envía el aviso por correo y lo registra (una fila por servicio).
     * @return array{ok:bool, mensaje:string}
     */
    public function enviarCorreo(array $g, int $idEmpresa, int $idUsuario, string $destinatarios, string $asunto, string $mensaje, string $origen): array
    {
        $empresa = $this->rec->empresaNombre($idEmpresa);
        $destinatarios = trim($destinatarios) !== '' ? trim($destinatarios) : $this->rec->destinatarios($g)['correo'];
        if ($destinatarios === '') {
            throw new Exception('Ni el vehículo ni el cliente tienen correo registrado. Escriba un correo de destino.');
        }
        foreach (array_map('trim', explode(',', $destinatarios)) as $mail) {
            if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) throw new Exception("El correo \"{$mail}\" no es válido.");
        }
        $asunto  = trim($asunto) !== '' ? $asunto : $this->aplicarEtiquetas(self::ASUNTO_DEFECTO, $g, $empresa);
        $mensaje = trim($mensaje) !== '' ? $mensaje : $this->aplicarEtiquetas(self::MENSAJE_DEFECTO, $g, $empresa);
        $html = "<div style='font-family:Arial,sans-serif;line-height:1.5;color:#333;'>"
              . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) . '</div>';

        $ok = false; $detalle = null;
        try {
            $ok = (new \App\Services\EnvioDocumentosSRIService())->enviarAvisoSimple(
                $idEmpresa, $destinatarios, $this->rec->destinatarios($g)['nombre'] ?: 'Cliente', $asunto, $html, $empresa
            );
            if (!$ok) $detalle = 'No se pudo enviar el correo (revise la configuración de correo de la empresa).';
        } catch (\Throwable $e) {
            $detalle = 'Error al enviar el correo: ' . $e->getMessage();
        }
        if (!$ok) $this->ultimoError = $detalle;
        $this->registrar($g, $idEmpresa, $idUsuario, 'correo', $destinatarios, $asunto, $mensaje, $ok, $detalle, $origen);
        return ['ok' => $ok, 'mensaje' => $ok ? 'Aviso enviado a ' . $destinatarios . '.' : (string) $detalle];
    }

    /**
     * Aviso automático con una plantilla de WhatsApp aprobada por Meta. Variables del cuerpo,
     * EN ORDEN: {{1}} cliente, {{2}} placa, {{3}} servicios ("Aceite de motor a los 85.000 km,
     * …"), {{4}} empresa, {{5}} km estimado de hoy.
     */
    public function enviarWhatsappPlantilla(array $g, int $idEmpresa, int $idUsuario, string $plantilla, string $idioma, int $numVars): bool
    {
        $tel = $this->rec->destinatarios($g)['telefono'];
        if ($tel === '') return false;
        $valores = [
            $this->rec->destinatarios($g)['nombre'] ?: 'Cliente',
            (string) ($g['placa'] ?? ''),
            self::textoServicios($g['items'], true),
            $this->rec->empresaNombre($idEmpresa),
            isset($g['km_est']) ? VehiculoMantenimientoService::km((int) $g['km_est']) : '',
        ];
        $components = [];
        if ($numVars > 0) {
            $params = [];
            for ($i = 0; $i < $numVars; $i++) $params[] = ['type' => 'text', 'text' => $valores[$i] ?? ''];
            $components[] = ['type' => 'body', 'parameters' => $params];
        }
        $ok = false; $detalle = null;
        try {
            $resp = (new \App\services\WhatsappService())->sendTemplateMessage($idEmpresa, $tel, $plantilla, $idioma, $components);
            $ok = (bool) ($resp['success'] ?? false);
            if (!$ok) $detalle = 'WhatsApp rechazó el envío: ' . (string) ($resp['message'] ?? json_encode($resp['data']['error'] ?? $resp, JSON_UNESCAPED_UNICODE));
            if ($ok) {
                // Registrar en el Chat Center (si falla, el envío igual cuenta).
                try {
                    $waRepo = new \App\repositories\modulos\WhatsappMensajeRepository();
                    $idChat = $waRepo->getOrCreateChat($idEmpresa, $tel, $valores[0], 'Mantenimiento: ' . $plantilla, false);
                    $waRepo->saveMessage($idEmpresa, $idChat, 'OUT', $tel, 'template',
                        ['template' => $plantilla, 'variables' => array_slice($valores, 0, $numVars), 'template_text' => 'Aviso de mantenimiento'],
                        $resp['data']['messages'][0]['id'] ?? null, 'sent');
                } catch (\Throwable $e) {
                    error_log('[Mantenimiento WhatsApp] Enviado pero no registrado en chat: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $detalle = 'Error al enviar por WhatsApp: ' . $e->getMessage();
        }
        if (!$ok) $this->ultimoError = $detalle;
        $this->registrar($g, $idEmpresa, $idUsuario, 'whatsapp', $tel, null,
            'Plantilla ' . $plantilla . ': ' . implode(' | ', array_slice($valores, 0, max(1, $numVars))), $ok, $detalle, 'automatico');
        return $ok;
    }

    /** Aviso por WhatsApp abierto a mano (wa.me): solo deja constancia. */
    public function registrarWhatsappManual(int $idVehiculo, int $idEmpresa, int $idUsuario, string $telefono, string $mensaje): void
    {
        $g = $this->grupoManual($idVehiculo, $idEmpresa);
        $tel = CarWashRecordatorioService::normalizarTelefono($telefono !== '' ? $telefono : $g['telefono_destino']);
        if ($tel === '') throw new Exception('Ni el vehículo ni el cliente tienen teléfono registrado. Escriba un número.');
        $this->registrar($g, $idEmpresa, $idUsuario, 'whatsapp', $tel, null, $mensaje, true, 'Abierto en WhatsApp para envío manual.', 'manual');
    }

    /** Una fila por servicio avisado, en una sola transacción, más la auditoría. */
    private function registrar(array $g, int $idEmpresa, int $idUsuario, string $canal, string $destino, ?string $asunto, ?string $mensaje, bool $ok, ?string $detalle, string $origen): void
    {
        $db = $this->repo->getDb();
        $propia = !$db->inTransaction();
        if ($propia) $db->beginTransaction();
        try {
            $ids = [];
            foreach ($g['items'] as $it) {
                $ids[] = $this->repo->registrarAviso([
                    'id_empresa' => $idEmpresa, 'id_vehiculo' => (int) $g['id_vehiculo'], 'id_cliente' => (int) ($g['id_cliente'] ?? 0),
                    'id_vehiculo_mantenimiento' => $it['id_vehiculo_mantenimiento'], 'id_servicio' => $it['id_servicio'],
                    'proximo_km' => $it['proximo_km'], 'aviso_n' => $origen === 'automatico' ? $it['aviso_n'] : null,
                    'canal' => $canal, 'destinatario' => $destino, 'asunto' => $asunto, 'mensaje' => $mensaje,
                    'estado' => $ok ? 'enviado' : 'error', 'detalle' => $detalle, 'origen' => $origen, 'id_usuario' => $idUsuario,
                ]);
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'AVISO_MANTENIMIENTO', 'carwash_recordatorios', $ids[0] ?? null, null, [
                'id_vehiculo' => (int) $g['id_vehiculo'], 'canal' => $canal, 'destinatario' => $destino, 'estado' => $ok ? 'enviado' : 'error',
                'origen' => $origen, 'servicios' => array_map(fn($i) => $i['servicio'] . ' ' . $i['proximo_km'], $g['items']),
            ]);
            if ($propia) $db->commit();
        } catch (\Throwable $e) {
            if ($propia && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function avisosVehiculo(int $idVehiculo, int $idEmpresa): array
    {
        return $this->repo->avisosVehiculo($idVehiculo, $idEmpresa);
    }
}
