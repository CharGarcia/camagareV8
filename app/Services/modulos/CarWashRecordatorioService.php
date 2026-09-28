<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CarWashRecordatorioRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Recordatorios de la próxima cita de Car-Wash.
 *
 * La cita sale de la orden (carwash_ordenes.proxima_cita). Se avisa:
 *  - a mano, desde la ficha del vehículo (pestaña Recordatorios): por correo (lo envía
 *    el sistema) o por WhatsApp (se abre WhatsApp con el mensaje; el sistema lo registra);
 *  - automáticamente, con la acción "Car-Wash → Recordatorio de próxima cita" de
 *    Automatizaciones (Handlers\CarWashRecordatorioHandler): correo o plantilla de WhatsApp.
 * Todo envío queda en carwash_recordatorios (historial + evita duplicar el automático).
 *
 * Destinatario: correo / teléfono del VEHÍCULO y, si no tiene, los del CLIENTE de la orden.
 */
class CarWashRecordatorioService
{
    public const ASUNTO_DEFECTO  = 'Recordatorio de su próxima cita - {empresa}';
    public const MENSAJE_DEFECTO = "Hola {cliente}:\n\nLe recordamos que su vehículo {placa} tiene su próxima cita en {empresa} el {fecha_cita}.\n\n¡Le esperamos!";
    public const ETIQUETAS       = '{cliente} {placa} {marca} {fecha_cita} {empresa} {orden}';

    private CarWashRecordatorioRepository $repo;
    private LogSistemaService $log;

    public function __construct(?CarWashRecordatorioRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo = $repo ?? new CarWashRecordatorioRepository();
        $this->log  = $log ?? new LogSistemaService();
    }

    public function tablaDisponible(): bool
    {
        return $this->repo->existeTabla();
    }

    /**
     * Citas del vehículo con los datos listos para la pestaña Recordatorios: destinatarios
     * por defecto, mensaje armado con la plantilla por defecto y si la cita ya pasó.
     */
    public function citasVehiculo(int $idVehiculo, int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $empresa = $this->empresaNombre($idEmpresa);
        $hoy = date('Y-m-d');
        return array_map(function (array $c) use ($empresa, $hoy) {
            $dest = $this->destinatarios($c);
            $c['correo_destino']   = $dest['correo'];
            $c['telefono_destino'] = $dest['telefono'];
            $c['nombre_destino']   = $dest['nombre'];
            $c['asunto_defecto']   = $this->aplicarEtiquetas(self::ASUNTO_DEFECTO, $c, $empresa);
            $c['mensaje_defecto']  = $this->aplicarEtiquetas(self::MENSAJE_DEFECTO, $c, $empresa);
            $c['vencida']          = substr((string) $c['proxima_cita'], 0, 10) < $hoy;
            return $c;
        }, $this->repo->citasPorVehiculo($idVehiculo, $idEmpresa, $idUsuarioFiltro));
    }

    public function historialVehiculo(int $idVehiculo, int $idEmpresa): array
    {
        return $this->repo->historialPorVehiculo($idVehiculo, $idEmpresa);
    }

