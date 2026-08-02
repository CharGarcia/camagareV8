<?php
declare(strict_types=1);

namespace App\Services\modulos\Transferencias;

use App\Services\modulos\Transferencias\Formatters\TransferenciaFormatoConfigurable;
use App\Services\modulos\Transferencias\Formatters\TransferenciaFormatoGenericoExcel;

/**
 * Resuelve el formateador de archivo a partir de una fila de
 * `transferencia_formatos` (catálogo configurable en /config/transferencia-formatos).
 * Si la fila trae `clase_formatter`, se instancia esa clase (escape hatch para un
 * layout que el motor genérico no pueda expresar). Si no, se usa el motor
 * genérico TransferenciaFormatoConfigurable, que arma el archivo a partir de
 * `campos`. Sin formato (lote viejo o formato eliminado), cae al Excel
 * genérico como red de seguridad.
 */
class TransferenciaFormatterFactory
{
    public static function getFormatter(?array $formato): TransferenciaFormatterInterface
    {
        if (!$formato) {
            return new TransferenciaFormatoGenericoExcel();
        }
        if (!empty($formato['clase_formatter']) && class_exists($formato['clase_formatter'])) {
            return new $formato['clase_formatter']();
        }
        return new TransferenciaFormatoConfigurable($formato);
    }
}
