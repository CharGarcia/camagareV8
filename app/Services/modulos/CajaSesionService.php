<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;
use App\repositories\modulos\CajaSesionRepository;
use App\Rules\modulos\CajaSesionRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Turnos de caja del Punto de Venta: abrir (con fondo inicial) y cerrar
 * (con arqueo). Es el prerrequisito común a las tres plantillas de pantalla
 * del POS — ninguna vende sin una sesión de caja abierta para su punto de
 * emisión.
 */
class CajaSesionService
{
    private CajaSesionRepository $repository;
    private CajaSesionRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        CajaSesionRepository $repository,
        CajaSesionRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getSesionAbierta(int $idEmpresa, int $idPuntoEmision): ?array
    {
        return $this->repository->getAbiertaPorPuntoEmision($idEmpresa, $idPuntoEmision);
    }

    /** Cualquier turno abierto de la empresa (portal público QR — ver CajaSesionRepository::getAbiertaPorEmpresa). */
    public function getSesionAbiertaEmpresa(int $idEmpresa): ?array
    {
        return $this->repository->getAbiertaPorEmpresa($idEmpresa);
    }

    /** ¿Ese turno sigue abierto y es de esta empresa? (validación de un id recibido de afuera). */
    public function esSesionAbiertaDeEmpresa(int $idCajaSesion, int $idEmpresa): bool
    {
        return $this->repository->esAbiertaDeEmpresa($idCajaSesion, $idEmpresa);
    }

