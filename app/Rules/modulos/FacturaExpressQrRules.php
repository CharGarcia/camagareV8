<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class FacturaExpressQrRules
{
    // ── Validar datos de una plantilla ────────────────────────────────────────
    public function validarPlantilla(array $data): void
    {
        if (empty(trim($data['nombre'] ?? ''))) {
            throw new \InvalidArgumentException('El nombre de la plantilla es obligatorio.');
        }
        if (mb_strlen(trim($data['nombre'])) > 150) {
            throw new \InvalidArgumentException('El nombre no puede superar 150 caracteres.');
        }
        $max = (int) ($data['max_solicitudes_hora'] ?? 10);
        if ($max < 1 || $max > 200) {
            throw new \InvalidArgumentException('El límite de solicitudes por hora debe estar entre 1 y 200.');
        }
        if (empty($data['id_establecimiento'])) {
            throw new \InvalidArgumentException('Debe seleccionar un establecimiento.');
        }
        if (empty($data['id_punto_emision'])) {
            throw new \InvalidArgumentException('Debe seleccionar un punto de emisión.');
        }
        if (empty(trim($data['forma_pago'] ?? ''))) {
            throw new \InvalidArgumentException('Debe seleccionar una forma de pago.');
        }
    }

    // ── Validar ítems de una plantilla ────────────────────────────────────────
    public function validarItems(array $items): void
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('La plantilla debe tener al menos un producto o servicio.');
        }
        foreach ($items as $idx => $item) {
            $fila = $idx + 1;
            if (empty(trim($item['descripcion'] ?? ''))) {
                throw new \InvalidArgumentException("Ítem #{$fila}: la descripción es obligatoria.");
            }
            if ((float)($item['precio_unitario'] ?? -1) < 0) {
                throw new \InvalidArgumentException("Ítem #{$fila}: el precio no puede ser negativo.");
            }
            if ((float)($item['cantidad_default'] ?? 0) <= 0) {
                throw new \InvalidArgumentException("Ítem #{$fila}: la cantidad debe ser mayor a cero.");
            }
        }
    }

    // ── Validar solicitud del formulario público ──────────────────────────────
    public function validarSolicitudPublica(array $data, array $items): void
    {
        if (empty(trim($data['nombre_cliente'] ?? ''))) {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }
        if (mb_strlen(trim($data['nombre_cliente'])) > 200) {
            throw new \InvalidArgumentException('El nombre no puede superar 200 caracteres.');
        }

        $identificacion = trim($data['identificacion'] ?? '');
        if (empty($identificacion)) {
            throw new \InvalidArgumentException('La identificación es obligatoria.');
        }
        $tipo = $data['tipo_identificacion'] ?? 'cedula';
        $this->validarIdentificacion($identificacion, $tipo);

        $correo = trim($data['correo_cliente'] ?? '');
        if (empty($correo)) {
            throw new \InvalidArgumentException('El correo electrónico es obligatorio.');
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo electrónico no tiene un formato válido.');
        }

        if (empty($items)) {
            throw new \InvalidArgumentException('Debe seleccionar al menos un producto o servicio.');
        }

        foreach ($items as $item) {
            if ((float)($item['cantidad'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('La cantidad de cada ítem debe ser mayor a cero.');
            }
        }
    }

    // ── Validar identificación ecuatoriana ────────────────────────────────────
    private function validarIdentificacion(string $id, string $tipo): void
    {
        if ($tipo === 'sin_ruc' || $tipo === 'pasaporte') {
            return; // Consumidor final / pasaporte: sin validación de dígito
        }

        $id = preg_replace('/\D/', '', $id);

        if ($tipo === 'cedula') {
            if (strlen($id) !== 10) {
                throw new \InvalidArgumentException('La cédula debe tener 10 dígitos.');
            }
            if (!$this->validarCedula($id)) {
                throw new \InvalidArgumentException('La cédula ingresada no es válida.');
            }
        } elseif ($tipo === 'ruc') {
            if (strlen($id) !== 13) {
                throw new \InvalidArgumentException('El RUC debe tener 13 dígitos.');
            }
            // Sufijo RUC debe ser 001
            if (substr($id, -3) !== '001') {
                throw new \InvalidArgumentException('El RUC debe terminar en 001.');
            }
            if (!$this->validarCedula(substr($id, 0, 10))) {
                throw new \InvalidArgumentException('El RUC ingresado no es válido.');
            }
        }
    }

    private function validarCedula(string $cedula): bool
    {
        if (strlen($cedula) !== 10) return false;
        $provincia = (int) substr($cedula, 0, 2);
        if ($provincia < 1 || $provincia > 24) return false;

        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $val = (int) $cedula[$i] * $coeficientes[$i];
            $suma += $val > 9 ? $val - 9 : $val;
        }
        $verificador = (10 - ($suma % 10)) % 10;
        return $verificador === (int) $cedula[9];
    }
}
