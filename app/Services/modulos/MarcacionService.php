<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MarcacionRepository;
use App\repositories\modulos\BiometriaRepository;
use App\repositories\modulos\AsistenciaPuntoRepository;
use App\repositories\modulos\AsistenciaJornadaRepository;
use App\repositories\modulos\AsistenciaHorarioRepository;
use App\Rules\modulos\MarcacionRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Registro de marcaciones.
 *
 * Flujo móvil: el empleado (identificado por su token personal en el celular)
 * escanea el QR del punto de servicio. Se valida presencia física por cruce
 * de GPS y se sugiere entrada/salida a partir de la última marca del día.
 */
class MarcacionService
{
    /** Ventana anti-doble-marca, en minutos. */
    private const VENTANA_DEDUP_MIN = 1;

    private MarcacionRepository $repository;
    private BiometriaRepository $bioRepository;
    private AsistenciaPuntoRepository $puntoRepository;
    private MarcacionRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        MarcacionRepository $repository,
        BiometriaRepository $bioRepository,
        AsistenciaPuntoRepository $puntoRepository,
        MarcacionRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->bioRepository = $bioRepository;
        $this->puntoRepository = $puntoRepository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /**
     * Marcación desde el celular del empleado escaneando el QR del punto.
     *
     * @param array $in tokenEmpleado, tokenPunto, tipo?, latitud?, longitud?, selfie_path?, dispositivo_id?
     * @return array Resumen de la marca registrada.
     */
    public function marcarPorQr(array $in): array
    {
        $tokenEmpleado = trim((string) ($in['tokenEmpleado'] ?? ''));
        $tokenPunto    = trim((string) ($in['tokenPunto'] ?? ''));

        if ($tokenEmpleado === '') {
            throw new Exception('No se pudo identificar al empleado en este dispositivo.');
        }
        if ($tokenPunto === '') {
            throw new Exception('QR de punto de servicio no válido.');
        }

        $empleado = $this->bioRepository->getByQrToken($tokenEmpleado);
        if (!$empleado) {
            throw new Exception('Credencial de empleado no válida o revocada.');
        }
        if (($empleado['empleado_estado'] ?? 'activo') !== 'activo') {
            throw new Exception('El empleado está inactivo.');
        }

        $punto = $this->puntoRepository->getByQrToken($tokenPunto);
        if (!$punto) {
            throw new Exception('El punto de servicio no existe o está inactivo.');
        }

        // El punto y el empleado deben pertenecer a la misma empresa.
        $idEmpresa = (int) $empleado['id_empresa'];
        if ((int) $punto['id_empresa'] !== $idEmpresa) {
            throw new Exception('Este punto de servicio no corresponde a la empresa del empleado.');
        }

        $idEmpleado = (int) $empleado['id_empleado'];

        // Anti-doble-marca.
        if ($this->repository->existeMarcacionReciente($idEmpleado, $idEmpresa, self::VENTANA_DEDUP_MIN)) {
            throw new Exception('Ya registraste una marca hace instantes. Espera un momento.');
        }

        // Tipo: usar el enviado o sugerir por la última marca del día.
        $tipo = trim((string) ($in['tipo'] ?? ''));
        if ($tipo === '') {
            $tipo = $this->sugerirTipo($idEmpleado, $idEmpresa);
        }

        // Anti-fraude por cruce de GPS.
        $lat = (isset($in['latitud']) && $in['latitud'] !== '') ? (float) $in['latitud'] : null;
        $lng = (isset($in['longitud']) && $in['longitud'] !== '') ? (float) $in['longitud'] : null;
        $distancia = null;
        $estado = 'valida';
        $observacion = null;

        if (!empty($punto['exige_gps'])) {
            if ($lat === null || $lng === null) {
                throw new Exception('Este punto exige ubicación GPS. Activa la ubicación e inténtalo de nuevo.');
            }
            if ($punto['latitud'] !== null && $punto['longitud'] !== null) {
                $distancia = $this->distanciaMetros($lat, $lng, (float) $punto['latitud'], (float) $punto['longitud']);
                if ($distancia > (int) $punto['radio_m']) {
                    $estado = 'sospechosa';
                    $observacion = 'Fuera de la geocerca: ' . round($distancia) . ' m (radio ' . (int) $punto['radio_m'] . ' m).';
                }
            }
        }

        // Confirmación facial (1:1): si el rostro no coincide, marca sospechosa (no bloquea).
        $confianza = (isset($in['confianza']) && $in['confianza'] !== '') ? (float) $in['confianza'] : null;
        if (!empty($in['face_sospechosa'])) {
            $estado = 'sospechosa';
            $obsFace = 'El rostro no coincide con el registrado.';
            $observacion = $observacion ? ($observacion . ' ' . $obsFace) : $obsFace;
        }

        $data = [
            'id_empresa'    => $idEmpresa,
            'id_empleado'   => $idEmpleado,
            'id_punto'      => (int) $punto['id'],
            'tipo'          => $tipo,
            'metodo'        => 'qr_punto',
            'latitud'       => $lat,
            'longitud'      => $lng,
            'distancia_m'   => $distancia,
            'selfie_path'   => $in['selfie_path'] ?? null,
            'confianza'     => $confianza,
            'dispositivo_id' => $in['dispositivo_id'] ?? null,
            'estado'        => $estado,
            'observacion'   => $observacion,
            'created_by'    => $empleado['id_usuario_sistema'] ?? null,
        ];
        $this->rules->validate($data);

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }

