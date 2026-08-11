<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ConsolidacionGruposRules
{
    private const TIPOS_VALIDOS = ['ACTIVO', 'PASIVO', 'PATRIMONIO', 'INGRESO', 'COSTO', 'GASTO'];
    private const MODOS_VALIDOS = ['SUMA', 'UNICA'];

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

        $modo = (string) ($data['modo_consolidacion'] ?? 'SUMA');
        if (!in_array($modo, self::MODOS_VALIDOS, true)) {
            throw new \Exception('Modo de consolidación no válido.');
        }
        if ($modo === 'UNICA') {
            $idFuente = (int) ($data['id_empresa_fuente'] ?? 0);
            if ($idFuente <= 0 || !isset($empresasVistas[$idFuente])) {
                throw new \Exception('Seleccione de qué establecimiento se toma el valor cuando el concepto no se debe sumar entre establecimientos.');
            }
        }
    }
}
