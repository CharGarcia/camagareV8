<?php
declare(strict_types=1);

namespace App\Services\modulos\Transferencias;

use App\Services\modulos\Transferencias\Formatters\TransferenciaFormatoGenericoExcel;
use App\Services\modulos\Transferencias\Formatters\TransferenciaFormatoProdubanco;

/**
 * Resuelve el formateador de archivo según el nombre del banco (bancos_ecuador.nombre_banco
 * del banco elegido como "formato" del lote). Sin una implementación específica, cae al
 * formato genérico Excel. Agregar un banco nuevo = una clase que implemente
 * TransferenciaFormatterInterface + un caso nuevo en el match de abajo (no requiere tocar
 * el resto del módulo).
 */
class TransferenciaFormatterFactory
{
    public static function getFormatter(?string $nombreBanco): TransferenciaFormatterInterface
    {
        return match (strtoupper(trim((string) $nombreBanco))) {
            // Banco Promerica se fusionó con Produbanco; comparten el mismo formato.
            'PRODUBANCO', 'PROMERICA' => new TransferenciaFormatoProdubanco(),
            // Ejemplo futuro: 'PICHINCHA' => new TransferenciaFormatoPichincha(),
            default => new TransferenciaFormatoGenericoExcel(),
        };
    }
}
