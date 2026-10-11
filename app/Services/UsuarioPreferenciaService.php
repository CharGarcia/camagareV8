<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\UsuarioPreferenciaRepository;

class UsuarioPreferenciaService
{
    private UsuarioPreferenciaRepository $repository;

    public function __construct(UsuarioPreferenciaRepository $repository)
    {
        $this->repository = $repository;
    }

    public function guardarPreferencia(int $idUsuario, int $idEmpresa, string $modulo, string $campo, $valor): void
    {
        if (trim($modulo) === '' || trim($campo) === '') {
            throw new \Exception("Módulo o campo inválido");
        }
        
        $this->repository->guardarPreferencia($idUsuario, $idEmpresa, $modulo, $campo, $valor);
    }

    public function obtenerPreferencias(int $idUsuario, int $idEmpresa, string $modulo): array
    {
        return $this->repository->obtenerPreferencias($idUsuario, $idEmpresa, $modulo);
    }

    /*
     * ── Menú de módulos: personalización POR USUARIO (igual en todas sus empresas) ──
     * Se guarda en la misma tabla usuarios_preferencias con id_empresa = 0, que
     * significa "del usuario, no de una empresa" (la tabla no tiene FK a empresas).
     * Claves (todas opcionales):
     *   tipo      'horizontal' (por defecto) | 'vertical'
     *   orden     ids de módulo en el orden elegido; [] = orden original
     *   colapsado sidebar contraído (solo íconos)
     */
    public const SIN_EMPRESA = 0;
    public const MODULO_MENU = 'menu_usuario';

    public function obtenerMenuUsuario(int $idUsuario): array
    {
        if ($idUsuario <= 0) {
            return [];
        }
        $menu = $this->repository->obtenerPreferencias($idUsuario, self::SIN_EMPRESA, self::MODULO_MENU)['menu'] ?? [];
        return is_array($menu) ? $menu : [];
    }

    /** Mezcla los cambios con lo guardado; ignora claves y valores no válidos. */
    public function guardarMenuUsuario(int $idUsuario, array $cambios): void
    {
        if ($idUsuario <= 0) {
            throw new \Exception('Usuario inválido');
        }
        $menu = $this->obtenerMenuUsuario($idUsuario);

        if (array_key_exists('tipo', $cambios)) {
            $menu['tipo'] = $cambios['tipo'] === 'vertical' ? 'vertical' : 'horizontal';
        }
        if (array_key_exists('orden', $cambios) && is_array($cambios['orden'])) {
            $menu['orden'] = array_values(array_unique(array_filter(
                array_map('intval', $cambios['orden']),
                static fn(int $id) => $id > 0
            )));
        }
        if (array_key_exists('colapsado', $cambios)) {
            $menu['colapsado'] = filter_var($cambios['colapsado'], FILTER_VALIDATE_BOOLEAN);
        }

        $this->repository->guardarPreferencia($idUsuario, self::SIN_EMPRESA, self::MODULO_MENU, 'menu', $menu);
    }
}
