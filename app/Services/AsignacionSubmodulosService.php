<?php
/**
 * Lógica de negocio de la asignación masiva de submódulos (herramienta de superadmin).
 * Resuelve el conjunto de destinatarios (usuario+empresa) según el modo elegido,
 * arma la previsualización y aplica la asignación en lote con auditoría.
 */

declare(strict_types=1);

namespace App\Services;

use App\models\Empresa;
use App\models\EmpresaAsignada;
use App\models\PermisoSubmodulo;

class AsignacionSubmodulosService
{
    private EmpresaAsignada $modelEmpresaAsignada;
    private PermisoSubmodulo $modelPermiso;
    private Empresa $modelEmpresa;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->modelEmpresaAsignada = new EmpresaAsignada();
        $this->modelPermiso = new PermisoSubmodulo();
        $this->modelEmpresa = new Empresa();
        $this->logService = new LogSistemaService();
    }

    /**
     * Resuelve la lista deduplicada de destinos [id_usuario, nombre_usuario, id_empresa, nombre_empresa]
     * según el modo de selección. Los usuarios nivel 3 (superadmin) siempre quedan excluidos.
     */
    public function resolverDestinos(string $modo, array $params): array
    {
        $idEmpresaFiltro = (int) ($params['id_empresa_filtro'] ?? 0);

        $destinos = match ($modo) {
            'usuarios' => $this->resolverPorUsuarios($params['ids_usuario'] ?? [], $idEmpresaFiltro),
            'nivel'    => $this->resolverPorNivel((string) ($params['nivel'] ?? ''), $idEmpresaFiltro),
            'empresa'  => $this->resolverPorEmpresa((int) ($params['id_empresa'] ?? 0)),
            default    => [],
        };

        // Deduplicar por (id_usuario, id_empresa)
        $unicos = [];
        foreach ($destinos as $d) {
            $clave = $d['id_usuario'] . ':' . $d['id_empresa'];
            $unicos[$clave] = $d;
        }

        return array_values($unicos);
    }

    private function resolverPorUsuarios(array $idsUsuario, int $idEmpresaFiltro): array
    {
        $destinos = [];
        foreach ($idsUsuario as $idUsuario) {
            $idUsuario = (int) $idUsuario;
            if ($idUsuario <= 0) continue;
            $usuario = $this->modelEmpresaAsignada->getUsuarioPorId($idUsuario);
            if (!$usuario || (int) ($usuario['nivel'] ?? 0) >= 3) continue;
            $destinos = array_merge($destinos, $this->expandirUsuarioAEmpresas($usuario, $idEmpresaFiltro));
        }
        return $destinos;
    }

    private function resolverPorNivel(string $nivel, int $idEmpresaFiltro): array
    {
        $niveles = match ($nivel) {
            '2'     => [2],
            '1'     => [1],
            'todos' => [1, 2],
            default => [],
        };
        if (empty($niveles)) return [];

        $usuarios = $this->modelEmpresaAsignada->getUsuariosPorNiveles($niveles);
        $destinos = [];
        foreach ($usuarios as $usuario) {
            $destinos = array_merge($destinos, $this->expandirUsuarioAEmpresas($usuario, $idEmpresaFiltro));
        }
        return $destinos;
    }

    private function resolverPorEmpresa(int $idEmpresa): array
    {
        if ($idEmpresa <= 0) return [];
        $empresa = $this->modelEmpresa->getPorId($idEmpresa);
        $nombreEmpresa = $empresa['nombre_comercial'] ?? $empresa['ruc'] ?? ('Empresa #' . $idEmpresa);

        $usuarios = $this->modelEmpresaAsignada->getUsuariosDeEmpresa($idEmpresa);
        $destinos = [];
        foreach ($usuarios as $u) {
            if ((int) ($u['nivel'] ?? 0) >= 3) continue;
            $destinos[] = [
                'id_usuario'      => (int) $u['id_usuario'],
                'nombre_usuario'  => (string) ($u['nombre'] ?? ''),
                'id_empresa'      => $idEmpresa,
                'nombre_empresa'  => (string) $nombreEmpresa,
            ];
        }
        return $destinos;
    }

    /** Expande un usuario (array con id_usuario|id, nombre) a una fila por cada empresa asignada. */
    private function expandirUsuarioAEmpresas(array $usuario, int $idEmpresaFiltro): array
    {
        $idUsuario = (int) ($usuario['id_usuario'] ?? $usuario['id'] ?? 0);
        if ($idUsuario <= 0) return [];

        $empresas = $this->modelEmpresaAsignada->getEmpresasDeUsuario($idUsuario);
        $destinos = [];
        foreach ($empresas as $e) {
            $idEmpresa = (int) $e['id_empresa'];
            if ($idEmpresaFiltro > 0 && $idEmpresa !== $idEmpresaFiltro) continue;
            $destinos[] = [
                'id_usuario'     => $idUsuario,
                'nombre_usuario' => (string) ($usuario['nombre'] ?? ''),
                'id_empresa'     => $idEmpresa,
                'nombre_empresa' => (string) ($e['nombre_comercial'] ?? $e['ruc'] ?? ('Empresa #' . $idEmpresa)),
            ];
        }
        return $destinos;
    }

    /**
     * Marca en cada destino si ya tiene el submódulo asignado y arma los totales.
     */
    public function previsualizar(int $idSubmodulo, array $destinos): array
    {
        $existentes = $this->modelPermiso->getAsignacionesExistentes($idSubmodulo);

        $nuevos = 0;
        $yaAsignados = 0;
        foreach ($destinos as &$d) {
            $clave = $d['id_usuario'] . ':' . $d['id_empresa'];
            $d['ya_asignado'] = isset($existentes[$clave]);
            $d['ya_asignado'] ? $yaAsignados++ : $nuevos++;
        }
        unset($d);

        return [
            'filas'        => $destinos,
            'total'        => count($destinos),
            'nuevos'       => $nuevos,
            'ya_asignados' => $yaAsignados,
        ];
    }

    /**
     * Aplica la asignación en lote y registra un log de auditoría resumen.
     *
     * @return array{insertados:int,actualizados:int,omitidos:int,total:int}
     */
    public function aplicar(
        int $idUsuarioActual,
        int $idModulo,
        int $idSubmodulo,
        string $nombreSubmodulo,
        array $destinos,
        array $permisos,
        bool $sobrescribir
    ): array {
        $resultado = $this->modelPermiso->asignarSubmoduloEnLote($idModulo, $idSubmodulo, $destinos, $permisos, $sobrescribir);
        $resultado['total'] = count($destinos);

        $this->logService->registrar(
            $idUsuarioActual,
            null,
            'asignacion_masiva_submodulo',
            'modulos_asignados',
            null,
            null,
            [
                'id_submodulo'     => $idSubmodulo,
                'nombre_submodulo' => $nombreSubmodulo,
                'permisos'         => $permisos,
                'sobrescribir'     => $sobrescribir,
                'resultado'        => $resultado,
                'destinos'         => array_map(
                    static fn (array $d) => ['id_usuario' => $d['id_usuario'], 'id_empresa' => $d['id_empresa']],
                    $destinos
                ),
            ]
        );

        return $resultado;
    }
}
