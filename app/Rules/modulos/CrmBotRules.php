<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * CRM fase 2: reglas del asistente virtual (docs/diseno/crm-redes-fase2.md).
 *
 * - Solo mensajes directos, solo dentro de las 24 h del último mensaje del
 *   cliente: la etiqueta HUMAN_AGENT es para personas, nunca para el bot.
 * - Solo si el último mensaje es del cliente (si ya respondió una persona o el
 *   propio bot, no hay nada que contestar).
 * - Horario «fuera_horario»: no responde dentro del horario de atención.
 * - Topes por conversación y por día: al llegar, traspaso a una persona.
 */
class CrmBotRules
{
    public const SEGUNDOS_AGRUPAR = 8;      // espera para juntar mensajes seguidos del cliente (luego responde el proceso en vivo)
    public const SEGUNDOS_REINTENTO = 120;  // tras un fallo del proveedor
    public const MAX_INTENTOS = 2;          // fallos seguidos antes de pasar a una persona

    /**
     * Decide qué hacer con una conversación tomada de la cola.
     * @return array{accion:'responder'|'omitir'|'traspasar', motivo:?string}
     */
    public function decidir(array $conv, ?array $config, array $historial, int $respuestasHoy, ?int $ahora = null): array
    {
        $ahora ??= time();
        if (!$config || !$config['activo']) {
            return ['accion' => 'omitir', 'motivo' => 'El asistente no está activo en esta cuenta.'];
        }
        if (($conv['bot_estado'] ?? '') !== 'activo') {
            return ['accion' => 'omitir', 'motivo' => 'El asistente está pausado en esta conversación.'];
        }
        if (($conv['tipo'] ?? '') !== 'dm') {
            return ['accion' => 'omitir', 'motivo' => 'El asistente solo responde mensajes directos.'];
        }
        if (($conv['canal_estado'] ?? '') !== 'activo') {
            return ['accion' => 'omitir', 'motivo' => 'La cuenta de Instagram no está conectada.'];
        }
        $ultimo = end($historial) ?: null;
        if (!$ultimo || $ultimo['direccion'] !== 'IN') {
            return ['accion' => 'omitir', 'motivo' => 'No hay mensaje del cliente pendiente de respuesta.'];
        }
        $entrada = $conv['ultima_entrada_at'] ?? null;
        if (!$entrada || $ahora - strtotime((string) $entrada) > CrmMensajeRules::HORAS_VENTANA * 3600) {
            return ['accion' => 'omitir', 'motivo' => 'Fuera de la ventana de 24 h: solo una persona puede responder.'];
        }
        if ($config['horario'] === 'fuera_horario' && $this->dentroDeHorario($config['horario_atencion'], $ahora)) {
            return ['accion' => 'omitir', 'motivo' => 'Horario de atención: responde el equipo.'];
        }
        if ((int) ($conv['bot_respuestas'] ?? 0) >= (int) $config['max_respuestas_conv']) {
            return ['accion' => 'traspasar', 'motivo' => 'El asistente llegó a su máximo de respuestas en esta conversación.'];
        }
        if ($respuestasHoy >= (int) $config['max_respuestas_dia']) {
            return ['accion' => 'traspasar', 'motivo' => 'El asistente llegó a su tope de respuestas del día.'];
        }
        return ['accion' => 'responder', 'motivo' => null];
    }