        // Auditoría fuera de la transacción: nunca debe hacer perder la marca.
        try {
            $this->logService->registrar(
                (int) ($empleado['id_usuario_sistema'] ?? 0),
                $idEmpresa,
                'MARCAR',
                'asistencia_marcaciones',
                $id,
                null,
                $data
            );
        } catch (\Throwable $e) {
            // Silencioso a propósito.
        }

        // Recalcular la jornada del día (silencioso: la marca ya se guardó).
        $this->sincronizarJornada($idEmpresa, $idEmpleado, date('Y-m-d'));

        return [
            'id'          => $id,
            'tipo'        => $tipo,
            'empleado'    => $empleado['nombres_apellidos'] ?? '',
            'punto'       => $punto['nombre'] ?? '',
            'estado'      => $estado,
            'distancia_m' => $distancia,
            'observacion' => $observacion,
        ];
    }

    /** Marcación manual desde el panel administrativo (por un usuario del sistema). */
    public function marcarManual(array $data, int $idEmpresa, int $idUsuario): int
    {
        $data['id_empresa'] = $idEmpresa;
        $data['metodo']     = 'manual';
        $data['created_by'] = $idUsuario;
        $data['estado']     = $data['estado'] ?? 'valida';
        $this->rules->validate($data);

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'asistencia_marcaciones', $id, null, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        $fechaJornada = !empty($data['fecha_hora']) ? date('Y-m-d', strtotime((string) $data['fecha_hora'])) : date('Y-m-d');
        $this->sincronizarJornada($idEmpresa, (int) $data['id_empleado'], $fechaJornada);
        return $id;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->getDetalle($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Marcación no encontrada.');
        }
        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'asistencia_marcaciones', $id, $old, null);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        $fechaJornada = !empty($old['fecha_hora']) ? date('Y-m-d', strtotime((string) $old['fecha_hora'])) : date('Y-m-d');
        $this->sincronizarJornada($idEmpresa, (int) $old['id_empleado'], $fechaJornada);
    }

    /** Recalcula la jornada del día afectada por la marca (silencioso si falla). */
    private function sincronizarJornada(int $idEmpresa, int $idEmpleado, string $fecha): void
    {
        try {
            $svc = new JornadaService(
                new AsistenciaJornadaRepository(),
                $this->repository,
                new AsistenciaHorarioRepository(),
                $this->logService
            );
            $svc->recalcularDia($idEmpresa, $idEmpleado, $fecha);
        } catch (\Throwable $e) {
            // Silencioso: la jornada se puede recalcular manualmente.
        }
    }

    /** Sugiere entrada/salida alternando según la última marca del día. */
    private function sugerirTipo(int $idEmpleado, int $idEmpresa): string
    {
        $ultima = $this->repository->getUltimaDelDia($idEmpleado, $idEmpresa, date('Y-m-d'));
        if (!$ultima) {
            return 'entrada';
        }
        return in_array($ultima['tipo'], ['entrada', 'fin_break'], true) ? 'salida' : 'entrada';
    }

    /** Distancia en metros entre dos coordenadas (Haversine). */
    private function distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0; // radio terrestre en metros
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
