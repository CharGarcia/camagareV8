<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ConsolidacionGruposRules
{
    private const TIPOS_VALIDOS = ['ACTIVO', 'PASIVO', 'PATRIMONIO', 'INGRESO', 'COSTO', 'GASTO'];

    public function validarGuardado(array $data): void
    {
        if (trim((string) ($data['nombre'] ?? '')) === '') {
            throw new \Exception('Ingrese el nombre del concepto consolidado.');
        }
        if (!in_array((string) ($data['tipo'] ?? ''), self::TIPOS_VALIDOS, true)) {
            throw new \Exception('Tipo de cuenta no válido.');
        }
        $cuentas = $data['cuentas'] ?? [];
        if (!is_array($cuentas) || count($cuentas) < 2) {
            throw new \Exception('Seleccione al menos 2 establecimientos con su cuenta equivalente — un grupo de 1 sola cuenta no consolida nada.');
        }
        $empresasVistas = [];
        foreach ($cuentas as $c) {
            $idEmpresa = (int) ($c['id_empresa'] ?? 0);
            $idCuenta  = (int) ($c['id_cuenta'] ?? 0);
            if ($idEmpresa <= 0 || $idCuenta <= 0) {
                throw new \Exception('Cada fila debe tener empresa y cuenta seleccionadas.');
            }
            if (isset($empresasVistas[$idEmpresa])) {
                throw new \Exception('No puede seleccionar dos cuentas de la misma empresa en un mismo grupo.');
            }
            $empresasVistas[$idEmpresa] = true;
        }
    }
}
