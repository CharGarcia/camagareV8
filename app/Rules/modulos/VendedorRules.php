<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class VendedorRules
{
    /**
     * Valida los datos básicos de un vendedor.
     */
    public function validar(array $data, int $idVendedorActual = 0): void
    {
        if (trim($data['nombre'] ?? '') === '') {
            throw new \InvalidArgumentException('El nombre del vendedor es obligatorio.');
        }

        if (trim($data['identificacion'] ?? '') === '') {
            throw new \InvalidArgumentException('La identificación del vendedor es obligatoria.');
        }

        if (!empty($data['correo']) && !filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El formato del correo electrónico no es válido.');
        }

        $this->validarUsuarioVinculado($data, $idVendedorActual);
    }

    /**
     * El "Usuario del sistema" del vendedor debe ser un usuario activo y
     * asignado a la misma empresa: sin esto, un id cualquiera en la petición
     * dejaría a un usuario de otra empresa viendo estas ventas en el Reporte de
     * Ventas por Vendedor.
     */
    private function validarUsuarioVinculado(array $data, int $idVendedorActual): void
    {
        $idUsuarioVinculado = (int) ($data['id_usuario_vinculado'] ?? 0);
        if ($idUsuarioVinculado <= 0) {
            return;
        }

        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            throw new \InvalidArgumentException('No hay empresa activa para validar el usuario del vendedor.');
        }

        $repo = new \App\repositories\modulos\VendedorRepository();
        foreach ($repo->getUsuariosVinculables($idEmpresa, $idVendedorActual) as $u) {
            if ((int) $u['id'] === $idUsuarioVinculado) {
                return;
            }
        }

        throw new \InvalidArgumentException(
            'El usuario seleccionado no está asignado a esta empresa o no está activo.'
        );
    }
}
