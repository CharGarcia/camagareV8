<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class PlanCuentaRules
{
    public function validate(array $data): void
    {
        if (empty($data['codigo'])) {
            throw new \Exception('El código de la cuenta es obligatorio.');
        }
        if (empty($data['nombre'])) {
            throw new \Exception('El nombre de la cuenta es obligatorio.');
        }
        if (empty($data['nivel'])) {
            throw new \Exception('El nivel de la cuenta es obligatorio.');
        }

        $nivel = (int) $data['nivel'];
        if ($nivel >= 1 && $nivel <= 4) {
            if ($data['nombre'] !== mb_strtoupper($data['nombre'])) {
                throw new \Exception('Las cuentas de nivel 1 al 4 deben estar en MAYÚSCULAS.');
            }
        }

        $this->validarMapeoEcp($data);
    }

    /**
     * Mapeo Supercías ECP (Estado de Cambios en el Patrimonio):
     *  - Columna (supercias_ecp_subcodigo): componente del patrimonio, numérico (301 … 30702).
     *  - Fila de cambios (supercias_ecp_codigo): opcional; si viene, debe ser una fila de
     *    "cambios del año" válida. Se acepta también '99' por compatibilidad con mapeos antiguos
     *    (equivale a dejarla vacía: se usa la fila por defecto de la columna).
     */
    private function validarMapeoEcp(array $data): void
    {
        $columna = trim((string) ($data['supercias_ecp_subcodigo'] ?? ''));
        $fila    = trim((string) ($data['supercias_ecp_codigo'] ?? ''));

        if ($columna !== '' && !ctype_digit($columna)) {
            throw new \Exception('Supercias ECP Columna debe ser numérica (ej. 301, 30401, 30601).');
        }
        if ($fila !== '' && $fila !== '99'
            && !in_array($fila, \App\Services\modulos\EstadosFinancierosService::ECP_FILAS_CAMBIO, true)) {
            throw new \Exception('Supercias ECP Fila de cambios no válida. Use una de: ' . implode(', ', \App\Services\modulos\EstadosFinancierosService::ECP_FILAS_CAMBIO) . ' o déjela vacía.');
        }
        if ($fila !== '' && $columna === '') {
            throw new \Exception('Para fijar la fila de cambios del ECP primero indique la columna (Supercias ECP Columna).');
        }
    }
}
