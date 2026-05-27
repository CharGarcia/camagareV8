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
}
