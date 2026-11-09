<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/** CRM: reglas de contactos. */
class CrmContactoRules
{
    public function validar(array $d): void
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre del contacto es obligatorio.');
        }
        if (mb_strlen($nombre) > 200) {
            throw new \InvalidArgumentException('El nombre no puede superar 200 caracteres.');
        }
        $correo = trim((string) ($d['correo'] ?? ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo no tiene un formato válido.');
        }
        $url = trim((string) ($d['linkedin_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://([a-z0-9-]+\.)*linkedin\.com/#i', $url)) {
            throw new \InvalidArgumentException('El perfil de LinkedIn debe ser un enlace de linkedin.com.');
        }
        $tel = trim((string) ($d['telefono'] ?? ''));
        if ($tel !== '' && !preg_match('/^[0-9+()\-\s]{6,30}$/', $tel)) {
            throw new \InvalidArgumentException('El teléfono solo puede tener números, espacios y los signos + ( ) -.');
        }
        // Sin contacto posible no hay seguimiento que hacer.
        $hayCanal = $correo !== '' || $tel !== '' || $url !== ''
            || trim((string) ($d['instagram_usuario'] ?? '')) !== '' || trim((string) ($d['instagram_id'] ?? '')) !== '';
        if (!$hayCanal) {
            throw new \InvalidArgumentException('Indique al menos un medio de contacto: teléfono, correo, Instagram o LinkedIn.');
        }
    }

    public function validarEliminar(int $oportunidades): void
    {
        if ($oportunidades > 0) {
            throw new \InvalidArgumentException(
                "El contacto tiene {$oportunidades} oportunidad(es). Elimínelas o asígnelas a otro contacto primero."
            );
        }
    }
}
