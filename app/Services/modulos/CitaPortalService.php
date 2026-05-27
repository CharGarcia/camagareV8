<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CitaPortalRepository;
use App\Services\LogSistemaService;

class CitaPortalService
{
    public function __construct(
        private CitaPortalRepository $repo,
        private LogSistemaService    $log
    ) {}

    // ─── PORTAL CONFIG ────────────────────────────────────────────────────────

    public function getConfigBySlug(string $slug): array
    {
        if (!preg_match('/^[a-z0-9\-]{1,100}$/', $slug)) {
            throw new \Exception('Portal no encontrado.');
        }
        $config = $this->repo->getConfigBySlug($slug);
        if ($config === null) {
            throw new \Exception('El portal de reservas no existe o no está activo.');
        }
        return $config;
    }

    public function getPortalStats(int $idEmpresa): array
    {
        return $this->repo->getPortalStats($idEmpresa);
    }

    public function getUltimasReservasPortal(int $idEmpresa, int $limit = 10): array
    {
        return $this->repo->getUltimasReservasPortal($idEmpresa, $limit);
    }

    // ─── CATÁLOGOS ────────────────────────────────────────────────────────────

    public function getCatalogos(int $idEmpresa): array
    {
        return [
            'tipos'    => $this->repo->getTiposActivos($idEmpresa),
            'recursos' => $this->repo->getRecursosActivos($idEmpresa),
        ];
    }

    // ─── DISPONIBILIDAD ───────────────────────────────────────────────────────

    /**
     * Calcula y retorna los slots disponibles para una fecha dada.
     */
    public function getDisponibilidad(
        int $idEmpresa, string $fecha, int $idTipoCita, ?int $idRecurso,
        int $maxDiasAnticipacion, int $minHorasAnticipacion
    ): array {
        // Validar fecha
        $fechaTs = strtotime($fecha);
        if (!$fechaTs) throw new \Exception('Fecha no válida.');

        $hoy = strtotime(date('Y-m-d'));
        $maxFecha = strtotime('+' . $maxDiasAnticipacion . ' days', $hoy);

        if ($fechaTs < $hoy) throw new \Exception('No se puede reservar en fechas pasadas.');
        if ($fechaTs > $maxFecha) throw new \Exception("Solo se pueden hacer reservas con hasta {$maxDiasAnticipacion} días de anticipación.");

        // Obtener duración del tipo de cita
        $tipos    = $this->repo->getTiposActivos($idEmpresa);
        $tipo     = current(array_filter($tipos, fn($t) => (int)$t['id'] === $idTipoCita));
        if (!$tipo) throw new \Exception('Tipo de cita no válido.');
        $duracion = (int) ($tipo['duracion_minutos'] ?? 30);

        // Día de semana (1=Lunes...7=Domingo, igual que DB)
        $diaSemana = (int) date('N', $fechaTs); // 1=Mon, 7=Sun

        // Horarios laborales
        $horarios = $this->repo->getHorariosParaDia($idEmpresa, $diaSemana, $idRecurso);
        if (empty($horarios)) return [];

        // Citas ya ocupadas ese día
        $ocupadas = $this->repo->getCitasOcupadas($idEmpresa, $fecha, $idRecurso);

        // Tiempo mínimo de anticipación
        $ahora = time();
        $minAnticipacionSecs = $minHorasAnticipacion * 3600;

        $slots = [];
        foreach ($horarios as $h) {
            $inicio = strtotime($fecha . ' ' . $h['hora_inicio']);
            $fin    = strtotime($fecha . ' ' . $h['hora_fin']);

            $cursor = $inicio;
            while ($cursor + $duracion * 60 <= $fin) {
                $slotFin = $cursor + $duracion * 60;

                // Respetar anticipación mínima
                if ($cursor < $ahora + $minAnticipacionSecs) {
                    $cursor += $duracion * 60;
                    continue;
                }

                // Verificar si el slot se superpone con alguna cita existente
                $ocupado = false;
                foreach ($ocupadas as $cita) {
                    $citaIni = strtotime($cita['fecha_inicio']);
                    $citaFin = strtotime($cita['fecha_fin']);
                    if ($cursor < $citaFin && $slotFin > $citaIni) {
                        $ocupado = true;
                        break;
                    }
                }

                if (!$ocupado) {
                    $slots[] = [
                        'inicio'     => date('H:i', $cursor),
                        'fin'        => date('H:i', $slotFin),
                        'inicio_ts'  => $cursor,
                        'fecha_hora' => date('Y-m-d H:i:s', $cursor),
                        'fecha_hora_fin' => date('Y-m-d H:i:s', $slotFin),
                    ];
                }

                $cursor += $duracion * 60;
            }
        }

        return $slots;
    }

