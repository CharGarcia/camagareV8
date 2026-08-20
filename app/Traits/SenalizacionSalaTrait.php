<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\modulos\videollamadas\SenalizacionService;

/**
 * Endpoints de señalización WebRTC compartidos.
 *
 * Los usa tanto el controlador del módulo (usuarios del ERP, con sesión y
 * permisos) como el controlador público de invitados (gente sin cuenta que
 * entra por un enlace con token). El protocolo es idéntico para los dos; lo
 * único que cambia es CÓMO se identifica a quien llama.
 *
 * Por eso el trait no resuelve identidad: la pide con contextoSala(), que cada
 * controlador implementa a su manera. Así las rutas autenticadas siguen exigiendo
 * sesión y permisos, y las públicas exigen token válido, sin que ninguna de las
 * dos abra un agujero en la otra.
 *
 * contextoSala() debe devolver:
 *   id_empresa, id_sala, peer_id, nombre, id_usuario (int|null),
 *   manda_sala (bool), es_invitado (bool)
 * y cortar la petición por su cuenta si el acceso no es válido.
 *
 * REGLA: todos estos endpoints liberan el lock de sesión antes de responder
 * (lo hace el contexto) y usan polling corto, NUNCA long-polling: en Apache
 * prefork una petición que espera bloquea un proceso completo.
 */
trait SenalizacionSalaTrait
{
    /** @return array{id_empresa:int,id_sala:int,peer_id:string,nombre:string,id_usuario:?int,manda_sala:bool,es_invitado:bool} */
    abstract protected function contextoSala(bool $creandoPeer = false): array;

    /** Se ejecuta cuando alguien entra de verdad a la sala (para asistencia). */
    abstract protected function alEntrarEnSala(array $ctx): void;

    /** Se ejecuta al salir. */
    abstract protected function alSalirDeSala(array $ctx): void;

