<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;

/**
 * Relaciones de una factura de venta con OTROS módulos (proforma, consignación,
 * recibo, etc.). Solo se devuelven las relaciones que TIENEN datos para la factura.
 *
 * Diseño EXTENSIBLE: cada módulo aporta un "proveedor" en proveedores() con:
 *   - key     : identificador (se usa en la pestaña).
 *   - label   : título de la pestaña.
 *   - icono   : clase Bootstrap Icons.
 *   - permiso : ruta del módulo (opcional; para futuros chequeos de permiso).
 *   - resumen : callable(array $factura, int $idEmpresa) => ?array
 *               Devuelve null si la factura NO tiene relación con ese módulo, o un
 *               array de resumen: ['numero'=>.., 'campos'=>[['label','valor'],...],
 *               'url_abrir'=>..].
 *
 * Para agregar un módulo nuevo: añade un proveedor aquí. Cero cambios en el modal.
 */
class FacturaRelacionesService
{
    /**
     * @return array<int, array{key:string,label:string,icono:string,permiso:?string,resumen:array}>
     */
    public function getRelaciones(array $factura, int $idEmpresa): array
    {
        $out = [];
        foreach ($this->proveedores() as $prov) {
            try {
                $resumen = ($prov['resumen'])($factura, $idEmpresa);
            } catch (\Throwable $e) {
                error_log('[FacturaRelaciones] ' . ($prov['key'] ?? '?') . ': ' . $e->getMessage());
                $resumen = null;
            }
            if (!empty($resumen)) {
                $out[] = [
                    'key'     => $prov['key'],
                    'label'   => $prov['label'],
                    'icono'   => $prov['icono'],
                    'permiso' => $prov['permiso'] ?? null,
                    'resumen' => $resumen,
                ];
            }
        }
        return $out;
    }

    /** Registro de proveedores de relación. Agregar aquí los módulos futuros. */
    private function proveedores(): array
    {
        return [
            [
                'key'     => 'proforma',
                'label'   => 'Proforma',
                'icono'   => 'bi-file-earmark-text',
                'permiso' => 'modulos/proformas',
                'resumen' => [$this, 'resumenProforma'],
            ],
            // Futuro (agregar cuando se pidan):
            // consignación (consignaciones_facturas), recibo de venta
            // (recibos_venta_cabecera.id_factura_origen), etc.
        ];
    }

    // ── Proveedores ─────────────────────────────────────────────────────────

    /** Proforma de origen: ventas_cabecera.id_proforma. */
    private function resumenProforma(array $factura, int $idEmpresa): ?array
    {
        $idProforma = (int) ($factura['id_proforma'] ?? 0);
        if ($idProforma <= 0) {
            return null;
        }

        $db = Database::getConnection();
        $st = $db->prepare(
            "SELECT p.id, p.establecimiento, p.punto_emision, p.secuencial, p.fecha_emision,
                    p.estado, p.importe_total, c.nombre AS cliente_nombre
             FROM proformas_cabecera p
             LEFT JOIN clientes c ON c.id = p.id_cliente
             WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false"
        );
        $st->execute([':id' => $idProforma, ':e' => $idEmpresa]);
        $p = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$p) {
            return null;
        }

        $numero = ($p['establecimiento'] ?? '') . '-'
                . ($p['punto_emision'] ?? '') . '-'
                . str_pad((string) ($p['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

        return [
            'numero' => $numero,
            'campos' => [
                ['label' => 'Número',  'valor' => $numero],
                ['label' => 'Fecha',   'valor' => !empty($p['fecha_emision']) ? date('d-m-Y', strtotime($p['fecha_emision'])) : '—'],
                ['label' => 'Estado',  'valor' => ucfirst((string) ($p['estado'] ?? ''))],
                ['label' => 'Cliente', 'valor' => $p['cliente_nombre'] ?? '—'],
                ['label' => 'Total',   'valor' => '$ ' . number_format((float) ($p['importe_total'] ?? 0), 2)],
            ],
            'url_abrir' => rtrim(BASE_URL, '/') . '/modulos/proformas?b=' . urlencode($numero),
        ];
    }
}