    // ─── VERIFICAR CLIENTE ────────────────────────────────────────────────────

    public function verificarCliente(string $identificacion, string $email, int $idEmpresa): array
    {
        if (empty($identificacion) && empty($email)) {
            return ['encontrado' => false, 'cliente' => null];
        }
        $cliente = $this->repo->buscarClienteEnSistema($identificacion, $email, $idEmpresa);
        if ($cliente) {
            return [
                'encontrado' => true,
                'cliente'    => [
                    'id'             => $cliente['id'],
                    'nombre'         => $cliente['nombre'],
                    'identificacion' => $cliente['identificacion'],
                    'email'          => $cliente['email'],
                    'telefono'       => $cliente['telefono'],
                ],
            ];
        }
        return ['encontrado' => false, 'cliente' => null];
    }

    // ─── CREAR RESERVA ────────────────────────────────────────────────────────

    public function reservar(array $d, array $portalConfig): int
    {
        // Validaciones básicas
        if (empty($d['fecha_inicio'])) throw new \Exception('Fecha/hora de inicio requerida.');
        if (empty($d['fecha_fin']))    throw new \Exception('Fecha/hora de fin requerida.');
        if (strtotime($d['fecha_fin']) <= strtotime($d['fecha_inicio'])) {
            throw new \Exception('La hora de fin debe ser posterior a la inicio.');
        }

        $idEmpresa = (int) $portalConfig['id_empresa'];

        // Resolver id_cliente o crear cliente externo
        $idCliente         = null;
        $idClienteExterno  = null;

        if (!empty($d['id_cliente'])) {
            $idCliente = (int) $d['id_cliente'];
        } else {
            // Crear cliente externo
            if (empty($d['nombres'])) throw new \Exception('El nombre es obligatorio.');
            $idClienteExterno = $this->repo->createClienteExterno([
                'id_empresa'        => $idEmpresa,
                'nombres'           => trim($d['nombres']),
                'apellidos'         => trim($d['apellidos'] ?? ''),
                'email'             => trim($d['email'] ?? ''),
                'telefono'          => trim($d['telefono'] ?? ''),
                'identificacion'    => trim($d['identificacion'] ?? ''),
                'id_cliente_sistema' => null,
            ]);
        }

        $this->repo->beginTransaction();
        try {
            $idCita = $this->repo->createCitaPortal([
                'id_empresa'           => $idEmpresa,
                'id_tipo_cita'         => (int) ($d['id_tipo_cita'] ?? 0) ?: null,
                'id_recurso'           => (int) ($d['id_recurso']   ?? 0) ?: null,
                'id_cliente'           => $idCliente,
                'id_cliente_externo'   => $idClienteExterno,
                'titulo'               => trim($d['titulo'] ?? '') ?: null,
                'fecha_inicio'         => $d['fecha_inicio'],
                'fecha_fin'            => $d['fecha_fin'],
                'notas'                => trim($d['notas'] ?? '') ?: null,
                'requiere_confirmacion' => (bool) ($portalConfig['requiere_confirmacion'] ?? false),
            ]);

            $this->log->registrar(
                0, $idEmpresa, 'crear', 'citas', $idCita,
                null,
                ['origen' => 'portal', 'id_tipo_cita' => $d['id_tipo_cita']]
            );

            $this->repo->commit();
            return $idCita;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function getCitaById(int $id): ?array
    {
        return $this->repo->getCitaById($id);
    }
}
