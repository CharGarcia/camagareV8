<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio del módulo Comandas (POS Restaurantes).
 */
class ComandaRules
{
    public function validarAbrir(?array $mesa): void
    {
        if (!$mesa) {
            throw new Exception('La mesa no existe.');
        }
        if (($mesa['estado'] ?? '') !== 'disponible') {
            throw new Exception('La mesa no está disponible.');
        }
    }

    /** Solo se puede agregar/anular líneas o modificar la cabecera mientras la comanda está abierta. */
    public function validarPuedeModificar(?array $comanda): void
    {
        if (!$comanda) {
            throw new Exception('Comanda no encontrada.');
        }
        if (($comanda['estado'] ?? '') !== 'abierta') {
            throw new Exception('La comanda no está abierta; no se puede modificar.');
        }
    }

    public function validarLinea(array $item): void
    {
        if (empty($item['id_producto']) && empty($item['id_menu_item'])) {
            throw new Exception('Selecciona un producto o un ítem del menú.');
        }
        if ((float) ($item['cantidad'] ?? 0) <= 0) {
            throw new Exception('La cantidad debe ser mayor a 0.');
        }
    }

    /**
     * Lote/caducidad/NUP: mismas reglas que exige Facturación (empresa →
     * Facturación) al cobrar (FacturaVentaRules/ReciboVentaRules) — se
     * validan aquí, al agregar el ítem, para no descubrir el problema recién
     * al cobrar la cuenta (con el cliente esperando y sin forma de corregirlo
     * desde esta pantalla). Solo aplica a productos inventariables reales
     * (no compuestos/kits, ver ProductoRepository::isInventariable()).
     */
    public function validarLoteCaducidadNup(bool $esInventariableControlado, array $empresaConfig, array $item): void
    {
        if (!$esInventariableControlado) {
            return;
        }
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');

        if ($toBool($empresaConfig['obligatorio_lotes'] ?? false) && empty($item['lote'])) {
            throw new Exception('Este producto exige indicar el número de lote.');
        }
        if ($toBool($empresaConfig['obligatorio_caducidad'] ?? false) && empty($item['caducidad'])) {
            throw new Exception('Este producto exige indicar la fecha de caducidad.');
        }
        if ($toBool($empresaConfig['obligatorio_nup'] ?? false) && empty($item['nup'])) {
            throw new Exception('Este producto exige indicar el número de serie (NUP).');
        }
    }

    /** Una línea se puede seguir editando (ej. descuento) mientras no esté anulada ni ya asignada a un grupo de cobro. */
    public function validarPuedeEditarLinea(?array $linea): void
    {
        if (!$linea) {
            throw new Exception('Ítem no encontrado.');
        }
        if (($linea['estado_linea'] ?? '') === 'anulado') {
            throw new Exception('Este ítem está anulado.');
        }
        if (!empty($linea['id_grupo_cobro'])) {
            throw new Exception('Este ítem ya está en un grupo de cobro; no se puede modificar.');
        }
    }

    /** Solo se puede restaurar un ítem que esté eliminado ("anulado"). */
    public function validarPuedeRestaurarLinea(?array $linea): void
    {
        if (!$linea) {
            throw new Exception('Ítem no encontrado.');
        }
        if (($linea['estado_linea'] ?? '') !== 'anulado') {
            throw new Exception('Este ítem no está eliminado.');
        }
    }

    /** Al menos una línea para armar un grupo de cobro. */
    public function validarLineasParaGrupo(array $lineas): void
    {
        if (empty($lineas)) {
            throw new Exception('No hay ítems para cobrar.');
        }
    }

    /**
     * Un ítem que todavía está "preparando" (la cocina/barra ya lo empezó
     * pero no lo ha terminado) no se puede cobrar todavía — podría cambiar,
     * cancelarse o tardar. Sí se puede cobrar 'pendiente'/'enviado' (aún no
     * empezó) o 'listo'/'entregado' (ya terminado).
     */
    public function validarLineaCobrable(array $linea): void
    {
        if (($linea['estado_linea'] ?? '') === 'preparando') {
            throw new Exception('El ítem "' . ($linea['descripcion'] ?? '') . '" todavía se está preparando; no se puede cobrar hasta que esté listo.');
        }
    }

