<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\AccesoFrecuenteRepository;

/**
 * Accesos frecuentes de la pantalla Inicio: registra qué submódulos abre cada
 * usuario en cada empresa y devuelve los más usados últimamente (los usos
 * pierden la mitad de su peso cada 7 días; ver AccesoFrecuenteRepository).
 *
 * Trabaja sobre $menuModulos (ModuloMenu::getModulosConSubmodulos, ya filtrado
 * por permisos): solo se cuentan y se muestran submódulos que el usuario tiene
 * asignados hoy en la empresa activa.
 *
 * Si la tabla aún no existe (SQL sin aplicar) no hace nada: degrada a "sin accesos".
 */
class AccesosFrecuentesService
{
    /** Rutas que no se cuentan ni se muestran como acceso. */
    private const RUTAS_EXCLUIDAS = ['modulos/dashboard', 'home/moduloenconstruccion'];

    private AccesoFrecuenteRepository $repository;

    public function __construct(?AccesoFrecuenteRepository $repository = null)
    {
        $this->repository = $repository ?? new AccesoFrecuenteRepository();
    }

    /**
     * Suma un uso al submódulo de la pantalla que se está abriendo, si es uno del menú.
     * Se llama al dibujar una página completa (Controller::getLayoutData).
     */
    public function registrarVisita(int $idUsuario, int $idEmpresa, array $menuModulos, string $pathActual): void
    {
        if ($idUsuario <= 0 || $idEmpresa <= 0 || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        $idSubmodulo = $this->submoduloDePath($menuModulos, $pathActual);
        if ($idSubmodulo === null) {
            return;
        }
        try {
            // Un error de SQL dentro de una transacción abierta la abortaría (25P02):
            // la estadística de uso nunca debe arriesgar un guardado.
            if ($this->repository->enTransaccion()) {
                return;
            }
            $this->repository->registrarUso($idUsuario, $idEmpresa, $idSubmodulo);
        } catch (\Throwable $e) {
            // Tabla sin crear u otro problema: no afecta a la pantalla.
        }
    }

    /**
     * Los submódulos más usados últimamente, listos para dibujar.
     *
     * @return array<int, array{id_submodulo:int, nombre:string, modulo:string, icono:string, href:string, nueva_pestana:bool}>
     */
    public function obtener(int $idUsuario, int $idEmpresa, array $menuModulos, int $limite = 12): array
    {
        if ($idUsuario <= 0 || $idEmpresa <= 0 || empty($menuModulos)) {
            return [];
        }
        try {
            // Se piden de más: algunos pueden haber perdido el permiso.
            $ids = $this->repository->idsMasUsados($idUsuario, $idEmpresa, $limite * 2);
        } catch (\Throwable $e) {
            return [];
        }

        $porId = [];
        foreach ($menuModulos as $mod) {
            foreach ($mod['submodulos'] ?? [] as $sub) {
                $ruta = (string) ($sub['ruta'] ?? '');
                if ($this->rutaExcluida($ruta)) {
                    continue;
                }
                $porId[(int) $sub['id_submodulo']] = $this->acceso($mod, $sub);
            }
        }

        $accesos = [];
        foreach ($ids as $id) {
            if (isset($porId[$id])) {
                $accesos[] = $porId[$id];
                if (count($accesos) >= $limite) {
                    break;
                }
            }
        }
        return $accesos;
    }

    /**
     * El dashboard como acceso (va primero en la pantalla Inicio), o null si no está
     * entre los submódulos del usuario. Nunca se cuenta como acceso frecuente.
     */
    public function accesoDashboard(array $menuModulos): ?array
    {
        foreach ($menuModulos as $mod) {
            foreach ($mod['submodulos'] ?? [] as $sub) {
                $p = strtolower(rtrim((string) (parse_url((string) ($sub['ruta'] ?? ''), PHP_URL_PATH) ?: ''), '/'));
                if (str_ends_with($p, '/modulos/dashboard')) {
                    return $this->acceso($mod, $sub);
                }
            }
        }
        return null;
    }

    private function acceso(array $mod, array $sub): array
    {
        $ruta = (string) ($sub['ruta'] ?? '');
        return [
            'id_submodulo'  => (int) $sub['id_submodulo'],
            'nombre'        => (string) ($sub['nombre_submodulo'] ?? ''),
            'modulo'        => (string) ($mod['nombre_modulo'] ?? ''),
            'icono'         => (string) ($sub['icono_submodulo'] ?? ''),
            'href'          => $ruta,
            'nueva_pestana' => function_exists('rutaAbrePestanaNueva') && rutaAbrePestanaNueva($ruta),
        ];
    }

    /**
     * Submódulo del menú que corresponde a la ruta abierta. Gana la coincidencia más
     * larga (/modulos/control-bancario/posfechados antes que /modulos/control-bancario),
     * igual que el resaltado del menú lateral.
     */
    private function submoduloDePath(array $menuModulos, string $pathActual): ?int
    {
        $pathActual = strtolower(rtrim($pathActual, '/'));
        if ($pathActual === '') {
            return null;
        }
        $mejor = null;
        $largoMejor = 0;
        foreach ($menuModulos as $mod) {
            foreach ($mod['submodulos'] ?? [] as $sub) {
                $ruta = (string) ($sub['ruta'] ?? '');
                if ($this->rutaExcluida($ruta)) {
                    continue;
                }
                $p = strtolower(rtrim((string) (parse_url($ruta, PHP_URL_PATH) ?: ''), '/'));
                if ($p === '') {
                    continue;
                }
                if (($pathActual === $p || str_starts_with($pathActual, $p . '/')) && strlen($p) > $largoMejor) {
                    $mejor = (int) $sub['id_submodulo'];
                    $largoMejor = strlen($p);
                }
            }
        }
        return $mejor;
    }

    private function rutaExcluida(string $ruta): bool
    {
        if ($ruta === '' || $ruta === '#' || preg_match('#^https?://#i', $ruta)) {
            return true;
        }
        $p = strtolower(rtrim((string) (parse_url($ruta, PHP_URL_PATH) ?: ''), '/'));
        foreach (self::RUTAS_EXCLUIDAS as $excluida) {
            if (str_ends_with($p, '/' . $excluida)) {
                return true;
            }
        }
        return false;
    }
}
