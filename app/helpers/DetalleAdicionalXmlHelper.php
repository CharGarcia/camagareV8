<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * "Adicional" de un ítem del XML del SRI (<detalle><detallesAdicionales><detAdicional>).
 *
 * Compras guarda ese bloque en compras_detalle.info_adicional (varchar 300), como un
 * solo texto: cada <detAdicional nombre="X" valor="Y"/> queda "X: Y" y varios se unen
 * con " | " (mismo separador que FacturaItemsPresentacionService al agrupar). Si el
 * nombre es el genérico "Detalle" que emite el propio sistema, se guarda solo el valor.
 *
 * Lo usan el registro automático (descargas del SRI, carga de XML) y la vista previa
 * del XML (ComprasService::parsearComprobanteXml), para que ambos lean lo mismo.
 */
final class DetalleAdicionalXmlHelper
{
    public const MAX_LARGO = 300;

    public static function texto(\SimpleXMLElement $detalle): string
    {
        if (!isset($detalle->detallesAdicionales->detAdicional)) {
            return '';
        }
        $partes = [];
        foreach ($detalle->detallesAdicionales->detAdicional as $da) {
            $nombre = trim((string) ($da['nombre'] ?? ''));
            $valor  = trim((string) ($da['valor'] ?? ''));
            if ($valor === '') {
                continue;
            }
            $partes[] = ($nombre === '' || strcasecmp($nombre, 'Detalle') === 0)
                ? $valor
                : $nombre . ': ' . $valor;
        }
        return mb_substr(implode(' | ', $partes), 0, self::MAX_LARGO);
    }
}
