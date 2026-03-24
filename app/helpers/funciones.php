<?php
/**
 * Funciones adicionales
 */

/**
 * Genera un token seguro para recuperación de contraseña.
 */
function token_recuperar(): string
{
    return bin2hex(random_bytes(16));
}