    /**
     * Envía por correo el recordatorio de la cita de una orden y lo registra.
     * @return array{ok:bool, mensaje:string}
     */
    public function enviarCorreo(int $idOrden, int $idEmpresa, int $idUsuario, string $destinatarios, string $asunto, string $mensaje, string $origen = 'manual'): array
    {
        $cita = $this->citaValida($idOrden, $idEmpresa);
        $empresa = $this->empresaNombre($idEmpresa);
        $destinatarios = trim($destinatarios) !== '' ? trim($destinatarios) : $this->destinatarios($cita)['correo'];
        if ($destinatarios === '') {
            throw new Exception('Ni el vehículo ni el cliente tienen correo registrado. Escriba un correo de destino.');
        }
        foreach (array_map('trim', explode(',', $destinatarios)) as $mail) {
            if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El correo \"{$mail}\" no es válido.");
            }
        }
        $asunto  = trim($asunto) !== '' ? $asunto : $this->aplicarEtiquetas(self::ASUNTO_DEFECTO, $cita, $empresa);
        $mensaje = trim($mensaje) !== '' ? $mensaje : $this->aplicarEtiquetas(self::MENSAJE_DEFECTO, $cita, $empresa);

        $html = "<div style='font-family:Arial,sans-serif;line-height:1.5;color:#333;'>"
              . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) . '</div>';
        $ok = false; $detalle = null;
        try {
            $ok = (new \App\Services\EnvioDocumentosSRIService())->enviarAvisoSimple(
                $idEmpresa, $destinatarios, $this->destinatarios($cita)['nombre'] ?: 'Cliente', $asunto, $html, $empresa
            );
            if (!$ok) $detalle = 'No se pudo enviar el correo (revise la configuración de correo de la empresa).';
        } catch (\Throwable $e) {
            $detalle = 'Error al enviar el correo: ' . $e->getMessage();
        }

        $this->registrar($cita, $idEmpresa, $idUsuario, 'correo', $destinatarios, $asunto, $mensaje, $ok, $detalle, $origen);
        return ['ok' => $ok, 'mensaje' => $ok ? 'Recordatorio enviado a ' . $destinatarios . '.' : (string) $detalle];
    }

    /**
     * Registra un recordatorio enviado a mano por WhatsApp (la pantalla abre WhatsApp con
     * el mensaje listo; el sistema no puede confirmar la entrega, solo deja constancia).
     */
    public function registrarWhatsappManual(int $idOrden, int $idEmpresa, int $idUsuario, string $telefono, string $mensaje): void
    {
        $cita = $this->citaValida($idOrden, $idEmpresa);
        $tel  = self::normalizarTelefono($telefono !== '' ? $telefono : $this->destinatarios($cita)['telefono']);
        if ($tel === '') {
            throw new Exception('Ni el vehículo ni el cliente tienen teléfono registrado. Escriba un número.');
        }
        $this->registrar($cita, $idEmpresa, $idUsuario, 'whatsapp', $tel, null, $mensaje, true,
            'Abierto en WhatsApp para envío manual.', 'manual');
    }

    /**
     * Envía el recordatorio con una plantilla de WhatsApp aprobada por Meta (envío
     * automático). Variables del cuerpo, EN ORDEN: {{1}} cliente, {{2}} placa,
     * {{3}} fecha de la cita, {{4}} empresa, {{5}} N.° de orden.
     */
    public function enviarWhatsappPlantilla(array $cita, int $idEmpresa, int $idUsuario, string $plantilla, string $idioma, int $numVars, string $origen = 'automatico'): bool
    {
        $empresa = $this->empresaNombre($idEmpresa);
        $tel = $this->destinatarios($cita)['telefono'];
        if ($tel === '') return false;
        $valores = [
            $this->destinatarios($cita)['nombre'] ?: 'Cliente',
            (string) ($cita['placa'] ?? ''),
            date('d-m-Y', strtotime((string) $cita['proxima_cita'])),
            $empresa,
            (string) ($cita['numero_orden'] ?? ''),
        ];
        $components = [];
        if ($numVars > 0) {
            $params = [];
            for ($i = 0; $i < $numVars; $i++) {
                $params[] = ['type' => 'text', 'text' => $valores[$i] ?? ''];
            }
            $components[] = ['type' => 'body', 'parameters' => $params];
        }
        $ok = false; $detalle = null;
        try {
            $resp = (new \App\services\WhatsappService())->sendTemplateMessage($idEmpresa, $tel, $plantilla, $idioma, $components);
            $ok = (bool) ($resp['success'] ?? false);
            if (!$ok) $detalle = 'WhatsApp rechazó el envío: ' . json_encode($resp['error'] ?? $resp, JSON_UNESCAPED_UNICODE);
            if ($ok) {
                // Registrar en el Chat Center (si falla, el envío igual cuenta).
                try {
                    $waRepo = new \App\repositories\modulos\WhatsappMensajeRepository();
                    $idChat = $waRepo->getOrCreateChat($idEmpresa, $tel, $valores[0], 'Car-Wash: ' . $plantilla, false);
                    $waRepo->saveMessage($idEmpresa, $idChat, 'OUT', $tel, 'template',
                        ['template' => $plantilla, 'variables' => array_slice($valores, 0, $numVars), 'template_text' => 'Recordatorio de cita'],
                        $resp['data']['messages'][0]['id'] ?? null, 'sent');
                } catch (\Throwable $e) {
                    error_log('[CarWash WhatsApp] Enviado pero no registrado en chat: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $detalle = 'Error al enviar por WhatsApp: ' . $e->getMessage();
        }
        $this->registrar($cita, $idEmpresa, $idUsuario, 'whatsapp', $tel, null,
            'Plantilla ' . $plantilla . ': ' . implode(' | ', array_slice($valores, 0, max(1, $numVars))), $ok, $detalle, $origen);
        return $ok;
    }

    /** Citas pendientes de aviso automático (entre hoy y hoy + $dias). */
    public function citasPendientes(int $idEmpresa, int $dias, string $canal, ?int $idEstablecimiento = null): array
    {
        return $this->repo->citasPendientes($idEmpresa, $dias, $canal, $idEstablecimiento);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Correo, teléfono y nombre de destino: primero los del vehículo, luego los del cliente. */
    public function destinatarios(array $cita): array
    {
        $correo = trim((string) ($cita['vehiculo_correo'] ?? '')) ?: trim((string) ($cita['cliente_email'] ?? ''));
        $tel    = trim((string) ($cita['vehiculo_telefono'] ?? '')) ?: trim((string) ($cita['cliente_telefono'] ?? ''));
        $cliente = trim((string) ($cita['cliente_nombre'] ?? ''));
        // Consumidor Final no es un nombre: se saluda al propietario del vehículo.
        if (preg_match('/^\s*consumidor\s+final\s*$/iu', $cliente)) $cliente = '';
        $nombre = $cliente ?: trim((string) ($cita['vehiculo_propietario'] ?? ''));
        return ['correo' => $correo, 'telefono' => self::normalizarTelefono($tel), 'nombre' => $nombre];
    }

    public function aplicarEtiquetas(string $plantilla, array $cita, string $empresa): string
    {
        return strtr($plantilla, [
            '{cliente}'    => $this->destinatarios($cita)['nombre'] ?: 'Cliente',
            '{placa}'      => (string) ($cita['placa'] ?? ''),
            '{marca}'      => (string) (($cita['marca'] ?? '') ?: ($cita['vehiculo_marca'] ?? '')),
            '{fecha_cita}' => !empty($cita['proxima_cita']) ? date('d-m-Y', strtotime((string) $cita['proxima_cita'])) : '',
            '{empresa}'    => $empresa,
            '{orden}'      => (string) ($cita['numero_orden'] ?? ''),
        ]);
    }

    public function empresaNombre(int $idEmpresa): string
    {
        static $cache = [];
        if (!isset($cache[$idEmpresa])) {
            $e = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $cache[$idEmpresa] = trim((string) (($e['nombre_comercial'] ?? '') ?: ($e['nombre'] ?? '')));
        }
        return $cache[$idEmpresa];
    }

    /** Teléfono en formato internacional de Ecuador (593…), como el resto de envíos de WhatsApp. */
    public static function normalizarTelefono(string $telefono): string
    {
        $tel = preg_replace('/\D/', '', $telefono);
        if ($tel === '' || strlen($tel) < 9) return '';
        if (str_starts_with($tel, '593')) return $tel;
        if (str_starts_with($tel, '0'))   return '593' . substr($tel, 1);
        return '593' . $tel;
    }

    private function citaValida(int $idOrden, int $idEmpresa): array
    {
        if (!$this->repo->existeTabla()) {
            throw new Exception('Falta aplicar el SQL de recordatorios (20260929_carwash_recordatorios.sql).');
        }
        $cita = $this->repo->citaPorOrden($idOrden, $idEmpresa);
        if (!$cita) throw new Exception('Orden no encontrada.');
        if (empty($cita['proxima_cita'])) throw new Exception('La orden no tiene próxima cita.');
        return $cita;
    }

    private function registrar(array $cita, int $idEmpresa, int $idUsuario, string $canal, string $destino, ?string $asunto, ?string $mensaje, bool $ok, ?string $detalle, string $origen): void
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $id = $this->repo->registrar([
                'id_empresa'   => $idEmpresa,
                'id_orden'     => (int) $cita['id_orden'],
                'id_vehiculo'  => (int) ($cita['id_vehiculo'] ?? 0),
                'id_cliente'   => (int) ($cita['id_cliente'] ?? 0),
                'fecha_cita'   => substr((string) $cita['proxima_cita'], 0, 10),
                'canal'        => $canal,
                'destinatario' => $destino,
                'asunto'       => $asunto,
                'mensaje'      => $mensaje,
                'estado'       => $ok ? 'enviado' : 'error',
                'detalle'      => $detalle,
                'origen'       => $origen,
                'id_usuario'   => $idUsuario,
            ]);
            $this->log->registrar($idUsuario, $idEmpresa, 'RECORDATORIO_CITA_CARWASH', 'carwash_recordatorios', $id, null,
                ['id_orden' => (int) $cita['id_orden'], 'canal' => $canal, 'destinatario' => $destino, 'estado' => $ok ? 'enviado' : 'error', 'origen' => $origen]);
            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
