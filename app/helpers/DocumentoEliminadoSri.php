<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Services\DocumentoSriRestauracionService;

/**
 * Listado de comprobantes electrónicos ELIMINADOS (filtro `estado:eliminada`, solo
 * superadministrador) para restaurarlos. Piezas comunes a los 7 módulos:
 *
 *  - Repository::getListado():  `$soloEliminadas = DocumentoEliminadoSri::extraerFiltro($filtros, $permitir);`
 *                               → `eliminado = TRUE` en vez de FALSE, y en el SELECT
 *                               `DocumentoEliminadoSri::sqlUltimaAccion('nota_credito', 'nc') AS sri_ultima_accion`.
 *  - Vista / searchAjax:        `if (DocumentoEliminadoSri::esEliminado($r)) { echo DocumentoEliminadoSri::fila(...); continue; }`
 *  - JS:                        nada: el clic lo atiende `public/js/app.js` (tr[data-restaurar-sri]).
 *
 * Ref.: Facturas de Venta (que además conserva su propia fila con todas las columnas).
 */
final class DocumentoEliminadoSri
{
    private const SINONIMOS = ['eliminada', 'eliminadas', 'eliminado', 'eliminados'];

    /**
     * ¿El buscador pide las eliminadas (`estado:eliminada`)? Solo si $permitir (superadmin):
     * entonces quita ese filtro de $filtros (no es un valor real de la columna estado) y
     * devuelve true. Para el resto el filtro sigue su curso y no encuentra nada.
     */
    public static function extraerFiltro(array &$filtros, bool $permitir): bool
    {
        if (!$permitir || !isset($filtros['estado']) || !empty($filtros['estado']['neg'])) {
            return false;
        }
        $vals = array_map(static fn($x) => strtolower(trim((string) $x)), (array) $filtros['estado']['valor']);
        if (!array_intersect($vals, self::SINONIMOS)) {
            return false;
        }
        unset($filtros['estado']);
        return true;
    }

    /** Subconsulta: última acción del SRI del documento en su ambiente (null = nunca se envió). */
    public static function sqlUltimaAccion(string $tipoLog, string $alias): string
    {
        if (!isset(DocumentoSriRestauracionService::TIPOS[$tipoLog]) || !preg_match('/^[a-z_][a-z0-9_]*$/', $alias)) {
            throw new \InvalidArgumentException('Tipo o alias no válido');
        }
        return "(SELECT l.accion FROM sri_envio_log l
                  WHERE l.tipo_comprobante = '{$tipoLog}' AND l.id_comprobante = {$alias}.id
                    AND l.tipo_ambiente = {$alias}.tipo_ambiente
                  ORDER BY l.id DESC LIMIT 1)";
    }

    /** ¿La fila del listado es un documento eliminado? (PDO devuelve el booleano como bool o 't'). */
    public static function esEliminado(array $r): bool
    {
        return in_array($r['eliminado'] ?? false, [true, 't', 'true', 1, '1'], true);
    }

    /** Etiqueta y color del último paso por el SRI. */
    public static function etiquetaSri(?string $accion): array
    {
        $accion = (string) $accion;
        return match ($accion) {
            ''                               => ['No enviada al SRI', 'secondary'],
            'autorizado', 'autorizada'       => ['SRI: AUTORIZADA', 'success'],
            'no_autorizado', 'no_autorizada' => ['SRI: NO AUTORIZADA', 'danger'],
            'recibida'                       => ['SRI: RECIBIDA', 'warning'],
            'en_procesamiento'               => ['SRI: EN PROCESAMIENTO', 'warning'],
            'devuelta'                       => ['SRI: DEVUELTA', 'secondary'],
            default                          => ['SRI: ' . strtoupper(str_replace('_', ' ', $accion)), 'secondary'],
        };
    }

    /**
     * Fila genérica de un documento eliminado, ocupando todo el ancho de la tabla ($colspan):
     * número tachado · fecha · tercero · total · [Eliminada] [estado SRI]. Al hacer clic,
     * app.js pide confirmación y llama a $urlRestaurar (POST id).
     */
    public static function fila(array $r, string $urlRestaurar, int $colspan, string $tercero = '', ?float $total = null): string
    {
        $esc    = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $numero = DocumentoSriRestauracionService::numero($r);
        $fecha  = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime((string) $r['fecha_emision'])) : '-';
        $elimEl = !empty($r['deleted_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['deleted_at'])) : '';
        [$sriTxt, $sriColor] = self::etiquetaSri($r['sri_ultima_accion'] ?? null);
        $estado = (string) ($r['estado'] ?? '');

        return '<tr class="cmg-doc-eliminado" role="button" tabindex="0" style="opacity:.65"'
            . ' data-restaurar-sri="' . $esc($urlRestaurar) . '" data-id="' . (int) ($r['id'] ?? 0) . '"'
            . ' data-numero="' . $esc($numero) . '" data-estado="' . $esc($estado) . '"'
            . ' title="Documento eliminado: clic para restaurarlo">'
            . '<td class="ps-3" colspan="' . max(1, $colspan) . '">'
            . '<div class="d-flex flex-wrap align-items-center gap-3 small">'
            . '<code class="text-secondary text-decoration-line-through">' . $esc($numero) . '</code>'
            . '<span>' . $fecha . '</span>'
            . ($tercero !== '' ? '<span class="fw-medium text-truncate" style="max-width:320px">' . $esc($tercero) . '</span>' : '')
            . ($total !== null ? '<span class="fw-bold">$' . number_format($total, 2) . '</span>' : '')
            . '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Eliminada'
            . ($elimEl ? ' el ' . $elimEl : '') . '</span>'
            . '<span class="badge bg-' . $sriColor . ' bg-opacity-10 text-' . $sriColor . ' border border-' . $sriColor . ' border-opacity-25">' . $esc($sriTxt) . '</span>'
            . '<span class="text-muted ms-auto pe-2"><i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar</span>'
            . '</div></td></tr>';
    }
}
