<?php

declare(strict_types=1);

namespace App\Services\Sri;

use App\core\Database;
use App\models\Empresa;
use App\models\SriEnvioLog;

/**
 * Reintenta automáticamente los comprobantes que quedaron pendientes de
 * resolución en el SRI (enviados pero sin autorización ni rechazo definitivo
 * — típicamente el ambiente de pruebas tardando en publicar el resultado), y
 * avisa por correo a la empresa si llevan más de una hora sin resolver.
 *
 * Se dispara desde app/cron/cron_runner.php en cada tick. Es seguro llamarlo
 * repetidamente: SriEnvioService::enviarXxx() nunca reenvía un comprobante que
 * el SRI ya tiene en cola (ver preVerificarAutorizacion() / claveYaRecibidaSinResolver()
 * en SriEnvioService) — como mucho vuelve a consultar su estado.
 */
class SriReintentosPendientesService
{
    /** tipo_comprobante (sri_envio_log) => tabla de cabecera. */
    private const TABLAS = [
        'factura_venta'      => 'ventas_cabecera',
        'factura_reembolso'  => 'factura_reembolso_cabecera',
        'nota_credito'       => 'notas_credito_cabecera',
        'nota_debito'        => 'nota_debito_cabecera',
        'retencion_compra'   => 'retencion_compra_cabecera',
        'guia_remision'      => 'guias_remision_cabecera',
        'liquidacion_compra' => 'liquidaciones_cabecera',
    ];

    /** Última acción registrada = todavía sin una resolución definitiva del SRI. */
    private const ACCIONES_PENDIENTES = ['enviando', 'recibida', 'en_procesamiento', 'error'];

    /** No reintentar antes de esto: evita chocar con un envío manual recién hecho. */
    private const MINUTOS_MINIMO_ESPERA = 5;

