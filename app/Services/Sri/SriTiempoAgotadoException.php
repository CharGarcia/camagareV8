<?php

declare(strict_types=1);

namespace App\Services\Sri;

/**
 * Se agotó el tiempo máximo total de un envío al SRI (SriEnvioService::TIEMPO_MAXIMO_SEGUNDOS).
 *
 * No es un rechazo ni un error del comprobante: el sistema dejó de ESPERAR. El
 * documento queda pendiente y SriReintentosPendientesService (cron, cada 5 min)
 * termina de consultarlo o de enviarlo. El mensaje está pensado para el usuario.
 */
class SriTiempoAgotadoException extends \RuntimeException
{
}