    /**
     * Desde el portal QR el cliente solo puede pedir la cuenta de lo que YA
     * le sirvieron — no tiene sentido pedir pagar algo que ni siquiera ha
     * recibido (más estricto que la regla del mesero, que ya permite cobrar
     * desde 'listo'). Ver ComandaService::crearGrupoCobroQr().
     */
    public function validarLineaCobrableQr(array $linea): void
    {
        if (($linea['estado_linea'] ?? '') !== 'entregado') {
            throw new Exception('El ítem "' . ($linea['descripcion'] ?? '') . '" todavía no se ha entregado; solo puedes pedir la cuenta de lo que ya te sirvieron.');
        }
    }

    /**
     * Datos de facturación del cliente en el portal QR — mismo criterio que
     * FacturaExpressQrRules::validarSolicitudPublica() (nombre/identificación/
     * correo obligatorios, validación de cédula/RUC ecuatoriana).
     */
    public function validarDatosClienteQr(array $data): void
    {
        if (empty(trim((string) ($data['nombre'] ?? '')))) {
            throw new Exception('El nombre es obligatorio.');
        }
        if (mb_strlen(trim((string) $data['nombre'])) > 200) {
            throw new Exception('El nombre no puede superar 200 caracteres.');
        }

        $identificacion = trim((string) ($data['identificacion'] ?? ''));
        if ($identificacion === '') {
            throw new Exception('La identificación es obligatoria.');
        }
        $this->validarIdentificacionQr($identificacion, (string) ($data['tipo_identificacion'] ?? 'cedula'));

        $correo = trim((string) ($data['correo'] ?? ''));
        if ($correo === '') {
            throw new Exception('El correo electrónico es obligatorio.');
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no tiene un formato válido.');
        }
    }

    /** Mismo algoritmo módulo-10 que usa Factura Express QR — cédula/RUC ecuatorianos. */
    private function validarIdentificacionQr(string $id, string $tipo): void
    {
        if ($tipo === 'sin_ruc' || $tipo === 'pasaporte') {
            return;
        }
        $id = preg_replace('/\D/', '', $id) ?? '';

        if ($tipo === 'cedula') {
            if (strlen($id) !== 10) {
                throw new Exception('La cédula debe tener 10 dígitos.');
            }
            if (!$this->validarDigitoCedula($id)) {
                throw new Exception('La cédula ingresada no es válida.');
            }
        } elseif ($tipo === 'ruc') {
            if (strlen($id) !== 13) {
                throw new Exception('El RUC debe tener 13 dígitos.');
            }
            if (substr($id, -3) !== '001') {
                throw new Exception('El RUC debe terminar en 001.');
            }
            if (!$this->validarDigitoCedula(substr($id, 0, 10))) {
                throw new Exception('El RUC ingresado no es válido.');
            }
        }
    }

    private function validarDigitoCedula(string $cedula): bool
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

    /** Orden de avance de una línea en cocina/barra; 'anulado' se maneja aparte (anularLinea). */
    private const ORDEN_ESTADO_LINEA = ['pendiente' => 0, 'enviado' => 1, 'preparando' => 2, 'listo' => 3, 'entregado' => 4];

    /** Solo se permite avanzar (nunca retroceder ni saltar a/desde 'anulado') el estado de una línea. */
    public function validarTransicionLinea(string $actual, string $nuevo): void
    {
        if (!isset(self::ORDEN_ESTADO_LINEA[$nuevo])) {
            throw new Exception('Estado no válido.');
        }
        if ($actual === 'anulado') {
            throw new Exception('Este ítem está anulado.');
        }
        if (!isset(self::ORDEN_ESTADO_LINEA[$actual]) || self::ORDEN_ESTADO_LINEA[$nuevo] <= self::ORDEN_ESTADO_LINEA[$actual]) {
            throw new Exception('No se puede pasar de "' . $actual . '" a "' . $nuevo . '".');
        }
    }
}
