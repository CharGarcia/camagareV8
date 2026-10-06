<?php

declare(strict_types=1);

namespace App\Helpers;

use App\repositories\modulos\VendedorRepository;

/**
 * Alcance de registros por usuario (§6) en los reportes comerciales (Reporte de
 * Ventas, Reporte de Ventas por Vendedor, Cuentas por Cobrar): resuelve QUÉ
 * documentos puede ver quien consulta cuando es de nivel 1 y NO tiene el permiso
 * de acceso total ('t') en el módulo.
 *
 * La regla, en orden:
 *   1. Nivel 2 (administrador) o 3 (superadministrador), o acceso total: sin
 *      restricción, ve toda la empresa. El nivel 2 no depende de 't': en estos
 *      reportes el administrador ve siempre todo, igual que el superadministrador.
 *   2. El usuario es un VENDEDOR (asesor): se resuelve con
 *      `VendedorRepository::getPorUsuario()` — vínculo explícito de la ficha del
 *      vendedor ("Usuario del sistema") o coincidencia de la cédula del usuario
 *      con la identificación del vendedor. Entonces ve SOLO LO DE SU VENDEDOR:
 *      cada documento es del vendedor que lleva (`id_vendedor` del documento) y,
 *      si no lleva ninguno, del vendedor asignado a su cliente
 *      (`clientes.id_vendedor`). Nunca ve documentos a nombre de otro vendedor,
 *      aunque el cliente sea suyo. En consolidado se resuelve un vendedor por
 *      establecimiento (la tabla es por empresa) y se juntan todos.
 *   3. Sin vendedor vinculado (un cajero, un digitador): ve solo los documentos
 *      que él mismo registró (`id_usuario` / `created_by`), como el resto de
 *      los módulos del sistema.
 *
 * Devuelve las dos claves que consumen los repositorios dentro de `$filtros`:
 *   - `id_vendedor_filtro`: int[]  (vacío = no aplica)
 *   - `id_usuario_filtro`:  ?int   (null = no aplica)
 * Solo una de las dos está activa a la vez. NUNCA se leen de la petición.
 *
 * Un documento "sin vendedor" es `id_vendedor` NULL o 0: los repositorios lo
 * comparan con COALESCE(id_vendedor, 0) = 0.
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
        if ((int) ($_SESSION['nivel'] ?? 1) >= 2 || !empty($perm['todo']) || $idUsuario <= 0) {
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

    /**
     * Condición SQL del alcance para concatenar al WHERE (" AND (...)"), o cadena
     * vacía si el usuario ve toda la empresa. Es la regla única que comparten
     * Cuentas por Cobrar y el buscador de documentos pendientes de Ingresos, para
     * que las dos pantallas muestren los mismos documentos al mismo usuario.
     *
     * Modo vendedor: manda el `id_vendedor` del documento; si no tiene (NULL o 0),
     * el vendedor asignado al cliente. Modo registros propios: `{alias}.{columna}`
     * igual al usuario.
     *
     * Cada llamada usa su propio prefijo de placeholder ($ph): varias consultas
     * combinan más de un WHERE en el mismo SQL. Dentro de una misma condición el
     * IN del vendedor se repite (cliente y documento); en este proyecto eso es
     * seguro (ver memoria pdo-placeholders-repetidos).
     *
     * @param string $columna          Columna del creador (`id_usuario` o `created_by`).
     * @param bool   $docTieneVendedor false para tablas sin `id_vendedor` (saldos
     *                                 iniciales, facturas de reembolso): entran solo
     *                                 por el vendedor del cliente.
     */
    public static function condicionSql(array $filtros, string $alias, string $columna, string $ph, array &$params, bool $docTieneVendedor = true): string
    {
        $idsVend = self::idsVendedor($filtros);
        if ($idsVend) {
            $in = [];
            foreach ($idsVend as $i => $id) {
                $in[] = ":{$ph}_v{$i}";
                $params[":{$ph}_v{$i}"] = $id;
            }
            $in = implode(',', $in);
            $clienteSuyo = "EXISTS (SELECT 1 FROM clientes {$ph}_c
                                    WHERE {$ph}_c.id = {$alias}.id_cliente AND {$ph}_c.id_vendedor IN ({$in}))";
            if (!$docTieneVendedor) {
                return " AND {$clienteSuyo}";
            }
            return " AND ({$alias}.id_vendedor IN ({$in})
                          OR (COALESCE({$alias}.id_vendedor, 0) = 0 AND {$clienteSuyo}))";
        }

        $idUsuario = self::idUsuario($filtros);
        if ($idUsuario <= 0) {
            return '';
        }
        $params[":{$ph}"] = $idUsuario;
        return " AND {$alias}.{$columna} = :{$ph}";
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
     * Quita el filtro Vendedor de la pantalla a un usuario restringido: su alcance
     * ya lo limita a su vendedor (o a sus registros), así un id de otro vendedor
     * enviado a mano no cambia nada, y no se pierden los documentos sin vendedor
     * de sus clientes, que el filtro por `id_vendedor` del documento dejaría fuera.
     * Llamar después de agregar el alcance a los filtros.
     */
    public static function limpiarFiltroVendedor(array $filtros): array
    {
        if (self::restringe($filtros)) {
            $filtros['id_vendedor'] = '';
        }
        return $filtros;
    }

    /**
     * Suma al alcance de un usuario restringido los vendedores adicionales que el
     * administrador le habilitó en /config/permisos-modulos ("Vendedores que puede
     * ver", tabla `usuarios_vendedores_visibles`). Hoy solo lo usa el Reporte de
     * Ventas por Vendedor. Sin restricción, o sin vendedores habilitados, devuelve
     * el alcance tal cual. Un usuario que no es vendedor pasa del modo "registros
     * propios" a ver lo de esos vendedores.
     *
     * @param int[] $idsEmpresa
     */
    public static function ampliarConVendedoresVisibles(array $alcance, int $idUsuario, array $idsEmpresa): array
    {
        if (!self::restringe($alcance)) {
            return $alcance;
        }
        $repo = new \App\repositories\modulos\VendedorVisibleRepository();
        $extra = [];
        foreach ($idsEmpresa as $idEmpresa) {
            $extra = array_merge($extra, $repo->getIdsVendedores((int) $idEmpresa, $idUsuario));
        }
        if (!$extra) {
            return $alcance;
        }
        return [
            'id_usuario_filtro'  => null,
            'id_vendedor_filtro' => array_values(array_unique(array_merge(self::idsVendedor($alcance), $extra))),
        ];
    }

    /**
     * Acota el alcance de un usuario que ve TODA la empresa a los vendedores que
     * el administrador le dejó visibles en /config/permisos-modulos ("Vendedores
     * que puede ver — {módulo}", tabla `usuarios_vendedores_ocultos`, catálogo
     * App\Helpers\VendedoresModulo). Sin vendedores ocultos devuelve el alcance
     * tal cual (ve a todos). A un usuario ya restringido (§6) no le cambia nada:
     * ya ve solo lo de su vendedor. El resultado es el modo vendedor normal
     * (`id_vendedor_filtro` = todos los vendedores vigentes de la empresa menos
     * los ocultos), así que los repositorios no necesitan nada nuevo: ve lo que
     * lleva el nombre de esos vendedores y, sin vendedor, lo de sus clientes.
     *
     * @param int[] $idsEmpresa
     */
    public static function acotarAVendedoresVisibles(array $alcance, int $idUsuario, array $idsEmpresa, string $modulo): array
    {
        if (self::restringe($alcance) || $idUsuario <= 0 || (int) ($_SESSION['nivel'] ?? 1) >= 2) {
            return $alcance;
        }
        $repo = new \App\repositories\modulos\VendedorOcultoRepository();
        $permitidos = [];
        $hayOcultos = false;
        foreach ($idsEmpresa as $idEmpresa) {
            $ocultos = $repo->getIdsOcultos((int) $idEmpresa, $idUsuario, $modulo);
            if (!$ocultos) {
                // Sin ocultos en este establecimiento: ve a todos los suyos.
                $permitidos = array_merge($permitidos, $repo->getIdsVendedoresEmpresa((int) $idEmpresa));
                continue;
            }
            $hayOcultos = true;
            $permitidos = array_merge($permitidos, array_diff($repo->getIdsVendedoresEmpresa((int) $idEmpresa), $ocultos));
        }
        if (!$hayOcultos) {
            return $alcance;
        }
        $permitidos = array_values(array_unique(array_filter(array_map('intval', $permitidos), static fn (int $id) => $id > 0)));
        if (!$permitidos) {
            // No debería pasar (las Rules no dejan ocultar al último), pero nunca
            // debe degradar a "ve todo": se restringe a sus registros propios.
            return ['id_usuario_filtro' => $idUsuario, 'id_vendedor_filtro' => []];
        }
        return ['id_usuario_filtro' => null, 'id_vendedor_filtro' => $permitidos];
    }

    /**
     * Filtro Vendedor de la pantalla para un usuario restringido que ve VARIOS
     * vendedores: si eligió uno de su alcance, el alcance se acota a ese
     * vendedor (conserva la regla "sin vendedor → vendedor del cliente"); si no,
     * se ignora. En ambos casos `id_vendedor` queda vacío, como en
     * limpiarFiltroVendedor(). Llamar después de agregar el alcance a los filtros.
     */
    public static function acotarAVendedorElegido(array $filtros): array
    {
        if (!self::restringe($filtros)) {
            return $filtros;
        }
        $elegido = (int) ($filtros['id_vendedor'] ?? 0);
        if ($elegido > 0 && in_array($elegido, self::idsVendedor($filtros), true)) {
            $filtros['id_vendedor_filtro'] = [$elegido];
        }
        $filtros['id_vendedor'] = '';
        return $filtros;
    }

    /**
     * Opciones del filtro Vendedor para un usuario restringido: solo su propio
     * vendedor en la empresa activa (0 o 1 fila con `id` y `nombre`). Quien no es
     * vendedor no tiene opciones. Sin restricción no se usa: ahí va el catálogo.
     */
    public static function vendedorPropio(array $alcance, int $idEmpresa, int $idUsuario): array
    {
        if (!self::idsVendedor($alcance)) {
            return [];
        }
        $v = (new VendedorRepository())->getPorUsuario($idEmpresa, $idUsuario);
        return $v ? [['id' => (int) $v['id'], 'nombre' => (string) $v['nombre']]] : [];
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
            // Manda el vendedor del documento; solo si no tiene, el de su cliente.
            $vDoc = (int) ($registro['id_vendedor'] ?? 0);
            $vDueno = $vDoc > 0 ? $vDoc : ($idVendedorCliente ?? (int) ($registro['cliente_id_vendedor'] ?? 0));
            return $vDueno > 0 && in_array($vDueno, $idsVend, true);
        }
        $idUsuario = self::idUsuario($filtros);
        if ($idUsuario > 0) {
            return (int) ($registro[$campoCreador] ?? 0) === $idUsuario;
        }
        return true; // sin restricción
    }
}