    public function abrir(array $data): array
    {
        $this->rules->validarApertura($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idPuntoEmision = (int) $data['id_punto_emision'];

        if ($this->repository->getAbiertaPorPuntoEmision($idEmpresa, $idPuntoEmision)) {
            throw new Exception('Este punto de emisión ya tiene una caja abierta. Ciérrala antes de abrir una nueva.');
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa' => $idEmpresa,
                'id_punto_emision' => $idPuntoEmision,
                'id_usuario' => (int) $data['id_usuario'],
                'fondo_inicial' => (float) $data['fondo_inicial'],
                'created_by' => (int) $data['id_usuario'],
            ];

            $id = $this->repository->create($insertData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'crear',
                'caja_sesiones',
                $id,
                null,
                $insertData
            );

            $this->repository->commit();

            return $this->repository->findById($id, $idEmpresa) ?? $insertData;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function cerrar(int $id, int $idEmpresa, array $data): array
    {
        $this->rules->validarCierre($data);

        $sesion = $this->repository->findById($id, $idEmpresa);
        if (!$sesion) {
            throw new Exception('La sesión de caja no existe.');
        }
        if ($sesion['estado'] !== 'abierta') {
            throw new Exception('Esta sesión de caja ya está cerrada.');
        }

        // Arqueo real: lo esperado es el fondo con el que se abrió el turno
        // más el efectivo cobrado durante el turno (Facturas/Recibos del POS
        // pagados en efectivo). Tarjeta/banco no se cuentan aquí: no afectan
        // lo que debe haber físicamente en la caja.
        $formasPago = $this->repository->getCobrosPorFormaPagoEnTurno($id);
        $montoEsperado = round((float) $sesion['fondo_inicial'] + $this->efectivoDelTurno($id, $formasPago), 2);
        $montoContado = (float) $data['monto_contado'];
        $diferencia = round($montoContado - $montoEsperado, 2);

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'monto_esperado' => $montoEsperado,
                'monto_contado' => $montoContado,
                'diferencia' => $diferencia,
                'observaciones_cierre' => trim($data['observaciones_cierre'] ?? '') ?: null,
                'updated_by' => (int) $data['id_usuario'],
            ];

            $this->repository->cerrar($id, $idEmpresa, $updateData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'caja_sesiones',
                $id,
                $sesion,
                $updateData
            );

            $this->repository->commit();

            $sesionCerrada = $this->repository->findById($id, $idEmpresa) ?? $updateData;
            $sesionCerrada['formas_pago'] = $formasPago;

            // El correo va DESPUÉS del commit y no puede tumbar el cierre: la
            // caja ya está cuadrada y cerrada; si el correo falla, se avisa.
            $sesionCerrada['aviso_correo'] = $this->enviarCorreoCierre($idEmpresa, $sesionCerrada, $formasPago);

            return $sesionCerrada;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Desglose de cobros del turno por forma de pago, para la pantalla de cierre. */
    public function getResumenTurno(int $id, int $idEmpresa): array
    {
        $sesion = $this->repository->findById($id, $idEmpresa);
        if (!$sesion) {
            throw new Exception('La sesión de caja no existe.');
        }

        $formasPago = $this->repository->getCobrosPorFormaPagoEnTurno($id);
        $efectivo   = $this->efectivoDelTurno($id, $formasPago);

        return [
            'formas_pago'     => $formasPago,
            'total_cobrado'   => round(array_sum(array_column($formasPago, 'total')), 2),
            'efectivo'        => $efectivo,
            'fondo_inicial'   => round((float) $sesion['fondo_inicial'], 2),
            'monto_esperado'  => round((float) $sesion['fondo_inicial'] + $efectivo, 2),
        ];
    }

    /**
     * Efectivo que debería haber en el cajón al cerrar.
     *
     * Se toma de las formas de pago de tipo EFECTIVO del desglose (lo que
     * realmente eligió quien cobró), pero **nunca menos** de lo que daba el
     * cálculo anterior por código SRI '01'. Los dos criterios pueden discrepar:
     * hay turnos cuyos documentos salieron con otro código SRI aunque se
     * cobraran en efectivo, y turnos cuyos cobros no llegaron a generar su
     * Ingreso —y sin Ingreso no hay forma de pago que consultar—. Quedarse con
     * el mayor evita reportarle al cajero menos efectivo del que tiene que
     * entregar, que es el error que sí duele.
     */
    private function efectivoDelTurno(int $id, array $formasPago): float
    {
        $porDesglose = 0.0;
        foreach ($formasPago as $f) {
            if (strtoupper((string) ($f['tipo'] ?? '')) === 'EFECTIVO') {
                $porDesglose += (float) $f['total'];
            }
        }

        return round(max($porDesglose, $this->repository->getEfectivoCobradoEnTurno($id)), 2);
    }

    /**
     * Correo con el detalle del cierre al buzón registrado en la empresa.
     * Devuelve null si salió bien, o el motivo por el que no se pudo enviar.
     */
    private function enviarCorreoCierre(int $idEmpresa, array $sesion, array $formasPago): ?string
    {
        try {
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $destino = trim((string) ($empresa['mail'] ?? ''));

            if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                return 'La empresa no tiene un correo válido configurado, así que no se envió el detalle del cierre.';
            }
            if (!function_exists('enviar_correo_reporte')) {
                return 'El envío de correo no está disponible en este servidor.';
            }

            $ok = enviar_correo_reporte(
                [$destino],
                'Cierre de caja ' . date('d-m-Y') . ' — ' . ($empresa['nombre'] ?? ''),
                $this->cuerpoCorreoCierre($empresa, $sesion, $formasPago)
            );

            return $ok ? null : ($GLOBALS['LAST_EMAIL_ERROR'] ?? 'No se pudo enviar el correo del cierre.');
        } catch (\Throwable $e) {
            error_log('[CajaSesion] Correo de cierre no enviado (turno ' . ($sesion['id'] ?? '?') . '): ' . $e->getMessage());
            return 'No se pudo enviar el correo del cierre: ' . $e->getMessage();
        }
    }

    /** Cuerpo HTML del correo de cierre. */
    private function cuerpoCorreoCierre(array $empresa, array $sesion, array $formasPago): string
    {
        $e   = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $m   = static fn($v) => '$ ' . number_format((float) $v, 2);
        $fec = static function (?string $f): string {
            return $f ? date('d-m-Y H:i:s', strtotime($f)) : '—';
        };

        $filas = '';
        foreach ($formasPago as $f) {
            $filas .= '<tr>'
                . '<td style="padding:6px 12px;border-bottom:1px solid #eee;">' . $e($f['nombre'])
                . ($f['tipo'] !== '' ? ' <span style="color:#888;font-size:12px;">(' . $e($f['tipo']) . ')</span>' : '')
                . '</td>'
                . '<td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:center;">' . (int) $f['documentos'] . '</td>'
                . '<td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:right;">' . $m($f['total']) . '</td>'
                . '</tr>';
        }
        if ($filas === '') {
            $filas = '<tr><td colspan="3" style="padding:10px 12px;color:#888;">El turno se cerró sin cobros registrados.</td></tr>';
        }

        $totalCobrado = array_sum(array_column($formasPago, 'total'));
        $diferencia   = (float) ($sesion['diferencia'] ?? 0);
        $colorDif     = abs($diferencia) < 0.01 ? '#198754' : '#dc3545';

        return '
            <div style="font-family:Arial,sans-serif;color:#333;max-width:640px;margin:auto;">
                <h2 style="color:#2563eb;margin-bottom:4px;">Cierre de caja</h2>
                <p style="margin-top:0;color:#666;">' . $e($empresa['nombre'] ?? '') . '</p>

                <table style="border-collapse:collapse;font-size:14px;margin-bottom:18px;">
                    <tr><td style="padding:3px 12px 3px 0;color:#666;">Turno</td><td style="padding:3px 0;"><strong>#' . (int) ($sesion['id'] ?? 0) . '</strong></td></tr>
                    <tr><td style="padding:3px 12px 3px 0;color:#666;">Cajero</td><td style="padding:3px 0;">' . $e($sesion['usuario_nombre'] ?? $sesion['cajero_nombre'] ?? '—') . '</td></tr>
                    <tr><td style="padding:3px 12px 3px 0;color:#666;">Apertura</td><td style="padding:3px 0;">' . $e($fec($sesion['fecha_apertura'] ?? null)) . '</td></tr>
                    <tr><td style="padding:3px 12px 3px 0;color:#666;">Cierre</td><td style="padding:3px 0;">' . $e($fec($sesion['fecha_cierre'] ?? null)) . '</td></tr>
                </table>

                <h3 style="font-size:15px;margin-bottom:6px;">Cobrado por forma de pago</h3>
                <table style="border-collapse:collapse;font-size:14px;width:100%;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="padding:6px 12px;text-align:left;">Forma de pago</th>
                            <th style="padding:6px 12px;text-align:center;">Docs.</th>
                            <th style="padding:6px 12px;text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>' . $filas . '</tbody>
                    <tfoot>
                        <tr>
                            <th style="padding:8px 12px;text-align:left;">Total cobrado</th>
                            <th style="padding:8px 12px;"></th>
                            <th style="padding:8px 12px;text-align:right;">' . $m($totalCobrado) . '</th>
                        </tr>
                    </tfoot>
                </table>

                <h3 style="font-size:15px;margin:18px 0 6px;">Arqueo de efectivo</h3>
                <table style="border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:3px 12px 3px 0;color:#666;">Fondo inicial</td><td style="padding:3px 0;text-align:right;">' . $m($sesion['fondo_inicial'] ?? 0) . '</td></tr>
                    <tr><td style="padding:3px 12px 3px 0;color:#666;">Esperado en caja</td><td style="padding:3px 0;text-align:right;">' . $m($sesion['monto_esperado'] ?? 0) . '</td></tr>
                    <tr><td style="padding:3px 12px 3px 0;color:#666;">Contado</td><td style="padding:3px 0;text-align:right;">' . $m($sesion['monto_contado'] ?? 0) . '</td></tr>
                    <tr><td style="padding:3px 12px 3px 0;color:#666;">Diferencia</td><td style="padding:3px 0;text-align:right;color:' . $colorDif . ';"><strong>' . $m($diferencia) . '</strong></td></tr>
                </table>

                ' . (!empty($sesion['observaciones_cierre'])
                        ? '<p style="font-size:14px;"><strong>Observaciones:</strong> ' . $e($sesion['observaciones_cierre']) . '</p>'
                        : '') . '

                <p style="color:#888;font-size:12px;margin-top:24px;">Enviado automáticamente al cerrar la caja, el ' . date('d-m-Y H:i:s') . '.</p>
            </div>';
    }
}
