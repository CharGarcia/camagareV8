<?php

declare(strict_types=1);

namespace App\Services\modulos\videollamadas;

use App\Helpers\Cache;

/**
 * Buzón de señalización WebRTC.
 *
 * Los navegadores no pueden hablarse directo hasta haber intercambiado su
 * descripción de sesión (SDP) y sus direcciones candidatas (ICE). Este servicio
 * es el buzón por el que pasan esos mensajes: los recibe de un participante y
 * los deja disponibles para otro. NO transporta audio ni video — eso viaja
 * directo entre navegadores una vez establecida la conexión.
 *
 * ── Por qué no va a PostgreSQL ──────────────────────────────────────────────
 * Es tráfico efímero y de alta frecuencia (un poll por segundo durante la
 * negociación). Mandarlo a la base agotaría el pool de conexiones de la Managed
 * DB, que es el cuello de botella real del sistema. Vive en memoria (APCu) con
 * TTL corto; si un mensaje se pierde, el navegador simplemente reintenta.
 *
 * ── Cómo evita condiciones de carrera ───────────────────────────────────────
 * Varios procesos de Apache escriben el mismo buzón a la vez. En lugar de un
 * array compartido (que se pisaría), CADA MENSAJE ES UNA CLAVE PROPIA, numerada
 * con un contador atómico (apcu_inc). Cada participante lleva su cursor y pide
 * "lo que haya después del número N". Sin locks y sin perder mensajes.
 *
 * ── Respaldo sin APCu ───────────────────────────────────────────────────────
 * Si APCu no está instalado (típico en XAMPP local), cae a archivos JSON en
 * storage/. Más lento, pero permite desarrollar sin la extensión. En producción
 * APCu sí está activo.
 */
class SenalizacionService
{
    /** Cuánto vive un mensaje sin leer. Más que suficiente para una negociación. */
    private const TTL_MENSAJE = 60;

    /** Cuánto vale una marca de presencia sin refrescarse. Pasado esto, el peer se da por ido. */
    private const TTL_PRESENCIA = 25;

    /** Tope de mensajes devueltos por poll (evita respuestas gigantes ante un atasco). */
    private const MAX_POR_POLL = 60;

    private string $rutaRespaldo;

    public function __construct()
    {
        $this->rutaRespaldo = rtrim(defined('MVC_ROOT') ? MVC_ROOT : dirname(__DIR__, 4), '/\\')
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'videollamadas_signal';
    }

    // ────────────────────────────────────────────────────────────────────
    //  Presencia
    // ────────────────────────────────────────────────────────────────────

    /**
     * Anuncia (o refresca) que un participante está en la sala.
     * Se llama al entrar y luego en cada poll, a modo de latido.
     */
    public function marcarPresencia(int $idSala, string $peerId, array $datos): void
    {
        $valor = $datos + ['peer_id' => $peerId, 'ts' => time()];

        if (Cache::disponible()) {
            Cache::set($this->clavePeer($idSala, $peerId), $valor, self::TTL_PRESENCIA);
            return;
        }

        $this->escribirArchivo($this->rutaSala($idSala) . '/peer_' . $this->sanear($peerId) . '.json', $valor);
    }

    /** Quita al participante de la sala (salida explícita). */
    public function quitarPresencia(int $idSala, string $peerId): void
    {
        if (Cache::disponible()) {
            Cache::delete($this->clavePeer($idSala, $peerId));
            return;
        }

        $archivo = $this->rutaSala($idSala) . '/peer_' . $this->sanear($peerId) . '.json';
        if (is_file($archivo)) {
            @unlink($archivo);
        }
    }