    /**
     * Configuración del asistente que llega de la pantalla. Devuelve los datos
     * limpios listos para guardar.
     */
    public function validarConfig(array $d): array
    {
        $out = [];
        $out['activo'] = in_array($d['activo'] ?? false, [true, 1, '1', 'true', 'on'], true);
        $out['nombre_asistente'] = trim((string) ($d['nombre_asistente'] ?? '')) ?: 'Asistente virtual';
        if (mb_strlen($out['nombre_asistente']) > 60) {
            throw new \InvalidArgumentException('El nombre del asistente admite hasta 60 caracteres.');
        }
        $out['presentacion'] = trim((string) ($d['presentacion'] ?? '')) ?: null;
        if ($out['presentacion'] !== null && mb_strlen($out['presentacion']) > 500) {
            throw new \InvalidArgumentException('La presentación admite hasta 500 caracteres.');
        }
        $out['tono'] = in_array($d['tono'] ?? '', ['cercano', 'formal'], true) ? $d['tono'] : 'cercano';
        $out['horario'] = in_array($d['horario'] ?? '', ['siempre', 'fuera_horario'], true) ? $d['horario'] : 'siempre';
        $out['horario_atencion'] = $this->validarHorario(is_array($d['horario_atencion'] ?? null) ? $d['horario_atencion'] : []);
        if ($out['horario'] === 'fuera_horario' && !$out['horario_atencion']) {
            throw new \InvalidArgumentException('Indique el horario de atención (al menos un día) o elija «Siempre».');
        }
        $out['instrucciones'] = trim((string) ($d['instrucciones'] ?? '')) ?: null;
        if ($out['instrucciones'] !== null && mb_strlen($out['instrucciones']) > 4000) {
            throw new \InvalidArgumentException('Las instrucciones admiten hasta 4000 caracteres.');
        }
        $out['temas_prohibidos'] = trim((string) ($d['temas_prohibidos'] ?? '')) ?: null;
        if ($out['temas_prohibidos'] !== null && mb_strlen($out['temas_prohibidos']) > 1000) {
            throw new \InvalidArgumentException('Los temas prohibidos admiten hasta 1000 caracteres.');
        }
        $out['mostrar_stock'] = in_array($d['mostrar_stock'] ?? false, [true, 1, '1', 'true', 'on'], true);
        $out['crear_oportunidades'] = in_array($d['crear_oportunidades'] ?? false, [true, 1, '1', 'true', 'on'], true);
        $out['max_respuestas_conv'] = (int) ($d['max_respuestas_conv'] ?? 6);
        $out['max_respuestas_dia'] = (int) ($d['max_respuestas_dia'] ?? 300);
        if ($out['max_respuestas_conv'] < 1 || $out['max_respuestas_conv'] > 50) {
            throw new \InvalidArgumentException('Las respuestas por conversación deben estar entre 1 y 50.');
        }
        if ($out['max_respuestas_dia'] < 1 || $out['max_respuestas_dia'] > 100000) {
            throw new \InvalidArgumentException('Las respuestas por día deben estar entre 1 y 100000.');
        }
        return $out;
    }

    /** {"1":["08:00","17:00"], …}: días 1-7, horas HH:MM, inicio antes que fin. */
    private function validarHorario(array $h): array
    {
        $out = [];
        foreach ($h as $dia => $tramo) {
            $dia = (string) $dia;
            if (!preg_match('/^[1-7]$/', $dia) || !is_array($tramo) || count($tramo) < 2) {
                continue;
            }
            [$ini, $fin] = [trim((string) $tramo[0]), trim((string) $tramo[1])];
            if ($ini === '' && $fin === '') {
                continue;
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $ini) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fin) || $ini >= $fin) {
                throw new \InvalidArgumentException('Horario no válido: use HH:MM y que la hora de inicio sea antes que la de fin.');
            }
            $out[$dia] = [$ini, $fin];
        }
        ksort($out);
        return $out;
    }

    /** $horario: {"1":["08:00","17:00"], …} con 1 = lunes … 7 = domingo. */
    public function dentroDeHorario(array $horario, int $ahora): bool
    {
        $dia = $horario[(string) date('N', $ahora)] ?? null;
        if (!is_array($dia) || count($dia) < 2) {
            return false;
        }
        $hm = date('H:i', $ahora);
        return $hm >= (string) $dia[0] && $hm < (string) $dia[1];
    }

    /**
     * Datos que el cliente dio en el chat (herramienta actualizar_contacto).
     * Devuelve solo los válidos; lanza si no queda ninguno.
     */
    public function validarDatosContacto(array $in): array
    {
        $out = [];
        $nombre = trim((string) ($in['nombre'] ?? ''));
        if ($nombre !== '' && mb_strlen($nombre) <= 200) {
            $out['nombre'] = $nombre;
        }
        $tel = trim((string) ($in['telefono'] ?? ''));
        if ($tel !== '' && preg_match('/^[0-9+()\-\s]{6,30}$/', $tel)) {
            $out['telefono'] = $tel;
        }
        $correo = mb_strtolower(trim((string) ($in['correo'] ?? '')));
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $out['correo'] = $correo;
        }
        if (!$out) {
            throw new \InvalidArgumentException('Ningún dato válido: revise el formato del teléfono o del correo.');
        }
        return $out;
    }

    /** Texto que el bot va a enviar: no vacío y dentro del tope de Instagram. */
    public function textoEnviable(string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        if (strlen($texto) > \App\Services\InstagramApiService::MAX_BYTES_MENSAJE) {
            $texto = mb_strcut($texto, 0, \App\Services\InstagramApiService::MAX_BYTES_MENSAJE - 3, 'UTF-8') . '…';
        }
        return $texto;
    }
}
