<?php

/**
 * Controlador PÚBLICO — sin autenticación.
 *
 * Permite que una persona sin cuenta en el sistema entre a una videollamada
 * mediante el enlace personal que se le envió. La credencial es el token: cada
 * invitado tiene el suyo, atado a una sala concreta.
 *
 * Rutas:
 *   GET  /videollamada-invitado?t=TOKEN   index()        Valida el token y abre la sala
 *   POST /videollamada-invitado/entrarAjax        (del trait)
 *   GET  /videollamada-invitado/senalesAjax       (del trait)
 *   POST /videollamada-invitado/enviarSenalAjax   (del trait)
 *   POST /videollamada-invitado/salirAjax         (del trait)
 *
 * Los cuatro endpoints de señalización vienen de SenalizacionSalaTrait, el mismo
 * que usa el módulo autenticado. Lo único propio de aquí es cómo se identifica
 * a quien llama: por token la primera vez y por sesión de invitado después.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\VideollamadaRepository;
use App\Rules\modulos\VideollamadaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\VideollamadaService;
use App\Traits\SenalizacionSalaTrait;

class VideollamadaInvitadoController extends Controller
{
    use SenalizacionSalaTrait;

    /** Datos del invitado ya validado. Se llenan al abrir el enlace. */
    private const SESSION_INVITADO = 'vc_invitado';

    private VideollamadaService $service;
    private VideollamadaRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new VideollamadaRepository();
        $this->service    = new VideollamadaService(
            $this->repository,
            new VideollamadaRules(),
            new LogSistemaService()
        );
    }

    // ────────────────────────────────────────────────────────────────────
    //  Entrada por enlace
    // ────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = trim((string) ($_GET['t'] ?? ''));
        if ($token === '') {
            $this->error('El enlace está incompleto.', 'Pida al organizador que se lo reenvíe.');
        }

        $inv = $this->repository->getParticipantePorToken($token);

        if ($inv === null) {
            $this->error(
                'Este enlace no es válido.',
                'Puede que la reunión se haya eliminado o que el enlace esté mal copiado.'
            );
        }

        if (in_array($inv['estado'], ['finalizada', 'cancelada'], true)) {
            $this->error(
                'Esta reunión ya terminó.',
                'Si necesita volver a reunirse, pida al organizador que programe una nueva.'
            );
        }

        if (empty($inv['permite_invitados']) && empty($inv['id_usuario'])) {
            $this->error(
                'La reunión no admite invitados externos.',
                'Pida al organizador que active esa opción o que lo agregue como usuario del sistema.'
            );
        }

        // Sesión de invitado: a partir de aquí el token ya no viaja en cada
        // petición. Va atada a la sala concreta, no da acceso a nada más.
        $_SESSION[self::SESSION_INVITADO] = [
            'id_participante' => (int) $inv['id'],
            'id_sala'         => (int) $inv['id_sala'],
            'id_empresa'      => (int) $inv['id_empresa'],
            'nombre'          => (string) ($inv['nombre_invitado'] ?? 'Invitado'),
            'peer_id'         => 'g' . (int) $inv['id'] . '-' . bin2hex(random_bytes(5)),
            'manda_sala'      => ($inv['rol'] ?? '') === 'moderador',
        ];

        $this->view('publica/videollamada/invitado', [
            'titulo'      => (string) $inv['titulo'],
            'codigo'      => (string) $inv['codigo'],
            'nombre'      => (string) ($inv['nombre_invitado'] ?? 'Invitado'),
            'idSala'      => (int) $inv['id_sala'],
            'salaEspera'  => !empty($inv['sala_espera']),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Contrato de SenalizacionSalaTrait
    // ────────────────────────────────────────────────────────────────────

    /**
     * Identidad del invitado.
     *
     * No hay usuario ni permisos de módulo: la autorización es haber abierto un
     * enlace válido, que dejó la sesión de invitado. Sin esa sesión no se pasa,
     * así que un tercero no puede señalizar aunque conozca el id de la sala.
     */
    protected function contextoSala(bool $creandoPeer = false): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $inv = $_SESSION[self::SESSION_INVITADO] ?? null;

        // La sesión ya no se escribe: liberar el lock cuanto antes, porque estos
        // endpoints se consultan una vez por segundo.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!is_array($inv) || empty($inv['peer_id'])) {
            $this->json(['ok' => false, 'reentrar' => true, 'mensaje' => 'Su acceso caducó. Vuelva a abrir el enlace de la invitación.']);
        }

        return [
            'id_empresa'      => (int) $inv['id_empresa'],
            'id_sala'         => (int) $inv['id_sala'],
            'peer_id'         => (string) $inv['peer_id'],
            'nombre'          => (string) $inv['nombre'],
            'id_usuario'      => null,
            'manda_sala'      => !empty($inv['manda_sala']),
            'es_invitado'     => true,
            'id_participante' => (int) $inv['id_participante'],
        ];
    }

    protected function alEntrarEnSala(array $ctx): void
    {
        $this->repository->marcarConexionParticipante($ctx['id_participante'], true);
        $this->repository->registrarEvento(
            $ctx['id_empresa'],
            $ctx['id_sala'],
            $ctx['id_participante'],
            'entro',
            ['invitado' => true],
            null
        );
    }

    protected function alSalirDeSala(array $ctx): void
    {
        $this->repository->marcarConexionParticipante($ctx['id_participante'], false);
        $this->repository->registrarEvento(
            $ctx['id_empresa'],
            $ctx['id_sala'],
            $ctx['id_participante'],
            'salio',
            ['invitado' => true],
            null
        );
    }

    protected function salaService(): VideollamadaService
    {
        return $this->service;
    }

    protected function salaRepository(): VideollamadaRepository
    {
        return $this->repository;
    }

    // ────────────────────────────────────────────────────────────────────

    /** Página de error, con lenguaje para alguien ajeno al sistema. */
    private function error(string $titulo, string $detalle): never
    {
        $this->view('publica/videollamada/error', [
            'tituloError' => $titulo,
            'detalle'     => $detalle,
        ]);
        exit;
    }
}
