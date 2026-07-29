<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Reglas de negocio del módulo Videollamadas.
 *
 * La regla más importante es la de capacidad: el motor interno funciona por
 * malla P2P (cada navegador envía su video a todos los demás), así que el
 * número de participantes tiene un techo físico. Superarlo no "va lento": las
 * llamadas se caen. Por eso se valida aquí y no en la interfaz.
 */
class VideollamadaRules
{
    /** Tope duro del motor interno (mesh P2P). Sobre esto hay que usar proveedor externo. */
    public const MAX_PARTICIPANTES_MESH = 8;

    /** Punto donde la calidad ya se degrada de forma perceptible en mesh. */
    public const RECOMENDADO_MESH = 6;

    public function validar(array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            throw new Exception('El título de la reunión es obligatorio.');
        }
        if (mb_strlen($titulo) > 200) {
            throw new Exception('El título no puede superar los 200 caracteres.');
        }

        $tipo = $data['tipo'] ?? 'instantanea';
        if (!in_array($tipo, ['instantanea', 'programada', 'permanente'], true)) {
            throw new Exception('El tipo de sala no es válido.');
        }

        if (empty($data['id_anfitrion'])) {
            throw new Exception('Debe indicar quién es el anfitrión de la reunión.');
        }

        $this->validarFechas($data, $tipo);
        $this->validarCapacidad($data);

        $duracion = (int) ($data['duracion_minutos'] ?? 0);
        if ($duracion < 0) {
            throw new Exception('La duración no puede ser negativa.');
        }
        if ($duracion > 1440) {
            throw new Exception('La duración no puede superar las 24 horas.');
        }
    }

    /**
     * Una sala programada necesita fecha; una instantánea no.
     * La fecha de fin, si existe, debe ser posterior a la de inicio.
     */
    private function validarFechas(array $data, string $tipo): void
    {
        $inicio = trim((string) ($data['fecha_inicio'] ?? ''));
        $fin    = trim((string) ($data['fecha_fin'] ?? ''));

        if ($tipo === 'programada' && $inicio === '') {
            throw new Exception('Una reunión programada necesita fecha y hora de inicio.');
        }

        if ($inicio !== '' && strtotime($inicio) === false) {
            throw new Exception('La fecha de inicio no tiene un formato válido.');
        }

        if ($fin !== '') {
            if (strtotime($fin) === false) {
                throw new Exception('La fecha de fin no tiene un formato válido.');
            }
            if ($inicio !== '' && strtotime($fin) <= strtotime($inicio)) {
                throw new Exception('La fecha de fin debe ser posterior a la de inicio.');
            }
        }
    }

    /**
     * Valida el cupo contra el motor elegido.
     *
     * El motor interno no puede sostener reuniones grandes: con N participantes
     * cada navegador sube N-1 copias de su video. Si se necesita más cupo, la
     * sala debe crearse con un proveedor externo.
     */
    private function validarCapacidad(array $data): void
    {
        $max = (int) ($data['max_participantes'] ?? 6);
        if ($max < 2) {
            throw new Exception('Una reunión necesita al menos 2 participantes.');
        }
        if ($max > 100) {
            throw new Exception('El máximo de participantes no puede superar 100.');
        }

        $proveedor = $data['proveedor'] ?? 'interno';
        if ($proveedor === 'interno' && $max > self::MAX_PARTICIPANTES_MESH) {
            throw new Exception(
                'El motor interno admite hasta ' . self::MAX_PARTICIPANTES_MESH . ' participantes. '
                . 'Para reuniones más grandes hay que configurar un proveedor externo en la configuración del módulo.'
            );
        }
    }

    /**
     * Valida la lista de participantes de la sala.
     *
     * @param array $participantes Cada uno con id_usuario O nombre_invitado.
     */
    public function validarParticipantes(array $participantes, int $maxParticipantes, bool $permiteInvitados): void
    {
        if (count($participantes) > $maxParticipantes) {
            throw new Exception(
                'La sala admite ' . $maxParticipantes . ' participantes y se están agregando ' . count($participantes) . '.'
            );
        }

        $usuariosVistos = [];
        foreach ($participantes as $p) {
            $idUsuario = (int) ($p['id_usuario'] ?? 0);
            $nombre    = trim((string) ($p['nombre_invitado'] ?? ''));

            if ($idUsuario <= 0 && $nombre === '') {
                throw new Exception('Cada participante necesita un usuario del sistema o un nombre de invitado.');
            }

            if ($idUsuario <= 0 && !$permiteInvitados) {
                throw new Exception(
                    'La sala no permite invitados externos. Active la opción "Permitir invitados" o quite a "' . $nombre . '".'
                );
            }

            if ($idUsuario > 0) {
                if (isset($usuariosVistos[$idUsuario])) {
                    throw new Exception('Hay un usuario repetido en la lista de participantes.');
                }
                $usuariosVistos[$idUsuario] = true;
            }

            $email = trim((string) ($p['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El correo "' . $email . '" no es válido.');
            }

            $rol = $p['rol'] ?? 'participante';
            if (!in_array($rol, ['anfitrion', 'moderador', 'participante'], true)) {
                throw new Exception('El rol del participante no es válido.');
            }
        }
    }

    /** Una sala finalizada o cancelada ya no se edita. */
    public function validarEditable(array $sala): void
    {
        $estado = $sala['estado'] ?? 'programada';

        if ($estado === 'finalizada') {
            throw new Exception('La reunión ya finalizó y no se puede modificar. Su historial queda como registro.');
        }
        if ($estado === 'cancelada') {
            throw new Exception('La reunión está cancelada. Cree una nueva si necesita reprogramarla.');
        }
    }

    /**
     * Quién puede entrar a la sala.
     *
     * Se distingue ENTRAR de INICIAR:
     *   - El anfitrión (o alguien con acceso total) puede hacer ambas cosas.
     *   - Un participante invitado puede entrar cuando la reunión ya está en
     *     curso, pero no puede adelantarla si todavía está programada.
     *   - Quien no fue invitado no entra, aunque conozca el enlace.
     */
    public function validarPuedeEntrar(array $sala, int $idUsuario, bool $accesoTotal, bool $esParticipante): void
    {
        if ($sala['estado'] === 'finalizada' || $sala['estado'] === 'cancelada') {
            throw new Exception('Esta reunión ya no está disponible.');
        }

        if ($accesoTotal || (int) $sala['id_anfitrion'] === $idUsuario) {
            return;
        }

        if (!$esParticipante) {
            throw new Exception('No está invitado a esta reunión.');
        }

        if (($sala['estado'] ?? '') === 'programada') {
            throw new Exception('La reunión todavía no ha comenzado. El anfitrión debe iniciarla.');
        }
    }
}
