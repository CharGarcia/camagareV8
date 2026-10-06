<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Catálogo de módulos en los que el administrador puede limitar, por usuario,
 * de qué vendedores se ve la información (tarjeta "Vendedores que puede ver —
 * {módulo}" de /config/permisos-modulos). Hermano de PestanasModulo.
 *
 * Regla: por defecto el usuario ve a todos los vendedores; desmarcar uno oculta
 * sus ventas y lo quita del selector Vendedor del módulo. Solo cambia algo para
 * un usuario de nivel 1 que ve toda la empresa (Acceso total): quien no lo
 * tiene ya ve solo lo de su vendedor (§6) y esta lista no le aplica. Niveles 2
 * y 3 ven a todos siempre.
 *
 * Para habilitar otro módulo: agregar su entrada aquí con la ruta MVC como
 * clave y, en su controlador, pasar el alcance por
 * AlcanceRegistros::acotarAVendedoresVisibles($alcance, $idUsuario, $idsEmpresa, $ruta)
 * (ver ReporteVentasController::alcanceUsuario()). La tarjeta en
 * permisos-modulos aparece sola. Los vendedores ocultos se guardan en
 * `usuarios_vendedores_ocultos` (database/2026-10-06_usuarios_vendedores_ocultos.sql).
 */
final class VendedoresModulo
{
    /** @var array<string, array{titulo:string, icono:string}> */
    public const CATALOGO = [
        'modulos/reporte_ventas' => [
            'titulo' => 'Reporte de Ventas',
            'icono'  => 'bi-file-earmark-bar-graph',
        ],
        'modulos/cuentas_por_cobrar' => [
            'titulo' => 'Cuentas por Cobrar',
            'icono'  => 'bi-wallet2',
        ],
    ];

    public static function catalogo(): array
    {
        return self::CATALOGO;
    }

    public static function definicion(string $modulo): ?array
    {
        return self::CATALOGO[$modulo] ?? null;
    }
}