    /**
     * Anuncia la llegada y entrega lo necesario para conectarse: identificador
     * de par, cursor del buzón, servidores ICE y quién está ya dentro.
     */
    public function entrarAjax(): void
    {
        $ctx = $this->contextoSala(true);

        try {
            $sala = $this->salaService()->getPorId($ctx['id_sala'], $ctx['id_empresa']);
            if ($sala === null) {
                $this->json(['ok' => false, 'mensaje' => 'La reunión no existe o ya no está disponible.']);
            }
            if (in_array($sala['estado'], ['finalizada', 'cancelada'], true)) {
                $this->json(['ok' => false, 'mensaje' => 'Esta reunión ya terminó.']);
            }

            $senal = new SenalizacionService();

            // Señal de vida de la sala, para el cierre automático por
            // inactividad. Va antes de repartir a la antesala a propósito: el
            // que espera a que lo admitan también es alguien dentro.
            if ($sala['estado'] === 'en_curso') {
                $this->salaRepository()->marcarLatidoSala($ctx['id_sala'], $ctx['id_empresa']);
            }

            // El anfitrión y los moderadores nunca hacen antesala: alguien tiene
            // que estar dentro para poder admitir a los demás.
            $debeEsperar = !empty($sala['sala_espera']) && !$ctx['manda_sala'];

            if ($debeEsperar) {
                $senal->marcarPresencia($ctx['id_sala'], $ctx['peer_id'], [
                    'id_usuario' => $ctx['id_usuario'],
                    'nombre'     => $ctx['nombre'],
                    'espera'     => true,
                ]);

                $this->json([
                    'ok'        => true,
                    'peer_id'   => $ctx['peer_id'],
                    'en_espera' => true,
                    'cursor'    => $senal->getSecuenciaActual($ctx['id_sala']),
                ]);
            }

            $senal->marcarPresencia($ctx['id_sala'], $ctx['peer_id'], [
                'id_usuario' => $ctx['id_usuario'],
                'nombre'     => $ctx['nombre'],
                'anfitrion'  => $ctx['manda_sala'],
                'invitado'   => $ctx['es_invitado'],
                'espera'     => false,
            ]);

            $this->alEntrarEnSala($ctx);

            $this->json([
                'ok'           => true,
                'peer_id'      => $ctx['peer_id'],
                'en_espera'    => false,
                'manda_sala'   => $ctx['manda_sala'],
                'cursor'       => $senal->getSecuenciaActual($ctx['id_sala']),
                'presentes'    => $this->filtrarAdmitidos($senal->getPresentes($ctx['id_sala'], $ctx['peer_id'])),
                'credenciales' => $this->salaService()->getCredenciales($ctx['id_sala'], $ctx['id_empresa'], (int) ($ctx['id_usuario'] ?? 0)),
                'max'          => (int) $sala['max_participantes'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Latido y recogida del buzón. */
    public function senalesAjax(): void
    {
        $ctx = $this->contextoSala();

        try {
            $senal = new SenalizacionService();
            $datos = $this->salaRepository()->getDatosPoll($ctx['id_sala'], $ctx['id_empresa']);

            // Latido de la sala: mientras alguien siga preguntando, la reunión
            // está viva y el cron no debe cerrarla. Es lo único que llega a la
            // base desde aquí, y va con freno propio (ver marcarLatidoSala):
            // como mucho una escritura por minuto y por sala, sin importar
            // cuánta gente esté poleando.
            //
            // Se hace ANTES de la rama de sala de espera porque esa rama corta
            // la petición, y quien aguarda en la antesala cuenta como presencia.
            if ($datos['estado'] === 'en_curso') {
                $this->salaRepository()->marcarLatidoSala($ctx['id_sala'], $ctx['id_empresa']);
            }

            if (filter_var($_GET['espera'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->responderEspera($senal, $ctx, $datos);
            }

            $senal->marcarPresencia($ctx['id_sala'], $ctx['peer_id'], [
                'id_usuario' => $ctx['id_usuario'],
                'nombre'     => $ctx['nombre'],
                'invitado'   => $ctx['es_invitado'],
                'espera'     => false,
            ]);

            $recibido  = $senal->recibir($ctx['id_sala'], $ctx['peer_id'], (int) ($_GET['cursor'] ?? 0));
            $presentes = $senal->getPresentes($ctx['id_sala'], $ctx['peer_id']);

            $this->json([
                'ok'        => true,
                'cursor'    => $recibido['cursor'],
                'mensajes'  => $recibido['mensajes'],
                'presentes' => $this->filtrarAdmitidos($presentes),
                // La cola de espera solo la ve quien puede resolverla.
                'esperando' => $ctx['manda_sala'] ? $this->filtrarEnEspera($presentes) : [],
                'estado'    => $datos['estado'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Deja una oferta, respuesta o candidato ICE para otro participante. */
    public function enviarSenalAjax(): void
    {
        $ctx = $this->contextoSala();

        $tipo = (string) ($_POST['tipo'] ?? '');
        if (!in_array($tipo, ['offer', 'answer', 'ice', 'bye', 'estado'], true)) {
            $this->json(['ok' => false, 'mensaje' => 'Tipo de señal no válido.']);
        }

        $payload = json_decode((string) ($_POST['payload'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $senal = new SenalizacionService();
            $seq = $senal->enviar($ctx['id_sala'], $ctx['peer_id'], (string) ($_POST['para'] ?? ''), $tipo, $payload);
            $this->json(['ok' => true, 'seq' => $seq]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Salida explícita: quita la presencia y avisa a los demás. */
    public function salirAjax(): void
    {
        $ctx = $this->contextoSala();

        try {
            $senal = new SenalizacionService();
            $senal->enviar($ctx['id_sala'], $ctx['peer_id'], '', 'bye', []);
            $senal->quitarPresencia($ctx['id_sala'], $ctx['peer_id']);
            $this->alSalirDeSala($ctx);
        } catch (\Throwable $e) {
            // La salida es best-effort: si falla, el TTL de presencia lo resuelve solo.
            error_log('Videollamadas::salirAjax ' . $e->getMessage());
        }

        $this->json(['ok' => true]);
    }

    /**
     * Respuesta para quien está en la antesala: se le dice si ya lo dejaron
     * pasar, si lo rechazaron, o que siga esperando. Termina la petición.
     */
    private function responderEspera(SenalizacionService $senal, array $ctx, array $datos): void
    {
        $admision = $senal->getAdmision($ctx['id_sala'], $ctx['peer_id']);

        if ($admision === false) {
            $senal->quitarPresencia($ctx['id_sala'], $ctx['peer_id']);
            $this->json(['ok' => true, 'rechazado' => true]);
        }

        if ($admision !== true) {
            // Sigue esperando: se refresca la presencia para no desaparecer de
            // la cola del anfitrión mientras aguarda.
            $senal->marcarPresencia($ctx['id_sala'], $ctx['peer_id'], [
                'id_usuario' => $ctx['id_usuario'],
                'nombre'     => $ctx['nombre'],
                'invitado'   => $ctx['es_invitado'],
                'espera'     => true,
            ]);
            $this->json(['ok' => true, 'en_espera' => true, 'estado' => $datos['estado']]);
        }

        $senal->marcarPresencia($ctx['id_sala'], $ctx['peer_id'], [
            'id_usuario' => $ctx['id_usuario'],
            'nombre'     => $ctx['nombre'],
            'invitado'   => $ctx['es_invitado'],
            'espera'     => false,
        ]);
        $this->alEntrarEnSala($ctx);

        $this->json([
            'ok'           => true,
            'admitido'     => true,
            'cursor'       => $senal->getSecuenciaActual($ctx['id_sala']),
            'presentes'    => $this->filtrarAdmitidos($senal->getPresentes($ctx['id_sala'], $ctx['peer_id'])),
            'credenciales' => $this->salaService()->getCredenciales($ctx['id_sala'], $ctx['id_empresa'], (int) ($ctx['id_usuario'] ?? 0)),
        ]);
    }

    /** Con quiénes hay que abrir conexión: los que ya están dentro, no los que esperan. */
    private function filtrarAdmitidos(array $presentes): array
    {
        return array_values(array_filter($presentes, static fn (array $p): bool => empty($p['espera'])));
    }

    /** La cola de la antesala. */
    private function filtrarEnEspera(array $presentes): array
    {
        return array_values(array_filter($presentes, static fn (array $p): bool => !empty($p['espera'])));
    }

    /** El service del módulo, que cada controlador ya tiene instanciado. */
    abstract protected function salaService(): \App\Services\modulos\VideollamadaService;

    abstract protected function salaRepository(): \App\repositories\modulos\VideollamadaRepository;
}
