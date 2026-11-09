<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * CRM: reglas de los canales conectados (Instagram). Decisión 5 del diseño:
 * a quién se asignan las conversaciones nuevas.
 */
class CrmCanalRules
{
    public const REGLAS_ASIGNACION = ['sin_asignar', 'rotacion', 'fija'];

    /**
     * @param array $usuariosValidos ids de usuarios asignados a la empresa
     * @return array{regla:string, usuarios:int[]} asignación normalizada
     */
    public function validarAsignacion(string $regla, array $usuarios, array $usuariosValidos): array
    {
        if (!in_array($regla, self::REGLAS_ASIGNACION, true)) {
            throw new \InvalidArgumentException('Regla de asignación no válida.');
        }
        $usuarios = array_values(array_unique(array_filter(array_map('intval', $usuarios))));
        $ajenos = array_diff($usuarios, array_map('intval', $usuariosValidos));
        if ($ajenos) {
            throw new \InvalidArgumentException('Hay usuarios que no pertenecen a esta empresa.');
        }
        if ($regla === 'fija' && count($usuarios) !== 1) {
            throw new \InvalidArgumentException('Con asignación fija elija exactamente un usuario.');
        }
        if ($regla === 'rotacion' && count($usuarios) < 1) {
            throw new \InvalidArgumentException('Para repartir por turnos elija al menos un usuario.');
        }
        return ['regla' => $regla, 'usuarios' => $regla === 'sin_asignar' ? [] : $usuarios];
    }

    /**
     * Datos de la app de Meta de la empresa. El secreto es obligatorio al
     * registrarla; al editar, vacío = se conserva el guardado.
     */
    public function validarApp(string $appId, string $secreto, bool $nueva): void
    {
        if (!preg_match('/^\d{5,30}$/', $appId)) {
            throw new \InvalidArgumentException('El App ID de Instagram son solo números (cópielo del panel de la app de Meta).');
        }
        if ($nueva && $secreto === '') {
            throw new \InvalidArgumentException('Escriba el App Secret de Instagram.');
        }
        if ($secreto !== '' && !preg_match('/^[A-Za-z0-9]{16,64}$/', $secreto)) {
            throw new \InvalidArgumentException('El App Secret no tiene el formato esperado (letras y números, sin espacios).');
        }
    }

    public function validarQuitarApp(int $cuentasActivas): void
    {
        if ($cuentasActivas > 0) {
            throw new \InvalidArgumentException('Hay cuentas de Instagram conectadas con esta app. Desconéctelas primero.');
        }
    }

    /** La cuenta de Instagram debe ser profesional (empresa o creador) para usar la API. */
    public function validarTipoCuenta(string $tipo): void
    {
        if ($tipo !== '' && !in_array(strtoupper($tipo), ['BUSINESS', 'MEDIA_CREATOR', 'CREATOR'], true)) {
            throw new \InvalidArgumentException(
                'La cuenta de Instagram es personal. Cámbiela a cuenta profesional (Empresa o Creador) desde la app de Instagram y vuelva a conectar.'
            );
        }
    }
}
