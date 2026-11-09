<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Meta respondió que el acceso de la cuenta ya no sirve (error 190: vencido,
 * revocado, o el cliente quitó la app de su portafolio). Quien lo atrapa marca el
 * canal como «token_vencido» y deja de reintentar: solo se arregla reconectando.
 * Diseño: docs/diseno/crm-redes-unified-login.md (decisión 5).
 */
class InstagramTokenInvalidoException extends \RuntimeException
{
}
