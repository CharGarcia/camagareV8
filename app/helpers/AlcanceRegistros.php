<?php

declare(strict_types=1);

namespace App\Helpers;

use App\repositories\modulos\VendedorRepository;

/**
 * Alcance de registros por usuario (§6) en los reportes comerciales (Reporte de
 * Ventas, Cuentas por Cobrar): resuelve QUÉ documentos puede ver quien consulta
 * cuando NO tiene el permiso de acceso total ('t') en el módulo.
 *
 * La regla, en orden:
 *   1. Acceso total (o nivel 3, que `Permisos::porRuta()` devuelve siempre con
 *      'todo'): sin restricción, ve toda la empresa.
 *   2. El usuario es un VENDEDOR (asesor): se resuelve con
 *      `VendedorRepository::getPorUsuario()` — vínculo explícito de la ficha del
 *      vendedor ("Usuario del sistema") o coincidencia de la cédula del usuario
 *      con la identificación del vendedor. Entonces ve su CARTERA: los
 *      documentos de los clientes que tiene asignados (`clientes.id_vendedor`)
 *      y, además, los documentos emitidos a su nombre (`id_vendedor` del
 *      documento), aunque el cliente esté asignado a otro asesor o a ninguno.
 *      En consolidado se resuelve un vendedor por establecimiento (la tabla es
 *      por empresa) y se juntan todos.
 *   3. Sin vendedor vinculado (un cajero, un digitador): ve solo los documentos
 *      que él mismo registró (`id_usuario` / `created_by`), como el resto de
 *      los módulos del sistema.
 *
 * Devuelve las dos claves que consumen los repositorios dentro de `$filtros`:
 *   - `id_vendedor_filtro`: int[]  (vacío = no aplica)
 *   - `id_usuario_filtro`:  ?int   (null = no aplica)
 * Solo una de las dos está activa a la vez. NUNCA se leen de la petición.
 */
final class AlcanceRegistros
{
    public const SIN_RESTRICCION = ['id_usuario_filtro' => null, 'id_vendedor_filtro' => []];

    /**
     * @param array $perm        Permisos del módulo (`getPermisos()`), se mira 'todo'.
     * @param int   $idUsuario   Usuario en sesión.
     * @param int[] $idsEmpresa  Alcance del reporte (una empresa o el grupo RUC).
     */
    public static function resolver(array $perm, int $idUsuario, array $idsEmpresa): array
    {
        if (!empty($perm['todo']) || $idUsuario <= 0) {
            return self::SIN_RESTRICCION;
        }

        $repo = new VendedorRepository();
        $idsVendedor = [];
        foreach ($idsEmpresa as $idEmpresa) {
            $v = $repo->getPorUsuario((int) $idEmpresa, $idUsuario);
            if ($v && (int) ($v['id'] ?? 0) > 0) {
                $idsVendedor[] = (int) $v['id'];
            }
        }
        if ($idsVendedor) {
            return ['id_usuario_filtro' => null, 'id_vendedor_filtro' => array_values(array_unique($idsVendedor))];
        }
        return ['id_usuario_filtro' => $idUsuario, 'id_vendedor_filtro' => []];
    }

    /** ¿Los filtros traen alguna restricción de alcance? */
    public static function restringe(array $filtros): bool
    {
        return self::idsVendedor($filtros) !== [] || self::idUsuario($filtros) > 0;
    }

    /** @return int[] Vendedores del alcance (vacío si no aplica el modo vendedor). */
    public static function idsVendedor(array $filtros): array
    {
        $ids = array_map('intval', (array) ($filtros['id_vendedor_filtro'] ?? []));
        return array_values(array_filter($ids, static fn (int $id) => $id > 0));
    }

    /** Usuario del modo "registros propios" (0 si no aplica). */
    public static function idUsuario(array $filtros): int
    {
        return (int) ($filtros['id_usuario_filtro'] ?? 0);
    }

    /**
     * ¿Un documento ya leído cae dentro del alcance? Para las acciones por id
     * (cobrar, historial, correo…), que sin esto llegarían a un documento que el
     * listado oculta.
     *
     * @param array  $registro           Fila del documento.
     * @param string $campoCreador       Columna del creador (`id_usuario` o `created_by`).
     * @param ?int   $idVendedorCliente  `clientes.id_vendedor` del cliente del documento
     *                                   (null si el llamador no lo trae).
     */
    public static function incluye(array $filtros, array $registro, string $campoCreador, ?int $idVendedorCliente = null): bool
    {
        $idsVend = self::idsVendedor($filtros);
        if ($idsVend) {
            $vDoc = (int) ($registro['id_vendedor'] ?? 0);
            $vCli = $idVendedorCliente ?? (int) ($registro['cliente_id_vendedor'] ?? 0);
            return ($vDoc > 0 && in_array($vDoc, $idsVend, true))
                || ($vCli > 0 && in_array($vCli, $idsVend, true));
        }
        $idUsuario = self::idUsuario($filtros);
        if ($idUsuario > 0) {
            return (int) ($registro[$campoCreador] ?? 0) === $idUsuario;
        }
        return true; // sin restricción
    }
}
