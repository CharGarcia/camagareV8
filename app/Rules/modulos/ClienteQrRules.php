<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones del QR de registro de clientes: formulario público (lo que escribe el
 * cliente) y configuración del impreso (título y mensaje).
 */
class ClienteQrRules
{
    /** Tipos que puede elegir el cliente: RUC, cédula y pasaporte (sin consumidor final). */
    public const TIPOS = ['05' => 'Cédula', '04' => 'RUC', '06' => 'Pasaporte'];

    public const MAX_TITULO  = 150;
    public const MAX_MENSAJE = 600;

    /**
     * Normaliza y valida el formulario público. Devuelve los datos limpios.
     * @throws \InvalidArgumentException con un mensaje apto para mostrar al cliente.
     */
    public function validarRegistroPublico(array $d): array
    {
        $tipo = trim((string) ($d['tipo_id'] ?? ''));
        if (!isset(self::TIPOS[$tipo])) {
            throw new \InvalidArgumentException('Elige el tipo de identificación.');
        }

        $ident = trim((string) ($d['identificacion'] ?? ''));
        if ($tipo !== '06') {
            $ident = preg_replace('/\D/', '', $ident);
        }
        if ($ident === '') {
            throw new \InvalidArgumentException('La identificación es obligatoria.');
        }
        // La identificación de Consumidor Final (todo 9) es un cliente genérico de la
        // empresa: el QR la actualizaría con el nombre y correo de quien la escriba.
        if (preg_match('/^9+$/', $ident)) {
            throw new \InvalidArgumentException('Ingresa tu propia cédula, RUC o pasaporte.');
        }
        // Solo largo y sufijo, sin dígito verificador: las cédulas/RUC nuevos que emite
        // el SRI ya no siempre lo cumplen (mismo criterio que ClienteRules).
        if ($tipo === '05' && strlen($ident) !== 10) {
            throw new \InvalidArgumentException('La cédula debe tener 10 dígitos.');
        }
        if ($tipo === '04') {
            if (strlen($ident) !== 13) {
                throw new \InvalidArgumentException('El RUC debe tener 13 dígitos.');
            }
            if (!in_array(substr($ident, -3), ['001', '002'], true)) {
                throw new \InvalidArgumentException('El RUC debe terminar en 001 o 002.');
            }
        }
        if ($tipo === '06' && (mb_strlen($ident) > 20 || !preg_match('/^[A-Za-z0-9-]+$/', $ident))) {
            throw new \InvalidArgumentException('El pasaporte solo admite letras, números y guiones (máximo 20).');
        }

        $nombre = trim(preg_replace('/\s+/u', ' ', (string) ($d['nombre'] ?? '')));
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }
        if (mb_strlen($nombre) > 300) {
            throw new \InvalidArgumentException('El nombre no puede superar 300 caracteres.');
        }

        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        if ($email === '') {
            throw new \InvalidArgumentException('El correo electrónico es obligatorio.');
        }
        if (mb_strlen($email) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo electrónico no tiene un formato válido.');
        }

        $telefono = trim((string) ($d['telefono'] ?? ''));
        if ($telefono !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $telefono)) {
            throw new \InvalidArgumentException('El teléfono solo admite números, espacios, +, - y paréntesis (6 a 20 caracteres).');
        }

        $direccion = trim(preg_replace('/\s+/u', ' ', (string) ($d['direccion'] ?? '')));
        if (mb_strlen($direccion) > 300) {
            throw new \InvalidArgumentException('La dirección no puede superar 300 caracteres.');
        }

        return [
            'tipo_id'        => $tipo,
            'identificacion' => strtoupper($ident),
            'nombre'         => $nombre,
            'email'          => $email,
            'telefono'       => $telefono,
            'direccion'      => $direccion,
        ];
    }

    /** Título y mensaje del impreso: opcionales, con tope de largo. */
    public function validarConfig(?string $titulo, ?string $mensaje): void
    {
        if ($titulo !== null && mb_strlen($titulo) > self::MAX_TITULO) {
            throw new \InvalidArgumentException('El título no puede superar ' . self::MAX_TITULO . ' caracteres.');
        }
        if ($mensaje !== null && mb_strlen($mensaje) > self::MAX_MENSAJE) {
            throw new \InvalidArgumentException('El mensaje no puede superar ' . self::MAX_MENSAJE . ' caracteres.');
        }
    }
}