    /**
     * Participantes vivos en la sala, excluyendo a quien pregunta.
     * "Vivo" = marcó presencia dentro de la ventana de TTL_PRESENCIA.
     */
    public function getPresentes(int $idSala, string $excluirPeerId = ''): array
    {
        $limite = time() - self::TTL_PRESENCIA;
        $out = [];

        foreach ($this->leerTodos($idSala, 'peer') as $valor) {
            if (!is_array($valor) || empty($valor['peer_id'])) {
                continue;
            }
            if ($valor['peer_id'] === $excluirPeerId) {
                continue;
            }
            if ((int) ($valor['ts'] ?? 0) < $limite) {
                continue;
            }
            $out[] = $valor;
        }

        return $out;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Mensajes (oferta, respuesta, candidatos ICE, salida)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Deja un mensaje en el buzón de la sala.
     *
     * @param string $para Peer destinatario, o '' para difundir a todos.
     * @param string $tipo offer | answer | ice | bye | renegociar
     * @return int Número de secuencia asignado al mensaje.
     */
    public function enviar(int $idSala, string $de, string $para, string $tipo, array $payload): int
    {
        $seq = $this->siguienteSecuencia($idSala);

        $mensaje = [
            'seq'     => $seq,
            'de'      => $de,
            'para'    => $para,
            'tipo'    => $tipo,
            'payload' => $payload,
            'ts'      => time(),
        ];

        if (Cache::disponible()) {
            Cache::set($this->claveMensaje($idSala, $seq), $mensaje, self::TTL_MENSAJE);
        } else {
            $this->escribirArchivo($this->rutaSala($idSala) . '/msg_' . str_pad((string) $seq, 12, '0', STR_PAD_LEFT) . '.json', $mensaje);
        }

        return $seq;
    }

    /**
     * Mensajes dirigidos a un participante con secuencia mayor que $desde.
     *
     * @return array{mensajes: array, cursor: int}
     */
    public function recibir(int $idSala, string $peerId, int $desde): array
    {
        $mensajes = [];
        $cursor   = $desde;

        foreach ($this->leerTodos($idSala, 'msg') as $m) {
            if (!is_array($m) || !isset($m['seq'])) {
                continue;
            }
            $seq = (int) $m['seq'];
            if ($seq <= $desde) {
                continue;
            }

            // El cursor avanza sobre TODOS los mensajes vistos, no solo los
            // propios: si no, los ajenos se revisarían en cada poll para siempre.
            if ($seq > $cursor) {
                $cursor = $seq;
            }

            if ($m['de'] === $peerId) {
                continue; // lo mandó él mismo
            }
            if ($m['para'] !== '' && $m['para'] !== $peerId) {
                continue; // es para otro
            }

            $mensajes[] = $m;
        }

        usort($mensajes, static fn (array $a, array $b): int => $a['seq'] <=> $b['seq']);

        if (count($mensajes) > self::MAX_POR_POLL) {
            $mensajes = array_slice($mensajes, 0, self::MAX_POR_POLL);
            $cursor   = (int) end($mensajes)['seq'];
        }

        return ['mensajes' => $mensajes, 'cursor' => $cursor];
    }

    /**
     * Secuencia actual de la sala. El navegador la pide al entrar para no
     * recibir el historial de mensajes anteriores a su llegada.
     */
    public function getSecuenciaActual(int $idSala): int
    {
        if (Cache::disponible()) {
            return (int) (Cache::get($this->claveSeq($idSala)) ?? 0);
        }

        $archivos = glob($this->rutaSala($idSala) . '/msg_*.json') ?: [];
        return count($archivos) > 0 ? (int) $this->seqDesdeNombre(end($archivos)) : 0;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Interno
    // ────────────────────────────────────────────────────────────────────

    /**
     * Contador atómico por sala. apcu_inc es atómico entre procesos, así que dos
     * peticiones simultáneas nunca reciben el mismo número.
     */
    private function siguienteSecuencia(int $idSala): int
    {
        if (Cache::disponible()) {
            $clave = $this->claveSeq($idSala);
            $ok = false;
            $seq = apcu_inc($clave, 1, $ok);
            if (!$ok) {
                // Primera vez: apcu_add solo crea si no existe, así que dos
                // procesos simultáneos no se pisan.
                apcu_add($clave, 0, 3600);
                $seq = apcu_inc($clave, 1);
            }
            return (int) $seq;
        }

        // Sin APCu: el número sale del reloj en microsegundos, que basta para
        // ordenar mensajes en un entorno de desarrollo de un solo usuario.
        return (int) (microtime(true) * 1000);
    }

    /** Lee todas las entradas de un tipo ('peer' o 'msg') de la sala. */
    private function leerTodos(int $idSala, string $tipo): array
    {
        if (Cache::disponible()) {
            if (!class_exists('\APCuIterator')) {
                return [];
            }
            $patron = '/^' . preg_quote($this->prefijo($idSala) . $tipo . ':', '/') . '/';
            $out = [];
            foreach (new \APCuIterator($patron) as $item) {
                $out[] = $item['value'];
            }
            return $out;
        }

        $archivos = glob($this->rutaSala($idSala) . '/' . $tipo . '_*.json') ?: [];
        $out = [];
        foreach ($archivos as $archivo) {
            // Limpieza perezosa del respaldo en disco.
            if (filemtime($archivo) < time() - self::TTL_MENSAJE) {
                @unlink($archivo);
                continue;
            }
            $contenido = @file_get_contents($archivo);
            $valor = $contenido !== false ? json_decode($contenido, true) : null;
            if (is_array($valor)) {
                $out[] = $valor;
            }
        }
        return $out;
    }

    private function escribirArchivo(string $ruta, array $valor): void
    {
        $dir = dirname($ruta);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($ruta, json_encode($valor, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function seqDesdeNombre(string $ruta): string
    {
        return preg_replace('/\D/', '', basename($ruta)) ?: '0';
    }

    private function rutaSala(int $idSala): string
    {
        return $this->rutaRespaldo . DIRECTORY_SEPARATOR . 's' . $idSala;
    }

    private function prefijo(int $idSala): string
    {
        return 'vc:s' . $idSala . ':';
    }

    private function clavePeer(int $idSala, string $peerId): string
    {
        return $this->prefijo($idSala) . 'peer:' . $this->sanear($peerId);
    }

    private function claveMensaje(int $idSala, int $seq): string
    {
        return $this->prefijo($idSala) . 'msg:' . str_pad((string) $seq, 12, '0', STR_PAD_LEFT);
    }

    private function claveSeq(int $idSala): string
    {
        return $this->prefijo($idSala) . 'seq';
    }

    /** El peer id viene del navegador: se limpia antes de usarlo como clave. */
    private function sanear(string $valor): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $valor) ?? '';
    }
}
