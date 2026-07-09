<?php

declare(strict_types=1);

namespace App\Services\MigracionMysql;

use Throwable;

/**
 * Migración desde la BD MySQL del sistema anterior hacia el sistema nuevo (PostgreSQL).
 * Fase actual: ANÁLISIS/RESUMEN (solo lectura) — cuenta cuántos registros hay por
 * entidad para la empresa seleccionada, antes de migrar.
 * La transferencia real por entidad se implementa por fases (catálogos → documentos → cobros).
 */
class MigracionMysqlService
{
    /** Entidades migrables: clave => [label, tabla origen en MySQL]. Todas filtran por ruc_empresa. */
    public const ENTIDADES = [
        'clientes'          => ['label' => 'Clientes',                        'tabla' => 'clientes'],
        'productos'         => ['label' => 'Productos y servicios',           'tabla' => 'productos_servicios'],
        'proveedores'       => ['label' => 'Proveedores',                     'tabla' => 'proveedores'],
        'facturas'          => ['label' => 'Facturas de venta',               'tabla' => 'encabezado_factura'],
        'notas_credito'     => ['label' => 'Notas de crédito',                'tabla' => 'encabezado_nc'],
        'retenciones_venta' => ['label' => 'Retenciones en venta',            'tabla' => 'encabezado_retencion_venta'],
        'compras'           => ['label' => 'Compras',                         'tabla' => 'encabezado_compra'],
        'ingresos_egresos'  => ['label' => 'Cobros y pagos (ingresos/egresos)','tabla' => 'ingresos_egresos'],
    ];

    /**
     * Resumen de cuántos registros hay por entidad para la empresa (por RUC base,
     * incluye todos los establecimientos del contribuyente). Solo lectura.
     *
     * @param string[] $entidades  claves a incluir; vacío = todas
     * @return array<string,array{label:string,tabla:string,total:?int,error:?string}>
     */
    public function analizar(string $rucEmpresa, array $entidades = []): array
    {
        $pdo  = LegacyMysqlConnection::get();
        $base = substr(preg_replace('/\D+/', '', $rucEmpresa), 0, 10);

        $out = [];
        foreach (self::ENTIDADES as $key => $def) {
            if (!empty($entidades) && !in_array($key, $entidades, true)) {
                continue;
            }
            $fila = ['label' => $def['label'], 'tabla' => $def['tabla'], 'total' => null, 'error' => null];
            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM `{$def['tabla']}` WHERE LEFT(ruc_empresa, 10) = :b");
                $st->execute([':b' => $base]);
                $fila['total'] = (int) $st->fetchColumn();
            } catch (Throwable $e) {
                $fila['error'] = substr($e->getMessage(), 0, 140);
            }
            $out[$key] = $fila;
        }
        return $out;
    }
}