    /** A partir de esta antigüedad sin resolver, avisar por correo a la empresa. */
    private const MINUTOS_AVISO = 60;

    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Punto de entrada del cron. Reintenta los pendientes elegibles y envía
     * avisos por correo donde corresponda. Cada comprobante se procesa en su
     * propio try/catch: uno con problemas no bloquea a los demás ni al cron.
     *
     * @return array{reintentados:int, resueltos:int, avisos:int}
     */
    public function procesar(): array
    {
        $stats = ['reintentados' => 0, 'resueltos' => 0, 'avisos' => 0];

        $candidatos = $this->getCandidatosPendientes();
        if (!$candidatos) {
            return $stats;
        }

        $envioService = new SriEnvioService();

        foreach ($candidatos as $c) {
            $tabla = self::TABLAS[$c['tipo_comprobante']] ?? null;
            if ($tabla === null) {
                continue;
            }

            try {
                $doc = $this->getDocumentoVigente($tabla, (int) $c['id_comprobante'], (int) $c['id_empresa']);
                if ($doc === null) {
                    continue; // Anulado, eliminado, o ya no existe: no reintentar.
                }

                $idUsuario = (int) ($doc['created_by'] ?: $doc['updated_by'] ?: 0);
                $stats['reintentados']++;

                $resultado = $this->despachar($envioService, $c['tipo_comprobante'], (int) $c['id_comprobante'], (int) $c['id_empresa'], $idUsuario);
                if (in_array($resultado['estado'] ?? '', ['autorizado', 'autorizada'], true)) {
                    $stats['resueltos']++;
                }
            } catch (\Throwable $e) {
                error_log('[SriReintentosPendientes] ' . $c['tipo_comprobante'] . ' #' . $c['id_comprobante'] . ': ' . $e->getMessage());
            }

            // Independiente del resultado del reintento: si ya lleva 1h+ sin
            // resolver, avisar (una sola vez por día) a la empresa.
            try {
                if ($this->avisarSiCorresponde($c)) {
                    $stats['avisos']++;
                }
            } catch (\Throwable $e) {
                error_log('[SriReintentosPendientes] Aviso ' . $c['tipo_comprobante'] . ' #' . $c['id_comprobante'] . ': ' . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Última fila de sri_envio_log por (tipo_comprobante, id_comprobante,
     * id_empresa) cuya acción sigue sin resolución definitiva, con al menos
     * MINUTOS_MINIMO_ESPERA desde el último intento.
     */
    private function getCandidatosPendientes(): array
    {
        $sql = "
            SELECT DISTINCT ON (tipo_comprobante, id_comprobante, id_empresa)
                   id, id_empresa, tipo_comprobante, id_comprobante, clave_acceso,
                   tipo_ambiente, accion, created_at
            FROM sri_envio_log
            WHERE clave_acceso IS NOT NULL AND clave_acceso <> ''
            ORDER BY tipo_comprobante, id_comprobante, id_empresa, id DESC
        ";
        $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        $limite = (new \DateTime())->modify('-' . self::MINUTOS_MINIMO_ESPERA . ' minutes');
        $candidatos = [];
        foreach ($rows as $r) {
            if (!in_array($r['accion'], self::ACCIONES_PENDIENTES, true)) {
                continue;
            }
            if (new \DateTime($r['created_at']) > $limite) {
                continue; // Muy reciente: puede ser un envío manual en curso ahora mismo.
            }
            $candidatos[] = $r;
        }
        return $candidatos;
    }

    /** Documento vigente (no anulado, no eliminado) de la empresa dueña del log. */
    private function getDocumentoVigente(string $tabla, int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, id_empresa, estado, created_by, updated_by, fecha_emision
             FROM {$tabla} WHERE id = ? AND id_empresa = ? AND eliminado = false"
        );
        $st->execute([$id, $idEmpresa]);
        $doc = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$doc || $doc['estado'] === 'anulado') {
            return null;
        }
        return $doc;
    }

    private function despachar(SriEnvioService $svc, string $tipo, int $id, int $idEmpresa, int $idUsuario): array
    {
        return match ($tipo) {
            'factura_venta'      => $svc->enviarFacturaVenta($id, $idEmpresa, $idUsuario),
            'factura_reembolso'  => $svc->enviarFacturaReembolso($id, $idEmpresa, $idUsuario),
            'nota_credito'       => $svc->enviarNotaCredito($id, $idEmpresa, $idUsuario),
            'nota_debito'        => $svc->enviarNotaDebito($id, $idEmpresa, $idUsuario),
            'retencion_compra'   => $svc->enviarRetencionCompra($id, $idEmpresa, $idUsuario),
            'guia_remision'      => $svc->enviarGuiaRemision($id, $idEmpresa, $idUsuario),
            'liquidacion_compra' => $svc->enviarLiquidacionCompra($id, $idEmpresa, $idUsuario),
            default              => ['ok' => false, 'estado' => 'error', 'mensaje' => 'Tipo no soportado.'],
        };
    }

    /**
     * Si el comprobante lleva >= MINUTOS_AVISO sin resolución desde su primer
     * envío, avisa por correo a la empresa (una sola vez por día por clave de
     * acceso — el propio aviso queda registrado en sri_envio_log como marca).
     */
    private function avisarSiCorresponde(array $candidato): bool
    {
        $claveAcceso = (string) ($candidato['clave_acceso'] ?? '');
        if ($claveAcceso === '') {
            return false;
        }

        $stPrimer = $this->db->prepare(
            "SELECT MIN(created_at) FROM sri_envio_log WHERE clave_acceso = ? AND accion = 'enviando'"
        );
        $stPrimer->execute([$claveAcceso]);
        $primerEnvio = $stPrimer->fetchColumn();
        if (!$primerEnvio) {
            return false;
        }

        $minutos = (time() - strtotime((string) $primerEnvio)) / 60;
        if ($minutos < self::MINUTOS_AVISO) {
            return false;
        }

        $stAviso = $this->db->prepare(
            "SELECT 1 FROM sri_envio_log
             WHERE clave_acceso = ? AND accion = 'aviso_email' AND created_at::date = CURRENT_DATE
             LIMIT 1"
        );
        $stAviso->execute([$claveAcceso]);
        if ($stAviso->fetchColumn()) {
            return false; // Ya se avisó hoy por esta clave.
        }

        $empresa = (new Empresa())->getPorId((int) $candidato['id_empresa']);
        $correo  = trim((string) ($empresa['mail'] ?? $empresa['correo'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        require_once MVC_APP . '/helpers/mail.php';
        $ok = enviar_correo_sri_pendientes($correo, [
            'empresa_nombre'   => $empresa['nombre'] ?? '',
            'tipo_comprobante' => (string) $candidato['tipo_comprobante'],
            'id_comprobante'   => (int) $candidato['id_comprobante'],
            'clave_acceso'     => $claveAcceso,
            'minutos'          => (int) round($minutos),
        ]);

        // Solo se marca si el correo salió: si falla (SMTP caído, etc.), se
        // reintenta el aviso en el siguiente tick en vez de perderse por hoy.
        if ($ok) {
            (new SriEnvioLog())->registrar([
                'id_empresa'       => (int) $candidato['id_empresa'],
                'tipo_comprobante' => (string) $candidato['tipo_comprobante'],
                'id_comprobante'   => (int) $candidato['id_comprobante'],
                'clave_acceso'     => $claveAcceso,
                'tipo_ambiente'    => (string) ($candidato['tipo_ambiente'] ?? '1'),
                'accion'           => 'aviso_email',
                'mensaje'          => 'Aviso enviado a la empresa: comprobante sin resolver hace ' . (int) round($minutos) . ' minutos.',
                'created_by'       => 0,
            ]);
        }

        return $ok;
    }
}
