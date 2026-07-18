<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio de Lotes de Transferencia.
 */
class TransferenciaLoteRules
{
    private const TIPOS_LOTE = ['PROVEEDORES', 'NOMINA', 'AMBOS'];

    public function validarCabecera(array $data): void
    {
        $tipo = $data['tipo_lote'] ?? '';
        if (!in_array($tipo, self::TIPOS_LOTE, true)) {
            throw new \InvalidArgumentException('El tipo de lote debe ser PROVEEDORES, NOMINA o AMBOS.');
        }
        if (empty($data['id_forma_pago_origen'])) {
            throw new \InvalidArgumentException('Debe seleccionar la cuenta de la empresa desde la que se transfiere.');
        }
        if (empty($data['fecha_pago'])) {
            throw new \InvalidArgumentException('Debe indicar la fecha de pago.');
        }
    }

    /**
     * Verifica que cada línea del detalle tenga cuenta bancaria destino completa.
     * @param array $detalle Filas de transferencias_lotes_detalle.
     * @return string[] Lista de errores (vacía si todo está completo).
     */
    public function validarLineasCompletas(array $detalle): array
    {
        $errores = [];
        foreach ($detalle as $d) {
            if (empty($d['id_banco_ecuador']) || empty($d['numero_cuenta'])) {
                $errores[] = 'El beneficiario "' . ($d['nombre_beneficiario'] ?? '?') . '" no tiene banco/cuenta registrada.';
            }
            if ((float) ($d['monto'] ?? 0) <= 0) {
                $errores[] = 'El beneficiario "' . ($d['nombre_beneficiario'] ?? '?') . '" tiene un monto inválido.';
            }
        }
        return $errores;
    }

    public function validarTieneLineas(array $detalle): void
    {
        if (empty($detalle)) {
            throw new \InvalidArgumentException('El lote no tiene pagos agregados.');
        }
    }
}
