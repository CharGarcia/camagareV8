<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Catálogo de módulos cuyas pestañas se pueden mostrar u ocultar por usuario
 * desde /config/permisos-modulos (tarjeta "Pestañas que puede ver").
 *
 * Es la única fuente de verdad de qué pestañas tiene cada módulo configurable:
 * la tarjeta de permisos las lista desde aquí, el módulo dibuja su barra de
 * pestañas desde aquí y las Rules rechazan cualquier clave que no esté aquí.
 *
 * Para habilitar otro módulo (reporte u otro con pestañas): agregar su entrada
 * al CATALOGO con la ruta MVC como clave; en su controlador, resolver las
 * pestañas con PestanasModuloService::visibles() y proteger cada acción de
 * pestaña con ese mismo resultado (ver ReporteInventariosController::
 * pestanasPermitidas() y requirePestana()); en su vista, dibujar la barra con
 * PestanasModulo::pestanas($rutaModulo). La tarjeta en permisos-modulos aparece
 * sola. Cómo se ve para el usuario: docs/manual/config/permisos-modulos.md.
 *
 * Las pestañas ocultas se guardan en `usuarios_pestanas_ocultas`
 * (database/2026-10-06_usuarios_pestanas_ocultas.sql): sin filas, el usuario
 * ve todas.
 */
final class PestanasModulo
{
    /** @var array<string, array{titulo:string, icono:string, pestanas: array<string, array{titulo:string, icono:string}>}> */
    public const CATALOGO = [
        'modulos/reporte_inventarios' => [
            'titulo'   => 'Reporte de Inventarios',
            'icono'    => 'bi-clipboard-data',
            'pestanas' => [
                'existencias'    => ['icono' => 'bi-box-seam',         'titulo' => 'Existencias'],
                'movimientos'    => ['icono' => 'bi-arrow-left-right', 'titulo' => 'Movimientos (Kardex)'],
                'valorizacion'   => ['icono' => 'bi-cash-coin',        'titulo' => 'Valorización'],
                'consignaciones' => ['icono' => 'bi-truck',            'titulo' => 'Consignaciones'],
                'auditoria'      => ['icono' => 'bi-shield-check',     'titulo' => 'Auditoría'],
            ],
        ],
    ];

    /** Todos los módulos configurables, en el orden en que se muestran las tarjetas. */
    public static function catalogo(): array
    {
        return self::CATALOGO;
    }

    /** Definición de un módulo (título, ícono y pestañas), o null si no es configurable. */
    public static function definicion(string $modulo): ?array
    {
        return self::CATALOGO[$modulo] ?? null;
    }

    /**
     * Pestañas de un módulo, en el orden de su barra: clave => [icono, titulo].
     * Vacío si el módulo no está en el catálogo.
     *
     * @return array<string, array{titulo:string, icono:string}>
     */
    public static function pestanas(string $modulo): array
    {
        return self::CATALOGO[$modulo]['pestanas'] ?? [];
    }

    public static function existe(string $modulo, string $pestana): bool
    {
        return isset(self::CATALOGO[$modulo]['pestanas'][$pestana]);
    }
}
